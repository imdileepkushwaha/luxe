<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$pdo = db();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ($_GET['action'] ?? '');
$userId = $input['user_id'] ?? ($_GET['user_id'] ?? null);

if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    if ($action === 'list') {
        $stmt = $pdo->prepare("
            SELECT ci.*, p.name, p.price, p.image_path 
            FROM cart_items ci 
            JOIN products p ON ci.product_id = p.id 
            WHERE ci.user_id = ?
            ORDER BY ci.created_at DESC
        ");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $count = 0;
        foreach ($items as &$item) {
            $count += (int)$item['qty'];
            if ($item['image_path'] && !str_starts_with($item['image_path'], 'http')) {
                $item['image_url'] = 'http://localhost:5000/' . ltrim($item['image_path'], '/');
            }
        }
        
        echo json_encode([
            'ok' => true, 
            'items' => $items, 
            'count' => $count
        ]);
        exit;
    }

    if ($action === 'add') {
        $productId = $input['product_id'] ?? null;
        $qty = $input['qty'] ?? 1;
        
        if (!$productId) {
            echo json_encode(['ok' => false, 'error' => 'Product ID required']);
            exit;
        }
        
        // Check if already in cart
        $stmt = $pdo->prepare("SELECT id, qty FROM cart_items WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $pdo->prepare("UPDATE cart_items SET qty = qty + ? WHERE id = ?");
            $stmt->execute([$qty, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, qty) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $productId, $qty]);
        }
        
        echo json_encode(['ok' => true, 'message' => 'Item added to cart']);
        exit;
    }

    if ($action === 'remove') {
        $cartItemId = $input['cart_item_id'] ?? null;
        if (!$cartItemId) {
            echo json_encode(['ok' => false, 'error' => 'Cart item ID required']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
        $stmt->execute([$cartItemId, $userId]);
        
        echo json_encode(['ok' => true, 'message' => 'Item removed from cart']);
        exit;
    }

    if ($action === 'update') {
        $cartItemId = $input['cart_item_id'] ?? null;
        $qty = $input['qty'] ?? 1;
        if (!$cartItemId) {
            echo json_encode(['ok' => false, 'error' => 'Cart item ID required']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE cart_items SET qty = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$qty, $cartItemId, $userId]);
        echo json_encode(['ok' => true, 'message' => 'Quantity updated']);
        exit;
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
