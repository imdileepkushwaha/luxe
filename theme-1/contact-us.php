<?php
declare(strict_types=1);

require __DIR__ . '/partials/theme1-page-context.php';

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
  <title>Contact Us — LUXE</title>
  <meta name="description" content="Contact LUXE for order help, seller enquiries or general support.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body class="t1-static-body">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="t1-static-hero t1-static-hero--compact" aria-labelledby="t1-contact-title">
      <div class="container t1-static-hero-inner t1-static-hero-inner--compact">
        <div class="t1-static-hero-copy">
          <p class="t1-static-kicker script-accent">We are here to help</p>
          <h1 id="t1-contact-title" class="t1-static-title">Contact Us</h1>
          <p class="t1-static-lead">
            Questions about an order, your account or selling on LUXE? Send us a note — we read every message.
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
          <p class="t1-static-p">Our support hours are Monday–Saturday, 9:00–18:00 IST.</p>
          <ul class="t1-contact-details">
            <li>
              <span class="t1-contact-details-label">Address</span>
              37 W 24th St, New York, NY
            </li>
            <li>
              <span class="t1-contact-details-label">Phone</span>
              <a href="tel:+123324587939">+123 324 5879 39</a>
            </li>
            <li>
              <span class="t1-contact-details-label">Email</span>
              <a href="mailto:info@luxe.com">info@luxe.com</a>
            </li>
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
