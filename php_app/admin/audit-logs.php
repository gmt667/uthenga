<?php
/**
 * Uthenga — Admin Platform Audit Logs Viewer
 */
$pageTitle = 'Platform Audit Logs';
$activeNav = 'admin-logs';

require_once __DIR__ . '/includes/admin_header.php';

// Filters
$search       = trim($_GET['q'] ?? '');
$filterAction = trim($_GET['action_filter'] ?? 'all');
$filterRole   = trim($_GET['role_filter'] ?? 'all');
$dateStart    = trim($_GET['date_start'] ?? '');
$dateEnd      = trim($_GET['date_end'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 40;

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(user_name LIKE ? OR details LIKE ? OR action LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterAction !== 'all') {
    $where[] = 'action = ?';
    $params[] = $filterAction;
}
if ($filterRole !== 'all') {
    $where[] = 'user_role = ?';
    $params[] = $filterRole;
}
if ($dateStart !== '') {
    $where[] = 'created_at >= ?';
    $params[] = $dateStart . ' 00:00:00';
}
if ($dateEnd !== '') {
    $where[] = 'created_at <= ?';
    $params[] = $dateEnd . ' 23:59:59';
}

$whereStr = implode(' AND ', $where);
$totalCount = dbCount("SELECT COUNT(*) FROM audit_logs WHERE $whereStr", $params);
$totalPages = max(1, ceil($totalCount / $perPage));
$offset = ($page - 1) * $perPage;

$logs = dbQuery("
    SELECT * FROM audit_logs
    WHERE $whereStr
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);

// Fetch unique action keys for filter
$allActions = dbQuery("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");
$allRoles   = dbQuery("SELECT DISTINCT user_role FROM audit_logs ORDER BY user_role ASC");
?>

<div class="page-header">
  <div>
    <h1 class="page-title">🪵 Platform Audit Logs</h1>
    <p class="text-muted">Monitor system events, admin modifications, authentication attempts, and vendor updates.</p>
  </div>
</div>

<!-- Advanced Filters Panel -->
<div class="glass-panel" style="padding: 1.25rem; margin-bottom: 1.5rem;">
  <form method="GET" action="audit-logs.php" id="filter-form" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.75rem; align-items:end;">
    
    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label" style="font-size:0.75rem;">Search Keyword</label>
      <input type="text" name="q" placeholder="e.g. Login, Password..." class="form-control" value="<?= e($search) ?>">
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label" style="font-size:0.75rem;">Action Type</label>
      <select name="action_filter" class="form-control">
        <option value="all" <?= $filterAction === 'all' ? 'selected' : '' ?>>All Actions</option>
        <?php foreach ($allActions as $act): ?>
          <option value="<?= e($act['action']) ?>" <?= $filterAction === $act['action'] ? 'selected' : '' ?>><?= e($act['action']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label" style="font-size:0.75rem;">User Role</label>
      <select name="role_filter" class="form-control">
        <option value="all" <?= $filterRole === 'all' ? 'selected' : '' ?>>All Roles</option>
        <?php foreach ($allRoles as $role): ?>
          <option value="<?= e($role['user_role']) ?>" <?= $filterRole === $role['user_role'] ? 'selected' : '' ?>><?= e($role['user_role']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label" style="font-size:0.75rem;">From Date</label>
      <input type="date" name="date_start" class="form-control" value="<?= e($dateStart) ?>">
    </div>

    <div class="form-group" style="margin-bottom:0;">
      <label class="form-label" style="font-size:0.75rem;">To Date</label>
      <input type="date" name="date_end" class="form-control" value="<?= e($dateEnd) ?>">
    </div>

    <div style="display:flex; gap:0.5rem; justify-content:end;">
      <button type="submit" class="btn btn-primary" style="flex:1;">Filter</button>
      <a href="audit-logs.php" class="btn btn-secondary" style="flex:1; text-align:center;">Clear</a>
    </div>
  </form>
</div>

<!-- Logs Timeline View -->
<div class="glass-panel" style="padding: 1.5rem;">
  <?php if (empty($logs)): ?>
    <div style="text-align: center; padding: 2rem 0; color: var(--clr-text-soft);">
      No audit log entries found matching criteria.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width:160px;">Timestamp</th>
            <th style="width:180px;">Action</th>
            <th style="width:150px;">User</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): 
              $act = $log['action'] ?? '';
              if (strpos($act, 'Login Failed') !== false || strpos($act, 'Denied') !== false) {
                  $badgeClass = 'badge-rejected';
              } elseif (strpos($act, 'Login') !== false) {
                  $badgeClass = 'badge-approved';
              } else {
                  $badgeClass = 'badge-pending';
              }
          ?>
            <tr>
              <td class="text-xs text-muted"><?= e($log['created_at']) ?></td>
              <td><span class="badge <?= $badgeClass ?>"><?= e($log['action']) ?></span></td>
              <td>
                <strong><?= e($log['user_name']) ?></strong>
                <div class="text-xs text-muted"><?= e($log['user_role']) ?></div>
              </td>
              <td class="text-sm"><?= e($log['details']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div style="display:flex; justify-content:center; gap:0.5rem; margin-top:2rem; flex-wrap:wrap;">
  <?php if ($page > 1): ?>
    <a href="?page=<?= $page-1 ?>&action_filter=<?= urlencode($filterAction) ?>&role_filter=<?= urlencode($filterRole) ?>&date_start=<?= urlencode($dateStart) ?>&date_end=<?= urlencode($dateEnd) ?>&q=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">← Prev</a>
  <?php endif; ?>
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>&action_filter=<?= urlencode($filterAction) ?>&role_filter=<?= urlencode($filterRole) ?>&date_start=<?= urlencode($dateStart) ?>&date_end=<?= urlencode($dateEnd) ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($page < $totalPages): ?>
    <a href="?page=<?= $page+1 ?>&action_filter=<?= urlencode($filterAction) ?>&role_filter=<?= urlencode($filterRole) ?>&date_start=<?= urlencode($dateStart) ?>&date_end=<?= urlencode($dateEnd) ?>&q=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">Next →</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
