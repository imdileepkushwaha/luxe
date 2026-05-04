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
  <div class="t2-loader__mesh" aria-hidden="true"></div>
  <div class="t2-loader__sweep" aria-hidden="true"></div>
  <div class="t2-loader__content">
    <p class="t2-loader__eyebrow">Opening collection</p>
    <p class="t2-loader__title"><?= h($t2LoaderBrand) ?></p>
    <div class="t2-loader__dots" aria-hidden="true">
      <span></span>
      <span></span>
      <span></span>
    </div>
    <div class="t2-loader__rail" aria-hidden="true">
      <span class="t2-loader__rail-beam"></span>
    </div>
  </div>
</div>
