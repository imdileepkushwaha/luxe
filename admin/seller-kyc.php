<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_pagination.php';

$pdo = db();
$admin = admin_require_login($pdo);

$pageTitle = 'Seller KYC Requests';
$activeNav = 'seller_kyc';

$validCategories = ['fashion', 'electronics', 'beauty', 'home'];
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $sellerId = (int) ($_POST['seller_id'] ?? 0);

    if ($requestId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $reqSt = $pdo->prepare(
            "SELECT id, full_name, email, phone, requested_password_hash, requested_categories,
                    business_name, gst_number, pan_number, aadhaar_number,
                    bank_account_name, bank_account_number, bank_ifsc,
                    address_line1, city, state, pin_code, id_proof_type, id_proof_number
             FROM seller_create_requests
             WHERE id = ? AND status = 'pending'
             LIMIT 1"
        );
        $reqSt->execute([$requestId]);
        $request = $reqSt->fetch();

        if (!$request) {
            $flash = ['ok' => false, 'text' => 'Pending request nahi mili ya pehle review ho chuki hai.'];
        } elseif ($action === 'approve') {
            $requestedHash = (string) ($request['requested_password_hash'] ?? '');
            if ($requestedHash === '') {
                $flash = ['ok' => false, 'text' => 'Password hash missing hai, request approve nahi ho sakti.'];
            } else {
                $existingSellerSt = $pdo->prepare('SELECT id FROM seller_users WHERE email = ? LIMIT 1');
                $existingSellerSt->execute([(string) $request['email']]);
                if ($existingSellerSt->fetch()) {
                    $flash = ['ok' => false, 'text' => 'Is email ka seller account already exist karta hai.'];
                } else {
                    $categories = [];
                    $parts = explode(',', (string) ($request['requested_categories'] ?? ''));
                    foreach ($parts as $part) {
                        $cat = strtolower(trim($part));
                        if (in_array($cat, $validCategories, true)) {
                            $categories[] = $cat;
                        }
                    }
                    $categories = array_values(array_unique($categories));
                    if ($categories === []) {
                        $categories = $validCategories;
                    }

                    $pdo->beginTransaction();
                    try {
                        $insSeller = $pdo->prepare(
                            'INSERT INTO seller_users (email, password_hash, full_name, allowed_categories, business_name, gst_number, is_active)
                             VALUES (?, ?, ?, ?, ?, ?, 1)'
                        );
                        $insSeller->execute([
                            (string) $request['email'],
                            $requestedHash,
                            (string) $request['full_name'],
                            implode(',', $categories),
                            (string) ($request['business_name'] ?? ''),
                            (string) ($request['gst_number'] ?? ''),
                        ]);
                        $sellerId = (int) $pdo->lastInsertId();

                        $updReq = $pdo->prepare(
                            "UPDATE seller_create_requests
                             SET status = 'approved',
                                 reviewed_by = ?,
                                 reviewed_at = NOW(),
                                 seller_id = ?,
                                 rejection_reason = ''
                             WHERE id = ? LIMIT 1"
                        );
                        $updReq->execute([(int) $admin['id'], $sellerId, $requestId]);

                        $pdo->commit();
                        $flash = ['ok' => true, 'text' => 'Request approve ho gayi aur seller account create ho gaya.'];
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        $flash = ['ok' => false, 'text' => 'Approve action fail hua: ' . $e->getMessage()];
                    }
                }
            }
        } else {
            $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
            if (mb_strlen($reason) < 5) {
                $flash = ['ok' => false, 'text' => 'Reject karne ke liye reason minimum 5 characters ka hona chahiye.'];
            } else {
                $updReq = $pdo->prepare(
                    "UPDATE seller_create_requests
                     SET status = 'rejected',
                         reviewed_by = ?,
                         reviewed_at = NOW(),
                         rejection_reason = ?
                     WHERE id = ? AND status = 'pending'
                     LIMIT 1"
                );
                $updReq->execute([(int) $admin['id'], $reason, $requestId]);
                $flash = ['ok' => true, 'text' => 'Request reject kar di gayi.'];
            }
        }
    } elseif ($sellerId > 0 && in_array($action, ['final_approve', 'final_reject', 'final_edit_approve', 'final_edit_reject'], true)) {
        $sellerSt = $pdo->prepare(
            "SELECT id, full_name, email, kyc_completed, kyc_final_approved, kyc_edit_request_status
             FROM seller_users
             WHERE id = ?
             LIMIT 1"
        );
        $sellerSt->execute([$sellerId]);
        $sellerRow = $sellerSt->fetch();

        if (!$sellerRow) {
            $flash = ['ok' => false, 'text' => 'Seller account not found.'];
        } elseif ((int) ($sellerRow['kyc_completed'] ?? 0) !== 1) {
            $flash = ['ok' => false, 'text' => 'Seller ne abhi KYC details submit nahi ki hain.'];
        } elseif ($action === 'final_edit_approve') {
            if ((int) ($sellerRow['kyc_final_approved'] ?? 0) !== 1) {
                $flash = ['ok' => false, 'text' => 'Edit unlock sirf final-approved KYC ke liye hota hai.'];
            } elseif ((string) ($sellerRow['kyc_edit_request_status'] ?? 'none') !== 'pending') {
                $flash = ['ok' => false, 'text' => 'Is seller ki pending edit request nahi hai.'];
            } else {
                $upd = $pdo->prepare(
                    "UPDATE seller_users
                     SET kyc_edit_request_status = 'approved',
                         kyc_edit_unlocked = 1,
                         kyc_edit_reviewed_by = ?,
                         kyc_edit_reviewed_at = NOW(),
                         kyc_edit_rejection_reason = ''
                     WHERE id = ?
                     LIMIT 1"
                );
                $upd->execute([(int) $admin['id'], $sellerId]);
                $flash = ['ok' => true, 'text' => 'KYC edit access approve kar diya gaya. Seller ab details edit kar sakta hai.'];
            }
        } elseif ($action === 'final_edit_reject') {
            $reason = trim((string) ($_POST['final_edit_rejection_reason'] ?? ''));
            if (mb_strlen($reason) < 5) {
                $flash = ['ok' => false, 'text' => 'Edit reject ke liye reason minimum 5 characters ka hona chahiye.'];
            } elseif ((string) ($sellerRow['kyc_edit_request_status'] ?? 'none') !== 'pending') {
                $flash = ['ok' => false, 'text' => 'Is seller ki pending edit request nahi hai.'];
            } else {
                $upd = $pdo->prepare(
                    "UPDATE seller_users
                     SET kyc_edit_request_status = 'rejected',
                         kyc_edit_unlocked = 0,
                         kyc_edit_reviewed_by = ?,
                         kyc_edit_reviewed_at = NOW(),
                         kyc_edit_rejection_reason = ?
                     WHERE id = ?
                     LIMIT 1"
                );
                $upd->execute([(int) $admin['id'], $reason, $sellerId]);
                $flash = ['ok' => true, 'text' => 'KYC edit request reject kar di gayi.'];
            }
        } elseif ($action === 'final_approve') {
            $upd = $pdo->prepare(
                "UPDATE seller_users
                 SET kyc_final_approved = 1,
                     kyc_final_reviewed_by = ?,
                     kyc_final_reviewed_at = NOW(),
                     kyc_rejection_reason = '',
                     kyc_edit_request_status = 'none',
                     kyc_edit_requested_at = NULL,
                     kyc_edit_reviewed_by = NULL,
                     kyc_edit_reviewed_at = NULL,
                     kyc_edit_rejection_reason = '',
                     kyc_edit_unlocked = 0
                 WHERE id = ?
                 LIMIT 1"
            );
            $upd->execute([(int) $admin['id'], $sellerId]);
            $flash = ['ok' => true, 'text' => 'Final KYC approve ho gayi. Seller ab products add kar sakta hai.'];
        } else {
            $reason = trim((string) ($_POST['final_rejection_reason'] ?? ''));
            if (mb_strlen($reason) < 5) {
                $flash = ['ok' => false, 'text' => 'Final reject ke liye reason minimum 5 characters ka hona chahiye.'];
            } else {
                $upd = $pdo->prepare(
                    "UPDATE seller_users
                     SET kyc_final_approved = 0,
                         kyc_final_reviewed_by = ?,
                         kyc_final_reviewed_at = NOW(),
                         kyc_rejection_reason = ?,
                         kyc_edit_unlocked = 0
                     WHERE id = ?
                     LIMIT 1"
                );
                $upd->execute([(int) $admin['id'], $reason, $sellerId]);
                $flash = ['ok' => true, 'text' => 'Final KYC reject ki gayi. Seller ko details update karni hongi.'];
            }
        }
    }
}

$statusFilter = (string) ($_GET['status'] ?? 'pending');
if (!in_array($statusFilter, ['pending', 'approved', 'rejected', 'all'], true)) {
    $statusFilter = 'pending';
}

$whereSql = '';
$params = [];
if ($statusFilter !== 'all') {
    $whereSql = 'WHERE r.status = ?';
    $params[] = $statusFilter;
}

$countRegSt = $pdo->prepare("SELECT COUNT(*) FROM seller_create_requests r {$whereSql}");
$countRegSt->execute($params);
$totalRegRequests = (int) $countRegSt->fetchColumn();

['page' => $regReqPage, 'perPage' => $regPerPage] = admin_pagination_read_keys('reg_page', 'reg_per_page', 25);
$pReg = admin_pagination_resolve($totalRegRequests, $regReqPage, $regPerPage);
$regPage = $pReg['page'];
$regOffset = $pReg['offset'];
$regPerPage = $pReg['perPage'];
$regTotalPages = $pReg['totalPages'];

if ($totalRegRequests === 0) {
    $requests = [];
} else {
    $regLimit = max(1, (int) $regPerPage);
    $regOff = max(0, (int) $regOffset);
    $requestsSt = $pdo->prepare(
        "SELECT r.id, r.full_name, r.email, r.phone, r.requested_categories, r.note,
                r.business_name, r.gst_number, r.pan_number, r.aadhaar_number,
                r.bank_account_name, r.bank_account_number, r.bank_ifsc,
                r.address_line1, r.city, r.state, r.pin_code,
                r.id_proof_type, r.id_proof_number, r.status, r.reviewed_at, r.rejection_reason,
                r.created_at, s.id AS seller_id, a.full_name AS reviewed_by_name
         FROM seller_create_requests r
         LEFT JOIN seller_users s ON s.id = r.seller_id
         LEFT JOIN admin_users a ON a.id = r.reviewed_by
         {$whereSql}
         ORDER BY r.id DESC
         LIMIT {$regLimit} OFFSET {$regOff}"
    );
    $requestsSt->execute($params);
    $requests = $requestsSt->fetchAll();
}

$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_create_requests WHERE status = 'pending'")->fetchColumn();
$approvedCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_create_requests WHERE status = 'approved'")->fetchColumn();
$rejectedCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_create_requests WHERE status = 'rejected'")->fetchColumn();
$kycPendingFinalCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_users WHERE is_active = 1 AND kyc_completed = 1 AND kyc_final_approved = 0")->fetchColumn();
$kycFinalApprovedCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_users WHERE is_active = 1 AND kyc_completed = 1 AND kyc_final_approved = 1")->fetchColumn();
$pendingEditCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_users WHERE is_active = 1 AND kyc_final_approved = 1 AND kyc_edit_request_status = 'pending'")->fetchColumn();

$totalFinalKyc = (int) $pdo->query('SELECT COUNT(*) FROM seller_users WHERE is_active = 1')->fetchColumn();

['page' => $finReqPage, 'perPage' => $finPerPage] = admin_pagination_read_keys('fin_page', 'fin_per_page', 25);
$pFin = admin_pagination_resolve($totalFinalKyc, $finReqPage, $finPerPage);

if ($totalFinalKyc === 0) {
    $finalKycRows = [];
} else {
    $finLimit = max(1, (int) $pFin['perPage']);
    $finOff = max(0, (int) $pFin['offset']);
    $finalKycSt = $pdo->prepare(
        "SELECT s.id, s.full_name, s.email, s.business_name, s.gst_number, s.pan_number, s.aadhaar_number,
                s.gst_doc_path, s.pan_doc_path, s.aadhaar_doc_path,
                s.bank_name, s.bank_account_name, s.bank_account_number, s.bank_ifsc,
                s.address_line1, s.city, s.state, s.pin_code,
                s.id_proof_type, s.id_proof_number,
                s.kyc_completed, s.kyc_updated_at, s.kyc_final_approved, s.kyc_final_reviewed_at, s.kyc_rejection_reason,
                s.kyc_edit_request_status, s.kyc_edit_requested_at, s.kyc_edit_reviewed_at, s.kyc_edit_rejection_reason, s.kyc_edit_unlocked,
                a.full_name AS reviewed_by_name
         FROM seller_users s
         LEFT JOIN admin_users a ON a.id = s.kyc_final_reviewed_by
         WHERE s.is_active = 1
         ORDER BY s.id DESC
         LIMIT {$finLimit} OFFSET {$finOff}"
    );
    $finalKycSt->execute();
    $finalKycRows = $finalKycSt->fetchAll();
}

['page' => $editReqPage, 'perPage' => $editPerPage] = admin_pagination_read_keys('edit_page', 'edit_per_page', 25);
$pEdit = admin_pagination_resolve($pendingEditCount, $editReqPage, $editPerPage);

if ($pendingEditCount === 0) {
    $pendingEditRows = [];
} else {
    $editLimit = max(1, (int) $pEdit['perPage']);
    $editOff = max(0, (int) $pEdit['offset']);
    $pendingEditSt = $pdo->prepare(
        "SELECT s.id, s.full_name, s.email, s.kyc_edit_requested_at, s.kyc_updated_at
         FROM seller_users s
         WHERE s.is_active = 1
           AND s.kyc_final_approved = 1
           AND s.kyc_edit_request_status = 'pending'
         ORDER BY s.kyc_edit_requested_at DESC, s.id DESC
         LIMIT {$editLimit} OFFSET {$editOff}"
    );
    $pendingEditSt->execute();
    $pendingEditRows = $pendingEditSt->fetchAll();
}

/**
 * @param mixed $raw
 */
function admin_kyc_fmt_dt($raw): string
{
    if ($raw === null || $raw === '') {
        return '—';
    }
    $s = (string) $raw;
    try {
        return (new DateTimeImmutable($s))->format('M j, Y · g:i A');
    } catch (Throwable $e) {
        return $s;
    }
}

/**
 * @param array<string, mixed> $r
 */
function admin_kyc_reg_haystack(array $r): string
{
    $parts = [
        (string) ($r['id'] ?? ''),
        (string) ($r['full_name'] ?? ''),
        (string) ($r['email'] ?? ''),
        (string) ($r['phone'] ?? ''),
        (string) ($r['requested_categories'] ?? ''),
        (string) ($r['business_name'] ?? ''),
        (string) ($r['gst_number'] ?? ''),
        (string) ($r['pan_number'] ?? ''),
        (string) ($r['aadhaar_number'] ?? ''),
        (string) ($r['bank_account_name'] ?? ''),
        (string) ($r['bank_account_number'] ?? ''),
        (string) ($r['bank_ifsc'] ?? ''),
        (string) ($r['address_line1'] ?? ''),
        (string) ($r['city'] ?? ''),
        (string) ($r['state'] ?? ''),
        (string) ($r['pin_code'] ?? ''),
        (string) ($r['id_proof_type'] ?? ''),
        (string) ($r['id_proof_number'] ?? ''),
        (string) ($r['status'] ?? ''),
        (string) ($r['note'] ?? ''),
        (string) ($r['rejection_reason'] ?? ''),
    ];
    $clean = [];
    foreach ($parts as $p) {
        $t = trim((string) $p);
        if ($t !== '') {
            $clean[] = $t;
        }
    }
    $s = implode(' ', $clean);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($s, 'UTF-8');
    }

    return strtolower($s);
}

/**
 * @param array<string, mixed> $r
 */
function admin_kyc_edit_haystack(array $r): string
{
    $parts = [
        (string) ($r['id'] ?? ''),
        (string) ($r['full_name'] ?? ''),
        (string) ($r['email'] ?? ''),
        (string) ($r['kyc_updated_at'] ?? ''),
        (string) ($r['kyc_edit_requested_at'] ?? ''),
    ];
    $clean = [];
    foreach ($parts as $p) {
        $t = trim((string) $p);
        if ($t !== '') {
            $clean[] = $t;
        }
    }
    $s = implode(' ', $clean);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($s, 'UTF-8');
    }

    return strtolower($s);
}

/**
 * @param array<string, mixed> $r
 */
function admin_kyc_final_haystack(array $r): string
{
    $parts = [
        (string) ($r['id'] ?? ''),
        (string) ($r['full_name'] ?? ''),
        (string) ($r['email'] ?? ''),
        (string) ($r['business_name'] ?? ''),
        (string) ($r['gst_number'] ?? ''),
        (string) ($r['pan_number'] ?? ''),
        (string) ($r['aadhaar_number'] ?? ''),
        (string) ($r['gst_doc_path'] ?? ''),
        (string) ($r['pan_doc_path'] ?? ''),
        (string) ($r['aadhaar_doc_path'] ?? ''),
        (string) ($r['bank_name'] ?? ''),
        (string) ($r['bank_account_name'] ?? ''),
        (string) ($r['bank_account_number'] ?? ''),
        (string) ($r['bank_ifsc'] ?? ''),
        (string) ($r['address_line1'] ?? ''),
        (string) ($r['city'] ?? ''),
        (string) ($r['state'] ?? ''),
        (string) ($r['pin_code'] ?? ''),
        (string) ($r['id_proof_type'] ?? ''),
        (string) ($r['id_proof_number'] ?? ''),
        (string) ($r['kyc_completed'] ?? ''),
        (string) ($r['kyc_final_approved'] ?? ''),
        (string) ($r['kyc_edit_request_status'] ?? ''),
        (string) ($r['kyc_updated_at'] ?? ''),
        (string) ($r['kyc_final_reviewed_at'] ?? ''),
        (string) ($r['kyc_rejection_reason'] ?? ''),
        (string) ($r['reviewed_by_name'] ?? ''),
        (string) ($r['kyc_edit_requested_at'] ?? ''),
        (string) ($r['kyc_edit_reviewed_at'] ?? ''),
        (string) ($r['kyc_edit_rejection_reason'] ?? ''),
        (string) ($r['kyc_edit_unlocked'] ?? ''),
    ];
    if ((int) ($r['kyc_completed'] ?? 0) !== 1) {
        $parts[] = 'kyc details pending';
    } elseif ((int) ($r['kyc_final_approved'] ?? 0) === 1) {
        $parts[] = 'final approved';
    } else {
        $parts[] = 'pending final approval';
    }
    $clean = [];
    foreach ($parts as $p) {
        $t = trim((string) $p);
        if ($t !== '') {
            $clean[] = $t;
        }
    }
    $s = implode(' ', $clean);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($s, 'UTF-8');
    }

    return strtolower($s);
}

require __DIR__ . '/partials/shell-top.php';
?>

        <div class="admin-kyc-page">
        <?php if ($flash): ?>
          <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?> admin-kyc-flash" role="status">
            <?= h((string) ($flash['text'] ?? '')) ?>
          </div>
        <?php endif; ?>

        <div class="admin-page-head">
          <div class="admin-page-head__intro">
            <span class="admin-page-head__eyebrow">Marketplace</span>
            <h1>Seller KYC</h1>
            <p class="admin-page-head__lede">Registration queue, document review, and final approval before sellers can list products.</p>
          </div>
          <div class="admin-page-head__actions">
            <a class="admin-btn admin-btn--outline" href="sellers.php">All sellers</a>
          </div>
        </div>

        <div class="admin-grid admin-grid--stats admin-grid--stats--flow admin-kyc-kpi-grid" aria-label="KYC summary">
          <div class="admin-card admin-stat admin-stat--stripe-amber admin-kyc-kpi">
            <div>
              <div class="admin-stat__label admin-kyc-kpi__label">Pending registration</div>
              <div class="admin-stat__value"><?= (int) $pendingCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">New signup requests</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--pink" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="14" y1="2" x2="14" y2="8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-green admin-kyc-kpi">
            <div>
              <div class="admin-stat__label admin-kyc-kpi__label">Reg. approved</div>
              <div class="admin-stat__value"><?= (int) $approvedCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Accounts created</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--green" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-red admin-kyc-kpi">
            <div>
              <div class="admin-stat__label admin-kyc-kpi__label">Reg. rejected</div>
              <div class="admin-stat__value"><?= (int) $rejectedCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Declined applications</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--red" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-violet admin-kyc-kpi">
            <div>
              <div class="admin-stat__label admin-kyc-kpi__label">Pending final KYC</div>
              <div class="admin-stat__value"><?= (int) $kycPendingFinalCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Submitted, not final</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--purple" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-teal admin-kyc-kpi">
            <div>
              <div class="admin-stat__label admin-kyc-kpi__label">Final approved</div>
              <div class="admin-stat__value"><?= (int) $kycFinalApprovedCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Can list products</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--blue" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
          </div>
          <div class="admin-card admin-stat admin-stat--stripe-indigo admin-kyc-kpi">
            <div>
              <div class="admin-stat__label admin-kyc-kpi__label">Pending edit</div>
              <div class="admin-stat__value"><?= (int) $pendingEditCount ?></div>
              <div class="admin-stat__delta admin-stat__delta--muted">Unlock requests</div>
            </div>
            <div class="admin-stat__icon admin-stat__icon--indigo" aria-hidden="true">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </div>
          </div>
        </div>

        <div class="card admin-kyc-section-card">
          <div class="card-header admin-kyc-section-header">
            <div class="admin-kyc-section-head">
              <div class="admin-kyc-section-head-text">
                <h2 class="card-title">Registration requests</h2>
                <p class="card-subtitle admin-kyc-section-sub">Initial review before a <span class="admin-inline-code">seller_users</span> row is created. Paginated below · in-page search filters loaded rows only.</p>
              </div>
              <?php if ($requests !== []): ?>
                <label class="admin-users-search-wrap admin-kyc-search-wrap" for="adminKycRegSearch">
                  <span class="admin-users-search-icon admin-kyc-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminKycRegSearch"
                    class="admin-users-search-input admin-kyc-search-input"
                    placeholder="Search name, email, GST, city…"
                    autocomplete="off"
                    aria-label="Search registration requests"
                  >
                </label>
              <?php endif; ?>
            </div>
            <nav class="admin-kyc-tabs" aria-label="Filter by status">
              <a class="admin-kyc-tab<?= $statusFilter === 'pending' ? ' admin-kyc-tab--active' : '' ?>" href="seller-kyc.php?status=pending">
                <span class="admin-kyc-tab__label">Pending</span>
              </a>
              <a class="admin-kyc-tab<?= $statusFilter === 'approved' ? ' admin-kyc-tab--active' : '' ?>" href="seller-kyc.php?status=approved">
                <span class="admin-kyc-tab__label">Approved</span>
              </a>
              <a class="admin-kyc-tab<?= $statusFilter === 'rejected' ? ' admin-kyc-tab--active' : '' ?>" href="seller-kyc.php?status=rejected">
                <span class="admin-kyc-tab__label">Rejected</span>
              </a>
              <a class="admin-kyc-tab<?= $statusFilter === 'all' ? ' admin-kyc-tab--active' : '' ?>" href="seller-kyc.php?status=all">
                <span class="admin-kyc-tab__label">All</span>
              </a>
            </nav>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table admin-kyc-table">
                <thead>
                  <tr>
                    <th>Seller</th>
                    <th>Business + KYC</th>
                    <th>Bank</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th class="admin-table__th-narrow">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($requests as $request): ?>
                    <?php
                    $status = (string) ($request['status'] ?? 'pending');
                    $hay = admin_kyc_reg_haystack($request);
                    $fn = trim((string) ($request['full_name'] ?? ''));
                    $ini = strtoupper(substr(preg_replace('/\s+/', '', $fn), 0, 2));
                    if ($ini === '') {
                        $ini = '?';
                    }
                    ?>
                    <tr class="admin-kyc-reg-row" data-kyc-reg-search="<?= h($hay) ?>">
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <div class="admin-kyc-cell-stack">
                            <span class="admin-kyc-strong"><?= h($fn !== '' ? $fn : '—') ?></span>
                            <span class="admin-kyc-meta"><?= h((string) $request['email']) ?></span>
                            <span class="admin-kyc-meta">Phone · <?= h((string) ($request['phone'] ?: '—')) ?></span>
                            <span class="admin-kyc-meta">Categories · <?= h((string) ($request['requested_categories'] ?: '—')) ?></span>
                            <span class="admin-kyc-meta">Submitted · <?= h(admin_kyc_fmt_dt($request['created_at'] ?? null)) ?></span>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="admin-kyc-cell-stack">
                          <span class="admin-kyc-strong"><?= h((string) ($request['business_name'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">GST · <?= h((string) ($request['gst_number'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">PAN · <?= h((string) ($request['pan_number'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">Aadhaar · <?= h((string) ($request['aadhaar_number'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta"><?= h(strtoupper(str_replace('_', ' ', (string) ($request['id_proof_type'] ?: '-')))) ?> · <?= h((string) ($request['id_proof_number'] ?: '—')) ?></span>
                          <?php if ((string) ($request['note'] ?? '') !== ''): ?>
                            <span class="admin-kyc-note">Note · <?= h((string) $request['note']) ?></span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <div class="admin-kyc-cell-stack">
                          <span class="admin-kyc-meta">Holder · <?= h((string) ($request['bank_account_name'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">A/C · <?= h((string) ($request['bank_account_number'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">IFSC · <?= h((string) ($request['bank_ifsc'] ?: '—')) ?></span>
                        </div>
                      </td>
                      <td>
                        <div class="admin-kyc-cell-stack">
                          <span class="admin-kyc-meta"><?= h((string) ($request['address_line1'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta"><?= h((string) ($request['city'] ?: '—')) ?>, <?= h((string) ($request['state'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">PIN · <?= h((string) ($request['pin_code'] ?: '—')) ?></span>
                        </div>
                      </td>
                      <td>
                        <div class="admin-kyc-cell-stack">
                          <span class="admin-status admin-status--<?= h($status === 'approved' ? 'delivered' : ($status === 'rejected' ? 'cancelled' : 'processing')) ?>">
                            <?= h(ucfirst($status)) ?>
                          </span>
                          <?php if ((string) ($request['reviewed_at'] ?? '') !== ''): ?>
                            <span class="admin-kyc-meta"><?= h(admin_kyc_fmt_dt($request['reviewed_at'])) ?></span>
                            <span class="admin-kyc-meta">By · <?= h((string) ($request['reviewed_by_name'] ?: 'Admin')) ?></span>
                          <?php endif; ?>
                          <?php if ($status === 'approved' && (int) ($request['seller_id'] ?? 0) > 0): ?>
                            <a class="admin-kyc-inline-link" href="seller-view.php?id=<?= (int) $request['seller_id'] ?>">Open seller →</a>
                          <?php endif; ?>
                          <?php if ($status === 'rejected' && (string) ($request['rejection_reason'] ?? '') !== ''): ?>
                            <span class="admin-kyc-reason">Reason · <?= h((string) $request['rejection_reason']) ?></span>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <?php if ($status === 'pending'): ?>
                          <form method="post" class="admin-kyc-action-form">
                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                            <button type="submit" name="action" value="approve" class="admin-btn admin-btn--primary admin-kyc-action-form__btn" onclick="return confirm('Is KYC request ko approve karna hai?')">Approve</button>
                            <input type="text" name="rejection_reason" class="admin-input admin-kyc-action-form__input" placeholder="Reject reason (min 5 chars)" minlength="5" autocomplete="off">
                            <button type="submit" name="action" value="reject" class="admin-btn admin-kyc-action-form__btn admin-kyc-action-form__btn--reject" onclick="return confirm('Is request ko reject karna hai?')">Reject</button>
                          </form>
                        <?php else: ?>
                          <span class="admin-kyc-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <tr id="adminKycRegNoMatchRow" class="admin-kyc-no-match-row">
                    <td colspan="6">
                      <div class="admin-kyc-no-match">
                        <strong class="admin-kyc-no-match__title">No matches</strong>
                        <p class="admin-kyc-no-match__text">Try another keyword (this list only).</p>
                      </div>
                    </td>
                  </tr>
                  <?php if ($requests === []): ?>
                    <tr class="admin-kyc-empty-only">
                      <td colspan="6">
                        <p class="admin-empty-hint admin-empty-hint--boxed admin-kyc-empty-p">No seller registration requests for this filter.</p>
                      </td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if ($requests !== []): ?>
            <script>
            (function () {
              var searchInput = document.getElementById('adminKycRegSearch');
              if (!searchInput) return;
              var rows = document.querySelectorAll('tr.admin-kyc-reg-row');
              var noMatch = document.getElementById('adminKycRegNoMatchRow');
              function run() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var any = false;
                rows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-kyc-reg-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) { return hay.indexOf(w) !== -1; });
                  tr.style.display = show ? '' : 'none';
                  if (show) any = true;
                });
                if (noMatch) noMatch.style.display = (words.length > 0 && !any) ? 'table-row' : 'none';
              }
              searchInput.addEventListener('input', run);
              searchInput.addEventListener('search', run);
            })();
            </script>
            <?php endif; ?>
            <?php if ($totalRegRequests > 0): ?>
            <?php
            $paginationScript = 'seller-kyc.php';
            $paginationTotal = $totalRegRequests;
            $paginationPage = $regPage;
            $paginationPerPage = $regPerPage;
            $paginationTotalPages = $regTotalPages;
            $paginationPageKey = 'reg_page';
            $paginationPerPageKey = 'reg_per_page';
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="card admin-kyc-section-card admin-kyc-section-card--spaced">
          <div class="card-header admin-kyc-section-header">
            <div class="admin-kyc-section-head">
              <div class="admin-kyc-section-head-text">
                <h2 class="card-title">Pending KYC edit requests</h2>
                <p class="card-subtitle admin-kyc-section-sub">Final-approved sellers asking to unlock profile edits. · Search filters this list only.</p>
              </div>
              <?php if ($pendingEditRows !== []): ?>
                <label class="admin-users-search-wrap admin-kyc-search-wrap" for="adminKycEditSearch">
                  <span class="admin-users-search-icon admin-kyc-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminKycEditSearch"
                    class="admin-users-search-input admin-kyc-search-input"
                    placeholder="Search name, email, ID…"
                    autocomplete="off"
                    aria-label="Search edit requests"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table admin-kyc-table">
                <thead>
                  <tr>
                    <th>Seller</th>
                    <th>KYC last submitted</th>
                    <th>Edit requested</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pendingEditRows as $row): ?>
                    <?php
                    $fn = trim((string) ($row['full_name'] ?? ''));
                    $ini = strtoupper(substr(preg_replace('/\s+/', '', $fn), 0, 2));
                    if ($ini === '') {
                        $ini = '?';
                    }
                    $hayEdit = admin_kyc_edit_haystack($row);
                    ?>
                    <tr class="admin-kyc-edit-row" data-kyc-edit-search="<?= h($hayEdit) ?>">
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <div class="admin-kyc-cell-stack">
                            <span class="admin-kyc-strong"><?= h($fn !== '' ? $fn : '—') ?></span>
                            <span class="admin-kyc-meta"><?= h((string) $row['email']) ?></span>
                          </div>
                        </div>
                      </td>
                      <td class="admin-table__td-muted"><?= h(admin_kyc_fmt_dt($row['kyc_updated_at'] ?? null)) ?></td>
                      <td class="admin-table__td-muted"><?= h(admin_kyc_fmt_dt($row['kyc_edit_requested_at'] ?? null)) ?></td>
                      <td>
                        <form method="post" class="admin-kyc-inline-form">
                          <input type="hidden" name="seller_id" value="<?= (int) $row['id'] ?>">
                          <button type="submit" name="action" value="final_edit_approve" class="admin-btn admin-btn--primary admin-kyc-inline-form__btn" onclick="return confirm('Seller ko KYC edit access dena hai?')">Approve edit</button>
                          <input type="text" name="final_edit_rejection_reason" class="admin-input admin-kyc-inline-form__input" placeholder="Reject reason" minlength="5" autocomplete="off">
                          <button type="submit" name="action" value="final_edit_reject" class="admin-btn admin-kyc-inline-form__btn admin-kyc-inline-form__btn--reject" onclick="return confirm('Edit request reject karna hai?')">Reject</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($pendingEditRows !== []): ?>
                  <tr id="adminKycEditNoMatchRow" class="admin-kyc-no-match-row">
                    <td colspan="4">
                      <div class="admin-kyc-no-match">
                        <strong class="admin-kyc-no-match__title">No matches</strong>
                        <p class="admin-kyc-no-match__text">Try another keyword (this list only).</p>
                      </div>
                    </td>
                  </tr>
                  <?php endif; ?>
                  <?php if ($pendingEditRows === []): ?>
                    <tr><td colspan="4"><p class="admin-empty-hint admin-empty-hint--boxed admin-kyc-empty-p">No pending KYC edit requests.</p></td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if ($pendingEditRows !== []): ?>
            <script>
            (function () {
              var searchInput = document.getElementById('adminKycEditSearch');
              if (!searchInput) return;
              var rows = document.querySelectorAll('tr.admin-kyc-edit-row');
              var noMatch = document.getElementById('adminKycEditNoMatchRow');
              function run() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var any = false;
                rows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-kyc-edit-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) { return hay.indexOf(w) !== -1; });
                  tr.style.display = show ? '' : 'none';
                  if (show) any = true;
                });
                if (noMatch) noMatch.style.display = (words.length > 0 && !any) ? 'table-row' : 'none';
              }
              searchInput.addEventListener('input', run);
              searchInput.addEventListener('search', run);
            })();
            </script>
            <?php endif; ?>
            <?php if ($pendingEditCount > 0): ?>
            <?php
            $paginationScript = 'seller-kyc.php';
            $paginationTotal = $pendingEditCount;
            $paginationPage = $pEdit['page'];
            $paginationPerPage = $pEdit['perPage'];
            $paginationTotalPages = $pEdit['totalPages'];
            $paginationPageKey = 'edit_page';
            $paginationPerPageKey = 'edit_per_page';
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="card admin-kyc-section-card admin-kyc-section-card--spaced">
          <div class="card-header admin-kyc-section-header">
            <div class="admin-kyc-section-head">
              <div class="admin-kyc-section-head-text">
                <h2 class="card-title">Final KYC (seller panel)</h2>
                <p class="card-subtitle admin-kyc-section-sub">Documents submitted in the seller dashboard — approve before they can sell. · Search filters this list only.</p>
              </div>
              <?php if ($finalKycRows !== []): ?>
                <label class="admin-users-search-wrap admin-kyc-search-wrap" for="adminKycFinalSearch">
                  <span class="admin-users-search-icon admin-kyc-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                  </span>
                  <input
                    type="search"
                    id="adminKycFinalSearch"
                    class="admin-users-search-input admin-kyc-search-input"
                    placeholder="Search name, email, GST, bank, city…"
                    autocomplete="off"
                    aria-label="Search final KYC list"
                  >
                </label>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body card-body--flush">
            <div class="admin-table-wrap">
              <table class="admin-table admin-kyc-table">
                <thead>
                  <tr>
                    <th>Seller</th>
                    <th>Business + proof</th>
                    <th>Bank + address</th>
                    <th>Final status</th>
                    <th class="admin-table__th-narrow">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($finalKycRows as $row): ?>
                    <?php
                    $isKycCompleted = (int) ($row['kyc_completed'] ?? 0) === 1;
                    $isFinalApproved = (int) ($row['kyc_final_approved'] ?? 0) === 1;
                    $editStatus = (string) ($row['kyc_edit_request_status'] ?? 'none');
                    $fn = trim((string) ($row['full_name'] ?? ''));
                    $ini = strtoupper(substr(preg_replace('/\s+/', '', $fn), 0, 2));
                    if ($ini === '') {
                        $ini = '?';
                    }
                    $hayFinal = admin_kyc_final_haystack($row);
                    ?>
                    <tr class="admin-kyc-final-row" data-kyc-final-search="<?= h($hayFinal) ?>">
                      <td>
                        <div class="admin-cell-user">
                          <div class="admin-avatar-sm"><?= h($ini) ?></div>
                          <div class="admin-kyc-cell-stack">
                            <span class="admin-kyc-strong"><?= h($fn !== '' ? $fn : '—') ?></span>
                            <span class="admin-kyc-meta"><?= h((string) $row['email']) ?></span>
                            <a class="admin-kyc-inline-link" href="seller-view.php?id=<?= (int) $row['id'] ?>">Seller profile →</a>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="admin-kyc-cell-stack">
                          <span class="admin-kyc-meta">Business · <?= h((string) ($row['business_name'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">GST · <?= h((string) ($row['gst_number'] ?: '—')) ?></span>
                          <?php if ((string) ($row['gst_doc_path'] ?? '') !== ''): ?>
                            <span class="admin-kyc-meta">GST doc · <a href="../<?= h((string) $row['gst_doc_path']) ?>" target="_blank" rel="noopener" class="admin-kyc-doc-link">View</a></span>
                          <?php endif; ?>
                          <span class="admin-kyc-meta">PAN · <?= h((string) ($row['pan_number'] ?: '—')) ?></span>
                          <?php if ((string) ($row['pan_doc_path'] ?? '') !== ''): ?>
                            <span class="admin-kyc-meta">PAN doc · <a href="../<?= h((string) $row['pan_doc_path']) ?>" target="_blank" rel="noopener" class="admin-kyc-doc-link">View</a></span>
                          <?php endif; ?>
                          <span class="admin-kyc-meta">Aadhaar · <?= h((string) ($row['aadhaar_number'] ?: '—')) ?></span>
                          <?php if ((string) ($row['aadhaar_doc_path'] ?? '') !== ''): ?>
                            <span class="admin-kyc-meta">Aadhaar doc · <a href="../<?= h((string) $row['aadhaar_doc_path']) ?>" target="_blank" rel="noopener" class="admin-kyc-doc-link">View</a></span>
                          <?php endif; ?>
                          <span class="admin-kyc-meta"><?= h(strtoupper(str_replace('_', ' ', (string) ($row['id_proof_type'] ?: '-')))) ?> · <?= h((string) ($row['id_proof_number'] ?: '—')) ?></span>
                        </div>
                      </td>
                      <td>
                        <div class="admin-kyc-cell-stack">
                          <span class="admin-kyc-meta">Bank · <?= h((string) ($row['bank_name'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">Holder · <?= h((string) ($row['bank_account_name'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">A/C · <?= h((string) ($row['bank_account_number'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta">IFSC · <?= h((string) ($row['bank_ifsc'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta"><?= h((string) ($row['address_line1'] ?: '—')) ?></span>
                          <span class="admin-kyc-meta"><?= h((string) ($row['city'] ?: '-')) ?>, <?= h((string) ($row['state'] ?: '-')) ?> — <?= h((string) ($row['pin_code'] ?: '—')) ?></span>
                        </div>
                      </td>
                      <td>
                        <div class="admin-kyc-cell-stack">
                          <?php if (!$isKycCompleted): ?>
                            <span class="admin-status admin-status--processing">KYC details pending</span>
                          <?php elseif ($isFinalApproved): ?>
                            <span class="admin-status admin-status--delivered">Final approved</span>
                          <?php else: ?>
                            <span class="admin-status admin-status--processing">Pending final approval</span>
                          <?php endif; ?>
                          <?php if ((string) ($row['kyc_updated_at'] ?? '') !== ''): ?>
                            <span class="admin-kyc-meta">Submitted · <?= h(admin_kyc_fmt_dt($row['kyc_updated_at'])) ?></span>
                          <?php endif; ?>
                          <?php if ((string) ($row['kyc_final_reviewed_at'] ?? '') !== ''): ?>
                            <span class="admin-kyc-meta">Reviewed · <?= h(admin_kyc_fmt_dt($row['kyc_final_reviewed_at'])) ?></span>
                            <span class="admin-kyc-meta">By · <?= h((string) ($row['reviewed_by_name'] ?: 'Admin')) ?></span>
                          <?php endif; ?>
                          <?php if ((string) ($row['kyc_rejection_reason'] ?? '') !== ''): ?>
                            <span class="admin-kyc-reason">Reason · <?= h((string) $row['kyc_rejection_reason']) ?></span>
                          <?php endif; ?>
                          <?php if ($isFinalApproved): ?>
                            <span class="admin-kyc-meta">Edit · <?= h(ucfirst($editStatus)) ?></span>
                            <?php if ((string) ($row['kyc_edit_requested_at'] ?? '') !== ''): ?>
                              <span class="admin-kyc-meta">Requested · <?= h(admin_kyc_fmt_dt($row['kyc_edit_requested_at'])) ?></span>
                            <?php endif; ?>
                            <?php if ((string) ($row['kyc_edit_reviewed_at'] ?? '') !== ''): ?>
                              <span class="admin-kyc-meta">Edit reviewed · <?= h(admin_kyc_fmt_dt($row['kyc_edit_reviewed_at'])) ?></span>
                            <?php endif; ?>
                            <?php if ((string) ($row['kyc_edit_rejection_reason'] ?? '') !== ''): ?>
                              <span class="admin-kyc-reason">Edit · <?= h((string) $row['kyc_edit_rejection_reason']) ?></span>
                            <?php endif; ?>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <?php if ($isKycCompleted && !$isFinalApproved): ?>
                          <form method="post" class="admin-kyc-action-form">
                            <input type="hidden" name="seller_id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" name="action" value="final_approve" class="admin-btn admin-btn--primary admin-kyc-action-form__btn" onclick="return confirm('Final KYC approve karke product add access dena hai?')">Final approve</button>
                            <input type="text" name="final_rejection_reason" class="admin-input admin-kyc-action-form__input" placeholder="Reject reason (min 5)" minlength="5" autocomplete="off">
                            <button type="submit" name="action" value="final_reject" class="admin-btn admin-kyc-action-form__btn admin-kyc-action-form__btn--reject" onclick="return confirm('Final KYC reject karna hai?')">Final reject</button>
                          </form>
                        <?php elseif ($isFinalApproved && $editStatus === 'pending'): ?>
                          <form method="post" class="admin-kyc-action-form">
                            <input type="hidden" name="seller_id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" name="action" value="final_edit_approve" class="admin-btn admin-btn--primary admin-kyc-action-form__btn" onclick="return confirm('Seller ko KYC edit access dena hai?')">Approve edit</button>
                            <input type="text" name="final_edit_rejection_reason" class="admin-input admin-kyc-action-form__input" placeholder="Reject reason (min 5)" minlength="5" autocomplete="off">
                            <button type="submit" name="action" value="final_edit_reject" class="admin-btn admin-kyc-action-form__btn admin-kyc-action-form__btn--reject" onclick="return confirm('Edit request reject karna hai?')">Reject edit</button>
                          </form>
                        <?php elseif (!$isKycCompleted): ?>
                          <span class="admin-kyc-muted">Waiting for seller</span>
                        <?php elseif ($isFinalApproved && (int) ($row['kyc_edit_unlocked'] ?? 0) === 1): ?>
                          <span class="admin-kyc-muted">Edit access granted</span>
                        <?php else: ?>
                          <span class="admin-kyc-muted">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if ($finalKycRows !== []): ?>
                  <tr id="adminKycFinalNoMatchRow" class="admin-kyc-no-match-row">
                    <td colspan="5">
                      <div class="admin-kyc-no-match">
                        <strong class="admin-kyc-no-match__title">No matches</strong>
                        <p class="admin-kyc-no-match__text">Try another keyword (this list only).</p>
                      </div>
                    </td>
                  </tr>
                  <?php endif; ?>
                  <?php if ($finalKycRows === []): ?>
                    <tr><td colspan="5"><p class="admin-empty-hint admin-empty-hint--boxed admin-kyc-empty-p">No sellers in this list.</p></td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if ($finalKycRows !== []): ?>
            <script>
            (function () {
              var searchInput = document.getElementById('adminKycFinalSearch');
              if (!searchInput) return;
              var rows = document.querySelectorAll('tr.admin-kyc-final-row');
              var noMatch = document.getElementById('adminKycFinalNoMatchRow');
              function run() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var words = q.split(/\s+/).filter(Boolean);
                var any = false;
                rows.forEach(function (tr) {
                  var hay = (tr.getAttribute('data-kyc-final-search') || '').toLowerCase();
                  var show = words.length === 0 || words.every(function (w) { return hay.indexOf(w) !== -1; });
                  tr.style.display = show ? '' : 'none';
                  if (show) any = true;
                });
                if (noMatch) noMatch.style.display = (words.length > 0 && !any) ? 'table-row' : 'none';
              }
              searchInput.addEventListener('input', run);
              searchInput.addEventListener('search', run);
            })();
            </script>
            <?php endif; ?>
            <?php if ($totalFinalKyc > 0): ?>
            <?php
            $paginationScript = 'seller-kyc.php';
            $paginationTotal = $totalFinalKyc;
            $paginationPage = $pFin['page'];
            $paginationPerPage = $pFin['perPage'];
            $paginationTotalPages = $pFin['totalPages'];
            $paginationPageKey = 'fin_page';
            $paginationPerPageKey = 'fin_per_page';
            require __DIR__ . '/partials/table-pagination.php';
            ?>
            <?php endif; ?>
          </div>
        </div>
        </div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
