<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Reviews';
$activeNav = 'reviews';

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

$flash = '';
$flashOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_review_response') {
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $sellerResponse = trim((string) ($_POST['seller_response'] ?? ''));
    $reviewStatus = strtolower(trim((string) ($_POST['review_status'] ?? 'pending')));
    if (!in_array($reviewStatus, ['pending', 'approved', 'rejected'], true)) {
        $reviewStatus = 'pending';
    }
    if (strlen($sellerResponse) > 1000) {
        $sellerResponse = substr($sellerResponse, 0, 1000);
    }

    if ($reviewId <= 0) {
        $flash = 'Invalid review selected.';
    } else {
        $ownerSt = $pdo->prepare(
            'SELECT r.id, r.product_id
             FROM product_reviews r
             INNER JOIN products p ON p.id = r.product_id
             WHERE r.id = ? AND p.seller_id = ?
             LIMIT 1'
        );
        $ownerSt->execute([$reviewId, (int) $seller['id']]);
        $ownedReview = $ownerSt->fetch();
        $ownedReviewId = (int) ($ownedReview['id'] ?? 0);
        $productId = (int) ($ownedReview['product_id'] ?? 0);
        if ($ownedReviewId <= 0 || $productId <= 0) {
            $flash = 'You are not allowed to respond to this review.';
        } else {
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
            $flash = 'Review moderation saved successfully.';
            $flashOk = true;
        }
    }
}

$reviewsSt = $pdo->prepare(
    'SELECT r.id, r.customer_name, r.rating, r.review_text, r.review_status, r.seller_response, r.created_at, r.seller_reviewed_at, r.seller_responded_at,
            p.id AS product_id, p.name AS product_name
     FROM product_reviews r
     INNER JOIN products p ON p.id = r.product_id
     WHERE p.seller_id = ?
     ORDER BY r.created_at DESC, r.id DESC'
);
$reviewsSt->execute([(int) $seller['id']]);
$reviews = $reviewsSt->fetchAll();

$totalReviews = count($reviews);
$avgRating = 0.0;
if ($totalReviews > 0) {
    $sum = 0;
    foreach ($reviews as $row) {
        $sum += max(0, min(5, (int) ($row['rating'] ?? 0)));
    }
    $avgRating = round($sum / $totalReviews, 2);
}

$pendingCount = 0;
foreach ($reviews as $row) {
    if ((string) ($row['review_status'] ?? 'pending') === 'pending') {
        $pendingCount++;
    }
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head">
          <h1>Customer reviews</h1>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>" style="margin-bottom:14px"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Total reviews</div>
              <div class="seller-kpi-card__value"><?= $totalReviews ?></div>
              <div class="seller-kpi-card__hint">All customer feedback</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Average rating</div>
              <div class="seller-kpi-card__value"><?= number_format($avgRating, 2) ?>/5</div>
              <div class="seller-kpi-card__hint">Based on customer ratings</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Pending approvals</div>
              <div class="seller-kpi-card__value"><?= $pendingCount ?></div>
              <div class="seller-kpi-card__hint">Approve to show on product page</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <h2 class="card-title" style="white-space:nowrap">Moderate & respond to reviews</h2>
          </div>
          <div class="card-body">
            <style>
              .seller-review-list { display:grid; gap:12px; }
              .seller-review-item { border:1px solid var(--admin-border); border-radius:12px; padding:12px; background:#fff; }
              .seller-review-item__grid { display:grid; grid-template-columns: minmax(220px, 1fr) minmax(280px, 1.2fr); gap:12px; align-items:start; }
              .seller-review-meta { font-size:.8rem; color:var(--admin-text-muted); margin-top:4px; }
              .seller-review-text { margin-top:8px; white-space:pre-wrap; line-height:1.45; }
              .seller-review-time { margin-top:8px; font-size:.74rem; color:var(--admin-text-muted); }
              .seller-review-lock-hint { margin-top:6px; font-size:.74rem; color:var(--admin-text-muted); }
              .seller-review-form label { display:block; font-size:.76rem; color:var(--admin-text-muted); margin-bottom:4px; }
              .seller-review-inline { display:grid; grid-template-columns: 220px 1fr; gap:8px; align-items:end; }
              .seller-review-reply-input { width:100%; }
              .seller-review-edit-btn {
                display:inline-flex !important;
                align-items:center;
                justify-content:center;
                min-height:36px;
                padding:0 12px;
                border:1px solid var(--admin-border);
                border-radius:8px;
                background:#fff;
                color:var(--admin-text);
                font-weight:600;
                cursor:pointer;
              }
              .seller-review-edit-btn:hover {
                border-color:var(--admin-accent);
                color:var(--admin-accent);
                background:#f8fafc;
              }
              html.admin-theme-dark .seller-review-item {
                background:linear-gradient(180deg, #121212 0%, #222222 100%);
                border-color:#2a3038;
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.03);
              }
              html.admin-theme-dark .seller-review-meta,
              html.admin-theme-dark .seller-review-time,
              html.admin-theme-dark .seller-review-lock-hint,
              html.admin-theme-dark .seller-review-form label {
                color:#9aa4b2;
              }
              html.admin-theme-dark .seller-review-text,
              html.admin-theme-dark .seller-review-item strong {
                color:#e8edf3;
              }
              html.admin-theme-dark .seller-review-item .seller-badge-input {
                background:#121212;
                border-color:rgba(243,243,243,0.08);
                color:#e8edf3;
              }
              html.admin-theme-dark .seller-review-item .seller-badge-input:focus {
                border-color:#59e3dd;
                box-shadow:0 0 0 3px rgba(89, 227, 221, 0.16);
              }
              html.admin-theme-dark .seller-review-edit-btn {
                background:#151a20;
                border-color:#3a424d;
                color:#dbe2ea;
              }
              html.admin-theme-dark .seller-review-edit-btn:hover {
                border-color:#5ce2db;
                color:#c7fffb;
                background:#0f141a;
              }
              @media (max-width: 920px) {
                .seller-review-item__grid { grid-template-columns: 1fr; }
                .seller-review-inline { grid-template-columns: 1fr; }
              }
            </style>

            <div class="seller-review-list">
              <?php foreach ($reviews as $review): ?>
                <?php
                $rating = max(0, min(5, (int) ($review['rating'] ?? 0)));
                $stars = str_repeat('*', $rating) . str_repeat('-', 5 - $rating);
                $status = (string) ($review['review_status'] ?? 'pending');
                $hasResponse = trim((string) ($review['seller_response'] ?? '')) !== '';
                $isLockedByDefault = $status === 'approved' || $hasResponse;
                ?>
                <article class="seller-review-item">
                  <div class="seller-review-item__grid">
                    <div>
                      <div style="font-weight:600"><?= h((string) ($review['customer_name'] ?? 'Customer')) ?></div>
                      <div class="seller-review-meta">Product: <?= h((string) ($review['product_name'] ?? '-')) ?></div>
                      <div class="seller-review-meta">Rating: <?= h($stars) ?> (<?= $rating ?>/5)</div>
                      <div class="seller-review-meta">Status: <strong><?= h(ucfirst((string) ($review['review_status'] ?? 'pending'))) ?></strong></div>
                      <div class="seller-review-text"><?= nl2br(h((string) ($review['review_text'] ?? ''))) ?></div>
                      <div class="seller-review-time">Posted: <?= h((string) ($review['created_at'] ?? '-')) ?></div>
                    </div>
                    <div>
                      <form method="post" class="seller-review-form" data-locked="<?= $isLockedByDefault ? '1' : '0' ?>">
                        <input type="hidden" name="action" value="save_review_response">
                        <input type="hidden" name="review_id" value="<?= (int) ($review['id'] ?? 0) ?>">
                        <div class="seller-review-inline">
                          <div>
                            <label>Review status</label>
                            <select class="seller-badge-input" name="review_status"<?= $isLockedByDefault ? ' disabled' : '' ?>>
                              <option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Pending</option>
                              <option value="approved"<?= $status === 'approved' ? ' selected' : '' ?>>Approved (show on product page)</option>
                              <option value="rejected"<?= $status === 'rejected' ? ' selected' : '' ?>>Rejected (hide from product page)</option>
                            </select>
                          </div>
                          <div>
                            <label>Reply</label>
                            <input class="seller-badge-input seller-review-reply-input" type="text" name="seller_response" maxlength="1000" placeholder="Write a reply to customer review..." value="<?= h((string) ($review['seller_response'] ?? '')) ?>"<?= $isLockedByDefault ? ' disabled' : '' ?>>
                          </div>
                        </div>
                        <div class="seller-review-time">
                          Last reviewed at: <?= h((string) ($review['seller_reviewed_at'] ?? '-')) ?><br>
                          Last response at: <?= h((string) ($review['seller_responded_at'] ?? '-')) ?>
                        </div>
                        <?php if ($isLockedByDefault): ?>
                          <div class="seller-review-lock-hint">Reply locked. Click Edit reply to modify status/response.</div>
                        <?php endif; ?>
                        <div class="seller-actions" style="margin-top:8px">
                          <?php if ($isLockedByDefault): ?>
                            <button class="admin-btn admin-btn--ghost-light seller-review-edit-btn" type="button">Edit reply</button>
                          <?php endif; ?>
                          <button class="admin-btn admin-btn--primary seller-review-save-btn" type="submit"<?= $isLockedByDefault ? ' style="display:none"' : '' ?>>Save response</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
              <?php if ($reviews === []): ?>
                <div class="seller-review-item">No customer reviews available yet.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

<script>
  (function () {
    var forms = document.querySelectorAll('.seller-review-form[data-locked="1"]');
    forms.forEach(function (form) {
      var editBtn = form.querySelector('.seller-review-edit-btn');
      var saveBtn = form.querySelector('.seller-review-save-btn');
      if (!editBtn || !saveBtn) return;

      editBtn.addEventListener('click', function () {
        var controls = form.querySelectorAll('select[name="review_status"], [name="seller_response"]');
        controls.forEach(function (control) {
          control.disabled = false;
        });
        saveBtn.style.display = '';
        editBtn.style.display = 'none';
        var replyInput = form.querySelector('[name="seller_response"]');
        if (replyInput) replyInput.focus();
      });
    });
  })();
</script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
