<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$currentTab = $_GET['tab'] ?? 'dashboard';

// Helper to determine if a menu item is active
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

// Helper to generate the href. If we are on profile.php, we use # for tabs to let JS handle it without reloading,
// EXCEPT if we are currently not on profile.php, then we use profile.php?tab=...
function profile_menu_href($tabName) {
    global $currentPage;
    if ($currentPage === 'profile.php') {
        return '#';
    }
    return 'profile.php?tab=' . $tabName;
}
?>
<aside class="profile-side">
  <div class="profile-card-top">
    <div class="profile-avatar-wrap">
      <div class="profile-avatar"><?= h($initial ?? 'M') ?></div>
    </div>
    <h3><?= h($fullName ?? 'Member') ?></h3>
    <p><?= h((string) ($user['email'] ?? '')) ?></p>
    <span class="profile-member-since"><?= h($memberSince ?? '') ?></span>
  </div>
  <button type="button" class="profile-menu-toggle mobile-only" id="profileMenuToggle" aria-expanded="false">
    <span class="toggle-text">Profile Menu</span>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="toggle-icon"><polyline points="6 9 12 15 18 9"></polyline></svg>
  </button>
  <ul class="profile-menu" id="profileMenu">
    <li><a href="<?= profile_menu_href('dashboard') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="dashboard"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'dashboard') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon">🏠</span> Dashboard
    </a></li>
    <li><a href="<?= profile_menu_href('addresses') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="addresses"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'addresses') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon">📍</span> Addresses
    </a></li>
    <li><a href="<?= profile_menu_href('wishlist') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="wishlist"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'wishlist') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon"><svg class="heart-icon" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg></span> Wishlist
    </a></li>
    <li><a href="<?= profile_menu_href('reviews') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="reviews"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'reviews') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon">⭐</span> Reviews
    </a></li>
    <li><a href="<?= profile_menu_href('rewards') ?>" <?= $currentPage === 'profile.php' ? 'data-tab-link="rewards"' : '' ?> class="<?= is_profile_menu_active('profile.php', 'rewards') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon">🎁</span> Rewards
    </a></li>
    <li><a href="orders.php" class="<?= is_profile_menu_active('orders.php') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon">📦</span> My Orders
    </a></li>
    <li><a href="settings.php" class="<?= is_profile_menu_active('settings.php') ? 'is-active' : '' ?>">
      <span class="profile-menu-icon">⚙️</span> Settings
    </a></li>
    <div class="profile-menu-divider"></div>
    <li><a href="../actions/logout.php?redirect=theme-1/index.php" style="color:#dc2626;">
      <span class="profile-menu-icon" style="background:linear-gradient(140deg,#fee2e2,#fecaca);border-color:rgba(239,68,68,0.2);">🚪</span> Logout
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
