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

    if ($sellerId > 0 && $action === 'toggle_seller_active') {
        $nextState = (int) ($_POST['next_state'] ?? -1);
        if (!in_array($nextState, [0, 1], true)) {
            $_SESSION['seller_admin_flash'] = ['ok' => false, 'text' => 'Invalid seller status update request.'];
            header('Location: sellers.php');
            exit;
        }

        $sellerSt = $pdo->prepare('SELECT id, full_name, email, is_active FROM seller_users WHERE id = ? LIMIT 1');
        $sellerSt->execute([$sellerId]);
        $sellerRow = $sellerSt->fetch(PDO::FETCH_ASSOC);
        if (!$sellerRow) {
            $_SESSION['seller_admin_flash'] = ['ok' => false, 'text' => 'Seller account not found.'];
            header('Location: sellers.php');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $setSeller = $pdo->prepare('UPDATE seller_users SET is_active = ? WHERE id = ? LIMIT 1');
            $setSeller->execute([$nextState, $sellerId]);

            $setProducts = $pdo->prepare('UPDATE products SET active = ? WHERE seller_id = ?');
            $setProducts->execute([$nextState, $sellerId]);

            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['seller_admin_flash'] = ['ok' => false, 'text' => 'Seller status update failed. Please try again.'];
            header('Location: sellers.php');
            exit;
        }

        $sellerName = trim((string) ($sellerRow['full_name'] ?? ''));
        if ($sellerName === '') {
            $sellerName = (string) ($sellerRow['email'] ?? ('Seller #' . $sellerId));
        }
        if ($nextState === 0) {
            $_SESSION['seller_admin_flash'] = ['ok' => true, 'text' => 'Seller deactivated: ' . $sellerName . '. Products are now out of stock.'];
        } else {
            $_SESSION['seller_admin_flash'] = ['ok' => true, 'text' => 'Seller activated: ' . $sellerName . '. Product stock visibility restored.'];
        }
        header('Location: sellers.php');
        exit;
    }

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
                s.business_name, s.phone_number, s.business_address, s.logo_path, s.banner_path,
                s.address_line1, s.city, s.state, s.pin_code,
                ds.status AS deletion_status,
                COALESCE(p_agg.products_count, 0)  AS products_count,
                COALESCE(p_agg.stock_total, 0)     AS stock_total,
                COALESCE(p_agg.avg_rating, 0)      AS avg_rating,
                COALESCE(p_agg.reviews_total, 0)   AS reviews_total,
                COALESCE(o_agg.orders_count, 0)    AS orders_count,
                COALESCE(o_agg.customers_count, 0) AS customers_count,
                COALESCE(o_agg.revenue_total, 0)   AS revenue_total
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
         LEFT JOIN (
            SELECT seller_id,
                   COUNT(*)            AS products_count,
                   SUM(stock_qty)      AS stock_total,
                   AVG(rating)         AS avg_rating,
                   SUM(review_count)   AS reviews_total
            FROM products
            GROUP BY seller_id
         ) p_agg ON p_agg.seller_id = s.id
         LEFT JOIN (
            SELECT per_order.seller_id,
                   COUNT(*)                            AS orders_count,
                   COUNT(DISTINCT per_order.user_id)   AS customers_count,
                   SUM(per_order.total_amount)         AS revenue_total
            FROM (
                SELECT DISTINCT p2.seller_id, o2.id AS order_id, o2.user_id, o2.total_amount
                FROM orders o2
                INNER JOIN order_items oi2 ON oi2.order_id = o2.id
                INNER JOIN products p2 ON p2.id = oi2.product_id
            ) per_order
            GROUP BY per_order.seller_id
         ) o_agg ON o_agg.seller_id = s.id
         ORDER BY s.id DESC
         LIMIT ? OFFSET ?"
    );
    $sellerSt->bindValue(1, $perPage, PDO::PARAM_INT);
    $sellerSt->bindValue(2, $offset, PDO::PARAM_INT);
    $sellerSt->execute();
    $sellerRows = $sellerSt->fetchAll();
}

/**
 * Build a one-line address string from available seller fields.
 * Falls back to business_address when granular fields are empty.
 *
 * @param array<string, mixed> $r
 */
function admin_sellers_fmt_address(array $r): string
{
    $parts = [];
    foreach (['address_line1', 'city', 'state', 'pin_code'] as $k) {
        $v = trim((string) ($r[$k] ?? ''));
        if ($v !== '') {
            $parts[] = $v;
        }
    }
    if ($parts !== []) {
        return implode(', ', $parts);
    }
    $ba = trim((string) ($r['business_address'] ?? ''));
    return $ba !== '' ? $ba : '';
}

/**
 * Pick the primary (first) category from allowed_categories CSV.
 */
function admin_sellers_primary_cat(string $csv): string
{
    foreach (explode(',', $csv) as $c) {
        $c = trim($c);
        if ($c !== '') {
            return ucfirst($c);
        }
    }
    return '';
}

/**
 * Compact money formatter — Rs 1.2k / 4.5L style for the card stat.
 */
function admin_sellers_fmt_money(int $rupees): string
{
    if ($rupees <= 0) {
        return 'Rs 0';
    }
    if ($rupees >= 10000000) {
        return 'Rs ' . rtrim(rtrim(number_format($rupees / 10000000, 2, '.', ''), '0'), '.') . 'Cr';
    }
    if ($rupees >= 100000) {
        return 'Rs ' . rtrim(rtrim(number_format($rupees / 100000, 2, '.', ''), '0'), '.') . 'L';
    }
    if ($rupees >= 1000) {
        return 'Rs ' . rtrim(rtrim(number_format($rupees / 1000, 1, '.', ''), '0'), '.') . 'k';
    }
    return 'Rs ' . number_format($rupees);
}

/**
 * Compact count formatter — 1.2k / 12.4k style.
 */
function admin_sellers_fmt_count(int $n): string
{
    if ($n >= 1000000) {
        return rtrim(rtrim(number_format($n / 1000000, 1, '.', ''), '0'), '.') . 'M';
    }
    if ($n >= 1000) {
        return rtrim(rtrim(number_format($n / 1000, 1, '.', ''), '0'), '.') . 'k';
    }
    return (string) $n;
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
        (string) ($r['business_name'] ?? ''),
        (string) ($r['phone_number'] ?? ''),
        (string) ($r['city'] ?? ''),
        (string) ($r['state'] ?? ''),
        (string) ($r['pin_code'] ?? ''),
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
              <div class="admin-sellers-head-tools">
                <?php if ($sellerRows !== []): ?>
                  <div class="admin-sellers-view-tabs" role="tablist" aria-label="Switch sellers view">
                    <button type="button" class="admin-sellers-view-tab" role="tab" aria-selected="false" data-sellers-view="table" aria-label="Table view" title="Table view">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18v14H3z"/><path d="M3 10h18"/><path d="M3 15h18"/><path d="M9 5v14"/></svg>
                    </button>
                    <button type="button" class="admin-sellers-view-tab is-active" role="tab" aria-selected="true" data-sellers-view="grid" aria-label="Grid view" title="Grid view">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    </button>
                  </div>
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
          </div>
          <div class="card-body card-body--flush">
            <?php if ($totalSellers === 0): ?>
              <p class="admin-empty-hint admin-empty-hint--boxed">No sellers registered yet.</p>
            <?php else: ?>
            <?php
            $maxRevenue = 0;
            foreach ($sellerRows as $r) {
                $maxRevenue = max($maxRevenue, (int) ($r['revenue_total'] ?? 0));
            }
            ?>
            <div class="admin-sellers-view admin-sellers-view--grid" data-sellers-view-panel="grid">
            <div class="admin-sellers-grid">
              <?php foreach ($sellerRows as $sellerRow): ?>
                <?php
                $hay = admin_sellers_search_haystack($sellerRow);
                $fn = trim((string) ($sellerRow['full_name'] ?? ''));
                $bizName = trim((string) ($sellerRow['business_name'] ?? ''));
                $cardName = $bizName !== '' ? $bizName : ($fn !== '' ? $fn : ('Seller #' . (int) $sellerRow['id']));
                $ini = strtoupper(substr(preg_replace('/\s+/', '', $cardName), 0, 2));
                if ($ini === '') {
                    $ini = '?';
                }
                $primaryCat = admin_sellers_primary_cat((string) $sellerRow['allowed_categories']);
                $allCats = [];
                foreach (explode(',', (string) $sellerRow['allowed_categories']) as $c) {
                    $c = trim($c);
                    if ($c !== '') {
                        $allCats[] = ucfirst($c);
                    }
                }
                $address = admin_sellers_fmt_address($sellerRow);
                $phone = trim((string) ($sellerRow['phone_number'] ?? ''));
                $logoPath = trim((string) ($sellerRow['logo_path'] ?? ''));
                $bannerPath = trim((string) ($sellerRow['banner_path'] ?? ''));
                $rating = (float) ($sellerRow['avg_rating'] ?? 0);
                $reviews = (int) ($sellerRow['reviews_total'] ?? 0);
                $revenue = (int) ($sellerRow['revenue_total'] ?? 0);
                $stock = (int) ($sellerRow['stock_total'] ?? 0);
                $sells = (int) ($sellerRow['orders_count'] ?? 0);
                $clients = (int) ($sellerRow['customers_count'] ?? 0);
                $progressPct = $maxRevenue > 0 ? min(100, (int) round($revenue * 100 / $maxRevenue)) : 0;

                $deletionStatus = strtolower((string) ($sellerRow['deletion_status'] ?? ''));
                $isActiveSeller = (int) ($sellerRow['is_active'] ?? 0) === 1;
                $isDeletedSeller = $deletionStatus === 'approved';
                $isPendingDelete = $deletionStatus === 'pending';
                $nextState = $isActiveSeller ? 0 : 1;

                if ($isDeletedSeller) {
                    $statusClass = 'admin-sellers-card__status--deleted';
                    $statusLabel = 'Deleted';
                } elseif ($isPendingDelete) {
                    $statusClass = 'admin-sellers-card__status--pending';
                    $statusLabel = 'Deletion pending';
                } elseif ($isActiveSeller) {
                    $statusClass = 'admin-sellers-card__status--active';
                    $statusLabel = 'Active';
                } else {
                    $statusClass = 'admin-sellers-card__status--inactive';
                    $statusLabel = 'Inactive';
                }
                ?>
                <article class="admin-sellers-card admin-sellers-row<?= $isActiveSeller ? '' : ' admin-sellers-card--muted' ?>" data-sellers-search="<?= h($hay) ?>">
                  <header class="admin-sellers-card__head<?= $bannerPath !== '' ? ' admin-sellers-card__head--has-banner' : '' ?>"<?php if ($bannerPath !== ''): ?> style="background-image: url(../<?= h($bannerPath) ?>);"<?php endif; ?>>
                    <span class="admin-sellers-card__status <?= h($statusClass) ?>" title="Account status"><?= h($statusLabel) ?></span>
                    <div class="admin-sellers-card__logo" aria-hidden="true">
                      <?php if ($logoPath !== ''): ?>
                        <img src="../<?= h($logoPath) ?>" alt="" loading="lazy">
                      <?php else: ?>
                        <span class="admin-sellers-card__logo-text"><?= h($ini) ?></span>
                      <?php endif; ?>
                    </div>
                  </header>

                  <div class="admin-sellers-card__body">
                    <h3 class="admin-sellers-card__name">
                      <?= h($cardName) ?>
                    </h3>
                    <?php if ($allCats !== []): ?>
                      <div class="admin-sellers-card__cats" aria-label="Allowed categories">
                        <?php foreach ($allCats as $cat): ?>
                          <span class="admin-sellers-card__cat-chip"><?= h($cat) ?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <a class="admin-sellers-card__link" href="mailto:<?= h((string) $sellerRow['email']) ?>" title="<?= h((string) $sellerRow['email']) ?>">
                      <?= h((string) $sellerRow['email']) ?>
                    </a>

                    <div class="admin-sellers-card__rating" title="Average product rating">
                      <span class="admin-sellers-card__star-pill">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        <span><?= $rating > 0 ? number_format($rating, 1) : '—' ?></span>
                      </span>
                      <span class="admin-sellers-card__rating-count"><?= $reviews > 0 ? admin_sellers_fmt_count($reviews) : 'No reviews' ?></span>
                    </div>

                    <ul class="admin-sellers-card__meta">
                      <li>
                        <span class="admin-sellers-card__meta-icon admin-sellers-card__meta-icon--pin" aria-hidden="true">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <span class="admin-sellers-card__meta-text"><?= h($address !== '' ? $address : 'Address not set') ?></span>
                      </li>
                      <li>
                        <span class="admin-sellers-card__meta-icon admin-sellers-card__meta-icon--mail" aria-hidden="true">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m22 6-10 7L2 6"/></svg>
                        </span>
                        <span class="admin-sellers-card__meta-text admin-sellers-card__meta-text--ellipsis"><?= h((string) $sellerRow['email']) ?></span>
                      </li>
                      <li>
                        <span class="admin-sellers-card__meta-icon admin-sellers-card__meta-icon--phone" aria-hidden="true">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </span>
                        <span class="admin-sellers-card__meta-text"><?= h($phone !== '' ? $phone : 'Phone not set') ?></span>
                      </li>
                    </ul>

                    <div class="admin-sellers-card__revenue">
                      <div class="admin-sellers-card__revenue-row">
                        <span class="admin-sellers-card__revenue-label"><?= h($primaryCat !== '' ? $primaryCat : 'Sales') ?></span>
                        <span class="admin-sellers-card__revenue-value">
                          <?= h(admin_sellers_fmt_money($revenue)) ?>
                          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 17 9 11 13 15 21 7"/><polyline points="14 7 21 7 21 14"/></svg>
                        </span>
                      </div>
                      <div class="admin-sellers-card__progress" role="progressbar" aria-valuenow="<?= (int) $progressPct ?>" aria-valuemin="0" aria-valuemax="100" title="Revenue vs. top seller on this page">
                        <span style="width: <?= (int) $progressPct ?>%"></span>
                      </div>
                    </div>

                    <ul class="admin-sellers-card__stats">
                      <li>
                        <span class="admin-sellers-card__stat-num"><?= h(admin_sellers_fmt_count($stock)) ?></span>
                        <span class="admin-sellers-card__stat-label">Item Stock</span>
                      </li>
                      <li>
                        <span class="admin-sellers-card__stat-num"><?= h(admin_sellers_fmt_count($sells)) ?></span>
                        <span class="admin-sellers-card__stat-label">Sells</span>
                      </li>
                      <li>
                        <span class="admin-sellers-card__stat-num"><?= h(admin_sellers_fmt_count($clients)) ?></span>
                        <span class="admin-sellers-card__stat-label">Happy Client</span>
                      </li>
                    </ul>
                  </div>

                  <footer class="admin-sellers-card__foot">
                    <a class="admin-sellers-card__btn admin-sellers-card__btn--primary" href="seller-view.php?id=<?= (int) $sellerRow['id'] ?>">View Profile</a>
                    <a class="admin-sellers-card__btn admin-sellers-card__btn--ghost" href="seller-kyc.php?id=<?= (int) $sellerRow['id'] ?>">KYC</a>

                    <?php if (!$isDeletedSeller): ?>
                      <form method="post" class="admin-sellers-card__icon-form" title="<?= $isActiveSeller ? 'Deactivate seller' : 'Activate seller' ?>">
                        <input type="hidden" name="action" value="toggle_seller_active">
                        <input type="hidden" name="seller_id" value="<?= (int) $sellerRow['id'] ?>">
                        <input type="hidden" name="next_state" value="<?= $nextState ?>">
                        <button type="submit"
                          class="admin-sellers-card__icon-btn admin-sellers-card__icon-btn--power<?= $isActiveSeller ? ' is-active' : '' ?>"
                          aria-label="<?= $isActiveSeller ? 'Deactivate seller' : 'Activate seller' ?>"
                          title="<?= $isActiveSeller ? 'Deactivate seller' : 'Activate seller' ?>"
                          onclick="return confirm(<?= $isActiveSeller ? "'Seller deactivate karna hai? Seller ke saare products out of stock ho jayenge.'" : "'Seller activate karna hai? Products ka stock wapas visible ho jayega.'" ?>);">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                        </button>
                      </form>
                      <form method="post" class="admin-sellers-card__icon-form" data-seller-delete-form data-seller-name="<?= h($cardName) ?>">
                        <input type="hidden" name="action" value="delete_seller">
                        <input type="hidden" name="seller_id" value="<?= (int) $sellerRow['id'] ?>">
                        <button type="submit"
                          class="admin-sellers-card__icon-btn admin-sellers-card__icon-btn--delete"
                          aria-label="Delete seller" title="Delete seller">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="admin-sellers-card__icon-note" title="Account permanently disabled">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="5.64" y1="5.64" x2="18.36" y2="18.36"/></svg>
                        <span>Disabled</span>
                      </span>
                    <?php endif; ?>
                  </footer>
                </article>
              <?php endforeach; ?>
            </div>
            </div>

            <div class="admin-sellers-view admin-sellers-view--table" data-sellers-view-panel="table" hidden>
              <div class="admin-table-wrap">
                <table class="admin-table admin-sellers-table">
                  <thead>
                    <tr>
                      <th>Seller</th>
                      <th class="admin-table__cell-email">Email</th>
                      <th>Phone</th>
                      <th>Allowed categories</th>
                      <th class="admin-table__th-narrow">Stock</th>
                      <th class="admin-table__th-narrow">Sells</th>
                      <th class="admin-table__th-narrow">Clients</th>
                      <th class="admin-table__th-narrow">Revenue</th>
                      <th class="admin-table__th-narrow">Status</th>
                      <th class="admin-table__th-narrow">Access</th>
                      <th>Created</th>
                      <th class="admin-table__th-narrow">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($sellerRows as $sellerRow): ?>
                      <?php
                      $hayT = admin_sellers_search_haystack($sellerRow);
                      $fnT = trim((string) ($sellerRow['full_name'] ?? ''));
                      $bizT = trim((string) ($sellerRow['business_name'] ?? ''));
                      $nameT = $bizT !== '' ? $bizT : ($fnT !== '' ? $fnT : ('Seller #' . (int) $sellerRow['id']));
                      $iniT = strtoupper(substr(preg_replace('/\s+/', '', $nameT), 0, 2));
                      if ($iniT === '') {
                          $iniT = '?';
                      }
                      $delStatusT = strtolower((string) ($sellerRow['deletion_status'] ?? ''));
                      $isActiveT = (int) ($sellerRow['is_active'] ?? 0) === 1;
                      $isDeletedT = $delStatusT === 'approved';
                      $nextStateT = $isActiveT ? 0 : 1;
                      $phoneT = trim((string) ($sellerRow['phone_number'] ?? ''));
                      ?>
                      <tr class="admin-sellers-row admin-sellers-table-row" data-sellers-search="<?= h($hayT) ?>">
                        <td>
                          <div class="admin-cell-user">
                            <div class="admin-avatar-sm"><?= h($iniT) ?></div>
                            <div class="admin-sellers-name-wrap">
                              <span class="admin-sellers-name"><?= h($nameT) ?></span>
                              <span class="admin-sellers-id">#<?= (int) $sellerRow['id'] ?></span>
                            </div>
                          </div>
                        </td>
                        <td class="admin-table__cell-email">
                          <span class="admin-sellers-email" title="<?= h((string) $sellerRow['email']) ?>"><?= h((string) $sellerRow['email']) ?></span>
                        </td>
                        <td><?= h($phoneT !== '' ? $phoneT : '—') ?></td>
                        <td>
                          <span class="admin-sellers-cats"><?= h((string) $sellerRow['allowed_categories']) ?></span>
                        </td>
                        <td class="admin-table__td-num"><?= (int) $sellerRow['stock_total'] ?></td>
                        <td class="admin-table__td-num"><?= (int) $sellerRow['orders_count'] ?></td>
                        <td class="admin-table__td-num"><?= (int) $sellerRow['customers_count'] ?></td>
                        <td class="admin-table__td-num"><?= h(admin_sellers_fmt_money((int) $sellerRow['revenue_total'])) ?></td>
                        <td>
                          <?php if ($isDeletedT): ?>
                            <span class="admin-status admin-status--cancelled">Deleted</span>
                          <?php elseif ($delStatusT === 'pending'): ?>
                            <span class="admin-status admin-status--processing">Deletion pending</span>
                          <?php elseif ($isActiveT): ?>
                            <span class="admin-status admin-status--delivered">Active</span>
                          <?php else: ?>
                            <span class="admin-status admin-status--cancelled">Inactive</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($isDeletedT): ?>
                            <span class="admin-sellers-toggle-note">Disabled</span>
                          <?php else: ?>
                            <form method="post" class="admin-sellers-toggle-form">
                              <input type="hidden" name="action" value="toggle_seller_active">
                              <input type="hidden" name="seller_id" value="<?= (int) $sellerRow['id'] ?>">
                              <input type="hidden" name="next_state" value="<?= $nextStateT ?>">
                              <label class="admin-sellers-switch" title="<?= $isActiveT ? 'Deactivate seller' : 'Activate seller' ?>">
                                <input
                                  type="checkbox"
                                  <?= $isActiveT ? 'checked' : '' ?>
                                  onchange="if(!confirm(this.checked ? 'Seller activate karna hai? Products ka stock wapas visible ho jayega.' : 'Seller deactivate karna hai? Seller ke saare products out of stock ho jayenge.')){ this.checked = !this.checked; return; } this.form.submit();"
                                >
                                <span class="admin-sellers-switch__slider" aria-hidden="true"></span>
                                <span class="admin-sellers-switch__label"><?= $isActiveT ? 'Active' : 'Inactive' ?></span>
                              </label>
                            </form>
                          <?php endif; ?>
                        </td>
                        <td class="admin-table__td-muted"><?= h(admin_sellers_fmt_created($sellerRow['created_at'] ?? null)) ?></td>
                        <td>
                          <div class="admin-sellers-actions">
                            <a href="seller-view.php?id=<?= (int) $sellerRow['id'] ?>" class="admin-btn admin-btn--primary admin-sellers-actions__btn admin-sellers-actions__btn--icon" aria-label="View seller" title="View seller">
                              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M9 4.46A9.8 9.8 0 0 1 12 4c4.182 0 7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296A14.5 14.5 0 0 1 5 6.821"></path><path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path></g></svg>
                            </a>
                            <?php if (!$isDeletedT): ?>
                              <form method="post" class="admin-sellers-actions__form" data-seller-delete-form data-seller-name="<?= h($nameT) ?>">
                                <input type="hidden" name="action" value="delete_seller">
                                <input type="hidden" name="seller_id" value="<?= (int) $sellerRow['id'] ?>">
                                <button type="submit" class="admin-btn admin-sellers-actions__btn admin-sellers-actions__btn--delete admin-sellers-actions__btn--icon" aria-label="Delete seller" title="Delete seller">
                                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M20.5 6h-17m5.67-2a3.001 3.001 0 0 1 5.66 0m3.544 11.4c-.177 2.654-.266 3.981-1.131 4.79s-2.195.81-4.856.81h-.774c-2.66 0-3.99 0-4.856-.81c-.865-.809-.953-2.136-1.13-4.79l-.46-6.9m13.666 0l-.2 3"></path></svg>
                                </button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div id="adminSellersNoMatchRow" class="admin-sellers-no-match-row">
              <strong class="admin-sellers-no-match__title">No matches</strong>
              <p class="admin-sellers-no-match__text">Try another keyword — name, email, ID, or categories (this page only).</p>
            </div>

            <div class="admin-confirm-modal" id="adminSellerDeleteModal" hidden aria-hidden="true">
              <div class="admin-confirm-modal__backdrop" data-confirm-close></div>
              <div class="admin-confirm-modal__dialog" role="alertdialog" aria-modal="true" aria-labelledby="adminSellerDeleteTitle" aria-describedby="adminSellerDeleteDesc">
                <div class="admin-confirm-modal__icon" aria-hidden="true">
                  <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                </div>
                <h3 id="adminSellerDeleteTitle" class="admin-confirm-modal__title">Delete seller account?</h3>
                <p id="adminSellerDeleteDesc" class="admin-confirm-modal__text">
                  <strong data-confirm-name>This seller</strong> ka account permanently delete ho jayega aur seller ke saare products unlink (discontinued) ho jayenge. Ye action wapas nahi ho sakti.
                </p>
                <div class="admin-confirm-modal__actions">
                  <button type="button" class="admin-btn admin-btn--outline admin-confirm-modal__btn" data-confirm-close>Cancel</button>
                  <button type="button" class="admin-btn admin-confirm-modal__btn admin-confirm-modal__btn--danger" data-confirm-accept>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="m19 6-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    Yes, delete seller
                  </button>
                </div>
              </div>
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
              var rows = document.querySelectorAll('.admin-sellers-row');
              var noMatch = document.getElementById('adminSellersNoMatchRow');
              var tabs = document.querySelectorAll('.admin-sellers-view-tab');
              var panels = document.querySelectorAll('[data-sellers-view-panel]');
              var STORAGE_KEY = 'adminSellersView';

              function applySellerSearch() {
                if (!searchInput) return;
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                rows.forEach(function (row) {
                  var hay = (row.getAttribute('data-sellers-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) {
                    return hay.indexOf(w) !== -1;
                  });
                  row.style.display = show ? '' : 'none';
                  if (show) anyShown = true;
                });
                if (noMatch) {
                  noMatch.style.display = (words.length > 0 && !anyShown) ? 'block' : 'none';
                }
              }

              function setView(view) {
                if (view !== 'table' && view !== 'grid') view = 'grid';
                panels.forEach(function (panel) {
                  var match = panel.getAttribute('data-sellers-view-panel') === view;
                  panel.hidden = !match;
                });
                tabs.forEach(function (tab) {
                  var match = tab.getAttribute('data-sellers-view') === view;
                  tab.classList.toggle('is-active', match);
                  tab.setAttribute('aria-selected', match ? 'true' : 'false');
                });
                try { localStorage.setItem(STORAGE_KEY, view); } catch (e) {}
              }

              tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                  setView(tab.getAttribute('data-sellers-view'));
                });
              });

              var saved = 'grid';
              try { saved = localStorage.getItem(STORAGE_KEY) || 'grid'; } catch (e) {}
              setView(saved);

              if (searchInput) {
                searchInput.addEventListener('input', applySellerSearch);
                searchInput.addEventListener('search', applySellerSearch);
              }

              var modal = document.getElementById('adminSellerDeleteModal');
              var deleteForms = document.querySelectorAll('[data-seller-delete-form]');
              if (modal && deleteForms.length) {
                var nameEl = modal.querySelector('[data-confirm-name]');
                var acceptBtn = modal.querySelector('[data-confirm-accept]');
                var closeEls = modal.querySelectorAll('[data-confirm-close]');
                var pendingForm = null;
                var lastTrigger = null;

                function openModal(form, trigger) {
                  pendingForm = form;
                  lastTrigger = trigger || null;
                  var nm = (form.getAttribute('data-seller-name') || '').trim();
                  if (nameEl) nameEl.textContent = nm !== '' ? nm : 'This seller';
                  modal.hidden = false;
                  modal.setAttribute('aria-hidden', 'false');
                  document.body.classList.add('admin-modal-open');
                  requestAnimationFrame(function () {
                    modal.classList.add('is-open');
                    if (acceptBtn) acceptBtn.focus();
                  });
                }

                function closeModal() {
                  modal.classList.remove('is-open');
                  modal.setAttribute('aria-hidden', 'true');
                  document.body.classList.remove('admin-modal-open');
                  pendingForm = null;
                  setTimeout(function () { modal.hidden = true; }, 160);
                  if (lastTrigger && typeof lastTrigger.focus === 'function') {
                    try { lastTrigger.focus(); } catch (e) {}
                  }
                  lastTrigger = null;
                }

                deleteForms.forEach(function (form) {
                  form.addEventListener('submit', function (e) {
                    if (form.dataset.confirmed === '1') return;
                    e.preventDefault();
                    var trigger = form.querySelector('button[type="submit"]');
                    openModal(form, trigger);
                  });
                });

                closeEls.forEach(function (el) {
                  el.addEventListener('click', closeModal);
                });

                if (acceptBtn) {
                  acceptBtn.addEventListener('click', function () {
                    if (!pendingForm) { closeModal(); return; }
                    var form = pendingForm;
                    form.dataset.confirmed = '1';
                    closeModal();
                    form.submit();
                  });
                }

                document.addEventListener('keydown', function (e) {
                  if (modal.hidden) return;
                  if (e.key === 'Escape') {
                    e.preventDefault();
                    closeModal();
                  }
                });
              }
            })();
            </script>
            <?php endif; ?>
          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
