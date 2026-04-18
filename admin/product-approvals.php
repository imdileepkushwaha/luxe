<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Product approvals';
$activeNav = 'product_approvals';

$tab = strtolower(trim((string) ($_GET['tab'] ?? 'pending')));
if (!in_array($tab, ['pending', 'rejected', 'approved_recent'], true)) {
    $tab = 'pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $productId = (int) ($_POST['product_id'] ?? 0);

    if ($productId <= 0) {
        header('Location: product-approvals.php?tab=' . rawurlencode($tab) . '&msg=invalid');
        exit;
    }

    $own = $pdo->prepare(
        'SELECT p.id, p.approval_status
         FROM products p
         INNER JOIN seller_users s ON s.id = p.seller_id
         WHERE p.id = ?
           AND p.seller_id IS NOT NULL
           AND s.is_active = 1
           AND NOT EXISTS (
                SELECT 1
                FROM seller_account_deletion_requests dr
                WHERE dr.status = \'approved\'
                  AND (dr.seller_id = s.id OR dr.email = s.email)
           )
         LIMIT 1'
    );
    $own->execute([$productId]);
    $prow = $own->fetch(PDO::FETCH_ASSOC);
    if (!$prow) {
        header('Location: product-approvals.php?tab=' . rawurlencode($tab) . '&msg=notfound');
        exit;
    }

    if ($action === 'approve_product') {
        $upd = $pdo->prepare(
            "UPDATE products
             SET approval_status = 'approved'
             WHERE id = ?
               AND seller_id IS NOT NULL
             LIMIT 1"
        );
        $upd->execute([$productId]);
        header('Location: product-approvals.php?tab=' . rawurlencode($tab) . '&msg=' . ($upd->rowCount() > 0 ? 'approved' : 'fail'));
        exit;
    }

    if ($action === 'reject_product') {
        $cur = strtolower((string) ($prow['approval_status'] ?? ''));
        if ($cur !== 'pending') {
            header('Location: product-approvals.php?tab=' . rawurlencode($tab) . '&msg=reject_state');
            exit;
        }
        $upd = $pdo->prepare(
            "UPDATE products
             SET approval_status = 'rejected'
             WHERE id = ?
               AND approval_status = 'pending'
             LIMIT 1"
        );
        $upd->execute([$productId]);
        header('Location: product-approvals.php?tab=pending&msg=' . ($upd->rowCount() > 0 ? 'rejected' : 'fail'));
        exit;
    }

    header('Location: product-approvals.php?tab=' . rawurlencode($tab) . '&msg=invalid');
    exit;
}

$flash = null;
$msg = (string) ($_GET['msg'] ?? '');
if ($msg === 'approved') {
    $flash = ['ok' => true, 'text' => 'Product approved — ab yeh store par live ho sakta hai (active ho to).'];
} elseif ($msg === 'rejected') {
    $flash = ['ok' => true, 'text' => 'Product reject kar diya. Seller edit karke dubara bhej sakta hai.'];
} elseif ($msg === 'fail') {
    $flash = ['ok' => false, 'text' => 'Action apply nahi ho paya.'];
} elseif ($msg === 'invalid') {
    $flash = ['ok' => false, 'text' => 'Invalid request.'];
} elseif ($msg === 'notfound') {
    $flash = ['ok' => false, 'text' => 'Product ya seller valid nahi hai.'];
} elseif ($msg === 'reject_state') {
    $flash = ['ok' => false, 'text' => 'Sirf pending product reject ho sakta hai.'];
}

$pendingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM products p
     INNER JOIN seller_users s ON s.id = p.seller_id
     WHERE p.seller_id IS NOT NULL
       AND p.approval_status = 'pending'
       AND s.is_active = 1
       AND NOT EXISTS (
            SELECT 1
            FROM seller_account_deletion_requests dr
            WHERE dr.status = 'approved'
              AND (dr.seller_id = s.id OR dr.email = s.email)
       )"
)->fetchColumn();

$rejectedCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM products p
     INNER JOIN seller_users s ON s.id = p.seller_id
     WHERE p.seller_id IS NOT NULL
       AND p.approval_status = 'rejected'
       AND s.is_active = 1
       AND NOT EXISTS (
            SELECT 1
            FROM seller_account_deletion_requests dr
            WHERE dr.status = 'approved'
              AND (dr.seller_id = s.id OR dr.email = s.email)
       )"
)->fetchColumn();

$statusClause = match ($tab) {
    'pending' => "p.approval_status = 'pending'",
    'rejected' => "p.approval_status = 'rejected'",
    default => "p.approval_status = 'approved'",
};

$orderSql = match ($tab) {
    'approved_recent' => 'p.id DESC',
    default => 'p.id ASC',
};

$limit = $tab === 'approved_recent' ? 30 : 500;
$rows = $pdo->query(
    "SELECT p.id, p.name, p.slug, p.sku, p.category, p.price, p.original_price, p.image_path, p.approval_status, p.active, p.created_at,
            s.id AS seller_user_id, s.full_name AS seller_name, s.email AS seller_email, s.business_name AS seller_business
     FROM products p
     INNER JOIN seller_users s ON s.id = p.seller_id
     WHERE p.seller_id IS NOT NULL
       AND {$statusClause}
       AND s.is_active = 1
       AND NOT EXISTS (
            SELECT 1
            FROM seller_account_deletion_requests dr
            WHERE dr.status = 'approved'
              AND (dr.seller_id = s.id OR dr.email = s.email)
       )
     ORDER BY {$orderSql}
     LIMIT " . (int) $limit
)->fetchAll();

function admin_product_approval_badge(string $status): string
{
    return match (strtolower($status)) {
        'approved' => 'admin-status admin-status--delivered',
        'rejected' => 'admin-status admin-status--cancelled',
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
          <h1>Product approvals</h1>
          <div class="admin-page-head__actions">
            <span class="admin-date-pill" title="Queue">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
              Pending: <?= (int) $pendingCount ?>
            </span>
          </div>
        </div>

        <div class="admin-page-head" style="margin-top:-8px;margin-bottom:18px;flex-wrap:wrap;gap:10px">
          <a class="admin-btn<?= $tab === 'pending' ? ' admin-btn--primary' : '' ?>" href="product-approvals.php?tab=pending" style="<?= $tab === 'pending' ? '' : 'border:1px solid var(--admin-border)' ?>">Pending (<?= (int) $pendingCount ?>)</a>
          <a class="admin-btn<?= $tab === 'rejected' ? ' admin-btn--primary' : '' ?>" href="product-approvals.php?tab=rejected" style="<?= $tab === 'rejected' ? '' : 'border:1px solid var(--admin-border)' ?>">Rejected (<?= (int) $rejectedCount ?>)</a>
          <a class="admin-btn<?= $tab === 'approved_recent' ? ' admin-btn--primary' : '' ?>" href="product-approvals.php?tab=approved_recent" style="<?= $tab === 'approved_recent' ? '' : 'border:1px solid var(--admin-border)' ?>">Recently approved</a>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title"><?= $tab === 'pending' ? 'Awaiting approval' : ($tab === 'rejected' ? 'Rejected — seller dubara edit kar sakta hai' : 'Latest approved seller products') ?></h2>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Seller</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Added</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $row): ?>
                    <?php
                    $pid = (int) ($row['id'] ?? 0);
                    $img = trim((string) ($row['image_path'] ?? ''));
                    $st = strtolower((string) ($row['approval_status'] ?? ''));
                    ?>
                    <tr>
                      <td>
                        <div style="display:flex;gap:10px;align-items:flex-start">
                          <?php if ($img !== ''): ?>
                            <img src="../<?= h($img) ?>" alt="" width="48" height="48" style="object-fit:cover;border-radius:8px;border:1px solid var(--admin-border)">
                          <?php endif; ?>
                          <div>
                            <strong><?= h((string) ($row['name'] ?? '')) ?></strong><br>
                            <small style="color:var(--admin-text-muted)">#<?= $pid ?> · <?= h((string) ($row['slug'] ?? '')) ?></small>
                            <?php $sku = trim((string) ($row['sku'] ?? '')); ?>
                            <?php if ($sku !== ''): ?>
                              <br><small>SKU: <?= h($sku) ?></small>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div><?= h((string) ($row['seller_name'] ?? '')) ?></div>
                        <small style="color:var(--admin-text-muted)"><?= h((string) ($row['seller_email'] ?? '')) ?></small>
                        <?php $biz = trim((string) ($row['seller_business'] ?? '')); ?>
                        <?php if ($biz !== ''): ?>
                          <br><small><?= h($biz) ?></small>
                        <?php endif; ?>
                        <br><a href="seller-view.php?id=<?= (int) ($row['seller_user_id'] ?? 0) ?>" style="font-size:0.82rem">Seller profile →</a>
                      </td>
                      <td><?= h((string) ($row['category'] ?? '')) ?></td>
                      <td>Rs <?= number_format((int) ($row['price'] ?? 0)) ?><br><small>MRP Rs <?= number_format((int) ($row['original_price'] ?? 0)) ?></small></td>
                      <td>
                        <span class="<?= admin_product_approval_badge($st) ?>"><?= h(ucfirst($st)) ?></span>
                        <?php if ((int) ($row['active'] ?? 0) !== 1): ?>
                          <br><small class="admin-stat__delta admin-stat__delta--muted">Inactive flag</small>
                        <?php endif; ?>
                      </td>
                      <td><?= h((string) ($row['created_at'] ?? '—')) ?></td>
                      <td>
                        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-start">
                          <a class="admin-btn admin-btn--primary" style="padding:6px 10px" href="../product.php?id=<?= $pid ?>" target="_blank" rel="noopener">Preview</a>
                          <?php if ($tab === 'pending'): ?>
                            <form method="post" onsubmit="return confirm('Is product ko approve karke live karwana hai?');">
                              <input type="hidden" name="action" value="approve_product">
                              <input type="hidden" name="product_id" value="<?= $pid ?>">
                              <button type="submit" class="admin-btn" style="padding:6px 10px;border:1px solid var(--admin-border)">Approve</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Reject kar dena hai? Seller dubara edit kar sakta hai.');">
                              <input type="hidden" name="action" value="reject_product">
                              <input type="hidden" name="product_id" value="<?= $pid ?>">
                              <button type="submit" class="admin-btn admin-btn--outline" style="padding:6px 10px;color:#b91c1c;border-color:#fecaca">Reject</button>
                            </form>
                          <?php elseif ($st === 'rejected'): ?>
                            <form method="post" onsubmit="return confirm('Ab is product ko approve karna hai?');">
                              <input type="hidden" name="action" value="approve_product">
                              <input type="hidden" name="product_id" value="<?= $pid ?>">
                              <button type="submit" class="admin-btn" style="padding:6px 10px;border:1px solid var(--admin-border)">Approve</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="admin-stat__delta admin-stat__delta--muted" style="padding:1.25rem">Is section me abhi koi product nahi.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <p class="admin-stat__delta admin-stat__delta--muted" style="margin-top:14px">
          Naye seller products <strong>pending</strong> se shuru hote hain; approve ke baad hi catalog / cart me dikhte hain. Seller changes ke baad dubara pending ho sakta hai.
        </p>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
