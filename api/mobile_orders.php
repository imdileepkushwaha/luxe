<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

$pdo = db();

/**
 * @return array{status:string,reason:string,seller_reason:string}|null
 */
function mobile_cancel_status_for_order(array $cancelByOrder, int $orderId): ?array
{
    if (!isset($cancelByOrder[$orderId])) {
        return null;
    }
    $row = $cancelByOrder[$orderId];
    $status = strtolower(trim((string) ($row['status'] ?? 'none')));
    if ($status === '' || $status === 'none') {
        return null;
    }

    return [
        'status' => $status,
        'reason' => (string) ($row['requestReason'] ?? ''),
        'seller_reason' => (string) ($row['sellerReason'] ?? ''),
    ];
}

try {
    $reviewedProductIds = [];
    $reviewedSt = $pdo->prepare(
        'SELECT product_id, review_status FROM product_reviews WHERE user_id = ? AND product_id IS NOT NULL'
    );
    $reviewedSt->execute([$userId]);
    while ($rv = $reviewedSt->fetch(PDO::FETCH_ASSOC)) {
        $rpid = (int) ($rv['product_id'] ?? 0);
        $status = strtolower(trim((string) ($rv['review_status'] ?? 'pending')));
        if ($rpid > 0 && $status !== 'rejected') {
            $reviewedProductIds[$rpid] = true;
        }
    }

    $returnMap = [];
    $returnSt = $pdo->prepare(
        'SELECT urr.id, urr.order_item_id, urr.product_name, urr.reason, urr.details, urr.status,
                urr.pickup_status, urr.refund_amount, urr.refund_mode, urr.order_id
         FROM user_return_requests urr
         WHERE urr.user_id = ?
         ORDER BY urr.id DESC'
    );
    $returnSt->execute([$userId]);
    while ($rr = $returnSt->fetch(PDO::FETCH_ASSOC)) {
        $orderItemId = (int) ($rr['order_item_id'] ?? 0);
        if ($orderItemId <= 0 || isset($returnMap[$orderItemId])) {
            continue;
        }
        $returnMap[$orderItemId] = [
            'id' => (int) ($rr['id'] ?? 0),
            'status' => (string) ($rr['status'] ?? 'pending'),
            'pickup_status' => (string) ($rr['pickup_status'] ?? 'not_scheduled'),
            'reason' => (string) ($rr['reason'] ?? ''),
            'details' => (string) ($rr['details'] ?? ''),
            'refund_amount' => (int) ($rr['refund_amount'] ?? 0),
            'refund_mode' => (string) ($rr['refund_mode'] ?? ''),
        ];
    }

    $cancelByOrder = [];
    $cancelSt = $pdo->prepare(
        'SELECT id, order_id, status, reason, details, seller_note, requested_at, reviewed_at
         FROM user_order_cancel_requests
         WHERE user_id = ?
         ORDER BY id DESC'
    );
    $cancelSt->execute([$userId]);
    while ($cr = $cancelSt->fetch(PDO::FETCH_ASSOC)) {
        $orderId = (int) ($cr['order_id'] ?? 0);
        if ($orderId <= 0) {
            continue;
        }
        if (!isset($cancelByOrder[$orderId])) {
            $cancelByOrder[$orderId] = [
                'status' => 'none',
                'requestReason' => '',
                'sellerReason' => '',
            ];
        }
        $status = strtolower(trim((string) ($cr['status'] ?? '')));
        if ($status === 'approved') {
            $cancelByOrder[$orderId]['status'] = 'approved';
        } elseif ($status === 'pending' && $cancelByOrder[$orderId]['status'] !== 'approved') {
            $cancelByOrder[$orderId]['status'] = 'pending';
        } elseif (
            $status === 'rejected'
            && !in_array($cancelByOrder[$orderId]['status'], ['approved', 'pending'], true)
        ) {
            $cancelByOrder[$orderId]['status'] = 'rejected';
        }
        if ($cancelByOrder[$orderId]['requestReason'] === '') {
            $cancelByOrder[$orderId]['requestReason'] = (string) ($cr['reason'] ?? '');
        }
        if ($status === 'rejected' && $cancelByOrder[$orderId]['sellerReason'] === '') {
            $cancelByOrder[$orderId]['sellerReason'] = trim((string) ($cr['seller_note'] ?? ''));
        }
    }

    $st = $pdo->prepare(
        'SELECT id, order_ref, status, total_amount, payment_method, shipping_address, created_at, delivered_at
         FROM orders WHERE user_id = ? ORDER BY created_at DESC'
    );
    $st->execute([$userId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $itemSt = $pdo->prepare(
        'SELECT oi.id, oi.product_id, oi.name, oi.qty, oi.price, oi.variant_text, oi.status,
                p.image_path AS product_image
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.id ASC'
    );
    $imgSt = $pdo->prepare(
        'SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1'
    );

    $orders = [];
    foreach ($rows as $row) {
        $orderId = (int) ($row['id'] ?? 0);
        $orderStatus = strtolower(trim((string) ($row['status'] ?? 'processing')));
        $itemSt->execute([$orderId]);
        $items = [];
        $itemCount = 0;
        $hasUnreturned = false;

        while ($it = $itemSt->fetch(PDO::FETCH_ASSOC)) {
            $productId = (int) ($it['product_id'] ?? 0);
            $qty = max(1, (int) ($it['qty'] ?? 1));
            $price = (int) ($it['price'] ?? 0);
            $itemCount += $qty;
            $orderItemId = (int) ($it['id'] ?? 0);

            $path = trim((string) ($it['product_image'] ?? ''));
            if ($path === '' && $productId > 0) {
                $imgSt->execute([$productId]);
                $path = trim((string) ($imgSt->fetchColumn() ?: ''));
            }

            $returnRequest = $returnMap[$orderItemId] ?? null;
            if ($returnRequest === null) {
                $hasUnreturned = true;
            }

            $items[] = [
                'id' => $orderItemId,
                'product_id' => $productId,
                'name' => (string) ($it['name'] ?? 'Item'),
                'qty' => $qty,
                'price' => $price,
                'variant' => (string) ($it['variant_text'] ?? ''),
                'status' => (string) ($it['status'] ?? $row['status'] ?? 'processing'),
                'image_url' => $path !== '' ? luxe_absolute_media_url($path) : '',
                'can_review' => $orderStatus === 'delivered' && $productId > 0 && !isset($reviewedProductIds[$productId]),
                'return_request' => $returnRequest,
            ];
        }

        $cancelRequest = mobile_cancel_status_for_order($cancelByOrder, $orderId);
        $canCancel = in_array($orderStatus, ['processing', 'pending', 'confirmed', 'shipped'], true)
            && $cancelRequest === null;

        $loyaltyPoints = loyalty_points_from_order_total((int) ($row['total_amount'] ?? 0));
        $loyaltyStatus = 'none';
        if ($loyaltyPoints > 0 && $orderStatus !== 'cancelled') {
            if ($orderStatus === 'delivered') {
                $delRaw = trim((string) ($row['delivered_at'] ?? $row['created_at'] ?? ''));
                $loyaltyStatus = 'credited';
                if ($delRaw !== '') {
                    try {
                        $creditAt = (new DateTimeImmutable($delRaw))->modify('+' . LUXE_LOYALTY_CREDIT_DELAY_DAYS . ' days');
                        if (new DateTimeImmutable('now') < $creditAt) {
                            $loyaltyStatus = 'pending';
                        }
                    } catch (Throwable) {
                        $loyaltyStatus = 'credited';
                    }
                }
            } else {
                $loyaltyStatus = 'upcoming';
            }
        }

        $orders[] = [
            'id' => $orderId,
            'order_ref' => (string) ($row['order_ref'] ?? ''),
            'status' => (string) ($row['status'] ?? 'processing'),
            'total_amount' => (int) ($row['total_amount'] ?? 0),
            'payment_method' => (string) ($row['payment_method'] ?? ''),
            'shipping_address' => (string) ($row['shipping_address'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'item_count' => $itemCount,
            'items' => $items,
            'can_cancel' => $canCancel,
            'can_return' => $orderStatus === 'delivered' && $hasUnreturned,
            'can_invoice' => $orderStatus === 'delivered',
            'cancel_request' => $cancelRequest,
            'loyalty_points' => $loyaltyPoints,
            'loyalty_status' => $loyaltyStatus,
        ];
    }

    echo json_encode(['ok' => true, 'orders' => $orders], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
