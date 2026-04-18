<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_finance.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Transactions';
$activeNav = 'transactions';

$summary = seller_finance_summary($pdo, (int) $seller['id']);

$creditSt = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.created_at, SUM(oi.price * oi.qty) AS amount
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     INNER JOIN products p ON p.id = oi.product_id
     WHERE p.seller_id = ?
       AND o.status = 'delivered'
     GROUP BY o.id, o.order_ref, o.created_at
     ORDER BY o.id DESC"
);
$creditSt->execute([(int) $seller['id']]);
$credits = $creditSt->fetchAll();

$debitSt = $pdo->prepare(
    "SELECT id, amount, method, account_ref, status, requested_at, reviewed_at, note, rejection_reason
     FROM seller_withdraw_requests
     WHERE seller_id = ?
     ORDER BY id DESC"
);
$debitSt->execute([(int) $seller['id']]);
$debits = $debitSt->fetchAll();

$txns = [];
foreach ($credits as $c) {
    $txns[] = [
        'type' => 'credit',
        'title' => 'Order earning',
        'reference' => (string) ($c['order_ref'] ?? '-'),
        'status' => 'delivered',
        'amount' => (int) ($c['amount'] ?? 0),
        'date' => (string) ($c['created_at'] ?? ''),
        'meta' => 'Credited from delivered order',
    ];
}
foreach ($debits as $d) {
    $status = strtolower((string) ($d['status'] ?? 'pending'));
    $effectiveDate = (string) ($d['reviewed_at'] ?: $d['requested_at']);
    $meta = 'Withdraw request via ' . (string) ($d['method'] ?? 'bank');
    if ($status === 'rejected' && trim((string) ($d['rejection_reason'] ?? '')) !== '') {
        $meta .= ' | Reason: ' . (string) $d['rejection_reason'];
    }
    $txns[] = [
        'type' => 'debit',
        'title' => 'Withdraw request',
        'reference' => 'WR' . (int) ($d['id'] ?? 0),
        'status' => $status,
        'amount' => (int) ($d['amount'] ?? 0),
        'date' => $effectiveDate,
        'meta' => $meta,
    ];
}

usort($txns, static function (array $a, array $b): int {
    return strtotime((string) ($b['date'] ?? '')) <=> strtotime((string) ($a['date'] ?? ''));
});

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Transaction history</h1>
        </div>

        <div class="seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Total credited</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['delivered_total']) ?></div>
              <div class="seller-kpi-card__hint">Delivered order earnings</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 6 13.5 14.5 8.5 9.5 2 16"/><polyline points="16 6 22 6 22 12"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Total debited</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['paid_out_total']) ?></div>
              <div class="seller-kpi-card__hint">Approved/Paid withdrawals</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="m7 17 5 5 5-5"/><path d="M5 7h14"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Current withdrawable</div>
              <div class="seller-kpi-card__value">Rs <?= number_format((int) $summary['withdrawable_balance']) ?></div>
              <div class="seller-kpi-card__hint">Available now</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">All transactions</h2>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Details</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($txns as $t): ?>
                    <?php $isCredit = (string) ($t['type'] ?? '') === 'credit'; ?>
                    <tr>
                      <td><?= h((string) ($t['date'] ?? '-')) ?></td>
                      <td><?= h((string) ($t['title'] ?? '-')) ?></td>
                      <td><?= h((string) ($t['reference'] ?? '-')) ?></td>
                      <td><span class="seller-status-chip seller-status-chip--<?= h(strtolower((string) ($t['status'] ?? 'pending'))) ?>"><?= h((string) ($t['status'] ?? '-')) ?></span></td>
                      <td class="<?= $isCredit ? 'seller-txn-amount--credit' : 'seller-txn-amount--debit' ?>">
                        <?= $isCredit ? '+' : '-' ?>Rs <?= number_format((int) ($t['amount'] ?? 0)) ?>
                      </td>
                      <td><?= h((string) ($t['meta'] ?? '-')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($txns === []): ?>
                    <tr><td colspan="6">No transactions found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
