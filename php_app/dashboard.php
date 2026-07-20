<?php
/**
 * Uthenga - Customer Dashboard
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

requireCustomer();

$pageTitle = 'My Dashboard';
$activeNav = 'home';

$userId = (int)($_SESSION['user_id'] ?? 0);
$user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]) ?: [];
$activeTab = $_GET['tab'] ?? 'overview';
$hasBookingItems = uthenga_table_exists('booking_items');
$hasSupportTickets = uthenga_table_exists('support_tickets');
$hasLoyaltyTransactions = uthenga_table_exists('loyalty_transactions');

if ($hasBookingItems) {
    $bookingItemJoin = "
     LEFT JOIN (
         SELECT booking_id,
                SUBSTRING_INDEX(GROUP_CONCAT(item_name ORDER BY id SEPARATOR '||'), '||', 1) AS item_name,
                SUBSTRING_INDEX(GROUP_CONCAT(item_type ORDER BY id SEPARATOR '||'), '||', 1) AS item_type
         FROM booking_items
         GROUP BY booking_id
     ) ref ON ref.booking_id = b.id";
} else {
    $bookingItemJoin = '';
}

$recentBookings = $hasBookingItems
    ? dbQuery(
        "SELECT b.*, COALESCE(ref.item_name, b.reference_name, b.booking_code) AS booking_title,
                COALESCE(ref.item_type, 'booking') AS booking_type
         FROM bookings b
         {$bookingItemJoin}
         WHERE b.customer_id = ?
         ORDER BY b.created_at DESC LIMIT 5",
        [$userId]
    )
    : dbQuery(
        "SELECT b.*, COALESCE(b.reference_name, b.booking_code) AS booking_title,
                'booking' AS booking_type
         FROM bookings b
         WHERE b.customer_id = ?
         ORDER BY b.created_at DESC LIMIT 5",
        [$userId]
    );

$allBookings = $hasBookingItems
    ? dbQuery(
        "SELECT b.*, COALESCE(ref.item_name, b.reference_name, b.booking_code) AS booking_title,
                COALESCE(ref.item_type, 'booking') AS booking_type
         FROM bookings b
         {$bookingItemJoin}
         WHERE b.customer_id = ?
         ORDER BY b.created_at DESC",
        [$userId]
    )
    : dbQuery(
        "SELECT b.*, COALESCE(b.reference_name, b.booking_code) AS booking_title,
                'booking' AS booking_type
         FROM bookings b
         WHERE b.customer_id = ?
         ORDER BY b.created_at DESC",
        [$userId]
    );

$supportTickets = $hasSupportTickets
    ? dbQuery(
        'SELECT * FROM support_tickets WHERE requester_user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC',
        [$userId]
    )
    : [];
$wishlist = marketplace_fetch_favorites($userId);
$payments = $hasBookingItems
    ? dbQuery(
        "SELECT t.*, COALESCE(t.gateway_name, t.gateway, 'N/A') AS gateway_label,
                t.transaction_reference AS receipt_number,
                b.reference_name AS booking_title, COALESCE(ref.item_type, 'booking') AS booking_type
         FROM transactions t
         JOIN bookings b ON b.id = t.booking_id
         LEFT JOIN (
             SELECT booking_id,
                    SUBSTRING_INDEX(GROUP_CONCAT(item_type ORDER BY id SEPARATOR '||'), '||', 1) AS item_type
             FROM booking_items
             GROUP BY booking_id
         ) ref ON ref.booking_id = b.id
        WHERE t.user_id = ? AND LOWER(t.status) IN ('success','paid') ORDER BY t.created_at DESC",
        [$userId]
    )
    : dbQuery(
        "SELECT t.*, COALESCE(t.gateway_name, t.gateway, 'N/A') AS gateway_label,
                t.transaction_reference AS receipt_number,
                b.reference_name AS booking_title, 'booking' AS booking_type
         FROM transactions t
         JOIN bookings b ON b.id = t.booking_id
        WHERE t.user_id = ? AND LOWER(t.status) IN ('success','paid') ORDER BY t.created_at DESC",
        [$userId]
    );

$totalSpent = (float)(dbQueryOne(
    "SELECT COALESCE(SUM(grand_total),0) AS t FROM bookings WHERE customer_id = ? AND LOWER(payment_status) IN ('paid','success','authorized','partially_paid')",
    [$userId]
)['t'] ?? 0);

// Loyalty points balance
$loyaltyPoints = $hasLoyaltyTransactions ? (int)(dbQueryOne(
    'SELECT COALESCE(SUM(points), 0) AS bal FROM loyalty_transactions WHERE user_id = ?',
    [$userId]
)['bal'] ?? $user['loyalty_points'] ?? 0) : (int)($user['loyalty_points'] ?? 0);

// Loyalty points history
$loyaltyHistory = $hasLoyaltyTransactions
    ? dbQuery(
        'SELECT * FROM loyalty_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
        [$userId]
    )
    : [];

// Recently viewed items
$recentlyViewed = [];
if (uthenga_table_exists('recent_views')) {
    if (uthenga_column_exists('recent_views', 'view_type')) {
        $recentlyViewed = dbQuery(
            "SELECT rv.*, rv.view_type AS item_type, rv.reference_id AS item_id, rv.viewed_at
             FROM recent_views rv
             WHERE rv.user_id = ? ORDER BY rv.viewed_at DESC LIMIT 20",
            [$userId]
        );
    } else {
        $recentlyViewed = dbQuery(
            "SELECT rv.*, rv.item_type, rv.item_id, rv.viewed_at
             FROM recent_views rv
             WHERE rv.user_id = ? ORDER BY rv.viewed_at DESC LIMIT 20",
            [$userId]
        );
    }
}

function customerBadgeClass(string $status): string {
    $status = strtolower($status);
    if (in_array($status, ['confirmed', 'resolved', 'closed', 'paid', 'success', 'completed'], true)) {
        return 'badge-approved';
    }
    if (in_array($status, ['cancelled', 'failed'], true)) {
        return 'badge-rejected';
    }
    return 'badge-pending';
}

require_once __DIR__ . '/includes/dashboard_shell.php';
renderDashboardChromeStart([
    'role' => ROLE_CUSTOMER,
    'title' => $pageTitle,
    'active' => $activeTab === 'overview' ? 'dashboard.php' : 'dashboard.php?tab=' . $activeTab,
    'search' => false,
    'status' => 'Customer Account',
]);
?>

<div class="container dashboard-content-frame" style="padding-top:2.25rem;padding-bottom:3rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title">Welcome back, <?= e(explode(' ', (string)($user['name'] ?? ''))[0] ?: 'Customer') ?></h1>
      <p class="text-muted">Keep your bookings, tickets, favorites, payments, and profile in one place.</p>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>events.php" class="btn btn-primary">Browse Events</a>
      <a href="<?= BASE_URL ?>profile.php" class="btn btn-secondary">Edit Profile</a>
    </div>
  </div>

  <div class="grid grid-cols-4 gap-3" style="margin-bottom:1.75rem;">
    <div class="stat-card">
      <div class="stat-icon stat-icon-blue"></div>
      <div>
        <div class="stat-value"><?= count($allBookings) ?></div>
        <div class="stat-label">Total Bookings</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-yellow"></div>
      <div>
        <div class="stat-value"><?= count($supportTickets) ?></div>
        <div class="stat-label">My Tickets</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-green"></div>
      <div>
        <div class="stat-value"><?= count($wishlist) ?></div>
        <div class="stat-label">Favorites</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon stat-icon-purple"></div>
      <div>
        <div class="stat-value"><?= formatMWK($totalSpent) ?></div>
        <div class="stat-label">Total Spent</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#f97316);border-radius:50%;width:2.25rem;height:2.25rem;"></div>
      <div>
        <div class="stat-value" style="color:#f59e0b;"><?= number_format($loyaltyPoints) ?></div>
        <div class="stat-label">⭐ Loyalty Points</div>
      </div>
    </div>
  </div>

  <div class="filter-tabs" style="margin-bottom:1.5rem;">
    <a href="?tab=overview"  class="filter-tab <?= $activeTab === 'overview'  ? 'active' : '' ?>">Overview</a>
    <a href="?tab=bookings"  class="filter-tab <?= $activeTab === 'bookings'  ? 'active' : '' ?>">My Bookings</a>
    <a href="?tab=tickets"   class="filter-tab <?= $activeTab === 'tickets'   ? 'active' : '' ?>">My Tickets</a>
    <a href="?tab=favorites" class="filter-tab <?= $activeTab === 'favorites' ? 'active' : '' ?>">Favorites</a>
    <a href="?tab=payments"  class="filter-tab <?= $activeTab === 'payments'  ? 'active' : '' ?>">Payments</a>
    <a href="?tab=viewed"    class="filter-tab <?= $activeTab === 'viewed'    ? 'active' : '' ?>">Recently Viewed</a>
    <a href="?tab=loyalty"   class="filter-tab <?= $activeTab === 'loyalty'   ? 'active' : '' ?>">⭐ Loyalty</a>
    <a href="?tab=profile"   class="filter-tab <?= $activeTab === 'profile'   ? 'active' : '' ?>">Profile</a>
  </div>

  <?php if ($activeTab === 'overview'): ?>
    <div class="grid grid-cols-2 gap-3">
      <div class="glass-panel" style="padding:1.5rem;">
        <div class="section-head">
          <div>
            <h3>Recent bookings</h3>
            <p class="text-sm text-muted">Your latest activity at a glance.</p>
          </div>
          <a href="?tab=bookings" class="btn btn-secondary btn-sm">View all</a>
        </div>

        <?php if (empty($recentBookings)): ?>
          <div class="card" style="padding:1.25rem;text-align:center;">
            <p>No bookings yet.</p>
            <a href="<?= BASE_URL ?>events.php" class="btn btn-primary btn-sm" style="margin-top:0.75rem;">Browse marketplace</a>
          </div>
        <?php else: ?>
          <div style="display:grid;gap:0.75rem;">
            <?php foreach ($recentBookings as $booking): ?>
              <div class="card" style="padding:1rem;display:flex;justify-content:space-between;gap:1rem;align-items:center;">
                <div style="min-width:0;">
                  <div style="font-weight:700;"><?= e($booking['booking_title']) ?></div>
                  <div class="text-xs text-muted"><?= e($booking['id']) ?> · <?= e(ucfirst($booking['booking_type'])) ?></div>
                </div>
                <div style="text-align:right;">
                  <div style="font-weight:700;"><?= formatMWK((float)$booking['grand_total']) ?></div>
                  <span class="status-badge <?= customerBadgeClass($booking['booking_status']) ?>"><?= e(ucfirst($booking['booking_status'])) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div style="display:grid;gap:1rem;">
        <div class="glass-panel" style="padding:1.5rem;">
          <div class="section-head">
            <div>
              <h3>Quick actions</h3>
              <p class="text-sm text-muted">Fast access to common tasks.</p>
            </div>
          </div>
          <div style="display:grid;gap:0.75rem;">
            <a href="<?= BASE_URL ?>events.php" class="btn btn-secondary">Browse events</a>
            <a href="<?= BASE_URL ?>hotels.php" class="btn btn-secondary">Find a stay</a>
            <a href="<?= BASE_URL ?>transport.php" class="btn btn-secondary">Book transport</a>
            <a href="<?= BASE_URL ?>support.php" class="btn btn-secondary">Open support ticket</a>
          </div>
        </div>

        <div class="glass-panel" style="padding:1.5rem;">
          <div class="section-head">
            <div>
              <h3>Profile snapshot</h3>
              <p class="text-sm text-muted">Account details and status.</p>
            </div>
          </div>
          <div style="display:grid;gap:0.6rem;">
            <div class="flex justify-between gap-2"><span class="text-muted">Name</span><strong><?= e($user['name'] ?? '') ?></strong></div>
            <div class="flex justify-between gap-2"><span class="text-muted">Email</span><strong><?= e($user['email'] ?? '') ?></strong></div>
            <div class="flex justify-between gap-2"><span class="text-muted">Wallet</span><strong><?= formatMWK((float)($user['balance'] ?? 0)) ?></strong></div>
            <div class="flex justify-between gap-2"><span class="text-muted">Member since</span><strong><?= e($user['created_at'] ?? $user['joined_date'] ?? 'N/A') ?></strong></div>
          </div>
        </div>
      </div>
    </div>
  <?php elseif ($activeTab === 'bookings'): ?>
    <div class="glass-panel" style="padding:1.5rem;">
      <div class="section-head">
        <div>
          <h3>My bookings</h3>
          <p class="text-sm text-muted">All bookings in one clean table.</p>
        </div>
      </div>
      <?php if (empty($allBookings)): ?>
        <div class="card" style="padding:1.5rem;text-align:center;">
          <p>No bookings found.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Booking</th>
                <th>Type</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allBookings as $booking): ?>
                <tr>
                  <td>
                    <div style="font-weight:700;"><?= e($booking['booking_title']) ?></div>
                    <div class="text-xs text-muted"><?= e($booking['id']) ?></div>
                  </td>
                  <td><?= e(ucfirst($booking['booking_type'])) ?></td>
                  <td class="text-xs text-muted"><?= e($booking['created_at']) ?></td>
                  <td style="font-weight:700;"><?= formatMWK((float)$booking['total_price']) ?></td>
                  <td><span class="status-badge <?= customerBadgeClass($booking['booking_status']) ?>"><?= e(ucfirst($booking['booking_status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php elseif ($activeTab === 'tickets'): ?>
    <div class="glass-panel" style="padding:1.5rem;">
      <div class="section-head">
        <div>
          <h3>My tickets</h3>
          <p class="text-sm text-muted">Support requests and their current status.</p>
        </div>
      </div>
      <?php if (empty($supportTickets)): ?>
        <div class="card" style="padding:1.5rem;text-align:center;">
          <p>No support tickets yet.</p>
        </div>
      <?php else: ?>
        <div style="display:grid;gap:0.75rem;">
          <?php foreach ($supportTickets as $ticket): ?>
            <div class="card" style="padding:1rem;display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;">
              <div>
                <div style="font-weight:700;"><?= e($ticket['subject']) ?></div>
                <div class="text-xs text-muted"><?= e($ticket['ticket_code']) ?> · <?= e($ticket['category']) ?></div>
              </div>
              <span class="badge <?= customerBadgeClass($ticket['status']) ?>"><?= e(ucfirst($ticket['status'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php elseif ($activeTab === 'favorites'): ?>
    <div class="glass-panel" style="padding:1.5rem;">
      <div class="section-head">
        <div>
          <h3>Favorites</h3>
          <p class="text-sm text-muted">Saved listings you can revisit later.</p>
        </div>
      </div>
      <?php if (empty($wishlist)): ?>
        <div class="card" style="padding:1.5rem;text-align:center;">
          <p>No favorites saved yet.</p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-3 gap-3">
          <?php foreach ($wishlist as $listing): ?>
            <article class="card">
              <div class="card-img-wrap">
                <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
              </div>
              <div class="card-body">
                <div class="card-title"><?= e($listing['title']) ?></div>
                <div class="card-loc"><?= e($listing['location']) ?></div>
                <div class="card-price"><?= e(marketplace_price_label($listing)) ?></div>
              </div>
              <div class="card-footer">
                <a href="<?= BASE_URL ?>event-details.php?id=<?= e($listing['id']) ?>" class="btn btn-secondary btn-sm" style="width:100%;">View Details</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php elseif ($activeTab === 'payments'): ?>
    <div class="glass-panel" style="padding:1.5rem;">
      <div class="section-head">
        <div>
          <h3>Payments</h3>
          <p class="text-sm text-muted">Your payment history and receipts.</p>
        </div>
      </div>
      <?php if (empty($payments)): ?>
        <div class="card" style="padding:1.5rem;text-align:center;">
          <p>No payments recorded yet.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Listing</th>
                <th>Gateway</th>
                <th>Receipt</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $payment): ?>
                <tr>
                  <td class="text-xs text-muted"><?= e($payment['created_at']) ?></td>
                  <td>
                    <div style="font-weight:700;"><?= e($payment['booking_title']) ?></div>
                    <div class="text-xs text-muted"><?= e(ucfirst($payment['booking_type'])) ?></div>
                  </td>
                  <td><?= e($payment['gateway_label']) ?></td>
                  <td class="text-xs font-mono"><?= e($payment['receipt_number']) ?></td>
                  <td style="font-weight:700;"><?= formatMWK((float)$payment['amount']) ?></td>
                  <td><span class="badge <?= customerBadgeClass($payment['status']) ?>"><?= e($payment['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="card" style="padding:1rem;margin-top:1rem;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
          <span class="text-muted">Total spent</span>
          <strong><?= formatMWK($totalSpent) ?></strong>
        </div>
      <?php endif; ?>
    </div>
  <?php elseif ($activeTab === 'viewed'): ?>
    <div class="glass-panel" style="padding:1.5rem;">
      <div class="section-head">
        <div>
          <h3>Recently Viewed</h3>
          <p class="text-sm text-muted">Items you've browsed lately.</p>
        </div>
      </div>
      <?php if (empty($recentlyViewed)): ?>
        <div class="card" style="padding:1.5rem;text-align:center;"><p>No recently viewed items yet. Start browsing!</p></div>
      <?php else: ?>
        <div style="display:grid;gap:.75rem;">
          <?php foreach ($recentlyViewed as $rv):
            $icon = ['event'=>'🎉','hotel'=>'🏨','tour'=>'🧭','transport'=>'🚌'][$rv['item_type']] ?? '📍';
          ?>
          <div class="card" style="padding:1rem;display:flex;justify-content:space-between;gap:1rem;align-items:center;">
            <div style="display:flex;gap:.75rem;align-items:center;">
              <span style="font-size:1.5rem;"><?= $icon ?></span>
              <div>
                <div style="font-weight:700;"><?= e($rv['item_title'] ?? $rv['item_id']) ?></div>
                <div class="text-xs text-muted"><?= ucfirst(e($rv['item_type'])) ?> &middot; Viewed <?= e(date('M j, Y', strtotime($rv['viewed_at']))) ?></div>
              </div>
            </div>
            <a href="<?= BASE_URL ?>event-details.php?id=<?= urlencode($rv['item_id']) ?>" class="btn btn-secondary btn-sm">View Again</a>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  <?php elseif ($activeTab === 'loyalty'): ?>
    <div class="grid grid-cols-2 gap-3">
      <div class="glass-panel" style="padding:1.5rem;">
        <div class="section-head">
          <div><h3>⭐ Loyalty Points</h3><p class="text-sm text-muted">Earn points with every booking.</p></div>
        </div>
        <!-- Points balance card -->
        <div style="background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:var(--radius-md);padding:2rem;text-align:center;margin-bottom:1.5rem;border:1px solid rgba(245,158,11,.25);">
          <div style="font-size:2.5rem;font-weight:800;color:#f59e0b;"><?= number_format($loyaltyPoints) ?></div>
          <div style="color:rgba(255,255,255,.6);font-size:.85rem;margin:.25rem 0;">Points Balance</div>
          <div style="font-size:.75rem;color:rgba(255,255,255,.4);">Earn 1 point per MK100 spent</div>
        </div>
        <!-- Redeem notice -->
        <div style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius-sm);padding:1rem;font-size:.82rem;margin-bottom:1rem;">
          💡 <strong>Redeeming soon!</strong> Points redemption at checkout is coming. Watch this space.
        </div>
        <a href="<?= BASE_URL ?>events.php" class="btn btn-primary" style="width:100%;text-align:center;display:block;">
          Earn More Points — Browse Events
        </a>
      </div>

      <div class="glass-panel" style="padding:1.5rem;">
        <div class="section-head">
          <div><h3>Points History</h3><p class="text-sm text-muted">All your earned and spent points.</p></div>
        </div>
        <?php if (empty($loyaltyHistory)): ?>
          <div class="card" style="padding:1.25rem;text-align:center;"><p>No points transactions yet.</p></div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="admin-table">
              <thead><tr><th>Date</th><th>Description</th><th>Points</th></tr></thead>
              <tbody>
                <?php foreach ($loyaltyHistory as $lp): ?>
                <tr>
                  <td class="text-xs text-muted"><?= e(date('M j, Y', strtotime($lp['created_at']))) ?></td>
                  <td><?= e($lp['description'] ?? ucfirst($lp['reason'])) ?></td>
                  <td style="font-weight:700;color:<?= $lp['points'] >= 0 ? '#10b981' : '#ef4444' ?>;">
                    <?= $lp['points'] >= 0 ? '+' : '' ?><?= number_format($lp['points']) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  <?php elseif ($activeTab === 'profile'): ?>
    <div class="grid grid-cols-2 gap-3">
      <div class="glass-panel" style="padding:1.5rem;">
        <div class="section-head">
          <div>
            <h3>Profile</h3>
            <p class="text-sm text-muted">Your account information.</p>
          </div>
        </div>
        <div style="display:grid;gap:0.75rem;">
          <div class="card" style="padding:1rem;">
            <div class="text-xs text-muted">Full name</div>
            <div style="font-weight:700;"><?= e($user['name'] ?? '') ?></div>
          </div>
          <div class="card" style="padding:1rem;">
            <div class="text-xs text-muted">Email address</div>
            <div style="font-weight:700;"><?= e($user['email'] ?? '') ?></div>
          </div>
          <div class="card" style="padding:1rem;">
            <div class="text-xs text-muted">Account type</div>
            <div style="font-weight:700;"><?= e($user['role'] ?? 'Customer') ?></div>
          </div>
        </div>
      </div>

      <div class="glass-panel" style="padding:1.5rem;">
        <div class="section-head">
          <div>
            <h3>Account actions</h3>
            <p class="text-sm text-muted">Manage access and preferences.</p>
          </div>
        </div>
        <div style="display:grid;gap:0.75rem;">
          <a href="<?= BASE_URL ?>profile.php" class="btn btn-secondary">Edit profile</a>
          <a href="<?= BASE_URL ?>change_password.php" class="btn btn-secondary">Change password</a>
          <a href="<?= BASE_URL ?>logout.php" class="btn btn-danger">Sign out</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php renderDashboardChromeEnd(); ?>
