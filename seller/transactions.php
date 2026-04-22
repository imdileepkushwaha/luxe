<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_finance.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Transactions';
$activeNav = 'transactions';

$summary = seller_finance_summary($pdo, (int) $seller['id']);

require_once __DIR__ . '/../admin/_pagination.php';

$creditsCountSt = $pdo->prepare(
    "SELECT COUNT(*) FROM (
         SELECT o.id
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         INNER JOIN products p ON p.id = oi.product_id
         WHERE p.seller_id = ?
           AND o.status = 'delivered'
         GROUP BY o.id
     ) x"
);
$creditsCountSt->execute([(int) $seller['id']]);
$creditsTotal = (int) $creditsCountSt->fetchColumn();

$debitsCountSt = $pdo->prepare('SELECT COUNT(*) FROM seller_withdraw_requests WHERE seller_id = ?');
$debitsCountSt->execute([(int) $seller['id']]);
$debitsTotal = (int) $debitsCountSt->fetchColumn();

['page' => $_txnPageUnused, 'perPage' => $txnPerPage] = admin_pagination_read(25);
$creditsReqPage = max(1, (int) ($_GET['credits_page'] ?? 1));
$debitsReqPage = max(1, (int) ($_GET['debits_page'] ?? 1));
$creditsMeta = admin_pagination_resolve($creditsTotal, $creditsReqPage, $txnPerPage);
$debitsMeta = admin_pagination_resolve($debitsTotal, $debitsReqPage, $txnPerPage);
$creditsPage = $creditsMeta['page'];
$creditsPerPage = $creditsMeta['perPage'];
$creditsOffset = $creditsMeta['offset'];
$creditsTotalPages = $creditsMeta['totalPages'];
$debitsPage = $debitsMeta['page'];
$debitsPerPage = $debitsMeta['perPage'];
$debitsOffset = $debitsMeta['offset'];
$debitsTotalPages = $debitsMeta['totalPages'];

$creditSt = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.created_at, SUM(oi.price * oi.qty) AS amount
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     INNER JOIN products p ON p.id = oi.product_id
     WHERE p.seller_id = ?
       AND o.status = 'delivered'
     GROUP BY o.id, o.order_ref, o.created_at
     ORDER BY o.id DESC
     LIMIT " . (int) $creditsPerPage . ' OFFSET ' . (int) $creditsOffset
);
$creditSt->execute([(int) $seller['id']]);
$credits = $creditSt->fetchAll();

$debitSt = $pdo->prepare(
    "SELECT id, amount, method, account_ref, status, requested_at, reviewed_at, note, rejection_reason
     FROM seller_withdraw_requests
     WHERE seller_id = ?
     ORDER BY id DESC
     LIMIT " . (int) $debitsPerPage . ' OFFSET ' . (int) $debitsOffset
);
$debitSt->execute([(int) $seller['id']]);
$debits = $debitSt->fetchAll();

function seller_txn_format_dt(?string $raw): string
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

function seller_txn_withdraw_chip_mod(string $status): string
{
    return match (strtolower(trim($status))) {
        'paid', 'approved' => 'seller-status-chip--delivered',
        'rejected' => 'seller-status-chip--rejected',
        'pending' => 'seller-status-chip--pending',
        default => '',
    };
}

function seller_txn_withdraw_status_label(string $status): string
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

        <div class="admin-page-head seller-txn-head">
          <div>
            <h1>Transaction history</h1>
            <p class="seller-txn-subtitle">Order earnings and withdrawal requests are listed separately. Credits appear when an order is <strong>delivered</strong>; debits when a withdraw request is approved or paid. Search filters <strong>this page</strong> only in each table.</p>
          </div>
          <div class="admin-page-head__actions">
            <a class="admin-btn admin-btn--ghost-light" href="withdraw-requests.php">Withdraw</a>
          </div>
        </div>

        <div class="seller-kpi seller-txn-kpi">
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Total credited</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['delivered_total'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Delivered order earnings</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 6 13.5 14.5 8.5 9.5 2 16"/><polyline points="16 6 22 6 22 12"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Total debited</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['paid_out_total'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Approved / paid withdrawals</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="m7 17 5 5 5-5"/><path d="M5 7h14"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Withdrawable balance</div>
              <div class="seller-kpi-card__value">₹<?= number_format((int) $summary['withdrawable_balance'], 0, '.', ',') ?></div>
              <div class="seller-kpi-card__hint">Available now</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
          </div>
        </div>

        <div class="card seller-txn-card">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Order earnings</h2>
              <p class="card-subtitle seller-txn-card-sub">Your line-item total per order after status is <strong>delivered</strong>. This is what counts toward your balance.</p>
            </div>
            <span class="seller-txn-count-pill"><?= (int) $creditsTotal ?> order<?= $creditsTotal === 1 ? '' : 's' ?></span>
          </div>
          <div class="card-body card-body--flush">
            <div class="seller-txn-search-bar">
              <label class="seller-inventory-search-wrap seller-txn-search" for="sellerTxnCreditsSearch">
                <span class="seller-inventory-search-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                  type="search"
                  id="sellerTxnCreditsSearch"
                  class="seller-inventory-search-input"
                  placeholder="Search order ref, ID, amount, date…"
                  autocomplete="off"
                  aria-label="Search order earnings"
                  <?= $credits === [] ? 'disabled' : '' ?>
                >
              </label>
            </div>
            <div class="admin-table-wrap seller-txn-table-wrap">
              <table class="admin-table seller-txn-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Order</th>
                    <th class="seller-txn-th-amount">Amount</th>
                    <th class="seller-txn-th-actions"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($credits as $c): ?>
                    <?php
                    $oid = (int) ($c['id'] ?? 0);
                    $amt = (int) ($c['amount'] ?? 0);
                    $ref = trim((string) ($c['order_ref'] ?? ''));
                    $createdRaw = trim((string) ($c['created_at'] ?? ''));
                    $createdFmt = seller_txn_format_dt($createdRaw);
                    $creditSearchBlob = mb_strtolower(
                        $ref . ' '
                        . (string) $oid . ' '
                        . (string) $amt . ' '
                        . preg_replace('/[^\d]/', '', (string) $amt) . ' '
                        . $createdRaw . ' '
                        . $createdFmt . ' '
                        . 'delivered earning order'
                    );
                    ?>
                    <tr class="seller-txn-credit-row" data-txn-search="<?= h($creditSearchBlob) ?>">
                      <td class="seller-txn-td-muted"><?= h($createdFmt) ?></td>
                      <td>
                        <span class="seller-orders-ref"><?= h($ref !== '' ? $ref : '—') ?></span>
                        <span class="seller-orders-id-tag">#<?= $oid ?></span>
                      </td>
                      <td class="seller-txn-td-amount seller-txn-amount--credit">+₹<?= number_format($amt, 0, '.', ',') ?></td>
                      <td class="seller-txn-td-actions">
                        <?php if ($oid > 0): ?>
                          <a class="seller-edit-btn seller-order-actions__link" href="order-details.php?id=<?= $oid ?>" aria-label="Order detail" title="Order detail">
                            <svg class="seller-details-icon" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M9 4.46A9.8 9.8 0 0 1 12 4c4.182 0 7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296A14.5 14.5 0 0 1 5 6.821"></path><path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path></g></svg>
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($credits !== []): ?>
                    <tr id="sellerTxnCreditsNoMatch" class="seller-txn-no-match-row" style="display:none">
                      <td colspan="4">
                        <div class="seller-txn-no-match-inner">
                          <span class="seller-txn-no-match-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                          </span>
                          <div>
                            <strong>No matching orders</strong>
                            <p>Try another keyword — reference, order ID, or amount.</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                  <?php if ($credits === []): ?>
                    <tr>
                      <td colspan="4">
                        <div class="seller-txn-empty">
                          <p class="seller-txn-empty__title">No delivered earnings yet</p>
                          <p class="seller-txn-empty__text">Credits appear here when orders containing your products are marked <strong>delivered</strong>.</p>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'transactions.php';
            $paginationTotal = $creditsTotal;
            $paginationPage = $creditsPage;
            $paginationPerPage = $creditsPerPage;
            $paginationTotalPages = $creditsTotalPages;
            $paginationPageKey = 'credits_page';
            require __DIR__ . '/partials/table-pagination.php';
            ?>
          </div>
        </div>

        <div class="card seller-txn-card seller-txn-card--withdraw">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Withdraw requests</h2>
              <p class="card-subtitle seller-txn-card-sub">Payout requests you submitted. Pending amounts reduce withdrawable balance until approved, rejected, or paid.</p>
            </div>
            <div class="seller-txn-card-head-actions">
              <span class="seller-txn-count-pill"><?= (int) $debitsTotal ?> request<?= $debitsTotal === 1 ? '' : 's' ?></span>
              <a class="admin-btn admin-btn--primary" href="withdraw-requests.php">New request</a>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <div class="seller-txn-search-bar">
              <label class="seller-inventory-search-wrap seller-txn-search" for="sellerTxnDebitsSearch">
                <span class="seller-inventory-search-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                  type="search"
                  id="sellerTxnDebitsSearch"
                  class="seller-inventory-search-input"
                  placeholder="Search WR ref, amount, method, status, notes…"
                  autocomplete="off"
                  aria-label="Search withdraw requests"
                  <?= $debits === [] ? 'disabled' : '' ?>
                >
              </label>
            </div>
            <div class="admin-table-wrap seller-txn-table-wrap">
              <table class="admin-table seller-txn-table">
                <thead>
                  <tr>
                    <th>Requested</th>
                    <th>Reference</th>
                    <th class="seller-txn-th-amount">Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Processed</th>
                    <th>Details</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($debits as $d): ?>
                    <?php
                    $wid = (int) ($d['id'] ?? 0);
                    $stRaw = (string) ($d['status'] ?? '');
                    $stMod = seller_txn_withdraw_chip_mod($stRaw);
                    $stLabel = seller_txn_withdraw_status_label($stRaw);
                    $reviewed = trim((string) ($d['reviewed_at'] ?? ''));
                    $note = trim((string) ($d['note'] ?? ''));
                    $rej = trim((string) ($d['rejection_reason'] ?? ''));
                    $acct = trim((string) ($d['account_ref'] ?? ''));
                    $detailParts = [];
                    if ($note !== '') {
                        $detailParts[] = $note;
                    }
                    if ($acct !== '') {
                        $detailParts[] = 'Ref: ' . $acct;
                    }
                    if ($rej !== '') {
                        $detailParts[] = 'Rejection: ' . $rej;
                    }
                    $detailText = $detailParts !== [] ? implode(' · ', $detailParts) : '—';
                    $reqRaw = trim((string) ($d['requested_at'] ?? ''));
                    $reqFmt = seller_txn_format_dt($reqRaw);
                    $revFmt = $reviewed !== '' ? seller_txn_format_dt($reviewed) : '';
                    $wamt = (int) ($d['amount'] ?? 0);
                    $method = trim((string) ($d['method'] ?? ''));
                    $debitSearchBlob = mb_strtolower(
                        'wr' . (string) $wid . ' '
                        . (string) $wid . ' '
                        . (string) $wamt . ' '
                        . preg_replace('/[^\d]/', '', (string) $wamt) . ' '
                        . strtolower($stRaw) . ' '
                        . strtolower($stLabel) . ' '
                        . $method . ' '
                        . $note . ' '
                        . $acct . ' '
                        . $rej . ' '
                        . $reqRaw . ' '
                        . $reqFmt . ' '
                        . $reviewed . ' '
                        . $revFmt . ' '
                        . $detailText
                    );
                    ?>
                    <tr class="seller-txn-debit-row" data-txn-search="<?= h($debitSearchBlob) ?>">
                      <td class="seller-txn-td-muted"><?= h($reqFmt) ?></td>
                      <td><span class="seller-product-list-sku">WR<?= $wid ?></span></td>
                      <td class="seller-txn-td-amount seller-txn-amount--debit">−₹<?= number_format($wamt, 0, '.', ',') ?></td>
                      <td><?= h($method !== '' ? $method : '—') ?></td>
                      <td><span class="seller-status-chip <?= h($stMod !== '' ? $stMod : '') ?>"><?= h($stLabel) ?></span></td>
                      <td class="seller-txn-td-muted"><?= $reviewed !== '' ? h($revFmt) : '—' ?></td>
                      <td class="seller-txn-td-details"><?= h($detailText) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($debits !== []): ?>
                    <tr id="sellerTxnDebitsNoMatch" class="seller-txn-no-match-row" style="display:none">
                      <td colspan="7">
                        <div class="seller-txn-no-match-inner">
                          <span class="seller-txn-no-match-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                          </span>
                          <div>
                            <strong>No matching requests</strong>
                            <p>Try WR number, amount, bank method, or status.</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                  <?php if ($debits === []): ?>
                    <tr>
                      <td colspan="7">
                        <div class="seller-txn-empty">
                          <p class="seller-txn-empty__title">No withdraw requests</p>
                          <p class="seller-txn-empty__text">Submit a payout from <a href="withdraw-requests.php">Withdraw requests</a> when you have withdrawable balance.</p>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'transactions.php';
            $paginationTotal = $debitsTotal;
            $paginationPage = $debitsPage;
            $paginationPerPage = $debitsPerPage;
            $paginationTotalPages = $debitsTotalPages;
            $paginationPageKey = 'debits_page';
            require __DIR__ . '/partials/table-pagination.php';
            ?>
          </div>
        </div>

        <script>
          (function () {
            function wireTxnSearch(inputId, rowSelector, noMatchId) {
              var input = document.getElementById(inputId);
              if (!input || input.disabled) return;
              var rows = document.querySelectorAll(rowSelector);
              var noMatch = document.getElementById(noMatchId);
              function apply() {
                var q = (input.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                rows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-txn-search') || '').toLowerCase();
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
            wireTxnSearch('sellerTxnCreditsSearch', 'tr.seller-txn-credit-row', 'sellerTxnCreditsNoMatch');
            wireTxnSearch('sellerTxnDebitsSearch', 'tr.seller-txn-debit-row', 'sellerTxnDebitsNoMatch');
          })();
        </script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
