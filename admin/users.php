<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_pagination.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Users';
$activeNav = 'users';

['page' => $reqPage, 'perPage' => $perPage] = admin_pagination_read(25);
$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

$kpiRow = $pdo->query(
    'SELECT
       COUNT(*) AS total,
       SUM(CASE WHEN TRIM(COALESCE(phone, \'\')) <> \'\' THEN 1 ELSE 0 END) AS with_phone,
       SUM(CASE WHEN dob IS NOT NULL THEN 1 ELSE 0 END) AS with_dob,
       SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS new_30d
     FROM users'
)->fetch(PDO::FETCH_ASSOC) ?: [];
$kpiWithPhone = (int) ($kpiRow['with_phone'] ?? 0);
$kpiWithDob = (int) ($kpiRow['with_dob'] ?? 0);
$kpiNew30d = (int) ($kpiRow['new_30d'] ?? 0);

$pMeta = admin_pagination_resolve($totalUsers, $reqPage, $perPage);
$page = $pMeta['page'];
$offset = $pMeta['offset'];
$perPage = $pMeta['perPage'];
$totalPages = $pMeta['totalPages'];

$usersSt = $pdo->prepare(
    'SELECT id, first_name, last_name, email, phone, gender, dob, created_at
     FROM users
     ORDER BY id DESC
     LIMIT ? OFFSET ?'
);
$usersSt->bindValue(1, $perPage, PDO::PARAM_INT);
$usersSt->bindValue(2, $offset, PDO::PARAM_INT);
$usersSt->execute();
$users = $usersSt->fetchAll();

/**
 * @param mixed $raw
 */
function admin_users_fmt_date($raw, bool $withTime = false): string
{
    if ($raw === null || $raw === '') {
        return '—';
    }
    $s = (string) $raw;
    try {
        $d = new DateTimeImmutable($s);

        return $d->format($withTime ? 'M j, Y · g:i A' : 'M j, Y');
    } catch (Throwable $e) {
        return $s;
    }
}

/**
 * Lowercase string for client-side search (data attribute).
 *
 * @param mixed $raw
 */
function admin_users_search_haystack(array $u, string $fn, string $ln, string $phone, string $gender): string
{
    $parts = [
        (string) ($u['id'] ?? ''),
        $fn,
        $ln,
        trim($fn . ' ' . $ln),
        (string) ($u['email'] ?? ''),
        $phone,
        $gender,
        (string) ($u['dob'] ?? ''),
        (string) ($u['created_at'] ?? ''),
    ];
    $clean = [];
    foreach ($parts as $p) {
        $t = trim((string) $p);
        if ($t !== '') {
            $clean[] = $t;
        }
    }
    $s = implode(' ', $clean);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($s, 'UTF-8');
    }

    return strtolower($s);
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-users-page">
        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Shop</span>
            <h1>Users</h1>
            <p class="admin-page-head__lede">Registered customer accounts and profile details.</p>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats admin-grid--stats--flow admin-users-kpi-grid" aria-label="User summary">
          <div class="admin-card admin-stat admin-stat--stripe-blue admin-users-kpi">
            <div>
              <div class="admin-stat__label admin-users-kpi__label">All users</div>
              <div class="admin-stat__value"><?= (int) $totalUsers ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Registered accounts</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-green admin-users-kpi">
            <div>
              <div class="admin-stat__label admin-users-kpi__label">Phone on file</div>
              <div class="admin-stat__value"><?= (int) $kpiWithPhone ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Contact number saved</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--green" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-violet admin-users-kpi">
            <div>
              <div class="admin-stat__label admin-users-kpi__label">Birthday set</div>
              <div class="admin-stat__value"><?= (int) $kpiWithDob ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Date of birth on profile</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--purple" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-indigo admin-users-kpi">
            <div>
              <div class="admin-stat__label admin-users-kpi__label">New (30 days)</div>
              <div class="admin-stat__value"><?= (int) $kpiNew30d ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Recently joined</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--indigo" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            </div>
          </div>
        </div>

        <div class="card admin-users-card">
          <div class="card-header admin-users-card-header">
            <div class="admin-users-card-head">
              <div class="admin-users-card-head-text">
                <h2 class="card-title">All users</h2>
                <p class="card-subtitle admin-users-card-sub"><?= (int) $totalUsers ?> user<?= $totalUsers === 1 ? '' : 's' ?> total · Search filters this page only. On small screens, scroll the table sideways if needed.</p>
              </div>
              <?php if ($users !== []): ?>
                <label class="admin-users-search-wrap" for="adminUsersSearch">
                  <span class="admin-users-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminUsersSearch"
                    class="admin-users-search-input"
                    placeholder="Search name, email, phone, ID…"
                    autocomplete="off"
                    aria-label="Search users"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <?php if ($users === []): ?>
              <p class="admin-empty-hint admin-empty-hint--boxed">No registered users yet.</p>
            <?php else: ?>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th class="admin-table__th-narrow">ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th class="admin-table__th-narrow">Gender</th>
                    <th>DOB</th>
                    <th>Created</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                    <?php
                    $fn = trim((string) $u['first_name']);
                    $ln = trim((string) $u['last_name']);
                    $ini = strtoupper(substr($fn, 0, 1) . substr($ln, 0, 1));
                    if ($ini === '') {
                        $ini = '?';
                    }
                    $name = trim($fn . ' ' . $ln);
                    if ($name === '') {
                        $name = '—';
                    }
                    $phone = trim((string) ($u['phone'] ?? ''));
                    $phoneDisp = $phone === '' ? '—' : $phone;
                    $gender = trim((string) ($u['gender'] ?? ''));
                    $hay = admin_users_search_haystack($u, $fn, $ln, $phone, $gender);
                    ?>
                    <tr class="admin-users-row" data-users-search="<?= h($hay) ?>">
                      <td class="admin-table__td-num"><?= (int) $u['id'] ?></td>
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <span class="admin-users-name"><?= h($name) ?></span>
                        </div>
                      </td>
                      <td class="admin-table__cell-email"><span class="admin-users-email"><?= h((string) $u['email']) ?></span></td>
                      <td class="admin-table__td-muted"><?= h($phoneDisp) ?></td>
                      <td>
                        <?php if ($gender !== ''): ?>
                          <span class="admin-badge admin-badge--muted"><?= h($gender) ?></span>
                        <?php else: ?>
                          <span class="admin-table__dash">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="admin-table__td-muted"><?= h(admin_users_fmt_date($u['dob'] ?? null, false)) ?></td>
                      <td class="admin-table__td-muted"><?= h(admin_users_fmt_date($u['created_at'] ?? null, true)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="adminUsersNoMatchRow" class="admin-users-no-match-row">
                    <td colspan="7">
                      <div class="admin-users-no-match">
                        <strong class="admin-users-no-match__title">No matches</strong>
                        <p class="admin-users-no-match__text">Try another keyword — name, email, phone, or ID.</p>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <?php
            $paginationScript = 'users.php';
            $paginationTotal = $totalUsers;
            $paginationPage = $page;
            $paginationPerPage = $perPage;
            $paginationTotalPages = $totalPages;
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            <script>
            (function () {
              var searchInput = document.getElementById('adminUsersSearch');
              if (!searchInput) return;
              var userRows = document.querySelectorAll('tr.admin-users-row');
              var noMatchRow = document.getElementById('adminUsersNoMatchRow');

              function applyUserSearch() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var anyShown = false;
                userRows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-users-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) {
                    return hay.indexOf(w) !== -1;
                  });
                  tr.style.display = show ? '' : 'none';
                  if (show) anyShown = true;
                });
                if (noMatchRow) {
                  noMatchRow.style.display = (words.length > 0 && !anyShown) ? 'table-row' : 'none';
                }
              }

              searchInput.addEventListener('input', applyUserSearch);
              searchInput.addEventListener('search', applyUserSearch);
            })();
            </script>
            <?php endif; ?>
          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
