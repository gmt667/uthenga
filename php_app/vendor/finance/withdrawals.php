<?php
/**
 * Uthenga - Vendor Withdrawals & Settlement Requests
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireApprovedVendor();

$pageTitle = 'Withdrawals & Settlements';
$vendorUserId = (int) ($_SESSION['user_id'] ?? 0);
$vendorRecord = uthenga_vendor_record_for_user($vendorUserId) ?: [];

if (empty($vendorRecord['id'])) {
    redirect(BASE_URL . 'vendor/pending.php');
}

$vendorId = (int) $vendorRecord['id'];
$vendor = dbQueryOne('SELECT * FROM users WHERE id = ?', [$vendorUserId]) ?: [];
$wallet = uthenga_finance_ensure_vendor_wallet($vendorId) ?: ['id' => null, 'balance' => 0, 'pending_balance' => 0, 'currency' => APP_CURRENCY];
$wallet = dbQueryOne('SELECT * FROM vendor_wallets WHERE vendor_id = ? LIMIT 1', [$vendorId]) ?: $wallet;

$flashSuccess = $_SESSION['vendor_withdrawal_success'] ?? '';
$flashError = $_SESSION['vendor_withdrawal_error'] ?? '';
unset($_SESSION['vendor_withdrawal_success'], $_SESSION['vendor_withdrawal_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    if (!validateCsrf()) {
        $flashError = 'Security check failed. Please refresh and try again.';
    } else {
        $result = uthenga_finance_request_withdrawal([
            'vendor_id' => $vendorId,
            'amount' => (float) ($_POST['amount'] ?? 0),
            'currency' => $wallet['currency'] ?? APP_CURRENCY,
            'request_method' => trim((string) ($_POST['request_method'] ?? '')),
            'destination' => trim((string) ($_POST['destination'] ?? '')),
        ]);

        if (!empty($result['success'])) {
            $amount = formatMWK((float) ($_POST['amount'] ?? 0));
            $method = trim((string) ($_POST['request_method'] ?? 'withdrawal'));
            logAction('Withdrawal Requested', "Vendor withdrawal request submitted for {$amount} via {$method}.");
            $_SESSION['vendor_withdrawal_success'] = (string) ($result['message'] ?? 'Withdrawal request submitted.');
            redirect(BASE_URL . 'vendor/finance/withdrawals.php?submitted=1');
        }

        $flashError = (string) ($result['message'] ?? 'Could not submit withdrawal request.');
    }
}

$requestReferenceExpr = uthenga_column_exists('withdrawal_requests', 'request_reference')
    ? 'COALESCE(wr.request_reference, CONCAT("WDR-", wr.id))'
    : 'CONCAT("WDR-", wr.id)';
$requestChargesExpr = uthenga_column_exists('withdrawal_requests', 'charges')
    ? 'COALESCE(wr.charges, 0)'
    : '0';
$payoutChargesExpr = uthenga_column_exists('vendor_payouts', 'charges')
    ? 'COALESCE(vp.charges, 0)'
    : '0';

$summary = dbQueryOne(
    "SELECT
        COUNT(*) AS request_count,
        COALESCE(SUM(amount), 0) AS request_total,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_total,
        COALESCE(SUM(CASE WHEN status = 'processed' THEN amount ELSE 0 END), 0) AS processed_total,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END), 0) AS rejected_total
     FROM withdrawal_requests
     WHERE vendor_id = ?",
    [$vendorId]
) ?: ['request_count' => 0, 'request_total' => 0, 'pending_total' => 0, 'processed_total' => 0, 'rejected_total' => 0];

$withdrawals = dbQuery(
    "SELECT
        wr.*,
        {$requestReferenceExpr} AS request_ref_label,
        {$requestChargesExpr} AS charges_amount,
        vw.balance AS wallet_balance,
        vw.pending_balance AS wallet_pending_balance
     FROM withdrawal_requests wr
     LEFT JOIN vendor_wallets vw ON vw.id = wr.wallet_id
     WHERE wr.vendor_id = ?
     ORDER BY wr.created_at DESC
     LIMIT 20",
    [$vendorId]
);

$payouts = dbQuery(
    "SELECT
        vp.*,
        {$payoutChargesExpr} AS charges_amount
     FROM vendor_payouts vp
     WHERE vp.vendor_id = ?
     ORDER BY vp.created_at DESC
     LIMIT 10",
    [$vendorId]
);

function uthenga_vendor_settlement_badge(string $status): string {
    switch (strtolower($status)) {
        case 'processed':
            return 'badge-approved';
        case 'approved':
            return 'badge-confirmed';
        case 'rejected':
            return 'badge-rejected';
        default:
            return 'badge-pending';
    }
}

require_once __DIR__ . '/../../includes/dashboard_shell.php';
renderDashboardChromeStart([
    'role' => currentRole(),
    'title' => $pageTitle,
    'active' => 'vendor/finance/withdrawals.php',
    'search' => false,
    'status' => 'Wallet & settlements',
]);
?>

<div class="container dashboard-content-frame" style="padding-top:2.25rem;padding-bottom:3rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <?= dashboard_icon_svg('wallet') ?>
        <span>Withdrawals & Settlements</span>
      </h1>
      <p class="text-muted">Request a payout from your available wallet balance and follow every settlement status in one place.</p>
    </div>
    <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>vendor/dashboard.php">
      <?= dashboard_icon_svg('home') ?>
      <span>Back to Dashboard</span>
    </a>
  </div>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;"><?= e($flashSuccess) ?></div>
  <?php endif; ?>
  <?php if ($flashError): ?>
    <div class="alert alert-error" style="margin-bottom:1rem;"><?= e($flashError) ?></div>
  <?php endif; ?>

  <div class="grid grid-cols-4 gap-2" style="margin-bottom:1.25rem;">
    <div class="presentation-stat"><span>Available Balance</span><strong><?= formatMWK((float) ($wallet['balance'] ?? 0)) ?></strong></div>
    <div class="presentation-stat"><span>Pending Balance</span><strong><?= formatMWK((float) ($wallet['pending_balance'] ?? 0)) ?></strong></div>
    <div class="presentation-stat"><span>Withdrawals</span><strong><?= number_format((int) ($summary['request_count'] ?? 0)) ?></strong></div>
    <div class="presentation-stat"><span>Processed</span><strong><?= formatMWK((float) ($summary['processed_total'] ?? 0)) ?></strong></div>
  </div>

  <div class="grid grid-cols-2 gap-3" style="margin-bottom:1rem;">
    <div class="glass-panel" style="padding:1.5rem;">
      <div class="section-head" style="margin-bottom:1rem;">
        <div>
          <h2 class="page-title" style="font-size:1.25rem;">Request a Withdrawal</h2>
          <p class="text-muted">Funds move from available balance to pending settlement before the admin processes payout.</p>
        </div>
      </div>

      <form method="POST" action="" style="display:grid;gap:1rem;">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="request_withdrawal" value="1">

        <div class="form-group">
          <label class="form-label" for="withdrawal-amount">Amount (MWK)</label>
          <input type="number" id="withdrawal-amount" name="amount" class="form-control" min="1" step="0.01" placeholder="Enter withdrawal amount" required>
          <small class="text-muted">Available: <?= formatMWK((float) ($wallet['balance'] ?? 0)) ?></small>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="form-group">
            <label class="form-label" for="withdrawal-method">Withdrawal Method</label>
            <select id="withdrawal-method" name="request_method" class="form-control" required>
              <option value="">Select method</option>
              <option value="Airtel Money">Airtel Money</option>
              <option value="TNM Mpamba">TNM Mpamba</option>
              <option value="Bank Transfer">Bank Transfer</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="withdrawal-destination">Destination</label>
            <input type="text" id="withdrawal-destination" name="destination" class="form-control" placeholder="Phone number or bank account" required>
          </div>
        </div>

        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:center;">
          <div class="text-xs text-muted">Super Admin reviews every payout request before it is marked processed.</div>
          <button type="submit" class="btn btn-primary">Submit Request</button>
        </div>
      </form>
    </div>

    <div class="glass-panel" style="padding:1.5rem;">
      <div class="section-head" style="margin-bottom:1rem;">
        <div>
          <h2 class="page-title" style="font-size:1.25rem;">Settlement Snapshot</h2>
          <p class="text-muted">A quick summary of your request lifecycle.</p>
        </div>
      </div>

      <div class="presentation-grid">
        <div class="presentation-stat"><span>Total Requested</span><strong><?= formatMWK((float) ($summary['request_total'] ?? 0)) ?></strong></div>
        <div class="presentation-stat"><span>Pending Requests</span><strong><?= formatMWK((float) ($summary['pending_total'] ?? 0)) ?></strong></div>
        <div class="presentation-stat"><span>Rejected</span><strong><?= formatMWK((float) ($summary['rejected_total'] ?? 0)) ?></strong></div>
        <div class="presentation-stat"><span>Vendor</span><strong><?= e($vendor['name'] ?? $vendor['full_name'] ?? 'Vendor') ?></strong></div>
      </div>

      <div class="alert alert-info" style="margin-top:1rem;">
        <strong>Tip:</strong> Keep your payout destination updated and make sure the account holder name matches your vendor records.
      </div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.5rem;margin-bottom:1rem;">
    <div class="section-head" style="margin-bottom:1rem;">
      <div>
        <h2 class="page-title" style="font-size:1.25rem;">Recent Withdrawal Requests</h2>
        <p class="text-muted">Track every payout request from submission to processing.</p>
      </div>
    </div>

    <?php if (empty($withdrawals)): ?>
      <div class="text-muted">No withdrawal requests yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Method</th>
              <th>Destination</th>
              <th>Amount</th>
              <th>Charges</th>
              <th>Status</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($withdrawals as $row): ?>
              <tr>
                <td class="font-mono text-xs"><?= e($row['request_ref_label'] ?? ('WDR-' . $row['id'])) ?></td>
                <td><?= e($row['request_method'] ?? '') ?></td>
                <td><?= e($row['destination'] ?? 'N/A') ?></td>
                <td style="font-weight:700;color:var(--clr-accent);"><?= formatMWK((float) ($row['amount'] ?? 0)) ?></td>
                <td><?= formatMWK((float) ($row['charges_amount'] ?? 0)) ?></td>
                <td><span class="badge <?= uthenga_vendor_settlement_badge((string) ($row['status'] ?? 'pending')) ?>"><?= e(ucfirst((string) ($row['status'] ?? 'pending'))) ?></span></td>
                <td class="text-xs text-muted"><?= e(substr((string) ($row['created_at'] ?? ''), 0, 16)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="glass-panel" style="padding:1.5rem;">
    <div class="section-head" style="margin-bottom:1rem;">
      <div>
        <h2 class="page-title" style="font-size:1.25rem;">Payout History</h2>
        <p class="text-muted">Completed payouts recorded by the finance team.</p>
      </div>
    </div>

    <?php if (empty($payouts)): ?>
      <div class="text-muted">No payouts have been processed yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Reference</th>
              <th>Method</th>
              <th>Amount</th>
              <th>Charges</th>
              <th>Status</th>
              <th>Processed</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payouts as $row): ?>
              <tr>
                <td class="font-mono text-xs"><?= e($row['transaction_reference'] ?? ('PAYOUT-' . $row['id'])) ?></td>
                <td><?= e($row['payout_method'] ?? 'N/A') ?></td>
                <td style="font-weight:700;color:var(--clr-accent);"><?= formatMWK((float) ($row['amount'] ?? 0)) ?></td>
                <td><?= formatMWK((float) ($row['charges_amount'] ?? 0)) ?></td>
                <td><span class="badge <?= uthenga_vendor_settlement_badge((string) ($row['status'] ?? 'pending')) ?>"><?= e(ucfirst((string) ($row['status'] ?? 'pending'))) ?></span></td>
                <td class="text-xs text-muted"><?= e(substr((string) ($row['processed_at'] ?? $row['created_at'] ?? ''), 0, 16)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php renderDashboardChromeEnd(); ?>
