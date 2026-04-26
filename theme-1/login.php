<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
if (auth_user($pdo)) {
    header('Location: ../index.php');
    exit;
}

$redirect = '../theme-1/index.php';
if (isset($_GET['redirect'])) {
    $r = trim((string) $_GET['redirect']);
    if ($r !== '' && !preg_match('#^https?://#i', $r) && strpos($r, '..') === false) {
        $redirect = '../' . ltrim($r, '/');
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
        $redirect = '../' . ltrim($postedRedirect, '/');
    }

    if ($email === '' || $password === '') {
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LUXE Theme 1 - Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700&family=Jost:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .theme1-auth-wrap { min-height: 100vh; display: grid; place-items: center; padding: 36px 14px; background: linear-gradient(180deg, #f5f8fd 0%, #edf2f9 100%); }
    .theme1-auth-card { width: min(980px, 100%); border-radius: 20px; border: 1px solid rgba(148, 163, 184, 0.22); background: #fff; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12); overflow: hidden; display: grid; grid-template-columns: 1.1fr 1fr; }
    .theme1-auth-left { padding: 40px; background: linear-gradient(145deg, #ffedd5, #e0f2fe); }
    .theme1-auth-logo { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; margin-bottom: 18px; }
    .theme1-auth-logo .logo-word { font-size: 32px; color: #111827; }
    .theme1-auth-kicker { color: #475569; font-size: 14px; margin: 0 0 8px; }
    .theme1-auth-title { font-family: Jost, sans-serif; font-size: 42px; line-height: 1.08; margin: 0; color: #0f172a; }
    .theme1-auth-title em { color: #ea580c; font-style: normal; }
    .theme1-auth-desc { color: #475569; margin: 14px 0 0; max-width: 390px; }
    .theme1-auth-right { padding: 40px 34px; }
    .theme1-auth-head { margin: 0 0 18px; }
    .theme1-auth-head h1 { margin: 0 0 6px; font-family: Jost, sans-serif; font-size: 30px; }
    .theme1-auth-head p { margin: 0; color: #64748b; font-size: 14px; }
    .theme1-auth-tabs { display: grid; grid-template-columns: 1fr 1fr; background: #f1f5f9; border-radius: 12px; padding: 4px; margin: 0 0 16px; }
    .theme1-tab { border: 0; background: transparent; border-radius: 9px; padding: 9px 10px; font-weight: 700; color: #475569; cursor: pointer; font-family: Jost, sans-serif; }
    .theme1-tab.active { background: #fff; color: #0f172a; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08); }
    .theme1-pane.hidden { display: none; }
    .theme1-form-grid { display: grid; gap: 12px; }
    .theme1-form-grid label { font-size: 13px; font-weight: 600; color: #334155; }
    .theme1-field-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .theme1-inline-link { font-size: 12px; color: #ea580c; text-decoration: none; font-weight: 600; white-space: nowrap; border: 0; background: transparent; cursor: pointer; }
    .theme1-form-grid input { width: 100%; border: 1px solid #dbe2ea; border-radius: 12px; padding: 12px 14px; font-size: 14px; font-family: inherit; outline: none; transition: border-color .2s, box-shadow .2s; }
    .theme1-form-grid input:focus { border-color: rgba(249, 115, 22, .5); box-shadow: 0 0 0 3px rgba(249, 115, 22, .14); }
    .theme1-login-btn { margin-top: 4px; border: 0; border-radius: 12px; padding: 12px 16px; font-family: Jost, sans-serif; font-weight: 700; color: #fff; cursor: pointer; background: linear-gradient(180deg, #ff9339 0%, #ff7a1a 45%, #ea580c 100%); }
    .theme1-error { margin: 0 0 12px; border: 1px solid #fecaca; background: #fff1f2; color: #be123c; border-radius: 10px; padding: 10px 12px; font-size: 13px; }
    .theme1-success { margin: 0 0 12px; border: 1px solid #bbf7d0; background: #f0fdf4; color: #15803d; border-radius: 10px; padding: 10px 12px; font-size: 13px; }
    .theme1-auth-links { margin-top: 14px; font-size: 13px; color: #475569; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .theme1-auth-links a { color: #ea580c; text-decoration: none; font-weight: 600; }
    .theme1-verify-wrap { border: 1px dashed #cbd5e1; border-radius: 12px; padding: 12px; background: #f8fafc; margin-top: 12px; }
    .theme1-verify-title { margin: 0 0 8px; font-size: 13px; color: #334155; font-weight: 600; }
    .theme1-forgot-overlay { position: fixed; inset: 0; z-index: 300; background: rgba(15, 23, 42, 0.45); display: none; align-items: center; justify-content: center; padding: 16px; }
    .theme1-forgot-overlay.show { display: flex; }
    .theme1-forgot-card { width: min(460px, 100%); background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22); padding: 20px; }
    .theme1-forgot-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 8px; }
    .theme1-forgot-head h3 { margin: 0; font-family: Jost, sans-serif; }
    .theme1-forgot-close { border: 0; background: #f1f5f9; width: 34px; height: 34px; border-radius: 10px; cursor: pointer; }
    .theme1-note { font-size: 12px; color: #64748b; margin: 8px 0 0; }
    @media (max-width: 860px) {
      .theme1-auth-card { grid-template-columns: 1fr; }
      .theme1-auth-left, .theme1-auth-right { padding: 28px 20px; }
      .theme1-auth-title { font-size: 34px; }
    }
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/header.php'; ?>
  <div class="theme1-auth-wrap">
    <section class="theme1-auth-card" aria-label="Login">
      <aside class="theme1-auth-left">
        <a class="theme1-auth-logo" href="../index.php" aria-label="LUXE home">
          <span class="logo-mark" aria-hidden="true"><span class="logo-stripe"></span><span class="logo-stripe"></span><span class="logo-stripe"></span></span>
          <span class="logo-word">LUXE</span>
        </a>
        <p class="theme1-auth-kicker">Welcome Back</p>
        <h2 class="theme1-auth-title">Sign in and continue your <em>shopping story</em></h2>
        <p class="theme1-auth-desc">Track orders, manage wishlist and access your personalized product picks with one click.</p>
      </aside>

      <div class="theme1-auth-right">
        <div class="theme1-auth-head">
          <h1>Login</h1>
          <p>Use your registered email and password</p>
        </div>

        <div class="theme1-auth-tabs" role="tablist">
          <button type="button" class="theme1-tab active" data-tab="login">Sign In</button>
          <button type="button" class="theme1-tab" data-tab="signup">Create Account</button>
        </div>

        <div id="pane-login" class="theme1-pane">
          <?php if ($error !== ''): ?>
            <p class="theme1-error"><?= h($error) ?></p>
          <?php endif; ?>
          <form method="post" class="theme1-form-grid">
            <input type="hidden" name="redirect" value="<?= h(ltrim(str_replace('../', '', $redirect), '/')) ?>">
            <div>
              <label for="email">Email Address</label>
              <input id="email" name="email" type="email" value="<?= h($email) ?>" placeholder="you@example.com" autocomplete="email" required>
            </div>
            <div>
              <div class="theme1-field-head">
                <label for="password">Password</label>
                <button type="button" class="theme1-inline-link" id="openForgotBtn">Forgot password?</button>
              </div>
              <input id="password" name="password" type="password" placeholder="Enter password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="theme1-login-btn">Sign In</button>
          </form>
        </div>

        <div id="pane-signup" class="theme1-pane hidden">
          <p class="theme1-success hidden" id="signupSuccess"></p>
          <p class="theme1-error hidden" id="signupError"></p>
          <form class="theme1-form-grid" id="signupForm">
            <div><label for="sfname">First Name</label><input id="sfname" name="first_name" type="text" placeholder="Rahul" required></div>
            <div><label for="slname">Last Name</label><input id="slname" name="last_name" type="text" placeholder="Sharma" required></div>
            <div><label for="semail">Email Address</label><input id="semail" name="email" type="email" placeholder="you@example.com" required></div>
            <div><label for="spass">Password</label><input id="spass" name="password" type="password" placeholder="Minimum 8 chars" required></div>
            <button type="submit" class="theme1-login-btn">Send verification code</button>
          </form>
          <div class="theme1-verify-wrap">
            <p class="theme1-verify-title">Already got verification code?</p>
            <p class="theme1-error hidden" id="verifyError"></p>
            <form class="theme1-form-grid" id="verifyForm">
              <div><label for="scode">6-digit Code</label><input id="scode" name="code" type="text" inputmode="numeric" maxlength="6" placeholder="Enter code"></div>
              <button type="submit" class="theme1-login-btn">Verify & Create Account</button>
            </form>
          </div>
        </div>

        <div class="theme1-auth-links">
          <a href="#" id="switchSignupLink">Create new account</a>
          <a href="#" id="openForgotLink">Reset password</a>
          <a href="../index.php">Back to home</a>
          <a href="../login.php">Advanced auth page</a>
        </div>
      </div>
    </section>
  </div>

  <div class="theme1-forgot-overlay" id="forgotOverlay" role="dialog" aria-modal="true" aria-labelledby="forgotTitle">
    <div class="theme1-forgot-card">
      <div class="theme1-forgot-head">
        <h3 id="forgotTitle">Forgot password</h3>
        <button type="button" class="theme1-forgot-close" id="closeForgotBtn" aria-label="Close">✕</button>
      </div>
      <p class="theme1-note">Reset flow advanced auth page pe configured hai. Email dalke continue karein.</p>
      <form class="theme1-form-grid" id="forgotForm">
        <div><label for="femail">Email Address</label><input id="femail" type="email" placeholder="you@example.com" required></div>
        <button type="submit" class="theme1-login-btn">Continue to reset</button>
      </form>
    </div>
  </div>
  <?php require __DIR__ . '/partials/footer.php'; ?>

  <script>
    (function () {
      var tabs = document.querySelectorAll(".theme1-tab");
      var paneLogin = document.getElementById("pane-login");
      var paneSignup = document.getElementById("pane-signup");
      var switchSignupLink = document.getElementById("switchSignupLink");
      var openForgotBtn = document.getElementById("openForgotBtn");
      var openForgotLink = document.getElementById("openForgotLink");
      var forgotOverlay = document.getElementById("forgotOverlay");
      var closeForgotBtn = document.getElementById("closeForgotBtn");
      var forgotForm = document.getElementById("forgotForm");
      var signupForm = document.getElementById("signupForm");
      var verifyForm = document.getElementById("verifyForm");
      var signupError = document.getElementById("signupError");
      var signupSuccess = document.getElementById("signupSuccess");
      var verifyError = document.getElementById("verifyError");

      function showTab(name) {
        tabs.forEach(function (t) { t.classList.toggle("active", t.getAttribute("data-tab") === name); });
        paneLogin.classList.toggle("hidden", name !== "login");
        paneSignup.classList.toggle("hidden", name !== "signup");
      }

      tabs.forEach(function (tab) {
        tab.addEventListener("click", function () { showTab(tab.getAttribute("data-tab")); });
      });
      if (switchSignupLink) {
        switchSignupLink.addEventListener("click", function (e) { e.preventDefault(); showTab("signup"); });
      }

      function openForgot() { forgotOverlay.classList.add("show"); }
      function closeForgot() { forgotOverlay.classList.remove("show"); }
      if (openForgotBtn) openForgotBtn.addEventListener("click", openForgot);
      if (openForgotLink) openForgotLink.addEventListener("click", function (e) { e.preventDefault(); openForgot(); });
      if (closeForgotBtn) closeForgotBtn.addEventListener("click", closeForgot);
      forgotOverlay.addEventListener("click", function (e) {
        if (e.target === forgotOverlay) closeForgot();
      });

      forgotForm.addEventListener("submit", function (e) {
        e.preventDefault();
        var email = document.getElementById("femail").value.trim();
        window.location.href = "../login.php#forgot=" + encodeURIComponent(email);
      });

      signupForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        signupError.classList.add("hidden");
        signupSuccess.classList.add("hidden");
        var payload = {
          first_name: document.getElementById("sfname").value.trim(),
          last_name: document.getElementById("slname").value.trim(),
          email: document.getElementById("semail").value.trim(),
          password: document.getElementById("spass").value
        };
        try {
          var res = await fetch("../actions/register-send-code.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
          });
          var data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.message || "Sign up failed");
          signupSuccess.textContent = "Verification code sent. Please check your email and enter code below.";
          signupSuccess.classList.remove("hidden");
        } catch (err) {
          signupError.textContent = err.message || "Sign up failed";
          signupError.classList.remove("hidden");
        }
      });

      verifyForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        verifyError.classList.add("hidden");
        try {
          var res = await fetch("../actions/register-verify.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ code: document.getElementById("scode").value.trim() })
          });
          var data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.message || "Verification failed");
          window.location.href = "../index.php";
        } catch (err) {
          verifyError.textContent = err.message || "Verification failed";
          verifyError.classList.remove("hidden");
        }
      });
    })();
  </script>
</body>
</html>
