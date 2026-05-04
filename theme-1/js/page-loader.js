/**
 * Theme 1 — dismiss full-page loader after window load.
 */
(function () {
  var el = document.getElementById("t1-loader");
  if (!el) return;

  function hide() {
    el.classList.add("t1-loader--hide");
    el.setAttribute("aria-busy", "false");
    el.setAttribute("aria-hidden", "true");
  }

  var delay = 520;
  try {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      delay = 120;
    }
  } catch (_e) {}

  function scheduleHide() {
    window.setTimeout(hide, delay);
  }

  if (document.readyState === "complete") {
    scheduleHide();
  } else {
    window.addEventListener("load", scheduleHide, { once: true });
  }
})();
