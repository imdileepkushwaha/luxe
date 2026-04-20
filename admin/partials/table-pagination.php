<?php
declare(strict_types=1);

require_once __DIR__ . '/../_pagination.php';

/** @var string $paginationScript basename e.g. orders.php */
/** @var int $paginationTotal */
/** @var int $paginationPage */
/** @var int $paginationPerPage */
/** @var int $paginationTotalPages */
/** @var string $paginationPageKey */
/** @var string $paginationPerPageKey */

if (!isset($paginationScript, $paginationTotal, $paginationPage, $paginationPerPage, $paginationTotalPages)) {
    throw new RuntimeException('pagination: set paginationScript, paginationTotal, paginationPage, paginationPerPage, paginationTotalPages');
}

$paginationPageKey = $paginationPageKey ?? 'page';
$paginationPerPageKey = $paginationPerPageKey ?? 'per_page';

$range = admin_pagination_visible_range($paginationTotal, $paginationPage, $paginationPerPage);
$pFrom = $range['from'];
$pTo = $range['to'];
$prevUrl = admin_pagination_href($paginationScript, max(1, $paginationPage - 1), $paginationPerPage, [], $paginationPageKey, $paginationPerPageKey);
$nextUrl = admin_pagination_href($paginationScript, min($paginationTotalPages, $paginationPage + 1), $paginationPerPage, [], $paginationPageKey, $paginationPerPageKey);
$showNav = $paginationTotal > 0 && $paginationTotalPages > 1;
?>
        <nav class="admin-pagination" aria-label="Table pagination">
          <div class="admin-pagination__info">
            <?php if ($paginationTotal > 0): ?>
              <span class="admin-pagination__range">Showing <strong><?= (int) $pFrom ?>–<?= (int) $pTo ?></strong> of <strong><?= (int) $paginationTotal ?></strong></span>
            <?php else: ?>
              <span class="admin-pagination__range">No rows</span>
            <?php endif; ?>
          </div>
          <?php if ($paginationTotal > 0): ?>
          <div class="admin-pagination__controls">
            <form class="admin-pagination__per-page" method="get" action="<?= h($paginationScript) ?>">
              <?php foreach ($_GET as $k => $v): ?>
                <?php if ($k === $paginationPageKey || $k === $paginationPerPageKey) {
                    continue;
                } ?>
                <?php if (is_array($v)) {
                    continue;
                } ?>
                <input type="hidden" name="<?= h((string) $k) ?>" value="<?= h((string) $v) ?>">
              <?php endforeach; ?>
              <label class="admin-pagination__per-label">
                <span class="visually-hidden">Rows per page</span>
                <span aria-hidden="true">Per page</span>
                <select class="admin-pagination__select" name="<?= h($paginationPerPageKey) ?>" onchange="var pe=this.form.elements['<?= h($paginationPageKey) ?>']; if(pe) pe.value=1; this.form.submit();">
                  <?php foreach ([10, 25, 50, 100] as $opt): ?>
                    <option value="<?= (int) $opt ?>"<?= $paginationPerPage === $opt ? ' selected' : '' ?>><?= (int) $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <input type="hidden" name="<?= h($paginationPageKey) ?>" value="1">
            </form>
            <?php if ($showNav): ?>
            <div class="admin-pagination__arrows">
              <?php if ($paginationPage > 1): ?>
                <a class="admin-pagination__btn" href="<?= h($prevUrl) ?>">Previous</a>
              <?php else: ?>
                <span class="admin-pagination__btn admin-pagination__btn--disabled">Previous</span>
              <?php endif; ?>
              <span class="admin-pagination__status">Page <?= (int) $paginationPage ?> of <?= (int) $paginationTotalPages ?></span>
              <?php if ($paginationPage < $paginationTotalPages): ?>
                <a class="admin-pagination__btn" href="<?= h($nextUrl) ?>">Next</a>
              <?php else: ?>
                <span class="admin-pagination__btn admin-pagination__btn--disabled">Next</span>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <span class="admin-pagination__status admin-pagination__status--solo">Page <?= (int) $paginationPage ?> of <?= (int) $paginationTotalPages ?></span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </nav>
