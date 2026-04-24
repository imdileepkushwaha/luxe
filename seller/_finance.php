<?php
declare(strict_types=1);

/**
 * Seller’s share of order-level admin commission (proportional to their lines’ merchandise subtotal).
 *
 * @param string $orderWhereSql SQL fragment on alias `o` (e.g. "o.status = 'delivered'")
 */
function seller_finance_allocated_admin_commission_rupees(PDO $pdo, int $sellerId, string $orderWhereSql): int
{
    $sql = "SELECT COALESCE(SUM(o.admin_commission_rupees * xs.seller_sub / om.order_merch), 0)
            FROM orders o
            INNER JOIN (
                SELECT order_id, SUM(price * qty) AS order_merch
                FROM order_items
                GROUP BY order_id
            ) om ON om.order_id = o.id AND om.order_merch > 0
            INNER JOIN (
                SELECT oi.order_id, SUM(oi.price * oi.qty) AS seller_sub
                FROM order_items oi
                INNER JOIN products p ON p.id = oi.product_id
                WHERE p.seller_id = ?
                GROUP BY oi.order_id
            ) xs ON xs.order_id = o.id
            WHERE o.admin_commission_rupees > 0 AND " . $orderWhereSql;

    $st = $pdo->prepare($sql);
    $st->execute([$sellerId]);

    return (int) round((float) $st->fetchColumn());
}

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

    $delivered -= seller_finance_allocated_admin_commission_rupees($pdo, $sellerId, "o.status = 'delivered'");
    $pipeline -= seller_finance_allocated_admin_commission_rupees($pdo, $sellerId, "o.status IN ('processing','shipped')");
    if ($delivered < 0) {
        $delivered = 0;
    }
    if ($pipeline < 0) {
        $pipeline = 0;
    }

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

function seller_finance_delivered_order_count(PDO $pdo, int $sellerId): int
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM (
             SELECT o.id
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             INNER JOIN products p ON p.id = oi.product_id
             WHERE p.seller_id = ?
               AND o.status = 'delivered'
             GROUP BY o.id
         ) x"
    );
    $st->execute([$sellerId]);

    return (int) $st->fetchColumn();
}

/** @return list<array<string,mixed>> */
function seller_finance_recent_delivered_orders(PDO $pdo, int $sellerId, int $limit = 10, int $offset = 0): array
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
         LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
    );
    $st->execute([$sellerId]);
    return $st->fetchAll();
}

function seller_finance_withdraw_request_count(PDO $pdo, int $sellerId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM seller_withdraw_requests WHERE seller_id = ?');
    $st->execute([$sellerId]);

    return (int) $st->fetchColumn();
}

/** @return list<array<string,mixed>> */
function seller_finance_withdraw_requests(PDO $pdo, int $sellerId, int $limit = 30, int $offset = 0): array
{
    $st = $pdo->prepare(
        "SELECT id, amount, method, account_ref, note, status, requested_at, reviewed_at, rejection_reason
         FROM seller_withdraw_requests
         WHERE seller_id = ?
         ORDER BY id DESC
         LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
    );
    $st->execute([$sellerId]);
    return $st->fetchAll();
}

