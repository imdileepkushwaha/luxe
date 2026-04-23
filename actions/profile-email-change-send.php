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

$resend = !empty($data['resend']);

if ($resend) {
    $pending = $_SESSION['profile_email_change'] ?? null;
    if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) !== $userId || empty($pending['new_email'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'No pending email change. Start again from your profile.']);
        exit;
    }
    $sentAt = (int) ($pending['sent_at'] ?? 0);
    if (time() - $sentAt < 60) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'message' => 'Please wait a minute before requesting another code.']);
        exit;
    }
    if (time() > (int) ($pending['expires_at'] ?? 0)) {
        unset($_SESSION['profile_email_change']);
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'That session has expired. Please start again.']);
        exit;
    }
    $code = luxe_signup_otp_code();
    $send = luxe_deliver_verification_code_email(
        (string) $pending['new_email'],
        'Confirm your new LUXE email',
        $code,
        'If you did not request this change, ignore this email.'
    );
    if (!$send['ok']) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not send email. Check your SMTP settings.']);
        exit;
    }
    $_SESSION['profile_email_change']['code_hash'] = luxe_signup_code_hash($code);
    $_SESSION['profile_email_change']['expires_at'] = time() + 900;
    $_SESSION['profile_email_change']['sent_at'] = time();
    $_SESSION['profile_email_change']['attempts'] = 0;
    $out = ['ok' => true, 'email_hint' => luxe_mask_email((string) $pending['new_email'])];
    if (!empty($send['dev_code'])) {
        $out['dev_code'] = $send['dev_code'];
        $out['dev_note'] = $send['dev_note'] ?? null;
    }
    echo json_encode($out);
    exit;
}

$newEmail = trim((string) ($data['new_email'] ?? ''));
if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Please enter a valid new email address.']);
    exit;
}

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Account not found.']);
        exit;
    }
    $current = (string) $row['email'];
    if (strcasecmp($current, $newEmail) === 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'This email is already on your account.']);
        exit;
    }
    $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
    $dup->execute([$newEmail, $userId]);
    if ($dup->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'This email is already used by another account.']);
        exit;
    }

    $code = luxe_signup_otp_code();
    $send = luxe_deliver_verification_code_email(
        $newEmail,
        'Confirm your new LUXE email',
        $code,
        'If you did not request this change, ignore this email.'
    );
    if (!$send['ok']) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not send the verification email.']);
        exit;
    }

    $_SESSION['profile_email_change'] = [
        'user_id' => $userId,
        'new_email' => $newEmail,
        'code_hash' => luxe_signup_code_hash($code),
        'expires_at' => time() + 900,
        'sent_at' => time(),
        'attempts' => 0,
    ];

    $out = [
        'ok' => true,
        'email_hint' => luxe_mask_email($newEmail),
    ];
    if (!empty($send['dev_code'])) {
        $out['dev_code'] = $send['dev_code'];
        $out['dev_note'] = $send['dev_note'] ?? null;
    }
    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error.']);
}
