<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$already = seller_user($pdo);
if ($already) {
    header('Location: index.php');
    exit;
}

$error = '';
$info = '';

$msg = (string) ($_GET['msg'] ?? '');
if ($msg === 'deleted') {
    $info = 'Seller account delete ho gaya. Agar dobara access chahiye to new create request bhejein.';
}
if (isset($_SESSION['seller_login_notice'])) {
    $info = trim((string) $_SESSION['seller_login_notice']);
    unset($_SESSION['seller_login_notice']);
}
$infoIsError = stripos($info, 'deactivated') !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email aur password required hai.';
    } else {
        $st = $pdo->prepare('SELECT id, password_hash, is_active FROM seller_users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();

        if (!$row) {
            $error = 'Invalid credentials.';
        } elseif (!password_verify($password, (string) $row['password_hash'])) {
            $error = 'Invalid credentials.';
        } elseif ((int) ($row['is_active'] ?? 0) !== 1) {
            seller_logout();
            $info = 'Your Seller Panel is Deactivated. For any query, contact admin.';
        } else {
            seller_set((int) $row['id']);
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
<?php require __DIR__ . '/../admin/partials/theme-head-script.php'; ?>
  <title>Seller Login - LUXE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body class="admin-login admin-app--merchant">
  <div class="admin-login-card">
    <div class="admin-login-card__brand">
      <div class="admin-sidebar__logo" style="width:44px;height:44px" aria-hidden="true"><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--a"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--b"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--c"></span></div>
      <div>
        <div class="admin-sidebar__title">LUXE</div>
        <div class="admin-sidebar__subtitle">Seller · Sign in</div>
      </div>
    </div>
    <h1>Seller panel login</h1>
    <p>Orders dekhne aur products manage karne ke liye sign in karein.</p>

    <?php if ($error !== ''): ?>
      <div class="err"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($info !== ''): ?>
      <div class="admin-del-flash<?= $infoIsError ? ' admin-del-flash--err' : ' admin-del-flash--ok' ?>" style="margin-bottom:12px"><?= h($info) ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required autocomplete="email" placeholder="seller@luxe.com">

      <label for="password">Password</label>
      <div class="seller-password-wrap">
        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="********">
        <?php require __DIR__ . '/partials/password-toggle-button.php'; ?>
      </div>

      <button type="submit">Login</button>
    </form>

    <div class="hint">Default: seller@luxe.com / seller123</div>
    <div class="hint"><a href="create-request.php">Need seller account? Submit registration request</a></div>
  </div>
  <script src="js/password-toggle.js?v=2"></script>
</body>
</html>
