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
$dealWeekImgUrl = luxe_theme_asset('images/deal-1.png');
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1">
  <title>Home — <?= h($t2HomeBrand) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Montserrat:wght@500;600;700;800&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
</head>
<body class="t3-page-home">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="hero-block" aria-label="Featured">
      <div class="container hero-block-inner">
        <article class="hero-banner-v3" id="hero-carousel" aria-roledescription="carousel" aria-label="Promotional banners">
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
                <div class="hero-slide-v3" id="hero-carousel-slide-<?= $i + 1 ?>" data-hero-slide aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
                  <div class="hero-banner-inner-v3">
                    <div class="hero-banner-copy-v3">
                      <p class="hero-kicker-v3">Best for your categories</p>
                      <h1 class="hero-title-v3">
                        Exclusive Collection <br>
                        in <span class="highlight">Our Online</span> Store
                      </h1>
                      <p class="hero-lede-v3">
                        Discover our exclusive collection available only in our online store. Shop now for unique and premium items that you won't find anywhere else.
                      </p>
                      
                      <div class="hero-actions-v3">
                        <a class="btn-shop-now-v3" href="<?= h($heroHref) ?>">Shop Now</a>
                      </div>
                    </div>
                    
                    <div class="hero-banner-visual-v3">
                      <div class="image-wrapper">
                        <?php if ($thumbStyle !== ''): ?>
                          <div class="hero-img-v3" style="<?= $thumbStyle ?>" role="img" aria-label="<?= h($slideTitle) ?>"></div>
                        <?php else: ?>
                          <div class="hero-img-v3" style="background-image: url('<?= luxe_theme_asset('images/image-slide-1.png') ?>');" role="img" aria-label="<?= h($slideTitle) ?>"></div>
                        <?php endif; ?>
                        <div class="image-border-v3"></div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="hero-dots-v3">
            <?php foreach ($heroSlides as $i => $unused): ?>
              <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" data-hero-dot="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
            <?php endforeach; ?>
          </div>
        </article>
      </div>
    </section>

    <section class="category-marquee-v3">
      <div class="marquee-content-v3">
        <div class="marquee-item-v3">JACKETS <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">JEANS <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">BLAZER <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">MEN <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">JACKETS <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">WOMEN <span class="star-icon">✦</span></div>
        <!-- Duplicate for infinite scroll -->
        <div class="marquee-item-v3">JACKETS <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">JEANS <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">BLAZER <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">MEN <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">JACKETS <span class="star-icon">✦</span></div>
        <div class="marquee-item-v3">WOMEN <span class="star-icon">✦</span></div>
      </div>
    </section>

    <section class="benefits-section-v3">
      <div class="container">
        <div class="benefits-box-v3">
          <div class="benefit-item-v3">
            <div class="benefit-icon-v3">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
            </div>
            <div class="benefit-info-v3">
              <h3>Free Shipping</h3>
              <p>You get your items delivered without any extra cost.</p>
            </div>
          </div>
          
          <div class="benefit-divider-v3"></div>
          
          <div class="benefit-item-v3">
            <div class="benefit-icon-v3">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <div class="benefit-info-v3">
              <h3>Great Support 24/7</h3>
              <p>Our customer support team is available around the clock.</p>
            </div>
          </div>
          
          <div class="benefit-divider-v3"></div>
          
          <div class="benefit-item-v3">
            <div class="benefit-icon-v3">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><polyline points="17 11 19 13 23 9"></polyline></svg>
            </div>
            <div class="benefit-info-v3">
              <h3>Return Available</h3>
              <p>Making it easy to return any items if you're not satisfied.</p>
            </div>
          </div>
          
          <div class="benefit-divider-v3"></div>
          
          <div class="benefit-item-v3">
            <div class="benefit-icon-v3">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
            </div>
            <div class="benefit-info-v3">
              <h3>Secure Payment</h3>
              <p>Shop with confidence knowing that our secure payment.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="top-categories-v3" id="t3-app-categories">
      <div class="container">
        <div class="top-cat-header-v3">
          <div class="top-cat-title-wrap">
            <p class="top-cat-kicker"><span class="star-mini">✦</span> Categories</p>
            <h2 class="top-cat-title">Browse Top Category</h2>
          </div>
          <div class="top-cat-nav">
            <button class="nav-btn prev" aria-label="Previous">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </button>
            <button class="nav-btn next" aria-label="Next">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
            </button>
          </div>
        </div>
        
        <div class="top-cat-grid-v3">
          <div class="top-cat-card-v3">
            <div class="cat-img-wrap">
              <img src="https://images.unsplash.com/photo-1591047139829-d91aecb6caea?q=80&w=400" alt="Man Shirts">
              <div class="cat-pill">Man Shirts</div>
            </div>
          </div>
          
          <div class="top-cat-card-v3">
            <div class="cat-img-wrap">
              <img src="https://images.unsplash.com/photo-1542272604-787c3835535d?q=80&w=400" alt="Denim Jeans">
              <div class="cat-pill">Denim Jeans</div>
            </div>
          </div>
          
          <div class="top-cat-card-v3">
            <div class="cat-img-wrap">
              <img src="https://images.unsplash.com/photo-1594932224828-b4b05a832fe2?q=80&w=400" alt="Casual Suit">
              <div class="cat-pill">Casual Suit</div>
            </div>
          </div>
          
          <div class="top-cat-card-v3">
            <div class="cat-img-wrap">
              <img src="https://images.unsplash.com/photo-1496747611176-843222e1e57c?q=80&w=400" alt="Summer Dress">
              <div class="cat-pill">Summer Dress</div>
            </div>
          </div>
          
          <div class="top-cat-card-v3">
            <div class="cat-img-wrap">
              <img src="https://images.unsplash.com/photo-1434389677669-e08b4cac3105?q=80&w=400" alt="Sweaters">
              <div class="cat-pill">Sweaters</div>
            </div>
          </div>
          
          <div class="top-cat-card-v3">
            <div class="cat-img-wrap">
              <img src="https://images.unsplash.com/photo-1551488831-00ddcb6c6bd3?q=80&w=400" alt="Jackets">
              <div class="cat-pill">Jackets</div>
            </div>
          </div>

          <div class="top-cat-card-v3">
            <div class="cat-img-wrap">
              <img src="https://images.unsplash.com/photo-1496747611176-843222e1e57c?q=80&w=400" alt="Summer Dress">
              <div class="cat-pill">Summer Dress</div>
            </div>
          </div>
          
          <div class="top-cat-card-v3">
            <div class="cat-img-wrap">
              <img src="https://images.unsplash.com/photo-1434389677669-e08b4cac3105?q=80&w=400" alt="Sweaters">
              <div class="cat-pill">Sweaters</div>
            </div>
          </div>
          
          <div class="top-cat-card-v3">
            <div class="cat-img-wrap">
              <img src="https://images.unsplash.com/photo-1551488831-00ddcb6c6bd3?q=80&w=400" alt="Jackets">
              <div class="cat-pill">Jackets</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Promo Banner Section V3 -->
    <section class="promo-banner-v3">
      <div class="container">
        <div class="promo-banner-grid-v3">
          <!-- Card 1 -->
          <div class="banner-item style-one bg-one">
                                <div class="shape shape-one"><span><img src="theme-3/images/discount.png" alt="shape"></span></div>
                                <div class="shape shape-two"><span><img src="theme-3/images/line.png" alt="shape"></span></div>
                                <div class="banner-img"><img src="theme-3/images/banner-1.png" alt="banner image"></div>
                                <div class="banner-content">
                                    <span>UP TO <span class="off">50%</span></span>
                                    <h4>Exclusive Kids &amp; Adults Summer Outfits</h4>
                                    <a href="shops.html" class="theme-btn style-one">Shop Now</a>
                                </div>
                            </div>

          <!-- Card 2 -->
          <div class="banner-item style-one bg-two">
                                <div class="shape shape-one"><span><img src="theme-3/images/discount.png" alt="shape"></span></div>
                                <div class="shape shape-two"><span><img src="theme-3/images/line.png" alt="shape"></span></div>
                                <div class="banner-img"><img src="theme-3/images/banner-2.png" alt="banner image"></div>
                                <div class="banner-content">
                                    <span>UP TO <span class="off">70%</span></span>
                                    <h4>Exclusive Kids &amp; Adults Summer Outfits</h4>
                                    <a href="shops.html" class="theme-btn style-one">Shop Now</a>
                                </div>
                            </div>
        </div>
      </div>
    </section>

    <div class="container page">
      <section class="split-block split-block--listed" aria-labelledby="split-listed-heading">
        <div class="split-block-main" style="width: 100%;">
          <div class="split-block-head">
            <h2 id="split-listed-heading" class="split-block-title">Top listed items</h2>
            <a class="split-block-viewall" href="<?= h(luxe_public_href('product-list.php')) ?>">View All →</a>
          </div>
          <?php if ($splitListedProducts === []): ?>
            <p class="split-block-empty">No products yet. Add items in admin to show this grid.</p>
          <?php else: ?>
            <div class="split-block-grid">
              <?php foreach (array_slice($splitListedProducts, 0, 4) as $product): ?>
                <?php
                $purl = theme1_url($product);
                $thumbUrl = theme1_thumb_url($product);
                $filledStars = theme1_split_star_filled($product);
                $reviewN = theme1_split_review_count($product);
                $hasOldPrice = theme1_old_price($product) !== '';
                ?>
                <article class="split-product-v2">
                  <a href="<?= h($purl) ?>" class="sp-link">
                    <div class="sp-image-wrap">
                      <?php if ($thumbUrl !== ''): ?>
                        <img src="<?= h($thumbUrl) ?>" alt="<?= h((string) ($product['name'] ?? '')) ?>" class="sp-image" loading="lazy">
                      <?php else: ?>
                        <div class="sp-fallback">🛍</div>
                      <?php endif; ?>
                    </div>
                    <div class="sp-info-box">
                      <div class="sp-rating-row">
                        <div class="sp-rating">
                          <span class="sp-stars" aria-hidden="true">
                            <?php for ($si = 1; $si <= 5; $si++): ?>
                              <span class="<?= $si <= $filledStars ? 'sp-star sp-star--full' : 'sp-star sp-star--empty' ?>">★</span>
                            <?php endfor; ?>
                          </span>
                          <span class="sp-review-count">(<?= $reviewN ?>)</span>
                        </div>
                        <?php if ($hasOldPrice): ?>
                          <del class="sp-old-price"><?= h(theme1_old_price($product)) ?></del>
                        <?php endif; ?>
                      </div>
                      <div class="sp-title-row">
                        <h3 class="sp-title"><?= h((string) ($product['name'] ?? 'Product')) ?></h3>
                        <span class="sp-current-price"><?= h(theme1_price($product)) ?></span>
                      </div>
                    </div>
                  </a>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
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
            <article class="pcard pcard--v3">
              <div class="pcard__image-wrap">
                <a href="<?= h(theme1_url($product)) ?>" class="pcard__img-link">
                  <div class="pcard__img" role="img" aria-label="<?= h((string)($product['name'] ?? 'Product')) ?>" style="<?= h(theme1_thumb_style($product)) ?>"></div>
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
                    data-image="<?= h(theme1_thumb_url($product)) ?>"
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
            <article class="pcard pcard--v3">
              <div class="pcard__image-wrap">
                <a href="<?= h(theme1_url($product)) ?>" class="pcard__img-link">
                  <div class="pcard__img" role="img" aria-label="<?= h((string)($product['name'] ?? 'Product')) ?>" style="<?= h(theme1_thumb_style($product)) ?>"></div>
                </a>
                <?php if (!empty($product['badge'])): ?>
                  <?php $badgeType = strtolower(trim((string)$product['badge'])); ?>
                  <span class="pcard__off-badge pcard__off-badge--<?= h($badgeType) ?>"><?= h(strtoupper((string) $product['badge'])) ?></span>
                <?php elseif ($pcardSavePct !== null): ?>
                  <span class="pcard__off-badge"><?= $pcardSavePct ?>% OFF</span>
                <?php else: ?>
                  <span class="pcard__off-badge pcard__off-badge--new">NEW</span>
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
                    data-image="<?= h(theme1_thumb_url($product)) ?>"
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
        </div>
      </section>
    </div>

    <section class="deal-week" aria-labelledby="deal-week-heading">
      <div class="deal-week__inner container">
        <div class="deal-week__grid">
          <div class="deal-week__content">
            <p class="deal-week__badge">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82zM7 9a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/></svg>
              <span>Deal of the Week</span>
            </p>
            <h2 id="deal-week-heading" class="deal-week__title">
              Hurry Up! Offer ends in. Get<br>
              <span class="highlight-red">UP TO 80% OFF</span>
            </h2>
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
                <span class="deal-week__timer-unit">day</span>
              </div>
              <div class="deal-week__timer-cell">
                <span class="deal-week__timer-val" data-cd="hours">0</span>
                <span class="deal-week__timer-unit">hour</span>
              </div>
              <div class="deal-week__timer-cell">
                <span class="deal-week__timer-val" data-cd="minutes">0</span>
                <span class="deal-week__timer-unit">minute</span>
              </div>
              <div class="deal-week__timer-cell">
                <span class="deal-week__timer-val" data-cd="seconds">0</span>
                <span class="deal-week__timer-unit">second</span>
              </div>
            </div>
            <a href="<?= h($dealWeekHref) ?>" class="deal-week__shop-btn">Shop Now</a>
          </div>
          <div class="deal-week__visual">
            <img src="<?= h($dealWeekImgUrl) ?>" alt="Deal of the week" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
    </section>

    <section class="testimonial-section">
      <div class="testimonial-wrapper">
        <div class="testimonial__visual">
          <div class="testimonial__visual-bg"></div>
          <!-- Placeholder image; swap with the exact model image later -->
          <img src="<?= h(luxe_theme_asset('images/testimonial-img1.png')) ?>" alt="Our Client" class="testimonial__model-img" loading="lazy" decoding="async">
        </div>
        <div class="testimonial__content">
          <div class="testimonial__card">
            <p class="testimonial__badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l2.4 7.6L22 12l-7.6 2.4L12 22l-2.4-7.6L2 12l7.6-2.4L12 2z"/></svg>
              <span>Testimonial</span>
            </p>
            <h2 class="testimonial__title">What's Our Clients Say</h2>
            
            <div class="testimonial__quote-box">
              <button type="button" class="testimonial__nav-btn testimonial__nav-btn--prev" aria-label="Previous testimonial">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
              </button>
              <button type="button" class="testimonial__nav-btn testimonial__nav-btn--next" aria-label="Next testimonial">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>
              
              <div class="testimonial__slider" id="testimonialSlider">
                <!-- Slide 1 -->
                <div class="testimonial__slide active">
                  <div class="testimonial__stars" aria-hidden="true">★★★★★</div>
                  <p class="testimonial__text">
                    I recently ordered a few items from Fashionista Boutique, and I couldn't be happier with my purchase! The quality of the clothes is outstanding, and the fit is perfect. My order arrived promptly, beautifully packaged.
                  </p>
                  <div class="testimonial__author">
                    <img src="https://i.pravatar.cc/150?u=rhodes" alt="Rhodes Jhon" class="testimonial__avatar" loading="lazy">
                    <div class="testimonial__author-info">
                      <strong>Rhodes Jhon</strong>
                      <span>Manager and CEO</span>
                    </div>
                  </div>
                </div>

                <!-- Slide 2 -->
                <div class="testimonial__slide">
                  <div class="testimonial__stars" aria-hidden="true">★★★★★</div>
                  <p class="testimonial__text">
                    The customer service is phenomenal. They answered all my questions and helped me find the perfect dress for my event. The shipping was extremely fast. I will definitely be shopping here again!
                  </p>
                  <div class="testimonial__author">
                    <img src="https://i.pravatar.cc/150?u=sarah" alt="Sarah Miller" class="testimonial__avatar" loading="lazy">
                    <div class="testimonial__author-info">
                      <strong>Sarah Miller</strong>
                      <span>Fashion Blogger</span>
                    </div>
                  </div>
                </div>

                <!-- Slide 3 -->
                <div class="testimonial__slide">
                  <div class="testimonial__stars" aria-hidden="true">★★★★☆</div>
                  <p class="testimonial__text">
                    Beautiful designs and great fabrics. The only reason it's not 5 stars is because one item was out of stock, but the refund was processed immediately. Highly recommend their exclusive collections.
                  </p>
                  <div class="testimonial__author">
                    <img src="https://i.pravatar.cc/150?u=david" alt="David Lee" class="testimonial__avatar" loading="lazy">
                    <div class="testimonial__author-info">
                      <strong>David Lee</strong>
                      <span>Creative Director</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="newsletter-section">
                <div class="container">
                    <!--=== Newsletter Wrapper  ===-->
                    <div class="newsletter-wrapper">
                        <div class="newsletter-shape pattern-one"><span><img src="<?= h(luxe_theme_asset('images/pattern-1.png')) ?>" alt="Pattern Shape" loading="lazy" decoding="async"></span></div>
                        <div class="newsletter-shape pattern-two"><span><img src="<?= h(luxe_theme_asset('images/pattern-2.png')) ?>" alt="Pattern Shape" loading="lazy" decoding="async"></span></div>
                        <div class="newsletter-shape shape-one"><span><img src="<?= h(luxe_theme_asset('images/shape-1.png')) ?>" alt="Shape" loading="lazy" decoding="async"></span></div>
                        <div class="newsletter-inner-box">
                            
                                <div class="newsletter-content-box">
                                    <span class="sub-text">Our Newsletter</span>
                                    <h3>Get weekly update. Sign up and get up to <span>20% off</span> your first purchase</h3>
                                    <form>
                                        <div class="form-group">
                                            <input type="email" class="form_control" placeholder="Write your Email Address" name="email">
                                            <button class="theme-btn style-one">Subscribe</button>
                                        </div>
                                    </form>
                                </div>
                            
                                <div class="newsletter-image">
                                    <img src="<?= h(luxe_theme_asset('images/newsletter-1.png')) ?>" alt="Newsletter Image" loading="lazy" decoding="async">
                                </div>
                            
                        </div>
                    </div>
                </div>
            </section>

  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
  <script src="<?= h(luxe_theme_asset('js/hero-carousel.js')) ?>" defer></script>
  <script src="<?= h(luxe_theme_asset('js/pcard-swatches.js')) ?>" defer></script>
  <script src="<?= h(luxe_theme_asset('js/wishlist.js')) ?>" defer></script>
  <script>
    (function() {
      const slides = document.querySelectorAll('#testimonialSlider .testimonial__slide');
      const btnNext = document.querySelector('.testimonial__nav-btn--next');
      const btnPrev = document.querySelector('.testimonial__nav-btn--prev');
      let currentIndex = 0;

      if (slides.length > 0 && btnNext && btnPrev) {
        btnNext.addEventListener('click', function() {
          slides[currentIndex].classList.remove('active');
          currentIndex = (currentIndex + 1) % slides.length;
          slides[currentIndex].classList.add('active');
        });

        btnPrev.addEventListener('click', function() {
          slides[currentIndex].classList.remove('active');
          currentIndex = (currentIndex - 1 + slides.length) % slides.length;
          slides[currentIndex].classList.add('active');
        });
      }
    })();
  </script>
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

    })();
  </script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
  <script>
    $(document).ready(function(){
      $(".top-cat-grid-v3").slick({
        slidesToShow: 6,
        slidesToScroll: 1,
        infinite: true,
        autoplay: true,
        autoplaySpeed: 3000,
        pauseOnHover: true,
        arrows: true,
        prevArrow: $(".top-cat-nav .prev"),
        nextArrow: $(".top-cat-nav .next"),
        responsive: [
          {
            breakpoint: 1200,
            settings: {
              slidesToShow: 4
            }
          },
          {
            breakpoint: 1024,
            settings: {
              slidesToShow: 3
            }
          },
          {
            breakpoint: 768,
            settings: {
              slidesToShow: 2
            }
          },
          {
            breakpoint: 480,
            settings: {
              slidesToShow: 1
            }
          }
        ]
      });
    });
  </script>
</body>
</html>
