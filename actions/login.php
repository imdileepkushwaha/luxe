<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Email and password are required.']);
    exit;
}

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $row = $st->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Invalid email or password.']);
        exit;
    }
    auth_set_user((int) $row['id']);

    $requested = trim((string) ($data['redirect'] ?? ''));
    $allowedPages = ['index.php', 'cart.php', 'checkout.php', 'profile.php', 'orders.php', 'product.php'];
    $redirect = 'index.php';
    if ($requested !== '') {
        $path = parse_url($requested, PHP_URL_PATH);
        $base = $path !== null && $path !== '' ? basename($path) : basename($requested);
        if ($base !== '' && in_array($base, $allowedPages, true)) {
            $redirect = $base;
        }
    }

    echo json_encode(['ok' => true, 'redirect' => $redirect]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error. Check database configuration.']);
}
