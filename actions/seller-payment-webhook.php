<?php
declare(strict_types=1);

/**
 * Stub endpoint for seller payment gateway webhooks.
 * Production: verify gateway signatures, persist events, idempotency.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true, 'received' => true]);
