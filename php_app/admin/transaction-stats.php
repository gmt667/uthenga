<?php
/**
 * Uthenga — Admin Transaction Statistics Dashboard
 * Provides daily/monthly KPIs, gateway breakdown, and Chart.js visualisations.
 */
$pageTitle = 'Transaction Statistics';
$activeNav = 'admin-transactions';

require_once __DIR__ . '/includes/admin_header.php';

// ── Date range filter ─────────────────────────────────────────────────────────
$dateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));         // first of this month
$dateTo   = trim($_GET['date_to']   ?? date('Y-m-d'));          // today
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))   $dateTo   = date('Y-m-d');
if ($dateTo < $dateFrom) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

$transactionsTableExists = uthenga_table_exists('transactions');
$analyticsTableExists    = uthenga_table_exists('transaction_analytics');

// ── Helper: safe zero-fallback query ─────────────────────────────────────────
function stats_qone(string $sql, array $params = [], mixed $fallback = 0): mixed {
    if (!uthenga_db_is_available()) return $fallback;
    try {
        return dbQueryOne($sql, $params) ?: $fallback;
    } catch (Throwable) {
        return $fallback;
    }
}
function stats_q(string $sql, array $params = [], array $fallback = []): array {
    if (!uthenga_db_is_available()) return $fallback;
    try {
        return dbQuery($sql, $params) ?: $fallback;
    } catch (Throwable) {
        return $fallback;
    }
}

// ── KPI — date-ranged totals ──────────────────────────────────────────────────
if ($transactionsTableExists) {
    $kpiRow = stats_qone(
        "SELECT
            COUNT(*) AS total_txns,
            COALESCE(SUM(CASE WHEN LOWER(status) IN ('success','paid') THEN 1 END), 0) AS success_count,
            COALESCE(SUM(CASE WHEN LOWER(status) = 'failed' THEN 1 END), 0) AS failed_count,
            COALESCE(SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 END), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN LOWER(status) IN ('success','paid') THEN amount ELSE 0 END), 0) AS total_revenue,
            COALESCE(AVG(CASE WHEN LOWER(status) IN ('success','paid') THEN amount END), 0) AS avg_txn_value
         FROM transactions
         WHERE DATE(created_at) BETWEEN ? AND ?",
        [$dateFrom, $dateTo],
        ['total_txns'=>0,'success_count'=>0,'failed_count'=>0,'pending_count'=>0,'total_revenue'=>0,'avg_txn_value'=>0]
    );
} else {
    $kpiRow = ['total_txns'=>0,'success_count'=>0,'failed_count'=>0,'pending_count'=>0,'total_revenue'=>0,'avg_txn_value'=>0];
}

$conversionRate = $kpiRow['total_txns'] > 0
    ? round(($kpiRow['success_count'] / $kpiRow['total_txns']) * 100, 1)
    : 0;

// ── Daily chart data (last 30 days capped at range) ───────────────────────────
$dailyRows = $transactionsTableExists ? stats_q(
    "SELECT
        DATE(created_at) AS txn_date,
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN LOWER(status) IN ('success','paid') THEN 1 END), 0) AS success_ct,
        COALESCE(SUM(CASE WHEN LOWER(status) = 'failed' THEN 1 END), 0) AS failed_ct,
        COALESCE(SUM(CASE WHEN LOWER(status) IN ('success','paid') THEN amount ELSE 0 END), 0) AS revenue
     FROM transactions
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY txn_date ASC",
    [$dateFrom, $dateTo]
) : [];

$dailyLabels   = json_encode(array_column($dailyRows, 'txn_date'));
$dailyTotal    = json_encode(array_map(fn($r) => (int)$r['total'], $dailyRows));
$dailySuccess  = json_encode(array_map(fn($r) => (int)$r['success_ct'], $dailyRows));
$dailyFailed   = json_encode(array_map(fn($r) => (int)$r['failed_ct'], $dailyRows));
$dailyRevenue  = json_encode(array_map(fn($r) => (float)$r['revenue'], $dailyRows));

// ── Monthly chart data (last 12 months) ───────────────────────────────────────
$monthlyRows = $transactionsTableExists ? stats_q(
    "SELECT
        DATE_FORMAT(created_at, '%b %Y') AS month_label,
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN LOWER(status) IN ('success','paid') THEN amount ELSE 0 END), 0) AS revenue
     FROM transactions
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY month_key, month_label
     ORDER BY month_key ASC"
) : [];

$monthLabels  = json_encode(array_column($monthlyRows, 'month_label'));
$monthRevenue = json_encode(array_map(fn($r) => (float)$r['revenue'], $monthlyRows));
$monthTotal   = json_encode(array_map(fn($r) => (int)$r['total'], $monthlyRows));

// ── Gateway breakdown ─────────────────────────────────────────────────────────
$gatewayRows = $transactionsTableExists ? stats_q(
    "SELECT
        COALESCE(gateway_name, 'Unknown') AS gateway,
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN LOWER(status) IN ('success','paid') THEN amount ELSE 0 END), 0) AS revenue,
        ROUND(
            100.0 * SUM(CASE WHEN LOWER(status) IN ('success','paid') THEN 1 ELSE 0 END) / COUNT(*), 1
        ) AS success_rate
     FROM transactions
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY gateway_name
     ORDER BY total DESC",
    [$dateFrom, $dateTo]
) : [];

$gatewayLabels    = json_encode(array_column($gatewayRows, 'gateway'));
$gatewayRevenue   = json_encode(array_map(fn($r) => (float)$r['revenue'], $gatewayRows));
$gatewayTotals    = json_encode(array_map(fn($r) => (int)$r['total'], $gatewayRows));

// ── Recent failed transactions ────────────────────────────────────────────────
$recentFailed = $transactionsTableExists ? stats_q(
    "SELECT t.transaction_reference, t.amount, t.gateway_name, t.status, t.created_at,
            u.name AS customer_name, u.email AS customer_email
     FROM transactions t
     LEFT JOIN users u ON u.id = t.user_id
     WHERE LOWER(t.status) = 'failed'
       AND DATE(t.created_at) BETWEEN ? AND ?
     ORDER BY t.created_at DESC
     LIMIT 10",
    [$dateFrom, $dateTo]
) : [];

// ── Hourly heatmap data ────────────────────────────────────────────────────────
$hourlyRows = $transactionsTableExists ? stats_q(
    "SELECT HOUR(created_at) AS hr, COUNT(*) AS cnt
     FROM transactions
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY HOUR(created_at)
     ORDER BY hr",
    [$dateFrom, $dateTo]
) : [];

$hourlyMap = array_fill(0, 24, 0);
foreach ($hourlyRows as $h) { $hourlyMap[(int)$h['hr']] = (int)$h['cnt']; }
$hourlyData   = json_encode(array_values($hourlyMap));
$hourlyLabels = json_encode(array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23)));
?>

<div class="admin-content-header">
  <div>
    <h1 class="admin-content-title">Transaction Statistics</h1>
    <p class="admin-content-subtitle">Payment performance, gateway analytics, and revenue trends</p>
  </div>
  <div style="display:flex;gap:.75rem;align-items:center;">
    <a href="<?= BASE_URL ?>admin/transactions.php" class="btn btn-secondary btn-sm">View Ledger</a>
    <a href="transactions.php?export=csv&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-sm btn-outline">⬇ Export CSV</a>
  </div>
</div>

<!-- Date Filter -->
<div class="glass-panel" style="padding:1.25rem;margin-bottom:2rem;">
  <form method="GET" action="transaction-stats.php" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
    <div class="form-group" style="margin-bottom:0;flex:1;min-width:160px;">
      <label class="form-label">From</label>
      <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
    </div>
    <div class="form-group" style="margin-bottom:0;flex:1;min-width:160px;">
      <label class="form-label">To</label>
      <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
    <a href="transaction-stats.php" class="btn btn-secondary">Reset</a>
  </form>
</div>

<!-- KPI Row -->
<div class="kpi-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1.25rem;margin-bottom:2rem;">

  <div class="kpi-card" style="background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-lg);padding:1.5rem;">
    <div style="font-size:.75rem;color:var(--clr-muted);text-transform:uppercase;font-weight:600;letter-spacing:.05em;margin-bottom:.5rem;">Total Transactions</div>
    <div style="font-size:2rem;font-weight:800;"><?= number_format($kpiRow['total_txns']) ?></div>
    <div style="font-size:.75rem;color:var(--clr-muted);margin-top:.25rem;"><?= e($dateFrom) ?> → <?= e($dateTo) ?></div>
  </div>

  <div class="kpi-card" style="background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-lg);padding:1.5rem;">
    <div style="font-size:.75rem;color:var(--clr-muted);text-transform:uppercase;font-weight:600;letter-spacing:.05em;margin-bottom:.5rem;">Revenue Cleared</div>
    <div style="font-size:2rem;font-weight:800;color:var(--clr-success);">MK <?= number_format($kpiRow['total_revenue']) ?></div>
    <div style="font-size:.75rem;color:var(--clr-muted);margin-top:.25rem;">Avg MK <?= number_format($kpiRow['avg_txn_value']) ?> / txn</div>
  </div>

  <div class="kpi-card" style="background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-lg);padding:1.5rem;">
    <div style="font-size:.75rem;color:var(--clr-muted);text-transform:uppercase;font-weight:600;letter-spacing:.05em;margin-bottom:.5rem;">Success Rate</div>
    <div style="font-size:2rem;font-weight:800;color:<?= $conversionRate >= 70 ? 'var(--clr-success)' : ($conversionRate >= 40 ? 'var(--clr-warning, #f59e0b)' : 'var(--clr-danger)') ?>;"><?= $conversionRate ?>%</div>
    <div style="font-size:.75rem;color:var(--clr-muted);margin-top:.25rem;"><?= number_format($kpiRow['success_count']) ?> of <?= number_format($kpiRow['total_txns']) ?> successful</div>
  </div>

  <div class="kpi-card" style="background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-lg);padding:1.5rem;">
    <div style="font-size:.75rem;color:var(--clr-muted);text-transform:uppercase;font-weight:600;letter-spacing:.05em;margin-bottom:.5rem;">Failed</div>
    <div style="font-size:2rem;font-weight:800;color:var(--clr-danger);"><?= number_format($kpiRow['failed_count']) ?></div>
    <div style="font-size:.75rem;color:var(--clr-muted);margin-top:.25rem;"><?= number_format($kpiRow['pending_count']) ?> pending</div>
  </div>

</div>

<!-- Charts Row 1: Daily volume + Monthly revenue -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

  <div class="glass-panel" style="padding:1.5rem;">
    <h3 style="margin-bottom:1rem;font-size:1rem;">Daily Transaction Volume</h3>
    <?php if (empty($dailyRows)): ?>
      <div style="text-align:center;padding:3rem 0;color:var(--clr-muted);">No data for the selected period.</div>
    <?php else: ?>
      <canvas id="chartDaily" height="220"></canvas>
    <?php endif; ?>
  </div>

  <div class="glass-panel" style="padding:1.5rem;">
    <h3 style="margin-bottom:1rem;font-size:1rem;">Monthly Revenue (Last 12 Months)</h3>
    <?php if (empty($monthlyRows)): ?>
      <div style="text-align:center;padding:3rem 0;color:var(--clr-muted);">No data available.</div>
    <?php else: ?>
      <canvas id="chartMonthly" height="220"></canvas>
    <?php endif; ?>
  </div>

</div>

<!-- Charts Row 2: Gateway pie + Hourly heatmap -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">

  <div class="glass-panel" style="padding:1.5rem;">
    <h3 style="margin-bottom:1rem;font-size:1rem;">Revenue by Payment Gateway</h3>
    <?php if (empty($gatewayRows)): ?>
      <div style="text-align:center;padding:3rem 0;color:var(--clr-muted);">No gateway data for this period.</div>
    <?php else: ?>
      <canvas id="chartGateway" height="260"></canvas>
    <?php endif; ?>
  </div>

  <div class="glass-panel" style="padding:1.5rem;">
    <h3 style="margin-bottom:1rem;font-size:1rem;">Transactions by Hour of Day</h3>
    <?php if (array_sum($hourlyMap) === 0): ?>
      <div style="text-align:center;padding:3rem 0;color:var(--clr-muted);">No hourly data for this period.</div>
    <?php else: ?>
      <canvas id="chartHourly" height="260"></canvas>
    <?php endif; ?>
  </div>

</div>

<!-- Gateway breakdown table -->
<?php if (!empty($gatewayRows)): ?>
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;">
  <h3 style="margin-bottom:1rem;font-size:1rem;">Gateway Performance Breakdown</h3>
  <div style="overflow-x:auto;">
    <table class="table">
      <thead>
        <tr>
          <th>Gateway</th>
          <th>Total Txns</th>
          <th>Revenue (MWK)</th>
          <th>Success Rate</th>
          <th>Status Bar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($gatewayRows as $gw): ?>
          <tr>
            <td><strong><?= e($gw['gateway']) ?></strong></td>
            <td><?= number_format($gw['total']) ?></td>
            <td><?= number_format($gw['revenue']) ?></td>
            <td>
              <span style="color: <?= $gw['success_rate'] >= 70 ? 'var(--clr-success)' : ($gw['success_rate'] >= 40 ? 'var(--clr-warning, #f59e0b)' : 'var(--clr-danger)') ?>; font-weight:700;">
                <?= $gw['success_rate'] ?>%
              </span>
            </td>
            <td style="min-width:120px;">
              <div style="background:var(--clr-border);border-radius:4px;height:8px;overflow:hidden;">
                <div style="width:<?= min(100,(float)$gw['success_rate']) ?>%;height:100%;background:<?= $gw['success_rate'] >= 70 ? 'var(--clr-success)' : ($gw['success_rate'] >= 40 ? '#f59e0b' : 'var(--clr-danger)') ?>;border-radius:4px;"></div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Recent Failed Transactions -->
<?php if (!empty($recentFailed)): ?>
<div class="glass-panel" style="padding:1.5rem;margin-bottom:2rem;">
  <h3 style="margin-bottom:1rem;font-size:1rem;color:var(--clr-danger);">⚠ Recent Failed Transactions</h3>
  <div style="overflow-x:auto;">
    <table class="table">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Customer</th>
          <th>Gateway</th>
          <th>Amount (MWK)</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentFailed as $f): ?>
          <tr>
            <td><code style="font-size:.8rem;"><?= e($f['transaction_reference']) ?></code></td>
            <td>
              <div><?= e($f['customer_name'] ?? 'Unknown') ?></div>
              <div style="font-size:.75rem;color:var(--clr-muted);"><?= e($f['customer_email'] ?? '') ?></div>
            </td>
            <td><?= e($f['gateway_name'] ?? '—') ?></td>
            <td><?= number_format((float)$f['amount']) ?></td>
            <td style="font-size:.8rem;color:var(--clr-muted);"><?= e($f['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!$transactionsTableExists): ?>
<div class="alert alert-danger" style="margin-bottom:2rem;">
  <strong>Database Notice:</strong> The <code>transactions</code> table is not yet present in this environment.
  Run <a href="<?= BASE_URL ?>admin/system-monitor.php">database migration</a> to activate statistics.
</div>
<?php endif; ?>

<!-- Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function() {
    const isDark  = document.body.classList.contains('dark') || document.documentElement.getAttribute('data-theme') === 'dark';
    const gridCol = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
    const txtCol  = isDark ? '#a0aec0' : '#6b7280';

    const baseOpts = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: txtCol, font: { family: 'Inter' } } } },
        scales: {
            x: { ticks: { color: txtCol }, grid: { color: gridCol } },
            y: { ticks: { color: txtCol }, grid: { color: gridCol }, beginAtZero: true },
        }
    };

    // Daily volume chart
    const dailyEl = document.getElementById('chartDaily');
    if (dailyEl) {
        new Chart(dailyEl, {
            type: 'line',
            data: {
                labels: <?= $dailyLabels ?>,
                datasets: [
                    {
                        label: 'Total',
                        data: <?= $dailyTotal ?>,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                    },
                    {
                        label: 'Success',
                        data: <?= $dailySuccess ?>,
                        borderColor: '#22c55e',
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        pointRadius: 3,
                    },
                    {
                        label: 'Failed',
                        data: <?= $dailyFailed ?>,
                        borderColor: '#ef4444',
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        pointRadius: 3,
                    }
                ]
            },
            options: { ...baseOpts, plugins: { ...baseOpts.plugins } }
        });
    }

    // Monthly revenue chart
    const monthlyEl = document.getElementById('chartMonthly');
    if (monthlyEl) {
        new Chart(monthlyEl, {
            type: 'bar',
            data: {
                labels: <?= $monthLabels ?>,
                datasets: [
                    {
                        label: 'Revenue (MWK)',
                        data: <?= $monthRevenue ?>,
                        backgroundColor: 'rgba(99,102,241,0.7)',
                        borderColor: '#6366f1',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'yRev',
                    },
                    {
                        label: 'Transactions',
                        data: <?= $monthTotal ?>,
                        type: 'line',
                        borderColor: '#f59e0b',
                        backgroundColor: 'transparent',
                        tension: 0.4,
                        pointRadius: 4,
                        yAxisID: 'yTxn',
                    }
                ]
            },
            options: {
                ...baseOpts,
                scales: {
                    x: { ticks: { color: txtCol }, grid: { color: gridCol } },
                    yRev: { position: 'left', ticks: { color: txtCol }, grid: { color: gridCol }, beginAtZero: true },
                    yTxn: { position: 'right', ticks: { color: txtCol }, grid: { display: false }, beginAtZero: true },
                }
            }
        });
    }

    // Gateway doughnut
    const gatewayEl = document.getElementById('chartGateway');
    if (gatewayEl) {
        new Chart(gatewayEl, {
            type: 'doughnut',
            data: {
                labels: <?= $gatewayLabels ?>,
                datasets: [{
                    data: <?= $gatewayRevenue ?>,
                    backgroundColor: ['#6366f1','#22c55e','#f59e0b','#3b82f6','#ec4899','#14b8a6'],
                    borderWidth: 2,
                    borderColor: isDark ? '#1a1a2e' : '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { color: txtCol, font: { family: 'Inter' } } } }
            }
        });
    }

    // Hourly bar chart
    const hourlyEl = document.getElementById('chartHourly');
    if (hourlyEl) {
        new Chart(hourlyEl, {
            type: 'bar',
            data: {
                labels: <?= $hourlyLabels ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?= $hourlyData ?>,
                    backgroundColor: 'rgba(99,102,241,0.6)',
                    borderColor: '#6366f1',
                    borderWidth: 1,
                    borderRadius: 3,
                }]
            },
            options: {
                ...baseOpts,
                plugins: { legend: { display: false } },
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
