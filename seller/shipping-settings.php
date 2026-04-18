<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/site_settings.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Shipping settings';
$activeNav = 'shipping';

$flash = '';
$flashOk = false;

// Ensure row exists for this seller (defaults match platform cart shipping rule).
$pFee = site_cart_below_min_shipping_fee_rupees($pdo);
$pMin = site_cart_free_shipping_min_rupees($pdo);
$seedSt = $pdo->prepare(
    'INSERT INTO seller_shipping_settings (seller_id, default_shipping_fee, free_shipping_min_order)
     SELECT ?, ?, ?
     WHERE NOT EXISTS (SELECT 1 FROM seller_shipping_settings WHERE seller_id = ?)'
);
$seedSt->execute([(int) $seller['id'], $pFee, $pMin, (int) $seller['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_shipping_settings') {
    $handling = max(0, min(14, (int) ($_POST['handling_time_days'] ?? 2)));
    $shippingFee = max(0, (int) ($_POST['default_shipping_fee'] ?? 0));
    $freeMin = max(0, (int) ($_POST['free_shipping_min_order'] ?? 0));
    $regions = trim((string) ($_POST['shipping_regions'] ?? 'All India'));
    $codEnabled = isset($_POST['cod_enabled']) ? 1 : 0;
    $policy = trim((string) ($_POST['shipping_policy'] ?? ''));
    if (strlen($regions) > 255) {
        $regions = substr($regions, 0, 255);
    }

    $upd = $pdo->prepare(
        'UPDATE seller_shipping_settings
         SET handling_time_days = ?, default_shipping_fee = ?, free_shipping_min_order = ?,
             shipping_regions = ?, cod_enabled = ?, shipping_policy = ?
         WHERE seller_id = ?
         LIMIT 1'
    );
    $upd->execute([$handling, $shippingFee, $freeMin, $regions, $codEnabled, $policy, (int) $seller['id']]);
    $flash = 'Shipping settings updated successfully.';
    $flashOk = true;
}

$settingsSt = $pdo->prepare(
    'SELECT handling_time_days, default_shipping_fee, free_shipping_min_order, shipping_regions, cod_enabled, shipping_policy, updated_at
     FROM seller_shipping_settings
     WHERE seller_id = ?
     LIMIT 1'
);
$settingsSt->execute([(int) $seller['id']]);
$settings = $settingsSt->fetch() ?: [
    'handling_time_days' => 2,
    'default_shipping_fee' => 0,
    'free_shipping_min_order' => 0,
    'shipping_regions' => 'All India',
    'cod_enabled' => 1,
    'shipping_policy' => '',
    'updated_at' => null,
];

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Shipping settings</h1>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>" style="margin-bottom:14px"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Handling time</div>
              <div class="seller-kpi-card__value"><?= (int) ($settings['handling_time_days'] ?? 0) ?> day(s)</div>
              <div class="seller-kpi-card__hint">Order dispatch prep window</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Default shipping fee</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) ($settings['default_shipping_fee'] ?? 0)) ?></div>
              <div class="seller-kpi-card__hint">Applied when no special option selected</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3z"/><path d="M16 10h3l2 2v5h-5z"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Free shipping threshold</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) ($settings['free_shipping_min_order'] ?? 0)) ?></div>
              <div class="seller-kpi-card__hint">0 means disabled</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Configure shipping details</h2>
          </div>
          <div class="card-body">
            <form method="post" class="seller-withdraw-form">
              <input type="hidden" name="action" value="save_shipping_settings">
              <div class="seller-form__row">
                <div>
                  <label for="handling_time_days">Handling time (days)</label>
                  <input id="handling_time_days" class="seller-stock-input" type="number" min="0" max="14" name="handling_time_days" value="<?= (int) ($settings['handling_time_days'] ?? 2) ?>" required>
                </div>
                <div>
                  <label for="default_shipping_fee">Default shipping fee (Rs)</label>
                  <input id="default_shipping_fee" class="seller-stock-input" type="number" min="0" step="1" name="default_shipping_fee" value="<?= (int) ($settings['default_shipping_fee'] ?? 0) ?>" required>
                </div>
              </div>

              <div class="seller-form__row">
                <div>
                  <label for="free_shipping_min_order">Free shipping min order (Rs)</label>
                  <input id="free_shipping_min_order" class="seller-stock-input" type="number" min="0" step="1" name="free_shipping_min_order" value="<?= (int) ($settings['free_shipping_min_order'] ?? 0) ?>" required>
                </div>
                <div>
                  <label for="shipping_regions">Shipping regions</label>
                  <input id="shipping_regions" class="seller-badge-input" type="text" maxlength="255" name="shipping_regions" value="<?= h((string) ($settings['shipping_regions'] ?? 'All India')) ?>" required>
                </div>
              </div>

              <div>
                <label for="shipping_policy">Shipping policy details</label>
                <textarea id="shipping_policy" class="seller-badge-input" style="min-height:100px" name="shipping_policy" placeholder="Return shipment conditions, cutoff time, holiday handling, etc."><?= h((string) ($settings['shipping_policy'] ?? '')) ?></textarea>
              </div>

              <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem">
                <input type="checkbox" name="cod_enabled" value="1"<?= (int) ($settings['cod_enabled'] ?? 0) === 1 ? ' checked' : '' ?>>
                Cash on Delivery (COD) enabled
              </label>

              <div class="seller-actions">
                <button class="admin-btn admin-btn--primary" type="submit">Save shipping settings</button>
              </div>
            </form>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
