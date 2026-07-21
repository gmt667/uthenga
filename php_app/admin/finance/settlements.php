<?php
/**
 * Uthenga - Vendor Settlements Administration
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requireAdmin();

$pageTitle = 'Vendor Settlements';
$activeNav = 'admin-settlements';

$flashSuccess = $_SESSION['settlement_success'] ?? '';
$flashError = $_SESSION['settlement_error'] ?? '';
unset($_SESSION['settlement_success'], $_SESSION['settlement_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settlement_action'])) {
    if (!validateCsrf()) {
        $flashError = 'Security check failed. Please refresh and try again.';
    } else {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $decision = trim((string) ($_POST['settlement_action'] ?? 'approve'));
        $meta = [
            'charges' => (float) ($_POST['charges'] ?? 0),
        ];

        $result = uthenga_finance_review_withdrawal_request($requestId, (int) ($_SESSION['user_id'] ?? 0), $decision, $meta);

        if (!empty($result['success'])) {
            $summaryAction = $decision === 'reject' ? 'rejected' : 'processed';
            logAction('Vendor Settlement Reviewed', "Withdrawal request #{$requestId} {$summaryAction} by admin.");
            $_SESSION['settlement_success'] = (string) ($result['message'] ?? 'Settlement updated.');
            redirect(BASE_URL . 'admin/finance/settlements.php?updated=1');
        }

        $flashError = (string) ($result['message'] ?? 'Unable to update the settlement request.');
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
        COUNT(*) AS total_requests,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_amount,
        COALESCE(SUM(CASE WHEN status = 'processed' THEN amount ELSE 0 END), 0) AS processed_amount,
        COALESCE(SUM(CASE WHEN status = 'rejected' THEN amount ELSE 0 END), 0) AS rejected_amount
     FROM withdrawal_requests") ?: [
    'total_requests' => 0,
    'pending_amount' => 0,
    'processed_amount' => 0,
    'rejected_amount' => 0,
];

$walletSummary = dbQueryOne(
    "SELECT
        COALESCE(SUM(balance), 0) AS available_total,
        COALESCE(SUM(pending_balance), 0) AS pending_total
     FROM vendor_wallets"
) ?: ['available_total' => 0, 'pending_total' => 0];

$payoutSummary = dbQueryOne(
    "SELECT
        COUNT(*) AS payout_count,
        COALESCE(SUM(amount), 0) AS payout_total
     FROM vendor_payouts
     WHERE status = 'processed'"
) ?: ['payout_count' => 0, 'payout_total' => 0];

$requests = dbQuery(
    "SELECT
        wr.*,
        {$requestReferenceExpr} AS request_ref_label,
        {$requestChargesExpr} AS charges_amount,
        v.business_name,
        v.display_name,
        v.business_phone,
        v.business_email,
        u.name AS owner_name,
        u.email AS owner_email,
        vw.balance AS wallet_balance,
        vw.pending_balance AS wallet_pending_balance
     FROM withdrawal_requests wr
     INNER JOIN vendors v ON v.id = wr.vendor_id
     LEFT JOIN users u ON u.id = v.user_id
     LEFT JOIN vendor_wallets vw ON vw.id = wr.wallet_id
     ORDER BY wr.created_at DESC
     LIMIT 50"
);

$payouts = dbQuery(
    "SELECT
        vp.*,
        {$payoutChargesExpr} AS charges_amount,
        v.business_name,
        v.display_name,
        u.name AS owner_name
     FROM vendor_payouts vp
     INNER JOIN vendors v ON v.id = vp.vendor_id
     LEFT JOIN users u ON u.id = v.user_id
     ORDER BY vp.created_at DESC
     LIMIT 20"
);

function uthenga_settlement_badge(string $status): string {
    switch (strtolower($status)) {
        case 'processed':
        case 'approved':
            return 'badge-approved';
        case 'rejected':
        case 'failed':
            return 'badge-rejected';
        default:
            return 'badge-pending';
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;flex-wrap:wrap;"><?= admin_icon_svg('wallet') ?><span>Vendor Settlements</span></h1>
    <p class="text-muted">Approve or reject vendor withdrawal requests, then review the processed payout history.</p>
  </div>
  <div class="glass-panel" style="padding:0.85rem 1.1rem;text-align:right;">
    <div class="text-xs text-muted">Pending withdrawals</div>
    <div style="font-size:1.25rem;font-weight:800;color:var(--clr-accent);"><?= formatMWK((float) ($summary['pending_amount'] ?? 0)) ?></div>
  </div>
</div>

<?php if ($flashSuccess): ?><div class="alert alert-success">Success: <?= e($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert alert-error">Error: <?= e($flashError) ?></div><?php endif; ?>

<div class="grid grid-cols-4 gap-2" style="margin-bottom:1.5rem;">
  <div class="stat-card"><div class="stat-icon stat-icon-blue"><?= admin_icon_svg('wallet') ?></div><div><div class="stat-value"><?= number_format((int) ($summary['total_requests'] ?? 0)) ?></div><div class="stat-label">Total Requests</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-yellow"><?= admin_icon_svg('clock') ?></div><div><div class="stat-value"><?= formatMWK((float) ($summary['pending_amount'] ?? 0)) ?></div><div class="stat-label">Pending Amount</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-green"><?= admin_icon_svg('check') ?></div><div><div class="stat-value"><?= formatMWK((float) ($summary['processed_amount'] ?? 0)) ?></div><div class="stat-label">Processed Amount</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-purple"><?= admin_icon_svg('bank') ?></div><div><div class="stat-value"><?= formatMWK((float) ($payoutSummary['payout_total'] ?? 0)) ?></div><div class="stat-label">Payout Volume</div></div></div>
</div>

<div class="grid grid-cols-3 gap-2" style="margin-bottom:1.5rem;">
  <div class="glass-panel" style="padding:1.25rem;">
    <div class="text-xs text-muted">Wallets available</div>
    <div style="font-size:1.35rem;font-weight:800;"><?= formatMWK((float) ($walletSummary['available_total'] ?? 0)) ?></div>
  </div>
  <div class="glass-panel" style="padding:1.25rem;">
    <div class="text-xs text-muted">Wallets pending</div>
    <div style="font-size:1.35rem;font-weight:800;"><?= formatMWK((float) ($walletSummary['pending_total'] ?? 0)) ?></div>
  </div>
  <div class="glass-panel" style="padding:1.25rem;">
    <div class="text-xs text-muted">Processed payouts</div>
    <div style="font-size:1.35rem;font-weight:800;"><?= number_format((int) ($payoutSummary['payout_count'] ?? 0)) ?></div>
  </div>
</div>

<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;">
  <div class="section-head" style="margin-bottom:1rem;">
    <div>
      <h2 class="page-title" style="font-size:1.25rem;">Pending Settlement Queue</h2>
      <p class="text-muted">Approve or reject each withdrawal request. Approved requests are marked processed immediately and logged as payouts.</p>
    </div>
  </div>

  <?php if (empty($requests)): ?>
    <div class="text-muted">No withdrawal requests found.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Vendor</th>
            <th>Method</th>
            <th>Destination</th>
            <th>Amount</th>
            <th>Charges</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $row): ?>
            <?php
              $businessName = trim((string) ($row['display_name'] ?? $row['business_name'] ?? ''));
              if ($businessName === '') {
                  $businessName = trim((string) ($row['owner_name'] ?? 'Vendor #' . $row['vendor_id']));
              }
            ?>
            <tr>
              <td class="font-mono text-xs"><?= e($row['request_ref_label'] ?? ('WDR-' . $row['id'])) ?></td>
              <td>
                <strong><?= e($businessName) ?></strong><br>
                <span class="text-xs text-muted"><?= e($row['owner_email'] ?? $row['business_email'] ?? '') ?></span>
              </td>
              <td><?= e($row['request_method'] ?? '') ?></td>
              <td><?= e($row['destination'] ?? 'N/A') ?></td>
              <td style="font-weight:700;color:var(--clr-accent);"><?= formatMWK((float) ($row['amount'] ?? 0)) ?></td>
              <td><?= formatMWK((float) ($row['charges_amount'] ?? 0)) ?></td>
              <td><span class="badge <?= uthenga_settlement_badge((string) ($row['status'] ?? 'pending')) ?>"><?= e(ucfirst((string) ($row['status'] ?? 'pending'))) ?></span></td>
              <td>
                <?php if (in_array(strtolower((string) ($row['status'] ?? 'pending')), ['pending', 'approved'], true)): ?>
                  <form method="POST" action="" style="display:inline-flex;gap:.35rem;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>">
                    <button type="submit" name="settlement_action" value="approve" class="btn btn-primary btn-sm">Approve &amp; Pay</button>
                    <button type="submit" name="settlement_action" value="reject" class="btn btn-secondary btn-sm">Reject</button>
                  </form>
                <?php else: ?>
                  <span class="text-xs text-muted">Completed</span>
                <?php endif; ?>
              </td>
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
      <h2 class="page-title" style="font-size:1.25rem;">Processed Payouts</h2>
      <p class="text-muted">Completed payouts are recorded here for audit and reconciliation.</p>
    </div>
  </div>

  <?php if (empty($payouts)): ?>
    <div class="text-muted">No processed payouts yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Reference</th>
            <th>Vendor</th>
            <th>Amount</th>
            <th>Charges</th>
            <th>Status</th>
            <th>Processed At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payouts as $row): ?>
            <?php
              $businessName = trim((string) ($row['display_name'] ?? $row['business_name'] ?? ''));
              if ($businessName === '') {
                  $businessName = trim((string) ($row['owner_name'] ?? 'Vendor #' . $row['vendor_id']));
              }
            ?>
            <tr>
              <td class="font-mono text-xs"><?= e($row['transaction_reference'] ?? ('PAYOUT-' . $row['id'])) ?></td>
              <td><?= e($businessName) ?></td>
              <td style="font-weight:700;color:var(--clr-accent);"><?= formatMWK((float) ($row['amount'] ?? 0)) ?></td>
              <td><?= formatMWK((float) ($row['charges_amount'] ?? 0)) ?></td>
              <td><span class="badge <?= uthenga_settlement_badge((string) ($row['status'] ?? 'processed')) ?>"><?= e(ucfirst((string) ($row['status'] ?? 'processed'))) ?></span></td>
              <td class="text-xs text-muted"><?= e(substr((string) ($row['processed_at'] ?? $row['created_at'] ?? ''), 0, 16)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/admin_footer.php';
