<?php

declare(strict_types=1);

function auth_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function auth_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function auth_set_user(int $userId): void
{
    $_SESSION['user_id'] = $userId;
}

function auth_logout(): void
{
    unset($_SESSION['user_id']);
}

function auth_user(PDO $pdo): ?array
{
    $id = auth_user_id();
    if ($id === null) {
        return null;
    }
    $st = $pdo->prepare(
        'SELECT id, email, first_name, last_name, phone, dob, gender, created_at,
                email_verified_at, phone_verified_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}
