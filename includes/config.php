<?php

declare(strict_types=1);

/**
 * Loads environment-specific settings from config.local.php or config.production.php.
 *
 * Auto-detect:
 *   - localhost / 127.0.0.1  → local
 *   - live domain            → production
 *
 * Override: set env LUXE_ENV=local or LUXE_ENV=production (CLI / server).
 */

if (!function_exists('luxe_detect_app_env')) {
    function luxe_detect_app_env(): string
    {
        $forced = getenv('LUXE_ENV');
        if (is_string($forced) && $forced !== '') {
            $env = strtolower(trim($forced));
            if (in_array($env, ['local', 'production'], true)) {
                return $env;
            }
        }

        if (PHP_SAPI === 'cli') {
            return strtolower(PHP_OS_FAMILY) === 'windows' ? 'local' : 'production';
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        if ($host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return 'local';
        }

        return 'production';
    }
}

$luxeAppEnv = luxe_detect_app_env();
if (!defined('LUXE_APP_ENV')) {
    define('LUXE_APP_ENV', $luxeAppEnv);
}

$configPath = __DIR__ . '/config.' . $luxeAppEnv . '.php';
if (!is_readable($configPath)) {
    throw new RuntimeException(
        'Missing includes/config.' . $luxeAppEnv . '.php — copy includes/config.' . $luxeAppEnv . '.example.php and set your credentials.'
    );
}

$cfg = require $configPath;
if (!is_array($cfg)) {
    throw new RuntimeException('Invalid includes/config.' . $luxeAppEnv . '.php');
}

return $cfg;
