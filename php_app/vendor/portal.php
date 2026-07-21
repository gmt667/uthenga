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

$vendorRecord = dbQueryOne('SELECT * FROM vendors WHERE user_id = ? LIMIT 1', [$vendorId]) ?: [];
$vendorWallet = !empty($vendorRecord['id']) && uthenga_table_exists('vendor_wallets')
    ? (dbQueryOne('SELECT * FROM vendor_wallets WHERE vendor_id = ? LIMIT 1', [(int)$vendorRecord['id']]) ?: ['balance' => 0, 'pending_balance' => 0])
    : ['balance' => 0, 'pending_balance' => 0];
$vendorCommissionTotals = !empty($vendorRecord['id']) && uthenga_table_exists('commissions')
    ? (dbQueryOne('SELECT COALESCE(SUM(commission_amount), 0) AS commission_total, COALESCE(SUM(net_vendor_amount), 0) AS vendor_total FROM commissions WHERE vendor_id = ?', [(int)$vendorRecord['id']]) ?: ['commission_total' => 0, 'vendor_total' => 0])
    : ['commission_total' => 0, 'vendor_total' => 0];
$vendorPayoutTotals = !empty($vendorRecord['id']) && uthenga_table_exists('vendor_payouts')
    ? (dbQueryOne("SELECT COALESCE(SUM(amount), 0) AS payout_total FROM vendor_payouts WHERE vendor_id = ? AND status = 'processed'", [(int)$vendorRecord['id']]) ?: ['payout_total' => 0])
    : ['payout_total' => 0];

$typeCounts = ['event' => 0, 'property' => 0, 'tour' => 0, 'transport' => 0];
foreach ($allItems as $row) {
    $type = $row['type'] ?? 'event';
    if (!isset($typeCounts[$type])) {
        $typeCounts[$type] = 0;
    }
    $typeCounts[$type]++;
}

$profileUpdateSuccess = '';
$profileUpdateError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_vendor_profile'])) {
    if (!validateCsrf()) {
        $profileUpdateError = 'Security error. Please refresh and try again.';
    } else {
        $name   = trim((string)($_POST['name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone  = trim((string)($_POST['phone'] ?? ''));
        $avatar = trim((string)($_POST['avatar'] ?? ''));

        if (strlen($name) < 2) {
            $profileUpdateError = 'Name must be at least 2 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileUpdateError = 'Please enter a valid email address.';
        } elseif ($phone === '') {
            $profileUpdateError = 'Please enter a phone number.';
        } elseif (!preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
            $profileUpdateError = 'Please enter a valid phone number.';
        } else {
            $existingEmail = dbQueryOne(
                'SELECT id FROM users WHERE LOWER(email) = ? AND id <> ? LIMIT 1',
                [$email, $vendorId]
            );
            $existingPhone = dbQueryOne(
                'SELECT id FROM users WHERE phone = ? AND phone IS NOT NULL AND phone <> "" AND id <> ? LIMIT 1',
                [$phone, $vendorId]
            );

            if ($existingEmail) {
                $profileUpdateError = 'Another account already uses that email address.';
            } elseif ($existingPhone) {
                $profileUpdateError = 'Another account already uses that phone number.';
            } else {
                dbExecute(
                    'UPDATE users SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?',
                    [$name, $email, $phone, $avatar !== '' ? $avatar : ($vendor['avatar'] ?? null), $vendorId]
                );
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                if ($avatar !== '') {
                    $_SESSION['user_avatar'] = $avatar;
                }
                $vendor = dbQueryOne('SELECT * FROM users WHERE id = ?', [$vendorId]) ?: $vendor;
                $profileUpdateSuccess = 'Profile updated successfully.';
                logAction('Profile Updated', 'Vendor updated account details from dashboard.');
            }
        }
    }
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
      <div class="text-xs text-muted">Available wallet</div>
      <div style="font-size:1.4rem;font-weight:800;color:var(--clr-accent);"><?= formatMWK((float) ($vendorWallet['balance'] ?? 0)) ?></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.25rem;margin-bottom:1rem;">
    <div class="grid grid-cols-4 gap-2">
      <div class="presentation-stat"><span>Available Balance</span><strong><?= formatMWK((float) ($vendorWallet['balance'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Pending Balance</span><strong><?= formatMWK((float) ($vendorWallet['pending_balance'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Total Sales</span><strong><?= formatMWK((float) ($vendorCommissionTotals['vendor_total'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Commission Paid</span><strong><?= formatMWK((float) ($vendorCommissionTotals['commission_total'] ?? 0)) ?></strong></div>
    </div>
    <div class="presentation-grid" style="margin-top:1rem;">
      <div class="presentation-stat"><span>Items</span><strong><?= number_format(count($allItems)) ?></strong></div>
      <div class="presentation-stat"><span>Bookings</span><strong><?= number_format((int) ($bookingStats['total_bookings'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Gross revenue</span><strong><?= formatMWK((float) ($bookingStats['total_revenue'] ?? 0)) ?></strong></div>
      <div class="presentation-stat"><span>Withdrawn</span><strong><?= formatMWK((float) ($vendorPayoutTotals['payout_total'] ?? 0)) ?></strong></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.5rem;margin-bottom:1rem;">
    <div class="section-head">
      <div>
        <h3>Quick profile edit</h3>
        <p class="text-sm text-muted">Keep your vendor contact details current without leaving the dashboard.</p>
      </div>
    </div>
    <?php if ($profileUpdateSuccess): ?>
      <div class="alert alert-success" style="margin-bottom:1rem;">Success: <?= e($profileUpdateSuccess) ?></div>
    <?php endif; ?>
    <?php if ($profileUpdateError): ?>
      <div class="alert alert-error" style="margin-bottom:1rem;">Error: <?= e($profileUpdateError) ?></div>
    <?php endif; ?>

    <form method="POST" action="" style="display:grid;gap:1rem;">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="update_vendor_profile" value="1">

      <div class="grid grid-cols-2 gap-3">
        <div class="form-group">
          <label class="form-label" for="vendor-profile-name">Full Name</label>
          <input type="text" id="vendor-profile-name" name="name" class="form-control" value="<?= e($vendor['name'] ?? '') ?>" required minlength="2">
        </div>
        <div class="form-group">
          <label class="form-label" for="vendor-profile-email">Email Address</label>
          <input type="email" id="vendor-profile-email" name="email" class="form-control" value="<?= e($vendor['email'] ?? '') ?>" required>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="form-group">
          <label class="form-label" for="vendor-profile-phone">Phone Number</label>
          <input type="tel" id="vendor-profile-phone" name="phone" class="form-control" value="<?= e($vendor['phone'] ?? '') ?>" placeholder="+265 999 123 456" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="vendor-profile-avatar">Avatar URL <span class="text-muted">(optional)</span></label>
          <input type="url" id="vendor-profile-avatar" name="avatar" class="form-control" value="<?= e($vendor['avatar'] ?? '') ?>" placeholder="https://example.com/photo.jpg">
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap;">
        <div class="text-xs text-muted">Approved vendor account</div>
        <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
      </div>
    </form>
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
