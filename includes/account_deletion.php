<?php

declare(strict_types=1);

const ACCOUNT_DELETION_HOURS = 48;

function account_deletion_pending_for_user(PDO $pdo, int $userId): ?array
{
    $st = $pdo->prepare(
        "SELECT id, process_after, requested_at
         FROM user_account_deletion_requests
         WHERE user_id = ? AND status = 'pending'
         LIMIT 1"
    );
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return true|string true on success, error message on failure */
function account_deletion_request_create(PDO $pdo, int $userId, string $email, string $firstName, string $lastName)
{
    if (account_deletion_pending_for_user($pdo, $userId)) {
        return 'A deletion request is already pending for your account.';
    }
    $processAfter = (new DateTimeImmutable('now'))->modify('+' . ACCOUNT_DELETION_HOURS . ' hours')->format('Y-m-d H:i:s');
    $ins = $pdo->prepare(
        'INSERT INTO user_account_deletion_requests (user_id, email, first_name, last_name, status, process_after)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        $userId,
        $email,
        $firstName,
        $lastName,
        'pending',
        $processAfter,
    ]);

    return true;
}

/** Pending + overdue pending for admin list */
function account_deletion_admin_list(PDO $pdo): array
{
    return $pdo->query(
        "SELECT r.id, r.user_id, r.email, r.first_name, r.last_name, r.status, r.requested_at, r.process_after, r.completed_at
         FROM user_account_deletion_requests r
         ORDER BY FIELD(r.status, 'pending', 'completed', 'cancelled'), r.requested_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function account_deletion_admin_cancel(PDO $pdo, int $requestId): bool
{
    $st = $pdo->prepare("UPDATE user_account_deletion_requests SET status = 'cancelled', completed_at = NOW() WHERE id = ? AND status = 'pending'");
    $st->execute([$requestId]);

    return $st->rowCount() > 0;
}

/** Complete pending requests whose process_after has passed: remove user, mark request completed. */
function account_deletion_process_overdue(PDO $pdo): int
{
    $st = $pdo->query(
        "SELECT id, user_id FROM user_account_deletion_requests
         WHERE status = 'pending' AND process_after <= NOW()"
    );
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $n = 0;
    foreach ($rows as $row) {
        $rid = (int) $row['id'];
        $uid = (int) $row['user_id'];
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            $pdo->prepare("UPDATE user_account_deletion_requests SET status = 'completed', completed_at = NOW() WHERE id = ? AND status = 'pending'")->execute([$rid]);
            $pdo->commit();
            $n++;
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    return $n;
}

function seller_deletion_pending_for_seller(PDO $pdo, int $sellerId): ?array
{
    $st = $pdo->prepare(
        "SELECT id, requested_at
         FROM seller_account_deletion_requests
         WHERE seller_id = ? AND status = 'pending'
         LIMIT 1"
    );
    $st->execute([$sellerId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function seller_deletion_latest_for_seller(PDO $pdo, int $sellerId): ?array
{
    $st = $pdo->prepare(
        "SELECT id, status, requested_at, reviewed_at
         FROM seller_account_deletion_requests
         WHERE seller_id = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $st->execute([$sellerId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function seller_deletion_latest_for_email(PDO $pdo, string $email): ?array
{
    $st = $pdo->prepare(
        "SELECT id, status, requested_at, reviewed_at
         FROM seller_account_deletion_requests
         WHERE email = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** @return true|string true on success, error message on failure */
function seller_deletion_request_create(PDO $pdo, int $sellerId, string $email, string $fullName)
{
    $latest = seller_deletion_latest_for_seller($pdo, $sellerId);
    $latestByEmail = seller_deletion_latest_for_email($pdo, $email);
    $effectiveLatest = $latestByEmail ?: $latest;
    if ($effectiveLatest && (string) ($effectiveLatest['status'] ?? '') === 'pending') {
        return 'A deletion request is already pending for your seller account.';
    }
    if ($effectiveLatest && (string) ($effectiveLatest['status'] ?? '') === 'approved') {
        return 'Your seller deletion request has already been approved.';
    }

    $ins = $pdo->prepare(
        'INSERT INTO seller_account_deletion_requests (seller_id, email, full_name, status)
         VALUES (?, ?, ?, ?)'
    );
    $ins->execute([
        $sellerId,
        $email,
        $fullName,
        'pending',
    ]);

    return true;
}

function seller_deletion_admin_list(PDO $pdo): array
{
    return $pdo->query(
        "SELECT r.id, r.seller_id, r.email, r.full_name, r.status, r.requested_at, r.reviewed_at, r.rejection_reason,
                s.is_active
         FROM seller_account_deletion_requests r
         LEFT JOIN seller_users s ON s.id = r.seller_id
         ORDER BY FIELD(r.status, 'pending', 'approved', 'rejected'), r.requested_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function seller_mark_products_discontinued(PDO $pdo, int $sellerId): void
{
    $upd = $pdo->prepare(
        "UPDATE products
         SET active = 0,
             stock_qty = 0,
             badge = 'Discontinued'
         WHERE seller_id = ?"
    );
    $upd->execute([$sellerId]);
}

function seller_deletion_admin_approve(PDO $pdo, int $requestId, int $adminId): bool
{
    $st = $pdo->prepare(
        "SELECT id, seller_id, email
         FROM seller_account_deletion_requests
         WHERE id = ? AND status = 'pending'
         LIMIT 1"
    );
    $st->execute([$requestId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }

    try {
        $pdo->beginTransaction();

        // Find the live seller row first by id/email.
        $sellerSt = $pdo->prepare(
            "SELECT id
             FROM seller_users
             WHERE id = ? OR email = ?
             LIMIT 1"
        );
        $sellerSt->execute([(int) $row['seller_id'], (string) ($row['email'] ?? '')]);
        $sellerRow = $sellerSt->fetch(PDO::FETCH_ASSOC);
        $sellerDbId = (int) ($sellerRow['id'] ?? 0);

        if ($sellerDbId > 0) {
            // Ensure seller catalog becomes unavailable for purchase before account removal.
            seller_mark_products_discontinued($pdo, $sellerDbId);
            // Hard safety: disable login first so account cannot stay active.
            $pdo->prepare('UPDATE seller_users SET is_active = 0 WHERE id = ? LIMIT 1')->execute([$sellerDbId]);
            // Try hard delete after deactivation.
            $pdo->prepare('DELETE FROM seller_users WHERE id = ? LIMIT 1')->execute([$sellerDbId]);
        }

        $pdo->prepare(
            "UPDATE seller_account_deletion_requests
             SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ''
             WHERE id = ? AND status = 'pending'
             LIMIT 1"
        )->execute([$adminId, $requestId]);

        $pdo->commit();
        return true;
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function seller_deletion_admin_reject(PDO $pdo, int $requestId, int $adminId, string $reason): bool
{
    $st = $pdo->prepare(
        "UPDATE seller_account_deletion_requests
         SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ?
         WHERE id = ? AND status = 'pending'
         LIMIT 1"
    );
    $st->execute([$adminId, $reason, $requestId]);

    return $st->rowCount() > 0;
}
