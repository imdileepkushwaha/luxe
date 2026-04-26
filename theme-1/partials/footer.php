<?php
declare(strict_types=1);

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
?>
<footer class="footer">
  <div class="container footer-top">
    <div class="footer-brand">
      <a class="footer-logo" href="../index.php">
        <span class="footer-logo-mark" aria-hidden="true">
          <span class="logo-stripe"></span>
          <span class="logo-stripe"></span>
          <span class="logo-stripe"></span>
        </span>
        <span class="footer-logo-word">LUXE</span>
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
        <li><a href="../about-us.php">About Us</a></li>
        <li><a href="../contact-us.php">Contact Us</a></li>
        <li><a href="../seller-store.php">Affiliate</a></li>
        <li><a href="../index.php">Career</a></li>
        <li><a href="../index.php">Latest News</a></li>
      </ul>
    </div>

    <div class="footer-links">
      <h4>Category</h4>
      <ul>
        <?php foreach (array_slice(array_values($theme1FooterCategories), 0, 5) as $category): ?>
          <li><a href="../product-list.php?category=<?= h(rawurlencode(strtolower((string) $category))) ?>"><?= h((string) $category) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="footer-links">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="../profile.php?tab=settings">Privacy Policy</a></li>
        <li><a href="../profile.php?tab=settings">Terms And Condition</a></li>
        <li><a href="../profile.php?tab=orders">Return Policy</a></li>
        <li><a href="../faq.php">FAQ's</a></li>
        <li><a href="../seller/login.php">Become A Vendor</a></li>
      </ul>
    </div>

    <div class="footer-contact">
      <h4>Contact Us</h4>
      <p>Need help with your order or account? Our support team is available for quick assistance.</p>
      <ul>
        <li>37 W 24th St, New York, NY</li>
        <li>+123 324 5879 39</li>
        <li>info@luxe.com</li>
      </ul>
    </div>
  </div>

  <div class="container footer-bottom">
    <p>Copyright @ <span>LUXE</span> <?= date('Y') ?>. All right reserved.</p>
    <div class="footer-payments">
      <span>Payment by :</span>
      <i>Mastercard</i>
      <i>Visa</i>
      <i>Bank</i>
    </div>
  </div>
</footer>
