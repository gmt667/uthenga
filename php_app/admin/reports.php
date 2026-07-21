<?php
/**
 * Uthenga - Admin Reports & Analytics
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();

function uthenga_reports_load_data(): array
{
    $typeStats = dbQuery("
        SELECT listing_type, COUNT(*) AS count, SUM(total_price) AS revenue
        FROM bookings
        WHERE payment_status = 'Paid'
        GROUP BY listing_type
    ") ?: [];

    $chartData = [];
    $maxRevenue = 1000;
    foreach ($typeStats as $stat) {
        $chartData[$stat['listing_type']] = [
            'count' => (int) $stat['count'],
            'revenue' => (float) $stat['revenue'],
        ];
        $maxRevenue = max($maxRevenue, (float) $stat['revenue']);
    }

    foreach (['event', 'accommodation', 'tour', 'transport'] as $type) {
        $chartData[$type] = $chartData[$type] ?? ['count' => 0, 'revenue' => 0.0];
    }

    return [
        'chartData' => $chartData,
        'maxRevenue' => $maxRevenue,
        'topListings' => dbQuery("
            SELECT listing_title, listing_type, COUNT(*) AS bookings_count, SUM(total_price) AS total_revenue
            FROM bookings
            WHERE payment_status = 'Paid'
            GROUP BY listing_id
            ORDER BY total_revenue DESC
            LIMIT 5
        ") ?: [],
        'monthlyStats' => dbQuery("
            SELECT DATE_FORMAT(created_at, '%b %Y') AS month, COUNT(*) AS count, SUM(total_price) AS total
            FROM bookings
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY created_at ASC
            LIMIT 6
        ") ?: [],
        'totalBookings' => dbCount("SELECT COUNT(*) FROM bookings"),
    ];
}

function uthenga_reports_render_pdf(array $data): void
{
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
              <td class="text-center"><?= number_format($data['chartData'][$key]['count']) ?></td>
              <td class="text-right"><?= number_format($data['chartData'][$key]['revenue'], 2) ?></td>
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
          <?php foreach ($data['monthlyStats'] as $ms): ?>
            <tr>
              <td><?= htmlspecialchars($ms['month']) ?></td>
              <td class="text-center"><?= number_format($ms['count']) ?></td>
              <td class="text-right"><?= number_format((float) $ms['total'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2>Top Performing Listings</h2>
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
          <?php foreach ($data['topListings'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['listing_title']) ?></td>
              <td><?= htmlspecialchars(ucfirst($row['listing_type'])) ?></td>
              <td class="text-center"><?= number_format($row['bookings_count']) ?></td>
              <td class="text-right"><?= htmlspecialchars(number_format((float) $row['total_revenue'], 2)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <script>window.print();</script>
    </body>
    </html>
    <?php
}

$reportData = uthenga_reports_load_data();

if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'xls', 'pdf'], true)) {
    if ($_GET['export'] === 'pdf') {
        uthenga_reports_render_pdf($reportData);
        exit;
    }

    $isExcel = $_GET['export'] === 'xls';
    header('Content-Type: ' . ($isExcel ? 'application/vnd.ms-excel' : 'text/csv') . '; charset=UTF-8');
    header('Content-Disposition: attachment; filename=platform-reports-export.' . ($isExcel ? 'xls' : 'csv'));

    $out = fopen('php://output', 'w');
    $delimiter = $isExcel ? "\t" : ',';

    fputcsv($out, ['--- Category Performance ---'], $delimiter);
    fputcsv($out, ['Category', 'Bookings Count', 'Revenue (MWK)'], $delimiter);
    foreach (['event' => 'Events', 'accommodation' => 'Stays', 'tour' => 'Tours', 'transport' => 'Transport'] as $key => $label) {
        fputcsv($out, [$label, $reportData['chartData'][$key]['count'], $reportData['chartData'][$key]['revenue']], $delimiter);
    }

    fputcsv($out, [''], $delimiter);
    fputcsv($out, ['--- Monthly Booking Volumes ---'], $delimiter);
    fputcsv($out, ['Month', 'Bookings Count', 'Total Revenue (MWK)'], $delimiter);
    foreach ($reportData['monthlyStats'] as $row) {
        fputcsv($out, [$row['month'], $row['count'], $row['total']], $delimiter);
    }

    fputcsv($out, [''], $delimiter);
    fputcsv($out, ['--- Top Performing Products/Listings ---'], $delimiter);
    fputcsv($out, ['Listing Title', 'Category', 'Bookings Count', 'Total Revenue (MWK)'], $delimiter);
    foreach ($reportData['topListings'] as $row) {
        fputcsv($out, [$row['listing_title'], ucfirst($row['listing_type']), $row['bookings_count'], $row['total_revenue']], $delimiter);
    }

    fclose($out);
    exit;
}

$pageTitle = 'Reports & Analytics';
$activeNav = 'admin-reports';

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
  .report-hero-actions { display:flex; gap:.5rem; flex-wrap:wrap; }
  .report-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1rem;
    margin-bottom:1rem;
  }
  .report-panel {
    padding:1.25rem;
    min-width:0;
  }
  .report-panel h3 {
    display:flex;
    align-items:center;
    gap:.5rem;
    font-size:1.05rem;
    margin:0 0 1rem;
  }
  .report-chart-container {
    height:260px;
    display:flex;
    align-items:flex-end;
    justify-content:space-around;
    gap:.75rem;
    padding:1rem .85rem .5rem;
  }
  .report-summary-list {
    display:flex;
    flex-direction:column;
    gap:.85rem;
  }
  .report-summary-row {
    display:grid;
    grid-template-columns:minmax(0,1fr) auto;
    gap:.75rem;
    align-items:center;
  }
  .report-progress {
    height:6px;
    background:rgba(15,23,42,.06);
    border-radius:999px;
    overflow:hidden;
  }
  .report-progress > span {
    display:block;
    height:100%;
    background:linear-gradient(90deg,var(--clr-accent),var(--clr-cyan));
  }
  @media (max-width: 1080px) {
    .report-grid { grid-template-columns:1fr; }
  }
  @media (max-width: 640px) {
    .report-panel { padding:1rem; }
    .report-chart-container { height:auto; min-height:220px; }
    .report-summary-row { grid-template-columns:1fr; }
  }
</style>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= admin_icon_svg('chart') ?><span>Reports & Analytics</span></h1>
    <p class="text-muted">Analyze transaction volumes, category performance, and platform revenue.</p>
  </div>
  <div class="report-hero-actions">
    <a href="?export=csv" class="btn btn-secondary btn-sm"><?= admin_icon_svg('download') ?> CSV</a>
    <a href="?export=xls" class="btn btn-secondary btn-sm"><?= admin_icon_svg('download') ?> Excel</a>
    <a href="?export=pdf" class="btn btn-secondary btn-sm" target="_blank"><?= admin_icon_svg('download') ?> PDF</a>
  </div>
</div>

<div class="report-grid">
  <div class="glass-panel report-panel">
    <h3><?= admin_icon_svg('wallet') ?><span>Revenue by Category</span></h3>
    <div class="report-chart-container">
      <?php foreach (['event' => 'Events', 'accommodation' => 'Stays', 'tour' => 'Tours', 'transport' => 'Transport'] as $key => $label):
        $rev = $reportData['chartData'][$key]['revenue'];
        $pct = $reportData['maxRevenue'] > 0 ? ($rev / $reportData['maxRevenue']) * 100 : 0;
      ?>
        <div class="chart-bar-col">
          <div class="chart-bar" style="height: <?= max(8, $pct) ?>%;">
            <div class="chart-bar-value"><?= number_format($rev / 1000, 0) ?>K</div>
          </div>
          <div class="chart-label"><?= e($label) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="text-xs text-muted" style="text-align:center;margin-top:1rem;">Values shown in thousands of MWK</div>
  </div>

  <div class="glass-panel report-panel">
    <h3><?= admin_icon_svg('activity') ?><span>Monthly Booking Volume</span></h3>
    <?php if (empty($reportData['monthlyStats'])): ?>
      <p class="text-muted">No historical monthly data available.</p>
    <?php else: ?>
      <div class="report-summary-list">
        <?php foreach ($reportData['monthlyStats'] as $row):
          $pct = $reportData['totalBookings'] > 0 ? ($row['count'] / $reportData['totalBookings']) * 100 : 0;
        ?>
          <div class="report-summary-row">
            <div>
              <div style="display:flex;justify-content:space-between;gap:.75rem;font-size:.85rem;margin-bottom:.3rem;">
                <strong><?= e($row['month']) ?></strong>
                <span><?= number_format($row['count']) ?> bookings, <?= formatMWK((float)$row['total']) ?></span>
              </div>
              <div class="report-progress"><span style="width:<?= max(8, $pct) ?>%;"></span></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="glass-panel report-panel">
  <h3><?= admin_icon_svg('report') ?><span>Top Performing Listings</span></h3>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Listing Title</th>
          <th>Category</th>
          <th style="text-align:center;">Confirmed Bookings</th>
          <th style="text-align:right;">Total Revenue</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($reportData['topListings'])): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--clr-text-muted);">No sales data available.</td></tr>
        <?php else: ?>
          <?php foreach ($reportData['topListings'] as $index => $row): ?>
            <tr>
              <td>
                <span style="color:var(--clr-accent);font-weight:700;margin-right:.5rem;">#<?= $index + 1 ?></span>
                <strong><?= e($row['listing_title']) ?></strong>
              </td>
              <td><span class="badge badge-active" style="text-transform:capitalize;"><?= e($row['listing_type']) ?></span></td>
              <td style="text-align:center;"><?= number_format($row['bookings_count']) ?></td>
              <td style="text-align:right;font-weight:700;color:var(--clr-green);"><?= formatMWK((float)$row['total_revenue']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
