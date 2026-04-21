<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/seller_product_catalog.php';

$pdo = db();
$seller = seller_require_login($pdo);

$wizardFormError = '';
if (!empty($_SESSION['seller_wizard_product_error'])) {
    $wizardFormError = (string) $_SESSION['seller_wizard_product_error'];
    unset($_SESSION['seller_wizard_product_error']);
}

$sizeCatalogWizard = seller_base_size_catalog();
$editProductId = (int) ($_GET['id'] ?? 0);
$editProduct = null;
$isEditMode = false;
$selectedSizesWizard = [];
$selectedColorsWizard = [];
$editGalleryPaths = [];
$shippingClassWizard = 'standard';
$editPublishStatusLabel = '';
$editPublishAtDisplay = '';

$activeNav = 'products';

$kycCompleted = (int) ($seller['kyc_completed'] ?? 0) === 1;
$kycFinalApproved = (int) ($seller['kyc_final_approved'] ?? 0) === 1;
$canAddProducts = $kycCompleted && $kycFinalApproved;
$kycRejectionReason = trim((string) ($seller['kyc_rejection_reason'] ?? ''));

$allowedCategories = is_array($seller['allowed_categories']) ? $seller['allowed_categories'] : [];
$categoryLabels = [
    'fashion' => 'Fashion',
    'electronics' => 'Electronics',
    'beauty' => 'Beauty',
    'home' => 'Home',
];

$colorCatalogFull = ['Black', 'White', 'Blue', 'Navy', 'Red', 'Green', 'Yellow', 'Orange', 'Pink', 'Purple', 'Brown', 'Grey', 'Silver', 'Gold', 'Beige', 'Maroon'];

/** Category-specific colours in Advance → Inventory (subset of catalog). */
$colorsByCategory = [
    'fashion' => $colorCatalogFull,
    'electronics' => ['Black', 'White', 'Silver', 'Gold', 'Grey', 'Navy', 'Blue', 'Red'],
    'beauty' => ['Black', 'White', 'Pink', 'Red', 'Purple', 'Beige', 'Brown', 'Gold', 'Silver', 'Maroon', 'Navy', 'Orange'],
    'home' => $colorCatalogFull,
];

foreach ($colorsByCategory as $k => $arr) {
    $colorsByCategory[$k] = array_values(array_intersect($colorCatalogFull, $arr));
}

if ($editProductId > 0) {
    $editSt = $pdo->prepare(
        'SELECT id, name, sku, category, product_type, price, original_price, emoji, badge, brand, size_options, color_options, stock_qty, description,
                offer_flash_text, offer_countdown_seconds, offer_bank_text, shipping_class,
                manufacturer_generic_name, manufacturer_country, manufacturer_name_address, packer_name_address,
                active, approval_status, image_path, created_at
         FROM products
         WHERE id = ? AND seller_id = ?
         LIMIT 1'
    );
    $editSt->execute([$editProductId, (int) $seller['id']]);
    $editProduct = $editSt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$editProduct) {
        header('Location: products.php');
        exit;
    }
    $isEditMode = true;
    $selectedSizesWizard = seller_parse_saved_options((string) ($editProduct['size_options'] ?? ''), $sizeCatalogWizard);
    $selectedColorsWizard = seller_parse_saved_options((string) ($editProduct['color_options'] ?? ''), $colorCatalogFull);
    $imgSt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $imgSt->execute([$editProductId]);
    $editGalleryPaths = array_values(array_filter(array_map('strval', $imgSt->fetchAll(PDO::FETCH_COLUMN) ?: []), static fn (string $v): bool => $v !== ''));
    $mainImg = trim((string) ($editProduct['image_path'] ?? ''));
    if ($editGalleryPaths === [] && $mainImg !== '') {
        $editGalleryPaths = [$mainImg];
    }
    $sc = strtolower(trim((string) ($editProduct['shipping_class'] ?? 'standard')));
    $shippingClassWizard = in_array($sc, ['standard', 'express', 'free'], true) ? $sc : 'standard';

    $ap = strtolower(trim((string) ($editProduct['approval_status'] ?? '')));
    $active = (int) ($editProduct['active'] ?? 0) === 1;
    if ($ap === 'rejected') {
        $editPublishStatusLabel = 'Rejected';
    } elseif (!$active) {
        $editPublishStatusLabel = 'Unpublished';
    } elseif ($ap === 'approved') {
        $editPublishStatusLabel = 'Published';
    } elseif ($ap === 'pending') {
        $editPublishStatusLabel = 'Pending approval';
    } else {
        $editPublishStatusLabel = $ap !== '' ? ucfirst($ap) : '—';
    }
    $createdRaw = trim((string) ($editProduct['created_at'] ?? ''));
    if ($createdRaw !== '') {
        try {
            $editPublishAtDisplay = (new DateTimeImmutable($createdRaw))->format('d M Y, g:i A');
        } catch (Exception) {
            $editPublishAtDisplay = $createdRaw;
        }
    } else {
        $editPublishAtDisplay = '—';
    }
}

$pageTitle = $isEditMode ? 'Edit product' : 'Add product';

$productTypesByCategory = seller_product_types_by_category();

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="seller-add-product-page seller-add-product--riho">
          <nav class="seller-wizard-breadcrumb" aria-label="Breadcrumb">
            <a href="products.php">ECommerce</a>
            <span class="seller-wizard-breadcrumb__sep" aria-hidden="true">/</span>
            <span class="seller-wizard-breadcrumb__current"><?= $isEditMode ? 'Edit Product' : 'Add Product' ?></span>
          </nav>

          <div class="seller-wizard-page-head">
            <h1 class="seller-wizard-page-title"><?= $isEditMode ? 'Edit Product' : 'Add Product' ?></h1>
            <a class="seller-wizard-back-link" href="products.php">← Back to products</a>
          </div>

          <?php if ($wizardFormError !== ''): ?>
            <div class="seller-alert seller-alert--error seller-add-product-wizard-flash"><?= h($wizardFormError) ?></div>
          <?php endif; ?>

          <?php if (!$canAddProducts): ?>
            <div class="seller-alert seller-alert--warn seller-add-product-kyc">
              <?php if (!$kycCompleted): ?>
                KYC aur bank details complete nahi hai. Pehle <a class="seller-drawer-alert-link" href="kyc-details.php">KYC details fill</a> karein.
              <?php else: ?>
                Admin ne abhi aapka KYC approve nahi kiya. Tab tak naye products add nahi ho sakte.
                <?php if ($kycRejectionReason !== ''): ?>
                  Last review reason: <?= h($kycRejectionReason) ?>.
                <?php endif; ?>
                <a class="seller-drawer-alert-link" href="kyc-details.php">Update KYC</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="seller-product-wizard seller-wizard-card card" data-can-add="<?= $canAddProducts ? '1' : '0' ?>">
            <aside class="seller-wizard-steps" aria-label="Product form steps">
              <h2 class="seller-wizard-sidebar-title">Product Form</h2>
              <ol class="seller-wizard-steps__list">
                <li class="seller-wizard-step seller-wizard-step--active" data-step="1">
                  <span class="seller-wizard-step__rail" aria-hidden="true"></span>
                  <span class="seller-wizard-step__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/><rect width="20" height="20" x="2" y="2" rx="2"/></svg>
                  </span>
                  <span class="seller-wizard-step__text">
                    <span class="seller-wizard-step__title">Add Product Details</span>
                    <span class="seller-wizard-step__desc">Add Product name &amp; details</span>
                  </span>
                </li>
                <li class="seller-wizard-step" data-step="2">
                  <span class="seller-wizard-step__rail" aria-hidden="true"></span>
                  <span class="seller-wizard-step__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                  </span>
                  <span class="seller-wizard-step__text">
                    <span class="seller-wizard-step__title">Product gallery</span>
                    <span class="seller-wizard-step__desc">thumbnail &amp; Add Product Gallery</span>
                  </span>
                </li>
                <li class="seller-wizard-step" data-step="3">
                  <span class="seller-wizard-step__rail" aria-hidden="true"></span>
                  <span class="seller-wizard-step__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.19a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.19a2 2 0 0 1-1.66 0L2 12.65"/></svg>
                  </span>
                  <span class="seller-wizard-step__text">
                    <span class="seller-wizard-step__title">Product Categories</span>
                    <span class="seller-wizard-step__desc">Add Product category &amp; status</span>
                  </span>
                </li>
                <li class="seller-wizard-step" data-step="4">
                  <span class="seller-wizard-step__rail" aria-hidden="true"></span>
                  <span class="seller-wizard-step__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                  </span>
                  <span class="seller-wizard-step__text">
                    <span class="seller-wizard-step__title">Selling prices</span>
                    <span class="seller-wizard-step__desc">Add Product basic price &amp; Discount</span>
                  </span>
                </li>
                <li class="seller-wizard-step" data-step="5">
                  <span class="seller-wizard-step__rail" aria-hidden="true"></span>
                  <span class="seller-wizard-step__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                  </span>
                  <span class="seller-wizard-step__text">
                    <span class="seller-wizard-step__title">Advance</span>
                    <span class="seller-wizard-step__desc">Add Meta details &amp; Inventory details</span>
                  </span>
                </li>
                <li class="seller-wizard-step" data-step="6">
                  <span class="seller-wizard-step__rail seller-wizard-step__rail--last" aria-hidden="true"></span>
                  <span class="seller-wizard-step__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                  </span>
                  <span class="seller-wizard-step__text">
                    <span class="seller-wizard-step__title">Shipping</span>
                    <span class="seller-wizard-step__desc">Weight, dimensions &amp; shipping class</span>
                  </span>
                </li>
              </ol>
            </aside>

            <div class="seller-wizard-main">
              <h2 class="seller-wizard-form-title visually-hidden">Product Form</h2>

              <form class="seller-wizard-form" id="sellerAddProductWizard" method="post" action="products.php" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="return_to" value="add_wizard">
                <input type="hidden" name="action" value="<?= $isEditMode ? 'edit_product' : 'add_product' ?>">
                <?php if ($isEditMode): ?>
                  <input type="hidden" name="product_id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">
                  <input type="hidden" name="emoji" value="<?= h((string) ($editProduct['emoji'] ?? '📦')) ?>">
                <?php endif; ?>
                <input type="file" id="wizard_images_combined" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,image/*" class="seller-wizard-file-native" tabindex="-1" aria-hidden="true">

                <div class="seller-wizard-panel seller-wizard-panel--active" data-panel="1" role="tabpanel">
                  <div class="seller-form-field seller-wizard-sku-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                    <span class="seller-form-field__label" id="wizard_sku_mode_label">SKU</span>
                    <?php if ($isEditMode): ?>
                      <input
                        type="text"
                        id="wizard_sku"
                        class="seller-input-wrap__input seller-wizard-sku-readonly"
                        value="<?= h((string) ($editProduct['sku'] ?? '')) ?>"
                        readonly
                        tabindex="0"
                        aria-readonly="true"
                      >
                      <p class="seller-form-field__hint">SKU unique identifier hai — create ke baad change nahi hota.</p>
                    <?php else: ?>
                    <div class="seller-wizard-sku-mode" role="radiogroup" aria-labelledby="wizard_sku_mode_label">
                      <label class="seller-wizard-sku-mode-opt">
                        <input type="radio" name="wizard_sku_mode" value="auto" checked <?= $canAddProducts ? '' : 'disabled' ?>>
                        <span>Auto-generate on save</span>
                      </label>
                      <label class="seller-wizard-sku-mode-opt">
                        <input type="radio" name="wizard_sku_mode" value="manual" <?= $canAddProducts ? '' : 'disabled' ?>>
                        <span>Manual</span>
                      </label>
                    </div>
                    <div class="seller-wizard-sku-manual" id="wizard_sku_manual_wrap" hidden>
                      <div class="seller-wizard-sku-row">
                        <input
                          type="text"
                          id="wizard_sku"
                          name="sku"
                          maxlength="40"
                          class="seller-input-wrap__input"
                          placeholder="e.g. FAS-SHIRT-001"
                          autocomplete="off"
                          inputmode="text"
                          disabled
                          value=""
                        >
                        <button type="button" class="admin-btn admin-btn--ghost-light seller-wizard-sku-gen-btn" id="wizard_sku_generate_btn" <?= $canAddProducts ? '' : 'disabled' ?>>Auto-generate SKU</button>
                      </div>
                      <p class="seller-form-field__hint seller-wizard-sku-manual-hint">4–40 characters · sirf A–Z, 0–9, hyphen aur underscore</p>
                    </div>
                    <p class="seller-form-field__hint" id="wizard_sku_auto_hint">Khali chhodoge to save par system SKU banayega (category + product name se).</p>
                    <?php endif; ?>
                  </div>

                  <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                    <label class="seller-form-field__label" for="wizard_product_title">
                      Product Title <span class="seller-req" aria-hidden="true">*</span>
                    </label>
                    <div class="seller-input-wrap" id="wizard_title_wrap">
                      <input
                        type="text"
                        id="wizard_product_title"
                        name="name"
                        class="seller-input-wrap__input"
                        placeholder="Product title"
                        autocomplete="off"
                        <?= $canAddProducts ? '' : 'disabled' ?>
                        value="<?= $isEditMode ? h((string) ($editProduct['name'] ?? '')) : '' ?>"
                      >
                      <span class="seller-input-wrap__icon seller-input-wrap__icon--error" id="wizard_title_err_icon" hidden aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                      </span>
                    </div>
                    <p class="seller-form-field__success" id="wizard_title_ok" hidden>Looks good!</p>
                    <p class="seller-form-field__error" id="wizard_title_error" hidden>A product name is required and recommended to be unique.</p>
                    <p class="seller-form-field__hint">Kam se kam 2 shabd (sirf ek letter ya ek shabd se Next / Submit nahi hoga).</p>
                  </div>

                  <div class="seller-form-field seller-form-field--editor<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                    <span class="seller-form-field__label">Description</span>
                    <div class="seller-quill-wrap" id="wizard_description_wrap">
                      <div id="wizard_description_editor" class="seller-quill-editor<?= !$canAddProducts ? ' seller-quill-editor--disabled' : '' ?>"></div>
                    </div>
                    <textarea id="wizard_description" name="description" hidden <?= $canAddProducts ? '' : 'disabled' ?>><?= $isEditMode ? h((string) ($editProduct['description'] ?? '')) : '' ?></textarea>
                    <p class="seller-form-field__hint">Improve product visibility by adding a compelling description. Kam se kam 2 shabd zaroori hain.</p>
                    <p class="seller-form-field__error" id="wizard_description_error" hidden>Please enter at least 2 words in the description.</p>
                  </div>
                </div>

                <div class="seller-wizard-panel" data-panel="2" role="tabpanel" hidden>
                  <div class="seller-wizard-drop-grid">
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <span class="seller-form-field__label">Product Image</span>
                      <div class="seller-wizard-dropzone" id="wizard_drop_main" role="button" tabindex="0" aria-label="Upload main product image">
                        <input type="file" id="wizard_file_main" class="seller-wizard-dropzone__input" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" aria-hidden="true" <?= $canAddProducts ? '' : 'disabled' ?>>
                        <span class="seller-wizard-dropzone__title">Drag your image here, or browser</span>
                        <span class="seller-wizard-dropzone__hint">SVG, PNG, JPG or GIF</span>
                      </div>
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <span class="seller-form-field__label">Product Gallery</span>
                      <div class="seller-wizard-dropzone seller-wizard-dropzone--wide" id="wizard_drop_gallery" role="button" tabindex="0" aria-label="Upload gallery images">
                        <input type="file" id="wizard_file_gallery" class="seller-wizard-dropzone__input" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" multiple aria-hidden="true" <?= $canAddProducts ? '' : 'disabled' ?>>
                        <span class="seller-wizard-dropzone__title">Drag files here</span>
                        <span class="seller-wizard-dropzone__hint">Add Product Gallery Images (max 6)</span>
                      </div>
                      <p class="seller-form-field__hint">Gallery mein maximum 6 images — main product image alag hai.</p>
                      <p class="seller-form-field__error" id="wizard_gallery_error" hidden>Maximum 6 gallery images — baaki hata di gayi.</p>
                    </div>
                  </div>
                  <?php if ($isEditMode && $editGalleryPaths !== []): ?>
                    <div class="seller-wizard-current-images">
                      <p class="seller-form-field__hint">Abhi ki photos — nayi upload tabhi replace karti hai jab aap files chunen.</p>
                      <div class="seller-wizard-current-images__grid">
                        <?php foreach ($editGalleryPaths as $gpath): ?>
                          <img class="seller-wizard-current-images__thumb" src="<?= h('../' . ltrim((string) $gpath, '/')) ?>" alt="" loading="lazy" decoding="async">
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="seller-wizard-panel" data-panel="3" role="tabpanel" hidden>
                  <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                    <span class="seller-form-field__label">Select category <span class="seller-req" aria-hidden="true">*</span></span>
                    <div class="seller-wizard-chips" id="wizard_category_chips" role="group" aria-label="Category">
                      <?php foreach ($allowedCategories as $cat): ?>
                        <?php
                        $key = strtolower((string) $cat);
                        $label = $categoryLabels[$key] ?? ucfirst($key);
                        ?>
                        <button type="button" class="seller-wizard-chip" data-value="<?= h($key) ?>"><?= h($label) ?></button>
                      <?php endforeach; ?>
                      <?php if ($allowedCategories === []): ?>
                        <p class="seller-form-field__hint">Admin ne abhi categories assign nahi ki hain.</p>
                      <?php endif; ?>
                    </div>
                    <select id="wizard_category" name="category" class="seller-wizard-select visually-hidden" aria-label="Category" <?= $canAddProducts ? '' : 'disabled' ?>>
                      <option value="">Select category</option>
                      <?php foreach ($allowedCategories as $cat): ?>
                        <?php
                        $key = strtolower((string) $cat);
                        $label = $categoryLabels[$key] ?? ucfirst($key);
                        $catSel = $isEditMode && strtolower((string) ($editProduct['category'] ?? '')) === $key;
                        ?>
                        <option value="<?= h($key) ?>"<?= $catSel ? ' selected' : '' ?>><?= h($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <p class="seller-form-field__hint">Pehle category chuno — phir product type.</p>
                  </div>

                  <div class="seller-form-field seller-wizard-product-type-wrap<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>" id="wizard_product_type_field" hidden>
                    <span class="seller-form-field__label">Product type <span class="seller-req" aria-hidden="true">*</span></span>
                    <?php foreach ($allowedCategories as $cat): ?>
                      <?php
                      $ck = strtolower((string) $cat);
                      $ptList = $productTypesByCategory[$ck] ?? [['slug' => 'general', 'label' => 'General']];
                      ?>
                      <div class="seller-wizard-product-type-panel" data-for-category="<?= h($ck) ?>" hidden>
                        <div class="seller-wizard-chips seller-wizard-chips--wrap" role="group" aria-label="Product type for <?= h($ck) ?>">
                          <?php foreach ($ptList as $pt): ?>
                            <button type="button" class="seller-wizard-chip seller-wizard-chip--ptype" data-value="<?= h($pt['slug']) ?>"><?= h($pt['label']) ?></button>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                    <input type="hidden" name="product_type" id="wizard_product_type" value="<?= $isEditMode ? h((string) ($editProduct['product_type'] ?? '')) : '' ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    <p class="seller-form-field__hint">Jaise Fashion → Jeans → size 28,30,32…; Shoes → UK 6,7…; Shirt / T-shirt → S,M,L…</p>
                  </div>

                  <div class="seller-wizard-offer-card seller-wizard-merch-card<?= !$canAddProducts ? ' seller-wizard-offer-card--disabled' : '' ?>">
                    <div class="seller-wizard-offer-card__head">
                      <h3 class="seller-wizard-offer-card__title">Merchandising</h3>
                      <p class="seller-wizard-offer-card__sub">Badge aur brand line — optional lekin storefront par helpful.</p>
                    </div>
                    <div class="seller-wizard-offer-card__body">
                      <div class="seller-wizard-offer-grid">
                        <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                          <label class="seller-form-field__label" for="wizard_badge">Badge</label>
                          <input
                            type="text"
                            id="wizard_badge"
                            name="badge"
                            maxlength="64"
                            class="seller-input-wrap__input"
                            placeholder="New / Sale / Hot"
                            autocomplete="off"
                            <?= $canAddProducts ? '' : 'disabled' ?>
                            value="<?= $isEditMode ? h((string) ($editProduct['badge'] ?? '')) : '' ?>"
                          >
                        </div>
                        <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                          <label class="seller-form-field__label" for="wizard_brand">Brand</label>
                          <input
                            type="text"
                            id="wizard_brand"
                            name="brand"
                            maxlength="255"
                            class="seller-input-wrap__input"
                            placeholder="LUXE"
                            autocomplete="organization"
                            <?= $canAddProducts ? '' : 'disabled' ?>
                            value="<?= $isEditMode ? h((string) ($editProduct['brand'] ?? 'LUXE')) : '' ?>"
                          >
                        </div>
                      </div>
                    </div>
                  </div>

                  <?php if ($isEditMode): ?>
                  <div class="seller-form-field">
                    <span class="seller-form-field__label">Publish status</span>
                    <p class="seller-wizard-readonly-value" id="wizard_publish_status_readonly"><?= h($editPublishStatusLabel) ?></p>
                    <p class="seller-form-field__hint">Yeh current listing state hai — edit se change nahi hota. Approval LUXE admin panel se hota hai.</p>
                  </div>
                  <div class="seller-form-field">
                    <span class="seller-form-field__label">Publish Date &amp; Time</span>
                    <p class="seller-wizard-readonly-value" id="wizard_publish_dt_readonly"><?= h($editPublishAtDisplay) ?></p>
                    <p class="seller-form-field__hint">Pehli save / create ka time (edit se change nahi hota).</p>
                  </div>
                  <?php else: ?>
                  <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                    <span class="seller-form-field__label">Publish Status</span>
                    <div class="seller-wizard-pills" role="group" aria-label="Publish status">
                      <button type="button" class="seller-wizard-pill seller-wizard-pill--active" data-pub="publish">Publish</button>
                      <button type="button" class="seller-wizard-pill" data-pub="draft">Drafts</button>
                      <button type="button" class="seller-wizard-pill" data-pub="unpublish">Unpublish</button>
                    </div>
                    <p class="seller-form-field__hint">Choose the status (LUXE listing approval flow unchanged).</p>
                  </div>

                  <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                    <label class="seller-form-field__label" for="wizard_publish_dt">Publish Date &amp; Time</label>
                    <input type="datetime-local" id="wizard_publish_dt" class="seller-input-wrap__input" <?= $canAddProducts ? '' : 'disabled' ?>>
                  </div>
                  <?php endif; ?>
                </div>

                <div class="seller-wizard-panel" data-panel="4" role="tabpanel" hidden>
                    <div class="seller-wizard-grid seller-wizard-grid--prices">
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_original_price">Initial cost <span class="seller-req" aria-hidden="true">*</span></label>
                      <input type="number" id="wizard_original_price" name="original_price" min="1" class="seller-input-wrap__input" placeholder="1499" <?= $canAddProducts ? '' : 'disabled' ?> value="<?= $isEditMode ? (int) ($editProduct['original_price'] ?? 0) : '' ?>">
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_price">Selling price <span class="seller-req" aria-hidden="true">*</span></label>
                      <input type="number" id="wizard_price" name="price" min="1" class="seller-input-wrap__input" placeholder="999" <?= $canAddProducts ? '' : 'disabled' ?> value="<?= $isEditMode ? (int) ($editProduct['price'] ?? 0) : '' ?>">
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_currency">Choose your currency</label>
                      <select id="wizard_currency" class="seller-wizard-select" disabled>
                        <option selected>Rupees ₹</option>
                      </select>
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_stock">Product stocks <span class="seller-req" aria-hidden="true">*</span></label>
                      <input type="number" id="wizard_stock" name="stock_qty" min="0" class="seller-input-wrap__input" value="<?= $isEditMode ? (int) ($editProduct['stock_qty'] ?? 0) : '0' ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                  </div>

                  <div class="seller-wizard-offer-card<?= !$canAddProducts ? ' seller-wizard-offer-card--disabled' : '' ?>">
                    <div class="seller-wizard-offer-card__head">
                      <h3 class="seller-wizard-offer-card__title">Offer strip</h3>
                      <p class="seller-wizard-offer-card__sub">Flash line, countdown timer display, aur bank copy — optional.</p>
                    </div>
                    <div class="seller-wizard-offer-card__body">
                      <div class="seller-wizard-offer-grid">
                        <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                          <label class="seller-form-field__label" for="wizard_offer_flash">Flash offer text</label>
                          <input
                            type="text"
                            id="wizard_offer_flash"
                            name="offer_flash_text"
                            maxlength="150"
                            class="seller-input-wrap__input"
                            placeholder="Flash deal ends in"
                            value="<?= $isEditMode ? h((string) ($editProduct['offer_flash_text'] ?? '')) : 'Flash deal ends in' ?>"
                            <?= $canAddProducts ? '' : 'disabled' ?>
                          >
                        </div>
                        <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                          <label class="seller-form-field__label" for="wizard_offer_countdown">Countdown (HH:MM:SS)</label>
                          <input
                            type="text"
                            id="wizard_offer_countdown"
                            name="offer_countdown"
                            maxlength="8"
                            class="seller-input-wrap__input seller-wizard-input-mono"
                            pattern="\d{1,2}:[0-5]\d:[0-5]\d"
                            placeholder="02:14:38"
                            value="<?= $isEditMode ? h(seller_format_offer_countdown((int) ($editProduct['offer_countdown_seconds'] ?? 0))) : '02:14:38' ?>"
                            <?= $canAddProducts ? '' : 'disabled' ?>
                          >
                        </div>
                      </div>
                      <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                        <label class="seller-form-field__label" for="wizard_offer_bank">Card / bank offer text</label>
                        <input
                          type="text"
                          id="wizard_offer_bank"
                          name="offer_bank_text"
                          maxlength="150"
                          class="seller-input-wrap__input"
                          placeholder="Extra 10% off with HDFC card"
                          value="<?= $isEditMode ? h((string) ($editProduct['offer_bank_text'] ?? '')) : 'Extra 10% off with HDFC card' ?>"
                          <?= $canAddProducts ? '' : 'disabled' ?>
                        >
                      </div>
                    </div>
                  </div>
                </div>

                <div class="seller-wizard-panel" data-panel="5" role="tabpanel" hidden>
                  <div class="seller-wizard-tabs" role="tablist">
                    <button type="button" class="seller-wizard-tab seller-wizard-tab--active" role="tab" aria-selected="true" data-tab="inv">Inventory</button>
                    <button type="button" class="seller-wizard-tab" role="tab" aria-selected="false" data-tab="mfg">Manufacturer</button>
                    <button type="button" class="seller-wizard-tab" role="tab" aria-selected="false" data-tab="add">Additional Options</button>
                    <button type="button" class="seller-wizard-tab" role="tab" aria-selected="false" data-tab="ship">Shipping</button>
                  </div>

                  <div class="seller-wizard-tab-panel" data-tab-panel="inv">
                    <label class="seller-wizard-check<?= !$canAddProducts ? ' seller-wizard-check--disabled' : '' ?>">
                      <input type="checkbox" disabled> Allow Backorders
                    </label>
                    <label class="seller-wizard-check<?= !$canAddProducts ? ' seller-wizard-check--disabled' : '' ?>">
                      <input type="checkbox" disabled> This is a digital Product
                    </label>

                    <div class="seller-form-field seller-wizard-variant-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <span class="seller-form-field__label">Size &amp; colour <span class="seller-wizard-variant-kicker">(category + product type)</span></span>
                      <p class="seller-form-field__hint seller-wizard-variant-hint" id="wizard_variant_hint">Pehle step 3 mein category aur product type chuno — phir yahan us type ke size aur colour dikhenge.</p>
                      <div class="seller-wizard-variant-block" id="wizard_variant_block" hidden>
                        <?php foreach ($allowedCategories as $cat): ?>
                          <?php
                          $ck = strtolower((string) $cat);
                          $ptList = $productTypesByCategory[$ck] ?? [['slug' => 'general', 'label' => 'General']];
                          $colors = $colorsByCategory[$ck] ?? $colorCatalogFull;
                          ?>
                          <?php foreach ($ptList as $pt): ?>
                            <?php
                            $ptSlug = $pt['slug'];
                            $sizes = seller_get_sizes_for_category_product_type($ck, $ptSlug);
                            ?>
                            <div class="seller-wizard-variant-cat" data-category="<?= h($ck) ?>" data-product-type="<?= h($ptSlug) ?>" hidden>
                              <div class="seller-wizard-opt-group">
                                <span class="seller-wizard-opt-label">Size options</span>
                                <div class="seller-wizard-opt-grid" role="group" aria-label="Size options">
                                  <?php foreach ($sizes as $sz): ?>
                                    <?php
                                    $sid = 'sz_' . $ck . '_' . $ptSlug . '_' . preg_replace('/[^a-z0-9]+/i', '_', $sz);
                                    ?>
                                    <label class="seller-wizard-opt-chip" for="<?= h($sid) ?>">
                                      <input type="checkbox" name="size_options[]" id="<?= h($sid) ?>" value="<?= h($sz) ?>" <?= $canAddProducts ? '' : 'disabled' ?><?= in_array($sz, $selectedSizesWizard, true) ? ' checked' : '' ?>>
                                      <span><?= h($sz) ?></span>
                                    </label>
                                  <?php endforeach; ?>
                                </div>
                              </div>
                              <div class="seller-wizard-opt-group">
                                <span class="seller-wizard-opt-label">Color options</span>
                                <div class="seller-wizard-opt-grid" role="group" aria-label="Color options">
                                  <?php foreach ($colors as $col): ?>
                                    <?php
                                    $cid = 'col_' . $ck . '_' . $ptSlug . '_' . preg_replace('/[^a-z0-9]+/i', '_', $col);
                                    ?>
                                    <label class="seller-wizard-opt-chip" for="<?= h($cid) ?>">
                                      <input type="checkbox" name="color_options[]" id="<?= h($cid) ?>" value="<?= h($col) ?>" <?= $canAddProducts ? '' : 'disabled' ?><?= in_array($col, $selectedColorsWizard, true) ? ' checked' : '' ?>>
                                      <span><?= h($col) ?></span>
                                    </label>
                                  <?php endforeach; ?>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        <?php endforeach; ?>
                      </div>
                    </div>

                  </div>

                  <div class="seller-wizard-tab-panel" data-tab-panel="mfg" hidden>
                    <p class="seller-form-field__hint seller-wizard-mfg-intro">Generic name, country, manufacturer aur packer ki details — labelling / compliance ke liye. Add aur edit dono me yahan se update ho sakti hai.</p>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_mfg_generic">Generic name</label>
                      <input
                        type="text"
                        id="wizard_mfg_generic"
                        name="manufacturer_generic_name"
                        maxlength="255"
                        class="seller-input-wrap__input"
                        placeholder="e.g. Paracetamol 500 mg"
                        autocomplete="off"
                        <?= $canAddProducts ? '' : 'disabled' ?>
                        value="<?= $isEditMode ? h((string) ($editProduct['manufacturer_generic_name'] ?? '')) : '' ?>"
                      >
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_mfg_country">Country</label>
                      <input
                        type="text"
                        id="wizard_mfg_country"
                        name="manufacturer_country"
                        maxlength="128"
                        class="seller-input-wrap__input"
                        placeholder="e.g. India"
                        autocomplete="country-name"
                        <?= $canAddProducts ? '' : 'disabled' ?>
                        value="<?= $isEditMode ? h((string) ($editProduct['manufacturer_country'] ?? '')) : '' ?>"
                      >
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_mfg_address">Name and address of the manufacturer</label>
                      <textarea
                        id="wizard_mfg_address"
                        name="manufacturer_name_address"
                        class="seller-wizard-textarea seller-wizard-textarea--mfg"
                        maxlength="2000"
                        rows="4"
                        placeholder="Full legal name, complete address, pin code…"
                        <?= $canAddProducts ? '' : 'disabled' ?>
                      ><?= $isEditMode ? h((string) ($editProduct['manufacturer_name_address'] ?? '')) : '' ?></textarea>
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_packer_address">Name and address of the packer</label>
                      <textarea
                        id="wizard_packer_address"
                        name="packer_name_address"
                        class="seller-wizard-textarea seller-wizard-textarea--mfg"
                        maxlength="2000"
                        rows="4"
                        placeholder="If different from manufacturer — full name &amp; address"
                        <?= $canAddProducts ? '' : 'disabled' ?>
                      ><?= $isEditMode ? h((string) ($editProduct['packer_name_address'] ?? '')) : '' ?></textarea>
                    </div>
                  </div>

                  <div class="seller-wizard-tab-panel" data-tab-panel="add" hidden>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_seo_title">Additional Tag Title</label>
                      <input type="text" id="wizard_seo_title" class="seller-input-wrap__input" placeholder="Add a new tag title" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_seo_tags">Specific Tags</label>
                      <input type="text" id="wizard_seo_tags" class="seller-input-wrap__input" placeholder="Keywords" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_seo_desc">Additional Description</label>
                      <textarea id="wizard_seo_desc" class="seller-wizard-textarea" rows="4" placeholder="Enhance your SEO ranking..." <?= $canAddProducts ? '' : 'disabled' ?>></textarea>
                      <p class="seller-form-field__hint">Enhance your SEO ranking with an added tag description for the product.</p>
                    </div>
                  </div>

                  <div class="seller-wizard-tab-panel" data-tab-panel="ship" hidden>
                    <p class="seller-form-field__hint">Shipping class product listing ke saath save hoti hai. Poora profile <a href="shipping-settings.php">Shipping Settings</a> me.</p>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_ship_class">Shipping Class</label>
                      <select id="wizard_ship_class" name="shipping_class" class="seller-wizard-select" <?= $canAddProducts ? '' : 'disabled' ?>>
                        <option value="standard"<?= $shippingClassWizard === 'standard' ? ' selected' : '' ?>>Standard shipping</option>
                        <option value="express"<?= $shippingClassWizard === 'express' ? ' selected' : '' ?>>Express shipping</option>
                        <option value="free"<?= $shippingClassWizard === 'free' ? ' selected' : '' ?>>Free shipping</option>
                      </select>
                      <p class="seller-form-field__hint">Teen options mein se ek chuno — Standard, Express, ya Free.</p>
                    </div>
                  </div>
                </div>

                <div class="seller-wizard-panel" data-panel="6" role="tabpanel" hidden>
                  <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                    <label class="seller-form-field__label" for="wizard_pickup_state">Where can I pick up my order?</label>
                    <select id="wizard_pickup_state" class="seller-wizard-select" <?= $canAddProducts ? '' : 'disabled' ?>>
                      <option value="">State</option>
                      <option>Gujarat</option>
                      <option>Punjab</option>
                      <option>Himachal Pradesh</option>
                      <option>Goa</option>
                      <option>Sikkim</option>
                      <option>Telangana</option>
                    </select>
                  </div>
                  <div class="seller-wizard-grid seller-wizard-grid--tight">
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_weight">Weight (kg)</label>
                      <input type="text" id="wizard_weight" class="seller-input-wrap__input" placeholder="0" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                    <div class="seller-form-field<?= !$canAddProducts ? ' seller-form-field--disabled' : '' ?>">
                      <label class="seller-form-field__label" for="wizard_dims">Dimensions</label>
                      <input type="text" id="wizard_dims" class="seller-input-wrap__input" placeholder="L × W × H" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                  </div>
                  <p class="seller-form-field__hint">Decide if the product is a digital or physical item. Shipping may be necessary for real-world items.</p>
                </div>

                <div class="seller-wizard-footer">
                  <button type="button" class="seller-wizard-btn-prev admin-btn admin-btn--ghost-light seller-wizard-btn-back" id="sellerWizardBack" hidden>Previous</button>
                  <div class="seller-wizard-footer__spacer"></div>
                  <button type="button" class="seller-wizard-btn-next admin-btn admin-btn--ghost-light" id="sellerWizardNext">
                    Next
                    <svg class="seller-wizard-btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                  </button>
                  <button type="submit" class="seller-wizard-btn-submit admin-btn admin-btn--primary" id="sellerWizardSubmit" hidden <?= $canAddProducts ? '' : 'disabled' ?>><?= $isEditMode ? 'Update product' : 'Submit' ?></button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js" crossorigin="anonymous"></script>
        <script>
          window.__SELLER_PRODUCT_WIZARD_BOOT__ = <?= json_encode([
              'editMode' => $isEditMode,
              'category' => $isEditMode ? strtolower((string) ($editProduct['category'] ?? '')) : '',
              'productType' => $isEditMode ? (string) ($editProduct['product_type'] ?? '') : '',
          ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        </script>
        <script>
          (function () {
            var root = document.querySelector('.seller-product-wizard');
            var canAdd = root && root.getAttribute('data-can-add') === '1';
            var form = document.getElementById('sellerAddProductWizard');
            var panels = document.querySelectorAll('.seller-wizard-panel');
            var steps = document.querySelectorAll('.seller-wizard-step');
            var btnBack = document.getElementById('sellerWizardBack');
            var btnNext = document.getElementById('sellerWizardNext');
            var btnSubmit = document.getElementById('sellerWizardSubmit');
            var titleInput = document.getElementById('wizard_product_title');
            var titleWrap = document.getElementById('wizard_title_wrap');
            var titleErr = document.getElementById('wizard_title_error');
            var titleOk = document.getElementById('wizard_title_ok');
            var titleErrIcon = document.getElementById('wizard_title_err_icon');
            var skuInput = document.getElementById('wizard_sku');
            var skuManualWrap = document.getElementById('wizard_sku_manual_wrap');
            var skuAutoHint = document.getElementById('wizard_sku_auto_hint');
            var skuGenBtn = document.getElementById('wizard_sku_generate_btn');
            var skuModeRadios = document.querySelectorAll('input[name="wizard_sku_mode"]');
            var descHidden = document.getElementById('wizard_description');
            var descWrap = document.getElementById('wizard_description_wrap');
            var descErr = document.getElementById('wizard_description_error');
            var TITLE_ERR_REQUIRED = 'A product name is required and recommended to be unique.';
            var TITLE_ERR_WORDS = 'Please enter at least 2 words in the product title.';

            function wordCount(str) {
              var s = String(str || '').trim();
              if (!s) return 0;
              return s.split(/\s+/).filter(Boolean).length;
            }

            function getDescriptionPlainText() {
              if (quill) {
                return quill.getText().replace(/\u00a0/g, ' ').trim();
              }
              if (descHidden && descHidden.value) {
                var tmp = document.createElement('div');
                tmp.innerHTML = descHidden.value;
                return (tmp.textContent || tmp.innerText || '').trim();
              }
              return '';
            }
            var combinedInput = document.getElementById('wizard_images_combined');
            var fileMain = document.getElementById('wizard_file_main');
            var fileGallery = document.getElementById('wizard_file_gallery');
            var dropMain = document.getElementById('wizard_drop_main');
            var dropGallery = document.getElementById('wizard_drop_gallery');
            var galleryErr = document.getElementById('wizard_gallery_error');
            var GALLERY_MAX = 6;
            var catSelect = document.getElementById('wizard_category');
            var current = 1;
            var maxStep = 6;
            var quill = null;
            var lastVariantKey = '';
            var boot = window.__SELLER_PRODUCT_WIZARD_BOOT__ || null;

            function bootstrapEditMode() {
              if (!boot || !boot.editMode) return;
              var cat = String(boot.category || '').toLowerCase();
              var pt = String(boot.productType || '').trim();
              if (catSelect) catSelect.value = cat;
              document.querySelectorAll('#wizard_category_chips .seller-wizard-chip').forEach(function (c) {
                var v = String(c.getAttribute('data-value') || '').toLowerCase();
                c.classList.toggle('seller-wizard-chip--active', v === cat && cat !== '');
              });
              document.querySelectorAll('.seller-wizard-product-type-panel').forEach(function (pan) {
                var fc = String(pan.getAttribute('data-for-category') || '').toLowerCase();
                pan.hidden = !cat || fc !== cat;
              });
              var ptInput0 = document.getElementById('wizard_product_type');
              if (ptInput0) ptInput0.value = pt;
              document.querySelectorAll('.seller-wizard-chip--ptype').forEach(function (chip) {
                var panel = chip.closest('.seller-wizard-product-type-panel');
                if (!panel || panel.hidden) return;
                var pv = String(chip.getAttribute('data-value') || '').trim();
                chip.classList.toggle('seller-wizard-chip--active', pv.toLowerCase() === pt.toLowerCase());
              });
              lastVariantKey = cat + '|' + pt.toLowerCase();
              var ptField0 = document.getElementById('wizard_product_type_field');
              if (ptField0) ptField0.hidden = !cat;
            }

            function clearVariantSelections() {
              var block = document.getElementById('wizard_variant_block');
              if (!block) return;
              block.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                cb.checked = false;
              });
            }

            function syncVariantPanels() {
              var cat = catSelect ? String(catSelect.value || '').trim().toLowerCase() : '';
              var ptInput = document.getElementById('wizard_product_type');
              var pt = ptInput ? String(ptInput.value || '').trim().toLowerCase() : '';
              var block = document.getElementById('wizard_variant_block');
              var hint = document.getElementById('wizard_variant_hint');
              var ptField = document.getElementById('wizard_product_type_field');
              if (!block || !hint) return;
              if (ptField) {
                ptField.hidden = !cat;
              }
              if (!cat) {
                block.hidden = true;
                hint.hidden = false;
                document.querySelectorAll('.seller-wizard-variant-cat').forEach(function (el) {
                  el.hidden = true;
                });
                return;
              }
              if (!pt) {
                block.hidden = true;
                hint.hidden = false;
                document.querySelectorAll('.seller-wizard-variant-cat').forEach(function (el) {
                  el.hidden = true;
                });
                return;
              }
              hint.hidden = true;
              block.hidden = false;
              document.querySelectorAll('.seller-wizard-variant-cat').forEach(function (el) {
                var c = String(el.getAttribute('data-category') || '').trim().toLowerCase();
                var p = String(el.getAttribute('data-product-type') || '').trim().toLowerCase();
                el.hidden = (c !== cat) || (p !== pt);
              });
            }

            function onProductTypeChanged() {
              var cat = catSelect ? String(catSelect.value || '').trim().toLowerCase() : '';
              var ptInput = document.getElementById('wizard_product_type');
              var pt = ptInput ? String(ptInput.value || '').trim().toLowerCase() : '';
              var newKey = cat + '|' + pt;
              if (newKey !== lastVariantKey) {
                clearVariantSelections();
                lastVariantKey = newKey;
              }
              syncVariantPanels();
            }

            function onCategoryChanged() {
              if (!catSelect) return;
              var cat = String(catSelect.value || '').trim().toLowerCase();
              var ptInput = document.getElementById('wizard_product_type');
              if (ptInput) ptInput.value = '';
              document.querySelectorAll('.seller-wizard-chip--ptype').forEach(function (c) {
                c.classList.remove('seller-wizard-chip--active');
              });
              document.querySelectorAll('.seller-wizard-product-type-panel').forEach(function (pan) {
                var fc = String(pan.getAttribute('data-for-category') || '').toLowerCase();
                pan.hidden = cat === '' || fc !== cat;
              });
              var newKey = cat + '|';
              if (newKey !== lastVariantKey) {
                clearVariantSelections();
                lastVariantKey = newKey;
              }
              syncVariantPanels();
            }

            function setStep(n) {
              current = n;
              steps.forEach(function (el) {
                var s = parseInt(el.getAttribute('data-step'), 10);
                el.classList.toggle('seller-wizard-step--active', s === n);
                el.classList.toggle('seller-wizard-step--done', s < n);
              });
              panels.forEach(function (el) {
                var p = parseInt(el.getAttribute('data-panel'), 10);
                var on = p === n;
                el.hidden = !on;
                el.classList.toggle('seller-wizard-panel--active', on);
              });
              btnBack.hidden = n <= 1;
              btnNext.hidden = n >= maxStep;
              btnSubmit.hidden = n < maxStep;
              if (n === 5) {
                syncVariantPanels();
              }
            }

            function clearTitleError() {
              if (!titleInput || !titleWrap || !titleErr) return;
              titleInput.classList.remove('seller-input-wrap__input--error');
              titleWrap.classList.remove('seller-input-wrap--error');
              titleErr.textContent = TITLE_ERR_REQUIRED;
              titleErr.hidden = true;
              if (titleErrIcon) titleErrIcon.hidden = true;
              titleInput.setAttribute('aria-invalid', 'false');
              updateTitleOk();
            }

            function clearDescError() {
              if (descErr) descErr.hidden = true;
              if (descWrap) descWrap.classList.remove('seller-quill-wrap--error');
            }

            function showDescError() {
              if (descErr) descErr.hidden = false;
              if (descWrap) descWrap.classList.add('seller-quill-wrap--error');
            }

            function updateTitleOk() {
              if (!titleOk || !titleInput) return;
              var v = titleInput.value.trim();
              var show = wordCount(v) >= 2 && !titleInput.classList.contains('seller-input-wrap__input--error');
              titleOk.hidden = !show;
            }

            function showTitleError() {
              if (!titleInput || !titleWrap || !titleErr) return;
              titleInput.classList.add('seller-input-wrap__input--error');
              titleWrap.classList.add('seller-input-wrap--error');
              titleErr.hidden = false;
              if (titleOk) titleOk.hidden = true;
              if (titleErrIcon) titleErrIcon.hidden = false;
              titleInput.setAttribute('aria-invalid', 'true');
            }

            function validateStep1() {
              if (!titleInput || !canAdd) return true;
              var v = titleInput.value.trim();
              if (v === '') {
                if (titleErr) titleErr.textContent = TITLE_ERR_REQUIRED;
                showTitleError();
                titleInput.focus();
                return false;
              }
              if (wordCount(v) < 2) {
                if (titleErr) titleErr.textContent = TITLE_ERR_WORDS;
                showTitleError();
                titleInput.focus();
                return false;
              }
              clearTitleError();

              var descPlain = getDescriptionPlainText();
              if (wordCount(descPlain) < 2) {
                showDescError();
                if (quill) {
                  quill.focus();
                }
                return false;
              }
              clearDescError();
              return true;
            }

            function mergeImageFiles() {
              if (!combinedInput || !fileMain || !fileGallery) return;
              var dt = new DataTransfer();
              if (fileMain.files && fileMain.files[0]) {
                dt.items.add(fileMain.files[0]);
              }
              if (fileGallery.files) {
                var cap = Math.min(fileGallery.files.length, GALLERY_MAX);
                for (var i = 0; i < cap; i++) {
                  dt.items.add(fileGallery.files[i]);
                }
              }
              combinedInput.files = dt.files;
            }

            function wireDropzone(zone, input, opts) {
              opts = opts || {};
              var maxFiles = opts.maxFiles;
              var isGallery = input === fileGallery;

              function syncGalleryError(truncated) {
                if (!isGallery) return;
                if (truncated) {
                  if (galleryErr) galleryErr.hidden = false;
                  zone.classList.add('seller-wizard-dropzone--error');
                } else {
                  if (galleryErr) galleryErr.hidden = true;
                  zone.classList.remove('seller-wizard-dropzone--error');
                }
              }

              if (!zone || !input) return;
              function pick() { input.click(); }
              zone.addEventListener('click', function (e) {
                if (e.target === input) return;
                pick();
              });
              zone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  pick();
                }
              });
              ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (ev) {
                zone.addEventListener(ev, function (e) {
                  e.preventDefault();
                  e.stopPropagation();
                });
              });
              zone.addEventListener('dragover', function () { zone.classList.add('seller-wizard-dropzone--drag'); });
              zone.addEventListener('dragleave', function () { zone.classList.remove('seller-wizard-dropzone--drag'); });
              zone.addEventListener('drop', function (e) {
                zone.classList.remove('seller-wizard-dropzone--drag');
                var files = e.dataTransfer && e.dataTransfer.files;
                if (!files || !files.length) return;
                var dt = new DataTransfer();
                var cap;
                if (input.multiple) {
                  cap = maxFiles != null ? Math.min(files.length, maxFiles) : files.length;
                } else {
                  cap = Math.min(files.length, 1);
                }
                for (var i = 0; i < cap; i++) {
                  dt.items.add(files[i]);
                }
                input.files = dt.files;
                if (input.multiple && maxFiles != null && files.length > maxFiles) {
                  syncGalleryError(true);
                } else if (isGallery) {
                  syncGalleryError(false);
                }
                zone.classList.add('seller-wizard-dropzone--filled');
              });
              input.addEventListener('change', function () {
                if (!input.files || !input.files.length) {
                  zone.classList.remove('seller-wizard-dropzone--filled');
                  if (isGallery) syncGalleryError(false);
                  return;
                }
                if (isGallery && maxFiles != null && input.files.length > maxFiles) {
                  var dt2 = new DataTransfer();
                  for (var j = 0; j < maxFiles; j++) {
                    dt2.items.add(input.files[j]);
                  }
                  input.files = dt2.files;
                  syncGalleryError(true);
                } else if (isGallery) {
                  syncGalleryError(false);
                }
                zone.classList.add('seller-wizard-dropzone--filled');
              });
            }

            if (canAdd && document.getElementById('wizard_description_editor')) {
              quill = new Quill('#wizard_description_editor', {
                theme: 'snow',
                placeholder: 'Enter your messages...',
                modules: {
                  toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ indent: '-1' }, { indent: '+1' }],
                    ['link', 'image', 'video']
                  ]
                }
              });
              if (descHidden && descHidden.value) {
                quill.root.innerHTML = descHidden.value;
              }
              quill.on('text-change', function () {
                clearDescError();
              });
              bootstrapEditMode();
            }

            if (titleInput) {
              titleInput.addEventListener('input', function () {
                clearTitleError();
                updateTitleOk();
              });
            }

            function applySkuMode() {
              if (!skuInput || !skuManualWrap || !skuAutoHint) return;
              var mode = 'auto';
              skuModeRadios.forEach(function (r) {
                if (r.checked) mode = r.value;
              });
              if (!canAdd) {
                skuInput.disabled = true;
                return;
              }
              if (mode === 'manual') {
                skuManualWrap.hidden = false;
                skuAutoHint.hidden = true;
                skuInput.disabled = false;
              } else {
                skuManualWrap.hidden = true;
                skuAutoHint.hidden = false;
                skuInput.value = '';
                skuInput.disabled = true;
              }
            }

            skuModeRadios.forEach(function (r) {
              r.addEventListener('change', applySkuMode);
            });

            function partSku(str, maxLen, fallback) {
              var clean = String(str || '').toUpperCase().replace(/[^A-Z0-9]+/g, '');
              if (!clean) return fallback;
              return clean.slice(0, maxLen);
            }
            function randomSkuChunk() {
              return Math.random().toString(36).slice(2, 6).toUpperCase();
            }

            if (skuGenBtn && skuInput && titleInput) {
              skuGenBtn.addEventListener('click', function () {
                var catEl = document.getElementById('wizard_category');
                var cat = partSku(catEl ? catEl.value : '', 3, 'PRD');
                var name = partSku(titleInput.value, 6, 'ITEM');
                skuInput.value = cat + '-' + name + '-' + randomSkuChunk();
                skuInput.focus();
              });
            }

            wireDropzone(dropMain, fileMain);
            wireDropzone(dropGallery, fileGallery, { maxFiles: GALLERY_MAX });

            document.querySelectorAll('#wizard_category_chips .seller-wizard-chip').forEach(function (chip) {
              chip.addEventListener('click', function () {
                var val = chip.getAttribute('data-value');
                document.querySelectorAll('#wizard_category_chips .seller-wizard-chip').forEach(function (c) {
                  c.classList.remove('seller-wizard-chip--active');
                });
                chip.classList.add('seller-wizard-chip--active');
                if (catSelect) {
                  catSelect.value = val || '';
                  onCategoryChanged();
                }
              });
            });

            document.querySelectorAll('.seller-wizard-chip--ptype').forEach(function (chip) {
              chip.addEventListener('click', function () {
                var panel = chip.closest('.seller-wizard-product-type-panel');
                if (!panel) return;
                panel.querySelectorAll('.seller-wizard-chip--ptype').forEach(function (c) {
                  c.classList.remove('seller-wizard-chip--active');
                });
                chip.classList.add('seller-wizard-chip--active');
                var ptInput = document.getElementById('wizard_product_type');
                if (ptInput) ptInput.value = chip.getAttribute('data-value') || '';
                onProductTypeChanged();
              });
            });

            if (catSelect) {
              catSelect.addEventListener('change', onCategoryChanged);
            }

            document.querySelectorAll('.seller-wizard-pill[data-pub]').forEach(function (pill) {
              pill.addEventListener('click', function () {
                var p = pill.closest('.seller-wizard-pills');
                if (!p) return;
                p.querySelectorAll('.seller-wizard-pill').forEach(function (x) { x.classList.remove('seller-wizard-pill--active'); });
                pill.classList.add('seller-wizard-pill--active');
              });
            });

            document.querySelectorAll('.seller-wizard-pills--compact .seller-wizard-pill').forEach(function (pill) {
              pill.addEventListener('click', function () {
                var p = pill.closest('.seller-wizard-pills');
                if (!p) return;
                p.querySelectorAll('.seller-wizard-pill').forEach(function (x) { x.classList.remove('seller-wizard-pill--active'); });
                pill.classList.add('seller-wizard-pill--active');
              });
            });

            document.querySelectorAll('.seller-wizard-tab').forEach(function (tab) {
              tab.addEventListener('click', function () {
                var id = tab.getAttribute('data-tab');
                document.querySelectorAll('.seller-wizard-tab').forEach(function (t) {
                  t.classList.toggle('seller-wizard-tab--active', t === tab);
                  t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                });
                document.querySelectorAll('.seller-wizard-tab-panel').forEach(function (pan) {
                  var on = pan.getAttribute('data-tab-panel') === id;
                  pan.hidden = !on;
                });
              });
            });

            function validateStep3() {
              if (!canAdd) return true;
              var catEl = document.getElementById('wizard_category');
              var ptEl = document.getElementById('wizard_product_type');
              if (!catEl || catEl.value.trim() === '') {
                setStep(3);
                if (catEl) catEl.focus();
                return false;
              }
              if (!ptEl || ptEl.value.trim() === '') {
                setStep(3);
                var firstPtype = document.querySelector('.seller-wizard-product-type-panel:not([hidden]) .seller-wizard-chip--ptype');
                if (firstPtype) firstPtype.focus();
                return false;
              }
              return true;
            }

            btnNext.addEventListener('click', function () {
              if (current === 1 && !validateStep1()) return;
              if (current === 3 && !validateStep3()) return;
              if (current < maxStep) setStep(current + 1);
            });

            btnBack.addEventListener('click', function () {
              if (current > 1) setStep(current - 1);
            });

            function validateFinal() {
              if (!canAdd) return false;
              if (!validateStep1()) return false;
              var cat = document.getElementById('wizard_category');
              var pt = document.getElementById('wizard_product_type');
              var price = document.getElementById('wizard_price');
              var mrp = document.getElementById('wizard_original_price');
              if (cat && cat.value.trim() === '') {
                setStep(3);
                cat.focus();
                return false;
              }
              if (pt && pt.value.trim() === '') {
                setStep(3);
                var firstPtype = document.querySelector('.seller-wizard-product-type-panel:not([hidden]) .seller-wizard-chip--ptype');
                if (firstPtype) firstPtype.focus();
                return false;
              }
              var pr = price ? parseInt(price.value, 10) : 0;
              var op = mrp ? parseInt(mrp.value, 10) : 0;
              if (!pr || pr <= 0 || !op || op <= 0) {
                setStep(4);
                if (price && (!pr || pr <= 0)) price.focus();
                else if (mrp) mrp.focus();
                return false;
              }
              return true;
            }

            form.addEventListener('submit', function (e) {
              if (!canAdd) {
                e.preventDefault();
                return;
              }
              mergeImageFiles();
              if (quill && descHidden) {
                descHidden.value = quill.root.innerHTML;
              }
              if (!validateFinal()) {
                e.preventDefault();
              }
            });

            setStep(1);
            if (!quill) {
              bootstrapEditMode();
            }
            syncVariantPanels();
            applySkuMode();
            if (titleInput) updateTitleOk();
          })();
        </script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
