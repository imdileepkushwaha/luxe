<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Products';
$activeNav = 'products';
$allowedCategories = is_array($seller['allowed_categories']) ? $seller['allowed_categories'] : [];
$kycCompleted = (int) ($seller['kyc_completed'] ?? 0) === 1;
$kycFinalApproved = (int) ($seller['kyc_final_approved'] ?? 0) === 1;
$canAddProducts = $kycCompleted && $kycFinalApproved;
$kycRejectionReason = trim((string) ($seller['kyc_rejection_reason'] ?? ''));
$sizeCatalog = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'UK 6', 'UK 7', 'UK 8', 'UK 9', 'UK 10', 'UK 11', 'UK 12', 'Free Size'];
$colorCatalog = ['Black', 'White', 'Blue', 'Navy', 'Red', 'Green', 'Yellow', 'Orange', 'Pink', 'Purple', 'Brown', 'Grey', 'Silver', 'Gold', 'Beige', 'Maroon'];
$selectedSizes = [];
$selectedColors = [];

/**
 * Creates a URL-safe slug.
 */
function seller_make_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    if ($value === '') {
        return 'product';
    }
    return substr($value, 0, 180);
}

function seller_unique_slug(PDO $pdo, string $baseSlug, int $ignoreProductId = 0): string
{
    $slug = $baseSlug;
    $i = 2;
    $st = $pdo->prepare('SELECT id FROM products WHERE slug = ? AND id != ? LIMIT 1');
    while (true) {
        $st->execute([$slug, $ignoreProductId]);
        if (!$st->fetch()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $i;
        $i++;
    }
}

function seller_sku_exists(PDO $pdo, string $sku, int $ignoreProductId = 0): bool
{
    $st = $pdo->prepare('SELECT id FROM products WHERE sku = ? AND id != ? LIMIT 1');
    $st->execute([$sku, $ignoreProductId]);
    return (bool) $st->fetchColumn();
}

function seller_generate_unique_sku(PDO $pdo, string $name, string $category, int $ignoreProductId = 0): string
{
    $cat = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $category) ?? '');
    $cat = $cat === '' ? 'PRD' : substr($cat, 0, 3);
    $nameSeed = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $name) ?? '');
    $nameSeed = $nameSeed === '' ? 'ITEM' : substr($nameSeed, 0, 6);

    for ($i = 0; $i < 12; $i++) {
        try {
            $rand = strtoupper(bin2hex(random_bytes(2)));
        } catch (Throwable) {
            $rand = strtoupper((string) mt_rand(1000, 9999));
        }
        $sku = $cat . '-' . $nameSeed . '-' . $rand;
        if (!seller_sku_exists($pdo, $sku, $ignoreProductId)) {
            return $sku;
        }
    }

    $fallback = $cat . '-' . $nameSeed . '-' . strtoupper(substr(md5((string) microtime(true)), 0, 6));
    if (!seller_sku_exists($pdo, $fallback, $ignoreProductId)) {
        return $fallback;
    }

    return $cat . '-' . strtoupper(substr(uniqid('', true), -8));
}

/**
 * @param mixed $posted
 * @param list<string> $allowed
 */
function seller_normalize_options_from_post($posted, array $allowed): string
{
    if (!is_array($posted)) {
        return '';
    }

    $allowedMap = [];
    foreach ($allowed as $a) {
        $allowedMap[strtolower($a)] = $a;
    }

    $clean = [];
    foreach ($posted as $part) {
        $key = strtolower(trim((string) $part));
        if ($key === '' || !isset($allowedMap[$key])) {
            continue;
        }
        $clean[] = $allowedMap[$key];
        if (count($clean) >= 12) {
            break;
        }
    }

    return implode(', ', array_values(array_unique($clean)));
}

/**
 * @param list<string> $allowed
 * @return list<string>
 */
function seller_parse_saved_options(string $value, array $allowed): array
{
    if (trim($value) === '') {
        return [];
    }

    $allowedMap = [];
    foreach ($allowed as $item) {
        $allowedMap[strtolower($item)] = $item;
    }

    $clean = [];
    foreach (explode(',', $value) as $part) {
        $key = strtolower(trim($part));
        if ($key === '' || !isset($allowedMap[$key])) {
            continue;
        }
        $clean[] = $allowedMap[$key];
    }

    return array_values(array_unique($clean));
}

function seller_parse_offer_countdown_to_seconds(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    if (!preg_match('/^(\d{1,2}):([0-5]\d):([0-5]\d)$/', $value, $m)) {
        return -1;
    }
    $hours = (int) $m[1];
    $minutes = (int) $m[2];
    $seconds = (int) $m[3];
    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

function seller_format_offer_countdown(int $seconds): string
{
    if ($seconds <= 0) {
        return '';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

/**
 * @return array{ok:bool,paths?:list<string>,error?:string}
 */
function seller_handle_product_images_upload(array $files, int $sellerId): array
{
    $names = $files['name'] ?? null;
    $tmpNames = $files['tmp_name'] ?? null;
    $errors = $files['error'] ?? null;
    $sizes = $files['size'] ?? null;
    if (!is_array($names) || !is_array($tmpNames) || !is_array($errors) || !is_array($sizes)) {
        return ['ok' => true, 'paths' => []];
    }

    $maxBytes = 4 * 1024 * 1024; // 4MB
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $extByMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $uploadDir = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'error' => 'Could not create uploads directory.'];
    }

    $paths = [];
    foreach ($names as $idx => $name) {
        if (count($paths) >= 6) {
            break;
        }

        $err = (int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || trim((string) $name) === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            if ($finfo) {
                finfo_close($finfo);
            }
            return ['ok' => false, 'error' => 'Image upload failed. Please try again.'];
        }

        $tmpName = (string) ($tmpNames[$idx] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            if ($finfo) {
                finfo_close($finfo);
            }
            return ['ok' => false, 'error' => 'Invalid upload request.'];
        }
        if ((int) ($sizes[$idx] ?? 0) > $maxBytes) {
            if ($finfo) {
                finfo_close($finfo);
            }
            return ['ok' => false, 'error' => 'Each image size should be 4MB or less.'];
        }

        $mime = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
        if (!isset($extByMime[$mime])) {
            if ($finfo) {
                finfo_close($finfo);
            }
            return ['ok' => false, 'error' => 'Only JPG, PNG, WEBP or GIF images are allowed.'];
        }

        try {
            $rand = bin2hex(random_bytes(4));
        } catch (Throwable) {
            $rand = (string) mt_rand(100000, 999999);
        }
        $fileName = 'seller-' . $sellerId . '-' . time() . '-' . $idx . '-' . $rand . '.' . $extByMime[$mime];
        $destAbs = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($tmpName, $destAbs)) {
            if ($finfo) {
                finfo_close($finfo);
            }
            return ['ok' => false, 'error' => 'Could not save uploaded images.'];
        }
        $paths[] = 'uploads/products/' . $fileName;
    }

    if ($finfo) {
        finfo_close($finfo);
    }

    return ['ok' => true, 'paths' => $paths];
}

$error = '';
$toastMessage = '';
$toastIsError = false;
$drawerMode = 'add';
$editingProduct = null;
$productByIdSt = $pdo->prepare(
    'SELECT id, name, sku, category, price, original_price, emoji, badge, brand, size_options, color_options, stock_qty, description, image_path,
            offer_flash_text, offer_countdown_seconds, offer_bank_text, active, approval_status
     FROM products
     WHERE id = ? AND seller_id = ?
     LIMIT 1'
);
$editIdFromQuery = (int) ($_GET['edit'] ?? 0);
if ($editIdFromQuery > 0) {
    $productByIdSt->execute([$editIdFromQuery, (int) $seller['id']]);
    $editingProduct = $productByIdSt->fetch() ?: null;
    if ($editingProduct) {
        $drawerMode = 'edit';
        $selectedSizes = seller_parse_saved_options((string) ($editingProduct['size_options'] ?? ''), $sizeCatalog);
        $selectedColors = seller_parse_saved_options((string) ($editingProduct['color_options'] ?? ''), $colorCatalog);
    }
}
$msg = (string) ($_GET['msg'] ?? '');
if ($msg === 'added') {
    $toastMessage = 'Product admin approval ke liye submit ho gaya. Approve hone ke baad store par dikhega.';
} elseif ($msg === 'updated') {
    $toastMessage = 'Product successfully update ho gaya.';
} elseif ($msg === 'deleted') {
    $toastMessage = 'Product delete ho gaya.';
} elseif ($msg === 'delete_fail') {
    $toastMessage = 'Product delete nahi ho paya.';
    $toastIsError = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'add_product');

    if ($action === 'delete_product') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            $del = $pdo->prepare('DELETE FROM products WHERE id = ? AND seller_id = ? LIMIT 1');
            $del->execute([$productId, (int) $seller['id']]);
            if ($del->rowCount() > 0) {
                header('Location: products.php?msg=deleted');
                exit;
            }
        }
        header('Location: products.php?msg=delete_fail');
        exit;
    }

    if ($action === 'add_product' || $action === 'edit_product') {
        $drawerMode = $action === 'edit_product' ? 'edit' : 'add';
        $editingProductId = 0;
        if ($drawerMode === 'edit') {
            $editingProductId = (int) ($_POST['product_id'] ?? 0);
            if ($editingProductId <= 0) {
                $error = 'Edit ke liye valid product select karein.';
            } else {
                $productByIdSt->execute([$editingProductId, (int) $seller['id']]);
                $editingProduct = $productByIdSt->fetch() ?: null;
                if (!$editingProduct) {
                    $error = 'Product nahi mila ya aapko access nahi hai.';
                }
            }
        }

        if (!$canAddProducts) {
            if (!$kycCompleted) {
                $error = 'Product add karne se pehle KYC & bank details complete karein.';
            } else {
                $error = 'KYC submitted hai, lekin final admin approval pending hai. Approval ke baad product add hoga.';
            }
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $category = strtolower(trim((string) ($_POST['category'] ?? '')));
        $price = (int) ($_POST['price'] ?? 0);
        $originalPrice = (int) ($_POST['original_price'] ?? 0);
        $stockQty = array_key_exists('stock_qty', $_POST)
            ? (int) $_POST['stock_qty']
            : (int) ($editingProduct['stock_qty'] ?? 0);
        $emoji = trim((string) ($_POST['emoji'] ?? '📦'));
        $badge = trim((string) ($_POST['badge'] ?? ''));
        $brand = trim((string) ($_POST['brand'] ?? 'LUXE'));
        $skuInput = strtoupper(trim((string) ($_POST['sku'] ?? '')));
        $selectedSizes = is_array($_POST['size_options'] ?? null) ? array_values(array_map('strval', $_POST['size_options'])) : [];
        $selectedColors = is_array($_POST['color_options'] ?? null) ? array_values(array_map('strval', $_POST['color_options'])) : [];
        $sizeOptions = seller_normalize_options_from_post($_POST['size_options'] ?? [], $sizeCatalog);
        $colorOptions = seller_normalize_options_from_post($_POST['color_options'] ?? [], $colorCatalog);
        $description = trim((string) ($_POST['description'] ?? ''));
        $offerFlashText = trim((string) ($_POST['offer_flash_text'] ?? ''));
        $offerCountdownInput = trim((string) ($_POST['offer_countdown'] ?? ''));
        $offerBankText = trim((string) ($_POST['offer_bank_text'] ?? ''));
        $offerCountdownSeconds = seller_parse_offer_countdown_to_seconds($offerCountdownInput);

        if ($error === '' && $name === '') {
            $error = 'Product name required hai.';
        } elseif ($error === '' && ($price <= 0 || $originalPrice <= 0)) {
            $error = 'Price aur original price valid honi chahiye.';
        } elseif ($error === '' && $stockQty < 0) {
            $error = 'Stock quantity negative nahi ho sakti.';
        } elseif ($error === '' && !in_array($category, $allowedCategories, true)) {
            $error = 'Aap is category me product add nahi kar sakte.';
        } elseif ($error === '' && $skuInput !== '' && !preg_match('/^[A-Z0-9_-]{4,40}$/', $skuInput)) {
            $error = 'SKU format invalid hai. 4-40 chars, sirf A-Z, 0-9, - aur _ allowed hai.';
        } elseif ($error === '' && $skuInput !== '' && seller_sku_exists($pdo, $skuInput, (int) ($editingProduct['id'] ?? 0))) {
            $error = 'Yeh SKU already use ho raha hai. Dusra SKU daalein ya auto-generate karein.';
        } elseif ($error === '' && strlen($offerFlashText) > 150) {
            $error = 'Flash offer text 150 characters se zyada nahi ho sakta.';
        } elseif ($error === '' && strlen($offerBankText) > 150) {
            $error = 'Card offer text 150 characters se zyada nahi ho sakta.';
        } elseif ($error === '' && $offerCountdownSeconds < 0) {
            $error = 'Offer countdown format HH:MM:SS me daalein (example: 02:30:00).';
        } elseif ($error === '') {
            $upload = seller_handle_product_images_upload($_FILES['images'] ?? [], (int) $seller['id']);
            if (!$upload['ok']) {
                $error = (string) ($upload['error'] ?? 'Images upload failed.');
            }
        }

        if ($error === '' && $action === 'add_product') {
            $baseSlug = seller_make_slug($name);
            $slug = seller_unique_slug($pdo, $baseSlug);
            $sku = $skuInput !== '' ? $skuInput : seller_generate_unique_sku($pdo, $name, $category);
            $ins = $pdo->prepare(
                'INSERT INTO products
                    (seller_id, name, slug, sku, category, price, original_price, emoji, badge, rating, review_count, brand, image_bg, image_path, size_options, color_options, stock_qty, description,
                     offer_flash_text, offer_countdown_seconds, offer_bank_text, active, approval_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 4.5, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, \'pending\')'
            );
            $uploadedPaths = is_array($upload['paths'] ?? null) ? $upload['paths'] : [];
            $mainImagePath = $uploadedPaths[0] ?? null;
            $ins->execute([
                (int) $seller['id'],
                $name,
                $slug,
                $sku,
                $category,
                $price,
                $originalPrice,
                $emoji === '' ? '📦' : $emoji,
                $badge,
                $brand === '' ? 'LUXE' : $brand,
                '#1a0a2e',
                $mainImagePath,
                $sizeOptions,
                $colorOptions,
                max(0, $stockQty),
                $description,
                $offerFlashText,
                max(0, $offerCountdownSeconds),
                $offerBankText,
            ]);
            $productId = (int) $pdo->lastInsertId();
            if ($uploadedPaths !== [] && $productId > 0) {
                $imgIns = $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)');
                foreach ($uploadedPaths as $i => $path) {
                    $imgIns->execute([$productId, $path, $i]);
                }
            }
            header('Location: products.php?msg=added');
            exit;
        }

        if ($error === '' && $action === 'edit_product') {
            $productId = (int) ($editingProduct['id'] ?? 0);
            if ($productId <= 0) {
                $error = 'Product update nahi ho paya.';
            } else {
                $baseSlug = seller_make_slug($name);
                $slug = seller_unique_slug($pdo, $baseSlug, $productId);
                $existingSku = strtoupper(trim((string) ($editingProduct['sku'] ?? '')));
                $sku = $skuInput !== '' ? $skuInput : ($existingSku !== '' ? $existingSku : seller_generate_unique_sku($pdo, $name, $category, $productId));
                $uploadedPaths = is_array($upload['paths'] ?? null) ? $upload['paths'] : [];
                $mainImagePath = $uploadedPaths[0] ?? null;

                if ($mainImagePath !== null) {
                    $upd = $pdo->prepare(
                        'UPDATE products
                         SET name = ?, slug = ?, sku = ?, category = ?, price = ?, original_price = ?, emoji = ?, badge = ?, brand = ?, image_path = ?, size_options = ?, color_options = ?, stock_qty = ?, description = ?
                            , offer_flash_text = ?, offer_countdown_seconds = ?, offer_bank_text = ?
                            , approval_status = CASE WHEN approval_status = \'approved\' THEN \'approved\' ELSE \'pending\' END
                         WHERE id = ? AND seller_id = ?
                         LIMIT 1'
                    );
                    $upd->execute([
                        $name,
                        $slug,
                        $sku,
                        $category,
                        $price,
                        $originalPrice,
                        $emoji === '' ? '📦' : $emoji,
                        $badge,
                        $brand === '' ? 'LUXE' : $brand,
                        $mainImagePath,
                        $sizeOptions,
                        $colorOptions,
                        max(0, $stockQty),
                        $description,
                        $offerFlashText,
                        max(0, $offerCountdownSeconds),
                        $offerBankText,
                        $productId,
                        (int) $seller['id'],
                    ]);
                } else {
                    $upd = $pdo->prepare(
                        'UPDATE products
                         SET name = ?, slug = ?, sku = ?, category = ?, price = ?, original_price = ?, emoji = ?, badge = ?, brand = ?, size_options = ?, color_options = ?, stock_qty = ?, description = ?
                            , offer_flash_text = ?, offer_countdown_seconds = ?, offer_bank_text = ?
                            , approval_status = CASE WHEN approval_status = \'approved\' THEN \'approved\' ELSE \'pending\' END
                         WHERE id = ? AND seller_id = ?
                         LIMIT 1'
                    );
                    $upd->execute([
                        $name,
                        $slug,
                        $sku,
                        $category,
                        $price,
                        $originalPrice,
                        $emoji === '' ? '📦' : $emoji,
                        $badge,
                        $brand === '' ? 'LUXE' : $brand,
                        $sizeOptions,
                        $colorOptions,
                        max(0, $stockQty),
                        $description,
                        $offerFlashText,
                        max(0, $offerCountdownSeconds),
                        $offerBankText,
                        $productId,
                        (int) $seller['id'],
                    ]);
                }

                if ($uploadedPaths !== []) {
                    $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
                    $imgIns = $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)');
                    foreach ($uploadedPaths as $i => $path) {
                        $imgIns->execute([$productId, $path, $i]);
                    }
                }
                header('Location: products.php?msg=updated');
                exit;
            }
        }
    }
}

$productsSt = $pdo->prepare(
    'SELECT p.id, p.name, p.slug, p.sku, p.category, p.price, p.original_price, p.emoji, p.badge, p.brand, p.image_path, p.size_options, p.color_options, p.stock_qty, p.description, p.active,
            p.offer_flash_text, p.offer_countdown_seconds, p.offer_bank_text, p.approval_status,
            COALESCE(v.variant_stock_sum, p.stock_qty) AS display_stock_qty
     FROM products p
     LEFT JOIN (
         SELECT product_id, SUM(stock_qty) AS variant_stock_sum
         FROM product_variant_inventory
         GROUP BY product_id
     ) v ON v.product_id = p.id
     WHERE p.seller_id = ?
     ORDER BY p.id DESC'
);
$productsSt->execute([(int) $seller['id']]);
$products = $productsSt->fetchAll();
$productCount = count($products);
$approvedCount = 0;
$pendingCount = 0;
$rejectedCount = 0;
$storeLiveCount = 0;
$lowStockCount = 0;
foreach ($products as $_p) {
    $ap = strtolower(trim((string) ($_p['approval_status'] ?? '')));
    if ($ap === 'approved') {
        $approvedCount++;
    } elseif ($ap === 'pending') {
        $pendingCount++;
    } else {
        $rejectedCount++;
    }
    if ($ap === 'approved' && (int) ($_p['active'] ?? 0) === 1) {
        $storeLiveCount++;
    }
    $dq = (int) ($_p['display_stock_qty'] ?? $_p['stock_qty'] ?? 0);
    if ($dq < 5) {
        $lowStockCount++;
    }
}
$productImagesMap = [];
if ($products !== []) {
    $gallerySt = $pdo->prepare(
        'SELECT pi.product_id, pi.image_path
         FROM product_images pi
         INNER JOIN products p ON p.id = pi.product_id
         WHERE p.seller_id = ?
         ORDER BY pi.product_id ASC, pi.sort_order ASC, pi.id ASC'
    );
    $gallerySt->execute([(int) $seller['id']]);
    foreach ($gallerySt->fetchAll() as $row) {
        $pid = (int) ($row['product_id'] ?? 0);
        $path = trim((string) ($row['image_path'] ?? ''));
        if ($pid <= 0 || $path === '') {
            continue;
        }
        if (!isset($productImagesMap[$pid])) {
            $productImagesMap[$pid] = [];
        }
        $productImagesMap[$pid][] = $path;
    }
}
$openProductDrawer = $error !== '' || $drawerMode === 'edit';

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-products-page-head">
          <div>
            <h1>Products</h1>
            <p class="seller-products-subtitle">Manage your catalogue — search, preview on the storefront, edit details, or add new listings. Discounts apply only after admin approves new items.</p>
          </div>
          <div class="admin-page-head__actions seller-products-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="inventory.php">Inventory</a>
            <button type="button" class="admin-btn admin-btn--primary" id="openProductDrawerBtn" aria-controls="productDrawer" aria-expanded="<?= $openProductDrawer ? 'true' : 'false' ?>">Add product</button>
          </div>
        </div>

        <div class="seller-products-kpis seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Total listings</div>
              <div class="seller-kpi-card__value"><?= (int) $productCount ?></div>
              <div class="seller-kpi-card__hint">All products in your account</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Live on store</div>
              <div class="seller-kpi-card__value"><?= (int) $storeLiveCount ?></div>
              <div class="seller-kpi-card__hint">Approved + active</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Pending review</div>
              <div class="seller-kpi-card__value"><?= (int) $pendingCount ?></div>
              <div class="seller-kpi-card__hint">Awaiting admin approval</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Low stock</div>
              <div class="seller-kpi-card__value"><?= (int) $lowStockCount ?></div>
              <div class="seller-kpi-card__hint">Fewer than 5 units (incl. out of stock)</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </div>
          </div>
        </div>

        <?php if ($rejectedCount > 0): ?>
          <div class="seller-alert seller-alert--warn seller-products-rejected-banner">
            <strong><?= (int) $rejectedCount ?></strong> listing<?= $rejectedCount === 1 ? '' : 's' ?> rejected — open <strong>Edit</strong>, fix details, and save to resubmit for approval.
          </div>
        <?php endif; ?>

        <div class="card seller-products-card">
          <div class="card-header">
            <div class="seller-card-head seller-card-head--inventory seller-products-card-head">
              <div>
                <h2 class="card-title">Catalogue</h2>
                <p class="card-subtitle seller-products-card-sub"><?= $productCount === 0 ? 'Add your first product to appear here after approval.' : (int) $productCount . ' product' . ($productCount === 1 ? '' : 's') . ' · ' . (int) $storeLiveCount . ' visible to buyers.' ?></p>
              </div>
              <div class="seller-inventory-toolbar seller-products-toolbar">
                <label class="seller-inventory-search-wrap seller-products-search" for="sellerProductsSearch">
                  <span class="seller-inventory-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="sellerProductsSearch"
                    class="seller-inventory-search-input"
                    placeholder="Search name, SKU, slug, category, brand…"
                    autocomplete="off"
                    aria-label="Search products"
                  >
                </label>
              </div>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap seller-products-table-wrap">
              <table class="admin-table seller-products-table">
                <thead>
                  <tr>
                    <th class="seller-products-th--id">ID</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Listing</th>
                    <th class="seller-products-th--actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($products as $p): ?>
                    <?php
                    $pid = (int) ($p['id'] ?? 0);
                    $displayStockQty = (int) ($p['display_stock_qty'] ?? $p['stock_qty'] ?? 0);
                    $gallery = [];
                    $mainImage = trim((string) ($p['image_path'] ?? ''));
                    if ($mainImage !== '') {
                        $gallery[] = $mainImage;
                    }
                    if (isset($productImagesMap[$pid]) && is_array($productImagesMap[$pid])) {
                        foreach ($productImagesMap[$pid] as $imgPath) {
                            $imgPath = trim((string) $imgPath);
                            if ($imgPath !== '') {
                                $gallery[] = $imgPath;
                            }
                        }
                    }
                    $gallery = array_values(array_unique($gallery));
                    $galleryJson = json_encode($gallery, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                    if (!is_string($galleryJson)) {
                        $galleryJson = '[]';
                    }
                    $apRaw = strtolower(trim((string) ($p['approval_status'] ?? 'approved')));
                    $productsSearchBlob = mb_strtolower(
                        (string) $pid . ' '
                        . trim((string) ($p['name'] ?? '')) . ' '
                        . trim((string) ($p['slug'] ?? '')) . ' '
                        . trim((string) ($p['sku'] ?? '')) . ' '
                        . trim((string) ($p['category'] ?? '')) . ' '
                        . trim((string) ($p['brand'] ?? '')) . ' '
                        . trim((string) ($p['badge'] ?? '')) . ' '
                        . (string) (int) ($p['price'] ?? 0) . ' '
                        . (string) (int) ($p['original_price'] ?? 0) . ' '
                        . (string) $displayStockQty . ' '
                        . ((int) ($p['active'] ?? 0) === 1 ? 'active' : 'inactive') . ' '
                        . $apRaw . ' '
                        . trim((string) ($p['size_options'] ?? '')) . ' '
                        . trim((string) ($p['color_options'] ?? ''))
                    );
                    ?>
                    <tr class="seller-product-row" data-products-search="<?= h($productsSearchBlob) ?>">
                      <td class="seller-products-td--id"><span class="seller-products-id"><?= (int) $p['id'] ?></span></td>
                      <td class="seller-product-cell--main">
                        <div class="seller-product-cell__row">
                          <?php if ((string) ($p['image_path'] ?? '') !== ''): ?>
                            <img class="seller-product-thumb" src="../<?= h((string) $p['image_path']) ?>" alt="" width="56" height="56" loading="lazy">
                          <?php else: ?>
                            <span class="seller-product-thumb seller-product-thumb--placeholder" aria-hidden="true"><?= h((string) ($p['emoji'] ?? '📦')) ?></span>
                          <?php endif; ?>
                          <div class="seller-product-cell__text">
                            <span class="seller-product-cell__name"><?= h((string) $p['name']) ?></span>
                            <span class="seller-product-cell__slug"><?= h((string) $p['slug']) ?></span>
                            <?php $brandList = trim((string) ($p['brand'] ?? '')); ?>
                            <?php if ($brandList !== ''): ?>
                              <span class="seller-product-cell__brand"><?= h($brandList) ?></span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td>
                        <?php $skuListVal = trim((string) ($p['sku'] ?? '')); ?>
                        <?php if ($skuListVal !== ''): ?>
                          <span class="seller-product-list-sku"><?= h($skuListVal) ?></span>
                        <?php else: ?>
                          <span class="seller-products-emdash">—</span>
                        <?php endif; ?>
                      </td>
                      <td><span class="seller-product-cat-pill"><?= h(ucfirst((string) $p['category'])) ?></span></td>
                      <td>
                        <div class="seller-product-price">
                          <span class="seller-product-price__sale">₹<?= number_format((int) $p['price'], 0, '.', ',') ?></span>
                          <span class="seller-product-price__mrp">MRP ₹<?= number_format((int) $p['original_price'], 0, '.', ',') ?></span>
                        </div>
                      </td>
                      <td>
                        <?php
                        $stockLow = $displayStockQty > 0 && $displayStockQty < 5;
                        if ($displayStockQty === 0) {
                            $stockClass = 'seller-stock-pill seller-stock-pill--out';
                        } elseif ($stockLow) {
                            $stockClass = 'seller-stock-pill seller-stock-pill--low';
                        } else {
                            $stockClass = 'seller-stock-pill';
                        }
                        ?>
                        <span class="<?= h($stockClass) ?>"><?= (int) $displayStockQty ?></span>
                        <?php if ($displayStockQty === 0): ?>
                          <span class="seller-stock-zero">Out of stock</span>
                        <?php elseif ($stockLow): ?>
                          <span class="seller-stock-low-hint">Low</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="seller-product-listing-stack">
                          <?php if ((int) $p['active'] === 1): ?>
                            <span class="seller-status-chip seller-status-chip--delivered">Active</span>
                          <?php else: ?>
                            <span class="seller-status-chip seller-status-chip--inactive">Inactive</span>
                          <?php endif; ?>
                          <?php
                        $ap = $apRaw;
                        if ($ap === 'approved'): ?>
                            <span class="seller-status-chip seller-status-chip--approved">Approved</span>
                          <?php elseif ($ap === 'pending'): ?>
                            <span class="seller-status-chip seller-status-chip--pending">Pending</span>
                          <?php else: ?>
                            <span class="seller-status-chip seller-status-chip--rejected">Rejected</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="seller-products-td--actions">
                        <div class="seller-product-actions">
                          <button
                            type="button"
                            class="seller-view-btn seller-product-actions__btn"
                            data-product-id="<?= (int) $p['id'] ?>"
                            data-name="<?= h((string) $p['name']) ?>"
                            data-slug="<?= h((string) $p['slug']) ?>"
                            data-sku="<?= h((string) ($p['sku'] ?? '')) ?>"
                            data-category="<?= h((string) $p['category']) ?>"
                            data-price="<?= (int) $p['price'] ?>"
                            data-original-price="<?= (int) $p['original_price'] ?>"
                            data-stock="<?= $displayStockQty ?>"
                            data-status="<?= (int) $p['active'] === 1 ? 'Active' : 'Inactive' ?>"
                            data-badge="<?= h((string) ($p['badge'] ?? '')) ?>"
                            data-brand="<?= h((string) ($p['brand'] ?? '')) ?>"
                            data-emoji="<?= h((string) ($p['emoji'] ?? '')) ?>"
                            data-sizes="<?= h((string) ($p['size_options'] ?? '')) ?>"
                            data-colors="<?= h((string) ($p['color_options'] ?? '')) ?>"
                            data-description="<?= h((string) ($p['description'] ?? '')) ?>"
                            data-offer-flash="<?= h((string) ($p['offer_flash_text'] ?? '')) ?>"
                            data-offer-countdown="<?= h(seller_format_offer_countdown((int) ($p['offer_countdown_seconds'] ?? 0))) ?>"
                            data-offer-bank="<?= h((string) ($p['offer_bank_text'] ?? '')) ?>"
                            data-image="<?= h((string) ($p['image_path'] ?? '')) ?>"
                            data-images="<?= h((string) $galleryJson) ?>"
                            data-preview-url="../product.php?id=<?= (int) $p['id'] ?>"
                          >View</button>
                          <a href="products.php?edit=<?= (int) $p['id'] ?>" class="seller-edit-btn seller-product-actions__btn">Edit</a>
                          <form method="post" class="seller-product-actions__form" onsubmit="return confirm('Kya aap is product ko delete karna chahte hain?');">
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                            <button type="submit" class="seller-delete-btn seller-product-actions__btn seller-product-actions__btn--danger">Delete</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($products === []): ?>
                    <tr class="seller-products-empty-placeholder">
                      <td colspan="8">
                        <div class="seller-products-empty">
                          <div class="seller-products-empty__icon" aria-hidden="true">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
                          </div>
                          <h3 class="seller-products-empty__title">No products yet</h3>
                          <p class="seller-products-empty__text"><?= $canAddProducts ? 'Create your first listing — it will go for admin approval before appearing on LUXE.' : 'Complete KYC and get admin approval to start adding products.' ?></p>
                          <?php if ($canAddProducts): ?>
                            <button type="button" class="admin-btn admin-btn--primary" id="openProductDrawerBtnEmpty">Add your first product</button>
                          <?php else: ?>
                            <a class="admin-btn admin-btn--primary" href="kyc-details.php">Complete KYC</a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <tr id="sellerProductsNoMatchRow" class="seller-products-no-match-row" style="display:none">
                      <td colspan="8" class="seller-products-no-match-cell">
                        <div class="seller-products-no-match-inner">
                          <span class="seller-products-no-match-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                          </span>
                          <div>
                            <strong>No matches</strong>
                            <p>Try another keyword — name, slug, SKU, category, or brand.</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="seller-drawer-backdrop" id="productViewBackdrop"></div>
        <aside class="seller-drawer seller-drawer--view" id="productViewDrawer" role="dialog" aria-modal="true" aria-labelledby="productViewTitle" aria-hidden="true">
          <div class="seller-drawer__head">
            <h2 class="seller-drawer__title" id="productViewTitle">Product details</h2>
            <button type="button" class="seller-drawer__close" id="closeProductViewBtn" aria-label="Close product details panel">✕</button>
          </div>
          <div class="seller-drawer__body">
            <div class="seller-view-slider" id="productViewSlider">
              <button type="button" class="seller-view-slider__nav seller-view-slider__nav--prev" id="productViewPrev" aria-label="Previous image">‹</button>
              <div class="seller-view-media" id="productViewMedia">No image</div>
              <button type="button" class="seller-view-slider__nav seller-view-slider__nav--next" id="productViewNext" aria-label="Next image">›</button>
            </div>
            <div class="seller-view-slider__meta" id="productViewCount">0 / 0</div>
            <h3 class="seller-view-name" id="productViewName">-</h3>
            <p class="seller-view-slug" id="productViewSlug">-</p>
            <div class="seller-view-grid">
              <div class="seller-view-item"><span class="seller-view-label">SKU</span><strong id="productViewSku">-</strong></div>
              <div class="seller-view-item"><span class="seller-view-label">Category</span><strong id="productViewCategory">-</strong></div>
              <div class="seller-view-item"><span class="seller-view-label">Brand</span><strong id="productViewBrand">-</strong></div>
              <div class="seller-view-item"><span class="seller-view-label">Price</span><strong id="productViewPrice">-</strong></div>
              <div class="seller-view-item"><span class="seller-view-label">Original</span><strong id="productViewOriginal">-</strong></div>
              <div class="seller-view-item"><span class="seller-view-label">Stock</span><strong id="productViewStock">-</strong></div>
              <div class="seller-view-item"><span class="seller-view-label">Status</span><strong id="productViewStatus">-</strong></div>
              <div class="seller-view-item"><span class="seller-view-label">Badge</span><strong id="productViewBadge">-</strong></div>
              <div class="seller-view-item"><span class="seller-view-label">Emoji</span><strong id="productViewEmoji">-</strong></div>
            </div>
            <div class="seller-view-item-block">
              <span class="seller-view-label">Sizes</span>
              <p id="productViewSizes">-</p>
            </div>
            <div class="seller-view-item-block">
              <span class="seller-view-label">Colors</span>
              <p id="productViewColors">-</p>
            </div>
            <div class="seller-view-item-block">
              <span class="seller-view-label">Description</span>
              <p id="productViewDescription">-</p>
            </div>
            <div class="seller-view-item-block">
              <span class="seller-view-label">Offers</span>
              <p id="productViewOfferFlash">Flash: -</p>
              <p id="productViewOfferCountdown">Countdown: -</p>
              <p id="productViewOfferBank">Card offer: -</p>
            </div>
            <div class="seller-actions" style="justify-content:flex-start">
              <a class="seller-preview-btn" id="productViewPreview" href="#" target="_blank" rel="noopener">Open public preview</a>
            </div>
          </div>
        </aside>

        <div class="seller-drawer-backdrop<?= $openProductDrawer ? ' is-visible' : '' ?>" id="productDrawerBackdrop"></div>
        <aside class="seller-drawer<?= $openProductDrawer ? ' is-open' : '' ?>" id="productDrawer" role="dialog" aria-modal="true" aria-labelledby="productDrawerTitle" aria-hidden="<?= $openProductDrawer ? 'false' : 'true' ?>">
          <div class="seller-drawer__head">
            <div class="seller-drawer__head-main">
              <h2 class="seller-drawer__title" id="productDrawerTitle"><?= $drawerMode === 'edit' ? 'Edit product' : 'Add new product' ?></h2>
              <p class="seller-drawer__subtitle"><?= $drawerMode === 'edit' ? 'Save karne par changes admin workflow me ja sakte hain.' : 'Required fields bharo — listing pehle admin approve karega, phir store par dikhegi.' ?></p>
            </div>
            <div class="seller-drawer__head-actions">
              <?php if ($drawerMode === 'edit'): ?>
                <a class="seller-drawer__switch-link" href="products.php">Switch to Add</a>
              <?php endif; ?>
              <button type="button" class="seller-drawer__close" id="closeProductDrawerBtn" aria-label="Close add product panel">✕</button>
            </div>
          </div>
          <div class="seller-drawer__body seller-drawer__body--product-form">
            <?php if ($error !== ''): ?>
              <div class="seller-alert seller-alert--error"><?= h($error) ?></div>
            <?php endif; ?>
            <?php if (!$canAddProducts): ?>
              <div class="seller-alert seller-alert--warn seller-product-drawer-kyc-alert">
                <?php if (!$kycCompleted): ?>
                  KYC aur bank details complete nahi hai. Pehle <a class="seller-drawer-alert-link" href="kyc-details.php">KYC details fill</a> karein.
                <?php else: ?>
                  KYC submit ho chuki hai. Final admin approval ke baad hi product add kar sakte hain.
                  <?php if ($kycRejectionReason !== ''): ?>
                    Last review reason: <?= h($kycRejectionReason) ?>.
                  <?php endif; ?>
                  <a class="seller-drawer-alert-link" href="kyc-details.php">Update KYC</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="seller-form seller-product-drawer-form">
              <input type="hidden" name="action" value="<?= $drawerMode === 'edit' ? 'edit_product' : 'add_product' ?>">
              <?php if ($drawerMode === 'edit'): ?>
                <input type="hidden" name="product_id" value="<?= (int) ($editingProduct['id'] ?? 0) ?>">
              <?php endif; ?>

              <div class="seller-product-drawer-scroll">
              <section class="seller-product-form-section" aria-labelledby="product-section-basics">
                <header class="seller-product-form-section__head">
                  <h3 class="seller-product-form-section__title" id="product-section-basics">Basics</h3>
                  <p class="seller-product-form-section__sub">Display name aur catalogue identity (SKU).</p>
                </header>
                <div class="seller-product-form-section__body">
                  <div class="seller-product-form-field">
                    <label for="name">Product name</label>
                    <input id="name" name="name" required placeholder="e.g. Smart Watch X2" value="<?= h((string) ($_POST['name'] ?? ($editingProduct['name'] ?? ''))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                  </div>
                  <div class="seller-form__row seller-product-form__row seller-product-form__row--sku">
                    <div class="seller-product-form-field seller-product-form-field--grow">
                      <label for="sku">SKU <span class="seller-product-form-optional">(manual ya auto)</span></label>
                      <input id="sku" name="sku" maxlength="40" placeholder="e.g. FAS-SHIRT-001" value="<?= h((string) ($_POST['sku'] ?? ($editingProduct['sku'] ?? ''))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                    <div class="seller-product-form-sku-action">
                      <button class="seller-view-btn seller-sku-generate-btn" id="generateSkuBtn" type="button" <?= $canAddProducts ? '' : 'disabled' ?>>Auto-generate SKU</button>
                    </div>
                  </div>
                  <p class="seller-help seller-product-form-hint seller-product-form-hint--sku">Khali chhodoge to save par system SKU generate karega.</p>
                </div>
              </section>

              <section class="seller-product-form-section" aria-labelledby="product-section-catalogue">
                <header class="seller-product-form-section__head">
                  <h3 class="seller-product-form-section__title" id="product-section-catalogue">Catalogue</h3>
                  <p class="seller-product-form-section__sub">Category sirf aapki assigned list se — chhota emoji tile icon ke liye.</p>
                </header>
                <div class="seller-product-form-section__body">
                  <div class="seller-form__row seller-product-form__row">
                    <div class="seller-product-form-field">
                      <label for="category">Category</label>
                      <select id="category" name="category" required <?= $canAddProducts ? '' : 'disabled' ?>>
                        <?php foreach ($allowedCategories as $cat): ?>
                          <option value="<?= h($cat) ?>"<?= ((string) ($_POST['category'] ?? ($editingProduct['category'] ?? '')) === $cat) ? ' selected' : '' ?>><?= h(ucfirst($cat)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="seller-product-form-field seller-product-form-field--emoji">
                      <label for="emoji">Emoji</label>
                      <input id="emoji" name="emoji" maxlength="16" placeholder="📦" value="<?= h((string) ($_POST['emoji'] ?? ($editingProduct['emoji'] ?? ''))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                  </div>
                </div>
              </section>

              <section class="seller-product-form-section" aria-labelledby="product-section-pricing">
                <header class="seller-product-form-section__head">
                  <h3 class="seller-product-form-section__title" id="product-section-pricing">Pricing &amp; stock</h3>
                  <p class="seller-product-form-section__sub">Selling vs MRP — inventory yahan set hota hai.</p>
                </header>
                <div class="seller-product-form-section__body">
                  <div class="seller-form__row seller-product-form__row">
                    <div class="seller-product-form-field">
                      <label for="price">Price (Rs)</label>
                      <input id="price" class="seller-product-input--money" type="number" name="price" min="1" required placeholder="999" value="<?= h((string) ($_POST['price'] ?? ($editingProduct['price'] ?? ''))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                    <div class="seller-product-form-field">
                      <label for="original_price">Original price (Rs)</label>
                      <input id="original_price" class="seller-product-input--money" type="number" name="original_price" min="1" required placeholder="1499" value="<?= h((string) ($_POST['original_price'] ?? ($editingProduct['original_price'] ?? ''))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                  </div>
                  <div class="seller-product-form-field seller-product-form-field--stock">
                    <label for="stock_qty">Stock quantity</label>
                    <input id="stock_qty" class="seller-product-input--qty" type="number" name="stock_qty" min="0" value="<?= h((string) ($_POST['stock_qty'] ?? ($editingProduct['stock_qty'] ?? '0'))) ?>" required <?= $canAddProducts ? '' : 'disabled' ?>>
                  </div>
                </div>
              </section>

              <section class="seller-product-form-section" aria-labelledby="product-section-merch">
                <header class="seller-product-form-section__head">
                  <h3 class="seller-product-form-section__title" id="product-section-merch">Merchandising</h3>
                  <p class="seller-product-form-section__sub">Badge aur brand line — optional lekin storefront par helpful.</p>
                </header>
                <div class="seller-product-form-section__body">
                  <div class="seller-form__row seller-product-form__row">
                    <div class="seller-product-form-field">
                      <label for="badge">Badge</label>
                      <input id="badge" name="badge" maxlength="64" placeholder="New / Sale / Hot" value="<?= h((string) ($_POST['badge'] ?? ($editingProduct['badge'] ?? ''))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                    <div class="seller-product-form-field">
                      <label for="brand">Brand</label>
                      <input id="brand" name="brand" maxlength="255" placeholder="LUXE" value="<?= h((string) ($_POST['brand'] ?? ($editingProduct['brand'] ?? 'LUXE'))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                  </div>
                </div>
              </section>

              <section class="seller-product-form-section" aria-labelledby="product-section-variants">
                <header class="seller-product-form-section__head">
                  <h3 class="seller-product-form-section__title" id="product-section-variants">Sizes &amp; colors</h3>
                  <p class="seller-product-form-section__sub">Ctrl / Cmd + click se multiple options.</p>
                </header>
                <div class="seller-product-form-section__body">
                  <div class="seller-form__row seller-product-form__row">
                    <div class="seller-product-form-field">
                      <label for="size_options">Size options</label>
                      <select id="size_options" name="size_options[]" class="seller-multi-select seller-multi-select--product" multiple size="6" <?= $canAddProducts ? '' : 'disabled' ?>>
                        <?php foreach ($sizeCatalog as $size): ?>
                          <option value="<?= h($size) ?>"<?= in_array($size, $selectedSizes, true) ? ' selected' : '' ?>><?= h($size) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <p class="seller-help seller-product-form-hint">Multiple sizes ek saath select kar sakte ho.</p>
                    </div>
                    <div class="seller-product-form-field">
                      <label for="color_options">Color options</label>
                      <select id="color_options" name="color_options[]" class="seller-multi-select seller-multi-select--product" multiple size="6" <?= $canAddProducts ? '' : 'disabled' ?>>
                        <?php foreach ($colorCatalog as $color): ?>
                          <option value="<?= h($color) ?>"<?= in_array($color, $selectedColors, true) ? ' selected' : '' ?>><?= h($color) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <p class="seller-help seller-product-form-hint">Multiple colors ek saath select kar sakte ho.</p>
                    </div>
                  </div>
                </div>
              </section>

              <section class="seller-product-form-section" aria-labelledby="product-section-desc">
                <header class="seller-product-form-section__head">
                  <h3 class="seller-product-form-section__title" id="product-section-desc">Description</h3>
                  <p class="seller-product-form-section__sub">Chhota sa clear copy buyers ke liye.</p>
                </header>
                <div class="seller-product-form-section__body">
                  <div class="seller-product-form-field">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Short product details" <?= $canAddProducts ? '' : 'disabled' ?>><?= h((string) ($_POST['description'] ?? ($editingProduct['description'] ?? ''))) ?></textarea>
                  </div>
                </div>
              </section>

              <section class="seller-product-form-section" aria-labelledby="product-section-offers">
                <header class="seller-product-form-section__head">
                  <h3 class="seller-product-form-section__title" id="product-section-offers">Offer strip</h3>
                  <p class="seller-product-form-section__sub">Flash line, countdown timer display, aur bank copy — optional.</p>
                </header>
                <div class="seller-product-form-section__body">
                  <div class="seller-form__row seller-product-form__row">
                    <div class="seller-product-form-field">
                      <label for="offer_flash_text">Flash offer text</label>
                      <input id="offer_flash_text" name="offer_flash_text" maxlength="150" placeholder="Flash deal ends in" value="<?= h((string) ($_POST['offer_flash_text'] ?? ($editingProduct['offer_flash_text'] ?? 'Flash deal ends in'))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                    <div class="seller-product-form-field">
                      <label for="offer_countdown">Countdown <span class="seller-product-form-optional">(HH:MM:SS)</span></label>
                      <input id="offer_countdown" class="seller-product-input--mono" name="offer_countdown" maxlength="8" pattern="\d{1,2}:[0-5]\d:[0-5]\d" placeholder="02:14:38" value="<?= h((string) ($_POST['offer_countdown'] ?? seller_format_offer_countdown((int) ($editingProduct['offer_countdown_seconds'] ?? 8078)))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                  </div>
                  <div class="seller-product-form-field">
                    <label for="offer_bank_text">Card / bank offer text</label>
                    <input id="offer_bank_text" name="offer_bank_text" maxlength="150" placeholder="Extra 10% off with HDFC card" value="<?= h((string) ($_POST['offer_bank_text'] ?? ($editingProduct['offer_bank_text'] ?? 'Extra 10% off with HDFC card'))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                  </div>
                </div>
              </section>

              <section class="seller-product-form-section" aria-labelledby="product-section-media">
                <header class="seller-product-form-section__head">
                  <h3 class="seller-product-form-section__title" id="product-section-media">Images</h3>
                  <p class="seller-product-form-section__sub">Max 6 files · JPG, PNG, WEBP, GIF · 4MB each.</p>
                </header>
                <div class="seller-product-form-section__body">
                  <div class="seller-product-form-field">
                    <label for="images">Product images <span class="seller-product-form-optional">(optional)</span></label>
                    <input id="images" class="seller-product-form-file" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" multiple <?= $canAddProducts ? '' : 'disabled' ?>>
                    <p class="seller-help seller-product-form-hint">
                      Gallery order upload order jaisi rahegi.
                      <?php if ($drawerMode === 'edit'): ?>
                        <strong>Edit mode:</strong> nayi upload purani gallery replace karti hai.
                      <?php endif; ?>
                      <span id="productImagesPickCount" class="seller-product-form-file-count" hidden></span>
                    </p>
                  </div>
                </div>
              </section>
              </div>

              <div class="seller-product-drawer-footer">
                <div class="seller-product-form-submit-panel">
                  <p class="seller-product-form-submit-panel__hint">Product hamesha aapki <strong>assigned categories</strong> ke andar hi save hota hai.</p>
                  <div class="seller-actions seller-product-form-actions">
                    <button class="admin-btn admin-btn--primary seller-product-form-submit-btn" type="submit" <?= $canAddProducts ? '' : 'disabled' ?>><?= $drawerMode === 'edit' ? 'Update product' : 'Add product' ?></button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </aside>

        <script>
          (function () {
            var searchInput = document.getElementById('sellerProductsSearch');
            if (searchInput) {
              var productRows = document.querySelectorAll('tr.seller-product-row');
              var noMatchRow = document.getElementById('sellerProductsNoMatchRow');
              function applyProductSearch() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                productRows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-products-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) {
                    return hay.indexOf(w) !== -1;
                  });
                  tr.style.display = show ? '' : 'none';
                  if (show) {
                    anyShown = true;
                  }
                });
                if (noMatchRow) {
                  noMatchRow.style.display = (words.length > 0 && !anyShown) ? '' : 'none';
                }
              }
              searchInput.addEventListener('input', applyProductSearch);
              searchInput.addEventListener('search', applyProductSearch);
            }
          })();
        </script>

        <script>
          (function () {
            var viewDrawer = document.getElementById('productViewDrawer');
            var viewBackdrop = document.getElementById('productViewBackdrop');
            var closeViewBtn = document.getElementById('closeProductViewBtn');
            var prevBtn = document.getElementById('productViewPrev');
            var nextBtn = document.getElementById('productViewNext');
            var countEl = document.getElementById('productViewCount');
            var viewButtons = document.querySelectorAll('.seller-view-btn');
            if (!viewDrawer || !viewBackdrop || !closeViewBtn || !prevBtn || !nextBtn || !countEl || !viewButtons.length) return;

            var lastFocused = null;
            var currentImages = [];
            var currentImageIndex = 0;
            function text(id, value) {
              var el = document.getElementById(id);
              if (el) el.textContent = value && String(value).trim() !== '' ? String(value) : '-';
            }

            function renderSlider() {
              var media = document.getElementById('productViewMedia');
              if (!media) return;
              if (!currentImages.length) {
                media.innerHTML = 'No image';
                countEl.textContent = '0 / 0';
                prevBtn.disabled = true;
                nextBtn.disabled = true;
                return;
              }
              var safeIndex = Math.max(0, Math.min(currentImages.length - 1, currentImageIndex));
              currentImageIndex = safeIndex;
              var currentPath = currentImages[currentImageIndex];
              var nameEl = document.getElementById('productViewName');
              var altText = nameEl ? nameEl.textContent : 'Product image';
              media.innerHTML = '<img src="../' + currentPath + '" alt="' + String(altText).replace(/"/g, '&quot;') + '">';
              countEl.textContent = (currentImageIndex + 1) + ' / ' + currentImages.length;
              prevBtn.disabled = currentImages.length <= 1;
              nextBtn.disabled = currentImages.length <= 1;
            }

            function setMedia(path, altText, imagesJson) {
              var media = document.getElementById('productViewMedia');
              if (!media) return;
              var parsed = [];
              if (imagesJson) {
                try {
                  var raw = JSON.parse(imagesJson);
                  if (Array.isArray(raw)) {
                    parsed = raw.filter(function (v) { return typeof v === 'string' && v.trim() !== ''; });
                  }
                } catch (e) {}
              }
              if (!parsed.length && path) {
                parsed = [path];
              }
              currentImages = parsed;
              currentImageIndex = 0;
              renderSlider();
            }

            function openView(btn) {
              lastFocused = document.activeElement;
              var d = btn.dataset;
              text('productViewName', d.name);
              text('productViewSlug', d.slug ? ('Slug: ' + d.slug) : '-');
              text('productViewSku', d.sku);
              text('productViewCategory', d.category);
              text('productViewBrand', d.brand);
              text('productViewPrice', d.price ? ('Rs ' + Number(d.price).toLocaleString('en-IN')) : '-');
              text('productViewOriginal', d.originalPrice ? ('Rs ' + Number(d.originalPrice).toLocaleString('en-IN')) : '-');
              text('productViewStock', d.stock);
              text('productViewStatus', d.status);
              text('productViewBadge', d.badge);
              text('productViewEmoji', d.emoji);
              text('productViewSizes', d.sizes);
              text('productViewColors', d.colors);
              text('productViewDescription', d.description);
              text('productViewOfferFlash', 'Flash: ' + (d.offerFlash || '-'));
              text('productViewOfferCountdown', 'Countdown: ' + (d.offerCountdown || '-'));
              text('productViewOfferBank', 'Card offer: ' + (d.offerBank || '-'));
              setMedia(d.image || '', d.name || 'Product image', d.images || '[]');
              var preview = document.getElementById('productViewPreview');
              if (preview) preview.href = d.previewUrl || '#';

              viewDrawer.classList.add('is-open');
              viewBackdrop.classList.add('is-visible');
              document.body.classList.add('seller-drawer-open');
              viewDrawer.setAttribute('aria-hidden', 'false');
              closeViewBtn.focus();
            }

            function closeView() {
              viewDrawer.classList.remove('is-open');
              viewBackdrop.classList.remove('is-visible');
              viewDrawer.setAttribute('aria-hidden', 'true');
              document.body.classList.remove('seller-drawer-open');
              if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
              }
            }

            viewButtons.forEach(function (btn) {
              btn.addEventListener('click', function () {
                openView(btn);
              });
            });
            prevBtn.addEventListener('click', function () {
              if (!currentImages.length) return;
              currentImageIndex = currentImageIndex <= 0 ? currentImages.length - 1 : currentImageIndex - 1;
              renderSlider();
            });
            nextBtn.addEventListener('click', function () {
              if (!currentImages.length) return;
              currentImageIndex = currentImageIndex >= currentImages.length - 1 ? 0 : currentImageIndex + 1;
              renderSlider();
            });
            closeViewBtn.addEventListener('click', closeView);
            viewBackdrop.addEventListener('click', closeView);
            document.addEventListener('keydown', function (event) {
              if (event.key === 'Escape' && viewDrawer.classList.contains('is-open')) {
                event.preventDefault();
                closeView();
              }
            });
          })();
        </script>

        <script>
          (function () {
            var skuInput = document.getElementById('sku');
            var skuBtn = document.getElementById('generateSkuBtn');
            var nameInput = document.getElementById('name');
            var categoryInput = document.getElementById('category');
            if (!skuInput || !skuBtn || !nameInput || !categoryInput) return;

            function part(str, maxLen, fallback) {
              var clean = String(str || '').toUpperCase().replace(/[^A-Z0-9]+/g, '');
              if (!clean) return fallback;
              return clean.slice(0, maxLen);
            }

            function randomChunk() {
              return Math.random().toString(36).slice(2, 6).toUpperCase();
            }

            skuBtn.addEventListener('click', function () {
              var cat = part(categoryInput.value, 3, 'PRD');
              var name = part(nameInput.value, 6, 'ITEM');
              skuInput.value = cat + '-' + name + '-' + randomChunk();
              skuInput.focus();
            });
          })();
        </script>

        <script>
          (function () {
            var openBtn = document.getElementById('openProductDrawerBtn');
            var closeBtn = document.getElementById('closeProductDrawerBtn');
            var drawer = document.getElementById('productDrawer');
            var backdrop = document.getElementById('productDrawerBackdrop');
            if (!openBtn || !closeBtn || !drawer || !backdrop) return;
            var focusableSelector = 'a[href], area[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
            var lastFocusedElement = null;

            function getFocusableEls() {
              var all = drawer.querySelectorAll(focusableSelector);
              return Array.prototype.filter.call(all, function (el) {
                return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
              });
            }

            function setDrawerState(open) {
              drawer.classList.toggle('is-open', open);
              backdrop.classList.toggle('is-visible', open);
              document.body.classList.toggle('seller-drawer-open', open);
              drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
              openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
              if (open) {
                var focusEls = getFocusableEls();
                if (focusEls.length > 0) {
                  focusEls[0].focus();
                } else {
                  drawer.setAttribute('tabindex', '-1');
                  drawer.focus();
                }
              } else if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
              }
            }

            openBtn.addEventListener('click', function () {
              lastFocusedElement = document.activeElement;
              setDrawerState(true);
            });
            closeBtn.addEventListener('click', function () { setDrawerState(false); });
            backdrop.addEventListener('click', function () { setDrawerState(false); });
            document.addEventListener('keydown', function (event) {
              if (!drawer.classList.contains('is-open')) return;
              if (event.key === 'Escape') {
                event.preventDefault();
                setDrawerState(false);
                return;
              }
              if (event.key !== 'Tab') return;
              var focusEls = getFocusableEls();
              if (focusEls.length === 0) {
                event.preventDefault();
                drawer.focus();
                return;
              }
              var first = focusEls[0];
              var last = focusEls[focusEls.length - 1];
              if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
              } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
              }
            });

            if (drawer.classList.contains('is-open')) {
              lastFocusedElement = openBtn;
              document.body.classList.add('seller-drawer-open');
              setDrawerState(true);
            }

            var openEmpty = document.getElementById('openProductDrawerBtnEmpty');
            if (openEmpty) {
              openEmpty.addEventListener('click', function () {
                lastFocusedElement = document.activeElement;
                setDrawerState(true);
              });
            }
          })();
        </script>

        <script>
          (function () {
            var imagesInput = document.getElementById('images');
            var countEl = document.getElementById('productImagesPickCount');
            if (!imagesInput || !countEl) return;
            imagesInput.addEventListener('change', function () {
              var n = imagesInput.files ? imagesInput.files.length : 0;
              if (n === 0) {
                countEl.textContent = '';
                countEl.setAttribute('hidden', '');
                return;
              }
              countEl.removeAttribute('hidden');
              countEl.textContent = ' \u2014 ' + n + ' file' + (n === 1 ? '' : 's') + ' selected';
            });
          })();
        </script>

        <?php if ($toastMessage !== ''): ?>
          <div id="sellerToast" class="seller-toast<?= $toastIsError ? ' seller-toast--error' : ' seller-toast--success' ?>" role="status">
            <?= h($toastMessage) ?>
          </div>
          <script>
            (function () {
              var toast = document.getElementById('sellerToast');
              if (!toast) return;
              if (window.history && window.history.replaceState) {
                var cleanUrl = window.location.pathname + window.location.hash;
                window.history.replaceState({}, document.title, cleanUrl);
              }
              requestAnimationFrame(function () {
                toast.classList.add('show');
              });
              setTimeout(function () {
                toast.classList.remove('show');
              }, 3000);
              setTimeout(function () {
                if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
              }, 3600);
            })();
          </script>
        <?php endif; ?>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
