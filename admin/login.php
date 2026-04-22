<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$already = admin_user($pdo);
if ($already) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email aur password required hai.';
    } else {
        $st = $pdo->prepare('SELECT id, password_hash, is_active FROM admin_users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();

        if (!$row || (int) $row['is_active'] !== 1 || !password_verify($password, (string) $row['password_hash'])) {
            $error = 'Invalid credentials.';
        } else {
            admin_set((int) $row['id']);
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/partials/theme-head-script.php'; ?>
  <title>Admin Login — LUXE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-login admin-app--platform">
  <div class="admin-login-card">
    <div class="admin-login-card__brand">
      <div class="admin-sidebar__logo" style="width:44px;height:44px" aria-hidden="true"><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--a"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--b"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--c"></span></div>
      <div>
        <div class="admin-sidebar__title">LUXE</div>
        <div class="admin-sidebar__subtitle">Admin · Sign in</div>
      </div>
    </div>
    <h1>Welcome back</h1>
    <p>Admin dashboard access ke liye sign in karein.</p>

    <?php if ($error !== ''): ?>
      <div class="err"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required autocomplete="email" placeholder="admin@luxe.com">

      <label for="password">Password</label>
      <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="********">

      <button type="submit">Login</button>
    </form>

    <div class="hint">Default: admin@luxe.com / admin123</div>
  </div>
  <script src="js/password-toggle.js" defer></script>
</body>
</html>
