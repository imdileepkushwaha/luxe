<?php

declare(strict_types=1);

/**
 * Shared product type + size presets for seller add-product wizard and products.php validation.
 */

/** Full allowlist for size_options CSV (must cover all preset sizes below). */
function seller_base_size_catalog(): array
{
    return [
        '28', '30', '32', '34', '36', '38', '40',
        'XS', 'S', 'M', 'L', 'XL', 'XXL',
        'UK 6', 'UK 7', 'UK 8', 'UK 9', 'UK 10', 'UK 11', 'UK 12',
        'Free Size',
    ];
}

/**
 * @return array<string, list<array{slug:string,label:string}>>
 */
function seller_product_types_by_category(): array
{
    return [
        'fashion' => [
            ['slug' => 'jeans', 'label' => 'Jeans'],
            ['slug' => 'shirt', 'label' => 'Shirt'],
            ['slug' => 'tshirt', 'label' => 'T-shirt'],
            ['slug' => 'shoes', 'label' => 'Shoes'],
            ['slug' => 'slipper', 'label' => 'Slipper'],
            ['slug' => 'dress', 'label' => 'Dress'],
            ['slug' => 'ethnicwear', 'label' => 'Ethnic wear'],
            ['slug' => 'bags', 'label' => 'Bags'],
            ['slug' => 'accessories', 'label' => 'Accessories'],
            ['slug' => 'watches', 'label' => 'Watches'],
            ['slug' => 'general', 'label' => 'Other / General'],
        ],
        'electronics' => [
            ['slug' => 'smartphone', 'label' => 'Smartphone'],
            ['slug' => 'laptop', 'label' => 'Laptop'],
            ['slug' => 'tablet', 'label' => 'Tablet'],
            ['slug' => 'audio', 'label' => 'Audio'],
            ['slug' => 'accessories', 'label' => 'Accessories'],
            ['slug' => 'wearables', 'label' => 'Wearables'],
            ['slug' => 'general', 'label' => 'Other / General'],
        ],
        'beauty' => [
            ['slug' => 'skincare', 'label' => 'Skincare'],
            ['slug' => 'makeup', 'label' => 'Makeup'],
            ['slug' => 'fragrance', 'label' => 'Fragrance'],
            ['slug' => 'haircare', 'label' => 'Haircare'],
            ['slug' => 'general', 'label' => 'Other / General'],
        ],
        'home' => [
            ['slug' => 'furniture', 'label' => 'Furniture'],
            ['slug' => 'decor', 'label' => 'Decor'],
            ['slug' => 'kitchen', 'label' => 'Kitchen'],
            ['slug' => 'bedding', 'label' => 'Bedding'],
            ['slug' => 'general', 'label' => 'Other / General'],
        ],
    ];
}

/** @return list<array{slug:string,label:string}> */
function seller_product_types_for_category(string $category): array
{
    $cat = strtolower(trim($category));
    $map = seller_product_types_by_category();

    return $map[$cat] ?? [['slug' => 'general', 'label' => 'General']];
}

/**
 * Size presets per category + product type (Fashion: jeans = waist numbers, shirts = S/M/L, shoes = UK…).
 *
 * @return array<string, list<string>>
 */
function seller_sizes_map_by_category_product_type(): array
{
    $letter = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    $uk = ['UK 6', 'UK 7', 'UK 8', 'UK 9', 'UK 10', 'UK 11', 'UK 12'];
    $waist = ['28', '30', '32', '34', '36', '38', '40'];
    $electronics = ['Free Size', 'XS', 'S', 'M', 'L', 'XL', 'XXL'];
    $beautyHome = ['Free Size', 'S', 'M', 'L', 'XL'];

    return [
        'fashion:jeans' => $waist,
        'fashion:shirt' => $letter,
        'fashion:tshirt' => $letter,
        'fashion:dress' => $letter,
        'fashion:ethnicwear' => $letter,
        'fashion:shoes' => $uk,
        'fashion:slipper' => $uk,
        'fashion:bags' => ['Free Size'],
        'fashion:accessories' => ['Free Size', 'XS', 'S', 'M', 'L', 'XL', 'XXL'],
        'fashion:watches' => ['Free Size'],
        'fashion:general' => seller_base_size_catalog(),
        'electronics:smartphone' => ['Free Size', 'XS', 'S', 'M', 'L', 'XL'],
        'electronics:laptop' => ['Free Size', 'XS', 'S', 'M', 'L', 'XL', 'XXL'],
        'electronics:tablet' => ['Free Size', 'XS', 'S', 'M', 'L', 'XL'],
        'electronics:audio' => ['Free Size', 'XS', 'S', 'M', 'L'],
        'electronics:accessories' => $electronics,
        'electronics:wearables' => ['Free Size', 'XS', 'S', 'M', 'L', 'XL'],
        'electronics:general' => $electronics,
        'beauty:skincare' => $beautyHome,
        'beauty:makeup' => $beautyHome,
        'beauty:fragrance' => ['Free Size', 'S', 'M', 'L'],
        'beauty:haircare' => $beautyHome,
        'beauty:general' => $beautyHome,
        'home:furniture' => ['Free Size', 'S', 'M', 'L', 'XL', 'XXL'],
        'home:decor' => ['Free Size', 'S', 'M', 'L', 'XL'],
        'home:kitchen' => ['Free Size', 'S', 'M', 'L', 'XL', 'XXL'],
        'home:bedding' => ['Free Size', 'S', 'M', 'L', 'XL', 'XXL'],
        'home:general' => ['Free Size', 'S', 'M', 'L', 'XL', 'XXL'],
    ];
}

/** @return list<string> */
function seller_get_sizes_for_category_product_type(string $category, string $productType): array
{
    $cat = strtolower(trim($category));
    $pt = strtolower(trim($productType));
    if ($pt === '') {
        $pt = 'general';
    }
    $key = $cat . ':' . $pt;
    $map = seller_sizes_map_by_category_product_type();
    $base = seller_base_size_catalog();
    if (!isset($map[$key])) {
        $key = $cat . ':general';
    }
    $sizes = $map[$key] ?? $base;

    return array_values(array_intersect($base, $sizes));
}

/** @return 'general'|string */
function seller_normalize_product_type(string $category, string $raw): string
{
    $slug = strtolower(trim($raw));
    if ($slug === '') {
        return 'general';
    }
    $allowed = array_column(seller_product_types_for_category($category), 'slug');

    return in_array($slug, $allowed, true) ? $slug : 'general';
}

/**
 * Map saved CSV options to canonical labels from an allowlist (seller wizard + drawer).
 *
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
