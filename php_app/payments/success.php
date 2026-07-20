<?php
/**
 * Uthenga - Payment Success Page
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$txnRef   = trim($_GET['txn'] ?? '');
$gateway  = trim($_GET['gateway'] ?? '');
$pageTitle = 'Payment Successful';

$txn = $txnRef ? dbQueryOne(
    "SELECT t.*, b.booking_code, b.reference_name, b.booking_status, b.payment_status
     FROM transactions t
     LEFT JOIN bookings b ON b.id = t.booking_id
     WHERE (t.transaction_reference = ? OR t.id = ?) AND t.user_id = ?",
    [$txnRef, $txnRef, $_SESSION['user_id']]
) : null;

$gatewayLabels = [
    'paychangu' => 'PayChangu',
    'airtel'    => 'Airtel Money',
    'tnm'       => 'TNM Mpamba',
    'card'      => 'Card',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    .success-wrap { max-width: 560px; margin: 4rem auto; text-align: center; padding: 2rem; }
    .success-icon { font-size: 4rem; animation: bounce .6s ease; margin-bottom: 1rem; }
    @keyframes bounce { 0%,100%{transform:scale(1)} 50%{transform:scale(1.25)} }
    .success-card { background: var(--clr-surface); border: 1px solid rgba(16,185,129,.35); border-radius: var(--radius-lg); padding: 2.5rem; }
    .txn-row { display: flex; justify-content: space-between; font-size: .85rem; padding: .5rem 0; border-bottom: 1px solid var(--clr-border); }
    .txn-row:last-child { border-bottom: none; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="container" style="padding-top:2rem; padding-bottom:6rem;">
  <div class="success-wrap">
    <div class="success-card">
      <div class="success-icon">🎉</div>
      <h1 style="font-size:1.8rem;font-weight:800;color:#10b981;margin-bottom:.5rem;">Payment Successful!</h1>
      <p class="text-muted" style="margin-bottom:1.75rem;">Your booking has been confirmed. A confirmation has been sent to your email.</p>

      <?php if ($txn): ?>
      <div style="background:var(--clr-surface2);border-radius:var(--radius-md);padding:1.25rem;text-align:left;margin-bottom:1.75rem;">
        <div class="txn-row">
          <span class="text-muted">Transaction Ref</span>
          <span style="font-family:monospace;font-size:.78rem;"><?= e($txn['transaction_reference'] ?? $txn['id']) ?></span>
        </div>
        <div class="txn-row">
          <span class="text-muted">Amount Paid</span>
          <strong style="color:#10b981;">MK <?= number_format((float)$txn['amount']) ?></strong>
        </div>
        <div class="txn-row">
          <span class="text-muted">Payment Method</span>
          <span><?= e($gatewayLabels[$gateway] ?? ($txn['gateway_name'] ?? ucfirst($gateway))) ?></span>
        </div>
        <div class="txn-row">
          <span class="text-muted">Status</span>
          <span style="color:#10b981;font-weight:700;">✅ <?= ucfirst(e($txn['status'])) ?></span>
        </div>
        <?php if (!empty($txn['booking_code']) || !empty($txn['booking_id'])): ?>
        <div class="txn-row">
          <span class="text-muted">Booking Ref</span>
          <span style="font-family:monospace;font-size:.78rem;"><?= e($txn['booking_code'] ?: $txn['booking_id']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($txn['reference_name'])): ?>
        <div class="txn-row">
          <span class="text-muted">Booking Title</span>
          <span><?= e($txn['reference_name']) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center;">
        <a href="<?= BASE_URL ?>bookings.php" class="btn btn-cyan">View My Bookings</a>
        <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
