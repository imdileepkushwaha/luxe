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
<?php require __DIR__ . '/page-loader.php'; ?>
<div class="header-promo-bar" role="region" aria-label="Announcements">
  <div class="container header-promo-inner">
    <p class="header-promo-text">
      Open Door To A Worlds Of Fashion
      <a href="product-list.php">Discover Now</a>
    </p>
  </div>
</div>
<header class="site-header">
  <div class="container header-inner">
    <div class="header-left">
      <a class="header-logo <?= $t2HeaderLogoUrl !== '' ? 'header-logo--has-img' : 'header-logo--text' ?>" href="index.php" aria-label="<?= h($t2HeaderBrand) ?> home">
        <?php if ($t2HeaderLogoUrl !== ''): ?>
          <img src="<?= h($t2HeaderLogoUrl) ?>" alt="<?= h($t2HeaderBrand) ?>" class="header-logo-img">
        <?php else: ?>
          <span class="logo-word"><?= h($t2HeaderBrand) ?></span>
        <?php endif; ?>
      </a>
    </div>

    <div class="header-center">
      <form class="header-search-v3" role="search" action="product-list.php" method="get">
        <div class="search-cat-v3">
          <select name="category_search" class="search-cat-select-v3">
            <option value="">All Categories</option>
            <?php foreach ($theme1HeaderCategories as $category): ?>
              <option value="<?= h(strtolower((string) $category)) ?>"><?= h((string) $category) ?></option>
            <?php endforeach; ?>
          </select>
          <svg class="cat-chev" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <span class="search-v3-divider"></span>
        <input type="search" class="search-input-v3" name="q" placeholder="Enter Search Products" autocomplete="off">
        <button type="submit" class="search-submit-v3" aria-label="Search">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
          </svg>
        </button>
      </form>
    </div>

    <div class="header-right">
      <?php if ($isLoggedIn): ?>
        <a href="profile.php" class="header-user-card">
          <div class="header-user-avatar"><?= h($userInitials) ?></div>
          <div class="header-user-info">
            <span class="header-user-name"><?= h($userName) ?></span>
            <span class="header-user-id"><?= h($userEmail) ?></span>
          </div>
        </a>
      <?php else: ?>
        <a href="<?= h($theme1LoginHref) ?>" class="header-login-btn">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <div class="login-text-wrap">
            <span class="login-text">Login / Register</span>
          </div>
        </a>
      <?php endif; ?>
      <button type="button" class="t3-app-header-btn" id="openSearchBtn" aria-label="Search">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4.3-4.3"/></svg>
      </button>
      <a href="cart.php" class="t3-app-header-btn t3-app-header-cart" aria-label="Cart">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <?php if ($theme1HeaderCartCount > 0): ?>
          <span class="t3-app-header-badge"><?= (int) $theme1HeaderCartCount ?></span>
        <?php endif; ?>
      </a>
      <button type="button" class="mobile-menu-toggle-v3" id="openDrawerBtn" aria-label="Open menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
    </div>
  </div>
</header>

<nav class="navbar-v3">
  <div class="container navbar-inner-v3">
    <div class="nav-v3-left">
      <div class="category-dropdown-v3">
        <button class="cat-toggle-v3">
          <svg class="icon-list" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          <span>Products Category</span>
          <svg class="icon-chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <div class="cat-menu-v3">
          <?php foreach ($theme1HeaderCategories as $category): ?>
            <a href="product-list.php?category_search=<?= h(strtolower((string) $category)) ?>"><?= h((string) $category) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="nav-v3-center">
      <ul class="nav-links-v3">
        <li><a href="index.php" class="<?= $theme1CurrentScript === 'index.php' ? 'active' : '' ?>">Home <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg></a></li>
        <li><a href="product-list.php" class="<?= in_array($theme1CurrentScript, ['product-list.php', 'product.php'], true) ? 'active' : '' ?>">Shop <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg></a></li>
        <li><a href="about-us.php" class="<?= $theme1CurrentScript === 'about-us.php' ? 'active' : '' ?>">Blog <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg></a></li>
        <li><a href="faq.php" class="<?= $theme1CurrentScript === 'faq.php' ? 'active' : '' ?>">Pages <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m6 9 6 6 6-6"/></svg></a></li>
        <li><a href="contact-us.php" class="<?= $theme1CurrentScript === 'contact-us.php' ? 'active' : '' ?>">Contact</a></li>
      </ul>
    </div>

    <div class="nav-v3-right">
      <div class="nav-action-item deal-badge">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.256 1.185-3.103.352.651.815 1.103 1.315 1.603z"/></svg>
        <span>Deal</span>
      </div>
      <span class="nav-v3-divider"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></span>
      <a href="profile.php?tab=wishlist" class="nav-action-item">
        <div class="action-icon-wrap">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          <span class="badge-v3" id="theme1WishlistCount">0</span>
        </div>
      </a>
      <span class="nav-v3-divider"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></span>
      <a href="cart.php" class="nav-action-item">
        <div class="action-icon-wrap">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>
          </svg>
          <span class="badge-v3"><?= $theme1HeaderCartCount ?></span>
        </div>
      </a>
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
      <a class="header-logo drawer-header-logo <?= $t2HeaderLogoUrl !== '' ? 'header-logo--has-img' : 'header-logo--text' ?>" href="index.php" aria-label="<?= h($t2HeaderBrand) ?> home">
        <?php if ($t2HeaderLogoUrl !== ''): ?>
          <img src="<?= h($t2HeaderLogoUrl) ?>" alt="<?= h($t2HeaderBrand) ?>" class="header-logo-img drawer-header-logo-img">
        <?php else: ?>
          <span class="logo-word-row">
            <span class="logo-word"><?= h($t2HeaderBrand) ?><span class="logo-dot" aria-hidden="true"></span></span>
            <span class="logo-tagline logo-tagline--stack"><span>Fashion</span><span>Store</span></span>
          </span>
        <?php endif; ?>
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
        <a href="profile.php" class="drawer-nav-link">Pages</a>
        <a href="index.php" class="drawer-nav-link">Blog</a>
        <a href="contact-us.php" class="drawer-nav-link<?= $theme1CurrentScript === 'contact-us.php' ? ' is-active' : '' ?>">Contact</a>
        <button type="button" class="drawer-nav-link drawer-nav-link--btn" id="drawerOpenSearchBtn">Search products</button>
        <a href="about-us.php" class="drawer-nav-link<?= $theme1CurrentScript === 'about-us.php' ? ' is-active' : '' ?>">About Us</a>
        <a href="faq.php" class="drawer-nav-link<?= $theme1CurrentScript === 'faq.php' ? ' is-active' : '' ?>">FAQ's</a>
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
            <a href="<?= h(luxe_action_href('logout.php?redirect=' . rawurlencode('index.php'))) ?>" class="drawer-tool-link drawer-tool-link--logout">
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
    
    var closeSearchBtn = document.getElementById('closeSearchBtn');
    var openSearchBtn = document.getElementById('openSearchBtn');
    var mobileSearchOverlay = document.getElementById('mobileSearchOverlay');
    var mobileSearchInput = document.getElementById('mobileSearchInput');
    function openMobileSearch() {
      if (!mobileSearchOverlay) return;
      mobileSearchOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
      if (mobileSearchInput) mobileSearchInput.focus();
    }
    function closeMobileSearch() {
      if (!mobileSearchOverlay) return;
      mobileSearchOverlay.classList.remove('active');
      document.body.style.overflow = '';
    }
    if (closeSearchBtn) {
      closeSearchBtn.addEventListener('click', closeMobileSearch);
    }
    if (openSearchBtn) {
      openSearchBtn.addEventListener('click', openMobileSearch);
    }

    var openDrawerBtn = document.getElementById('openDrawerBtn');
    var closeDrawerBtn = document.getElementById('closeDrawerBtn');
    var mobileDrawerOverlay = document.getElementById('mobileDrawerOverlay');
    var drawerOpenSearchBtn = document.getElementById('drawerOpenSearchBtn');

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

    if (drawerOpenSearchBtn && mobileSearchOverlay && mobileDrawerOverlay) {
      drawerOpenSearchBtn.addEventListener('click', function () {
        mobileDrawerOverlay.classList.remove('active');
        mobileSearchOverlay.classList.add('active');
        if (mobileSearchInput) mobileSearchInput.focus();
        document.body.style.overflow = 'hidden';
      });
    }

  })();
</script>
