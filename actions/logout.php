<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

auth_logout();
$redirect = luxe_public_href('index.php');
if (isset($_GET['redirect'])) {
    $r = trim((string) $_GET['redirect']);
    if ($r !== '' && !preg_match('#^https?://#i', $r) && strpos($r, '..') === false) {
        $redirect = luxe_public_href(ltrim($r, '/'));
    }
}
header('Location: ' . $redirect);
exit;
