<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$fname = trim((string) ($data['first_name'] ?? ''));
$lname = trim((string) ($data['last_name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

if (strlen($fname) < 2 || strlen($lname) < 2) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Please enter your first and last name.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Enter a valid email address.']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    if ($st->fetch()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'An account with this email already exists.']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (email, password_hash, first_name, last_name) VALUES (?,?,?,?)');
    $ins->execute([$email, $hash, $fname, $lname]);
    auth_set_user((int) $pdo->lastInsertId());
    echo json_encode(['ok' => true, 'redirect' => 'index.php']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error. Check database configuration.']);
}
