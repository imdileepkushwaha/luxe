<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_review_helpers.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Reviews';
$activeNav = 'reviews';

require_once __DIR__ . '/../admin/_pagination.php';

$revStatsSt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(AVG(r.rating), 0) AS avg_rating,
        SUM(CASE WHEN r.review_status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
        SUM(CASE WHEN r.review_status = 'approved' THEN 1 ELSE 0 END) AS approved_cnt,
        SUM(CASE WHEN r.review_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_cnt
     FROM product_reviews r
     INNER JOIN products p ON p.id = r.product_id
     WHERE p.seller_id = ?"
);
$revStatsSt->execute([(int) $seller['id']]);
$revStats = $revStatsSt->fetch() ?: [];
$totalReviews = (int) ($revStats['total'] ?? 0);
$avgRating = $totalReviews > 0 ? round((float) ($revStats['avg_rating'] ?? 0), 2) : 0.0;
$pendingCount = (int) ($revStats['pending_cnt'] ?? 0);
$approvedCount = (int) ($revStats['approved_cnt'] ?? 0);
$rejectedCount = (int) ($revStats['rejected_cnt'] ?? 0);

['page' => $reviewsListPage, 'perPage' => $reviewsPerPage] = admin_pagination_read(25);
$reviewsPageMeta = admin_pagination_resolve($totalReviews, $reviewsListPage, $reviewsPerPage);
$reviewsPage = $reviewsPageMeta['page'];
$reviewsOffset = $reviewsPageMeta['offset'];
$reviewsPerPage = $reviewsPageMeta['perPage'];
$reviewsTotalPages = $reviewsPageMeta['totalPages'];

$reviewsSt = $pdo->prepare(
    'SELECT r.id, r.customer_name, r.rating, r.review_text, r.review_status, r.created_at,
            p.id AS product_id, p.name AS product_name, p.image_path AS product_image_path, p.emoji AS product_emoji
     FROM product_reviews r
     INNER JOIN products p ON p.id = r.product_id
     WHERE p.seller_id = ?
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT ' . (int) $reviewsPerPage . ' OFFSET ' . (int) $reviewsOffset
);
$reviewsSt->execute([(int) $seller['id']]);
$reviews = $reviewsSt->fetchAll();

function seller_reviews_preview_text(string $text, int $max = 96): string
{
    $t = trim($text);
    if ($t === '') {
        return '—';
    }
    if (function_exists('mb_strlen') && mb_strlen($t) > $max) {
        return rtrim(mb_substr($t, 0, $max - 1)) . '…';
    }
    if (strlen($t) > $max) {
        return rtrim(substr($t, 0, $max - 3)) . '…';
    }

    return $t;
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-txn-head seller-reviews-page-head">
          <div>
            <h1>Reviews</h1>
            <p class="seller-txn-subtitle">Neeche saare customer reviews ki <strong>list</strong> hai — product, rating, status. <strong>Details</strong> par click karke <strong>Moderate &amp; reply</strong> page par jao (approve / reject / public reply).</p>
          </div>
          <div class="admin-page-head__actions seller-txn-card-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="products.php">Products</a>
          </div>
        </div>

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
              <div class="seller-kpi-card__hint">Saare reviews ka average</div>
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
              <h2 class="card-title">Review list</h2>
              <p class="card-subtitle seller-txn-card-sub">Har row me product + customer summary. <strong>Details</strong> se moderation page khulegi. Is page par search sirf <strong>current page</strong> ki rows filter karta hai.</p>
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
                  aria-label="Search reviews on this page"
                >
              </label>
            </div>
            <?php endif; ?>
            <div class="seller-reviews-list-wrap">
            <?php if ($reviews !== []): ?>
            <div class="admin-table-wrap seller-txn-table-wrap seller-reviews-table-wrap">
              <table class="admin-table seller-txn-table seller-reviews-list-table">
                <thead>
                  <tr>
                    <th class="seller-reviews-th-product">Product</th>
                    <th>Customer</th>
                    <th class="seller-reviews-th-rating">Rating</th>
                    <th>Status</th>
                    <th>Posted</th>
                    <th class="seller-reviews-th-preview">Review</th>
                    <th class="seller-txn-th-actions"></th>
                  </tr>
                </thead>
                <tbody>
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
                    $imgPath = trim((string) ($review['product_image_path'] ?? ''));
                    $emoji = trim((string) ($review['product_emoji'] ?? '')) ?: '📦';
                    $createdRaw = trim((string) ($review['created_at'] ?? ''));
                    $postedFmt = seller_reviews_format_dt($createdRaw);
                    $previewPlain = seller_reviews_preview_text($rtext, 100);
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
                    <tr class="seller-reviews-table-row" data-reviews-search="<?= h($searchBlob) ?>">
                      <td>
                        <div class="seller-reviews-list-product">
                          <?php if ($imgPath !== ''): ?>
                            <img class="seller-reviews-list-product__thumb" src="../<?= h($imgPath) ?>" alt="" width="44" height="44" loading="lazy">
                          <?php else: ?>
                            <span class="seller-reviews-list-product__emoji" aria-hidden="true"><?= h($emoji) ?></span>
                          <?php endif; ?>
                          <div class="seller-reviews-list-product__text">
                            <span class="seller-reviews-list-product__name" title="<?= h($pname !== '' ? $pname : '—') ?>"><?= h($pname !== '' ? $pname : '—') ?></span>
                            <?php if ($pid > 0): ?>
                              <span class="seller-reviews-list-product__id">#<?= $pid ?></span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="seller-reviews-list-customer"><?= h($cust) ?></span>
                      </td>
                      <td class="seller-reviews-td-rating">
                        <span class="seller-reviews-list-rating-num"><?= $rating ?><span class="seller-reviews-list-rating-max">/5</span></span>
                      </td>
                      <td>
                        <span class="seller-status-chip <?= h($stMod !== '' ? $stMod : '') ?>"><?= h($stLabel) ?></span>
                      </td>
                      <td class="seller-txn-td-muted seller-reviews-td-date"><?= h($postedFmt) ?></td>
                      <td class="seller-reviews-td-preview"><?= $previewPlain !== '—' ? h($previewPlain) : '<span class="seller-review-text--empty">(No text)</span>' ?></td>
                      <td class="seller-txn-td-actions">
                        <?php if ($rid > 0): ?>
                          <a class="seller-edit-btn" href="review-moderate.php?id=<?= $rid ?>" aria-label="Review details" title="Review details">
                            <svg class="seller-details-icon" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M9 4.46A9.8 9.8 0 0 1 12 4c4.182 0 7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296A14.5 14.5 0 0 1 5 6.821"></path><path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"></path></g></svg>
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
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
            <?php else: ?>
                <div class="seller-txn-empty seller-reviews-empty seller-reviews-list-empty">
                  <p class="seller-txn-empty__title">Abhi reviews nahi</p>
                  <p class="seller-txn-empty__text">Customers deliver ke baad rate kar sakte hain. <a href="products.php">Products</a> par listing theek rakho.</p>
                </div>
            <?php endif; ?>
            <?php
            $paginationScript = 'reviews.php';
            $paginationTotal = $totalReviews;
            $paginationPage = $reviewsPage;
            $paginationPerPage = $reviewsPerPage;
            $paginationTotalPages = $reviewsTotalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            </div>
          </div>
        </div>

        <script>
          (function () {
            function wireReviewsSearch() {
              var input = document.getElementById('sellerReviewsSearch');
              if (!input) return;
              var rows = document.querySelectorAll('tr.seller-reviews-table-row');
              var noMatch = document.getElementById('sellerReviewsNoMatch');
              var tableWrap = document.querySelector('.seller-reviews-table-wrap');
              function apply() {
                var q = (input.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                rows.forEach(function (el) {
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
                if (tableWrap) {
                  tableWrap.style.display = (words.length > 0 && !anyShown) ? 'none' : '';
                }
              }
              input.addEventListener('input', apply);
              input.addEventListener('search', apply);
            }
            wireReviewsSearch();
          })();
        </script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
