<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/captcha.php';

header('Content-Type: application/json; charset=utf-8');

if (!luxe_captcha_enabled() || luxe_captcha_provider() !== 'builtin') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'CAPTCHA refresh unavailable.']);
    exit;
}

$scope = luxe_captcha_normalize_scope((string) ($_GET['scope'] ?? $_POST['scope'] ?? 'luxe-captcha-login'));
$challenge = luxe_captcha_issue_challenge($scope);
$_SESSION['luxe_captcha_challenges'][$scope]['question'] = $challenge['question'];

echo json_encode([
    'ok' => true,
    'scope' => $challenge['scope'],
    'question' => $challenge['question'],
]);
