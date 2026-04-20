<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_pagination.php';

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

$totalSellers = (int) $pdo->query('SELECT COUNT(*) FROM seller_users')->fetchColumn();

['page' => $reqPage, 'perPage' => $perPage] = admin_pagination_read(25);
$pMeta = admin_pagination_resolve($totalSellers, $reqPage, $perPage);
$page = $pMeta['page'];
$offset = $pMeta['offset'];
$perPage = $pMeta['perPage'];
$totalPages = $pMeta['totalPages'];

if ($totalSellers === 0) {
    $sellerRows = [];
} else {
    $sellerSt = $pdo->prepare(
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
         ORDER BY s.id DESC
         LIMIT ? OFFSET ?"
    );
    $sellerSt->bindValue(1, $perPage, PDO::PARAM_INT);
    $sellerSt->bindValue(2, $offset, PDO::PARAM_INT);
    $sellerSt->execute();
    $sellerRows = $sellerSt->fetchAll();
}

/**
 * @param mixed $raw
 */
function admin_sellers_fmt_created($raw): string
{
    if ($raw === null || $raw === '') {
        return '—';
    }
    $s = (string) $raw;
    try {
        return (new DateTimeImmutable($s))->format('M j, Y · g:i A');
    } catch (Throwable $e) {
        return $s;
    }
}

/**
 * @param array<string, mixed> $r
 */
function admin_sellers_search_haystack(array $r): string
{
    $parts = [
        (string) ($r['id'] ?? ''),
        (string) ($r['full_name'] ?? ''),
        (string) ($r['email'] ?? ''),
        (string) ($r['allowed_categories'] ?? ''),
        (string) ($r['products_count'] ?? ''),
        (string) ($r['orders_count'] ?? ''),
        (string) ($r['is_active'] ?? ''),
        (string) ($r['deletion_status'] ?? ''),
        (string) ($r['created_at'] ?? ''),
    ];
    $clean = [];
    foreach ($parts as $p) {
        $t = trim((string) $p);
        if ($t !== '') {
            $clean[] = $t;
        }
    }
    $s = implode(' ', $clean);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($s, 'UTF-8');
    }

    return strtolower($s);
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-sellers-page">
        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?> admin-sellers-flash" role="status">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Marketplace</span>
            <h1>Sellers</h1>
            <p class="admin-page-head__lede">Registered seller accounts, catalog activity, and moderation actions.</p>
          </div>
          <div class="admin-page-head__actions">
            <a class="admin-btn admin-btn--outline" href="product-approvals.php">Product approvals</a>
            <a class="admin-btn admin-btn--primary" href="seller-kyc.php">Review KYC</a>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats admin-grid--stats--flow admin-sellers-kpi-grid" aria-label="Seller summary">
          <div class="admin-card admin-stat admin-stat--stripe-green admin-sellers-kpi">
            <div>
              <div class="admin-stat__label admin-sellers-kpi__label">Active</div>
              <div class="admin-stat__value"><?= (int) ($statsRow['active_sellers'] ?? 0) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Can list products</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--green" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-blue admin-sellers-kpi">
            <div>
              <div class="admin-stat__label admin-sellers-kpi__label">New today</div>
              <div class="admin-stat__value"><?= (int) ($statsRow['new_sellers_today'] ?? 0) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Joined today (date)</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-red admin-sellers-kpi">
            <div>
              <div class="admin-stat__label admin-sellers-kpi__label">Inactive</div>
              <div class="admin-stat__value"><?= (int) ($statsRow['inactive_sellers'] ?? 0) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Flagged off</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--red" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-amber admin-sellers-kpi">
            <div>
              <div class="admin-stat__label admin-sellers-kpi__label">Pending KYC</div>
              <div class="admin-stat__value"><?= (int) $pendingSellerCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Awaiting registration review</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--pink" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="14" y1="2" x2="14" y2="8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
          </div>
        </div>

        <div class="card admin-sellers-table-card">
          <div class="card-header admin-sellers-table-header">
            <div class="admin-sellers-table-head">
              <div class="admin-sellers-table-head-text">
                <h2 class="card-title">All sellers</h2>
                <p class="card-subtitle admin-sellers-table-sub">
                  <?= (int) $totalSellers ?> seller account<?= $totalSellers === 1 ? '' : 's' ?> total · Search filters this page only. On small screens, scroll the table sideways if needed.
                </p>
              </div>
              <?php if ($sellerRows !== []): ?>
                <label class="admin-users-search-wrap admin-sellers-search-wrap" for="adminSellersSearch">
                  <span class="admin-users-search-icon admin-sellers-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminSellersSearch"
                    class="admin-users-search-input admin-sellers-search-input"
                    placeholder="Search name, email, ID, categories…"
                    autocomplete="off"
                    aria-label="Search sellers"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($totalSellers === 0): ?>
              <p class="admin-empty-hint admin-empty-hint--boxed">No sellers registered yet.</p>
            <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table admin-sellers-table">
                <thead>
                  <tr>
                    <th>Seller</th>
                    <th class="admin-table__cell-email">Email</th>
                    <th>Allowed categories</th>
                    <th class="admin-table__th-narrow">Products</th>
                    <th class="admin-table__th-narrow">Orders</th>
                    <th class="admin-table__th-narrow">Status</th>
                    <th>Created</th>
                    <th class="admin-table__th-narrow">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($sellerRows as $sellerRow): ?>
                    <?php
                    $hay = admin_sellers_search_haystack($sellerRow);
                    $fn = trim((string) ($sellerRow['full_name'] ?? ''));
                    $ini = strtoupper(substr(preg_replace('/\s+/', '', $fn), 0, 2));
                    if ($ini === '') {
                        $ini = '?';
                    }
                    ?>
                    <tr class="admin-sellers-row" data-sellers-search="<?= h($hay) ?>">
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <div class="admin-sellers-name-wrap">
                            <span class="admin-sellers-name"><?= h($fn !== '' ? $fn : '—') ?></span>
                            <span class="admin-sellers-id">#<?= (int) $sellerRow['id'] ?></span>
                          </div>
                        </div>
                      </td>
                      <td class="admin-table__cell-email">
                        <span class="admin-sellers-email" title="<?= h((string) $sellerRow['email']) ?>"><?= h((string) $sellerRow['email']) ?></span>
                      </td>
                      <td>
                        <span class="admin-sellers-cats"><?= h((string) $sellerRow['allowed_categories']) ?></span>
                      </td>
                      <td class="admin-table__td-num"><?= (int) $sellerRow['products_count'] ?></td>
                      <td class="admin-table__td-num"><?= (int) $sellerRow['orders_count'] ?></td>
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
                      <td class="admin-table__td-muted"><?= h(admin_sellers_fmt_created($sellerRow['created_at'] ?? null)) ?></td>
                      <td>
                        <div class="admin-sellers-actions">
                          <a href="seller-view.php?id=<?= (int) $sellerRow['id'] ?>" class="admin-btn admin-btn--primary admin-sellers-actions__btn">View</a>
                          <?php if ($deletionStatus !== 'approved'): ?>
                            <form method="post" class="admin-sellers-actions__form" onsubmit="return confirm('Seller account delete karna hai? Is seller ke products unlink ho jayenge.');">
                              <input type="hidden" name="action" value="delete_seller">
                              <input type="hidden" name="seller_id" value="<?= (int) $sellerRow['id'] ?>">
                              <button type="submit" class="admin-btn admin-sellers-actions__btn admin-sellers-actions__btn--delete">Delete</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="adminSellersNoMatchRow" class="admin-sellers-no-match-row">
                    <td colspan="8">
                      <div class="admin-sellers-no-match">
                        <strong class="admin-sellers-no-match__title">No matches</strong>
                        <p class="admin-sellers-no-match__text">Try another keyword — name, email, ID, or categories (this page only).</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'sellers.php';
            $paginationTotal = $totalSellers;
            $paginationPage = $page;
            $paginationPerPage = $perPage;
            $paginationTotalPages = $totalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            <script>
            (function () {
              var searchInput = document.getElementById('adminSellersSearch');
              if (!searchInput) return;
              var userRows = document.querySelectorAll('tr.admin-sellers-row');
              var noMatchRow = document.getElementById('adminSellersNoMatchRow');

              function applySellerSearch() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                userRows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-sellers-search') || '').toLowerCase();
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

              searchInput.addEventListener('input', applySellerSearch);
              searchInput.addEventListener('search', applySellerSearch);
            })();
            </script>
            <?php endif; ?>
          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
