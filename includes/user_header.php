<?php
declare(strict_types=1);

if (!function_exists('h')) {
    return;
}

$header = $header ?? [];
$headerUser = $header['user'] ?? null;
$headerCartCount = (int) ($header['cart_count'] ?? 0);
$headerTopText = (string) ($header['top_text'] ?? 'New arrivals every week');
$headerHighlight = (string) ($header['top_highlight'] ?? 'Free shipping above ₹999');
$headerHighlights = $header['top_highlights'] ?? [$headerHighlight];
if (!is_array($headerHighlights) || $headerHighlights === []) {
    $headerHighlights = [$headerHighlight];
}
$headerHighlights = array_values(array_filter(array_map(
    static fn($v): string => trim((string) $v),
    $headerHighlights
), static fn(string $v): bool => $v !== ''));
if ($headerHighlights === []) {
    $headerHighlights = [$headerHighlight];
}
$headerTopLinks = $header['top_links'] ?? [
    ['label' => "Today's Deals", 'href' => 'index.php#deals'],
    ['label' => 'Top Brands', 'href' => 'index.php#brands'],
];
$headerMenuLinks = $header['menu_links'] ?? [
    ['label' => 'Home', 'href' => 'index.php'],
    ['label' => 'Shop', 'href' => 'product-list.php'],
    ['label' => 'Collections', 'href' => 'index.php#collections'],
    ['label' => 'Trending', 'href' => 'index.php#trending'],
    ['label' => 'Deals', 'href' => 'index.php#deals'],
    ['label' => 'Brands', 'href' => 'index.php#brands'],
];
$headerWishlistHref = (string) ($header['wishlist_href'] ?? (
    $headerUser
        ? 'profile.php?tab=wishlist'
        : 'login.php?redirect=' . rawurlencode('profile.php?tab=wishlist')
));
$headerBreadcrumb = $header['breadcrumb'] ?? null;
$headerSearchLead = (string) ($header['search_lead'] ?? 'Search by product name, brand, or category — matches show below.');
$headerUserName = trim((string) (($headerUser['first_name'] ?? '') . ' ' . ($headerUser['last_name'] ?? '')));
if ($headerUserName === '') {
    $headerUserName = trim((string) ($headerUser['name'] ?? 'Member'));
}
$headerUserEmail = trim((string) ($headerUser['email'] ?? ''));

$headerSiteBrand = '';
$headerSiteLogo = '';
if (function_exists('site_brand_name') && function_exists('db')) {
    try {
        $headerSiteBrand = site_brand_name(db());
        $headerSiteLogo = site_logo_path(db());
    } catch (Throwable $e) {
        $headerSiteBrand = '';
        $headerSiteLogo = '';
    }
}
if ($headerSiteBrand === '') {
    $headerSiteBrand = 'LUXE';
}
$headerLogoUrl = $headerSiteLogo !== '' ? ltrim($headerSiteLogo, '/') : '';
?>
<nav class="navbar navbar-kart" id="navbar">
  <div class="nav-kart-topbar">
    <div class="nav-kart-topbar__inner">
      <p class="nav-kart-topbar__text"><span class="nav-kart-topbar__highlight" data-rotating-offers='<?= h((string) json_encode($headerHighlights, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'><?= h($headerHighlights[0]) ?></span> · <?= h($headerTopText) ?></p>
      <div class="nav-kart-topbar__links">
        <?php foreach ($headerTopLinks as $item): ?>
          <a href="<?= h((string) ($item['href'] ?? '#')) ?>"><?= h((string) ($item['label'] ?? 'Link')) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="nav-container nav-kart-main">
    <div class="nav-brand-cluster">
      <?php require __DIR__ . '/nav_hamburger_btn.php'; ?>
      <a href="index.php" class="nav-logo">
        <?php if ($headerLogoUrl !== ''): ?>
          <img src="<?= h($headerLogoUrl) ?>" alt="<?= h($headerSiteBrand) ?>" class="nav-logo__img">
        <?php else: ?>
          <?= h($headerSiteBrand) ?>
        <?php endif; ?>
      </a>
    </div>
    <form class="nav-kart-search" id="headerQuickSearch" role="search" aria-label="Quick product search">
      <span class="nav-kart-search__icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </span>
      <input type="search" id="headerQuickSearchInput" placeholder="Search products, brands and categories..." autocomplete="off" />
      <button type="submit">Search</button>
    </form>
    <div class="nav-actions">
      <button class="icon-btn nav-search-mobile-btn" id="searchBtn" aria-label="Search">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <a href="<?= h($headerWishlistHref) ?>" class="icon-btn" aria-label="Wishlist" data-nav-mobile="drawer">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
      </a>
      <a href="cart.php" class="cart-btn" aria-label="Cart">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <span class="cart-count" id="cartCount"><?= $headerCartCount ?></span>
      </a>
    </div>
  </div>
  <div class="nav-kart-menu-wrap">
    <div class="nav-kart-menu-shell">
      <ul class="nav-links nav-kart-menu">
        <?php foreach ($headerMenuLinks as $item): ?>
          <li><a href="<?= h((string) ($item['href'] ?? '#')) ?>"><?= h((string) ($item['label'] ?? 'Menu')) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($headerUser): ?>
      <div class="nav-kart-auth-actions">
        <div class="nav-kart-user-meta">
          <strong><?= h($headerUserName) ?></strong>
          <?php if ($headerUserEmail !== ''): ?>
            <span><?= h($headerUserEmail) ?></span>
          <?php endif; ?>
        </div>
        <div class="nav-kart-profile-wrap">
          <a href="profile.php" class="icon-btn nav-kart-profile-btn" aria-label="Profile menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </a>
          <div class="nav-kart-profile-dropdown" role="menu" aria-label="Profile quick links">
            <a href="profile.php" role="menuitem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span>Profile</span>
            </a>
            <a href="orders.php" role="menuitem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              <span>My Orders</span>
            </a>
            <a href="profile.php?tab=settings" role="menuitem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 4.4 17l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.2 7.2A2 2 0 1 1 7.03 4.4l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06A2 2 0 1 1 19.6 7l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
              <span>Settings</span>
            </a>
            <a href="actions/logout.php" role="menuitem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              <span>Logout</span>
            </a>
          </div>
        </div>
      </div>
      <?php else: ?>
      <a href="login.php" class="nav-login-btn nav-kart-auth-btn" aria-label="Login">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Sign In
      </a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<?php require __DIR__ . '/nav_drawer.php'; ?>

<div class="search-overlay" id="searchOverlay" role="dialog" aria-modal="true" aria-labelledby="searchOverlayTitle">
  <div class="search-overlay__ambient" aria-hidden="true"></div>
  <button class="search-close" id="searchClose" type="button" aria-label="Close search">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
  </button>
  <div class="search-inner">
    <p class="search-kicker">LUXE catalog</p>
    <h2 id="searchOverlayTitle" class="search-title">Find your next favorite</h2>
    <p class="search-lead"><?= h($headerSearchLead) ?></p>
    <div class="search-panel">
      <label class="search-box" for="searchInput">
        <span class="search-box__icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </span>
        <input type="search" id="searchInput" name="q" placeholder="Try Nike, watch, serum..." autocomplete="off" enterkeyhint="search" />
      </label>
      <p class="search-hint">
        <span class="search-hint__desktop"><kbd>Enter</kbd><span class="search-hint__text">Jumps to matching results</span></span>
        <span class="search-hint__mobile">Submit search to view results</span>
      </p>
      <div class="search-live-results" id="searchLiveResults" hidden aria-live="polite"></div>
    </div>
    <div class="search-tags-block">
      <span class="search-tags-label">Popular picks</span>
      <div class="search-tags">
        <button type="button" class="tag">Sneakers</button>
        <button type="button" class="tag">Bags</button>
        <button type="button" class="tag">Watches</button>
        <button type="button" class="tag">Laptops</button>
        <button type="button" class="tag">Skincare</button>
      </div>
    </div>
  </div>
</div>
