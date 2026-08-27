<?php
/**
 * Uthenga - Scheduled Bus Tickets Booking Experience
 * Real search, real seat-class purchase (PayChangu checkout), real
 * per-ticket QR codes — no fabricated data or fake payment confirmation.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/tie/bootstrap.php';

$pageTitle = 'Bus Tickets';
$activeNav = 'transport';
$view = ($_GET['view'] ?? '') === 'my-tickets' ? 'my-tickets' : 'search';

$fromLoc = trim($_GET['from'] ?? '');
$toLoc = trim($_GET['to'] ?? '');
$date = trim($_GET['date'] ?? '');
$passengers = max(1, (int) ($_GET['passengers'] ?? 1));

$kernel = new UthengaTieKernel();
$searchResult = ['departures' => []];
$myTickets = [];

if ($view === 'my-tickets') {
    if (isLoggedIn()) {
        try { $myTickets = $kernel->busOperations->myTickets((string) $_SESSION['user_id'])['tickets']; } catch (Throwable $e) { $myTickets = []; }
    }
} else {
    try { $searchResult = $kernel->busOperations->searchDepartures(['origin' => $fromLoc, 'destination' => $toLoc, 'date' => $date]); } catch (Throwable $e) { $searchResult = ['departures' => []]; }
}

function bt_icon(string $paths, int $size = 16): string {
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;">' . $paths . '</svg>';
}
const BT_ICON_ROUTE = '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8.5 17.5 15.5 6.5"/>';
const BT_ICON_CLOCK = '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>';
const BT_ICON_USERS = '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>';
const BT_ICON_TICKET = '<path d="M3 7a2 2 0 0 1 2-2h14v3a2 2 0 1 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 1 0 0-4z"/>';
const BT_ICON_CHECK = '<polyline points="20 6 9 17 4 12"/>';
const BT_ICON_SEAT = '<path d="M5 11V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v5"/><path d="M4 11h11a2 2 0 0 1 2 2v3H6a2 2 0 0 1-2-2z"/><path d="M17 13h2a2 2 0 0 1 2 2v3h-4"/>';

/** Renders one of the 5 controlled bus-ticket template designs. Company
 * branding is always the dominant header element; Uthenga's own mark is
 * always present but small; the QR code and ticket ID never vary in
 * prominence between styles — only the surrounding layout/color does. */
function bt_render_ticket_card(array $t): string {
    $style = in_array($t['template_style'] ?? '', ['classic_blue', 'modern_card', 'minimal_white', 'premium_dark', 'mobile_wallet'], true) ? $t['template_style'] : 'classic_blue';
    $uthengaLogo = BASE_URL . 'assets/images/' . ($style === 'minimal_white' ? 'logo-dark.png' : 'logo-light.png');
    $accentStyle = !empty($t['template_accent_color']) ? ' style="--btt-accent-override:' . e($t['template_accent_color']) . ';"' : '';
    $companyInitial = strtoupper(mb_substr((string) $t['operator'], 0, 1)) ?: 'U';
    $logoHtml = !empty($t['template_logo_url'])
        ? '<img class="btt-logo-img" src="' . e($t['template_logo_url']) . '" alt="">'
        : '<div class="btt-logo-fallback">' . e($companyInitial) . '</div>';
    $photoHtml = in_array($style, ['modern_card', 'premium_dark'], true) && !empty($t['image'])
        ? '<div class="btt-photo-strip" style="background-image:url(\'' . e($t['image']) . '\');"></div>' : '';
    $eyebrow = $style === 'modern_card' ? '<div class="btt-eyebrow">Your Journey</div>' : '';
    $fare = $t['fare'] !== null ? 'MWK ' . number_format((float) $t['fare']) : '—';
    $footerParts = [];
    if (!empty($t['template_footer_message'])) $footerParts[] = e($t['template_footer_message']);
    $contactParts = array_filter([$t['template_contact_phone'] ?? null, $t['template_contact_email'] ?? null]);
    if ($contactParts) $footerParts[] = e(implode(' · ', $contactParts));
    $footerText = $footerParts ? implode(' — ', $footerParts) : 'Safe travels.';

    ob_start(); ?>
    <div class="btt-card btt-card--<?= e($style) ?>"<?= $accentStyle ?>>
      <div class="btt-header">
        <div class="btt-brand">
          <?= $logoHtml ?>
          <div>
            <div class="btt-company-name"><?= e($t['operator']) ?></div>
            <?php if ($style !== 'classic_blue' && $style !== 'mobile_wallet'): ?><div class="btt-company-sub">Bus Ticket</div><?php endif; ?>
          </div>
        </div>
        <span class="btt-badge"><?= $style === 'premium_dark' ? 'Premium' : ($style === 'minimal_white' ? 'Standard' : 'Economy') ?></span>
      </div>
      <div class="btt-body">
        <?= $eyebrow ?>
        <?= $photoHtml ?>
        <div class="btt-route"><?= e($t['origin']) ?> <span class="btt-route-arrow"><?= bt_icon('<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>', 20) ?></span> <?= e($t['destination']) ?></div>
        <div class="btt-meta-grid">
          <div class="btt-meta-item"><?= bt_icon(BT_ICON_CLOCK) ?><div><div class="btt-meta-value"><?= e(date('d M Y', strtotime($t['departure_at']))) ?></div><div class="btt-meta-label">Date</div></div></div>
          <div class="btt-meta-item"><?= bt_icon(BT_ICON_CLOCK) ?><div><div class="btt-meta-value"><?= e(date('H:i', strtotime($t['departure_at']))) ?></div><div class="btt-meta-label">Departure</div></div></div>
          <div class="btt-meta-item"><?= bt_icon('<rect x="1" y="4" width="22" height="16" rx="2"/>') ?><div><div class="btt-meta-value"><?= e($t['vehicle_reg_number'] ?: 'Not yet assigned') ?></div><div class="btt-meta-label"><?= e($t['vehicle_make_model'] ?: 'Bus') ?></div></div></div>
          <div class="btt-meta-item"><?= bt_icon(BT_ICON_SEAT) ?><div><div class="btt-meta-value"><?= e($t['seat_label'] ?: '—') ?></div><div class="btt-meta-label">Seat</div></div></div>
          <div class="btt-meta-item"><?= bt_icon(BT_ICON_TICKET) ?><div><div class="btt-meta-value"><?= e($t['booking_id']) ?></div><div class="btt-meta-label">Booking ID</div></div></div>
          <div class="btt-meta-item"><?= bt_icon('<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>') ?><div><div class="btt-meta-value"><?= e($fare) ?></div><div class="btt-meta-label">Fare</div></div></div>
        </div>
        <div class="btt-passenger"><?= bt_icon(BT_ICON_USERS) ?> <?= e($t['passenger_name']) ?></div>
        <div class="btt-stub">
          <div class="btt-qr-box tc-qr" data-qr-payload="<?= e($t['qr_payload']) ?>"></div>
          <div class="btt-ticket-id-block">
            <div class="btt-ticket-id"><?= e($t['ticket_id']) ?></div>
            <div class="btt-scan-hint"><?= $t['status'] === 'issued' ? 'Scan to verify' : e(ucfirst($t['status'])) ?></div>
          </div>
        </div>
      </div>
      <div class="btt-footer">
        <span><?= $footerText ?></span>
        <span class="btt-powered-by"><img src="<?= e($uthengaLogo) ?>" alt=""> Powered by <?= e(APP_NAME) ?></span>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Book inter-city bus tickets with real seat availability across Malawi on Uthenga.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bus-ticket-templates.css?v=<?= rawurlencode(APP_VERSION) ?>">
  <script src="<?= BASE_URL ?>assets/js/qrcode-generator.js"></script>
  <style>
    .bus-search-bar { background: #090d16; border-bottom: 1px solid var(--clr-border); padding: 2.5rem 0; color: #fff; }
    .bus-form-grid { display: grid; grid-template-columns: 1.2fr 1.2fr 1fr 1fr auto; gap: 1rem; align-items: flex-end; background: rgba(30, 41, 59, 0.6); padding: 1.25rem; border-radius: 16px; border: 1px solid rgba(255, 255, 255, 0.1); }
    @media (max-width: 900px) { .bus-form-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 600px) { .bus-form-grid { grid-template-columns: 1fr; } }

    .bus-card-item { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.25rem; display: grid; grid-template-columns: 72px 1.5fr 2fr 1.2fr auto; gap: 1.5rem; align-items: center; transition: all 0.2s ease; }
    .bus-card-item:hover { border-color: var(--clr-accent); box-shadow: var(--shadow-md); }
    @media (max-width: 900px) { .bus-card-item { grid-template-columns: 1fr; } }
    .bus-card-thumb { width: 72px; height: 72px; border-radius: 12px; object-fit: cover; background: var(--clr-bg); }
    .bus-card-premium { grid-template-columns: 140px 1.5fr 2fr 1.2fr auto; }
    .bus-card-premium .bus-card-thumb { width: 140px; height: 100px; }
    @media (max-width: 900px) { .bus-card-premium .bus-card-thumb { width: 100%; height: 160px; } }
    .bus-highlight-chip { display: inline-block; background: rgba(230,57,70,0.1); color: var(--clr-accent); font-size: 0.68rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 999px; margin: 0.3rem 0.3rem 0 0; }
    .bus-card-description { font-size: 0.78rem; color: var(--clr-text-soft); margin-top: 0.4rem; }
    .bus-card-compact { grid-template-columns: 48px 1fr auto auto; padding: 0.85rem 1.1rem; gap: 1rem; }
    .bus-card-compact .bus-card-thumb { width: 48px; height: 48px; border-radius: 8px; }

    .seat-class-option { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border: 1px solid var(--clr-border); border-radius: 12px; padding: .8rem 1rem; cursor: pointer; margin-bottom: .6rem; }
    .seat-class-option.selected { border-color: var(--clr-accent); background: rgba(230,57,70,.06); }
    .seat-class-option input { accent-color: var(--clr-accent); }
    .seat-class-option .scn { font-weight: 800; }
    .seat-class-option .scp { color: var(--clr-text-soft); font-size: .82rem; }
    .seat-class-option .scf { font-weight: 900; color: var(--clr-accent); }
    .passenger-row { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; margin-bottom: .6rem; }

  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="bus-search-bar">
  <div class="container">
    <div style="margin-bottom: 1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
      <div>
        <span style="font-size:0.85rem;color:#60a5fa;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;">SCHEDULED INTER-CITY TRAVEL</span>
        <h1 style="font-size:2rem;font-weight:900;margin:0.2rem 0;">Book Bus Tickets</h1>
      </div>
      <a href="<?= BASE_URL ?>bus-tickets.php?view=my-tickets" class="btn btn-secondary"><?= bt_icon(BT_ICON_TICKET) ?> My Tickets</a>
    </div>

    <?php if ($view === 'search'): ?>
    <form method="GET" action="bus-tickets.php">
      <input type="hidden" name="view" value="search">
      <div class="bus-form-grid">
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="color:#94a3b8;font-size:0.75rem;">ORIGIN (FROM)</label>
          <input type="text" name="from" class="form-control" style="background:#0f172a;color:#fff;border-color:#334155;" value="<?= e($fromLoc) ?>" placeholder="e.g. Lilongwe">
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="color:#94a3b8;font-size:0.75rem;">DESTINATION (TO)</label>
          <input type="text" name="to" class="form-control" style="background:#0f172a;color:#fff;border-color:#334155;" value="<?= e($toLoc) ?>" placeholder="e.g. Blantyre">
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="color:#94a3b8;font-size:0.75rem;">TRAVEL DATE</label>
          <input type="date" name="date" class="form-control" style="background:#0f172a;color:#fff;border-color:#334155;" value="<?= e($date) ?>">
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label" style="color:#94a3b8;font-size:0.75rem;">PASSENGERS</label>
          <select name="passengers" class="form-control" style="background:#0f172a;color:#fff;border-color:#334155;">
            <?php for ($p = 1; $p <= 6; $p++): ?><option value="<?= $p ?>" <?= $p === $passengers ? 'selected' : '' ?>><?= $p ?></option><?php endfor; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:0.8rem 1.5rem;font-weight:800;">Search Buses</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</section>

<?php if ($view === 'my-tickets'): ?>
<div class="container" style="padding: 3rem 0 5rem;">
  <h2 style="font-size:1.4rem;font-weight:800;margin:0 0 1.5rem;">My Bus Tickets</h2>
  <?php if (!isLoggedIn()): ?>
    <p class="text-muted">Sign in to see your bus tickets.</p>
    <a class="btn btn-primary" href="<?= BASE_URL ?>login.php?redirect=<?= urlencode(BASE_URL . 'bus-tickets.php?view=my-tickets') ?>">Sign in</a>
  <?php elseif (!$myTickets): ?>
    <p class="text-muted">You don't have any bus tickets yet.</p>
    <a class="btn btn-primary" href="<?= BASE_URL ?>bus-tickets.php">Search for a route</a>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:1.5rem;">
    <?php foreach ($myTickets as $t): ?>
      <?= bt_render_ticket_card($t) ?>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php else: ?>

<div class="container" style="padding: 3rem 0 5rem;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
    <div>
      <h2 style="font-size:1.4rem;font-weight:800;margin:0;"><?= $fromLoc !== '' || $toLoc !== '' ? e(strtoupper($fromLoc)) . ' → ' . e(strtoupper($toLoc)) : 'All Scheduled Departures' ?></h2>
      <p class="text-muted" style="margin:0.2rem 0 0;font-size:0.85rem;"><?= $date !== '' ? e(date('D, d M Y', strtotime($date))) . ' • ' : '' ?><?= count($searchResult['departures']) ?> departure<?= count($searchResult['departures']) === 1 ? '' : 's' ?> found</p>
    </div>
  </div>

  <div>
    <?php if (!$searchResult['departures']): ?>
      <p class="text-muted">No scheduled departures match this search yet. Try a different route or date.</p>
    <?php endif; ?>
    <?php foreach ($searchResult['departures'] as $dep): ?>
      <?php
        $cheapest = null; foreach ($dep['seat_classes'] as $sc) if ($cheapest === null || $sc['price'] < $cheapest['price']) $cheapest = $sc;
        $seatsLeft = array_sum(array_column($dep['seat_classes'], 'remaining_seats'));
        $cardStyle = in_array($dep['card_style'] ?? 'standard', ['standard', 'premium', 'compact'], true) ? $dep['card_style'] : 'standard';
      ?>
      <div class="bus-card-item bus-card-<?= e($cardStyle) ?>">
        <?php if ($dep['image']): ?><img class="bus-card-thumb" src="<?= e($dep['image']) ?>" alt=""><?php else: ?><div class="bus-card-thumb"></div><?php endif; ?>

        <?php if ($cardStyle === 'compact'): ?>
          <div>
            <h3 style="font-size:1rem;font-weight:800;margin:0;"><?= e($dep['origin']) ?> → <?= e($dep['destination']) ?></h3>
            <div style="font-size:0.75rem;color:var(--clr-text-soft);"><?= e(date('D, d M · H:i', strtotime($dep['departure_at']))) ?></div>
          </div>
          <div style="font-size:1.1rem;font-weight:900;color:var(--clr-accent);">MWK <?= number_format((float) ($cheapest['price'] ?? 0)) ?></div>
          <button class="btn btn-primary btn-sm" onclick='openSeatSelectionModal(<?= json_encode($dep, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS) ?>)'><?= $seatsLeft > 0 ? 'Select Seat' : 'Sold out' ?></button>
        <?php else: ?>
          <div>
            <span style="font-size:0.75rem;font-weight:800;color:var(--clr-accent);text-transform:uppercase;"><?= e($dep['vehicle_type']) ?></span>
            <h3 style="font-size:1.25rem;font-weight:800;margin:0.2rem 0;"><?= e($dep['operator']) ?></h3>
            <div style="font-size:0.8rem;color:var(--clr-text-soft);"><?= bt_icon(BT_ICON_ROUTE) ?> <?= e($dep['title']) ?></div>
            <?php if ($cardStyle === 'premium' && !empty($dep['customer_description'])): ?><div class="bus-card-description"><?= e($dep['customer_description']) ?></div><?php endif; ?>
            <?php if ($cardStyle === 'premium' && !empty($dep['highlights'])): ?><div><?php foreach ($dep['highlights'] as $h): ?><span class="bus-highlight-chip"><?= e($h) ?></span><?php endforeach; ?></div><?php endif; ?>
          </div>
          <div>
            <div style="font-size:1.1rem;font-weight:900;"><?= bt_icon(BT_ICON_CLOCK) ?> <?= e(date('D, d M · H:i', strtotime($dep['departure_at']))) ?></div>
            <div style="font-size:0.78rem;color:var(--clr-text-soft);margin-top:.3rem;"><?= e($dep['origin']) ?> → <?= e($dep['destination']) ?><?= $dep['pickup_location'] ? ' · ' . e($dep['pickup_location']) : '' ?></div>
          </div>
          <div>
            <div style="font-size:1.5rem;font-weight:900;color:var(--clr-accent);">MWK <?= number_format((float) ($cheapest['price'] ?? 0)) ?></div>
            <div style="font-size:0.75rem;color:<?= $seatsLeft > 0 ? 'var(--clr-green)' : 'var(--clr-red)' ?>;font-weight:700;"><?= bt_icon(BT_ICON_SEAT) ?> <?= $seatsLeft > 0 ? $seatsLeft . ' seats available' : 'Sold out' ?></div>
          </div>
          <div>
            <button class="btn btn-primary" <?= $seatsLeft > 0 ? '' : 'disabled' ?> onclick='openSeatSelectionModal(<?= json_encode($dep, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS) ?>)'><?= $seatsLeft > 0 ? 'Select Seat' : 'Sold out' ?></button>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- SEAT CLASS SELECTION & BOOKING MODAL -->
<div class="modal-overlay" id="bus-seat-modal" role="dialog" aria-hidden="true" style="display:none;">
  <div class="modal" style="max-width: 520px;">
    <div class="modal-header">
      <div>
        <h3 id="bs-operator-name">Operator</h3>
        <div style="font-size:0.8rem;color:var(--clr-text-soft);" id="bs-route-name">Route</div>
      </div>
      <button class="modal-close" onclick="closeModal('bus-seat-modal')">&times;</button>
    </div>
    <div class="modal-body">
      <div id="bs-form-body">
        <div style="font-size:0.8rem;font-weight:700;color:var(--clr-accent);margin-bottom:0.75rem;"><?= bt_icon(BT_ICON_SEAT) ?> CHOOSE A SEAT CLASS</div>
        <div id="bs-seat-classes"></div>

        <div class="form-group" style="margin-top:1rem;">
          <label class="form-label"><?= bt_icon(BT_ICON_USERS) ?> Number of passengers</label>
          <select id="bs-quantity" class="form-control"><?php for ($q = 1; $q <= 6; $q++): ?><option value="<?= $q ?>"><?= $q ?></option><?php endfor; ?></select>
        </div>
        <div id="bs-passenger-fields"></div>

        <div style="font-size:0.8rem;font-weight:700;color:var(--clr-accent);margin:1rem 0 0.5rem;"><?= bt_icon(BT_ICON_SEAT) ?> SELECT YOUR SEAT POSITION ON BUS</div>
        <div style="background:#0f172a;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:1rem;margin-bottom:1rem;display:flex;flex-direction:column;align-items:center;">
          <div style="font-size:0.65rem;font-weight:800;color:#64748b;text-transform:uppercase;margin-bottom:0.75rem;">FRONT OF BUS / DRIVER</div>
          <div id="cust-seat-picker-grid" style="display:grid;grid-template-columns:repeat(4, 40px);gap:8px;justify-content:center;"></div>
          <div style="font-size:0.72rem;color:#94a3b8;margin-top:0.75rem;display:flex;gap:0.8rem;">
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#10b981;margin-right:3px;"></span>Available</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#e63946;margin-right:3px;"></span>Selected</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#475569;margin-right:3px;"></span>Sold</span>
          </div>
        </div>

        <div style="font-size:0.8rem;font-weight:700;color:var(--clr-accent);margin:1rem 0 0.5rem;">PAY WITH</div>
        <div id="bs-payment-methods"><div class="text-muted text-sm">Loading your payment methods…</div></div>

        <div class="glass-panel" style="padding:1rem;margin-top:1rem;display:flex;align-items:center;justify-content:space-between;">
          <div style="font-size:0.8rem;color:var(--clr-text-soft);">Total for <span id="bs-total-qty">1</span> seat(s)</div>
          <div style="font-size:1.3rem;font-weight:900;color:var(--clr-accent);" id="bs-total-fare">MWK 0</div>
        </div>
        <div id="bs-error" class="text-muted" style="color:#ef4444;margin-top:.5rem;display:none;"></div>
      </div>

      <div id="bs-confirm-state" style="display:none;">
        <div class="glass-panel" style="padding:1.25rem;text-align:center;">
          <div id="bs-confirm-message" style="font-weight:700;margin-bottom:0.5rem;"></div>
          <div id="bs-confirm-detail" class="text-muted text-sm"></div>
        </div>
      </div>
    </div>
    <div class="modal-footer" id="bs-modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('bus-seat-modal')">Cancel</button>
      <button class="btn btn-primary" id="bs-pay-btn" onclick="purchaseBusTicket()">Continue to Payment</button>
    </div>
  </div>
</div>

<script>
(function () {
  var isLoggedIn = <?= isLoggedIn() ? 'true' : 'false' ?>;
  var loginUrl = <?= json_encode(BASE_URL . 'login.php?redirect=' . urlencode(BASE_URL . 'bus-tickets.php'), JSON_UNESCAPED_SLASHES) ?>;
  var purchaseApi = <?= json_encode(BASE_URL . 'api/tie/transport/purchase.php', JSON_UNESCAPED_SLASHES) ?>;
  var statusApi = <?= json_encode(BASE_URL . 'api/tie/transport/purchase-status.php', JSON_UNESCAPED_SLASHES) ?>;
  var paymentMethodsApi = <?= json_encode(BASE_URL . 'api/tie/transport/payment-methods.php', JSON_UNESCAPED_SLASHES) ?>;
  var paymentMethodsPageUrl = <?= json_encode(BASE_URL . 'payment-methods.php', JSON_UNESCAPED_SLASHES) ?>;
  var csrf = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_UNESCAPED_SLASHES) ?>;
  var currentDeparture = null;
  var selectedSeatClassId = null;
  var selectedPaymentMethodId = null;
  var pollTimer = null;


  function renderCustSeatGrid() {
    var grid = document.getElementById('cust-seat-picker-grid');
    if (!grid) return;
    var html = '';
    for (var r = 1; r <= 8; r++) {
      ['A', 'B', 'C', 'D'].forEach(function(col) {
        var sNo = (r < 10 ? '0' + r : r) + col;
        var isSold = (r === 1 && col === 'A') || (r === 3 && col === 'C');
        var bg = isSold ? '#475569' : '#10b981';
        html += '<div class="cust-seat-btn" data-seat="' + sNo + '" style="width:40px;height:40px;border-radius:6px;background:' + bg + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:900;cursor:pointer;" onclick="selectCustSeat(this,'' + sNo + '')">' + sNo + '</div>';
      });
    }
    grid.innerHTML = html;
  }

  var selectedCustSeat = '01B';
  window.selectCustSeat = function(el, sNo) {
    document.querySelectorAll('.cust-seat-btn').forEach(function(b){ if(b.style.background!=='rgb(71, 85, 105)'&&b.style.background!='#475569') b.style.background='#10b981'; });
    if (el.style.background !== 'rgb(71, 85, 105)' && el.style.background !== '#475569') {
      el.style.background = '#e63946';
      selectedCustSeat = sNo;
    }
  };

  window.openSeatSelectionModal = function (dep) {
    if (!isLoggedIn) { window.location.href = loginUrl; return; }
    currentDeparture = dep;
    document.getElementById('bs-operator-name').innerText = dep.operator;
    document.getElementById('bs-route-name').innerText = dep.title + ' · Departs ' + new Date(dep.departure_at).toLocaleString();
    renderSeatClasses();
    document.getElementById('bs-quantity').value = '1';
    renderPassengerFields();
    renderCustSeatGrid();
    updateTotal();
    resetToForm();
    loadPaymentMethods();
    document.getElementById('bus-seat-modal').style.display = 'flex';
  };

  function resetToForm() {
    document.getElementById('bs-error').style.display = 'none';
    document.getElementById('bs-form-body').style.display = '';
    document.getElementById('bs-confirm-state').style.display = 'none';
    document.getElementById('bs-modal-footer').style.display = 'flex';
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }

  function showConfirmState(message, detail) {
    document.getElementById('bs-form-body').style.display = 'none';
    document.getElementById('bs-modal-footer').style.display = 'none';
    document.getElementById('bs-confirm-message').textContent = message;
    document.getElementById('bs-confirm-detail').innerHTML = detail;
    document.getElementById('bs-confirm-state').style.display = 'block';
  }

  function pollBookingStatus(bookingId) {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(function () {
      fetch(statusApi + '?booking_id=' + encodeURIComponent(bookingId), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j || !j.success) return;
        if (j.result.payment_status === 'Paid') {
          clearInterval(pollTimer); pollTimer = null;
          showConfirmState('Payment received!', 'Your ticket is confirmed. <a href="' + <?= json_encode(BASE_URL . 'dashboard.php?tab=tickets', JSON_UNESCAPED_SLASHES) ?> + '">View your ticket</a>.');
        } else if (j.result.payment_status === 'Failed') {
          clearInterval(pollTimer); pollTimer = null;
          showConfirmState('Payment did not go through', 'Your seat has been released. You can try again with a different payment method.');
        }
      });
    }, 4000);
  }

  function loadPaymentMethods() {
    var wrap = document.getElementById('bs-payment-methods');
    var payBtn = document.getElementById('bs-pay-btn');
    fetch(paymentMethodsApi + '?action=list', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      var methods = (j && j.success) ? j.result.methods : [];
      if (!methods.length) {
        wrap.innerHTML = '<div class="glass-panel" style="padding:1rem;">You need a payment method before buying a ticket. <a href="' + paymentMethodsPageUrl + '">Add one now</a>.</div>';
        payBtn.disabled = true;
        selectedPaymentMethodId = null;
        return;
      }
      wrap.innerHTML = '';
      methods.forEach(function (m, i) {
        var isDefault = !!m.is_default;
        var label = document.createElement('label');
        label.className = 'seat-class-option' + ((isDefault || i === 0) ? ' selected' : '');
        var title = m.channel === 'mobile_money' ? (m.operator_name + ' — ' + m.mobile_number_masked) : (m.bank_name ? 'Bank Transfer — ' + m.bank_name : 'Bank Transfer');
        label.innerHTML = '<span><input type="radio" name="bs-payment-method" value="' + m.id + '" ' + ((isDefault || i === 0) ? 'checked' : '') + '> <span class="scn">' + esc(title) + '</span></span>';
        label.querySelector('input').addEventListener('change', function () {
          document.querySelectorAll('#bs-payment-methods .seat-class-option').forEach(function (el) { el.classList.remove('selected'); });
          label.classList.add('selected');
          selectedPaymentMethodId = m.id;
        });
        wrap.appendChild(label);
        if (isDefault || i === 0) selectedPaymentMethodId = m.id;
      });
      payBtn.disabled = false;
    }).catch(function () {
      wrap.innerHTML = '<div class="text-muted text-sm">Could not load payment methods. Refresh and try again.</div>';
      payBtn.disabled = true;
    });
  }

  function renderSeatClasses() {
    var wrap = document.getElementById('bs-seat-classes');
    wrap.innerHTML = '';
    currentDeparture.seat_classes.forEach(function (sc, i) {
      var label = document.createElement('label');
      label.className = 'seat-class-option' + (i === 0 ? ' selected' : '');
      label.innerHTML = '<span><input type="radio" name="bs-seat-class" value="' + sc.departure_seat_id + '" ' + (i === 0 ? 'checked' : '') + '> <span class="scn">' + esc(sc.class_name) + '</span> <span class="scp">(' + sc.remaining_seats + ' left)</span></span><span class="scf">MWK ' + Number(sc.price).toLocaleString() + '</span>';
      label.querySelector('input').addEventListener('change', function () {
        document.querySelectorAll('.seat-class-option').forEach(function (el) { el.classList.remove('selected'); });
        label.classList.add('selected');
        selectedSeatClassId = sc.departure_seat_id;
        updateTotal();
      });
      wrap.appendChild(label);
      if (i === 0) selectedSeatClassId = sc.departure_seat_id;
    });
    document.getElementById('bs-quantity').onchange = function () { renderPassengerFields();
    renderCustSeatGrid(); updateTotal(); };
  }

  function renderPassengerFields() {
    var qty = Number(document.getElementById('bs-quantity').value);
    var wrap = document.getElementById('bs-passenger-fields');
    wrap.innerHTML = '';
    for (var i = 0; i < qty; i++) {
      var row = document.createElement('div');
      row.className = 'passenger-row';
      row.innerHTML = '<input type="text" class="form-control bs-p-name" placeholder="Passenger ' + (i + 1) + ' full name" required>' +
        '<input type="tel" class="form-control bs-p-phone" placeholder="Phone (optional)">';
      wrap.appendChild(row);
    }
    document.getElementById('bs-total-qty').innerText = qty;
  }

  function currentSeatClass() {
    return currentDeparture.seat_classes.find(function (sc) { return sc.departure_seat_id === selectedSeatClassId; });
  }
  function updateTotal() {
    var sc = currentSeatClass();
    var qty = Number(document.getElementById('bs-quantity').value);
    document.getElementById('bs-total-fare').innerText = 'MWK ' + (sc ? (sc.price * qty).toLocaleString() : '0');
  }

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  window.closeModal = function (id) { document.getElementById(id).style.display = 'none'; };

  window.purchaseBusTicket = function () {
    var names = Array.prototype.slice.call(document.querySelectorAll('.bs-p-name'));
    var phones = Array.prototype.slice.call(document.querySelectorAll('.bs-p-phone'));
    var passengers = names.map(function (n, i) { return { name: n.value.trim(), phone: phones[i].value.trim() || null }; });
    var errorEl = document.getElementById('bs-error');
    if (passengers.some(function (p) { return !p.name; })) { errorEl.textContent = 'Enter a name for every passenger.'; errorEl.style.display = 'block'; return; }
    if (!selectedPaymentMethodId) { errorEl.textContent = 'Add a payment method before purchasing a ticket.'; errorEl.style.display = 'block'; return; }
    var btn = document.getElementById('bs-pay-btn');
    btn.disabled = true; btn.textContent = 'Processing…';
    fetch(purchaseApi, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ departure_seat_id: selectedSeatClassId, quantity: passengers.length, passengers: passengers, payment_method_id: selectedPaymentMethodId }),
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j || j.success !== true) throw new Error((j && j.error && j.error.message) || 'Could not start checkout.');
      var result = j.result;
      btn.disabled = false; btn.textContent = 'Continue to Payment';
      if (result.status === 'awaiting_mobile_confirmation') {
        showConfirmState('Check your phone', esc(result.instructions));
      } else if (result.status === 'awaiting_bank_transfer') {
        showConfirmState('Complete your bank transfer', 'Transfer <strong>MWK ' + Number(result.amount).toLocaleString() + '</strong> to:<br>' +
          '<strong>' + esc(result.bank_name) + '</strong><br>Account: ' + esc(result.account_number) + '<br>Name: ' + esc(result.account_name));
      }
      pollBookingStatus(result.booking_id);
    }).catch(function (err) {
      errorEl.textContent = err.message; errorEl.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Continue to Payment';
    });
  };
})();

document.querySelectorAll('.tc-qr[data-qr-payload]').forEach(function (el) {
  var payload = el.getAttribute('data-qr-payload');
  if (!payload || typeof qrcode === 'undefined') return;
  try { var qr = qrcode(0, 'M'); qr.addData(payload); qr.make(); el.innerHTML = qr.createSvgTag(2, 0); } catch (e) {}
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
