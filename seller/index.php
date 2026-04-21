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

$processingCountSt = $pdo->prepare(
    "SELECT COUNT(DISTINCT o.id)
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     WHERE p.seller_id = ? AND o.status = 'processing'"
);
$processingCountSt->execute([(int) $seller['id']]);
$processingOrdersCount = (int) $processingCountSt->fetchColumn();

function seller_dashboard_status_chip_mod(string $status): string
{
    return match (strtolower(trim($status))) {
        'delivered' => 'seller-status-chip--delivered',
        'out', 'shipped' => 'seller-status-chip--shipped',
        'confirmed' => 'seller-status-chip--approved',
        'processing' => 'seller-status-chip--pending',
        'cancelled' => 'seller-status-chip--rejected',
        default => '',
    };
}

function seller_dashboard_status_label(string $status): string
{
    $s = strtolower(trim($status));

    return match ($s) {
        'out' => 'Out for delivery',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'cancelled' => 'Cancelled',
        default => $s !== '' ? ucfirst($s) : '—',
    };
}

function seller_dashboard_format_dt(?string $raw): string
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

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-dashboard-head">
          <div>
            <h1>Dashboard</h1>
            <p class="seller-dashboard-subtitle">Hi <?= h(trim((string) ($seller['full_name'] ?? 'Seller'))) ?> — manage products, orders, and payouts from here. Numbers below include every order that contains your SKUs.</p>
          </div>
          <div class="admin-page-head__actions seller-dashboard-head-actions">
            <a class="admin-btn admin-btn--primary" href="products.php">Add product</a>
            <a class="admin-btn admin-btn--ghost-light" href="orders.php">Orders</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?> seller-dashboard-flash"><?= h($flash) ?></div>
        <?php endif; ?>
        <?php if (!$kycCompleted): ?>
          <div class="seller-alert seller-alert--warn seller-dashboard-banner">
            <span class="seller-dashboard-banner__lead">KYC incomplete</span>
            Bank details and business proof are required for payouts and compliance.
            <a class="seller-dashboard-banner__link" href="kyc-details.php">Complete KYC</a>
          </div>
        <?php elseif (!$kycFinalApproved): ?>
          <div class="seller-alert seller-alert--warn seller-dashboard-banner">
            <span class="seller-dashboard-banner__lead">Awaiting final approval</span>
            Your KYC is submitted; admin review is pending before you can add products.
            <?php if ($kycRejectionReason !== ''): ?>
              <span class="seller-dashboard-banner__meta">Last note: <?= h($kycRejectionReason) ?>.</span>
            <?php endif; ?>
            <?php if ($kycUpdatedAt !== ''): ?>
              <span class="seller-dashboard-banner__meta">Updated: <?= h($kycUpdatedAt) ?>.</span>
            <?php endif; ?>
            <a class="seller-dashboard-banner__link" href="kyc-details.php">View / update KYC</a>
          </div>
        <?php endif; ?>

        <div class="seller-dashboard-layout">
          <section class="card seller-dashboard-card seller-dashboard-card--categories">
            <div class="card-header">
              <div>
                <h2 class="card-title">Your categories</h2>
                <p class="card-subtitle seller-dashboard-card-sub">You can only list and manage products in these categories.</p>
              </div>
            </div>
            <div class="card-body">
              <div class="seller-pill-row seller-dashboard-pills">
                <?php foreach ($allowedCategories as $cat): ?>
                  <span class="seller-pill"><?= h(ucfirst($cat)) ?></span>
                <?php endforeach; ?>
                <?php if ($allowedCategories === []): ?>
                  <span class="seller-pill seller-pill--muted">No category assigned</span>
                <?php endif; ?>
              </div>
            </div>
          </section>

          <aside class="seller-dashboard-welcome" aria-label="Store snapshot">
            <div class="seller-dashboard-welcome__inner">
              <div class="seller-dashboard-welcome__icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              </div>
              <div>
                <h2 class="seller-dashboard-welcome__title">Store snapshot</h2>
                <ul class="seller-dashboard-welcome__stats">
                  <li><strong><?= (int) $productsCount ?></strong> live products</li>
                  <li><strong><?= (int) $ordersCount ?></strong> orders (all time)</li>
                  <li><strong><?= (int) $processingOrdersCount ?></strong> awaiting confirm</li>
                </ul>
                <a class="seller-dashboard-welcome__link" href="orders.php">Open order list →</a>
              </div>
            </div>
          </aside>
        </div>

        <div class="seller-kpi seller-dashboard-kpi">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Total orders</div>
              <div class="seller-kpi-card__value"><?= (int) $ordersCount ?></div>
              <div class="seller-kpi-card__hint">Orders with your line items</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">To confirm</div>
              <div class="seller-kpi-card__value"><?= (int) $processingOrdersCount ?></div>
              <div class="seller-kpi-card__hint">Processing — confirm in Orders</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Order value (sum)</div>
              <div class="seller-kpi-card__value">₹<?= number_format($revenueCount, 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Full order totals (not net payout)</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Live products</div>
              <div class="seller-kpi-card__value"><?= (int) $productsCount ?></div>
              <div class="seller-kpi-card__hint">Approved + active in catalogue</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
            </div>
          </div>
        </div>

        <nav class="seller-dashboard-quick" aria-label="Quick links">
          <a class="seller-dashboard-quick__item" href="products.php">
            <span class="seller-dashboard-quick__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg></span>
            <span class="seller-dashboard-quick__label">Products</span>
          </a>
          <a class="seller-dashboard-quick__item" href="inventory.php">
            <span class="seller-dashboard-quick__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/></svg></span>
            <span class="seller-dashboard-quick__label">Inventory</span>
          </a>
          <a class="seller-dashboard-quick__item" href="coupons.php">
            <span class="seller-dashboard-quick__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
            <span class="seller-dashboard-quick__label">Coupons</span>
          </a>
          <a class="seller-dashboard-quick__item" href="shipping-settings.php">
            <span class="seller-dashboard-quick__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3z"/><path d="M16 10h3l2 2v5h-5z"/></svg></span>
            <span class="seller-dashboard-quick__label">Shipping</span>
          </a>
          <a class="seller-dashboard-quick__item" href="earnings.php">
            <span class="seller-dashboard-quick__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
            <span class="seller-dashboard-quick__label">Earnings</span>
          </a>
          <a class="seller-dashboard-quick__item" href="settings.php">
            <span class="seller-dashboard-quick__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
            <span class="seller-dashboard-quick__label">Settings</span>
          </a>
          <a class="seller-dashboard-quick__item" href="profile.php">
            <span class="seller-dashboard-quick__icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
            <span class="seller-dashboard-quick__label">Profile</span>
          </a>
        </nav>

        <div class="card seller-dashboard-recent-card">
          <div class="card-header seller-dashboard-recent-head">
            <div>
              <h2 class="card-title">Recent orders</h2>
              <p class="card-subtitle seller-dashboard-card-sub">Latest activity across your catalogue — open a row for full detail and status updates.</p>
            </div>
            <a class="admin-btn admin-btn--ghost-light seller-dashboard-recent-all" href="orders.php">View all</a>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap seller-dashboard-table-wrap">
              <table class="admin-table seller-dashboard-table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th class="seller-dashboard-th-actions"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentOrders as $order): ?>
                    <?php
                    $name = trim(((string) ($order['first_name'] ?? '')) . ' ' . ((string) ($order['last_name'] ?? '')));
                    if ($name === '') {
                        $name = 'Guest';
                    }
                    $oid = (int) ($order['id'] ?? 0);
                    $stRaw = (string) ($order['status'] ?? '');
                    $stMod = seller_dashboard_status_chip_mod($stRaw);
                    $stLabel = seller_dashboard_status_label($stRaw);
                    ?>
                    <tr class="seller-dashboard-order-row">
                      <td>
                        <span class="seller-orders-ref"><?= h((string) $order['order_ref']) ?></span>
                        <span class="seller-orders-id-tag">#<?= $oid ?></span>
                      </td>
                      <td>
                        <span class="seller-orders-customer-name"><?= h($name) ?></span>
                      </td>
                      <td>
                        <span class="seller-status-chip <?= h($stMod !== '' ? $stMod : '') ?>"><?= h($stLabel) ?></span>
                      </td>
                      <td class="seller-orders-td-total"><span class="seller-orders-amount">₹<?= number_format((int) $order['total_amount'], 0, '.', ',') ?></span></td>
                      <td class="seller-dashboard-td-date"><?= h(seller_dashboard_format_dt((string) ($order['created_at'] ?? ''))) ?></td>
                      <td class="seller-dashboard-td-link">
                        <a class="seller-edit-btn" href="order-details.php?id=<?= $oid ?>">Details</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($recentOrders === []): ?>
                    <tr class="seller-dashboard-empty-row">
                      <td colspan="6">
                        <div class="seller-dashboard-empty-orders">
                          <p class="seller-dashboard-empty-orders__title">No orders yet</p>
                          <p class="seller-dashboard-empty-orders__text">When buyers purchase your products, they will show up here and in <a href="orders.php">Orders</a>.</p>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <section class="card seller-danger-card seller-dashboard-danger" id="danger-zone">
          <div class="card-header">
            <div>
              <h2 class="card-title">Danger zone</h2>
              <p class="card-subtitle seller-dashboard-card-sub">Account deletion removes seller access and unlinks your products from this seller profile after admin processing.</p>
            </div>
          </div>
          <div class="card-body">
            <?php if ($pendingDeletionRequest): ?>
              <div class="seller-alert seller-alert--warn seller-dashboard-danger-alert">
                Deletion request pending (requested <?= h((string) ($pendingDeletionRequest['requested_at'] ?? '—')) ?>). Admin will process it; you cannot submit another request until resolved.
              </div>
            <?php elseif ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved'): ?>
              <div class="seller-alert seller-alert--success seller-dashboard-danger-alert">
                Deletion was approved. Access to this seller account will be revoked shortly.
              </div>
            <?php endif; ?>
            <form method="post" class="seller-danger-form seller-dashboard-danger-form" onsubmit="return confirm('Kya aap pakka seller account delete karna chahte hain?');">
              <input type="hidden" name="action" value="delete_account">
              <div class="seller-dashboard-danger-fields">
                <div>
                  <label for="confirmText">Type <strong>DELETE</strong> to confirm</label>
                  <input id="confirmText" name="confirm_text" class="seller-stock-input" required placeholder="DELETE" autocomplete="off" <?= ($pendingDeletionRequest || ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved')) ? 'disabled' : '' ?>>
                </div>
                <button type="submit" class="seller-btn-danger seller-dashboard-danger-submit" <?= ($pendingDeletionRequest || ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved')) ? 'disabled' : '' ?>>
                  <?php if ($pendingDeletionRequest): ?>
                    Request pending
                  <?php elseif ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved'): ?>
                    Request approved
                  <?php else: ?>
                    Request account deletion
                  <?php endif; ?>
                </button>
              </div>
            </form>
          </div>
        </section>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
