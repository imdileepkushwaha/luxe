<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'login') {
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($email) || empty($password)) {
            echo json_encode(['ok' => false, 'error' => 'Email and password required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Remove sensitive info
            unset($user['password_hash']);
            echo json_encode(['ok' => true, 'user' => $user]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
        }
        exit;
    }

    if ($action === 'register') {
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $firstName = $input['first_name'] ?? '';
        $lastName = $input['last_name'] ?? '';

        if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            echo json_encode(['ok' => false, 'error' => 'All fields are required']);
            exit;
        }

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Email already registered']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $hash, $firstName, $lastName]);
        $userId = $pdo->lastInsertId();

        echo json_encode(['ok' => true, 'message' => 'Registration successful', 'user_id' => $userId]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
