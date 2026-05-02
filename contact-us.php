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

$cmsContact = cms_page_get($pdo, 'contact', [
    'hero_kicker' => 'We are here to help',
    'hero_title' => 'Contact Us',
    'hero_lead' => 'Share your question about orders, payments, returns, or account settings. Our support team reviews every message and responds as quickly as possible during working hours.',
    'meta_description' => 'Get in touch with LUXE support for order help, account issues, and general questions.',
]);
$siteContact = site_contact_bundle($pdo);
$contactPhoneHref = site_contact_phone_href($siteContact['phone']);

$formSent = false;
$contactName = '';
$contactEmail = '';
$contactMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactName = trim((string) ($_POST['name'] ?? ''));
    $contactEmail = trim((string) ($_POST['email'] ?? ''));
    $contactMessage = trim((string) ($_POST['message'] ?? ''));
    if ($contactName !== '' && $contactEmail !== '' && $contactMessage !== '') {
        $formSent = true;
        $contactMessage = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title><?= h($cmsContact['hero_title'] !== '' ? $cmsContact['hero_title'] : 'Contact Us') ?> — <?= h($siteContact['brand']) ?></title>
  <meta name="description" content="<?= h($cmsContact['meta_description']) ?>" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
  <style>
    .contact-hero {
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      padding: clamp(20px, 3vw, 30px);
      margin-bottom: 16px;
      background:
        radial-gradient(ellipse at top right, rgba(16, 185, 129, 0.15), transparent 50%),
        radial-gradient(ellipse at bottom left, rgba(139, 92, 246, 0.17), transparent 55%),
        var(--card);
    }
    .contact-hero h2 {
      margin: 0 0 8px;
      color: var(--white);
      font-size: clamp(1.35rem, 2.7vw, 1.85rem);
    }
    .contact-hero p {
      margin: 0;
      color: var(--text-muted);
      max-width: 66ch;
      line-height: 1.68;
    }
    .contact-card {
      border: 1px solid var(--border);
      background: var(--card);
      border-radius: var(--radius-xl);
      padding: 22px;
    }
    .contact-form { display: grid; gap: 12px; }
    .contact-form input,
    .contact-form textarea {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 10px;
      background: var(--bg3);
      color: var(--text);
      padding: 11px 12px;
      font: inherit;
    }
    .contact-form input:focus,
    .contact-form textarea:focus {
      outline: none;
      border-color: rgba(139, 92, 246, 0.6);
      box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.16);
    }
    .contact-form textarea { min-height: 140px; resize: vertical; }
    .contact-info-list {
      display: grid;
      gap: 10px;
    }
    .contact-info-item {
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 11px 12px;
      background: rgba(255, 255, 255, 0.02);
    }
    .contact-info-item span {
      display: block;
      font-size: 0.74rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--text-dim);
      margin-bottom: 4px;
    }
    .contact-info-item strong,
    .contact-info-item a {
      color: var(--text);
      font-size: 0.92rem;
    }
  </style>
</head>
<body class="index-page contact-page">
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
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
        <h1><?= h($cmsContact['hero_title'] !== '' ? $cmsContact['hero_title'] : 'Contact Us') ?></h1>
        <a href="faq.php" class="continue-link">View FAQs →</a>
      </div>

      <section class="contact-hero">
        <?php if (($cmsContact['hero_kicker'] ?? '') !== ''): ?>
          <h2><?= h($cmsContact['hero_kicker']) ?></h2>
        <?php endif; ?>
        <p><?= nl2br(h($cmsContact['hero_lead'])) ?></p>
      </section>

      <?php if ($formSent): ?>
        <div class="select-bar" style="margin-bottom:14px;border-color:rgba(16,185,129,0.45)">
          <span style="color:var(--text-muted);font-size:0.92rem">Thanks <?= h($contactName) ?>, your message has been received. Our team will connect soon.</span>
        </div>
      <?php endif; ?>

      <div class="cart-layout">
        <section class="cart-items-col">
          <div class="contact-card">
            <h3 class="saved-title" style="margin-bottom:14px">Send us a message</h3>
            <form method="post" class="contact-form">
              <input type="text" name="name" placeholder="Your name" value="<?= h($contactName) ?>" required />
              <input type="email" name="email" placeholder="Your email" value="<?= h($contactEmail) ?>" required />
              <textarea name="message" placeholder="Write your message..." required><?= h($contactMessage) ?></textarea>
              <button type="submit" class="checkout-btn" style="max-width:220px;">Send Message</button>
            </form>
          </div>
        </section>
        <aside class="summary-col">
          <div class="summary-card">
            <h3 class="summary-title">Support Desk</h3>
            <div class="contact-info-list">
              <?php if ($siteContact['email'] !== ''): ?>
                <div class="contact-info-item">
                  <span>Email</span>
                  <a href="mailto:<?= h($siteContact['email']) ?>"><?= h($siteContact['email']) ?></a>
                </div>
              <?php endif; ?>
              <?php if ($siteContact['phone'] !== ''): ?>
                <div class="contact-info-item">
                  <span>Phone</span>
                  <a href="tel:<?= h($contactPhoneHref) ?>"><?= h($siteContact['phone']) ?></a>
                </div>
              <?php endif; ?>
              <?php if ($siteContact['address'] !== ''): ?>
                <div class="contact-info-item">
                  <span>Address</span>
                  <strong><?= nl2br(h($siteContact['address'])) ?></strong>
                </div>
              <?php endif; ?>
              <?php if ($siteContact['hours'] !== ''): ?>
                <div class="contact-info-item">
                  <span>Working Hours</span>
                  <strong><?= h($siteContact['hours']) ?></strong>
                </div>
              <?php endif; ?>
              <div class="contact-info-item">
                <span>Order Support</span>
                <a href="orders.php">Open order center</a>
              </div>
              <div class="contact-info-item">
                <span>Returns &amp; Cancellations</span>
                <a href="faq.php">Read policy FAQs</a>
              </div>
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
