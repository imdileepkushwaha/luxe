<?php
declare(strict_types=1);

require __DIR__ . '/partials/theme1-page-context.php';

$cmsContactT1 = cms_page_get($pdo, 'contact', [
    'hero_kicker' => 'We are here to help',
    'hero_title' => 'Contact Us',
    'hero_lead' => 'Questions about an order, your account or selling on LUXE? Send us a note — we read every message.',
    'meta_description' => 'Contact LUXE for order help, seller enquiries or general support.',
]);
$siteContactT1 = site_contact_bundle($pdo);
$contactPhoneHrefT1 = site_contact_phone_href($siteContactT1['phone']);

$contactThanks = isset($_GET['thanks']) && $_GET['thanks'] === '1';
$formName = '';
$formEmail = '';
$formSubject = '';
$formMessage = '';
$contactError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formName = trim((string) ($_POST['name'] ?? ''));
    $formEmail = trim((string) ($_POST['email'] ?? ''));
    $formSubject = trim((string) ($_POST['subject'] ?? ''));
    $formMessage = trim((string) ($_POST['message'] ?? ''));

    if ($formName === '' || $formEmail === '' || $formMessage === '') {
        $contactError = 'Please fill in your name, email, and message.';
    } elseif (!filter_var($formEmail, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Please enter a valid email address.';
    } elseif (mb_strlen($formMessage) < 10) {
        $contactError = 'Message should be at least 10 characters.';
    } else {
        header('Location: contact-us.php?thanks=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($cmsContactT1['hero_title'] !== '' ? $cmsContactT1['hero_title'] : 'Contact Us') ?> — <?= h($siteContactT1['brand']) ?></title>
  <meta name="description" content="<?= h($cmsContactT1['meta_description']) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="t1-static-body">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="t1-static-hero t1-static-hero--compact" aria-labelledby="t1-contact-title">
      <div class="container t1-static-hero-inner t1-static-hero-inner--compact">
        <div class="t1-static-hero-copy">
          <?php if (($cmsContactT1['hero_kicker'] ?? '') !== ''): ?>
            <p class="t1-static-kicker script-accent"><?= h($cmsContactT1['hero_kicker']) ?></p>
          <?php endif; ?>
          <h1 id="t1-contact-title" class="t1-static-title"><?= h($cmsContactT1['hero_title'] !== '' ? $cmsContactT1['hero_title'] : 'Contact Us') ?></h1>
          <p class="t1-static-lead">
            <?= nl2br(h($cmsContactT1['hero_lead'])) ?>
          </p>
        </div>
      </div>
    </section>

    <div class="container t1-static-wrap">
      <nav class="t1-breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span aria-hidden="true"> / </span>
        <span class="current">Contact</span>
      </nav>

      <?php if ($contactThanks): ?>
      <div class="t1-contact-alert t1-contact-alert--success" role="status">
        Thank you — your message has been received. We will get back to you soon.
      </div>
      <?php elseif ($contactError !== ''): ?>
      <div class="t1-contact-alert t1-contact-alert--error" role="alert">
        <?= h($contactError) ?>
      </div>
      <?php endif; ?>

      <div class="t1-contact-grid">
        <div class="t1-contact-card t1-contact-card--info">
          <h2 class="t1-static-h2">Reach us directly</h2>
          <?php if ($siteContactT1['hours'] !== ''): ?>
            <p class="t1-static-p">Our support hours are <?= h($siteContactT1['hours']) ?>.</p>
          <?php endif; ?>
          <ul class="t1-contact-details">
            <?php if ($siteContactT1['address'] !== ''): ?>
              <li>
                <span class="t1-contact-details-label">Address</span>
                <?= nl2br(h($siteContactT1['address'])) ?>
              </li>
            <?php endif; ?>
            <?php if ($siteContactT1['phone'] !== ''): ?>
              <li>
                <span class="t1-contact-details-label">Phone</span>
                <a href="tel:<?= h($contactPhoneHrefT1) ?>"><?= h($siteContactT1['phone']) ?></a>
              </li>
            <?php endif; ?>
            <?php if ($siteContactT1['email'] !== ''): ?>
              <li>
                <span class="t1-contact-details-label">Email</span>
                <a href="mailto:<?= h($siteContactT1['email']) ?>"><?= h($siteContactT1['email']) ?></a>
              </li>
            <?php endif; ?>
          </ul>
          <p class="t1-static-p t1-static-p--small">
            For order-specific issues, include your order number so we can help faster.
          </p>
        </div>

        <div class="t1-contact-card t1-contact-card--form">
          <h2 class="t1-static-h2">Send a message</h2>
          <form class="t1-contact-form" method="post" action="contact-us.php" novalidate>
            <label class="t1-field">
              <span class="t1-field-label">Name</span>
              <input type="text" name="name" value="<?= h($formName) ?>" required autocomplete="name" placeholder="Your full name">
            </label>
            <label class="t1-field">
              <span class="t1-field-label">Email</span>
              <input type="email" name="email" value="<?= h($formEmail) ?>" required autocomplete="email" placeholder="you@example.com">
            </label>
            <label class="t1-field">
              <span class="t1-field-label">Subject <span class="t1-optional">(optional)</span></span>
              <input type="text" name="subject" value="<?= h($formSubject) ?>" placeholder="e.g. Order #1234">
            </label>
            <label class="t1-field">
              <span class="t1-field-label">Message</span>
              <textarea name="message" rows="5" required placeholder="How can we help?"><?= h($formMessage) ?></textarea>
            </label>
            <button type="submit" class="btn-hero">Send message</button>
          </form>
        </div>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
