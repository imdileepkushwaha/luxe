<?php

declare(strict_types=1);

/**
 * Razorpay REST helpers (Orders API + payment signature verify).
 *
 * Keys (pehla match jo dono fields bhar de):
 * 1) includes/config.php + env — agar key_id aur key_secret dono non-empty
 * 2) Admin → Settings → Payments (`platform_payment_gateway_config`, gateway = razorpay) — public_key = Key ID, secret_key = Key Secret
 */
function luxe_razorpay_credentials_from_file(): array
{
    $cfg = luxe_app_config();
    $r = is_array($cfg['razorpay'] ?? null) ? $cfg['razorpay'] : [];
    $keyId = trim((string) ($r['key_id'] ?? ''));
    $keySecret = trim((string) ($r['key_secret'] ?? ''));
    if ($keyId === '') {
        $keyId = trim((string) (getenv('LUXE_RAZORPAY_KEY_ID') ?: getenv('RAZORPAY_KEY_ID') ?: ''));
    }
    if ($keySecret === '') {
        $keySecret = trim((string) (getenv('LUXE_RAZORPAY_KEY_SECRET') ?: getenv('RAZORPAY_KEY_SECRET') ?: ''));
    }

    return [
        'key_id' => $keyId,
        'key_secret' => $keySecret,
    ];
}

/**
 * @return array{key_id: string, key_secret: string}
 */
function luxe_razorpay_credentials(?PDO $pdo = null): array
{
    $fromFile = luxe_razorpay_credentials_from_file();
    if ($fromFile['key_id'] !== '' && $fromFile['key_secret'] !== '') {
        return $fromFile;
    }
    if ($pdo !== null) {
        require_once __DIR__ . '/platform_payment_gateway.php';
        $pg = platform_payment_gateway_load($pdo);
        $gw = strtolower(trim((string) ($pg['gateway'] ?? '')));
        $pub = trim((string) ($pg['public_key'] ?? ''));
        $sec = trim((string) ($pg['secret_key'] ?? ''));
        if ($gw === 'razorpay' && strlen($pub) >= 8 && strlen($sec) >= 8) {
            return ['key_id' => $pub, 'key_secret' => $sec];
        }
    }

    return $fromFile;
}

function luxe_razorpay_configured(?PDO $pdo = null): bool
{
    $c = luxe_razorpay_credentials($pdo);

    return $c['key_id'] !== '' && $c['key_secret'] !== '';
}

/** Local / staging: online checkout bina Razorpay API — production par false rakho. */
function luxe_razorpay_dev_skip_payment(): bool
{
    $cfg = luxe_app_config();
    $r = $cfg['razorpay'] ?? null;

    return is_array($r) && !empty($r['dev_skip_gateway']);
}

/**
 * @return array{ok:bool,status:int,data:?array<string,mixed>,error?:string}
 */
function luxe_razorpay_api_json(string $method, string $path, ?array $body, string $keyId, string $keySecret): array
{
    $url = 'https://api.razorpay.com/v1' . $path;
    $auth = base64_encode($keyId . ':' . $keySecret);
    $payload = $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'curl_init failed'];
        }
        $headers = ['Authorization: Basic ' . $auth, 'Content-Type: application/json'];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => "Authorization: Basic {$auth}\r\nContent-Type: application/json\r\n",
                'content' => $payload ?? '',
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
    }

    if ($raw === false || $raw === '') {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Empty Razorpay response'];
    }
    try {
        /** @var array<string,mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Invalid JSON from Razorpay'];
    }

    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => $data];
}

/**
 * @param array<string, string> $notes Razorpay requires string values in notes.
 * @return array{ok:bool,order?:array<string,mixed>,error?:string}
 */
function luxe_razorpay_create_order_api(int $amountPaise, string $receipt, array $notes, string $keyId, string $keySecret): array
{
    $body = [
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => $receipt,
        'payment_capture' => 1,
        'notes' => $notes,
    ];
    $res = luxe_razorpay_api_json('POST', '/orders', $body, $keyId, $keySecret);
    if (!$res['ok'] || !is_array($res['data'])) {
        $err = is_array($res['data'] ?? null) ? (string) (($res['data']['error']['description'] ?? $res['data']['message'] ?? '') ?: ($res['error'] ?? 'Razorpay order failed')) : ($res['error'] ?? 'Razorpay order failed');

        return ['ok' => false, 'error' => $err !== '' ? $err : 'Razorpay order failed'];
    }

    return ['ok' => true, 'order' => $res['data']];
}

/**
 * @return array<string,mixed>|null
 */
function luxe_razorpay_fetch_order_api(string $razorpayOrderId, string $keyId, string $keySecret): ?array
{
    $path = '/orders/' . rawurlencode($razorpayOrderId);
    $res = luxe_razorpay_api_json('GET', $path, null, $keyId, $keySecret);
    if (!$res['ok'] || !is_array($res['data'])) {
        return null;
    }

    return $res['data'];
}

function luxe_razorpay_verify_payment_signature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature, string $keySecret): bool
{
    $expected = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $keySecret);

    return hash_equals($expected, $razorpaySignature);
}
