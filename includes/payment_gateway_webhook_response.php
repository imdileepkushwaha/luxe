<?php

declare(strict_types=1);

/**
 * Shared stub response for payment provider webhooks (platform + legacy URL).
 */
function payment_gateway_webhook_send_stub_response(): void
{
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
        exit;
    }

    http_response_code(200);
    echo json_encode(['ok' => true, 'received' => true]);
}
