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

$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$resend = !empty($data['resend']);

if ($resend) {
    $pending = $_SESSION['signup_pending'] ?? null;
    if (!is_array($pending) || empty($pending['email'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'No pending sign-up. Please start again.']);
        exit;
    }
    $sentAt = (int) ($pending['sent_at'] ?? 0);
    if (time() - $sentAt < 60) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'message' => 'Please wait a minute before requesting another code.']);
        exit;
    }
    if (time() > (int) ($pending['expires_at'] ?? 0)) {
        unset($_SESSION['signup_pending']);
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'That sign-up session expired. Please start again.']);
        exit;
    }
    $code = luxe_signup_otp_code();
    $_SESSION['signup_pending']['code_hash'] = luxe_signup_code_hash($code);
    $_SESSION['signup_pending']['expires_at'] = time() + 900;
    $_SESSION['signup_pending']['sent_at'] = time();
    $_SESSION['signup_pending']['attempts'] = 0;
    $send = luxe_deliver_signup_verification_code((string) $pending['email'], $code);
    if (!$send['ok']) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not send email. Set mail.smtp in includes/config.php or mail.skip_email_send for local testing.']);
        exit;
    }
    $out = [
        'ok' => true,
        'email_hint' => luxe_mask_email((string) $pending['email']),
    ];
    if (!empty($send['dev_code'])) {
        $out['dev_code'] = $send['dev_code'];
        $out['dev_note'] = $send['dev_note'] ?? null;
    }
    echo json_encode($out);
    exit;
}

$fname = trim((string) ($data['first_name'] ?? ''));
$lname = trim((string) ($data['last_name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

if (strlen($fname) < 2 || strlen($lname) < 2) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Please enter your first and last name.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Enter a valid email address.']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    if ($st->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'An account with this email already exists.']);
        exit;
    }

    $code = luxe_signup_otp_code();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $send = luxe_deliver_signup_verification_code($email, $code);
    if (!$send['ok']) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not send verification email. Add mail.smtp in includes/config.php, or set mail.skip_email_send for local dev.']);
        exit;
    }

    $_SESSION['signup_pending'] = [
        'email' => $email,
        'first_name' => $fname,
        'last_name' => $lname,
        'password_hash' => $hash,
        'code_hash' => luxe_signup_code_hash($code),
        'expires_at' => time() + 900,
        'sent_at' => time(),
        'attempts' => 0,
    ];

    $out = [
        'ok' => true,
        'email_hint' => luxe_mask_email($email),
    ];
    if (!empty($send['dev_code'])) {
        $out['dev_code'] = $send['dev_code'];
        $out['dev_note'] = $send['dev_note'] ?? null;
    }
    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error. Check database configuration.']);
}
