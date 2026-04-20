<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Account deletions';
$activeNav = 'deletions';

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'cancel') {
        $rid = (int) ($_POST['request_id'] ?? 0);
        if ($rid > 0 && account_deletion_admin_cancel($pdo, $rid)) {
            header('Location: account-deletions.php?msg=cancelled');
            exit;
        }
        header('Location: account-deletions.php?msg=cancel_fail');
        exit;
    }
    if ($action === 'process_due') {
        $n = account_deletion_process_overdue($pdo);
        header('Location: account-deletions.php?msg=purge&n=' . $n);
        exit;
    }
    if ($action === 'approve_seller_delete') {
        $rid = (int) ($_POST['request_id'] ?? 0);
        if ($rid > 0 && seller_deletion_admin_approve($pdo, $rid, (int) $admin['id'])) {
            header('Location: account-deletions.php?msg=seller_approved');
            exit;
        }
        header('Location: account-deletions.php?msg=seller_approve_fail');
        exit;
    }
    if ($action === 'reject_seller_delete') {
        $rid = (int) ($_POST['request_id'] ?? 0);
        $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
        if ($rid > 0 && strlen($reason) >= 5 && seller_deletion_admin_reject($pdo, $rid, (int) $admin['id'], $reason)) {
            header('Location: account-deletions.php?msg=seller_rejected');
            exit;
        }
        header('Location: account-deletions.php?msg=seller_reject_fail');
        exit;
    }
}

$msgKey = (string) ($_GET['msg'] ?? '');
if ($msgKey === 'cancelled') {
    $flash = ['ok' => true, 'text' => 'Request cancelled. The user account was not deleted.'];
} elseif ($msgKey === 'cancel_fail') {
    $flash = ['ok' => false, 'text' => 'Could not cancel that request (already processed or invalid).'];
} elseif ($msgKey === 'purge') {
    $n = (int) ($_GET['n'] ?? 0);
    $flash = ['ok' => true, 'text' => $n > 0 ? "Processed {$n} overdue deletion(s)." : 'No overdue deletions to process.'];
} elseif ($msgKey === 'seller_approved') {
    $flash = ['ok' => true, 'text' => 'Seller deletion request approved. Seller account deleted.'];
} elseif ($msgKey === 'seller_approve_fail') {
    $flash = ['ok' => false, 'text' => 'Could not approve seller deletion request.'];
} elseif ($msgKey === 'seller_rejected') {
    $flash = ['ok' => true, 'text' => 'Seller deletion request rejected.'];
} elseif ($msgKey === 'seller_reject_fail') {
    $flash = ['ok' => false, 'text' => 'Could not reject seller deletion request.'];
}

$rows = account_deletion_admin_list($pdo);
$sellerRows = seller_deletion_admin_list($pdo);

$buyerPending = 0;
foreach ($rows as $r0) {
    if (($r0['status'] ?? '') === 'pending') {
        $buyerPending++;
    }
}
$sellerPending = 0;
foreach ($sellerRows as $s0) {
    if (($s0['status'] ?? '') === 'pending') {
        $sellerPending++;
    }
}
$buyerTotal = count($rows);
$sellerTotal = count($sellerRows);

/**
 * @param mixed $dt
 */
function admin_deletions_fmt_dt($dt): string
{
    if ($dt === null || $dt === '') {
        return '—';
    }
    $s = (string) $dt;
    try {
        return (new DateTimeImmutable($s))->format('M j, Y · g:i A');
    } catch (Throwable) {
        return $s;
    }
}

/**
 * @param array<string, mixed> $r
 */
function admin_deletions_buyer_haystack(array $r): string
{
    $fn = trim((string) ($r['first_name'] ?? '') . ' ' . (string) ($r['last_name'] ?? ''));
    $parts = [
        (string) ($r['id'] ?? ''),
        (string) ($r['user_id'] ?? ''),
        (string) ($r['email'] ?? ''),
        $fn,
        (string) ($r['status'] ?? ''),
        (string) ($r['requested_at'] ?? ''),
        (string) ($r['process_after'] ?? ''),
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

/**
 * @param array<string, mixed> $r
 */
function admin_deletions_seller_haystack(array $r): string
{
    $parts = [
        (string) ($r['id'] ?? ''),
        (string) ($r['seller_id'] ?? ''),
        (string) ($r['email'] ?? ''),
        (string) ($r['full_name'] ?? ''),
        (string) ($r['status'] ?? ''),
        (string) ($r['requested_at'] ?? ''),
        (string) ($r['reviewed_at'] ?? ''),
        (string) ($r['rejection_reason'] ?? ''),
        (string) ($r['is_active'] ?? ''),
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

function admin_deletions_user_status_badge(string $status): string
{
    return match (strtolower($status)) {
        'pending' => 'admin-status admin-status--processing',
        'cancelled' => 'admin-status admin-status--cancelled',
        'completed' => 'admin-status admin-status--delivered',
        default => 'admin-status admin-status--processing',
    };
}

function admin_deletions_seller_status_badge(string $status): string
{
    return match (strtolower($status)) {
        'pending' => 'admin-status admin-status--processing',
        'approved' => 'admin-status admin-status--delivered',
        'rejected' => 'admin-status admin-status--cancelled',
        default => 'admin-status admin-status--processing',
    };
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-deletions-page">
        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?> admin-deletions-flash" role="status">
            <?= h((string) $flash['text']) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Privacy &amp; safety</span>
            <h1>Account deletions</h1>
            <p class="admin-page-head__lede">Shopper account deletion queue (48h window) and seller deletion requests — cancel or approve before data is removed.</p>
          </div>
          <div class="admin-page-head__actions">
            <a class="admin-btn admin-btn--outline" href="users.php">Users</a>
            <a class="admin-btn admin-btn--outline" href="sellers.php">Sellers</a>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats admin-grid--stats--flow admin-deletions-kpi-grid" aria-label="Deletion summary">
          <div class="admin-card admin-stat admin-stat--stripe-amber admin-deletions-kpi">
            <div>
              <div class="admin-stat__label admin-deletions-kpi__label">Buyer pending</div>
              <div class="admin-stat__value"><?= (int) $buyerPending ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Awaiting window / purge</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--pink" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-blue admin-deletions-kpi">
            <div>
              <div class="admin-stat__label admin-deletions-kpi__label">Buyer requests</div>
              <div class="admin-stat__value"><?= (int) $buyerTotal ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">All time (list below)</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-red admin-deletions-kpi">
            <div>
              <div class="admin-stat__label admin-deletions-kpi__label">Seller pending</div>
              <div class="admin-stat__value"><?= (int) $sellerPending ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Needs approve / reject</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--red" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-teal admin-deletions-kpi">
            <div>
              <div class="admin-stat__label admin-deletions-kpi__label">Seller requests</div>
              <div class="admin-stat__value"><?= (int) $sellerTotal ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">All time (list below)</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--green" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="14" y1="2" x2="14" y2="8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
            </div>
          </div>
        </div>

        <div class="card admin-deletions-purge-card">
          <div class="card-header admin-deletions-purge-header">
            <div>
              <h2 class="card-title">Process overdue</h2>
              <p class="card-subtitle admin-deletions-purge-sub">After the cooling-off period, run purge to delete accounts that are due. <?= (int) $buyerPending ?> buyer request<?= $buyerPending === 1 ? '' : 's' ?> currently pending.</p>
            </div>
            <form method="post" class="admin-deletions-purge-form">
              <input type="hidden" name="action" value="process_due">
              <button type="submit" class="admin-btn admin-btn--primary">Process overdue deletions</button>
            </form>
          </div>
        </div>

        <div class="card admin-deletions-section-card">
          <div class="card-header admin-deletions-table-header">
            <div class="admin-deletions-table-head">
              <div class="admin-deletions-table-head-text">
                <h2 class="card-title">Shopper account deletions</h2>
                <p class="card-subtitle admin-deletions-table-sub">Users who asked to delete their account — cancel before the scheduled window if needed. · Search filters this list only.</p>
              </div>
              <?php if ($rows !== []): ?>
                <label class="admin-users-search-wrap admin-deletions-search-wrap" for="adminDelBuyerSearch">
                  <span class="admin-users-search-icon admin-deletions-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminDelBuyerSearch"
                    class="admin-users-search-input admin-deletions-search-input"
                    placeholder="Search name, email, ID, status…"
                    autocomplete="off"
                    aria-label="Search buyer deletion requests"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($rows === []): ?>
              <p class="admin-empty-hint admin-empty-hint--boxed">No shopper deletion requests yet.</p>
            <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table admin-deletions-table">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Requested</th>
                    <th>Delete after</th>
                    <th>Note</th>
                    <th class="admin-table__th-narrow">Status</th>
                    <th class="admin-table__th-narrow">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $r): ?>
                    <?php
                    $fullName = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
                    if ($fullName === '') {
                        $fullName = 'User';
                    }
                    $ini = strtoupper(substr($fullName, 0, 1) . substr(trim((string) $r['last_name']), 0, 1));
                    if (strlen($ini) < 2) {
                        $ini = strtoupper(substr($fullName, 0, 2));
                    }
                    if ($ini === '') {
                        $ini = '?';
                    }
                    $st = strtolower((string) ($r['status'] ?? ''));
                    $hay = admin_deletions_buyer_haystack($r);
                    ?>
                    <tr class="admin-deletions-buyer-row" data-deletions-buyer-search="<?= h($hay) ?>">
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <div class="admin-deletions-user">
                            <span class="admin-deletions-user__name"><?= h($fullName) ?></span>
                            <span class="admin-deletions-user__meta"><?= h((string) $r['email']) ?> · ID <?= (int) $r['user_id'] ?></span>
                          </div>
                        </div>
                      </td>
                      <td class="admin-table__td-muted"><?= h(admin_deletions_fmt_dt($r['requested_at'] ?? null)) ?></td>
                      <td class="admin-table__td-muted"><?= h(admin_deletions_fmt_dt($r['process_after'] ?? null)) ?></td>
                      <td><span class="admin-deletions-note">User requested account removal</span></td>
                      <td><span class="<?= admin_deletions_user_status_badge($st) ?>"><?= h(ucfirst((string) ($r['status'] ?? ''))) ?></span></td>
                      <td>
                        <?php if ($st === 'pending'): ?>
                          <form method="post" class="admin-deletions-action-form" onsubmit="return confirm('Cancel this deletion request? The user account will stay active.');">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="admin-btn admin-btn--outline admin-deletions-cancel-btn">Cancel request</button>
                          </form>
                        <?php else: ?>
                          <span class="admin-deletions-dash">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="adminDelBuyerNoMatch" class="admin-deletions-no-match-row">
                    <td colspan="6">
                      <div class="admin-deletions-no-match">
                        <strong class="admin-deletions-no-match__title">No matches</strong>
                        <p class="admin-deletions-no-match__text">Try another keyword (this list only).</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <script>
            (function () {
              var searchInput = document.getElementById('adminDelBuyerSearch');
              if (!searchInput) return;
              var rowEls = document.querySelectorAll('tr.admin-deletions-buyer-row');
              var noMatch = document.getElementById('adminDelBuyerNoMatch');
              function run() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var any = false;
                rowEls.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-deletions-buyer-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) { return hay.indexOf(w) !== -1; });
                  tr.style.display = show ? '' : 'none';
                  if (show) any = true;
                });
                if (noMatch) noMatch.style.display = (words.length > 0 && !any) ? 'table-row' : 'none';
              }
              searchInput.addEventListener('input', run);
              searchInput.addEventListener('search', run);
            })();
            </script>
            <?php endif; ?>
          </div>
        </div>

        <div class="card admin-deletions-section-card admin-deletions-section-card--spaced">
          <div class="card-header admin-deletions-table-header">
            <div class="admin-deletions-table-head">
              <div class="admin-deletions-table-head-text">
                <h2 class="card-title">Seller deletion requests</h2>
                <p class="card-subtitle admin-deletions-table-sub">Approve deletes the seller account (and related flows); reject with a reason shown to the seller. · Search filters this list only.</p>
              </div>
              <?php if ($sellerRows !== []): ?>
                <label class="admin-users-search-wrap admin-deletions-search-wrap" for="adminDelSellerSearch">
                  <span class="admin-users-search-icon admin-deletions-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminDelSellerSearch"
                    class="admin-users-search-input admin-deletions-search-input"
                    placeholder="Search seller, email, status…"
                    autocomplete="off"
                    aria-label="Search seller deletion requests"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($sellerRows === []): ?>
              <p class="admin-empty-hint admin-empty-hint--boxed">No seller deletion requests yet.</p>
            <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table admin-deletions-table">
                <thead>
                  <tr>
                    <th>Seller</th>
                    <th>Requested</th>
                    <th class="admin-table__th-narrow">Status</th>
                    <th>Note / reason</th>
                    <th class="admin-table__th-narrow">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($sellerRows as $sr): ?>
                    <?php
                    $sStatus = strtolower((string) ($sr['status'] ?? 'pending'));
                    $fn = trim((string) ($sr['full_name'] ?? ''));
                    $ini = strtoupper(substr(preg_replace('/\s+/', '', $fn), 0, 2));
                    if ($ini === '') {
                        $ini = '?';
                    }
                    $hayS = admin_deletions_seller_haystack($sr);
                    ?>
                    <tr class="admin-deletions-seller-row" data-deletions-seller-search="<?= h($hayS) ?>">
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <div class="admin-deletions-user">
                            <span class="admin-deletions-user__name"><?= h((string) ($sr['full_name'] ?: 'Seller')) ?></span>
                            <span class="admin-deletions-user__meta"><?= h((string) ($sr['email'] ?? '')) ?> · ID <?= (int) ($sr['seller_id'] ?? 0) ?></span>
                          </div>
                        </div>
                      </td>
                      <td class="admin-table__td-muted"><?= h(admin_deletions_fmt_dt($sr['requested_at'] ?? null)) ?></td>
                      <td><span class="<?= admin_deletions_seller_status_badge($sStatus) ?>"><?= h(ucfirst((string) ($sr['status'] ?? 'pending'))) ?></span></td>
                      <td class="admin-deletions-seller-note">
                        <?= h((string) (($sr['rejection_reason'] ?? '') !== '' ? $sr['rejection_reason'] : '—')) ?>
                      </td>
                      <td>
                        <?php if ($sStatus === 'pending'): ?>
                          <div class="admin-deletions-seller-actions">
                            <form method="post" class="admin-deletions-seller-actions__form">
                              <input type="hidden" name="request_id" value="<?= (int) $sr['id'] ?>">
                              <button type="submit" name="action" value="approve_seller_delete" class="admin-btn admin-btn--primary admin-deletions-seller-actions__btn" onclick="return confirm('Approve karke seller account delete karna hai?');">Approve</button>
                            </form>
                            <form method="post" class="admin-deletions-seller-actions__form">
                              <input type="hidden" name="request_id" value="<?= (int) $sr['id'] ?>">
                              <input type="text" name="rejection_reason" class="admin-input admin-deletions-seller-actions__input" placeholder="Reject reason (min 5)" minlength="5" maxlength="255" autocomplete="off">
                              <button type="submit" name="action" value="reject_seller_delete" class="admin-btn admin-btn--outline admin-deletions-seller-actions__btn admin-deletions-seller-actions__btn--reject" onclick="return confirm('Seller deletion request reject karna hai?');">Reject</button>
                            </form>
                          </div>
                        <?php else: ?>
                          <span class="admin-deletions-dash">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="adminDelSellerNoMatch" class="admin-deletions-no-match-row">
                    <td colspan="5">
                      <div class="admin-deletions-no-match">
                        <strong class="admin-deletions-no-match__title">No matches</strong>
                        <p class="admin-deletions-no-match__text">Try another keyword (this list only).</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <script>
            (function () {
              var searchInput = document.getElementById('adminDelSellerSearch');
              if (!searchInput) return;
              var rowEls = document.querySelectorAll('tr.admin-deletions-seller-row');
              var noMatch = document.getElementById('adminDelSellerNoMatch');
              function run() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var any = false;
                rowEls.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-deletions-seller-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) { return hay.indexOf(w) !== -1; });
                  tr.style.display = show ? '' : 'none';
                  if (show) any = true;
                });
                if (noMatch) noMatch.style.display = (words.length > 0 && !any) ? 'table-row' : 'none';
              }
              searchInput.addEventListener('input', run);
              searchInput.addEventListener('search', run);
            })();
            </script>
            <?php endif; ?>
          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
