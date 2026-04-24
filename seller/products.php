<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/seller_product_catalog.php';
require_once __DIR__ . '/../includes/seller_variant_inventory.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Products';
$activeNav = 'products';
$allowedCategories = is_array($seller['allowed_categories']) ? $seller['allowed_categories'] : [];
$kycCompleted = (int) ($seller['kyc_completed'] ?? 0) === 1;
$kycFinalApproved = (int) ($seller['kyc_final_approved'] ?? 0) === 1;
$canAddProducts = $kycCompleted && $kycFinalApproved;
$kycRejectionReason = trim((string) ($seller['kyc_rejection_reason'] ?? ''));
$sizeCatalog = seller_base_size_catalog();
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

/** Whitespace-separated words (Unicode-safe). */
function seller_word_count(string $text): int
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') {
        return 0;
    }
    $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

    return is_array($parts) ? count($parts) : 0;
}

/** @return 'standard'|'express'|'free' */
function seller_normalize_shipping_class(string $raw): string
{
    $v = strtolower(trim($raw));
    $allowed = ['standard', 'express', 'free'];

    return in_array($v, $allowed, true) ? $v : 'standard';
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

/** @return 'men'|'women'|'unisex' */
function seller_normalize_gender(string $raw): string
{
    $v = strtolower(trim($raw));
    if ($v === 'men' || $v === 'women' || $v === 'unisex') {
        return $v;
    }
    return 'unisex';
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
        /* Color-wise uploads allow more files; per-color max is validated separately. */
        if (count($paths) >= 60) {
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

function seller_product_images_has_color_label(PDO $pdo): bool
{
    static $checked = false;
    static $has = false;
    if ($checked) {
        return $has;
    }
    $checked = true;
    try {
        $st = $pdo->query("SHOW COLUMNS FROM product_images LIKE 'color_label'");
        $has = (bool) $st->fetch(PDO::FETCH_ASSOC);
        if (!$has) {
            $pdo->exec("ALTER TABLE product_images ADD COLUMN color_label VARCHAR(64) NULL AFTER image_path");
            $has = true;
        }
    } catch (Throwable) {
        $has = false;
    }
    return $has;
}

/**
 * @return array<int,string> zero-based upload-index => color label
 */
function seller_parse_image_color_map_from_post(string $rawJson, array $allowedColors): array
{
    $rawJson = trim($rawJson);
    if ($rawJson === '') {
        return [];
    }
    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) {
        return [];
    }
    $allowedMap = [];
    foreach ($allowedColors as $color) {
        $c = trim((string) $color);
        if ($c === '') {
            continue;
        }
        $allowedMap[mb_strtolower($c)] = $c;
    }
    $out = [];
    foreach ($decoded as $k => $v) {
        $idx = (int) $k;
        if ($idx < 0 || $idx > 99) {
            continue;
        }
        $colorRaw = trim((string) $v);
        if ($colorRaw === '') {
            continue;
        }
        if (mb_strtolower($colorRaw) === 'all') {
            continue;
        }
        $key = mb_strtolower($colorRaw);
        if (!isset($allowedMap[$key])) {
            continue;
        }
        $out[$idx] = $allowedMap[$key];
    }
    return $out;
}

/**
 * @param list<string> $uploadedPaths
 * @param array<int,string> $imageColorMap
 * @return string empty => valid
 */
function seller_validate_colorwise_image_limits(array $uploadedPaths, array $imageColorMap): string
{
    if ($uploadedPaths === []) {
        return '';
    }
    $bucketCounts = [];
    foreach ($uploadedPaths as $i => $_path) {
        $mapped = trim((string) ($imageColorMap[(int) $i] ?? ''));
        $bucket = $mapped !== '' ? mb_strtolower($mapped) : '__all__';
        $bucketCounts[$bucket] = (int) ($bucketCounts[$bucket] ?? 0) + 1;
    }
    foreach ($bucketCounts as $bucket => $count) {
        if ($count <= 6) {
            continue;
        }
        if ($bucket === '__all__') {
            return 'All colors bucket me max 6 images allowed hain. Extra images ko specific color assign karein.';
        }
        return 'Color-wise max 6 images allowed hain. "' . ucfirst($bucket) . '" ke liye 6 se zyada images hain.';
    }
    return '';
}

function seller_products_ensure_hybrid_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $checkStyle = $pdo->query("SHOW COLUMNS FROM products LIKE 'style_group_code'");
        $hasStyle = (bool) $checkStyle->fetch(PDO::FETCH_ASSOC);
        if (!$hasStyle) {
            $pdo->exec("ALTER TABLE products ADD COLUMN style_group_code VARCHAR(120) NULL AFTER product_type");
        }
        $checkPrimary = $pdo->query("SHOW COLUMNS FROM products LIKE 'primary_color'");
        $hasPrimary = (bool) $checkPrimary->fetch(PDO::FETCH_ASSOC);
        if (!$hasPrimary) {
            $pdo->exec("ALTER TABLE products ADD COLUMN primary_color VARCHAR(64) NULL AFTER color_options");
        }
    } catch (Throwable) {
        // Keep old flow functional if schema migration fails.
    }
}

function seller_make_style_group_code(string $name, string $category, string $productType): string
{
    $seed = strtolower(trim($category)) . '-' . strtolower(trim($productType)) . '-' . strtolower(trim($name));
    $slug = seller_make_slug($seed);
    return substr($slug === '' ? 'style-group' : $slug, 0, 110);
}

seller_products_ensure_hybrid_columns($pdo);

$error = '';
$toastMessage = '';
$toastIsError = false;
$drawerMode = 'add';
$editingProduct = null;
$productByIdSt = $pdo->prepare(
    'SELECT id, name, sku, category, product_type, style_group_code, price, original_price, emoji, badge, brand, size_options, color_options, primary_color, stock_qty, description, image_path,
            gender,
            offer_flash_text, offer_countdown_seconds, offer_bank_text, shipping_class,
            manufacturer_generic_name, manufacturer_country, manufacturer_name_address, packer_name_address,
            active, approval_status
     FROM products
     WHERE id = ? AND seller_id = ?
     LIMIT 1'
);
$editIdFromQuery = (int) ($_GET['edit'] ?? 0);
if ($editIdFromQuery > 0) {
    header('Location: add-product.php?id=' . $editIdFromQuery);
    exit;
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
} elseif ($msg === 'activated') {
    $toastMessage = 'Product active ho gaya. Listing buyers ko normal dikhegi.';
} elseif ($msg === 'deactivated') {
    $toastMessage = 'Product inactive ho gaya. Buyers ko Out of stock dikhega.';
} elseif ($msg === 'status_fail') {
    $toastMessage = 'Status update nahi ho paya. Sirf approved listing ko active/inactive kar sakte hain.';
    $toastIsError = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'add_product');

    if ($action === 'toggle_product_active') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $nextActive = (int) ($_POST['next_active'] ?? 0) === 1 ? 1 : 0;
        if ($productId > 0) {
            $rowSt = $pdo->prepare(
                'SELECT approval_status
                 FROM products
                 WHERE id = ? AND seller_id = ?
                 LIMIT 1'
            );
            $rowSt->execute([$productId, (int) $seller['id']]);
            $row = $rowSt->fetch(PDO::FETCH_ASSOC);
            if ($row && strtolower(trim((string) ($row['approval_status'] ?? ''))) === 'approved') {
                $upd = $pdo->prepare(
                    'UPDATE products
                     SET active = ?
                     WHERE id = ? AND seller_id = ?
                     LIMIT 1'
                );
                $upd->execute([$nextActive, $productId, (int) $seller['id']]);
                $q = ['msg' => $nextActive === 1 ? 'activated' : 'deactivated'];
                $lp = (int) ($_POST['list_page'] ?? 0);
                $lper = (int) ($_POST['list_per_page'] ?? 0);
                $ldf = strtolower(trim((string) ($_POST['list_date_filter'] ?? 'all')));
                if ($lp > 0) {
                    $q['page'] = $lp;
                }
                if ($lper >= 5 && $lper <= 100) {
                    $q['per_page'] = $lper;
                }
                if (in_array($ldf, ['day', 'week', 'month'], true)) {
                    $q['date_filter'] = $ldf;
                }
                header('Location: products.php?' . http_build_query($q));
                exit;
            }
        }
        $q = ['msg' => 'status_fail'];
        $lp = (int) ($_POST['list_page'] ?? 0);
        $lper = (int) ($_POST['list_per_page'] ?? 0);
        $ldf = strtolower(trim((string) ($_POST['list_date_filter'] ?? 'all')));
        if ($lp > 0) {
            $q['page'] = $lp;
        }
        if ($lper >= 5 && $lper <= 100) {
            $q['per_page'] = $lper;
        }
        if (in_array($ldf, ['day', 'week', 'month'], true)) {
            $q['date_filter'] = $ldf;
        }
        header('Location: products.php?' . http_build_query($q));
        exit;
    }

    if ($action === 'delete_product') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            $del = $pdo->prepare('DELETE FROM products WHERE id = ? AND seller_id = ? LIMIT 1');
            $del->execute([$productId, (int) $seller['id']]);
            if ($del->rowCount() > 0) {
                $q = ['msg' => 'deleted'];
                $lp = (int) ($_POST['list_page'] ?? 0);
                $lper = (int) ($_POST['list_per_page'] ?? 0);
                $ldf = strtolower(trim((string) ($_POST['list_date_filter'] ?? 'all')));
                if ($lp > 0) {
                    $q['page'] = $lp;
                }
                if ($lper >= 5 && $lper <= 100) {
                    $q['per_page'] = $lper;
                }
                if (in_array($ldf, ['day', 'week', 'month'], true)) {
                    $q['date_filter'] = $ldf;
                }
                header('Location: products.php?' . http_build_query($q));
                exit;
            }
        }
        $q = ['msg' => 'delete_fail'];
        $lp = (int) ($_POST['list_page'] ?? 0);
        $lper = (int) ($_POST['list_per_page'] ?? 0);
        $ldf = strtolower(trim((string) ($_POST['list_date_filter'] ?? 'all')));
        if ($lp > 0) {
            $q['page'] = $lp;
        }
        if ($lper >= 5 && $lper <= 100) {
            $q['per_page'] = $lper;
        }
        if (in_array($ldf, ['day', 'week', 'month'], true)) {
            $q['date_filter'] = $ldf;
        }
        header('Location: products.php?' . http_build_query($q));
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
        $imageColorMap = seller_parse_image_color_map_from_post((string) ($_POST['image_color_map'] ?? ''), $selectedColors);
        $description = trim((string) ($_POST['description'] ?? ''));
        $offerFlashText = trim((string) ($_POST['offer_flash_text'] ?? ''));
        $offerCountdownInput = trim((string) ($_POST['offer_countdown'] ?? ''));
        $offerBankText = trim((string) ($_POST['offer_bank_text'] ?? ''));
        $offerCountdownSeconds = seller_parse_offer_countdown_to_seconds($offerCountdownInput);
        $shippingClassPrev = ($drawerMode === 'edit' && $editingProduct)
            ? (string) ($editingProduct['shipping_class'] ?? 'standard')
            : 'standard';
        $shippingClass = seller_normalize_shipping_class((string) ($_POST['shipping_class'] ?? $shippingClassPrev));
        $manufacturerGenericName = mb_substr(trim((string) ($_POST['manufacturer_generic_name'] ?? '')), 0, 255);
        $manufacturerCountry = mb_substr(trim((string) ($_POST['manufacturer_country'] ?? '')), 0, 128);
        $manufacturerNameAddress = mb_substr(trim((string) ($_POST['manufacturer_name_address'] ?? '')), 0, 2000);
        $packerNameAddress = mb_substr(trim((string) ($_POST['packer_name_address'] ?? '')), 0, 2000);
        $productTypePrev = ($drawerMode === 'edit' && $editingProduct)
            ? (string) ($editingProduct['product_type'] ?? '')
            : '';
        $productType = seller_normalize_product_type($category, (string) ($_POST['product_type'] ?? $productTypePrev));
        $styleGroupInput = trim((string) ($_POST['style_group_code'] ?? ''));
        $styleGroupCode = $styleGroupInput !== '' ? seller_make_slug($styleGroupInput) : seller_make_style_group_code($name, $category, $productType);
        $primaryColorInput = trim((string) ($_POST['primary_color'] ?? ''));
        $primaryColor = '';
        if ($primaryColorInput !== '') {
            foreach ($selectedColors as $col) {
                if (strcasecmp($col, $primaryColorInput) === 0) {
                    $primaryColor = $col;
                    break;
                }
            }
        }
        if ($primaryColor === '' && $selectedColors !== []) {
            $primaryColor = (string) $selectedColors[0];
        }
        $genderPrev = ($drawerMode === 'edit' && $editingProduct)
            ? (string) ($editingProduct['gender'] ?? 'unisex')
            : 'unisex';
        $gender = seller_normalize_gender((string) ($_POST['gender'] ?? $genderPrev));

        if ($error === '' && $name === '') {
            $error = 'Product name required hai.';
        } elseif ($error === '' && seller_word_count($name) < 2) {
            $error = 'Product title me kam se kam 2 shabd hon.';
        } elseif ($error === '' && seller_word_count(trim(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) < 2) {
            $error = 'Description me kam se kam 2 shabd hon.';
        } elseif ($error === '' && ($price <= 0 || $originalPrice <= 0)) {
            $error = 'Price aur original price valid honi chahiye.';
        } elseif ($error === '' && $stockQty < 0) {
            $error = 'Stock quantity negative nahi ho sakti.';
        } elseif ($error === '' && !in_array($category, $allowedCategories, true)) {
            $error = 'Aap is category me product add nahi kar sakte.';
        } elseif ($error === '' && $action !== 'edit_product' && $skuInput !== '' && !preg_match('/^[A-Z0-9_-]{4,40}$/', $skuInput)) {
            $error = 'SKU format invalid hai. 4-40 chars, sirf A-Z, 0-9, - aur _ allowed hai.';
        } elseif ($error === '' && $action !== 'edit_product' && $skuInput !== '' && seller_sku_exists($pdo, $skuInput, 0)) {
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
            } else {
                $uploadedPathsCheck = is_array($upload['paths'] ?? null) ? $upload['paths'] : [];
                $imgLimitErr = seller_validate_colorwise_image_limits($uploadedPathsCheck, $imageColorMap);
                if ($imgLimitErr !== '') {
                    $error = $imgLimitErr;
                }
            }
        }

        if ($error === '' && $action === 'add_product') {
            $baseSlug = seller_make_slug($name);
            $slug = seller_unique_slug($pdo, $baseSlug);
            $sku = $skuInput !== '' ? $skuInput : seller_generate_unique_sku($pdo, $name, $category);
            $ins = $pdo->prepare(
                'INSERT INTO products
                    (seller_id, name, slug, sku, category, product_type, style_group_code, gender, price, original_price, emoji, badge, rating, review_count, brand, image_bg, image_path, size_options, color_options, primary_color, stock_qty, description,
                     offer_flash_text, offer_countdown_seconds, offer_bank_text, shipping_class,
                     manufacturer_generic_name, manufacturer_country, manufacturer_name_address, packer_name_address,
                     active, approval_status)
                 VALUES (:seller_id, :name, :slug, :sku, :category, :product_type, :style_group_code, :gender, :price, :original_price, :emoji, :badge, 4.5, 0, :brand, :image_bg, :image_path, :size_options, :color_options, :primary_color, :stock_qty, :description, :offer_flash_text, :offer_countdown_seconds, :offer_bank_text, :shipping_class, :manufacturer_generic_name, :manufacturer_country, :manufacturer_name_address, :packer_name_address, 1, \'pending\')'
            );
            $uploadedPaths = is_array($upload['paths'] ?? null) ? $upload['paths'] : [];
            $mainImagePath = $uploadedPaths[0] ?? null;
            $ins->execute([
                ':seller_id' => (int) $seller['id'],
                ':name' => $name,
                ':slug' => $slug,
                ':sku' => $sku,
                ':category' => $category,
                ':product_type' => $productType,
                ':style_group_code' => $styleGroupCode,
                ':gender' => $gender,
                ':price' => $price,
                ':original_price' => $originalPrice,
                ':emoji' => $emoji === '' ? '📦' : $emoji,
                ':badge' => $badge,
                ':brand' => $brand === '' ? 'LUXE' : $brand,
                ':image_bg' => '#1a0a2e',
                ':image_path' => $mainImagePath,
                ':size_options' => $sizeOptions,
                ':color_options' => $colorOptions,
                ':primary_color' => $primaryColor !== '' ? $primaryColor : null,
                ':stock_qty' => max(0, $stockQty),
                ':description' => $description,
                ':offer_flash_text' => $offerFlashText,
                ':offer_countdown_seconds' => max(0, $offerCountdownSeconds),
                ':offer_bank_text' => $offerBankText,
                ':shipping_class' => $shippingClass,
                ':manufacturer_generic_name' => $manufacturerGenericName,
                ':manufacturer_country' => $manufacturerCountry,
                ':manufacturer_name_address' => $manufacturerNameAddress,
                ':packer_name_address' => $packerNameAddress,
            ]);
            $productId = (int) $pdo->lastInsertId();
            if ($uploadedPaths !== [] && $productId > 0) {
                $supportsColorImageMap = seller_product_images_has_color_label($pdo);
                $imgIns = $supportsColorImageMap
                    ? $pdo->prepare('INSERT INTO product_images (product_id, image_path, color_label, sort_order) VALUES (?, ?, ?, ?)')
                    : $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)');
                foreach ($uploadedPaths as $i => $path) {
                    $mappedColor = trim((string) ($imageColorMap[(int) $i] ?? ''));
                    if ($supportsColorImageMap) {
                        $imgIns->execute([$productId, $path, $mappedColor !== '' ? $mappedColor : null, $i]);
                    } else {
                        $imgIns->execute([$productId, $path, $i]);
                    }
                }
            }
            if ($productId > 0) {
                seller_seed_variant_inventory($pdo, $productId, max(0, $stockQty), $sizeOptions, $colorOptions);
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
                $sku = $existingSku !== '' ? $existingSku : seller_generate_unique_sku($pdo, $name, $category, $productId);
                $uploadedPaths = is_array($upload['paths'] ?? null) ? $upload['paths'] : [];
                $removeImageIdsRaw = is_array($_POST['remove_image_ids'] ?? null) ? $_POST['remove_image_ids'] : [];
                $removeImageIds = array_values(array_unique(array_filter(array_map(static fn($v): int => (int) $v, $removeImageIdsRaw), static fn(int $v): bool => $v > 0)));
                $mainImagePath = $uploadedPaths[0] ?? null;

                if ($mainImagePath !== null) {
                    $upd = $pdo->prepare(
                        'UPDATE products
                         SET name = ?, slug = ?, sku = ?, category = ?, product_type = ?, style_group_code = ?, gender = ?, price = ?, original_price = ?, emoji = ?, badge = ?, brand = ?, image_path = ?, size_options = ?, color_options = ?, primary_color = ?, stock_qty = ?, description = ?
                            , offer_flash_text = ?, offer_countdown_seconds = ?, offer_bank_text = ?, shipping_class = ?
                            , manufacturer_generic_name = ?, manufacturer_country = ?, manufacturer_name_address = ?, packer_name_address = ?
                            , approval_status = CASE WHEN approval_status = \'approved\' THEN \'approved\' ELSE \'pending\' END
                         WHERE id = ? AND seller_id = ?
                         LIMIT 1'
                    );
                    $upd->execute([
                        $name,
                        $slug,
                        $sku,
                        $category,
                        $productType,
                        $styleGroupCode,
                        $gender,
                        $price,
                        $originalPrice,
                        $emoji === '' ? '📦' : $emoji,
                        $badge,
                        $brand === '' ? 'LUXE' : $brand,
                        $mainImagePath,
                        $sizeOptions,
                        $colorOptions,
                        $primaryColor !== '' ? $primaryColor : null,
                        max(0, $stockQty),
                        $description,
                        $offerFlashText,
                        max(0, $offerCountdownSeconds),
                        $offerBankText,
                        $shippingClass,
                        $manufacturerGenericName,
                        $manufacturerCountry,
                        $manufacturerNameAddress,
                        $packerNameAddress,
                        $productId,
                        (int) $seller['id'],
                    ]);
                } else {
                    $upd = $pdo->prepare(
                        'UPDATE products
                         SET name = ?, slug = ?, sku = ?, category = ?, product_type = ?, style_group_code = ?, gender = ?, price = ?, original_price = ?, emoji = ?, badge = ?, brand = ?, size_options = ?, color_options = ?, primary_color = ?, stock_qty = ?, description = ?
                            , offer_flash_text = ?, offer_countdown_seconds = ?, offer_bank_text = ?, shipping_class = ?
                            , manufacturer_generic_name = ?, manufacturer_country = ?, manufacturer_name_address = ?, packer_name_address = ?
                            , approval_status = CASE WHEN approval_status = \'approved\' THEN \'approved\' ELSE \'pending\' END
                         WHERE id = ? AND seller_id = ?
                         LIMIT 1'
                    );
                    $upd->execute([
                        $name,
                        $slug,
                        $sku,
                        $category,
                        $productType,
                        $styleGroupCode,
                        $gender,
                        $price,
                        $originalPrice,
                        $emoji === '' ? '📦' : $emoji,
                        $badge,
                        $brand === '' ? 'LUXE' : $brand,
                        $sizeOptions,
                        $colorOptions,
                        $primaryColor !== '' ? $primaryColor : null,
                        max(0, $stockQty),
                        $description,
                        $offerFlashText,
                        max(0, $offerCountdownSeconds),
                        $offerBankText,
                        $shippingClass,
                        $manufacturerGenericName,
                        $manufacturerCountry,
                        $manufacturerNameAddress,
                        $packerNameAddress,
                        $productId,
                        (int) $seller['id'],
                    ]);
                }

                if ($uploadedPaths !== []) {
                    $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
                    $supportsColorImageMap = seller_product_images_has_color_label($pdo);
                    $imgIns = $supportsColorImageMap
                        ? $pdo->prepare('INSERT INTO product_images (product_id, image_path, color_label, sort_order) VALUES (?, ?, ?, ?)')
                        : $pdo->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?, ?, ?)');
                    foreach ($uploadedPaths as $i => $path) {
                        $mappedColor = trim((string) ($imageColorMap[(int) $i] ?? ''));
                        if ($supportsColorImageMap) {
                            $imgIns->execute([$productId, $path, $mappedColor !== '' ? $mappedColor : null, $i]);
                        } else {
                            $imgIns->execute([$productId, $path, $i]);
                        }
                    }
                } elseif ($removeImageIds !== []) {
                    $imgRowsSt = $pdo->prepare('SELECT id, image_path FROM product_images WHERE product_id = ?');
                    $imgRowsSt->execute([$productId]);
                    $imgRows = $imgRowsSt->fetchAll(PDO::FETCH_ASSOC);
                    $existingImgIds = [];
                    foreach ($imgRows as $imgRow) {
                        $iid = (int) ($imgRow['id'] ?? 0);
                        if ($iid <= 0) {
                            continue;
                        }
                        $existingImgIds[$iid] = true;
                    }
                    $safeDeleteIds = array_values(array_filter($removeImageIds, static fn(int $iid): bool => isset($existingImgIds[$iid])));
                    if ($safeDeleteIds !== []) {
                        $placeholders = implode(',', array_fill(0, count($safeDeleteIds), '?'));
                        $delParams = array_merge([$productId], $safeDeleteIds);
                        $delSt = $pdo->prepare('DELETE FROM product_images WHERE product_id = ? AND id IN (' . $placeholders . ')');
                        $delSt->execute($delParams);
                        // Always re-sync main image from remaining gallery after explicit removals.
                        $nextMainSt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
                        $nextMainSt->execute([$productId]);
                        $nextMain = trim((string) ($nextMainSt->fetchColumn() ?: ''));
                        $pdo->prepare('UPDATE products SET image_path = ? WHERE id = ? AND seller_id = ? LIMIT 1')
                            ->execute([$nextMain !== '' ? $nextMain : null, $productId, (int) $seller['id']]);
                    }
                }
                $vCntSt = $pdo->prepare('SELECT COUNT(*) FROM product_variant_inventory WHERE product_id = ?');
                $vCntSt->execute([$productId]);
                if ((int) $vCntSt->fetchColumn() === 0) {
                    seller_seed_variant_inventory($pdo, $productId, max(0, $stockQty), $sizeOptions, $colorOptions);
                }
                header('Location: products.php?msg=updated');
                exit;
            }
        }

        if ($error !== '' && (string) ($_POST['return_to'] ?? '') === 'add_wizard') {
            $_SESSION['seller_wizard_product_error'] = $error;
            $wizPid = (int) ($_POST['product_id'] ?? 0);
            $loc = 'add-product.php';
            if ($action === 'edit_product' && $wizPid > 0) {
                $loc .= '?id=' . $wizPid;
            }
            header('Location: ' . $loc);
            exit;
        }
    }
}

require_once __DIR__ . '/../admin/_pagination.php';

$productKpiSt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(TRIM(COALESCE(p.approval_status, \'\'))) = \'approved\' THEN 1 ELSE 0 END) AS approved_cnt,
        SUM(CASE WHEN LOWER(TRIM(COALESCE(p.approval_status, \'\'))) = \'pending\' THEN 1 ELSE 0 END) AS pending_cnt,
        SUM(CASE WHEN LOWER(TRIM(COALESCE(p.approval_status, \'\'))) NOT IN (\'approved\', \'pending\') THEN 1 ELSE 0 END) AS rejected_cnt,
        SUM(CASE WHEN LOWER(TRIM(COALESCE(p.approval_status, \'\'))) = \'approved\' AND p.active = 1 THEN 1 ELSE 0 END) AS store_live,
        SUM(CASE WHEN COALESCE(v.variant_stock_sum, p.stock_qty) < 5 THEN 1 ELSE 0 END) AS low_stock
     FROM products p
     LEFT JOIN (
         SELECT product_id, SUM(stock_qty) AS variant_stock_sum
         FROM product_variant_inventory
         GROUP BY product_id
     ) v ON v.product_id = p.id
     WHERE p.seller_id = ?'
);
$productKpiSt->execute([(int) $seller['id']]);
$productKpi = $productKpiSt->fetch() ?: [];
$productCount = (int) ($productKpi['total'] ?? 0);
$approvedCount = (int) ($productKpi['approved_cnt'] ?? 0);
$pendingCount = (int) ($productKpi['pending_cnt'] ?? 0);
$rejectedCount = (int) ($productKpi['rejected_cnt'] ?? 0);
$storeLiveCount = (int) ($productKpi['store_live'] ?? 0);
$lowStockCount = (int) ($productKpi['low_stock'] ?? 0);

$productsDateFilter = strtolower(trim((string) ($_GET['date_filter'] ?? 'all')));
$productsDateFilterMap = [
    'all' => ['label' => 'All time', 'sql' => ''],
    'day' => ['label' => 'Last 24 hours', 'sql' => ' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)'],
    'week' => ['label' => 'Last 7 days', 'sql' => ' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'],
    'month' => ['label' => 'Last 30 days', 'sql' => ' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'],
];
if (!isset($productsDateFilterMap[$productsDateFilter])) {
    $productsDateFilter = 'all';
}
$productsDateWhereSql = (string) ($productsDateFilterMap[$productsDateFilter]['sql'] ?? '');

$productsCountSt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM products p
     WHERE p.seller_id = ?' . $productsDateWhereSql
);
$productsCountSt->execute([(int) $seller['id']]);
$productsFilteredCount = (int) $productsCountSt->fetchColumn();

['page' => $productsListPage, 'perPage' => $productsPerPage] = admin_pagination_read(25);
$productsPageMeta = admin_pagination_resolve($productsFilteredCount, $productsListPage, $productsPerPage);
$productsPage = $productsPageMeta['page'];
$productsOffset = $productsPageMeta['offset'];
$productsPerPage = $productsPageMeta['perPage'];
$productsTotalPages = $productsPageMeta['totalPages'];

$productsSt = $pdo->prepare(
    'SELECT p.id, p.name, p.slug, p.sku, p.category, p.gender, p.price, p.original_price, p.emoji, p.badge, p.brand, p.image_path, p.size_options, p.color_options, p.stock_qty, p.description, p.active,
            p.offer_flash_text, p.offer_countdown_seconds, p.offer_bank_text, p.approval_status,
            COALESCE(v.variant_stock_sum, p.stock_qty) AS display_stock_qty
     FROM products p
     LEFT JOIN (
         SELECT product_id, SUM(stock_qty) AS variant_stock_sum
         FROM product_variant_inventory
         GROUP BY product_id
     ) v ON v.product_id = p.id
     WHERE p.seller_id = ?' . $productsDateWhereSql . '
     ORDER BY p.id DESC
     LIMIT ' . (int) $productsPerPage . ' OFFSET ' . (int) $productsOffset
);
$productsSt->execute([(int) $seller['id']]);
$products = $productsSt->fetchAll();
$productsFormQuery = [
    'page' => $productsPage,
    'per_page' => $productsPerPage,
];
if ($productsDateFilter !== 'all') {
    $productsFormQuery['date_filter'] = $productsDateFilter;
}
$productsFormAction = 'products.php?' . http_build_query($productsFormQuery);
$openProductDrawer = $error !== '' || $drawerMode === 'edit';

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-products-page-head">
          <div>
            <h1>Products</h1>
            <p class="seller-products-subtitle">Manage your catalogue — search filters <strong>this page</strong> only; use pagination to browse the full catalogue. Preview on the storefront, edit details, or add new listings. Discounts apply only after admin approves new items.</p>
          </div>
          <div class="admin-page-head__actions seller-products-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="inventory.php">Inventory</a>
            <a class="admin-btn admin-btn--primary" href="add-product.php">Add product</a>
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
                <p class="card-subtitle seller-products-card-sub">
                  <?php if ($productsDateFilter === 'all'): ?>
                    <?= $productCount === 0 ? 'Add your first product to appear here after approval.' : (int) $productCount . ' product' . ($productCount === 1 ? '' : 's') . ' · ' . (int) $storeLiveCount . ' visible to buyers.' ?>
                  <?php else: ?>
                    Showing <strong><?= (int) $productsFilteredCount ?></strong> product<?= $productsFilteredCount === 1 ? '' : 's' ?> for <strong><?= h((string) ($productsDateFilterMap[$productsDateFilter]['label'] ?? 'Selected range')) ?></strong>.
                  <?php endif; ?>
                </p>
              </div>
              <div class="seller-inventory-toolbar seller-products-toolbar">
                <form method="get" class="seller-products-date-filter-form">
                  <input type="hidden" name="page" value="1">
                  <input type="hidden" name="per_page" value="<?= (int) $productsPerPage ?>">
                  <label class="seller-products-date-filter-label" for="sellerProductsDateFilter">Date</label>
                  <select id="sellerProductsDateFilter" name="date_filter" class="seller-products-date-filter-select" onchange="this.form.submit()">
                    <option value="all"<?= $productsDateFilter === 'all' ? ' selected' : '' ?>>All time</option>
                    <option value="day"<?= $productsDateFilter === 'day' ? ' selected' : '' ?>>Last 24 hours</option>
                    <option value="week"<?= $productsDateFilter === 'week' ? ' selected' : '' ?>>Last 7 days</option>
                    <option value="month"<?= $productsDateFilter === 'month' ? ' selected' : '' ?>>Last 30 days</option>
                  </select>
                </form>
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
                    <th>Status</th>
                    <th class="seller-products-th--actions">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($products as $p): ?>
                    <?php
                    $pid = (int) ($p['id'] ?? 0);
                    $isActiveListing = (int) ($p['active'] ?? 0) === 1;
                    $displayStockQty = $isActiveListing ? (int) ($p['display_stock_qty'] ?? $p['stock_qty'] ?? 0) : 0;
                    $apRaw = strtolower(trim((string) ($p['approval_status'] ?? 'approved')));
                    $productsSearchBlob = mb_strtolower(
                        (string) $pid . ' '
                        . trim((string) ($p['name'] ?? '')) . ' '
                        . trim((string) ($p['slug'] ?? '')) . ' '
                        . trim((string) ($p['sku'] ?? '')) . ' '
                        . trim((string) ($p['category'] ?? '')) . ' '
                        . trim((string) ($p['gender'] ?? '')) . ' '
                        . trim((string) ($p['brand'] ?? '')) . ' '
                        . trim((string) ($p['badge'] ?? '')) . ' '
                        . (string) (int) ($p['price'] ?? 0) . ' '
                        . (string) (int) ($p['original_price'] ?? 0) . ' '
                        . (string) $displayStockQty . ' '
                        . ($isActiveListing ? 'active' : 'inactive') . ' '
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
                      <td>
                        <div class="seller-product-listing-stack">
                          <?php if ($apRaw === 'approved'): ?>
                            <form method="post" action="<?= h($productsFormAction) ?>" class="seller-product-status-form">
                              <input type="hidden" name="action" value="toggle_product_active">
                              <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                              <input type="hidden" name="next_active" value="<?= $isActiveListing ? '0' : '1' ?>">
                              <input type="hidden" name="list_page" value="<?= (int) $productsPage ?>">
                              <input type="hidden" name="list_per_page" value="<?= (int) $productsPerPage ?>">
                              <input type="hidden" name="list_date_filter" value="<?= h($productsDateFilter) ?>">
                              <label class="seller-toggle-switch" title="<?= $isActiveListing ? 'Deactivate listing' : 'Activate listing' ?>">
                                <input
                                  type="checkbox"
                                  <?= $isActiveListing ? 'checked' : '' ?>
                                  aria-label="<?= $isActiveListing ? 'Deactivate listing' : 'Activate listing' ?>"
                                  onchange="this.form.querySelector('input[name=next_active]').value=this.checked?'1':'0';this.form.submit();"
                                >
                                <span class="seller-toggle-switch__track" aria-hidden="true"></span>
                              </label>
                            </form>
                          <?php else: ?>
                            <span class="seller-stock-low-hint">Approve hone ke baad</span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="seller-products-td--actions">
                        <div class="seller-product-actions">
                          <a href="product-view.php?id=<?= (int) $p['id'] ?>" class="seller-view-btn seller-product-actions__btn">
                          <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M9 4.46A9.8 9.8 0 0 1 12 4c4.182 0 7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296A14.5 14.5 0 0 1 5 6.821"></path><path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path></g></svg>
                          </a>
                          <a href="add-product.php?id=<?= (int) $p['id'] ?>" class="seller-edit-btn seller-product-actions__btn">
                          <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M4 22h4m12 0h-8m1.888-18.337l.742-.742a3.146 3.146 0 1 1 4.449 4.45l-.742.74m-4.449-4.448s.093 1.576 1.483 2.966s2.966 1.483 2.966 1.483m-4.449-4.45L7.071 10.48c-.462.462-.693.692-.891.947a5.2 5.2 0 0 0-.599.969c-.139.291-.242.601-.449 1.22l-.875 2.626m14.08-8.13L14.93 11.52m-3.41 3.41c-.462.462-.692.692-.947.891q-.451.352-.969.599c-.291.139-.601.242-1.22.448l-2.626.876m0 0l-.641.213a.848.848 0 0 1-1.073-1.073l.213-.641m1.501 1.5l-1.5-1.5"></path></svg>
                          </a>
                          <form method="post" class="seller-product-actions__form" action="<?= h($productsFormAction) ?>" onsubmit="return confirm('Kya aap is product ko delete karna chahte hain?');">
                            <input type="hidden" name="action" value="delete_product">
                            <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="list_page" value="<?= (int) $productsPage ?>">
                            <input type="hidden" name="list_per_page" value="<?= (int) $productsPerPage ?>">
                            <input type="hidden" name="list_date_filter" value="<?= h($productsDateFilter) ?>">
                            <button type="submit" class="seller-delete-btn seller-product-actions__btn seller-product-actions__btn--danger">
                            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.5" d="M20.5 6h-17m5.67-2a3.001 3.001 0 0 1 5.66 0m3.544 11.4c-.177 2.654-.266 3.981-1.131 4.79s-2.195.81-4.856.81h-.774c-2.66 0-3.99 0-4.856-.81c-.865-.809-.953-2.136-1.13-4.79l-.46-6.9m13.666 0l-.2 3"></path></svg>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($products === []): ?>
                    <tr class="seller-products-empty-placeholder">
                      <td colspan="9">
                        <div class="seller-products-empty">
                          <div class="seller-products-empty__icon" aria-hidden="true">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
                          </div>
                          <h3 class="seller-products-empty__title">No products yet</h3>
                          <p class="seller-products-empty__text"><?= $canAddProducts ? 'Create your first listing — it will go for admin approval before appearing on LUXE.' : 'Complete KYC and get admin approval to start adding products.' ?></p>
                          <?php if ($canAddProducts): ?>
                            <a class="admin-btn admin-btn--primary" href="add-product.php">Add your first product</a>
                          <?php else: ?>
                            <a class="admin-btn admin-btn--primary" href="kyc-details.php">Complete KYC</a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <tr id="sellerProductsNoMatchRow" class="seller-products-no-match-row" style="display:none">
                      <td colspan="9" class="seller-products-no-match-cell">
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
            <?php
            $paginationScript = 'products.php';
            $paginationTotal = $productsFilteredCount;
            $paginationPage = $productsPage;
            $paginationPerPage = $productsPerPage;
            $paginationTotalPages = $productsTotalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
          </div>
        </div>

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
                      <label for="sku">SKU<?= $drawerMode === 'edit' ? '' : ' <span class="seller-product-form-optional">(manual ya auto)</span>' ?></label>
                      <input id="sku" name="sku" maxlength="40" placeholder="e.g. FAS-SHIRT-001" value="<?= h((string) ($_POST['sku'] ?? ($editingProduct['sku'] ?? ''))) ?>" <?= $drawerMode === 'edit' ? 'readonly class="seller-product-input--readonly"' : '' ?> <?= $canAddProducts ? '' : 'disabled' ?>>
                    </div>
                    <?php if ($drawerMode !== 'edit'): ?>
                    <div class="seller-product-form-sku-action">
                      <button class="seller-view-btn seller-sku-generate-btn" id="generateSkuBtn" type="button" <?= $canAddProducts ? '' : 'disabled' ?>>Auto-generate SKU</button>
                    </div>
                    <?php endif; ?>
                  </div>
                  <p class="seller-help seller-product-form-hint seller-product-form-hint--sku"><?= $drawerMode === 'edit' ? 'SKU unique hai — product create ke baad change nahi hota.' : 'Khali chhodoge to save par system SKU generate karega.' ?></p>
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
                    <div class="seller-product-form-field">
                      <label for="gender">Gender</label>
                      <?php $genderFormVal = seller_normalize_gender((string) ($_POST['gender'] ?? ($editingProduct['gender'] ?? 'unisex'))); ?>
                      <select id="gender" name="gender" <?= $canAddProducts ? '' : 'disabled' ?>>
                        <option value="men"<?= $genderFormVal === 'men' ? ' selected' : '' ?>>Men</option>
                        <option value="women"<?= $genderFormVal === 'women' ? ' selected' : '' ?>>Women</option>
                        <option value="unisex"<?= $genderFormVal === 'unisex' ? ' selected' : '' ?>>Unisex</option>
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

              <section class="seller-product-form-section" aria-labelledby="product-section-manufacturer">
                <header class="seller-product-form-section__head">
                  <h3 class="seller-product-form-section__title" id="product-section-manufacturer">Manufacturer info</h3>
                  <p class="seller-product-form-section__sub">Generic name, country, manufacturer &amp; packer — optional lekin compliance ke liye useful.</p>
                </header>
                <div class="seller-product-form-section__body">
                  <div class="seller-product-form-field">
                    <label for="manufacturer_generic_name">Generic name</label>
                    <input id="manufacturer_generic_name" name="manufacturer_generic_name" type="text" maxlength="255" placeholder="e.g. Paracetamol 500 mg" value="<?= h((string) ($_POST['manufacturer_generic_name'] ?? ($editingProduct['manufacturer_generic_name'] ?? ''))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                  </div>
                  <div class="seller-product-form-field">
                    <label for="manufacturer_country">Country</label>
                    <input id="manufacturer_country" name="manufacturer_country" type="text" maxlength="128" placeholder="e.g. India" value="<?= h((string) ($_POST['manufacturer_country'] ?? ($editingProduct['manufacturer_country'] ?? ''))) ?>" <?= $canAddProducts ? '' : 'disabled' ?>>
                  </div>
                  <div class="seller-product-form-field">
                    <label for="manufacturer_name_address">Name and address of the manufacturer</label>
                    <textarea id="manufacturer_name_address" name="manufacturer_name_address" class="seller-wizard-textarea" maxlength="2000" rows="3" placeholder="Full legal name, address…" <?= $canAddProducts ? '' : 'disabled' ?>><?= h((string) ($_POST['manufacturer_name_address'] ?? ($editingProduct['manufacturer_name_address'] ?? ''))) ?></textarea>
                  </div>
                  <div class="seller-product-form-field">
                    <label for="packer_name_address">Name and address of the packer</label>
                    <textarea id="packer_name_address" name="packer_name_address" class="seller-wizard-textarea" maxlength="2000" rows="3" placeholder="If different from manufacturer…" <?= $canAddProducts ? '' : 'disabled' ?>><?= h((string) ($_POST['packer_name_address'] ?? ($editingProduct['packer_name_address'] ?? ''))) ?></textarea>
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
                    <input type="hidden" id="image_color_map" name="image_color_map" value="">
                    <p class="seller-help seller-product-form-hint">
                      Gallery order upload order jaisi rahegi.
                      <?php if ($drawerMode === 'edit'): ?>
                        <strong>Edit mode:</strong> nayi upload purani gallery replace karti hai.
                      <?php endif; ?>
                      <span id="productImagesPickCount" class="seller-product-form-file-count" hidden></span>
                    </p>
                    <div id="imageColorMapWrap" class="seller-help seller-product-form-hint" hidden></div>
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
            if (!closeBtn || !drawer || !backdrop) return;
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
              if (openBtn) openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
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

            if (openBtn) {
              openBtn.addEventListener('click', function () {
                lastFocusedElement = document.activeElement;
                setDrawerState(true);
              });
            }
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
              lastFocusedElement = openBtn || closeBtn;
              document.body.classList.add('seller-drawer-open');
              setDrawerState(true);
            }
          })();
        </script>

        <script>
          (function () {
            var imagesInput = document.getElementById('images');
            var countEl = document.getElementById('productImagesPickCount');
            var colorSelect = document.getElementById('color_options');
            var mappingWrap = document.getElementById('imageColorMapWrap');
            var mappingInput = document.getElementById('image_color_map');
            if (!imagesInput || !countEl || !colorSelect || !mappingWrap || !mappingInput) return;

            function getSelectedColors() {
              var opts = Array.prototype.slice.call(colorSelect.options || []);
              return opts.filter(function (o) { return o.selected; }).map(function (o) { return String(o.value || '').trim(); }).filter(Boolean);
            }

            function renderMappingUi() {
              var n = imagesInput.files ? imagesInput.files.length : 0;
              if (n === 0) {
                countEl.textContent = '';
                countEl.setAttribute('hidden', '');
                mappingWrap.setAttribute('hidden', '');
                mappingWrap.innerHTML = '';
                mappingInput.value = '';
                return;
              }

              countEl.removeAttribute('hidden');
              countEl.textContent = ' \u2014 ' + n + ' file' + (n === 1 ? '' : 's') + ' selected';

              var colors = getSelectedColors();
              if (colors.length === 0) {
                mappingWrap.removeAttribute('hidden');
                mappingWrap.innerHTML = '<strong>Tip:</strong> Agar color-wise images chahiye to pehle color options select karein.';
                mappingInput.value = '';
                return;
              }

              var html = '<strong>Color-wise image mapping</strong><br><small>Har image ko color assign karo. "All colors" ka matlab common gallery image.</small><div style="display:grid;gap:8px;margin-top:8px">';
              for (var i = 0; i < n; i++) {
                var fileName = imagesInput.files[i] ? imagesInput.files[i].name : ('Image ' + (i + 1));
                html += '<label style="display:flex;justify-content:space-between;align-items:center;gap:10px;"><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:260px;">' + fileName.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>';
                html += '<select data-image-map-index="' + i + '"><option value="">All colors</option>';
                colors.forEach(function (color) {
                  var safe = color.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                  html += '<option value="' + safe + '">' + safe + '</option>';
                });
                html += '</select></label>';
              }
              html += '</div>';
              mappingWrap.removeAttribute('hidden');
              mappingWrap.innerHTML = html;
              syncMapValue();
            }

            function syncMapValue() {
              var map = {};
              var selects = mappingWrap.querySelectorAll('select[data-image-map-index]');
              selects.forEach(function (sel) {
                var idx = Number(sel.getAttribute('data-image-map-index') || '-1');
                if (idx < 0) return;
                var color = String(sel.value || '').trim();
                if (color !== '') {
                  map[idx] = color;
                }
              });
              mappingInput.value = JSON.stringify(map);
            }

            imagesInput.addEventListener('change', renderMappingUi);
            colorSelect.addEventListener('change', renderMappingUi);
            mappingWrap.addEventListener('change', function (event) {
              var target = event.target;
              if (target && target.matches && target.matches('select[data-image-map-index]')) {
                syncMapValue();
              }
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
