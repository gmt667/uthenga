<?php
/**
 * Uthenga — Admin Event Report
 * Full reporting with CSV, print/PDF, and Excel-compatible export
 * PHP 7.3+ compatible
 */
$pageTitle = 'Event Report';
$activeNav = 'admin-event-report';

require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/functions.php';

// Load events for selector
$events = dbQuery(
    "SELECT id, title, location, meta FROM listings
     WHERE listing_type = 'event' AND is_active = 1
     ORDER BY title ASC"
);

$selectedId = trim($_GET['event_id'] ?? '');
$event      = null;
$stats      = null;
$scans      = null;
$sessions   = [];

// CSV export
if (isset($_GET['export']) && $selectedId) {
    $format = $_GET['export'];
    $event  = dbQueryOne("SELECT * FROM listings WHERE id = ? AND listing_type = 'event'", [$selectedId]);
    if ($event) {
        $bkRows = dbQuery(
            "SELECT b.id, b.customer_name, b.customer_email, b.created_at, b.payment_status,
                    b.booking_status, b.total_price,
                    JSON_UNQUOTE(JSON_EXTRACT(b.details, '$.ticket_type')) AS ticket_type,
                    JSON_UNQUOTE(JSON_EXTRACT(b.details, '$.quantity')) AS quantity
             FROM bookings b
             WHERE b.listing_id = ? ORDER BY b.created_at DESC",
            [$selectedId]
        );
        $scanRows = [];
        try {
            $scanRows = dbQuery(
                "SELECT gs.id, gs.started_at, gs.stopped_at, gs.total_valid, gs.total_invalid, gs.total_duplicate
                 FROM gate_sessions gs WHERE gs.listing_id = ? ORDER BY gs.started_at DESC",
                [$selectedId]
            );
        } catch (Exception $e) {}

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="event-report-' . $selectedId . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Event Report — ' . $event['title']]);
            fputcsv($out, []);
            fputcsv($out, ['Booking ID', 'Customer Name', 'Customer Email', 'Ticket Type', 'Quantity', 'Total Price (MK)', 'Payment Status', 'Booking Status', 'Date']);
            foreach ($bkRows as $row) {
                fputcsv($out, [
                    $row['id'], $row['customer_name'], $row['customer_email'],
                    $row['ticket_type'] ?? 'Standard', $row['quantity'] ?? 1,
                    number_format((float)$row['total_price'], 2),
                    $row['payment_status'], $row['booking_status'],
                    $row['created_at']
                ]);
            }
            fclose($out);
            exit;
        }
        if ($format === 'excel') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="event-report-' . $selectedId . '.xls"');
            echo "Booking ID\tCustomer Name\tCustomer Email\tTicket Type\tQuantity\tTotal Price (MK)\tPayment Status\tBooking Status\tDate\n";
            foreach ($bkRows as $row) {
                echo implode("\t", [
                    $row['id'], $row['customer_name'], $row['customer_email'],
                    $row['ticket_type'] ?? 'Standard', $row['quantity'] ?? 1,
                    number_format((float)$row['total_price'], 2),
                    $row['payment_status'], $row['booking_status'], $row['created_at']
                ]) . "\n";
            }
            exit;
        }
    }
}

if ($selectedId) {
    $event = dbQueryOne("SELECT * FROM listings WHERE id = ? AND listing_type = 'event'", [$selectedId]);

    if ($event) {
        $em = json_decode($event['meta'], true);

        // Booking stats
        $stats = dbQueryOne(
            "SELECT
               COUNT(*) AS total_bookings,
               COALESCE(SUM(CASE WHEN LOWER(payment_status) = 'paid' THEN COALESCE(NULLIF(quantity, 0), 1) ELSE 0 END), 0) AS paid_count,
               COALESCE(SUM(CASE WHEN LOWER(payment_status) = 'paid' THEN total_price ELSE 0 END), 0) AS revenue,
               COALESCE(SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count
             FROM bookings WHERE listing_id = ?",
            [$selectedId]
        );

        // Ticket tier breakdown
        $tierBreakdown = dbQuery(
            "SELECT
               COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details, '$.ticket_type')), 'Standard') AS tier,
               COALESCE(SUM(COALESCE(NULLIF(quantity, 0), 1)), 0) AS count,
               COALESCE(SUM(total_price), 0) AS tier_revenue
             FROM bookings
             WHERE listing_id = ? AND LOWER(payment_status) = 'paid'
             GROUP BY tier",
            [$selectedId]
        );

        // Gate scan stats
        $scanStats = null;
        try {
            $scanStats = dbQueryOne(
                "SELECT SUM(total_valid) AS total_valid, SUM(total_invalid) AS total_invalid,
                        SUM(total_duplicate) AS total_duplicate, SUM(total_scanned) AS total_scanned
                 FROM gate_sessions WHERE listing_id = ?",
                [$selectedId]
            );
            $sessions = dbQuery(
                "SELECT * FROM gate_sessions WHERE listing_id = ? ORDER BY started_at DESC",
                [$selectedId]
            );
        } catch (Exception $e) {
            $scanStats = null;
            $sessions  = [];
        }

        // Booking rows for table
        $bookingRows = dbQuery(
            "SELECT b.id, b.customer_name, b.customer_email, b.created_at,
                    b.payment_status, b.booking_status, b.total_price,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(b.details, '$.ticket_type')), 'Standard') AS ticket_type,
                    COALESCE(NULLIF(b.quantity, 0), CAST(JSON_UNQUOTE(JSON_EXTRACT(b.details, '$.quantity')) AS UNSIGNED), 1) AS quantity
             FROM bookings b WHERE b.listing_id = ? ORDER BY b.created_at DESC LIMIT 100",
            [$selectedId]
        );

        $totalSold     = (int) ($stats['paid_count'] ?? 0);
        $totalScanned  = (int) ($scanStats['total_valid'] ?? 0);
        $noShows       = max(0, $totalSold - $totalScanned);
        $revenue       = (float) ($stats['revenue'] ?? 0);
        $attendancePct = $totalSold > 0 ? round(($totalScanned / $totalSold) * 100, 1) : 0;
        $totalCapacity = (int) (($em['standardAvailable'] ?? 0) + ($em['vipAvailable'] ?? 0));
        $soldPct       = $totalCapacity > 0 ? round(($totalSold / $totalCapacity) * 100, 1) : 0;
    }
}
?>

<div class="page-header" style="margin-bottom:1.5rem;">
  <div>
    <h1 class="page-title">📊 Event Report</h1>
    <p class="text-muted">Detailed analytics, ticket breakdown, and attendance reports for events.</p>
  </div>
  <?php if ($event): ?>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;" id="report-export-btns">
      <a href="?event_id=<?= urlencode($selectedId) ?>&export=csv" class="btn btn-sm btn-secondary" id="export-csv-btn">⬇️ Export CSV</a>
      <a href="?event_id=<?= urlencode($selectedId) ?>&export=excel" class="btn btn-sm btn-secondary" id="export-excel-btn">📊 Export Excel</a>
      <button class="btn btn-sm btn-secondary" id="export-pdf-btn" onclick="window.print()">🖨️ Print / PDF</button>
    </div>
  <?php endif; ?>
</div>

<!-- Event Selector -->
<div class="glass-panel" style="padding:1.25rem 1.5rem;margin-bottom:1.5rem;" id="report-selector">
  <form method="GET" action="event_report.php" id="report-filter-form" style="display:flex;align-items:flex-end;gap:1rem;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;flex:1;min-width:240px;">
      <label class="form-label" for="report-event-select">Select Event</label>
      <select name="event_id" class="form-control" id="report-event-select" onchange="this.form.submit()">
        <option value="">— Choose an Event —</option>
        <?php foreach ($events as $ev): ?>
          <?php $em2 = json_decode($ev['meta'], true); ?>
          <option value="<?= e($ev['id']) ?>" <?= $selectedId === $ev['id'] ? 'selected' : '' ?>>
            <?= e($ev['title']) ?> — <?= e($ev['location']) ?> (<?= e($em2['date'] ?? 'No date') ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-secondary btn-sm" id="report-load-btn" style="height:42px;">Load Report</button>
  </form>
</div>

<?php if (!$event): ?>
<div class="glass-panel" style="padding:3rem;text-align:center;">
  <div style="font-size:3rem;margin-bottom:1rem;">📊</div>
  <h3>No Event Selected</h3>
  <p class="text-muted">Select an event above to generate a full report.</p>
</div>

<?php else: ?>

<!-- Report Header (visible in print) -->
<div id="print-header" style="display:none;">
  <h2 style="margin-bottom:0.25rem;"><?= e($event['title']) ?> — Event Report</h2>
  <p style="color:#666;margin-bottom:1rem;">Generated: <?= date('d M Y H:i') ?> | Venue: <?= e($event['location']) ?></p>
  <hr>
</div>

<!-- Event Info -->
<div class="glass-panel" style="padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;" id="event-info-card">
  <img src="<?= e($event['image']) ?>" alt="" style="width:64px;height:64px;border-radius:var(--radius-md);object-fit:cover;">
  <div style="flex:1;">
    <div style="font-weight:700;font-size:1.05rem;"><?= e($event['title']) ?></div>
    <div class="text-sm text-muted">
      📍 <?= e($event['location']) ?> &nbsp;·&nbsp;
      📅 <?= e($em['date'] ?? 'TBC') ?> &nbsp;·&nbsp;
      ⏰ <?= e($em['time'] ?? 'TBC') ?> &nbsp;·&nbsp;
      🎟️ Capacity: <?= number_format($totalCapacity) ?>
    </div>
  </div>
</div>

<!-- Summary Metrics -->
<div class="report-metric-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;" id="report-summary-metrics">
  <?php
  $metrics = [
    ['icon' => '🎫', 'val' => number_format($totalSold), 'label' => 'Tickets Sold', 'color' => 'var(--clr-blue)'],
    ['icon' => '✅', 'val' => number_format($totalScanned), 'label' => 'Scanned / Attended', 'color' => 'var(--clr-green)'],
    ['icon' => '🚷', 'val' => number_format($noShows), 'label' => 'No Shows', 'color' => 'var(--clr-red)'],
    ['icon' => '💰', 'val' => formatMWK($revenue), 'label' => 'Total Revenue', 'color' => 'var(--clr-accent)'],
    ['icon' => '📈', 'val' => $attendancePct . '%', 'label' => 'Attendance Rate', 'color' => 'var(--clr-green)'],
    ['icon' => '📊', 'val' => $soldPct . '%', 'label' => 'Tickets Sold %', 'color' => 'var(--clr-purple)'],
    ['icon' => '❌', 'val' => number_format((int)($stats['cancelled_count'] ?? 0)), 'label' => 'Cancellations', 'color' => 'var(--clr-red)'],
    ['icon' => '📋', 'val' => number_format((int)($stats['total_bookings'] ?? 0)), 'label' => 'Total Bookings', 'color' => 'var(--clr-blue)'],
  ];
  foreach ($metrics as $m): ?>
  <div class="glass-panel" style="padding:1.25rem;text-align:center;">
    <div style="font-size:1.8rem;margin-bottom:0.4rem;"><?= $m['icon'] ?></div>
    <div style="font-size:1.5rem;font-weight:800;color:<?= $m['color'] ?>;"><?= $m['val'] ?></div>
    <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--clr-text-muted);margin-top:0.2rem;"><?= $m['label'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Attendance Bar Chart -->
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;" id="attendance-chart-card">
  <h4 style="margin-bottom:1rem;">Attendance Overview</h4>
  <?php
  $barWidth   = $totalSold > 0 ? min(100, $attendancePct) : 0;
  $soldBarW   = $totalCapacity > 0 ? min(100, $soldPct) : 0;
  ?>
  <div style="margin-bottom:1rem;">
    <div style="display:flex;justify-content:space-between;margin-bottom:0.35rem;">
      <span style="font-size:0.85rem;font-weight:600;">Attendance Rate</span>
      <span style="font-size:0.85rem;color:var(--clr-green);font-weight:700;"><?= $attendancePct ?>%</span>
    </div>
    <div style="height:14px;background:var(--clr-surface2);border-radius:999px;overflow:hidden;">
      <div style="height:100%;width:<?= $barWidth ?>%;background:var(--clr-green);border-radius:999px;transition:width 1s ease;"></div>
    </div>
  </div>
  <div>
    <div style="display:flex;justify-content:space-between;margin-bottom:0.35rem;">
      <span style="font-size:0.85rem;font-weight:600;">Tickets Sold of Capacity</span>
      <span style="font-size:0.85rem;color:var(--clr-accent);font-weight:700;"><?= $soldPct ?>%</span>
    </div>
    <div style="height:14px;background:var(--clr-surface2);border-radius:999px;overflow:hidden;">
      <div style="height:100%;width:<?= $soldBarW ?>%;background:var(--clr-accent);border-radius:999px;transition:width 1s ease;"></div>
    </div>
  </div>
</div>

<!-- Ticket Tier Breakdown -->
<?php if (!empty($tierBreakdown)): ?>
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;" id="tier-breakdown-card">
  <h4 style="margin-bottom:1rem;">Ticket Tier Breakdown</h4>
  <div class="table-responsive">
    <table class="admin-table" id="tier-breakdown-table">
      <thead>
        <tr><th>Tier</th><th>Tickets Sold</th><th>Revenue (MK)</th><th>% of Total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($tierBreakdown as $tier): ?>
        <tr>
          <td><strong><?= e($tier['tier']) ?></strong></td>
          <td><?= number_format((int)$tier['count']) ?></td>
          <td><?= formatMWK((float)$tier['tier_revenue']) ?></td>
          <td><?= $totalSold > 0 ? round(($tier['count'] / $totalSold) * 100, 1) : 0 ?>%</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="font-weight:700;">
          <td>Total</td>
          <td><?= number_format($totalSold) ?></td>
          <td><?= formatMWK($revenue) ?></td>
          <td>100%</td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Gate Sessions History -->
<?php if (!empty($sessions)): ?>
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;" id="gate-sessions-card">
  <h4 style="margin-bottom:1rem;">Gate Session History</h4>
  <div class="table-responsive">
    <table class="admin-table" id="gate-sessions-table">
      <thead>
        <tr><th>Session ID</th><th>Started By</th><th>Start Time</th><th>Status</th><th>Valid</th><th>Invalid</th><th>Duplicate</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $sess): ?>
        <tr>
          <td><code style="font-size:0.78rem;"><?= e($sess['id']) ?></code></td>
          <td><?= e($sess['started_name']) ?></td>
          <td><?= date('d M Y H:i', strtotime($sess['started_at'])) ?></td>
          <td>
            <?php
            $sc = $sess['status'] === 'active' ? 'var(--clr-green)' : ($sess['status'] === 'stopped' ? 'var(--clr-text-muted)' : 'var(--clr-accent)');
            ?>
            <span style="color:<?= $sc ?>;font-weight:700;text-transform:capitalize;"><?= e($sess['status']) ?></span>
          </td>
          <td style="color:var(--clr-green);font-weight:700;"><?= number_format((int)$sess['total_valid']) ?></td>
          <td style="color:var(--clr-red);"><?= number_format((int)$sess['total_invalid']) ?></td>
          <td style="color:var(--clr-accent);"><?= number_format((int)$sess['total_duplicate']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Booking Details Table -->
<div class="glass-panel" style="padding:1.5rem;" id="booking-details-card">
  <h4 style="margin-bottom:1rem;">Booking Details <span class="text-muted text-sm">(showing up to 100)</span></h4>
  <div class="table-responsive">
    <table class="admin-table" id="bookings-details-table">
      <thead>
        <tr>
          <th>Booking ID</th><th>Customer</th><th>Ticket Tier</th><th>Qty</th>
          <th>Amount</th><th>Payment</th><th>Status</th><th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookingRows)): ?>
          <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--clr-text-muted);">No bookings found.</td></tr>
        <?php else: ?>
          <?php foreach ($bookingRows as $bk): ?>
          <tr>
            <td><code style="font-size:0.78rem;"><?= e($bk['id']) ?></code></td>
            <td>
              <div style="font-weight:600;font-size:0.85rem;"><?= e($bk['customer_name']) ?></div>
              <div class="text-xs text-muted"><?= e($bk['customer_email']) ?></div>
            </td>
            <td><?= e($bk['ticket_type']) ?></td>
            <td><?= (int)$bk['quantity'] ?></td>
            <td style="font-weight:700;"><?= formatMWK((float)$bk['total_price']) ?></td>
            <td>
              <?php
              $ps = strtolower($bk['payment_status']);
              $pc = $ps === 'paid' ? 'var(--clr-green)' : ($ps === 'failed' ? 'var(--clr-red)' : 'var(--clr-accent)');
              ?>
              <span style="color:<?= $pc ?>;font-weight:600;"><?= e($bk['payment_status']) ?></span>
            </td>
            <td>
              <?php $bs = strtolower($bk['booking_status']); ?>
              <span style="color:<?= $bs === 'confirmed' ? 'var(--clr-green)' : ($bs === 'cancelled' ? 'var(--clr-red)' : 'var(--clr-accent)') ?>;font-weight:600;text-transform:capitalize;">
                <?= e($bk['booking_status']) ?>
              </span>
            </td>
            <td class="text-xs text-muted"><?= date('d M Y', strtotime($bk['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; // $event ?>

<style>
@media print {
  .dashboard-topbar, .sidebar, #report-selector, #report-export-btns,
  .dashboard-sidebar-toggle, .page-header .btn { display: none !important; }
  #print-header { display: block !important; }
  .glass-panel { border: 1px solid #ccc !important; box-shadow: none !important; background: #fff !important; }
  body, * { color: #000 !important; background: #fff !important; }
  .admin-table th { background: #f0f0f0 !important; }
}
@media (max-width: 768px) {
  .report-metric-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
</style>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
