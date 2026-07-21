<?php
/**
 * Uthenga - Admin Transactions Ledger
 */
$pageTitle = 'Transaction Ledger';
$activeNav = 'admin-transactions';

require_once __DIR__ . '/includes/admin_header.php';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expStatus  = strtolower($_GET['status'] ?? 'all');
    $expGateway = trim($_GET['gateway'] ?? 'all');
    $expSearch  = trim($_GET['q'] ?? '');
    $expFrom    = $_GET['date_from'] ?? '';
    $expTo      = $_GET['date_to'] ?? '';

    $expWhere  = ['1=1'];
    $expParams = [];
    if ($expStatus !== 'all') {
        $expWhere[] = 'LOWER(t.status) = ?';
        $expParams[] = $expStatus;
    }
    if ($expGateway !== 'all') {
        $expWhere[] = 'COALESCE(t.gateway_name, "") = ?';
        $expParams[] = $expGateway;
    }
    if ($expSearch !== '') {
        $expWhere[] = '(t.transaction_reference LIKE ? OR CAST(t.booking_id AS CHAR) LIKE ? OR CAST(t.user_id AS CHAR) LIKE ? OR b.booking_code LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
        $expParams[] = "%$expSearch%";
        $expParams[] = "%$expSearch%";
        $expParams[] = "%$expSearch%";
        $expParams[] = "%$expSearch%";
        $expParams[] = "%$expSearch%";
        $expParams[] = "%$expSearch%";
    }
    if ($expFrom) {
        $expWhere[] = 'DATE(t.created_at) >= ?';
        $expParams[] = $expFrom;
    }
    if ($expTo) {
        $expWhere[] = 'DATE(t.created_at) <= ?';
        $expParams[] = $expTo;
    }

    $expRows = dbQuery(
        'SELECT t.id, t.transaction_reference, t.booking_id, t.user_id, t.amount, t.gateway_name, t.transaction_type, t.status, t.created_at, b.booking_code, u.name AS customer_name, u.email AS customer_email
         FROM transactions t
         LEFT JOIN bookings b ON b.id = t.booking_id
         LEFT JOIN users u ON u.id = t.user_id
         WHERE ' . implode(' AND ', $expWhere) . '
         ORDER BY t.created_at DESC',
        $expParams
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');
    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['Transaction ID', 'Reference', 'Booking ID', 'Booking Code', 'Customer ID', 'Customer Name', 'Customer Email', 'Amount (MWK)', 'Gateway', 'Type', 'Status', 'Date']);
    foreach ($expRows as $r) {
        fputcsv($fp, [
            $r['id'],
            $r['transaction_reference'],
            $r['booking_id'],
            $r['booking_code'],
            $r['user_id'],
            $r['customer_name'],
            $r['customer_email'],
            number_format((float)$r['amount'], 2, '.', ''),
            $r['gateway_name'],
            $r['transaction_type'] ?? '',
            $r['status'],
            $r['created_at'],
        ]);
    }
    fclose($fp);
    exit;
}

$kpi = [
    'cleared_amount' => (float)(dbQueryOne("SELECT COALESCE(SUM(amount),0) AS v FROM transactions WHERE LOWER(status) IN ('success','paid')")['v'] ?? 0),
    'cleared_count'  => (int)(dbQueryOne("SELECT COUNT(*) AS v FROM transactions WHERE LOWER(status) IN ('success','paid')")['v'] ?? 0),
    'pending_amount' => (float)(dbQueryOne("SELECT COALESCE(SUM(amount),0) AS v FROM transactions WHERE LOWER(status)='pending'")['v'] ?? 0),
    'pending_count'  => (int)(dbQueryOne("SELECT COUNT(*) AS v FROM transactions WHERE LOWER(status)='pending'")['v'] ?? 0),
    'failed_amount'  => (float)(dbQueryOne("SELECT COALESCE(SUM(amount),0) AS v FROM transactions WHERE LOWER(status)='failed'")['v'] ?? 0),
    'failed_count'   => (int)(dbQueryOne("SELECT COUNT(*) AS v FROM transactions WHERE LOWER(status)='failed'")['v'] ?? 0),
    'refunded_amount'=> (float)(dbQueryOne("SELECT COALESCE(SUM(amount),0) AS v FROM transactions WHERE LOWER(status)='refunded'")['v'] ?? 0),
    'refunded_count' => (int)(dbQueryOne("SELECT COUNT(*) AS v FROM transactions WHERE LOWER(status)='refunded'")['v'] ?? 0),
    'total_count'    => (int)(dbQueryOne("SELECT COUNT(*) AS v FROM transactions")['v'] ?? 0),
];

$filterStatus  = strtolower($_GET['status'] ?? 'all');
$filterGateway = trim($_GET['gateway'] ?? 'all');
$search        = trim($_GET['q'] ?? '');
$dateFrom      = $_GET['date_from'] ?? '';
$dateTo        = $_GET['date_to'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 20;

$where  = ['1=1'];
$params = [];
if ($filterStatus !== 'all') {
    if ($filterStatus === 'success') {
        $where[] = 'LOWER(t.status) IN (\'success\', \'paid\')';
    } else {
        $where[] = 'LOWER(t.status) = ?';
        $params[] = $filterStatus;
    }
}
if ($filterGateway !== 'all') {
    $where[] = 'COALESCE(t.gateway_name, "") = ?';
    $params[] = $filterGateway;
}
if ($search !== '') {
    $where[] = '(t.transaction_reference LIKE ? OR CAST(t.booking_id AS CHAR) LIKE ? OR CAST(t.user_id AS CHAR) LIKE ? OR b.booking_code LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($dateFrom !== '') {
    $where[] = 'DATE(t.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(t.created_at) <= ?';
    $params[] = $dateTo;
}

$whereStr   = implode(' AND ', $where);
$totalCount = dbCount("SELECT COUNT(*) FROM transactions t LEFT JOIN bookings b ON b.id = t.booking_id LEFT JOIN users u ON u.id = t.user_id WHERE $whereStr", $params);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$offset     = ($page - 1) * $perPage;

$txns = dbQuery(
    "SELECT t.id, t.transaction_reference, t.booking_id, t.user_id, t.amount, t.gateway_name, t.transaction_type, t.status, t.created_at, t.updated_at,
            b.booking_code, b.reference_name, u.name AS customer_name, u.email AS customer_email
     FROM transactions t
     LEFT JOIN bookings b ON b.id = t.booking_id
     LEFT JOIN users u ON u.id = t.user_id
     WHERE $whereStr
     ORDER BY t.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

$gatewayOptions = dbQuery(
    "SELECT DISTINCT gateway_name
     FROM transactions
     WHERE gateway_name IS NOT NULL AND gateway_name <> ''
     ORDER BY gateway_name"
);

$txnAnalytics = uthenga_transaction_status_summary(30);
$txnDailyLabels = [];
$txnDailyRevenue = [];
$txnDailyCounts = [];
foreach ($txnAnalytics['daily'] as $row) {
    $txnDailyLabels[] = (string)($row['event_date'] ?? '');
    $txnDailyRevenue[] = (float)($row['revenue'] ?? 0);
    $txnDailyCounts[] = (int)($row['total_transactions'] ?? 0);
}

$txnMethodLabels = [];
$txnMethodRevenue = [];
foreach ($txnAnalytics['by_method'] as $row) {
    $txnMethodLabels[] = (string)($row['payment_method'] ?? 'Unknown');
    $txnMethodRevenue[] = (float)($row['revenue'] ?? 0);
}

function txStatusBadge(string $s): string {
    return match (strtolower($s)) {
        'success' => 'badge-approved',
        'refunded' => 'badge-refunded',
        'failed' => 'badge-cancelled',
        default => 'badge-pending',
    };
}

function paginationQs(array $base, int $page): string {
    $base['page'] = $page;
    return '?' . http_build_query($base);
}

$filterQs = array_filter([
    'status'    => $filterStatus !== 'all' ? $filterStatus : null,
    'gateway'   => $filterGateway !== 'all' ? $filterGateway : null,
    'q'         => $search !== '' ? $search : null,
    'date_from' => $dateFrom !== '' ? $dateFrom : null,
    'date_to'   => $dateTo !== '' ? $dateTo : null,
]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= admin_icon_svg('credit-card') ?> Transaction Ledger</h1>
    <p class="text-muted">Full audit trail of all platform payment transactions.</p>
  </div>
  <div class="dashboard-head-meta">
    <a href="<?= 'transactions.php?' . http_build_query(array_merge($filterQs, ['export' => 'csv'])) ?>" class="btn btn-secondary btn-sm" id="txn-export-csv">
      <?= admin_icon_svg('download') ?> Export CSV
    </a>
    <a href="payments.php" class="btn btn-ghost btn-sm" id="txn-goto-payments"><?= admin_icon_svg('settings') ?> Financial Settings</a>
  </div>
</div>

<div class="txn-kpi-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem;">
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-accent,#7c3aed);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">Analytics Window</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-accent,#7c3aed);">30 Days</div>
    <div class="text-xs text-muted">rolling transaction summary</div>
  </div>
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-success,#22c55e);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">Successful</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-success,#22c55e);"><?= number_format($txnAnalytics['successful_payments']) ?></div>
    <div class="text-xs text-muted"><?= formatMWK($txnAnalytics['revenue']) ?> revenue</div>
  </div>
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-warning,#f59e0b);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">Pending</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-warning,#f59e0b);"><?= number_format($txnAnalytics['pending_payments']) ?></div>
    <div class="text-xs text-muted">awaiting gateway confirmation</div>
  </div>
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-danger,#ef4444);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">Failed</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-danger,#ef4444);"><?= number_format($txnAnalytics['failed_payments']) ?></div>
    <div class="text-xs text-muted">needs retry or review</div>
  </div>
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-cyan,#06b6d4);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">Bookings Logged</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-cyan,#06b6d4);"><?= number_format($txnAnalytics['booking_count']) ?></div>
    <div class="text-xs text-muted">from analytics events</div>
  </div>
</div>

<div class="glass-panel animate-in" style="padding:1.5rem;margin-bottom:2rem;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
    <div>
      <h3 style="margin:0 0 0.25rem;">Transaction Analytics</h3>
      <p class="text-xs text-muted" style="margin:0;">Daily revenue and transaction counts from the new analytics ledger.</p>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
    <div style="min-height:280px;">
      <canvas id="txnDailyChart"></canvas>
    </div>
    <div style="display:grid;gap:0.75rem;align-content:start;">
      <?php foreach ($txnAnalytics['by_method'] as $row): ?>
        <div style="padding:0.9rem 1rem;border:1px solid var(--clr-border);border-radius:0.75rem;background:var(--clr-surface-2,rgba(255,255,255,0.04));">
          <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;">
            <strong><?= e($row['payment_method'] ?? 'Unknown') ?></strong>
            <span class="text-xs text-muted"><?= number_format((int)($row['total_transactions'] ?? 0)) ?> txns</span>
          </div>
          <div style="font-size:1.1rem;font-weight:800;margin-top:0.35rem;"><?= formatMWK((float)($row['revenue'] ?? 0)) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="txn-kpi-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:2rem;">
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-success,#22c55e);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">Cleared</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-success,#22c55e);"><?= formatMWK($kpi['cleared_amount']) ?></div>
    <div class="text-xs text-muted"><?= number_format($kpi['cleared_count']) ?> transaction<?= $kpi['cleared_count'] !== 1 ? 's' : '' ?></div>
  </div>
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-warning,#f59e0b);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">Pending</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-warning,#f59e0b);"><?= formatMWK($kpi['pending_amount']) ?></div>
    <div class="text-xs text-muted"><?= number_format($kpi['pending_count']) ?> transaction<?= $kpi['pending_count'] !== 1 ? 's' : '' ?></div>
  </div>
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-danger,#ef4444);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">Failed</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-danger,#ef4444);"><?= formatMWK($kpi['failed_amount']) ?></div>
    <div class="text-xs text-muted"><?= number_format($kpi['failed_count']) ?> transaction<?= $kpi['failed_count'] !== 1 ? 's' : '' ?></div>
  </div>
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-cyan,#06b6d4);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">Refunded</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-cyan,#06b6d4);"><?= formatMWK($kpi['refunded_amount']) ?></div>
    <div class="text-xs text-muted"><?= number_format($kpi['refunded_count']) ?> transaction<?= $kpi['refunded_count'] !== 1 ? 's' : '' ?></div>
  </div>
  <div class="glass-panel animate-in txn-kpi-card" style="padding:1.25rem;border-left:4px solid var(--clr-accent,#7c3aed);">
    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-text-muted);margin-bottom:0.35rem;">All Transactions</div>
    <div style="font-size:1.5rem;font-weight:800;color:var(--clr-accent,#7c3aed);"><?= number_format($kpi['total_count']) ?></div>
    <div class="text-xs text-muted">across all gateways</div>
  </div>
</div>

<div class="glass-panel animate-in" style="padding:1.5rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
    <h3 style="font-size:1.1rem;margin:0;display:flex;align-items:center;gap:0.5rem;"><?= admin_icon_svg('report') ?> Transaction History</h3>
    <span class="text-xs text-muted">Showing <strong><?= number_format(count($txns)) ?></strong> of <strong><?= number_format($totalCount) ?></strong> results</span>
  </div>

  <form method="GET" id="txn-filter-form" style="display:flex;gap:0.65rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:1.5rem;padding:1rem;background:var(--clr-surface-2,rgba(255,255,255,0.04));border-radius:0.625rem;">
    <div style="flex:1;min-width:200px;">
      <label class="form-label" style="font-size:0.72rem;margin-bottom:0.3rem;">Search</label>
      <input type="text" name="q" id="txn-search" placeholder="Ref, booking, user, customer..." class="form-control" style="height:2.25rem;" value="<?= e($search) ?>">
    </div>
    <div style="min-width:150px;">
      <label class="form-label" style="font-size:0.72rem;margin-bottom:0.3rem;">Status</label>
      <select name="status" id="txn-status" class="form-control" style="height:2.25rem;">
        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
        <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>><?= admin_icon_svg('check') ?> Success</option>
        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>><?= admin_icon_svg('clock') ?> Pending</option>
        <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>><?= admin_icon_svg('close') ?> Failed</option>
        <option value="refunded"<?= $filterStatus === 'refunded' ? 'selected' : '' ?>><?= admin_icon_svg('cash') ?> Refunded</option>
      </select>
    </div>
    <div style="min-width:180px;">
      <label class="form-label" style="font-size:0.72rem;margin-bottom:0.3rem;">Gateway</label>
      <select name="gateway" id="txn-gateway" class="form-control" style="height:2.25rem;">
        <option value="all" <?= $filterGateway === 'all' ? 'selected' : '' ?>>All Gateways</option>
        <?php foreach ($gatewayOptions as $gwRow): ?>
          <?php $gw = (string)($gwRow['gateway_name'] ?? ''); ?>
          <option value="<?= e($gw) ?>" <?= $filterGateway === $gw ? 'selected' : '' ?>><?= e($gw) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:140px;">
      <label class="form-label" style="font-size:0.72rem;margin-bottom:0.3rem;">From</label>
      <input type="date" name="date_from" class="form-control" style="height:2.25rem;" value="<?= e($dateFrom) ?>">
    </div>
    <div style="min-width:140px;">
      <label class="form-label" style="font-size:0.72rem;margin-bottom:0.3rem;">To</label>
      <input type="date" name="date_to" class="form-control" style="height:2.25rem;" value="<?= e($dateTo) ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="transactions.php" class="btn btn-secondary btn-sm">Clear</a>
  </form>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>TXN ID</th>
          <th>Reference</th>
          <th>Booking</th>
          <th>Customer</th>
          <th>Amount</th>
          <th>Gateway</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($txns)): ?>
          <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--clr-text-muted);">No transactions found.</td></tr>
        <?php else: ?>
          <?php foreach ($txns as $t): ?>
          <tr>
            <td class="font-mono text-xs"><?= e($t['id']) ?></td>
            <td class="text-xs"><?= e($t['transaction_reference']) ?></td>
            <td class="font-mono text-xs"><?= e($t['booking_code'] ?? $t['booking_id'] ?? '') ?></td>
            <td class="text-xs">
              <div><?= e($t['customer_name'] ?? 'N/A') ?></div>
              <div class="text-muted" style="font-size:0.72rem;"><?= e($t['customer_email'] ?? '') ?></div>
            </td>
            <td style="font-weight:700;color:var(--clr-accent);"><?= formatMWK((float)$t['amount']) ?></td>
            <td class="text-xs"><?= e($t['gateway_name'] ?? 'N/A') ?></td>
            <td><span class="badge <?= txStatusBadge($t['status']) ?>"><?= e($t['status']) ?></span></td>
            <td class="text-xs text-muted"><?= e(substr((string)$t['created_at'], 0, 16)) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination" style="margin-top: 1.5rem;">
    <?php if ($page > 1): ?>
      <a href="<?= e(paginationQs($filterQs, $page - 1)) ?>" class="page-btn">&larr; Prev</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="<?= e(paginationQs($filterQs, $i)) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="<?= e(paginationQs($filterQs, $page + 1)) ?>" class="page-btn">Next &rarr;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <p class="text-xs text-muted" style="text-align:center;margin-top:1rem;">Showing <?= count($txns) ?> of <?= number_format($totalCount) ?> transactions</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(() => {
  const el = document.getElementById('txnDailyChart');
  if (!el || typeof Chart === 'undefined') {
    return;
  }

  const labels = <?= json_encode($txnDailyLabels) ?>;
  const revenue = <?= json_encode($txnDailyRevenue) ?>;
  const counts = <?= json_encode($txnDailyCounts) ?>;

  new Chart(el, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Daily Revenue',
          data: revenue,
          backgroundColor: 'rgba(6, 182, 212, 0.75)',
          borderRadius: 8,
        },
        {
          label: 'Transaction Count',
          data: counts,
          type: 'line',
          borderColor: 'rgba(124, 58, 237, 0.95)',
          backgroundColor: 'rgba(124, 58, 237, 0.15)',
          tension: 0.35,
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { labels: { color: '#94a3b8' } } },
      scales: {
        x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,.06)' } },
        y: { beginAtZero: true, ticks: { color: '#94a3b8', callback: value => 'MK ' + Number(value).toLocaleString() }, grid: { color: 'rgba(255,255,255,.06)' } },
        y1: {
          position: 'right',
          beginAtZero: true,
          grid: { drawOnChartArea: false },
          ticks: { color: '#94a3b8' },
        },
      },
    },
  });
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
