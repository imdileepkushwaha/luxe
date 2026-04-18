<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

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
     LIMIT 100"
);
$requestsSt->execute($params);
$requests = $requestsSt->fetchAll();

$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_create_requests WHERE status = 'pending'")->fetchColumn();
$approvedCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_create_requests WHERE status = 'approved'")->fetchColumn();
$rejectedCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_create_requests WHERE status = 'rejected'")->fetchColumn();
$kycPendingFinalCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_users WHERE is_active = 1 AND kyc_completed = 1 AND kyc_final_approved = 0")->fetchColumn();
$kycFinalApprovedCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_users WHERE is_active = 1 AND kyc_completed = 1 AND kyc_final_approved = 1")->fetchColumn();
$pendingEditCount = (int) $pdo->query("SELECT COUNT(*) FROM seller_users WHERE is_active = 1 AND kyc_final_approved = 1 AND kyc_edit_request_status = 'pending'")->fetchColumn();

$finalKycRows = $pdo->query(
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
     LIMIT 100"
)->fetchAll();

$pendingEditRows = $pdo->query(
    "SELECT s.id, s.full_name, s.email, s.kyc_edit_requested_at, s.kyc_updated_at
     FROM seller_users s
     WHERE s.is_active = 1
       AND s.kyc_final_approved = 1
       AND s.kyc_edit_request_status = 'pending'
     ORDER BY s.kyc_edit_requested_at DESC, s.id DESC
     LIMIT 100"
)->fetchAll();

require __DIR__ . '/partials/shell-top.php';
?>

<?php if ($flash): ?>
  <div class="admin-del-flash<?= !empty($flash['ok']) ? ' admin-del-flash--ok' : ' admin-del-flash--err' ?>" role="status" style="margin-bottom:14px">
    <?= h((string) ($flash['text'] ?? '')) ?>
  </div>
<?php endif; ?>

<div class="admin-grid admin-grid--stats" style="margin-bottom:16px">
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Pending registration</div>
      <div class="admin-stat__value"><?= $pendingCount ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Approved</div>
      <div class="admin-stat__value"><?= $approvedCount ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Pending final KYC</div>
      <div class="admin-stat__value"><?= $kycPendingFinalCount ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Final approved</div>
      <div class="admin-stat__value"><?= $kycFinalApprovedCount ?></div>
    </div>
  </div>
  <div class="admin-card admin-stat">
    <div>
      <div class="admin-stat__label">Pending edit requests</div>
      <div class="admin-stat__value"><?= $pendingEditCount ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
    <h1 class="admin-page-title card-title">Seller registration requests (initial approval)</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="admin-btn<?= $statusFilter === 'pending' ? ' admin-btn--primary' : '' ?>" href="seller-kyc.php?status=pending">Pending</a>
      <a class="admin-btn<?= $statusFilter === 'approved' ? ' admin-btn--primary' : '' ?>" href="seller-kyc.php?status=approved">Approved</a>
      <a class="admin-btn<?= $statusFilter === 'rejected' ? ' admin-btn--primary' : '' ?>" href="seller-kyc.php?status=rejected">Rejected</a>
      <a class="admin-btn<?= $statusFilter === 'all' ? ' admin-btn--primary' : '' ?>" href="seller-kyc.php?status=all">All</a>
    </div>
  </div>
  <div class="card-body card-body--flush">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Seller</th>
            <th>Business + KYC</th>
            <th>Bank details</th>
            <th>Address</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $request): ?>
            <?php $status = (string) ($request['status'] ?? 'pending'); ?>
            <tr>
              <td>
                <strong><?= h((string) $request['full_name']) ?></strong><br>
                <small><?= h((string) $request['email']) ?></small><br>
                <small>Phone: <?= h((string) ($request['phone'] ?: '-')) ?></small><br>
                <small>Categories: <?= h((string) ($request['requested_categories'] ?: '-')) ?></small><br>
                <small>Submitted: <?= h((string) $request['created_at']) ?></small>
              </td>
              <td>
                <strong><?= h((string) ($request['business_name'] ?: '-')) ?></strong><br>
                <small>GST: <?= h((string) ($request['gst_number'] ?: '-')) ?></small><br>
                <small>PAN: <?= h((string) ($request['pan_number'] ?: '-')) ?></small><br>
                <small>Aadhaar: <?= h((string) ($request['aadhaar_number'] ?: '-')) ?></small><br>
                <small><?= h(strtoupper(str_replace('_', ' ', (string) ($request['id_proof_type'] ?: '-')))) ?>: <?= h((string) ($request['id_proof_number'] ?: '-')) ?></small>
                <?php if ((string) ($request['note'] ?? '') !== ''): ?>
                  <br><small>Note: <?= h((string) $request['note']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <small>Holder: <?= h((string) ($request['bank_account_name'] ?: '-')) ?></small><br>
                <small>A/C: <?= h((string) ($request['bank_account_number'] ?: '-')) ?></small><br>
                <small>IFSC: <?= h((string) ($request['bank_ifsc'] ?: '-')) ?></small>
              </td>
              <td>
                <small><?= h((string) ($request['address_line1'] ?: '-')) ?></small><br>
                <small><?= h((string) ($request['city'] ?: '-')) ?>, <?= h((string) ($request['state'] ?: '-')) ?></small><br>
                <small>PIN: <?= h((string) ($request['pin_code'] ?: '-')) ?></small>
              </td>
              <td>
                <span class="admin-status admin-status--<?= h($status === 'approved' ? 'delivered' : ($status === 'rejected' ? 'cancelled' : 'processing')) ?>">
                  <?= h(ucfirst($status)) ?>
                </span>
                <?php if ((string) ($request['reviewed_at'] ?? '') !== ''): ?>
                  <br><small><?= h((string) $request['reviewed_at']) ?></small>
                  <br><small>By: <?= h((string) ($request['reviewed_by_name'] ?: 'Admin')) ?></small>
                <?php endif; ?>
                <?php if ($status === 'approved' && (int) ($request['seller_id'] ?? 0) > 0): ?>
                  <br><a href="seller-view.php?id=<?= (int) $request['seller_id'] ?>">Open seller</a>
                <?php endif; ?>
                <?php if ($status === 'rejected' && (string) ($request['rejection_reason'] ?? '') !== ''): ?>
                  <br><small>Reason: <?= h((string) $request['rejection_reason']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($status === 'pending'): ?>
                  <form method="post" style="display:flex;flex-direction:column;gap:8px;min-width:180px">
                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                    <button type="submit" name="action" value="approve" class="admin-btn admin-btn--primary" onclick="return confirm('Is KYC request ko approve karna hai?')">Approve</button>
                    <input type="text" name="rejection_reason" placeholder="Reject reason (required)" minlength="5">
                    <button type="submit" name="action" value="reject" class="admin-btn" style="border:1px solid var(--admin-border);color:#b91c1c" onclick="return confirm('Is request ko reject karna hai?')">Reject</button>
                  </form>
                <?php else: ?>
                  <small>No action</small>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($requests === []): ?>
            <tr><td colspan="6">No seller KYC requests found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-header">
    <h2 class="card-title">Pending KYC edit requests</h2>
  </div>
  <div class="card-body card-body--flush">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Seller</th>
            <th>KYC last submitted</th>
            <th>Edit requested at</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingEditRows as $row): ?>
            <tr>
              <td>
                <strong><?= h((string) $row['full_name']) ?></strong><br>
                <small><?= h((string) $row['email']) ?></small>
              </td>
              <td><?= h((string) ($row['kyc_updated_at'] ?: '-')) ?></td>
              <td><?= h((string) ($row['kyc_edit_requested_at'] ?: '-')) ?></td>
              <td>
                <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                  <input type="hidden" name="seller_id" value="<?= (int) $row['id'] ?>">
                  <button type="submit" name="action" value="final_edit_approve" class="admin-btn admin-btn--primary" onclick="return confirm('Seller ko KYC edit access dena hai?')">Approve edit</button>
                  <input type="text" name="final_edit_rejection_reason" placeholder="Reject reason (required)" minlength="5">
                  <button type="submit" name="action" value="final_edit_reject" class="admin-btn" style="border:1px solid var(--admin-border);color:#b91c1c" onclick="return confirm('Edit request reject karna hai?')">Reject edit</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($pendingEditRows === []): ?>
            <tr><td colspan="4">No pending KYC edit requests.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card" style="margin-top:16px">
  <div class="card-header">
    <h2 class="card-title">Final KYC approval (seller panel submissions)</h2>
  </div>
  <div class="card-body card-body--flush">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Seller</th>
            <th>Business + Proof</th>
            <th>Bank + Address</th>
            <th>Final status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($finalKycRows as $row): ?>
            <?php
            $isKycCompleted = (int) ($row['kyc_completed'] ?? 0) === 1;
            $isFinalApproved = (int) ($row['kyc_final_approved'] ?? 0) === 1;
            $editStatus = (string) ($row['kyc_edit_request_status'] ?? 'none');
            ?>
            <tr>
              <td>
                <strong><?= h((string) $row['full_name']) ?></strong><br>
                <small><?= h((string) $row['email']) ?></small><br>
                <small><a href="seller-view.php?id=<?= (int) $row['id'] ?>">Open seller</a></small>
              </td>
              <td>
                <small>Business: <?= h((string) ($row['business_name'] ?: '-')) ?></small><br>
                <small>GST: <?= h((string) ($row['gst_number'] ?: '-')) ?></small><br>
                <?php if ((string) ($row['gst_doc_path'] ?? '') !== ''): ?>
                  <small>GST doc: <a href="../<?= h((string) $row['gst_doc_path']) ?>" target="_blank" rel="noopener">View</a></small><br>
                <?php endif; ?>
                <small>PAN: <?= h((string) ($row['pan_number'] ?: '-')) ?></small><br>
                <?php if ((string) ($row['pan_doc_path'] ?? '') !== ''): ?>
                  <small>PAN doc: <a href="../<?= h((string) $row['pan_doc_path']) ?>" target="_blank" rel="noopener">View</a></small><br>
                <?php endif; ?>
                <small>Aadhaar: <?= h((string) ($row['aadhaar_number'] ?: '-')) ?></small><br>
                <?php if ((string) ($row['aadhaar_doc_path'] ?? '') !== ''): ?>
                  <small>Aadhaar doc: <a href="../<?= h((string) $row['aadhaar_doc_path']) ?>" target="_blank" rel="noopener">View</a></small><br>
                <?php endif; ?>
                <small><?= h(strtoupper(str_replace('_', ' ', (string) ($row['id_proof_type'] ?: '-')))) ?>: <?= h((string) ($row['id_proof_number'] ?: '-')) ?></small>
              </td>
              <td>
                <small>Bank: <?= h((string) ($row['bank_name'] ?: '-')) ?></small><br>
                <small>Holder: <?= h((string) ($row['bank_account_name'] ?: '-')) ?></small><br>
                <small>A/C: <?= h((string) ($row['bank_account_number'] ?: '-')) ?></small><br>
                <small>IFSC: <?= h((string) ($row['bank_ifsc'] ?: '-')) ?></small><br>
                <small><?= h((string) ($row['address_line1'] ?: '-')) ?></small><br>
                <small><?= h((string) ($row['city'] ?: '-')) ?>, <?= h((string) ($row['state'] ?: '-')) ?> - <?= h((string) ($row['pin_code'] ?: '-')) ?></small>
              </td>
              <td>
                <?php if (!$isKycCompleted): ?>
                  <span class="admin-status admin-status--processing">KYC details pending</span>
                <?php elseif ($isFinalApproved): ?>
                  <span class="admin-status admin-status--delivered">Final approved</span>
                <?php else: ?>
                  <span class="admin-status admin-status--processing">Pending final approval</span>
                <?php endif; ?>
                <?php if ((string) ($row['kyc_updated_at'] ?? '') !== ''): ?>
                  <br><small>Submitted: <?= h((string) $row['kyc_updated_at']) ?></small>
                <?php endif; ?>
                <?php if ((string) ($row['kyc_final_reviewed_at'] ?? '') !== ''): ?>
                  <br><small>Reviewed: <?= h((string) $row['kyc_final_reviewed_at']) ?></small>
                  <br><small>By: <?= h((string) ($row['reviewed_by_name'] ?: 'Admin')) ?></small>
                <?php endif; ?>
                <?php if ((string) ($row['kyc_rejection_reason'] ?? '') !== ''): ?>
                  <br><small>Reason: <?= h((string) $row['kyc_rejection_reason']) ?></small>
                <?php endif; ?>
                <?php if ($isFinalApproved): ?>
                  <br><small>Edit request: <?= h(ucfirst($editStatus)) ?></small>
                  <?php if ((string) ($row['kyc_edit_requested_at'] ?? '') !== ''): ?>
                    <br><small>Requested: <?= h((string) $row['kyc_edit_requested_at']) ?></small>
                  <?php endif; ?>
                  <?php if ((string) ($row['kyc_edit_reviewed_at'] ?? '') !== ''): ?>
                    <br><small>Edit reviewed: <?= h((string) $row['kyc_edit_reviewed_at']) ?></small>
                  <?php endif; ?>
                  <?php if ((string) ($row['kyc_edit_rejection_reason'] ?? '') !== ''): ?>
                    <br><small>Edit reason: <?= h((string) $row['kyc_edit_rejection_reason']) ?></small>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isKycCompleted && !$isFinalApproved): ?>
                  <form method="post" style="display:flex;flex-direction:column;gap:8px;min-width:190px">
                    <input type="hidden" name="seller_id" value="<?= (int) $row['id'] ?>">
                    <button type="submit" name="action" value="final_approve" class="admin-btn admin-btn--primary" onclick="return confirm('Final KYC approve karke product add access dena hai?')">Final approve</button>
                    <input type="text" name="final_rejection_reason" placeholder="Reject reason (required)" minlength="5">
                    <button type="submit" name="action" value="final_reject" class="admin-btn" style="border:1px solid var(--admin-border);color:#b91c1c" onclick="return confirm('Final KYC reject karna hai?')">Final reject</button>
                  </form>
                <?php elseif ($isFinalApproved && $editStatus === 'pending'): ?>
                  <form method="post" style="display:flex;flex-direction:column;gap:8px;min-width:190px">
                    <input type="hidden" name="seller_id" value="<?= (int) $row['id'] ?>">
                    <button type="submit" name="action" value="final_edit_approve" class="admin-btn admin-btn--primary" onclick="return confirm('Seller ko KYC edit access dena hai?')">Approve edit</button>
                    <input type="text" name="final_edit_rejection_reason" placeholder="Reject reason (required)" minlength="5">
                    <button type="submit" name="action" value="final_edit_reject" class="admin-btn" style="border:1px solid var(--admin-border);color:#b91c1c" onclick="return confirm('Edit request reject karna hai?')">Reject edit</button>
                  </form>
                <?php elseif (!$isKycCompleted): ?>
                  <small>Seller se KYC details ka wait hai</small>
                <?php elseif ($isFinalApproved && (int) ($row['kyc_edit_unlocked'] ?? 0) === 1): ?>
                  <small>Edit access granted</small>
                <?php else: ?>
                  <small>No action</small>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if ($finalKycRows === []): ?>
            <tr><td colspan="5">No sellers found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
