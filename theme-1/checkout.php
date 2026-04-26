<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart_session.php';

$pdo = db();
$user = auth_user($pdo);
if (!$user) {
    header('Location: login.php?redirect=' . rawurlencode('theme-1/checkout.php'));
    exit;
}

$cartItems = cart_filter_available_items($pdo, $_SESSION['cart'] ?? []);
$_SESSION['cart'] = $cartItems;
$selected = array_values(array_filter($cartItems, static fn(array $x): bool => (bool) ($x['checked'] ?? true)));
if ($selected === []) {
    header('Location: cart.php');
    exit;
}

$uid = (int) ($user['id'] ?? 0);
$addresses = addresses_fetch_for_user($pdo, $uid);
$defaultAddr = 0;
foreach ($addresses as $a) {
    if (!empty($a['isDefault'])) {
        $defaultAddr = (int) $a['id'];
        break;
    }
}
if ($defaultAddr === 0 && $addresses !== []) {
    $defaultAddr = (int) ($addresses[0]['id'] ?? 0);
}

$subtotal = 0;
foreach ($selected as $line) {
    $subtotal += max(1, (int) ($line['qty'] ?? 1)) * max(0, (int) ($line['price'] ?? 0));
}
$cartCount = 0;
foreach ($cartItems as $ci) {
    $cartCount += (int) ($ci['qty'] ?? 1);
}

$fullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($fullName === '') {
    $fullName = 'Member';
}
$initial = strtoupper(substr((string) ($user['first_name'] ?? $fullName), 0, 1));
$isLoggedIn = true;
$userInitials = $initial;
$userName = $fullName;
$userEmail = trim((string) ($user['email'] ?? ''));
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('theme-1/checkout.php');
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
  <title>LUXE Theme 1 - Checkout</title>
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
          <h2>Checkout</h2>
          <a class="profile-edit-cancel" href="cart.php">Back to cart</a>
        </div>

        <div class="t1-checkout-layout">
          <div>
            <h3>Select Address</h3>
            <div class="t1-checkout-addresses">
              <?php if ($addresses === []): ?>
                <p class="theme1-address-empty">No saved address. Add from Profile → Address.</p>
              <?php else: ?>
                <?php foreach ($addresses as $a): ?>
                  <label class="t1-checkout-address">
                    <input type="radio" name="checkout_address" value="<?= (int) ($a['id'] ?? 0) ?>" <?= (int) ($a['id'] ?? 0) === $defaultAddr ? 'checked' : '' ?>>
                    <span><strong><?= h((string) ($a['name'] ?? '')) ?></strong> · <?= h((string) ($a['phone'] ?? '')) ?><br><?= h((string) ($a['line1'] ?? '')) ?><?= !empty($a['line2']) ? ', ' . h((string) $a['line2']) : '' ?>, <?= h((string) ($a['city'] ?? '')) ?>, <?= h((string) ($a['state'] ?? '')) ?> <?= h((string) ($a['pin'] ?? '')) ?></span>
                  </label>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="t1-checkout-payment">
              <label><input type="radio" name="checkout_payment" value="COD" checked> Cash on Delivery</label>
            </div>
          </div>
          <aside class="t1-checkout-summary">
            <h3>Order Summary</h3>
            <?php foreach ($selected as $line): ?>
              <p><?= h((string) ($line['name'] ?? 'Item')) ?> x <?= (int) ($line['qty'] ?? 1) ?> <strong>Rs <?= number_format((int) (($line['qty'] ?? 1) * ($line['price'] ?? 0))) ?></strong></p>
            <?php endforeach; ?>
            <hr>
            <p>Total <strong>Rs <?= number_format($subtotal) ?></strong></p>
            <button class="profile-edit-btn" id="placeOrderBtn" <?= $addresses === [] ? 'disabled' : '' ?>>Place Order</button>
            <p class="profile-edit-msg hidden" id="checkoutMsg"></p>
          </aside>
        </div>
      </article>
    </section>
  </main>
  <?php require __DIR__ . '/partials/footer.php'; ?>
  <script>
    (function () {
      var btn = document.getElementById("placeOrderBtn");
      var msg = document.getElementById("checkoutMsg");
      if (!btn || !msg) return;
      var items = <?= json_encode(array_map(static function (array $x): array {
        return [
          'id' => (int) ($x['id'] ?? 0),
          'qty' => max(1, (int) ($x['qty'] ?? 1)),
          'size' => (string) ($x['size'] ?? ''),
          'color' => (string) ($x['color'] ?? '')
        ];
      }, $selected), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
      btn.addEventListener("click", async function () {
        var addr = document.querySelector("input[name='checkout_address']:checked");
        if (!addr) {
          msg.textContent = "Please select an address.";
          msg.classList.remove("hidden");
          msg.classList.add("is-error");
          return;
        }
        btn.disabled = true;
        msg.classList.add("hidden");
        try {
          var res = await fetch("../actions/place-order.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              items: items,
              address_id: parseInt(addr.value, 10),
              payment_method: "COD"
            })
          });
          var data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.message || "Could not place order.");
          window.location.href = "orders.php?placed=" + encodeURIComponent(data.order_ref || "");
        } catch (e) {
          msg.textContent = e.message || "Could not place order.";
          msg.classList.remove("hidden");
          msg.classList.add("is-error");
        } finally {
          btn.disabled = false;
        }
      });
    })();
  </script>
</body>
</html>
