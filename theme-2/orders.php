<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$user = auth_user($pdo);
if (!$user) {
    header('Location: login.php?redirect=' . rawurlencode('orders.php'));
    exit;
}

$allOrders = orders_fetch_for_user($pdo, (int) ($user['id'] ?? 0));
$filter = strtolower(trim($_GET['filter'] ?? 'all'));
$search = strtolower(trim($_GET['search'] ?? ''));

$orders = [];
foreach ($allOrders as $ord) {
    $ordStatus = strtolower(trim((string) ($ord['status'] ?? 'processing')));
    $statusClass = 'processing';
    if ($ordStatus === 'delivered' || $ordStatus === 'completed') $statusClass = 'delivered';
    elseif ($ordStatus === 'processing' || $ordStatus === 'pending') $statusClass = 'processing';
    elseif ($ordStatus === 'shipped' || $ordStatus === 'out_for_delivery' || $ordStatus === 'out for delivery') $statusClass = 'shipped';
    elseif ($ordStatus === 'cancelled') $statusClass = 'cancelled';
    
    $keep = true;
    if ($filter !== 'all') {
        if ($filter === 'returns') {
            $hasReturn = false;
            foreach ($ord['items'] ?? [] as $item) {
                if (!empty($item['returnRequest'])) { $hasReturn = true; break; }
            }
            if (!$hasReturn) $keep = false;
        } else {
            if ($statusClass !== $filter) $keep = false;
        }
    }
    
    if ($keep && $search !== '') {
        $found = false;
        if (strpos(strtolower((string)$ord['id']), $search) !== false) $found = true;
        foreach ($ord['items'] ?? [] as $item) {
            if (strpos(strtolower((string)$item['name']), $search) !== false) $found = true;
        }
        if (!$found) $keep = false;
    }
    
    if ($keep) $orders[] = $ord;
}

$limit = 5;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalOrders = count($orders);
$totalPages = max(1, (int)ceil($totalOrders / $limit));
if ($page > $totalPages) $page = max(1, $totalPages);
$offset = ($page - 1) * $limit;
$pagedOrders = array_slice($orders, $offset, $limit);

$qs = [];
if ($filter !== 'all') $qs['filter'] = $filter;
if ($search !== '') $qs['search'] = $search;
$qstr = empty($qs) ? '' : '&' . http_build_query($qs);

$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $item) {
    $cartCount += (int) ($item['qty'] ?? 1);
}
$fullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($fullName === '') {
    $fullName = 'Member';
}
$initial = strtoupper(substr((string) ($user['first_name'] ?? $fullName), 0, 1));
$isLoggedIn = true;
$userInitials = $initial;
$userName = $fullName;
$userEmail = trim((string) ($user['email'] ?? ''));
$theme2LoginHref = 'login.php?redirect=' . rawurlencode('orders.php');
$theme2HeaderCategories = ["Men's Fashion", "Women's Fashion", "Kid's Fashion", 'Footwear'];
$theme2HeaderCompareCount = 0;
$theme2HeaderCartCount = $cartCount;
$theme2FooterCategories = $theme2HeaderCategories;

$memberSince = 'Recently joined';
$createdAt = (string) ($user['created_at'] ?? '');
if ($createdAt !== '' && strtotime($createdAt) !== false) {
    $memberSince = 'Member since ' . date('M Y', strtotime($createdAt));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders — LUXE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="profile-page-wrap t2-account-layout">
  <?php require __DIR__ . '/partials/header.php'; ?>
  <main>
    <section class="profile-shell profile-shell--full">
      <!-- Main Content -->
      <article class="profile-main">
        <div class="profile-main-head">
          <div>
            <h2 style="font-size:32px; font-weight:800; color:#0f172a; margin-bottom:8px; display:flex; align-items:center; gap:12px;">
              <div style="width:48px; height:48px; background:rgba(183, 155, 108, 0.1); border-radius:14px; display:flex; align-items:center; justify-content:center; color:var(--theme-color);">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8V20.9932C21 21.5501 20.5552 22 20.0066 22H3.9934C3.44476 22 3 21.5501 3 20.9932V8L12 3L21 8Z"/><path d="M3 8L12 13L21 8"/><path d="M12 13V22"/></svg>
              </div>
              My Orders
            </h2>
            <p style="color:#64748b; font-size:14px; font-weight:500;">Manage your orders and track their delivery status.</p>
          </div>
          <a class="t2-order-btn t2-order-btn-outline" href="index.php" style="border-radius:14px; padding:10px 20px;">
            Continue shopping
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
        </div>
        
        <?php if (!empty($_GET['placed'])): ?>
          <p class="profile-edit-msg is-success" style="margin-bottom:20px;">🎉 Order placed successfully: <strong><?= h((string) $_GET['placed']) ?></strong></p>
        <?php endif; ?>

        <?php if ($allOrders === []): ?>
          <div class="t2-review-empty" style="margin-top:20px;">
            <span class="t2-review-empty__icon">🛍️</span>
            <h3>No orders yet</h3>
            <p>You haven't placed any orders. Start exploring our premium collection.</p>
            <a href="index.php" class="profile-edit-btn" style="display:inline-block;margin-top:16px;text-decoration:none;">Start Shopping</a>
          </div>
        <?php else: ?>
        <div class="t2-orders-top-bar">
            <div class="t2-orders-filters">
              <a href="?filter=all<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t2-orders-filter <?= $filter === 'all' ? 'active' : '' ?>">All Orders</a>
              <a href="?filter=returns<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t2-orders-filter <?= $filter === 'returns' ? 'active' : '' ?>">Returns</a>
              <a href="?filter=delivered<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t2-orders-filter <?= $filter === 'delivered' ? 'active' : '' ?>">Delivered</a>
              <a href="?filter=shipped<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t2-orders-filter <?= $filter === 'shipped' ? 'active' : '' ?>">Shipped</a>
              <a href="?filter=processing<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t2-orders-filter <?= $filter === 'processing' ? 'active' : '' ?>">Processing</a>
              <a href="?filter=cancelled<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t2-orders-filter <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
            </div>
            <form class="t2-orders-search" method="GET" action="">
              <?php if ($filter !== 'all'): ?>
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
              <?php endif; ?>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:#94a3b8;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" name="search" placeholder="Search by ID or Product name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
              <button type="submit" style="display:none;"></button>
            </form>
          </div>

          <?php if ($orders === []): ?>
            <div class="t2-review-empty" style="margin-top:20px; padding:60px 40px; background:#fff; border:1px solid #e2e8f0; border-radius:24px; text-align:center; box-shadow: var(--shadow-sm);">
              <div style="width:80px; height:80px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 24px;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </div>
              <h3 style="margin-top:0; color:#1e293b; font-size:20px; font-weight:800;">No results found</h3>
              <p style="color:#64748b; margin-bottom:24px;">We couldn't find any orders matching your criteria.</p>
              <?php if ($filter !== 'all' || $search !== ''): ?>
                <a href="orders.php" class="t2-order-btn t2-order-btn-primary" style="text-decoration:none;">Clear All Filters</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="t2-orders-list">
            <?php foreach ($pagedOrders as $ord): 
              $hasUnreviewedItems = false;
              $hasUnreturnedItems = false;
              foreach ($ord['items'] ?? [] as $item) {
                  if (empty($item['hasReview'])) $hasUnreviewedItems = true;
                  if (empty($item['returnRequest'])) $hasUnreturnedItems = true;
              }
              $ordStatus = strtolower(trim((string) ($ord['status'] ?? 'processing')));
              $statusClass = 'processing';
              $statusIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
              $statusLabel = 'Processing';
              $stepIdx = 0;
              
              if ($ordStatus === 'delivered' || $ordStatus === 'completed') { 
                  $statusClass = 'delivered'; 
                  $statusIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'; 
                  $statusLabel = 'Delivered'; $stepIdx = 4;
              } elseif ($ordStatus === 'processing' || $ordStatus === 'pending') { 
                  $statusClass = 'processing'; 
                  $statusIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                  $statusLabel = 'Processing'; $stepIdx = 0;
              } elseif ($ordStatus === 'shipped') { 
                  $statusClass = 'shipped'; 
                  $statusIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
                  $statusLabel = 'Shipped'; $stepIdx = 2;
              } elseif ($ordStatus === 'out_for_delivery' || $ordStatus === 'out for delivery') { 
                  $statusClass = 'shipped'; 
                  $statusIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
                  $statusLabel = 'Out for Delivery'; $stepIdx = 3;
              } elseif ($ordStatus === 'cancelled') { 
                  $statusClass = 'cancelled'; 
                  $statusIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                  $statusLabel = 'Cancelled'; $stepIdx = -1;
              }
            ?>
              <div class="t2-order-card">
                <div class="t2-order-card-header">
                  <div class="t2-order-header-left">
                    <span class="t2-order-id-badge">ORDER ID</span>
                    <span class="t2-order-id-val">#<?= h((string) ($ord['id'] ?? '')) ?></span>
                    <span class="t2-order-date">Placed on <?= h((string) ($ord['date'] ?? '')) ?></span>
                  </div>
                  <div class="t2-order-header-actions">
                    <?php
                      $cReq = $ord['cancelRequest'] ?? null;
                      $cStatus = $cReq ? strtolower((string)$cReq['status']) : null;
                      $canCancel = in_array(strtolower((string)$ord['status']), ['processing', 'pending', 'shipped']);
                    ?>
                    <?php if ($cStatus === 'pending'): ?>
                        <button type="button" disabled class="t2-cancel-btn t2-cancel-btn--pending">Cancel Pending</button>
                    <?php elseif ($cStatus === 'rejected'): ?>
                        <button type="button" disabled class="t2-cancel-btn t2-cancel-btn--rejected">Cancel Rejected</button>
                    <?php elseif ($canCancel && !$cStatus): ?>
                        <button type="button" onclick="openCancelModal('<?= h((string)$ord['id']) ?>')" class="t2-cancel-btn">Cancel Order</button>
                    <?php endif; ?>
                    <div class="t2-order-status <?= $statusClass ?>">
                      <?= $statusIcon ?> <?= $statusLabel ?>
                    </div>
                  </div>
                </div>

                <div class="t2-order-card-body">
                  <div class="t2-order-item-wrap">
                    <?php foreach (($ord['items'] ?? []) as $it): 
                      $itemName = h((string) ($it['name'] ?? 'Item'));
                      $itemQty = (int) ($it['qty'] ?? 1);
                      $rawImg = trim((string) ($it['image'] ?? ''));
                      $itemImg = '';
                      if ($rawImg !== '') {
                          if (preg_match('~^(https?:)?//~i', $rawImg) || str_starts_with($rawImg, '/')) {
                              $itemImg = $rawImg;
                          } elseif (str_starts_with($rawImg, '../')) {
                              $itemImg = $rawImg;
                          } else {
                              $itemImg = luxe_public_href(ltrim($rawImg, '/'));
                          }
                      }
                    ?>
                      <div class="t2-order-item-row t2-order-item-row--col">
                        <div class="t2-order-item-row__inner">
                          <div class="t2-order-item-thumb">
                            <?php if ($itemImg): ?>
                              <img src="<?= h($itemImg) ?>" alt="<?= $itemName ?>" loading="lazy">
                            <?php else: ?>
                              <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#f8fafc; color:#cbd5e1;">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8V20.9932C21 21.5501 20.5552 22 20.0066 22H3.9934C3.44476 22 3 21.5501 3 20.9932V8L12 3L21 8Z"/><path d="M3 8L12 13L21 8"/><path d="M12 13V22"/></svg>
                              </div>
                            <?php endif; ?>
                          </div>
                          <div class="t2-order-item-details">
                            <strong><?= $itemName ?></strong>
                            <span>Quantity: <?= $itemQty ?></span>
                            <?php if ($statusClass === 'cancelled'): ?>
                              <span class="t2-order-item-status-msg">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                Cancelled
                              </span>
                            <?php endif; ?>
                          </div>
                          <?php if ($statusClass === 'delivered' && !empty($it['canReview'])): ?>
                             <button type="button" class="t2-order-btn t2-order-btn-outline t2-order-review-btn review-btn-<?= $it['productId'] ?>" onclick="openReviewModal('<?= h((string)$ord['id']) ?>', '<?= $it['productId'] ?>', '<?= h(addslashes($itemName)) ?>')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Rate & Review
                             </button>
                          <?php endif; ?>
                        </div>
                        <?php if (!empty($item['returnRequest'])): 
                            $rr = $item['returnRequest'];
                            $rStatus = strtolower(trim((string)($rr['status'] ?? 'pending')));
                            $pStatus = strtolower(trim((string)($rr['pickupStatus'] ?? 'not_scheduled')));
                            
                            $rStepIdx = 0;
                            $rSteps = ['Requested', 'Approved', 'Picked Up', 'Refunded'];
                            $isRejected = ($rStatus === 'rejected');
                            if ($isRejected) {
                                $rSteps = ['Requested', 'Rejected', '', ''];
                                $rStepIdx = 1;
                            } else {
                                if ($rStatus === 'approved') $rStepIdx = 1;
                                if ($pStatus === 'picked_up') $rStepIdx = 2;
                                if ($rStatus === 'resolved' || $rStatus === 'refunded' || $rStatus === 'completed') $rStepIdx = 3;
                            }
                            $rProgressWidth = ($rStepIdx / 3) * 100;
                        ?>
                        <div class="t2-order-item-progress t2-return-progress-box">
                          <div class="t2-order-item-progress-title t2-return-progress-title">
                            <span>✦</span> RETURN PROGRESS
                          </div>
                          <div class="t2-progress-bar-container">
                            <div class="t2-progress-bar-line"></div>
                            <div class="t2-progress-bar-fill" style="width: <?= $rProgressWidth ?>%; <?= $isRejected ? 'background: linear-gradient(90deg, #f87171, #dc2626); box-shadow: 0 0 12px rgba(220, 38, 38, 0.4);' : '' ?>"></div>
                            <?php foreach ($rSteps as $idx => $label): 
                               if ($label === '') continue;
                               $stepState = '';
                               if ($idx < $rStepIdx) $stepState = 'completed';
                               elseif ($idx === $rStepIdx) $stepState = 'current';
                               
                               $dotStyle = '';
                               if ($isRejected && $idx === 1) {
                                   $dotStyle = 'background: linear-gradient(135deg, #f87171, #dc2626); box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.15); border-color:#fff;';
                               } elseif ($stepState === 'completed' || $stepState === 'current') {
                                   $dotStyle = ''; // Uses default gold styles from CSS
                               }
                            ?>
                              <div class="t2-progress-step <?= $stepState ?>">
                                <div class="t2-progress-dot" style="<?= $dotStyle ?>">
                                  <?php if ($idx < $rStepIdx): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                  <?php elseif ($idx === $rStepIdx && $isRejected): ?>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                  <?php else: ?>
                                    <?= $idx + 1 ?>
                                  <?php endif; ?>
                                </div>
                                <div class="t2-progress-label"><?= $label ?></div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                          <div class="t2-return-info-box" style="margin-top:20px; padding:16px; background:rgba(255,255,255,0.5); border-radius:12px; font-size:13px; color:#475569; border:1px solid #e2e8f0;">
                             <div style="margin-bottom:4px;"><strong style="color:#1e293b;">Reason:</strong> <?= htmlspecialchars($rr['reason']) ?></div>
                             <div><strong style="color:#1e293b;">Refund:</strong> <span style="color:var(--theme-color); font-weight:800;">₹<?= number_format((int)$rr['refundAmount']) ?></span> (<?= htmlspecialchars($rr['refundMode']) ?>)</div>
                          </div>
                        </div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  
                  <?php if ($stepIdx >= 0): 
                     $progressWidth = ($stepIdx / 4) * 100;
                     $steps = ['Ordered', 'Confirmed', 'Shipped', 'Out for Delivery', 'Delivered'];
                  ?>
                  <div class="t2-order-item-progress" style="margin-top:24px;">
                    <div class="t2-order-item-progress-title">
                      <span>✦</span> ORDER JOURNEY
                    </div>
                    <div class="t2-progress-bar-container">
                      <div class="t2-progress-bar-line"></div>
                      <div class="t2-progress-bar-fill" style="width: <?= $progressWidth ?>%;"></div>
                      <?php foreach ($steps as $idx => $label): 
                         $stepState = '';
                         if ($idx < $stepIdx) $stepState = 'completed';
                         elseif ($idx === $stepIdx) $stepState = 'current';
                      ?>
                        <div class="t2-progress-step <?= $stepState ?>">
                          <div class="t2-progress-dot">
                            <?php if ($idx < $stepIdx): ?>
                              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php else: ?>
                              <?= $idx + 1 ?>
                            <?php endif; ?>
                          </div>
                          <div class="t2-progress-label"><?= $label ?></div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                </div>

                <div class="t2-order-card-footer">
                  <div class="t2-order-footer-left">
                    <span>TOTAL AMOUNT PAID</span>
                    <strong>₹<?= number_format((int) ($ord['total'] ?? 0)) ?></strong>
                  </div>
                  <div class="t2-order-actions">
                    <?php if ($statusClass === 'delivered'): ?>
                      <?php if ($hasUnreturnedItems): ?>
                        <button type="button" class="t2-order-btn t2-order-btn-outline">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 17l-5-5 5-5"/><path d="M18 17l-5-5 5-5"/></svg>
                          Return
                        </button>
                      <?php endif; ?>
                      <a href="download-invoice.php?order_ref=<?= urlencode((string)$ord['id']) ?>" target="_blank" class="t2-order-btn t2-order-btn-outline t2-order-invoice-link">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Invoice
                      </a>
                    <?php endif; ?>
                    <button type="button" class="t2-order-btn t2-order-btn-primary" onclick="document.getElementById('modal-<?= $ord['id'] ?>').classList.remove('hidden')">
                      View Details
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                  </div>
                </div>
              </div>
              

            <?php endforeach; ?>
          </div>

          <?php if ($totalPages > 1): ?>
            <div class="t2-pagination">
              <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $qstr ?>" class="t2-page-btn">Previous</a>
              <?php else: ?>
                <span class="t2-page-btn disabled">Previous</span>
              <?php endif; ?>
              
              <div class="t2-page-numbers">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <a href="?page=<?= $i ?><?= $qstr ?>" class="t2-page-num <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
              </div>
              
              <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?><?= $qstr ?>" class="t2-page-btn">Next</a>
              <?php else: ?>
                <span class="t2-page-btn disabled">Next</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
      </article>
    </section>
  </main>

  <!-- Modals rendered outside so they overlay the whole page correctly -->
  <?php if (!empty($pagedOrders)): foreach ($pagedOrders as $ord): ?>
  <div class="theme2-modal-overlay hidden" id="modal-<?= $ord['id'] ?>" onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="theme2-modal-card" style="max-width:700px;">
      <div class="theme2-modal-header" style="background:#f8fafc; border-radius:28px 28px 0 0;">
        <h3 style="display:flex; align-items:center; gap:12px;">
          <div style="width:36px; height:36px; background:#fff; border-radius:10px; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,0.05); color:var(--theme-color);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8V20.9932C21 21.5501 20.5552 22 20.0066 22H3.9934C3.44476 22 3 21.5501 3 20.9932V8L12 3L21 8Z"/><path d="M3 8L12 13L21 8"/><path d="M12 13V22"/></svg>
          </div>
          Order #<?= htmlspecialchars($ord['id']) ?>
        </h3>
        <button type="button" class="theme2-modal-close" onclick="document.getElementById('modal-<?= $ord['id'] ?>').classList.add('hidden')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="theme2-modal-body t2-modal-body-scroll" style="padding:0;">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1px; background:#f1f5f9; border-bottom:1px solid #f1f5f9;">
          <div style="background:#fff; padding:24px;">
            <span style="font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; display:block; margin-bottom:8px;">Order Date</span>
            <strong style="color:#0f172a; font-size:15px;"><?= htmlspecialchars($ord['date']) ?></strong>
          </div>
          <div style="background:#fff; padding:24px;">
            <span style="font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; display:block; margin-bottom:8px;">Payment Mode</span>
            <strong style="color:#0f172a; font-size:15px;"><?= htmlspecialchars($ord['payment']) ?></strong>
          </div>
          <div style="background:#fff; padding:24px;">
            <span style="font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; display:block; margin-bottom:8px;">Current Status</span>
            <div class="t2-order-status <?= strtolower($ord['status']) ?>" style="padding:4px 12px; font-size:11px;">
               <?= htmlspecialchars($ord['status']) ?>
            </div>
          </div>
        </div>
        
        <div style="padding:24px; background:#fff; border-bottom:1px solid #f1f5f9;">
           <span style="font-size:10px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; display:block; margin-bottom:8px;">Shipping Address</span>
           <p style="color:#475569; font-size:14px; line-height:1.6; margin:0;"><?= htmlspecialchars($ord['address']) ?></p>
        </div>

        <div style="padding:24px;">
           <h4 style="font-size:14px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:1px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
             <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--theme-color);"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
             Items in Order
           </h4>
           <div style="display:flex; flex-direction:column; gap:16px;">
              <?php foreach ($ord['items'] as $item): ?>
                 <div style="display:flex; align-items:center; gap:16px; padding:12px; border:1px solid #f1f5f9; border-radius:16px; transition:all 0.2s;" onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';" onmouseout="this.style.background='transparent'; this.style.borderColor='#f1f5f9';">
                   <div style="width:54px; height:54px; background:#f1f5f9; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px;">
                     <?= htmlspecialchars($item['emoji']) ?>
                   </div>
                   <div style="flex:1;">
                     <strong style="font-size:14px; color:#1e293b; display:block;"><?= htmlspecialchars($item['name']) ?></strong>
                     <span style="font-size:12px; color:#94a3b8; font-weight:600;">Qty: <?= $item['qty'] ?></span>
                   </div>
                   <strong style="font-size:15px; color:#0f172a;">₹<?= number_format($item['lineTotal']) ?></strong>
                 </div>
              <?php endforeach; ?>
           </div>
        </div>

        <div style="padding:24px; background:#f8fafc; border-top:1px solid #f1f5f9; border-radius:0 0 28px 28px; display:flex; justify-content:space-between; align-items:center;">
           <span style="font-size:12px; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">Grand Total</span>
           <strong style="font-size:24px; font-weight:800; color:var(--theme-color); font-family:'Outfit', sans-serif;">₹<?= number_format($ord['total']) ?></strong>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>

  <!-- Review Modal -->
  <div class="theme2-modal-overlay hidden t2-modal-overlay--top" id="reviewModal">
    <div class="theme2-modal-card t2-modal-card--sm">
      <h3 class="t2-modal-heading">Rate & Review</h3>
      <p id="reviewProductName" class="t2-modal-subtext t2-modal-subtext--bold"></p>
      
      <form id="reviewOrderForm">
        <input type="hidden" id="reviewOrderRef" value="">
        <input type="hidden" id="reviewProductId" value="">
        <div class="t2-modal-field-group">
          <label class="t2-modal-label">Rating</label>
          <select id="reviewRating" required class="t2-modal-select">
            <option value="5">⭐⭐⭐⭐⭐ 5 Stars</option>
            <option value="4">⭐⭐⭐⭐ 4 Stars</option>
            <option value="3">⭐⭐⭐ 3 Stars</option>
            <option value="2">⭐⭐ 2 Stars</option>
            <option value="1">⭐ 1 Star</option>
          </select>
        </div>
        <div class="t2-modal-field-group t2-modal-field-group--lg">
          <label class="t2-modal-label">Your Review</label>
          <textarea id="reviewText" required minlength="10" maxlength="1000" rows="4" class="t2-modal-textarea" placeholder="Share your experience with this product..."></textarea>
        </div>
        
        <p id="reviewMsg" class="hidden t2-modal-msg"></p>

        <div class="t2-modal-actions">
          <button type="button" class="profile-edit-cancel" onclick="closeReviewModal()">Cancel</button>
          <button type="submit" class="profile-edit-btn" id="reviewSubmitBtn">Submit Review</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const LUXE_ACT = <?= json_encode(luxe_actions_root_url(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
    var reviewModal = document.getElementById('reviewModal');
    var reviewOrderRef = document.getElementById('reviewOrderRef');
    var reviewProductId = document.getElementById('reviewProductId');
    var reviewProductName = document.getElementById('reviewProductName');
    var reviewForm = document.getElementById('reviewOrderForm');
    var reviewMsg = document.getElementById('reviewMsg');
    var reviewSubmitBtn = document.getElementById('reviewSubmitBtn');

    window.openReviewModal = function(orderRef, productId, productName) {
      reviewOrderRef.value = orderRef;
      reviewProductId.value = productId;
      reviewProductName.textContent = productName;
      reviewForm.reset();
      reviewMsg.className = 'hidden';
      reviewModal.classList.remove('hidden');
    };

    window.closeReviewModal = function() {
      reviewModal.classList.add('hidden');
    };

    if (reviewForm) {
      reviewForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        var orderRef = reviewOrderRef.value;
        var productId = reviewProductId.value;
        var rating = document.getElementById('reviewRating').value;
        var reviewText = document.getElementById('reviewText').value.trim();

        if (reviewText.length < 10) {
          reviewMsg.textContent = 'Please write at least 10 characters.';
          reviewMsg.className = 'profile-edit-msg is-error';
          return;
        }

        reviewSubmitBtn.disabled = true;
        reviewSubmitBtn.style.opacity = '0.5';
        reviewSubmitBtn.textContent = 'Submitting...';

        try {
          var res = await fetch(LUXE_ACT + 'submit-review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_ref: orderRef, product_id: productId, rating: rating, review_text: reviewText })
          });
          var data = await res.json();
          if (!res.ok || !data.ok) {
            throw new Error(data.message || 'Could not submit review.');
          }
          
          reviewMsg.textContent = data.message || 'Review submitted successfully.';
          reviewMsg.className = 'profile-edit-msg is-success';
          
          // Hide all buttons for this product id
          document.querySelectorAll('.review-btn-' + productId).forEach(b => b.style.display = 'none');
          
          setTimeout(() => { closeReviewModal(); }, 1500);

        } catch (err) {
          reviewMsg.textContent = err.message;
          reviewMsg.className = 'profile-edit-msg is-error';
        } finally {
          reviewSubmitBtn.disabled = false;
          reviewSubmitBtn.style.opacity = '1';
          reviewSubmitBtn.textContent = 'Submit Review';
        }
      });
    }
  </script>

  <div class="theme2-modal-overlay hidden t2-modal-overlay--top" id="cancelModal">
    <div class="theme2-modal-card t2-modal-card--sm">
      <h3 class="t2-modal-heading t2-modal-heading--lg">Cancel Order</h3>
      <p class="t2-modal-subtext">Are you sure you want to cancel this order? Please provide a reason.</p>
      
      <form id="cancelOrderForm">
        <input type="hidden" id="cancelOrderRef" value="">
        <div class="t2-modal-field-group">
          <label class="t2-modal-label">Reason for Cancellation</label>
          <select id="cancelReason" required class="t2-modal-select">
            <option value="">Select a reason...</option>
            <option value="Changed my mind">Changed my mind</option>
            <option value="Ordered by mistake">Ordered by mistake</option>
            <option value="Found better price elsewhere">Found better price elsewhere</option>
            <option value="Delivery is taking too long">Delivery is taking too long</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="t2-modal-field-group t2-modal-field-group--lg">
          <label class="t2-modal-label">Additional Details (Optional)</label>
          <textarea id="cancelDetails" rows="3" class="t2-modal-textarea" placeholder="Anything else you'd like us to know?"></textarea>
        </div>
        
        <p id="cancelMsg" class="hidden t2-modal-msg"></p>

        <div class="t2-modal-actions">
          <button type="button" class="profile-edit-cancel" onclick="closeCancelModal()">Keep Order</button>
          <button type="submit" class="profile-edit-btn t2-modal-btn-danger" id="cancelSubmitBtn">Cancel Order</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    var cancelModal = document.getElementById('cancelModal');
    var cancelOrderRef = document.getElementById('cancelOrderRef');
    var cancelForm = document.getElementById('cancelOrderForm');
    var cancelMsg = document.getElementById('cancelMsg');
    var cancelSubmitBtn = document.getElementById('cancelSubmitBtn');

    window.openCancelModal = function(orderRef) {
      cancelOrderRef.value = orderRef;
      cancelForm.reset();
      cancelMsg.className = 'hidden';
      cancelModal.classList.remove('hidden');
    };

    window.closeCancelModal = function() {
      cancelModal.classList.add('hidden');
    };

    if (cancelForm) {
      cancelForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        var orderRef = cancelOrderRef.value;
        var reason = document.getElementById('cancelReason').value;
        var details = document.getElementById('cancelDetails').value.trim();

        if (!reason) {
          cancelMsg.textContent = 'Please select a reason.';
          cancelMsg.className = 'profile-edit-msg is-error';
          return;
        }

        cancelSubmitBtn.disabled = true;
        cancelSubmitBtn.style.opacity = '0.5';
        cancelSubmitBtn.textContent = 'Submitting...';

        try {
          var res = await fetch(LUXE_ACT + 'order-cancel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_ref: orderRef, reason: reason, details: details })
          });
          var data = await res.json();
          if (!res.ok || !data.ok) {
            throw new Error(data.message || 'Could not submit cancellation.');
          }
          
          cancelMsg.textContent = data.message || 'Cancellation requested successfully.';
          cancelMsg.className = 'profile-edit-msg is-success';
          setTimeout(() => { window.location.reload(); }, 1500);

        } catch (err) {
          cancelMsg.textContent = err.message;
          cancelMsg.className = 'profile-edit-msg is-error';
          cancelSubmitBtn.disabled = false;
          cancelSubmitBtn.style.opacity = '1';
          cancelSubmitBtn.textContent = 'Cancel Order';
        }
      });
    }
  </script>

  <?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
