<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$user = auth_user($pdo);
$allProducts = products_fetch_all($pdo);

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
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('product-list.php');

$categories = [];
$brands = ['Luxe', 'Nike', 'Adidas', 'Gucci', 'Prada', 'Zara']; // Mock brands for filter
$colors = ['#000000', '#ffffff', '#ef4444', '#3b82f6', '#22c55e', '#eab308', '#a855f7'];

foreach ($allProducts as $product) {
    $cat = trim(strtolower((string) ($product['category'] ?? '')));
    if ($cat === '') {
        $cat = 'uncategorized';
    }
    $label = ucwords(str_replace(['-', '_'], ' ', $cat));
    $categories[$label] = ($categories[$label] ?? 0) + 1;
}

$theme1HeaderCategories = array_keys($categories);

// Search & Filter Logic
$searchQuery = trim((string)($_GET['q'] ?? ''));
$searchCategory = trim((string)($_GET['category_search'] ?? '')); // Renamed to avoid collision with sidebar
$selectedColor = trim((string)($_GET['color'] ?? ''));
$selectedCategories = (array)($_GET['category'] ?? []);
$selectedBrands = (array)($_GET['brand'] ?? []);
$selectedPrices = (array)($_GET['price'] ?? []);
$sortBy = trim((string)($_GET['sort'] ?? 'popular'));

if ($searchQuery !== '' || $searchCategory !== '' || $selectedColor !== '' || !empty($selectedCategories) || !empty($selectedBrands) || !empty($selectedPrices)) {
    $filteredProducts = [];
    foreach ($allProducts as $product) {
        $matchesSearch = true;
        $matchesCategorySearch = true;
        $matchesColor = true;
        $matchesCategory = true;
        $matchesBrand = true;
        $matchesPrice = true;
        
        if ($searchQuery !== '') {
            $name = strtolower((string)($product['name'] ?? ''));
            $desc = strtolower((string)($product['description'] ?? ''));
            $q = strtolower($searchQuery);
            if (!str_contains($name, $q) && !str_contains($desc, $q)) {
                $matchesSearch = false;
            }
        }
        
        if ($searchCategory !== '') {
            $cat = strtolower((string)($product['category'] ?? ''));
            if ($cat !== strtolower($searchCategory)) {
                $matchesCategorySearch = false;
            }
        }

        if ($selectedColor !== '') {
            $pColor = trim((string)($product['primary_color'] ?? ''));
            if (strtolower($pColor) !== strtolower($selectedColor)) {
                $matchesColor = false;
            }
        }

        if (!empty($selectedCategories)) {
            $cat = ucwords(str_replace(['-', '_'], ' ', strtolower((string)($product['category'] ?? ''))));
            if (!in_array($cat, $selectedCategories)) {
                $matchesCategory = false;
            }
        }

        if (!empty($selectedBrands)) {
            $brand = trim((string)($product['brand'] ?? ''));
            if (!in_array($brand, $selectedBrands)) {
                $matchesBrand = false;
            }
        }

        if (!empty($selectedPrices)) {
            $price = (float)($product['price'] ?? 0);
            $priceMatched = false;
            foreach ($selectedPrices as $range) {
                if ($range === '0-1000' && $price <= 1000) $priceMatched = true;
                elseif ($range === '1000-5000' && $price > 1000 && $price <= 5000) $priceMatched = true;
                elseif ($range === '5000-10000' && $price > 5000 && $price <= 10000) $priceMatched = true;
                elseif ($range === '10000+' && $price > 10000) $priceMatched = true;
                if ($priceMatched) break;
            }
            if (!$priceMatched) $matchesPrice = false;
        }
        
        if ($matchesSearch && $matchesCategorySearch && $matchesColor && $matchesCategory && $matchesBrand && $matchesPrice) {
            $filteredProducts[] = $product;
        }
    }
    $allProducts = $filteredProducts;
}

// Apply Sorting
usort($allProducts, function($a, $b) use ($sortBy) {
    if ($sortBy === 'price-low') {
        return (float)($a['price'] ?? 0) <=> (float)($b['price'] ?? 0);
    } elseif ($sortBy === 'price-high') {
        return (float)($b['price'] ?? 0) <=> (float)($a['price'] ?? 0);
    } elseif ($sortBy === 'new') {
        return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
    } elseif ($sortBy === 'popular') {
        // Sort by review count descending
        return (int)($b['reviews'] ?? 0) <=> (int)($a['reviews'] ?? 0);
    }
    return 0;
});

$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = $cartCount;

/**
 * @param array<string,mixed> $p
 */
function theme1_price(array $p): string
{
    return '₹' . number_format((float) ($p['price'] ?? 0), 2);
}

/**
 * @param array<string,mixed> $p
 */
function theme1_old_price(array $p): string
{
    $price = (float) ($p['price'] ?? 0);
    $old = (float) ($p['original'] ?? 0);
    if ($old <= 0 || $old <= $price) {
        return '';
    }
    return '₹' . number_format($old, 2);
}

/**
 * @param array<string,mixed> $p
 */
function theme1_url(array $p): string
{
    return luxe_product_url((int) ($p['id'] ?? 0), (string) ($p['slug'] ?? ''));
}

/**
 * @param array<string,mixed> $p
 */
function theme1_thumb_url(array $p): string
{
    $path = trim((string) ($p['image_path'] ?? ''));
    if ($path === '' || strcasecmp($path, 'default') === 0) {
        return '';
    }
    if (!preg_match('#^(?:https?:)?//#i', $path) && !str_starts_with($path, '/')) {
        $path = luxe_public_href(ltrim($path, '/'));
    }
    return $path;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products | LUXE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body>
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main class="t1-plp-page">
    <div class="container">
      
      <div class="t1-plp-header">
        <h1 class="t1-plp-title">All Products</h1>
        <div class="t1-plp-breadcrumb">
          <a href="index.php">Home</a>
          <span aria-hidden="true">/</span>
          <span>Products</span>
        </div>
      </div>

      <div class="t1-plp-layout">
        
        <!-- Sidebar Filters -->
        <div class="t1-plp-sidebar-overlay" id="plpSidebarOverlay">
          <aside class="t1-plp-sidebar" id="plpSidebar">
            <div class="t1-plp-sidebar-header">
              <h2 class="t1-plp-sidebar-title">Filters</h2>
              <div class="t1-plp-sidebar-actions">
                <?php if ($selectedColor !== '' || !empty($selectedCategories) || !empty($selectedBrands) || !empty($selectedPrices)): ?>
                  <a href="product-list.php" class="t1-plp-clear-all">Clear All</a>
                <?php endif; ?>
                <button type="button" class="close-sidebar-btn mobile-only" id="closePlpSidebarBtn" aria-label="Close filters">✕</button>
              </div>
            </div>
          <div class="t1-plp-filter-group">
            <h3 class="t1-plp-filter-title">Categories</h3>
            <?php foreach ($categories as $catName => $count): ?>
              <label class="t1-plp-checkbox">
                <input type="checkbox" name="category[]" value="<?= h($catName) ?>">
                <span><?= h($catName) ?> (<?= $count ?>)</span>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="t1-plp-filter-group">
            <h3 class="t1-plp-filter-title">Brands</h3>
            <?php foreach ($brands as $brand): ?>
              <label class="t1-plp-checkbox">
                <input type="checkbox" name="brand[]" value="<?= h($brand) ?>">
                <span><?= h($brand) ?></span>
              </label>
            <?php endforeach; ?>
          </div>

          <div class="t1-plp-filter-group">
            <h3 class="t1-plp-filter-title">Price Range</h3>
            <label class="t1-plp-checkbox"><input type="checkbox" name="price[]" value="0-1000"> Under ₹1,000</label>
            <label class="t1-plp-checkbox"><input type="checkbox" name="price[]" value="1000-5000"> ₹1,000 - ₹5,000</label>
            <label class="t1-plp-checkbox"><input type="checkbox" name="price[]" value="5000-10000"> ₹5,000 - ₹10,000</label>
            <label class="t1-plp-checkbox"><input type="checkbox" name="price[]" value="10000+"> Over ₹10,000</label>
          </div>

          <div class="t1-plp-filter-group">
            <h3 class="t1-plp-filter-title">Colors</h3>
            <div class="t1-plp-color-swatches">
              <?php foreach ($colors as $colorHex): ?>
                <div 
                  class="t1-plp-color-swatch <?= strtolower($selectedColor) === strtolower($colorHex) ? 'active' : '' ?>" 
                  style="background-color: <?= $colorHex ?>;" 
                  title="Color <?= $colorHex ?>"
                  data-color="<?= h($colorHex) ?>"
                ></div>
              <?php endforeach; ?>
              <?php if ($selectedColor !== ''): ?>
                <?php 
                  $clearColorUrl = $_GET;
                  unset($clearColorUrl['color']);
                  $clearColorUrl = 'product-list.php?' . http_build_query($clearColorUrl);
                ?>
                <a href="<?= h($clearColorUrl) ?>" class="t1-plp-clear-color" title="Clear Color">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </a>
              <?php endif; ?>
            </div>
          </div>
          </aside>
        </div>

        <!-- Main Product Area -->
        <div class="t1-plp-main">
          
          <div class="t1-plp-toolbar">
            <div class="t1-plp-results-count">
              Showing <span>1–<?= count($allProducts) ?></span> of <span><?= count($allProducts) ?></span> results
            </div>
            
            <div class="t1-plp-toolbar-actions">
              <button type="button" class="t1-plp-filter-toggle mobile-only" id="openPlpSidebarBtn" aria-label="Open Filters">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filters
              </button>
              
              <div class="t1-plp-sort">
                <select aria-label="Sort by" id="plpSortSelect">
                  <option value="popular" <?= $sortBy === 'popular' ? 'selected' : '' ?>>Sort by Popularity</option>
                  <option value="new" <?= $sortBy === 'new' ? 'selected' : '' ?>>Sort by Latest</option>
                  <option value="price-low" <?= $sortBy === 'price-low' ? 'selected' : '' ?>>Price: Low to High</option>
                  <option value="price-high" <?= $sortBy === 'price-high' ? 'selected' : '' ?>>Price: High to Low</option>
                </select>
              </div>
              
              <div class="t1-plp-view-toggles">
                <button type="button" class="t1-plp-view-btn active" id="viewGrid" aria-label="Grid View">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </button>
                <button type="button" class="t1-plp-view-btn" id="viewList" aria-label="List View">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                </button>
              </div>
            </div>
          </div>

          <div class="t1-plp-grid" id="productGrid">
            <?php if (count($allProducts) === 0): ?>
              <div style="grid-column: 1 / -1; padding: 60px 20px; text-align: center; color: #64748b; background: #fff; border-radius: 16px; border: 1px dashed #cbd5e1;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 16px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <h3 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 8px;">No Products Found</h3>
                <p style="margin: 0;">We couldn't find any products matching your search criteria. Try using different keywords or removing filters.</p>
                <a href="product-list.php" class="btn-hero btn-hero-sm" style="margin-top: 24px; display: inline-flex;">Clear Search</a>
              </div>
            <?php else: ?>
              <?php foreach ($allProducts as $product): ?>
                <?php $thumb = theme1_thumb_url($product); ?>
                <?php
                  $pcardRating = (float) ($product['rating'] ?? 0);
                  $pcardSavePct = luxe_pcard_save_percent($product);
                ?>
                <article class="pcard pcard--v3">
                  <div class="pcard__image-wrap">
                    <a href="<?= h(theme1_url($product)) ?>" class="pcard__img-link">
                      <div class="pcard__img" role="img" aria-label="<?= h((string)($product['name'] ?? 'Product')) ?>" style="background-image:url('<?= h($thumb) ?>');"></div>
                    </a>
                    <?php if (!empty($product['badge'])): ?>
                      <?php $badgeType = strtolower(trim((string)$product['badge'])); ?>
                      <span class="pcard__off-badge pcard__off-badge--<?= h($badgeType) ?>"><?= h(strtoupper((string) $product['badge'])) ?></span>
                    <?php elseif ($pcardSavePct !== null): ?>
                      <span class="pcard__off-badge"><?= $pcardSavePct ?>% OFF</span>
                    <?php endif; ?>
                    <div class="pcard__side-actions">
                      <button
                        type="button"
                        class="pcard__side-btn pcard__wish-toggle"
                        aria-label="Toggle wishlist"
                        data-wishlist-btn="1"
                        data-id="<?= (int) ($product['id'] ?? 0) ?>"
                        data-name="<?= h((string) ($product['name'] ?? 'Product')) ?>"
                        data-emoji="<?= h((string) ($product['emoji'] ?? '🛍')) ?>"
                        data-price="<?= (int) ($product['price'] ?? 0) ?>"
                        data-orig="<?= (int) ($product['original'] ?? 0) ?>"
                        data-image="<?= h($thumb) ?>"
                      ><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button>
                      <a href="<?= h(theme1_url($product)) ?>" class="pcard__side-btn" aria-label="Quick view">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                      </a>
                    </div>
                    <div class="pcard__cart-overlay">
                      <a href="<?= h(theme1_url($product)) ?>" class="pcard__cart-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                        Add To Cart
                      </a>
                    </div>
                  </div>
                  <div class="pcard__body">
                    <div class="pcard__rating">
                      <?= luxe_pcard_stars_html($pcardRating) ?>
                      <span class="pcard__reviews-count">(<?= (int) ($product['reviews'] ?? 0) ?>)</span>
                    </div>
                    <h3 class="pcard__title">
                      <a href="<?= h(theme1_url($product)) ?>"><?= h((string) ($product['name'] ?? 'Product')) ?></a>
                    </h3>
                    <div class="pcard__price-row">
                      <?php if (theme1_old_price($product) !== ''): ?><del class="pcard__price-old"><?= h(theme1_old_price($product)) ?></del><?php endif; ?>
                      <span class="pcard__price-current"><?= h(theme1_price($product)) ?></span>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
              <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>

  <script src="<?= h(luxe_theme_asset('js/wishlist.js')) ?>" defer></script>
  
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const viewGrid = document.getElementById('viewGrid');
      const viewList = document.getElementById('viewList');
      const productGrid = document.getElementById('productGrid');

      if(viewGrid && viewList && productGrid) {
        viewGrid.addEventListener('click', () => {
          viewGrid.classList.add('active');
          viewList.classList.remove('active');
          productGrid.classList.remove('list-view');
        });

        viewList.addEventListener('click', () => {
          viewList.classList.add('active');
          viewGrid.classList.remove('active');
          productGrid.classList.add('list-view');
        });
      }
      
      // Live Filtering with Page Reload
      const filterInputs = document.querySelectorAll('.t1-plp-sidebar input[type="checkbox"]');
      filterInputs.forEach(input => {
        // Set initial state from URL
        const url = new URL(window.location.href);
        const name = input.getAttribute('name');
        const values = url.searchParams.getAll(name);
        if (values.includes(input.value)) {
          input.checked = true;
        }

        input.addEventListener('change', () => {
          const url = new URL(window.location.href);
          const name = input.getAttribute('name');
          
          // Get all checked values for this name
          const checkedInputs = document.querySelectorAll(`.t1-plp-sidebar input[name="${name}"]:checked`);
          
          // Clear current values for this name
          url.searchParams.delete(name);
          
          // Add new values
          checkedInputs.forEach(checked => {
            url.searchParams.append(name, checked.value);
          });
          
          window.location.href = url.toString();
        });
      });
      
      // Color swatch selection
      const colorSwatches = document.querySelectorAll('.t1-plp-color-swatch');
      colorSwatches.forEach(swatch => {
        swatch.addEventListener('click', () => {
          const color = swatch.getAttribute('data-color');
          const url = new URL(window.location.href);
          if (swatch.classList.contains('active')) {
            url.searchParams.delete('color');
          } else {
            url.searchParams.set('color', color);
          }
          window.location.href = url.toString();
        });
      });
      
      // Sorting interaction
      const plpSortSelect = document.getElementById('plpSortSelect');
      if (plpSortSelect) {
        plpSortSelect.addEventListener('change', () => {
          const url = new URL(window.location.href);
          url.searchParams.set('sort', plpSortSelect.value);
          window.location.href = url.toString();
        });
      }
      
      // Mobile Filter Sidebar Toggle
      const openPlpSidebarBtn = document.getElementById('openPlpSidebarBtn');
      const closePlpSidebarBtn = document.getElementById('closePlpSidebarBtn');
      const plpSidebarOverlay = document.getElementById('plpSidebarOverlay');
      
      if (openPlpSidebarBtn && closePlpSidebarBtn && plpSidebarOverlay) {
        openPlpSidebarBtn.addEventListener('click', () => {
          plpSidebarOverlay.classList.add('active');
          document.body.style.overflow = 'hidden';
        });
        
        closePlpSidebarBtn.addEventListener('click', () => {
          plpSidebarOverlay.classList.remove('active');
          document.body.style.overflow = '';
        });
        
        plpSidebarOverlay.addEventListener('click', (e) => {
          if (e.target === plpSidebarOverlay) {
            plpSidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
          }
        });
      }
    });
  </script>
</body>
</html>
