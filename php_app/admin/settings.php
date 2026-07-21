<?php
/**
 * Uthenga - Admin Platform Settings & Coupons
 */
$pageTitle = 'Platform Settings';
$activeNav = 'admin-settings';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

$message = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_settings') {
        $pName = trim($_POST['platform_name'] ?? 'Uthenga');
        $pEmail = trim($_POST['platform_email'] ?? 'support@uthenga.co');
        $vReg = isset($_POST['allow_vendor_registration']) ? '1' : '0';

        if ($pName === '' || $pEmail === '') {
            $err = 'Platform name and email are required.';
        } else {
            setSetting('platform_name', $pName, $_SESSION['user_id'] ?? null);
            setSetting('platform_email', $pEmail, $_SESSION['user_id'] ?? null);
            setSetting('allow_vendor_registration', $vReg, $_SESSION['user_id'] ?? null);
            logAction('Updated Platform Settings', 'Admin updated site configurations');
            $message = 'Platform settings updated successfully.';
        }
    } elseif ($action === 'create_coupon') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $type = $_POST['discount_type'] ?? 'percentage';
        $val = (float) ($_POST['value'] ?? 0);
        $minSpend = !empty($_POST['min_spend']) ? (float) $_POST['min_spend'] : null;
        $expiry = $_POST['expiry_date'] ?? '';

        if ($code === '' || $val <= 0 || $expiry === '') {
            $err = 'Coupon code, value, and expiry date are required.';
        } elseif (!uthenga_table_exists('coupons')) {
            $err = 'Coupons table is not yet available. Please run the database migration.';
        } else {
            $exists = dbCount('SELECT COUNT(*) FROM coupons WHERE code = ?', [$code]);
            if ($exists > 0) {
                $err = "Coupon code '$code' already exists.";
            } else {
                dbExecute(
                    'INSERT INTO coupons (code, discount_type, value, min_spend, expiry_date, is_active) VALUES (?, ?, ?, ?, ?, 1)',
                    [$code, $type, $val, $minSpend, $expiry]
                );
                logAction('Created Coupon Code', "Admin created coupon: $code");
                $message = "Coupon code '$code' created successfully.";
            }
        }
    } elseif ($action === 'toggle_coupon') {
        $code = $_POST['coupon_code'] ?? '';
        $state = (int) ($_POST['state'] ?? 0);
        if ($code !== '') {
            dbExecute('UPDATE coupons SET is_active = ? WHERE code = ?', [$state, $code]);
            logAction('Toggled Coupon', "Admin toggled coupon status for: $code to $state");
            $message = 'Coupon status updated.';
        }
    } elseif ($action === 'delete_coupon') {
        $code = $_POST['coupon_code'] ?? '';
        if ($code !== '') {
            dbExecute('DELETE FROM coupons WHERE code = ?', [$code]);
            logAction('Deleted Coupon', "Admin deleted coupon: $code");
            $message = 'Coupon code deleted successfully.';
        }
    }
}

$platformName = getSetting('platform_name', 'Uthenga');
$platformEmail = getSetting('platform_email', 'support@uthenga.co');
$allowVendorReg = (int) getSetting('allow_vendor_registration', 1);
$coupons = uthenga_table_exists('coupons') ? (dbQuery('SELECT * FROM coupons ORDER BY expiry_date DESC') ?: []) : [];

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
  .settings-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
    align-items:start;
  }
  .settings-panel {
    padding:1.25rem;
    min-width:0;
  }
  .settings-panel h3 {
    display:flex;
    align-items:center;
    gap:.5rem;
    font-size:1.05rem;
    margin:0 0 1rem;
  }
  .settings-note {
    border:1px solid var(--clr-border);
    background:var(--clr-surface2);
    border-radius:14px;
    padding:1rem;
    color:var(--clr-text-soft);
  }
  .coupon-actions {
    display:flex;
    gap:.5rem;
    flex-wrap:wrap;
    justify-content:flex-end;
  }
  @media (max-width: 960px) {
    .settings-grid { grid-template-columns:1fr; }
  }
  @media (max-width: 640px) {
    .settings-panel { padding:1rem; }
    .coupon-actions { justify-content:flex-start; }
  }
</style>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= admin_icon_svg('settings') ?><span>Settings & System Rules</span></h1>
    <p class="text-muted">Configure platform details, vendor access, and discount coupon controls.</p>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success">Success: <?= e($message) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error">Error: <?= e($err) ?></div><?php endif; ?>

<div class="settings-grid">
  <div class="glass-panel settings-panel">
    <h3><?= admin_icon_svg('home') ?><span>Platform Properties</span></h3>
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

      <div class="form-group">
        <label class="form-label" style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
          <input type="checkbox" name="allow_vendor_registration" value="1" <?= $allowVendorReg ? 'checked' : '' ?>>
          Allow open vendor self-registration
        </label>
        <div class="settings-note" style="margin-top:.75rem;">
          When disabled, vendor accounts can only be created by administrators from the panel.
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;"><?= admin_icon_svg('settings') ?> Save Platform Settings</button>
    </form>
  </div>

  <div class="glass-panel settings-panel">
    <h3><?= admin_icon_svg('plus') ?><span>Create Promo / Coupon Code</span></h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="action" value="create_coupon">

      <div class="form-group">
        <label class="form-label">Promo Code</label>
        <input type="text" name="code" class="form-control" placeholder="e.g. LAKE20" style="text-transform:uppercase;" required>
      </div>

      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
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

      <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;">
        <div class="form-group">
          <label class="form-label">Min Spend (optional)</label>
          <input type="number" name="min_spend" class="form-control" placeholder="e.g. 5000">
        </div>
        <div class="form-group">
          <label class="form-label">Expiry Date</label>
          <input type="date" name="expiry_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;"><?= admin_icon_svg('plus') ?> Create Coupon Code</button>
    </form>
  </div>
</div>

<div class="glass-panel settings-panel" style="margin-top:1.25rem;">
  <h3><?= admin_icon_svg('grid') ?><span>Active Platform Coupons</span></h3>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Promo Code</th>
          <th>Discount Rate</th>
          <th>Min Spend</th>
          <th>Expiry Date</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($coupons)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--clr-text-muted);">No coupon codes registered.</td></tr>
        <?php else: ?>
          <?php foreach ($coupons as $coupon): ?>
            <tr>
              <td><strong style="color:var(--clr-accent);font-size:1.05rem;font-family:monospace;"><?= e($coupon['code']) ?></strong></td>
              <td><?= $coupon['discount_type'] === 'percentage' ? e($coupon['value']) . '%' : formatMWK((float) $coupon['value']) ?></td>
              <td><?= $coupon['min_spend'] ? formatMWK((float) $coupon['min_spend']) : 'None' ?></td>
              <td class="text-xs text-muted"><?= e($coupon['expiry_date']) ?></td>
              <td>
                <span class="badge <?= $coupon['is_active'] ? 'badge-approved' : 'badge-rejected' ?>">
                  <?= $coupon['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td style="text-align:right;">
                <div class="coupon-actions">
                  <form method="POST" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="toggle_coupon">
                    <input type="hidden" name="coupon_code" value="<?= e($coupon['code']) ?>">
                    <input type="hidden" name="state" value="<?= $coupon['is_active'] ? 0 : 1 ?>">
                    <button type="submit" class="btn btn-sm btn-secondary"><?= admin_icon_svg('toggle') ?> <?= $coupon['is_active'] ? 'Disable' : 'Enable' ?></button>
                  </form>
                  <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this coupon code?');">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="delete_coupon">
                    <input type="hidden" name="coupon_code" value="<?= e($coupon['code']) ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><?= admin_icon_svg('trash') ?> Delete</button>
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

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
