<?php

declare(strict_types=1);

function seller_sync_product_review_metrics(PDO $pdo, int $productId): void
{
    $aggSt = $pdo->prepare(
        'SELECT COUNT(*) AS total_reviews, COALESCE(AVG(rating), 0) AS avg_rating
         FROM product_reviews
         WHERE product_id = ? AND review_status = ?'
    );
    $aggSt->execute([$productId, 'approved']);
    $agg = $aggSt->fetch() ?: ['total_reviews' => 0, 'avg_rating' => 0];

    $updProduct = $pdo->prepare(
        'UPDATE products
         SET review_count = ?, rating = ?
         WHERE id = ?
         LIMIT 1'
    );
    $updProduct->execute([
        (int) ($agg['total_reviews'] ?? 0),
        (float) ($agg['avg_rating'] ?? 0),
        $productId,
    ]);
}

function seller_reviews_format_dt(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('M j, Y · g:i A');
    } catch (Throwable) {
        return $raw;
    }
}

function seller_reviews_status_chip_mod(string $status): string
{
    return match (strtolower(trim($status))) {
        'approved' => 'seller-status-chip--delivered',
        'rejected' => 'seller-status-chip--rejected',
        'pending' => 'seller-status-chip--pending',
        default => '',
    };
}

function seller_reviews_status_label(string $status): string
{
    $s = strtolower(trim($status));

    return match ($s) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'pending' => 'Pending',
        default => $s !== '' ? ucfirst($s) : '—',
    };
}

function seller_reviews_render_stars(int $rating): void
{
    $rating = max(0, min(5, $rating));
    echo '<span class="seller-reviews-stars" role="img" aria-label="' . h((string) $rating) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        $on = $i <= $rating;
        echo '<span class="seller-reviews-star' . ($on ? ' seller-reviews-star--on' : '') . '" aria-hidden="true">';
        echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
        echo '</span>';
    }
    echo '</span>';
}

/**
 * @return array{ok: bool, message: string, product_id: int}
 */
function seller_apply_review_moderation(
    PDO $pdo,
    int $sellerId,
    int $reviewId,
    string $sellerResponse,
    string $reviewStatus
): array {
    if ($reviewId <= 0) {
        return ['ok' => false, 'message' => 'Invalid review selected.', 'product_id' => 0];
    }
    if (strlen($sellerResponse) > 1000) {
        $sellerResponse = substr($sellerResponse, 0, 1000);
    }
    if (!in_array($reviewStatus, ['pending', 'approved', 'rejected'], true)) {
        $reviewStatus = 'pending';
    }

    $ownerSt = $pdo->prepare(
        'SELECT r.id, r.product_id
         FROM product_reviews r
         INNER JOIN products p ON p.id = r.product_id
         WHERE r.id = ? AND p.seller_id = ?
         LIMIT 1'
    );
    $ownerSt->execute([$reviewId, $sellerId]);
    $ownedReview = $ownerSt->fetch();
    $ownedReviewId = (int) ($ownedReview['id'] ?? 0);
    $productId = (int) ($ownedReview['product_id'] ?? 0);
    if ($ownedReviewId <= 0 || $productId <= 0) {
        return ['ok' => false, 'message' => 'You are not allowed to respond to this review.', 'product_id' => 0];
    }

    if ($sellerResponse === '') {
        $upd = $pdo->prepare(
            'UPDATE product_reviews
             SET seller_response = ?, seller_responded_at = NULL, review_status = ?, seller_reviewed_at = NOW()
             WHERE id = ?
             LIMIT 1'
        );
        $upd->execute([$sellerResponse, $reviewStatus, $reviewId]);
    } else {
        $upd = $pdo->prepare(
            'UPDATE product_reviews
             SET seller_response = ?, seller_responded_at = NOW(), review_status = ?, seller_reviewed_at = NOW()
             WHERE id = ?
             LIMIT 1'
        );
        $upd->execute([$sellerResponse, $reviewStatus, $reviewId]);
    }
    seller_sync_product_review_metrics($pdo, $productId);

    return ['ok' => true, 'message' => 'Review moderation saved successfully.', 'product_id' => $productId];
}
