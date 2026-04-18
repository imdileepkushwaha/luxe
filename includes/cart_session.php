<?php

declare(strict_types=1);

require_once __DIR__ . '/site_settings.php';

function cart_normalize_variant_size(string $s): string
{
    $s = trim($s);
    if ($s === '' || strcasecmp($s, 'Standard') === 0) {
        return '';
    }

    return $s;
}

function cart_normalize_variant_color(string $c): string
{
    $c = trim($c);
    if ($c === '' || strcasecmp($c, 'Default') === 0) {
        return '';
    }

    return $c;
}

function cart_line_max_qty(PDO $pdo, int $productId, string $size, string $color): int
{
    $cntSt = $pdo->prepare('SELECT COUNT(*) FROM product_variant_inventory WHERE product_id = ? AND active = 1');
    $cntSt->execute([$productId]);
    if ((int) $cntSt->fetchColumn() > 0) {
        $wantSz = mb_strtolower(cart_normalize_variant_size($size));
        $wantCl = mb_strtolower(cart_normalize_variant_color($color));

        $st = $pdo->prepare(
            'SELECT size_label, color_label, stock_qty FROM product_variant_inventory
             WHERE product_id = ? AND active = 1'
        );
        $st->execute([$productId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rsz = mb_strtolower(cart_normalize_variant_size((string) ($row['size_label'] ?? '')));
            $rcl = mb_strtolower(cart_normalize_variant_color((string) ($row['color_label'] ?? '')));
            if ($rsz === $wantSz && $rcl === $wantCl) {
                return max(0, (int) ($row['stock_qty'] ?? 0));
            }
        }

        return 0;
    }

    $pSt = $pdo->prepare('SELECT stock_qty FROM products WHERE id = ? LIMIT 1');
    $pSt->execute([$productId]);

    return max(0, (int) ($pSt->fetchColumn() ?: 0));
}

/**
 * @return list<array<string,mixed>>
 */
function cart_filter_available_items(PDO $pdo, array $items): array
{
    if ($items === []) {
        return [];
    }

    $productSt = $pdo->prepare(
        'SELECT p.id, p.name, p.emoji, p.price, p.stock_qty, p.brand
         FROM products p
         LEFT JOIN seller_users s ON s.id = p.seller_id
         WHERE p.id = ?
           AND p.active = 1
           AND p.approval_status = \'approved\'
           AND p.seller_id IS NOT NULL
           AND s.is_active = 1
           AND NOT EXISTS (
                SELECT 1
                FROM seller_account_deletion_requests dr
                WHERE dr.status = \'approved\'
                  AND (dr.seller_id = s.id OR dr.email = s.email)
           )
         LIMIT 1'
    );

    $clean = [];
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $pid = (int) ($it['id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }

        $productSt->execute([$pid]);
        $p = $productSt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            continue;
        }

        $maxQty = cart_line_max_qty($pdo, $pid, (string) ($it['size'] ?? ''), (string) ($it['color'] ?? ''));
        if ($maxQty <= 0) {
            continue;
        }

        $qty = max(1, (int) ($it['qty'] ?? 1));
        $qty = min($qty, $maxQty);
        $brandLine = trim((string) ($it['brand'] ?? ''));
        if ($brandLine === '') {
            $brandLine = trim((string) ($p['brand'] ?? ''));
        }
        $clean[] = [
            'id' => (int) ($p['id'] ?? 0),
            'name' => (string) ($p['name'] ?? 'Item'),
            'brand' => $brandLine,
            'emoji' => (string) ($p['emoji'] ?? '📦'),
            'price' => max(0, (int) ($p['price'] ?? 0)),
            'orig' => max(0, (int) ($it['orig'] ?? ($p['price'] ?? 0))),
            'qty' => $qty,
            'max_qty' => $maxQty,
            'size' => (string) ($it['size'] ?? ''),
            'color' => (string) ($it['color'] ?? ''),
            'checked' => isset($it['checked']) ? (bool) $it['checked'] : true,
        ];
    }

    return $clean;
}

/**
 * Sum seller base delivery (not speed surcharges).
 *
 * @param list<array<string,mixed>> $lines
 */
function cart_compute_delivery_total(PDO $pdo, array $lines): int
{
    if ($lines === []) {
        return 0;
    }

    $productIds = [];
    foreach ($lines as $li) {
        if (!is_array($li)) {
            continue;
        }
        $pid = (int) ($li['id'] ?? 0);
        if ($pid > 0) {
            $productIds[$pid] = true;
        }
    }
    $ids = array_keys($productIds);
    if ($ids === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, seller_id FROM products WHERE id IN ($placeholders)");
    $st->execute($ids);
    $sellerOf = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $sellerOf[(int) $row['id']] = (int) ($row['seller_id'] ?? 0);
    }

    $bySeller = [];
    foreach ($lines as $li) {
        if (!is_array($li)) {
            continue;
        }
        $pid = (int) ($li['id'] ?? 0);
        $sid = $sellerOf[$pid] ?? 0;
        if ($sid <= 0) {
            continue;
        }
        $unit = max(0, (int) ($li['price'] ?? 0));
        $qty = max(1, (int) ($li['qty'] ?? 1));
        $bySeller[$sid] = ($bySeller[$sid] ?? 0) + ($unit * $qty);
    }

    if ($bySeller === []) {
        return 0;
    }

    $sellerIds = array_keys($bySeller);
    $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
    $setSt = $pdo->prepare(
        "SELECT seller_id, default_shipping_fee, free_shipping_min_order
         FROM seller_shipping_settings
         WHERE seller_id IN ($placeholders)"
    );
    $setSt->execute($sellerIds);
    $settings = [];
    while ($row = $setSt->fetch(PDO::FETCH_ASSOC)) {
        $settings[(int) $row['seller_id']] = [
            'fee' => max(0, (int) ($row['default_shipping_fee'] ?? 0)),
            'min' => max(0, (int) ($row['free_shipping_min_order'] ?? 0)),
        ];
    }

    $platformFallbackFee = site_cart_below_min_shipping_fee_rupees($pdo);
    $platformFallbackMin = site_cart_free_shipping_min_rupees($pdo);

    $total = 0;
    foreach ($bySeller as $sid => $subtotal) {
        if (!isset($settings[$sid])) {
            $fee = $platformFallbackFee;
            $min = $platformFallbackMin;
        } else {
            $fee = $settings[$sid]['fee'];
            $min = $settings[$sid]['min'];
        }
        if ($min > 0 && $subtotal >= $min) {
            continue;
        }
        $total += $fee;
    }

    return $total;
}

/**
 * Sum Express / Same Day surcharges per distinct seller in cart (active options only).
 *
 * @param list<array<string,mixed>> $lines Normalized lines (id, qty, …)
 * @return array{express: int, same_day: int}
 */
function cart_speed_fee_totals_for_lines(PDO $pdo, array $lines): array
{
    $out = ['express' => 0, 'same_day' => 0];
    if ($lines === []) {
        return $out;
    }

    $productIds = [];
    foreach ($lines as $li) {
        if (!is_array($li)) {
            continue;
        }
        $pid = (int) ($li['id'] ?? 0);
        if ($pid > 0) {
            $productIds[$pid] = true;
        }
    }
    $ids = array_keys($productIds);
    if ($ids === []) {
        return $out;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, seller_id FROM products WHERE id IN ($placeholders)");
    $st->execute($ids);
    $sellerOf = [];
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $sellerOf[(int) $row['id']] = (int) ($row['seller_id'] ?? 0);
    }

    $sellersSeen = [];
    foreach ($lines as $li) {
        if (!is_array($li)) {
            continue;
        }
        $pid = (int) ($li['id'] ?? 0);
        $sid = $sellerOf[$pid] ?? 0;
        if ($sid > 0) {
            $sellersSeen[$sid] = true;
        }
    }

    $sellerIds = array_keys($sellersSeen);
    if ($sellerIds === []) {
        return $out;
    }

    $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
    $optSt = $pdo->prepare(
        "SELECT seller_id, option_code, fee_amount, is_active
         FROM seller_delivery_options
         WHERE seller_id IN ($placeholders)
           AND option_code IN ('express','same_day')"
    );
    $optSt->execute($sellerIds);
    while ($row = $optSt->fetch(PDO::FETCH_ASSOC)) {
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            continue;
        }
        $fee = max(0, (int) ($row['fee_amount'] ?? 0));
        $code = strtolower((string) ($row['option_code'] ?? ''));
        if ($code === 'express') {
            $out['express'] += $fee;
        } elseif ($code === 'same_day') {
            $out['same_day'] += $fee;
        }
    }

    return $out;
}

/**
 * Backfill orders.platform_fee_rupees where it is still 0 but total_amount matches
 * subtotal + site platform fee + delivery (standard, express, or same-day), using
 * current site_settings and seller shipping rules — same math as checkout.
 *
 * @return int Number of orders updated
 */
function orders_backfill_platform_fee_on_orders(PDO $pdo): int
{
    $feeNow = site_platform_fee_rupees($pdo);
    if ($feeNow <= 0) {
        return 0;
    }

    $list = $pdo->query(
        'SELECT id, total_amount FROM orders WHERE platform_fee_rupees = 0 ORDER BY id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($list === []) {
        return 0;
    }

    $upd = $pdo->prepare(
        'UPDATE orders SET platform_fee_rupees = ? WHERE id = ? AND platform_fee_rupees = 0 LIMIT 1'
    );
    $oiSt = $pdo->prepare('SELECT product_id, price, qty FROM order_items WHERE order_id = ?');
    $updated = 0;

    foreach ($list as $row) {
        $orderId = (int) ($row['id'] ?? 0);
        $totalAmt = (int) ($row['total_amount'] ?? 0);
        if ($orderId <= 0) {
            continue;
        }

        $oiSt->execute([$orderId]);
        $lines = [];
        $subtotal = 0;
        while ($oi = $oiSt->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int) ($oi['product_id'] ?? 0);
            $pr = max(0, (int) ($oi['price'] ?? 0));
            $q = max(1, (int) ($oi['qty'] ?? 1));
            $subtotal += $pr * $q;
            if ($pid > 0) {
                $lines[] = ['id' => $pid, 'price' => $pr, 'qty' => $q];
            }
        }

        if ($lines === []) {
            continue;
        }

        $baseDelivery = cart_compute_delivery_total($pdo, $lines);
        $speedFees = cart_speed_fee_totals_for_lines($pdo, $lines);
        $expressDel = (int) ($speedFees['express'] ?? 0);
        $sameDel = (int) ($speedFees['same_day'] ?? 0);

        $expectedTotals = [
            $subtotal + $feeNow + $baseDelivery,
            $subtotal + $feeNow + $expressDel,
            $subtotal + $feeNow + $sameDel,
        ];

        if (!in_array($totalAmt, $expectedTotals, true)) {
            continue;
        }

        $upd->execute([$feeNow, $orderId]);
        if ($upd->rowCount() > 0) {
            $updated++;
        }
    }

    return $updated;
}
