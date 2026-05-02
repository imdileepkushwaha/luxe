<?php
declare(strict_types=1);

require __DIR__ . '/partials/theme1-page-context.php';

$faqItems = [
    [
        'q' => 'How do I place an order on LUXE?',
        'a' => 'Browse products, add items to your cart, and proceed to checkout. You can create an account or continue as a guest where supported. Review your address and payment details before confirming.',
    ],
    [
        'q' => 'What payment methods are accepted?',
        'a' => 'We support major cards, UPI, and net banking where enabled by our payment partner. Available options are shown at checkout before you pay.',
    ],
    [
        'q' => 'How long does delivery take?',
        'a' => 'Delivery times depend on the seller, your location, and the shipping option you choose. Estimated timelines appear on the product or checkout page when applicable.',
    ],
    [
        'q' => 'Can I return or exchange an item?',
        'a' => 'Return and exchange policies may vary by seller and product category. Check the product page and your order details for eligibility. Contact support if you need help with a specific order.',
    ],
    [
        'q' => 'How do I track my order?',
        'a' => 'Sign in and open the Orders section from your profile. You will see status updates and tracking information when the seller or carrier provides them.',
    ],
    [
        'q' => 'How do I become a seller on LUXE?',
        'a' => 'Vendors can apply through our seller onboarding flow. If you are interested, use the “Become A Vendor” link in the footer or contact us for partnership details.',
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FAQ — LUXE</title>
  <meta name="description" content="Frequently asked questions about shopping, orders, delivery and returns on LUXE.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body class="t1-static-body">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="t1-static-hero t1-static-hero--compact" aria-labelledby="t1-faq-title">
      <div class="container t1-static-hero-inner t1-static-hero-inner--compact">
        <div class="t1-static-hero-copy">
          <p class="t1-static-kicker script-accent">Help centre</p>
          <h1 id="t1-faq-title" class="t1-static-title">Frequently asked questions</h1>
          <p class="t1-static-lead">
            Quick answers about orders, payments and account basics. Still stuck? <a href="contact-us.php" class="t1-inline-link">Contact our team</a>.
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
          <?php foreach ($faqItems as $i => $item): ?>
            <details class="t1-faq-item"<?= $i === 0 ? ' open' : '' ?>>
              <summary><?= h((string) $item['q']) ?></summary>
              <div class="t1-faq-answer">
                <p><?= h((string) $item['a']) ?></p>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
