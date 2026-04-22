<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_review_helpers.php';

$pdo = db();
$seller = seller_require_login($pdo);
$sellerId = (int) $seller['id'];

$reviewId = (int) ($_GET['id'] ?? 0);

$pageTitle = 'Moderate & reply';
$activeNav = 'reviews';

$flash = '';
$flashOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_review_response') {
    $rid = (int) ($_POST['review_id'] ?? 0);
    $sellerResponse = trim((string) ($_POST['seller_response'] ?? ''));
    $reviewStatus = strtolower(trim((string) ($_POST['review_status'] ?? 'pending')));
    $result = seller_apply_review_moderation($pdo, $sellerId, $rid, $sellerResponse, $reviewStatus);
    if ($result['ok']) {
        header('Location: review-moderate.php?id=' . $rid . '&saved=1');
        exit;
    }
    $flash = $result['message'];
    $flashOk = false;
    if ($rid > 0) {
        $reviewId = $rid;
    }
}

if (isset($_GET['saved']) && (string) $_GET['saved'] === '1') {
    $flash = 'Review moderation saved successfully.';
    $flashOk = true;
}

$detailSt = $pdo->prepare(
    'SELECT r.id, r.customer_name, r.rating, r.review_text, r.review_status, r.seller_response, r.created_at, r.seller_reviewed_at, r.seller_responded_at,
            p.id AS product_id, p.name AS product_name
     FROM product_reviews r
     INNER JOIN products p ON p.id = r.product_id
     WHERE r.id = ? AND p.seller_id = ?
     LIMIT 1'
);
$detailSt->execute([$reviewId, $sellerId]);
$review = $detailSt->fetch(PDO::FETCH_ASSOC);

$formAction = 'review-moderate.php?id=' . $reviewId;

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-txn-head seller-reviews-page-head">
          <div>
            <nav class="seller-review-moderate-breadcrumb" aria-label="Breadcrumb">
              <a href="reviews.php">Reviews</a>
              <span class="seller-review-moderate-breadcrumb__sep" aria-hidden="true">/</span>
              <span class="seller-review-moderate-breadcrumb__here">Moderate &amp; reply</span>
            </nav>
            <h1>Moderate &amp; reply</h1>
            <p class="seller-txn-subtitle"><?= $reviewId > 0 ? 'Review #' . (int) $reviewId . (is_array($review) && ($review['product_name'] ?? '') !== '' ? ' · ' . h((string) $review['product_name']) : '') : 'Review select karein' ?></p>
          </div>
          <div class="admin-page-head__actions seller-txn-card-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="reviews.php">← All reviews</a>
            <?php if (is_array($review) && (int) ($review['product_id'] ?? 0) > 0): ?>
              <a class="admin-btn admin-btn--ghost-light" href="products.php?edit=<?= (int) $review['product_id'] ?>">Edit product</a>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-reviews-flash seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <?php if (!is_array($review) || (int) ($review['id'] ?? 0) <= 0): ?>
          <div class="card seller-txn-card">
            <div class="card-body">
              <p class="seller-txn-empty__title">Review nahi mila</p>
              <p class="seller-txn-empty__text">Yeh review aapke products se linked nahi hai ya delete ho chuka hai. <a href="reviews.php">Reviews list</a> par wapas jayein.</p>
            </div>
          </div>
        <?php else: ?>
          <div class="seller-review-list seller-review-list--single">
            <?php
            require __DIR__ . '/partials/review-moderate-card.php';
            ?>
          </div>
        <?php endif; ?>

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
                saveBtn.hidden = false;
                editBtn.hidden = true;
                form.classList.add('seller-review-form--editing');
                var aside = form.closest('.seller-review-card__aside');
                if (aside) {
                  aside.classList.remove('seller-review-card__aside--locked');
                  aside.classList.add('seller-review-card__aside--editable');
                }
                var callout = form.querySelector('.seller-review-lock-callout');
                if (callout) callout.setAttribute('hidden', '');
                var badge = form.querySelector('.seller-review-form__state-badge');
                if (badge) {
                  badge.textContent = 'Editing';
                  badge.classList.add('seller-review-form__state-badge--editing');
                }
                var replyInput = form.querySelector('[name="seller_response"]');
                if (replyInput) replyInput.focus();
              });
            });
          })();
        </script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
