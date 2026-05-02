<?php
declare(strict_types=1);

require __DIR__ . '/partials/theme1-page-context.php';
$lastUpdated = 'April 28, 2026';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Return Policy — LUXE</title>
  <meta name="description" content="How returns, exchanges and refunds work on LUXE marketplace orders.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body class="t1-static-body">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="t1-static-hero t1-static-hero--compact" aria-labelledby="t1-return-title">
      <div class="container t1-static-hero-inner t1-static-hero-inner--compact">
        <div class="t1-static-hero-copy">
          <p class="t1-static-kicker script-accent">Customer care</p>
          <h1 id="t1-return-title" class="t1-static-title">Return Policy</h1>
          <p class="t1-static-lead">
            We want you to shop with confidence. Here is how returns, exchanges and refunds typically work across sellers on LUXE.
          </p>
        </div>
      </div>
    </section>

    <div class="container t1-static-wrap">
      <nav class="t1-breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span aria-hidden="true"> / </span>
        <span class="current">Return Policy</span>
      </nav>

      <div class="t1-legal-layout">
        <aside class="t1-legal-aside" aria-label="On this page">
          <p class="t1-legal-aside-title">On this page</p>
          <ul class="t1-legal-toc">
            <li><a href="#overview">Overview</a></li>
            <li><a href="#eligible">Eligibility</a></li>
            <li><a href="#window">Time window</a></li>
            <li><a href="#process">How to return</a></li>
            <li><a href="#refunds">Refunds</a></li>
            <li><a href="#exceptions">Non-returnable items</a></li>
            <li><a href="#help">Need help?</a></li>
          </ul>
        </aside>

        <article class="t1-legal-doc">
          <p class="t1-legal-meta">Last updated: <?= h($lastUpdated) ?></p>

          <section id="overview" class="t1-legal-section">
            <h2>Overview</h2>
            <p class="t1-static-p">LUXE is a marketplace: individual sellers set fulfilment and return rules within platform guidelines and applicable law. The return window and specifics for your order are shown on the product page and order details where available.</p>
            <p class="t1-static-p">This policy describes general practices; your order confirmation or seller policy may include additional conditions.</p>
          </section>

          <section id="eligible" class="t1-legal-section">
            <h2>Eligibility</h2>
            <p class="t1-static-p">Returns are generally accepted when:</p>
            <ul class="t1-legal-list">
              <li>The item is unused, in original condition, with tags and packaging where applicable.</li>
              <li>You received the wrong item, a damaged item, or a materially different product than described.</li>
              <li>The product category and seller allow returns (some categories are final sale—see below).</li>
            </ul>
          </section>

          <section id="window" class="t1-legal-section">
            <h2>Time window</h2>
            <p class="t1-static-p">Many sellers offer a return window (for example, 7–14 days from delivery). The exact deadline for your purchase appears in your order or the seller’s terms. Requests after the window may be declined except where required by consumer law.</p>
          </section>

          <section id="process" class="t1-legal-section">
            <h2>How to start a return</h2>
            <ol class="t1-legal-ordered">
              <li>Sign in and open <a href="orders.php" class="t1-inline-link">Orders</a>, or contact support with your order number.</li>
              <li>Select the item and reason for return. Follow any pickup or ship-back instructions provided.</li>
              <li>Pack the item securely with invoice or return label if supplied.</li>
              <li>After the seller or warehouse confirms receipt and condition, your refund or exchange is processed per their timeline.</li>
            </ol>
          </section>

          <section id="refunds" class="t1-legal-section">
            <h2>Refunds &amp; exchanges</h2>
            <p class="t1-static-p">Approved refunds are usually sent to the original payment method. Processing can take several business days after approval, depending on banks or payment partners. Exchanges depend on stock and seller policy; you may receive a replacement or a refund if unavailable.</p>
            <p class="t1-static-p">Original shipping charges may be non-refundable unless the return is due to seller error or a defective item, as stated in your order terms.</p>
          </section>

          <section id="exceptions" class="t1-legal-section">
            <h2>Non-returnable categories</h2>
            <p class="t1-static-p">For hygiene, safety, or customization reasons, some items may not be returnable (for example, certain beauty products, intimate apparel, perishables, or final-sale clearance). Such restrictions are indicated at checkout or on the product page where required.</p>
          </section>

          <section id="help" class="t1-legal-section t1-legal-section--cta">
            <h2>Need help?</h2>
            <p class="t1-static-p">If you are unsure whether your order qualifies for a return, <a href="contact-us.php" class="t1-inline-link">contact us</a> with your order ID. You can also read our <a href="faq.php" class="t1-inline-link">FAQ’s</a> for common questions.</p>
          </section>
        </article>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
