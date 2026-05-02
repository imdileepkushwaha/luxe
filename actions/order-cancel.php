<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON.']);
    exit;
}

$orderRef = trim((string) ($data['order_ref'] ?? ''));
$reason = trim((string) ($data['reason'] ?? ''));
$details = trim((string) ($data['details'] ?? ''));

if ($orderRef === '') {
    echo json_encode(['ok' => false, 'message' => 'Missing order reference.']);
    exit;
}

if ($reason === '') {
    echo json_encode(['ok' => false, 'message' => 'Reason is required.']);
    exit;
}

$pdo = db();

// Verify order belongs to user and is eligible for cancellation (not already delivered or cancelled)
$st = $pdo->prepare('SELECT id, status FROM orders WHERE order_ref = ? AND user_id = ? LIMIT 1');
$st->execute([$orderRef, $userId]);
$order = $st->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['ok' => false, 'message' => 'Order not found.']);
    exit;
}

$status = strtolower($order['status']);
if ($status === 'delivered' || $status === 'completed' || $status === 'cancelled' || $status === 'out_for_delivery') {
    echo json_encode(['ok' => false, 'message' => 'This order cannot be cancelled at this stage.']);
    exit;
}

$orderId = (int) $order['id'];

// Check if already requested
$stCheck = $pdo->prepare('SELECT id FROM user_order_cancel_requests WHERE order_id = ? AND user_id = ? LIMIT 1');
$stCheck->execute([$orderId, $userId]);
if ($stCheck->fetch()) {
    echo json_encode(['ok' => false, 'message' => 'Cancellation request already exists for this order.']);
    exit;
}

// Find all unique sellers involved in this order
$stSellers = $pdo->prepare('
    SELECT DISTINCT p.seller_id 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ? AND p.seller_id IS NOT NULL
');
$stSellers->execute([$orderId]);
$sellers = $stSellers->fetchAll(PDO::FETCH_COLUMN);

if (empty($sellers)) {
    // If no sellers found, the items might have been deleted. We can't insert a cancellation request without a valid seller_id due to FK constraints.
    echo json_encode(['ok' => false, 'message' => 'Cannot cancel: no active sellers found for this order.']);
    exit;
}

$pdo->beginTransaction();
try {
    $ins = $pdo->prepare('
        INSERT INTO user_order_cancel_requests (user_id, order_id, seller_id, order_ref, reason, details, status, requested_at)
        VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())
    ');
    foreach ($sellers as $sId) {
        $ins->execute([$userId, $orderId, (int) $sId, $orderRef, $reason, $details]);
    }
    $pdo->commit();
    echo json_encode(['ok' => true, 'message' => 'Cancellation request submitted successfully.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Order Cancel Error: " . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'A database error occurred.']);
}
