<?php
/**
 * Uthenga — Customer Bookings List Page (Standalone)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth_check.php';

requireCustomer();

$pageTitle = 'My Bookings';
$activeNav = 'bookings';

$userId = $_SESSION['user_id'];
$user   = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);

$bookings = dbQuery(
    'SELECT * FROM bookings WHERE customer_id = ? ORDER BY created_at DESC',
    [$userId]
);

function statusClass(string $status): string {
    $s = strtolower($status);
    if (in_array($s, ['confirmed', 'paid', 'success', 'resolved'], true)) return 'status-confirmed';
    if (in_array($s, ['pending', 'open', 'in progress'], true)) return 'status-pending';
    if (in_array($s, ['cancelled', 'failed'], true)) return 'status-cancelled';
    if ($s === 'refunded') return 'status-refunded';
    return 'status-pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container" style="padding-top:2.5rem;padding-bottom:4rem;">
  
  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h1 class="page-title"><?= uthenga_public_icon_svg('ticket') ?> My Bookings</h1>
      <p class="text-muted">Welcome, <strong style="color:var(--clr-text);"><?= e($user['name']) ?></strong>. Here is your reservation history.</p>
    </div>
    <div style="text-align:right;">
      <div class="text-xs text-muted">Wallet Balance</div>
      <div style="font-size:1.4rem;font-weight:800;color:var(--clr-accent);"><?= formatMWK((float)$user['balance']) ?></div>
    </div>
  </div>

  <!-- Bookings Section -->
  <?php if (empty($bookings)): ?>
    <div class="glass-panel animate-in" style="text-align:center;padding:4rem 2rem; margin-top: 1rem;">
      <div style="font-size:3.5rem;margin-bottom:1rem;"><?= uthenga_public_icon_svg('ticket') ?></div>
      <h3>No bookings found</h3>
      <p class="text-muted" style="margin:0.5rem 0 1.5rem;">You haven't made any bookings yet. Start exploring Malawi today!</p>
      <a href="<?= BASE_URL ?>index.php" class="btn btn-primary btn-lg" id="explore-btn">Explore Listings</a>
    </div>
  <?php else: ?>
    <div style="display:grid;gap:1.25rem; margin-top: 1rem;">
      <?php foreach ($bookings as $bk):
        $details = json_decode($bk['details'], true) ?? [];
        $ticketFormat = strtolower(trim((string)($details['ticket_format'] ?? 'qr')));
        if (!in_array($ticketFormat, ['qr', 'barcode', 'code'], true)) {
            $ticketFormat = 'qr';
        }
        $ticketCode = $bk['ticket_code'] ?: $bk['qr_code'] ?: $bk['id'];
      ?>
      <div class="card animate-in" style="border-radius:var(--radius-lg);overflow:visible;" id="bk-row-<?= e($bk['id']) ?>">
        <div style="display:grid;grid-template-columns:92px 1fr auto;gap:1.25rem;padding:1.25rem;align-items:center;">
          <!-- Thumbnail -->
          <img src="<?= e($bk['listing_image']) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:var(--radius-md);">
          <!-- Details -->
          <div>
            <div style="font-weight:700;color:var(--clr-text);margin-bottom:0.2rem;"><?= e($bk['listing_title']) ?></div>
            <div class="text-xs text-muted" style="margin-bottom:0.5rem;">
              Booked: <?= e($bk['booking_date'] ?? $bk['booked_at'] ?? $bk['created_at'] ?? '') ?>
              <?php if (!empty($details['check_in_date'])): ?>
                · Check-in: <?= e($details['check_in_date']) ?> to <?= e($details['check_out_date'] ?? '') ?>
              <?php elseif (!empty($details['tour_date'])): ?>
                · Tour Date: <?= e($details['tour_date']) ?>
              <?php elseif (!empty($details['travel_date'])): ?>
                · Travel Date: <?= e($details['travel_date']) ?>
              <?php endif; ?>
              <?php if (!empty($details['ticket_type'])): ?>
                · <?= e($details['ticket_type']) ?> × <?= (int)($details['quantity'] ?? 1) ?>
              <?php endif; ?>
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
              <span class="status-badge <?= statusClass($bk['booking_status']) ?>">
                <?= ucfirst(e($bk['booking_status'])) ?>
              </span>
              <span class="status-badge <?= statusClass($bk['payment_status']) ?>">
                <?= e($bk['payment_status']) ?>
              </span>
              <span class="status-badge" style="background:rgba(6,182,212,0.12);color:var(--clr-primary);">
                <?= e(strtoupper($ticketFormat)) ?>
              </span>
              <span class="text-xs text-muted">ID: <?= e($bk['id']) ?></span>
            </div>
          </div>
          <div style="min-width:92px;text-align:center;">
            <div class="text-xs text-muted" style="margin-bottom:0.35rem;">Ticket</div>
            <?php if ($ticketFormat === 'barcode'): ?>
              <svg class="booking-barcode" data-code="<?= e($ticketCode) ?>" style="width:92px;height:42px;background:#fff;border:1px solid var(--clr-border);border-radius:6px;padding:0.15rem;"></svg>
            <?php elseif ($ticketFormat === 'code'): ?>
              <div style="padding:0.35rem 0.4rem;border:1px solid var(--clr-border);border-radius:6px;background:rgba(15,23,42,0.03);font-family:monospace;font-size:0.62rem;letter-spacing:0.12em;word-break:break-all;line-height:1.2;"><?= e(substr($ticketCode, 0, 14)) ?><?= strlen($ticketCode) > 14 ? '…' : '' ?></div>
            <?php else: ?>
              <img src="https://chart.googleapis.com/chart?chs=90x90&cht=qr&chl=<?= urlencode($ticketCode) ?>&choe=UTF-8" alt="Ticket QR" style="width:92px;height:92px;border:1px solid var(--clr-border);border-radius:6px;padding:0.15rem;background:#fff;">
            <?php endif; ?>
          </div>
          <!-- Price + Actions -->
          <div style="text-align:right;min-width:140px;">
            <div style="font-size:1.2rem;font-weight:800;color:var(--clr-accent);margin-bottom:0.75rem;"><?= formatMWK((float)$bk['total_price']) ?></div>
            <?php if ($bk['booking_status'] === 'confirmed'): ?>
              <button
                class="btn btn-sm btn-secondary"
                onclick="toggleQR('qr-<?= e($bk['id']) ?>')"
                id="view-qr-<?= e($bk['id']) ?>"
                style="margin-bottom:0.4rem;width:100%;"
              ><?= uthenga_public_icon_svg('ticket') ?> Digital Ticket</button>
              <?php if ($bk['payment_status'] !== 'Refunded'): ?>
                <button
                  class="btn btn-sm btn-danger btn-cancel-booking"
                  data-booking-id="<?= e($bk['id']) ?>"
                  style="width:100%;"
                >Cancel & Refund</button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <!-- QR Code Panel (hidden by default) -->
          <?php if ($bk['qr_code']): ?>
        <div id="qr-<?= e($bk['id']) ?>" style="display:none;border-top:1px solid var(--clr-border);padding:1.25rem;">
          <div class="qr-block" style="text-align:center; padding: 1rem 0;">
            <div class="text-xs text-muted" style="margin-bottom:0.5rem;">Digital Ticket (<?= e(strtoupper($ticketFormat)) ?>)</div>
            <img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=<?= urlencode($bk['qr_code']) ?>&choe=UTF-8" alt="QR Code" style="margin: 0.5rem auto; display: block; border: 1px solid var(--clr-border); border-radius: 4px; padding: 0.5rem; background: #fff;">
            <div class="qr-string" style="font-family: monospace; font-size: 0.85rem; font-weight: bold; color: var(--clr-text-soft); margin-top: 0.5rem;"><?= e($bk['qr_code']) ?></div>
            <div class="text-xs text-muted" style="margin-top:0.5rem;">Keep this ticket open or return later. It will stay ready to be scanned.</div>
            <a href="ticket.php?id=<?= e($bk['id']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="margin-top: 1rem; display: inline-flex; align-items: center; gap: 0.25rem;"><?= uthenga_public_icon_svg('share') ?> Open Ticket / Print / Share</a>
          </div>
          <?php if ($bk['transaction_id']): ?>
            <p class="text-xs text-muted" style="text-align:center;margin-top:0.75rem;">
              Transaction ID: <span class="font-mono"><?= e($bk['transaction_id']) ?></span>
            </p>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
function toggleQR(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = el.style.display === 'none' ? '' : 'none';
}
</script>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script>
document.querySelectorAll('.booking-barcode').forEach(function(svg) {
  if (!window.JsBarcode) return;
  var code = svg.dataset.code || '';
  try {
    window.JsBarcode(svg, code, {
      format: 'CODE128',
      lineColor: '#111827',
      background: '#ffffff',
      width: 1.2,
      height: 28,
      margin: 2,
      displayValue: false
    });
  } catch (err) {
    console.log(err);
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
