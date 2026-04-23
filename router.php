<?php
declare(strict_types=1);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($uriPath) || $uriPath === '') {
    $uriPath = '/';
}

$decodedPath = rawurldecode($uriPath);
$resolved = realpath(__DIR__ . $decodedPath);
$baseDir = realpath(__DIR__);

if ($resolved !== false && $baseDir !== false && str_starts_with($resolved, $baseDir)) {
    if (is_file($resolved)) {
        return false;
    }
    if (is_dir($resolved)) {
        $index = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($index)) {
            require $index;
            exit;
        }
    }
}

if ($decodedPath === '/' || $decodedPath === '') {
    require __DIR__ . '/index.php';
    exit;
}

$firstSegment = strtolower((string) strtok(ltrim($decodedPath, '/'), '/'));
$nonUserPrefixes = ['admin', 'seller', 'actions', 'api', 'vendor'];
if (in_array($firstSegment, $nonUserPrefixes, true)) {
    return false;
}

$pathInfo = pathinfo($decodedPath);
$hasPhpExt = isset($pathInfo['extension']) && strtolower((string) $pathInfo['extension']) === 'php';
if ($hasPhpExt) {
    $candidate = __DIR__ . $decodedPath;
    if (is_file($candidate)) {
        require $candidate;
        exit;
    }
}

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = rtrim(dirname($scriptName), '/.');
$nfPath = ($basePath !== '' ? $basePath : '') . '/page-not-found.php';
header('Location: ' . $nfPath, true, 302);
exit;
