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
    echo json_encode(['ok' => false, 'message' => 'Please sign in.']);
    exit;
}

$pdo = db();
$st = $pdo->prepare('SELECT id, email, first_name, last_name FROM users WHERE id = ? LIMIT 1');
$st->execute([$userId]);
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Account not found.']);
    exit;
}

$result = account_deletion_request_create(
    $pdo,
    $userId,
    (string) $user['email'],
    (string) $user['first_name'],
    (string) $user['last_name']
);

if ($result !== true) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => $result]);
    exit;
}

$msg = 'Your deletion request has been submitted. Your account will be removed within 48 hours. '
    . 'Aapki request admin ko mil gayi hai — aapka account 48 ghante ke andar delete ho jayega.';

echo json_encode([
    'ok' => true,
    'message' => $msg,
    'hours' => ACCOUNT_DELETION_HOURS,
]);
