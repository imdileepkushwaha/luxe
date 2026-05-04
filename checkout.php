<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cart_session.php';
require_once __DIR__ . '/includes/coupons.php';

$userId = auth_user_id();
if ($userId === null) {
    header('Location: login.php?redirect=' . rawurlencode('checkout.php'));
    exit;
}

$pdo = db();
$user = auth_user($pdo);
require_once __DIR__ . '/includes/razorpay.php';
$rzDevSkip = luxe_razorpay_dev_skip_payment();
$rzCheckoutKeyId = ($rzDevSkip || !luxe_razorpay_configured($pdo)) ? '' : luxe_razorpay_credentials($pdo)['key_id'];
$checkoutRzpPrefill = [
    'email' => trim((string) ($user['email'] ?? '')),
    'contact' => preg_replace('/\D+/', '', (string) ($user['phone'] ?? '')),
    'name' => trim(trim((string) ($user['first_name'] ?? '')) . ' ' . trim((string) ($user['last_name'] ?? ''))),
];
$cartItems = $_SESSION['cart'] ?? [];
$cartItems = cart_filter_available_items($pdo, $cartItems);
$_SESSION['cart'] = $cartItems;
$cartNavCount = 0;
foreach ($cartItems as $ci) {
    $cartNavCount += (int) ($ci['qty'] ?? 1);
}
$searchCatalogProducts = products_fetch_all($pdo);

$toCheckout = array_values(array_filter(
    $cartItems,
    static function ($x) {
        return is_array($x) && ($x['checked'] ?? true);
    }
));
if ($toCheckout === []) {
    header('Location: cart.php');
    exit;
}

$subtotal = 0;
foreach ($toCheckout as $li) {
    $subtotal += max(0, (int) ($li['price'] ?? 0)) * max(1, (int) ($li['qty'] ?? 1));
}

$linesForShip = [];
foreach ($toCheckout as $li) {
    $linesForShip[] = [
        'id' => (int) ($li['id'] ?? 0),
        'price' => max(0, (int) ($li['price'] ?? 0)),
        'qty' => max(1, (int) ($li['qty'] ?? 1)),
    ];
}
$baseDelivery = cart_compute_delivery_total($pdo, $linesForShip);
$speedFees = cart_speed_fee_totals_for_lines($pdo, $linesForShip);
$expressFeeRu = (int) ($speedFees['express'] ?? 0);
$sameDayFeeRu = (int) ($speedFees['same_day'] ?? 0);
$platformFeeRupees = site_platform_fee_rupees($pdo);

$addresses = addresses_fetch_for_user($pdo, $userId);
$defaultAddrId = 0;
foreach ($addresses as $a) {
    if (!empty($a['isDefault'])) {
        $defaultAddrId = (int) $a['id'];
        break;
    }
}
if ($defaultAddrId === 0 && $addresses !== []) {
    $defaultAddrId = (int) $addresses[0]['id'];
}

$hasAddresses = $addresses !== [];

$checkoutItemsPayload = array_map(static function (array $x): array {
    return [
        'id' => (int) ($x['id'] ?? 0),
        'qty' => max(1, (int) ($x['qty'] ?? 1)),
        'size' => (string) ($x['size'] ?? ''),
        'color' => (string) ($x['color'] ?? ''),
        'price' => max(0, (int) ($x['price'] ?? 0)),
        'seller_id' => max(0, (int) ($x['seller_id'] ?? 0)),
    ];
}, $toCheckout);

$couponDefsJs = coupons_defs_for_frontend($pdo);
$couponFeaturedCodes = coupons_featured_tag_codes($pdo, 10);
$checkoutCouponOfferLines = [];
foreach ($couponFeaturedCodes as $c) {
    $d = $couponDefsJs[$c] ?? null;
    if (is_array($d) && isset($d['desc'])) {
        $checkoutCouponOfferLines[] = '✦ ' . $c . ' — ' . (string) $d['desc'];
    }
    if (count($checkoutCouponOfferLines) >= 6) {
        break;
    }
}

$initialTotal = $subtotal + $platformFeeRupees + $baseDelivery;
$itemCount = count($toCheckout);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>LUXE — Checkout</title>
  <meta name="description" content="Complete your LUXE order — delivery address and payment." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
  <style>
    .checkout-left-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius-xl);
      padding: 28px;
    }
    .online-payment-status {
      margin-top: 8px;
      border: 1px solid rgba(59, 130, 246, 0.28);
      background: linear-gradient(135deg, rgba(37, 99, 235, 0.14), rgba(14, 116, 144, 0.1));
      border-radius: 14px;
      padding: 14px 16px;
    }
    .online-payment-status .status-row {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      letter-spacing: 0.1px;
      color: var(--text);
    }
    .online-payment-status .status-icon {
      width: 30px;
      height: 30px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      background: rgba(59, 130, 246, 0.2);
      border: 1px solid rgba(59, 130, 246, 0.35);
    }
    #onlinePaymentInfoText {
      margin: 0;
      color: var(--text);
      font-size: 0.94rem;
    }
  </style>
</head>
<body class="index-page checkout-page">
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
      'wishlist_href' => 'profile.php?tab=wishlist',
      'breadcrumb' => [
          'home_href' => 'index.php',
          'home_label' => 'Home',
          'title' => 'Checkout',
          'current' => 'Checkout',
      ],
      'search_lead' => 'Search by product name, brand, or category — matches show below.',
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <main class="page-main">
    <div class="container">
      <div class="page-header">
        <h1>Checkout <span class="count-badge"><?= (int) $itemCount ?> item<?= $itemCount === 1 ? '' : 's' ?></span></h1>
        <a href="cart.php" class="continue-link">← Back to cart</a>
      </div>
      <div class="checkout-steps" id="checkoutSteps">
        <span class="checkout-step is-active" data-step="1">1. Address</span>
        <span class="checkout-step-div">→</span>
        <span class="checkout-step" data-step="2">2. Payment</span>
        <span class="checkout-step-div">→</span>
        <span class="checkout-step" data-step="3">3. Review</span>
      </div>

      <?php if (!$hasAddresses): ?>
      <div class="select-bar" style="margin-bottom:20px;border-color:rgba(239,68,68,0.35)">
        <span style="color:var(--text-muted);font-size:0.92rem">Please add a delivery address to continue checkout.</span>
      </div>
      <?php endif; ?>

      <div class="cart-layout" id="checkoutLayout">

        <div class="cart-items-col">
          <section id="checkoutStepAddress" class="checkout-left-card">
            <h3 class="saved-title" style="margin-bottom:14px">Delivery address</h3>
            <div class="panel-header" style="margin:0 0 14px">
              <h2 style="font-size:1.05rem;margin:0">Saved Addresses</h2>
              <button class="checkout-btn" type="button" style="font-size:0.85rem;padding:10px 18px;max-width:160px" onclick="showAddressModal()">+ Add New</button>
            </div>
            <div class="addresses-grid" id="addressesGrid"></div>
            <div class="form-actions" style="margin-top:16px">
              <button type="button" class="checkout-btn" id="stepToPaymentBtn" style="max-width:fit-content">Continue to Payment →</button>
            </div>
          </section>

          <section id="checkoutStepPayment" class="hidden checkout-left-card" style="margin-top:0;">
            <h3 class="saved-title" style="margin:0 0 14px">Payment method</h3>
            <div class="payment-method-cards payment-method-cards--three" style="margin-bottom:14px">
              <label class="payment-method-card">
                <input type="radio" name="payment_method" value="ONLINE" checked />
                <span class="payment-method-card-inner">
                  <span class="pm-icon">🌐</span>
                  <span class="pm-title">Online Payment</span>
                  <span class="pm-desc">Cards, UPI, netbanking via Razorpay</span>
                </span>
              </label>
              <label class="payment-method-card">
                <input type="radio" name="payment_method" value="COD" />
                <span class="payment-method-card-inner">
                  <span class="pm-icon">💵</span>
                  <span class="pm-title">COD</span>
                  <span class="pm-desc">Pay on delivery</span>
                </span>
              </label>
            </div>
            <div class="online-fields-wrap" id="onlineDetailsWrap">
              <div class="online-payment-status">
                <p class="cod-note status-row" id="onlinePaymentInfoText">
                  <span class="status-icon"><?= $rzDevSkip ? '🔧' : '💳' ?></span>
                  <span><?php if ($rzDevSkip): ?>
                    Dev mode: <strong>Place order</strong> par seedha order ban jayega — Razorpay modal nahi. Live par <code>dev_skip_gateway</code> band rakho.
                  <?php else: ?>
                    Razorpay secure checkout opens when you tap <strong>Place order</strong> on the last step.
                  <?php endif; ?></span>
                </p>
              </div>
            </div>
            <div class="cod-fields-wrap hidden" id="codDetailsWrap">
              <p class="cod-note">Cash on Delivery selected. Keep exact change ready for smoother delivery.</p>
            </div>
            <div class="form-actions" style="margin-top:16px">
              <button type="button" class="ghost-btn" id="stepBackToAddressBtn">← Back</button>
              <button type="button" class="checkout-btn" id="stepToReviewBtn" style="max-width:fit-content">Continue to Review →</button>
            </div>
          </section>

          <section id="checkoutStepReview" class="hidden checkout-left-card">
            <h3 class="saved-title" style="margin:0 0 14px">Final review</h3>
            <div class="cart-item" style="margin-bottom:0">
              <div class="item-details">
                <div class="item-brand">Delivery Address</div>
                <div class="item-name" id="reviewAddressText">—</div>
                <div class="item-brand" style="margin-top:14px">Payment Method</div>
                <div class="item-name" id="reviewPaymentText">—</div>
                <div class="item-brand" style="margin-top:14px">Delivery Speed</div>
                <div class="item-name" id="reviewSpeedText">—</div>
              </div>
            </div>
            <div class="form-actions" style="margin-top:16px">
              <button type="button" class="ghost-btn" id="stepBackToPaymentBtn">← Back</button>
              <button type="button" class="checkout-btn" id="checkoutPlaceBtn" <?= $hasAddresses ? '' : 'disabled' ?> style="max-width:260px">
                Place order — ₹<span id="coPayAmount"><?= number_format($initialTotal, 0, '.', ',') ?></span>
              </button>
            </div>
          </section>
        </div>

        <div class="summary-col">
          <div class="summary-card">
            <h3 class="summary-title">Order Summary</h3>

            <div class="price-rows">
              <div class="price-row"><span>Subtotal (<span id="coItemCount"><?= (int) $itemCount ?></span> items)</span><strong id="coSubtotalEl">₹<?= number_format($subtotal, 0, '.', ',') ?></strong></div>
              <div class="price-row"><span>Delivery Charges</span><strong id="coDeliveryEl" class="text-green"><?= $baseDelivery === 0 ? 'FREE' : '₹' . number_format($baseDelivery, 0, '.', ',') ?></strong></div>
              <div class="price-row"><span>Platform Fee</span><strong id="coPlatformEl">₹<?= number_format($platformFeeRupees, 0, '.', ',') ?></strong></div>
              <div class="price-row discount-row" id="coDiscountRow" style="display:none"><span>Coupon Discount</span><strong id="coDiscountEl" class="text-green">-₹0</strong></div>
              <div class="price-divider"></div>
              <div class="price-row total-row"><span>Total Amount</span><strong id="coTotalEl">₹<?= number_format($initialTotal, 0, '.', ',') ?></strong></div>
            </div>

            <div class="delivery-opts">
              <div class="delivery-opt-label">📦 Delivery Speed</div>
              <label class="delivery-option">
                <input type="radio" name="delivery" value="standard" checked />
                <div class="opt-content"><strong>Standard (3-5 days)</strong><span class="text-green">FREE</span></div>
              </label>
              <label class="delivery-option">
                <input type="radio" name="delivery" value="express" />
                <div class="opt-content"><strong>Express (1-2 days)</strong><span id="expressFeeDisplay"><?= $expressFeeRu > 0 ? '₹' . number_format($expressFeeRu, 0, '.', ',') : 'FREE' ?></span></div>
              </label>
              <label class="delivery-option">
                <input type="radio" name="delivery" value="same_day" />
                <div class="opt-content"><strong>Same Day</strong><span id="sameDayFeeDisplay"><?= $sameDayFeeRu > 0 ? '₹' . number_format($sameDayFeeRu, 0, '.', ',') : 'FREE' ?></span></div>
              </label>
            </div>

            <div class="secure-note">🔒 Secured by 256-bit SSL encryption</div>

            <div class="payment-methods">
              <span class="pm">💳 Cards</span>
              <span class="pm">🏦 Net Banking</span>
              <span class="pm">📱 UPI</span>
              <span class="pm">💰 COD</span>
            </div>
          </div>

          <div class="offers-box">
            <div class="offers-title">🎁 Seller coupons</div>
            <?php if ($checkoutCouponOfferLines !== []): ?>
              <?php foreach ($checkoutCouponOfferLines as $offerLine): ?>
                <div class="offer-line"><?= h($offerLine) ?></div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="offer-line">Abhi koi live seller coupon chip nahi — cart par code enter karke try karein jab seller ne offer banaya ho.</div>
            <?php endif; ?>
            <div class="offer-line">✦ Extra 5% cashback on HDFC cards</div>
          </div>
        </div>

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

  <div class="modal-overlay hidden" id="addressModal">
    <div class="modal-card">
      <div class="modal-header"><h3 id="addressModalTitle">Add address</h3><button type="button" class="modal-close" onclick="closeAddressModal()">✕</button></div>
      <form id="addressForm" onsubmit="saveAddress(event)">
        <input type="hidden" id="addressId" name="address_id" value="" />
        <div class="form-grid">
          <div class="form-field"><label for="addrName">Full Name</label><input type="text" id="addrName" name="full_name" placeholder="Rahul Sharma" required maxlength="255" autocomplete="name" /></div>
          <div class="form-field"><label for="addrPhone">Phone</label><input type="tel" id="addrPhone" name="phone" placeholder="+91 98765 43210" maxlength="40" autocomplete="tel" /></div>
          <div class="form-field" style="grid-column:1/-1"><label for="addrLine1">Address Line 1</label><input type="text" id="addrLine1" name="line1" placeholder="House/Flat No., Street" required maxlength="255" autocomplete="address-line1" /></div>
          <div class="form-field" style="grid-column:1/-1"><label for="addrLine2">Address Line 2</label><input type="text" id="addrLine2" name="line2" placeholder="Landmark (optional)" maxlength="255" autocomplete="address-line2" /></div>
          <div class="form-field"><label for="addrCity">City</label><input type="text" id="addrCity" name="city" placeholder="Mumbai" required maxlength="100" autocomplete="address-level2" /></div>
          <div class="form-field"><label for="addrPin">PIN Code</label><input type="text" id="addrPin" name="pin" placeholder="400001" required maxlength="20" inputmode="numeric" autocomplete="postal-code" /></div>
          <div class="form-field"><label for="addrState">State</label><input type="text" id="addrState" name="state" placeholder="Maharashtra" required maxlength="100" autocomplete="address-level1" /></div>
          <div class="form-field"><label for="addrType">Type</label><select id="addrType" name="type"><option value="Home">Home</option><option value="Work">Work</option><option value="Other">Other</option></select></div>
          <div class="form-field" style="grid-column:1/-1"><label class="checkbox-label" style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-top:4px"><input type="checkbox" id="addrIsDefault" name="is_default" /> <span>Set as default address</span></label></div>
        </div>
        <div class="form-actions">
          <button type="submit" class="checkout-btn" id="addressSaveBtn" style="max-width:200px">Save address</button>
          <button type="button" class="ghost-btn" onclick="closeAddressModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div class="toast" id="toast"></div>

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
    window.__API_CART_DELIVERY__ = 'api/cart-delivery.php';
    window.__API_PLACE_ORDER__ = 'actions/place-order.php';
    window.__API_RAZORPAY_CREATE_ORDER__ = 'actions/razorpay-create-order.php';
    window.__RAZORPAY_KEY_ID__ = <?= json_encode($rzCheckoutKeyId, JSON_THROW_ON_ERROR) ?>;
    window.__RAZORPAY_DEV_SKIP__ = <?= $rzDevSkip ? 'true' : 'false' ?>;
    window.__CHECKOUT_USER_PREFILL__ = <?= json_encode($checkoutRzpPrefill, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__API_ADDRESS_SAVE__ = 'actions/save-address.php';
    window.__API_ADDRESS_DELETE__ = 'actions/delete-address.php';
    window.__API_ADDRESS_DEFAULT__ = 'actions/set-default-address.php';
    window.__ADDRESSES__ = <?= json_encode($addresses, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__CHECKOUT_ADDRESS_RELOAD__ = true;
    window.__PLATFORM_FEE_RUPEES__ = <?= (int) $platformFeeRupees ?>;
    window.__CHECKOUT_ITEMS__ = <?= json_encode($checkoutItemsPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__CHECKOUT_SUBTOTAL__ = <?= (int) $subtotal ?>;
    window.__CART_SPEED_FEES__ = <?= json_encode(['express' => $expressFeeRu, 'same_day' => $sameDayFeeRu], JSON_THROW_ON_ERROR) ?>;
    window.__COUPON_DEFS__ = <?= json_encode($couponDefsJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__PRODUCTS__ = <?= json_encode($searchCatalogProducts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
  </script>
  <script src="script/luxe.js"></script>
  <script>
(function () {
  const items = window.__CHECKOUT_ITEMS__ || [];
  const platformFee = (typeof window.__PLATFORM_FEE_RUPEES__ === "number" && Number.isFinite(window.__PLATFORM_FEE_RUPEES__))
    ? Math.max(0, window.__PLATFORM_FEE_RUPEES__)
    : 3;
  let deliveryBase = <?= (int) $baseDelivery ?>;
  let expressFee = <?= (int) $expressFeeRu ?>;
  let sameDayFee = <?= (int) $sameDayFeeRu ?>;
  let latestTotal = Number(window.__CHECKOUT_SUBTOTAL__) || 0;

  function checkoutCouponDiscount() {
    let code = "";
    try { code = (sessionStorage.getItem("luxeCheckoutCoupon") || "").trim().toUpperCase(); } catch (_e) {}
    if (!code || typeof window.__COUPON_DEFS__ !== "object" || !window.__COUPON_DEFS__) return 0;
    const def = window.__COUPON_DEFS__[code];
    if (!def) return 0;
    const sellerScope = def.seller_id != null && def.seller_id !== "" ? Number(def.seller_id) : null;
    const minOrder = def.min_order != null ? Number(def.min_order) : 0;
    let base = 0;
    for (const i of items) {
      const line = Number(i.price || 0) * Number(i.qty || 1);
      if (sellerScope != null && Number.isFinite(sellerScope)) {
        if (Number(i.seller_id || 0) === sellerScope) base += line;
      } else {
        base += line;
      }
    }
    if (base < minOrder || base <= 0) return 0;
    let d = 0;
    if (def.type === "percent") {
      const cap = def.max != null && def.max !== "" ? Number(def.max) : Infinity;
      d = Math.min(Math.round(base * Number(def.val) / 100), cap);
    } else {
      d = Number(def.val) || 0;
    }
    return Math.min(d, base);
  }

  function speedMode() {
    const el = document.querySelector('input[name="delivery"]:checked');
    if (!el) return "standard";
    const v = String(el.value);
    if (v === "standard" || v === "express" || v === "same_day") return v;
    return "standard";
  }

  function speedExtra() {
    const m = speedMode();
    if (m === "express") return expressFee;
    if (m === "same_day") return sameDayFee;
    return 0;
  }

  function refreshTotals() {
    const sub = Number(window.__CHECKOUT_SUBTOTAL__) || 0;
    const mode = speedMode();
    const ship = mode === "standard" ? deliveryBase : speedExtra();
    const disc = checkoutCouponDiscount();
    const total = Math.max(0, sub + platformFee + ship - disc);
    latestTotal = total;
    const delEl = document.getElementById("coDeliveryEl");
    if (delEl) {
      delEl.textContent = ship === 0 ? "FREE" : "₹" + ship.toLocaleString("en-IN");
      delEl.className = ship === 0 ? "text-green" : "";
    }
    const dRow = document.getElementById("coDiscountRow");
    const dEl = document.getElementById("coDiscountEl");
    if (dRow && dEl) {
      if (disc > 0) {
        dRow.style.display = "flex";
        dEl.textContent = "-₹" + disc.toLocaleString("en-IN");
      } else {
        dRow.style.display = "none";
      }
    }
    const totEl = document.getElementById("coTotalEl");
    if (totEl) totEl.textContent = "₹" + total.toLocaleString("en-IN");
    const payAmt = document.getElementById("coPayAmount");
    if (payAmt) payAmt.textContent = total.toLocaleString("en-IN");
  }

  async function fetchDeliveryFees() {
    if (!items.length) return;
    try {
      const api = window.__API_CART_DELIVERY__ || "api/cart-delivery.php";
      const r = await fetch(api, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ items: items.map(i => ({ id: i.id, qty: i.qty })) })
      });
      const data = await r.json();
      deliveryBase = Number(data.delivery) || 0;
      expressFee = Number(data.express_fee) || 0;
      sameDayFee = Number(data.same_day_fee) || 0;
      if (typeof window.__CART_SPEED_FEES__ === "object") {
        window.__CART_SPEED_FEES__.express = expressFee;
        window.__CART_SPEED_FEES__.same_day = sameDayFee;
      }
      const ex = document.getElementById("expressFeeDisplay");
      const sd = document.getElementById("sameDayFeeDisplay");
      if (ex) {
        ex.textContent = expressFee > 0 ? "₹" + expressFee.toLocaleString("en-IN") : "FREE";
        ex.className = expressFee > 0 ? "" : "text-green";
      }
      if (sd) {
        sd.textContent = sameDayFee > 0 ? "₹" + sameDayFee.toLocaleString("en-IN") : "FREE";
        sd.className = sameDayFee > 0 ? "" : "text-green";
      }
    } catch (_e) {}
    refreshTotals();
  }

  document.querySelectorAll('input[name="delivery"]').forEach(el => {
    el.addEventListener("change", refreshTotals);
  });

  const btn = document.getElementById("checkoutPlaceBtn");
  const stepAddress = document.getElementById("checkoutStepAddress");
  const stepPayment = document.getElementById("checkoutStepPayment");
  const stepReview = document.getElementById("checkoutStepReview");
  const stepPills = Array.from(document.querySelectorAll("#checkoutSteps .checkout-step"));
  const stepToPaymentBtn = document.getElementById("stepToPaymentBtn");
  const stepBackToAddressBtn = document.getElementById("stepBackToAddressBtn");
  const stepToReviewBtn = document.getElementById("stepToReviewBtn");
  const stepBackToPaymentBtn = document.getElementById("stepBackToPaymentBtn");
  const reviewAddressText = document.getElementById("reviewAddressText");
  const reviewPaymentText = document.getElementById("reviewPaymentText");
  const reviewSpeedText = document.getElementById("reviewSpeedText");
  const onlineDetailsWrap = document.getElementById("onlineDetailsWrap");
  const codDetailsWrap = document.getElementById("codDetailsWrap");
  const REVIEW_BTN_DEFAULT_LABEL = "Continue to Review →";
  let currentStep = 1;

  function setStep(n) {
    currentStep = n;
    if (stepAddress) stepAddress.classList.toggle("hidden", n !== 1);
    if (stepPayment) stepPayment.classList.toggle("hidden", n !== 2);
    if (stepReview) stepReview.classList.toggle("hidden", n !== 3);
    stepPills.forEach((pill, idx) => {
      const stepNum = idx + 1;
      pill.classList.toggle("is-active", stepNum === n);
      pill.style.opacity = stepNum <= n ? "1" : "";
    });
  }

  function getAddressById(id) {
    const rows = Array.isArray(window.__ADDRESSES__) ? window.__ADDRESSES__ : [];
    return rows.find(a => Number(a.id) === Number(id)) || null;
  }

  function prettySpeedName() {
    const m = speedMode();
    if (m === "express") return "Express (1-2 days)";
    if (m === "same_day") return "Same Day";
    return "Standard (3-5 days)";
  }

  function selectedPaymentMethod() {
    const payEl = document.querySelector('input[name="payment_method"]:checked');
    return payEl ? String(payEl.value) : "ONLINE";
  }

  function updateReviewButtonState() {
    if (!stepToReviewBtn) return;
    stepToReviewBtn.disabled = false;
    stepToReviewBtn.textContent = REVIEW_BTN_DEFAULT_LABEL;
  }

  function togglePaymentDetails() {
    const payment = selectedPaymentMethod();
    if (onlineDetailsWrap) onlineDetailsWrap.classList.toggle("hidden", payment !== "ONLINE");
    if (codDetailsWrap) codDetailsWrap.classList.toggle("hidden", payment !== "COD");
    updateReviewButtonState();
  }

  function validatePaymentDetails() {
    return true;
  }

  function renderReview() {
    const addressId = resolveCheckoutAddressId();
    const a = getAddressById(addressId);
    if (reviewAddressText) {
      if (a) {
        const line2 = a.line2 && String(a.line2).trim() ? ", " + String(a.line2).trim() : "";
        reviewAddressText.textContent = `${a.name || ""} — ${a.line1 || ""}${line2}, ${a.city || ""}, ${a.state || ""} ${a.pin || ""}`.trim();
      } else {
        reviewAddressText.textContent = "No address selected";
      }
    }
    const pay = selectedPaymentMethod();
    if (reviewPaymentText) {
      if (pay === "ONLINE") {
        reviewPaymentText.textContent = window.__RAZORPAY_DEV_SKIP__
          ? "Online (dev — gateway skip)"
          : "Razorpay (UPI / card / netbanking)";
      } else {
        reviewPaymentText.textContent = "Cash on Delivery";
      }
    }
    if (reviewSpeedText) reviewSpeedText.textContent = prettySpeedName();
  }

  stepToPaymentBtn?.addEventListener("click", function () {
    if (!resolveCheckoutAddressId()) {
      if (typeof showToast === "function") showToast("⚠️ Please add/select a delivery address.");
      return;
    }
    setStep(2);
  });
  stepBackToAddressBtn?.addEventListener("click", function () { setStep(1); });
  stepToReviewBtn?.addEventListener("click", function () {
    if (!validatePaymentDetails()) return;
    renderReview();
    setStep(3);
  });
  stepBackToPaymentBtn?.addEventListener("click", function () { setStep(2); });
  document.querySelectorAll('input[name="payment_method"]').forEach(el => {
    el.addEventListener("change", function () {
      togglePaymentDetails();
    });
  });

  function resolveCheckoutAddressId() {
    const selected = document.querySelector('input[name="checkout_address"]:checked');
    if (selected) {
      const id = parseInt(selected.value, 10);
      if (id > 0) return id;
    }
    const defCard = document.querySelector("#addressesGrid .address-card.default-addr");
    if (defCard && defCard.getAttribute("data-address-id")) {
      const id = parseInt(defCard.getAttribute("data-address-id"), 10);
      if (id > 0) return id;
    }
    const firstCard = document.querySelector("#addressesGrid .address-card");
    if (firstCard && firstCard.getAttribute("data-address-id")) {
      const id = parseInt(firstCard.getAttribute("data-address-id"), 10);
      if (id > 0) return id;
    }
    return 0;
  }
  function getCouponCodeForOrder() {
    try { return (sessionStorage.getItem("luxeCheckoutCoupon") || "").trim(); } catch (_e) { return ""; }
  }

  function loadRazorpayScript() {
    return new Promise(function (resolve, reject) {
      if (window.Razorpay) {
        resolve();
        return;
      }
      const s = document.createElement("script");
      s.src = "https://checkout.razorpay.com/v1/checkout.js";
      s.onload = function () { resolve(); };
      s.onerror = function () { reject(new Error("Could not load Razorpay checkout")); };
      document.body.appendChild(s);
    });
  }

  async function postPlaceOrder(addressId, paymentMethod, rzpExtra) {
    const url = window.__API_PLACE_ORDER__ || "actions/place-order.php";
    /** @type {Record<string, unknown>} */
    const body = {
      items: items,
      address_id: addressId,
      payment_method: paymentMethod,
      delivery_speed: speedMode(),
      coupon_code: getCouponCodeForOrder()
    };
    if (rzpExtra && typeof rzpExtra === "object") {
      body.razorpay_order_id = rzpExtra.razorpay_order_id;
      body.razorpay_payment_id = rzpExtra.razorpay_payment_id;
      body.razorpay_signature = rzpExtra.razorpay_signature;
    }
    const r = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(body)
    });
    return r.json();
  }

  if (btn) {
    btn.addEventListener("click", async function () {
      const addressId = resolveCheckoutAddressId();
      if (!addressId) {
        if (typeof showToast === "function") showToast("⚠️ Please add a delivery address in your profile.");
        else alert("Add a delivery address in your profile.");
        return;
      }
      const payment = selectedPaymentMethod();
      if (payment === "COD") {
        btn.disabled = true;
        try {
          const data = await postPlaceOrder(addressId, "COD", null);
          if (data.ok && data.order_ref) {
            try { sessionStorage.removeItem("luxeCheckoutCoupon"); } catch (_e) {}
            window.location.href = "orders.php?placed=" + encodeURIComponent(data.order_ref);
            return;
          }
          if (typeof showToast === "function") showToast(data.message || "Could not place order.");
          else alert(data.message || "Could not place order.");
        } catch (_e) {
          if (typeof showToast === "function") showToast("Network error — try again.");
          else alert("Network error.");
        }
        btn.disabled = false;
        return;
      }
      if (window.__RAZORPAY_DEV_SKIP__ === true) {
        btn.disabled = true;
        try {
          const data = await postPlaceOrder(addressId, "Razorpay", null);
          if (data.ok && data.order_ref) {
            try { sessionStorage.removeItem("luxeCheckoutCoupon"); } catch (_e) {}
            window.location.href = "orders.php?placed=" + encodeURIComponent(data.order_ref);
            return;
          }
          if (typeof showToast === "function") showToast(data.message || "Could not place order.");
          else alert(data.message || "Could not place order.");
        } catch (_e) {
          if (typeof showToast === "function") showToast("Network error — try again.");
          else alert("Network error.");
        }
        btn.disabled = false;
        return;
      }
      const rzKey = (typeof window.__RAZORPAY_KEY_ID__ === "string" && window.__RAZORPAY_KEY_ID__.trim()) || "";
      if (!rzKey) {
        if (typeof showToast === "function") {
          showToast("Razorpay is not configured. Use COD or add key_id / key_secret in includes/config.php.");
        } else {
          alert("Razorpay not configured.");
        }
        return;
      }
      btn.disabled = true;
      try {
        await loadRazorpayScript();
      } catch (e) {
        const msg = e && e.message ? String(e.message) : "Could not load payment UI.";
        if (typeof showToast === "function") showToast(msg);
        else alert(msg);
        btn.disabled = false;
        return;
      }
      try {
        const createUrl = window.__API_RAZORPAY_CREATE_ORDER__ || "actions/razorpay-create-order.php";
        const cr = await fetch(createUrl, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({
            items: items,
            address_id: addressId,
            delivery_speed: speedMode(),
            coupon_code: getCouponCodeForOrder()
          })
        });
        const co = await cr.json();
        if (!co.ok) {
          if (typeof showToast === "function") showToast(co.message || "Could not start payment.");
          else alert(co.message || "Could not start payment.");
          btn.disabled = false;
          return;
        }
        const prefill = (typeof window.__CHECKOUT_USER_PREFILL__ === "object" && window.__CHECKOUT_USER_PREFILL__) || {};
        const placeBtn = btn;
        const options = {
          key: co.key_id,
          amount: co.amount,
          currency: co.currency || "INR",
          order_id: co.order_id,
          name: "LUXE",
          description: "Order payment",
          handler: function (response) {
            void (async function () {
              try {
                const data = await postPlaceOrder(addressId, "Razorpay", {
                  razorpay_order_id: response.razorpay_order_id,
                  razorpay_payment_id: response.razorpay_payment_id,
                  razorpay_signature: response.razorpay_signature
                });
                if (data.ok && data.order_ref) {
                  try { sessionStorage.removeItem("luxeCheckoutCoupon"); } catch (_e2) {}
                  window.location.href = "orders.php?placed=" + encodeURIComponent(data.order_ref);
                  return;
                }
                if (typeof showToast === "function") {
                  showToast(data.message || "Order failed after payment. Contact support if you were charged.");
                } else {
                  alert(data.message || "Order failed.");
                }
              } catch (_e3) {
                if (typeof showToast === "function") {
                  showToast("Network error after payment — check My orders or contact support.");
                } else {
                  alert("Network error.");
                }
              }
              placeBtn.disabled = false;
            })();
          },
          modal: {
            ondismiss: function () {
              placeBtn.disabled = false;
            }
          },
          prefill: {
            email: prefill.email || "",
            contact: prefill.contact || "",
            name: (String(prefill.name || "").trim()) || "Customer"
          },
          theme: { color: "#18181b" }
        };
        const rzp = new window.Razorpay(options);
        rzp.on("payment.failed", function () {
          if (typeof showToast === "function") showToast("Payment failed or was cancelled.");
          placeBtn.disabled = false;
        });
        rzp.open();
      } catch (_err) {
        if (typeof showToast === "function") showToast("Could not start Razorpay — try again.");
        btn.disabled = false;
      }
    });
  }

  setStep(1);
  togglePaymentDetails();
  void fetchDeliveryFees();
})();
  </script>
</body>
</html>
