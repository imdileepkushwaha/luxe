<?php

declare(strict_types=1);

/**
 * Platform + seller coupon definitions for cart/checkout/order.
 */

/**
 * @return array<string, array{type:string,val:int,max:?int,desc:string,seller_id:?int,min_order:int}>
 */
function coupons_builtin_defs(): array
{
    return [
        'LUXE10' => [
            'type' => 'percent',
            'val' => 10,
            'max' => 500,
            'desc' => '10% off (max ₹500)',
            'seller_id' => null,
            'min_order' => 0,
        ],
        'FIRST50' => [
            'type' => 'percent',
            'val' => 50,
            'max' => 2000,
            'desc' => '50% off first order!',
            'seller_id' => null,
            'min_order' => 0,
        ],
        'SALE20' => [
            'type' => 'flat',
            'val' => 200,
            'max' => null,
            'desc' => '₹200 flat off',
            'seller_id' => null,
            'min_order' => 0,
        ],
    ];
}

function coupons_normalize_code(string $code): string
{
    return strtoupper(trim($code));
}

function seller_coupon_dates_ok(?string $from, ?string $until): bool
{
    $today = date('Y-m-d');
    if ($from !== null && $from !== '' && $from > $today) {
        return false;
    }
    if ($until !== null && $until !== '' && $until < $today) {
        return false;
    }

    return true;
}

/**
 * @param array<string,mixed> $row
 * @return array{type:string,val:int,max:?int,desc:string,seller_id:int,min_order:int}
 */
function seller_coupon_row_to_def(array $row): array
{
    $type = (string) ($row['discount_type'] ?? '') === 'percent' ? 'percent' : 'flat';
    $val = max(0, (int) ($row['discount_value'] ?? 0));
    $maxRaw = $row['max_discount_rupees'] ?? null;
    $max = $maxRaw !== null && $maxRaw !== '' ? max(0, (int) $maxRaw) : null;
    $desc = trim((string) ($row['description'] ?? ''));
    if ($desc === '') {
        if ($type === 'percent') {
            $desc = $max !== null ? "{$val}% off (max ₹{$max})" : "{$val}% off";
        } else {
            $desc = '₹' . $val . ' off';
        }
    }

    return [
        'type' => $type,
        'val' => $val,
        'max' => $max,
        'desc' => $desc,
        'seller_id' => (int) ($row['seller_id'] ?? 0),
        'min_order' => max(0, (int) ($row['min_order_rupees'] ?? 0)),
    ];
}

/**
 * Active seller coupons (all sellers), for cart chips / JSON defs.
 *
 * @return list<array<string,mixed>>
 */
function seller_coupons_list_active(PDO $pdo): array
{
    $st = $pdo->query(
        "SELECT id, seller_id, code, discount_type, discount_value, max_discount_rupees, min_order_rupees, description, is_active, valid_from, valid_until
         FROM seller_coupons
         WHERE is_active = 1
         ORDER BY id DESC"
    );
    if (!$st) {
        return [];
    }
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!seller_coupon_dates_ok(
            isset($row['valid_from']) ? (string) $row['valid_from'] : null,
            isset($row['valid_until']) ? (string) $row['valid_until'] : null
        )) {
            continue;
        }
        $out[] = $row;
    }

    return $out;
}

/**
 * Merged defs keyed by uppercase code (DB overrides same key as builtin if ever collided).
 *
 * @return array<string, array{type:string,val:int,max:?int,desc:string,seller_id:?int,min_order:int}>
 */
function coupons_defs_for_frontend(PDO $pdo): array
{
    $merged = coupons_builtin_defs();
    foreach (seller_coupons_list_active($pdo) as $row) {
        $code = coupons_normalize_code((string) ($row['code'] ?? ''));
        if ($code === '') {
            continue;
        }
        $def = seller_coupon_row_to_def($row);
        $merged[$code] = [
            'type' => $def['type'],
            'val' => $def['val'],
            'max' => $def['max'],
            'desc' => $def['desc'],
            'seller_id' => $def['seller_id'],
            'min_order' => $def['min_order'],
        ];
    }

    return $merged;
}

/**
 * Codes to show as quick-apply chips (platform first, then recent seller codes).
 *
 * @return list<string>
 */
function coupons_featured_tag_codes(PDO $pdo, int $maxTags = 10): array
{
    $tags = array_keys(coupons_builtin_defs());
    foreach (seller_coupons_list_active($pdo) as $row) {
        $c = coupons_normalize_code((string) ($row['code'] ?? ''));
        if ($c !== '' && !in_array($c, $tags, true)) {
            $tags[] = $c;
        }
        if (count($tags) >= $maxTags) {
            break;
        }
    }

    return $tags;
}

/**
 * @param array<string,mixed>|null $def
 */
function coupons_def_applicable_subtotal(array $lines, ?array $def): int
{
    if ($def === null) {
        return 0;
    }
    $sellerScope = isset($def['seller_id']) && $def['seller_id'] !== null ? (int) $def['seller_id'] : null;
    $sum = 0;
    foreach ($lines as $li) {
        if (!is_array($li)) {
            continue;
        }
        $unit = max(0, (int) ($li['price'] ?? 0));
        $qty = max(1, (int) ($li['qty'] ?? 1));
        $sid = (int) ($li['seller_id'] ?? 0);
        if ($sellerScope !== null && $sellerScope > 0) {
            if ($sid === $sellerScope) {
                $sum += $unit * $qty;
            }
        } else {
            $sum += $unit * $qty;
        }
    }

    return $sum;
}

/**
 * @return array{type:string,val:int,max:?int,desc:string,seller_id:?int,min_order:int}|null
 */
function coupons_resolve_def(PDO $pdo, string $code): ?array
{
    $code = coupons_normalize_code($code);
    if ($code === '') {
        return null;
    }
    $built = coupons_builtin_defs();
    if (isset($built[$code])) {
        return $built[$code];
    }

    $st = $pdo->prepare(
        'SELECT seller_id, code, discount_type, discount_value, max_discount_rupees, min_order_rupees, description, is_active, valid_from, valid_until
         FROM seller_coupons
         WHERE code = ?
         LIMIT 1'
    );
    $st->execute([$code]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) ($row['is_active'] ?? 0) !== 1) {
        return null;
    }
    if (!seller_coupon_dates_ok(
        isset($row['valid_from']) ? (string) $row['valid_from'] : null,
        isset($row['valid_until']) ? (string) $row['valid_until'] : null
    )) {
        return null;
    }

    $def = seller_coupon_row_to_def($row);

    return [
        'type' => $def['type'],
        'val' => $def['val'],
        'max' => $def['max'],
        'desc' => $def['desc'],
        'seller_id' => $def['seller_id'],
        'min_order' => $def['min_order'],
    ];
}

/**
 * @param list<array{id?:int,price?:int,qty?:int,seller_id?:int}> $lines
 */
function coupons_order_discount_rupees(PDO $pdo, string $code, array $lines): int
{
    $def = coupons_resolve_def($pdo, $code);
    if ($def === null) {
        return 0;
    }
    $base = coupons_def_applicable_subtotal($lines, $def);
    $min = (int) ($def['min_order'] ?? 0);
    if ($base < $min || $base <= 0) {
        return 0;
    }
    if ($def['type'] === 'percent') {
        $cap = isset($def['max']) && $def['max'] !== null ? (int) $def['max'] : PHP_INT_MAX;
        $d = (int) round($base * (int) $def['val'] / 100);

        return min($d, $cap, $base);
    }
    $d = (int) $def['val'];

    return min(max(0, $d), $base);
}

/**
 * @return list<array<string,mixed>>
 */
function seller_coupons_for_seller(PDO $pdo, int $sellerId): array
{
    $st = $pdo->prepare(
        'SELECT id, seller_id, code, discount_type, discount_value, max_discount_rupees, min_order_rupees, description, is_active, valid_from, valid_until, created_at
         FROM seller_coupons
         WHERE seller_id = ?
         ORDER BY id DESC'
    );
    $st->execute([$sellerId]);

    return $st->fetchAll(PDO::FETCH_ASSOC);
}
