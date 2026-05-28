<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * @return array{enabled: bool, provider: string, site_key: string, secret_key: string}
 */
function luxe_captcha_config(): array
{
    $cfg = luxe_app_config()['captcha'] ?? [];
    if (!is_array($cfg)) {
        $cfg = [];
    }

    $provider = strtolower(trim((string) ($cfg['provider'] ?? 'builtin')));
    if (!in_array($provider, ['builtin', 'recaptcha'], true)) {
        $provider = 'builtin';
    }

    $enabled = array_key_exists('enabled', $cfg)
        ? (bool) $cfg['enabled']
        : ($provider === 'builtin' || (trim((string) ($cfg['site_key'] ?? '')) !== '' && trim((string) ($cfg['secret_key'] ?? '')) !== ''));

    return [
        'enabled' => $enabled,
        'provider' => $provider,
        'site_key' => trim((string) ($cfg['site_key'] ?? '')),
        'secret_key' => trim((string) ($cfg['secret_key'] ?? '')),
    ];
}

function luxe_captcha_enabled(): bool
{
    return luxe_captcha_config()['enabled'];
}

function luxe_captcha_provider(): string
{
    return luxe_captcha_config()['provider'];
}

function luxe_captcha_site_key(): string
{
    return luxe_captcha_config()['site_key'];
}

function luxe_captcha_ui_context(): string
{
    if (defined('LUXE_CAPTCHA_UI_CONTEXT')) {
        $forced = trim((string) LUXE_CAPTCHA_UI_CONTEXT);
        if ($forced !== '') {
            return $forced;
        }
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#/(seller|admin)(/|$)#', $script)) {
        return 'admin';
    }

    $themeSlug = function_exists('luxe_storefront_theme_slug') ? luxe_storefront_theme_slug() : '';
    if ($themeSlug !== '' && preg_match('#^theme-\d+$#', $themeSlug)) {
        return $themeSlug;
    }

    if (preg_match('#/(theme-\d+)(/|$)#', $script, $m)) {
        return $m[1];
    }

    return 'default';
}

/**
 * Optional per-theme captcha skin (matches that theme's login form inputs).
 */
function luxe_captcha_theme_stylesheet_href(): string
{
    $context = luxe_captcha_ui_context();
    if (!preg_match('#^theme-\d+$#', $context)) {
        return '';
    }

    $projectRoot = dirname(__DIR__);
    $relative = $context . '/css/captcha.css';
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        return '';
    }

    return luxe_public_href($relative);
}

function luxe_captcha_pepper(): string
{
    $cfg = luxe_app_config()['captcha'] ?? [];
    $salt = is_array($cfg) ? trim((string) ($cfg['secret_salt'] ?? '')) : '';
    if ($salt !== '') {
        return $salt;
    }

    return 'luxe-builtin-captcha-v1';
}

function luxe_captcha_normalize_scope(string $scope): string
{
    $scope = preg_replace('/[^a-zA-Z0-9_-]/', '', $scope) ?? '';

    return $scope !== '' ? $scope : 'luxe-captcha';
}

function luxe_captcha_answer_hash(string $answer): string
{
    return hash_hmac('sha256', $answer, luxe_captcha_pepper());
}

/**
 * @return array{scope: string, question: string}
 */
function luxe_captcha_issue_challenge(string $scope): array
{
    $scope = luxe_captcha_normalize_scope($scope);

    $a = random_int(2, 18);
    $b = random_int(1, 12);
    if (random_int(0, 1) === 1 && $a >= $b) {
        $question = $a . ' - ' . $b;
        $answer = (string) ($a - $b);
    } else {
        $question = $a . ' + ' . $b;
        $answer = (string) ($a + $b);
    }

    if (!isset($_SESSION['luxe_captcha_challenges']) || !is_array($_SESSION['luxe_captcha_challenges'])) {
        $_SESSION['luxe_captcha_challenges'] = [];
    }

    $_SESSION['luxe_captcha_challenges'][$scope] = [
        'hash' => luxe_captcha_answer_hash($answer),
        'expires' => time() + 900,
        'question' => $question,
    ];

    return [
        'scope' => $scope,
        'question' => $question,
    ];
}

/**
 * @return array{scope: string, question: string}
 */
function luxe_captcha_get_or_create_challenge(string $scope): array
{
    $scope = luxe_captcha_normalize_scope($scope);
    $existing = $_SESSION['luxe_captcha_challenges'][$scope] ?? null;
    if (
        is_array($existing)
        && !empty($existing['hash'])
        && time() <= (int) ($existing['expires'] ?? 0)
    ) {
        $question = trim((string) ($existing['question'] ?? ''));
        if ($question !== '') {
            return ['scope' => $scope, 'question' => $question];
        }
    }

    return luxe_captcha_issue_challenge($scope);
}

function luxe_captcha_clear_challenge(string $scope): void
{
    $scope = luxe_captcha_normalize_scope($scope);
    if (isset($_SESSION['luxe_captcha_challenges'][$scope])) {
        unset($_SESSION['luxe_captcha_challenges'][$scope]);
    }
}

/**
 * @param array<string, mixed>|null $jsonData
 */
function luxe_captcha_scope_from_request(?array $jsonData = null, string $default = 'luxe-captcha-login'): string
{
    if ($jsonData !== null && !empty($jsonData['captcha_scope'])) {
        return luxe_captcha_normalize_scope((string) $jsonData['captcha_scope']);
    }

    $posted = trim((string) ($_POST['captcha_scope'] ?? ''));

    return $posted !== '' ? luxe_captcha_normalize_scope($posted) : luxe_captcha_normalize_scope($default);
}

/**
 * @param array<string, mixed>|null $jsonData
 */
function luxe_captcha_answer_from_request(?array $jsonData = null): string
{
    if ($jsonData !== null) {
        if (isset($jsonData['captcha_token'])) {
            return trim((string) $jsonData['captcha_token']);
        }
        if (isset($jsonData['captcha_answer'])) {
            return trim((string) $jsonData['captcha_answer']);
        }
    }

    $answer = trim((string) ($_POST['captcha_answer'] ?? ''));
    if ($answer !== '') {
        return $answer;
    }

    return trim((string) ($_POST['g-recaptcha-response'] ?? ''));
}

/**
 * @return array{ok: bool, message: string}
 */
function luxe_captcha_verify_builtin(string $scope, string $answer): array
{
    $scope = luxe_captcha_normalize_scope($scope);
    $answer = preg_replace('/\s+/', '', $answer) ?? '';

    if ($answer === '' || !preg_match('/^-?\d+$/', $answer)) {
        return ['ok' => false, 'message' => 'Please solve the security check.'];
    }

    $stored = $_SESSION['luxe_captcha_challenges'][$scope] ?? null;
    if (!is_array($stored) || empty($stored['hash'])) {
        return ['ok' => false, 'message' => 'Security check expired. Refresh and try again.'];
    }

    if (time() > (int) ($stored['expires'] ?? 0)) {
        luxe_captcha_clear_challenge($scope);

        return ['ok' => false, 'message' => 'Security check expired. Refresh and try again.'];
    }

    if (!hash_equals((string) $stored['hash'], luxe_captcha_answer_hash($answer))) {
        return ['ok' => false, 'message' => 'Incorrect security check answer. Try again.'];
    }

    luxe_captcha_clear_challenge($scope);

    return ['ok' => true, 'message' => ''];
}

/**
 * @return array{ok: bool, message: string}
 */
function luxe_captcha_verify_recaptcha(string $token): array
{
    if ($token === '') {
        return ['ok' => false, 'message' => 'Please complete the CAPTCHA verification.'];
    }

    $secret = luxe_captcha_config()['secret_key'];
    $postFields = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);

    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        if ($ch !== false) {
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
    }

    if ($raw === false) {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postFields,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    }

    if ($raw === false || $raw === '') {
        return ['ok' => false, 'message' => 'CAPTCHA verification failed. Please try again.'];
    }

    try {
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'message' => 'CAPTCHA verification failed. Please try again.'];
    }

    if (!empty($data['success'])) {
        return ['ok' => true, 'message' => ''];
    }

    return ['ok' => false, 'message' => 'CAPTCHA verification failed. Please try again.'];
}

/**
 * @return array{ok: bool, message: string}
 */
function luxe_captcha_verify(string $scope, string $answer): array
{
    if (!luxe_captcha_enabled()) {
        return ['ok' => true, 'message' => ''];
    }

    if (luxe_captcha_provider() === 'recaptcha') {
        return luxe_captcha_verify_recaptcha($answer);
    }

    return luxe_captcha_verify_builtin($scope, $answer);
}

/**
 * @param array<string, mixed>|null $jsonData
 */
function luxe_captcha_require_json(?array $jsonData = null, string $scope = 'luxe-captcha-login'): void
{
    $scope = luxe_captcha_scope_from_request($jsonData, $scope);
    $result = luxe_captcha_verify($scope, luxe_captcha_answer_from_request($jsonData));
    if (!$result['ok']) {
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'code' => 'captcha_failed',
            'message' => $result['message'],
        ]);
        exit;
    }
}

function luxe_captcha_require_form(string $scope = 'luxe-captcha-login'): string
{
    $scope = luxe_captcha_scope_from_request(null, $scope);
    $result = luxe_captcha_verify($scope, luxe_captcha_answer_from_request());
    if (!$result['ok']) {
        return $result['message'];
    }

    return '';
}
