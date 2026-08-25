<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

$pdo = db();
$user = auth_user($pdo);

$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $item) {
    $cartCount += (int) ($item['qty'] ?? 1);
}

$userName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($userName === '') {
    $userName = trim((string) ($user['name'] ?? 'Guest User'));
}
$userInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $userName) ?: 'GU', 0, 2));
$userEmail = trim((string) ($user['email'] ?? ''));
$isLoggedIn = $user !== null;

$theme1Self = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php'));
$theme1ThemeDir = basename(dirname(__DIR__));
$theme1LoginHref = 'login.php?redirect=' . rawurlencode($theme1ThemeDir . '/' . $theme1Self);

/** @var array<int,string> $theme1HeaderCategories */
$theme1HeaderCategories = $theme1HeaderCategories ?? [
    "Men's Fashion",
    "Women's Fashion",
    "Kid's Fashion",
    "Footwear",
];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = $theme1HeaderCategories;
