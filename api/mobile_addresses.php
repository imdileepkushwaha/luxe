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
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$action = (string) ($input['action'] ?? ($_GET['action'] ?? 'list'));
$userId = (int) ($input['user_id'] ?? ($_GET['user_id'] ?? 0));

if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    if ($action === 'list' || $action === '') {
        echo json_encode([
            'ok' => true,
            'addresses' => addresses_fetch_for_user($pdo, $userId),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'save') {
        $parsed = addresses_validate_payload($input);
        if (empty($parsed['ok'])) {
            echo json_encode(['ok' => false, 'error' => (string) ($parsed['message'] ?? 'Invalid address')]);
            exit;
        }
        $v = $parsed;
        unset($v['ok']);
        $addressId = (int) ($input['id'] ?? 0);

        if ($addressId > 0) {
            $existing = addresses_get_for_user($pdo, $userId, $addressId);
            if (!$existing) {
                echo json_encode(['ok' => false, 'error' => 'Address not found']);
                exit;
            }
            if (!addresses_update($pdo, $userId, $addressId, $v)) {
                echo json_encode(['ok' => false, 'error' => 'Could not update address']);
                exit;
            }
        } else {
            $addressId = addresses_insert($pdo, $userId, $v);
        }

        echo json_encode([
            'ok' => true,
            'address_id' => $addressId,
            'addresses' => addresses_fetch_for_user($pdo, $userId),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'set_default') {
        $addressId = (int) ($input['id'] ?? 0);
        if ($addressId <= 0 || !addresses_set_default($pdo, $userId, $addressId)) {
            echo json_encode(['ok' => false, 'error' => 'Address not found']);
            exit;
        }
        echo json_encode([
            'ok' => true,
            'addresses' => addresses_fetch_for_user($pdo, $userId),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
