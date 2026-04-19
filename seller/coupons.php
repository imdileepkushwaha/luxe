<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/coupons.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Coupons';
$activeNav = 'coupons';

$flash = '';
$flashOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create_coupon') {
        $rawCode = (string) ($_POST['code'] ?? '');
        $code = coupons_normalize_code(preg_replace('/[^A-Za-z0-9]/', '', $rawCode) ?? '');
        $type = (string) ($_POST['discount_type'] ?? '') === 'percent' ? 'percent' : 'flat';
        $val = max(0, (int) ($_POST['discount_value'] ?? 0));
        $maxDisc = trim((string) ($_POST['max_discount_rupees'] ?? ''));
        $maxDiscSql = $maxDisc === '' ? null : max(0, (int) $maxDisc);
        $minOrder = max(0, (int) ($_POST['min_order_rupees'] ?? 0));
        $desc = trim((string) ($_POST['description'] ?? ''));
        if (strlen($desc) > 255) {
            $desc = substr($desc, 0, 255);
        }
        $vf = trim((string) ($_POST['valid_from'] ?? ''));
        $vu = trim((string) ($_POST['valid_until'] ?? ''));
        $validFrom = $vf !== '' ? $vf : null;
        $validUntil = $vu !== '' ? $vu : null;

        if (strlen($code) < 3 || strlen($code) > 20) {
            $flash = 'Coupon code must be 3–20 letters or numbers.';
            $flashOk = false;
        } elseif ($type === 'percent' && ($val < 1 || $val > 90)) {
            $flash = 'Percent discount must be between 1 and 90.';
            $flashOk = false;
        } elseif ($type === 'flat' && ($val < 1 || $val > 500000)) {
            $flash = 'Flat discount must be between 1 and 500000 rupees.';
            $flashOk = false;
        } elseif ($type === 'percent' && $maxDiscSql !== null && $maxDiscSql < 1) {
            $flash = 'Max discount must be at least ₹1 when set.';
            $flashOk = false;
        } else {
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO seller_coupons (seller_id, code, discount_type, discount_value, max_discount_rupees, min_order_rupees, description, is_active, valid_from, valid_until)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                );
                $ins->execute([
                    (int) $seller['id'],
                    $code,
                    $type,
                    $val,
                    $maxDiscSql,
                    $minOrder,
                    $desc,
                    1,
                    $validFrom,
                    $validUntil,
                ]);
                $flash = 'Coupon created. It will appear on the cart page for customers (with your other active coupons).';
                $flashOk = true;
            } catch (Throwable) {
                $flash = 'Could not save — the code may already be in use by another seller.';
                $flashOk = false;
            }
        }
    } elseif ($action === 'toggle_coupon') {
        $cid = (int) ($_POST['coupon_id'] ?? 0);
        if ($cid > 0) {
            $pdo->prepare(
                'UPDATE seller_coupons SET is_active = NOT is_active WHERE id = ? AND seller_id = ? LIMIT 1'
            )->execute([$cid, (int) $seller['id']]);
            $flash = 'Coupon status updated.';
            $flashOk = true;
        }
    } elseif ($action === 'delete_coupon') {
        $cid = (int) ($_POST['coupon_id'] ?? 0);
        if ($cid > 0) {
            $pdo->prepare('DELETE FROM seller_coupons WHERE id = ? AND seller_id = ? LIMIT 1')->execute([$cid, (int) $seller['id']]);
            $flash = 'Coupon removed.';
            $flashOk = true;
        }
    }
}

$couponRows = seller_coupons_for_seller($pdo, (int) $seller['id']);
$totalCoupons = count($couponRows);
$liveCoupons = 0;
foreach ($couponRows as $_cr) {
    if ((int) ($_cr['is_active'] ?? 0) !== 1) {
        continue;
    }
    if (!seller_coupon_dates_ok(
        isset($_cr['valid_from']) ? (string) $_cr['valid_from'] : null,
        isset($_cr['valid_until']) ? (string) $_cr['valid_until'] : null
    )) {
        continue;
    }
    $liveCoupons++;
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <div>
            <h1>Coupons</h1>
            <p class="seller-coupons-subtitle">Offer codes for your store. Discounts apply only to <strong>your</strong> products in the customer’s cart — then show up on checkout.</p>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?> seller-coupons-flash"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-coupons-kpis seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Live coupons</div>
              <div class="seller-kpi-card__value"><?= (int) $liveCoupons ?></div>
              <div class="seller-kpi-card__hint">Active + within date range</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Total created</div>
              <div class="seller-kpi-card__value"><?= (int) $totalCoupons ?></div>
              <div class="seller-kpi-card__hint">Including paused codes</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Cart visibility</div>
              <div class="seller-kpi-card__value" style="font-size:1.05rem">LUXE cart</div>
              <div class="seller-kpi-card__hint">Codes appear as quick-apply chips when live</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
          </div>
        </div>

        <div class="seller-coupons-layout">
          <div class="card seller-coupons-form-card">
            <div class="card-header">
              <div>
                <h2 class="card-title">New coupon</h2>
                <p class="card-subtitle seller-coupons-card-sub">Pick a memorable code. Customers type it or tap a chip on the cart page.</p>
              </div>
            </div>
            <div class="card-body">
              <div class="seller-coupon-tip" role="note">
                <span class="seller-coupon-tip__icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                </span>
                <div>
                  <strong>Unique code</strong> — Har code poori site par unique hona chahiye. Agar save fail ho to shayad kisi aur seller ne wahi code use kiya ho; naya code try karein.
                </div>
              </div>

              <form method="post" class="seller-form seller-coupons-form">
                <input type="hidden" name="action" value="create_coupon" />
                <div>
                  <label for="code">Coupon code</label>
                  <input id="code" class="seller-coupon-code-input" name="code" required maxlength="20" pattern="[A-Za-z0-9]{3,20}" placeholder="SAVE10" autocomplete="off" />
                  <p class="seller-help">3–20 letters or numbers. Saved in uppercase.</p>
                </div>

                <div class="seller-form__row">
                  <div>
                    <label for="discount_type">Discount type</label>
                    <select id="discount_type" name="discount_type" class="seller-status-select">
                      <option value="percent">Percent off</option>
                      <option value="flat">Flat amount (₹)</option>
                    </select>
                  </div>
                  <div>
                    <label for="discount_value" id="discount_value_label">Value</label>
                    <input id="discount_value" class="seller-stock-input" name="discount_value" type="number" min="1" required value="10" />
                    <p class="seller-help" id="discount_value_hint">Percent between 1 and 90.</p>
                  </div>
                </div>

                <div id="sellerCouponMaxWrap">
                  <label for="max_discount_rupees">Max discount cap (₹) <span class="seller-coupons-optional">optional</span></label>
                  <input id="max_discount_rupees" class="seller-stock-input" name="max_discount_rupees" type="number" min="1" placeholder="e.g. 500" />
                  <p class="seller-help">Stops huge discounts on large carts (percent offers only).</p>
                </div>

                <div class="seller-form__row">
                  <div>
                    <label for="min_order_rupees">Minimum cart (₹)</label>
                    <input id="min_order_rupees" class="seller-stock-input" name="min_order_rupees" type="number" min="0" value="0" />
                    <p class="seller-help">Only your store’s lines count toward this minimum.</p>
                  </div>
                  <div>
                    <label for="description">Customer message <span class="seller-coupons-optional">optional</span></label>
                    <input id="description" class="seller-badge-input" name="description" type="text" maxlength="255" placeholder="e.g. Monsoon sale on our store" />
                  </div>
                </div>

                <div class="seller-form__row">
                  <div>
                    <label for="valid_from">Valid from <span class="seller-coupons-optional">optional</span></label>
                    <input id="valid_from" class="seller-stock-input" name="valid_from" type="date" />
                  </div>
                  <div>
                    <label for="valid_until">Valid until <span class="seller-coupons-optional">optional</span></label>
                    <input id="valid_until" class="seller-stock-input" name="valid_until" type="date" />
                  </div>
                </div>

                <div class="seller-actions seller-coupons-form-actions">
                  <button type="submit" class="admin-btn admin-btn--primary">Create coupon</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card seller-coupons-list-card">
            <div class="card-header seller-coupons-list-head">
              <div>
                <h2 class="card-title">Your coupons</h2>
                <p class="card-subtitle seller-coupons-card-sub"><?= $totalCoupons === 0 ? 'Nothing here yet — add your first code.' : (int) $totalCoupons . ' code' . ($totalCoupons === 1 ? '' : 's') . ' · ' . (int) $liveCoupons . ' live for shoppers.' ?></p>
              </div>
            </div>
            <div class="card-body card-body--flush seller-coupons-list-body">
              <?php if ($couponRows === []): ?>
                <div class="seller-coupons-empty">
                  <div class="seller-coupons-empty__visual" aria-hidden="true">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                  </div>
                  <h3 class="seller-coupons-empty__title">No coupons yet</h3>
                  <p class="seller-coupons-empty__text">Create a code on the left. It will show as a chip on the cart page when it’s live.</p>
                </div>
              <?php else: ?>
                <ul class="seller-coupons-list" role="list">
                  <?php foreach ($couponRows as $row): ?>
                    <?php
                      $def = seller_coupon_row_to_def($row);
                      $datesOk = seller_coupon_dates_ok(
                          isset($row['valid_from']) ? (string) $row['valid_from'] : null,
                          isset($row['valid_until']) ? (string) $row['valid_until'] : null
                      );
                      $isOn = (int) ($row['is_active'] ?? 0) === 1;
                      $deal = $def['type'] === 'percent'
                          ? (string) $def['val'] . '% off' . ($def['max'] ? ' · max ₹' . number_format((int) $def['max']) : '')
                          : '₹' . number_format((int) $def['val']) . ' off';
                      $minRu = (int) ($row['min_order_rupees'] ?? 0);
                      $descShow = trim((string) ($row['description'] ?? ''));
                      if ($isOn && $datesOk) {
                          $statusClass = 'seller-status-chip--delivered';
                          $statusLabel = 'Live';
                      } elseif (!$isOn) {
                          $statusClass = 'seller-status-chip--pending';
                          $statusLabel = 'Paused';
                      } else {
                          $statusClass = 'seller-status-chip--rejected';
                          $statusLabel = 'Out of date';
                      }
                      ?>
                    <li class="seller-coupon-item">
                      <div class="seller-coupon-item__top">
                        <div class="seller-coupon-item__identity">
                          <span class="seller-coupon-item__code"><?= h((string) $row['code']) ?></span>
                          <span class="seller-coupon-item__deal"><?= h($deal) ?></span>
                        </div>
                        <span class="seller-status-chip <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
                      </div>
                      <div class="seller-coupon-item__meta">
                        <?php if ($minRu > 0): ?>
                          <span class="seller-coupon-meta-pill">Min order ₹<?= number_format($minRu) ?></span>
                        <?php endif; ?>
                        <span class="seller-coupon-meta-pill seller-coupon-meta-pill--muted">
                          <?php if ($row['valid_from'] || $row['valid_until']): ?>
                            <?= $row['valid_from'] ? h((string) $row['valid_from']) : 'Start' ?>
                            →
                            <?= $row['valid_until'] ? h((string) $row['valid_until']) : 'No end' ?>
                          <?php else: ?>
                            No date limits
                          <?php endif; ?>
                        </span>
                      </div>
                      <?php if ($descShow !== ''): ?>
                        <p class="seller-coupon-item__desc"><?= h($descShow) ?></p>
                      <?php endif; ?>
                      <div class="seller-coupon-item__actions">
                        <form method="post" class="seller-coupon-action-form">
                          <input type="hidden" name="action" value="toggle_coupon" />
                          <input type="hidden" name="coupon_id" value="<?= (int) $row['id'] ?>" />
                          <button type="submit" class="seller-view-btn"><?= $isOn ? 'Pause' : 'Activate' ?></button>
                        </form>
                        <form method="post" class="seller-coupon-action-form" onsubmit="return confirm('Delete this coupon permanently?');">
                          <input type="hidden" name="action" value="delete_coupon" />
                          <input type="hidden" name="coupon_id" value="<?= (int) $row['id'] ?>" />
                          <button type="submit" class="seller-coupon-btn-danger">Delete</button>
                        </form>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <script>
        (function () {
          var typeEl = document.getElementById("discount_type");
          var maxWrap = document.getElementById("sellerCouponMaxWrap");
          var valLabel = document.getElementById("discount_value_label");
          var valHint = document.getElementById("discount_value_hint");
          function sync() {
            if (!typeEl || !maxWrap) return;
            var isPct = typeEl.value === "percent";
            maxWrap.style.display = isPct ? "" : "none";
            if (valLabel) valLabel.textContent = isPct ? "Percent off" : "Amount (₹)";
            if (valHint) valHint.textContent = isPct ? "Between 1% and 90%." : "Flat rupees off eligible subtotal.";
          }
          typeEl?.addEventListener("change", sync);
          sync();
        })();
        </script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
