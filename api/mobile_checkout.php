<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$pdo = db();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}
$userId = (int) ($input['user_id'] ?? 0);
$address = trim((string) ($input['address'] ?? ''));
$paymentMethod = $input['payment_method'] ?? 'COD';
$addressId = (int) ($input['address_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

if ($addressId > 0) {
    $saved = addresses_get_for_user($pdo, $userId, $addressId);
    if (!$saved) {
        echo json_encode(['ok' => false, 'error' => 'Saved address not found']);
        exit;
    }
    $address = addresses_format_shipping($saved);
}

if ($address === '') {
    echo json_encode(['ok' => false, 'error' => 'Delivery address required']);
    exit;
}

try {
    // 1. Get cart items
    $stmt = $pdo->prepare("SELECT ci.*, p.price, p.name FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.user_id = ?");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cartItems)) {
        echo json_encode(['ok' => false, 'error' => 'Cart is empty']);
        exit;
    }
    
    $totalAmount = 0;
    foreach ($cartItems as $item) {
        $totalAmount += (int)$item['price'] * (int)$item['qty'];
    }
    
    // 2. Create order
    $orderRef = 'LUXEM' . strtoupper(substr(uniqid(), -8));
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, order_ref, status, total_amount, payment_method, shipping_address) VALUES (?, ?, 'processing', ?, ?, ?)");
    $stmt->execute([$userId, $orderRef, $totalAmount, $paymentMethod, $address]);
    $orderId = $pdo->lastInsertId();
    
    // 3. Move items to order_items
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, name, price, qty, status) VALUES (?, ?, ?, ?, ?, 'processing')");
    foreach ($cartItems as $item) {
        $stmt->execute([$orderId, $item['product_id'], $item['name'], $item['price'], $item['qty']]);
    }
    
    // 4. Clear cart
    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    echo json_encode([
        'ok' => true, 
        'message' => 'Order placed successfully',
        'order_ref' => $orderRef
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
