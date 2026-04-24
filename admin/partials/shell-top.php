<?php
declare(strict_types=1);

/** @var string $pageTitle */
/** @var string $activeNav dashboard|settings|users|orders|earnings|sellers|seller_kyc|seller_withdrawals|deletions|product_approvals|search */
/** @var array $admin */
/** @var string $adminSearchQuery optional; top bar search input value */

if (!isset($pageTitle, $activeNav, $admin) || !is_array($admin)) {
    throw new RuntimeException('shell-top: set $pageTitle, $activeNav, $admin');
}

$topbarSearchValue = isset($adminSearchQuery) && is_string($adminSearchQuery) ? $adminSearchQuery : '';

$initials = '';
$name = trim((string) ($admin['full_name'] ?? ''));
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
    $initials = 'A';
}

$adminNotifyItems = [
    ['href' => 'orders.php', 'title' => 'New order received', 'meta' => 'Shop · Review in Orders', 'at' => strtotime('-14 minutes'), 'dot' => ''],
    ['href' => 'users.php', 'title' => 'New user registration', 'meta' => 'Accounts · Users', 'at' => strtotime('-2 hours'), 'dot' => 'muted'],
    ['href' => 'seller-kyc.php', 'title' => 'Seller KYC pending', 'meta' => 'Seller onboarding · Review now', 'at' => strtotime('-5 hours'), 'dot' => 'warn'],
    ['href' => 'seller-withdrawals.php', 'title' => 'Seller withdrawals', 'meta' => 'Finance · Mark paid / Reject', 'at' => strtotime('-1 day'), 'dot' => 'warn'],
    ['href' => 'account-deletions.php', 'title' => 'Deletion request pending', 'meta' => 'Compliance · Action needed', 'at' => strtotime('-2 days'), 'dot' => 'warn'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/theme-head-script.php'; ?>
  <title><?= h($pageTitle) ?> — LUXE Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-app admin-app--platform">
  <div class="admin-layout" id="adminLayout">
    <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
      <div class="admin-sidebar__brand">
        <div class="admin-sidebar__logo" aria-hidden="true"><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--a"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--b"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--c"></span></div>
        <div>
          <div class="admin-sidebar__title">LUXE</div>
          <div class="admin-sidebar__subtitle">Admin console</div>
        </div>
      </div>
      <div class="admin-sidebar__user">
        <div class="admin-sidebar__user-row">
          <div class="admin-sidebar__user-avatar" aria-hidden="true"><?= h($initials) ?></div>
          <div class="admin-sidebar__user-text">
            <div class="admin-sidebar__user-name"><?= h((string) $admin['full_name']) ?></div>
            <div class="admin-sidebar__user-email"><?= h((string) $admin['email']) ?></div>
          </div>
        </div>
        <a class="admin-sidebar__signout" href="logout.php">Sign Out</a>
      </div>
      <nav class="admin-nav">
        <div class="admin-nav__label">Main menu</div>
        <a class="admin-nav__link<?= $activeNav === 'dashboard' ? ' admin-nav__link--active' : '' ?>" href="index.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
          Dashboard
        </a>
        <a class="admin-nav__link<?= $activeNav === 'settings' ? ' admin-nav__link--active' : '' ?>" href="settings.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          Settings
        </a>
        <div class="admin-nav__label">Shop</div>
        <a class="admin-nav__link<?= $activeNav === 'users' ? ' admin-nav__link--active' : '' ?>" href="users.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Users
        </a>
        <a class="admin-nav__link<?= $activeNav === 'orders' ? ' admin-nav__link--active' : '' ?>" href="orders.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          Orders
        </a>
        <a class="admin-nav__link<?= $activeNav === 'earnings' ? ' admin-nav__link--active' : '' ?>" href="earnings.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          Admin Earnings
        </a>
        <a class="admin-nav__link<?= $activeNav === 'product_approvals' ? ' admin-nav__link--active' : '' ?>" href="product-approvals.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
          Product Approvals
        </a>

        <div class="admin-nav__label">Seller</div>
        <a class="admin-nav__link<?= $activeNav === 'sellers' ? ' admin-nav__sublink--active' : '' ?>" href="sellers.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="8" y1="6" x2="21" y2="6"/>
              <line x1="8" y1="12" x2="21" y2="12"/>
              <line x1="8" y1="18" x2="21" y2="18"/>
              <circle cx="4" cy="6" r="1"/>
              <circle cx="4" cy="12" r="1"/>
              <circle cx="4" cy="18" r="1"/>
            </svg>
            Seller List
          </a>
        <a class="admin-nav__link<?= $activeNav === 'seller_kyc' ? ' admin-nav__sublink--active' : '' ?>" href="seller-kyc.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2l7 4v6c0 5-3.5 9.74-7 10-3.5-.26-7-5-7-10V6l7-4z"/>
              <path d="M9.5 12.5l1.8 1.8 3.2-3.2"/>
            </svg>
            KYC Kequests
          </a>
        <a class="admin-nav__link<?= $activeNav === 'seller_withdrawals' ? ' admin-nav__sublink--active' : '' ?>" href="seller-withdrawals.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2v20"/>
              <path d="m17 7-5-5-5 5"/>
              <path d="M5 17h14"/>
            </svg>
            Withdrawals
          </a>
        <a class="admin-nav__link<?= $activeNav === 'deletions' ? ' admin-nav__link--active' : '' ?>" href="account-deletions.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
          Deletion Requests
        </a>
        <div class="admin-nav__label">Account</div>
        <a class="admin-nav__link" href="logout.php">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign Out
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
          <form class="admin-search" method="get" action="search.php" role="search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="search" name="q" value="<?= h($topbarSearchValue) ?>" placeholder="Search users, orders, sellers…" autocomplete="off" aria-label="Search admin">
          </form>
        </div>
        <div class="admin-topbar__actions">
          <button type="button" class="admin-icon-btn" id="adminFullscreenBtn" title="Fullscreen" aria-label="Fullscreen" aria-pressed="false">
            <span class="admin-fs-icon admin-fs-icon--enter" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
            </span>
            <span class="admin-fs-icon admin-fs-icon--exit" aria-hidden="true" hidden>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/></svg>
            </span>
          </button>
          <button type="button" class="admin-icon-btn" id="adminThemeBtn" title="Dark mode" aria-label="Switch to dark mode" aria-pressed="false">
            <span class="admin-theme-icon admin-theme-icon--moon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </span>
            <span class="admin-theme-icon admin-theme-icon--sun" aria-hidden="true" hidden>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
            </span>
          </button>
          <div class="admin-apps-wrap">
            <button type="button" class="admin-icon-btn admin-apps-wrap__btn" id="adminAppsBtn" title="Apps" aria-label="Apps" aria-haspopup="menu" aria-expanded="false">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </button>
            <div class="admin-apps-dropdown" role="menu" aria-label="Open applications">
              <div class="admin-apps-dropdown__inner">
                <a class="admin-apps-dropdown__item" role="menuitem" href="index.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg></span><span>Dashboard</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="settings.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span><span>Settings</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="users.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span>Users</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="orders.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span><span>Orders</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="earnings.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span><span>Platform earnings</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="product-approvals.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span><span>Product approvals</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="sellers.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg></span><span>Sellers</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="seller-kyc.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 4v6c0 5-3.5 9.74-7 10-3.5-.26-7-5-7-10V6l7-4z"/><path d="M9.5 12.5l1.8 1.8 3.2-3.2"/></svg></span><span>Seller KYC</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="seller-withdrawals.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="m17 7-5-5-5 5"/><path d="M5 17h14"/></svg></span><span>Seller withdrawals</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="account-deletions.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></span><span>Deletion requests</span></a>
                <a class="admin-apps-dropdown__item" role="menuitem" href="search.php"><span class="admin-apps-dropdown__icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></span><span>Search</span></a>
              </div>
            </div>
          </div>
          <div class="admin-notify-wrap">
            <button type="button" class="admin-icon-btn admin-icon-btn--notify admin-notify-wrap__btn" id="adminNotifyBtn" title="Notifications" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            </button>
            <div class="admin-notify-dropdown" role="region" aria-label="Notification list">
              <div class="admin-notify-dropdown__inner">
                <div class="admin-notify-dropdown__head">Notifications</div>
                <ul class="admin-notify-list">
<?php foreach ($adminNotifyItems as $n):
    $dot = (string) ($n['dot'] ?? '');
    $dotClass = 'admin-notify-row__dot' . ($dot === 'muted' ? ' admin-notify-row__dot--muted' : ($dot === 'warn' ? ' admin-notify-row__dot--warn' : ''));
    $at = (int) ($n['at'] ?? time());
    ?>
                  <li>
                    <a class="admin-notify-row" href="<?= h((string) $n['href']) ?>">
                      <span class="<?= h($dotClass) ?>" aria-hidden="true"></span>
                      <span class="admin-notify-row__body">
                        <span class="admin-notify-row__top">
                          <span class="admin-notify-row__title"><?= h((string) $n['title']) ?></span>
                          <time class="admin-notify-row__when" datetime="<?= h(date('c', $at)) ?>"><?= h(date('j M, g:i A', $at)) ?></time>
                        </span>
                        <span class="admin-notify-row__meta"><?= h((string) $n['meta']) ?></span>
                      </span>
                    </a>
                  </li>
<?php endforeach; ?>
                </ul>
                <a class="admin-notify-dropdown__foot" href="index.php">View dashboard</a>
              </div>
            </div>
          </div>
          <button type="button" class="admin-icon-btn" id="adminLanguageBtn" title="Language" aria-label="Language">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          </button>
          <div class="admin-user-wrap">
            <button type="button" class="admin-user-pill admin-user-wrap__btn" id="adminUserMenuBtn" aria-haspopup="true" aria-expanded="false" aria-label="Account menu">
              <span class="admin-user-pill__avatar" aria-hidden="true"><?= h($initials) ?></span>
              <span class="admin-user-pill__meta">
                <span class="admin-user-pill__name"><?= h((string) $admin['full_name']) ?></span>
                <span class="admin-user-pill__role">Administrator</span>
              </span>
            </button>
            <div class="admin-user-dropdown" role="region" aria-label="Account options">
              <div class="admin-user-dropdown__inner">
                <div class="admin-user-dropdown__head">
                  <div class="admin-user-dropdown__name"><?= h((string) $admin['full_name']) ?></div>
                  <div class="admin-user-dropdown__email"><?= h((string) $admin['email']) ?></div>
                </div>
                <ul class="admin-user-dropdown__list" role="list">
                  <li>
                    <a class="admin-user-dropdown__item" href="index.php">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Profile</span>
                        <span class="admin-user-dropdown__hint">Admin home</span>
                      </span>
                    </a>
                  </li>
                  <li>
                    <a class="admin-user-dropdown__item" href="settings.php">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Settings</span>
                        <span class="admin-user-dropdown__hint">Account &amp; store</span>
                      </span>
                    </a>
                  </li>
                  <li>
                    <a class="admin-user-dropdown__item admin-user-dropdown__item--logout" href="logout.php">
                      <span class="admin-user-dropdown__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                      </span>
                      <span class="admin-user-dropdown__text">
                        <span class="admin-user-dropdown__label">Sign Out</span>
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
