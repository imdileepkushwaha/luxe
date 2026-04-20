<?php
declare(strict_types=1);

if (!function_exists('db') || !function_exists('auth_user')) {
    return;
}

$pdoNav = db();
$userNav = auth_user($pdoNav);
$cartNavCt = 0;
foreach ($_SESSION['cart'] ?? [] as $ci) {
    $cartNavCt += (int) ($ci['qty'] ?? 1);
}
$wishlistNavHref = $userNav
    ? 'profile.php?tab=wishlist'
    : 'login.php?redirect=' . rawurlencode('profile.php?tab=wishlist');
$fn = trim((string) ($userNav['first_name'] ?? ''));
$ln = trim((string) ($userNav['last_name'] ?? ''));
$displayName = trim($fn . ' ' . $ln);
if ($displayName === '') {
    $displayName = 'Member';
}
$initial = $fn !== '' ? strtoupper(substr($fn, 0, 1)) : ($userNav ? 'U' : '?');
$emailNav = trim((string) ($userNav['email'] ?? ''));
?>
<div class="nav-drawer" id="navDrawer" role="dialog" aria-modal="true" aria-label="Site menu" aria-hidden="true">
  <div class="nav-drawer__backdrop" data-nav-drawer-close tabindex="-1"></div>
  <div class="nav-drawer__panel">
    <div class="nav-drawer__head">
      <span class="nav-drawer__title">Menu</span>
      <button type="button" class="nav-drawer__close" data-nav-drawer-close aria-label="Close menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="nav-drawer__body">
      <?php if ($userNav): ?>
      <div class="nav-drawer__user">
        <div class="nav-drawer__avatar" aria-hidden="true"><?= h($initial) ?></div>
        <div class="nav-drawer__user-text">
          <strong><?= h($displayName) ?></strong>
          <?php if ($emailNav !== ''): ?>
          <span><?= h($emailNav) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="nav-drawer__actions">
        <a href="profile.php" class="nav-drawer__btn nav-drawer__btn--primary">My profile</a>
        <a href="orders.php" class="nav-drawer__btn">My orders</a>
        <a href="<?= h($wishlistNavHref) ?>" class="nav-drawer__btn">Wishlist</a>
        <a href="actions/logout.php" class="nav-drawer__btn nav-drawer__btn--ghost">Sign out</a>
      </div>
      <?php else: ?>
      <div class="nav-drawer__guest">
        <p class="nav-drawer__guest-lead">Sign in for orders, wishlist &amp; saved addresses.</p>
        <div class="nav-drawer__actions">
          <a href="login.php" class="nav-drawer__btn nav-drawer__btn--primary">Sign in</a>
          <a href="login.php#register" class="nav-drawer__btn">Create account</a>
        </div>
      </div>
      <?php endif; ?>

      <div class="nav-drawer__section-label">Shop</div>
      <nav class="nav-drawer__links" aria-label="Store">
        <a href="index.php">Home</a>
        <a href="index.php#collections">Collections</a>
        <a href="index.php#trending">Trending</a>
        <a href="index.php#deals">Deals</a>
        <a href="index.php#brands">Brands</a>
        <a href="cart.php" class="nav-drawer__cart-link">Cart<?php if ($cartNavCt > 0): ?> <span class="nav-drawer__badge"><?= (int) $cartNavCt ?></span><?php endif; ?></a>
      </nav>
    </div>
  </div>
</div>
