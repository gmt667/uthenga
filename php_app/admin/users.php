<?php
/**
 * Uthenga - Admin Management
 */
$pageTitle = 'Admin Management';
$activeNav = 'admin-users';

require_once __DIR__ . '/includes/admin_header.php';

if (!function_exists('adminManagementEnsureSchema')) {
    function adminManagementEnsureSchema(): array {
        $hasPhone = true;
        try {
            dbQueryOne('SELECT phone FROM users LIMIT 1');
        } catch (Throwable $e) {
            $hasPhone = false;
            try {
                dbExecute('ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER email');
                $hasPhone = true;
            } catch (Throwable $ignored) {
                $hasPhone = false;
            }
        }

        $permissionsReady = true;
        try {
            dbExecute('
                CREATE TABLE IF NOT EXISTS admin_permissions (
                    user_id VARCHAR(30) NOT NULL PRIMARY KEY,
                    permissions JSON NOT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB
            ');
        } catch (Throwable $e) {
            $permissionsReady = false;
        }

        return [$hasPhone, $permissionsReady];
    }
}

if (!function_exists('adminManagementPermissionSet')) {
    function adminManagementPermissionSet(): array {
        return [
            'admin_users' => 'Admin Management',
            'vendor_review' => 'Vendor Review',
            'listings' => 'Listings',
            'bookings' => 'Bookings',
            'support' => 'Support',
            'reports' => 'Reports',
            'settings' => 'Settings',
            'logs' => 'Audit Logs',
        ];
    }
}

if (!function_exists('adminManagementLoadPermissions')) {
    function adminManagementLoadPermissions(string $userId): array {
        try {
            $row = dbQueryOne('SELECT permissions FROM admin_permissions WHERE user_id = ?', [$userId]);
            if (!$row || empty($row['permissions'])) {
                return [];
            }
            $decoded = json_decode((string)$row['permissions'], true);
            return is_array($decoded) ? array_values(array_unique(array_filter($decoded))) : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('adminManagementSavePermissions')) {
    function adminManagementSavePermissions(string $userId, array $permissions): void {
        $clean = array_values(array_intersect(array_keys(adminManagementPermissionSet()), $permissions));
        dbExecute(
            'INSERT INTO admin_permissions (user_id, permissions) VALUES (?, ?) ON DUPLICATE KEY UPDATE permissions = VALUES(permissions), updated_at = CURRENT_TIMESTAMP',
            [$userId, json_encode($clean)]
        );
    }
}

if (!function_exists('adminManagementGeneratePassword')) {
    function adminManagementGeneratePassword(int $length = 12): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#';
        $max = strlen($alphabet) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }
        return $password;
    }
}

[$hasPhoneColumn, $permissionsReady] = adminManagementEnsureSchema();

$flashSuccess = '';
$flashError = '';
$permissionKeys = array_keys(adminManagementPermissionSet());
$permissionLabels = adminManagementPermissionSet();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = $_POST['admin_action'] ?? '';

    if (!hasRole(ROLE_SUPER_ADMIN)) {
        $flashError = 'Only the Super Administrator can manage admin accounts.';
    } else {
        try {
            if ($action === 'bulk') {
                $bulkChoice = $_POST['bulk_choice'] ?? '';
                $rawIds = trim($_POST['bulk_ids'] ?? '');
                $ids = array_values(array_filter(array_map('trim', explode(',', $rawIds))));
                $ids = array_values(array_diff($ids, [$_SESSION['user_id']]));

                if (empty($ids)) {
                    throw new RuntimeException('Select at least one admin account.');
                }
                if (!in_array($bulkChoice, ['activate', 'deactivate', 'delete'], true)) {
                    throw new RuntimeException('Choose a valid bulk action.');
                }

                if ($bulkChoice === 'delete') {
                    $superAdminCount = dbCount("SELECT COUNT(*) FROM users WHERE role = 'Super Administrator'");
                    foreach ($ids as $id) {
                        $target = dbQueryOne('SELECT id, role, name, email FROM users WHERE id = ?', [$id]);
                        if (!$target) {
                            continue;
                        }
                        if ($target['role'] === ROLE_SUPER_ADMIN && $superAdminCount <= 1) {
                            throw new RuntimeException('At least one Super Administrator must remain active.');
                        }
                        dbExecute('DELETE FROM users WHERE id = ?', [$id]);
                        logAction('Deleted Admin Account', "Bulk deleted admin account: {$target['name']} ({$target['email']})");
                    }
                    $flashSuccess = 'Selected admin accounts deleted.';
                } else {
                    $nextStatus = $bulkChoice === 'activate' ? 1 : 0;
                    foreach ($ids as $id) {
                        $target = dbQueryOne('SELECT id, name, role FROM users WHERE id = ?', [$id]);
                        if (!$target) {
                            continue;
                        }
                        dbExecute('UPDATE users SET is_approved = ? WHERE id = ?', [$nextStatus, $id]);
                        logAction(
                            $nextStatus ? 'Activated User' : 'Suspended User',
                            "Bulk updated {$target['name']} ({$target['role']}) to " . ($nextStatus ? 'Active' : 'Inactive')
                        );
                    }
                    $flashSuccess = $nextStatus ? 'Selected admin accounts activated.' : 'Selected admin accounts deactivated.';
                }
            } elseif ($action === 'create_admin') {
                $name = trim($_POST['admin_name'] ?? '');
                $email = trim($_POST['admin_email'] ?? '');
                $phone = trim($_POST['admin_phone'] ?? '');
                $role = $_POST['admin_role'] ?? ROLE_ADMIN;
                $status = ($_POST['admin_status'] ?? 'active') === 'active' ? 1 : 0;
                $password = trim($_POST['admin_password'] ?? '');
                $permissions = $_POST['permissions'] ?? [];
                $permissions = is_array($permissions) ? $permissions : [];

                if ($name === '' || $email === '') {
                    throw new RuntimeException('Full name and email address are required.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Please enter a valid email address.');
                }
                if (!in_array($role, [ROLE_ADMIN, ROLE_SUPER_ADMIN], true)) {
                    throw new RuntimeException('Invalid role selected.');
                }
                if ($password === '') {
                    $password = adminManagementGeneratePassword();
                } elseif (strlen($password) < MIN_PASSWORD_LEN) {
                    throw new RuntimeException('Temporary password must be at least ' . MIN_PASSWORD_LEN . ' characters.');
                }
                $exists = dbQueryOne('SELECT id FROM users WHERE email = ?', [strtolower($email)]);
                if ($exists) {
                    throw new RuntimeException('That email address is already in use.');
                }

                $userId = generateId('A');
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                if ($hasPhoneColumn) {
                    dbExecute(
                        'INSERT INTO users (id, name, email, phone, password_hash, role, is_approved, must_change_pw) VALUES (?,?,?,?,?,?,?,1)',
                        [$userId, $name, strtolower($email), $phone !== '' ? $phone : null, $hash, $role, $status]
                    );
                } else {
                    dbExecute(
                        'INSERT INTO users (id, name, email, password_hash, role, is_approved, must_change_pw) VALUES (?,?,?,?,?, ?,1)',
                        [$userId, $name, strtolower($email), $hash, $role, $status]
                    );
                }

                if ($permissionsReady) {
                    adminManagementSavePermissions($userId, $permissions);
                }

                logAction('Created Admin Account', "Super Admin created $role account: $name ($email)");
                $flashSuccess = "Admin account created successfully. Temporary password: {$password}";
            } elseif ($action === 'update_admin') {
                $userId = trim($_POST['user_id'] ?? '');
                $name = trim($_POST['admin_name'] ?? '');
                $email = trim($_POST['admin_email'] ?? '');
                $phone = trim($_POST['admin_phone'] ?? '');
                $role = $_POST['admin_role'] ?? ROLE_ADMIN;
                $status = ($_POST['admin_status'] ?? 'active') === 'active' ? 1 : 0;
                $permissions = $_POST['permissions'] ?? [];
                $permissions = is_array($permissions) ? $permissions : [];

                if ($userId === '') {
                    throw new RuntimeException('Missing admin account ID.');
                }
                if ($userId === $_SESSION['user_id']) {
                    throw new RuntimeException('You cannot edit your own access here.');
                }
                if ($name === '' || $email === '') {
                    throw new RuntimeException('Full name and email address are required.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Please enter a valid email address.');
                }
                if (!in_array($role, [ROLE_ADMIN, ROLE_SUPER_ADMIN], true)) {
                    throw new RuntimeException('Invalid role selected.');
                }

                $target = dbQueryOne('SELECT id FROM users WHERE id = ?', [$userId]);
                if (!$target) {
                    throw new RuntimeException('Admin account not found.');
                }
                $conflict = dbQueryOne('SELECT id FROM users WHERE email = ? AND id <> ?', [strtolower($email), $userId]);
                if ($conflict) {
                    throw new RuntimeException('That email address is already assigned to another account.');
                }

                if ($hasPhoneColumn) {
                    dbExecute(
                        'UPDATE users SET name = ?, email = ?, phone = ?, role = ?, is_approved = ? WHERE id = ?',
                        [$name, strtolower($email), $phone !== '' ? $phone : null, $role, $status, $userId]
                    );
                } else {
                    dbExecute(
                        'UPDATE users SET name = ?, email = ?, role = ?, is_approved = ? WHERE id = ?',
                        [$name, strtolower($email), $role, $status, $userId]
                    );
                }

                if ($permissionsReady) {
                    adminManagementSavePermissions($userId, $permissions);
                }

                logAction('Updated Admin Account', "Super Admin updated account: $name ($email)");
                $flashSuccess = 'Admin details updated successfully.';
            } elseif ($action === 'reset_password') {
                $userId = trim($_POST['user_id'] ?? '');
                if ($userId === '' || $userId === $_SESSION['user_id']) {
                    throw new RuntimeException('Invalid target account.');
                }
                $target = dbQueryOne('SELECT id, name, email FROM users WHERE id = ?', [$userId]);
                if (!$target) {
                    throw new RuntimeException('Admin account not found.');
                }
                $tempPassword = adminManagementGeneratePassword();
                $hash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                dbExecute('UPDATE users SET password_hash = ?, must_change_pw = 1 WHERE id = ?', [$hash, $userId]);
                logAction('Reset User Password', "Super Admin reset password for user: {$target['email']} ({$target['id']})");
                $flashSuccess = "Password reset complete for {$target['name']}. Temporary password: {$tempPassword}";
            } elseif ($action === 'toggle_status') {
                $userId = trim($_POST['user_id'] ?? '');
                if ($userId === '' || $userId === $_SESSION['user_id']) {
                    throw new RuntimeException('You cannot change your own account status.');
                }
                $target = dbQueryOne('SELECT id, name, is_approved FROM users WHERE id = ?', [$userId]);
                if (!$target) {
                    throw new RuntimeException('Admin account not found.');
                }
                $newStatus = (int)!((bool)$target['is_approved']);
                dbExecute('UPDATE users SET is_approved = ? WHERE id = ?', [$newStatus, $userId]);
                logAction($newStatus ? 'Activated User' : 'Suspended User', "Super Admin toggled status for {$target['name']} ($userId)");
                $flashSuccess = $newStatus ? 'Admin account activated.' : 'Admin account suspended.';
            } elseif ($action === 'delete_admin') {
                $userId = trim($_POST['user_id'] ?? '');
                if ($userId === '' || $userId === $_SESSION['user_id']) {
                    throw new RuntimeException('You cannot delete your own account.');
                }
                $target = dbQueryOne('SELECT id, name, email, role FROM users WHERE id = ?', [$userId]);
                if (!$target) {
                    throw new RuntimeException('Admin account not found.');
                }
                if ($target['role'] === ROLE_SUPER_ADMIN) {
                    $superAdminCount = dbCount("SELECT COUNT(*) FROM users WHERE role = 'Super Administrator'");
                    if ($superAdminCount <= 1) {
                        throw new RuntimeException('At least one Super Administrator must remain active.');
                    }
                }
                dbExecute('DELETE FROM users WHERE id = ?', [$userId]);
                logAction('Deleted Admin Account', "Super Admin deleted account: {$target['name']} ({$target['email']})");
                $flashSuccess = 'Admin account deleted successfully.';
            }
        } catch (Throwable $e) {
            $flashError = $e->getMessage();
        }
    }
}

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$roleFilter = $_GET['role'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$where = ["role IN ('Super Administrator','Administrator')"];
$params = [];
if ($search !== '') {
    if ($hasPhoneColumn) {
        $where[] = '(name LIKE ? OR email LIKE ? OR COALESCE(phone, "") LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    } else {
        $where[] = '(name LIKE ? OR email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}
if ($roleFilter !== 'all') {
    $where[] = 'role = ?';
    $params[] = $roleFilter;
}
if ($statusFilter === 'active') {
    $where[] = 'is_approved = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'is_approved = 0';
}

$whereSql = implode(' AND ', $where);
$totalCount = dbCount("SELECT COUNT(*) FROM users WHERE $whereSql", $params);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$offset = ($page - 1) * $perPage;
$admins = dbQuery("SELECT * FROM users WHERE $whereSql ORDER BY role DESC, created_at DESC LIMIT $perPage OFFSET $offset", $params);

$adminIds = array_column($admins, 'id');
$adminPermissions = [];
if ($permissionsReady && !empty($adminIds)) {
    $placeholders = implode(',', array_fill(0, count($adminIds), '?'));
    foreach (dbQuery("SELECT user_id, permissions FROM admin_permissions WHERE user_id IN ($placeholders)", $adminIds) as $row) {
        $decoded = json_decode((string)$row['permissions'], true);
        $adminPermissions[$row['user_id']] = is_array($decoded) ? $decoded : [];
    }
}

if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'xls', 'pdf'], true)) {
    $exportRows = dbQuery("SELECT * FROM users WHERE $whereSql ORDER BY role DESC, created_at DESC", $params);
    $exportHeader = ['ID', 'Name', 'Email', 'Phone', 'Role', 'Status', 'Created At', 'Permissions'];

    if ($_GET['export'] === 'pdf') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Admin Management Export</title>
          <style>
            body { font-family: Arial, sans-serif; padding: 24px; color: #111827; }
            h1 { margin: 0 0 8px; }
            p { margin: 0 0 18px; color: #4b5563; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #d1d5db; padding: 8px 10px; text-align: left; font-size: 12px; }
            th { background: #f3f4f6; }
          </style>
        </head>
        <body>
          <h1>Admin Management Export</h1>
          <p>Generated on <?= e(date('Y-m-d H:i')) ?></p>
          <table>
            <thead><tr><?php foreach ($exportHeader as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($exportRows as $row): ?>
                <?php $rowPermissions = $permissionsReady ? adminManagementLoadPermissions((string)$row['id']) : []; ?>
                <tr>
                  <td><?= e($row['id']) ?></td>
                  <td><?= e($row['name']) ?></td>
                  <td><?= e($row['email']) ?></td>
                  <td><?= e($hasPhoneColumn ? ($row['phone'] ?? '') : '') ?></td>
                  <td><?= e($row['role']) ?></td>
                  <td><?= e($row['is_approved'] ? 'Active' : 'Inactive') ?></td>
                  <td><?= e($row['created_at'] ?? '') ?></td>
                  <td><?= e(implode('|', $rowPermissions)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <script>window.print();</script>
        </body>
        </html>
        <?php
        exit;
    }

    $isExcel = $_GET['export'] === 'xls';
    header('Content-Type: ' . ($isExcel ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=UTF-8');
    header('Content-Disposition: attachment; filename=admin-management-export.' . ($isExcel ? 'xls' : 'csv'));
    $out = fopen('php://output', 'w');
    fputcsv($out, $exportHeader, $isExcel ? "\t" : ',');
    foreach ($exportRows as $row) {
        $rowPermissions = $permissionsReady ? adminManagementLoadPermissions((string)$row['id']) : [];
        fputcsv($out, [
            $row['id'],
            $row['name'],
            $row['email'],
            $hasPhoneColumn ? ($row['phone'] ?? '') : '',
            $row['role'],
            $row['is_approved'] ? 'Active' : 'Inactive',
            $row['created_at'] ?? '',
            implode('|', $rowPermissions),
        ], $isExcel ? "\t" : ',');
    }
    fclose($out);
    exit;
}

$totals = [
    'all' => dbCount("SELECT COUNT(*) FROM users WHERE role IN ('Super Administrator','Administrator')"),
    'super' => dbCount("SELECT COUNT(*) FROM users WHERE role = 'Super Administrator'"),
    'active' => dbCount("SELECT COUNT(*) FROM users WHERE role IN ('Super Administrator','Administrator') AND is_approved = 1"),
    'inactive' => dbCount("SELECT COUNT(*) FROM users WHERE role IN ('Super Administrator','Administrator') AND is_approved = 0"),
];

$editSeed = [];
if (!empty($admins)) {
    $firstAdmin = $admins[0];
    $editSeed = [
        'user_id' => $firstAdmin['id'],
        'name' => $firstAdmin['name'],
        'email' => $firstAdmin['email'],
        'phone' => $hasPhoneColumn ? ($firstAdmin['phone'] ?? '') : '',
        'role' => $firstAdmin['role'],
        'status' => $firstAdmin['is_approved'] ? 'active' : 'inactive',
        'permissions' => $adminPermissions[$firstAdmin['id']] ?? [],
    ];
}
?>

<div class="page-header" id="admin-management">
  <div>
    <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('users') ?><span>Admin Management</span></h1>
    <p class="text-muted">Create, review, edit, suspend, reset, and remove administrator accounts from a single secure workspace.</p>
  </div>
  <div class="dashboard-head-meta">
    <button class="btn btn-primary" type="button" onclick="openModal('create-admin-modal')" id="create-admin-btn">
      <?= admin_icon_svg('plus') ?>
      Create Admin
    </button>
  </div>
</div>

<?php if ($flashSuccess): ?><div class="alert alert-success">Success: <?= e($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-error">Error: <?= e($flashError) ?></div><?php endif; ?>

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card"><div class="stat-icon stat-icon-purple"><?= admin_icon_svg('shield') ?></div><div><div class="stat-value"><?= number_format($totals['all']) ?></div><div class="stat-label">Administrators</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-blue"><?= admin_icon_svg('shield') ?></div><div><div class="stat-value"><?= number_format($totals['super']) ?></div><div class="stat-label">Super Admins</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-green"><?= admin_icon_svg('toggle') ?></div><div><div class="stat-value"><?= number_format($totals['active']) ?></div><div class="stat-label">Active</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('lock') ?></div><div><div class="stat-value"><?= number_format($totals['inactive']) ?></div><div class="stat-label">Inactive</div></div></div>
</div>

<form method="GET" class="glass-panel table-toolbar" style="padding:1rem;margin-bottom:1.5rem;">
  <input type="text" name="q" placeholder="Search admins by name, email, or phone" class="form-control" style="min-width:240px;flex:1;" value="<?= e($search) ?>">
  <select name="role" class="form-control" style="min-width:180px;">
    <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
    <option value="<?= ROLE_ADMIN ?>" <?= $roleFilter === ROLE_ADMIN ? 'selected' : '' ?>>Administrator</option>
    <option value="<?= ROLE_SUPER_ADMIN ?>" <?= $roleFilter === ROLE_SUPER_ADMIN ? 'selected' : '' ?>>Super Administrator</option>
  </select>
  <select name="status" class="form-control" style="min-width:180px;">
    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><?= admin_icon_svg('search') ?> Filter</button>
  <a href="users.php" class="btn btn-secondary btn-sm">Clear</a>
</form>

<div class="glass-panel" style="padding:1rem;margin-bottom:1rem;">
  <form method="POST" id="bulk-admin-form" class="table-toolbar" style="margin:0;">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="admin_action" value="bulk">
    <input type="hidden" name="bulk_ids" id="bulk-admin-ids" value="">
    <select name="bulk_choice" class="form-control" style="min-width:180px;">
      <option value="">Bulk Actions</option>
      <option value="activate">Activate Selected</option>
      <option value="deactivate">Deactivate Selected</option>
      <option value="delete">Delete Selected</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
    <a href="?<?= http_build_query(['q' => $search, 'role' => $roleFilter, 'status' => $statusFilter, 'export' => 'csv']) ?>" class="btn btn-secondary btn-sm"><?= admin_icon_svg('download') ?> CSV</a>
    <a href="?<?= http_build_query(['q' => $search, 'role' => $roleFilter, 'status' => $statusFilter, 'export' => 'xls']) ?>" class="btn btn-secondary btn-sm"><?= admin_icon_svg('download') ?> Excel</a>
    <a href="?<?= http_build_query(['q' => $search, 'role' => $roleFilter, 'status' => $statusFilter, 'export' => 'pdf']) ?>" class="btn btn-secondary btn-sm" target="_blank"><?= admin_icon_svg('download') ?> PDF</a>
  </form>
</div>

<div class="glass-panel" style="padding:1rem;">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th><input type="checkbox" id="admin-select-all" aria-label="Select all admins"></th>
          <th data-sort>Name</th>
          <th data-sort>Contact</th>
          <th data-sort>Role</th>
          <th>Permissions</th>
          <th data-sort>Status</th>
          <th data-sort>Updated</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($admins)): ?>
          <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--clr-text-muted);">No admin accounts found.</td></tr>
        <?php else: ?>
          <?php foreach ($admins as $admin): ?>
            <?php
              $perms = $adminPermissions[$admin['id']] ?? [];
              $permLabels = array_values(array_map(function($key) use ($permissionLabels) { return $permissionLabels[$key] ?? $key; }, $perms));
              $statusText = $admin['is_approved'] ? 'Active' : 'Inactive';
              $rowData = [
                  'user_id' => $admin['id'],
                  'name' => $admin['name'],
                  'email' => $admin['email'],
                  'phone' => $hasPhoneColumn ? ($admin['phone'] ?? '') : '',
                  'role' => $admin['role'],
                  'status' => $admin['is_approved'] ? 'active' : 'inactive',
                  'permissions' => $perms,
            ];
            ?>
            <tr>
              <td><input type="checkbox" class="admin-select-row" value="<?= e($admin['id']) ?>" aria-label="Select <?= e($admin['name']) ?>"></td>
              <td>
                <div style="font-weight:700;"><?= e($admin['name']) ?></div>
                <div class="text-xs text-muted"><?= e($admin['id']) ?></div>
              </td>
              <td>
                <div class="text-xs"><?= e($admin['email']) ?></div>
                <?php if ($hasPhoneColumn): ?><div class="text-xs text-muted"><?= e($admin['phone'] ?? 'No phone') ?></div><?php endif; ?>
              </td>
              <td><span class="role-badge role-admin"><?= e($admin['role']) ?></span></td>
              <td>
                <?php if (empty($permLabels)): ?>
                  <span class="text-xs text-muted">Inherited from role</span>
                <?php else: ?>
                  <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                    <?php foreach ($permLabels as $label): ?>
                      <span class="badge badge-pending" style="text-transform:none;letter-spacing:0;"><?= e($label) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $admin['is_approved'] ? 'badge-approved' : 'badge-rejected' ?>"><?= e($statusText) ?></span></td>
              <td class="text-xs text-muted"><?= e(substr($admin['updated_at'] ?? $admin['created_at'], 0, 16)) ?></td>
              <td style="text-align:right;">
                <div style="display:inline-flex;gap:0.45rem;justify-content:flex-end;flex-wrap:wrap;">
                  <button
                    type="button"
                    class="btn btn-sm btn-secondary"
                    onclick="openAdminEditModal(this)"
                    data-admin='<?= e(json_encode($rowData, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG)) ?>'
                  >Edit</button>

                  <form method="POST" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="admin_action" value="toggle_status">
                    <input type="hidden" name="user_id" value="<?= e($admin['id']) ?>">
                    <button type="submit" class="btn btn-sm <?= $admin['is_approved'] ? 'btn-danger' : 'btn-secondary' ?>">
                      <?= $admin['is_approved'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </form>

                  <form method="POST" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="admin_action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?= e($admin['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Reset this password and force a change on next login?');">Reset Password</button>
                  </form>

                  <?php if ($admin['id'] !== $_SESSION['user_id']): ?>
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="admin_action" value="delete_admin">
                      <input type="hidden" name="user_id" value="<?= e($admin['id']) ?>">
                      <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this admin account? This cannot be undone.');">Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination" style="margin-top:1.5rem;">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&role=<?= urlencode($roleFilter) ?>&status=<?= urlencode($statusFilter) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<p class="text-xs text-muted" style="text-align:center;margin-top:1rem;">Showing <?= count($admins) ?> of <?= number_format($totalCount) ?> admin accounts</p>

<?php if (hasRole(ROLE_SUPER_ADMIN)): ?>
<div class="modal-overlay" id="create-admin-modal" aria-hidden="true">
  <div class="modal" style="max-width:720px;">
    <div class="modal-header">
      <h3>Create Admin Account</h3>
      <button class="modal-close" type="button" onclick="closeModal('create-admin-modal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="admin_action" value="create_admin">
      <div class="modal-body">
        <div class="grid grid-cols-2 gap-2">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="admin_name" class="form-control" required placeholder="Administrator name">
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="admin_email" class="form-control" required placeholder="admin@example.com">
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number <?= $hasPhoneColumn ? '(Optional)' : '(Not available on this schema)' ?></label>
            <input type="text" name="admin_phone" class="form-control" <?= $hasPhoneColumn ? '' : 'disabled' ?> placeholder="+265 ...">
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="admin_role" class="form-control">
              <option value="<?= ROLE_ADMIN ?>">Administrator</option>
              <option value="<?= ROLE_SUPER_ADMIN ?>">Super Administrator</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Temporary Password</label>
            <input type="text" name="admin_password" class="form-control" placeholder="Leave blank to auto-generate">
          </div>
          <div class="form-group">
            <label class="form-label">Account Status</label>
            <select name="admin_status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-top:1rem;">
          <label class="form-label">Permissions</label>
          <div class="grid grid-cols-2 gap-2">
            <?php foreach ($permissionLabels as $key => $label): ?>
              <label class="glass-panel" style="padding:0.75rem 0.9rem;display:flex;align-items:center;gap:0.6rem;">
                <input type="checkbox" name="permissions[]" value="<?= e($key) ?>" checked>
                <span><?= e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="alert alert-warning" style="margin-top:1rem;">The account will be forced to change its password on first login.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('create-admin-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Account</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="edit-admin-modal" aria-hidden="true">
  <div class="modal" style="max-width:720px;">
    <div class="modal-header">
      <h3>Edit Admin Account</h3>
      <button class="modal-close" type="button" onclick="closeModal('edit-admin-modal')">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="admin_action" value="update_admin">
      <input type="hidden" name="user_id" id="edit-admin-id" value="">
      <div class="modal-body">
        <div class="grid grid-cols-2 gap-2">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="admin_name" id="edit-admin-name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="admin_email" id="edit-admin-email" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number <?= $hasPhoneColumn ? '(Optional)' : '(Not available on this schema)' ?></label>
            <input type="text" name="admin_phone" id="edit-admin-phone" class="form-control" <?= $hasPhoneColumn ? '' : 'disabled' ?>>
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="admin_role" id="edit-admin-role" class="form-control">
              <option value="<?= ROLE_ADMIN ?>">Administrator</option>
              <option value="<?= ROLE_SUPER_ADMIN ?>">Super Administrator</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Account Status</label>
            <select name="admin_status" id="edit-admin-status" class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-top:1rem;">
          <label class="form-label">Permissions</label>
          <div class="grid grid-cols-2 gap-2">
            <?php foreach ($permissionLabels as $key => $label): ?>
              <label class="glass-panel" style="padding:0.75rem 0.9rem;display:flex;align-items:center;gap:0.6rem;">
                <input type="checkbox" name="permissions[]" value="<?= e($key) ?>" data-admin-permission="<?= e($key) ?>">
                <span><?= e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('edit-admin-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
const bulkSelectAll = document.getElementById('admin-select-all');
const bulkRows = Array.from(document.querySelectorAll('.admin-select-row'));
const bulkIdsField = document.getElementById('bulk-admin-ids');
const bulkForm = document.getElementById('bulk-admin-form');

if (bulkSelectAll) {
  bulkSelectAll.addEventListener('change', () => {
    bulkRows.forEach((row) => { row.checked = bulkSelectAll.checked; });
  });
}

if (bulkForm && bulkIdsField) {
  bulkForm.addEventListener('submit', (event) => {
    const selected = bulkRows.filter((row) => row.checked).map((row) => row.value);
    if (!selected.length) {
      event.preventDefault();
      alert('Select at least one admin account.');
      return;
    }
    const choice = bulkForm.querySelector('[name="bulk_choice"]')?.value || '';
    if (!choice) {
      event.preventDefault();
      alert('Choose a bulk action first.');
      return;
    }
    if (choice === 'delete' && !confirm('Delete all selected admin accounts? This cannot be undone.')) {
      event.preventDefault();
      return;
    }
    bulkIdsField.value = selected.join(',');
  });
}

function openAdminEditModal(button) {
  try {
    const data = JSON.parse(button.dataset.admin || '{}');
    document.getElementById('edit-admin-id').value = data.user_id || '';
    document.getElementById('edit-admin-name').value = data.name || '';
    document.getElementById('edit-admin-email').value = data.email || '';
    const phone = document.getElementById('edit-admin-phone');
    if (phone) phone.value = data.phone || '';
    document.getElementById('edit-admin-role').value = data.role || '<?= ROLE_ADMIN ?>';
    document.getElementById('edit-admin-status').value = data.status || 'active';
    document.querySelectorAll('[data-admin-permission]').forEach((input) => {
      input.checked = Array.isArray(data.permissions) && data.permissions.includes(input.dataset.adminPermission);
    });
    openModal('edit-admin-modal');
  } catch (e) {
    alert('Could not open admin editor.');
  }
}
</script>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>

