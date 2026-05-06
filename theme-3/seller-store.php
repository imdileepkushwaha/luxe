<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$sellerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$seller = $sellerId > 0 ? seller_fetch_public_profile($pdo, $sellerId) : null;

if (!$seller) {
    header('Location: ' . luxe_public_href('index.php'));
    exit;
}

$products = products_fetch_by_seller_for_store($pdo, $sellerId);
$currentUser = auth_user($pdo);

// Cart calculation
$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $item) {
    $cartCount += (int) ($item['qty'] ?? 1);
}

// User details
$userName = trim((string) (($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')));
if ($userName === '') $userName = trim((string) ($currentUser['name'] ?? 'Guest User'));
$userInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $userName) ?: 'GU', 0, 2));
$userEmail = trim((string) ($currentUser['email'] ?? ''));
$isLoggedIn = $currentUser !== null;
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('seller-store.php?id=' . $sellerId);

$theme1HeaderCategories = [];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = [];

// Helper functions for theme-1
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
        $path = luxe_public_href(ltrim($path, '/'));
    }
    return $path;
}
function theme1_thumb_style(array $p): string {
    $path = theme1_thumb_url($p);
    return $path !== '' ? "background-image:url('" . h($path) . "');background-size:cover;background-position:center;" : '';
}

// Seller details
$displayName = trim((string) ($seller['business_name'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string) ($seller['full_name'] ?? 'Seller'));
}
$logoPath = trim((string) ($seller['logo_path'] ?? ''));
if ($logoPath !== '' && !preg_match('#^(?:https?:)?//#i', $logoPath) && !str_starts_with($logoPath, '/')) {
    $logoPath = luxe_public_href(ltrim($logoPath, '/'));
}
$bannerPath = trim((string) ($seller['banner_path'] ?? ''));
if ($bannerPath !== '' && !preg_match('#^(?:https?:)?//#i', $bannerPath) && !str_starts_with($bannerPath, '/')) {
    $bannerPath = luxe_public_href(ltrim($bannerPath, '/'));
}
$city = trim((string) ($seller['city'] ?? ''));
$state = trim((string) ($seller['state'] ?? ''));
$email = trim((string) ($seller['email'] ?? ''));
$sellerFullName = trim((string) ($seller['full_name'] ?? ''));
$pinCode = trim((string) ($seller['pin_code'] ?? ''));
$bizAddr = trim((string) ($seller['business_address'] ?? ''));
$location = trim($city . ($city !== '' && $state !== '' ? ', ' : '') . $state);

$categories = array_values(array_filter(array_map('trim', explode(',', (string) ($seller['allowed_categories'] ?? '')))));
$initials = '';
$parts = preg_split('/\s+/', $displayName) ?: [];
foreach ($parts as $w) {
    $initials .= strtoupper(substr($w, 0, 1));
    if (strlen($initials) >= 2) break;
}
if ($initials === '') $initials = 'S';

$memberSince = '—';
$createdRaw = (string) ($seller['created_at'] ?? '');
if ($createdRaw !== '') {
    try {
        $memberSince = (new DateTimeImmutable($createdRaw))->format('M Y');
    } catch (Throwable $e) {
        $memberSince = $createdRaw;
    }
}

$pageTitle = $displayName . ' — LUXE Seller';
$productCount = count($products);

$sellerRating = (float)($seller['rating'] ?? 4.8);
$sellerReviewsCount = (int)($seller['reviews_count'] ?? 124);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= h($pageTitle) ?></title>
  <meta name="description" content="Shop <?= h($displayName) ?> on LUXE — curated products from a verified seller." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="t1-seller-page t2-seller-store">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <!-- Seller Hero -->
  <section class="t1-seller-hero" <?= $bannerPath !== '' ? 'style="background-image:url(\''.h($bannerPath).'\')"' : '' ?>>
    <div class="t1-seller-hero-overlay"></div>
    <div class="container t1-seller-hero-inner">
      <div class="t1-seller-info-card t2-seller-info-card" role="region" aria-label="Seller profile">
        <div class="t1-seller-logo">
          <?php if ($logoPath !== ''): ?>
            <img src="<?= h($logoPath) ?>" alt="<?= h($displayName) ?>" />
          <?php else: ?>
            <span><?= h($initials) ?></span>
          <?php endif; ?>
        </div>
        <div class="t1-seller-meta">
          <span class="t1-seller-verified">✓ Verified Seller</span>
          <h1 class="t1-seller-title"><?= h($displayName) ?></h1>
          <p class="t1-seller-subtitle">
            <?php if ($location !== ''): ?>
              <?= h($location) ?> • 
            <?php endif; ?>
            Member since <?= h($memberSince) ?>
          </p>
          <div class="t1-seller-stats">
            <div class="stat">
              <strong><?= $productCount ?></strong>
              <span>Products</span>
            </div>
            <div class="stat">
              <strong><?= h(number_format($sellerRating, 1)) ?> ★</strong>
              <span><?= h(number_format($sellerReviewsCount)) ?> Reviews</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <main class="container t1-seller-main">
    <!-- Seller Details Grid -->
    <div class="t1-seller-details-grid">
      <div class="t1-seller-detail-box">
        <h3>About the Store</h3>
        <p>This seller offers premium quality products on LUXE. Enjoy secure payments, verified items, and smooth delivery options at checkout.</p>
      </div>

      <?php if ($categories !== []): ?>
      <div class="t1-seller-detail-box">
        <h3>Categories</h3>
        <div class="t1-seller-tags">
          <?php foreach ($categories as $c): ?>
            <span class="t1-seller-tag"><?= h($c) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($email !== '' || $bizAddr !== '' || $pinCode !== ''): ?>
      <div class="t1-seller-detail-box">
        <h3>Store Information</h3>
        <?php if ($email !== ''): ?>
          <p><strong>Email:</strong> <a href="mailto:<?= h($email) ?>"><?= h($email) ?></a></p>
        <?php endif; ?>
        <?php if ($sellerFullName !== '' && strcasecmp($sellerFullName, $displayName) !== 0): ?>
          <p><strong>Owner:</strong> <?= h($sellerFullName) ?></p>
        <?php endif; ?>
        <?php if ($bizAddr !== ''): ?>
          <p><strong>Address:</strong> <?= h($bizAddr) ?></p>
        <?php endif; ?>
        <?php if ($pinCode !== ''): ?>
          <p><strong>PIN:</strong> <?= h($pinCode) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Products Grid -->
    <section class="block block--products">
      <div class="block-head t2-seller-block-head">
        <h2>Products from <?= h($displayName) ?></h2>
      </div>
      <?php if ($products === []): ?>
        <p class="t1-empty-msg">No products available from this seller at the moment.</p>
      <?php else: ?>
        <div class="product-grid four">
          <?php foreach ($products as $p): ?>
            <?php
              $pcardRating = (float) ($p['rating'] ?? 0);
              $pcardSavePct = luxe_pcard_save_percent((array) $p);
              $pcardCat = strtoupper(trim((string) ($p['category'] ?? 'General')));
            ?>
            <?php $thumb = theme1_thumb_url((array)$p); ?>
            <article class="pcard pcard--v3">
              <div class="pcard__image-wrap">
                <a href="<?= h(theme1_url((array)$p)) ?>" class="pcard__img-link">
                  <div class="pcard__img" role="img" aria-label="<?= h((string)($p['name'] ?? 'Product')) ?>" style="background-image:url('<?= h($thumb) ?>');"></div>
                </a>
                <?php if (!empty($p['badge'])): ?>
                  <?php $badgeType = strtolower(trim((string)$p['badge'])); ?>
                  <span class="pcard__off-badge pcard__off-badge--<?= h($badgeType) ?>"><?= h(strtoupper((string) $p['badge'])) ?></span>
                <?php elseif ($pcardSavePct !== null): ?>
                  <span class="pcard__off-badge"><?= $pcardSavePct ?>% OFF</span>
                <?php endif; ?>
                <div class="pcard__side-actions">
                  <button
                    type="button"
                    class="pcard__side-btn pcard__wish-toggle"
                    aria-label="Toggle wishlist"
                    data-wishlist-btn="1"
                    data-id="<?= (int) ($p['id'] ?? 0) ?>"
                    data-name="<?= h((string) ($p['name'] ?? 'Product')) ?>"
                    data-emoji="<?= h((string) ($p['emoji'] ?? '🛍')) ?>"
                    data-price="<?= (int) ($p['price'] ?? 0) ?>"
                    data-orig="<?= (int) ($p['original'] ?? 0) ?>"
                    data-image="<?= h($thumb) ?>"
                  ><svg class="heart-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button>
                  <a href="<?= h(theme1_url((array)$p)) ?>" class="pcard__side-btn" aria-label="Quick view">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </a>
                </div>
                <div class="pcard__cart-overlay">
                  <a href="<?= h(theme1_url((array)$p)) ?>" class="pcard__cart-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    Add To Cart
                  </a>
                </div>
              </div>
              <div class="pcard__body">
                <div class="pcard__rating">
                  <?= luxe_pcard_stars_html($pcardRating) ?>
                  <span class="pcard__reviews-count">(<?= (int) ($p['reviews'] ?? 0) ?>)</span>
                </div>
                <h3 class="pcard__title">
                  <a href="<?= h(theme1_url((array)$p)) ?>"><?= h((string) ($p['name'] ?? 'Product')) ?></a>
                </h3>
                <div class="pcard__price-row">
                  <?php if (theme1_old_price((array)$p) !== ''): ?><del class="pcard__price-old"><?= h(theme1_old_price((array)$p)) ?></del><?php endif; ?>
                  <span class="pcard__price-current"><?= h(theme1_price((array)$p)) ?></span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
  
  <div id="toastContainer" class="t2-seller-toast-host" aria-live="polite"></div>

  <script>
    function showToast(msg) {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      toast.className = 't2-seller-toast';
      toast.textContent = msg;

      container.appendChild(toast);

      requestAnimationFrame(function () {
        toast.classList.add('is-visible');
      });

      setTimeout(function () {
        toast.classList.remove('is-visible');
        setTimeout(function () { toast.remove(); }, 300);
      }, 3000);
    }

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
