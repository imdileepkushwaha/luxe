<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/cart_session.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$raw = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($raw) || !isset($raw['items']) || !is_array($raw['items'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid payload']);
    exit;
}

$pdo = db();
$productSt = $pdo->prepare(
    'SELECT p.id, p.price
     FROM products p
     LEFT JOIN seller_users s ON s.id = p.seller_id
     WHERE p.id = ?
       AND p.active = 1
       AND p.approval_status = \'approved\'
       AND p.seller_id IS NOT NULL
       AND s.is_active = 1
       AND NOT EXISTS (
            SELECT 1
            FROM seller_account_deletion_requests dr
            WHERE dr.status = \'approved\'
              AND (dr.seller_id = s.id OR dr.email = s.email)
       )
     LIMIT 1'
);

$lines = [];
foreach ($raw['items'] as $it) {
    if (!is_array($it)) {
        continue;
    }
    $pid = (int) ($it['id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $qty = max(1, (int) ($it['qty'] ?? 1));
    $productSt->execute([$pid]);
    $row = $productSt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        continue;
    }
    $lines[] = [
        'id' => $pid,
        'price' => max(0, (int) ($row['price'] ?? 0)),
        'qty' => $qty,
    ];
}

$delivery = $lines === [] ? 0 : cart_compute_delivery_total($pdo, $lines);
$speeds = $lines === [] ? ['express' => 0, 'same_day' => 0] : cart_speed_fee_totals_for_lines($pdo, $lines);

echo json_encode([
    'ok' => true,
    'delivery' => $delivery,
    'express_fee' => (int) ($speeds['express'] ?? 0),
    'same_day_fee' => (int) ($speeds['same_day'] ?? 0),
], JSON_UNESCAPED_UNICODE);
