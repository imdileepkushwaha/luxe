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
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('theme-1/index.php');

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

$heroSlides = array_slice($allProducts, 0, 3);
if ($heroSlides === []) {
    $heroSlides = [
        ['name' => 'Discover Your Best Fitting Clothes', 'badge' => 'Best Selling', 'category' => 'fashion'],
        ['name' => 'Where Fashion Meets Individuality', 'badge' => 'New Arrivals', 'category' => 'fashion'],
        ['name' => 'Make Your Fashion Look More Changing', 'badge' => 'Trending', 'category' => 'fashion'],
    ];
}

$miniProducts = array_slice($allProducts, 0, 8);
$trendingProducts = array_slice($allProducts, 0, 5);
$newProducts = array_slice(array_reverse($allProducts), 0, 4);
$theme1HeaderCategories = array_keys($categories);
$theme1HeaderCompareCount = count($trendingProducts);
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = array_keys($categories);

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
        $path = '../' . ltrim($path, '/');
    }
    return $path;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LUXE Theme 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="hero-block" aria-label="Featured">
      <div class="container hero-block-inner">
        <div class="hero-row">
          <aside class="category-sidebar">
            <ul class="category-list">
              <?php foreach ($categories as $category => $count): ?>
                <li>
                  <span class="cat-ico" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M4 12c2-4 5-5 8-5s6 1 8 5v7H4v-7Z"/>
                      <path d="M12 7v12"/>
                    </svg>
                  </span>
                  <span><?= h($category) ?></span>
                  <span class="cat-chev" aria-hidden="true"></span>
                </li>
              <?php endforeach; ?>
            </ul>
            <a href="product-list.php" class="view-all-categories">View All Categories <span aria-hidden="true">→</span></a>
          </aside>

          <article class="hero-banner" id="hero-carousel" aria-roledescription="carousel" aria-label="Promotional banners">
            <div class="hero-slides-viewport">
              <div class="hero-slides-track" data-hero-track>
                <?php foreach ($heroSlides as $i => $slide): ?>
                  <div class="hero-slide" id="hero-carousel-slide-<?= $i + 1 ?>" data-hero-slide aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
                    <div class="hero-banner-inner">
                      <div class="hero-banner-copy">
                        <p class="hero-script-accent"><?= h((string) ($slide['badge'] ?? 'Featured Product')) ?></p>
                        <h1 class="hero-title"><?= h((string) ($slide['name'] ?? 'Trending Product')) ?></h1>
                        <a class="btn-hero" href="<?= h(theme1_url((array) $slide)) ?>">Shop Now</a>
                      </div>
                      <div class="hero-banner-visual">
                        <span class="hero-pink-blob" aria-hidden="true"></span>
                        <div class="hero-model-photo hero-model-photo--<?= ($i % 3) + 1 ?>" role="img" aria-label="Featured product"></div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="hero-slider-dots" role="tablist" aria-label="Choose slide">
              <?php foreach ($heroSlides as $i => $unused): ?>
                <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="hero-carousel-slide-<?= $i + 1 ?>" id="hero-carousel-tab-<?= $i + 1 ?>" data-hero-dot="<?= $i ?>" aria-label="Slide <?= $i + 1 ?> of <?= count($heroSlides) ?>"></button>
              <?php endforeach; ?>
            </div>
          </article>

          <article class="hero-side-card">
            <span class="hero-side-badge script-accent">Summer Offer</span>
            <h2 class="hero-side-title">Make Your Fashion Story Unique Every Day</h2>
            <a class="btn-hero btn-hero-sm" href="../product-list.php">
              Shop Now
              <svg class="btn-hero-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
            </a>
            <div class="hero-side-photo" role="img" aria-label="Model in denim"></div>
          </article>
        </div>
      </div>
    </section>

    <div class="container page">
      <section class="split-block">
        <article class="left-banner">
          <div class="left-banner__content">
            <h3>Live products from seller catalog</h3>
            <p>Home page now uses dynamic data from database.</p>
            <a class="btn left-banner__btn" href="../product-list.php">Shop Now <span aria-hidden="true">↗</span></a>
          </div>
          <div class="left-banner__model" role="img" aria-label="Fashion model"></div>
        </article>
        <div class="right-list">
          <?php foreach ($miniProducts as $product): ?>
            <article class="mini-product">
              <a href="<?= h(theme1_url($product)) ?>" class="mini-product-link">
                <span class="mini-save"><?= h((string) ($product['badge'] ?? 'Popular')) ?></span>
                <div class="mini-thumb peach" style="<?= h(theme1_thumb_style($product)) ?>"></div>
                <div class="mini-info">
                  <h4><?= h((string) ($product['name'] ?? 'Product')) ?></h4>
                  <div class="mini-stars" aria-hidden="true">★★★★☆</div>
                  <p><span><?= h(theme1_price($product)) ?></span>
                    <?php if (theme1_old_price($product) !== ''): ?><del><?= h(theme1_old_price($product)) ?></del><?php endif; ?>
                  </p>
                </div>
              </a>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="block block--products">
        <div class="block-head"><h2>Trending Products</h2><a href="product-list.php">View all</a></div>
        <div class="product-grid five">
          <?php foreach ($trendingProducts as $product): ?>
            <article class="pcard">
              <div class="pcard__media">
                <div class="pcard__badges">
                  <?php if (!empty($product['badge'])): ?><span class="pcard__badge pcard__badge--new"><?= h((string) $product['badge']) ?></span><?php endif; ?>
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
                <div class="thumb peach" role="img" aria-hidden="true" style="<?= h(theme1_thumb_style($product)) ?>"></div>
                <div class="pcard__overlay">
                  <a href="<?= h(theme1_url($product)) ?>" class="pcard__btn--buy">Buy Now</a>
                </div>
              </div>
              <div class="pcard__body">
                <h3 class="pcard__title"><?= h((string) ($product['name'] ?? 'Product')) ?></h3>
                <div class="pcard__price">
                  <span class="pcard__price-current"><?= h(theme1_price($product)) ?></span>
                  <?php if (theme1_old_price($product) !== ''): ?><del class="pcard__price-old"><?= h(theme1_old_price($product)) ?></del><?php endif; ?>
                </div>
                <div class="pcard__rating"><span class="pcard__reviews">(<?= (int) ($product['reviews'] ?? 0) ?> Reviews)</span></div>
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
            <article class="pcard">
              <div class="pcard__media">
                <div class="pcard__badges"><span class="pcard__badge pcard__badge--new">new</span></div>
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
                <div class="thumb cyan" role="img" aria-hidden="true" style="<?= h(theme1_thumb_style($product)) ?>"></div>
                <div class="pcard__overlay">
                  <a href="<?= h(theme1_url($product)) ?>" class="pcard__btn--buy">Buy Now</a>
                </div>
              </div>
              <div class="pcard__body">
                <h3 class="pcard__title"><?= h((string) ($product['name'] ?? 'Product')) ?></h3>
                <div class="pcard__price"><span class="pcard__price-current"><?= h(theme1_price($product)) ?></span></div>
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

    <section class="newsletter-banner" aria-labelledby="newsletter-heading">
      <div class="newsletter-banner__row">
        <div class="newsletter-banner__visual newsletter-banner__visual--left" role="img" aria-label="Customer shopping"></div>
        <div class="newsletter-banner__center">
          <h2 id="newsletter-heading" class="newsletter-banner__title">
            Get Upto <span class="newsletter-banner__accent">70%</span> Off Discount Coupon
          </h2>
          <p class="newsletter-banner__sub">By Subscribe Our Newsletter</p>
          <form class="newsletter-banner__form" action="#" method="post">
            <label class="visually-hidden" for="newsletter-email">Email</label>
            <div class="newsletter-banner__field">
              <input id="newsletter-email" type="email" name="email" placeholder="Your email" autocomplete="email" required>
              <button type="submit">Subscribe</button>
            </div>
          </form>
        </div>
        <div class="newsletter-banner__visual newsletter-banner__visual--right" role="img" aria-label="Folded apparel"></div>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
  <script src="js/hero-carousel.js" defer></script>
  <script src="js/pcard-swatches.js" defer></script>
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
