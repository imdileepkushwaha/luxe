<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$themeDir = storefront_theme_directory($pdo);
if ($themeDir === 'theme-2') {
    require __DIR__ . '/theme-2/settings.php';
    exit;
}
if ($themeDir === 'theme-1') {
    require __DIR__ . '/theme-1/settings.php';
    exit;
}

header('Location: ' . luxe_public_href('profile.php'), true, 302);
exit;
