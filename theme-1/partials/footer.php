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

$t1FooterBrand = 'LUXE';
$t1FooterAddress = '37 W 24th St, New York, NY';
$t1FooterPhone = '+123 324 5879 39';
$t1FooterEmail = 'info@luxe.com';
$t1FooterLogo = '';
if (function_exists('site_contact_bundle') && function_exists('db')) {
    try {
        $b = site_contact_bundle(db());
        $t1FooterBrand   = $b['brand']   !== '' ? $b['brand']   : $t1FooterBrand;
        $t1FooterAddress = $b['address'] !== '' ? $b['address'] : $t1FooterAddress;
        $t1FooterPhone   = $b['phone']   !== '' ? $b['phone']   : $t1FooterPhone;
        $t1FooterEmail   = $b['email']   !== '' ? $b['email']   : $t1FooterEmail;
        $t1FooterLogo    = $b['logo'];
    } catch (Throwable $e) {
        /* defaults */
    }
}
$t1FooterLogoUrl = $t1FooterLogo !== '' ? luxe_public_href(ltrim($t1FooterLogo, '/')) : '';
?>
<footer class="footer">
  <div class="container footer-top">
    <div class="footer-brand">
      <a class="footer-logo" href="index.php">
        <?php if ($t1FooterLogoUrl !== ''): ?>
          <img src="<?= h($t1FooterLogoUrl) ?>" alt="<?= h($t1FooterBrand) ?>" class="footer-logo-img">
        <?php else: ?>
          <span class="footer-logo-mark" aria-hidden="true">
            <span class="logo-stripe"></span>
            <span class="logo-stripe"></span>
            <span class="logo-stripe"></span>
          </span>
          <span class="footer-logo-word"><?= h($t1FooterBrand) ?></span>
        <?php endif; ?>
      </a>
      <p>Curated collections from trusted sellers. Shop fashion, lifestyle and essentials with easy returns and secure checkout.</p>
      <div class="footer-social">
        <span>Follow :</span>
        <a href="#" aria-label="Facebook">f</a>
        <a href="#" aria-label="Twitter">x</a>
        <a href="#" aria-label="Google">g+</a>
        <a href="#" aria-label="LinkedIn">in</a>
      </div>
    </div>

    <div class="footer-links">
      <h4>Company</h4>
      <ul>
        <li><a href="about-us.php">About Us</a></li>
        <li><a href="contact-us.php">Contact Us</a></li>
        <li><a href="seller-store.php">Affiliate</a></li>
        <li><a href="index.php">Career</a></li>
        <li><a href="index.php">Latest News</a></li>
      </ul>
    </div>

    <div class="footer-links">
      <h4>Category</h4>
      <ul>
        <?php foreach (array_slice(array_values($theme1FooterCategories), 0, 5) as $category): ?>
          <li><a href="product-list.php?category=<?= h(rawurlencode(strtolower((string) $category))) ?>"><?= h((string) $category) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="footer-links">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="privacy-policy.php">Privacy Policy</a></li>
        <li><a href="terms-and-conditions.php">Terms And Condition</a></li>
        <li><a href="return-policy.php">Return Policy</a></li>
        <li><a href="faq.php">FAQ's</a></li>
        <li><a href="<?= h(luxe_public_href('seller/login.php')) ?>">Become A Vendor</a></li>
      </ul>
    </div>

    <div class="footer-contact">
      <h4>Contact Us</h4>
      <p>Need help with your order or account? Our support team is available for quick assistance.</p>
      <ul>
        <?php if ($t1FooterAddress !== ''): ?>
          <li><?= h($t1FooterAddress) ?></li>
        <?php endif; ?>
        <?php if ($t1FooterPhone !== ''): ?>
          <li><?= h($t1FooterPhone) ?></li>
        <?php endif; ?>
        <?php if ($t1FooterEmail !== ''): ?>
          <li><?= h($t1FooterEmail) ?></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="container footer-bottom">
    <p>Copyright @ <span><?= h($t1FooterBrand) ?></span> <?= date('Y') ?>. All right reserved.</p>
    <div class="footer-payments">
      <span>Payment by :</span>
      <i>Mastercard</i>
      <i>Visa</i>
      <i>Bank</i>
    </div>
  </div>
</footer>
