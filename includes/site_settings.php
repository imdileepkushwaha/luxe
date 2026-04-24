<?php

declare(strict_types=1);

function site_setting_get(PDO $pdo, string $key, string $default = ''): string
{
    $st = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    if ($v === false) {
        return $default;
    }

    return (string) $v;
}

function site_setting_set(PDO $pdo, string $key, string $value): void
{
    $st = $pdo->prepare(
        'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
    );
    $st->execute([$key, $value]);
}

function site_platform_fee_rupees(PDO $pdo): int
{
    return max(0, (int) site_setting_get($pdo, 'platform_fee_rupees', '3'));
}

/** Percent (0–100) of merchandise subtotal kept as admin commission on marketplace orders; not shown to shoppers. */
function site_admin_seller_commission_percent(PDO $pdo): float
{
    $raw = trim(site_setting_get($pdo, 'admin_seller_commission_percent', '1'));
    if ($raw === '') {
        return 0.0;
    }
    $v = (float) $raw;
    if ($v < 0) {
        return 0.0;
    }
    if ($v > 100) {
        return 100.0;
    }

    return $v;
}

function order_admin_commission_rupees_from_subtotal(int $merchandiseSubtotalRupees, float $percent): int
{
    if ($merchandiseSubtotalRupees <= 0 || $percent <= 0) {
        return 0;
    }

    return (int) round($merchandiseSubtotalRupees * ($percent / 100.0));
}

function site_cart_free_shipping_min_rupees(PDO $pdo): int
{
    return max(0, (int) site_setting_get($pdo, 'cart_free_shipping_min_rupees', '1000'));
}

function site_cart_below_min_shipping_fee_rupees(PDO $pdo): int
{
    return max(0, (int) site_setting_get($pdo, 'cart_below_min_shipping_fee_rupees', '50'));
}
