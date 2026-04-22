<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();
$user = auth_user($pdo);
if (!$user) {
    header('Location: login.php');
    exit;
}

$profileReviewFlash = null;
if (!empty($_SESSION['profile_review_flash']) && is_array($_SESSION['profile_review_flash'])) {
    $profileReviewFlash = $_SESSION['profile_review_flash'];
    unset($_SESSION['profile_review_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'submit_order_review') {
    $orderRef = trim((string) ($_POST['order_ref'] ?? ''));
    $productId = (int) ($_POST['product_id'] ?? 0);
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $reviewText = trim((string) ($_POST['review_text'] ?? ''));
    $customerName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    $result = orders_try_submit_product_review($pdo, (int) $user['id'], $customerName, $orderRef, $productId, $rating, $reviewText);
    $_SESSION['profile_review_flash'] = [
        'type' => $result['ok'] ? 'success' : 'error',
        'msg' => (string) ($result['message'] ?? ($result['ok'] ? 'Saved.' : 'Could not save review.')),
    ];
    $hl = $productId > 0 ? '&highlight=' . $productId : '';
    header('Location: profile.php?tab=reviews' . $hl);
    exit;
}

$pendingDeletion = account_deletion_pending_for_user($pdo, (int) $user['id']);
$cartNavCount = 0;
foreach ($_SESSION['cart'] ?? [] as $ci) {
    $cartNavCount += (int) ($ci['qty'] ?? 1);
}
$addresses = [];
$wishlistArr = [];
$orderStats = ['order_count' => 0, 'lifetime_spend_rupees' => 0, 'total_saved_rupees' => 0];
$deliveredReviewRows = [];
if ($user) {
    $addresses = addresses_fetch_for_user($pdo, (int) $user['id']);
    $orderStats = profile_order_stats_for_user($pdo, (int) $user['id']);
    $deliveredReviewRows = profile_delivered_review_rows_for_user($pdo, (int) $user['id']);
}
$deliveredReviewCount = count($deliveredReviewRows);
$reviewedPurchasesCount = 0;
foreach ($deliveredReviewRows as $_rr) {
    if (!empty($_rr['review_id'])) {
        $reviewedPurchasesCount++;
    }
}
$pendingReviewCount = $deliveredReviewCount - $reviewedPurchasesCount;
$allProducts = products_fetch_all($pdo);
foreach (array_slice($allProducts, 0, 8) as $p) {
    $wishlistArr[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'emoji' => $p['emoji'],
        'price' => $p['price'],
        'orig' => $p['original'],
    ];
}
$wishlistCountInitial = count($wishlistArr);
$loyaltySummary = loyalty_summary_for_user($pdo, (int) $user['id']);
$loyaltyBalance = (int) $loyaltySummary['balance'];
$loyaltyGoldAt = 2000;
$loyaltyPlatinumAt = 3000;
if ($loyaltyBalance >= $loyaltyPlatinumAt) {
    $rewardsTierTitle = 'LUXE Platinum Member';
    $rewardsLeadHtml = 'You\'ve reached <strong>Platinum</strong> — enjoy exclusive perks! 🏆';
    $rewardsProgressPct = 100.0;
} elseif ($loyaltyBalance >= $loyaltyGoldAt) {
    $rewardsTierTitle = 'LUXE Gold Member';
    $away = max(0, $loyaltyPlatinumAt - $loyaltyBalance);
    $rewardsLeadHtml = 'You\'re <strong>' . h(number_format($away)) . ' points</strong> away from Platinum status! 🏆';
    $rewardsProgressPct = min(100.0, (($loyaltyBalance - $loyaltyGoldAt) / ($loyaltyPlatinumAt - $loyaltyGoldAt)) * 100.0);
} else {
    $rewardsTierTitle = 'LUXE Member';
    $away = max(0, $loyaltyGoldAt - $loyaltyBalance);
    $rewardsLeadHtml = 'You\'re <strong>' . h(number_format($away)) . ' points</strong> away from Gold status! 🏆';
    $rewardsProgressPct = $loyaltyGoldAt > 0 ? min(100.0, ($loyaltyBalance / $loyaltyGoldAt) * 100.0) : 0.0;
}
$loyaltyHistoryUi = [];
foreach ($loyaltySummary['history'] as $h) {
    $iso = (string) ($h['date_iso'] ?? '');
    $loyaltyHistoryUi[] = [
        'desc' => (string) ($h['label'] ?? ''),
        'date' => $iso !== '' ? date('M j, Y', strtotime($iso)) : '',
        'pts' => (($h['type'] ?? '') === 'pending')
            ? '+' . (int) ($h['pts'] ?? 0) . ' (pending)'
            : '+' . (int) ($h['pts'] ?? 0),
        'type' => (($h['type'] ?? '') === 'pending') ? 'pending' : 'earn',
    ];
}
$memberSinceLabel = '—';
$createdRaw = (string) ($user['created_at'] ?? '');
if ($createdRaw !== '') {
    $ts = strtotime($createdRaw);
    $memberSinceLabel = $ts !== false ? date('M Y', $ts) : $createdRaw;
}
$profileBadgeLabel = $orderStats['order_count'] > 0 ? '⭐ LUXE Premium Member' : 'LUXE Member';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>LUXE — My Profile</title>
  <meta name="description" content="Manage your LUXE profile, addresses, and preferences." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body class="index-page profile-page">
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>
  <div class="bg-scene"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="grid-lines"></div></div>

  <?php
  $header = [
      'user' => $user,
      'cart_count' => $cartNavCount,
      'top_text' => 'New arrivals every week',
      'top_highlight' => 'Free shipping above ₹999',
      'top_links' => [
          ['label' => "Today's Deals", 'href' => 'index.php#deals'],
          ['label' => 'Top Brands', 'href' => 'index.php#brands'],
      ],
      'menu_links' => [
          ['label' => 'Home', 'href' => 'index.php'],
          ['label' => 'Shop', 'href' => 'product-list.php'],
          ['label' => 'Collections', 'href' => 'index.php#collections'],
          ['label' => 'Trending', 'href' => 'index.php#trending'],
          ['label' => 'Deals', 'href' => 'index.php#deals'],
          ['label' => 'Brands', 'href' => 'index.php#brands'],
      ],
      'wishlist_href' => 'profile.php?tab=wishlist',
      'breadcrumb' => [
          'home_href' => 'index.php',
          'home_label' => 'Home',
          'title' => 'My Profile',
          'current' => 'My Profile',
      ],
      'search_lead' => 'Search by product name, brand, or category — matches show below.',
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <main class="page-main">
    <div class="container">

      <?php if ($pendingDeletion): ?>
      <div class="profile-deletion-banner" role="status">
        <strong>Account deletion scheduled</strong>
        <p>Your request is with our team. Your account will be removed within 48 hours (by <?= h(date('M j, Y g:i A', strtotime((string) $pendingDeletion['process_after']))) ?>). Aapka account 48 ghante ke andar delete ho jayega.</p>
      </div>
      <?php endif; ?>

      <!-- Profile Hero Banner -->
      <div class="profile-hero">
        <div class="profile-hero-bg"></div>
        <div class="profile-hero-content">
          <div class="avatar-wrap">
            <div class="avatar" id="avatarEl"><?= $user ? h(strtoupper(substr($user['first_name'], 0, 1))) : '?' ?></div>
            <button class="avatar-edit" onclick="showToast('📷 Photo upload coming soon!')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
          </div>
          <div class="profile-hero-info">
            <h1 id="profileName"><?= $user ? h($user['first_name'] . ' ' . $user['last_name']) : 'Guest' ?></h1>
            <p id="profileEmail"><?= $user ? h($user['email']) : 'Sign in to sync your account' ?></p>
            <div class="profile-meta">
              <span class="profile-badge"><?= h($profileBadgeLabel) ?></span>
              <span class="profile-join">Member since <?= h($memberSinceLabel) ?></span>
            </div>
          </div>
          <div class="profile-stats">
            <div class="pstat"><strong id="profileStatOrders"><?= (int) $orderStats['order_count'] ?></strong><span>Orders</span></div>
            <div class="pstat-div"></div>
            <div class="pstat"><strong id="profileStatWishlist"><?= (int) $wishlistCountInitial ?></strong><span>Wishlist</span></div>
            <div class="pstat-div"></div>
            <div class="pstat"><strong id="profileStatPoints"><?= h(number_format($loyaltyBalance)) ?></strong><span>LUXE Points</span></div>
            <div class="pstat-div"></div>
            <div class="pstat"><strong id="profileStatSaved">&#8377;<?= h(number_format((int) $orderStats['total_saved_rupees'])) ?></strong><span>Total Saved</span></div>
          </div>
        </div>
      </div>

      <!-- Profile Layout -->
      <div class="profile-layout">

        <!-- Sidebar Nav -->
        <aside class="profile-sidebar">
          <div class="sidebar-menu">
            <button class="smenu-item active" data-tab="personal" onclick="switchTab(this)">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Personal Info
            </button>
            <button class="smenu-item" data-tab="addresses" onclick="switchTab(this)">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              Addresses
            </button>
            <button class="smenu-item" data-tab="wishlist" onclick="switchTab(this)">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
              Wishlist
            </button>
            <button class="smenu-item" data-tab="reviews" onclick="switchTab(this)">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              My Reviews
            </button>
            <button class="smenu-item" data-tab="rewards" onclick="switchTab(this)">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              LUXE Rewards
            </button>
            <button class="smenu-item" data-tab="settings" onclick="switchTab(this)">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
              Settings
            </button>
            <div class="sidebar-divider"></div>
            <a href="orders.php" class="smenu-item">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              My Orders
            </a>
            <a href="actions/logout.php" class="smenu-item danger" style="text-decoration:none;color:inherit">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
              Sign Out
            </a>
          </div>
        </aside>

        <!-- Content Area -->
        <div class="profile-content">

          <!-- Personal Info Tab -->
          <div class="tab-panel active" id="tab-personal">
            <div class="panel-header"><h2>Personal Information</h2><button class="edit-toggle" id="editToggle" onclick="toggleEdit()">✏️ Edit</button></div>
            <form id="profileForm" onsubmit="saveProfile(event)">
              <div class="form-grid">
                <div class="form-field">
                  <label>First Name</label>
                  <input type="text" id="firstName" value="<?= $user ? h($user['first_name']) : '' ?>" disabled />
                </div>
                <div class="form-field">
                  <label>Last Name</label>
                  <input type="text" id="lastName" value="<?= $user ? h($user['last_name']) : '' ?>" disabled />
                </div>
                <div class="form-field">
                  <label>Email Address</label>
                  <input type="email" id="email" value="<?= $user ? h($user['email']) : '' ?>" disabled />
                </div>
                <div class="form-field">
                  <label>Phone Number</label>
                  <input type="tel" id="phone" value="<?= $user ? h((string) ($user['phone'] ?? '')) : '' ?>" placeholder="+91 98765 43210" disabled />
                </div>
                <div class="form-field">
                  <label>Date of Birth</label>
                  <input type="date" id="dob" value="<?= $user && !empty($user['dob']) ? h(substr((string) $user['dob'], 0, 10)) : '' ?>" disabled />
                </div>
                <div class="form-field">
                  <label>Gender</label>
                  <?php
                    $ug = ($user && isset($user['gender']) && $user['gender'] !== null && $user['gender'] !== '')
                      ? (string) $user['gender']
                      : '';
                    if ($ug !== '' && !in_array($ug, ['male', 'female', 'other'], true)) {
                        $ug = 'other';
                    }
                  ?>
                  <select id="gender" disabled>
                    <option value="male" <?= $ug === 'male' ? ' selected' : '' ?>>Male</option>
                    <option value="female" <?= $ug === 'female' ? ' selected' : '' ?>>Female</option>
                    <option value="other" <?= ($ug === 'other' || $ug === '') ? ' selected' : '' ?>>Prefer not to say</option>
                  </select>
                </div>
              </div>
              <div class="form-actions hidden" id="formActions">
                <button type="submit" class="checkout-btn" style="max-width:160px">Save Changes</button>
                <button type="button" class="ghost-btn" onclick="cancelEdit()">Cancel</button>
              </div>
            </form>
          </div>

          <!-- Addresses Tab -->
          <div class="tab-panel hidden" id="tab-addresses">
            <div class="panel-header"><h2>Saved Addresses</h2><button class="checkout-btn" style="font-size:0.85rem;padding:10px 18px" onclick="showAddressModal()">+ Add New</button></div>
            <div class="addresses-grid" id="addressesGrid"></div>
          </div>

          <!-- Wishlist Tab -->
          <div class="tab-panel hidden" id="tab-wishlist">
            <header class="wishlist-panel-head">
              <div class="wishlist-panel-head__main">
                <span class="wishlist-kicker">Your edit</span>
                <div class="wishlist-title-row">
                  <h2 class="wishlist-title">My Wishlist</h2>
                  <span class="wishlist-count-chip" id="wishlistCountPill">0</span>
                </div>
                <p class="wishlist-lede">Saved pieces from across LUXE — tap a card to view full details.</p>
              </div>
              <a href="index.php" class="wishlist-cta-outline">Browse collection</a>
            </header>
            <div class="wishlist-grid" id="wishlistGrid"></div>
          </div>

          <!-- Reviews Tab -->
          <div class="tab-panel hidden" id="tab-reviews">
            <header class="wishlist-panel-head profile-reviews-head">
              <div class="wishlist-panel-head__main">
                <span class="wishlist-kicker">Delivered orders</span>
                <div class="wishlist-title-row">
                  <h2 class="wishlist-title">My Reviews</h2>
                  <span class="wishlist-count-chip"><?= (int) $deliveredReviewCount ?></span>
                </div>
                <p class="wishlist-lede">Sirf <strong>delivered</strong> orders ke products. Pending review: <strong><?= (int) $pendingReviewCount ?></strong> · Submitted: <strong><?= (int) $reviewedPurchasesCount ?></strong>. Neeche se hi rating aur review submit karein.</p>
              </div>
              <a href="orders.php" class="wishlist-cta-outline">My orders</a>
            </header>
            <?php if ($profileReviewFlash !== null && isset($profileReviewFlash['msg'])): ?>
            <div class="profile-review-flash profile-review-flash--<?= ($profileReviewFlash['type'] ?? '') === 'error' ? 'error' : 'success' ?>" role="status" style="margin:0 0 20px;padding:12px 16px;border-radius:12px;font-size:0.95rem;line-height:1.45<?= ($profileReviewFlash['type'] ?? '') === 'error' ? ';background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35)' : ';background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.35)' ?>">
              <?= h((string) $profileReviewFlash['msg']) ?>
            </div>
            <?php endif; ?>
            <?php if ($deliveredReviewRows === []): ?>
            <div class="wishlist-empty--premium profile-reviews-empty" role="status">
              <div class="wishlist-empty__glow" aria-hidden="true"></div>
              <span class="wishlist-empty__mark" aria-hidden="true">📦</span>
              <h3 class="wishlist-empty__title">Abhi koi delivered product nahi</h3>
              <p class="wishlist-empty__text">Jab aapka order <strong>Delivered</strong> ho jaye, woh products yahan dikhenge — phir aap review de sakte hain.</p>
              <a href="orders.php" class="wishlist-empty__cta">Orders dekhein</a>
            </div>
            <?php else: ?>
            <div class="profile-reviews-list">
              <?php foreach ($deliveredReviewRows as $rev):
                  $pid = (int) ($rev['product_id'] ?? 0);
                  $pnameDb = trim((string) ($rev['product_name'] ?? ''));
                  $pname = $pnameDb !== '' ? $pnameDb : trim((string) ($rev['item_name'] ?? 'Product'));
                  $pemoji = trim((string) ($rev['product_emoji'] ?? ''));
                  if ($pemoji === '') {
                      $pemoji = (string) ($rev['item_emoji'] ?? '📦');
                  }
                  $variantLine = trim((string) ($rev['variant_text'] ?? ''));
                  $hasReview = !empty($rev['review_id']);
                  $rating = $hasReview ? max(1, min(5, (int) ($rev['rating'] ?? 5))) : 0;
                  $status = strtolower(trim((string) ($rev['review_status'] ?? 'pending')));
                  $statusLabel = match ($status) {
                      'approved' => 'Live on product',
                      'rejected' => 'Not published',
                      default => 'Pending approval',
                  };
                  $statusClass = match ($status) {
                      'approved' => 'profile-review-status--ok',
                      'rejected' => 'profile-review-status--no',
                      default => 'profile-review-status--wait',
                  };
                  $mainImg = trim((string) ($rev['image_path'] ?? ''));
                  $galImg = trim((string) ($rev['gallery_first'] ?? ''));
                  $rawImg = $mainImg !== '' ? $mainImg : $galImg;
                  $hasUpload = $rawImg !== '' && strcasecmp($rawImg, 'default') !== 0;
                  $thumbSrc = $hasUpload
                      ? $rawImg
                      : 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=600&q=80';
                  $thumbSrcEsc = h($thumbSrc);
                  $reviewBody = trim((string) ($rev['review_text'] ?? ''));
                  $sellerReply = trim((string) ($rev['seller_response'] ?? ''));
                  $createdRaw = (string) ($rev['review_created_at'] ?? '');
                  $reviewDate = $createdRaw !== '' && strtotime($createdRaw) !== false
                      ? date('M j, Y', strtotime($createdRaw))
                      : '';
                  $deliveredRaw = (string) ($rev['delivered_at'] ?? '');
                  $deliveredDate = $deliveredRaw !== '' && strtotime($deliveredRaw) !== false
                      ? date('M j, Y', strtotime($deliveredRaw))
                      : '';
                  $productUrl = 'product.php?id=' . $pid;
                  ?>
              <article id="profile-review-row-<?= (int) $pid ?>" class="profile-review-card<?= $hasReview ? '' : ' profile-review-card--needs-review' ?>">
                <a href="<?= h($productUrl) ?>" class="profile-review-card__media" aria-label="<?= h('View ' . $pname) ?>">
                  <?php if ($hasUpload): ?>
                  <img src="<?= $thumbSrcEsc ?>" alt="" width="96" height="96" loading="lazy" decoding="async" />
                  <?php else: ?>
                  <span class="profile-review-card__emoji" aria-hidden="true"><?= h($pemoji) ?></span>
                  <?php endif; ?>
                </a>
                <div class="profile-review-card__body">
                  <div class="profile-review-card__top">
                    <div class="profile-review-card__title-block">
                      <a href="<?= h($productUrl) ?>" class="profile-review-card__title"><?= h($pname) ?></a>
                      <?php if ($variantLine !== ''): ?>
                      <p class="profile-review-variant"><?= h($variantLine) ?></p>
                      <?php endif; ?>
                      <div class="profile-review-card__meta">
                        <?php if ($deliveredDate !== ''): ?>
                        <span class="profile-review-date">Delivered <?= h($deliveredDate) ?></span>
                        <?php endif; ?>
                        <?php if ($hasReview): ?>
                        <span class="profile-review-stars" aria-label="<?= h($rating . ' out of 5 stars') ?>"><?php
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $rating ? '★' : '☆';
                            }
                        ?></span>
                        <?php if ($reviewDate !== ''): ?>
                        <span class="profile-review-date">Review · <?= h($reviewDate) ?></span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="profile-review-pending-label">Abhi review nahi diya</span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <?php if ($hasReview): ?>
                    <span class="profile-review-status <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
                    <?php else: ?>
                    <span class="profile-review-status profile-review-status--cta">Review pending</span>
                    <?php endif; ?>
                  </div>
                  <?php if ($hasReview && $reviewBody !== ''): ?>
                  <p class="profile-review-text"><?= nl2br(h($reviewBody)) ?></p>
                  <?php elseif (!$hasReview): ?>
                  <p class="profile-review-text profile-review-text--muted">Delivered order — yahin se rating aur review submit karein (min. 10 characters).</p>
                  <form method="post" class="profile-review-inline-form">
                    <input type="hidden" name="action" value="submit_order_review">
                    <input type="hidden" name="order_ref" value="<?= h((string) ($rev['order_ref'] ?? '')) ?>">
                    <input type="hidden" name="product_id" value="<?= (int) $pid ?>">
                    <div class="profile-review-inline-form__row">
                      <label class="profile-review-inline-label" for="profile-review-rating-<?= (int) $pid ?>">Rating</label>
                      <select id="profile-review-rating-<?= (int) $pid ?>" name="rating" class="profile-review-inline-select" required>
                        <?php for ($ri = 5; $ri >= 1; $ri--): ?>
                        <option value="<?= $ri ?>"><?= $ri ?> star<?= $ri > 1 ? 's' : '' ?></option>
                        <?php endfor; ?>
                      </select>
                    </div>
                    <div class="profile-review-inline-form__row">
                      <label class="profile-review-inline-label" for="profile-review-text-<?= (int) $pid ?>">Your review</label>
                      <textarea id="profile-review-text-<?= (int) $pid ?>" name="review_text" class="profile-review-inline-textarea" rows="3" maxlength="1000" minlength="10" required placeholder="Apna experience share karein..."></textarea>
                    </div>
                    <div class="profile-review-actions profile-review-actions--form">
                      <button type="submit" class="checkout-btn profile-review-write-btn">Submit review</button>
                      <a href="<?= h($productUrl) ?>" class="ghost-btn profile-review-write-secondary">View product</a>
                    </div>
                  </form>
                  <?php endif; ?>
                  <?php if ($hasReview && $sellerReply !== ''): ?>
                  <div class="profile-review-seller-reply">
                    <strong>Seller reply</strong>
                    <p><?= nl2br(h($sellerReply)) ?></p>
                  </div>
                  <?php endif; ?>
                </div>
              </article>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Rewards Tab -->
          <div class="tab-panel hidden" id="tab-rewards">
            <div class="panel-header"><h2>LUXE Rewards</h2></div>
            <div class="rewards-hero">
              <div class="rewards-pts-circle">
                <strong id="rewardsPtsCircle"><?= h(number_format($loyaltyBalance)) ?></strong>
                <span>Points</span>
              </div>
              <div class="rewards-info">
                <h3 id="rewardsTierTitle"><?= h($rewardsTierTitle) ?></h3>
                <p id="rewardsLeadLine"><?= $rewardsLeadHtml ?></p>
                <div class="points-bar-wrap">
                  <div class="points-bar"><div class="points-fill" id="rewardsProgressFill" style="width:<?= h((string) round($rewardsProgressPct, 1)) ?>%"></div></div>
                  <div class="points-labels"><span>Gold (<?= (int) $loyaltyGoldAt ?>)</span><span>Platinum (<?= (int) $loyaltyPlatinumAt ?>)</span></div>
                </div>
              </div>
            </div>
            <div class="rewards-history">
              <h3>Points History</h3>
              <div class="rh-list" id="rewardsHistory"></div>
            </div>
            <div class="rewards-redeem">
              <h3>Redeem Points</h3>
              <p>100 points = ₹10 off on your next order</p>
              <div class="redeem-row">
                <input type="number" id="redeemInput" placeholder="Enter points to redeem" min="100" max="<?= max(100, $loyaltyBalance) ?>" step="100" />
                <button class="checkout-btn" style="max-width:140px" onclick="redeemPoints()">Redeem</button>
              </div>
            </div>
          </div>

          <!-- Settings Tab -->
          <div class="tab-panel hidden" id="tab-settings">
            <div class="panel-header"><h2>Account Settings</h2></div>
            <div class="settings-list">
              <div class="setting-item"><div class="setting-info"><strong>Email Notifications</strong><span>Receive deals, order updates via email</span></div><label class="toggle"><input type="checkbox" id="emailNotif" checked /><span class="slider"></span></label></div>
              <div class="setting-item"><div class="setting-info"><strong>SMS Alerts</strong><span>Get order & delivery SMS updates</span></div><label class="toggle"><input type="checkbox" id="smsNotif" checked /><span class="slider"></span></label></div>
              <div class="setting-item"><div class="setting-info"><strong>Push Notifications</strong><span>Flash sale & personalized alerts</span></div><label class="toggle"><input type="checkbox" id="pushNotif" /><span class="slider"></span></label></div>
              <div class="setting-item"><div class="setting-info"><strong>Personalised Recommendations</strong><span>AI-powered product suggestions</span></div><label class="toggle"><input type="checkbox" id="aiRec" checked /><span class="slider"></span></label></div>
              <!-- <div class="setting-divider"></div> -->
              <div class="setting-item">
                <div class="setting-info"><strong>Change Password</strong><span>Update your account password</span></div>
                <button type="button" class="ghost-btn" onclick="openChangePasswordModal()">Update</button>
              </div>
              <div class="setting-item">
                <div class="setting-info"><strong>Two-Factor Authentication</strong><span>Add extra security to your account</span></div>
                <button class="ghost-btn" onclick="showToast('📱 2FA setup coming soon!')">Enable</button>
              </div>
              <!-- <div class="setting-divider"></div> -->
              <div class="setting-item danger-zone">
                <div class="setting-info"><strong>Delete Account</strong><span>Submit a request; your account is removed within 48 hours after admin review</span></div>
                <button type="button" class="danger-btn" id="deleteAccountBtn"<?= $pendingDeletion ? ' disabled data-pending-deletion="1"' : '' ?>><?= $pendingDeletion ? 'Request pending' : 'Delete' ?></button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </main>

  <?php
  $footer = [
      'deals_href' => 'index.php#deals',
      'year' => '2026',
  ];
  require __DIR__ . '/includes/user_footer.php';
  ?>

  <!-- Address Modal -->
  <div class="modal-overlay hidden" id="addressModal">
    <div class="modal-card">
      <div class="modal-header"><h3 id="addressModalTitle">Add address</h3><button type="button" class="modal-close" onclick="closeAddressModal()">✕</button></div>
      <form id="addressForm" onsubmit="saveAddress(event)">
        <input type="hidden" id="addressId" name="address_id" value="" />
        <div class="form-grid">
          <div class="form-field"><label for="addrName">Full Name</label><input type="text" id="addrName" name="full_name" placeholder="Rahul Sharma" required maxlength="255" autocomplete="name" /></div>
          <div class="form-field"><label for="addrPhone">Phone</label><input type="tel" id="addrPhone" name="phone" placeholder="+91 98765 43210" maxlength="40" autocomplete="tel" /></div>
          <div class="form-field" style="grid-column:1/-1"><label for="addrLine1">Address Line 1</label><input type="text" id="addrLine1" name="line1" placeholder="House/Flat No., Street" required maxlength="255" autocomplete="address-line1" /></div>
          <div class="form-field" style="grid-column:1/-1"><label for="addrLine2">Address Line 2</label><input type="text" id="addrLine2" name="line2" placeholder="Landmark (optional)" maxlength="255" autocomplete="address-line2" /></div>
          <div class="form-field"><label for="addrCity">City</label><input type="text" id="addrCity" name="city" placeholder="Mumbai" required maxlength="100" autocomplete="address-level2" /></div>
          <div class="form-field"><label for="addrPin">PIN Code</label><input type="text" id="addrPin" name="pin" placeholder="400001" required maxlength="20" inputmode="numeric" autocomplete="postal-code" /></div>
          <div class="form-field"><label for="addrState">State</label><input type="text" id="addrState" name="state" placeholder="Maharashtra" required maxlength="100" autocomplete="address-level1" /></div>
          <div class="form-field"><label for="addrType">Type</label><select id="addrType" name="type"><option value="Home">Home</option><option value="Work">Work</option><option value="Other">Other</option></select></div>
          <div class="form-field" style="grid-column:1/-1"><label class="checkbox-label" style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-top:4px"><input type="checkbox" id="addrIsDefault" name="is_default" /> <span>Set as default address</span></label></div>
        </div>
        <div class="form-actions">
          <button type="submit" class="checkout-btn" id="addressSaveBtn" style="max-width:200px">Save address</button>
          <button type="button" class="ghost-btn" onclick="closeAddressModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Change password modal -->
  <div class="modal-overlay hidden" id="changePasswordModal" role="dialog" aria-modal="true" aria-labelledby="changePasswordModalTitle">
    <div class="modal-card">
      <div class="modal-header">
        <h3 id="changePasswordModalTitle">Change password</h3>
        <button type="button" class="modal-close" onclick="closeChangePasswordModal()" aria-label="Close">✕</button>
      </div>
      <p class="password-modal-lead">Use at least 8 characters with letters and numbers.</p>
      <form id="changePasswordForm" onsubmit="savePasswordChange(event)" autocomplete="off">
        <div class="form-grid">
          <div class="form-field" style="grid-column: 1 / -1">
            <label for="pchCurrent">Current password</label>
            <input type="password" id="pchCurrent" name="current_password" autocomplete="current-password" required maxlength="128" />
          </div>
          <div class="form-field" style="grid-column: 1 / -1">
            <label for="pchNew">New password</label>
            <input type="password" id="pchNew" name="new_password" autocomplete="new-password" required minlength="8" maxlength="128" />
          </div>
          <div class="form-field" style="grid-column: 1 / -1">
            <label for="pchConfirm">Confirm new password</label>
            <input type="password" id="pchConfirm" name="confirm_password" autocomplete="new-password" required minlength="8" maxlength="128" />
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="checkout-btn" id="pchSubmit" style="max-width: 220px">Update password</button>
          <button type="button" class="ghost-btn" onclick="closeChangePasswordModal()">Cancel</button>
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
    window.__ADDRESSES__ = <?= json_encode($addresses, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__WISHLIST__ = <?= json_encode($wishlistArr, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__PRODUCTS__ = <?= json_encode($allProducts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
    window.__API_PROFILE_UPDATE__ = 'actions/update-profile.php';
    window.__API_ACCOUNT_DELETE__ = 'actions/request-account-deletion.php';
    window.__API_CHANGE_PASSWORD__ = 'actions/change-password.php';
    window.__API_ADDRESS_SAVE__ = 'actions/save-address.php';
    window.__API_ADDRESS_DELETE__ = 'actions/delete-address.php';
    window.__API_ADDRESS_DEFAULT__ = 'actions/set-default-address.php';
    window.__API_REDEEM_LOYALTY__ = 'actions/redeem-loyalty-points.php';
    window.__LOYALTY__ = <?= json_encode([
        'balance' => $loyaltyBalance,
        'goldAt' => $loyaltyGoldAt,
        'platinumAt' => $loyaltyPlatinumAt,
        'pending' => (int) $loyaltySummary['pending'],
        'earned' => (int) $loyaltySummary['earned'],
        'history' => $loyaltyHistoryUi,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
