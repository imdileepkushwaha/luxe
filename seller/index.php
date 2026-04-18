<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

$flash = '';
$flashOk = false;
$pendingDeletionRequest = seller_deletion_pending_for_seller($pdo, (int) $seller['id']);
$latestDeletionRequest = seller_deletion_latest_for_seller($pdo, (int) $seller['id']);
$latestDeletionByEmail = seller_deletion_latest_for_email($pdo, (string) $seller['email']);
$effectiveLatestDeletionRequest = $latestDeletionByEmail ?: $latestDeletionRequest;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete_account') {
        $confirmText = trim((string) ($_POST['confirm_text'] ?? ''));
        if (strtoupper($confirmText) !== 'DELETE') {
            $flash = 'Account delete karne ke liye confirmation box me DELETE likhna zaruri hai.';
            $flashOk = false;
        } elseif ($pendingDeletionRequest || ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'pending')) {
            $flash = 'Aapki deletion request already pending hai. Admin review ka wait karein.';
            $flashOk = false;
        } elseif ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved') {
            $flash = 'Deletion request already approved hai. Nayi request allowed nahi hai.';
            $flashOk = false;
        } else {
            $result = seller_deletion_request_create(
                $pdo,
                (int) $seller['id'],
                (string) $seller['email'],
                (string) $seller['full_name']
            );
            if ($result === true) {
                $flash = 'Account deletion request admin ko bhej di gayi hai.';
                $flashOk = true;
                $pendingDeletionRequest = seller_deletion_pending_for_seller($pdo, (int) $seller['id']);
                $latestDeletionRequest = seller_deletion_latest_for_seller($pdo, (int) $seller['id']);
                $latestDeletionByEmail = seller_deletion_latest_for_email($pdo, (string) $seller['email']);
                $effectiveLatestDeletionRequest = $latestDeletionByEmail ?: $latestDeletionRequest;
            } else {
                $flash = (string) $result;
                $flashOk = false;
            }
        }
    }
}

$allowedCategories = is_array($seller['allowed_categories']) ? $seller['allowed_categories'] : [];
$kycCompleted = (int) ($seller['kyc_completed'] ?? 0) === 1;
$kycFinalApproved = (int) ($seller['kyc_final_approved'] ?? 0) === 1;
$kycRejectionReason = trim((string) ($seller['kyc_rejection_reason'] ?? ''));
$kycUpdatedAt = (string) ($seller['kyc_updated_at'] ?? '');

$ordersCount = 0;
$revenueCount = 0;
$productsCount = 0;
$recentOrders = [];

$ordersCountSt = $pdo->prepare(
    "SELECT COUNT(DISTINCT o.id)
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     WHERE p.seller_id = ?"
);
$ordersCountSt->execute([(int) $seller['id']]);
$ordersCount = (int) $ordersCountSt->fetchColumn();

$revenueSt = $pdo->prepare(
    "SELECT COALESCE(SUM(x.total_amount), 0)
     FROM (
         SELECT o.id, o.total_amount
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.id
         INNER JOIN products p ON p.id = oi.product_id
         WHERE p.seller_id = ?
         GROUP BY o.id, o.total_amount
     ) x"
);
$revenueSt->execute([(int) $seller['id']]);
$revenueCount = (int) $revenueSt->fetchColumn();

$productsCountSt = $pdo->prepare(
    'SELECT COUNT(*) FROM products WHERE active = 1 AND approval_status = \'approved\' AND seller_id = ?'
);
$productsCountSt->execute([(int) $seller['id']]);
$productsCount = (int) $productsCountSt->fetchColumn();

$recentOrdersSt = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.created_at, u.first_name, u.last_name
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE p.seller_id = ?
     GROUP BY o.id, o.order_ref, o.status, o.total_amount, o.created_at, u.first_name, u.last_name
     ORDER BY o.id DESC
     LIMIT 8"
);
$recentOrdersSt->execute([(int) $seller['id']]);
$recentOrders = $recentOrdersSt->fetchAll();

function seller_order_status_class(string $status): string
{
    return match (strtolower($status)) {
        'delivered' => 'admin-status admin-status--delivered',
        'shipped' => 'admin-status admin-status--shipped',
        'processing' => 'admin-status admin-status--processing',
        'cancelled' => 'admin-status admin-status--cancelled',
        default => 'admin-status admin-status--processing',
    };
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Seller dashboard</h1>
          <div class="admin-page-head__actions">
            <a class="admin-btn admin-btn--primary" href="products.php">Add product</a>
            <a class="admin-btn admin-btn--ghost-light" href="orders.php">View orders</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? '' : ' seller-alert--error' ?>" style="margin-bottom:14px"><?= h($flash) ?></div>
        <?php endif; ?>
        <?php if (!$kycCompleted): ?>
          <div class="seller-alert" style="margin-bottom:14px;border:1px solid #f59e0b;background:#fff7ed;color:#9a3412">
            Bank details aur business address/proof pending hai. Payout aur compliance ke liye complete karein.
            <a href="kyc-details.php" style="font-weight:600;margin-left:8px">Complete now</a>
          </div>
        <?php elseif (!$kycFinalApproved): ?>
          <div class="seller-alert" style="margin-bottom:14px;border:1px solid #f59e0b;background:#fff7ed;color:#9a3412">
            KYC submit ho chuki hai, final admin approval pending hai. Approval ke baad product add kar paayenge.
            <?php if ($kycRejectionReason !== ''): ?>
              Last review reason: <?= h($kycRejectionReason) ?>.
            <?php endif; ?>
            <?php if ($kycUpdatedAt !== ''): ?>
              Updated: <?= h($kycUpdatedAt) ?>.
            <?php endif; ?>
            <a href="kyc-details.php" style="font-weight:600;margin-left:8px">Update KYC</a>
          </div>
        <?php endif; ?>

        <section class="admin-card">
          <div class="admin-card__head">
            <h2 class="admin-card__title">Your categories</h2>
          </div>
          <div class="admin-card__body">
            <p class="seller-help">Sirf in categories ke products add/handle kar sakte hain.</p>
            <div class="seller-pill-row">
              <?php foreach ($allowedCategories as $cat): ?>
                <span class="seller-pill"><?= h($cat) ?></span>
              <?php endforeach; ?>
              <?php if ($allowedCategories === []): ?>
                <span class="seller-pill">No category assigned</span>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <div class="seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Total orders</div>
              <div class="seller-kpi-card__value"><?= (int) $ordersCount ?></div>
              <div class="seller-kpi-card__hint">Orders linked to your products</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Total revenue</div>
              <div class="seller-kpi-card__value">Rs <?= number_format($revenueCount) ?></div>
              <div class="seller-kpi-card__hint">Combined order amount</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Active products</div>
              <div class="seller-kpi-card__value"><?= (int) $productsCount ?></div>
              <div class="seller-kpi-card__hint">Live products in catalogue</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Recent orders in your categories</h2>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Order ref</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentOrders as $order): ?>
                    <?php
                    $name = trim(((string) ($order['first_name'] ?? '')) . ' ' . ((string) ($order['last_name'] ?? '')));
                    if ($name === '') {
                        $name = 'Guest';
                    }
                    ?>
                    <tr>
                      <td><strong><?= h((string) $order['order_ref']) ?></strong></td>
                      <td><?= h($name) ?></td>
                      <td><span class="<?= seller_order_status_class((string) $order['status']) ?>"><?= h((string) $order['status']) ?></span></td>
                      <td>Rs <?= number_format((int) $order['total_amount']) ?></td>
                      <td><?= h((string) $order['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($recentOrders === []): ?>
                    <tr><td colspan="5">No orders available for your categories yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <section class="card seller-danger-card" id="danger-zone" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Danger zone</h2>
          </div>
          <div class="card-body">
            <p class="seller-help">Agar account delete karte ho, seller login access remove ho jayega aur aapke products seller account se unlink ho jayenge.</p>
            <?php if ($pendingDeletionRequest): ?>
              <div class="seller-alert" style="margin-bottom:12px;border:1px solid #f59e0b;background:#fff7ed;color:#9a3412">
                Deletion request pending hai (requested: <?= h((string) ($pendingDeletionRequest['requested_at'] ?? '-')) ?>). Admin action ke baad account process hoga.
              </div>
            <?php elseif ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved'): ?>
              <div class="seller-alert" style="margin-bottom:12px;border:1px solid #86efac;background:#f0fdf4;color:#166534">
                Seller deletion request approved ho chuki hai. Is account ka access jaldi revoke ho jayega.
              </div>
            <?php endif; ?>
            <form method="post" class="seller-danger-form" onsubmit="return confirm('Kya aap pakka seller account delete karna chahte hain?');">
              <input type="hidden" name="action" value="delete_account">
              <label for="confirmText">Type DELETE to confirm</label>
              <input id="confirmText" name="confirm_text" required placeholder="DELETE" <?= ($pendingDeletionRequest || ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved')) ? 'disabled' : '' ?>>
              <button type="submit" class="seller-btn-danger" <?= ($pendingDeletionRequest || ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved')) ? 'disabled' : '' ?>>
                <?php if ($pendingDeletionRequest): ?>
                  Request pending
                <?php elseif ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved'): ?>
                  Request approved
                <?php else: ?>
                  Request account deletion
                <?php endif; ?>
              </button>
            </form>
          </div>
        </section>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
