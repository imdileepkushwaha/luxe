<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function seller_id(): ?int
{
    return isset($_SESSION['seller_id']) ? (int) $_SESSION['seller_id'] : null;
}

function seller_set(int $sellerId): void
{
    $_SESSION['seller_id'] = $sellerId;
}

function seller_logout(): void
{
    unset($_SESSION['seller_id']);
}

/**
 * @return list<string>
 */
function seller_categories_from_csv(string $csv): array
{
    $parts = array_map('trim', explode(',', strtolower($csv)));
    $parts = array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));
    return array_values(array_unique($parts));
}

function seller_user(PDO $pdo): ?array
{
    $id = seller_id();
    if ($id === null) {
        return null;
    }

    $st = $pdo->prepare(
        'SELECT id, email, full_name, allowed_categories, is_active, created_at,
                kyc_completed, kyc_updated_at, kyc_final_approved, kyc_final_reviewed_at, kyc_rejection_reason,
                kyc_edit_request_status, kyc_edit_requested_at, kyc_edit_reviewed_at, kyc_edit_rejection_reason, kyc_edit_unlocked
         FROM seller_users
         WHERE id = ?
         LIMIT 1'
    );
    $st->execute([$id]);
    $row = $st->fetch();

    if (!$row || (int) $row['is_active'] !== 1) {
        seller_logout();
        return null;
    }

    // Safety: if admin has approved deletion request, block seller login immediately.
    $delSt = $pdo->prepare(
        "SELECT status
         FROM seller_account_deletion_requests
         WHERE seller_id = ? OR email = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $delSt->execute([$id, (string) ($row['email'] ?? '')]);
    $delStatus = strtolower((string) $delSt->fetchColumn());
    if ($delStatus === 'approved') {
        seller_logout();
        return null;
    }

    $row['allowed_categories'] = seller_categories_from_csv((string) ($row['allowed_categories'] ?? ''));
    return $row;
}

function seller_require_login(PDO $pdo): array
{
    $seller = seller_user($pdo);
    if (!$seller) {
        header('Location: login.php');
        exit;
    }

    return $seller;
}
