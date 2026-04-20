<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_pagination.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Seller withdrawals';
$activeNav = 'seller_withdrawals';

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $adminId = (int) ($admin['id'] ?? 0);

    if ($requestId <= 0 || $adminId <= 0) {
        header('Location: seller-withdrawals.php?msg=invalid');
        exit;
    }

    if ($action === 'mark_paid') {
        $upd = $pdo->prepare(
            "UPDATE seller_withdraw_requests
             SET status = 'paid',
                 reviewed_by = ?,
                 reviewed_at = NOW(),
                 rejection_reason = ''
             WHERE id = ? AND status = 'pending'
             LIMIT 1"
        );
        $upd->execute([$adminId, $requestId]);
        header('Location: seller-withdrawals.php?msg=' . ($upd->rowCount() > 0 ? 'paid' : 'fail'));
        exit;
    }

    if ($action === 'reject') {
        $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
        if (strlen($reason) < 5) {
            header('Location: seller-withdrawals.php?msg=reason_short');
            exit;
        }
        if (strlen($reason) > 255) {
            $reason = substr($reason, 0, 255);
        }
        $upd = $pdo->prepare(
            "UPDATE seller_withdraw_requests
             SET status = 'rejected',
                 reviewed_by = ?,
                 reviewed_at = NOW(),
                 rejection_reason = ?
             WHERE id = ? AND status = 'pending'
             LIMIT 1"
        );
        $upd->execute([$adminId, $reason, $requestId]);
        header('Location: seller-withdrawals.php?msg=' . ($upd->rowCount() > 0 ? 'rejected' : 'fail'));
        exit;
    }

    header('Location: seller-withdrawals.php?msg=invalid');
    exit;
}

$msg = (string) ($_GET['msg'] ?? '');
if ($msg === 'paid') {
    $flash = ['ok' => true, 'text' => 'Withdrawal marked as paid. Seller balance update ho chuka hai.'];
} elseif ($msg === 'rejected') {
    $flash = ['ok' => true, 'text' => 'Withdraw request reject ho gayi.'];
} elseif ($msg === 'fail') {
    $flash = ['ok' => false, 'text' => 'Action apply nahi ho paya — shayad request pehle se process ho chuki hai.'];
} elseif ($msg === 'invalid') {
    $flash = ['ok' => false, 'text' => 'Invalid request.'];
} elseif ($msg === 'reason_short') {
    $flash = ['ok' => false, 'text' => 'Reject karne ke liye kam se kam 5 characters ka reason likhein.'];
}

$wdStats = $pdo->query(
    "SELECT
        COUNT(*) AS total_all,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_n,
        COALESCE(SUM(CASE WHEN status IN ('paid', 'approved') THEN 1 ELSE 0 END), 0) AS paid_n,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_n
     FROM seller_withdraw_requests"
)->fetch(PDO::FETCH_ASSOC) ?: [];
$totalWithdrawals = (int) ($wdStats['total_all'] ?? 0);
$pendingCount = (int) ($wdStats['pending_n'] ?? 0);
$paidCount = (int) ($wdStats['paid_n'] ?? 0);
$rejectedCount = (int) ($wdStats['rejected_n'] ?? 0);

['page' => $reqPage, 'perPage' => $perPage] = admin_pagination_read(25);
$pMeta = admin_pagination_resolve($totalWithdrawals, $reqPage, $perPage);
$page = $pMeta['page'];
$offset = $pMeta['offset'];
$perPage = $pMeta['perPage'];
$totalPages = $pMeta['totalPages'];

if ($totalWithdrawals === 0) {
    $rows = [];
} else {
    $rowsSt = $pdo->prepare(
        "SELECT w.id, w.seller_id, w.amount, w.method, w.account_ref, w.note, w.status,
                w.requested_at, w.reviewed_at, w.rejection_reason,
                s.email AS seller_email, s.full_name AS seller_name, s.business_name AS seller_business
         FROM seller_withdraw_requests w
         INNER JOIN seller_users s ON s.id = w.seller_id
         ORDER BY (w.status = 'pending') DESC, w.id DESC
         LIMIT ? OFFSET ?"
    );
    $rowsSt->bindValue(1, $perPage, PDO::PARAM_INT);
    $rowsSt->bindValue(2, $offset, PDO::PARAM_INT);
    $rowsSt->execute();
    $rows = $rowsSt->fetchAll();
}

/**
 * @param mixed $raw
 */
function admin_wd_fmt_dt($raw): string
{
    if ($raw === null || $raw === '') {
        return '—';
    }
    $s = (string) $raw;
    try {
        return (new DateTimeImmutable($s))->format('M j, Y · g:i A');
    } catch (Throwable $e) {
        return $s;
    }
}

/**
 * @param array<string, mixed> $row
 */
function admin_wd_search_haystack(array $row): string
{
    $parts = [
        (string) ($row['id'] ?? ''),
        (string) ($row['seller_id'] ?? ''),
        (string) ($row['seller_name'] ?? ''),
        (string) ($row['seller_email'] ?? ''),
        (string) ($row['seller_business'] ?? ''),
        (string) ($row['amount'] ?? ''),
        (string) ($row['method'] ?? ''),
        (string) ($row['account_ref'] ?? ''),
        (string) ($row['status'] ?? ''),
        (string) ($row['note'] ?? ''),
        (string) ($row['rejection_reason'] ?? ''),
        (string) ($row['requested_at'] ?? ''),
        (string) ($row['reviewed_at'] ?? ''),
    ];
    $clean = [];
    foreach ($parts as $p) {
        $t = trim((string) $p);
        if ($t !== '') {
            $clean[] = $t;
        }
    }
    $s = implode(' ', $clean);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($s, 'UTF-8');
    }

    return strtolower($s);
}

function admin_withdraw_status_badge(string $status): string
{
    return match (strtolower($status)) {
        'pending' => 'admin-status admin-status--processing',
        'paid', 'approved' => 'admin-status admin-status--delivered',
        'rejected' => 'admin-status admin-status--cancelled',
        default => 'admin-status admin-status--processing',
    };
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-withdrawals-page">
        <?php if ($flash !== null): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?> admin-wd-flash" role="status">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Payouts</span>
            <h1>Seller withdrawals</h1>
            <p class="admin-page-head__lede">Review payout requests — mark paid after bank/UPI transfer, or reject with a reason visible to the seller.</p>
          </div>
          <div class="admin-page-head__actions">
            <a class="admin-btn admin-btn--outline" href="sellers.php">Sellers</a>
            <a class="admin-btn admin-btn--outline" href="earnings.php">Earnings</a>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats admin-grid--stats--flow admin-wd-kpi-grid" aria-label="Withdrawal summary">
          <div class="admin-card admin-stat admin-stat--stripe-amber admin-wd-kpi">
            <div>
              <div class="admin-stat__label admin-wd-kpi__label">Pending</div>
              <div class="admin-stat__value"><?= (int) $pendingCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Awaiting action</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--pink" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-green admin-wd-kpi">
            <div>
              <div class="admin-stat__label admin-wd-kpi__label">Paid</div>
              <div class="admin-stat__value"><?= (int) $paidCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Completed payouts</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--green" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-red admin-wd-kpi">
            <div>
              <div class="admin-stat__label admin-wd-kpi__label">Rejected</div>
              <div class="admin-stat__value"><?= (int) $rejectedCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Declined requests</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--red" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-blue admin-wd-kpi">
            <div>
              <div class="admin-stat__label admin-wd-kpi__label">All requests</div>
              <div class="admin-stat__value"><?= (int) $totalWithdrawals ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Total in database</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
        </div>

        <div class="card admin-wd-table-card">
          <div class="card-header admin-wd-table-header">
            <div class="admin-wd-table-head">
              <div class="admin-wd-table-head-text">
                <h2 class="card-title">Withdrawal requests</h2>
                <p class="card-subtitle admin-wd-table-sub">
                  <?= (int) $totalWithdrawals ?> total · Search filters this page only. Pending rows sort first.
                </p>
              </div>
              <?php if ($rows !== []): ?>
                <label class="admin-users-search-wrap admin-wd-search-wrap" for="adminWdSearch">
                  <span class="admin-users-search-icon admin-wd-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminWdSearch"
                    class="admin-users-search-input admin-wd-search-input"
                    placeholder="Search seller, amount, method, status, ref…"
                    autocomplete="off"
                    aria-label="Search withdrawals"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($totalWithdrawals === 0): ?>
              <p class="admin-empty-hint admin-empty-hint--boxed">Koi withdraw request nahi mili.</p>
            <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table admin-wd-table">
                <thead>
                  <tr>
                    <th class="admin-table__th-narrow">ID</th>
                    <th>Seller</th>
                    <th class="admin-table__th-money">Amount</th>
                    <th class="admin-table__th-narrow">Method</th>
                    <th>Account / UPI</th>
                    <th class="admin-table__th-narrow">Status</th>
                    <th>Requested</th>
                    <th>Reviewed</th>
                    <th>Note</th>
                    <th class="admin-table__th-narrow">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $row): ?>
                    <?php
                    $wid = (int) ($row['id'] ?? 0);
                    $sid = (int) ($row['seller_id'] ?? 0);
                    $st = strtolower((string) ($row['status'] ?? 'pending'));
                    $isPending = $st === 'pending';
                    $biz = trim((string) ($row['seller_business'] ?? ''));
                    $hay = admin_wd_search_haystack($row);
                    $fn = trim((string) ($row['seller_name'] ?? ''));
                    $ini = strtoupper(substr(preg_replace('/\s+/', '', $fn), 0, 2));
                    if ($ini === '') {
                        $ini = '?';
                    }
                    ?>
                    <tr class="admin-wd-row" data-wd-search="<?= h($hay) ?>">
                      <td class="admin-table__td-num"><strong>#<?= $wid ?></strong></td>
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <div class="admin-wd-seller">
                            <span class="admin-wd-seller__name"><?= h($fn !== '' ? $fn : '—') ?></span>
                            <?php if ($biz !== ''): ?>
                              <span class="admin-wd-seller__biz"><?= h($biz) ?></span>
                            <?php endif; ?>
                            <span class="admin-wd-seller__email"><?= h((string) ($row['seller_email'] ?? '—')) ?></span>
                            <a class="admin-wd-seller__link" href="seller-view.php?id=<?= $sid ?>">Seller profile →</a>
                          </div>
                        </div>
                      </td>
                      <td class="admin-table__td-money">₹<?= number_format((int) ($row['amount'] ?? 0)) ?></td>
                      <td><span class="admin-badge admin-badge--muted"><?= h((string) ($row['method'] ?? '—')) ?></span></td>
                      <td class="admin-wd-account"><?= h((string) ($row['account_ref'] ?? '—')) ?></td>
                      <td><span class="<?= admin_withdraw_status_badge((string) ($row['status'] ?? '')) ?>"><?= h((string) ($row['status'] ?? '—')) ?></span></td>
                      <td class="admin-table__td-muted"><?= h(admin_wd_fmt_dt($row['requested_at'] ?? null)) ?></td>
                      <td class="admin-table__td-muted"><?= h(admin_wd_fmt_dt($row['reviewed_at'] ?? null)) ?></td>
                      <td class="admin-wd-note-cell">
                        <span class="admin-wd-note"><?= h((string) ($row['note'] ?? '—')) ?></span>
                        <?php if (trim((string) ($row['rejection_reason'] ?? '')) !== ''): ?>
                          <span class="admin-wd-reject-reason">Reason: <?= h((string) $row['rejection_reason']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($isPending): ?>
                          <div class="admin-wd-actions">
                            <form method="post" class="admin-wd-actions__form" onsubmit="return confirm('Payout complete — is request ko paid mark karein?');">
                              <input type="hidden" name="action" value="mark_paid">
                              <input type="hidden" name="request_id" value="<?= $wid ?>">
                              <button type="submit" class="admin-btn admin-btn--primary admin-wd-actions__btn">Mark as paid</button>
                            </form>
                            <form method="post" class="admin-wd-actions__form" onsubmit="return confirm('Is withdraw request reject karni hai?');">
                              <input type="hidden" name="action" value="reject">
                              <input type="hidden" name="request_id" value="<?= $wid ?>">
                              <input type="text" name="rejection_reason" class="admin-input admin-wd-actions__input" required minlength="5" maxlength="255" placeholder="Reject reason (min 5)" autocomplete="off">
                              <button type="submit" class="admin-btn admin-btn--outline admin-wd-actions__btn admin-wd-actions__btn--reject">Reject</button>
                            </form>
                          </div>
                        <?php else: ?>
                          <span class="admin-wd-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="adminWdNoMatchRow" class="admin-wd-no-match-row">
                    <td colspan="10">
                      <div class="admin-wd-no-match">
                        <strong class="admin-wd-no-match__title">No matches</strong>
                        <p class="admin-wd-no-match__text">Try another keyword — this page only.</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'seller-withdrawals.php';
            $paginationTotal = $totalWithdrawals;
            $paginationPage = $page;
            $paginationPerPage = $perPage;
            $paginationTotalPages = $totalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            <script>
            (function () {
              var searchInput = document.getElementById('adminWdSearch');
              if (!searchInput) return;
              var rowEls = document.querySelectorAll('tr.admin-wd-row');
              var noMatchRow = document.getElementById('adminWdNoMatchRow');
              function applyWdSearch() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                rowEls.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-wd-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) {
                    return hay.indexOf(w) !== -1;
                  });
                  tr.style.display = show ? '' : 'none';
                  if (show) anyShown = true;
                });
                if (noMatchRow) {
                  noMatchRow.style.display = (words.length > 0 && !anyShown) ? 'table-row' : 'none';
                }
              }
              searchInput.addEventListener('input', applyWdSearch);
              searchInput.addEventListener('search', applyWdSearch);
            })();
            </script>
            <?php endif; ?>
          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
