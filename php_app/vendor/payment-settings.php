<?php
/**
 * Uthenga — Vendor Payment Settlement Profile
 *
 * The vendor configures ONLY where their own payouts land — a bank account
 * or mobile money number. Uthenga's own PayChangu Secret Key/API
 * Secret/Webhook Secret are never collected here (or anywhere vendor-facing);
 * those live solely in the platform's centralized gateway config. This form
 * is the vendor's half of the "Vendor Payment Profile" step in the payment
 * configuration hierarchy — Admin owns commission rules and provider
 * credentials, the vendor owns their own settlement destination.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireApprovedVendor();

$vendorId = (string) ($_SESSION['user_id'] ?? '');
$message = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $method = trim((string) ($_POST['settlement_method'] ?? 'bank'));
    if (!in_array($method, ['bank', 'mobile_money'], true)) {
        $err = 'Choose a valid settlement method.';
    } elseif ($method === 'bank' && (trim($_POST['bank_name'] ?? '') === '' || trim($_POST['bank_account_number'] ?? '') === '' || trim($_POST['bank_account_name'] ?? '') === '')) {
        $err = 'Bank name, account name and account number are required.';
    } elseif ($method === 'mobile_money' && (trim($_POST['mobile_money_provider'] ?? '') === '' || trim($_POST['mobile_money_number'] ?? '') === '')) {
        $err = 'Mobile money provider and number are required.';
    } else {
        $existing = dbQueryOne('SELECT vendor_id FROM uthenga_vendor_payment_profiles WHERE vendor_id = ?', [$vendorId]);
        $params = [
            $method,
            $method === 'bank' ? trim($_POST['bank_name']) : null,
            $method === 'bank' ? trim($_POST['bank_account_name']) : null,
            $method === 'bank' ? trim($_POST['bank_account_number']) : null,
            $method === 'bank' ? trim($_POST['bank_branch'] ?? '') : null,
            $method === 'mobile_money' ? trim($_POST['mobile_money_provider']) : null,
            $method === 'mobile_money' ? trim($_POST['mobile_money_number']) : null,
        ];
        if ($existing) {
            // Any change to the settlement destination must be re-verified —
            // never let an edited payout destination silently keep an old
            // "verified" status.
            dbExecute("
                UPDATE uthenga_vendor_payment_profiles
                SET settlement_method = ?, bank_name = ?, bank_account_name = ?, bank_account_number = ?, bank_branch = ?,
                    mobile_money_provider = ?, mobile_money_number = ?, verification_status = 'PENDING', verified_by = NULL, verified_at = NULL
                WHERE vendor_id = ?
            ", [...$params, $vendorId]);
        } else {
            dbExecute("
                INSERT INTO uthenga_vendor_payment_profiles
                    (vendor_id, settlement_method, bank_name, bank_account_name, bank_account_number, bank_branch, mobile_money_provider, mobile_money_number, verification_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')
            ", [$vendorId, ...$params]);
        }
        $message = 'Payment settlement details saved. Uthenga will verify your payout destination before your next settlement.';
    }
}

$profile = dbQueryOne('SELECT * FROM uthenga_vendor_payment_profiles WHERE vendor_id = ?', [$vendorId]);

$statusBadge = [
    'PENDING'  => ['#f59e0b', 'Pending verification'],
    'VERIFIED' => ['#10b981', 'Verified'],
    'REJECTED' => ['#e63946', 'Rejected — please review and resubmit'],
][$profile['verification_status'] ?? 'PENDING'] ?? ['#94a3b8', 'Not configured'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container dashboard-content-frame" style="padding-top:2.25rem;padding-bottom:3rem;max-width:720px;">
  <div class="page-header">
    <div>
      <h1 class="page-title">Payment Settlement Profile</h1>
      <p class="text-muted">Tell Uthenga where to send your earnings. You never need a payment provider account or API keys — Uthenga's payment infrastructure is fully centralized and managed for you.</p>
    </div>
  </div>

  <?php if ($message): ?><div class="alert alert-success" style="margin-bottom:1.25rem;"><?= e($message) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error" style="margin-bottom:1.25rem;"><?= e($err) ?></div><?php endif; ?>

  <?php if ($profile): ?>
  <div class="glass-panel" style="padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.75rem;">
    <span style="width:10px;height:10px;border-radius:50%;background:<?= $statusBadge[0] ?>;flex-shrink:0;"></span>
    <span><strong>Status:</strong> <?= e($statusBadge[1]) ?></span>
  </div>
  <?php endif; ?>

  <div class="glass-panel" style="padding:2rem;">
    <form method="POST" id="settlement-form">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <div class="form-group">
        <label class="form-label">Settlement Method</label>
        <select name="settlement_method" class="form-control" id="settlement-method-select" onchange="uthengaToggleSettlement(this.value)">
          <option value="bank" <?= ($profile['settlement_method'] ?? 'bank') === 'bank' ? 'selected' : '' ?>>Bank Account</option>
          <option value="mobile_money" <?= ($profile['settlement_method'] ?? '') === 'mobile_money' ? 'selected' : '' ?>>Mobile Money (Airtel Money / TNM Mpamba)</option>
        </select>
      </div>

      <div id="bank-fields">
        <div class="form-group">
          <label class="form-label">Bank Name</label>
          <input type="text" name="bank_name" class="form-control" value="<?= e($profile['bank_name'] ?? '') ?>" placeholder="e.g. National Bank of Malawi">
        </div>
        <div class="form-group">
          <label class="form-label">Account Name</label>
          <input type="text" name="bank_account_name" class="form-control" value="<?= e($profile['bank_account_name'] ?? '') ?>" placeholder="Name on the account">
        </div>
        <div class="form-group">
          <label class="form-label">Account Number</label>
          <input type="text" name="bank_account_number" class="form-control" value="<?= e($profile['bank_account_number'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Branch (optional)</label>
          <input type="text" name="bank_branch" class="form-control" value="<?= e($profile['bank_branch'] ?? '') ?>">
        </div>
      </div>

      <div id="mobile-fields" style="display:none;">
        <div class="form-group">
          <label class="form-label">Mobile Money Provider</label>
          <select name="mobile_money_provider" class="form-control">
            <option value="airtel" <?= ($profile['mobile_money_provider'] ?? '') === 'airtel' ? 'selected' : '' ?>>Airtel Money</option>
            <option value="mpamba" <?= ($profile['mobile_money_provider'] ?? '') === 'mpamba' ? 'selected' : '' ?>>TNM Mpamba</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Mobile Money Number</label>
          <input type="text" name="mobile_money_number" class="form-control" value="<?= e($profile['mobile_money_number'] ?? '') ?>" placeholder="099X XXX XXX">
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem;">Save Settlement Details</button>
    </form>
  </div>

  <p class="text-muted text-sm" style="margin-top:1rem;">
    Commission rates, service fees, and payment provider infrastructure are managed centrally by Uthenga — you don't need to configure or maintain any of that here.
  </p>
</div>

<script>
function uthengaToggleSettlement(method) {
  document.getElementById('bank-fields').style.display = method === 'bank' ? 'block' : 'none';
  document.getElementById('mobile-fields').style.display = method === 'mobile_money' ? 'block' : 'none';
}
uthengaToggleSettlement(document.getElementById('settlement-method-select').value);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
