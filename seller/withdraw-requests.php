<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_finance.php';
require_once __DIR__ . '/../includes/withdraw_otp.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Withdraw requests';
$activeNav = 'withdraw';

$flash = '';
$flashOk = false;

$smsCfg = withdraw_otp_sms_config();
$otpMode = (string) ($smsCfg['withdraw_otp_mode'] ?? 'stub');
$withdrawOtpChallenge = withdraw_otp_pending_challenge_for_seller((int) $seller['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'cancel_withdraw_otp') {
        withdraw_otp_cancel_challenge();
        header('Location: withdraw-requests.php');
        exit;
    }

    if ($action === 'request_withdraw_otp') {
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
            $err = withdraw_otp_begin_challenge($pdo, (int) $seller['id'], [
                'amount' => $amount,
                'method' => $method,
                'account_ref' => $accountRef,
                'note' => $note,
            ]);
            if ($err !== null) {
                $flash = $err;
            } else {
                header('Location: withdraw-requests.php?msg=otp_sent');
                exit;
            }
        }
    } elseif ($action === 'confirm_withdraw_otp') {
        $otp = (string) ($_POST['otp'] ?? '');
        $err = withdraw_otp_confirm_and_create_request($pdo, (int) $seller['id'], $otp);
        if ($err !== null) {
            $flash = $err;
        } else {
            header('Location: withdraw-requests.php?msg=created');
            exit;
        }
    }
}

$msg = (string) ($_GET['msg'] ?? '');
if ($msg === 'created') {
    $flash = 'Withdraw request submit ho gayi. Admin review ke baad update milega.';
    $flashOk = true;
} elseif ($msg === 'otp_sent') {
    $last4 = (string) ($withdrawOtpChallenge['phone_last4'] ?? '');
    $hint = $last4 !== '' ? (' Number ke last 4 digits: **' . $last4 . '.') : '';
    if ($otpMode !== 'production') {
        $devCode = (string) ($smsCfg['withdraw_otp_dev_code'] ?? '123456');
        $flash = 'OTP process start ho gaya.' . $hint . ' Abhi dev/stub mode hai — OTP enter karein: ' . $devCode . ' (PHP error_log me seller number + stub phone bhi dikhega.)';
    } else {
        $flash = 'OTP aapke profile wale mobile par bheja gaya.' . $hint;
    }
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
            <h2 class="card-title"><?= $withdrawOtpChallenge !== null ? 'OTP confirm karein' : 'Create new request' ?></h2>
          </div>
          <div class="card-body">
            <?php if ($withdrawOtpChallenge !== null): ?>
              <p class="seller-help" style="margin-top:0;margin-bottom:14px">
                Profile wale number par OTP bheja gaya hai (last 4: <strong><?= h((string) ($withdrawOtpChallenge['phone_last4'] ?? '')) ?></strong>).
                <?php if ($otpMode !== 'production'): ?>
                  <br><strong>Dev / stub:</strong> OTP abhi SMS se nahi jaata — <code style="font-size:0.9em"><?= h((string) ($smsCfg['withdraw_otp_dev_code'] ?? '123456')) ?></code> enter karein. Live SMS ke liye <code>includes/config.php</code> me <code>sms.withdraw_otp_mode</code> = <code>production</code> aur <code>sms_api_url</code> set karein.
 <?php endif; ?>
              </p>
              <form method="post" class="seller-withdraw-form">
                <input type="hidden" name="action" value="confirm_withdraw_otp">
                <div>
                  <label for="withdraw_otp">Mobile OTP</label>
                  <input id="withdraw_otp" class="seller-stock-input" type="text" name="otp" inputmode="numeric" pattern="[0-9]*" maxlength="8" required autocomplete="one-time-code" placeholder="6-digit OTP">
                </div>
                <div class="seller-actions" style="flex-wrap:wrap;gap:10px">
                  <button class="admin-btn admin-btn--primary" type="submit">Confirm &amp; submit request</button>
                </div>
              </form>
              <form method="post" style="margin-top:12px">
                <input type="hidden" name="action" value="cancel_withdraw_otp">
                <button class="admin-btn admin-btn--ghost-light" type="submit">Cancel — form wapas kholen</button>
              </form>
            <?php else: ?>
              <p class="seller-help" style="margin-top:0;margin-bottom:14px">
                Request se pehle OTP aapke <a href="profile.php">profile</a> wale mobile number par jayega taake confirm ho sake ki request aapne hi lagai hai.
              </p>
              <form method="post" class="seller-withdraw-form">
                <input type="hidden" name="action" value="request_withdraw_otp">
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
                  <button class="admin-btn admin-btn--primary" type="submit">Send OTP to mobile</button>
                </div>
              </form>
            <?php endif; ?>
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
