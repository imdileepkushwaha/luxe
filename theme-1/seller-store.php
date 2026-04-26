<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function luxe_public_product_card_image(array $p): string
{
    $raw = trim((string) ($p['image_path'] ?? ''));
    if ($raw !== '' && strcasecmp($raw, 'default') !== 0) {
        if (!preg_match('#^(?:https?:)?//#i', $raw) && !str_starts_with($raw, '/')) {
            return '../' . ltrim($raw, '/');
        }
        return $raw;
    }
    return 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=600&q=80';
}

$pdo = db();
$sellerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$seller = $sellerId > 0 ? seller_fetch_public_profile($pdo, $sellerId) : null;

if (!$seller) {
    header('Location: index.php');
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
$theme1LoginHref = '../login.php?redirect=' . rawurlencode('theme-1/seller-store.php?id=' . $sellerId);

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
    if (!preg_match('#^(?:https?:)?//#i', $path) && !str_starts_with($path, '/')) $path = '../' . ltrim($path, '/');
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
    $logoPath = '../' . ltrim($logoPath, '/');
}
$bannerPath = trim((string) ($seller['banner_path'] ?? ''));
if ($bannerPath !== '' && !preg_match('#^(?:https?:)?//#i', $bannerPath) && !str_starts_with($bannerPath, '/')) {
    $bannerPath = '../' . ltrim($bannerPath, '/');
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
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700&family=Jost:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body class="t1-seller-page">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <!-- Seller Hero -->
  <section class="t1-seller-hero" <?= $bannerPath !== '' ? 'style="background-image:url(\''.h($bannerPath).'\')"' : '' ?>>
    <div class="t1-seller-hero-overlay"></div>
    <div class="container t1-seller-hero-inner">
      <div class="t1-seller-info-card">
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
      <div class="block-head" style="margin-bottom:24px;">
        <h2>Products from <?= h($displayName) ?></h2>
      </div>
      <?php if ($products === []): ?>
        <p class="t1-empty-msg">No products available from this seller at the moment.</p>
      <?php else: ?>
        <div class="product-grid four">
          <?php foreach ($products as $p): ?>
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
                <div class="pcard__rating"><span class="pcard__reviews">(<?= (int) ($p['reviews'] ?? 0) ?> Reviews)</span></div>
                <div class="pcard__actions">
                  <a class="pcard__btn pcard__btn--view" href="<?= h(theme1_url((array)$p)) ?>">View</a>
                  <a class="pcard__btn pcard__btn--cart" href="<?= h(theme1_url((array)$p)) ?>">Add to Cart</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
  
  <div id="toastContainer" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:12px;"></div>

  <script>
    function showToast(msg) {
      const container = document.getElementById('toastContainer');
      const toast = document.createElement('div');
      toast.style.background = 'rgba(15, 23, 42, 0.9)';
      toast.style.color = '#fff';
      toast.style.padding = '12px 20px';
      toast.style.borderRadius = '8px';
      toast.style.fontSize = '14px';
      toast.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
      toast.style.backdropFilter = 'blur(10px)';
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(20px)';
      toast.style.transition = 'all 0.3s ease';
      toast.textContent = msg;

      container.appendChild(toast);
      
      requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
      });

      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
        setTimeout(() => toast.remove(), 300);
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
        btn.textContent = active ? "♥" : "♡";
        btn.setAttribute("aria-label", active ? "Remove from wishlist" : "Add to wishlist");
        if(active) {
            btn.style.color = "#ec4899";
            btn.style.borderColor = "#ec4899";
            btn.style.background = "#fdf2f8";
        } else {
            btn.style.color = "#475569";
            btn.style.borderColor = "transparent";
            btn.style.background = "rgba(255, 255, 255, 0.9)";
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
