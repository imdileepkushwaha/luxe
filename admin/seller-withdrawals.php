<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

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

$pendingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM seller_withdraw_requests WHERE status = 'pending'"
)->fetchColumn();

$rows = $pdo->query(
    "SELECT w.id, w.seller_id, w.amount, w.method, w.account_ref, w.note, w.status,
            w.requested_at, w.reviewed_at, w.rejection_reason,
            s.email AS seller_email, s.full_name AS seller_name, s.business_name AS seller_business
     FROM seller_withdraw_requests w
     INNER JOIN seller_users s ON s.id = w.seller_id
     ORDER BY (w.status = 'pending') DESC, w.id DESC
     LIMIT 500"
)->fetchAll();

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

        <?php if ($flash !== null): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?>" role="status" style="margin-bottom:14px">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head" style="margin-bottom:16px">
          <h1>Seller withdraw Requests</h1>
          <p style="margin:6px 0 0;font-size:0.9rem;color:var(--admin-text-muted)">
            Pending requests approve karke <strong>Mark as paid</strong> karein jab payout complete ho. Reject par seller ko reason dikhega.
            <?php if ($pendingCount > 0): ?>
              <span class="admin-status admin-status--processing" style="margin-left:8px"><?= (int) $pendingCount ?> pending</span>
            <?php endif; ?>
          </p>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title">All requests</h2>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Seller</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Account / UPI</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Reviewed</th>
                    <th>Note</th>
                    <th>Action</th>
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
                    ?>
                    <tr>
                      <td><strong>#<?= $wid ?></strong></td>
                      <td>
                        <div><?= h((string) ($row['seller_name'] ?? '-')) ?></div>
                        <?php if ($biz !== ''): ?>
                          <div style="font-size:0.82rem;color:var(--admin-text-muted)"><?= h($biz) ?></div>
                        <?php endif; ?>
                        <div style="font-size:0.82rem;color:var(--admin-text-muted)"><?= h((string) ($row['seller_email'] ?? '-')) ?></div>
                        <a href="seller-view.php?id=<?= $sid ?>" style="font-size:0.82rem">Seller profile →</a>
                      </td>
                      <td>Rs <?= number_format((int) ($row['amount'] ?? 0)) ?></td>
                      <td><?= h((string) ($row['method'] ?? '-')) ?></td>
                      <td style="max-width:200px;word-break:break-word"><?= h((string) ($row['account_ref'] ?? '-')) ?></td>
                      <td><span class="<?= admin_withdraw_status_badge((string) ($row['status'] ?? '')) ?>"><?= h((string) ($row['status'] ?? '-')) ?></span></td>
                      <td><?= h((string) ($row['requested_at'] ?? '—')) ?></td>
                      <td><?= h((string) ($row['reviewed_at'] ?? '—')) ?></td>
                      <td style="max-width:180px;font-size:0.86rem">
                        <?= h((string) ($row['note'] ?? '—')) ?>
                        <?php if (trim((string) ($row['rejection_reason'] ?? '')) !== ''): ?>
                          <div style="color:#b91c1c;margin-top:4px">Reason: <?= h((string) $row['rejection_reason']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($isPending): ?>
                          <div style="display:flex;flex-direction:column;gap:8px;min-width:140px">
                            <form method="post" style="margin:0" onsubmit="return confirm('Payout complete — is request ko paid mark karein?');">
                              <input type="hidden" name="action" value="mark_paid">
                              <input type="hidden" name="request_id" value="<?= $wid ?>">
                              <button type="submit" class="admin-btn admin-btn--primary" style="width:100%">Mark as paid</button>
                            </form>
                            <form method="post" style="margin:0" onsubmit="return confirm('Is withdraw request reject karni hai?');">
                              <input type="hidden" name="action" value="reject">
                              <input type="hidden" name="request_id" value="<?= $wid ?>">
                              <input type="text" name="rejection_reason" required minlength="5" maxlength="255" placeholder="Reject reason" style="width:100%;margin-bottom:6px;padding:6px 8px;border:1px solid var(--admin-border);border-radius:6px;font-size:0.82rem">
                              <button type="submit" class="admin-btn admin-btn--outline" style="width:100%">Reject</button>
                            </form>
                          </div>
                        <?php else: ?>
                          <span style="font-size:0.86rem;color:var(--admin-text-muted)">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($rows === []): ?>
                    <tr><td colspan="10">Koi withdraw request nahi mili.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
