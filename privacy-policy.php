<?php
require_once __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>Privacy Policy — LUXE</title>
  <meta name="description" content="Learn how LUXE collects, uses, and protects your personal information." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body class="static-page index-page">
<div class="bg-scene"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="grid-lines"></div></div>

  <?php
  $header = ['top_text' => 'Your Privacy Matters'];
  require __DIR__ . '/includes/user_header.php';
  ?>

<main class="page-main">
  <div class="container">
  <div class="page-header">
        <h1>Privacy Policy</h1>
        <a href="index.php" class="continue-link">← Back to home</a>
      </div>

      <div class="about-breadcrumb"><a href="index.php">Home</a><span>/</span><span>Privacy Policy</span></div>


    <div class=" static-wrap">
     

      <div class="static-content-wrap">
        <aside class="static-aside" aria-label="On this page">
          <p class="aside-title">On this page</p>
          <ul class="static-toc">
            <li><a href="#collection">1. Information Collection</a></li>
            <li><a href="#usage">2. How We Use Data</a></li>
            <li><a href="#sharing">3. Data Sharing</a></li>
            <li><a href="#security">4. Data Security</a></li>
            <li><a href="#cookies">5. Cookies & Tracking</a></li>
            <li><a href="#rights">6. Your Rights</a></li>
            <li><a href="#updates">7. Policy Updates</a></li>
          </ul>
        </aside>

        <article class="static-article">
          <p class="static-meta">Last Updated: April 28, 2026</p>

          <section id="collection" class="static-section">
            <h2>Information Collection</h2>
            <p class="static-p">We collect information you provide directly to us when you create an account, place an order, or communicate with us. This may include your name, email address, phone number, and shipping address.</p>
            <p class="static-p">We also collect certain information automatically when you use our Service, such as your IP address, browser type, and device information.</p>
          </section>

          <section id="usage" class="static-section">
            <h2>How We Use Data</h2>
            <p class="static-p">We use the information we collect to provide, maintain, and improve our Service, process your transactions, and communicate with you about your orders and promotional offers.</p>
            <ul class="static-list">
              <li>To personalize your shopping experience.</li>
              <li>To process payments and prevent fraud.</li>
              <li>To provide customer support.</li>
              <li>To send technical notices and security alerts.</li>
            </ul>
          </section>

          <section id="sharing" class="static-section">
            <h2>Data Sharing</h2>
            <p class="static-p">We do not sell your personal information to third parties. We may share your data with service providers who perform services on our behalf, such as payment processing and shipping.</p>
            <p class="static-p">We may also disclose information if required by law or to protect the rights and safety of LUXE and its users.</p>
          </section>

          <section id="security" class="static-section">
            <h2>Data Security</h2>
            <p class="static-p">We implement industry-standard security measures to protect your personal information from unauthorized access, disclosure, or destruction. However, no method of transmission over the internet is completely secure.</p>
          </section>

          <section id="cookies" class="static-section">
            <h2>Cookies & Tracking</h2>
            <p class="static-p">We use cookies and similar technologies to enhance your experience on our platform. You can manage your cookie preferences through your browser settings.</p>
          </section>

          <section id="rights" class="static-section">
            <h2>Your Rights</h2>
            <p class="static-p">You have the right to access, correct, or delete your personal information. You can also object to certain processing activities or request data portability.</p>
            <p class="static-p">To exercise these rights, please contact our privacy team at privacy@luxe.com.</p>
          </section>

          <section id="updates" class="static-section">
            <h2>Policy Updates</h2>
            <p class="static-p">We may update this Privacy Policy from time to time. Any changes will be posted on this page with an updated "Last Updated" date.</p>
          </section>
        </article>
      </div>
    </div>
  </div>
  
  </main>

  <?php require __DIR__ . '/includes/user_footer.php'; ?>

  <script src="script/luxe.js"></script>
</body>
</html>
