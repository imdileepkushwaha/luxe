<?php

declare(strict_types=1);

/**
 * Legacy webhook URL (per-seller query). Forwards to the same stub as the platform endpoint.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/payment_gateway_webhook_response.php';

payment_gateway_webhook_send_stub_response();
