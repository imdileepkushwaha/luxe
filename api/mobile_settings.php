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

function mobile_settings_user_payload(array $user): array
{
    $createdAt = (string) ($user['created_at'] ?? '');
    $memberSince = 'Recently joined';
    if ($createdAt !== '' && strtotime($createdAt) !== false) {
        $memberSince = 'Member since ' . date('M Y', strtotime($createdAt));
    }
    $dobRaw = substr(trim((string) ($user['dob'] ?? '')), 0, 10);
    $dobIso = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobRaw) ? $dobRaw : '';
    $dobLabel = '';
    if ($dobIso !== '' && strtotime($dobIso) !== false) {
        $dobLabel = date('d M Y', strtotime($dobIso));
    }

    return [
        'id' => (int) ($user['id'] ?? 0),
        'email' => (string) ($user['email'] ?? ''),
        'first_name' => (string) ($user['first_name'] ?? ''),
        'last_name' => (string) ($user['last_name'] ?? ''),
        'phone' => (string) ($user['phone'] ?? ''),
        'gender' => (string) ($user['gender'] ?? ''),
        'dob' => $dobLabel,
        'dob_iso' => $dobIso,
        'member_since' => $memberSince,
    ];
}

$pdo = db();
$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$action = (string) ($input['action'] ?? ($_GET['action'] ?? 'profile'));
$userId = (int) ($input['user_id'] ?? ($_GET['user_id'] ?? 0));

if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

$st = $pdo->prepare(
    'SELECT id, email, first_name, last_name, phone, password_hash, created_at, gender, dob
     FROM users WHERE id = ? LIMIT 1'
);
try {
    $st->execute([$userId]);
} catch (Throwable) {
    $st = $pdo->prepare(
        'SELECT id, email, first_name, last_name, phone, password_hash, created_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $st->execute([$userId]);
}
$user = $st->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Account not found']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' || $action === 'profile') {
        $pending = account_deletion_pending_for_user($pdo, $userId);
        echo json_encode([
            'ok' => true,
            'user' => mobile_settings_user_payload($user),
            'deletion_pending' => $pending !== null,
            'deletion_process_after' => $pending['process_after'] ?? null,
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'update_profile') {
        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        $dobRaw = trim((string) ($input['dob'] ?? ''));
        $genderRaw = strtolower(trim((string) ($input['gender'] ?? '')));
        $phoneRaw = trim((string) ($input['phone'] ?? ''));

        if ($first === '' || $last === '') {
            echo json_encode(['ok' => false, 'error' => 'First name and last name are required.']);
            exit;
        }
        if (strlen($first) > 100 || strlen($last) > 100) {
            echo json_encode(['ok' => false, 'error' => 'Name fields are too long.']);
            exit;
        }

        $dob = null;
        if ($dobRaw !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $dobRaw);
            if (!$dt || $dt->format('Y-m-d') !== $dobRaw) {
                echo json_encode(['ok' => false, 'error' => 'Date of birth must be YYYY-MM-DD.']);
                exit;
            }
            $year = (int) $dt->format('Y');
            $thisYear = (int) date('Y');
            if ($year < 1900 || $year > $thisYear) {
                echo json_encode(['ok' => false, 'error' => 'Date of birth year is not valid.']);
                exit;
            }
            $dob = $dobRaw;
        }

        $gender = null;
        if ($genderRaw !== '') {
            if (!in_array($genderRaw, ['male', 'female', 'other'], true)) {
                echo json_encode(['ok' => false, 'error' => 'Invalid gender value.']);
                exit;
            }
            $gender = $genderRaw;
        }

        $phone = preg_replace('/\D+/', '', $phoneRaw) ?? '';
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }
        $currentPhone = preg_replace('/\D+/', '', (string) ($user['phone'] ?? '')) ?? '';
        if ($phone !== '' && $phone !== $currentPhone && !preg_match('/^[6-9][0-9]{9}$/', $phone)) {
            echo json_encode(['ok' => false, 'error' => 'Enter a valid 10-digit mobile number.']);
            exit;
        }
        if ($phone === $currentPhone) {
            $phone = (string) ($user['phone'] ?? $phone);
        }

        try {
            $up = $pdo->prepare(
                'UPDATE users SET first_name = ?, last_name = ?, dob = ?, gender = ?, phone = ? WHERE id = ?'
            );
            $up->execute([$first, $last, $dob, $gender, $phone, $userId]);
        } catch (Throwable) {
            $up = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?');
            $up->execute([$first, $last, $userId]);
        }

        $fresh = $pdo->prepare(
            'SELECT id, email, first_name, last_name, phone, created_at, gender, dob FROM users WHERE id = ? LIMIT 1'
        );
        try {
            $fresh->execute([$userId]);
        } catch (Throwable) {
            $fresh = $pdo->prepare(
                'SELECT id, email, first_name, last_name, phone, created_at FROM users WHERE id = ? LIMIT 1'
            );
            $fresh->execute([$userId]);
        }
        $updated = $fresh->fetch(PDO::FETCH_ASSOC) ?: $user;
        echo json_encode([
            'ok' => true,
            'message' => 'Profile updated.',
            'user' => mobile_settings_user_payload($updated),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'change_password') {
        $current = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['new_password'] ?? '');
        if ($current === '' || $new === '') {
            echo json_encode(['ok' => false, 'error' => 'Current password and new password are required.']);
            exit;
        }
        if (strlen($new) < 8) {
            echo json_encode(['ok' => false, 'error' => 'New password must be at least 8 characters.']);
            exit;
        }
        if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/[0-9]/', $new)) {
            echo json_encode(['ok' => false, 'error' => 'New password must include at least one letter and one number.']);
            exit;
        }
        if ($new === $current) {
            echo json_encode(['ok' => false, 'error' => 'New password must be different from your current password.']);
            exit;
        }
        if (!password_verify($current, (string) ($user['password_hash'] ?? ''))) {
            echo json_encode(['ok' => false, 'error' => 'Current password is incorrect.']);
            exit;
        }
        $up = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $up->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        echo json_encode(['ok' => true, 'message' => 'Password updated successfully.']);
        exit;
    }

    if ($action === 'delete_account') {
        $result = account_deletion_request_create(
            $pdo,
            $userId,
            (string) ($user['email'] ?? ''),
            (string) ($user['first_name'] ?? ''),
            (string) ($user['last_name'] ?? '')
        );
        if ($result !== true) {
            echo json_encode(['ok' => false, 'error' => $result]);
            exit;
        }
        echo json_encode([
            'ok' => true,
            'message' => 'Your deletion request has been submitted. Your account will be removed within 48 hours.',
        ]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
