<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 1;
$sellerSessionId = isset($_SESSION['seller_id']) ? (int) $_SESSION['seller_id'] : 0;
$adminSessionId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
$product = products_fetch_by_id($pdo, $id);
if (!$product && $sellerSessionId > 0) {
    $product = products_fetch_by_id($pdo, $id, $sellerSessionId);
}
if (!$product && $adminSessionId > 0) {
    $product = products_fetch_by_id_for_admin($pdo, $id);
}
if (!$product) {
    header('Location: index.php');
    exit;
}

function product_parse_options_csv(string $csv): array
{
    $parts = array_map('trim', explode(',', $csv));
    $parts = array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));
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

/** Swatch + main image backdrop gradient from color name (falls back to index palette). */
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

/** Human-readable "N sold" / "1.2K+ sold" from fulfilled order line qty (excludes cancelled orders). */
function product_format_units_sold_label(int $units): string
{
    if ($units <= 0) {
        return '';
    }
    if ($units >= 1_000_000) {
        $m = $units / 1_000_000;

        return ($m >= 10 ? number_format($m, 0) : number_format($m, 1)) . 'M+ sold';
    }
    if ($units >= 1_000) {
        $k = $units / 1_000;

        return ($k >= 10 ? number_format($k, 0) : number_format($k, 1)) . 'K+ sold';
    }
    if ($units >= 100) {
        return number_format($units) . '+ sold';
    }

    return (string) $units . ' sold';
}

$currentUserId = auth_user_id();
$currentUser = auth_user($pdo);
$reviewError = '';
$reviewSuccess = '';
$postedRating = 5;
$postedReviewText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'submit_product_review') {
    $reviewProductId = (int) ($_POST['product_id'] ?? 0);
    $postedRating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $postedReviewText = trim((string) ($_POST['review_text'] ?? ''));

    if ($reviewProductId !== (int) $product['id']) {
        $reviewError = 'Invalid product review request.';
    } elseif ($currentUserId === null || !$currentUser) {
        $reviewError = 'Please sign in to submit a review.';
    } elseif (strlen($postedReviewText) < 10) {
        $reviewError = 'Review must be at least 10 characters.';
    } else {
        if (strlen($postedReviewText) > 1000) {
            $postedReviewText = substr($postedReviewText, 0, 1000);
        }
        $customerName = trim((string) (($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')));
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        $existingSt = $pdo->prepare(
            'SELECT id
             FROM product_reviews
             WHERE product_id = ? AND user_id = ?
             LIMIT 1'
        );
        $existingSt->execute([(int) $product['id'], (int) $currentUserId]);
        $existingReviewId = (int) ($existingSt->fetchColumn() ?: 0);

        if ($existingReviewId > 0) {
            $upd = $pdo->prepare(
                'UPDATE product_reviews
                 SET customer_name = ?, rating = ?, review_text = ?,
                     review_status = ?, seller_response = \'\', seller_reviewed_at = NULL, seller_responded_at = NULL
                 WHERE id = ?
                 LIMIT 1'
            );
            $upd->execute([$customerName, $postedRating, $postedReviewText, 'pending', $existingReviewId]);
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO product_reviews (product_id, user_id, customer_name, rating, review_text, review_status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([(int) $product['id'], (int) $currentUserId, $customerName, $postedRating, $postedReviewText, 'pending']);
        }

        $aggSt = $pdo->prepare(
            'SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating), 0) AS avg_rating
             FROM product_reviews
             WHERE product_id = ? AND review_status = ?'
        );
        $aggSt->execute([(int) $product['id'], 'approved']);
        $agg = $aggSt->fetch() ?: ['total_reviews' => 0, 'avg_rating' => 0];
        $totalReviews = (int) ($agg['total_reviews'] ?? 0);
        $avgRating = (float) ($agg['avg_rating'] ?? 0.0);

        $updProduct = $pdo->prepare(
            'UPDATE products
             SET review_count = ?, rating = ?
             WHERE id = ?
             LIMIT 1'
        );
        $updProduct->execute([$totalReviews, $avgRating, (int) $product['id']]);

        header('Location: product.php?id=' . (int) $product['id'] . '&review_saved=1#tab-reviews');
        exit;
    }
}

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
$ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$ratingSum = 0;
foreach ($productReviews as $reviewRow) {
    $r = max(1, min(5, (int) ($reviewRow['rating'] ?? 0)));
    $ratingBreakdown[$r]++;
    $ratingSum += $r;
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

if (isset($_GET['review_saved']) && (string) $_GET['review_saved'] === '1') {
    $reviewSuccess = 'Review submitted. It will be visible after seller approval.';
}

$variantRows = [];
$variantSt = $pdo->prepare(
    'SELECT size_label, color_label, stock_qty
     FROM product_variant_inventory
     WHERE product_id = ? AND active = 1'
);
$variantSt->execute([(int) $product['id']]);
$variantRows = $variantSt->fetchAll(PDO::FETCH_ASSOC);

/** @var array<string,int> */
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
$mergedSizeSeen = [];
$sizeOptions = [];
foreach ($csvSizes as $s) {
    $t = trim((string) $s);
    if ($t === '') {
        continue;
    }
    $lk = mb_strtolower($t);
    if (!isset($mergedSizeSeen[$lk])) {
        $mergedSizeSeen[$lk] = true;
        $sizeOptions[] = $t;
    }
}
foreach ($variantRows as $vr) {
    $t = trim((string) ($vr['size_label'] ?? ''));
    if ($t === '') {
        continue;
    }
    $lk = mb_strtolower($t);
    if (!isset($mergedSizeSeen[$lk])) {
        $mergedSizeSeen[$lk] = true;
        $sizeOptions[] = $t;
    }
}
usort($sizeOptions, static function (string $a, string $b): int {
    return strnatcasecmp($a, $b);
});

$csvColors = product_parse_options_csv((string) ($product['color_options'] ?? ''));
$mergedColorSeen = [];
$colorOptions = [];
foreach ($csvColors as $c) {
    $t = trim((string) $c);
    if ($t === '') {
        continue;
    }
    $lk = mb_strtolower($t);
    if (!isset($mergedColorSeen[$lk])) {
        $mergedColorSeen[$lk] = true;
        $colorOptions[] = $t;
    }
}
foreach ($variantRows as $vr) {
    $t = trim((string) ($vr['color_label'] ?? ''));
    if ($t === '') {
        continue;
    }
    $lk = mb_strtolower($t);
    if (!isset($mergedColorSeen[$lk])) {
        $mergedColorSeen[$lk] = true;
        $colorOptions[] = $t;
    }
}
usort($colorOptions, static function (string $a, string $b): int {
    return strnatcasecmp($a, $b);
});

/** @var list<string> */
$sizeKeysForVariant = $sizeOptions !== [] ? $sizeOptions : [''];

$activeColorIdx = 0;
if ($colorOptions !== [] && $hasVariantInventory) {
    foreach ($colorOptions as $i => $col) {
        $ck = mb_strtolower(trim($col));
        foreach ($sizeKeysForVariant as $sz) {
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

$shippingSettings = [
    'handling_time_days' => 2,
    'shipping_policy' => '',
];
$returnSettings = [
    'return_window_days' => 30,
    'return_conditions' => '',
    'refund_method' => 'original_payment',
];
$sellerIdForSettings = (int) ($product['seller_id'] ?? 0);
if ($sellerIdForSettings > 0) {
    $shippingSt = $pdo->prepare(
        'SELECT handling_time_days, shipping_policy
         FROM seller_shipping_settings
         WHERE seller_id = ?
         LIMIT 1'
    );
    $shippingSt->execute([$sellerIdForSettings]);
    $shippingSettings = array_merge($shippingSettings, $shippingSt->fetch() ?: []);

    $returnSt = $pdo->prepare(
        'SELECT return_window_days, return_conditions, refund_method
         FROM seller_return_settings
         WHERE seller_id = ?
         LIMIT 1'
    );
    $returnSt->execute([$sellerIdForSettings]);
    $returnSettings = array_merge($returnSettings, $returnSt->fetch() ?: []);
}

$estimatedDays = max(1, (int) ($shippingSettings['handling_time_days'] ?? 2) + 3);
$estimatedDateText = (new DateTimeImmutable())
    ->modify('+' . $estimatedDays . ' days')
    ->format('D, M j');
$returnWindowDays = max(0, (int) ($returnSettings['return_window_days'] ?? 30));
$returnPolicySummary = trim((string) ($returnSettings['return_conditions'] ?? ''));
if ($returnPolicySummary === '') {
    $returnPolicySummary = 'Hassle-free return policy';
}
$refundMethodMap = [
    'original_payment' => 'Refund to original payment source',
    'bank_transfer' => 'Refund via bank transfer',
    'store_credit' => 'Refund as store credit',
];
$refundMethodText = $refundMethodMap[(string) ($returnSettings['refund_method'] ?? 'original_payment')] ?? 'Secure payment and refund support';
$offerCountdownSeconds = max(0, (int) ($product['offer_countdown_seconds'] ?? 0));
if ($offerCountdownSeconds <= 0) {
    $offerCountdownSeconds = (2 * 3600) + (14 * 60) + 38;
}
$offerCountdownDisplay = gmdate('H:i:s', $offerCountdownSeconds);
$offerBankText = trim((string) ($product['offer_bank_text'] ?? ''));
if ($offerBankText === '') {
    $offerBankText = 'Extra 10% off with HDFC card';
}
/** Timer pill label: never duplicate bank line; custom flash text only if different from bank. */
$offerFlashTextRaw = trim((string) ($product['offer_flash_text'] ?? ''));
$offerTimerLabel = 'Offer ends in';
if ($offerFlashTextRaw !== '' && mb_strtolower($offerFlashTextRaw) !== mb_strtolower($offerBankText)) {
    $offerTimerLabel = $offerFlashTextRaw;
}

$related = products_fetch_related($pdo, (int) $product['id'], (string) $product['category'], 4);
$initialCartCount = 0;
if (is_array($_SESSION['cart'] ?? null)) {
    foreach ($_SESSION['cart'] as $line) {
        if (!is_array($line)) {
            continue;
        }
        $initialCartCount += max(1, (int) ($line['qty'] ?? 1));
    }
}
$approvalStatus = strtolower(trim((string) ($product['approval_status'] ?? 'approved')));
$sellerPreviewOnly = $approvalStatus !== 'approved'
    && (
        ($sellerSessionId > 0 && $sellerSessionId === (int) ($product['seller_id'] ?? 0))
        || $adminSessionId > 0
    );

$pageProduct = [
    'id' => $product['id'],
    'name' => $product['name'],
    'brand' => $product['brand'],
    'sellerName' => $product['seller_name'] ?? 'LUXE Store',
    'images' => $product['images'] ?? [],
    'stockQty' => (int) ($product['stock_qty'] ?? 0),
    'price' => $product['price'],
    'original' => $product['original'],
    'emoji' => $product['emoji'],
    'imageBg' => $product['image_bg'],
    'rating' => $displayRating,
    'reviews' => $displayReviewCount,
    'badge' => $product['badge'] ?? '',
    'category' => $product['category'],
    'sizes' => $sizeOptions,
    'colors' => $colorOptions,
    'hasColorOptions' => $colorOptions !== [],
    'hasVariantInventory' => $hasVariantInventory,
    'variantStock' => $variantStockMap,
    'offerCountdownSeconds' => $offerCountdownSeconds,
    'sellerPreviewOnly' => $sellerPreviewOnly,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LUXE — <?= h($product['name']) ?> | Product Details</title>
  <meta name="description" content="<?= h($product['name']) ?> — Shop at LUXE with free delivery and 30-day returns." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body>

  <!-- Cursor -->
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>

  <!-- Background -->
  <div class="bg-scene">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="grid-lines"></div>
  </div>

  <!-- Navbar -->
  <nav class="navbar" id="navbar">
    <div class="nav-container">
      <a href="index.php" class="nav-logo">LUXE</a>
      <div class="nav-breadcrumb">
        <a href="index.php">Home</a>
        <span>/</span>
        <a href="index.php#collections">Fashion</a>
        <span>/</span>
        <span class="breadcrumb-current"><?= h($product['name']) ?></span>
      </div>
      <div class="nav-actions">
        <button class="icon-btn" id="searchBtn" aria-label="Search">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </button>
        <?php
        $wishlistNavHref = $currentUser
            ? 'profile.php?tab=wishlist'
            : 'login.php?redirect=' . rawurlencode('profile.php?tab=wishlist');
        ?>
        <a href="<?= h($wishlistNavHref) ?>" class="icon-btn" id="wishlistNavBtn" aria-label="Wishlist">
          <svg id="wishNavIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </a>
        <button class="cart-btn" id="cartNavBtn" type="button" aria-label="Cart" onclick="window.location.href='cart.php'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          <span class="cart-count" id="cartCount"><?= (int) $initialCartCount ?></span>
        </button>
      </div>
    </div>
  </nav>

  <!-- Search Overlay -->
  <div class="search-overlay" id="searchOverlay">
    <button class="search-close" id="searchClose">✕</button>
    <div class="search-inner">
      <h2>What are you looking for?</h2>
      <div class="search-box">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" id="searchInput" placeholder="Search products, brands..." />
      </div>
      <div class="search-tags">
        <span class="tag">👟 Sneakers</span>
        <span class="tag">👜 Bags</span>
        <span class="tag">⌚ Watches</span>
        <span class="tag">💻 Laptops</span>
        <span class="tag">🧴 Skincare</span>
      </div>
    </div>
  </div>

  <!-- Page Content -->
  <main class="main-content">

    <!-- ===== PRODUCT SECTION ===== -->
    <section class="product-section">
      <div class="container">
        <?php if ($sellerPreviewOnly): ?>
        <div class="seller-preview-banner" role="status">
          <?php if ($adminSessionId > 0): ?>
            <strong>Admin preview</strong> — Yeh listing abhi public catalog me live nahi hai. Approve karne ke liye <a href="admin/product-approvals.php" style="color:inherit;text-decoration:underline">Product approvals</a> kholein.
          <?php elseif ($approvalStatus === 'pending'): ?>
            <strong>Seller preview</strong> — Yeh product admin approval ke baad hi buyers ko dikhega. Abhi cart / checkout available nahi hai.
          <?php else: ?>
            <strong>Seller preview</strong> — Yeh product reject ho chuka hai. Seller panel se edit karke dubara submit karein.
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="product-layout">

          <!-- LEFT: Image Gallery -->
          <div class="gallery-col">
            <!-- Thumbnails -->
            <div class="thumbnails" id="thumbs">
              <button class="thumb active" data-image="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80" onclick="switchImage(this)">
                <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=240&q=70" alt="Product thumbnail 1" loading="lazy" />
              </button>
              <button class="thumb" data-image="https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=900&q=80" onclick="switchImage(this)">
                <img src="https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=240&q=70" alt="Product thumbnail 2" loading="lazy" />
              </button>
              <button class="thumb" data-image="https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=900&q=80" onclick="switchImage(this)">
                <img src="https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=240&q=70" alt="Product thumbnail 3" loading="lazy" />
              </button>
              <button class="thumb" data-image="https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=900&q=80" onclick="switchImage(this)">
                <img src="https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=240&q=70" alt="Product thumbnail 4" loading="lazy" />
              </button>
            </div>

            <!-- Main Image -->
            <div class="main-image-wrap">
              <div class="main-image" id="mainImage">
                <div class="img-bg" id="imgBg"></div>
                <img class="product-emoji" id="productEmoji" src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80" alt="<?= h($product['name']) ?>" />
                <div class="img-shine"></div>

                <!-- Badges -->
                <div class="img-badge badge-sale">38% OFF</div>
                <div class="img-badge badge-stock">✓ In Stock</div>

              </div>

              <!-- Zoom lens overlay -->
              <div class="zoom-overlay hidden" id="zoomOverlay">
                <div class="zoom-display" id="zoomDisplay">
                  <img id="zoomEmoji" src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80" alt="<?= h($product['name']) ?> zoom" />
                </div>
              </div>
            </div>
          </div>

          <!-- RIGHT: Product Info -->
          <div class="info-col">

            <!-- Brand & Rating -->
            <div class="product-meta-top">
              <span class="product-brand">Nike × LUXE Exclusive</span>
              <div class="product-rating">
                <div class="stars-row">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star half">★</span>
                </div>
                <span class="rating-count"><?= h(number_format($displayRating, 1)) ?> <span>(<?= h(number_format($displayReviewCount)) ?> reviews)</span></span>
                <?php if ($displaySoldLabel !== ''): ?>
                <span class="rating-dot">·</span>
                <span class="sold-count"><?= h($displaySoldLabel) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <?php
            $pubSellerId = (int) ($product['seller_id'] ?? 0);
            $pubSellerName = (string) ($product['seller_name'] ?? 'LUXE Store');
            ?>
            <p class="product-seller-line">Sold by <?php if ($pubSellerId > 0): ?>
                <a href="seller-store.php?id=<?= $pubSellerId ?>" class="product-seller-link"><strong id="productSellerName"><?= h($pubSellerName) ?></strong></a>
              <?php else: ?>
                <strong id="productSellerName"><?= h($pubSellerName) ?></strong>
              <?php endif; ?>
            </p>

            <!-- Name -->
            <h1 class="product-name">AirMax Pro 2026</h1>
            <p class="product-tagline">Engineered for the relentless. Styled for the fearless.</p>

            <!-- Price -->
            <div class="price-block">
              <span class="price-main">₹8,999</span>
              <span class="price-strike">₹14,500</span>
              <span class="price-save">Save ₹5,501 (38%)</span>
            </div>

            <!-- Offer pill -->
            <div class="offer-pills">
              <div class="offer-pill">
                <span class="offer-icon">⚡</span>
                <span><?= h($offerTimerLabel) ?> <strong id="offerTimer" data-offer-seconds="<?= (int) $offerCountdownSeconds ?>"><?= h($offerCountdownDisplay) ?></strong></span>
              </div>
              <div class="offer-pill">
                <span class="offer-icon">🏦</span>
                <span><?= h($offerBankText) ?></span>
              </div>
            </div>

            <!-- Divider -->
            <div class="section-divider"></div>

            <!-- Color -->
            <div class="option-group">
              <div class="option-label">
                <span>Color</span>
                <strong id="selectedColor"><?= h($selectedColorDefault) ?></strong>
              </div>
              <div class="color-swatches">
                <?php if ($colorOptions !== []): ?>
                  <?php foreach ($colorOptions as $idx => $color): ?>
                    <button class="swatch<?= $idx === $activeColorIdx ? ' active' : '' ?>" type="button" style="background:<?= h(product_swatch_style_for_color($color, $idx)) ?>" data-color="<?= h($color) ?>" onclick="selectColor(this)" title="<?= h($color) ?>"></button>
                  <?php endforeach; ?>
                <?php else: ?>
                  <button class="swatch active" type="button" style="background:linear-gradient(135deg,#8b5cf6,#ec4899)" data-color="Default" onclick="selectColor(this)"></button>
                <?php endif; ?>
              </div>
            </div>

            <!-- Size -->
            <div class="option-group">
              <div class="option-label">
                <span>Size (UK)</span>
                <a href="#" class="size-guide-link">Size Guide →</a>
              </div>
              <div class="size-grid" id="sizeGrid">
                <?php if ($sizeOptions !== []): ?>
                  <?php foreach ($sizeOptions as $idx => $size): ?>
                    <?php
                    $vk = mb_strtolower(trim($size)) . '|' . $colorKeyForDefault;
                    $sizeQty = $hasVariantInventory
                        ? (int) ($variantStockMap[$vk] ?? 0)
                        : max(0, (int) ($product['stock_qty'] ?? 0));
                    $sizeOut = $hasVariantInventory && $sizeQty <= 0;
                    $isActive = $idx === $activeSizeIdx;
                    ?>
                    <button class="size-btn<?= $isActive ? ' active' : '' ?><?= $sizeOut ? ' out' : '' ?>" type="button" data-size="<?= h($size) ?>" onclick="selectSize(this)">
                      <span class="size-btn-label"><?= h($size) ?></span>
                      <?php if ($hasVariantInventory): ?>
                        <span class="size-btn-stock"><?= $sizeQty > 0 ? '(' . (int) $sizeQty . ')' : '' ?></span>
                      <?php endif; ?>
                    </button>
                  <?php endforeach; ?>
                <?php else: ?>
                  <?php
                  $stdStockKey = mb_strtolower('') . '|' . $colorKeyForDefault;
                  $stdQty = $hasVariantInventory
                      ? (int) ($variantStockMap[$stdStockKey] ?? 0)
                      : max(0, (int) ($product['stock_qty'] ?? 0));
                  $stdOut = $hasVariantInventory && $stdQty <= 0;
                  ?>
                  <button class="size-btn active<?= $stdOut ? ' out' : '' ?>" type="button" data-size="" onclick="selectSize(this)">
                    <span class="size-btn-label">Standard</span>
                    <?php if ($hasVariantInventory): ?>
                      <span class="size-btn-stock"><?= $stdQty > 0 ? '(' . (int) $stdQty . ')' : '' ?></span>
                    <?php endif; ?>
                  </button>
                <?php endif; ?>
              </div>
              <span class="size-note">Available sizes are seller configured.</span>
            </div>

            <!-- Quantity -->
            <div class="option-group">
              <div class="option-label"><span>Quantity</span></div>
              <div class="qty-row">
                <div class="qty-control">
                  <button class="qty-btn" id="qtyMinus" type="button" onclick="changeQty(-1)">−</button>
                  <span class="qty-val" id="qtyVal">1</span>
                  <button class="qty-btn" id="qtyPlus" type="button" onclick="changeQty(1)">+</button>
                </div>
                <span class="qty-available">Only <strong id="productStockQty"><?= (int) ($product['stock_qty'] ?? 0) ?></strong> left in stock</span>
              </div>
            </div>

            <div class="section-divider"></div>

            <!-- CTA Buttons -->
            <div class="cta-row">
              <button class="btn-primary cta-cart" id="addCartBtn" onclick="addToCart()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                Add to Cart
              </button>
              <button class="btn-buynow" id="buyNowBtn" onclick="buyNow()">
                Buy Now →
              </button>
              <button class="btn-wishlist" id="wishlistBtn" onclick="toggleWishlist()" aria-label="Wishlist">
                <svg id="wishIcon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
              </button>
            </div>

            <!-- Delivery -->
            <div class="delivery-cards">
              <div class="delivery-card">
                <span class="dc-icon">🚚</span>
                <div>
                  <strong>Free Delivery</strong>
                  <span>Estimated by <b><?= h($estimatedDateText) ?></b></span>
                </div>
              </div>
              <div class="delivery-card">
                <span class="dc-icon">🔄</span>
                <div>
                  <strong><?= (int) $returnWindowDays ?>-Day Returns</strong>
                  <span><?= h($returnPolicySummary) ?></span>
                </div>
              </div>
              <div class="delivery-card">
                <span class="dc-icon">🔒</span>
                <div>
                  <strong>Secure Checkout</strong>
                  <span><?= h($refundMethodText) ?></span>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>
    </section>

    <!-- ===== TABS SECTION ===== -->
    <section class="tabs-section">
      <div class="container">
        <div class="product-tabs">
          <div class="tab-nav">
            <button class="ptab active" data-tab="description" onclick="switchProductTab(this)">Description</button>
            <button class="ptab" data-tab="specs" onclick="switchProductTab(this)">Specifications</button>
            <button class="ptab" data-tab="reviews" onclick="switchProductTab(this)">Reviews <span class="tab-count"><?= h(number_format($displayReviewCount)) ?></span></button>
          </div>

          <!-- Description -->
          <div class="tab-panel active" id="tab-description">
            <div class="desc-grid">
              <div class="desc-text">
                <h3>About This Product</h3>
                <p>The AirMax Pro 2026 represents the pinnacle of athletic footwear engineering. Built for those who refuse to compromise between performance and aesthetics, this shoe delivers an unparalleled running experience.</p>
                <p>Featuring Nike's proprietary AirMax cushioning system with 40% more air volume than its predecessor, every stride feels like walking on clouds. The knit upper adapts dynamically to your foot's movement, providing a sock-like fit without sacrificing support.</p>
                <ul class="feature-list">
                  <li>✦ ReactX foam midsole for 13% more energy return</li>
                  <li>✦ Flyknit upper with 360° breathability</li>
                  <li>✦ Rubber outsole optimized for urban terrain</li>
                  <li>✦ Recycled materials — 60% recycled content</li>
                  <li>✦ Available in 5 exclusive LUXE colorways</li>
                </ul>
              </div>
              <div class="desc-visual">
                <div class="desc-big-emoji">👟</div>
                <div class="desc-stat-grid">
                  <div class="desc-stat"><strong>40%</strong><span>More Air Volume</span></div>
                  <div class="desc-stat"><strong>13%</strong><span>Energy Return</span></div>
                  <div class="desc-stat"><strong>60%</strong><span>Recycled Material</span></div>
                  <div class="desc-stat"><strong>270g</strong><span>Lightweight</span></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Specifications -->
          <div class="tab-panel hidden" id="tab-specs">
            <div class="specs-table">
              <div class="spec-row"><span class="spec-key">Brand</span><span class="spec-val"><?= h((string) ($product['brand'] ?? 'LUXE')) ?></span></div>
              <div class="spec-row"><span class="spec-key">Model</span><span class="spec-val"><?= h((string) ($product['name'] ?? '-')) ?></span></div>
              <div class="spec-row"><span class="spec-key">Category</span><span class="spec-val"><?= h((string) ucfirst((string) ($product['category'] ?? '-'))) ?></span></div>
              <div class="spec-row"><span class="spec-key">Seller</span><span class="spec-val"><?php if ($pubSellerId > 0): ?><a href="seller-store.php?id=<?= $pubSellerId ?>" class="product-seller-link"><?= h($pubSellerName) ?></a><?php else: ?><?= h($pubSellerName) ?><?php endif; ?></span></div>
              <div class="spec-row"><span class="spec-key">Sizes Available</span><span class="spec-val"><?= h($sizeOptions !== [] ? implode(', ', $sizeOptions) : 'Standard') ?></span></div>
              <div class="spec-row"><span class="spec-key">Colors Available</span><span class="spec-val"><?= h($colorOptions !== [] ? implode(', ', $colorOptions) : 'Default') ?></span></div>
              <div class="spec-row"><span class="spec-key">Stock</span><span class="spec-val"><?= (int) ($product['stock_qty'] ?? 0) ?> units</span></div>
              <div class="spec-row"><span class="spec-key">SKU Code</span><span class="spec-val"><?= h(trim((string) ($product['sku'] ?? '')) !== '' ? (string) $product['sku'] : 'Not set') ?></span></div>
            </div>
          </div>

          <!-- Reviews -->
          <div class="tab-panel hidden" id="tab-reviews">
            <div class="reviews-layout">
              <!-- Summary -->
              <div class="reviews-summary">
                <div class="rating-big"><?= h(number_format($displayRating, 1)) ?></div>
                <div class="stars-big">
                  <?php
                  $roundedStars = (int) round($displayRating);
                  echo h(str_repeat('★', max(0, min(5, $roundedStars))) . str_repeat('☆', max(0, 5 - $roundedStars)));
                  ?>
                </div>
                <div class="reviews-total">Based on <?= h(number_format($displayReviewCount)) ?> reviews</div>
                <div class="rating-bars">
                  <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                    <?php
                    $count = (int) ($ratingBreakdown[$star] ?? 0);
                    $percent = $totalProductReviews > 0 ? (int) round(($count / $totalProductReviews) * 100) : 0;
                    $fillStyle = $star >= 4 ? '' : ($star === 3 ? 'background:#f59e0b' : 'background:#ef4444');
                    ?>
                    <div class="rbar-row">
                      <span><?= $star ?>★</span>
                      <div class="rbar"><div class="rbar-fill" style="width:<?= $percent ?>%;<?= $fillStyle ?>"></div></div>
                      <span><?= $percent ?>%</span>
                    </div>
                  <?php endforeach; ?>
                </div>
                <?php if ($reviewSuccess !== ''): ?>
                  <div class="review-alert review-alert--success"><?= h($reviewSuccess) ?></div>
                <?php endif; ?>
                <?php if ($reviewError !== ''): ?>
                  <div class="review-alert review-alert--error"><?= h($reviewError) ?></div>
                <?php endif; ?>
                <?php if ($currentUserId !== null): ?>
                  <form method="post" class="review-form">
                    <input type="hidden" name="action" value="submit_product_review">
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <label for="review_rating" class="review-form__label">Your rating</label>
                    <select id="review_rating" name="rating" class="review-form__control" required>
                      <?php for ($i = 5; $i >= 1; $i--): ?>
                        <option value="<?= $i ?>"<?= $postedRating === $i ? ' selected' : '' ?>><?= $i ?> Star<?= $i > 1 ? 's' : '' ?></option>
                      <?php endfor; ?>
                    </select>
                    <label for="review_text" class="review-form__label">Your review</label>
                    <textarea id="review_text" name="review_text" rows="4" maxlength="1000" placeholder="Share your experience about this product..." class="review-form__control review-form__textarea" required><?= h($postedReviewText) ?></textarea>
                    <button class="btn-primary" type="submit" style="width:100%">Submit Review</button>
                  </form>
                <?php else: ?>
                  <a class="btn-primary" style="margin-top:20px;width:100%;text-decoration:none;display:inline-flex;justify-content:center" href="login.php">Sign in to write a review</a>
                <?php endif; ?>
              </div>
              <!-- Review Cards -->
              <div class="reviews-list">
                <?php foreach ($productReviews as $idx => $review): ?>
                  <?php
                  $reviewRating = max(1, min(5, (int) ($review['rating'] ?? 0)));
                  $reviewStars = str_repeat('★', $reviewRating) . str_repeat('☆', 5 - $reviewRating);
                  $reviewer = trim((string) ($review['customer_name'] ?? 'Customer'));
                  if ($reviewer === '') {
                      $reviewer = 'Customer';
                  }
                  $initial = strtoupper(substr($reviewer, 0, 1));
                  $reviewClass = $idx === 0 ? 'review-card featured-review' : 'review-card';
                  ?>
                  <div class="<?= h($reviewClass) ?>">
                    <div class="review-header">
                      <div class="review-avatar"><?= h($initial) ?></div>
                      <div class="review-meta">
                        <strong><?= h($reviewer) ?></strong>
                        <span>Verified Buyer</span>
                      </div>
                      <div class="review-stars"><?= h($reviewStars) ?></div>
                      <?php if ($idx === 0): ?>
                        <span class="review-badge">Top Review</span>
                      <?php endif; ?>
                    </div>
                    <p><?= nl2br(h((string) ($review['review_text'] ?? ''))) ?></p>
                    <?php if (trim((string) ($review['seller_response'] ?? '')) !== ''): ?>
                      <div class="review-seller-response">
                        <strong>Seller response</strong>
                        <p><?= nl2br(h((string) $review['seller_response'])) ?></p>
                      </div>
                    <?php endif; ?>
                    <div class="review-footer">
                      <span class="review-date"><?= h((string) ($review['created_at'] ?? '-')) ?></span>
                      <button class="helpful-btn" onclick="markHelpful(this)">👍 Helpful (0)</button>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php if ($productReviews === []): ?>
                  <div class="review-card">
                    <div class="review-header">
                      <div class="review-avatar">i</div>
                      <div class="review-meta">
                        <strong>No reviews yet</strong>
                        <span>Be the first customer to review this product.</span>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== RELATED PRODUCTS ===== -->
    <section class="related-section">
      <div class="container">
        <div class="section-header">
          <div class="section-badge">You May Also Like</div>
          <h2 class="section-title">Related Products</h2>
        </div>
        <div class="related-grid" id="relatedGrid">
          <!-- Rendered by JS -->
        </div>
      </div>
    </section>

    <!-- ===== STICKY CTA (Mobile) ===== -->
    <div class="sticky-cta" id="stickyCta">
      <div class="sticky-info">
        <span class="sticky-emoji">👟</span>
        <div>
          <strong>AirMax Pro 2026</strong>
          <span>₹8,999 <s>₹14,500</s></span>
        </div>
      </div>
      <button class="btn-primary" id="stickyAddCartBtn" onclick="addToCart()">Add to Cart</button>
    </div>

  </main>

  <!-- Toast -->
  <div class="toast" id="toast"></div>

  <!-- Size Guide Modal -->
  <div class="modal-overlay hidden" id="sizeModal">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Size Guide</h3>
        <button class="modal-close" onclick="closeSizeGuide()">✕</button>
      </div>
      <div class="size-chart">
        <table>
          <thead><tr><th>UK</th><th>EU</th><th>US</th><th>Foot Length (cm)</th></tr></thead>
          <tbody>
            <tr><td>6</td><td>39</td><td>7</td><td>24.5</td></tr>
            <tr><td>7</td><td>40</td><td>8</td><td>25.5</td></tr>
            <tr><td>8</td><td>41</td><td>9</td><td>26.5</td></tr>
            <tr><td>9</td><td>42</td><td>10</td><td>27.5</td></tr>
            <tr><td>10</td><td>43</td><td>11</td><td>28.5</td></tr>
            <tr><td>12</td><td>45</td><td>13</td><td>30.0</td></tr>
          </tbody>
        </table>
      </div>
      <p class="size-tip">💡 Tip: If you're between sizes, we recommend going up for a comfortable fit.</p>
    </div>
  </div>

  <script>
    window.LUXE_URLS = <?= json_encode([
        'home' => 'index.php',
        'login' => 'login.php',
        'cart' => 'cart.php',
        'checkout' => 'checkout.php',
        'product' => 'product.php',
        'orders' => 'orders.php',
        'profile' => 'profile.php',
    ], JSON_THROW_ON_ERROR) ?>;
    window.__API_CART__ = 'api/cart.php';
    window.__AUTH_USER_ID__ = <?= json_encode(auth_user_id()) ?>;
    window.__CART_COUNT__ = <?= (int) $initialCartCount ?>;
    window.__PRODUCT_PAGE__ = <?= json_encode($pageProduct, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__RELATED_PRODUCTS__ = <?= json_encode($related, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
