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

function luxe_profile_phone_digits(string $p): string
{
    return preg_replace('/\D/', '', $p) ?? '';
}

$resend = !empty($data['resend']);

if ($resend) {
    $pending = $_SESSION['profile_phone_change'] ?? null;
    if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) !== $userId || empty($pending['new_phone'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'No pending phone update.']);
        exit;
    }
    $sentAt = (int) ($pending['sent_at'] ?? 0);
    if (time() - $sentAt < 60) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'message' => 'Please wait a minute before requesting another code.']);
        exit;
    }
    if (time() > (int) ($pending['expires_at'] ?? 0)) {
        unset($_SESSION['profile_phone_change']);
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'That session has expired.']);
        exit;
    }
    $pdo = db();
    $u = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $u->execute([$userId]);
    $acc = $u->fetch();
    $toEmail = $acc ? (string) $acc['email'] : '';
    if ($toEmail === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'No email address on file for this account.']);
        exit;
    }
    $code = luxe_signup_otp_code();
    $newPhone = (string) $pending['new_phone'];
    $send = luxe_deliver_verification_code_email(
        $toEmail,
        'Verify your LUXE mobile number',
        $code,
        'Use this code to confirm mobile number ' . $newPhone . ' on your LUXE account. If you did not request this, ignore this email.'
    );
    if (!$send['ok']) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not send the code by email.']);
        exit;
    }
    $_SESSION['profile_phone_change']['code_hash'] = luxe_signup_code_hash($code);
    $_SESSION['profile_phone_change']['expires_at'] = time() + 900;
    $_SESSION['profile_phone_change']['sent_at'] = time();
    $_SESSION['profile_phone_change']['attempts'] = 0;
    $out = ['ok' => true, 'email_hint' => luxe_mask_email($toEmail)];
    if (!empty($send['dev_code'])) {
        $out['dev_code'] = $send['dev_code'];
        $out['dev_note'] = $send['dev_note'] ?? null;
    }
    echo json_encode($out);
    exit;
}

$newPhone = trim((string) ($data['new_phone'] ?? ''));
$digits = luxe_profile_phone_digits($newPhone);
if (strlen($digits) < 10 || strlen($digits) > 15) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Enter a mobile number with 10–15 digits (country code optional).']);
    exit;
}
if (strlen($newPhone) > 40) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'That phone number is too long.']);
    exit;
}

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT email, phone FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Account not found.']);
        exit;
    }
    $toEmail = trim((string) $row['email']);
    if ($toEmail === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Add an email to your account before verifying a phone number.']);
        exit;
    }
    $curDigits = luxe_profile_phone_digits((string) ($row['phone'] ?? ''));
    if ($curDigits === $digits && $digits !== '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'This number is already saved on your profile.']);
        exit;
    }

    $code = luxe_signup_otp_code();
    $send = luxe_deliver_verification_code_email(
        $toEmail,
        'Verify your LUXE mobile number',
        $code,
        'Use this code to confirm mobile number ' . $newPhone . ' on your LUXE account. If you did not request this, ignore this email.'
    );
    if (!$send['ok']) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not send the code to your registered email.']);
        exit;
    }

    $_SESSION['profile_phone_change'] = [
        'user_id' => $userId,
        'new_phone' => $newPhone,
        'code_hash' => luxe_signup_code_hash($code),
        'expires_at' => time() + 900,
        'sent_at' => time(),
        'attempts' => 0,
    ];

    $out = [
        'ok' => true,
        'email_hint' => luxe_mask_email($toEmail),
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
