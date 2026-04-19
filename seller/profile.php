<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'Profile';
$activeNav = 'profile';

/**
 * @return array{ok:bool,path?:string,error?:string}
 */
function seller_handle_brand_asset_upload(string $fieldName, int $sellerId, string $existingPath): array
{
    $file = $_FILES[$fieldName] ?? null;
    if (!is_array($file)) {
        return ['ok' => true, 'path' => $existingPath];
    }
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => $existingPath];
    }
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Image upload failed. Please try again.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'error' => 'Invalid image upload request.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size > (5 * 1024 * 1024)) {
        return ['ok' => false, 'error' => 'Logo/Banner image 5MB se chhoti honi chahiye.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }
    $extByMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($extByMime[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, WEBP, GIF images allowed for logo/banner.'];
    }

    $uploadDir = dirname(__DIR__) . '/uploads/seller-branding';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'error' => 'Could not create branding uploads directory.'];
    }

    try {
        $rand = bin2hex(random_bytes(4));
    } catch (Throwable) {
        $rand = (string) mt_rand(100000, 999999);
    }
    $fileName = 'seller-' . $sellerId . '-' . $fieldName . '-' . time() . '-' . $rand . '.' . $extByMime[$mime];
    $destAbs = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmpName, $destAbs)) {
        return ['ok' => false, 'error' => 'Could not save uploaded image.'];
    }

    if ($existingPath !== '') {
        $oldAbs = dirname(__DIR__) . '/' . ltrim($existingPath, '/');
        if (is_file($oldAbs)) {
            @unlink($oldAbs);
        }
    }

    return ['ok' => true, 'path' => 'uploads/seller-branding/' . $fileName];
}

$flash = '';
$flashOk = false;

$profileSt = $pdo->prepare(
    'SELECT id, full_name, email, allowed_categories, business_name, gst_number, pan_number, aadhaar_number,
            bank_name, bank_account_name, bank_account_number, bank_ifsc,
            address_line1, city, state, pin_code,
            id_proof_type, id_proof_number,
            gst_doc_path, pan_doc_path, aadhaar_doc_path, phone_number, business_address, logo_path, banner_path,
            kyc_completed, kyc_updated_at, kyc_final_approved, kyc_final_reviewed_at, kyc_rejection_reason,
            kyc_edit_request_status, kyc_edit_requested_at, kyc_edit_reviewed_at, kyc_edit_rejection_reason
     FROM seller_users
     WHERE id = ?
     LIMIT 1'
);
$profileSt->execute([(int) $seller['id']]);
$profile = $profileSt->fetch() ?: [];

if ($profile === []) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_profile_details') {
    $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
    $businessAddress = trim((string) ($_POST['business_address'] ?? ''));
    if (strlen($phoneNumber) > 40) {
        $phoneNumber = substr($phoneNumber, 0, 40);
    }
    if (strlen($businessAddress) > 255) {
        $businessAddress = substr($businessAddress, 0, 255);
    }

    if ($phoneNumber !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $phoneNumber)) {
        $flash = 'Phone number valid format me daalein (8-20 chars).';
    } elseif ($businessAddress !== '' && mb_strlen($businessAddress) < 6) {
        $flash = 'Business address minimum 6 characters ka hona chahiye.';
    } else {
        $logoUpload = seller_handle_brand_asset_upload('logo_file', (int) $seller['id'], (string) ($profile['logo_path'] ?? ''));
        if (!$logoUpload['ok']) {
            $flash = (string) ($logoUpload['error'] ?? 'Logo upload failed.');
        }
        $bannerUpload = seller_handle_brand_asset_upload('banner_file', (int) $seller['id'], (string) ($profile['banner_path'] ?? ''));
        if ($flash === '' && !$bannerUpload['ok']) {
            $flash = (string) ($bannerUpload['error'] ?? 'Banner upload failed.');
        }
        if ($flash === '') {
            $logoPath = (string) ($logoUpload['path'] ?? (string) ($profile['logo_path'] ?? ''));
            $bannerPath = (string) ($bannerUpload['path'] ?? (string) ($profile['banner_path'] ?? ''));
            $upd = $pdo->prepare(
                'UPDATE seller_users
                 SET phone_number = ?, business_address = ?, logo_path = ?, banner_path = ?
                 WHERE id = ?
                 LIMIT 1'
            );
            $upd->execute([$phoneNumber, $businessAddress, $logoPath, $bannerPath, (int) $seller['id']]);
            $flash = 'Profile details updated successfully.';
            $flashOk = true;

            $profile['phone_number'] = $phoneNumber;
            $profile['business_address'] = $businessAddress;
            $profile['logo_path'] = $logoPath;
            $profile['banner_path'] = $bannerPath;
        }
    }
}

$allowedCategories = array_values(array_filter(array_map('trim', explode(',', (string) ($profile['allowed_categories'] ?? ''))), static fn(string $v): bool => $v !== ''));
$kycCompleted = (int) ($profile['kyc_completed'] ?? 0) === 1;
$kycFinalApproved = (int) ($profile['kyc_final_approved'] ?? 0) === 1;

function seller_profile_format_dt(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('M j, Y · g:i A');
    } catch (Throwable) {
        return $raw;
    }
}

function seller_profile_edit_status_label(string $status): string
{
    $s = strtolower(trim($status));

    return match ($s) {
        'none', '' => 'None',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        default => $s !== '' ? ucfirst($s) : '—',
    };
}

$editReqStatus = (string) ($profile['kyc_edit_request_status'] ?? 'none');
$editReqLabel = seller_profile_edit_status_label($editReqStatus);

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-txn-head seller-profile-page-head">
          <div>
            <h1>Profile</h1>
            <p class="seller-txn-subtitle">Yahan <strong>contact &amp; branding</strong> update hoti hai. GST, bank, docs — sab <a href="kyc-details.php">KYC &amp; Bank</a> page par.</p>
          </div>
          <div class="admin-page-head__actions seller-txn-card-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="../seller-store.php?id=<?= (int) ($profile['id'] ?? 0) ?>" target="_blank" rel="noopener">View store</a>
            <a class="admin-btn admin-btn--primary" href="kyc-details.php">KYC &amp; Bank</a>
          </div>
        </div>

        <?php if ($flash !== ''): ?>
          <div class="seller-profile-flash seller-alert<?= $flashOk ? ' seller-alert--success' : ' seller-alert--error' ?>"><?= h($flash) ?></div>
        <?php endif; ?>

        <div class="seller-kpi seller-txn-kpi seller-profile-kpi">
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">KYC submitted</div>
              <div class="seller-kpi-card__value"><?= $kycCompleted ? 'Yes' : 'No' ?></div>
              <div class="seller-kpi-card__hint">Updated: <?= h(seller_profile_format_dt((string) ($profile['kyc_updated_at'] ?? ''))) ?></div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 4v6c0 5-3.5 9.74-7 10-3.5-.26-7-5-7-10V6l7-4z"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Final approval</div>
              <div class="seller-kpi-card__value"><?= $kycFinalApproved ? 'Approved' : 'Pending' ?></div>
              <div class="seller-kpi-card__hint">Reviewed: <?= h(seller_profile_format_dt((string) ($profile['kyc_final_reviewed_at'] ?? ''))) ?></div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">KYC edit request</div>
              <div class="seller-kpi-card__value"><?= h($editReqLabel) ?></div>
              <div class="seller-kpi-card__hint">Requested: <?= h(seller_profile_format_dt((string) ($profile['kyc_edit_requested_at'] ?? ''))) ?></div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17.25V21h3.75L19.81 7.94l-3.75-3.75z"/></svg>
            </div>
          </div>
        </div>

        <?php if (trim((string) ($profile['kyc_rejection_reason'] ?? '')) !== '' || trim((string) ($profile['kyc_edit_rejection_reason'] ?? '')) !== ''): ?>
        <div class="seller-profile-callouts">
          <?php if (trim((string) ($profile['kyc_rejection_reason'] ?? '')) !== ''): ?>
            <div class="seller-profile-callout seller-profile-callout--warn" role="status">
              <p class="seller-profile-callout__title">Last KYC review note</p>
              <p class="seller-profile-callout__text"><?= h((string) $profile['kyc_rejection_reason']) ?></p>
            </div>
          <?php endif; ?>
          <?php if (trim((string) ($profile['kyc_edit_rejection_reason'] ?? '')) !== ''): ?>
            <div class="seller-profile-callout seller-profile-callout--warn" role="status">
              <p class="seller-profile-callout__title">Last edit-request note</p>
              <p class="seller-profile-callout__text"><?= h((string) $profile['kyc_edit_rejection_reason']) ?></p>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="card seller-txn-card seller-profile-card">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Account &amp; identity</h2>
              <p class="card-subtitle seller-txn-card-sub">KYC se aayi hui fields read-only yahan dikhti hain. Sirf phone / address / branding neeche wale form se badal sakte ho.</p>
            </div>
          </div>
          <div class="card-body seller-profile-card-body">
            <div class="seller-detail-grid seller-profile-detail-grid">
              <div class="seller-detail-item seller-profile-detail-item"><span>Full name</span><strong><?= h((string) ($profile['full_name'] ?? '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>Email</span><strong><?= h((string) ($profile['email'] ?? '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>Business name</span><strong><?= h((string) ($profile['business_name'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>Phone (on file)</span><strong><?= h((string) ($profile['phone_number'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-detail-item--wide seller-profile-detail-item"><span>Business address (on file)</span><strong><?= h((string) ($profile['business_address'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>Allowed categories</span><strong><?= h($allowedCategories !== [] ? implode(', ', $allowedCategories) : '—') ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>GST number</span><strong><?= h((string) ($profile['gst_number'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>PAN number</span><strong><?= h((string) ($profile['pan_number'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>Aadhaar number</span><strong><?= h((string) ($profile['aadhaar_number'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>ID proof</span><strong><?= h((string) ($profile['id_proof_type'] ?: '—')) ?> · <?= h((string) ($profile['id_proof_number'] ?: '—')) ?></strong></div>
            </div>
          </div>
        </div>

        <div class="card seller-txn-card seller-profile-card">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Store branding</h2>
              <p class="card-subtitle seller-txn-card-sub">Logo square, banner wide — buyer storefront par dikhte hain.</p>
            </div>
          </div>
          <div class="card-body seller-profile-card-body">
            <div class="seller-profile-brand-grid">
              <div class="seller-profile-brand-card">
                <span class="seller-profile-brand-label">Logo</span>
                <?php if ((string) ($profile['logo_path'] ?? '') !== ''): ?>
                  <img src="../<?= h((string) $profile['logo_path']) ?>" alt="Seller logo" class="seller-brand-media seller-brand-media--logo seller-profile-brand-img">
                <?php else: ?>
                  <div class="seller-profile-brand-placeholder">Abhi koi logo upload nahi</div>
                <?php endif; ?>
              </div>
              <div class="seller-profile-brand-card seller-profile-brand-card--wide">
                <span class="seller-profile-brand-label">Banner</span>
                <?php if ((string) ($profile['banner_path'] ?? '') !== ''): ?>
                  <img src="../<?= h((string) $profile['banner_path']) ?>" alt="Seller banner" class="seller-brand-media seller-brand-media--banner seller-profile-brand-img">
                <?php else: ?>
                  <div class="seller-profile-brand-placeholder seller-profile-brand-placeholder--banner">Abhi koi banner upload nahi</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card seller-txn-card seller-profile-card">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Quick edits</h2>
              <p class="card-subtitle seller-txn-card-sub">Phone, address, logo, banner. Baaki changes ke liye <a href="kyc-details.php">KYC &amp; Bank</a> kholo.</p>
            </div>
          </div>
          <div class="card-body seller-profile-card-body">
            <form method="post" enctype="multipart/form-data" class="seller-profile-form seller-withdraw-form">
              <input type="hidden" name="action" value="save_profile_details">
              <div class="seller-form__row">
                <div>
                  <label for="phone_number">Phone number</label>
                  <input id="phone_number" class="seller-badge-input" type="text" name="phone_number" maxlength="40" placeholder="+91 9876543210" value="<?= h((string) ($profile['phone_number'] ?? '')) ?>" autocomplete="tel">
                </div>
                <div>
                  <label for="business_address">Business address</label>
                  <textarea id="business_address" class="seller-badge-input seller-profile-textarea" name="business_address" maxlength="255" rows="3" placeholder="Shop / office address"><?= h((string) ($profile['business_address'] ?? '')) ?></textarea>
                </div>
              </div>
              <div class="seller-form__row seller-profile-upload-row">
                <div>
                  <label for="logo_file">Logo file</label>
                  <input id="logo_file" class="seller-badge-input seller-profile-file-input" type="file" name="logo_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                  <p class="seller-profile-file-hint">JPG / PNG / WebP / GIF · max <strong>5 MB</strong> · square suggest</p>
                </div>
                <div>
                  <label for="banner_file">Banner file</label>
                  <input id="banner_file" class="seller-badge-input seller-profile-file-input" type="file" name="banner_file" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                  <p class="seller-profile-file-hint">Wide ratio · max <strong>5 MB</strong></p>
                </div>
              </div>
              <div class="seller-actions seller-profile-form-actions">
                <button class="admin-btn admin-btn--primary" type="submit">Save changes</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card seller-txn-card seller-profile-card seller-profile-card--last">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Bank &amp; documents</h2>
              <p class="card-subtitle seller-txn-card-sub">Read-only snapshot. Update flow <strong>KYC &amp; Bank</strong> par.</p>
            </div>
            <a class="admin-btn admin-btn--outline" href="kyc-details.php">Open KYC</a>
          </div>
          <div class="card-body seller-profile-card-body">
            <div class="seller-detail-grid seller-profile-detail-grid">
              <div class="seller-detail-item seller-profile-detail-item"><span>Bank name</span><strong><?= h((string) ($profile['bank_name'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>Account holder</span><strong><?= h((string) ($profile['bank_account_name'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>Account number</span><strong class="seller-profile-mono"><?= h((string) ($profile['bank_account_number'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item"><span>IFSC</span><strong class="seller-profile-mono"><?= h((string) ($profile['bank_ifsc'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-detail-item--wide seller-profile-detail-item"><span>Registered address</span><strong><?= h((string) ($profile['address_line1'] ?: '—')) ?>, <?= h((string) ($profile['city'] ?: '—')) ?>, <?= h((string) ($profile['state'] ?: '—')) ?> — <?= h((string) ($profile['pin_code'] ?: '—')) ?></strong></div>
              <div class="seller-detail-item seller-profile-detail-item">
                <span>GST document</span>
                <strong class="seller-profile-doc-cell">
                  <?php if ((string) ($profile['gst_doc_path'] ?? '') !== ''): ?>
                    <a class="seller-edit-btn seller-profile-doc-btn" href="../<?= h((string) $profile['gst_doc_path']) ?>" target="_blank" rel="noopener">Open</a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </strong>
              </div>
              <div class="seller-detail-item seller-profile-detail-item">
                <span>PAN document</span>
                <strong class="seller-profile-doc-cell">
                  <?php if ((string) ($profile['pan_doc_path'] ?? '') !== ''): ?>
                    <a class="seller-edit-btn seller-profile-doc-btn" href="../<?= h((string) $profile['pan_doc_path']) ?>" target="_blank" rel="noopener">Open</a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </strong>
              </div>
              <div class="seller-detail-item seller-profile-detail-item">
                <span>Aadhaar document</span>
                <strong class="seller-profile-doc-cell">
                  <?php if ((string) ($profile['aadhaar_doc_path'] ?? '') !== ''): ?>
                    <a class="seller-edit-btn seller-profile-doc-btn" href="../<?= h((string) $profile['aadhaar_doc_path']) ?>" target="_blank" rel="noopener">Open</a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </strong>
              </div>
            </div>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
