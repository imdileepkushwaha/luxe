<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = auth_user_id();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please sign in to update your profile.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$first = trim((string) ($data['first_name'] ?? ''));
$last = trim((string) ($data['last_name'] ?? ''));
$dobRaw = trim((string) ($data['dob'] ?? ''));
$genderRaw = trim((string) ($data['gender'] ?? ''));

if ($first === '' || $last === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'First name and last name are required.']);
    exit;
}

if (strlen($first) > 100 || strlen($last) > 100) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Name fields are too long.']);
    exit;
}

$dob = null;
if ($dobRaw !== '') {
    $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dobRaw);
    if (!$dt || $dt->format('Y-m-d') !== $dobRaw) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Date of birth must be YYYY-MM-DD.']);
        exit;
    }
    $y = (int) $dt->format('Y');
    $thisYear = (int) date('Y');
    if ($y < 1900 || $y > $thisYear) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Date of birth year is not valid.']);
        exit;
    }
    $dob = $dobRaw;
}

$gender = null;
if ($genderRaw !== '') {
    if (!in_array($genderRaw, ['male', 'female', 'other'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Invalid gender value.']);
        exit;
    }
    $gender = $genderRaw;
}

try {
    $pdo = db();
    $st = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, dob = ?, gender = ? WHERE id = ?');
    $st->execute([$first, $last, $dob, $gender, $userId]);

    $ro = $pdo->prepare(
        'SELECT email, phone, email_verified_at, phone_verified_at FROM users WHERE id = ? LIMIT 1'
    );
    $ro->execute([$userId]);
    $contact = $ro->fetch() ?: [];

    echo json_encode([
        'ok' => true,
        'first_name' => $first,
        'last_name' => $last,
        'email' => (string) ($contact['email'] ?? ''),
        'phone' => (string) ($contact['phone'] ?? ''),
        'dob' => $dob,
        'gender' => $gender,
        'email_verified' => !empty($contact['email_verified_at']),
        'phone_verified' => !empty($contact['phone_verified_at']),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Could not save profile. Try again later.']);
}
