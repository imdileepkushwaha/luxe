<?php
declare(strict_types=1);

/**
 * @return array{
 *   delivered_total:int,
 *   pipeline_total:int,
 *   cancelled_total:int,
 *   paid_out_total:int,
 *   pending_withdraw_total:int,
 *   withdrawable_balance:int
 * }
 */
function seller_finance_summary(PDO $pdo, int $sellerId): array
{
    $summarySt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN oi.price * oi.qty ELSE 0 END), 0) AS delivered_total,
            COALESCE(SUM(CASE WHEN o.status IN ('processing', 'shipped') THEN oi.price * oi.qty ELSE 0 END), 0) AS pipeline_total,
            COALESCE(SUM(CASE WHEN o.status = 'cancelled' THEN oi.price * oi.qty ELSE 0 END), 0) AS cancelled_total
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         INNER JOIN products p ON p.id = oi.product_id
         WHERE p.seller_id = ?"
    );
    $summarySt->execute([$sellerId]);
    $orderSummary = $summarySt->fetch() ?: [];

    $withdrawSt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN status IN ('approved', 'paid') THEN amount ELSE 0 END), 0) AS paid_out_total,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_withdraw_total
         FROM seller_withdraw_requests
         WHERE seller_id = ?"
    );
    $withdrawSt->execute([$sellerId]);
    $withdrawSummary = $withdrawSt->fetch() ?: [];

    $delivered = (int) ($orderSummary['delivered_total'] ?? 0);
    $pipeline = (int) ($orderSummary['pipeline_total'] ?? 0);
    $cancelled = (int) ($orderSummary['cancelled_total'] ?? 0);
    $paidOut = (int) ($withdrawSummary['paid_out_total'] ?? 0);
    $pendingWithdraw = (int) ($withdrawSummary['pending_withdraw_total'] ?? 0);
    $withdrawable = $delivered - $paidOut - $pendingWithdraw;
    if ($withdrawable < 0) {
        $withdrawable = 0;
    }

    return [
        'delivered_total' => $delivered,
        'pipeline_total' => $pipeline,
        'cancelled_total' => $cancelled,
        'paid_out_total' => $paidOut,
        'pending_withdraw_total' => $pendingWithdraw,
        'withdrawable_balance' => $withdrawable,
    ];
}

/** @return list<array<string,mixed>> */
function seller_finance_recent_delivered_orders(PDO $pdo, int $sellerId, int $limit = 10): array
{
    $st = $pdo->prepare(
        "SELECT o.id, o.order_ref, o.created_at,
                SUM(oi.qty) AS total_qty,
                SUM(oi.price * oi.qty) AS seller_total
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         INNER JOIN products p ON p.id = oi.product_id
         WHERE p.seller_id = ?
           AND o.status = 'delivered'
         GROUP BY o.id, o.order_ref, o.created_at
         ORDER BY o.id DESC
         LIMIT " . (int) $limit
    );
    $st->execute([$sellerId]);
    return $st->fetchAll();
}

/** @return list<array<string,mixed>> */
function seller_finance_withdraw_requests(PDO $pdo, int $sellerId, int $limit = 30): array
{
    $st = $pdo->prepare(
        "SELECT id, amount, method, account_ref, note, status, requested_at, reviewed_at, rejection_reason
         FROM seller_withdraw_requests
         WHERE seller_id = ?
         ORDER BY id DESC
         LIMIT " . (int) $limit
    );
    $st->execute([$sellerId]);
    return $st->fetchAll();
}

