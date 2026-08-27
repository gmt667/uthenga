<?php
/**
 * Uthenga — Customer Payment History
 * Every payment and refund the logged-in customer has made through the
 * Uthenga Payment Engine, across every service (Accommodation, Events, Shop).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

requireLogin();
$userId = (string) $_SESSION['user_id'];

$payments = dbQuery("
    SELECT cl.*, pi.service_type, pi.service_id, pi.booking_id
    FROM uthenga_customer_ledger cl
    LEFT JOIN uthenga_payment_intents pi ON pi.intent_ref = cl.intent_ref
    WHERE cl.customer_id = ?
    ORDER BY cl.created_at DESC
    LIMIT 100
", [$userId]);

$totalPaid = dbQueryOne("SELECT COALESCE(SUM(amount),0) AS total FROM uthenga_customer_ledger WHERE customer_id = ? AND amount > 0", [$userId]);
$totalRefunded = dbQueryOne("SELECT COALESCE(SUM(ABS(amount)),0) AS total FROM uthenga_customer_ledger WHERE customer_id = ? AND amount < 0", [$userId]);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container dashboard-content-frame" style="padding-top:2.25rem;padding-bottom:3rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title">Payment History</h1>
      <p class="text-muted">Every payment and refund on your Uthenga account.</p>
    </div>
  </div>

  <div style="display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap;">
    <div class="glass-panel" style="padding:1rem 1.5rem;">
      <div class="text-xs text-muted" style="text-transform:uppercase;">Total Paid</div>
      <div style="font-size:1.4rem;font-weight:800;">MK <?= number_format((float) $totalPaid['total'], 2) ?></div>
    </div>
    <div class="glass-panel" style="padding:1rem 1.5rem;">
      <div class="text-xs text-muted" style="text-transform:uppercase;">Total Refunded</div>
      <div style="font-size:1.4rem;font-weight:800;">MK <?= number_format((float) $totalRefunded['total'], 2) ?></div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.5rem;">
    <?php if (empty($payments)): ?>
      <p class="text-muted" style="text-align:center;padding:2rem 0;">No payments yet.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead><tr><th>Date</th><th>Service</th><th>Method</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead>
          <tbody>
            <?php foreach ($payments as $p): $isRefund = (float) $p['amount'] < 0; ?>
              <tr>
                <td class="text-xs text-muted"><?= e($p['created_at']) ?></td>
                <td><?= e(ucfirst((string) ($p['service_type'] ?? '—'))) ?></td>
                <td class="text-sm"><?= e(ucfirst(str_replace('_', ' ', (string) $p['payment_method']))) ?></td>
                <td style="<?= $isRefund ? 'color:#e63946;' : '' ?>font-weight:700;">
                  <?= $isRefund ? '−' : '' ?>MK <?= number_format(abs((float) $p['amount']), 2) ?>
                </td>
                <td><span class="badge <?= $isRefund ? 'badge-pending' : 'badge-approved' ?>"><?= e($p['status']) ?></span></td>
                <td><a href="<?= BASE_URL ?>payments/receipt.php?receipt=<?= urlencode($p['receipt_number']) ?>" target="_blank" class="btn btn-sm btn-secondary">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
