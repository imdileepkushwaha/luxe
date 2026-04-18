<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Orders';
$activeNav = 'orders';

$orders = [];
$cancelRequests = [];
$toastMessage = '';
$toastIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'confirm_order') {
    $confirmOrderId = (int) ($_POST['order_id'] ?? 0);
    if ($confirmOrderId > 0) {
        $confirmSt = $pdo->prepare(
            "UPDATE orders o
             SET o.status = 'confirmed'
             WHERE o.id = ?
               AND o.status = 'processing'
               AND EXISTS (
                 SELECT 1
                 FROM order_items oi
                 INNER JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id = o.id
                   AND p.seller_id = ?
               )
             LIMIT 1"
        );
        $confirmSt->execute([$confirmOrderId, (int) $seller['id']]);
        if ($confirmSt->rowCount() > 0) {
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
                 SET status = ?, reviewed_at = NOW()
                 WHERE order_id = ? AND status = ?'
            );
            $updReq->execute(['approved', $orderId, 'pending']);

            $updOrder = $pdo->prepare(
                'UPDATE orders
                 SET status = ?
                 WHERE id = ? AND status IN (?,?,?,?)
                 LIMIT 1'
            );
            $updOrder->execute(['cancelled', $orderId, 'processing', 'confirmed', 'shipped', 'out']);

            $toastMessage = 'Cancel request approved and order cancelled.';
        } else {
            $updReq = $pdo->prepare(
                'UPDATE user_order_cancel_requests
                 SET status = ?, reviewed_at = NOW()
                 WHERE id = ? AND seller_id = ? AND status = ?
                 LIMIT 1'
            );
            $updReq->execute(['rejected', $requestId, (int) $seller['id'], 'pending']);
            $toastMessage = 'Cancel request rejected.';
        }
    }
}

$st = $pdo->prepare(
    "SELECT o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.shipping_address, o.created_at,
            u.first_name, u.last_name, u.email,
            GROUP_CONCAT(DISTINCT p.category ORDER BY p.category SEPARATOR ', ') AS categories
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     INNER JOIN products p ON p.id = oi.product_id
     LEFT JOIN users u ON u.id = o.user_id
     WHERE p.seller_id = ?
     GROUP BY o.id, o.order_ref, o.status, o.total_amount, o.payment_method, o.shipping_address, o.created_at,
              u.first_name, u.last_name, u.email
     ORDER BY o.id DESC"
);
$st->execute([(int) $seller['id']]);
$orders = $st->fetchAll();

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

function seller_order_status_class_orders(string $status): string
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

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="card">
          <div class="card-header">
            <div class="seller-card-head">
              <h1 class="admin-page-title card-title">Your order list</h1>
              <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <label style="display:flex;align-items:center;gap:6px;font-size:0.82rem;color:var(--admin-text-muted)">
                  <input type="checkbox" id="sellerOrdersAutoRefresh" checked>
                  Auto refresh (5s)
                </label>
                <button type="button" id="sellerOrdersRefreshNow" class="admin-btn admin-btn--ghost-light">Refresh now</button>
              </div>
            </div>
          </div>
          <?php if ($cancelRequests !== []): ?>
            <div class="admin-table-wrap" style="margin:0 16px 12px">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Cancel request</th>
                    <th>Reason</th>
                    <th>Details</th>
                    <th>Requested at</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cancelRequests as $req): ?>
                    <tr>
                      <td>
                        <strong>#<?= h((string) ($req['order_ref'] ?? '')) ?></strong>
                        <div style="font-size:0.82rem;color:var(--admin-text-muted)">Customer: <?= h((string) ($req['customer_name'] ?? 'Guest')) ?></div>
                        <div style="font-size:0.82rem;color:var(--admin-text-muted)">Order status: <?= h((string) ($req['order_status'] ?? '-')) ?></div>
                      </td>
                      <td><?= h((string) ($req['reason'] ?? '-')) ?></td>
                      <td><?= h((string) ($req['details'] ?? '-')) ?></td>
                      <td><?= h((string) ($req['requested_at'] ?? '-')) ?></td>
                      <td>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                          <form method="post" style="margin:0">
                            <input type="hidden" name="action" value="review_cancel_request">
                            <input type="hidden" name="request_id" value="<?= (int) ($req['id'] ?? 0) ?>">
                            <input type="hidden" name="decision" value="approve">
                            <button class="admin-btn admin-btn--primary" type="submit">Approve</button>
                          </form>
                          <form method="post" style="margin:0">
                            <input type="hidden" name="action" value="review_cancel_request">
                            <input type="hidden" name="request_id" value="<?= (int) ($req['id'] ?? 0) ?>">
                            <input type="hidden" name="decision" value="reject">
                            <button class="admin-btn admin-btn--ghost-light" type="submit">Reject</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Order ref</th>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Categories</th>
                    <th>Date</th>
                    <th>Action</th>
                    <th>Details</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $o): ?>
                    <?php
                    $cust = trim(((string) ($o['first_name'] ?? '')) . ' ' . ((string) ($o['last_name'] ?? '')));
                    if ($cust === '') {
                        $cust = 'Guest';
                    }
                    ?>
                    <tr>
                      <td><strong><?= h((string) $o['order_ref']) ?></strong></td>
                      <td><?= h($cust) ?></td>
                      <td><?= h((string) ($o['email'] ?? '-')) ?></td>
                      <td><span class="<?= seller_order_status_class_orders((string) $o['status']) ?>"><?= h((string) $o['status']) ?></span></td>
                      <td>Rs <?= number_format((int) $o['total_amount']) ?></td>
                      <td><span class="admin-badge admin-badge--muted"><?= h((string) $o['payment_method']) ?></span></td>
                      <td><?= h((string) ($o['categories'] ?? '-')) ?></td>
                      <td><?= h((string) $o['created_at']) ?></td>
                      <td>
                        <?php if (strtolower((string) ($o['status'] ?? '')) === 'processing'): ?>
                          <form method="post" style="margin:0">
                            <input type="hidden" name="action" value="confirm_order">
                            <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                            <button class="admin-btn admin-btn--ghost-light" type="submit">Confirm</button>
                          </form>
                        <?php else: ?>
                          -
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                          <a class="seller-preview-btn" href="order-details.php?id=<?= (int) $o['id'] ?>">Details</a>
                          <?php
                          $oid = (int) $o['id'];
                          $retRid = $latestReturnIdByOrder[$oid] ?? 0;
                          ?>
                          <?php if ($retRid > 0): ?>
                            <a class="seller-preview-btn seller-preview-btn--return" href="order-details.php?id=<?= $oid ?>#seller-return-req-<?= $retRid ?>">Return</a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($orders === []): ?>
                    <tr><td colspan="10">No orders found for your allowed categories.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

<script>
  (function () {
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
