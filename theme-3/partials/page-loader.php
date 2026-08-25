<?php
declare(strict_types=1);

$t2LoaderBrand = 'LUXE';
if (function_exists('site_setting_get') && function_exists('db')) {
    try {
        $bn = trim((string) site_setting_get(db(), 'site_brand_name', 'LUXE'));
        if ($bn !== '') {
            $t2LoaderBrand = $bn;
        }
    } catch (Throwable $e) {
        /* keep default */
    }
}
?>
<div class="t2-loader" id="t2-loader" role="status" aria-live="polite" aria-busy="true" aria-label="Loading storefront">
  <div class="t2-loader__bg" aria-hidden="true">
    <div class="t2-loader__orb t2-loader__orb--1"></div>
    <div class="t2-loader__orb t2-loader__orb--2"></div>
    <div class="t2-loader__orb t2-loader__orb--3"></div>
  </div>
  
  <div class="t2-loader__content">
    <div class="t2-loader__liquid-wrap" aria-hidden="true">
      <div class="t2-loader__liquid">
        <div class="t2-loader__drop"></div>
        <div class="t2-loader__drop"></div>
        <div class="t2-loader__drop"></div>
      </div>
    </div>
    
    <div class="t2-loader__text-wrap">
      <p class="t2-loader__eyebrow">Initializing Luxury</p>
      <h2 class="t2-loader__title"><?= h($t2LoaderBrand) ?></h2>
      <div class="t2-loader__progress" aria-hidden="true">
        <div class="t2-loader__progress-inner"></div>
      </div>
    </div>
  </div>
</div>
