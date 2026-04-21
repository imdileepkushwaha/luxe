<?php

declare(strict_types=1);

require_once __DIR__ . '/seller_variant_inventory.php';

/**
 * @return list<array{id:int,name:string,category:string,price:int,original:int,emoji:string,badge:string,rating:float,reviews:int,brand:string,slug:string,image_bg:string,image_path:string,requires_variant_pick:bool}>
 */
function products_fetch_all(PDO $pdo): array
{
    $st = $pdo->query(
        'SELECT p.id, p.name, p.slug, p.category, p.price, p.original_price AS original, p.emoji, p.badge, p.rating, p.review_count AS reviews, p.brand, p.image_bg, p.image_path,
                p.size_options, p.color_options,
                (SELECT COUNT(*) FROM product_variant_inventory pvi WHERE pvi.product_id = p.id AND pvi.active = 1) AS variant_row_count,
                (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1) AS gallery_first
         FROM products p
         LEFT JOIN seller_users s ON s.id = p.seller_id
         WHERE p.active = 1
           AND p.approval_status = \'approved\'
           AND p.seller_id IS NOT NULL
           AND s.is_active = 1
           AND NOT EXISTS (
                SELECT 1
                FROM seller_account_deletion_requests dr
                WHERE dr.status = \'approved\'
                  AND (dr.seller_id = s.id OR dr.email = s.email)
           )
         ORDER BY p.id ASC'
    );
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['price'] = (int) $r['price'];
        $r['original'] = (int) $r['original'];
        $r['reviews'] = (int) $r['reviews'];
        $r['rating'] = (float) $r['rating'];
        $variantRowCount = (int) ($r['variant_row_count'] ?? 0);
        $sizes = seller_parse_product_option_csv((string) ($r['size_options'] ?? ''));
        $colors = seller_parse_product_option_csv((string) ($r['color_options'] ?? ''));
        $multiOption =
            count($sizes) > 1
            || count($colors) > 1
            || (count($sizes) >= 1 && count($colors) >= 1);
        $r['requires_variant_pick'] = $variantRowCount > 0 || $multiOption;
        unset($r['variant_row_count'], $r['size_options'], $r['color_options']);
        $main = trim((string) ($r['image_path'] ?? ''));
        $gal = trim((string) ($r['gallery_first'] ?? ''));
        unset($r['gallery_first']);
        $r['image_path'] = $main !== '' ? $main : $gal;
        $r['category'] = strtolower(trim((string) ($r['category'] ?? '')));
    }
    return $rows;
}

/**
 * @param ?int $forSellerOwnerId If set, load product for that seller even when not yet approved (own preview).
 */
function products_fetch_by_id(PDO $pdo, int $id, ?int $forSellerOwnerId = null): ?array
{
    if ($forSellerOwnerId !== null && $forSellerOwnerId > 0) {
        $st = $pdo->prepare(
            "SELECT p.id, p.seller_id, p.name, p.slug, p.sku, p.category, COALESCE(NULLIF(TRIM(p.product_type), ''), 'general') AS product_type, p.price, p.original_price AS original, p.emoji, p.badge,
                    p.rating, p.review_count AS reviews, p.brand, p.image_bg, p.image_path, p.stock_qty, p.size_options, p.color_options, p.description,
                    p.offer_flash_text, p.offer_countdown_seconds, p.offer_bank_text, p.approval_status,
                    COALESCE(NULLIF(TRIM(p.shipping_class), ''), 'standard') AS shipping_class,
                    COALESCE(NULLIF(TRIM(s.full_name), ''), 'LUXE Store') AS seller_name
             FROM products p
             LEFT JOIN seller_users s ON s.id = p.seller_id
             WHERE p.id = ?
               AND p.seller_id = ?
               AND s.is_active = 1
               AND NOT EXISTS (
                    SELECT 1
                    FROM seller_account_deletion_requests dr
                    WHERE dr.status = 'approved'
                      AND (dr.seller_id = s.id OR dr.email = s.email)
               )
             LIMIT 1"
        );
        $st->execute([$id, $forSellerOwnerId]);
    } else {
        $st = $pdo->prepare(
            "SELECT p.id, p.seller_id, p.name, p.slug, p.sku, p.category, COALESCE(NULLIF(TRIM(p.product_type), ''), 'general') AS product_type, p.price, p.original_price AS original, p.emoji, p.badge,
                    p.rating, p.review_count AS reviews, p.brand, p.image_bg, p.image_path, p.stock_qty, p.size_options, p.color_options, p.description,
                    p.offer_flash_text, p.offer_countdown_seconds, p.offer_bank_text, p.approval_status,
                    COALESCE(NULLIF(TRIM(p.shipping_class), ''), 'standard') AS shipping_class,
                    COALESCE(NULLIF(TRIM(s.full_name), ''), 'LUXE Store') AS seller_name
             FROM products p
             LEFT JOIN seller_users s ON s.id = p.seller_id
             WHERE p.active = 1
               AND p.approval_status = 'approved'
               AND p.id = ?
               AND p.seller_id IS NOT NULL
               AND s.is_active = 1
               AND NOT EXISTS (
                    SELECT 1
                    FROM seller_account_deletion_requests dr
                    WHERE dr.status = 'approved'
                      AND (dr.seller_id = s.id OR dr.email = s.email)
               )
             LIMIT 1"
        );
        $st->execute([$id]);
    }
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $r['id'] = (int) $r['id'];
    $r['seller_id'] = (int) ($r['seller_id'] ?? 0);
    $r['price'] = (int) $r['price'];
    $r['original'] = (int) $r['original'];
    $r['reviews'] = (int) $r['reviews'];
    $r['rating'] = (float) $r['rating'];
    $r['stock_qty'] = (int) ($r['stock_qty'] ?? 0);
    $imgSt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $imgSt->execute([$id]);
    $images = $imgSt->fetchAll(PDO::FETCH_COLUMN);
    $images = array_values(array_filter(array_map('strval', is_array($images) ? $images : []), static fn(string $v): bool => $v !== ''));
    if ((string) ($r['image_path'] ?? '') !== '') {
        array_unshift($images, (string) $r['image_path']);
    }
    $r['images'] = array_values(array_unique($images));
    return $r;
}

/**
 * Load a seller product by ID for admin moderation (any approval_status / active flag).
 */
function products_fetch_by_id_for_admin(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        "SELECT p.id, p.seller_id, p.name, p.slug, p.sku, p.category, COALESCE(NULLIF(TRIM(p.product_type), ''), 'general') AS product_type, p.price, p.original_price AS original, p.emoji, p.badge,
                p.rating, p.review_count AS reviews, p.brand, p.image_bg, p.image_path, p.stock_qty, p.size_options, p.color_options, p.description,
                p.offer_flash_text, p.offer_countdown_seconds, p.offer_bank_text, p.approval_status,
                COALESCE(NULLIF(TRIM(p.shipping_class), ''), 'standard') AS shipping_class,
                COALESCE(NULLIF(TRIM(s.full_name), ''), 'LUXE Store') AS seller_name
         FROM products p
         LEFT JOIN seller_users s ON s.id = p.seller_id
         WHERE p.id = ?
           AND p.seller_id IS NOT NULL
         LIMIT 1"
    );
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    $r['id'] = (int) $r['id'];
    $r['seller_id'] = (int) ($r['seller_id'] ?? 0);
    $r['price'] = (int) $r['price'];
    $r['original'] = (int) $r['original'];
    $r['reviews'] = (int) $r['reviews'];
    $r['rating'] = (float) $r['rating'];
    $r['stock_qty'] = (int) ($r['stock_qty'] ?? 0);
    $imgSt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
    $imgSt->execute([$id]);
    $images = $imgSt->fetchAll(PDO::FETCH_COLUMN);
    $images = array_values(array_filter(array_map('strval', is_array($images) ? $images : []), static fn(string $v): bool => $v !== ''));
    if ((string) ($r['image_path'] ?? '') !== '') {
        array_unshift($images, (string) $r['image_path']);
    }
    $r['images'] = array_values(array_unique($images));
    return $r;
}

/** @return list<array<string,mixed>> */
function products_fetch_related(PDO $pdo, int $excludeId, string $category, int $limit = 4): array
{
    $st = $pdo->prepare(
        'SELECT p.id, p.name, p.category, p.emoji, p.price, p.original_price AS original, p.badge, p.image_bg, p.image_path,
                (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1) AS gallery_first
         FROM products p
         LEFT JOIN seller_users s ON s.id = p.seller_id
         WHERE p.active = 1
           AND p.approval_status = \'approved\'
           AND p.id != ?
           AND p.category = ?
           AND p.seller_id IS NOT NULL
           AND s.is_active = 1
           AND NOT EXISTS (
                SELECT 1
                FROM seller_account_deletion_requests dr
                WHERE dr.status = \'approved\'
                  AND (dr.seller_id = s.id OR dr.email = s.email)
           )
         ORDER BY p.id ASC
         LIMIT ' . (int) $limit
    );
    $st->execute([$excludeId, $category]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['price'] = (int) $r['price'];
        $r['original'] = (int) $r['original'];
        $main = trim((string) ($r['image_path'] ?? ''));
        $gal = trim((string) ($r['gallery_first'] ?? ''));
        unset($r['gallery_first']);
        $r['image_path'] = $main !== '' ? $main : $gal;
        $r['category'] = strtolower(trim((string) ($r['category'] ?? '')));
    }
    unset($r);
    return $rows;
}

/**
 * Public storefront seller (active, not deletion-approved). No sensitive KYC fields (no phone, PAN, bank, docs).
 *
 * @return array<string,mixed>|null
 */
function seller_fetch_public_profile(PDO $pdo, int $sellerId): ?array
{
    if ($sellerId <= 0) {
        return null;
    }
    $st = $pdo->prepare(
        "SELECT s.id, s.email, s.full_name, s.business_name, s.logo_path, s.banner_path,
                s.city, s.state, s.pin_code, s.allowed_categories, s.created_at,
                s.business_address
         FROM seller_users s
         WHERE s.id = ?
           AND s.is_active = 1
           AND NOT EXISTS (
                SELECT 1
                FROM seller_account_deletion_requests dr
                WHERE dr.status = 'approved'
                  AND (dr.seller_id = s.id OR dr.email = s.email)
           )
         LIMIT 1"
    );
    $st->execute([$sellerId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];

    return $row;
}

/**
 * Approved active products for a seller (same visibility rules as main catalog).
 *
 * @return list<array<string,mixed>>
 */
function products_fetch_by_seller_for_store(PDO $pdo, int $sellerId): array
{
    if ($sellerId <= 0) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT p.id, p.name, p.slug, p.category, p.price, p.original_price AS original, p.emoji, p.badge, p.rating, p.review_count AS reviews, p.brand, p.image_bg, p.image_path,
                (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.sort_order ASC, pi.id ASC LIMIT 1) AS gallery_first
         FROM products p
         INNER JOIN seller_users s ON s.id = p.seller_id
         WHERE p.seller_id = ?
           AND p.active = 1
           AND p.approval_status = \'approved\'
           AND s.is_active = 1
           AND NOT EXISTS (
                SELECT 1
                FROM seller_account_deletion_requests dr
                WHERE dr.status = \'approved\'
                  AND (dr.seller_id = s.id OR dr.email = s.email)
           )
         ORDER BY p.id DESC'
    );
    $st->execute([$sellerId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['price'] = (int) $r['price'];
        $r['original'] = (int) $r['original'];
        $r['reviews'] = (int) $r['reviews'];
        $r['rating'] = (float) $r['rating'];
        $main = trim((string) ($r['image_path'] ?? ''));
        $gal = trim((string) ($r['gallery_first'] ?? ''));
        unset($r['gallery_first']);
        $r['image_path'] = $main !== '' ? $main : $gal;
        $r['category'] = strtolower(trim((string) ($r['category'] ?? '')));
    }

    return $rows;
}

/**
 * True if the map has any key "size|something" where something is non-empty (color-specific stock).
 *
 * @param array<string,int> $map
 */
function product_variant_map_has_color_specific_rows_for_size(array $map, string $sizeLower): bool
{
    $prefix = $sizeLower . '|';
    $plen = strlen($prefix);
    foreach ($map as $mk => $_) {
        $mks = (string) $mk;
        if (!str_starts_with($mks, $prefix)) {
            continue;
        }
        if (strlen($mks) > $plen) {
            return true;
        }
    }

    return false;
}

/**
 * Per-variant qty for product page (matches storefront JS getVariantStock after map expansion).
 *
 * @param array<string,int> $map
 */
function product_variant_display_qty_from_map(
    array $map,
    bool $hasColorSwatches,
    string $colorKeyLower,
    string $sizeLabel
): int {
    $sz = mb_strtolower(trim($sizeLabel));
    $k = $sz . '|' . $colorKeyLower;
    if (array_key_exists($k, $map)) {
        return max(0, (int) $map[$k]);
    }
    if ($hasColorSwatches) {
        $blank = $sz . '|';
        if (array_key_exists($blank, $map) && !product_variant_map_has_color_specific_rows_for_size($map, $sz)) {
            return max(0, (int) $map[$blank]);
        }

        return 0;
    }
    $blank = $sz . '|';
    if (array_key_exists($blank, $map)) {
        return max(0, (int) $map[$blank]);
    }
    if ($sz === '') {
        return 0;
    }
    $prefix = $sz . '|';
    $best = 0;
    foreach ($map as $mk => $mv) {
        if (str_starts_with((string) $mk, $prefix)) {
            $best = max($best, (int) $mv);
        }
    }

    return $best;
}
