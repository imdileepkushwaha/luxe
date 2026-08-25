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

try {
    $pdo = db();

    $stmt = $pdo->query(
        "SELECT DISTINCT p.category
         FROM products p
         LEFT JOIN seller_users s ON s.id = p.seller_id
         WHERE p.approval_status = 'approved'
           AND p.active = 1
           AND p.seller_id IS NOT NULL
           AND s.is_active = 1
           AND p.category IS NOT NULL
           AND TRIM(p.category) != ''
         ORDER BY p.category ASC"
    );
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $categories = array_values(array_filter(array_map('strval', $categories)));
    array_unshift($categories, 'All');

    echo json_encode([
        'ok' => true,
        'categories' => $categories,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
