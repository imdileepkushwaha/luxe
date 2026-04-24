<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_pagination.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Orders';
$activeNav = 'orders';

['page' => $reqPage, 'perPage' => $perPage] = admin_pagination_read(25);
$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();

$orderStatusMap = [];
foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $orderStatusMap[strtolower(trim((string) ($row['status'] ?? '')))] = (int) ($row['c'] ?? 0);
}
$kpiProcessing = (int) ($orderStatusMap['processing'] ?? 0);
$kpiShipped = (int) ($orderStatusMap['shipped'] ?? 0);
$kpiDelivered = (int) ($orderStatusMap['delivered'] ?? 0);

$pMeta = admin_pagination_resolve($totalOrders, $reqPage, $perPage);
$page = $pMeta['page'];
$offset = $pMeta['offset'];
$perPage = $pMeta['perPage'];
$totalPages = $pMeta['totalPages'];

$ordersSt = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.shipping_address, o.created_at,
            u.first_name, u.last_name, u.email,
            CASE
              WHEN LOWER(TRIM(COALESCE(o.status, ''))) = 'delivered'
                   AND EXISTS (
                     SELECT 1 FROM user_return_requests ur
                     WHERE LOWER(TRIM(COALESCE(ur.status, ''))) <> 'rejected'
                       AND (ur.order_id = o.id
                            OR (COALESCE(ur.order_id, 0) = 0 AND o.order_ref <> '' AND ur.order_ref = o.order_ref))
                   )
              THEN 'return'
              ELSE o.status
            END AS admin_orders_row_status
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC
     LIMIT ? OFFSET ?"
);
$ordersSt->bindValue(1, $perPage, PDO::PARAM_INT);
$ordersSt->bindValue(2, $offset, PDO::PARAM_INT);
$ordersSt->execute();
$orders = $ordersSt->fetchAll();

function admin_order_status_class_orders(string $status): string
{
    return match (strtolower($status)) {
        'delivered' => 'admin-status admin-status--delivered',
        'return' => 'admin-status admin-status--open',
        'shipped' => 'admin-status admin-status--shipped',
        'processing' => 'admin-status admin-status--processing',
        'cancelled' => 'admin-status admin-status--cancelled',
        default => 'admin-status admin-status--processing',
    };
}

/**
 * @param mixed $raw
 */
function admin_orders_fmt_created($raw): string
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
 * @param array<string, mixed> $o
 */
function admin_orders_search_haystack(array $o, string $cust, string $emailDisp): string
{
    $parts = [
        (string) ($o['id'] ?? ''),
        (string) ($o['order_ref'] ?? ''),
        $cust,
        (string) ($o['email'] ?? ''),
        $emailDisp !== '—' ? $emailDisp : '',
        (string) ($o['admin_orders_row_status'] ?? $o['status'] ?? ''),
        (string) ($o['total_amount'] ?? ''),
        (string) ($o['payment_method'] ?? ''),
        (string) ($o['shipping_address'] ?? ''),
        (string) ($o['created_at'] ?? ''),
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

        <div class="admin-orders-page">
        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Shop</span>
            <h1>Orders</h1>
            <p class="admin-page-head__lede">Purchase orders across the store — newest first.</p>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats admin-grid--stats--flow admin-orders-kpi-grid" aria-label="Order summary">
          <div class="admin-card admin-stat admin-stat--stripe-blue admin-orders-kpi">
            <div>
              <div class="admin-stat__label admin-orders-kpi__label">All orders</div>
              <div class="admin-stat__value"><?= (int) $totalOrders ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Store-wide total</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-green admin-orders-kpi">
            <div>
              <div class="admin-stat__label admin-orders-kpi__label">Needs confirm</div>
              <div class="admin-stat__value"><?= (int) $kpiProcessing ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Processing — action required</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--green" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-violet admin-orders-kpi">
            <div>
              <div class="admin-stat__label admin-orders-kpi__label">In transit</div>
              <div class="admin-stat__value"><?= (int) $kpiShipped ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Shipped / out for delivery</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--purple" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 17h4V5H2v12h3"/><path d="M20 17h2v-3.34a4 4 0 0 0-1.17-2.83L19 9"/><path d="M14 17h1"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-indigo admin-orders-kpi">
            <div>
              <div class="admin-stat__label admin-orders-kpi__label">Delivered</div>
              <div class="admin-stat__value"><?= (int) $kpiDelivered ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Completed deliveries</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--indigo" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
          </div>
        </div>

        <div class="card admin-orders-card">
          <div class="card-header admin-orders-card-header">
            <div class="admin-orders-card-head">
              <div class="admin-orders-card-head-text">
                <h2 class="card-title">Purchase orders</h2>
                <p class="card-subtitle admin-orders-card-sub"><?= (int) $totalOrders ?> order<?= $totalOrders === 1 ? '' : 's' ?> total · Search filters this page only. Scroll sideways on small screens.</p>
              </div>
              <?php if ($orders !== []): ?>
                <label class="admin-orders-search-wrap" for="adminOrdersSearch">
                  <span class="admin-orders-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminOrdersSearch"
                    class="admin-orders-search-input"
                    placeholder="Search ref, customer, email, status, total…"
                    autocomplete="off"
                    aria-label="Search orders"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($orders === []): ?>
              <p class="admin-empty-hint admin-empty-hint--boxed">No orders yet.</p>
            <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table admin-orders-table">
                <thead>
                  <tr>
                    <th>Order ref</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th class="admin-table__th-money">Total</th>
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
                    $ini = strtoupper(substr($cust, 0, 2));
                    if ($ini === '') {
                        $ini = '?';
                    }
                    $emailDisp = trim((string) ($o['email'] ?? ''));
                    if ($emailDisp === '') {
                        $emailDisp = '—';
                    }
                    $rowStatus = (string) ($o['admin_orders_row_status'] ?? $o['status'] ?? '');
                    $hay = admin_orders_search_haystack($o, $cust, $emailDisp);
                    ?>
                    <tr class="admin-orders-row" data-orders-search="<?= h($hay) ?>">
                      <td><strong class="admin-orders-ref"><?= h((string) $o['order_ref']) ?></strong></td>
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <span class="admin-orders-cust-name"><?= h($cust) ?></span>
                        </div>
                      </td>
                      <td class="admin-table__cell-email"><span class="admin-orders-email"><?= h($emailDisp) ?></span></td>
                      <td><span class="<?= admin_order_status_class_orders($rowStatus) ?>"><?= h($rowStatus) ?></span></td>
                      <td class="admin-table__td-money">₹<?= number_format((int) $o['total_amount']) ?></td>
                      <td><span class="admin-badge admin-badge--muted"><?= h((string) $o['payment_method']) ?></span></td>
                      <td class="admin-table__cell-shipping"><span class="admin-orders-ship"><?= h((string) $o['shipping_address']) ?></span></td>
                      <td class="admin-table__td-muted"><?= h(admin_orders_fmt_created($o['created_at'] ?? null)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="adminOrdersNoMatchRow" class="admin-orders-no-match-row">
                    <td colspan="8">
                      <div class="admin-orders-no-match">
                        <strong class="admin-orders-no-match__title">No matches</strong>
                        <p class="admin-orders-no-match__text">Try another keyword — order ref, name, email, status, or amount.</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'orders.php';
            $paginationTotal = $totalOrders;
            $paginationPage = $page;
            $paginationPerPage = $perPage;
            $paginationTotalPages = $totalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            <script>
            (function () {
              var searchInput = document.getElementById('adminOrdersSearch');
              if (!searchInput) return;
              var rows = document.querySelectorAll('tr.admin-orders-row');
              var noMatchRow = document.getElementById('adminOrdersNoMatchRow');
              function apply() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                rows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-orders-search') || '').toLowerCase();
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
              searchInput.addEventListener('input', apply);
              searchInput.addEventListener('search', apply);
            })();
            </script>
            <?php endif; ?>
          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
