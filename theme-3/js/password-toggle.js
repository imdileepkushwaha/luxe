(function () {
  "use strict";

  function syncUi(input, btn) {
    var visible = input.type === "text";
    btn.classList.toggle("t2-login-pass-toggle--on", visible);
    btn.setAttribute("aria-pressed", visible ? "true" : "false");
    btn.setAttribute("aria-label", visible ? "Hide password" : "Show password");
  }

  function initWrap(wrap) {
    if (wrap.dataset.t2PwInit === "1") return;
    wrap.dataset.t2PwInit = "1";

    var input = wrap.querySelector('input[type="password"], input[type="text"]');
    var btn = wrap.querySelector(".t2-login-pass-toggle");
    if (!input || !btn) return;

    btn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      input.type = input.type === "password" ? "text" : "password";
      syncUi(input, btn);
      input.focus();
    });
    syncUi(input, btn);
  }

  document.querySelectorAll(".t2-login-pass-wrap").forEach(initWrap);
})();
