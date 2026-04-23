<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_finance.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Earnings';
$activeNav = 'earnings';

$summary = seller_finance_summary($pdo, (int) $seller['id']);
$sellerProfitTotal = max(
    0,
    (int) ($summary['paid_out_total'] ?? 0) + (int) ($summary['withdrawable_balance'] ?? 0)
);

require_once __DIR__ . '/../admin/_pagination.php';

$deliveredOrdersTotal = seller_finance_delivered_order_count($pdo, (int) $seller['id']);
$withdrawHistoryTotal = seller_finance_withdraw_request_count($pdo, (int) $seller['id']);

['page' => $_earnPgUnused, 'perPage' => $earnPerPage] = admin_pagination_read(25);
$earnOrdersPageReq = max(1, (int) ($_GET['earn_orders_page'] ?? 1));
$earnWithdrawPageReq = max(1, (int) ($_GET['earn_withdraw_page'] ?? 1));
$earnOrdersMeta = admin_pagination_resolve($deliveredOrdersTotal, $earnOrdersPageReq, $earnPerPage);
$earnWithdrawMeta = admin_pagination_resolve($withdrawHistoryTotal, $earnWithdrawPageReq, $earnPerPage);
$earnOrdersPage = $earnOrdersMeta['page'];
$earnOrdersPerPage = $earnOrdersMeta['perPage'];
$earnOrdersTotalPages = $earnOrdersMeta['totalPages'];
$earnWithdrawPage = $earnWithdrawMeta['page'];
$earnWithdrawPerPage = $earnWithdrawMeta['perPage'];
$earnWithdrawTotalPages = $earnWithdrawMeta['totalPages'];

$recentOrders = seller_finance_recent_delivered_orders($pdo, (int) $seller['id'], $earnOrdersPerPage, $earnOrdersMeta['offset']);
$withdraws = seller_finance_withdraw_requests($pdo, (int) $seller['id'], $earnWithdrawPerPage, $earnWithdrawMeta['offset']);

function seller_earnings_format_dt(?string $raw): string
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

function seller_earnings_withdraw_chip_mod(string $status): string
{
    return match (strtolower(trim($status))) {
        'paid', 'approved' => 'seller-status-chip--delivered',
        'rejected' => 'seller-status-chip--rejected',
        'pending' => 'seller-status-chip--pending',
        default => '',
    };
}

function seller_earnings_withdraw_status_label(string $status): string
{
    $s = strtolower(trim($status));

    return match ($s) {
        'paid' => 'Paid',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'pending' => 'Pending',
        default => $s !== '' ? ucfirst($s) : '—',
    };
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-txn-head seller-earnings-page-head">
          <div>
            <h1>Earnings</h1>
            <p class="seller-txn-subtitle">Delivered orders aapke <strong>credited</strong> total ko banate hain. Withdrawable = delivered − paid out − pending requests. Pipeline = abhi deliver nahi hua. Neeche tables me search sirf <strong>us page</strong> par lagta hai.</p>
          </div>
          <div class="admin-page-head__actions seller-txn-card-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="transactions.php">Transactions</a>
            <a class="admin-btn admin-btn--primary" href="withdraw-requests.php">Withdraw</a>
          </div>
        </div>

        <div class="seller-kpi seller-txn-kpi seller-earnings-kpi">
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Delivered (credited)</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['delivered_total'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Line items jab order delivered ho</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Seller profit</div>
              <div class="seller-kpi-card__value">₹<?= number_format($sellerProfitTotal, 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Paid out + current withdrawable</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 7 13.5 15.5 9 11 2 18"/><polyline points="16 7 22 7 22 13"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Withdrawable</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['withdrawable_balance'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Abhi payout request kar sakte ho</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="m17 7-5-5-5 5"/><path d="M5 17h14"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Pending withdraw</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['pending_withdraw_total'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Admin review — balance se hold</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Paid out</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['paid_out_total'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Approved / paid withdrawals</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Pipeline</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['pipeline_total'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Processing + shipped (estimate)</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 6 13.5 14.5 8.5 9.5 2 16"/><polyline points="16 6 22 6 22 12"/></svg>
            </div>
          </div>
        </div>

        <div class="card seller-txn-card seller-earnings-card">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Delivered orders</h2>
              <p class="card-subtitle seller-txn-card-sub">Aapke products wale line items ka total jab order <strong>delivered</strong> ho chuka ho. Poori history ke liye <a href="transactions.php">Transactions</a> dekho.</p>
            </div>
            <span class="seller-txn-count-pill"><?= (int) $deliveredOrdersTotal ?> order<?= $deliveredOrdersTotal === 1 ? '' : 's' ?></span>
          </div>
          <div class="card-body card-body--flush">
            <div class="seller-txn-search-bar">
              <label class="seller-inventory-search-wrap seller-txn-search" for="sellerEarningsOrdersSearch">
                <span class="seller-inventory-search-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                  type="search"
                  id="sellerEarningsOrdersSearch"
                  class="seller-inventory-search-input"
                  placeholder="Search ref, order id, amount, date…"
                  autocomplete="off"
                  aria-label="Search delivered orders"
                  <?= $recentOrders === [] ? 'disabled' : '' ?>
                >
              </label>
            </div>
            <div class="admin-table-wrap seller-txn-table-wrap">
              <table class="admin-table seller-txn-table seller-earnings-orders-table">
                <thead>
                  <tr>
                    <th>Delivered</th>
                    <th>Order</th>
                    <th class="seller-earnings-th-qty">Qty</th>
                    <th class="seller-txn-th-amount">Earnings</th>
                    <th class="seller-txn-th-actions"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentOrders as $row): ?>
                    <?php
                    $oid = (int) ($row['id'] ?? 0);
                    $ref = trim((string) ($row['order_ref'] ?? ''));
                    $qty = (int) ($row['total_qty'] ?? 0);
                    $earn = (int) ($row['seller_total'] ?? 0);
                    $createdRaw = trim((string) ($row['created_at'] ?? ''));
                    $createdFmt = seller_earnings_format_dt($createdRaw);
                    $orderSearch = mb_strtolower(
                        $ref . ' '
                        . (string) $oid . ' '
                        . (string) $qty . ' '
                        . (string) $earn . ' '
                        . preg_replace('/[^\d]/', '', (string) $earn) . ' '
                        . $createdRaw . ' '
                        . $createdFmt . ' '
                        . 'delivered earnings'
                    );
                    ?>
                    <tr class="seller-earnings-order-row" data-earnings-search="<?= h($orderSearch) ?>">
                      <td class="seller-txn-td-muted"><?= h($createdFmt) ?></td>
                      <td>
                        <span class="seller-orders-ref"><?= h($ref !== '' ? $ref : '—') ?></span>
                        <span class="seller-orders-id-tag">#<?= $oid ?></span>
                      </td>
                      <td class="seller-earnings-td-qty"><?= $qty ?></td>
                      <td class="seller-txn-td-amount seller-txn-amount--credit">+₹<?= number_format($earn, 0, '.', ',') ?></td>
                      <td class="seller-txn-td-actions">
                        <?php if ($oid > 0): ?>
                          <a class="seller-edit-btn" href="order-details.php?id=<?= $oid ?>" aria-label="Order details" title="Order details">
                            <svg class="seller-details-icon" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M9 4.46A9.8 9.8 0 0 1 12 4c4.182 0 7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296A14.5 14.5 0 0 1 5 6.821"></path><path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path></g></svg>
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($recentOrders !== []): ?>
                    <tr id="sellerEarningsOrdersNoMatch" class="seller-txn-no-match-row" style="display:none">
                      <td colspan="5">
                        <div class="seller-txn-no-match-inner">
                          <span class="seller-txn-no-match-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                          </span>
                          <div>
                            <strong>No matching orders</strong>
                            <p>Reference, order ID, ya amount try karein.</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                  <?php if ($recentOrders === []): ?>
                    <tr>
                      <td colspan="5">
                        <div class="seller-txn-empty seller-earnings-empty">
                          <p class="seller-txn-empty__title">Abhi delivered earnings nahi</p>
                          <p class="seller-txn-empty__text">Jab aapke products wale orders deliver ho jayenge, yahan <strong>+₹</strong> lines dikhengi. <a href="orders.php">Orders</a> se status track karein.</p>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'earnings.php';
            $paginationTotal = $deliveredOrdersTotal;
            $paginationPage = $earnOrdersPage;
            $paginationPerPage = $earnOrdersPerPage;
            $paginationTotalPages = $earnOrdersTotalPages;
            $paginationPageKey = 'earn_orders_page';
            require __DIR__ . '/partials/table-pagination.php';
            ?>
          </div>
        </div>

        <div class="card seller-txn-card seller-earnings-card seller-earnings-card--withdraw">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Withdraw requests</h2>
              <p class="card-subtitle seller-txn-card-sub">OTP flow aur nayi request ke liye <a href="withdraw-requests.php">Withdraw requests</a> page kholo.</p>
            </div>
            <div class="seller-txn-card-head-actions">
              <span class="seller-txn-count-pill"><?= (int) $withdrawHistoryTotal ?> total</span>
              <a class="admin-btn admin-btn--ghost-light" href="withdraw-requests.php">Withdraw</a>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <div class="seller-txn-search-bar">
              <label class="seller-inventory-search-wrap seller-txn-search" for="sellerEarningsWithdrawSearch">
                <span class="seller-inventory-search-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                  type="search"
                  id="sellerEarningsWithdrawSearch"
                  class="seller-inventory-search-input"
                  placeholder="Search WR, amount, method, status…"
                  autocomplete="off"
                  aria-label="Search withdraw requests"
                  <?= $withdraws === [] ? 'disabled' : '' ?>
                >
              </label>
            </div>
            <div class="admin-table-wrap seller-txn-table-wrap">
              <table class="admin-table seller-txn-table seller-earnings-withdraw-table">
                <thead>
                  <tr>
                    <th>Requested</th>
                    <th>Reference</th>
                    <th class="seller-txn-th-amount">Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($withdraws as $w): ?>
                    <?php
                    $wid = (int) ($w['id'] ?? 0);
                    $wamt = (int) ($w['amount'] ?? 0);
                    $stRaw = (string) ($w['status'] ?? '');
                    $stMod = seller_earnings_withdraw_chip_mod($stRaw);
                    $stLabel = seller_earnings_withdraw_status_label($stRaw);
                    $methodRaw = strtolower(trim((string) ($w['method'] ?? '')));
                    $methodLabel = $methodRaw === 'upi' ? 'UPI' : ($methodRaw === 'bank' ? 'Bank' : ($methodRaw !== '' ? ucfirst($methodRaw) : '—'));
                    $methodPillMod = $methodRaw === 'upi' ? 'seller-withdraw-method-pill--upi' : 'seller-withdraw-method-pill--bank';
                    $reqRaw = trim((string) ($w['requested_at'] ?? ''));
                    $reqFmt = seller_earnings_format_dt($reqRaw);
                    $wSearch = mb_strtolower(
                        'wr' . (string) $wid . ' '
                        . (string) $wid . ' '
                        . (string) $wamt . ' '
                        . preg_replace('/[^\d]/', '', (string) $wamt) . ' '
                        . strtolower($stRaw) . ' '
                        . strtolower($stLabel) . ' '
                        . $methodRaw . ' '
                        . strtolower($methodLabel) . ' '
                        . $reqRaw . ' '
                        . $reqFmt
                    );
                    ?>
                    <tr class="seller-earnings-withdraw-row" data-earnings-search="<?= h($wSearch) ?>">
                      <td class="seller-txn-td-muted"><?= h($reqFmt) ?></td>
                      <td><span class="seller-product-list-sku">WR<?= $wid ?></span></td>
                      <td class="seller-txn-td-amount seller-earnings-withdraw-amt">₹<?= number_format($wamt, 0, '.', ',') ?></td>
                      <td>
                        <?php if ($methodLabel !== '—'): ?>
                          <span class="seller-withdraw-method-pill <?= h($methodPillMod) ?>"><?= h($methodLabel) ?></span>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </td>
                      <td><span class="seller-status-chip <?= h($stMod !== '' ? $stMod : '') ?>"><?= h($stLabel) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($withdraws !== []): ?>
                    <tr id="sellerEarningsWithdrawNoMatch" class="seller-txn-no-match-row" style="display:none">
                      <td colspan="5">
                        <div class="seller-txn-no-match-inner">
                          <span class="seller-txn-no-match-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                          </span>
                          <div>
                            <strong>No matching requests</strong>
                            <p>WR number, amount, ya status try karein.</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                  <?php if ($withdraws === []): ?>
                    <tr>
                      <td colspan="5">
                        <div class="seller-txn-empty seller-earnings-empty">
                          <p class="seller-txn-empty__title">Abhi withdraw request nahi</p>
                          <p class="seller-txn-empty__text">Balance hone par <a href="withdraw-requests.php">Withdraw</a> se OTP ke saath request bhejein.</p>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'earnings.php';
            $paginationTotal = $withdrawHistoryTotal;
            $paginationPage = $earnWithdrawPage;
            $paginationPerPage = $earnWithdrawPerPage;
            $paginationTotalPages = $earnWithdrawTotalPages;
            $paginationPageKey = 'earn_withdraw_page';
            require __DIR__ . '/partials/table-pagination.php';
            ?>
          </div>
        </div>

        <script>
          (function () {
            function wireEarningsSearch(inputId, rowSelector, noMatchId) {
              var input = document.getElementById(inputId);
              if (!input || input.disabled) return;
              var rows = document.querySelectorAll(rowSelector);
              var noMatch = document.getElementById(noMatchId);
              function apply() {
                var q = (input.value || '').trim().toLowerCase();
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
                if (noMatch) {
                  noMatch.style.display = (words.length > 0 && !anyShown) ? '' : 'none';
                }
              }
              input.addEventListener('input', apply);
              input.addEventListener('search', apply);
            }
            wireEarningsSearch('sellerEarningsOrdersSearch', 'tr.seller-earnings-order-row', 'sellerEarningsOrdersNoMatch');
            wireEarningsSearch('sellerEarningsWithdrawSearch', 'tr.seller-earnings-withdraw-row', 'sellerEarningsWithdrawNoMatch');
          })();
        </script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
