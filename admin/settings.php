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

$memberSince = (string) ($adminDetail['created_at'] ?? '—');
if ($memberSince !== '—' && $memberSince !== '') {
    try {
        $memberSince = (new DateTimeImmutable($memberSince))->format('M j, Y · g:i A');
    } catch (Throwable $e) {
        /* keep raw */
    }
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-settings-page">
        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?>" role="status">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Account &amp; store</span>
            <h1>Settings</h1>
            <p class="admin-page-head__lede">Profile, security, and checkout defaults for the site.</p>
          </div>
        </div>

        <div class="admin-settings-cols">
          <div class="card admin-settings-card">
            <div class="card-header admin-settings-card-header">
              <div class="admin-settings-card-head">
                <div class="admin-settings-card-head-text">
                  <div class="admin-settings-card-title-row">
                    <span class="admin-card__title-icon admin-card__title-icon--accent" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    <div>
                      <h2 class="card-title">Admin account</h2>
                      <p class="card-subtitle admin-settings-card-sub">Your name and email for this console</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="card-body">
              <div class="admin-settings-summary" role="group" aria-label="Account details">
                <div class="admin-settings-summary__item">
                  <span class="admin-settings-summary__label">Admin ID</span>
                  <span class="admin-settings-summary__value admin-settings-summary__value--id"><?= (int) ($adminDetail['id'] ?? 0) ?></span>
                </div>
                <div class="admin-settings-summary__item">
                  <span class="admin-settings-summary__label">Status</span>
                  <div class="admin-settings-summary__value">
                    <?php if ((int) ($adminDetail['is_active'] ?? 0) === 1): ?>
                      <span class="admin-status admin-status--delivered">Active</span>
                    <?php else: ?>
                      <span class="admin-status admin-status--cancelled">Inactive</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="admin-settings-summary__item">
                  <span class="admin-settings-summary__label">Member since</span>
                  <span class="admin-settings-summary__value admin-settings-summary__value--date"><?= h($memberSince) ?></span>
                </div>
              </div>
              <form method="post" class="admin-settings-form admin-form-stack admin-form-stack--narrow">
                <input type="hidden" name="action" value="update_profile">
                <label class="admin-field">
                  <span class="admin-field__label">Full name</span>
                  <input class="admin-input" type="text" name="full_name" required maxlength="120" value="<?= h((string) ($adminDetail['full_name'] ?? '')) ?>">
                </label>
                <label class="admin-field">
                  <span class="admin-field__label">Email</span>
                  <input class="admin-input" type="email" name="email" required value="<?= h((string) ($adminDetail['email'] ?? '')) ?>">
                </label>
                <div class="admin-form-actions">
                  <button type="submit" class="admin-btn admin-btn--primary">Save profile</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card admin-settings-card">
            <div class="card-header admin-settings-card-header">
              <div class="admin-settings-card-head">
                <div class="admin-settings-card-head-text">
                  <div class="admin-settings-card-title-row">
                    <span class="admin-card__title-icon admin-card__title-icon--violet" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                    <div>
                      <h2 class="card-title">Change password</h2>
                      <p class="card-subtitle admin-settings-card-sub">Pick a strong password and do not reuse it on other sites</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="card-body">
              <form method="post" class="admin-settings-form admin-form-stack admin-form-stack--narrow">
                <input type="hidden" name="action" value="change_password">
                <label class="admin-field">
                  <span class="admin-field__label">Current password</span>
                  <input class="admin-input" type="password" name="current_password" required autocomplete="current-password">
                </label>
                <label class="admin-field">
                  <span class="admin-field__label">New password</span>
                  <span class="admin-field__hint">At least 8 characters</span>
                  <input class="admin-input" type="password" name="new_password" required minlength="8" autocomplete="new-password">
                </label>
                <label class="admin-field">
                  <span class="admin-field__label">Confirm new password</span>
                  <input class="admin-input" type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password">
                </label>
                <div class="admin-form-actions">
                  <button type="submit" class="admin-btn admin-btn--outline">Update password</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="card admin-settings-store">
          <div class="card-header admin-settings-card-header">
            <div class="admin-settings-card-head">
              <div class="admin-settings-card-head-text">
                <div class="admin-settings-card-title-row">
                  <span class="admin-card__title-icon admin-card__title-icon--amber" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg></span>
                  <div>
                    <h2 class="card-title">Store defaults</h2>
                    <p class="card-subtitle admin-settings-card-sub">Checkout and cart rules from <code class="admin-inline-code">site_settings</code>. Seller-specific shipping still comes from the seller panel.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="card-body">
              <form method="post" class="admin-settings-form">
                <input type="hidden" name="action" value="save_site_defaults">
                <div class="admin-form-grid-3">
                  <label class="admin-field">
                    <span class="admin-field__label">Platform fee (₹ / order)</span>
                    <input class="admin-input" type="number" name="platform_fee_rupees" required min="0" step="1" value="<?= (int) $platformFee ?>">
                  </label>
                  <label class="admin-field">
                    <span class="admin-field__label">Free shipping — min. cart (₹)</span>
                    <input class="admin-input" type="number" name="cart_free_shipping_min_rupees" required min="0" step="1" value="<?= (int) $freeShipMin ?>">
                  </label>
                  <label class="admin-field">
                    <span class="admin-field__label">Below minimum — shipping fee (₹)</span>
                    <input class="admin-input" type="number" name="cart_below_min_shipping_fee_rupees" required min="0" step="1" value="<?= (int) $belowMinShip ?>">
                  </label>
                </div>
                <div class="admin-form-actions admin-form-actions--spaced">
                  <button type="submit" class="admin-btn admin-btn--primary">Save store defaults</button>
                </div>
              </form>
          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
