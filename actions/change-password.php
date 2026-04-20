<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = auth_user_id();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please sign in to change your password.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$current = (string) ($data['current_password'] ?? '');
$new = (string) ($data['new_password'] ?? '');

if ($current === '' || $new === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Current password and new password are required.']);
    exit;
}

if (strlen($new) < 8) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'New password must be at least 8 characters.']);
    exit;
}

if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'New password must include at least one letter and one number.']);
    exit;
}

if ($new === $current) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'New password must be different from your current password.']);
    exit;
}

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row || empty($row['password_hash'])) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Could not verify your account.']);
        exit;
    }

    if (!password_verify($current, (string) $row['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $up = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $up->execute([$newHash, $userId]);

    echo json_encode(['ok' => true, 'message' => 'Password updated successfully.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Could not update password. Try again later.']);
}
