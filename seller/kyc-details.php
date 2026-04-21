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
    } elseif ($action === 'cancel_edit_unlock') {
        if (!$isFinalApproved || !$isEditUnlocked) {
            $error = 'Edit unlock active nahi hai.';
        } else {
            $cancelUpd = $pdo->prepare(
                "UPDATE seller_users
                 SET kyc_edit_request_status = 'none',
                     kyc_edit_requested_at = NULL,
                     kyc_edit_reviewed_by = NULL,
                     kyc_edit_reviewed_at = NULL,
                     kyc_edit_rejection_reason = '',
                     kyc_edit_unlocked = 0
                 WHERE id = ?
                   AND kyc_final_approved = 1
                   AND kyc_edit_unlocked = 1
                   AND kyc_edit_request_status = 'approved'
                 LIMIT 1"
            );
            $cancelUpd->execute([(int) $seller['id']]);
            if ($cancelUpd->rowCount() < 1) {
                $error = 'Lock wapas nahi ho saka. Page refresh karke dubara try karein.';
            } else {
                $success = 'Edit band kar diya — details phir se lock hain. Zarurat ho to dubara request bhej sakte ho.';
                $editRequestStatus = 'none';
                $isEditUnlocked = false;
                $isEditable = false;
                $details['kyc_edit_request_status'] = 'none';
                $details['kyc_edit_requested_at'] = null;
                $details['kyc_edit_reviewed_at'] = null;
                $details['kyc_edit_rejection_reason'] = '';
                $details['kyc_edit_unlocked'] = 0;
            }
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
                if ($key === 'gst_doc_path' || $key === 'pan_doc_path' || $key === 'aadhaar_doc_path') {
                    continue;
                }
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

function seller_kyc_format_dt(?string $raw): string
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

function seller_kyc_edit_status_label(string $status): string
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

$kycSubmitted = (int) ($details['kyc_completed'] ?? 0) === 1;
$editReqLabel = seller_kyc_edit_status_label($editRequestStatus);

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-page-head seller-txn-head seller-kyc-page-head">
          <div>
            <h1>KYC &amp; Bank</h1>
            <p class="seller-txn-subtitle">Compliance aur payouts ke liye business, bank, address aur documents. Submit ke baad <strong>admin final approval</strong> hota hai. Phone / branding <a href="profile.php">Profile</a> par.</p>
          </div>
          <div class="admin-page-head__actions seller-txn-card-head-actions">
            <a class="admin-btn admin-btn--ghost-light" href="profile.php">Profile</a>
          </div>
        </div>

        <div class="seller-kpi seller-txn-kpi seller-kyc-kpi">
          <div class="seller-kpi-card seller-kpi-card--products">
            <div>
              <div class="seller-kpi-card__label">KYC packet</div>
              <div class="seller-kpi-card__value"><?= $kycSubmitted ? 'Submitted' : 'Draft' ?></div>
              <div class="seller-kpi-card__hint">Updated: <?= h(seller_kyc_format_dt((string) ($details['kyc_updated_at'] ?? ''))) ?></div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l7 4v6c0 5-3.5 9.74-7 10-3.5-.26-7-5-7-10V6l7-4z"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--revenue">
            <div>
              <div class="seller-kpi-card__label">Final approval</div>
              <div class="seller-kpi-card__value"><?= $isFinalApproved ? 'Approved' : 'Pending' ?></div>
              <div class="seller-kpi-card__hint">Reviewed: <?= h(seller_kyc_format_dt((string) ($details['kyc_final_reviewed_at'] ?? ''))) ?></div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 13 4 4L19 7"/></svg>
            </div>
          </div>
          <div class="seller-kpi-card seller-kpi-card--orders">
            <div>
              <div class="seller-kpi-card__label">Edit request</div>
              <div class="seller-kpi-card__value"><?= h($editReqLabel) ?></div>
              <div class="seller-kpi-card__hint"><?= $isEditUnlocked ? 'Unlocked — form edit ho sakta hai' : 'Admin workflow' ?></div>
            </div>
            <div class="seller-kpi-card__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17.25V21h3.75L19.81 7.94l-3.75-3.75z"/></svg>
            </div>
          </div>
        </div>

        <?php if (trim((string) ($details['kyc_rejection_reason'] ?? '')) !== '' && !$isFinalApproved): ?>
          <div class="seller-kyc-callout seller-kyc-callout--reject" role="status">
            <p class="seller-kyc-callout__title">Last admin review</p>
            <p class="seller-kyc-callout__text"><?= h((string) $details['kyc_rejection_reason']) ?></p>
          </div>
        <?php endif; ?>
        <?php if ($editRequestStatus === 'rejected' && trim((string) ($details['kyc_edit_rejection_reason'] ?? '')) !== ''): ?>
          <div class="seller-kyc-callout seller-kyc-callout--reject" role="status">
            <p class="seller-kyc-callout__title">Edit request note</p>
            <p class="seller-kyc-callout__text"><?= h((string) $details['kyc_edit_rejection_reason']) ?></p>
          </div>
        <?php endif; ?>

        <div class="card seller-txn-card seller-kyc-card">
          <div class="card-header seller-txn-card-head">
            <div>
              <h2 class="card-title">Verification &amp; bank form</h2>
              <p class="card-subtitle seller-txn-card-sub">GST (optional), PAN, Aadhaar, bank account, address. Har document <strong>PDF / JPG / PNG / WebP</strong>, max <strong>5 MB</strong>.</p>
            </div>
            <span class="seller-txn-count-pill<?= $isEditable ? '' : ' seller-kyc-pill--locked' ?>"><?= $isEditable ? 'Editing open' : 'Locked' ?></span>
          </div>
          <div class="card-body seller-kyc-card-body">
            <?php if ($error !== ''): ?>
              <div class="seller-kyc-flash seller-alert seller-alert--error"><?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
              <div class="seller-kyc-flash seller-alert seller-alert--success"><?= h($success) ?></div>
            <?php endif; ?>

            <div class="seller-kyc-status" role="status">
              <?php if ($kycSubmitted): ?>
                <span class="seller-status-chip seller-status-chip--delivered">KYC submitted</span>
              <?php else: ?>
                <span class="seller-status-chip seller-status-chip--pending">KYC incomplete</span>
              <?php endif; ?>
              <?php if ($isFinalApproved): ?>
                <span class="seller-status-chip seller-status-chip--delivered">Final approved</span>
              <?php else: ?>
                <span class="seller-status-chip seller-status-chip--pending">Final approval pending</span>
              <?php endif; ?>
              <span class="seller-kyc-status-meta">Last update: <strong><?= h(seller_kyc_format_dt((string) ($details['kyc_updated_at'] ?? ''))) ?></strong></span>
            </div>

            <div class="seller-kyc-intro">
              <p class="seller-kyc-intro__text">
                <?php if ($kycSubmitted && $isFinalApproved): ?>
                  Final approval: <strong><?= h(seller_kyc_format_dt((string) ($details['kyc_final_reviewed_at'] ?? ''))) ?></strong>.
                  <?php if ($isEditUnlocked): ?>
                    Admin ne edit unlock kiya — form bhar kar dubara <strong>Save</strong> karein. Pehle upload ki files tab tak rehti hain jab tak nayi file choose na ho. Edit band karna ho to neeche <strong>Cancel edit — lock again</strong> use karein.
                  <?php elseif ($editRequestStatus === 'pending'): ?>
                    <strong>Edit request</strong> admin ke review me hai.
                  <?php elseif ($editRequestStatus === 'rejected' && trim((string) ($details['kyc_edit_rejection_reason'] ?? '')) !== ''): ?>
                    Edit request reject — note upar dekhein. Phir nayi request bhej sakte ho (jab allow ho).
                  <?php else: ?>
                    Details <strong>lock</strong> hain. Badlav ke liye neeche <strong>Request edit access</strong> use karein.
                  <?php endif; ?>
                <?php elseif ($kycSubmitted): ?>
                  Admin final review wait kar raha hai.
                  <?php if (trim((string) ($details['kyc_rejection_reason'] ?? '')) !== ''): ?>
                    Feedback upar callout me hai — form fix karke save karein.
                  <?php endif; ?>
                <?php else: ?>
                  Sab required fields aur teen documents bharo, phir save — packet admin ko jayega.
                <?php endif; ?>
              </p>
            </div>

            <div class="seller-kyc-doc-grid">
              <div class="seller-kyc-doc-card">
                <span class="seller-kyc-doc-card__title">GST document</span>
                <?php if ($form['gst_doc_path'] !== ''): ?>
                  <a class="seller-edit-btn seller-kyc-doc-btn" href="../<?= h($form['gst_doc_path']) ?>" target="_blank" rel="noopener">View file</a>
                <?php else: ?>
                  <span class="seller-kyc-doc-missing">Abhi upload nahi</span>
                <?php endif; ?>
              </div>
              <div class="seller-kyc-doc-card">
                <span class="seller-kyc-doc-card__title">PAN document</span>
                <?php if ($form['pan_doc_path'] !== ''): ?>
                  <a class="seller-edit-btn seller-kyc-doc-btn" href="../<?= h($form['pan_doc_path']) ?>" target="_blank" rel="noopener">View file</a>
                <?php else: ?>
                  <span class="seller-kyc-doc-missing">Abhi upload nahi</span>
                <?php endif; ?>
              </div>
              <div class="seller-kyc-doc-card">
                <span class="seller-kyc-doc-card__title">Aadhaar document</span>
                <?php if ($form['aadhaar_doc_path'] !== ''): ?>
                  <a class="seller-edit-btn seller-kyc-doc-btn" href="../<?= h($form['aadhaar_doc_path']) ?>" target="_blank" rel="noopener">View file</a>
                <?php else: ?>
                  <span class="seller-kyc-doc-missing">Abhi upload nahi</span>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($isFinalApproved && !$isEditUnlocked): ?>
              <div class="seller-kyc-request-banner">
                <div class="seller-kyc-request-banner__text">
                  <strong>Edit chahiye?</strong> Admin se unlock request bhejo. Approve hone ke baad hi yeh form dubara save hoga.
                </div>
                <form method="post" class="seller-kyc-request-form">
                  <input type="hidden" name="action" value="request_edit_access">
                  <button type="submit" class="admin-btn admin-btn--outline seller-kyc-request-btn" <?= $editRequestStatus === 'pending' ? ' disabled' : '' ?>>
                    Request edit access
                  </button>
                </form>
              </div>
            <?php endif; ?>

            <?php if ($isFinalApproved && $isEditUnlocked): ?>
              <div class="seller-kyc-request-banner">
                <div class="seller-kyc-request-banner__text">
                  <strong>Edit nahi karna?</strong> Bina save kiye lock wapas laga sakte ho — database me jo documents pehle se hain wahi rahenge.
                </div>
                <form method="post" class="seller-kyc-request-form">
                  <input type="hidden" name="action" value="cancel_edit_unlock">
                  <button type="submit" class="admin-btn admin-btn--outline seller-kyc-request-btn" onclick="return confirm('KYC form dubara lock karna hai? Baad mein badlav ke liye phir se admin se edit request karni hogi.');">
                    Cancel edit — lock again
                  </button>
                </form>
              </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="seller-form seller-kyc-form<?= !$isEditable ? ' seller-kyc-form--locked' : '' ?>">
              <input type="hidden" name="action" value="save_kyc">

              <section class="seller-kyc-section" aria-labelledby="kyc-business-heading">
                <header class="seller-kyc-section__head">
                  <h3 class="seller-kyc-section__title" id="kyc-business-heading">Business &amp; tax</h3>
                  <p class="seller-kyc-section__sub">Legal name, GST (agar hai), PAN / Aadhaar aur unke scans.</p>
                </header>
                <div class="seller-form__row seller-kyc-form__row">
                  <div class="seller-kyc-field-group">
                    <label for="business_name">Business name</label>
                    <input id="business_name" name="business_name" required value="<?= h($form['business_name']) ?>" placeholder="Brand / company name" <?= !$isEditable ? 'disabled' : '' ?>>
                  </div>
                  <div class="seller-kyc-field-group seller-kyc-field-group--stack">
                    <label for="gst_number">GST number <span class="seller-kyc-optional">(optional)</span></label>
                    <input id="gst_number" class="seller-kyc-input--gst" name="gst_number" maxlength="15" value="<?= h($form['gst_number']) ?>" placeholder="22AAAAA0000A1Z5" <?= !$isEditable ? 'disabled' : '' ?>>
                    <label class="seller-kyc-file-label" for="gst_document">GST proof <span class="seller-kyc-file-hint-inline">PDF / image · 5 MB</span></label>
                    <input id="gst_document" class="seller-kyc-file" type="file" name="gst_document" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" <?= !$isEditable ? 'disabled' : '' ?>>
                    <?php if ($form['gst_doc_path'] !== ''): ?>
                      <div class="seller-kyc-uploaded-row">
                        <span class="seller-kyc-uploaded-pill">On file</span>
                        <a class="seller-kyc-uploaded-link" href="../<?= h($form['gst_doc_path']) ?>" target="_blank" rel="noopener">View document</a>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="seller-form__row seller-kyc-form__row">
                  <div class="seller-kyc-field-group seller-kyc-field-group--stack">
                    <label for="pan_number">PAN number</label>
                    <input id="pan_number" class="seller-kyc-input--pan" name="pan_number" required maxlength="10" value="<?= h($form['pan_number']) ?>" placeholder="ABCDE1234F" <?= !$isEditable ? 'disabled' : '' ?>>
                    <label class="seller-kyc-file-label" for="pan_document">PAN card scan</label>
                    <input id="pan_document" class="seller-kyc-file" type="file" name="pan_document" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" <?= !$isEditable ? 'disabled' : '' ?>>
                    <?php if ($form['pan_doc_path'] !== ''): ?>
                      <div class="seller-kyc-uploaded-row">
                        <span class="seller-kyc-uploaded-pill">On file</span>
                        <a class="seller-kyc-uploaded-link" href="../<?= h($form['pan_doc_path']) ?>" target="_blank" rel="noopener">View document</a>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="seller-kyc-field-group seller-kyc-field-group--stack">
                    <label for="aadhaar_number">Aadhaar number</label>
                    <input id="aadhaar_number" class="seller-kyc-input--aadhaar" name="aadhaar_number" required pattern="^[0-9]{12}$" value="<?= h($form['aadhaar_number']) ?>" placeholder="12 digit Aadhaar" <?= !$isEditable ? 'disabled' : '' ?>>
                    <label class="seller-kyc-file-label" for="aadhaar_document">Aadhaar scan</label>
                    <input id="aadhaar_document" class="seller-kyc-file" type="file" name="aadhaar_document" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" <?= !$isEditable ? 'disabled' : '' ?>>
                    <?php if ($form['aadhaar_doc_path'] !== ''): ?>
                      <div class="seller-kyc-uploaded-row">
                        <span class="seller-kyc-uploaded-pill">On file</span>
                        <a class="seller-kyc-uploaded-link" href="../<?= h($form['aadhaar_doc_path']) ?>" target="_blank" rel="noopener">View document</a>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </section>

              <section class="seller-kyc-section" aria-labelledby="kyc-bank-heading">
                <header class="seller-kyc-section__head">
                  <h3 class="seller-kyc-section__title" id="kyc-bank-heading">Bank account</h3>
                  <p class="seller-kyc-section__sub">Payouts isi account par aayenge — details bank passbook jaisi honi chahiye.</p>
                </header>
                <div class="seller-form__row seller-kyc-form__row">
                  <div class="seller-kyc-field-group">
                    <label for="bank_name">Bank name</label>
                    <input id="bank_name" name="bank_name" required value="<?= h($form['bank_name']) ?>" placeholder="e.g. HDFC Bank" <?= !$isEditable ? 'disabled' : '' ?>>
                  </div>
                  <div class="seller-kyc-field-group">
                    <label for="bank_account_name">Account holder name</label>
                    <input id="bank_account_name" name="bank_account_name" required value="<?= h($form['bank_account_name']) ?>" placeholder="Name as per bank" <?= !$isEditable ? 'disabled' : '' ?>>
                  </div>
                </div>
                <div class="seller-form__row seller-kyc-form__row">
                  <div class="seller-kyc-field-group">
                    <label for="bank_account_number">Account number</label>
                    <input id="bank_account_number" class="seller-kyc-input--mono" name="bank_account_number" required pattern="^[0-9]{9,18}$" value="<?= h($form['bank_account_number']) ?>" placeholder="9–18 digits" <?= !$isEditable ? 'disabled' : '' ?>>
                  </div>
                  <div class="seller-kyc-field-group">
                    <label for="bank_ifsc">IFSC code</label>
                    <input id="bank_ifsc" class="seller-kyc-input--ifsc seller-kyc-input--mono" name="bank_ifsc" required value="<?= h($form['bank_ifsc']) ?>" placeholder="HDFC0001234" <?= !$isEditable ? 'disabled' : '' ?>>
                  </div>
                </div>
              </section>

              <section class="seller-kyc-section" aria-labelledby="kyc-address-heading">
                <header class="seller-kyc-section__head">
                  <h3 class="seller-kyc-section__title" id="kyc-address-heading">Address &amp; ID proof</h3>
                  <p class="seller-kyc-section__sub">Registered / business location aur jo ID type select kiya hai uska number.</p>
                </header>
                <div class="seller-kyc-field-group seller-kyc-field-group--wide">
                  <label for="address_line1">Address line</label>
                  <input id="address_line1" name="address_line1" required value="<?= h($form['address_line1']) ?>" placeholder="Shop / office address" <?= !$isEditable ? 'disabled' : '' ?>>
                </div>
                <div class="seller-form__row seller-kyc-form__row">
                  <div class="seller-kyc-field-group">
                    <label for="city">City</label>
                    <input id="city" class="seller-kyc-input--city" name="city" required value="<?= h($form['city']) ?>" placeholder="City" <?= !$isEditable ? 'disabled' : '' ?>>
                  </div>
                  <div class="seller-kyc-field-group">
                    <label for="state">State</label>
                    <input id="state" class="seller-kyc-input--state" name="state" required value="<?= h($form['state']) ?>" placeholder="State" <?= !$isEditable ? 'disabled' : '' ?>>
                  </div>
                </div>
                <div class="seller-form__row seller-kyc-form__row">
                  <div class="seller-kyc-field-group">
                    <label for="pin_code">PIN code</label>
                    <input id="pin_code" class="seller-kyc-input--pin seller-kyc-input--mono" name="pin_code" required pattern="^[0-9]{6}$" value="<?= h($form['pin_code']) ?>" placeholder="6 digit PIN" <?= !$isEditable ? 'disabled' : '' ?>>
                  </div>
                  <div class="seller-kyc-field-group">
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
                <div class="seller-kyc-field-group seller-kyc-field-group--wide">
                  <label for="id_proof_number">ID proof number</label>
                  <input id="id_proof_number" name="id_proof_number" required value="<?= h($form['id_proof_number']) ?>" placeholder="Document number" <?= !$isEditable ? 'disabled' : '' ?>>
                </div>
              </section>

              <div class="seller-kyc-submit-panel">
                <p class="seller-kyc-submit-panel__hint">Save karne par packet admin review queue me chala jata hai. Final approve hone tak sensitive fields lock ho sakti hain.</p>
                <div class="seller-actions seller-kyc-form-actions">
                  <button class="admin-btn admin-btn--primary seller-kyc-submit-btn" type="submit" <?= !$isEditable ? 'disabled' : '' ?>>Save &amp; submit for review</button>
                </div>
              </div>
            </form>
          </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
