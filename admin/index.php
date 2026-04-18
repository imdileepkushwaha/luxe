<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

$usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$sellersCount = (int) $pdo->query('SELECT COUNT(*) FROM seller_users WHERE is_active = 1')->fetchColumn();
$ordersCount = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$revenue = (int) $pdo->query('SELECT COALESCE(SUM(total_amount), 0) FROM orders')->fetchColumn();
$processingCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();

$buyersStmt = $pdo->query('SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL');
$distinctBuyers = (int) $buyersStmt->fetchColumn();
$conversionPct = $usersCount > 0 ? round(100 * $distinctBuyers / $usersCount, 1) : 0.0;

$statusRows = $pdo->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status')->fetchAll();
$statusMap = [];
foreach ($statusRows as $r) {
    $statusMap[(string) $r['status']] = (int) $r['c'];
}

$monthlyStmt = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(total_amount), 0) AS total
     FROM orders
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY ym ASC"
);
$monthlyRaw = $monthlyStmt->fetchAll();
$byMonth = [];
foreach ($monthlyRaw as $row) {
    $byMonth[(string) $row['ym']] = (int) $row['total'];
}

$labels = [];
$revenueSeries = [];
for ($i = 5; $i >= 0; $i--) {
    $d = new DateTimeImmutable('first day of this month');
    $d = $d->modify("-{$i} months");
    $key = $d->format('Y-m');
    $labels[] = $d->format('M');
    $revenueSeries[] = $byMonth[$key] ?? 0;
}

$donutLabels = ['Delivered', 'Shipped', 'Processing', 'Other'];
$donutValues = [
    $statusMap['delivered'] ?? 0,
    $statusMap['shipped'] ?? 0,
    $statusMap['processing'] ?? 0,
    max(0, $ordersCount - (($statusMap['delivered'] ?? 0) + ($statusMap['shipped'] ?? 0) + ($statusMap['processing'] ?? 0))),
];

$pipelineTotal = max(1, $ordersCount);
$cntDel = (int) ($statusMap['delivered'] ?? 0);
$cntShip = (int) ($statusMap['shipped'] ?? 0);
$cntProc = (int) ($statusMap['processing'] ?? 0);
$cntOther = max(0, $ordersCount - $cntDel - $cntShip - $cntProc);
$pipeLead = (int) round(100 * $cntProc / $pipelineTotal);
$pipeProposal = (int) round(100 * $cntShip / $pipelineTotal);
$pipeSales = (int) round(100 * $cntDel / $pipelineTotal);
$pipeWon = (int) round(100 * $cntOther / $pipelineTotal);

$recentOrders = $pdo->query(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.created_at,
            u.first_name, u.last_name, u.email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC
     LIMIT 8"
)->fetchAll();

$adminNameParts = preg_split('/\s+/', trim((string) ($admin['full_name'] ?? ''))) ?: [];
$adminFirst = (string) ($adminNameParts[0] ?? '');
if ($adminFirst === '') {
    $adminFirst = 'Admin';
}

$weekStart = (new DateTimeImmutable('today'))->modify('-6 days')->format('Y-m-d 00:00:00');
$wkStmt = $pdo->prepare('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM orders WHERE created_at >= ? GROUP BY DATE(created_at)');
$wkStmt->execute([$weekStart]);
$wkMap = [];
foreach ($wkStmt->fetchAll() as $wr) {
    $wkMap[substr((string) $wr['d'], 0, 10)] = (int) $wr['c'];
}
$weekLabels = [];
$weekCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $d = (new DateTimeImmutable('today'))->modify("-{$i} days");
    $weekLabels[] = $d->format('D');
    $weekCounts[] = $wkMap[$d->format('Y-m-d')] ?? 0;
}

$recentUsers = $pdo->query(
    'SELECT id, first_name, last_name, email, created_at FROM users ORDER BY id DESC LIMIT 6'
)->fetchAll();

$attentionOrders = $pdo->query(
    "SELECT o.id, o.order_ref, o.status, o.created_at, u.first_name, u.last_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.status = 'processing'
     ORDER BY o.created_at ASC
     LIMIT 5"
)->fetchAll();

$revThisStmt = $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0) FROM orders
     WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
);
$revThisMonth = (int) $revThisStmt->fetchColumn();
$revLastStmt = $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0) FROM orders
     WHERE YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
     AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
);
$revLastMonth = (int) $revLastStmt->fetchColumn();
$revTrendPct = null;
if ($revLastMonth > 0) {
    $revTrendPct = round(100 * ($revThisMonth - $revLastMonth) / $revLastMonth, 2);
} elseif ($revThisMonth > 0) {
    $revTrendPct = 100.0;
}

$ordThisStmt = $pdo->query(
    "SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())"
);
$ordersThisMonth = (int) $ordThisStmt->fetchColumn();
$ordLastStmt = $pdo->query(
    "SELECT COUNT(*) FROM orders WHERE YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
     AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
);
$ordersLastMonth = (int) $ordLastStmt->fetchColumn();
$ordTrendPct = null;
if ($ordersLastMonth > 0) {
    $ordTrendPct = round(100 * ($ordersThisMonth - $ordersLastMonth) / $ordersLastMonth, 2);
} elseif ($ordersThisMonth > 0) {
    $ordTrendPct = 100.0;
}

function admin_order_status_class(string $status): string
{
    return match (strtolower($status)) {
        'delivered' => 'admin-status admin-status--delivered',
        'shipped' => 'admin-status admin-status--shipped',
        'processing' => 'admin-status admin-status--processing',
        'cancelled' => 'admin-status admin-status--cancelled',
        default => 'admin-status admin-status--processing',
    };
}

function admin_fake_probability(string $status): int
{
    return match (strtolower($status)) {
        'delivered' => 100,
        'shipped' => 72,
        'processing' => 45,
        'cancelled' => 0,
        default => 38,
    };
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Dashboard</h1>
          <div class="admin-page-head__actions">
            <span class="admin-date-pill" title="Date range">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <?= h((new DateTimeImmutable('-28 days'))->format('d M y')) ?> - <?= h((new DateTimeImmutable())->format('d M y')) ?>
            </span>
            <button type="button" class="admin-ghost-btn" title="Export" aria-label="Export">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </button>
            <button type="button" class="admin-ghost-btn" title="Refresh" aria-label="Refresh" onclick="location.reload()">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
            </button>
          </div>
        </div>

        <section class="admin-hero" aria-labelledby="adminHeroTitle">
          <div class="admin-hero__text">
            <h2 id="adminHeroTitle">Welcome back, <?= h($adminFirst) ?></h2>
            <p><?= (int) $usersCount ?> registered users · <?= (int) $ordersCount ?> orders · <?= $processingCount ?> in processing right now.</p>
          </div>
          <div class="admin-hero__actions">
            <a class="admin-btn admin-btn--primary" href="users.php">Users</a>
            <a class="admin-btn admin-btn--ghost-light" href="orders.php">All orders</a>
          </div>
        </section>

        <div class="admin-grid admin-grid--stats">
          <div class="admin-card admin-stat">
            <div>
              <div class="admin-stat__label">Total orders</div>
              <div class="admin-stat__value"><?= (int) $ordersCount ?></div>
              <?php if ($ordTrendPct !== null): ?>
                <div class="admin-stat__delta<?= $ordTrendPct >= 0 ? ' admin-stat__delta--up' : ' admin-stat__delta--down' ?>"><?= $ordTrendPct >= 0 ? '+' : '' ?><?= h((string) $ordTrendPct) ?>% from last month</div>
              <?php else: ?>
                <div class="admin-stat__delta admin-stat__delta--muted">Month-over-month trend</div>
              <?php endif; ?>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat">
            <div>
              <div class="admin-stat__label">Revenue (store)</div>
              <div class="admin-stat__value">₹<?= number_format($revenue) ?></div>
              <?php if ($revTrendPct !== null): ?>
                <div class="admin-stat__delta<?= $revTrendPct >= 0 ? ' admin-stat__delta--up' : ' admin-stat__delta--down' ?>"><?= $revTrendPct >= 0 ? '+' : '' ?><?= h((string) $revTrendPct) ?>% from last month</div>
              <?php else: ?>
                <div class="admin-stat__delta admin-stat__delta--muted">This month vs last</div>
              <?php endif; ?>
            </div>
            <div class="admin-stat__icon admin-stat__icon--red">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat">
            <div>
              <div class="admin-stat__label">Processing queue</div>
              <div class="admin-stat__value"><?= (int) $processingCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Orders awaiting fulfilment</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--purple">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat">
            <div>
              <div class="admin-stat__label">Total users</div>
              <div class="admin-stat__value"><?= (int) $usersCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--up"><?= h((string) $conversionPct) ?>% placed an order</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--pink">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat">
            <div>
              <div class="admin-stat__label">Active sellers</div>
              <div class="admin-stat__value"><?= (int) $sellersCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Seller panel accounts</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
          </div>
        </div>

        <div class="admin-dash-grid-3">
          <div class="admin-card">
            <div class="admin-card__head">
              <h2 class="admin-card__title">Orders (7 days)</h2>
              <label class="admin-card-filter"><span class="visually-hidden">Range</span>
                <select aria-label="Chart range" disabled><option>This week</option></select>
              </label>
            </div>
            <div class="admin-card__body admin-card__body--flush">
              <div class="admin-chart-wrap admin-chart-wrap--sm">
                <canvas id="chartWeek" aria-label="Orders per day"></canvas>
              </div>
              <p class="admin-metric-line"><strong><?= array_sum($weekCounts) ?></strong> orders in the last 7 days</p>
            </div>
          </div>
          <div class="admin-card">
            <div class="admin-card__head">
              <h2 class="admin-card__title">Revenue (6 months)</h2>
              <label class="admin-card-filter"><span class="visually-hidden">Year</span>
                <select aria-label="Year" disabled><option><?= h((new DateTimeImmutable())->format('Y')) ?></option></select>
              </label>
            </div>
            <div class="admin-card__body admin-card__body--flush">
              <div class="admin-chart-wrap admin-chart-wrap--sm">
                <canvas id="chartRevenue" aria-label="Revenue by month"></canvas>
              </div>
              <p class="admin-metric-line"><strong>₹<?= number_format($revenue) ?></strong> lifetime revenue</p>
            </div>
          </div>
          <div class="admin-card">
            <div class="admin-card__head">
              <h2 class="admin-card__title">Order status mix</h2>
            </div>
            <div class="admin-card__body">
              <div class="admin-chart-wrap admin-chart-wrap--sm">
                <canvas id="chartTraffic" aria-label="Status distribution"></canvas>
              </div>
              <ul class="admin-legend" id="trafficLegend"></ul>
            </div>
          </div>
        </div>

        <?php $listOrders = array_slice($recentOrders, 0, 6); ?>
        <div class="admin-dash-lists">
          <div class="admin-card">
            <div class="admin-card__head">
              <h2 class="admin-card__title">Recent transactions</h2>
              <a class="admin-view-all" href="orders.php">View all</a>
            </div>
            <div class="admin-card__body">
              <div class="admin-list-feed">
                <?php foreach ($listOrders as $o): ?>
                  <?php
                  $owner = trim(((string) ($o['first_name'] ?? '')) . ' ' . ((string) ($o['last_name'] ?? '')));
                  if ($owner === '') {
                      $owner = 'Guest';
                  }
                  $ini = strtoupper(substr($owner, 0, 2));
                  if ($ini === '') {
                      $ini = '?';
                  }
                  ?>
                  <div class="admin-list-feed__row">
                    <div class="admin-avatar-sm"><?= h($ini) ?></div>
                    <div class="admin-list-feed__body">
                      <div class="admin-list-feed__title"><?= h((string) $o['order_ref']) ?></div>
                      <div class="admin-list-feed__meta"><?= h($owner) ?> · <?= h((new DateTimeImmutable((string) $o['created_at']))->format('M j, Y')) ?></div>
                      <span class="<?= admin_order_status_class((string) $o['status']) ?>"><?= h((string) $o['status']) ?></span>
                    </div>
                    <div class="admin-list-feed__side">₹<?= number_format((int) $o['total_amount']) ?></div>
                  </div>
                <?php endforeach; ?>
                <?php if ($listOrders === []): ?>
                  <p style="margin:0;color:var(--admin-text-muted);font-size:0.9rem">No orders yet.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="admin-card">
            <div class="admin-card__head">
              <h2 class="admin-card__title">Recently registered</h2>
              <a class="admin-view-all" href="users.php">View all</a>
            </div>
            <div class="admin-card__body">
              <div class="admin-list-feed">
                <?php foreach ($recentUsers as $ru): ?>
                  <?php
                  $un = trim(((string) ($ru['first_name'] ?? '')) . ' ' . ((string) ($ru['last_name'] ?? '')));
                  if ($un === '') {
                      $un = 'User';
                  }
                  $uini = strtoupper(substr($un, 0, 2));
                  ?>
                  <div class="admin-list-feed__row">
                    <div class="admin-avatar-sm"><?= h($uini) ?></div>
                    <div class="admin-list-feed__body">
                      <div class="admin-list-feed__title"><?= h($un) ?></div>
                      <div class="admin-list-feed__meta"><?= h((string) ($ru['email'] ?? '')) ?></div>
                    </div>
                    <div class="admin-list-feed__side"><?= h((new DateTimeImmutable((string) $ru['created_at']))->format('M j')) ?></div>
                  </div>
                <?php endforeach; ?>
                <?php if ($recentUsers === []): ?>
                  <p style="margin:0;color:var(--admin-text-muted);font-size:0.9rem">No users yet.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="admin-card">
            <div class="admin-card__head">
              <h2 class="admin-card__title">Needs attention</h2>
              <a class="admin-view-all" href="orders.php">Open queue</a>
            </div>
            <div class="admin-card__body">
              <div class="admin-list-feed">
                <?php foreach ($attentionOrders as $ao): ?>
                  <?php
                  $an = trim(((string) ($ao['first_name'] ?? '')) . ' ' . ((string) ($ao['last_name'] ?? '')));
                  if ($an === '') {
                      $an = 'Guest';
                  }
                  $aini = strtoupper(substr($an, 0, 2));
                  ?>
                  <div class="admin-list-feed__row">
                    <div class="admin-avatar-sm"><?= h($aini) ?></div>
                    <div class="admin-list-feed__body">
                      <div class="admin-list-feed__title"><?= h((string) $ao['order_ref']) ?></div>
                      <div class="admin-list-feed__meta">Processing · <?= h((new DateTimeImmutable((string) $ao['created_at']))->format('M j, Y')) ?></div>
                      <a class="admin-list-feed__action" href="orders.php">Review →</a>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php if ($attentionOrders === []): ?>
                  <p style="margin:0;color:var(--admin-text-muted);font-size:0.9rem">No processing orders. You’re all caught up.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
  var accent = getComputedStyle(document.documentElement).getPropertyValue('--admin-accent').trim() || '#e53935';
  var orange = '#fb923c';

  var wLabels = <?= json_encode($weekLabels, JSON_THROW_ON_ERROR) ?>;
  var wCounts = <?= json_encode($weekCounts, JSON_THROW_ON_ERROR) ?>;
  var wctx = document.getElementById('chartWeek');
  if (wctx) {
    new Chart(wctx, {
      type: 'bar',
      data: {
        labels: wLabels,
        datasets: [{
          label: 'Orders',
          data: wCounts,
          backgroundColor: orange,
          borderRadius: 6,
          barThickness: 22
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { color: '#6b7280', font: { size: 11 } } },
          y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { color: '#6b7280', precision: 0 } }
        }
      }
    });
  }

  var labels = <?= json_encode($labels, JSON_THROW_ON_ERROR) ?>;
  var revenue = <?= json_encode($revenueSeries, JSON_THROW_ON_ERROR) ?>;
  var ctx = document.getElementById('chartRevenue');
  if (ctx) {
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Revenue (₹)',
          data: revenue,
          backgroundColor: accent,
          borderRadius: 6,
          barThickness: 24
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false }, ticks: { color: '#6b7280' } },
          y: { grid: { color: '#f3f4f6' }, ticks: { color: '#6b7280' } }
        }
      }
    });
  }

  var dLabels = <?= json_encode($donutLabels, JSON_THROW_ON_ERROR) ?>;
  var dVals = <?= json_encode($donutValues, JSON_THROW_ON_ERROR) ?>;
  var colors = ['#3b82f6', '#eab308', accent, '#a855f7'];
  var sumD = dVals.reduce(function (a, b) { return a + b; }, 0);
  if (sumD === 0) {
    dLabels = ['No data'];
    dVals = [1];
    colors = ['#e5e7eb'];
  }
  var tctx = document.getElementById('chartTraffic');
  if (tctx) {
    new Chart(tctx, {
      type: 'doughnut',
      data: {
        labels: dLabels,
        datasets: [{ data: dVals, backgroundColor: colors, borderWidth: 0 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: { legend: { display: false } }
      }
    });
  }

  var leg = document.getElementById('trafficLegend');
  if (leg) {
    var total = dVals.reduce(function (a, b) { return a + b; }, 0) || 1;
    dLabels.forEach(function (lbl, i) {
      var pct = sumD === 0 && lbl === 'No data' ? 0 : Math.round((dVals[i] / total) * 100);
      var li = document.createElement('li');
      li.innerHTML = '<span class="admin-legend__left"><span class="admin-legend__dot" style="background:' + colors[i] + '"></span>' + lbl + '</span><span>' + pct + '% · ' + (sumD === 0 ? '0' : dVals[i]) + '</span>';
      leg.appendChild(li);
    });
  }
})();
</script>
<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
