<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . 'login.php');
}

$bookingId = trim($_GET['id'] ?? '');
if ($bookingId === '') {
    die('Invalid Request.');
}

// Fetch booking
$bk = dbQueryOne("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
if (!$bk) {
    die('Ticket not found.');
}

// Verify owner or admin
if ($_SESSION['user_id'] !== $bk['customer_id'] && !hasRole(ADMIN_ROLES)) {
    die('Unauthorized access.');
}

// Fetch listing
$listing = dbQueryOne("SELECT * FROM listings WHERE id = ?", [$bk['listing_id']]);
$listingMeta = $listing ? json_decode($listing['meta'], true) : [];

$details = json_decode($bk['details'], true) ?? [];
$ticketBackground = $listing['image'] ?? 'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=1400&fit=crop&q=80';
$ticketCity = trim($listing['location'] ?? 'Malawi');
$ticketCode = trim((string)($bk['ticket_code'] ?? $bk['qr_code'] ?? $bk['id']));
$ticketFormat = strtolower(trim((string)($details['ticket_format'] ?? $listingMeta['ticketCodeFormat'] ?? $listingMeta['ticket_code_format'] ?? $listingMeta['scanFormat'] ?? $listingMeta['scan_format'] ?? 'qr')));
if (!in_array($ticketFormat, ['qr', 'barcode', 'code'], true)) {
    $ticketFormat = 'qr';
}
$ticketModeLabel = match ($ticketFormat) {
    'barcode' => 'Barcode Ticket',
    'code' => 'Code Ticket',
    default => 'QR Ticket',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticket - <?= e($bk['listing_title']) ?> | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --clr-bg: #f3f4f6;
      --clr-surface: #ffffff;
      --clr-text: #1f2937;
      --clr-text-muted: #6b7280;
      --clr-border: #e5e7eb;
      --clr-accent: #e63946;
    }
    body {
      font-family: 'Inter', sans-serif;
      background:
        linear-gradient(180deg, rgba(15,23,42,0.78), rgba(15,23,42,0.5)),
        url('<?= e($ticketBackground) ?>') center/cover fixed;
      color: var(--clr-text);
      margin: 0;
      padding: 2rem 1rem;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }
    .ticket-container {
      background: rgba(255,255,255,0.95);
      max-width: 500px;
      width: 100%;
      border-radius: 16px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.05);
      border: 1px solid var(--clr-border);
      overflow: hidden;
      backdrop-filter: blur(10px);
    }
    .ticket-actions {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.75rem;
      margin: 1rem 0 1.25rem;
    }
    .ticket-action-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      padding: 0.8rem 1rem;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 700;
      font-size: 0.86rem;
      border: 1px solid var(--clr-border);
      background: #fff;
      color: var(--clr-text);
      cursor: pointer;
    }
    .ticket-action-btn.primary {
      background: var(--clr-accent);
      color: #fff;
      border-color: transparent;
    }
    .ticket-action-btn.soft {
      background: rgba(230,57,70,0.08);
      color: var(--clr-accent);
      border-color: rgba(230,57,70,0.15);
    }
    .ticket-note {
      margin: 0 0 1.25rem;
      padding: 0.85rem 1rem;
      border-radius: 12px;
      background: rgba(15,23,42,0.04);
      border: 1px solid rgba(15,23,42,0.08);
      color: var(--clr-text-muted);
      font-size: 0.88rem;
    }
    .ticket-display {
      display: grid;
      place-items: center;
      padding: 1rem 0 0.5rem;
    }
    .ticket-code-card {
      width: 100%;
      max-width: 320px;
      padding: 1rem;
      border-radius: 14px;
      background: linear-gradient(135deg, rgba(15,23,42,0.03), rgba(230,57,70,0.05));
      border: 1px solid rgba(15,23,42,0.08);
      text-align: center;
    }
    .ticket-code-text {
      font-family: monospace;
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: 0.18em;
      word-break: break-all;
    }
    .ticket-header {
      background:
        linear-gradient(135deg, rgba(230,57,70,0.92), rgba(15,23,42,0.88)),
        url('<?= e($ticketBackground) ?>') center/cover;
      color: #fff;
      padding: 1.5rem;
      text-align: center;
    }
    .ticket-hero-note {
      margin-top: 0.5rem;
      font-size: 0.82rem;
      color: rgba(255,255,255,0.88);
    }
    .ticket-body {
      padding: 2rem;
    }
    .ticket-title {
      font-size: 1.25rem;
      font-weight: 800;
      margin: 0 0 0.5rem 0;
    }
    .ticket-meta {
      font-size: 0.85rem;
      color: var(--clr-text-muted);
      margin-bottom: 1.5rem;
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.25rem;
      margin-bottom: 1.5rem;
    }
    .info-label {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      color: var(--clr-text-muted);
      margin-bottom: 0.25rem;
    }
    .info-value {
      font-size: 0.95rem;
      font-weight: 700;
    }
    .qr-section {
      text-align: center;
      border-top: 2px dashed var(--clr-border);
      padding-top: 1.5rem;
      margin-top: 1.5rem;
    }
    .qr-code-img {
      width: 180px;
      height: 180px;
      border: 1px solid var(--clr-border);
      padding: 0.5rem;
      background: #fff;
      border-radius: 8px;
    }
    .barcode-svg {
      width: 100%;
      max-width: 320px;
      height: 120px;
      background: #fff;
      border: 1px solid var(--clr-border);
      border-radius: 8px;
      padding: 0.5rem;
    }
    .ticket-code {
      font-family: monospace;
      font-size: 1rem;
      font-weight: bold;
      color: var(--clr-text);
      margin-top: 0.75rem;
    }
    .status-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 100px;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
    }
    .status-active { background: #d1fae5; color: #065f46; }
    .status-used { background: #fee2e2; color: #991b1b; }
    .btn-print {
      display: block;
      width: 100%;
      text-align: center;
      padding: 0.75rem;
      background: var(--clr-text);
      color: #fff;
      text-decoration: none;
      font-weight: 600;
      border-radius: 8px;
      margin-top: 1.5rem;
      cursor: pointer;
      border: none;
    }
    @media print {
      body { background: #fff; padding: 0; }
      .ticket-container { box-shadow: none; border: none; }
      .ticket-actions { display: none; }
      .btn-print { display: none; }
    }
  </style>
</head>
<body>

<div class="ticket-container">
  <div class="ticket-header">
    <div style="font-size: 0.85rem; font-weight: bold; letter-spacing: 0.1em; text-transform: uppercase;">OFFICIAL ENTRY TICKET</div>
    <div class="ticket-code" style="color: #fff; margin-top: 0.25rem; font-size: 1.2rem;"><?= e($bk['ticket_code'] ?: 'TKT-' . $bk['id']) ?></div>
    <div class="ticket-hero-note"><?= e($ticketCity) ?></div>
  </div>

  <div class="ticket-body">
    <h1 class="ticket-title"><?= e($bk['listing_title']) ?></h1>
    <div class="ticket-meta">📍 <?= e($listing['location'] ?? 'Venue Details') ?></div>
    <div class="ticket-actions">
      <button onclick="window.print()" class="ticket-action-btn primary" type="button">🖨️ Print / PDF</button>
      <button id="share-ticket-btn" class="ticket-action-btn" type="button">📤 Share</button>
      <button id="copy-ticket-btn" class="ticket-action-btn soft" type="button">📋 Copy Code</button>
    </div>

    <div class="ticket-note">
      Keep this ticket ready in your bookings. You can print it, share it, or leave it here for scanning at the venue.
      <strong style="display:block;color:var(--clr-text);margin-top:0.35rem;"><?= e($ticketModeLabel) ?></strong>
    </div>

    <div class="info-grid">
      <div>
        <div class="info-label">Customer Name</div>
        <div class="info-value"><?= e($bk['customer_name']) ?></div>
      </div>
      <div>
        <div class="info-label">Customer Email</div>
        <div class="info-value" style="font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($bk['customer_email']) ?></div>
      </div>
      <div>
        <div class="info-label">Date</div>
        <div class="info-value"><?= e($listingMeta['date'] ?? 'TBC') ?></div>
      </div>
      <div>
        <div class="info-label">Time</div>
        <div class="info-value"><?= e($listingMeta['time'] ?? 'TBC') ?></div>
      </div>
      <div>
        <div class="info-label">Ticket Type</div>
        <div class="info-value"><?= e($details['ticket_type'] ?? 'Standard') ?></div>
      </div>
      <div>
        <div class="info-label">Quantity</div>
        <div class="info-value"><?= (int)$bk['quantity'] ?> Ticket<?= $bk['quantity'] > 1 ? 's' : '' ?></div>
      </div>
      <div>
        <div class="info-label">Used count</div>
        <div class="info-value"><?= (int)$bk['tickets_used'] ?> / <?= (int)$bk['quantity'] ?> scanned</div>
      </div>
      <div>
        <div class="info-label">Ticket Status</div>
        <div>
          <?php
            $status = strtolower($bk['ticket_status'] ?: 'active');
            $class = 'status-active';
            if ($status === 'fully_used' || $status === 'cancelled') {
                $class = 'status-used';
            }
          ?>
          <span class="status-badge <?= $class ?>"><?= str_replace('_', ' ', $status) ?></span>
        </div>
      </div>
    </div>

    <div class="qr-section">
      <div class="ticket-display">
        <?php if ($ticketFormat === 'barcode'): ?>
          <svg id="ticket-barcode" class="barcode-svg" aria-label="Ticket barcode"></svg>
          <div style="font-size: 0.8rem; color: var(--clr-text-muted); margin-top: 0.5rem;">Present the barcode to the gate officer for scanning.</div>
        <?php elseif ($ticketFormat === 'code'): ?>
          <div class="ticket-code-card">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--clr-text-muted);font-weight:700;margin-bottom:0.5rem;">Scan / Verify Code</div>
            <div class="ticket-code-text"><?= e($ticketCode) ?></div>
          </div>
          <div style="font-size: 0.8rem; color: var(--clr-text-muted); margin-top: 0.5rem;">Show this code to the gate officer for manual verification or scanning.</div>
        <?php else: ?>
          <img src="https://chart.googleapis.com/chart?chs=220x220&cht=qr&chl=<?= urlencode($ticketCode) ?>&choe=UTF-8" alt="Ticket QR Code" class="qr-code-img">
          <div style="font-size: 0.8rem; color: var(--clr-text-muted); margin-top: 0.5rem;">Present this QR code to the gate officer for scanning.</div>
        <?php endif; ?>
      </div>
      <div style="font-size: 0.78rem; color: var(--clr-text-muted); margin-top: 0.75rem;">Ticket ID: <span style="font-family:monospace;"><?= e($ticketCode) ?></span></div>
    </div>

    <button onclick="window.print()" class="btn-print">Print Ticket</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
const ticketCode = <?= json_encode($ticketCode) ?>;
const ticketFormat = <?= json_encode($ticketFormat) ?>;

const shareBtn = document.getElementById('share-ticket-btn');
const copyBtn = document.getElementById('copy-ticket-btn');

if (ticketFormat === 'barcode') {
  try {
    JsBarcode('#ticket-barcode', ticketCode, {
      format: 'CODE128',
      lineColor: '#111827',
      background: '#ffffff',
      width: 2,
      height: 60,
      margin: 10,
      displayValue: true,
      fontSize: 18
    });
  } catch (err) {
    console.error('Barcode render failed', err);
  }
}

if (shareBtn) {
  shareBtn.addEventListener('click', async () => {
    const shareData = {
      title: <?= json_encode($bk['listing_title']) ?>,
      text: 'Your Uthenga ticket is ready for <?= json_encode($ticketCity) ?>. Code: ' + ticketCode,
      url: window.location.href
    };
    try {
      if (navigator.share) {
        await navigator.share(shareData);
      } else {
        await navigator.clipboard.writeText(window.location.href);
        alert('Ticket link copied to clipboard.');
      }
    } catch (err) {
      console.log(err);
    }
  });
}

if (copyBtn) {
  copyBtn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(ticketCode);
      alert('Ticket code copied.');
    } catch (err) {
      alert('Could not copy the code. Please copy it manually.');
    }
  });
}
</script>

</body>
</html>
