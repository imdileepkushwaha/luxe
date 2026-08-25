<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/signup_mail.php';
require_once __DIR__ . '/../includes/notification_mail.php';
require_once __DIR__ . '/../includes/captcha.php';

$pdo = db();
$error = '';
$success = '';
$verifyInfo = '';
$showVerifyStep = false;

$allowedCategories = ['fashion', 'electronics', 'beauty', 'home'];
$categoryUi = [
    'fashion' => ['label' => 'Fashion', 'icon' => '👕', 'hint' => 'Clothing, footwear, accessories'],
    'electronics' => ['label' => 'Electronics', 'icon' => '🎧', 'hint' => 'Mobiles, gadgets, appliances'],
    'beauty' => ['label' => 'Beauty', 'icon' => '💄', 'hint' => 'Skincare, makeup, wellness'],
    'home' => ['label' => 'Home', 'icon' => '🏠', 'hint' => 'Decor, kitchen, living'],
];
$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'business_name' => '',
    'gst_number' => '',
    'note' => '',
];
$selectedCategories = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'send_code');

    if ($action === 'verify_code') {
        $pending = $_SESSION['seller_create_verify'] ?? null;
        if (!is_array($pending) || empty($pending['form']) || empty($pending['requested_password_hash']) || empty($pending['code_hash'])) {
            $error = 'Verification session missing hai. Form dobara submit karein.';
        } else {
            $showVerifyStep = true;
            $code = preg_replace('/\D/', '', (string) ($_POST['verification_code'] ?? '')) ?? '';
            if (strlen($code) !== 6) {
                $error = 'Email me aaya 6-digit code enter karein.';
            } elseif (time() > (int) ($pending['expires_at'] ?? 0)) {
                unset($_SESSION['seller_create_verify']);
                $error = 'Verification code expire ho gaya. Naya code bhejein.';
            } else {
                $attempts = (int) ($pending['attempts'] ?? 0);
                if ($attempts >= 8) {
                    unset($_SESSION['seller_create_verify']);
                    $error = 'Bahut galat attempts ho gaye. Form dobara submit karein.';
                } elseif (!hash_equals((string) $pending['code_hash'], luxe_signup_code_hash($code))) {
                    $_SESSION['seller_create_verify']['attempts'] = $attempts + 1;
                    $error = 'Invalid verification code.';
                } else {
                    $pendingForm = is_array($pending['form']) ? $pending['form'] : [];
                    $pendingCategories = is_array($pending['selected_categories'] ?? null) ? $pending['selected_categories'] : [];
                    $email = (string) ($pendingForm['email'] ?? '');

                    $pendingSt = $pdo->prepare("SELECT id FROM seller_create_requests WHERE email = ? AND status = 'pending' LIMIT 1");
                    $pendingSt->execute([$email]);
                    if ($pendingSt->fetch()) {
                        $error = 'Is email ki request already pending hai. Admin review ka wait karein.';
                    } else {
                        $existingSellerSt = $pdo->prepare('SELECT id FROM seller_users WHERE email = ? LIMIT 1');
                        $existingSellerSt->execute([$email]);
                        if ($existingSellerSt->fetch()) {
                            $error = 'Is email ka seller account pehle se available hai. Login karein.';
                        } else {
                            $insert = $pdo->prepare(
                                'INSERT INTO seller_create_requests (
                                    full_name, email, phone, requested_password_hash, password_confirmed_at,
                                    requested_categories, note, business_name, gst_number, status
                                 ) VALUES (
                                    ?, ?, ?, ?, ?,
                                    ?, ?, ?, ?, ?
                                 )'
                            );
                            $passwordConfirmedAt = !empty($pending['password_confirmed_at'])
                                ? date('Y-m-d H:i:s', (int) $pending['password_confirmed_at'])
                                : date('Y-m-d H:i:s');
                            $insert->execute([
                                (string) ($pendingForm['full_name'] ?? ''),
                                $email,
                                (string) ($pendingForm['phone'] ?? ''),
                                (string) $pending['requested_password_hash'],
                                $passwordConfirmedAt,
                                implode(',', $pendingCategories),
                                (string) ($pendingForm['note'] ?? ''),
                                (string) ($pendingForm['business_name'] ?? ''),
                                (string) ($pendingForm['gst_number'] ?? ''),
                                'pending',
                            ]);

                            luxe_send_welcome_email(
                                $email,
                                (string) ($pendingForm['full_name'] ?? ''),
                                'seller'
                            );
                            unset($_SESSION['seller_create_verify']);
                            $success = 'Registration request submit ho gayi. Admin approval ke baad seller panel login karke KYC + bank details fill karein.';
                            foreach ($form as $key => $_) {
                                $form[$key] = '';
                            }
                            $selectedCategories = [];
                            $showVerifyStep = false;
                        }
                    }
                }
            }
        }
    } else {
        $pending = $_SESSION['seller_create_verify'] ?? null;
        if ($action === 'resend_code') {
            if (!is_array($pending) || empty($pending['form']['email'])) {
                $error = 'Verification session missing hai. Form dobara submit karein.';
            } elseif (time() - (int) ($pending['sent_at'] ?? 0) < 60) {
                $error = 'Naya code bhejne se pehle 1 minute wait karein.';
            } else {
                $email = (string) $pending['form']['email'];
                $code = luxe_signup_otp_code();
                $send = luxe_deliver_verification_code_email(
                    $email,
                    'Verify your seller registration email',
                    $code,
                    'Use this code to submit your seller onboarding request on LUXE.'
                );
                if (!$send['ok']) {
                    $error = 'Could not send verification code. SMTP settings check karein.';
                } else {
                    $_SESSION['seller_create_verify']['code_hash'] = luxe_signup_code_hash($code);
                    $_SESSION['seller_create_verify']['expires_at'] = time() + 900;
                    $_SESSION['seller_create_verify']['sent_at'] = time();
                    $_SESSION['seller_create_verify']['attempts'] = 0;
                    $verifyInfo = 'Naya 6-digit code aapke email par bhej diya gaya hai.';
                    if (!empty($send['dev_code'])) {
                        $verifyInfo .= ' Dev code: ' . (string) $send['dev_code'];
                    }
                }
                $showVerifyStep = true;
            }
        } else {
            foreach ($form as $key => $_) {
                $form[$key] = trim((string) ($_POST[$key] ?? ''));
            }
            $password = (string) ($_POST['password'] ?? '');
            $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
            $form['email'] = strtolower($form['email']);
            $form['gst_number'] = strtoupper($form['gst_number']);

            $categoriesIn = $_POST['categories'] ?? [];
            if (is_array($categoriesIn)) {
                foreach ($categoriesIn as $cat) {
                    $value = strtolower(trim((string) $cat));
                    if (in_array($value, $allowedCategories, true)) {
                        $selectedCategories[] = $value;
                    }
                }
            }
            $selectedCategories = array_values(array_unique($selectedCategories));

            $captchaError = luxe_captcha_require_form('luxe-captcha-register');
            if ($captchaError !== '') {
                $error = $captchaError;
            } elseif (mb_strlen($form['full_name']) < 3) {
                $error = 'Full name kam se kam 3 characters ka hona chahiye.';
            } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Valid email address enter karein.';
            } elseif (!preg_match('/^\+?[0-9 ]{10,15}$/', $form['phone'])) {
                $error = 'Phone number 10-15 digits me enter karein.';
            } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $error = 'Password minimum 8 characters ka ho aur letters + numbers dono hon.';
            } elseif ($passwordConfirm === '') {
                $error = 'Confirm password enter karein.';
            } elseif (!hash_equals($password, $passwordConfirm)) {
                $error = 'Password aur confirm password match nahi kar rahe.';
            } elseif (mb_strlen($form['business_name']) < 3) {
                $error = 'Business name required hai.';
            } elseif ($form['gst_number'] !== '' && !preg_match('/^[0-9A-Z]{15}$/', $form['gst_number'])) {
                $error = 'GST number 15 characters ka valid format me hona chahiye.';
            } elseif ($selectedCategories === []) {
                $error = 'At least ek selling category select karein.';
            } else {
                $pendingSt = $pdo->prepare("SELECT id FROM seller_create_requests WHERE email = ? AND status = 'pending' LIMIT 1");
                $pendingSt->execute([$form['email']]);
                if ($pendingSt->fetch()) {
                    $error = 'Is email ki request already pending hai. Admin review ka wait karein.';
                } else {
                    $existingSellerSt = $pdo->prepare('SELECT id FROM seller_users WHERE email = ? LIMIT 1');
                    $existingSellerSt->execute([$form['email']]);
                    if ($existingSellerSt->fetch()) {
                        $error = 'Is email ka seller account pehle se available hai. Login karein.';
                    } else {
                        $code = luxe_signup_otp_code();
                        $send = luxe_deliver_verification_code_email(
                            $form['email'],
                            'Verify your seller registration email',
                            $code,
                            'Use this code to submit your seller onboarding request on LUXE.'
                        );
                        if (!$send['ok']) {
                            $error = 'Could not send verification code. SMTP settings check karein.';
                        } else {
                            $_SESSION['seller_create_verify'] = [
                                'form' => [
                                    'full_name' => $form['full_name'],
                                    'email' => $form['email'],
                                    'phone' => $form['phone'],
                                    'business_name' => $form['business_name'],
                                    'gst_number' => $form['gst_number'],
                                    'note' => $form['note'],
                                ],
                                'requested_password_hash' => password_hash($password, PASSWORD_DEFAULT),
                                'password_confirmed_at' => time(),
                                'selected_categories' => $selectedCategories,
                                'code_hash' => luxe_signup_code_hash($code),
                                'expires_at' => time() + 900,
                                'sent_at' => time(),
                                'attempts' => 0,
                            ];
                            $verifyInfo = '6-digit verification code ' . $form['email'] . ' par bhej diya gaya hai.';
                            if (!empty($send['dev_code'])) {
                                $verifyInfo .= ' Dev code: ' . (string) $send['dev_code'];
                            }
                            $showVerifyStep = true;
                        }
                    }
                }
            }
        }
    }
}

$pending = $_SESSION['seller_create_verify'] ?? null;
if (is_array($pending)) {
    $pendingForm = is_array($pending['form'] ?? null) ? $pending['form'] : [];
    if ($pendingForm !== []) {
        foreach (['full_name', 'email', 'phone', 'business_name', 'gst_number', 'note'] as $k) {
            if (isset($pendingForm[$k])) {
                $form[$k] = (string) $pendingForm[$k];
            }
        }
    }
    $selectedCategories = is_array($pending['selected_categories'] ?? null) ? array_values(array_unique($pending['selected_categories'])) : $selectedCategories;
    if ($success === '') {
        $showVerifyStep = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../admin/partials/theme-head-script.php'; ?>
  <title>Seller Registration - LUXE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../admin/css/admin.css">
  <link rel="stylesheet" href="css/seller.css">
  <?php require __DIR__ . '/../includes/partials/captcha_assets.php'; ?>
  <style>
    .seller-category-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:12px}
    .seller-category-card{position:relative;display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1px solid #e6e8ef;border-radius:12px;background:#fff;cursor:pointer;transition:all .15s ease}
    .seller-category-card:hover{border-color:#cfd4e2;box-shadow:0 2px 8px rgba(17,24,39,.06)}
    .seller-category-card input{
      position:absolute;
      width:1px;
      height:1px;
      margin:-1px;
      padding:0;
      border:0;
      clip:rect(0 0 0 0);
      overflow:hidden;
      white-space:nowrap;
      pointer-events:none
    }
    .seller-category-card__check{margin-top:2px;width:16px;height:16px;border:1.5px solid #c4cbd8;border-radius:4px;display:inline-block;flex:0 0 auto;background:#fff;transition:all .15s ease}
    .seller-category-card__icon{font-size:17px;line-height:1}
    .seller-category-card__label{display:block;font-weight:600;color:#111827}
    .seller-category-card__hint{display:block;margin-top:2px;font-size:12px;color:#6b7280}
    .seller-category-card:has(input:checked){border-color:#ef4444;background:#fff5f5;box-shadow:0 0 0 2px rgba(239,68,68,.14)}
    .seller-category-card:has(input:checked) .seller-category-card__check{border-color:#ef4444;background:linear-gradient(135deg,#ef4444,#dc2626)}
    @media (max-width: 640px){.seller-category-grid{grid-template-columns:1fr}}
    .seller-form-row2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 14px}
    @media (max-width: 640px){.seller-form-row2{grid-template-columns:1fr;gap:0}}
    .seller-form-field{min-width:0}
  </style>
</head>
<body class="seller-login admin-app--merchant">
  <main class="seller-login-shell seller-login-shell--wide">
    <div class="seller-login-card">
      <div class="seller-login-card__brand">
        <div class="admin-sidebar__logo seller-login-card__logo" aria-hidden="true"><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--a"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--b"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--c"></span></div>
      <div>
        <div class="admin-sidebar__title">LUXE</div>
        <div class="admin-sidebar__subtitle">Seller onboarding</div>
      </div>
    </div>
    <h1>Seller Registration</h1>
    <p>Yeh basic onboarding request hai. Approval ke baad seller login active hoga, fir aap bank details aur business address/proof panel me add kar sakte hain.</p>

    <?php if ($error !== ''): ?>
      <div class="err"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
      <div class="admin-del-flash admin-del-flash--ok" style="margin-bottom:14px"><?= h($success) ?></div>
    <?php endif; ?>
    <?php if ($verifyInfo !== ''): ?>
      <div class="admin-del-flash admin-del-flash--ok" style="margin-bottom:14px"><?= h($verifyInfo) ?></div>
    <?php endif; ?>

    <form method="post">
      <h3 style="margin:14px 0 8px">Account details</h3>
      <label for="full_name">Full name</label>
      <input id="full_name" name="full_name" required minlength="3" value="<?= h($form['full_name']) ?>" placeholder="Seller name">

      <div class="seller-form-row2">
        <div class="seller-form-field">
          <label for="email">Email</label>
          <input id="email" type="email" name="email" required value="<?= h($form['email']) ?>" placeholder="seller@example.com">
        </div>
        <div class="seller-form-field">
          <label for="phone">Phone</label>
          <input id="phone" name="phone" required pattern="^\+?[0-9 ]{10,15}$" value="<?= h($form['phone']) ?>" placeholder="+91 9876543210">
        </div>
      </div>

      <div class="seller-form-row2">
        <div class="seller-form-field">
          <label for="password">Set password</label>
          <div class="seller-password-wrap">
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Min 8 chars (letters + numbers)">
            <?php require __DIR__ . '/partials/password-toggle-button.php'; ?>
          </div>
        </div>
        <div class="seller-form-field">
          <label for="password_confirm">Confirm password</label>
          <div class="seller-password-wrap">
            <input id="password_confirm" type="password" name="password_confirm" required minlength="8" autocomplete="new-password" placeholder="Repeat password">
            <?php require __DIR__ . '/partials/password-toggle-button.php'; ?>
          </div>
        </div>
      </div>

      <h3 style="margin:18px 0 8px">Business details</h3>
      <div class="seller-form-row2">
        <div class="seller-form-field">
          <label for="business_name">Business name</label>
          <input id="business_name" name="business_name" required value="<?= h($form['business_name']) ?>" placeholder="Your brand / company name">
        </div>
        <div class="seller-form-field">
          <label for="gst_number">GST number (optional)</label>
          <input id="gst_number" name="gst_number" maxlength="15" value="<?= h($form['gst_number']) ?>" placeholder="22AAAAA0000A1Z5">
        </div>
      </div>

      <label>Requested categories</label>
      <div class="seller-category-grid">
        <?php foreach ($allowedCategories as $cat): ?>
          <?php $meta = $categoryUi[$cat] ?? ['label' => ucfirst($cat), 'icon' => '📦', 'hint' => '']; ?>
          <label class="seller-category-card">
            <input type="checkbox" name="categories[]" value="<?= h($cat) ?>" <?= in_array($cat, $selectedCategories, true) ? 'checked' : '' ?>>
            <span class="seller-category-card__check" aria-hidden="true"></span>
            <span>
              <span class="seller-category-card__label">
                <span class="seller-category-card__icon"><?= h((string) $meta['icon']) ?></span>
                <?= h((string) $meta['label']) ?>
              </span>
              <span class="seller-category-card__hint"><?= h((string) $meta['hint']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>

      <label for="note">Additional note (optional)</label>
      <input id="note" name="note" value="<?= h($form['note']) ?>" placeholder="Store type, expected products, etc.">

      <?php $captchaElementId = 'luxe-captcha-register'; require __DIR__ . '/../includes/partials/captcha_widget.php'; ?>

      <input type="hidden" name="action" value="send_code">
      <button type="submit">Send verification code</button>
    </form>

    <?php if ($showVerifyStep): ?>
    <form method="post" style="margin-top:14px;border-top:1px solid #e5e7eb;padding-top:14px">
      <h3 style="margin:0 0 8px">Email verification</h3>
      <p class="hint" style="margin-bottom:10px">Inbox me aaya 6-digit code enter karke request confirm karein.</p>
      <label for="verification_code">Verification code</label>
      <input id="verification_code" name="verification_code" required inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" autocomplete="one-time-code">
      <input type="hidden" name="action" value="verify_code">
      <button type="submit">Verify and submit request</button>
    </form>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="action" value="resend_code">
      <button type="submit" class="admin-btn admin-btn--ghost">Resend code</button>
    </form>
    <?php endif; ?>

    <div class="hint"><a href="login.php">Back to seller login</a></div>
    </div>
  </main>
  <script src="js/password-toggle.js?v=2"></script>
  <script>
    (function () {
      var form = document.querySelector('form input[name="action"][value="send_code"]')?.closest('form');
      if (!form) return;
      form.addEventListener('submit', function (e) {
        var pass = document.getElementById('password');
        var confirm = document.getElementById('password_confirm');
        if (!pass || !confirm) return;
        if (pass.value !== confirm.value) {
          e.preventDefault();
          alert('Password aur confirm password match nahi kar rahe.');
          confirm.focus();
        }
      });
    })();
  </script>
</body>
</html>
