<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$pdo = db();
$user = auth_user($pdo);
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not authenticated.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON.']);
    exit;
}

$orderRef = trim((string) ($data['order_ref'] ?? ''));
$productId = (int) ($data['product_id'] ?? 0);
$rating = (int) ($data['rating'] ?? 5);
$reviewText = trim((string) ($data['review_text'] ?? ''));
$customerName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));

$result = orders_try_submit_product_review($pdo, (int) $user['id'], $customerName, $orderRef, $productId, $rating, $reviewText);
echo json_encode($result);
