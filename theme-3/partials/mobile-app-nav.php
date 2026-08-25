<?php
declare(strict_types=1);

$theme1CurrentScript = basename((string) ($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
$tabQuery = (string) ($_GET['tab'] ?? '');
$isWishlist = $theme1CurrentScript === 'profile.php' && $tabQuery === 'wishlist';
$isHome = $theme1CurrentScript === 'index.php';
$isCategories = in_array($theme1CurrentScript, ['product-list.php', 'product.php'], true);
$isOrders = $theme1CurrentScript === 'orders.php';
$isProfile = in_array($theme1CurrentScript, ['profile.php', 'settings.php', 'address.php'], true) && !$isWishlist;

$t3NavLogin = 'login.php?redirect=' . rawurlencode($theme1CurrentScript !== '' ? $theme1CurrentScript : 'index.php');
$ordersHref = !empty($isLoggedIn) ? 'orders.php' : $t3NavLogin;
$profileHref = !empty($isLoggedIn) ? 'profile.php' : $t3NavLogin;
$catalogHref = !empty($isLoggedIn) ? 'profile.php?tab=wishlist' : $t3NavLogin;
?>
<nav class="t3-app-tabbar" aria-label="App navigation">
  <a href="index.php" class="t3-app-tab<?= $isHome ? ' is-active' : '' ?>">
    <span class="t3-app-tab__icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="<?= $isHome ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 10.5 12 3l9 7.5V20a1.5 1.5 0 0 1-1.5 1.5H16v-6H8v6H4.5A1.5 1.5 0 0 1 3 20Z"/>
      </svg>
    </span>
    <span class="t3-app-tab__label">Home</span>
  </a>
  <a href="product-list.php" class="t3-app-tab<?= $isCategories ? ' is-active' : '' ?>">
    <span class="t3-app-tab__icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
        <circle cx="5.5" cy="5.5" r="1.7"/>
        <circle cx="12" cy="5.5" r="1.7"/>
        <circle cx="18.5" cy="5.5" r="1.7"/>
        <circle cx="5.5" cy="12" r="1.7"/>
        <circle cx="12" cy="12" r="1.7"/>
        <circle cx="18.5" cy="12" r="1.7"/>
        <circle cx="5.5" cy="18.5" r="1.7"/>
        <circle cx="12" cy="18.5" r="1.7"/>
        <circle cx="18.5" cy="18.5" r="1.7"/>
      </svg>
    </span>
    <span class="t3-app-tab__label">Categories</span>
  </a>
  <a href="<?= h($ordersHref) ?>" class="t3-app-tab<?= $isOrders ? ' is-active' : '' ?>">
    <span class="t3-app-tab__icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
        <path d="M3.3 7 12 12l8.7-5"/>
        <path d="M12 22V12"/>
      </svg>
    </span>
    <span class="t3-app-tab__label">Orders</span>
  </a>
  <a href="<?= h($catalogHref) ?>" class="t3-app-tab<?= $isWishlist ? ' is-active' : '' ?>">
    <span class="t3-app-tab__icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
      </svg>
    </span>
    <span class="t3-app-tab__label">Wishlist</span>
  </a>
  <a href="<?= h($profileHref) ?>" class="t3-app-tab<?= $isProfile ? ' is-active' : '' ?>">
    <span class="t3-app-tab__icon" aria-hidden="true">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
        <circle cx="12" cy="7" r="4"/>
      </svg>
    </span>
    <span class="t3-app-tab__label">Profile</span>
  </a>
</nav>
