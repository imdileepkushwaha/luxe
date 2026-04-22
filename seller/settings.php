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
$openDeleteModal = false;

if (isset($_GET['deletion']) && (string) $_GET['deletion'] === 'requested') {
    $flash = 'Account deletion request admin ko bhej di gayi hai.';
    $flashOk = true;
}

if (isset($_GET['notice']) && (string) $_GET['notice'] === 'payment_gateways_admin') {
    $flash = 'Payment gateways ab LUXE Admin panel → Settings → Payments tab se configure hote hain.';
    $flashOk = true;
}

$pendingDeletionRequest = seller_deletion_pending_for_seller($pdo, $sellerId);
$latestDeletionRequest = seller_deletion_latest_for_seller($pdo, $sellerId);
$latestDeletionByEmail = seller_deletion_latest_for_email($pdo, (string) $seller['email']);
$effectiveLatestDeletionRequest = $latestDeletionByEmail ?: $latestDeletionRequest;

$hashSt = $pdo->prepare('SELECT password_hash FROM seller_users WHERE id = ? LIMIT 1');
$hashSt->execute([$sellerId]);
$currentHash = (string) ($hashSt->fetchColumn() ?: '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'change_password') {
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
    } elseif ($action === 'delete_account') {
        $confirmText = trim((string) ($_POST['confirm_text'] ?? ''));
        if (strtoupper($confirmText) !== 'DELETE') {
            $flash = 'Account delete karne ke liye confirmation box me DELETE likhna zaruri hai.';
            $flashOk = false;
            $openDeleteModal = true;
        } elseif ($pendingDeletionRequest || ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'pending')) {
            $flash = 'Aapki deletion request already pending hai. Admin review ka wait karein.';
            $flashOk = false;
            $openDeleteModal = true;
        } elseif ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved') {
            $flash = 'Deletion request already approved hai. Nayi request allowed nahi hai.';
            $flashOk = false;
            $openDeleteModal = true;
        } else {
            $result = seller_deletion_request_create(
                $pdo,
                $sellerId,
                (string) $seller['email'],
                (string) $seller['full_name']
            );
            if ($result === true) {
                header('Location: settings.php?deletion=requested');
                exit;
            }
            $flash = (string) $result;
            $flashOk = false;
            $openDeleteModal = true;
        }
    }
}

$openPasswordModal = $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'change_password'
    && !$flashOk;

$deleteFormDisabled = $pendingDeletionRequest
    || ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved');

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="seller-settings-page">
        <div class="admin-page-head seller-settings-head">
          <div class="seller-settings-head__intro">
            <h1>Settings</h1>
            <p class="seller-settings-head__lede">Seller account, payouts, shipping aur security — sab sections neeche zones me grouped hain.</p>
          </div>
          <div class="admin-page-head__actions seller-orders-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="index.php">Dashboard</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-settings-flash seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <nav class="seller-settings-toc" aria-label="Settings sections">
          <span class="seller-settings-toc__label">Jump</span>
          <a class="seller-settings-toc__link" href="#settings-account-heading">Account</a>
          <a class="seller-settings-toc__link" href="#settings-finance-heading">Finance</a>
          <a class="seller-settings-toc__link" href="#settings-fulfil-heading">Fulfilment</a>
          <a class="seller-settings-toc__link" href="#settings-security-heading">Security</a>
          <a class="seller-settings-toc__link" href="#settings-more-heading">Shortcuts</a>
          <a class="seller-settings-toc__link seller-settings-toc__link--danger" href="#seller-delete-zone">Delete account</a>
        </nav>

        <div class="seller-settings-grid seller-settings-grid--balanced">
          <section class="card seller-settings-card seller-settings-card--zone seller-settings-card--account" aria-labelledby="settings-account-heading">
            <div class="card-header seller-settings-card__head">
              <div class="seller-settings-card__head-text">
                <p class="seller-settings-card__kicker">Zone 1 · Identity</p>
                <h2 class="card-title" id="settings-account-heading">Account &amp; profile</h2>
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

          <section class="card seller-settings-card seller-settings-card--zone seller-settings-card--finance" aria-labelledby="settings-finance-heading">
            <div class="card-header seller-settings-card__head">
              <div class="seller-settings-card__head-text">
                <p class="seller-settings-card__kicker">Zone 2 · Payouts</p>
                <h2 class="card-title" id="settings-finance-heading">Financial</h2>
              </div>
            </div>
            <div class="card-body seller-settings-card-body">
              <ul class="seller-settings-links" role="list">
                <li>
                  <a class="seller-settings-link" href="financial-settings.php">
                    <span class="seller-settings-link__icon" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Financial settings</span>
                      <span class="seller-settings-link__hint">Bank accounts list &amp; add new</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
              </ul>
            </div>
          </section>

          <section class="card seller-settings-card seller-settings-card--zone seller-settings-card--fulfil" aria-labelledby="settings-fulfil-heading">
            <div class="card-header seller-settings-card__head">
              <div class="seller-settings-card__head-text">
                <p class="seller-settings-card__kicker">Zone 3 · Delivery</p>
                <h2 class="card-title" id="settings-fulfil-heading">Fulfilment</h2>
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

          <section class="card seller-settings-card seller-settings-card--zone seller-settings-card--security" id="seller-delete-zone" aria-labelledby="settings-security-heading">
            <div class="card-header seller-settings-card__head">
              <div class="seller-settings-card__head-text">
                <p class="seller-settings-card__kicker">Zone 4 · Access</p>
                <h2 class="card-title" id="settings-security-heading">Security</h2>
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
                <li>
                  <button
                    type="button"
                    class="seller-settings-link seller-settings-link--btn seller-settings-link--danger"
                    id="sellerDeleteOpenBtn"
                    aria-haspopup="dialog"
                    aria-controls="sellerDeleteModal"
                    aria-expanded="false"
                  >
                    <span class="seller-settings-link__icon seller-settings-link__icon--danger" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Delete account</span>
                      <span class="seller-settings-link__hint">Admin review ke baad access band</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </button>
                </li>
              </ul>
            </div>
          </section>

          <section class="card seller-settings-card seller-settings-card--zone seller-settings-card--more" aria-labelledby="settings-more-heading">
            <div class="card-header seller-settings-card__head">
              <div class="seller-settings-card__head-text">
                <p class="seller-settings-card__kicker">Zone 5 · Shortcuts</p>
                <h2 class="card-title" id="settings-more-heading">Orders &amp; tools</h2>
              </div>
            </div>
            <div class="card-body seller-settings-card-body">
              <ul class="seller-settings-links seller-settings-links--compact" role="list">
                <li>
                  <a class="seller-settings-link" href="orders.php">
                    <span class="seller-settings-link__icon seller-settings-link__icon--muted" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Orders</span>
                      <span class="seller-settings-link__hint">Fulfilment &amp; status</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
                <li>
                  <a class="seller-settings-link" href="shipped-products.php">
                    <span class="seller-settings-link__icon seller-settings-link__icon--muted" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 18H3c-.6 0-1-.4-1-1V7c0-.6.4-1 1-1h10c.6 0 1 .4 1 1v11"/><path d="M14 9h4l4 4v2c0 .6-.4 1-1 1h-2"/><circle cx="7" cy="18" r="2"/><path d="M15 18H9"/><circle cx="17" cy="18" r="2"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Shipped products</span>
                      <span class="seller-settings-link__hint">Line-level shipped list</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
                <li>
                  <a class="seller-settings-link" href="earnings.php">
                    <span class="seller-settings-link__icon seller-settings-link__icon--muted" aria-hidden="true">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <span class="seller-settings-link__text">
                      <span class="seller-settings-link__label">Earnings &amp; payouts</span>
                      <span class="seller-settings-link__hint">Withdraw, transactions</span>
                    </span>
                    <span class="seller-settings-link__arrow" aria-hidden="true">→</span>
                  </a>
                </li>
              </ul>
            </div>
          </section>
        </div>
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

        <div class="seller-pw-modal-backdrop" id="sellerDeleteModalBackdrop" aria-hidden="true"></div>
        <div
          class="seller-pw-modal"
          id="sellerDeleteModal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="sellerDeleteModalTitle"
          aria-hidden="true"
        >
          <div class="seller-pw-modal__panel seller-pw-modal__panel--delete">
            <div class="seller-pw-modal__head">
              <h2 class="seller-pw-modal__title" id="sellerDeleteModalTitle">Delete seller account</h2>
              <button type="button" class="seller-pw-modal__close" id="sellerDeleteModalClose" aria-label="Close dialog">✕</button>
            </div>
            <div class="seller-pw-modal__body">
              <p class="seller-pw-modal__lead">Yeh action aapka seller access hata deta hai admin approval ke baad. Products is profile se unlink ho jayenge.</p>
              <?php if ($pendingDeletionRequest): ?>
                <div class="seller-alert seller-alert--warn seller-settings-delete-alert">
                  Deletion request pending (requested <?= h((string) ($pendingDeletionRequest['requested_at'] ?? '—')) ?>). Admin process karega; resolve hone tak nayi request nahi.
                </div>
              <?php elseif ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved'): ?>
                <div class="seller-alert seller-alert--success seller-settings-delete-alert">
                  Deletion approve ho chuka hai. Is seller account par access jald band ho jayega.
                </div>
              <?php endif; ?>
              <form method="post" class="seller-settings-delete-form" id="sellerDeleteModalForm" autocomplete="off">
                <input type="hidden" name="action" value="delete_account">
                <div class="seller-settings-field">
                  <label for="seller_delete_confirm">Confirm karne ke liye <strong>DELETE</strong> likhein</label>
                  <input
                    id="seller_delete_confirm"
                    name="confirm_text"
                    class="seller-stock-input"
                    placeholder="DELETE"
                    autocomplete="off"
                    <?= $deleteFormDisabled ? 'disabled' : 'required' ?>
                    value="<?= h((string) ($_POST['confirm_text'] ?? '')) ?>"
                  >
                </div>
                <div class="seller-pw-modal__actions seller-pw-modal__actions--delete">
                  <button type="button" class="admin-btn admin-btn--ghost-light" id="sellerDeleteModalCancel">Cancel</button>
                  <button type="submit" class="seller-btn-danger" id="sellerDeleteModalSubmit" <?= $deleteFormDisabled ? 'disabled' : '' ?>>
                    <?php if ($pendingDeletionRequest): ?>
                      Request pending
                    <?php elseif ($effectiveLatestDeletionRequest && (string) ($effectiveLatestDeletionRequest['status'] ?? '') === 'approved'): ?>
                      Request approved
                    <?php else: ?>
                      Request account deletion
                    <?php endif; ?>
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

<script>
  window.sellerSettingsModalBodyLock = function () {
    var pw = document.getElementById('sellerPwModal');
    var del = document.getElementById('sellerDeleteModal');
    var on = (pw && pw.classList.contains('is-open')) || (del && del.classList.contains('is-open'));
    document.body.classList.toggle('seller-pw-modal-open', !!on);
  };

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
      window.sellerSettingsModalBodyLock();
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

  (function () {
    var openOnLoad = <?= $openDeleteModal ? 'true' : 'false' ?>;
    var backdrop = document.getElementById('sellerDeleteModalBackdrop');
    var modal = document.getElementById('sellerDeleteModal');
    var openBtn = document.getElementById('sellerDeleteOpenBtn');
    var closeBtn = document.getElementById('sellerDeleteModalClose');
    var cancelBtn = document.getElementById('sellerDeleteModalCancel');
    var form = document.getElementById('sellerDeleteModalForm');
    if (!backdrop || !modal || !openBtn || !form) return;

    var lastFocus = null;

    function setOpen(on) {
      backdrop.classList.toggle('is-open', on);
      modal.classList.toggle('is-open', on);
      backdrop.setAttribute('aria-hidden', on ? 'false' : 'true');
      modal.setAttribute('aria-hidden', on ? 'false' : 'true');
      openBtn.setAttribute('aria-expanded', on ? 'true' : 'false');
      window.sellerSettingsModalBodyLock();
      if (on) {
        lastFocus = document.activeElement;
        var first = document.getElementById('seller_delete_confirm');
        if (first && !first.disabled) {
          setTimeout(function () { first.focus(); }, 10);
        }
      } else {
        if (window.location.hash === '#seller-delete-zone') {
          try {
            history.replaceState(null, '', window.location.pathname + window.location.search);
          } catch (e) {}
        }
        if (lastFocus && typeof lastFocus.focus === 'function') {
          try { lastFocus.focus(); } catch (e) {}
        }
        openBtn.focus();
        form.reset();
      }
    }

    function openDeleteModalIfHash() {
      if (window.location.hash !== '#seller-delete-zone') return;
      setTimeout(function () { setOpen(true); }, 0);
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

    if (openOnLoad) setOpen(true);

    openDeleteModalIfHash();
    window.addEventListener('hashchange', openDeleteModalIfHash);
  })();
</script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
