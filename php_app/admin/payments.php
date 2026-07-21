<?php
/**
 * Uthenga - Admin Payments & Financial Ledger
 */
$pageTitle = 'Payments & Financials';
$activeNav = 'admin-transactions';

require_once __DIR__ . '/includes/admin_header.php';

// Handle updating commission rate setting
$message = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_commission']) && validateCsrf()) {
    $rates = [
        'commission_rate_event' => (float)($_POST['commission_rate_event'] ?? 10),
        'commission_rate_accommodation' => (float)($_POST['commission_rate_accommodation'] ?? 12),
        'commission_rate_tour' => (float)($_POST['commission_rate_tour'] ?? 15),
        'commission_rate_transport' => (float)($_POST['commission_rate_transport'] ?? 8),
        'service_fee_event' => (float)($_POST['service_fee_event'] ?? 0),
        'service_fee_accommodation' => (float)($_POST['service_fee_accommodation'] ?? 0),
        'service_fee_tour' => (float)($_POST['service_fee_tour'] ?? 0),
        'service_fee_transport' => (float)($_POST['service_fee_transport'] ?? 0),
    ];

    foreach ($rates as $key => $value) {
        if ($value < 0 || $value > 100) {
            $err = 'Commission rates must be between 0% and 100%, and service fees must be non-negative.';
            break;
        }
    }

    if ($err === '') {
        foreach ($rates as $key => $value) {
            setSetting($key, $value, $_SESSION['user_id'] ?? null);
        }
        logAction('Updated Commission Rates', 'Admin updated marketplace commission and service-fee settings.');
        $message = 'Commission and service-fee settings updated successfully.';
    }
}

// Load current commission rate from settings
$commissionRateEvent = (float)getSetting('commission_rate_event', getSetting('commission_rate', 10));
$commissionRateAccommodation = (float)getSetting('commission_rate_accommodation', 12);
$commissionRateTour = (float)getSetting('commission_rate_tour', 15);
$commissionRateTransport = (float)getSetting('commission_rate_transport', 8);
$serviceFeeEvent = (float)getSetting('service_fee_event', 0);
$serviceFeeAccommodation = (float)getSetting('service_fee_accommodation', 0);
$serviceFeeTour = (float)getSetting('service_fee_tour', 0);
$serviceFeeTransport = (float)getSetting('service_fee_transport', 0);

// â”€â”€â”€ Filters â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$filterStatus  = strtolower($_GET['status']  ?? 'all');
$filterGateway = $_GET['gateway'] ?? 'all';
$search        = trim($_GET['q']  ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 15;

$gatewayExpr = uthenga_column_exists('transactions', 'gateway')
    ? 'COALESCE(gateway_name, gateway)'
    : 'gateway_name';
$referenceExpr = 'COALESCE(transaction_reference, id)';
$statusExpr = 'LOWER(COALESCE(status, ""))';

$where  = ['1=1'];
$params = [];
if ($filterStatus !== 'all')  { $where[] = "$statusExpr = ?";  $params[] = strtolower($filterStatus); }
if ($filterGateway !== 'all') { $where[] = "$gatewayExpr = ?"; $params[] = $filterGateway; }
if ($search) {
    $where[] = "($referenceExpr LIKE ? OR CAST(booking_id AS CHAR) LIKE ? OR $gatewayExpr LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$whereStr   = implode(' AND ', $where);
$totalCount = dbCount("SELECT COUNT(*) FROM transactions WHERE $whereStr", $params);
$totalPages = max(1, ceil($totalCount / $perPage));
$offset     = ($page - 1) * $perPage;

$txns = dbQuery("
    SELECT *, $gatewayExpr AS gateway_label, $referenceExpr AS receipt_ref
    FROM transactions
    WHERE $whereStr
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);

$totalCleared = dbQueryOne("SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE $statusExpr IN ('success','paid')");
$totalCommission = uthenga_table_exists('commissions') ? dbQueryOne("SELECT COALESCE(SUM(commission_amount),0) AS total FROM commissions") : ['total' => 0];
$totalVendorEarnings = uthenga_table_exists('commissions') ? dbQueryOne("SELECT COALESCE(SUM(net_vendor_amount),0) AS total FROM commissions") : ['total' => 0];
$pendingSettlements = uthenga_table_exists('vendor_wallets') ? dbQueryOne("SELECT COALESCE(SUM(pending_balance),0) AS total FROM vendor_wallets") : ['total' => 0];
$processedPayouts = uthenga_table_exists('vendor_payouts') ? dbQueryOne("SELECT COALESCE(SUM(amount),0) AS total FROM vendor_payouts WHERE status = 'processed'") : ['total' => 0];
$refundTotals = uthenga_table_exists('refunds') ? dbQueryOne("SELECT COALESCE(SUM(amount),0) AS total FROM refunds WHERE LOWER(status) IN ('processed','approved')") : ['total' => 0];

function txStatusBadge(string $s): string {
    return match(strtolower($s)) {
        'success'  => 'badge-approved',
        'refunded' => 'badge-refunded',
        'failed'   => 'badge-cancelled',
        default    => 'badge-pending'
    };
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('credit-card') ?><span>Payments & Financials</span></h1>
    <p class="text-muted">Review transactions, check billing gateways, and configure marketplace commissions, service fees, and payouts.</p>
  </div>
  <div class="glass-panel" style="padding:0.75rem 1.25rem;text-align:right;">
    <div class="text-xs text-muted">Total Cleared Volume</div>
    <div style="font-size:1.25rem;font-weight:800;color:var(--clr-accent);"><?= formatMWK((float)$totalCleared['total']) ?></div>
  </div>
</div>

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card"><div class="stat-icon stat-icon-green"><?= admin_icon_svg('wallet') ?></div><div><div class="stat-value"><?= formatMWK((float)($totalCommission['total'] ?? 0)) ?></div><div class="stat-label">Platform Commission</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-blue"><?= admin_icon_svg('store') ?></div><div><div class="stat-value"><?= formatMWK((float)($totalVendorEarnings['total'] ?? 0)) ?></div><div class="stat-label">Vendor Earnings</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('clock') ?></div><div><div class="stat-value"><?= formatMWK((float)($pendingSettlements['total'] ?? 0)) ?></div><div class="stat-label">Pending Settlements</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-purple"><?= admin_icon_svg('report') ?></div><div><div class="stat-value"><?= formatMWK((float)($processedPayouts['total'] ?? 0)) ?></div><div class="stat-label">Processed Payouts</div></div></div>
</div>

<?php if ($message): ?><div class="alert alert-success">Success: <?= e($message) ?></div><?php endif; ?>
<?php if ($err):     ?><div class="alert alert-error">Error: <?= e($err) ?></div><?php endif; ?>

<!-- Settings + Configuration -->
<div class="glass-panel animate-in" style="padding: 1.5rem; margin-bottom: 2rem;">
  <h3 style="font-size: 1.1rem; margin-bottom: 1rem; display:flex; align-items:center; gap:0.45rem;"><?= admin_icon_svg('settings') ?><span>Commission Configuration</span></h3>
  <form method="POST" class="flex items-center gap-3 wrap">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="update_commission" value="1">
    <div class="grid grid-cols-4 gap-2" style="width:100%;margin-bottom:1rem;">
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Events %</label><input type="number" name="commission_rate_event" class="form-control" min="0" max="100" step="0.1" value="<?= e($commissionRateEvent) ?>"></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Hotels %</label><input type="number" name="commission_rate_accommodation" class="form-control" min="0" max="100" step="0.1" value="<?= e($commissionRateAccommodation) ?>"></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Tours %</label><input type="number" name="commission_rate_tour" class="form-control" min="0" max="100" step="0.1" value="<?= e($commissionRateTour) ?>"></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Transport %</label><input type="number" name="commission_rate_transport" class="form-control" min="0" max="100" step="0.1" value="<?= e($commissionRateTransport) ?>"></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Events Fee</label><input type="number" name="service_fee_event" class="form-control" min="0" max="100000" step="0.1" value="<?= e($serviceFeeEvent) ?>"></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Hotels Fee</label><input type="number" name="service_fee_accommodation" class="form-control" min="0" max="100000" step="0.1" value="<?= e($serviceFeeAccommodation) ?>"></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Tours Fee</label><input type="number" name="service_fee_tour" class="form-control" min="0" max="100000" step="0.1" value="<?= e($serviceFeeTour) ?>"></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Transport Fee</label><input type="number" name="service_fee_transport" class="form-control" min="0" max="100000" step="0.1" value="<?= e($serviceFeeTransport) ?>"></div>
    </div>
    <div style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
      <button type="submit" class="btn btn-primary">Update Fee Settings</button>
      <div class="text-xs text-muted">Commission is applied to the net booking amount after discounts. Service fees are charged to the customer and kept by Uthenga.</div>
    </div>
  </form>
</div>

<!-- Ledger Grid -->
<div class="glass-panel animate-in" style="padding: 1.5rem;">
  <h3 style="font-size: 1.1rem; margin-bottom: 1rem; display:flex; align-items:center; gap:0.45rem;"><?= admin_icon_svg('report') ?><span>Transaction Ledger</span></h3>
  
  <form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.5rem;" id="txn-filter-form">
    <input type="text" name="q" placeholder="Search transactions..." class="form-control" style="max-width:260px;" value="<?= e($search) ?>">
    <select name="status" class="form-control" style="max-width:160px;" onchange="this.form.submit()">
      <option value="all"     <?= $filterStatus === 'all'     ? 'selected' : '' ?>>All Statuses</option>
      <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>Success</option>
      <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="failed"  <?= $filterStatus === 'failed'  ? 'selected' : '' ?>>Failed</option>
      <option value="refunded"<?= $filterStatus === 'refunded'? 'selected' : '' ?>>Refunded</option>
    </select>
    <select name="gateway" class="form-control" style="max-width:200px;" onchange="this.form.submit()">
      <option value="all" <?= $filterGateway === 'all' ? 'selected' : '' ?>>All Gateways</option>
      <?php foreach (['Airtel Money','TNM Mpamba','Bank Card','Direct NBS Transfer','Uthenga Pay'] as $gw): ?>
        <option value="<?= e($gw) ?>" <?= $filterGateway === $gw ? 'selected' : '' ?>><?= e($gw) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm" id="txn-filter-btn">Filter</button>
    <a href="payments.php" class="btn btn-secondary btn-sm" id="txn-clear-btn">Clear</a>
  </form>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>TXN ID</th>
          <th>Reference</th>
          <th>Booking ID</th>
          <th>Amount</th>
          <th>Gateway</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($txns)): ?>
          <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--clr-text-muted);">No transactions found.</td></tr>
        <?php else: ?>
          <?php foreach ($txns as $t): ?>
          <tr>
            <td class="font-mono text-xs"><?= e($t['id']) ?></td>
            <td class="text-xs"><?= e($t['receipt_ref'] ?? $t['transaction_reference'] ?? $t['id']) ?></td>
            <td class="font-mono text-xs"><?= e($t['booking_id']) ?></td>
            <td style="font-weight:700;color:var(--clr-accent);"><?= formatMWK((float)$t['amount']) ?></td>
            <td class="text-xs"><?= e($t['gateway_label'] ?? $t['gateway_name'] ?? $t['gateway'] ?? 'N/A') ?></td>
            <td>
              <span class="badge <?= txStatusBadge($t['status']) ?>">
                <?= e($t['status']) ?>
              </span>
            </td>
            <td class="text-xs text-muted"><?= e(substr($t['created_at'],0,16)) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination" style="margin-top: 1.5rem;">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page-1 ?>&status=<?= urlencode($filterStatus) ?>&gateway=<?= urlencode($filterGateway) ?>&q=<?= urlencode($search) ?>" class="page-btn">Prev</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&gateway=<?= urlencode($filterGateway) ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page+1 ?>&status=<?= urlencode($filterStatus) ?>&gateway=<?= urlencode($filterGateway) ?>&q=<?= urlencode($search) ?>" class="page-btn">Next</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  
  <p class="text-xs text-muted" style="text-align:center;margin-top:1rem;">Showing <?= count($txns) ?> of <?= number_format($totalCount) ?> transactions</p>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
