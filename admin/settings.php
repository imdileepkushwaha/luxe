<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/site_settings.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Settings';
$activeNav = 'settings';

$adminId = (int) ($admin['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            header('Location: settings.php?msg=profile_invalid');
            exit;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: settings.php?msg=profile_invalid');
            exit;
        }
        $dup = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? AND id != ? LIMIT 1');
        $dup->execute([$email, $adminId]);
        if ($dup->fetch()) {
            header('Location: settings.php?msg=profile_email_taken');
            exit;
        }
        $upd = $pdo->prepare('UPDATE admin_users SET full_name = ?, email = ? WHERE id = ? AND is_active = 1 LIMIT 1');
        $upd->execute([$fullName, $email, $adminId]);
        header('Location: settings.php?msg=' . ($upd->rowCount() > 0 ? 'profile_saved' : 'profile_fail'));
        exit;
    }

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        if (strlen($new) < 8 || $new !== $confirm) {
            header('Location: settings.php?msg=password_mismatch');
            exit;
        }
        $st = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
        $st->execute([$adminId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $hash = (string) ($row['password_hash'] ?? '');
        if ($hash === '' || !password_verify($current, $hash)) {
            header('Location: settings.php?msg=password_current_wrong');
            exit;
        }
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ? LIMIT 1');
        $upd->execute([$newHash, $adminId]);
        header('Location: settings.php?msg=' . ($upd->rowCount() > 0 ? 'password_saved' : 'password_fail'));
        exit;
    }

    if ($action === 'save_site_defaults') {
        $platformFee = max(0, (int) ($_POST['platform_fee_rupees'] ?? 0));
        $freeShipMin = max(0, (int) ($_POST['cart_free_shipping_min_rupees'] ?? 0));
        $belowMinShip = max(0, (int) ($_POST['cart_below_min_shipping_fee_rupees'] ?? 0));
        site_setting_set($pdo, 'platform_fee_rupees', (string) $platformFee);
        site_setting_set($pdo, 'cart_free_shipping_min_rupees', (string) $freeShipMin);
        site_setting_set($pdo, 'cart_below_min_shipping_fee_rupees', (string) $belowMinShip);
        header('Location: settings.php?msg=site_saved');
        exit;
    }

    header('Location: settings.php?msg=invalid');
    exit;
}

$flash = null;
$msg = (string) ($_GET['msg'] ?? '');
if ($msg === 'profile_saved') {
    $flash = ['ok' => true, 'text' => 'Profile update ho gaya.'];
} elseif ($msg === 'profile_invalid') {
    $flash = ['ok' => false, 'text' => 'Naam ya email valid nahi hai.'];
} elseif ($msg === 'profile_email_taken') {
    $flash = ['ok' => false, 'text' => 'Yeh email kisi aur admin par pehle se hai.'];
} elseif ($msg === 'profile_fail') {
    $flash = ['ok' => false, 'text' => 'Profile save nahi ho paya.'];
} elseif ($msg === 'password_saved') {
    $flash = ['ok' => true, 'text' => 'Password change ho gaya.'];
} elseif ($msg === 'password_mismatch') {
    $flash = ['ok' => false, 'text' => 'Naya password kam se kam 8 characters ka ho aur dono fields match karein.'];
} elseif ($msg === 'password_current_wrong') {
    $flash = ['ok' => false, 'text' => 'Current password galat hai.'];
} elseif ($msg === 'password_fail') {
    $flash = ['ok' => false, 'text' => 'Password update nahi ho paya.'];
} elseif ($msg === 'site_saved') {
    $flash = ['ok' => true, 'text' => 'Store defaults save ho gaye.'];
} elseif ($msg === 'invalid') {
    $flash = ['ok' => false, 'text' => 'Invalid action.'];
}

$detailSt = $pdo->prepare(
    'SELECT id, email, full_name, is_active, created_at FROM admin_users WHERE id = ? LIMIT 1'
);
$detailSt->execute([$adminId]);
$adminDetail = $detailSt->fetch(PDO::FETCH_ASSOC) ?: [];

$platformFee = site_platform_fee_rupees($pdo);
$freeShipMin = site_cart_free_shipping_min_rupees($pdo);
$belowMinShip = site_cart_below_min_shipping_fee_rupees($pdo);

require __DIR__ . '/partials/shell-top.php';
?>

        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?>" role="status" style="margin-bottom:14px">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <h1>Settings</h1>
        </div>

        <div class="card" style="margin-bottom:16px">
          <div class="card-header">
            <h2 class="card-title">Admin account</h2>
          </div>
          <div class="card-body">
            <div class="admin-table-wrap" style="margin-bottom:1.25rem">
              <table class="admin-table">
                <tbody>
                  <tr><th style="width:200px">Admin ID</th><td><?= (int) ($adminDetail['id'] ?? 0) ?></td></tr>
                  <tr><th>Status</th><td><?= (int) ($adminDetail['is_active'] ?? 0) === 1 ? '<span class="admin-status admin-status--delivered">Active</span>' : '<span class="admin-status admin-status--cancelled">Inactive</span>' ?></td></tr>
                  <tr><th>Member since</th><td><?= h((string) ($adminDetail['created_at'] ?? '—')) ?></td></tr>
                </tbody>
              </table>
            </div>
            <form method="post" class="admin-settings-form">
              <input type="hidden" name="action" value="update_profile">
              <div style="display:grid;gap:14px;max-width:420px">
                <label>
                  <span class="admin-stat__delta admin-stat__delta--muted" style="display:block;margin-bottom:6px;font-weight:500">Full name</span>
                  <input type="text" name="full_name" required maxlength="120" value="<?= h((string) ($adminDetail['full_name'] ?? '')) ?>" style="width:100%;padding:10px 12px;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface);color:var(--admin-text)">
                </label>
                <label>
                  <span class="admin-stat__delta admin-stat__delta--muted" style="display:block;margin-bottom:6px;font-weight:500">Email</span>
                  <input type="email" name="email" required value="<?= h((string) ($adminDetail['email'] ?? '')) ?>" style="width:100%;padding:10px 12px;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface);color:var(--admin-text)">
                </label>
                <button type="submit" class="admin-btn admin-btn--primary" style="justify-self:start">Save profile</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card" style="margin-bottom:16px">
          <div class="card-header">
            <h2 class="card-title">Change password</h2>
          </div>
          <div class="card-body">
            <form method="post" class="admin-settings-form">
              <input type="hidden" name="action" value="change_password">
              <div style="display:grid;gap:14px;max-width:420px">
                <label>
                  <span class="admin-stat__delta admin-stat__delta--muted" style="display:block;margin-bottom:6px;font-weight:500">Current password</span>
                  <input type="password" name="current_password" required autocomplete="current-password" style="width:100%;padding:10px 12px;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface);color:var(--admin-text)">
                </label>
                <label>
                  <span class="admin-stat__delta admin-stat__delta--muted" style="display:block;margin-bottom:6px;font-weight:500">New password (min 8 characters)</span>
                  <input type="password" name="new_password" required minlength="8" autocomplete="new-password" style="width:100%;padding:10px 12px;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface);color:var(--admin-text)">
                </label>
                <label>
                  <span class="admin-stat__delta admin-stat__delta--muted" style="display:block;margin-bottom:6px;font-weight:500">Confirm new password</span>
                  <input type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password" style="width:100%;padding:10px 12px;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface);color:var(--admin-text)">
                </label>
                <button type="submit" class="admin-btn" style="justify-self:start;border:1px solid var(--admin-border)">Update password</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h2 class="card-title">Store defaults</h2>
            <p class="admin-stat__delta admin-stat__delta--muted" style="margin:0.35rem 0 0;font-weight:400">Checkout / cart rules (site_settings). Seller-specific shipping ab bhi seller panel se aata hai.</p>
          </div>
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="action" value="save_site_defaults">
              <div style="display:grid;gap:14px;max-width:420px">
                <label>
                  <span class="admin-stat__delta admin-stat__delta--muted" style="display:block;margin-bottom:6px;font-weight:500">Platform fee (Rs per order)</span>
                  <input type="number" name="platform_fee_rupees" required min="0" step="1" value="<?= (int) $platformFee ?>" style="width:100%;padding:10px 12px;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface);color:var(--admin-text)">
                </label>
                <label>
                  <span class="admin-stat__delta admin-stat__delta--muted" style="display:block;margin-bottom:6px;font-weight:500">Free shipping — minimum cart (Rs)</span>
                  <input type="number" name="cart_free_shipping_min_rupees" required min="0" step="1" value="<?= (int) $freeShipMin ?>" style="width:100%;padding:10px 12px;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface);color:var(--admin-text)">
                </label>
                <label>
                  <span class="admin-stat__delta admin-stat__delta--muted" style="display:block;margin-bottom:6px;font-weight:500">Below minimum — platform shipping fee (Rs)</span>
                  <input type="number" name="cart_below_min_shipping_fee_rupees" required min="0" step="1" value="<?= (int) $belowMinShip ?>" style="width:100%;padding:10px 12px;border:1px solid var(--admin-border);border-radius:8px;background:var(--admin-surface);color:var(--admin-text)">
                </label>
                <button type="submit" class="admin-btn admin-btn--primary" style="justify-self:start">Save store defaults</button>
              </div>
            </form>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
