<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/seller_product_catalog.php';

$pdo = db();
$seller = seller_require_login($pdo);

$productId = (int) ($_GET['id'] ?? 0);
if ($productId <= 0) {
    header('Location: products.php');
    exit;
}

$st = $pdo->prepare(
    'SELECT id, name, slug, sku, category, product_type, price, original_price, emoji, badge, brand,
            size_options, color_options, stock_qty, description, image_path,
            offer_flash_text, offer_countdown_seconds, offer_bank_text, shipping_class,
            manufacturer_generic_name, manufacturer_country, manufacturer_name_address, packer_name_address,
            active, approval_status, created_at
     FROM products
     WHERE id = ? AND seller_id = ?
     LIMIT 1'
);
$st->execute([$productId, (int) $seller['id']]);
$product = $st->fetch(PDO::FETCH_ASSOC);
if (!$product) {
    header('Location: products.php');
    exit;
}

$imgSt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
$imgSt->execute([$productId]);
$galleryPaths = array_values(array_filter(array_map('strval', $imgSt->fetchAll(PDO::FETCH_COLUMN) ?: []), static fn (string $v): bool => $v !== ''));
$mainImg = trim((string) ($product['image_path'] ?? ''));
if ($galleryPaths === [] && $mainImg !== '') {
    $galleryPaths = [$mainImg];
}

$varSt = $pdo->prepare(
    'SELECT size_label, color_label, stock_qty, active
     FROM product_variant_inventory
     WHERE product_id = ?
     ORDER BY size_label ASC, color_label ASC, id ASC'
);
$varSt->execute([$productId]);
$variantRows = $varSt->fetchAll(PDO::FETCH_ASSOC);

$variantStockSum = 0;
foreach ($variantRows as $vr) {
    $variantStockSum += (int) ($vr['stock_qty'] ?? 0);
}
$displayStock = $variantRows !== [] ? $variantStockSum : (int) ($product['stock_qty'] ?? 0);

$ptSlug = trim((string) ($product['product_type'] ?? ''));
$ptLabel = $ptSlug !== '' ? $ptSlug : '—';
foreach (seller_product_types_for_category((string) ($product['category'] ?? '')) as $t) {
    if (($t['slug'] ?? '') === $ptSlug) {
        $ptLabel = (string) ($t['label'] ?? $ptSlug);
        break;
    }
}

$ap = strtolower(trim((string) ($product['approval_status'] ?? '')));
$active = (int) ($product['active'] ?? 0) === 1;
if ($ap === 'rejected') {
    $publishLabel = 'Rejected';
} elseif (!$active) {
    $publishLabel = 'Unpublished';
} elseif ($ap === 'approved') {
    $publishLabel = 'Published';
} elseif ($ap === 'pending') {
    $publishLabel = 'Pending approval';
} else {
    $publishLabel = $ap !== '' ? ucfirst($ap) : '—';
}

$createdDisplay = '—';
$createdRaw = trim((string) ($product['created_at'] ?? ''));
if ($createdRaw !== '') {
    try {
        $createdDisplay = (new DateTimeImmutable($createdRaw))->format('j M Y, g:i a');
    } catch (Throwable) {
        $createdDisplay = $createdRaw;
    }
}

$shipClass = strtolower(trim((string) ($product['shipping_class'] ?? 'standard')));
$shippingLabels = [
    'standard' => 'Standard delivery',
    'express' => 'Express delivery',
    'free' => 'Free shipping',
];
$shippingLabel = $shippingLabels[$shipClass] ?? ucfirst($shipClass !== '' ? $shipClass : 'standard');

$descRaw = trim((string) ($product['description'] ?? ''));
$descHtml = '';
if ($descRaw !== '') {
    $descHtml = strip_tags(
        $descRaw,
        '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><blockquote><span>'
    );
}

$hasManufacturer = trim((string) ($product['manufacturer_generic_name'] ?? '')) !== ''
    || trim((string) ($product['manufacturer_country'] ?? '')) !== ''
    || trim((string) ($product['manufacturer_name_address'] ?? '')) !== ''
    || trim((string) ($product['packer_name_address'] ?? '')) !== '';

$pageTitle = 'Product — ' . (string) $product['name'];
$activeNav = 'products';

$galleryJson = json_encode($galleryPaths, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
if (!is_string($galleryJson)) {
    $galleryJson = '[]';
}

$priceInt = (int) ($product['price'] ?? 0);
$origInt = (int) ($product['original_price'] ?? 0);
$discountPct = 0;
if ($origInt > $priceInt && $origInt > 0) {
    $discountPct = (int) round(100 - ($priceInt / $origInt * 100));
}

$approvalChipClass = 'seller-status-chip--pending';
if ($ap === 'approved') {
    $approvalChipClass = 'seller-status-chip--approved';
} elseif ($ap === 'rejected') {
    $approvalChipClass = 'seller-status-chip--rejected';
}

$listingChipClass = $active ? 'seller-status-chip--delivered' : 'seller-status-chip--inactive';
$listingChipLabel = $active ? 'Listing active' : 'Listing inactive';

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="seller-add-product-page seller-product-view-page">
          <div class="seller-pv-hero">
            <nav class="seller-wizard-breadcrumb seller-pv-breadcrumb" aria-label="Breadcrumb">
              <a href="products.php">Products</a>
              <span class="seller-wizard-breadcrumb__sep" aria-hidden="true">/</span>
              <span class="seller-wizard-breadcrumb__current">View product</span>
            </nav>

            <div class="seller-wizard-page-head seller-pv-page-head">
              <div class="seller-pv-title-block">
                <p class="seller-pv-eyebrow">Product #<?= (int) $product['id'] ?></p>
                <h1 class="seller-wizard-page-title seller-pv-title"><?= h((string) $product['name']) ?></h1>
                <p class="seller-pv-sub"><span class="seller-pv-sub__mono"><?= h((string) ($product['slug'] ?? '')) ?></span></p>
                <div class="seller-pv-chips" aria-label="Listing status">
                  <span class="seller-status-chip <?= h($listingChipClass) ?>"><?= h($listingChipLabel) ?></span>
                  <span class="seller-status-chip <?= h($approvalChipClass) ?>"><?= h(ucfirst($ap !== '' ? $ap : '—')) ?></span>
                  <?php if (trim((string) ($product['badge'] ?? '')) !== ''): ?>
                    <span class="seller-pv-chip seller-pv-chip--badge"><?= h((string) $product['badge']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="seller-pv-head-actions">
                <a class="seller-pv-btn seller-pv-btn--ghost" href="products.php">
                  <span class="seller-pv-btn__ico" aria-hidden="true">←</span> Catalogue
                </a>
                <a class="seller-pv-btn seller-pv-btn--ghost" href="../product.php?id=<?= (int) $product['id'] ?>" target="_blank" rel="noopener">Store preview</a>
                <a class="seller-pv-btn seller-pv-btn--primary" href="add-product.php?id=<?= (int) $product['id'] ?>">Edit product</a>
              </div>
            </div>
          </div>

          <div class="seller-pv-layout">
            <div class="seller-wizard-card card seller-pv-card seller-pv-card--media seller-pv-gallery-card">
              <div class="seller-pv-gallery" id="sellerPvGallery" role="region" aria-label="Product images">
                <div class="seller-pv-gallery__main">
                  <div class="seller-pv-gallery__main-inner" id="sellerPvMedia">
                    <?php if ($galleryPaths === []): ?>
                      <span class="seller-pv-gallery__empty">No image</span>
                    <?php else: ?>
                      <img src="../<?= h($galleryPaths[0]) ?>" alt="<?= h((string) $product['name']) ?>" decoding="async">
                    <?php endif; ?>
                  </div>
                </div>
                <?php if ($galleryPaths !== []): ?>
                  <div class="seller-pv-gallery__thumbs" id="sellerPvThumbs" role="tablist" aria-label="Image thumbnails">
                    <?php foreach ($galleryPaths as $ti => $tpath): ?>
                      <button
                        type="button"
                        class="seller-pv-gallery__thumb<?= $ti === 0 ? ' is-active' : '' ?>"
                        id="sellerPvThumb-<?= (int) $ti ?>"
                        role="tab"
                        aria-selected="<?= $ti === 0 ? 'true' : 'false' ?>"
                        aria-controls="sellerPvMedia"
                        data-index="<?= (int) $ti ?>"
                        tabindex="<?= $ti === 0 ? '0' : '-1' ?>"
                      >
                        <img src="../<?= h($tpath) ?>" alt="" loading="<?= $ti === 0 ? 'eager' : 'lazy' ?>" decoding="async">
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="seller-pv-details">
              <div class="seller-wizard-card card seller-pv-card seller-pv-summary">
                <header class="seller-pv-summary__top">
                  <span class="seller-pv-summary__top-label">Listing snapshot</span>
                  <p class="seller-pv-summary__top-hint">Core catalogue fields shown to buyers after approval.</p>
                </header>
                <div class="seller-pv-summary__block seller-pv-summary__block--basics">
                  <h2 class="seller-pv-section-title" id="pv-basics">Basics &amp; catalogue</h2>
                  <dl class="seller-pv-kv seller-pv-kv--summary">
                    <dt>SKU</dt><dd><code class="seller-pv-code"><?= h((string) ($product['sku'] ?? '')) ?></code></dd>
                    <dt>Category</dt><dd><span class="seller-pv-pill"><?= h(ucfirst((string) ($product['category'] ?? ''))) ?></span></dd>
                    <dt>Product type</dt><dd><?= h($ptLabel) ?></dd>
                    <dt>Brand</dt><dd><?= h((string) ($product['brand'] ?? '')) ?></dd>
                    <dt>Tile emoji</dt><dd><span class="seller-pv-emoji-wrap" aria-hidden="true"><span class="seller-pv-emoji"><?= h((string) ($product['emoji'] ?? '')) ?></span></span></dd>
                  </dl>
                </div>
                <div class="seller-pv-summary__block seller-pv-summary__block--pricing">
                  <h2 class="seller-pv-section-title" id="pv-pricing">Pricing &amp; stock</h2>
                  <div class="seller-pv-price-panel">
                    <div class="seller-pv-price-row">
                      <div class="seller-pv-price-main-wrap">
                        <span class="seller-pv-price-label">Selling price</span>
                        <p class="seller-pv-price-amount">₹<?= number_format($priceInt, 0, '.', ',') ?></p>
                        <?php if ($origInt > $priceInt): ?>
                          <p class="seller-pv-price-secondary">
                            <span class="seller-pv-price-mrp">MRP ₹<?= number_format($origInt, 0, '.', ',') ?></span>
                            <?php if ($discountPct > 0): ?>
                              <span class="seller-pv-price-off"><?= (int) $discountPct ?>% off</span>
                            <?php endif; ?>
                          </p>
                        <?php endif; ?>
                      </div>
                      <div class="seller-pv-stat-pills">
                        <div class="seller-pv-stat-pill">
                          <span class="seller-pv-stat-pill__label">Stock<?= $variantRows !== [] ? ' (variants)' : '' ?></span>
                          <span class="seller-pv-stat-pill__value"><?= (int) $displayStock ?></span>
                        </div>
                        <div class="seller-pv-stat-pill">
                          <span class="seller-pv-stat-pill__label">Base qty</span>
                          <span class="seller-pv-stat-pill__value"><?= (int) ($product['stock_qty'] ?? 0) ?></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="seller-pv-summary__block seller-pv-summary__block--offers seller-pv-summary__block--last">
                  <h2 class="seller-pv-section-title" id="pv-offers">Offers</h2>
                  <dl class="seller-pv-kv seller-pv-kv--summary">
                    <dt>Flash text</dt><dd><?= h((string) ($product['offer_flash_text'] ?? '')) !== '' ? h((string) $product['offer_flash_text']) : '—' ?></dd>
                    <dt>Countdown</dt><dd><span class="seller-pv-mono-value"><?= h(seller_format_offer_countdown((int) ($product['offer_countdown_seconds'] ?? 0))) ?></span></dd>
                    <dt>Bank / card copy</dt><dd><?= h((string) ($product['offer_bank_text'] ?? '')) !== '' ? h((string) $product['offer_bank_text']) : '—' ?></dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <section class="seller-wizard-card card seller-pv-card seller-pv-bottom-tabs" aria-label="More product details">
            <header class="seller-pv-tabs-header">
              <div class="seller-pv-tabs-header__text">
                <h2 class="seller-pv-tabs-title">More product details</h2>
                <p class="seller-pv-tabs-sub">Everything buyers see after basics — options, compliance, story, delivery, and approval.</p>
              </div>
            </header>
            <div class="seller-pv-tabs-bar-wrap">
              <div class="seller-pv-tabs-track" role="tablist" aria-label="Product detail sections">
                <button type="button" class="seller-pv-tab is-active" role="tab" id="sellerPvTabSizes" aria-selected="true" aria-controls="sellerPvPanelSizes" data-panel="sizes">
                  <span class="seller-pv-tab__ico" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" x2="12" y1="22.08" y2="12"/></svg></span>
                  <span class="seller-pv-tab__label">Sizes &amp; colours</span>
                </button>
                <button type="button" class="seller-pv-tab" role="tab" id="sellerPvTabMfg" aria-selected="false" aria-controls="sellerPvPanelMfg" data-panel="mfg" tabindex="-1">
                  <span class="seller-pv-tab__ico" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>
                  <span class="seller-pv-tab__label">Manufacturer</span>
                </button>
                <button type="button" class="seller-pv-tab" role="tab" id="sellerPvTabDesc" aria-selected="false" aria-controls="sellerPvPanelDesc" data-panel="desc" tabindex="-1">
                  <span class="seller-pv-tab__ico" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span>
                  <span class="seller-pv-tab__label">Description</span>
                </button>
                <button type="button" class="seller-pv-tab" role="tab" id="sellerPvTabShip" aria-selected="false" aria-controls="sellerPvPanelShip" data-panel="ship" tabindex="-1">
                  <span class="seller-pv-tab__ico" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 18h4"/><path d="M11 6h2a1 1 0 0 1 1 1v8"/><path d="M4 18V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg></span>
                  <span class="seller-pv-tab__label">Shipping</span>
                </button>
                <button type="button" class="seller-pv-tab" role="tab" id="sellerPvTabStatus" aria-selected="false" aria-controls="sellerPvPanelStatus" data-panel="status" tabindex="-1">
                  <span class="seller-pv-tab__ico" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                  <span class="seller-pv-tab__label">Listing status</span>
                </button>
              </div>
            </div>
            <div class="seller-pv-tabs-panels">
              <div class="seller-pv-tab-panel is-active" role="tabpanel" id="sellerPvPanelSizes" aria-labelledby="sellerPvTabSizes" data-panel="sizes" aria-hidden="false">
                <div class="seller-pv-tab-panel__inner">
                  <div class="seller-pv-option-grid">
                    <div class="seller-pv-option-card">
                      <span class="seller-pv-option-card__label">Sizes</span>
                      <p class="seller-pv-option-card__value"><?= h((string) ($product['size_options'] ?? '')) !== '' ? h((string) $product['size_options']) : '—' ?></p>
                    </div>
                    <div class="seller-pv-option-card">
                      <span class="seller-pv-option-card__label">Colours</span>
                      <p class="seller-pv-option-card__value"><?= h((string) ($product['color_options'] ?? '')) !== '' ? h((string) $product['color_options']) : '—' ?></p>
                    </div>
                  </div>
                <?php if ($variantRows !== []): ?>
                  <p class="seller-pv-panel-note">Variant inventory (per size / colour)</p>
                  <div class="seller-pv-table-wrap">
                    <table class="seller-pv-variants-table">
                      <thead>
                        <tr>
                          <th>Size</th>
                          <th>Colour</th>
                          <th>Qty</th>
                          <th>Active</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($variantRows as $vr): ?>
                          <tr>
                            <td><?= h((string) ($vr['size_label'] ?? '')) !== '' ? h((string) $vr['size_label']) : '—' ?></td>
                            <td><?= h((string) ($vr['color_label'] ?? '')) !== '' ? h((string) $vr['color_label']) : '—' ?></td>
                            <td><?= (int) ($vr['stock_qty'] ?? 0) ?></td>
                            <td><?= (int) ($vr['active'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
                </div>
              </div>
              <div class="seller-pv-tab-panel" role="tabpanel" id="sellerPvPanelMfg" aria-labelledby="sellerPvTabMfg" data-panel="mfg" aria-hidden="true">
                <div class="seller-pv-tab-panel__inner">
                <?php if ($hasManufacturer): ?>
                  <dl class="seller-pv-kv seller-pv-kv--tab">
                    <dt>Generic name</dt><dd><?= h((string) ($product['manufacturer_generic_name'] ?? '')) !== '' ? h((string) $product['manufacturer_generic_name']) : '—' ?></dd>
                    <dt>Country</dt><dd><?= h((string) ($product['manufacturer_country'] ?? '')) !== '' ? h((string) $product['manufacturer_country']) : '—' ?></dd>
                    <dt>Mfg. name &amp; address</dt><dd><?= h((string) ($product['manufacturer_name_address'] ?? '')) !== '' ? h((string) $product['manufacturer_name_address']) : '—' ?></dd>
                    <dt>Packer name &amp; address</dt><dd><?= h((string) ($product['packer_name_address'] ?? '')) !== '' ? h((string) $product['packer_name_address']) : '—' ?></dd>
                  </dl>
                <?php else: ?>
                  <div class="seller-pv-empty-state" role="status">
                    <span class="seller-pv-empty-state__ico" aria-hidden="true"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg></span>
                    <p class="seller-pv-empty-state__title">No manufacturer details</p>
                    <p class="seller-pv-empty-state__text">Add generic name, country, and addresses in the product wizard (Advance step).</p>
                  </div>
                <?php endif; ?>
                </div>
              </div>
              <div class="seller-pv-tab-panel" role="tabpanel" id="sellerPvPanelDesc" aria-labelledby="sellerPvTabDesc" data-panel="desc" aria-hidden="true">
                <div class="seller-pv-tab-panel__inner">
                <?php if ($descHtml !== ''): ?>
                  <div class="seller-pv-rich-text seller-pv-rich-text--boxed"><?= $descHtml ?></div>
                <?php else: ?>
                  <div class="seller-pv-empty-state" role="status">
                    <span class="seller-pv-empty-state__ico" aria-hidden="true"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                    <p class="seller-pv-empty-state__title">No description yet</p>
                    <p class="seller-pv-empty-state__text">A detailed description helps conversion. Edit the product to add copy from the wizard.</p>
                  </div>
                <?php endif; ?>
                </div>
              </div>
              <div class="seller-pv-tab-panel" role="tabpanel" id="sellerPvPanelShip" aria-labelledby="sellerPvTabShip" data-panel="ship" aria-hidden="true">
                <div class="seller-pv-tab-panel__inner">
                <div class="seller-pv-ship-card">
                  <span class="seller-pv-ship-card__label">Shipping class</span>
                  <p class="seller-pv-ship-card__value"><?= h($shippingLabel) ?></p>
                </div>
                </div>
              </div>
              <div class="seller-pv-tab-panel" role="tabpanel" id="sellerPvPanelStatus" aria-labelledby="sellerPvTabStatus" data-panel="status" aria-hidden="true">
                <div class="seller-pv-tab-panel__inner">
                <dl class="seller-pv-kv seller-pv-kv--tab">
                  <dt>Active flag</dt><dd><?= $active ? 'Active' : 'Inactive' ?></dd>
                  <dt>Approval</dt><dd><?= h(ucfirst($ap !== '' ? $ap : '—')) ?></dd>
                  <dt>Publish summary</dt><dd><?= h($publishLabel) ?></dd>
                  <dt>Created</dt><dd><?= h($createdDisplay) ?></dd>
                </dl>
                </div>
              </div>
            </div>
          </section>
        </div>

        <script>
          (function () {
            var tabRoot = document.querySelector('.seller-pv-bottom-tabs');
            if (tabRoot) {
              var tabBtns = tabRoot.querySelectorAll('.seller-pv-tab');
              var tabPanels = tabRoot.querySelectorAll('.seller-pv-tab-panel');
              function activateTab(key) {
                tabBtns.forEach(function (btn) {
                  var on = btn.getAttribute('data-panel') === key;
                  btn.classList.toggle('is-active', on);
                  btn.setAttribute('aria-selected', on ? 'true' : 'false');
                  btn.setAttribute('tabindex', on ? '0' : '-1');
                });
                tabPanels.forEach(function (panel) {
                  var on = panel.getAttribute('data-panel') === key;
                  panel.classList.toggle('is-active', on);
                  panel.setAttribute('aria-hidden', on ? 'false' : 'true');
                });
              }
              tabBtns.forEach(function (btn) {
                btn.addEventListener('click', function () {
                  var key = btn.getAttribute('data-panel');
                  if (key) activateTab(key);
                });
              });
              tabRoot.addEventListener('keydown', function (e) {
                if (e.target && !e.target.classList.contains('seller-pv-tab')) return;
                var keys = ['sizes', 'mfg', 'desc', 'ship', 'status'];
                var i = keys.indexOf(e.target.getAttribute('data-panel') || '');
                if (i < 0) return;
                if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                  e.preventDefault();
                  var ni = e.key === 'ArrowRight' ? (i + 1) % keys.length : (i - 1 + keys.length) % keys.length;
                  activateTab(keys[ni]);
                  var nextBtn = tabRoot.querySelector('.seller-pv-tab[data-panel="' + keys[ni] + '"]');
                  if (nextBtn) nextBtn.focus();
                }
              });
              tabPanels.forEach(function (panel) {
                if (!panel.classList.contains('is-active')) {
                  panel.setAttribute('aria-hidden', 'true');
                }
              });
            }
          })();
        </script>
        <script>
          (function () {
            var paths = <?= $galleryJson ?>;
            var media = document.getElementById('sellerPvMedia');
            var thumbsRoot = document.getElementById('sellerPvThumbs');
            if (!media) return;
            var images = Array.isArray(paths) ? paths.filter(function (v) { return typeof v === 'string' && v.trim() !== ''; }) : [];
            var idx = 0;
            var altBase = <?= json_encode((string) $product['name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

            function syncThumbs() {
              if (!thumbsRoot) return;
              var buttons = thumbsRoot.querySelectorAll('.seller-pv-gallery__thumb');
              buttons.forEach(function (btn, i) {
                var on = i === idx;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
                btn.setAttribute('tabindex', on ? '0' : '-1');
              });
            }

            function render() {
              if (!images.length) {
                media.innerHTML = '<span class="seller-pv-gallery__empty">No image</span>';
                return;
              }
              idx = Math.max(0, Math.min(images.length - 1, idx));
              var path = images[idx];
              media.innerHTML = '';
              var img = document.createElement('img');
              img.src = '../' + path;
              img.alt = altBase;
              img.decoding = 'async';
              media.appendChild(img);
              syncThumbs();
            }

            if (thumbsRoot) {
              thumbsRoot.addEventListener('click', function (e) {
                var t = e.target;
                var btn = t && t.closest ? t.closest('.seller-pv-gallery__thumb') : null;
                if (!btn || !thumbsRoot.contains(btn)) return;
                var i = parseInt(btn.getAttribute('data-index') || '0', 10);
                if (!isNaN(i) && i >= 0 && i < images.length) {
                  idx = i;
                  render();
                }
              });
              thumbsRoot.addEventListener('keydown', function (e) {
                if (!images.length || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' && e.key !== 'Home' && e.key !== 'End')) return;
                var max = images.length - 1;
                if (e.key === 'ArrowRight') idx = idx >= max ? 0 : idx + 1;
                else if (e.key === 'ArrowLeft') idx = idx <= 0 ? max : idx - 1;
                else if (e.key === 'Home') idx = 0;
                else if (e.key === 'End') idx = max;
                e.preventDefault();
                render();
                var activeBtn = thumbsRoot.querySelector('.seller-pv-gallery__thumb.is-active');
                if (activeBtn) activeBtn.focus();
              });
            }
          })();
        </script>

<?php
require __DIR__ . '/partials/shell-bottom.php';
