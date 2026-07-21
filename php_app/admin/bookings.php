<?php
/**
 * Uthenga - Admin Bookings Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

$filterStatus  = strtolower($_GET['status'] ?? 'all');
$filterPayment = strtolower($_GET['payment'] ?? 'all');
$filterType    = $_GET['type'] ?? 'all';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 15;

$where = ['1=1'];
$params = [];

if ($filterStatus !== 'all') {
    $where[] = 'b.booking_status = ?';
    $params[] = $filterStatus;
}
if ($filterPayment !== 'all') {
    $where[] = 'b.payment_status = ?';
    $params[] = $filterPayment;
}
if ($filterType !== 'all') {
    $where[] = 'b.listing_type = ?';
    $params[] = $filterType;
}
if ($search !== '') {
    $where[] = '(b.booking_code LIKE ? OR b.reference_name LIKE ? OR b.id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereStr   = implode(' AND ', $where);

// Handle export before header
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bookings_export_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Booking ID', 'Customer Name', 'Customer Email', 'Listing Title', 'Listing Type', 'Date', 'Amount', 'Booking Status', 'Payment Status']);
    $allBookings = dbQuery(
        "SELECT b.id, u.name AS customer_name, u.email AS customer_email, b.listing_title, b.listing_type, b.booked_at, b.grand_total, b.booking_status, b.payment_status
         FROM bookings b
         INNER JOIN users u ON u.id = b.customer_id
         WHERE $whereStr
         ORDER BY b.created_at DESC",
        $params
    );
    foreach ($allBookings as $b) {
        fputcsv($out, [
            $b['id'],
            $b['customer_name'],
            $b['customer_email'],
            $b['listing_title'],
            $b['listing_type'],
            $b['booked_at'],
            $b['grand_total'],
            $b['booking_status'],
            $b['payment_status']
        ]);
    }
    fclose($out);
    exit;
}

$totalCount = dbCount("SELECT COUNT(b.id) FROM bookings b INNER JOIN users u ON u.id = b.customer_id WHERE $whereStr", $params);
$totalPages = max(1, ceil($totalCount / $perPage));
$offset     = ($page - 1) * $perPage;

$bookings = dbQuery(
    "SELECT b.*, u.name AS customer_name, u.email AS customer_email
     FROM bookings b
     INNER JOIN users u ON u.id = b.customer_id
     WHERE $whereStr
     ORDER BY b.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$stats = [
    'total' => dbCount('SELECT COUNT(*) FROM bookings'),
    'confirmed' => dbCount("SELECT COUNT(*) FROM bookings WHERE booking_status='confirmed'"),
    'pending' => dbCount("SELECT COUNT(*) FROM bookings WHERE booking_status='pending'"),
    'cancelled' => dbCount("SELECT COUNT(*) FROM bookings WHERE booking_status='cancelled'"),
];
$revenue = dbQueryOne("SELECT COALESCE(SUM(grand_total),0) AS total FROM bookings WHERE payment_status='paid'");

function bkStatusBadge(string $status): string {
    return match (strtolower($status)) {
        'confirmed', 'completed' => 'badge-approved',
        'pending' => 'badge-pending',
        'cancelled' => 'badge-cancelled',
        default => 'badge-pending',
    };
}

function pyStatusBadge(string $status): string {
    return match (strtolower($status)) {
        'paid', 'success' => 'badge-approved',
        'refunded' => 'badge-refunded',
        'failed' => 'badge-cancelled',
        default => 'badge-pending',
    };
}

$pageTitle = 'Booking Management';
$activeNav = 'admin-bookings';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Bookings Ledger</h1>
    <p class="text-muted">Monitor reservations, manage booking status, and execute refunds.</p>
  </div>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
  </div>
</div>

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('file') ?></div>
    <div><div class="stat-value"><?= number_format($stats['total']) ?></div><div class="stat-label">Total Bookings</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-green"><?= admin_icon_svg('calendar') ?></div>
    <div><div class="stat-value"><?= number_format($stats['confirmed']) ?></div><div class="stat-label">Confirmed</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-blue"><?= admin_icon_svg('toggle') ?></div>
    <div><div class="stat-value"><?= number_format($stats['pending']) ?></div><div class="stat-label">Pending</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-purple"><?= admin_icon_svg('wallet') ?></div>
    <div>
      <div class="stat-value" style="font-size:1.1rem;"><?= formatMWK((float)($revenue['total'] ?? 0)) ?></div>
      <div class="stat-label">Total Revenue</div>
    </div>
  </div>
</div>

<form method="GET" action="bookings.php" id="filter-form">
  <div class="table-toolbar">
    <div class="search-wrap">
      <span class="search-icon"><?= uthenga_public_icon_svg('search') ?></span>
      <input type="text" name="q" placeholder="Search ID, customer, reference..." value="<?= e($search) ?>" autocomplete="off" id="bookings-search-input">
    </div>
    <select name="status" onchange="this.form.submit()">
      <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
      <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
      <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
      <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
    </select>
    <select name="payment" onchange="this.form.submit()">
      <option value="all" <?= $filterPayment === 'all' ? 'selected' : '' ?>>All Payments</option>
      <option value="paid" <?= $filterPayment === 'paid' ? 'selected' : '' ?>>Paid</option>
      <option value="pending" <?= $filterPayment === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="refunded" <?= $filterPayment === 'refunded' ? 'selected' : '' ?>>Refunded</option>
      <option value="failed" <?= $filterPayment === 'failed' ? 'selected' : '' ?>>Failed</option>
    </select>
    <select name="type" onchange="this.form.submit()">
      <option value="all" <?= $filterType === 'all' ? 'selected' : '' ?>>All Types</option>
      <option value="event" <?= $filterType === 'event' ? 'selected' : '' ?>>Events</option>
      <option value="accommodation" <?= $filterType === 'accommodation' ? 'selected' : '' ?>>Stays</option>
      <option value="tour" <?= $filterType === 'tour' ? 'selected' : '' ?>>Tours</option>
      <option value="transport" <?= $filterType === 'transport' ? 'selected' : '' ?>>Transport</option>
    </select>
    <div class="export-group">
      <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-secondary btn-sm"><?= admin_icon_svg('download') ?> CSV</a>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <a href="bookings.php" class="btn btn-ghost btn-sm">Clear</a>
    </div>
  </div>
</form>

<div class="glass-panel" style="padding:1rem;">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Booking ID</th>
          <th>Customer</th>
          <th>Listing</th>
          <th>Type</th>
          <th>Date</th>
          <th>Amount</th>
          <th>Booking Status</th>
          <th>Payment Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
          <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--clr-text-muted);">No bookings found.</td></tr>
        <?php else: ?>
          <?php foreach ($bookings as $bk): ?>
            <tr id="booking-row-<?= e($bk['id']) ?>">
              <td class="font-mono text-xs"><?= e($bk['id']) ?></td>
              <td>
                <div style="font-weight:600;font-size:0.85rem;"><?= e($bk['customer_name']) ?></div>
                <div class="text-xs text-muted"><?= e($bk['customer_email']) ?></div>
              </td>
              <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($bk['reference_name'] ?: $bk['booking_code']) ?></td>
              <td class="text-xs" style="text-transform:capitalize;"><?= e($filterType !== 'all' ? $filterType : 'booking') ?></td>
              <td class="text-xs text-muted"><?= e(substr($bk['booked_at'], 0, 16)) ?></td>
              <td style="font-weight:700;color:var(--clr-accent);"><?= formatMWK((float)$bk['grand_total']) ?></td>
              <td>
                <select
                  class="admin-status-select form-control"
                  data-booking-id="<?= e($bk['id']) ?>"
                  data-field="booking_status"
                  data-original="<?= e($bk['booking_status']) ?>"
                  style="padding:0.25rem;font-size:0.75rem;width:120px;"
                >
                  <option value="pending" <?= $bk['booking_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="confirmed" <?= $bk['booking_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                  <option value="cancelled" <?= $bk['booking_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                  <option value="completed" <?= $bk['booking_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
              </td>
              <td><span class="badge <?= pyStatusBadge($bk['payment_status']) ?>"><?= e($bk['payment_status']) ?></span></td>
              <td style="text-align:right;">
                <div style="display:inline-flex;gap:0.4rem;justify-content:flex-end;">
                  <?php if ($bk['payment_status'] !== 'refunded' && $bk['booking_status'] !== 'cancelled'): ?>
                    <button class="btn btn-sm btn-danger btn-refund" data-booking-id="<?= e($bk['id']) ?>" id="refund-<?= e($bk['id']) ?>">Refund</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
  <div class="pagination" style="margin-top:1.5rem;">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($filterStatus) ?>&payment=<?= urlencode($filterPayment) ?>&type=<?= urlencode($filterType) ?>&q=<?= urlencode($search) ?>" class="page-btn">Prev</a>
    <?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <a href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&payment=<?= urlencode($filterPayment) ?>&type=<?= urlencode($filterType) ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($filterStatus) ?>&payment=<?= urlencode($filterPayment) ?>&type=<?= urlencode($filterType) ?>&q=<?= urlencode($search) ?>" class="page-btn">Next</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<p class="text-xs text-muted" style="text-align:center;margin-top:1rem;">
  Showing <?= count($bookings) ?> of <?= number_format($totalCount) ?> bookings
</p>

<script>
document.getElementById('bookings-search-input').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.admin-table tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
