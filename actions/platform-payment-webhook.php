<?php

declare(strict_types=1);

/**
 * Platform payment gateway webhook endpoint (configure in provider dashboard).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/payment_gateway_webhook_response.php';

payment_gateway_webhook_send_stub_response();
