<?php
/**
 * Uthenga - Admin Vendor Verification Page
 */
$pageTitle = 'Vendor Verification';
$activeNav = 'admin-vendors';

require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action   = $_POST['action'] ?? '';
    $vendorId = $_POST['vendor_id'] ?? '';

    if ($vendorId !== '') {
        if ($action === 'approve') {
            dbExecute("UPDATE vendor_profiles SET approval_status='approved', approved_at=NOW() WHERE vendor_id=?", [$vendorId]);
            dbExecute("UPDATE users SET is_approved=1 WHERE id=?", [$vendorId]);
            logAction('Approved Vendor', "Admin approved vendor: $vendorId");
            $message = 'Vendor account approved.';
        } elseif ($action === 'reject') {
            dbExecute("UPDATE vendor_profiles SET approval_status='rejected' WHERE vendor_id=?", [$vendorId]);
            dbExecute("UPDATE users SET is_approved=0 WHERE id=?", [$vendorId]);
            logAction('Rejected Vendor', "Admin rejected vendor: $vendorId");
            $message = 'Vendor account rejected.';
        }
    }
}

$vendors = dbQuery("
    SELECT vp.vendor_id AS id,
           vp.phone, vp.address, vp.city, vp.category, vp.description,
           vp.approval_status AS status, vp.created_at, vp.approved_at,
           u.name AS full_name, u.email, u.role, u.is_approved
    FROM vendor_profiles vp
    INNER JOIN users u ON u.id = vp.vendor_id
    ORDER BY FIELD(vp.approval_status, 'pending', 'rejected', 'approved'), vp.created_at DESC
");

function vendorStatusBadge(string $status): string {
    return match (strtolower($status)) {
        'approved' => 'badge-approved',
        'rejected' => 'badge-rejected',
        'suspended' => 'badge-cancelled',
        default => 'badge-pending',
    };
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('store') ?><span>Vendor Verification</span></h1>
    <p class="text-muted">Review, approve, or suspend provider accounts.</p>
  </div>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>

<div class="glass-panel" style="padding:1.25rem;">
  <?php if (empty($vendors)): ?>
    <div style="text-align:center;padding:3rem 0;">
      <h3>No registered vendors found</h3>
      <p class="text-muted" style="margin-top:0.5rem;">New vendor applications will appear here.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Vendor</th>
            <th>Email</th>
            <th>Category</th>
            <th>Location</th>
            <th>Status</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vendors as $vendor): ?>
            <tr>
              <td>
                <div style="font-weight:700;"><?= e($vendor['full_name']) ?></div>
                <div class="text-xs text-muted">Joined <?= e(substr($vendor['created_at'], 0, 10)) ?></div>
              </td>
              <td><?= e($vendor['email']) ?></td>
              <td><?= e($vendor['category'] ?: $vendor['role'] ?: 'Vendor') ?></td>
              <td><?= e(trim(($vendor['city'] ? $vendor['city'] . ', ' : '') . ($vendor['address'] ?? '')) ?: 'N/A') ?></td>
              <td><span class="badge <?= vendorStatusBadge($vendor['status']) ?>"><?= e(ucfirst((string)$vendor['status'])) ?></span></td>
              <td style="text-align:right;">
                <div style="display:inline-flex;gap:0.5rem;flex-wrap:wrap;justify-content:flex-end;">
                  <button class="btn btn-sm btn-secondary" onclick='viewDetails(<?= json_encode($vendor, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>Details</button>
                  <?php if ($vendor['status'] !== 'approved'): ?>
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                      <input type="hidden" name="vendor_id" value="<?= e($vendor['id']) ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($vendor['status'] !== 'rejected'): ?>
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                      <input type="hidden" name="vendor_id" value="<?= e($vendor['id']) ?>">
                      <input type="hidden" name="action" value="reject">
                      <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                    </form>
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

<div class="modal-overlay" id="vendor-details-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-vendor-name">Vendor Profile</h3>
      <button class="modal-close" type="button" onclick="closeModal('vendor-details-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="display:grid;gap:1rem;">
        <div>
          <div class="text-xs text-muted">Vendor ID</div>
          <div id="modal-vendor-id" class="font-mono" style="font-weight:600;margin-top:0.25rem;"></div>
        </div>
        <div>
          <div class="text-xs text-muted">Contact Info</div>
          <div id="modal-vendor-contact" style="font-weight:600;margin-top:0.25rem;"></div>
        </div>
        <div>
          <div class="text-xs text-muted">Description</div>
          <p id="modal-vendor-desc" class="text-sm" style="margin-top:0.25rem;line-height:1.5;color:var(--clr-text-soft);"></p>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" type="button" onclick="closeModal('vendor-details-modal')">Close</button>
    </div>
  </div>
</div>

<script>
function viewDetails(vendor) {
  document.getElementById('modal-vendor-name').textContent = (vendor.business_name || vendor.full_name) + ' - Profile';
  document.getElementById('modal-vendor-id').textContent = vendor.id || '';
  document.getElementById('modal-vendor-contact').innerHTML = 'Email: ' + (vendor.email || 'N/A') + '<br>Phone: ' + (vendor.phone || 'Not provided') + '<br>Status: ' + (vendor.status || 'pending');
  document.getElementById('modal-vendor-desc').textContent = vendor.description || 'No description provided.';
  openModal('vendor-details-modal');
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>

