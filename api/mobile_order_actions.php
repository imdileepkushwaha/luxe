<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$action = (string) ($input['action'] ?? '');
$userId = (int) ($input['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

$pdo = db();
$userSt = $pdo->prepare('SELECT id, first_name, last_name FROM users WHERE id = ? LIMIT 1');
$userSt->execute([$userId]);
$user = $userSt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'User not found']);
    exit;
}

try {
    if ($action === 'cancel') {
        $orderRef = trim((string) ($input['order_ref'] ?? ''));
        $reason = trim((string) ($input['reason'] ?? ''));
        $details = trim((string) ($input['details'] ?? ''));
        if ($orderRef === '' || $reason === '') {
            echo json_encode(['ok' => false, 'error' => 'Please select a cancel reason.']);
            exit;
        }
        if (strlen($reason) > 120) {
            $reason = substr($reason, 0, 120);
        }
        if (strlen($details) > 1000) {
            $details = substr($details, 0, 1000);
        }

        $st = $pdo->prepare('SELECT id, status FROM orders WHERE order_ref = ? AND user_id = ? LIMIT 1');
        $st->execute([$orderRef, $userId]);
        $order = $st->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            echo json_encode(['ok' => false, 'error' => 'Order not found']);
            exit;
        }
        $status = strtolower(trim((string) $order['status']));
        if (!in_array($status, ['processing', 'pending', 'confirmed', 'shipped'], true)) {
            echo json_encode(['ok' => false, 'error' => 'This order cannot be cancelled at this stage.']);
            exit;
        }
        $orderId = (int) $order['id'];

        $stCheck = $pdo->prepare(
            'SELECT id FROM user_order_cancel_requests WHERE order_id = ? AND user_id = ? LIMIT 1'
        );
        $stCheck->execute([$orderId, $userId]);
        if ($stCheck->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Cancellation request already exists for this order.']);
            exit;
        }

        $stSellers = $pdo->prepare(
            'SELECT DISTINCT p.seller_id
             FROM order_items oi
             LEFT JOIN products p ON oi.product_id = p.id
             WHERE oi.order_id = ? AND p.seller_id IS NOT NULL'
        );
        $stSellers->execute([$orderId]);
        $sellers = array_filter(array_map('intval', $stSellers->fetchAll(PDO::FETCH_COLUMN)));
        if ($sellers === []) {
            echo json_encode(['ok' => false, 'error' => 'Cannot cancel: no active sellers found for this order.']);
            exit;
        }

        $pdo->beginTransaction();
        $ins = $pdo->prepare(
            'INSERT INTO user_order_cancel_requests
                (user_id, order_id, seller_id, order_ref, reason, details, status, requested_at)
             VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())'
        );
        foreach ($sellers as $sId) {
            $ins->execute([$userId, $orderId, $sId, $orderRef, $reason, $details]);
        }
        $pdo->commit();
        echo json_encode(['ok' => true, 'message' => 'Cancellation request submitted successfully.']);
        exit;
    }

    if ($action === 'return') {
        $orderRef = trim((string) ($input['order_ref'] ?? ''));
        $orderItemId = (int) ($input['order_item_id'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));
        $details = trim((string) ($input['details'] ?? ''));
        if ($orderRef === '' || $reason === '') {
            echo json_encode(['ok' => false, 'error' => 'Please fill all required return details.']);
            exit;
        }

        $ownOrderSt = $pdo->prepare(
            'SELECT id, status, created_at, payment_method FROM orders WHERE user_id = ? AND order_ref = ? LIMIT 1'
        );
        $ownOrderSt->execute([$userId, $orderRef]);
        $ownOrder = $ownOrderSt->fetch(PDO::FETCH_ASSOC);
        $orderId = (int) ($ownOrder['id'] ?? 0);
        $orderStatus = strtolower(trim((string) ($ownOrder['status'] ?? '')));
        if ($orderId <= 0 || $orderStatus !== 'delivered') {
            echo json_encode(['ok' => false, 'error' => 'Return request allowed only for delivered orders.']);
            exit;
        }

        if ($orderItemId > 0) {
            $itemSt = $pdo->prepare(
                'SELECT oi.id, oi.product_id, oi.name, oi.price, oi.qty, p.seller_id
                 FROM order_items oi
                 INNER JOIN products p ON p.id = oi.product_id
                 WHERE oi.id = ? AND oi.order_id = ?
                 LIMIT 1'
            );
            $itemSt->execute([$orderItemId, $orderId]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Please select an item to return.']);
            exit;
        }
        $itemRow = $itemSt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($itemRow)) {
            echo json_encode(['ok' => false, 'error' => 'Selected item does not belong to this order.']);
            exit;
        }

        $orderItemId = (int) ($itemRow['id'] ?? 0);
        $productId = (int) ($itemRow['product_id'] ?? 0);
        $sellerId = (int) ($itemRow['seller_id'] ?? 0);
        $refundAmount = max(0, (int) ($itemRow['price'] ?? 0)) * max(1, (int) ($itemRow['qty'] ?? 1));
        $refundMode = trim((string) ($ownOrder['payment_method'] ?? ''));
        if ($refundMode === '') {
            $refundMode = 'Original payment method';
        }
        $productName = trim((string) ($itemRow['name'] ?? 'Order item'));
        if ($orderItemId <= 0 || $sellerId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Return request could not be linked to seller item.']);
            exit;
        }

        $pendingSt = $pdo->prepare(
            "SELECT id FROM user_return_requests
             WHERE user_id = ? AND order_item_id = ? AND status IN ('pending','approved','pickup_scheduled','picked_up','refund_processing','refunded')
             LIMIT 1"
        );
        $pendingSt->execute([$userId, $orderItemId]);
        if ($pendingSt->fetchColumn()) {
            echo json_encode(['ok' => false, 'error' => 'A return request already exists for this item.']);
            exit;
        }

        $ins = $pdo->prepare(
            'INSERT INTO user_return_requests
                (user_id, order_ref, order_id, order_item_id, seller_id, product_id, product_name, reason, details, status, pickup_status, refund_amount, refund_mode)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $userId,
            $orderRef,
            $orderId,
            $orderItemId,
            $sellerId,
            $productId > 0 ? $productId : null,
            $productName,
            $reason,
            $details,
            'pending',
            'not_scheduled',
            $refundAmount,
            $refundMode,
        ]);
        orders_recompute_admin_commission_rupees($pdo, $orderId);
        echo json_encode(['ok' => true, 'message' => 'Return request submitted successfully.']);
        exit;
    }

    if ($action === 'review') {
        $orderRef = trim((string) ($input['order_ref'] ?? ''));
        $productId = (int) ($input['product_id'] ?? 0);
        $rating = (int) ($input['rating'] ?? 5);
        $reviewText = trim((string) ($input['review_text'] ?? ''));
        $customerName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        $result = orders_try_submit_product_review($pdo, $userId, $customerName, $orderRef, $productId, $rating, $reviewText);
        if (empty($result['ok'])) {
            echo json_encode(['ok' => false, 'error' => (string) ($result['message'] ?? 'Could not submit review.')]);
            exit;
        }
        echo json_encode(['ok' => true, 'message' => (string) ($result['message'] ?? 'Review submitted.')]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
