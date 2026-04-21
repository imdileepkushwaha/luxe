<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);
$sellerId = (int) $seller['id'];

$pageTitle = 'Financial settings';
$activeNav = 'settings';

$flash = '';
$flashOk = false;
$openBankModal = false;

if (isset($_GET['saved']) && (string) $_GET['saved'] === '1') {
    $flash = 'Naya bank account save ho gaya.';
    $flashOk = true;
} elseif (isset($_GET['removed']) && (string) $_GET['removed'] === '1') {
    $flash = 'Bank account hata diya gaya.';
    $flashOk = true;
}

/**
 * @param non-empty-string $num
 */
function seller_financial_mask_account(string $num): string
{
    $len = strlen($num);
    if ($len <= 4) {
        return str_repeat('•', $len);
    }

    return str_repeat('•', $len - 4) . substr($num, -4);
}

function seller_financial_valid_upi(string $upi): bool
{
    if ($upi === '') {
        return true;
    }
    if (strlen($upi) < 5 || strlen($upi) > 100) {
        return false;
    }

    return (bool) preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$/', $upi);
}

function seller_financial_mask_upi(string $upi): string
{
    if ($upi === '') {
        return '';
    }
    $parts = explode('@', $upi, 2);
    if (count($parts) !== 2) {
        return '••••';
    }
    [$local, $domain] = $parts;
    $localLen = strlen($local);
    if ($localLen <= 2) {
        $locShow = str_repeat('•', max(2, $localLen));
    } else {
        $locShow = substr($local, 0, 2) . str_repeat('•', min(6, $localLen - 2));
    }

    return $locShow . '@' . $domain;
}

$kycSt = $pdo->prepare(
    'SELECT bank_name, bank_account_name, bank_account_number, bank_ifsc
     FROM seller_users WHERE id = ? LIMIT 1'
);
$kycSt->execute([$sellerId]);
$kycBank = $kycSt->fetch() ?: [];
$kycHasBank = trim((string) ($kycBank['bank_account_number'] ?? '')) !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'delete_bank_account') {
        $delId = (int) ($_POST['account_id'] ?? 0);
        if ($delId > 0) {
            $del = $pdo->prepare('DELETE FROM seller_bank_accounts WHERE id = ? AND seller_id = ? LIMIT 1');
            $del->execute([$delId, $sellerId]);
            if ($del->rowCount() > 0) {
                header('Location: financial-settings.php?removed=1');
                exit;
            }
            $flash = 'Account delete nahi ho saka.';
        }
    } elseif ($action === 'add_bank_account') {
        $flash = '';
        $bankName = trim((string) ($_POST['bank_name'] ?? ''));
        $holder = trim((string) ($_POST['account_holder_name'] ?? ''));
        $acct = (string) (preg_replace('/\s+/', '', (string) ($_POST['account_number'] ?? '')) ?? '');
        $ifscRaw = (string) (preg_replace('/\s+/', '', (string) ($_POST['ifsc'] ?? '')) ?? '');
        $ifsc = strtoupper($ifscRaw);
        $upiRaw = strtolower(trim((string) ($_POST['upi_id'] ?? '')));

        if (mb_strlen($bankName) < 2) {
            $flash = 'Bank name required hai.';
        } elseif (mb_strlen($holder) < 3) {
            $flash = 'Account holder name required hai.';
        } elseif (!preg_match('/^[0-9]{9,18}$/', $acct)) {
            $flash = 'Bank account number 9–18 digits ka hona chahiye.';
        } elseif (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
            $flash = 'Valid IFSC code enter karein (example: HDFC0001234).';
        } elseif (!seller_financial_valid_upi($upiRaw)) {
            $flash = 'UPI ID format sahi nahi hai (example: naam@paytm).';
        } else {
            $dup = $pdo->prepare('SELECT id FROM seller_bank_accounts WHERE seller_id = ? AND account_number = ? LIMIT 1');
            $dup->execute([$sellerId, $acct]);
            if ($dup->fetch()) {
                $flash = 'Yeh account number pehle se saved hai.';
            } elseif ($kycHasBank && (string) ($kycBank['bank_account_number'] ?? '') === $acct) {
                $flash = 'Yeh account KYC primary account se match karta hai — alag account add karein.';
            } elseif ($upiRaw !== '') {
                $dupUpi = $pdo->prepare(
                    'SELECT id FROM seller_bank_accounts WHERE seller_id = ? AND upi_id <> \'\' AND LOWER(upi_id) = ? LIMIT 1'
                );
                $dupUpi->execute([$sellerId, $upiRaw]);
                if ($dupUpi->fetch()) {
                    $flash = 'Yeh UPI ID pehle se saved hai.';
                }
            }
            if ($flash === '') {
                try {
                    $ins = $pdo->prepare(
                        'INSERT INTO seller_bank_accounts (seller_id, bank_name, account_holder_name, account_number, ifsc, upi_id)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $ins->execute([$sellerId, $bankName, $holder, $acct, $ifsc, $upiRaw]);
                    header('Location: financial-settings.php?saved=1');
                    exit;
                } catch (Throwable) {
                    $flash = 'Save nahi ho saka — dubara try karein.';
                }
            }
        }
        if (!$flashOk && $flash !== '') {
            $openBankModal = true;
        }
    }
}

$listSt = $pdo->prepare(
    'SELECT id, bank_name, account_holder_name, account_number, ifsc, upi_id, created_at
     FROM seller_bank_accounts
     WHERE seller_id = ?
     ORDER BY created_at DESC, id DESC'
);
$listSt->execute([$sellerId]);
$extraAccounts = $listSt->fetchAll();

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-settings-head">
          <div>
            <h1>Financial settings</h1>
            <p class="seller-orders-subtitle">Apne bank accounts aur optional <strong>UPI ID</strong> yahan save karein. <strong>KYC</strong> wala primary payout account <a href="kyc-details.php">KYC page</a> par update hota hai; neeche extra accounts + UPI add kar sakte hain (withdraw par bank / UPI manually copy kar sakte hain).</p>
          </div>
          <div class="admin-page-head__actions seller-orders-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="settings.php">← Settings</a>
            <button
              type="button"
              class="admin-btn admin-btn--primary"
              id="sellerBankOpenBtn"
              aria-haspopup="dialog"
              aria-controls="sellerBankModal"
              aria-expanded="false"
            >
              + New account
            </button>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-settings-flash seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-bank-page">
          <?php if (!$kycHasBank && count($extraAccounts) === 0): ?>
            <p class="seller-bank-empty">Abhi koi bank detail show nahi ho rahi. KYC complete karein ya <strong>New account</strong> se yahan ek account add karein.</p>
          <?php endif; ?>

          <ul class="seller-bank-list" role="list">
            <?php if ($kycHasBank): ?>
              <li class="seller-bank-card seller-bank-card--kyc">
                <div class="seller-bank-card__head">
                  <span class="seller-bank-badge seller-bank-badge--kyc">KYC primary</span>
                  <span class="seller-bank-card__bank"><?= h((string) ($kycBank['bank_name'] ?? '')) ?></span>
                </div>
                <dl class="seller-bank-card__dl">
                  <div><dt>Holder</dt><dd><?= h((string) ($kycBank['bank_account_name'] ?? '')) ?></dd></div>
                  <div><dt>Account</dt><dd class="seller-bank-mono"><?= h(seller_financial_mask_account((string) ($kycBank['bank_account_number'] ?? ''))) ?></dd></div>
                  <div><dt>IFSC</dt><dd class="seller-bank-mono"><?= h(strtoupper((string) ($kycBank['bank_ifsc'] ?? ''))) ?></dd></div>
                </dl>
                <p class="seller-bank-card__foot"><a href="kyc-details.php">KYC page par edit</a></p>
              </li>
            <?php endif; ?>

            <?php foreach ($extraAccounts as $row): ?>
              <li class="seller-bank-card">
                <div class="seller-bank-card__head">
                  <span class="seller-bank-badge">Saved</span>
                  <span class="seller-bank-card__bank"><?= h((string) ($row['bank_name'] ?? '')) ?></span>
                </div>
                <dl class="seller-bank-card__dl">
                  <div><dt>Holder</dt><dd><?= h((string) ($row['account_holder_name'] ?? '')) ?></dd></div>
                  <div><dt>Account</dt><dd class="seller-bank-mono"><?= h(seller_financial_mask_account((string) ($row['account_number'] ?? ''))) ?></dd></div>
                  <div><dt>IFSC</dt><dd class="seller-bank-mono"><?= h((string) ($row['ifsc'] ?? '')) ?></dd></div>
                  <?php if (trim((string) ($row['upi_id'] ?? '')) !== ''): ?>
                    <div><dt>UPI ID</dt><dd class="seller-bank-mono"><?= h(seller_financial_mask_upi((string) ($row['upi_id'] ?? ''))) ?></dd></div>
                  <?php endif; ?>
                </dl>
                <form method="post" class="seller-bank-card__del" onsubmit="return confirm('Is bank account delete karna hai?');">
                  <input type="hidden" name="action" value="delete_bank_account">
                  <input type="hidden" name="account_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                  <button type="submit" class="admin-btn admin-btn--ghost-light seller-bank-remove-btn">Remove</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="seller-pw-modal-backdrop" id="sellerBankModalBackdrop" aria-hidden="true"></div>
        <div
          class="seller-pw-modal"
          id="sellerBankModal"
          role="dialog"
          aria-modal="true"
          aria-labelledby="sellerBankModalTitle"
          aria-hidden="true"
        >
          <div class="seller-pw-modal__panel seller-pw-modal__panel--bank">
            <div class="seller-pw-modal__head">
              <h2 class="seller-pw-modal__title" id="sellerBankModalTitle">Add bank account</h2>
              <button type="button" class="seller-pw-modal__close" id="sellerBankModalClose" aria-label="Close dialog">✕</button>
            </div>
            <div class="seller-pw-modal__body">
              <form method="post" class="seller-settings-password-form" id="sellerBankModalForm" autocomplete="off">
                <input type="hidden" name="action" value="add_bank_account">
                <div class="seller-settings-password-stack">
                  <div class="seller-settings-field">
                    <label for="fin_bank_name">Bank name</label>
                    <input id="fin_bank_name" name="bank_name" type="text" required class="seller-stock-input" maxlength="120" value="<?= h((string) ($_POST['bank_name'] ?? '')) ?>" <?= $openBankModal ? '' : 'disabled' ?> data-bank-field>
                  </div>
                  <div class="seller-settings-field">
                    <label for="fin_holder">Account holder name</label>
                    <input id="fin_holder" name="account_holder_name" type="text" required class="seller-stock-input" maxlength="120" value="<?= h((string) ($_POST['account_holder_name'] ?? '')) ?>" <?= $openBankModal ? '' : 'disabled' ?> data-bank-field>
                  </div>
                  <div class="seller-settings-field">
                    <label for="fin_acct">Account number</label>
                    <input id="fin_acct" name="account_number" type="text" required class="seller-stock-input seller-bank-mono" pattern="[0-9]{9,18}" inputmode="numeric" value="<?= h((string) ($_POST['account_number'] ?? '')) ?>" <?= $openBankModal ? '' : 'disabled' ?> data-bank-field>
                  </div>
                  <div class="seller-settings-field">
                    <label for="fin_ifsc">IFSC</label>
                    <input id="fin_ifsc" name="ifsc" type="text" required class="seller-stock-input seller-bank-mono" maxlength="11" pattern="[A-Za-z]{4}0[A-Za-z0-9]{6}" value="<?= h((string) ($_POST['ifsc'] ?? '')) ?>" <?= $openBankModal ? '' : 'disabled' ?> data-bank-field placeholder="HDFC0001234">
                  </div>
                  <div class="seller-settings-field">
                    <label for="fin_upi">UPI ID <span class="seller-bank-optional">(optional)</span></label>
                    <input id="fin_upi" name="upi_id" type="text" class="seller-stock-input seller-bank-mono" maxlength="100" autocomplete="off" value="<?= h((string) ($_POST['upi_id'] ?? '')) ?>" <?= $openBankModal ? '' : 'disabled' ?> data-bank-field placeholder="yourname@paytm">
                  </div>
                </div>
                <div class="seller-pw-modal__actions">
                  <button type="button" class="admin-btn admin-btn--ghost-light" id="sellerBankModalCancel">Cancel</button>
                  <button type="submit" class="admin-btn admin-btn--primary">Save account</button>
                </div>
              </form>
            </div>
          </div>
        </div>

<script>
  (function () {
    var openOnLoad = <?= $openBankModal ? 'true' : 'false' ?>;
    var backdrop = document.getElementById('sellerBankModalBackdrop');
    var modal = document.getElementById('sellerBankModal');
    var openBtn = document.getElementById('sellerBankOpenBtn');
    var closeBtn = document.getElementById('sellerBankModalClose');
    var cancelBtn = document.getElementById('sellerBankModalCancel');
    var form = document.getElementById('sellerBankModalForm');
    if (!backdrop || !modal || !openBtn || !form) return;

    var fields = form.querySelectorAll('[data-bank-field]');
    var lastFocus = null;

    function setDisabled(on) {
      for (var i = 0; i < fields.length; i++) {
        fields[i].disabled = !on;
      }
    }

    function setOpen(on) {
      backdrop.classList.toggle('is-open', on);
      modal.classList.toggle('is-open', on);
      backdrop.setAttribute('aria-hidden', on ? 'false' : 'true');
      modal.setAttribute('aria-hidden', on ? 'false' : 'true');
      openBtn.setAttribute('aria-expanded', on ? 'true' : 'false');
      document.body.classList.toggle('seller-pw-modal-open', on);
      setDisabled(on);
      if (on) {
        lastFocus = document.activeElement;
        var first = document.getElementById('fin_bank_name');
        if (first) setTimeout(function () { first.focus(); }, 10);
      } else {
        if (lastFocus && typeof lastFocus.focus === 'function') {
          try { lastFocus.focus(); } catch (e) {}
        }
        openBtn.focus();
        form.reset();
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

    setDisabled(false);
    if (openOnLoad) setOpen(true);
  })();
</script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
