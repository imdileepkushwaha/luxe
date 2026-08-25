(function () {
  document.querySelectorAll(".pcard__swatches").forEach(function (group) {
    group.addEventListener("click", function (e) {
      var btn = e.target.closest(".pcard__swatch");
      if (!btn || !group.contains(btn)) return;
      group.querySelectorAll(".pcard__swatch").forEach(function (b) {
        b.classList.remove("is-active");
        b.setAttribute("aria-pressed", "false");
      });
      btn.classList.add("is-active");
      btn.setAttribute("aria-pressed", "true");
    });
  });
})();
