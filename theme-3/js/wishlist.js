(function () {
  "use strict";

  var wishlistKey = "luxe_profile_wishlist_v1";

  function getWishlist() {
    try {
      var list = JSON.parse(localStorage.getItem(wishlistKey) || "[]");
      return Array.isArray(list) ? list : [];
    } catch (_e) {
      return [];
    }
  }

  function setWishlist(items) {
    localStorage.setItem(wishlistKey, JSON.stringify(items));
    window.dispatchEvent(new Event("theme1:wishlist-updated"));
  }

  function updateBtnState(btn, active) {
    btn.classList.toggle("is-active", active);
    btn.setAttribute(
      "aria-label",
      active ? "Remove from wishlist" : "Add to wishlist"
    );
  }

  function initWishlistBtns() {
    document.querySelectorAll("[data-wishlist-btn='1']").forEach(function (btn) {
      // Prevent double-binding
      if (btn._wishlistBound) return;
      btn._wishlistBound = true;

      var id = parseInt(btn.getAttribute("data-id") || "0", 10);
      if (id <= 0) return;

      // Set initial active state from localStorage
      var current = getWishlist();
      updateBtnState(btn, current.some(function (x) { return Number(x.id) === id; }));

      btn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        var list = getWishlist();
        var idx = list.findIndex(function (x) { return Number(x.id) === id; });

        if (idx >= 0) {
          list.splice(idx, 1);
          setWishlist(list);
          updateBtnState(btn, false);
        } else {
          list.push({
            id: id,
            name: btn.getAttribute("data-name") || "Product",
            emoji: btn.getAttribute("data-emoji") || "🛍",
            price: Number(btn.getAttribute("data-price") || "0"),
            orig: Number(btn.getAttribute("data-orig") || "0"),
            image: btn.getAttribute("data-image") || ""
          });
          setWishlist(list);
          updateBtnState(btn, true);

          // Heart pulse animation
          btn.classList.add("wish-pulse");
          setTimeout(function () { btn.classList.remove("wish-pulse"); }, 600);
        }
      });
    });
  }

  // Init on DOM ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWishlistBtns);
  } else {
    initWishlistBtns();
  }

  // Re-init if new cards are dynamically added
  window.theme1InitWishlist = initWishlistBtns;
})();
