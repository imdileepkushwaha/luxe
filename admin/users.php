<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Users';
$activeNav = 'users';

$users = $pdo->query(
    'SELECT id, first_name, last_name, email, phone, gender, dob, created_at
     FROM users
     ORDER BY id DESC'
)->fetchAll();

require __DIR__ . '/partials/shell-top.php';
?>

        <h1 class="admin-page-title">Manage users</h1>
        <div class="admin-card">
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Gender</th>
                  <th>DOB</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td><?= (int) $u['id'] ?></td>
                    <td>
                      <div class="admin-cell-user">
                        <?php
                        $fn = trim((string) $u['first_name']);
                        $ln = trim((string) $u['last_name']);
                        $ini = strtoupper(substr($fn, 0, 1) . substr($ln, 0, 1));
                        if ($ini === '') {
                            $ini = '?';
                        }
                        ?>
                        <div class="admin-avatar-sm"><?= h($ini) ?></div>
                        <span><?= h(trim(((string) $u['first_name']) . ' ' . ((string) $u['last_name']))) ?></span>
                      </div>
                    </td>
                    <td><?= h((string) $u['email']) ?></td>
                    <td><?= h((string) $u['phone']) ?></td>
                    <td><?= h((string) ($u['gender'] ?? '—')) ?></td>
                    <td><?= h((string) ($u['dob'] ?? '—')) ?></td>
                    <td><?= h((string) $u['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
