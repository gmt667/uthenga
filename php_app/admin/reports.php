<?php
/**
 * Uthenga — Admin Reports & Analytics
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

// Handle exports BEFORE admin_header.php to avoid "headers already sent"
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'xls', 'pdf'], true)) {
    $typeStats = dbQuery("
        SELECT listing_type, COUNT(*) AS count, SUM(total_price) AS revenue
        FROM bookings
        WHERE payment_status = 'Paid'
        GROUP BY listing_type
    ");
    $chartData = [];
    foreach ($typeStats as $stat) {
        $chartData[$stat['listing_type']] = [
            'count' => (int)$stat['count'],
            'revenue' => (float)$stat['revenue']
        ];
    }
    foreach (['event', 'accommodation', 'tour', 'transport'] as $t) {
        if (!isset($chartData[$t])) {
            $chartData[$t] = ['count' => 0, 'revenue' => 0.0];
        }
    }

    $topListings = dbQuery("
        SELECT listing_title, listing_type, COUNT(*) AS bookings_count, SUM(total_price) AS total_revenue
        FROM bookings
        WHERE payment_status = 'Paid'
        GROUP BY listing_id
        ORDER BY total_revenue DESC
        LIMIT 5
    ");

    $monthlyStats = dbQuery("
        SELECT DATE_FORMAT(created_at, '%b %Y') AS month, COUNT(*) AS count, SUM(total_price) AS total
        FROM bookings
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
        LIMIT 6
    ");

    if ($_GET['export'] === 'pdf') {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Platform Reports & Analytics</title>
          <style>
            body { font-family: Arial, sans-serif; padding: 30px; color: #111827; }
            h1 { margin: 0 0 4px; }
            h2 { margin: 24px 0 8px; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; font-size: 16px; }
            p { margin: 0 0 18px; color: #4b5563; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #d1d5db; padding: 8px 10px; text-align: left; font-size: 12px; }
            th { background: #f3f4f6; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
          </style>
        </head>
        <body>
          <h1>Platform Reports & Analytics</h1>
          <p>Generated on <?= htmlspecialchars(date('Y-m-d H:i')) ?></p>

          <h2>Category Revenue Performance</h2>
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th class="text-center">Bookings Count</th>
                <th class="text-right">Revenue (MWK)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (['event' => 'Events', 'accommodation' => 'Stays', 'tour' => 'Tours', 'transport' => 'Transport'] as $key => $label): ?>
                <tr>
                  <td><?= htmlspecialchars($label) ?></td>
                  <td class="text-center"><?= number_format($chartData[$key]['count']) ?></td>
                  <td class="text-right"><?= number_format($chartData[$key]['revenue'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <h2>Monthly Booking Volumes</h2>
          <table>
            <thead>
              <tr>
                <th>Month</th>
                <th class="text-center">Bookings Count</th>
                <th class="text-right">Total Revenue (MWK)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($monthlyStats as $ms): ?>
                <tr>
                  <td><?= htmlspecialchars($ms['month']) ?></td>
                  <td class="text-center"><?= number_format($ms['count']) ?></td>
                  <td class="text-right"><?= number_format((float)$ms['total'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <h2>Top Performing Products/Listings</h2>
          <table>
            <thead>
              <tr>
                <th>Listing Title</th>
                <th>Category</th>
                <th class="text-center">Bookings Count</th>
                <th class="text-right">Total Revenue (MWK)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topListings as $tl): ?>
                <tr>
                  <td><?= htmlspecialchars($tl['listing_title']) ?></td>
                  <td><?= htmlspecialchars(ucfirst($tl['listing_type'])) ?></td>
                  <td class="text-center"><?= number_format($tl['bookings_count']) ?></td>
                  <td class="text-right"><?= htmlspecialchars(number_format((float)$tl['total_revenue'], 2)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <script>window.print();</script>
        </body>
        </html>
        <?php
        exit;
    }

    $isExcel = $_GET['export'] === 'xls';
    header('Content-Type: ' . ($isExcel ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=UTF-8');
    header('Content-Disposition: attachment; filename=platform-reports-export.' . ($isExcel ? 'xls' : 'csv'));
    
    $out = fopen('php://output', 'w');
    
    fputcsv($out, ['--- Category Performance ---'], $isExcel ? "\t" : ',');
    fputcsv($out, ['Category', 'Bookings Count', 'Revenue (MWK)'], $isExcel ? "\t" : ',');
    foreach (['event' => 'Events', 'accommodation' => 'Stays', 'tour' => 'Tours', 'transport' => 'Transport'] as $key => $label) {
        fputcsv($out, [$label, $chartData[$key]['count'], $chartData[$key]['revenue']], $isExcel ? "\t" : ',');
    }
    
    fputcsv($out, [''], $isExcel ? "\t" : ',');
    
    fputcsv($out, ['--- Monthly Booking Volumes ---'], $isExcel ? "\t" : ',');
    fputcsv($out, ['Month', 'Bookings Count', 'Total Revenue (MWK)'], $isExcel ? "\t" : ',');
    foreach ($monthlyStats as $ms) {
        fputcsv($out, [$ms['month'], $ms['count'], $ms['total']], $isExcel ? "\t" : ',');
    }
    
    fputcsv($out, [''], $isExcel ? "\t" : ',');
    
    fputcsv($out, ['--- Top Performing Products/Listings ---'], $isExcel ? "\t" : ',');
    fputcsv($out, ['Listing Title', 'Category', 'Bookings Count', 'Total Revenue (MWK)'], $isExcel ? "\t" : ',');
    foreach ($topListings as $tl) {
        fputcsv($out, [$tl['listing_title'], ucfirst($tl['listing_type']), $tl['bookings_count'], $tl['total_revenue']], $isExcel ? "\t" : ',');
    }
    
    fclose($out);
    exit;
}

$pageTitle = 'Reports & Analytics';
$activeNav = 'admin-reports';

require_once __DIR__ . '/includes/admin_header.php';

// Query bookings by type
$typeStats = dbQuery("
    SELECT listing_type, COUNT(*) AS count, SUM(total_price) AS revenue
    FROM bookings
    WHERE payment_status = 'Paid'
    GROUP BY listing_type
");

$chartData = [];
$maxRevenue = 1000;
foreach ($typeStats as $stat) {
    $chartData[$stat['listing_type']] = [
        'count' => (int)$stat['count'],
        'revenue' => (float)$stat['revenue']
    ];
    if ($stat['revenue'] > $maxRevenue) {
        $maxRevenue = $stat['revenue'];
    }
}

foreach (['event', 'accommodation', 'tour', 'transport'] as $t) {
    if (!isset($chartData[$t])) {
        $chartData[$t] = ['count' => 0, 'revenue' => 0.0];
    }
}

$topListings = dbQuery("
    SELECT listing_title, listing_type, COUNT(*) AS bookings_count, SUM(total_price) AS total_revenue
    FROM bookings
    WHERE payment_status = 'Paid'
    GROUP BY listing_id
    ORDER BY total_revenue DESC
    LIMIT 5
");

$monthlyStats = dbQuery("
    SELECT DATE_FORMAT(created_at, '%b %Y') AS month, COUNT(*) AS count, SUM(total_price) AS total
    FROM bookings
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at ASC
    LIMIT 6
");
?>

<div class="page-header">
  <div>
    <h1 class="page-title">📈 Reports & Analytics</h1>
    <p class="text-muted">Analyze transaction volumes, category performance, and platform revenue stats.</p>
  </div>
  <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
    <a href="?export=csv" class="btn btn-secondary btn-sm"><?= admin_icon_svg('download') ?> CSV</a>
    <a href="?export=xls" class="btn btn-secondary btn-sm"><?= admin_icon_svg('download') ?> Excel</a>
    <a href="?export=pdf" class="btn btn-secondary btn-sm" target="_blank"><?= admin_icon_svg('download') ?> PDF</a>
  </div>
</div>

<div class="grid grid-cols-2 gap-3" style="margin-bottom: 2rem;">
  <!-- Revenue by Category Chart -->
  <div class="glass-panel" style="padding: 1.5rem;">
    <h3 style="font-size: 1.1rem; margin-bottom: 1.5rem;">💰 Revenue by Category (MWK)</h3>
    <div class="report-chart-container">
      <?php foreach (['event' => 'Events', 'accommodation' => 'Stays', 'tour' => 'Tours', 'transport' => 'Transport'] as $key => $label): 
        $rev = $chartData[$key]['revenue'];
        $pct = ($maxRevenue > 0) ? ($rev / $maxRevenue) * 100 : 0;
      ?>
        <div class="chart-bar-col">
          <div class="chart-bar" style="height: <?= max(8, $pct) ?>%;">
            <div class="chart-bar-value"><?= number_format($rev / 1000, 0) ?>K</div>
          </div>
          <div class="chart-label"><?= $label ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-xs text-muted" style="text-align: center; margin-top: 1rem;">Values in thousands (K) of MWK</div>
  </div>

  <!-- Booking Volumes -->
  <div class="glass-panel" style="padding: 1.5rem;">
    <h3 style="font-size: 1.1rem; margin-bottom: 1.5rem;">📈 Monthly Booking Volume</h3>
    <?php if (empty($monthlyStats)): ?>
      <p class="text-muted">No historical monthly data available.</p>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-top: 1.5rem;">
        <?php foreach ($monthlyStats as $ms): ?>
          <div>
            <div style="display:flex; justify-content:space-between; font-size: 0.85rem; margin-bottom: 0.25rem;">
              <strong><?= e($ms['month']) ?></strong>
              <span><?= number_format($ms['count']) ?> bookings (<?= formatMWK((float)$ms['total']) ?>)</span>
            </div>
            <div style="height: 6px; background: rgba(255,255,255,0.05); border-radius: 3px; overflow: hidden;">
              <?php
              $totalCount = dbCount("SELECT COUNT(*) FROM bookings");
              $pct = ($totalCount > 0) ? ($ms['count'] / $totalCount) * 100 : 0;
              ?>
              <div style="height: 100%; width: <?= $pct ?>%; background: var(--clr-accent);"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Top Listings Table -->
<div class="glass-panel" style="padding: 1.5rem;">
  <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">🏆 Top 5 Performing Products/Listings</h3>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Listing Title</th>
          <th>Category</th>
          <th style="text-align: center;">Confirmed Bookings</th>
          <th style="text-align: right;">Total Generated Revenue</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($topListings)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--clr-text-muted);">No sales data available.</td></tr>
        <?php else: ?>
          <?php foreach ($topListings as $index => $tl): ?>
            <tr>
              <td>
                <span style="color: var(--clr-accent); font-weight: 700; margin-right: 0.5rem;">#<?= $index + 1 ?></span>
                <strong style="color: var(--clr-text);"><?= e($tl['listing_title']) ?></strong>
              </td>
              <td><span class="role-badge role-customer" style="text-transform: capitalize;"><?= e($tl['listing_type']) ?></span></td>
              <td style="text-align: center;"><?= number_format($tl['bookings_count']) ?></td>
              <td style="text-align: right; font-weight: 700; color: var(--clr-green);"><?= formatMWK((float)$tl['total_revenue']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
