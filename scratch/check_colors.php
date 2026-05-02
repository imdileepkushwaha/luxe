<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();
$st = $pdo->query("SELECT id, name, primary_color FROM products LIMIT 10");
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
