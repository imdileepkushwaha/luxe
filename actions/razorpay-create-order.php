<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart_session.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/../includes/checkout_order_quote.php';
require_once __DIR__ . '/../includes/razorpay.php';

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

if (luxe_razorpay_dev_skip_payment()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'dev_skip_gateway is on — checkout se direct Place order use karo; Razorpay order banane ki zaroorat nahi.']);
    exit;
}

if (!luxe_razorpay_configured($pdo)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Online payment is not configured. Admin → Settings → Payments (Razorpay) ya includes/config.php — ya COD use karein.']);
    exit;
}

$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No items to order.']);
    exit;
}

$items = cart_merge_duplicate_lines($data['items']);
$deliverySpeedRaw = $data['delivery_speed'] ?? 'standard';
$deliverySpeed = 'standard';
if (is_string($deliverySpeedRaw) && in_array($deliverySpeedRaw, ['standard', 'express', 'same_day'], true)) {
    $deliverySpeed = $deliverySpeedRaw;
}
$couponCode = coupons_normalize_code((string) ($data['coupon_code'] ?? ''));

try {
    $quote = checkout_order_quote($pdo, $items, $deliverySpeed, $couponCode);
    if (!($quote['ok'] ?? false)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => (string) ($quote['message'] ?? 'Could not price order.')]);
        exit;
    }
    $orderTotalRupees = (int) ($quote['order_total'] ?? 0);
    if ($orderTotalRupees < 1) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Order total must be at least ₹1 for online payment.']);
        exit;
    }
    $amountPaise = $orderTotalRupees * 100;
    if ($amountPaise < 100) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Invalid amount for Razorpay.']);
        exit;
    }

    $nonce = bin2hex(random_bytes(16));
    $receipt = 'LUXE' . substr((string) time(), -8) . substr(bin2hex(random_bytes(3)), 0, 4);
    if (strlen($receipt) > 40) {
        $receipt = substr($receipt, 0, 40);
    }

    $creds = luxe_razorpay_credentials($pdo);
    $notes = [
        'luxe_user_id' => (string) $userId,
        'luxe_nonce' => $nonce,
    ];
    $api = luxe_razorpay_create_order_api($amountPaise, $receipt, $notes, $creds['key_id'], $creds['key_secret']);
    if (!($api['ok'] ?? false) || !is_array($api['order'] ?? null)) {
        http_response_code(502);
        echo json_encode(['ok' => false, 'message' => (string) ($api['error'] ?? 'Could not create Razorpay order.')]);
        exit;
    }
    $order = $api['order'];
    $rzOrderId = (string) ($order['id'] ?? '');
    if ($rzOrderId === '') {
        http_response_code(502);
        echo json_encode(['ok' => false, 'message' => 'Invalid Razorpay response.']);
        exit;
    }

    $_SESSION['luxe_razorpay_checkout'] = [
        'nonce' => $nonce,
        'amount_rupees' => $orderTotalRupees,
        'razorpay_order_id' => $rzOrderId,
        'created_at' => time(),
    ];

    echo json_encode([
        'ok' => true,
        'key_id' => $creds['key_id'],
        'order_id' => $rzOrderId,
        'amount' => $amountPaise,
        'currency' => 'INR',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error creating payment.']);
    error_log('razorpay-create-order: ' . $e->getMessage());
}
