<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_finance.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Withdraw requests';
$activeNav = 'withdraw';

$flash = '';
$flashOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'create_withdraw_request') {
    $amount = max(0, (int) ($_POST['amount'] ?? 0));
    $method = strtolower(trim((string) ($_POST['method'] ?? 'bank')));
    $accountRef = trim((string) ($_POST['account_ref'] ?? ''));
    $note = trim((string) ($_POST['note'] ?? ''));
    if (!in_array($method, ['bank', 'upi'], true)) {
        $method = 'bank';
    }
    if (strlen($note) > 255) {
        $note = substr($note, 0, 255);
    }

    $summary = seller_finance_summary($pdo, (int) $seller['id']);
    $available = (int) $summary['withdrawable_balance'];

    if ($amount < 100) {
        $flash = 'Minimum withdraw amount Rs 100 hai.';
    } elseif ($accountRef === '') {
        $flash = 'Account / UPI details required hai.';
    } elseif ($amount > $available) {
        $flash = 'Requested amount withdrawable balance se zyada hai.';
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO seller_withdraw_requests (seller_id, amount, method, account_ref, note, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([(int) $seller['id'], $amount, $method, $accountRef, $note, 'pending']);
        header('Location: withdraw-requests.php?msg=created');
        exit;
    }
}

$msg = (string) ($_GET['msg'] ?? '');
if ($msg === 'created') {
    $flash = 'Withdraw request submit ho gayi. Admin review ke baad update milega.';
    $flashOk = true;
}

$summary = seller_finance_summary($pdo, (int) $seller['id']);
$requests = seller_finance_withdraw_requests($pdo, (int) $seller['id'], 100);

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Withdraw request</h1>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>" style="margin-bottom:14px"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Withdrawable</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['withdrawable_balance']) ?></div>
              <div class="seller-kpi-card__hint">Amount available right now</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="m17 7-5-5-5 5"/><path d="M5 17h14"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Pending requests</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['pending_withdraw_total']) ?></div>
              <div class="seller-kpi-card__hint">Under admin review</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Paid out</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['paid_out_total']) ?></div>
              <div class="seller-kpi-card__hint">Already approved/paid withdrawals</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Create new request</h2>
          </div>
          <div class="card-body">
            <form method="post" class="seller-withdraw-form">
              <input type="hidden" name="action" value="create_withdraw_request">
              <div>
                <label for="amount">Amount (Rs)</label>
                <input id="amount" class="seller-stock-input" type="number" min="100" step="1" name="amount" required value="<?= h((string) ($_POST['amount'] ?? '')) ?>">
              </div>
              <div>
                <label for="method">Method</label>
                <select id="method" class="seller-status-select" name="method">
                  <?php $m = (string) ($_POST['method'] ?? 'bank'); ?>
                  <option value="bank"<?= $m === 'bank' ? ' selected' : '' ?>>Bank transfer</option>
                  <option value="upi"<?= $m === 'upi' ? ' selected' : '' ?>>UPI</option>
                </select>
              </div>
              <div>
                <label for="account_ref">Bank account / UPI ID</label>
                <input id="account_ref" class="seller-badge-input" type="text" name="account_ref" required value="<?= h((string) ($_POST['account_ref'] ?? '')) ?>" placeholder="e.g. 9876543210 / name@upi">
              </div>
              <div>
                <label for="note">Note (optional)</label>
                <input id="note" class="seller-badge-input" type="text" name="note" maxlength="255" value="<?= h((string) ($_POST['note'] ?? '')) ?>" placeholder="Any payout note">
              </div>
              <div class="seller-actions">
                <button class="admin-btn admin-btn--primary" type="submit">Submit request</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Request history</h2>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Account / UPI</th>
                    <th>Status</th>
                    <th>Requested at</th>
                    <th>Reviewed at</th>
                    <th>Note</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($requests as $row): ?>
                    <tr>
                      <td>#WR<?= (int) ($row['id'] ?? 0) ?></td>
                      <td>Rs <?= number_format((int) ($row['amount'] ?? 0)) ?></td>
                      <td><?= h((string) ($row['method'] ?? '-')) ?></td>
                      <td><?= h((string) ($row['account_ref'] ?? '-')) ?></td>
                      <td><span class="seller-status-chip seller-status-chip--<?= h(strtolower((string) ($row['status'] ?? 'pending'))) ?>"><?= h((string) ($row['status'] ?? 'pending')) ?></span></td>
                      <td><?= h((string) ($row['requested_at'] ?? '-')) ?></td>
                      <td><?= h((string) ($row['reviewed_at'] ?? '-')) ?></td>
                      <td>
                        <?= h((string) ($row['note'] ?? '-')) ?>
                        <?php if (trim((string) ($row['rejection_reason'] ?? '')) !== ''): ?>
                          <div style="color:#b91c1c;font-size:0.78rem;margin-top:4px">Reason: <?= h((string) $row['rejection_reason']) ?></div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($requests === []): ?>
                    <tr><td colspan="8">No withdraw requests yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
