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
        if (!in_array($pick, ['default', 'theme-1', 'theme-2', 'theme-3'], true)) {
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
if (!in_array($storefrontThemeCurrent, ['default', 'theme-1', 'theme-2', 'theme-3'], true)) {
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
            <span class="admin-page-head__eyebrow">CRM &bull; CMS PLATFORM</span>
            <h1>Site &amp; Content</h1>
            <p class="admin-page-head__lede">Manage your brand identity, storefront pages, and customer support content. Every change is instantly synced to your live storefront themes.</p>
          </div>
          <div class="admin-page-head__actions">
            <a href="../index.php" class="admin-btn admin-btn--outline" target="_blank">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
              View Storefront
            </a>
          </div>
        </div>

        <div class="card admin-crm-card">
          <div class="admin-crm-tabs" role="tablist" aria-label="CRM sections">
            <?php
            $tabMeta = [
                'site_info'      => ['label' => 'Site Info', 'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
                'contact'        => ['label' => 'Contact',   'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'],
                'about'          => ['label' => 'About',     'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 12.75C8.83 12.75 6.25 10.17 6.25 7C6.25 3.83 8.83 1.25 12 1.25C15.17 1.25 17.75 3.83 17.75 7C17.75 10.17 15.17 12.75 12 12.75ZM12 2.75C9.66 2.75 7.75 4.66 7.75 7C7.75 9.34 9.66 11.25 12 11.25C14.34 11.25 16.25 9.34 16.25 7C16.25 4.66 14.34 2.75 12 2.75Z" fill="currentColor"></path>
                        <path d="M20.5901 22.75C20.1801 22.75 19.8401 22.41 19.8401 22C19.8401 18.55 16.3202 15.75 12.0002 15.75C7.68015 15.75 4.16016 18.55 4.16016 22C4.16016 22.41 3.82016 22.75 3.41016 22.75C3.00016 22.75 2.66016 22.41 2.66016 22C2.66016 17.73 6.85015 14.25 12.0002 14.25C17.1502 14.25 21.3401 17.73 21.3401 22C21.3401 22.41 21.0001 22.75 20.5901 22.75Z" fill="currentColor"></path>
                    </svg>'],
                'faq'            => ['label' => 'FAQ',       'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'],
                'terms'          => ['label' => 'Terms',     'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
                'privacy'        => ['label' => 'Privacy',   'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'],
                'return_policy'  => ['label' => 'Return',    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6"/><path d="M16 12l5 5-5 5"/><path d="M21 17H9"/></svg>'],
                'theme'          => ['label' => 'Theme',     'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>'],
            ];
            foreach ($tabMeta as $tk => $tm):
                $isActive = $tab === $tk;
            ?>
              <a class="admin-crm-tab<?= $isActive ? ' admin-crm-tab--active' : '' ?>"
                 role="tab"
                 aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                 href="crm.php?tab=<?= h($tk) ?>">
                <?= $tm['icon'] ?>
                <span><?= h($tm['label']) ?></span>
              </a>
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
                      <span class="admin-field__label">Brand Logo</span>
                      <span class="admin-field__hint">Recommended size: 200x200px (PNG/SVG preferred). Max 800KB.</span>
                      <input class="admin-input" type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp">
                    </label>
                    <?php if ($logoUrl !== ''): ?>
                      <label class="admin-crm-checkbox">
                        <input type="checkbox" name="remove_logo" value="1">
                        <span>Remove current logo and use text fallback</span>
                      </label>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="admin-form-grid-3">
                  <label class="admin-field">
                    <span class="admin-field__label">Brand name <span class="admin-settings-crms-req" aria-hidden="true">*</span></span>
                    <input class="admin-input" type="text" name="brand" required maxlength="80" value="<?= h($contact['brand']) ?>" placeholder="e.g. LUXE Store">
                  </label>
                  <label class="admin-field">
                    <span class="admin-field__label">Contact email</span>
                    <input class="admin-input" type="email" name="email" maxlength="200" value="<?= h($contact['email']) ?>" placeholder="support@luxe.com">
                  </label>
                  <label class="admin-field">
                    <span class="admin-field__label">Mobile / phone number</span>
                    <input class="admin-input" type="text" name="phone" maxlength="60" value="<?= h($contact['phone']) ?>" placeholder="+91 98765 43210">
                  </label>
                  <label class="admin-field">
                    <span class="admin-field__label">Working hours</span>
                    <input class="admin-input" type="text" name="hours" maxlength="120" value="<?= h($contact['hours']) ?>" placeholder="Mon-Sat: 9AM - 6PM">
                  </label>
                  <label class="admin-field admin-settings-crms-field-span2">
                    <span class="admin-field__label">Office / business address</span>
                    <textarea class="admin-input admin-crm-textarea admin-crm-textarea--sm" name="address" maxlength="500" placeholder="Full physical address..."><?= h($contact['address']) ?></textarea>
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

              <div class="admin-crm-theme-hero">
                <div class="admin-crm-theme-hero__left">
                  <span class="admin-crm-theme-hero__eyebrow">Live storefront skin</span>
                  <p class="admin-crm-theme-hero__title">
                    Current theme:
                    <strong>
                      <?php
                        $activeThemeLabel = $storefrontThemeCurrent === 'default' ? 'Theme (Default)'
                          : ($storefrontThemeCurrent === 'theme-1' ? 'Theme 1' 
                          : ($storefrontThemeCurrent === 'theme-2' ? 'Theme 2' : 'Theme 3'));
                        echo h($activeThemeLabel);
                      ?>
                    </strong>
                  </p>
                  <p class="admin-crm-theme-hero__lede">Theme switch instant hota hai aur pages same URLs par serve hote hain. Sirf visual shell, header/footer aur component styles change hote hain.</p>
                </div>
                <div class="admin-crm-theme-hero__right">
                  <div class="admin-crm-theme-hero__routes">
                    <span>Applies to:</span>
                    <code class="admin-crm-code">/index.php</code>
                    <code class="admin-crm-code">/product-list.php</code>
                    <code class="admin-crm-code">/product.php</code>
                  </div>
                </div>
              </div>

              <div class="admin-crm-theme-grid">
                <?php
                $themeOptions = [
                    'default' => [
                        'title' => 'Classic Luxe',
                        'desc' => 'The original visual identity. Clean, safe, and lightning fast.',
                        'chips' => ['Reliable', 'Clean', 'Stable'],
                        'gradient' => 'linear-gradient(135deg, #6366f1, #818cf8)'
                    ],
                    'theme-1' => [
                        'title' => 'Modern Editorial',
                        'desc' => 'Content-first approach with bold typography and elegant white space.',
                        'chips' => ['Modern', 'Sleek', 'Vibrant'],
                        'gradient' => 'linear-gradient(135deg, #0ea5e9, #6366f1)'
                    ],
                    'theme-2' => [
                        'title' => 'Premium Glass',
                        'desc' => 'High-end glassmorphic skin with vibrant gradients and smooth interactions.',
                        'chips' => ['Premium', 'High-end', 'Visual'],
                        'gradient' => 'linear-gradient(135deg, #ef4444, #f97316)'
                    ],
                    'theme-3' => [
                        'title' => 'Elite Dark',
                        'desc' => 'Exclusive midnight glassmorphism with high-contrast accents and premium shadows.',
                        'chips' => ['Bold', 'Modern', 'Trendy'],
                        'gradient' => 'linear-gradient(135deg, #1e1b4b, #4338ca)'
                    ],
                ];
                foreach ($themeOptions as $tk => $meta):
                    $isLive = $storefrontThemeCurrent === $tk;
                ?>
                <div class="admin-crm-theme-card<?= $isLive ? ' admin-crm-theme-card--active' : '' ?>">
                  <div class="admin-crm-theme-card__preview" style="background: <?= $meta['gradient'] ?>; padding: 20px; display: flex; flex-direction: column; gap: 10px; position: relative; overflow: hidden;" aria-hidden="true">
                    <div style="width: 40%; height: 12px; background: rgba(255,255,255,0.4); border-radius: 99px;"></div>
                    <div style="width: 100%; height: 8px; background: rgba(255,255,255,0.2); border-radius: 99px;"></div>
                    <div style="width: 70%; height: 8px; background: rgba(255,255,255,0.2); border-radius: 99px;"></div>
                    <div style="position: absolute; bottom: -20px; right: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                  </div>
                  <div class="admin-crm-theme-card__top">
                    <h3 class="admin-crm-theme-card__title"><?= h((string) $meta['title']) ?></h3>
                    <?php if ($isLive): ?>
                      <span class="admin-status admin-status--delivered">Live</span>
                    <?php endif; ?>
                  </div>
                  <p class="admin-crm-theme-card__desc"><?= h($meta['desc']) ?></p>
                  <div class="admin-crm-theme-card__chips">
                    <?php foreach (($meta['chips'] ?? []) as $chip): ?>
                      <span class="admin-crm-theme-chip"><?= h((string) $chip) ?></span>
                    <?php endforeach; ?>
                  </div>
                  <?php if (!$isLive): ?>
                    <form method="post" class="admin-crm-theme-card__form">
                      <input type="hidden" name="action" value="save_storefront_theme">
                      <input type="hidden" name="storefront_theme" value="<?= h($tk) ?>">
                      <button type="submit" class="admin-btn admin-btn--primary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 8px;"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                        Activate Theme
                      </button>
                    </form>
                  <?php else: ?>
                    <div class="admin-crm-theme-card__note">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                      <span>Currently active</span>
                    </div>
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
                          <div class="admin-crm-faq-row__main">
                            <span class="admin-crm-faq-row__q"><?= h($f['question']) ?></span>
                            <span class="admin-crm-faq-row__meta">
                              #<?= (int) $f['id'] ?> &bull; Order <?= (int) $f['sort_order'] ?>
                            </span>
                          </div>
                          <div class="admin-crm-faq-row__status">
                            <?php if ($f['is_active']): ?>
                              <span class="admin-status admin-status--delivered">Active</span>
                            <?php else: ?>
                              <span class="admin-status admin-status--cancelled">Hidden</span>
                            <?php endif; ?>
                            <svg class="admin-crm-faq-row__chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                          </div>
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
                  <button type="submit" class="admin-btn admin-btn--primary">Update Content</button>
                </div>

                <?php if (!empty($p['updated_at'])): ?>
                  <div class="admin-crm-meta">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 6px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>System Sync: Last modified <?= h((string) $p['updated_at']) ?></span>
                  </div>
                <?php endif; ?>
              </form>
            </section>
          <?php endif; ?>

          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
