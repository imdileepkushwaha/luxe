<?php
require_once __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>Return Policy — LUXE</title>
  <meta name="description" content="Read our return and refund policy. Understand the process for returning items purchased on LUXE." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body class="static-page index-page">

  <?php
  $header = ['top_text' => 'Hassle-Free Returns'];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <main>
    <section class="static-hero" aria-labelledby="static-return-title">
      <div class="container static-hero-inner">
        <div class="static-hero-copy">
          <p class="static-kicker">Shop with Confidence</p>
          <h1 id="static-return-title" class="static-title">Return Policy</h1>
          <p class="static-lead">Not happy with your purchase? No problem. Here is how our returns and refunds work.</p>
        </div>
      </div>
    </section>

    <div class="container static-wrap">
      <nav class="static-breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span aria-hidden="true"> / </span>
        <span class="current">Return Policy</span>
      </nav>

      <div class="static-content-wrap">
        <aside class="static-aside" aria-label="On this page">
          <p class="aside-title">On this page</p>
          <ul class="static-toc">
            <li><a href="#eligibility">1. Eligibility</a></li>
            <li><a href="#window">2. Return Window</a></li>
            <li><a href="#process">3. Return Process</a></li>
            <li><a href="#refunds">4. Refunds</a></li>
            <li><a href="#exchanges">5. Exchanges</a></li>
            <li><a href="#exceptions">6. Exceptions</a></li>
          </ul>
        </aside>

        <article class="static-article">
          <p class="static-meta">Last Updated: April 28, 2026</p>

          <section id="eligibility" class="static-section">
            <h2>Eligibility</h2>
            <p class="static-p">To be eligible for a return, your item must be in the same condition that you received it, unworn or unused, with tags, and in its original packaging.</p>
            <p class="static-p">Proof of purchase (order confirmation or receipt) is required for all returns.</p>
          </section>

          <section id="window" class="static-section">
            <h2>Return Window</h2>
            <p class="static-p">Most items can be returned within 14 days of delivery. Some sellers may offer a shorter or longer window, which will be clearly indicated on the product page.</p>
          </section>

          <section id="process" class="static-section">
            <h2>Return Process</h2>
            <p class="static-p">To initiate a return, go to your <a href="orders.php" class="static-link">Orders</a> page and select the item you wish to return. Follow the on-screen instructions to generate a return label.</p>
            <ul class="static-list">
              <li>Pack the item securely in its original packaging.</li>
              <li>Affix the return label provided.</li>
              <li>Schedule a pickup or drop it off at a designated courier point.</li>
            </ul>
          </section>

          <section id="refunds" class="static-section">
            <h2>Refunds</h2>
            <p class="static-p">Once we receive and inspect your return, we will notify you of the approval or rejection of your refund. If approved, the refund will be processed to your original payment method within 5-7 business days.</p>
          </section>

          <section id="exchanges" class="static-section">
            <h2>Exchanges</h2>
            <p class="static-p">We only replace items if they are defective or damaged. If you need to exchange it for the same item, please initiate a return and place a new order.</p>
          </section>

          <section id="exceptions" class="static-section">
            <h2>Exceptions</h2>
            <p class="static-p">Certain types of items cannot be returned, such as perishable goods, custom products, and personal care items. Please check the product page for specific return restrictions.</p>
            <p class="static-p">Items purchased during a "Final Sale" event are also non-returnable.</p>
          </section>
        </article>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/includes/user_footer.php'; ?>

  <script src="script/luxe.js"></script>
</body>
</html>
