<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$user = auth_user($pdo);
if (!$user) {
    header('Location: login.php?redirect=' . rawurlencode('settings.php'));
    exit;
}

$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $item) {
    $cartCount += (int) ($item['qty'] ?? 1);
}
$fullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($fullName === '') { $fullName = 'Member'; }
$initial = strtoupper(substr((string) ($user['first_name'] ?? $fullName), 0, 1));
$isLoggedIn = true;
$userInitials = $initial;
$userName = $fullName;
$userEmail = trim((string) ($user['email'] ?? ''));
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('settings.php');
$theme1HeaderCategories = ["Men's Fashion", "Women's Fashion", "Kid's Fashion", 'Footwear'];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = $theme1HeaderCategories;
$memberSince = 'Recently joined';
$createdAt = (string) ($user['created_at'] ?? '');
if ($createdAt !== '' && strtotime($createdAt) !== false) {
    $memberSince = 'Member since ' . date('M Y', strtotime($createdAt));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LUXE – Account Settings</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="profile-page-wrap">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="profile-shell container" aria-label="Account settings">

      <!-- Sidebar (same as profile.php) -->
      <?php require __DIR__ . '/partials/profile-sidebar.php'; ?>

      <!-- Main content -->
      <article class="profile-main">
        <div class="profile-main-head">
          <h2>⚙️ Account Settings</h2>
          <p>Manage your account security, login sessions, and data privacy.</p>
        </div>
        
        <div class="t2-settings-grid">

          <!-- Security Card -->
          <div class="t2-settings-card">
            <div class="t2-settings-card-header">
              <div class="t2-settings-card-icon t2-settings-icon--security">🔐</div>
              <div class="t2-settings-card-title">
                <h2>Security & Access</h2>
                <p>Manage your password and session security</p>
              </div>
            </div>
            <div class="t2-settings-card-body">
              <div class="t2-settings-row">
                <div class="t2-settings-row-info">
                  <strong>Password</strong>
                  <span>Keep your account secure with a strong password.</span>
                </div>
                <button type="button" class="t2-settings-btn t2-settings-btn--primary" id="openChangePwd">
                  🔑 Change Password
                </button>
              </div>
              <div class="t2-settings-row">
                <div class="t2-settings-row-info">
                  <strong>Active Sessions</strong>
                  <span>Currently logged in on this device.</span>
                </div>
                <a href="<?= h(luxe_action_href('logout.php?redirect=' . rawurlencode('login.php'))) ?>" class="t2-settings-btn t2-settings-btn--secondary">
                  🚪 Logout
                </a>
              </div>
            </div>
          </div>

          <!-- Danger Zone Card -->
          <div class="t2-settings-card">
            <div class="t2-settings-card-header">
              <div class="t2-settings-card-icon t2-settings-icon--danger">⚠️</div>
              <div class="t2-settings-card-title">
                <h2>Danger Zone</h2>
                <p>Irreversible actions for your account</p>
              </div>
            </div>
            <div class="t2-settings-card-body">
              <div class="t2-settings-row">
                <div class="t2-settings-row-info">
                  <strong>Delete Account</strong>
                  <span>Permanently remove your data. This cannot be undone.</span>
                </div>
                <button type="button" class="t2-settings-btn t2-settings-btn--danger" id="openDeleteAccount">
                  🗑️ Delete Account
                </button>
              </div>
            </div>
          </div>

        </div><!-- /.t2-settings-grid -->
      </article><!-- /.profile-main -->

    </section><!-- /.profile-shell -->
  </main>

  <!-- ── Change Password Modal ── -->
  <div class="st-modal-overlay" id="changePwdModal" role="dialog" aria-modal="true" aria-labelledby="cpwdTitle">
    <div class="t2-settings-modal">
      <div class="t2-settings-modal-header">
        <div class="t2-settings-modal-title">
          <h3 id="cpwdTitle">Change Password</h3>
          <p>Choose a secure password for your LUXE account.</p>
        </div>
        <button class="st-modal-close" id="closeCpwd" aria-label="Close">✕</button>
      </div>
      <form id="changePwdForm">
        <div class="st-modal-fields">
          <div class="st-field">
            <label for="cpCurrent">Current Password</label>
            <input id="cpCurrent" type="password" placeholder="Enter current password" required>
          </div>
          <div class="st-field">
            <label for="cpNew">New Password</label>
            <input id="cpNew" type="password" minlength="8" placeholder="At least 8 characters" required>
          </div>
          <div class="st-field">
            <label for="cpConfirm">Confirm New Password</label>
            <input id="cpConfirm" type="password" minlength="8" placeholder="Repeat new password" required>
          </div>
        </div>
        <div id="cpwdMsg" class="st-msg"></div>
        <div class="st-modal-actions">
          <button type="button" class="t2-settings-btn t2-settings-btn--secondary" id="closeCpwd2">Cancel</button>
          <button type="submit" class="t2-settings-btn t2-settings-btn--primary" id="cpwdSubmitBtn">Update Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Delete Account Modal ── -->
  <div class="st-modal-overlay" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="delTitle">
    <div class="t2-settings-modal">
      <div class="t2-settings-modal-header">
        <div class="t2-settings-modal-title">
          <h3 id="delTitle">Delete Account</h3>
          <p>We're sorry to see you go. This action is permanent.</p>
        </div>
        <button class="st-modal-close" id="closeDelModal" aria-label="Close">✕</button>
      </div>

      <!-- Step 1: Warning -->
      <div class="st-delete-step is-active" id="delStep1">
        <div class="st-warning-box">
          <strong>⚠️ Account Deletion Impact:</strong>
          <ul class="st-delete-list">
            <li>Permanent loss of order history</li>
            <li>Saved addresses and preferences</li>
            <li>Wishlist and loyalty points</li>
            <li>Reviews and ratings</li>
          </ul>
        </div>
        <p style="font-size:13px;color:#64748b;margin:0 0 20px;line-height:1.6">Are you sure? This action cannot be reversed once completed.</p>
        <div class="st-modal-actions">
          <button type="button" class="t2-settings-btn t2-settings-btn--secondary" id="closeDelModal2">Cancel</button>
          <button type="button" class="t2-settings-btn t2-settings-btn--primary" id="delNextBtn">Continue</button>
        </div>
      </div>

      <!-- Step 2: Confirm -->
      <div class="st-delete-step" id="delStep2">
        <p style="font-size:13px;color:#64748b;margin:0 0 20px;line-height:1.6">Submit a deletion request. Your account will be deactivated immediately and scheduled for permanent removal.</p>
        <div id="delMsg" class="st-msg"></div>
        <div class="st-modal-actions">
          <button type="button" class="t2-settings-btn t2-settings-btn--secondary" id="delBackBtn">Back</button>
          <button type="button" class="t2-settings-btn t2-settings-btn--primary" id="delConfirmBtn">Delete My Account</button>
        </div>
      </div>

    </div>
  </div>

  <?php require __DIR__ . '/partials/footer.php'; ?>

  <script>
  (function () {
    const LUXE_ACT = <?= json_encode(luxe_actions_root_url(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
    /* ─── helpers ─── */
    function openModal(id)  { document.getElementById(id).classList.add('is-open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('is-open'); }
    function setMsg(el, txt, ok) {
      el.textContent = txt;
      el.className = 'st-msg ' + (ok ? 'is-success' : 'is-error');
    }

    /* ─── Change Password ─── */
    document.getElementById('openChangePwd').addEventListener('click', function () {
      document.getElementById('changePwdForm').reset();
      document.getElementById('cpwdMsg').className = 'st-msg';
      openModal('changePwdModal');
    });
    ['closeCpwd', 'closeCpwd2'].forEach(function (id) {
      document.getElementById(id).addEventListener('click', function () { closeModal('changePwdModal'); });
    });
    document.getElementById('changePwdModal').addEventListener('click', function (e) {
      if (e.target === this) closeModal('changePwdModal');
    });

    document.getElementById('changePwdForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      var btn  = document.getElementById('cpwdSubmitBtn');
      var msg  = document.getElementById('cpwdMsg');
      var cur  = document.getElementById('cpCurrent').value;
      var nxt  = document.getElementById('cpNew').value;
      var cfm  = document.getElementById('cpConfirm').value;
      if (nxt !== cfm) { setMsg(msg, '❌ New passwords do not match.', false); return; }
      btn.disabled = true; btn.textContent = 'Updating…';
      try {
        var res  = await fetch(LUXE_ACT + 'change-password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ current_password: cur, new_password: nxt })
        });
        var data = await res.json();
        setMsg(msg, (data.ok ? '✅ ' : '❌ ') + (data.message || (data.ok ? 'Password updated!' : 'Could not update.')), !!data.ok);
        if (data.ok) { this.reset(); setTimeout(function () { closeModal('changePwdModal'); }, 1800); }
      } catch (_) { setMsg(msg, '❌ Network error. Please try again.', false); }
      btn.disabled = false; btn.textContent = '🔐 Update Password';
    });

    /* ─── Delete Account ─── */
    document.getElementById('openDeleteAccount').addEventListener('click', function () {
      document.getElementById('delStep1').classList.add('is-active');
      document.getElementById('delStep2').classList.remove('is-active');
      document.getElementById('delMsg').className = 'st-msg';
      openModal('deleteModal');
    });
    ['closeDelModal', 'closeDelModal2'].forEach(function (id) {
      document.getElementById(id).addEventListener('click', function () { closeModal('deleteModal'); });
    });
    document.getElementById('deleteModal').addEventListener('click', function (e) {
      if (e.target === this) closeModal('deleteModal');
    });
    document.getElementById('delNextBtn').addEventListener('click', function () {
      document.getElementById('delStep1').classList.remove('is-active');
      document.getElementById('delStep2').classList.add('is-active');
    });
    document.getElementById('delBackBtn').addEventListener('click', function () {
      document.getElementById('delStep2').classList.remove('is-active');
      document.getElementById('delStep1').classList.add('is-active');
    });

    document.getElementById('delConfirmBtn').addEventListener('click', async function () {
      var btn = this;
      var msg = document.getElementById('delMsg');
      btn.disabled = true; btn.textContent = 'Submitting…';
      try {
        var res  = await fetch(LUXE_ACT + 'request-account-deletion.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: '{}'
        });
        var data = await res.json();
        if (data.ok) {
          setMsg(msg, '✅ ' + (data.message || 'Request submitted.'), true);
          setTimeout(function () { window.location.href = 'index.php'; }, 2200);
        } else {
          setMsg(msg, '❌ ' + (data.message || 'Could not submit request.'), false);
          btn.disabled = false; btn.textContent = '🗑️ Submit deletion request';
        }
      } catch (_) {
        setMsg(msg, '❌ Network error. Please try again.', false);
        btn.disabled = false; btn.textContent = '🗑️ Submit deletion request';
      }
    });
  })();
  </script>
</body>
</html>
