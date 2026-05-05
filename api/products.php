<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

try {
    $pdo = db();
    
    $category = $_GET['category'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $search = $_GET['search'] ?? null;
    
    $query = "SELECT * FROM products WHERE 1=1";
    $params = [];
    
    if ($category && $category !== 'All') {
        $query .= " AND category = ?";
        $params[] = $category;
    }

    if ($search) {
        $query .= " AND (name LIKE ? OR category LIKE ? OR description LIKE ?)";
        $s = "%$search%";
        $params[] = $s;
        $params[] = $s;
        $params[] = $s;
    }
    
    $query .= " ORDER BY created_at DESC LIMIT " . (int)$limit;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as &$product) {
        $product['id'] = (int)$product['id'];
        $product['price'] = (int)$product['price'];
        $product['original_price'] = (int)$product['original_price'];
        
        // Fetch additional images
        $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
        $imgStmt->execute([$product['id']]);
        $extraImages = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $allImages = [];
        if ($product['image_path']) {
            $allImages[] = $product['image_path'];
        }
        foreach ($extraImages as $ei) {
            $allImages[] = $ei;
        }
        
        $product['images'] = [];
        foreach ($allImages as $path) {
            if (!str_starts_with($path, 'http')) {
                $product['images'][] = 'http://localhost:5000/' . ltrim($path, '/');
            } else {
                $product['images'][] = $path;
            }
        }
        
        // Main image fallback
        $product['image_url'] = $product['images'][0] ?? ("https://picsum.photos/seed/" . $product['id'] . "/400/600");
    }
    
    echo json_encode([
        'ok' => true,
        'products' => $products
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'DB Connection Error',
        'details' => $e->getMessage()
    ]);
}
