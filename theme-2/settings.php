<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$user = auth_user($pdo);
if (!$user) {
    header('Location: login.php?redirect=' . rawurlencode('theme-1/settings.php'));
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
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('theme-1/settings.php');
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
  <link rel="stylesheet" href="css/styles.css">
  <style>
    /* ── Settings cards layout uses profile-shell grid ── */
    .st-cards {
      display: grid;
      gap: 20px;
    }
    .st-page-head {
      margin-bottom: 4px;
    }
    .st-page-head h1 {
      margin: 0 0 4px;
      font-size: 24px; font-weight: 800;
      color: #0f172a; letter-spacing: -0.03em;
    }
    .st-page-head p {
      margin: 0; font-size: 13px; color: #64748b;
    }

    /* ── Settings card ── */
    .st-card {
      background: rgba(255,255,255,0.82);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      border: 1px solid rgba(255,255,255,0.85);
      border-radius: 22px;
      box-shadow: 0 4px 20px rgba(15,23,42,0.07), 0 1px 0 rgba(255,255,255,0.9) inset;
      overflow: hidden;
    }
    .st-card-header {
      display: flex; align-items: center; gap: 16px;
      padding: 22px 26px 18px;
      border-bottom: 1px solid rgba(148,163,184,0.13);
    }
    .st-card-icon {
      width: 44px; height: 44px; border-radius: 13px;
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; flex-shrink: 0;
    }
    .st-card-icon--security { background: linear-gradient(140deg,#dbeafe,#bfdbfe); }
    .st-card-icon--danger   { background: linear-gradient(140deg,#fee2e2,#fecaca); }
    .st-card-icon--account  { background: linear-gradient(140deg,#fef3c7,#fde68a); }
    .st-card-title { flex: 1; }
    .st-card-title h2 { margin: 0 0 3px; font-size: 17px; font-weight: 800; color: #0f172a; }
    .st-card-title p  { margin: 0; font-size: 13px; color: #64748b; }

    .st-card-body { padding: 22px 26px 24px; }

    /* row of label + action */
    .st-row {
      display: flex; align-items: center;
      justify-content: space-between; gap: 16px;
      padding: 14px 0;
      border-bottom: 1px solid rgba(148,163,184,0.10);
    }
    .st-row:last-child { border-bottom: none; padding-bottom: 0; }
    .st-row-info strong { display: block; font-size: 14.5px; font-weight: 700; color: #0f172a; }
    .st-row-info span   { display: block; font-size: 12.5px; color: #94a3b8; margin-top: 2px; }

    /* Buttons */
    .st-btn {
      border: 0; border-radius: 12px; padding: 10px 20px;
      font-size: 13px; font-weight: 700; cursor: pointer;
      transition: transform .16s ease, box-shadow .16s ease, filter .16s ease;
      white-space: nowrap; text-decoration: none; display: inline-flex; align-items: center; gap: 7px;
    }
    .st-btn--primary {
      background: linear-gradient(135deg,#f59e0b,#f97316,#ea580c);
      color: #fff;
      box-shadow: 0 6px 18px rgba(234,88,12,0.28);
    }
    .st-btn--primary:hover { transform: translateY(-1px); filter: brightness(1.06); box-shadow: 0 10px 24px rgba(234,88,12,0.36); }
    .st-btn--secondary {
      background: rgba(248,250,252,0.9);
      border: 1px solid rgba(148,163,184,0.3);
      color: #334155;
    }
    .st-btn--secondary:hover { background:#fff; border-color:rgba(99,102,241,0.35); color:#3730a3; }
    .st-btn--danger {
      background: rgba(254,226,226,0.9);
      border: 1px solid rgba(239,68,68,0.28);
      color: #dc2626;
    }
    .st-btn--danger:hover { background:#fff; border-color:rgba(239,68,68,0.5); box-shadow:0 6px 18px rgba(239,68,68,0.18); }
    .st-btn--danger-solid {
      background: linear-gradient(135deg,#ef4444,#dc2626);
      color: #fff;
      box-shadow: 0 6px 18px rgba(220,38,38,0.3);
    }
    .st-btn--danger-solid:hover { transform:translateY(-1px); filter:brightness(1.05); }
    .st-btn--ghost {
      background: transparent; border: 1px solid rgba(148,163,184,0.3);
      color: #64748b;
    }
    .st-btn--ghost:hover { background:#f8fafc; }

    /* ── Modal overlay ── */
    .st-modal-overlay {
      position: fixed; inset: 0;
      background: rgba(15,23,42,0.55);
      backdrop-filter: blur(4px);
      display: flex; align-items: center; justify-content: center;
      z-index: 2000; padding: 20px;
      opacity: 0; pointer-events: none;
      transition: opacity .22s ease;
    }
    .st-modal-overlay.is-open { opacity: 1; pointer-events: all; }
    .st-modal {
      width: min(480px,100%);
      background: rgba(255,255,255,0.97);
      backdrop-filter: blur(24px);
      border-radius: 24px;
      border: 1px solid rgba(255,255,255,0.9);
      box-shadow: 0 32px 64px rgba(15,23,42,0.2);
      padding: 30px 28px 26px;
      transform: translateY(14px) scale(0.98);
      transition: transform .25s cubic-bezier(.4,0,.2,1);
    }
    .st-modal-overlay.is-open .st-modal { transform: translateY(0) scale(1); }
    .st-modal-head {
      display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
      margin-bottom: 22px;
    }
    .st-modal-head-icon {
      width: 48px; height: 48px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center; font-size: 22px;
      flex-shrink: 0;
    }
    .st-modal-head h3 { margin: 0 0 4px; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; }
    .st-modal-head p  { margin: 0; font-size: 13px; color: #64748b; }
    .st-modal-close {
      width: 34px; height: 34px; border-radius: 50%;
      border: 1px solid rgba(148,163,184,0.3); background: rgba(248,250,252,.9);
      font-size: 15px; cursor: pointer; color: #6b7280;
      display: flex; align-items: center; justify-content: center;
      transition: all .16s ease; flex-shrink: 0;
    }
    .st-modal-close:hover { background:#fee2e2; border-color:rgba(239,68,68,0.3); color:#dc2626; }
    .st-modal-fields { display: grid; gap: 14px; margin-bottom: 6px; }
    .st-field label {
      display: block; margin-bottom: 6px;
      font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748b;
    }
    .st-field input {
      width: 100%; border: 1px solid rgba(148,163,184,0.35); border-radius: 12px;
      padding: 12px 14px; font-size: 14px; font-family: inherit;
      background: rgba(255,255,255,.9); color: #0f172a; outline: none;
      transition: border-color .16s ease, box-shadow .16s ease;
      box-sizing: border-box;
    }
    .st-field input:focus { border-color: rgba(245,158,11,0.5); box-shadow: 0 0 0 3px rgba(245,158,11,0.12); }
    .st-field input.is-danger:focus { border-color: rgba(239,68,68,0.5); box-shadow: 0 0 0 3px rgba(239,68,68,0.12); }
    .st-modal-actions { display: flex; gap: 10px; margin-top: 20px; }
    .st-modal-actions .st-btn { flex: 1; justify-content: center; }
    .st-msg {
      margin-top: 12px; padding: 10px 14px; border-radius: 10px;
      font-size: 13px; font-weight: 700; display: none;
    }
    .st-msg.is-success { background: rgba(22,163,74,0.1); color: #15803d; border: 1px solid rgba(22,163,74,0.25); display: block; }
    .st-msg.is-error   { background: rgba(239,68,68,0.1); color: #dc2626; border: 1px solid rgba(239,68,68,0.22); display: block; }

    /* Delete steps */
    .st-delete-step { display: none; }
    .st-delete-step.is-active { display: block; }
    .st-warning-box {
      background: rgba(254,226,226,0.6); border: 1px solid rgba(239,68,68,0.22);
      border-radius: 14px; padding: 14px 16px; margin-bottom: 16px;
    }
    .st-warning-box p { margin: 0; font-size: 13px; color: #991b1b; line-height: 1.6; }
    .st-warning-box strong { display: block; font-size: 14px; font-weight: 800; margin-bottom: 4px; }
    .st-delete-list { margin: 0 0 0 18px; padding: 0; color: #b91c1c; font-size: 13px; line-height: 1.8; }
  </style>
</head>
<body class="profile-page-wrap">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="profile-shell" aria-label="Account settings">

      <!-- Sidebar (same as profile.php) -->
      <?php require __DIR__ . '/partials/profile-sidebar.php'; ?>

      <!-- Main content -->
      <article class="profile-main">
        <div class="profile-main-head">
          <h2>⚙️ Account Settings</h2>
        </div>
        <div class="st-cards">

      <!-- Account Info Card -->
      <div class="st-card">
        <div class="st-card-header">
          <div class="st-card-icon st-card-icon--account">👤</div>
          <div class="st-card-title">
            <h2>Account Information</h2>
            <p>Your current account details</p>
          </div>
          <a href="profile.php" class="st-btn st-btn--secondary">Edit Profile</a>
        </div>
        <div class="st-card-body">
          <div class="st-row">
            <div class="st-row-info">
              <strong><?= h($fullName) ?></strong>
              <span>Full name</span>
            </div>
          </div>
          <div class="st-row">
            <div class="st-row-info">
              <strong><?= h($userEmail) ?></strong>
              <span>Email address</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Security Card -->
      <div class="st-card">
        <div class="st-card-header">
          <div class="st-card-icon st-card-icon--security">🔐</div>
          <div class="st-card-title">
            <h2>Security</h2>
            <p>Manage your password and login settings</p>
          </div>
        </div>
        <div class="st-card-body">
          <div class="st-row">
            <div class="st-row-info">
              <strong>Password</strong>
              <span>Last changed recently. Use a strong, unique password.</span>
            </div>
            <button type="button" class="st-btn st-btn--primary" id="openChangePwd">
              🔑 Change Password
            </button>
          </div>
          <div class="st-row">
            <div class="st-row-info">
              <strong>Active Sessions</strong>
              <span>You are currently logged in on this device.</span>
            </div>
            <a href="../actions/logout.php?redirect=theme-1/login.php" class="st-btn st-btn--secondary">
              🚪 Logout
            </a>
          </div>
        </div>
      </div>

      <!-- Danger Zone Card -->
      <div class="st-card">
        <div class="st-card-header">
          <div class="st-card-icon st-card-icon--danger">⚠️</div>
          <div class="st-card-title">
            <h2>Danger Zone</h2>
            <p>Irreversible account actions</p>
          </div>
        </div>
        <div class="st-card-body">
          <div class="st-row">
            <div class="st-row-info">
              <strong>Delete Account</strong>
              <span>Permanently delete your account and all associated data. This cannot be undone.</span>
            </div>
            <button type="button" class="st-btn st-btn--danger" id="openDeleteAccount">
              🗑️ Delete Account
            </button>
          </div>
        </div>
      </div>

        </div><!-- /.st-cards -->
      </article><!-- /.profile-main -->

    </section><!-- /.profile-shell -->
  </main>

  <!-- ── Change Password Modal ── -->
  <div class="st-modal-overlay" id="changePwdModal" role="dialog" aria-modal="true" aria-labelledby="cpwdTitle">
    <div class="st-modal">
      <div class="st-modal-head">
        <div style="display:flex;align-items:flex-start;gap:14px;flex:1;min-width:0">
          <div class="st-modal-head-icon" style="background:linear-gradient(140deg,#dbeafe,#bfdbfe)">🔑</div>
          <div>
            <h3 id="cpwdTitle">Change Password</h3>
            <p>Enter your current password then choose a new one.</p>
          </div>
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
            <input id="cpNew" type="password" minlength="8" placeholder="Min. 8 characters" required>
          </div>
          <div class="st-field">
            <label for="cpConfirm">Confirm New Password</label>
            <input id="cpConfirm" type="password" minlength="8" placeholder="Repeat new password" required>
          </div>
        </div>
        <div id="cpwdMsg" class="st-msg"></div>
        <div class="st-modal-actions">
          <button type="button" class="st-btn st-btn--ghost" id="closeCpwd2">Cancel</button>
          <button type="submit" class="st-btn st-btn--primary" id="cpwdSubmitBtn">🔐 Update Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Delete Account Modal ── -->
  <div class="st-modal-overlay" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="delTitle">
    <div class="st-modal">
      <div class="st-modal-head">
        <div style="display:flex;align-items:flex-start;gap:14px;flex:1;min-width:0">
          <div class="st-modal-head-icon" style="background:linear-gradient(140deg,#fee2e2,#fecaca)">🗑️</div>
          <div>
            <h3 id="delTitle">Delete Account</h3>
            <p>This action is permanent and cannot be undone.</p>
          </div>
        </div>
        <button class="st-modal-close" id="closeDelModal" aria-label="Close">✕</button>
      </div>

      <!-- Step 1: Warning -->
      <div class="st-delete-step is-active" id="delStep1">
        <div class="st-warning-box">
          <strong>⚠️ You will permanently lose:</strong>
          <ul class="st-delete-list">
            <li>All your order history and receipts</li>
            <li>Saved addresses and preferences</li>
            <li>Wishlist and saved items</li>
            <li>Reviews and ratings you've submitted</li>
            <li>Any active loyalty points or credits</li>
          </ul>
        </div>
        <p style="font-size:13px;color:#64748b;margin:0 0 16px">Are you sure you want to proceed? This cannot be reversed.</p>
        <div class="st-modal-actions">
          <button type="button" class="st-btn st-btn--ghost" id="closeDelModal2">Cancel</button>
          <button type="button" class="st-btn st-btn--danger-solid" id="delNextBtn">Continue →</button>
        </div>
      </div>

      <!-- Step 2: Confirm with password -->
      <div class="st-delete-step" id="delStep2">
        <p style="font-size:13px;color:#64748b;margin:0 0 16px">Enter your password to confirm account deletion:</p>
        <div class="st-field" style="margin-bottom:6px">
          <label for="delPassword">Your Password</label>
          <input id="delPassword" type="password" placeholder="Enter your password to confirm" class="is-danger">
        </div>
        <div id="delMsg" class="st-msg"></div>
        <div class="st-modal-actions" style="margin-top:16px">
          <button type="button" class="st-btn st-btn--ghost" id="delBackBtn">← Back</button>
          <button type="button" class="st-btn st-btn--danger-solid" id="delConfirmBtn">🗑️ Delete My Account</button>
        </div>
      </div>

    </div>
  </div>

  <?php require __DIR__ . '/partials/footer.php'; ?>

  <script>
  (function () {
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
        var res  = await fetch('../actions/change-password.php', {
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
      document.getElementById('delPassword').value = '';
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
      document.getElementById('delPassword').focus();
    });
    document.getElementById('delBackBtn').addEventListener('click', function () {
      document.getElementById('delStep2').classList.remove('is-active');
      document.getElementById('delStep1').classList.add('is-active');
    });

    document.getElementById('delConfirmBtn').addEventListener('click', async function () {
      var btn = this;
      var pwd = document.getElementById('delPassword').value.trim();
      var msg = document.getElementById('delMsg');
      if (!pwd) { setMsg(msg, '❌ Please enter your password.', false); return; }
      btn.disabled = true; btn.textContent = 'Deleting…';
      try {
        var res  = await fetch('../actions/delete-account.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ password: pwd })
        });
        var data = await res.json();
        if (data.ok) {
          setMsg(msg, '✅ Account deleted. Redirecting…', true);
          setTimeout(function () { window.location.href = '../theme-1/index.php'; }, 1800);
        } else {
          setMsg(msg, '❌ ' + (data.message || 'Could not delete account.'), false);
          btn.disabled = false; btn.textContent = '🗑️ Delete My Account';
        }
      } catch (_) {
        setMsg(msg, '❌ Network error. Please try again.', false);
        btn.disabled = false; btn.textContent = '🗑️ Delete My Account';
      }
    });
  })();
  </script>
</body>
</html>
