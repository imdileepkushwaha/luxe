<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$error = '';
$success = '';

$allowedCategories = ['fashion', 'electronics', 'beauty', 'home'];
$categoryUi = [
    'fashion' => ['label' => 'Fashion', 'icon' => '👕', 'hint' => 'Clothing, footwear, accessories'],
    'electronics' => ['label' => 'Electronics', 'icon' => '🎧', 'hint' => 'Mobiles, gadgets, appliances'],
    'beauty' => ['label' => 'Beauty', 'icon' => '💄', 'hint' => 'Skincare, makeup, wellness'],
    'home' => ['label' => 'Home', 'icon' => '🏠', 'hint' => 'Decor, kitchen, living'],
];
$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
    'business_name' => '',
    'gst_number' => '',
    'note' => '',
];
$selectedCategories = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $_) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $form['email'] = strtolower($form['email']);
    $form['gst_number'] = strtoupper($form['gst_number']);

    $categoriesIn = $_POST['categories'] ?? [];
    if (is_array($categoriesIn)) {
        foreach ($categoriesIn as $cat) {
            $value = strtolower(trim((string) $cat));
            if (in_array($value, $allowedCategories, true)) {
                $selectedCategories[] = $value;
            }
        }
    }
    $selectedCategories = array_values(array_unique($selectedCategories));

    if (mb_strlen($form['full_name']) < 3) {
        $error = 'Full name kam se kam 3 characters ka hona chahiye.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Valid email address enter karein.';
    } elseif (!preg_match('/^\+?[0-9 ]{10,15}$/', $form['phone'])) {
        $error = 'Phone number 10-15 digits me enter karein.';
    } elseif (strlen($form['password']) < 8 || !preg_match('/[A-Za-z]/', $form['password']) || !preg_match('/[0-9]/', $form['password'])) {
        $error = 'Password minimum 8 characters ka ho aur letters + numbers dono hon.';
    } elseif (mb_strlen($form['business_name']) < 3) {
        $error = 'Business name required hai.';
    } elseif ($form['gst_number'] !== '' && !preg_match('/^[0-9A-Z]{15}$/', $form['gst_number'])) {
        $error = 'GST number 15 characters ka valid format me hona chahiye.';
    } elseif ($selectedCategories === []) {
        $error = 'At least ek selling category select karein.';
    } else {
        $pendingSt = $pdo->prepare("SELECT id FROM seller_create_requests WHERE email = ? AND status = 'pending' LIMIT 1");
        $pendingSt->execute([$form['email']]);
        if ($pendingSt->fetch()) {
            $error = 'Is email ki request already pending hai. Admin review ka wait karein.';
        } else {
            $existingSellerSt = $pdo->prepare('SELECT id FROM seller_users WHERE email = ? LIMIT 1');
            $existingSellerSt->execute([$form['email']]);
            if ($existingSellerSt->fetch()) {
                $error = 'Is email ka seller account pehle se available hai. Login karein.';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO seller_create_requests (
                        full_name, email, phone, requested_password_hash, requested_categories, note,
                        business_name, gst_number, status
                     ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?
                     )'
                );
                $insert->execute([
                    $form['full_name'],
                    $form['email'],
                    $form['phone'],
                    password_hash($form['password'], PASSWORD_DEFAULT),
                    implode(',', $selectedCategories),
                    $form['note'],
                    $form['business_name'],
                    $form['gst_number'],
                    'pending',
                ]);

                $success = 'Registration request submit ho gayi. Admin approval ke baad seller panel login karke KYC + bank details fill karein.';
                foreach ($form as $key => $_) {
                    $form[$key] = '';
                }
                $selectedCategories = [];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../admin/partials/theme-head-script.php'; ?>
  <title>Seller Registration - LUXE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../admin/css/admin.css">
  <style>
    .seller-category-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:12px}
    .seller-category-card{position:relative;display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1px solid #e6e8ef;border-radius:12px;background:#fff;cursor:pointer;transition:all .15s ease}
    .seller-category-card:hover{border-color:#cfd4e2;box-shadow:0 2px 8px rgba(17,24,39,.06)}
    .seller-category-card input{
      position:absolute;
      width:1px;
      height:1px;
      margin:-1px;
      padding:0;
      border:0;
      clip:rect(0 0 0 0);
      overflow:hidden;
      white-space:nowrap;
      pointer-events:none
    }
    .seller-category-card__check{margin-top:2px;width:16px;height:16px;border:1.5px solid #c4cbd8;border-radius:4px;display:inline-block;flex:0 0 auto;background:#fff;transition:all .15s ease}
    .seller-category-card__icon{font-size:17px;line-height:1}
    .seller-category-card__label{display:block;font-weight:600;color:#111827}
    .seller-category-card__hint{display:block;margin-top:2px;font-size:12px;color:#6b7280}
    .seller-category-card:has(input:checked){border-color:#ef4444;background:#fff5f5;box-shadow:0 0 0 2px rgba(239,68,68,.14)}
    .seller-category-card:has(input:checked) .seller-category-card__check{border-color:#ef4444;background:linear-gradient(135deg,#ef4444,#dc2626)}
    @media (max-width: 640px){.seller-category-grid{grid-template-columns:1fr}}
  </style>
</head>
<body class="admin-login admin-app--merchant">
  <div class="admin-login-card" style="max-width:760px">
    <div class="admin-login-card__brand">
      <div class="admin-sidebar__logo" style="width:44px;height:44px" aria-hidden="true"><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--a"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--b"></span><span class="admin-sidebar__logo-bit admin-sidebar__logo-bit--c"></span></div>
      <div>
        <div class="admin-sidebar__title">LUXE</div>
        <div class="admin-sidebar__subtitle">Seller onboarding</div>
      </div>
    </div>
    <h1>Seller Registration</h1>
    <p>Yeh basic onboarding request hai. Approval ke baad seller login active hoga, fir aap bank details aur business address/proof panel me add kar sakte hain.</p>

    <?php if ($error !== ''): ?>
      <div class="err"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
      <div class="admin-del-flash admin-del-flash--ok" style="margin-bottom:14px"><?= h($success) ?></div>
    <?php endif; ?>

    <form method="post">
      <h3 style="margin:14px 0 8px">Account details</h3>
      <label for="full_name">Full name</label>
      <input id="full_name" name="full_name" required minlength="3" value="<?= h($form['full_name']) ?>" placeholder="Seller name">

      <label for="email">Email</label>
      <input id="email" type="email" name="email" required value="<?= h($form['email']) ?>" placeholder="seller@example.com">

      <label for="phone">Phone</label>
      <input id="phone" name="phone" required pattern="^\+?[0-9 ]{10,15}$" value="<?= h($form['phone']) ?>" placeholder="+91 9876543210">

      <label for="password">Set password</label>
      <div class="seller-password-wrap">
        <input id="password" type="password" name="password" required minlength="8" placeholder="Minimum 8 chars (letters + numbers)">
        <?php require __DIR__ . '/partials/password-toggle-button.php'; ?>
      </div>

      <h3 style="margin:18px 0 8px">Business details</h3>
      <label for="business_name">Business name</label>
      <input id="business_name" name="business_name" required value="<?= h($form['business_name']) ?>" placeholder="Your brand / company name">

      <label for="gst_number">GST number (optional)</label>
      <input id="gst_number" name="gst_number" maxlength="15" value="<?= h($form['gst_number']) ?>" placeholder="22AAAAA0000A1Z5">

      <label>Requested categories</label>
      <div class="seller-category-grid">
        <?php foreach ($allowedCategories as $cat): ?>
          <?php $meta = $categoryUi[$cat] ?? ['label' => ucfirst($cat), 'icon' => '📦', 'hint' => '']; ?>
          <label class="seller-category-card">
            <input type="checkbox" name="categories[]" value="<?= h($cat) ?>" <?= in_array($cat, $selectedCategories, true) ? 'checked' : '' ?>>
            <span class="seller-category-card__check" aria-hidden="true"></span>
            <span>
              <span class="seller-category-card__label">
                <span class="seller-category-card__icon"><?= h((string) $meta['icon']) ?></span>
                <?= h((string) $meta['label']) ?>
              </span>
              <span class="seller-category-card__hint"><?= h((string) $meta['hint']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>

      <label for="note">Additional note (optional)</label>
      <input id="note" name="note" value="<?= h($form['note']) ?>" placeholder="Store type, expected products, etc.">

      <button type="submit">Submit registration</button>
    </form>

    <div class="hint"><a href="login.php">Back to seller login</a></div>
  </div>
  <script src="js/password-toggle.js?v=2"></script>
</body>
</html>
