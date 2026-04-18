<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Order details';
$activeNav = 'orders';

$orderId = (int) ($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: orders.php');
    exit;
}

function seller_order_status_class_detail(string $status): string
{
    return match (strtolower($status)) {
        'delivered' => 'admin-status admin-status--delivered',
        'out' => 'admin-status admin-status--shipped',
        'shipped' => 'admin-status admin-status--shipped',
        'confirmed' => 'admin-status admin-status--processing',
        'processing' => 'admin-status admin-status--processing',
        'cancelled' => 'admin-status admin-status--cancelled',
        default => 'admin-status admin-status--processing',
    };
}

function seller_next_statuses(string $status): array
{
    return match (strtolower($status)) {
        'processing' => ['confirmed'],
        'confirmed' => ['shipped'],
        'shipped' => ['out'],
        'out' => ['delivered'],
        default => [],
    };
}

/**
 * Product name key → order line id when exactly one seller line matches (fixes dedupe when legacy rows omit order_item_id).
 *
 * @param list<array<string,mixed>> $sellerItems
 * @return array<string,int>
 */
function seller_return_single_line_id_by_name_key(array $sellerItems): array
{
    $idsByKey = [];
    foreach ($sellerItems as $sellerItem) {
        $sid = (int) ($sellerItem['id'] ?? 0);
        $nm = trim((string) ($sellerItem['name'] ?? ''));
        if ($sid <= 0 || $nm === '') {
            continue;
        }
        $key = mb_strtolower(preg_replace('/\s+/', ' ', $nm) ?? $nm);
        $idsByKey[$key][] = $sid;
    }
    $out = [];
    foreach ($idsByKey as $key => $ids) {
        if (count($ids) === 1) {
            $out[$key] = $ids[0];
        }
    }

    return $out;
}

/** No further seller actions (completed, rejected, or resolved in DB). */
function seller_return_request_terminal_label(array $rr): ?string
{
    $reqStatus = strtolower(trim((string) ($rr['status'] ?? '')));
    $pickupStatus = strtolower(trim((string) ($rr['pickup_status'] ?? '')));
    $resolvedRaw = trim((string) ($rr['resolved_at'] ?? ''));
    if ($reqStatus === 'rejected') {
        return 'Rejected';
    }
    if ($reqStatus === 'refunded' || $pickupStatus === 'completed' || $resolvedRaw !== '') {
        return 'Return completed';
    }

    return null;
}

function seller_return_format_dt(?string $v): string
{
    $t = trim((string) $v);

    return $t !== '' ? $t : '—';
}

/** @return list<string> */
function seller_return_timeline_labels(): array
{
    return ['Requested', 'Approved', 'Pickup scheduled', 'Picked up', 'Refunded'];
}

function seller_return_timeline_index(string $reqStatus): int
{
    return match ($reqStatus) {
        'pending' => 0,
        'approved' => 1,
        'pickup_scheduled' => 2,
        'picked_up', 'refund_processing' => 3,
        'refunded' => 4,
        default => 0,
    };
}

$orderSt = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.shipping_address, o.created_at,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), 'Guest') AS customer_name,
            COALESCE(u.email, '-') AS customer_email
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ?
       AND EXISTS (
         SELECT 1
         FROM order_items oi
         INNER JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = o.id
           AND p.seller_id = ?
       )
     LIMIT 1"
);
$orderSt->execute([$orderId, (int) $seller['id']]);
$order = $orderSt->fetch();
if (!$order) {
    header('Location: orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'update_status') {
    $newStatus = strtolower(trim((string) ($_POST['new_status'] ?? '')));
    $allowed = seller_next_statuses((string) $order['status']);
    if (in_array($newStatus, $allowed, true)) {
        try {
            $pdo->beginTransaction();

            $upd = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ? LIMIT 1');
            $upd->execute([$newStatus, (int) $order['id']]);

            $pdo->commit();
            header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=status_updated');
            exit;
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=status_invalid');
            exit;
        }
    }
    header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=status_invalid');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'review_return_request') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = strtolower(trim((string) ($_POST['decision'] ?? '')));
    $sellerNote = trim((string) ($_POST['seller_note'] ?? ''));
    if (strlen($sellerNote) > 255) {
        $sellerNote = substr($sellerNote, 0, 255);
    }
    $allowedDecisions = ['approve', 'reject', 'schedule_pickup', 'mark_picked_up', 'refund_done'];
    if ($requestId <= 0 || !in_array($decision, $allowedDecisions, true)) {
        header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid');
        exit;
    }

    $reqSt = $pdo->prepare(
        'SELECT id, status, pickup_status, resolved_at
         FROM user_return_requests
         WHERE id = ? AND seller_id = ? AND order_id = ?
         LIMIT 1'
    );
    $reqSt->execute([$requestId, (int) $seller['id'], (int) $order['id']]);
    $req = $reqSt->fetch();
    if (!$req) {
        header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid');
        exit;
    }
    if (seller_return_request_terminal_label($req) !== null) {
        header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid#return-details-card');
        exit;
    }
    $currentStatus = strtolower(trim((string) ($req['status'] ?? '')));
    $currentPickupStatus = strtolower(trim((string) ($req['pickup_status'] ?? 'not_scheduled')));

    $newStatus = $currentStatus;
    $newPickupStatus = $currentPickupStatus;
    $setReviewedAt = false;
    $setResolvedAt = false;
    $setPickupScheduledAt = false;
    $setPickupCompletedAt = false;

    if ($decision === 'approve') {
        if ($currentStatus !== 'pending') {
            header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid');
            exit;
        }
        $newStatus = 'approved';
        $newPickupStatus = 'not_scheduled';
        $setReviewedAt = true;
    } elseif ($decision === 'reject') {
        if (!in_array($currentStatus, ['pending', 'approved'], true)) {
            header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid');
            exit;
        }
        $newStatus = 'rejected';
        $newPickupStatus = 'cancelled';
        $setReviewedAt = true;
        $setResolvedAt = true;
    } elseif ($decision === 'schedule_pickup') {
        if (!in_array($currentStatus, ['approved', 'pickup_scheduled'], true)) {
            header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid');
            exit;
        }
        $newStatus = 'pickup_scheduled';
        $newPickupStatus = 'scheduled';
        $setPickupScheduledAt = true;
    } elseif ($decision === 'mark_picked_up') {
        if ($currentStatus !== 'pickup_scheduled') {
            header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid');
            exit;
        }
        $newStatus = 'picked_up';
        $newPickupStatus = 'picked_up';
        $setPickupCompletedAt = true;
    } elseif ($decision === 'refund_done') {
        if (!in_array($currentStatus, ['picked_up', 'refund_processing'], true)) {
            header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid');
            exit;
        }
        $newStatus = 'refunded';
        $newPickupStatus = 'completed';
        $setResolvedAt = true;
    }

    $upd = $pdo->prepare(
        'UPDATE user_return_requests
         SET status = ?, pickup_status = ?, pickup_note = ?,
             reviewed_at = CASE WHEN ? = 1 THEN NOW() ELSE reviewed_at END,
             pickup_scheduled_at = CASE WHEN ? = 1 THEN NOW() ELSE pickup_scheduled_at END,
             pickup_completed_at = CASE WHEN ? = 1 THEN NOW() ELSE pickup_completed_at END,
             resolved_at = CASE WHEN ? = 1 THEN NOW() ELSE resolved_at END
         WHERE id = ? AND seller_id = ? AND order_id = ?
         LIMIT 1'
    );
    $updParams = [
        $newStatus,
        $newPickupStatus,
        $sellerNote,
        $setReviewedAt ? 1 : 0,
        $setPickupScheduledAt ? 1 : 0,
        $setPickupCompletedAt ? 1 : 0,
        $setResolvedAt ? 1 : 0,
        $requestId,
        (int) $seller['id'],
        (int) $order['id'],
    ];

    if ($decision === 'refund_done') {
        try {
            $pdo->beginTransaction();
            $upd->execute($updParams);
            if ($upd->rowCount() < 1) {
                $pdo->rollBack();
                header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid#return-details-card');
                exit;
            }
            orders_restore_stock_after_return_completed($pdo, $requestId, (int) $seller['id'], (int) $order['id']);
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_invalid#return-details-card');
            exit;
        }
    } else {
        $upd->execute($updParams);
    }

    header('Location: order-details.php?id=' . (int) $order['id'] . '&msg=return_updated#return-details-card');
    exit;
}

$itemsSt = $pdo->prepare(
    "SELECT oi.id, oi.name, oi.emoji, oi.variant_text, oi.price, oi.qty,
            p.id AS product_id, p.slug AS product_slug, p.category AS product_category
     FROM order_items oi
     INNER JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ? AND p.seller_id = ?
     ORDER BY oi.id ASC"
);
$itemsSt->execute([(int) $order['id'], (int) $seller['id']]);
$items = $itemsSt->fetchAll();

/** @var array<int,true> $sellerOrderItemIds */
$sellerOrderItemIds = [];
/** @var array<string,true> $sellerItemNameKeys */
$sellerItemNameKeys = [];
/** @var array<int,int> $sellerLineTotalByOrderItemId */
$sellerLineTotalByOrderItemId = [];
/** @var array<string,int> $sellerLineTotalByNameKey */
$sellerLineTotalByNameKey = [];
foreach ($items as $sellerItem) {
    $sid = (int) ($sellerItem['id'] ?? 0);
    if ($sid > 0) {
        $sellerOrderItemIds[$sid] = true;
        $sellerLineTotalByOrderItemId[$sid] = max(0, (int) ($sellerItem['price'] ?? 0)) * max(1, (int) ($sellerItem['qty'] ?? 1));
    }
    $nm = trim((string) ($sellerItem['name'] ?? ''));
    if ($nm !== '') {
        $key = mb_strtolower(preg_replace('/\s+/', ' ', $nm) ?? $nm);
        $sellerItemNameKeys[$key] = true;
        $line = max(0, (int) ($sellerItem['price'] ?? 0)) * max(1, (int) ($sellerItem['qty'] ?? 1));
        $sellerLineTotalByNameKey[$key] = ($sellerLineTotalByNameKey[$key] ?? 0) + $line;
    }
}

$sellerSingleLineIdByNameKey = seller_return_single_line_id_by_name_key($items);

$returnReqSt = $pdo->prepare(
    'SELECT urr.id, urr.order_id, urr.order_ref, urr.order_item_id, urr.seller_id, urr.product_name, urr.reason, urr.details, urr.status, urr.pickup_status, urr.pickup_note, urr.refund_amount, urr.refund_mode,
            oi.price AS order_item_price, oi.qty AS order_item_qty,
            o.payment_method AS order_payment_method,
            urr.requested_at, urr.reviewed_at, urr.pickup_scheduled_at, urr.pickup_completed_at, urr.resolved_at
     FROM user_return_requests urr
     LEFT JOIN order_items oi ON oi.id = urr.order_item_id
     LEFT JOIN orders o ON o.id = urr.order_id
     WHERE (urr.order_id = ? OR urr.order_ref = ?)
     ORDER BY urr.id DESC'
);
$returnReqSt->execute([(int) $order['id'], (string) ($order['order_ref'] ?? '')]);
/** @var list<array<string,mixed>> $orderReturnRows */
$orderReturnRows = [];
while ($rr = $returnReqSt->fetch()) {
    $rrSellerId = (int) ($rr['seller_id'] ?? 0);
    $rrOrderItemId = (int) ($rr['order_item_id'] ?? 0);
    $rrProductName = trim((string) ($rr['product_name'] ?? ''));
    $rrProductNameKey = $rrProductName !== '' ? mb_strtolower(preg_replace('/\s+/', ' ', $rrProductName) ?? $rrProductName) : '';
    $belongsToSeller = false;
    if ($rrSellerId > 0) {
        $belongsToSeller = $rrSellerId === (int) $seller['id'];
    } elseif ($rrOrderItemId > 0) {
        $belongsToSeller = isset($sellerOrderItemIds[$rrOrderItemId]);
    } elseif ($rrProductNameKey !== '') {
        $belongsToSeller = isset($sellerItemNameKeys[$rrProductNameKey]);
    } else {
        // Legacy rows may miss seller/order-item mapping; for this order page, show them.
        $belongsToSeller = true;
    }
    if (!$belongsToSeller) {
        continue;
    }

    if ($rrOrderItemId <= 0 && $rrProductNameKey !== '' && isset($sellerSingleLineIdByNameKey[$rrProductNameKey])) {
        $rrOrderItemId = $sellerSingleLineIdByNameKey[$rrProductNameKey];
        $rr['order_item_id'] = $rrOrderItemId;
        foreach ($items as $si) {
            if ((int) ($si['id'] ?? 0) === $rrOrderItemId) {
                $rr['order_item_price'] = $si['price'];
                $rr['order_item_qty'] = $si['qty'];
                break;
            }
        }
    }

    $storedRefundAmount = max(0, (int) ($rr['refund_amount'] ?? 0));
    $fallbackRefundAmount = max(0, (int) ($rr['order_item_price'] ?? 0)) * max(1, (int) ($rr['order_item_qty'] ?? 1));
    if ($storedRefundAmount > 0) {
        $rr['refund_amount'] = $storedRefundAmount;
    } elseif ($fallbackRefundAmount > 0) {
        $rr['refund_amount'] = $fallbackRefundAmount;
    } elseif ($rrOrderItemId > 0 && isset($sellerLineTotalByOrderItemId[$rrOrderItemId])) {
        $rr['refund_amount'] = (int) $sellerLineTotalByOrderItemId[$rrOrderItemId];
    } elseif ($rrProductNameKey !== '' && isset($sellerLineTotalByNameKey[$rrProductNameKey])) {
        $rr['refund_amount'] = (int) $sellerLineTotalByNameKey[$rrProductNameKey];
    } else {
        $rr['refund_amount'] = 0;
    }
    $storedRefundMode = trim((string) ($rr['refund_mode'] ?? ''));
    $fallbackRefundMode = trim((string) ($rr['order_payment_method'] ?? ''));
    $rr['refund_mode'] = $storedRefundMode !== '' ? $storedRefundMode : ($fallbackRefundMode !== '' ? $fallbackRefundMode : 'Original payment method');
    $orderReturnRows[] = $rr;
}

/** Newest row per order line / product name (query is ORDER BY id DESC). */
$returnDedupeKeys = [];
$orderReturnRowsDeduped = [];
foreach ($orderReturnRows as $rr) {
    $oid = (int) ($rr['order_item_id'] ?? 0);
    $pn = trim((string) ($rr['product_name'] ?? ''));
    $pnKey = $pn !== '' ? mb_strtolower(preg_replace('/\s+/', ' ', $pn) ?? $pn) : '';
    $dedupeKey = $oid > 0 ? 'oi:' . $oid : ($pnKey !== '' ? 'pn:' . $pnKey : 'id:' . (int) ($rr['id'] ?? 0));
    if (isset($returnDedupeKeys[$dedupeKey])) {
        continue;
    }
    $returnDedupeKeys[$dedupeKey] = true;
    $orderReturnRowsDeduped[] = $rr;
}
$orderReturnRows = $orderReturnRowsDeduped;

$sellerSubtotal = 0;
foreach ($items as $it) {
    $sellerSubtotal += ((int) ($it['price'] ?? 0)) * ((int) ($it['qty'] ?? 0));
}

$flash = '';
$flashOk = false;
$msg = (string) ($_GET['msg'] ?? '');
if ($msg === 'status_updated') {
    $flash = 'Order status successfully updated.';
    $flashOk = true;
    $order['status'] = strtolower((string) ($_POST['new_status'] ?? $order['status']));
} elseif ($msg === 'status_invalid') {
    $flash = 'Selected status transition allowed nahi hai.';
} elseif ($msg === 'return_updated') {
    $flash = 'Return request updated successfully.';
    $flashOk = true;
} elseif ($msg === 'return_invalid') {
    $flash = 'Return request action invalid hai.';
}

if ($msg === 'status_updated') {
    $refreshSt = $pdo->prepare('SELECT status FROM orders WHERE id = ? LIMIT 1');
    $refreshSt->execute([(int) $order['id']]);
    $fresh = $refreshSt->fetchColumn();
    if (is_string($fresh) && $fresh !== '') {
        $order['status'] = $fresh;
    }
}

$nextStatuses = seller_next_statuses((string) $order['status']);

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Order details</h1>
          <div class="admin-page-head__actions">
            <a class="seller-preview-btn" href="orders.php">Back to orders</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>" style="margin-bottom:14px"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title">Order #<?= h((string) $order['order_ref']) ?></h2>
          </div>
          <div class="card-body">
            <div class="seller-order-meta-grid">
              <div class="seller-order-meta-item">
                <span class="seller-order-meta-label">Customer</span>
                <strong><?= h((string) $order['customer_name']) ?></strong>
              </div>
              <div class="seller-order-meta-item">
                <span class="seller-order-meta-label">Email</span>
                <strong><?= h((string) $order['customer_email']) ?></strong>
              </div>
              <div class="seller-order-meta-item">
                <span class="seller-order-meta-label">Current status</span>
                <span class="<?= seller_order_status_class_detail((string) $order['status']) ?>"><?= h((string) $order['status']) ?></span>
              </div>
              <div class="seller-order-meta-item">
                <span class="seller-order-meta-label">Order date</span>
                <strong><?= h((string) $order['created_at']) ?></strong>
              </div>
              <div class="seller-order-meta-item">
                <span class="seller-order-meta-label">Order total</span>
                <strong>Rs <?= number_format((int) ($order['total_amount'] ?? 0)) ?></strong>
              </div>
              <div class="seller-order-meta-item">
                <span class="seller-order-meta-label">Your items total</span>
                <strong>Rs <?= number_format($sellerSubtotal) ?></strong>
              </div>
              <div class="seller-order-meta-item">
                <span class="seller-order-meta-label">Payment method</span>
                <strong><?= h((string) ($order['payment_method'] ?? '-')) ?></strong>
              </div>
              <div class="seller-order-meta-item seller-order-meta-item--wide">
                <span class="seller-order-meta-label">Shipping address</span>
                <strong><?= h((string) ($order['shipping_address'] ?? '-')) ?></strong>
              </div>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Update order status</h2>
          </div>
          <div class="card-body">
            <p class="seller-help" style="margin-bottom:10px">Stock deduction checkout/order placement ke time par ho chuki hoti hai.</p>
            <?php if ($nextStatuses === []): ?>
              <p class="seller-help">Is order ka status ab आगे update nahi kiya ja sakta.</p>
            <?php else: ?>
              <form method="post" class="seller-order-status-form">
                <input type="hidden" name="action" value="update_status">
                <label for="newStatus">Next status</label>
                <select id="newStatus" name="new_status" required>
                  <?php foreach ($nextStatuses as $status): ?>
                    <option value="<?= h($status) ?>"><?= h(ucfirst($status)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="admin-btn admin-btn--primary">Update status</button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" id="return-details-card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Return details</h2>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($orderReturnRows !== []): ?>
              <p class="seller-help seller-return-panels-intro">Summary table se quick actions; neeche <strong>Return process — full view</strong> mein poora flow, refund aur timestamps.</p>
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Return</th>
                      <th>Reason</th>
                      <th>Details</th>
                      <th>Pickup</th>
                      <th>Requested</th>
                      <th>Full process</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($orderReturnRows as $rr): ?>
                      <?php
                      $reqId = (int) ($rr['id'] ?? 0);
                      $reqStatus = strtolower(trim((string) ($rr['status'] ?? 'pending')));
                      if ($reqStatus === '') {
                          $reqStatus = 'pending';
                      }
                      $pickupStatus = strtolower(trim((string) ($rr['pickup_status'] ?? 'not_scheduled')));
                      if ($pickupStatus === '') {
                          $pickupStatus = 'not_scheduled';
                      }
                      $statusLabel = ucwords(str_replace('_', ' ', $reqStatus));
                      $pickupLabel = ucwords(str_replace('_', ' ', $pickupStatus));
                      $terminalLabel = seller_return_request_terminal_label($rr);
                      ?>
                      <tr>
                        <td>
                          <strong>#<?= h((string) ($order['order_ref'] ?? '')) ?></strong>
                          <div class="seller-return-table-meta">Return ID <?= $reqId ?></div>
                          <div class="seller-return-table-meta"><?= h((string) ($rr['product_name'] ?? '-')) ?></div>
                          <div class="seller-return-table-meta"><?= h((string) ($order['customer_name'] ?? 'Guest')) ?></div>
                          <div class="seller-return-table-meta">Status: <?= h($statusLabel) ?></div>
                          <div class="seller-return-table-meta">Refund: Rs <?= number_format(max(0, (int) ($rr['refund_amount'] ?? 0))) ?> · <?= h(trim((string) ($rr['refund_mode'] ?? '')) !== '' ? (string) $rr['refund_mode'] : '-') ?></div>
                        </td>
                        <td><?= h((string) ($rr['reason'] ?? '-')) ?></td>
                        <td><?php
                        $detailText = trim((string) ($rr['details'] ?? ''));
                        echo $detailText === '' ? '<span class="seller-help">—</span>' : h($detailText);
                        ?></td>
                        <td>
                          <div><?= h($pickupLabel) ?></div>
                          <?php if (trim((string) ($rr['pickup_note'] ?? '')) !== ''): ?>
                            <div class="seller-return-table-meta">Note: <?= h((string) $rr['pickup_note']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td><?= h((string) ($rr['requested_at'] ?? '-')) ?></td>
                        <td>
                          <?php if ($reqId > 0): ?>
                            <a class="seller-preview-btn seller-preview-btn--soft" href="#seller-return-req-<?= $reqId ?>">Open full process</a>
                          <?php else: ?>
                            —
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($terminalLabel !== null): ?>
                            <span class="seller-return-terminal-badge"><?= h($terminalLabel) ?></span>
                          <?php else: ?>
                            <div class="seller-return-action-stack">
                            <?php if ($reqStatus === 'pending'): ?>
                              <form method="post" class="seller-return-action-form">
                                <input type="hidden" name="action" value="review_return_request">
                                <input type="hidden" name="request_id" value="<?= $reqId ?>">
                                <input type="hidden" name="decision" value="approve">
                                <input type="text" name="seller_note" class="seller-return-note-input" maxlength="255" placeholder="Optional note" autocomplete="off">
                                <button class="admin-btn admin-btn--primary" type="submit">Approve</button>
                              </form>
                              <form method="post" class="seller-return-action-form">
                                <input type="hidden" name="action" value="review_return_request">
                                <input type="hidden" name="request_id" value="<?= $reqId ?>">
                                <input type="hidden" name="decision" value="reject">
                                <input type="text" name="seller_note" class="seller-return-note-input" maxlength="255" placeholder="Optional note" autocomplete="off">
                                <button class="admin-btn admin-btn--ghost-light" type="submit">Reject</button>
                              </form>
                            <?php elseif ($reqStatus === 'approved'): ?>
                              <form method="post" class="seller-return-action-form">
                                <input type="hidden" name="action" value="review_return_request">
                                <input type="hidden" name="request_id" value="<?= $reqId ?>">
                                <input type="hidden" name="decision" value="schedule_pickup">
                                <input type="text" name="seller_note" class="seller-return-note-input" maxlength="255" placeholder="Pickup / note" autocomplete="off">
                                <button class="admin-btn admin-btn--primary" type="submit">Schedule Pickup</button>
                              </form>
                            <?php elseif ($reqStatus === 'pickup_scheduled'): ?>
                              <form method="post" class="seller-return-action-form">
                                <input type="hidden" name="action" value="review_return_request">
                                <input type="hidden" name="request_id" value="<?= $reqId ?>">
                                <input type="hidden" name="decision" value="mark_picked_up">
                                <input type="text" name="seller_note" class="seller-return-note-input" maxlength="255" placeholder="Optional note" autocomplete="off">
                                <button class="admin-btn admin-btn--primary" type="submit">Mark Picked Up</button>
                              </form>
                            <?php elseif ($reqStatus === 'picked_up' || $reqStatus === 'refund_processing'): ?>
                              <form method="post" class="seller-return-action-form">
                                <input type="hidden" name="action" value="review_return_request">
                                <input type="hidden" name="request_id" value="<?= $reqId ?>">
                                <input type="hidden" name="decision" value="refund_done">
                                <input type="text" name="seller_note" class="seller-return-note-input" maxlength="255" placeholder="Refund note" autocomplete="off">
                                <button class="admin-btn admin-btn--primary" type="submit">Mark Refund Done</button>
                              </form>
                            <?php else: ?>
                              <span class="seller-help">No action</span>
                            <?php endif; ?>
                            </div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="seller-return-panels-stack">
                <div class="seller-return-panels-head">
                  <h3 class="seller-return-panels-heading">Return process — full view</h3>
                  <p class="seller-help seller-return-panels-help">Har return ka poora flow: steps, refund details, aur timestamps — panel khol kar dekhein.</p>
                </div>
                <?php foreach ($orderReturnRows as $rrPanel): ?>
                  <?php
                  $rid = (int) ($rrPanel['id'] ?? 0);
                  if ($rid <= 0) {
                      continue;
                  }
                  $pStatus = strtolower(trim((string) ($rrPanel['status'] ?? 'pending')));
                  if ($pStatus === '') {
                      $pStatus = 'pending';
                  }
                  $pPickup = strtolower(trim((string) ($rrPanel['pickup_status'] ?? 'not_scheduled')));
                  if ($pPickup === '') {
                      $pPickup = 'not_scheduled';
                  }
                  $isRejectedPanel = ($pStatus === 'rejected');
                  $isRefundedPanel = ($pStatus === 'refunded');
                  $stepIndex = seller_return_timeline_index($pStatus);
                  $tlLabels = seller_return_timeline_labels();
                  $statusBadgeClass = 'seller-return-badge--neutral';
                  if ($isRejectedPanel) {
                      $statusBadgeClass = 'seller-return-badge--danger';
                  } elseif ($isRefundedPanel || $pStatus === 'picked_up' || $pStatus === 'refund_processing') {
                      $statusBadgeClass = 'seller-return-badge--success';
                  } elseif (in_array($pStatus, ['pending', 'approved', 'pickup_scheduled'], true)) {
                      $statusBadgeClass = 'seller-return-badge--progress';
                  }
                  ?>
                  <details id="seller-return-req-<?= $rid ?>" class="seller-return-details-panel">
                    <summary class="seller-return-details-summary">
                      <span class="seller-return-summary-chevron" aria-hidden="true"></span>
                      <div class="seller-return-summary-text">
                        <span class="seller-return-summary-id">Return #<?= $rid ?></span>
                        <span class="seller-return-summary-item"><?= h((string) ($rrPanel['product_name'] ?? '—')) ?></span>
                      </div>
                      <div class="seller-return-summary-badges">
                        <span class="seller-return-badge <?= $statusBadgeClass ?>"><?= h(ucwords(str_replace('_', ' ', $pStatus))) ?></span>
                        <span class="seller-return-badge seller-return-badge--pickup">Pickup · <?= h(ucwords(str_replace('_', ' ', $pPickup))) ?></span>
                      </div>
                    </summary>
                    <div class="seller-return-details-body">
                      <?php if ($isRejectedPanel): ?>
                        <p class="seller-return-rejected-banner">Return request <strong>rejected</strong> — customer ko inform karein agar zarurat ho.</p>
                      <?php else: ?>
                        <div class="seller-return-timeline-wrap">
                          <p class="seller-return-timeline-title">Progress</p>
                          <div class="seller-return-timeline<?= $isRefundedPanel ? ' seller-return-timeline--complete' : '' ?>" aria-label="Return progress">
                          <?php foreach ($tlLabels as $ti => $tlabel): ?>
                            <?php
                            $done = $isRefundedPanel || ($ti < $stepIndex);
                            $active = !$isRefundedPanel && $ti === $stepIndex;
                            ?>
                            <div class="seller-return-timeline-step<?= $done ? ' seller-return-timeline-step--done' : '' ?><?= $active ? ' seller-return-timeline-step--active' : '' ?>">
                              <span class="seller-return-timeline-dot"><?= $done ? '✓' : (string) ($ti + 1) ?></span>
                              <span class="seller-return-timeline-label"><?= h($tlabel) ?></span>
                            </div>
                          <?php endforeach; ?>
                          </div>
                        </div>
                      <?php endif; ?>

                      <div class="seller-return-facts-wrap">
                        <div class="seller-return-facts-block">
                          <h4 class="seller-return-facts-block-title">Refund &amp; order</h4>
                          <dl class="seller-return-facts">
                            <div class="seller-return-fact"><dt>Refund amount</dt><dd class="seller-return-fact--emphasis">Rs <?= number_format(max(0, (int) ($rrPanel['refund_amount'] ?? 0))) ?></dd></div>
                            <div class="seller-return-fact"><dt>Refund mode</dt><dd><?= h(trim((string) ($rrPanel['refund_mode'] ?? '')) !== '' ? (string) $rrPanel['refund_mode'] : '—') ?></dd></div>
                            <div class="seller-return-fact"><dt>Order ref</dt><dd><code class="seller-return-mono"><?= h((string) ($order['order_ref'] ?? '')) ?></code></dd></div>
                            <div class="seller-return-fact"><dt>Order line ID</dt><dd><code class="seller-return-mono"><?= (int) ($rrPanel['order_item_id'] ?? 0) ?: '—' ?></code></dd></div>
                            <div class="seller-return-fact seller-return-fact--wide"><dt>Pickup note</dt><dd><?= h(trim((string) ($rrPanel['pickup_note'] ?? '')) !== '' ? (string) $rrPanel['pickup_note'] : '—') ?></dd></div>
                          </dl>
                        </div>
                        <div class="seller-return-facts-block">
                          <h4 class="seller-return-facts-block-title">Timeline</h4>
                          <dl class="seller-return-facts seller-return-facts--timeline">
                            <div class="seller-return-fact"><dt>Requested</dt><dd><?= h(seller_return_format_dt($rrPanel['requested_at'] ?? null)) ?></dd></div>
                            <div class="seller-return-fact"><dt>Reviewed</dt><dd><?= h(seller_return_format_dt($rrPanel['reviewed_at'] ?? null)) ?></dd></div>
                            <div class="seller-return-fact"><dt>Pickup scheduled</dt><dd><?= h(seller_return_format_dt($rrPanel['pickup_scheduled_at'] ?? null)) ?></dd></div>
                            <div class="seller-return-fact"><dt>Pickup completed</dt><dd><?= h(seller_return_format_dt($rrPanel['pickup_completed_at'] ?? null)) ?></dd></div>
                            <div class="seller-return-fact"><dt>Resolved</dt><dd><?= h(seller_return_format_dt($rrPanel['resolved_at'] ?? null)) ?></dd></div>
                          </dl>
                        </div>
                      </div>
                    </div>
                  </details>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="seller-help">Is order ke liye abhi koi return request nahi mili.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title">Order items (your products)</h2>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Variant</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Line total</th>
                    <th>Product</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $it): ?>
                    <?php
                    $lineTotal = ((int) ($it['price'] ?? 0)) * ((int) ($it['qty'] ?? 0));
                    $variant = trim((string) ($it['variant_text'] ?? ''));
                    ?>
                    <tr>
                      <td>
                        <strong><?= h((string) ($it['name'] ?? 'Item')) ?></strong>
                        <div style="font-size:0.82rem;color:var(--admin-text-muted)"><?= h((string) ($it['emoji'] ?? '📦')) ?></div>
                      </td>
                      <td><?= h((string) ($it['product_category'] ?? '-')) ?></td>
                      <td><?= h($variant !== '' ? $variant : '-') ?></td>
                      <td>Rs <?= number_format((int) ($it['price'] ?? 0)) ?></td>
                      <td><?= (int) ($it['qty'] ?? 0) ?></td>
                      <td>Rs <?= number_format($lineTotal) ?></td>
                      <td>
                        <a class="seller-preview-btn" href="../product.php?id=<?= (int) ($it['product_id'] ?? 0) ?>" target="_blank" rel="noopener">View product</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($items === []): ?>
                    <tr><td colspan="7">No order items found for your catalogue.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

<?php if ($orderReturnRows !== []): ?>
 <script>
        (function () {
          function openReturnFromHash() {
            var h = window.location.hash || '';
            if (h.indexOf('#seller-return-req-') !== 0) return;
            var el = document.getElementById(h.slice(1));
            if (!el || el.tagName !== 'DETAILS') return;
            el.open = true;
            window.requestAnimationFrame(function () {
              el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
          }
          if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', openReturnFromHash);
          } else {
            openReturnFromHash();
          }
          window.addEventListener('hashchange', openReturnFromHash);
        })();
        </script>
<?php endif; ?>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
