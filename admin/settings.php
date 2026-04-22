<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/site_settings.php';
require_once __DIR__ . '/../includes/platform_payment_gateway.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Settings';
$activeNav = 'settings';

$adminId = (int) ($admin['id'] ?? 0);

$pgwAllowedGateways = ['none', 'razorpay', 'stripe', 'payu'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last = trim((string) ($_POST['last_name'] ?? ''));
        $fullName = trim($first . ' ' . $last);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if ($first === '' || $fullName === '' || mb_strlen($fullName) > 120) {
            header('Location: settings.php?msg=profile_invalid&tab=profile');
            exit;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: settings.php?msg=profile_invalid&tab=profile');
            exit;
        }
        $dup = $pdo->prepare('SELECT id FROM admin_users WHERE email = ? AND id != ? LIMIT 1');
        $dup->execute([$email, $adminId]);
        if ($dup->fetch()) {
            header('Location: settings.php?msg=profile_email_taken&tab=profile');
            exit;
        }
        $upd = $pdo->prepare('UPDATE admin_users SET full_name = ?, email = ? WHERE id = ? AND is_active = 1 LIMIT 1');
        $upd->execute([$fullName, $email, $adminId]);
        $pmsg = $upd->rowCount() > 0 ? 'profile_saved' : 'profile_fail';
        header('Location: settings.php?msg=' . $pmsg . '&tab=profile');
        exit;
    }

    if ($action === 'change_password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        if (strlen($new) < 8 || $new !== $confirm) {
            header('Location: settings.php?msg=password_mismatch&tab=security');
            exit;
        }
        $st = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1');
        $st->execute([$adminId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $hash = (string) ($row['password_hash'] ?? '');
        if ($hash === '' || !password_verify($current, $hash)) {
            header('Location: settings.php?msg=password_current_wrong&tab=security');
            exit;
        }
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ? LIMIT 1');
        $upd->execute([$newHash, $adminId]);
        $pwmsg = $upd->rowCount() > 0 ? 'password_saved' : 'password_fail';
        header('Location: settings.php?msg=' . $pwmsg . '&tab=security');
        exit;
    }

    if ($action === 'save_site_defaults') {
        $platformFee = max(0, (int) ($_POST['platform_fee_rupees'] ?? 0));
        $freeShipMin = max(0, (int) ($_POST['cart_free_shipping_min_rupees'] ?? 0));
        $belowMinShip = max(0, (int) ($_POST['cart_below_min_shipping_fee_rupees'] ?? 0));
        site_setting_set($pdo, 'platform_fee_rupees', (string) $platformFee);
        site_setting_set($pdo, 'cart_free_shipping_min_rupees', (string) $freeShipMin);
        site_setting_set($pdo, 'cart_below_min_shipping_fee_rupees', (string) $belowMinShip);
        header('Location: settings.php?msg=site_saved&tab=store');
        exit;
    }

    if ($action === 'save_payment_gateway') {
        $gw = strtolower(trim((string) ($_POST['gateway'] ?? '')));
        if (!in_array($gw, $pgwAllowedGateways, true)) {
            $gw = 'none';
        }
        $mode = strtolower(trim((string) ($_POST['mode'] ?? 'test')));
        if (!in_array($mode, ['test', 'live'], true)) {
            $mode = 'test';
        }
        $publicKey = trim((string) ($_POST['public_key'] ?? ''));
        $secretKey = trim((string) ($_POST['secret_key'] ?? ''));
        $merchantId = trim((string) ($_POST['merchant_id'] ?? ''));
        $webhookSecret = trim((string) ($_POST['webhook_secret'] ?? ''));
        $prev = platform_payment_gateway_load($pdo);
        if ($secretKey === '') {
            $secretKey = (string) ($prev['secret_key'] ?? '');
        }
        if ($webhookSecret === '') {
            $webhookSecret = (string) ($prev['webhook_secret'] ?? '');
        }
        if (strlen($publicKey) > 255 || strlen($secretKey) > 255 || strlen($merchantId) > 120 || strlen($webhookSecret) > 255) {
            header('Location: settings.php?msg=gateway_invalid&tab=payments');
            exit;
        }
        if ($gw !== 'none' && (strlen($publicKey) < 8 || strlen($secretKey) < 8)) {
            header('Location: settings.php?msg=gateway_invalid&tab=payments');
            exit;
        }
        $upsert = $pdo->prepare(
            'INSERT INTO platform_payment_gateway_config
                (id, gateway, mode, public_key, secret_key, merchant_id, webhook_secret)
             VALUES (1, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                gateway = VALUES(gateway),
                mode = VALUES(mode),
                public_key = VALUES(public_key),
                secret_key = VALUES(secret_key),
                merchant_id = VALUES(merchant_id),
                webhook_secret = VALUES(webhook_secret),
                updated_at = CURRENT_TIMESTAMP'
        );
        $upsert->execute([$gw, $mode, $publicKey, $secretKey, $merchantId, $webhookSecret]);
        header('Location: settings.php?msg=gateway_saved&tab=payments');
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
} elseif ($msg === 'gateway_saved') {
    $flash = ['ok' => true, 'text' => 'Payment gateway settings save ho gayi.'];
} elseif ($msg === 'gateway_invalid') {
    $flash = ['ok' => false, 'text' => 'Gateway settings valid nahi hain — keys / lengths check karein.'];
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

$pgwConfig = platform_payment_gateway_load($pdo);
$pgwConfig['gateway'] = in_array((string) ($pgwConfig['gateway'] ?? ''), $pgwAllowedGateways, true)
    ? (string) $pgwConfig['gateway']
    : 'none';
$pgwConfig['mode'] = in_array((string) ($pgwConfig['mode'] ?? ''), ['test', 'live'], true)
    ? (string) $pgwConfig['mode']
    : 'test';
$pgwWebhookUrl = platform_payment_gateway_webhook_url();
$pgwLabel = match ($pgwConfig['gateway']) {
    'razorpay' => 'Razorpay',
    'stripe' => 'Stripe',
    'payu' => 'PayU',
    default => '—',
};

$memberSince = (string) ($adminDetail['created_at'] ?? '—');
if ($memberSince !== '—' && $memberSince !== '') {
    try {
        $memberSince = (new DateTimeImmutable($memberSince))->format('M j, Y · g:i A');
    } catch (Throwable $e) {
        /* keep raw */
    }
}

$tabRaw = (string) ($_GET['tab'] ?? '');
$tab = in_array($tabRaw, ['profile', 'security', 'store', 'payments'], true)
    ? $tabRaw
    : (match (true) {
        str_starts_with($msg, 'password_') => 'security',
        $msg === 'site_saved' => 'store',
        str_starts_with($msg, 'gateway_') => 'payments',
        default => 'profile',
    });

$rawName = trim((string) ($adminDetail['full_name'] ?? ''));
$firstName = '';
$lastName = '';
if ($rawName !== '') {
    if (preg_match('/^(\S+)\s+(.+)$/u', $rawName, $m)) {
        $firstName = $m[1];
        $lastName = trim($m[2]);
    } else {
        $firstName = $rawName;
    }
}

$avatarLetters = '';
foreach (preg_split('/\s+/', $rawName) ?: [] as $p) {
    if ($p === '') {
        continue;
    }
    $avatarLetters .= strtoupper(substr($p, 0, 1));
    if (strlen($avatarLetters) >= 2) {
        break;
    }
}
if ($avatarLetters === '') {
    $avatarLetters = 'A';
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-settings-page admin-settings-page--crms">
        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?>" role="status">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-settings-crms-head">
          <div>
            <p class="admin-settings-crms-head__label">General settings</p>
            <h1 class="admin-settings-crms-head__title">Settings</h1>
            <p class="admin-settings-crms-head__lede">Profile, security, store defaults, aur platform payment gateways yahan se manage hote hain.</p>
          </div>
        </div>

        <div class="card admin-settings-crms-card">
          <input class="admin-settings-crms-tab-input visually-hidden" type="radio" name="admin_settings_tab" id="admin-tab-profile" value="profile"<?= $tab === 'profile' ? ' checked' : '' ?>>
          <input class="admin-settings-crms-tab-input visually-hidden" type="radio" name="admin_settings_tab" id="admin-tab-security" value="security"<?= $tab === 'security' ? ' checked' : '' ?>>
          <input class="admin-settings-crms-tab-input visually-hidden" type="radio" name="admin_settings_tab" id="admin-tab-store" value="store"<?= $tab === 'store' ? ' checked' : '' ?>>
          <input class="admin-settings-crms-tab-input visually-hidden" type="radio" name="admin_settings_tab" id="admin-tab-payments" value="payments"<?= $tab === 'payments' ? ' checked' : '' ?>>

          <div class="admin-settings-crms-tabs" role="tablist" aria-label="Settings sections">
            <label class="admin-settings-crms-tab" for="admin-tab-profile" role="tab">Profile</label>
            <label class="admin-settings-crms-tab" for="admin-tab-security" role="tab">Security</label>
            <label class="admin-settings-crms-tab" for="admin-tab-store" role="tab">Store</label>
            <label class="admin-settings-crms-tab" for="admin-tab-payments" role="tab">Payments</label>
          </div>

          <div class="admin-settings-crms-panels">
            <div class="admin-settings-crms-panel admin-settings-crms-panel--profile">
              <section class="admin-settings-crms-section" aria-labelledby="settings-profile-info">
                <h2 class="admin-settings-crms-section__title" id="settings-profile-info">Administrator information</h2>
                <p class="admin-settings-crms-section__hint">Provide the information below.</p>

                <div class="admin-settings-crms-avatar-row">
                  <div class="admin-settings-crms-avatar" aria-hidden="true"><?= h($avatarLetters) ?></div>
                  <div class="admin-settings-crms-avatar-meta">
                    <span class="admin-settings-crms-avatar-meta__title">Profile image</span>
                    <span class="admin-settings-crms-avatar-meta__hint">JPG, GIF or PNG. Max size of 800K.</span>
                  </div>
                </div>

                <div class="admin-settings-summary admin-settings-summary--crms" role="group" aria-label="Account details">
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

                <form method="post" class="admin-settings-form admin-settings-crms-form">
                  <input type="hidden" name="action" value="update_profile">
                  <div class="admin-settings-crms-form-grid">
                    <label class="admin-field">
                      <span class="admin-field__label">First name <span class="admin-settings-crms-req" aria-hidden="true">*</span></span>
                      <input class="admin-input" type="text" name="first_name" required maxlength="80" autocomplete="given-name" value="<?= h($firstName) ?>">
                    </label>
                    <label class="admin-field">
                      <span class="admin-field__label">Last name</span>
                      <input class="admin-input" type="text" name="last_name" maxlength="80" autocomplete="family-name" value="<?= h($lastName) ?>">
                    </label>
                    <label class="admin-field admin-settings-crms-field-span2">
                      <span class="admin-field__label">Email <span class="admin-settings-crms-req" aria-hidden="true">*</span></span>
                      <input class="admin-input" type="email" name="email" required value="<?= h((string) ($adminDetail['email'] ?? '')) ?>">
                    </label>
                  </div>
                  <div class="admin-settings-crms-actions">
                    <a class="admin-btn admin-btn--outline" href="settings.php">Cancel</a>
                    <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
                  </div>
                </form>
              </section>
            </div>

            <div class="admin-settings-crms-panel admin-settings-crms-panel--security">
              <section class="admin-settings-crms-section" aria-labelledby="settings-security-title">
                <h2 class="admin-settings-crms-section__title" id="settings-security-title">Security</h2>
                <p class="admin-settings-crms-section__hint">Update your password. Use a strong password and do not reuse it elsewhere.</p>
                <form method="post" class="admin-settings-form admin-form-stack admin-form-stack--narrow admin-settings-crms-form">
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
                  <div class="admin-settings-crms-actions">
                    <a class="admin-btn admin-btn--outline" href="settings.php?tab=security">Cancel</a>
                    <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
                  </div>
                </form>
              </section>
            </div>

            <div class="admin-settings-crms-panel admin-settings-crms-panel--store">
              <section class="admin-settings-crms-section" aria-labelledby="settings-store-title">
                <h2 class="admin-settings-crms-section__title" id="settings-store-title">Store defaults</h2>
                <p class="admin-settings-crms-section__hint">Checkout and cart rules from <code class="admin-inline-code">site_settings</code>. Seller shipping overrides still apply from the seller panel.</p>
                <form method="post" class="admin-settings-form admin-settings-crms-form">
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
                  <div class="admin-settings-crms-actions">
                    <a class="admin-btn admin-btn--outline" href="settings.php?tab=store">Cancel</a>
                    <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
                  </div>
                </form>
              </section>
            </div>

            <div class="admin-settings-crms-panel admin-settings-crms-panel--payments">
              <section class="admin-settings-crms-section" aria-labelledby="settings-payments-title">
                <h2 class="admin-settings-crms-section__title" id="settings-payments-title">Payment gateways</h2>
                <p class="admin-settings-crms-section__hint">Platform-wide checkout provider — keys test mode se start karein, phir live. Webhook URL provider dashboard me register karein.</p>

                <ol class="admin-pgw-steps" aria-label="Integration steps">
                  <li class="admin-pgw-step admin-pgw-step--active"><span class="admin-pgw-step__n">1</span> Provider</li>
                  <li class="admin-pgw-step"><span class="admin-pgw-step__n">2</span> API keys</li>
                  <li class="admin-pgw-step"><span class="admin-pgw-step__n">3</span> Webhook</li>
                  <li class="admin-pgw-step"><span class="admin-pgw-step__n">4</span> Save &amp; test</li>
                </ol>

                <form method="post" class="admin-settings-form admin-settings-crms-form" aria-labelledby="settings-payments-title">
                  <input type="hidden" name="action" value="save_payment_gateway">

                  <fieldset class="admin-pgw-fieldset">
                    <legend class="admin-pgw-legend">Step 1 · Provider</legend>
                    <p class="admin-pgw-hint">Site-wide payment collection ke liye active provider yahan set hota hai.</p>
                    <div class="admin-pgw-gateway-grid" role="radiogroup" aria-label="Payment provider">
                      <?php
                        $pgwOpts = [
                            'none' => ['None (disabled)', 'Checkout gateway band'],
                            'razorpay' => ['Razorpay', 'India — cards, UPI, netbanking'],
                            'stripe' => ['Stripe', 'Global — cards, wallets'],
                            'payu' => ['PayU', 'India — multiple banks'],
                        ];
                      foreach ($pgwOpts as $val => $meta):
                          $checked = $pgwConfig['gateway'] === $val;
                      ?>
                        <label class="admin-pgw-gateway-tile<?= $checked ? ' is-selected' : '' ?>">
                          <input class="admin-pgw-gateway-input" type="radio" name="gateway" value="<?= h($val) ?>" <?= $checked ? 'checked' : '' ?>>
                          <span class="admin-pgw-gateway-tile__title"><?= h($meta[0]) ?></span>
                          <span class="admin-pgw-gateway-tile__hint"><?= h($meta[1]) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>

                  <fieldset class="admin-pgw-fieldset">
                    <legend class="admin-pgw-legend">Step 2 · Environment &amp; keys</legend>
                    <div class="admin-pgw-form-row">
                      <label for="admin_pgw_mode">Mode</label>
                      <select id="admin_pgw_mode" name="mode" class="admin-input" style="max-width:280px">
                        <option value="test"<?= $pgwConfig['mode'] === 'test' ? ' selected' : '' ?>>Test / sandbox</option>
                        <option value="live"<?= $pgwConfig['mode'] === 'live' ? ' selected' : '' ?>>Live</option>
                      </select>
                    </div>
                    <div class="admin-pgw-form-row admin-pgw-form-row--stack">
                      <div>
                        <label for="admin_pgw_public">Public / key ID <span class="admin-pgw-label-hint">(Razorpay Key Id, Stripe publishable, PayU merchant key)</span></label>
                        <input id="admin_pgw_public" name="public_key" class="admin-input" maxlength="255" value="<?= h((string) $pgwConfig['public_key']) ?>" autocomplete="off" placeholder="rzp_test_… / pk_test_…">
                      </div>
                      <div>
                        <label for="admin_pgw_secret">Secret key <span class="admin-pgw-label-hint">(server-side only)</span></label>
                        <input id="admin_pgw_secret" name="secret_key" type="password" class="admin-input" maxlength="255" value="" autocomplete="new-password" placeholder="<?= ($pgwConfig['secret_key'] ?? '') !== '' ? 'Leave blank to keep existing secret' : 'Min 8 characters' ?>">
                      </div>
                      <div>
                        <label for="admin_pgw_merchant">Merchant / account ID <span class="admin-pgw-label-hint">(optional)</span></label>
                        <input id="admin_pgw_merchant" name="merchant_id" class="admin-input" maxlength="120" value="<?= h((string) $pgwConfig['merchant_id']) ?>" autocomplete="off">
                      </div>
                      <div>
                        <label for="admin_pgw_whsec">Webhook signing secret <span class="admin-pgw-label-hint">(optional)</span></label>
                        <input id="admin_pgw_whsec" name="webhook_secret" type="password" class="admin-input" maxlength="255" value="" autocomplete="new-password" placeholder="<?= ($pgwConfig['webhook_secret'] ?? '') !== '' ? 'Optional — blank keeps previous' : 'Optional' ?>">
                      </div>
                    </div>
                  </fieldset>

                  <fieldset class="admin-pgw-fieldset">
                    <legend class="admin-pgw-legend">Step 3 · Webhook URL</legend>
                    <p class="admin-pgw-hint">Provider dashboard me is URL ko webhook destination ke taur par add karein.</p>
                    <div class="admin-pgw-webhook-row">
                      <input id="admin_pgw_webhook_url" type="text" class="admin-input admin-pgw-webhook-input" readonly value="<?= h($pgwWebhookUrl) ?>">
                      <button type="button" class="admin-btn admin-btn--outline" id="adminPgwCopyWebhook" data-copy-target="admin_pgw_webhook_url">Copy</button>
                    </div>
                  </fieldset>

                  <fieldset class="admin-pgw-fieldset">
                    <legend class="admin-pgw-legend">Step 4 · Save</legend>
                    <p class="admin-pgw-hint">Live keys sirf HTTPS par use karein; credentials share na karein.</p>
                    <div class="admin-pgw-summary">
                      <span class="admin-pgw-summary__label">Active provider</span>
                      <strong class="admin-pgw-summary__val"><?= h($pgwLabel) ?></strong>
                      <span class="admin-pgw-summary__label">Mode</span>
                      <strong class="admin-pgw-summary__val"><?= h(strtoupper((string) $pgwConfig['mode'])) ?></strong>
                    </div>
                    <div class="admin-settings-crms-actions">
                      <a class="admin-btn admin-btn--outline" href="settings.php?tab=payments">Cancel</a>
                      <button type="submit" class="admin-btn admin-btn--primary">Save gateway settings</button>
                    </div>
                  </fieldset>
                </form>
              </section>
            </div>
          </div>
        </div>
        </div>

<script>
  (function () {
    document.querySelectorAll('.admin-pgw-gateway-tile').forEach(function (tile) {
      var input = tile.querySelector('.admin-pgw-gateway-input');
      if (!input) return;
      function sync() {
        document.querySelectorAll('.admin-pgw-gateway-tile').forEach(function (t) {
          var inp = t.querySelector('.admin-pgw-gateway-input');
          t.classList.toggle('is-selected', inp && inp.checked);
        });
      }
      input.addEventListener('change', sync);
      tile.addEventListener('click', function (e) {
        if (e.target !== input) {
          input.checked = true;
          sync();
        }
      });
    });
    var btn = document.getElementById('adminPgwCopyWebhook');
    var target = document.getElementById('admin_pgw_webhook_url');
    if (btn && target && navigator.clipboard && navigator.clipboard.writeText) {
      btn.addEventListener('click', function () {
        navigator.clipboard.writeText(target.value).then(function () {
          var t = btn.textContent;
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = t; }, 1600);
        });
      });
    }
  })();
</script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
