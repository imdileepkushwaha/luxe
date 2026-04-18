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
  <title>LUXE — Premium Shopping Experience</title>
  <meta name="description" content="Discover curated luxury fashion, electronics & lifestyle products at LUXE. Free shipping on orders over ₹999. Shop the latest trends today." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body>

  <!-- Cursor Dot -->
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>

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

  <!-- Navbar -->
  <nav class="navbar" id="navbar">
    <div class="nav-container">
      <a href="index.php" class="nav-logo">LUXE</a>
      <ul class="nav-links">
        <li><a href="#collections">Collections</a></li>
        <li><a href="#trending">Trending</a></li>
        <li><a href="#deals">Deals</a></li>
        <li><a href="#brands">Brands</a></li>
      </ul>
      <div class="nav-actions">
        <button class="icon-btn" id="searchBtn" aria-label="Search">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        </button>
        <button class="icon-btn" aria-label="Wishlist">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        </button>
        <?php if ($user): ?>
        <a href="actions/logout.php" class="nav-login-btn" aria-label="Logout">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout
        </a>
        <?php else: ?>
        <a href="login.php" class="nav-login-btn" aria-label="Login">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Sign In
        </a>
        <?php endif; ?>
        <?php if ($user): ?>
        <a href="profile.php" class="icon-btn" aria-label="Profile">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </a>
        <?php endif; ?>
        <a href="cart.php" class="cart-btn" aria-label="Cart">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          <span class="cart-count" id="cartCount"><?= (int) $cartNavCount ?></span>
        </a>
      </div>
    </div>
  </nav>

  <!-- Search Overlay -->
  <div class="search-overlay" id="searchOverlay">
    <button class="search-close" id="searchClose">✕</button>
    <div class="search-inner">
      <h2>What are you looking for?</h2>
      <div class="search-box">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" id="searchInput" placeholder="Search products, brands..." />
      </div>
      <div class="search-tags">
        <span class="tag">👟 Sneakers</span>
        <span class="tag">👜 Bags</span>
        <span class="tag">⌚ Watches</span>
        <span class="tag">💻 Laptops</span>
        <span class="tag">🧴 Skincare</span>
      </div>
    </div>
  </div>

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
            <a href="#" class="col-link">Shop Now →</a>
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
            <a href="#" class="col-link">Shop Now →</a>
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
            <a href="#" class="col-link">Shop Now →</a>
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
            <a href="#" class="col-link">Shop Now →</a>
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
            <a href="#" class="col-link">Shop Now →</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Trending Products -->
  <section class="trending" id="trending">
    <div class="container">
      <div class="section-header">
        <div class="section-badge">🔥 Hot Right Now</div>
        <h2 class="section-title">Trending Products</h2>
        <p class="section-subtitle">Most loved products this week</p>
      </div>
      <div class="filter-tabs">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="fashion">Fashion</button>
        <button class="filter-btn" data-filter="electronics">Electronics</button>
        <button class="filter-btn" data-filter="beauty">Beauty</button>
        <button class="filter-btn" data-filter="home">Home</button>
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
        <div class="deals-visual">
          <div class="deals-card">
            <header class="deals-card__head">
              <span class="deals-card__label">Spotlight picks</span>
              <span class="deals-card__mark" aria-hidden="true">✦</span>
            </header>
            <div class="deals-products">
              <a href="product.php" class="deal-item deal-item-link">
                <span class="deal-emoji" aria-hidden="true">👟</span>
                <div class="deal-item__body">
                  <strong>Nike Air Max 270</strong>
                  <div class="deal-price"><s>₹12,995</s><b>₹5,499</b></div>
                </div>
              </a>
              <a href="product.php" class="deal-item deal-item-link">
                <span class="deal-emoji" aria-hidden="true">🎧</span>
                <div class="deal-item__body">
                  <strong>Sony WH‑1000XM5</strong>
                  <div class="deal-price"><s>₹34,990</s><b>₹18,999</b></div>
                </div>
              </a>
              <a href="product.php" class="deal-item deal-item-link">
                <span class="deal-emoji" aria-hidden="true">⌚</span>
                <div class="deal-item__body">
                  <strong>Apple Watch SE</strong>
                  <div class="deal-price"><s>₹29,900</s><b>₹19,500</b></div>
                </div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Brands Section -->
  <section class="brands" id="brands">
    <div class="container">
      <div class="section-header">
        <div class="section-badge">Our Partners</div>
        <h2 class="section-title">Top Brands</h2>
      </div>
      <div class="brands-row">
        <div class="brand-logo">Nike</div>
        <div class="brand-logo">Apple</div>
        <div class="brand-logo">Samsung</div>
        <div class="brand-logo">Adidas</div>
        <div class="brand-logo">Sony</div>
        <div class="brand-logo">Puma</div>
        <div class="brand-logo">Zara</div>
        <div class="brand-logo">H&M</div>
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

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="footer-top">
        <div class="footer-brand">
          <span class="footer-logo">LUXE</span>
          <p>Your premium destination for fashion, tech & lifestyle. Shop with confidence.</p>
          <div class="social-links">
            <a href="#" class="social-link" aria-label="Instagram">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8.59916 0.656738C9.43179 0.658114 9.85373 0.662524 10.2183 0.673378L10.3619 0.678068C10.5278 0.683965 10.6915 0.691364 10.8888 0.700612C11.6761 0.736991 12.2131 0.861531 12.6848 1.04465C13.1724 1.2327 13.5843 1.48671 13.9955 1.89795C14.4061 2.30919 14.6602 2.72227 14.8489 3.20873C15.0313 3.67977 15.1559 4.21741 15.1929 5.00473C15.2017 5.20203 15.2088 5.36569 15.2147 5.53159L15.2193 5.67519C15.2301 6.03975 15.2351 6.46176 15.2366 7.29442L15.2372 7.84606C15.2373 7.91346 15.2373 7.98301 15.2373 8.05477L15.2372 8.26349L15.2368 8.8152C15.2354 9.64783 15.231 10.0699 15.2201 10.4344L15.2154 10.578C15.2095 10.7439 15.2022 10.9076 15.1929 11.1048C15.1565 11.8922 15.0313 12.4292 14.8489 12.9008C14.6608 13.3886 14.4061 13.8004 13.9955 14.2116C13.5843 14.6223 13.1706 14.8763 12.6848 15.0649C12.2131 15.2474 11.6761 15.372 10.8888 15.409C10.6915 15.4178 10.5278 15.4249 10.3619 15.4307L10.2183 15.4354C9.85373 15.4462 9.43179 15.4511 8.59916 15.4528L8.04745 15.4534C7.98005 15.4534 7.9105 15.4534 7.83874 15.4534H7.63002L7.07831 15.4528C6.24568 15.4515 5.82368 15.4471 5.45912 15.4362L5.31552 15.4315C5.14961 15.4256 4.98596 15.4182 4.78867 15.409C4.00133 15.3726 3.46494 15.2474 2.99267 15.0649C2.50559 14.8769 2.09312 14.6223 1.68189 14.2116C1.27065 13.8004 1.01725 13.3867 0.828588 12.9008C0.645474 12.4292 0.521548 11.8922 0.484555 11.1048C0.475766 10.9076 0.468596 10.7439 0.462788 10.578L0.458135 10.4344C0.447311 10.0699 0.442376 9.64783 0.440778 8.8152L0.440681 7.29442C0.442058 6.46176 0.44646 6.03975 0.457313 5.67519L0.462012 5.53159C0.467908 5.36569 0.475307 5.20203 0.484555 5.00473C0.520926 4.21679 0.645474 3.68039 0.828588 3.20873C1.01663 2.72166 1.27065 2.30919 1.68189 1.89795C2.09312 1.48671 2.50621 1.23331 2.99267 1.04465C3.46432 0.861531 4.00072 0.737605 4.78867 0.700612C4.98596 0.69183 5.14961 0.684661 5.31552 0.678853L5.45912 0.674199C5.82368 0.663367 6.24568 0.658433 7.07831 0.656834L8.59916 0.656738ZM7.83874 4.35551C5.79457 4.35551 4.13944 6.01244 4.13944 8.05477C4.13944 10.0989 5.79637 11.7541 7.83874 11.7541C9.88288 11.7541 11.538 10.0972 11.538 8.05477C11.538 6.01064 9.88103 4.35551 7.83874 4.35551ZM7.83874 5.83522C9.0646 5.83522 10.0583 6.82861 10.0583 8.05477C10.0583 9.28064 9.0649 10.2743 7.83874 10.2743C6.61287 10.2743 5.61915 9.28101 5.61915 8.05477C5.61915 6.8289 6.6125 5.83522 7.83874 5.83522ZM11.723 3.24572C11.213 3.24572 10.7982 3.65998 10.7982 4.16991C10.7982 4.67986 11.2124 5.09475 11.723 5.09475C12.2329 5.09475 12.6478 4.68051 12.6478 4.16991C12.6478 3.65998 12.2322 3.24509 11.723 3.24572Z" fill="currentColor"></path></svg>
            </a>
            <a href="#" class="social-link" aria-label="Facebook">
            <svg xmlns="http://www.w3.org/2000/svg" width="8" height="16" viewBox="0 0 8 16" fill="none"><path d="M5.33899 9.16479H7.21792L7.9695 6.20547H5.33899V4.72581C5.33899 3.96424 5.33899 3.24615 6.84214 3.24615H7.9695V0.760389C7.72471 0.728391 6.7993 0.656738 5.82217 0.656738C3.78203 0.656738 2.33269 1.88253 2.33269 4.13373V6.20547H0.0779705V9.16479H2.33269V15.4534H5.33899V9.16479Z" fill="currentColor"></path></svg>
            </a>
            <a href="#" class="social-link" aria-label="YouTube">
            <svg xmlns="http://www.w3.org/2000/svg" width="19" height="16" viewBox="0 0 19 16" fill="none"><path d="M9.51528 0.656738C9.98947 0.659457 11.1759 0.671406 12.4364 0.724002L12.8834 0.744302C14.1525 0.806895 15.4206 0.913825 16.0496 1.0965C16.8885 1.342 17.5479 2.05833 17.7707 2.96636C18.1256 4.40827 18.17 7.22255 18.1755 7.9036L18.1763 8.04473V8.05463C18.1763 8.05463 18.1763 8.05805 18.1763 8.06462L18.1755 8.20575C18.17 8.8868 18.1256 11.7011 17.7707 13.143C17.5448 14.0543 16.8854 14.7707 16.0496 15.0128C15.4206 15.1955 14.1525 15.3024 12.8834 15.365L12.4364 15.3853C11.1759 15.4379 9.98947 15.4498 9.51528 15.4526L9.30717 15.4534H9.29793C9.29793 15.4534 9.29483 15.4534 9.2887 15.4534L9.08077 15.4526C8.07716 15.4469 3.8809 15.3996 2.5463 15.0128C1.70737 14.7673 1.04799 14.051 0.825102 13.143C0.470261 11.7011 0.425904 8.8868 0.420364 8.20575V7.9036C0.425904 7.22255 0.470261 4.40827 0.825102 2.96636C1.05108 2.05497 1.71046 1.33864 2.5463 1.0965C3.8809 0.709667 8.07716 0.662491 9.08077 0.656738H9.51528ZM7.52227 4.81772V11.2916L12.8493 8.05463L7.52227 4.81772Z" fill="currentColor"></path></svg>
            </a>
          </div>
        </div>
        <div class="footer-col">
          <h4>Shop</h4>
          <ul>
            <li><a href="product.php">New Arrivals</a></li>
            <li><a href="product.php">Best Sellers</a></li>
            <li><a href="#deals">Sale & Offers</a></li>
            <li><a href="#">Gift Cards</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Support</h4>
          <ul>
            <li><a href="#">Track Order</a></li>
            <li><a href="#">Returns</a></li>
            <li><a href="#">FAQ</a></li>
            <li><a href="#">Contact Us</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h4>Company</h4>
          <ul>
            <li><a href="#">About LUXE</a></li>
            <li><a href="login.php">Sign In / Register</a></li>
            <li><a href="#">Blog</a></li>
            <li><a href="#">Press</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>© 2026 LUXE. All rights reserved.</p>
        <div class="payment-icons">
          <span class="pay-icon">
            <img src="images/payment/1.svg" alt="Payment Icon" >
          </span>
          <span class="pay-icon">
            <img src="images/payment/2.svg" alt="Payment Icon" >
          </span>
          <span class="pay-icon">
            <img src="images/payment/3.svg" alt="Payment Icon" >
          </span>
          <span class="pay-icon">
            <img src="images/payment/4.svg" alt="Payment Icon" >
          </span>
          <span class="pay-icon">
            <img src="images/payment/5.svg" alt="Payment Icon" >
          </span>
          <span class="pay-icon">
            <img src="images/payment/6.svg" alt="Payment Icon" >
          </span>
          <span class="pay-icon">
            <img src="images/payment/7.svg" alt="Payment Icon" >
          </span>
        </div>
      </div>
    </div>
  </footer>

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
    ], JSON_THROW_ON_ERROR) ?>;
    window.__PRODUCTS__ = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
