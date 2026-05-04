<?php
require_once __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title><?= h($docTitle) ?></title>
  <meta name="description" content="<?= h($metaDesc) ?>" />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body class="static-page index-page">

  <?php
  $header = [
      'breadcrumb' => [
          'home_href' => 'index.php',
          'home_label' => 'Home',
          'title' => 'Shop Grid',
          'current' => 'Shop',
      ],
      'search_lead' => 'Search by product name, brand, or category — results update in the grid below.',
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <main>
    <section class="static-hero" aria-labelledby="static-terms-title">
      <div class="container static-hero-inner">
        <div class="static-hero-copy">
          <p class="static-kicker">Legal Framework</p>
          <h1 id="static-terms-title" class="static-title">Terms & Conditions</h1>
          <p class="static-lead">Please read these terms carefully before using our platform. They govern your relationship with LUXE and its independent sellers.</p>
        </div>
      </div>
    </section>

    <div class="container static-wrap">
      <nav class="static-breadcrumb" aria-label="Breadcrumb">
        <a href="index.php">Home</a>
        <span aria-hidden="true"> / </span>
        <span class="current">Terms &amp; Conditions</span>
      </nav>

      <div class="static-content-wrap">
        <aside class="static-aside" aria-label="On this page">
          <p class="aside-title">On this page</p>
          <ul class="static-toc">
            <li><a href="#acceptance">1. Acceptance</a></li>
            <li><a href="#marketplace">2. Marketplace Role</a></li>
            <li><a href="#accounts">3. User Accounts</a></li>
            <li><a href="#orders">4. Orders & Payments</a></li>
            <li><a href="#conduct">5. Prohibited Conduct</a></li>
            <li><a href="#ip">6. Intellectual Property</a></li>
            <li><a href="#liability">7. Limitation of Liability</a></li>
            <li><a href="#changes">8. Changes to Terms</a></li>
            <li><a href="#contact">9. Contact Us</a></li>
          </ul>
        </aside>

        <article class="static-article">
          <p class="static-meta">Last Updated: April 28, 2026</p>

          <section id="acceptance" class="static-section">
            <h2>Acceptance</h2>
            <p class="static-p">By accessing or using the LUXE platform (the "Service"), you agree to be bound by these Terms & Conditions. If you do not agree with any part of these terms, you must not access the platform.</p>
            <p class="static-p">These terms apply to all visitors, users, and others who access or use the Service.</p>
          </section>

          <section id="marketplace" class="static-section">
            <h2>Marketplace Role</h2>
            <p class="static-p">LUXE provides a technology platform that connects independent sellers ("Sellers") with buyers ("Buyers"). Unless explicitly stated, LUXE is not the seller of items listed on the platform.</p>
            <ul class="static-list">
              <li>Sellers are responsible for their own listings, including accuracy, pricing, and fulfillment.</li>
              <li>LUXE does not guarantee the quality or safety of products sold by third-party Sellers.</li>
              <li>Disputes regarding specific products should first be addressed with the Seller.</li>
            </ul>
          </section>

          <section id="accounts" class="static-section">
            <h2>User Accounts</h2>
            <p class="static-p">To access certain features, you may be required to create an account. You must provide accurate and complete information and keep your login credentials secure.</p>
            <p class="static-p">You are responsible for all activity that occurs under your account. We reserve the right to terminate accounts that violate our security protocols or these terms.</p>
          </section>

          <section id="orders" class="static-section">
            <h2>Orders & Payments</h2>
            <p class="static-p">When you place an order, you are making an offer to purchase. LUXE or the Seller may accept or decline this offer based on stock availability, pricing errors, or fraud detection.</p>
            <p class="static-p">Payments are processed through secure third-party gateways. We do not store full credit card numbers on our servers. All prices are in Indian Rupees (₹) unless otherwise specified.</p>
          </section>

          <section id="conduct" class="static-section">
            <h2>Prohibited Conduct</h2>
            <p class="static-p">You agree not to use the Service for any unlawful purpose or in any way that could damage, disable, or overburden the platform. Prohibited activities include:</p>
            <ul class="static-list">
              <li>Automated data collection or scraping without authorization.</li>
              <li>Impersonating any person or entity.</li>
              <li>Uploading viruses or malicious code.</li>
              <li>Circumventing security features or seller restrictions.</li>
            </ul>
          </section>

          <section id="ip" class="static-section">
            <h2>Intellectual Property</h2>
            <p class="static-p">The Service and its original content, features, and functionality are and will remain the exclusive property of LUXE and its licensors. LUXE is protected by copyright, trademark, and other laws.</p>
            <p class="static-p">Our trademarks and trade dress may not be used in connection with any product or service without the prior written consent of LUXE.</p>
          </section>

          <section id="liability" class="static-section">
            <h2>Limitation of Liability</h2>
            <p class="static-p">In no event shall LUXE, nor its directors, employees, partners, or agents, be liable for any indirect, incidental, special, or consequential damages resulting from your use of the Service or products purchased through it.</p>
            <p class="static-p">The Service is provided on an "AS IS" and "AS AVAILABLE" basis.</p>
          </section>

          <section id="changes" class="static-section">
            <h2>Changes to Terms</h2>
            <p class="static-p">We reserve the right, at our sole discretion, to modify or replace these Terms at any time. We will provide notice of material changes by posting the new terms on this page.</p>
            <p class="static-p">Your continued use of the Service after such changes constitutes acceptance of the new terms.</p>
          </section>

          <section id="contact" class="static-section">
            <h2>Contact Us</h2>
            <p class="static-p">If you have any questions about these Terms, please contact us at:</p>
            <ul class="static-list">
              <li>Email: legal@luxe.com</li>
              <li>Phone: +91 1800-LUXE-00</li>
              <li>Address: Luxe Headquarters, Level 5, Tech Park, Bengaluru.</li>
            </ul>
          </section>
        </article>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/includes/user_footer.php'; ?>

  <script src="script/luxe.js"></script>
</body>
</html>
