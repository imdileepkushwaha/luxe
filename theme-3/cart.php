<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart_session.php';
require_once __DIR__ . '/../includes/coupons.php';

$pdo  = db();
$user = auth_user($pdo);

/* ── AJAX cart actions (JSON) ──────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id']     ?? 0);
    $cart   = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];

    foreach ($cart as $k => $line) {
        if ((int) ($line['id'] ?? 0) !== $id) continue;
        if ($action === 'remove') {
            unset($cart[$k]);
        } elseif ($action === 'save') {
            $saved = is_array($_SESSION['saved_for_later'] ?? null) ? $_SESSION['saved_for_later'] : [];
            $saved[] = $line;
            $_SESSION['saved_for_later'] = $saved;
            unset($cart[$k]);
        } elseif ($action === 'qty') {
            $qty = max(1, min(50, (int) ($_POST['qty'] ?? 1)));
            $cart[$k]['qty'] = $qty;
        } elseif ($action === 'toggle_checked') {
            $cart[$k]['checked'] = (bool) ($_POST['checked'] ?? true);
        }
    }
    if ($action === 'bulk_checked') {
        $ids = is_array($_POST['ids'] ?? null) ? array_map('intval', $_POST['ids']) : [];
        $checked = (bool) ($_POST['checked'] ?? true);
        foreach ($cart as $k => $line) {
            if (in_array((int)($line['id']??0), $ids)) {
                $cart[$k]['checked'] = $checked;
            }
        }
    }
    $cart = array_values($cart);

    // Also handle restoring from saved
    if ($action === 'restore') {
        $saved = is_array($_SESSION['saved_for_later'] ?? null) ? $_SESSION['saved_for_later'] : [];
        foreach ($saved as $sk => $sline) {
            if ((int)($sline['id'] ?? 0) === $id) {
                $cart[] = $sline;
                unset($saved[$sk]);
                break;
            }
        }
        $_SESSION['saved_for_later'] = array_values($saved);
    } elseif ($action === 'remove_saved') {
        $saved = is_array($_SESSION['saved_for_later'] ?? null) ? $_SESSION['saved_for_later'] : [];
        foreach ($saved as $sk => $sline) {
            if ((int)($sline['id'] ?? 0) === $id) {
                unset($saved[$sk]);
                break;
            }
        }
        $_SESSION['saved_for_later'] = array_values($saved);
    }
    $cart = array_values($cart);
    $cart = cart_filter_available_items($pdo, $cart);
    $_SESSION['cart'] = $cart;

    $total = 0; $count = 0;
    foreach ($cart as $ci) {
        $q = max(1, (int)($ci['qty'] ?? 1));
        $total += $q * max(0, (int)($ci['price'] ?? 0));
        $count += $q;
    }

    $linesForShip = [];
    foreach ($cart as $ci) {
        $linesForShip[] = ['id'=>(int)($ci['id']??0),'price'=>max(0,(int)($ci['price']??0)),'qty'=>max(1,(int)($ci['qty']??1))];
    }
    $baseDelivery = $linesForShip !== [] ? cart_compute_delivery_total($pdo, $linesForShip) : 0;
    if ($total >= 1000) { $baseDelivery = 0; }
    $speedFees = $linesForShip !== [] ? cart_speed_fee_totals_for_lines($pdo, $linesForShip) : ['express'=>0,'same_day'=>0];

    echo json_encode([
        'ok' => true, 
        'subtotal' => $total, 
        'count' => $count, 
        'cart' => $cart,
        'base_delivery' => $baseDelivery,
        'express_fee' => (int)($speedFees['express'] ?? 0),
        'same_day_fee' => (int)($speedFees['same_day'] ?? 0)
    ]);
    exit;
}

/* ── Normal POST (fallback non-JS) ─────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int)   ($_POST['id']     ?? 0);
    foreach ($_SESSION['cart'] as $k => $line) {
        if ((int) ($line['id'] ?? 0) !== $id) continue;
        if ($action === 'remove') {
            unset($_SESSION['cart'][$k]);
        } elseif ($action === 'save') {
            $saved = is_array($_SESSION['saved_for_later'] ?? null) ? $_SESSION['saved_for_later'] : [];
            $saved[] = $_SESSION['cart'][$k];
            $_SESSION['saved_for_later'] = $saved;
            unset($_SESSION['cart'][$k]);
        } elseif ($action === 'qty') {
            $qty = max(1, min(50, (int) ($_POST['qty'] ?? 1)));
            $_SESSION['cart'][$k]['qty'] = $qty;
        }
    }
    $_SESSION['cart'] = array_values($_SESSION['cart']);

    if ($action === 'restore') {
        $saved = is_array($_SESSION['saved_for_later'] ?? null) ? $_SESSION['saved_for_later'] : [];
        foreach ($saved as $sk => $sline) {
            if ((int)($sline['id'] ?? 0) === $id) {
                $_SESSION['cart'][] = $sline;
                unset($saved[$sk]);
                break;
            }
        }
        $_SESSION['saved_for_later'] = array_values($saved);
    } elseif ($action === 'remove_saved') {
        $saved = is_array($_SESSION['saved_for_later'] ?? null) ? $_SESSION['saved_for_later'] : [];
        foreach ($saved as $sk => $sline) {
            if ((int)($sline['id'] ?? 0) === $id) {
                unset($saved[$sk]);
                break;
            }
        }
        $_SESSION['saved_for_later'] = array_values($saved);
    }
    header('Location: cart.php');
    exit;
}

/* ── Data ───────────────────────────────────────────────── */
$cartItems = cart_filter_available_items($pdo, $_SESSION['cart'] ?? []);
$_SESSION['cart'] = $cartItems;

$savedItems = is_array($_SESSION['saved_for_later'] ?? null) ? $_SESSION['saved_for_later'] : [];
$savedItems = cart_filter_available_items($pdo, $savedItems);
$_SESSION['saved_for_later'] = $savedItems;

function t1_cart_thumb(array $line): string {
    $img = trim((string) ($line['image'] ?? $line['image_path'] ?? ''));
    if ($img === '' || strcasecmp($img, 'default') === 0) return '';
    if (!preg_match('#^(https?:)?//#i', $img) && !str_starts_with($img, '/')) {
        $img = luxe_public_href(ltrim($img, '/'));
    }
    return $img;
}

$cartCount = 0; $subtotal = 0;
foreach ($cartItems as $ci) {
    $q = max(1, (int)($ci['qty'] ?? 1));
    $cartCount += $q;
    $subtotal  += $q * max(0, (int)($ci['price'] ?? 0));
}

// Shipping + platform fees
$linesForShip = [];
foreach ($cartItems as $ci) {
    $linesForShip[] = ['id'=>(int)($ci['id']??0),'price'=>max(0,(int)($ci['price']??0)),'qty'=>max(1,(int)($ci['qty']??1))];
}
$baseDelivery  = $linesForShip !== [] ? cart_compute_delivery_total($pdo, $linesForShip) : 0;
$speedFees     = $linesForShip !== [] ? cart_speed_fee_totals_for_lines($pdo, $linesForShip) : ['express'=>0,'same_day'=>0];
$expressFeeRu  = (int)($speedFees['express'] ?? 0);
$sameDayFeeRu  = (int)($speedFees['same_day'] ?? 0);
$platformFeeRu = site_platform_fee_rupees($pdo);
// Free delivery if subtotal >= 1000
if ($subtotal >= 1000) { $baseDelivery = 0; }
$couponDefsJs  = coupons_defs_for_frontend($pdo);
$couponFeatCodes = coupons_featured_tag_codes($pdo, 5);

$fullName = trim((string)(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($fullName === '') $fullName = 'Member';
$initial  = strtoupper(substr((string)($user['first_name'] ?? $fullName), 0, 1));
$isLoggedIn = $user !== null;
$userInitials = $initial;
$userName = $fullName;
$userEmail = trim((string)($user['email'] ?? ''));
$theme1LoginHref        = 'login.php?redirect=' . rawurlencode('cart.php');
$theme1HeaderCategories = ["Men's Fashion", "Women's Fashion", "Kid's Fashion", 'Footwear'];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount  = $cartCount;
$theme1FooterCategories = $theme1HeaderCategories;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Cart — LUXE</title>
  <meta name="description" content="Review your cart and proceed to checkout on LUXE.">
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
  
</head>
<body class="profile-page-wrap t1-cart-page t2-account-layout t2-cart-checkout">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <div class="cart-main-wrap">
 <div class="container">

      <!-- Header -->
      <div class="t1-cart-header">
        <h1>My Cart <span id="cartCountLabel"><?= $cartCount ?> item<?= $cartCount !== 1 ? 's' : '' ?></span></h1>
        <a href="index.php" class="luxe-continue-link">← Continue Shopping</a>
      </div>

      <?php if ($cartItems === []): ?>
        <!-- Empty State -->
        <div class="luxe-cart-empty">
          <div class="luxe-cart-empty-icon">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
          </div>
          <h2>Your cart is empty</h2>
          <p>Looks like you haven't added anything yet. Let's change that!</p>
          <a href="index.php" class="luxe-cart-checkout-btn" style="max-width:220px;margin:0 auto;">Start Shopping →</a>
        </div>

      <?php else: ?>
        <div class="luxe-cart-layout">

          <!-- Left: Items -->
          <div class="luxe-cart-items-col" id="cartItemsCol">
            <div class="luxe-cart-select-all-bar">
              <div style="display:flex; align-items:center; gap:14px;">
                <label class="luxe-cart-checkbox-wrap">
                  <input type="checkbox" id="selectAllItems" onchange="toggleSelectAll(this)" <?= (!isset($cartItems[0]) || ($cartItems[0]['checked'] ?? true)) ? 'checked' : '' ?>>
                  <span class="luxe-cart-checkmark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                  </span>
                </label>
                <label for="selectAllItems" class="luxe-cart-select-label">Select All (<span id="selectedCount"><?= count(array_filter($cartItems, fn($x) => $x['checked'] ?? true)) ?></span>/<?= count($cartItems) ?>)</label>
              </div>
              <div id="bulkActions" class="luxe-bulk-actions" style="display:none;">
                <button class="luxe-cart-action-link" onclick="bulkSaveForLater()">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg> Save All
                </button>
                <button class="luxe-cart-action-link danger" onclick="bulkRemove()">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg> Remove All
                </button>
              </div>
            </div>
            
            <div class="luxe-cart-items-list">
            <?php foreach ($cartItems as $line):
              $lineId    = (int)   ($line['id']    ?? 0);
              $lineQty   = max(1, (int) ($line['qty'] ?? 1));
              $linePrice = max(0, (int) ($line['price'] ?? 0));
              $lineTotal = $lineQty * $linePrice;
              $lineName  = (string) ($line['name']  ?? 'Product');
              $lineBrand = (string) ($line['brand'] ?? '');
              $lineEmoji = (string) ($line['emoji'] ?? '🛍');
              $lineSize  = (string) ($line['size']  ?? '');
              $lineColor = (string) ($line['color'] ?? '');
              $lineImg   = t1_cart_thumb($line);
            ?>
            <div class="luxe-cart-item" id="cartCard<?= $lineId ?>">
              <div class="luxe-cart-item-inner">
                <label class="luxe-cart-checkbox-wrap">
                  <input type="checkbox" class="item-checkbox" data-id="<?= $lineId ?>" onchange="onItemSelect(<?= $lineId ?>, this.checked)" <?= ($line['checked'] ?? true) ? 'checked' : '' ?>>
                  <span class="luxe-cart-checkmark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                  </span>
                </label>

                <a href="product.php?slug=<?= h((string)($line['slug'] ?? '')) ?>" class="luxe-cart-item-img">
                  <?php if ($lineImg !== ''): ?>
                    <img src="<?= h($lineImg) ?>" alt="<?= h($lineName) ?>">
                  <?php else: ?>
                    <span><?= h($lineEmoji) ?></span>
                  <?php endif; ?>
                </a>

                <div class="luxe-cart-item-details">
                  <div class="luxe-cart-item-info">
                    <?php if ($lineBrand !== ''): ?>
                      <div class="luxe-cart-item-brand"><?= h($lineBrand) ?></div>
                    <?php endif; ?>
                    <h3 class="luxe-cart-item-name"><?= h($lineName) ?></h3>
                    
                    <?php if ($lineSize !== '' || $lineColor !== ''): ?>
                    <div class="luxe-cart-item-variants">
                      <?php if ($lineSize !== ''): ?><span>Size: <?= h($lineSize) ?></span><?php endif; ?>
                      <?php if ($lineColor !== ''): ?><span>Color: <?= h($lineColor) ?></span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                  </div>

                  <div class="luxe-cart-item-pricing">
                    <span class="luxe-cart-price">₹<?= number_format($lineTotal) ?></span>
                    <?php 
                      $origUnitPrice = (int)($line['orig'] ?? 0);
                      if ($origUnitPrice > $linePrice): 
                        $origTotal = $origUnitPrice * $lineQty;
                        $off = round((($origUnitPrice - $linePrice) / $origUnitPrice) * 100);
                    ?>
                      <div class="luxe-cart-price-original">
                        <del>₹<?= number_format($origTotal) ?></del>
                        <span class="luxe-cart-discount"><?= $off ?>% OFF</span>
                      </div>
                    <?php endif; ?>
                    <?php if ($lineQty > 1): ?>
                      <div class="luxe-cart-unit-price">₹<?= number_format($linePrice) ?> / piece</div>
                    <?php endif; ?>
                  </div>

                  <div class="luxe-cart-item-controls">
                    <div class="luxe-cart-qty-pill">
                      <button type="button" onclick="changeQty(<?= $lineId ?>, -1)">−</button>
                      <input type="number" id="qty<?= $lineId ?>" value="<?= $lineQty ?>" min="1" max="<?= (int)($line['max_qty'] ?? 50) ?>" onchange="setQty(<?= $lineId ?>, this.value)">
                      <button type="button" id="plus<?= $lineId ?>" onclick="changeQty(<?= $lineId ?>, 1)" <?= $lineQty >= (int)($line['max_qty'] ?? 50) ? 'disabled' : '' ?>>+</button>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="luxe-cart-item-footer">
                <div class="luxe-cart-delivery-info" id="delInfo<?= $lineId ?>"></div>
                <div class="luxe-cart-item-actions">
                  <button type="button" class="luxe-cart-action-btn" onclick="saveForLater(<?= $lineId ?>)">Save for Later</button>
                  <span class="luxe-cart-action-divider"></span>
                  <button type="button" class="luxe-cart-action-btn danger" onclick="removeItem(<?= $lineId ?>)">Remove</button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            </div>
          </div>

          <!-- Right: Order Summary -->
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
                    <input type="radio" name="cart_speed" value="standard" checked onchange="onCartSpeed(this)">
                    <div class="opt-text">Standard <span>(3-5 days)</span></div>
                    <span id="cartStFee" class="opt-price" style="color:<?= $baseDelivery===0?'#10b981':'#0f172a' ?>;"><?= $baseDelivery===0?'FREE':'₹'.number_format($baseDelivery) ?></span>
                  </label>
                  <label class="luxe-co-speed-opt" id="sopt-express">
                    <input type="radio" name="cart_speed" value="express" onchange="onCartSpeed(this)">
                    <div class="opt-text">Express <span>(1-2 days)</span></div>
                    <span id="cartExFee" class="opt-price"><?= $expressFeeRu>0?'₹'.number_format($expressFeeRu):'FREE' ?></span>
                  </label>
                  <label class="luxe-co-speed-opt" id="sopt-same_day">
                    <input type="radio" name="cart_speed" value="same_day" onchange="onCartSpeed(this)">
                    <div class="opt-text">Same Day</div>
                    <span id="cartSdFee" class="opt-price"><?= $sameDayFeeRu>0?'₹'.number_format($sameDayFeeRu):'FREE' ?></span>
                  </label>
                </div>
              </div>

              <!-- Fee rows -->
              <div class="luxe-co-fee-rows">
                <div class="luxe-co-fee-row"><span>Subtotal (<span id="summaryCount"><?= $cartCount ?></span> items)</span><span id="summarySubtotal">₹<?= number_format($subtotal) ?></span></div>
                <div class="luxe-co-fee-row"><span>Delivery Charges</span><span class="highlight" id="cartDeliveryFee"><?= $baseDelivery===0?'FREE':'₹'.number_format($baseDelivery) ?></span></div>
                <div class="luxe-co-fee-row"><span>Platform Fee</span><span>₹<?= number_format($platformFeeRu) ?></span></div>
                <div class="luxe-co-fee-row discount" id="discRow" style="display:none">
                  <span>Coupon Discount</span>
                  <div style="display:flex;align-items:center;gap:6px;">
                    <span id="discVal">-₹0</span>
                    <button class="luxe-co-coupon-remove" id="removeCouponBtn" onclick="removeCoupon()">✕</button>
                  </div>
                </div>
                <div class="luxe-co-fee-row total">
                  <span>Total Amount</span>
                  <span id="summaryTotal">₹<?= number_format($subtotal + $platformFeeRu + $baseDelivery) ?></span>
                </div>
              </div>

              <!-- CTA -->
              <a href="checkout.php" class="luxe-cart-checkout-btn" id="checkoutBtn">Proceed to Checkout</a>
              <div class="luxe-co-secure">🔒 Secured by 256-bit SSL encryption</div>
            </div>
          </div>

        </div>
      <?php endif; ?>

      <!-- Saved for Later -->
      <?php if ($savedItems !== []): ?>
      <div class="luxe-saved-section">
        <h3 class="luxe-saved-title">Saved for Later (<?= count($savedItems) ?>)</h3>
        <div class="luxe-saved-grid">
          <?php foreach ($savedItems as $sline):
            $sId = (int)($sline['id'] ?? 0);
            $sName = (string)($sline['name'] ?? 'Product');
            $sPrice = max(0, (int)($sline['price'] ?? 0));
            $sImg = t1_cart_thumb($sline);
            $sEmoji = (string)($sline['emoji'] ?? '🛍');
            $sBrand = (string)($sline['brand'] ?? '');
          ?>
          <div class="luxe-saved-card" id="savedCard<?= $sId ?>">
            <div class="luxe-saved-card-img">
              <?php if ($sImg !== ''): ?>
                <img src="<?= h($sImg) ?>" alt="<?= h($sName) ?>" loading="lazy">
              <?php else: ?>
                <span><?= h($sEmoji) ?></span>
              <?php endif; ?>
            </div>
            <div class="luxe-saved-card-body">
              <?php if ($sBrand !== ''): ?>
                <div class="luxe-saved-card-brand"><?= h($sBrand) ?></div>
              <?php endif; ?>
              <h4 class="luxe-saved-card-name"><?= h($sName) ?></h4>
              <div class="luxe-saved-card-price-row">
                <div class="luxe-saved-price">₹<?= number_format($sPrice) ?></div>
                <?php 
                  $sOrig = (int)($sline['orig'] ?? 0);
                  if ($sOrig > $sPrice): 
                    $sOff = round((($sOrig - $sPrice) / $sOrig) * 100);
                ?>
                  <del class="luxe-saved-old">₹<?= number_format($sOrig) ?></del>
                  <span class="luxe-saved-off"><?= $sOff ?>% OFF</span>
                <?php endif; ?>
              </div>
              <div class="luxe-saved-card-actions">
                <button class="btn-move" onclick="moveToCart(<?= $sId ?>)">Move to Cart</button>
                <button class="btn-remove" onclick="removeSaved(<?= $sId ?>)">Remove</button>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
    </div>
   
  </main>

  <div class="t1-cart-toast" id="cartToast"></div>

  <?php require __DIR__ . '/partials/footer.php'; ?>

  <script>
    let CART_SUBTOTAL   = <?= (int)$subtotal ?>;
    const CART_PLATFORM   = <?= (int)$platformFeeRu ?>;
    let CART_BASE_DEL   = <?= (int)$baseDelivery ?>;
    let CART_EXPRESS    = <?= (int)$expressFeeRu ?>;
    let CART_SAMEDAY    = <?= (int)$sameDayFeeRu ?>;
    const COUPON_DEFS     = <?= json_encode($couponDefsJs, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;
    const CART_ITEMS_DATA = <?= json_encode(array_map(fn($x)=>['id'=>(int)($x['id']??0),'qty'=>max(1,(int)($x['qty']??1)),'price'=>max(0,(int)($x['price']??0)),'seller_id'=>max(0,(int)($x['seller_id']??0)),'max_qty'=>(int)($x['max_qty']??50),'checked'=>(bool)($x['checked']??true)], $cartItems), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;

    let cartCouponDisc = 0;

    // Render coupon tags
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
    })();

    function getCartSpeedFee() {
      const v = document.querySelector('input[name="cart_speed"]:checked')?.value || 'standard';
      if (v === 'express') return CART_EXPRESS;
      if (v === 'same_day') return CART_SAMEDAY;
      return CART_BASE_DEL;
    }

    function refreshCartTotal() {
      const del   = getCartSpeedFee();
      const total = Math.max(0, CART_SUBTOTAL + CART_PLATFORM + del - cartCouponDisc);
      const fmt   = n => '₹' + parseInt(n).toLocaleString('en-IN');
      const delEl = document.getElementById('cartDeliveryFee');
      if (delEl) { 
        delEl.textContent = del === 0 ? 'FREE' : fmt(del); 
        delEl.className = del === 0 ? 'green' : ''; 
      }
      document.getElementById('summaryTotal').textContent = fmt(total);

      // Update option labels (important if subtotal crossed free threshold)
      const stEl = document.getElementById('cartStFee');
      if (stEl) {
        stEl.textContent = CART_BASE_DEL === 0 ? 'FREE' : fmt(CART_BASE_DEL);
        stEl.style.color = CART_BASE_DEL === 0 ? '#10b981' : '#0f172a';
      }
      const exEl = document.getElementById('cartExFee');
      if (exEl) exEl.textContent = CART_EXPRESS === 0 ? 'FREE' : fmt(CART_EXPRESS);
      const sdEl = document.getElementById('cartSdFee');
      if (sdEl) sdEl.textContent = CART_SAMEDAY === 0 ? 'FREE' : fmt(CART_SAMEDAY);

      updateAllExpectedDates();
    }

    function updateAllExpectedDates() {
      const speed = document.querySelector('input[name="cart_speed"]:checked')?.value || 'standard';
      const map = {
        standard: { name: 'Standard Delivery', min: 3, max: 7 },
        express:  { name: 'Express Delivery',  min: 1, max: 2 },
        same_day: { name: 'Same Day Delivery', min: 0, max: 1 }
      };
      const cfg = map[speed] || map.standard;
      const dateStr = getExpectedDateStr(cfg.min, cfg.max);
      
      CART_ITEMS_DATA.forEach(it => {
        const el = document.getElementById('delInfo' + it.id);
        if (el) {
          el.innerHTML = `🚚 <span style="color:#64748b;font-weight:400">Expected delivery:</span> ${cfg.name} by ${dateStr}`;
        }
      });
    }

    function getExpectedDateStr(min, max) {
      const opt = { day: 'numeric', month: 'short' };
      const now = new Date();
      const d1 = new Date(now); d1.setDate(now.getDate() + min);
      const d2 = new Date(now); d2.setDate(now.getDate() + max);
      if (min === max || d1.toDateString() === d2.toDateString()) return d1.toLocaleDateString('en-IN', opt);
      return d1.toLocaleDateString('en-IN', opt) + ' – ' + d2.toLocaleDateString('en-IN', opt);
    }

    function onCartSpeed(el) {
      document.querySelectorAll('.t1-co-speed-opt').forEach(o => o.classList.remove('active'));
      el.closest('.t1-co-speed-opt').classList.add('active');
      refreshCartTotal();
    }

    function showCartToast(msg, isErr = false) {
      const t = document.getElementById('cartToast');
      t.textContent = msg;
      t.style.background = isErr ? '#ef4444' : '#0f172a';
      t.classList.add('show');
      clearTimeout(t._timer);
      t._timer = setTimeout(() => t.classList.remove('show'), 2800);
    }

    async function cartAction(payload) {
      const fd = new FormData();
      for (const [k, v] of Object.entries(payload)) fd.append(k, v);
      const res = await fetch('cart.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      return res.json();
    }

    async function removeItem(id) {
      const card = document.getElementById('cartCard' + id);
      if (!card) return;
      card.classList.add('removing');
      setTimeout(async () => {
        card.remove();
        try {
          const data = await cartAction({ action: 'remove', id });
          if (data.ok) {
            CART_SUBTOTAL = data.subtotal;
            CART_BASE_DEL = data.base_delivery;
            CART_EXPRESS  = data.express_fee;
            CART_SAMEDAY  = data.same_day_fee;
            updateSummaryCount(data.count, data.subtotal);
          }
        } catch(e) {}
        if (!document.querySelector('.luxe-cart-item')) location.reload();
      }, 350);
      showCartToast('Item removed from cart');
    }

    async function saveForLater(id) {
      const card = document.getElementById('cartCard' + id);
      if (!card) return;
      card.style.opacity = '0.5';
      card.style.pointerEvents = 'none';
      try {
        const data = await cartAction({ action: 'save', id });
        if (data.ok) {
          showCartToast('Item saved for later');
          setTimeout(() => location.reload(), 500);
        }
      } catch(e) { card.style.opacity = '1'; card.style.pointerEvents = 'auto'; }
    }

    async function moveToCart(id) {
      const card = document.getElementById('savedCard' + id);
      if (!card) return;
      card.style.opacity = '0.5';
      card.style.pointerEvents = 'none';
      try {
        const data = await cartAction({ action: 'restore', id });
        if (data.ok) {
          showCartToast('Item moved to cart');
          setTimeout(() => location.reload(), 500);
        }
      } catch(e) { card.style.opacity = '1'; card.style.pointerEvents = 'auto'; }
    }

    async function removeSaved(id) {
      const card = document.getElementById('savedCard' + id);
      if (!card) return;
      card.style.opacity = '0.5';
      try {
        const data = await cartAction({ action: 'remove_saved', id });
        if (data.ok) {
          card.remove();
          showCartToast('Item removed from saved list');
          if (!document.querySelector('.luxe-saved-section .luxe-saved-card')) location.reload();
        }
      } catch(e) { card.style.opacity = '1'; }
    }

    async function toggleSelectAll(master) {
      const cbs = document.querySelectorAll('.item-checkbox');
      const ids = [];
      cbs.forEach(cb => {
        cb.checked = master.checked;
        ids.push(cb.dataset.id);
      });
      try {
        await cartAction({ action: 'bulk_checked', ids, checked: master.checked ? 1 : 0 });
      } catch(e){}
      onItemSelect();
    }

    async function onItemSelect(id = null, checked = null) {
      if (id !== null) {
        try {
          await cartAction({ action: 'toggle_checked', id, checked: checked ? 1 : 0 });
        } catch(e){}
      }
      const cbs = Array.from(document.querySelectorAll('.item-checkbox'));
      const selected = cbs.filter(cb => cb.checked);
      document.getElementById('selectedCount').textContent = selected.length;
      document.getElementById('selectAllItems').checked = (selected.length === cbs.length && cbs.length > 0);
      document.getElementById('bulkActions').style.display = selected.length > 0 ? 'flex' : 'none';
      
      const btn = document.getElementById('checkoutBtn');
      if (btn) {
        if (selected.length === 0) {
          btn.style.opacity = '0.5';
          btn.style.pointerEvents = 'none';
          btn.style.cursor = 'not-allowed';
        } else {
          btn.style.opacity = '1';
          btn.style.pointerEvents = 'auto';
          btn.style.cursor = 'pointer';
        }
      }
    }

    async function bulkRemove() {
      const ids = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.dataset.id);
      if (!ids.length) return;
      if (!confirm('Remove ' + ids.length + ' items from cart?')) return;
      for (const id of ids) {
        await cartAction({ action: 'remove', id });
      }
      location.reload();
    }

    async function bulkSaveForLater() {
      const ids = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.dataset.id);
      if (!ids.length) return;
      for (const id of ids) {
        await cartAction({ action: 'save', id });
      }
      location.reload();
    }

    async function setQty(id, val) {
      const itemData = CART_ITEMS_DATA.find(i => i.id == id) || { max_qty: 50 };
      const qty = Math.max(1, Math.min(itemData.max_qty, parseInt(val, 10) || 1));
      document.getElementById('qty' + id).value = qty;
      const plusBtn = document.getElementById('plus' + id);
      if (plusBtn) plusBtn.disabled = (qty >= itemData.max_qty);
      try {
        const data = await cartAction({ action: 'qty', id, qty });
        if (data.ok) {
          CART_SUBTOTAL = data.subtotal;
          CART_BASE_DEL = data.base_delivery;
          CART_EXPRESS  = data.express_fee;
          CART_SAMEDAY  = data.same_day_fee;
          updateSummaryCount(data.count, data.subtotal);
          const card = document.getElementById('cartCard' + id);
          if (card) {
            const item = (data.cart || []).find(i => i.id == id);
            if (item) {
              const priceEl = card.querySelector('.luxe-cart-price');
              const unitEl  = card.querySelector('.luxe-cart-unit-price');
              if (priceEl) priceEl.textContent = '₹' + parseInt(item.price * item.qty).toLocaleString('en-IN');
              if (unitEl && item.qty > 1) { unitEl.textContent = '₹' + parseInt(item.price).toLocaleString('en-IN') + ' × ' + item.qty; unitEl.style.display = ''; }
              else if (unitEl) unitEl.style.display = 'none';
            }
          }
        }
      } catch(e) { showCartToast('Could not update qty', true); }
    }

    function changeQty(id, delta) {
      const input = document.getElementById('qty' + id);
      setQty(id, Math.max(1, (parseInt(input.value, 10) || 1) + delta));
    }

    function updateSummaryCount(count, subtotalValue = null) {
      const subEl = document.getElementById('summarySubtotal');
      const cntEl = document.getElementById('summaryCount');
      const hBadge = document.querySelector('a[href="cart.php"] .badge-count, a[href="cart.php"] .badge-v3');
      const cLabel = document.getElementById('cartCountLabel');
      if (cntEl) cntEl.textContent = count;
      if (hBadge) hBadge.textContent = count;
      if (cLabel) cLabel.textContent = count + ' item' + (count !== 1 ? 's' : '');
      if (subtotalValue !== null && subEl) {
        subEl.textContent = '₹' + parseInt(subtotalValue).toLocaleString('en-IN');
      }
      refreshCartTotal();
    }

    function applyCoupon() {
      const code = document.getElementById('promoInput').value.trim().toUpperCase();
      const okEl = document.getElementById('couponOk');
      const erEl = document.getElementById('couponErr');
      if (okEl) okEl.style.display = 'none';
      if (erEl) erEl.style.display = 'none';
      cartCouponDisc = 0;
      document.getElementById('discRow').style.display = 'none';
      if (!code) { if(erEl){erEl.textContent='Enter a coupon code';erEl.style.display='flex';} return; }
      const def = COUPON_DEFS[code];
      if (!def) { if(erEl){erEl.textContent='Invalid coupon code';erEl.style.display='flex';} return; }
      const minOrder = Number(def.min_order || 0);
      if (CART_SUBTOTAL < minOrder) { if(erEl){erEl.textContent='Min order ₹'+minOrder+' required';erEl.style.display='flex';} return; }
      let d = 0;
      if (def.type === 'percent') { const cap = def.max ? Number(def.max) : Infinity; d = Math.min(Math.round(CART_SUBTOTAL * Number(def.val) / 100), cap); }
      else d = Number(def.val) || 0;
      cartCouponDisc = Math.min(d, CART_SUBTOTAL);
      if (cartCouponDisc > 0) {
        document.getElementById('discRow').style.display = 'flex';
        document.getElementById('discVal').textContent = '-₹' + cartCouponDisc.toLocaleString('en-IN');
        if(okEl){okEl.textContent='✓ Saved ₹'+cartCouponDisc.toLocaleString('en-IN')+'!';okEl.style.display='flex';}
        try { sessionStorage.setItem('luxeCheckoutCoupon', code); } catch(_) {}
        refreshCartTotal();
      }
    }

    function removeCoupon() {
      cartCouponDisc = 0;
      document.getElementById('promoInput').value = '';
      document.getElementById('discRow').style.display = 'none';
      document.getElementById('couponOk').style.display = 'none';
      document.getElementById('couponErr').style.display = 'none';
      try { sessionStorage.removeItem('luxeCheckoutCoupon'); } catch(_) {}
      refreshCartTotal();
    }

    refreshCartTotal();
    onItemSelect();
  </script>
</body>
</html>
