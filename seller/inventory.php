<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

/**
 * @return list<string>
 */
function seller_parse_option_csv(string $csv): array
{
    $parts = array_map('trim', explode(',', $csv));
    $parts = array_values(array_filter($parts, static fn(string $v): bool => $v !== ''));
    return array_values(array_unique($parts));
}

/**
 * @param list<string> $sizes
 * @param list<string> $colors
 * @return list<array{size:string,color:string,key:string,label:string}>
 */
function seller_variant_combinations(array $sizes, array $colors): array
{
    if ($sizes === [] && $colors === []) {
        return [];
    }
    $sizeList = $sizes === [] ? [''] : $sizes;
    $colorList = $colors === [] ? [''] : $colors;
    $rows = [];
    foreach ($sizeList as $size) {
        foreach ($colorList as $color) {
            $sz = trim((string) $size);
            $cl = trim((string) $color);
            $key = strtolower($sz) . '|' . strtolower($cl);
            $label = '';
            if ($sz !== '' && $cl !== '') {
                $label = $sz . ' / ' . $cl;
            } elseif ($sz !== '') {
                $label = $sz;
            } elseif ($cl !== '') {
                $label = $cl;
            } else {
                $label = 'Default';
            }
            $rows[] = [
                'size' => $sz,
                'color' => $cl,
                'key' => $key,
                'label' => $label,
            ];
        }
    }
    return $rows;
}

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

        <div class="admin-page-head">
          <h1>Inventory management</h1>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>" style="margin-bottom:14px"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi">
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">Total products</div>
              <div class="seller-kpi-card__value"><?= (int) $totalProducts ?></div>
              <div class="seller-kpi-card__hint">All products in your catalogue</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Active products</div>
              <div class="seller-kpi-card__value"><?= (int) $activeProducts ?></div>
              <div class="seller-kpi-card__hint">Visible to buyers</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Low stock</div>
              <div class="seller-kpi-card__value"><?= (int) $lowStockProducts ?></div>
              <div class="seller-kpi-card__hint">Stock between 1-5</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Variant rows</div>
              <div class="seller-kpi-card__value"><?= (int) $totalVariantRows ?></div>
              <div class="seller-kpi-card__hint">Size/Color combinations</div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M7 12h10"/><path d="M10 18h4"/></svg>
            </div>
          </div>
        </div>

        <div class="card" style="margin-top:16px">
          <div class="card-header">
            <div class="seller-card-head seller-card-head--inventory">
              <h2 class="card-title">Manage inventory</h2>
              <div class="seller-inventory-toolbar">
                <label class="seller-inventory-search-wrap" for="inventoryTableSearch">
                  <span class="seller-inventory-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="inventoryTableSearch"
                    class="seller-inventory-search-input"
                    placeholder="Search by name, slug, category, ID…"
                    autocomplete="off"
                    aria-label="Search products in inventory"
                  >
                </label>
                <button type="submit" form="inventoryForm" class="admin-btn admin-btn--primary">Save inventory</button>
              </div>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <form method="post" id="inventoryForm">
              <input type="hidden" name="action" value="save_inventory">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>Category</th>
                      <th>Price</th>
                      <th>Stock qty</th>
                      <th>Status</th>
                      <th>Badge</th>
                      <th>Preview</th>
                    </tr>
                  </thead>
                  <?php if ($products === []): ?>
                  <tbody>
                    <tr><td colspan="7">No products available. Pehle product add karein.</td></tr>
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
                      ?>
                  <tbody class="inventory-product-group" data-inventory-search="<?= h($inventorySearchBlob) ?>">
                      <tr>
                        <td>
                          <?php if ((string) ($p['image_path'] ?? '') !== ''): ?>
                            <img class="seller-product-thumb" src="../<?= h((string) $p['image_path']) ?>" alt="<?= h((string) $p['name']) ?>">
                          <?php else: ?>
                            <span class="seller-product-thumb seller-product-thumb--placeholder">No image</span>
                          <?php endif; ?>
                          <div style="margin-top:6px">
                            <strong><?= h((string) $p['name']) ?></strong><br>
                            <small><?= h((string) $p['slug']) ?></small>
                          </div>
                        </td>
                        <td><?= h((string) $p['category']) ?></td>
                        <td>Rs <?= number_format((int) ($p['price'] ?? 0)) ?></td>
                        <td>
                          <?php if ($matrix !== []): ?>
                            <input
                              class="seller-stock-input seller-stock-input--readonly"
                              type="number"
                              min="0"
                              name="rows[<?= $pid ?>][stock_qty]"
                              value="<?= (int) $mainStockDisplay ?>"
                              readonly
                              title="Size/color products: yahan total dikhta hai. Stock badalne ke liye View → variant stock."
                            >
                            <div class="seller-help" style="margin-top:4px;font-size:0.78rem">Per variant: <strong>View</strong></div>
                          <?php else: ?>
                            <input
                              class="seller-stock-input"
                              type="number"
                              min="0"
                              name="rows[<?= $pid ?>][stock_qty]"
                              value="<?= $stock ?>"
                            >
                          <?php endif; ?>
                        </td>
                        <td>
                          <select class="seller-status-select" name="rows[<?= $pid ?>][active]">
                            <option value="1"<?= $active === 1 ? ' selected' : '' ?>>Active</option>
                            <option value="0"<?= $active === 0 ? ' selected' : '' ?>>Inactive</option>
                          </select>
                        </td>
                        <td>
                          <input
                            class="seller-badge-input"
                            type="text"
                            maxlength="64"
                            name="rows[<?= $pid ?>][badge]"
                            value="<?= h((string) ($p['badge'] ?? '')) ?>"
                            placeholder="e.g. Sale / New / Discontinued"
                          >
                        </td>
                        <td>
                          <a class="seller-preview-btn" href="../product.php?id=<?= $pid ?>" target="_blank" rel="noopener">View product</a>
                        </td>
                      </tr>
                      <tr class="seller-variant-summary-row">
                        <td colspan="7" class="seller-variant-cell">
                          <?php if ($matrix === []): ?>
                            <p class="seller-help" style="margin:0">Is product me size/color options set nahi hain. <a href="products.php">Products</a> se edit karke options add karein.</p>
                          <?php else: ?>
                            <div class="seller-variant-summary">
                              <span class="seller-variant-summary__label">Variant stock (Size / Color)</span>
                              <button
                                type="button"
                                class="admin-btn admin-btn--secondary seller-variant-open-btn"
                                data-variant-drawer="variantDrawer-<?= $pid ?>"
                                aria-haspopup="dialog"
                                aria-controls="variantDrawer-<?= $pid ?>"
                              >View</button>
                              <span class="seller-variant-hint"><?= count($matrix) ?> combination(s)</span>
                            </div>
                          <?php endif; ?>
                        </td>
                      </tr>
                  </tbody>
                    <?php endforeach; ?>
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
              groups.forEach(function (tb) {
                var hay = (tb.getAttribute('data-inventory-search') || '').toLowerCase();
                var show = words.length === 0 || words.every(function (w) {
                  return hay.indexOf(w) !== -1;
                });
                tb.style.display = show ? '' : 'none';
              });
            }

            input.addEventListener('input', applyFilter);
            input.addEventListener('search', applyFilter);
          })();
        </script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
