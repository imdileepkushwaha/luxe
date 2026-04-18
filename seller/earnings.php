<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_finance.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Earnings';
$activeNav = 'earnings';

$summary = seller_finance_summary($pdo, (int) $seller['id']);
$recentOrders = seller_finance_recent_delivered_orders($pdo, (int) $seller['id'], 12);
$withdraws = seller_finance_withdraw_requests($pdo, (int) $seller['id'], 8);

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Earnings dashboard</h1>
        </div>

        <div class="seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Delivered earnings</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['delivered_total']) ?></div>
              <div class="seller-kpi-card__hint">Total earnings from delivered orders</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Withdrawable balance</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['withdrawable_balance']) ?></div>
              <div class="seller-kpi-card__hint">Available to request withdrawal</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="m17 7-5-5-5 5"/><path d="M5 17h14"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Pending withdrawals</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['pending_withdraw_total']) ?></div>
              <div class="seller-kpi-card__hint">Requests currently under review</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">In pipeline</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['pipeline_total']) ?></div>
              <div class="seller-kpi-card__hint">Processing + shipped orders value</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 6 13.5 14.5 8.5 9.5 2 16"/><polyline points="16 6 22 6 22 12"/></svg>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Recent delivered orders (earnings)</h2>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Order ref</th>
                    <th>Date</th>
                    <th>Items sold</th>
                    <th>Your earnings</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentOrders as $row): ?>
                    <tr>
                      <td><strong><?= h((string) $row['order_ref']) ?></strong></td>
                      <td><?= h((string) $row['created_at']) ?></td>
                      <td><?= (int) ($row['total_qty'] ?? 0) ?></td>
                      <td>Rs <?= number_format((int) ($row['seller_total'] ?? 0)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($recentOrders === []): ?>
                    <tr><td colspan="4">No delivered order earnings yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Recent withdraw requests</h2>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Request ID</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Requested at</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($withdraws as $w): ?>
                    <tr>
                      <td>#WR<?= (int) ($w['id'] ?? 0) ?></td>
                      <td>Rs <?= number_format((int) ($w['amount'] ?? 0)) ?></td>
                      <td><?= h((string) ($w['method'] ?? '-')) ?></td>
                      <td><span class="seller-status-chip seller-status-chip--<?= h(strtolower((string) ($w['status'] ?? 'pending'))) ?>"><?= h((string) ($w['status'] ?? 'pending')) ?></span></td>
                      <td><?= h((string) ($w['requested_at'] ?? '-')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($withdraws === []): ?>
                    <tr><td colspan="5">No withdraw requests yet.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
