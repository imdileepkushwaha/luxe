<?php
declare(strict_types=1);

/** @var string $pageTitle */
/** @var string $activeNav dashboard|profile|orders|products|inventory|reviews|shipping|delivery|returns|earnings|withdraw|transactions|kyc */
/** @var array $seller */

if (!isset($pageTitle, $activeNav, $seller) || !is_array($seller)) {
    throw new RuntimeException('shell-top: set $pageTitle, $activeNav, $seller');
}

$initials = '';
$name = trim((string) ($seller['full_name'] ?? ''));
if ($name !== '') {
    $parts = preg_split('/\s+/', $name) ?: [];
    foreach ($parts as $p) {
        $initials .= strtoupper(substr($p, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }
}
if ($initials === '') {
    $initials = 'S';
}

$sellerNotifications = [];
try {
    $pdo = db();
    $notifySt = $pdo->prepare(
        "SELECT o.id, o.order_ref, o.status, o.total_amount, o.created_at,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))), ''), 'Guest') AS customer_name,
                GROUP_CONCAT(DISTINCT oi.name ORDER BY oi.id SEPARATOR ', ') AS items_text
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.id
         INNER JOIN products p ON p.id = oi.product_id
         LEFT JOIN users u ON u.id = o.user_id
         WHERE p.seller_id = ?
         GROUP BY o.id, o.order_ref, o.status, o.total_amount, o.created_at, u.first_name, u.last_name
         ORDER BY o.id DESC
         LIMIT 6"
    );
    $notifySt->execute([(int) $seller['id']]);
    $sellerNotifications = $notifySt->fetchAll();
} catch (Throwable) {
    $sellerNotifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>
    (function () {
      try {
        if (localStorage.getItem('luxeAdminTheme') === 'dark') {
          document.documentElement.classList.add('admin-theme-dark');
        }
      } catch (e) {}
    })();
  </script>
  <title><?= h($pageTitle) ?> - LUXE Seller</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../admin/css/admin.css">
  <link rel="stylesheet" href="css/seller.css">
</head>
<body class="admin-app">
  <div class="admin-layout" id="adminLayout">
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
      <div class="admin-sidebar__brand">
        <div class="admin-sidebar__logo" aria-hidden="true"><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--a"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--b"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--c"></span></div>
        <div>
          <div class="admin-sidebar__title">LUXE</div>
          <div class="admin-sidebar__subtitle">Seller panel</div>
        </div>
      </div>
      <div class="admin-sidebar__user">
        <div class="admin-sidebar__user-row">
          <div class="admin-sidebar__user-avatar" aria-hidden="true"><?= h($initials) ?></div>
          <div class="admin-sidebar__user-text">
            <div class="admin-sidebar__user-name"><?= h((string) $seller['full_name']) ?></div>
            <div class="admin-sidebar__user-email"><?= h((string) $seller['email']) ?></div>
          </div>
        </div>
        <a class="admin-sidebar__signout" href="logout.php">Sign out</a>
      </div>
      <nav class="admin-nav">
        <div class="admin-nav__label">Seller menu</div>
        <a class="admin-nav__link<?= $activeNav === 'dashboard' ? ' admin-nav__link--active' : '' ?>" href="index.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
          Dashboard
        </a>
        <a class="admin-nav__link<?= $activeNav === 'orders' ? ' admin-nav__link--active' : '' ?>" href="orders.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/></svg>
          Orders
        </a>
        <a class="admin-nav__link<?= $activeNav === 'products' ? ' admin-nav__link--active' : '' ?>" href="products.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
          Products
        </a>
        <a class="admin-nav__link<?= $activeNav === 'inventory' ? ' admin-nav__link--active' : '' ?>" href="inventory.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>
          Inventory
        </a>
        <a class="admin-nav__link<?= $activeNav === 'reviews' ? ' admin-nav__link--active' : '' ?>" href="reviews.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8"/><path d="M8 13h6"/></svg>
          Reviews
        </a>
        <a class="admin-nav__link<?= $activeNav === 'shipping' ? ' admin-nav__link--active' : '' ?>" href="shipping-settings.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h13v10H3z"/><path d="M16 10h3l2 2v5h-5z"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="17.5" cy="17.5" r="1.5"/></svg>
          Shipping settings
        </a>
        <a class="admin-nav__link<?= $activeNav === 'delivery' ? ' admin-nav__link--active' : '' ?>" href="delivery-options.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/><path d="M5 5v14"/></svg>
          Delivery options
        </a>
        <a class="admin-nav__link<?= $activeNav === 'returns' ? ' admin-nav__link--active' : '' ?>" href="return-refund-settings.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18"/><path d="M7 7v10a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V7"/><path d="M9 11h6"/></svg>
          Returns & refunds
        </a>
        <div class="admin-nav__label">Finance</div>
        <a class="admin-nav__link<?= $activeNav === 'earnings' ? ' admin-nav__link--active' : '' ?>" href="earnings.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          Earnings
        </a>
        <a class="admin-nav__link<?= $activeNav === 'withdraw' ? ' admin-nav__link--active' : '' ?>" href="withdraw-requests.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="m17 7-5-5-5 5"/><path d="M5 17h14"/></svg>
          Withdraw requests
        </a>
        <a class="admin-nav__link<?= $activeNav === 'transactions' ? ' admin-nav__link--active' : '' ?>" href="transactions.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 6 13.5 14.5 8.5 9.5 2 16"/><polyline points="16 6 22 6 22 12"/></svg>
          Transactions
        </a>
        <div class="admin-nav__label">Account</div>
        <a class="admin-nav__link" href="logout.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout
        </a>
      </nav>
    </aside>
    <div class="admin-sidebar-overlay" id="adminSidebarOverlay" aria-hidden="true"></div>
    <div class="admin-main-wrap">
      <header class="admin-topbar">
        <div class="admin-topbar__left">
          <button type="button" class="admin-menu-btn" id="adminMenuBtn" aria-label="Open menu" aria-expanded="false" aria-controls="adminSidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
          </button>
          <div class="seller-top-meta">Manage orders and products in your categories</div>
        </div>
        <div class="admin-topbar__actions">
          <div class="admin-notify-wrap">
            <button type="button" class="admin-icon-btn admin-icon-btn--notify admin-notify-wrap__btn" id="sellerNotifyBtn" title="Notifications" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            </button>
            <div class="admin-notify-dropdown" role="region" aria-label="Order notifications">
              <div class="admin-notify-dropdown__inner">
                <div class="admin-notify-dropdown__head">Order notifications</div>
                <ul class="admin-notify-list">
                  <?php foreach ($sellerNotifications as $n): ?>
                    <?php
                    $status = strtolower((string) ($n['status'] ?? 'processing'));
                    $dotClass = 'admin-notify-row__dot';
                    if ($status === 'processing') {
                        $dotClass .= ' admin-notify-row__dot--warn';
                    } elseif ($status === 'delivered' || $status === 'shipped') {
                        $dotClass .= ' admin-notify-row__dot--muted';
                    }
                    $meta = ucfirst($status) . ' · Rs ' . number_format((int) $n['total_amount']) . ' · ' . (new DateTimeImmutable((string) $n['created_at']))->format('M j, g:i A');
                    ?>
                    <li>
                      <a class="admin-notify-row" href="orders.php">
                        <span class="<?= h($dotClass) ?>" aria-hidden="true"></span>
                        <span class="admin-notify-row__body">
                          <span class="admin-notify-row__title"><?= h((string) $n['order_ref']) ?> · <?= h((string) $n['customer_name']) ?></span>
                          <span class="admin-notify-row__meta"><?= h($meta) ?></span>
                        </span>
                      </a>
                    </li>
                  <?php endforeach; ?>
                  <?php if ($sellerNotifications === []): ?>
                    <li>
                      <span class="admin-notify-row">
                        <span class="admin-notify-row__dot admin-notify-row__dot--muted" aria-hidden="true"></span>
                        <span class="admin-notify-row__body">
                          <span class="admin-notify-row__title">No orders yet</span>
                          <span class="admin-notify-row__meta">New order notifications yahan dikhenge.</span>
                        </span>
                      </span>
                    </li>
                  <?php endif; ?>
                </ul>
                <a class="admin-notify-dropdown__foot" href="orders.php">View all orders</a>
              </div>
            </div>
          </div>
          <button type="button" class="admin-icon-btn" id="adminThemeBtn" title="Dark mode" aria-label="Switch to dark mode" aria-pressed="false">
            <span class="admin-theme-icon admin-theme-icon--moon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </span>
            <span class="admin-theme-icon admin-theme-icon--sun" aria-hidden="true" hidden>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            </span>
          </button>
          <div class="admin-user-wrap">
            <button type="button" class="admin-user-pill admin-user-wrap__btn" id="sellerUserMenuBtn" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
              <span class="admin-user-pill__avatar" aria-hidden="true"><?= h($initials) ?></span>
              <span class="admin-user-pill__meta">
                <span class="admin-user-pill__name"><?= h((string) $seller['full_name']) ?></span>
                <span class="admin-user-pill__role">Seller</span>
              </span>
            </button>
            <div class="admin-user-dropdown" role="region" aria-label="Seller account options">
              <div class="admin-user-dropdown__inner">
                <div class="admin-user-dropdown__head">
                  <div class="admin-user-dropdown__name"><?= h((string) $seller['full_name']) ?></div>
                  <div class="admin-user-dropdown__email"><?= h((string) $seller['email']) ?></div>
                </div>
                <ul class="admin-user-dropdown__list" role="list">
                  <li>
                    <a class="admin-user-dropdown__item" href="profile.php">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Profile</span>
                        <span class="admin-user-dropdown__hint">Seller dashboard</span>
                      </span>
                    </a>
                  </li>
                  <li>
                    <span class="admin-user-dropdown__item admin-user-dropdown__item--disabled" tabindex="-1" aria-disabled="true">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Settings</span>
                        <span class="admin-user-dropdown__hint">Coming soon</span>
                      </span>
                    </span>
                  </li>
                  <li>
                    <a class="admin-user-dropdown__item" href="index.php#danger-zone">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Delete account</span>
                        <span class="admin-user-dropdown__hint">Open danger zone</span>
                      </span>
                    </a>
                  </li>
                  <li>
                    <a class="admin-user-dropdown__item admin-user-dropdown__item--logout" href="logout.php">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Log out</span>
                        <span class="admin-user-dropdown__hint">End session</span>
                      </span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </header>
      <main class="admin-content">
