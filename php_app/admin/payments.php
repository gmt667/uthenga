<?php
/**
 * Uthenga — Admin Payments & Financial ledger
 */
$pageTitle = 'Payments & Financials';
$activeNav = 'admin-transactions';

require_once __DIR__ . '/includes/admin_header.php';

// Handle updating commission rate setting
$message = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_commission']) && validateCsrf()) {
    $rate = (int)($_POST['commission_rate'] ?? 10);
    if ($rate < 0 || $rate > 100) {
        $err = 'Commission rate must be between 0% and 100%.';
    } else {
        setSetting('commission_rate', $rate, $_SESSION['user_id'] ?? null);
        logAction('Updated Commission Rate', "Admin updated platform commission rate to: $rate%");
        $message = "Platform commission rate updated successfully to $rate%!";
    }
}

// Load current commission rate from settings
$commissionRate = (int)getSetting('commission_rate', 10);

// ─── Filters ─────────────────────────────────────────────────────────────────
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
    <h1 class="page-title">💳 Payments & Financials</h1>
    <p class="text-muted">Review transactions, check billing gateways, and configure platform commissions.</p>
  </div>
  <div class="glass-panel" style="padding:0.75rem 1.25rem;text-align:right;">
    <div class="text-xs text-muted">Total Cleared Volume</div>
    <div style="font-size:1.25rem;font-weight:800;color:var(--clr-accent);"><?= formatMWK((float)$totalCleared['total']) ?></div>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-success">✓ <?= e($message) ?></div><?php endif; ?>
<?php if ($err):     ?><div class="alert alert-error">✕ <?= e($err) ?></div><?php endif; ?>

<!-- Settings + Configuration -->
<div class="glass-panel animate-in" style="padding: 1.5rem; margin-bottom: 2rem;">
  <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">⚙️ Commission Configuration</h3>
  <form method="POST" class="flex items-center gap-3 wrap">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="update_commission" value="1">
    
    <div class="form-group" style="margin-bottom: 0; display: inline-flex; align-items: center; gap: 0.5rem;">
      <label class="form-label" style="margin-bottom: 0;">Commission Rate (%)</label>
      <input type="number" name="commission_rate" class="form-control" style="width: 100px; padding: 0.4rem;" min="0" max="100" value="<?= $commissionRate ?>">
    </div>
    <div>
      <button type="submit" class="btn btn-primary">Update Fee Settings</button>
    </div>
    <div class="text-xs text-muted" style="margin-left: 1rem;">This rate applies to all new vendor bookings and is deducted during payout calculations.</div>
  </form>
</div>

<!-- Ledger Grid -->
<div class="glass-panel animate-in" style="padding: 1.5rem;">
  <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">📰 Transaction Ledger</h3>
  
  <form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.5rem;" id="txn-filter-form">
    <input type="text" name="q" placeholder="Search transactions…" class="form-control" style="max-width:260px;" value="<?= e($search) ?>">
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
      <a href="?page=<?= $page-1 ?>&status=<?= urlencode($filterStatus) ?>&gateway=<?= urlencode($filterGateway) ?>&q=<?= urlencode($search) ?>" class="page-btn">← Prev</a>
    <?php endif; ?>
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>&gateway=<?= urlencode($filterGateway) ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page+1 ?>&status=<?= urlencode($filterStatus) ?>&gateway=<?= urlencode($filterGateway) ?>&q=<?= urlencode($search) ?>" class="page-btn">Next →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  
  <p class="text-xs text-muted" style="text-align:center;margin-top:1rem;">Showing <?= count($txns) ?> of <?= number_format($totalCount) ?> transactions</p>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
