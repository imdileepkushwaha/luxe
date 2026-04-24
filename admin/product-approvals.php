<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_pagination.php';

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
             SET approval_status = 'approved',
                 active = 1
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

$sellerProductScope = "
     INNER JOIN seller_users s ON s.id = p.seller_id
     WHERE p.seller_id IS NOT NULL
       AND s.is_active = 1
       AND NOT EXISTS (
            SELECT 1
            FROM seller_account_deletion_requests dr
            WHERE dr.status = 'approved'
              AND (dr.seller_id = s.id OR dr.email = s.email)
       )";

$pendingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM products p {$sellerProductScope}
       AND p.approval_status = 'pending'"
)->fetchColumn();

$rejectedCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM products p {$sellerProductScope}
       AND p.approval_status = 'rejected'"
)->fetchColumn();

$approvedCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM products p {$sellerProductScope}
       AND p.approval_status = 'approved'"
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

$tabTotal = match ($tab) {
    'pending' => $pendingCount,
    'rejected' => $rejectedCount,
    default => $approvedCount,
};

['page' => $reqPage, 'perPage' => $perPage] = admin_pagination_read(25);
$pMeta = admin_pagination_resolve($tabTotal, $reqPage, $perPage);
$page = $pMeta['page'];
$offset = $pMeta['offset'];
$perPage = $pMeta['perPage'];
$totalPages = $pMeta['totalPages'];

if ($tabTotal === 0) {
    $rows = [];
} else {
    $rowsSt = $pdo->prepare(
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
         LIMIT ? OFFSET ?"
    );
    $rowsSt->bindValue(1, $perPage, PDO::PARAM_INT);
    $rowsSt->bindValue(2, $offset, PDO::PARAM_INT);
    $rowsSt->execute();
    $rows = $rowsSt->fetchAll();
}

/**
 * @param mixed $raw
 */
function admin_pa_fmt_created($raw): string
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
 * @param array<string, mixed> $row
 */
function admin_pa_search_haystack(array $row): string
{
    $parts = [
        (string) ($row['id'] ?? ''),
        (string) ($row['name'] ?? ''),
        (string) ($row['slug'] ?? ''),
        (string) ($row['sku'] ?? ''),
        (string) ($row['category'] ?? ''),
        (string) ($row['price'] ?? ''),
        (string) ($row['original_price'] ?? ''),
        (string) ($row['seller_name'] ?? ''),
        (string) ($row['seller_email'] ?? ''),
        (string) ($row['seller_business'] ?? ''),
        (string) ($row['approval_status'] ?? ''),
        (string) ($row['created_at'] ?? ''),
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

function admin_product_approval_badge(string $status): string
{
    return match (strtolower($status)) {
        'approved' => 'admin-status admin-status--delivered',
        'rejected' => 'admin-status admin-status--cancelled',
        default => 'admin-status admin-status--processing',
    };
}

$cardTitle = match ($tab) {
    'pending' => 'Awaiting approval',
    'rejected' => 'Rejected listings',
    default => 'Recently approved',
};

$cardSub = match ($tab) {
    'pending' => 'Oldest pending first. Approve or reject — in-page search filters loaded rows only.',
    'rejected' => 'Rejections that sellers can fix and resubmit.',
    default => 'Newest approved first — paginated below.',
};

$paRange = admin_pagination_visible_range($tabTotal, $page, $perPage);
$listHint = $tabTotal > 0
    ? 'Showing ' . (int) $paRange['from'] . '–' . (int) $paRange['to'] . ' of ' . (int) $tabTotal
    : 'No rows';

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-product-approvals-page">
        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?> admin-pa-flash" role="status">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Catalog</span>
            <h1>Product approvals</h1>
            <p class="admin-page-head__lede">Review seller submissions before they appear in the shop catalog.</p>
          </div>
          <div class="admin-page-head__actions">
            <a class="admin-btn admin-btn--outline" href="sellers.php">Sellers</a>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats admin-grid--stats--flow admin-pa-kpi-grid" aria-label="Approval counts">
          <div class="admin-card admin-stat admin-stat--stripe-amber admin-pa-kpi">
            <div>
              <div class="admin-stat__label admin-pa-kpi__label">Pending</div>
              <div class="admin-stat__value"><?= (int) $pendingCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Needs review</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--pink" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-red admin-pa-kpi">
            <div>
              <div class="admin-stat__label admin-pa-kpi__label">Rejected</div>
              <div class="admin-stat__value"><?= (int) $rejectedCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Can be edited &amp; resubmitted</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--red" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-teal admin-pa-kpi">
            <div>
              <div class="admin-stat__label admin-pa-kpi__label">Approved</div>
              <div class="admin-stat__value"><?= (int) $approvedCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Approved seller products (total)</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-blue admin-pa-kpi">
            <div>
              <div class="admin-stat__label admin-pa-kpi__label">This list</div>
              <div class="admin-stat__value"><?= (int) count($rows) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted"><?= h($listHint) ?></div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--purple" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
          </div>
        </div>

        <nav class="admin-pa-tabs" aria-label="Approval queue">
          <a class="admin-pa-tab<?= $tab === 'pending' ? ' admin-pa-tab--active' : '' ?>" href="product-approvals.php?tab=pending">
            <span class="admin-pa-tab__label">Pending</span>
            <span class="admin-pa-tab__meta"><?= (int) $pendingCount ?></span>
          </a>
          <a class="admin-pa-tab<?= $tab === 'rejected' ? ' admin-pa-tab--active' : '' ?>" href="product-approvals.php?tab=rejected">
            <span class="admin-pa-tab__label">Rejected</span>
            <span class="admin-pa-tab__meta"><?= (int) $rejectedCount ?></span>
          </a>
          <a class="admin-pa-tab<?= $tab === 'approved_recent' ? ' admin-pa-tab--active' : '' ?>" href="product-approvals.php?tab=approved_recent">
            <span class="admin-pa-tab__label">Recently approved</span>
            <span class="admin-pa-tab__meta"><?= (int) $approvedCount ?></span>
          </a>
        </nav>

        <div class="card admin-pa-table-card">
          <div class="card-header admin-pa-table-header">
            <div class="admin-pa-table-head">
              <div class="admin-pa-table-head-text">
                <h2 class="card-title"><?= h($cardTitle) ?></h2>
                <p class="card-subtitle admin-pa-table-sub"><?= h($cardSub) ?></p>
              </div>
              <?php if ($rows !== []): ?>
                <label class="admin-users-search-wrap admin-pa-search-wrap" for="adminPaSearch">
                  <span class="admin-users-search-icon admin-pa-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminPaSearch"
                    class="admin-users-search-input admin-pa-search-input"
                    placeholder="Search name, SKU, seller, category…"
                    autocomplete="off"
                    aria-label="Search this list"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($rows === []): ?>
              <p class="admin-empty-hint admin-empty-hint--boxed">Is section me abhi koi product nahi.</p>
            <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table admin-pa-table">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Seller</th>
                    <th>Category</th>
                    <th class="admin-table__th-money">Price</th>
                    <th class="admin-table__th-narrow">Status</th>
                    <th>Added</th>
                    <th class="admin-table__th-narrow">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $row): ?>
                    <?php
                    $pid = (int) ($row['id'] ?? 0);
                    $img = trim((string) ($row['image_path'] ?? ''));
                    $st = strtolower((string) ($row['approval_status'] ?? ''));
                    $hay = admin_pa_search_haystack($row);
                    ?>
                    <tr class="admin-pa-row" data-pa-search="<?= h($hay) ?>">
                      <td>
                        <div class="admin-pa-product">
                          <?php if ($img !== ''): ?>
                            <img class="admin-pa-product__thumb" src="../<?= h($img) ?>" alt="" width="48" height="48" loading="lazy">
                          <?php else: ?>
                            <div class="admin-pa-product__thumb admin-pa-product__thumb--placeholder" aria-hidden="true">
                              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            </div>
                          <?php endif; ?>
                          <div class="admin-pa-product__text">
                            <span class="admin-pa-product__name"><?= h((string) ($row['name'] ?? '')) ?></span>
                            <span class="admin-pa-product__meta">#<?= $pid ?> · <?= h((string) ($row['slug'] ?? '')) ?></span>
                            <?php $sku = trim((string) ($row['sku'] ?? '')); ?>
                            <?php if ($sku !== ''): ?>
                              <span class="admin-pa-product__sku">SKU <?= h($sku) ?></span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="admin-pa-seller">
                          <span class="admin-pa-seller__name"><?= h((string) ($row['seller_name'] ?? '')) ?></span>
                          <span class="admin-pa-seller__email"><?= h((string) ($row['seller_email'] ?? '')) ?></span>
                          <?php $biz = trim((string) ($row['seller_business'] ?? '')); ?>
                          <?php if ($biz !== ''): ?>
                            <span class="admin-pa-seller__biz"><?= h($biz) ?></span>
                          <?php endif; ?>
                          <a class="admin-pa-seller__link" href="seller-view.php?id=<?= (int) ($row['seller_user_id'] ?? 0) ?>">Seller profile →</a>
                        </div>
                      </td>
                      <td><span class="admin-badge admin-badge--muted"><?= h((string) ($row['category'] ?? '—')) ?></span></td>
                      <td class="admin-table__td-money">
                        <span class="admin-pa-price">₹<?= number_format((int) ($row['price'] ?? 0)) ?></span>
                        <span class="admin-pa-mrp">MRP ₹<?= number_format((int) ($row['original_price'] ?? 0)) ?></span>
                      </td>
                      <td>
                        <span class="<?= admin_product_approval_badge($st) ?>"><?= h(ucfirst($st)) ?></span>
                        <?php if ((int) ($row['active'] ?? 0) !== 1): ?>
                          <span class="admin-pa-inactive-flag">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td class="admin-table__td-muted"><?= h(admin_pa_fmt_created($row['created_at'] ?? null)) ?></td>
                      <td>
                        <div class="admin-pa-actions">
                          <a class="admin-btn admin-btn--primary admin-pa-actions__btn admin-pa-actions__btn--icon" href="../product.php?id=<?= $pid ?>" target="_blank" rel="noopener" title="Preview" aria-label="Preview product">
                          <svg class="seller-details-icon" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M9 4.46A9.8 9.8 0 0 1 12 4c4.182 0 7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296A14.5 14.5 0 0 1 5 6.821"></path><path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path></g></svg>
                          </a>
                          <?php if ($tab === 'pending'): ?>
                            <form method="post" class="admin-pa-actions__form" onsubmit="return confirm('Is product ko approve karke live karwana hai?');">
                              <input type="hidden" name="action" value="approve_product">
                              <input type="hidden" name="product_id" value="<?= $pid ?>">
                              <button type="submit" class="admin-btn admin-btn--outline admin-pa-actions__btn admin-pa-actions__btn--icon" title="Approve" aria-label="Approve product">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                              </button>
                            </form>
                            <form method="post" class="admin-pa-actions__form" onsubmit="return confirm('Reject kar dena hai? Seller dubara edit kar sakta hai.');">
                              <input type="hidden" name="action" value="reject_product">
                              <input type="hidden" name="product_id" value="<?= $pid ?>">
                              <button type="submit" class="admin-btn admin-pa-actions__btn admin-pa-actions__btn--reject admin-pa-actions__btn--icon" title="Reject" aria-label="Reject product">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                              </button>
                            </form>
                          <?php elseif ($st === 'rejected'): ?>
                            <form method="post" class="admin-pa-actions__form" onsubmit="return confirm('Ab is product ko approve karna hai?');">
                              <input type="hidden" name="action" value="approve_product">
                              <input type="hidden" name="product_id" value="<?= $pid ?>">
                              <button type="submit" class="admin-btn admin-btn--outline admin-pa-actions__btn admin-pa-actions__btn--icon" title="Approve" aria-label="Approve product">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                              </button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="adminPaNoMatchRow" class="admin-pa-no-match-row">
                    <td colspan="7">
                      <div class="admin-pa-no-match">
                        <strong class="admin-pa-no-match__title">No matches</strong>
                        <p class="admin-pa-no-match__text">Try another keyword — this page only.</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <script>
            (function () {
              var searchInput = document.getElementById('adminPaSearch');
              if (!searchInput) return;
              var rowEls = document.querySelectorAll('tr.admin-pa-row');
              var noMatchRow = document.getElementById('adminPaNoMatchRow');

              function applyPaSearch() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                rowEls.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-pa-search') || '').toLowerCase();
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

              searchInput.addEventListener('input', applyPaSearch);
              searchInput.addEventListener('search', applyPaSearch);
            })();
            </script>
            <?php
            $paginationScript = 'product-approvals.php';
            $paginationTotal = $tabTotal;
            $paginationPage = $page;
            $paginationPerPage = $perPage;
            $paginationTotalPages = $totalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            <?php endif; ?>
          </div>
        </div>

        <p class="admin-pa-footnote">
          Naye seller products <strong>pending</strong> se shuru hote hain; approve ke baad hi catalog / cart me dikhte hain. Seller changes ke baad dubara pending ho sakta hai.
        </p>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
