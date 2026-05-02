(function () {
  var root = document.getElementById("hero-carousel");
  if (!root) return;

  var track = root.querySelector("[data-hero-track]");
  var slides = root.querySelectorAll("[data-hero-slide]");
  var dots = root.querySelectorAll("[data-hero-dot]");
  if (!track || !slides.length) return;

  var index = 0;
  var timer = null;
  var INTERVAL_MS = 5000;
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function goTo(i) {
    var n = slides.length;
    index = ((i % n) + n) % n;
    track.style.transform = "translateX(-" + index * 100 + "%)";
    slides.forEach(function (slide, j) {
      slide.setAttribute("aria-hidden", j === index ? "false" : "true");
    });
    dots.forEach(function (dot, j) {
      var on = j === index;
      dot.classList.toggle("is-active", on);
      dot.setAttribute("aria-selected", on ? "true" : "false");
    });
  }

  function next() {
    goTo(index + 1);
  }

  function start() {
    if (reducedMotion) return;
    stop();
    timer = window.setInterval(next, INTERVAL_MS);
  }

  function stop() {
    if (timer) {
      window.clearInterval(timer);
      timer = null;
    }
  }

  dots.forEach(function (dot) {
    dot.addEventListener("click", function () {
      var j = parseInt(dot.getAttribute("data-hero-dot"), 10);
      if (!isNaN(j)) {
        goTo(j);
        start();
      }
    });
  });

  root.addEventListener("mouseenter", stop);
  root.addEventListener("mouseleave", start);
  root.addEventListener("focusin", stop);
  root.addEventListener("focusout", function (e) {
    if (!root.contains(e.relatedTarget)) start();
  });

  document.addEventListener("visibilitychange", function () {
    if (document.hidden) stop();
    else start();
  });

  if (reducedMotion) {
    track.style.transition = "none";
  }

  goTo(0);
  start();
})();
