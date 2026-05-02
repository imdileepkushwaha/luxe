<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/cms.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'CRM — Pages & Site';
$activeNav = 'crm';

/** Tabs handled by this single page. */
$validTabs = ['site_info', 'contact', 'about', 'faq', 'terms', 'privacy', 'return_policy', 'theme'];

/** Page key labels for headings + sidebar dropdown labels. */
$pageLabels = [
    'contact'        => 'Contact Us',
    'about'          => 'About Us',
    'faq'            => 'FAQ',
    'terms'          => 'Terms & Conditions',
    'privacy'        => 'Privacy Policy',
    'return_policy'  => 'Return Policy',
];

$logoDir = __DIR__ . '/../uploads/site';
$logoUrlPrefix = '../uploads/site/';

if (!is_dir($logoDir)) {
    @mkdir($logoDir, 0775, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_storefront_theme') {
        $pick = trim((string) ($_POST['storefront_theme'] ?? ''));
        if (!in_array($pick, ['default', 'theme-1', 'theme-2'], true)) {
            header('Location: crm.php?msg=theme_invalid&tab=theme');
            exit;
        }
        site_setting_set($pdo, 'storefront_theme', $pick);
        header('Location: crm.php?msg=theme_saved&tab=theme');
        exit;
    }

    if ($action === 'save_site_info') {
        $brand   = trim((string) ($_POST['brand']   ?? ''));
        $email   = trim((string) ($_POST['email']   ?? ''));
        $phone   = trim((string) ($_POST['phone']   ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $hours   = trim((string) ($_POST['hours']   ?? ''));

        if ($brand === '' || mb_strlen($brand) > 80) {
            header('Location: crm.php?msg=site_invalid&tab=site_info');
            exit;
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: crm.php?msg=site_invalid&tab=site_info');
            exit;
        }
        if (mb_strlen($email) > 200 || mb_strlen($phone) > 60 || mb_strlen($hours) > 120 || mb_strlen($address) > 500) {
            header('Location: crm.php?msg=site_invalid&tab=site_info');
            exit;
        }

        site_setting_set($pdo, 'site_brand_name',     $brand);
        site_setting_set($pdo, 'site_contact_email',  $email);
        site_setting_set($pdo, 'site_contact_phone',  $phone);
        site_setting_set($pdo, 'site_contact_address',$address);
        site_setting_set($pdo, 'site_contact_hours',  $hours);

        $logoSaved = false;
        if (!empty($_FILES['logo']) && (int) $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $tmp  = (string) $_FILES['logo']['tmp_name'];
            $size = (int) $_FILES['logo']['size'];
            if ($size > 0 && $size <= 800 * 1024) {
                $info = @getimagesize($tmp);
                $ext = '';
                if (is_array($info)) {
                    switch ((int) $info[2]) {
                        case IMAGETYPE_JPEG: $ext = 'jpg'; break;
                        case IMAGETYPE_PNG:  $ext = 'png'; break;
                        case IMAGETYPE_GIF:  $ext = 'gif'; break;
                        case IMAGETYPE_WEBP: $ext = 'webp'; break;
                    }
                }
                if ($ext !== '') {
                    $name = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $dest = $logoDir . '/' . $name;
                    if (@move_uploaded_file($tmp, $dest)) {
                        site_setting_set($pdo, 'site_logo_path', 'uploads/site/' . $name);
                        $logoSaved = true;
                    }
                }
            }
            if (!$logoSaved) {
                header('Location: crm.php?msg=logo_failed&tab=site_info');
                exit;
            }
        }

        if (!empty($_POST['remove_logo'])) {
            site_setting_set($pdo, 'site_logo_path', '');
        }

        header('Location: crm.php?msg=site_saved&tab=site_info');
        exit;
    }

    if ($action === 'save_page') {
        $key = (string) ($_POST['page_key'] ?? '');
        if (!in_array($key, CMS_PAGE_KEYS, true)) {
            header('Location: crm.php?msg=invalid');
            exit;
        }
        try {
            cms_page_save($pdo, $key, [
                'hero_kicker'      => (string) ($_POST['hero_kicker'] ?? ''),
                'hero_title'       => (string) ($_POST['hero_title']  ?? ''),
                'hero_lead'        => (string) ($_POST['hero_lead']   ?? ''),
                'body_html'        => (string) ($_POST['body_html']   ?? ''),
                'meta_description' => (string) ($_POST['meta_description'] ?? ''),
            ]);
        } catch (Throwable $e) {
            header('Location: crm.php?msg=page_failed&tab=' . urlencode($key));
            exit;
        }
        header('Location: crm.php?msg=page_saved&tab=' . urlencode($key));
        exit;
    }

    if ($action === 'save_faq') {
        $faqId      = (int) ($_POST['faq_id'] ?? 0);
        $question   = (string) ($_POST['question'] ?? '');
        $answer     = (string) ($_POST['answer']   ?? '');
        $sortOrder  = (int)    ($_POST['sort_order'] ?? 0);
        $isActive   = !empty($_POST['is_active']);
        try {
            cms_faq_save($pdo, $faqId > 0 ? $faqId : null, $question, $answer, $sortOrder, $isActive);
        } catch (Throwable $e) {
            header('Location: crm.php?msg=faq_invalid&tab=faq');
            exit;
        }
        header('Location: crm.php?msg=faq_saved&tab=faq');
        exit;
    }

    if ($action === 'delete_faq') {
        $faqId = (int) ($_POST['faq_id'] ?? 0);
        cms_faq_delete($pdo, $faqId);
        header('Location: crm.php?msg=faq_deleted&tab=faq');
        exit;
    }

    header('Location: crm.php?msg=invalid');
    exit;
}

$msg = (string) ($_GET['msg'] ?? '');
$flash = null;
if ($msg === 'site_saved')   $flash = ['ok' => true,  'text' => 'Site info save ho gayi.'];
elseif ($msg === 'site_invalid') $flash = ['ok' => false, 'text' => 'Brand name / email valid nahi hai.'];
elseif ($msg === 'logo_failed')  $flash = ['ok' => false, 'text' => 'Logo upload fail — JPG/PNG/GIF/WEBP under 800KB hi accept hota hai.'];
elseif ($msg === 'page_saved')   $flash = ['ok' => true,  'text' => 'Page content update ho gaya.'];
elseif ($msg === 'page_failed')  $flash = ['ok' => false, 'text' => 'Page save nahi ho paya.'];
elseif ($msg === 'faq_saved')    $flash = ['ok' => true,  'text' => 'FAQ item save ho gaya.'];
elseif ($msg === 'faq_invalid')  $flash = ['ok' => false, 'text' => 'Question aur answer dono required hain.'];
elseif ($msg === 'faq_deleted')  $flash = ['ok' => true,  'text' => 'FAQ item delete ho gaya.'];
elseif ($msg === 'invalid')      $flash = ['ok' => false, 'text' => 'Invalid action.'];
elseif ($msg === 'theme_saved')  $flash = ['ok' => true,  'text' => 'Storefront theme activate ho gaya.'];
elseif ($msg === 'theme_invalid') $flash = ['ok' => false, 'text' => 'Theme valid nahi hai.'];

$tabRaw = (string) ($_GET['tab'] ?? '');
$tab = in_array($tabRaw, $validTabs, true) ? $tabRaw : 'site_info';

$contact = site_contact_bundle($pdo);
$logoUrl = $contact['logo'] !== '' ? '../' . ltrim($contact['logo'], '/') : '';

$pageData = [];
foreach (CMS_PAGE_KEYS as $key) {
    $pageData[$key] = cms_page_get($pdo, $key);
}
$faqs = cms_faqs_all($pdo, false);

$storefrontThemeCurrent = trim(site_setting_get($pdo, 'storefront_theme', 'default'));
if (!in_array($storefrontThemeCurrent, ['default', 'theme-1', 'theme-2'], true)) {
    $storefrontThemeCurrent = 'default';
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-crm-page">
        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?>" role="status">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">CRM · Content</span>
            <h1>Site &amp; Pages</h1>
            <p class="admin-page-head__lede">Brand info, contact details, FAQ aur sabhi storefront pages (Contact / About / FAQ / Terms / Privacy / Return Policy) yahan se manage karein. Sab kuch DB me save hota hai.</p>
          </div>
        </div>

        <div class="card admin-crm-card">
          <div class="admin-crm-tabs" role="tablist" aria-label="CRM sections">
            <?php
            $tabLabels = [
                'site_info'      => 'Site Info',
                'contact'        => 'Contact Page',
                'about'          => 'About Page',
                'faq'            => 'FAQ Page',
                'terms'          => 'Terms',
                'privacy'        => 'Privacy',
                'return_policy'  => 'Return Policy',
                'theme'          => 'Theme',
            ];
            foreach ($tabLabels as $tk => $tl):
                $isActive = $tab === $tk;
            ?>
              <a class="admin-crm-tab<?= $isActive ? ' admin-crm-tab--active' : '' ?>"
                 role="tab"
                 aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                 href="crm.php?tab=<?= h($tk) ?>"><?= h($tl) ?></a>
            <?php endforeach; ?>
          </div>

          <div class="admin-crm-body">

          <?php if ($tab === 'site_info'): ?>
            <section class="admin-crm-section">
              <h2 class="admin-crm-section__title">Brand &amp; contact information</h2>
              <p class="admin-crm-section__hint">Header/footer logo text, customer support contact details aur public address. Yeh values site ke har page par dikhti hain.</p>

              <form method="post" enctype="multipart/form-data" class="admin-settings-form admin-crm-form">
                <input type="hidden" name="action" value="save_site_info">

                <div class="admin-crm-logo-row">
                  <div class="admin-crm-logo-preview" aria-hidden="true">
                    <?php if ($logoUrl !== ''): ?>
                      <img src="<?= h($logoUrl) ?>" alt="Site logo">
                    <?php else: ?>
                      <span class="admin-crm-logo-fallback"><?= h(strtoupper(substr($contact['brand'], 0, 4))) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="admin-crm-logo-meta">
                    <label class="admin-field" style="margin:0">
                      <span class="admin-field__label">Replace logo (optional)</span>
                      <span class="admin-field__hint">JPG / PNG / GIF / WEBP — max 800KB. Empty rakhne par mojooda logo barkarar rahega.</span>
                      <input class="admin-input" type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp">
                    </label>
                    <?php if ($logoUrl !== ''): ?>
                      <label class="admin-crm-checkbox">
                        <input type="checkbox" name="remove_logo" value="1"> Logo hata dein (text fallback dikhega)
                      </label>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="admin-form-grid-3">
                  <label class="admin-field">
                    <span class="admin-field__label">Brand name <span class="admin-settings-crms-req" aria-hidden="true">*</span></span>
                    <input class="admin-input" type="text" name="brand" required maxlength="80" value="<?= h($contact['brand']) ?>">
                  </label>
                  <label class="admin-field">
                    <span class="admin-field__label">Contact email</span>
                    <input class="admin-input" type="email" name="email" maxlength="200" value="<?= h($contact['email']) ?>">
                  </label>
                  <label class="admin-field">
                    <span class="admin-field__label">Mobile / phone number</span>
                    <input class="admin-input" type="text" name="phone" maxlength="60" value="<?= h($contact['phone']) ?>">
                  </label>
                  <label class="admin-field">
                    <span class="admin-field__label">Working hours</span>
                    <input class="admin-input" type="text" name="hours" maxlength="120" value="<?= h($contact['hours']) ?>">
                  </label>
                  <label class="admin-field admin-settings-crms-field-span2">
                    <span class="admin-field__label">Office / business address</span>
                    <textarea class="admin-input admin-crm-textarea admin-crm-textarea--sm" name="address" maxlength="500"><?= h($contact['address']) ?></textarea>
                  </label>
                </div>

                <div class="admin-settings-crms-actions">
                  <a class="admin-btn admin-btn--outline" href="crm.php?tab=site_info">Cancel</a>
                  <button type="submit" class="admin-btn admin-btn--primary">Save site info</button>
                </div>
              </form>
            </section>

          <?php elseif ($tab === 'theme'): ?>
            <section class="admin-crm-section">
              <h2 class="admin-crm-section__title">Storefront theme</h2>
              <p class="admin-crm-section__hint">Jo theme <strong>activate</strong> hoga, public site ke URLs clean rahengi (jaise <code class="admin-crm-code">/index.php</code>, <code class="admin-crm-code">/product-list.php</code>) — browser address bar me <code class="admin-crm-code">theme-1</code> dikhega nahi; design selected theme folder se load hota hai.</p>

              <div class="admin-crm-theme-grid">
                <?php
                $themeOptions = [
                    'default' => [
                        'title' => 'Theme',
                        'desc' => 'Default LUXE layout — root <code class="admin-crm-code">css/luxe.css</code> aur classic navbar.',
                    ],
                    'theme-1' => [
                        'title' => 'Theme 1',
                        'desc' => 'Alternate storefront — folder <code class="admin-crm-code">theme-1/</code> (styles, header, footer).',
                    ],
                    'theme-2' => [
                        'title' => 'Theme 2',
                        'desc' => 'Second skin — folder <code class="admin-crm-code">theme-2/</code>, alag visual system.',
                    ],
                ];
                foreach ($themeOptions as $tk => $meta):
                    $isLive = $storefrontThemeCurrent === $tk;
                ?>
                <div class="admin-crm-theme-card<?= $isLive ? ' admin-crm-theme-card--active' : '' ?>">
                  <div class="admin-crm-theme-card__top">
                    <h3 class="admin-crm-theme-card__title"><?= h((string) $meta['title']) ?></h3>
                    <?php if ($isLive): ?>
                      <span class="admin-status admin-status--delivered">Active</span>
                    <?php endif; ?>
                  </div>
                  <p class="admin-crm-theme-card__desc"><?= $meta['desc'] ?></p>
                  <?php if (!$isLive): ?>
                    <form method="post" class="admin-crm-theme-card__form">
                      <input type="hidden" name="action" value="save_storefront_theme">
                      <input type="hidden" name="storefront_theme" value="<?= h($tk) ?>">
                      <button type="submit" class="admin-btn admin-btn--primary">Activate</button>
                    </form>
                  <?php else: ?>
                    <p class="admin-crm-theme-card__note">Abhi yeh theme live hai. Koi aur activate karne par woh replace ho jayega.</p>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </section>

          <?php elseif ($tab === 'faq'): ?>
            <section class="admin-crm-section">
              <h2 class="admin-crm-section__title"><?= h($pageLabels['faq']) ?> · Hero</h2>
              <p class="admin-crm-section__hint">Top hero strip jo FAQ page ke upar dikhti hai.</p>
              <form method="post" class="admin-settings-form admin-crm-form">
                <input type="hidden" name="action" value="save_page">
                <input type="hidden" name="page_key" value="faq">
                <?php $p = $pageData['faq']; ?>
                <div class="admin-form-grid-3">
                  <label class="admin-field">
                    <span class="admin-field__label">Hero kicker</span>
                    <input class="admin-input" type="text" name="hero_kicker" maxlength="120" value="<?= h($p['hero_kicker']) ?>">
                  </label>
                  <label class="admin-field admin-settings-crms-field-span2">
                    <span class="admin-field__label">Page title</span>
                    <input class="admin-input" type="text" name="hero_title" maxlength="255" value="<?= h($p['hero_title']) ?>">
                  </label>
                  <label class="admin-field admin-crm-span-3">
                    <span class="admin-field__label">Lead / subtitle</span>
                    <textarea class="admin-input admin-crm-textarea admin-crm-textarea--sm" name="hero_lead" maxlength="1000"><?= h($p['hero_lead']) ?></textarea>
                  </label>
                  <label class="admin-field admin-crm-span-3">
                    <span class="admin-field__label">Meta description (SEO)</span>
                    <textarea class="admin-input admin-crm-textarea admin-crm-textarea--sm" name="meta_description" maxlength="500"><?= h($p['meta_description']) ?></textarea>
                  </label>
                </div>
                <div class="admin-settings-crms-actions">
                  <button type="submit" class="admin-btn admin-btn--primary">Save FAQ hero</button>
                </div>
              </form>
            </section>

            <section class="admin-crm-section">
              <h2 class="admin-crm-section__title">FAQ items</h2>
              <p class="admin-crm-section__hint">Question / answer pairs jo public FAQ page par accordion ki tarah dikhte hain. Inactive ko hide karne ke liye toggle off karein.</p>

              <?php if ($faqs === []): ?>
                <p class="admin-empty-hint admin-empty-hint--boxed">Abhi koi FAQ item nahi hai — neeche se add karein.</p>
              <?php else: ?>
                <div class="admin-crm-faq-list">
                  <?php foreach ($faqs as $f): ?>
                    <details class="admin-crm-faq-row">
                      <summary>
                        <span class="admin-crm-faq-row__q"><?= h($f['question']) ?></span>
                        <span class="admin-crm-faq-row__meta">
                          #<?= (int) $f['id'] ?> · order <?= (int) $f['sort_order'] ?> ·
                          <?php if ($f['is_active']): ?>
                            <span class="admin-status admin-status--delivered">Active</span>
                          <?php else: ?>
                            <span class="admin-status admin-status--cancelled">Hidden</span>
                          <?php endif; ?>
                        </span>
                      </summary>
                      <form method="post" class="admin-settings-form admin-crm-form admin-crm-faq-form">
                        <input type="hidden" name="action" value="save_faq">
                        <input type="hidden" name="faq_id" value="<?= (int) $f['id'] ?>">
                        <div class="admin-form-grid-3">
                          <label class="admin-field admin-crm-span-3">
                            <span class="admin-field__label">Question</span>
                            <input class="admin-input" type="text" name="question" maxlength="500" value="<?= h($f['question']) ?>" required>
                          </label>
                          <label class="admin-field admin-crm-span-3">
                            <span class="admin-field__label">Answer</span>
                            <textarea class="admin-input admin-crm-textarea" name="answer" required><?= h($f['answer']) ?></textarea>
                          </label>
                          <label class="admin-field">
                            <span class="admin-field__label">Sort order</span>
                            <input class="admin-input" type="number" name="sort_order" min="0" max="65535" value="<?= (int) $f['sort_order'] ?>">
                          </label>
                          <label class="admin-field admin-crm-checkbox-field">
                            <span class="admin-field__label">Visibility</span>
                            <label class="admin-crm-checkbox"><input type="checkbox" name="is_active" value="1" <?= $f['is_active'] ? 'checked' : '' ?>> Active (public par dikhao)</label>
                          </label>
                        </div>
                        <div class="admin-settings-crms-actions">
                          <button type="submit" class="admin-btn admin-btn--primary">Update item</button>
                        </div>
                      </form>
                      <form method="post" class="admin-crm-faq-delete" onsubmit="return confirm('Yeh FAQ delete karni hai?');">
                        <input type="hidden" name="action" value="delete_faq">
                        <input type="hidden" name="faq_id" value="<?= (int) $f['id'] ?>">
                        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                      </form>
                    </details>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <div class="admin-crm-faq-add">
                <h3 class="admin-crm-section__subtitle">Add new FAQ</h3>
                <form method="post" class="admin-settings-form admin-crm-form">
                  <input type="hidden" name="action" value="save_faq">
                  <input type="hidden" name="faq_id" value="0">
                  <div class="admin-form-grid-3">
                    <label class="admin-field admin-crm-span-3">
                      <span class="admin-field__label">Question</span>
                      <input class="admin-input" type="text" name="question" maxlength="500" required>
                    </label>
                    <label class="admin-field admin-crm-span-3">
                      <span class="admin-field__label">Answer</span>
                      <textarea class="admin-input admin-crm-textarea" name="answer" required></textarea>
                    </label>
                    <label class="admin-field">
                      <span class="admin-field__label">Sort order</span>
                      <input class="admin-input" type="number" name="sort_order" min="0" max="65535" value="<?= max(10, (count($faqs) + 1) * 10) ?>">
                    </label>
                    <label class="admin-field admin-crm-checkbox-field">
                      <span class="admin-field__label">Visibility</span>
                      <label class="admin-crm-checkbox"><input type="checkbox" name="is_active" value="1" checked> Active (public par dikhao)</label>
                    </label>
                  </div>
                  <div class="admin-settings-crms-actions">
                    <button type="submit" class="admin-btn admin-btn--primary">Add FAQ item</button>
                  </div>
                </form>
              </div>
            </section>

          <?php else: ?>
            <?php
              $key = $tab;
              $p = $pageData[$key] ?? null;
              if ($p === null) {
                  $p = cms_page_get($pdo, $key);
              }
              $supportsBody = in_array($key, ['terms', 'privacy', 'return_policy', 'about', 'contact'], true);
            ?>
            <section class="admin-crm-section">
              <h2 class="admin-crm-section__title"><?= h($pageLabels[$key] ?? ucfirst($key)) ?></h2>
              <p class="admin-crm-section__hint">Public storefront page ka content. Hero kicker / title / lead frontend ke top section me dikhte hain. Body HTML legal/long pages (Terms, Privacy, Return) ke main article me inject hota hai — basic HTML tags (h2, p, ul, ol, li, a, strong, em) supported hain.</p>
              <?php if ($key === 'contact'): ?>
                <p class="admin-crm-section__hint"><strong>Footer aur contact strip:</strong> jo phone, email aur address har page ke footer / Contact section me dikhte hain wo <a href="crm.php?tab=site_info">Site Info</a> tab se save hote hain — is Contact Page tab me sirf hero copy aur optional body hai.</p>
              <?php endif; ?>

              <form method="post" class="admin-settings-form admin-crm-form">
                <input type="hidden" name="action" value="save_page">
                <input type="hidden" name="page_key" value="<?= h($key) ?>">

                <div class="admin-form-grid-3">
                  <label class="admin-field">
                    <span class="admin-field__label">Hero kicker</span>
                    <input class="admin-input" type="text" name="hero_kicker" maxlength="120" value="<?= h($p['hero_kicker']) ?>">
                  </label>
                  <label class="admin-field admin-settings-crms-field-span2">
                    <span class="admin-field__label">Page title <span class="admin-settings-crms-req" aria-hidden="true">*</span></span>
                    <input class="admin-input" type="text" name="hero_title" maxlength="255" value="<?= h($p['hero_title']) ?>" required>
                  </label>
                  <label class="admin-field admin-crm-span-3">
                    <span class="admin-field__label">Lead / subtitle</span>
                    <textarea class="admin-input admin-crm-textarea admin-crm-textarea--sm" name="hero_lead" maxlength="1000"><?= h($p['hero_lead']) ?></textarea>
                  </label>
                  <label class="admin-field admin-crm-span-3">
                    <span class="admin-field__label">Meta description (SEO)</span>
                    <textarea class="admin-input admin-crm-textarea admin-crm-textarea--sm" name="meta_description" maxlength="500"><?= h($p['meta_description']) ?></textarea>
                  </label>
                  <?php if ($supportsBody): ?>
                  <label class="admin-field admin-crm-span-3">
                    <span class="admin-field__label">Body content (HTML)</span>
                    <span class="admin-field__hint">Legal pages (Terms / Privacy / Return) ke liye main body. Khali rakhne par template default content dikhayega.</span>
                    <textarea class="admin-input admin-crm-textarea admin-crm-textarea--lg" name="body_html"><?= h($p['body_html']) ?></textarea>
                  </label>
                  <?php endif; ?>
                </div>

                <div class="admin-settings-crms-actions">
                  <a class="admin-btn admin-btn--outline" href="crm.php?tab=<?= h($key) ?>">Cancel</a>
                  <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
                </div>

                <?php if (!empty($p['updated_at'])): ?>
                  <p class="admin-crm-meta">Last updated: <?= h((string) $p['updated_at']) ?></p>
                <?php endif; ?>
              </form>
            </section>
          <?php endif; ?>

          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
