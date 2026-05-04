<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

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
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
  <style>
    body {
      margin: 0; padding: 0;
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: #f8fafc;
      min-height: 100vh;
    }
    .theme1-auth-wrap {
      display: flex;
      min-height: 100vh;
      width: 100%;
    }
    
    /* ── Left Side (Brand/Image) ── */
    .theme1-auth-left {
      flex: 1.2;
      background: 
        radial-gradient(circle at 15% 50%, rgba(249, 115, 22, 0.25), transparent 50%),
        radial-gradient(circle at 85% 30%, rgba(225, 29, 72, 0.25), transparent 50%),
        radial-gradient(circle at 50% 80%, rgba(79, 70, 229, 0.25), transparent 50%),
        #0f172a;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 60px;
      color: #fff;
      position: relative;
      overflow: hidden;
    }
    .theme1-auth-left::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.65%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');
      opacity: 0.05;
      mix-blend-mode: overlay;
      pointer-events: none;
    }
    .theme1-auth-logo {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      z-index: 1;
    }
    .theme1-auth-logo .logo-word {
      font-size: 38px;
      font-weight: 800;
      letter-spacing: 2px;
      color: #fff;
      font-family: 'Outfit', sans-serif;
    }
    .theme1-auth-content {
      z-index: 1;
      max-width: 500px;
    }
    .theme1-auth-kicker {
      font-size: 14px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #f97316;
      margin-bottom: 16px;
    }
    .theme1-auth-title {
      font-family: 'Outfit', sans-serif;
      font-size: 48px;
      line-height: 1.1;
      font-weight: 700;
      margin: 0 0 20px;
      color: #fff;
    }
    .theme1-auth-title em {
      font-style: normal;
      background: linear-gradient(135deg, #f97316, #fb923c);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .theme1-auth-desc {
      font-size: 16px;
      line-height: 1.6;
      color: #cbd5e1;
      margin: 0;
    }
    
    /* ── Right Side (Form) ── */
    .theme1-auth-right {
      flex: 1;
      max-width: 600px;
      background: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 60px 80px;
      box-shadow: -20px 0 40px rgba(0,0,0,0.05);
      z-index: 10;
      position: relative;
    }
    .theme1-auth-head {
      margin-bottom: 32px;
    }
    .theme1-auth-head h1 {
      margin: 0 0 8px;
      font-family: 'Outfit', sans-serif;
      font-size: 32px;
      color: #0f172a;
    }
    .theme1-auth-head p {
      margin: 0;
      color: #64748b;
      font-size: 15px;
    }
    
    .theme1-auth-tabs {
      display: flex;
      background: #f1f5f9;
      border-radius: 14px;
      padding: 6px;
      margin-bottom: 32px;
      position: relative;
    }
    .theme1-tab {
      flex: 1;
      border: 0;
      background: transparent;
      border-radius: 10px;
      padding: 12px;
      font-size: 15px;
      font-weight: 700;
      color: #64748b;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .theme1-tab.active {
      background: #fff;
      color: #0f172a;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    }
    
    .theme1-pane.hidden { display: none; }
    
    .theme1-form-grid {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .theme1-form-field {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .theme1-form-field label {
      font-size: 14px;
      font-weight: 600;
      color: #334155;
    }
    .theme1-field-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .theme1-inline-link {
      font-size: 13px;
      color: #f97316;
      text-decoration: none;
      font-weight: 600;
      border: 0;
      background: transparent;
      cursor: pointer;
      padding: 0;
      transition: color 0.2s;
    }
    .theme1-inline-link:hover { color: #ea580c; }
    
    .theme1-form-grid input {
      width: 100%;
      border: 1.5px solid #e2e8f0;
      border-radius: 14px;
      padding: 14px 16px;
      font-size: 15px;
      font-family: inherit;
      outline: none;
      transition: all 0.2s;
      background: #fff;
      color: #0f172a;
    }
    .theme1-form-grid input:focus {
      border-color: #f97316;
      box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
    }
    .theme1-form-grid input::placeholder { color: #94a3b8; }
    
    .theme1-login-btn {
      margin-top: 8px;
      border: 0;
      border-radius: 14px;
      padding: 16px;
      font-size: 16px;
      font-weight: 700;
      color: #fff;
      cursor: pointer;
      background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
      box-shadow: 0 10px 25px rgba(249, 115, 22, 0.25);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .theme1-login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 15px 35px rgba(249, 115, 22, 0.35);
    }
    
    .theme1-error, .theme1-success {
      margin: 0 0 20px;
      border-radius: 12px;
      padding: 14px 16px;
      font-size: 14px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .theme1-error { border: 1px solid rgba(239, 68, 68, 0.2); background: #fef2f2; color: #b91c1c; }
    .theme1-success { border: 1px solid rgba(34, 197, 94, 0.2); background: #f0fdf4; color: #15803d; }
    
    .theme1-auth-links {
      margin-top: 32px;
      font-size: 14px;
      color: #64748b;
      display: flex;
      justify-content: center;
      gap: 24px;
      flex-wrap: wrap;
    }
    .theme1-auth-links a {
      color: #64748b;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s;
    }
    .theme1-auth-links a:hover { color: #0f172a; }
    
    .theme1-verify-wrap {
      border: 1.5px dashed #cbd5e1;
      border-radius: 16px;
      padding: 24px;
      background: #f8fafc;
      margin-top: 24px;
    }
    .theme1-verify-title {
      margin: 0 0 16px;
      font-size: 14px;
      color: #334155;
      font-weight: 600;
      text-align: center;
    }
    
    /* ── Forgot Password Modal ── */
    .theme1-forgot-overlay {
      position: fixed; inset: 0; z-index: 300; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; padding: 16px;
    }
    .theme1-forgot-overlay.show { display: flex; }
    .theme1-forgot-card {
      width: min(480px, 100%); background: #fff; border-radius: 24px; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); padding: 32px; transform: scale(0.95); opacity: 0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .theme1-forgot-overlay.show .theme1-forgot-card { transform: scale(1); opacity: 1; }
    .theme1-forgot-head {
      display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;
    }
    .theme1-forgot-head h3 { margin: 0; font-family: 'Outfit', sans-serif; font-size: 24px; color: #0f172a; }
    .theme1-forgot-close { border: 0; background: #f1f5f9; width: 36px; height: 36px; border-radius: 12px; cursor: pointer; color: #64748b; font-weight: bold; transition: all 0.2s; }
    .theme1-forgot-close:hover { background: #e2e8f0; color: #0f172a; }
    .theme1-note { font-size: 14px; color: #64748b; margin: 0 0 24px; line-height: 1.5; }
    
    @media (max-width: 900px) {
      .theme1-auth-wrap { flex-direction: column; }
      .theme1-auth-left { flex: none; padding: 40px 24px; min-height: 35vh; justify-content: center; text-align: center; }
      .theme1-auth-logo { margin-bottom: 24px; justify-content: center; width: 100%; }
      .theme1-auth-content { margin: 0 auto; }
      .theme1-auth-title { font-size: 36px; }
      .theme1-auth-right { max-width: 100%; padding: 40px 24px; box-shadow: none; border-radius: 32px 32px 0 0; margin-top: -32px; }
    }
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/page-loader.php'; ?>
  <div class="theme1-auth-wrap">
    
    <!-- Left Banner -->
    <aside class="theme1-auth-left">
      <a class="theme1-auth-logo" href="index.php" aria-label="LUXE home">
        <span class="logo-mark" aria-hidden="true">
          <span class="logo-stripe" style="background:#fff"></span>
          <span class="logo-stripe" style="background:#fff"></span>
          <span class="logo-stripe" style="background:#fff"></span>
        </span>
        <span class="logo-word">LUXE</span>
      </a>
      <div class="theme1-auth-content">
        <p class="theme1-auth-kicker">Welcome to Premium</p>
        <h2 class="theme1-auth-title">Discover your next <em>signature look</em></h2>
        <p class="theme1-auth-desc">Sign in to access personalized recommendations, track orders, and manage your luxury wardrobe.</p>
      </div>
      <div style="font-size:13px; color:rgba(255,255,255,0.5);">© <?= date('Y') ?> LUXE Fashion. All rights reserved.</div>
    </aside>

    <!-- Right Form -->
    <main class="theme1-auth-right">
      <div class="theme1-auth-head">
        <h1 id="formTitle">Welcome Back</h1>
        <p id="formSubtitle">Please enter your details to sign in.</p>
      </div>

      <div class="theme1-auth-tabs" role="tablist">
        <button type="button" class="theme1-tab active" data-tab="login">Sign In</button>
        <button type="button" class="theme1-tab" data-tab="signup">Create Account</button>
      </div>

      <!-- Login Pane -->
      <div id="pane-login" class="theme1-pane">
        <?php if ($error !== ''): ?>
          <div class="theme1-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= h($error) ?>
          </div>
        <?php endif; ?>
        <form method="post" class="theme1-form-grid">
          <input type="hidden" name="redirect" value="<?= h(luxe_redirect_app_path_for_form($redirect)) ?>">
          <div class="theme1-form-field">
            <label for="email">Email Address</label>
            <input id="email" name="email" type="email" value="<?= h($email) ?>" placeholder="you@example.com" autocomplete="email" required>
          </div>
          <div class="theme1-form-field">
            <div class="theme1-field-head">
              <label for="password">Password</label>
              <button type="button" class="theme1-inline-link" id="openForgotBtn">Forgot password?</button>
            </div>
            <input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="theme1-login-btn">Sign In to LUXE</button>
        </form>
      </div>

      <!-- Signup Pane -->
      <div id="pane-signup" class="theme1-pane hidden">
        <div class="theme1-success hidden" id="signupSuccess">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <span></span>
        </div>
        <div class="theme1-error hidden" id="signupError">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span></span>
        </div>
        <form class="theme1-form-grid" id="signupForm">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div class="theme1-form-field"><label for="sfname">First Name</label><input id="sfname" name="first_name" type="text" placeholder="John" required></div>
            <div class="theme1-form-field"><label for="slname">Last Name</label><input id="slname" name="last_name" type="text" placeholder="Doe" required></div>
          </div>
          <div class="theme1-form-field"><label for="semail">Email Address</label><input id="semail" name="email" type="email" placeholder="you@example.com" required></div>
          <div class="theme1-form-field"><label for="spass">Password</label><input id="spass" name="password" type="password" placeholder="Minimum 8 characters" required></div>
          <button type="submit" class="theme1-login-btn">Create Account</button>
        </form>
        
        <div class="theme1-verify-wrap hidden" id="verifyWrap">
          <p class="theme1-verify-title">Enter Verification Code</p>
          <div class="theme1-error hidden" id="verifyError"></div>
          <form class="theme1-form-grid" id="verifyForm">
            <div class="theme1-form-field">
              <input id="scode" name="code" type="text" inputmode="numeric" maxlength="6" placeholder="6-digit code" style="text-align:center; font-size:24px; letter-spacing:8px; font-weight:700;">
            </div>
            <button type="submit" class="theme1-login-btn">Verify & Complete</button>
          </form>
        </div>
      </div>

      <div class="theme1-auth-links">
        <a href="index.php">← Back to home</a>
      </div>
    </main>
  </div>

  <!-- Forgot Password Modal -->
  <div class="theme1-forgot-overlay" id="forgotOverlay" role="dialog" aria-modal="true">
    <div class="theme1-forgot-card">
      <div class="theme1-forgot-head">
        <h3>Reset Password</h3>
        <button type="button" class="theme1-forgot-close" id="closeForgotBtn" aria-label="Close">✕</button>
      </div>
      <p class="theme1-note">Enter your registered email address and we'll send you instructions to reset your password.</p>
      <form class="theme1-form-grid" id="forgotForm">
        <div class="theme1-form-field">
          <label for="femail">Email Address</label>
          <input id="femail" type="email" placeholder="you@example.com" required>
        </div>
        <button type="submit" class="theme1-login-btn">Send Reset Link</button>
      </form>
    </div>
  </div>

  <script>
    (function () {
      const LUXE_ACT = <?= json_encode(luxe_actions_root_url(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
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
        
        var title = document.getElementById('formTitle');
        var sub = document.getElementById('formSubtitle');
        if (name === 'login') {
          title.textContent = "Welcome Back";
          sub.textContent = "Please enter your details to sign in.";
        } else {
          title.textContent = "Create Account";
          sub.textContent = "Join LUXE for exclusive perks and fast checkout.";
        }
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
        window.location.href = "login.php#forgot=" + encodeURIComponent(email);
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
          var res = await fetch(LUXE_ACT + "register-send-code.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
          });
          var data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.message || "Sign up failed");
          signupSuccess.querySelector('span').textContent = "Verification code sent. Please check your email.";
          signupSuccess.classList.remove("hidden");
          signupForm.classList.add("hidden");
          document.getElementById("verifyWrap").classList.remove("hidden");
        } catch (err) {
          signupError.querySelector('span').textContent = err.message || "Sign up failed";
          signupError.classList.remove("hidden");
        }
      });

      verifyForm.addEventListener("submit", async function (e) {
        e.preventDefault();
        verifyError.classList.add("hidden");
        try {
          var res = await fetch(LUXE_ACT + "register-verify.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ code: document.getElementById("scode").value.trim() })
          });
          var data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.message || "Verification failed");
          window.location.href = "index.php";
        } catch (err) {
          verifyError.textContent = err.message || "Verification failed";
          verifyError.classList.remove("hidden");
        }
      });
    })();
  </script>
  <script src="<?= h(luxe_theme_asset('js/page-loader.js')) ?>" defer></script>
</body>
</html>
