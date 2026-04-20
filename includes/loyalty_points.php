<?php

declare(strict_types=1);

/** Every this many ₹ (delivered order total) earns {@see LUXE_LOYALTY_POINTS_PER_BLOCK} points (after hold period). */
const LUXE_LOYALTY_RUPEES_PER_BLOCK = 1000;
const LUXE_LOYALTY_POINTS_PER_BLOCK = 10;
/** Points appear only this many full days after delivery timestamp. */
const LUXE_LOYALTY_CREDIT_DELAY_DAYS = 10;

function loyalty_points_from_order_total(int $totalRupees): int
{
    if ($totalRupees <= 0) {
        return 0;
    }

    return intdiv($totalRupees, LUXE_LOYALTY_RUPEES_PER_BLOCK) * LUXE_LOYALTY_POINTS_PER_BLOCK;
}

function loyalty_user_redeemed_total(PDO $pdo, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $st = $pdo->prepare('SELECT COALESCE(loyalty_points_redeemed, 0) AS r FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return max(0, (int) ($row['r'] ?? 0));
}

/**
 * Return orders that block loyalty for this user+ref (open return or refund path — not rejected).
 */
/**
 * @return array<string,true>
 */
function loyalty_blocked_order_refs_for_user(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT DISTINCT order_ref FROM user_return_requests
         WHERE user_id = ? AND status <> \'rejected\''
    );
    $st->execute([$userId]);
    $out = [];
    while ($ref = $st->fetchColumn()) {
        $s = trim((string) $ref);
        if ($s !== '') {
            $out[$s] = true;
        }
    }

    return $out;
}

/**
 * @return array{
 *   earned:int,
 *   pending:int,
 *   redeemed:int,
 *   balance:int,
 *   history:list<array{type:string,pts:int,ref:string,label:string,date_iso:string}>
 * }
 */
function loyalty_summary_for_user(PDO $pdo, int $userId): array
{
    $empty = [
        'earned' => 0,
        'pending' => 0,
        'redeemed' => 0,
        'balance' => 0,
        'history' => [],
    ];
    if ($userId <= 0) {
        return $empty;
    }

    $redeemed = loyalty_user_redeemed_total($pdo, $userId);

    $st = $pdo->prepare(
        'SELECT o.order_ref, o.total_amount, o.delivered_at
         FROM orders o
         WHERE o.user_id = ?
           AND o.status = \'delivered\'
           AND o.delivered_at IS NOT NULL ORDER BY o.delivered_at DESC
         LIMIT 80'
    );
    $st->execute([$userId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $blocked = loyalty_blocked_order_refs_for_user($pdo, $userId);

    $earned = 0;
    $pending = 0;
    $history = [];
    $now = new DateTimeImmutable('now');

    foreach ($rows as $r) {
        $ref = (string) ($r['order_ref'] ?? '');
        if ($ref === '' || isset($blocked[$ref])) {
            continue;
        }
        $amt = (int) ($r['total_amount'] ?? 0);
        $pts = loyalty_points_from_order_total($amt);
        if ($pts <= 0) {
            continue;
        }
        $delRaw = (string) ($r['delivered_at'] ?? '');
        if ($delRaw === '') {
            continue;
        }
        try {
            $delAt = new DateTimeImmutable($delRaw);
        } catch (Throwable) {
            continue;
        }
        $creditAt = $delAt->modify('+' . LUXE_LOYALTY_CREDIT_DELAY_DAYS . ' days');
        if ($now < $creditAt) {
            $pending += $pts;
            $history[] = [
                'type' => 'pending',
                'pts' => $pts,
                'ref' => $ref,
                'label' => 'Pending — credits on ' . $creditAt->format('M j, Y'),
                'date_iso' => $creditAt->format('Y-m-d'),
            ];
        } else {
            $earned += $pts;
            $history[] = [
                'type' => 'credited',
                'pts' => $pts,
                'ref' => $ref,
                'label' => 'Delivered order · credited',
                'date_iso' => $creditAt->format('Y-m-d'),
            ];
        }
    }

    $balance = max(0, $earned - $redeemed);

    return [
        'earned' => $earned,
        'pending' => $pending,
        'redeemed' => $redeemed,
        'balance' => $balance,
        'history' => $history,
    ];
}

function loyalty_try_redeem(PDO $pdo, int $userId, int $points): array
{
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Please sign in.'];
    }
    if ($points < 100 || $points % 100 !== 0) {
        return ['ok' => false, 'message' => 'Redeem in multiples of 100 points (minimum 100).'];
    }

    try {
        $pdo->beginTransaction();
        $summary = loyalty_summary_for_user($pdo, $userId);
        if ($points > $summary['balance']) {
            $pdo->rollBack();

            return ['ok' => false, 'message' => 'Not enough points to redeem.'];
        }
        $upd = $pdo->prepare(
            'UPDATE users SET loyalty_points_redeemed = loyalty_points_redeemed + ? WHERE id = ? LIMIT 1'
        );
        $upd->execute([$points, $userId]);
        if ($upd->rowCount() < 1) {
            $pdo->rollBack();

            return ['ok' => false, 'message' => 'Could not update account.'];
        }
        $pdo->commit();

        $newSummary = loyalty_summary_for_user($pdo, $userId);

        return [
            'ok' => true,
            'message' => 'Points redeemed. ₹' . (string) (int) (($points / 100) * 10) . ' off will apply on your next eligible checkout.',
            'balance' => $newSummary['balance'],
        ];
    } catch (Throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => 'Server error. Try again.'];
    }
}
