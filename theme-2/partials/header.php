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
$theme1LoginHref = (string) ($theme1LoginHref ?? ('login.php?redirect=' . rawurlencode('index.php')));
$theme1CurrentScript = basename((string) ($_SERVER['PHP_SELF'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));

$t2HeaderBrand = 'LUXE';
$t2HeaderLogo = '';
if (function_exists('site_brand_name') && function_exists('db')) {
    try {
        $t2HeaderBrand = site_brand_name(db());
        $t2HeaderLogo = site_logo_path(db());
    } catch (Throwable $e) {
        $t2HeaderBrand = 'LUXE';
        $t2HeaderLogo = '';
    }
}
$t2HeaderLogoUrl = $t2HeaderLogo !== '' ? luxe_public_href(ltrim($t2HeaderLogo, '/')) : '';
?>
<header class="site-header">
  <div class="container header-inner">
    
    <!-- Mobile Hamburger -->
    <button type="button" class="mobile-menu-toggle" id="openDrawerBtn" aria-label="Open menu">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
    </button>

    <a class="header-logo" href="index.php" aria-label="<?= h($t2HeaderBrand) ?> home">
      <?php if ($t2HeaderLogoUrl !== ''): ?>
        <img src="<?= h($t2HeaderLogoUrl) ?>" alt="<?= h($t2HeaderBrand) ?>" class="header-logo-img">
      <?php else: ?>
        <span class="logo-mark" aria-hidden="true"><span class="logo-stripe"></span><span class="logo-stripe"></span><span class="logo-stripe"></span></span>
        <span class="logo-word"><?= h($t2HeaderBrand) ?></span>
      <?php endif; ?>
    </a>
    
    <!-- Desktop Search -->
    <form class="header-search" role="search" action="product-list.php" method="get">
      <div class="search-cat">
        <label class="visually-hidden" for="header-cat">Category</label>
        <select id="header-cat" name="category_search" class="search-cat-select">
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

    <div class="header-actions-right">
      <!-- Mobile Search Icon -->
      <button type="button" class="mobile-search-toggle" id="openSearchBtn" aria-label="Search">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4.3-4.3"/></svg>
      </button>

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
        <a href="profile.php?tab=wishlist" class="tool-link" title="Wishlist">
          <svg class="heart-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
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
  </div>
</header>
<nav class="navbar">
<div class="container">
  <div class="subnav-strip">
    
    <a class="subnav-logo" href="index.php" aria-label="<?= h($t2HeaderBrand) ?> home">
      <?php if ($t2HeaderLogoUrl !== ''): ?>
        <img src="<?= h($t2HeaderLogoUrl) ?>" alt="<?= h($t2HeaderBrand) ?>" class="subnav-logo-img">
      <?php else: ?>
        <span class="logo-mark" aria-hidden="true"><span class="logo-stripe"></span><span class="logo-stripe"></span><span class="logo-stripe"></span></span>
        <span class="logo-word"><?= h($t2HeaderBrand) ?></span>
      <?php endif; ?>
    </a>
    <nav class="subnav-menu" aria-label="Main">
      <a href="index.php" class="<?= $theme1CurrentScript === 'index.php' ? 'is-active' : '' ?>">Home</a>
      <a href="index.php#trending">Shop <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="index.php">Vendor <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="index.php#deals">Flash Deals <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="profile.php">Pages <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="about-us.php" class="<?= $theme1CurrentScript === 'about-us.php' ? 'is-active' : '' ?>">About Us</a>
      <a href="faq.php" class="<?= $theme1CurrentScript === 'faq.php' ? 'is-active' : '' ?>">FAQ's</a>
      <a href="index.php">Blog <span class="nav-chev" aria-hidden="true"></span></a>
      <a href="contact-us.php" class="<?= $theme1CurrentScript === 'contact-us.php' ? 'is-active' : '' ?>">Contact</a>
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
            <a href="settings.php" role="menuitem">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
              <span>Settings</span>
            </a>
            <a href="<?= h(luxe_action_href('logout.php?redirect=' . rawurlencode('index.php'))) ?>" role="menuitem">
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

<!-- Mobile Full Screen Search Overlay -->
<div class="mobile-search-overlay" id="mobileSearchOverlay">
  <div class="mobile-search-container">
    <div class="search-header">
      <h3 class="search-title">Search LUXE</h3>
      <button type="button" class="close-search-btn" id="closeSearchBtn" aria-label="Close search">✕</button>
    </div>
    <form action="product-list.php" method="get" class="mobile-search-form">
      <div class="mobile-search-input-wrap">
        <svg class="search-icon-left" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4.3-4.3"/></svg>
        <input type="search" name="q" id="mobileSearchInput" placeholder="Search for products, brands..." required>
        <button type="submit" class="mobile-search-submit">Search</button>
      </div>
    </form>
    <div class="mobile-search-suggestions">
      <p class="suggestion-title">Popular Searches</p>
      <div class="suggestion-tags">
        <a href="index.php?q=sneakers">Sneakers</a>
        <a href="index.php?q=watches">Watches</a>
        <a href="index.php?q=jackets">Jackets</a>
        <a href="index.php?q=bags">Bags</a>
      </div>
    </div>
  </div>
</div>

<!-- Mobile Drawer -->
<div class="mobile-drawer-overlay" id="mobileDrawerOverlay">
  <div class="mobile-drawer" id="mobileDrawer">
    <div class="drawer-header">
      <a class="header-logo" href="index.php" aria-label="LUXE home">
        <span class="logo-mark" aria-hidden="true"><span class="logo-stripe"></span><span class="logo-stripe"></span><span class="logo-stripe"></span></span>
        <span class="logo-word">LUXE</span>
      </a>
      <button type="button" class="close-drawer-btn" id="closeDrawerBtn" aria-label="Close menu">✕</button>
    </div>
    <div class="drawer-body">
      <!-- Login / User -->
      <div class="drawer-user">
        <?php if ($isLoggedIn): ?>
          <a href="profile.php" class="drawer-user-card">
            <span class="user-avatar" aria-hidden="true"><?= h($userInitials) ?></span>
            <span class="user-meta">
              <span class="user-name"><?= h($userName) ?></span>
              <?php if ($userEmail !== ''): ?><span class="user-email"><?= h($userEmail) ?></span><?php endif; ?>
            </span>
          </a>
        <?php else: ?>
          <div class="drawer-login-prompt">
            <p>Experience premium shopping.</p>
            <a href="<?= h($theme1LoginHref) ?>" class="btn-hero btn-hero-sm drawer-login-btn">Login to LUXE</a>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Nav Links -->
      <nav class="drawer-nav">
        <a href="index.php" class="drawer-nav-link<?= $theme1CurrentScript === 'index.php' ? ' is-active' : '' ?>">Home</a>
        <a href="index.php#trending" class="drawer-nav-link">Shop</a>
        <a href="index.php" class="drawer-nav-link">Vendor</a>
        <a href="index.php#deals" class="drawer-nav-link">Flash Deals</a>
        <a href="profile.php" class="drawer-nav-link">Pages</a>
        <a href="about-us.php" class="drawer-nav-link<?= $theme1CurrentScript === 'about-us.php' ? ' is-active' : '' ?>">About Us</a>
        <a href="faq.php" class="drawer-nav-link<?= $theme1CurrentScript === 'faq.php' ? ' is-active' : '' ?>">FAQ's</a>
        <a href="index.php" class="drawer-nav-link">Blog</a>
        <a href="contact-us.php" class="drawer-nav-link<?= $theme1CurrentScript === 'contact-us.php' ? ' is-active' : '' ?>">Contact</a>
      </nav>

      <?php if ($isLoggedIn): ?>
        <div class="drawer-tools-section">
          <p class="drawer-tools-title">Account Actions</p>
          <div class="drawer-tools">
            <a href="orders.php" class="drawer-tool-link">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              <span>My Orders</span>
            </a>
            <a href="settings.php" class="drawer-tool-link">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
              <span>Settings</span>
            </a>
            <a href="<?= h(luxe_action_href('logout.php?redirect=' . rawurlencode('index.php'))) ?>" class="drawer-tool-link" style="color: #ef4444;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              <span>Logout</span>
            </a>
          </div>
        </div>
      <?php endif; ?>
      
      <!-- Subnav tools removed as per user request -->
    </div>
  </div>
</div>
<script>
  (function () {
    if (window.__theme1WishlistBadgeInit) return;
    window.__theme1WishlistBadgeInit = true;
    var key = "luxe_profile_wishlist_v1";
    var el = document.getElementById("theme1WishlistCount");
    var elMob = document.getElementById("theme1MobileWishlistCount");

    window.theme1RefreshWishlistBadge = function () {
      var count = 0;
      try {
        var list = JSON.parse(localStorage.getItem(key) || "[]");
        if (Array.isArray(list)) count = list.length;
      } catch (_e) {}
      if(el) el.textContent = String(count);
      if(elMob) elMob.textContent = String(count);
    };

    window.theme1RefreshWishlistBadge();
    window.addEventListener("storage", function (e) {
      if (!e || e.key === key) window.theme1RefreshWishlistBadge();
    });
    window.addEventListener("theme1:wishlist-updated", window.theme1RefreshWishlistBadge);
    
    // Mobile Interactions
    var openSearchBtn = document.getElementById('openSearchBtn');
    var closeSearchBtn = document.getElementById('closeSearchBtn');
    var mobileSearchOverlay = document.getElementById('mobileSearchOverlay');
    var mobileSearchInput = document.getElementById('mobileSearchInput');
    
    if (openSearchBtn && closeSearchBtn && mobileSearchOverlay) {
      openSearchBtn.addEventListener('click', function() {
        mobileSearchOverlay.classList.add('active');
        if (mobileSearchInput) mobileSearchInput.focus();
        document.body.style.overflow = 'hidden';
      });
      closeSearchBtn.addEventListener('click', function() {
        mobileSearchOverlay.classList.remove('active');
        document.body.style.overflow = '';
      });
    }

    var openDrawerBtn = document.getElementById('openDrawerBtn');
    var closeDrawerBtn = document.getElementById('closeDrawerBtn');
    var mobileDrawerOverlay = document.getElementById('mobileDrawerOverlay');
    
    if (openDrawerBtn && closeDrawerBtn && mobileDrawerOverlay) {
      openDrawerBtn.addEventListener('click', function() {
        mobileDrawerOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
      });
      closeDrawerBtn.addEventListener('click', function() {
        mobileDrawerOverlay.classList.remove('active');
        document.body.style.overflow = '';
      });
      mobileDrawerOverlay.addEventListener('click', function(e) {
        if(e.target === mobileDrawerOverlay) {
          mobileDrawerOverlay.classList.remove('active');
          document.body.style.overflow = '';
        }
      });
    }

  })();
</script>
