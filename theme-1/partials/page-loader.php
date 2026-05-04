<?php
declare(strict_types=1);

$t1LoaderBrand = 'LUXE';
if (function_exists('site_brand_name') && function_exists('db')) {
    try {
        $bn = trim((string) site_brand_name(db()));
        if ($bn !== '') {
            $t1LoaderBrand = $bn;
        }
    } catch (Throwable $e) {
        /* keep default */
    }
}
?>
<div class="t1-loader" id="t1-loader" role="status" aria-live="polite" aria-busy="true" aria-label="Loading store">
  <div class="t1-loader__mesh" aria-hidden="true"></div>
  <div class="t1-loader__content">
    <div class="t1-loader__mark" aria-hidden="true">
      <span class="t1-loader__orbit"></span>
      <span class="t1-loader__orbit t1-loader__orbit--inner"></span>
      <span class="t1-loader__pulse"></span>
    </div>
    <p class="t1-loader__brand"><?= h($t1LoaderBrand) ?></p>
    <p class="t1-loader__tagline">Loading your storefront…</p>
    <div class="t1-loader__bar" aria-hidden="true">
      <span class="t1-loader__bar-fill"></span>
      <span class="t1-loader__bar-glow"></span>
    </div>
  </div>
</div>
