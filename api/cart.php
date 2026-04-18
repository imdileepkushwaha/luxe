<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart_session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sessionCart = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
    $safeCart = cart_filter_available_items(db(), $sessionCart);
    $_SESSION['cart'] = $safeCart;
    echo json_encode($safeCart, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['ok' => false]);
        exit;
    }
    $safeCart = cart_filter_available_items(db(), $input);
    $_SESSION['cart'] = $safeCart;
    echo json_encode(['ok' => true, 'cart' => $safeCart], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false]);
