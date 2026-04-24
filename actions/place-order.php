<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart_session.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/../includes/notification_mail.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$userId = auth_user_id();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please sign in to place an order.']);
    exit;
}

$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No items to order.']);
    exit;
}

$items = $data['items'];
$payment = trim((string) ($data['payment_method'] ?? 'Card'));
$deliverySpeedRaw = $data['delivery_speed'] ?? 'standard';
$deliverySpeed = 'standard';
if (is_string($deliverySpeedRaw) && in_array($deliverySpeedRaw, ['standard', 'express', 'same_day'], true)) {
    $deliverySpeed = $deliverySpeedRaw;
} elseif (is_numeric($deliverySpeedRaw)) {
    $n = (int) $deliverySpeedRaw;
    if ($n === 99) {
        $deliverySpeed = 'express';
    } elseif ($n === 199) {
        $deliverySpeed = 'same_day';
    }
}
$addressId = isset($data['address_id']) ? (int) $data['address_id'] : 0;
$address = trim((string) ($data['shipping_address'] ?? ''));
$couponCode = coupons_normalize_code((string) ($data['coupon_code'] ?? ''));

try {
    $pdo = db();
    if ($addressId > 0) {
        $addrRow = addresses_get_for_user($pdo, $userId, $addressId);
        if (!$addrRow) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Invalid address. Please choose a saved address.']);
            exit;
        }
        $address = addresses_format_shipping($addrRow);
    }
    if ($address === '') {
        $address = 'Address on file';
    }
    $pdo->beginTransaction();
    $ref = 'LUXE' . substr((string) time(), -6) . random_int(100, 999);
    $insO = $pdo->prepare(
        'INSERT INTO orders (user_id, order_ref, status, total_amount, payment_method, shipping_address, platform_fee_rupees)
         VALUES (?,?,?,?,?,?,?)'
    );
    $insO->execute([$userId, $ref, 'processing', 0, $payment, $address, 0]);
    $orderId = (int) $pdo->lastInsertId();

    $insI = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, name, emoji, variant_text, price, qty, status) VALUES (?,?,?,?,?,?,?,?)'
    );
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
    $variantCountSt = $pdo->prepare(
        'SELECT COUNT(*) FROM product_variant_inventory
         WHERE product_id = ? AND active = 1'
    );
    $variantLockSt = $pdo->prepare(
        'SELECT id, stock_qty
         FROM product_variant_inventory
         WHERE product_id = ?
           AND active = 1
           AND LOWER(TRIM(size_label)) = ?
           AND LOWER(TRIM(color_label)) = ?
         LIMIT 1
         FOR UPDATE'
    );
    $variantDeductSt = $pdo->prepare(
        'UPDATE product_variant_inventory
         SET stock_qty = stock_qty - ?
         WHERE id = ?
           AND stock_qty >= ?
         LIMIT 1'
    );
    $productLockSt = $pdo->prepare(
        'SELECT stock_qty
         FROM products
         WHERE id = ?
         LIMIT 1
         FOR UPDATE'
    );
    $productDeductSt = $pdo->prepare(
        'UPDATE products
         SET stock_qty = stock_qty - ?
         WHERE id = ?
           AND stock_qty >= ?
         LIMIT 1'
    );
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

        $name = (string) ($productRow['name'] ?? 'Item');
        $emoji = (string) ($productRow['emoji'] ?? '📦');
        $variant = (string) ($it['size'] ?? '') . (isset($it['color']) ? ' · ' . $it['color'] : '');
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

        $size = cart_normalize_variant_size((string) ($it['size'] ?? ''));
        $color = cart_normalize_variant_color((string) ($it['color'] ?? ''));
        $variantSizeKey = mb_strtolower($size);
        $variantColorKey = mb_strtolower($color);

        $variantCountSt->execute([$pid]);
        $hasVariantRows = (int) $variantCountSt->fetchColumn() > 0;
        if ($hasVariantRows) {
            $variantLockSt->execute([$pid, $variantSizeKey, $variantColorKey]);
            $variantRow = $variantLockSt->fetch(PDO::FETCH_ASSOC);
            if (!$variantRow) {
                continue;
            }
            $availableVariantQty = max(0, (int) ($variantRow['stock_qty'] ?? 0));
            if ($availableVariantQty <= 0) {
                continue;
            }
            $qty = min($qty, $availableVariantQty);
            if ($qty <= 0) {
                continue;
            }
            $variantDeductSt->execute([$qty, (int) $variantRow['id'], $qty]);
            if ($variantDeductSt->rowCount() < 1) {
                continue;
            }
        } else {
            $productLockSt->execute([$pid]);
            $availableProductQty = max(0, (int) $productLockSt->fetchColumn());
            if ($availableProductQty <= 0) {
                continue;
            }
            $qty = min($qty, $availableProductQty);
            if ($qty <= 0) {
                continue;
            }
            $productDeductSt->execute([$qty, $pid, $qty]);
            if ($productDeductSt->rowCount() < 1) {
                continue;
            }
        }

        $insI->execute([$orderId, $pid, $name, $emoji, $variant, $price, $qty, 'processing']);
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
        throw new RuntimeException('No valid items available for order.');
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

    $updO = $pdo->prepare(
        'UPDATE orders
         SET total_amount = ?, platform_fee_rupees = ?, admin_commission_rupees = ?
         WHERE id = ?
         LIMIT 1'
    );
    $updO->execute([$orderTotal, $platformFee, $adminCommission, $orderId]);

    $_SESSION['cart'] = [];
    unset($_SESSION['checkout']);
    $pdo->commit();
    try {
        $userSt = $pdo->prepare(
            "SELECT email, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))), ''), 'Customer') AS customer_name
             FROM users WHERE id = ? LIMIT 1"
        );
        $userSt->execute([$userId]);
        $userRow = $userSt->fetch(PDO::FETCH_ASSOC) ?: null;
        $userEmail = trim((string) ($userRow['email'] ?? ''));
        if ($userEmail !== '') {
            $customerName = trim((string) ($userRow['customer_name'] ?? 'Customer'));
            luxe_send_order_update_email($userEmail, $customerName, $ref, 'processing');
        }
    } catch (Throwable $mailErr) {
        error_log('LUXE order mail send failed: ' . $mailErr->getMessage());
    }
    echo json_encode(['ok' => true, 'order_ref' => $ref]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $message = $e instanceof RuntimeException ? $e->getMessage() : 'Could not place order.';
    http_response_code($e instanceof RuntimeException ? 422 : 500);
    echo json_encode(['ok' => false, 'message' => $message]);
}
