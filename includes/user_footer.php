<?php
declare(strict_types=1);

if (!function_exists('site_contact_bundle')) {
    require_once __DIR__ . '/bootstrap.php';
}

if (!function_exists('h')) {
    return;
}

$footer = $footer ?? [];
$footerDealsHref = (string) ($footer['deals_href'] ?? 'index.php#deals');
$footerYear = (string) ($footer['year'] ?? '2026');

$footerSiteBrand = 'LUXE';
$footerSiteLogo = '';
$footerSiteEmail = 'info@luxe.com';
$footerSitePhone = '+123 324 5879 39';
$footerSiteAddress = '37 W 24th St, New York, NY';
$footerSiteHours = 'Mon–Sat, 9:00–18:00 IST';
if (function_exists('site_contact_bundle') && function_exists('db')) {
    try {
        $sb = site_contact_bundle(db());
        $footerSiteBrand = $sb['brand'] !== '' ? $sb['brand'] : $footerSiteBrand;
        $footerSiteLogo = $sb['logo'];
        $footerSiteEmail = $sb['email'] !== '' ? $sb['email'] : $footerSiteEmail;
        $footerSitePhone = $sb['phone'] !== '' ? $sb['phone'] : $footerSitePhone;
        $footerSiteAddress = $sb['address'] !== '' ? $sb['address'] : $footerSiteAddress;
        $footerSiteHours = $sb['hours'] !== '' ? $sb['hours'] : $footerSiteHours;
    } catch (Throwable $e) {
        /* fall back to defaults above */
    }
}
$footerLogoUrl = $footerSiteLogo !== '' ? ltrim($footerSiteLogo, '/') : '';
$footerPhoneHref = function_exists('site_contact_phone_href') ? site_contact_phone_href($footerSitePhone) : preg_replace('/[^0-9+]/', '', $footerSitePhone);
?>
<footer class="footer">
  <div class="container">
    <div class="footer-top">
      <div class="footer-brand">
        <?php if ($footerLogoUrl !== ''): ?>
          <span class="footer-logo"><img src="<?= h($footerLogoUrl) ?>" alt="<?= h($footerSiteBrand) ?>" class="footer-logo__img"></span>
        <?php else: ?>
          <span class="footer-logo"><?= h($footerSiteBrand) ?></span>
        <?php endif; ?>
        <p>Your premium destination for fashion, tech &amp; lifestyle. Shop with confidence.</p>
        <ul class="footer-contact-list" role="list">
          <?php if ($footerSiteAddress !== ''): ?>
            <li class="footer-contact-list__item">
              <span class="footer-contact-list__icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              </span>
              <span class="footer-contact-list__body">
                <span class="footer-contact-list__label">Visit us</span>
                <span class="footer-contact-list__value"><?= h($footerSiteAddress) ?></span>
              </span>
            </li>
          <?php endif; ?>
          <?php if ($footerSitePhone !== ''): ?>
            <li class="footer-contact-list__item">
              <span class="footer-contact-list__icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
              </span>
              <span class="footer-contact-list__body">
                <span class="footer-contact-list__label">Call us</span>
                <a class="footer-contact-list__value footer-contact-list__value--link" href="tel:<?= h($footerPhoneHref) ?>"><?= h($footerSitePhone) ?></a>
              </span>
            </li>
          <?php endif; ?>
          <?php if ($footerSiteEmail !== ''): ?>
            <li class="footer-contact-list__item">
              <span class="footer-contact-list__icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </span>
              <span class="footer-contact-list__body">
                <span class="footer-contact-list__label">Write to us</span>
                <a class="footer-contact-list__value footer-contact-list__value--link" href="mailto:<?= h($footerSiteEmail) ?>"><?= h($footerSiteEmail) ?></a>
              </span>
            </li>
          <?php endif; ?>
        </ul>
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
          <li><a href="product-list.php">New Arrivals</a></li>
          <li><a href="index.php#trending">Best Sellers</a></li>
          <li><a href="<?= h($footerDealsHref) ?>">Sale & Offers</a></li>
          <li><a href="index.php#collections">Gift Cards</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Support</h4>
        <ul>
          <li><a href="orders.php">Track Order</a></li>
          <li><a href="orders.php">Returns</a></li>
          <li><a href="faq.php">FAQ</a></li>
          <li><a href="contact-us.php">Contact Us</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Company</h4>
        <ul>
          <li><a href="about-us.php">About LUXE</a></li>
          <li><a href="login.php">Sign In / Register</a></li>
          <li><a href="index.php#collections">Blog</a></li>
          <li><a href="index.php#brands">Press</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© <?= h($footerYear) ?> <?= h($footerSiteBrand) ?>. All rights reserved.</p>
      <div class="payment-icons">
        <span class="pay-icon"><img src="images/payment/1.svg" alt="Payment Icon"></span>
        <span class="pay-icon"><img src="images/payment/2.svg" alt="Payment Icon"></span>
        <span class="pay-icon"><img src="images/payment/3.svg" alt="Payment Icon"></span>
        <span class="pay-icon"><img src="images/payment/4.svg" alt="Payment Icon"></span>
        <span class="pay-icon"><img src="images/payment/5.svg" alt="Payment Icon"></span>
        <span class="pay-icon"><img src="images/payment/6.svg" alt="Payment Icon"></span>
        <span class="pay-icon"><img src="images/payment/7.svg" alt="Payment Icon"></span>
      </div>
    </div>
  </div>
</footer>
