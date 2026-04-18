<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Orders';
$activeNav = 'orders';

$orders = $pdo->query(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.shipping_address, o.created_at,
            u.first_name, u.last_name, u.email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC"
)->fetchAll();

function admin_order_status_class_orders(string $status): string
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

        <div class="card">
          <div class="card-header">
            <h1 class="admin-page-title card-title">Purchase orders</h1>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Order ref</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Shipping</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $o): ?>
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
                      <td><span class="<?= admin_order_status_class_orders((string) $o['status']) ?>"><?= h((string) $o['status']) ?></span></td>
                      <td>Rs <?= number_format((int) $o['total_amount']) ?></td>
                      <td><span class="admin-badge admin-badge--muted"><?= h((string) $o['payment_method']) ?></span></td>
                      <td style="max-width:220px"><?= h((string) $o['shipping_address']) ?></td>
                      <td><?= h((string) $o['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
