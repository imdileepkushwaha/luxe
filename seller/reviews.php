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
$approvedCount = 0;
$rejectedCount = 0;
foreach ($reviews as $row) {
    $rs = (string) ($row['review_status'] ?? 'pending');
    if ($rs === 'pending') {
        $pendingCount++;
    } elseif ($rs === 'approved') {
        $approvedCount++;
    } elseif ($rs === 'rejected') {
        $rejectedCount++;
    }
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

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-txn-head seller-reviews-page-head">
          <div>
            <h1>Reviews</h1>
            <p class="seller-txn-subtitle"><strong>Approve</strong> karne par review product page par dikhega; <strong>reject</strong> par hide. Reply save karne ke baad edit ke liye <strong>Edit reply</strong> use karein.</p>
          </div>
          <div class="admin-page-head__actions seller-txn-card-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="products.php">Products</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-reviews-flash seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi seller-txn-kpi seller-reviews-kpi">
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Total reviews</div>
              <div class="seller-kpi-card__value"><?= (int) $totalReviews ?></div>
              <div class="seller-kpi-card__hint">Saare customer messages</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Average rating</div>
              <div class="seller-kpi-card__value"><?= $totalReviews > 0 ? number_format($avgRating, 2) : '—' ?><?= $totalReviews > 0 ? '<span class="seller-reviews-kpi-suffix">/5</span>' : '' ?></div>
              <div class="seller-kpi-card__hint">Is list ke hisaab se</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Pending</div>
              <div class="seller-kpi-card__value"><?= (int) $pendingCount ?></div>
              <div class="seller-kpi-card__hint">Moderation zaroori</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Published</div>
              <div class="seller-kpi-card__value"><?= (int) $approvedCount ?></div>
              <div class="seller-kpi-card__hint">Live on product · rejected: <?= (int) $rejectedCount ?></div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
        </div>

        <div class="card seller-txn-card seller-reviews-card">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Moderate &amp; reply</h2>
              <p class="card-subtitle seller-txn-card-sub">Har card par status, public reply, aur timestamps. Search se customer, product, ya review text dhundho.</p>
            </div>
            <span class="seller-txn-count-pill"><?= (int) $totalReviews ?> review<?= $totalReviews === 1 ? '' : 's' ?></span>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($reviews !== []): ?>
            <div class="seller-txn-search-bar seller-reviews-search-bar">
              <label class="seller-inventory-search-wrap seller-txn-search" for="sellerReviewsSearch">
                <span class="seller-inventory-search-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                  type="search"
                  id="sellerReviewsSearch"
                  class="seller-inventory-search-input"
                  placeholder="Search customer, product, text, status, rating…"
                  autocomplete="off"
                  aria-label="Search reviews"
                >
              </label>
            </div>
            <?php endif; ?>
            <div class="seller-reviews-list-wrap">
            <div class="seller-review-list">
              <?php foreach ($reviews as $review): ?>
                <?php
                $rid = (int) ($review['id'] ?? 0);
                $pid = (int) ($review['product_id'] ?? 0);
                $rating = max(0, min(5, (int) ($review['rating'] ?? 0)));
                $status = (string) ($review['review_status'] ?? 'pending');
                $stMod = seller_reviews_status_chip_mod($status);
                $stLabel = seller_reviews_status_label($status);
                $cust = trim((string) ($review['customer_name'] ?? ''));
                if ($cust === '') {
                    $cust = 'Customer';
                }
                $pname = trim((string) ($review['product_name'] ?? ''));
                $rtext = trim((string) ($review['review_text'] ?? ''));
                $hasResponse = trim((string) ($review['seller_response'] ?? '')) !== '';
                $isLockedByDefault = $status === 'approved' || $hasResponse;
                $createdRaw = trim((string) ($review['created_at'] ?? ''));
                $postedFmt = seller_reviews_format_dt($createdRaw);
                $revAtRaw = trim((string) ($review['seller_reviewed_at'] ?? ''));
                $respAtRaw = trim((string) ($review['seller_responded_at'] ?? ''));
                $revAtFmt = seller_reviews_format_dt($revAtRaw);
                $respAtFmt = seller_reviews_format_dt($respAtRaw);
                $searchBlob = mb_strtolower(
                    (string) $rid . ' '
                    . $cust . ' '
                    . $pname . ' '
                    . $rtext . ' '
                    . strtolower($status) . ' '
                    . strtolower($stLabel) . ' '
                    . (string) $rating . ' '
                    . $createdRaw . ' '
                    . $postedFmt
                );
                ?>
                <article class="seller-review-card" data-reviews-search="<?= h($searchBlob) ?>">
                  <div class="seller-review-card__grid">
                    <div class="seller-review-card__main">
                      <p class="seller-review-card__eyebrow">Customer review</p>
                      <header class="seller-review-card__head">
                        <div class="seller-review-card__head-text">
                          <div class="seller-review-card__customer"><?= h($cust) ?></div>
                          <div class="seller-review-card__rating-row">
                            <?php seller_reviews_render_stars($rating); ?>
                            <span class="seller-review-card__rating-num"><?= $rating ?><span class="seller-review-card__rating-max">/5</span></span>
                          </div>
                        </div>
                        <span class="seller-status-chip <?= h($stMod !== '' ? $stMod : '') ?>"><?= h($stLabel) ?></span>
                      </header>
                      <div class="seller-review-card__product">
                        <div class="seller-review-card__product-head">
                          <span class="seller-review-card__product-icon" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                          </span>
                          <span class="seller-review-card__product-label">Linked product</span>
                        </div>
                        <div class="seller-review-card__product-body">
                          <span class="seller-review-card__product-name" title="<?= h($pname !== '' ? $pname : '—') ?>"><?= h($pname !== '' ? $pname : '—') ?></span>
                          <?php if ($pid > 0): ?>
                            <div class="seller-review-card__product-actions">
                              <a class="seller-review-pill-link seller-review-pill-link--primary" href="products.php?edit=<?= $pid ?>">Edit listing</a>
                              <a class="seller-review-pill-link" href="../product.php?id=<?= $pid ?>" target="_blank" rel="noopener">View in store</a>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                      <blockquote class="seller-review-quote">
                        <div class="seller-review-quote__body"><?= $rtext !== '' ? nl2br(h($rtext)) : '<span class="seller-review-text--empty">(No written review)</span>' ?></div>
                      </blockquote>
                      <div class="seller-review-time">
                        <span class="seller-review-time__icon" aria-hidden="true">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        </span>
                        <span class="seller-review-time__text">Posted <strong><?= h($postedFmt) ?></strong></span>
                      </div>
                    </div>
                    <div class="seller-review-card__aside<?= $isLockedByDefault ? ' seller-review-card__aside--locked' : ' seller-review-card__aside--editable' ?>">
                      <form method="post" class="seller-review-form" data-locked="<?= $isLockedByDefault ? '1' : '0' ?>">
                        <input type="hidden" name="action" value="save_review_response">
                        <input type="hidden" name="review_id" value="<?= $rid ?>">
                        <div class="seller-review-form__head">
                          <div class="seller-review-form__head-titles">
                            <p class="seller-review-form__eyebrow">Your response</p>
                            <h3 class="seller-review-form__title">Reply &amp; moderation</h3>
                          </div>
                          <?php if ($isLockedByDefault): ?>
                            <span class="seller-review-form__state-badge">Locked</span>
                          <?php endif; ?>
                        </div>
                        <div class="seller-review-form__fields">
                          <div class="seller-review-form__field">
                            <label for="review_status_<?= $rid ?>">Review status</label>
                            <select id="review_status_<?= $rid ?>" class="seller-status-select seller-review-status-select" name="review_status" title="Pending = moderation; Approved = product page par; Rejected = hide"<?= $isLockedByDefault ? ' disabled' : '' ?>>
                              <option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Pending (moderation)</option>
                              <option value="approved"<?= $status === 'approved' ? ' selected' : '' ?>>Approved (live on product)</option>
                              <option value="rejected"<?= $status === 'rejected' ? ' selected' : '' ?>>Rejected (hidden)</option>
                            </select>
                            <p class="seller-review-form__hint">Approved reviews buyers ko product page par dikhte hain.</p>
                          </div>
                          <div class="seller-review-form__field">
                            <label for="seller_response_<?= $rid ?>">Public reply</label>
                            <textarea id="seller_response_<?= $rid ?>" class="seller-badge-input seller-review-reply-input" name="seller_response" rows="4" maxlength="1000" placeholder="Short, professional reply jo customer ko dikhega…"<?= $isLockedByDefault ? ' disabled' : '' ?>><?= h((string) ($review['seller_response'] ?? '')) ?></textarea>
                          </div>
                        </div>
                        <div class="seller-review-meta-times">
                          <div class="seller-review-meta-times__item">
                            <span class="seller-review-meta-times__label">Moderation</span>
                            <strong class="seller-review-meta-times__value"><?= h($revAtFmt) ?></strong>
                          </div>
                          <div class="seller-review-meta-times__item">
                            <span class="seller-review-meta-times__label">Reply sent</span>
                            <strong class="seller-review-meta-times__value"><?= h($respAtFmt) ?></strong>
                          </div>
                        </div>
                        <?php if ($isLockedByDefault): ?>
                          <div class="seller-review-lock-callout" role="status">
                            <span class="seller-review-lock-callout__icon" aria-hidden="true">
                              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                            <p class="seller-review-lock-callout__text"><strong>Pehle “Edit reply”</strong> dabayen — tabhi status aur reply change ho sakte hain. Phir <strong>Save</strong> se submit.</p>
                          </div>
                        <?php endif; ?>
                        <div class="seller-review-form__actions">
                          <?php if ($isLockedByDefault): ?>
                            <button class="admin-btn admin-btn--outline seller-review-edit-btn" type="button">Edit reply</button>
                          <?php endif; ?>
                          <button class="admin-btn admin-btn--primary seller-review-save-btn" type="submit"<?= $isLockedByDefault ? ' hidden' : '' ?>>Save changes</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
              <?php if ($reviews !== []): ?>
                <div id="sellerReviewsNoMatch" class="seller-reviews-no-match" style="display:none" role="status">
                  <div class="seller-reviews-no-match__inner">
                    <span class="seller-reviews-no-match__icon" aria-hidden="true">
                      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    </span>
                    <div>
                      <strong class="seller-reviews-no-match__title">Koi match nahi</strong>
                      <p class="seller-reviews-no-match__text">Aur keywords try karein — naam, product, ya “pending”.</p>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
              <?php if ($reviews === []): ?>
                <div class="seller-txn-empty seller-reviews-empty">
                  <p class="seller-txn-empty__title">Abhi reviews nahi</p>
                  <p class="seller-txn-empty__text">Customers deliver ke baad rate kar sakte hain. <a href="products.php">Products</a> par listing theek rakho.</p>
                </div>
              <?php endif; ?>
            </div>
            </div>
          </div>
        </div>

        <script>
          (function () {
            function wireReviewsSearch() {
              var input = document.getElementById('sellerReviewsSearch');
              if (!input) return;
              var cards = document.querySelectorAll('article.seller-review-card');
              var noMatch = document.getElementById('sellerReviewsNoMatch');
              function apply() {
                var q = (input.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                cards.forEach(function (el) {
                  var hay = (el.getAttribute('data-reviews-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) {
                    return hay.indexOf(w) !== -1;
                  });
                  el.style.display = show ? '' : 'none';
                  if (show) anyShown = true;
                });
                if (noMatch) {
                  noMatch.style.display = (words.length > 0 && !anyShown) ? '' : 'none';
                }
              }
              input.addEventListener('input', apply);
              input.addEventListener('search', apply);
            }
            wireReviewsSearch();

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
