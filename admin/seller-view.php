<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Seller details';
$activeNav = 'sellers';

$sellerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($sellerId <= 0) {
    $_SESSION['seller_admin_flash'] = ['ok' => false, 'text' => 'Invalid seller selected.'];
    header('Location: sellers.php');
    exit;
}

$sellerSt = $pdo->prepare(
    "SELECT id, full_name, email, allowed_categories, is_active, created_at,
            business_name, gst_number, pan_number, aadhaar_number,
            gst_doc_path, pan_doc_path, aadhaar_doc_path,
            bank_name, bank_account_name, bank_account_number, bank_ifsc,
            address_line1, city, state, pin_code, id_proof_type, id_proof_number,
            kyc_completed, kyc_updated_at, kyc_final_approved, kyc_final_reviewed_at, kyc_rejection_reason
     FROM seller_users
     WHERE id = ?
     LIMIT 1"
);
$sellerSt->execute([$sellerId]);
$seller = $sellerSt->fetch();

if (!$seller) {
    $_SESSION['seller_admin_flash'] = ['ok' => false, 'text' => 'Seller not found.'];
    header('Location: sellers.php');
    exit;
}

$deletionStatusSt = $pdo->prepare(
    "SELECT status
     FROM seller_account_deletion_requests
     WHERE seller_id = ? OR email = ?
     ORDER BY id DESC
     LIMIT 1"
);
$deletionStatusSt->execute([$sellerId, (string) ($seller['email'] ?? '')]);
$deletionRequestRow = $deletionStatusSt->fetch();
$deletionStatus = $deletionRequestRow
    ? strtolower((string) ($deletionRequestRow['status'] ?? ''))
    : '';

$statsRow = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_sellers,
        COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS inactive_sellers,
        COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS new_sellers_today
     FROM seller_users"
)->fetch();
$pendingSellerCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_create_requests WHERE status = 'pending'")->fetchColumn();

$productCountSt = $pdo->prepare(
    "SELECT
        COUNT(*) AS products_count,
        COALESCE(SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END), 0) AS active_products_count,
        COALESCE(SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END), 0) AS inactive_products_count
     FROM products
     WHERE seller_id = ?"
);
$productCountSt->execute([$sellerId]);
$productStats = $productCountSt->fetch() ?: [];

$orderCountSt = $pdo->prepare(
    "SELECT
        COUNT(DISTINCT o.id) AS orders_count,
        COALESCE(SUM(x.total_amount), 0) AS revenue_total
     FROM orders o
     INNER JOIN (
        SELECT DISTINCT o2.id, o2.total_amount
        FROM orders o2
        INNER JOIN order_items oi2 ON oi2.order_id = o2.id
        INNER JOIN products p2 ON p2.id = oi2.product_id
        WHERE p2.seller_id = ?
     ) x ON x.id = o.id"
);
$orderCountSt->execute([$sellerId]);
$orderStats = $orderCountSt->fetch() ?: [];

$productsSt = $pdo->prepare(
    "SELECT id, name, category, price, stock_qty, active, '' AS created_at
     FROM products
     WHERE seller_id = ?
     ORDER BY id DESC"
);
$productsSt->execute([$sellerId]);
$products = $productsSt->fetchAll();

$ordersSt = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.created_at,
            u.first_name, u.last_name, u.email,
            COUNT(oi.id) AS item_lines
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE p.seller_id = ?
     GROUP BY o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.created_at,
              u.first_name, u.last_name, u.email
     ORDER BY o.id DESC
     LIMIT 20"
);
$ordersSt->execute([$sellerId]);
$orders = $ordersSt->fetchAll();

$requestsSt = $pdo->prepare(
    "SELECT id, status, requested_categories, phone, note, created_at, reviewed_at, rejection_reason
     FROM seller_create_requests
     WHERE seller_id = ? OR email = ?
     ORDER BY id DESC
     LIMIT 10"
);
$requestsSt->execute([$sellerId, (string) $seller['email']]);
$requests = $requestsSt->fetchAll();

function admin_seller_order_status_class(string $status): string
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

<div class="admin-page-head" style="margin-bottom:14px">
  <h1>Seller details</h1>
  <div class="admin-page-head__actions">
    <a class="admin-btn" href="sellers.php" style="border:1px solid var(--admin-border)">Back to Seller list</a>
  </div>
</div>



<div class="admin-grid admin-grid--stats" style="margin-bottom:16px">
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Seller name</div>
      <div class="admin-stat__value" style="font-size:1rem"><?= h((string) $seller['full_name']) ?></div>
      <div class="admin-stat__delta admin-stat__delta--muted"><?= h((string) $seller['email']) ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Products</div>
      <div class="admin-stat__value"><?= (int) ($productStats['products_count'] ?? 0) ?></div>
      <div class="admin-stat__delta admin-stat__delta--muted">Active: <?= (int) ($productStats['active_products_count'] ?? 0) ?> · Inactive: <?= (int) ($productStats['inactive_products_count'] ?? 0) ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Orders</div>
      <div class="admin-stat__value"><?= (int) ($orderStats['orders_count'] ?? 0) ?></div>
      <div class="admin-stat__delta admin-stat__delta--muted">Revenue: Rs <?= number_format((int) ($orderStats['revenue_total'] ?? 0)) ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Seller status</div>
      <div class="admin-stat__value" style="font-size:1rem">
        <?php if ($deletionStatus === 'approved'): ?>
          <span class="admin-status admin-status--cancelled">Deleted</span>
        <?php elseif ($deletionStatus === 'pending'): ?>
          <span class="admin-status admin-status--processing">Deletion pending</span>
        <?php elseif ((int) $seller['is_active'] === 1): ?>
          <span class="admin-status admin-status--delivered">Active</span>
        <?php else: ?>
          <span class="admin-status admin-status--cancelled">Inactive</span>
        <?php endif; ?>
      </div>
      <div class="admin-stat__delta admin-stat__delta--muted">Created: <?= h((string) $seller['created_at']) ?></div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <h2 class="card-title">KYC & bank details</h2>
  </div>
  <div class="card-body">
    <div class="admin-grid admin-grid--stats" style="margin-bottom:12px">
      <div class="admin-card admin-stat">
        <div>
          <div class="admin-stat__label">KYC submission</div>
          <div class="admin-stat__value" style="font-size:1rem">
            <?php if ((int) ($seller['kyc_completed'] ?? 0) === 1): ?>
              <span class="admin-status admin-status--delivered">Submitted</span>
            <?php else: ?>
              <span class="admin-status admin-status--processing">Pending</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="admin-card admin-stat">
        <div>
          <div class="admin-stat__label">Final approval</div>
          <div class="admin-stat__value" style="font-size:1rem">
            <?php if ((int) ($seller['kyc_final_approved'] ?? 0) === 1): ?>
              <span class="admin-status admin-status--delivered">Approved</span>
            <?php else: ?>
              <span class="admin-status admin-status--processing">Pending</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="admin-card admin-stat">
        <div>
          <div class="admin-stat__label">Last submitted</div>
          <div class="admin-stat__value" style="font-size:0.95rem"><?= h((string) (($seller['kyc_updated_at'] ?? '') !== '' ? $seller['kyc_updated_at'] : '-')) ?></div>
        </div>
      </div>
      <div class="admin-card admin-stat">
        <div>
          <div class="admin-stat__label">Last final review</div>
          <div class="admin-stat__value" style="font-size:0.95rem"><?= h((string) (($seller['kyc_final_reviewed_at'] ?? '') !== '' ? $seller['kyc_final_reviewed_at'] : '-')) ?></div>
        </div>
      </div>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <tbody>
          <tr>
            <th style="width:220px">Business Details</th>
            <td>
              Name: <?= h((string) ($seller['business_name'] ?: '-')) ?><br>
              GST: <?= h((string) ($seller['gst_number'] ?: '-')) ?><br>
              PAN: <?= h((string) ($seller['pan_number'] ?: '-')) ?><br>
              Aadhaar: <?= h((string) ($seller['aadhaar_number'] ?: '-')) ?>
            </td>
          </tr>
          <tr>
            <th>Uploaded Documents</th>
            <td style="display:flex;gap:8px;flex-wrap:wrap">
              <?php if ((string) ($seller['gst_doc_path'] ?? '') !== ''): ?>
                <a href="../<?= h((string) $seller['gst_doc_path']) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn--primary" style="padding:6px 10px">Open GST Doc</a>
              <?php else: ?>
                <span class="seller-help">GST doc missing</span>
              <?php endif; ?>
              <?php if ((string) ($seller['pan_doc_path'] ?? '') !== ''): ?>
                <a href="../<?= h((string) $seller['pan_doc_path']) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn--primary" style="padding:6px 10px">Open PAN Doc</a>
              <?php else: ?>
                <span class="seller-help">PAN doc missing</span>
              <?php endif; ?>
              <?php if ((string) ($seller['aadhaar_doc_path'] ?? '') !== ''): ?>
                <a href="../<?= h((string) $seller['aadhaar_doc_path']) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn--primary" style="padding:6px 10px">Open Aadhaar Doc</a>
              <?php else: ?>
                <span class="seller-help">Aadhaar doc missing</span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <th>Bank Details</th>
            <td>
              Bank: <?= h((string) ($seller['bank_name'] ?: '-')) ?><br>
              Holder: <?= h((string) ($seller['bank_account_name'] ?: '-')) ?><br>
              Account No: <?= h((string) ($seller['bank_account_number'] ?: '-')) ?><br>
              IFSC: <?= h((string) ($seller['bank_ifsc'] ?: '-')) ?>
            </td>
          </tr>
          <tr>
            <th>Address & Proof</th>
            <td>
              Address: <?= h((string) ($seller['address_line1'] ?: '-')) ?>, <?= h((string) ($seller['city'] ?: '-')) ?>, <?= h((string) ($seller['state'] ?: '-')) ?> - <?= h((string) ($seller['pin_code'] ?: '-')) ?><br>
              ID Type: <?= h(strtoupper(str_replace('_', ' ', (string) ($seller['id_proof_type'] ?: '-')))) ?><br>
              ID Number: <?= h((string) ($seller['id_proof_number'] ?: '-')) ?>
              <?php if ((string) ($seller['kyc_rejection_reason'] ?? '') !== ''): ?>
                <br>Final review reason: <?= h((string) $seller['kyc_rejection_reason']) ?>
              <?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <h2 class="card-title">Seller products</h2>
  </div>
  <div class="card-body card-body--flush">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $product): ?>
            <tr>
              <td>
                <strong><?= h((string) $product['name']) ?></strong><br>
                <small>#<?= (int) $product['id'] ?></small>
              </td>
              <td><?= h((string) $product['category']) ?></td>
              <td>Rs <?= number_format((int) $product['price']) ?></td>
              <td><?= (int) ($product['stock_qty'] ?? 0) ?></td>
              <td>
                <?php if ((int) $product['active'] === 1): ?>
                  <span class="admin-status admin-status--delivered">Active</span>
                <?php else: ?>
                  <span class="admin-status admin-status--cancelled">Inactive</span>
                <?php endif; ?>
              </td>
              <td><?= h((string) (($product['created_at'] ?? '') !== '' ? $product['created_at'] : '-')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($products === []): ?>
            <tr><td colspan="6">No products found for this seller.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <h2 class="card-title">Seller orders</h2>
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
            <th>Items</th>
            <th>Payment</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <?php
            $customer = trim(((string) ($order['first_name'] ?? '')) . ' ' . ((string) ($order['last_name'] ?? '')));
            if ($customer === '') {
                $customer = 'Guest';
            }
            ?>
            <tr>
              <td><strong><?= h((string) $order['order_ref']) ?></strong></td>
              <td><?= h($customer) ?></td>
              <td><?= h((string) ($order['email'] ?? '-')) ?></td>
              <td><span class="<?= admin_seller_order_status_class((string) $order['status']) ?>"><?= h((string) $order['status']) ?></span></td>
              <td>Rs <?= number_format((int) $order['total_amount']) ?></td>
              <td><?= (int) $order['item_lines'] ?></td>
              <td><span class="admin-badge admin-badge--muted"><?= h((string) $order['payment_method']) ?></span></td>
              <td><?= h((string) $order['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($orders === []): ?>
            <tr><td colspan="8">No orders found for this seller.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Seller request history</h2>
  </div>
  <div class="card-body card-body--flush">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Request</th>
            <th>Status</th>
            <th>Categories</th>
            <th>Phone</th>
            <th>Note / Reason</th>
            <th>Reviewed</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $req): ?>
            <?php $reqStatus = (string) ($req['status'] ?? 'pending'); ?>
            <tr>
              <td>
                <strong>#<?= (int) $req['id'] ?></strong><br>
                <small><?= h((string) $req['created_at']) ?></small>
              </td>
              <td>
                <span class="admin-status admin-status--<?= h($reqStatus === 'approved' ? 'delivered' : ($reqStatus === 'rejected' ? 'cancelled' : 'pending')) ?>">
                  <?= h(ucfirst($reqStatus)) ?>
                </span>
              </td>
              <td><?= h((string) ($req['requested_categories'] ?? '-')) ?></td>
              <td><?= h((string) ($req['phone'] ?? '-')) ?></td>
              <td>
                <?= h((string) ($req['note'] ?? '-')) ?>
                <?php if ($reqStatus === 'rejected' && (string) ($req['rejection_reason'] ?? '') !== ''): ?>
                  <br><small>Reason: <?= h((string) $req['rejection_reason']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= h((string) ($req['reviewed_at'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ($requests === []): ?>
            <tr><td colspan="6">No request history found for this seller.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
