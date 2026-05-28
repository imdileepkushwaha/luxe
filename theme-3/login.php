<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/captcha.php';

$pdo = db();
if (auth_user($pdo)) {
    header('Location: ' . luxe_public_href('index.php'));
    exit;
}

$redirect = luxe_public_href('index.php');
if (isset($_GET['redirect'])) {
    $r = trim((string) $_GET['redirect']);
    if ($r !== '' && !preg_match('#^https?://#i', $r) && strpos($r, '..') === false) {
        $redirect = luxe_public_href(ltrim($r, '/'));
    }
}

$email = '';
$error = '';
$theme1HeaderCategories = ["Men's Fashion", "Women's Fashion", "Kid's Fashion"];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = 0;
$theme1FooterCategories = $theme1HeaderCategories;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $postedRedirect = trim((string) ($_POST['redirect'] ?? ''));

    if ($postedRedirect !== '' && !preg_match('#^https?://#i', $postedRedirect) && strpos($postedRedirect, '..') === false) {
        $redirect = luxe_public_href(ltrim($postedRedirect, '/'));
    }

    $captchaError = luxe_captcha_require_form('luxe-captcha-login');
    if ($captchaError !== '') {
        $error = $captchaError;
    } elseif ($email === '' || $password === '') {
        $error = 'Email aur password dono required hain.';
    } else {
        $st = $pdo->prepare('SELECT id, password_hash, email_verified_at FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($password, (string) ($row['password_hash'] ?? ''))) {
            $error = 'Invalid email ya password.';
        } elseif (empty($row['email_verified_at'])) {
            $error = 'Email verify karke fir login karein.';
        } else {
            auth_set_user((int) $row['id']);
            header('Location: ' . $redirect);
            exit;
        }
    }
}

$t2LoginBrand = 'LUXE';
$t2LoginLogoPath = '';
if (function_exists('site_brand_name') && function_exists('site_logo_path')) {
    try {
        $t2LoginBrand = site_brand_name($pdo);
        $t2LoginLogoPath = site_logo_path($pdo);
    } catch (Throwable $e) {
        $t2LoginBrand = 'LUXE';
        $t2LoginLogoPath = '';
    }
}
$t2LoginLogoUrl = $t2LoginLogoPath !== '' ? luxe_public_href(ltrim($t2LoginLogoPath, '/')) : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign in — <?= h($t2LoginBrand) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Jost:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
  <?php require __DIR__ . '/../includes/partials/captcha_assets.php'; ?>
</head>
<body class="t2-login-page">
  <?php require __DIR__ . '/partials/page-loader.php'; ?>
  <div class="t2-login-bg" aria-hidden="true"></div>

  <header class="t2-login-topbar">
    <a class="header-logo <?= $t2LoginLogoUrl !== '' ? 'header-logo--has-img' : 'header-logo--text' ?> t2-login-header-logo" href="index.php" aria-label="<?= h($t2LoginBrand) ?> home">
      <?php if ($t2LoginLogoUrl !== ''): ?>
        <img src="<?= h($t2LoginLogoUrl) ?>" alt="<?= h($t2LoginBrand) ?>" class="header-logo-img">
        <span class="logo-tagline logo-tagline--stack"><span>Fashion</span><span>Store</span></span>
      <?php else: ?>
        <span class="logo-word-row">
          <span class="logo-word"><?= h($t2LoginBrand) ?><span class="logo-dot" aria-hidden="true"></span></span>
          <span class="logo-tagline logo-tagline--stack"><span>Fashion</span><span>Store</span></span>
        </span>
      <?php endif; ?>
    </a>
    <a class="t2-login-back" href="index.php">← Back to store</a>
  </header>

  <main class="t2-login-main">
    <div class="t2-login-shell">
      <aside class="t2-login-aside" aria-hidden="true">
        <div class="t2-login-aside-bg"></div>
        <div class="t2-login-aside-inner">
          <p class="t2-login-aside-eyebrow">Member lounge</p>
          <p class="t2-login-aside-headline">Style that stays with you—online and at your door.</p>
          <ul class="t2-login-aside-list">
            <li><span class="t2-login-aside-dot"></span>Wishlists sync across visits</li>
            <li><span class="t2-login-aside-dot"></span>Orders &amp; returns in one dashboard</li>
            <li><span class="t2-login-aside-dot"></span>Checkout remembers your details</li>
          </ul>
        </div>
      </aside>

      <div class="t2-login-card">
        <div class="t2-login-intro">
        <p class="t2-login-kicker">Member access</p>
        <h1 class="t2-login-title" id="formTitle">Welcome back</h1>
        <p class="t2-login-lead" id="formSubtitle">Sign in with your email to continue shopping.</p>
        </div>

        <div class="t2-login-tabs" role="tablist">
        <button type="button" class="t2-login-tab active" data-tab="login">Sign in</button>
        <button type="button" class="t2-login-tab" data-tab="signup">Join</button>
        </div>

        <div id="pane-login" class="t2-login-pane">
        <?php if ($error !== ''): ?>
          <div class="t2-login-alert t2-login-alert--error">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
            <?= h($error) ?>
          </div>
        <?php endif; ?>
        <form method="post" class="t2-login-form">
          <input type="hidden" name="redirect" value="<?= h(luxe_redirect_app_path_for_form($redirect)) ?>">
          <label class="t2-login-field">
            <span class="t2-login-label">Email</span>
            <input id="email" name="email" type="email" value="<?= h($email) ?>" placeholder="name@email.com" autocomplete="email" required>
          </label>
          <label class="t2-login-field">
            <span class="t2-login-label-row">
              <span class="t2-login-label">Password</span>
              <button type="button" class="t2-login-textbtn" id="openForgotBtn">Forgot?</button>
            </span>
            <input id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required>
          </label>
          <?php $captchaElementId = 'luxe-captcha-login'; require __DIR__ . '/../includes/partials/captcha_widget.php'; ?>
          <button type="submit" class="t2-login-submit">Enter account</button>
        </form>
        </div>

        <div id="pane-signup" class="t2-login-pane hidden">
        <div class="t2-login-alert t2-login-alert--ok hidden" id="signupSuccess">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <span></span>
        </div>
        <div class="t2-login-alert t2-login-alert--error hidden" id="signupError">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
          <span></span>
        </div>
        <form class="t2-login-form" id="signupForm">
          <div class="t2-login-row2">
            <label class="t2-login-field">
              <span class="t2-login-label">First name</span>
              <input id="sfname" name="first_name" type="text" placeholder="Jane" required>
            </label>
            <label class="t2-login-field">
              <span class="t2-login-label">Last name</span>
              <input id="slname" name="last_name" type="text" placeholder="Doe" required>
            </label>
          </div>
          <label class="t2-login-field">
            <span class="t2-login-label">Email</span>
            <input id="semail" name="email" type="email" placeholder="name@email.com" required>
          </label>
          <label class="t2-login-field">
            <span class="t2-login-label">Password</span>
            <input id="spass" name="password" type="password" placeholder="At least 8 characters" required>
          </label>
          <?php $captchaElementId = 'luxe-captcha-register'; require __DIR__ . '/../includes/partials/captcha_widget.php'; ?>
          <button type="submit" class="t2-login-submit t2-login-submit--outline">Create member account</button>
        </form>

        <div class="t2-login-verify hidden" id="verifyWrap">
          <p class="t2-login-verify-title">Verification code</p>
          <p class="t2-login-verify-hint">Enter the 6-digit code we emailed you.</p>
          <div class="t2-login-alert t2-login-alert--error hidden" id="verifyError"></div>
          <form class="t2-login-form" id="verifyForm">
            <label class="t2-login-field">
              <input id="scode" name="code" type="text" inputmode="numeric" maxlength="6" placeholder="000000" class="t2-login-code-input">
            </label>
            <button type="submit" class="t2-login-submit">Verify & continue</button>
          </form>
        </div>
        </div>
      </div>
    </div>

    <p class="t2-login-footnote">© <?= date('Y') ?> <?= h($t2LoginBrand) ?> · Secure checkout</p>
  </main>

  <div class="t2-login-modal-overlay" id="forgotOverlay" role="dialog" aria-modal="true">
    <div class="t2-login-modal">
      <div class="t2-login-modal-head">
        <h3>Reset password</h3>
        <button type="button" class="t2-login-modal-x" id="closeForgotBtn" aria-label="Close">✕</button>
      </div>
      <p class="t2-login-modal-note">We’ll send reset instructions to your registered email.</p>
      <form class="t2-login-form" id="forgotForm">
        <label class="t2-login-field">
          <span class="t2-login-label">Email</span>
          <input id="femail" type="email" placeholder="name@email.com" required>
        </label>
        <button type="submit" class="t2-login-submit">Send email</button>
      </form>
    </div>
  </div>

  <script>
    (function () {
      const LUXE_ACT = <?= json_encode(luxe_actions_root_url(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
      var tabs = document.querySelectorAll('.t2-login-tab');
      var paneLogin = document.getElementById('pane-login');
      var paneSignup = document.getElementById('pane-signup');
      var openForgotBtn = document.getElementById('openForgotBtn');
      var forgotOverlay = document.getElementById('forgotOverlay');
      var closeForgotBtn = document.getElementById('closeForgotBtn');
      var forgotForm = document.getElementById('forgotForm');
      var signupForm = document.getElementById('signupForm');
      var verifyForm = document.getElementById('verifyForm');
      var signupError = document.getElementById('signupError');
      var signupSuccess = document.getElementById('signupSuccess');
      var verifyError = document.getElementById('verifyError');

      function showTab(name) {
        tabs.forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-tab') === name); });
        paneLogin.classList.toggle('hidden', name !== 'login');
        paneSignup.classList.toggle('hidden', name !== 'signup');
        var title = document.getElementById('formTitle');
        var sub = document.getElementById('formSubtitle');
        if (name === 'login') {
          title.textContent = 'Welcome back';
          sub.textContent = 'Sign in with your email to continue shopping.';
        } else {
          title.textContent = 'Become a member';
          sub.textContent = 'Create an account for wishlists, orders, and faster checkout.';
        }
        if (name === 'signup' && window.LuxeCaptcha) {
          setTimeout(function () { LuxeCaptcha.refreshPending(); }, 50);
        }
      }

      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () { showTab(tab.getAttribute('data-tab')); });
      });

      function openForgot() { forgotOverlay.classList.add('show'); }
      function closeForgot() { forgotOverlay.classList.remove('show'); }
      if (openForgotBtn) openForgotBtn.addEventListener('click', openForgot);
      if (closeForgotBtn) closeForgotBtn.addEventListener('click', closeForgot);
      forgotOverlay.addEventListener('click', function (e) {
        if (e.target === forgotOverlay) closeForgot();
      });

      forgotForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var email = document.getElementById('femail').value.trim();
        window.location.href = 'login.php#forgot=' + encodeURIComponent(email);
      });

      signupForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        signupError.classList.add('hidden');
        signupSuccess.classList.add('hidden');
        var payload = {
          first_name: document.getElementById('sfname').value.trim(),
          last_name: document.getElementById('slname').value.trim(),
          email: document.getElementById('semail').value.trim(),
          password: document.getElementById('spass').value
        };
        try {
          if (window.LuxeCaptcha && LuxeCaptcha.enabled()) {
            payload.captcha_token = LuxeCaptcha.requireToken('luxe-captcha-register');
            payload.captcha_scope = 'luxe-captcha-register';
          }
        } catch (captchaErr) {
          signupError.querySelector('span').textContent = captchaErr.message || 'Please complete the CAPTCHA verification.';
          signupError.classList.remove('hidden');
          return;
        }
        try {
          var res = await fetch(LUXE_ACT + 'register-send-code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          var data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.message || 'Sign up failed');
          signupSuccess.querySelector('span').textContent = 'Code sent — check your inbox.';
          signupSuccess.classList.remove('hidden');
          signupForm.classList.add('hidden');
          document.getElementById('verifyWrap').classList.remove('hidden');
        } catch (err) {
          if (window.LuxeCaptcha) LuxeCaptcha.reset('luxe-captcha-register');
          signupError.querySelector('span').textContent = err.message || 'Sign up failed';
          signupError.classList.remove('hidden');
        }
      });

      verifyForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        verifyError.classList.add('hidden');
        try {
          var res = await fetch(LUXE_ACT + 'register-verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: document.getElementById('scode').value.trim() })
          });
          var data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.message || 'Verification failed');
          window.location.href = 'index.php';
        } catch (err) {
          verifyError.textContent = err.message || 'Verification failed';
          verifyError.classList.remove('hidden');
        }
      });
    })();
  </script>
  <script src="<?= h(luxe_theme_asset('js/page-loader.js')) ?>" defer></script>
</body>
</html>
