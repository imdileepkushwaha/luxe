<?php

declare(strict_types=1);

require_once __DIR__ . '/site_settings.php';

/** @return '' for default storefront, or `theme-1` / `theme-2` when that skin should serve root URLs */
function storefront_theme_directory(PDO $pdo): string
{
    $v = trim(site_setting_get($pdo, 'storefront_theme', 'default'));

    return ($v === 'theme-1' || $v === 'theme-2' || $v === 'theme-3') ? $v : '';
}

/**
 * When Theme 1/2 is active, root storefront scripts (e.g. index.php) internally run the themed PHP file
 * so the browser URL stays /index.php — no theme-* segment.
 */
function storefront_theme_dispatch_from_root_if_needed(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (defined('LUXE_STOREFRONT_THEME_DISPATCHING')) {
        return;
    }
    if (
        (defined('LUXE_SKIP_STOREFRONT_THEME_DISPATCH') && LUXE_SKIP_STOREFRONT_THEME_DISPATCH)
        || (defined('LUXE_SKIP_STOREFRONT_THEME_REDIRECT') && LUXE_SKIP_STOREFRONT_THEME_REDIRECT)
    ) {
        return;
    }

    $projectRoot = dirname(__DIR__);
    $realRoot = realpath($projectRoot);
    $scriptFn = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    $realScript = $scriptFn !== '' ? realpath($scriptFn) : false;
    if ($realRoot === false || $realScript === false || !str_starts_with($realScript, $realRoot)) {
        return;
    }

    $rel = substr($realScript, strlen($realRoot));
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '' || str_contains($rel, '/')) {
        return;
    }

    try {
        $pdo = db();
    } catch (Throwable $e) {
        return;
    }

    $themeDir = storefront_theme_directory($pdo);
    if ($themeDir === '') {
        return;
    }

    $targetPath = $realRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $themeDir)
        . DIRECTORY_SEPARATOR . $rel;
    if (!is_file($targetPath)) {
        return;
    }

    define('LUXE_STOREFRONT_THEME_DISPATCHING', true);
    define('LUXE_STOREFRONT_THEME_SLUG', $themeDir);
    require $targetPath;
    exit;
}
