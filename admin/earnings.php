<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_pagination.php';
require_once __DIR__ . '/../includes/site_settings.php';
require_once __DIR__ . '/../includes/cart_session.php';

$pdo = db();
$admin = admin_require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'backfill_platform_fees') {
    $n = orders_backfill_platform_fee_on_orders($pdo);
    $_SESSION['admin_earnings_flash'] = [
        'ok' => true,
        'text' => $n > 0
            ? 'Missing platform fees filled for ' . $n . ' order(s). Totals were checked against item lines + current fee (₹' . number_format(site_platform_fee_rupees($pdo)) . ') + shipping rules.'
            : 'No orders matched. Fees stay at 0 when the order total does not match current fee + shipping math, or the fee setting is 0.',
    ];
    header('Location: earnings.php');
    exit;
}

$flash = null;
if (isset($_SESSION['admin_earnings_flash']) && is_array($_SESSION['admin_earnings_flash'])) {
    $flash = $_SESSION['admin_earnings_flash'];
    unset($_SESSION['admin_earnings_flash']);
}

$pageTitle = 'Platform earnings';
$activeNav = 'earnings';

$feePerOrderRupees = site_platform_fee_rupees($pdo);

$totalFeesStmt = $pdo->query('SELECT COALESCE(SUM(platform_fee_rupees), 0) FROM orders');
$totalPlatformFees = (int) $totalFeesStmt->fetchColumn();

$ordersWithFeeStmt = $pdo->query('SELECT COUNT(*) FROM orders WHERE platform_fee_rupees > 0');
$ordersWithFeeCount = (int) $ordersWithFeeStmt->fetchColumn();

$ordersMissingFeeStmt = $pdo->query('SELECT COUNT(*) FROM orders WHERE platform_fee_rupees = 0');
$ordersMissingFeeCount = (int) $ordersMissingFeeStmt->fetchColumn();

$monthFeesStmt = $pdo->query(
    "SELECT COALESCE(SUM(platform_fee_rupees), 0) FROM orders
     WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
);
$feesThisMonth = (int) $monthFeesStmt->fetchColumn();

['page' => $reqPage, 'perPage' => $perPage] = admin_pagination_read(25);
$pMeta = admin_pagination_resolve($ordersWithFeeCount, $reqPage, $perPage);
$page = $pMeta['page'];
$offset = $pMeta['offset'];
$perPage = $pMeta['perPage'];
$totalPages = $pMeta['totalPages'];

if ($ordersWithFeeCount === 0) {
    $rows = [];
} else {
    $rowsSt = $pdo->prepare(
        "SELECT o.id, o.order_ref, o.status, o.total_amount, o.platform_fee_rupees, o.payment_method, o.created_at,
                u.first_name, u.last_name, u.email
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.platform_fee_rupees > 0
         ORDER BY o.id DESC
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
function admin_earnings_fmt_created($raw): string
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
function admin_earnings_search_haystack(array $o, string $cust, string $email): string
{
    $fn = trim((string) ($o['first_name'] ?? ''));
    $ln = trim((string) ($o['last_name'] ?? ''));
    $parts = [
        (string) ($o['id'] ?? ''),
        (string) ($o['order_ref'] ?? ''),
        $cust,
        $fn,
        $ln,
        trim($fn . ' ' . $ln),
        $email,
        (string) ($o['status'] ?? ''),
        (string) ($o['total_amount'] ?? ''),
        (string) ($o['platform_fee_rupees'] ?? ''),
        (string) ($o['payment_method'] ?? ''),
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

function admin_order_status_class_earnings(string $status): string
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

        <div class="admin-earnings-page">
        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?> admin-earnings-flash" role="status">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Finance</span>
            <h1>Platform earnings</h1>
            <p class="admin-page-head__lede">Per-order platform fee revenue, recorded on each completed checkout.</p>
          </div>
          <div class="admin-page-head__actions">
            <span class="admin-earnings-fee-pill" title="Fee per order (Settings → platform fee)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              Current fee: ₹<?= number_format($feePerOrderRupees) ?> / order
            </span>
            <a class="admin-btn admin-btn--outline" href="orders.php">All orders</a>
            <a class="admin-btn admin-btn--outline" href="settings.php">Settings</a>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats admin-grid--stats--flow admin-earnings-kpi-grid" aria-label="Earnings summary">
          <div class="admin-card admin-stat admin-stat--stripe-violet admin-earnings-kpi">
            <div>
              <div class="admin-stat__label admin-earnings-kpi__label">Total collected</div>
              <div class="admin-stat__value">₹<?= number_format($totalPlatformFees) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">All time · stored on each order</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--purple" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-blue admin-earnings-kpi">
            <div>
              <div class="admin-stat__label admin-earnings-kpi__label">This month</div>
              <div class="admin-stat__value">₹<?= number_format($feesThisMonth) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Fees by order date (calendar month)</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-green admin-earnings-kpi">
            <div>
              <div class="admin-stat__label admin-earnings-kpi__label">Orders with fee</div>
              <div class="admin-stat__value"><?= number_format($ordersWithFeeCount) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Checkouts where fee &gt; 0</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--green" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-red admin-earnings-kpi">
            <div>
              <div class="admin-stat__label admin-earnings-kpi__label">Stored fee ₹0</div>
              <div class="admin-stat__value"><?= number_format($ordersMissingFeeCount) ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Often legacy rows; totals may still include fee</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--red" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
          </div>
        </div>

        <?php if ($feePerOrderRupees > 0 && $ordersMissingFeeCount > 0): ?>
        <div class="card admin-earnings-backfill-card">
          <div class="card-header admin-earnings-backfill-header">
            <div class="admin-earnings-backfill-head">
              <h2 class="card-title">Fix missing fee on past orders</h2>
              <p class="card-subtitle admin-earnings-backfill-sub">One-time match using today’s fee and shipping rules.</p>
            </div>
          </div>
          <div class="card-body">
            <p class="admin-earnings-backfill-detail">
              New checkouts already save the fee on each order. For older rows still at ₹0, you can run a match: we set
              <span class="admin-inline-code">platform_fee_rupees</span> to the <strong>current</strong> setting (₹<?= number_format($feePerOrderRupees) ?>)
              only when <strong>order total = items subtotal + that fee + delivery</strong> (standard / express / same-day), using
              <strong>today’s</strong> shipping rules. If the fee differed when the order was placed, or seller shipping changed, some orders may not match —
              temporarily adjust <span class="admin-inline-code">site_settings.platform_fee_rupees</span> if needed, or update rows manually.
            </p>
            <form method="post" class="admin-earnings-backfill-form" onsubmit="return confirm('Backfill platform fee on matching orders using current ₹<?= number_format($feePerOrderRupees) ?> fee and current shipping rules?');">
              <input type="hidden" name="action" value="backfill_platform_fees">
              <button type="submit" class="admin-btn admin-btn--primary">Match totals and fill missing fees</button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <div class="card admin-earnings-table-card">
          <div class="card-header admin-earnings-table-header">
            <div class="admin-earnings-table-head">
              <div class="admin-earnings-table-head-text">
                <h2 class="card-title">Fee by order</h2>
                <p class="card-subtitle admin-earnings-table-sub">
                  <?= number_format($ordersWithFeeCount) ?> order<?= $ordersWithFeeCount === 1 ? '' : 's' ?> with a recorded fee · Search filters this page only. Use pagination for older rows.
                </p>
              </div>
              <?php if ($ordersWithFeeCount > 0): ?>
                <label class="admin-users-search-wrap admin-earnings-search-wrap" for="adminEarningsSearch">
                  <span class="admin-users-search-icon admin-earnings-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminEarningsSearch"
                    class="admin-users-search-input admin-earnings-search-input"
                    placeholder="Search ref, customer, email, status, amount…"
                    autocomplete="off"
                    aria-label="Search fee rows"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($ordersWithFeeCount === 0): ?>
              <p class="admin-empty-hint admin-empty-hint--boxed">No orders with a recorded platform fee yet.</p>
            <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Order ref</th>
                    <th>Customer</th>
                    <th class="admin-table__cell-email">Email</th>
                    <th class="admin-table__th-narrow">Status</th>
                    <th class="admin-table__th-money">Order total</th>
                    <th class="admin-table__th-money">Platform fee</th>
                    <th class="admin-table__th-narrow">Payment</th>
                    <th>Placed</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $o): ?>
                    <?php
                    $fn = trim((string) ($o['first_name'] ?? ''));
                    $ln = trim((string) ($o['last_name'] ?? ''));
                    $cust = trim($fn . ' ' . $ln);
                    if ($cust === '') {
                        $cust = 'Guest';
                    }
                    $ini = strtoupper(substr($fn, 0, 1) . substr($ln, 0, 1));
                    if ($ini === '') {
                        $ini = strtoupper(substr(preg_replace('/\s+/', '', $cust), 0, 2));
                    }
                    if ($ini === '') {
                        $ini = '?';
                    }
                    $email = (string) ($o['email'] ?? '');
                    $emailDisp = $email !== '' ? $email : '—';
                    $hay = admin_earnings_search_haystack($o, $cust, $email);
                    ?>
                    <tr class="admin-earnings-row" data-earnings-search="<?= h($hay) ?>">
                      <td><strong><?= h((string) $o['order_ref']) ?></strong></td>
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <span class="admin-earnings-cust-name"><?= h($cust) ?></span>
                        </div>
                      </td>
                      <td class="admin-table__cell-email"><span class="admin-earnings-email" title="<?= h($emailDisp) ?>"><?= h($emailDisp) ?></span></td>
                      <td><span class="<?= admin_order_status_class_earnings((string) $o['status']) ?>"><?= h((string) $o['status']) ?></span></td>
                      <td class="admin-table__td-money">₹<?= number_format((int) $o['total_amount']) ?></td>
                      <td class="admin-table__td-money">₹<?= number_format((int) $o['platform_fee_rupees']) ?></td>
                      <td><span class="admin-badge admin-badge--muted"><?= h((string) $o['payment_method']) ?></span></td>
                      <td class="admin-table__td-muted"><?= h(admin_earnings_fmt_created($o['created_at'] ?? null)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="adminEarningsNoMatchRow" class="admin-earnings-no-match-row">
                    <td colspan="8">
                      <div class="admin-earnings-no-match">
                        <strong class="admin-earnings-no-match__title">No matches</strong>
                        <p class="admin-earnings-no-match__text">Try another keyword — ref, name, email, status, or amount (this page only).</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'earnings.php';
            $paginationTotal = $ordersWithFeeCount;
            $paginationPage = $page;
            $paginationPerPage = $perPage;
            $paginationTotalPages = $totalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            <script>
            (function () {
              var searchInput = document.getElementById('adminEarningsSearch');
              if (!searchInput) return;
              var rows = document.querySelectorAll('tr.admin-earnings-row');
              var noMatchRow = document.getElementById('adminEarningsNoMatchRow');

              function applySearch() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                rows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-earnings-search') || '').toLowerCase();
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

              searchInput.addEventListener('input', applySearch);
              searchInput.addEventListener('search', applySearch);
            })();
            </script>
            <?php endif; ?>
          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
