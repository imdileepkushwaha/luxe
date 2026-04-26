<?php
declare(strict_types=1);

$theme1HeaderCategories = $theme1HeaderCategories ?? [];
if (!is_array($theme1HeaderCategories) || $theme1HeaderCategories === []) {
    $theme1HeaderCategories = [
        "Men's Fashion",
        "Women's Fashion",
        "Kid's Fashion",
    ];
}
$theme1HeaderCompareCount = (int) ($theme1HeaderCompareCount ?? 0);
$theme1HeaderCartCount = (int) ($theme1HeaderCartCount ?? 0);
$isLoggedIn = (bool) ($isLoggedIn ?? false);
$userInitials = (string) ($userInitials ?? 'GU');
$userName = (string) ($userName ?? 'Guest User');
$userEmail = (string) ($userEmail ?? '');
$theme1LoginHref = (string) ($theme1LoginHref ?? ('login.php?redirect=' . rawurlencode('theme-1/index.php')));
?>
<header class="site-header">
  <div class="container header-inner">
    <a class="header-logo" href="index.php" aria-label="LUXE home">
      <span class="logo-mark" aria-hidden="true"><span class="logo-stripe"></span><span class="logo-stripe"></span><span class="logo-stripe"></span></span>
      <span class="logo-word">LUXE</span>
    </a>
    <form class="header-search" role="search" action="index.php" method="get">
      <div class="search-cat">
        <label class="visually-hidden" for="header-cat">Category</label>
        <select id="header-cat" name="category" class="search-cat-select">
          <option value="">All Categories</option>
          <?php foreach ($theme1HeaderCategories as $category): ?>
            <option value="<?= h(strtolower((string) $category)) ?>"><?= h((string) $category) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <span class="search-divider" aria-hidden="true"></span>
      <input type="search" class="search-input" name="q" placeholder="Search your product..." autocomplete="off">
      <button type="submit" class="search-submit" aria-label="Search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/>
          <path d="M20 20l-4.3-4.3"/>
        </svg>
      </button>
    </form>
    <div class="subnav-tools">
      <a href="index.php#trending" class="tool-link" title="Compare">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14.3918 16.7996V6.12709" stroke="#1E1F21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M18 13.5894L14.3921 16.8L10.7842 13.5894" stroke="#1E1F21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M5.60812 4.00044V14.6729" stroke="#1E1F21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M2 7.21062L5.6079 4L9.2158 7.21062" stroke="#1E1F21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="badge-count"><?= $theme1HeaderCompareCount ?></span>
      </a>
      <a href="profile.php" class="tool-link" title="Wishlist">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M9.68868 17.0951C11.4023 16.0404 12.9965 14.7992 14.4427 13.3936C15.4594 12.3813 16.2334 11.1472 16.7055 9.78583C17.5549 7.14498 16.5627 4.12171 13.786 3.22699C12.3266 2.75719 10.7328 3.0257 9.50309 3.94853C8.27289 3.02682 6.67964 2.7584 5.22021 3.22699C2.44348 4.12171 1.44415 7.14498 2.29358 9.78583C2.76562 11.1472 3.53964 12.3813 4.55637 13.3936C6.00254 14.7992 7.59671 16.0404 9.31036 17.0951L9.49595 17.2105L9.68868 17.0951Z" stroke="#1E1F21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="badge-count" id="theme1WishlistCount">0</span>
      </a>
      <a href="cart.php" class="tool-link" title="Cart">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M6.53688 18.222H13.1476C15.5758 18.222 17.4387 17.3449 16.9096 13.8149L16.2934 9.03084C15.9673 7.26944 14.8437 6.59532 13.8579 6.59532H5.79752C4.79722 6.59532 3.73893 7.32018 3.36201 9.03084L2.74588 13.8149C2.29647 16.9463 4.10861 18.222 6.53688 18.222Z" stroke="#1E1F21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M6.42814 6.42135C6.42814 4.53181 7.95992 3.00003 9.84946 3.00003V3.00003C10.7594 2.99618 11.6333 3.35493 12.2781 3.99697C12.9228 4.63901 13.2853 5.51144 13.2853 6.42135V6.42135" stroke="#1E1F21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M7.50092 9.98764H7.53716" stroke="#1E1F21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M12.1182 9.98764H12.1545" stroke="#1E1F21" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="badge-count"><?= $theme1HeaderCartCount ?></span>
      </a>
    </div>
  </div>
</header>
<nav class="navbar">
<div class="container">
  <div class="subnav-strip">
    
    <a class="subnav-logo" href="index.php" aria-label="LUXE home">
      <span class="logo-mark" aria-hidden="true"><span class="logo-stripe"></span><span class="logo-stripe"></span><span class="logo-stripe"></span></span>
      <span class="logo-word">LUXE</span>
    </a>
    <nav class="subnav-menu" aria-label="Main">
      <a href="index.php" class="is-active">Home</a>
      <a href="index.php#trending">Shop <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="index.php">Vendor <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="index.php#deals">Flash Deals <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="profile.php">Pages <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="index.php">Blog <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="index.php">Contact</a>
    </nav>
    <div class="subnav-tools">
      <?php if ($isLoggedIn): ?>
        <div class="user-chip user-chip--dropdown">
          <span class="user-avatar" aria-hidden="true"><?= h($userInitials) ?></span>
          <span class="user-meta">
            <span class="user-name"><?= h($userName) ?></span>
            <?php if ($userEmail !== ''): ?><span class="user-email"><?= h($userEmail) ?></span><?php endif; ?>
          </span>
          <div class="user-chip-menu" role="menu" aria-label="User menu">
            <a href="profile.php" role="menuitem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <span>Profile</span>
            </a>
            <a href="orders.php" role="menuitem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              <span>Orders</span>
            </a>
            <a href="../actions/logout.php?redirect=theme-1/index.php" role="menuitem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              <span>Logout</span>
            </a>
          </div>
        </div>
      <?php else: ?>
        <a class="btn-hero btn-hero-sm" href="<?= h($theme1LoginHref) ?>">Login</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</nav>
<script>
  (function () {
    if (window.__theme1WishlistBadgeInit) return;
    window.__theme1WishlistBadgeInit = true;
    var key = "luxe_profile_wishlist_v1";
    var el = document.getElementById("theme1WishlistCount");
    if (!el) return;

    window.theme1RefreshWishlistBadge = function () {
      var count = 0;
      try {
        var list = JSON.parse(localStorage.getItem(key) || "[]");
        if (Array.isArray(list)) count = list.length;
      } catch (_e) {}
      el.textContent = String(count);
    };

    window.theme1RefreshWishlistBadge();
    window.addEventListener("storage", function (e) {
      if (!e || e.key === key) window.theme1RefreshWishlistBadge();
    });
    window.addEventListener("theme1:wishlist-updated", window.theme1RefreshWishlistBadge);
  })();
</script>
