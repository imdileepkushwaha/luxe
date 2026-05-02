<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/orders_repo.php';
require_once __DIR__ . '/loyalty_points.php';
require_once __DIR__ . '/addresses.php';
require_once __DIR__ . '/account_deletion.php';
require_once __DIR__ . '/cms.php';

auth_start();

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

require_once __DIR__ . '/storefront_theme.php';
storefront_theme_dispatch_from_root_if_needed();
