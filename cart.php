<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cart_session.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LUXE — Your Cart</title>
  <meta name="description" content="Review your cart items and proceed to checkout at LUXE." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body>
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="bg-scene"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="grid-lines"></div></div>

  <!-- Navbar -->
  <nav class="navbar" id="navbar">
    <div class="nav-container">
      <a href="index.php" class="nav-logo">LUXE</a>
      <div class="nav-breadcrumb">
        <a href="index.php">Home</a><span>/</span>
        <span class="breadcrumb-current">Your Cart</span>
      </div>
      <div class="nav-actions">
        <?php if ($userLoggedIn): ?>
        <a href="profile.php" class="nav-icon-link" aria-label="Profile">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php endif; ?>
        <a href="orders.php" class="nav-icon-link" aria-label="Orders">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </a>
        <?php if ($userLoggedIn): ?>
        <a href="actions/logout.php" class="nav-login-btn" aria-label="Sign out">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign Out
        </a>
        <?php else: ?>
        <a href="login.php" class="nav-login-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Sign In
        </a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <main class="page-main">
    <div class="container">

      <!-- Page Header -->
      <div class="page-header">
        <h1>Your Cart <span id="cartBadge" class="count-badge">3 items</span></h1>
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
              <div class="coupon-tags">
                <span class="ctag" onclick="fillCoupon('LUXE10')">LUXE10</span>
                <span class="ctag" onclick="fillCoupon('FIRST50')">FIRST50</span>
                <span class="ctag" onclick="fillCoupon('SALE20')">SALE20</span>
              </div>
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
            <div class="offers-title">🎁 Available Offers</div>
            <div class="offer-line">✦ 10% off with LUXE10 — up to ₹500</div>
            <div class="offer-line">✦ 50% off on first order with FIRST50</div>
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
    window.__AUTH_USER_ID__ = <?= json_encode(auth_user_id()) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
