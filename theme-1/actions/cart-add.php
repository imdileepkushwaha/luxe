<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/cart_session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

$pdo        = db();
$productId  = (int) ($_POST['product_id'] ?? 0);
$qty        = max(1, (int) ($_POST['qty'] ?? 1));
$size       = trim((string) ($_POST['size']  ?? ''));
$color      = trim((string) ($_POST['color'] ?? ''));

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Invalid product']);
    exit;
}

// Fetch product details
$st = $pdo->prepare(
    'SELECT p.id, p.name, p.emoji, p.price, p.stock_qty, p.brand, p.seller_id, p.image_path
     FROM products p
     LEFT JOIN seller_users s ON s.id = p.seller_id
     WHERE p.id = ?
       AND p.active = 1
       AND p.approval_status = \'approved\'
       AND p.seller_id IS NOT NULL
       AND s.is_active = 1
     LIMIT 1'
);
$st->execute([$productId]);
$product = $st->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Product not found or unavailable']);
    exit;
}

// Check stock
$maxQty = cart_line_max_qty($pdo, $productId, $size, $color);
if ($maxQty <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Out of stock']);
    exit;
}

$qty = min($qty, $maxQty);

// Build cart line
$newLine = [
    'id'        => (int) $product['id'],
    'name'      => (string) $product['name'],
    'brand'     => (string) ($product['brand'] ?? ''),
    'emoji'     => (string) ($product['emoji'] ?? '📦'),
    'image_path'=> (string) ($product['image_path'] ?? ''),
    'price'     => max(0, (int) $product['price']),
    'orig'      => max(0, (int) $product['price']),
    'qty'       => $qty,
    'size'      => $size,
    'color'     => $color,
    'checked'   => true,
    'seller_id' => (int) ($product['seller_id'] ?? 0),
];

// Merge into session cart
$cart = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
$cart[] = $newLine;
$cart = cart_merge_duplicate_lines($cart);
$cart = cart_filter_available_items($pdo, $cart);
$_SESSION['cart'] = $cart;

// Total cart item count
$totalCount = 0;
foreach ($cart as $line) {
    $totalCount += max(1, (int) ($line['qty'] ?? 1));
}

echo json_encode([
    'ok'          => true,
    'msg'         => 'Added to cart!',
    'cart_count'  => $totalCount,
    'cart'        => $cart,
]);
