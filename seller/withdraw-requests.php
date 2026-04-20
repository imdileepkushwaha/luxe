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

require_once __DIR__ . '/../admin/_pagination.php';

$withdrawHistoryTotal = seller_finance_withdraw_request_count($pdo, (int) $seller['id']);
$withdrawPendingCountSt = $pdo->prepare(
    "SELECT COUNT(*) FROM seller_withdraw_requests WHERE seller_id = ? AND LOWER(TRIM(status)) = 'pending'"
);
$withdrawPendingCountSt->execute([(int) $seller['id']]);
$withdrawHistoryPendingRows = (int) $withdrawPendingCountSt->fetchColumn();

['page' => $withdrawListPage, 'perPage' => $withdrawPerPage] = admin_pagination_read(25);
$withdrawPageMeta = admin_pagination_resolve($withdrawHistoryTotal, $withdrawListPage, $withdrawPerPage);
$withdrawHistoryPage = $withdrawPageMeta['page'];
$withdrawHistoryPerPage = $withdrawPageMeta['perPage'];
$withdrawHistoryTotalPages = $withdrawPageMeta['totalPages'];
$requests = seller_finance_withdraw_requests($pdo, (int) $seller['id'], $withdrawHistoryPerPage, $withdrawPageMeta['offset']);

function seller_withdraw_format_dt(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('M j, Y · g:i A');
    } catch (Throwable) {
        return $raw;
    }
}

function seller_withdraw_chip_mod(string $status): string
{
    return match (strtolower(trim($status))) {
        'paid', 'approved' => 'seller-status-chip--delivered',
        'rejected' => 'seller-status-chip--rejected',
        'pending' => 'seller-status-chip--pending',
        default => '',
    };
}

function seller_withdraw_status_label(string $status): string
{
    $s = strtolower(trim($status));

    return match ($s) {
        'paid' => 'Paid',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'pending' => 'Pending',
        default => $s !== '' ? ucfirst($s) : '—',
    };
}

$withdrawHistoryCount = $withdrawHistoryTotal;

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-txn-head seller-withdraw-page-head">
          <div>
            <h1>Withdraw requests</h1>
            <p class="seller-txn-subtitle">OTP aapke <a href="profile.php">profile</a> mobile par aata hai. Pending requests withdrawable balance se hold hoti hain jab tak admin <strong>approve</strong>, <strong>reject</strong>, ya <strong>paid</strong> na kare.</p>
          </div>
          <div class="admin-page-head__actions seller-txn-card-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="transactions.php">Transactions</a>
            <a class="admin-btn admin-btn--ghost-light" href="earnings.php">Earnings</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-withdraw-flash seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi seller-txn-kpi seller-withdraw-kpi">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Withdrawable</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['withdrawable_balance'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Abhi request kar sakte ho</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="m17 7-5-5-5 5"/><path d="M5 17h14"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Pending (held)</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['pending_withdraw_total'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Admin review ke under</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Paid out</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['paid_out_total'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Approve / paid withdrawals</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">History rows</div>
              <div class="seller-kpi-card__value"><?= (int) $withdrawHistoryCount ?></div>
              <div class="seller-kpi-card__hint"><?= (int) $withdrawHistoryPendingRows ?> pending · neeche pagination</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
            </div>
          </div>
        </div>

        <div class="card seller-txn-card seller-withdraw-card seller-withdraw-card--form">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title"><?= $withdrawOtpChallenge !== null ? 'OTP confirm karein' : 'Nayi withdraw request' ?></h2>
              <p class="card-subtitle seller-txn-card-sub">
                <?= $withdrawOtpChallenge !== null
                    ? 'Code enter karke request lock karein. Cancel se wapas amount edit kar sakte ho.'
                    : 'Minimum ₹100. Pehle OTP bhejenge, phir request create hogi.' ?>
              </p>
            </div>
            <?php if ($withdrawOtpChallenge === null): ?>
              <span class="seller-txn-count-pill seller-withdraw-pill--muted">OTP required</span>
            <?php endif; ?>
          </div>
          <div class="card-body seller-withdraw-card-body">
            <?php if ($withdrawOtpChallenge !== null): ?>
              <div class="seller-withdraw-callout seller-withdraw-callout--otp" role="status">
                <p class="seller-withdraw-callout__title">Mobile OTP bheja gaya</p>
                <p class="seller-withdraw-callout__text">
                  Last 4 digits: <strong><?= h((string) ($withdrawOtpChallenge['phone_last4'] ?? '')) ?></strong>
                  <?php if ($otpMode !== 'production'): ?>
                    <span class="seller-withdraw-callout__break"><strong>Dev / stub:</strong> SMS nahi jaata — code <code class="seller-withdraw-code"><?= h((string) ($smsCfg['withdraw_otp_dev_code'] ?? '123456')) ?></code> try karein. Live ke liye <code class="seller-withdraw-code">sms.withdraw_otp_mode</code> = production.</span>
                  <?php endif; ?>
                </p>
              </div>
              <form method="post" class="seller-withdraw-form seller-withdraw-form--otp">
                <input type="hidden" name="action" value="confirm_withdraw_otp">
                <div class="seller-withdraw-field seller-withdraw-field--otp">
                  <label for="withdraw_otp">Mobile OTP</label>
                  <input id="withdraw_otp" class="seller-stock-input seller-withdraw-otp-input" type="text" name="otp" inputmode="numeric" pattern="[0-9]*" maxlength="8" required autocomplete="one-time-code" placeholder="6-digit OTP">
                </div>
                <div class="seller-withdraw-form-actions">
                  <button class="admin-btn admin-btn--primary" type="submit">Confirm &amp; submit request</button>
                </div>
              </form>
              <form method="post" class="seller-withdraw-cancel-form">
                <input type="hidden" name="action" value="cancel_withdraw_otp">
                <button class="admin-btn admin-btn--ghost-light" type="submit">Cancel — form wapas kholen</button>
              </form>
            <?php else: ?>
              <div class="seller-withdraw-callout seller-withdraw-callout--info">
                <p class="seller-withdraw-callout__text">OTP sirf verify karta hai ki request aapne hi bheji hai. Payout details admin panel se process hoti hai.</p>
              </div>
              <form method="post" class="seller-withdraw-form seller-withdraw-form--create">
                <input type="hidden" name="action" value="request_withdraw_otp">
                <div class="seller-withdraw-form-grid">
                  <div class="seller-withdraw-field">
                    <label for="amount">Amount (₹)</label>
                    <input id="amount" class="seller-stock-input" type="number" min="100" step="1" name="amount" required value="<?= h((string) ($_POST['amount'] ?? '')) ?>" placeholder="100 se zyada">
                  </div>
                  <div class="seller-withdraw-field">
                    <label for="method">Method</label>
                    <select id="method" class="seller-status-select" name="method">
                      <?php $m = (string) ($_POST['method'] ?? 'bank'); ?>
                      <option value="bank"<?= $m === 'bank' ? ' selected' : '' ?>>Bank transfer</option>
                      <option value="upi"<?= $m === 'upi' ? ' selected' : '' ?>>UPI</option>
                    </select>
                  </div>
                </div>
                <div class="seller-withdraw-field">
                  <label for="account_ref">Bank account / UPI ID</label>
                  <input id="account_ref" class="seller-badge-input" type="text" name="account_ref" required value="<?= h((string) ($_POST['account_ref'] ?? '')) ?>" placeholder="Account no. ya name@upi">
                </div>
                <div class="seller-withdraw-field">
                  <label for="note">Note <span class="seller-withdraw-optional">(optional)</span></label>
                  <input id="note" class="seller-badge-input" type="text" name="note" maxlength="255" value="<?= h((string) ($_POST['note'] ?? '')) ?>" placeholder="Admin ke liye chhota note">
                </div>
                <div class="seller-withdraw-form-actions">
                  <button class="admin-btn admin-btn--primary" type="submit">Send OTP to mobile</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="card seller-txn-card seller-withdraw-card seller-withdraw-card--history">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Request history</h2>
              <p class="card-subtitle seller-txn-card-sub">WR id, amount, method, account, status ya date se dhundh sakte ho. Search sirf <strong>is page</strong> par filter karta hai.</p>
            </div>
            <span class="seller-txn-count-pill"><?= (int) $withdrawHistoryCount ?> row<?= $withdrawHistoryCount === 1 ? '' : 's' ?></span>
          </div>
          <div class="card-body card-body--flush">
            <div class="seller-txn-search-bar">
              <label class="seller-inventory-search-wrap seller-txn-search" for="sellerWithdrawHistorySearch">
                <span class="seller-inventory-search-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                  type="search"
                  id="sellerWithdrawHistorySearch"
                  class="seller-inventory-search-input"
                  placeholder="Search WR, amount, UPI, bank, status…"
                  autocomplete="off"
                  aria-label="Search withdraw history"
                  <?= $requests === [] ? 'disabled' : '' ?>
                >
              </label>
            </div>
            <div class="admin-table-wrap seller-txn-table-wrap">
              <table class="admin-table seller-txn-table seller-withdraw-history-table">
                <thead>
                  <tr>
                    <th>Requested</th>
                    <th>Reference</th>
                    <th class="seller-txn-th-amount">Amount</th>
                    <th>Method</th>
                    <th>Account / UPI</th>
                    <th>Status</th>
                    <th>Reviewed</th>
                    <th>Note</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($requests as $row): ?>
                    <?php
                    $wid = (int) ($row['id'] ?? 0);
                    $stRaw = (string) ($row['status'] ?? '');
                    $stMod = seller_withdraw_chip_mod($stRaw);
                    $stLabel = seller_withdraw_status_label($stRaw);
                    $reqRaw = trim((string) ($row['requested_at'] ?? ''));
                    $reqFmt = seller_withdraw_format_dt($reqRaw);
                    $revRaw = trim((string) ($row['reviewed_at'] ?? ''));
                    $revFmt = $revRaw !== '' ? seller_withdraw_format_dt($revRaw) : '';
                    $wamt = (int) ($row['amount'] ?? 0);
                    $methodRaw = strtolower(trim((string) ($row['method'] ?? '')));
                    $methodLabel = $methodRaw === 'upi' ? 'UPI' : ($methodRaw === 'bank' ? 'Bank' : ($methodRaw !== '' ? ucfirst($methodRaw) : '—'));
                    $methodPillMod = $methodRaw === 'upi' ? 'seller-withdraw-method-pill--upi' : 'seller-withdraw-method-pill--bank';
                    $acct = trim((string) ($row['account_ref'] ?? ''));
                    $note = trim((string) ($row['note'] ?? ''));
                    $rej = trim((string) ($row['rejection_reason'] ?? ''));
                    $searchBlob = mb_strtolower(
                        'wr' . (string) $wid . ' '
                        . (string) $wid . ' '
                        . (string) $wamt . ' '
                        . preg_replace('/[^\d]/', '', (string) $wamt) . ' '
                        . strtolower($stRaw) . ' '
                        . strtolower($stLabel) . ' '
                        . $methodRaw . ' '
                        . strtolower($methodLabel) . ' '
                        . $note . ' '
                        . $acct . ' '
                        . $rej . ' '
                        . $reqRaw . ' '
                        . $reqFmt . ' '
                        . $revRaw . ' '
                        . $revFmt
                    );
                    ?>
                    <tr class="seller-withdraw-history-row" data-withdraw-search="<?= h($searchBlob) ?>">
                      <td class="seller-txn-td-muted"><?= h($reqFmt) ?></td>
                      <td><span class="seller-product-list-sku">WR<?= $wid ?></span></td>
                      <td class="seller-txn-td-amount seller-withdraw-amt">₹<?= number_format($wamt, 0, '.', ',') ?></td>
                      <td>
                        <?php if ($methodLabel !== '—'): ?>
                          <span class="seller-withdraw-method-pill <?= h($methodPillMod) ?>"><?= h($methodLabel) ?></span>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($acct !== ''): ?>
                          <span class="seller-withdraw-acct" title="<?= h($acct) ?>"><?= h($acct) ?></span>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </td>
                      <td><span class="seller-status-chip <?= h($stMod !== '' ? $stMod : '') ?>"><?= h($stLabel) ?></span></td>
                      <td class="seller-txn-td-muted"><?= $revRaw !== '' ? h($revFmt) : '—' ?></td>
                      <td class="seller-withdraw-note-cell">
                        <?php if ($note !== ''): ?>
                          <span class="seller-withdraw-note"><?= h($note) ?></span>
                        <?php else: ?>
                          <span class="seller-withdraw-note seller-withdraw-note--empty">—</span>
                        <?php endif; ?>
                        <?php if ($rej !== ''): ?>
                          <div class="seller-withdraw-rejection">Reason: <?= h($rej) ?></div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($requests !== []): ?>
                    <tr id="sellerWithdrawHistoryNoMatch" class="seller-txn-no-match-row" style="display:none">
                      <td colspan="8">
                        <div class="seller-txn-no-match-inner">
                          <span class="seller-txn-no-match-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                          </span>
                          <div>
                            <strong>No matching rows</strong>
                            <p>WR number, amount, account, ya status try karein.</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                  <?php if ($requests === []): ?>
                    <tr>
                      <td colspan="8">
                        <div class="seller-txn-empty seller-withdraw-empty">
                          <p class="seller-txn-empty__title">Abhi koi withdraw request nahi</p>
                          <p class="seller-txn-empty__text">Jab balance ho, upar se OTP ke saath nayi request bhejein. Summary <a href="transactions.php">Transactions</a> par bhi dekho.</p>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'withdraw-requests.php';
            $paginationTotal = $withdrawHistoryTotal;
            $paginationPage = $withdrawHistoryPage;
            $paginationPerPage = $withdrawHistoryPerPage;
            $paginationTotalPages = $withdrawHistoryTotalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
          </div>
        </div>

        <script>
          (function () {
            function wireWithdrawHistorySearch(inputId, rowSelector, noMatchId) {
              var input = document.getElementById(inputId);
              if (!input || input.disabled) return;
              var rows = document.querySelectorAll(rowSelector);
              var noMatch = document.getElementById(noMatchId);
              function apply() {
                var q = (input.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                rows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-withdraw-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) {
                    return hay.indexOf(w) !== -1;
                  });
                  tr.style.display = show ? '' : 'none';
                  if (show) anyShown = true;
                });
                if (noMatch) {
                  noMatch.style.display = (words.length > 0 && !anyShown) ? '' : 'none';
                }
              }
              input.addEventListener('input', apply);
              input.addEventListener('search', apply);
            }
            wireWithdrawHistorySearch('sellerWithdrawHistorySearch', 'tr.seller-withdraw-history-row', 'sellerWithdrawHistoryNoMatch');
          })();
        </script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
