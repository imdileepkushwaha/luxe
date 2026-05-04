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
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('index.php');

$categories = [];
foreach ($allProducts as $product) {
    $cat = trim((string) ($product['category'] ?? ''));
    if ($cat === '') {
        continue;
    }
    $label = ucwords(str_replace(['-', '_'], ' ', $cat));
    $categories[$label] = ($categories[$label] ?? 0) + 1;
}
if ($categories === []) {
    $categories = [
        "Men's Fashion" => 0,
        "Women's Fashion" => 0,
        "Kid's Fashion" => 0,
        "Footwear" => 0,
        'Beauty & Cosmetics' => 0,
    ];
}

/** Promotional hero slides only (not tied to catalog products). */
$heroSlides = [
    [
        'hero_kicker' => "Women's Collection",
        'title' => 'FLORAL BODYSUIT',
        'hero_detail' => 'Soft silhouettes and fresh prints—pair with denim or layer for evenings out.',
        'hero_image' => 'images/image-slide-1.png',
        'href' => 'product-list.php',
    ],
    [
        'hero_kicker' => 'New Season',
        'title' => 'LINEN & LIGHT',
        'hero_detail' => 'Breezy fabrics and clean lines built for warm days and relaxed weekends.',
        'hero_image' => 'images/image-slide-2.png',
        'href' => 'product-list.php',
    ],
    [
        'hero_kicker' => 'Everyday edit',
        'title' => 'WARDROBE CORE',
        'hero_detail' => 'Versatile staples you will reach for daily—quality that holds up wash after wash.',
        'hero_image' => 'images/image-slide-3.png',
        'href' => 'product-list.php',
    ],
];

$miniProducts = array_slice($allProducts, 0, 8);
$splitListedProducts = array_slice($allProducts, 0, 9);
$trendingProducts = array_slice($allProducts, 0, 5);
$newProducts = array_slice(array_reverse($allProducts), 0, 4);
$theme1HeaderCategories = array_keys($categories);
$theme1HeaderCompareCount = count($trendingProducts);
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = array_keys($categories);

$dealWeekProduct = $trendingProducts[0] ?? ($allProducts[0] ?? null);
$dealWeekTitle = '';
if ($dealWeekProduct !== null) {
    $dealWeekTitle = trim((string) ($dealWeekProduct['name'] ?? ''));
}
if ($dealWeekTitle === '') {
    $dealWeekTitle = 'Roland Grand White short T-shirt';
}
$dealWeekHref = $dealWeekProduct !== null ? theme1_url($dealWeekProduct) : luxe_public_href('product-list.php');
$dealWeekImgUrl = luxe_theme_asset('images/deal-of-week.png');
$dealWeekDeadlineIso = '2026-11-12T23:59:59';
$dealWeekDeadlineLabel = 'November 12, 2026';

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
function theme1_thumb_style(array $p): string
{
    $path = theme1_thumb_url($p);
    if ($path === '') {
        return '';
    }

    return "background-image:url('" . h($path) . "');background-size:cover;background-position:center;";
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

/**
 * @param array<string,mixed> $slide
 */
function theme1_hero_kicker(array $slide): string
{
    $k = trim((string) ($slide['hero_kicker'] ?? ''));
    if ($k !== '') {
        return $k;
    }
    $cat = trim((string) ($slide['category'] ?? ''));
    if ($cat === '') {
        return "Women's Collection";
    }

    return ucwords(str_replace(['-', '_'], ' ', $cat)) . ' Collection';
}

/**
 * Hero slide background image URL (hero_image / image_url).
 *
 * Relative paths under theme static files use luxe_theme_asset (e.g. images/slide.png → …/theme-2/images/…).
 * Paths under uploads/ use luxe_public_href. Absolute http(s) / root-relative URLs pass through.
 *
 * @param array<string,mixed> $slide
 */
function theme2_hero_slide_image_url(array $slide): string
{
    $path = trim((string) ($slide['hero_image'] ?? $slide['image_url'] ?? ''));
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(?:https?:)?//#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, '/')) {
        return $path;
    }

    $norm = ltrim(str_replace('\\', '/', $path), '/');
    $normLower = strtolower($norm);

    if (str_starts_with($normLower, 'uploads/')) {
        return luxe_public_href($norm);
    }

    return luxe_theme_asset($norm);
}

/**
 * @param array<string,mixed> $slide
 */
function theme2_hero_slide_bg_style(array $slide): string
{
    $url = theme2_hero_slide_image_url($slide);
    if ($url === '') {
        return '';
    }

    /* sizing via .hero-model-photo--dynamic (contain — full image visible, no crop) */
    return "background-image:url('" . h($url) . "');background-position:center;";
}

/**
 * @param array<string,mixed> $slide
 */
function theme2_hero_slide_title(array $slide): string
{
    $t = trim((string) ($slide['title'] ?? $slide['name'] ?? ''));

    return $t !== '' ? $t : 'Collection';
}

/**
 * @param array<string,mixed> $slide
 */
function theme2_hero_slide_href(array $slide): string
{
    $h = trim((string) ($slide['href'] ?? ''));
    if ($h === '') {
        return luxe_public_href('product-list.php');
    }
    if (!preg_match('#^(?:https?:)?//#i', $h) && !str_starts_with($h, '/') && !str_starts_with($h, '#') && !str_starts_with($h, 'mailto:') && !str_starts_with($h, 'tel:')) {
        return luxe_public_href(ltrim($h, '/'));
    }

    return $h;
}

/**
 * Short supporting line under the hero title.
 *
 * @param array<string,mixed> $slide
 */
function theme2_hero_slide_detail(array $slide): string
{
    $d = trim((string) ($slide['hero_detail'] ?? $slide['hero_lede'] ?? $slide['detail'] ?? ''));

    return $d !== '' ? $d : 'Explore the collection—curated styles with secure checkout and easy returns.';
}

/**
 * Primary CTA label (e.g. View Collection).
 *
 * @param array<string,mixed> $slide
 */
function theme2_hero_slide_cta_label(array $slide): string
{
    $l = trim((string) ($slide['cta_label'] ?? ''));

    return $l !== '' ? $l : 'View Collection';
}

/**
 * Stable pseudo-review count for split-block stars row.
 *
 * @param array<string,mixed> $p
 */
function theme1_split_review_count(array $p): int
{
    return 10 + ((int) ($p['id'] ?? 0) % 41);
}

/**
 * Star rating 3–5 for visual variety (attachment-style row).
 *
 * @param array<string,mixed> $p
 */
function theme1_split_star_filled(array $p): int
{
    return 3 + ((int) ($p['id'] ?? 0) % 3);
}

$t2HomeBrand = trim(site_setting_get($pdo, 'site_brand_name', 'LUXE'));
if ($t2HomeBrand === '') {
    $t2HomeBrand = 'LUXE';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home — <?= h($t2HomeBrand) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Montserrat:wght@500;600;700;800&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body>
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="hero-block" aria-label="Featured">
      <div class="container hero-block-inner">
        <article class="hero-banner hero-banner--cream" id="hero-carousel" aria-roledescription="carousel" aria-label="Promotional banners">
          <div class="hero-slides-viewport">
            <div class="hero-slides-track" data-hero-track>
              <?php foreach ($heroSlides as $i => $slide): ?>
                <?php
                $slideArr = (array) $slide;
                $thumbStyle = theme2_hero_slide_bg_style($slideArr);
                $heroHref = theme2_hero_slide_href($slideArr);
                $slideTitle = theme2_hero_slide_title($slideArr);
                $slideDetail = theme2_hero_slide_detail($slideArr);
                $ctaLabel = theme2_hero_slide_cta_label($slideArr);
                ?>
                <div class="hero-slide" id="hero-carousel-slide-<?= $i + 1 ?>" data-hero-slide aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
                  <div class="hero-banner-inner">
                    <span class="hero-banner-shape" aria-hidden="true"></span>
                    <div class="hero-banner-copy">
                      <a class="hero-banner-copy-hit" href="<?= h($heroHref) ?>">
                        <p class="hero-kicker"><?= h(theme1_hero_kicker($slideArr)) ?></p>
                        <h1 class="hero-display-title"><?= h($slideTitle) ?></h1>
                      </a>
                      <p class="hero-lede hero-lede--cream"><?= h($slideDetail) ?></p>
                      <a class="btn-hero hero-view-collection" href="<?= h($heroHref) ?>"><?= h($ctaLabel) ?></a>
                    </div>
                    <div class="hero-banner-visual">
                      <?php if ($thumbStyle !== ''): ?>
                        <div class="hero-model-photo hero-model-photo--dynamic" style="<?= $thumbStyle ?>" role="img" aria-label="<?= h($slideTitle) ?>"></div>
                      <?php else: ?>
                        <div class="hero-model-photo hero-model-photo--cream<?= ($i % 3) + 1 ?>" role="img" aria-label="<?= h($slideTitle) ?>"></div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="hero-slider-dots hero-slider-dots--cream" role="tablist" aria-label="Choose slide">
            <?php foreach ($heroSlides as $i => $unused): ?>
              <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="hero-carousel-slide-<?= $i + 1 ?>" id="hero-carousel-tab-<?= $i + 1 ?>" data-hero-dot="<?= $i ?>" aria-label="Slide <?= $i + 1 ?> of <?= count($heroSlides) ?>"></button>
            <?php endforeach; ?>
          </div>
        </article>
      </div>
    </section>

    

    <div class="container page">
      <section class="split-block split-block--listed" aria-labelledby="split-listed-heading">
        <div class="split-block-main">
          <div class="split-block-head">
            <h2 id="split-listed-heading" class="split-block-title">Top listed items</h2>
            <a class="split-block-viewall" href="<?= h(luxe_public_href('product-list.php')) ?>">View All →</a>
          </div>
          <?php if ($splitListedProducts === []): ?>
            <p class="split-block-empty">No products yet. Add items in admin to show this grid.</p>
          <?php else: ?>
            <div class="split-block-grid">
              <?php foreach (array_chunk($splitListedProducts, 3) as $rowProducts): ?>
                <div class="split-block-row">
                  <?php foreach ($rowProducts as $product): ?>
                    <?php
                    $purl = theme1_url($product);
                    $thumbUrl = theme1_thumb_url($product);
                    $filledStars = theme1_split_star_filled($product);
                    $reviewN = theme1_split_review_count($product);
                    ?>
                    <article class="split-product">
                      <a href="<?= h($purl) ?>" class="split-product-link">
                        <div class="split-product-thumb-wrap">
                          <?php if ($thumbUrl !== ''): ?>
                            <img src="<?= h($thumbUrl) ?>" alt="" class="split-product-thumb-img" loading="lazy" width="96" height="96">
                          <?php else: ?>
                            <span class="split-product-thumb-fallback" aria-hidden="true">🛍</span>
                          <?php endif; ?>
                        </div>
                        <div class="split-product-body">
                          <div class="split-product-rating">
                            <span class="split-stars" aria-hidden="true">
                              <?php for ($si = 1; $si <= 5; $si++): ?>
                                <span class="<?= $si <= $filledStars ? 'split-star split-star--full' : 'split-star split-star--empty' ?>"><?= $si <= $filledStars ? '★' : '☆' ?></span>
                              <?php endfor; ?>
                            </span>
                            <span class="split-review-count">(<?= $reviewN ?>)</span>
                          </div>
                          <h3 class="split-product-name"><?= h((string) ($product['name'] ?? 'Product')) ?></h3>
                          <p class="split-product-price"><?= h(theme1_price($product)) ?></p>
                        </div>
                      </a>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <aside class="split-block-promo">
          <div class="split-block-promo-inner">
            <p class="split-block-promo-kicker">Hot monthly deal</p>
            <h3 class="split-block-promo-title">Save an extra ₹500 per order</h3>
            <a class="split-block-promo-btn" href="<?= h(luxe_public_href('product-list.php')) ?>">Shop Now</a>
            <div class="split-block-promo-visual" role="img" aria-label="Featured shoppers"></div>
          </div>
        </aside>
      </section>

      <section class="block block--products">
        <div class="block-head"><h2>Trending Products</h2><a href="product-list.php">View all</a></div>
        <div class="product-grid five">
          <?php foreach ($trendingProducts as $product): ?>
            <?php
              $pcardRating = (float) ($product['rating'] ?? 0);
              $pcardSavePct = luxe_pcard_save_percent($product);
              $pcardCat = strtoupper(trim((string) ($product['category'] ?? 'General')));
            ?>
            <article class="pcard">
              <div class="pcard__media">
                <div class="pcard__toolbar">
                  <div class="pcard__toolbar-left">
                    <div class="pcard__badges">
                      <?php if (!empty($product['badge'])): ?><span class="pcard__badge pcard__badge--new"><?= h((string) $product['badge']) ?></span><?php endif; ?>
                    </div>
                    <span class="pcard__category"><?= h($pcardCat) ?></span>
                  </div>
                  <button
                    type="button"
                    class="pcard__wish-toggle"
                    aria-label="Toggle wishlist"
                    data-wishlist-btn="1"
                    data-id="<?= (int) ($product['id'] ?? 0) ?>"
                    data-name="<?= h((string) ($product['name'] ?? 'Product')) ?>"
                    data-emoji="<?= h((string) ($product['emoji'] ?? '🛍')) ?>"
                    data-price="<?= (int) ($product['price'] ?? 0) ?>"
                    data-orig="<?= (int) ($product['original'] ?? 0) ?>"
                    data-image="<?= h(theme1_thumb_url($product)) ?>"
                  ><svg class="heart-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></button>
                </div>
                <div class="pcard__image-frame">
                  <div class="thumb" role="img" aria-hidden="true" style="<?= h(theme1_thumb_style($product)) ?>"></div>
                  <div class="pcard__overlay">
                    <a href="<?= h(theme1_url($product)) ?>" class="pcard__btn--buy">Buy Now</a>
                  </div>
                </div>
              </div>
              <div class="pcard__body">
                <h3 class="pcard__title"><?= h((string) ($product['name'] ?? 'Product')) ?></h3>
                <div class="pcard__rating">
                  <?= luxe_pcard_stars_html($pcardRating) ?>
                  <span class="pcard__rating-num"><?= h(number_format($pcardRating, 1)) ?></span>
                  <span class="pcard__reviews"><?= (int) ($product['reviews'] ?? 0) ?> reviews</span>
                </div>
                <div class="pcard__price-row">
                  <div class="pcard__price-stack">
                    <span class="pcard__price-current"><?= h(theme1_price($product)) ?></span>
                    <?php if (theme1_old_price($product) !== ''): ?><del class="pcard__price-old"><?= h(theme1_old_price($product)) ?></del><?php endif; ?>
                  </div>
                  <?php if ($pcardSavePct !== null): ?><span class="pcard__save-badge">Save <?= $pcardSavePct ?>%</span><?php endif; ?>
                </div>
                <div class="pcard__actions">
                  <a class="pcard__btn pcard__btn--view" href="<?= h(theme1_url($product)) ?>">View</a>
                  <a class="pcard__btn pcard__btn--cart" href="<?= h(theme1_url($product)) ?>">Add to Cart</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="block block--products">
        <div class="block-head"><h2>Our New Arrival Products</h2><a href="product-list.php">View all</a></div>
        <div class="product-grid four">
          <?php foreach ($newProducts as $product): ?>
            <?php
              $pcardRating = (float) ($product['rating'] ?? 0);
              $pcardSavePct = luxe_pcard_save_percent($product);
              $pcardCat = strtoupper(trim((string) ($product['category'] ?? 'General')));
            ?>
            <article class="pcard">
              <div class="pcard__media">
                <div class="pcard__toolbar">
                  <div class="pcard__toolbar-left">
                    <div class="pcard__badges"><span class="pcard__badge pcard__badge--new">new</span></div>
                    <span class="pcard__category"><?= h($pcardCat) ?></span>
                  </div>
                  <button
                    type="button"
                    class="pcard__wish-toggle"
                    aria-label="Toggle wishlist"
                    data-wishlist-btn="1"
                    data-id="<?= (int) ($product['id'] ?? 0) ?>"
                    data-name="<?= h((string) ($product['name'] ?? 'Product')) ?>"
                    data-emoji="<?= h((string) ($product['emoji'] ?? '🛍')) ?>"
                    data-price="<?= (int) ($product['price'] ?? 0) ?>"
                    data-orig="<?= (int) ($product['original'] ?? 0) ?>"
                    data-image="<?= h(theme1_thumb_url($product)) ?>"
                  ><svg class="heart-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></button>
                </div>
                <div class="pcard__image-frame">
                  <div class="thumb" role="img" aria-hidden="true" style="<?= h(theme1_thumb_style($product)) ?>"></div>
                  <div class="pcard__overlay">
                    <a href="<?= h(theme1_url($product)) ?>" class="pcard__btn--buy">Buy Now</a>
                  </div>
                </div>
              </div>
              <div class="pcard__body">
                <h3 class="pcard__title"><?= h((string) ($product['name'] ?? 'Product')) ?></h3>
                <div class="pcard__rating">
                  <?= luxe_pcard_stars_html($pcardRating) ?>
                  <span class="pcard__rating-num"><?= h(number_format($pcardRating, 1)) ?></span>
                  <span class="pcard__reviews"><?= (int) ($product['reviews'] ?? 0) ?> reviews</span>
                </div>
                <div class="pcard__price-row">
                  <div class="pcard__price-stack">
                    <span class="pcard__price-current"><?= h(theme1_price($product)) ?></span>
                    <?php if (theme1_old_price($product) !== ''): ?><del class="pcard__price-old"><?= h(theme1_old_price($product)) ?></del><?php endif; ?>
                  </div>
                  <?php if ($pcardSavePct !== null): ?><span class="pcard__save-badge">Save <?= $pcardSavePct ?>%</span><?php endif; ?>
                </div>
                <div class="pcard__actions">
                  <a class="pcard__btn pcard__btn--view" href="<?= h(theme1_url($product)) ?>">View</a>
                  <a class="pcard__btn pcard__btn--cart" href="<?= h(theme1_url($product)) ?>">Add to Cart</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <section class="deal-week" aria-labelledby="deal-week-heading">
      <div class="deal-week__inner">
        <div class="deal-week__grid">
          <div class="deal-week__visual">
            <img src="<?= h($dealWeekImgUrl) ?>" alt="<?= h($dealWeekTitle) ?> — Deal of the week" width="800" height="900" loading="lazy" decoding="async">
          </div>
          <div class="deal-week__content">
            <p class="deal-week__badge">
              <svg class="deal-week__badge-wave" width="30" height="12" viewBox="0 0 30 12" aria-hidden="true">
                <path d="M2 8c3.5 0 3.5-6 7-6s3.5 6 7 6 3.5-6 7-6 3.5 6 7 6" fill="none" stroke="currentColor" stroke-width="1.45" stroke-linecap="round"/>
              </svg>
              <span>Deal Of The Week</span>
            </p>
            <h2 id="deal-week-heading" class="deal-week__title">
              <a href="<?= h($dealWeekHref) ?>"><?= h($dealWeekTitle) ?></a>
            </h2>
            <p class="deal-week__desc">
              Our intent and our actions have always been informed by progress. We look at an impact report as a way to measure.
            </p>
            <div
              class="deal-week__timer"
              role="timer"
              aria-live="polite"
              aria-atomic="true"
              data-deal-countdown
              data-deadline="<?= h($dealWeekDeadlineIso) ?>"
            >
              <div class="deal-week__timer-cell">
                <span class="deal-week__timer-val" data-cd="days">0</span>
                <span class="deal-week__timer-unit">D</span>
              </div>
              <div class="deal-week__timer-cell">
                <span class="deal-week__timer-val" data-cd="hours">0</span>
                <span class="deal-week__timer-unit">H</span>
              </div>
              <div class="deal-week__timer-cell">
                <span class="deal-week__timer-val" data-cd="minutes">0</span>
                <span class="deal-week__timer-unit">M</span>
              </div>
              <div class="deal-week__timer-cell">
                <span class="deal-week__timer-val" data-cd="seconds">0</span>
                <span class="deal-week__timer-unit">S</span>
              </div>
            </div>
            <div class="deal-week__cta-note">
              <span class="deal-week__pct-badge" aria-hidden="true">%</span>
              <p>
                Limited time offer. The deal expires on <time datetime="<?= h($dealWeekDeadlineIso) ?>"><?= h($dealWeekDeadlineLabel) ?></time>
                <strong> HURRY UP!</strong>
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="t2-benefits-strip" aria-label="Store benefits">
      <div class="t2-benefits-strip__inner">
        <div class="t2-benefits-strip__grid">
          <article class="t2-benefits-strip__item">
            <div class="t2-benefits-strip__blob t2-benefits-strip__blob--a" aria-hidden="true">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M14 17h7v-5l-2-3h-4l-2 3v5" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M1 17h10v-9H5l-4 4v5z" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="6.5" cy="17" r="2" stroke="currentColor" stroke-width="1.65"/>
                <circle cx="17.5" cy="17" r="2" stroke="currentColor" stroke-width="1.65"/>
                <circle cx="19" cy="6" r="3.25" stroke="currentColor" stroke-width="1.5"/>
                <path d="M19 4.9V7.1M17.3 6h3.4" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/>
              </svg>
            </div>
            <h3 class="t2-benefits-strip__title">Super Fast Delivery</h3>
            <p class="t2-benefits-strip__text">Potenti dapibus lobortis convallis sociis orci sagittis ligula sollicitudin.</p>
          </article>
          <article class="t2-benefits-strip__item">
            <div class="t2-benefits-strip__blob t2-benefits-strip__blob--b" aria-hidden="true">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 10h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10z" stroke="currentColor" stroke-width="1.65" stroke-linejoin="round"/>
                <path d="M8 10V8a4 4 0 0 1 8 0v2" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M19 13.5a5.5 5.5 0 1 1-2-4.2" stroke="currentColor" stroke-width="1.65" stroke-linecap="round"/>
                <path d="M15.5 8.5l2.2 2.5 2.8-1.8" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <h3 class="t2-benefits-strip__title">30 Day Easy Return</h3>
            <p class="t2-benefits-strip__text">Potenti dapibus lobortis convallis sociis orci sagittis ligula sollicitudin.</p>
          </article>
          <article class="t2-benefits-strip__item">
            <div class="t2-benefits-strip__blob t2-benefits-strip__blob--c" aria-hidden="true">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 8V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3 10h18v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4z" stroke="currentColor" stroke-width="1.65" stroke-linejoin="round"/>
                <circle cx="15" cy="14" r="3.25" stroke="currentColor" stroke-width="1.65"/>
                <path d="M15 12v4M13 14h4" stroke="currentColor" stroke-width="1.35" stroke-linecap="round"/>
              </svg>
            </div>
            <h3 class="t2-benefits-strip__title">Security Payment</h3>
            <p class="t2-benefits-strip__text">Potenti dapibus lobortis convallis sociis orci sagittis ligula sollicitudin.</p>
          </article>
          <article class="t2-benefits-strip__item">
            <div class="t2-benefits-strip__blob t2-benefits-strip__blob--d" aria-hidden="true">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3 18v-3a9 9 0 0 1 18 0v3" stroke="currentColor" stroke-width="1.65" stroke-linecap="round"/>
                <path d="M21 19a2 2 0 0 1-2 2h-2v-5h3a2 2 0 0 1 2 2v1z" stroke="currentColor" stroke-width="1.65" stroke-linejoin="round"/>
                <path d="M3 19a2 2 0 0 0 2 2h2v-5H4a2 2 0 0 0-2 2v1z" stroke="currentColor" stroke-width="1.65" stroke-linejoin="round"/>
                <circle cx="18" cy="6.5" r="3" stroke="currentColor" stroke-width="1.5"/>
                <path d="M18 5v3M16.5 6.5h3" stroke="currentColor" stroke-width="1.25" stroke-linecap="round"/>
              </svg>
            </div>
            <h3 class="t2-benefits-strip__title">24/7 Support</h3>
            <p class="t2-benefits-strip__text">Potenti dapibus lobortis convallis sociis orci sagittis ligula sollicitudin.</p>
          </article>
        </div>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
  <script src="<?= h(luxe_theme_asset('js/hero-carousel.js')) ?>" defer></script>
  <script src="<?= h(luxe_theme_asset('js/pcard-swatches.js')) ?>" defer></script>
  <script>
    (function () {
      var root = document.querySelector("[data-deal-countdown]");
      if (!root) return;
      var iso = root.getAttribute("data-deadline");
      if (!iso) return;
      var end = new Date(iso).getTime();
      if (Number.isNaN(end)) return;

      var sel = function (u) {
        return root.querySelector('[data-cd="' + u + '"]');
      };
      var els = {
        days: sel("days"),
        hours: sel("hours"),
        minutes: sel("minutes"),
        seconds: sel("seconds"),
      };

      function tick() {
        var ms = Math.max(0, end - Date.now());
        var totalSec = Math.floor(ms / 1000);
        var days = Math.floor(totalSec / 86400);
        var hours = Math.floor((totalSec % 86400) / 3600);
        var minutes = Math.floor((totalSec % 3600) / 60);
        var seconds = totalSec % 60;
        if (els.days) els.days.textContent = String(days);
        if (els.hours) els.hours.textContent = String(hours);
        if (els.minutes) els.minutes.textContent = String(minutes);
        if (els.seconds) els.seconds.textContent = String(seconds);
      }

      tick();
      window.setInterval(tick, 1000);
    })();
  </script>
  <script>
    (function () {
      var strip = document.querySelector(".subnav-strip");
      var heroInner = document.querySelector(".hero-block-inner");
      if (!strip || !heroInner) return;
      var stripTop = 0;

      var measureTop = function () {
        strip.classList.remove("menu_fix");
        heroInner.classList.remove("menu-fix-active");
        stripTop = strip.getBoundingClientRect().top + window.scrollY;
      };

      var onScroll = function () {
        var shouldFix = window.scrollY > stripTop;
        strip.classList.toggle("menu_fix", shouldFix);
        heroInner.classList.toggle("menu-fix-active", shouldFix);
        if (shouldFix) {
          heroInner.style.setProperty("--subnav-fix-offset", strip.offsetHeight + "px");
        }
      };

      measureTop();
      window.addEventListener("scroll", onScroll, { passive: true });
      window.addEventListener("resize", function () {
        measureTop();
        onScroll();
      });
      onScroll();

      var wishlistKey = "luxe_profile_wishlist_v1";
      var getWishlist = function () {
        try {
          var list = JSON.parse(localStorage.getItem(wishlistKey) || "[]");
          return Array.isArray(list) ? list : [];
        } catch (_e) {
          return [];
        }
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
        });
      });
    })();
  </script>
</body>
</html>
