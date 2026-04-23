<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/signup_mail.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = auth_user_id();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please sign in.']);
    exit;
}

$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$raw = preg_replace('/\D/', '', (string) ($data['code'] ?? ''));
if (strlen($raw) !== 4) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Enter the 4-digit code.']);
    exit;
}

$pending = $_SESSION['profile_phone_change'] ?? null;
if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) !== $userId || empty($pending['new_phone']) || empty($pending['code_hash'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Send a code to your registered email first.']);
    exit;
}

if (time() > (int) ($pending['expires_at'])) {
    unset($_SESSION['profile_phone_change']);
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'That code has expired.']);
    exit;
}

$attempts = (int) ($pending['attempts'] ?? 0);
if ($attempts >= 8) {
    unset($_SESSION['profile_phone_change']);
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Too many wrong attempts. Please start again.']);
    exit;
}

$expected = (string) $pending['code_hash'];
if (!hash_equals($expected, luxe_signup_code_hash($raw))) {
    $_SESSION['profile_phone_change']['attempts'] = $attempts + 1;
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid code.']);
    exit;
}

$newPhone = (string) $pending['new_phone'];

try {
    $pdo = db();
    $upd = $pdo->prepare('UPDATE users SET phone = ?, phone_verified_at = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1');
    $upd->execute([$newPhone, $userId]);
    unset($_SESSION['profile_phone_change']);

    echo json_encode([
        'ok' => true,
        'phone' => $newPhone,
        'phone_verified' => true,
        'message' => 'Your mobile number is verified.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Could not update your account.']);
}
