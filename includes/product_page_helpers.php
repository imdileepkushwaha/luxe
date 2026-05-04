<?php

declare(strict_types=1);

/**
 * Shared helpers for storefront product detail pages (root + theme skins).
 * Loaded via require_once to avoid "Cannot redeclare" when multiple product.php variants run.
 */

if (!function_exists('product_parse_options_csv')) {
    function product_parse_options_csv(string $csv): array
    {
        $csv = str_replace(["\r\n", "\r", "\n", "\t", ';', '|', '،'], ',', $csv);
        $parts = array_map('trim', explode(',', $csv));
        $parts = array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));

        return array_values(array_unique($parts));
    }
}

if (!function_exists('product_swatch_style')) {
    function product_swatch_style(int $idx): string
    {
        $palette = [
            'linear-gradient(135deg,#8b5cf6,#ec4899)',
            'linear-gradient(135deg,#1e40af,#0ea5e9)',
            'linear-gradient(135deg,#064e3b,#10b981)',
            'linear-gradient(135deg,#1c1c1c,#475569)',
            'linear-gradient(135deg,#7f1d1d,#ef4444)',
            'linear-gradient(135deg,#7c2d12,#f97316)',
        ];

        return $palette[$idx % count($palette)];
    }
}

/** Swatch + main image backdrop gradient from color name (falls back to index palette). */
if (!function_exists('product_swatch_style_for_color')) {
    function product_swatch_style_for_color(string $colorName, int $idx): string
    {
        $lower = mb_strtolower(trim($colorName));
        if ($lower === '' || $lower === 'default') {
            return 'linear-gradient(135deg,#8b5cf6,#ec4899)';
        }
        if (preg_match('/\b(white|off[\s-]?white|ivory|pearl|cream|snow|frost)\b/u', $lower)) {
            return 'linear-gradient(140deg,#ffffff 0%,#f1f5f9 50%,#e2e8f0 100%)';
        }
        if (preg_match('/\b(black|jet|charcoal|midnight)\b/u', $lower)) {
            return 'linear-gradient(135deg,#0f172a,#1e293b)';
        }
        if (preg_match('/\b(red|crimson|maroon|burgundy)\b/u', $lower)) {
            return 'linear-gradient(135deg,#991b1b,#ef4444)';
        }
        if (preg_match('/\b(blue|navy|indigo|azure)\b/u', $lower)) {
            return 'linear-gradient(135deg,#1e40af,#0ea5e9)';
        }
        if (preg_match('/\b(teal|cyan|aqua)\b/u', $lower)) {
            return 'linear-gradient(135deg,#0f766e,#22d3ee)';
        }
        if (preg_match('/\b(green|olive|forest|emerald|mint)\b/u', $lower)) {
            return 'linear-gradient(135deg,#064e3b,#10b981)';
        }
        if (preg_match('/\b(yellow|gold|mustard|amber)\b/u', $lower)) {
            return 'linear-gradient(135deg,#ca8a04,#fbbf24)';
        }
        if (preg_match('/\b(orange|coral|peach)\b/u', $lower)) {
            return 'linear-gradient(135deg,#c2410c,#fb923c)';
        }
        if (preg_match('/\b(pink|rose|magenta|purple|violet|lavender)\b/u', $lower)) {
            return 'linear-gradient(135deg,#8b5cf6,#ec4899)';
        }
        if (preg_match('/\b(gray|grey|silver)\b/u', $lower)) {
            return 'linear-gradient(135deg,#64748b,#94a3b8)';
        }
        if (preg_match('/\b(brown|tan|beige|khaki|camel)\b/u', $lower)) {
            return 'linear-gradient(135deg,#78350f,#d97706)';
        }

        return product_swatch_style($idx);
    }
}

/** Human-readable "N sold" / "1.2K+ sold" from fulfilled order line qty (excludes cancelled orders). */
if (!function_exists('product_format_units_sold_label')) {
    function product_format_units_sold_label(int $units): string
    {
        if ($units <= 0) {
            return '';
        }
        if ($units >= 1_000_000) {
            $m = $units / 1_000_000;

            return ($m >= 10 ? number_format($m, 0) : number_format($m, 1)) . 'M+ sold';
        }
        if ($units >= 1_000) {
            $k = $units / 1_000;

            return ($k >= 10 ? number_format($k, 0) : number_format($k, 1)) . 'K+ sold';
        }
        if ($units >= 100) {
            return number_format($units) . '+ sold';
        }

        return (string) $units . ' sold';
    }
}
