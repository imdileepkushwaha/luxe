<?php

declare(strict_types=1);

require_once __DIR__ . '/../captcha.php';

if (!luxe_captcha_enabled()) {
    echo "<script>window.__LUXE_CAPTCHA_ENABLED__=false;</script>\n";
    return;
}

if (!defined('LUXE_CAPTCHA_ASSETS_LOADED')) {
    define('LUXE_CAPTCHA_ASSETS_LOADED', true);
    $clientJs = luxe_public_href('script/captcha-client.js');
    $captchaCss = luxe_public_href('css/captcha.css');
    $refreshUrl = luxe_public_href('actions/captcha-refresh.php');
    $provider = luxe_captcha_provider();

    echo '<link rel="stylesheet" href="' . h($captchaCss) . '">' . "\n";
    $context = luxe_captcha_ui_context();
    if ($context === 'default') {
        $defaultCaptchaCss = luxe_public_href('css/captcha-default.css');
        echo '<link rel="stylesheet" href="' . h($defaultCaptchaCss) . '">' . "\n";
    }
    $themeCaptchaCss = luxe_captcha_theme_stylesheet_href();
    if ($themeCaptchaCss !== '') {
        echo '<link rel="stylesheet" href="' . h($themeCaptchaCss) . '">' . "\n";
    }
    echo '<script>window.__LUXE_CAPTCHA_ENABLED__=true;window.__LUXE_CAPTCHA_PROVIDER__=' . json_encode($provider, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . ';window.__LUXE_CAPTCHA_REFRESH_URL__=' . json_encode($refreshUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . ';';

    if ($provider === 'recaptcha') {
        $siteKey = luxe_captcha_site_key();
        echo 'window.__LUXE_CAPTCHA_SITE_KEY__=' . json_encode($siteKey, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . ';';
    }

    echo '</script>' . "\n";
    echo '<script src="' . h($clientJs) . '"></script>' . "\n";

    if ($provider === 'recaptcha') {
        echo '<script src="https://www.google.com/recaptcha/api.js?onload=luxeCaptchaOnload&render=explicit" async defer></script>' . "\n";
    }
}
