<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/seller_variant_inventory.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Inventory';
$activeNav = 'inventory';

$flash = '';
$flashOk = false;

$productsSt = $pdo->prepare(
    'SELECT id, name, slug, category, price, stock_qty, badge, active, image_path, size_options, color_options
     FROM products
     WHERE seller_id = ?
     ORDER BY id DESC'
);
$productsSt->execute([(int) $seller['id']]);
$products = $productsSt->fetchAll();

/** @var array<int,array<string,mixed>> $productIndex */
$productIndex = [];
/** @var array<int,list<array{size:string,color:string,key:string,label:string}>> $variantMatrixByProduct */
$variantMatrixByProduct = [];
/** @var array<int,array<string,array{size:string,color:string}>> $allowedVariantMap */
$allowedVariantMap = [];
foreach ($products as $p) {
    $pid = (int) ($p['id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $productIndex[$pid] = $p;
    $sizes = seller_parse_option_csv((string) ($p['size_options'] ?? ''));
    $colors = seller_parse_option_csv((string) ($p['color_options'] ?? ''));
    $matrix = seller_variant_combinations($sizes, $colors);
    $variantMatrixByProduct[$pid] = $matrix;
    $allowedVariantMap[$pid] = [];
    foreach ($matrix as $row) {
        $allowedVariantMap[$pid][$row['key']] = ['size' => $row['size'], 'color' => $row['color']];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_inventory') {
    $rows = $_POST['rows'] ?? [];
    $variants = $_POST['variants'] ?? [];
    if (!is_array($rows)) {
        $flash = 'Invalid request payload.';
    } else {
        $updatedProducts = 0;
        $updatedVariants = 0;

        $updFull = $pdo->prepare(
            'UPDATE products
             SET stock_qty = ?, active = ?, badge = ?
             WHERE id = ? AND seller_id = ?
             LIMIT 1'
        );
        $updMeta = $pdo->prepare(
            'UPDATE products
             SET active = ?, badge = ?
             WHERE id = ? AND seller_id = ?
             LIMIT 1'
        );
        foreach ($rows as $productId => $row) {
            $pid = (int) $productId;
            if ($pid <= 0 || !isset($productIndex[$pid]) || !is_array($row)) {
                continue;
            }
            $hasVariantMatrix = ($variantMatrixByProduct[$pid] ?? []) !== [];
            $active = (int) ($row['active'] ?? 0) === 1 ? 1 : 0;
            $badge = trim((string) ($row['badge'] ?? ''));
            if (strlen($badge) > 64) {
                $badge = substr($badge, 0, 64);
            }
            $stockQty = max(0, (int) ($row['stock_qty'] ?? 0));
            $lineStockForBadge = $stockQty;
            if ($hasVariantMatrix && is_array($variants[$pid] ?? null)) {
                $lineStockForBadge = 0;
                foreach ($variants[$pid] as $vr) {
                    if (is_array($vr)) {
                        $lineStockForBadge += max(0, (int) ($vr['stock_qty'] ?? 0));
                    }
                }
            }
            if ($active === 0 && $lineStockForBadge === 0 && $badge === '') {
                $badge = 'Discontinued';
            }
            if ($hasVariantMatrix) {
                $updMeta->execute([$active, $badge, $pid, (int) $seller['id']]);
                if ($updMeta->rowCount() > 0) {
                    $updatedProducts++;
                }
            } else {
                $updFull->execute([$stockQty, $active, $badge, $pid, (int) $seller['id']]);
                if ($updFull->rowCount() > 0) {
                    $updatedProducts++;
                }
            }
        }

        if (is_array($variants) && $variants !== []) {
            $upsert = $pdo->prepare(
                'INSERT INTO product_variant_inventory (product_id, size_label, color_label, stock_qty, active)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE stock_qty = VALUES(stock_qty), active = VALUES(active)'
            );
            foreach ($variants as $productId => $variantRows) {
                $pid = (int) $productId;
                if ($pid <= 0 || !isset($allowedVariantMap[$pid]) || !is_array($variantRows)) {
                    continue;
                }
                foreach ($variantRows as $vRow) {
                    if (!is_array($vRow)) {
                        continue;
                    }
                    $size = trim((string) ($vRow['size'] ?? ''));
                    $color = trim((string) ($vRow['color'] ?? ''));
                    $key = strtolower($size) . '|' . strtolower($color);
                    if (!isset($allowedVariantMap[$pid][$key])) {
                        continue;
                    }
                    $stockQty = max(0, (int) ($vRow['stock_qty'] ?? 0));
                    $active = (int) ($vRow['active'] ?? 0) === 1 ? 1 : 0;
                    $canonical = $allowedVariantMap[$pid][$key];
                    $upsert->execute([$pid, $canonical['size'], $canonical['color'], $stockQty, $active]);
                    $updatedVariants++;
                }
            }
        }

        $sumVariantStock = $pdo->prepare(
            'SELECT COALESCE(SUM(stock_qty), 0) FROM product_variant_inventory WHERE product_id = ?'
        );
        $syncProductStock = $pdo->prepare(
            'UPDATE products SET stock_qty = ? WHERE id = ? AND seller_id = ? LIMIT 1'
        );
        foreach ($productIndex as $pid => $_p) {
            if (($variantMatrixByProduct[$pid] ?? []) === []) {
                continue;
            }
            $sumVariantStock->execute([$pid]);
            $total = (int) $sumVariantStock->fetchColumn();
            $syncProductStock->execute([$total, $pid, (int) $seller['id']]);
            if ($syncProductStock->rowCount() > 0) {
                $updatedProducts++;
            }
        }

        $flashOk = true;
        if ($updatedProducts === 0 && $updatedVariants === 0) {
            $flash = 'No inventory changes detected.';
        } else {
            $flash = 'Inventory saved. Products updated: ' . $updatedProducts . ', variants updated: ' . $updatedVariants . '.';
        }

        $productsSt->execute([(int) $seller['id']]);
        $products = $productsSt->fetchAll();
        $productIndex = [];
        $variantMatrixByProduct = [];
        $allowedVariantMap = [];
        foreach ($products as $p) {
            $pid = (int) ($p['id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $productIndex[$pid] = $p;
            $sizes = seller_parse_option_csv((string) ($p['size_options'] ?? ''));
            $colors = seller_parse_option_csv((string) ($p['color_options'] ?? ''));
            $matrix = seller_variant_combinations($sizes, $colors);
            $variantMatrixByProduct[$pid] = $matrix;
            $allowedVariantMap[$pid] = [];
            foreach ($matrix as $m) {
                $allowedVariantMap[$pid][$m['key']] = ['size' => $m['size'], 'color' => $m['color']];
            }
        }
    }
}

$variantRows = [];
if ($productIndex !== []) {
    $variantSt = $pdo->prepare(
        'SELECT pvi.product_id, pvi.size_label, pvi.color_label, pvi.stock_qty, pvi.active
         FROM product_variant_inventory pvi
         INNER JOIN products p ON p.id = pvi.product_id
         WHERE p.seller_id = ?
         ORDER BY pvi.product_id ASC, pvi.size_label ASC, pvi.color_label ASC'
    );
    $variantSt->execute([(int) $seller['id']]);
    $variantRows = $variantSt->fetchAll();
}

/** @var array<int,array<string,array{stock_qty:int,active:int}>> $variantValueMap */
$variantValueMap = [];
foreach ($variantRows as $v) {
    $pid = (int) ($v['product_id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $key = strtolower(trim((string) ($v['size_label'] ?? ''))) . '|' . strtolower(trim((string) ($v['color_label'] ?? '')));
    if (!isset($variantValueMap[$pid])) {
        $variantValueMap[$pid] = [];
    }
    $variantValueMap[$pid][$key] = [
        'stock_qty' => max(0, (int) ($v['stock_qty'] ?? 0)),
        'active' => (int) ($v['active'] ?? 0) === 1 ? 1 : 0,
    ];
}

$totalProducts = count($products);
$activeProducts = 0;
$lowStockProducts = 0;
$outOfStockProducts = 0;
$totalVariantRows = 0;
foreach ($products as $p) {
    $pid = (int) ($p['id'] ?? 0);
    $isActive = (int) ($p['active'] ?? 0) === 1;
    $stock = (int) ($p['stock_qty'] ?? 0);
    if ($isActive) {
        $activeProducts++;
    }
    if ($isActive && $stock > 0 && $stock <= 5) {
        $lowStockProducts++;
    }
    if ($stock <= 0) {
        $outOfStockProducts++;
    }
    $totalVariantRows += count($variantMatrixByProduct[$pid] ?? []);
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-txn-head seller-inventory-page-head">
          <div>
            <h1>Inventory</h1>
            <p class="seller-txn-subtitle">Stock, active/inactive, aur badges yahan se update karo. Size/color wale products ka detail stock <strong>View variants</strong> drawer se.</p>
          </div>
          <div class="admin-page-head__actions seller-txn-card-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="products.php">Products</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-inventory-flash seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi seller-txn-kpi seller-inventory-kpi">
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Catalogue</div>
              <div class="seller-kpi-card__value"><?= (int) $totalProducts ?></div>
              <div class="seller-kpi-card__hint">Total products</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Active</div>
              <div class="seller-kpi-card__value"><?= (int) $activeProducts ?></div>
              <div class="seller-kpi-card__hint">Buyers ko visible</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Low stock</div>
              <div class="seller-kpi-card__value"><?= (int) $lowStockProducts ?></div>
              <div class="seller-kpi-card__hint">Active · qty 1–5</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Out of stock</div>
              <div class="seller-kpi-card__value"><?= (int) $outOfStockProducts ?></div>
              <div class="seller-kpi-card__hint">Total qty ≤ 0</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Variant rows</div>
              <div class="seller-kpi-card__value"><?= (int) $totalVariantRows ?></div>
              <div class="seller-kpi-card__hint">Size × color lines</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>
            </div>
          </div>
        </div>

        <div class="card seller-txn-card seller-inventory-card">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Stock &amp; visibility</h2>
              <p class="card-subtitle seller-txn-card-sub">Table me inline edit; neeche har product ka variant strip. Save sab rows ek saath bhejta hai.</p>
            </div>
            <div class="seller-txn-card-head-actions seller-inventory-card-actions">
              <span class="seller-txn-count-pill"><?= (int) $totalProducts ?> product<?= $totalProducts === 1 ? '' : 's' ?></span>
              <button type="submit" form="inventoryForm" class="admin-btn admin-btn--primary">Save inventory</button>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($products !== []): ?>
            <div class="seller-txn-search-bar seller-inventory-search-bar">
              <label class="seller-inventory-search-wrap seller-inventory-search-wrap--bar seller-txn-search" for="inventoryTableSearch">
                <span class="seller-inventory-search-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input
                  type="search"
                  id="inventoryTableSearch"
                  class="seller-inventory-search-input"
                  placeholder="Search name, slug, category, ID, badge…"
                  autocomplete="off"
                  aria-label="Search inventory"
                >
              </label>
            </div>
            <?php endif; ?>
            <form method="post" id="inventoryForm">
              <input type="hidden" name="action" value="save_inventory">
              <div class="admin-table-wrap seller-inventory-table-wrap">
                <table class="admin-table seller-inventory-table">
                  <thead>
                    <tr>
                      <th class="seller-inventory-th-product">Product</th>
                      <th class="seller-inventory-th-cat">Category</th>
                      <th class="seller-inventory-th-price">Price</th>
                      <th class="seller-inventory-th-stock">Stock</th>
                      <th class="seller-inventory-th-status">Status</th>
                      <th class="seller-inventory-th-badge">Badge</th>
                      <th class="seller-inventory-th-preview"></th>
                    </tr>
                  </thead>
                  <?php if ($products === []): ?>
                  <tbody>
                    <tr>
                      <td colspan="7">
                        <div class="seller-txn-empty seller-inventory-empty">
                          <p class="seller-txn-empty__title">Koi product nahi</p>
                          <p class="seller-txn-empty__text">Pehle <a href="products.php">Products</a> se listing add karein — phir yahan stock set karein.</p>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                  <?php else: ?>
                    <?php foreach ($products as $p): ?>
                      <?php
                      $pid = (int) ($p['id'] ?? 0);
                      $stock = (int) ($p['stock_qty'] ?? 0);
                      $active = (int) ($p['active'] ?? 0) === 1 ? 1 : 0;
                      $matrix = $variantMatrixByProduct[$pid] ?? [];
                      $mainStockDisplay = $stock;
                      if ($matrix !== []) {
                          $mainStockDisplay = 0;
                          foreach ($matrix as $m) {
                              $sv = $variantValueMap[$pid][$m['key']] ?? null;
                              $mainStockDisplay += (int) ($sv['stock_qty'] ?? 0);
                          }
                          if (($variantValueMap[$pid] ?? []) === []) {
                              $mainStockDisplay = $stock;
                          }
                      }
                      $inventorySearchBlob = mb_strtolower(
                          trim((string) ($p['name'] ?? '')) . ' '
                          . trim((string) ($p['slug'] ?? '')) . ' '
                          . trim((string) ($p['category'] ?? '')) . ' '
                          . (string) $pid . ' '
                          . trim((string) ($p['badge'] ?? ''))
                      );
                      $isLow = $active === 1 && $mainStockDisplay > 0 && $mainStockDisplay <= 5;
                      $isOut = $mainStockDisplay <= 0;
                      ?>
                  <tbody class="inventory-product-group" data-inventory-search="<?= h($inventorySearchBlob) ?>">
                      <tr class="seller-inventory-main-row">
                        <td class="seller-inventory-td-product">
                          <div class="seller-inventory-product-cell">
                            <?php if ((string) ($p['image_path'] ?? '') !== ''): ?>
                              <img class="seller-product-thumb seller-inventory-thumb" src="../<?= h((string) $p['image_path']) ?>" alt="<?= h((string) $p['name']) ?>">
                            <?php else: ?>
                              <span class="seller-product-thumb seller-product-thumb--placeholder seller-inventory-thumb">No img</span>
                            <?php endif; ?>
                            <div class="seller-inventory-product-meta">
                              <div class="seller-inventory-product-name-row">
                                <span class="seller-inventory-product-name"><?= h((string) $p['name']) ?></span>
                                <span class="seller-product-list-sku seller-inventory-id-pill">#<?= $pid ?></span>
                              </div>
                              <div class="seller-inventory-slug"><?= h((string) $p['slug']) ?></div>
                              <div class="seller-inventory-stock-badges">
                                <?php if ($matrix !== []): ?>
                                  <span class="seller-inventory-chip seller-inventory-chip--variants">Variants</span>
                                <?php endif; ?>
                                <?php if ($isLow): ?>
                                  <span class="seller-inventory-chip seller-inventory-chip--low">Low stock</span>
                                <?php endif; ?>
                                <?php if ($isOut): ?>
                                  <span class="seller-inventory-chip seller-inventory-chip--out">Out of stock</span>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                        </td>
                        <td class="seller-inventory-td-muted"><?= h((string) $p['category']) ?></td>
                        <td class="seller-inventory-td-price">₹<?= number_format((int) ($p['price'] ?? 0), 0, '.', ',') ?></td>
                        <td class="seller-inventory-td-stock">
                          <?php if ($matrix !== []): ?>
                            <input
                              class="seller-stock-input seller-stock-input--readonly seller-inventory-stock-input"
                              type="number"
                              min="0"
                              name="rows[<?= $pid ?>][stock_qty]"
                              value="<?= (int) $mainStockDisplay ?>"
                              readonly
                              title="Variant products: total yahan. Edit drawer se badlein."
                            >
                            <p class="seller-inventory-stock-hint">Sum of variants — <strong>View variants</strong></p>
                          <?php else: ?>
                            <input
                              class="seller-stock-input seller-inventory-stock-input"
                              type="number"
                              min="0"
                              name="rows[<?= $pid ?>][stock_qty]"
                              value="<?= $stock ?>"
                            >
                          <?php endif; ?>
                        </td>
                        <td>
                          <select class="seller-status-select seller-inventory-status-select" name="rows[<?= $pid ?>][active]">
                            <option value="1"<?= $active === 1 ? ' selected' : '' ?>>Active</option>
                            <option value="0"<?= $active === 0 ? ' selected' : '' ?>>Inactive</option>
                          </select>
                        </td>
                        <td>
                          <input
                            class="seller-badge-input seller-inventory-badge-input"
                            type="text"
                            maxlength="64"
                            name="rows[<?= $pid ?>][badge]"
                            value="<?= h((string) ($p['badge'] ?? '')) ?>"
                            placeholder="Sale, New…"
                          >
                        </td>
                        <td class="seller-inventory-td-preview">
                          <a class="seller-edit-btn seller-inventory-preview-link" href="../product.php?id=<?= $pid ?>" target="_blank" rel="noopener">Store</a>
                        </td>
                      </tr>
                      <tr class="seller-variant-summary-row">
                        <td colspan="7" class="seller-variant-cell">
                          <?php if ($matrix === []): ?>
                            <div class="seller-inventory-variant-empty">
                              <span class="seller-inventory-variant-empty__icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                              </span>
                              <p class="seller-inventory-variant-empty__text">Size / color options nahi hain. <a href="products.php">Products</a> se edit karke combinations add karein.</p>
                            </div>
                          <?php else: ?>
                            <div class="seller-variant-summary">
                              <div class="seller-variant-summary__text">
                                <span class="seller-variant-summary__label">Variant stock</span>
                                <span class="seller-variant-hint"><?= count($matrix) ?> combination<?= count($matrix) === 1 ? '' : 's' ?> · size / color</span>
                              </div>
                              <button
                                type="button"
                                class="admin-btn admin-btn--outline seller-variant-open-btn seller-variant-open-btn--pill"
                                data-variant-drawer="variantDrawer-<?= $pid ?>"
                                aria-haspopup="dialog"
                                aria-controls="variantDrawer-<?= $pid ?>"
                              >View variants</button>
                            </div>
                          <?php endif; ?>
                        </td>
                      </tr>
                  </tbody>
                    <?php endforeach; ?>
                  <tbody id="inventoryNoMatchRow" class="seller-inventory-no-match-tbody" style="display:none">
                    <tr>
                      <td colspan="7">
                        <div class="seller-txn-no-match-inner seller-inventory-no-match-inner">
                          <span class="seller-txn-no-match-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                          </span>
                          <div>
                            <strong>No matching products</strong>
                            <p>Dusra keyword try karein — naam, slug, category, ya ID.</p>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                  <?php endif; ?>
                </table>
              </div>

              <?php if ($totalVariantRows > 0): ?>
              <div class="seller-drawer-backdrop" id="variantDrawerBackdrop" aria-hidden="true"></div>
              <?php foreach ($products as $pDrawer): ?>
                <?php
                $dPid = (int) ($pDrawer['id'] ?? 0);
                $dMatrix = $variantMatrixByProduct[$dPid] ?? [];
                if ($dMatrix === []) {
                    continue;
                }
                ?>
                <aside
                  class="seller-drawer seller-variant-drawer"
                  id="variantDrawer-<?= $dPid ?>"
                  role="dialog"
                  aria-modal="true"
                  aria-labelledby="variantDrawerTitle-<?= $dPid ?>"
                  aria-hidden="true"
                >
                  <div class="seller-drawer__head">
                    <h2 class="seller-drawer__title" id="variantDrawerTitle-<?= $dPid ?>">Variant stock — <?= h((string) $pDrawer['name']) ?></h2>
                    <button type="button" class="seller-drawer__close" data-variant-drawer-close aria-label="Close variant stock panel">✕</button>
                  </div>
                  <div class="seller-drawer__body">
                    <div class="seller-variant-block">
                      <div class="seller-variant-grid">
                        <?php foreach ($dMatrix as $variantIdx => $m): ?>
                          <?php
                          $vKey = $m['key'];
                          $saved = $variantValueMap[$dPid][$vKey] ?? ['stock_qty' => 0, 'active' => 1];
                          ?>
                          <div class="seller-variant-row">
                            <div class="seller-variant-row__label"><?= h($m['label']) ?></div>
                            <input type="hidden" name="variants[<?= $dPid ?>][<?= (int) $variantIdx ?>][size]" value="<?= h($m['size']) ?>">
                            <input type="hidden" name="variants[<?= $dPid ?>][<?= (int) $variantIdx ?>][color]" value="<?= h($m['color']) ?>">
                            <div class="seller-variant-row__fields">
                              <label>
                                <span>Stock</span>
                                <input
                                  class="seller-stock-input"
                                  type="number"
                                  min="0"
                                  name="variants[<?= $dPid ?>][<?= (int) $variantIdx ?>][stock_qty]"
                                  value="<?= (int) ($saved['stock_qty'] ?? 0) ?>"
                                >
                              </label>
                              <label>
                                <span>Status</span>
                                <select
                                  class="seller-status-select"
                                  name="variants[<?= $dPid ?>][<?= (int) $variantIdx ?>][active]"
                                >
                                  <option value="1"<?= ((int) ($saved['active'] ?? 1) === 1) ? ' selected' : '' ?>>Active</option>
                                  <option value="0"<?= ((int) ($saved['active'] ?? 1) === 0) ? ' selected' : '' ?>>Inactive</option>
                                </select>
                              </label>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                  <div class="seller-drawer__foot seller-variant-drawer__foot">
                    <p class="seller-variant-drawer__foot-hint">Changes yahan se save karein — poori inventory form submit hogi.</p>
                    <div class="seller-drawer__foot-actions">
                      <button type="button" class="admin-btn admin-btn--ghost-light" data-variant-drawer-close>Close</button>
                      <button type="submit" class="admin-btn admin-btn--primary">Save inventory</button>
                    </div>
                  </div>
                </aside>
              <?php endforeach; ?>
              <?php endif; ?>
            </form>
          </div>
        </div>

        <script>
          (function () {
            var backdrop = document.getElementById('variantDrawerBackdrop');
            var drawers = document.querySelectorAll('.seller-variant-drawer');
            if (!backdrop || !drawers.length) return;

            var openDrawer = null;
            var lastFocus = null;

            function closeVariantDrawer() {
              if (!openDrawer) return;
              openDrawer.classList.remove('is-open');
              openDrawer.setAttribute('aria-hidden', 'true');
              backdrop.classList.remove('is-visible');
              document.body.classList.remove('seller-drawer-open');
              openDrawer = null;
              if (lastFocus && typeof lastFocus.focus === 'function') {
                try { lastFocus.focus(); } catch (e) {}
              }
            }

            function openVariantDrawer(id) {
              var d = document.getElementById(id);
              if (!d) return;
              if (openDrawer && openDrawer !== d) {
                openDrawer.classList.remove('is-open');
                openDrawer.setAttribute('aria-hidden', 'true');
              }
              lastFocus = document.activeElement;
              openDrawer = d;
              d.classList.add('is-open');
              d.setAttribute('aria-hidden', 'false');
              backdrop.classList.add('is-visible');
              document.body.classList.add('seller-drawer-open');
              var firstInput = d.querySelector('input, select, button');
              if (firstInput) firstInput.focus();
            }

            document.querySelectorAll('.seller-variant-open-btn').forEach(function (btn) {
              btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-variant-drawer');
                if (id) openVariantDrawer(id);
              });
            });

            document.querySelectorAll('[data-variant-drawer-close]').forEach(function (btn) {
              btn.addEventListener('click', closeVariantDrawer);
            });

            backdrop.addEventListener('click', closeVariantDrawer);

            document.addEventListener('keydown', function (ev) {
              if (ev.key !== 'Escape') return;
              if (!openDrawer) return;
              ev.preventDefault();
              closeVariantDrawer();
            });
          })();
        </script>

        <script>
          (function () {
            var input = document.getElementById('inventoryTableSearch');
            if (!input) return;
            var groups = document.querySelectorAll('tbody.inventory-product-group');
            if (!groups.length) return;

            function applyFilter() {
              var q = (input.value || '').trim().toLowerCase();
              var words = q.split(/\s+/).filter(Boolean);
              var anyShown = false;
              groups.forEach(function (tb) {
                var hay = (tb.getAttribute('data-inventory-search') || '').toLowerCase();
                var show = words.length === 0 || words.every(function (w) {
                  return hay.indexOf(w) !== -1;
                });
                tb.style.display = show ? '' : 'none';
                if (show) anyShown = true;
              });
              var noMatch = document.getElementById('inventoryNoMatchRow');
              if (noMatch) {
                noMatch.style.display = (words.length > 0 && !anyShown) ? '' : 'none';
              }
            }

            input.addEventListener('input', applyFilter);
            input.addEventListener('search', applyFilter);
          })();
        </script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
