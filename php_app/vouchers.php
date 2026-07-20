<?php
/**
 * Uthenga — Gift Vouchers Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$success = '';
$error = '';
$voucherDetails = null;

$userId = $_SESSION['user_id'] ?? null;

// ─── Handle Purchase ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_voucher'])) {
    if (!validateCsrf()) {
        $error = 'Security check failed. Please refresh.';
    } else {
        $recipientName  = trim($_POST['recipient_name'] ?? '');
        $recipientEmail = strtolower(trim($_POST['recipient_email'] ?? ''));
        $amount         = (float)($_POST['amount'] ?? 0);
        $message        = trim($_POST['message'] ?? '');

        if ($amount < 1000) {
            $error = 'Minimum voucher purchase amount is MK 1,000.';
        } elseif (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid recipient email address.';
        } else {
            // Generate unique voucher code: e.g. GV-XXXX-XXXX
            $code = 'GV-' . strtoupper(substr(md5(uniqid()), 0, 4) . '-' . substr(md5(uniqid()), 4, 4));
            
            dbExecute("
                INSERT INTO gift_vouchers 
                (voucher_code, purchased_by, recipient_email, recipient_name, amount_mwk, balance_mwk, message, valid_from, valid_to, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active', NOW())
            ", [$code, $userId, $recipientEmail, $recipientName, $amount, $amount, $message]);

            if ($userId) {
                logAction('Gift Voucher Purchased', "User purchased gift voucher code $code worth MK " . number_format($amount));
            }

            $success = "Voucher purchased successfully! Code: <strong>$code</strong> has been generated.";
        }
    }
}

// ─── Handle Balance Check ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_balance'])) {
    $code = strtoupper(trim($_POST['voucher_code'] ?? ''));
    if (empty($code)) {
        $error = 'Please enter a voucher code.';
    } else {
        $voucherDetails = dbQueryOne("SELECT * FROM gift_vouchers WHERE voucher_code = ?", [$code]);
        if (!$voucherDetails) {
            $error = 'Invalid or non-existent voucher code.';
        }
    }
}

// Fetch user's purchased vouchers if logged in
$purchasedVouchers = $userId ? dbQuery("SELECT * FROM gift_vouchers WHERE purchased_by = ? ORDER BY created_at DESC", [$userId]) : [];
$pageTitle = 'Gift Vouchers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gift Vouchers | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    .voucher-container { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 3rem auto; max-width: 960px; }
    @media(max-width: 768px) { .voucher-container { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<main class="container">
  <div style="text-align: center; margin-top: 2rem;">
    <h1 style="font-size:2.2rem; font-weight:800; margin-bottom:0.5rem;">🎁 Gift Vouchers</h1>
    <p class="text-muted">Purchase travel gift cards for your family or check your voucher balance.</p>
  </div>

  <?php if ($success): ?><div class="alert alert-success" style="max-width:960px; margin: 1.5rem auto 0;">🎉 <?= $success ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error" style="max-width:960px; margin: 1.5rem auto 0;">✕ <?= e($error) ?></div><?php endif; ?>

  <div class="voucher-container">
    <!-- Purchase Form -->
    <div class="glass-panel" style="padding:2rem;">
      <h2 style="font-size:1.35rem; font-weight:800; margin-bottom:1.5rem;">➕ Purchase Gift Voucher</h2>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="buy_voucher" value="1">

        <div class="form-group">
          <label class="form-label" for="recipient_name">Recipient Name</label>
          <input type="text" id="recipient_name" name="recipient_name" class="form-control" placeholder="Friend's Name" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="recipient_email">Recipient Email</label>
          <input type="email" id="recipient_email" name="recipient_email" class="form-control" placeholder="friend@example.com" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="amount">Voucher Value (MK)</label>
          <select name="amount" id="amount" class="form-control">
            <option value="5000">MK 5,000</option>
            <option value="10000">MK 10,000</option>
            <option value="25000" selected>MK 25,000</option>
            <option value="50000">MK 50,000</option>
            <option value="100000">MK 100,000</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="message">Gift Message (Optional)</label>
          <textarea id="message" name="message" class="form-control" rows="3" placeholder="Happy Travels!"></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; margin-top: 1rem;">Purchase Voucher</button>
      </form>
    </div>

    <!-- Balance Check & List -->
    <div>
      <div class="glass-panel" style="padding:2rem; margin-bottom:1.5rem;">
        <h2 style="font-size:1.35rem; font-weight:800; margin-bottom:1.5rem;">🔍 Check Voucher Balance</h2>
        <form method="POST" action="">
          <input type="hidden" name="check_balance" value="1">
          <div class="form-group">
            <label class="form-label" for="voucher_code">Voucher Code</label>
            <div style="display:flex; gap:0.5rem;">
              <input type="text" id="voucher_code" name="voucher_code" class="form-control" placeholder="GV-XXXX-XXXX" style="font-family:monospace; text-transform:uppercase;" required>
              <button type="submit" class="btn btn-secondary">Check</button>
            </div>
          </div>
        </form>

        <?php if ($voucherDetails): ?>
          <div style="background:var(--clr-surface2); border: 1px solid var(--clr-border); border-radius: var(--radius-sm); padding: 1.25rem; margin-top: 1.5rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
              <span class="text-muted">Code</span>
              <strong class="font-mono"><?= e($voucherDetails['voucher_code']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
              <span class="text-muted">Original Value</span>
              <strong><?= formatMWK((float)$voucherDetails['amount_mwk']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
              <span class="text-muted">Remaining Balance</span>
              <strong style="color:#10b981; font-size:1.15rem;"><?= formatMWK((float)$voucherDetails['balance_mwk']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
              <span class="text-muted">Status</span>
              <span class="badge badge-<?= $voucherDetails['status'] === 'active' ? 'approved' : 'rejected' ?>"><?= e(ucfirst($voucherDetails['status'])) ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
              <span class="text-muted">Valid To</span>
              <strong><?= e(date('M j, Y', strtotime($voucherDetails['valid_to']))) ?></strong>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- User's Purchased Vouchers -->
      <?php if ($userId && !empty($purchasedVouchers)): ?>
        <div class="glass-panel" style="padding:2rem;">
          <h2 style="font-size:1.35rem; font-weight:800; margin-bottom:1.5rem;">📋 Your Gift Cards</h2>
          <div style="display:grid; gap:0.75rem;">
            <?php foreach ($purchasedVouchers as $gv): ?>
              <div class="card" style="padding:1rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                  <div class="font-mono" style="font-weight:700; color:var(--clr-cyan);"><?= e($gv['voucher_code']) ?></div>
                  <div class="text-xs text-muted">To: <?= e($gv['recipient_email']) ?></div>
                </div>
                <div style="text-align:right;">
                  <strong style="color:#10b981;"><?= formatMWK((float)$gv['balance_mwk']) ?></strong>
                  <div class="text-xs text-muted"><?= ucfirst($gv['status']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
