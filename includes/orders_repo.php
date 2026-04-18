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
    $returnMap = [];
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

    $st = $pdo->prepare(
        'SELECT id, order_ref, status, total_amount, payment_method, shipping_address, created_at
         FROM orders WHERE user_id = ? ORDER BY created_at DESC'
    );
    $st->execute([$userId]);
    $orders = $st->fetchAll();
    $out = [];
    foreach ($orders as $o) {
        $it = $pdo->prepare(
            'SELECT pi.id AS order_item_id, pi.product_id, pi.name, pi.emoji, pi.variant_text AS variant, pi.price, pi.qty
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
            $items[] = [
                'orderItemId' => $orderItemId,
                'emoji' => $row['emoji'] ?? '📦',
                'name' => $row['name'],
                'productId' => (int) ($row['product_id'] ?? 0),
                'variant' => $row['variant'] ?? '',
                'price' => $price,
                'qty' => $qty,
                'lineTotal' => $lineTotal,
                'returnRequest' => $returnMap[$orderItemId] ?? null,
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
        ];
    }
    return $out;
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
}
