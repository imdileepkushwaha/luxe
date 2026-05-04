<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pdo = db();
$products = products_fetch_all($pdo);
$user = auth_user($pdo);
$cartNavCount = 0;
foreach ($_SESSION['cart'] ?? [] as $ci) {
    $cartNavCount += (int) ($ci['qty'] ?? 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>LUXE — Premium Shopping Experience</title>
  <meta name="description" content="Discover curated luxury fashion, electronics & lifestyle products at LUXE. Free shipping on orders over ₹999. Shop the latest trends today." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body class="index-page">

  <!-- Loader -->
  <div class="loader" id="loader" role="status" aria-live="polite" aria-busy="true" aria-label="Loading page">
    <div class="loader-backdrop" aria-hidden="true"></div>
    <div class="loader-inner">
      <div class="loader-brand">
        <div class="loader-orbit" aria-hidden="true"></div>
        <span class="loader-logo">LUXE</span>
      </div>
      <p class="loader-tagline">Premium shopping experience</p>
      <div class="loader-bar" aria-hidden="true">
        <div class="loader-fill"></div>
        <div class="loader-shine"></div>
      </div>
    </div>
  </div>

  <?php
  $header = [
      'user' => $user,
      'cart_count' => $cartNavCount,
      'top_text' => 'New arrivals every week',
      'top_highlight' => 'Free shipping above ₹999',
      'top_links' => [
          ['label' => "Today's Deals", 'href' => '#deals'],
          ['label' => 'Top Brands', 'href' => '#brands'],
      ],
      'menu_links' => [
          ['label' => 'Home', 'href' => 'index.php'],
          ['label' => 'Shop', 'href' => 'product-list.php'],
          ['label' => 'Collections', 'href' => '#collections'],
          ['label' => 'Trending', 'href' => '#trending'],
          ['label' => 'Deals', 'href' => '#deals'],
          ['label' => 'Brands', 'href' => '#brands'],
      ],
      'wishlist_href' => $user
          ? 'profile.php?tab=wishlist'
          : 'login.php?redirect=' . rawurlencode('profile.php?tab=wishlist'),
      'search_lead' => 'Search by product name, brand, or category — matches show below; the trending section updates too.',
  ];
  require __DIR__ . '/includes/user_header.php';
  ?>

  <!-- Hero Section -->
  <section class="hero" id="hero">
    <div class="hero-bg">
      <div class="blob blob-1"></div>
      <div class="blob blob-2"></div>
      <div class="blob blob-3"></div>
      <div class="grid-lines"></div>
    </div>
    <div class="hero-content">
      <div class="hero-badge animate-fade-up">✦ New Season 2026 Has Arrived</div>
      <h1 class="hero-title animate-fade-up delay-1">
        Shop Smarter.<br />
        <em>Live Better.</em>
      </h1>
      <p class="hero-subtitle animate-fade-up delay-2">
        Curated luxury for the modern lifestyle — fashion, tech & beyond. <br />
        Free delivery on every order.
      </p>
      <div class="hero-actions animate-fade-up delay-3">
        <a href="#collections" class="btn-primary">Explore Collection <span class="btn-arrow">→</span></a>
        <a href="#deals" class="btn-ghost">Today's Deals</a>
      </div>
      <div class="hero-stats animate-fade-up delay-4">
        <div class="stat"><span class="stat-num">50K+</span><span class="stat-label">Products</span></div>
        <div class="stat-divider"></div>
        <div class="stat"><span class="stat-num">2M+</span><span class="stat-label">Happy Buyers</span></div>
        <div class="stat-divider"></div>
        <div class="stat"><span class="stat-num">4.9★</span><span class="stat-label">Rating</span></div>
      </div>
    </div>
    <div class="hero-visual animate-slide-right delay-2">
      <div class="product-showcase">
        <a href="product.php" class="showcase-card card-main showcase-link">
          <div class="card-glow"></div>
          <div class="product-img-wrap">
            <div class="product-img-placeholder main-product">
              <div class="emoji-product">👟</div>
              <div class="product-shine"></div>
            </div>
          </div>
          <div class="showcase-info">
            <span class="showcase-tag">Best Seller</span>
            <h3>AeroMax Pro 2026</h3>
            <div class="showcase-price">
              <span class="price-new">₹8,999</span>
              <span class="price-old">₹14,500</span>
            </div>
            <span class="showcase-view-btn">View Details →</span>
          </div>
        </a>
        <a href="product.php" class="showcase-card card-mini card-mini-1">
          <div class="mini-emoji">⌚</div>
          <span>LuxWatch X</span>
          <strong>₹24,999</strong>
        </a>
        <a href="product.php" class="showcase-card card-mini card-mini-2">
          <div class="mini-emoji">💻</div>
          <span>UltraBook Air</span>
          <strong>₹69,999</strong>
        </a>
        <div class="floating-badge badge-discount">38% OFF</div>
        <div class="floating-badge badge-free">Free Ship</div>
      </div>
    </div>
    <div class="scroll-indicator">
      <div class="scroll-dot"></div>
      <span>Scroll Down</span>
    </div>
  </section>

  <!-- Category Strip -->
  <section class="categories-strip">
    <div class="container">
      <div class="strip-track" id="stripTrack">
        <div class="strip-item">👗 Fashion</div>
        <div class="strip-item">📱 Electronics</div>
        <div class="strip-item">🏠 Home & Living</div>
        <div class="strip-item">🌿 Beauty & Wellness</div>
        <div class="strip-item">👟 Footwear</div>
        <div class="strip-item">⌚ Watches</div>
        <div class="strip-item">📚 Books & More</div>
        <div class="strip-item">🎮 Gaming</div>
        <div class="strip-item">🍳 Kitchen</div>
        <!-- duplicate for loop -->
        <div class="strip-item">👗 Fashion</div>
        <div class="strip-item">📱 Electronics</div>
        <div class="strip-item">🏠 Home & Living</div>
        <div class="strip-item">🌿 Beauty & Wellness</div>
        <div class="strip-item">👟 Footwear</div>
        <div class="strip-item">⌚ Watches</div>
        <div class="strip-item">📚 Books & More</div>
        <div class="strip-item">🎮 Gaming</div>
        <div class="strip-item">🍳 Kitchen</div>
      </div>
    </div>
  </section>

  <!-- Cyber Monday promo (HTML/CSS build) -->
  <section class="cyber-promo" aria-labelledby="cyberPromoTitle">
    <div class="container">
      <div class="cyber-promo__card">
        <div class="cyber-promo__bg" aria-hidden="true">
          <span class="cyber-promo__blob cyber-promo__blob--peach"></span>
          <span class="cyber-promo__blob cyber-promo__blob--violet"></span>
          <span class="cyber-promo__noise"></span>
        </div>
        <div class="cyber-promo__row">
          <div class="cyber-promo__visual cyber-promo__visual--left" aria-hidden="true">
            <div class="cyber-promo__product cyber-promo__product--phones">
              <span class="cyber-promo__emoji">🎧</span>
            </div>
          </div>
          <div class="cyber-promo__copy">
            <h2 id="cyberPromoTitle" class="cyber-promo__title">Cyber Monday Sell</h2>
            <p class="cyber-promo__discount">
              <span class="cyber-promo__discount-muted">UP TO</span>
              <span class="cyber-promo__discount-num">30%</span>
              <span class="cyber-promo__discount-muted">OFF</span>
            </p>
            <p class="cyber-promo__cats">Computer &amp; mobile accessories</p>
            <a href="#deals" class="cyber-promo__cta">Shop Now <span class="cyber-promo__cta-plus" aria-hidden="true">+</span></a>
          </div>
          <div class="cyber-promo__visual cyber-promo__visual--right" aria-hidden="true">
            <div class="cyber-promo__product cyber-promo__product--tablet">
              <span class="cyber-promo__tablet-screen"></span>
              <span class="cyber-promo__tablet-kb"></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Collections -->
  <section class="collections" id="collections">
    <div class="container">
      <div class="section-header">
        <div class="section-badge">Our Collections</div>
        <h2 class="section-title">Shop by Category</h2>
        <p class="section-subtitle">Explore hand‑picked collections crafted for every taste.</p>
      </div>
      <div class="collections-grid">
        <div class="collection-card large">
          <div class="col-bg">
            <div class="col-emoji">👗</div>
          </div>
          <div class="col-content">
            <div>
            <h3>Fashion</h3>
            <p>2,400+ Styles</p>
            </div>
            <a href="product-list.php?category=fashion" class="col-link">Shop Now →</a>
          </div>
        </div>
        <div class="collection-card">
          <div class="col-bg">
            <div class="col-emoji">📱</div>
          </div>
          <div class="col-content">
           <div>
           <h3>Electronics</h3>
           <p>1,800+ Items</p>
           </div>
            <a href="product-list.php?category=electronics" class="col-link">Shop Now →</a>
          </div>
        </div>
        <div class="collection-card">
          <div class="col-bg">
            <div class="col-emoji">🌿</div>
          </div>
          <div class="col-content">
           <div>
           <h3>Beauty</h3>
           <p>900+ Products</p>
           </div>
            <a href="product-list.php?category=beauty" class="col-link">Shop Now →</a>
          </div>
        </div>
        <div class="collection-card">
          <div class="col-bg">
            <div class="col-emoji">🏠</div>
          </div>
          <div class="col-content">
           <div>
           <h3>Home & Living</h3>
           <p>3,100+ Items</p>
           </div>
            <a href="product-list.php?category=home" class="col-link">Shop Now →</a>
          </div>
        </div>
        <div class="collection-card">
          <div class="col-bg">
            <div class="col-emoji">🎮</div>
          </div>
          <div class="col-content">
           <div>
           <h3>Gaming</h3>
           <p>650+ Products</p>
           </div>
            <a href="product-list.php?category=electronics" class="col-link">Shop Now →</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Trending Products -->
  <section class="trending" id="trending">
    <div class="trending-bg" aria-hidden="true">
      <div class="trending-bg__blob trending-bg__blob--1"></div>
      <div class="trending-bg__blob trending-bg__blob--2"></div>
      <div class="trending-bg__grid"></div>
    </div>
    <div class="container trending-container">
      <div class="section-header">
        <div class="section-badge">🔥 Hot Right Now</div>
        <h2 class="section-title">Trending Products</h2>
        <p class="section-subtitle">Most loved products this week</p>
      </div>
      <div class="filter-tabs" role="tablist" aria-label="Filter trending by category">
        <button type="button" class="filter-btn active" data-filter="all" role="tab" aria-selected="true">All</button>
        <button type="button" class="filter-btn" data-filter="fashion" role="tab" aria-selected="false">Fashion</button>
        <button type="button" class="filter-btn" data-filter="electronics" role="tab" aria-selected="false">Electronics</button>
        <button type="button" class="filter-btn" data-filter="beauty" role="tab" aria-selected="false">Beauty</button>
        <button type="button" class="filter-btn" data-filter="home" role="tab" aria-selected="false">Home</button>
      </div>
      <div class="products-grid" id="productsGrid">
        <!-- Products rendered by JS -->
      </div>
    </div>
  </section>

  <!-- Flash Deals Banner -->
  <section class="flash-deals" id="deals">
    <div class="deals-bg" aria-hidden="true">
      <div class="deals-blob deals-blob--1"></div>
      <div class="deals-blob deals-blob--2"></div>
      <div class="deals-noise"></div>
    </div>
    <div class="container deals-stage">
      <div class="deals-inner">
        <div class="deals-text">
          <div class="deals-badge">
            <span class="deals-badge__pulse" aria-hidden="true"></span>
            <span class="deals-badge__text">Flash sale</span>
          </div>
          <p class="deals-kicker">Limited window · Curated luxury</p>
          <h2 class="deals-headline">Up to <span class="deals-highlight">70% off</span></h2>
          <p class="deals-lead">Hand-picked drops on icons you love — prices lock when the timer hits zero.</p>
          <p class="countdown-label" id="countdownLabel">Ends in</p>
          <div class="countdown" id="countdown" role="timer" aria-labelledby="countdownLabel">
            <div class="time-block"><span id="hours">08</span><label>Hours</label></div>
            <div class="time-sep" aria-hidden="true">:</div>
            <div class="time-block"><span id="mins">45</span><label>Mins</label></div>
            <div class="time-sep" aria-hidden="true">:</div>
            <div class="time-block"><span id="secs">30</span><label>Secs</label></div>
          </div>
          <a href="product.php" class="btn-primary deals-cta"><span>Shop the edit</span><span class="deals-cta__arrow" aria-hidden="true">→</span></a>
        </div>
      </div>
    </div>
  </section>

  <!-- Brands Section -->
  <section class="brands" id="brands" aria-labelledby="brandsHeading">
    <div class="brands__bg" aria-hidden="true"></div>
    <div class="brands__glow brands__glow--1" aria-hidden="true"></div>
    <div class="brands__glow brands__glow--2" aria-hidden="true"></div>
    <div class="container brands__container">
      <div class="section-header brands__header">
        <div class="section-badge">Our Partners</div>
        <h2 class="section-title" id="brandsHeading">Top Brands</h2>
        <p class="section-subtitle brands__lead">Global names shoppers know and trust — crisp marks, zero clutter.</p>
      </div>
      <div class="brands__grid" role="list">
        <div class="brand-logo" role="listitem">
          <span class="brand-logo__surface" aria-hidden="true"></span>
          <img class="brand-logo__img" src="images/brands/nike.svg" alt="Nike" width="160" height="40" loading="lazy" decoding="async" />
        </div>
        <div class="brand-logo" role="listitem">
          <span class="brand-logo__surface" aria-hidden="true"></span>
          <img class="brand-logo__img" src="images/brands/apple.svg" alt="Apple" width="160" height="40" loading="lazy" decoding="async" />
        </div>
        <div class="brand-logo" role="listitem">
          <span class="brand-logo__surface" aria-hidden="true"></span>
          <img class="brand-logo__img" src="images/brands/samsung.svg" alt="Samsung" width="160" height="40" loading="lazy" decoding="async" />
        </div>
        <div class="brand-logo" role="listitem">
          <span class="brand-logo__surface" aria-hidden="true"></span>
          <img class="brand-logo__img" src="images/brands/adidas.svg" alt="Adidas" width="160" height="40" loading="lazy" decoding="async" />
        </div>
        <div class="brand-logo" role="listitem">
          <span class="brand-logo__surface" aria-hidden="true"></span>
          <img class="brand-logo__img" src="images/brands/sony.svg" alt="Sony" width="160" height="40" loading="lazy" decoding="async" />
        </div>
        <div class="brand-logo" role="listitem">
          <span class="brand-logo__surface" aria-hidden="true"></span>
          <img class="brand-logo__img" src="images/brands/puma.svg" alt="Puma" width="160" height="40" loading="lazy" decoding="async" />
        </div>
        <div class="brand-logo" role="listitem">
          <span class="brand-logo__surface" aria-hidden="true"></span>
          <img class="brand-logo__img" src="images/brands/zara.svg" alt="Zara" width="160" height="40" loading="lazy" decoding="async" />
        </div>
        <div class="brand-logo" role="listitem">
          <span class="brand-logo__surface" aria-hidden="true"></span>
          <img class="brand-logo__img" src="images/brands/hm.svg" alt="H&amp;M" width="160" height="40" loading="lazy" decoding="async" />
        </div>
      </div>
    </div>
  </section>

  <!-- Features Strip (light two-tone cards, reference layout) -->
  <section class="features-strip" aria-label="Why shop with LUXE">
    <div class="container features-strip__container">
      <div class="features-grid">
        <article class="feature-item feature-card feature-card--orange">
          <div class="feature-card__inner">
            <div class="feature-icon-wrap" aria-hidden="true">
              <img src="images/feature-icon_1.svg" alt="Return & refund" >
            </div>
            <div class="feature-panel">
              <strong class="feature-title">Return &amp; refund</strong>
              <span class="feature-desc">Money back guarantee</span>
            </div>
          </div>
        </article>
        <article class="feature-item feature-card feature-card--cyan">
          <div class="feature-card__inner">
            <div class="feature-icon-wrap" aria-hidden="true">
            <img src="images/feature-icon_2.svg" alt="Return & refund" >
            </div>
            <div class="feature-panel">
              <strong class="feature-title">Quality Support</strong>
              <span class="feature-desc">Always online 24/7</span>
            </div>
          </div>
        </article>
        <article class="feature-item feature-card feature-card--lime">
          <div class="feature-card__inner">
            <div class="feature-icon-wrap" aria-hidden="true">
            <img src="images/feature-icon_3.svg" alt="Return & refund" >
            </div>
            <div class="feature-panel">
              <strong class="feature-title">Secure Payment</strong>
              <span class="feature-desc">100% safe checkout</span>
            </div>
          </div>
        </article>
        <article class="feature-item feature-card feature-card--teal">
          <div class="feature-card__inner">
            <div class="feature-icon-wrap" aria-hidden="true">
            <img src="images/feature-icon_4.svg" alt="Return & refund" >
            </div>
            <div class="feature-panel">
              <strong class="feature-title">Daily Offers</strong>
              <span class="feature-desc">20% off by subscribing</span>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- Testimonials -->
  <section class="testimonials">
    <div class="container">
      <div class="section-header">
        <div class="section-badge">Reviews</div>
        <h2 class="section-title">What Our Customers Say</h2>
      </div>
      <div class="testimonials-grid">
        <div class="testimonial-card">
          <div class="stars">★★★★★</div>
          <p>"Absolutely love the quality! Got my order in 2 days and the packaging was premium. Will definitely order again!"</p>
          <div class="reviewer">
            <div class="reviewer-avatar">P</div>
            <div>
              <strong>Priya Sharma</strong>
              <span>Mumbai, MH</span>
            </div>
          </div>
        </div>
        <div class="testimonial-card featured">
          <div class="stars">★★★★★</div>
          <p>"Best online shopping experience ever! The product recommendations were spot on and checkout was super smooth."</p>
          <div class="reviewer">
            <div class="reviewer-avatar">R</div>
            <div>
              <strong>Rahul Verma</strong>
              <span>Delhi, DL</span>
            </div>
          </div>
        </div>
        <div class="testimonial-card">
          <div class="stars">★★★★★</div>
          <p>"Got amazing deals on electronics. The flash sale saved me ₹8000 on my laptop. Can't recommend enough!"</p>
          <div class="reviewer">
            <div class="reviewer-avatar">A</div>
            <div>
              <strong>Anjali Singh</strong>
              <span>Bengaluru, KA</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Newsletter -->
  <section class="newsletter">
    <div class="container">
      <div class="newsletter-inner">
        <div class="nl-bg-blob"></div>
        <div class="nl-content">
          <h2>Stay in the Loop 📬</h2>
          <p>Subscribe for exclusive deals, early access to sales, and curated style tips.</p>
          <form class="nl-form" id="nlForm" onsubmit="handleSubscribe(event)">
            <input type="email" id="nlEmail" placeholder="Enter your email address" required />
            <button type="submit" class="btn-primary">Subscribe</button>
          </form>
          <span class="nl-note">No spam, ever. Unsubscribe anytime.</span>
        </div>
      </div>
    </div>
  </section>

  <?php
  $footer = [
      'deals_href' => '#deals',
      'year' => '2026',
  ];
  require __DIR__ . '/includes/user_footer.php';
  ?>

  <!-- Cart Sidebar -->
  <div class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
      <h3>Your Cart <span id="cartItemCount">(0)</span></h3>
      <button class="cart-close" id="cartClose">✕</button>
    </div>
    <div class="cart-items" id="cartItems">
      <div class="cart-empty">
        <span class="cart-empty-icon">🛒</span>
        <p>Your cart is empty</p>
        <a href="#trending" class="btn-primary" id="shopNowCart">Start Shopping</a>
      </div>
    </div>
    <div class="cart-footer">
      <div class="cart-total">
        <span>Total</span><strong id="cartTotal">₹0</strong>
      </div>
      <button class="btn-primary full-width" id="checkoutBtn" onclick="goCheckout()">Checkout →</button>
    </div>
  </div>
  <div class="cart-overlay" id="cartOverlay"></div>

  <!-- Index offer modal (shown once after welcome loader; dismiss persists) -->
  <div class="offer-popup" id="offerPopup" role="dialog" aria-modal="true" aria-labelledby="offerPopupTitle" aria-describedby="offerPopupDesc" hidden>
    <div class="offer-popup__backdrop" data-offer-dismiss tabindex="-1" aria-hidden="true"></div>
    <div class="offer-popup__dialog">
      <div class="offer-popup__glow" aria-hidden="true"></div>
      <div class="offer-popup__frame">
        <button type="button" class="offer-popup__close" id="offerPopupClose" data-offer-dismiss aria-label="Close offer">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
        <div class="offer-popup__ribbon" aria-hidden="true">
          <span class="offer-popup__ribbon-text">Spring edit</span>
        </div>
        <p class="offer-popup__eyebrow">LUXE members</p>
        <h2 id="offerPopupTitle" class="offer-popup__title">Up to 25% off your first order</h2>
        <p id="offerPopupDesc" class="offer-popup__lead">Curated picks from verified sellers — fashion, beauty, tech &amp; home. Free shipping on orders over ₹999.</p>
        <ul class="offer-popup__perks" role="list">
          <li><span class="offer-popup__perk-icon" aria-hidden="true">✦</span> First-order savings at checkout</li>
          <li><span class="offer-popup__perk-icon" aria-hidden="true">✦</span> Authentic brands &amp; easy returns</li>
          <li><span class="offer-popup__perk-icon" aria-hidden="true">✦</span> New arrivals weekly</li>
        </ul>
        <div class="offer-popup__actions">
          <a href="product-list.php" class="offer-popup__cta">Explore the shop</a>
          <button type="button" class="offer-popup__dismiss" data-offer-dismiss>Maybe later</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div class="toast" id="toast"></div>

  <script>
    window.LUXE_URLS = <?= json_encode([
        'home' => 'index.php',
        'login' => 'login.php',
        'cart' => 'cart.php',
        'product' => 'product.php',
        'orders' => 'orders.php',
        'profile' => 'profile.php',
        'productList' => 'product-list.php',
    ], JSON_THROW_ON_ERROR) ?>;
    window.__PRODUCTS__ = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
