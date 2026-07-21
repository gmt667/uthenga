<?php
/**
 * Uthenga - Payment Success Page
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$txnRef    = trim($_GET['txn'] ?? '');
$gateway   = trim($_GET['gateway'] ?? '');
$pageTitle = 'Payment Successful';

$gatewayLabels = [
    'paychangu' => 'PayChangu',
    'airtel'    => 'Airtel Money',
    'tnm'       => 'TNM Mpamba',
    'card'      => 'Card',
];

$txn = null;
$booking = null;

if ($txnRef !== '') {
    $txn = dbQueryOne(
        "SELECT t.*
         FROM transactions t
         WHERE (t.transaction_reference = ? OR t.id = ?)
           AND t.user_id = ?",
        [$txnRef, $txnRef, $_SESSION['user_id']]
    );

    if ($txn) {
        $lookupColumns = [];
        $lookupParams = [$_SESSION['user_id']];

        if (!empty($txn['booking_id'])) {
            $lookupColumns[] = 'id = ?';
            $lookupParams[] = $txn['booking_id'];
        }

        foreach (array_filter([
            $txn['transaction_reference'] ?? '',
            $txn['id'] ?? '',
        ]) as $candidate) {
            $lookupColumns[] = 'booking_code = ?';
            $lookupParams[] = $candidate;
            $lookupColumns[] = 'transaction_id = ?';
            $lookupParams[] = $candidate;
            if (uthenga_column_exists('bookings', 'qr_code')) {
                $lookupColumns[] = 'qr_code = ?';
                $lookupParams[] = $candidate;
            }
        }

        if ($lookupColumns) {
            $booking = dbQueryOne(
                "SELECT *
                 FROM bookings
                 WHERE customer_id = ?
                   AND (" . implode(' OR ', $lookupColumns) . ")
                 ORDER BY created_at DESC
                 LIMIT 1",
                $lookupParams
            );
        }

        if (!$booking && !empty($txn['booking_id'])) {
            $booking = dbQueryOne(
                "SELECT * FROM bookings WHERE customer_id = ? AND id = ? LIMIT 1",
                [$_SESSION['user_id'], $txn['booking_id']]
            );
        }
    }
}

$bookingRef = trim((string)($booking['booking_code'] ?? $booking['id'] ?? $txn['booking_id'] ?? $txnRef));
$bookingTitle = trim((string)($booking['reference_name'] ?? $booking['listing_title'] ?? ''));
if ($bookingTitle === '') {
    $bookingTitle = $bookingRef !== '' ? $bookingRef : 'Your booking';
}

$ticketCode = trim((string)(
    $booking['ticket_code']
    ?? $booking['qr_code']
    ?? $booking['booking_code']
    ?? $booking['transaction_id']
    ?? $booking['id']
    ?? $txnRef
));

if ($ticketCode === '') {
    $ticketCode = $bookingRef !== '' ? $bookingRef : $txnRef;
}

$ticketTargetId = trim((string)($booking['id'] ?? $txn['booking_id'] ?? ''));
$ticketUrl = $ticketTargetId !== '' ? BASE_URL . 'ticket.php?id=' . urlencode($ticketTargetId) : '';
$paymentMethod = $gatewayLabels[$gateway] ?? ($txn['gateway_name'] ?? ucfirst($gateway));
$paymentAmount = (float)($txn['amount'] ?? 0);
$paymentStatus = strtolower((string)($txn['status'] ?? 'success'));
$scanFormat = strtolower(trim((string)($booking['details'] ?? '')));
if ($booking && !empty($booking['details'])) {
    $details = json_decode((string)$booking['details'], true);
    if (is_array($details)) {
        $scanFormat = strtolower(trim((string)($details['ticket_format'] ?? $details['scan_format'] ?? $details['ticketCodeFormat'] ?? 'qr')));
    } else {
        $scanFormat = 'qr';
    }
} else {
    $scanFormat = 'qr';
}
if (!in_array($scanFormat, ['qr', 'barcode', 'code'], true)) {
    $scanFormat = 'qr';
}

$ticketImage = 'https://chart.googleapis.com/chart?chs=240x240&cht=qr&chl=' . urlencode($ticketCode) . '&choe=UTF-8';
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
    .payment-success-shell {
      max-width: 980px;
      margin: 0 auto;
      padding: 2rem 0 5rem;
    }
    .payment-success-grid {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
      gap: 1.5rem;
      align-items: start;
    }
    @media (max-width: 920px) {
      .payment-success-grid { grid-template-columns: 1fr; }
    }
    .success-panel,
    .ticket-panel {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }
    .success-hero {
      padding: 2rem;
      background: linear-gradient(135deg, rgba(16,185,129,.12), rgba(6,182,212,.06));
      border-bottom: 1px solid rgba(16,185,129,.12);
    }
    .success-mark {
      width: 56px;
      height: 56px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(16,185,129,.15);
      color: #059669;
      margin-bottom: 1rem;
    }
    .success-mark svg {
      width: 28px;
      height: 28px;
    }
    .success-title {
      margin: 0 0 .4rem;
      font-size: clamp(1.5rem, 2vw, 2rem);
      font-weight: 800;
      letter-spacing: -0.02em;
    }
    .success-copy {
      margin: 0;
      color: var(--clr-text-soft);
      line-height: 1.7;
    }
    .success-body,
    .ticket-body {
      padding: 1.5rem 2rem 2rem;
    }
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .9rem;
      margin-top: 1.25rem;
    }
    @media (max-width: 640px) {
      .summary-grid { grid-template-columns: 1fr; }
    }
    .summary-item {
      padding: .9rem 1rem;
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-md);
      background: var(--clr-surface2);
    }
    .summary-label {
      display: block;
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--clr-text-soft);
      margin-bottom: .25rem;
      font-weight: 700;
    }
    .summary-value {
      display: block;
      font-weight: 700;
      word-break: break-word;
    }
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .35rem .7rem;
      border-radius: 999px;
      font-size: .78rem;
      font-weight: 700;
      background: rgba(16,185,129,.12);
      color: #059669;
    }
    .status-pill svg {
      width: 14px;
      height: 14px;
    }
    .ticket-preview {
      padding: 1.4rem;
      background: linear-gradient(180deg, rgba(15,23,42,.02), rgba(6,182,212,.03));
    }
    .ticket-preview-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      margin-bottom: 1rem;
    }
    .ticket-preview-title {
      margin: 0;
      font-size: 1rem;
      font-weight: 800;
    }
    .ticket-preview-code {
      font-family: monospace;
      font-size: .92rem;
      letter-spacing: .12em;
      word-break: break-all;
      color: var(--clr-text);
      margin-top: .4rem;
    }
    .ticket-code-shell {
      border: 1px dashed var(--clr-border);
      border-radius: var(--radius-md);
      background: var(--clr-surface);
      padding: 1rem;
      text-align: center;
    }
    .ticket-code-shell img {
      width: 100%;
      max-width: 240px;
      height: auto;
      display: block;
      margin: 0 auto .9rem;
      border-radius: 12px;
      background: #fff;
      border: 1px solid var(--clr-border);
      padding: .35rem;
    }
    .ticket-code-shell .ticket-code-text {
      font-family: monospace;
      font-size: 1.1rem;
      font-weight: 800;
      letter-spacing: .18em;
      word-break: break-all;
    }
    .ticket-meta {
      margin-top: 1rem;
      display: grid;
      gap: .8rem;
    }
    .ticket-meta-item {
      display: flex;
      justify-content: space-between;
      gap: 1rem;
      font-size: .92rem;
      padding-bottom: .65rem;
      border-bottom: 1px solid var(--clr-border);
    }
    .ticket-meta-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .ticket-meta-label {
      color: var(--clr-text-soft);
      font-weight: 600;
    }
    .ticket-meta-value {
      text-align: right;
      font-weight: 700;
      word-break: break-word;
    }
    .action-row {
      display: flex;
      flex-wrap: wrap;
      gap: .75rem;
      margin-top: 1.5rem;
    }
    .action-row .btn {
      flex: 1 1 180px;
      justify-content: center;
    }
    .redirect-banner {
      display: flex;
      align-items: center;
      gap: .65rem;
      padding: .85rem 1rem;
      margin-bottom: 1rem;
      border-radius: var(--radius-md);
      background: rgba(6,182,212,.08);
      border: 1px solid rgba(6,182,212,.2);
      color: var(--clr-text);
      font-size: .9rem;
    }
    .redirect-banner strong {
      font-weight: 800;
    }
    .redirect-banner a {
      color: var(--clr-cyan, #06b6d4);
      font-weight: 700;
      text-decoration: none;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="container">
  <div class="payment-success-shell">
    <div class="payment-success-grid">
      <section class="success-panel">
        <div class="success-hero">
          <div class="success-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" role="img" aria-hidden="true">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"></circle>
              <path d="M7.5 12.5l3 3L16.8 9.2" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
          </div>
          <h1 class="success-title">Payment successful</h1>
          <p class="success-copy">
            Your booking has been confirmed and your scan-ready ticket is prepared below.
            <?= $ticketUrl ? 'You will be taken to the full ticket in a few seconds.' : 'If the ticket link is unavailable, check your bookings page.' ?>
          </p>
        </div>

        <div class="success-body">
          <?php if ($txn): ?>
            <div class="summary-grid">
              <div class="summary-item">
                <span class="summary-label">Transaction reference</span>
                <span class="summary-value" style="font-family:monospace;"><?= e($txn['transaction_reference'] ?? $txn['id']) ?></span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Amount paid</span>
                <span class="summary-value">MK <?= number_format($paymentAmount) ?></span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Payment method</span>
                <span class="summary-value"><?= e($paymentMethod) ?></span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Payment status</span>
                <span class="status-pill">
                  <svg viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M3.5 8.2l2.1 2.1 6.4-6.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                  </svg>
                  <?= e(ucfirst($paymentStatus)) ?>
                </span>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($ticketUrl): ?>
            <div class="redirect-banner">
              <span class="status-pill" style="margin:0;">Ticket ready</span>
              <span>Opening the full ticket in <strong id="redirect-count">5</strong> seconds.</span>
              <a href="<?= e($ticketUrl) ?>" id="ticket-open-now">Open now</a>
            </div>
          <?php endif; ?>

          <?php if ($booking): ?>
            <div class="action-row">
              <a href="<?= e($ticketUrl ?: BASE_URL . 'bookings.php') ?>" class="btn btn-cyan">Open full ticket</a>
              <a href="<?= BASE_URL ?>bookings.php" class="btn btn-secondary">View all bookings</a>
              <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-secondary">Go to dashboard</a>
            </div>
          <?php else: ?>
            <div class="action-row">
              <a href="<?= BASE_URL ?>bookings.php" class="btn btn-cyan">View my bookings</a>
              <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-secondary">Go to dashboard</a>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <aside class="ticket-panel">
        <div class="ticket-preview">
          <div class="ticket-preview-head">
            <div>
              <p class="ticket-preview-title">Scan-ready ticket</p>
              <div class="ticket-preview-code"><?= e($bookingRef ?: $ticketCode) ?></div>
            </div>
            <span class="status-pill" style="white-space:nowrap;">Ready to scan</span>
          </div>

          <div class="ticket-code-shell">
            <img src="<?= e($ticketImage) ?>" alt="Ticket QR code">
            <div class="ticket-code-text"><?= e($ticketCode) ?></div>
            <div style="margin-top:.5rem;color:var(--clr-text-soft);font-size:.84rem;">
              Present this code or open the full ticket page for scanning at the venue.
            </div>
          </div>

          <div class="ticket-meta">
            <div class="ticket-meta-item">
              <span class="ticket-meta-label">Booking</span>
              <span class="ticket-meta-value"><?= e($bookingRef) ?></span>
            </div>
            <div class="ticket-meta-item">
              <span class="ticket-meta-label">Title</span>
              <span class="ticket-meta-value"><?= e($bookingTitle) ?></span>
            </div>
            <div class="ticket-meta-item">
              <span class="ticket-meta-label">Scan format</span>
              <span class="ticket-meta-value"><?= e(strtoupper($scanFormat)) ?></span>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</main>

<script>
(function () {
  const ticketUrl = <?= json_encode($ticketUrl) ?>;
  const countdownEl = document.getElementById('redirect-count');
  const openNowEl = document.getElementById('ticket-open-now');

  if (!ticketUrl || !countdownEl || !openNowEl) {
    return;
  }

  let seconds = 5;
  const tick = () => {
    countdownEl.textContent = String(seconds);
    if (seconds <= 0) {
      window.location.href = ticketUrl;
      return;
    }
    seconds -= 1;
    window.setTimeout(tick, 1000);
  };

  openNowEl.addEventListener('click', function (event) {
    event.preventDefault();
    window.location.href = ticketUrl;
  });

  window.setTimeout(tick, 1000);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
