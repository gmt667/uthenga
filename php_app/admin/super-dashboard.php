<?php
/**
 * Uthenga - Super Admin Command Center
 */
$pageTitle = 'Super Admin Command Center';
$activeNav = 'super-dashboard';

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/shop_helpers.php';

requireLogin([ROLE_SUPER_ADMIN]);

require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/includes/admin_icons.php';

function superDashboardBadgeClass(string $status): string {
    return match (strtolower(trim($status))) {
        'confirmed', 'completed', 'resolved', 'closed', 'paid', 'success', 'approved', 'active', 'authorized' => 'badge-approved',
        'cancelled', 'failed', 'rejected', 'suspended' => 'badge-cancelled',
        'open', 'in progress', 'in_progress', 'waiting_customer', 'pending' => 'badge-pending',
        default => 'badge-confirmed',
    };
}

$hasSupportTickets = uthenga_table_exists('support_tickets');
$hasUserSessions = uthenga_table_exists('user_sessions');
$hasBookingItems = uthenga_table_exists('booking_items');

$counts = [
    'users'   => dbCount('SELECT COUNT(*) FROM users'),
    'vendors' => uthenga_table_exists('vendor_profiles')
        ? dbCount('SELECT COUNT(*) FROM vendor_profiles')
        : dbCount("SELECT COUNT(*) FROM users WHERE role IN ('Vendor','Event Organizer','Hotel/Lodge Manager','Tour Operator','Transport Provider')"),
    'events'     => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'event' AND is_active = 1"),
    'properties' => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'accommodation' AND is_active = 1"),
    'tours'      => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'tour' AND is_active = 1"),
    'routes'     => dbCount("SELECT COUNT(*) FROM listings WHERE listing_type = 'transport' AND is_active = 1"),
    'bookings'   => dbCount('SELECT COUNT(*) FROM bookings'),
    'revenue'    => dbQueryOne("SELECT COALESCE(SUM(grand_total),0) AS total FROM bookings WHERE LOWER(payment_status) = 'paid'") ?: ['total' => 0],
    'openTickets' => $hasSupportTickets ? dbCount("SELECT COUNT(*) FROM support_tickets WHERE LOWER(status) IN ('open','in_progress','waiting_customer')") : 0,
    'shopProducts' => uthenga_table_exists('shop_products') ? dbCount("SELECT COUNT(*) FROM shop_products WHERE deleted_at IS NULL") : 0,
    'shopOrders' => uthenga_table_exists('shop_orders') ? dbCount('SELECT COUNT(*) FROM shop_orders') : 0,
    'shopRevenue' => uthenga_table_exists('shop_orders') ? (dbQueryOne("SELECT COALESCE(SUM(total_amount),0) AS total FROM shop_orders WHERE LOWER(payment_status) IN ('paid','authorized','partially_paid')") ?: ['total' => 0]) : ['total' => 0],
];

$recentBookings = dbQuery("
    SELECT booking_code, reference_name, booking_status, payment_status, grand_total, created_at
    FROM bookings
    ORDER BY created_at DESC
    LIMIT 8
");

$recentAdmins = dbQuery("
    SELECT id, full_name, email, account_status, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 8
");

$recentTickets = $hasSupportTickets ? dbQuery("
    SELECT ticket_code, requester_name, subject, status, created_at
    FROM support_tickets
    ORDER BY created_at DESC
    LIMIT 6
") : [];

// Super Admin Analytics Calculations
$monthlyRevenue = dbQuery("
    SELECT
        DATE_FORMAT(created_at, '%b %Y') AS month,
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        COALESCE(SUM(grand_total), 0) AS revenue
    FROM bookings
    WHERE LOWER(payment_status) = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month
    ORDER BY month_key
");
$revenueLabels = json_encode(array_column($monthlyRevenue, 'month'));
$revenueValues = json_encode(array_map(fn($r) => (float) $r['revenue'], $monthlyRevenue));

$userGrowth = dbQuery("
    SELECT
        DATE_FORMAT(created_at, '%b %Y') AS month,
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        COUNT(*) AS registrations
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month_key, month
    ORDER BY month_key
");
$growthLabels = json_encode(array_column($userGrowth, 'month'));
$growthValues = json_encode(array_map(fn($r) => (int) $r['registrations'], $userGrowth));

$destinations = $hasBookingItems ? dbQuery("
    SELECT city, COUNT(*) AS bookings_count
    FROM (
        SELECT COALESCE(l.location, 'Unknown') AS city
        FROM bookings b
        JOIN booking_items bi ON bi.booking_id = b.id
        LEFT JOIN listings l ON l.id = bi.reference_id
        WHERE LOWER(b.payment_status) = 'paid'
    ) t
    GROUP BY city
    ORDER BY bookings_count DESC
    LIMIT 5
") : [];

// System Health Checks
$cacheDir = __DIR__ . '/../cache';
$cacheSize = 0;
if (is_dir($cacheDir)) {
    foreach (scandir($cacheDir) as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $cacheDir . '/' . $file;
            if (is_file($filePath)) {
                $cacheSize += filesize($filePath);
            }
        }
    }
}
$healthStatus = [
    'db' => 'Online',
    'sessions' => $hasUserSessions ? dbCount('SELECT COUNT(*) FROM user_sessions') . ' Active' : 'Unavailable',
    'cache' => number_format($cacheSize / 1024, 2) . ' KB',
    'system' => ($hasUserSessions && $hasSupportTickets) ? 'Operational' : 'Needs Migration',
];
?>

<style>
  @media (max-width: 768px) {
    .dashboard-head-meta {
      width: 100%;
      display: grid;
      grid-template-columns: 1fr;
      gap: 0.5rem;
      margin-top: 0.85rem;
    }

    .dashboard-head-meta .btn {
      width: 100%;
      justify-content: center;
    }

    .glass-panel {
      padding: 1rem !important;
    }

    .grid.grid-cols-2,
    .grid.grid-cols-3 {
      grid-template-columns: 1fr !important;
    }

    .grid.grid-cols-3 > section,
    .grid.grid-cols-2 > section {
      grid-column: auto !important;
    }

    .table-responsive {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
  }
</style>

<div class="container dashboard-content-frame" style="padding-top:2rem;padding-bottom:3rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('shield') ?><span>Super Admin Command Center</span></h1>
      <p class="text-muted">High-level operations dashboard and platform administration metrics.</p>
    </div>
    <div class="dashboard-head-meta">
      <a href="<?= BASE_URL ?>admin/analytics.php" class="btn btn-primary btn-sm">Platform Analytics</a>
      <a href="<?= BASE_URL ?>admin/system-monitor.php" class="btn btn-secondary btn-sm">System Monitor</a>
      <a href="<?= BASE_URL ?>admin/audit-logs.php" class="btn btn-secondary btn-sm">System Audit Logs</a>
      <a href="<?= BASE_URL ?>admin/shop.php" class="btn btn-secondary btn-sm">Global Shop Management</a>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1.5rem;">
    <div class="presentation-grid">
      <div class="presentation-stat"><span>Total Users</span><strong><?= number_format((int) $counts['users']) ?></strong></div>
      <div class="presentation-stat"><span>Registered Vendors</span><strong><?= number_format((int) $counts['vendors']) ?></strong></div>
      <div class="presentation-stat"><span>Total Bookings</span><strong><?= number_format((int) $counts['bookings']) ?></strong></div>
      <div class="presentation-stat"><span>Gross Revenue</span><strong><?= formatMWK((float) ($counts['revenue']['total'] ?? 0)) ?></strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1.5rem;">
    <div class="section-head">
      <div>
        <h3>Operations Snapshot</h3>
        <p class="text-xs text-muted">Platform load, support pressure, and catalog composition.</p>
      </div>
    </div>
    <div class="presentation-grid">
      <div class="presentation-stat"><span>Open tickets</span><strong><?= number_format((int) $counts['openTickets']) ?></strong></div>
      <div class="presentation-stat"><span>Events</span><strong><?= number_format((int) $counts['events']) ?></strong></div>
      <div class="presentation-stat"><span>Properties</span><strong><?= number_format((int) $counts['properties']) ?></strong></div>
      <div class="presentation-stat"><span>Routes</span><strong><?= number_format((int) $counts['routes']) ?></strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1.5rem;">
    <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">System Health Overview</h2>
    <div class="presentation-grid">
      <div class="presentation-stat"><span style="color:#10b981;">Database Status</span><strong><?= e($healthStatus['db']) ?></strong></div>
      <div class="presentation-stat"><span style="color:#38bdf8;">Sessions Health</span><strong><?= e($healthStatus['sessions']) ?></strong></div>
      <div class="presentation-stat"><span style="color:#f59e0b;">Cache Size</span><strong><?= e($healthStatus['cache']) ?></strong></div>
      <div class="presentation-stat"><span style="color:#a855f7;">System Core</span><strong><?= e($healthStatus['system']) ?></strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1.5rem;">
    <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">Global Shop Overview</h2>
    <div class="presentation-grid">
      <div class="presentation-stat"><span>Products</span><strong><?= number_format((int) $counts['shopProducts']) ?></strong></div>
      <div class="presentation-stat"><span>Orders</span><strong><?= number_format((int) $counts['shopOrders']) ?></strong></div>
      <div class="presentation-stat"><span>Revenue</span><strong><?= formatMWK((float) ($counts['shopRevenue']['total'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Delivery Partners</span><strong><?= number_format((int) (uthenga_table_exists('delivery_riders') ? dbCount('SELECT COUNT(*) FROM delivery_riders') : 0)) ?></strong></div>
    </div>
  </div>

  <div class="grid grid-cols-2 gap-3" style="margin-bottom:1.5rem;">
    <section class="glass-panel">
      <div class="section-head">
        <div>
          <h3>Monthly Revenue (Paid)</h3>
          <p class="text-xs text-muted">Platform revenue trend over the past 6 months.</p>
        </div>
      </div>
      <div style="height: 250px; position: relative;">
        <canvas id="superRevenueChart"></canvas>
      </div>
    </section>

    <section class="glass-panel">
      <div class="section-head">
        <div>
          <h3>User Sign-ups (Growth)</h3>
          <p class="text-xs text-muted">Monthly user growth trend over the past 6 months.</p>
        </div>
      </div>
      <div style="height: 250px; position: relative;">
        <canvas id="superGrowthChart"></canvas>
      </div>
    </section>
  </div>

  <div class="grid grid-cols-3 gap-3" style="margin-bottom: 1.5rem;">
    <section class="glass-panel" style="grid-column: span 2;">
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
                  <td><span class="badge <?= superDashboardBadgeClass((string) ($row['booking_status'] ?? '')) ?>"><?= e($row['booking_status'] ?? '') ?></span></td>
                  <td><span class="badge <?= superDashboardBadgeClass((string) ($row['payment_status'] ?? '')) ?>"><?= e($row['payment_status'] ?? '') ?></span></td>
                  <td><?= formatMWK((float) ($row['grand_total'] ?? 0)) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="glass-panel">
      <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">Popular Destinations</h2>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>City</th>
              <th>Bookings</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($destinations)): ?>
              <tr><td colspan="2" class="text-muted">No data available</td></tr>
            <?php else: ?>
              <?php foreach ($destinations as $d): ?>
                <tr>
                  <td><strong><?= e($d['city']) ?></strong></td>
                  <td><?= number_format($d['bookings_count']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div class="grid grid-cols-2 gap-3" style="margin-bottom: 1.5rem;">
    <section class="glass-panel">
      <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">Support Tickets</h2>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Requester</th>
              <th>Subject</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recentTickets)): ?>
              <tr><td colspan="4" class="text-muted">No support tickets found.</td></tr>
            <?php else: ?>
              <?php foreach ($recentTickets as $ticket): ?>
                <tr>
                  <td><?= e($ticket['ticket_code'] ?? '') ?></td>
                  <td><?= e($ticket['requester_name'] ?? '') ?></td>
                  <td><?= e($ticket['subject'] ?? '') ?></td>
                  <td><span class="badge <?= superDashboardBadgeClass((string) ($ticket['status'] ?? '')) ?>"><?= e($ticket['status'] ?? '') ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="glass-panel">
      <h2 class="page-title" style="font-size:1.35rem;margin-bottom:0.75rem;">Recent Registrations</h2>
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
            <?php if (empty($recentAdmins)): ?>
              <tr><td colspan="4" class="text-muted">No recent registrations found.</td></tr>
            <?php else: ?>
              <?php foreach ($recentAdmins as $row): ?>
                <tr>
                  <td><?= e($row['full_name'] ?? '') ?></td>
                  <td><?= e($row['email'] ?? '') ?></td>
                  <td><span class="badge <?= superDashboardBadgeClass((string) ($row['account_status'] ?? '')) ?>"><?= e($row['account_status'] ?? '') ?></span></td>
                  <td class="text-xs text-muted"><?= e($row['created_at'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const superChartConfig = {
  color: 'rgba(255,255,255,.7)',
  font: { family: 'Inter, sans-serif', size: 12 },
};
Chart.defaults.color = superChartConfig.color;
Chart.defaults.font = superChartConfig.font;

new Chart(document.getElementById('superRevenueChart'), {
  type: 'line',
  data: {
    labels: <?= $revenueLabels ?>,
    datasets: [{
      label: 'Gross Sales (MK)',
      data: <?= $revenueValues ?>,
      borderColor: '#10b981',
      backgroundColor: 'rgba(16,185,129,0.1)',
      borderWidth: 2,
      fill: true
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { grid: { color: 'rgba(255,255,255,.07)' }, ticks: { callback: v => 'MK ' + v.toLocaleString() } },
      x: { grid: { display: false } }
    }
  }
});

new Chart(document.getElementById('superGrowthChart'), {
  type: 'line',
  data: {
    labels: <?= $growthLabels ?>,
    datasets: [{
      label: 'New Registrations',
      data: <?= $growthValues ?>,
      borderColor: '#38bdf8',
      backgroundColor: 'rgba(56,189,248,0.1)',
      borderWidth: 2,
      fill: true
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { grid: { color: 'rgba(255,255,255,.07)' }, beginAtZero: true },
      x: { grid: { display: false } }
    }
  }
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
