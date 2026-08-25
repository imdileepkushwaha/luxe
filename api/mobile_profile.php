<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$pdo = db();
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$action = (string) ($input['action'] ?? ($_GET['action'] ?? 'summary'));
$userId = (int) ($input['user_id'] ?? ($_GET['user_id'] ?? 0));
if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    if ($action === 'redeem') {
        $points = (int) ($input['points'] ?? 0);
        $result = loyalty_try_redeem($pdo, $userId, $points);
        if (empty($result['ok'])) {
            echo json_encode(['ok' => false, 'error' => (string) ($result['message'] ?? 'Could not redeem points.')]);
            exit;
        }
        echo json_encode([
            'ok' => true,
            'message' => (string) ($result['message'] ?? 'Points redeemed.'),
            'balance' => (int) ($result['balance'] ?? 0),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    $st = $pdo->prepare('SELECT id, email, first_name, last_name, phone, created_at FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo json_encode(['ok' => false, 'error' => 'Account not found']);
        exit;
    }

    $stats = profile_order_stats_for_user($pdo, $userId);
    $loyalty = loyalty_summary_for_user($pdo, $userId);
    $balance = (int) ($loyalty['balance'] ?? 0);
    $goldAt = 1000;
    $platAt = 5000;
    if ($balance >= $platAt) {
        $tier = [
            'title' => 'LUXE Platinum Member',
            'lead' => "You've reached Platinum — enjoy exclusive perks.",
            'progress' => 100,
            'next' => null,
            'to_next' => 0,
        ];
    } elseif ($balance >= $goldAt) {
        $away = $platAt - $balance;
        $tier = [
            'title' => 'LUXE Gold Member',
            'lead' => "You're {$away} points away from Platinum status.",
            'progress' => min(100, (($balance - $goldAt) / ($platAt - $goldAt)) * 100),
            'next' => 'Platinum',
            'to_next' => $away,
        ];
    } else {
        $away = $goldAt - $balance;
        $tier = [
            'title' => 'LUXE Member',
            'lead' => "You're {$away} points away from Gold status.",
            'progress' => $goldAt > 0 ? min(100, ($balance / $goldAt) * 100) : 0,
            'next' => 'Gold',
            'to_next' => $away,
        ];
    }

    $history = [];
    foreach ($loyalty['history'] as $h) {
        $iso = (string) ($h['date_iso'] ?? '');
        $history[] = [
            'type' => (string) ($h['type'] ?? ''),
            'pts' => (int) ($h['pts'] ?? 0),
            'ref' => (string) ($h['ref'] ?? ''),
            'label' => (string) ($h['label'] ?? ''),
            'date' => $iso !== '' ? date('M j, Y', strtotime($iso)) : '',
        ];
    }

    $reviews = [];
    foreach (profile_delivered_review_rows_for_user($pdo, $userId) as $row) {
        $path = trim((string) ($row['image_path'] ?? ''));
        if ($path === '') {
            $path = trim((string) ($row['gallery_first'] ?? ''));
        }
        $reviews[] = [
            'product_id' => (int) ($row['product_id'] ?? 0),
            'name' => (string) (($row['item_name'] ?? '') !== '' ? $row['item_name'] : ($row['product_name'] ?? 'Item')),
            'order_ref' => (string) ($row['order_ref'] ?? ''),
            'variant' => (string) ($row['variant_text'] ?? ''),
            'image_url' => $path !== '' ? luxe_absolute_media_url($path) : '',
            'review_id' => (int) ($row['review_id'] ?? 0),
            'rating' => (int) ($row['rating'] ?? 0),
            'review_text' => (string) ($row['review_text'] ?? ''),
            'review_status' => (string) ($row['review_status'] ?? ''),
            'seller_response' => (string) ($row['seller_response'] ?? ''),
            'can_review' => empty($row['review_id']),
        ];
    }

    $createdAt = (string) ($user['created_at'] ?? '');
    $memberSince = 'Recently joined';
    if ($createdAt !== '' && strtotime($createdAt) !== false) {
        $memberSince = 'Member since ' . date('M Y', strtotime($createdAt));
    }

    echo json_encode([
        'ok' => true,
        'user' => [
            'id' => (int) $user['id'],
            'email' => (string) ($user['email'] ?? ''),
            'first_name' => (string) ($user['first_name'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'phone' => (string) ($user['phone'] ?? ''),
            'member_since' => $memberSince,
        ],
        'stats' => $stats,
        'loyalty' => [
            'balance' => $balance,
            'earned' => (int) ($loyalty['earned'] ?? 0),
            'pending' => (int) ($loyalty['pending'] ?? 0),
            'redeemed' => (int) ($loyalty['redeemed'] ?? 0),
            'gold_at' => $goldAt,
            'platinum_at' => $platAt,
            'tier' => $tier,
            'history' => $history,
        ],
        'reviews' => $reviews,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
