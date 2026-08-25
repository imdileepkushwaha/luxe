<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

/**
 * @return list<string>
 */
function api_parse_csv(?string $csv): array
{
    $csv = str_replace(["\r\n", "\r", "\n", "\t", ';', '|', '،'], ',', (string) $csv);
    $parts = array_map('trim', explode(',', $csv));

    return array_values(array_unique(array_filter($parts, static fn(string $v): bool => $v !== '')));
}

function api_color_hex(string $colorName): string
{
    $lower = mb_strtolower(trim($colorName));
    if ($lower === '' || $lower === 'default') {
        return '#8b5cf6';
    }
    if (preg_match('/\b(white|off[\s-]?white|ivory|pearl|cream|snow|frost)\b/u', $lower)) {
        return '#f1f5f9';
    }
    if (preg_match('/\b(black|jet|charcoal|midnight)\b/u', $lower)) {
        return '#0f172a';
    }
    if (preg_match('/\b(red|crimson|maroon|burgundy)\b/u', $lower)) {
        return '#ef4444';
    }
    if (preg_match('/\b(blue|navy|indigo|azure)\b/u', $lower)) {
        return '#1e40af';
    }
    if (preg_match('/\b(teal|cyan|aqua)\b/u', $lower)) {
        return '#0f766e';
    }
    if (preg_match('/\b(green|olive|forest|emerald|mint)\b/u', $lower)) {
        return '#059669';
    }
    if (preg_match('/\b(yellow|gold|mustard|amber)\b/u', $lower)) {
        return '#ca8a04';
    }
    if (preg_match('/\b(orange|coral|peach)\b/u', $lower)) {
        return '#f97316';
    }
    if (preg_match('/\b(pink|rose|magenta)\b/u', $lower)) {
        return '#ec4899';
    }
    if (preg_match('/\b(purple|violet|lavender)\b/u', $lower)) {
        return '#8b5cf6';
    }
    if (preg_match('/\b(gray|grey|silver)\b/u', $lower)) {
        return '#64748b';
    }
    if (preg_match('/\b(brown|tan|beige|khaki|camel)\b/u', $lower)) {
        return '#92400e';
    }

    return '#64748b';
}

/**
 * Sibling colors + reviews for a single product (matches theme-3 PDP).
 *
 * @param array<string,mixed> $product
 */
function api_enrich_product_detail(PDO $pdo, array &$product): void
{
    $id = (int) ($product['id'] ?? 0);
    if ($id <= 0) {
        return;
    }

    $colors = is_array($product['colors'] ?? null) ? $product['colors'] : [];
    $primary = trim((string) ($product['primary_color'] ?? ''));
    if ($primary !== '' && !in_array($primary, $colors, true)) {
        array_unshift($colors, $primary);
    }

    try {
        $vSt = $pdo->prepare(
            'SELECT DISTINCT color_label FROM product_variant_inventory
             WHERE product_id = ? AND active = 1 AND TRIM(color_label) <> \'\''
        );
        $vSt->execute([$id]);
        foreach ($vSt->fetchAll(PDO::FETCH_COLUMN) as $label) {
            $label = trim((string) $label);
            if ($label !== '' && !in_array($label, $colors, true)) {
                $colors[] = $label;
            }
        }
    } catch (Throwable) {
        // table may be missing on older installs
    }

    /** @var array<string,int> $colorProductIds */
    $colorProductIds = [];
    foreach ($colors as $name) {
        $colorProductIds[mb_strtolower(trim((string) $name))] = $id;
    }

    $sellerId = (int) ($product['seller_id'] ?? 0);
    $styleGroup = trim((string) ($product['style_group_code'] ?? ''));
    if ($sellerId > 0) {
        try {
            if ($styleGroup !== '') {
                $sibSt = $pdo->prepare(
                    "SELECT id, primary_color, color_options
                     FROM products
                     WHERE seller_id = ?
                       AND style_group_code = ?
                       AND (id = ? OR (active = 1 AND approval_status = 'approved'))
                     ORDER BY id ASC"
                );
                $sibSt->execute([$sellerId, $styleGroup, $id]);
            } else {
                $sibSt = $pdo->prepare(
                    "SELECT id, primary_color, color_options
                     FROM products
                     WHERE seller_id = ?
                       AND LOWER(TRIM(category)) = ?
                       AND LOWER(TRIM(COALESCE(product_type, ''))) = ?
                       AND LOWER(TRIM(name)) = ?
                       AND (id = ? OR (active = 1 AND approval_status = 'approved'))
                     ORDER BY id ASC"
                );
                $sibSt->execute([
                    $sellerId,
                    strtolower(trim((string) ($product['category'] ?? ''))),
                    strtolower(trim((string) ($product['product_type'] ?? ''))),
                    strtolower(trim((string) ($product['name'] ?? ''))),
                    $id,
                ]);
            }
            foreach ($sibSt->fetchAll(PDO::FETCH_ASSOC) as $sib) {
                $sid = (int) ($sib['id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $sibPrimary = trim((string) ($sib['primary_color'] ?? ''));
                if ($sibPrimary === '') {
                    $csv = api_parse_csv($sib['color_options'] ?? '');
                    $sibPrimary = $csv[0] ?? '';
                }
                if ($sibPrimary === '') {
                    continue;
                }
                $lk = mb_strtolower($sibPrimary);
                if (!isset($colorProductIds[$lk])) {
                    $colorProductIds[$lk] = $sid;
                }
                if (!in_array($sibPrimary, $colors, true)) {
                    $colors[] = $sibPrimary;
                }
            }
        } catch (Throwable) {
            // ignore sibling lookup failures
        }
    }

    $product['colors'] = array_values($colors);
    $swatches = [];
    foreach ($product['colors'] as $name) {
        $lk = mb_strtolower(trim((string) $name));
        $swatches[] = [
            'name' => (string) $name,
            'hex' => api_color_hex((string) $name),
            'product_id' => $colorProductIds[$lk] ?? $id,
        ];
    }
    $product['color_swatches'] = $swatches;

    $reviews = [];
    try {
        $revSt = $pdo->prepare(
            "SELECT customer_name, rating, review_text, seller_response, created_at
             FROM product_reviews
             WHERE product_id = ?
               AND LOWER(review_status) IN ('approved', 'published')
             ORDER BY created_at DESC, id DESC
             LIMIT 50"
        );
        $revSt->execute([$id]);
        $rows = $revSt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            $revSt = $pdo->prepare(
                "SELECT customer_name, rating, review_text, seller_response, created_at
                 FROM product_reviews
                 WHERE product_id = ?
                   AND LOWER(COALESCE(review_status, '')) NOT IN ('rejected', 'hidden', 'spam')
                 ORDER BY created_at DESC, id DESC
                 LIMIT 50"
            );
            $revSt->execute([$id]);
            $rows = $revSt->fetchAll(PDO::FETCH_ASSOC);
        }
        $sum = 0;
        foreach ($rows as $row) {
            $rating = max(1, min(5, (int) ($row['rating'] ?? 5)));
            $sum += $rating;
            $reviews[] = [
                'customer_name' => trim((string) ($row['customer_name'] ?? '')) ?: 'Customer',
                'rating' => $rating,
                'review_text' => (string) ($row['review_text'] ?? ''),
                'seller_response' => trim((string) ($row['seller_response'] ?? '')),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        if ($reviews !== []) {
            $product['rating'] = round($sum / count($reviews), 1);
            $product['review_count'] = count($reviews);
        }
    } catch (Throwable) {
        $reviews = [];
    }
    $product['reviews'] = $reviews;

    unset($product['seller_id'], $product['style_group_code'], $product['product_type'], $product['primary_color']);
}

try {
    $pdo = db();

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $category = trim((string) ($_GET['category'] ?? ''));
    $search = trim((string) ($_GET['search'] ?? ''));
    $offersOnly = isset($_GET['offers']) && !in_array((string) $_GET['offers'], ['0', 'false', ''], true);
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 24;
    if ($limit < 1) {
        $limit = 24;
    }
    if ($limit > 80) {
        $limit = 80;
    }

    $sql = "SELECT p.id, p.name, p.slug, p.category, p.price, p.original_price, p.brand, p.badge,
                   p.rating, p.review_count, p.image_path, p.description, p.size_options, p.color_options,
                   p.stock_qty, p.active, p.offer_flash_text, p.offer_bank_text, p.offer_countdown_seconds,
                   p.seller_id, p.style_group_code, p.product_type, p.primary_color
            FROM products p
            LEFT JOIN seller_users s ON s.id = p.seller_id
            WHERE p.approval_status = 'approved'
              AND p.active = 1
              AND p.seller_id IS NOT NULL
              AND s.is_active = 1
              AND NOT EXISTS (
                    SELECT 1
                    FROM seller_account_deletion_requests dr
                    WHERE dr.status = 'approved'
                      AND (dr.seller_id = s.id OR dr.email = s.email)
              )";
    $params = [];

    if ($id > 0) {
        $sql .= ' AND p.id = ?';
        $params[] = $id;
    }

    if ($category !== '' && strcasecmp($category, 'All') !== 0) {
        $sql .= ' AND LOWER(p.category) = LOWER(?)';
        $params[] = $category;
    }

    if ($search !== '') {
        $sql .= ' AND (p.name LIKE ? OR p.category LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($offersOnly) {
        $sql .= ' AND p.original_price > p.price AND p.original_price > 0';
        $sql .= ' ORDER BY ((p.original_price - p.price) / p.original_price) DESC, p.created_at DESC';
    } else {
        $sql .= ' ORDER BY p.created_at DESC';
    }
    if ($id <= 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as &$product) {
        $product['id'] = (int) $product['id'];
        $product['price'] = (int) $product['price'];
        $product['original_price'] = (int) $product['original_price'];
        $product['rating'] = (float) ($product['rating'] ?? 0);
        $product['review_count'] = (int) ($product['review_count'] ?? 0);
        $product['stock_qty'] = (int) ($product['stock_qty'] ?? 0);
        $product['brand'] = trim((string) ($product['brand'] ?? '')) ?: 'LUXE';
        $product['badge'] = trim((string) ($product['badge'] ?? ''));
        $product['category'] = trim((string) ($product['category'] ?? ''));
        $product['sizes'] = api_parse_csv($product['size_options'] ?? '');
        $product['colors'] = api_parse_csv($product['color_options'] ?? '');
        $product['offer_flash_text'] = trim((string) ($product['offer_flash_text'] ?? ''));
        $product['offer_bank_text'] = trim((string) ($product['offer_bank_text'] ?? ''));
        $product['offer_countdown_seconds'] = (int) ($product['offer_countdown_seconds'] ?? 0);
        $orig = (int) $product['original_price'];
        $curr = (int) $product['price'];
        $product['discount_percent'] = ($orig > $curr && $orig > 0)
            ? (int) round((($orig - $curr) / $orig) * 100)
            : 0;
        unset($product['size_options'], $product['color_options'], $product['active']);

        $imgStmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC');
        $imgStmt->execute([$product['id']]);
        $extraImages = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

        $allImages = [];
        $main = trim((string) ($product['image_path'] ?? ''));
        if ($main !== '') {
            $allImages[] = $main;
        }
        foreach ($extraImages as $extra) {
            $extra = trim((string) $extra);
            if ($extra !== '') {
                $allImages[] = $extra;
            }
        }
        $allImages = array_values(array_unique($allImages));

        $product['images'] = [];
        foreach ($allImages as $path) {
            $abs = luxe_absolute_media_url($path);
            if ($abs !== '') {
                $product['images'][] = $abs;
            }
        }

        $product['image_url'] = $product['images'][0] ?? '';
        unset($product['image_path']);
        if ($id <= 0) {
            unset($product['seller_id'], $product['style_group_code'], $product['product_type'], $product['primary_color']);
        }
    }
    unset($product);

    if ($id > 0 && isset($products[0])) {
        api_enrich_product_detail($pdo, $products[0]);
    }

    echo json_encode([
        'ok' => true,
        'products' => $products,
        'product' => $id > 0 ? ($products[0] ?? null) : null,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not load products',
        'details' => $e->getMessage(),
    ]);
}
