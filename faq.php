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

$cmsFaqPage = cms_page_get($pdo, 'faq', [
    'hero_kicker' => 'Help centre',
    'hero_title' => 'Frequently Asked Questions',
    'hero_lead' => 'Find answers to the most frequently asked questions about our services and products. If you have any additional queries, please contact our support team.',
    'meta_description' => 'Frequently asked questions about orders, shipping, returns, and account support on LUXE.',
]);
$siteContactFaq = site_contact_bundle($pdo);
$contactPhoneHrefFaq = site_contact_phone_href($siteContactFaq['phone']);
$cmsFaqItems = cms_faqs_all($pdo, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title><?= h($cmsFaqPage['hero_title'] !== '' ? $cmsFaqPage['hero_title'] : 'FAQ') ?> — <?= h($siteContactFaq['brand']) ?></title>
  <meta name="description" content="<?= h($cmsFaqPage['meta_description']) ?>" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
  <style>
    .faq-breadcrumb {
      margin: 8px 0 14px;
      color: var(--text-dim);
      font-size: 0.82rem;
    }
    .faq-breadcrumb a { color: var(--primary-light); }
    .faq-breadcrumb span { margin: 0 6px; }
    .faq-hero {
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      padding: clamp(20px, 2.7vw, 30px);
      margin-bottom: 16px;
      background:
        radial-gradient(ellipse at top right, rgba(14, 165, 233, 0.14), transparent 52%),
        radial-gradient(ellipse at bottom left, rgba(139, 92, 246, 0.16), transparent 55%),
        var(--card);
    }
    .faq-hero h2 {
      margin: 0 0 8px;
      color: var(--white);
      font-size: clamp(1.35rem, 2.7vw, 1.8rem);
    }
    .faq-hero p {
      margin: 0;
      color: var(--text-muted);
      line-height: 1.65;
      max-width: 65ch;
    }
    .faq-contact-strip {
      margin-top: 14px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .faq-contact-chip {
      border: 1px solid rgba(139, 92, 246, 0.36);
      background: rgba(139, 92, 246, 0.12);
      border-radius: 999px;
      padding: 8px 12px;
      color: var(--text);
      font-size: 0.85rem;
    }
    .faq-contact-chip a { color: var(--white); }
    .faq-list { display: grid; gap: 12px; }
    .faq-item {
      border: 1px solid var(--border);
      border-radius: 14px;
      background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0)) , var(--card);
      padding: 0;
      overflow: hidden;
      transition: border-color 0.2s ease, transform 0.2s ease;
    }
    .faq-item:hover {
      border-color: rgba(139, 92, 246, 0.4);
      transform: translateY(-1px);
    }
    .faq-item summary {
      list-style: none;
      cursor: pointer;
      padding: 14px 16px;
      font-weight: 700;
      color: var(--text);
      position: relative;
      padding-right: 44px;
    }
    .faq-item summary::after {
      content: "+";
      position: absolute;
      right: 14px;
      top: 11px;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      border: 1px solid var(--border);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--primary-light);
      font-weight: 700;
    }
    .faq-item[open] summary::after { content: "-"; }
    .faq-item__content {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.34s ease;
    }
    .faq-item__body {
      margin: 0;
      padding: 0 16px 14px;
      color: var(--text-muted);
      line-height: 1.65;
    }
    .faq-helpful {
      margin-top: 12px;
      display: inline-flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      font-size: 0.78rem;
      color: var(--text-muted);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 6px 8px 6px 12px;
      background: rgba(255, 255, 255, 0.03);
    }
    .faq-helpful button {
      border: 1px solid rgba(139, 92, 246, 0.28);
      border-radius: 999px;
      background: rgba(139, 92, 246, 0.08);
      color: #e9ddff;
      padding: 5px 12px;
      font-family: inherit;
      font-size: 0.76rem;
      font-weight: 600;
      cursor: pointer;
      transition:
        background 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease;
    }
    .faq-helpful button:hover {
      border-color: rgba(236, 72, 153, 0.45);
      background: linear-gradient(135deg, rgba(139, 92, 246, 0.22), rgba(236, 72, 153, 0.2));
      color: var(--white);
      transform: translateY(-1px);
    }
    :root[data-theme="light"] .faq-helpful {
      background: rgba(255, 255, 255, 0.9);
      border-color: rgba(15, 23, 42, 0.12);
      color: #475569;
    }
    :root[data-theme="light"] .faq-helpful button {
      background: rgba(139, 92, 246, 0.12);
      border-color: rgba(139, 92, 246, 0.32);
      color: #5b21b6;
    }
    :root[data-theme="light"] .faq-helpful button:hover {
      color: #ffffff;
    }
  </style>
</head>
<body class="index-page faq-page">
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
        <h1><?= h($cmsFaqPage['hero_title'] !== '' ? $cmsFaqPage['hero_title'] : 'Frequently Asked Questions') ?></h1>
        <a href="contact-us.php" class="continue-link">Need help? Contact us →</a>
      </div>

      <div class="faq-breadcrumb"><a href="index.php">Home</a><span>/</span><span><?= h($cmsFaqPage['hero_kicker'] !== '' ? $cmsFaqPage['hero_kicker'] : 'FAQ') ?></span></div>

      <section class="faq-hero">
        <?php if (($cmsFaqPage['hero_kicker'] ?? '') !== ''): ?>
          <h2><?= h($cmsFaqPage['hero_kicker']) ?></h2>
        <?php endif; ?>
        <p><?= nl2br(h($cmsFaqPage['hero_lead'])) ?></p>
        <div class="faq-contact-strip">
          <?php if ($siteContactFaq['email'] !== ''): ?>
            <div class="faq-contact-chip">Email: <a href="mailto:<?= h($siteContactFaq['email']) ?>"><?= h($siteContactFaq['email']) ?></a></div>
          <?php endif; ?>
          <?php if ($siteContactFaq['phone'] !== ''): ?>
            <div class="faq-contact-chip">Phone: <a href="tel:<?= h($contactPhoneHrefFaq) ?>"><?= h($siteContactFaq['phone']) ?></a></div>
          <?php endif; ?>
        </div>
      </section>

      <section class="faq-list">
        <?php if ($cmsFaqItems === []): ?>
          <p class="faq-empty">Abhi koi FAQ uplabdh nahi hai.</p>
        <?php else: ?>
          <?php foreach ($cmsFaqItems as $i => $faqItem): ?>
            <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
              <summary><?= h($faqItem['question']) ?></summary>
              <div class="faq-item__content">
                <p class="faq-item__body"><?= nl2br(h($faqItem['answer'])) ?></p>
                <div class="faq-helpful">Helpful? <button type="button">Yes</button><button type="button">No</button></div>
              </div>
            </details>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
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
  <script>
    (function initFaqAccordionSmooth() {
      const items = document.querySelectorAll(".faq-item");
      if (!items.length) return;

      function expand(details, content) {
        details.setAttribute("open", "");
        content.style.maxHeight = content.scrollHeight + "px";
      }

      function collapse(details, content) {
        content.style.maxHeight = content.scrollHeight + "px";
        requestAnimationFrame(() => {
          content.style.maxHeight = "0px";
          window.setTimeout(() => details.removeAttribute("open"), 340);
        });
      }

      items.forEach(item => {
        const summary = item.querySelector("summary");
        const content = item.querySelector(".faq-item__content");
        if (!summary || !content) return;

        content.style.maxHeight = item.hasAttribute("open") ? content.scrollHeight + "px" : "0px";

        summary.addEventListener("click", e => {
          e.preventDefault();
          const isOpen = item.hasAttribute("open");
          if (isOpen) {
            collapse(item, content);
          } else {
            expand(item, content);
          }
        });
      });

      window.addEventListener("resize", () => {
        items.forEach(item => {
          const content = item.querySelector(".faq-item__content");
          if (!content) return;
          if (item.hasAttribute("open")) {
            content.style.maxHeight = content.scrollHeight + "px";
          }
        });
      });
    })();
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
