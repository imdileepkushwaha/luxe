<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);
$sellerId = (int) $seller['id'];

$pageTitle = 'Settings';
$activeNav = 'settings';

$flash = '';
$flashOk = false;

$hashSt = $pdo->prepare('SELECT password_hash FROM seller_users WHERE id = ? LIMIT 1');
$hashSt->execute([$sellerId]);
$currentHash = (string) ($hashSt->fetchColumn() ?: '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'change_password') {
    $cur = (string) ($_POST['current_password'] ?? '');
    $nw = (string) ($_POST['new_password'] ?? '');
    $nw2 = (string) ($_POST['new_password_confirm'] ?? '');

    if ($currentHash === '' || !password_verify($cur, $currentHash)) {
        $flash = 'Current password galat hai.';
    } elseif (strlen($nw) < 8 || !preg_match('/[A-Za-z]/', $nw) || !preg_match('/[0-9]/', $nw)) {
        $flash = 'Naya password minimum 8 characters — letters aur numbers dono hon.';
    } elseif ($nw !== $nw2) {
        $flash = 'Naye password match nahi kar rahe.';
    } elseif (password_verify($nw, $currentHash)) {
        $flash = 'Naya password purane jaisa nahi ho sakta.';
    } else {
        $upd = $pdo->prepare('UPDATE seller_users SET password_hash = ? WHERE id = ? LIMIT 1');
        $upd->execute([password_hash($nw, PASSWORD_DEFAULT), $sellerId]);
        $flash = 'Password update ho gaya. Agli baar naye password se login karein.';
        $flashOk = true;
        $hashSt->execute([$sellerId]);
        $currentHash = (string) ($hashSt->fetchColumn() ?: '');
    }
}

$openPasswordModal = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'change_password'
    && !$flashOk;

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-settings-head">
          <div>
            <h1>Settings</h1>
            <p class="seller-orders-subtitle">Account security, storefront branding, aur fulfilment rules — saari important links ek jagah. <strong>Theme</strong> (dark / light) top bar ke moon icon se toggle hota hai.</p>
          </div>
          <div class="admin-page-head__actions seller-orders-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="index.php">Dashboard</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-settings-flash seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-settings-grid">
          <section class="card seller-settings-card" aria-labelledby="settings-account-heading">
            <div class="card-header">
              <div>
                <h2 class="card-title" id="settings-account-heading">Account &amp; profile</h2>
                <p class="card-subtitle seller-orders-card-sub">Contact, logo/banner, allowed categories summary — profile page par manage karein.</p>
              </div>
            </div>
            <div class="card-body seller-settings-card-body">
              <ul class="seller-settings-links" role="list">
                <li>
                  <a class="seller-settings-link" href="profile.php">
                    <span class="seller-settings-link__icon" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Profile &amp; branding</span>
                      <span class="seller-settings-link__hint">Phone, address, logo, banner</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
                <li>
                  <a class="seller-settings-link" href="kyc-details.php">
                    <span class="seller-settings-link__icon" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">KYC &amp; bank</span>
                      <span class="seller-settings-link__hint">GST, documents, payout details</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
                <li>
                  <a class="seller-settings-link" href="../seller-store.php?id=<?= $sellerId ?>" target="_blank" rel="noopener">
                    <span class="seller-settings-link__icon" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Public store</span>
                      <span class="seller-settings-link__hint">Buyer-facing storefront (new tab)</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">↗</span>
                  </a>
                </li>
              </ul>
            </div>
          </section>

          <section class="card seller-settings-card" aria-labelledby="settings-fulfil-heading">
            <div class="card-header">
              <div>
                <h2 class="card-title" id="settings-fulfil-heading">Fulfilment</h2>
                <p class="card-subtitle seller-orders-card-sub">Shipping, delivery fees, returns — yahan se open karein.</p>
              </div>
            </div>
            <div class="card-body seller-settings-card-body">
              <ul class="seller-settings-links" role="list">
                <li>
                  <a class="seller-settings-link" href="shipping-settings.php">
                    <span class="seller-settings-link__icon" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18h2"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Shipping settings</span>
                      <span class="seller-settings-link__hint">Classes, handling, zones</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
                <li>
                  <a class="seller-settings-link" href="delivery-options.php">
                    <span class="seller-settings-link__icon" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Delivery options</span>
                      <span class="seller-settings-link__hint">Buyer delivery choices</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
                <li>
                  <a class="seller-settings-link" href="return-refund-settings.php">
                    <span class="seller-settings-link__icon" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Returns &amp; refunds</span>
                      <span class="seller-settings-link__hint">Policy &amp; windows</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
              </ul>
            </div>
          </section>

          <section class="card seller-settings-card seller-settings-card--wide" aria-labelledby="settings-security-heading">
            <div class="card-header">
              <div>
                <h2 class="card-title" id="settings-security-heading">Security</h2>
                <p class="card-subtitle seller-orders-card-sub">Login password badalne ke liye neeche <strong>Change password</strong> par click karein — form popup me khulega.</p>
              </div>
            </div>
            <div class="card-body seller-settings-card-body">
              <ul class="seller-settings-links" role="list">
                <li>
                  <button
                    type="button"
                    class="seller-settings-link seller-settings-link--btn"
                    id="sellerPwOpenBtn"
                    aria-haspopup="dialog"
                    aria-controls="sellerPwModal"
                    aria-expanded="false"
                  >
                    <span class="seller-settings-link__icon" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Change password</span>
                      <span class="seller-settings-link__hint">Current password + naya strong password</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </button>
                </li>
              </ul>
            </div>
          </section>

          <section class="card seller-settings-card seller-settings-card--wide" aria-labelledby="settings-more-heading">
            <div class="card-header">
              <div>
                <h2 class="card-title" id="settings-more-heading">More</h2>
                <p class="card-subtitle seller-orders-card-sub">Orders, finance, aur account closure.</p>
              </div>
            </div>
            <div class="card-body seller-settings-card-body">
              <ul class="seller-settings-links seller-settings-links--compact" role="list">
                <li>
                  <a class="seller-settings-link" href="orders.php">
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Orders</span>
                      <span class="seller-settings-link__hint">Fulfilment &amp; status</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
                <li>
                  <a class="seller-settings-link" href="shipped-products.php">
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Shipped products</span>
                      <span class="seller-settings-link__hint">Line-level shipped list</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
                <li>
                  <a class="seller-settings-link" href="earnings.php">
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Earnings &amp; payouts</span>
                      <span class="seller-settings-link__hint">Withdraw, transactions</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
                <li>
                  <a class="seller-settings-link" href="index.php#danger-zone">
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Danger zone</span>
                      <span class="seller-settings-link__hint">Account deletion request</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
              </ul>
            </div>
          </section>
        </div>

        <div class="seller-pw-modal-backdrop" id="sellerPwModalBackdrop" aria-hidden="true"></div>
        <div
          class="seller-pw-modal"
          id="sellerPwModal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="sellerPwModalTitle"
          aria-hidden="true"
        >
          <div class="seller-pw-modal__panel">
            <div class="seller-pw-modal__head">
              <h2 class="seller-pw-modal__title" id="sellerPwModalTitle">Change password</h2>
              <button type="button" class="seller-pw-modal__close" id="sellerPwModalClose" aria-label="Close dialog">✕</button>
            </div>
            <div class="seller-pw-modal__body">
              <p class="seller-pw-modal__lead">Current password verify hoga. Naya password minimum <strong>8 characters</strong> — letters aur numbers dono.</p>
              <form method="post" class="seller-settings-password-form" id="sellerPwModalForm" autocomplete="off">
                <input type="hidden" name="action" value="change_password">
                <div class="seller-settings-password-stack">
                  <div class="seller-settings-field">
                    <label for="seller_pw_current">Current password</label>
                    <div class="seller-password-wrap">
                      <input id="seller_pw_current" name="current_password" type="password" required class="seller-stock-input" autocomplete="current-password" minlength="1">
                      <?php require __DIR__ . '/partials/password-toggle-button.php'; ?>
                    </div>
                  </div>
                  <div class="seller-settings-field">
                    <label for="seller_pw_new">New password</label>
                    <div class="seller-password-wrap">
                      <input id="seller_pw_new" name="new_password" type="password" required class="seller-stock-input" autocomplete="new-password" minlength="8" placeholder="Min 8 chars, letters + numbers">
                      <?php require __DIR__ . '/partials/password-toggle-button.php'; ?>
                    </div>
                  </div>
                  <div class="seller-settings-field">
                    <label for="seller_pw_confirm">Confirm new password</label>
                    <div class="seller-password-wrap">
                      <input id="seller_pw_confirm" name="new_password_confirm" type="password" required class="seller-stock-input" autocomplete="new-password" minlength="8">
                      <?php require __DIR__ . '/partials/password-toggle-button.php'; ?>
                    </div>
                  </div>
                </div>
                <div class="seller-pw-modal__actions">
                  <button type="button" class="admin-btn admin-btn--ghost-light" id="sellerPwModalCancel">Cancel</button>
                  <button type="submit" class="admin-btn admin-btn--primary">Update password</button>
                </div>
              </form>
            </div>
          </div>
        </div>

<script>
  (function () {
    var open = <?= $openPasswordModal ? 'true' : 'false' ?>;
    var backdrop = document.getElementById('sellerPwModalBackdrop');
    var modal = document.getElementById('sellerPwModal');
    var openBtn = document.getElementById('sellerPwOpenBtn');
    var closeBtn = document.getElementById('sellerPwModalClose');
    var cancelBtn = document.getElementById('sellerPwModalCancel');
    var form = document.getElementById('sellerPwModalForm');
    if (!backdrop || !modal || !openBtn || !form) return;

    var lastFocus = null;

    function setOpen(on) {
      backdrop.classList.toggle('is-open', on);
      modal.classList.toggle('is-open', on);
      backdrop.setAttribute('aria-hidden', on ? 'false' : 'true');
      modal.setAttribute('aria-hidden', on ? 'false' : 'true');
      openBtn.setAttribute('aria-expanded', on ? 'true' : 'false');
      document.body.classList.toggle('seller-pw-modal-open', on);
      if (on) {
        lastFocus = document.activeElement;
        var first = document.getElementById('seller_pw_current');
        if (first) {
          setTimeout(function () { first.focus(); }, 10);
        }
      } else {
        if (lastFocus && typeof lastFocus.focus === 'function') {
          try { lastFocus.focus(); } catch (e) {}
        }
        openBtn.focus();
      }
    }

    openBtn.addEventListener('click', function () { setOpen(true); });
    closeBtn && closeBtn.addEventListener('click', function () { setOpen(false); });
    cancelBtn && cancelBtn.addEventListener('click', function () { setOpen(false); });
    backdrop.addEventListener('click', function () { setOpen(false); });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        e.preventDefault();
        setOpen(false);
      }
    });

    if (open) setOpen(true);
  })();
</script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
