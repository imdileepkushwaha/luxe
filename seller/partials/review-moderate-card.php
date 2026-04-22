<?php

declare(strict_types=1);

/** @var array $review */
/** @var string $formAction */

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
?>
                <article class="seller-review-card">
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
                      <form method="post" class="seller-review-form" action="<?= h($formAction) ?>" data-locked="<?= $isLockedByDefault ? '1' : '0' ?>">
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
