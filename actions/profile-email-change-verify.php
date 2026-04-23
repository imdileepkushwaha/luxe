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
if (strlen($raw) !== 6) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Enter the 6-digit code.']);
    exit;
}

$pending = $_SESSION['profile_email_change'] ?? null;
if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) !== $userId || empty($pending['new_email']) || empty($pending['code_hash'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Send a code to your new email first.']);
    exit;
}

if (time() > (int) ($pending['expires_at'])) {
    unset($_SESSION['profile_email_change']);
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'That code has expired. Request a new one.']);
    exit;
}

$attempts = (int) ($pending['attempts'] ?? 0);
if ($attempts >= 8) {
    unset($_SESSION['profile_email_change']);
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Too many wrong attempts. Start the email change again.']);
    exit;
}

$expected = (string) $pending['code_hash'];
if (!hash_equals($expected, luxe_signup_code_hash($raw))) {
    $_SESSION['profile_email_change']['attempts'] = $attempts + 1;
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid code. Try again.']);
    exit;
}

$newEmail = (string) $pending['new_email'];

try {
    $pdo = db();
    $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $dup->execute([$newEmail, $userId]);
    if ($dup->fetch()) {
        unset($_SESSION['profile_email_change']);
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'That email was just taken by another account.']);
        exit;
    }

    $upd = $pdo->prepare('UPDATE users SET email = ?, email_verified_at = CURRENT_TIMESTAMP WHERE id = ? LIMIT 1');
    $upd->execute([$newEmail, $userId]);
    unset($_SESSION['profile_email_change']);

    echo json_encode([
        'ok' => true,
        'email' => $newEmail,
        'email_verified' => true,
        'message' => 'Your new email is confirmed.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Could not update your account.']);
}
