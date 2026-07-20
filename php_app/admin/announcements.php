<?php
/**
 * Uthenga - System Announcements
 */
$pageTitle = 'System Announcements';
$activeNav = 'admin-notifications';

require_once __DIR__ . '/includes/admin_header.php';

$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    if (!hasRole(ROLE_SUPER_ADMIN)) {
        $flashError = 'Only the Super Administrator can send announcements.';
    } else {
        $action = $_POST['announcement_action'] ?? '';
        try {
            if ($action === 'create') {
                $title = trim($_POST['title'] ?? '');
                $message = trim($_POST['message'] ?? '');
                $audience = $_POST['audience'] ?? 'all';
                $priority = $_POST['priority'] ?? 'medium';
                $startsAt = $_POST['starts_at'] ?? date('Y-m-d\TH:i');

                if ($title === '' || $message === '') {
                    throw new RuntimeException('Title and message are required.');
                }

                if (!in_array($audience, ['all', 'customers', 'vendors', 'admins'], true)) {
                    throw new RuntimeException('Invalid audience selected.');
                }
                if (!in_array($priority, ['low', 'medium', 'high', 'critical'], true)) {
                    throw new RuntimeException('Invalid priority selected.');
                }

                dbExecute(
                    'INSERT INTO system_announcements (title, message, audience, priority, starts_at, ends_at, is_active, created_by)
                     VALUES (?,?,?,?,?,?,?,?)',
                    [
                        $title,
                        $message,
                        $audience,
                        $priority,
                        date('Y-m-d H:i:s', strtotime($startsAt)),
                        null,
                        1,
                        $_SESSION['user_id'],
                    ]
                );
                logAction('Created Announcement', "Super Admin published announcement: {$title}");
                $flashSuccess = 'Announcement sent successfully.';
            } elseif ($action === 'toggle') {
                $id = trim($_POST['id'] ?? '');
                $row = dbQueryOne('SELECT id, is_active, title FROM system_announcements WHERE id = ?', [$id]);
                if (!$row) {
                    throw new RuntimeException('Announcement not found.');
                }
                $next = (int)!((bool)$row['is_active']);
                dbExecute('UPDATE system_announcements SET is_active = ? WHERE id = ?', [$next, $id]);
                logAction($next ? 'Activated Announcement' : 'Deactivated Announcement', "Announcement: {$row['title']}");
                $flashSuccess = $next ? 'Announcement activated.' : 'Announcement deactivated.';
            } elseif ($action === 'delete') {
                $id = trim($_POST['id'] ?? '');
                $row = dbQueryOne('SELECT id, title FROM system_announcements WHERE id = ?', [$id]);
                if (!$row) {
                    throw new RuntimeException('Announcement not found.');
                }
                dbExecute('DELETE FROM system_announcements WHERE id = ?', [$id]);
                logAction('Deleted Announcement', "Announcement removed: {$row['title']}");
                $flashSuccess = 'Announcement deleted.';
            }
        } catch (Throwable $e) {
            $flashError = $e->getMessage();
        }
    }
}

$announcements = dbQuery('SELECT * FROM system_announcements ORDER BY created_at DESC LIMIT 20');
?>

<div class="page-header">
  <div>
    <h1 class="page-title">System Announcements</h1>
    <p class="text-muted">Publish platform-wide messages to the audience you choose.</p>
  </div>
  <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-secondary"><?= admin_icon_svg('chart') ?> Back to Dashboard</a>
</div>

<?php if ($flashSuccess): ?><div class="alert alert-success">✓ <?= e($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-error">✕ <?= e($flashError) ?></div><?php endif; ?>

<div class="grid grid-cols-2 gap-3">
  <div class="glass-panel" style="padding:1.25rem;">
    <div class="section-head">
      <div>
        <h3>Send Announcement</h3>
        <p class="text-xs text-muted">This writes directly to `system_announcements`.</p>
      </div>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="announcement_action" value="create">
      <div class="form-group">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" required placeholder="Service maintenance notice">
      </div>
      <div class="form-group">
        <label class="form-label">Message</label>
        <textarea name="message" class="form-control" rows="6" required placeholder="Write the full announcement..."></textarea>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div class="form-group">
          <label class="form-label">Audience</label>
          <select name="audience" class="form-control">
            <option value="all">All Users</option>
            <option value="customers">Customers</option>
            <option value="vendors">Vendors</option>
            <option value="admins">Admins</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Priority</label>
          <select name="priority" class="form-control">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Start Time</label>
        <input type="datetime-local" name="starts_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
      </div>
      <button type="submit" class="btn btn-primary"><?= admin_icon_svg('megaphone') ?> Send Announcement</button>
    </form>
  </div>

  <div class="glass-panel" style="padding:1.25rem;">
    <div class="section-head">
      <div>
        <h3>Recent Announcements</h3>
        <p class="text-xs text-muted">Manage visibility and cleanup old notices.</p>
      </div>
    </div>
    <div class="simple-list">
      <?php if (empty($announcements)): ?>
        <div class="text-muted">No announcements yet.</div>
      <?php else: ?>
        <?php foreach ($announcements as $a): ?>
          <div class="simple-list-item simple-list-item-stack">
            <div class="simple-list-top">
              <strong><?= e($a['title']) ?></strong>
              <span class="badge <?= $a['is_active'] ? 'badge-approved' : 'badge-cancelled' ?>"><?= $a['is_active'] ? 'Active' : 'Inactive' ?></span>
            </div>
            <div class="text-xs text-muted" style="line-height:1.6;"><?= e($a['message']) ?></div>
            <div class="simple-list-top">
              <span class="text-xs text-muted"><?= e($a['audience']) ?> • <?= e($a['priority']) ?> • <?= e(substr($a['created_at'], 0, 16)) ?></span>
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <form method="POST" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="announcement_action" value="toggle">
                  <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-secondary"><?= $a['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="POST" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="announcement_action" value="delete">
                  <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                  <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this announcement?');">Delete</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
