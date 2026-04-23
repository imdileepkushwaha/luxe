<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/signup_mail.php';
require_once __DIR__ . '/../includes/notification_mail.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
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
    echo json_encode(['ok' => false, 'message' => 'Enter the 6-digit code from your email.']);
    exit;
}

$pending = $_SESSION['signup_pending'] ?? null;
if (!is_array($pending) || empty($pending['email']) || empty($pending['code_hash']) || empty($pending['password_hash'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'No pending sign-up. Request a new code from the form.']);
    exit;
}

if (time() > (int) ($pending['expires_at'])) {
    unset($_SESSION['signup_pending']);
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'That code has expired. Request a new one.']);
    exit;
}

$attempts = (int) ($pending['attempts'] ?? 0);
if ($attempts >= 8) {
    unset($_SESSION['signup_pending']);
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Too many wrong attempts. Please start sign-up again.']);
    exit;
}

$expected = (string) $pending['code_hash'];
$actual = luxe_signup_code_hash($raw);
if (!hash_equals($expected, $actual)) {
    $_SESSION['signup_pending']['attempts'] = $attempts + 1;
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid code. Try again or resend a new code.']);
    exit;
}

$email = (string) $pending['email'];
$fname = (string) ($pending['first_name'] ?? '');
$lname = (string) ($pending['last_name'] ?? '');
$passwordHash = (string) $pending['password_hash'];

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    if ($st->fetch()) {
        unset($_SESSION['signup_pending']);
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'code' => 'email_taken',
            'message' => 'This email is already registered. Sign in or use a different email.',
        ]);
        exit;
    }

    $ins = $pdo->prepare(
        'INSERT INTO users (email, password_hash, first_name, last_name, email_verified_at) VALUES (?,?,?,?, CURRENT_TIMESTAMP)'
    );
    $ins->execute([$email, $passwordHash, $fname, $lname]);
    unset($_SESSION['signup_pending']);
    auth_set_user((int) $pdo->lastInsertId());
    $fullName = trim($fname . ' ' . $lname);
    luxe_send_welcome_email($email, $fullName, 'user');
    echo json_encode(['ok' => true, 'redirect' => 'index.php']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error. Check database configuration.']);
}
