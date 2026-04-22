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
<body class="index-page product-list-page" data-page="product-list">

  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>

  <?php
  $header = [
      'user' => $user,
      'cart_count' => $cartNavCount,
      'top_text' => 'New arrivals every week',
      'top_highlight' => 'Free shipping above ₹999',
      'top_links' => [
          ['label' => "Today's Deals", 'href' => 'index.php#deals'],
          ['label' => 'Top Brands', 'href' => 'index.php#brands'],
      ],
      'menu_links' => [
          ['label' => 'Home', 'href' => 'index.php'],
          ['label' => 'Shop', 'href' => 'product-list.php'],
          ['label' => 'Collections', 'href' => 'index.php#collections'],
          ['label' => 'Trending', 'href' => 'index.php#trending'],
          ['label' => 'Deals', 'href' => 'index.php#deals'],
          ['label' => 'Brands', 'href' => 'index.php#brands'],
      ],
      'wishlist_href' => $user
          ? 'profile.php?tab=wishlist'
          : 'login.php?redirect=' . rawurlencode('profile.php?tab=wishlist'),
      'breadcrumb' => [
          'home_href' => 'index.php',
          'home_label' => 'Home',
          'title' => 'Shop Grid',
          'current' => 'Shop',
      ],
      'search_lead' => 'Search by product name, brand, or category — results update in the grid below.',
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

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
      <div class="product-list-page-inner">
        <aside class="product-filters" id="productFiltersPanel" aria-label="Product filters">
          <div class="product-filters__mobile-head">
            <span class="product-filters__mobile-title">Filters</span>
            <button type="button" class="product-filters__close-btn" id="productFiltersCloseBtn" aria-label="Close filters">✕</button>
          </div>
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
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="jeans_28_30">
                    <span>Jeans: 28 - 30</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="jeans_32_34">
                    <span>Jeans: 32 - 34</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="jeans_36_plus">
                    <span>Jeans: 36+</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="shoes_uk_6_7">
                    <span>Shoes: UK 6 - 7</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="shoes_uk_8_9">
                    <span>Shoes: UK 8 - 9</span>
                  </label>
                  <label class="product-filter-option">
                    <input type="checkbox" name="sizeFit" value="shoes_uk_10_plus">
                    <span>Shoes: UK 10+</span>
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
        <div class="product-list-layout">
          <div class="product-list-toolbar">
            <button type="button" class="product-filters-open-btn" id="productFiltersOpenBtn" aria-expanded="false" aria-controls="productFiltersPanel">
              <svg class="product-filters-open-btn__icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
              </svg>
              <span>Filters</span>
            </button>
          </div>
          <div class="products-grid" id="productsGrid"></div>
        </div>
      </div>
      <div class="product-list-filters-overlay" id="productFiltersOverlay" hidden aria-hidden="true"></div>
    </div>
  </section>

  <?php
  $footer = [
      'deals_href' => 'index.php#deals',
      'year' => '2026',
  ];
  require __DIR__ . '/includes/user_footer.php';
  ?>

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
