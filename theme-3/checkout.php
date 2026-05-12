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
      <div class="luxe-co-header">
        <h1>Checkout</h1>
        <a href="cart.php" class="luxe-continue-link">← Back to Cart</a>
      </div>

      <!-- Steps -->
      <div class="luxe-co-steps">
        <span class="luxe-co-step active" id="pill1">
          <div class="step-num">1</div>
          <div class="step-text">Address</div>
        </span>
        <span class="luxe-co-step" id="pill2">
          <div class="step-num">2</div>
          <div class="step-text">Payment</div>
        </span>
        <span class="luxe-co-step" id="pill3">
          <div class="step-num">3</div>
          <div class="step-text">Review</div>
        </span>
      </div>

      <div class="luxe-cart-layout">
        <!-- LEFT -->
        <div class="luxe-co-items-col" id="leftCol">

          <!-- STEP 1: Address -->
          <div class="luxe-co-card" id="step1">
            <div class="luxe-co-card-header">
              <h2 class="luxe-co-card-title">Delivery Address</h2>
              <button class="luxe-co-add-addr-btn" onclick="openAddrModal()">+ Add New</button>
            </div>
            
            <div class="luxe-co-addr-grid" id="addrGrid">
              <?php if ($addresses === []): ?>
                <div class="luxe-co-empty-addr">
                  <div class="empty-icon">📍</div>
                  <p>No saved addresses yet.</p>
                  <button type="button" class="luxe-co-add-addr-btn outline" onclick="openAddrModal()">Add your first address</button>
                </div>
              <?php else: foreach ($addresses as $a):
                $aId = (int)($a['id']??0); $aLine2 = trim((string)($a['line2']??''));
                $aType = (string)($a['type']??'Home');
                $aIsDefault = !empty($a['isDefault']);
              ?>
              <label class="luxe-addr-card <?= $aId===$defAddrId?'selected':'' ?>">
                <input type="radio" name="co_addr" value="<?= $aId ?>" <?= $aId===$defAddrId?'checked':'' ?> onchange="document.querySelectorAll('.luxe-addr-card').forEach(c=>c.classList.remove('selected'));this.closest('.luxe-addr-card').classList.add('selected')">
                <span class="luxe-addr-radio"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></span>
                
                <div class="luxe-addr-body">
                  <div class="luxe-addr-header">
                    <span class="luxe-addr-name"><?= h((string)($a['name']??'')) ?></span>
                    <div class="luxe-addr-tags">
                      <span class="luxe-addr-type"><?= h($aType) ?></span>
                      <?php if ($aIsDefault): ?><span class="luxe-addr-tag-default">Default</span><?php endif; ?>
                    </div>
                  </div>
                  <div class="luxe-addr-line">
                    <?= h((string)($a['line1']??'')) ?><?= $aLine2!==''?', '.h($aLine2):'' ?><br>
                    <?= h((string)($a['city']??'')) ?>, <?= h((string)($a['state']??'')) ?> — <?= h((string)($a['pin']??'')) ?>
                  </div>
                  <?php if (!empty($a['phone'])): ?>
                    <div class="luxe-addr-phone">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                      <?= h((string)$a['phone']) ?>
                    </div>
                  <?php endif; ?>
                  
                  <div class="luxe-addr-actions">
                    <?php if (!$aIsDefault): ?>
                      <button type="button" class="luxe-addr-action-btn" onclick="event.preventDefault();setDefaultAddr(<?= $aId ?>)" title="Set as Default">Set Default</button>
                    <?php endif; ?>
                    <button type="button" class="luxe-addr-action-btn" onclick="event.preventDefault();editAddr(<?= $aId ?>)" title="Edit">Edit</button>
                    <button type="button" class="luxe-addr-action-btn danger" onclick="event.preventDefault();deleteAddr(<?= $aId ?>)" title="Delete">Delete</button>
                  </div>
                </div>
              </label>
              <?php endforeach; endif; ?>
            </div>
            
            <div class="luxe-co-btn-row">
              <button class="luxe-co-btn primary" onclick="goStep(2)" <?= $addresses===[]?'disabled':'' ?>>Continue to Payment →</button>
            </div>
          </div>

          <!-- STEP 2: Payment -->
          <div class="luxe-co-card hidden" id="step2">
            <h2 class="luxe-co-card-title">Payment Method</h2>
            
            <div class="luxe-pm-grid">
              <label class="luxe-pm-card selected" onclick="this.parentNode.querySelectorAll('.luxe-pm-card').forEach(c=>c.classList.remove('selected'));this.classList.add('selected')">
                <input type="radio" name="co_pay" value="COD" checked>
                <div class="luxe-pm-radio"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="luxe-pm-body">
                  <div class="luxe-pm-title">Cash on Delivery</div>
                  <div class="luxe-pm-desc">Pay when delivered</div>
                </div>
                <div class="luxe-pm-icon">💵</div>
              </label>
              
              <label class="luxe-pm-card" onclick="this.parentNode.querySelectorAll('.luxe-pm-card').forEach(c=>c.classList.remove('selected'));this.classList.add('selected')">
                <input type="radio" name="co_pay" value="ONLINE">
                <div class="luxe-pm-radio"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                <div class="luxe-pm-body">
                  <div class="luxe-pm-title">Online Payment</div>
                  <div class="luxe-pm-desc">UPI, Cards, Netbanking</div>
                </div>
                <div class="luxe-pm-icon">🌐</div>
              </label>
            </div>
            
            <div class="luxe-co-note" id="payNote">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <span>Cash on Delivery — Please keep exact change ready for smoother delivery.</span>
            </div>
            
            <div class="luxe-co-btn-row">
              <button class="luxe-co-back-btn" onclick="goStep(1)">← Back</button>
              <button class="luxe-co-btn primary" onclick="goStep(3)">Review Order →</button>
            </div>
          </div>

          <!-- STEP 3: Review -->
          <div class="luxe-co-card hidden" id="step3">
            <h2 class="luxe-co-card-title">Final Review</h2>
            
            <div class="luxe-co-review-grid">
              <div class="luxe-co-review-item">
                <div class="luxe-co-review-icon">📍</div>
                <div class="luxe-co-review-body">
                  <div class="luxe-co-review-lbl">Delivery Address</div>
                  <div class="luxe-co-review-val" id="rvAddr">—</div>
                </div>
              </div>
              
              <div class="luxe-co-review-item">
                <div class="luxe-co-review-icon">💳</div>
                <div class="luxe-co-review-body">
                  <div class="luxe-co-review-lbl">Payment Method</div>
                  <div class="luxe-co-review-val" id="rvPay">—</div>
                </div>
              </div>
              
              <div class="luxe-co-review-item">
                <div class="luxe-co-review-icon">🚚</div>
                <div class="luxe-co-review-body">
                  <div class="luxe-co-review-lbl">Delivery Speed</div>
                  <div class="luxe-co-review-val" id="rvSpeed">Standard (3-5 days)</div>
                </div>
              </div>
            </div>
            
            <div class="luxe-co-btn-row" style="margin-top:30px">
              <button class="luxe-co-back-btn" onclick="goStep(2)">← Back</button>
              <button class="luxe-co-btn primary" id="placeBtn" onclick="placeOrder()">
                Place Order — ₹<span id="payAmt"><?= number_format($initialTotal) ?></span>
              </button>
            </div>
            <p id="coMsg" class="luxe-co-error-msg" style="display:none"></p>
          </div>

        </div>

        <!-- RIGHT: Order Summary -->
        <div class="luxe-cart-summary-col">
          <div class="luxe-co-sum-card">
            <h3 class="luxe-co-sum-title">Order Summary</h3>

            <!-- Coupon -->
            <div class="luxe-co-coupon-box">
              <div class="luxe-co-coupon-row">
                <input type="text" class="luxe-co-coupon-input" id="promoInput" placeholder="Enter coupon code">
                <button class="luxe-co-coupon-btn" onclick="applyCoupon()">Apply</button>
              </div>
              <div class="luxe-cart-coupon-tags" id="couponTagsRow"></div>
              <div class="luxe-cart-coupon-msg success" id="couponOk" style="display:none;"></div>
              <div class="luxe-cart-coupon-msg error" id="couponErr" style="display:none;"></div>
            </div>

            <!-- Delivery Speed -->
            <div class="luxe-co-speed-box">
              <div class="luxe-co-speed-lbl">Shipping Speed</div>
              <?php if ($subtotal >= 1000): ?>
              <div class="luxe-co-speed-free">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> FREE delivery on orders above ₹1,000
              </div>
              <?php endif; ?>
              <div class="luxe-co-speed-opts">
                <label class="luxe-co-speed-opt active" id="sopt-standard">
                  <input type="radio" name="co_speed" value="standard" checked onchange="onSpeedChange(this)">
                  <div class="opt-text">Standard <span>(3-5 days)</span></div>
                  <span id="cartStFee" class="opt-price" style="color:<?= $baseDelivery===0?'#10b981':'#0f172a' ?>;"><?= $baseDelivery===0?'FREE':'₹'.number_format($baseDelivery) ?></span>
                </label>
                <label class="luxe-co-speed-opt" id="sopt-express">
                  <input type="radio" name="co_speed" value="express" onchange="onSpeedChange(this)">
                  <div class="opt-text">Express <span>(1-2 days)</span></div>
                  <span id="exFee" class="opt-price"><?= $expressFeeRu>0?'₹'.number_format($expressFeeRu):'FREE' ?></span>
                </label>
                <label class="luxe-co-speed-opt" id="sopt-same_day">
                  <input type="radio" name="co_speed" value="same_day" onchange="onSpeedChange(this)">
                  <div class="opt-text">Same Day</div>
                  <span id="sdFee" class="opt-price"><?= $sameDayFeeRu>0?'₹'.number_format($sameDayFeeRu):'FREE' ?></span>
                </label>
              </div>
            </div>

            <div class="luxe-co-ship-to">
              <div class="luxe-co-ship-to-line">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span id="coShipToText"><?= $defaultShipState !== '' ? 'Shipping to ' . h($defaultShipState) . '.' : ($addresses === [] ? 'Add a delivery address.' : 'Select an address.') ?></span>
              </div>
              <button type="button" class="luxe-co-change-addr" onclick="goStep(1); window.scrollTo({ top: 0, behavior: 'smooth' });">Change</button>
            </div>

            <!-- Fee Breakdown -->
            <div class="luxe-co-fee-rows">
              <div class="luxe-co-fee-row"><span>Subtotal</span><span id="summarySubtotal">₹<?= number_format($subtotal) ?></span></div>
              <div class="luxe-co-fee-row"><span>Delivery Charges</span><span class="highlight" id="feeDelivery"><?= $baseDelivery===0?'FREE':'₹'.number_format($baseDelivery) ?></span></div>
              <div class="luxe-co-fee-row"><span>Platform Fee</span><span>₹<?= number_format($platformFeeRu) ?></span></div>
              <div class="luxe-co-fee-row discount" id="discRow" style="display:none">
                <span>Coupon Discount</span>
                <div style="display:flex;align-items:center;gap:6px;">
                  <span id="discVal">-₹0</span>
                  <button class="luxe-co-coupon-remove" id="removeCouponBtn" onclick="removeCoupon()">✕</button>
                </div>
              </div>
              <div class="luxe-co-fee-row total"><span>Total Amount</span><span id="feeTotal">₹<?= number_format($initialTotal) ?></span></div>
            </div>

            <button type="button" class="luxe-cart-checkout-btn" id="placeBtnSum" onclick="placeOrder()">
              Place Order — ₹<span id="payAmtSum"><?= number_format($initialTotal) ?></span>
            </button>
            <div class="luxe-co-secure">🔒 Secured by 256-bit SSL encryption</div>
          </div>
        </div>

      </div>

</div>
</div>
</main>

<!-- Address Modal -->
<div class="luxe-modal-overlay hidden" id="addrModal">
  <div class="luxe-modal-card" style="max-width:560px">
    <div class="luxe-modal-header">
      <h3>Add New Address</h3>
      <button class="luxe-modal-close" onclick="closeAddrModal()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
      </button>
    </div>
    <form id="addrForm" onsubmit="saveAddr(event)" class="luxe-modal-body">
      <div class="luxe-form-grid">
        <div class="luxe-form-field"><label>Full Name</label><input type="text" name="full_name" required placeholder="Rahul Sharma"></div>
        <div class="luxe-form-field"><label>Phone</label><input type="text" name="phone" placeholder="+91 9876543210"></div>
        <div class="luxe-form-field full-width"><label>Address Line 1</label><input type="text" name="line1" required placeholder="House/Flat No., Street"></div>
        <div class="luxe-form-field full-width"><label>Address Line 2</label><input type="text" name="line2" placeholder="Landmark (optional)"></div>
        <div class="luxe-form-field"><label>City</label><input type="text" name="city" required placeholder="Mumbai"></div>
        <div class="luxe-form-field"><label>PIN Code</label><input type="text" name="pin" required placeholder="400001" inputmode="numeric"></div>
        <div class="luxe-form-field"><label>State</label><input type="text" name="state" required placeholder="Maharashtra"></div>
        <div class="luxe-form-field"><label>Type</label><select name="type"><option value="Home">Home</option><option value="Work">Work</option><option value="Other">Other</option></select></div>
      </div>
      <div class="luxe-modal-actions">
        <button type="button" class="luxe-btn-outline" onclick="closeAddrModal()">Cancel</button>
        <button type="submit" class="luxe-btn-primary">Save Address</button>
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
      btn.className = 'luxe-cart-coupon-tag';
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
  document.querySelectorAll('.luxe-co-speed-opt').forEach(o=>o.classList.remove('active'));
  el.closest('.luxe-co-speed-opt').classList.add('active');
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
  document.querySelectorAll('.luxe-pm-card').forEach(c=>c.classList.remove('selected'));
  this.closest('.luxe-pm-card').classList.add('selected');
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
