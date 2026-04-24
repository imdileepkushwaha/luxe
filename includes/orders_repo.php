<?php

declare(strict_types=1);

function orders_return_product_name_key(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    $collapsed = preg_replace('/\s+/', ' ', $name);
    return mb_strtolower($collapsed !== null ? $collapsed : $name);
}

/**
 * Map a legacy return row (missing order_item_id) to a line on the order by product title.
 *
 * @return int|null
 */
function orders_resolve_return_order_item_id(PDO $pdo, int $orderId, string $productName): ?int
{
    $want = orders_return_product_name_key($productName);
    if ($want === '') {
        return null;
    }
    $st = $pdo->prepare('SELECT id, name FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $st->execute([$orderId]);
    while ($row = $st->fetch()) {
        $rid = (int) ($row['id'] ?? 0);
        if ($rid <= 0) {
            continue;
        }
        if (orders_return_product_name_key((string) ($row['name'] ?? '')) === $want) {
            return $rid;
        }
    }
    return null;
}

/**
 * @param array<string,mixed> $rr
 * @return array<string,mixed>
 */
function orders_return_request_payload(PDO $pdo, array $rr): array
{
    $orderItemId = (int) ($rr['order_item_id'] ?? 0);
    $storedRefundAmount = max(0, (int) ($rr['refund_amount'] ?? 0));
    $fallbackRefundAmount = max(0, (int) ($rr['order_item_price'] ?? 0)) * max(1, (int) ($rr['order_item_qty'] ?? 1));
    if ($fallbackRefundAmount <= 0 && $orderItemId > 0) {
        $oiSt = $pdo->prepare('SELECT price, qty FROM order_items WHERE id = ? LIMIT 1');
        $oiSt->execute([$orderItemId]);
        $oiRow = $oiSt->fetch();
        if (is_array($oiRow)) {
            $fallbackRefundAmount = max(0, (int) ($oiRow['price'] ?? 0)) * max(1, (int) ($oiRow['qty'] ?? 1));
        }
    }
    $effectiveRefundAmount = $storedRefundAmount > 0 ? $storedRefundAmount : $fallbackRefundAmount;
    $storedRefundMode = trim((string) ($rr['refund_mode'] ?? ''));
    $fallbackRefundMode = trim((string) ($rr['order_payment_method'] ?? ''));
    $effectiveRefundMode = $storedRefundMode !== '' ? $storedRefundMode : ($fallbackRefundMode !== '' ? $fallbackRefundMode : 'Original payment method');

    return [
        'id' => (int) ($rr['id'] ?? 0),
        'status' => (string) ($rr['status'] ?? 'pending'),
        'pickupStatus' => (string) ($rr['pickup_status'] ?? 'not_scheduled'),
        'reason' => (string) ($rr['reason'] ?? ''),
        'details' => (string) ($rr['details'] ?? ''),
        'pickupNote' => (string) ($rr['pickup_note'] ?? ''),
        'refundAmount' => $effectiveRefundAmount,
        'refundMode' => $effectiveRefundMode,
        'requestedAt' => (string) ($rr['requested_at'] ?? ''),
        'reviewedAt' => (string) ($rr['reviewed_at'] ?? ''),
        'pickupScheduledAt' => (string) ($rr['pickup_scheduled_at'] ?? ''),
        'pickupCompletedAt' => (string) ($rr['pickup_completed_at'] ?? ''),
        'resolvedAt' => (string) ($rr['resolved_at'] ?? ''),
    ];
}

/**
 * Shape expected by luxe.js orders page.
 *
 * @return list<array<string,mixed>>
 */
function orders_fetch_for_user(PDO $pdo, int $userId): array
{
    $reviewedProductIds = [];
    $reviewedSt = $pdo->prepare(
        'SELECT product_id
         FROM product_reviews
         WHERE user_id = ?
           AND product_id IS NOT NULL'
    );
    $reviewedSt->execute([$userId]);
    while ($rv = $reviewedSt->fetch()) {
        $rpid = (int) ($rv['product_id'] ?? 0);
        if ($rpid > 0) {
            $reviewedProductIds[$rpid] = true;
        }
    }

    $returnMap = [];
    $enquiryMap = [];
    $cancelByOrder = [];
    $returnSt = $pdo->prepare(
        'SELECT urr.id, urr.order_ref, urr.order_id, urr.order_item_id, urr.product_id, urr.product_name, urr.reason, urr.details, urr.status,
                urr.pickup_status, urr.pickup_note, urr.refund_amount, urr.refund_mode,
                oi.price AS order_item_price, oi.qty AS order_item_qty,
                o.payment_method AS order_payment_method,
                urr.requested_at, urr.reviewed_at, urr.pickup_scheduled_at, urr.pickup_completed_at, urr.resolved_at
         FROM user_return_requests urr
         LEFT JOIN order_items oi ON oi.id = urr.order_item_id
         LEFT JOIN orders o ON o.id = urr.order_id
         WHERE urr.user_id = ?
         ORDER BY urr.id DESC'
    );
    $returnSt->execute([$userId]);
    while ($rr = $returnSt->fetch()) {
        $orderItemId = (int) ($rr['order_item_id'] ?? 0);
        if ($orderItemId > 0) {
            if (isset($returnMap[$orderItemId])) {
                continue;
            }
            $returnMap[$orderItemId] = orders_return_request_payload($pdo, $rr);
            continue;
        }
        $ordId = (int) ($rr['order_id'] ?? 0);
        if ($ordId <= 0) {
            continue;
        }
        $resolved = orders_resolve_return_order_item_id($pdo, $ordId, (string) ($rr['product_name'] ?? ''));
        if ($resolved === null || isset($returnMap[$resolved])) {
            continue;
        }
        $oiSt = $pdo->prepare('SELECT price, qty FROM order_items WHERE id = ? LIMIT 1');
        $oiSt->execute([$resolved]);
        $oiRow = $oiSt->fetch();
        if (is_array($oiRow)) {
            $rr['order_item_id'] = $resolved;
            $rr['order_item_price'] = $oiRow['price'];
            $rr['order_item_qty'] = $oiRow['qty'];
        }
        $returnMap[$resolved] = orders_return_request_payload($pdo, $rr);
    }

    $enquirySt = $pdo->prepare(
        'SELECT id, order_item_id, message, seller_reply, created_at, replied_at
         FROM user_order_enquiries
         WHERE user_id = ?
         ORDER BY id DESC'
    );
    $enquirySt->execute([$userId]);
    while ($eq = $enquirySt->fetch()) {
        $orderItemId = (int) ($eq['order_item_id'] ?? 0);
        if ($orderItemId <= 0 || isset($enquiryMap[$orderItemId])) {
            continue;
        }
        $enquiryMap[$orderItemId] = [
            'id' => (int) ($eq['id'] ?? 0),
            'message' => (string) ($eq['message'] ?? ''),
            'sellerReply' => (string) ($eq['seller_reply'] ?? ''),
            'createdAt' => (string) ($eq['created_at'] ?? ''),
            'repliedAt' => (string) ($eq['replied_at'] ?? ''),
        ];
    }

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
                'requestDetails' => '',
                'sellerReason' => '',
                'requestedAt' => '',
                'reviewedAt' => '',
            ];
        }
        $status = strtolower(trim((string) ($cr['status'] ?? '')));
        if ($status === 'approved') {
            $cancelByOrder[$orderId]['status'] = 'approved';
        } elseif (
            $status === 'pending'
            && $cancelByOrder[$orderId]['status'] !== 'approved'
        ) {
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
        if ($cancelByOrder[$orderId]['requestDetails'] === '') {
            $cancelByOrder[$orderId]['requestDetails'] = (string) ($cr['details'] ?? '');
        }
        if ($cancelByOrder[$orderId]['requestedAt'] === '') {
            $cancelByOrder[$orderId]['requestedAt'] = (string) ($cr['requested_at'] ?? '');
        }
        if ($cancelByOrder[$orderId]['reviewedAt'] === '') {
            $cancelByOrder[$orderId]['reviewedAt'] = (string) ($cr['reviewed_at'] ?? '');
        }
        if (
            $status === 'rejected'
            && $cancelByOrder[$orderId]['sellerReason'] === ''
        ) {
            $cancelByOrder[$orderId]['sellerReason'] = trim((string) ($cr['seller_note'] ?? ''));
        }
    }

    $st = $pdo->prepare(
        'SELECT id, order_ref, status, total_amount, payment_method, shipping_address, created_at
         FROM orders WHERE user_id = ? ORDER BY created_at DESC'
    );
    $st->execute([$userId]);
    $orders = $st->fetchAll();
    $out = [];
    foreach ($orders as $o) {
        $it = $pdo->prepare(
            'SELECT pi.id AS order_item_id, pi.product_id, pi.name, pi.emoji, pi.variant_text AS variant, pi.price, pi.qty, pi.status AS item_status
             FROM order_items pi WHERE pi.order_id = ?'
        );
        $it->execute([(int) $o['id']]);
        $items = [];
        $computedTotal = 0;
        while ($row = $it->fetch()) {
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $price = (int) ($row['price'] ?? 0);
            $lineTotal = $price * $qty;
            $computedTotal += $lineTotal;
            $orderItemId = (int) ($row['order_item_id'] ?? 0);
            $productId = (int) ($row['product_id'] ?? 0);
            $itemStatus = strtolower(trim((string) ($row['item_status'] ?? (string) ($o['status'] ?? 'processing'))));
            if ($itemStatus === '') {
                $itemStatus = strtolower(trim((string) ($o['status'] ?? 'processing')));
            }
            $items[] = [
                'orderItemId' => $orderItemId,
                'emoji' => $row['emoji'] ?? '📦',
                'name' => $row['name'],
                'productId' => $productId,
                'variant' => $row['variant'] ?? '',
                'price' => $price,
                'qty' => $qty,
                'status' => $itemStatus,
                'tracking' => order_tracking_steps($itemStatus),
                'lineTotal' => $lineTotal,
                'returnRequest' => $returnMap[$orderItemId] ?? null,
                'enquiry' => $enquiryMap[$orderItemId] ?? null,
                'hasReview' => $productId > 0 && isset($reviewedProductIds[$productId]),
            ];
        }
        $orderTotal = $computedTotal > 0 ? $computedTotal : (int) $o['total_amount'];
        $out[] = [
            'id' => $o['order_ref'],
            'date' => date('M j, Y', strtotime($o['created_at'])),
            'createdAt' => (string) $o['created_at'],
            'status' => $o['status'],
            'items' => $items,
            'total' => $orderTotal,
            'address' => $o['shipping_address'] ?? '',
            'payment' => $o['payment_method'] ?? '',
            'tracking' => order_tracking_steps($o['status']),
            'cancelRequest' => $cancelByOrder[(int) $o['id']] ?? null,
        ];
    }
    return $out;
}

/**
 * Aggregates for profile hero (orders count, spend, savings vs MRP from catalog).
 *
 * @return array{order_count:int,lifetime_spend_rupees:int,total_saved_rupees:int}
 */
function profile_order_stats_for_user(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return ['order_count' => 0, 'lifetime_spend_rupees' => 0, 'total_saved_rupees' => 0];
    }
    $st = $pdo->prepare(
        'SELECT COUNT(*) AS c, COALESCE(SUM(total_amount), 0) AS spend FROM orders WHERE user_id = ?'
    );
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $orderCount = (int) ($row['c'] ?? 0);
    $spend = (int) ($row['spend'] ?? 0);

    $savedSt = $pdo->prepare(
        'SELECT COALESCE(SUM(
            GREATEST(0, COALESCE(p.original_price, oi.price) - oi.price) * oi.qty
        ), 0) AS saved
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id AND o.user_id = ?
         LEFT JOIN products p ON p.id = oi.product_id'
    );
    $savedSt->execute([$userId]);
    $savedRow = $savedSt->fetch(PDO::FETCH_ASSOC) ?: [];
    $saved = (int) ($savedRow['saved'] ?? 0);

    return [
        'order_count' => $orderCount,
        'lifetime_spend_rupees' => $spend,
        'total_saved_rupees' => $saved,
    ];
}

/**
 * One row per product the user received on a delivered order, with optional review (same user + product).
 * Pending reviews sort first, then by most recent delivery.
 *
 * @return list<array<string, mixed>>
 */
function profile_delivered_review_rows_for_user(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    try {
        $st = $pdo->prepare(
            'SELECT d.product_id,
                    oi.name AS item_name,
                    oi.emoji AS item_emoji,
                    oi.variant_text,
                    o.order_ref,
                    COALESCE(o.delivered_at, o.created_at) AS delivered_at,
                    p.name AS product_name,
                    p.slug,
                    p.emoji AS product_emoji,
                    p.image_path,
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1) AS gallery_first,
                    r.id AS review_id,
                    r.rating,
                    r.review_text,
                    r.review_status,
                    r.seller_response,
                    r.seller_responded_at,
                    r.created_at AS review_created_at
             FROM (
                SELECT oi.product_id, MAX(oi.id) AS max_oi_id
                FROM order_items oi
                INNER JOIN orders o ON o.id = oi.order_id
                WHERE o.user_id = ?
                  AND LOWER(TRIM(o.status)) = ?
                  AND oi.product_id IS NOT NULL
                GROUP BY oi.product_id
             ) d
             INNER JOIN order_items oi ON oi.id = d.max_oi_id
             INNER JOIN orders o ON o.id = oi.order_id
                AND o.user_id = ?
                AND LOWER(TRIM(o.status)) = ?
             LEFT JOIN products p ON p.id = d.product_id
             LEFT JOIN product_reviews r ON r.product_id = d.product_id AND r.user_id = ?
             ORDER BY (r.id IS NULL) DESC, delivered_at DESC, d.product_id DESC
             LIMIT 200'
        );
        $delivered = 'delivered';
        $st->execute([$userId, $delivered, $userId, $delivered, $userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }

    return is_array($rows) ? $rows : [];
}

/**
 * Whether the user has this product on at least one delivered order.
 */
function profile_user_has_delivered_product(PDO $pdo, int $userId, int $productId): bool
{
    if ($userId <= 0 || $productId <= 0) {
        return false;
    }
    try {
        $st = $pdo->prepare(
            'SELECT 1
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE o.user_id = ?
               AND oi.product_id = ?
               AND LOWER(TRIM(o.status)) = ?
             LIMIT 1'
        );
        $st->execute([$userId, $productId, 'delivered']);

        return (bool) $st->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

/**
 * Submit or update a product review from a delivered order (same rules as orders page).
 *
 * @return array{ok: bool, message?: string}
 */
function orders_try_submit_product_review(PDO $pdo, int $uid, string $customerName, string $orderRef, int $productId, int $rating, string $reviewText): array
{
    $orderRef = trim($orderRef);
    $reviewText = trim($reviewText);
    if ($orderRef === '' || $productId <= 0 || strlen($reviewText) < 10) {
        return ['ok' => false, 'message' => 'Please provide valid review details (min 10 characters).'];
    }
    $rating = max(1, min(5, $rating));
    if (strlen($reviewText) > 1000) {
        $reviewText = substr($reviewText, 0, 1000);
    }
    $customerName = trim($customerName);
    if ($customerName === '') {
        $customerName = 'Customer';
    }

    $ownOrderSt = $pdo->prepare('SELECT id, status FROM orders WHERE user_id = ? AND order_ref = ? LIMIT 1');
    $ownOrderSt->execute([$uid, $orderRef]);
    $ownOrder = $ownOrderSt->fetch();
    $orderId = (int) ($ownOrder['id'] ?? 0);
    $orderStatus = strtolower(trim((string) ($ownOrder['status'] ?? '')));

    if ($orderId <= 0 || $orderStatus !== 'delivered') {
        return ['ok' => false, 'message' => 'Review allowed only for delivered orders.'];
    }

    $itemSt = $pdo->prepare('SELECT id FROM order_items WHERE order_id = ? AND product_id = ? LIMIT 1');
    $itemSt->execute([$orderId, $productId]);
    if (!(bool) $itemSt->fetchColumn()) {
        return ['ok' => false, 'message' => 'Selected product does not belong to this order.'];
    }

    $existingSt = $pdo->prepare('SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
    $existingSt->execute([$productId, $uid]);
    $existingReviewId = (int) ($existingSt->fetchColumn() ?: 0);

    if ($existingReviewId > 0) {
        $upd = $pdo->prepare(
            'UPDATE product_reviews
             SET customer_name = ?, rating = ?, review_text = ?,
                 review_status = ?, seller_response = \'\', seller_reviewed_at = NULL, seller_responded_at = NULL
             WHERE id = ?
             LIMIT 1'
        );
        $upd->execute([$customerName, $rating, $reviewText, 'pending', $existingReviewId]);
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO product_reviews
                (product_id, user_id, customer_name, rating, review_text, review_status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$productId, $uid, $customerName, $rating, $reviewText, 'pending']);
    }

    return ['ok' => true, 'message' => 'Review submitted successfully. Seller approval ke baad show hoga.'];
}

/** @return list<string> */
function order_tracking_steps(string $status): array
{
    $all = ['ordered', 'confirmed', 'shipped', 'out', 'delivered'];
    $map = [
        'processing' => ['ordered'],
        'confirmed' => ['ordered', 'confirmed'],
        'shipped' => ['ordered', 'confirmed', 'shipped'],
        'out' => ['ordered', 'confirmed', 'shipped', 'out'],
        'delivered' => $all,
        'cancelled' => ['ordered', 'confirmed'],
    ];
    return $map[$status] ?? ['ordered', 'confirmed'];
}

require_once __DIR__ . '/cart_session.php';

/**
 * Parse order_items.variant_text back into size/color (matches checkout "size · color" format).
 *
 * @return array{0: string, 1: string} [sizeRaw, colorRaw] before normalization
 */
function orders_variant_parts_from_line(string $variantText): array
{
    $variantText = trim($variantText);
    if ($variantText === '') {
        return ['', ''];
    }
    if (preg_match('/^·\s*(.+)$/u', $variantText, $m)) {
        return ['', trim($m[1])];
    }
    if (preg_match('/^(.+?)\s*·\s*(.+)$/u', $variantText, $m)) {
        return [trim($m[1]), trim($m[2])];
    }

    return [$variantText, ''];
}

/**
 * Add stock back for one order line (full line qty) after return is completed.
 */
function orders_restore_stock_for_return_line(PDO $pdo, int $orderItemId): void
{
    if ($orderItemId <= 0) {
        return;
    }
    $st = $pdo->prepare('SELECT product_id, qty, variant_text FROM order_items WHERE id = ? LIMIT 1');
    $st->execute([$orderItemId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $pid = (int) ($row['product_id'] ?? 0);
    $qty = max(0, (int) ($row['qty'] ?? 0));
    if ($pid <= 0 || $qty <= 0) {
        return;
    }
    [$sizeRaw, $colorRaw] = orders_variant_parts_from_line((string) ($row['variant_text'] ?? ''));
    $sizeNorm = cart_normalize_variant_size($sizeRaw);
    $colorNorm = cart_normalize_variant_color($colorRaw);
    $variantSizeKey = mb_strtolower($sizeNorm);
    $variantColorKey = mb_strtolower($colorNorm);

    $variantCountSt = $pdo->prepare('SELECT COUNT(*) FROM product_variant_inventory WHERE product_id = ? AND active = 1');
    $variantCountSt->execute([$pid]);
    $hasVariantRows = (int) $variantCountSt->fetchColumn() > 0;
    if ($hasVariantRows) {
        $upd = $pdo->prepare(
            'UPDATE product_variant_inventory
             SET stock_qty = stock_qty + ?
             WHERE product_id = ?
               AND active = 1
               AND LOWER(TRIM(size_label)) = ?
               AND LOWER(TRIM(color_label)) = ?
             LIMIT 1'
        );
        $upd->execute([$qty, $pid, $variantSizeKey, $variantColorKey]);

        return;
    }

    $updP = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ? LIMIT 1');
    $updP->execute([$qty, $pid]);
}

/**
 * Resolve order line from return row and restore catalog stock (variant or product level).
 */
function orders_restore_stock_after_return_completed(PDO $pdo, int $returnRequestId, int $sellerId, int $orderId): void
{
    $st = $pdo->prepare(
        'SELECT order_item_id, product_name FROM user_return_requests
         WHERE id = ? AND seller_id = ? AND order_id = ?
         LIMIT 1'
    );
    $st->execute([$returnRequestId, $sellerId, $orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $oi = (int) ($row['order_item_id'] ?? 0);
    if ($oi <= 0) {
        $resolved = orders_resolve_return_order_item_id($pdo, $orderId, (string) ($row['product_name'] ?? ''));
        $oi = $resolved ?? 0;
    }
    if ($oi <= 0) {
        return;
    }
    orders_restore_stock_for_return_line($pdo, $oi);
    orders_recompute_admin_commission_rupees($pdo, $orderId);
}

/**
 * Recompute admin commission for every order that has at least one non-rejected return row.
 * Call after backfill rule changes or to repair rows where commission was stored on full merchandise.
 *
 * @return int Number of orders processed
 */
function orders_recompute_admin_commission_all_orders_with_returns(PDO $pdo): int
{
    try {
        $st = $pdo->query(
            "SELECT DISTINCT o.id
             FROM orders o
             INNER JOIN user_return_requests ur ON (
                 ur.order_id = o.id
                 OR (COALESCE(ur.order_id, 0) = 0 AND ur.order_ref <> '' AND ur.order_ref = o.order_ref)
             )
             WHERE LOWER(TRIM(COALESCE(ur.status, ''))) <> 'rejected'"
        );
        if (!$st) {
            return 0;
        }
        $n = 0;
        while (($id = $st->fetchColumn()) !== false) {
            $oid = (int) $id;
            if ($oid > 0) {
                orders_recompute_admin_commission_rupees($pdo, $oid);
                $n++;
            }
        }

        return $n;
    } catch (Throwable) {
        return 0;
    }
}

/**
 * Sync orders.admin_commission_rupees: zero when the order is cancelled; otherwise current site %
 * on merchandise subtotal minus any line with a non-rejected return (pending through refunded).
 */
function orders_recompute_admin_commission_rupees(PDO $pdo, int $orderId): void
{
    if ($orderId <= 0) {
        return;
    }
    require_once __DIR__ . '/site_settings.php';

    $st = $pdo->prepare('SELECT LOWER(TRIM(COALESCE(status, \'\'))) FROM orders WHERE id = ? LIMIT 1');
    $st->execute([$orderId]);
    $orderStatus = (string) $st->fetchColumn();
    if ($orderStatus === 'cancelled') {
        $z = $pdo->prepare('UPDATE orders SET admin_commission_rupees = 0 WHERE id = ? LIMIT 1');
        $z->execute([$orderId]);

        return;
    }

    $orefSt = $pdo->prepare('SELECT TRIM(COALESCE(order_ref, \'\')) FROM orders WHERE id = ? LIMIT 1');
    $orefSt->execute([$orderId]);
    $orderRef = trim((string) $orefSt->fetchColumn());

    $mSt = $pdo->prepare('SELECT COALESCE(SUM(price * qty), 0) FROM order_items WHERE order_id = ?');
    $mSt->execute([$orderId]);
    $merchTotal = (int) $mSt->fetchColumn();
    if ($merchTotal <= 0) {
        $z = $pdo->prepare('UPDATE orders SET admin_commission_rupees = 0 WHERE id = ? LIMIT 1');
        $z->execute([$orderId]);

        return;
    }

    $returnExcludedMerch = 0;
    try {
        $urSt = $pdo->prepare(
            "SELECT ur.order_item_id, ur.refund_amount
             FROM user_return_requests ur
             WHERE (ur.order_id = ? OR (COALESCE(ur.order_id, 0) = 0 AND ? <> '' AND ur.order_ref = ?))
               AND LOWER(TRIM(COALESCE(ur.status, ''))) <> 'rejected'"
        );
        $urSt->execute([$orderId, $orderRef, $orderRef]);
        $perLine = [];
        $orphanRefund = 0;
        while ($ur = $urSt->fetch(PDO::FETCH_ASSOC)) {
            $oiid = (int) ($ur['order_item_id'] ?? 0);
            if ($oiid > 0) {
                $lvSt = $pdo->prepare('SELECT COALESCE(price * qty, 0) FROM order_items WHERE id = ? AND order_id = ? LIMIT 1');
                $lvSt->execute([$oiid, $orderId]);
                $lv = (int) $lvSt->fetchColumn();
                $perLine[$oiid] = max($perLine[$oiid] ?? 0, $lv);
            } else {
                $orphanRefund += max(0, (int) ($ur['refund_amount'] ?? 0));
            }
        }
        $returnExcludedMerch = array_sum($perLine) + $orphanRefund;
    } catch (Throwable) {
        $returnExcludedMerch = 0;
    }

    if ($returnExcludedMerch > $merchTotal) {
        $returnExcludedMerch = $merchTotal;
    }
    $netMerch = max(0, $merchTotal - $returnExcludedMerch);
    $pct = site_admin_seller_commission_percent($pdo);
    $comm = order_admin_commission_rupees_from_subtotal($netMerch, $pct);
    $upd = $pdo->prepare('UPDATE orders SET admin_commission_rupees = ? WHERE id = ? LIMIT 1');
    $upd->execute([$comm, $orderId]);
}
