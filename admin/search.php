<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Search';
$activeNav = 'search';
$q = trim((string) ($_GET['q'] ?? ''));
$adminSearchQuery = $q;

/**
 * Escape LIKE wildcards for MySQL (with default escape \).
 */
function admin_global_search_like(string $term): string
{
    $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

    return '%' . $term . '%';
}

$minLen = 2;
$tooShort = $q !== '' && mb_strlen($q) < $minLen;

$users = [];
$orders = [];
$sellers = [];
$products = [];

if (!$tooShort && mb_strlen($q) >= $minLen) {
    $like = admin_global_search_like($q);

    $uSt = $pdo->prepare(
        'SELECT id, first_name, last_name, email, phone, created_at
         FROM users
         WHERE email LIKE ?
            OR first_name LIKE ?
            OR last_name LIKE ?
            OR phone LIKE ?
            OR CONCAT(COALESCE(first_name, \'\'), \' \', COALESCE(last_name, \'\')) LIKE ?
            OR CAST(id AS CHAR) LIKE ?
         ORDER BY id DESC
         LIMIT 20'
    );
    $uSt->execute([$like, $like, $like, $like, $like, $like]);
    $users = $uSt->fetchAll(PDO::FETCH_ASSOC);

    $oSt = $pdo->prepare(
        "SELECT o.id, o.order_ref, o.status, o.total_amount, o.created_at,
                u.first_name, u.last_name, u.email
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.order_ref LIKE ?
            OR CAST(o.id AS CHAR) LIKE ?
            OR o.shipping_address LIKE ?
            OR u.email LIKE ?
            OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ?
         ORDER BY o.id DESC
         LIMIT 20"
    );
    $oSt->execute([$like, $like, $like, $like, $like]);
    $orders = $oSt->fetchAll(PDO::FETCH_ASSOC);

    $sSt = $pdo->prepare(
        'SELECT id, full_name, email, business_name, created_at
         FROM seller_users
         WHERE email LIKE ?
            OR full_name LIKE ?
            OR business_name LIKE ?
            OR CAST(id AS CHAR) LIKE ?
         ORDER BY id DESC
         LIMIT 20'
    );
    $sSt->execute([$like, $like, $like, $like]);
    $sellers = $sSt->fetchAll(PDO::FETCH_ASSOC);

    $pSt = $pdo->prepare(
        "SELECT p.id, p.name, p.slug, p.sku, p.category, p.price, p.approval_status,
                s.id AS seller_id, s.full_name AS seller_name
         FROM products p
         INNER JOIN seller_users s ON s.id = p.seller_id
         WHERE p.seller_id IS NOT NULL
           AND s.is_active = 1
           AND NOT EXISTS (
                SELECT 1
                FROM seller_account_deletion_requests dr
                WHERE dr.status = 'approved'
                  AND (dr.seller_id = s.id OR dr.email = s.email)
           )
           AND (p.name LIKE ? OR p.slug LIKE ? OR p.sku LIKE ? OR CAST(p.id AS CHAR) LIKE ?)
         ORDER BY p.id DESC
         LIMIT 20"
    );
    $pSt->execute([$like, $like, $like, $like]);
    $products = $pSt->fetchAll(PDO::FETCH_ASSOC);
}

$totalHits = count($users) + count($orders) + count($sellers) + count($products);

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-search-page">
          <div class="admin-page-head">
            <div class="admin-page-head__intro">
              <span class="admin-page-head__eyebrow">Admin</span>
              <h1>Search</h1>
              <p class="admin-page-head__lede">Users, orders, sellers, and seller products (max 20 per category). Use the top bar or the form below.</p>
            </div>
          </div>

          <div class="card admin-users-card admin-search-page__block">
            <div class="card-header">
              <form method="get" action="search.php" class="admin-search-page-form" role="search">
                <label class="visually-hidden" for="adminSearchPageQ">Search query</label>
                <input type="search" id="adminSearchPageQ" name="q" value="<?= h($q) ?>" placeholder="Type at least 2 characters…" autocomplete="off" class="admin-users-search-input admin-search-page-form__input">
                <button type="submit" class="admin-btn admin-btn--primary">Search</button>
              </form>
            </div>
          </div>

          <?php if ($q === ''): ?>
            <p class="admin-empty-hint admin-empty-hint--boxed">Search query enter karein — shoppers, orders, sellers, ya products dhoondhne ke liye.</p>
          <?php elseif ($tooShort): ?>
            <p class="admin-empty-hint admin-empty-hint--boxed">Kam se kam <strong><?= (int) $minLen ?></strong> characters likhein.</p>
          <?php elseif ($totalHits === 0): ?>
            <p class="admin-empty-hint admin-empty-hint--boxed">Koi match nahi mila. Alag keyword try karein.</p>
          <?php endif; ?>

          <?php if (!$tooShort && mb_strlen($q) >= $minLen && $users !== []): ?>
          <div class="card admin-users-card admin-search-page__block">
            <div class="card-header">
              <h2 class="card-title">Shoppers (<?= count($users) ?>)</h2>
              <p class="card-subtitle">Registered users table — detail ke liye <a href="users.php">Users</a> page par bhi dekh sakte ho.</p>
            </div>
            <div class="card-body card-body--flush">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th class="admin-table__th-narrow">ID</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Phone</th>
                      <th>Joined</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($users as $u): ?>
                      <tr>
                        <td><?= (int) $u['id'] ?></td>
                        <td><?= h(trim((string) $u['first_name'] . ' ' . (string) $u['last_name'])) ?></td>
                        <td><?= h((string) $u['email']) ?></td>
                        <td class="admin-table__td-muted"><?= h((string) ($u['phone'] ?? '') ?: '—') ?></td>
                        <td class="admin-table__td-muted"><?= h((string) ($u['created_at'] ?? '')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!$tooShort && mb_strlen($q) >= $minLen && $orders !== []): ?>
          <div class="card admin-users-card admin-search-page__block">
            <div class="card-header">
              <h2 class="card-title">Orders (<?= count($orders) ?>)</h2>
              <p class="card-subtitle">Full list <a href="orders.php">Orders</a> page par — yahan ref se jump karein.</p>
            </div>
            <div class="card-body card-body--flush">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Ref</th>
                      <th>Customer</th>
                      <th>Status</th>
                      <th class="admin-table__th-money">Total</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($orders as $o): ?>
                      <?php
                      $cust = trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''));
                      if ($cust === '') {
                          $cust = '—';
                      }
                      ?>
                      <tr>
                        <td><strong><?= h((string) $o['order_ref']) ?></strong></td>
                        <td><?= h($cust) ?></td>
                        <td><?= h((string) $o['status']) ?></td>
                        <td class="admin-table__td-money">₹<?= number_format((int) $o['total_amount']) ?></td>
                        <td class="admin-table__td-muted"><?= h((string) $o['created_at']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!$tooShort && mb_strlen($q) >= $minLen && $sellers !== []): ?>
          <div class="card admin-users-card admin-search-page__block">
            <div class="card-header">
              <h2 class="card-title">Sellers (<?= count($sellers) ?>)</h2>
              <p class="card-subtitle">Profile open karne ke liye link use karein.</p>
            </div>
            <div class="card-body card-body--flush">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th class="admin-table__th-narrow">ID</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Business</th>
                      <th class="admin-table__th-narrow">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($sellers as $s): ?>
                      <tr>
                        <td><?= (int) $s['id'] ?></td>
                        <td><?= h((string) $s['full_name']) ?></td>
                        <td><?= h((string) $s['email']) ?></td>
                        <td><?= h((string) ($s['business_name'] ?? '') ?: '—') ?></td>
                        <td><a class="admin-btn admin-btn--outline" href="seller-view.php?id=<?= (int) $s['id'] ?>">Open</a></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!$tooShort && mb_strlen($q) >= $minLen && $products !== []): ?>
          <div class="card admin-users-card admin-search-page__block">
            <div class="card-header">
              <h2 class="card-title">Seller products (<?= count($products) ?>)</h2>
              <p class="card-subtitle">Store preview — admin queue <a href="product-approvals.php">Product approvals</a>.</p>
            </div>
            <div class="card-body card-body--flush">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th class="admin-table__th-narrow">ID</th>
                      <th>Product</th>
                      <th>Seller</th>
                      <th>Status</th>
                      <th class="admin-table__th-narrow">Preview</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($products as $p): ?>
                      <tr>
                        <td><?= (int) $p['id'] ?></td>
                        <td><?= h((string) $p['name']) ?></td>
                        <td><?= h((string) $p['seller_name']) ?> · #<?= (int) $p['seller_id'] ?></td>
                        <td><?= h((string) $p['approval_status']) ?></td>
                        <td><a class="admin-btn admin-btn--outline" href="../product.php?id=<?= (int) $p['id'] ?>" target="_blank" rel="noopener">View</a></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
