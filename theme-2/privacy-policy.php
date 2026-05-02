<?php
declare(strict_types=1);

require __DIR__ . '/partials/theme1-page-context.php';
$lastUpdated = 'April 28, 2026';
$cmsPrivacyT2 = cms_page_get($pdo, 'privacy', [
    'hero_kicker' => 'Legal',
    'hero_title' => 'Privacy Policy',
    'hero_lead' => 'We respect your privacy. This policy explains what we collect, why we collect it, and the choices you have when you shop or use LUXE.',
    'meta_description' => 'How LUXE collects, uses and protects your personal information.',
]);
$siteBrandT2Privacy = site_brand_name($pdo);
if (!empty($cmsPrivacyT2['updated_at'])) {
    try {
        $lastUpdated = (new DateTimeImmutable((string) $cmsPrivacyT2['updated_at']))->format('F j, Y');
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
  <title><?= h($cmsPrivacyT2['hero_title'] !== '' ? $cmsPrivacyT2['hero_title'] : 'Privacy Policy') ?> — <?= h($siteBrandT2Privacy) ?></title>
  <meta name="description" content="<?= h($cmsPrivacyT2['meta_description']) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="t1-static-body">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="t1-static-hero t1-static-hero--compact" aria-labelledby="t1-privacy-title">
      <div class="container t1-static-hero-inner t1-static-hero-inner--compact">
        <div class="t1-static-hero-copy">
          <?php if (($cmsPrivacyT2['hero_kicker'] ?? '') !== ''): ?>
            <p class="t1-static-kicker script-accent"><?= h($cmsPrivacyT2['hero_kicker']) ?></p>
          <?php endif; ?>
          <h1 id="t1-privacy-title" class="t1-static-title"><?= h($cmsPrivacyT2['hero_title'] !== '' ? $cmsPrivacyT2['hero_title'] : 'Privacy Policy') ?></h1>
          <p class="t1-static-lead">
            <?= nl2br(h($cmsPrivacyT2['hero_lead'])) ?>
          </p>
        </div>
      </div>
    </section>

    <div class="container t1-static-wrap">
      <nav class="t1-breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span aria-hidden="true"> / </span>
        <span class="current">Privacy Policy</span>
      </nav>

      <div class="t1-legal-layout">
        <aside class="t1-legal-aside" aria-label="On this page">
          <p class="t1-legal-aside-title">On this page</p>
          <ul class="t1-legal-toc">
            <li><a href="#intro">Introduction</a></li>
            <li><a href="#collect">Information we collect</a></li>
            <li><a href="#use">How we use information</a></li>
            <li><a href="#share">Sharing &amp; disclosure</a></li>
            <li><a href="#cookies">Cookies</a></li>
            <li><a href="#security">Security</a></li>
            <li><a href="#rights">Your choices</a></li>
            <li><a href="#changes">Changes</a></li>
            <li><a href="#contact">Contact</a></li>
          </ul>
        </aside>

        <article class="t1-legal-doc">
          <p class="t1-legal-meta">Last updated: <?= h($lastUpdated) ?></p>

          <?php if (trim((string) $cmsPrivacyT2['body_html']) !== ''): ?>
            <div class="t1-legal-cms-body"><?= $cmsPrivacyT2['body_html'] ?></div>
          <?php else: ?>
          <section id="intro" class="t1-legal-section">
            <h2>Introduction</h2>
            <p class="t1-static-p">LUXE (“we”, “us”, “our”) operates an online marketplace that connects shoppers with sellers. This Privacy Policy describes how we handle personal information when you use our website, mobile experiences, or related services.</p>
            <p class="t1-static-p">By using LUXE, you agree to this policy and to our <a href="terms-and-conditions.php" class="t1-inline-link">Terms &amp; Conditions</a>. If you do not agree, please do not use our services.</p>
          </section>

          <section id="collect" class="t1-legal-section">
            <h2>Information we collect</h2>
            <p class="t1-static-p">We may collect information you provide directly and data we receive automatically:</p>
            <ul class="t1-legal-list">
              <li><strong>Account &amp; profile:</strong> name, email, phone number, delivery addresses, and preferences you save in your account.</li>
              <li><strong>Orders &amp; payments:</strong> items purchased, order history, and payment-related metadata processed by our payment partners (we do not store full card numbers on our servers).</li>
              <li><strong>Support &amp; communications:</strong> messages you send to support or sellers through LUXE.</li>
              <li><strong>Technical data:</strong> device type, browser, IP address, approximate location, and usage data such as pages viewed and actions taken, to improve performance and security.</li>
            </ul>
          </section>

          <section id="use" class="t1-legal-section">
            <h2>How we use information</h2>
            <p class="t1-static-p">We use personal information to:</p>
            <ul class="t1-legal-list">
              <li>Process and deliver orders, and provide customer support.</li>
              <li>Authenticate accounts, prevent fraud, and protect the platform.</li>
              <li>Show relevant products, personalize your experience (where you opt in), and measure feature usage.</li>
              <li>Send transactional messages (order updates, security alerts) and, where permitted, marketing you can opt out of.</li>
              <li>Comply with law and enforce our terms.</li>
            </ul>
          </section>

          <section id="share" class="t1-legal-section">
            <h2>Sharing &amp; disclosure</h2>
            <p class="t1-static-p">We may share information with:</p>
            <ul class="t1-legal-list">
              <li><strong>Sellers &amp; logistics partners</strong> — to fulfil your orders (for example, shipping name, address, and contact where required).</li>
              <li><strong>Service providers</strong> — such as hosting, payments, analytics, and email delivery, under contracts that limit their use of data.</li>
              <li><strong>Legal &amp; safety</strong> — when required by law, or to protect rights, safety, and integrity of LUXE, users, or the public.</li>
            </ul>
            <p class="t1-static-p">We do not sell your personal information to third parties for their independent marketing.</p>
          </section>

          <section id="cookies" class="t1-legal-section">
            <h2>Cookies &amp; similar technologies</h2>
            <p class="t1-static-p">We use cookies and similar technologies to keep you signed in, remember preferences, understand traffic, and improve the site. You can control cookies through your browser settings; blocking some cookies may limit certain features.</p>
          </section>

          <section id="security" class="t1-legal-section">
            <h2>Security</h2>
            <p class="t1-static-p">We use reasonable technical and organizational measures to protect information. No method of transmission over the internet is 100% secure; we encourage strong passwords and caution when sharing account access.</p>
          </section>

          <section id="rights" class="t1-legal-section">
            <h2>Your choices &amp; rights</h2>
            <p class="t1-static-p">Depending on where you live, you may have rights to access, correct, delete, or export personal data, or to object to certain processing. You can update much of your account information in <a href="profile.php" class="t1-inline-link">Profile</a> settings. For other requests, <a href="contact-us.php" class="t1-inline-link">contact us</a>.</p>
          </section>

          <section id="changes" class="t1-legal-section">
            <h2>Changes to this policy</h2>
            <p class="t1-static-p">We may update this Privacy Policy from time to time. We will post the revised version on this page and adjust the “Last updated” date. Continued use after changes means you accept the updated policy, where permitted by law.</p>
          </section>

          <section id="contact" class="t1-legal-section">
            <h2>Contact us</h2>
            <p class="t1-static-p">Questions about this policy or your data: email <a href="mailto:info@luxe.com" class="t1-inline-link">info@luxe.com</a> or use our <a href="contact-us.php" class="t1-inline-link">contact form</a>.</p>
          </section>
          <?php endif; ?>
        </article>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
