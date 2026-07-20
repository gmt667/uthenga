<?php
/**
 * Uthenga - Admin Profile
 */
$pageTitle = 'My Profile';
$activeNav = 'admin-profile';

require_once __DIR__ . '/includes/admin_header.php';

$user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
$profileSuccess = '';
$profileError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin_profile'])) {
    if (!validateCsrf()) {
        $profileError = 'Security token mismatch. Please refresh and try again.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $avatar = trim((string)($_POST['avatar'] ?? ''));

        if ($name === '' || strlen($name) < 2) {
            $profileError = 'Please enter a valid full name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'Please enter a valid official email address.';
        } elseif ($phone === '') {
            $profileError = 'Please enter a phone number.';
        } elseif (!preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
            $profileError = 'Please enter a valid phone number.';
        } else {
            $existingEmail = dbQueryOne(
                'SELECT id FROM users WHERE LOWER(email) = ? AND id <> ? LIMIT 1',
                [$email, $_SESSION['user_id']]
            );
            $existingPhone = dbQueryOne(
                'SELECT id FROM users WHERE phone = ? AND phone IS NOT NULL AND phone <> "" AND id <> ? LIMIT 1',
                [$phone, $_SESSION['user_id']]
            );

            if ($existingEmail) {
                $profileError = 'Another account already uses that email address.';
            } elseif ($existingPhone) {
                $profileError = 'Another account already uses that phone number.';
            } else {
                dbExecute(
                    'UPDATE users SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?',
                    [$name, $email, $phone, $avatar !== '' ? $avatar : ($user['avatar'] ?? null), $_SESSION['user_id']]
                );
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
                $profileSuccess = 'Admin profile updated successfully.';

                try {
                    dbExecute(
                        'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
                        [$_SESSION['user_id'], $name, $_SESSION['user_role'] ?? 'Administrator', 'Admin Profile Updated', 'Administrator updated profile contact information.']
                    );
                } catch (Throwable $e) {
                    // Audit logging should never block profile updates.
                }
            }
        }
    }
}

$permissionRow = dbQueryOne('SELECT permissions FROM admin_permissions WHERE user_id = ?', [$_SESSION['user_id']]);
$permissionMap = [
    'admin_users'    => 'Admin Management',
    'vendor_review'  => 'Vendor Review',
    'listings'       => 'Listings',
    'bookings'       => 'Bookings',
    'support'        => 'Support',
    'reports'        => 'Reports',
    'settings'       => 'Settings',
    'logs'           => 'Audit Logs',
];

$permissions = [];
if ($permissionRow && !empty($permissionRow['permissions'])) {
    $decoded = json_decode((string)$permissionRow['permissions'], true);
    if (is_array($decoded)) {
        $permissions = array_values(array_unique(array_filter($decoded)));
    }
}

$recentActivity = dbQuery(
    'SELECT action, details, created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 6',
    [$_SESSION['user_id']]
);

$accountState = !empty($user['is_approved']) ? 'Active' : 'Inactive';
$roleLabel = $_SESSION['user_role'] ?? 'Administrator';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">My Profile</h1>
    <p class="text-muted">Review your administrator identity, permissions, and recent platform activity.</p>
  </div>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>admin/settings.php" class="btn btn-secondary btn-sm"><?= admin_icon_svg('settings') ?> Settings</a>
    <a href="<?= BASE_URL ?>admin/logs.php" class="btn btn-secondary btn-sm"><?= admin_icon_svg('activity') ?> Audit Logs</a>
  </div>
</div>

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon stat-icon-red"><?= admin_icon_svg('user') ?></div>
    <div><div class="stat-value"><?= e($roleLabel) ?></div><div class="stat-label">Access Level</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-cyan"><?= admin_icon_svg('shield') ?></div>
    <div><div class="stat-value"><?= e($accountState) ?></div><div class="stat-label">Account Status</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('users') ?></div>
    <div><div class="stat-value"><?= number_format(count($permissions)) ?></div><div class="stat-label">Custom Permissions</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-green"><?= admin_icon_svg('calendar') ?></div>
    <div><div class="stat-value"><?= e(substr((string)($user['last_login_at'] ?? 'N/A'), 0, 16)) ?></div><div class="stat-label">Last Login</div></div>
  </div>
</div>

<div class="dashboard-surface-row">
  <section class="glass-panel dashboard-panel dashboard-panel-wide">
    <div class="section-head">
      <div>
        <h3>Account Details</h3>
        <p class="text-xs text-muted">Identity and contact information used across the admin console.</p>
      </div>
    </div>
    <?php if ($profileError): ?>
      <div class="alert alert-error" style="margin-bottom:1rem;">Error: <?= e($profileError) ?></div>
    <?php endif; ?>
    <?php if ($profileSuccess): ?>
      <div class="alert alert-success" style="margin-bottom:1rem;">Success: <?= e($profileSuccess) ?></div>
    <?php endif; ?>

    <form method="POST" action="" style="display:grid;gap:1rem;">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="update_admin_profile" value="1">

      <div class="grid grid-cols-2 gap-3">
        <div class="form-group">
          <label class="form-label" for="name">Full Name</label>
          <input type="text" id="name" name="name" class="form-control" value="<?= e($user['name'] ?? $_SESSION['user_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="email">Official Email</label>
          <input type="email" id="email" name="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" required>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="form-group">
          <label class="form-label" for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+265 999 123 456" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="avatar">Profile Photo URL <span class="text-muted">(optional)</span></label>
          <input type="url" id="avatar" name="avatar" class="form-control" value="<?= e($user['avatar'] ?? '') ?>" placeholder="https://...">
        </div>
      </div>

      <div class="table-responsive">
        <table class="admin-table">
          <tbody>
            <tr><th style="width:220px;">Role</th><td><?= e($roleLabel) ?></td></tr>
            <tr><th>Status</th><td><span class="badge <?= !empty($user['is_approved']) ? 'badge-approved' : 'badge-cancelled' ?>"><?= e($accountState) ?></span></td></tr>
            <tr><th>Joined</th><td><?= e(substr((string)($user['created_at'] ?? ''), 0, 16)) ?></td></tr>
            <tr><th>Must Change Password</th><td><?= !empty($user['must_change_pw']) ? 'Yes' : 'No' ?></td></tr>
          </tbody>
        </table>
      </div>

      <div style="display:flex;justify-content:flex-end;">
        <button type="submit" class="btn btn-primary"><?= admin_icon_svg('settings') ?> Save Profile Changes</button>
      </div>
    </form>
  </section>

  <aside class="glass-panel dashboard-panel dashboard-panel-side">
    <div class="section-head">
      <div>
        <h3>Quick Actions</h3>
        <p class="text-xs text-muted">Your most common admin shortcuts.</p>
      </div>
    </div>
    <div class="quick-actions-stack">
      <a class="btn btn-primary w-full" href="<?= BASE_URL ?>admin/dashboard.php"><?= admin_icon_svg('grid') ?> Dashboard</a>
      <a class="btn btn-secondary w-full" href="<?= BASE_URL ?>admin/reports.php"><?= admin_icon_svg('report') ?> Reports</a>
      <a class="btn btn-secondary w-full" href="<?= BASE_URL ?>admin/settings.php"><?= admin_icon_svg('settings') ?> Settings</a>
      <a class="btn btn-secondary w-full" href="<?= BASE_URL ?>logout.php"><?= admin_icon_svg('logout') ?> Sign Out</a>
    </div>
  </aside>
</div>

<div class="dashboard-surface-row" style="margin-top:1.5rem;">
  <section class="glass-panel dashboard-panel">
    <div class="section-head">
      <div>
        <h3>Custom Permissions</h3>
        <p class="text-xs text-muted">Permissions assigned specifically to this account. If empty, access is inherited from the role.</p>
      </div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
      <?php if (empty($permissions)): ?>
        <span class="text-muted">No custom permissions configured.</span>
      <?php else: ?>
        <?php foreach ($permissions as $key): ?>
          <span class="badge badge-pending" style="text-transform:none;letter-spacing:0;"><?= e($permissionMap[$key] ?? $key) ?></span>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <aside class="glass-panel dashboard-panel dashboard-panel-side">
    <div class="section-head">
      <div>
        <h3>Recent Activity</h3>
        <p class="text-xs text-muted">Your latest recorded admin actions.</p>
      </div>
    </div>
    <div class="simple-list">
      <?php if (empty($recentActivity)): ?>
        <div class="text-muted text-xs">No activity recorded yet.</div>
      <?php else: ?>
        <?php foreach ($recentActivity as $item): ?>
          <div class="simple-list-item simple-list-item-stack">
            <div class="simple-list-top">
              <strong><?= e($item['action']) ?></strong>
              <span class="text-xs text-muted"><?= e(substr($item['created_at'], 0, 16)) ?></span>
            </div>
            <div class="text-xs text-muted"><?= e($item['details']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
