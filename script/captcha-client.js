(function () {
  "use strict";

  var widgets = {};

  function enabled() {
    return !!window.__LUXE_CAPTCHA_ENABLED__;
  }

  function provider() {
    return window.__LUXE_CAPTCHA_PROVIDER__ || "builtin";
  }

  function answerInput(scope) {
    return document.getElementById(scope + "-answer");
  }

  function questionEl(scope) {
    return document.getElementById(scope + "-question");
  }

  function isVisible(el) {
    if (!el) return false;
    var node = el;
    while (node && node !== document.body) {
      var style = window.getComputedStyle(node);
      if (style.display === "none" || style.visibility === "hidden") return false;
      node = node.parentElement;
    }
    return true;
  }

  function mountRecaptchaElement(el) {
    if (!enabled() || provider() !== "recaptcha" || typeof grecaptcha === "undefined") return;
    if (!el || !el.id) return;
    if (widgets[el.id] !== undefined || el.getAttribute("data-luxe-captcha-ready") === "1") return;
    if (!isVisible(el)) return;

    var siteKey = window.__LUXE_CAPTCHA_SITE_KEY__;
    if (!siteKey) return;

    try {
      var widgetId = grecaptcha.render(el.id, { sitekey: siteKey });
      widgets[el.id] = widgetId;
      el.setAttribute("data-luxe-captcha-ready", "1");
    } catch (e) {
      console.warn("LUXE captcha mount failed for #" + el.id, e);
    }
  }

  function mountAllRecaptcha() {
    if (provider() !== "recaptcha") return;
    document.querySelectorAll("[data-luxe-captcha]").forEach(mountRecaptchaElement);
  }

  function scheduleRecaptchaMount() {
    if (typeof grecaptcha !== "undefined" && typeof grecaptcha.ready === "function") {
      grecaptcha.ready(mountAllRecaptcha);
      return;
    }
    mountAllRecaptcha();
  }

  window.luxeCaptchaOnload = scheduleRecaptchaMount;

  function builtinAnswer(scope) {
    var input = answerInput(scope);
    return input ? String(input.value || "").trim() : "";
  }

  function clearBuiltin(scope) {
    var input = answerInput(scope);
    if (input) input.value = "";
  }

  async function refreshBuiltin(scope) {
    var url = window.__LUXE_CAPTCHA_REFRESH_URL__;
    if (!url) return;
    var res = await fetch(url + "?scope=" + encodeURIComponent(scope), {
      credentials: "same-origin",
    });
    var data = await res.json();
    if (!res.ok || !data.ok) {
      throw new Error((data && data.message) || "Could not refresh security check.");
    }
    var q = questionEl(scope);
    if (q) q.textContent = data.question + " = ?";
    clearBuiltin(scope);
  }

  window.LuxeCaptcha = {
    enabled: enabled,
    provider: provider,
    token: function (scope) {
      if (!enabled()) return "";
      if (provider() === "recaptcha") {
        var widgetId = widgets[scope];
        if (widgetId === undefined) return "";
        try {
          return grecaptcha.getResponse(widgetId) || "";
        } catch (e) {
          return "";
        }
      }
      return builtinAnswer(scope);
    },
    reset: function (scope) {
      if (!enabled()) return;
      if (provider() === "recaptcha") {
        var widgetId = widgets[scope];
        if (widgetId === undefined) return;
        try {
          grecaptcha.reset(widgetId);
        } catch (e) {}
        return;
      }
      refreshBuiltin(scope).catch(function () {
        clearBuiltin(scope);
      });
    },
    requireToken: function (scope) {
      if (!enabled()) return "";
      if (provider() === "recaptcha") {
        scheduleRecaptchaMount();
      }
      var token = this.token(scope);
      if (!token) {
        throw new Error("Please solve the security check.");
      }
      return token;
    },
    refreshPending: function () {
      if (provider() === "recaptcha") {
        scheduleRecaptchaMount();
      }
    },
  };

  document.addEventListener("click", function (e) {
    var btn = e.target && e.target.closest ? e.target.closest(".luxe-captcha-refresh") : null;
    if (!btn) return;
    e.preventDefault();
    var scope = btn.getAttribute("data-captcha-scope");
    if (!scope) return;
    refreshBuiltin(scope).catch(function (err) {
      alert(err.message || "Could not refresh security check.");
    });
  });

  if (provider() === "recaptcha" && typeof grecaptcha !== "undefined") {
    scheduleRecaptchaMount();
  }
})();
