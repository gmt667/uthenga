<?php
/**
 * Uthenga - Admin Vendor Validation & Compliance Center
 */
$pageTitle = 'Vendor Verification & Validation';
$activeNav = 'admin-vendors';

require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$err = '';

// Ensure schema columns exist for vendor verification
try {
    if (!uthenga_column_exists('vendor_profiles', 'rejection_reason')) {
        dbExecute("ALTER TABLE vendor_profiles ADD COLUMN rejection_reason TEXT NULL AFTER approval_status");
    }
    if (!uthenga_column_exists('vendor_profiles', 'business_reg_number')) {
        dbExecute("ALTER TABLE vendor_profiles ADD COLUMN business_reg_number VARCHAR(80) NULL AFTER category");
    }
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action   = (string)($_POST['action'] ?? '');
    $vendorId = (string)($_POST['vendor_id'] ?? '');
    $reason   = trim((string)($_POST['reason'] ?? ''));

    if ($vendorId !== '') {
        $adminId = $_SESSION['user_id'] ?? 'admin';
        if ($action === 'approve') {
            dbExecute("UPDATE vendor_profiles SET approval_status='approved', approved_at=NOW(), approved_by=? WHERE vendor_id=?", [$adminId, $vendorId]);
            dbExecute("UPDATE users SET is_approved=1, account_status='active' WHERE id=?", [$vendorId]);
            logAction('Vendor Approved', "Admin ($adminId) approved and verified vendor: $vendorId");
            $message = "Vendor account ($vendorId) verified and approved successfully.";
        } elseif ($action === 'reject') {
            dbExecute("UPDATE vendor_profiles SET approval_status='rejected', rejection_reason=? WHERE vendor_id=?", [$reason ?: 'Application credentials incomplete', $vendorId]);
            dbExecute("UPDATE users SET is_approved=0, account_status='rejected' WHERE id=?", [$vendorId]);
            logAction('Vendor Rejected', "Admin ($adminId) rejected vendor: $vendorId. Reason: $reason");
            $message = "Vendor account application rejected.";
        } elseif ($action === 'suspend') {
            dbExecute("UPDATE vendor_profiles SET approval_status='suspended', rejection_reason=? WHERE vendor_id=?", [$reason ?: 'Suspended by system administrator', $vendorId]);
            dbExecute("UPDATE users SET is_approved=0, account_status='suspended' WHERE id=?", [$vendorId]);
            logAction('Vendor Suspended', "Admin ($adminId) suspended vendor: $vendorId. Reason: $reason");
            $message = "Vendor account suspended.";
        } elseif ($action === 'reactivate') {
            dbExecute("UPDATE vendor_profiles SET approval_status='approved', approved_at=NOW() WHERE vendor_id=?", [$vendorId]);
            dbExecute("UPDATE users SET is_approved=1, account_status='active' WHERE id=?", [$vendorId]);
            logAction('Vendor Reactivated', "Admin ($adminId) reactivated vendor: $vendorId");
            $message = "Vendor account reactivated successfully.";
        } elseif ($action === 'verify_payment_profile') {
            dbExecute("UPDATE uthenga_vendor_payment_profiles SET verification_status='VERIFIED', verified_by=?, verified_at=NOW() WHERE vendor_id=?", [$adminId, $vendorId]);
            logAction('Vendor Payment Profile Verified', "Admin ($adminId) verified the settlement destination for vendor: $vendorId");
            $message = "Vendor payment settlement profile verified.";
        } elseif ($action === 'reject_payment_profile') {
            dbExecute("UPDATE uthenga_vendor_payment_profiles SET verification_status='REJECTED', verified_by=?, verified_at=NOW() WHERE vendor_id=?", [$adminId, $vendorId]);
            logAction('Vendor Payment Profile Rejected', "Admin ($adminId) rejected the settlement destination for vendor: $vendorId. Reason: $reason");
            $message = "Vendor payment settlement profile rejected.";
        }
    }
}

$pendingPaymentProfiles = uthenga_table_exists('uthenga_vendor_payment_profiles') ? dbQuery("
    SELECT p.*, u.name AS vendor_name, u.email AS vendor_email
    FROM uthenga_vendor_payment_profiles p
    INNER JOIN users u ON u.id = p.vendor_id
    WHERE p.verification_status = 'PENDING'
    ORDER BY p.updated_at DESC
") : [];

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
$search       = trim((string)($_GET['q'] ?? ''));

$where = ["1=1"];
$params = [];

if ($statusFilter !== 'all' && in_array($statusFilter, ['pending', 'approved', 'rejected', 'suspended'], true)) {
    $where[] = "LOWER(vp.approval_status) = ?";
    $params[] = $statusFilter;
}

if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR vp.category LIKE ? OR vp.city LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}

$whereStr = implode(" AND ", $where);

$vendors = dbQuery("
    SELECT vp.vendor_id AS id,
           u.name AS business_name,
           vp.phone, vp.address, vp.city, vp.category, vp.description,
           COALESCE(vp.business_reg_number, CONCAT('REG-MW-', vp.vendor_id)) AS reg_number,
           COALESCE(vp.rejection_reason, '') AS rejection_reason,
           vp.approval_status AS status, vp.created_at, vp.approved_at,
           u.name AS full_name, u.email, u.role, u.is_approved
    FROM vendor_profiles vp
    INNER JOIN users u ON u.id = vp.vendor_id
    WHERE $whereStr
    ORDER BY FIELD(vp.approval_status, 'pending', 'rejected', 'suspended', 'approved'), vp.created_at DESC
", $params);

// Counts for filter pills
$counts = [
    'all'       => (int)dbQueryOne("SELECT COUNT(*) AS c FROM vendor_profiles")['c'],
    'pending'   => (int)dbQueryOne("SELECT COUNT(*) AS c FROM vendor_profiles WHERE LOWER(approval_status)='pending'")['c'],
    'approved'  => (int)dbQueryOne("SELECT COUNT(*) AS c FROM vendor_profiles WHERE LOWER(approval_status)='approved'")['c'],
    'rejected'  => (int)dbQueryOne("SELECT COUNT(*) AS c FROM vendor_profiles WHERE LOWER(approval_status)='rejected'")['c'],
    'suspended' => (int)dbQueryOne("SELECT COUNT(*) AS c FROM vendor_profiles WHERE LOWER(approval_status)='suspended'")['c'],
];

function vendorStatusBadge(string $status): string {
    return match (strtolower(trim($status))) {
        'approved'  => 'badge-approved',
        'rejected'  => 'badge-rejected',
        'suspended' => 'badge-cancelled',
        default     => 'badge-pending',
    };
}

function vendorStatusLabel(string $status): string {
    return match (strtolower(trim($status))) {
        'approved'  => '✓ Verified & Approved',
        'rejected'  => '✕ Rejected',
        'suspended' => '⚠ Suspended',
        'pending'   => '● Pending Verification',
        default     => ucwords(str_replace(['_', '-'], ' ', strtolower(trim($status)))),
    };
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('store') ?><span>Vendor Verification & Validation</span></h1>
    <p class="text-muted">Review, validate, approve, or suspend provider business profiles.</p>
  </div>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success">✓ <?= e($message) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">✕ <?= e($err) ?></div><?php endif; ?>

<!-- Vendor Payment Settlement Profiles Awaiting Verification -->
<?php if (!empty($pendingPaymentProfiles)): ?>
<div class="glass-panel" style="padding:1.25rem;margin-bottom:1.25rem;">
  <h3 style="margin-top:0;">Payment Settlement Profiles Awaiting Verification (<?= count($pendingPaymentProfiles) ?>)</h3>
  <p class="text-muted text-sm">Confirm each vendor's payout destination looks legitimate before their next settlement is released against it. This never involves any payment provider credentials — those are centrally managed.</p>
  <div class="table-responsive">
    <table class="admin-table">
      <thead><tr><th>Vendor</th><th>Method</th><th>Destination</th><th>Updated</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($pendingPaymentProfiles as $pp): ?>
          <tr>
            <td><strong><?= e($pp['vendor_name']) ?></strong><div class="text-xs text-muted"><?= e($pp['vendor_email']) ?></div></td>
            <td><?= e($pp['settlement_method'] === 'bank' ? 'Bank Account' : 'Mobile Money') ?></td>
            <td class="text-sm">
              <?php if ($pp['settlement_method'] === 'bank'): ?>
                <?= e($pp['bank_name']) ?> — <?= e($pp['bank_account_name']) ?> (<?= e($pp['bank_account_number']) ?>)
              <?php else: ?>
                <?= e(ucfirst((string) $pp['mobile_money_provider'])) ?> — <?= e($pp['mobile_money_number']) ?>
              <?php endif; ?>
            </td>
            <td class="text-xs text-muted"><?= e($pp['updated_at']) ?></td>
            <td style="white-space:nowrap;">
              <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="verify_payment_profile"><input type="hidden" name="vendor_id" value="<?= e($pp['vendor_id']) ?>"><button type="submit" class="btn btn-sm btn-primary">Verify</button></form>
              <form method="POST" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="reject_payment_profile"><input type="hidden" name="vendor_id" value="<?= e($pp['vendor_id']) ?>"><button type="submit" class="btn btn-sm btn-secondary">Reject</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Status Filter Tabs & Search Bar -->
<div class="glass-panel" style="padding:1rem;margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.85rem;">
  <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
    <a href="?status=all<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter==='all'?'btn-primary':'btn-secondary' ?>">All (<?= $counts['all'] ?>)</a>
    <a href="?status=pending<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter==='pending'?'btn-primary':'btn-secondary' ?>">
      Pending (<?= $counts['pending'] ?>)
    </a>
    <a href="?status=approved<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter==='approved'?'btn-primary':'btn-secondary' ?>">Approved (<?= $counts['approved'] ?>)</a>
    <a href="?status=rejected<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter==='rejected'?'btn-primary':'btn-secondary' ?>">Rejected (<?= $counts['rejected'] ?>)</a>
    <a href="?status=suspended<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $statusFilter==='suspended'?'btn-primary':'btn-secondary' ?>">Suspended (<?= $counts['suspended'] ?>)</a>
  </div>

  <form method="GET" style="display:flex;gap:0.5rem;margin:0;">
    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
    <input type="text" name="q" placeholder="Search name, email, city..." value="<?= e($search) ?>" class="form-control" style="width:240px;padding:0.4rem 0.75rem;font-size:0.82rem;">
    <button type="submit" class="btn btn-sm btn-primary">Search</button>
  </form>
</div>

<div class="glass-panel" style="padding:1.25rem;">
  <?php if (empty($vendors)): ?>
    <div style="text-align:center;padding:3rem 0;">
      <h3>No vendors found matching your filter</h3>
      <p class="text-muted" style="margin-top:0.5rem;">Try adjusting search terms or status filter.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Vendor / Business</th>
            <th>Contact Email</th>
            <th>Category</th>
            <th>Location</th>
            <th>Status</th>
            <th style="text-align:right;">Verification Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vendors as $vendor): ?>
            <tr>
              <td>
                <div style="font-weight:800;color:var(--clr-text);"><?= e($vendor['business_name']) ?></div>
                <div class="text-xs text-muted">Owner: <?= e($vendor['full_name']) ?> · Joined <?= e(substr($vendor['created_at'], 0, 10)) ?></div>
              </td>
              <td><?= e($vendor['email']) ?></td>
              <td><span class="badge" style="background:rgba(6,182,212,0.12);color:var(--clr-primary);"><?= e($vendor['category'] ?: 'Event Organizer') ?></span></td>
              <td><?= e(trim(($vendor['city'] ? $vendor['city'] . ', ' : '') . ($vendor['address'] ?? '')) ?: 'Malawi') ?></td>
              <td>
                <span class="badge <?= vendorStatusBadge($vendor['status']) ?>"><?= e(vendorStatusLabel((string) $vendor['status'])) ?></span>
                <?php if ($vendor['rejection_reason']): ?>
                  <div class="text-xs text-muted" style="margin-top:2px;color:#ef4444;">Note: <?= e($vendor['rejection_reason']) ?></div>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <div style="display:inline-flex;gap:0.4rem;flex-wrap:wrap;justify-content:flex-end;">
                  <button class="btn btn-sm btn-secondary" onclick='viewDetails(<?= json_encode($vendor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>Details</button>

                  <?php if ($vendor['status'] !== 'approved'): ?>
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                      <input type="hidden" name="vendor_id" value="<?= e($vendor['id']) ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" class="btn btn-sm btn-primary">Approve & Verify</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($vendor['status'] === 'approved'): ?>
                    <button class="btn btn-sm btn-warning" onclick="openActionModal('suspend', '<?= e($vendor['id']) ?>', '<?= e($vendor['business_name']) ?>')">Suspend</button>
                  <?php endif; ?>

                  <?php if ($vendor['status'] === 'suspended'): ?>
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                      <input type="hidden" name="vendor_id" value="<?= e($vendor['id']) ?>">
                      <input type="hidden" name="action" value="reactivate">
                      <button type="submit" class="btn btn-sm btn-primary">Re-activate</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($vendor['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-danger" onclick="openActionModal('reject', '<?= e($vendor['id']) ?>', '<?= e($vendor['business_name']) ?>')">Reject</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Modal: Vendor Profile Details -->
<div class="modal-overlay" id="vendor-details-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <h3 id="modal-vendor-name">Vendor Business Profile</h3>
      <button class="modal-close" type="button" onclick="closeModal('vendor-details-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="display:grid;gap:1.1rem;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
          <div>
            <div class="text-xs text-muted">Vendor ID</div>
            <div id="modal-vendor-id" class="font-mono" style="font-weight:700;margin-top:0.25rem;"></div>
          </div>
          <div>
            <div class="text-xs text-muted">Business Reg Number</div>
            <div id="modal-vendor-reg" class="font-mono" style="font-weight:700;margin-top:0.25rem;"></div>
          </div>
        </div>

        <div>
          <div class="text-xs text-muted">Contact Details</div>
          <div id="modal-vendor-contact" style="font-weight:600;margin-top:0.25rem;"></div>
        </div>

        <div>
          <div class="text-xs text-muted">Location</div>
          <div id="modal-vendor-location" style="font-weight:600;margin-top:0.25rem;"></div>
        </div>

        <div>
          <div class="text-xs text-muted">Business Description</div>
          <p id="modal-vendor-desc" class="text-sm" style="margin-top:0.25rem;line-height:1.5;color:var(--clr-text-soft);"></p>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" type="button" onclick="closeModal('vendor-details-modal')">Close</button>
    </div>
  </div>
</div>

<!-- Modal: Reject / Suspend Prompt -->
<div class="modal-overlay" id="vendor-action-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal" style="max-width:480px;">
    <form method="POST" id="vendor-action-form">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="vendor_id" id="action-vendor-id">
      <input type="hidden" name="action" id="action-type">

      <div class="modal-header">
        <h3 id="action-modal-title">Vendor Action</h3>
        <button class="modal-close" type="button" onclick="closeModal('vendor-action-modal')">&times;</button>
      </div>
      <div class="modal-body">
        <p id="action-modal-msg" style="font-size:0.85rem;color:var(--clr-text-soft);margin-bottom:1rem;"></p>
        <div class="form-group">
          <label class="form-label" for="action-reason">Reason / Note (Sent to vendor)</label>
          <textarea name="reason" id="action-reason" class="form-control" rows="3" placeholder="Provide reason for this verification decision..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" onclick="closeModal('vendor-action-modal')">Cancel</button>
        <button class="btn btn-danger" type="submit" id="action-submit-btn">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
function viewDetails(vendor) {
  document.getElementById('modal-vendor-name').textContent = (vendor.business_name || vendor.full_name) + ' — Verification Profile';
  document.getElementById('modal-vendor-id').textContent = vendor.id || '';
  document.getElementById('modal-vendor-reg').textContent = vendor.reg_number || 'REG-MW-' + (vendor.id || '001');
  document.getElementById('modal-vendor-contact').innerHTML = 'Owner: ' + (vendor.full_name || 'N/A') + '<br>Email: ' + (vendor.email || 'N/A') + '<br>Phone: ' + (vendor.phone || 'Not provided');
  document.getElementById('modal-vendor-location').textContent = (vendor.city ? vendor.city + ' · ' : '') + (vendor.address || 'Malawi');
  document.getElementById('modal-vendor-desc').textContent = vendor.description || 'No business description provided.';
  openModal('vendor-details-modal');
}

function openActionModal(action, vendorId, businessName) {
  document.getElementById('action-vendor-id').value = vendorId;
  document.getElementById('action-type').value = action;
  var title = action === 'reject' ? 'Reject Vendor Application' : 'Suspend Vendor Account';
  var msg = 'Are you sure you want to ' + action + ' <strong>' + (businessName || vendorId) + '</strong>?';
  document.getElementById('action-modal-title').textContent = title;
  document.getElementById('action-modal-msg').innerHTML = msg;
  document.getElementById('action-submit-btn').className = action === 'reject' ? 'btn btn-danger' : 'btn btn-warning';
  document.getElementById('action-submit-btn').textContent = action === 'reject' ? 'Confirm Rejection' : 'Confirm Suspension';
  openModal('vendor-action-modal');
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
