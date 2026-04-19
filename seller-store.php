<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

function luxe_public_product_card_image(array $p): string
{
    $raw = trim((string) ($p['image_path'] ?? ''));
    if ($raw !== '' && strcasecmp($raw, 'default') !== 0) {
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
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
$user = auth_user($pdo);
$cartNavCount = 0;
foreach ($_SESSION['cart'] ?? [] as $ci) {
    $cartNavCount += (int) ($ci['qty'] ?? 1);
}

$displayName = trim((string) ($seller['business_name'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string) ($seller['full_name'] ?? 'Seller'));
}
$logoPath = trim((string) ($seller['logo_path'] ?? ''));
$bannerPath = trim((string) ($seller['banner_path'] ?? ''));
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
    if (strlen($initials) >= 2) {
        break;
    }
}
if ($initials === '') {
    $initials = 'S';
}

$memberSince = '—';
$createdRaw = (string) ($seller['created_at'] ?? '');
if ($createdRaw !== '') {
    try {
        $memberSince = (new DateTimeImmutable($createdRaw))->format('M Y');
    } catch (Throwable) {
        $memberSince = $createdRaw;
    }
}

$pageTitle = $displayName . ' — LUXE Seller';
$productCount = count($products);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title><?= h($pageTitle) ?></title>
  <meta name="description" content="<?= h('Shop ' . $displayName . ' on LUXE — curated products from a verified seller.') ?>" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
  <link rel="stylesheet" href="css/seller-store.css" />
</head>
<body class="ss-page">

  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>

  <div class="bg-scene" aria-hidden="true">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="grid-lines"></div>
  </div>

  <nav class="navbar" id="navbar">
    <div class="nav-container">
      <a href="index.php" class="nav-logo">LUXE</a>
      <ul class="nav-links">
        <li><a href="index.php#collections">Collections</a></li>
        <li><a href="index.php#trending">Trending</a></li>
      </ul>
      <div class="nav-actions">
        <?php if ($user): ?>
        <a href="actions/logout.php" class="nav-login-btn" aria-label="Sign out">Sign Out</a>
        <?php else: ?>
        <a href="login.php" class="nav-login-btn" aria-label="Login">Sign In</a>
        <?php endif; ?>
        <a href="cart.php" class="cart-btn" aria-label="Cart">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          <span class="cart-count"><?= (int) $cartNavCount ?></span>
        </a>
      </div>
    </div>
  </nav>

  <div class="ss-breadcrumb">
    <a href="index.php">Home</a> · <span aria-current="page"><?= h($displayName) ?></span>
  </div>

  <header class="ss-hero" aria-labelledby="ssHeroTitle">
    <div
      class="ss-hero__media<?= $bannerPath === '' ? ' ss-hero__media--fallback' : '' ?>"
      <?php if ($bannerPath !== ''): ?>
        style="background-image:url('<?= h($bannerPath) ?>')"
      <?php endif; ?>
    >
    </div>
    <div class="ss-hero__noise" aria-hidden="true"></div>
    <div class="ss-hero__content">
      <div class="ss-hero__card">
        <span class="ss-hero__kicker">Verified seller</span>
        <?php if ($logoPath !== ''): ?>
          <img class="ss-hero__logo" src="<?= h($logoPath) ?>" alt="<?= h($displayName) ?> logo" width="96" height="96" />
        <?php else: ?>
          <div class="ss-hero__logo ss-hero__logo--placeholder" aria-hidden="true"><?= h($initials) ?></div>
        <?php endif; ?>
        <h1 id="ssHeroTitle" class="ss-hero__title"><?= h($displayName) ?></h1>
        <?php if ($location !== ''): ?>
          <p class="ss-hero__subtitle"><?= h($location) ?> · Partner on LUXE</p>
        <?php else: ?>
          <p class="ss-hero__subtitle">Partner on LUXE — premium selection, trusted checkout.</p>
        <?php endif; ?>
        <div class="ss-hero__stats">
          <span class="ss-stat"><strong><?= (int) $productCount ?></strong> live listings</span>
          <span class="ss-stat">Member since <strong><?= h($memberSince) ?></strong></span>
        </div>
      </div>
    </div>
  </header>

  <main class="ss-main">
    <div class="container">
      <div class="ss-details">
        <?php if ($categories !== []): ?>
        <div class="ss-detail-card">
          <h3>Categories</h3>
          <div class="ss-tags">
            <?php foreach ($categories as $c): ?>
              <span class="ss-tag"><?= h($c) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
        <div class="ss-detail-card">
          <h3>Contact</h3>
          <p><a class="ss-detail-link" href="mailto:<?= h($email) ?>"><?= h($email) ?></a></p>
        </div>
        <?php endif; ?>
        <?php
        $showRegisteredName = $sellerFullName !== '' && strcasecmp($sellerFullName, $displayName) !== 0;
        $hasStoreMeta = $showRegisteredName || $pinCode !== '';
        ?>
        <?php if ($hasStoreMeta): ?>
        <div class="ss-detail-card">
          <h3>Seller details</h3>
          <?php if ($showRegisteredName): ?>
            <p class="ss-detail-line"><span class="ss-detail-label">Name</span> <?= h($sellerFullName) ?></p>
          <?php endif; ?>
          <?php if ($pinCode !== ''): ?>
            <p class="ss-detail-line"><span class="ss-detail-label">PIN code</span> <?= h($pinCode) ?></p>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($bizAddr !== ''): ?>
        <div class="ss-detail-card">
          <h3>Business address</h3>
          <p><?= h($bizAddr) ?></p>
        </div>
        <?php endif; ?>
        <?php if ($categories === [] && $email === '' && $bizAddr === '' && !$hasStoreMeta): ?>
        <div class="ss-detail-card">
          <h3>About</h3>
          <p>This seller offers quality products on LUXE with secure payments and delivery options at checkout.</p>
        </div>
        <?php endif; ?>
      </div>

      <div class="ss-section-head">
        <div class="section-badge">Boutique</div>
        <h2>From this seller</h2>
        <p>Hand-picked listings approved for the LUXE catalog.</p>
      </div>

      <?php if ($products === []): ?>
        <div class="ss-empty">Abhi is seller ke live products catalog me nahi hain. Baad me dubara check karein.</div>
      <?php else: ?>
        <div class="ss-products-grid">
          <?php foreach ($products as $p): ?>
            <?php
            $pid = (int) $p['id'];
            $imgSrc = luxe_public_product_card_image($p);
            $badge = trim((string) ($p['badge'] ?? ''));
            $cat = (string) ($p['category'] ?? '');
            $reviews = (int) ($p['reviews'] ?? 0);
            $rating = (float) ($p['rating'] ?? 0);
            $revLabel = $reviews >= 1000 ? number_format($reviews / 1000, 1) . 'k' : (string) max(0, $reviews);
            ?>
            <article class="product-card reveal">
              <a href="product.php?id=<?= $pid ?>" class="product-card-img-link" style="text-decoration:none;display:block">
                <div class="product-card-img">
                  <img class="card-emoji" src="<?= h($imgSrc) ?>" alt="<?= h((string) $p['name']) ?>" loading="lazy" decoding="async" />
                  <?php if ($badge !== ''): ?>
                    <div class="product-card-badge<?= $badge === 'New' ? ' new' : '' ?>"><?= h($badge) ?></div>
                  <?php endif; ?>
                </div>
              </a>
              <div class="product-card-body">
                <div class="product-card-category"><?= h($cat) ?></div>
                <a href="product.php?id=<?= $pid ?>" class="product-card-name" style="text-decoration:none;color:inherit;display:block"><?= h((string) $p['name']) ?></a>
                <div class="product-card-meta">
                  <div class="product-card-price">
                    <span class="price-cur">&#8377;<?= number_format((int) $p['price']) ?></span>
                    <span class="price-orig">&#8377;<?= number_format((int) $p['original']) ?></span>
                  </div>
                  <div class="product-card-rating"><span>★</span> <?= h(number_format($rating, 1)) ?> (<?= h($revLabel) ?>)</div>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script src="script/luxe.js"></script>
</body>
</html>
