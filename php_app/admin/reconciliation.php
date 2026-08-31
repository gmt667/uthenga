<?php
/**
 * Uthenga — Payment Engine Reconciliation & Audit Trail
 * Internal reconciliation: do Uthenga's own ledgers agree with each other,
 * and is any payment intent stuck in a state it should have moved out of.
 * (A true 3-way reconciliation against the provider's own records is out of
 * scope until a real settlement-report API is available — see the payment
 * engine's REFUNDED/refund docs for the same "no fabricated provider data"
 * discipline.)
 */
$pageTitle = 'Payment Reconciliation';
$activeNav = 'admin-reconciliation';

require_once __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/payment_engine.php';

// ─── Platform settlement account + settlement records ──────────────────────
// Uthenga's own bank/mobile-money destination for its commission revenue —
// distinct from uthenga_vendor_payment_profiles (each vendor's own payout
// destination). No real payout API exists anywhere in this codebase to
// automate a transfer, so this is an honest manual-settlement record: an
// admin transfers the money outside the app, then records that it happened.
$settleMessage = '';
$settleErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    requireAdminPermission('finance.manage');
    requireRecentAdminReauthentication('finance');
    if (isset($_POST['save_settlement_account'])) {
        $accountType = trim((string) ($_POST['account_type'] ?? 'bank'));
        if (!in_array($accountType, ['bank', 'mobile_money'], true)) {
            $settleErr = 'Choose a valid account type.';
        } else {
            $existing = dbQueryOne('SELECT id FROM uthenga_platform_settlement_accounts ORDER BY id DESC LIMIT 1');
            $params = [
                $accountType,
                $accountType === 'bank' ? trim((string) $_POST['bank_name']) : null,
                $accountType === 'bank' ? trim((string) $_POST['account_name']) : null,
                $accountType === 'bank' ? trim((string) $_POST['account_number']) : null,
                $accountType === 'mobile_money' ? trim((string) $_POST['mobile_money_provider']) : null,
                $accountType === 'mobile_money' ? trim((string) $_POST['mobile_money_number']) : null,
                $_SESSION['user_id'] ?? null,
            ];
            if ($existing) {
                dbExecute("UPDATE uthenga_platform_settlement_accounts SET account_type=?, bank_name=?, account_name=?, account_number=?, mobile_money_provider=?, mobile_money_number=?, updated_by=? WHERE id=?", [...$params, $existing['id']]);
            } else {
                dbExecute("INSERT INTO uthenga_platform_settlement_accounts (account_type, bank_name, account_name, account_number, mobile_money_provider, mobile_money_number, updated_by) VALUES (?,?,?,?,?,?,?)", $params);
            }
            logAction('Updated Platform Settlement Account', 'Admin updated Uthenga\'s own settlement destination.');
            $settleMessage = 'Settlement account saved.';
        }
    } elseif (isset($_POST['record_settlement'])) {
        $periodStart = trim((string) ($_POST['period_start'] ?? ''));
        $periodEnd = trim((string) ($_POST['period_end'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? 0);
        $note = trim((string) ($_POST['reference_note'] ?? ''));
        $account = dbQueryOne('SELECT * FROM uthenga_platform_settlement_accounts ORDER BY id DESC LIMIT 1');

        if ($periodStart === '' || $periodEnd === '' || strtotime($periodStart) === false || strtotime($periodEnd) === false) {
            $settleErr = 'Enter a valid settlement period.';
        } elseif ($amount <= 0) {
            $settleErr = 'Enter a settlement amount greater than zero.';
        } elseif (!$account) {
            $settleErr = 'Configure a settlement account before recording a settlement.';
        } else {
            dbExecute(
                'INSERT INTO uthenga_platform_settlements (id, period_start, period_end, amount, destination_snapshot, reference_note, actor_id) VALUES (?,?,?,?,?,?,?)',
                ['pst_' . bin2hex(random_bytes(12)), $periodStart, $periodEnd, $amount, json_encode($account), $note ?: null, $_SESSION['user_id'] ?? 'admin']
            );
            logAction('Recorded Platform Settlement', "Admin recorded a settlement of MWK " . number_format($amount, 2) . " for {$periodStart} to {$periodEnd}.");
            $settleMessage = 'Settlement recorded.';
        }
    }
}

$settlementAccount = dbQueryOne('SELECT * FROM uthenga_platform_settlement_accounts ORDER BY id DESC LIMIT 1');
$settlementHistory = dbQuery('SELECT * FROM uthenga_platform_settlements ORDER BY created_at DESC LIMIT 20');
$totalNetRevenue = (float) (dbQueryOne('SELECT COALESCE(SUM(net_revenue),0) AS t FROM uthenga_revenue_ledger')['t'] ?? 0);
$totalSettled = (float) (dbQueryOne('SELECT COALESCE(SUM(amount),0) AS t FROM uthenga_platform_settlements')['t'] ?? 0);
$totalUnsettled = max(0, $totalNetRevenue - $totalSettled);

// ─── Ledger totals by service category ─────────────────────────────────────
$revenueByCategory = dbQuery("
    SELECT service_category,
           SUM(gross_amount) AS gross,
           SUM(platform_fee) AS platform_fee,
           SUM(net_revenue) AS net_revenue,
           COUNT(*) AS row_count
    FROM uthenga_revenue_ledger
    GROUP BY service_category
");

$payableByCategory = dbQuery("
    SELECT service_category,
           SUM(CASE WHEN payout_status = 'PENDING' THEN net_payable ELSE 0 END) AS pending_payable,
           SUM(CASE WHEN payout_status = 'PROCESSED' THEN net_payable ELSE 0 END) AS processed_payable
    FROM uthenga_vendor_payable_ledger
    GROUP BY service_category
");
$payableIndex = [];
foreach ($payableByCategory as $row) { $payableIndex[$row['service_category']] = $row; }

$intentStatusCounts = dbQuery("SELECT status, COUNT(*) AS c FROM uthenga_payment_intents GROUP BY status ORDER BY c DESC");

$totalRefunded = dbQueryOne("SELECT COALESCE(SUM(amount),0) AS total FROM uthenga_refund_ledger");
$totalCustomerPaid = dbQueryOne("SELECT COALESCE(SUM(amount),0) AS total FROM uthenga_customer_ledger");

// ─── Data-integrity checks ──────────────────────────────────────────────────
// Every intent that ever reached SETTLED must have posted exactly one
// positive row into each of the 3 ledgers. A settled intent with zero
// revenue/vendor rows means postings silently failed somewhere.
$settledWithoutRevenue = dbQuery("
    SELECT pi.intent_ref, pi.service_type, pi.gross_amount, pi.updated_at
    FROM uthenga_payment_intents pi
    WHERE pi.status IN ('SETTLED', 'PARTIALLY_REFUNDED', 'REFUNDED')
      AND NOT EXISTS (SELECT 1 FROM uthenga_revenue_ledger rl WHERE rl.intent_ref = pi.intent_ref AND rl.gross_amount > 0)
    ORDER BY pi.updated_at DESC LIMIT 50
");
$settledWithoutPayable = dbQuery("
    SELECT pi.intent_ref, pi.service_type, pi.gross_amount, pi.updated_at
    FROM uthenga_payment_intents pi
    WHERE pi.status IN ('SETTLED', 'PARTIALLY_REFUNDED', 'REFUNDED')
      AND NOT EXISTS (SELECT 1 FROM uthenga_vendor_payable_ledger vl WHERE vl.intent_ref = pi.intent_ref AND vl.gross_amount > 0)
    ORDER BY pi.updated_at DESC LIMIT 50
");

// Intents stuck pre-confirmation past their own expiry — should have been
// lazily swept on the next check_status/checkout poll, but nobody has polled
// since (e.g. the customer just closed the browser tab entirely).
$stuckIntents = dbQuery("
    SELECT intent_ref, service_type, status, gross_amount, expires_at, created_at
    FROM uthenga_payment_intents
    WHERE status IN ('CREATED', 'PAYMENT_PENDING', 'PROCESSING')
      AND expires_at < NOW()
    ORDER BY expires_at ASC LIMIT 50
");

// Refund totals that don't reconcile against their own intent's gross amount
// (would indicate an over-refund slipped past refundIntent()'s own guard).
$overRefunded = dbQuery("
    SELECT pi.intent_ref, pi.gross_amount, SUM(rl.amount) AS total_refunded
    FROM uthenga_payment_intents pi
    INNER JOIN uthenga_refund_ledger rl ON rl.intent_ref = pi.intent_ref
    GROUP BY pi.intent_ref, pi.gross_amount
    HAVING total_refunded > pi.gross_amount + 0.01
");

// ─── Audit trail search ─────────────────────────────────────────────────────
$searchRef = trim($_GET['intent_ref'] ?? '');
$auditRows = [];
if ($searchRef !== '') {
    $auditRows = dbQuery("SELECT * FROM uthenga_payment_audit_log WHERE intent_ref = ? ORDER BY created_at ASC, id ASC", [$searchRef]);
}
$recentAudit = dbQuery("SELECT * FROM uthenga_payment_audit_log ORDER BY created_at DESC, id DESC LIMIT 60");

function reconMoney($v): string { return 'MK ' . number_format((float) $v, 2); }
function reconBadge(string $status): string {
    $ok = in_array($status, ['SETTLED', 'CONFIRMED'], true);
    $bad = in_array($status, ['FAILED', 'EXPIRED', 'CANCELLED'], true);
    $refund = in_array($status, ['REFUNDED', 'PARTIALLY_REFUNDED'], true);
    $cls = $ok ? 'badge-approved' : ($bad ? 'badge-rejected' : ($refund ? 'badge-pending' : 'badge-pending'));
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Payment Reconciliation</h1>
    <p class="text-muted">Internal ledger cross-checks and the structured payment audit trail — every state-changing action against a payment intent, with actor, source, and before/after status.</p>
  </div>
</div>

<?php if ($settleMessage): ?><div class="alert alert-success" style="margin-bottom:1rem;"><?= e($settleMessage) ?></div><?php endif; ?>
<?php if ($settleErr): ?><div class="alert alert-error" style="margin-bottom:1rem;"><?= e($settleErr) ?></div><?php endif; ?>

<!-- Platform Settlement -->
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;">
  <h3 style="margin-top:0;">Platform Settlement</h3>
  <p class="text-muted text-sm">Where Uthenga's own commission revenue is banked — distinct from vendor payout profiles. No automated payout API exists to build a "click to withdraw" on top of, so this records a settlement an admin actually carried out outside the app.</p>

  <div style="display:flex;gap:1.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
    <div><span class="text-muted text-xs">Total Net Revenue</span><br><strong>MK <?= number_format($totalNetRevenue, 2) ?></strong></div>
    <div><span class="text-muted text-xs">Settled to Date</span><br><strong style="color:#10b981;">MK <?= number_format($totalSettled, 2) ?></strong></div>
    <div><span class="text-muted text-xs">Unsettled</span><br><strong style="color:#f59e0b;">MK <?= number_format($totalUnsettled, 2) ?></strong></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
    <div>
      <h4 style="font-size:0.9rem;text-transform:uppercase;color:var(--clr-text-soft);margin-bottom:0.75rem;">Settlement Account</h4>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="save_settlement_account" value="1">
        <div class="form-group">
          <label class="form-label">Account Type</label>
          <select name="account_type" class="form-control" id="settlement-account-type" onchange="uthengaToggleSettlementAccount(this.value)">
            <option value="bank" <?= ($settlementAccount['account_type'] ?? 'bank') === 'bank' ? 'selected' : '' ?>>Bank Account</option>
            <option value="mobile_money" <?= ($settlementAccount['account_type'] ?? '') === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
          </select>
        </div>
        <div id="settlement-bank-fields">
          <div class="form-group"><label class="form-label">Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= e($settlementAccount['bank_name'] ?? '') ?>"></div>
          <div class="form-group"><label class="form-label">Account Name</label><input type="text" name="account_name" class="form-control" value="<?= e($settlementAccount['account_name'] ?? '') ?>"></div>
          <div class="form-group"><label class="form-label">Account Number</label><input type="text" name="account_number" class="form-control" value="<?= e($settlementAccount['account_number'] ?? '') ?>"></div>
        </div>
        <div id="settlement-mobile-fields" style="display:none;">
          <div class="form-group"><label class="form-label">Provider</label><select name="mobile_money_provider" class="form-control"><option value="airtel" <?= ($settlementAccount['mobile_money_provider'] ?? '') === 'airtel' ? 'selected' : '' ?>>Airtel Money</option><option value="mpamba" <?= ($settlementAccount['mobile_money_provider'] ?? '') === 'mpamba' ? 'selected' : '' ?>>TNM Mpamba</option></select></div>
          <div class="form-group"><label class="form-label">Mobile Number</label><input type="text" name="mobile_money_number" class="form-control" value="<?= e($settlementAccount['mobile_money_number'] ?? '') ?>"></div>
        </div>
        <button type="submit" class="btn btn-secondary">Save Settlement Account</button>
      </form>
    </div>
    <div>
      <h4 style="font-size:0.9rem;text-transform:uppercase;color:var(--clr-text-soft);margin-bottom:0.75rem;">Record a Settlement</h4>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="record_settlement" value="1">
        <div class="form-group"><label class="form-label">Period Start</label><input type="date" name="period_start" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Period End</label><input type="date" name="period_end" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Amount Settled (MWK)</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Reference Note</label><input type="text" name="reference_note" class="form-control" placeholder="e.g. Bank transfer ref #..."></div>
        <button type="submit" class="btn btn-primary">Record Settlement</button>
      </form>
    </div>
  </div>

  <h4 style="font-size:0.9rem;text-transform:uppercase;color:var(--clr-text-soft);margin:1.5rem 0 0.75rem;">Settlement History</h4>
  <?php if (empty($settlementHistory)): ?>
    <p class="text-muted text-sm">No settlements recorded yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="admin-table">
      <thead><tr><th>Period</th><th>Amount</th><th>Note</th><th>Recorded By</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($settlementHistory as $s): ?>
          <tr>
            <td><?= e($s['period_start']) ?> – <?= e($s['period_end']) ?></td>
            <td>MK <?= number_format((float) $s['amount'], 2) ?></td>
            <td class="text-sm"><?= e($s['reference_note'] ?? '') ?></td>
            <td class="text-xs"><?= e($s['actor_id']) ?></td>
            <td class="text-xs text-muted"><?= e($s['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
function uthengaToggleSettlementAccount(type) {
  document.getElementById('settlement-bank-fields').style.display = type === 'bank' ? 'block' : 'none';
  document.getElementById('settlement-mobile-fields').style.display = type === 'mobile_money' ? 'block' : 'none';
}
uthengaToggleSettlementAccount(document.getElementById('settlement-account-type').value);
</script>

<!-- Revenue by category -->
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;">
  <h3 style="margin-top:0;">Platform Revenue Ledger (by category)</h3>
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr><th>Category</th><th>Gross</th><th>Platform Fee</th><th>Net Revenue</th><th>Vendor Payable (Pending)</th><th>Vendor Payable (Processed)</th><th>Ledger Rows</th></tr>
      </thead>
      <tbody>
        <?php if (empty($revenueByCategory)): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;">No ledger activity yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($revenueByCategory as $row): $pay = $payableIndex[$row['service_category']] ?? ['pending_payable' => 0, 'processed_payable' => 0]; ?>
          <tr>
            <td><strong><?= e(ucfirst($row['service_category'])) ?></strong></td>
            <td><?= reconMoney($row['gross']) ?></td>
            <td><?= reconMoney($row['platform_fee']) ?></td>
            <td><?= reconMoney($row['net_revenue']) ?></td>
            <td><?= reconMoney($pay['pending_payable']) ?></td>
            <td><?= reconMoney($pay['processed_payable']) ?></td>
            <td class="text-muted"><?= (int) $row['row_count'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div style="display:flex;gap:2rem;margin-top:1rem;flex-wrap:wrap;">
    <div><span class="text-muted text-xs">Total customer ledger (net of refunds)</span><br><strong><?= reconMoney($totalCustomerPaid['total']) ?></strong></div>
    <div><span class="text-muted text-xs">Total refunded (uthenga_refund_ledger)</span><br><strong><?= reconMoney($totalRefunded['total']) ?></strong></div>
  </div>
</div>

<!-- Intent status breakdown -->
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;">
  <h3 style="margin-top:0;">Payment Intents by Status</h3>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
    <?php foreach ($intentStatusCounts as $row): ?>
      <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:0.6rem 1rem;">
        <?= reconBadge($row['status']) ?>
        <div style="font-size:1.25rem;font-weight:800;margin-top:0.25rem;"><?= (int) $row['c'] ?></div>
      </div>
    <?php endforeach; ?>
    <?php if (empty($intentStatusCounts)): ?><div class="text-muted">No payment intents yet.</div><?php endif; ?>
  </div>
</div>

<!-- Integrity exceptions -->
<div class="glass-panel" style="padding:1.5rem;margin-bottom:1.5rem;">
  <h3 style="margin-top:0;">Exceptions Requiring Attention</h3>

  <h4 style="font-size:0.85rem;text-transform:uppercase;color:var(--clr-text-soft);margin-bottom:0.5rem;">Settled intents missing a revenue ledger row (<?= count($settledWithoutRevenue) ?>)</h4>
  <?php if (empty($settledWithoutRevenue)): ?>
    <p class="text-muted text-sm">None — every settled intent has posted revenue.</p>
  <?php else: ?>
    <div class="table-responsive"><table class="admin-table"><thead><tr><th>Intent</th><th>Service</th><th>Amount</th><th>Updated</th></tr></thead><tbody>
      <?php foreach ($settledWithoutRevenue as $row): ?>
        <tr><td><a href="?intent_ref=<?= urlencode($row['intent_ref']) ?>"><?= e($row['intent_ref']) ?></a></td><td><?= e($row['service_type']) ?></td><td><?= reconMoney($row['gross_amount']) ?></td><td class="text-xs text-muted"><?= e($row['updated_at']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>

  <h4 style="font-size:0.85rem;text-transform:uppercase;color:var(--clr-text-soft);margin:1.25rem 0 0.5rem;">Settled intents missing a vendor payable row (<?= count($settledWithoutPayable) ?>)</h4>
  <?php if (empty($settledWithoutPayable)): ?>
    <p class="text-muted text-sm">None — every settled intent has a vendor payable posting.</p>
  <?php else: ?>
    <div class="table-responsive"><table class="admin-table"><thead><tr><th>Intent</th><th>Service</th><th>Amount</th><th>Updated</th></tr></thead><tbody>
      <?php foreach ($settledWithoutPayable as $row): ?>
        <tr><td><a href="?intent_ref=<?= urlencode($row['intent_ref']) ?>"><?= e($row['intent_ref']) ?></a></td><td><?= e($row['service_type']) ?></td><td><?= reconMoney($row['gross_amount']) ?></td><td class="text-xs text-muted"><?= e($row['updated_at']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>

  <h4 style="font-size:0.85rem;text-transform:uppercase;color:var(--clr-text-soft);margin:1.25rem 0 0.5rem;">Intents stuck past their own expiry, unswept (<?= count($stuckIntents) ?>)</h4>
  <?php if (empty($stuckIntents)): ?>
    <p class="text-muted text-sm">None — nothing is waiting on a sweep it hasn't had yet.</p>
  <?php else: ?>
    <div class="table-responsive"><table class="admin-table"><thead><tr><th>Intent</th><th>Service</th><th>Status</th><th>Amount</th><th>Expired At</th></tr></thead><tbody>
      <?php foreach ($stuckIntents as $row): ?>
        <tr><td><a href="?intent_ref=<?= urlencode($row['intent_ref']) ?>"><?= e($row['intent_ref']) ?></a></td><td><?= e($row['service_type']) ?></td><td><?= reconBadge($row['status']) ?></td><td><?= reconMoney($row['gross_amount']) ?></td><td class="text-xs text-muted"><?= e($row['expires_at']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>

  <h4 style="font-size:0.85rem;text-transform:uppercase;color:var(--clr-text-soft);margin:1.25rem 0 0.5rem;">Over-refunded intents (<?= count($overRefunded) ?>)</h4>
  <?php if (empty($overRefunded)): ?>
    <p class="text-muted text-sm">None — no intent has been refunded beyond its gross amount.</p>
  <?php else: ?>
    <div class="table-responsive"><table class="admin-table"><thead><tr><th>Intent</th><th>Gross</th><th>Total Refunded</th></tr></thead><tbody>
      <?php foreach ($overRefunded as $row): ?>
        <tr><td><a href="?intent_ref=<?= urlencode($row['intent_ref']) ?>"><?= e($row['intent_ref']) ?></a></td><td><?= reconMoney($row['gross_amount']) ?></td><td style="color:var(--clr-danger, #e63946);"><?= reconMoney($row['total_refunded']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<!-- Audit trail -->
<div class="glass-panel" style="padding:1.5rem;">
  <h3 style="margin-top:0;">Payment Audit Trail</h3>
  <form method="GET" style="display:flex;gap:0.5rem;margin-bottom:1rem;">
    <input type="text" name="intent_ref" class="form-control" placeholder="Search by intent reference (e.g. UTH-XXXXXXXX)" value="<?= e($searchRef) ?>" style="max-width:340px;">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($searchRef !== ''): ?><a href="reconciliation.php" class="btn btn-secondary">Clear</a><?php endif; ?>
  </form>

  <?php $rowsToShow = $searchRef !== '' ? $auditRows : $recentAudit; ?>
  <?php if ($searchRef !== ''): ?>
    <p class="text-muted text-sm">Showing <?= count($auditRows) ?> event(s) for <strong><?= e($searchRef) ?></strong>, oldest first.</p>
  <?php else: ?>
    <p class="text-muted text-sm">Most recent 60 events across all intents.</p>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="admin-table">
      <thead><tr><th style="width:150px;">Timestamp</th><th>Intent</th><th>Action</th><th>Source</th><th>Actor</th><th>From → To</th><th>Note</th></tr></thead>
      <tbody>
        <?php if (empty($rowsToShow)): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;">No audit events found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rowsToShow as $row): ?>
          <tr>
            <td class="text-xs text-muted"><?= e($row['created_at']) ?></td>
            <td><a href="?intent_ref=<?= urlencode($row['intent_ref']) ?>"><?= e($row['intent_ref']) ?></a></td>
            <td><span class="badge badge-pending"><?= e($row['action']) ?></span></td>
            <td class="text-xs"><?= e($row['source']) ?></td>
            <td class="text-xs"><?= e($row['actor_id']) ?></td>
            <td class="text-xs"><?= e($row['from_status'] ?? '—') ?> → <?= e($row['to_status'] ?? '—') ?></td>
            <td class="text-xs text-muted"><?= e($row['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
?>
