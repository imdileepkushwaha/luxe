<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$user = auth_user($pdo);
if (!$user) {
    header('Location: login.php?redirect=' . rawurlencode('theme-1/orders.php'));
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
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('theme-1/orders.php');
$theme1HeaderCategories = ["Men's Fashion", "Women's Fashion", "Kid's Fashion", 'Footwear'];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = $theme1HeaderCategories;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LUXE Theme 1 - Orders</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body class="profile-page-wrap">
  <?php require __DIR__ . '/partials/header.php'; ?>
  <main>
    <section class="profile-shell">
      <!-- Sidebar -->
      <aside class="profile-side">
        <div class="profile-card-top">
          <div class="profile-avatar-wrap">
            <div class="profile-avatar"><?= h($initial) ?></div>
          </div>
          <h3><?= h($fullName) ?></h3>
          <p><?= h($userEmail) ?></p>
          <span class="profile-member-since">
            <?php
              $memberSince = 'Recently joined';
              $createdAt = (string) ($user['created_at'] ?? '');
              if ($createdAt !== '' && strtotime($createdAt) !== false) {
                  $memberSince = 'Member since ' . date('M Y', strtotime($createdAt));
              }
              echo h($memberSince);
            ?>
          </span>
        </div>
        <ul class="profile-menu">
          <li><a href="profile.php" data-tab-link="dashboard">
            <span class="profile-menu-icon">🏠</span> Dashboard
          </a></li>
          <li><a href="profile.php?tab=addresses">
            <span class="profile-menu-icon">📍</span> Addresses
          </a></li>
          <li><a href="profile.php?tab=wishlist">
            <span class="profile-menu-icon">💖</span> Wishlist
          </a></li>
          <li><a href="profile.php?tab=reviews">
            <span class="profile-menu-icon">⭐</span> Reviews
          </a></li>
          <li><a href="orders.php" class="is-active">
            <span class="profile-menu-icon">📦</span> My Orders
          </a></li>
          <li><a href="settings.php">
            <span class="profile-menu-icon">⚙️</span> Settings
          </a></li>
          <div class="profile-menu-divider"></div>
          <li><a href="../actions/logout.php?redirect=theme-1/index.php" style="color:#dc2626;">
            <span class="profile-menu-icon" style="background:linear-gradient(140deg,#fee2e2,#fecaca);border-color:rgba(239,68,68,0.2);">🚪</span> Logout
          </a></li>
        </ul>
      </aside>

      <!-- Main Content -->
      <article class="profile-main">
        <div class="profile-main-head">
          <h2>📦 My Orders</h2>
          <a class="t1-header-btn-secondary" href="index.php" style="padding:8px 16px;border-radius:12px;font-size:13px;text-decoration:none;border:1px solid #cbd5e1;color:#334155;">Continue shopping →</a>
        </div>
        
        <?php if (!empty($_GET['placed'])): ?>
          <p class="profile-edit-msg is-success" style="margin-bottom:20px;">🎉 Order placed successfully: <strong><?= h((string) $_GET['placed']) ?></strong></p>
        <?php endif; ?>

        <?php if ($allOrders === []): ?>
          <div class="t1-review-empty" style="margin-top:20px;">
            <span class="t1-review-empty__icon">🛍️</span>
            <h3>No orders yet</h3>
            <p>You haven't placed any orders. Start exploring our premium collection.</p>
            <a href="index.php" class="profile-edit-btn" style="display:inline-block;margin-top:16px;text-decoration:none;">Start Shopping</a>
          </div>
        <?php else: ?>
          <div class="t1-orders-top-bar">
            <div class="t1-orders-filters">
              <a href="?filter=all<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t1-orders-filter <?= $filter === 'all' ? 'active' : '' ?>">All</a>
              <a href="?filter=returns<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t1-orders-filter <?= $filter === 'returns' ? 'active' : '' ?>">Returns</a>
              <a href="?filter=delivered<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t1-orders-filter <?= $filter === 'delivered' ? 'active' : '' ?>">Delivered</a>
              <a href="?filter=shipped<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t1-orders-filter <?= $filter === 'shipped' ? 'active' : '' ?>">Shipped</a>
              <a href="?filter=processing<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t1-orders-filter <?= $filter === 'processing' ? 'active' : '' ?>">Processing</a>
              <a href="?filter=cancelled<?= $search ? '&search='.urlencode($_GET['search']) : '' ?>" class="t1-orders-filter <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
            </div>
            <form class="t1-orders-search" method="GET" action="">
              <?php if ($filter !== 'all'): ?>
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
              <?php endif; ?>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" name="search" placeholder="Search orders..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
              <button type="submit" style="display:none;"></button>
            </form>
          </div>

          <?php if ($orders === []): ?>
            <div class="t1-review-empty" style="margin-top:20px; padding:40px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:16px;">
              <span class="t1-review-empty__icon" style="font-size:36px;opacity:0.6;background:none;box-shadow:none;">🔍</span>
              <h3 style="margin-top:12px;color:#475569;">No results found</h3>
              <p>No orders matched your filter or search criteria.</p>
              <?php if ($filter !== 'all' || $search !== ''): ?>
                <a href="orders.php" class="profile-edit-btn" style="display:inline-block;margin-top:16px;text-decoration:none;background:#fff;color:#0f172a;border:1px solid #e2e8f0;">Clear Filters</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="t1-orders-list">
            <?php foreach ($pagedOrders as $ord): 
              $hasUnreviewedItems = false;
              $hasUnreturnedItems = false;
              foreach ($ord['items'] ?? [] as $item) {
                  if (empty($item['hasReview'])) $hasUnreviewedItems = true;
                  if (empty($item['returnRequest'])) $hasUnreturnedItems = true;
              }
              $ordStatus = strtolower(trim((string) ($ord['status'] ?? 'processing')));
              $statusClass = 'processing';
              $statusIcon = '⏳';
              $statusLabel = 'Processing';
              $stepIdx = 0;
              
              if ($ordStatus === 'delivered' || $ordStatus === 'completed') { 
                  $statusClass = 'delivered'; $statusIcon = '✅'; $statusLabel = 'Delivered'; $stepIdx = 4;
              } elseif ($ordStatus === 'processing' || $ordStatus === 'pending') { 
                  $statusClass = 'processing'; $statusIcon = '⏳'; $statusLabel = 'Processing'; $stepIdx = 0;
              } elseif ($ordStatus === 'shipped') { 
                  $statusClass = 'shipped'; $statusIcon = '🚚'; $statusLabel = 'Shipped'; $stepIdx = 2;
              } elseif ($ordStatus === 'out_for_delivery' || $ordStatus === 'out for delivery') { 
                  $statusClass = 'shipped'; $statusIcon = '🚚'; $statusLabel = 'Out for Delivery'; $stepIdx = 3;
              } elseif ($ordStatus === 'cancelled') { 
                  $statusClass = 'cancelled'; $statusIcon = '✖'; $statusLabel = 'Cancelled'; $stepIdx = -1;
              }
            ?>
              <div class="t1-order-card">
                <div class="t1-order-card-header">
                  <div class="t1-order-header-left">
                    <span class="t1-order-id-badge">ORDER ID</span>
                    <span class="t1-order-id-val">#<?= h((string) ($ord['id'] ?? '')) ?></span>
                    <span class="t1-order-date">Placed on <?= h((string) ($ord['date'] ?? '')) ?></span>
                  </div>
                  <div style="display:flex; align-items:center; gap:12px;">
                    <?php
                      $cReq = $ord['cancelRequest'] ?? null;
                      $cStatus = $cReq ? strtolower((string)$cReq['status']) : null;
                      $canCancel = in_array(strtolower((string)$ord['status']), ['processing', 'pending', 'shipped']);
                    ?>
                    <?php if ($cStatus === 'pending'): ?>
                        <button type="button" disabled style="opacity:0.7; cursor:not-allowed; border:1px solid #cbd5e1; background:#f8fafc; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; color:#64748b;">Cancel Pending</button>
                    <?php elseif ($cStatus === 'rejected'): ?>
                        <button type="button" disabled style="opacity:0.7; cursor:not-allowed; border:1px solid #fca5a5; color:#ef4444; background:#fef2f2; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600;">Cancel Rejected</button>
                    <?php elseif ($canCancel && !$cStatus): ?>
                        <button type="button" onclick="openCancelModal('<?= h((string)$ord['id']) ?>')" style="border:1px solid #e2e8f0; background:#fff; padding:6px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; color:#0f172a; transition:all 0.2s;" onmouseover="this.style.borderColor='#cbd5e1';this.style.background='#f8fafc';" onmouseout="this.style.borderColor='#e2e8f0';this.style.background='#fff';">Cancel Order</button>
                    <?php endif; ?>
                    <div class="t1-order-status <?= $statusClass ?>">
                      <?= $statusIcon ?> <?= $statusLabel ?>
                    </div>
                  </div>
                </div>

                <div class="t1-order-card-body">
                  <div class="t1-order-item-wrap">
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
                              $itemImg = '../' . ltrim($rawImg, '/');
                          }
                      }
                    ?>
                      <div class="t1-order-item-row" style="flex-direction:column; align-items:stretch;">
                        <div style="display:flex; align-items:center; gap:16px;">
                          <div class="t1-order-item-thumb">
                            <?php if ($itemImg): ?>
                              <img src="<?= h($itemImg) ?>" alt="<?= $itemName ?>" loading="lazy">
                            <?php else: ?>
                              <span class="t1-order-item-fallback">📦</span>
                            <?php endif; ?>
                          </div>
                          <div class="t1-order-item-details">
                            <strong><?= $itemName ?></strong>
                            <span>Qty: <?= $itemQty ?></span>
                            <?php if ($statusClass === 'cancelled'): ?>
                              <span class="t1-order-item-status-msg">✖ Cancelled</span>
                            <?php endif; ?>
                          </div>
                          <?php if ($statusClass === 'delivered' && !empty($it['canReview'])): ?>
                             <button type="button" class="t1-order-btn t1-order-btn-outline review-btn-<?= $it['productId'] ?>" style="margin-left:auto; padding:6px 12px; font-size:12px;" onclick="openReviewModal('<?= h((string)$ord['id']) ?>', '<?= $it['productId'] ?>', '<?= h(addslashes($itemName)) ?>')">Rate & Review</button>
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
                        <div class="t1-order-item-progress" style="margin-top:12px; padding:0; background:rgba(248,250,252,0.6); border-radius:12px; padding-bottom:12px;">
                          <div class="t1-order-item-progress-title" style="padding:12px 12px 4px; color:#b45309;">
                            <span>✦</span> RETURN PROGRESS
                          </div>
                          <div class="t1-progress-bar-container">
                            <div class="t1-progress-bar-line"></div>
                            <div class="t1-progress-bar-fill" style="width: <?= $rProgressWidth ?>%; <?= $isRejected ? 'background: linear-gradient(135deg, #f87171, #dc2626);' : 'background: linear-gradient(135deg, #fbbf24, #f59e0b);' ?>"></div>
                            <?php foreach ($rSteps as $idx => $label): 
                               if ($label === '') continue;
                               $stepState = '';
                               if ($idx < $rStepIdx) $stepState = 'completed';
                               elseif ($idx === $rStepIdx) $stepState = 'current';
                               
                               $dotStyle = '';
                               if ($isRejected && $idx === 1) {
                                   $dotStyle = 'background: linear-gradient(135deg, #f87171, #dc2626); box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.15); border-color:#fff;';
                               } elseif ($stepState === 'completed' || $stepState === 'current') {
                                   $dotStyle = 'background: linear-gradient(135deg, #fbbf24, #f59e0b); box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15); border-color:#fff;';
                               }
                            ?>
                              <div class="t1-progress-step <?= $stepState ?>">
                                <div class="t1-progress-dot" style="<?= $dotStyle ?>"><?= ($idx <= $rStepIdx) ? ($isRejected && $idx === 1 ? '✖' : '✓') : '' ?></div>
                                <div class="t1-progress-label"><?= $label ?></div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                          <div style="margin:0 12px; font-size:12px; color:#475569; background:#fff; padding:8px 12px; border-radius:8px; border:1px solid rgba(148,163,184,0.2);">
                             <strong>Reason:</strong> <?= htmlspecialchars($rr['reason']) ?> <br>
                             <strong>Refund:</strong> ₹<?= number_format((int)$rr['refundAmount']) ?> (<?= htmlspecialchars($rr['refundMode']) ?>)
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
                  <div class="t1-order-item-progress">
                    <div class="t1-order-item-progress-title">
                      <span>✦</span> ITEM-WISE ORDER PROGRESS
                    </div>
                    <div class="t1-progress-bar-container">
                      <div class="t1-progress-bar-line"></div>
                      <div class="t1-progress-bar-fill" style="width: <?= $progressWidth ?>%;"></div>
                      <?php foreach ($steps as $idx => $label): 
                         $stepState = '';
                         if ($idx < $stepIdx) $stepState = 'completed';
                         elseif ($idx === $stepIdx) $stepState = 'current';
                      ?>
                        <div class="t1-progress-step <?= $stepState ?>">
                          <div class="t1-progress-dot"><?= ($idx <= $stepIdx) ? '✓' : '' ?></div>
                          <div class="t1-progress-label"><?= $label ?></div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                </div>

                <div class="t1-order-card-footer">
                  <div class="t1-order-footer-left">
                    <span>ORDER TOTAL</span>
                    <strong>₹<?= number_format((int) ($ord['total'] ?? 0)) ?></strong>
                  </div>
                  <div class="t1-order-actions">
                    <?php if ($statusClass === 'delivered'): ?>
                      <?php if ($hasUnreturnedItems): ?>
                        <button type="button" class="t1-order-btn t1-order-btn-outline">Return</button>
                      <?php endif; ?>
                      <a href="../download-invoice.php?order_ref=<?= urlencode((string)$ord['id']) ?>" target="_blank" class="t1-order-btn t1-order-btn-outline" style="text-decoration:none;">Download Invoice</a>
                    <?php endif; ?>
                    <button type="button" class="t1-order-btn t1-order-btn-primary" onclick="document.getElementById('modal-<?= $ord['id'] ?>').classList.remove('hidden')">View Details →</button>
                  </div>
                </div>
              </div>
              

            <?php endforeach; ?>
          </div>

          <?php if ($totalPages > 1): ?>
            <div class="t1-pagination">
              <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $qstr ?>" class="t1-page-btn">Previous</a>
              <?php else: ?>
                <span class="t1-page-btn disabled">Previous</span>
              <?php endif; ?>
              
              <div class="t1-page-numbers">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <a href="?page=<?= $i ?><?= $qstr ?>" class="t1-page-num <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
              </div>
              
              <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?><?= $qstr ?>" class="t1-page-btn">Next</a>
              <?php else: ?>
                <span class="t1-page-btn disabled">Next</span>
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
  <div class="theme1-modal-overlay hidden" id="modal-<?= $ord['id'] ?>" onclick="if(event.target===this) this.classList.add('hidden')">
    <div class="theme1-modal-card">
      <div class="theme1-modal-header">
        <h3>Order Details: <?= htmlspecialchars($ord['id']) ?></h3>
        <button type="button" class="theme1-modal-close" onclick="document.getElementById('modal-<?= $ord['id'] ?>').classList.add('hidden')">✕</button>
      </div>
      <div class="theme1-modal-body" style="max-height: 70vh; overflow-y: auto;">
        <p style="margin-bottom:8px"><strong>Date:</strong> <?= htmlspecialchars($ord['date']) ?></p>
        <p style="margin-bottom:8px"><strong>Status:</strong> <?= htmlspecialchars($ord['status']) ?></p>
        <p style="margin-bottom:8px"><strong>Payment:</strong> <?= htmlspecialchars($ord['payment']) ?></p>
        <p style="margin-bottom:16px"><strong>Address:</strong> <?= htmlspecialchars($ord['address']) ?></p>
        <h4 style="margin: 0 0 12px; font-size: 16px; font-weight: 700; color: #0f172a;">Items</h4>
        <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:20px;">
           <?php foreach ($ord['items'] as $item): ?>
              <div style="display:flex; align-items:center; gap:12px; padding-bottom:12px; border-bottom:1px dashed rgba(148,163,184,0.2);">
                <div style="width:48px;height:48px;border-radius:10px;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;"><?= htmlspecialchars($item['emoji']) ?></div>
                <div style="flex:1;">
                  <strong style="display:block;font-size:14px;color:#0f172a;margin-bottom:2px;"><?= htmlspecialchars($item['name']) ?></strong>
                  <span style="font-size:12px;color:#64748b;">Qty: <?= $item['qty'] ?></span>
                </div>
                <strong style="font-size:14px;color:#0f172a;">₹<?= number_format($item['lineTotal']) ?></strong>
              </div>
           <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid rgba(148,163,184,0.15);padding-top:16px;">
           <span style="font-size:14px;font-weight:700;color:#64748b;">ORDER TOTAL</span>
           <strong style="font-size:22px;font-weight:800;color:#0f172a;">₹<?= number_format($ord['total']) ?></strong>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>

  <!-- Review Modal -->
  <div class="theme1-modal-overlay hidden" id="reviewModal" style="z-index:9999;">
    <div class="theme1-modal-card" style="max-width:400px; padding:32px;">
      <h3 style="margin:0 0 8px; font-size:20px; font-weight:700; color:#0f172a;">Rate & Review</h3>
      <p id="reviewProductName" style="margin:0 0 20px; font-size:14px; color:#475569; font-weight:600;"></p>
      
      <form id="reviewOrderForm">
        <input type="hidden" id="reviewOrderRef" value="">
        <input type="hidden" id="reviewProductId" value="">
        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#334155;">Rating</label>
          <select id="reviewRating" required style="width:100%; padding:10px 14px; border-radius:8px; border:1px solid #e2e8f0; outline:none; background:#f8fafc; color:#0f172a; font-size:14px;">
            <option value="5">⭐⭐⭐⭐⭐ 5 Stars</option>
            <option value="4">⭐⭐⭐⭐ 4 Stars</option>
            <option value="3">⭐⭐⭐ 3 Stars</option>
            <option value="2">⭐⭐ 2 Stars</option>
            <option value="1">⭐ 1 Star</option>
          </select>
        </div>
        <div style="margin-bottom:24px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#334155;">Your Review</label>
          <textarea id="reviewText" required minlength="10" maxlength="1000" rows="4" style="width:100%; padding:10px 14px; border-radius:8px; border:1px solid #e2e8f0; outline:none; background:#f8fafc; color:#0f172a; font-size:14px; resize:none;" placeholder="Share your experience with this product..."></textarea>
        </div>
        
        <p id="reviewMsg" class="hidden" style="font-size:13px; font-weight:600; margin-bottom:16px; text-align:center; padding:8px; border-radius:8px;"></p>

        <div style="display:flex; gap:12px;">
          <button type="button" class="profile-edit-cancel" onclick="closeReviewModal()" style="flex:1;">Cancel</button>
          <button type="submit" class="profile-edit-btn" id="reviewSubmitBtn" style="flex:1;">Submit Review</button>
        </div>
      </form>
    </div>
  </div>

  <script>
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
          var res = await fetch('../actions/submit-review.php', {
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

  <div class="theme1-modal-overlay hidden" id="cancelModal" style="z-index:9999;">
    <div class="theme1-modal-card" style="max-width:400px; padding:32px;">
      <h3 style="margin:0 0 16px; font-size:20px; font-weight:700; color:#0f172a;">Cancel Order</h3>
      <p style="margin:0 0 20px; font-size:14px; color:#475569;">Are you sure you want to cancel this order? Please provide a reason.</p>
      
      <form id="cancelOrderForm">
        <input type="hidden" id="cancelOrderRef" value="">
        <div style="margin-bottom:16px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#334155;">Reason for Cancellation</label>
          <select id="cancelReason" required style="width:100%; padding:10px 14px; border-radius:8px; border:1px solid #e2e8f0; outline:none; background:#f8fafc; color:#0f172a; font-size:14px;">
            <option value="">Select a reason...</option>
            <option value="Changed my mind">Changed my mind</option>
            <option value="Ordered by mistake">Ordered by mistake</option>
            <option value="Found better price elsewhere">Found better price elsewhere</option>
            <option value="Delivery is taking too long">Delivery is taking too long</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div style="margin-bottom:24px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#334155;">Additional Details (Optional)</label>
          <textarea id="cancelDetails" rows="3" style="width:100%; padding:10px 14px; border-radius:8px; border:1px solid #e2e8f0; outline:none; background:#f8fafc; color:#0f172a; font-size:14px; resize:none;" placeholder="Anything else you'd like us to know?"></textarea>
        </div>
        
        <p id="cancelMsg" class="hidden" style="font-size:13px; font-weight:600; margin-bottom:16px; text-align:center; padding:8px; border-radius:8px;"></p>

        <div style="display:flex; gap:12px;">
          <button type="button" class="profile-edit-cancel" onclick="closeCancelModal()" style="flex:1;">Keep Order</button>
          <button type="submit" class="profile-edit-btn" id="cancelSubmitBtn" style="flex:1; background:#ef4444; border-color:#ef4444;">Cancel Order</button>
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
          var res = await fetch('../actions/order-cancel.php', {
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
