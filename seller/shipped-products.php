<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../admin/_pagination.php';

$pdo = db();
$seller = seller_require_login($pdo);
$sellerId = (int) $seller['id'];

$pageTitle = 'Shipped products';
$activeNav = 'shipped';

$statusFilter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$allowedFilters = ['all', 'shipped', 'out', 'delivered'];
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

function seller_shipped_status_chip(string $status): string
{
    return match (strtolower(trim($status))) {
        'delivered' => 'seller-status-chip--delivered',
        'out', 'shipped' => 'seller-status-chip--shipped',
        default => '',
    };
}

function seller_shipped_status_label(string $status): string
{
    $s = strtolower(trim($status));

    return match ($s) {
        'out' => 'Out for delivery',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        default => $s !== '' ? ucfirst($s) : '—',
    };
}

function seller_shipped_format_dt(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('M j, Y · g:i A');
    } catch (Throwable) {
        return $raw;
    }
}

$pipelineStatuses = ['shipped', 'out', 'delivered'];

$countSt = $pdo->prepare(
    "SELECT o.status, COUNT(*) AS cnt
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     INNER JOIN products p ON p.id = oi.product_id
     WHERE p.seller_id = ?
       AND o.status IN ('shipped', 'out', 'delivered')
     GROUP BY o.status"
);
$countSt->execute([$sellerId]);
$statusCounts = ['shipped' => 0, 'out' => 0, 'delivered' => 0, 'all' => 0];
while ($row = $countSt->fetch()) {
    $s = strtolower(trim((string) ($row['status'] ?? '')));
    $c = (int) ($row['cnt'] ?? 0);
    if (isset($statusCounts[$s])) {
        $statusCounts[$s] = $c;
        $statusCounts['all'] += $c;
    }
}

$totalSt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     INNER JOIN products p ON p.id = oi.product_id
     WHERE p.seller_id = ?
       AND o.status IN ('shipped', 'out', 'delivered')"
     . ($statusFilter !== 'all' ? ' AND o.status = ?' : '')
);
if ($statusFilter === 'all') {
    $totalSt->execute([$sellerId]);
} else {
    $totalSt->execute([$sellerId, $statusFilter]);
}
$totalLines = (int) $totalSt->fetchColumn();

['page' => $listPage, 'perPage' => $listPerPage] = admin_pagination_read(25);
$pageMeta = admin_pagination_resolve($totalLines, $listPage, $listPerPage);
$listPage = $pageMeta['page'];
$listOffset = $pageMeta['offset'];
$listPerPage = $pageMeta['perPage'];
$totalPages = $pageMeta['totalPages'];

$sql = "SELECT oi.id AS order_item_id,
               o.id AS order_id,
               o.order_ref,
               o.status AS order_status,
               o.payment_method,
               o.created_at AS order_placed_at,
               oi.name AS item_name,
               oi.emoji AS item_emoji,
               oi.variant_text,
               oi.price AS line_price,
               oi.qty AS line_qty,
               p.id AS product_id,
               p.slug AS product_slug,
               p.category AS product_category,
               COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), 'Guest') AS customer_name,
               COALESCE(u.email, '—') AS customer_email
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        INNER JOIN products p ON p.id = oi.product_id
        LEFT JOIN users u ON u.id = o.user_id
        WHERE p.seller_id = ?
          AND o.status IN ('shipped', 'out', 'delivered')";
if ($statusFilter !== 'all') {
    $sql .= ' AND o.status = ?';
}
$sql .= ' ORDER BY o.id DESC, oi.id ASC
          LIMIT ' . (int) $listPerPage . ' OFFSET ' . (int) $listOffset;

$rowsSt = $pdo->prepare($sql);
if ($statusFilter === 'all') {
    $rowsSt->execute([$sellerId]);
} else {
    $rowsSt->execute([$sellerId, $statusFilter]);
}
$rows = $rowsSt->fetchAll();

$filterQueryBase = static function (string $script, string $filter, int $page, int $perPage): string {
    $q = ['status' => $filter, 'page' => $page, 'per_page' => $perPage];

    return $script . '?' . http_build_query($q);
};

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-orders-page-head seller-shipped-head">
          <div>
            <h1>Shipped products</h1>
            <p class="seller-orders-subtitle">Aapke catalogue ki wo lines jahan order <strong>ship ho chuka hai</strong> — status (shipped / out / delivered), customer aur line detail ek jagah.</p>
          </div>
          <div class="admin-page-head__actions seller-orders-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="orders.php">All orders</a>
          </div>
        </div>

        <div class="seller-shipped-kpis seller-kpi seller-orders-kpis">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">All shipped lines</div>
              <div class="seller-kpi-card__value"><?= (int) $statusCounts['all'] ?></div>
              <div class="seller-kpi-card__hint">Shipped + out + delivered</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18h2"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Shipped</div>
              <div class="seller-kpi-card__value"><?= (int) $statusCounts['shipped'] ?></div>
              <div class="seller-kpi-card__hint">Courier handed over</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Out for delivery</div>
              <div class="seller-kpi-card__value"><?= (int) $statusCounts['out'] ?></div>
              <div class="seller-kpi-card__hint">Last-mile</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3z"/><path d="M16 10h3l2 2v5h-5z"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Delivered</div>
              <div class="seller-kpi-card__value"><?= (int) $statusCounts['delivered'] ?></div>
              <div class="seller-kpi-card__hint">Buyer ko mil gaya</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
          </div>
        </div>

        <div class="seller-shipped-filters" role="tablist" aria-label="Filter by order status">
          <?php
          $filters = [
              'all' => 'All',
              'shipped' => 'Shipped',
              'out' => 'Out for delivery',
              'delivered' => 'Delivered',
          ];
          foreach ($filters as $key => $label):
              $isActive = $statusFilter === $key;
              $href = $filterQueryBase('shipped-products.php', $key, 1, $listPerPage);
              ?>
            <a
              class="seller-shipped-filter<?= $isActive ? ' seller-shipped-filter--active' : '' ?>"
              role="tab"
              aria-selected="<?= $isActive ? 'true' : 'false' ?>"
              href="<?= h($href) ?>"
            ><?= h($label) ?></a>
          <?php endforeach; ?>
        </div>

        <div class="card seller-orders-card seller-shipped-card">
          <div class="card-header">
            <div class="seller-card-head seller-card-head--inventory seller-orders-card-head">
              <div>
                <h2 class="card-title">Line items</h2>
                <p class="card-subtitle seller-orders-card-sub"><?= $totalLines === 0 ? 'Abhi is filter par koi shipped line nahi.' : (int) $totalLines . ' line' . ($totalLines === 1 ? '' : 's') . ' · Har row aapka ek product + us order ka live status.' ?></p>
              </div>
              <div class="seller-inventory-toolbar seller-orders-toolbar">
                <label class="seller-inventory-search-wrap seller-orders-search" for="sellerShippedSearch">
                  <span class="seller-inventory-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="sellerShippedSearch"
                    class="seller-inventory-search-input"
                    placeholder="Search product, order ref, customer, email…"
                    autocomplete="off"
                    aria-label="Search shipped lines"
                  >
                </label>
              </div>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap seller-orders-table-wrap seller-shipped-table-wrap">
              <table class="admin-table seller-orders-table seller-shipped-table">
                <thead>
                  <tr>
                    <th>Product &amp; variant</th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Qty &amp; line total</th>
                    <th>Order status</th>
                    <th>Placed</th>
                    <th class="seller-orders-th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $r): ?>
                    <?php
                    $oid = (int) ($r['order_id'] ?? 0);
                    $lineTotal = max(0, (int) ($r['line_price'] ?? 0)) * max(1, (int) ($r['line_qty'] ?? 1));
                    $variant = trim((string) ($r['variant_text'] ?? ''));
                    $st = strtolower(trim((string) ($r['order_status'] ?? '')));
                    $searchBlob = mb_strtolower(trim(
                        (string) ($r['item_name'] ?? '') . ' '
                        . (string) ($r['order_ref'] ?? '') . ' '
                        . (string) ($r['customer_name'] ?? '') . ' '
                        . (string) ($r['customer_email'] ?? '') . ' '
                        . $variant . ' '
                        . (string) ($r['product_category'] ?? '') . ' '
                        . seller_shipped_status_label($st)
                    ));
                    ?>
                    <tr class="seller-order-row seller-shipped-row" data-shipped-search="<?= h($searchBlob) ?>">
                      <td class="seller-shipped-cell-product">
                        <div class="seller-shipped-product-name">
                          <?php if (trim((string) ($r['item_emoji'] ?? '')) !== ''): ?>
                            <span class="seller-shipped-emoji" aria-hidden="true"><?= h((string) $r['item_emoji']) ?></span>
                          <?php endif; ?>
                          <span><?= h((string) ($r['item_name'] ?? '—')) ?></span>
                        </div>
                        <?php if ($variant !== ''): ?>
                          <div class="seller-shipped-variant"><?= h($variant) ?></div>
                        <?php endif; ?>
                        <div class="seller-shipped-meta">
                          <span class="seller-orders-id-tag">Item #<?= (int) ($r['order_item_id'] ?? 0) ?></span>
                          <?php $pcat = trim((string) ($r['product_category'] ?? '')); ?>
                          <?php if ($pcat !== ''): ?>
                            <span class="seller-shipped-cat"><?= h(ucfirst($pcat)) ?></span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <span class="seller-orders-ref"><?= h((string) ($r['order_ref'] ?? '')) ?></span>
                        <div class="seller-shipped-order-id">Order #<?= $oid ?></div>
                        <div class="seller-shipped-pay"><?= h(ucfirst(str_replace('_', ' ', (string) ($r['payment_method'] ?? '—')))) ?></div>
                      </td>
                      <td>
                        <div class="seller-shipped-customer"><?= h((string) ($r['customer_name'] ?? 'Guest')) ?></div>
                        <div class="seller-shipped-email"><?= h((string) ($r['customer_email'] ?? '—')) ?></div>
                      </td>
                      <td class="seller-shipped-cell-qty">
                        <strong><?= (int) ($r['line_qty'] ?? 1) ?></strong>
                        <span class="seller-shipped-times">×</span>
                        <span>₹<?= number_format((int) ($r['line_price'] ?? 0)) ?></span>
                        <div class="seller-shipped-line-total">Line: ₹<?= number_format($lineTotal) ?></div>
                      </td>
                      <td>
                        <span class="seller-status-chip <?= h(seller_shipped_status_chip($st)) ?>"><?= h(seller_shipped_status_label($st)) ?></span>
                      </td>
                      <td class="seller-shipped-cell-date"><?= h(seller_shipped_format_dt((string) ($r['order_placed_at'] ?? ''))) ?></td>
                      <td>
                        <a class="seller-edit-btn seller-order-actions__link" href="order-details.php?id=<?= $oid ?>" aria-label="Order detail" title="Order detail">
                          <svg class="seller-details-icon" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M9 4.46A9.8 9.8 0 0 1 12 4c4.182 0 7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296A14.5 14.5 0 0 1 5 6.821"></path><path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path></g></svg>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($rows === []): ?>
                    <tr>
                      <td colspan="7">
                        <div class="seller-orders-empty">
                          <div class="seller-orders-empty__icon" aria-hidden="true">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18h2"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
                          </div>
                          <h3 class="seller-orders-empty__title">No shipped lines</h3>
                          <p class="seller-orders-empty__text">Jab aap orders ko <strong>Shipped</strong> ya aage ke status par le jayenge, unki product lines yahan dikhengi. Pehle <a href="orders.php">Orders</a> se fulfilment update karein.</p>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <tr id="sellerShippedNoMatchRow" class="seller-orders-no-match-row" style="display:none">
                      <td colspan="7" class="seller-orders-no-match-cell">
                        <div class="seller-orders-no-match-inner">
                          <span class="seller-orders-no-match-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                          </span>
                          <div>
                            <strong>No matches</strong>
                            <p>Try another keyword — product name, order ref, customer, or email.</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'shipped-products.php';
            $paginationTotal = $totalLines;
            $paginationPage = $listPage;
            $paginationPerPage = $listPerPage;
            $paginationTotalPages = $totalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
          </div>
        </div>

<script>
  (function () {
    var searchInput = document.getElementById('sellerShippedSearch');
    if (!searchInput) return;
    var rowEls = document.querySelectorAll('tr.seller-shipped-row');
    var noMatch = document.getElementById('sellerShippedNoMatchRow');
    function run() {
      var q = (searchInput.value || '').trim().toLowerCase();
      var words = q.split(/\s+/).filter(Boolean);
      var any = false;
      rowEls.forEach(function (tr) {
        var hay = (tr.getAttribute('data-shipped-search') || '').toLowerCase();
        var show = words.length === 0 || words.every(function (w) { return hay.indexOf(w) !== -1; });
        tr.style.display = show ? '' : 'none';
        if (show) any = true;
      });
      if (noMatch) {
        noMatch.style.display = (words.length > 0 && !any) ? '' : 'none';
      }
    }
    searchInput.addEventListener('input', run);
    searchInput.addEventListener('search', run);
  })();
</script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
