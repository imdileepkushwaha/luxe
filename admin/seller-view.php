<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_pagination.php';

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

$rawPp = (int) ($_GET['products_per_page'] ?? 25);
$productsPerPage = ($rawPp >= 5 && $rawPp <= 100) ? $rawPp : 25;
$productsPage = max(1, (int) ($_GET['products_page'] ?? 1));

$rawOp = (int) ($_GET['orders_per_page'] ?? 25);
$ordersPerPage = ($rawOp >= 5 && $rawOp <= 100) ? $rawOp : 25;
$ordersPage = max(1, (int) ($_GET['orders_page'] ?? 1));

$productParams = [$sellerId];
$productCountFilterSt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE seller_id = ?');
$productCountFilterSt->execute($productParams);
$totalProducts = (int) $productCountFilterSt->fetchColumn();

$pMetaP = admin_pagination_resolve($totalProducts, $productsPage, $productsPerPage);
$productsPage = $pMetaP['page'];
$productsPerPage = $pMetaP['perPage'];
$offsetP = $pMetaP['offset'];
$totalPagesProducts = $pMetaP['totalPages'];

$limitP = (int) $productsPerPage;
$offsetPInt = (int) $offsetP;
$productsSt = $pdo->prepare(
    "SELECT id, name, category, price, stock_qty, active, created_at, sku, slug, brand
     FROM products
     WHERE seller_id = ?
     ORDER BY id DESC
     LIMIT {$limitP} OFFSET {$offsetPInt}"
);
$productsSt->execute($productParams);
$products = $productsSt->fetchAll();

$orderParams = [$sellerId];
$orderCountFilterSt = $pdo->prepare(
    "SELECT COUNT(DISTINCT o.id) AS c
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE p.seller_id = ?"
);
$orderCountFilterSt->execute($orderParams);
$totalOrders = (int) $orderCountFilterSt->fetchColumn();

$pMetaO = admin_pagination_resolve($totalOrders, $ordersPage, $ordersPerPage);
$ordersPage = $pMetaO['page'];
$ordersPerPage = $pMetaO['perPage'];
$offsetO = $pMetaO['offset'];
$totalPagesOrders = $pMetaO['totalPages'];

$limitO = (int) $ordersPerPage;
$offsetOInt = (int) $offsetO;
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
     LIMIT {$limitO} OFFSET {$offsetOInt}"
);
$ordersSt->execute($orderParams);
$orders = $ordersSt->fetchAll();

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

function admin_seller_view_product_haystack(array $product): string
{
    $parts = [
        (string) ($product['name'] ?? ''),
        (string) ($product['id'] ?? ''),
        (string) ($product['category'] ?? ''),
        (string) ($product['sku'] ?? ''),
        (string) ($product['slug'] ?? ''),
        (string) ($product['brand'] ?? ''),
        (string) ($product['price'] ?? ''),
        (string) ($product['stock_qty'] ?? ''),
        (int) ($product['active'] ?? 0) === 1 ? 'active' : 'inactive',
        (string) ($product['created_at'] ?? ''),
    ];

    return strtolower(implode(' ', $parts));
}

function admin_seller_view_order_haystack(array $order, string $customer): string
{
    $parts = [
        (string) ($order['order_ref'] ?? ''),
        (string) ($order['id'] ?? ''),
        (string) ($order['status'] ?? ''),
        (string) ($order['payment_method'] ?? ''),
        (string) ($order['total_amount'] ?? ''),
        (string) ($order['email'] ?? ''),
        $customer,
        (string) ($order['first_name'] ?? ''),
        (string) ($order['last_name'] ?? ''),
        (string) ($order['item_lines'] ?? ''),
        (string) ($order['created_at'] ?? ''),
    ];

    return strtolower(implode(' ', $parts));
}

/**
 * @param mixed $raw
 */
function admin_seller_view_fmt_created($raw): string
{
    if ($raw === null || $raw === '') {
        return '—';
    }
    $s = (string) $raw;
    try {
        return (new DateTimeImmutable($s))->format('M j, Y · g:i A');
    } catch (Throwable) {
        return $s;
    }
}

require __DIR__ . '/partials/shell-top.php';
?>

<div class="admin-seller-view-page">
        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Marketplace</span>
            <h1>Seller details</h1>
            <p class="admin-page-head__lede">Profile, catalog, orders, and account status for this seller.</p>
          </div>
          <div class="admin-page-head__actions">
            <a class="admin-btn admin-btn--outline" href="sellers.php">Back to Seller list</a>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats admin-grid--stats--flow admin-seller-view-kpi-grid" aria-label="Seller summary">
          <div class="admin-card admin-stat admin-stat--stripe-blue admin-seller-view-kpi">
            <div>
              <div class="admin-stat__label admin-seller-view-kpi__label">Seller profile</div>
              <div class="admin-stat__value admin-seller-view-kpi__lead"><?= h((string) $seller['full_name']) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted"><?= h((string) $seller['email']) ?></div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-teal admin-seller-view-kpi">
            <div>
              <div class="admin-stat__label admin-seller-view-kpi__label">Products</div>
              <div class="admin-stat__value"><?= (int) ($productStats['products_count'] ?? 0) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Active: <?= (int) ($productStats['active_products_count'] ?? 0) ?> · Inactive: <?= (int) ($productStats['inactive_products_count'] ?? 0) ?></div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--green" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-amber admin-seller-view-kpi">
            <div>
              <div class="admin-stat__label admin-seller-view-kpi__label">Orders</div>
              <div class="admin-stat__value"><?= (int) ($orderStats['orders_count'] ?? 0) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Revenue: Rs <?= number_format((int) ($orderStats['revenue_total'] ?? 0)) ?></div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--pink" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-amber admin-seller-view-kpi">
            <div>
              <div class="admin-stat__label admin-seller-view-kpi__label">Account status</div>
              <div class="admin-stat__value admin-seller-view-kpi__status">
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
              <div class="admin-stat__delta admin-stat__delta--muted">Created: <?= h(admin_seller_view_fmt_created($seller['created_at'] ?? null)) ?></div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--pink" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
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
  <div class="card-header admin-seller-view-table-header">
    <div class="admin-seller-view-table-head">
      <div class="admin-seller-view-table-head-text">
        <h2 class="card-title">Seller products</h2>
        <p class="card-subtitle admin-seller-view-table-sub">
          <?= (int) ($productStats['products_count'] ?? 0) ?> total in catalog · Type to filter this page only. Pagination is independent of orders below.
        </p>
      </div>
      <?php if ($totalProducts > 0): ?>
        <label class="admin-users-search-wrap admin-seller-view-search-wrap" for="sellerViewProductsSearch">
          <span class="admin-users-search-icon admin-seller-view-search-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          </span>
          <input
            type="search"
            id="sellerViewProductsSearch"
            class="admin-users-search-input admin-seller-view-search-input"
            placeholder="Search products…"
            autocomplete="off"
            aria-label="Search seller products on this page"
          >
        </label>
      <?php endif; ?>
    </div>
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
            <?php $pHay = admin_seller_view_product_haystack($product); ?>
            <tr class="admin-seller-view-products-row" data-seller-view-products-search="<?= h($pHay) ?>">
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
          <?php if ($totalProducts === 0): ?>
            <tr>
              <td colspan="6">No products found for this seller.</td>
            </tr>
          <?php elseif ($products !== []): ?>
            <tr id="adminSellerViewProductsNoMatch" class="admin-seller-view-no-match-row">
              <td colspan="6">
                <div class="admin-seller-view-no-match">
                  <strong class="admin-seller-view-no-match__title">No matches</strong>
                  <p class="admin-seller-view-no-match__text">Try another keyword — this page only.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    $paginationScript = 'seller-view.php';
    $paginationTotal = $totalProducts;
    $paginationPage = $productsPage;
    $paginationPerPage = $productsPerPage;
    $paginationTotalPages = $totalPagesProducts;
    $paginationPageKey = 'products_page';
    $paginationPerPageKey = 'products_per_page';
    require __DIR__ . '/partials/table-pagination.php';
    ?>
  </div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-header admin-seller-view-table-header">
    <div class="admin-seller-view-table-head">
      <div class="admin-seller-view-table-head-text">
        <h2 class="card-title">Seller orders</h2>
        <p class="card-subtitle admin-seller-view-table-sub">
          <?= (int) ($orderStats['orders_count'] ?? 0) ?> total orders · Type to filter this page only. Pagination is independent of products above.
        </p>
      </div>
      <?php if ($totalOrders > 0): ?>
        <label class="admin-users-search-wrap admin-seller-view-search-wrap" for="sellerViewOrdersSearch">
          <span class="admin-users-search-icon admin-seller-view-search-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          </span>
          <input
            type="search"
            id="sellerViewOrdersSearch"
            class="admin-users-search-input admin-seller-view-search-input"
            placeholder="Search orders…"
            autocomplete="off"
            aria-label="Search seller orders on this page"
          >
        </label>
      <?php endif; ?>
    </div>
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
            $oHay = admin_seller_view_order_haystack($order, $customer);
            ?>
            <tr class="admin-seller-view-orders-row" data-seller-view-orders-search="<?= h($oHay) ?>">
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
          <?php if ($totalOrders === 0): ?>
            <tr>
              <td colspan="8">No orders found for this seller.</td>
            </tr>
          <?php elseif ($orders !== []): ?>
            <tr id="adminSellerViewOrdersNoMatch" class="admin-seller-view-no-match-row">
              <td colspan="8">
                <div class="admin-seller-view-no-match">
                  <strong class="admin-seller-view-no-match__title">No matches</strong>
                  <p class="admin-seller-view-no-match__text">Try another keyword — this page only.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
    $paginationScript = 'seller-view.php';
    $paginationTotal = $totalOrders;
    $paginationPage = $ordersPage;
    $paginationPerPage = $ordersPerPage;
    $paginationTotalPages = $totalPagesOrders;
    $paginationPageKey = 'orders_page';
    $paginationPerPageKey = 'orders_per_page';
    require __DIR__ . '/partials/table-pagination.php';
    ?>
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

<?php if ($totalProducts > 0): ?>
<script>
(function () {
  var searchInput = document.getElementById('sellerViewProductsSearch');
  if (!searchInput) return;
  var rowEls = document.querySelectorAll('tr.admin-seller-view-products-row');
  var noMatchRow = document.getElementById('adminSellerViewProductsNoMatch');
  function applyProductsSearch() {
    var q = (searchInput.value || '').trim().toLowerCase();
    var words = q.split(/\s+/).filter(Boolean);
    var anyShown = false;
    rowEls.forEach(function (tr) {
      var hay = (tr.getAttribute('data-seller-view-products-search') || '').toLowerCase();
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
  searchInput.addEventListener('input', applyProductsSearch);
  searchInput.addEventListener('search', applyProductsSearch);
})();
</script>
<?php endif; ?>

<?php if ($totalOrders > 0): ?>
<script>
(function () {
  var searchInput = document.getElementById('sellerViewOrdersSearch');
  if (!searchInput) return;
  var rowEls = document.querySelectorAll('tr.admin-seller-view-orders-row');
  var noMatchRow = document.getElementById('adminSellerViewOrdersNoMatch');
  function applyOrdersSearch() {
    var q = (searchInput.value || '').trim().toLowerCase();
    var words = q.split(/\s+/).filter(Boolean);
    var anyShown = false;
    rowEls.forEach(function (tr) {
      var hay = (tr.getAttribute('data-seller-view-orders-search') || '').toLowerCase();
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
  searchInput.addEventListener('input', applyOrdersSearch);
  searchInput.addEventListener('search', applyOrdersSearch);
})();
</script>
<?php endif; ?>

</div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
