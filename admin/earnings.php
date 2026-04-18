<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/site_settings.php';
require_once __DIR__ . '/../includes/cart_session.php';

$pdo = db();
$admin = admin_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'backfill_platform_fees') {
    $n = orders_backfill_platform_fee_on_orders($pdo);
    $_SESSION['admin_earnings_flash'] = [
        'ok' => true,
        'text' => $n > 0
            ? 'Missing platform fees filled for ' . $n . ' order(s). Totals were checked against item lines + current fee (Rs ' . number_format(site_platform_fee_rupees($pdo)) . ') + shipping rules.'
            : 'No orders matched. Fees stay at 0 when the order total does not match current fee + shipping math, or the fee setting is 0.',
    ];
    header('Location: earnings.php');
    exit;
}

$flash = null;
if (isset($_SESSION['admin_earnings_flash']) && is_array($_SESSION['admin_earnings_flash'])) {
    $flash = $_SESSION['admin_earnings_flash'];
    unset($_SESSION['admin_earnings_flash']);
}

$pageTitle = 'Platform earnings';
$activeNav = 'earnings';

$feePerOrderRupees = site_platform_fee_rupees($pdo);

$totalFeesStmt = $pdo->query('SELECT COALESCE(SUM(platform_fee_rupees), 0) FROM orders');
$totalPlatformFees = (int) $totalFeesStmt->fetchColumn();

$ordersWithFeeStmt = $pdo->query('SELECT COUNT(*) FROM orders WHERE platform_fee_rupees > 0');
$ordersWithFeeCount = (int) $ordersWithFeeStmt->fetchColumn();

$ordersMissingFeeStmt = $pdo->query('SELECT COUNT(*) FROM orders WHERE platform_fee_rupees = 0');
$ordersMissingFeeCount = (int) $ordersMissingFeeStmt->fetchColumn();

$monthFeesStmt = $pdo->query(
    "SELECT COALESCE(SUM(platform_fee_rupees), 0) FROM orders
     WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
);
$feesThisMonth = (int) $monthFeesStmt->fetchColumn();

$rows = $pdo->query(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.platform_fee_rupees, o.payment_method, o.created_at,
            u.first_name, u.last_name, u.email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.platform_fee_rupees > 0
     ORDER BY o.id DESC
     LIMIT 500"
)->fetchAll();

function admin_order_status_class_earnings(string $status): string
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

        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?>" role="status" style="margin-bottom:14px">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <h1>Platform fee earnings</h1>
          <div class="admin-page-head__actions">
            <span class="admin-date-pill" title="Fee charged per checkout (Settings)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              Current fee: Rs <?= number_format($feePerOrderRupees) ?> / order
            </span>
            <a class="admin-ghost-btn" href="orders.php" title="All orders">Orders</a>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats">
          <div class="admin-card admin-stat">
            <div>
              <div class="admin-stat__label">Total platform fees collected</div>
              <div class="admin-stat__value">Rs <?= number_format($totalPlatformFees) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">All time (stored on each order)</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--purple">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat">
            <div>
              <div class="admin-stat__label">This month</div>
              <div class="admin-stat__value">Rs <?= number_format($feesThisMonth) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Platform fees by order date</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat">
            <div>
              <div class="admin-stat__label">Orders with fee recorded</div>
              <div class="admin-stat__value"><?= number_format($ordersWithFeeCount) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Checkout rows where fee &gt; 0</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--pink">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat">
            <div>
              <div class="admin-stat__label">Stored fee still Rs 0</div>
              <div class="admin-stat__value"><?= number_format($ordersMissingFeeCount) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Often old/seed orders; customer total may still include fee</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--red">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
          </div>
        </div>

        <?php if ($feePerOrderRupees > 0 && $ordersMissingFeeCount > 0): ?>
        <div class="card" style="margin-top:1rem">
          <div class="card-header">
            <h2 class="card-title">Fix missing fee on past orders</h2>
          </div>
          <div class="card-body">
            <p class="admin-stat__delta admin-stat__delta--muted" style="margin:0 0 12px;font-weight:400">
              New checkouts already save the fee on each order. For older rows still at Rs 0, you can run a one-time match: we set <strong>platform_fee_rupees</strong> to the <strong>current</strong> setting (Rs <?= number_format($feePerOrderRupees) ?>)
              only when <strong>order total = items subtotal + that fee + delivery</strong> (standard / express / same-day), using <strong>today’s</strong> shipping rules. If the fee was a different amount when the order was placed, or seller shipping changed, some orders may not match — adjust <code>site_settings.platform_fee_rupees</code> temporarily if needed, or update rows manually.
            </p>
            <form method="post" onsubmit="return confirm('Backfill platform fee on matching orders using current Rs <?= number_format($feePerOrderRupees) ?> fee and current shipping rules?');">
              <input type="hidden" name="action" value="backfill_platform_fees">
              <button type="submit" class="admin-btn admin-btn--primary">Match totals and fill missing fees</button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <div class="card" style="margin-top:1.25rem">
          <div class="card-header">
            <h2 class="card-title">Fee by order</h2>
            <p class="admin-stat__delta admin-stat__delta--muted" style="margin:0.35rem 0 0;font-weight:400">
              Listed orders have a non-zero stored fee. If your Rs <?= number_format($feePerOrderRupees) ?> checkouts are missing here, use “Match totals” above or confirm <code>orders.platform_fee_rupees</code> exists and new orders run through <code>actions/place-order.php</code>.
            </p>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($rows === []): ?>
              <div class="card-body" style="padding:1.5rem">
                <p class="admin-stat__delta admin-stat__delta--muted" style="margin:0">No orders with a recorded platform fee yet.</p>
              </div>
            <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Order ref</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Order total</th>
                    <th>Platform fee</th>
                    <th>Payment</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $o): ?>
                    <?php
                    $cust = trim(((string) ($o['first_name'] ?? '')) . ' ' . ((string) ($o['last_name'] ?? '')));
                    if ($cust === '') {
                        $cust = 'Guest';
                    }
                    ?>
                    <tr>
                      <td><strong><?= h((string) $o['order_ref']) ?></strong></td>
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h(strtoupper(substr($cust, 0, 2))) ?></div>
                          <span><?= h($cust) ?></span>
                        </div>
                      </td>
                      <td><?= h((string) ($o['email'] ?? '—')) ?></td>
                      <td><span class="<?= admin_order_status_class_earnings((string) $o['status']) ?>"><?= h((string) $o['status']) ?></span></td>
                      <td>Rs <?= number_format((int) $o['total_amount']) ?></td>
                      <td><strong>Rs <?= number_format((int) $o['platform_fee_rupees']) ?></strong></td>
                      <td><span class="admin-badge admin-badge--muted"><?= h((string) $o['payment_method']) ?></span></td>
                      <td><?= h((string) $o['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
