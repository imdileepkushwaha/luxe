<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Sellers';
$activeNav = 'sellers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $sellerId = (int) ($_POST['seller_id'] ?? 0);

    if ($sellerId > 0 && $action === 'delete_seller') {
        $sellerSt = $pdo->prepare('SELECT id, full_name, email FROM seller_users WHERE id = ? LIMIT 1');
        $sellerSt->execute([$sellerId]);
        $sellerRow = $sellerSt->fetch();

        if (!$sellerRow) {
            $_SESSION['seller_admin_flash'] = ['ok' => false, 'text' => 'Seller account not found.'];
            header('Location: sellers.php');
            exit;
        }

        try {
            $pdo->beginTransaction();
            seller_mark_products_discontinued($pdo, $sellerId);
            $del = $pdo->prepare('DELETE FROM seller_users WHERE id = ? LIMIT 1');
            $del->execute([$sellerId]);
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['seller_admin_flash'] = ['ok' => false, 'text' => 'Seller delete failed. Please try again.'];
            header('Location: sellers.php');
            exit;
        }

        $sellerName = trim((string) ($sellerRow['full_name'] ?? ''));
        if ($sellerName === '') {
            $sellerName = (string) ($sellerRow['email'] ?? 'Seller');
        }
        $_SESSION['seller_admin_flash'] = ['ok' => true, 'text' => 'Seller account deleted: ' . $sellerName . ' (products discontinued).'];
        header('Location: sellers.php');
        exit;
    }
}

$flash = null;
if (isset($_SESSION['seller_admin_flash']) && is_array($_SESSION['seller_admin_flash'])) {
    $flash = $_SESSION['seller_admin_flash'];
    unset($_SESSION['seller_admin_flash']);
}

$statsRow = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_sellers,
        COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) AS inactive_sellers,
        COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END), 0) AS new_sellers_today
     FROM seller_users"
)->fetch();
$pendingSellerCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_create_requests WHERE status = 'pending'")->fetchColumn();

$sellerRows = $pdo->query(
    "SELECT s.id, s.full_name, s.email, s.allowed_categories, s.is_active, s.created_at,
            ds.status AS deletion_status,
            COUNT(p.id) AS products_count,
            COUNT(DISTINCT o.id) AS orders_count
     FROM seller_users s
     LEFT JOIN (
        SELECT r1.email, r1.status
        FROM seller_account_deletion_requests r1
        INNER JOIN (
            SELECT email, MAX(id) AS max_id
            FROM seller_account_deletion_requests
            GROUP BY email
        ) x ON x.max_id = r1.id
     ) ds ON ds.email = s.email
     LEFT JOIN products p ON p.seller_id = s.id
     LEFT JOIN order_items oi ON oi.product_id = p.id
     LEFT JOIN orders o ON o.id = oi.order_id
     GROUP BY s.id, s.full_name, s.email, s.allowed_categories, s.is_active, s.created_at, ds.status
     ORDER BY s.id DESC"
)->fetchAll();

require __DIR__ . '/partials/shell-top.php';
?>

<?php if ($flash): ?>
  <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?>" role="status" style="margin-bottom:14px">
    <?= h((string) ($flash['text'] ?? '')) ?>
  </div>
<?php endif; ?>

<div class="admin-grid admin-grid--stats" style="margin-bottom:16px">
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Active Seller</div>
      <div class="admin-stat__value"><?= (int) ($statsRow['active_sellers'] ?? 0) ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">New Seller Same day</div>
      <div class="admin-stat__value"><?= (int) ($statsRow['new_sellers_today'] ?? 0) ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">In active seller</div>
      <div class="admin-stat__value"><?= (int) ($statsRow['inactive_sellers'] ?? 0) ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Pending seller</div>
      <div class="admin-stat__value"><?= (int) $pendingSellerCount ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h1 class="admin-page-title card-title">Seller list</h1>
    <a href="seller-kyc.php" class="admin-btn admin-btn--primary">Review KYC requests</a>
  </div>
  <div class="card-body card-body--flush">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Seller</th>
            <th>Email</th>
            <th>Allowed categories</th>
            <th>Products added</th>
            <th>Orders</th>
            <th>Status</th>
            <th>Created</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sellerRows as $sellerRow): ?>
            <tr>
              <td>
                <strong><?= h((string) $sellerRow['full_name']) ?></strong><br>
                <small>#<?= (int) $sellerRow['id'] ?></small>
              </td>
              <td><?= h((string) $sellerRow['email']) ?></td>
              <td><?= h((string) $sellerRow['allowed_categories']) ?></td>
              <td><?= (int) $sellerRow['products_count'] ?></td>
              <td><?= (int) $sellerRow['orders_count'] ?></td>
              <td>
                <?php $deletionStatus = strtolower((string) ($sellerRow['deletion_status'] ?? '')); ?>
                <?php if ($deletionStatus === 'approved'): ?>
                  <span class="admin-status admin-status--cancelled">Deleted</span>
                <?php elseif ($deletionStatus === 'pending'): ?>
                  <span class="admin-status admin-status--processing">Deletion pending</span>
                <?php elseif ((int) $sellerRow['is_active'] === 1): ?>
                  <span class="admin-status admin-status--delivered">Active</span>
                <?php else: ?>
                  <span class="admin-status admin-status--cancelled">Inactive</span>
                <?php endif; ?>
              </td>
              <td><?= h((string) $sellerRow['created_at']) ?></td>
              <td>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                  <a href="seller-view.php?id=<?= (int) $sellerRow['id'] ?>" class="admin-btn admin-btn--primary" style="padding:6px 10px">View</a>
                  <?php if ($deletionStatus !== 'approved'): ?>
                    <form method="post" onsubmit="return confirm('Seller account delete karna hai? Is seller ke products unlink ho jayenge.');">
                      <input type="hidden" name="action" value="delete_seller">
                      <input type="hidden" name="seller_id" value="<?= (int) $sellerRow['id'] ?>">
                      <button type="submit" class="admin-btn" style="padding:6px 10px;border:1px solid var(--admin-border);color:#b91c1c">Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($sellerRows === []): ?>
            <tr><td colspan="8">No sellers found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
