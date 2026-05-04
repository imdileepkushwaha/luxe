<?php
declare(strict_types=1);

require __DIR__ . '/partials/theme1-page-context.php';

$cmsFaqPageT1 = cms_page_get($pdo, 'faq', [
    'hero_kicker' => 'Help centre',
    'hero_title' => 'Frequently asked questions',
    'hero_lead' => 'Quick answers about orders, payments and account basics. Still stuck?',
    'meta_description' => 'Frequently asked questions about shopping, orders, delivery and returns on LUXE.',
]);
$siteBrandT1Faq = site_brand_name($pdo);
$cmsFaqRowsT1 = cms_faqs_all($pdo, true);
$faqItems = [];
foreach ($cmsFaqRowsT1 as $row) {
    $faqItems[] = ['q' => $row['question'], 'a' => $row['answer']];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($cmsFaqPageT1['hero_title'] !== '' ? $cmsFaqPageT1['hero_title'] : 'FAQ') ?> — <?= h($siteBrandT1Faq) ?></title>
  <meta name="description" content="<?= h($cmsFaqPageT1['meta_description']) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="t1-static-body">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="t1-static-hero t1-static-hero--compact" aria-labelledby="t1-faq-title">
      <div class="container t1-static-hero-inner t1-static-hero-inner--compact">
        <div class="t1-static-hero-copy">
          <?php if (($cmsFaqPageT1['hero_kicker'] ?? '') !== ''): ?>
            <p class="t1-static-kicker script-accent"><?= h($cmsFaqPageT1['hero_kicker']) ?></p>
          <?php endif; ?>
          <h1 id="t1-faq-title" class="t1-static-title"><?= h($cmsFaqPageT1['hero_title'] !== '' ? $cmsFaqPageT1['hero_title'] : 'Frequently asked questions') ?></h1>
          <p class="t1-static-lead">
            <?= nl2br(h($cmsFaqPageT1['hero_lead'])) ?> <a href="contact-us.php" class="t1-inline-link">Contact our team</a>.
          </p>
        </div>
      </div>
    </section>

    <div class="container t1-static-wrap">
      <nav class="t1-breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span aria-hidden="true"> / </span>
        <span class="current">FAQ's</span>
      </nav>

      <div class="t1-faq-layout">
        <aside class="t1-faq-aside" aria-label="Topics">
          <p class="t1-faq-aside-title">Popular topics</p>
          <ul class="t1-faq-topic-list">
            <li><a href="#faq-list">Orders &amp; checkout</a></li>
            <li><a href="#faq-list">Shipping &amp; tracking</a></li>
            <li><a href="#faq-list">Returns</a></li>
            <li><a href="#faq-list">Selling on LUXE</a></li>
          </ul>
          <div class="t1-faq-aside-card">
            <strong>Need a human?</strong>
            <p>We reply within one business day for most requests.</p>
            <a class="btn-hero btn-hero-sm" href="contact-us.php">Contact us</a>
          </div>
        </aside>

        <div class="t1-faq-main" id="faq-list">
          <?php if ($faqItems === []): ?>
            <p class="t1-static-p">No FAQs available right now. Please check back later.</p>
          <?php else: ?>
            <?php foreach ($faqItems as $i => $item): ?>
              <details class="t1-faq-item"<?= $i === 0 ? ' open' : '' ?>>
                <summary><?= h((string) $item['q']) ?></summary>
                <div class="t1-faq-answer">
                  <p><?= nl2br(h((string) $item['a'])) ?></p>
                </div>
              </details>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
