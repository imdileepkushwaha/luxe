<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart_session.php';

$pdo = db();
$user = auth_user($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    foreach ($_SESSION['cart'] as $k => $line) {
        if ((int) ($line['id'] ?? 0) !== $id) {
            continue;
        }
        if ($action === 'remove') {
            unset($_SESSION['cart'][$k]);
        } elseif ($action === 'qty') {
            $qty = max(1, min(20, (int) ($_POST['qty'] ?? 1)));
            $_SESSION['cart'][$k]['qty'] = $qty;
        } elseif ($action === 'check') {
            $_SESSION['cart'][$k]['checked'] = !empty($_POST['checked']);
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    header('Location: cart.php');
    exit;
}

$cartItems = cart_filter_available_items($pdo, $_SESSION['cart'] ?? []);
$_SESSION['cart'] = $cartItems;

function t1_cart_thumb(array $line): string
{
    $img = trim((string) ($line['image'] ?? $line['image_path'] ?? ''));
    if ($img === '' || strcasecmp($img, 'default') === 0) {
        return '';
    }
    if (!preg_match('#^(https?:)?//#i', $img) && !str_starts_with($img, '/')) {
        $img = '../' . ltrim($img, '/');
    }
    return $img;
}

$cartCount = 0;
$subtotal = 0;
$checkedSubtotal = 0;
$checkedCount = 0;
foreach ($cartItems as $ci) {
    $qty = max(1, (int) ($ci['qty'] ?? 1));
    $price = max(0, (int) ($ci['price'] ?? 0));
    $lineTotal = $qty * $price;
    $cartCount += $qty;
    $subtotal += $lineTotal;
    if (($ci['checked'] ?? true)) {
        $checkedSubtotal += $lineTotal;
        $checkedCount += $qty;
    }
}

$fullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($fullName === '') {
    $fullName = 'Member';
}
$initial = strtoupper(substr((string) ($user['first_name'] ?? $fullName), 0, 1));
$isLoggedIn = $user !== null;
$userInitials = $initial;
$userName = $fullName;
$userEmail = trim((string) ($user['email'] ?? ''));
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('theme-1/cart.php');
$theme1HeaderCategories = ["Men's Fashion", "Women's Fashion", "Kid's Fashion", 'Footwear'];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = $theme1HeaderCategories;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LUXE Theme 1 - Cart</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body class="profile-page-wrap">
  <?php require __DIR__ . '/partials/header.php'; ?>
  <main>
    <section class="profile-shell">
      <article class="profile-main t1-full">
        <div class="profile-main-head">
          <h2>My Cart</h2>
          <a class="profile-edit-btn" href="checkout.php">Checkout (<?= (int) $checkedCount ?>)</a>
        </div>
        <?php if ($cartItems === []): ?>
          <div class="theme1-address-empty">Your cart is empty.</div>
        <?php else: ?>
          <div class="t1-cart-list">
            <?php foreach ($cartItems as $line): ?>
              <?php
                $lineId = (int) ($line['id'] ?? 0);
                $lineQty = max(1, (int) ($line['qty'] ?? 1));
                $linePrice = max(0, (int) ($line['price'] ?? 0));
                $lineChecked = (bool) ($line['checked'] ?? true);
                $lineTotal = $lineQty * $linePrice;
                $lineName = (string) ($line['name'] ?? 'Product');
                $lineEmoji = (string) ($line['emoji'] ?? '🛍');
                $lineImg = t1_cart_thumb($line);
              ?>
              <div class="t1-cart-item">
                <div class="t1-cart-media"><?php if ($lineImg !== ''): ?><img src="<?= h($lineImg) ?>" alt="<?= h($lineName) ?>"><?php else: ?><span><?= h($lineEmoji) ?></span><?php endif; ?></div>
                <div class="t1-cart-main">
                  <h3><?= h($lineName) ?></h3>
                  <p>Rs <?= number_format($linePrice) ?> x <?= $lineQty ?> = <strong>Rs <?= number_format($lineTotal) ?></strong></p>
                  <div class="t1-cart-actions">
                    <form method="post">
                      <input type="hidden" name="action" value="check">
                      <input type="hidden" name="id" value="<?= $lineId ?>">
                      <label><input type="checkbox" name="checked" value="1" <?= $lineChecked ? 'checked' : '' ?> onchange="this.form.submit()"> Include</label>
                    </form>
                    <form method="post">
                      <input type="hidden" name="action" value="qty">
                      <input type="hidden" name="id" value="<?= $lineId ?>">
                      <input type="number" name="qty" min="1" max="20" value="<?= $lineQty ?>">
                      <button class="profile-edit-cancel" type="submit">Update</button>
                    </form>
                    <form method="post">
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="id" value="<?= $lineId ?>">
                      <button class="theme1-action-btn is-delete" type="submit">Remove</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="t1-cart-summary">
            <p>Cart subtotal: <strong>Rs <?= number_format($subtotal) ?></strong></p>
            <p>Checkout subtotal: <strong>Rs <?= number_format($checkedSubtotal) ?></strong></p>
          </div>
        <?php endif; ?>
      </article>
    </section>
  </main>
  <?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
