<?php
/**
 * Uthenga — Admin Platform Settings & Coupons
 */
$pageTitle = 'Platform Settings';
$activeNav = 'admin-settings';

require_once __DIR__ . '/includes/admin_header.php';

$message = '';
$err = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $pName   = trim($_POST['platform_name'] ?? 'Uthenga');
        $pEmail  = trim($_POST['platform_email'] ?? 'support@uthenga.co');
        $vReg    = isset($_POST['allow_vendor_registration']) ? '1' : '0';
        
        if (empty($pName) || empty($pEmail)) {
            $err = 'Platform name and email are required.';
        } else {
            setSetting('platform_name', $pName, $_SESSION['user_id'] ?? null);
            setSetting('platform_email', $pEmail, $_SESSION['user_id'] ?? null);
            setSetting('allow_vendor_registration', $vReg, $_SESSION['user_id'] ?? null);
            
            logAction('Updated Platform Settings', "Admin updated site configurations");
            $message = "Platform settings updated successfully!";
        }
    } elseif ($action === 'create_coupon') {
        $code      = strtoupper(trim($_POST['code'] ?? ''));
        $type      = $_POST['discount_type'] ?? 'percentage';
        $val       = (float)($_POST['value'] ?? 0);
        $minSpend  = !empty($_POST['min_spend']) ? (float)$_POST['min_spend'] : null;
        $expiry    = $_POST['expiry_date'] ?? '';
        
        if (empty($code) || $val <= 0 || empty($expiry)) {
            $err = 'Coupon code, value, and expiry date are required.';
        } elseif (!uthenga_table_exists('coupons')) {
            $err = 'Coupons table is not yet available. Please run the database migration.';
        } else {
            // Check if coupon code already exists
            $exists = dbCount("SELECT COUNT(*) FROM coupons WHERE code = ?", [$code]);
            if ($exists > 0) {
                $err = "Coupon code '$code' already exists.";
            } else {
                dbExecute(
                    "INSERT INTO coupons (code, discount_type, value, min_spend, expiry_date, is_active) VALUES (?, ?, ?, ?, ?, 1)",
                    [$code, $type, $val, $minSpend, $expiry]
                );
                logAction('Created Coupon Code', "Admin created coupon: $code");
                $message = "Coupon code '$code' created successfully!";
            }
        }
    } elseif ($action === 'toggle_coupon') {
        $code = $_POST['coupon_code'] ?? '';
        $state = (int)($_POST['state'] ?? 0);
        if (!empty($code)) {
            dbExecute("UPDATE coupons SET is_active = ? WHERE code = ?", [$state, $code]);
            logAction('Toggled Coupon', "Admin toggled coupon status for: $code to $state");
            $message = "Coupon status updated.";
        }
    } elseif ($action === 'delete_coupon') {
        $code = $_POST['coupon_code'] ?? '';
        if (!empty($code)) {
            dbExecute("DELETE FROM coupons WHERE code = ?", [$code]);
            logAction('Deleted Coupon', "Admin deleted coupon: $code");
            $message = "Coupon code deleted successfully.";
        }
    }
}

// Fetch platform configurations
$platformName = getSetting('platform_name', 'Uthenga');
$platformEmail = getSetting('platform_email', 'support@uthenga.co');
$allowVendorReg = (int)getSetting('allow_vendor_registration', 1);

// Fetch coupons
$coupons = uthenga_table_exists('coupons') ? (dbQuery("SELECT * FROM coupons ORDER BY expiry_date DESC") ?: []) : [];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">⚙️ Settings & System Rules</h1>
    <p class="text-muted">Configure platform constants, manage discount coupons, and edit platform access rules.</p>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success">✓ <?= e($message) ?></div><?php endif; ?>
<?php if ($err):     ?><div class="alert alert-error">✕ <?= e($err) ?></div><?php endif; ?>

<div class="grid grid-cols-2 gap-3" style="align-items: start;">
  
  <!-- Left: Core Settings Form -->
  <div class="glass-panel animate-in" style="padding: 1.5rem;">
    <h3 style="font-size: 1.1rem; margin-bottom: 1.5rem;">🌎 Platform Properties</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="update_settings">

      <div class="form-group">
        <label class="form-label">Platform Name</label>
        <input type="text" name="platform_name" class="form-control" value="<?= e($platformName) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Support Email Address</label>
        <input type="email" name="platform_email" class="form-control" value="<?= e($platformEmail) ?>" required>
      </div>

      <div class="form-group" style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
        <label class="form-label" style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
          <input type="checkbox" name="allow_vendor_registration" value="1" <?= $allowVendorReg ? 'checked' : '' ?>>
          Allow Open Vendor Self-Registration
        </label>
        <span class="text-xs text-muted" style="display:block; margin-top:0.25rem;">If disabled, vendor accounts can only be created by administrators from the panel.</span>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%;">Save Platform Settings</button>
    </form>
  </div>

  <!-- Right: Add Coupon Code -->
  <div class="glass-panel animate-in" style="padding: 1.5rem;">
    <h3 style="font-size: 1.1rem; margin-bottom: 1.5rem;">🎟 Create Promo/Coupon Code</h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="create_coupon">

      <div class="form-group">
        <label class="form-label">Promo Code</label>
        <input type="text" name="code" class="form-control" placeholder="e.g. LAKE20" style="text-transform: uppercase;" required>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label class="form-label">Discount Type</label>
          <select name="discount_type" class="form-control">
            <option value="percentage">Percentage (%)</option>
            <option value="fixed">Fixed Amount (MWK)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Value</label>
          <input type="number" name="value" class="form-control" min="1" placeholder="e.g. 10" required>
        </div>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label class="form-label">Min Spend (optional)</label>
          <input type="number" name="min_spend" class="form-control" placeholder="e.g. 5000">
        </div>
        <div class="form-group">
          <label class="form-label">Expiry Date</label>
          <input type="date" name="expiry_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%;">Create Coupon Code</button>
    </form>
  </div>

</div>

<!-- Coupon list panel -->
<div class="glass-panel animate-in" style="padding: 1.5rem; margin-top: 2rem;">
  <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">🏷 Active Platform Coupons</h3>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Promo Code</th>
          <th>Discount Rate</th>
          <th>Min Spend Required</th>
          <th>Expiry Date</th>
          <th>Status</th>
          <th style="text-align: right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($coupons)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--clr-text-muted);">No coupon codes registered.</td></tr>
        <?php else: ?>
          <?php foreach ($coupons as $c): ?>
            <tr>
              <td><strong class="font-mono" style="color:var(--clr-accent); font-size:1.05rem;"><?= e($c['code']) ?></strong></td>
              <td><?= $c['discount_type'] === 'percentage' ? e($c['value']) . '%' : formatMWK((float)$c['value']) ?></td>
              <td><?= $c['min_spend'] ? formatMWK((float)$c['min_spend']) : 'None' ?></td>
              <td class="text-xs text-muted"><?= e($c['expiry_date']) ?></td>
              <td>
                <span class="badge <?= $c['is_active'] ? 'badge-approved' : 'badge-rejected' ?>">
                  <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td style="text-align: right;">
                <div style="display:inline-flex; gap: 0.5rem;">
                  <!-- Toggle active state form -->
                  <form method="POST" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="toggle_coupon">
                    <input type="hidden" name="coupon_code" value="<?= e($c['code']) ?>">
                    <input type="hidden" name="state" value="<?= $c['is_active'] ? 0 : 1 ?>">
                    <button type="submit" class="btn btn-sm btn-secondary"><?= $c['is_active'] ? 'Disable' : 'Enable' ?></button>
                  </form>
                  <!-- Delete form -->
                  <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this coupon code?');">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="delete_coupon">
                    <input type="hidden" name="coupon_code" value="<?= e($c['code']) ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
