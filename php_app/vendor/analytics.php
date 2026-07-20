<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/restoration_helpers.php';

requireApprovedVendor();
$stats = [
    'listings' => dbCount('SELECT COUNT(*) FROM listings WHERE vendor_id = ?', [$_SESSION['user_id']]),
    'bookings' => dbCount('SELECT COUNT(*) FROM bookings b JOIN listings l ON l.id = b.listing_id WHERE l.vendor_id = ?', [$_SESSION['user_id']]),
    'revenue' => (float)(dbQueryOne('SELECT COALESCE(SUM(b.total_price),0) AS revenue FROM bookings b JOIN listings l ON l.id = b.listing_id WHERE l.vendor_id = ? AND LOWER(b.payment_status) = "paid"', [$_SESSION['user_id']])['revenue'] ?? 0),
];
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section" style="padding-top:3rem;"><div class="container">
  <div class="card" style="padding:1.5rem;"><h1>Vendor Analytics</h1>
    <div class="grid grid-cols-3 gap-2" style="margin-top:1rem;">
      <div class="stat-card"><div class="stat-icon stat-icon-blue"><?= dashboard_icon_svg('store') ?></div><div><div class="stat-value"><?= number_format($stats['listings']) ?></div><div class="stat-label">Listings</div></div></div>
      <div class="stat-card"><div class="stat-icon stat-icon-green"><?= dashboard_icon_svg('calendar') ?></div><div><div class="stat-value"><?= number_format($stats['bookings']) ?></div><div class="stat-label">Bookings</div></div></div>
      <div class="stat-card"><div class="stat-icon stat-icon-yellow"><?= dashboard_icon_svg('wallet') ?></div><div><div class="stat-value"><?= formatMWK($stats['revenue']) ?></div><div class="stat-label">Revenue</div></div></div>
    </div>
  </div>
</div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
