<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Delivery options';
$activeNav = 'delivery';

$defaultOptions = [
    'standard' => ['label' => 'Standard Delivery', 'eta_min' => 3, 'eta_max' => 7, 'fee' => 0, 'sort' => 1],
    'express' => ['label' => 'Express Delivery', 'eta_min' => 1, 'eta_max' => 3, 'fee' => 99, 'sort' => 2],
    'same_day' => ['label' => 'Same Day Delivery', 'eta_min' => 0, 'eta_max' => 1, 'fee' => 199, 'sort' => 3],
];

// Seed defaults once.
$seedSt = $pdo->prepare(
    'INSERT INTO seller_delivery_options
        (seller_id, option_code, option_label, eta_min_days, eta_max_days, fee_amount, is_active, sort_order)
     SELECT ?, ?, ?, ?, ?, ?, 1, ?
     WHERE NOT EXISTS (
       SELECT 1 FROM seller_delivery_options WHERE seller_id = ? AND option_code = ?
     )'
);
foreach ($defaultOptions as $code => $cfg) {
    $seedSt->execute([
        (int) $seller['id'],
        $code,
        $cfg['label'],
        (int) $cfg['eta_min'],
        (int) $cfg['eta_max'],
        (int) $cfg['fee'],
        (int) $cfg['sort'],
        (int) $seller['id'],
        $code,
    ]);
}

$flash = '';
$flashOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_delivery_options') {
    $rows = $_POST['options'] ?? [];
    if (!is_array($rows)) {
        $flash = 'Invalid request payload.';
    } else {
        $upd = $pdo->prepare(
            'UPDATE seller_delivery_options
             SET option_label = ?, eta_min_days = ?, eta_max_days = ?, fee_amount = ?, is_active = ?
             WHERE seller_id = ? AND option_code = ?
             LIMIT 1'
        );
        foreach ($rows as $code => $row) {
            if (!is_array($row)) {
                continue;
            }
            $optionCode = strtolower(trim((string) $code));
            if (!isset($defaultOptions[$optionCode])) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? $defaultOptions[$optionCode]['label']));
            if ($label === '') {
                $label = $defaultOptions[$optionCode]['label'];
            }
            if (strlen($label) > 80) {
                $label = substr($label, 0, 80);
            }
            $etaMin = max(0, min(30, (int) ($row['eta_min_days'] ?? 0)));
            $etaMax = max($etaMin, min(45, (int) ($row['eta_max_days'] ?? $etaMin)));
            $fee = max(0, (int) ($row['fee_amount'] ?? 0));
            $isActive = isset($row['is_active']) ? 1 : 0;

            $upd->execute([$label, $etaMin, $etaMax, $fee, $isActive, (int) $seller['id'], $optionCode]);
        }
        $flash = 'Delivery options updated successfully.';
        $flashOk = true;
    }
}

$optionsSt = $pdo->prepare(
    'SELECT option_code, option_label, eta_min_days, eta_max_days, fee_amount, is_active, updated_at
     FROM seller_delivery_options
     WHERE seller_id = ?
     ORDER BY sort_order ASC, id ASC'
);
$optionsSt->execute([(int) $seller['id']]);
$options = $optionsSt->fetchAll();

$activeCount = 0;
foreach ($options as $opt) {
    if ((int) ($opt['is_active'] ?? 0) === 1) {
        $activeCount++;
    }
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Delivery options</h1>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>" style="margin-bottom:14px"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Total options</div>
              <div class="seller-kpi-card__value"><?= count($options) ?></div>
              <div class="seller-kpi-card__hint">Delivery choices configured</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/><path d="M5 5v14"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Active options</div>
              <div class="seller-kpi-card__value"><?= $activeCount ?></div>
              <div class="seller-kpi-card__hint">Shown to customers</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <div class="seller-card-head">
              <h2 class="card-title">Manage delivery options</h2>
              <button type="submit" form="deliveryOptionsForm" class="admin-btn admin-btn--primary">Save delivery options</button>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <form method="post" id="deliveryOptionsForm">
              <input type="hidden" name="action" value="save_delivery_options">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Option</th>
                      <th>Min ETA (days)</th>
                      <th>Max ETA (days)</th>
                      <th>Fee (Rs)</th>
                      <th>Active</th>
                      <th>Last updated</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($options as $opt): ?>
                      <?php $code = (string) ($opt['option_code'] ?? ''); ?>
                      <tr>
                        <td>
                          <input class="seller-badge-input" type="text" maxlength="80" name="options[<?= h($code) ?>][label]" value="<?= h((string) ($opt['option_label'] ?? '')) ?>" required>
                          <div style="font-size:0.76rem;color:var(--admin-text-muted);margin-top:4px">Code: <?= h($code) ?></div>
                        </td>
                        <td>
                          <input class="seller-stock-input" type="number" min="0" max="30" name="options[<?= h($code) ?>][eta_min_days]" value="<?= (int) ($opt['eta_min_days'] ?? 0) ?>" required>
                        </td>
                        <td>
                          <input class="seller-stock-input" type="number" min="0" max="45" name="options[<?= h($code) ?>][eta_max_days]" value="<?= (int) ($opt['eta_max_days'] ?? 0) ?>" required>
                        </td>
                        <td>
                          <input class="seller-stock-input" type="number" min="0" step="1" name="options[<?= h($code) ?>][fee_amount]" value="<?= (int) ($opt['fee_amount'] ?? 0) ?>" required>
                        </td>
                        <td>
                          <label style="display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="options[<?= h($code) ?>][is_active]" value="1"<?= (int) ($opt['is_active'] ?? 0) === 1 ? ' checked' : '' ?>>
                            Enabled
                          </label>
                        </td>
                        <td><?= h((string) ($opt['updated_at'] ?? '-')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if ($options === []): ?>
                      <tr><td colspan="6">No delivery options configured.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </form>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
