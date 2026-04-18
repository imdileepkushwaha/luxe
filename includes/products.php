<?php

declare(strict_types=1);

/**
 * @return list<array{id:int,name:string,category:string,price:int,original:int,emoji:string,badge:string,rating:float,reviews:int,brand:string,slug:string,image_bg:string,image_path:string}>
 */
function products_fetch_all(PDO $pdo): array
{
    $st = $pdo->query(
        'SELECT p.id, p.name, p.slug, p.category, p.price, p.original_price AS original, p.emoji, p.badge, p.rating, p.review_count AS reviews, p.brand, p.image_bg, p.image_path,
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
            "SELECT p.id, p.seller_id, p.name, p.slug, p.sku, p.category, p.price, p.original_price AS original, p.emoji, p.badge,
                    p.rating, p.review_count AS reviews, p.brand, p.image_bg, p.image_path, p.stock_qty, p.description,
                    p.offer_flash_text, p.offer_countdown_seconds, p.offer_bank_text, p.approval_status,
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
            "SELECT p.id, p.seller_id, p.name, p.slug, p.sku, p.category, p.price, p.original_price AS original, p.emoji, p.badge,
                    p.rating, p.review_count AS reviews, p.brand, p.image_bg, p.image_path, p.stock_qty, p.description,
                    p.offer_flash_text, p.offer_countdown_seconds, p.offer_bank_text, p.approval_status,
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

/** @return list<array<string,mixed>> */
function products_fetch_related(PDO $pdo, int $excludeId, string $category, int $limit = 4): array
{
    $st = $pdo->prepare(
        'SELECT p.id, p.name, p.emoji, p.price, p.original_price AS original, p.badge
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
    }
    return $rows;
}
