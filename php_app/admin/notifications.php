<?php
/**
 * Uthenga — Admin Notifications Hub
 */
$pageTitle = 'Notifications';
$activeNav = 'admin-notifications';

require_once __DIR__ . '/includes/admin_header.php';

$hasAuditLogs = uthenga_table_exists('audit_logs');
$hasSupportTickets = uthenga_table_exists('support_tickets');

$recentLogs = $hasAuditLogs ? dbQuery("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10") : [];
$openTickets = $hasSupportTickets ? dbQuery("SELECT * FROM support_tickets WHERE status IN ('Open','In Progress') ORDER BY created_at DESC LIMIT 10") : [];
$pendingVendorApprovals = dbCount("SELECT COUNT(*) FROM users WHERE role IN ('Event Organizer','Hotel/Lodge Manager','Tour Operator','Transport Provider','Vendor') AND is_approved = 0");
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= admin_icon_svg('bell') ?><span>Notifications</span></h1>
    <p class="text-muted">Monitor platform alerts, pending approvals, and recent system activity.</p>
  </div>
</div>

<div class="grid grid-cols-3 gap-2" style="margin-bottom:2rem;">
  <div class="stat-card">
    <div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('bell') ?></div>
    <div><div class="stat-value"><?= number_format(count($recentLogs)) ?></div><div class="stat-label">Recent Alerts</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-blue"><?= admin_icon_svg('support') ?></div>
    <div><div class="stat-value"><?= number_format(count($openTickets)) ?></div><div class="stat-label">Open Support Items</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-green"><?= admin_icon_svg('store') ?></div>
    <div><div class="stat-value"><?= number_format($pendingVendorApprovals) ?></div><div class="stat-label">Pending Vendors</div></div>
  </div>
</div>

<div class="grid grid-cols-2 gap-3">
  <div class="glass-panel" style="padding:1.25rem;">
    <h3 style="margin-bottom:1rem;">Recent System Alerts</h3>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr><th>Time</th><th>Action</th><th>Details</th></tr>
        </thead>
        <tbody>
          <?php if (empty($recentLogs)): ?>
            <tr><td colspan="3" style="text-align:center;padding:1.5rem;color:var(--clr-text-muted);">No recent alerts.</td></tr>
          <?php else: ?>
            <?php foreach ($recentLogs as $log): ?>
              <tr>
                <td class="text-xs text-muted"><?= e(substr($log['created_at'], 0, 16)) ?></td>
                <td class="text-xs font-bold"><?= e($log['action']) ?></td>
                <td class="text-xs text-muted"><?= e($log['details']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;">
    <h3 style="margin-bottom:1rem;">Open Support Tickets</h3>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr><th>Ticket</th><th>Customer</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if (empty($openTickets)): ?>
            <tr><td colspan="3" style="text-align:center;padding:1.5rem;color:var(--clr-text-muted);">No open support tickets.</td></tr>
          <?php else: ?>
            <?php foreach ($openTickets as $ticket): ?>
              <tr>
                <td class="font-mono text-xs"><?= e($ticket['id']) ?></td>
                <td class="text-xs"><?= e($ticket['customer_name']) ?></td>
                <td><span class="badge badge-pending"><?= e($ticket['status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
