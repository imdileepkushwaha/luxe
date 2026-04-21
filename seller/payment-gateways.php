<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);
$sellerId = (int) $seller['id'];

$pageTitle = 'Payment gateways';
$activeNav = 'payment_gateways';

$flash = '';
$flashOk = false;

$allowedGateways = ['none', 'razorpay', 'stripe', 'payu'];

function seller_payment_gateways_public_base_path(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $norm = str_replace('\\', '/', $script);
    $sellerDir = dirname($norm);
    $parent = dirname($sellerDir);
    if ($parent === '/' || $parent === '\\' || $parent === '.') {
        return '';
    }

    return rtrim($parent, '/');
}

function seller_payment_gateways_webhook_url(int $sellerId): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $basePath = seller_payment_gateways_public_base_path();

    return $scheme . '://' . $host . $basePath . '/actions/seller-payment-webhook.php?seller_id=' . $sellerId;
}

$defaults = [
    'gateway' => 'none',
    'mode' => 'test',
    'public_key' => '',
    'secret_key' => '',
    'merchant_id' => '',
    'webhook_secret' => '',
];

$loadSt = $pdo->prepare(
    'SELECT gateway, mode, public_key, secret_key, merchant_id, webhook_secret
     FROM seller_payment_gateway_configs WHERE seller_id = ? LIMIT 1'
);
$loadSt->execute([$sellerId]);
$row = $loadSt->fetch();
$config = $row ? array_merge($defaults, $row) : $defaults;
$config['gateway'] = in_array((string) ($config['gateway'] ?? ''), $allowedGateways, true)
    ? (string) $config['gateway']
    : 'none';
$config['mode'] = in_array((string) ($config['mode'] ?? ''), ['test', 'live'], true)
    ? (string) $config['mode']
    : 'test';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_payment_gateway') {
    $gw = strtolower(trim((string) ($_POST['gateway'] ?? '')));
    if (!in_array($gw, $allowedGateways, true)) {
        $gw = 'none';
    }
    $mode = strtolower(trim((string) ($_POST['mode'] ?? 'test')));
    if (!in_array($mode, ['test', 'live'], true)) {
        $mode = 'test';
    }
    $publicKey = trim((string) ($_POST['public_key'] ?? ''));
    $secretKey = trim((string) ($_POST['secret_key'] ?? ''));
    $merchantId = trim((string) ($_POST['merchant_id'] ?? ''));
    $webhookSecret = trim((string) ($_POST['webhook_secret'] ?? ''));
    if ($secretKey === '') {
        $secretKey = (string) ($config['secret_key'] ?? '');
    }
    if ($webhookSecret === '') {
        $webhookSecret = (string) ($config['webhook_secret'] ?? '');
    }

    if (strlen($publicKey) > 255 || strlen($secretKey) > 255 || strlen($merchantId) > 120 || strlen($webhookSecret) > 255) {
        $flash = 'Fields ki length zyada hai — dubara check karein.';
    } elseif ($gw !== 'none' && (strlen($publicKey) < 8 || strlen($secretKey) < 8)) {
        $flash = 'Gateway enable karne ke liye public / client key aur secret key dono (min 8 chars) zaroori hain.';
    } else {
        $upsert = $pdo->prepare(
            'INSERT INTO seller_payment_gateway_configs
                (seller_id, gateway, mode, public_key, secret_key, merchant_id, webhook_secret)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                gateway = VALUES(gateway),
                mode = VALUES(mode),
                public_key = VALUES(public_key),
                secret_key = VALUES(secret_key),
                merchant_id = VALUES(merchant_id),
                webhook_secret = VALUES(webhook_secret),
                updated_at = CURRENT_TIMESTAMP'
        );
        $upsert->execute([$sellerId, $gw, $mode, $publicKey, $secretKey, $merchantId, $webhookSecret]);
        $flash = 'Payment gateway settings save ho gayi.';
        $flashOk = true;
        $config = [
            'gateway' => $gw,
            'mode' => $mode,
            'public_key' => $publicKey,
            'secret_key' => $secretKey,
            'merchant_id' => $merchantId,
            'webhook_secret' => $webhookSecret,
        ];
    }
}

$webhookUrl = seller_payment_gateways_webhook_url($sellerId);
$gwLabel = match ($config['gateway']) {
    'razorpay' => 'Razorpay',
    'stripe' => 'Stripe',
    'payu' => 'PayU',
    default => '—',
};

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-pgw-head">
          <div>
            <h1>Payment gateways</h1>
            <p class="seller-pgw-lede">Apna checkout provider connect karein — keys test mode se start karein, phir live.</p>
          </div>
          <div class="admin-page-head__actions">
            <a class="admin-btn admin-btn--ghost-light" href="settings.php">← Settings</a>
            <a class="admin-btn admin-btn--ghost-light" href="financial-settings.php">Financial settings</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>" style="margin-bottom:16px"><?= h($flash) ?></div>
        <?php endif; ?>

        <ol class="seller-pgw-steps" aria-label="Integration steps">
          <li class="seller-pgw-step seller-pgw-step--active"><span class="seller-pgw-step__n">1</span> Provider chunein</li>
          <li class="seller-pgw-step"><span class="seller-pgw-step__n">2</span> API keys</li>
          <li class="seller-pgw-step"><span class="seller-pgw-step__n">3</span> Webhook</li>
          <li class="seller-pgw-step"><span class="seller-pgw-step__n">4</span> Save &amp; test</li>
        </ol>

        <div class="card seller-pgw-card">
          <div class="card-header">
            <h2 class="card-title" id="pgw-form-heading">Gateway setup</h2>
          </div>
          <div class="card-body">
            <form method="post" class="seller-pgw-form" aria-labelledby="pgw-form-heading">
              <input type="hidden" name="action" value="save_payment_gateway">

              <fieldset class="seller-pgw-fieldset">
                <legend class="seller-pgw-legend">Step 1 · Provider</legend>
                <p class="seller-pgw-hint">Marketplace checkout jab seller-level gateway support karega, yahan save ki values use hongi.</p>
                <div class="seller-pgw-gateway-grid" role="radiogroup" aria-label="Payment provider">
                  <?php
                    $opts = [
                        'none' => ['None (disabled)', 'Abhi collect on platform default'],
                        'razorpay' => ['Razorpay', 'India — cards, UPI, netbanking'],
                        'stripe' => ['Stripe', 'Global — cards, wallets'],
                        'payu' => ['PayU', 'India — multiple banks'],
                    ];
                  foreach ($opts as $val => $meta):
                      $checked = $config['gateway'] === $val;
                  ?>
                    <label class="seller-pgw-gateway-tile<?= $checked ? ' is-selected' : '' ?>">
                      <input class="seller-pgw-gateway-input" type="radio" name="gateway" value="<?= h($val) ?>" <?= $checked ? 'checked' : '' ?>>
                      <span class="seller-pgw-gateway-tile__title"><?= h($meta[0]) ?></span>
                      <span class="seller-pgw-gateway-tile__hint"><?= h($meta[1]) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>

              <fieldset class="seller-pgw-fieldset">
                <legend class="seller-pgw-legend">Step 2 · Environment &amp; keys</legend>
                <div class="seller-pgw-form-row">
                  <div>
                    <label for="pgw_mode">Mode</label>
                    <select id="pgw_mode" name="mode" class="seller-stock-input">
                      <option value="test"<?= $config['mode'] === 'test' ? ' selected' : '' ?>>Test / sandbox</option>
                      <option value="live"<?= $config['mode'] === 'live' ? ' selected' : '' ?>>Live</option>
                    </select>
                  </div>
                </div>
                <div class="seller-pgw-form-row seller-pgw-form-row--stack">
                  <div>
                    <label for="pgw_public">Public / key ID <span class="seller-pgw-label-hint">(Razorpay Key Id, Stripe publishable, PayU merchant key)</span></label>
                    <input id="pgw_public" name="public_key" class="seller-stock-input" maxlength="255" value="<?= h((string) $config['public_key']) ?>" autocomplete="off" placeholder="rzp_test_… / pk_test_…">
                  </div>
                  <div>
                    <label for="pgw_secret">Secret key <span class="seller-pgw-label-hint">(server-side only)</span></label>
                    <input id="pgw_secret" name="secret_key" type="password" class="seller-stock-input" maxlength="255" value="" autocomplete="new-password" placeholder="<?= ($config['secret_key'] ?? '') !== '' ? 'Khali chhoden = pehle wala secret' : 'Min 8 characters' ?>">
                  </div>
                  <div>
                    <label for="pgw_merchant">Merchant / account ID <span class="seller-pgw-label-hint">(optional)</span></label>
                    <input id="pgw_merchant" name="merchant_id" class="seller-stock-input" maxlength="120" value="<?= h((string) $config['merchant_id']) ?>" autocomplete="off">
                  </div>
                  <div>
                    <label for="pgw_whsec">Webhook signing secret <span class="seller-pgw-label-hint">(optional, verify events)</span></label>
                    <input id="pgw_whsec" name="webhook_secret" type="password" class="seller-stock-input" maxlength="255" value="" autocomplete="new-password" placeholder="<?= ($config['webhook_secret'] ?? '') !== '' ? 'Optional — khali = purana' : 'Optional' ?>">
                  </div>
                </div>
              </fieldset>

              <fieldset class="seller-pgw-fieldset">
                <legend class="seller-pgw-legend">Step 3 · Webhook URL</legend>
                <p class="seller-pgw-hint">Provider dashboard me is URL ko webhook destination ke taur par add karein (jab available ho).</p>
                <div class="seller-pgw-webhook-row">
                  <input id="pgw_webhook_url" type="text" class="seller-stock-input seller-pgw-webhook-input" readonly value="<?= h($webhookUrl) ?>">
                  <button type="button" class="admin-btn admin-btn--ghost-light" id="pgwCopyWebhook" data-copy-target="pgw_webhook_url">Copy</button>
                </div>
              </fieldset>

              <fieldset class="seller-pgw-fieldset">
                <legend class="seller-pgw-legend">Step 4 · Save</legend>
                <p class="seller-pgw-hint">Live keys sirf HTTPS par store karein; credentials share na karein.</p>
                <div class="seller-pgw-summary">
                  <span class="seller-pgw-summary__label">Active provider</span>
                  <strong class="seller-pgw-summary__val"><?= h($gwLabel) ?></strong>
                  <span class="seller-pgw-summary__label">Mode</span>
                  <strong class="seller-pgw-summary__val"><?= h(strtoupper((string) $config['mode'])) ?></strong>
                </div>
                <div class="seller-pgw-actions">
                  <button type="submit" class="admin-btn admin-btn--primary">Save gateway settings</button>
                </div>
              </fieldset>
            </form>
          </div>
        </div>

<script>
  (function () {
    document.querySelectorAll('.seller-pgw-gateway-tile').forEach(function (tile) {
      var input = tile.querySelector('.seller-pgw-gateway-input');
      if (!input) return;
      function sync() {
        document.querySelectorAll('.seller-pgw-gateway-tile').forEach(function (t) {
          t.classList.toggle('is-selected', t.querySelector('.seller-pgw-gateway-input') && t.querySelector('.seller-pgw-gateway-input').checked);
        });
      }
      input.addEventListener('change', sync);
      tile.addEventListener('click', function (e) {
        if (e.target !== input) {
          input.checked = true;
          sync();
        }
      });
    });
    var btn = document.getElementById('pgwCopyWebhook');
    var target = document.getElementById('pgw_webhook_url');
    if (btn && target && navigator.clipboard && navigator.clipboard.writeText) {
      btn.addEventListener('click', function () {
        navigator.clipboard.writeText(target.value).then(function () {
          var t = btn.textContent;
          btn.textContent = 'Copied';
          setTimeout(function () { btn.textContent = t; }, 1600);
        });
      });
    }
  })();
</script>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
