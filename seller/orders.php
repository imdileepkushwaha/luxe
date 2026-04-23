<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/notification_mail.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Orders';
$activeNav = 'orders';

$orders = [];
$cancelRequests = [];
$toastMessage = '';
$toastIsError = false;

function seller_orders_status_rank(string $status): int
{
    return match (strtolower(trim($status))) {
        'cancelled' => 0,
        'processing' => 1,
        'confirmed' => 2,
        'shipped' => 3,
        'out' => 4,
        'delivered' => 5,
        default => 1,
    };
}

function seller_orders_status_from_rank(int $rank): string
{
    return match ($rank) {
        5 => 'delivered',
        4 => 'out',
        3 => 'shipped',
        2 => 'confirmed',
        0 => 'cancelled',
        default => 'processing',
    };
}

function seller_orders_sync_parent_order_status_from_items(PDO $pdo, int $orderId): void
{
    $st = $pdo->prepare('SELECT status FROM order_items WHERE order_id = ?');
    $st->execute([$orderId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows) || $rows === []) {
        return;
    }
    $minRank = 5;
    foreach ($rows as $row) {
        $lineStatus = strtolower(trim((string) ($row['status'] ?? 'processing')));
        $minRank = min($minRank, seller_orders_status_rank($lineStatus));
    }
    $upd = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? LIMIT 1');
    $upd->execute([seller_orders_status_from_rank($minRank), $orderId]);
}

/**
 * @return array{email:string,customer_name:string,order_ref:string}|null
 */
function seller_orders_customer_mail_meta(PDO $pdo, int $orderId): ?array
{
    if ($orderId <= 0) {
        return null;
    }
    $st = $pdo->prepare(
        "SELECT COALESCE(u.email, '') AS email,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), 'Customer') AS customer_name,
                o.order_ref
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ?
         LIMIT 1"
    );
    $st->execute([$orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    return [
        'email' => trim((string) ($row['email'] ?? '')),
        'customer_name' => trim((string) ($row['customer_name'] ?? 'Customer')),
        'order_ref' => trim((string) ($row['order_ref'] ?? '')),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'confirm_order') {
    $confirmOrderId = (int) ($_POST['order_id'] ?? 0);
    if ($confirmOrderId > 0) {
        $confirmSt = $pdo->prepare(
            "UPDATE order_items oi
             INNER JOIN products p ON p.id = oi.product_id
             INNER JOIN orders o ON o.id = oi.order_id
             SET oi.status = 'confirmed',
                 oi.confirmed_at = COALESCE(oi.confirmed_at, NOW())
             WHERE oi.order_id = ?
               AND oi.status = 'processing'
               AND o.status IN ('processing', 'confirmed')
               AND p.seller_id = ?"
        );
        $confirmSt->execute([$confirmOrderId, (int) $seller['id']]);
        if ($confirmSt->rowCount() > 0) {
            seller_orders_sync_parent_order_status_from_items($pdo, $confirmOrderId);
            $mailMeta = seller_orders_customer_mail_meta($pdo, $confirmOrderId);
            if (is_array($mailMeta) && $mailMeta['email'] !== '') {
                luxe_send_order_update_email($mailMeta['email'], $mailMeta['customer_name'], $mailMeta['order_ref'], 'confirmed');
            }
            $toastMessage = 'Order confirmed successfully.';
        } else {
            $toastMessage = 'Order confirm nahi ho paaya. Shayad order already updated hai.';
            $toastIsError = true;
        }
    } else {
        $toastMessage = 'Invalid order selected.';
        $toastIsError = true;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'review_cancel_request') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = strtolower(trim((string) ($_POST['decision'] ?? '')));
    $sellerNote = trim((string) ($_POST['seller_note'] ?? ''));
    if (strlen($sellerNote) > 255) {
        $sellerNote = substr($sellerNote, 0, 255);
    }
    if ($requestId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        $toastMessage = 'Invalid cancel request action.';
        $toastIsError = true;
    } else {
        $reqSt = $pdo->prepare(
            'SELECT id, order_id
             FROM user_order_cancel_requests
             WHERE id = ? AND seller_id = ? AND status = ?
             LIMIT 1'
        );
        $reqSt->execute([$requestId, (int) $seller['id'], 'pending']);
        $req = $reqSt->fetch();
        $orderId = (int) ($req['order_id'] ?? 0);
        if ($orderId <= 0) {
            $toastMessage = 'Cancel request already processed or not found.';
            $toastIsError = true;
        } elseif ($decision === 'approve') {
            $updReq = $pdo->prepare(
                'UPDATE user_order_cancel_requests
                 SET status = ?, seller_note = ?, reviewed_at = NOW()
                 WHERE order_id = ? AND status = ?'
            );
            $updReq->execute(['approved', '', $orderId, 'pending']);

            $updOrder = $pdo->prepare(
                'UPDATE orders
                 SET status = ?
                 WHERE id = ? AND status IN (?,?,?,?)
                 LIMIT 1'
            );
            $updOrder->execute(['cancelled', $orderId, 'processing', 'confirmed', 'shipped', 'out']);
            if ($updOrder->rowCount() > 0) {
                $updLines = $pdo->prepare(
                    "UPDATE order_items
                     SET status = 'cancelled'
                     WHERE order_id = ?
                       AND status IN ('processing', 'confirmed', 'shipped', 'out')"
                );
                $updLines->execute([$orderId]);
                $mailMeta = seller_orders_customer_mail_meta($pdo, $orderId);
                if (is_array($mailMeta) && $mailMeta['email'] !== '') {
                    luxe_send_order_update_email($mailMeta['email'], $mailMeta['customer_name'], $mailMeta['order_ref'], 'cancelled');
                }
            }

            $toastMessage = 'Cancel request approved and order cancelled.';
        } else {
            $updReq = $pdo->prepare(
                'UPDATE user_order_cancel_requests
                 SET status = ?, seller_note = ?, reviewed_at = NOW()
                 WHERE id = ? AND seller_id = ? AND status = ?
                 LIMIT 1'
            );
            $updReq->execute(['rejected', $sellerNote, $requestId, (int) $seller['id'], 'pending']);
            $toastMessage = 'Cancel request rejected.';
        }
    }
}

require_once __DIR__ . '/../admin/_pagination.php';

$ordersDateFilter = strtolower(trim((string) ($_GET['date_filter'] ?? 'all')));
$ordersDateFilterMap = [
    'all' => ['label' => 'All time', 'sql' => ''],
    'day' => ['label' => 'Last 24 hours', 'sql' => ' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'],
    'week' => ['label' => 'Last 7 days', 'sql' => ' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'],
    'month' => ['label' => 'Last 30 days', 'sql' => ' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'],
];
if (!isset($ordersDateFilterMap[$ordersDateFilter])) {
    $ordersDateFilter = 'all';
}
$ordersDateWhereSql = (string) ($ordersDateFilterMap[$ordersDateFilter]['sql'] ?? '');

$orderTotalSt = $pdo->prepare(
    "SELECT COUNT(DISTINCT o.id)
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     WHERE p.seller_id = ?"
);
$orderTotalSt->execute([(int) $seller['id']]);
$orderTotalCount = (int) $orderTotalSt->fetchColumn();

$orderFilteredTotalSt = $pdo->prepare(
    "SELECT COUNT(DISTINCT o.id)
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     WHERE p.seller_id = ?" . $ordersDateWhereSql
);
$orderFilteredTotalSt->execute([(int) $seller['id']]);
$orderFilteredCount = (int) $orderFilteredTotalSt->fetchColumn();

$orderStatusSt = $pdo->prepare(
    "SELECT o.status, COUNT(DISTINCT o.id) AS cnt
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     WHERE p.seller_id = ?
     GROUP BY o.status"
);
$orderStatusSt->execute([(int) $seller['id']]);
$processingCount = 0;
$confirmedCount = 0;
$inTransitCount = 0;
$deliveredCount = 0;
$cancelledCount = 0;
while ($row = $orderStatusSt->fetch()) {
    $s = strtolower(trim((string) ($row['status'] ?? '')));
    $c = (int) ($row['cnt'] ?? 0);
    match ($s) {
        'processing' => $processingCount += $c,
        'confirmed' => $confirmedCount += $c,
        'shipped', 'out' => $inTransitCount += $c,
        'delivered' => $deliveredCount += $c,
        'cancelled' => $cancelledCount += $c,
        default => null,
    };
}

['page' => $ordersListPage, 'perPage' => $ordersPerPage] = admin_pagination_read(25);
$ordersPageMeta = admin_pagination_resolve($orderFilteredCount, $ordersListPage, $ordersPerPage);
$ordersPage = $ordersPageMeta['page'];
$ordersOffset = $ordersPageMeta['offset'];
$ordersPerPage = $ordersPageMeta['perPage'];
$ordersTotalPages = $ordersPageMeta['totalPages'];

$st = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.shipping_address, o.created_at,
            u.first_name, u.last_name, u.email,
            GROUP_CONCAT(DISTINCT p.category ORDER BY p.category SEPARATOR ', ') AS categories
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE p.seller_id = ?" . $ordersDateWhereSql . "
     GROUP BY o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.shipping_address, o.created_at,
              u.first_name, u.last_name, u.email
     ORDER BY o.id DESC
     LIMIT " . (int) $ordersPerPage . ' OFFSET ' . (int) $ordersOffset
);
$st->execute([(int) $seller['id']]);
$orders = $st->fetchAll();

$sellerStatusByOrderId = [];
if ($orders !== []) {
    $orderIds = array_values(array_filter(array_map(static fn(array $o): int => (int) ($o['id'] ?? 0), $orders), static fn(int $id): bool => $id > 0));
    if ($orderIds !== []) {
        $ph = implode(',', array_fill(0, count($orderIds), '?'));
        $lineSt = $pdo->prepare(
            "SELECT oi.order_id, oi.status
             FROM order_items oi
             INNER JOIN products p ON p.id = oi.product_id
             WHERE p.seller_id = ?
               AND oi.order_id IN ($ph)"
        );
        $lineSt->execute(array_merge([(int) $seller['id']], $orderIds));
        while ($line = $lineSt->fetch(PDO::FETCH_ASSOC)) {
            $oid = (int) ($line['order_id'] ?? 0);
            if ($oid <= 0) {
                continue;
            }
            $rank = seller_orders_status_rank((string) ($line['status'] ?? 'processing'));
            if (!isset($sellerStatusByOrderId[$oid])) {
                $sellerStatusByOrderId[$oid] = seller_orders_status_from_rank($rank);
                continue;
            }
            $prevRank = seller_orders_status_rank((string) $sellerStatusByOrderId[$oid]);
            if ($rank < $prevRank) {
                $sellerStatusByOrderId[$oid] = seller_orders_status_from_rank($rank);
            }
        }
    }
}

/** Deep-link to newest return row on order-details (hash opens panel). */
$latestReturnIdByOrder = [];
if ($orders !== []) {
    $orderIds = array_map(static fn(array $o): int => (int) ($o['id'] ?? 0), $orders);
    $orderIds = array_values(array_filter($orderIds, static fn(int $id): bool => $id > 0));
    if ($orderIds !== []) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $lr = $pdo->prepare(
            "SELECT order_id, MAX(id) AS rid FROM user_return_requests
             WHERE seller_id = ? AND order_id IN ($placeholders)
             GROUP BY order_id"
        );
        $lr->execute(array_merge([(int) $seller['id']], $orderIds));
        while ($row = $lr->fetch()) {
            $latestReturnIdByOrder[(int) ($row['order_id'] ?? 0)] = (int) ($row['rid'] ?? 0);
        }
    }
}

$cancelReqSt = $pdo->prepare(
    "SELECT r.id, r.order_ref, r.reason, r.details, r.requested_at, r.status,
            o.id AS order_id, o.status AS order_status,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), 'Guest') AS customer_name
     FROM user_order_cancel_requests r
     INNER JOIN orders o ON o.id = r.order_id
     LEFT JOIN users u ON u.id = r.user_id
     WHERE r.seller_id = ?
       AND r.status = 'pending'
     ORDER BY r.requested_at DESC, r.id DESC"
);
$cancelReqSt->execute([(int) $seller['id']]);
$cancelRequests = $cancelReqSt->fetchAll();

$returnTotalCount = 0;
$returnPendingCount = 0;
$returnOpenCount = 0;
$returnCompletedCount = 0;
$returnSellerCancelledCount = 0;
try {
    $returnKpiSt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN LOWER(TRIM(status)) = 'pending' THEN 1 ELSE 0 END) AS pending_n,
            SUM(CASE WHEN LOWER(TRIM(status)) NOT IN ('rejected', 'refunded') THEN 1 ELSE 0 END) AS open_n,
            SUM(CASE WHEN LOWER(TRIM(status)) = 'refunded' THEN 1 ELSE 0 END) AS completed_n,
            SUM(CASE WHEN LOWER(TRIM(status)) = 'rejected' THEN 1 ELSE 0 END) AS seller_cancelled_n
         FROM user_return_requests
         WHERE seller_id = ?"
    );
    $returnKpiSt->execute([(int) $seller['id']]);
    $returnKpiRow = $returnKpiSt->fetch() ?: [];
    $returnTotalCount = (int) ($returnKpiRow['total'] ?? 0);
    $returnPendingCount = (int) ($returnKpiRow['pending_n'] ?? 0);
    $returnOpenCount = (int) ($returnKpiRow['open_n'] ?? 0);
    $returnCompletedCount = (int) ($returnKpiRow['completed_n'] ?? 0);
    $returnSellerCancelledCount = (int) ($returnKpiRow['seller_cancelled_n'] ?? 0);
} catch (Throwable) {
    $returnTotalCount = 0;
    $returnPendingCount = 0;
    $returnOpenCount = 0;
    $returnCompletedCount = 0;
    $returnSellerCancelledCount = 0;
}

$orderCount = $orderTotalCount;
$ordersFormQuery = ['page' => $ordersPage, 'per_page' => $ordersPerPage];
if ($ordersDateFilter !== 'all') {
    $ordersFormQuery['date_filter'] = $ordersDateFilter;
}
$ordersFormAction = 'orders.php?' . http_build_query($ordersFormQuery);

function seller_order_status_chip_modifier(string $status): string
{
    return match (strtolower(trim($status))) {
        'delivered' => 'seller-status-chip--delivered',
        'out', 'shipped' => 'seller-status-chip--shipped',
        'confirmed' => 'seller-status-chip--approved',
        'processing' => 'seller-status-chip--pending',
        'cancelled' => 'seller-status-chip--rejected',
        default => '',
    };
}

function seller_order_status_label_orders(string $status): string
{
    $s = strtolower(trim($status));

    return match ($s) {
        'out' => 'Out for delivery',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'cancelled' => 'Cancelled',
        default => $s !== '' ? ucfirst($s) : '—',
    };
}

function seller_orders_format_datetime(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    try {
        $dt = new DateTimeImmutable($raw);

        return $dt->format('M j, Y · g:i A');
    } catch (Throwable) {
        return $raw;
    }
}

function seller_orders_time_ago(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($raw);
        $now = new DateTimeImmutable('now');
        if ($dt > $now) {
            return 'Today';
        }
        $diff = $now->diff($dt);
        if ($diff->days <= 0) {
            if ($diff->h > 0) {
                return $diff->h . ' hour' . ($diff->h === 1 ? '' : 's') . ' ago';
            }
            if ($diff->i > 0) {
                return $diff->i . ' min' . ($diff->i === 1 ? '' : 's') . ' ago';
            }
            return 'Just now';
        }
        if ($diff->days === 1) {
            return '1 day ago';
        }
        return $diff->days . ' days ago';
    } catch (Throwable) {
        return '';
    }
}

function seller_orders_payment_status_label(string $paymentMethod, string $orderStatus): string
{
    $method = strtolower(trim($paymentMethod));
    $status = strtolower(trim($orderStatus));
    $isCod = in_array($method, ['cod', 'cash on delivery', 'cash_on_delivery'], true);
    if ($isCod) {
        return $status === 'delivered' ? 'Paid' : 'Pending';
    }

    return 'Paid';
}

/**
 * @return list<string>
 */
function seller_orders_category_pills(string $csv): array
{
    $csv = trim($csv);
    if ($csv === '') {
        return [];
    }
    $parts = array_map('trim', explode(',', $csv));
    $out = [];
    foreach ($parts as $p) {
        if ($p !== '' && !in_array($p, $out, true)) {
            $out[] = $p;
        }
    }

    return $out;
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-orders-page-head">
          <div>
            <h1>Orders</h1>
            <p class="seller-orders-subtitle">Track fulfilment for orders that include your products. Search filters <strong>this page</strong> only — use pagination for older orders. New orders may need a quick <strong>Confirm</strong> before you ship.</p>
          </div>
          <div class="admin-page-head__actions seller-orders-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="shipped-products.php">Shipped</a>
            <a class="admin-btn admin-btn--ghost-light" href="earnings.php">Earnings</a>
          </div>
        </div>

        <div class="seller-orders-kpis seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">All orders</div>
              <div class="seller-kpi-card__value"><?= (int) $orderCount ?></div>
              <div class="seller-kpi-card__hint">Rows in your list</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--kpi-yellow">
            <div>
              <div class="seller-kpi-card__label">Needs confirm</div>
              <div class="seller-kpi-card__value"><?= (int) $processingCount ?></div>
              <div class="seller-kpi-card__hint">Processing — action required</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--kpi-orange">
            <div>
              <div class="seller-kpi-card__label">In transit</div>
              <div class="seller-kpi-card__value"><?= (int) $inTransitCount ?></div>
              <div class="seller-kpi-card__hint">Shipped / out for delivery</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3z"/><path d="M16 10h3l2 2v5h-5z"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--kpi-green">
            <div>
              <div class="seller-kpi-card__label">Delivered</div>
              <div class="seller-kpi-card__value"><?= (int) $deliveredCount ?></div>
              <div class="seller-kpi-card__hint">Completed deliveries</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
          </div>

          <h2 class="seller-orders-kpi-section-heading">Returns</h2>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Return requests</div>
              <div class="seller-kpi-card__value"><?= (int) $returnTotalCount ?></div>
              <div class="seller-kpi-card__hint">Customer returns linked to your products</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--kpi-yellow">
            <div>
              <div class="seller-kpi-card__label">Open returns</div>
              <div class="seller-kpi-card__value"><?= (int) $returnOpenCount ?></div>
              <div class="seller-kpi-card__hint">
                <?php if ($returnPendingCount > 0): ?>
                  <?= (int) $returnPendingCount ?> pending your review<?= $returnOpenCount > $returnPendingCount ? ' · baaki pickup/refund steps' : '' ?>
                <?php elseif ($returnOpenCount > 0): ?>
                  Pickup / refund in progress
                <?php else: ?>
                  None active — rejected/refunded closed
                <?php endif; ?>
              </div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--kpi-green">
            <div>
              <div class="seller-kpi-card__label">Return completed</div>
              <div class="seller-kpi-card__value"><?= (int) $returnCompletedCount ?></div>
              <div class="seller-kpi-card__hint">Refund completed and closed</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--returns-cancelled">
            <div>
              <div class="seller-kpi-card__label">Seller cancelled return</div>
              <div class="seller-kpi-card__value"><?= (int) $returnSellerCancelledCount ?></div>
              <div class="seller-kpi-card__hint">Rejected by seller</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
          </div>
        </div>

        <?php if ($cancelRequests !== []): ?>
        <div class="card seller-cancel-requests-card">
          <div class="card-header">
            <div>
              <h2 class="card-title">Cancellation requests</h2>
              <p class="card-subtitle seller-cancel-requests-sub"><?= count($cancelRequests) ?> pending — review and approve or reject.</p>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap seller-cancel-table-wrap">
              <table class="admin-table seller-cancel-table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Reason &amp; details</th>
                    <th>Requested</th>
                    <th class="seller-orders-th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cancelRequests as $req): ?>
                    <tr class="seller-cancel-row">
                      <td class="seller-cancel-cell-order">
                        <span class="seller-orders-ref"><?= h((string) ($req['order_ref'] ?? '')) ?></span>
                        <span class="seller-orders-id-tag">#<?= (int) ($req['order_id'] ?? 0) ?></span>
                        <div class="seller-cancel-customer"><?= h((string) ($req['customer_name'] ?? 'Guest')) ?></div>
                        <div class="seller-cancel-order-status">
                          <span class="seller-status-chip <?= h(seller_order_status_chip_modifier((string) ($req['order_status'] ?? ''))) ?>"><?= h(seller_order_status_label_orders((string) ($req['order_status'] ?? ''))) ?></span>
                        </div>
                      </td>
                      <td class="seller-cancel-cell-reason">
                        <div class="seller-cancel-reason"><?= h((string) ($req['reason'] ?? '—')) ?></div>
                        <?php $det = trim((string) ($req['details'] ?? '')); ?>
                        <?php if ($det !== ''): ?>
                          <div class="seller-cancel-details"><?= h($det) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="seller-cancel-cell-date"><?= h(seller_orders_format_datetime((string) ($req['requested_at'] ?? ''))) ?></td>
                      <td class="seller-cancel-cell-actions">
                        <div class="seller-cancel-actions">
                          <form method="post" class="seller-cancel-action-form" action="<?= h($ordersFormAction) ?>">
                            <input type="hidden" name="action" value="review_cancel_request">
                            <input type="hidden" name="request_id" value="<?= (int) ($req['id'] ?? 0) ?>">
                            <input type="hidden" name="decision" value="approve">
                            <button class="admin-btn admin-btn--primary seller-cancel-btn" type="submit">Approve cancel</button>
                          </form>
                          <form method="post" class="seller-cancel-action-form" action="<?= h($ordersFormAction) ?>">
                            <input type="hidden" name="action" value="review_cancel_request">
                            <input type="hidden" name="request_id" value="<?= (int) ($req['id'] ?? 0) ?>">
                            <input type="hidden" name="decision" value="reject">
                            <input type="text" name="seller_note" maxlength="255" placeholder="Reject reason (visible to user)" class="seller-return-note-input" autocomplete="off">
                            <button class="admin-btn admin-btn--ghost-light seller-cancel-btn" type="submit">Reject</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div class="card seller-orders-card">
          <div class="card-header">
            <div class="seller-card-head seller-card-head--inventory seller-orders-card-head">
              <div>
                <h2 class="card-title">Order list</h2>
                <p class="card-subtitle seller-orders-card-sub">
                  <?php if ($ordersDateFilter === 'all'): ?>
                    <?= $orderCount === 0 ? 'Orders with your products will show here.' : (int) $orderCount . ' order' . ($orderCount === 1 ? '' : 's') . ' total · Auto-refresh keeps the list current.' ?>
                  <?php else: ?>
                    Showing <strong><?= (int) $orderFilteredCount ?></strong> order<?= $orderFilteredCount === 1 ? '' : 's' ?> for <strong><?= h((string) ($ordersDateFilterMap[$ordersDateFilter]['label'] ?? 'Selected range')) ?></strong>.
                  <?php endif; ?>
                </p>
              </div>
              <div class="seller-inventory-toolbar seller-orders-toolbar">
                <form method="get" class="seller-orders-date-filter-form">
                  <input type="hidden" name="page" value="1">
                  <input type="hidden" name="per_page" value="<?= (int) $ordersPerPage ?>">
                  <label class="seller-orders-date-filter-label" for="sellerOrdersDateFilter">Date</label>
                  <select id="sellerOrdersDateFilter" name="date_filter" class="seller-orders-date-filter-select" onchange="this.form.submit()">
                    <option value="all"<?= $ordersDateFilter === 'all' ? ' selected' : '' ?>>All time</option>
                    <option value="day"<?= $ordersDateFilter === 'day' ? ' selected' : '' ?>>Last 24 hours</option>
                    <option value="week"<?= $ordersDateFilter === 'week' ? ' selected' : '' ?>>Last 7 days</option>
                    <option value="month"<?= $ordersDateFilter === 'month' ? ' selected' : '' ?>>Last 30 days</option>
                  </select>
                </form>
                <label class="seller-inventory-search-wrap seller-orders-search" for="sellerOrdersSearch">
                  <span class="seller-inventory-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="sellerOrdersSearch"
                    class="seller-inventory-search-input"
                    placeholder="Search ref, customer, email, status, payment…"
                    autocomplete="off"
                    aria-label="Search orders"
                  >
                </label>
                <div class="seller-orders-refresh-actions">
                  <label class="seller-orders-auto-refresh-label">
                    <input type="checkbox" id="sellerOrdersAutoRefresh" checked>
                    Auto (5s)
                  </label>
                  <button type="button" id="sellerOrdersRefreshNow" class="admin-btn admin-btn--ghost-light">Refresh</button>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap seller-orders-table-wrap">
              <table class="admin-table seller-orders-table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Payment type</th>
                    <th>Ordered at</th>
                    <th class="seller-orders-th-actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $o): ?>
                    <?php
                    $cust = trim(((string) ($o['first_name'] ?? '')) . ' ' . ((string) ($o['last_name'] ?? '')));
                    if ($cust === '') {
                        $cust = 'Guest';
                    }
                    $ordersSearchBlob = mb_strtolower(
                        trim((string) ($o['order_ref'] ?? '')) . ' '
                        . $cust . ' '
                        . trim((string) ($o['email'] ?? '')) . ' '
                        . trim((string) ($o['status'] ?? '')) . ' '
                        . trim((string) ($o['categories'] ?? '')) . ' '
                        . trim((string) ($o['payment_method'] ?? '')) . ' '
                        . (string) (int) ($o['id'] ?? 0) . ' '
                        . (string) (int) ($o['total_amount'] ?? 0) . ' '
                        . trim((string) ($o['shipping_address'] ?? '')) . ' '
                        . trim((string) ($o['created_at'] ?? ''))
                    );
                    $parentOrderStatus = strtolower(trim((string) ($o['status'] ?? '')));
                    $sellerStatus = (string) ($sellerStatusByOrderId[(int) ($o['id'] ?? 0)] ?? (string) ($o['status'] ?? 'processing'));
                    if ($parentOrderStatus === 'cancelled') {
                        $sellerStatus = 'cancelled';
                    }
                    $stMod = seller_order_status_chip_modifier($sellerStatus);
                    $stLabel = seller_order_status_label_orders($sellerStatus);
                    $catPills = seller_orders_category_pills((string) ($o['categories'] ?? ''));
                    $createdRaw = (string) ($o['created_at'] ?? '');
                    $createdFmt = seller_orders_format_datetime($createdRaw);
                    $createdAgo = seller_orders_time_ago($createdRaw);
                    $paymentStatusLabel = seller_orders_payment_status_label((string) ($o['payment_method'] ?? ''), $sellerStatus);
                    ?>
                    <tr class="seller-order-row" data-orders-search="<?= h($ordersSearchBlob) ?>">
                      <td class="seller-orders-td-order">
                        <span class="seller-orders-ref"><?= h((string) $o['order_ref']) ?></span>
                        <span class="seller-orders-id-tag">#<?= (int) $o['id'] ?></span>
                      </td>
                      <td class="seller-orders-td-customer">
                        <span class="seller-orders-customer-name"><?= h($cust) ?></span>
                        <span class="seller-orders-customer-email"><?= h((string) ($o['email'] ?? '—')) ?></span>
                      </td>
                      <td class="seller-orders-td-category">
                        <?php if ($catPills !== []): ?>
                          <div class="seller-orders-cat-pills">
                            <?php foreach ($catPills as $cp): ?>
                              <span class="seller-orders-cat-pill"><?= h(ucfirst($cp)) ?></span>
                            <?php endforeach; ?>
                          </div>
                        <?php else: ?>
                          <span class="seller-help">—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="seller-status-chip <?= h($stMod !== '' ? $stMod : '') ?>"><?= h($stLabel) ?></span>
                      </td>
                      <td class="seller-orders-td-total">
                        <span class="seller-orders-amount">₹<?= number_format((int) $o['total_amount'], 0, '.', ',') ?></span>
                      </td>
                      <td class="seller-orders-td-payment">
                        <span class="seller-orders-pay-pill"><?= h((string) $o['payment_method']) ?></span>
                        <span class="seller-orders-pay-status<?= strtolower($paymentStatusLabel) === 'paid' ? ' seller-orders-pay-status--paid' : ' seller-orders-pay-status--pending' ?>">
                          <?= h($paymentStatusLabel) ?>
                        </span>
                      </td>
                      <td class="seller-orders-td-datetime">
                        <span class="seller-orders-date"><?= h($createdFmt) ?></span>
                        <?php if ($createdAgo !== ''): ?>
                          <span class="seller-orders-ago"><?= h($createdAgo) ?></span>
                        <?php endif; ?>
                      </td>
                      <td class="seller-orders-td-actions">
                        <div class="seller-order-actions">
                          <?php if (strtolower($sellerStatus) === 'processing'): ?>
                            <form method="post" class="seller-order-action-form" action="<?= h($ordersFormAction) ?>">
                              <input type="hidden" name="action" value="confirm_order">
                              <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                              <button class="admin-btn admin-btn--primary seller-order-confirm-btn" type="submit" aria-label="Confirm order" title="Confirm order">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path stroke-linecap="round" stroke-linejoin="round" d="m20 6-11 11-5-5"/></svg>
                              </button>
                            </form>
                          <?php endif; ?>
                          <?php
                          $oid = (int) $o['id'];
                          $retRid = $latestReturnIdByOrder[$oid] ?? 0;
                          ?>
                          <a class="seller-edit-btn seller-order-actions__link" href="order-details.php?id=<?= $oid ?>" aria-label="Order details" title="Order details">
                            <svg class="seller-details-icon" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M9 4.46A9.8 9.8 0 0 1 12 4c4.182 0 7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296A14.5 14.5 0 0 1 5 6.821"></path><path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path></g></svg>
                          </a>
                          <?php if ($retRid > 0): ?>
                            <a class="seller-preview-btn seller-preview-btn--return seller-order-actions__link" href="order-details.php?id=<?= $oid ?>#seller-return-req-<?= $retRid ?>" aria-label="Return details" title="Return details">
                              <svg class="seller-details-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                            </a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($orders === []): ?>
                    <tr class="seller-orders-empty-placeholder">
                      <td colspan="8">
                        <div class="seller-orders-empty">
                          <div class="seller-orders-empty__icon" aria-hidden="true">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                          </div>
                          <h3 class="seller-orders-empty__title">No orders yet</h3>
                          <p class="seller-orders-empty__text">When customers buy your products, they will appear here. You can confirm processing orders and open full details for shipping updates.</p>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <tr id="sellerOrdersNoMatchRow" class="seller-orders-no-match-row" style="display:none">
                      <td colspan="8" class="seller-orders-no-match-cell">
                        <div class="seller-orders-no-match-inner">
                          <span class="seller-orders-no-match-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                          </span>
                          <div>
                            <strong>No matches</strong>
                            <p>Try another keyword — order ref, customer name, email, or status.</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'orders.php';
            $paginationTotal = $orderFilteredCount;
            $paginationPage = $ordersPage;
            $paginationPerPage = $ordersPerPage;
            $paginationTotalPages = $ordersTotalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
          </div>
        </div>

<script>
  (function () {
    var searchInput = document.getElementById('sellerOrdersSearch');
    if (searchInput) {
      var orderRows = document.querySelectorAll('tr.seller-order-row');
      var noMatchRow = document.getElementById('sellerOrdersNoMatchRow');

      function applyOrderSearch() {
        var q = (searchInput.value || '').trim().toLowerCase();
        var words = q.split(/\s+/).filter(Boolean);
        var anyShown = false;
        orderRows.forEach(function (tr) {
          var hay = (tr.getAttribute('data-orders-search') || '').toLowerCase();
          var show = words.length === 0 || words.every(function (w) {
            return hay.indexOf(w) !== -1;
          });
          tr.style.display = show ? '' : 'none';
          if (show) {
            anyShown = true;
          }
        });
        if (noMatchRow) {
          noMatchRow.style.display = (words.length > 0 && !anyShown) ? '' : 'none';
        }
      }

      searchInput.addEventListener('input', applyOrderSearch);
      searchInput.addEventListener('search', applyOrderSearch);
    }

    var autoRefreshCheckbox = document.getElementById('sellerOrdersAutoRefresh');
    var refreshNowBtn = document.getElementById('sellerOrdersRefreshNow');
    if (!autoRefreshCheckbox || !refreshNowBtn) return;

    var storageKey = 'sellerOrdersAutoRefresh5s';
    try {
      var saved = localStorage.getItem(storageKey);
      if (saved === '0') autoRefreshCheckbox.checked = false;
    } catch (e) {}

    refreshNowBtn.addEventListener('click', function () {
      window.location.reload();
    });

    autoRefreshCheckbox.addEventListener('change', function () {
      try {
        localStorage.setItem(storageKey, autoRefreshCheckbox.checked ? '1' : '0');
      } catch (e) {}
    });

    setInterval(function () {
      if (!autoRefreshCheckbox.checked) return;
      if (document.hidden) return;
      var active = document.activeElement;
      if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT')) {
        return;
      }
      window.location.reload();
    }, 5000);
  })();
</script>

<?php if ($toastMessage !== ''): ?>
        <div id="sellerToast" class="seller-toast<?= $toastIsError ? ' seller-toast--error' : ' seller-toast--success' ?>" role="status">
          <?= h($toastMessage) ?>
        </div>
        <script>
          (function () {
            var toast = document.getElementById('sellerToast');
            if (!toast) return;
            if (window.history && window.history.replaceState) {
              var cleanUrl = window.location.pathname + window.location.hash;
              window.history.replaceState({}, document.title, cleanUrl);
            }
            requestAnimationFrame(function () {
              toast.classList.add('show');
            });
            setTimeout(function () {
              toast.classList.remove('show');
            }, 3000);
            setTimeout(function () {
              if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
            }, 3600);
          })();
        </script>
<?php endif; ?>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
