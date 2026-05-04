<?php
declare(strict_types=1);

require __DIR__ . '/partials/theme1-page-context.php';
$lastUpdated = 'April 28, 2026';
$cmsTermsT2 = cms_page_get($pdo, 'terms', [
    'hero_kicker' => 'Legal',
    'hero_title' => 'Terms & Conditions',
    'hero_lead' => 'Please read these terms carefully. They govern your access to LUXE and your relationship with us and with independent sellers on the platform.',
    'meta_description' => 'Terms and conditions for using the LUXE marketplace and services.',
]);
$siteBrandT2Terms = site_brand_name($pdo);
$siteContactT2Terms = site_contact_bundle($pdo);
if (!empty($cmsTermsT2['updated_at'])) {
    try {
        $lastUpdated = (new DateTimeImmutable((string) $cmsTermsT2['updated_at']))->format('F j, Y');
    } catch (Throwable $e) {
        /* keep default */
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($cmsTermsT2['hero_title'] !== '' ? $cmsTermsT2['hero_title'] : 'Terms & Conditions') ?> — <?= h($siteBrandT2Terms) ?></title>
  <meta name="description" content="<?= h($cmsTermsT2['meta_description']) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="t1-static-body">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="t1-static-hero t1-static-hero--compact" aria-labelledby="t1-terms-title">
      <div class="container t1-static-hero-inner t1-static-hero-inner--compact">
        <div class="t1-static-hero-copy">
          <?php if (($cmsTermsT2['hero_kicker'] ?? '') !== ''): ?>
            <p class="t1-static-kicker script-accent"><?= h($cmsTermsT2['hero_kicker']) ?></p>
          <?php endif; ?>
          <h1 id="t1-terms-title" class="t1-static-title"><?= h($cmsTermsT2['hero_title'] !== '' ? $cmsTermsT2['hero_title'] : 'Terms & Conditions') ?></h1>
          <p class="t1-static-lead">
            <?= nl2br(h($cmsTermsT2['hero_lead'])) ?>
          </p>
        </div>
      </div>
    </section>

    <div class="container t1-static-wrap">
      <nav class="t1-breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span aria-hidden="true"> / </span>
        <span class="current">Terms &amp; Conditions</span>
      </nav>

      <div class="t1-legal-layout">
        <aside class="t1-legal-aside" aria-label="On this page">
          <p class="t1-legal-aside-title">On this page</p>
          <ul class="t1-legal-toc">
            <li><a href="#accept">Acceptance</a></li>
            <li><a href="#services">The service</a></li>
            <li><a href="#accounts">Accounts</a></li>
            <li><a href="#orders">Orders &amp; pricing</a></li>
            <li><a href="#conduct">Acceptable use</a></li>
            <li><a href="#ip">Intellectual property</a></li>
            <li><a href="#liability">Liability</a></li>
            <li><a href="#law">Governing law</a></li>
            <li><a href="#contact">Contact</a></li>
          </ul>
        </aside>

        <article class="t1-legal-doc">
          <p class="t1-legal-meta">Last updated: <?= h($lastUpdated) ?></p>

          <?php if (trim((string) $cmsTermsT2['body_html']) !== ''): ?>
            <div class="t1-legal-cms-body"><?= $cmsTermsT2['body_html'] ?></div>
          <?php else: ?>
          <section id="accept" class="t1-legal-section">
            <h2>Acceptance</h2>
            <p class="t1-static-p">These Terms &amp; Conditions (“Terms”) form a binding agreement between you and LUXE when you access or use our website, apps, or services (collectively, the “Platform”). If you do not agree, do not use the Platform.</p>
            <p class="t1-static-p">Our <a href="privacy-policy.php" class="t1-inline-link">Privacy Policy</a> explains how we handle personal data and is incorporated by reference.</p>
          </section>

          <section id="services" class="t1-legal-section">
            <h2>The service</h2>
            <p class="t1-static-p">LUXE provides an online marketplace where independent sellers list products and buyers can discover and purchase them. LUXE is not the seller of items listed by third parties unless clearly stated otherwise. Each seller is responsible for their listings, descriptions, compliance with law, and fulfilment.</p>
          </section>

          <section id="accounts" class="t1-legal-section">
            <h2>Accounts &amp; security</h2>
            <p class="t1-static-p">You may need an account for certain features. You agree to provide accurate information and keep credentials confidential. You are responsible for activity under your account and must notify us promptly of unauthorized use.</p>
          </section>

          <section id="orders" class="t1-legal-section">
            <h2>Orders, pricing &amp; payments</h2>
            <p class="t1-static-p">When you place an order, you offer to buy products at the price and terms shown at checkout. We or the seller may refuse or cancel orders in limited cases (for example, errors, fraud checks, or stock issues).</p>
            <p class="t1-static-p">Prices are shown in rupees (₹) unless stated otherwise and may change before checkout is completed. Taxes, shipping, and fees—where applicable—are shown before you pay. Payment processing may be handled by third-party providers subject to their terms.</p>
          </section>

          <section id="conduct" class="t1-legal-section">
            <h2>Acceptable use</h2>
            <p class="t1-static-p">You agree not to misuse the Platform, including by:</p>
            <ul class="t1-legal-list">
              <li>Violating law or third-party rights.</li>
              <li>Attempting to interfere with security, scrape data abusively, or disrupt operations.</li>
              <li>Posting fraudulent, misleading, or harassing content.</li>
              <li>Circumventing technical limits or seller policies.</li>
            </ul>
            <p class="t1-static-p">We may suspend or terminate access for violations or risk to other users.</p>
          </section>

          <section id="ip" class="t1-legal-section">
            <h2>Intellectual property</h2>
            <p class="t1-static-p">The LUXE name, logos, site design, and Platform software are protected by intellectual property laws. You receive a limited, revocable licence to use the Platform for personal, non-commercial shopping as permitted by these Terms. Seller content remains owned by sellers or their licensors.</p>
          </section>

          <section id="liability" class="t1-legal-section">
            <h2>Disclaimers &amp; limitation of liability</h2>
            <p class="t1-static-p">The Platform is provided “as is” and “as available” to the fullest extent permitted by law. We do not guarantee uninterrupted or error-free operation. To the extent permitted, LUXE and its affiliates are not liable for indirect, incidental, or consequential damages arising from your use of the Platform or third-party seller products.</p>
            <p class="t1-static-p">Some jurisdictions do not allow certain limitations; in those cases our liability is limited to the maximum permitted by law.</p>
          </section>

          <section id="law" class="t1-legal-section">
            <h2>Governing law &amp; disputes</h2>
            <p class="t1-static-p">These Terms are governed by applicable laws of India, without regard to conflict-of-law rules. Courts at a competent location in India shall have exclusive jurisdiction, subject to mandatory consumer protections where you reside.</p>
          </section>

          <section id="contact" class="t1-legal-section">
            <h2>Contact</h2>
            <?php $legalEmailTermsT2 = trim((string) ($siteContactT2Terms['email'] ?? '')); ?>
            <div class="t1-legal-chat-cta" role="group" aria-label="Chat support">
              <a href="contact-us.php" class="t1-legal-chat-link">
                <span class="t1-legal-chat-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true">
                    <path d="M7.5 8.25h9m-9 3.5h6m-9.25 8.75 1.05-3.15a8 8 0 1 1 2.02 1.03L4.25 20.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <span>
                  <strong>Chat now</strong>
                  <small>Our support team is here to help.</small>
                </span>
              </a>
            </div>
            <?php if ($legalEmailTermsT2 !== ''): ?>
              <p class="t1-static-p t1-legal-contact-email">Prefer email? Reach us at <a href="mailto:<?= h($legalEmailTermsT2) ?>" class="t1-inline-link"><?= h($legalEmailTermsT2) ?></a>.</p>
            <?php endif; ?>
          </section>
          <?php endif; ?>
        </article>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
