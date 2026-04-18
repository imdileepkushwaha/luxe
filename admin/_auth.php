<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function admin_id(): ?int
{
    return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
}

function admin_set(int $adminId): void
{
    $_SESSION['admin_id'] = $adminId;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id']);
}

function admin_user(PDO $pdo): ?array
{
    $id = admin_id();
    if ($id === null) {
        return null;
    }

    $st = $pdo->prepare('SELECT id, email, full_name, is_active, created_at FROM admin_users WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();

    if (!$row || (int) $row['is_active'] !== 1) {
        admin_logout();
        return null;
    }

    return $row;
}

function admin_require_login(PDO $pdo): array
{
    $admin = admin_user($pdo);
    if (!$admin) {
        header('Location: login.php');
        exit;
    }

    return $admin;
}
