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

function superDashboardStatusLabel(string $status): string {
    $normalized = strtolower(trim($status));
    return match ($normalized) {
        'success' => 'Successful',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'approved' => 'Approved',
        'active' => 'Active',
        'authorized' => 'Authorized',
        'cancelled' => 'Cancelled',
        'failed' => 'Failed',
        'rejected' => 'Rejected',
        'suspended' => 'Suspended',
        'open' => 'Open',
        'in progress', 'in_progress' => 'In Progress',
        'waiting_customer' => 'Waiting for Customer',
        default => ucwords(str_replace(['_', '-'], ' ', $normalized)),
    };
}

function superDashboardStatusHint(string $status): string {
    $normalized = strtolower(trim($status));
    return match ($normalized) {
        'success', 'paid' => 'The payment or workflow completed successfully.',
        'pending' => 'The item is waiting for the next action.',
        'confirmed' => 'The booking or order has been confirmed.',
        'completed', 'closed', 'resolved' => 'The item is finished and closed out.',
        'approved', 'active' => 'This account or item is active and approved.',
        'authorized' => 'The payment has been authorized and is awaiting capture or settlement.',
        'cancelled', 'failed', 'rejected', 'suspended' => 'This item is no longer active.',
        'open' => 'The item is still open and needs attention.',
        'in progress', 'in_progress' => 'Work is currently in progress.',
        'waiting_customer' => 'Waiting for the customer to respond or complete an action.',
        default => 'Current system status.',
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
    'shopCarts' => uthenga_table_exists('shop_cart_items') ? dbQueryOne("SELECT COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), CONCAT('session:', session_token))) AS total FROM shop_cart_items") : ['total' => 0],
    'shopCartItems' => uthenga_table_exists('shop_cart_items') ? dbCount('SELECT COUNT(*) FROM shop_cart_items') : 0,
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
$recentCartItems = uthenga_table_exists('shop_cart_items') ? dbQuery("
    SELECT sci.*, sp.name AS product_name, sp.sku AS product_sku, u.name AS customer_name, u.email AS customer_email
    FROM shop_cart_items sci
    LEFT JOIN shop_products sp ON sp.id = sci.product_id
    LEFT JOIN users u ON u.id = sci.user_id
    ORDER BY sci.updated_at DESC, sci.id DESC
    LIMIT 10
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
  .super-dashboard-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.9fr);
    gap: 1rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border-radius: 28px;
    border: 1px solid var(--clr-border);
    background:
      radial-gradient(circle at top left, rgba(245, 158, 11, 0.12), transparent 34%),
      radial-gradient(circle at top right, rgba(14, 165, 233, 0.12), transparent 30%),
      linear-gradient(180deg, var(--clr-surface) 0%, color-mix(in srgb, var(--clr-surface) 86%, var(--clr-surface2)) 100%);
    box-shadow: var(--shadow-sm);
  }

  .super-dashboard-hero-copy {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1rem;
    min-width: 0;
  }

  .super-dashboard-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    width: fit-content;
    padding: 0.4rem 0.7rem;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.12);
    color: var(--clr-accent);
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .super-dashboard-hero h1 {
    margin: 0.55rem 0 0.75rem;
    font-size: clamp(1.8rem, 2.6vw, 2.6rem);
    line-height: 1.05;
    letter-spacing: -0.04em;
  }

  .super-dashboard-hero p {
    max-width: 68ch;
    color: var(--clr-text-muted);
    margin: 0;
  }

  .super-dashboard-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.55rem;
  }

  .super-dashboard-hero-actions .btn {
    min-height: 40px;
  }

  .super-dashboard-status-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    align-content: start;
  }

  .super-dashboard-status-card {
    padding: 1rem;
    border-radius: 20px;
    border: 1px solid var(--clr-border);
    background: color-mix(in srgb, var(--clr-surface) 88%, var(--clr-surface2));
    min-height: 104px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 0.5rem;
  }

  .super-dashboard-status-card span {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--clr-text-muted);
  }

  .super-dashboard-status-card strong {
    font-size: 1.15rem;
    line-height: 1.1;
  }

  .super-dashboard-status-list {
    margin-top: 0.8rem;
    display: grid;
    gap: 0.7rem;
  }

  .super-dashboard-status-item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.85rem 0.95rem;
    border-radius: 18px;
    border: 1px solid var(--clr-border);
    background: rgba(255,255,255,0.03);
  }

  .super-dashboard-status-dot {
    width: 11px;
    height: 11px;
    border-radius: 999px;
    flex: none;
    box-shadow: 0 0 0 4px rgba(255,255,255,0.03);
  }

  .super-dashboard-status-item strong {
    display: block;
    font-size: 0.88rem;
  }

  .super-dashboard-status-item span {
    display: block;
    font-size: 0.74rem;
    color: var(--clr-text-muted);
    margin-top: 0.1rem;
  }

  @media (max-width: 768px) {
    .super-dashboard-hero {
      grid-template-columns: 1fr;
      padding: 1.1rem;
      border-radius: 24px;
    }

    .super-dashboard-status-grid {
      grid-template-columns: 1fr 1fr;
    }

    .super-dashboard-status-card {
      min-height: 92px;
      padding: 0.9rem;
    }

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

  @media (max-width: 480px) {
    .super-dashboard-status-grid {
      grid-template-columns: 1fr;
    }

    .super-dashboard-hero h1 {
      font-size: 1.65rem;
    }
  }
</style>

<div class="container dashboard-content-frame" style="padding-top:2rem;padding-bottom:3rem;">
  <section class="super-dashboard-hero" aria-label="Super admin overview">
    <div class="super-dashboard-hero-copy">
      <div>
        <div class="super-dashboard-kicker"><?= admin_icon_svg('shield') ?> Super Admin Control Room</div>
        <h1>Manage the full Uthenga platform from one polished command center.</h1>
        <p>
          Monitor revenue, bookings, users, support, and shop operations across the platform. This view is built for fast decisions, cleaner oversight, and a smoother day-to-day admin workflow.
        </p>
      </div>

      <div class="super-dashboard-hero-actions">
        <a href="<?= BASE_URL ?>admin/analytics.php" class="btn btn-primary btn-sm">Platform Analytics</a>
        <a href="<?= BASE_URL ?>admin/system-monitor.php" class="btn btn-secondary btn-sm">System Monitor</a>
        <a href="<?= BASE_URL ?>admin/audit-logs.php" class="btn btn-secondary btn-sm">Audit Logs</a>
        <a href="<?= BASE_URL ?>admin/shop.php" class="btn btn-secondary btn-sm">Global Shop Management</a>
      </div>

      <div class="super-dashboard-status-list">
        <div class="super-dashboard-status-item">
          <span class="super-dashboard-status-dot" style="background:#10b981;"></span>
          <div>
            <strong>Database online</strong>
            <span>Core data services are reachable.</span>
          </div>
        </div>
        <div class="super-dashboard-status-item">
          <span class="super-dashboard-status-dot" style="background:#38bdf8;"></span>
          <div>
            <strong>Sessions active</strong>
            <span><?= e($healthStatus['sessions']) ?></span>
          </div>
        </div>
        <div class="super-dashboard-status-item">
          <span class="super-dashboard-status-dot" style="background:#f59e0b;"></span>
          <div>
            <strong>Platform core</strong>
            <span><?= e($healthStatus['system']) ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="super-dashboard-status-grid">
      <div class="super-dashboard-status-card">
        <span>Total Users</span>
        <strong><?= number_format((int) $counts['users']) ?></strong>
      </div>
      <div class="super-dashboard-status-card">
        <span>Registered Vendors</span>
        <strong><?= number_format((int) $counts['vendors']) ?></strong>
      </div>
      <div class="super-dashboard-status-card">
        <span>Total Bookings</span>
        <strong><?= number_format((int) $counts['bookings']) ?></strong>
      </div>
      <div class="super-dashboard-status-card">
        <span>Gross Revenue</span>
        <strong><?= formatMWK((float) ($counts['revenue']['total'] ?? 0)) ?></strong>
      </div>
    </div>
  </section>

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
      <div class="presentation-stat"><span>Active Carts</span><strong><?= number_format((int) ($counts['shopCarts']['total'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Cart Items</span><strong><?= number_format((int) $counts['shopCartItems']) ?></strong></div>
      <div class="presentation-stat"><span>Delivery Partners</span><strong><?= number_format((int) (uthenga_table_exists('delivery_riders') ? dbCount('SELECT COUNT(*) FROM delivery_riders') : 0)) ?></strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1.5rem;">
    <div class="section-head">
      <div>
        <h3>Recent Cart Activity</h3>
        <p class="text-xs text-muted">Live cart rows synced from session and signed-in customer carts.</p>
      </div>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Cart Owner</th>
            <th>Product</th>
            <th>Qty</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentCartItems)): ?>
            <tr><td colspan="4" class="text-muted">No active carts found.</td></tr>
          <?php else: ?>
            <?php foreach ($recentCartItems as $cartRow): ?>
              <?php
                $cartOwner = !empty($cartRow['customer_name'])
                    ? (string) $cartRow['customer_name']
                    : ('Session ' . substr((string) ($cartRow['session_token'] ?? ''), 0, 10));
              ?>
              <tr>
                <td><?= e($cartOwner) ?><br><span class="text-xs text-muted"><?= e($cartRow['customer_email'] ?? $cartRow['session_token'] ?? 'N/A') ?></span></td>
                <td><?= e($cartRow['product_name'] ?? 'Unknown product') ?><br><span class="text-xs text-muted"><?= e($cartRow['product_sku'] ?? '') ?></span></td>
                <td><?= number_format((int) ($cartRow['quantity'] ?? 0)) ?></td>
                <td class="text-xs text-muted"><?= e((string) ($cartRow['updated_at'] ?? $cartRow['created_at'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
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
                  <td>
                    <span class="badge <?= superDashboardBadgeClass((string) ($row['booking_status'] ?? '')) ?>"><?= e(superDashboardStatusLabel((string) ($row['booking_status'] ?? ''))) ?></span>
                    <div class="text-xs text-muted" style="margin-top:.3rem;line-height:1.35;"><?= e(superDashboardStatusHint((string) ($row['booking_status'] ?? ''))) ?></div>
                  </td>
                  <td>
                    <span class="badge <?= superDashboardBadgeClass((string) ($row['payment_status'] ?? '')) ?>"><?= e(superDashboardStatusLabel((string) ($row['payment_status'] ?? ''))) ?></span>
                    <div class="text-xs text-muted" style="margin-top:.3rem;line-height:1.35;"><?= e(superDashboardStatusHint((string) ($row['payment_status'] ?? ''))) ?></div>
                  </td>
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
                  <td>
                    <span class="badge <?= superDashboardBadgeClass((string) ($ticket['status'] ?? '')) ?>"><?= e(superDashboardStatusLabel((string) ($ticket['status'] ?? ''))) ?></span>
                    <div class="text-xs text-muted" style="margin-top:.3rem;line-height:1.35;"><?= e(superDashboardStatusHint((string) ($ticket['status'] ?? ''))) ?></div>
                  </td>
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
                  <td>
                    <span class="badge <?= superDashboardBadgeClass((string) ($row['account_status'] ?? '')) ?>"><?= e(superDashboardStatusLabel((string) ($row['account_status'] ?? ''))) ?></span>
                    <div class="text-xs text-muted" style="margin-top:.3rem;line-height:1.35;"><?= e(superDashboardStatusHint((string) ($row['account_status'] ?? ''))) ?></div>
                  </td>
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
