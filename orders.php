<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();
$uid = auth_user_id();
$userLoggedIn = $uid !== null;
$cartNavCount = 0;
foreach ($_SESSION['cart'] ?? [] as $ci) {
    $cartNavCount += (int) ($ci['qty'] ?? 1);
}

$flashMessage = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $uid !== null) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'submit_order_review') {
        $orderRef = trim((string) ($_POST['order_ref'] ?? ''));
        $productId = (int) ($_POST['product_id'] ?? 0);
        $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
        $reviewText = trim((string) ($_POST['review_text'] ?? ''));

        if ($orderRef === '' || $productId <= 0 || strlen($reviewText) < 10) {
            $flashMessage = 'Please provide valid review details (min 10 characters).';
            $flashType = 'error';
        } else {
            $ownOrderSt = $pdo->prepare('SELECT id, status, created_at, payment_method FROM orders WHERE user_id = ? AND order_ref = ? LIMIT 1');
            $ownOrderSt->execute([$uid, $orderRef]);
            $ownOrder = $ownOrderSt->fetch();
            $orderId = (int) ($ownOrder['id'] ?? 0);
            $orderStatus = (string) ($ownOrder['status'] ?? '');
            $orderCreatedAt = (string) ($ownOrder['created_at'] ?? '');
            $orderPaymentMethod = trim((string) ($ownOrder['payment_method'] ?? ''));

            if ($orderId <= 0 || $orderStatus !== 'delivered') {
                $flashMessage = 'Review allowed only for delivered orders.';
                $flashType = 'error';
            } else {
                $itemSt = $pdo->prepare('SELECT id FROM order_items WHERE order_id = ? AND product_id = ? LIMIT 1');
                $itemSt->execute([$orderId, $productId]);
                $isValidItem = (bool) $itemSt->fetchColumn();

                if (!$isValidItem) {
                    $flashMessage = 'Selected product does not belong to this order.';
                    $flashType = 'error';
                } else {
                    $user = auth_user($pdo);
                    $customerName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
                    if ($customerName === '') {
                        $customerName = 'Customer';
                    }
                    if (strlen($reviewText) > 1000) {
                        $reviewText = substr($reviewText, 0, 1000);
                    }

                    $existingSt = $pdo->prepare('SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
                    $existingSt->execute([$productId, $uid]);
                    $existingReviewId = (int) ($existingSt->fetchColumn() ?: 0);

                    if ($existingReviewId > 0) {
                        $upd = $pdo->prepare(
                            'UPDATE product_reviews
                             SET customer_name = ?, rating = ?, review_text = ?,
                                 review_status = ?, seller_response = \'\', seller_reviewed_at = NULL, seller_responded_at = NULL
                             WHERE id = ?
                             LIMIT 1'
                        );
                        $upd->execute([$customerName, $rating, $reviewText, 'pending', $existingReviewId]);
                    } else {
                        $ins = $pdo->prepare(
                            'INSERT INTO product_reviews
                                (product_id, user_id, customer_name, rating, review_text, review_status)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $ins->execute([$productId, $uid, $customerName, $rating, $reviewText, 'pending']);
                    }

                    $flashMessage = 'Review submitted successfully. Seller approval ke baad show hoga.';
                    $flashType = 'success';
                }
            }
        }
    } elseif ($action === 'submit_return_request') {
        $orderRef = trim((string) ($_POST['order_ref'] ?? ''));
        $orderItemId = (int) ($_POST['order_item_id'] ?? 0);
        $productName = trim((string) ($_POST['product_name'] ?? ''));
        $reason = trim((string) ($_POST['return_reason'] ?? ''));
        $details = trim((string) ($_POST['return_details'] ?? ''));

        if ($orderRef === '' || $reason === '') {
            $flashMessage = 'Please fill all required return details.';
            $flashType = 'error';
        } else {
            $ownOrderSt = $pdo->prepare('SELECT id, status, created_at, payment_method FROM orders WHERE user_id = ? AND order_ref = ? LIMIT 1');
            $ownOrderSt->execute([$uid, $orderRef]);
            $ownOrder = $ownOrderSt->fetch();
            $orderId = (int) ($ownOrder['id'] ?? 0);
            $orderStatus = (string) ($ownOrder['status'] ?? '');
            $orderCreatedAt = (string) ($ownOrder['created_at'] ?? '');
            $orderPaymentMethod = trim((string) ($ownOrder['payment_method'] ?? ''));

            if ($orderId <= 0 || $orderStatus !== 'delivered') {
                $flashMessage = 'Return request allowed only for delivered orders.';
                $flashType = 'error';
            } else {
                $withinReturnWindow = true;
                try {
                    $deliveredAt = new DateTimeImmutable($orderCreatedAt);
                    $returnDeadline = $deliveredAt->modify('+10 days');
                    $withinReturnWindow = (new DateTimeImmutable()) <= $returnDeadline;
                } catch (Throwable) {
                    $withinReturnWindow = true;
                }
                if (!$withinReturnWindow) {
                    $flashMessage = 'Return window closed. Return request is allowed only within 10 days of delivery.';
                    $flashType = 'error';
                } else {
                    if ($orderItemId > 0) {
                        $itemSt = $pdo->prepare(
                            'SELECT oi.id, oi.product_id, oi.name, oi.price, oi.qty, p.seller_id
                             FROM order_items oi
                             INNER JOIN products p ON p.id = oi.product_id
                             WHERE oi.id = ? AND oi.order_id = ?
                             LIMIT 1'
                        );
                        $itemSt->execute([$orderItemId, $orderId]);
                    } else {
                        $itemSt = $pdo->prepare(
                            'SELECT oi.id, oi.product_id, oi.name, oi.price, oi.qty, p.seller_id
                             FROM order_items oi
                             INNER JOIN products p ON p.id = oi.product_id
                             WHERE oi.order_id = ? AND oi.name = ?
                             LIMIT 1'
                        );
                        $itemSt->execute([$orderId, $productName]);
                    }
                    $itemRow = $itemSt->fetch();
                    $isValidItem = is_array($itemRow);

                    if (!$isValidItem) {
                        $flashMessage = 'Selected item does not belong to this order.';
                        $flashType = 'error';
                    } else {
                        $orderItemId = (int) ($itemRow['id'] ?? 0);
                        $productId = (int) ($itemRow['product_id'] ?? 0);
                        $sellerId = (int) ($itemRow['seller_id'] ?? 0);
                        $itemPrice = max(0, (int) ($itemRow['price'] ?? 0));
                        $itemQty = max(1, (int) ($itemRow['qty'] ?? 1));
                        $refundAmount = $itemPrice * $itemQty;
                        $refundMode = $orderPaymentMethod !== '' ? $orderPaymentMethod : 'Original payment method';
                        $refundMode = trim($refundMode);
                        if ($refundMode === '') {
                            $refundMode = 'Original payment method';
                        }
                        $productName = trim((string) ($itemRow['name'] ?? $productName));
                        if ($productName === '') {
                            $productName = 'Order item';
                        }
                        if ($orderItemId <= 0 || $sellerId <= 0) {
                            $flashMessage = 'Return request could not be linked to seller item.';
                            $flashType = 'error';
                        } else {
                            $blockStatuses = ['pending', 'approved', 'pickup_scheduled', 'picked_up', 'refund_processing', 'refunded'];
                            $placeholders = implode(',', array_fill(0, count($blockStatuses), '?'));
                            $pendingSt = $pdo->prepare(
                                "SELECT order_item_id, product_name
                                 FROM user_return_requests
                                 WHERE user_id = ?
                                   AND status IN ($placeholders)
                                   AND (order_id = ? OR (COALESCE(order_id, 0) = 0 AND order_ref = ?))"
                            );
                            $pendingSt->execute(array_merge([$uid], $blockStatuses, [$orderId, $orderRef]));
                            $itemNameKey = orders_return_product_name_key($productName);
                            $hasOpenRequest = false;
                            while ($ex = $pendingSt->fetch()) {
                                $eoi = (int) ($ex['order_item_id'] ?? 0);
                                if ($eoi === $orderItemId) {
                                    $hasOpenRequest = true;
                                    break;
                                }
                                if ($eoi === 0 && $itemNameKey !== '') {
                                    $exKey = orders_return_product_name_key((string) ($ex['product_name'] ?? ''));
                                    if ($exKey === $itemNameKey) {
                                        $hasOpenRequest = true;
                                        break;
                                    }
                                }
                            }
                            if ($hasOpenRequest) {
                                $flashMessage = 'Return request already submitted for this item.';
                                $flashType = 'error';
                            } else {
                        if (strlen($details) > 1000) {
                            $details = substr($details, 0, 1000);
                        }
                        if (strlen($reason) > 120) {
                            $reason = substr($reason, 0, 120);
                        }
                                $ins = $pdo->prepare(
                                    'INSERT INTO user_return_requests
                                        (user_id, order_ref, order_id, order_item_id, seller_id, product_id, product_name, reason, details, status, pickup_status, refund_amount, refund_mode)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                                );
                                $ins->execute([
                                    $uid,
                                    $orderRef,
                                    $orderId,
                                    $orderItemId,
                                    $sellerId,
                                    $productId > 0 ? $productId : null,
                                    $productName,
                                    $reason,
                                    $details,
                                    'pending',
                                    'not_scheduled',
                                    $refundAmount,
                                    $refundMode,
                                ]);

                                $flashMessage = 'Return request submitted successfully.';
                                $flashType = 'success';
                            }
                        }
                    }
                }
            }
        }
    } elseif ($action === 'submit_cancel_request') {
        $orderRef = trim((string) ($_POST['order_ref'] ?? ''));
        $reason = trim((string) ($_POST['cancel_reason'] ?? ''));
        $details = trim((string) ($_POST['cancel_details'] ?? ''));

        if ($orderRef === '' || $reason === '') {
            $flashMessage = 'Please select cancel reason.';
            $flashType = 'error';
        } else {
            $ownOrderSt = $pdo->prepare('SELECT id, status FROM orders WHERE user_id = ? AND order_ref = ? LIMIT 1');
            $ownOrderSt->execute([$uid, $orderRef]);
            $ownOrder = $ownOrderSt->fetch();
            $orderId = (int) ($ownOrder['id'] ?? 0);
            $orderStatus = strtolower((string) ($ownOrder['status'] ?? ''));

            if ($orderId <= 0 || !in_array($orderStatus, ['processing', 'confirmed', 'shipped'], true)) {
                $flashMessage = 'Cancel request allowed only before out for delivery.';
                $flashType = 'error';
            } else {
                $pendingSt = $pdo->prepare(
                    'SELECT id FROM user_order_cancel_requests
                     WHERE user_id = ? AND order_id = ? AND status = ?
                     LIMIT 1'
                );
                $pendingSt->execute([$uid, $orderId, 'pending']);
                $hasPending = (bool) $pendingSt->fetchColumn();

                if ($hasPending) {
                    $flashMessage = 'Cancel request already pending for this order.';
                    $flashType = 'error';
                } else {
                    if (strlen($reason) > 120) {
                        $reason = substr($reason, 0, 120);
                    }
                    if (strlen($details) > 1000) {
                        $details = substr($details, 0, 1000);
                    }

                    $sellerIdsSt = $pdo->prepare(
                        'SELECT DISTINCT p.seller_id
                         FROM order_items oi
                         INNER JOIN products p ON p.id = oi.product_id
                         WHERE oi.order_id = ? AND p.seller_id IS NOT NULL'
                    );
                    $sellerIdsSt->execute([$orderId]);
                    $sellerIds = array_filter(array_map('intval', $sellerIdsSt->fetchAll(PDO::FETCH_COLUMN)));

                    if ($sellerIds === []) {
                        $flashMessage = 'No seller found for this order items.';
                        $flashType = 'error';
                    } else {
                        $ins = $pdo->prepare(
                            'INSERT INTO user_order_cancel_requests
                                (user_id, order_id, seller_id, order_ref, reason, details, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?)'
                        );
                        foreach ($sellerIds as $sellerId) {
                            $ins->execute([$uid, $orderId, (int) $sellerId, $orderRef, $reason, $details, 'pending']);
                        }
                        $flashMessage = 'Cancel request submitted. Seller review karega.';
                        $flashType = 'success';
                    }
                }
            }
        }
    }
}

$ordersData = $uid ? orders_fetch_for_user($pdo, $uid) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LUXE — My Orders</title>
  <meta name="description" content="Track and manage your LUXE orders." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
  <style>
    .orders-flash { margin-bottom: 14px; padding: 10px 12px; border-radius: 10px; font-size: 0.88rem; font-weight: 600; }
    .orders-flash--success { background: rgba(16, 185, 129, 0.14); border: 1px solid rgba(16, 185, 129, 0.35);  }
    .orders-flash--error { background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35);  }
    .orders-modal-form { display: grid; gap: 10px; }
    .orders-modal-form label { display: block; margin-bottom: 6px; font-size: 0.83rem; color: var(--text-muted); font-weight: 600; }
    .orders-modal-form select,
    .orders-modal-form textarea {
      width: 100%;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px 12px;
      font: inherit;
      background: var(--bg3);
      color: var(--text);
    }
    .orders-modal-form textarea { min-height: 100px; resize: vertical; }
    .action-btn.is-disabled,
    .action-btn:disabled {
      opacity: 0.5;
      pointer-events: none;
      cursor: not-allowed;
    }
  </style>
</head>
<body>
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="bg-scene"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="grid-lines"></div></div>

  <nav class="navbar" id="navbar">
    <div class="nav-container">
      <a href="index.php" class="nav-logo">LUXE</a>
      <div class="nav-breadcrumb">
        <a href="index.php">Home</a><span>/</span>
        <span class="breadcrumb-current">My Orders</span>
      </div>
      <div class="nav-actions">
        <?php if ($userLoggedIn): ?>
        <a href="profile.php" class="nav-icon-link" aria-label="Profile">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php endif; ?>
        <a href="cart.php" class="nav-icon-link" aria-label="Cart" style="position:relative">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          <span class="nav-cart-dot" id="cartCount"><?= (int) $cartNavCount ?></span>
        </a>
        <?php if ($userLoggedIn): ?>
        <a href="actions/logout.php" class="nav-login-btn" aria-label="Sign out">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign Out
        </a>
        <?php else: ?>
        <a href="login.php" class="nav-login-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Sign In
        </a>
        <?php endif; ?>
      </div>
    </div>
  </nav>

  <main class="page-main">
    <div class="container">

      <div class="page-header">
        <h1>My Orders <span class="count-badge" id="ordersBadge">6 orders</span></h1>
        <a href="index.php" class="continue-link">← Back to Shopping</a>
      </div>
      <?php if ($flashMessage !== ''): ?>
        <div class="orders-flash<?= $flashType === 'success' ? ' orders-flash--success' : ' orders-flash--error' ?>">
          <?= h($flashMessage) ?>
        </div>
      <?php endif; ?>

      <!-- Tabs & Search -->
      <div class="orders-controls">
        <div class="orders-tabs">
          <button class="otab active" data-status="all" onclick="filterOrders(this)">All</button>
          <button class="otab" data-status="returns" onclick="filterOrders(this)">Returns</button>
          <button class="otab" data-status="confirmed" onclick="filterOrders(this)">Confirmed</button>
          <button class="otab" data-status="out" onclick="filterOrders(this)">Out for delivery</button>
          <button class="otab" data-status="delivered" onclick="filterOrders(this)">Delivered</button>
          <button class="otab" data-status="shipped" onclick="filterOrders(this)">Shipped</button>
          <button class="otab" data-status="processing" onclick="filterOrders(this)">Processing</button>
          <button class="otab" data-status="cancelled" onclick="filterOrders(this)">Cancelled</button>
        </div>
        <div class="orders-search">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" id="orderSearch" placeholder="Search orders..." oninput="searchOrders(this.value)" />
        </div>
      </div>

      <!-- Orders List -->
      <div id="ordersList"></div>

      <!-- Empty State -->
      <div class="empty-orders hidden" id="emptyOrders">
        <div class="empty-emoji">📦</div>
        <h2>No orders found</h2>
        <p>Start shopping to see your orders here!</p>
        <a href="index.php" class="checkout-btn" style="display:inline-flex;max-width:220px;justify-content:center">Shop Now →</a>
      </div>

    </div>
  </main>

  <!-- Order Detail Modal -->
  <div class="modal-overlay hidden" id="detailModal">
    <div class="modal-card detail-modal">
      <div class="modal-header">
        <div>
          <h3>Order Details</h3>
          <span class="modal-order-id" id="detailOrderId"></span>
        </div>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
      <div id="detailContent"></div>
    </div>
  </div>

  <div class="modal-overlay hidden" id="orderReviewModal">
    <div class="modal-card detail-modal">
      <div class="modal-header">
        <div>
          <h3>Rate & Review</h3>
          <span class="modal-order-id" id="reviewOrderId"></span>
        </div>
        <button class="modal-close" type="button" onclick="closeOrderReviewModal()">✕</button>
      </div>
      <form method="post" class="orders-modal-form">
        <input type="hidden" name="action" value="submit_order_review">
        <input type="hidden" name="order_ref" id="reviewOrderRef" value="">
        <div>
          <label for="reviewProductId">Product</label>
          <select id="reviewProductId" name="product_id" required></select>
        </div>
        <div>
          <label for="reviewRating">Rating</label>
          <select id="reviewRating" name="rating" required>
            <option value="5">5 Stars</option>
            <option value="4">4 Stars</option>
            <option value="3">3 Stars</option>
            <option value="2">2 Stars</option>
            <option value="1">1 Star</option>
          </select>
        </div>
        <div>
          <label for="reviewText">Review</label>
          <textarea id="reviewText" name="review_text" maxlength="1000" placeholder="Share your experience..." required></textarea>
        </div>
        <div>
          <button class="checkout-btn" type="submit" style="max-width:none">Submit review</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-overlay hidden" id="orderReturnModal">
    <div class="modal-card detail-modal">
      <div class="modal-header">
        <div>
          <h3>Return Request</h3>
          <span class="modal-order-id" id="returnOrderId"></span>
        </div>
        <button class="modal-close" type="button" onclick="closeOrderReturnModal()">✕</button>
      </div>
      <form method="post" class="orders-modal-form">
        <input type="hidden" name="action" value="submit_return_request">
        <input type="hidden" name="order_ref" id="returnOrderRef" value="">
        <input type="hidden" name="product_name" id="returnProductNameText" value="">
        <div>
          <label for="returnProductName">Item</label>
          <select id="returnProductName" name="order_item_id" required></select>
        </div>
        <div>
          <label for="returnReason">Reason</label>
          <select id="returnReason" name="return_reason" required>
            <option value="">Select reason</option>
            <option value="Damaged product">Damaged product</option>
            <option value="Wrong item delivered">Wrong item delivered</option>
            <option value="Quality not as expected">Quality not as expected</option>
            <option value="Size/Fit issue">Size/Fit issue</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div>
          <label for="returnDetails">Details</label>
          <textarea id="returnDetails" name="return_details" maxlength="1000" placeholder="Add more details (optional)"></textarea>
        </div>
        <div>
          <button class="checkout-btn" type="submit" style="max-width:none">Submit return request</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-overlay hidden" id="orderCancelModal">
    <div class="modal-card detail-modal">
      <div class="modal-header">
        <div>
          <h3>Cancel Order Request</h3>
          <span class="modal-order-id" id="cancelOrderId"></span>
        </div>
        <button class="modal-close" type="button" onclick="closeOrderCancelModal()">✕</button>
      </div>
      <form method="post" class="orders-modal-form">
        <input type="hidden" name="action" value="submit_cancel_request">
        <input type="hidden" name="order_ref" id="cancelOrderRef" value="">
        <div>
          <label for="cancelReason">Reason</label>
          <select id="cancelReason" name="cancel_reason" required>
            <option value="">Select reason</option>
            <option value="Changed my mind">Changed my mind</option>
            <option value="Ordered by mistake">Ordered by mistake</option>
            <option value="Need faster delivery">Need faster delivery</option>
            <option value="Found better price elsewhere">Found better price elsewhere</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div>
          <label for="cancelDetails">Details</label>
          <textarea id="cancelDetails" name="cancel_details" maxlength="1000" placeholder="Optional details for seller"></textarea>
        </div>
        <div>
          <button class="checkout-btn" type="submit" style="max-width:none">Submit cancel request</button>
        </div>
      </form>
    </div>
  </div>

  <div class="toast" id="toast"></div>
  <script>
    window.LUXE_URLS = <?= json_encode([
        'home' => 'index.php',
        'login' => 'login.php',
        'cart' => 'cart.php',
        'product' => 'product.php',
        'orders' => 'orders.php',
        'profile' => 'profile.php',
    ], JSON_THROW_ON_ERROR) ?>;
    window.__API_CART__ = 'api/cart.php';
    window.__CART_COUNT__ = <?= (int) $cartNavCount ?>;
    window.__ORDERS__ = <?= json_encode($ordersData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
