<?php
/**
 * Uthenga - Vendor Portal
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireApprovedVendor();

$pageTitle = 'Vendor Dashboard';
$activeNav = 'vendor';
$vendorId = (int) ($_SESSION['user_id'] ?? 0);
$vendor = dbQueryOne('SELECT * FROM users WHERE id = ?', [$vendorId]) ?: [];
$hasBookingItems = uthenga_table_exists('booking_items');
$activeTab = $_GET['tab'] ?? 'overview';

$allItems = array_merge(
    array_filter(marketplace_fetch_events('', 0, false), function ($row) use ($vendorId) {
        return (int) ($row['vendor_id'] ?? 0) === $vendorId;
    }),
    array_filter(marketplace_fetch_properties('', 0, false), function ($row) use ($vendorId) {
        return (int) ($row['vendor_id'] ?? 0) === $vendorId;
    }),
    array_filter(marketplace_fetch_tours('', 0, false), function ($row) use ($vendorId) {
        return (int) ($row['vendor_id'] ?? 0) === $vendorId;
    }),
    array_filter(marketplace_fetch_transport_routes('', 0, false), function ($row) use ($vendorId) {
        return (int) ($row['vendor_id'] ?? 0) === $vendorId;
    })
);

usort($allItems, function ($a, $b) {
    $aTime = strtotime($a['created_at'] ?? '') ?: 0;
    $bTime = strtotime($b['created_at'] ?? '') ?: 0;
    if ($aTime === $bTime) {
        return 0;
    }
    return $aTime > $bTime ? -1 : 1;
});

$bookingRows = $hasBookingItems ? dbQuery("
    SELECT
        b.id,
        b.booking_code,
        b.booking_status,
        b.payment_status,
        b.grand_total,
        b.created_at,
        bi.item_type,
        bi.item_name,
        bi.reference_id
    FROM booking_items bi
    JOIN bookings b ON b.id = bi.booking_id
    WHERE bi.vendor_id = ?
    ORDER BY b.created_at DESC, bi.id DESC
", [$vendorId]) : [];

$bookingStats = $hasBookingItems ? dbQueryOne("
    SELECT
        COUNT(*) AS total_bookings,
        COALESCE(SUM(b.grand_total), 0) AS total_revenue
    FROM booking_items bi
    JOIN bookings b ON b.id = bi.booking_id
    WHERE bi.vendor_id = ?
", [$vendorId]) : ['total_bookings' => 0, 'total_revenue' => 0];

$typeCounts = ['event' => 0, 'property' => 0, 'tour' => 0, 'transport' => 0];
foreach ($allItems as $row) {
    $type = $row['type'] ?? 'event';
    if (!isset($typeCounts[$type])) {
        $typeCounts[$type] = 0;
    }
    $typeCounts[$type]++;
}

require_once __DIR__ . '/../includes/dashboard_shell.php';
renderDashboardChromeStart([
    'role' => currentRole(),
    'title' => $pageTitle,
    'active' => $activeTab === 'overview' ? 'vendor/dashboard.php' : 'vendor/dashboard.php?tab=' . $activeTab,
    'search' => false,
    'status' => 'Approved Vendor',
]);
?>

<div class="container dashboard-content-frame" style="padding-top:2.25rem;padding-bottom:3rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title">Vendor Dashboard</h1>
      <p class="text-muted"><?= e($vendor['full_name'] ?? '') ?> - manage your events, stays, tours, and transport from one place.</p>
    </div>
    <div style="text-align:right;">
      <div class="text-xs text-muted">Wallet balance</div>
      <div style="font-size:1.4rem;font-weight:800;color:var(--clr-accent);"><?= formatMWK((float) ($vendor['balance'] ?? 0)) ?></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1rem;">
    <div class="presentation-grid">
      <div class="presentation-stat"><span>Items</span><strong><?= number_format(count($allItems)) ?></strong></div>
      <div class="presentation-stat"><span>Bookings</span><strong><?= number_format((int) ($bookingStats['total_bookings'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Gross revenue</span><strong><?= formatMWK((float) ($bookingStats['total_revenue'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Events / Stays / Tours / Transport</span><strong><?= number_format(array_sum($typeCounts)) ?></strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1rem;">
    <div class="page-header" style="margin-bottom:1rem;">
      <div>
        <h2 class="page-title" style="font-size:1.4rem;">Your Inventory</h2>
        <p class="text-muted">This now reads directly from module tables, not a legacy listings table.</p>
      </div>
    </div>

    <?php if (empty($allItems)): ?>
      <div class="text-muted">No inventory yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Type</th>
              <th>Title</th>
              <th>Location</th>
              <th>Price</th>
              <th>Published</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allItems as $item): ?>
              <tr>
                <td><span class="badge <?= e($item['badge_class'] ?? '') ?>"><?= e($item['type_label'] ?? ucfirst($item['type'] ?? 'Item')) ?></span></td>
                <td><?= e($item['title'] ?? '') ?></td>
                <td><?= e($item['location'] ?? '') ?></td>
                <td><?= e($item['price_label'] ?? '') ?></td>
                <td><?= e($item['created_at'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="glass-panel" style="padding:1.25rem;">
    <div class="page-header" style="margin-bottom:1rem;">
      <div>
        <h2 class="page-title" style="font-size:1.4rem;">Recent Bookings</h2>
        <p class="text-muted">Bookings are now tied to booking items and vendor ownership.</p>
      </div>
    </div>

    <?php if (empty($bookingRows)): ?>
      <div class="text-muted">No bookings yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Booking</th>
              <th>Item</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($bookingRows, 0, 10) as $row): ?>
              <tr>
                <td><?= e($row['booking_code'] ?? '') ?></td>
                <td><?= e(ucfirst(str_replace('_', ' ', $row['item_type'] ?? ''))) ?>: <?= e($row['item_name'] ?? '') ?></td>
                <td><?= e($row['booking_status'] ?? '') ?></td>
                <td><?= e($row['payment_status'] ?? '') ?></td>
                <td><?= formatMWK((float) ($row['grand_total'] ?? 0)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
