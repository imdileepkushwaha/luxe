<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cart_session.php';
require_once __DIR__ . '/includes/coupons.php';

$pdo = db();
$cartItems = $_SESSION['cart'] ?? [];
$cartItems = cart_filter_available_items($pdo, $cartItems);
$_SESSION['cart'] = $cartItems;

$expressFeeRu = 0;
$sameDayFeeRu = 0;
$platformFeeRupees = site_platform_fee_rupees($pdo);
if ($cartItems !== []) {
    $linesForFees = array_values(array_filter(
        $cartItems,
        static function ($x) {
            return is_array($x) && ($x['checked'] ?? true);
        }
    ));
    if ($linesForFees === []) {
        $linesForFees = $cartItems;
    }
    $speedFees = cart_speed_fee_totals_for_lines($pdo, $linesForFees);
    $expressFeeRu = (int) ($speedFees['express'] ?? 0);
    $sameDayFeeRu = (int) ($speedFees['same_day'] ?? 0);
}

$userLoggedIn = auth_user_id() !== null;
$user = auth_user($pdo);
$cartNavCount = 0;
foreach ($cartItems as $ci) {
    $cartNavCount += (int) ($ci['qty'] ?? 1);
}
$allProducts = products_fetch_all($pdo);
$couponDefsJs = coupons_defs_for_frontend($pdo);
$couponFeaturedCodes = coupons_featured_tag_codes($pdo, 10);
$couponOfferLines = [];
foreach ($couponFeaturedCodes as $c) {
    $d = $couponDefsJs[$c] ?? null;
    if (is_array($d) && isset($d['desc'])) {
        $couponOfferLines[] = '✦ ' . $c . ' — ' . (string) $d['desc'];
    }
    if (count($couponOfferLines) >= 6) {
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>LUXE — Your Cart</title>
  <meta name="description" content="Review your cart items and proceed to checkout at LUXE." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body class="index-page cart-page">
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="bg-scene"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="grid-lines"></div></div>

  <?php
  $header = [
      'user' => $user,
      'cart_count' => $cartNavCount,
      'top_text' => 'New arrivals every week',
      'top_highlight' => 'Free shipping above ₹999',
      'top_links' => [
          ['label' => "Today's Deals", 'href' => 'index.php#deals'],
          ['label' => 'Top Brands', 'href' => 'index.php#brands'],
      ],
      'menu_links' => [
          ['label' => 'Home', 'href' => 'index.php'],
          ['label' => 'Shop', 'href' => 'product-list.php'],
          ['label' => 'Collections', 'href' => 'index.php#collections'],
          ['label' => 'Trending', 'href' => 'index.php#trending'],
          ['label' => 'Deals', 'href' => 'index.php#deals'],
          ['label' => 'Brands', 'href' => 'index.php#brands'],
      ],
      'wishlist_href' => $user
          ? 'profile.php?tab=wishlist'
          : 'login.php?redirect=' . rawurlencode('profile.php?tab=wishlist'),
      'breadcrumb' => [
          'home_href' => 'index.php',
          'home_label' => 'Home',
          'title' => 'Your Cart',
          'current' => 'Your Cart',
      ],
      'search_lead' => 'Search by product name, brand, or category — matches show below.',
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <main class="page-main">
    <div class="container">

      <!-- Page Header -->
      <div class="page-header">
        <h1>Your Cart <span id="cartBadge" class="count-badge"><?= (int) count($cartItems) ?> item<?= count($cartItems) === 1 ? '' : 's' ?></span></h1>
        <a href="index.php" class="continue-link">← Continue Shopping</a>
      </div>

      <div class="cart-layout" id="cartLayout">

        <!-- Left: Cart Items -->
        <div class="cart-items-col">

          <!-- Select All -->
          <div class="select-bar">
            <label class="checkbox-label">
              <input type="checkbox" id="selectAll" checked onchange="toggleAll(this)" />
              <span class="checkmark"></span>
              Select All
            </label>
            <button class="remove-selected" onclick="removeSelected()">Remove Selected</button>
          </div>

          <!-- Items Container -->
          <div id="itemsContainer"></div>

          <!-- Related / Save for Later -->
          <div class="saved-section" id="savedSection">
            <h3 class="saved-title">Saved for Later <span id="savedCount">(0)</span></h3>
            <div id="savedContainer" class="saved-grid"></div>
          </div>
        </div>

        <!-- Right: Order Summary -->
        <div class="summary-col">
          <div class="summary-card">
            <h3 class="summary-title">Order Summary</h3>

            <!-- Coupon -->
            <div class="coupon-box">
              <div class="coupon-label">🏷️ Apply Coupon</div>
              <div class="coupon-row">
                <input type="text" id="couponInput" placeholder="Enter coupon code" />
                <button class="coupon-btn" onclick="applyCoupon()">Apply</button>
              </div>
              <?php if ($couponFeaturedCodes !== []): ?>
              <div class="coupon-tags">
                <?php foreach ($couponFeaturedCodes as $coupCode): ?>
                <span class="ctag" onclick="fillCoupon('<?= htmlspecialchars($coupCode, ENT_QUOTES, 'UTF-8') ?>')"><?= htmlspecialchars($coupCode, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <div class="coupon-msg" id="couponMsg"></div>
            </div>

            <!-- Price Breakdown -->
            <div class="price-rows">
              <div class="price-row"><span>Subtotal (<span id="itemCount">3</span> items)</span><strong id="subtotalEl">₹0</strong></div>
              <div class="price-row discount-row" id="discountRow" style="display:none"><span>Coupon Discount</span><strong id="discountEl" class="text-green">-₹0</strong></div>
              <div class="price-row"><span>Delivery Charges</span><strong id="deliveryEl" class="text-green">FREE</strong></div>
              <div class="price-row"><span>Platform Fee</span><strong id="platformFeeEl">₹<?= htmlspecialchars((string) $platformFeeRupees, ENT_QUOTES, 'UTF-8') ?></strong></div>
              <div class="price-divider"></div>
              <div class="price-row total-row"><span>Total Amount</span><strong id="totalEl">₹0</strong></div>
              <div class="saving-badge" id="savingBadge">You save ₹0 on this order! 🎉</div>
            </div>

            <!-- Delivery Options -->
            <div class="delivery-opts">
              <div class="delivery-opt-label">📦 Delivery Speed</div>
              <label class="delivery-option">
                <input type="radio" name="delivery" value="standard" checked onchange="updateTotal()" />
                <div class="opt-content"><strong>Standard (3-5 days)</strong><span class="text-green">FREE</span></div>
              </label>
              <label class="delivery-option">
                <input type="radio" name="delivery" value="express" onchange="updateTotal()" />
                <div class="opt-content"><strong>Express (1-2 days)</strong><span id="expressFeeDisplay"><?= $expressFeeRu > 0 ? '₹' . number_format($expressFeeRu, 0, '.', ',') : 'FREE' ?></span></div>
              </label>
              <label class="delivery-option">
                <input type="radio" name="delivery" value="same_day" onchange="updateTotal()" />
                <div class="opt-content"><strong>Same Day</strong><span id="sameDayFeeDisplay"><?= $sameDayFeeRu > 0 ? '₹' . number_format($sameDayFeeRu, 0, '.', ',') : 'FREE' ?></span></div>
              </label>
            </div>

            <button class="checkout-btn" onclick="placeOrder()">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
              Proceed to Checkout
            </button>
            <div class="secure-note">🔒 Secured by 256-bit SSL encryption</div>

            <!-- Payment icons -->
            <div class="payment-methods">
              <span class="pm">💳 Cards</span>
              <span class="pm">🏦 Net Banking</span>
              <span class="pm">📱 UPI</span>
              <span class="pm">💰 COD</span>
            </div>
          </div>

          <!-- Offers box -->
          <div class="offers-box">
            <div class="offers-title">🎁 Seller coupons</div>
            <?php if ($couponOfferLines !== []): ?>
              <?php foreach ($couponOfferLines as $line): ?>
                <div class="offer-line"><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="offer-line">Jab sellers live coupons banayenge, yahan chips dikhengi — ya neeche code type karke apply karein.</div>
            <?php endif; ?>
            <div class="offer-line">✦ Extra 5% cashback on HDFC cards</div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div class="empty-cart hidden" id="emptyCart">
        <div class="empty-emoji">🛒</div>
        <h2>Your cart is empty</h2>
        <p>Looks like you haven't added anything yet. Let's change that!</p>
        <a href="index.php" class="checkout-btn" style="display:inline-flex;max-width:240px;justify-content:center">Start Shopping →</a>
      </div>

    </div>
  </main>

  <?php
  $footer = [
      'deals_href' => 'index.php#deals',
      'year' => '2026',
  ];
  require __DIR__ . '/includes/user_footer.php';
  ?>

  <div class="toast" id="toast"></div>
  <!-- Order Confirm Modal -->
  <div class="modal-overlay hidden" id="orderModal">
    <div class="modal-card order-modal">
      <div class="order-success-icon">✓</div>
      <h2>Order Placed! 🎉</h2>
      <p>Your order has been placed successfully. You'll get a confirmation shortly.</p>
      <p class="order-id">Order ID: <strong id="modalOrderId"></strong></p>
      <div class="modal-btns">
        <a href="orders.php" class="checkout-btn">Track Order →</a>
        <a href="index.php" class="ghost-btn">Continue Shopping</a>
      </div>
    </div>
  </div>

  <script>
    window.LUXE_URLS = <?= json_encode([
        'home' => 'index.php',
        'login' => 'login.php',
        'cart' => 'cart.php',
        'checkout' => 'checkout.php',
        'product' => 'product.php',
        'orders' => 'orders.php',
        'profile' => 'profile.php',
    ], JSON_THROW_ON_ERROR) ?>;
    window.__API_CART__ = 'api/cart.php';
    window.__API_CART_DELIVERY__ = 'api/cart-delivery.php';
    window.__API_PLACE_ORDER__ = 'actions/place-order.php';
    window.__PLATFORM_FEE_RUPEES__ = <?= (int) $platformFeeRupees ?>;
    window.__CART_SPEED_FEES__ = <?= json_encode(['express' => $expressFeeRu, 'same_day' => $sameDayFeeRu], JSON_THROW_ON_ERROR) ?>;
    window.__CART_ITEMS__ = <?= json_encode($cartItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__PRODUCTS__ = <?= json_encode($allProducts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__COUPON_DEFS__ = <?= json_encode($couponDefsJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__AUTH_USER_ID__ = <?= json_encode(auth_user_id()) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
