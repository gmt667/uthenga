<?php
/**
 * Uthenga - Admin Dashboard
 */
$pageTitle = 'Admin Dashboard';
$activeNav = 'admin-dashboard';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/shop_helpers.php';

if (($_SESSION['user_role'] ?? '') === ROLE_SUPER_ADMIN) {
    redirect(BASE_URL . 'admin/super-dashboard.php');
}

require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/includes/admin_icons.php';

function dashboardBadgeClass(string $status): string {
    return match (strtolower(trim($status))) {
        'confirmed', 'completed', 'resolved', 'closed', 'paid', 'success', 'authorized' => 'badge-approved',
        'cancelled', 'failed', 'rejected', 'suspended' => 'badge-cancelled',
        'open', 'in progress', 'in_progress', 'waiting_customer', 'pending' => 'badge-pending',
        'approved', 'active' => 'badge-success',
        default => 'badge-confirmed',
    };
}

$hasSupportTickets = uthenga_table_exists('support_tickets');

$metrics = [
    'users'   => dbCount('SELECT COUNT(*) FROM users'),
    'vendors' => uthenga_table_exists('vendor_profiles')
        ? dbCount('SELECT COUNT(*) FROM vendor_profiles')
        : dbCount("SELECT COUNT(*) FROM users WHERE role IN ('Vendor','Event Organizer','Hotel/Lodge Manager','Tour Operator','Transport Provider')"),
    'events'     => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'event' AND is_active = 1"),
    'properties' => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'accommodation' AND is_active = 1"),
    'tours'      => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'tour' AND is_active = 1"),
    'routes'     => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'transport' AND is_active = 1"),
    'bookings'   => dbCount('SELECT COUNT(*) FROM bookings'),
    'revenue'    => dbQueryOne("SELECT COALESCE(SUM(grand_total),0) AS total FROM bookings WHERE LOWER(payment_status) IN ('paid','authorized','partially_paid')") ?: ['total' => 0],
    'openTickets' => $hasSupportTickets ? dbCount("SELECT COUNT(*) FROM support_tickets WHERE LOWER(status) IN ('open','in_progress','waiting_customer')") : 0,
    'shopProducts' => uthenga_table_exists('shop_products') ? dbCount("SELECT COUNT(*) FROM shop_products WHERE deleted_at IS NULL") : 0,
    'shopOrders' => uthenga_table_exists('shop_orders') ? dbCount('SELECT COUNT(*) FROM shop_orders') : 0,
    'shopRevenue' => uthenga_table_exists('shop_orders') ? (dbQueryOne("SELECT COALESCE(SUM(total_amount),0) AS total FROM shop_orders WHERE LOWER(payment_status) IN ('paid','authorized','partially_paid')") ?: ['total' => 0]) : ['total' => 0],
];

$recentBookings = dbQuery("
    SELECT booking_code, reference_name, booking_status, payment_status, grand_total, created_at
    FROM bookings
    ORDER BY created_at DESC
    LIMIT 5
");

$recentUsers = dbQuery("
    SELECT id, full_name, email, account_status, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
");

$recentTickets = $hasSupportTickets ? dbQuery("
    SELECT ticket_code, requester_name, subject, status, created_at
    FROM support_tickets
    ORDER BY created_at DESC
    LIMIT 5
") : [];

?>
<div class="container dashboard-content-frame" style="padding-top:2rem;padding-bottom:3rem;">
<div class="page-header">
  <div>
    <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('grid') ?><span>Admin Dashboard</span></h1>
    <p class="text-muted">Live counts for the modular marketplace tables.</p>
  </div>
  <div class="dashboard-head-meta">
    <a href="<?= BASE_URL ?>admin/bookings.php" class="btn btn-secondary btn-sm">Bookings</a>
    <a href="<?= BASE_URL ?>admin/support.php" class="btn btn-secondary btn-sm">Support</a>
    <a href="<?= BASE_URL ?>admin/analytics.php" class="btn btn-primary btn-sm">Analytics</a>
    <a href="<?= BASE_URL ?>admin/shop.php" class="btn btn-secondary btn-sm">Shop Management</a>
  </div>
</div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1rem;">
    <div class="presentation-grid">
      <div class="presentation-stat"><span>Users</span><strong><?= number_format((int) $metrics['users']) ?></strong></div>
      <div class="presentation-stat"><span>Vendors</span><strong><?= number_format((int) $metrics['vendors']) ?></strong></div>
      <div class="presentation-stat"><span>Bookings</span><strong><?= number_format((int) $metrics['bookings']) ?></strong></div>
      <div class="presentation-stat"><span>Revenue</span><strong><?= formatMWK((float) ($metrics['revenue']['total'] ?? 0)) ?></strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1rem;">
    <div class="section-head">
      <div>
        <h3>Operations Snapshot</h3>
        <p class="text-xs text-muted">Support activity and the current state of the public catalog.</p>
      </div>
    </div>
    <div class="presentation-grid">
      <div class="presentation-stat"><span>Open tickets</span><strong><?= number_format((int) $metrics['openTickets']) ?></strong></div>
      <div class="presentation-stat"><span>Events</span><strong><?= number_format((int) $metrics['events']) ?></strong></div>
      <div class="presentation-stat"><span>Properties</span><strong><?= number_format((int) $metrics['properties']) ?></strong></div>
      <div class="presentation-stat"><span>Transport routes</span><strong><?= number_format((int) $metrics['routes']) ?></strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1rem;">
    <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">Catalog</h2>
    <div class="presentation-grid">
      <div class="presentation-stat"><span>Tours</span><strong><?= number_format((int) $metrics['tours']) ?></strong></div>
      <div class="presentation-stat"><span>Support tickets</span><strong><?= number_format((int) $metrics['openTickets']) ?></strong></div>
      <div class="presentation-stat"><span>Recent users</span><strong><?= number_format(count($recentUsers)) ?></strong></div>
      <div class="presentation-stat"><span>Recent bookings</span><strong><?= number_format(count($recentBookings)) ?></strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1rem;">
    <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">Shop Snapshot</h2>
    <div class="presentation-grid">
      <div class="presentation-stat"><span>Shop products</span><strong><?= number_format((int) $metrics['shopProducts']) ?></strong></div>
      <div class="presentation-stat"><span>Shop orders</span><strong><?= number_format((int) $metrics['shopOrders']) ?></strong></div>
      <div class="presentation-stat"><span>Shop revenue</span><strong><?= formatMWK((float) ($metrics['shopRevenue']['total'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Management</span><strong>Active</strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1rem;">
    <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">Recent Bookings</h2>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Reference</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentBookings)): ?>
            <tr><td colspan="5" class="text-muted">No recent bookings found.</td></tr>
          <?php else: ?>
            <?php foreach ($recentBookings as $row): ?>
              <tr>
                <td><?= e($row['booking_code'] ?? '') ?></td>
                <td><?= e($row['reference_name'] ?? '') ?></td>
                <td><span class="badge <?= dashboardBadgeClass((string) ($row['booking_status'] ?? '')) ?>"><?= e($row['booking_status'] ?? '') ?></span></td>
                <td><span class="badge <?= dashboardBadgeClass((string) ($row['payment_status'] ?? '')) ?>"><?= e($row['payment_status'] ?? '') ?></span></td>
                <td><?= formatMWK((float) ($row['grand_total'] ?? 0)) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1rem;">
    <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">Support Tickets</h2>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Requester</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Opened</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentTickets)): ?>
            <tr><td colspan="5" class="text-muted">No support tickets found.</td></tr>
          <?php else: ?>
            <?php foreach ($recentTickets as $ticket): ?>
              <tr>
                <td><?= e($ticket['ticket_code'] ?? '') ?></td>
                <td><?= e($ticket['requester_name'] ?? '') ?></td>
                <td><?= e($ticket['subject'] ?? '') ?></td>
                <td><span class="badge <?= dashboardBadgeClass((string) ($ticket['status'] ?? '')) ?>"><?= e($ticket['status'] ?? '') ?></span></td>
                <td><?= e($ticket['created_at'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;">
    <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">Recent Users</h2>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Joined</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentUsers)): ?>
            <tr><td colspan="4" class="text-muted">No recent users found.</td></tr>
          <?php else: ?>
            <?php foreach ($recentUsers as $row): ?>
              <tr>
                <td><?= e($row['full_name'] ?? '') ?></td>
                <td><?= e($row['email'] ?? '') ?></td>
                <td><span class="badge <?= dashboardBadgeClass((string) ($row['account_status'] ?? '')) ?>"><?= e($row['account_status'] ?? '') ?></span></td>
                <td><?= e($row['created_at'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
