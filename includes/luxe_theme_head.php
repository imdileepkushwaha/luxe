<?php

declare(strict_types=1);

/** Runs before luxe.css: default theme is light; honors localStorage key used by script/luxe.js (luxe-theme). */
?>
  <script>
  (function () {
    try {
      var t = localStorage.getItem("luxe-theme");
      if (t !== "light" && t !== "dark") t = "light";
      document.documentElement.setAttribute("data-theme", t);
    } catch (e) {
      document.documentElement.setAttribute("data-theme", "light");
    }
  })();
  </script>
