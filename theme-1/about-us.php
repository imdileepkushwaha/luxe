<?php
declare(strict_types=1);

require __DIR__ . '/partials/theme1-page-context.php';
$cmsAboutT1 = cms_page_get($pdo, 'about', [
    'hero_kicker' => 'Our story',
    'hero_title' => 'Style that fits your everyday',
    'hero_lead' => 'LUXE brings together trusted sellers and thoughtful curation so you can shop fashion and lifestyle with confidence — from discovery to delivery.',
    'meta_description' => 'Learn about LUXE: curated fashion and lifestyle from trusted sellers across India.',
]);
$siteBrandT1About = site_brand_name($pdo);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($cmsAboutT1['hero_title'] !== '' ? $cmsAboutT1['hero_title'] : 'About Us') ?> — <?= h($siteBrandT1About) ?></title>
  <meta name="description" content="<?= h($cmsAboutT1['meta_description']) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="t1-static-body">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="t1-static-hero" aria-labelledby="t1-about-title">
      <div class="container t1-static-hero-inner">
        <div class="t1-static-hero-copy">
          <?php if (($cmsAboutT1['hero_kicker'] ?? '') !== ''): ?>
            <p class="t1-static-kicker script-accent"><?= h($cmsAboutT1['hero_kicker']) ?></p>
          <?php endif; ?>
          <h1 id="t1-about-title" class="t1-static-title"><?= h($cmsAboutT1['hero_title'] !== '' ? $cmsAboutT1['hero_title'] : 'About Us') ?></h1>
          <p class="t1-static-lead">
            <?= nl2br(h($cmsAboutT1['hero_lead'])) ?>
          </p>
          <div class="t1-static-hero-actions">
            <a class="btn-hero" href="product-list.php">Shop collection</a>
            <a class="btn-hero btn-hero-outline" href="contact-us.php">Talk to us</a>
          </div>
        </div>
        <div class="t1-static-hero-visual t1-static-hero-visual--about" aria-hidden="true"></div>
      </div>
    </section>

    <div class="container t1-static-wrap">
      <nav class="t1-breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span aria-hidden="true"> / </span>
        <span class="current">About Us</span>
      </nav>

      <section class="t1-static-section">
        <div class="t1-static-grid-2">
          <div>
            <?php if (trim((string) $cmsAboutT1['body_html']) !== ''): ?>
              <div class="t1-static-cms-body"><?= $cmsAboutT1['body_html'] ?></div>
            <?php else: ?>
              <h2 class="t1-static-h2">Who we are</h2>
              <p class="t1-static-p">
                We started with a simple idea: make premium shopping feel personal again. Our marketplace highlights quality listings, clear pricing in rupees, and sellers who care about the details — so every order feels considered, not rushed.
              </p>
              <p class="t1-static-p">
                Whether you are refreshing your wardrobe or gifting something special, LUXE is built to help you compare, save favourites, and check out securely — on desktop or mobile.
              </p>
            <?php endif; ?>
          </div>
          <ul class="t1-static-stats" role="list">
            <li><strong>500+</strong><span>Curated styles</span></li>
            <li><strong>50+</strong><span>Trusted vendors</span></li>
            <li><strong>24/7</strong><span>Support ready</span></li>
            <li><strong>4.8</strong><span>Shopper rating</span></li>
          </ul>
        </div>
      </section>

      <section class="t1-static-section t1-static-section--muted">
        <header class="t1-static-section-head">
          <h2 class="t1-static-h2">What we stand for</h2>
          <p class="t1-static-sub">Principles that guide how we build LUXE.</p>
        </header>
        <div class="t1-value-cards">
          <article class="t1-value-card">
            <span class="t1-value-icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </span>
            <h3>Trust first</h3>
            <p>Verified sellers and transparent policies so you always know what you are buying.</p>
          </article>
          <article class="t1-value-card">
            <span class="t1-value-icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </span>
            <h3>Designed for you</h3>
            <p>Clean catalogues, wishlists, and fast search — fewer clicks from browse to bag.</p>
          </article>
          <article class="t1-value-card">
            <span class="t1-value-icon" aria-hidden="true">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </span>
            <h3>Always improving</h3>
            <p>We ship updates often, from checkout polish to seller tools, based on real feedback.</p>
          </article>
        </div>
      </section>

      <section class="t1-static-section t1-static-cta-band">
        <div class="t1-static-cta-inner">
          <div>
            <h2 class="t1-static-h2 t1-static-h2--light">Join the LUXE community</h2>
            <p class="t1-static-cta-text">Explore new drops, track orders, and save pieces you love — all in one place.</p>
          </div>
          <a class="btn-hero btn-hero-on-dark" href="index.php#trending">Browse trending</a>
        </div>
      </section>
    </div>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
