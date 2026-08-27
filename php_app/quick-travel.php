<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/tie/bootstrap.php';

// This route is retired. Quick Taxi is served only by the React workspace.
if (is_file(__DIR__ . '/frontend/.vite/manifest.json')) {
    header('Location: ' . BASE_URL . 'ai.php', true, 302);
    exit;
}

$pageTitle = 'Uthenga Quick Taxi';
$activeNav = 'quick-travel';
$pageStyles = ['assets/css/quick-trip-planner.css'];
define('SKIP_AI_WIDGET', true);

$signedIn = isLoggedIn();
$features = UthengaTieFeatureFlags::all();
$userName = (string) ($_SESSION['user_name'] ?? 'Christopher');
$userFirstName = explode(' ', trim($userName))[0] ?: 'Christopher';
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="qt-wrapper">

  <!-- TOP BRAND HEADER -->
  <header class="qt-top-bar">
    <a href="<?= BASE_URL ?>" class="qt-brand">
      <?php $logoSize = 'sm'; $logoLink = false; require __DIR__ . '/includes/logo.php'; ?>
      <div class="qt-brand-text">
        <strong>UTHENGA</strong>
        <small>Quick Taxi</small>
      </div>
    </a>

    <div class="qt-header-right">
      <div class="qt-weather-badge">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
        <span>26°C</span>
      </div>

      <button type="button" class="qt-bell-btn" title="Notifications">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="qt-bell-dot"></span>
      </button>

      <div class="qt-user-badge">
        <div class="qt-user-avatar-wrap">
          <img src="<?= BASE_URL ?>assets/images/avatars/christopher.svg" alt="<?= e($userFirstName) ?>">
          <span class="qt-user-online-dot"></span>
        </div>
        <div class="qt-user-info-text">
          <strong><?= e($userFirstName) ?></strong>
          <small>Online</small>
        </div>
      </div>
    </div>
  </header>

  <!-- TOP SECTION: AI PROMPT & MINI MAP -->
  <section class="qt-top-section">

    <!-- AI Travel Assistant Box -->
    <div class="qt-prompt-card">
      <div class="qt-ai-orb">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="10" rx="2"/>
          <circle cx="12" cy="5" r="2"/><path d="M12 7v4"/>
          <circle cx="8" cy="16" r="1" fill="currentColor"/><circle cx="16" cy="16" r="1" fill="currentColor"/>
        </svg>
      </div>

      <div class="qt-prompt-content">
        <h1 class="qt-greeting-text">
          Hi <?= e($userFirstName) ?>!
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#facc15" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;"><path d="M18 11V6a2 2 0 0 0-4 0v5"/><path d="M14 10V4a2 2 0 0 0-4 0v6"/><path d="M10 10.5V6a2 2 0 0 0-4 0v9"/><path d="M18 11a2 2 0 0 1 4 0v3a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg>
        </h1>
        <p class="qt-sub-greeting">I'm your travel assistant.<br>Let's get you there safely.</p>

        <h2 class="qt-question-heading" id="agent-workspace-title">Where are you heading to?</h2>
        <span class="qt-cursor-bar">|</span>

        <div class="qt-shortcuts-box">
          <div class="qt-shortcuts-label">SUGGESTED SHORTCUTS</div>
          <div class="qt-shortcuts-list">
            <button type="button" class="qt-shortcut-btn green-icon" onclick="document.getElementById('qt-main-input').value='Mzuzu';document.getElementById('qt-main-input').focus();">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="2"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
              <span>Go to Mzuzu</span>
            </button>
            <button type="button" class="qt-shortcut-btn blue-icon" onclick="document.getElementById('qt-main-input').value='Airport Transfer';document.getElementById('qt-main-input').focus();">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.7 5.2c.3.4.8.5 1.3.3l.5-.3c.4-.2.6-.6.5-1.1z"/></svg>
              <span>Airport Transfer</span>
            </button>
            <button type="button" class="qt-shortcut-btn orange-icon" onclick="document.getElementById('qt-main-input').value='Work Commute';document.getElementById('qt-main-input').focus();">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
              <span>Work Commute</span>
            </button>
            <button type="button" class="qt-shortcut-btn">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
              <span>More</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Mini Map -->
    <div class="qt-mini-map-card">
      <div class="qt-map-canvas">
        <svg style="position:absolute;inset:0;width:100%;height:100%;" viewBox="0 0 380 220" preserveAspectRatio="none">
          <!-- Dark terrain -->
          <path d="M 0,180 Q 150,140 380,170 L 380,220 L 0,220 Z" fill="rgba(30,58,138,0.2)"/>
          <!-- Roads -->
          <path d="M 0,130 C 100,120 200,140 380,120" stroke="rgba(255,255,255,0.05)" stroke-width="2" fill="none"/>
          <path d="M 60,0 C 80,80 90,140 100,220" stroke="rgba(255,255,255,0.05)" stroke-width="2" fill="none"/>
          <!-- Dotted route line (origin -> destination) -->
          <path d="M 72,158 C 130,130 210,140 290,55" stroke="#f43f5e" stroke-width="3" stroke-dasharray="8 5" fill="none" stroke-linecap="round"/>
          <!-- Origin dot (blue) -->
          <circle cx="72" cy="158" r="7" fill="#3b82f6" opacity="0.9"/>
          <circle cx="72" cy="158" r="12" fill="rgba(59,130,246,0.25)"/>
          <!-- Destination dot (red) -->
          <circle cx="290" cy="55" r="7" fill="#f43f5e" opacity="0.9"/>
          <circle cx="290" cy="55" r="12" fill="rgba(244,63,94,0.25)"/>
        </svg>

        <!-- Pin 1: Origin label -->
        <div class="qt-map-pin origin">
          <div class="qt-pin-badge">
            <strong>Your Location</strong>
            <small>Area 47, Lilongwe</small>
          </div>
        </div>

        <!-- Pin 2: Destination label -->
        <div class="qt-map-pin dest">
          <div class="qt-pin-badge">
            <strong>Mzuzu Bus Depot</strong>
            <small>Destination</small>
          </div>
        </div>

        <!-- Floating Controls -->
        <div class="qt-map-controls">
          <button type="button" class="qt-map-btn" title="Recenter">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
          <button type="button" class="qt-map-btn" title="Zoom In">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
          <button type="button" class="qt-map-btn" title="Zoom Out">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- MAIN TRIPLE COLUMN GRID -->
  <section class="qt-main-grid">

    <!-- Column 1: YOUR TRIP (DRAFT) -->
    <div class="qt-draft-card">
      <div class="qt-card-header-row">
        <h3 class="qt-card-title">YOUR TRIP (DRAFT)</h3>
        <a href="#clear" class="qt-link-clear">Clear</a>
      </div>

      <div class="qt-draft-items">
        <div class="qt-draft-item">
          <div class="qt-draft-icon green">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </div>
          <div class="qt-draft-details">
            <small>From</small>
            <strong>Current Location</strong>
            <span>Area 47, Lilongwe</span>
          </div>
          <div class="qt-draft-action-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
        </div>

        <div class="qt-draft-item">
          <div class="qt-draft-icon red">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </div>
          <div class="qt-draft-details">
            <small>To</small>
            <strong>Not set yet</strong>
          </div>
        </div>

        <div class="qt-draft-item">
          <div class="qt-draft-icon blue">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <div class="qt-draft-details">
            <small>Date</small>
            <strong>Not set yet</strong>
          </div>
        </div>

        <div class="qt-draft-item">
          <div class="qt-draft-icon purple">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div class="qt-draft-details">
            <small>Passengers</small>
            <strong>1 Passenger</strong>
          </div>
        </div>

        <div class="qt-draft-item">
          <div class="qt-draft-icon orange">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          </div>
          <div class="qt-draft-details">
            <small>Preference</small>
            <strong>Not set yet</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Column 2: MATCHING TRANSPORTS -->
    <div class="qt-transports-card">
      <div class="qt-match-header">
        <div class="qt-match-count">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          <span>8 MATCHING TRANSPORTS FOUND</span>
        </div>
        <div style="display:flex;align-items:center;gap:0.5rem;">
          <span>Sorted by:</span>
          <select class="qt-sort-select">
            <option>Best Match</option>
            <option>Fastest</option>
            <option>Cheapest</option>
          </select>
        </div>
      </div>

      <div class="qt-transport-list">

        <!-- Card 1: Axon Bus Services (RECOMMENDED) -->
        <div class="qt-transport-item recommended">
          <div class="qt-bus-thumb-wrap">
            <img src="<?= BASE_URL ?>assets/images/buses/axon_bus.svg" alt="Axon Bus Services">
            <span class="qt-badge-recommended">RECOMMENDED</span>
            <div class="qt-rating-pill">
              <span>4.8</span>
              <svg width="10" height="10" viewBox="0 0 24 24" fill="#facc15" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
          </div>
          <div class="qt-transport-info">
            <h4 class="qt-transport-title"><span style="color:var(--qt-green-light);">Axon</span> Bus Services</h4>
            <div class="qt-transport-location">Lilongwe Bus Depot</div>
            <div class="qt-transport-meta-row">
              <div class="qt-transport-meta-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>Departs 14:30 (Today)</span>
              </div>
              <span>·</span>
              <div class="qt-transport-meta-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 4a2 2 0 1 0-4 0 2 2 0 0 0 4 0z"/><path d="M7 21l3-7 2 3v4"/><path d="M17 21l-4-8-2 1-3 4"/><path d="M7 11l4-2 3 3 3-2"/></svg>
                <span>18 min away</span>
              </div>
            </div>
            <div class="qt-seats-left green">Seats left: 8</div>
          </div>
          <div class="qt-transport-price-side">
            <div class="qt-transport-price">MWK 12,000</div>
            <div class="qt-transport-perseat">Per seat</div>
            <button type="button" class="qt-badge-btn green">Best Match</button>
          </div>
        </div>

        <!-- Card 2: Speed Coaster -->
        <div class="qt-transport-item">
          <div class="qt-bus-thumb-wrap">
            <img src="<?= BASE_URL ?>assets/images/buses/speed_coaster.svg" alt="Speed Coaster">
            <div class="qt-rating-pill">
              <span>4.5</span>
              <svg width="10" height="10" viewBox="0 0 24 24" fill="#facc15" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
          </div>
          <div class="qt-transport-info">
            <h4 class="qt-transport-title">Speed Coaster</h4>
            <div class="qt-transport-location">Area 25 Terminal</div>
            <div class="qt-transport-meta-row">
              <div class="qt-transport-meta-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>Departs 14:15 (Today)</span>
              </div>
              <span>·</span>
              <div class="qt-transport-meta-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 4a2 2 0 1 0-4 0 2 2 0 0 0 4 0z"/><path d="M7 21l3-7 2 3v4"/><path d="M17 21l-4-8-2 1-3 4"/><path d="M7 11l4-2 3 3 3-2"/></svg>
                <span>12 min away</span>
              </div>
            </div>
            <div class="qt-seats-left orange">Seats left: 5</div>
          </div>
          <div class="qt-transport-price-side">
            <div class="qt-transport-price">MWK 15,000</div>
            <div class="qt-transport-perseat">Per seat</div>
            <button type="button" class="qt-badge-btn blue">Fastest</button>
          </div>
        </div>

        <!-- Card 3: Malawi Express -->
        <div class="qt-transport-item">
          <div class="qt-bus-thumb-wrap">
            <img src="<?= BASE_URL ?>assets/images/buses/malawi_express.svg" alt="Malawi Express">
            <div class="qt-rating-pill">
              <span>4.2</span>
              <svg width="10" height="10" viewBox="0 0 24 24" fill="#facc15" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
          </div>
          <div class="qt-transport-info">
            <h4 class="qt-transport-title">Malawi Express</h4>
            <div class="qt-transport-location">Kamuzu Highway Stop</div>
            <div class="qt-transport-meta-row">
              <div class="qt-transport-meta-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>Departs 15:00 (Today)</span>
              </div>
              <span>·</span>
              <div class="qt-transport-meta-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 4a2 2 0 1 0-4 0 2 2 0 0 0 4 0z"/><path d="M7 21l3-7 2 3v4"/><path d="M17 21l-4-8-2 1-3 4"/><path d="M7 11l4-2 3 3 3-2"/></svg>
                <span>25 min away</span>
              </div>
            </div>
            <div class="qt-seats-left green">Seats left: 12</div>
          </div>
          <div class="qt-transport-price-side">
            <div class="qt-transport-price">MWK 11,000</div>
            <div class="qt-transport-perseat">Per seat</div>
            <button type="button" class="qt-badge-btn purple">Cheapest</button>
          </div>
        </div>

      </div>
      <a href="#options" class="qt-view-all-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
        View all 8 options ▾
      </a>
    </div>

    <!-- Column 3: STATUS CARDS -->
    <div class="qt-right-col">

      <!-- Card 1: DEPARTURE CONFIRMED -->
      <div class="qt-card-confirmed">
        <div class="qt-confirmed-top">
          <span>DEPARTURE CONFIRMED (PENDING BOARDING)</span>
          <div class="qt-live-tag">LIVE</div>
        </div>

        <div class="qt-confirmed-body">
          <div class="qt-confirmed-left">
            <div class="qt-confirmed-bus-icon">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="2"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
            </div>
            <div class="qt-confirmed-info">
              <strong>Axon Bus Services</strong>
              <span>Driver: Patrick Banda</span>
              <span>Bus Reg: MW AX 1234</span>
            </div>
          </div>

          <div class="qt-confirmed-countdown">
            <small>Departs in</small>
            <div class="qt-confirmed-digits" id="qt-live-countdown">00:18:22</div>
            <small>14:30 Today</small>
          </div>
        </div>
      </div>

      <!-- Card 2: GETTING THERE -->
      <div class="qt-card-getting-there">
        <div class="qt-getting-head">GETTING THERE</div>

        <div class="qt-getting-body">
          <div class="qt-getting-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 4a2 2 0 1 0-4 0 2 2 0 0 0 4 0z"/><path d="M7 21l3-7 2 3v4"/><path d="M17 21l-4-8-2 1-3 4"/><path d="M7 11l4-2 3 3 3-2"/></svg>
          </div>
          <div class="qt-getting-info">
            <strong>You're 320 metres away</strong>
            <span>Est. walk time: 4 min</span>
            <div class="qt-progress-bar-wrap">
              <div class="qt-progress-fill"></div>
            </div>
            <span style="font-size:0.65rem;color:var(--qt-text-dim);margin-top:0.25rem;display:block;">Stay on Kaliyeka Road</span>
          </div>
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.2rem;flex-shrink:0;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#facc15" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
            <span style="font-size:0.65rem;color:#ffffff;font-weight:700;">Good progress!</span>
          </div>
        </div>
      </div>

      <!-- Card 3: AI ADVICE -->
      <div class="qt-card-advice">
        <div class="qt-advice-left">
          <div class="qt-advice-title">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span>AI ADVICE</span>
          </div>
          <p class="qt-advice-text">Leave in the next 5 minutes<br>to arrive on time.</p>
        </div>

        <div class="qt-advice-weather">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#93c5fd" stroke-width="2"><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25"/><line x1="8" y1="19" x2="8" y2="21"/><line x1="12" y1="19" x2="12" y2="21"/><line x1="16" y1="19" x2="16" y2="21"/></svg>
          <strong>26°C</strong>
          <div style="display:flex;align-items:center;gap:0.3rem;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="5 12 5 7 12 3 19 7 19 12"/><line x1="12" y1="22" x2="12" y2="17"/><path d="M9 22v-4h6v4"/><path d="M5 12H3l9 10 9-10h-2"/></svg>
            <span style="font-size:0.68rem;">Light rain</span>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- BOTTOM FIXED ACTION BAR -->
  <div class="qt-bottom-bar">
    <div class="qt-input-box">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      <input type="text" id="qt-main-input" class="qt-input-field" placeholder="Type your answer..." autocomplete="off">
      <button type="button" class="qt-send-btn" title="Send">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      </button>
    </div>

    <div class="qt-bottom-actions">
      <button type="button" class="qt-action-pill call">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        <span>Call Driver</span>
      </button>
      <button type="button" class="qt-action-pill cancel">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span>Cancel Trip</span>
      </button>
      <button type="button" class="qt-action-pill share">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        <span>Share Trip</span>
      </button>
      <button type="button" class="qt-action-pill help">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3z"/><path d="M3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
        <span>Need Help?</span>
      </button>
    </div>
  </div>

</div>

<script>
(function() {
  'use strict';
  // Live countdown
  function tick() {
    var node = document.getElementById('qt-live-countdown');
    if (!node) return;
    var now = new Date();
    var seconds = 18 * 60 + 22 - (Math.floor(now.getTime() / 1000) % 60);
    if (seconds < 0) seconds = 18 * 60 + 22;
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    var s = seconds % 60;
    var pad = function(n) { return String(n).padStart(2, '0'); };
    node.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
  }
  setInterval(tick, 1000);

  // Focus main input on load
  var input = document.getElementById('qt-main-input');
  if (input) setTimeout(function() { input.focus(); }, 200);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
