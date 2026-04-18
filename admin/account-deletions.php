<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Delete account request';
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
$totalRequests = count($rows);
$pendingRequests = 0;
foreach ($rows as $r0) {
    if (($r0['status'] ?? '') === 'pending') {
        $pendingRequests++;
    }
}

function admin_deletion_status_badge(string $status): string
{
    return match (strtolower($status)) {
        'pending' => 'admin-del-pill admin-del-pill--pending',
        'cancelled' => 'admin-del-pill admin-del-pill--rejected',
        'completed' => 'admin-del-pill admin-del-pill--done',
        default => 'admin-del-pill admin-del-pill--muted',
    };
}

function admin_deletion_format_dt(?string $dt): string
{
    if ($dt === null || $dt === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($dt))->format('d M y');
    } catch (Throwable) {
        return (string) $dt;
    }
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-del-requests">
          <div class="admin-del-head">
            <div class="admin-del-head__main">
              <div class="admin-del-title-row">
                <h1 class="admin-del-title">Delete account request</h1>
                <?php if ($totalRequests > 0): ?>
                  <span class="admin-del-count-badge" title="Total requests"><?= (int) $totalRequests ?></span>
                <?php endif; ?>
              </div>
              <nav class="admin-del-breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a>
                <span class="admin-del-breadcrumb__sep">›</span>
                <span aria-current="page">Delete account request</span>
              </nav>
            </div>
            <div class="admin-del-head__actions">
              <details class="admin-del-export">
                <summary class="admin-del-btn admin-del-btn--outline">Export</summary>
                <div class="admin-del-export__menu">
                  <button type="button" class="admin-del-export__item" disabled>CSV (soon)</button>
                  <button type="button" class="admin-del-export__item" disabled>PDF (soon)</button>
                </div>
              </details>
              <button type="button" class="admin-ghost-btn" title="Refresh" aria-label="Refresh" onclick="location.reload()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
              </button>
              <button type="button" class="admin-ghost-btn" title="Layout" aria-label="Layout">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
              </button>
            </div>
          </div>

          <?php if ($flash): ?>
            <div class="admin-del-flash<?= $flash['ok'] ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?>" role="status">
              <?= h($flash['text']) ?>
            </div>
          <?php endif; ?>

          <div class="admin-del-toolbar">
            <div class="admin-del-toolbar__search">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <input type="search" class="admin-del-toolbar__input" placeholder="Search in table…" name="q" autocomplete="off" disabled aria-label="Search (coming soon)" />
            </div>
            <select class="admin-del-select" disabled aria-label="Filter"><option>Filter</option></select>
            <span class="admin-date-pill admin-del-date" title="Date range">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?= h((new DateTimeImmutable('-28 days'))->format('d M y')) ?> - <?= h((new DateTimeImmutable())->format('d M y')) ?>
            </span>
            <select class="admin-del-select" disabled aria-label="Sort by"><option>Sort by</option></select>
            <button type="button" class="admin-del-btn-columns" disabled title="Coming soon">Manage columns</button>
          </div>

          <div class="admin-del-process">
            <p class="admin-del-process__text"><?= (int) $pendingRequests ?> pending · Run purge after the 48h window to remove due accounts.</p>
            <form method="post" class="admin-del-process__form">
              <input type="hidden" name="action" value="process_due" />
              <button type="submit" class="admin-del-btn admin-del-btn--primary">Process overdue deletions</button>
            </form>
          </div>

          <div class="admin-card admin-del-card">
            <div class="admin-table-wrap admin-del-table-wrap">
              <table class="admin-table admin-del-table">
                <thead>
                  <tr>
                    <th class="admin-del-th-check"><input type="checkbox" disabled aria-label="Select all" /></th>
                    <th class="admin-del-th-star" aria-hidden="true">★</th>
                    <th>User name</th>
                    <th>Requested</th>
                    <th>Delete after</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th class="admin-del-th-action">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$rows): ?>
                    <tr><td colspan="8" class="admin-del-empty">No deletion requests yet.</td></tr>
                  <?php else: ?>
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
                      ?>
                      <tr>
                        <td class="admin-del-td-check"><input type="checkbox" disabled aria-label="Select row" /></td>
                        <td class="admin-del-td-star"><button type="button" class="admin-del-star" disabled aria-hidden="true">☆</button></td>
                        <td>
                          <div class="admin-del-user">
                            <div class="admin-avatar-sm admin-del-avatar"><?= h($ini) ?></div>
                            <div class="admin-del-user__text">
                              <div class="admin-del-user__name"><?= h($fullName) ?></div>
                              <div class="admin-del-user__meta"><?= h((string) $r['email']) ?> · ID <?= (int) $r['user_id'] ?></div>
                            </div>
                          </div>
                        </td>
                        <td><?= h(admin_deletion_format_dt((string) ($r['requested_at'] ?? ''))) ?></td>
                        <td><?= h(admin_deletion_format_dt((string) ($r['process_after'] ?? ''))) ?></td>
                        <td><span class="admin-del-reason">User requested account removal</span></td>
                        <td><span class="<?= admin_deletion_status_badge($st) ?>"><?= h(ucfirst((string) ($r['status'] ?? ''))) ?></span></td>
                        <td>
                          <?php if ($st === 'pending'): ?>
                            <details class="admin-del-menu">
                              <summary class="admin-del-menu__trigger" aria-label="Actions">⋮</summary>
                              <div class="admin-del-menu__panel">
                                <form method="post" onsubmit="return confirm('Cancel this deletion request? The user account will stay active.');">
                                  <input type="hidden" name="action" value="cancel" />
                                  <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>" />
                                  <button type="submit" class="admin-del-menu__action">Cancel request</button>
                                </form>
                              </div>
                            </details>
                          <?php else: ?>
                            <span class="admin-del-menu__dash">—</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <div class="admin-del-table-footer">
              <label class="admin-del-page-size">
                Show
                <select disabled><option>10</option></select>
                entries
              </label>
              <nav class="admin-del-pagination" aria-label="Pagination">
                <button type="button" class="admin-del-page-btn" disabled>Previous</button>
                <button type="button" class="admin-del-page-btn admin-del-page-btn--active" aria-current="page">1</button>
                <button type="button" class="admin-del-page-btn" disabled>Next</button>
              </nav>
            </div>
          </div>
        </div>

       

        <div class="admin-card admin-del-card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Seller deletion requests</h2>
          </div>
          <div class="admin-table-wrap admin-del-table-wrap">
            <table class="admin-table admin-del-table">
              <thead>
                <tr>
                  <th>Seller</th>
                  <th>Requested</th>
                  <th>Status</th>
                  <th>Reason</th>
                  <th class="admin-del-th-action">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$sellerRows): ?>
                  <tr><td colspan="5" class="admin-del-empty">No seller deletion requests yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($sellerRows as $sr): ?>
                    <?php $sStatus = strtolower((string) ($sr['status'] ?? 'pending')); ?>
                    <tr>
                      <td>
                        <strong><?= h((string) ($sr['full_name'] ?: 'Seller')) ?></strong><br>
                        <small><?= h((string) ($sr['email'] ?? '')) ?> · ID <?= (int) ($sr['seller_id'] ?? 0) ?></small>
                      </td>
                      <td><?= h(admin_deletion_format_dt((string) ($sr['requested_at'] ?? ''))) ?></td>
                      <td><span class="<?= admin_deletion_status_badge($sStatus) ?>"><?= h(ucfirst((string) ($sr['status'] ?? 'pending'))) ?></span></td>
                      <td><?= h((string) (($sr['rejection_reason'] ?? '') !== '' ? $sr['rejection_reason'] : '-')) ?></td>
                      <td>
                        <?php if ($sStatus === 'pending'): ?>
                          <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            <input type="hidden" name="request_id" value="<?= (int) $sr['id'] ?>">
                            <button type="submit" name="action" value="approve_seller_delete" class="admin-del-btn admin-del-btn--primary" onclick="return confirm('Approve karke seller account delete karna hai?');">Approve</button>
                            <input type="text" name="rejection_reason" placeholder="Reject reason (required)" minlength="5">
                            <button type="submit" name="action" value="reject_seller_delete" class="admin-del-btn admin-del-btn--outline" onclick="return confirm('Seller deletion request reject karna hai?');">Reject</button>
                          </form>
                        <?php else: ?>
                          <span class="admin-del-menu__dash">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
