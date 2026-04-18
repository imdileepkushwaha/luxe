<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();
$seller = seller_require_login($pdo);

$pageTitle = 'KYC & Bank Details';
$activeNav = 'kyc';

$allowedProofTypes = ['aadhaar', 'pan', 'passport', 'driving_license', 'voter_id'];
$error = '';
$success = '';

/**
 * @return array{ok:bool,path?:string,error?:string}
 */
function seller_handle_kyc_doc_upload(string $fieldName, int $sellerId, string $existingPath): array
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
        return ['ok' => false, 'error' => 'Document upload failed. Please try again.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'error' => 'Invalid document upload request.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size > (5 * 1024 * 1024)) {
        return ['ok' => false, 'error' => 'Each document should be 5MB or less.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $extByMime = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extByMime[$mime])) {
        return ['ok' => false, 'error' => 'Only PDF, JPG, PNG, WEBP files are allowed for documents.'];
    }

    $uploadDir = dirname(__DIR__) . '/uploads/seller-kyc';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'error' => 'Could not create document uploads directory.'];
    }

    try {
        $rand = bin2hex(random_bytes(4));
    } catch (Throwable) {
        $rand = (string) mt_rand(100000, 999999);
    }
    $fileName = 'seller-' . $sellerId . '-' . $fieldName . '-' . time() . '-' . $rand . '.' . $extByMime[$mime];
    $destAbs = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($tmpName, $destAbs)) {
        return ['ok' => false, 'error' => 'Could not save uploaded document.'];
    }

    if ($existingPath !== '') {
        $oldAbs = dirname(__DIR__) . '/' . ltrim($existingPath, '/');
        if (is_file($oldAbs)) {
            @unlink($oldAbs);
        }
    }

    return ['ok' => true, 'path' => 'uploads/seller-kyc/' . $fileName];
}

$sellerDetailsSt = $pdo->prepare(
    "SELECT business_name, gst_number, pan_number, aadhaar_number,
            gst_doc_path, pan_doc_path, aadhaar_doc_path,
            bank_name, bank_account_name, bank_account_number, bank_ifsc,
            address_line1, city, state, pin_code, id_proof_type, id_proof_number,
            kyc_completed, kyc_updated_at, kyc_final_approved, kyc_final_reviewed_at, kyc_rejection_reason,
            kyc_edit_request_status, kyc_edit_requested_at, kyc_edit_reviewed_at, kyc_edit_rejection_reason, kyc_edit_unlocked
     FROM seller_users
     WHERE id = ?
     LIMIT 1"
);
$sellerDetailsSt->execute([(int) $seller['id']]);
$details = $sellerDetailsSt->fetch() ?: [];
$isFinalApproved = (int) ($details['kyc_final_approved'] ?? 0) === 1;
$editRequestStatus = (string) ($details['kyc_edit_request_status'] ?? 'none');
$isEditUnlocked = $isFinalApproved && (int) ($details['kyc_edit_unlocked'] ?? 0) === 1 && $editRequestStatus === 'approved';
$isEditable = !$isFinalApproved || $isEditUnlocked;

$form = [
    'business_name' => (string) ($details['business_name'] ?? ''),
    'gst_number' => (string) ($details['gst_number'] ?? ''),
    'pan_number' => (string) ($details['pan_number'] ?? ''),
    'aadhaar_number' => (string) ($details['aadhaar_number'] ?? ''),
    'gst_doc_path' => (string) ($details['gst_doc_path'] ?? ''),
    'pan_doc_path' => (string) ($details['pan_doc_path'] ?? ''),
    'aadhaar_doc_path' => (string) ($details['aadhaar_doc_path'] ?? ''),
    'bank_name' => (string) ($details['bank_name'] ?? ''),
    'bank_account_name' => (string) ($details['bank_account_name'] ?? ''),
    'bank_account_number' => (string) ($details['bank_account_number'] ?? ''),
    'bank_ifsc' => (string) ($details['bank_ifsc'] ?? ''),
    'address_line1' => (string) ($details['address_line1'] ?? ''),
    'city' => (string) ($details['city'] ?? ''),
    'state' => (string) ($details['state'] ?? ''),
    'pin_code' => (string) ($details['pin_code'] ?? ''),
    'id_proof_type' => (string) ($details['id_proof_type'] ?? 'aadhaar'),
    'id_proof_number' => (string) ($details['id_proof_number'] ?? ''),
];
if (!in_array($form['id_proof_type'], $allowedProofTypes, true)) {
    $form['id_proof_type'] = 'aadhaar';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save_kyc');

    if ($action === 'request_edit_access') {
        if (!$isFinalApproved) {
            $error = 'KYC abhi final approved nahi hai. Aap directly details update kar sakte hain.';
        } elseif ($editRequestStatus === 'pending') {
            $error = 'Edit request already pending hai. Admin approval ka wait karein.';
        } else {
            $reqUpd = $pdo->prepare(
                "UPDATE seller_users
                 SET kyc_edit_request_status = 'pending',
                     kyc_edit_requested_at = NOW(),
                     kyc_edit_reviewed_by = NULL,
                     kyc_edit_reviewed_at = NULL,
                     kyc_edit_rejection_reason = '',
                     kyc_edit_unlocked = 0
                 WHERE id = ?
                 LIMIT 1"
            );
            $reqUpd->execute([(int) $seller['id']]);
            $success = 'Edit request admin ko bhej di gayi hai. Approval ke baad hi details edit kar payenge.';
            $editRequestStatus = 'pending';
            $isEditUnlocked = false;
            $isEditable = false;
            $details['kyc_edit_request_status'] = 'pending';
            $details['kyc_edit_requested_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $details['kyc_edit_rejection_reason'] = '';
            $details['kyc_edit_unlocked'] = 0;
        }
    } else {
        if (!$isEditable) {
            if ($isFinalApproved && $editRequestStatus === 'pending') {
                $error = 'Edit request pending hai. Admin approval ke baad hi edit kar sakte hain.';
            } else {
                $error = 'Final admin approval ke baad edit ke liye pehle request bhejna zaruri hai.';
            }
        }

        if ($error === '') {
            foreach ($form as $key => $_) {
                $form[$key] = trim((string) ($_POST[$key] ?? ''));
            }
            $form['gst_number'] = strtoupper($form['gst_number']);
            $form['pan_number'] = strtoupper($form['pan_number']);
            $form['bank_ifsc'] = strtoupper($form['bank_ifsc']);
        }

        if ($error === '' && mb_strlen($form['business_name']) < 3) {
            $error = 'Business name required hai.';
        } elseif ($error === '' && $form['gst_number'] !== '' && !preg_match('/^[0-9A-Z]{15}$/', $form['gst_number'])) {
            $error = 'GST number valid format me enter karein (15 chars).';
        } elseif ($error === '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $form['pan_number'])) {
            $error = 'Valid PAN number enter karein (example: ABCDE1234F).';
        } elseif ($error === '' && !preg_match('/^[0-9]{12}$/', $form['aadhaar_number'])) {
            $error = 'Aadhaar number 12 digits ka hona chahiye.';
        } elseif ($error === '' && mb_strlen($form['bank_name']) < 2) {
            $error = 'Bank name required hai.';
        } elseif ($error === '' && mb_strlen($form['bank_account_name']) < 3) {
            $error = 'Account holder name required hai.';
        } elseif ($error === '' && !preg_match('/^[0-9]{9,18}$/', $form['bank_account_number'])) {
            $error = 'Bank account number 9-18 digits ka hona chahiye.';
        } elseif ($error === '' && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $form['bank_ifsc'])) {
            $error = 'Valid IFSC code enter karein (example: HDFC0001234).';
        } elseif ($error === '' && ($form['address_line1'] === '' || $form['city'] === '' || $form['state'] === '')) {
            $error = 'Address, city aur state required hai.';
        } elseif ($error === '' && !preg_match('/^[0-9]{6}$/', $form['pin_code'])) {
            $error = 'PIN code 6 digits ka hona chahiye.';
        } elseif ($error === '' && !in_array($form['id_proof_type'], $allowedProofTypes, true)) {
            $error = 'Valid ID proof type select karein.';
        } elseif ($error === '' && mb_strlen($form['id_proof_number']) < 4) {
            $error = 'ID proof number required hai.';
        } else {
            $gstUpload = seller_handle_kyc_doc_upload('gst_document', (int) $seller['id'], $form['gst_doc_path']);
            if (!$gstUpload['ok']) {
                $error = (string) ($gstUpload['error'] ?? 'GST document upload failed.');
            }
            $panUpload = seller_handle_kyc_doc_upload('pan_document', (int) $seller['id'], $form['pan_doc_path']);
            if ($error === '' && !$panUpload['ok']) {
                $error = (string) ($panUpload['error'] ?? 'PAN document upload failed.');
            }
            $aadhaarUpload = seller_handle_kyc_doc_upload('aadhaar_document', (int) $seller['id'], $form['aadhaar_doc_path']);
            if ($error === '' && !$aadhaarUpload['ok']) {
                $error = (string) ($aadhaarUpload['error'] ?? 'Aadhaar document upload failed.');
            }
            if ($error === '') {
                $form['gst_doc_path'] = (string) ($gstUpload['path'] ?? $form['gst_doc_path']);
                $form['pan_doc_path'] = (string) ($panUpload['path'] ?? $form['pan_doc_path']);
                $form['aadhaar_doc_path'] = (string) ($aadhaarUpload['path'] ?? $form['aadhaar_doc_path']);
            }
        }

        if ($error === '') {
            if ($form['gst_doc_path'] === '') {
                $error = 'GST document upload required hai (PDF/JPG/PNG/WEBP).';
            } elseif ($form['pan_doc_path'] === '') {
                $error = 'PAN document upload required hai (PDF/JPG/PNG/WEBP).';
            } elseif ($form['aadhaar_doc_path'] === '') {
                $error = 'Aadhaar document upload required hai (PDF/JPG/PNG/WEBP).';
            }
        }

        if ($error === '') {
            $upd = $pdo->prepare(
                "UPDATE seller_users
                 SET business_name = ?, gst_number = ?, pan_number = ?, aadhaar_number = ?,
                     gst_doc_path = ?, pan_doc_path = ?, aadhaar_doc_path = ?,
                     bank_name = ?, bank_account_name = ?, bank_account_number = ?, bank_ifsc = ?,
                     address_line1 = ?, city = ?, state = ?, pin_code = ?,
                     id_proof_type = ?, id_proof_number = ?,
                     kyc_completed = 1, kyc_updated_at = NOW(),
                     kyc_final_approved = 0, kyc_final_reviewed_by = NULL, kyc_final_reviewed_at = NULL, kyc_rejection_reason = '',
                     kyc_edit_request_status = 'none', kyc_edit_requested_at = NULL,
                     kyc_edit_reviewed_by = NULL, kyc_edit_reviewed_at = NULL,
                     kyc_edit_rejection_reason = '', kyc_edit_unlocked = 0
                 WHERE id = ?
                 LIMIT 1"
            );
            $upd->execute([
                $form['business_name'],
                $form['gst_number'],
                $form['pan_number'],
                $form['aadhaar_number'],
                $form['gst_doc_path'],
                $form['pan_doc_path'],
                $form['aadhaar_doc_path'],
                $form['bank_name'],
                $form['bank_account_name'],
                $form['bank_account_number'],
                $form['bank_ifsc'],
                $form['address_line1'],
                $form['city'],
                $form['state'],
                $form['pin_code'],
                $form['id_proof_type'],
                $form['id_proof_number'],
                (int) $seller['id'],
            ]);
            $success = 'Updated details admin ko final approval ke liye bhej di gayi hain.';
            $details['kyc_completed'] = 1;
            $details['kyc_updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            $details['kyc_final_approved'] = 0;
            $details['kyc_final_reviewed_at'] = null;
            $details['kyc_rejection_reason'] = '';
            $details['kyc_edit_request_status'] = 'none';
            $details['kyc_edit_requested_at'] = null;
            $details['kyc_edit_reviewed_at'] = null;
            $details['kyc_edit_rejection_reason'] = '';
            $details['kyc_edit_unlocked'] = 0;
            $isFinalApproved = false;
            $editRequestStatus = 'none';
            $isEditUnlocked = false;
            $isEditable = true;
        }
    }
}

require __DIR__ . '/partials/shell-top.php';
?>

<div class="admin-page-head">
  <h1>KYC & Bank details</h1>
</div>

<style>
  .seller-kyc-status{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
  .seller-kyc-status__pill{padding:7px 10px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid #e5e7eb;background:#fff}
  .seller-kyc-status__pill--ok{border-color:#86efac;background:#f0fdf4;color:#166534}
  .seller-kyc-status__pill--warn{border-color:#fcd34d;background:#fffbeb;color:#92400e}
  .seller-kyc-status__pill--muted{border-color:#d1d5db;background:#f9fafb;color:#374151}
  .seller-kyc-doc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:10px 0 14px}
  .seller-kyc-doc-card{border:1px solid #e5e7eb;border-radius:10px;padding:10px;background:#fff}
  .seller-kyc-doc-card__title{font-weight:600;font-size:13px;color:#111827;margin-bottom:8px}
  @media (max-width: 800px){.seller-kyc-doc-grid{grid-template-columns:1fr}}
</style>

<div class="card">
  <div class="card-body">
    <?php if ($error !== ''): ?>
      <div class="seller-alert seller-alert--error" style="margin-bottom:12px"><?= h($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
      <div class="admin-del-flash admin-del-flash--ok" style="margin-bottom:12px"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="seller-kyc-status">
      <?php if ((int) ($details['kyc_completed'] ?? 0) === 1): ?>
        <span class="seller-kyc-status__pill seller-kyc-status__pill--ok">KYC Submitted</span>
      <?php else: ?>
        <span class="seller-kyc-status__pill seller-kyc-status__pill--warn">KYC Pending</span>
      <?php endif; ?>
      <?php if ((int) ($details['kyc_final_approved'] ?? 0) === 1): ?>
        <span class="seller-kyc-status__pill seller-kyc-status__pill--ok">Final Approved</span>
      <?php else: ?>
        <span class="seller-kyc-status__pill seller-kyc-status__pill--warn">Final Approval Pending</span>
      <?php endif; ?>
      <span class="seller-kyc-status__pill seller-kyc-status__pill--muted">Last Update: <?= h((string) (($details['kyc_updated_at'] ?? '') !== '' ? $details['kyc_updated_at'] : '-')) ?></span>
    </div>

    <p class="seller-help" style="margin-bottom:8px">
      Yeh details compliance aur payouts ke liye required hain.
      <?php if ((int) ($details['kyc_completed'] ?? 0) === 1 && (int) ($details['kyc_final_approved'] ?? 0) === 1): ?>
        Final approval mil chuki hai. Last approval: <?= h((string) ($details['kyc_final_reviewed_at'] ?? '-')) ?>
        <?php if ($isEditUnlocked): ?>
          Edit access admin ne approve kar diya hai. Details update karke dubara submit karein.
        <?php elseif ($editRequestStatus === 'pending'): ?>
          Edit request admin review me hai.
        <?php elseif ($editRequestStatus === 'rejected' && (string) ($details['kyc_edit_rejection_reason'] ?? '') !== ''): ?>
          Last edit request reject reason: <?= h((string) $details['kyc_edit_rejection_reason']) ?>.
        <?php else: ?>
          KYC details lock hain. Edit ke liye request bhejni hogi.
        <?php endif; ?>
      <?php elseif ((int) ($details['kyc_completed'] ?? 0) === 1): ?>
        KYC submitted hai, final admin approval pending hai.
        <?php if ((string) ($details['kyc_rejection_reason'] ?? '') !== ''): ?>
          Last review reason: <?= h((string) $details['kyc_rejection_reason']) ?>.
        <?php endif; ?>
        Last update: <?= h((string) ($details['kyc_updated_at'] ?? '-')) ?>
      <?php else: ?>
        Abhi KYC complete nahi hai.
      <?php endif; ?>
    </p>

    <div class="seller-kyc-doc-grid">
      <div class="seller-kyc-doc-card">
        <div class="seller-kyc-doc-card__title">GST Document</div>
        <?php if ($form['gst_doc_path'] !== ''): ?>
          <a class="admin-btn" style="padding:6px 10px;border:1px solid var(--admin-border)" href="../<?= h($form['gst_doc_path']) ?>" target="_blank" rel="noopener">View current</a>
        <?php else: ?>
          <span class="seller-help">Not uploaded</span>
        <?php endif; ?>
      </div>
      <div class="seller-kyc-doc-card">
        <div class="seller-kyc-doc-card__title">PAN Document</div>
        <?php if ($form['pan_doc_path'] !== ''): ?>
          <a class="admin-btn" style="padding:6px 10px;border:1px solid var(--admin-border)" href="../<?= h($form['pan_doc_path']) ?>" target="_blank" rel="noopener">View current</a>
        <?php else: ?>
          <span class="seller-help">Not uploaded</span>
        <?php endif; ?>
      </div>
      <div class="seller-kyc-doc-card">
        <div class="seller-kyc-doc-card__title">Aadhaar Document</div>
        <?php if ($form['aadhaar_doc_path'] !== ''): ?>
          <a class="admin-btn" style="padding:6px 10px;border:1px solid var(--admin-border)" href="../<?= h($form['aadhaar_doc_path']) ?>" target="_blank" rel="noopener">View current</a>
        <?php else: ?>
          <span class="seller-help">Not uploaded</span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isFinalApproved && !$isEditUnlocked): ?>
      <form method="post" style="margin-bottom:14px">
        <input type="hidden" name="action" value="request_edit_access">
        <button type="submit" class="admin-btn" style="border:1px solid var(--admin-border)" <?= $editRequestStatus === 'pending' ? 'disabled' : '' ?>>
          &#9998; Request edit access
        </button>
      </form>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="seller-form">
      <input type="hidden" name="action" value="save_kyc">
      <h3 style="margin:0 0 10px">Business details</h3>
      <div class="seller-form__row">
        <div>
          <label for="business_name">Business name</label>
          <input id="business_name" name="business_name" required value="<?= h($form['business_name']) ?>" placeholder="Brand / company name" <?= !$isEditable ? 'disabled' : '' ?>>
        </div>
        <div>
          <label for="gst_number">GST number (optional)</label>
          <input id="gst_number" name="gst_number" maxlength="15" value="<?= h($form['gst_number']) ?>" placeholder="22AAAAA0000A1Z5" <?= !$isEditable ? 'disabled' : '' ?>>
          <label for="gst_document" style="margin-top:8px">GST document (PDF/Image)</label>
          <input id="gst_document" type="file" name="gst_document" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" <?= !$isEditable ? 'disabled' : '' ?>>
          <?php if ($form['gst_doc_path'] !== ''): ?>
            <p class="seller-help" style="margin-top:6px">Current file: <a href="../<?= h($form['gst_doc_path']) ?>" target="_blank" rel="noopener">Open GST document</a></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="seller-form__row">
        <div>
          <label for="pan_number">PAN number</label>
          <input id="pan_number" name="pan_number" required maxlength="10" value="<?= h($form['pan_number']) ?>" placeholder="ABCDE1234F" <?= !$isEditable ? 'disabled' : '' ?>>
          <label for="pan_document" style="margin-top:8px">PAN document (PDF/Image)</label>
          <input id="pan_document" type="file" name="pan_document" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" <?= !$isEditable ? 'disabled' : '' ?>>
          <?php if ($form['pan_doc_path'] !== ''): ?>
            <p class="seller-help" style="margin-top:6px">Current file: <a href="../<?= h($form['pan_doc_path']) ?>" target="_blank" rel="noopener">Open PAN document</a></p>
          <?php endif; ?>
        </div>
        <div>
          <label for="aadhaar_number">Aadhaar number</label>
          <input id="aadhaar_number" name="aadhaar_number" required pattern="^[0-9]{12}$" value="<?= h($form['aadhaar_number']) ?>" placeholder="12 digit Aadhaar" <?= !$isEditable ? 'disabled' : '' ?>>
          <label for="aadhaar_document" style="margin-top:8px">Aadhaar document (PDF/Image)</label>
          <input id="aadhaar_document" type="file" name="aadhaar_document" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" <?= !$isEditable ? 'disabled' : '' ?>>
          <?php if ($form['aadhaar_doc_path'] !== ''): ?>
            <p class="seller-help" style="margin-top:6px">Current file: <a href="../<?= h($form['aadhaar_doc_path']) ?>" target="_blank" rel="noopener">Open Aadhaar document</a></p>
          <?php endif; ?>
        </div>
      </div>

      <h3 style="margin:16px 0 10px">Bank details</h3>
      <div class="seller-form__row">
        <div>
          <label for="bank_name">Bank name</label>
          <input id="bank_name" name="bank_name" required value="<?= h($form['bank_name']) ?>" placeholder="e.g. HDFC Bank" <?= !$isEditable ? 'disabled' : '' ?>>
        </div>
        <div>
          <label for="bank_account_name">Account holder name</label>
          <input id="bank_account_name" name="bank_account_name" required value="<?= h($form['bank_account_name']) ?>" placeholder="Name as per bank" <?= !$isEditable ? 'disabled' : '' ?>>
        </div>
      </div>
      <div class="seller-form__row">
        <div>
          <label for="bank_account_number">Account number</label>
          <input id="bank_account_number" name="bank_account_number" required pattern="^[0-9]{9,18}$" value="<?= h($form['bank_account_number']) ?>" placeholder="9-18 digit account number" <?= !$isEditable ? 'disabled' : '' ?>>
        </div>
        <div>
          <label for="bank_ifsc">IFSC code</label>
          <input id="bank_ifsc" name="bank_ifsc" required value="<?= h($form['bank_ifsc']) ?>" placeholder="HDFC0001234" <?= !$isEditable ? 'disabled' : '' ?>>
        </div>
      </div>

      <h3 style="margin:16px 0 10px">Business address & proof</h3>
      <div>
        <label for="address_line1">Address line</label>
        <input id="address_line1" name="address_line1" required value="<?= h($form['address_line1']) ?>" placeholder="Shop / office address" <?= !$isEditable ? 'disabled' : '' ?>>
      </div>

      <div class="seller-form__row">
        <div>
          <label for="city">City</label>
          <input id="city" name="city" required value="<?= h($form['city']) ?>" placeholder="City" <?= !$isEditable ? 'disabled' : '' ?>>
        </div>
        <div>
          <label for="state">State</label>
          <input id="state" name="state" required value="<?= h($form['state']) ?>" placeholder="State" <?= !$isEditable ? 'disabled' : '' ?>>
        </div>
      </div>

      <div class="seller-form__row">
        <div>
          <label for="pin_code">PIN code</label>
          <input id="pin_code" name="pin_code" required pattern="^[0-9]{6}$" value="<?= h($form['pin_code']) ?>" placeholder="6 digit PIN" <?= !$isEditable ? 'disabled' : '' ?>>
        </div>
        <div>
          <label for="id_proof_type">ID proof type</label>
          <select id="id_proof_type" name="id_proof_type" required <?= !$isEditable ? 'disabled' : '' ?>>
            <option value="aadhaar" <?= $form['id_proof_type'] === 'aadhaar' ? 'selected' : '' ?>>Aadhaar</option>
            <option value="pan" <?= $form['id_proof_type'] === 'pan' ? 'selected' : '' ?>>PAN</option>
            <option value="passport" <?= $form['id_proof_type'] === 'passport' ? 'selected' : '' ?>>Passport</option>
            <option value="driving_license" <?= $form['id_proof_type'] === 'driving_license' ? 'selected' : '' ?>>Driving License</option>
            <option value="voter_id" <?= $form['id_proof_type'] === 'voter_id' ? 'selected' : '' ?>>Voter ID</option>
          </select>
        </div>
      </div>

      <div>
        <label for="id_proof_number">ID proof number</label>
        <input id="id_proof_number" name="id_proof_number" required value="<?= h($form['id_proof_number']) ?>" placeholder="Document number" <?= !$isEditable ? 'disabled' : '' ?>>
      </div>

      <div class="seller-actions" style="margin-top:12px">
        <button class="admin-btn admin-btn--primary" type="submit" <?= !$isEditable ? 'disabled' : '' ?>>Save details</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
