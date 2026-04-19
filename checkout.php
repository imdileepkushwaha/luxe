<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cart_session.php';

$userId = auth_user_id();
if ($userId === null) {
    header('Location: login.php?redirect=' . rawurlencode('checkout.php'));
    exit;
}

$pdo = db();
$cartItems = $_SESSION['cart'] ?? [];
$cartItems = cart_filter_available_items($pdo, $cartItems);
$_SESSION['cart'] = $cartItems;

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
    ];
}, $toCheckout);

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
  </style>
</head>
<body>
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="bg-scene"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="grid-lines"></div></div>

  <nav class="navbar" id="navbar">
    <div class="nav-container">
      <div class="nav-brand-cluster">
        <?php require __DIR__ . '/includes/nav_hamburger_btn.php'; ?>
        <a href="index.php" class="nav-logo">LUXE</a>
      </div>
      <div class="nav-breadcrumb">
        <a href="index.php">Home</a><span>/</span>
        <a href="cart.php">Cart</a><span>/</span>
        <span class="breadcrumb-current">Checkout</span>
      </div>
      <div class="nav-actions">
        <a href="profile.php" class="nav-icon-link" aria-label="Profile" data-nav-mobile="drawer">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <a href="orders.php" class="nav-icon-link" aria-label="Orders" data-nav-mobile="drawer">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </a>
        <a href="actions/logout.php" class="nav-login-btn" aria-label="Sign out" data-nav-mobile="drawer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign Out
        </a>
      </div>
    </div>
  </nav>
  <?php require __DIR__ . '/includes/nav_drawer.php'; ?>

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
                <input type="radio" name="payment_method" value="Card" checked />
                <span class="payment-method-card-inner">
                  <span class="pm-icon">💳</span>
                  <span class="pm-title">Card</span>
                  <span class="pm-desc">Credit / Debit</span>
                </span>
              </label>
              <label class="payment-method-card">
                <input type="radio" name="payment_method" value="UPI" />
                <span class="payment-method-card-inner">
                  <span class="pm-icon">📱</span>
                  <span class="pm-title">UPI</span>
                  <span class="pm-desc">GPay, PhonePe…</span>
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
            <div class="card-fields-wrap" id="cardDetailsWrap">
              <div class="form-grid">
                <div class="form-field" style="grid-column:1/-1">
                  <label for="payCardNumber">Card Number</label>
                  <input type="text" id="payCardNumber" placeholder="1234 5678 9012 3456" inputmode="numeric" maxlength="23" />
                </div>
                <div class="form-field">
                  <label for="payCardName">Card Holder Name</label>
                  <input type="text" id="payCardName" placeholder="Rahul Sharma" maxlength="80" />
                </div>
                <div class="form-field">
                  <label for="payCardExpiry">Expiry (MM/YY)</label>
                  <input type="text" id="payCardExpiry" placeholder="08/29" maxlength="5" />
                </div>
                <div class="form-field">
                  <label for="payCardCvv">CVV</label>
                  <input type="password" id="payCardCvv" placeholder="123" inputmode="numeric" maxlength="4" />
                </div>
                <div class="form-field" style="grid-column:1/-1">
                  <label class="checkbox-label" style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-top:4px">
                    <input type="checkbox" id="payCardSave" />
                    <span>Save this card for faster checkout next time</span>
                  </label>
                </div>
              </div>
            </div>
            <div class="upi-fields-wrap hidden" id="upiDetailsWrap">
              <div class="delivery-opts" style="margin-bottom:12px">
                <label class="delivery-option">
                  <input type="radio" name="upi_mode" value="id" checked />
                  <div class="opt-content"><strong>UPI ID</strong><span>Enter handle</span></div>
                </label>
                <label class="delivery-option">
                  <input type="radio" name="upi_mode" value="qr" />
                  <div class="opt-content"><strong>Scan QR</strong><span>Pay with any UPI app</span></div>
                </label>
              </div>
              <div id="upiIdEntry">
                <div class="form-grid">
                  <div class="form-field" style="grid-column:1/-1">
                    <label for="payUpiId">UPI ID</label>
                    <input type="text" id="payUpiId" placeholder="name@okhdfc" maxlength="80" />
                  </div>
                </div>
              </div>
              <div id="upiQrEntry" class="hidden">
                <div class="cart-item" style="align-items:center;gap:16px">
                  <img id="upiQrImage" alt="UPI QR code" width="150" height="150" style="border-radius:12px;border:1px solid var(--border);background:#fff;padding:8px" />
                  <div class="item-details">
                    <div class="item-brand">Scan and pay</div>
                    <div class="item-name" id="upiQrAmountText">₹0</div>
                    <div class="item-variants"><span class="var-tag" id="upiQrPayeeText">LUXE Store</span></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="cod-fields-wrap hidden" id="codDetailsWrap">
              <p class="cod-note">Cash on Delivery selected. Keep exact change ready for smoother delivery.</p>
            </div>
            <div class="form-actions" style="margin-top:16px">
              <button type="button" class="ghost-btn" id="stepBackToAddressBtn">← Back</button>
              <button type="button" class="checkout-btn" id="stepToReviewBtn" style="max-width:220px">Continue to Review →</button>
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
            <div class="offers-title">🎁 Available Offers</div>
            <div class="offer-line">✦ 10% off with LUXE10 — up to ₹500</div>
            <div class="offer-line">✦ 50% off on first order with FIRST50</div>
            <div class="offer-line">✦ Extra 5% cashback on HDFC cards</div>
          </div>
        </div>

      </div>
    </div>
  </main>

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
    window.__API_ADDRESS_SAVE__ = 'actions/save-address.php';
    window.__API_ADDRESS_DELETE__ = 'actions/delete-address.php';
    window.__API_ADDRESS_DEFAULT__ = 'actions/set-default-address.php';
    window.__ADDRESSES__ = <?= json_encode($addresses, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__CHECKOUT_ADDRESS_RELOAD__ = true;
    window.__PLATFORM_FEE_RUPEES__ = <?= (int) $platformFeeRupees ?>;
    window.__CHECKOUT_ITEMS__ = <?= json_encode($checkoutItemsPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__CHECKOUT_SUBTOTAL__ = <?= (int) $subtotal ?>;
    window.__CART_SPEED_FEES__ = <?= json_encode(['express' => $expressFeeRu, 'same_day' => $sameDayFeeRu], JSON_THROW_ON_ERROR) ?>;
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
    const total = sub + platformFee + ship;
    latestTotal = total;
    const delEl = document.getElementById("coDeliveryEl");
    if (delEl) {
      delEl.textContent = ship === 0 ? "FREE" : "₹" + ship.toLocaleString("en-IN");
      delEl.className = ship === 0 ? "text-green" : "";
    }
    const totEl = document.getElementById("coTotalEl");
    if (totEl) totEl.textContent = "₹" + total.toLocaleString("en-IN");
    const payAmt = document.getElementById("coPayAmount");
    if (payAmt) payAmt.textContent = total.toLocaleString("en-IN");
    if (selectedPaymentMethod() === "UPI" && selectedUpiMode() === "qr") {
      updateUpiQr(total);
    }
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
  const cardDetailsWrap = document.getElementById("cardDetailsWrap");
  const upiDetailsWrap = document.getElementById("upiDetailsWrap");
  const codDetailsWrap = document.getElementById("codDetailsWrap");
  const upiIdEntry = document.getElementById("upiIdEntry");
  const upiQrEntry = document.getElementById("upiQrEntry");
  const upiQrImage = document.getElementById("upiQrImage");
  const upiQrAmountText = document.getElementById("upiQrAmountText");
  const upiQrPayeeText = document.getElementById("upiQrPayeeText");
  const upiQrPayeeVpa = "luxe@upi";
  const upiQrPayeeName = "LUXE Store";
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
    return payEl ? String(payEl.value) : "Card";
  }

  function selectedUpiMode() {
    const upiEl = document.querySelector('input[name="upi_mode"]:checked');
    return upiEl ? String(upiEl.value) : "id";
  }

  function togglePaymentDetails() {
    const payment = selectedPaymentMethod();
    if (cardDetailsWrap) cardDetailsWrap.classList.toggle("hidden", payment !== "Card");
    if (upiDetailsWrap) upiDetailsWrap.classList.toggle("hidden", payment !== "UPI");
    if (codDetailsWrap) codDetailsWrap.classList.toggle("hidden", payment !== "COD");
    if (payment === "UPI") toggleUpiModeDetails();
  }

  function updateUpiQr(totalAmount) {
    const amount = Math.max(0, Number(totalAmount) || 0);
    const upiUri = `upi://pay?pa=${encodeURIComponent(upiQrPayeeVpa)}&pn=${encodeURIComponent(upiQrPayeeName)}&am=${encodeURIComponent(amount.toFixed(2))}&cu=INR&tn=${encodeURIComponent("LUXE Order Payment")}`;
    if (upiQrImage) {
      upiQrImage.src = "https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=" + encodeURIComponent(upiUri);
    }
    if (upiQrAmountText) {
      upiQrAmountText.textContent = "₹" + amount.toLocaleString("en-IN");
    }
    if (upiQrPayeeText) {
      upiQrPayeeText.textContent = upiQrPayeeName + " · " + upiQrPayeeVpa;
    }
  }

  function toggleUpiModeDetails() {
    const mode = selectedUpiMode();
    if (upiIdEntry) upiIdEntry.classList.toggle("hidden", mode !== "id");
    if (upiQrEntry) upiQrEntry.classList.toggle("hidden", mode !== "qr");
    if (mode === "qr") updateUpiQr(latestTotal);
  }

  function validatePaymentDetails() {
    const payment = selectedPaymentMethod();
    if (payment === "Card") {
      const number = (document.getElementById("payCardNumber")?.value || "").replace(/\s+/g, "");
      const name = (document.getElementById("payCardName")?.value || "").trim();
      const expiry = (document.getElementById("payCardExpiry")?.value || "").trim();
      const cvv = (document.getElementById("payCardCvv")?.value || "").trim();
      if (number.length < 12 || !/^\d+$/.test(number)) {
        showToast("⚠️ Enter a valid card number.");
        return false;
      }
      if (name.length < 2) {
        showToast("⚠️ Enter card holder name.");
        return false;
      }
      if (!/^\d{2}\/\d{2}$/.test(expiry)) {
        showToast("⚠️ Enter expiry in MM/YY format.");
        return false;
      }
      if (!/^\d{3,4}$/.test(cvv)) {
        showToast("⚠️ Enter a valid CVV.");
        return false;
      }
    } else if (payment === "UPI") {
      if (selectedUpiMode() === "id") {
        const upi = (document.getElementById("payUpiId")?.value || "").trim();
        if (!/^[a-zA-Z0-9.\-_]{2,}@[a-zA-Z]{2,}$/i.test(upi)) {
          showToast("⚠️ Enter a valid UPI ID.");
          return false;
        }
      }
    }
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
      if (pay === "Card") {
        const raw = (document.getElementById("payCardNumber")?.value || "").replace(/\s+/g, "");
        const tail = raw.length >= 4 ? raw.slice(-4) : "****";
        reviewPaymentText.textContent = "Card •••• " + tail;
      } else if (pay === "UPI") {
        if (selectedUpiMode() === "qr") {
          reviewPaymentText.textContent = "UPI QR (Scan & Pay)";
        } else {
          const upi = (document.getElementById("payUpiId")?.value || "").trim();
          reviewPaymentText.textContent = "UPI (" + (upi || "not set") + ")";
        }
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
    el.addEventListener("change", togglePaymentDetails);
  });
  document.querySelectorAll('input[name="upi_mode"]').forEach(el => {
    el.addEventListener("change", toggleUpiModeDetails);
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
  if (btn) {
    btn.addEventListener("click", async function () {
      const addressId = resolveCheckoutAddressId();
      if (!addressId) {
        if (typeof showToast === "function") showToast("⚠️ Please add a delivery address in your profile.");
        else alert("Add a delivery address in your profile.");
        return;
      }
      const payment = selectedPaymentMethod();
      btn.disabled = true;
      try {
        const url = window.__API_PLACE_ORDER__ || "actions/place-order.php";
        const r = await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({
            items: items,
            address_id: addressId,
            payment_method: payment,
            delivery_speed: speedMode()
          })
        });
        const data = await r.json();
        if (data.ok && data.order_ref) {
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
    });
  }

  setStep(1);
  togglePaymentDetails();
  toggleUpiModeDetails();
  void fetchDeliveryFees();
})();
  </script>
</body>
</html>
