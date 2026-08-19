<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/tie/TicketTemplates.php';

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

// Verify owner or admin. Cast IDs so valid users are not blocked by string/int mismatches.
if ((int) ($_SESSION['user_id'] ?? 0) !== (int) ($bk['customer_id'] ?? 0) && !hasRole(ADMIN_ROLES)) {
    die('Unauthorized access.');
}

// Fetch listing
$listing = dbQueryOne("SELECT * FROM listings WHERE id = ?", [$bk['listing_id']]);
$listingMeta = $listing ? json_decode($listing['meta'], true) : [];

$details = json_decode($bk['details'], true) ?? [];
$ticketBackground = $listing['image'] ?? 'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=1400&fit=crop&q=80';
$ticketCity = trim($listing['location'] ?? 'Malawi');
$ticketCode = trim((string)($bk['qr_code'] ?? $bk['booking_code'] ?? $bk['id']));
$ticketFormat = strtolower(trim((string)($details['ticket_format'] ?? $listingMeta['ticketCodeFormat'] ?? $listingMeta['ticket_code_format'] ?? $listingMeta['scanFormat'] ?? $listingMeta['scan_format'] ?? 'qr')));
if (!in_array($ticketFormat, ['qr', 'barcode', 'code'], true)) {
    $ticketFormat = 'qr';
}
$ticketModeLabel = match ($ticketFormat) {
    'barcode' => 'Barcode Ticket',
    'code' => 'Code Ticket',
    default => 'QR Ticket',
};

function ticketStatusLabel(string $status): string {
    return match (strtolower(trim($status))) {
        'active' => 'Active',
        'pending' => 'Pending',
        'partially_used' => 'Partially Used',
        'fully_used' => 'Fully Used',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        default => ucwords(str_replace(['_', '-'], ' ', strtolower(trim($status)))),
    };
}
$downloadMode = (string) ($_GET['download'] ?? '');

if ($downloadMode === 'pdf') {
    $safeTitle = preg_replace('/[^A-Z0-9\-]+/i', '-', (string) ($bk['listing_title'] ?? 'ticket')) ?: 'ticket';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="uthenga-ticket-' . $safeTitle . '.pdf"');
    $ticketLines = [
        'UTHENGA ENTRY TICKET',
        'Event: ' . (string) ($bk['listing_title'] ?? ''),
        'Ticket Code: ' . $ticketCode,
        'Customer: ' . (string) ($bk['customer_name'] ?? ''),
        'Email: ' . (string) ($bk['customer_email'] ?? ''),
        'Location: ' . (string) ($listing['location'] ?? 'Venue Details'),
        'Date: ' . (string) ($listingMeta['date'] ?? 'TBC'),
        'Time: ' . (string) ($listingMeta['time'] ?? 'TBC'),
        'Ticket Type: ' . (string) ($details['ticket_type'] ?? 'Standard'),
        'Quantity: ' . (string) ((int) $bk['quantity'] . ' Ticket' . ((int) $bk['quantity'] > 1 ? 's' : '')),
        'Used: ' . (string) ((int) $bk['tickets_used'] . ' / ' . (int) $bk['quantity']),
        'Status: ' . (string) ($bk['ticket_status'] ?? 'active'),
        'Gate Instruction: Present this ticket at the venue for scanning.',
    ];

    $pdfText = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', implode("\n", $ticketLines)) : implode("\n", $ticketLines);
    $pdfText = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', (string) $pdfText) ?: '';
    $pdfText = str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $pdfText);
    $contentLines = [];
    $y = 780;
    foreach (explode("\n", $pdfText) as $line) {
        $contentLines[] = 'BT /F1 12 Tf 48 ' . $y . ' Td (' . $line . ') Tj ET';
        $y -= 20;
        if ($y < 60) {
            break;
        }
    }

    $objects = [];
    $catalogId = 1;
    $pagesId = 2;
    $fontId = 3;
    $pageId = 4;
    $contentId = 5;
    $contentStream = implode("\n", $contentLines);
    $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[$contentId] = "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream";
    $objects[$pageId] = '<< /Type /Page /Parent ' . $pagesId . ' 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 ' . $fontId . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
    $objects[$pagesId] = '<< /Type /Pages /Kids [' . $pageId . ' 0 R] /Count 1 >>';
    $objects[$catalogId] = '<< /Type /Catalog /Pages ' . $pagesId . ' 0 R >>';
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    $pdf .= sprintf("%010d %05d f \n", 0, 65535);
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d %05d n \n", $offsets[$i] ?? 0, 0);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";
    echo $pdf;
    exit;
}

// ── Per-ticket entries (each ticket gets its own unique QR code) ──────
$eventTickets = [];
if (uthenga_table_exists('event_tickets')) {
    $eventTickets = dbQuery("SELECT id, qr_token, verification_signature, holder_name, status, checked_in_at FROM event_tickets WHERE booking_id = ? ORDER BY id ASC", [$bookingId]);
}
$quantity = max(1, (int) ($bk['quantity'] ?? 1));
$tickets = [];
if (!empty($eventTickets)) {
    foreach ($eventTickets as $et) {
        $tickets[] = [
            'id' => (string) $et['id'],
            'qr_payload' => $et['qr_token']
                ? 'UTHENGA|' . $et['id'] . '|' . $et['qr_token'] . '|' . ($et['verification_signature'] ?? '')
                : $et['id'],
            'holder' => trim((string) ($et['holder_name'] ?? '')) ?: (string) ($bk['customer_name'] ?? ''),
            'status' => $et['status'] ?? '',
        ];
    }
} else {
    for ($i = 1; $i <= $quantity; $i++) {
        $code = $quantity > 1 ? $ticketCode . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) : $ticketCode;
        $tickets[] = [
            'id' => $code,
            'qr_payload' => $code,
            'holder' => (string) ($bk['customer_name'] ?? ''),
            'status' => '',
        ];
    }
}

$template = strtolower((string) ($details['ticket_template'] ?? $listingMeta['ticketTemplate'] ?? 'general'));
if (!in_array($template, ['vip', 'vvip', 'early_bird', 'general', 'group', 'season'], true)) {
    $ticketTypeLower = strtolower((string) ($details['ticket_type'] ?? ''));
    $template = $ticketTypeLower === 'vip' || $ticketTypeLower === 'vvip' ? $ticketTypeLower : 'general';
}

$ticketType = (string) ($details['ticket_type'] ?? 'Standard');
$ticketNameShort = strtoupper(preg_replace('/[^A-Z0-9 ]/', '', $ticketType)) ?: 'TICKET';
$perks = [];
$perkIcons = [];
switch ($template) {
    case 'vip': $perks = ['VIP LOUNGE', 'FRONT ROW SEATING', 'NETWORKING ACCESS', 'WELCOME DRINK']; $ticketNameShort = 'VIP PASS'; break;
    case 'vvip': $perks = ['BACKSTAGE ACCESS', 'VIP PARKING', 'GOURMET DINNER', 'MEET & GREET']; $perkIcons = ['mic', 'car', 'utensils', 'group']; $ticketNameShort = 'VVIP PASS'; break;
    case 'early_bird': $perks = ['EARLY BIRD PRICE', 'ENTRY INCLUDED']; $ticketNameShort = 'EARLY BIRD'; break;
    case 'group': $perks = ['GROUP ADMISSION', 'GROUP ACCESS AREA']; $ticketNameShort = 'GROUP PASS'; break;
    case 'season': $perks = []; $ticketNameShort = 'SEASON PASS'; break;
    default: $perks = ['ENTRY INCLUDED', 'ALL DAY ACCESS'];
}

$statusRaw = strtolower(trim((string) ($bk['ticket_status'] ?? 'active')));
$statusCls = in_array($statusRaw, ['fully_used', 'cancelled', 'refunded'], true) ? 'used' : 'active';

$renderArgs = [
    'template' => $template,
    'event_title' => strtoupper((string) ($bk['listing_title'] ?? 'EVENT')),
    'tagline' => trim((string) ($listingMeta['tagline'] ?? ($template === 'early_bird' ? 'Feel the Beat. Live the Moment.' : ''))),
    'date' => strtoupper(trim((string) ($listingMeta['date'] ?? 'TBC'))),
    'time' => strtoupper(trim((string) ($listingMeta['time'] ?? 'TBC'))),
    'venue' => strtoupper(trim((string) ($listing['venue_name'] ?? $listing['location'] ?? 'VENUE'))),
    'city' => strtoupper(trim((string) ($listing['location'] ?? 'MALAWI'))),
    'ticket_name' => $ticketNameShort,
        'perks' => $perks,
        'perk_icons' => $perkIcons,
        'badge' => 'ADMIT ONE',
    'extra' => $template === 'group' ? '5' : null,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ticket - <?= e($bk['listing_title']) ?> | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --clr-bg: #f3f4f6;
      --clr-surface: #ffffff;
      --clr-text: #1f2937;
      --clr-text-muted: #6b7280;
      --clr-border: #e5e7eb;
      --clr-accent: #e63946;
    }
    * { box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, 'Segoe UI', sans-serif;
      background:
        linear-gradient(180deg, rgba(15,23,42,0.85), rgba(15,23,42,0.55)),
        url('<?= e($ticketBackground) ?>') center/cover fixed;
      color: var(--clr-text);
      margin: 0;
      padding: 2rem 1rem;
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
    }
    .ticket-page { max-width: 560px; margin: 0 auto; }
    .ticket-topbar {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 1.1rem; color: #fff; gap: .75rem; flex-wrap: wrap;
    }
    .ticket-topbar .tbl-logo { height: 30px; width: auto; opacity: .98; filter: drop-shadow(0 2px 8px rgba(0,0,0,.3)); }
    .ticket-topbar .tbl-title { font-size: .95rem; font-weight: 800; letter-spacing: .02em; text-shadow: 0 1px 6px rgba(0,0,0,.35); }
    .ticket-topbar .tbl-side { display: flex; align-items: center; gap: .6rem; }
    .tkt-status {
      font-size: .62rem; font-weight: 900; letter-spacing: .12em; text-transform: uppercase;
      padding: .32rem .7rem; border-radius: 999px;
    }
    .tkt-status.active { background: rgba(16,185,129,.92); color: #04301f; }
    .tkt-status.used { background: rgba(239,68,68,.92); color: #3f0707; }
    .ticket-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: .6rem; margin: 1.2rem 0; }
    .ticket-action-btn {
      display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
      padding: .72rem .6rem; border-radius: 12px; text-decoration: none; font-weight: 700;
      font-size: .78rem; border: 1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.1);
      color: #fff; cursor: pointer; backdrop-filter: blur(6px);
      transition: background .15s ease, transform .1s ease;
    }
    .ticket-action-btn:hover { background: rgba(255,255,255,.2); }
    .ticket-action-btn.primary { background: var(--clr-accent); border-color: transparent; }
    .ticket-action-btn.primary:hover { background: #d32f3c; }
    .ticket-action-btn.soft { background: rgba(255,255,255,.06); }
    .ticket-note {
      margin: 0 0 1.1rem; padding: .85rem 1rem; border-radius: 12px;
      background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14);
      color: rgba(255,255,255,.85); font-size: .84rem; backdrop-filter: blur(6px);
      display: flex; align-items: center; justify-content: space-between; gap: .6rem; flex-wrap: wrap;
    }
    .ticket-note strong { color: #fff; }
    .ticket-stack { display: grid; gap: 1.1rem; }
    .ticket-card { position: relative; }
    .ticket-card + .ticket-card::before {
      content: 'NEXT TICKET ↓'; position: absolute; top: -1.55rem; left: 50%; transform: translateX(-50%);
      font-size: .58rem; font-weight: 900; letter-spacing: .22em; color: rgba(255,255,255,.55);
    }
    .ticket-info {
      background: #fff; border-radius: 16px; padding: 1.1rem 1.25rem; margin-top: 1.1rem;
      box-shadow: 0 18px 40px rgba(15,23,42,.16);
    }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem 1.2rem; }
    .info-label { font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--clr-text-muted); margin-bottom: .22rem; }
    .info-value { font-size: .84rem; font-weight: 700; color: var(--clr-text); word-break: break-word; }
    .ticket-foot { text-align: center; margin-top: 1.4rem; color: rgba(255,255,255,.55); font-size: .7rem; font-weight: 600; letter-spacing: .04em; }
    .ticket-foot a { color: rgba(255,255,255,.85); }
    @media (max-width: 520px) {
      body { padding: 1.2rem .65rem; }
      .ticket-actions { grid-template-columns: 1fr 1fr; }
      .info-grid { grid-template-columns: 1fr; gap: .7rem; }
    }
    @media print {
      body { background: #fff !important; padding: 0; }
      .ticket-topbar, .ticket-actions, .ticket-note, .ticket-info, .ticket-foot { display: none !important; }
      .ticket-stack { gap: 0; }
      .ticket-card + .ticket-card { break-before: page; margin-top: 0 !important; }
      .ticket-card + .ticket-card::before { display: none; }
      .ticket-page { max-width: 100%; }
      .uth-tk { box-shadow: none; border-radius: 0; }
    }
  </style>
  <style><?= uthenga_ticket_render_css() ?></style>
  <style>
    .ticket-legacy { --tk-notch-bg: #0f172a; }
    @media print {
      .ticket-legacy { --tk-notch-bg: #fff; }
    }
  </style>
</head>
<body>
<div class="ticket-page">
  <div class="ticket-topbar">
    <img class="tbl-logo" src="<?= e(BASE_URL) ?>assets/images/logo-light.png" alt="<?= e(APP_NAME) ?>" onerror="this.style.display='none'">
    <span class="tbl-title"><?= e($bk['listing_title']) ?></span>
    <span class="tbl-side">
      <span class="tkt-status <?= $statusCls ?>"><?= e(ticketStatusLabel($statusRaw)) ?></span>
    </span>
  </div>

  <div class="ticket-note">
    <span>Keep this ticket ready in your bookings — print, share, or present for scanning at the venue.</span>
    <strong><?= e($ticketModeLabel) ?></strong>
  </div>

  <div class="ticket-actions">
    <button onclick="window.print()" class="ticket-action-btn primary" type="button">Print / PDF</button>
    <a href="?id=<?= urlencode((string) $bk['id']) ?>&download=pdf" class="ticket-action-btn">Download PDF</a>
    <button id="share-ticket-btn" class="ticket-action-btn soft" type="button">Share</button>
    <button id="copy-ticket-btn" class="ticket-action-btn soft" type="button">Copy Code</button>
  </div>

  <div class="ticket-stack">
    <?php foreach ($tickets as $i => $tk): ?>
      <?php
        $args = $renderArgs;
        $args['ticket_id'] = $tk['id'];
        $args['qr_payload'] = $tk['qr_payload'];
        $args['holder'] = $tk['holder'];
        $args['row'] = $quantity > 1 ? '' : (string) ($details['row'] ?? '');
        $args['seat'] = $quantity > 1 ? str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) : (string) ($details['seat'] ?? '');
      ?>
      <div class="ticket-card" data-index="<?= $i ?>"><?= uthenga_ticket_render($args) ?></div>
    <?php endforeach; ?>
  </div>

  <div class="ticket-info">
    <div class="info-grid">
      <div>
        <div class="info-label">Customer Name</div>
        <div class="info-value"><?= e($bk['customer_name']) ?></div>
      </div>
      <div>
        <div class="info-label">Customer Email</div>
        <div class="info-value" style="font-size:.78rem;"><?= e($bk['customer_email']) ?></div>
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
        <div class="info-value"><?= e($ticketType) ?></div>
      </div>
      <div>
        <div class="info-label">Quantity</div>
        <div class="info-value"><?= (int)$bk['quantity'] ?> Ticket<?= $bk['quantity'] > 1 ? 's' : '' ?></div>
      </div>
      <div>
        <div class="info-label">Used count</div>
        <div class="info-value"><?= (int)($bk['tickets_used'] ?? 0) ?> / <?= (int)$bk['quantity'] ?> scanned</div>
      </div>
      <div>
        <div class="info-label">Booking Ref</div>
        <div class="info-value" style="font-family:ui-monospace,Menlo,monospace;font-size:.78rem;"><?= e($bk['booking_code'] ?? $bk['id']) ?></div>
      </div>
    </div>
  </div>

  <div class="ticket-foot">
    <?= e(APP_NAME) ?> · <?= e($ticketCity) ?> — Powered by the Uthenga Marketplace
  </div>
</div>

<script src="<?= e(BASE_URL) ?>assets/js/qrcode-generator.js"></script>
<script>
(function () {
  const ticketCode = <?= json_encode($ticketCode) ?>;

  function renderQr(el) {
    const payload = String(el.getAttribute('data-qr') || '').trim();
    if (!payload) { el.querySelector('.uth-tk-qr-inner').textContent = 'NO CODE'; return; }
    try {
      const qr = qrcode(0, 'M');
      qr.addData(payload);
      qr.make();
      el.querySelector('.uth-tk-qr-inner').innerHTML = qr.createSvgTag(3, 0);
    } catch (err) {
      el.querySelector('.uth-tk-qr-inner').textContent = 'QR ERROR';
    }
  }
  document.querySelectorAll('.uth-tk-qr').forEach(renderQr);

  const shareBtn = document.getElementById('share-ticket-btn');
  const copyBtn = document.getElementById('copy-ticket-btn');

  if (shareBtn) {
    shareBtn.addEventListener('click', async () => {
      const shareData = {
        title: <?= json_encode($bk['listing_title']) ?>,
        text: 'Your Uthenga ticket is ready. Code: ' + ticketCode,
        url: window.location.href
      };
      try {
        if (navigator.share) { await navigator.share(shareData); }
        else { await navigator.clipboard.writeText(window.location.href); alert('Ticket link copied to clipboard.'); }
      } catch (err) { /* dismissed */ }
    });
  }

  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(ticketCode); alert('Ticket code copied.'); }
      catch (err) { alert('Could not copy the code. Please copy it manually.'); }
    });
  }
})();
</script>
</body>
</html>
