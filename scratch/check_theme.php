<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$pdo = db();
$stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'storefront_theme'");
$stmt->execute();
$value = $stmt->fetchColumn();
echo "Active Theme: " . ($value ?: 'default');
