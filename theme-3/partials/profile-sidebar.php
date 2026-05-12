<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$currentTab = $_GET['tab'] ?? 'dashboard';

// Helper to determine if a menu item is active
if (!function_exists('is_profile_menu_active')) {
    function is_profile_menu_active($pageName, $tabName = null) {
        global $currentPage, $currentTab;
        if ($currentPage === $pageName) {
            if ($tabName === null) {
                return true; // Match by page only (e.g., orders.php)
            }
            return $currentTab === $tabName; // Match by page and tab
        }
        return false;
    }
}

// Helper to generate the href. If we are on profile.php, we use # for tabs to let JS handle it without reloading,
// EXCEPT if we are currently not on profile.php, then we use profile.php?tab=...
if (!function_exists('profile_menu_href')) {
    function profile_menu_href($tabName) {
        global $currentPage;
        if ($currentPage === 'profile.php') {
            return '#';
        }
        return 'profile.php?tab=' . $tabName;
    }
}
?>
<aside class="profile-side">
  <div class="profile-card-top">
    <div class="profile-avatar-wrap">
      <div class="profile-avatar"><?= h($initial ?? 'M') ?></div>
    </div>
    <div class="profile-info-text">
      <h3><?= h($fullName ?? 'Member') ?></h3>
      <p><?= h((string) ($user['email'] ?? '')) ?></p>
      <span class="profile-member-since"><?= h($memberSince ?? '') ?></span>
    </div>
  </div>
  <button type="button" class="profile-menu-toggle mobile-only" id="profileMenuToggle" aria-expanded="false">
    <span class="toggle-text">Profile Menu</span>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="toggle-icon"><polyline points="6 9 12 15 18 9"></polyline></svg>
  </button>
  <ul class="profile-menu" id="profileMenu">
    <li><a href="<?= profile_menu_href('dashboard') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="dashboard"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'dashboard') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10M12 20V4M6 20v-6"/></svg></span> Dashboard
    </a></li>
    <li><a href="orders.php" class="<?= is_profile_menu_active('orders.php') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></span> Orders
    </a></li>
    <li><a href="<?= profile_menu_href('addresses') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="addresses"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'addresses') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span> Address
    </a></li>
    <li><a href="<?= profile_menu_href('rewards') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="rewards"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'rewards') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v10H4V12M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg></span> Rewards
    </a></li>
    <li><a href="<?= profile_menu_href('reviews') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="reviews"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'reviews') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span> Reviews
    </a></li>
    <li><a href="settings.php" class="<?= is_profile_menu_active('settings.php') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> Account Details
    </a></li>
    <li><a href="<?= profile_menu_href('wishlist') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="wishlist"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'wishlist') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span> Wishlist
    </a></li>
    <div class="profile-menu-divider"></div>
    <li><a href="<?= h(luxe_action_href('logout.php?redirect=' . rawurlencode('index.php'))) ?>" class="logout-link">
      <span class="profile-menu-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></span> Logout
    </a></li>
  </ul>
</aside>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var toggleBtn = document.getElementById('profileMenuToggle');
    var menu = document.getElementById('profileMenu');
    
    if (toggleBtn && menu) {
      // Find the active item text to update the button label
      var activeItem = menu.querySelector('a.is-active');
      if (activeItem) {
        var activeText = activeItem.textContent.replace(/[^a-zA-Z\s]/g, '').trim();
        if (activeText) {
          toggleBtn.querySelector('.toggle-text').textContent = activeText;
        }
      }

      toggleBtn.addEventListener('click', function() {
        var isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
        toggleBtn.setAttribute('aria-expanded', !isExpanded);
        menu.classList.toggle('active');
      });

      // Close menu when clicking a link on mobile (useful for tab links)
      menu.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
          if (window.innerWidth <= 780) {
            menu.classList.remove('active');
            toggleBtn.setAttribute('aria-expanded', 'false');
            var text = link.textContent.replace(/[^a-zA-Z\s]/g, '').trim();
            if (text) {
              toggleBtn.querySelector('.toggle-text').textContent = text;
            }
          }
        });
      });
    }
  });
</script>
