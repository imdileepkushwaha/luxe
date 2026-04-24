<?php

declare(strict_types=1);

require_once __DIR__ . '/cart_session.php';
require_once __DIR__ . '/coupons.php';
require_once __DIR__ . '/site_settings.php';

/**
 * Read-only cart resolution + totals (must match actions/place-order.php pricing).
 *
 * @param list<array<string,mixed>> $items
 * @return array{ok:true,lines_for_shipping:list<array{id:int,price:int,qty:int,seller_id:int}>,safe_total:int,order_total:int,platform_fee:int,admin_commission:int,delivery_total:int,coupon_discount:int}|array{ok:false,message:string}
 */
function checkout_order_quote(PDO $pdo, array $items, string $deliverySpeed, string $couponCode): array
{
    $productSt = $pdo->prepare(
        'SELECT p.id, p.name, p.emoji, p.price, p.stock_qty, p.seller_id
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

    $safeTotal = 0;
    $savedAny = false;
    $linesForShipping = [];

    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $pid = isset($it['id']) ? (int) $it['id'] : 0;
        if ($pid <= 0) {
            continue;
        }

        $productSt->execute([$pid]);
        $productRow = $productSt->fetch(PDO::FETCH_ASSOC);
        if (!$productRow) {
            continue;
        }

        $price = max(0, (int) ($productRow['price'] ?? 0));
        $requestedQty = max(1, (int) ($it['qty'] ?? 1));
        $maxForLine = cart_line_max_qty($pdo, $pid, (string) ($it['size'] ?? ''), (string) ($it['color'] ?? ''));
        if ($maxForLine <= 0) {
            continue;
        }
        $qty = min($requestedQty, $maxForLine);
        if ($qty <= 0) {
            continue;
        }

        $safeTotal += ($price * $qty);
        $savedAny = true;
        $linesForShipping[] = [
            'id' => $pid,
            'price' => $price,
            'qty' => $qty,
            'seller_id' => max(0, (int) ($productRow['seller_id'] ?? 0)),
        ];
    }

    if (!$savedAny) {
        return ['ok' => false, 'message' => 'No valid items available for order.'];
    }

    $platformFee = site_platform_fee_rupees($pdo);
    $adminCommission = order_admin_commission_rupees_from_subtotal(
        $safeTotal,
        site_admin_seller_commission_percent($pdo)
    );
    $deliveryTotal = cart_compute_delivery_total($pdo, $linesForShipping);
    $speedFees = cart_speed_fee_totals_for_lines($pdo, $linesForShipping);
    if ($deliverySpeed === 'express') {
        $deliveryTotal = (int) ($speedFees['express'] ?? 0);
    } elseif ($deliverySpeed === 'same_day') {
        $deliveryTotal = (int) ($speedFees['same_day'] ?? 0);
    }
    $couponDiscount = coupons_order_discount_rupees($pdo, $couponCode, $linesForShipping);
    $orderTotal = max(0, $safeTotal + $platformFee + $deliveryTotal - $couponDiscount);

    return [
        'ok' => true,
        'lines_for_shipping' => $linesForShipping,
        'safe_total' => $safeTotal,
        'order_total' => $orderTotal,
        'platform_fee' => $platformFee,
        'admin_commission' => $adminCommission,
        'delivery_total' => $deliveryTotal,
        'coupon_discount' => $couponDiscount,
    ];
}
