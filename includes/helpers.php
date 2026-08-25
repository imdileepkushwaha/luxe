<?php

declare(strict_types=1);

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Strip inline styles from seller-provided description HTML so theme (dark/light) controls color.
 */
function luxe_sanitize_product_description_html(string $html): string
{
    $out = preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/iu', '', $html);
    $out = preg_replace('/\sstyle\s*=\s*[^>\s]+/iu', '', $out ?? $html);

    return $out ?? $html;
}

/** Mask email for UI (e.g. ra***@example.com). */
function luxe_mask_email(string $email): string
{
    $email = trim($email);
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return $email;
    }
    [$local, $domain] = $parts;
    $len = strlen($local);
    if ($len <= 1) {
        return '*@' . $domain;
    }
    $show = $len <= 3 ? 1 : 2;

    return substr($local, 0, $show) . str_repeat('*', max(1, $len - $show)) . '@' . $domain;
}

/** Build storefront product URL with slug (fallback to id). */
function luxe_product_url(int $id, string $slug = ''): string
{
    $slug = trim($slug);
    if ($slug !== '') {
        return 'product.php?slug=' . rawurlencode($slug);
    }

    return 'product.php?id=' . $id;
}

/**
 * Product card image URL for public seller store / listings (root + themed storefronts).
 */
function luxe_public_product_card_image(array $p): string
{
    $raw = trim((string) ($p['image_path'] ?? ''));
    if ($raw !== '' && strcasecmp($raw, 'default') !== 0) {
        if (preg_match('#^(?:https?:)?//#i', $raw) || str_starts_with($raw, '/')) {
            return $raw;
        }

        return luxe_public_href(ltrim($raw, '/'));
    }

    return 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=600&q=80';
}

/**
 * Full app config (includes/db.php uses ['db'] via db_config()).
 *
 * @return array<string, mixed>
 */
function luxe_app_config(): array
{
    static $full = null;
    if ($full !== null) {
        return $full;
    }
    $path = __DIR__ . '/config.php';
    if (!is_readable($path)) {
        throw new RuntimeException('Missing includes/config.php loader — ensure config.local.php or config.production.php exists.');
    }
    $cfg = require $path;
    if (!is_array($cfg)) {
        throw new RuntimeException('Invalid includes/config.php');
    }
    $full = $cfg;

    return $full;
}

/**
 * First URL segment path for this app when not at domain root (e.g. `luxe` for `/luxe/index.php`).
 * Empty string when the storefront lives at `/index.php`.
 */
function luxe_web_path_prefix(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($scriptName);
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        return '';
    }

    // Scripts under `…/actions/` share the storefront app root. Using dirname(SCRIPT_NAME) alone
    // would yield `…/actions`, so luxe_public_href('index.php') wrongly becomes `…/actions/index.php`
    // (e.g. after logout). Walk up one level for those requests.
    if (preg_match('#/actions$#', $dir)) {
        $dir = dirname($dir);
        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            return '';
        }
    }

    $dirNorm = trim(str_replace('\\', '/', $dir), '/');
    // Theme/seller/admin PHP lives under subfolders — app root is the parent so /script/, /actions/, etc. resolve correctly.
    if ($dirNorm !== '' && preg_match('#(?:^|/)(theme-\d+|seller|admin)$#', $dirNorm)) {
        $parent = dirname($dirNorm);
        if ($parent === '.' || $parent === '') {
            return '';
        }

        return trim(str_replace('\\', '/', $parent), '/');
    }

    return trim($dir, '/');
}

/**
 * Root-relative URL for this app (always starts with `/`). Safe for subdirectory installs and for
 * Theme 1/2 when root PHP files internally dispatch themed pages (CSS/JS/uploads must not use `../`).
 *
 * @param string $path e.g. `uploads/site/logo.png`, `profile.php?tab=x`, or already `https://...`
 */
function luxe_public_href(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    if ($path === '') {
        return '/';
    }
    if (preg_match('#^(?:https?:)?//#i', $path)) {
        return $path;
    }
    if ($path[0] === '/') {
        return $path;
    }
    $suffix = '';
    if (($hp = strpos($path, '#')) !== false) {
        $suffix = substr($path, $hp);
        $path = substr($path, 0, $hp);
    }
    $query = '';
    if (($qp = strpos($path, '?')) !== false) {
        $query = substr($path, $qp);
        $path = substr($path, 0, $qp);
    }
    $path = ltrim($path, '/');
    $base = luxe_web_path_prefix();

    return '/' . trim($base . '/' . $path, '/') . $query . $suffix;
}

/**
 * Strip app base from a luxe_public_href result for storing in a hidden `redirect` field (app-root-relative).
 */
function luxe_redirect_app_path_for_form(string $href): string
{
    $href = str_replace('\\', '/', $href);
    $suffix = '';
    if (($hp = strpos($href, '#')) !== false) {
        $suffix = substr($href, $hp);
        $href = substr($href, 0, $hp);
    }
    $query = '';
    if (($qp = strpos($href, '?')) !== false) {
        $query = substr($href, $qp);
        $href = substr($href, 0, $qp);
    }
    $path = ltrim($href, '/');
    $base = luxe_web_path_prefix();
    if ($base !== '' && str_starts_with($path, $base . '/')) {
        $path = substr($path, strlen($base) + 1);
    }

    return $path . $query . $suffix;
}

/**
 * When root URLs internally serve theme-1/theme-2, this is set (see storefront_theme.php).
 */
function luxe_storefront_theme_slug(): string
{
    return defined('LUXE_STOREFRONT_THEME_SLUG') ? (string) LUXE_STOREFRONT_THEME_SLUG : '';
}

/**
 * Theme-only static URL (css/js/theme actions) when serving root URLs with an active alternate theme.
 */
function luxe_theme_asset(string $relative): string
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $slug = luxe_storefront_theme_slug();
    if ($slug === '') {
        return $relative;
    }
    $base = luxe_web_path_prefix();
    $tail = trim($slug . '/' . $relative, '/');

    return '/' . trim($base . '/' . $tail, '/');
}

/** Root-relative `/…/actions/` base URL for themed storefront pages (subdirectory-safe). */
function luxe_actions_root_url(): string
{
    return luxe_public_href('actions') . '/';
}

/** Root-relative URL to a script under `/actions/`. */
function luxe_action_href(string $script): string
{
    return luxe_public_href('actions/' . ltrim($script, '/'));
}

/** AJAX cart endpoint path when using themed product pages from root URLs. */
function luxe_theme_cart_add_url(): string
{
    return luxe_storefront_theme_slug() !== ''
        ? luxe_theme_asset('actions/cart-add.php')
        : 'actions/cart-add.php';
}

/**
 * Five-star row HTML for product cards (matches `.pcard__star` / `.pcard__stars` in theme CSS).
 */
function luxe_pcard_stars_html(float $rating): string
{
    $filled = (int) round(max(0.0, min(5.0, $rating)));
    $label = $filled . ' out of 5 stars';
    $out = '<span class="pcard__stars" aria-label="' . h($label) . '">';
    for ($i = 1; $i <= 5; $i++) {
        $full = $i <= $filled;
        $cls = $full ? 'pcard__star pcard__star--full' : 'pcard__star';
        $glyph = $full ? '★' : '☆';
        $out .= '<span class="' . h($cls) . '">' . h($glyph) . '</span>';
    }
    $out .= '</span>';

    return $out;
}

/** Discount percent for product card "Save X%" badge, or null if no discount. */
function luxe_pcard_save_percent(array $p): ?int
{
    $orig = (float) ($p['original'] ?? 0);
    $curr = (float) ($p['price'] ?? 0);
    if ($orig > $curr && $orig > 0) {
        return (int) round((($orig - $curr) / $orig) * 100);
    }

    return null;
}
