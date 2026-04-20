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

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$points = (int) ($data['points'] ?? 0);
$pdo = db();
$result = loyalty_try_redeem($pdo, $userId, $points);

if (!$result['ok']) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $result['message']]);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => $result['message'],
    'balance' => (int) $result['balance'],
], JSON_THROW_ON_ERROR);
