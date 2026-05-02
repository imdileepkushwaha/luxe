<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$user = auth_user($pdo);
if (!$user) {
    header('Location: login.php?redirect=' . rawurlencode('theme-1/profile.php'));
    exit;
}

$initialTab = 'dashboard';
$reviewFlash = null;
if (!empty($_SESSION['theme1_review_flash']) && is_array($_SESSION['theme1_review_flash'])) {
    $reviewFlash = $_SESSION['theme1_review_flash'];
    unset($_SESSION['theme1_review_flash']);
    $initialTab = 'reviews';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'submit_order_review') {
    $orderRef = trim((string) ($_POST['order_ref'] ?? ''));
    $productId = (int) ($_POST['product_id'] ?? 0);
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $reviewText = trim((string) ($_POST['review_text'] ?? ''));
    $customerName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
    $result = orders_try_submit_product_review($pdo, (int) ($user['id'] ?? 0), $customerName, $orderRef, $productId, $rating, $reviewText);
    $_SESSION['theme1_review_flash'] = [
        'type' => !empty($result['ok']) ? 'success' : 'error',
        'msg' => (string) ($result['message'] ?? (!empty($result['ok']) ? 'Review saved.' : 'Could not save review.')),
    ];
    header('Location: profile.php?tab=reviews');
    exit;
}

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
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('theme-1/profile.php');

$theme1HeaderCategories = ["Men's Fashion", "Women's Fashion", "Kid's Fashion", 'Footwear'];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = $theme1HeaderCategories;

function theme1_media_src(string $raw): string
{
    $path = trim($raw);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    if ($path[0] === '/') {
        return $path;
    }
    return '../' . ltrim($path, '/');
}

$uid = (int) ($user['id'] ?? 0);
$emailVerified = !empty($user['email_verified_at']);
$phoneVerified = !empty($user['phone_verified_at']);
$addresses = $uid > 0 ? addresses_fetch_for_user($pdo, $uid) : [];
$orderStats = $uid > 0 ? profile_order_stats_for_user($pdo, $uid) : ['order_count' => 0, 'lifetime_spend_rupees' => 0, 'total_saved_rupees' => 0];
$deliveredReviewRows = $uid > 0 ? profile_delivered_review_rows_for_user($pdo, $uid) : [];
$wishlistImageMap = [];
foreach (products_fetch_all($pdo) as $pp) {
    $pid = (int) ($pp['id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $wishlistImageMap[$pid] = theme1_media_src((string) ($pp['image_path'] ?? ''));
}

$loyaltySummary = loyalty_summary_for_user($pdo, (int) ($user['id'] ?? 0));
$loyaltyBalance = (int) $loyaltySummary['balance'];
$loyaltyGoldAt = 1000;
$loyaltyPlatinumAt = 5000;
$rewardsTierTitle = '';
$rewardsLeadHtml = '';
$rewardsProgressPct = 0.0;
if ($loyaltyBalance >= $loyaltyPlatinumAt) {
    $rewardsTierTitle = 'LUXE Platinum Member';
    $rewardsLeadHtml = 'You\'ve reached <strong>Platinum</strong> — enjoy exclusive perks! ✨';
    $rewardsProgressPct = 100.0;
} elseif ($loyaltyBalance >= $loyaltyGoldAt) {
    $rewardsTierTitle = 'LUXE Gold Member';
    $away = max(0, $loyaltyPlatinumAt - $loyaltyBalance);
    $rewardsLeadHtml = 'You\'re <strong>' . h(number_format($away)) . ' points</strong> away from Platinum status! ✨';
    $rewardsProgressPct = min(100.0, (($loyaltyBalance - $loyaltyGoldAt) / ($loyaltyPlatinumAt - $loyaltyGoldAt)) * 100.0);
} else {
    $rewardsTierTitle = 'LUXE Member';
    $away = max(0, $loyaltyGoldAt - $loyaltyBalance);
    $rewardsLeadHtml = 'You\'re <strong>' . h(number_format($away)) . ' points</strong> away from Gold status! ✨';
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
    ];
}

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
  <title>LUXE Theme 1 - My Account</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body class="profile-page-wrap">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="profile-shell" aria-label="Profile content">
      <?php require __DIR__ . '/partials/profile-sidebar.php'; ?>

      <article class="profile-main">
        <div class="t1-tab-panel" id="tab-dashboard">
          <div class="t1-dashboard-hero">
            <div>
              <p class="t1-dashboard-kicker">✨ Welcome back</p>
              <h3><?= h($fullName) ?></h3>
              <p class="t1-dashboard-sub"><?= h($memberSince) ?> &nbsp;·&nbsp; Keep your profile updated for faster checkout.</p>
            </div>
            <span class="t1-dashboard-badge">👑 Plus Member</span>
          </div>
          <div class="profile-main-head">
            <h2>Dashboard</h2>
          </div>
          <div class="t1-stat-grid">
            <div class="t1-stat-card"><strong><?= (int) ($orderStats['order_count'] ?? 0) ?></strong><span>Total Orders</span></div>
            <div class="t1-stat-card"><strong>Rs <?= number_format((int) ($orderStats['lifetime_spend_rupees'] ?? 0)) ?></strong><span>Lifetime Spend</span></div>
            <div class="t1-stat-card"><strong>Rs <?= number_format((int) ($orderStats['total_saved_rupees'] ?? 0)) ?></strong><span>Total Saved</span></div>
          </div>

          <div class="t1-activity-head"><span>Activity Overview</span></div>
          <div class="t1-activity-grid">
            <div class="t1-activity-card t1-act--orders">
              <div class="t1-activity-icon">📋</div>
              <div class="t1-activity-info"><strong><?= (int) ($orderStats['order_count'] ?? 0) ?></strong><span>Total Orders</span></div>
            </div>
            <div class="t1-activity-card t1-act--completed">
              <div class="t1-activity-icon">✅</div>
              <div class="t1-activity-info"><strong><?= (int) ($orderStats['delivered_count'] ?? 0) ?></strong><span>Completed</span></div>
            </div>
            <div class="t1-activity-card t1-act--pending">
              <div class="t1-activity-icon">🔄</div>
              <div class="t1-activity-info"><strong><?= (int) ($orderStats['pending_count'] ?? 0) ?></strong><span>Pending</span></div>
            </div>
            <div class="t1-activity-card t1-act--cancelled">
              <div class="t1-activity-icon">✖</div>
              <div class="t1-activity-info"><strong><?= (int) ($orderStats['cancelled_count'] ?? 0) ?></strong><span>Cancelled</span></div>
            </div>
            <div class="t1-activity-card t1-act--wishlist">
              <div class="t1-activity-icon"><svg class="heart-icon" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></div>
              <div class="t1-activity-info"><strong id="dashWishlistCount">—</strong><span>Wishlist</span></div>
            </div>
            <div class="t1-activity-card t1-act--reviews">
              <div class="t1-activity-icon">⭐</div>
              <div class="t1-activity-info"><strong><?= count($deliveredReviewRows) ?></strong><span>Reviews</span></div>
            </div>
          </div>
          <div class="t1-profile-card" style="background:#fff; border-radius:16px; border:1px solid #e2e8f0; padding:24px; margin-bottom:24px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
              <h3 style="margin:0; font-size:18px; font-weight:700; color:#0f172a;">Personal Information</h3>
              <button type="button" class="profile-edit-btn" id="profileEditBtn" style="padding:8px 16px; font-size:13px; border-radius:10px;">Edit Details</button>
            </div>
            
            <div id="profileInfoBox">
              <div class="profile-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="profile-field"><dt style="color:#64748b; font-size:13px; margin-bottom:4px;">Full Name</dt><dd id="profileNameValue" style="color:#0f172a; font-weight:600; font-size:15px; margin:0;"><?= h($fullName) ?></dd></div>
                <div class="profile-field"><dt style="color:#64748b; font-size:13px; margin-bottom:4px;">Email Address</dt><dd style="color:#0f172a; font-weight:600; font-size:15px; margin:0; display:flex; align-items:center; gap:8px;"><span id="profileEmailValue"><?= h((string) ($user['email'] ?? '—')) ?></span> <span class="t1-verify-badge <?= $emailVerified ? 'is-verified' : 'is-unverified' ?>" id="emailVerifyBadge" style="font-size:11px; padding:2px 8px;"><?= $emailVerified ? '✔ Verified' : '⚠ Unverified' ?></span></dd></div>
                <div class="profile-field"><dt style="color:#64748b; font-size:13px; margin-bottom:4px;">Mobile Number</dt><dd style="color:#0f172a; font-weight:600; font-size:15px; margin:0; display:flex; align-items:center; gap:8px;"><span id="profilePhoneValue"><?= h((string) ($user['phone'] ?? '—')) ?></span> <span class="t1-verify-badge <?= $phoneVerified ? 'is-verified' : 'is-unverified' ?>" id="phoneVerifyBadge" style="font-size:11px; padding:2px 8px;"><?= $phoneVerified ? '✔ Verified' : '⚠ Unverified' ?></span></dd></div>
                <div class="profile-field"><dt style="color:#64748b; font-size:13px; margin-bottom:4px;">Gender</dt><dd id="profileGenderValue" style="color:#0f172a; font-weight:600; font-size:15px; margin:0; text-transform:capitalize;"><?= h((string) ($user['gender'] ?? '—')) ?></dd></div>
                <div class="profile-field"><dt style="color:#64748b; font-size:13px; margin-bottom:4px;">Date of Birth</dt><dd id="profileDobValue" style="color:#0f172a; font-weight:600; font-size:15px; margin:0;"><?= h(!empty($user['dob']) ? (string) $user['dob'] : '—') ?></dd></div>
              </div>
            </div>

            <form class="profile-edit-form hidden" id="profileEditForm" style="margin-top:0; padding:20px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;">
              <div class="profile-edit-grid" style="margin-top:0;">
                <div><label for="editFirstName">First name</label><input id="editFirstName" type="text" value="<?= h((string) ($user['first_name'] ?? '')) ?>" required></div>
                <div><label for="editLastName">Last name</label><input id="editLastName" type="text" value="<?= h((string) ($user['last_name'] ?? '')) ?>" required></div>
                <div>
                  <label for="editEmail">Email</label>
                  <div style="display:flex; gap:8px;">
                    <input id="editEmail" type="email" value="<?= h((string) ($user['email'] ?? '')) ?>" style="flex:1;">
                    <button type="button" class="profile-edit-btn" id="verifyEmailBtn" style="padding:0 16px; font-size:13px; white-space:nowrap; border-radius:10px;">Verify</button>
                  </div>
                </div>
                <div>
                  <label for="editPhone">Mobile number</label>
                  <div style="display:flex; gap:8px;">
                    <input id="editPhone" type="tel" value="<?= h((string) ($user['phone'] ?? '')) ?>" style="flex:1;">
                    <button type="button" class="profile-edit-btn" id="verifyPhoneBtn" style="padding:0 16px; font-size:13px; white-space:nowrap; border-radius:10px;">Verify</button>
                  </div>
                </div>
                <div><label for="editDob">Date of birth</label><input id="editDob" type="date" value="<?= h(!empty($user['dob']) ? substr((string) $user['dob'], 0, 10) : '') ?>"></div>
                <div>
                  <label for="editGender">Gender</label>
                  <select id="editGender">
                    <option value="">Select</option>
                    <option value="male" <?= (string) ($user['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= (string) ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= (string) ($user['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                  </select>
                </div>
              </div>
              <div class="profile-edit-actions" style="margin-top:20px; display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="profile-edit-cancel" id="profileEditCancel">Cancel</button>
                <button type="submit" class="profile-edit-btn">Save Changes</button>
              </div>
              <p class="profile-edit-msg hidden" id="profileEditMsg"></p>
            </form>
          </div>
        </div>

        <div class="t1-tab-panel hidden" id="tab-addresses">
          <div class="profile-main-head">
            <h2>Saved Addresses</h2>
            <button type="button" class="profile-edit-btn" id="addAddressBtn">+ Add New</button>
          </div>
          <div class="theme1-address-grid" id="addressesGrid"></div>
        </div>

        <div class="t1-tab-panel hidden" id="tab-wishlist">
          <div class="profile-main-head"><h2>Wishlist</h2></div>
          <div class="t1-wishlist-grid" id="theme1WishlistGrid"></div>
        </div>

        <div class="t1-tab-panel hidden" id="tab-reviews">
          <div class="profile-main-head">
            <h2>My Reviews</h2>
          </div>
          <?php if ($reviewFlash !== null): ?>
            <p class="profile-edit-msg <?= ($reviewFlash['type'] ?? '') === 'error' ? 'is-error' : 'is-success' ?>"><?= h((string) ($reviewFlash['msg'] ?? '')) ?></p>
          <?php endif; ?>

          <?php if ($deliveredReviewRows === []): ?>
            <div class="t1-review-empty">
              <span class="t1-review-empty__icon">⭐</span>
              <h3>No reviews yet</h3>
              <p>Apne delivered orders ke products ko review karo aur doosron ki help karo.</p>
            </div>
          <?php else: ?>
          <div class="t1-review-list">
            <?php foreach ($deliveredReviewRows as $row): ?>
              <?php
                $reviewProduct = (string) ($row['product_name'] ?? $row['item_name'] ?? 'Product');
                $reviewText = trim((string) ($row['review_text'] ?? ''));
                $reviewRating = (int) ($row['rating'] ?? 0);
                $reviewStatus = strtolower(trim((string) ($row['review_status'] ?? 'pending')));
                $reviewDateRaw = (string) ($row['review_created_at'] ?? $row['delivered_at'] ?? '');
                $reviewDate = $reviewDateRaw !== '' && strtotime($reviewDateRaw) !== false ? date('M j, Y', strtotime($reviewDateRaw)) : '';
                $reviewImageRaw = trim((string) ($row['image_path'] ?? ''));
                if ($reviewImageRaw === '') {
                    $reviewImageRaw = trim((string) ($row['gallery_first'] ?? ''));
                }
                $reviewImage = theme1_media_src($reviewImageRaw);
                $filledStars = max(0, min(5, $reviewRating));
                $emptyStars = 5 - $filledStars;
              ?>
              <div class="t1-review-card">
                <div class="t1-review-head">
                  <span class="t1-review-thumb">
                    <?php if ($reviewImage !== ''): ?>
                      <img src="<?= h($reviewImage) ?>" alt="<?= h($reviewProduct) ?>" loading="lazy">
                    <?php else: ?>
                      <?= h((string) ($row['product_emoji'] ?? $row['item_emoji'] ?? '📦')) ?>
                    <?php endif; ?>
                  </span>
                  <div class="t1-review-meta-wrap">
                    <strong><?= h($reviewProduct) ?></strong>
                    <span class="t1-review-sub">
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                      <?= h($reviewDate !== '' ? $reviewDate : 'Delivered item') ?>
                    </span>
                  </div>
                  <span class="t1-review-status t1-review-status--<?= h($reviewStatus) ?>">
                    <?php if ($reviewStatus === 'approved'): ?>✔ Approved
                    <?php elseif ($reviewText !== ''): ?>⏳ Pending
                    <?php else: ?>✍ Write Review
                    <?php endif; ?>
                  </span>
                </div>

                <?php if ($reviewText !== ''): ?>
                  <div class="t1-review-body">
                    <div class="t1-review-stars-row">
                      <span class="t1-review-stars"><?= str_repeat('★', $filledStars) . str_repeat('☆', $emptyStars) ?></span>
                      <span class="t1-review-rating-label"><?= $filledStars ?>/5</span>
                    </div>
                    <p class="t1-review-text">"<?= h($reviewText) ?>"</p>
                  </div>
                <?php else: ?>
                  <div class="t1-review-write-prompt">
                    <p class="t1-review-prompt-text">✍️ Share your experience with this product</p>
                    <form method="post" class="t1-review-form">
                      <input type="hidden" name="action" value="submit_order_review">
                      <input type="hidden" name="order_ref" value="<?= h((string) ($row['order_ref'] ?? '')) ?>">
                      <input type="hidden" name="product_id" value="<?= (int) ($row['product_id'] ?? 0) ?>">
                      <div class="t1-review-form-row">
                        <select name="rating" required>
                          <option value="5">⭐⭐⭐⭐⭐ 5 Stars</option>
                          <option value="4">⭐⭐⭐⭐ 4 Stars</option>
                          <option value="3">⭐⭐⭐ 3 Stars</option>
                          <option value="2">⭐⭐ 2 Stars</option>
                          <option value="1">⭐ 1 Star</option>
                        </select>
                        <input type="text" name="review_text" minlength="10" maxlength="1000" required placeholder="Share what you think about this product...">
                        <button type="submit" class="profile-edit-btn">Submit</button>
                      </div>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div> <!-- Close tab-reviews -->

        <div class="t1-tab-panel hidden" id="tab-rewards">
          <div class="profile-main-head">
            <h2>Rewards</h2>
          </div>
          <div class="t1-rewards-hero">
            <div class="t1-rewards-circle-wrap">
              <div class="t1-rewards-circle-inner">
                <strong id="rewardsPtsCircle" class="t1-rewards-pts-num"><?= h(number_format($loyaltyBalance)) ?></strong>
                <span class="t1-rewards-pts-lbl">Points</span>
              </div>
            </div>
            <div class="t1-rewards-hero-content">
              <h3 id="rewardsTierTitle" class="t1-rewards-tier-title"><?= h($rewardsTierTitle) ?></h3>
              <p id="rewardsLeadLine" class="t1-rewards-lead-line"><?= $rewardsLeadHtml ?></p>
              <div class="t1-rewards-progress-wrap">
                <div class="t1-rewards-progress-track">
                  <div id="rewardsProgressFill" class="t1-rewards-progress-fill" style="width: <?= h((string) round($rewardsProgressPct, 1)) ?>%;"></div>
                </div>
                <div class="t1-rewards-progress-labels">
                  <span>Gold (<?= (int) $loyaltyGoldAt ?>)</span>
                  <span>Platinum (<?= (int) $loyaltyPlatinumAt ?>)</span>
                </div>
              </div>
            </div>
          </div>

          <div class="t1-rewards-grid">
            <div class="t1-rewards-card">
              <h3 class="t1-rewards-card-title">Points History</h3>
              <div id="rewardsHistory" class="t1-rewards-history-list">
                <?php if (empty($loyaltyHistoryUi)): ?>
                  <p class="t1-rewards-empty">No points history available yet.</p>
                <?php else: ?>
                  <?php foreach ($loyaltyHistoryUi as $h): ?>
                    <div class="t1-rewards-history-row">
                      <div>
                        <div class="t1-rewards-history-desc"><?= h($h['desc']) ?></div>
                        <div class="t1-rewards-history-date"><?= h($h['date']) ?></div>
                      </div>
                      <div class="t1-rewards-history-pts"><?= h($h['pts']) ?></div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
            
            <div class="t1-rewards-card">
              <h3 class="t1-rewards-card-title">Redeem Points</h3>
              <p class="t1-rewards-redeem-text">Convert your LUXE Points into wallet balance. <strong>100 points = ₹110 off</strong> on your next order.</p>
              <div class="t1-rewards-redeem-wrap">
                <input type="number" id="redeemInput" class="t1-rewards-redeem-input" placeholder="Enter points" min="100" max="<?= max(100, $loyaltyBalance) ?>" step="100" />
                <button class="profile-edit-btn t1-rewards-redeem-btn" onclick="redeemPoints()">Redeem</button>
              </div>
            </div>
          </div>
        </div>

      </article>
    </section>
  </main>

  <div class="theme1-modal-overlay hidden" id="addressModal">
    <div class="theme1-modal-card">
      <div class="theme1-modal-header">
        <h3 id="addressModalTitle">Add address</h3>
        <button type="button" class="theme1-modal-close" id="addressModalClose" aria-label="Close">x</button>
      </div>
      <form id="addressForm">
        <input type="hidden" id="addressId" value="">
        <div class="theme1-form-grid">
          <div class="theme1-form-field"><label for="addrName">Full Name</label><input type="text" id="addrName" required maxlength="255"></div>
          <div class="theme1-form-field"><label for="addrPhone">Phone</label><input type="tel" id="addrPhone" maxlength="40"></div>
          <div class="theme1-form-field theme1-col-span-2"><label for="addrLine1">Address Line 1</label><input type="text" id="addrLine1" required maxlength="255"></div>
          <div class="theme1-form-field theme1-col-span-2"><label for="addrLine2">Address Line 2</label><input type="text" id="addrLine2" maxlength="255"></div>
          <div class="theme1-form-field"><label for="addrCity">City</label><input type="text" id="addrCity" required maxlength="100"></div>
          <div class="theme1-form-field"><label for="addrPin">PIN Code</label><input type="text" id="addrPin" required maxlength="20"></div>
          <div class="theme1-form-field"><label for="addrState">State</label><input type="text" id="addrState" required maxlength="100"></div>
          <div class="theme1-form-field"><label for="addrType">Type</label><select id="addrType"><option value="Home">Home</option><option value="Work">Work</option><option value="Other">Other</option></select></div>
          <div class="theme1-form-field theme1-col-span-2"><label class="theme1-checkbox-label"><input type="checkbox" id="addrIsDefault"> <span>Set as default address</span></label></div>
        </div>
        <div class="theme1-form-actions">
          <button type="submit" class="profile-edit-btn" id="addressSaveBtn">Save address</button>
          <button type="button" class="profile-edit-cancel" id="addressCancelBtn">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div class="theme1-modal-overlay hidden" id="verifyCodeModal" style="z-index:9999;">
    <div class="theme1-modal-card" style="max-width:420px; padding:32px; text-align:center;">
      <div style="width:56px; height:56px; border-radius:50%; background:#f0fdf4; color:#16a34a; font-size:24px; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">✉️</div>
      <h3 style="margin:0 0 12px; font-size:20px; font-weight:700; color:#0f172a;">Verify your identity</h3>
      <p style="margin:0 0 24px; font-size:14px; color:#475569;" id="verifyCodeMsg">Please enter the 6-digit code sent to your device.</p>
      
      <div style="display:flex; gap:10px; justify-content:center; margin-bottom:32px;" id="verifyCodeInputs">
        <input type="text" maxlength="1" class="t1-code-input" style="width:44px; height:52px; font-size:24px; text-align:center; border:2px solid #e2e8f0; border-radius:12px; background:#f8fafc; font-weight:600; color:#0f172a; transition:all 0.2s;" autofocus>
        <input type="text" maxlength="1" class="t1-code-input" style="width:44px; height:52px; font-size:24px; text-align:center; border:2px solid #e2e8f0; border-radius:12px; background:#f8fafc; font-weight:600; color:#0f172a; transition:all 0.2s;">
        <input type="text" maxlength="1" class="t1-code-input" style="width:44px; height:52px; font-size:24px; text-align:center; border:2px solid #e2e8f0; border-radius:12px; background:#f8fafc; font-weight:600; color:#0f172a; transition:all 0.2s;">
        <input type="text" maxlength="1" class="t1-code-input" style="width:44px; height:52px; font-size:24px; text-align:center; border:2px solid #e2e8f0; border-radius:12px; background:#f8fafc; font-weight:600; color:#0f172a; transition:all 0.2s;">
        <input type="text" maxlength="1" class="t1-code-input" style="width:44px; height:52px; font-size:24px; text-align:center; border:2px solid #e2e8f0; border-radius:12px; background:#f8fafc; font-weight:600; color:#0f172a; transition:all 0.2s;">
        <input type="text" maxlength="1" class="t1-code-input" style="width:44px; height:52px; font-size:24px; text-align:center; border:2px solid #e2e8f0; border-radius:12px; background:#f8fafc; font-weight:600; color:#0f172a; transition:all 0.2s;">
      </div>
      <style>
        .t1-code-input:focus { border-color: #3b82f6 !important; background: #fff !important; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
      </style>
      <div style="display:flex; gap:12px;">
        <button type="button" class="profile-edit-cancel" id="verifyCodeCancel" style="flex:1;">Cancel</button>
        <button type="button" class="profile-edit-btn" id="verifyCodeSubmit" style="flex:1;">Verify</button>
      </div>
    </div>
  </div>

  <?php require __DIR__ . '/partials/footer.php'; ?>
  <script>
    (function () {
      var tabLinks = document.querySelectorAll("[data-tab-link]");
      var tabPanels = document.querySelectorAll(".t1-tab-panel");
      function activateTab(tab) {
        tabPanels.forEach(function (panel) { panel.classList.toggle("hidden", panel.id !== "tab-" + tab); });
        tabLinks.forEach(function (link) { link.classList.toggle("is-active", link.getAttribute("data-tab-link") === tab); });
      }
      tabLinks.forEach(function (link) { link.addEventListener("click", function (e) { e.preventDefault(); activateTab(link.getAttribute("data-tab-link")); }); });
      var tabFromUrl = "";
      try {
        tabFromUrl = new URLSearchParams(window.location.search).get("tab") || "";
      } catch (_e) {}
      var serverTab = <?= json_encode($initialTab, JSON_THROW_ON_ERROR) ?>;
      activateTab(tabFromUrl || serverTab || "dashboard");

      var editBtn = document.getElementById("profileEditBtn");
      var editForm = document.getElementById("profileEditForm");
      var cancelBtn = document.getElementById("profileEditCancel");
      var infoBox = document.getElementById("profileInfoBox");
      var verifyEmailBtn = document.getElementById("verifyEmailBtn");
      var verifyPhoneBtn = document.getElementById("verifyPhoneBtn");
      var msg = document.getElementById("profileEditMsg");
      function setMsg(el, text, ok) { el.textContent = text; el.classList.remove("hidden", "is-success", "is-error"); el.classList.add(ok ? "is-success" : "is-error"); }
      function setVerifyBadge(id, ok) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove("is-verified", "is-unverified");
        el.classList.add(ok ? "is-verified" : "is-unverified");
        el.textContent = ok ? "✔ Verified" : "⚠ Unverified";
      }
      if (editBtn && editForm && cancelBtn && msg && infoBox) {
        editBtn.addEventListener("click", function () { 
            editForm.classList.remove("hidden"); 
            infoBox.classList.add("hidden"); 
            editBtn.classList.add("hidden"); 
        });
        cancelBtn.addEventListener("click", function () { 
            editForm.classList.add("hidden"); 
            infoBox.classList.remove("hidden"); 
            editBtn.classList.remove("hidden"); 
            msg.classList.add("hidden"); 
        });
        editForm.addEventListener("submit", async function (e) {
          e.preventDefault();
          var payload = {
            first_name: document.getElementById("editFirstName").value.trim(),
            last_name: document.getElementById("editLastName").value.trim(),
            dob: document.getElementById("editDob").value,
            gender: document.getElementById("editGender").value
          };
          try {
            var res = await fetch("../actions/update-profile.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
            var data = await res.json();
            if (!res.ok || !data.ok) throw new Error(data.message || "Could not save profile");
            document.getElementById("profileNameValue").textContent = (data.first_name || "") + " " + (data.last_name || "");
            document.getElementById("profileEmailValue").textContent = data.email || "—";
            document.getElementById("profilePhoneValue").textContent = data.phone || "—";
            document.getElementById("profileGenderValue").textContent = data.gender || "—";
            document.getElementById("profileDobValue").textContent = data.dob || "—";
            setVerifyBadge("emailVerifyBadge", !!data.email_verified);
            setVerifyBadge("phoneVerifyBadge", !!data.phone_verified);
            setMsg(msg, data.message || "Profile updated.", true);
            infoBox.classList.remove("hidden");
            editForm.classList.add("hidden");
            editBtn.classList.remove("hidden");
          } catch (err) {
            setMsg(msg, err.message || "Could not save profile.", false);
          }
        });
      }

      function promptVerificationCode(targetText) {
        return new Promise((resolve) => {
          var modal = document.getElementById("verifyCodeModal");
          var msgEl = document.getElementById("verifyCodeMsg");
          var inputs = document.querySelectorAll(".t1-code-input");
          var cancelBtn = document.getElementById("verifyCodeCancel");
          var submitBtn = document.getElementById("verifyCodeSubmit");
          
          if(msgEl && targetText) msgEl.textContent = "Please enter the 6-digit code sent to " + targetText;
          
          inputs.forEach(i => { i.value = ""; i.style.borderColor = "#e2e8f0"; });
          modal.classList.remove("hidden");
          setTimeout(() => inputs[0].focus(), 50);
          
          var cleanup = () => {
             cancelBtn.removeEventListener("click", handleCancel);
             submitBtn.removeEventListener("click", handleSubmit);
             inputs.forEach(i => {
                i.removeEventListener("input", handleInput);
                i.removeEventListener("keydown", handleKeydown);
                i.removeEventListener("paste", handlePaste);
             });
          };
          
          var handleCancel = () => {
             modal.classList.add("hidden");
             cleanup();
             resolve(null);
          };
          var handleSubmit = () => {
             var code = Array.from(inputs).map(i => i.value).join("");
             if (code.length === 6) {
                 modal.classList.add("hidden");
                 cleanup();
                 resolve(code);
             } else {
                 inputs.forEach(i => i.style.borderColor = "#ef4444");
                 setTimeout(() => inputs.forEach(i => i.style.borderColor = "#e2e8f0"), 1000);
             }
          };
          var handleInput = (e) => {
             var target = e.target;
             var val = target.value;
             if (val && target.nextElementSibling && target.nextElementSibling.classList.contains('t1-code-input')) {
                 target.nextElementSibling.focus();
             }
          };
          var handleKeydown = (e) => {
             var target = e.target;
             if (e.key === "Backspace" && !target.value && target.previousElementSibling && target.previousElementSibling.classList.contains('t1-code-input')) {
                 target.previousElementSibling.focus();
                 target.previousElementSibling.value = '';
             }
             if (e.key === "Enter") {
                 handleSubmit();
             }
          };
          var handlePaste = (e) => {
             e.preventDefault();
             var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
             for(let j=0; j<pasted.length; j++){
                inputs[j].value = pasted[j];
             }
             if(pasted.length > 0) {
                if(pasted.length < 6) inputs[pasted.length].focus();
                else inputs[5].focus();
             }
          };
          
          cancelBtn.addEventListener("click", handleCancel);
          submitBtn.addEventListener("click", handleSubmit);
          inputs.forEach(i => {
             i.addEventListener("input", handleInput);
             i.addEventListener("keydown", handleKeydown);
             i.addEventListener("paste", handlePaste);
          });
        });
      }

      async function runVerifyFlow(sendUrl, sendPayload, verifyUrl, valueId, badgeId, successText) {
        try {
          var sendRes = await fetch(sendUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(sendPayload)
          });
          var sendData = await sendRes.json();
          if (!sendRes.ok || !sendData.ok) throw new Error(sendData.message || "Could not send verification code.");
          var targetVal = sendPayload.new_email || sendPayload.new_phone || "your device";
          var code = await promptVerificationCode(targetVal);
          if (!code) return;
          var verifyRes = await fetch(verifyUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ code: code.trim() })
          });
          var verifyData = await verifyRes.json();
          if (!verifyRes.ok || !verifyData.ok) throw new Error(verifyData.message || "Could not verify.");
          if (valueId && verifyData[valueId] != null) {
            var field = document.getElementById("profile" + (valueId === "email" ? "Email" : "Phone") + "Value");
            if (field) field.textContent = verifyData[valueId];
          }
          setVerifyBadge(badgeId, true);
          setMsg(msg, successText, true);
        } catch (e) {
          setMsg(msg, e.message || "Verification failed.", false);
        }
      }

      verifyEmailBtn?.addEventListener("click", function () {
        var newEmail = document.getElementById("editEmail").value.trim();
        if (!newEmail) {
          setMsg(msg, "Email required.", false);
          return;
        }
        runVerifyFlow(
          "../actions/profile-email-change-send.php",
          { new_email: newEmail },
          "../actions/profile-email-change-verify.php",
          "email",
          "emailVerifyBadge",
          "Email verified successfully."
        );
      });

      verifyPhoneBtn?.addEventListener("click", function () {
        var newPhone = document.getElementById("editPhone").value.trim();
        if (!newPhone) {
          setMsg(msg, "Mobile number required.", false);
          return;
        }
        runVerifyFlow(
          "../actions/profile-phone-change-send.php",
          { new_phone: newPhone },
          "../actions/profile-phone-change-verify.php",
          "phone",
          "phoneVerifyBadge",
          "Mobile number verified successfully."
        );
      });

      var cpForm = document.getElementById("changePasswordForm");
      var cpMsg = document.getElementById("changePasswordMsg");
      if (cpForm && cpMsg) {
        cpForm.addEventListener("submit", async function (e) {
          e.preventDefault();
          var current = document.getElementById("cpCurrent").value;
          var next = document.getElementById("cpNew").value;
          var confirm = document.getElementById("cpConfirm").value;
          if (next !== confirm) { setMsg(cpMsg, "New password and confirm password do not match.", false); return; }
          try {
            var res = await fetch("../actions/change-password.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ current_password: current, new_password: next }) });
            var data = await res.json();
            setMsg(cpMsg, data.message || (data.ok ? "Password updated." : "Could not update password."), !!data.ok);
            if (data.ok) cpForm.reset();
          } catch (_e) { setMsg(cpMsg, "Network error. Please try again.", false); }
        });
      }

      var addresses = <?= json_encode($addresses, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
      var grid = document.getElementById("addressesGrid");
      var modal = document.getElementById("addressModal");
      var form = document.getElementById("addressForm");
      function esc(s) { return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
      function closeModal() { modal.classList.add("hidden"); }
      function openModal(item) {
        document.getElementById("addressModalTitle").textContent = item ? "Edit address" : "Add address";
        form.reset();
        document.getElementById("addressId").value = item ? String(item.id || "") : "";
        document.getElementById("addrName").value = item ? (item.name || "") : "";
        document.getElementById("addrPhone").value = item ? (item.phone || "") : "";
        document.getElementById("addrLine1").value = item ? (item.line1 || "") : "";
        document.getElementById("addrLine2").value = item ? (item.line2 || "") : "";
        document.getElementById("addrCity").value = item ? (item.city || "") : "";
        document.getElementById("addrPin").value = item ? (item.pin || "") : "";
        document.getElementById("addrState").value = item ? (item.state || "") : "";
        document.getElementById("addrType").value = item ? (item.type || "Home") : "Home";
        document.getElementById("addrIsDefault").checked = item ? !!item.isDefault : addresses.length === 0;
        modal.classList.remove("hidden");
      }
      function renderAddresses() {
        if (!grid) return;
        if (!addresses.length) { grid.innerHTML = '<div class="theme1-address-empty">No address saved yet.</div>'; return; }
        grid.innerHTML = addresses.map(function (a) {
          var line2 = (a.line2 || "").trim() ? ", " + esc(a.line2) : "";
          return '<div class="theme1-address-card"><div class="theme1-address-top"><span class="theme1-address-type">' + esc(a.type || "Home") + '</span>' + (a.isDefault ? '<span class="theme1-address-default">Default</span>' : '') + '</div><strong>' + esc(a.name || "") + '</strong><p>' + esc(a.line1 || "") + line2 + ', ' + esc(a.city || "") + ', ' + esc(a.state || "") + ' - ' + esc(a.pin || "") + '</p><p>' + (a.phone ? ('Phone: ' + esc(a.phone)) : '') + '</p><div class="theme1-address-actions"><button type="button" class="theme1-action-btn is-edit" data-edit-id="' + Number(a.id || 0) + '">Edit</button>' + (a.isDefault ? "" : '<button type="button" class="theme1-action-btn is-default" data-default-id="' + Number(a.id || 0) + '">Set default</button>') + '<button type="button" class="theme1-action-btn is-delete" data-delete-id="' + Number(a.id || 0) + '">Delete</button></div></div>';
        }).join("");
        grid.querySelectorAll("[data-edit-id]").forEach(function (b) { b.addEventListener("click", function () { openModal(addresses.find(function (x) { return Number(x.id) === Number(b.getAttribute("data-edit-id")); }) || null); }); });
        grid.querySelectorAll("[data-default-id]").forEach(function (b) { b.addEventListener("click", function () { setDefaultAddress(Number(b.getAttribute("data-default-id"))); }); });
        grid.querySelectorAll("[data-delete-id]").forEach(function (b) { b.addEventListener("click", function () { deleteAddress(Number(b.getAttribute("data-delete-id"))); }); });
      }
      async function setDefaultAddress(id) {
        var res = await fetch("../actions/set-default-address.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ id }) });
        var data = await res.json();
        if (data.ok && Array.isArray(data.addresses)) { addresses = data.addresses; renderAddresses(); }
      }
      async function deleteAddress(id) {
        if (!window.confirm("Remove this address?")) return;
        var res = await fetch("../actions/delete-address.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ id }) });
        var data = await res.json();
        if (data.ok && Array.isArray(data.addresses)) { addresses = data.addresses; renderAddresses(); }
      }
      document.getElementById("addAddressBtn")?.addEventListener("click", function () { openModal(null); });
      document.getElementById("addressModalClose")?.addEventListener("click", closeModal);
      document.getElementById("addressCancelBtn")?.addEventListener("click", closeModal);
      modal?.addEventListener("click", function (e) { if (e.target === modal) closeModal(); });
      form?.addEventListener("submit", async function (e) {
        e.preventDefault();
        var idVal = parseInt(document.getElementById("addressId").value || "0", 10);
        var payload = {
          id: idVal > 0 ? idVal : undefined,
          type: document.getElementById("addrType").value || "Home",
          name: document.getElementById("addrName").value.trim(),
          phone: document.getElementById("addrPhone").value.trim(),
          line1: document.getElementById("addrLine1").value.trim(),
          line2: document.getElementById("addrLine2").value.trim(),
          city: document.getElementById("addrCity").value.trim(),
          state: document.getElementById("addrState").value.trim(),
          pin: document.getElementById("addrPin").value.trim(),
          is_default: !!document.getElementById("addrIsDefault").checked
        };
        var res = await fetch("../actions/save-address.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) });
        var data = await res.json();
        if (data.ok && Array.isArray(data.addresses)) { addresses = data.addresses; closeModal(); renderAddresses(); } else { alert(data.message || "Could not save address."); }
      });
      renderAddresses();

      var wishlistGrid = document.getElementById("theme1WishlistGrid");
      function renderWishlist() {
        if (!wishlistGrid) return;
        var raw = localStorage.getItem("luxe_profile_wishlist_v1");
        var items = [];
        var imageMap = <?= json_encode($wishlistImageMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
        try { items = JSON.parse(raw || "[]"); } catch (_e) { items = []; }
        /* update dashboard activity count */
        var dashCount = document.getElementById("dashWishlistCount");
        if (dashCount) dashCount.textContent = Array.isArray(items) ? items.length : 0;
        if (!Array.isArray(items) || items.length === 0) {
          wishlistGrid.innerHTML = '<div class="t1-wishlist-empty"><span class="t1-wishlist-empty__icon"><svg class="heart-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:48px;height:48px;margin:0 auto;opacity:0.4;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></span><h3>Your wishlist is empty</h3><p>Product page se heart icon pe click karke apne पसंदीदा products yahan save karo.</p><span class="t1-wishlist-empty__hint">Start exploring and build your premium collection.</span></div>';
          return;
        }
        function wEsc(s) { return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
        function normalizeImg(src) {
          var v = String(src || "").trim();
          if (!v) return "";
          if (/^(https?:)?\/\//i.test(v) || v.charAt(0) === "/") return v;
          if (v.indexOf("../") === 0) return v;
          return "../" + v.replace(/^\/+/, "");
        }
        wishlistGrid.innerHTML = items.map(function (w) {
          var id = Number(w.id || 0);
          var price = Number(w.price || 0);
          var orig = Number(w.orig || 0);
          var name = wEsc(w.name || "Product");
          var image = wEsc(normalizeImg(w.image || imageMap[String(id)] || ""));
          var media = image !== ""
            ? '<img src="' + image + '" alt="' + name + '" loading="lazy" decoding="async" onerror="this.style.display=\'none\'; var fb=this.nextElementSibling; if(fb){fb.style.display=\'flex\';}"><span class="t1-wishlist-media-fallback" style="display:none;">Image unavailable</span>'
            : '<span class="t1-wishlist-media-fallback">Image unavailable</span>';
          var discountPct = (orig > price && orig > 0) ? Math.round((orig - price) / orig * 100) : 0;
          var priceHtml = '<b>Rs ' + price.toLocaleString("en-IN") + (orig > price ? ' <small>Rs ' + orig.toLocaleString("en-IN") + '</small>' : '') + '</b>' + (discountPct > 0 ? '<span class="t1-wishlist-discount">' + discountPct + '% off</span>' : '');
          return '<div class="t1-wishlist-card"><button type="button" class="t1-wishlist-remove" data-w-remove="' + id + '" aria-label="Remove from wishlist"><svg class="heart-icon" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></button><a class="t1-wishlist-link" href="../product.php?id=' + id + '"><span class="t1-wishlist-media">' + media + '</span><div class="t1-wishlist-card-body"><strong>' + name + '</strong><span>LUXE — Premium Collection</span><span class="t1-wishlist-meta">Saved item</span><div class="t1-wishlist-price-row">' + priceHtml + '</div></div></a></div>';
        }).join("");
        wishlistGrid.querySelectorAll("[data-w-remove]").forEach(function (btn) {
          btn.addEventListener("click", function () {
            var removeId = Number(btn.getAttribute("data-w-remove"));
            var nextItems = items.filter(function (it) { return Number(it.id || 0) !== removeId; });
            localStorage.setItem("luxe_profile_wishlist_v1", JSON.stringify(nextItems));
            window.dispatchEvent(new Event("theme1:wishlist-updated"));
            renderWishlist();
          });
        });
      }
      renderWishlist();
      window.addEventListener("storage", function (e) {
        if (e.key === "luxe_profile_wishlist_v1") renderWishlist();
      });

      window.__API_REDEEM_LOYALTY__ = '../actions/redeem-loyalty-points.php';
      window.redeemPoints = async function () {
        var input = document.getElementById("redeemInput");
        if (!input) return;
        var pts = parseInt(input.value || "0", 10);
        if (pts < 100) { alert("Minimum 100 points required to redeem."); return; }
        if (pts % 100 !== 0) { alert("Points must be redeemed in multiples of 100."); return; }
        if (!window.confirm("Redeem " + pts + " points? This cannot be undone.")) return;
        try {
          var res = await fetch(window.__API_REDEEM_LOYALTY__, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ points: pts })
          });
          var data = await res.json();
          alert(data.message || (data.ok ? "Points redeemed successfully!" : "Could not redeem points."));
          if (data.ok) window.location.reload();
        } catch (_e) { alert("Network error. Please try again."); }
      };

      window.addEventListener("storage", function (e) {
        if (e.key === "luxe_profile_wishlist_v1") renderWishlist();
      });
    })();
  </script>
</body>
</html>
