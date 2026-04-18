<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Returns & refunds';
$activeNav = 'returns';

$refundMethods = [
    'original_payment' => 'Original payment source',
    'bank_transfer' => 'Bank transfer',
    'store_credit' => 'Store credit',
];

$flash = '';
$flashOk = false;

// Ensure row exists for this seller.
$seedSt = $pdo->prepare(
    'INSERT INTO seller_return_settings (seller_id)
     SELECT ?
     WHERE NOT EXISTS (SELECT 1 FROM seller_return_settings WHERE seller_id = ?)'
);
$seedSt->execute([(int) $seller['id'], (int) $seller['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_return_settings') {
    $returnWindowDays = max(0, min(30, (int) ($_POST['return_window_days'] ?? 7)));
    $returnConditions = trim((string) ($_POST['return_conditions'] ?? ''));
    $refundMethod = trim((string) ($_POST['refund_method'] ?? 'original_payment'));

    if (!isset($refundMethods[$refundMethod])) {
        $refundMethod = 'original_payment';
    }
    if (strlen($returnConditions) > 3000) {
        $returnConditions = substr($returnConditions, 0, 3000);
    }

    $upd = $pdo->prepare(
        'UPDATE seller_return_settings
         SET return_window_days = ?, return_conditions = ?, refund_method = ?
         WHERE seller_id = ?
         LIMIT 1'
    );
    $upd->execute([$returnWindowDays, $returnConditions, $refundMethod, (int) $seller['id']]);
    $flash = 'Return & refund settings updated successfully.';
    $flashOk = true;
}

$settingsSt = $pdo->prepare(
    'SELECT return_window_days, return_conditions, refund_method, updated_at
     FROM seller_return_settings
     WHERE seller_id = ?
     LIMIT 1'
);
$settingsSt->execute([(int) $seller['id']]);
$settings = $settingsSt->fetch() ?: [
    'return_window_days' => 7,
    'return_conditions' => '',
    'refund_method' => 'original_payment',
    'updated_at' => null,
];

$selectedRefundMethod = (string) ($settings['refund_method'] ?? 'original_payment');
if (!isset($refundMethods[$selectedRefundMethod])) {
    $selectedRefundMethod = 'original_payment';
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Returns & refunds</h1>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>" style="margin-bottom:14px"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Return window</div>
              <div class="seller-kpi-card__value"><?= (int) ($settings['return_window_days'] ?? 0) ?> day(s)</div>
              <div class="seller-kpi-card__hint">Customer can request return within this period</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v5h5"/><path d="M3 8a9 9 0 1 0 2.64-6.36L3 3"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Refund method</div>
              <div class="seller-kpi-card__value"><?= h((string) ($refundMethods[$selectedRefundMethod] ?? 'Original payment source')) ?></div>
              <div class="seller-kpi-card__hint">Last updated: <?= h((string) ($settings['updated_at'] ?? '-')) ?></div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Configure return policy</h2>
          </div>
          <div class="card-body">
            <form method="post" class="seller-withdraw-form">
              <input type="hidden" name="action" value="save_return_settings">

              <div class="seller-form__row">
                <div>
                  <label for="return_window_days">Return window (days)</label>
                  <input id="return_window_days" class="seller-stock-input" type="number" min="0" max="30" name="return_window_days" value="<?= (int) ($settings['return_window_days'] ?? 7) ?>" required>
                </div>
                <div>
                  <label for="refund_method">Refund method</label>
                  <select id="refund_method" class="seller-badge-input" name="refund_method" required>
                    <?php foreach ($refundMethods as $methodCode => $methodLabel): ?>
                      <option value="<?= h($methodCode) ?>"<?= $selectedRefundMethod === $methodCode ? ' selected' : '' ?>><?= h($methodLabel) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div>
                <label for="return_conditions">Return conditions</label>
                <textarea id="return_conditions" class="seller-badge-input" style="min-height:120px" name="return_conditions" placeholder="Example: Product unused hona chahiye, original packaging required, damaged product return nahi hoga, etc."><?= h((string) ($settings['return_conditions'] ?? '')) ?></textarea>
              </div>

              <div class="seller-actions">
                <button class="admin-btn admin-btn--primary" type="submit">Save return & refund settings</button>
              </div>
            </form>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
