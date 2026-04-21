<?php
declare(strict_types=1);

?>
  <meta name="theme-color" id="adminThemeColorMeta" content="#f8f9fa">
  <script>
  (function () {
    try {
      var stored = localStorage.getItem('luxeAdminTheme');
      var dark =
        stored === 'dark' ||
        (stored !== 'light' &&
          typeof window.matchMedia === 'function' &&
          window.matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.classList.toggle('admin-theme-dark', dark);
      var m = document.getElementById('adminThemeColorMeta');
      if (m) {
        m.setAttribute('content', dark ? '#121212' : '#f8f9fa');
      }
    } catch (e) {}
  })();
  </script>
