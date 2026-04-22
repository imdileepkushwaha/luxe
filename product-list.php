<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();
$products = products_fetch_all($pdo);
$user = auth_user($pdo);
$cartNavCount = 0;
foreach ($_SESSION['cart'] ?? [] as $ci) {
    $cartNavCount += (int) ($ci['qty'] ?? 1);
}

$catalogCategories = ['fashion', 'electronics', 'beauty', 'home'];
$catParam = strtolower(trim((string) ($_GET['category'] ?? '')));
$initialCategory = in_array($catParam, $catalogCategories, true) ? $catParam : 'all';
$pageTitles = [
    'all' => 'Shop all products',
    'fashion' => 'Fashion',
    'electronics' => 'Electronics',
    'beauty' => 'Beauty',
    'home' => 'Home & living',
];
$listHeading = $pageTitles[$initialCategory] ?? $pageTitles['all'];
$docTitle = $initialCategory === 'all'
    ? 'LUXE — Shop all products'
    : 'LUXE — ' . $listHeading;
$metaDesc = $initialCategory === 'all'
    ? 'Browse the full LUXE catalog — fashion, electronics, beauty, and home. Filter by category and add to cart.'
    : 'Browse ' . strtolower($listHeading) . ' on LUXE — curated listings from verified sellers.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title><?= h($docTitle) ?></title>
  <meta name="description" content="<?= h($metaDesc) ?>" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body data-page="product-list">

  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>

  <nav class="navbar" id="navbar">
    <div class="nav-container">
      <div class="nav-brand-cluster">
        <?php require __DIR__ . '/includes/nav_hamburger_btn.php'; ?>
        <a href="index.php" class="nav-logo">LUXE</a>
      </div>
      <ul class="nav-links">
        <li><a href="index.php">Home</a></li>
        <li><a href="product-list.php" aria-current="page">Shop</a></li>
        <li><a href="index.php#collections">Collections</a></li>
        <li><a href="index.php#deals">Deals</a></li>
      </ul>
      <div class="nav-actions">
        <button class="icon-btn" id="searchBtn" aria-label="Search">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </button>
        <?php
        $wishlistNavHref = $user
            ? 'profile.php?tab=wishlist'
            : 'login.php?redirect=' . rawurlencode('profile.php?tab=wishlist');
        ?>
        <a href="<?= h($wishlistNavHref) ?>" class="icon-btn" aria-label="Wishlist" data-nav-mobile="drawer">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </a>
        <?php if ($user): ?>
        <a href="actions/logout.php" class="nav-login-btn" aria-label="Sign out" data-nav-mobile="drawer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign Out
        </a>
        <?php else: ?>
        <a href="login.php" class="nav-login-btn" aria-label="Login" data-nav-mobile="drawer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Sign In
        </a>
        <?php endif; ?>
        <?php if ($user): ?>
        <a href="profile.php" class="icon-btn" aria-label="Profile" data-nav-mobile="drawer">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php endif; ?>
        <a href="cart.php" class="cart-btn" aria-label="Cart">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          <span class="cart-count" id="cartCount"><?= (int) $cartNavCount ?></span>
        </a>
      </div>
    </div>
  </nav>
  <?php require __DIR__ . '/includes/nav_drawer.php'; ?>

  <div class="search-overlay" id="searchOverlay" role="dialog" aria-modal="true" aria-labelledby="searchOverlayTitle">
    <div class="search-overlay__ambient" aria-hidden="true"></div>
    <button class="search-close" id="searchClose" type="button" aria-label="Close search">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
    <div class="search-inner">
      <p class="search-kicker">LUXE catalog</p>
      <h2 id="searchOverlayTitle" class="search-title">Find your next favorite</h2>
      <p class="search-lead">Search by product name, brand, or category — results update in the grid below.</p>
      <div class="search-panel">
        <label class="search-box" for="searchInput">
          <span class="search-box__icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          </span>
          <input type="search" id="searchInput" name="q" placeholder="Try Nike, watch, serum…" autocomplete="off" enterkeyhint="search" />
        </label>
        <p class="search-hint">
          <span class="search-hint__desktop"><kbd>Enter</kbd><span class="search-hint__text">Scrolls to results</span></span>
          <span class="search-hint__mobile">Submit search to scroll to results</span>
        </p>
        <div class="search-live-results" id="searchLiveResults" hidden aria-live="polite"></div>
      </div>
    </div>
  </div>

  <section class="trending product-list-catalog" id="trending">
    <div class="trending-bg" aria-hidden="true">
      <div class="trending-bg__blob trending-bg__blob--1"></div>
      <div class="trending-bg__blob trending-bg__blob--2"></div>
      <div class="trending-bg__grid"></div>
    </div>
    <div class="container trending-container">
      <div class="section-header">
        <div class="section-badge">Catalog</div>
        <h1 class="section-title"><?= h($listHeading) ?></h1>
        <p class="section-subtitle"><?= $initialCategory === 'all' ? 'Every approved listing in one place — switch tabs to focus a category.' : 'Products in this category from verified sellers. Use All to see the full catalog.' ?></p>
      </div>
      <div class="filter-tabs" role="tablist" aria-label="Filter products by category">
        <button type="button" class="filter-btn<?= $initialCategory === 'all' ? ' active' : '' ?>" data-filter="all" role="tab" aria-selected="<?= $initialCategory === 'all' ? 'true' : 'false' ?>">All</button>
        <button type="button" class="filter-btn<?= $initialCategory === 'fashion' ? ' active' : '' ?>" data-filter="fashion" role="tab" aria-selected="<?= $initialCategory === 'fashion' ? 'true' : 'false' ?>">Fashion</button>
        <button type="button" class="filter-btn<?= $initialCategory === 'electronics' ? ' active' : '' ?>" data-filter="electronics" role="tab" aria-selected="<?= $initialCategory === 'electronics' ? 'true' : 'false' ?>">Electronics</button>
        <button type="button" class="filter-btn<?= $initialCategory === 'beauty' ? ' active' : '' ?>" data-filter="beauty" role="tab" aria-selected="<?= $initialCategory === 'beauty' ? 'true' : 'false' ?>">Beauty</button>
        <button type="button" class="filter-btn<?= $initialCategory === 'home' ? ' active' : '' ?>" data-filter="home" role="tab" aria-selected="<?= $initialCategory === 'home' ? 'true' : 'false' ?>">Home</button>
      </div>
      <div class="product-list-layout">
        <aside class="product-filters" aria-label="Product filters">
          <div class="product-filters__card">
            <form id="productFiltersForm" class="product-filters__form" autocomplete="off">
              <details class="product-filter-group" open>
                <summary class="product-filter-group__summary">
                  <span>Categories</span>
                  <span class="product-filter-group__chevron" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" focusable="false" aria-hidden="true">
                      <path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                  </span>
                </summary>
                <div class="product-filter-group__panel">
                  <label class="product-filter-option">
                    <input type="checkbox" name="categories" value="fashion">
                    <span>Fashion</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="categories" value="electronics">
                    <span>Electronics</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="categories" value="beauty">
                    <span>Beauty</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="categories" value="home">
                    <span>Home &amp; living</span>
                  </label>
                </div>
              </details>

              <details class="product-filter-group">
                <summary class="product-filter-group__summary">
                  <span>Product Price</span>
                  <span class="product-filter-group__chevron" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" focusable="false" aria-hidden="true">
                      <path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                  </span>
                </summary>
                <div class="product-filter-group__panel">
                  <label class="product-filter-option">
                    <input type="radio" name="priceRange" value="" checked>
                    <span>All prices</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="radio" name="priceRange" value="0-999">
                    <span>Under ₹1,000</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="radio" name="priceRange" value="1000-2499">
                    <span>₹1,000 - ₹2,499</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="radio" name="priceRange" value="2500-4999">
                    <span>₹2,500 - ₹4,999</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="radio" name="priceRange" value="5000+">
                    <span>₹5,000 &amp; above</span>
                  </label>
                </div>
              </details>

              <details class="product-filter-group">
                <summary class="product-filter-group__summary">
                  <span>Gender</span>
                  <span class="product-filter-group__chevron" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" focusable="false" aria-hidden="true">
                      <path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                  </span>
                </summary>
                <div class="product-filter-group__panel">
                  <label class="product-filter-option">
                    <input type="checkbox" name="gender" value="men">
                    <span>Men</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="gender" value="women">
                    <span>Women</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="gender" value="unisex">
                    <span>Unisex</span>
                  </label>
                </div>
              </details>

              <details class="product-filter-group">
                <summary class="product-filter-group__summary">
                  <span>Size &amp; Fit</span>
                  <span class="product-filter-group__chevron" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" focusable="false" aria-hidden="true">
                      <path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                  </span>
                </summary>
                <div class="product-filter-group__panel">
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="small">
                    <span>Small</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="medium">
                    <span>Medium</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="large">
                    <span>Large</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="free">
                    <span>Free size</span>
                  </label>
                </div>
              </details>

              <details class="product-filter-group">
                <summary class="product-filter-group__summary">
                  <span>Rating</span>
                  <span class="product-filter-group__chevron" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" focusable="false" aria-hidden="true">
                      <path d="m6 8 4 4 4-4" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                  </span>
                </summary>
                <div class="product-filter-group__panel">
                  <label class="product-filter-option">
                    <input type="radio" name="rating" value="" checked>
                    <span>All ratings</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="radio" name="rating" value="4.5">
                    <span>4.5 ★ &amp; above</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="radio" name="rating" value="4">
                    <span>4 ★ &amp; above</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="radio" name="rating" value="3.5">
                    <span>3.5 ★ &amp; above</span>
                  </label>
                </div>
              </details>

              <button type="button" class="product-filters__apply" id="productFiltersApply">Apply</button>
            </form>
          </div>
        </aside>
        <div class="products-grid" id="productsGrid"></div>
      </div>
    </div>
  </section>

  <footer class="footer">
    <div class="container">
      <div class="footer-bottom" style="border-top: none; padding-top: 1.5rem;">
        <p>© 2026 LUXE. <a href="index.php" style="color: inherit;">Home</a> · <a href="product-list.php" style="color: inherit;">Shop</a></p>
      </div>
    </div>
  </footer>

  <div class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
      <h3>Your Cart <span id="cartItemCount">(0)</span></h3>
      <button class="cart-close" id="cartClose">✕</button>
    </div>
    <div class="cart-items" id="cartItems">
      <div class="cart-empty">
        <span class="cart-empty-icon">🛒</span>
        <p>Your cart is empty</p>
        <a href="#trending" class="btn-primary" id="shopNowCart">Start Shopping</a>
      </div>
    </div>
    <div class="cart-footer">
      <div class="cart-total">
        <span>Total</span><strong id="cartTotal">₹0</strong>
      </div>
      <button class="btn-primary full-width" id="checkoutBtn" onclick="goCheckout()">Checkout →</button>
    </div>
  </div>
  <div class="cart-overlay" id="cartOverlay"></div>
  <div class="toast" id="toast"></div>

  <script>
    window.LUXE_URLS = <?= json_encode([
        'home' => 'index.php',
        'login' => 'login.php',
        'cart' => 'cart.php',
        'product' => 'product.php',
        'orders' => 'orders.php',
        'profile' => 'profile.php',
        'productList' => 'product-list.php',
    ], JSON_THROW_ON_ERROR) ?>;
    window.__PRODUCTS__ = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__PRODUCT_LIST_INITIAL_CATEGORY__ = <?= json_encode($initialCategory, JSON_THROW_ON_ERROR) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
