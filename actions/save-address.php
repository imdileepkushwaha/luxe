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

$parsed = addresses_validate_payload($data);
if (empty($parsed['ok'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => (string) ($parsed['message'] ?? 'Invalid data')]);
    exit;
}

$v = $parsed;
unset($v['ok']);

$pdo = db();
$addressId = isset($data['id']) ? (int) $data['id'] : 0;

try {
    if ($addressId > 0) {
        $existing = addresses_get_for_user($pdo, $userId, $addressId);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Address not found.']);
            exit;
        }
        $ok = addresses_update($pdo, $userId, $addressId, $v);
        if (!$ok) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Could not update address.']);
            exit;
        }
    } else {
        addresses_insert($pdo, $userId, $v);
    }
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Something went wrong. Try again.']);
    exit;
}

$list = addresses_fetch_for_user($pdo, $userId);
echo json_encode(['ok' => true, 'addresses' => $list], JSON_THROW_ON_ERROR);
