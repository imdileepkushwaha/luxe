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
  <title>LUXE — Online payments</title>
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body class="index-page">
  <?php
  $header = [
      'user' => $user,
      'cart_count' => 0,
      'top_text' => 'Secure online payments',
      'top_highlight' => 'Razorpay',
      'menu_links' => [
          ['label' => 'Home', 'href' => 'index.php'],
          ['label' => 'Shop', 'href' => 'product-list.php'],
      ],
      'breadcrumb' => [
          'home_href' => 'index.php',
          'home_label' => 'Home',
          'title' => 'Online payments',
          'current' => 'Payments',
      ],
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <main class="page-main">
    <div class="container" style="max-width:720px">
      <div class="checkout-left-card">
        <h2 style="margin-top:0">Online payments</h2>
        <p style="color:var(--text-muted);margin-bottom:20px;line-height:1.55">
          LUXE checkout ab sirf <strong>Razorpay</strong> use karta hai (cards, UPI, netbanking). Gateway keys
          <strong>Admin → Settings → Payments</strong> ya <code>includes/config.php</code> se aati hain.
        </p>
        <div class="form-actions" style="margin-top:8px">
          <a href="<?= h($redirect) ?>" class="checkout-btn" style="max-width:240px;display:inline-flex;justify-content:center">Checkout par jao</a>
          <a href="index.php" class="ghost-btn">Home</a>
        </div>
      </div>
    </div>
  </main>

  <?php
  $footer = ['year' => '2026'];
  require __DIR__ . '/includes/user_footer.php';
  ?>
</body>
</html>
