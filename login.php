<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (auth_user(db())) {
    header('Location: index.php');
    exit;
}

$loginRedirect = '';
if (isset($_GET['redirect'])) {
    $r = trim((string) $_GET['redirect']);
    if ($r !== '' && !preg_match('#^https?://#i', $r) && strpos($r, '..') === false) {
        $loginRedirect = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php require __DIR__ . '/includes/luxe_theme_head.php'; ?>
  <title>LUXE — Sign In to Your Account</title>
  <meta name="description" content="Sign in to your LUXE account to access exclusive deals, track orders, and enjoy a premium shopping experience." />
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/luxe.css" />
</head>
<body>

  <!-- Custom Cursor -->
  <div class="cursor-dot" id="cursorDot"></div>
  <div class="cursor-ring" id="cursorRing"></div>

  <!-- Background -->
  <div class="bg-scene">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
    <div class="grid-lines"></div>
    <div class="particles" id="particles"></div>
  </div>

  <!-- Main Wrapper -->
  <div class="page-wrapper">

    <!-- Left Panel -->
    <div class="left-panel">
      <a href="index.php" class="back-link">← Back to Store</a>

      <div class="brand">
        <a href="index.php" class="logo">LUXE</a>
        <p class="brand-tagline">Premium Shopping Redefined</p>
      </div>

      <div class="left-content">
        <h1 class="left-title">
          Welcome<br />
          <em>Back.</em>
        </h1>
        <p class="left-desc">
          Sign in to unlock exclusive member deals, track your orders in real-time, and enjoy a seamless shopping experience.
        </p>

        <div class="perks">
          <div class="perk-item">
            <div class="perk-icon">⚡</div>
            <div>
              <strong>Flash Sale Access</strong>
              <span>Members-only early bird deals</span>
            </div>
          </div>
          <div class="perk-item">
            <div class="perk-icon">📦</div>
            <div>
              <strong>Order Tracking</strong>
              <span>Real-time delivery updates</span>
            </div>
          </div>
          <div class="perk-item">
            <div class="perk-icon">🎁</div>
            <div>
              <strong>LUXE Rewards</strong>
              <span>Earn points on every purchase</span>
            </div>
          </div>
        </div>

        <!-- Floating Product Cards -->
        <div class="floating-cards">
          <div class="f-card f-card-1">
            <span class="f-emoji">👟</span>
            <div>
              <strong>AirMax Pro 2026</strong>
              <span>₹8,999 <s>₹14,500</s></span>
            </div>
            <div class="f-badge">38% OFF</div>
          </div>
          <div class="f-card f-card-2">
            <span class="f-emoji">🎧</span>
            <div>
              <strong>Sony WH-1000XM5</strong>
              <span>₹18,999 <s>₹34,990</s></span>
            </div>
            <div class="f-badge">45% OFF</div>
          </div>
        </div>
      </div>

      <div class="left-footer">
        <span>© 2026 LUXE. All rights reserved.</span>
      </div>
    </div>

    <!-- Right Panel — Login Form -->
    <div class="right-panel">
      <div class="form-card" id="formCard">

        <!-- Tabs -->
        <div class="auth-tabs">
          <button class="auth-tab active" id="tabLogin" onclick="switchTab('login')">Sign In</button>
          <button class="auth-tab" id="tabRegister" onclick="switchTab('register')">Sign Up</button>
          <div class="tab-indicator" id="tabIndicator"></div>
        </div>

        <!-- ===== LOGIN FORM ===== -->
        <div class="auth-form" id="loginForm">
          <div class="form-header">
            <h2>Sign In</h2>
            <p>Enter your credentials to continue</p>
          </div>

          <div class="social-btns">
            <button class="social-btn" id="googleBtn">
              <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#EA4335" d="M5.26 9.77A7.5 7.5 0 0 1 12 4.5c1.77 0 3.38.62 4.64 1.64l3.46-3.46A12 12 0 0 0 0 12c0 2.01.5 3.9 1.38 5.57l3.88-3.01A7.47 7.47 0 0 1 5.26 9.77z"/><path fill="#FBBC05" d="M12 4.5c1.77 0 3.38.62 4.64 1.64l3.46-3.46A12 12 0 0 0 12 0c-4.64 0-8.64 2.65-10.62 6.52l3.88 3.01A7.5 7.5 0 0 1 12 4.5z"/><path fill="#4285F4" d="M23.9 12.27c0-.79-.07-1.55-.2-2.27H12v4.3h6.68a5.7 5.7 0 0 1-2.47 3.74l3.86 3A12 12 0 0 0 24 12c0-.24 0-.49-.1-.73z"/><path fill="#34A853" d="M5.26 14.23A7.48 7.48 0 0 1 4.5 12c0-.77.13-1.52.35-2.23L1 6.76A12 12 0 0 0 0 12c0 1.94.46 3.77 1.27 5.4l3.99-3.17z"/><path fill="#EA4335" d="M12 24c3.24 0 5.96-1.07 7.95-2.9l-3.86-3a7.5 7.5 0 0 1-11.04-3.87l-3.99 3.17C3.36 21.35 7.36 24 12 24z"/></svg>
              Continue with Google
            </button>
            <button class="social-btn" id="phoneBtn">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6 6l.91-.9a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.73 16.92z"/></svg>
              Continue with Phone
            </button>
          </div>

          <div class="divider"><span>or sign in with email</span></div>

          <form id="loginFormEl" onsubmit="handleLogin(event)" novalidate>
            <div class="input-group" id="lg-email-group">
              <label for="lg-email">Email Address</label>
              <div class="input-wrap">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <input type="email" id="lg-email" placeholder="you@example.com" autocomplete="email" />
              </div>
              <span class="error-msg" id="lg-email-err"></span>
            </div>

            <div class="input-group" id="lg-pass-group">
              <div class="label-row">
                <label for="lg-pass">Password</label>
                <a href="#" class="forgot-link" id="forgotLink">Forgot password?</a>
              </div>
              <div class="input-wrap">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="lg-pass" placeholder="Enter your password" autocomplete="current-password" />
                <button type="button" class="toggle-pass" id="lgTogglePass" aria-label="Toggle password">
                  <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <span class="error-msg" id="lg-pass-err"></span>
            </div>

            <div class="remember-row">
              <label class="checkbox-label">
                <input type="checkbox" id="rememberMe" />
                <span class="checkmark"></span>
                Remember me for 30 days
              </label>
            </div>

            <button type="submit" class="submit-btn" id="loginSubmitBtn">
              <span class="btn-text">Sign In</span>
              <span class="btn-loader" id="loginLoader"></span>
              <span class="btn-arrow">→</span>
            </button>
          </form>

          <p class="switch-text">Don't have an account? <button class="text-link" onclick="switchTab('register')">Create one →</button></p>
        </div>

        <!-- ===== REGISTER FORM ===== -->
        <div class="auth-form hidden" id="registerForm">
          <div id="registerStep1">
          <div class="form-header">
            <h2>Create Account</h2>
            <p>Join LUXE and start shopping premium</p>
          </div>

          <form id="registerFormEl" onsubmit="handleRegister(event)" novalidate>
            <div class="input-row">
              <div class="input-group" id="rg-fname-group">
                <label for="rg-fname">First Name</label>
                <div class="input-wrap">
                  <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <input type="text" id="rg-fname" placeholder="Rahul" autocomplete="given-name" />
                </div>
                <span class="error-msg" id="rg-fname-err"></span>
              </div>
              <div class="input-group" id="rg-lname-group">
                <label for="rg-lname">Last Name</label>
                <div class="input-wrap">
                  <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <input type="text" id="rg-lname" placeholder="Sharma" autocomplete="family-name" />
                </div>
                <span class="error-msg" id="rg-lname-err"></span>
              </div>
            </div>

            <div class="input-group" id="rg-email-group">
              <label for="rg-email">Email Address</label>
              <div class="input-wrap">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <input type="email" id="rg-email" placeholder="you@example.com" autocomplete="email" />
              </div>
              <span class="error-msg" id="rg-email-err"></span>
            </div>

            <div class="input-group" id="rg-pass-group">
              <label for="rg-pass">Password</label>
              <div class="input-wrap">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="rg-pass" placeholder="Create a strong password" autocomplete="new-password" />
                <button type="button" class="toggle-pass" id="rgTogglePass" aria-label="Toggle password">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
              <span class="error-msg" id="rg-pass-err"></span>
              <!-- Strength meter -->
              <div class="strength-wrap" id="strengthWrap">
                <div class="strength-bar">
                  <div class="strength-fill" id="strengthFill"></div>
                </div>
                <span class="strength-label" id="strengthLabel"></span>
              </div>
            </div>

            <div class="input-group" id="rg-confirm-group">
              <label for="rg-confirm">Confirm Password</label>
              <div class="input-wrap">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <input type="password" id="rg-confirm" placeholder="Repeat your password" autocomplete="new-password" />
              </div>
              <span class="error-msg" id="rg-confirm-err"></span>
            </div>

            <div class="remember-row">
              <label class="checkbox-label">
                <input type="checkbox" id="agreeTerms" />
                <span class="checkmark"></span>
                I agree to the <a href="#" class="text-link-inline">Terms</a> & <a href="#" class="text-link-inline">Privacy Policy</a>
              </label>
            </div>

            <button type="submit" class="submit-btn" id="regSubmitBtn">
              <span class="btn-text">Send verification code</span>
              <span class="btn-loader" id="regLoader"></span>
              <span class="btn-arrow">→</span>
            </button>
          </form>
          </div>

          <div id="registerVerifyStep" class="hidden">
            <div class="form-header">
              <h2>Verify your email</h2>
              <p>Enter the 4-digit code we sent to <strong id="verifyEmailHint"></strong></p>
            </div>
            <form id="registerVerifyFormEl" onsubmit="handleRegisterVerify(event)" novalidate>
              <div class="input-group" id="rg-code-group">
                <label for="rg-code">Verification code</label>
                <p class="signup-code-hint">अभी के लिए टेस्ट कोड: <strong>1234</strong> — For now use code <strong>1234</strong></p>
                <div class="input-wrap">
                  <input type="text" id="rg-code" class="signup-code-input" inputmode="numeric" maxlength="4" pattern="[0-9]*" autocomplete="one-time-code" placeholder="1234" />
                </div>
                <span class="error-msg" id="rg-code-err"></span>
              </div>
              <button type="submit" class="submit-btn" id="verifySubmitBtn">
                <span class="btn-text">Verify &amp; create account</span>
                <span class="btn-loader" id="verifyLoader"></span>
                <span class="btn-arrow">→</span>
              </button>
            </form>
            <p class="switch-text" style="margin-top: 14px; text-align: center;">
              <button type="button" class="text-link" id="resendCodeBtn">Resend code</button>
            </p>
            <p class="switch-text" style="text-align: center;">
              <button type="button" class="text-link" onclick="registerBackToStep1()">← Edit my details</button>
            </p>
          </div>

          <p class="switch-text">Already have an account? <button class="text-link" onclick="switchTab('login')">Sign in →</button></p>
        </div>

        <!-- ===== SUCCESS STATE ===== -->
        <div class="auth-form hidden" id="successState">
          <div class="success-content">
            <div class="success-icon">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <h2 id="successTitle">Welcome back!</h2>
            <p id="successMsg">You've signed in successfully. Redirecting you to the store...</p>
            <div class="redirect-bar"><div class="redirect-fill" id="redirectFill"></div></div>
            <a href="index.php" class="submit-btn" style="display:inline-flex;text-decoration:none;justify-content:center;margin-top:8px;">
              Go to Store →
            </a>
          </div>
        </div>

        <!-- Forgot Password Overlay -->
        <div class="forgot-overlay hidden" id="forgotOverlay">
          <button class="forgot-back" id="forgotBack">← Back</button>
          <div class="form-header">
            <div class="forgot-icon">🔑</div>
            <h2>Reset Password</h2>
            <p>Enter your email and we'll send a reset link</p>
          </div>
          <form onsubmit="handleForgot(event)" novalidate>
            <div class="input-group">
              <label for="forgot-email">Email Address</label>
              <div class="input-wrap">
                <svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                <input type="email" id="forgot-email" placeholder="you@example.com" />
              </div>
            </div>
            <button type="submit" class="submit-btn">Send Reset Link →</button>
          </form>
        </div>

      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="toast" id="toast"></div>

  <script>
    window.LUXE_URLS = <?= json_encode([
        'home' => 'index.php',
        'login' => 'login.php',
        'cart' => 'cart.php',
        'product' => 'product.php',
        'orders' => 'orders.php',
        'profile' => 'profile.php',
    ], JSON_THROW_ON_ERROR) ?>;
    window.__LOGIN_REDIRECT__ = <?= json_encode($loginRedirect, JSON_THROW_ON_ERROR) ?>;
    window.__API_LOGIN__ = 'actions/login.php';
    window.__API_REGISTER_SEND__ = 'actions/register-send-code.php';
    window.__API_REGISTER_VERIFY__ = 'actions/register-verify.php';
  </script>
  <script src="script/luxe.js"></script>
</body>
</html>
