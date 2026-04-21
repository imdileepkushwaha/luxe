<?php

declare(strict_types=1);

/**
 * Parse products.size_options / color_options CSV (comma-separated, same as seller inventory UI).
 *
 * @return list<string>
 */
function seller_parse_product_option_csv(string $csv): array
{
    $parts = array_map('trim', explode(',', $csv));
    $parts = array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));

    return array_values(array_unique($parts));
}

/** Alias for seller/inventory.php (same CSV parsing). */
function seller_parse_option_csv(string $csv): array
{
    return seller_parse_product_option_csv($csv);
}

/**
 * @param list<string> $sizes
 * @param list<string> $colors
 * @return list<array{size:string,color:string,key:string,label:string}>
 */
function seller_variant_combinations(array $sizes, array $colors): array
{
    if ($sizes === [] && $colors === []) {
        return [];
    }
    $sizeList = $sizes === [] ? [''] : $sizes;
    $colorList = $colors === [] ? [''] : $colors;
    $rows = [];
    foreach ($sizeList as $size) {
        foreach ($colorList as $color) {
            $sz = trim((string) $size);
            $cl = trim((string) $color);
            $key = strtolower($sz) . '|' . strtolower($cl);
            $label = '';
            if ($sz !== '' && $cl !== '') {
                $label = $sz . ' / ' . $cl;
            } elseif ($sz !== '') {
                $label = $sz;
            } elseif ($cl !== '') {
                $label = $cl;
            } else {
                $label = 'Default';
            }
            $rows[] = [
                'size' => $sz,
                'color' => $cl,
                'key' => $key,
                'label' => $label,
            ];
        }
    }

    return $rows;
}

/**
 * Create product_variant_inventory rows for a new/unsynced product: splits total stock evenly across
 * all size/color combinations (remainder goes to the first rows). Sum of rows equals $totalStock.
 */
function seller_seed_variant_inventory(PDO $pdo, int $productId, int $totalStock, string $sizeCsv, string $colorCsv): void
{
    if ($productId <= 0) {
        return;
    }
    $sizes = seller_parse_product_option_csv($sizeCsv);
    $colors = seller_parse_product_option_csv($colorCsv);
    $matrix = seller_variant_combinations($sizes, $colors);
    if ($matrix === []) {
        return;
    }
    $n = count($matrix);
    $total = max(0, $totalStock);
    $base = intdiv($total, $n);
    $rem = $total % $n;
    $upsert = $pdo->prepare(
        'INSERT INTO product_variant_inventory (product_id, size_label, color_label, stock_qty, active)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE stock_qty = VALUES(stock_qty), active = VALUES(active)'
    );
    foreach ($matrix as $i => $row) {
        $q = $base + ($i < $rem ? 1 : 0);
        $upsert->execute([$productId, $row['size'], $row['color'], $q]);
    }
}
