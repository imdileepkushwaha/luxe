<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart_session.php';
require_once __DIR__ . '/../includes/coupons.php';

$pdo  = db();
$user = auth_user($pdo);
if (!$user) {
    header('Location: login.php?redirect=' . rawurlencode('checkout.php'));
    exit;
}

$cartItems = cart_filter_available_items($pdo, $_SESSION['cart'] ?? []);
$_SESSION['cart'] = $cartItems;
$selected = array_values(array_filter($cartItems, fn($x) => (bool)($x['checked'] ?? true)));
if ($selected === []) { header('Location: cart.php'); exit; }

$uid       = (int)($user['id'] ?? 0);
$addresses = addresses_fetch_for_user($pdo, $uid);
$defAddrId = 0;
foreach ($addresses as $a) { if (!empty($a['isDefault'])) { $defAddrId = (int)$a['id']; break; } }
if ($defAddrId === 0 && $addresses !== []) $defAddrId = (int)($addresses[0]['id'] ?? 0);

$subtotal = 0;
foreach ($selected as $li) $subtotal += max(1,(int)($li['qty']??1)) * max(0,(int)($li['price']??0));

$linesForShip = array_map(fn($li) => ['id'=>(int)($li['id']??0),'price'=>max(0,(int)($li['price']??0)),'qty'=>max(1,(int)($li['qty']??1))], $selected);
$baseDelivery    = cart_compute_delivery_total($pdo, $linesForShip);
$speedFees       = cart_speed_fee_totals_for_lines($pdo, $linesForShip);
$expressFeeRu    = (int)($speedFees['express']  ?? 0);
$sameDayFeeRu    = (int)($speedFees['same_day'] ?? 0);
$platformFeeRu   = site_platform_fee_rupees($pdo);
// Free delivery if subtotal >= 1000
if ($subtotal >= 1000) { $baseDelivery = 0; }
$initialTotal    = $subtotal + $platformFeeRu + $baseDelivery;
$couponDefsJs    = coupons_defs_for_frontend($pdo);
$couponFeatCodes = coupons_featured_tag_codes($pdo, 5);
$checkoutPayload = array_map(fn($x) => ['id'=>(int)($x['id']??0),'qty'=>max(1,(int)($x['qty']??1)),'size'=>(string)($x['size']??''),'color'=>(string)($x['color']??''),'price'=>max(0,(int)($x['price']??0)),'seller_id'=>max(0,(int)($x['seller_id']??0))], $selected);

$cartCount = 0;
foreach ($cartItems as $ci) $cartCount += (int)($ci['qty']??1);
$fullName = trim(($user['first_name']??'').' '.($user['last_name']??'')) ?: 'Member';
$initial  = strtoupper(substr((string)($user['first_name']??$fullName),0,1));
$isLoggedIn = true; $userInitials = $initial; $userName = $fullName;
$userEmail = trim((string)($user['email']??''));
$theme1LoginHref = 'login.php'; $theme1HeaderCategories = ["Men's Fashion","Women's Fashion","Kid's Fashion",'Footwear'];
$theme1HeaderCompareCount = 0; $theme1HeaderCartCount = $cartCount; $theme1FooterCategories = $theme1HeaderCategories;

$defaultShipState = '';
foreach ($addresses as $a) {
    if ((int) ($a['id'] ?? 0) === $defAddrId) {
        $defaultShipState = trim((string) ($a['state'] ?? ''));
        break;
    }
}

function t1co_thumb(array $line): string {
    $img = trim((string)($line['image']??$line['image_path']??''));
    if ($img===''||strcasecmp($img,'default')===0) return '';
    if (!preg_match('#^(https?:)?//#i',$img)&&!str_starts_with($img,'/')) $img=luxe_public_href(ltrim($img,'/'));
    return $img;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Checkout — LUXE</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="profile-page-wrap t1-co-page t2-account-layout t2-cart-checkout">
<?php require __DIR__.'/partials/header.php'; ?>
<main>
   <div class="cart-main-wrap">
<div class="container">

  <!-- Header -->
  <div class="t1-co-header">
    <h1>Checkout</h1>
    <a href="cart.php" class="t1-continue-link">← Back to Cart</a>
  </div>

  <!-- Steps -->
  <div class="t1-co-steps">
    <span class="t1-co-step active" id="pill1">📍 Address</span>
    <span class="t1-co-step" id="pill2">💳 Payment</span>
    <span class="t1-co-step" id="pill3">✅ Review</span>
  </div>

  <div class="t1-co-layout">
    <!-- LEFT -->
    <div id="leftCol">

      <!-- STEP 1: Address -->
      <div class="t1-co-card" id="step1">
        <div class="t1-co-card-title">📍 Delivery Address</div>
        <button class="t1-co-add-addr-btn" onclick="openAddrModal()">+ Add New Address</button>
        <div class="t1-co-addr-grid" id="addrGrid">
          <?php if ($addresses === []): ?>
            <div class="t1-co-empty-addr">No saved address. Add one to continue.</div>
          <?php else: foreach ($addresses as $a):
            $aId = (int)($a['id']??0); $aLine2 = trim((string)($a['line2']??''));
            $aType = (string)($a['type']??'Home');
            $aIsDefault = !empty($a['isDefault']);
          ?>
          <label class="t1-co-addr-card <?= $aId===$defAddrId?'selected':'' ?>">
            <input type="radio" name="co_addr" value="<?= $aId ?>" <?= $aId===$defAddrId?'checked':'' ?> onchange="document.querySelectorAll('.t1-co-addr-card').forEach(c=>c.classList.remove('selected'));this.closest('.t1-co-addr-card').classList.add('selected')">
            <span class="t1-co-addr-radio"></span>
            <div class="t1-co-addr-body">
              <div class="t1-co-addr-header">
                <span class="t1-co-addr-name"><?= h((string)($a['name']??'')) ?></span>
                <span class="t1-co-addr-type"><?= h($aType) ?></span>
                <?php if ($aIsDefault): ?><span class="t1-co-addr-tag">Default</span><?php endif; ?>
              </div>
              <div class="t1-co-addr-line">
                <?= h((string)($a['line1']??'')) ?><?= $aLine2!==''?', '.h($aLine2):'' ?><br>
                <?= h((string)($a['city']??'')) ?>, <?= h((string)($a['state']??'')) ?> — <?= h((string)($a['pin']??'')) ?>
              </div>
              <?php if (!empty($a['phone'])): ?>
                <div class="t1-co-addr-phone">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                  <?= h((string)$a['phone']) ?>
                </div>
              <?php endif; ?>
              <div class="t1-co-addr-actions">
                <?php if (!$aIsDefault): ?>
                  <button type="button" class="t1-co-addr-action-btn" onclick="event.preventDefault();setDefaultAddr(<?= $aId ?>)" title="Set as Default">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    Set Default
                  </button>
                <?php endif; ?>
                <button type="button" class="t1-co-addr-action-btn" onclick="event.preventDefault();editAddr(<?= $aId ?>)" title="Edit">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </button>
                <button type="button" class="t1-co-addr-action-btn danger" onclick="event.preventDefault();deleteAddr(<?= $aId ?>)" title="Delete">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                  Delete
                </button>
              </div>
            </div>
          </label>
          <?php endforeach; endif; ?>
        </div>
        <div class="t1-co-btn-row">
          <button class="t1-co-btn" onclick="goStep(2)" <?= $addresses===[]?'disabled':'' ?>>Continue to Payment →</button>
        </div>
      </div>

      <!-- STEP 2: Payment -->
      <div class="t1-co-card hidden" id="step2">
        <div class="t1-co-card-title">💳 Payment Method</div>
        <div class="t1-pm-grid">
          <label class="t1-pm-card selected" onclick="this.parentNode.querySelectorAll('.t1-pm-card').forEach(c=>c.classList.remove('selected'));this.classList.add('selected')">
            <input type="radio" name="co_pay" value="COD" checked>
            <span class="t1-pm-icon">💵</span>
            <div><div class="t1-pm-title">Cash on Delivery</div><div class="t1-pm-desc">Pay when delivered</div></div>
          </label>
          <label class="t1-pm-card" onclick="this.parentNode.querySelectorAll('.t1-pm-card').forEach(c=>c.classList.remove('selected'));this.classList.add('selected')">
            <input type="radio" name="co_pay" value="ONLINE">
            <span class="t1-pm-icon">🌐</span>
            <div><div class="t1-pm-title">Online Payment</div><div class="t1-pm-desc">UPI / Cards / Netbanking</div></div>
          </label>
        </div>
        <div class="t1-co-note" id="payNote">💵 Cash on Delivery — Keep exact change ready for smoother delivery.</div>
        <div class="t1-co-btn-row">
          <button class="t1-co-back-btn" onclick="goStep(1)">← Back</button>
          <button class="t1-co-btn" onclick="goStep(3)">Continue to Review →</button>
        </div>
      </div>

      <!-- STEP 3: Review -->
      <div class="t1-co-card hidden" id="step3">
        <div class="t1-co-card-title">✅ Final Review</div>
        <div class="t1-co-review-row">
          <div class="t1-co-review-item">
            <div><div class="t1-co-review-lbl">Delivery Address</div><div class="t1-co-review-val" id="rvAddr">—</div></div>
          </div>
          <div class="t1-co-review-item">
            <div><div class="t1-co-review-lbl">Payment Method</div><div class="t1-co-review-val" id="rvPay">—</div></div>
          </div>
          <div class="t1-co-review-item">
            <div><div class="t1-co-review-lbl">Delivery Speed</div><div class="t1-co-review-val" id="rvSpeed">Standard (3-5 days)</div></div>
          </div>
        </div>
        <div class="t1-co-btn-row" style="margin-top:24px">
          <button class="t1-co-back-btn" onclick="goStep(2)">← Back</button>
          <button class="t1-co-btn" id="placeBtn" onclick="placeOrder()">
            Place Order — ₹<span id="payAmt"><?= number_format($initialTotal) ?></span>
          </button>
        </div>
        <p id="coMsg" style="color:#ef4444;font-size:14px;margin-top:12px;display:none"></p>
      </div>

    </div>

    <!-- RIGHT: Order Summary -->
    <div class="t1-co-sum-card">
      <div class="t1-co-sum-title">Order Summary</div>

      <!-- Coupon -->
      <div class="t1-co-coupon-box">
        <div class="t1-co-coupon-lbl">🏷️ Apply Coupon</div>
        <div class="t1-co-coupon-row">
          <input type="text" class="t1-co-coupon-input" id="promoInput" placeholder="Enter code">
          <button class="t1-co-coupon-btn" onclick="applyCoupon()">Apply</button>
        </div>
        <div class="t1-cart-coupon-tags" id="couponTagsRow"></div>
        <div class="t1-cart-coupon-tag-ok" id="couponOk"></div>
        <div class="t1-cart-coupon-tag-error" id="couponErr"></div>
      </div>

      <!-- Delivery Speed -->
      <div class="t1-co-speed-box">
        <div class="t1-co-speed-lbl t2-shipping-heading">Shipping</div>
        <?php if ($subtotal >= 1000): ?>
        <div class="ti-co-speed-free">
          🎉 FREE delivery on orders above ₹1,000!
        </div>
        <?php endif; ?>
        <div class="t1-co-speed-opts">
          <label class="t1-co-speed-opt active" id="sopt-standard">
            <input type="radio" name="co_speed" value="standard" checked onchange="onSpeedChange(this)">
            <div style="flex:1"><strong>Standard (3–5 days)</strong></div>
            <span style="color:<?= $baseDelivery===0?'#10b981':'#0f172a' ?>;font-weight:600"><?= $baseDelivery===0?'FREE':'₹'.number_format($baseDelivery) ?></span>
          </label>
          <label class="t1-co-speed-opt" id="sopt-express">
            <input type="radio" name="co_speed" value="express" onchange="onSpeedChange(this)">
            <div style="flex:1"><strong>Express (1–2 days)</strong></div>
            <span id="exFee"><?= $expressFeeRu>0?'₹'.number_format($expressFeeRu):'FREE' ?></span>
          </label>
          <label class="t1-co-speed-opt" id="sopt-same_day">
            <input type="radio" name="co_speed" value="same_day" onchange="onSpeedChange(this)">
            <div style="flex:1"><strong>Same Day</strong></div>
            <span id="sdFee"><?= $sameDayFeeRu>0?'₹'.number_format($sameDayFeeRu):'FREE' ?></span>
          </label>
        </div>
      </div>

      <div class="t2-co-ship-to">
        <p class="t2-co-ship-to-line">
          <span id="coShipToText"><?= $defaultShipState !== '' ? 'Shipping to ' . h($defaultShipState) . '.' : ($addresses === [] ? 'Add a delivery address to see shipping.' : 'Select a delivery address.') ?></span>
        </p>
        <button type="button" class="t2-co-change-addr" onclick="goStep(1); window.scrollTo({ top: 0, behavior: 'smooth' });">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Change address
        </button>
      </div>

      <!-- Fee Breakdown -->
      <div class="t1-co-fee-rows">
        <div class="t1-co-fee-row"><span>Subtotal</span><span id="summarySubtotal">₹<?= number_format($subtotal) ?></span></div>
        <div class="t1-co-fee-row"><span>Delivery Charges</span><span class="green" id="feeDelivery"><?= $baseDelivery===0?'FREE':'₹'.number_format($baseDelivery) ?></span></div>
        <div class="t1-co-fee-row"><span>Platform Fee</span><span>₹<?= number_format($platformFeeRu) ?></span></div>
        <div class="t1-co-fee-row" id="discRow" style="display:none">
          <span>Coupon Discount</span>
          <div style="display:flex;align-items:center;gap:8px;">
            <span class="green" id="discVal">-₹0</span>
            <button class="t1-co-coupon-remove-icon" id="removeCouponBtn" onclick="removeCoupon()" title="Remove Coupon">✕</button>
          </div>
        </div>
        <div class="t1-co-fee-row total"><span>Total Amount</span><span id="feeTotal">₹<?= number_format($initialTotal) ?></span></div>
      </div>

      <button type="button" class="t1-co-place-btn t2-cart-checkout-btn" id="placeBtnSum" onclick="placeOrder()">
        Place Order — ₹<span id="payAmtSum"><?= number_format($initialTotal) ?></span>
      </button>
      <div class="t1-co-secure">🔒 Secured by 256-bit SSL encryption</div>
    </div>
  </div>

</div>
</div>
</main>

<!-- Address Modal -->
<div class="theme1-modal-overlay hidden" id="addrModal">
  <div class="theme1-modal-card" style="max-width:560px">
    <div class="theme1-modal-header">
      <h3>Add New Address</h3>
      <button class="theme1-modal-close" onclick="closeAddrModal()">✕</button>
    </div>
    <form id="addrForm" onsubmit="saveAddr(event)">
      <div class="profile-edit-grid">
        <div class="profile-edit-field"><label>Full Name</label><input name="full_name" required placeholder="Rahul Sharma"></div>
        <div class="profile-edit-field"><label>Phone</label><input name="phone" placeholder="+91 9876543210"></div>
        <div class="profile-edit-field" style="grid-column:1/-1"><label>Address Line 1</label><input name="line1" required placeholder="House/Flat No., Street"></div>
        <div class="profile-edit-field" style="grid-column:1/-1"><label>Address Line 2</label><input name="line2" placeholder="Landmark (optional)"></div>
        <div class="profile-edit-field"><label>City</label><input name="city" required placeholder="Mumbai"></div>
        <div class="profile-edit-field"><label>PIN Code</label><input name="pin" required placeholder="400001" inputmode="numeric"></div>
        <div class="profile-edit-field"><label>State</label><input name="state" required placeholder="Maharashtra"></div>
        <div class="profile-edit-field"><label>Type</label><select name="type"><option value="Home">Home</option><option value="Work">Work</option><option value="Other">Other</option></select></div>
      </div>
      <div class="profile-edit-actions" style="margin-top:16px">
        <button type="button" class="profile-edit-cancel" onclick="closeAddrModal()">Cancel</button>
        <button type="submit" class="profile-edit-btn">Save Address</button>
      </div>
    </form>
  </div>
</div>

<div class="t1-cart-toast" id="coToast"></div>
<?php require __DIR__.'/partials/footer.php'; ?>
<script>
const SUBTOTAL    = <?= (int)$subtotal ?>;
const PLATFORM    = <?= (int)$platformFeeRu ?>;
const BASE_DEL    = <?= (int)$baseDelivery ?>;
const EXPRESS_FEE = <?= (int)$expressFeeRu ?>;
const SAMEDAY_FEE = <?= (int)$sameDayFeeRu ?>;
const COUPON_DEFS = <?= json_encode($couponDefsJs, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;
const ITEMS       = <?= json_encode($checkoutPayload, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;
const ADDRESSES   = <?= json_encode($addresses, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;
const LUXE_ACT = <?= json_encode(luxe_actions_root_url(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;

let couponDisc = 0;
let currentStep = 1;

function toast(msg, err=false) {
  const t = document.getElementById('coToast');
  t.textContent = msg; t.style.background = err?'#ef4444':'#0f172a';
  t.classList.add('show');
  clearTimeout(t._t); t._t = setTimeout(()=>t.classList.remove('show'),2800);
}

function goStep(n) {
  if (n===2) {
    const addr = document.querySelector('input[name="co_addr"]:checked');
    if (!addr && ADDRESSES.length>0) { toast('Please select a delivery address',true); return; }
    if (ADDRESSES.length===0) { toast('Please add a delivery address first',true); return; }
  }
  currentStep = n;
  [1,2,3].forEach(i => {
    document.getElementById('step'+i)?.classList.toggle('hidden', i!==n);
    const pill = document.getElementById('pill'+i);
    if (pill) { pill.classList.toggle('active', i===n); }
  });
  if (n===3) renderReview();
  window.scrollTo({top:0,behavior:'smooth'});
}

function renderReview() {
  const addr = document.querySelector('input[name="co_addr"]:checked');
  if (addr) {
    const a = ADDRESSES.find(x=>x.id==addr.value);
    document.getElementById('rvAddr').textContent = a ? `${a.name} — ${a.line1}, ${a.city}, ${a.state} ${a.pin}` : '—';
  }
  const pay = document.querySelector('input[name="co_pay"]:checked');
  document.getElementById('rvPay').textContent = pay?.value==='COD' ? 'Cash on Delivery' : 'Online Payment';
  const speed = document.querySelector('input[name="co_speed"]:checked');
  const speedMap = {standard:'Standard (3–5 days)',express:'Express (1–2 days)',same_day:'Same Day'};
  document.getElementById('rvSpeed').textContent = speedMap[speed?.value||'standard']||'Standard';
  updateShipToLine();
}

function updateShipToLine() {
  const el = document.getElementById('coShipToText');
  if (!el) return;
  const addr = document.querySelector('input[name="co_addr"]:checked');
  if (!addr) {
    el.textContent = ADDRESSES.length ? 'Select a delivery address.' : 'Add a delivery address.';
    return;
  }
  const a = ADDRESSES.find(x => String(x.id) === String(addr.value));
  el.textContent = (a && a.state) ? ('Shipping to ' + a.state + '.') : 'Delivery address selected.';
}

// Render coupon tags (sync with cart)
(function() {
  const codes = <?= json_encode($couponFeatCodes ?? [], JSON_HEX_TAG|JSON_HEX_AMP) ?>;
  const row = document.getElementById('couponTagsRow');
  if (row && codes.length) {
    codes.forEach(c => {
      const btn = document.createElement('span');
      btn.className = 't1-cart-coupon-tag';
      btn.textContent = c;
      btn.onclick = () => { document.getElementById('promoInput').value = c; applyCoupon(); };
      row.appendChild(btn);
    });
  }
  // Auto-apply from session
  try {
    const saved = sessionStorage.getItem('luxeCheckoutCoupon');
    if (saved) { document.getElementById('promoInput').value = saved; setTimeout(applyCoupon, 300); }
  } catch(_){}
})();

function getSpeedFee() {
  const v = document.querySelector('input[name="co_speed"]:checked')?.value||'standard';
  if (v==='express') return EXPRESS_FEE;
  if (v==='same_day') return SAMEDAY_FEE;
  return BASE_DEL;
}

function refreshTotal() {
  const del = getSpeedFee();
  const total = Math.max(0, SUBTOTAL + PLATFORM + del - couponDisc);
  const fmtINR = n => '₹'+parseInt(n).toLocaleString('en-IN');
  const delEl = document.getElementById('feeDelivery');
  if (delEl) {
    delEl.textContent = del === 0 ? 'FREE' : fmtINR(del);
    delEl.className = del === 0 ? 'green' : '';
  }
  document.getElementById('feeTotal').textContent = fmtINR(total);
  document.getElementById('payAmt').textContent = parseInt(total).toLocaleString('en-IN');
  document.getElementById('payAmtSum').textContent = parseInt(total).toLocaleString('en-IN');
  document.querySelectorAll('input[name="co_speed"]').forEach(r => {
    document.getElementById('sopt-'+r.value)?.classList.toggle('active', r.checked);
  });
}

function onSpeedChange(el) {
  document.querySelectorAll('.t1-co-speed-opt').forEach(o=>o.classList.remove('active'));
  el.closest('.t1-co-speed-opt').classList.add('active');
  document.getElementById('rvSpeed') && renderReview();
  refreshTotal();
}

function applyCoupon() {
  const code = document.getElementById('promoInput').value.trim().toUpperCase();
  const ok = document.getElementById('couponOk');
  const er = document.getElementById('couponErr');
  ok.style.display='none'; er.style.display='none'; couponDisc=0;
  document.getElementById('discRow').style.display='none';
  if (!code) { er.textContent='Enter a coupon code'; er.style.display='flex'; return; }
  const def = COUPON_DEFS[code];
  if (!def) { er.textContent='Invalid coupon code'; er.style.display='flex'; return; }
  const minOrder = Number(def.min_order||0);
  if (SUBTOTAL < minOrder) { er.textContent=`Min order ₹${minOrder} required`; er.style.display='flex'; return; }
  let d = 0;
  if (def.type==='percent') { const cap=def.max?Number(def.max):Infinity; d=Math.min(Math.round(SUBTOTAL*Number(def.val)/100),cap); }
  else d = Number(def.val)||0;
  couponDisc = Math.min(d, SUBTOTAL);
  if (couponDisc>0) {
    document.getElementById('discRow').style.display='flex';
    document.getElementById('discVal').textContent='-₹'+couponDisc.toLocaleString('en-IN');
    ok.textContent='✓ Saved ₹'+couponDisc.toLocaleString('en-IN')+'!';
    ok.style.display='flex';
    try { sessionStorage.setItem('luxeCheckoutCoupon', code); } catch(_){}
    refreshTotal();
  }
}

function removeCoupon() {
  couponDisc = 0;
  document.getElementById('promoInput').value = '';
  document.getElementById('discRow').style.display = 'none';
  document.getElementById('couponOk').style.display = 'none';
  document.getElementById('couponErr').style.display = 'none';
  try { sessionStorage.removeItem('luxeCheckoutCoupon'); } catch(_) {}
  refreshTotal();
}

document.querySelectorAll('input[name="co_pay"]').forEach(r => r.addEventListener('change', function() {
  document.querySelectorAll('.t1-pm-card').forEach(c=>c.classList.remove('selected'));
  this.closest('.t1-pm-card').classList.add('selected');
  const note = document.getElementById('payNote');
  if (note) note.textContent = this.value==='COD' ? '💵 Cash on Delivery — Keep exact change ready.' : '🌐 You will be redirected to complete payment after placing order.';
}));

async function placeOrder() {
  const addr = document.querySelector('input[name="co_addr"]:checked');
  if (!addr) { toast('Select a delivery address',true); goStep(1); return; }
  const pay   = document.querySelector('input[name="co_pay"]:checked');
  const speed = document.querySelector('input[name="co_speed"]:checked');
  const btn1 = document.getElementById('placeBtn');
  const btn2 = document.getElementById('placeBtnSum');
  [btn1,btn2].forEach(b=>b&&(b.disabled=true));
  const msg = document.getElementById('coMsg');
  if (msg) msg.style.display='none';
  try {
    const coupon = (()=>{ try{return sessionStorage.getItem('luxeCheckoutCoupon')||'';}catch(_){return '';} })();
    const res = await fetch(LUXE_ACT + 'place-order.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({items:ITEMS, address_id:parseInt(addr.value,10), payment_method: pay?.value||'COD', delivery_speed: speed?.value||'standard', coupon_code: coupon})
    });
    const data = await res.json();
    if (data.ok && data.order_ref) {
      try{sessionStorage.removeItem('luxeCheckoutCoupon');}catch(_){}
      window.location.href = 'orders.php?placed=' + encodeURIComponent(data.order_ref);
      return;
    }
    const errMsg = data.message || 'Could not place order.';
    toast(errMsg, true);
    if (msg) { msg.textContent=errMsg; msg.style.display='block'; }
  } catch(e) {
    toast('Network error — try again.',true);
  }
  [btn1,btn2].forEach(b=>b&&(b.disabled=false));
}

function openAddrModal() { document.getElementById('addrModal').classList.remove('hidden'); }
function closeAddrModal() { document.getElementById('addrModal').classList.add('hidden'); }

async function saveAddr(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const btn = e.target.querySelector('button[type="submit"]');
  btn.disabled=true; btn.textContent='Saving…';
  try {
    const res = await fetch(LUXE_ACT + 'save-address.php', {method:'POST',body:fd});
    const data = await res.json();
    if (data.ok) { toast('Address saved!'); closeAddrModal(); location.reload(); }
    else toast(data.message||'Could not save',true);
  } catch(_) { toast('Network error',true); }
  btn.disabled=false; btn.textContent='Save Address';
}


async function setDefaultAddr(id) {
  try {
    const res = await fetch(LUXE_ACT + 'set-default-address.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id})
    });
    const data = await res.json();
    if (data.ok) { toast('Default address updated'); location.reload(); }
    else toast(data.message||'Could not set default', true);
  } catch(_) { toast('Network error', true); }
}

async function deleteAddr(id) {
  if (!confirm('Are you sure you want to delete this address?')) return;
  try {
    const res = await fetch(LUXE_ACT + 'delete-address.php', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id})
    });
    const data = await res.json();
    if (data.ok) { toast('Address deleted'); location.reload(); }
    else toast(data.message||'Could not delete', true);
  } catch(_) { toast('Network error', true); }
}

function editAddr(id) {
  const a = ADDRESSES.find(x => x.id == id);
  if (!a) return;
  const form = document.getElementById('addrForm');
  form.querySelector('[name="full_name"]').value = a.name || '';
  form.querySelector('[name="phone"]').value = a.phone || '';
  form.querySelector('[name="line1"]').value = a.line1 || '';
  form.querySelector('[name="line2"]').value = a.line2 || '';
  form.querySelector('[name="city"]').value = a.city || '';
  form.querySelector('[name="pin"]').value = a.pin || '';
  form.querySelector('[name="state"]').value = a.state || '';
  form.querySelector('[name="type"]').value = a.type || 'Home';
  // Add hidden field for address ID to enable update
  let hiddenId = form.querySelector('[name="address_id"]');
  if (!hiddenId) {
    hiddenId = document.createElement('input');
    hiddenId.type = 'hidden'; hiddenId.name = 'address_id';
    form.appendChild(hiddenId);
  }
  hiddenId.value = id;
  document.querySelector('#addrModal h3').textContent = 'Edit Address';
  openAddrModal();
}

document.querySelectorAll('input[name="co_addr"]').forEach(r => r.addEventListener('change', updateShipToLine));
updateShipToLine();

refreshTotal();
</script>
</body>
</html>
