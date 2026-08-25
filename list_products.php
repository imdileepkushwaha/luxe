<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();
$stmt = $pdo->query("SELECT id, name, category, image_path FROM products");
$products = $stmt->fetchAll();
echo json_encode($products, JSON_PRETTY_PRINT);
