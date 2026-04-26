<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/seller_product_catalog.php';

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

function product_parse_options_csv(string $csv): array
{
    $csv = str_replace(["\r\n", "\r", "\n", "\t", ';', '|', '،'], ',', $csv);
    $parts = array_map('trim', explode(',', $csv));
    $parts = array_values(array_filter($parts, static fn($v) => $v !== ''));
    return array_values(array_unique($parts));
}

function product_swatch_style(int $idx): string
{
    $palette = [
        'linear-gradient(135deg,#8b5cf6,#ec4899)',
        'linear-gradient(135deg,#1e40af,#0ea5e9)',
        'linear-gradient(135deg,#064e3b,#10b981)',
        'linear-gradient(135deg,#1c1c1c,#475569)',
        'linear-gradient(135deg,#7f1d1d,#ef4444)',
        'linear-gradient(135deg,#7c2d12,#f97316)',
    ];
    return $palette[$idx % count($palette)];
}

function product_swatch_style_for_color(string $colorName, int $idx): string
{
    $lower = mb_strtolower(trim($colorName));
    if ($lower === '' || $lower === 'default') {
        return 'linear-gradient(135deg,#8b5cf6,#ec4899)';
    }
    if (preg_match('/\b(white|off[\s-]?white|ivory|pearl|cream|snow|frost)\b/u', $lower)) {
        return 'linear-gradient(140deg,#ffffff 0%,#f1f5f9 50%,#e2e8f0 100%)';
    }
    if (preg_match('/\b(black|jet|charcoal|midnight)\b/u', $lower)) {
        return 'linear-gradient(135deg,#0f172a,#1e293b)';
    }
    if (preg_match('/\b(red|crimson|maroon|burgundy)\b/u', $lower)) {
        return 'linear-gradient(135deg,#991b1b,#ef4444)';
    }
    if (preg_match('/\b(blue|navy|indigo|azure)\b/u', $lower)) {
        return 'linear-gradient(135deg,#1e40af,#0ea5e9)';
    }
    if (preg_match('/\b(teal|cyan|aqua)\b/u', $lower)) {
        return 'linear-gradient(135deg,#0f766e,#22d3ee)';
    }
    if (preg_match('/\b(green|olive|forest|emerald|mint)\b/u', $lower)) {
        return 'linear-gradient(135deg,#064e3b,#10b981)';
    }
    if (preg_match('/\b(yellow|gold|mustard|amber)\b/u', $lower)) {
        return 'linear-gradient(135deg,#ca8a04,#fbbf24)';
    }
    if (preg_match('/\b(orange|coral|peach)\b/u', $lower)) {
        return 'linear-gradient(135deg,#c2410c,#fb923c)';
    }
    if (preg_match('/\b(pink|rose|magenta|purple|violet|lavender)\b/u', $lower)) {
        return 'linear-gradient(135deg,#8b5cf6,#ec4899)';
    }
    if (preg_match('/\b(gray|grey|silver)\b/u', $lower)) {
        return 'linear-gradient(135deg,#64748b,#94a3b8)';
    }
    if (preg_match('/\b(brown|tan|beige|khaki|camel)\b/u', $lower)) {
        return 'linear-gradient(135deg,#78350f,#d97706)';
    }

    return product_swatch_style($idx);
}

function product_format_units_sold_label(int $units): string
{
    if ($units <= 0) return '';
    if ($units >= 1_000_000) return number_format($units / 1_000_000, 1) . 'M+ sold';
    if ($units >= 1_000) return number_format($units / 1_000, 1) . 'K+ sold';
    if ($units >= 100) return number_format($units) . '+ sold';
    return $units . ' sold';
}

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
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('theme-1/product.php?slug=' . h((string)$product['slug']));

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
    if (!preg_match('#^(?:https?:)?//#i', $path) && !str_starts_with($path, '/')) return '../' . ltrim($path, '/');
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
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700&family=Jost:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <!-- Breadcrumb -->
    <div class="t1-breadcrumb container">
      <a href="index.php">Home</a> <span>/</span>
      <a href="../product-list.php?category=<?= urlencode((string)$product['category']) ?>"><?= h(ucwords((string)$product['category'])) ?></a> <span>/</span>
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
        <div class="t1-product-glass-card">
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
          <p style="font-size: 14px; color: #64748b; margin-top: -4px; margin-bottom: 16px;">
             Sold by <a href="seller-store.php?id=<?= (int)$product['seller_id'] ?>" style="color: #3b82f6; font-weight: 600; text-decoration: none;"><?= h((string)($product['seller_name'] ?? 'LUXE Store')) ?></a>
          </p>
          
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
            <div id="stockAvailability" class="t1-stock-text" style="margin-top: 8px; font-size: 13px; font-weight: 500;"></div>
          </div>
          <?php endif; ?>

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
            >♡</button>
            <button type="button" class="t1-action-icon-btn" aria-label="Share" onclick="openShareModal()">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
            </button>
          </div>
          
          <div class="t1-product-perks">
             <span>✓ Free Delivery</span>
             <span>✓ 30 Days Return</span>
             <span>✓ Secure Payment</span>
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
          <div class="t1-desc-highlights">
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
          <?= luxe_sanitize_product_description_html((string)($product['description'] ?? '<p>No description provided.</p>')) ?>
        </div>
        <div id="tab-spec" class="t1-tab-content">
          <table class="t1-spec-table">
            <tr><th>Brand</th><td><?= h((string)($product['brand'] ?? '-')) ?></td></tr>
            <tr><th>Model</th><td><?= h((string)($product['name'] ?? '-')) ?></td></tr>
            <tr><th>Category</th><td><?= h(ucwords((string)($product['category'] ?? '-'))) ?></td></tr>
            <tr><th>Product type</th><td><?= h(ucwords((string)($product['product_type'] ?? 'General'))) ?></td></tr>
            <tr><th>Shipping</th><td><?= h(ucwords(str_replace('_', ' ', (string)($product['shipping_class'] ?? 'Standard')))) ?></td></tr>
            <tr><th>Seller</th><td><a href="seller-store.php?id=<?= (int)$product['seller_id'] ?>" style="color: #3b82f6; font-weight: 500; text-decoration: none;"><?= h((string)($product['seller_name'] ?? 'LUXE Store')) ?></a></td></tr>
            <tr><th>Sizes Available</th><td><?= !empty($sizeOptions) ? h(implode(', ', $sizeOptions)) : '-' ?></td></tr>
            <tr><th>Colors Available</th><td><?= !empty($colorOptions) ? h(implode(', ', $colorOptions)) : '-' ?></td></tr>
            <tr><th>Stock</th><td id="specStockQty"><?= $initialSpecStockQty ?> units (selected variant)</td></tr>
            <tr><th>Total inventory</th><td><?= (int)($product['stock_qty'] ?? 0) ?> units (all sizes & colors)</td></tr>
            <tr><th>SKU Code</th><td><?= h((string)($product['sku'] ?? '-')) ?></td></tr>
          </table>
        </div>
        <div id="tab-mfg" class="t1-tab-content">
          <div style="padding: 24px; background: rgba(255,255,255,0.4); border-radius: 12px; border: 1px solid rgba(255,255,255,0.6);">
            <h4 style="margin:0 0 16px;font-size:18px;color:#0f172a;">Manufacturer Details</h4>
            
            <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;margin-bottom:16px;">
              <strong style="color:#475569;">Generic Name:</strong>
              <span style="color:#0f172a;"><?= $mfgGenericName !== '' ? h($mfgGenericName) : h((string)($product['name'])) ?></span>
              
              <strong style="color:#475569;">Country of Origin:</strong>
              <span style="color:#0f172a;"><?= $mfgCountry !== '' ? h($mfgCountry) : 'India' ?></span>
              
              <strong style="color:#475569;">Manufacturer Name & Address:</strong>
              <span style="color:#0f172a; line-height: 1.5;"><?= $mfgNameAddress !== '' ? nl2br(h($mfgNameAddress)) : 'Not provided by seller.' ?></span>
            </div>

            <p style="color:#64748b; font-size: 13px; margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(0,0,0,0.05);">For customer support related to this product, please contact the seller via the LUXE platform or refer to the manufacturer details above.</p>
          </div>
        </div>
        <div id="tab-rev" class="t1-tab-content">
          <?php if (empty($productReviews)): ?>
            <p style="color:#64748b; padding:20px 0;">No reviews yet. Be the first to review this product!</p>
          <?php else: ?>
            <div class="t1-reviews-list">
              <?php foreach ($productReviews as $rev): ?>
                <div class="t1-review-item">
                  <div class="t1-review-head">
                    <strong><?= h((string)($rev['customer_name'] ?? 'Customer')) ?></strong>
                    <span class="stars">★★★★★</span> <!-- Simplified for demo -->
                    <span class="date"><?= date('M j, Y', strtotime((string)$rev['created_at'])) ?></span>
                  </div>
                  <p class="t1-review-body"><?= h((string)($rev['review_text'] ?? '')) ?></p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Related Products -->
    <?php if (!empty($related)): ?>
    <section class="block block--products container">
      <div class="block-head"><h2>Related Products</h2></div>
      <div class="product-grid four">
        <?php foreach ($related as $p): ?>
          <article class="pcard">
            <div class="pcard__media">
              <div class="pcard__badges">
                <?php if (!empty($p['badge'])): ?><span class="pcard__badge pcard__badge--new"><?= h((string) $p['badge']) ?></span><?php endif; ?>
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
              >♡</button>
              <div class="thumb" role="img" aria-hidden="true" style="<?= h(theme1_thumb_style((array)$p)) ?>"></div>
              <div class="pcard__overlay">
                <a href="<?= h(theme1_url((array)$p)) ?>" class="pcard__btn--buy">Buy Now</a>
              </div>
            </div>
            <div class="pcard__body">
              <h3 class="pcard__title"><?= h((string) ($p['name'] ?? 'Product')) ?></h3>
              <div class="pcard__price">
                <span class="pcard__price-current"><?= h(theme1_price((array)$p)) ?></span>
                <?php if (theme1_old_price((array)$p) !== ''): ?><del class="pcard__price-old"><?= h(theme1_old_price((array)$p)) ?></del><?php endif; ?>
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

    // Initialize stock text on load
    window.addEventListener('DOMContentLoaded', updateStock);

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
      const qty = document.getElementById('productQty').value;
      const pid = <?= (int)$product['id'] ?>;

      const fd = new FormData();
      fd.append('action', 'add');
      fd.append('product_id', pid);
      fd.append('qty', qty);
      if (sz) fd.append('size', sz);
      if (col) fd.append('color', col);

      try {
        const res = await fetch('../cart.php', { method: 'POST', body: fd });
        if (res.ok) {
           showToast('Added to Cart Successfully!');
           // Update cart counter in header
           const countEl = document.querySelector('a[href="cart.php"] .badge-count');
           if (countEl) {
               countEl.textContent = parseInt(countEl.textContent || 0) + parseInt(qty);
               countEl.style.display = 'inline-block';
           }
        }
      } catch (err) {
        console.error(err);
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
        btn.textContent = active ? "♥" : "♡";
        btn.setAttribute("aria-label", active ? "Remove from wishlist" : "Add to wishlist");
        if(active) {
            btn.style.color = "#ec4899";
            btn.style.borderColor = "#ec4899";
            btn.style.background = "#fdf2f8";
        } else {
            btn.style.color = "#475569";
            btn.style.borderColor = "#e2e8f0";
            btn.style.background = "#ffffff";
        }
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
