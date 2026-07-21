<?php
/**
 * Uthenga - Admin Platform Analytics
 */
$pageTitle = 'Platform Analytics';
$activeNav = 'admin-reports';

require_once __DIR__ . '/includes/admin_header.php';

// 1. Core KPIs
$kpis = dbQueryOne("
    SELECT 
        COALESCE(SUM(grand_total), 0) AS total_revenue,
        COUNT(id) AS total_bookings,
        COALESCE(AVG(grand_total), 0) AS avg_booking_value
    FROM bookings 
    WHERE LOWER(payment_status) = 'paid'
") ?: ['total_revenue' => 0, 'total_bookings' => 0, 'avg_booking_value' => 0];

$initiatedCount = dbCount("SELECT COUNT(*) FROM bookings");
$conversionRate = $initiatedCount > 0 ? ($kpis['total_bookings'] / $initiatedCount) * 100 : 0;

// 2. Revenue by Module
$moduleRevenue = dbQuery("
    SELECT 
        bi.item_type,
        COALESCE(SUM(bi.subtotal), 0) AS revenue,
        COUNT(bi.id) AS sales_count
    FROM booking_items bi
    JOIN bookings b ON b.id = bi.booking_id
    WHERE LOWER(b.payment_status) = 'paid'
    GROUP BY bi.item_type
");

$moduleLabels = [];
$moduleValues = [];
$moduleTypesMap = [
    'event_ticket' => 'Events',
    'property_room' => 'Stays',
    'transport_seat' => 'Transport',
    'tour_package' => 'Tours',
    'vendor_service' => 'Services',
];
foreach ($moduleRevenue as $row) {
    $moduleLabels[] = $moduleTypesMap[$row['item_type']] ?? $row['item_type'];
    $moduleValues[] = (float)$row['revenue'];
}

// 3. Booking Channel Distribution
$channels = dbQuery("
    SELECT booking_channel, COUNT(*) AS count 
    FROM bookings 
    GROUP BY booking_channel
");
$channelLabels = [];
$channelCounts = [];
foreach ($channels as $row) {
    $channelLabels[] = ucfirst($row['booking_channel']);
    $channelCounts[] = (int)$row['count'];
}

// 4. Monthly Platform Revenue (Last 6 Months)
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
$monthLabels = json_encode(array_column($monthlyRevenue, 'month'));
$monthValues = json_encode(array_map(fn($r) => (float)$r['revenue'], $monthlyRevenue));

// 5. Popular Destinations (Top 10 Cities)
$destinations = dbQuery("
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
    LIMIT 10
");

?>
<div class="page-header">
  <div>
    <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('report') ?><span>Platform Analytics</span></h1>
    <p class="text-muted">High-level sales analysis, conversions, and popular booking channels across Uthenga.</p>
  </div>
</div>

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card">
    <div class="stat-icon stat-icon-blue"><?= admin_icon_svg('credit-card') ?></div>
    <div>
      <div class="stat-value"><?= formatMWK($kpis['total_revenue']) ?></div>
      <div class="stat-label">Total Paid Revenue</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-green"><?= admin_icon_svg('file') ?></div>
    <div>
      <div class="stat-value"><?= number_format($kpis['total_bookings']) ?></div>
      <div class="stat-label">Paid Bookings (<?= number_format($initiatedCount) ?> initiated)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('activity') ?></div>
    <div>
      <div class="stat-value"><?= number_format($conversionRate, 1) ?>%</div>
      <div class="stat-label">Initiation Conversion Rate</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon stat-icon-purple"><?= admin_icon_svg('grid') ?></div>
    <div>
      <div class="stat-value"><?= formatMWK($kpis['avg_booking_value']) ?></div>
      <div class="stat-label">Average Order Value (AOV)</div>
    </div>
  </div>
</div>

<div class="grid grid-cols-2 gap-3" style="margin-bottom:1.5rem;">
  <!-- Revenue Line Chart -->
  <section class="glass-panel">
    <div class="section-head">
      <div>
        <h3 style="display:flex;align-items:center;gap:0.45rem;"><?= admin_icon_svg('activity') ?><span>Monthly Revenue</span></h3>
        <p class="text-xs text-muted">Paid revenue over the last 6 months.</p>
      </div>
    </div>
    <div style="height: 280px; position: relative;">
      <canvas id="monthlyRevenueChart"></canvas>
    </div>
  </section>

  <!-- Revenue by Module Chart -->
  <section class="glass-panel">
    <div class="section-head">
      <div>
        <h3 style="display:flex;align-items:center;gap:0.45rem;"><?= admin_icon_svg('credit-card') ?><span>Revenue by Module</span></h3>
        <p class="text-xs text-muted">Distribution of sales volume across modules.</p>
      </div>
    </div>
    <div style="height: 280px; position: relative;">
      <canvas id="moduleRevenueChart"></canvas>
    </div>
  </section>
</div>

<div class="grid grid-cols-2 gap-3">
  <!-- Booking Channels -->
  <section class="glass-panel">
    <div class="section-head">
      <div>
        <h3 style="display:flex;align-items:center;gap:0.45rem;"><?= admin_icon_svg('grid') ?><span>Booking Channels</span></h3>
        <p class="text-xs text-muted">Where do customers initiate bookings?</p>
      </div>
    </div>
    <div style="height: 250px; position: relative;">
      <canvas id="channelChart"></canvas>
    </div>
  </section>

  <!-- Popular Destinations -->
  <section class="glass-panel">
    <div class="section-head">
      <div>
        <h3 style="display:flex;align-items:center;gap:0.45rem;"><?= admin_icon_svg('map') ?><span>Popular Destinations</span></h3>
        <p class="text-xs text-muted">Top 10 regions/destinations by booking counts.</p>
      </div>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>City / Destination</th>
            <th>Bookings Count</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($destinations)): ?>
            <tr><td colspan="2" class="text-muted">No data available.</td></tr>
          <?php else: ?>
            <?php foreach ($destinations as $d): ?>
              <tr>
                <td><strong>City: <?= e($d['city']) ?></strong></td>
                <td><?= number_format($d['bookings_count']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartConfig = {
  color: 'rgba(255,255,255,.7)',
  font: { family: 'Inter, sans-serif', size: 12 },
};
Chart.defaults.color = chartConfig.color;
Chart.defaults.font  = chartConfig.font;

// Monthly Revenue Line Chart
new Chart(document.getElementById('monthlyRevenueChart'), {
  type: 'line',
  data: {
    labels: <?= $monthLabels ?>,
    datasets: [{
      label: 'Revenue (MK)',
      data: <?= $monthValues ?>,
      borderColor: '#38bdf8',
      backgroundColor: 'rgba(56,189,248,0.1)',
      borderWidth: 3,
      fill: true,
      tension: 0.3
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

// Module Distribution Pie Chart
new Chart(document.getElementById('moduleRevenueChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($moduleLabels) ?>,
    datasets: [{
      data: <?= json_encode($moduleValues) ?>,
      backgroundColor: ['#38bdf8', '#10b981', '#f59e0b', '#a855f7', '#ef4444'],
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'right', labels: { boxWidth: 12, padding: 12 } }
    }
  }
});

// Booking Channels Chart
new Chart(document.getElementById('channelChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($channelLabels) ?>,
    datasets: [{
      data: <?= json_encode($channelCounts) ?>,
      backgroundColor: 'rgba(168,85,247,0.7)',
      borderColor: '#a855f7',
      borderWidth: 1.5,
      borderRadius: 4
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
