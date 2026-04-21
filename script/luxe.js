// ================================================================
//  LUXE — Unified JavaScript Bundle
//  All page logic merged into a single file.
//  Each page section is wrapped in guards so it only runs
//  when the relevant DOM elements exist.
// ================================================================

const LUXE_URLS = (typeof window.LUXE_URLS !== "undefined" && window.LUXE_URLS) ? window.LUXE_URLS : {
  home: "index.php", login: "login.php", cart: "cart.php", product: "product.php", orders: "orders.php", profile: "profile.php"
};
function luxeProductUrl(id) { return LUXE_URLS.product + "?id=" + encodeURIComponent(String(id)); }

function luxeEscapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

/** When `window.__PRODUCTS__` is missing (static preview), search still works with demo rows. */
const LUXE_DEMO_CATALOG = [
  { id: 1, name: "AirMax Pro 2026", category: "fashion", price: 8999, original: 14500, emoji: "👟", badge: "Best Seller", rating: 4.8, reviews: 2140, brand: "" },
  { id: 2, name: "Sony WH-1000XM5", category: "electronics", price: 18999, original: 34990, emoji: "🎧", badge: "Sale", rating: 4.9, reviews: 5621, brand: "" },
  { id: 3, name: "Retinol Serum Kit", category: "beauty", price: 1899, original: 3500, emoji: "🧴", badge: "New", rating: 4.6, reviews: 890, brand: "" },
  { id: 4, name: "Smart Coffee Maker", category: "home", price: 5499, original: 8999, emoji: "☕", badge: "Sale", rating: 4.7, reviews: 432, brand: "" },
  { id: 5, name: "Apple Watch SE", category: "electronics", price: 19500, original: 29900, emoji: "⌚", badge: "Hot", rating: 4.8, reviews: 3210, brand: "" },
  { id: 6, name: "Linen Co-ord Set", category: "fashion", price: 3299, original: 5500, emoji: "👗", badge: "New", rating: 4.5, reviews: 678, brand: "" },
  { id: 7, name: "Vitamin C Gummies", category: "beauty", price: 699, original: 1200, emoji: "🍊", badge: "Sale", rating: 4.4, reviews: 1230, brand: "" },
  { id: 8, name: "LED Desk Lamp", category: "home", price: 1599, original: 2800, emoji: "💡", badge: "Best Seller", rating: 4.7, reviews: 980, brand: "" },
];

function luxeGetSearchCatalog() {
  if (typeof window.__PRODUCTS__ !== "undefined" && window.__PRODUCTS__ && window.__PRODUCTS__.length) {
    return window.__PRODUCTS__;
  }
  return LUXE_DEMO_CATALOG;
}

/** True when listing must not quick-add (variant stock or multiple size/color options). User picks on product page. */
function luxeProductRequiresVariantPick(p) {
  return !!(p && p.requires_variant_pick);
}

function luxeProductMatchesSearchQuery(p, query) {
  const rawQ = String(query || "").trim().toLowerCase();
  if (!rawQ) return true;
  const words = rawQ.split(/\s+/).filter(Boolean);
  const hay = [p.name, p.brand, p.category, p.badge, p.emoji]
    .map(x => String(x ?? "").toLowerCase())
    .join(" ");
  return words.every(w => hay.indexOf(w) !== -1);
}

function luxeSearchLiveRank(p, q) {
  const raw = String(q || "").trim().toLowerCase();
  const token = raw.split(/\s+/).filter(Boolean)[0] || raw;
  const name = String(p.name || "").toLowerCase();
  const brand = String(p.brand || "").toLowerCase();
  if (!token) return 50;
  if (name.startsWith(token)) return 0;
  if (name.indexOf(token) !== -1) return 10;
  if (brand.indexOf(token) !== -1) return 20;
  return 30;
}

function luxeWishlistIconSvg(active) {
  if (active) {
    return `<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 21.35 10.55 20.03C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3A6.12 6.12 0 0 1 12 5.09 6.12 6.12 0 0 1 16.5 3C19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54Z"/></svg>`;
  }
  return `<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" d="M12.1 20.85 10.8 19.67C5.91 15.24 2.69 12.32 2.69 8.69 2.69 5.75 4.99 3.45 7.93 3.45a5.38 5.38 0 0 1 4.17 1.93 5.38 5.38 0 0 1 4.17-1.93 5.24 5.24 0 0 1 5.24 5.24c0 3.63-3.22 6.55-8.11 10.98Z"/></svg>`;
}

/** Same key as profile wishlist — shared across index, product.php, profile. */
const LUXE_WISHLIST_STORAGE_KEY = "luxe_profile_wishlist_v1";

/**
 * @returns {Array<{id:number,name:string,emoji:string,price:number,orig:number}>|null} null if user never saved wishlist
 */
function luxeWishlistGetItems() {
  try {
    const raw = localStorage.getItem(LUXE_WISHLIST_STORAGE_KEY);
    if (raw === null) return null;
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed.filter(w => w && w.id != null && typeof w.name === "string").map(w => ({
      id: Number(w.id),
      name: String(w.name),
      emoji: typeof w.emoji === "string" ? w.emoji : "📦",
      price: Number(w.price) || 0,
      orig: Number(w.orig != null ? w.orig : w.price) || 0,
    }));
  } catch (_e) {
    return [];
  }
}

function luxeWishlistSetItems(items) {
  try {
    localStorage.setItem(LUXE_WISHLIST_STORAGE_KEY, JSON.stringify(items));
  } catch (_e) {}
}

/**
 * @param {{id:number,name?:string,emoji?:string,price?:number,original?:number,orig?:number}} product
 * @returns {boolean} true if now in wishlist
 */
function luxeWishlistToggleProduct(product) {
  const id = Number(product && product.id);
  if (!id) return false;
  let list = luxeWishlistGetItems();
  if (list === null) list = [];
  const idx = list.findIndex(w => Number(w.id) === id);
  if (idx >= 0) {
    list.splice(idx, 1);
    luxeWishlistSetItems(list);
    return false;
  }
  const orig = product.original != null ? product.original : (product.orig != null ? product.orig : product.price);
  list.push({
    id,
    name: String(product.name || ""),
    emoji: typeof product.emoji === "string" ? product.emoji : "📦",
    price: Number(product.price) || 0,
    orig: Number(orig) || 0,
  });
  luxeWishlistSetItems(list);
  return true;
}

function luxeWishlistIsInList(productId) {
  const id = Number(productId);
  if (!id) return false;
  const list = luxeWishlistGetItems();
  const rows = list === null ? [] : list;
  return rows.some(w => Number(w.id) === id);
}

/** Prefer seller upload path; then category-based stock photos. */
function luxeProductImageUrl(product) {
  const raw = product && typeof product.image_path === "string" ? product.image_path.trim() : "";
  if (raw !== "" && raw.toLowerCase() !== "default") {
    if (/^https?:\/\//i.test(raw)) return raw;
    return raw.replace(/^\/+/, "");
  }
  const category = luxeCategoryImageKey((product && product.category) || "");
  const byCategory = {
    fashion: [
      "https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=900&q=80"
    ],
    electronics: [
      "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1544117519-31a4b719223d?auto=format&fit=crop&w=900&q=80"
    ],
    beauty: [
      "https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1556228578-8c89e6adf883?auto=format&fit=crop&w=900&q=80"
    ],
    home: [
      "https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=900&q=80"
    ],
    gaming: [
      "https://images.unsplash.com/photo-1603481588273-2f908a9a7a1b?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1593305841991-05c297ba4575?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1605901309584-818e25960a8f?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1612287230202-1ff1d85d1bdf?auto=format&fit=crop&w=900&q=80"
    ],
    default: [
      "https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1563013544-824ae1b704d3?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=900&q=80"
    ]
  };
  const list = byCategory[category] || byCategory.default;
  const seed = Number((product && product.id) || 0) || String((product && product.name) || "").length || 1;
  return list[Math.abs(seed) % list.length];
}

/** Map DB/UI category strings to image buckets (avoids always using generic "default" set). */
function luxeCategoryImageKey(cat) {
  const c = String(cat || "").toLowerCase().trim().replace(/&/g, "and");
  const aliases = {
    footwear: "fashion",
    apparel: "fashion",
    clothing: "fashion",
    "womens fashion": "fashion",
    "mens fashion": "fashion",
    watches: "electronics",
    audio: "electronics",
    mobile: "electronics",
    skincare: "beauty",
    makeup: "beauty",
    wellness: "beauty",
    "home and living": "home",
    "home & living": "home",
    homeliving: "home",
    furniture: "home",
    kitchen: "home",
    decor: "home",
    games: "gaming",
    consoles: "gaming",
  };
  return aliases[c] || c;
}

function luxeProductImageSet(product) {
  const raw = product && typeof product.image_path === "string" ? product.image_path.trim() : "";
  if (raw !== "" && raw.toLowerCase() !== "default") {
    return [/^https?:\/\//i.test(raw) ? raw : raw.replace(/^\/+/, "")];
  }
  const category = luxeCategoryImageKey((product && product.category) || "");
  const sets = {
    fashion: [
      "https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1515955656352-a1fa3ffcd111?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1460353581641-37baddab0fa2?auto=format&fit=crop&w=900&q=80"
    ],
    electronics: [
      "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1484704849700-f032a568e944?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1544117519-31a4b719223d?auto=format&fit=crop&w=900&q=80"
    ],
    beauty: [
      "https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1556228578-8c89e6adf883?auto=format&fit=crop&w=900&q=80"
    ],
    home: [
      "https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=900&q=80"
    ],
    gaming: [
      "https://images.unsplash.com/photo-1603481588273-2f908a9a7a1b?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1593305841991-05c297ba4575?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1605901309584-818e25960a8f?auto=format&fit=crop&w=900&q=80",
      "https://images.unsplash.com/photo-1612287230202-1ff1d85d1bdf?auto=format&fit=crop&w=900&q=80"
    ]
  };
  return sets[category] || [
    "https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=900&q=80",
    "https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=900&q=80",
    "https://images.unsplash.com/photo-1563013544-824ae1b704d3?auto=format&fit=crop&w=900&q=80",
    "https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=900&q=80"
  ];
}

async function luxeFetchCart() {
  const api = window.__API_CART__ || "api/cart.php";
  const r = await fetch(api, { credentials: "same-origin" });
  if (!r.ok) return [];
  const data = await r.json();
  return Array.isArray(data) ? data : [];
}

async function luxeSaveCart(items) {
  const api = window.__API_CART__ || "api/cart.php";
  const r = await fetch(api, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
    body: JSON.stringify(items)
  });
  if (!r.ok) return null;
  try {
    const data = await r.json();
    if (data && data.ok && Array.isArray(data.cart)) return data.cart;
  } catch (_e) {}
  return null;
}

function luxeIndexLineFromProduct(p, qty) {
  return {
    id: p.id,
    name: p.name,
    brand: p.brand || "LUXE",
    emoji: p.emoji,
    price: p.price,
    orig: p.original != null ? p.original : p.price,
    qty,
    size: "—",
    color: "Default",
    checked: true
  };
}

/** Unique cart line: same product id + size + color = one line (qty merges). */
function luxeCartLineKey(it) {
  if (!it || typeof it !== "object") return "";
  return String(it.id) + "\x1f" + String(it.size ?? "").trim() + "\x1f" + String(it.color ?? "").trim();
}

function luxeCartLineMatches(a, b) {
  return luxeCartLineKey(a) === luxeCartLineKey(b);
}

function luxeCartLineDomId(it) {
  const k = luxeCartLineKey(it);
  try {
    return "cartln-" + btoa(unescape(encodeURIComponent(k))).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
  } catch (_e) {
    return "cartln-" + String(it.id);
  }
}

// ===================== SHARED: CURSOR =====================
const dot = document.getElementById("cursorDot");
const ring = document.getElementById("cursorRing");
let mx = 0, my = 0, rx = 0, ry = 0;

document.addEventListener("mousemove", e => {
  mx = e.clientX; my = e.clientY;
  if (dot) { dot.style.left = mx + "px"; dot.style.top = my + "px"; }
});
(function animCursor() {
  rx += (mx - rx) * 0.12; ry += (my - ry) * 0.12;
  if (ring) { ring.style.left = rx + "px"; ring.style.top = ry + "px"; }
  requestAnimationFrame(animCursor);
})();

function refreshCursorTargets() {
  document.querySelectorAll("a, button, input, select, label, .product-card, .collection-card, .brand-logo, .tag, .strip-item, .filter-btn, .ctag, .action-btn, .smenu-item, .wishlist-item, .address-card, .order-card, .cart-item, .thumb, .swatch, .size-btn, .review-card, .perk-item, .delivery-card, .ptab, .spec-row, .f-card, .nav-menu-btn, .nav-drawer__close").forEach(el => {
    el.addEventListener("mouseenter", () => ring?.classList.add("hover"));
    el.addEventListener("mouseleave", () => ring?.classList.remove("hover"));
  });
}

// ===================== SHARED: TOAST =====================
const toast = document.getElementById("toast");
let toastTimeout;
function showToast(msg) {
  clearTimeout(toastTimeout);
  if (toast) {
    toast.textContent = msg;
    toast.classList.add("show");
    toastTimeout = setTimeout(() => toast.classList.remove("show"), 3000);
  }
}

// ===================== SHARED: NAVBAR SCROLL =====================
window.addEventListener("scroll", () => {
  document.getElementById("navbar")?.classList.toggle("scrolled", window.scrollY > 40);
});

// ===================== SHARED: MOBILE NAV DRAWER (#navMenuBtn + #navDrawer) =====================
function closeNavDrawer() {
  const drawer = document.getElementById("navDrawer");
  const btn = document.getElementById("navMenuBtn");
  if (!drawer || !drawer.classList.contains("is-open")) return;
  drawer.classList.remove("is-open");
  document.body.classList.remove("nav-drawer-open");
  if (btn) btn.setAttribute("aria-expanded", "false");
  drawer.setAttribute("aria-hidden", "true");
}

function initNavSiteDrawer() {
  const btn = document.getElementById("navMenuBtn");
  const drawer = document.getElementById("navDrawer");
  if (!btn || !drawer) return;

  function openNavDrawer() {
    drawer.classList.add("is-open");
    document.body.classList.add("nav-drawer-open");
    btn.setAttribute("aria-expanded", "true");
    drawer.setAttribute("aria-hidden", "false");
  }

  btn.addEventListener("click", () => {
    if (drawer.classList.contains("is-open")) closeNavDrawer();
    else openNavDrawer();
  });
  drawer.querySelectorAll("[data-nav-drawer-close]").forEach(el => {
    el.addEventListener("click", () => closeNavDrawer());
  });
  drawer.querySelectorAll("a[href]").forEach(a => {
    a.addEventListener("click", () => closeNavDrawer());
  });
  document.addEventListener("keydown", e => {
    if (e.key === "Escape") closeNavDrawer();
  });
  window.addEventListener("resize", () => {
    if (window.matchMedia("(min-width: 769px)").matches) closeNavDrawer();
  });
}

initNavSiteDrawer();

// ===================== SHARED: SCROLL REVEAL =====================
const revealObserver = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add("visible"); revealObserver.unobserve(e.target); } });
}, { threshold: 0.08 });

function observeReveal() {
  document.querySelectorAll(".reveal").forEach(el => revealObserver.observe(el));
}
function observeAll() {
  observeReveal();
  refreshCursorTargets();
}

function initSearchOverlay() {
  const searchBtn = document.getElementById("searchBtn");
  const searchClose = document.getElementById("searchClose");
  const searchOverlay = document.getElementById("searchOverlay");
  const searchInput = document.getElementById("searchInput");
  const liveEl = document.getElementById("searchLiveResults");

  if (!searchBtn || !searchClose || !searchOverlay || !searchInput) return;

  let liveDebounce = null;
  function renderSearchLiveResults() {
    if (!liveEl) return;
    const q = (searchInput.value || "").trim();
    if (!q) {
      liveEl.hidden = true;
      liveEl.innerHTML = "";
      return;
    }
    const catalog = luxeGetSearchCatalog();
    const allMatches = catalog.filter(p => luxeProductMatchesSearchQuery(p, q));
    const matches = allMatches
      .slice()
      .sort((a, b) => luxeSearchLiveRank(a, q) - luxeSearchLiveRank(b) || String(a.name).localeCompare(String(b.name)))
      .slice(0, 8);
    const total = allMatches.length;
    if (matches.length === 0) {
      liveEl.hidden = false;
      liveEl.innerHTML = `<div class="search-live-results__empty" role="status">No products match “${luxeEscapeHtml(q)}”. Try another word.</div>`;
      return;
    }
    const more = total > matches.length ? `<span class="search-live-results__more">+${total - matches.length} more on the shop</span>` : "";
    liveEl.hidden = false;
    liveEl.innerHTML = `
      <div class="search-live-results__head">
        <span class="search-live-results__label">Matching products</span>
        <span class="search-live-results__count">${total} found</span>
      </div>
      <ul class="search-live-results__list" role="list">
        ${matches.map(p => {
    const brand = String(p.brand || "").trim();
    const cat = String(p.category || "").trim();
    const bits = [brand, cat].filter(Boolean);
    const metaPrefix = bits.length ? luxeEscapeHtml(bits.join(" · ")) + " · " : "";
    const img = luxeEscapeHtml(luxeProductImageUrl(p));
    const name = luxeEscapeHtml(p.name);
    return `<li>
            <a class="search-live-hit" href="${luxeProductUrl(p.id)}">
              <span class="search-live-hit__thumb"><img src="${img}" alt="" loading="lazy" decoding="async" /></span>
              <span class="search-live-hit__body">
                <span class="search-live-hit__name">${name}</span>
                <span class="search-live-hit__meta">${metaPrefix}₹${Number(p.price).toLocaleString()}</span>
              </span>
            </a>
          </li>`;
  }).join("")}
      </ul>
      ${more ? `<div class="search-live-results__foot">${more}</div>` : ""}`;
  }

  function scheduleSearchLiveResults() {
    if (!liveEl) return;
    clearTimeout(liveDebounce);
    liveDebounce = setTimeout(renderSearchLiveResults, 100);
  }

  searchBtn.addEventListener("click", () => {
    closeNavDrawer();
    searchOverlay.classList.add("open");
    setTimeout(() => {
      searchInput.focus();
      renderSearchLiveResults();
    }, 320);
  });
  searchClose.addEventListener("click", () => {
    searchOverlay.classList.remove("open");
    if (liveEl) {
      liveEl.hidden = true;
      liveEl.innerHTML = "";
    }
  });
  document.addEventListener("keydown", e => {
    if (e.key === "Escape") {
      searchOverlay.classList.remove("open");
      if (liveEl) {
        liveEl.hidden = true;
        liveEl.innerHTML = "";
      }
    }
  });
  document.querySelectorAll(".tag").forEach(tag => {
    tag.addEventListener("click", () => {
      searchInput.value = tag.textContent.replace(/^.{2}/, "").trim();
      searchInput.focus();
      searchInput.dispatchEvent(new Event("input", { bubbles: true }));
    });
  });

  searchInput.addEventListener("input", scheduleSearchLiveResults);
  searchInput.addEventListener("search", scheduleSearchLiveResults);

  if (liveEl) {
    liveEl.addEventListener("click", e => {
      if (e.target.closest("a.search-live-hit")) searchOverlay.classList.remove("open");
    });
  }

  searchInput.addEventListener("keydown", e => {
    if (e.key !== "Enter") return;
    const q = (searchInput.value || "").trim();
    const trending = document.getElementById("trending");
    if (trending) {
      searchOverlay.classList.remove("open");
      if (liveEl) {
        liveEl.hidden = true;
        liveEl.innerHTML = "";
      }
      trending.scrollIntoView({ behavior: "smooth", block: "start" });
      e.preventDefault();
      return;
    }
    if (q) {
      window.location.href = `index.php?q=${encodeURIComponent(q)}#trending`;
      e.preventDefault();
    }
  });
}

initSearchOverlay();

// ===================== SHARED: THEME TOGGLE =====================
const THEME_KEY = "luxe-theme";

function getPreferredTheme() {
  const saved = localStorage.getItem(THEME_KEY);
  if (saved === "light" || saved === "dark") return saved;
  return "light";
}

function applyTheme(theme) {
  const next = theme === "light" ? "light" : "dark";
  document.documentElement.setAttribute("data-theme", next);
  localStorage.setItem(THEME_KEY, next);
  updateThemeToggleIcon(next);
}

function updateThemeToggleIcon(theme) {
  const btn = document.getElementById("themeToggleBtn");
  if (!btn) return;
  const isLight = theme === "light";
  btn.setAttribute("aria-label", isLight ? "Switch to dark theme" : "Switch to light theme");
  btn.setAttribute("title", isLight ? "Dark Mode" : "Light Mode");
  btn.innerHTML = isLight
    ? `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3c0 .1 0 .21 0 .31A7 7 0 0 0 20.69 12c.1 0 .21 0 .31 0z"/></svg>`
    : `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2"/><path d="M12 21v2"/><path d="m4.22 4.22 1.42 1.42"/><path d="m18.36 18.36 1.42 1.42"/><path d="M1 12h2"/><path d="M21 12h2"/><path d="m4.22 19.78 1.42-1.42"/><path d="m18.36 5.64 1.42-1.42"/></svg>`;
}

function ensureThemeToggleButton() {
  if (document.getElementById("themeToggleBtn")) return;
  const target = document.querySelector(".nav-actions");
  if (!target) return;
  const btn = document.createElement("button");
  btn.className = "icon-btn theme-toggle-btn";
  btn.id = "themeToggleBtn";
  btn.type = "button";
  btn.addEventListener("click", () => {
    const current = document.documentElement.getAttribute("data-theme") === "light" ? "light" : "dark";
    applyTheme(current === "light" ? "dark" : "light");
  });
  target.prepend(btn);
}

function initThemeToggle() {
  applyTheme(getPreferredTheme());
  ensureThemeToggleButton();
  updateThemeToggleIcon(document.documentElement.getAttribute("data-theme") || "light");
}


// ================================================================
//  PAGE: INDEX (Landing Page) — app.js
//  Guard: #loader element exists
// ================================================================

(function initIndexPage() {
  if (!document.getElementById("loader")) return;

  // ---- Products Data (MySQL via PHP or fallback) ----
  const products = luxeGetSearchCatalog();

  let currentCategoryFilter = "all";
  let currentSearchQuery = "";

  function applyUrlSearchQueryParam() {
    const q = new URLSearchParams(window.location.search).get("q");
    if (q == null || q === "") return;
    currentSearchQuery = q;
    const si = document.getElementById("searchInput");
    if (si) si.value = q;
  }
  applyUrlSearchQueryParam();

  let cart = [];
  (async function hydrateCartFromSession() {
    try {
      const s = await luxeFetchCart();
      s.forEach(line => {
        cart.push({
          id: line.id,
          name: line.name,
          category: "all",
          price: line.price,
          original: line.orig != null ? line.orig : line.price,
          emoji: line.emoji,
          badge: "",
          rating: 0,
          reviews: 0,
          qty: line.qty || 1,
          size: line.size != null ? line.size : "—",
          color: line.color != null ? line.color : "Default"
        });
      });
      updateCart();
    } catch (e) { /* static hosting */ }
  })();

  // ---- Loader ----
  window.addEventListener("load", () => {
    setTimeout(() => {
      const loaderEl = document.getElementById("loader");
      loaderEl.classList.add("hidden");
      loaderEl.setAttribute("aria-busy", "false");
      initAnimations();
    }, 1900);
  });

  // ---- Render Products ----
  function renderProducts() {
    const grid = document.getElementById("productsGrid");
    if (!grid) return;
    let filtered = currentCategoryFilter === "all"
      ? products.slice()
      : products.filter(p => p.category === currentCategoryFilter);
    const rawQ = currentSearchQuery.trim().toLowerCase();
    if (rawQ) {
      filtered = filtered.filter(p => luxeProductMatchesSearchQuery(p, currentSearchQuery));
    }
    grid.innerHTML = "";
    if (filtered.length === 0) {
      const hint = rawQ
        ? `Try other keywords, clear the search, or pick a different category.`
        : `Try another category tab.`;
      grid.innerHTML = `<div class="products-grid-empty" role="status"><p>No products match your filters.</p><p class="products-grid-empty-hint">${hint}</p></div>`;
      return;
    }
    filtered.forEach((p, i) => {
      const inWish = luxeWishlistIsInList(p.id);
      const card = document.createElement("div");
      card.className = "product-card reveal";
      card.style.transitionDelay = (i * 0.06) + "s";
      card.innerHTML = `
        <a href="${luxeProductUrl(p.id)}" class="product-card-img-link" style="text-decoration:none;display:block">
          <div class="product-card-img">
            <img class="card-emoji" src="${luxeProductImageUrl(p)}" alt="${p.name}" loading="lazy" decoding="async" />
            <div class="product-card-badge ${p.badge === 'New' ? 'new' : ''}">${p.badge}</div>
            <button class="wishlist-btn${inWish ? " active" : ""}" data-id="${p.id}" aria-label="${inWish ? "Remove from wishlist" : "Add to wishlist"}">
              ${luxeWishlistIconSvg(inWish)}
            </button>
          </div>
        </a>
        <div class="product-card-body">
          <div class="product-card-category">${p.category}</div>
          <a href="${luxeProductUrl(p.id)}" class="product-card-name" style="text-decoration:none;color:inherit;display:block">${p.name}</a>
          <div class="product-card-meta">
            <div class="product-card-price">
              <span class="price-cur">₹${p.price.toLocaleString()}</span>
              <span class="price-orig">₹${p.original.toLocaleString()}</span>
            </div>
            <div class="product-card-rating"><span>★</span> ${p.rating} (${(p.reviews/1000).toFixed(1)}k)</div>
          </div>
          <div class="product-card-actions">
            <a href="${luxeProductUrl(p.id)}" class="view-product-btn">View Details</a>
            <button type="button" class="add-cart-btn" data-id="${p.id}" data-needs-options="${luxeProductRequiresVariantPick(p) ? "1" : "0"}">${luxeProductRequiresVariantPick(p) ? "Choose options" : "Add to Cart"}</button>
          </div>
        </div>
      `;
      grid.appendChild(card);

      card.querySelector(".wishlist-btn").addEventListener("click", function(e) {
        e.preventDefault(); e.stopPropagation();
        const isActive = luxeWishlistToggleProduct(p);
        this.classList.toggle("active", isActive);
        this.innerHTML = luxeWishlistIconSvg(isActive);
        this.setAttribute("aria-label", isActive ? "Remove from wishlist" : "Add to wishlist");
        showToast(isActive ? `❤️ Added to Wishlist` : `Removed from Wishlist`);
      });

      card.querySelector(".add-cart-btn").addEventListener("click", function(e) {
        e.stopPropagation();
        if (luxeProductRequiresVariantPick(p)) {
          showToast("Size / colour chunne ke liye product page khul raha hai…");
          window.location.href = luxeProductUrl(p.id);
          return;
        }
        addToCart(p);
      });
    });

    observeReveal();
    grid.querySelectorAll(".product-card, button").forEach(el => {
      el.addEventListener("mouseenter", () => ring?.classList.add("hover"));
      el.addEventListener("mouseleave", () => ring?.classList.remove("hover"));
    });
  }

  // ---- Filter Tabs ----
  document.querySelectorAll(".filter-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      document.querySelector(".filter-btn.active")?.classList.remove("active");
      btn.classList.add("active");
      currentCategoryFilter = btn.dataset.filter || "all";
      renderProducts();
    });
  });

  const searchInput = document.getElementById("searchInput");
  function syncProductSearchFromInput() {
    currentSearchQuery = searchInput ? String(searchInput.value || "") : "";
    renderProducts();
  }
  if (searchInput) {
    searchInput.addEventListener("input", syncProductSearchFromInput);
    searchInput.addEventListener("search", syncProductSearchFromInput);
  }

  // ---- Cart Sidebar ----
  const cartSidebar = document.getElementById("cartSidebar");
  const cartOverlay = document.getElementById("cartOverlay");
  const cartClose = document.getElementById("cartClose");
  const cartItemsEl = document.getElementById("cartItems");
  const cartCountEl = document.getElementById("cartCount");
  const cartItemCount = document.getElementById("cartItemCount");
  const cartTotal = document.getElementById("cartTotal");

  function openCart() {
    cartSidebar?.classList.add("open");
    cartOverlay?.classList.add("open");
    document.body.style.overflow = "hidden";
  }
  function closeCart() {
    cartSidebar?.classList.remove("open");
    cartOverlay?.classList.remove("open");
    document.body.style.overflow = "";
  }
  // Make closeCart available globally for onclick
  window.closeCart = closeCart;

  cartClose?.addEventListener("click", closeCart);
  cartOverlay?.addEventListener("click", closeCart);
  document.getElementById("shopNowCart")?.addEventListener("click", closeCart);

  async function addToCart(product) {
    const template = luxeIndexLineFromProduct(product, 1);
    const existing = cart.find(i => luxeCartLineMatches(i, template));
    if (existing) { existing.qty++; } else { cart.push({ ...product, ...template, qty: 1 }); }
    updateCart();
    try {
      const sessionCart = await luxeFetchCart();
      const row = cart.find(i => luxeCartLineMatches(i, template));
      const line = luxeIndexLineFromProduct(product, row ? row.qty : 1);
      const idx = sessionCart.findIndex(i => luxeCartLineMatches(i, line));
      if (idx >= 0) sessionCart[idx] = line; else sessionCart.push(line);
      await luxeSaveCart(sessionCart);
    } catch (e) { /* offline / no PHP */ }
    showToast(`🛒 "${product.name}" added to cart!`);
    if (cartCountEl) { cartCountEl.classList.remove("bump"); void cartCountEl.offsetWidth; cartCountEl.classList.add("bump"); }
  }

  window.removeFromCart = function(id) {
    cart = cart.filter(i => i.id !== id);
    updateCart();
  };

  function updateCart() {
    const count = cart.reduce((acc, i) => acc + i.qty, 0);
    if (cartCountEl) cartCountEl.textContent = count;
    if (cartItemCount) cartItemCount.textContent = `(${count})`;
    const total = cart.reduce((acc, i) => acc + i.price * i.qty, 0);
    if (cartTotal) cartTotal.textContent = "₹" + total.toLocaleString();

    if (cart.length === 0) {
      if (cartItemsEl) cartItemsEl.innerHTML = `<div class="cart-empty"><span class="cart-empty-icon">🛒</span><p>Your cart is empty</p><a href="#trending" class="btn-primary" onclick="closeCart()">Start Shopping</a></div>`;
      return;
    }
    if (cartItemsEl) cartItemsEl.innerHTML = cart.map(item => `
      <div class="cart-item">
        <div class="cart-item-emoji">${item.emoji}</div>
        <div class="cart-item-info">
          <div class="cart-item-name">${item.name} ${item.qty > 1 ? `×${item.qty}` : ""}</div>
          <div class="cart-item-price">₹${(item.price * item.qty).toLocaleString()}</div>
        </div>
        <button class="cart-item-remove" onclick="removeFromCart(${item.id})">✕</button>
      </div>
    `).join("");
  }

  // goCheckout — globally available
  window.goCheckout = function() {
    if (cart.length === 0) { showToast("⚠️ Your cart is empty!"); return; }
    closeCart();
    showToast("🔐 Redirecting to secure checkout...");
    setTimeout(() => { window.location.href = LUXE_URLS.cart; }, 1000);
  };

  // ---- Countdown Timer ----
  let endTime = Date.now() + (8 * 3600 + 45 * 60 + 30) * 1000;
  function updateCountdown() {
    const remaining = Math.max(0, endTime - Date.now());
    const h = Math.floor(remaining / 3600000);
    const m = Math.floor((remaining % 3600000) / 60000);
    const s = Math.floor((remaining % 60000) / 1000);
    const hEl = document.getElementById("hours"), mEl = document.getElementById("mins"), sEl = document.getElementById("secs");
    if (hEl) hEl.textContent = String(h).padStart(2, "0");
    if (mEl) mEl.textContent = String(m).padStart(2, "0");
    if (sEl) sEl.textContent = String(s).padStart(2, "0");
  }
  setInterval(updateCountdown, 1000);
  updateCountdown();

  // ---- Newsletter ----
  window.handleSubscribe = function(e) {
    e.preventDefault();
    showToast("🎉 Subscribed successfully! Welcome to LUXE.");
    document.getElementById("nlEmail").value = "";
  };

  // ---- Animations ----
  function initAnimations() {
    document.querySelectorAll(".animate-fade-up, .animate-slide-right").forEach(el => {
      setTimeout(() => el.classList.add("visible"), 100);
    });
    const obs = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add("visible"); obs.unobserve(e.target); } });
    }, { threshold: 0.12 });
    document.querySelectorAll(".section-header, .collection-card, .testimonial-card, .feature-item, .brand-logo, .deals-inner, .newsletter-inner, .reveal").forEach(el => {
      el.classList.add("reveal");
      obs.observe(el);
    });
    renderProducts();
  }

  // ---- Tilt Effect ----
  document.addEventListener("mousemove", e => {
    document.querySelectorAll("[data-tilt]").forEach(card => {
      const rect = card.getBoundingClientRect();
      const cx = rect.left + rect.width / 2;
      const cy = rect.top + rect.height / 2;
      const dx = (e.clientX - cx) / rect.width;
      const dy = (e.clientY - cy) / rect.height;
      card.style.transform = `perspective(800px) rotateY(${dx * 8}deg) rotateX(${-dy * 8}deg) translateY(-6px)`;
    });
  });
  document.querySelectorAll("[data-tilt]").forEach(card => {
    card.addEventListener("mouseleave", () => { card.style.transform = ""; });
  });

  // ---- Parallax Hero ----
  window.addEventListener("scroll", () => {
    const y = window.scrollY;
    document.querySelectorAll(".blob").forEach((b, i) => {
      b.style.transform = `translateY(${y * (0.05 + i * 0.02)}px)`;
    });
  });
})();


// ================================================================
//  PAGE: LOGIN — login.js
//  Guard: #loginForm element exists
// ================================================================
(function initLoginPage() {
  if (!document.getElementById("loginForm")) return;

  // ---- Particles ----
  const particleContainer = document.getElementById("particles");
  if (particleContainer) {
    for (let i = 0; i < 30; i++) {
      const p = document.createElement("div");
      p.className = "particle";
      p.style.left = Math.random() * 100 + "vw";
      p.style.width = p.style.height = (Math.random() * 3 + 2) + "px";
      const dur = Math.random() * 14 + 8;
      p.style.animationDuration = dur + "s";
      p.style.animationDelay = (Math.random() * dur) + "s";
      p.style.opacity = Math.random() * 0.5;
      particleContainer.appendChild(p);
    }
  }

  // ---- Tab Switching ----
  let currentTab = "login";
  window.switchTab = function(tab) {
    currentTab = tab;
    const indicator = document.getElementById("tabIndicator");
    const loginForm = document.getElementById("loginForm");
    const registerForm = document.getElementById("registerForm");
    const tabLogin = document.getElementById("tabLogin");
    const tabRegister = document.getElementById("tabRegister");

    if (tab === "login") {
      loginForm.classList.remove("hidden");
      registerForm.classList.add("hidden");
      tabLogin.classList.add("active");
      tabRegister.classList.remove("active");
      indicator.classList.remove("right");
    } else {
      loginForm.classList.add("hidden");
      registerForm.classList.remove("hidden");
      tabLogin.classList.remove("active");
      tabRegister.classList.add("active");
      indicator.classList.add("right");
      if (typeof window.registerResetFlow === "function") window.registerResetFlow();
    }
    clearErrors();
  };

  if (location.hash === "#register" || location.hash === "#signup") {
    window.switchTab("register");
  }

  // ---- Validation Helpers ----
  function setError(groupId, errId, msg) {
    const group = document.getElementById(groupId);
    const err = document.getElementById(errId);
    if (group) group.classList.add("has-error");
    if (err) err.textContent = msg;
    const inp = group?.querySelector("input");
    if (inp) inp.classList.add("error");
    return false;
  }
  function setSuccess(groupId) {
    const group = document.getElementById(groupId);
    if (group) {
      group.classList.remove("has-error");
      group.classList.add("has-success");
      const inp = group.querySelector("input");
      if (inp) { inp.classList.remove("error"); inp.classList.add("success"); }
    }
  }
  function clearErrors() {
    document.querySelectorAll(".error-msg").forEach(e => e.textContent = "");
    document.querySelectorAll(".input-group").forEach(g => g.classList.remove("has-error", "has-success"));
    document.querySelectorAll("input").forEach(i => i.classList.remove("error", "success"));
  }
  function isValidEmail(email) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim()); }

  // ---- Password Toggle ----
  function setupPasswordToggle(btnId, inputId) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener("click", () => {
      const inp = document.getElementById(inputId);
      const isPass = inp.type === "password";
      inp.type = isPass ? "text" : "password";
      btn.innerHTML = isPass
        ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`
        : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
    });
  }
  setupPasswordToggle("lgTogglePass", "lg-pass");
  setupPasswordToggle("rgTogglePass", "rg-pass");

  const rgCodeInput = document.getElementById("rg-code");
  rgCodeInput?.addEventListener("input", () => {
    rgCodeInput.value = rgCodeInput.value.replace(/\D/g, "").slice(0, 4);
  });

  window.registerResetFlow = function() {
    const step1 = document.getElementById("registerStep1");
    const step2 = document.getElementById("registerVerifyStep");
    if (step1) step1.classList.remove("hidden");
    if (step2) step2.classList.add("hidden");
    if (rgCodeInput) rgCodeInput.value = "";
    const ce = document.getElementById("rg-code-err");
    if (ce) ce.textContent = "";
    document.getElementById("rg-code-group")?.classList.remove("has-error", "has-success");
  };

  window.registerBackToStep1 = function() {
    window.registerResetFlow();
  };

  // ---- Password Strength ----
  const rgPass = document.getElementById("rg-pass");
  if (rgPass) {
    rgPass.addEventListener("input", () => {
      const val = rgPass.value;
      const wrap = document.getElementById("strengthWrap");
      const fill = document.getElementById("strengthFill");
      const label = document.getElementById("strengthLabel");
      if (!val) { wrap.classList.remove("visible"); return; }
      wrap.classList.add("visible");
      let score = 0;
      if (val.length >= 8) score++;
      if (val.length >= 12) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;
      const levels = [
        { w: "20%", color: "#ef4444", text: "Weak", labelColor: "#ef4444" },
        { w: "40%", color: "#f59e0b", text: "Fair", labelColor: "#f59e0b" },
        { w: "60%", color: "#f59e0b", text: "Good", labelColor: "#f59e0b" },
        { w: "80%", color: "#10b981", text: "Strong", labelColor: "#10b981" },
        { w: "100%", color: "#8b5cf6", text: "Excellent", labelColor: "#c4b5fd" },
      ];
      const lvl = levels[Math.min(score - 1, 4)] || levels[0];
      fill.style.width = lvl.w;
      fill.style.background = lvl.color;
      label.textContent = lvl.text;
      label.style.color = lvl.labelColor;
    });
  }

  // ---- Real-time Validation ----
  function liveValidate(inputId, groupId, errId, validator, errMsg) {
    const inp = document.getElementById(inputId);
    if (!inp) return;
    inp.addEventListener("blur", () => {
      if (!inp.value.trim()) { setError(groupId, errId, "This field is required."); }
      else if (!validator(inp.value)) { setError(groupId, errId, errMsg); }
      else {
        const group = document.getElementById(groupId);
        const err = document.getElementById(errId);
        group?.classList.remove("has-error");
        if (err) err.textContent = "";
        inp.classList.remove("error"); inp.classList.add("success");
      }
    });
  }
  liveValidate("lg-email", "lg-email-group", "lg-email-err", isValidEmail, "Enter a valid email address.");
  liveValidate("rg-email", "rg-email-group", "rg-email-err", isValidEmail, "Enter a valid email address.");
  liveValidate("rg-fname", "rg-fname-group", "rg-fname-err", v => v.trim().length >= 2, "Name must be at least 2 characters.");
  liveValidate("rg-lname", "rg-lname-group", "rg-lname-err", v => v.trim().length >= 2, "Name must be at least 2 characters.");

  // ---- Loading Simulation ----
  function setLoading(btnId, loaderId, state) {
    const btn = document.getElementById(btnId);
    const loader = document.getElementById(loaderId);
    if (state) { btn?.classList.add("loading"); loader?.classList.add("visible"); }
    else { btn?.classList.remove("loading"); loader?.classList.remove("visible"); }
  }

  function showSuccess(title, msg, redirectUrl) {
    document.getElementById("loginForm").classList.add("hidden");
    document.getElementById("registerForm").classList.add("hidden");
    document.querySelectorAll(".auth-tabs")[0].style.display = "none";
    const s = document.getElementById("successState");
    s.classList.remove("hidden");
    document.getElementById("successTitle").textContent = title;
    document.getElementById("successMsg").textContent = msg;
    const target = redirectUrl && typeof redirectUrl === "string" ? redirectUrl : LUXE_URLS.home;
    setTimeout(() => { window.location.href = target; }, 2000);
  }

  // ---- Login Handler ----
  window.handleLogin = function(e) {
    e.preventDefault();
    clearErrors();
    const email = document.getElementById("lg-email").value.trim();
    const pass = document.getElementById("lg-pass").value;
    let valid = true;
    if (!email) { setError("lg-email-group", "lg-email-err", "Email is required."); valid = false; }
    else if (!isValidEmail(email)) { setError("lg-email-group", "lg-email-err", "Enter a valid email address."); valid = false; }
    else { setSuccess("lg-email-group"); }
    if (!pass) { setError("lg-pass-group", "lg-pass-err", "Password is required."); valid = false; }
    else if (pass.length < 6) { setError("lg-pass-group", "lg-pass-err", "Password must be at least 6 characters."); valid = false; }
    else { setSuccess("lg-pass-group"); }
    if (!valid) return;
    setLoading("loginSubmitBtn", "loginLoader", true);
    const loginUrl = window.__API_LOGIN__ || "actions/login.php";
    const postRedirect = typeof window.__LOGIN_REDIRECT__ === "string" && window.__LOGIN_REDIRECT__.trim() !== ""
      ? window.__LOGIN_REDIRECT__.trim()
      : "";
    fetch(loginUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ email, password: pass, redirect: postRedirect })
    }).then(r => r.json()).then(data => {
      setLoading("loginSubmitBtn", "loginLoader", false);
      if (data.ok) {
        const next = typeof data.redirect === "string" && data.redirect ? data.redirect : (postRedirect || LUXE_URLS.home);
        showSuccess("Welcome back! 🎉", "You've signed in successfully. Redirecting...", next);
      } else {
        showToast(data.message || "Could not sign in.");
      }
    }).catch(() => {
      setLoading("loginSubmitBtn", "loginLoader", false);
      showToast("Network error — is PHP running?");
    });
  };

  // ---- Register Handler ----
  window.handleRegister = function(e) {
    e.preventDefault();
    clearErrors();
    const fname = document.getElementById("rg-fname").value.trim();
    const lname = document.getElementById("rg-lname").value.trim();
    const email = document.getElementById("rg-email").value.trim();
    const pass = document.getElementById("rg-pass").value;
    const confirm = document.getElementById("rg-confirm").value;
    const agreed = document.getElementById("agreeTerms").checked;
    let valid = true;
    if (!fname || fname.length < 2) { setError("rg-fname-group", "rg-fname-err", "Enter your first name."); valid = false; } else setSuccess("rg-fname-group");
    if (!lname || lname.length < 2) { setError("rg-lname-group", "rg-lname-err", "Enter your last name."); valid = false; } else setSuccess("rg-lname-group");
    if (!email) { setError("rg-email-group", "rg-email-err", "Email is required."); valid = false; }
    else if (!isValidEmail(email)) { setError("rg-email-group", "rg-email-err", "Enter a valid email address."); valid = false; } else setSuccess("rg-email-group");
    if (!pass) { setError("rg-pass-group", "rg-pass-err", "Password is required."); valid = false; }
    else if (pass.length < 8) { setError("rg-pass-group", "rg-pass-err", "Password must be at least 8 characters."); valid = false; } else setSuccess("rg-pass-group");
    if (!confirm) { setError("rg-confirm-group", "rg-confirm-err", "Please confirm your password."); valid = false; }
    else if (pass !== confirm) { setError("rg-confirm-group", "rg-confirm-err", "Passwords do not match."); valid = false; }
    else { setSuccess("rg-confirm-group"); }
    if (!agreed) { showToast("⚠️ Please agree to the Terms & Privacy Policy."); valid = false; }
    if (!valid) return;
    setLoading("regSubmitBtn", "regLoader", true);
    const sendUrl = window.__API_REGISTER_SEND__ || "actions/register-send-code.php";
    fetch(sendUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ first_name: fname, last_name: lname, email, password: pass })
    }).then(async r => {
      const text = await r.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        setLoading("regSubmitBtn", "regLoader", false);
        showToast("Sign-up server error (HTTP " + r.status + "). Check actions/register-send-code.php exists.");
        return null;
      }
      setLoading("regSubmitBtn", "regLoader", false);
      if (!r.ok && data && !data.message) {
        data.message = "Request failed (HTTP " + r.status + ").";
      }
      return data;
    }).then(data => {
      if (!data) return;
      if (data.ok) {
        document.getElementById("registerStep1")?.classList.add("hidden");
        document.getElementById("registerVerifyStep")?.classList.remove("hidden");
        const hint = document.getElementById("verifyEmailHint");
        if (hint) hint.textContent = data.email_hint || email;
        document.getElementById("rg-code")?.focus();
        if (data.dev_code) {
          showToast((data.dev_note || "Verification code") + " — Code: " + data.dev_code);
        } else {
          showToast("📧 Check your inbox for the 4-digit code.");
        }
      } else {
        showToast(data.message || "Could not send verification code.");
      }
    }).catch(() => {
      setLoading("regSubmitBtn", "regLoader", false);
      showToast("Network error — is PHP running?");
    });
  };

  window.handleRegisterVerify = function(e) {
    e.preventDefault();
    const code = (document.getElementById("rg-code")?.value || "").replace(/\D/g, "");
    const group = document.getElementById("rg-code-group");
    const err = document.getElementById("rg-code-err");
    if (code.length !== 4) {
      if (group) group.classList.add("has-error");
      if (err) err.textContent = "Enter the 4-digit code.";
      return;
    }
    if (group) group.classList.remove("has-error");
    if (err) err.textContent = "";
    setLoading("verifySubmitBtn", "verifyLoader", true);
    const verifyUrl = window.__API_REGISTER_VERIFY__ || "actions/register-verify.php";
    fetch(verifyUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ code })
    }).then(async r => {
      const text = await r.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        setLoading("verifySubmitBtn", "verifyLoader", false);
        showToast("Verify server error (HTTP " + r.status + ").");
        return null;
      }
      setLoading("verifySubmitBtn", "verifyLoader", false);
      return data;
    }).then(data => {
      if (!data) return;
      if (data.ok) {
        const fname = document.getElementById("rg-fname")?.value.trim() || "there";
        showSuccess(`Welcome to LUXE, ${fname}! 🎉`, "Your account has been created. Redirecting you to the store...");
      } else {
        if (group) group.classList.add("has-error");
        if (err) err.textContent = data.message || "Invalid code.";
        showToast(data.message || "Could not verify.");
      }
    }).catch(() => {
      setLoading("verifySubmitBtn", "verifyLoader", false);
      showToast("Network error — is PHP running?");
    });
  };

  document.getElementById("resendCodeBtn")?.addEventListener("click", () => {
    const sendUrl = window.__API_REGISTER_SEND__ || "actions/register-send-code.php";
    fetch(sendUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ resend: true })
    }).then(async r => {
      const text = await r.text();
      try {
        return JSON.parse(text);
      } catch {
        return { ok: false, message: "Bad response (HTTP " + r.status + ")" };
      }
    }).then(data => {
      if (data.ok) {
        if (data.dev_code) {
          showToast((data.dev_note || "New code") + " — " + data.dev_code);
        } else {
          showToast("📧 A new code has been sent.");
        }
      } else {
        showToast(data.message || "Could not resend.");
      }
    }).catch(() => showToast("Network error."));
  });

  // ---- Forgot Password ----
  const forgotLink = document.getElementById("forgotLink");
  const forgotOverlay = document.getElementById("forgotOverlay");
  const forgotBack = document.getElementById("forgotBack");
  forgotLink?.addEventListener("click", e => { e.preventDefault(); forgotOverlay.classList.remove("hidden"); });
  forgotBack?.addEventListener("click", () => { forgotOverlay.classList.add("hidden"); });
  window.handleForgot = function(e) {
    e.preventDefault();
    const email = document.getElementById("forgot-email").value.trim();
    if (!isValidEmail(email)) { showToast("⚠️ Please enter a valid email."); return; }
    showToast("📧 Reset link sent! Check your inbox.");
    forgotOverlay.classList.add("hidden");
  };

  // ---- Social Buttons ----
  document.getElementById("googleBtn")?.addEventListener("click", () => showToast("🔗 Connecting to Google..."));
  document.getElementById("phoneBtn")?.addEventListener("click", () => showToast("📱 Redirecting to phone verification..."));

  // ---- Input Focus Glow ----
  document.querySelectorAll(".input-wrap input").forEach(input => {
    input.addEventListener("focus", () => { input.closest(".input-wrap").style.filter = ""; });
  });
})();


// ================================================================
//  PAGE: PRODUCT — product.js
//  Guard: #mainImage element exists
// ================================================================
(function initProductPage() {
  if (!document.getElementById("mainImage")) return;
  const P = window.__PRODUCT_PAGE__ || {};
  /** Rebuild variant stock map from server list — JSON object keys for "size|color" can be unreliable in some environments. */
  if (Array.isArray(P.variantStockEntries) && P.variantStockEntries.length) {
    const rebuilt = Object.create(null);
    for (let i = 0; i < P.variantStockEntries.length; i++) {
      const row = P.variantStockEntries[i];
      if (!row || typeof row !== "object") continue;
      const k = String(row.k ?? "").trim();
      if (!k) continue;
      rebuilt[k] = Math.max(0, Number(row.q ?? 0) || 0);
    }
    P.variantStock = rebuilt;
  }
  /** Uploaded paths (uploads/...) need a leading slash for img src from product.php */
  function luxeResolveProductImgUrl(u) {
    if (!u || typeof u !== "string") return "";
    const t = u.trim();
    if (!t) return "";
    if (/^https?:\/\//i.test(t)) return t;
    if (t.startsWith("/")) return t;
    return "/" + t.replace(/^\/+/, "");
  }
  let productGalleryUrls = [];
  const stockQty = Math.max(0, Number(P.stockQty ?? 0) || 0);
  const isDiscontinuedBadge = String(P.badge || "").toLowerCase() === "discontinued";
  const hasVariantInventory =
    !!P.hasVariantInventory ||
    (Array.isArray(P.variantStockEntries) && P.variantStockEntries.length > 0);
  const hasColorOptions = !!P.hasColorOptions;
  const sellerPreviewOnly = !!P.sellerPreviewOnly;

  function normalizeVariantColorForStock(c) {
    const t = String(c ?? "").trim();
    if (!t || t.toLowerCase() === "default") return "";
    return t.toLowerCase();
  }

  function variantStockKey(size, color) {
    const sz = String(size ?? "").trim().toLowerCase();
    const col = hasColorOptions ? normalizeVariantColorForStock(color) : "";
    return sz + "|" + col;
  }

  function getVariantStock(size, color) {
    if (sellerPreviewOnly) return 0;
    if (isDiscontinuedBadge) return 0;
    if (!hasVariantInventory) return stockQty;
    const map = P.variantStock || {};
    const pick = key => {
      if (!Object.prototype.hasOwnProperty.call(map, key)) return null;
      return Math.max(0, Number(map[key]) || 0);
    };
    const k = variantStockKey(size, color);
    let v = pick(k);
    if (v !== null) return v;
    const sz = String(size ?? "").trim().toLowerCase();
    const blankKey = sz + "|";
    if (hasColorOptions) {
      const prefix = sz + "|";
      let hasColorSpecificRow = false;
      for (const key of Object.keys(map)) {
        if (key.startsWith(prefix) && key.length > prefix.length) {
          hasColorSpecificRow = true;
          break;
        }
      }
      if (!hasColorSpecificRow) {
        v = pick(blankKey);
        if (v !== null) return v;
      }
      return 0;
    } else {
      v = pick(blankKey);
      if (v !== null) return v;
      let best = 0;
      const p = sz + "|";
      for (const key of Object.keys(map)) {
        if (key.startsWith(p)) {
          best = Math.max(best, Number(map[key]) || 0);
        }
      }
      return best;
    }
  }

  function anyVariantInStock() {
    if (sellerPreviewOnly) return false;
    if (!hasVariantInventory) return stockQty > 0;
    const map = P.variantStock || {};
    return Object.keys(map).some(k => (Number(map[k]) || 0) > 0);
  }

  let maxQty = 0;
  if (!isDiscontinuedBadge && !hasVariantInventory) {
    maxQty = stockQty > 0 ? stockQty : 0;
  }

  if (typeof window.__PRODUCT_PAGE__ !== "undefined" && window.__PRODUCT_PAGE__) {
    const Pb = window.__PRODUCT_PAGE__;
    const pb = document.querySelector(".product-brand"); if (pb) pb.textContent = Pb.brand;
    const psn = document.getElementById("productSellerName"); if (psn) psn.textContent = Pb.sellerName || "LUXE Store";
    const pn = document.querySelector(".product-name"); if (pn) pn.textContent = Pb.name;
    const tag = document.querySelector(".product-tagline"); if (tag && Pb.category) tag.textContent = "Category: " + Pb.category + " · Premium quality at LUXE.";
    const pm = document.querySelector(".price-main"); if (pm) pm.textContent = "₹" + Pb.price.toLocaleString("en-IN");
    const ps = document.querySelector(".price-strike"); if (ps) ps.textContent = "₹" + Pb.original.toLocaleString("en-IN");
    const pct = Pb.original > 0 ? Math.round((1 - Pb.price / Pb.original) * 100) : 0;
    const psv = document.querySelector(".price-save"); if (psv) psv.textContent = "Save ₹" + (Pb.original - Pb.price).toLocaleString("en-IN") + " (" + pct + "%)";
    const rc = document.querySelector(".rating-count"); if (rc) rc.innerHTML = Pb.rating + " <span>(" + Pb.reviews.toLocaleString("en-IN") + " reviews)</span>";
    const rawList = (Array.isArray(Pb.images) && Pb.images.length) ? Pb.images : null;
    productGalleryUrls = rawList
      ? rawList.map(luxeResolveProductImgUrl).filter(u => u !== "")
      : luxeProductImageSet(Pb);
    if (productGalleryUrls.length === 0) {
      productGalleryUrls = luxeProductImageSet(Pb);
    }
    const maxGallery = 6;
    productGalleryUrls = productGalleryUrls.slice(0, maxGallery);
    const thumbsRoot = document.getElementById("thumbs");
    let thumbEls = Array.from(document.querySelectorAll("#thumbs .thumb"));
    while (productGalleryUrls.length > thumbEls.length && thumbsRoot && thumbEls[0] && thumbEls.length < maxGallery) {
      const clone = thumbEls[0].cloneNode(true);
      clone.classList.remove("active");
      thumbsRoot.appendChild(clone);
      thumbEls = Array.from(document.querySelectorAll("#thumbs .thumb"));
    }
    thumbEls.forEach((thumb, idx) => {
      if (idx >= productGalleryUrls.length) {
        thumb.style.display = "none";
        thumb.setAttribute("aria-hidden", "true");
        return;
      }
      thumb.style.display = "";
      thumb.removeAttribute("aria-hidden");
      thumb.classList.toggle("active", idx === 0);
      const imgUrl = productGalleryUrls[idx];
      thumb.dataset.image = imgUrl;
      const img = thumb.querySelector("img");
      if (img) {
        img.src = imgUrl;
        img.alt = (Pb.name || "Product") + " — " + (idx + 1);
      }
    });
    const pe = document.getElementById("productEmoji");
    if (pe && productGalleryUrls[0]) pe.src = productGalleryUrls[0];
    const ze = document.getElementById("zoomEmoji");
    if (ze && productGalleryUrls[0]) ze.src = productGalleryUrls[0];
    const saleBadge = document.querySelector(".main-image .badge-sale"); if (saleBadge) saleBadge.textContent = pct + "% OFF";
  }

  const stockBadgeEl = document.querySelector(".main-image .badge-stock");

  function updateMainStockBadge() {
    const inStock = !isDiscontinuedBadge && anyVariantInStock();
    if (!stockBadgeEl) return;
    if (inStock) {
      stockBadgeEl.textContent = "✓ In Stock";
      stockBadgeEl.style.background = "";
      stockBadgeEl.style.color = "";
    } else {
      stockBadgeEl.textContent = isDiscontinuedBadge ? "Discontinued" : "Out of Stock";
      stockBadgeEl.style.background = "rgba(239, 68, 68, 0.18)";
      stockBadgeEl.style.color = "#fecaca";
    }
  }

  const relatedProducts = (typeof window.__RELATED_PRODUCTS__ !== "undefined" && window.__RELATED_PRODUCTS__ && window.__RELATED_PRODUCTS__.length)
    ? window.__RELATED_PRODUCTS__
    : [
      { id: 10, name: "Nike React Infinity", category: "fashion", emoji: "👟", price: 10999, original: 16999, badge: "New" },
      { id: 11, name: "Sony WH-1000XM5", category: "electronics", emoji: "🎧", price: 18999, original: 34990, badge: "Sale" },
      { id: 12, name: "Adidas UltraBoost", category: "fashion", emoji: "👟", price: 12499, original: 19999, badge: "Hot" },
      { id: 13, name: "Apple Watch SE", category: "electronics", emoji: "⌚", price: 19500, original: 29900, badge: "Sale" },
    ];

  // ---- Gallery ----
  let currentImage = productGalleryUrls.length
    ? productGalleryUrls[0]
    : luxeProductImageUrl(window.__PRODUCT_PAGE__ || { category: "fashion", id: 1 });
  window.switchImage = function(btn) {
    document.querySelectorAll(".thumb").forEach(t => t.classList.remove("active"));
    btn.classList.add("active");
    currentImage = btn.dataset.image || currentImage;
    const image = document.getElementById("productEmoji");
    image.src = currentImage;
    document.getElementById("zoomEmoji").src = currentImage;
  };

  // ---- Color / Size / Qty ----
  function getCurrentColorText() {
    const el = document.getElementById("selectedColor");
    return el ? el.textContent.trim() : "Default";
  }

  function readSizeFromButton(btn) {
    if (!btn) return "";
    const attr = btn.getAttribute("data-size");
    if (attr !== null) return attr;
    const lab = btn.querySelector(".size-btn-label");
    if (lab) return lab.textContent.trim();
    return btn.textContent.replace(/\s+/g, " ").trim();
  }

  const initialSizeBtn =
    document.querySelector(".size-btn.active:not(.out)") ||
    document.querySelector(".size-btn.active") ||
    document.querySelector(".size-btn");
  let selectedSize = readSizeFromButton(initialSizeBtn);

  let qty = 1;
  const qtyValEl = document.getElementById("qtyVal");
  const qtyMinusEl = document.getElementById("qtyMinus");
  const qtyPlusEl = document.getElementById("qtyPlus");
  const addCartBtnEl = document.getElementById("addCartBtn");
  const buyNowBtnEl = document.getElementById("buyNowBtn");
  const stickyAddCartBtnEl = document.getElementById("stickyAddCartBtn");
  const cartNavBtnEl = document.getElementById("cartNavBtn");
  const cartCountNavEl = document.getElementById("cartCount");

  function syncQtyUi() {
    if (qtyValEl) qtyValEl.textContent = String(qty);
    if (qtyMinusEl) qtyMinusEl.style.opacity = qty <= 1 ? "0.4" : "1";
    if (qtyPlusEl) qtyPlusEl.style.opacity = qty >= maxQty ? "0.4" : "1";
  }

  function refreshVariantStock() {
    const colorTxt = getCurrentColorText();
    maxQty = getVariantStock(selectedSize, colorTxt);
    const stockWrap = document.querySelector(".qty-available");
    const canPurchaseAny = canPurchaseAnyFn();

    updateMainStockBadge();

    if (!canPurchaseAny) {
      if (stockWrap) stockWrap.textContent = isDiscontinuedBadge ? "Product discontinued" : "Out of stock";
      const se = document.getElementById("productStockQty");
      if (se) se.textContent = "0";
    } else if (maxQty <= 0) {
      if (stockWrap) stockWrap.textContent = "Not available in this size / color";
      const se = document.getElementById("productStockQty");
      if (se) se.textContent = "0";
    } else if (stockWrap) {
      stockWrap.innerHTML = `Only <strong id="productStockQty">${maxQty}</strong> left in stock`;
    }

    const specStockLine = document.getElementById("specStockLine");
    if (specStockLine) {
      if (!canPurchaseAny) {
        specStockLine.textContent = isDiscontinuedBadge ? "Discontinued" : "Out of stock";
      } else if (maxQty <= 0) {
        specStockLine.textContent = hasVariantInventory ? "Not available for this variant" : "Out of stock";
      } else {
        specStockLine.textContent = maxQty + " units";
      }
    }

    const sizeBtnCount = document.querySelectorAll(".size-btn").length;
    const multiSizeNoVariants = !hasVariantInventory && sizeBtnCount > 1;
    document.querySelectorAll(".size-btn").forEach(b => {
      const sz = readSizeFromButton(b);
      const v = getVariantStock(sz, colorTxt);
      const sub = b.querySelector(".size-btn-stock");
      if (hasVariantInventory) {
        b.classList.toggle("out", v <= 0);
        if (sub) sub.textContent = v > 0 ? "(" + v + ")" : "";
      } else if (sub) {
        if (multiSizeNoVariants) {
          sub.textContent = "";
          b.classList.toggle("out", v <= 0);
        } else {
          b.classList.toggle("out", v <= 0);
          sub.textContent = v > 0 ? "(" + v + ")" : "";
        }
      }
    });

    document.querySelectorAll(".swatch").forEach(sw => {
      if (!hasVariantInventory || !hasColorOptions) {
        sw.classList.remove("out");
        return;
      }
      const cSw = sw.dataset.color || "";
      let anySz = false;
      document.querySelectorAll(".size-btn").forEach(b => {
        const sz = readSizeFromButton(b);
        if (getVariantStock(sz, cSw) > 0) anySz = true;
      });
      sw.classList.toggle("out", !anySz);
    });

    if (!canPurchaseAny) {
      qty = 0;
    } else if (maxQty <= 0) {
      qty = 0;
    } else if (qty > maxQty) {
      qty = maxQty;
    } else if (qty < 1) {
      qty = 1;
    }

    syncQtyUi();
    applyPurchaseControls(canPurchaseAny);
  }

  function canPurchaseAnyFn() {
    if (sellerPreviewOnly) return false;
    return !isDiscontinuedBadge && (hasVariantInventory ? anyVariantInStock() : stockQty > 0);
  }

  function applyPurchaseControls(canPurchaseAny) {
    const okCombo = canPurchaseAny && maxQty > 0;
    if (qtyMinusEl) {
      qtyMinusEl.disabled = !okCombo;
      qtyMinusEl.style.opacity = !okCombo || qty <= 1 ? "0.35" : "1";
    }
    if (qtyPlusEl) {
      qtyPlusEl.disabled = !okCombo;
      qtyPlusEl.style.opacity = !okCombo || qty >= maxQty ? "0.35" : "1";
    }
    if (addCartBtnEl) {
      addCartBtnEl.disabled = !okCombo;
      addCartBtnEl.style.opacity = !okCombo ? "0.55" : "1";
    }
    if (buyNowBtnEl) {
      buyNowBtnEl.disabled = !okCombo;
      buyNowBtnEl.style.opacity = !okCombo ? "0.55" : "1";
    }
    if (stickyAddCartBtnEl) {
      stickyAddCartBtnEl.disabled = !okCombo;
      stickyAddCartBtnEl.style.opacity = !okCombo ? "0.55" : "1";
      stickyAddCartBtnEl.textContent = !canPurchaseAny
        ? (isDiscontinuedBadge ? "Discontinued" : "Out of Stock")
        : (!okCombo ? "Not available" : "Add to Cart");
    }
  }

  function applyProductImgBgFromSwatch(btn) {
    const imgBg = document.getElementById("imgBg");
    if (!imgBg || !btn) return;
    let bg = (btn.style && btn.style.background) ? String(btn.style.background).trim() : "";
    if (!bg) {
      const cs = window.getComputedStyle(btn);
      const img = cs.backgroundImage;
      if (img && img !== "none") {
        bg = img;
      } else if (cs.background && cs.background !== "none" && cs.background !== "rgba(0, 0, 0, 0)") {
        bg = cs.background;
      }
    }
    if (!bg) return;
    imgBg.style.background = bg;
  }

  window.selectColor = function(btn) {
    document.querySelectorAll(".swatch").forEach(s => s.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById("selectedColor").textContent = btn.dataset.color;
    applyProductImgBgFromSwatch(btn);
    showToast(`🎨 Color changed to "${btn.dataset.color}"`);
    refreshVariantStock();
  };

  window.selectSize = function(btn) {
    document.querySelectorAll(".size-btn").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    selectedSize = readSizeFromButton(btn);
    const sizeLabel = selectedSize || "Standard";
    showToast(`📏 Size ${sizeLabel} selected`);
    refreshVariantStock();
  };

  async function hydrateProductCartCount() {
    if (cartCountNavEl && typeof window.__CART_COUNT__ === "number") {
      cartCountNavEl.textContent = String(Math.max(0, Number(window.__CART_COUNT__) || 0));
    }
    try {
      const sessionCart = await luxeFetchCart();
      const totalQty = Array.isArray(sessionCart)
        ? sessionCart.reduce((sum, row) => sum + Math.max(0, Number(row?.qty || 0)), 0)
        : 0;
      if (cartCountNavEl) cartCountNavEl.textContent = String(totalQty);
    } catch (_e) {
      if (cartCountNavEl && !cartCountNavEl.textContent) cartCountNavEl.textContent = "0";
    }
  }

  if (cartNavBtnEl) {
    cartNavBtnEl.addEventListener("click", () => {
      window.location.href = LUXE_URLS.cart;
    });
  }

  refreshVariantStock();
  applyProductImgBgFromSwatch(document.querySelector(".swatch.active"));

  window.changeQty = function(delta) {
    if (maxQty <= 0) return;
    qty = Math.max(1, Math.min(maxQty, qty + delta));
    syncQtyUi();
    applyPurchaseControls(canPurchaseAnyFn());
  };

  // ---- Wishlist (shared localStorage with profile + index) ----
  function applyProductWishlistUi(active) {
    const btn = document.getElementById("wishlistBtn");
    const icon = document.getElementById("wishIcon");
    const navIcon = document.getElementById("wishNavIcon");
    if (btn) btn.classList.toggle("active", active);
    if (icon) {
      if (active) {
        icon.setAttribute("fill", "#ec4899");
        icon.setAttribute("stroke", "none");
      } else {
        icon.setAttribute("fill", "none");
        icon.setAttribute("stroke", "currentColor");
      }
    }
    if (navIcon) {
      if (active) {
        navIcon.setAttribute("fill", "#ec4899");
        navIcon.setAttribute("stroke", "none");
      } else {
        navIcon.setAttribute("fill", "none");
        navIcon.setAttribute("stroke", "currentColor");
      }
    }
  }

  let wished = luxeWishlistIsInList(P.id);
  applyProductWishlistUi(wished);

  window.toggleWishlist = function() {
    const Pb = window.__PRODUCT_PAGE__ || P;
    wished = luxeWishlistToggleProduct({
      id: Pb.id,
      name: Pb.name,
      emoji: Pb.emoji,
      price: Pb.price,
      original: Pb.original,
    });
    applyProductWishlistUi(wished);
    const btn = document.getElementById("wishlistBtn");
    if (wished) showToast("❤️ Added to Wishlist!");
    else showToast("Removed from Wishlist");
    if (btn) {
      btn.style.transform = "scale(1.3)";
      setTimeout(() => { btn.style.transform = ""; }, 300);
    }
  };

  // ---- Add to Cart / Buy Now ----
  window.addToCart = function() {
    if (maxQty <= 0) {
      if (isDiscontinuedBadge) {
        showToast("❌ This product is discontinued.");
      } else if (anyVariantInStock()) {
        showToast("❌ Not available in this size / color.");
      } else {
        showToast("❌ This product is out of stock.");
      }
      return;
    }
    const P = window.__PRODUCT_PAGE__ || { id: 1, name: "AirMax Pro 2026", price: 8999, original: 14500, emoji: "👟", brand: "Nike × LUXE Exclusive", sellerName: "LUXE Store" };
    const colorEl = document.getElementById("selectedColor");
    const colorTxt = colorEl ? colorEl.textContent.trim() : "Default";
    const btn = document.getElementById("addCartBtn");
    const cartBtn = document.getElementById("cartNavBtn");
    const rawSize = selectedSize == null ? "" : String(selectedSize).trim();
    const sizeLine = rawSize === "" ? "Standard" : rawSize;
    const line = {
      id: P.id,
      name: P.name,
      brand: P.brand,
      emoji: P.emoji,
      price: P.price,
      orig: P.original,
      qty,
      size: sizeLine,
      color: colorTxt,
      checked: true
    };
    luxeFetchCart().then(sessionCart => {
      const idx = sessionCart.findIndex(i => luxeCartLineMatches(i, line));
      const existingQty = idx >= 0 ? Math.max(0, Number(sessionCart[idx].qty) || 0) : 0;
      const cap = Math.max(0, maxQty - existingQty);
      const addQty = Math.min(qty, cap);
      if (addQty <= 0) {
        showToast("⚠️ You already have the maximum available for this size / color.");
        return Promise.reject(new Error("cap"));
      }
      if (idx >= 0) {
        sessionCart[idx].qty = existingQty + addQty;
      } else {
        sessionCart.push({ ...line, qty: addQty });
      }
      return luxeSaveCart(sessionCart).then(saved => ({ saved, addQty, sizeLine }));
    }).then(result => {
      if (!result || !result.saved) return;
      const { addQty: added, sizeLine: szLine } = result;
      if (btn) {
        btn.innerHTML = `<span>✓ Added!</span>`;
        btn.style.background = "linear-gradient(135deg, #10b981, #059669)";
        setTimeout(() => {
          btn.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> Add to Cart`;
          btn.style.background = "";
        }, 1500);
      }
      cartBtn?.classList.remove("bounce"); void cartBtn?.offsetWidth; cartBtn?.classList.add("bounce");
      const countEl = document.getElementById("cartCount");
      if (countEl) countEl.textContent = parseInt(countEl.textContent || "0", 10) + added;
      if (added < qty) {
        showToast(`⚠️ Only ${maxQty} in stock for this variant. Added ${added} to cart.`);
      } else {
        showToast(`🛒 ${added}x ${P.name} (${szLine}) added to cart!`);
      }
      hydrateProductCartCount();
    }).catch(() => {});
  };

  window.buyNow = function() {
    if (maxQty <= 0) {
      if (isDiscontinuedBadge) {
        showToast("❌ This product is discontinued.");
      } else if (anyVariantInStock()) {
        showToast("❌ Not available in this size / color.");
      } else {
        showToast("❌ This product is out of stock.");
      }
      return;
    }
    const P = window.__PRODUCT_PAGE__ || { id: 1, name: "AirMax Pro 2026", price: 8999, original: 14500, emoji: "👟", brand: "Nike × LUXE Exclusive", sellerName: "LUXE Store" };
    const colorEl = document.getElementById("selectedColor");
    const colorTxt = colorEl ? colorEl.textContent.trim() : "Default";
    const rawSizeBn = selectedSize == null ? "" : String(selectedSize).trim();
    const sizeLine = rawSizeBn === "" ? "Standard" : rawSizeBn;
    const line = {
      id: P.id,
      name: P.name,
      brand: P.brand,
      emoji: P.emoji,
      price: P.price,
      orig: P.original,
      qty,
      size: sizeLine,
      color: colorTxt,
      checked: true
    };
    const checkoutHref = (typeof LUXE_URLS.checkout === "string" && LUXE_URLS.checkout) ? LUXE_URLS.checkout : "checkout.php";
    const loginBase = LUXE_URLS.login || "login.php";
    const loginSep = loginBase.includes("?") ? "&" : "?";

    luxeFetchCart()
      .then(sessionCart => {
        const idx = sessionCart.findIndex(i => luxeCartLineMatches(i, line));
        const existingQty = idx >= 0 ? Math.max(0, Number(sessionCart[idx].qty) || 0) : 0;
        const cap = Math.max(0, maxQty - existingQty);
        const addQty = Math.min(qty, cap);
        if (addQty <= 0) {
          showToast("⚠️ You already have the maximum available for this size / color.");
          return Promise.reject(new Error("cap"));
        }
        if (idx >= 0) {
          sessionCart[idx].qty = existingQty + addQty;
        } else {
          sessionCart.push({ ...line, qty: addQty });
        }
        return luxeSaveCart(sessionCart);
      })
      .then(() => hydrateProductCartCount())
      .then(() => {
        if (window.__AUTH_USER_ID__) {
          showToast("⚡ Redirecting to checkout...");
          window.location.href = checkoutHref;
          return;
        }
        showToast("🔐 Please sign in to continue to checkout.");
        setTimeout(() => {
          window.location.href = loginBase + loginSep + "redirect=" + encodeURIComponent("checkout.php");
        }, 800);
      })
      .catch(() => {});
  };

  // ---- Product Tabs ----
  window.switchProductTab = function(btn) {
    document.querySelectorAll(".ptab").forEach(t => t.classList.remove("active"));
    document.querySelectorAll(".tab-panel").forEach(p => { p.classList.add("hidden"); p.classList.remove("active"); });
    btn.classList.add("active");
    const panel = document.getElementById("tab-" + btn.dataset.tab);
    panel.classList.remove("hidden"); panel.classList.add("active");
    if (btn.dataset.tab === "reviews") {
      setTimeout(() => {
        document.querySelectorAll(".rbar-fill").forEach(b => { const w = b.style.width; b.style.width = "0"; setTimeout(() => b.style.width = w, 50); });
      }, 100);
    }
  };

  if (window.location.hash === "#tab-reviews") {
    const reviewsTabBtn = document.querySelector('.ptab[data-tab="reviews"]');
    if (reviewsTabBtn) {
      window.switchProductTab(reviewsTabBtn);
      setTimeout(() => {
        document.getElementById("tab-reviews")?.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 80);
    }
  }

  // ---- Related Products ----
  function renderRelated() {
    const grid = document.getElementById("relatedGrid");
    if (!grid) return;
    grid.innerHTML = relatedProducts.map((p, i) => `
      <div class="product-card reveal" style="transition-delay:${i * 0.08}s">
        <a href="${luxeProductUrl(p.id)}" class="product-card-img-link" style="text-decoration:none;display:block"><div class="product-card-img"><img class="card-emoji" src="${luxeProductImageUrl(p)}" alt="${p.name}" loading="lazy" decoding="async" /><div class="product-card-badge">${p.badge || ""}</div></div></a>
        <div class="product-card-body">
          <div class="product-card-name"><a href="${luxeProductUrl(p.id)}" style="text-decoration:none;color:inherit">${p.name}</a></div>
          <div class="product-card-price"><span class="price-cur">₹${p.price.toLocaleString()}</span><span class="price-orig">₹${p.original.toLocaleString()}</span></div>
          <div class="rel-actions">
            <a class="rel-view-btn" href="${luxeProductUrl(p.id)}">View</a>
            <button class="rel-add-btn" onclick="showToast('🛒 ${p.name} added to cart!')">Add to Cart 🛒</button>
          </div>
        </div>
      </div>
    `).join("");
  }

  // ---- Review Helpful ----
  window.markHelpful = function(btn) {
    if (btn.classList.contains("voted")) return;
    btn.classList.add("voted");
    const match = btn.textContent.match(/\((\d+)\)/);
    if (match) btn.textContent = `👍 Helpful (${parseInt(match[1]) + 1})`;
    showToast("👍 Marked as helpful!");
  };

  // ---- Size Guide Modal ----
  document.querySelector(".size-guide-link")?.addEventListener("click", e => { e.preventDefault(); document.getElementById("sizeModal").classList.remove("hidden"); });
  window.closeSizeGuide = function() { document.getElementById("sizeModal").classList.add("hidden"); };
  document.getElementById("sizeModal")?.addEventListener("click", e => { if (e.target === e.currentTarget) closeSizeGuide(); });

  // ---- Offer Countdown (persist end time across refresh; reset when duration changes or timer expired) ----
  const offerTimerEl = document.getElementById("offerTimer");
  const offerDurationSecondsRaw = offerTimerEl ? Number(offerTimerEl.dataset.offerSeconds || "0") : 0;
  const offerDurationSeconds = Number.isFinite(offerDurationSecondsRaw) && offerDurationSecondsRaw > 0
    ? Math.floor(offerDurationSecondsRaw)
    : (2 * 3600 + 14 * 60 + 38);
  const offerProductId = Number((window.__PRODUCT_PAGE__ && window.__PRODUCT_PAGE__.id) || 0) || 0;
  const offerStorageKey = offerProductId > 0
    ? "luxe_offer_end_" + offerProductId + "_" + offerDurationSeconds
    : "luxe_offer_end_" + offerDurationSeconds;

  let offerEnd = 0;
  try {
    const stored = localStorage.getItem(offerStorageKey);
    if (stored) {
      const parsed = parseInt(stored, 10);
      if (Number.isFinite(parsed) && parsed > Date.now()) {
        offerEnd = parsed;
      } else if (Number.isFinite(parsed)) {
        localStorage.removeItem(offerStorageKey);
      }
    }
  } catch (_e) {
    /* private mode / blocked */
  }
  if (!offerEnd) {
    offerEnd = Date.now() + offerDurationSeconds * 1000;
    try {
      localStorage.setItem(offerStorageKey, String(offerEnd));
    } catch (_e) {
      /* ignore */
    }
  }

  function updateOfferTimer() {
    const diff = Math.max(0, offerEnd - Date.now());
    if (diff <= 0) {
      try {
        localStorage.removeItem(offerStorageKey);
      } catch (_e) {
        /* ignore */
      }
    }
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    if (offerTimerEl) offerTimerEl.textContent = `${String(h).padStart(2,"0")}:${String(m).padStart(2,"0")}:${String(s).padStart(2,"0")}`;
  }
  if (offerTimerEl) {
    setInterval(updateOfferTimer, 1000);
    updateOfferTimer();
  }

  // ---- Sticky CTA ----
  window.addEventListener("scroll", () => {
    const productSection = document.querySelector(".product-section");
    if (productSection) {
      const bottom = productSection.getBoundingClientRect().bottom;
      const stickyEl = document.getElementById("stickyCta");
      if (stickyEl) stickyEl.style.display = bottom < 0 ? "flex" : "none";
    }
  });

  // ---- Init ----
  document.addEventListener("DOMContentLoaded", () => {
    renderRelated();
    observeReveal();
    document.querySelectorAll(".reveal, .delivery-card, .offer-pill, .desc-stat, .review-card, .product-card").forEach(el => {
      el.classList.add("reveal"); revealObserver.observe(el);
    });
    refreshCursorTargets();
    hydrateProductCartCount();
    if (typeof changeQty === "function") changeQty(0);

    const reviewWriteToggle = document.getElementById("reviewWriteToggle");
    const reviewFormPanel = document.getElementById("reviewFormPanel");
    if (reviewWriteToggle && reviewFormPanel) {
      const labelEl = reviewWriteToggle.querySelector(".review-write-toggle__label");
      reviewWriteToggle.addEventListener("click", () => {
        const open = !reviewFormPanel.classList.contains("is-open");
        reviewFormPanel.classList.toggle("is-open", open);
        reviewWriteToggle.setAttribute("aria-expanded", open ? "true" : "false");
        if (labelEl) labelEl.textContent = open ? "Close" : "Write a review";
        if (open) {
          requestAnimationFrame(() => {
            reviewFormPanel.scrollIntoView({ behavior: "smooth", block: "nearest" });
            document.getElementById("review_text")?.focus();
          });
        }
      });
    }
  });
})();


// ================================================================
//  PAGE: CART — cart.js
//  Guard: #itemsContainer element exists
// ================================================================
(function initCartPage() {
  if (!document.getElementById("itemsContainer")) return;

  const COUPONS =
    typeof window.__COUPON_DEFS__ === "object" && window.__COUPON_DEFS__ && !Array.isArray(window.__COUPON_DEFS__)
      ? window.__COUPON_DEFS__
      : {};

  function cartApplicableSubtotalForCoupon(items, sellerScope) {
    const checked = items.filter(i => i.checked);
    if (sellerScope == null || sellerScope === "" || !Number.isFinite(Number(sellerScope))) {
      return checked.reduce((a, i) => a + i.price * i.qty, 0);
    }
    const sid = Number(sellerScope);
    return checked.reduce((a, i) => {
      const ls = i.seller_id != null ? Number(i.seller_id) : 0;
      return ls === sid ? a + i.price * i.qty : a;
    }, 0);
  }

  const DEFAULT_CART_ITEMS = [
    { id: 1, name: "AirMax Pro 2026", brand: "Nike × LUXE", emoji: "👟", price: 8999, orig: 14500, qty: 1, max_qty: 99, size: "UK 8", color: "Cosmic Purple", checked: true },
    { id: 2, name: "Sony WH-1000XM5", brand: "Sony", emoji: "🎧", price: 18999, orig: 34990, qty: 1, max_qty: 99, size: "—", color: "Black", checked: true },
    { id: 3, name: "Apple Watch SE", brand: "Apple", emoji: "⌚", price: 19500, orig: 29900, qty: 2, max_qty: 99, size: "44mm", color: "Midnight", checked: true },
  ];
  let cartItems;
  if (typeof window.__CART_ITEMS__ !== "undefined") {
    cartItems = window.__CART_ITEMS__.length ? JSON.parse(JSON.stringify(window.__CART_ITEMS__)) : [];
  } else {
    cartItems = DEFAULT_CART_ITEMS;
  }

  let savedItems = [
    { id: 4, name: "Linen Co-ord Set", emoji: "👗", price: 3299 },
    { id: 5, name: "Retinol Serum Kit", emoji: "🧴", price: 1899 },
  ];

  let appliedCoupon = null;
  let deliveryFetchSeq = 0;

  function cartPlatformFeeAmount() {
    const v = window.__PLATFORM_FEE_RUPEES__;
    if (typeof v === "number" && Number.isFinite(v)) return Math.max(0, v);
    const n = parseInt(String(v ?? ""), 10);
    return Number.isNaN(n) ? 3 : Math.max(0, n);
  }

  function cartItemMaxQty(item) {
    const m = item && typeof item.max_qty === "number" && item.max_qty > 0 ? item.max_qty : 0;
    return m > 0 ? m : 999;
  }

  function mergeCartFromServer(normalized) {
    if (!normalized || !Array.isArray(normalized)) return false;
    const byKey = new Map(normalized.map(x => [luxeCartLineKey(x), x]));
    let changed = false;
    cartItems.forEach(it => {
      const s = byKey.get(luxeCartLineKey(it));
      if (!s) return;
      if (typeof s.max_qty === "number" && it.max_qty !== s.max_qty) {
        it.max_qty = s.max_qty;
        changed = true;
      }
      if (typeof s.qty === "number" && it.qty !== s.qty) {
        it.qty = s.qty;
        changed = true;
      }
      if (typeof s.brand === "string" && s.brand !== "" && (!it.brand || String(it.brand) === "undefined")) {
        it.brand = s.brand;
        changed = true;
      }
    });
    return changed;
  }

  /** @returns {"standard"|"express"|"same_day"} */
  function getDeliverySpeedMode() {
    const delChoice = document.querySelector('input[name="delivery"]:checked');
    if (!delChoice) return "standard";
    const v = String(delChoice.value);
    if (v === "standard" || v === "express" || v === "same_day") return v;
    const n = parseInt(v, 10);
    if (n === 99) return "express";
    if (n === 199) return "same_day";
    return "standard";
  }
  window.getDeliverySpeedMode = getDeliverySpeedMode;

  function cartSpeedOptionKey(speedExtraOrMode) {
    if (typeof speedExtraOrMode === "string") {
      if (speedExtraOrMode === "express") return "express";
      if (speedExtraOrMode === "same_day") return "same_day";
      return "standard";
    }
    if (speedExtraOrMode === 99) return "express";
    if (speedExtraOrMode === 199) return "same_day";
    return "standard";
  }

  function fallbackEtaOption(key) {
    if (key === "express") return { min: 1, max: 3 };
    if (key === "same_day") return { min: 0, max: 1 };
    return { min: 3, max: 7 };
  }

  function formatCartEtaDateRange(handling, etaMin, etaMax) {
    const h = Math.max(0, +handling || 0);
    const a = Math.max(0, +etaMin || 0);
    const b = Math.max(a, +etaMax || 0);
    const start = new Date();
    start.setHours(12, 0, 0, 0);
    start.setDate(start.getDate() + h + a);
    const end = new Date();
    end.setHours(12, 0, 0, 0);
    end.setDate(end.getDate() + h + b);
    const fmt = d => d.toLocaleDateString("en-IN", { weekday: "short", month: "short", day: "numeric" });
    if (start.getTime() === end.getTime()) return fmt(start);
    return fmt(start) + " – " + fmt(end);
  }

  function cartDeliveryEtaInnerHtml(productId, speedExtraOrMode) {
    const metaRoot = typeof window.__CART_DELIVERY_META__ === "object" && window.__CART_DELIVERY_META__ ? window.__CART_DELIVERY_META__ : {};
    const meta = metaRoot[productId] ?? metaRoot[String(productId)];
    const key = cartSpeedOptionKey(speedExtraOrMode);
    const handling = meta && meta.handling != null ? Number(meta.handling) : 2;
    let opt = fallbackEtaOption(key);
    if (meta && meta.options && meta.options[key]) {
      const o = meta.options[key];
      if (o && (o.min != null || o.max != null)) {
        const mn = Math.max(0, Number(o.min != null ? o.min : 0));
        const mx = Math.max(mn, Number(o.max != null ? o.max : mn));
        opt = { min: mn, max: mx };
      }
    }
    const range = formatCartEtaDateRange(handling, opt.min, opt.max);
    const label = key === "express" ? "Express" : key === "same_day" ? "Same Day" : "Standard";
    return `<span class="del-date-label">${label}</span> · <span class="del-date-range">${range}</span>`;
  }

  function refreshCartDeliveryEtaDom() {
    const mode = getDeliverySpeedMode();
    cartItems.forEach(item => {
      const wrap = document.querySelector(`#${luxeCartLineDomId(item)} .delivery-info`);
      if (!wrap) return;
      wrap.innerHTML = "🚚 Expected delivery: " + cartDeliveryEtaInnerHtml(item.id, mode);
    });
  }

  function renderCart() {
    const container = document.getElementById("itemsContainer");
    if (!container) return;
    if (cartItems.length === 0) {
      document.getElementById("cartLayout").classList.add("hidden");
      document.getElementById("emptyCart").classList.remove("hidden");
      return;
    }
    document.getElementById("cartLayout").classList.remove("hidden");
    document.getElementById("emptyCart").classList.add("hidden");
    const speedMode0 = getDeliverySpeedMode();
    container.innerHTML = cartItems.map(item => {
      const maxQ = cartItemMaxQty(item);
      const atMax = item.qty >= maxQ;
      const atMin = item.qty <= 1;
      const brandShow = item.brand != null && String(item.brand) !== "undefined" ? item.brand : "";
      const lineEnc = encodeURIComponent(luxeCartLineKey(item)).replace(/'/g, "%27");
      const rowId = luxeCartLineDomId(item);
      return `
      <div class="cart-item reveal" id="${rowId}">
        <div class="item-check"><label class="checkbox-label"><input type="checkbox" ${item.checked ? "checked" : ""} onchange="toggleItem('${lineEnc}', this.checked)" /><span class="checkmark"></span></label></div>
        <a href="${luxeProductUrl(item.id)}" class="item-image">${item.emoji}</a>
        <div class="item-details">
          ${brandShow ? `<div class="item-brand">${brandShow}</div>` : ""}
          <div class="item-name"><a href="${luxeProductUrl(item.id)}">${item.name}</a></div>
          <div class="item-variants"><span class="var-tag">Size: ${item.size}</span><span class="var-tag">Color: ${item.color}</span></div>
          <div class="item-price-row"><span class="item-price">₹${item.price.toLocaleString()}</span><span class="item-orig">₹${item.orig.toLocaleString()}</span><span class="item-discount">${Math.round((1 - item.price/item.orig)*100)}% off</span></div>
          <div class="item-actions">
            <div class="qty-ctrl"><button type="button" class="qty-btn" ${atMin ? "disabled" : ""} onclick="changeQty('${lineEnc}', -1)">−</button><span class="qty-num">${item.qty}</span><button type="button" class="qty-btn" ${atMax ? "disabled" : ""} onclick="changeQty('${lineEnc}', 1)">+</button></div>
            <div class="action-divider"></div><button class="action-link" onclick="saveForLater('${lineEnc}')">🔖 Save for Later</button>
            <div class="action-divider"></div><button class="action-link remove" onclick="removeItem('${lineEnc}')">🗑️ Remove</button>
          </div>
          <div class="delivery-info">🚚 Expected delivery: ${cartDeliveryEtaInnerHtml(item.id, speedMode0)}</div>
        </div>
      </div>
    `;
    }).join("");
    void updateTotal();
    renderSaved();
    document.querySelector("#cartBadge").textContent = `${cartItems.length} item${cartItems.length !== 1 ? "s" : ""}`;
    document.querySelector("#itemCount").textContent = cartItems.filter(i => i.checked).length;
    observeAll();
    luxeSaveCart(cartItems).then(normalized => {
      if (mergeCartFromServer(normalized)) renderCart();
    }).catch(() => {});
  }

  function renderSaved() {
    const container = document.getElementById("savedContainer");
    document.getElementById("savedCount").textContent = `(${savedItems.length})`;
    if (savedItems.length === 0) { container.innerHTML = ""; return; }
    container.innerHTML = savedItems.map(s => `
      <div class="saved-item"><span class="saved-emoji">${s.emoji}</span><div class="saved-info"><strong>${s.name}</strong><span>₹${s.price.toLocaleString()}</span><button class="move-to-cart" onclick="moveToCart(${s.id})">+ Move to Cart</button></div></div>
    `).join("");
    refreshCursorTargets();
  }

  window.toggleItem = function(lineEnc, checked) {
    const k = decodeURIComponent(lineEnc);
    const item = cartItems.find(i => luxeCartLineKey(i) === k);
    if (item) {
      item.checked = checked;
      void updateTotal();
      luxeSaveCart(cartItems).catch(() => {});
    }
  };
  window.toggleAll = function(el) { cartItems.forEach(i => i.checked = el.checked); renderCart(); };
  window.changeQty = function(lineEnc, delta) {
    const k = decodeURIComponent(lineEnc);
    const item = cartItems.find(i => luxeCartLineKey(i) === k);
    if (!item) return;
    const maxQ = cartItemMaxQty(item);
    const next = item.qty + delta;
    if (delta > 0 && next > maxQ) {
      showToast(`⚠️ Only ${maxQ} in stock for this size / color.`);
      return;
    }
    item.qty = Math.max(1, Math.min(maxQ, next));
    renderCart();
    showToast(`📦 Quantity updated to ${item.qty}`);
  };
  window.removeItem = function(lineEnc) {
    const k = decodeURIComponent(lineEnc);
    const item = cartItems.find(i => luxeCartLineKey(i) === k);
    const el = item ? document.getElementById(luxeCartLineDomId(item)) : null;
    el?.classList.add("removing");
    setTimeout(() => {
      cartItems = cartItems.filter(i => luxeCartLineKey(i) !== k);
      renderCart();
      showToast("🗑️ Item removed from cart");
    }, 300);
  };
  window.saveForLater = function(lineEnc) {
    const k = decodeURIComponent(lineEnc);
    const item = cartItems.find(i => luxeCartLineKey(i) === k);
    if (!item) return;
    savedItems.push({ id: item.id, name: item.name, emoji: item.emoji, price: item.price });
    const el = document.getElementById(luxeCartLineDomId(item));
    el?.classList.add("removing");
    setTimeout(() => {
      cartItems = cartItems.filter(i => luxeCartLineKey(i) !== k);
      renderCart();
      showToast("🔖 Saved for later!");
    }, 300);
  };
  window.moveToCart = function(id) { const s = savedItems.find(i => i.id === id); if (!s) return; cartItems.push({ id: s.id, name: s.name, brand: "LUXE", emoji: s.emoji, price: s.price, orig: Math.round(s.price * 1.3), qty: 1, max_qty: 999, size: "—", color: "Default", checked: true }); savedItems = savedItems.filter(i => i.id !== id); renderCart(); showToast("✅ Moved to cart!"); };
  window.removeSelected = function() { const selected = cartItems.filter(i => i.checked).length; cartItems = cartItems.filter(i => !i.checked); renderCart(); showToast(`🗑️ ${selected} item(s) removed`); };

  window.fillCoupon = function(code) { document.getElementById("couponInput").value = code; applyCoupon(); };
  window.applyCoupon = function() {
    const code = document.getElementById("couponInput").value.trim().toUpperCase();
    const msg = document.getElementById("couponMsg");
    if (!code) {
      msg.textContent = "";
      appliedCoupon = null;
      try { sessionStorage.removeItem("luxeCheckoutCoupon"); } catch (_e) {}
      void updateTotal();
      return;
    }
    const coupon = COUPONS[code];
    if (!coupon) {
      msg.className = "coupon-msg error";
      msg.textContent = "❌ Invalid coupon code";
      appliedCoupon = null;
      try { sessionStorage.removeItem("luxeCheckoutCoupon"); } catch (_e) {}
    } else {
      const sellerScope = coupon.seller_id != null && coupon.seller_id !== "" ? Number(coupon.seller_id) : null;
      const minOrder = coupon.min_order != null ? Number(coupon.min_order) : 0;
      const base = cartApplicableSubtotalForCoupon(cartItems, sellerScope);
      if (sellerScope != null && Number.isFinite(sellerScope) && base <= 0) {
        msg.className = "coupon-msg error";
        msg.textContent = "❌ Add items from this seller's store to use this coupon";
        appliedCoupon = null;
        try { sessionStorage.removeItem("luxeCheckoutCoupon"); } catch (_e) {}
      } else if (base < minOrder) {
        msg.className = "coupon-msg error";
        msg.textContent = `❌ Minimum ₹${minOrder.toLocaleString("en-IN")} from eligible items for this coupon`;
        appliedCoupon = null;
        try { sessionStorage.removeItem("luxeCheckoutCoupon"); } catch (_e) {}
      } else {
        appliedCoupon = { ...coupon, code };
        msg.className = "coupon-msg success";
        msg.textContent = `✅ "${code}" applied — ${coupon.desc}`;
        showToast(`🏷️ Coupon "${code}" applied!`);
        try { sessionStorage.setItem("luxeCheckoutCoupon", code); } catch (_e) {}
      }
    }
    void updateTotal();
  };

  async function updateTotal() {
    const seq = ++deliveryFetchSeq;
    const checked = cartItems.filter(i => i.checked);
    const subtotal = checked.reduce((a, i) => a + i.price * i.qty, 0);
    const origTotal = checked.reduce((a, i) => a + i.orig * i.qty, 0);
    const speedMode = getDeliverySpeedMode();
    const speedName = speedMode === "express" ? "Express" : speedMode === "same_day" ? "Same Day" : "Standard";
    const speedTagClass = speedMode === "express" ? "express" : speedMode === "same_day" ? "same-day" : "standard";

    let sellerDelivery = 0;
    let expressFee = 0;
    let sameDayFee = 0;
    if (checked.length > 0) {
      try {
        const api = (typeof window.__API_CART_DELIVERY__ === "string" && window.__API_CART_DELIVERY__) ? window.__API_CART_DELIVERY__ : "api/cart-delivery.php";
        const r = await fetch(api, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ items: checked.map(i => ({ id: i.id, qty: i.qty })) })
        });
        const data = await r.json();
        if (seq !== deliveryFetchSeq) return;
        sellerDelivery = Number(data.delivery) || 0;
        expressFee = Number(data.express_fee) || 0;
        sameDayFee = Number(data.same_day_fee) || 0;
      } catch (_e) {
        if (seq !== deliveryFetchSeq) return;
        sellerDelivery = 0;
        const fb = typeof window.__CART_SPEED_FEES__ === "object" && window.__CART_SPEED_FEES__ ? window.__CART_SPEED_FEES__ : {};
        expressFee = Number(fb.express) || 0;
        sameDayFee = Number(fb.same_day) || 0;
      }
    } else {
      const fb = typeof window.__CART_SPEED_FEES__ === "object" && window.__CART_SPEED_FEES__ ? window.__CART_SPEED_FEES__ : {};
      expressFee = Number(fb.express) || 0;
      sameDayFee = Number(fb.same_day) || 0;
    }
    if (seq !== deliveryFetchSeq) return;

    if (typeof window.__CART_SPEED_FEES__ !== "object" || !window.__CART_SPEED_FEES__) {
      window.__CART_SPEED_FEES__ = {};
    }
    window.__CART_SPEED_FEES__.express = expressFee;
    window.__CART_SPEED_FEES__.same_day = sameDayFee;

    const expressDisp = document.getElementById("expressFeeDisplay");
    const sameDisp = document.getElementById("sameDayFeeDisplay");
    if (expressDisp) {
      expressDisp.textContent = expressFee > 0 ? "₹" + expressFee.toLocaleString("en-IN") : "FREE";
      expressDisp.className = expressFee > 0 ? "" : "text-green";
    }
    if (sameDisp) {
      sameDisp.textContent = sameDayFee > 0 ? "₹" + sameDayFee.toLocaleString("en-IN") : "FREE";
      sameDisp.className = sameDayFee > 0 ? "" : "text-green";
    }

    let speedExtra = 0;
    if (speedMode === "express") speedExtra = expressFee;
    else if (speedMode === "same_day") speedExtra = sameDayFee;

    const shippingTotal = speedMode === "standard" ? sellerDelivery : speedExtra;
    const platformFee = cartPlatformFeeAmount();
    let discount = 0;
    if (appliedCoupon) {
      const sellerScope = appliedCoupon.seller_id != null && appliedCoupon.seller_id !== "" ? Number(appliedCoupon.seller_id) : null;
      const minOrder = appliedCoupon.min_order != null ? Number(appliedCoupon.min_order) : 0;
      const base = cartApplicableSubtotalForCoupon(cartItems, sellerScope);
      if (base >= minOrder && base > 0) {
        if (appliedCoupon.type === "percent") {
          const cap = appliedCoupon.max != null && appliedCoupon.max !== "" ? Number(appliedCoupon.max) : Infinity;
          discount = Math.min(Math.round(base * appliedCoupon.val / 100), cap);
        } else {
          discount = appliedCoupon.val;
        }
        discount = Math.min(discount, base);
      }
    }
    const total = subtotal - discount + shippingTotal + platformFee;
    const saved = (origTotal - subtotal) + discount;
    document.getElementById("subtotalEl").textContent = "₹" + subtotal.toLocaleString();
    document.getElementById("totalEl").textContent = "₹" + total.toLocaleString();
    const pFeeEl = document.getElementById("platformFeeEl");
    if (pFeeEl) pFeeEl.textContent = "₹" + platformFee.toLocaleString("en-IN");
    document.getElementById("deliveryEl").textContent = shippingTotal === 0 ? "FREE" : "₹" + shippingTotal.toLocaleString("en-IN");
    document.getElementById("deliveryEl").className = shippingTotal === 0 ? "text-green" : "";
    const speedLbl = document.getElementById("deliverySpeedLabel");
    if (speedLbl) {
      speedLbl.textContent = speedName;
      speedLbl.className = "delivery-speed-tag delivery-speed-tag--" + speedTagClass;
    }
    const stdLbl = document.getElementById("standardSpeedExtraLabel");
    if (stdLbl) {
      if (speedMode === "standard") {
        if (sellerDelivery === 0) {
          stdLbl.textContent = "FREE";
          stdLbl.className = "text-green";
        } else {
          stdLbl.textContent = "In total";
          stdLbl.className = "";
        }
      } else {
        stdLbl.textContent = "FREE";
        stdLbl.className = "text-green";
      }
    }
    document.getElementById("savingBadge").textContent = `You save ₹${saved.toLocaleString()} on this order! 🎉`;
    const dRow = document.getElementById("discountRow");
    if (discount > 0) { dRow.style.display = "flex"; document.getElementById("discountEl").textContent = "-₹" + discount.toLocaleString(); }
    else { dRow.style.display = "none"; }
    refreshCartDeliveryEtaDom();
  }

  /** Inline handlers in cart.php (e.g. delivery radios) call this on window. */
  window.updateTotal = function() {
    void updateTotal();
  };

  window.proceedToCheckout = function() {
    if (!cartItems.some(i => i.checked)) { showToast("⚠️ Select at least one item!"); return; }
    try {
      const inp = document.getElementById("couponInput");
      const raw = inp ? inp.value.trim().toUpperCase() : "";
      if (raw && COUPONS[raw]) sessionStorage.setItem("luxeCheckoutCoupon", raw);
    } catch (_e) {}
    if (!window.__AUTH_USER_ID__) {
      showToast("🔐 Please sign in to continue to checkout.");
      const loginBase = LUXE_URLS.login || "login.php";
      const sep = loginBase.includes("?") ? "&" : "?";
      setTimeout(() => { window.location.href = loginBase + sep + "redirect=" + encodeURIComponent("checkout.php"); }, 800);
      return;
    }
    const dest = (typeof LUXE_URLS.checkout === "string" && LUXE_URLS.checkout) ? LUXE_URLS.checkout : "checkout.php";
    window.location.href = dest;
  };
  /** @deprecated Use proceedToCheckout — kept for any old onclick handlers */
  window.placeOrder = window.proceedToCheckout;

  document.getElementById("orderModal")?.addEventListener("click", e => { if (e.target === e.currentTarget) e.currentTarget.classList.add("hidden"); });
  document.addEventListener("DOMContentLoaded", renderCart);
})();


// ================================================================
//  PAGE: ORDERS — orders.js
//  Guard: #ordersList element exists
// ================================================================
(function initOrdersPage() {
  if (!document.getElementById("ordersList")) return;

  const FALLBACK_ORDERS = [
    { id: "LUXE83920741", date: "Apr 10, 2026", createdAt: "2026-04-10 12:00:00", status: "delivered", items: [{ productId: 1, emoji: "👟", name: "AirMax Pro 2026", variant: "UK 8 · Purple", price: 8999 }, { productId: 2, emoji: "🎧", name: "Sony WH-1000XM5", variant: "Black", price: 18999 }], total: 27998, address: "Sector 15, Noida, UP 201301", payment: "HDFC Credit Card", tracking: ["ordered","confirmed","shipped","out","delivered"] },
    { id: "LUXE92810465", date: "Apr 6, 2026", createdAt: "2026-04-06 10:00:00", status: "shipped", items: [{ productId: 5, emoji: "⌚", name: "Apple Watch SE 44mm", variant: "Midnight · GPS", price: 19500 }], total: 19500, address: "Sector 15, Noida, UP 201301", payment: "UPI - rahul@ok", tracking: ["ordered","confirmed","shipped"] },
    { id: "LUXE77541238", date: "Mar 28, 2026", createdAt: "2026-03-28 15:00:00", status: "delivered", items: [{ productId: 6, emoji: "👗", name: "Linen Co-ord Set", variant: "S · Beige", price: 3299 }, { productId: 3, emoji: "🧴", name: "Retinol Serum Kit", variant: "30ml", price: 1899 }, { productId: 8, emoji: "💡", name: "LED Desk Lamp", variant: "White", price: 1599 }], total: 6797, address: "Sector 15, Noida, UP 201301", payment: "Amazon Pay", tracking: ["ordered","confirmed","shipped","out","delivered"] },
    { id: "LUXE64829301", date: "Mar 15, 2026", createdAt: "2026-03-15 09:30:00", status: "cancelled", items: [{ productId: 0, emoji: "💻", name: "UltraBook Air", variant: "16GB · 512GB SSD", price: 69999 }], total: 69999, address: "Sector 15, Noida, UP 201301", payment: "Cancelled", tracking: ["ordered","confirmed"] },
    { id: "LUXE51203847", date: "Feb 28, 2026", createdAt: "2026-02-28 17:45:00", status: "delivered", items: [{ productId: 0, emoji: "📱", name: "Samsung Galaxy S25", variant: "256GB · Phantom Black", price: 74999 }], total: 74999, address: "Sector 15, Noida, UP 201301", payment: "No Cost EMI", tracking: ["ordered","confirmed","shipped","out","delivered"] },
    { id: "LUXE39402187", date: "Feb 12, 2026", createdAt: "2026-02-12 11:00:00", status: "processing", items: [{ productId: 0, emoji: "🎮", name: "PS5 DualSense Controller", variant: "Cosmic Red", price: 5499 }], total: 5499, address: "Sector 15, Noida, UP 201301", payment: "COD", tracking: ["ordered","confirmed"] },
  ];
  const orders = (typeof window.__ORDERS__ !== "undefined") ? window.__ORDERS__ : FALLBACK_ORDERS;

  const trackingLabels = ["Ordered", "Confirmed", "Shipped", "Out for Delivery", "Delivered"];
  const returnTrackingLabels = ["Requested", "Approved", "Pickup Scheduled", "Picked Up", "Refunded"];
  let currentFilter = "all", searchQuery = "";

  function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
  const statusDots = { delivered: "✓", out: "📍", shipped: "🚚", confirmed: "✔", processing: "⏳", cancelled: "✕" };
  function getTrackingStep(order) { return ({ processing: 1, confirmed: 2, shipped: 3, out: 4, delivered: 5, cancelled: 2 })[order.status] || 1; }
  function getReturnExpiry(order) {
    const raw = order?.createdAt || "";
    const dt = raw ? new Date(raw.replace(" ", "T")) : null;
    if (!dt || Number.isNaN(dt.getTime())) return null;
    return new Date(dt.getTime() + 10 * 24 * 60 * 60 * 1000);
  }
  function isReturnWindowOpen(order) {
    if (order?.status !== "delivered") return false;
    const expiry = getReturnExpiry(order);
    if (!expiry) return true;
    return Date.now() <= expiry.getTime();
  }
  function returnStatusLabel(status) {
    const s = String(status || "").toLowerCase();
    if (s === "pending") return "Pending Seller Review";
    if (s === "approved") return "Approved";
    if (s === "pickup_scheduled") return "Pickup Scheduled";
    if (s === "picked_up") return "Picked Up";
    if (s === "refund_processing") return "Refund Processing";
    if (s === "refunded") return "Refund Completed";
    if (s === "rejected") return "Rejected";
    return s ? s.replace(/_/g, " ") : "No return";
  }
  function pickupStatusLabel(status) {
    const s = String(status || "").toLowerCase();
    if (s === "not_scheduled") return "Not Scheduled";
    if (s === "scheduled") return "Scheduled";
    if (s === "picked_up") return "Picked Up";
    if (s === "completed") return "Completed";
    if (s === "cancelled") return "Cancelled";
    return s ? s.replace(/_/g, " ") : "Not Scheduled";
  }
  function returnProgressStep(status) {
    const s = String(status || "").toLowerCase();
    if (s === "pending") return 1;
    if (s === "approved") return 2;
    if (s === "pickup_scheduled") return 3;
    if (s === "picked_up" || s === "refund_processing") return 4;
    if (s === "refunded") return 5;
    if (s === "rejected") return 1;
    return 1;
  }
  function itemHasReturnRequest(item) {
    const rr = item?.returnRequest;
    return !!(rr && typeof rr === "object");
  }
  function canRequestReturn(item) {
    const rr = item?.returnRequest;
    if (!rr || typeof rr !== "object") return true;
    const st = String(rr.status || "").toLowerCase();
    // Allow fresh request only when no request exists or last one was rejected.
    return st === "rejected";
  }
  function hasReturnInProgress(item) {
    const rr = item?.returnRequest;
    if (!rr || typeof rr !== "object") return false;
    const st = String(rr.status || "").toLowerCase();
    return ["pending", "approved", "pickup_scheduled", "picked_up", "refund_processing"].includes(st);
  }
  function hasReturnCompleted(item) {
    const rr = item?.returnRequest;
    if (!rr || typeof rr !== "object") return false;
    const st = String(rr.status || "").toLowerCase();
    return st === "refunded";
  }
  function escHtml(v) {
    return String(v ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  window.filterOrders = function(btn) { document.querySelectorAll(".otab").forEach(t => t.classList.remove("active")); btn.classList.add("active"); currentFilter = btn.dataset.status; renderOrders(); };
  window.searchOrders = function(q) { searchQuery = q.toLowerCase(); renderOrders(); };

  function renderOrders() {
    let list = orders.filter(o => {
      const hasReturns = (o.items || []).some(item => itemHasReturnRequest(item));
      const sm = currentFilter === "all"
        || (currentFilter === "returns" ? hasReturns : o.status === currentFilter);
      const sq = !searchQuery || o.id.toLowerCase().includes(searchQuery) || o.items.some(i => i.name.toLowerCase().includes(searchQuery));
      return sm && sq;
    });
    const container = document.getElementById("ordersList");
    const empty = document.getElementById("emptyOrders");
    document.getElementById("ordersBadge").textContent = `${list.length} order${list.length !== 1 ? "s" : ""}`;
    if (list.length === 0) { container.innerHTML = ""; empty.classList.remove("hidden"); return; }
    empty.classList.add("hidden");
    container.innerHTML = list.map(order => {
      const extra = order.items.length > 2 ? order.items.length - 2 : 0;
      const display = order.items.slice(0, 2);
      const step = getTrackingStep(order);
      const canReturn = isReturnWindowOpen(order);
      const returnItems = (order.items || []).filter(item => itemHasReturnRequest(item));
      const returnableItems = (order.items || []).filter(item => canRequestReturn(item));
      const hasAnyReturnInProgress = (order.items || []).some(item => hasReturnInProgress(item));
      const hasAnyReturnCompleted = (order.items || []).some(item => hasReturnCompleted(item));
      const canCancel = order.status === "processing" || order.status === "confirmed" || order.status === "shipped";
      const cancelBtn = canCancel
        ? `<button class="action-btn secondary" onclick="openOrderCancelForm('${order.id}')">Cancel Order</button>`
        : "";
      const returnBtn = order.status === "delivered"
        ? (canReturn && returnableItems.length > 0
          ? `<button class="action-btn secondary" onclick="openOrderReturnForm('${order.id}')">Return</button>`
          : hasAnyReturnInProgress
          ? `<button class="action-btn secondary is-disabled" type="button" disabled title="Return request already in progress">Return In Progress</button>`
          : (hasAnyReturnCompleted && returnableItems.length === 0)
          ? `<button class="action-btn secondary is-disabled" type="button" disabled title="Refund completed for this order item">Return Completed</button>`
          : `<button class="action-btn secondary is-disabled" type="button" disabled title="Return window closed after 10 days">Return (Expired)</button>`)
        : "";
      const returnProgressHtml = returnItems.length > 0
        ? `<div class="tracking-section">
            <div class="tracking-label">↩ Return Progress</div>
            ${returnItems.slice(0, 3).map(item => {
              const rr = item.returnRequest || {};
              const rrStatus = String(rr.status || "").toLowerCase();
              const isRejected = rrStatus === "rejected";
              const rStep = returnProgressStep(rrStatus);
              if (isRejected) {
                return `<div style="margin-bottom:10px">
                  <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:6px">${escHtml(item.name)}</div>
                  <span class="status-badge status-cancelled">✕ Rejected</span>
                </div>`;
              }
              return `<div style="margin-bottom:10px">
                <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:6px">${escHtml(item.name)}</div>
                <div class="tracking-steps">
                  ${returnTrackingLabels.map((label, i) => `<div class="tracking-step ${i < rStep ? "done" : ""} ${i === rStep - 1 && rrStatus !== "refunded" ? "active" : ""}"><div class="step-dot">${i < rStep ? "✓" : i + 1}</div><span class="step-label">${label}</span></div>`).join("")}
                </div>
              </div>`;
            }).join("")}
            ${returnItems.length > 3 ? `<div style="font-size:0.76rem;color:var(--text-dim)">+${returnItems.length - 3} more return item(s)</div>` : ""}
          </div>`
        : "";
      return `<div class="order-card reveal">
        <div class="order-card-header"><div class="order-meta"><span class="order-id-label">Order ID</span><span class="order-id-val">#${order.id}</span><span class="order-date">Placed on ${order.date}</span></div><div><span class="status-badge status-${order.status}">${statusDots[order.status]} ${capitalize(order.status)}</span></div></div>
        <div class="order-items-row">${display.map(item => {
          const rr = item.returnRequest;
          const statusLine = rr
            ? `<span style="display:block;font-size:0.75rem;color:var(--text-dim)">Return: ${escHtml(returnStatusLabel(rr.status))} · Pickup: ${escHtml(pickupStatusLabel(rr.pickupStatus))}</span>`
            : "";
          return `<div class="order-product"><div class="order-product-img">${item.emoji}</div><div class="order-product-info"><strong>${item.name}</strong><span>${item.variant}</span>${statusLine}</div></div>`;
        }).join("")}${extra > 0 ? `<span class="order-more-items">+${extra} more</span>` : ""}</div>
        ${order.status !== "cancelled" ? `<div class="tracking-section"><div class="tracking-label">📍 Order Progress</div><div class="tracking-steps">${trackingLabels.map((label, i) => `<div class="tracking-step ${i < step ? "done" : ""} ${i === step - 1 && order.status !== "delivered" ? "active" : ""}"><div class="step-dot">${i < step ? "✓" : i+1}</div><span class="step-label">${label}</span></div>`).join("")}</div></div>` : ""}
        ${returnProgressHtml}
        <div class="order-card-footer"><div class="order-total-info"><span class="order-total-label">Order Total</span><span class="order-total-val">₹${order.total.toLocaleString()}</span></div>
        <div class="order-card-actions">${order.status === "delivered" ? `<button class="action-btn secondary" onclick="openOrderReviewForm('${order.id}')">Rate & Review</button>` : ""}${cancelBtn}${returnBtn}<button class="action-btn primary" onclick="viewDetail('${order.id}')">View Details →</button></div></div>
      </div>`;
    }).join("");
    observeAll();
  }

  window.viewDetail = function(ordId) {
    const order = orders.find(o => o.id === ordId); if (!order) return;
    document.getElementById("detailOrderId").textContent = "#" + order.id;
    const detailItemsHtml = (order.items || []).map(item => {
      const qty = Math.max(1, Number(item.qty || 1));
      const unitPrice = Number(item.price || 0);
      const lineTotal = Number(item.lineTotal || (unitPrice * qty));
      const qtyText = qty > 1 ? ` · Qty ${qty}` : "";
      return `<div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid var(--border)"><span style="font-size:2rem">${item.emoji}</span><div style="flex:1"><strong style="color:var(--white);display:block">${item.name}</strong><span style="font-size:0.8rem;color:var(--text-muted)">${item.variant}${qtyText}</span></div><strong style="color:var(--primary-light)">₹${lineTotal.toLocaleString()}</strong></div>`;
    }).join("");
    const returnItemsHtml = (order.items || [])
      .filter(item => item && item.returnRequest)
      .map(item => {
        const rr = item.returnRequest || {};
        const rrReason = rr.reason ? String(rr.reason) : "";
        const rrDetails = rr.details ? String(rr.details) : "";
        const rrPickupNote = rr.pickupNote ? String(rr.pickupNote) : "";
        const rrRefundAmount = Math.max(0, Number(rr.refundAmount || 0));
        const rrRefundMode = rr.refundMode ? String(rr.refundMode) : "";
        const rrStatus = String(rr.status || "").toLowerCase();
        const rStep = returnProgressStep(rr.status);
        const trackingHtml = rrStatus === "rejected"
          ? `<span style="display:block;font-size:0.74rem;color:#fca5a5;margin-top:6px">Return request rejected</span>`
          : `<div class="tracking-steps" style="margin-top:8px">${returnTrackingLabels.map((label, i) => `<div class="tracking-step ${i < rStep ? "done" : ""} ${i === rStep - 1 && rrStatus !== "refunded" ? "active" : ""}"><div class="step-dot">${i < rStep ? "✓" : i + 1}</div><span class="step-label">${label}</span></div>`).join("")}</div>`;
        return `<div style="padding:12px 0;border-bottom:1px solid var(--border)">
          <strong style="color:var(--white);display:block">${escHtml(item.name || "Item")}</strong>
          <span style="display:block;font-size:0.74rem;color:var(--text-dim);margin-top:4px">Return: ${escHtml(returnStatusLabel(rr.status))} | Pickup: ${escHtml(pickupStatusLabel(rr.pickupStatus))}</span>
          ${rrReason ? `<span style="display:block;font-size:0.74rem;color:var(--text-dim);margin-top:3px">Reason: ${escHtml(rrReason)}</span>` : ""}
          ${rrDetails ? `<span style="display:block;font-size:0.74rem;color:var(--text-dim);margin-top:3px">Details: ${escHtml(rrDetails)}</span>` : ""}
          ${rrPickupNote ? `<span style="display:block;font-size:0.74rem;color:var(--text-dim);margin-top:3px">Pickup Note: ${escHtml(rrPickupNote)}</span>` : ""}
          <span style="display:block;font-size:0.74rem;color:var(--text-dim);margin-top:3px">Refund Amount: ₹${rrRefundAmount.toLocaleString("en-IN")}</span>
          <span style="display:block;font-size:0.74rem;color:var(--text-dim);margin-top:3px">Refund Mode: ${escHtml(rrRefundMode || "Original payment method")}</span>
          ${trackingHtml}
        </div>`;
      }).join("");
    const returnCardHtml = returnItemsHtml
      ? `<div style="background:var(--bg3);border-radius:var(--radius-sm);padding:16px">
          <h4 style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.08em">Return Details</h4>
          ${returnItemsHtml}
        </div>`
      : "";
    document.getElementById("detailContent").innerHTML = `<div style="display:flex;flex-direction:column;gap:16px"><div style="background:var(--bg3);border-radius:var(--radius-sm);padding:16px;display:flex;justify-content:space-between;align-items:center"><div><span style="font-size:0.78rem;color:var(--text-dim)">STATUS</span><br/><span class="status-badge status-${order.status}" style="margin-top:6px;display:inline-flex">${capitalize(order.status)}</span></div><div><span style="font-size:0.78rem;color:var(--text-dim)">DATE</span><br/><strong style="color:var(--white)">${order.date}</strong></div><div><span style="font-size:0.78rem;color:var(--text-dim)">PAYMENT</span><br/><strong style="color:var(--white)">${order.payment}</strong></div></div><div><h4 style="font-size:0.85rem;color:var(--text-muted);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.08em">Items Ordered</h4>${detailItemsHtml}</div>${returnCardHtml}<div style="background:var(--bg3);border-radius:var(--radius-sm);padding:16px"><h4 style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.08em">Delivery Address</h4><p style="color:var(--text-muted);font-size:0.88rem">📍 ${order.address}</p></div><div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-top:1px solid var(--border)"><strong style="color:var(--white)">Order Total</strong><strong style="font-size:1.3rem;color:var(--primary-light)">₹${order.total.toLocaleString()}</strong></div></div>`;
    document.getElementById("detailModal").classList.remove("hidden");
  };
  window.closeModal = function() { document.getElementById("detailModal").classList.add("hidden"); };
  document.getElementById("detailModal")?.addEventListener("click", e => { if (e.target === e.currentTarget) closeModal(); });

  window.openOrderReviewForm = function(ordId) {
    const order = orders.find(o => o.id === ordId);
    if (!order || order.status !== "delivered") return;
    const modal = document.getElementById("orderReviewModal");
    const orderRefInput = document.getElementById("reviewOrderRef");
    const orderIdText = document.getElementById("reviewOrderId");
    const productSelect = document.getElementById("reviewProductId");
    if (!modal || !orderRefInput || !orderIdText || !productSelect) return;
    orderRefInput.value = order.id;
    orderIdText.textContent = "#" + order.id;
    const options = (order.items || [])
      .filter(i => Number(i.productId || 0) > 0)
      .map(i => `<option value="${Number(i.productId)}">${escHtml(i.name)}</option>`)
      .join("");
    productSelect.innerHTML = options || `<option value="">No reviewable product found</option>`;
    modal.classList.remove("hidden");
  };
  window.closeOrderReviewModal = function() {
    document.getElementById("orderReviewModal")?.classList.add("hidden");
  };

  window.openOrderReturnForm = function(ordId) {
    const order = orders.find(o => o.id === ordId);
    if (!order || order.status !== "delivered") return;
    if (!isReturnWindowOpen(order)) {
      showToast("⏱️ Return window closed (10 days).");
      return;
    }
    const modal = document.getElementById("orderReturnModal");
    const orderRefInput = document.getElementById("returnOrderRef");
    const orderIdText = document.getElementById("returnOrderId");
    const productSelect = document.getElementById("returnProductName");
    const productNameText = document.getElementById("returnProductNameText");
    if (!modal || !orderRefInput || !orderIdText || !productSelect || !productNameText) return;
    orderRefInput.value = order.id;
    orderIdText.textContent = "#" + order.id;
    const returnableItems = (order.items || []).filter(i => canRequestReturn(i));
    if (!returnableItems.length) {
      showToast("ℹ️ All items already have active return requests.");
      return;
    }
    productSelect.innerHTML = returnableItems.map(i => {
      const oid = Number(i.orderItemId || 0);
      const nm = escHtml(i.name);
      return `<option value="${oid}">${nm}</option>`;
    }).join("");
    const selectedOpt = productSelect.options[productSelect.selectedIndex];
    productNameText.value = selectedOpt ? selectedOpt.textContent : "";
    productSelect.onchange = function () {
      const opt = productSelect.options[productSelect.selectedIndex];
      productNameText.value = opt ? opt.textContent : "";
    };
    modal.classList.remove("hidden");
  };
  window.closeOrderReturnModal = function() {
    document.getElementById("orderReturnModal")?.classList.add("hidden");
  };

  window.openOrderCancelForm = function(ordId) {
    const order = orders.find(o => o.id === ordId);
    if (!order) return;
    if (!(order.status === "processing" || order.status === "confirmed" || order.status === "shipped")) {
      showToast("❌ Cancel not allowed after out for delivery.");
      return;
    }
    const modal = document.getElementById("orderCancelModal");
    const orderRefInput = document.getElementById("cancelOrderRef");
    const orderIdText = document.getElementById("cancelOrderId");
    if (!modal || !orderRefInput || !orderIdText) return;
    orderRefInput.value = order.id;
    orderIdText.textContent = "#" + order.id;
    modal.classList.remove("hidden");
  };
  window.closeOrderCancelModal = function() {
    document.getElementById("orderCancelModal")?.classList.add("hidden");
  };

  document.getElementById("orderReviewModal")?.addEventListener("click", e => { if (e.target === e.currentTarget) closeOrderReviewModal(); });
  document.getElementById("orderReturnModal")?.addEventListener("click", e => { if (e.target === e.currentTarget) closeOrderReturnModal(); });
  document.getElementById("orderCancelModal")?.addEventListener("click", e => { if (e.target === e.currentTarget) closeOrderCancelModal(); });

  document.addEventListener("DOMContentLoaded", renderOrders);
})();


// ================================================================
//  PAGE: PROFILE — profile.js
//  Guard: #profileForm and/or #addressModal (checkout uses modal only)
// ================================================================
(function initProfilePage() {
  const profileForm = document.getElementById("profileForm");
  const addressModalEl = document.getElementById("addressModal");
  if (!profileForm && !addressModalEl) return;

  // ---- Tab Switching ----
  if (profileForm) window.switchTab = function(btn) {
    document.querySelectorAll(".smenu-item").forEach(b => b.classList.remove("active"));
    document.querySelectorAll(".tab-panel").forEach(p => { p.classList.add("hidden"); p.classList.remove("active"); });
    btn.classList.add("active");
    const panel = document.getElementById("tab-" + btn.dataset.tab);
    if (panel) { panel.classList.remove("hidden"); panel.classList.add("active"); }
    refreshCursorTargets();
  };

  // ---- Edit Profile ----
  let editing = false;
  if (profileForm) window.toggleEdit = function() {
    editing = !editing;
    document.querySelectorAll("#profileForm input, #profileForm select").forEach(el => el.disabled = !editing);
    document.getElementById("formActions").classList.toggle("hidden", !editing);
    document.getElementById("editToggle").textContent = editing ? "✕ Cancel" : "✏️ Edit";
  };
  if (profileForm) {
    window.cancelEdit = function() { if (editing) toggleEdit(); };
    window.saveProfile = async function(e) {
      e.preventDefault();
      const first = document.getElementById("firstName").value.trim();
      const last = document.getElementById("lastName").value.trim();
      const email = document.getElementById("email").value.trim();
      const phone = document.getElementById("phone").value.trim();
      const dob = document.getElementById("dob").value.trim();
      const genderEl = document.getElementById("gender");
      const gender = genderEl ? genderEl.value : "";
      if (!first || !last || !email) {
        showToast("❌ Please fill first name, last name, and email.");
        return;
      }
      const url = typeof window.__API_PROFILE_UPDATE__ === "string"
        ? window.__API_PROFILE_UPDATE__
        : "actions/update-profile.php";
      try {
        const r = await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ first_name: first, last_name: last, email, phone, dob, gender }),
          credentials: "same-origin",
        });
        const j = await r.json().catch(() => ({}));
        if (!j.ok) {
          showToast("❌ " + (j.message || "Could not update profile."));
          return;
        }
        document.getElementById("profileName").textContent = `${first} ${last}`;
        document.getElementById("avatarEl").textContent = (first[0] || "?").toUpperCase();
        document.getElementById("profileEmail").textContent = email;
        const phoneEl = document.getElementById("phone");
        const dobEl = document.getElementById("dob");
        if (phoneEl && typeof j.phone === "string") phoneEl.value = j.phone;
        if (dobEl) dobEl.value = j.dob && typeof j.dob === "string" ? j.dob : "";
        if (genderEl) {
          const g = j.gender;
          genderEl.value = g === "male" || g === "female" || g === "other" ? g : "other";
        }
        toggleEdit();
        showToast("✅ Profile updated successfully!");
      } catch (_err) {
        showToast("❌ Network error. Try again.");
      }
    };

    window.openChangePasswordModal = function() {
      document.getElementById("changePasswordModal")?.classList.remove("hidden");
      setTimeout(() => document.getElementById("pchCurrent")?.focus(), 50);
    };
    window.closeChangePasswordModal = function() {
      document.getElementById("changePasswordModal")?.classList.add("hidden");
      document.getElementById("changePasswordForm")?.reset();
    };

    window.savePasswordChange = async function(e) {
      e.preventDefault();
      const cur = document.getElementById("pchCurrent")?.value ?? "";
      const nw = document.getElementById("pchNew")?.value ?? "";
      const cf = document.getElementById("pchConfirm")?.value ?? "";
      if (!cur || !nw || !cf) {
        showToast("❌ Please fill all password fields.");
        return;
      }
      if (nw !== cf) {
        showToast("❌ New password and confirmation do not match.");
        return;
      }
      if (nw.length < 8) {
        showToast("❌ New password must be at least 8 characters.");
        return;
      }
      const letter = /[A-Za-z]/.test(nw);
      const digit = /[0-9]/.test(nw);
      if (!letter || !digit) {
        showToast("❌ New password must include at least one letter and one number.");
        return;
      }
      if (nw === cur) {
        showToast("❌ New password must be different from your current password.");
        return;
      }
      const url = typeof window.__API_CHANGE_PASSWORD__ === "string"
        ? window.__API_CHANGE_PASSWORD__
        : "actions/change-password.php";
      const btn = document.getElementById("pchSubmit");
      if (btn) btn.disabled = true;
      try {
        const r = await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ current_password: cur, new_password: nw }),
          credentials: "same-origin",
        });
        const j = await r.json().catch(() => ({}));
        if (!j.ok) {
          showToast("❌ " + (j.message || "Could not update password."));
          return;
        }
        window.closeChangePasswordModal();
        showToast("✅ " + (j.message || "Password updated successfully."));
      } catch (_err) {
        showToast("❌ Network error. Try again.");
      } finally {
        if (btn) btn.disabled = false;
      }
    };
  }

  // ---- Addresses (server-backed) ----
  function luxeEscapeHtml(s) {
    return String(s ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }
  const FALLBACK_ADDRESSES = [
    { id: 1, type: "Home", name: "Rahul Sharma", phone: "+91 98765 43210", line1: "Flat 402, Emerald Heights", line2: "Sector 15, Near Metro Station", city: "Noida", pin: "201301", state: "Uttar Pradesh", isDefault: true },
    { id: 2, type: "Work", name: "Rahul Sharma", phone: "+91 91234 56789", line1: "LUXE Corp, Tower B, 5th Floor", line2: "Cyber City, DLF Phase 2", city: "Gurugram", pin: "122002", state: "Haryana", isDefault: false },
  ];
  let addresses;
  if (typeof window.__ADDRESSES__ !== "undefined") {
    addresses = window.__ADDRESSES__.length ? JSON.parse(JSON.stringify(window.__ADDRESSES__)) : [];
  } else if (addressModalEl) {
    addresses = [];
  } else {
    addresses = FALLBACK_ADDRESSES;
  }
  const hasAddressApi = typeof window.__API_ADDRESS_SAVE__ === "string" && window.__API_ADDRESS_SAVE__.length > 0;

  function addrTypeIcon(type) {
    if (type === "Home") return "🏠";
    if (type === "Work") return "💼";
    return "📍";
  }
  function addrTypeClass(type) {
    if (type === "Home") return "addr-type-home";
    if (type === "Work") return "addr-type-work";
    return "addr-type-work";
  }
  function formatAddressLines(a) {
    const l2 = (a.line2 && String(a.line2).trim()) ? `, ${luxeEscapeHtml(a.line2)}` : "";
    return `${luxeEscapeHtml(a.line1)}${l2}, ${luxeEscapeHtml(a.city)}, ${luxeEscapeHtml(a.state)} – ${luxeEscapeHtml(a.pin)}`;
  }

  function renderAddresses() {
    const grid = document.getElementById("addressesGrid");
    if (!grid) return;
    const cards = addresses.map(a => {
      const nm = luxeEscapeHtml(a.name);
      const ph = luxeEscapeHtml(a.phone || "");
      const type = luxeEscapeHtml(a.type || "Home");
      const def = a.isDefault ? `<span class="default-tag">Default</span>` : "";
      const setDef = !a.isDefault
        ? `<button type="button" class="addr-btn" data-addr-default="${a.id}">Set default</button>`
        : "";
      const del = `<button type="button" class="addr-btn" data-addr-delete="${a.id}" style="color:var(--danger)">Delete</button>`;
      return `<div class="address-card ${a.isDefault ? "default-addr" : ""}" data-address-id="${a.id}"><div class="address-type"><span class="addr-type-badge ${addrTypeClass(a.type)}">${addrTypeIcon(a.type)} ${type}</span>${def}</div><div class="address-name">${nm}</div><div class="address-text">${formatAddressLines(a)}</div><div class="address-phone">${ph ? `📞 ${ph}` : ""}</div><div class="addr-actions"><button type="button" class="addr-btn" data-addr-edit="${a.id}">Edit</button>${setDef}${del}</div></div>`;
    }).join("");
    grid.innerHTML = cards + `<button type="button" class="add-addr-card" id="addAddrCardBtn"><span class="add-addr-icon">+</span><span>Add new address</span></button>`;
    grid.querySelectorAll("[data-addr-edit]").forEach(btn => {
      btn.addEventListener("click", () => editAddress(Number(btn.getAttribute("data-addr-edit"), 10)));
    });
    grid.querySelectorAll("[data-addr-default]").forEach(btn => {
      btn.addEventListener("click", () => setDefaultAddress(Number(btn.getAttribute("data-addr-default"), 10)));
    });
    grid.querySelectorAll("[data-addr-delete]").forEach(btn => {
      btn.addEventListener("click", () => deleteAddress(Number(btn.getAttribute("data-addr-delete"), 10)));
    });
    document.getElementById("addAddrCardBtn")?.addEventListener("click", () => showAddressModal());
    refreshCursorTargets();
  }

  function resetAddressForm() {
    const idEl = document.getElementById("addressId");
    if (idEl) idEl.value = "";
    const f = document.getElementById("addressForm");
    if (f) f.reset();
    const def = document.getElementById("addrIsDefault");
    if (def) def.checked = addresses.length === 0;
  }

  window.showAddressModal = function() {
    const title = document.getElementById("addressModalTitle");
    if (title) title.textContent = "Add address";
    resetAddressForm();
    document.getElementById("addressModal")?.classList.remove("hidden");
  };

  window.editAddress = function(id) {
    const a = addresses.find(x => x.id === id);
    if (!a) return;
    const title = document.getElementById("addressModalTitle");
    if (title) title.textContent = "Edit address";
    const idEl = document.getElementById("addressId");
    if (idEl) idEl.value = String(a.id);
    const set = (fid, v) => { const el = document.getElementById(fid); if (el) el.value = v != null ? String(v) : ""; };
    set("addrName", a.name);
    set("addrPhone", a.phone);
    set("addrLine1", a.line1);
    set("addrLine2", a.line2);
    set("addrCity", a.city);
    set("addrPin", a.pin);
    set("addrState", a.state);
    const sel = document.getElementById("addrType");
    if (sel) {
      const t = a.type === "Work" || a.type === "Other" ? a.type : "Home";
      sel.value = t;
    }
    const def = document.getElementById("addrIsDefault");
    if (def) def.checked = !!a.isDefault;
    document.getElementById("addressModal")?.classList.remove("hidden");
  };

  window.closeAddressModal = function() {
    document.getElementById("addressModal")?.classList.add("hidden");
  };

  function applyAddressesFromResponse(j) {
    if (j && Array.isArray(j.addresses)) {
      addresses = j.addresses;
      if (window.__CHECKOUT_ADDRESS_RELOAD__ && !document.getElementById("addressesGrid")) {
        window.location.reload();
        return;
      }
      renderAddresses();
    }
  }

  window.setDefaultAddress = async function(id) {
    if (!hasAddressApi) {
      addresses.forEach(a => { a.isDefault = a.id === id; });
      renderAddresses();
      showToast("Default address updated.");
      return;
    }
    const url = window.__API_ADDRESS_DEFAULT__;
    try {
      const r = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
        credentials: "same-origin",
      });
      const j = await r.json().catch(() => ({}));
      if (!j.ok) {
        showToast("❌ " + (j.message || "Could not update default."));
        return;
      }
      applyAddressesFromResponse(j);
      showToast("Default address updated.");
    } catch (_e) {
      showToast("❌ Network error.");
    }
  };

  window.deleteAddress = async function(id) {
    if (!confirm("Remove this address?")) return;
    if (!hasAddressApi) {
      const idx = addresses.findIndex(a => a.id === id);
      if (idx > -1) {
        addresses.splice(idx, 1);
        renderAddresses();
        showToast("Address removed.");
      }
      return;
    }
    const url = window.__API_ADDRESS_DELETE__;
    try {
      const r = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id }),
        credentials: "same-origin",
      });
      const j = await r.json().catch(() => ({}));
      if (!j.ok) {
        showToast("❌ " + (j.message || "Could not delete."));
        return;
      }
      applyAddressesFromResponse(j);
      showToast("Address removed.");
    } catch (_e) {
      showToast("❌ Network error.");
    }
  };

  window.saveAddress = async function(e) {
    e.preventDefault();
    const btn = document.getElementById("addressSaveBtn");
    const idEl = document.getElementById("addressId");
    const rawId = idEl && idEl.value ? parseInt(idEl.value, 10) : 0;
    const payload = {
      id: rawId > 0 ? rawId : undefined,
      type: document.getElementById("addrType")?.value || "Home",
      name: document.getElementById("addrName")?.value.trim() || "",
      phone: document.getElementById("addrPhone")?.value.trim() || "",
      line1: document.getElementById("addrLine1")?.value.trim() || "",
      line2: document.getElementById("addrLine2")?.value.trim() || "",
      city: document.getElementById("addrCity")?.value.trim() || "",
      state: document.getElementById("addrState")?.value.trim() || "",
      pin: document.getElementById("addrPin")?.value.trim() || "",
      is_default: !!document.getElementById("addrIsDefault")?.checked,
    };
    if (!hasAddressApi) {
      if (rawId > 0) {
        const a = addresses.find(x => x.id === rawId);
        if (a) Object.assign(a, { type: payload.type, name: payload.name, phone: payload.phone, line1: payload.line1, line2: payload.line2, city: payload.city, state: payload.state, pin: payload.pin, isDefault: payload.is_default });
        if (payload.is_default) addresses.forEach(x => { x.isDefault = x.id === rawId; });
      } else {
        addresses.push({ id: Date.now(), type: payload.type, name: payload.name, phone: payload.phone, line1: payload.line1, line2: payload.line2, city: payload.city, state: payload.state, pin: payload.pin, isDefault: payload.is_default || addresses.length === 0 });
        if (payload.is_default) addresses.forEach(x => { x.isDefault = x.id === addresses[addresses.length - 1].id; });
      }
      closeAddressModal();
      renderAddresses();
      showToast("Address saved.");
      return;
    }
    const url = window.__API_ADDRESS_SAVE__;
    if (btn) btn.disabled = true;
    try {
      const r = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
        credentials: "same-origin",
      });
      const j = await r.json().catch(() => ({}));
      if (!j.ok) {
        showToast("❌ " + (j.message || "Could not save address."));
        return;
      }
      if (window.__CHECKOUT_ADDRESS_RELOAD__) {
        closeAddressModal();
        showToast("Address saved.");
        setTimeout(() => { window.location.reload(); }, 400);
        return;
      }
      applyAddressesFromResponse(j);
      closeAddressModal();
      showToast("Address saved.");
    } catch (_e) {
      showToast("❌ Network error.");
    } finally {
      if (btn) btn.disabled = false;
    }
  };

  document.getElementById("addressModal")?.addEventListener("click", e => { if (e.target === e.currentTarget) closeAddressModal(); });
  document.getElementById("changePasswordModal")?.addEventListener("click", e => { if (e.target === e.currentTarget) closeChangePasswordModal(); });

  // ---- Wishlist ----
  const FALLBACK_WISHLIST = [
    { id: 1, name: "AirMax Pro 2026", emoji: "👟", price: 8999, orig: 14500 },
    { id: 2, name: "Sony WH-1000XM5", emoji: "🎧", price: 18999, orig: 34990 },
    { id: 3, name: "Apple Watch SE", emoji: "⌚", price: 19500, orig: 29900 },
    { id: 4, name: "Linen Co-ord Set", emoji: "👗", price: 3299, orig: 5500 },
    { id: 5, name: "LED Desk Lamp", emoji: "💡", price: 1599, orig: 2800 },
    { id: 6, name: "Retinol Serum Kit", emoji: "🧴", price: 1899, orig: 3500 },
    { id: 7, name: "Smart Coffee Maker", emoji: "☕", price: 5499, orig: 8999 },
    { id: 8, name: "Vitamin C Gummies", emoji: "🍊", price: 699, orig: 1200 },
  ];
  const serverWishlistSeed = (typeof window.__WISHLIST__ !== "undefined" && Array.isArray(window.__WISHLIST__) && window.__WISHLIST__.length)
    ? window.__WISHLIST__
    : FALLBACK_WISHLIST;
  let profileWishlistItems = (() => {
    const stored = luxeWishlistGetItems();
    if (stored !== null) return stored;
    return serverWishlistSeed.map(w => ({
      id: w.id,
      name: w.name,
      emoji: w.emoji,
      price: w.price,
      orig: w.orig,
    }));
  })();
  function persistProfileWishlist() {
    luxeWishlistSetItems(profileWishlistItems);
  }
  let wishlistGridDelegateAttached = false;
  function wishlistEscapeHtml(str) {
    return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/"/g, "&quot;");
  }
  function removeProfileWishlistItem(productId) {
    const id = Number(productId);
    const next = profileWishlistItems.filter(w => Number(w.id) !== id);
    if (next.length === profileWishlistItems.length) return;
    profileWishlistItems = next;
    persistProfileWishlist();
    showToast("Removed from wishlist");
    renderWishlist();
  }
  function syncProfileWishlistStat() {
    const n = profileWishlistItems.length;
    const el = document.getElementById("profileStatWishlist");
    if (el) el.textContent = String(n);
    const chip = document.getElementById("wishlistCountPill");
    if (chip) chip.textContent = String(n);
  }
  function renderWishlist() {
    const grid = document.getElementById("wishlistGrid");
    if (!grid) return;
    if (!wishlistGridDelegateAttached) {
      wishlistGridDelegateAttached = true;
      grid.addEventListener("click", function(e) {
        const rm = e.target.closest(".wi-rm-btn");
        if (rm) {
          const pid = parseInt(rm.getAttribute("data-wishlist-id"), 10);
          if (Number.isNaN(pid)) return;
          e.preventDefault();
          removeProfileWishlistItem(pid);
          return;
        }
        const add = e.target.closest(".wi-cart-btn");
        if (add) {
          e.preventDefault();
          const row = add.closest(".wishlist-item");
          const nameEl = row && row.querySelector(".wi-name");
          showToast("🛒 " + (nameEl ? nameEl.textContent.trim() : "Item") + " added to cart!");
        }
      });
    }
    if (profileWishlistItems.length === 0) {
      grid.innerHTML = `<div class="wishlist-empty wishlist-empty--premium">
        <div class="wishlist-empty__glow" aria-hidden="true"></div>
        <span class="wishlist-empty__mark" aria-hidden="true">✦</span>
        <h3 class="wishlist-empty__title">Your wishlist is ready</h3>
        <p class="wishlist-empty__text">Heart items on trending or product pages — they sync here instantly.</p>
        <a href="index.php" class="wishlist-empty__cta">Discover products</a>
      </div>`;
      refreshCursorTargets();
      syncProfileWishlistStat();
      return;
    }
    grid.innerHTML = profileWishlistItems.map(w => {
      const nameEsc = wishlistEscapeHtml(w.name);
      const price = Number(w.price);
      const orig = Number(w.orig);
      const showStrike = orig > price && orig > 0;
      const pct = showStrike ? Math.round((1 - price / orig) * 100) : 0;
      const badge = pct > 0 ? `<span class="wi-save-badge">${pct}% off</span>` : "";
      const origHtml = showStrike ? `<span class="wi-orig">₹${orig.toLocaleString("en-IN")}</span>` : "";
      const pUrl = luxeProductUrl(w.id);
      return `<article class="wishlist-item wi-card">
        <a href="${pUrl}" class="wi-media">${badge}<span class="wi-emoji" aria-hidden="true">${wishlistEscapeHtml(w.emoji)}</span></a>
        <div class="wi-body">
          <a href="${pUrl}" class="wi-name">${nameEsc}</a>
          <div class="wi-pricing"><span class="wi-price">₹${price.toLocaleString("en-IN")}</span>${origHtml}</div>
          <div class="wi-actions">
            <a href="${pUrl}" class="wi-view-link">Details</a>
            <button type="button" class="wi-cart-btn">Add to bag</button>
            <button type="button" class="wi-rm-btn" data-wishlist-id="${w.id}" aria-label="Remove from wishlist">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            </button>
          </div>
        </div>
      </article>`;
    }).join("");
    refreshCursorTargets();
    syncProfileWishlistStat();
  }

  // ---- Rewards ----
  function luxeLoyaltyUiFromBalance(balance) {
    const cfg = window.__LOYALTY__;
    const g = typeof cfg?.goldAt === "number" ? cfg.goldAt : 2000;
    const p = typeof cfg?.platinumAt === "number" ? cfg.platinumAt : 3000;
    let tierTitle; let leadHtml; let pct;
    if (balance >= p) {
      tierTitle = "LUXE Platinum Member";
      leadHtml = "You've reached <strong>Platinum</strong> — enjoy exclusive perks! 🏆";
      pct = 100;
    } else if (balance >= g) {
      tierTitle = "LUXE Gold Member";
      const away = p - balance;
      leadHtml = `You're <strong>${away.toLocaleString("en-IN")} points</strong> away from Platinum status! 🏆`;
      pct = Math.min(100, ((balance - g) / (p - g)) * 100);
    } else {
      tierTitle = "LUXE Member";
      const away = g - balance;
      leadHtml = `You're <strong>${away.toLocaleString("en-IN")} points</strong> away from Gold status! 🏆`;
      pct = g > 0 ? Math.min(100, (balance / g) * 100) : 0;
    }
    return { tierTitle, leadHtml, pct };
  }

  function luxeApplyLoyaltyUi(balance) {
    const ui = luxeLoyaltyUiFromBalance(balance);
    const circle = document.getElementById("rewardsPtsCircle");
    const tierEl = document.getElementById("rewardsTierTitle");
    const leadEl = document.getElementById("rewardsLeadLine");
    const fill = document.getElementById("rewardsProgressFill");
    const stat = document.getElementById("profileStatPoints");
    const redeemIn = document.getElementById("redeemInput");
    if (circle) circle.textContent = balance.toLocaleString("en-IN");
    if (tierEl) tierEl.textContent = ui.tierTitle;
    if (leadEl) leadEl.innerHTML = ui.leadHtml;
    if (fill) fill.style.width = `${Math.round(ui.pct * 10) / 10}%`;
    if (stat) stat.textContent = balance.toLocaleString("en-IN");
    if (redeemIn) {
      redeemIn.max = String(Math.max(100, balance));
      if (balance < 100) redeemIn.value = "";
    }
    if (window.__LOYALTY__) window.__LOYALTY__.balance = balance;
  }

  function renderRewards() {
    const list = document.getElementById("rewardsHistory");
    if (!list) return;
    const rows = Array.isArray(window.__LOYALTY__?.history) ? window.__LOYALTY__.history : [];
    if (rows.length === 0) {
      list.innerHTML = "<div class=\"rh-item\"><div><div class=\"rh-desc\">No points activity yet. Delivered orders earn points after the credit period.</div></div></div>";
      return;
    }
    list.innerHTML = rows.map(r => {
      const desc = luxeEscapeHtml(r.desc || "");
      const date = luxeEscapeHtml(r.date || "");
      const pts = luxeEscapeHtml(r.pts || "");
      const t = (r.type === "pending" ? "pending" : r.type === "spend" ? "spend" : "earn");
      return `<div class="rh-item"><div><div class="rh-desc">${desc}</div><div class="rh-date">${date}</div></div><span class="rh-pts ${t}">${pts} pts</span></div>`;
    }).join("");
  }
  window.redeemPoints = async function() {
    const input = document.getElementById("redeemInput");
    const pts = parseInt(input && input.value, 10);
    const bal = typeof window.__LOYALTY__?.balance === "number" ? window.__LOYALTY__.balance : 0;
    if (!pts || pts < 100) { showToast("⚠️ Minimum 100 points required!"); return; }
    if (pts % 100 !== 0) { showToast("⚠️ Redeem in multiples of 100."); return; }
    if (pts > bal) { showToast("⚠️ Not enough points."); return; }
    const url = typeof window.__API_REDEEM_LOYALTY__ === "string" ? window.__API_REDEEM_LOYALTY__ : "actions/redeem-loyalty-points.php";
    try {
      const r = await fetch(url, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ points: pts })
      });
      const j = await r.json().catch(() => ({}));
      if (!j.ok) {
        showToast("❌ " + (j.message || "Could not redeem."));
        return;
      }
      const newBal = typeof j.balance === "number" ? j.balance : bal - pts;
      luxeApplyLoyaltyUi(newBal);
      showToast(j.message || `🎉 ${pts} points redeemed!`);
      if (input) input.value = "";
    } catch (_e) {
      showToast("❌ Network error. Try again.");
    }
  };

  // ---- Settings Toggles ----
  if (profileForm) {
    document.querySelectorAll(".toggle input").forEach(toggle => {
      toggle.addEventListener("change", function() {
        const label = this.closest(".setting-item").querySelector("strong").textContent;
        showToast(`${this.checked ? "✅" : "🔕"} ${label} ${this.checked ? "enabled" : "disabled"}`);
      });
    });

    const delBtn = document.getElementById("deleteAccountBtn");
    if (delBtn && !delBtn.disabled && !delBtn.dataset.pendingDeletion) {
      delBtn.addEventListener("click", async () => {
        if (!confirm("Request account deletion? Your account will be removed within 48 hours after this request is submitted.")) return;
        const url = typeof window.__API_ACCOUNT_DELETE__ === "string"
          ? window.__API_ACCOUNT_DELETE__
          : "actions/request-account-deletion.php";
        try {
          delBtn.disabled = true;
          const r = await fetch(url, { method: "POST", credentials: "same-origin" });
          const j = await r.json().catch(() => ({}));
          if (!j.ok) {
            delBtn.disabled = false;
            showToast("❌ " + (j.message || "Could not submit request."));
            return;
          }
          showToast(j.message || "Request submitted. Account will be deleted within 48 hours.");
          setTimeout(() => { window.location.reload(); }, 1200);
        } catch (_e) {
          delBtn.disabled = false;
          showToast("❌ Network error. Try again.");
        }
      });
    }
  }

  // ---- Init ----
  document.addEventListener("DOMContentLoaded", () => {
    renderAddresses();
    if (profileForm) {
      const allowedProfileTabs = ["personal", "addresses", "wishlist", "reviews", "rewards", "settings"];
      const params = new URLSearchParams(window.location.search);
      let deepTab = params.get("tab");
      if (!deepTab) {
        const h = (window.location.hash || "").replace(/^#/, "").toLowerCase();
        if (h === "wishlist" || h === "tab-wishlist") deepTab = "wishlist";
        if (h === "reviews" || h === "tab-reviews") deepTab = "reviews";
      }
      if (deepTab && allowedProfileTabs.includes(deepTab)) {
        const tabBtn = document.querySelector('.smenu-item[data-tab="' + deepTab + '"]');
        if (tabBtn) switchTab(tabBtn);
      }
      renderWishlist();
      renderRewards();
      refreshCursorTargets();
      setTimeout(() => {
        const bar = document.getElementById("rewardsProgressFill") || document.querySelector(".points-fill");
        if (bar) { const w = bar.style.width; bar.style.width = "0"; setTimeout(() => { bar.style.width = w; }, 100); }
      }, 300);
    } else {
      refreshCursorTargets();
    }
  });
})();


// ================================================================
//  GLOBAL INIT
// ================================================================
document.addEventListener("DOMContentLoaded", () => {
  initThemeToggle();
  refreshCursorTargets();
  observeAll();
});
