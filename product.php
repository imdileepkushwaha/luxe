<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/seller_product_catalog.php';
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}
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

$searchCatalogProducts = products_fetch_all($pdo);

function product_parse_options_csv(string $csv): array
{
    $csv = str_replace(["\r\n", "\r", "\n", "\t", ';', '|', '،'], ',', $csv);
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
$userHasDeliveredThisProduct = $currentUserId !== null
    && profile_user_has_delivered_product($pdo, (int) $currentUserId, (int) $product['id']);
$userHasReviewForProduct = false;
if ($currentUserId !== null) {
    $userReviewChk = $pdo->prepare('SELECT 1 FROM product_reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
    $userReviewChk->execute([(int) $product['id'], (int) $currentUserId]);
    $userHasReviewForProduct = (bool) $userReviewChk->fetchColumn();
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
/** DB-only keys (no storefront aliases); used for total inventory sum. */
$variantStockMapForTotals = $variantStockMap;
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
$variantSizesAllSt = $pdo->prepare(
    'SELECT DISTINCT TRIM(size_label) AS s FROM product_variant_inventory
     WHERE product_id = ? AND TRIM(COALESCE(size_label, \'\')) <> \'\''
);
$variantSizesAllSt->execute([(int) $product['id']]);
foreach ($variantSizesAllSt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $t = trim((string) ($row['s'] ?? ''));
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
$variantColorsAllSt = $pdo->prepare(
    'SELECT DISTINCT TRIM(color_label) AS c FROM product_variant_inventory
     WHERE product_id = ? AND TRIM(COALESCE(color_label, \'\')) <> \'\''
);
$variantColorsAllSt->execute([(int) $product['id']]);
foreach ($variantColorsAllSt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $t = trim((string) ($row['c'] ?? ''));
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

/*
 * Variant rows use size|color keys. Sellers often leave color_label empty while CSV still lists colors,
 * or store a color while the storefront has no color swatches — both break size|color lookups.
 * Add alias keys so PHP + JS variantStock matches selected swatches / "Default".
 */
if ($variantStockMap !== []) {
    if ($colorOptions !== []) {
        $aliasAdd = [];
        foreach ($variantStockMap as $key => $qty) {
            $parts = explode('|', (string) $key, 2);
            $szKey = $parts[0] ?? '';
            $clKey = $parts[1] ?? '';
            if ($clKey !== '') {
                continue;
            }
            // Blank-color rows (e.g. "30|") must not clone into "30|blue" when real "30|blue" rows exist,
            // or every size can show the same stale total as the blanket row.
            if (product_variant_map_has_color_specific_rows_for_size($variantStockMap, $szKey)) {
                continue;
            }
            foreach ($colorOptions as $col) {
                $alias = $szKey . '|' . mb_strtolower(trim($col));
                if (!array_key_exists($alias, $variantStockMap)) {
                    $aliasAdd[$alias] = $qty;
                }
            }
        }
        foreach ($aliasAdd as $ak => $qv) {
            $variantStockMap[$ak] = $qv;
        }
    } else {
        $aliasAdd = [];
        foreach ($variantStockMap as $key => $qty) {
            $parts = explode('|', (string) $key, 2);
            $szKey = $parts[0] ?? '';
            $clKey = $parts[1] ?? '';
            if ($clKey === '') {
                continue;
            }
            $blank = $szKey . '|';
            if (!array_key_exists($blank, $variantStockMap)) {
                $aliasAdd[$blank] = ($aliasAdd[$blank] ?? 0) + $qty;
            }
        }
        foreach ($aliasAdd as $ak => $qv) {
            if (!array_key_exists($ak, $variantStockMap)) {
                $variantStockMap[$ak] = $qv;
            }
        }
    }
}

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

/** Initial units for selected default size/color (matches JS after first refresh). */
$initialSpecStockQty = (int) ($product['stock_qty'] ?? 0);
if ($hasVariantInventory) {
    $initSizeForSpec = $sizeOptions !== [] ? trim((string) ($sizeOptions[$activeSizeIdx] ?? '')) : '';
    $initialSpecStockQty = product_variant_display_qty_from_map(
        $variantStockMap,
        $colorOptions !== [],
        $colorKeyForDefault,
        $initSizeForSpec
    );
}

/** Sum of all active variant rows; if no variants, same as product stock. */
$totalInventoryUnits = (int) ($product['stock_qty'] ?? 0);
if ($hasVariantInventory) {
    $totalInventoryUnits = 0;
    foreach ($variantStockMapForTotals as $unitQty) {
        $totalInventoryUnits += max(0, (int) $unitQty);
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

$productTypeSlug = strtolower(trim((string) ($product['product_type'] ?? 'general')));
$productTypeLabel = $productTypeSlug === '' || $productTypeSlug === 'general'
    ? 'General'
    : ucfirst(str_replace(['-', '_'], ' ', $productTypeSlug));
foreach (seller_product_types_for_category((string) ($product['category'] ?? '')) as $_pt) {
    if (($_pt['slug'] ?? '') === $productTypeSlug) {
        $productTypeLabel = (string) ($_pt['label'] ?? $productTypeLabel);
        break;
    }
}

$shippingClassKey = strtolower(trim((string) ($product['shipping_class'] ?? 'standard')));
if (!in_array($shippingClassKey, ['standard', 'express', 'free'], true)) {
    $shippingClassKey = 'standard';
}
$productShippingTitle = match ($shippingClassKey) {
    'express' => 'Express shipping',
    'free' => 'Free shipping',
    default => 'Standard shipping',
};
$productShippingDeliveryLine = match ($shippingClassKey) {
    'express' => 'Express handling — estimated by ' . $estimatedDateText,
    'free' => 'Includes free delivery — estimated by ' . $estimatedDateText,
    default => 'Estimated delivery by ' . $estimatedDateText,
};

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

/** @var list<array{k:string,q:int}> Stable list for JS — avoids JSON object-key quirks for size|color keys. */
$variantStockEntries = [];
foreach ($variantStockMap as $vk => $vq) {
    $variantStockEntries[] = ['k' => (string) $vk, 'q' => max(0, (int) $vq)];
}

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
    'variantStockEntries' => $variantStockEntries,
    'offerCountdownSeconds' => $offerCountdownSeconds,
    'sellerPreviewOnly' => $sellerPreviewOnly,
    'shippingClass' => $shippingClassKey,
    'shippingTitle' => $productShippingTitle,
];
?>
<!DOCTYPE html>
<html lang="en" class="product-page-root">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>LUXE — <?= h($product['name']) ?> | Product Details</title>
  <meta name="description" content="<?= h($product['name']) ?> — Shop at LUXE with free delivery and 30-day returns." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body class="page-product">

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
      <div class="nav-brand-cluster">
        <?php require __DIR__ . '/includes/nav_hamburger_btn.php'; ?>
        <a href="index.php" class="nav-logo">LUXE</a>
      </div>
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
        <a href="<?= h($wishlistNavHref) ?>" class="icon-btn" id="wishlistNavBtn" aria-label="Wishlist" data-nav-mobile="drawer">
          <svg id="wishNavIcon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </a>
        <button class="cart-btn" id="cartNavBtn" type="button" aria-label="Cart" onclick="window.location.href='cart.php'">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          <span class="cart-count" id="cartCount"><?= (int) $initialCartCount ?></span>
        </button>
      </div>
    </div>
  </nav>
  <?php require __DIR__ . '/includes/nav_drawer.php'; ?>

  <!-- Search Overlay -->
  <div class="search-overlay" id="searchOverlay" role="dialog" aria-modal="true" aria-labelledby="searchOverlayTitle">
    <div class="search-overlay__ambient" aria-hidden="true"></div>
    <button class="search-close" id="searchClose" type="button" aria-label="Close search">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
    <div class="search-inner">
      <p class="search-kicker">LUXE catalog</p>
      <h2 id="searchOverlayTitle" class="search-title">Find your next favorite</h2>
      <p class="search-lead">Search by product name, brand, or category — matches appear below; press Enter to open the full shop.</p>
      <div class="search-panel">
        <label class="search-box" for="searchInput">
          <span class="search-box__icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          </span>
          <input type="search" id="searchInput" name="q" placeholder="Try Nike, watch, serum…" autocomplete="off" enterkeyhint="search" />
        </label>
        <p class="search-hint">
          <span class="search-hint__desktop"><kbd>Enter</kbd><span class="search-hint__text">Opens shop with results</span></span>
          <span class="search-hint__mobile">Submit to go to the catalog</span>
        </p>
        <div class="search-live-results" id="searchLiveResults" hidden aria-live="polite"></div>
      </div>
      <div class="search-tags-block">
        <span class="search-tags-label">Popular picks</span>
        <div class="search-tags">
          <button type="button" class="tag">👟 Sneakers</button>
          <button type="button" class="tag">👜 Bags</button>
          <button type="button" class="tag">⌚ Watches</button>
          <button type="button" class="tag">💻 Laptops</button>
          <button type="button" class="tag">🧴 Skincare</button>
        </div>
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

          <!-- LEFT: Image Gallery (outer cell stretches row; inner .gallery-sticky is position:sticky) -->
          <div class="gallery-col">
            <div class="gallery-sticky">
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
                    $sizeQty = $hasVariantInventory
                        ? product_variant_display_qty_from_map(
                            $variantStockMap,
                            $colorOptions !== [],
                            $colorKeyForDefault,
                            $size
                        )
                        : (count($sizeOptions) > 1
                            ? 0
                            : max(0, (int) ($product['stock_qty'] ?? 0)));
                    $sizeOut = $hasVariantInventory && $sizeQty <= 0;
                    $isActive = $idx === $activeSizeIdx;
                    ?>
                    <button class="size-btn<?= $isActive ? ' active' : '' ?><?= $sizeOut ? ' out' : '' ?>" type="button" data-size="<?= h($size) ?>" onclick="selectSize(this)">
                      <span class="size-btn-label"><?= h($size) ?></span>
                      <?php if ($hasVariantInventory || ($sizeQty > 0 && count($sizeOptions) <= 1)): ?>
                        <span class="size-btn-stock"><?= $sizeQty > 0 ? '(' . (int) $sizeQty . ')' : '' ?></span>
                      <?php endif; ?>
                    </button>
                  <?php endforeach; ?>
                <?php else: ?>
                  <?php
                  $stdQty = $hasVariantInventory
                      ? product_variant_display_qty_from_map(
                          $variantStockMap,
                          $colorOptions !== [],
                          $colorKeyForDefault,
                          ''
                      )
                      : max(0, (int) ($product['stock_qty'] ?? 0));
                  $stdOut = $hasVariantInventory && $stdQty <= 0;
                  ?>
                  <button class="size-btn active<?= $stdOut ? ' out' : '' ?>" type="button" data-size="" onclick="selectSize(this)">
                    <span class="size-btn-label">Standard</span>
                    <?php if ($hasVariantInventory || $stdQty > 0): ?>
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
                <span class="qty-available">Only <strong id="productStockQty"><?= (int) $initialSpecStockQty ?></strong> left in stock</span>
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
              <button type="button" class="btn-share" id="productShareBtn" aria-label="Share this product" title="Share">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.59 13.51 6.83 3.98M15.41 6.51l-6.82 3.98"/></svg>
              </button>
            </div>

            <!-- Delivery -->
            <div class="delivery-cards">
              <div class="delivery-card">
                <span class="dc-icon">🚚</span>
                <div>
                  <strong><?= h($productShippingTitle) ?></strong>
                  <span><?= h($productShippingDeliveryLine) ?></span>
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
            <?php
            $productDescription = trim((string) ($product['description'] ?? ''));
            $productDescriptionHtml = '';
            if ($productDescription !== '') {
                $productDescriptionHtml = strip_tags(
                    $productDescription,
                    '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><blockquote><span>'
                );
                $productDescriptionHtml = luxe_sanitize_product_description_html($productDescriptionHtml);
            }
            ?>
            <div class="desc-grid">
              <div class="desc-text">
                <h3>About this product</h3>
                <?php if ($productDescriptionHtml !== ''): ?>
                <div class="desc-body"><?= $productDescriptionHtml ?></div>
                <?php else: ?>
                <p class="desc-placeholder">The seller has not added a long description yet. See specifications and reviews for more about <?= h((string) ($product['name'] ?? 'this product')) ?>.</p>
                <?php endif; ?>
              </div>
              <div class="desc-visual">
                
                <div class="desc-stat-grid">
                  <div class="desc-stat"><strong><?= h(number_format($displayRating, 1)) ?></strong><span>Rating</span></div>
                  <div class="desc-stat"><strong><?= h(number_format($displayReviewCount)) ?></strong><span>Reviews</span></div>
                  <div class="desc-stat"><strong><?= h((string) (($product['brand'] ?? '') !== '' ? (string) $product['brand'] : '—')) ?></strong><span>Brand</span></div>
                  <div class="desc-stat"><strong><?= h((string) (($product['category'] ?? '') !== '' ? ucfirst((string) $product['category']) : '—')) ?></strong><span>Category</span></div>
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
              <div class="spec-row"><span class="spec-key">Product type</span><span class="spec-val"><?= h($productTypeLabel) ?></span></div>
              <div class="spec-row"><span class="spec-key">Shipping</span><span class="spec-val"><?= h($productShippingTitle) ?></span></div>
              <div class="spec-row"><span class="spec-key">Seller</span><span class="spec-val"><?php if ($pubSellerId > 0): ?><a href="seller-store.php?id=<?= $pubSellerId ?>" class="product-seller-link"><?= h($pubSellerName) ?></a><?php else: ?><?= h($pubSellerName) ?><?php endif; ?></span></div>
              <div class="spec-row"><span class="spec-key">Sizes Available</span><span class="spec-val"><?= h($sizeOptions !== [] ? implode(', ', $sizeOptions) : 'Standard') ?></span></div>
              <div class="spec-row"><span class="spec-key">Colors Available</span><span class="spec-val"><?= h($colorOptions !== [] ? implode(', ', $colorOptions) : 'Default') ?></span></div>
              <div class="spec-row"><span class="spec-key">Stock</span><span class="spec-val"><span id="specStockLine"><?= (int) $initialSpecStockQty ?> units</span><?php if ($hasVariantInventory): ?> <span class="spec-stock-hint">(selected variant)</span><?php endif; ?></span></div>
              <?php if ($hasVariantInventory): ?>
              <div class="spec-row"><span class="spec-key">Total inventory</span><span class="spec-val"><?= (int) $totalInventoryUnits ?> units <span class="spec-stock-hint">(all sizes &amp; colors)</span></span></div>
              <?php endif; ?>
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
                <?php
                $profileReviewBase = 'profile.php?tab=reviews&highlight=' . (int) $product['id'];
                $loginForReviewHref = 'login.php?redirect=' . rawurlencode($profileReviewBase);
                ?>
                <?php if ($currentUserId !== null && $userHasDeliveredThisProduct && !$userHasReviewForProduct): ?>
                  <a class="btn-primary" style="margin-top:20px;width:100%;text-decoration:none;display:inline-flex;justify-content:center;align-items:center;gap:8px" href="<?= h($profileReviewBase) ?>">Write a review</a>
                <?php elseif ($currentUserId === null): ?>
                  <p class="review-cta-hint" style="margin-top:12px;font-size:0.85rem;opacity:0.85;line-height:1.45">Sirf <strong>delivered order</strong> wale buyers review de sakte hain — <a href="<?= h($loginForReviewHref) ?>" style="color:inherit;font-weight:600">Sign in</a> karke profile par manage karein.</p>
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
                        <span class="verified-buyer">Verified Buyer</span>
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
    <section class="related-section" aria-labelledby="related-heading">
      <div class="related-bg" aria-hidden="true">
        <div class="related-bg__blob related-bg__blob--1"></div>
        <div class="related-bg__blob related-bg__blob--2"></div>
      </div>
      <div class="container related-container">
        <div class="section-header">
          <div class="section-badge">You May Also Like</div>
          <h2 class="section-title" id="related-heading">Related Products</h2>
          <p class="section-subtitle">Hand-picked from the same universe as this piece</p>
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
    window.__PRODUCTS__ = <?= json_encode($searchCatalogProducts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
