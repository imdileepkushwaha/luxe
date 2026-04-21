      </main>
      <footer class="admin-footer">
        <span>Copyright © <?= (int) date('Y') ?> LUXE Seller</span>
        <div class="admin-footer__links">
          <a href="../index.php">Store</a>
          <a href="settings.php">Settings</a>
          <a href="profile.php">Profile</a>
          <a href="orders.php">Orders</a>
          <a href="products.php">Products</a>
          <a href="coupons.php">Coupons</a>
          <a href="inventory.php">Inventory</a>
          <a href="reviews.php">Reviews</a>
          <a href="shipping-settings.php">Shipping</a>
          <a href="delivery-options.php">Delivery</a>
          <a href="return-refund-settings.php">Returns</a>
          <a href="earnings.php">Earnings</a>
          <a href="withdraw-requests.php">Withdraw</a>
          <a href="transactions.php">Transactions</a>
          <a href="kyc-details.php">KYC</a>
        </div>
      </footer>
    </div>
  </div>
  <script>
    (function () {
      var layout = document.getElementById('adminLayout');
      var btn = document.getElementById('adminMenuBtn');
      var overlay = document.getElementById('adminSidebarOverlay');
      if (!layout || !btn) return;
      function close() { layout.classList.remove('sidebar-open'); }
      btn.addEventListener('click', function () { layout.classList.toggle('sidebar-open'); });
      if (overlay) overlay.addEventListener('click', close);
      window.addEventListener('resize', function () {
        if (window.innerWidth > 900) close();
      });
    })();
    (function () {
      var themeBtn = document.getElementById('adminThemeBtn');
      if (!themeBtn) return;
      var root = document.documentElement;
      var iconMoon = themeBtn.querySelector('.admin-theme-icon--moon');
      var iconSun = themeBtn.querySelector('.admin-theme-icon--sun');
      function isDark() {
        return root.classList.contains('admin-theme-dark');
      }
      function syncThemeUi() {
        var dark = isDark();
        themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
        themeBtn.title = dark ? 'Light mode' : 'Dark mode';
        themeBtn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
        if (iconMoon) iconMoon.hidden = dark;
        if (iconSun) iconSun.hidden = !dark;
      }
      function metaForDark(dark) {
        var tc = document.getElementById('adminThemeColorMeta');
        if (tc) tc.setAttribute('content', dark ? '#121212' : '#f8f9fa');
      }
      function applyTheme(dark) {
        root.classList.toggle('admin-theme-dark', dark);
        metaForDark(dark);
        syncThemeUi();
      }
      function persistTheme(dark) {
        try {
          localStorage.setItem('luxeAdminTheme', dark ? 'dark' : 'light');
        } catch (e) {}
        applyTheme(dark);
      }
      themeBtn.addEventListener('click', function () {
        persistTheme(!isDark());
      });
      syncThemeUi();
      try {
        if (window.matchMedia && localStorage.getItem('luxeAdminTheme') === null) {
          window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (ev) {
            try {
              if (localStorage.getItem('luxeAdminTheme') !== null) return;
            } catch (err) {
              return;
            }
            applyTheme(ev.matches);
          });
        }
      } catch (e) {}
    })();
    (function () {
      var wrap = document.querySelector('.admin-notify-wrap');
      var btn = document.getElementById('sellerNotifyBtn');
      if (!wrap || !btn) return;
      function open(on) {
        btn.setAttribute('aria-expanded', on ? 'true' : 'false');
      }
      wrap.addEventListener('mouseenter', function () { open(true); });
      wrap.addEventListener('mouseleave', function () { open(false); });
      wrap.addEventListener('focusin', function () { open(true); });
      wrap.addEventListener('focusout', function (e) {
        if (!wrap.contains(e.relatedTarget)) open(false);
      });
    })();
    (function () {
      var wrap = document.querySelector('.admin-user-wrap');
      var btn = document.getElementById('sellerUserMenuBtn');
      if (!wrap || !btn) return;
      function open(on) {
        btn.setAttribute('aria-expanded', on ? 'true' : 'false');
      }
      wrap.addEventListener('mouseenter', function () { open(true); });
      wrap.addEventListener('mouseleave', function () { open(false); });
      wrap.addEventListener('focusin', function () { open(true); });
      wrap.addEventListener('focusout', function (e) {
        if (!wrap.contains(e.relatedTarget)) open(false);
      });
    })();
  </script>
  <script src="js/password-toggle.js?v=2"></script>
</body>
</html>
