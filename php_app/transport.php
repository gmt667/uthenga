<?php
/**
 * Uthenga - Customer Transport Hub
 * Central Transport Gateway: Quick Taxi, Bus Tickets & Trip Planner
 * Designed exactly as specified in the reference mockup.
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Transport';
$activeNav = 'transport';

// Real upcoming bus tickets: this customer's own paid bookings with an
// item_type='bus' line item, enriched with the operator name and the
// departure/seat details captured on the ticket at issuance time.
$upcomingTickets = [];
if (isLoggedIn()) {
    $userId = (string) $_SESSION['user_id'];
    $rows = dbQuery("
        SELECT b.id, b.booking_code, b.booking_status, b.payment_status, b.total_price, b.created_at,
               bi.item_name, bi.metadata, l.vendor_name
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        LEFT JOIN listings l ON l.id = b.listing_id
        WHERE b.customer_id = ? AND bi.item_type = 'bus' AND b.payment_status = 'Paid'
        ORDER BY b.created_at DESC LIMIT 4
    ", [$userId]) ?: [];
    foreach ($rows as $row) {
        $meta = json_decode((string) ($row['metadata'] ?? '{}'), true) ?: [];
        $upcomingTickets[] = [
            'booking_code' => (string) $row['booking_code'],
            'route' => (string) $row['item_name'],
            'operator' => (string) ($row['vendor_name'] ?? ''),
            'seat_class' => (string) ($meta['seat_class'] ?? ''),
            'seat_label' => $meta['seat_label'] ?? null,
            'departure_at' => $meta['departure_at'] ?? null,
            'status' => (string) $row['booking_status'],
        ];
    }
}

$popularRoutes = [
    [
        'from' => 'Lilongwe',
        'to' => 'Blantyre',
        'price' => 'MK 25,000',
        'buses' => 18,
        'image' => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600&fit=crop&q=80',
    ],
    [
        'from' => 'Lilongwe',
        'to' => 'Mzuzu',
        'price' => 'MK 22,000',
        'buses' => 12,
        'image' => 'https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=600&fit=crop&q=80',
    ],
    [
        'from' => 'Blantyre',
        'to' => 'Lilongwe',
        'price' => 'MK 25,000',
        'buses' => 14,
        'image' => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600&fit=crop&q=80',
    ],
    [
        'from' => 'Mzuzu',
        'to' => 'Lilongwe',
        'price' => 'MK 22,000',
        'buses' => 9,
        'image' => 'https://images.unsplash.com/photo-1494515843206-f3117d3f51b7?w=600&fit=crop&q=80',
    ],
];

function tp_icon(string $paths, int $size = 16): string {
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;">' . $paths . '</svg>';
}
const TP_ICON_BUS = '<path d="M4 16V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10"/><path d="M4 16h16"/><path d="M4 11h16"/><circle cx="7.5" cy="18.5" r="1.5"/><circle cx="16.5" cy="18.5" r="1.5"/>';
const TP_ICON_CAR = '<path d="M5 17h14l-1.5-6a2 2 0 0 0-1.94-1.5H8.44A2 2 0 0 0 6.5 11z"/><path d="M5 17v2M19 17v2"/><circle cx="7.5" cy="17" r="1.5"/><circle cx="16.5" cy="17" r="1.5"/>';
const TP_ICON_CALENDAR = '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>';
const TP_ICON_CLOCK = '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>';
const TP_ICON_SEAT = '<path d="M5 11V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v5"/><path d="M4 11h11a2 2 0 0 1 2 2v3H6a2 2 0 0 1-2-2z"/><path d="M17 13h2a2 2 0 0 1 2 2v3h-4"/>';
const TP_ICON_BRIDGE = '<path d="M2 20h20"/><path d="M5 20V10c0-3 3-6 7-6s7 3 7 6v10"/><path d="M9 20v-6M15 20v-6"/>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Uthenga Customer Transport Hub — Quick Taxi, Bus Tickets & Trip Planner across Malawi.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    :root {
      --th-font: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    }
    body {
      font-family: var(--th-font);
      background-color: var(--clr-bg, #f8fafc);
      color: var(--clr-text, #0f172a);
    }

    /* TOP TITLE & HEADER BAR */
    .tp-hub-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .tp-hub-title {
      font-size: 2.2rem;
      font-weight: 900;
      letter-spacing: -0.02em;
      margin-bottom: 0.25rem;
    }
    .tp-hub-sub {
      color: var(--clr-text-soft, #64748b);
      font-size: 1.05rem;
    }
    .tp-top-right-actions {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .tp-icon-btn-wrap {
      width: 40px; height: 40px; border-radius: 50%;
      background: var(--clr-surface, #fff); border: 1px solid var(--clr-border, #e2e8f0);
      display: flex; align-items: center; justify-content: center; position: relative; cursor: pointer;
    }
    .tp-badge-count {
      position: absolute; top: -2px; right: -2px; background: #ef4444; color: #fff;
      font-size: 0.65rem; font-weight: 900; width: 18px; height: 18px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center; border: 2px solid #fff;
    }

    /* TOP 3 PRIMARY ACTION CARDS GRID */
    .tp-action-cards-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1.5rem;
      margin-bottom: 2.5rem;
    }
    @media (max-width: 1180px) { .tp-action-cards-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 700px) { .tp-action-cards-grid { grid-template-columns: 1fr; } }

    .tp-action-card {
      background: var(--clr-surface, #fff);
      border: 1px solid var(--clr-border, #e2e8f0);
      border-radius: 20px;
      padding: 1.75rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      min-height: 255px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      transition: all 0.25s ease;
    }
    .tp-action-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 28px rgba(0,0,0,0.08);
      border-color: rgba(59, 130, 246, 0.3);
    }
    .tp-action-card-content { min-width: 0; flex: 1 1 auto; z-index: 2; }
    .tp-card-badge-title {
      display: flex; align-items: center; gap: 0.5rem; font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem; color: #0f172a;
    }
    .tp-card-badge-icon {
      width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .tp-card-sub {
      font-size: 0.88rem; color: var(--clr-text-soft, #64748b); line-height: 1.45; margin-bottom: 1.5rem; max-width: 220px;
    }

    .tp-btn-action {
      display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.4rem;
      border-radius: 12px; font-weight: 800; font-size: 0.9rem; text-decoration: none; color: #fff; transition: all 0.2s ease;
    }
    .btn-taxi { background: #6366f1; }
    .btn-taxi:hover { background: #4f46e5; }
    .btn-bus { background: #10b981; }
    .btn-bus:hover { background: #059669; }
    .btn-planner { background: #3b82f6; }
    .btn-planner:hover { background: #2563eb; }

    .tp-card-graphic {
      width: clamp(92px, 28%, 132px); height: 110px; flex: 0 0 auto; position: relative; z-index: 1; opacity: 0.9;
    }
    .tp-card-graphic img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }
    @media (max-width: 700px) {
      .tp-action-card { min-height: 0; padding: 1.35rem; }
      .tp-card-graphic { width: 112px; height: 92px; }
    }

    /* MIDDLE SECTION: SEARCH BUSES WIDGET & SIDE PROMO */
    .tp-mid-grid {
      display: grid;
      grid-template-columns: 2.3fr 1fr;
      gap: 1.5rem;
      margin-bottom: 2.5rem;
    }
    @media (max-width: 992px) { .tp-mid-grid { grid-template-columns: 1fr; } }

    .tp-search-bus-card {
      background: var(--clr-surface, #fff);
      border: 1px solid var(--clr-border, #e2e8f0);
      border-radius: 20px;
      padding: 1.75rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .tp-search-header {
      display: flex; align-items: center; gap: 0.6rem; font-size: 1.2rem; font-weight: 800; margin-bottom: 1.25rem;
    }

    .tp-inline-search-form {
      display: grid;
      grid-template-columns: 1.2fr 1.2fr 1.1fr 1.1fr auto;
      gap: 0.85rem;
      align-items: flex-end;
      margin-bottom: 1.25rem;
    }
    @media (max-width: 900px) { .tp-inline-search-form { grid-template-columns: 1fr 1fr; } }

    .tp-form-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .tp-form-label { font-size: 0.75rem; font-weight: 700; color: var(--clr-text-soft, #64748b); }
    .tp-form-select, .tp-form-input {
      width: 100%; height: 44px; border-radius: 10px; border: 1px solid var(--clr-border, #cbd5e1);
      padding: 0 0.85rem; font-size: 0.9rem; font-weight: 600; background: #f8fafc; color: #0f172a;
    }

    .tp-quick-pills {
      display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; font-size: 0.8rem;
    }
    .tp-pill-btn {
      background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 20px; padding: 0.35rem 0.85rem;
      font-weight: 700; color: #475569; text-decoration: none; transition: all 0.15s ease;
    }
    .tp-pill-btn:hover { background: #e2e8f0; color: #0f172a; }

    /* PROMO BANNER CARD */
    .tp-promo-card {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      border-radius: 20px;
      padding: 1.75rem;
      color: #fff;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }
    .tp-promo-title { font-size: 1.4rem; font-weight: 900; margin-bottom: 0.4rem; }
    .tp-promo-sub { font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.5rem; }
    .tp-promo-btn {
      display: inline-block; padding: 0.6rem 1.2rem; background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; color: #fff; font-size: 0.85rem;
      font-weight: 700; text-decoration: none; width: fit-content;
    }

    /* MAIN CONTENT GRID (LEFT CONTENT 70% | RIGHT SIDEBAR 30%) */
    .tp-main-layout-grid {
      display: grid;
      grid-template-columns: 2.3fr 1fr;
      gap: 1.5rem;
    }
    @media (max-width: 992px) { .tp-main-layout-grid { grid-template-columns: 1fr; } }

    .tp-section-title {
      font-size: 1.25rem; font-weight: 800; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;
    }
    .tp-section-link { font-size: 0.82rem; color: #3b82f6; text-decoration: none; font-weight: 700; }

    /* UPCOMING TRAVEL CARDS */
    .tp-upcoming-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2.5rem; }
    @media (max-width: 768px) { .tp-upcoming-grid { grid-template-columns: 1fr; } }

    .tp-upcoming-card {
      background: var(--clr-surface, #fff);
      border: 1px solid var(--clr-border, #e2e8f0);
      border-radius: 16px;
      padding: 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    }
    .tp-tag-tomorrow {
      background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 0.65rem; font-weight: 900;
      padding: 0.25rem 0.5rem; border-radius: 6px; text-transform: uppercase; display: inline-block; margin-bottom: 0.4rem;
    }

    /* POPULAR BUS ROUTES GRID */
    .tp-routes-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 600px) { .tp-routes-grid { grid-template-columns: 1fr; } }

    .tp-route-card {
      background: var(--clr-surface, #fff);
      border: 1px solid var(--clr-border, #e2e8f0);
      border-radius: 16px;
      overflow: hidden;
      transition: all 0.2s ease;
      display: flex; flex-direction: column;
    }
    .tp-route-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.06); }
    .tp-route-img { width: 100%; height: 120px; object-fit: cover; }
    .tp-route-body { padding: 1rem; }
    .tp-route-title { font-size: 1rem; font-weight: 800; margin-bottom: 0.25rem; }
    .tp-route-price { font-size: 0.85rem; color: var(--clr-text-soft, #64748b); margin-bottom: 0.75rem; }
    .tp-route-footer { display: flex; align-items: center; justify-content: space-between; font-size: 0.78rem; font-weight: 700; color: #10b981; border-top: 1px solid #f1f5f9; padding-top: 0.6rem; }

    /* RIGHT SIDEBAR WIDGETS */
    .tp-sidebar-widget {
      background: var(--clr-surface, #fff);
      border: 1px solid var(--clr-border, #e2e8f0);
      border-radius: 16px;
      padding: 1.25rem;
      margin-bottom: 1.25rem;
    }
    .tp-widget-title { font-size: 0.95rem; font-weight: 800; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
    .tp-widget-list { list-style: none; padding: 0; margin: 0 0 1rem 0; font-size: 0.82rem; color: #475569; display: flex; flex-direction: column; gap: 0.5rem; }

    .tp-tip-card {
      background: #fefce8; border: 1px solid #fef08a; border-radius: 16px; padding: 1.25rem; color: #854d0e; font-size: 0.82rem;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container transport-hero" data-navbar-theme="light" style="padding: 2.5rem 0 5rem;">

  <!-- PAGE HEADER -->
  <div class="tp-hub-header">
    <div>
      <h1 class="tp-hub-title">Transport</h1>
      <p class="tp-hub-sub">Move around Malawi your way. Choose the best transport for your journey.</p>
    </div>

    <div class="tp-top-right-actions">
      <div class="tp-icon-btn-wrap" title="Notifications">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="tp-badge-count">5</span>
      </div>

      <div style="background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:0.4rem 0.85rem;font-size:0.85rem;font-weight:700;display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
        <span>Malawi</span>
        <span style="font-size:0.7rem;color:#94a3b8;">▾</span>
      </div>
    </div>
  </div>

  <!-- TOP 3 PRIMARY ACTION SURFACES GRID -->
  <div class="tp-action-cards-grid">
    <!-- CARD 1: QUICK TAXI -->
    <div class="tp-action-card">
      <div class="tp-action-card-content">
        <div class="tp-card-badge-title">
          <div class="tp-card-badge-icon" style="background:rgba(99,102,241,0.15);color:#6366f1;"><?= tp_icon(TP_ICON_CAR, 20) ?></div>
          <span>Quick Taxi</span>
        </div>
        <div class="tp-card-sub">Need a ride now?<br>Get a taxi from your current location.</div>
        <a href="<?= BASE_URL ?>ai.php#/driver" class="tp-btn-action btn-taxi">
          <span>Request Taxi</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
      <div class="tp-card-graphic">
        <img src="https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=400&fit=crop&q=80" alt="Quick Taxi">
      </div>
    </div>

    <!-- CARD 2: BUS TICKETS -->
    <div class="tp-action-card">
      <div class="tp-action-card-content">
        <div class="tp-card-badge-title">
          <div class="tp-card-badge-icon" style="background:rgba(16,185,129,0.15);color:#10b981;"><?= tp_icon(TP_ICON_BUS, 20) ?></div>
          <span>Bus Tickets</span>
        </div>
        <div class="tp-card-sub">Travel by bus<br>Search schedules, compare prices and buy your ticket.</div>
        <a href="<?= BASE_URL ?>bus-tickets.php" class="tp-btn-action btn-bus">
          <span>Find Bus Tickets</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
      <div class="tp-card-graphic">
        <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=400&fit=crop&q=80" alt="Bus Tickets">
      </div>
    </div>

    <!-- CARD 3: PLAN A TRIP -->
    <div class="tp-action-card">
      <div class="tp-action-card-content">
        <div class="tp-card-badge-title">
          <div class="tp-card-badge-icon" style="background:rgba(59,130,246,0.15);color:#3b82f6;"><?= tp_icon(TP_ICON_SUITCASE, 20) ?></div>
          <span>Plan a Trip</span>
        </div>
        <div class="tp-card-sub">Going somewhere for several days?<br>Plan your full journey with stays, tours and transport.</div>
        <a href="<?= BASE_URL ?>ai.php#/planner" class="tp-btn-action btn-planner">
          <span>Open Trip Planner</span>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
      <div class="tp-card-graphic">
        <img src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&fit=crop&q=80" alt="Plan a Trip">
      </div>
    </div>
  </div>

  <!-- MIDDLE SECTION: SEARCH BUSES WIDGET & SIDE PROMO -->
  <div class="tp-mid-grid">
    <!-- SEARCH BUS TICKETS BAR -->
    <div class="tp-search-bus-card">
      <div class="tp-search-header">
        <span style="color:#10b981;"><?= tp_icon(TP_ICON_BUS, 18) ?></span>
        <span>Search Bus Tickets</span>
      </div>

      <form method="GET" action="<?= BASE_URL ?>bus-tickets.php">
        <div class="tp-inline-search-form">
          <div class="tp-form-field">
            <label class="tp-form-label">From</label>
            <select name="from" class="tp-form-select">
              <option value="Lilongwe" selected>Lilongwe</option>
              <option value="Blantyre">Blantyre</option>
              <option value="Mzuzu">Mzuzu</option>
              <option value="Zomba">Zomba</option>
            </select>
          </div>

          <div class="tp-form-field">
            <label class="tp-form-label">To</label>
            <select name="to" class="tp-form-select">
              <option value="Blantyre" selected>Blantyre</option>
              <option value="Lilongwe">Lilongwe</option>
              <option value="Mzuzu">Mzuzu</option>
              <option value="Zomba">Zomba</option>
            </select>
          </div>

          <div class="tp-form-field">
            <label class="tp-form-label">Departure Date</label>
            <input type="date" name="date" class="tp-form-input" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
          </div>

          <div class="tp-form-field">
            <label class="tp-form-label">Passengers</label>
            <select name="passengers" class="tp-form-select">
              <option value="1" selected>1 Adult</option>
              <option value="2">2 Adults</option>
              <option value="3">3 Adults</option>
            </select>
          </div>

          <button type="submit" class="tp-btn-action btn-bus" style="height:44px;border:none;cursor:pointer;">Search Buses</button>
        </div>
      </form>

      <div class="tp-quick-pills">
        <span style="font-weight:700;color:#64748b;">Quick searches:</span>
        <a href="<?= BASE_URL ?>bus-tickets.php?from=Lilongwe&to=Blantyre" class="tp-pill-btn">Lilongwe → Blantyre</a>
        <a href="<?= BASE_URL ?>bus-tickets.php?from=Lilongwe&to=Mzuzu" class="tp-pill-btn">Lilongwe → Mzuzu</a>
        <a href="<?= BASE_URL ?>bus-tickets.php?from=Blantyre&to=Lilongwe" class="tp-pill-btn">Blantyre → Lilongwe</a>
        <a href="<?= BASE_URL ?>bus-tickets.php?from=Mzuzu&to=Lilongwe" class="tp-pill-btn">Mzuzu → Lilongwe</a>
      </div>
    </div>

    <!-- PROMO BANNER CARD -->
    <div class="tp-promo-card">
      <div>
        <h3 class="tp-promo-title">Travel farther for less</h3>
        <p class="tp-promo-sub">Great routes. Best prices. Trusted partners.</p>
      </div>
      <a href="<?= BASE_URL ?>bus-tickets.php" class="tp-promo-btn">Explore routes</a>
    </div>
  </div>

  <!-- MAIN TWO COLUMN LAYOUT (LEFT 70% | RIGHT 30%) -->
  <div class="tp-main-layout-grid">
    
    <!-- LEFT MAIN COLUMN -->
    <div>
      <!-- UPCOMING TRAVEL -->
      <div style="margin-bottom: 2.5rem;">
        <div class="tp-section-title">
          <span><?= tp_icon(TP_ICON_CALENDAR) ?> Upcoming Travel</span>
          <a href="<?= BASE_URL ?>bus-tickets.php?view=my-tickets" class="tp-section-link">View all</a>
        </div>

        <div class="tp-upcoming-grid">
          <?php if ($upcomingTickets): foreach ($upcomingTickets as $ticket): ?>
          <div class="tp-upcoming-card">
            <div style="display:flex;gap:0.85rem;align-items:flex-start;">
              <div style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#10b981;"><?= tp_icon(TP_ICON_BUS, 22) ?></div>
              <div>
                <h4 style="font-size:1.1rem;font-weight:900;margin-bottom:0.15rem;"><?= e($ticket['route']) ?></h4>
                <div style="font-size:0.78rem;color:#64748b;margin-bottom:0.4rem;"><?= e($ticket['operator']) ?><?= $ticket['seat_class'] ? ' • ' . e($ticket['seat_class']) : '' ?></div>
                <div style="font-size:0.75rem;font-weight:700;color:#334155;">
                  <?php if ($ticket['departure_at']): ?><?= tp_icon(TP_ICON_CALENDAR, 13) ?> <?= e(date('d M Y', strtotime($ticket['departure_at']))) ?> • <?= tp_icon(TP_ICON_CLOCK, 13) ?> <?= e(date('H:i', strtotime($ticket['departure_at']))) ?><?php endif; ?>
                  <?php if ($ticket['seat_label']): ?> • <?= tp_icon(TP_ICON_SEAT, 13) ?> Seat <?= e($ticket['seat_label']) ?><?php endif; ?>
                </div>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
              <span style="font-size:0.68rem;font-weight:800;color:#10b981;border:1px solid #10b981;padding:0.2rem 0.5rem;border-radius:6px;display:inline-block;margin-bottom:0.4rem;"><?= e(ucfirst($ticket['status'])) ?></span>
              <div style="font-size:0.7rem;color:#64748b;font-weight:700;"><?= e($ticket['booking_code']) ?></div>
            </div>
          </div>
          <?php endforeach; else: ?>
          <div class="tp-upcoming-card">
            <div style="display:flex;gap:0.85rem;align-items:center;">
              <div style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#10b981;"><?= tp_icon(TP_ICON_BUS, 22) ?></div>
              <div>
                <h4 style="font-size:1.1rem;font-weight:900;margin-bottom:0.15rem;">No upcoming bus tickets</h4>
                <div style="font-size:0.78rem;color:#64748b;">Book a scheduled route below to see it here.</div>
              </div>
            </div>
          </div>
          <div class="tp-upcoming-card">
            <div style="display:flex;gap:0.85rem;align-items:center;">
              <div style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#6366f1;"><?= tp_icon(TP_ICON_CAR, 22) ?></div>
              <div>
                <h4 style="font-size:1.1rem;font-weight:900;margin-bottom:0.15rem;">Need a ride right now?</h4>
                <div style="font-size:0.78rem;color:#64748b;">Quick Taxi finds a live driver near you.</div>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
              <div><a href="<?= BASE_URL ?>ai.php#/driver" style="font-size:0.75rem;font-weight:700;color:#3b82f6;text-decoration:none;">Open Quick Taxi</a></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- POPULAR BUS ROUTES -->
      <div>
        <div class="tp-section-title">
          <span><?= tp_icon(TP_ICON_BRIDGE) ?> Popular Bus Routes</span>
          <a href="<?= BASE_URL ?>bus-tickets.php" class="tp-section-link">View all</a>
        </div>

        <div class="tp-routes-grid">
          <?php foreach ($popularRoutes as $r): ?>
            <div class="tp-route-card">
              <img src="<?= e($r['image']) ?>" alt="<?= e($r['from']) ?> → <?= e($r['to']) ?>" class="tp-route-img">
              <div class="tp-route-body">
                <h4 class="tp-route-title"><?= e($r['from']) ?> → <?= e($r['to']) ?></h4>
                <div class="tp-route-price">From <strong style="color:#0f172a;"><?= e($r['price']) ?></strong></div>
                <div class="tp-route-footer">
                  <span><?= tp_icon(TP_ICON_BUS, 14) ?> <?= $r['buses'] ?> buses available</span>
                  <a href="<?= BASE_URL ?>bus-tickets.php?from=<?= urlencode($r['from']) ?>&to=<?= urlencode($r['to']) ?>" style="width:28px;height:28px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#0f172a;text-decoration:none;font-weight:900;">→</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- RIGHT SIDEBAR COLUMN -->
    <div>
      <!-- WIDGET 1: WHY BOOK ON UTHENGA? -->
      <div class="tp-sidebar-widget">
        <div class="tp-widget-title">
          <span style="color:#10b981;"><?= tp_icon('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 18) ?></span>
          <span>Why book on Uthenga?</span>
        </div>
        <ul class="tp-widget-list">
          <li>✓ Verified transport operators</li>
          <li>✓ Secure payments</li>
          <li>✓ Instant e-ticket delivery</li>
          <li>✓ Easy refunds &amp; support</li>
        </ul>
        <a href="#" style="font-size:0.8rem;color:#3b82f6;font-weight:700;text-decoration:none;">Learn more →</a>
      </div>

      <!-- WIDGET 2: CHAT WITH TIE -->
      <div class="tp-sidebar-widget" style="text-align:center;">
        <div style="display:flex;justify-content:center;margin-bottom:0.4rem;color:#6366f1;"><?= tp_icon('<rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/>', 36) ?></div>
        <h4 style="font-size:0.95rem;font-weight:800;margin-bottom:0.25rem;">Need help choosing?</h4>
        <p style="font-size:0.78rem;color:#64748b;margin-bottom:1rem;">Ask TIE, your travel assistant, for the best options.</p>
        <a href="<?= BASE_URL ?>ai.php#/planner" class="tp-btn-action btn-planner" style="width:100%;justify-content:center;font-size:0.82rem;padding:0.6rem 1rem;">Chat with TIE →</a>
      </div>

      <!-- WIDGET 3: TRAVEL TIPS CARD -->
      <div class="tp-tip-card">
        <div style="font-weight:800;margin-bottom:0.35rem;display:flex;align-items:center;gap:0.4rem;">
          <span style="color:#f59e0b;"><?= tp_icon('<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>', 16) ?></span> Travel Tips
        </div>
        <p style="margin-bottom:0.6rem;line-height:1.45;">Book early for weekend trips to get the best seats and prices.</p>
        <a href="#" style="font-weight:800;color:#854d0e;text-decoration:none;font-size:0.78rem;">See more tips →</a>
      </div>
    </div>

  </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
