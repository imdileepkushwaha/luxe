/**
 * Theme 2 — dismiss full-page loader after window load (distinct UX from theme 1).
 */
(function () {
  var el = document.getElementById("t2-loader");
  if (!el) return;

  function hide() {
    el.classList.add("t2-loader--hide");
    el.setAttribute("aria-busy", "false");
    el.setAttribute("aria-hidden", "true");
  }

  var delay = 480;
  try {
    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      delay = 100;
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
