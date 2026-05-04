<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/seller_product_catalog.php';
require_once __DIR__ . '/../includes/product_page_helpers.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$slug = trim((string) ($_GET['slug'] ?? ''));
$sellerSessionId = isset($_SESSION['seller_id']) ? (int) $_SESSION['seller_id'] : 0;
$adminSessionId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
$product = null;
if ($slug !== '') {
    $product = products_fetch_by_slug($pdo, $slug);
    if (!$product && $sellerSessionId > 0) {
        $product = products_fetch_by_slug($pdo, $slug, $sellerSessionId);
    }
}
if (!$product && $id > 0) {
    $product = products_fetch_by_id($pdo, $id);
    if (!$product && $sellerSessionId > 0) {
        $product = products_fetch_by_id($pdo, $id, $sellerSessionId);
    }
}
if (!$product && $id > 0 && $adminSessionId > 0) {
    $product = products_fetch_by_id_for_admin($pdo, $id);
}
if (!$product) {
    header('Location: index.php');
    exit;
}

$searchCatalogProducts = products_fetch_all($pdo);

$currentUserId = auth_user_id();
$currentUser = auth_user($pdo);

$reviewRowsSt = $pdo->prepare(
    'SELECT id, customer_name, rating, review_text, seller_response, created_at, seller_responded_at
     FROM product_reviews
     WHERE product_id = ? AND review_status = ?
     ORDER BY created_at DESC, id DESC
     LIMIT 50'
);
$reviewRowsSt->execute([(int) $product['id'], 'approved']);
$productReviews = $reviewRowsSt->fetchAll();

$totalProductReviews = count($productReviews);
$ratingSum = 0;
foreach ($productReviews as $reviewRow) {
    $ratingSum += max(1, min(5, (int) ($reviewRow['rating'] ?? 0)));
}
$calculatedAvgRating = $totalProductReviews > 0 ? round($ratingSum / $totalProductReviews, 1) : (float) ($product['rating'] ?? 0);
$displayReviewCount = $totalProductReviews > 0 ? $totalProductReviews : (int) ($product['reviews'] ?? 0);
$displayRating = $totalProductReviews > 0 ? $calculatedAvgRating : (float) ($product['rating'] ?? 0);

$soldUnitsSt = $pdo->prepare(
    'SELECT COALESCE(SUM(oi.qty), 0) AS units_sold
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE oi.product_id = ?
       AND LOWER(o.status) <> ?'
);
$soldUnitsSt->execute([(int) $product['id'], 'cancelled']);
$displaySoldLabel = product_format_units_sold_label((int) $soldUnitsSt->fetchColumn());

$variantRows = [];
$variantSt = $pdo->prepare('SELECT size_label, color_label, stock_qty FROM product_variant_inventory WHERE product_id = ? AND active = 1');
$variantSt->execute([(int) $product['id']]);
$variantRows = $variantSt->fetchAll(PDO::FETCH_ASSOC);

$variantStockMap = [];
foreach ($variantRows as $vr) {
    $sz = trim((string) ($vr['size_label'] ?? ''));
    $cl = trim((string) ($vr['color_label'] ?? ''));
    $qty = max(0, (int) ($vr['stock_qty'] ?? 0));
    $key = mb_strtolower($sz) . '|' . mb_strtolower($cl);
    $variantStockMap[$key] = $qty;
}
$hasVariantInventory = $variantRows !== [];

$csvSizes = product_parse_options_csv((string) ($product['size_options'] ?? ''));
$sizeOptions = $csvSizes; // Simplified for brevity

$csvColors = product_parse_options_csv((string) ($product['color_options'] ?? ''));
$colorOptions = $csvColors; // Simplified for brevity

/** @var array<string,array{url:string,product_id:int}> */
$colorLinkedProducts = [];
$styleGroupCode = trim((string) ($product['style_group_code'] ?? ''));
$sellerIdForColorLinks = (int) ($product['seller_id'] ?? 0);
if ($sellerIdForColorLinks > 0) {
    $selfId = (int) ($product['id'] ?? 0);
    if ($styleGroupCode !== '') {
        $siblingsSt = $pdo->prepare(
            "SELECT id, slug, primary_color, color_options
             FROM products
             WHERE seller_id = ?
               AND style_group_code = ?
               AND (id = ? OR (active = 1 AND approval_status = 'approved'))
             ORDER BY id ASC"
        );
        $siblingsSt->execute([$sellerIdForColorLinks, $styleGroupCode, $selfId]);
    } else {
        $siblingsSt = $pdo->prepare(
            "SELECT id, slug, primary_color, color_options
             FROM products
             WHERE seller_id = ?
               AND LOWER(TRIM(category)) = ?
               AND LOWER(TRIM(COALESCE(product_type, ''))) = ?
               AND LOWER(TRIM(name)) = ?
               AND (id = ? OR (active = 1 AND approval_status = 'approved'))
             ORDER BY id ASC"
        );
        $siblingsSt->execute([
            $sellerIdForColorLinks,
            strtolower(trim((string) ($product['category'] ?? ''))),
            strtolower(trim((string) ($product['product_type'] ?? ''))),
            strtolower(trim((string) ($product['name'] ?? ''))),
            $selfId,
        ]);
    }
    foreach ($siblingsSt->fetchAll(PDO::FETCH_ASSOC) as $sib) {
        $sid = (int) ($sib['id'] ?? 0);
        if ($sid <= 0) continue;
        
        $slugVal = trim((string) ($sib['slug'] ?? ''));
        $url = 'product.php?slug=' . rawurlencode($slugVal);
        $primary = trim((string) ($sib['primary_color'] ?? ''));
        if ($primary === '') {
            $csv = product_parse_options_csv((string) ($sib['color_options'] ?? ''));
            $primary = $csv[0] ?? '';
        }
        if ($primary === '') continue;
        
        $lk = mb_strtolower($primary);
        if (!isset($colorLinkedProducts[$lk])) {
            $colorLinkedProducts[$lk] = ['url' => $url, 'product_id' => $sid];
        }
        if (!in_array($primary, $colorOptions, true)) {
            $colorOptions[] = $primary;
        }
    }
    if ($colorOptions !== []) {
        usort($colorOptions, static function (string $a, string $b): int {
            return strnatcasecmp($a, $b);
        });
    }
}

$activeColorIdx = 0;
if ($colorOptions !== [] && $hasVariantInventory) {
    foreach ($colorOptions as $i => $col) {
        $ck = mb_strtolower(trim($col));
        foreach ($sizeOptions ?: [''] as $sz) {
            $k = mb_strtolower(trim($sz)) . '|' . $ck;
            if (($variantStockMap[$k] ?? 0) > 0) {
                $activeColorIdx = $i;
                break 2;
            }
        }
    }
}
$colorKeyForDefault = $colorOptions === [] ? '' : mb_strtolower(trim((string) ($colorOptions[$activeColorIdx] ?? '')));
$selectedColorDefault = $colorOptions !== [] ? (string) ($colorOptions[$activeColorIdx] ?? $colorOptions[0]) : 'Default';

$activeSizeIdx = 0;
if ($sizeOptions !== [] && $hasVariantInventory) {
    foreach ($sizeOptions as $i => $sz) {
        $k = mb_strtolower(trim($sz)) . '|' . $colorKeyForDefault;
        if (($variantStockMap[$k] ?? 0) > 0) {
            $activeSizeIdx = $i;
            break;
        }
    }
}

$initialSpecStockQty = (int) ($product['stock_qty'] ?? 0);
if ($hasVariantInventory) {
    $initSizeForSpec = $sizeOptions !== [] ? trim((string) ($sizeOptions[$activeSizeIdx] ?? '')) : '';
    $k = mb_strtolower(trim($initSizeForSpec)) . '|' . $colorKeyForDefault;
    if (isset($variantStockMap[$k])) $initialSpecStockQty = $variantStockMap[$k];
}

if ((int) ($product['active'] ?? 1) !== 1) {
    $variantStockMap = [];
    $hasVariantInventory = false;
    $initialSpecStockQty = 0;
    $product['stock_qty'] = 0;
}

$mfgSt = $pdo->prepare('SELECT manufacturer_generic_name, manufacturer_country, manufacturer_name_address FROM products WHERE id = ?');
$mfgSt->execute([(int)$product['id']]);
$mfgRow = $mfgSt->fetch(PDO::FETCH_ASSOC);

$mfgGenericName = trim((string)($mfgRow['manufacturer_generic_name'] ?? ''));
$mfgCountry = trim((string)($mfgRow['manufacturer_country'] ?? ''));
$mfgNameAddress = trim((string)($mfgRow['manufacturer_name_address'] ?? ''));

$related = products_fetch_related($pdo, (int) $product['id'], (string) $product['category'], 4);

$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $item) {
    $cartCount += (int) ($item['qty'] ?? 1);
}

$userName = trim((string) (($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')));
if ($userName === '') $userName = trim((string) ($currentUser['name'] ?? 'Guest User'));
$userInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $userName) ?: 'GU', 0, 2));
$userEmail = trim((string) ($currentUser['email'] ?? ''));
$isLoggedIn = $currentUser !== null;
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('product.php?slug=' . (string) ($product['slug'] ?? ''));

$theme1HeaderCategories = [];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = [];

function theme1_price(array $p): string { return '₹' . number_format((float) ($p['price'] ?? 0), 2); }
function theme1_old_price(array $p): string { 
    $price = (float) ($p['price'] ?? 0); $old = (float) ($p['original'] ?? 0);
    return ($old > $price) ? '₹' . number_format($old, 2) : '';
}
function theme1_url(array $p): string { return luxe_product_url((int) ($p['id'] ?? 0), (string) ($p['slug'] ?? '')); }
function theme1_thumb_url(array $p): string {
    $path = trim((string) ($p['image_path'] ?? ''));
    if ($path === '' || strcasecmp($path, 'default') === 0) return '';
    if (!preg_match('#^(?:https?:)?//#i', $path) && !str_starts_with($path, '/')) {
        return luxe_public_href(ltrim($path, '/'));
    }
    return $path;
}
function theme1_thumb_style(array $p): string {
    $path = theme1_thumb_url($p);
    return $path !== '' ? "background-image:url('" . h($path) . "');background-size:cover;background-position:center;" : '';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LUXE | <?= h((string)$product['name']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="t2-product-page">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <!-- Breadcrumb -->
    <div class="t1-breadcrumb container">
      <a href="index.php">Home</a> <span>/</span>
      <a href="product-list.php?category=<?= urlencode((string)$product['category']) ?>"><?= h(ucwords((string)$product['category'])) ?></a> <span>/</span>
      <span class="current"><?= h((string)$product['name']) ?></span>
    </div>

    <section class="t1-product-container container">
      <!-- Gallery Column -->
      <div class="t1-product-gallery">
        <?php 
          $images = $product['images'] ?? []; 
          if (empty($images)) $images[] = 'default';
        ?>
        <div class="t1-gallery-main">
          <?php $mainImgUrl = theme1_thumb_url(['image_path' => $images[0]]); ?>
          <img id="mainProductImage" src="<?= h($mainImgUrl) ?>" alt="<?= h((string)$product['name']) ?>" loading="lazy" />
          <?php if (!empty($product['badge'])): ?>
            <div class="t1-gallery-badge"><?= h((string)$product['badge']) ?></div>
          <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
        <div class="t1-gallery-thumbs">
          <?php foreach ($images as $idx => $img): $imgUrl = theme1_thumb_url(['image_path' => $img]); ?>
            <button class="t1-thumb-btn <?= $idx === 0 ? 'active' : '' ?>" onclick="switchProductImage(this, '<?= h($imgUrl) ?>')">
              <img src="<?= h($imgUrl) ?>" alt="Thumbnail <?= $idx+1 ?>" loading="lazy" />
            </button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Info Column -->
      <div class="t1-product-info">
        <div class="t1-product-glass-card t2-product-glass-card">
          <div class="t1-product-meta">
            <span class="t1-brand"><?= h((string)($product['brand'] ?? 'LUXE Exclusive')) ?></span>
            <div class="t1-rating">
              <span class="stars">★★★★<?= $displayRating < 5 ? '☆' : '★' ?></span>
              <span class="count"><?= h(number_format($displayRating, 1)) ?> (<?= h(number_format($displayReviewCount)) ?> reviews)</span>
            </div>
            <?php if ($displaySoldLabel !== ''): ?>
              <span class="t1-sold-badge">🔥 <?= h($displaySoldLabel) ?></span>
            <?php endif; ?>
          </div>

          <h1 class="t1-product-title"><?= h((string)$product['name']) ?></h1>
          <p class="t2-product-seller-line">
             Sold by <a class="t2-product-seller-link" href="seller-store.php?id=<?= (int)$product['seller_id'] ?>"><?= h((string)($product['seller_name'] ?? 'LUXE Store')) ?></a>
          </p>
          
          <!-- Flash Deal -->
          <div class="t1-flash-deal t2-flash-deal" role="status" aria-live="polite" aria-label="Flash deal countdown">
            <div class="t1-flash-deal-label">
              <span class="t1-flash-deal-icon" aria-hidden="true">⚡</span>
              <span class="t2-flash-deal-label-text">
                <span class="t2-flash-deal-kicker">Limited time</span>
                <span class="t2-flash-deal-line">Flash deal ends in</span>
              </span>
            </div>
            <div class="t1-flash-deal-timer" id="flashDealTimer">
              <span class="timer-unit">00</span>
              <span class="timer-sep" aria-hidden="true">:</span>
              <span class="timer-unit">00</span>
              <span class="timer-sep" aria-hidden="true">:</span>
              <span class="timer-unit">00</span>
            </div>
          </div>

          <div class="t1-product-price">
            <span class="current"><?= h(theme1_price((array)$product)) ?></span>
            <?php if (theme1_old_price((array)$product) !== ''): ?>
              <span class="old"><?= h(theme1_old_price((array)$product)) ?></span>
              <?php
                $orig = (float)($product['original'] ?? 0);
                $curr = (float)($product['price'] ?? 0);
                if ($orig > $curr && $orig > 0) {
                    $off = round((($orig - $curr) / $orig) * 100);
                    echo "<span class='discount-pill'>{$off}% OFF</span>";
                }
              ?>
            <?php endif; ?>
          </div>

          <!-- Color Swatches -->
          <?php if (!empty($colorOptions)): ?>
          <div class="t1-swatch-group">
            <label>Color: <strong id="selectedColorName"><?= h($selectedColorDefault) ?></strong></label>
            <div class="t1-swatches">
              <?php foreach ($colorOptions as $idx => $col): 
                $lk = mb_strtolower(trim($col));
                $isLinked = isset($colorLinkedProducts[$lk]) && $colorLinkedProducts[$lk]['product_id'] !== (int)$product['id'];
                if ($isLinked):
              ?>
                <a href="<?= h($colorLinkedProducts[$lk]['url']) ?>" class="t1-swatch-btn" 
                   style="background: <?= h(product_swatch_style_for_color($col, $idx)) ?>; display: inline-block;"
                   title="<?= h($col) ?>"></a>
              <?php else: ?>
                <button type="button" class="t1-swatch-btn <?= $idx === $activeColorIdx ? 'active' : '' ?>" 
                        style="background: <?= h(product_swatch_style_for_color($col, $idx)) ?>"
                        data-color="<?= h($col) ?>" onclick="selectProductColor(this)"></button>
              <?php endif; endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Size Swatches -->
          <?php if (!empty($sizeOptions)): ?>
          <div class="t1-swatch-group">
            <label>Size: <strong id="selectedSizeName"><?= h($sizeOptions[$activeSizeIdx] ?? '') ?></strong></label>
            <div class="t1-swatches size">
              <?php foreach ($sizeOptions as $idx => $sz): ?>
                <button type="button" class="t1-swatch-btn size-btn <?= $idx === $activeSizeIdx ? 'active' : '' ?>" 
                        data-size="<?= h($sz) ?>" onclick="selectProductSize(this)"><?= h($sz) ?></button>
              <?php endforeach; ?>
            </div>
            <div id="stockAvailability" class="t1-stock-text t2-product-stock-line"></div>
          </div>
          <?php endif; ?>

          <!-- Bank Offers -->
          <div class="t1-seller-offers t2-seller-offers">
            <div class="t1-offer-card">
              <div class="t1-offer-icon">💳</div>
              <div class="t1-offer-details">
                <span class="t1-offer-title">Extra 10% off with HDFC card</span>
                <span class="t1-offer-subtitle">Instant discount on orders above ₹5,000. T&C Apply.</span>
              </div>
            </div>
          </div>

          <!-- Add to Cart Block -->
          <div class="t1-product-actions">
            <div class="t1-qty-selector">
              <button type="button" onclick="changeQty(-1)">−</button>
              <input type="number" id="productQty" value="1" min="1" max="<?= $initialSpecStockQty > 0 ? $initialSpecStockQty : 1 ?>" readonly />
              <button type="button" onclick="changeQty(1)">+</button>
            </div>
            <button type="button" id="addToCartBtn" class="t1-add-to-cart-btn" onclick="addToCartEvent()" <?= $initialSpecStockQty <= 0 ? 'disabled' : '' ?>>
              <?= $initialSpecStockQty > 0 ? 'Add to Cart — ' . h(theme1_price((array)$product)) : 'Out of Stock' ?>
            </button>
            <button
              type="button"
              class="t1-action-icon-btn pcard__wish-toggle"
              aria-label="Toggle wishlist"
              data-wishlist-btn="1"
              data-id="<?= (int) ($product['id'] ?? 0) ?>"
              data-name="<?= h((string) ($product['name'] ?? 'Product')) ?>"
              data-emoji="<?= h((string) ($product['emoji'] ?? '🛍')) ?>"
              data-price="<?= (int) ($product['price'] ?? 0) ?>"
              data-orig="<?= (int) ($product['original'] ?? 0) ?>"
              data-image="<?= h(theme1_thumb_url(['image_path' => $product['images'][0] ?? ''])) ?>"
            ><svg class="heart-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></button>
            <button type="button" class="t1-action-icon-btn" aria-label="Share" onclick="openShareModal()">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
            </button>
          </div>
          
          <div class="t1-product-perks t2-product-perks" role="list" aria-label="Shopping benefits">
             <div class="t1-perk-item" role="listitem">
               <div class="t1-perk-icon" aria-hidden="true">
                 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
               </div>
               <span class="t1-perk-text">Free Delivery</span>
             </div>
             <div class="t1-perk-item" role="listitem">
               <div class="t1-perk-icon" aria-hidden="true">
                 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
               </div>
               <span class="t1-perk-text">30 Days Return</span>
             </div>
             <div class="t1-perk-item" role="listitem">
               <div class="t1-perk-icon" aria-hidden="true">
                 <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
               </div>
               <span class="t1-perk-text">Secure Payment</span>
             </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Tabs Section -->
    <section class="t1-product-tabs-section container">
      <div class="t1-tabs-header">
        <button class="t1-tab-btn active" onclick="switchTab('desc')">Description</button>
        <button class="t1-tab-btn" onclick="switchTab('spec')">Specifications</button>
        <button class="t1-tab-btn" onclick="switchTab('mfg')">Manufacturer</button>
        <button class="t1-tab-btn" onclick="switchTab('rev')">Reviews (<?= h(number_format($displayReviewCount)) ?>)</button>
      </div>
      <div class="t1-tabs-content-wrapper">
        <div id="tab-desc" class="t1-tab-content active">
          <div class="t1-desc-layout">
            <div class="t1-desc-text-col">
              <h3 class="t1-desc-title">About this product</h3>
              <div class="t1-desc-body">
                <?= luxe_sanitize_product_description_html((string)($product['description'] ?? '<p>No description provided.</p>')) ?>
              </div>
            </div>
            <div class="t1-desc-stats-col">
              <div class="t1-desc-highlights t2-desc-highlights" role="group" aria-label="Product highlights">
                <div class="t1-desc-highlight-card">
                  <strong><?= number_format($displayRating, 1) ?></strong>
                  <span>Rating</span>
                </div>
                <div class="t1-desc-highlight-card">
                  <strong><?= number_format($displayReviewCount) ?></strong>
                  <span>Reviews</span>
                </div>
                <div class="t1-desc-highlight-card">
                  <strong><?= h((string)($product['brand'] ?? 'LUXE')) ?></strong>
                  <span>Brand</span>
                </div>
                <div class="t1-desc-highlight-card">
                  <strong><?= h(ucwords((string)($product['category'] ?? 'General'))) ?></strong>
                  <span>Category</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div id="tab-spec" class="t1-tab-content">
          <table class="t1-spec-table">
            <tr><th>Brand</th><td><?= h((string)($product['brand'] ?? '-')) ?></td></tr>
            <tr><th>Model</th><td><?= h((string)($product['name'] ?? '-')) ?></td></tr>
            <tr><th>Category</th><td><?= h(ucwords((string)($product['category'] ?? '-'))) ?></td></tr>
            <tr><th>Product type</th><td><?= h(ucwords((string)($product['product_type'] ?? 'General'))) ?></td></tr>
            <tr><th>Shipping</th><td><?= h(ucwords(str_replace('_', ' ', (string)($product['shipping_class'] ?? 'Standard')))) ?></td></tr>
            <tr><th>Seller</th><td><a href="seller-store.php?id=<?= (int)$product['seller_id'] ?>"><?= h((string)($product['seller_name'] ?? 'LUXE Store')) ?></a></td></tr>
            <tr><th>Sizes Available</th><td><?= !empty($sizeOptions) ? h(implode(', ', $sizeOptions)) : '-' ?></td></tr>
            <tr><th>Colors Available</th><td><?= !empty($colorOptions) ? h(implode(', ', $colorOptions)) : '-' ?></td></tr>
            <tr><th>Stock</th><td id="specStockQty"><?= $initialSpecStockQty ?> units (selected variant)</td></tr>
            <tr><th>Total inventory</th><td><?= (int)($product['stock_qty'] ?? 0) ?> units (all sizes & colors)</td></tr>
            <tr><th>SKU Code</th><td><?= h((string)($product['sku'] ?? '-')) ?></td></tr>
          </table>
        </div>
        <div id="tab-mfg" class="t1-tab-content">
          <div class="t1-mfg-panel">
            <h4 class="t1-mfg-title">Manufacturer Details</h4>
            <div class="t1-mfg-grid">
              <strong class="t1-mfg-label">Generic Name:</strong>
              <span class="t1-mfg-value"><?= $mfgGenericName !== '' ? h($mfgGenericName) : h((string)($product['name'])) ?></span>
              <strong class="t1-mfg-label">Country of Origin:</strong>
              <span class="t1-mfg-value"><?= $mfgCountry !== '' ? h($mfgCountry) : 'India' ?></span>
              <strong class="t1-mfg-label">Manufacturer Name & Address:</strong>
              <span class="t1-mfg-value t1-mfg-value--multiline"><?= $mfgNameAddress !== '' ? nl2br(h($mfgNameAddress)) : 'Not provided by seller.' ?></span>
            </div>
            <p class="t1-mfg-footnote">For customer support related to this product, please contact the seller via the LUXE platform or refer to the manufacturer details above.</p>
          </div>
        </div>
        <div id="tab-rev" class="t1-tab-content">
          <div class="t1-rev-layout">
            <div class="t1-rev-summary-col">
              <div class="t1-rev-big-score"><?= number_format($displayRating, 1) ?></div>
              <div class="t1-rev-big-stars"><?= str_repeat('★', round($displayRating)) . str_repeat('☆', 5 - round($displayRating)) ?></div>
              <div class="t1-rev-based-on">Based on <?= $displayReviewCount ?> reviews</div>
              <div class="t1-rev-bars">
                <?php for($i=5; $i>=1; $i--): 
                   $pct = ($i == round($displayRating)) ? 100 : 0; 
                ?>
                <div class="t1-rev-bar-row">
                  <span class="t1-rev-bar-lbl"><?= $i ?>★</span>
                  <div class="t1-rev-bar-track"><div class="t1-rev-bar-fill" style="width: <?= $pct ?>%"></div></div>
                  <span class="t1-rev-bar-pct"><?= $pct ?>%</span>
                </div>
                <?php endfor; ?>
              </div>
            </div>
            
            <div class="t1-rev-list-col">
              <?php if (empty($productReviews)): ?>
                <p style="color:#64748b; padding:20px 0;">No reviews yet. Be the first to review this product!</p>
              <?php else: ?>
                <div class="t1-reviews-list">
                  <?php foreach ($productReviews as $rev): 
                    $rName = (string)($rev['customer_name'] ?? 'Customer');
                    $rInit = strtoupper(substr($rName, 0, 1));
                    $rRating = (int)($rev['rating'] ?? 5);
                  ?>
                    <div class="t1-review-item t2-review-item">
                      <div class="t1-review-head">
                        <div class="t1-review-avatar"><?= h($rInit) ?></div>
                        <div class="t1-review-author-info">
                          <strong><?= h($rName) ?></strong>
                          <span class="t1-review-verified">VERIFIED BUYER</span>
                        </div>
                        <div class="t1-review-badge-wrap">
                           <div class="stars"><?= str_repeat('★', $rRating) . str_repeat('☆', 5 - $rRating) ?></div>
                           <span class="t1-review-top-badge">TOP REVIEW</span>
                        </div>
                      </div>
                      <div class="t1-review-body">
                         <p><?= h((string)($rev['review_text'] ?? '')) ?></p>
                      </div>
                      <div class="t1-review-footer">
                        <span class="date"><?= date('Y-m-d H:i:s', strtotime((string)$rev['created_at'])) ?></span>
                        <button class="t1-helpful-btn">👍 Helpful (0)</button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
    <section class="block block--products container">
      <div class="block-head"><h2>Related Products</h2></div>
      <div class="product-grid four">
        <?php foreach ($related as $p): ?>
          <?php
            $pcardRating = (float) ($p['rating'] ?? 0);
            $pcardSavePct = luxe_pcard_save_percent((array) $p);
            $pcardCat = strtoupper(trim((string) ($p['category'] ?? 'General')));
          ?>
          <article class="pcard">
            <div class="pcard__media">
              <div class="pcard__toolbar">
                <div class="pcard__toolbar-left">
                  <div class="pcard__badges">
                    <?php if (!empty($p['badge'])): ?><span class="pcard__badge pcard__badge--new"><?= h((string) $p['badge']) ?></span><?php endif; ?>
                  </div>
                  <span class="pcard__category"><?= h($pcardCat) ?></span>
                </div>
                <button
                  type="button"
                  class="pcard__wish-toggle"
                  aria-label="Toggle wishlist"
                  data-wishlist-btn="1"
                  data-id="<?= (int) ($p['id'] ?? 0) ?>"
                  data-name="<?= h((string) ($p['name'] ?? 'Product')) ?>"
                  data-emoji="<?= h((string) ($p['emoji'] ?? '🛍')) ?>"
                  data-price="<?= (int) ($p['price'] ?? 0) ?>"
                  data-orig="<?= (int) ($p['original'] ?? 0) ?>"
                  data-image="<?= h(theme1_thumb_url((array)$p)) ?>"
                ><svg class="heart-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></button>
              </div>
              <div class="pcard__image-frame">
                <div class="thumb" role="img" aria-hidden="true" style="<?= h(theme1_thumb_style((array)$p)) ?>"></div>
                <div class="pcard__overlay">
                  <a href="<?= h(theme1_url((array)$p)) ?>" class="pcard__btn--buy">Buy Now</a>
                </div>
              </div>
            </div>
            <div class="pcard__body">
              <h3 class="pcard__title"><?= h((string) ($p['name'] ?? 'Product')) ?></h3>
              <div class="pcard__rating">
                <?= luxe_pcard_stars_html($pcardRating) ?>
                <span class="pcard__rating-num"><?= h(number_format($pcardRating, 1)) ?></span>
                <span class="pcard__reviews"><?= (int) ($p['reviews'] ?? 0) ?> reviews</span>
              </div>
              <div class="pcard__price-row">
                <div class="pcard__price-stack">
                  <span class="pcard__price-current"><?= h(theme1_price((array)$p)) ?></span>
                  <?php if (theme1_old_price((array)$p) !== ''): ?><del class="pcard__price-old"><?= h(theme1_old_price((array)$p)) ?></del><?php endif; ?>
                </div>
                <?php if ($pcardSavePct !== null): ?><span class="pcard__save-badge">Save <?= $pcardSavePct ?>%</span><?php endif; ?>
              </div>
              <div class="pcard__actions">
                <a class="pcard__btn pcard__btn--view" href="<?= h(theme1_url((array)$p)) ?>">View</a>
                <a class="pcard__btn pcard__btn--cart" href="<?= h(theme1_url((array)$p)) ?>">Add to Cart</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>

  <!-- Toast Notification System -->
  <div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:12px;"></div>

  <!-- Share Modal -->
  <div id="shareModal" class="t1-share-modal-overlay hidden" onclick="closeShareModal(event)">
    <div class="t1-share-modal-content">
      <button class="t1-share-close" onclick="closeShareModal()">&times;</button>
      <h3>Share this Product</h3>
      <div class="t1-share-options">
        <a href="#" id="shareWhatsapp" target="_blank" class="share-btn whatsapp">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.885-9.885 9.885m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
        </a>
        <a href="#" id="shareFacebook" target="_blank" class="share-btn facebook">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        </a>
        <a href="#" id="shareTwitter" target="_blank" class="share-btn twitter">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        </a>
        <a href="#" id="shareEmail" target="_blank" class="share-btn email">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
        </a>
      </div>
      <div class="t1-share-copy">
        <input type="text" id="shareUrlInput" readonly />
        <button onclick="copyShareLink()">Copy</button>
      </div>
    </div>
  </div>

  <script>
    const variantStock = <?= json_encode($variantStockMap) ?>;
    const basePrice = <?= (int)$product['price'] ?>;

    function switchProductImage(btn, url) {
      document.querySelectorAll('.t1-thumb-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('mainProductImage').src = url;
    }

    function selectProductColor(btn) {
      document.querySelectorAll('.t1-swatch-btn:not(.size-btn)').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const col = btn.getAttribute('data-color');
      document.getElementById('selectedColorName').textContent = col;
      updateStock();
    }

    function selectProductSize(btn) {
      document.querySelectorAll('.t1-swatch-btn.size-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const sz = btn.getAttribute('data-size');
      document.getElementById('selectedSizeName').textContent = sz;
      updateStock();
    }

    function updateStock() {
      const activeColBtn = document.querySelector('.t1-swatch-btn:not(.size-btn).active');
      const activeSizeBtn = document.querySelector('.t1-swatch-btn.size-btn.active');
      
      const col = activeColBtn ? activeColBtn.getAttribute('data-color').toLowerCase() : '';
      const sz = activeSizeBtn ? activeSizeBtn.getAttribute('data-size').toLowerCase() : '';
      
      const key = sz + '|' + col;
      const qtyInput = document.getElementById('productQty');
      const addBtn = document.getElementById('addToCartBtn');
      
      let stock = variantStock[key] !== undefined ? parseInt(variantStock[key], 10) : 0;
      // If no variants, fallback to product total stock
      if (Object.keys(variantStock).length === 0) {
          stock = <?= (int)$product['stock_qty'] ?>;
      }

      const stockDisplay = document.getElementById('stockAvailability');
      const specStock = document.getElementById('specStockQty');

      if (stock > 0) {
        qtyInput.max = stock;
        if (parseInt(qtyInput.value, 10) > stock) qtyInput.value = stock;
        addBtn.disabled = false;
        addBtn.textContent = 'Add to Cart — ₹' + (basePrice * parseInt(qtyInput.value, 10)).toLocaleString('en-IN');
        
        if (stockDisplay) {
           stockDisplay.textContent = 'Hurry! Only ' + stock + ' left in stock.';
           stockDisplay.style.color = stock < 5 ? '#e11d48' : '#10b981';
        }
        if (specStock) specStock.textContent = stock + ' units (selected variant)';
      } else {
        qtyInput.max = 0;
        qtyInput.value = 1;
        addBtn.disabled = true;
        addBtn.textContent = 'Out of Stock';
        
        if (stockDisplay) {
           stockDisplay.textContent = 'Currently out of stock.';
           stockDisplay.style.color = '#64748b';
        }
        if (specStock) specStock.textContent = '0 units (selected variant)';
      }
    }

    // Flash Deal Timer Logic
    function startFlashDealTimer() {
      const timerElement = document.getElementById('flashDealTimer');
      const units = timerElement.querySelectorAll('.timer-unit');
      
      // Mock timer: ends at the end of the current day
      function updateTimer() {
        const now = new Date();
        const endOfDay = new Date();
        endOfDay.setHours(23, 59, 59, 999);
        
        const diff = endOfDay - now;
        if (diff <= 0) {
          units[0].textContent = '00';
          units[1].textContent = '00';
          units[2].textContent = '00';
          return;
        }
        
        const h = Math.floor(diff / (1000 * 60 * 60));
        const m = Math.floor((diff / (1000 * 60)) % 60);
        const s = Math.floor((diff / 1000) % 60);
        
        units[0].textContent = String(h).padStart(2, '0');
        units[1].textContent = String(m).padStart(2, '0');
        units[2].textContent = String(s).padStart(2, '0');
      }
      
      updateTimer();
      setInterval(updateTimer, 1000);
    }

    // Initialize stock text on load
    window.addEventListener('DOMContentLoaded', () => {
      updateStock();
      startFlashDealTimer();
    });

    function changeQty(delta) {
      const input = document.getElementById('productQty');
      let val = parseInt(input.value, 10) || 1;
      const max = parseInt(input.max, 10) || 1;
      val += delta;
      if (val < 1) val = 1;
      if (val > max) val = max;
      input.value = val;
      updateStock();
    }

    function switchTab(tabId) {
      document.querySelectorAll('.t1-tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.t1-tab-content').forEach(c => c.classList.remove('active'));
      event.target.classList.add('active');
      document.getElementById('tab-' + tabId).classList.add('active');
    }

    function showToast(message) {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      toast.style.background = 'rgba(255, 255, 255, 0.9)';
      toast.style.backdropFilter = 'blur(12px)';
      toast.style.border = '1px solid rgba(255,255,255,0.4)';
      toast.style.padding = '14px 20px';
      toast.style.borderRadius = '12px';
      toast.style.boxShadow = '0 10px 25px -5px rgba(0,0,0,0.1)';
      toast.style.color = '#0f172a';
      toast.style.fontWeight = '600';
      toast.style.display = 'flex';
      toast.style.alignItems = 'center';
      toast.style.gap = '12px';
      toast.style.transform = 'translateY(100px)';
      toast.style.opacity = '0';
      toast.style.transition = 'all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
      
      toast.innerHTML = `<span style="background:#10b981;color:white;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;">✓</span> ${message}`;
      
      container.appendChild(toast);
      
      setTimeout(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
      }, 10);

      setTimeout(() => {
        toast.style.transform = 'translateY(20px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }

    async function addToCartEvent() {
      const activeColBtn = document.querySelector('.t1-swatch-btn:not(.size-btn).active');
      const activeSizeBtn = document.querySelector('.t1-swatch-btn.size-btn.active');
      const col = activeColBtn ? activeColBtn.getAttribute('data-color') : '';
      const sz = activeSizeBtn ? activeSizeBtn.getAttribute('data-size') : '';
      const qty = parseInt(document.getElementById('productQty').value, 10) || 1;
      const pid = <?= (int)$product['id'] ?>;

      const addBtn = document.getElementById('addToCartBtn');
      const origText = addBtn.textContent;
      addBtn.disabled = true;
      addBtn.textContent = 'Adding…';

      const fd = new FormData();
      fd.append('product_id', pid);
      fd.append('qty', qty);
      if (sz) fd.append('size', sz);
      if (col) fd.append('color', col);

      try {
        const res = await fetch(<?= json_encode(luxe_theme_cart_add_url(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>, { method: 'POST', body: fd });
        const data = await res.json();

        if (data.ok) {
          showToast('✓ Added to cart successfully!');

          // Update cart badge in theme-1 header (a[href="cart.php"] .badge-count)
          const cartBadge = document.querySelector('a[href="cart.php"] .badge-count');
          if (cartBadge) {
            cartBadge.textContent = data.cart_count;
          }
        } else {
          showToast('⚠ ' + (data.msg || 'Could not add to cart.'));
        }
      } catch (err) {
        console.error('Cart add error:', err);
        showToast('⚠ Network error. Please try again.');
      } finally {
        addBtn.disabled = false;
        addBtn.textContent = origText;
      }
    }

    // Share Modal Logic
    function openShareModal() {
      const url = encodeURIComponent(window.location.href);
      const title = encodeURIComponent("Check out this product on LUXE: " + document.title);
      
      document.getElementById('shareWhatsapp').href = `https://api.whatsapp.com/send?text=${title}%20${url}`;
      document.getElementById('shareFacebook').href = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
      document.getElementById('shareTwitter').href = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
      document.getElementById('shareEmail').href = `mailto:?subject=${title}&body=I found this amazing product: ${url}`;
      
      document.getElementById('shareUrlInput').value = window.location.href;
      
      const modal = document.getElementById('shareModal');
      modal.classList.remove('hidden');
    }

    function closeShareModal(e) {
      if (e && e.target !== e.currentTarget && e.target.id !== 'shareModal') return;
      document.getElementById('shareModal').classList.add('hidden');
    }

    function copyShareLink() {
      const input = document.getElementById('shareUrlInput');
      input.select();
      input.setSelectionRange(0, 99999);
      navigator.clipboard.writeText(input.value);
      showToast('Link copied to clipboard!');
    }

    // Wishlist Logic
    (function () {
      var wishlistKey = "luxe_profile_wishlist_v1";
      var getWishlist = function () {
        try {
          var list = JSON.parse(localStorage.getItem(wishlistKey) || "[]");
          return Array.isArray(list) ? list : [];
        } catch (_e) { return []; }
      };
      var setWishlist = function (items) {
        localStorage.setItem(wishlistKey, JSON.stringify(items));
        window.dispatchEvent(new Event("theme1:wishlist-updated"));
      };
      var updateBtnState = function (btn, active) {
        btn.classList.toggle("is-active", active);
        btn.setAttribute("aria-label", active ? "Remove from wishlist" : "Add to wishlist");
      };

      document.querySelectorAll("[data-wishlist-btn='1']").forEach(function (btn) {
        var id = parseInt(btn.getAttribute("data-id") || "0", 10);
        if (id <= 0) return;
        var current = getWishlist();
        updateBtnState(btn, current.some(function (x) { return Number(x.id) === id; }));
        btn.addEventListener("click", function () {
          var list = getWishlist();
          var idx = list.findIndex(function (x) { return Number(x.id) === id; });
          if (idx >= 0) {
            list.splice(idx, 1);
            setWishlist(list);
            updateBtnState(btn, false);
            showToast('Removed from Wishlist');
            return;
          }
          list.push({
            id: id,
            name: btn.getAttribute("data-name") || "Product",
            emoji: btn.getAttribute("data-emoji") || "🛍",
            price: Number(btn.getAttribute("data-price") || "0"),
            orig: Number(btn.getAttribute("data-orig") || "0"),
            image: btn.getAttribute("data-image") || ""
          });
          setWishlist(list);
          updateBtnState(btn, true);
          showToast('Added to Wishlist!');
        });
      });
    })();
  </script>
</body>
</html>
