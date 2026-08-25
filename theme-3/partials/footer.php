<?php
declare(strict_types=1);

if (!function_exists('site_contact_bundle')) {
    require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
}

$theme1FooterCategories = $theme1FooterCategories ?? [];
if (!is_array($theme1FooterCategories) || $theme1FooterCategories === []) {
    $theme1FooterCategories = [
        "Men's Fashion",
        "Women's Fashion",
        "Footwear",
        "Beauty & Cosmetics",
        "Sport Wear",
    ];
}

$t2FooterBrand = 'LUXE';
$t2FooterAddress = '37 W 24th St, New York, NY';
$t2FooterPhone = '+0020 500 - CALL - 000';
$t2FooterEmail = 'info@webmail.com';
$t2FooterHours = 'Mon - Fri: 9:00–20:00';
$t2FooterLogo = '';
if (function_exists('site_contact_bundle') && function_exists('db')) {
    try {
        $b = site_contact_bundle(db());
        $t2FooterBrand   = $b['brand']   !== '' ? $b['brand']   : $t2FooterBrand;
        $t2FooterAddress = $b['address'] !== '' ? $b['address'] : $t2FooterAddress;
        $t2FooterPhone   = $b['phone']   !== '' ? $b['phone']   : $t2FooterPhone;
        $t2FooterEmail   = $b['email']   !== '' ? $b['email']   : $t2FooterEmail;
        $t2FooterHours   = $b['hours']   !== '' ? $b['hours']   : $t2FooterHours;
        $t2FooterLogo    = $b['logo'];
    } catch (Throwable $e) {
        /* defaults */
    }
}
$t2FooterLogoUrl = $t2FooterLogo !== '' ? luxe_public_href(ltrim($t2FooterLogo, '/')) : '';
$t2WishlistHref = luxe_public_href('profile.php?tab=wishlist');
?>
<footer class="footer">
    <div class="container footer-top">
        <div class="footer-brand-col">
            <a
                class="footer-brand-logo <?= $t2FooterLogoUrl !== '' ? 'footer-brand-logo--has-img' : '' ?>"
                href="<?= h(luxe_public_href('index.php')) ?>"
            >
                <?php if ($t2FooterLogoUrl !== ''): ?>
                <img src="<?= h($t2FooterLogoUrl) ?>" alt="<?= h($t2FooterBrand) ?>" class="footer-brand-logo-img" />

                <?php else: ?> <?php endif; ?>
            </a>
            <div class="footer-brand-rule" aria-hidden="true"></div>
            <div class="footer-brand-lines">
                <div class="footer-hotline-block">
                    <span class="footer-hotline-icon" aria-hidden="true">
                        <svg
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.6"
                        >
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 2" />
                        </svg>
                    </span>
                    <div class="footer-hotline-text">
                        <span class="footer-hotline-schedule"><?= h($t2FooterHours) ?></span>
                        <strong class="footer-hotline-phone"><?= h($t2FooterPhone) ?></strong>
                    </div>
                </div>
                <div class="footer-hotline-block">
                    <span class="footer-hotline-icon" aria-hidden="true">
                        <svg
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="2" y="4" width="20" height="16" rx="2" />
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                        </svg>
                    </span>
                    <div class="footer-hotline-text">
                        <span class="footer-hotline-schedule">Get Free Support</span>
                        <strong class="footer-hotline-phone"><?= h($t2FooterEmail) ?></strong>
                    </div>
                </div>
            </div>
            
            <div class="footer-social footer-social--round">
                <a href="#" class="footer-social-btn" aria-label="Facebook"
                    ><svg xmlns="http://www.w3.org/2000/svg" width="8" height="16" viewBox="0 0 8 16" fill="none">
                        <path
                            d="M5.33899 9.16479H7.21792L7.9695 6.20547H5.33899V4.72581C5.33899 3.96424 5.33899 3.24615 6.84214 3.24615H7.9695V0.760389C7.72471 0.728391 6.7993 0.656738 5.82217 0.656738C3.78203 0.656738 2.33269 1.88253 2.33269 4.13373V6.20547H0.0779705V9.16479H2.33269V15.4534H5.33899V9.16479Z"
                            fill="currentColor"
                        ></path></svg
                ></a>
                <a href="#" class="footer-social-btn" aria-label="YouTube"
                    ><svg xmlns="http://www.w3.org/2000/svg" width="19" height="16" viewBox="0 0 19 16" fill="none">
                        <path
                            d="M9.51528 0.656738C9.98947 0.659457 11.1759 0.671406 12.4364 0.724002L12.8834 0.744302C14.1525 0.806895 15.4206 0.913825 16.0496 1.0965C16.8885 1.342 17.5479 2.05833 17.7707 2.96636C18.1256 4.40827 18.17 7.22255 18.1755 7.9036L18.1763 8.04473V8.05463C18.1763 8.05463 18.1763 8.05805 18.1763 8.06462L18.1755 8.20575C18.17 8.8868 18.1256 11.7011 17.7707 13.143C17.5448 14.0543 16.8854 14.7707 16.0496 15.0128C15.4206 15.1955 14.1525 15.3024 12.8834 15.365L12.4364 15.3853C11.1759 15.4379 9.98947 15.4498 9.51528 15.4526L9.30717 15.4534H9.29793C9.29793 15.4534 9.29483 15.4534 9.2887 15.4534L9.08077 15.4526C8.07716 15.4469 3.8809 15.3996 2.5463 15.0128C1.70737 14.7673 1.04799 14.051 0.825102 13.143C0.470261 11.7011 0.425904 8.8868 0.420364 8.20575V7.9036C0.425904 7.22255 0.470261 4.40827 0.825102 2.96636C1.05108 2.05497 1.71046 1.33864 2.5463 1.0965C3.8809 0.709667 8.07716 0.662491 9.08077 0.656738H9.51528ZM7.52227 4.81772V11.2916L12.8493 8.05463L7.52227 4.81772Z"
                            fill="currentColor"
                        ></path></svg
                ></a>
                <a href="#" class="footer-social-btn" aria-label="Instagram"
                    ><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <path
                            d="M8.59916 0.656738C9.43179 0.658114 9.85373 0.662524 10.2183 0.673378L10.3619 0.678068C10.5278 0.683965 10.6915 0.691364 10.8888 0.700612C11.6761 0.736991 12.2131 0.861531 12.6848 1.04465C13.1724 1.2327 13.5843 1.48671 13.9955 1.89795C14.4061 2.30919 14.6602 2.72227 14.8489 3.20873C15.0313 3.67977 15.1559 4.21741 15.1929 5.00473C15.2017 5.20203 15.2088 5.36569 15.2147 5.53159L15.2193 5.67519C15.2301 6.03975 15.2351 6.46176 15.2366 7.29442L15.2372 7.84606C15.2373 7.91346 15.2373 7.98301 15.2373 8.05477L15.2372 8.26349L15.2368 8.8152C15.2354 9.64783 15.231 10.0699 15.2201 10.4344L15.2154 10.578C15.2095 10.7439 15.2022 10.9076 15.1929 11.1048C15.1565 11.8922 15.0313 12.4292 14.8489 12.9008C14.6608 13.3886 14.4061 13.8004 13.9955 14.2116C13.5843 14.6223 13.1706 14.8763 12.6848 15.0649C12.2131 15.2474 11.6761 15.372 10.8888 15.409C10.6915 15.4178 10.5278 15.4249 10.3619 15.4307L10.2183 15.4354C9.85373 15.4462 9.43179 15.4511 8.59916 15.4528L8.04745 15.4534C7.98005 15.4534 7.9105 15.4534 7.83874 15.4534H7.63002L7.07831 15.4528C6.24568 15.4515 5.82368 15.4471 5.45912 15.4362L5.31552 15.4315C5.14961 15.4256 4.98596 15.4182 4.78867 15.409C4.00133 15.3726 3.46494 15.2474 2.99267 15.0649C2.50559 14.8769 2.09312 14.6223 1.68189 14.2116C1.27065 13.8004 1.01725 13.3867 0.828588 12.9008C0.645474 12.4292 0.521548 11.8922 0.484555 11.1048C0.475766 10.9076 0.468596 10.7439 0.462788 10.578L0.458135 10.4344C0.447311 10.0699 0.442376 9.64783 0.440778 8.8152L0.440681 7.29442C0.442058 6.46176 0.44646 6.03975 0.457313 5.67519L0.462012 5.53159C0.467908 5.36569 0.475307 5.20203 0.484555 5.00473C0.520926 4.21679 0.645474 3.68039 0.828588 3.20873C1.01663 2.72166 1.27065 2.30919 1.68189 1.89795C2.09312 1.48671 2.50621 1.23331 2.99267 1.04465C3.46432 0.861531 4.00072 0.737605 4.78867 0.700612C4.98596 0.69183 5.14961 0.684661 5.31552 0.678853L5.45912 0.674199C5.82368 0.663367 6.24568 0.658433 7.07831 0.656834L8.59916 0.656738ZM7.83874 4.35551C5.79457 4.35551 4.13944 6.01244 4.13944 8.05477C4.13944 10.0989 5.79637 11.7541 7.83874 11.7541C9.88288 11.7541 11.538 10.0972 11.538 8.05477C11.538 6.01064 9.88103 4.35551 7.83874 4.35551ZM7.83874 5.83522C9.0646 5.83522 10.0583 6.82861 10.0583 8.05477C10.0583 9.28064 9.0649 10.2743 7.83874 10.2743C6.61287 10.2743 5.61915 9.28101 5.61915 8.05477C5.61915 6.8289 6.6125 5.83522 7.83874 5.83522ZM11.723 3.24572C11.213 3.24572 10.7982 3.65998 10.7982 4.16991C10.7982 4.67986 11.2124 5.09475 11.723 5.09475C12.2329 5.09475 12.6478 4.68051 12.6478 4.16991C12.6478 3.65998 12.2322 3.24509 11.723 3.24572Z"
                            fill="currentColor"
                        ></path></svg
                ></a>
            </div>
        </div>

        <div class="footer-links footer-links--wb">
            <h4 class="footer-heading-wb">Information</h4>
            <ul>
                <li><a href="<?= h(luxe_public_href('about-us.php')) ?>">About</a></li>
                <li><a href="<?= h(luxe_public_href('faq.php')) ?>">FAQ's</a></li>
                <li><a href="<?= h(luxe_public_href('terms-and-conditions.php')) ?>">Terms &amp; Conditions</a></li>
                <li><a href="<?= h(luxe_public_href('privacy-policy.php')) ?>">Privacy Policy</a></li>
                <li><a href="<?= h(luxe_public_href('return-policy.php')) ?>">Return &amp; Refund Policy</a></li>
                <li><a href="<?= h(luxe_public_href('contact-us.php')) ?>">Contact Us</a></li>
            </ul>
        </div>

        <div class="footer-links footer-links--wb">
            <h4 class="footer-heading-wb">My account</h4>
            <ul>
                <li><a href="<?= h($t2WishlistHref) ?>">Wishlist</a></li>
                <li><a href="<?= h(luxe_public_href('cart.php')) ?>">Cart</a></li>
                <li><a href="<?= h(luxe_public_href('checkout.php')) ?>">Checkout</a></li>
                <li><a href="<?= h(luxe_public_href('profile.php')) ?>">My Account</a></li>
                <li><a href="<?= h(luxe_public_href('product-list.php')) ?>">Shop</a></li>
            </ul>
        </div>

        <div class="footer-newsletter-wb">
            <h4 class="footer-heading-wb">Get newsletter</h4>
            <p class="footer-newsletter-lead">Get 10% off your first order! Hurry up</p>
            <div class="footer-newsletter-form">
                <label class="visually-hidden" for="footer-newsletter-email">Email address</label>
                <input
                    id="footer-newsletter-email"
                    type="email"
                    autocomplete="email"
                    placeholder="Enter email address"
                />
                <button type="button" class="footer-newsletter-btn">Subscribe Now →</button>
            </div>

            <div class="footer-app-left">
                <strong class="footer-app-title">Order faster with our App!</strong>
                <div class="footer-app-badges">
                    <a href="#" class="footer-app-badge footer-app-badge--apple" aria-label="Download on the App Store">
                        <img src="theme-2/images/app-store.svg" alt="Download on the App Store" />
                    </a>
                    <a href="#" class="footer-app-badge footer-app-badge--google" aria-label="Get it on Google Play">
                        <img src="theme-2/images/google-play.svg" alt="Get it on Google Play" />
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-bottom-bar">
        <div class="container">
            <div class="footer-bottom-inner-container">
                <div class="footer-bottom-inner">
                    <p>
                        Copyright &amp; Design By <strong><?= h($t2FooterBrand) ?></strong> — <?= date('Y') ?>
                    </p>
                </div>

                <div class="footer-pay-icons" aria-label="Payment methods">
                    <img src="theme-2/images/payment/1.svg" class="img-fluid loaded" alt="" />
                    <img src="theme-2/images/payment/2.svg" class="img-fluid loaded" alt="" />
                    <img src="theme-2/images/payment/3.svg" class="img-fluid loaded" alt="" />
                    <img src="theme-2/images/payment/5.svg" class="img-fluid loaded" alt="" />
                    <img src="theme-2/images/payment/7.svg" class="img-fluid loaded" alt="" />
                </div>
            </div>
        </div>
    </div>
</footer>

<?php require __DIR__ . '/mobile-app-nav.php'; ?>

<button
    type="button"
    class="scroll-top-btn"
    id="scrollTopBtn"
    aria-label="Back to top"
    aria-hidden="true"
    tabindex="-1"
>
    <span class="scroll-top-btn__icons" aria-hidden="true">
        <svg
            class="scroll-top-btn__chev"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <path d="M18 15l-6-6-6 6" />
        </svg>
        <svg
            class="scroll-top-btn__ring"
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
        >
            <circle cx="12" cy="12" r="7" />
        </svg>
    </span>
</button>
<script>
    (function () {
        if (window.__t2ScrollTopInit) return;
        window.__t2ScrollTopInit = true;
        var btn = document.getElementById("scrollTopBtn");
        if (!btn) return;

        function sync() {
            var show = window.scrollY > 320;
            btn.classList.toggle("is-visible", show);
            btn.setAttribute("aria-hidden", show ? "false" : "true");
            btn.tabIndex = show ? 0 : -1;
        }

        btn.addEventListener("click", function () {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
        window.addEventListener("scroll", sync, { passive: true });
        sync();
    })();
</script>
<script src="<?= h(luxe_theme_asset('js/page-loader.js')) ?>" defer></script>
