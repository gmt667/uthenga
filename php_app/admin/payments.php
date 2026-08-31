<?php
/**
 * Uthenga - Admin Payments & Financial Ledger
 */
$pageTitle = 'Payments & Financials';
$activeNav = 'admin-transactions';

require_once __DIR__ . '/includes/admin_header.php';

// Handle saving a new, effective-dated fee rule — never overwrites a rate in
// place; uthenga_finance_save_fee_rule() closes whatever was active and
// inserts a fresh row, so transactions already created keep the rate that
// was active when they were made.
$message = '';
$err = '';
$feeRuleCategories = ['accommodation' => 'Accommodation', 'event' => 'Events', 'tour' => 'Tours', 'transport' => 'Transport', 'shop' => 'Shop'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fee_rule']) && validateCsrf()) {
    requireAdminPermission('finance.manage');
    requireRecentAdminReauthentication('finance');
    $category = trim((string) ($_POST['service_category'] ?? ''));
    $rate = (float) ($_POST['commission_rate'] ?? -1);
    $fee = (float) ($_POST['service_fee'] ?? -1);
    $effectiveFromInput = trim((string) ($_POST['effective_from'] ?? ''));

    if (!isset($feeRuleCategories[$category])) {
        $err = 'Unknown service category.';
    } elseif ($rate < 0 || $rate > 100) {
        $err = 'Commission rate must be between 0% and 100%.';
    } elseif ($fee < 0) {
        $err = 'Service fee must be zero or greater.';
    } else {
        $effectiveFrom = null;
        if ($effectiveFromInput !== '') {
            $parsed = strtotime($effectiveFromInput);
            if ($parsed === false) {
                $err = 'Invalid effective-from date.';
            } else {
                $effectiveFrom = date('Y-m-d H:i:s', $parsed);
            }
        }
        if ($err === '') {
            uthenga_finance_save_fee_rule($category, $rate, $fee, $_SESSION['user_id'] ?? null, $effectiveFrom);
            logAction('Updated Commission Rate', 'Admin set a new ' . $feeRuleCategories[$category] . " fee rule: {$rate}% + MK{$fee}, effective " . ($effectiveFrom ?? 'immediately') . '.');
            $message = $feeRuleCategories[$category] . ' fee rule saved.';
        }
    }
}

// Current active rule + recent history per category, straight from the
// versioned table — no more flat, historyless settings keys.
$activeFeeRules = [];
$feeRuleHistory = [];
foreach ($feeRuleCategories as $key => $label) {
    $activeFeeRules[$key] = uthenga_finance_active_fee_rule($key);
    $feeRuleHistory[$key] = dbQuery('SELECT * FROM uthenga_fee_rules WHERE service_category = ? ORDER BY effective_from DESC LIMIT 10', [$key]);
}

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
$totalCommission = uthenga_table_exists('commissions') ? dbQueryOne("SELECT COALESCE(SUM(commission_amount),0) AS total FROM commissions") : ['total' => 0];
$totalVendorEarnings = uthenga_table_exists('commissions') ? dbQueryOne("SELECT COALESCE(SUM(net_vendor_amount),0) AS total FROM commissions") : ['total' => 0];
$pendingSettlements = uthenga_table_exists('vendor_wallets') ? dbQueryOne("SELECT COALESCE(SUM(pending_balance),0) AS total FROM vendor_wallets") : ['total' => 0];
$processedPayouts = uthenga_table_exists('vendor_payouts') ? dbQueryOne("SELECT COALESCE(SUM(amount),0) AS total FROM vendor_payouts WHERE status = 'processed'") : ['total' => 0];
$refundTotals = uthenga_table_exists('refunds') ? dbQueryOne("SELECT COALESCE(SUM(amount),0) AS total FROM refunds WHERE LOWER(status) IN ('processed','approved')") : ['total' => 0];
$shopPaymentsTotal = uthenga_table_exists('shop_payments') ? dbQueryOne("SELECT COALESCE(SUM(amount),0) AS total FROM shop_payments WHERE LOWER(payment_status) IN ('paid','processing','authorized','pending')") : ['total' => 0];
$shopPaymentsCount = uthenga_table_exists('shop_payments') ? dbCount("SELECT COUNT(*) FROM shop_payments") : 0;
$shopPaidCount = uthenga_table_exists('shop_payments') ? dbCount("SELECT COUNT(*) FROM shop_payments WHERE LOWER(payment_status) = 'paid'") : 0;
$shopProcessingCount = uthenga_table_exists('shop_payments') ? dbCount("SELECT COUNT(*) FROM shop_payments WHERE LOWER(payment_status) = 'processing'") : 0;

$shopPayments = [];
if (uthenga_table_exists('shop_payments')) {
    $shopPayments = dbQuery("
        SELECT
            p.*,
            o.order_number,
            o.customer_name,
            o.customer_email,
            o.customer_phone,
            o.order_status,
            o.fulfillment_status
        FROM shop_payments p
        INNER JOIN shop_orders o ON o.id = p.order_id
        ORDER BY p.created_at DESC
        LIMIT 25
    ");
}

function txStatusBadge(string $s): string {
    $val = strtolower($s);
    if ($val === 'success') {
        return 'badge-approved';
    }
    if ($val === 'refunded') {
        return 'badge-refunded';
    }
    if ($val === 'failed') {
        return 'badge-cancelled';
    }
    return 'badge-pending';
}

function txStatusLabel(string $status): string {
    return match (strtolower(trim($status))) {
        'success' => 'Successful',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'processing' => 'Processing',
        'authorized' => 'Authorized',
        default => ucwords(str_replace(['_', '-'], ' ', strtolower(trim($status)))),
    };
}

function txStatusHint(string $status): string {
    return match (strtolower(trim($status))) {
        'success', 'paid' => 'The transaction has cleared successfully.',
        'pending' => 'The payment is waiting for confirmation.',
        'processing' => 'The payment is being processed.',
        'authorized' => 'The payment has been authorized and is awaiting capture or settlement.',
        'failed' => 'The payment did not complete.',
        'refunded' => 'The payment was returned to the customer.',
        default => 'Current ledger status.',
    };
}

function shopPaymentStatusBadge(string $status): string {
    $value = strtolower(trim($status));
    return match ($value) {
        'paid' => 'badge-approved',
        'processing', 'pending', 'authorized' => 'badge-pending',
        'failed' => 'badge-cancelled',
        'refunded' => 'badge-refunded',
        default => 'badge-pending',
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

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card"><div class="stat-icon stat-icon-green"><?= admin_icon_svg('credit-card') ?></div><div><div class="stat-value"><?= formatMWK((float)($shopPaymentsTotal['total'] ?? 0)) ?></div><div class="stat-label">Shop Payments</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-blue"><?= admin_icon_svg('cart') ?></div><div><div class="stat-value"><?= number_format((int) $shopPaymentsCount) ?></div><div class="stat-label">Shop Transactions</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('wallet') ?></div><div><div class="stat-value"><?= number_format((int) $shopPaidCount) ?></div><div class="stat-label">Paid Orders</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-purple"><?= admin_icon_svg('clock') ?></div><div><div class="stat-value"><?= number_format((int) $shopProcessingCount) ?></div><div class="stat-label">Processing</div></div></div>
</div>

<?php if ($message): ?><div class="alert alert-success">Success: <?= e($message) ?></div><?php endif; ?>
<?php if ($err):     ?><div class="alert alert-error">Error: <?= e($err) ?></div><?php endif; ?>

<!-- Settings + Configuration -->
<div class="glass-panel animate-in" style="padding: 1.5rem; margin-bottom: 2rem;">
  <h3 style="font-size: 1.1rem; margin-bottom: 1rem; display:flex; align-items:center; gap:0.45rem;"><?= admin_icon_svg('settings') ?><span>Payment Revenue Rules</span></h3>
  <div class="text-xs text-muted" style="margin-bottom:1rem;">Saving a rule never overwrites the previous one — it closes it and starts a new one, so a transaction always keeps the rate that was active when it was created.</div>

  <?php foreach ($feeRuleCategories as $catKey => $catLabel): $active = $activeFeeRules[$catKey]; ?>
  <div class="glass-panel" style="padding:1rem;margin-bottom:1rem;background:rgba(255,255,255,0.02);">
    <form method="POST" class="flex items-center gap-3 wrap" style="align-items:flex-end;">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="save_fee_rule" value="1">
      <input type="hidden" name="service_category" value="<?= e($catKey) ?>">
      <div class="form-group" style="margin-bottom:0;min-width:140px;"><label class="form-label">Service</label><div style="font-weight:700;padding:0.55rem 0;"><?= e($catLabel) ?></div></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Rate %</label><input type="number" name="commission_rate" class="form-control" min="0" max="100" step="0.1" value="<?= e($active['commission_rate'] ?? 0) ?>" required></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Service Fee</label><input type="number" name="service_fee" class="form-control" min="0" step="0.1" value="<?= e($active['service_fee'] ?? 0) ?>" required></div>
      <div class="form-group" style="margin-bottom:0;"><label class="form-label">Effective From</label><input type="datetime-local" name="effective_from" class="form-control"></div>
      <button type="submit" class="btn btn-primary btn-sm">Save Rule</button>
      <?php if ($active): ?>
        <div class="text-xs text-muted">Active since <?= e(date('d M Y', strtotime($active['effective_from']))) ?></div>
      <?php endif; ?>
    </form>
    <?php if (count($feeRuleHistory[$catKey]) > 1): ?>
    <details style="margin-top:0.75rem;">
      <summary class="text-xs text-muted" style="cursor:pointer;">Rate history (<?= count($feeRuleHistory[$catKey]) ?>)</summary>
      <table class="table" style="margin-top:0.5rem;font-size:0.8rem;">
        <thead><tr><th>Rate</th><th>Service Fee</th><th>Effective From</th><th>Effective To</th></tr></thead>
        <tbody>
          <?php foreach ($feeRuleHistory[$catKey] as $rule): ?>
          <tr>
            <td><?= e($rule['commission_rate']) ?>%</td>
            <td>MK<?= e(number_format((float) $rule['service_fee'], 2)) ?></td>
            <td><?= e(date('d M Y H:i', strtotime($rule['effective_from']))) ?></td>
            <td><?= $rule['effective_to'] ? e(date('d M Y H:i', strtotime($rule['effective_to']))) : '<strong>current</strong>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Ledger Grid -->
<div class="glass-panel animate-in" style="padding: 1.5rem;">
  <h3 style="font-size: 1.1rem; margin-bottom: 1rem; display:flex; align-items:center; gap:0.45rem;"><?= admin_icon_svg('report') ?><span>Transaction Ledger</span></h3>
  
  <form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:1.5rem;" id="txn-filter-form">
    <input type="text" name="q" placeholder="Search transactions..." class="form-control" style="max-width:260px;" value="<?= e($search) ?>">
    <select name="status" class="form-control" style="max-width:160px;" onchange="this.form.submit()">
      <option value="all"     <?= $filterStatus === 'all'     ? 'selected' : '' ?>>All Statuses</option>
      <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>Successful</option>
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
                <?= e(txStatusLabel((string) $t['status'])) ?>
              </span>
              <div class="text-xs text-muted" style="margin-top:.35rem;line-height:1.35;"><?= e(txStatusHint((string) $t['status'])) ?></div>
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

<div class="glass-panel animate-in" style="padding: 1.5rem; margin-top: 1.5rem;">
  <h3 style="font-size: 1.1rem; margin-bottom: 1rem; display:flex; align-items:center; gap:0.45rem;"><?= admin_icon_svg('cart') ?><span>Shop Payments</span></h3>
  <p class="text-muted" style="margin-top:-0.25rem;margin-bottom:1rem;">Live shop payment records from the Uthenga drinks store, including PayChangu, Cash on Delivery, bank transfer, and mobile money statuses.</p>

  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Order</th>
          <th>Customer</th>
          <th>Reference</th>
          <th>Method</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Paid At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($shopPayments)): ?>
          <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--clr-text-muted);">No shop payments found.</td></tr>
        <?php else: ?>
          <?php foreach ($shopPayments as $payment): ?>
            <tr>
              <td>
                <a href="<?= BASE_URL ?>admin/shop-order.php?id=<?= (int) $payment['order_id'] ?>" style="font-weight:700;"><?= e($payment['order_number']) ?></a><br>
                <span class="text-xs text-muted"><?= e((string) ($payment['order_status'] ?? 'pending')) ?> · <?= e((string) ($payment['fulfillment_status'] ?? 'pending')) ?></span>
              </td>
              <td>
                <?= e($payment['customer_name'] ?? 'N/A') ?><br>
                <span class="text-xs text-muted"><?= e($payment['customer_email'] ?? '') ?></span>
              </td>
              <td class="text-xs font-mono"><?= e($payment['payment_reference'] ?? 'N/A') ?></td>
              <td class="text-xs"><?= e(uthenga_shop_payment_method_label((string) ($payment['payment_method'] ?? 'cash_on_delivery'))) ?></td>
              <td style="font-weight:700;color:var(--clr-accent);"><?= formatMWK((float) ($payment['amount'] ?? 0)) ?></td>
              <td>
                <span class="badge <?= shopPaymentStatusBadge((string) ($payment['payment_status'] ?? 'pending')) ?>">
                  <?= e(uthenga_shop_status_label((string) ($payment['payment_status'] ?? 'pending'))) ?>
                </span>
                <div class="text-xs text-muted" style="margin-top:.35rem;line-height:1.35;"><?= e(uthenga_shop_status_hint((string) ($payment['payment_status'] ?? 'pending'))) ?></div>
              </td>
              <td class="text-xs text-muted"><?= e((string) ($payment['paid_at'] ?? $payment['created_at'] ?? '')) ?></td>
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
