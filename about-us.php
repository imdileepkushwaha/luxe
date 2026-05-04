<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$user = auth_user($pdo);
$cartNavCount = 0;
foreach ($_SESSION['cart'] ?? [] as $ci) {
    $cartNavCount += (int) ($ci['qty'] ?? 1);
}
$allProducts = products_fetch_all($pdo);

$cmsAbout = cms_page_get($pdo, 'about', [
    'hero_kicker' => 'Our story',
    'hero_title' => 'About Us',
    'hero_lead' => 'At LUXE, we combine product curation, engineering, and design thinking to build a premium shopping experience.',
    'meta_description' => 'Learn more about LUXE, our mission, and why shoppers trust our platform.',
]);
$siteContactAbout = site_contact_bundle($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title><?= h($cmsAbout['hero_title'] !== '' ? $cmsAbout['hero_title'] : 'About Us') ?> — <?= h($siteContactAbout['brand']) ?></title>
  <meta name="description" content="<?= h($cmsAbout['meta_description']) ?>" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
  
</head>
<body class="index-page about-page">
  <div class="bg-scene"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="grid-lines"></div></div>

  <?php
  $header = [
      'user' => $user,
      'cart_count' => $cartNavCount,
      'top_text' => 'New arrivals every week',
      'top_highlight' => 'Free shipping above ₹999',
      'top_links' => [
          ['label' => "Today's Deals", 'href' => 'index.php#deals'],
          ['label' => 'Top Brands', 'href' => 'index.php#brands'],
      ],
      'menu_links' => [
          ['label' => 'Home', 'href' => 'index.php'],
          ['label' => 'Shop', 'href' => 'product-list.php'],
          ['label' => 'Collections', 'href' => 'index.php#collections'],
          ['label' => 'Trending', 'href' => 'index.php#trending'],
          ['label' => 'Deals', 'href' => 'index.php#deals'],
          ['label' => 'Brands', 'href' => 'index.php#brands'],
      ],
      'wishlist_href' => $user
          ? 'profile.php?tab=wishlist'
          : 'login.php?redirect=' . rawurlencode('profile.php?tab=wishlist'),
      'search_lead' => 'Search by product name, brand, or category — matches show below.',
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <main class="page-main">
    <div class="container">
      <div class="page-header">
        <h1><?= h($cmsAbout['hero_title'] !== '' ? $cmsAbout['hero_title'] : 'About Us') ?></h1>
        <a href="index.php" class="continue-link">← Back to home</a>
      </div>

      <div class="about-breadcrumb"><a href="index.php">Home</a><span>/</span><span><?= h($cmsAbout['hero_title'] !== '' ? $cmsAbout['hero_title'] : 'About Us') ?></span></div>

      <section class="about-hero">
        <?php if (($cmsAbout['hero_kicker'] ?? '') !== ''): ?>
          <h2><?= h($cmsAbout['hero_kicker']) ?></h2>
        <?php endif; ?>
        <p><?= nl2br(h($cmsAbout['hero_lead'])) ?></p>
        <div class="about-stats">
          <div class="about-stat"><strong>6+ Years</strong><span>eCommerce excellence</span></div>
          <div class="about-stat"><strong>120k+</strong><span>Happy shoppers served</span></div>
          <div class="about-stat"><strong>99.9%</strong><span>Platform checkout uptime</span></div>
        </div>
      </section>

      <div class="cart-layout">
        <section class="cart-items-col">
          <?php if (trim((string) $cmsAbout['body_html']) !== ''): ?>
            <article class="about-panel about-panel--cms">
              <?= $cmsAbout['body_html'] ?>
            </article>
          <?php else: ?>
          <article class="about-panel">
            <h3>Years of eCommerce Excellence</h3>
            <p style="margin:0;">For years, LUXE has helped customers discover trusted products with transparent pricing, fast checkout, and reliable delivery. Our approach is user-first, data-driven, and built for long-term growth.</p>
          </article>
          <article class="about-panel">
            <h3>A Legacy of Digital Commerce Innovation</h3>
            <div class="about-grid-3">
              <div class="about-pill-card">
                <h4>Scalable Storefronts</h4>
                <p>Built for growth with performance-focused architecture and responsive UI.</p>
              </div>
              <div class="about-pill-card">
                <h4>Seller Trust Layer</h4>
                <p>Verified sellers, moderation flow, and safer product quality controls.</p>
              </div>
              <div class="about-pill-card">
                <h4>Experience First</h4>
                <p>Smooth discovery, quick filters, smart cart, and clear post-order support.</p>
              </div>
            </div>
          </article>
          <article class="about-panel">
            <h3>Our Team</h3>
            <p style="margin:0 0 10px;">We are a passionate team of developers, designers, and operations experts turning commerce ideas into delightful shopping journeys.</p>
            <div class="about-team-grid">
              <div class="about-team-card"><strong>Osiris Nash</strong><span>Chief Executive Officer</span></div>
              <div class="about-team-card"><strong>Rosa Olson</strong><span>Product & Growth Lead</span></div>
              <div class="about-team-card"><strong>Joseph Park</strong><span>Commerce Engineering Lead</span></div>
              <div class="about-team-card"><strong>Anna Baranov</strong><span>Design Systems Lead</span></div>
              <div class="about-team-card"><strong>Ellis Atkinson</strong><span>Customer Experience Lead</span></div>
              <div class="about-team-card"><strong>Noa Ruiz</strong><span>Trust & Security Lead</span></div>
            </div>
          </article>
          <article class="about-panel">
            <h3>Testimonials</h3>
            <div class="about-testimonials">
              <div class="about-quote">
                <p>"LUXE exceeded our expectations. The platform is clean, fast, and conversion-friendly from day one."</p>
                <strong>Jack Marckno — Marketing Head</strong>
              </div>
              <div class="about-quote">
                <p>"Our store redesign with LUXE improved both user experience and performance metrics within weeks."</p>
                <strong>Blaine Perez — BDE Lead</strong>
              </div>
            </div>
          </article>
          <div class="about-contact-cta">
            <p>Have a project, collaboration idea, or support query? Our team is ready to help.</p>
            <a href="contact-us.php" class="checkout-btn" style="max-width:200px;">Contact Us</a>
          </div>
          <?php endif; ?>
        </section>
        <aside class="summary-col">
          <div class="summary-card">
            <h3 class="summary-title">Quick Links</h3>
            <div class="price-rows">
              <div class="price-row"><span>Shop Products</span><a href="product-list.php">Open</a></div>
              <div class="price-row"><span>FAQ</span><a href="faq.php">Open</a></div>
              <div class="price-row"><span>Contact Support</span><a href="contact-us.php">Open</a></div>
            </div>
          </div>
        </aside>
      </div>
    </div>
  </main>

  <?php
  $footer = [
      'deals_href' => 'index.php#deals',
      'year' => '2026',
  ];
  require __DIR__ . '/includes/user_footer.php';
  ?>

  <script>
    window.__PRODUCTS__ = <?= json_encode($allProducts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
