<?php
declare(strict_types=1);

/**
 * Platform-wide checkout payment gateway config (admin-managed).
 */

function platform_payment_gateway_defaults(): array
{
    return [
        'gateway' => 'none',
        'mode' => 'test',
        'public_key' => '',
        'secret_key' => '',
        'merchant_id' => '',
        'webhook_secret' => '',
    ];
}

/**
 * @return array{gateway: string, mode: string, public_key: string, secret_key: string, merchant_id: string, webhook_secret: string}
 */
function platform_payment_gateway_load(PDO $pdo): array
{
    $defaults = platform_payment_gateway_defaults();
    try {
        $st = $pdo->query(
            'SELECT gateway, mode, public_key, secret_key, merchant_id, webhook_secret
             FROM platform_payment_gateway_config WHERE id = 1 LIMIT 1'
        );
        $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
        if (!$row) {
            return $defaults;
        }

        return array_merge($defaults, $row);
    } catch (Throwable) {
        return $defaults;
    }
}

function platform_payment_gateway_public_base_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $norm = str_replace('\\', '/', $script);
    $dir = dirname($norm);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }

    return rtrim($dir, '/');
}

function platform_payment_gateway_webhook_url(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $basePath = platform_payment_gateway_public_base_path();

    return $scheme . '://' . $host . $basePath . '/actions/platform-payment-webhook.php';
}
