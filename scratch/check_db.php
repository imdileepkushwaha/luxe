<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();
$stmt = $pdo->query("SELECT COUNT(*) FROM products");
echo "Total Products: " . $stmt->fetchColumn() . "\n";

$stmt = $pdo->query("SELECT id, name, category, price, active, approval_status FROM products LIMIT 10");
$products = $stmt->fetchAll();
print_r($products);
