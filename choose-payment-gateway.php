<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$userId = auth_user_id();
if ($userId === null) {
    header('Location: login.php?redirect=' . rawurlencode('choose-payment-gateway.php'));
    exit;
}

$pdo = db();
$user = auth_user($pdo);
$redirect = (string) ($_GET['redirect'] ?? 'checkout.php');
if ($redirect === '' || strpos($redirect, '://') !== false || strpos($redirect, '/') === 0) {
    $redirect = 'checkout.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>LUXE — Choose Payment Gateway</title>
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body class="index-page">
  <?php
  $header = [
      'user' => $user,
      'cart_count' => 0,
      'top_text' => 'Secure online payments',
      'top_highlight' => 'Choose your trusted gateway',
      'menu_links' => [
          ['label' => 'Home', 'href' => 'index.php'],
          ['label' => 'Shop', 'href' => 'product-list.php'],
      ],
      'breadcrumb' => [
          'home_href' => 'index.php',
          'home_label' => 'Home',
          'title' => 'Choose Payment Gateway',
          'current' => 'Gateway',
      ],
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <main class="page-main">
    <div class="container" style="max-width:900px">
      <div class="checkout-left-card">
        <h2 style="margin-top:0">Choose Payment Gateway</h2>
        <p style="color:var(--text-muted);margin-bottom:20px">Online payment ke liye gateway choose karein.</p>
        <div class="payment-method-cards" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
          <button type="button" class="payment-method-card" data-gateway="Razorpay">
            <span class="payment-method-card-inner">
              <span class="pm-icon">🟦</span>
              <span class="pm-title">Razorpay</span>
              <span class="pm-desc">Cards, UPI, Wallets</span>
            </span>
          </button>
          <button type="button" class="payment-method-card" data-gateway="PayU">
            <span class="payment-method-card-inner">
              <span class="pm-icon">🟩</span>
              <span class="pm-title">PayU</span>
              <span class="pm-desc">Fast checkout options</span>
            </span>
          </button>
          <button type="button" class="payment-method-card" data-gateway="Paytm">
            <span class="payment-method-card-inner">
              <span class="pm-icon">🟦</span>
              <span class="pm-title">Paytm</span>
              <span class="pm-desc">UPI and wallet support</span>
            </span>
          </button>
        </div>
        <div class="form-actions" style="margin-top:18px">
          <a href="<?= h($redirect) ?>" class="ghost-btn">← Back to Checkout</a>
        </div>
      </div>
    </div>
  </main>

  <?php
  $footer = ['year' => '2026'];
  require __DIR__ . '/includes/user_footer.php';
  ?>

  <script>
  (function () {
    const returnUrl = <?= json_encode($redirect, JSON_THROW_ON_ERROR) ?>;
    const buttons = Array.from(document.querySelectorAll("[data-gateway]"));
    buttons.forEach((btn) => {
      btn.addEventListener("click", function () {
        const gateway = String(btn.getAttribute("data-gateway") || "").trim();
        if (!gateway) return;
        if (gateway !== "Razorpay") {
          alert(gateway + " abhi setup nahi hai. Razorpay choose karein.");
          return;
        }

        try {
          window.open("https://razorpay.com/", "_blank", "noopener,noreferrer");
        } catch (_e) {}

        btn.disabled = true;
        btn.textContent = "Redirecting to Razorpay... returning in 20s";
        setTimeout(function () {
          const sep = returnUrl.indexOf("?") === -1 ? "?" : "&";
          window.location.href = returnUrl + sep + "payment_status=success&gateway=Razorpay";
        }, 20000);
      });
    });
  })();
  </script>
</body>
</html>
