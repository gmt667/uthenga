<?php
/**
 * UTHENGA EVENT SERVICES CONTROL CENTER — Mission Control Center
 * Complete Event Operations Workspace Dashboard with persistent AI Panel & Status Bar.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/tie/bootstrap.php';
require_once __DIR__ . '/../includes/tie/Api.php';
require_once __DIR__ . '/../includes/tie/Events.php';

requireApprovedVendor();

// This control surface embeds its module controllers in the document. Do not
// allow a browser to reuse an old controller after a deployment.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/tie/TicketTemplates.php';

$ewTicketPreviewTpls = ['vip', 'vvip', 'early_bird', 'general', 'group', 'season'];
$ewTicketPreviewSamples = [
    'event_title' => 'MALAWI BUSINESS SUMMIT 2026',
    'tagline' => 'Connect. Innovate. Grow.',
    'date' => '18 AUG 2026',
    'time' => '08:00 AM - 06:00 PM',
    'venue' => 'SUNBIRD CAPITAL HOTEL',
    'city' => 'LILONGWE, MALAWI',
    'ticket_id' => 'UTH-XXXX-000001',
    'holder' => 'Guest Ticket',
    'qr_payload' => 'WIZARD-PREVIEW-0001',
    'row' => 'A',
    'seat' => '01',
    'extra' => '5',
    'valid_from' => '01 JAN 2026',
    'valid_to' => '31 DEC 2026',
    'notes' => ['All Music Events', 'All Workshops', 'All Conferences', 'Priority Booking'],
];
$ewTicketPreviewByTpl = [];
foreach ($ewTicketPreviewTpls as $ewTpl) {
    $ewPerks = match ($ewTpl) {
        'vip' => ['VIP LOUNGE', 'FRONT ROW SEATING', 'NETWORKING ACCESS', 'WELCOME DRINK'],
        'vvip' => ['BACKSTAGE ACCESS', 'VIP PARKING', 'GOURMET DINNER', 'MEET & GREET'],
        'early_bird' => ['EARLY BIRD PRICE', 'ENTRY INCLUDED'],
        'group' => ['GROUP ADMISSION', 'GROUP ACCESS AREA'],
        'season' => [],
        default => ['ENTRY INCLUDED', 'ALL DAY ACCESS'],
    };
    $ewPerkIcons = $ewTpl === 'vvip' ? ['mic', 'car', 'utensils', 'group'] : [];
    $ewTicketPreviewByTpl[$ewTpl] = uthenga_ticket_render(array_merge($ewTicketPreviewSamples, [
        'template' => $ewTpl,
        'ticket_name' => match ($ewTpl) {
            'vip' => 'VIP PASS',
            'vvip' => 'VVIP PASS',
            'early_bird' => 'EARLY BIRD',
            'group' => 'GROUP PASS',
            'season' => 'SEASON PASS',
            default => 'GENERAL ADMISSION',
        },
        'badge' => $ewTpl === 'group' ? 'ADMIT 5 PEOPLE' : 'ADMIT ONE',
        'perks' => $ewPerks,
        'perk_icons' => $ewPerkIcons,
        'preview' => true,
    ]));
}

$pageTitle = 'Event Services Control Center | Uthenga';
$userName  = (string) ($_SESSION['user_name'] ?? 'Daniel Chirwa');
$vendorId  = (string) ($_SESSION['user_id'] ?? '');
$userFirstName = explode(' ', trim($userName))[0] ?: 'Daniel';
$userLastName = trim((string) preg_replace('/^\S+\s+/', '', (string) $userName)) ?: 'Chirwa';
$userRoleLabel = 'Event Organizer';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e(uthenga_theme_preference()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/events-control-center.css?v=<?= rawurlencode(APP_VERSION) ?>-12">
  <script src="<?= BASE_URL ?>assets/js/qrcode-generator.js"></script>
  <script>!function(){var t=document.documentElement,s=null;try{s=localStorage.getItem('uthenga-theme')}catch(e){}if(!s){var c=document.cookie.split('; ').find(function(r){return r.startsWith('uthenga-theme=')});if(c)s=decodeURIComponent(c.split('=').slice(1).join('='))}if(s==='dark'||s==='light')t.dataset.theme=s}();</script>
  <style>
    /* ─── Accommodation Control Center Header Complete Style System ─────── */
    body.ecc-page { font-family:'Plus Jakarta Sans', Inter, system-ui, sans-serif; }

    .acc-header {
      height: 64px !important;
      background: #0b1324 !important;
      border-bottom: 1px solid rgba(148,163,184,.14) !important;
      padding: 0 1.5rem !important;
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      gap: 1rem !important;
      position: relative !important;
      z-index: 100 !important;
      flex-shrink: 0 !important;
      width: 100% !important;
      box-sizing: border-box !important;
    }
    .acc-header-left { display: flex !important; align-items: center !important; gap: 1rem !important; flex-wrap: nowrap !important; }
    .acc-header-right { display: flex !important; align-items: center !important; gap: 0.75rem !important; flex-wrap: nowrap !important; }

    .acc-property-select {
      display: inline-flex !important; align-items: center !important; gap: 0.55rem !important;
      background: #0f1830 !important; border: 1px solid rgba(148,163,184,.14) !important;
      border-radius: 99px !important; padding: 0.4rem 0.9rem !important; font-size: 0.82rem !important; font-weight: 700 !important;
      cursor: pointer !important; color: #f1f5f9 !important; position: relative !important;
    }
    .acc-context-trigger {
      display: inline-flex !important; align-items: center !important; gap: 0.55rem !important;
      background: transparent !important; border: none !important; color: #f1f5f9 !important;
      font: inherit !important; cursor: pointer !important; padding: 0 !important; max-width: 320px !important;
    }
    .acc-context-name { font-weight: 700 !important; font-size: 0.85rem !important; overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; }
    .acc-context-chevron { transition: transform 0.2s !important; color: #94a3b8 !important; flex-shrink: 0 !important; }

    .acc-context-menu {
      display: none !important; position: absolute !important; top: calc(100% + 0.5rem) !important; left: 0 !important;
      width: 340px !important; max-width: 90vw !important; background: #0d1627 !important;
      border: 1px solid rgba(148,163,184,.16) !important; border-radius: 12px !important;
      box-shadow: 0 16px 48px rgba(0,0,0,0.35) !important; padding: 0.5rem !important; z-index: 1000 !important;
    }
    .acc-context-menu.open { display: block !important; }
    .acc-context-menu-head { font-size: 0.62rem !important; letter-spacing: 0.12em !important; text-transform: uppercase !important; color: #64748b !important; font-weight: 800 !important; padding: 0.5rem 0.6rem 0.4rem !important; }
    .acc-context-row { margin: 0 !important; }
    .acc-context-row-btn { width: 100% !important; display: flex !important; align-items: center !important; gap: 0.6rem !important; background: transparent !important; border: none !important; color: #f1f5f9 !important; cursor: pointer !important; padding: 0.55rem 0.6rem !important; border-radius: 9px !important; text-align: left !important; font: inherit !important; }
    .acc-context-row-btn:hover { background: #141f3d !important; }
    .acc-context-row.active .acc-context-row-btn { background: #141f3d !important; }
    .acc-context-dot { width: 8px !important; height: 8px !important; border-radius: 50% !important; flex: none !important; }
    .acc-context-row-main { display: flex !important; flex-direction: column !important; min-width: 0 !important; flex: 1 !important; }
    .acc-context-row-main b { font-size: 0.8rem !important; overflow: hidden !important; text-overflow: ellipsis !important; white-space: nowrap !important; color: #f1f5f9 !important; }
    .acc-context-row-main small { font-size: 0.68rem !important; color: #64748b !important; }
    .acc-context-check { font-size: 0.62rem !important; font-weight: 800 !important; color: #10b981 !important; flex: none !important; }
    .acc-context-menu-foot { display: flex !important; justify-content: space-between !important; gap: 0.5rem !important; border-top: 1px solid rgba(148,163,184,.14) !important; margin-top: 0.4rem !important; padding: 0.6rem 0.4rem 0.2rem !important; }
    .acc-context-menu-foot a { font-size: 0.74rem !important; font-weight: 800 !important; color: #e63946 !important; text-decoration: none !important; }
    .acc-context-menu-foot a.acc-context-new { color: #10b981 !important; }

    .acc-status-pill { background: rgba(16, 185, 129, 0.15) !important; color: #10b981 !important; font-size: 0.63rem !important; font-weight: 900 !important; padding: 0.15rem 0.5rem !important; border-radius: 99px !important; text-transform: uppercase !important; letter-spacing: 0.03em !important; flex-shrink: 0 !important; }

    .acc-brand { display: inline-flex !important; align-items: center !important; gap: 0.55rem !important; text-decoration: none !important; margin-right: 0.5rem !important; }

    .acc-search-box { position: relative !important; width: 330px !important; max-width: 380px !important; display: inline-flex !important; align-items: center !important; }
    .acc-search-input { width: 100% !important; height: 38px !important; background: #0f1830 !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; border-radius: 10px !important; padding: 0 3.2rem 0 2.5rem !important; color: #f1f5f9 !important; font-size: 0.8rem !important; font-weight: 500 !important; font-family: inherit !important; outline: none !important; box-sizing: border-box !important; transition: all 0.2s ease !important; box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08) !important; }
    .acc-search-input:focus { background: #0b1324 !important; border-color: #e63946 !important; box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.18), inset 0 1px 2px rgba(0,0,0,0.06) !important; }
    .acc-search-input::placeholder { color: #64748b !important; font-weight: 500 !important; }
    .acc-search-icon { position: absolute !important; left: 0.85rem !important; top: 50% !important; transform: translateY(-50%) !important; color: #64748b !important; pointer-events: none !important; transition: color 0.2s ease !important; }
    .acc-search-box:focus-within .acc-search-icon { color: #e63946 !important; }
    .acc-search-kbd { position: absolute !important; right: 0.55rem !important; top: 50% !important; transform: translateY(-50%) !important; background: rgba(148, 163, 184, 0.12) !important; border: 1px solid rgba(148, 163, 184, 0.2) !important; border-radius: 6px !important; padding: 0.15rem 0.4rem !important; font-size: 0.62rem !important; color: #94a3b8 !important; font-weight: 700 !important; letter-spacing: 0.02em !important; pointer-events: none !important; }

    .ecc-date-pill { display: inline-flex !important; align-items: center !important; gap: 0.45rem !important; background: #0f1830 !important; border: 1px solid rgba(148,163,184,.14) !important; border-radius: 99px !important; color: #94a3b8 !important; padding: 0.42rem 0.9rem !important; font-size: 0.73rem !important; font-weight: 700 !important; flex-shrink: 0 !important; }
    .ecc-date-pill svg { color: #64748b !important; flex-shrink: 0 !important; }

    .acc-theme-btn { display: inline-flex !important; align-items: center !important; gap: 0.4rem !important; background: #0f1830 !important; border: 1px solid rgba(148,163,184,.14) !important; border-radius: 99px !important; padding: 0.38rem 0.85rem !important; font-size: 0.74rem !important; font-weight: 700 !important; color: #94a3b8 !important; cursor: pointer !important; flex-shrink: 0 !important; }
    .acc-theme-btn:hover { border-color: #e63946 !important; color: #f1f5f9 !important; }

    .acc-tbtn { display: inline-flex !important; align-items: center !important; gap: 0.4rem !important; background: #0f1830 !important; border: 1px solid rgba(148,163,184,.14) !important; border-radius: 99px !important; padding: 0.38rem 0.85rem !important; font-size: 0.74rem !important; font-weight: 700 !important; color: #94a3b8 !important; text-decoration: none !important; flex-shrink: 0 !important; }
    .acc-tbtn:hover { border-color: #e63946 !important; color: #e63946 !important; }

    .acc-hd-wrap { position: relative !important; display: inline-flex !important; align-items: center !important; }
    .acc-icon-btn { width: 36px !important; height: 36px !important; border-radius: 50% !important; background: #0f1830 !important; border: 1px solid rgba(148,163,184,.14) !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; color: #94a3b8 !important; cursor: pointer !important; position: relative !important; flex-shrink: 0 !important; }
    .acc-icon-btn:hover { color: #f1f5f9 !important; border-color: #e63946 !important; }
    .acc-icon-badge { position: absolute !important; top: -3px !important; right: -3px !important; width: 17px !important; height: 17px !important; background: #e63946 !important; color: #fff !important; font-size: 0.6rem !important; font-weight: 900 !important; border-radius: 50% !important; display: flex !important; align-items: center !important; justify-content: center !important; border: 2px solid #0b1324 !important; box-shadow: 0 2px 6px rgba(230, 57, 70, 0.4) !important; }

    .acc-user-pill { display: inline-flex !important; align-items: center !important; gap: 0.65rem !important; padding: 0.25rem 0.75rem 0.25rem 0.25rem !important; background: #0f1830 !important; border: 1px solid rgba(148,163,184,.14) !important; border-radius: 99px !important; cursor: pointer !important; flex-shrink: 0 !important; }
    .acc-user-pill:hover { border-color: #e63946 !important; }
    .acc-user-avatar { width: 32px !important; height: 32px !important; border-radius: 50% !important; flex-shrink: 0 !important; background: rgba(230, 57, 70, 0.16) !important; color: #e63946 !important; font-weight: 900 !important; font-size: 0.75rem !important; display: grid !important; place-items: center !important; border: 2px solid #e63946 !important; }
    .acc-user-name { font-size: 0.8rem !important; font-weight: 800 !important; line-height: 1.1 !important; color: #f1f5f9 !important; }
    .acc-user-role { font-size: 0.66rem !important; color: #64748b !important; }

    .acc-hd-pop { display: none !important; position: absolute !important; top: calc(100% + 0.55rem) !important; right: 0 !important; width: 340px !important; max-width: 92vw !important; background: #0d1627 !important; border: 1px solid rgba(148,163,184,.16) !important; border-radius: 14px !important; box-shadow: 0 16px 48px rgba(0,0,0,0.3) !important; overflow: hidden !important; z-index: 1000 !important; }
    .acc-hd-pop.open { display: block !important; }
    .acc-hd-pop-hd { display: flex !important; align-items: center !important; gap: 0.6rem !important; padding: 0.75rem 1rem !important; border-bottom: 1px solid rgba(148,163,184,.14) !important; }
    .acc-hd-pop-hd b { font-size: 0.8rem !important; color: #f1f5f9 !important; }
    .acc-hd-pop-hd button { margin-left: auto !important; font-size: 0.65rem !important; font-weight: 800 !important; color: #e63946 !important; background: none !important; border: none !important; cursor: pointer !important; font-family: inherit !important; }
    .acc-hd-pop-ft { padding: 0.65rem 1rem !important; border-top: 1px solid rgba(148,163,184,.14) !important; text-align: center !important; }
    .acc-hd-pop-ft a { font-size: 0.72rem !important; font-weight: 800 !important; color: #e63946 !important; text-decoration: none !important; }
    .acc-notif-item { display: flex !important; align-items: center !important; gap: 0.7rem !important; padding: 0.65rem 1rem !important; border-bottom: 1px solid rgba(148,163,184,.14) !important; cursor: pointer !important; transition: background 0.15s ease !important; }
    .acc-notif-item:hover { background: #141f3d !important; }
    .acc-notif-ico { width: 32px !important; height: 32px !important; border-radius: 50% !important; display: grid !important; place-items: center !important; flex-shrink: 0 !important; }
    .acc-notif-item b { display: block !important; font-size: 0.78rem !important; color: #f1f5f9 !important; }
    .acc-notif-item small { display: block !important; font-size: 0.68rem !important; color: #64748b !important; }
    .acc-notif-item .t { margin-left: auto !important; font-size: 0.62rem !important; color: #64748b !important; font-weight: 600 !important; }

    /* Light Theme Overrides */
    html[data-theme="light"] .acc-header { background: #ffffff !important; border-bottom-color: #e2e8f0 !important; }
    html[data-theme="light"] .acc-search-box { background: transparent !important; }
    html[data-theme="light"] .acc-search-input { background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #0f172a !important; box-shadow: inset 0 1px 2px rgba(0,0,0,0.03) !important; }
    html[data-theme="light"] .acc-search-input:focus { background: #ffffff !important; border-color: #e63946 !important; box-shadow: 0 0 0 3px rgba(230, 57, 70, 0.14) !important; }
    html[data-theme="light"] .acc-search-input::placeholder { color: #94a3b8 !important; }
    html[data-theme="light"] .acc-search-icon { color: #64748b !important; }
    html[data-theme="light"] .acc-search-kbd { background: #ffffff !important; border-color: #cbd5e1 !important; color: #64748b !important; }
    html[data-theme="light"] .acc-theme-btn, html[data-theme="light"] .acc-tbtn, html[data-theme="light"] .acc-icon-btn, html[data-theme="light"] .ecc-date-pill, html[data-theme="light"] .acc-user-pill { background: #f1f5f9 !important; border-color: #e2e8f0 !important; color: #0f172a !important; }
    html[data-theme="light"] .acc-user-name { color: #0f172a !important; }
    html[data-theme="light"] .acc-user-role, html[data-theme="light"] .ecc-date-pill { color: #475569 !important; }
    html[data-theme="light"] .acc-hd-pop, html[data-theme="light"] .acc-context-menu { background: #ffffff !important; border-color: #e2e8f0 !important; }
    html[data-theme="light"] .acc-hd-pop.open, html[data-theme="light"] .acc-context-menu.open { display: block !important; }
    html[data-theme="light"] .acc-context-row-btn:hover, html[data-theme="light"] .acc-notif-item:hover { background: #f1f5f9 !important; }
    html[data-theme="light"] .acc-context-row-main b, html[data-theme="light"] .acc-notif-item b { color: #0f172a !important; }

    /* Sidebar */
    .ecc-sidebar { background:#0b1324; border-right:1px solid rgba(148,163,184,.14); }
    .ecc-nav-list { display:flex; flex-direction:column; gap:.1rem; flex:1; min-height:0; overflow-y:auto; scrollbar-width:thin; scrollbar-color:rgba(148,163,184,.25) transparent; }
    .ecc-nav-group-label { font-size:.6rem; letter-spacing:.13em; text-transform:uppercase; color:#64748b; font-weight:800; padding:.8rem 1rem .3rem; }
    .ecc-nav-item { display:flex; align-items:center; justify-content:space-between; gap:.7rem; padding:.45rem .8rem; border-radius:11px; color:#94a3b8; font-size:.82rem; font-weight:600; border:1px solid transparent; position:relative; transition:all .18s ease; }
    .ecc-nav-item:hover { background:rgba(148,163,184,.1); color:#f1f5f9; }
    .ecc-nav-item.active { background:linear-gradient(90deg,rgba(230,57,70,.22),rgba(230,57,70,.06)); color:#f1f5f9; font-weight:800; border-color:transparent; }
    .ecc-nav-item.active::before { content:''; position:absolute; left:-.9rem; top:22%; bottom:22%; width:3px; background:#e63946; border-radius:0 3px 3px 0; }
    .ecc-nav-item.active svg { color:#e63946; }

    html[data-theme="light"] .ecc-sidebar { background:#ffffff; border-right-color:#e2e8f0; }
    html[data-theme="light"] .ecc-nav-group-label { color:#64748b; }
    html[data-theme="light"] .ecc-nav-item { color:#475569; }
    html[data-theme="light"] .ecc-nav-item:hover { background:rgba(15,23,42,.06); color:#0f172a; }
    html[data-theme="light"] .ecc-nav-item.active { background:linear-gradient(90deg,rgba(230,57,70,.18),rgba(230,57,70,.05)); color:#0f172a; }

    /* Layout Shell */
    .ecc-shell { display:flex !important; flex-direction:column !important; height:100vh !important; overflow:hidden !important; }
    .ecc-body  { display:flex !important; flex:1 !important; min-height:0 !important; overflow:hidden !important; }

    @media (max-width:900px) { .ecc-nav-group-label { display:none; } }

    /* ── Ticket step: template picker + live preview ─────────────── */
    .ew-tpl-picker { display:grid; grid-template-columns:repeat(5,1fr); gap:0.55rem; margin:0.4rem 0 0.2rem; }
    .ew-tpl-card {
      position:relative; border:2px solid var(--ecc-border); border-radius:12px; padding:0.6rem 0.35rem 0.55rem;
      background:var(--ecc-surface); cursor:pointer; text-align:center; transition:border-color .14s ease, transform .1s ease, box-shadow .14s ease;
    }
    .ew-tpl-card:hover { border-color:rgba(230,57,70,.45); transform:translateY(-1px); }
    .ew-tpl-card.active { border-color:var(--ecc-primary); box-shadow:0 0 0 3px rgba(230,57,70,.14); }
    .ew-tpl-swatch { position:relative; width:30px; height:30px; border-radius:9px; margin:0 auto 0.4rem; box-shadow:inset 0 0 0 1px rgba(255,255,255,.25), 0 4px 10px rgba(15,23,42,.14); }
    .ew-tpl-swatch.vip { background:linear-gradient(135deg,#0f0c29,#302b63); }
    .ew-tpl-swatch.vvip { background:linear-gradient(135deg,#17130a,#0d0b06 60%,#151007); }
    .ew-tpl-swatch.vvip::after { content:'▲'; position:absolute; inset:0; display:grid; place-items:center; color:#e8b64c; font-size:0.7rem; font-weight:900; }
    .ew-tpl-swatch.early_bird { background:linear-gradient(135deg,#11998e,#38ef7d); }
    .ew-tpl-swatch.general { background:linear-gradient(135deg,#0052D4,#6FB1FC); }
    .ew-tpl-swatch.group { background:linear-gradient(135deg,#7F00FF,#E100FF); }
    .ew-tpl-swatch.season { background:linear-gradient(135deg,#c0392b,#922b21); }
    .ew-tpl-card b { display:block; font-size:0.7rem; font-weight:800; color:var(--ecc-text); line-height:1.2; }
    .ew-tpl-card small { font-size:0.58rem; font-weight:600; color:var(--ecc-text-dim); }
    .ew-tpl-card .ew-tpl-check { position:absolute; top:0.3rem; right:0.3rem; width:16px; height:16px; border-radius:50%; background:var(--ecc-primary); color:#fff; font-size:0.62rem; line-height:16px; display:none; }
    .ew-tpl-card.active .ew-tpl-check { display:block; }
    .ew-live-preview { margin:0.9rem 0 0.3rem; }
    .ew-live-preview .ew-pv-label { display:flex; align-items:center; gap:0.45rem; font-size:0.68rem; font-weight:800; color:var(--ecc-text-dim); text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.5rem; }
    .ew-live-preview .ew-pv-label::after { content:''; flex:1; height:1px; background:var(--ecc-border); }
    .ew-pv-slide { display:none; }
    .ew-pv-slide.active { display:block; animation:ev-pop .2s ease; }
    .ew-pv-slide .uth-tk { margin:0; }
    .ew-tb-hint { font-size:0.68rem; color:var(--ecc-text-muted); font-weight:600; margin:0.15rem 0 0; }
    .ew-tb-flags { display:flex; gap:1.1rem; margin-top:0.3rem; }
    .ew-tb-submit { display:flex; justify-content:flex-end; gap:0.5rem; margin-top:0.85rem; }
    @media (max-width:820px) { .ew-tpl-picker { grid-template-columns:repeat(3,1fr); } .ew-tpl-picker .ew-tpl-card:nth-child(4), .ew-tpl-picker .ew-tpl-card:nth-child(5) { grid-column:span 1; } }

    /* ── TicketType Wizard (TicketsWiz) live pass preview board ─────── */
    .ew-tk-ivp { width:100%; max-width:620px; margin:0 auto; }
    .ew-tk-ivp .ticket{position:relative;display:grid;grid-template-columns:1fr 128px;border-radius:14px;overflow:hidden;min-height:196px;box-shadow:0 10px 30px rgba(0,0,0,.55);background:#fff;color:var(--ink,#111827)}
    .ew-tk-ivp .ticket.sp{display:flex!important;flex-direction:row!important;align-items:stretch!important}
    .ew-tk-ivp .ticket.sp .t-main{flex:1 1 auto!important;min-width:0!important}
    .ew-tk-ivp .ticket.sp .mid{flex:0 0 100px!important;width:100px!important}
    .ew-tk-ivp .ticket.sp .t-stub{flex:0 0 100px!important;width:100px!important}
    .ew-tk-ivp .t-main{position:relative;padding:18px 20px;display:flex;flex-direction:column;gap:6px;z-index:1}
    .ew-tk-ivp .t-main>*{position:relative;z-index:2}
    .ew-tk-ivp .brand{display:flex;align-items:center;gap:8px;font-weight:800;font-size:13px;letter-spacing:1px}
    .ew-tk-ivp .brand .gear{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;font-size:13px;background:currentColor}
    .ew-tk-ivp .brand .gear span{color:#fff;display:inline-flex;line-height:1}
    .ew-tk-ivp .brand small{display:block;font-weight:600;font-size:7.5px;letter-spacing:3px;opacity:.75}
    .ew-tk-ivp .t-title{font-size:clamp(18px,1.8vw,24px);font-weight:900;letter-spacing:.3px;line-height:1.1;margin-top:2px}
    .ew-tk-ivp .t-sub{font-size:13px;font-weight:700;letter-spacing:.5px}
    .ew-tk-ivp .tagline{font-size:11.5px;font-weight:500;opacity:.85}
    .ew-tk-ivp .meta{list-style:none;margin-top:auto;display:grid;grid-template-columns:auto auto;gap:4px 18px;justify-content:start;font-size:11px;font-weight:600;letter-spacing:.4px;padding:0}
    .ew-tk-ivp .meta li{display:flex;align-items:center;gap:6px;white-space:nowrap}
    .ew-tk-ivp .meta li .mi{font-size:12px;opacity:.9;display:inline-flex}
    .ew-tk-ivp .meta.one-col{grid-template-columns:auto}
    .ew-tk-ivp .perks{display:flex;gap:18px;flex-wrap:wrap;margin-top:auto;font-size:9px;font-weight:700;letter-spacing:.8px;text-transform:uppercase}
    .ew-tk-ivp .perks span{display:flex;align-items:center;gap:5px;opacity:.9}
    .ew-tk-ivp .t-stub{position:relative;padding:14px 12px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:6px;border-left:2px dashed rgba(0,0,0,.18);color:#fff}
    .ew-tk-ivp .t-stub::before,.ew-tk-ivp .t-stub::after{content:"";position:absolute;left:-9px;width:16px;height:16px;border-radius:50%;background:var(--ecc-surface-2);z-index:3}
    .ew-tk-ivp .t-stub::before{top:-9px}
    .ew-tk-ivp .t-stub::after{bottom:-9px}
    .ew-tk-ivp .stub-title{font-size:13px;font-weight:800;letter-spacing:.6px;line-height:1.2}
    .ew-tk-ivp .tid{font-size:8px;font-weight:700;letter-spacing:1.2px;opacity:.8;text-transform:uppercase}
    .ew-tk-ivp .tid b{display:block;font-size:10px;letter-spacing:.5px;margin-top:2px;opacity:1}
    .ew-tk-ivp .rowseat{display:flex;gap:14px;font-size:9px;font-weight:700;letter-spacing:1px;opacity:.9}
    .ew-tk-ivp .rowseat b{display:block;font-size:14px;letter-spacing:0}
    .ew-tk-ivp .qr{width:64px;height:64px;background:#fff;border-radius:6px;padding:5px;margin-top:2px}
    .ew-tk-ivp .qr svg{width:100%;height:100%;display:block}
    .ew-tk-ivp .qr-placeholder{width:100%;height:100%;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:3px}
    .ew-tk-ivp .admit{margin-top:auto;font-size:10px;font-weight:800;letter-spacing:2px;text-transform:uppercase}
    .ew-tk-ivp .vvip .t-main{background:linear-gradient(120deg,rgba(232,182,76,.10) 0%,transparent 40%),linear-gradient(300deg,rgba(232,182,76,.14) 0%,transparent 35%),linear-gradient(160deg,#17130a,#0d0b06 60%,#151007);color:#fff}
    .ew-tk-ivp .vvip .t-main::after{content:"";position:absolute;inset:0;z-index:1;background:repeating-linear-gradient(115deg,transparent 0 26px,rgba(232,182,76,.05) 26px 27px)}
    .ew-tk-ivp .vvip .t-main>*{position:relative;z-index:2}
    .ew-tk-ivp .vvip .brand .gear{background:rgba(232,182,76,.2);color:var(--gold,#e8b64c)}
    .ew-tk-ivp .vvip .t-title{background:linear-gradient(90deg,#f5deab,#e8b64c);-webkit-background-clip:text;background-clip:text;color:transparent}
    .ew-tk-ivp .vvip .t-sub{color:#f3f4f6}
    .ew-tk-ivp .vvip .meta li{color:#e5e7eb}
    .ew-tk-ivp .vvip .perks{color:#e8c877}
    .ew-tk-ivp .vvip .t-stub{background:#0f0d08;border-left-color:rgba(232,182,76,.35)}
    .ew-tk-ivp .vvip .stub-title{color:var(--gold,#e8b64c)}
    .ew-tk-ivp .st .t-main::after{content:"";position:absolute;inset:0;z-index:1;background:radial-gradient(120% 170% at 118% 130%,#fbbf7a 44%,#fde8cf 52%,transparent 58%)}
    .ew-tk-ivp .st .brand{color:#7c2d12}
    .ew-tk-ivp .st .brand .gear{background:#7c2d12}
    .ew-tk-ivp .st .t-title{color:#f59e0b}
    .ew-tk-ivp .st .t-sub,.ew-tk-ivp .st .meta{color:#1f2937}
    .ew-tk-ivp .st .t-stub{background:linear-gradient(180deg,#f59e0b,#ea8a0a)}
    .ew-tk-ivp .cp .t-main::after{content:"";position:absolute;inset:0;z-index:1;background:radial-gradient(110% 160% at 115% -20%,#f9a8d4 40%,#fce7f3 50%,transparent 58%)}
    .ew-tk-ivp .cp .brand{color:#831843}
    .ew-tk-ivp .cp .brand .gear{background:#831843}
    .ew-tk-ivp .cp .t-title{color:#ec4899}
    .ew-tk-ivp .cp .t-sub,.ew-tk-ivp .cp .meta{color:#1f2937}
    .ew-tk-ivp .cp .t-stub{background:linear-gradient(180deg,#ec4899,#db2777)}
    .ew-tk-ivp .ex .t-main::after{content:"";position:absolute;inset:0;z-index:1;background:radial-gradient(85% 140% at 112% 100%,#0f766e 40%,#5eead4 50%,#ccfbf1 57%,transparent 63%)}
    .ew-tk-ivp .ex .brand{color:#134e4a}
    .ew-tk-ivp .ex .brand .gear{background:#134e4a}
    .ew-tk-ivp .ex .t-title{color:#0d9488}
    .ew-tk-ivp .ex .t-sub,.ew-tk-ivp .ex .meta{color:#1f2937}
    .ew-tk-ivp .ex .t-stub{background:linear-gradient(180deg,#0d9488,#0f766e)}
    .ew-tk-ivp .dp .t-main::after{content:"";position:absolute;inset:0;z-index:1;background:radial-gradient(115% 165% at 108% 120%,#c2410c 40%,#f97316 48%,#fdba74 55%,transparent 62%)}
    .ew-tk-ivp .dp .brand{color:#7c2d12}
    .ew-tk-ivp .dp .brand .gear{background:#7c2d12}
    .ew-tk-ivp .dp .t-title{color:#f97316}
    .ew-tk-ivp .dp .t-sub,.ew-tk-ivp .dp .meta{color:#1f2937}
    .ew-tk-ivp .dp .dayhex{position:absolute;right:150px;top:50%;transform:translateY(-50%);z-index:2;width:84px;height:92px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(255,255,255,.92);clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%);color:#c2410c;font-weight:900}
    .ew-tk-ivp .dp .dayhex small{font-size:10px;letter-spacing:2px;font-weight:800}
    .ew-tk-ivp .dp .dayhex b{font-size:30px;line-height:1}
    .ew-tk-ivp .dp .t-stub{background:linear-gradient(180deg,#f97316,#ea580c)}
    @media (max-width:640px){ .ew-tk-ivp .ticket{grid-template-columns:1fr 108px} .ew-tk-ivp .dp .dayhex{display:none} .ew-tk-ivp .meta{grid-template-columns:auto} }
    <?= uthenga_ticket_render_css() ?>
    .ticket-legacy { --tk-notch-bg: var(--ecc-surface-2, #1e293b); }
    .ew-pv-slide .ticket-legacy { box-shadow: 0 10px 26px rgba(0,0,0,.4); }

    /* ─── Venue Management System ─────────────────────────────── */
    .vw-steps { display:flex; gap:0.35rem; flex-wrap:wrap; padding:0.9rem 1.5rem; border-bottom:1px solid var(--ecc-border); background:var(--ecc-surface-2, rgba(148,163,184,.06)); }
    .vw-steps .vw-step { font-size:0.66rem; font-weight:800; letter-spacing:0.03em; text-transform:uppercase; color:var(--ecc-text-dim); border:1px solid var(--ecc-border); border-radius:99px; padding:0.28rem 0.7rem; opacity:0.55; }
    .vw-steps .vw-step.active { color:#fff; background:linear-gradient(90deg,#6366f1,#a855f7); border-color:transparent; opacity:1; box-shadow:0 4px 14px rgba(99,102,241,.35); }
    .vc-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:0.7rem; margin-bottom:1rem; }
    .vc-kpi { border:1px solid var(--ecc-border); border-radius:14px; padding:0.85rem 1rem; background:var(--ecc-surface-2, rgba(148,163,184,.06)); }
    .vc-view-switch { display:inline-flex; gap:0.25rem; background:var(--ecc-surface-3, rgba(148,163,184,.1)); border-radius:10px; padding:0.2rem; }
    .vc-view-btn { border:0; background:transparent; color:var(--ecc-text-dim); font-size:0.7rem; font-weight:800; padding:0.32rem 0.7rem; border-radius:8px; cursor:pointer; }
    .vc-view-btn.active { background:var(--ecc-surface-1, #0f172a); color:#fff; box-shadow:0 2px 8px rgba(0,0,0,.25); }
    .vc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:0.9rem; }
    .vc-card { border:1px solid var(--ecc-border); border-radius:16px; overflow:hidden; background:var(--ecc-surface-2, rgba(148,163,184,.06)); display:flex; flex-direction:column; transition:transform .15s ease, box-shadow .15s ease; }
    .vc-card:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(0,0,0,.25); }
    .vc-card-img { height:120px; position:relative; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:900; font-size:1.6rem; }
    .vc-card-body { padding:0.8rem 0.9rem 0.9rem; display:flex; flex-direction:column; gap:0.4rem; flex:1; }
    .vc-card-menu { border-top:1px dashed var(--ecc-border); padding-top:0.5rem; display:grid; gap:0.3rem; }
    .vc-table { width:100%; border-collapse:collapse; font-size:0.74rem; }
    .vc-table th { text-align:left; font-size:0.62rem; text-transform:uppercase; letter-spacing:0.07em; color:var(--ecc-text-dim); padding:0.5rem 0.6rem; border-bottom:1px solid var(--ecc-border); }
    .vc-table td { padding:0.55rem 0.6rem; border-bottom:1px solid var(--ecc-border); vertical-align:middle; }
    .vc-table tbody tr:hover { background:var(--ecc-surface-2, rgba(148,163,184,.06)); }
    .vc-cal { width:100%; border-collapse:collapse; }
    .vc-cal th { font-size:0.62rem; text-transform:uppercase; letter-spacing:0.07em; color:var(--ecc-text-dim); padding:0.4rem 0.3rem; text-align:left; }
    .vc-cal td { border:1px solid var(--ecc-border); width:14.28%; min-height:72px; vertical-align:top; padding:0.3rem; }
    .vc-cal-empty { background:transparent !important; border-color:transparent !important; }
    .vc-cal-day { background:var(--ecc-surface-2, rgba(148,163,184,.05)); min-height:74px; }
    .vc-cal-day.today .vc-cal-num { background:#6366f1; color:#fff; border-radius:99px; }
    .vc-cal-day.clickable { cursor:pointer; }
    .vc-cal-day.clickable:hover { outline:1px solid #6366f1; }
    .vc-cal-num { font-size:0.68rem; font-weight:800; display:inline-block; padding:0.15rem 0.45rem; margin-bottom:0.25rem; }
    .vc-cal-item { font-size:0.6rem; color:#fff; border-radius:6px; padding:0.22rem 0.35rem; margin-bottom:0.2rem; display:flex; justify-content:space-between; gap:0.3rem; overflow:hidden; }
    .vc-cal-t1 { font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .vc-cal-t2 { opacity:0.8; flex-shrink:0; }
    .vc-cal-more { font-size:0.6rem; color:var(--ecc-text-dim); font-weight:700; }
    .vc-legend { display:inline-block; width:9px; height:9px; border-radius:3px; margin-right:0.3rem; }
    .vc-ws-head { display:flex; gap:1rem; align-items:flex-start; border:1px solid var(--ecc-border); border-radius:16px; padding:1rem; background:var(--ecc-surface-2, rgba(148,163,184,.06)); }
    .vc-ws-cover { width:84px; height:84px; border-radius:14px; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:900; font-size:1.6rem; }
    .vc-ws-nav { display:flex; gap:0.3rem; flex-wrap:wrap; margin-top:0.9rem; }
    .vc-ws-tab { border:1px solid var(--ecc-border); background:transparent; color:var(--ecc-text-dim); font-size:0.7rem; font-weight:800; padding:0.4rem 0.8rem; border-radius:99px; cursor:pointer; }
    .vc-ws-tab.active { background:linear-gradient(90deg,#6366f1,#a855f7); color:#fff; border-color:transparent; }
    .vc-box { border:1px solid var(--ecc-border); border-radius:12px; padding:0.6rem 0.75rem; background:var(--ecc-surface-2, rgba(148,163,184,.06)); min-width:0; }
    .vc-av-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:0.6rem; margin-top:0.8rem; }
    .vc-fac-cb { display:flex; gap:0.4rem; align-items:center; font-size:0.72rem; cursor:pointer; }
  </style>

</head>
<body class="ecc-page">

<div class="ecc-shell">

  <!-- TOP NAVIGATION BAR (UNIFORM WITH ACCOMMODATION CONTROL CENTER HEADER) -->
  <header class="acc-header">
    <div class="acc-header-left">
      <!-- Official Uthenga Logo Brand & Event Services Workspace Label -->
      <a href="<?= BASE_URL ?>vendor/portal.php" class="acc-brand" style="display:flex;align-items:center;gap:0.55rem;text-decoration:none;margin-right:0.5rem;">
        <?php $logoSize = 'sm'; $logoLink = false; require __DIR__ . '/../includes/logo.php'; ?>
        <span class="acc-brand-sub" style="padding:0.22rem 0.6rem;background:rgba(230,57,70,0.12);color:var(--ecc-primary,#e63946);border-radius:6px;font-size:0.63rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;white-space:nowrap;display:inline-block;">EVENTS SERVICES</span>
      </a>

      <!-- Global Search Box -->
      <div class="acc-search-box">
        <svg class="acc-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="ecc-global-search" class="acc-search-input" placeholder="Search events, tickets, attendees..." oninput="if(this.value.trim()){switchEccModule('events');var el=document.getElementById('ev-search-input');if(el)el.value=this.value;state.q=this.value.trim();EventsWorkspace.render();}">
        <span class="acc-search-kbd">Ctrl + K</span>
      </div>
    </div>

    <div class="acc-header-right">
      <!-- Preserved Date Component (Clean Vector Calendar SVG Icon, No Emojis) -->
      <div class="ecc-date-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span><?= date('l, d M Y') ?></span>
      </div>

      <!-- AI Assistant Panel Toggle -->
      <div class="acc-hd-wrap" style="position:relative;">
        <button type="button" class="acc-icon-btn" id="ecc-ai-toggle" onclick="toggleAiPanel()" aria-label="Toggle AI assistant" title="AI Assistant">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15l.9 2.4 2.4.9-2.4.9-.9 2.4-.9-2.4-2.4-.9 2.4-.9z"/></svg>
        </button>
      </div>

      <!-- Theme Switcher Button -->
      <button type="button" class="acc-theme-btn" id="ecc-theme-toggle" onclick="toggleEventsTheme()">
        <svg id="ecc-theme-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <span id="ecc-theme-text">Dark Mode</span>
      </button>

      <!-- Back to Dashboard -->
      <a href="<?= BASE_URL ?>vendor/portal.php" class="acc-tbtn" style="font-size:.74rem;padding:.4rem .85rem;gap:.4rem;text-decoration:none;display:inline-flex;align-items:center;color:var(--ecc-text-dim);">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex:none"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        <span>Back to Dashboard</span>
      </a>

      <!-- Notifications Bell -->
      <div class="acc-hd-wrap" style="position:relative;">
        <button type="button" class="acc-icon-btn" id="ecc-bell-btn" onclick="eccHdToggle('ecc-notif-pop')" aria-label="Notifications">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="acc-icon-badge" id="ecc-bell-badge">3</span>
        </button>
        <div class="acc-hd-pop" id="ecc-notif-pop">
          <div class="acc-hd-pop-hd"><b>Notifications</b><button onclick="eccNotifMarkAll()">Mark all read</button></div>
          <div id="ecc-notif-pop-body">
            <div class="acc-notif-item unread" onclick="switchEccModule('events')">
              <div class="acc-notif-ico" style="background:rgba(16,185,129,0.14);color:#10b981">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </div>
              <div><b>Malawi Innovation Summit</b><small>15 new tickets sold</small></div>
              <span class="t">now</span>
            </div>
            <div class="acc-notif-item unread" onclick="switchEccModule('attendees')">
              <div class="acc-notif-ico" style="background:rgba(56,189,248,0.14);color:#60a5fa">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/></svg>
              </div>
              <div><b>Check-In LIVE Ready</b><small>245 attendees verified</small></div>
              <span class="t">today</span>
            </div>
          </div>
          <div class="acc-hd-pop-ft"><a href="#mod-events" onclick="switchEccModule('events')">View All Activity</a></div>
        </div>
      </div>

      <!-- Messages Chat Icon -->
      <div class="acc-hd-wrap" style="position:relative;">
        <button type="button" class="acc-icon-btn" id="ecc-msg-btn" onclick="eccHdToggle('ecc-msg-pop')" aria-label="Messages">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <span class="acc-icon-badge" id="ecc-msg-badge">1</span>
        </button>
        <div class="acc-hd-pop" id="ecc-msg-pop">
          <div class="acc-hd-pop-hd"><b>Messages</b><button onclick="eccMsgMarkAll()">Mark all read</button></div>
          <div id="ecc-msg-pop-body">
            <div class="acc-notif-item unread" onclick="switchEccModule('messages')">
              <div class="acc-notif-ico" style="background:rgba(139,92,246,0.14);color:#a78bfa">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <div><b>Uthenga Support</b><small>Event listing published successfully</small></div>
              <span class="t">today</span>
            </div>
          </div>
          <div class="acc-hd-pop-ft"><a href="#mod-messages" onclick="switchEccModule('messages')">Open Messages</a></div>
        </div>
      </div>

      <!-- User Profile Pill & Account Menu -->
      <div class="acc-hd-wrap" style="position:relative;">
        <button type="button" class="acc-user-pill" id="ecc-user-btn" onclick="eccHdToggle('ecc-user-menu')">
          <span class="acc-user-avatar" style="background:rgba(230,57,70,.16);color:var(--ecc-primary);display:grid;place-items:center;font-size:.72rem;font-weight:900"><?= e(strtoupper(substr($userFirstName, 0, 1))) ?><?= e(strtoupper(substr($userLastName, 0, 1))) ?></span>
          <div>
            <div class="acc-user-name"><?= e($userFirstName) ?> <?= e($userLastName) ?></div>
            <div class="acc-user-role"><?= e($userRoleLabel) ?></div>
          </div>
        </button>
        <div class="acc-hd-pop" id="ecc-user-menu" style="width:270px">
          <div style="padding:.9rem 1rem;border-bottom:1px solid var(--ecc-border);display:flex;align-items:center;gap:.7rem">
            <span class="acc-user-avatar" style="width:40px;height:40px;font-size:.95rem;background:rgba(230,57,70,.15);color:var(--ecc-primary);display:grid;place-items:center;font-weight:900"><?= e(strtoupper(substr($userFirstName, 0, 1))) ?><?= e(strtoupper(substr($userLastName, 0, 1))) ?></span>
            <div><b style="font-size:.8rem;color:var(--ecc-text);display:block"><?= e($userFirstName) ?> <?= e($userLastName) ?></b><small style="color:var(--ecc-text-dim);font-size:.64rem;font-weight:700"><?= e($userRoleLabel) ?></small></div>
          </div>
          <div style="padding:.4rem;display:grid">
            <a href="#mod-settings" onclick="switchEccModule('settings')" class="acc-settings-sec" style="padding:0.5rem 0.7rem;border-radius:8px;color:var(--ecc-text);text-decoration:none;font-size:0.78rem;font-weight:600;">My Profile</a>
            <a href="#mod-settings" onclick="switchEccModule('settings')" class="acc-settings-sec" style="padding:0.5rem 0.7rem;border-radius:8px;color:var(--ecc-text);text-decoration:none;font-size:0.78rem;font-weight:600;">Settings</a>
          </div>
          <div style="padding:.4rem;border-top:1px solid var(--ecc-border);display:grid">
            <button type="button" onclick="eccHdClose();document.getElementById('ecc-context-menu').classList.toggle('open')" style="text-align:left;background:none;border:none;padding:0.5rem 0.7rem;border-radius:8px;color:var(--ecc-text);font-size:0.78rem;font-weight:600;cursor:pointer;">Switch Context</button>
            <a href="<?= BASE_URL ?>logout.php" style="padding:0.5rem 0.7rem;border-radius:8px;color:#f87171;text-decoration:none;font-size:0.78rem;font-weight:700;">Sign Out</a>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- CORE THREE-PANEL BODY (Left Menu | Dynamic Workspace | Persistent Right AI Panel) -->
  <div class="ecc-body">

    <!-- LEFT SIDEBAR NAVIGATION -->
    <aside class="ecc-sidebar">
      <div class="ecc-nav-list">
        <div class="ecc-nav-group-label">Operations</div>
        <a class="ecc-nav-item active" data-mod="dashboard" onclick="switchEccModule('dashboard')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="events" onclick="switchEccModule('events')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg><span>Events</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="tickets" onclick="switchEccModule('tickets')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/></svg><span>Tickets</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="attendees" onclick="switchEccModule('attendees')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg><span>Attendees</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="check-in" onclick="switchEccModule('check-in')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/></svg><span>Check-In</span></div>
          <span class="ecc-nav-badge red">LIVE</span>
        </a>

        <div class="ecc-nav-group-label">Event Business</div>
        <a class="ecc-nav-item" data-mod="venues" onclick="switchEccModule('venues')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span>Venues</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="marketing" onclick="switchEccModule('marketing')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><span>Marketing</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="finance" onclick="switchEccModule('finance')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Finance</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="customers" onclick="switchEccModule('customers')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span>Customers</span></div>
        </a>

        <div class="ecc-nav-group-label">Insights</div>
        <a class="ecc-nav-item" data-mod="analytics" onclick="switchEccModule('analytics')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Analytics</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="reviews" onclick="switchEccModule('reviews')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Reviews</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="messages" onclick="switchEccModule('messages')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg><span>Messages</span></div>
          <span class="ecc-nav-badge purple">12</span>
        </a>

        <div class="ecc-nav-group-label">System</div>
        <a class="ecc-nav-item" data-mod="documents" onclick="switchEccModule('documents')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg><span>Documents</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="staff" onclick="switchEccModule('staff')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/></svg><span>Staff</span></div>
        </a>

        <a class="ecc-nav-item" data-mod="settings" onclick="switchEccModule('settings')">
          <div class="ecc-nav-left"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span>Settings</span></div>
        </a>
      </div>

      <!-- User Profile Footer -->
      <div class="ecc-user-card" onclick="switchEccModule('settings')">
        <img src="<?= BASE_URL ?>assets/images/avatars/christopher.svg" alt="<?= e($userName) ?>">
        <div class="ecc-user-info">
          <strong><?= e($userName) ?></strong>
          <small>Event Organizer</small>
        </div>
      </div>
    </aside>

    <!-- CENTER DYNAMIC WORKSPACE -->
    <main class="ecc-workspace">

      <!-- MODULE 0: DASHBOARD -->
      <div id="mod-dashboard" class="ecc-module-content active">
        <div class="ecc-hero-greeting">
          <h1>Good morning, <?= e(explode(' ', $userName)[0]) ?></h1>
          <p>Everything is ready for today's events.</p>
        </div>

        <!-- 5 Top KPI Cards -->
        <div class="ecc-stats-row">
          <div class="ecc-stat-card" onclick="switchEccModule('finance')">
            <div class="ecc-stat-info">
              <label>Revenue Today</label>
              <h2>MK 4,350,000</h2>
              <div class="ecc-stat-sub green">↑ 32% vs yesterday</div>
            </div>
            <div class="ecc-stat-icon-wrap purple"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
          </div>

          <div class="ecc-stat-card" onclick="switchEccModule('tickets')">
            <div class="ecc-stat-info">
              <label>Tickets Sold</label>
              <h2>245</h2>
              <div class="ecc-stat-sub green">↑ 18% vs yesterday</div>
            </div>
            <div class="ecc-stat-icon-wrap green"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/></svg></div>
          </div>

          <div class="ecc-stat-card" onclick="switchEccModule('events')">
            <div class="ecc-stat-info">
              <label>Active Events</label>
              <h2>3</h2>
              <div class="ecc-stat-sub blue">1 Live · 2 Upcoming</div>
            </div>
            <div class="ecc-stat-icon-wrap blue"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg></div>
          </div>

          <div class="ecc-stat-card" onclick="switchEccModule('check-in')">
            <div class="ecc-stat-info">
              <label>Check-ins Today</label>
              <h2>118</h2>
              <div class="ecc-stat-sub amber">76% of expected</div>
            </div>
            <div class="ecc-stat-icon-wrap orange"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          </div>

          <div class="ecc-stat-card" onclick="switchEccModule('messages')">
            <div class="ecc-stat-info">
              <label>Pending Messages</label>
              <h2>12</h2>
              <div class="ecc-stat-sub pink">Requires attention</div>
            </div>
            <div class="ecc-stat-icon-wrap pink"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></div>
          </div>
        </div>

        <!-- Live Event Featured Card & Upcoming List -->
        <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
          <div class="ecc-card">
            <div class="ecc-card-head">
              <h3>Live Event Operations</h3>
              <a href="#all" onclick="switchEccModule('events')" class="ecc-card-link">View All</a>
            </div>
            <div class="ecc-live-card">
              <div class="ecc-live-thumb-wrap">
                <img src="https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=600&q=80" alt="Worship Concert">
                <span class="ecc-live-badge-overlay">LIVE NOW</span>
              </div>
              <div class="ecc-live-info">
                <div>
                  <h4>Annual Worship Concert 2025</h4>
                  <p>Bingu International Convention Centre · Hall A</p>
                </div>
                <div style="font-size:0.75rem;display:flex;justify-content:space-between;">
                  <span><strong>150 / 200</strong> Checked-in</span>
                  <span><strong>MK 2,250,000</strong> Revenue</span>
                </div>
                <div class="ecc-progress-bar-wrap"><div class="ecc-progress-fill" style="width:75%;"></div></div>
                <div style="display:flex;gap:0.4rem;margin-top:0.6rem;">
                  <button type="button" class="ecc-btn ecc-btn-primary" style="flex:1;font-size:0.72rem;" onclick="switchEccModule('check-in')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <span>Open Live Dashboard</span>
                  </button>
                  <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.72rem;" onclick="eccNotify('Broadcast sent to all attendees!')">Broadcast Message</button>
                </div>
              </div>
            </div>
          </div>

          <div class="ecc-card">
            <div class="ecc-card-head">
              <h3>Upcoming Events</h3>
              <a href="#all" onclick="switchEccModule('events')" class="ecc-card-link">View All</a>
            </div>
            <div class="ecc-upcoming-list">
              <div class="ecc-upcoming-item">
                <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=400&q=80" alt="Conf">
                <div class="ecc-upcoming-details"><strong>Youth Leadership Conference</strong><span>May 16 - May 18 · Sunbird Nkopola Lodge</span></div>
                <span class="ecc-pill green">Starts in 2 days</span>
              </div>
              <div class="ecc-upcoming-item">
                <img src="https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=400&q=80" alt="Tech">
                <div class="ecc-upcoming-details"><strong>Tech Innovators Summit</strong><span>May 22 · Crossroads Hotel</span></div>
                <span class="ecc-pill blue">Starts in 8 days</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Schedule & Bookings Grid -->
        <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:1.25rem;">
          <div class="ecc-card">
            <div class="ecc-card-head"><h3>Today's Schedule</h3><a href="#cal" onclick="switchEccModule('events')" class="ecc-card-link">Full Schedule</a></div>
            <div class="ecc-timeline-list">
              <div class="ecc-timeline-item"><div class="ecc-timeline-node completed"></div><div><strong>Registration Opens</strong><small style="display:block;color:var(--ecc-text-dim);">08:00 AM · Main Entrance</small></div><span class="ecc-pill green">Completed</span></div>
              <div class="ecc-timeline-item"><div class="ecc-timeline-node live"></div><div><strong>Opening Ceremony</strong><small style="display:block;color:var(--ecc-text-dim);">09:00 AM · Main Hall</small></div><span class="ecc-pill rose">Live</span></div>
              <div class="ecc-timeline-item"><div class="ecc-timeline-node"></div><div><strong>Keynote Address</strong><small style="display:block;color:var(--ecc-text-dim);">10:30 AM · Hall A</small></div><span class="ecc-pill amber">Upcoming</span></div>
            </div>
          </div>

          <div class="ecc-card">
            <div class="ecc-card-head"><h3>Recent Bookings</h3><a href="#all" onclick="switchEccModule('attendees')" class="ecc-card-link">View All</a></div>
            <table class="ecc-table">
              <thead><tr><th>Attendee</th><th>Event</th><th>Ticket</th><th>Amount</th><th>Status</th></tr></thead>
              <tbody>
                <tr><td><strong>John Phiri</strong></td><td>Worship Concert</td><td>VIP</td><td>MK 25,000</td><td><span class="ecc-pill green">Paid</span></td></tr>
                <tr><td><strong>Grace Malunga</strong></td><td>Tech Summit</td><td>Standard</td><td>MK 10,000</td><td><span class="ecc-pill green">Paid</span></td></tr>
                <tr><td><strong>Mary Moyo</strong></td><td>Wedding Expo</td><td>VIP</td><td>MK 30,000</td><td><span class="ecc-pill amber">Pending</span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- MODULE 1: EVENTS GALLERY -->
      <div id="mod-events" class="ecc-module-content">
        <div class="ev-head">
          <div>
            <h2 class="ev-head-title">Events</h2>
            <p class="ev-head-sub">Create, manage and publish your events</p>
          </div>
          <div class="ev-head-actions">
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="eccNotify('Importing event catalogue...')">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <span>Import Event</span>
            </button>
            <button type="button" class="ecc-btn ecc-btn-primary" onclick="EventsWorkspace.openWizard()">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>Create Event</span>
            </button>
          </div>
        </div>

        <div id="ev-resume-draft" class="ev-resume-banner" style="display:none;">
          <div class="ev-resume-info">
            <strong>Continue your event</strong>
            <span id="ev-resume-title">&nbsp;</span>
            <small id="ev-resume-time"></small>
          </div>
          <div class="ev-resume-actions">
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="EventsWorkspace.dismissResume()">Dismiss</button>
            <button type="button" class="ecc-btn ecc-btn-primary" onclick="EventsWorkspace.resumeDraft()">Continue Editing</button>
          </div>
        </div>

        <div class="ev-filter-bar" id="ev-filter-bar">
          <?php foreach ([
            'all' => 'All', 'draft' => 'Drafts', 'pending' => 'Pending Review', 'published' => 'Published',
            'upcoming' => 'Upcoming', 'live' => 'Live', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'archived' => 'Archived',
          ] as $key => $label): ?>
            <button type="button" class="ev-filter-chip<?= $key === 'all' ? ' active' : '' ?>" data-filter="<?= e($key) ?>">
              <span><?= e($label) ?></span>
              <span class="ev-chip-count" id="ev-count-<?= e($key) ?>">0</span>
            </button>
          <?php endforeach; ?>
        </div>

        <div class="ev-toolbar">
          <div class="ecc-search-box ev-search-box">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
            <input type="text" id="ev-search-input" class="ecc-search-input" placeholder="Search events by name, ID, venue...">
          </div>
          
          <select id="ev-category-filter" class="ev-select">
            <option value="">Category: All Categories</option>
            <option value="Conferences">Conferences</option>
            <option value="Concerts & Gig">Concerts & Gig</option>
            <option value="Networking & Meetups">Networking & Meetups</option>
            <option value="Workshops & Masterclasses">Workshops & Masterclasses</option>
            <option value="Festivals & Fairs">Festivals & Fairs</option>
            <option value="Gala & Award Shows">Gala & Award Shows</option>
          </select>

          <select id="ev-date-filter" class="ev-select">
            <option value="">Date: All Dates</option>
            <option value="today">Today</option>
            <option value="this_week">This Week</option>
            <option value="this_month">This Month</option>
          </select>

          <select id="ev-status-filter" class="ev-select">
            <option value="">Status: All Statuses</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="upcoming">Upcoming</option>
            <option value="live">Live</option>
            <option value="completed">Completed</option>
          </select>

          <select id="ev-venue-filter" class="ev-select">
            <option value="">Venue: All Venues</option>
            <option value="Bingu International Convention Centre">Bingu International Convention Centre</option>
            <option value="Kamuzu Stadium">Kamuzu Stadium</option>
            <option value="Sunbird Mount Soche">Sunbird Mount Soche</option>
            <option value="Civo Stadium">Civo Stadium</option>
          </select>

          <button type="button" class="ev-filter-btn" onclick="eccNotify('Filters panel toggled')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            <span>Filters</span>
          </button>

          <div class="ev-view-toggle">
            <button type="button" class="ev-view-btn active" data-view="cards" title="Cards view">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
              <span>Cards</span>
            </button>
            <button type="button" class="ev-view-btn" data-view="table" title="Table view">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
              <span>Table</span>
            </button>
          </div>
        </div>

        <div id="events-workspace" class="ev-workspace" data-base-url="<?= e(BASE_URL) ?>" data-csrf="<?= e($_SESSION['csrf_token'] ?? '') ?>">
          <div class="ev-skeleton-grid">
            <?php for ($i = 0; $i < 4; $i++): ?>
              <div class="ev-skeleton-card"><div class="ev-skeleton-img"></div><div class="ev-skeleton-line w-70"></div><div class="ev-skeleton-line w-40"></div></div>
            <?php endfor; ?>
          </div>
        </div>

        <!-- Pagination Bar -->
        <div class="ev-pagination-bar" id="ev-pagination-bar">
          <div class="ev-pag-info" id="ev-pag-info">Showing 1 to 8 of 24 events</div>
          <div class="ev-pag-controls">
            <button type="button" class="ev-pag-btn" disabled>&lt;</button>
            <button type="button" class="ev-pag-btn active">1</button>
            <button type="button" class="ev-pag-btn">2</button>
            <button type="button" class="ev-pag-btn">3</button>
            <button type="button" class="ev-pag-btn">&gt;</button>
          </div>
        </div>
      </div>

      <!-- MODULE 2: TICKETS (TICKET COMMERCE & LIFECYCLE CONTROL CENTER) -->
      <div id="mod-tickets" class="ecc-module-content">

        <!-- Header & Event Context Bar -->
        <div class="ecc-tk-header-wrap">
          <div class="ecc-tk-header-title">
            <h2>Tickets</h2>
            <p>Create, price, distribute and manage tickets for your events.</p>
          </div>

          <!-- Event Context Selector (populated from live portfolio) -->
          <div class="ecc-tk-event-selector">
            <label>Event:</label>
            <select id="ecc-tk-event-select" onchange="TicketsControlCenter.switchEvent(this.value)">
              <option value="">Loading events…</option>
            </select>
            <span class="ecc-tk-event-meta" id="ecc-tk-event-meta">—</span>
          </div>

          <!-- Actions -->
          <div style="display:flex;gap:0.5rem;align-items:center;">
            <div style="position:relative;">
              <button type="button" class="ecc-btn ecc-btn-secondary" onclick="eccHdToggle('ecc-export-dropdown')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export ▾</span>
              </button>
              <div id="ecc-export-dropdown" class="acc-hd-pop" style="width:190px;right:0;">
                <div class="acc-hd-pop-hd"><b>Export Data</b></div>
                <div style="padding:0.4rem;">
                  <button type="button" class="acc-context-row-btn" onclick="TicketsControlCenter.exportData('inventory')">📦 Inventory CSV</button>
                  <button type="button" class="acc-context-row-btn" onclick="TicketsControlCenter.exportData('sales')">📊 Sales Report</button>
                  <button type="button" class="acc-context-row-btn" onclick="TicketsControlCenter.exportData('orders')">🧾 Orders Log</button>
                  <button type="button" class="acc-context-row-btn" onclick="TicketsControlCenter.exportData('issued')">👥 Ticket Holders</button>
                </div>
              </div>
            </div>

            <button type="button" class="ecc-btn ecc-btn-primary" onclick="TicketsControlCenter.newTicketType()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>+ Create Ticket Type</span>
            </button>
          </div>
        </div>

        <!-- KPI Command Strip (6 Stat Cards) -->
        <div class="ecc-tk-kpi-grid">
          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Tickets Sold</label>
              <div class="ecc-tk-kpi-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="tk-kpi-sold">—</div>
            <div class="ecc-tk-kpi-sub" id="tk-kpi-sold-sub"><span>of — total</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Gross Revenue</label>
              <div class="ecc-tk-kpi-icon" style="color:#10b981;background:rgba(16,185,129,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="tk-kpi-revenue" style="color:var(--ecc-primary);">—</div>
            <div class="ecc-tk-kpi-sub"><span id="tk-kpi-revenue-sub">Gross ticket sales</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Tickets Available</label>
              <div class="ecc-tk-kpi-icon" style="color:#3b82f6;background:rgba(59,130,246,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="tk-kpi-avail">—</div>
            <div class="ecc-tk-kpi-sub"><span id="tk-kpi-avail-sub">of active capacity</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Sell-through Rate</label>
              <div class="ecc-tk-kpi-icon" style="color:#f59e0b;background:rgba(245,158,11,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="tk-kpi-sellthrough">—</div>
            <div class="ecc-tk-kpi-sub"><span id="tk-kpi-sellthrough-sub">of total capacity sold</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Pending Orders</label>
              <div class="ecc-tk-kpi-icon" style="color:#8b5cf6;background:rgba(139,92,246,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="tk-kpi-pending">—</div>
            <div class="ecc-tk-kpi-sub"><span id="tk-kpi-pending-sub">awaiting payment</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Refunded</label>
              <div class="ecc-tk-kpi-icon" style="color:#ef4444;background:rgba(239,68,68,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="tk-kpi-refunded">—</div>
            <div class="ecc-tk-kpi-sub"><span id="tk-kpi-refunded-sub">tickets refunded</span></div>
          </div>
        </div>

        <!-- Subtabs Bar -->
        <div class="ecc-tk-subtabs">
          <button type="button" class="ecc-tk-subtab active" data-subtab="overview" onclick="TicketsControlCenter.switchSubtab('overview')">Overview</button>
          <button type="button" class="ecc-tk-subtab" data-subtab="types" onclick="TicketsControlCenter.switchSubtab('types')">Ticket Types</button>
          <button type="button" class="ecc-tk-subtab" data-subtab="orders" onclick="TicketsControlCenter.switchSubtab('orders')">Orders</button>
          <button type="button" class="ecc-tk-subtab" data-subtab="issued" onclick="TicketsControlCenter.switchSubtab('issued')">Issued Tickets</button>
          <button type="button" class="ecc-tk-subtab" data-subtab="transfers" onclick="TicketsControlCenter.switchSubtab('transfers')">Transfers</button>
          <button type="button" class="ecc-tk-subtab" data-subtab="refunds" onclick="TicketsControlCenter.switchSubtab('refunds')">Refunds</button>
        </div>

        <!-- SUBTAB 1: OVERVIEW -->
        <div id="ecc-tk-tab-overview" class="ecc-tk-tab-content active">

          <!-- Charts Section: Velocity Chart + Sales by Type Donut -->
          <div class="ecc-tk-charts-grid">

            <!-- Chart 1: Ticket Sales Over Time -->
            <div class="ecc-card">
              <div class="ecc-card-head" style="margin-bottom:0.75rem;">
                <div style="display:flex;align-items:center;gap:0.4rem;">
                  <h3 style="font-size:0.92rem;margin:0;">Ticket Sales Over Time</h3>
                  <span style="font-size:0.75rem;color:var(--ecc-text-dim);cursor:pointer;" title="Daily velocity chart showing ticket volume sold">ⓘ</span>
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                  <div style="display:flex;background:var(--ecc-surface-2);padding:0.15rem;border-radius:6px;font-size:0.7rem;">
                    <button type="button" class="ecc-btn ecc-btn-primary" style="padding:0.2rem 0.5rem;font-size:0.68rem;" onclick="TicketsControlCenter.setRange('7D',this)">7D</button>
                    <button type="button" class="ecc-btn ecc-btn-secondary" style="padding:0.2rem 0.5rem;font-size:0.68rem;border:none;" onclick="TicketsControlCenter.setRange('30D',this)">30D</button>
                    <button type="button" class="ecc-btn ecc-btn-secondary" style="padding:0.2rem 0.5rem;font-size:0.68rem;border:none;" onclick="TicketsControlCenter.setRange('90D',this)">90D</button>
                  </div>
                </div>
              </div>

              <div style="position:relative;height:210px;width:100%;margin-top:1rem;">
                <div id="tk-velocity-chart" class="ecc-tk-velocity">
                  <div class="ecc-tk-empty">Select an event to load the sales velocity chart.</div>
                </div>
                <div id="tk-velocity-labels" style="display:flex;justify-content:space-between;margin-top:0.4rem;font-size:0.68rem;color:var(--ecc-text-dim);font-weight:600;"></div>
              </div>
            </div>

            <!-- Chart 2: Sales by Ticket Type Donut Chart -->
            <div class="ecc-card">
              <div class="ecc-card-head" style="margin-bottom:0.5rem;">
                <h3 style="font-size:0.92rem;margin:0;">Sales by Ticket Type</h3>
              </div>

              <div style="display:flex;align-items:center;gap:1.5rem;margin-top:0.5rem;flex-wrap:wrap;">
                <div style="position:relative;width:150px;height:150px;flex-shrink:0;">
                  <svg width="150" height="150" viewBox="0 0 42 42" class="donut">
                    <circle class="donut-hole" cx="21" cy="21" r="15.91549430918954" fill="var(--ecc-surface)"></circle>
                    <circle class="donut-ring" cx="21" cy="21" r="15.91549430918954" fill="transparent" stroke="var(--ecc-surface-2)" stroke-width="6.5"></circle>
                    <g id="tk-donut-segments"></g>
                  </svg>
                  <div style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;">
                    <div style="font-size:1.15rem;font-weight:900;color:var(--ecc-text);line-height:1;" id="tk-donut-total">—</div>
                    <div style="font-size:0.62rem;color:var(--ecc-text-dim);font-weight:700;">Total Sold</div>
                  </div>
                </div>

                <div class="ecc-donut-legend" style="flex:1;" id="tk-donut-legend"></div>
              </div>
            </div>
          </div>

          <!-- Insights & Payment Channels -->
          <div class="ecc-tk-charts-grid" style="margin-bottom:1.25rem;">
            <div class="ecc-card">
              <div class="ecc-card-head" style="margin-bottom:0.5rem;">
                <h3 style="font-size:0.92rem;margin:0;">AI Insights</h3>
              </div>
              <div id="tk-insights" style="display:flex;flex-direction:column;gap:0.5rem;"></div>
            </div>
            <div class="ecc-card">
              <div class="ecc-card-head" style="margin-bottom:0.5rem;">
                <h3 style="font-size:0.92rem;margin:0;">Payment Channels</h3>
              </div>
              <div id="tk-channels" style="display:flex;flex-direction:column;gap:0.5rem;"></div>
            </div>
          </div>

          <!-- Ticket Types Table Section -->
          <div class="ecc-card">
            <div class="ecc-card-head" style="margin-bottom:0.75rem;">
              <h3 style="font-size:1rem;margin:0;">Ticket Types</h3>
              <div style="display:flex;gap:0.5rem;align-items:center;">
                <button type="button" class="ecc-btn ecc-btn-primary" onclick="TicketsControlCenter.newTicketType()">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  <span>+ Create Ticket Type</span>
                </button>
              </div>
            </div>

            <div style="overflow-x:auto;">
              <table class="ecc-table">
                <thead>
                  <tr>
                    <th>Ticket Type</th>
                    <th>Price</th>
                    <th style="min-width:140px;">Sold / Capacity</th>
                    <th>Available</th>
                    <th>Revenue</th>
                    <th>Check-ins</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody id="tk-types-table-body"></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- SUBTAB 2: TICKET TYPES -->
        <div id="ecc-tk-tab-types" class="ecc-tk-tab-content" style="display:none;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
            <input type="text" class="ecc-input" placeholder="Filter ticket types..." style="max-width:280px;" id="tk-types-filter" oninput="TicketsControlCenter.renderTypes()">
            <button type="button" class="ecc-btn ecc-btn-primary" onclick="TicketsControlCenter.newTicketType()">+ Create Ticket Type</button>
          </div>
          <div id="tk-types-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:1rem;"></div>
        </div>

        <!-- SUBTAB 3: ORDERS -->
        <div id="ecc-tk-tab-orders" class="ecc-tk-tab-content" style="display:none;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
            <input type="text" class="ecc-input" placeholder="Search orders by ID, name, email..." style="max-width:320px;" id="tk-orders-search" onkeyup="TicketsControlCenter.searchOrders(event)">
            <div style="display:flex;gap:0.4rem;" id="tk-orders-filters">
              <button class="ecc-btn ecc-btn-primary" style="padding:0.35rem 0.75rem;font-size:0.75rem;" data-filter="all" onclick="TicketsControlCenter.filterOrders('all',this)">All</button>
              <button class="ecc-btn ecc-btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.75rem;" data-filter="completed" onclick="TicketsControlCenter.filterOrders('completed',this)">Completed</button>
              <button class="ecc-btn ecc-btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.75rem;" data-filter="pending" onclick="TicketsControlCenter.filterOrders('pending',this)">Pending</button>
              <button class="ecc-btn ecc-btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.75rem;" data-filter="refunded" onclick="TicketsControlCenter.filterOrders('refunded',this)">Refunded</button>
              <button class="ecc-btn ecc-btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.75rem;" data-filter="failed" onclick="TicketsControlCenter.filterOrders('failed',this)">Failed</button>
            </div>
          </div>
          <div class="ecc-card">
            <table class="ecc-table">
              <thead><tr><th>Order ID</th><th>Customer</th><th>Tickets</th><th>Amount</th><th>Gateway</th><th>Payment</th><th>Issued</th><th>Date</th><th>Actions</th></tr></thead>
              <tbody id="tk-orders-body"></tbody>
            </table>
          </div>
        </div>

        <!-- SUBTAB 4: ISSUED TICKETS -->
        <div id="ecc-tk-tab-issued" class="ecc-tk-tab-content" style="display:none;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
            <input type="text" class="ecc-input" placeholder="Search Ticket ID, QR Token, Holder..." style="max-width:360px;" id="tk-issued-search" onkeyup="TicketsControlCenter.searchIssued(event)">
            <div style="display:flex;gap:0.4rem;" id="tk-issued-filters">
              <button class="ecc-btn ecc-btn-primary" style="padding:0.35rem 0.75rem;font-size:0.75rem;" data-status="all" onclick="TicketsControlCenter.filterIssued('all',this)">All</button>
              <button class="ecc-btn ecc-btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.75rem;" data-status="checked_in" onclick="TicketsControlCenter.filterIssued('checked_in',this)">Checked In</button>
              <button class="ecc-btn ecc-btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.75rem;" data-status="not_checked_in" onclick="TicketsControlCenter.filterIssued('not_checked_in',this)">Not Checked In</button>
            </div>
          </div>
          <div class="ecc-card">
            <table class="ecc-table">
              <thead><tr><th>Ticket ID</th><th>Ticket Type</th><th>Holder Name</th><th>Order Ref</th><th>Verification</th><th>Check-in Status</th><th>Actions</th></tr></thead>
              <tbody id="tk-issued-body"></tbody>
            </table>
          </div>
        </div>

        <!-- SUBTAB 5: TRANSFERS -->
        <div id="ecc-tk-tab-transfers" class="ecc-tk-tab-content" style="display:none;">
          <div class="ecc-card">
            <div class="ecc-card-head">
              <h3>Ticket Transfer Audit Trail</h3>
            </div>
            <table class="ecc-table">
              <thead><tr><th>Transfer ID</th><th>Ticket ID</th><th>Original Holder</th><th>New Holder</th><th>Date & Time</th><th>Initiated By</th><th>Status</th></tr></thead>
              <tbody id="tk-transfers-body"></tbody>
            </table>
          </div>
        </div>

        <!-- SUBTAB 6: REFUNDS -->
        <div id="ecc-tk-tab-refunds" class="ecc-tk-tab-content" style="display:none;">
          <div class="ecc-card">
            <div class="ecc-card-head">
              <h3>Refund Requests & Financial Reconciliation</h3>
            </div>
            <table class="ecc-table">
              <thead><tr><th>Refund ID</th><th>Order / Ticket</th><th>Customer</th><th>Amount</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody id="tk-refunds-body"></tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Ticket / Order Detail Side Drawer -->
      <div id="ecc-ticket-drawer" class="ecc-drawer-overlay" onclick="if(event.target===this)TicketsControlCenter.closeDrawer();">
        <div class="ecc-drawer-content">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid var(--ecc-border);padding-bottom:0.75rem;">
            <h3 style="margin:0;font-size:1.1rem;" id="drawer-title">Details</h3>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="TicketsControlCenter.closeDrawer()">✕</button>
          </div>
          <div id="drawer-body"><div class="ecc-tk-empty">Loading…</div></div>
        </div>
      </div>

      <!-- MODULE 3: ATTENDEES — Attendee Intelligence & Participant Management Center -->
      <div id="mod-attendees" class="ecc-module-content">

        <!-- Header & Event Context Bar -->
        <div class="ecc-tk-header-wrap">
          <div class="ecc-tk-header-title">
            <h2>Attendees</h2>
            <p>Who is attending, who has arrived and who is still expected — the live participant roster.</p>
          </div>

          <div class="ecc-tk-event-selector">
            <label>Event:</label>
            <select id="at-event-select" onchange="AttendeesControlCenter.switchEvent(this.value)">
              <option value="">Loading events…</option>
            </select>
            <span class="ecc-tk-event-meta" id="at-event-meta">—</span>
          </div>

          <div style="display:flex;gap:0.5rem;align-items:center;">
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="AttendeesControlCenter.openImportModal()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <span>Import</span>
            </button>

            <div style="position:relative;">
              <button type="button" class="ecc-btn ecc-btn-secondary" onclick="eccHdToggle('at-export-dropdown')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span>Export ▾</span>
              </button>
              <div id="at-export-dropdown" class="acc-hd-pop" style="width:200px;right:0;">
                <div class="acc-hd-pop-hd"><b>Export Attendees</b></div>
                <div style="padding:0.4rem;">
                  <button type="button" class="acc-context-row-btn" onclick="AttendeesControlCenter.exportData('all')">📇 All Attendees CSV</button>
                  <button type="button" class="acc-context-row-btn" onclick="AttendeesControlCenter.exportData('checked_in')">✅ Checked In CSV</button>
                  <button type="button" class="acc-context-row-btn" onclick="AttendeesControlCenter.exportData('not_arrived')">⏳ Not Arrived CSV</button>
                  <button type="button" class="acc-context-row-btn" onclick="AttendeesControlCenter.exportData('vip')">👑 VIP Guests CSV</button>
                </div>
              </div>
            </div>

            <button type="button" class="ecc-btn ecc-btn-primary" onclick="AttendeesControlCenter.openAddModal()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              <span>+ Add Attendee</span>
            </button>
          </div>
        </div>

        <!-- KPI Command Strip (6 Stat Cards) -->
        <div class="ecc-tk-kpi-grid">
          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Total Attendees</label>
              <div class="ecc-tk-kpi-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="at-kpi-total">—</div>
            <div class="ecc-tk-kpi-sub"><span>active tickets</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Checked In</label>
              <div class="ecc-tk-kpi-icon" style="color:#10b981;background:rgba(16,185,129,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="at-kpi-checked" style="color:var(--ecc-primary);">—</div>
            <div class="ecc-tk-kpi-sub"><span id="at-kpi-checked-sub">have arrived</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Expected</label>
              <div class="ecc-tk-kpi-icon" style="color:#3b82f6;background:rgba(59,130,246,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="at-kpi-expected">—</div>
            <div class="ecc-tk-kpi-sub"><span>tickets in circulation</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Not Arrived</label>
              <div class="ecc-tk-kpi-icon" style="color:#f59e0b;background:rgba(245,158,11,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="at-kpi-notarrived" style="color:#f59e0b;">—</div>
            <div class="ecc-tk-kpi-sub"><span>still expected</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>Cancelled / Refunded</label>
              <div class="ecc-tk-kpi-icon" style="color:#ef4444;background:rgba(239,68,68,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="at-kpi-cancelled">—</div>
            <div class="ecc-tk-kpi-sub"><span>removed from expected</span></div>
          </div>

          <div class="ecc-tk-kpi-card">
            <div class="ecc-tk-kpi-head">
              <label>VIP Guests</label>
              <div class="ecc-tk-kpi-icon" style="color:#8b5cf6;background:rgba(139,92,246,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>
            </div>
            <div class="ecc-tk-kpi-val" id="at-kpi-vip" style="color:#8b5cf6;">—</div>
            <div class="ecc-tk-kpi-sub"><span>premium ticket holders</span></div>
          </div>
        </div>

        <!-- Attendance Progress + Arrival Velocity -->
        <div class="ecc-tk-charts-grid" style="margin-bottom:1.25rem;">
          <div class="ecc-card">
            <div class="ecc-card-head" style="margin-bottom:0.6rem;">
              <h3 style="font-size:0.92rem;margin:0;">Attendance Progress</h3>
            </div>
            <div style="height:12px;background:var(--ecc-surface-2);border-radius:999px;overflow:hidden;">
              <div id="at-progress-fill" style="height:100%;width:0%;background:linear-gradient(90deg,#10b981,#3b82f6);border-radius:999px;transition:width .5s ease;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:0.4rem;font-size:0.72rem;color:var(--ecc-text-dim);font-weight:600;">
              <span id="at-progress-label">0 of 0 expected attendees have arrived</span>
              <span id="at-progress-rate">0%</span>
            </div>
          </div>

          <div class="ecc-card">
            <div class="ecc-card-head" style="margin-bottom:0.6rem;">
              <h3 style="font-size:0.92rem;margin:0;">Arrival Velocity</h3>
            </div>
            <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-bottom:0.6rem;">
              <div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.4rem 0.7rem;text-align:center;min-width:70px;">
                <div style="font-size:0.95rem;font-weight:900;" id="at-arr-15">—</div>
                <div style="font-size:0.6rem;color:var(--ecc-text-dim);font-weight:700;">LAST 15 MIN</div>
              </div>
              <div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.4rem 0.7rem;text-align:center;min-width:70px;">
                <div style="font-size:0.95rem;font-weight:900;" id="at-arr-60">—</div>
                <div style="font-size:0.6rem;color:var(--ecc-text-dim);font-weight:700;">LAST 60 MIN</div>
              </div>
              <div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.4rem 0.7rem;text-align:center;min-width:70px;">
                <div style="font-size:0.95rem;font-weight:900;color:#8b5cf6;" id="at-arr-peak">—</div>
                <div style="font-size:0.6rem;color:var(--ecc-text-dim);font-weight:700;">PEAK / MINUTE</div>
              </div>
            </div>
            <div id="at-arr-curve" style="display:flex;align-items:flex-end;gap:0.3rem;height:70px;overflow-x:auto;"></div>
          </div>
        </div>

        <!-- Analytics: By Type + By Gate -->
        <div class="ecc-tk-charts-grid" style="margin-bottom:1.25rem;">
          <div class="ecc-card">
            <div class="ecc-card-head" style="margin-bottom:0.5rem;">
              <h3 style="font-size:0.92rem;margin:0;">Attendance by Ticket Type</h3>
            </div>
            <div id="at-bytype-chart" style="display:flex;flex-direction:column;gap:0.6rem;"></div>
          </div>
          <div class="ecc-card">
            <div class="ecc-card-head" style="margin-bottom:0.5rem;">
              <h3 style="font-size:0.92rem;margin:0;">Arrivals by Gate</h3>
            </div>
            <div id="at-bygate-chart" style="display:flex;flex-direction:column;gap:0.6rem;"></div>
          </div>
        </div>

        <!-- Insights + Live Feed -->
        <div class="ecc-tk-charts-grid" style="margin-bottom:1.25rem;">
          <div class="ecc-card">
            <div class="ecc-card-head" style="margin-bottom:0.5rem;">
              <h3 style="font-size:0.92rem;margin:0;">AI Insights</h3>
            </div>
            <div id="at-insights" style="display:flex;flex-direction:column;gap:0.5rem;"></div>
          </div>
          <div class="ecc-card">
            <div class="ecc-card-head" style="margin-bottom:0.5rem;">
              <h3 style="font-size:0.92rem;margin:0;">Live Check-In Feed</h3>
              <span style="font-size:0.66rem;color:var(--ecc-text-dim);font-weight:700;">LIVE · auto-refresh</span>
            </div>
            <div id="at-live-feed" style="display:flex;flex-direction:column;gap:0.4rem;max-height:230px;overflow-y:auto;"></div>
          </div>
        </div>

        <!-- Attendee Directory -->
        <div class="ecc-card">
          <div class="ecc-card-head" style="margin-bottom:0.6rem;">
            <h3 style="font-size:1rem;margin:0;">Attendee Directory</h3>
            <span style="font-size:0.72rem;color:var(--ecc-text-dim);font-weight:700;" id="at-count-label">—</span>
          </div>

          <!-- Filter Bar -->
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;margin-bottom:0.6rem;">
            <input type="text" class="ecc-search-input" id="at-search" placeholder="Search name, email, phone, ticket, QR, booking, organization..." style="flex:1;min-width:280px;" onkeyup="AttendeesControlCenter.searchInput(event)">
            <select class="ecc-input" id="at-filter-type" style="max-width:170px;" onchange="AttendeesControlCenter.applyFilter('typeId',this.value)">
              <option value="0">All Ticket Types</option>
            </select>
            <select class="ecc-input" id="at-filter-attendance" style="max-width:160px;" onchange="AttendeesControlCenter.applyFilter('attendance',this.value)">
              <option value="">All Attendance</option>
              <option value="expected">Expected</option>
              <option value="checked_in">Checked In</option>
              <option value="not_arrived">Not Arrived</option>
              <option value="cancelled">Cancelled</option>
              <option value="refunded">Refunded</option>
              <option value="exited">Exited</option>
            </select>
            <select class="ecc-input" id="at-filter-payment" style="max-width:150px;" onchange="AttendeesControlCenter.applyFilter('payment',this.value)">
              <option value="">All Payment</option>
              <option value="paid">Paid</option>
              <option value="pending">Pending</option>
              <option value="failed">Failed</option>
              <option value="refunded">Refunded</option>
              <option value="comp">Complimentary</option>
            </select>
            <select class="ecc-input" id="at-filter-since" style="max-width:130px;" onchange="AttendeesControlCenter.applyFilter('since',this.value)">
              <option value="">All Time</option>
              <option value="today">Today</option>
              <option value="7d">Last 7 Days</option>
              <option value="30d">Last 30 Days</option>
            </select>
            <select class="ecc-input" id="at-filter-zone" style="max-width:140px;" onchange="AttendeesControlCenter.applyFilter('zone',this.value)">
              <option value="">All Access Zones</option>
            </select>
            <select class="ecc-input" id="at-filter-org" style="max-width:160px;" onchange="AttendeesControlCenter.applyFilter('org',this.value)">
              <option value="">All Organizations</option>
            </select>
            <button type="button" class="ecc-btn ecc-btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.75rem;" onclick="AttendeesControlCenter.resetFilters()">Reset</button>
          </div>

          <!-- Bulk Action Bar -->
          <div id="at-bulk-bar" style="display:none;align-items:center;gap:0.6rem;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);border-radius:8px;padding:0.5rem 0.7rem;margin-bottom:0.6rem;">
            <strong style="font-size:0.78rem;" id="at-bulk-count">0 selected</strong>
            <button type="button" class="ecc-btn ecc-btn-primary" style="padding:0.3rem 0.7rem;font-size:0.74rem;" onclick="AttendeesControlCenter.bulkCheckIn()">✅ Check In Selected</button>
            <button type="button" class="ecc-btn ecc-btn-secondary" style="padding:0.3rem 0.7rem;font-size:0.74rem;" onclick="AttendeesControlCenter.openMessageModal()">✉ Message</button>
            <button type="button" class="ecc-btn ecc-btn-secondary" style="padding:0.3rem 0.7rem;font-size:0.74rem;" onclick="AttendeesControlCenter.bulkExport()">⬇ Export Selected</button>
            <button type="button" class="ecc-btn ecc-btn-secondary" style="padding:0.3rem 0.7rem;font-size:0.74rem;" onclick="AttendeesControlCenter.clearSelection()">Clear</button>
          </div>

          <table class="ecc-table">
            <thead>
              <tr>
                <th style="width:34px;"><input type="checkbox" id="at-checkall" onchange="AttendeesControlCenter.toggleSelectAll(this.checked)"></th>
                <th>Attendee</th>
                <th>Contact</th>
                <th>Ticket</th>
                <th>Payment</th>
                <th>Attendance</th>
                <th>Registered</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody id="at-table-body">
              <tr><td colspan="8"><div class="ecc-tk-empty">Select an event to load the attendee directory.</div></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Attendee Detail Side Drawer -->
      <div id="ecc-attendee-drawer" class="ecc-drawer-overlay" onclick="if(event.target===this)AttendeesControlCenter.closeDrawer();">
        <div class="ecc-drawer-content">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid var(--ecc-border);padding-bottom:0.75rem;">
            <h3 style="margin:0;font-size:1.1rem;">Attendee Profile</h3>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="AttendeesControlCenter.closeDrawer()">✕</button>
          </div>
          <div id="at-drawer-body"><div class="ecc-tk-empty">Loading…</div></div>
        </div>
      </div>

      <!-- Modal: Add Attendee -->
      <div id="modal-add-attendee" class="ecc-modal-overlay">
        <div class="ecc-modal-content" style="max-width:560px;padding:1.75rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid var(--ecc-border);padding-bottom:0.75rem;">
            <div>
              <h3 style="margin:0;font-size:1.2rem;font-weight:900;">Add Attendee</h3>
              <p style="margin:0.2rem 0 0 0;font-size:0.75rem;color:var(--ecc-text-dim);">Register an attendee manually — a ticket is issued immediately.</p>
            </div>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-add-attendee')">✕</button>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.9rem;">
            <div style="grid-column:1 / -1;">
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Full Name *</label>
              <input class="ecc-input" id="at-add-name" style="width:100%;" placeholder="e.g. Chimwemwe Banda">
            </div>
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Email</label>
              <input class="ecc-input" id="at-add-email" style="width:100%;" placeholder="name@example.com">
            </div>
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Phone</label>
              <input class="ecc-input" id="at-add-phone" style="width:100%;" placeholder="+265 99 000 0000">
            </div>
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Ticket Type *</label>
              <select class="ecc-input" id="at-add-type" style="width:100%;"></select>
            </div>
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Payment Status</label>
              <select class="ecc-input" id="at-add-payment" style="width:100%;" onchange="AttendeesControlCenter.toggleAddPaymentFields()">
                <option value="Paid">Paid</option>
                <option value="Pending">Pending</option>
                <option value="Complimentary">Complimentary</option>
              </select>
            </div>
            <div id="at-add-amount-wrap">
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Amount (MWK)</label>
              <input class="ecc-input" id="at-add-amount" type="number" min="0" step="100" style="width:100%;" placeholder="Ticket price">
            </div>
            <div id="at-add-reason-wrap" style="display:none;">
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Complimentary Reason *</label>
              <select class="ecc-input" id="at-add-reason" style="width:100%;">
                <option value="">Select reason…</option>
                <option value="Sponsor">Sponsor</option>
                <option value="Media">Media</option>
                <option value="Staff">Staff</option>
                <option value="Guest">Guest</option>
                <option value="Partner">Partner</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div style="grid-column:1 / -1;">
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Organization</label>
              <input class="ecc-input" id="at-add-org" style="width:100%;" placeholder="Company / institution (optional)">
            </div>
          </div>
          <div style="display:flex;gap:0.6rem;margin-top:1.5rem;">
            <button type="button" class="ecc-btn ecc-btn-primary" style="flex:1;" onclick="AttendeesControlCenter.submitAdd()">Register Attendee</button>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-add-attendee')">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Modal: Import Attendees -->
      <div id="modal-import-attendees" class="ecc-modal-overlay">
        <div class="ecc-modal-content" style="max-width:640px;padding:1.75rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid var(--ecc-border);padding-bottom:0.75rem;">
            <div>
              <h3 style="margin:0;font-size:1.2rem;font-weight:900;">Import Attendees</h3>
              <p style="margin:0.2rem 0 0 0;font-size:0.75rem;color:var(--ecc-text-dim);">Paste rows as <code>Name, Email, Phone, Ticket Type</code> — one attendee per line (or a JSON array).</p>
            </div>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-import-attendees')">✕</button>
          </div>
          <textarea class="ecc-input" id="at-import-data" rows="10" style="width:100%;font-family:monospace;font-size:0.75rem;" placeholder="Chimwemwe Banda, chimwemwe@example.com, +265 99 000 0000, Regular&#10;Thoko Gondwe, thoko@example.com, , Early Bird"></textarea>
          <p style="font-size:0.7rem;color:var(--ecc-text-dim);margin:0.4rem 0 0 0;">Duplicates are skipped and reported — nothing is imported silently. Maximum 200 rows per import.</p>
          <div id="at-import-result" style="margin-top:0.8rem;display:none;"></div>
          <div style="display:flex;gap:0.6rem;margin-top:1.25rem;">
            <button type="button" class="ecc-btn ecc-btn-primary" style="flex:1;" onclick="AttendeesControlCenter.submitImport()">Import & Validate</button>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-import-attendees')">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Modal: Message Attendees -->
      <div id="modal-message-attendees" class="ecc-modal-overlay">
        <div class="ecc-modal-content" style="max-width:540px;padding:1.75rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid var(--ecc-border);padding-bottom:0.75rem;">
            <div>
              <h3 style="margin:0;font-size:1.2rem;font-weight:900;">Message Attendees</h3>
              <p style="margin:0.2rem 0 0 0;font-size:0.75rem;color:var(--ecc-text-dim);" id="at-msg-targets">Sending to 0 attendees.</p>
            </div>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-message-attendees')">✕</button>
          </div>
          <div style="margin-bottom:0.9rem;">
            <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Subject *</label>
            <input class="ecc-input" id="at-msg-subject" style="width:100%;" placeholder="e.g. Event day updates & arrival instructions">
          </div>
          <div style="margin-bottom:0.9rem;">
            <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Message</label>
            <textarea class="ecc-input" id="at-msg-body" rows="5" style="width:100%;" placeholder="Write your message… (delivered via the Uthenga Messages hub)"></textarea>
          </div>
          <div style="display:flex;gap:0.6rem;">
            <button type="button" class="ecc-btn ecc-btn-primary" style="flex:1;" onclick="AttendeesControlCenter.submitMessage()">Send Message</button>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-message-attendees')">Cancel</button>
          </div>
        </div>
      </div>

      <!-- MODULE 4: CHECK-IN LIVE — Operational Command Center -->
      <div id="mod-check-in" class="ecc-module-content">

        <!-- Offline banner (JS-driven) -->
        <div id="ci-offline-banner" style="display:none;background:#7c2d12;border:1px solid #ea580c;color:#fdba74;border-radius:10px;padding:0.7rem 1rem;margin-bottom:1rem;font-size:0.8rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
          <span>OFFLINE MODE — internet connection unavailable. Scans are queued locally. <span id="ci-queue-count"></span></span>
        </div>
        <div id="ci-sync-banner" style="display:none;background:var(--ecc-green-light);border:1px solid var(--ecc-green);color:var(--ecc-green);border-radius:10px;padding:0.7rem 1rem;margin-bottom:1rem;font-size:0.8rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;">
          <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          <span>SYNCING — <span id="ci-sync-progress">0 / 0</span> records synchronized. Back online.</span>
        </div>

        <!-- Header: operational context -->
        <div class="ecc-tk-header-wrap">
          <div class="ecc-tk-header-title">
            <div style="display:flex;align-items:center;gap:0.5rem;">
              <h2 style="margin:0;">Check-In LIVE</h2>
              <span class="ecc-ci-live-dot" id="ci-live-dot" title="Live event">LIVE</span>
            </div>
            <p style="margin:0.2rem 0 0 0;" id="ci-event-line">Scan → Validate → Decide → Admit → Record → Monitor</p>
          </div>

          <div class="ecc-tk-event-selector" style="flex-wrap:wrap;">
            <label>Event:</label>
            <select id="ci-event-select" onchange="CheckInControlCenter.switchEvent(this.value)">
              <option value="">Loading events…</option>
            </select>
            <label>Gate:</label>
            <select id="ci-gate-select" onchange="CheckInControlCenter.setGate(this.value)">
              <option value="Gate A">Gate A</option>
            </select>
            <span class="ecc-pill purple" id="ci-device-badge" title="Click to regenerate device identity" style="cursor:pointer;font-size:0.62rem;" onclick="CheckInControlCenter.newDeviceId()">DEV: …</span>
          </div>

          <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <span class="ecc-pill ci-conn-pill" id="ci-conn-status" style="background:rgba(16,185,129,0.1);color:#10b981;font-size:0.62rem;display:inline-flex;align-items:center;gap:0.3rem;"><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg><span id="ci-conn-text">Connected</span></span>
            <span class="ecc-pill amber" style="font-size:0.62rem;display:inline-flex;align-items:center;gap:0.3rem;" id="ci-operator-badge"><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg><span id="ci-operator-text">Operator</span></span>
            <div style="display:flex;background:var(--ecc-surface-2);padding:0.15rem;border-radius:6px;font-size:0.7rem;">
              <button type="button" class="ecc-btn ecc-btn-primary ci-icon-btn" style="padding:0.2rem 0.55rem;font-size:0.68rem;" id="ci-mode-op" onclick="CheckInControlCenter.setMode('operator')"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Operator</button>
              <button type="button" class="ecc-btn ecc-btn-secondary ci-icon-btn" style="padding:0.2rem 0.55rem;font-size:0.68rem;border:none;" id="ci-mode-cmd" onclick="CheckInControlCenter.setMode('command')"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Command</button>
            </div>
            <button type="button" class="ecc-btn ecc-btn-secondary ci-icon-btn" style="padding:0.3rem 0.7rem;font-size:0.74rem;" onclick="CheckInControlCenter.openLookup()"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Manual Lookup</button>
          </div>
        </div>

        <!-- MODE: OPERATOR -->
        <div id="ci-view-operator" style="display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:1.25rem;">

          <!-- Scanner panel -->
          <div class="ecc-card" style="position:relative;">
            <div class="ecc-card-head" style="margin-bottom:0.75rem;">
              <h3 style="font-size:0.95rem;margin:0;">Scanner</h3>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);font-weight:700;" id="ci-scan-hint">Position ticket QR in frame or type the ticket ID</span>
            </div>

            <div class="ecc-ci-scanner-frame" id="ci-scanner-frame">
              <div class="ecc-ci-scanner-corner tl"></div>
              <div class="ecc-ci-scanner-corner tr"></div>
              <div class="ecc-ci-scanner-corner bl"></div>
              <div class="ecc-ci-scanner-corner br"></div>
              <video id="ci-cam-video" class="ecc-ci-cam-video" playsinline muted></video>
              <div class="ecc-ci-scanline" id="ci-scanline"></div>
              <div id="ci-scanner-placeholder" style="text-align:center;">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="color:var(--ecc-text-dim);"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="7" y="7" width="4" height="4"/><rect x="13" y="7" width="4" height="4"/><rect x="7" y="13" width="4" height="4"/><rect x="13" y="13" width="4" height="4"/></svg>
                <div style="font-size:0.85rem;font-weight:900;margin-top:0.6rem;" id="ci-scan-title">SCAN TICKET</div>
                <div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-top:0.2rem;">Camera QR auto-detect active · or enter ticket ID manually</div>
                <button type="button" class="ecc-btn ecc-btn-secondary" style="margin-top:0.6rem;font-size:0.72rem;padding:0.25rem 0.65rem;" onclick="CheckInControlCenter.scanDemoTicket()"><i class="fas fa-qrcode"></i> Test Scan Active Ticket</button>
              </div>
              <div id="ci-cam-error" class="ecc-ci-cam-error"></div>
            </div>

            <div style="display:flex;gap:0.5rem;margin-top:0.9rem;">
              <input type="text" id="ci-scan-input" class="ecc-input" style="flex:1;font-family:monospace;font-size:1rem;padding:0.7rem 0.8rem;" placeholder="Ticket ID or QR payload (UTH-… / UTHENGA|… / token)" autocomplete="off">
              <button type="button" class="ecc-btn ecc-btn-primary ci-icon-btn" style="font-size:0.85rem;padding:0 1.2rem;" onclick="CheckInControlCenter.scanInput()"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>SCAN</button>
              <button type="button" class="ecc-btn ecc-btn-secondary ci-icon-btn" id="ci-cam-btn" style="padding:0 1rem;font-size:0.8rem;" onclick="CheckInControlCenter.toggleCamera()"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>Camera</button>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:0.5rem;font-size:0.72rem;color:var(--ecc-text-dim);font-weight:600;">
              <span id="ci-operator-line">Operator: —</span>
              <span id="ci-device-line">Device: —</span>
            </div>
          </div>

          <!-- Counter panel -->
          <div style="display:flex;flex-direction:column;gap:1rem;">
            <div class="ecc-ci-counter-big">
              <div class="ecc-ci-counter-val" id="ci-kpi-checked">—</div>
              <div class="ecc-ci-counter-label">CHECKED IN</div>
              <div class="ecc-ci-counter-sub" id="ci-kpi-checked-rate">—</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
              <div class="ecc-ci-counter-sm">
                <div class="ecc-ci-counter-val" id="ci-kpi-expected">—</div>
                <div class="ecc-ci-counter-label">EXPECTED</div>
              </div>
              <div class="ecc-ci-counter-sm">
                <div class="ecc-ci-counter-val" id="ci-kpi-remaining">—</div>
                <div class="ecc-ci-counter-label">REMAINING</div>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
              <div class="ecc-ci-counter-sm">
                <div class="ecc-ci-counter-val" id="ci-kpi-today">—</div>
                <div class="ecc-ci-counter-label">TODAY</div>
              </div>
              <div class="ecc-ci-counter-sm">
                <div class="ecc-ci-counter-val" id="ci-kpi-last15">—</div>
                <div class="ecc-ci-counter-label">LAST 15 MIN</div>
              </div>
            </div>
            <div class="ecc-card" style="padding:0.8rem 1rem;">
              <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.4rem;">
                <strong>ARRIVAL RATE</strong>
                <span style="color:var(--ecc-text-dim);font-size:0.72rem;">Peak: <strong id="ci-rate-peak">—</strong>/min</span>
              </div>
              <div style="font-size:1.6rem;font-weight:900;color:var(--ecc-primary);" id="ci-rate-min">—</div>
              <div style="font-size:0.68rem;color:var(--ecc-text-dim);font-weight:700;">people per minute</div>
              <div style="height:8px;background:var(--ecc-surface-2);border-radius:999px;overflow:hidden;margin-top:0.5rem;">
                <div id="ci-arrival-fill" style="height:100%;width:0%;background:linear-gradient(90deg,#10b981,#3b82f6);border-radius:999px;transition:width .5s ease;"></div>
              </div>
              <div style="display:flex;justify-content:space-between;margin-top:0.3rem;font-size:0.68rem;color:var(--ecc-text-dim);font-weight:700;">
                <span id="ci-arrival-label">—</span>
                <span>64.7% &rarr; expected</span>
              </div>
            </div>
          </div>
        </div>

        <!-- MODE: COMMAND -->
        <div id="ci-view-command" style="display:none;flex-direction:column;gap:1.25rem;">

          <!-- Command counters -->
          <div class="ecc-tk-kpi-grid">
            <div class="ecc-tk-kpi-card">
              <div class="ecc-tk-kpi-head"><label>Checked In</label><div class="ecc-tk-kpi-icon" style="color:#10b981;background:rgba(16,185,129,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div></div>
              <div class="ecc-tk-kpi-val" id="cmd-kpi-checked" style="color:var(--ecc-primary);">—</div>
              <div class="ecc-tk-kpi-sub"><span id="cmd-kpi-checked-sub">—</span></div>
            </div>
            <div class="ecc-tk-kpi-card">
              <div class="ecc-tk-kpi-head"><label>Expected</label><div class="ecc-tk-kpi-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div></div>
              <div class="ecc-tk-kpi-val" id="cmd-kpi-expected">—</div>
              <div class="ecc-tk-kpi-sub"><span>tickets in circulation</span></div>
            </div>
            <div class="ecc-tk-kpi-card">
              <div class="ecc-tk-kpi-head"><label>Remaining</label><div class="ecc-tk-kpi-icon" style="color:#f59e0b;background:rgba(245,158,11,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
              <div class="ecc-tk-kpi-val" id="cmd-kpi-remaining" style="color:#f59e0b;">—</div>
              <div class="ecc-tk-kpi-sub"><span>still expected</span></div>
            </div>
            <div class="ecc-tk-kpi-card">
              <div class="ecc-tk-kpi-head"><label>Attendance</label><div class="ecc-tk-kpi-icon" style="color:#3b82f6;background:rgba(59,130,246,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div></div>
              <div class="ecc-tk-kpi-val" id="cmd-kpi-rate">—</div>
              <div class="ecc-tk-kpi-sub"><span>of expected arrived</span></div>
            </div>
            <div class="ecc-tk-kpi-card">
              <div class="ecc-tk-kpi-head"><label>Arrival Rate</label><div class="ecc-tk-kpi-icon" style="color:#8b5cf6;background:rgba(139,92,246,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div></div>
              <div class="ecc-tk-kpi-val" id="cmd-kpi-ratepermin" style="color:#8b5cf6;">—</div>
              <div class="ecc-tk-kpi-sub"><span id="cmd-kpi-ratepermin-sub">people / min</span></div>
            </div>
            <div class="ecc-tk-kpi-card">
              <div class="ecc-tk-kpi-head"><label>Scans (30m)</label><div class="ecc-tk-kpi-icon" style="color:#ef4444;background:rgba(239,68,68,0.1);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div></div>
              <div class="ecc-tk-kpi-val" id="cmd-kpi-scans">—</div>
              <div class="ecc-tk-kpi-sub"><span id="cmd-kpi-scans-sub">0% rejected</span></div>
            </div>
          </div>

          <!-- Gates + Activity -->
          <div class="ecc-tk-charts-grid">
            <div class="ecc-card">
              <div class="ecc-card-head" style="margin-bottom:0.5rem;">
                <h3 style="font-size:0.92rem;margin:0;">GATE STATUS</h3>
                <span style="font-size:0.68rem;color:var(--ecc-text-dim);font-weight:700;" id="cmd-gates-legend">live per gate</span>
              </div>
              <div id="cmd-gates-list" style="display:flex;flex-direction:column;gap:0.6rem;"></div>
            </div>
            <div class="ecc-card">
              <div class="ecc-card-head" style="margin-bottom:0.5rem;">
                <h3 style="font-size:0.92rem;margin:0;">LIVE ACTIVITY</h3>
                <span style="font-size:0.66rem;color:var(--ecc-text-dim);font-weight:700;">auto-refresh</span>
              </div>
              <div id="cmd-activity-list" style="display:flex;flex-direction:column;gap:0.4rem;max-height:300px;overflow-y:auto;"></div>
            </div>
          </div>

          <!-- Devices + AI Insights -->
          <div class="ecc-tk-charts-grid">
            <div class="ecc-card">
              <div class="ecc-card-head" style="margin-bottom:0.5rem;">
                <h3 style="font-size:0.92rem;margin:0;">Scanner Devices (24h)</h3>
              </div>
              <div id="cmd-devices-list" style="display:flex;flex-direction:column;gap:0.5rem;"></div>
            </div>
            <div class="ecc-card">
              <div class="ecc-card-head" style="margin-bottom:0.5rem;">
                <h3 style="font-size:0.92rem;margin:0;display:flex;align-items:center;gap:0.4rem;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/></svg>Event Operations AI</h3>
              </div>
              <div id="cmd-insights" style="display:flex;flex-direction:column;gap:0.5rem;"></div>
            </div>
          </div>

          <!-- Audit log -->
          <div class="ecc-card">
            <div class="ecc-card-head" style="margin-bottom:0.6rem;">
              <h3 style="font-size:1rem;margin:0;">Scan Audit Log</h3>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);font-weight:700;">immutable operational record</span>
            </div>
            <div style="max-height:340px;overflow-y:auto;">
              <table class="ecc-table">
                <thead><tr><th>Time</th><th>Decision</th><th>Ticket</th><th>Reason</th><th>Gate</th><th>Device</th><th>Operator</th><th>Source</th></tr></thead>
                <tbody id="cmd-audit-body"><tr><td colspan="8"><div class="ecc-tk-empty">No scans recorded yet.</div></td></tr></tbody>
              </table>
            </div>
          </div>

          <!-- End-of-event report -->
          <div id="cmd-final-report" style="display:none;">
            <div class="ecc-card" style="background:linear-gradient(135deg,var(--ecc-surface-2),var(--ecc-surface));">
              <h3 style="font-size:1rem;margin:0 0 0.4rem 0;">EVENT CHECK-IN CLOSED — Final Attendance</h3>
              <div id="cmd-final-report-body"></div>
            </div>
          </div>
        </div>

        <!-- Decision overlay (covers scanner) -->
        <div id="ci-decision-card" class="ecc-ci-decision" style="display:none;">
          <div id="ci-decision-body"></div>
        </div>
      </div>

      <!-- Modal: Manual Lookup -->
      <div id="modal-ci-lookup" class="ecc-modal-overlay">
        <div class="ecc-modal-content" style="max-width:640px;padding:1.75rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid var(--ecc-border);padding-bottom:0.75rem;">
            <div>
              <h3 style="margin:0;font-size:1.2rem;font-weight:900;">Manual Lookup</h3>
              <p style="margin:0.2rem 0 0 0;font-size:0.75rem;color:var(--ecc-text-dim);">Search by ticket ID, name, email, phone or booking ID.</p>
            </div>
            <button type="button" class="ecc-btn ecc-btn-secondary ci-icon-btn" style="width:30px;height:30px;padding:0;" onclick="closeEccModal('modal-ci-lookup')" title="Close"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
          <input type="text" id="ci-lookup-search" class="ecc-input" style="width:100%;" placeholder="Search ticket, name, phone or order ID…" onkeyup="CheckInControlCenter.lookupSearch(event)">
          <div id="ci-lookup-results" style="margin-top:1rem;display:flex;flex-direction:column;gap:0.5rem;">
            <div class="ecc-tk-empty">Type at least 2 characters to search.</div>
          </div>
        </div>
      </div>

      <!-- Modal: Supervisor Override -->
      <div id="modal-ci-override" class="ecc-modal-overlay">
        <div class="ecc-modal-content" style="max-width:520px;padding:1.75rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid var(--ecc-border);padding-bottom:0.75rem;">
            <div>
              <h3 style="margin:0;font-size:1.2rem;font-weight:900;">Supervisor Override</h3>
              <p style="margin:0.2rem 0 0 0;font-size:0.75rem;color:var(--ecc-text-dim);">Requires explicit reason — recorded in the immutable audit trail.</p>
            </div>
            <button type="button" class="ecc-btn ecc-btn-secondary ci-icon-btn" style="width:30px;height:30px;padding:0;" onclick="closeEccModal('modal-ci-override')" title="Close"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
          </div>
          <div id="ci-override-info" style="font-size:0.8rem;background:var(--ecc-surface-2);border-radius:8px;padding:0.7rem 0.9rem;margin-bottom:0.9rem;"></div>
          <div style="margin-bottom:0.9rem;">
            <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Override reason *</label>
            <textarea class="ecc-input" id="ci-override-reason" rows="3" style="width:100%;" placeholder="e.g. Attendee lost first ticket; ID verified by supervisor at the gate"></textarea>
          </div>
          <div style="display:flex;gap:0.6rem;">
            <button type="button" class="ecc-btn ecc-btn-primary" style="flex:1;" onclick="CheckInControlCenter.submitOverride()">AUTHORIZE OVERRIDE</button>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-ci-override')">Cancel</button>
          </div>
        </div>
      </div>

      <!-- MODULE 5: VENUES -->
      <div id="mod-venues" class="ecc-module-content">
        <!-- Venue Console -->
        <div id="vc-console" style="display:block;">
          <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:0.8rem;margin-bottom:1.1rem;">
            <div>
              <h2 style="font-size:1.15rem;font-weight:900;margin:0 0 0.15rem;">Venue Control & Maps</h2>
              <div style="font-size:0.75rem;color:var(--ecc-text-dim);">Manage spaces, availability and event assignments across your portfolio.</div>
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
              <input type="search" id="vc-search" class="ecc-input" placeholder="Search venues…" style="width:210px;" value="">
              <div class="vc-view-switch">
                <button type="button" class="vc-view-btn active" data-vc-view="grid" onclick="VenuesControlCenter.switchView('grid')">▦ Grid</button>
                <button type="button" class="vc-view-btn" data-vc-view="list" onclick="VenuesControlCenter.switchView('list')">≡ List</button>
                <button type="button" class="vc-view-btn" data-vc-view="cal" onclick="VenuesControlCenter.switchView('cal')">▤ Calendar</button>
              </div>
              <button type="button" class="ecc-btn ecc-btn-primary" onclick="VenuesControlCenter.wizardOpen()">+ Add Venue</button>
            </div>
          </div>
          <div id="vc-kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:0.7rem;margin-bottom:1.1rem;"></div>
          <div id="vc-grid"></div>
          <div id="vc-list" style="display:none;"></div>
          <div id="vc-cal" style="display:none;"></div>
        </div>

        <!-- Venue Manage Workspace -->
        <div id="vc-workspace" style="display:none;">
          <button type="button" class="ecc-btn ecc-btn-secondary" style="margin-bottom:0.9rem;font-size:0.74rem;" onclick="VenuesControlCenter.closeWorkspace()">← All Venues</button>
          <div id="vc-ws-head" class="vc-ws-head"></div>
          <div id="vc-ws-nav" class="vc-ws-nav"></div>
          <div id="vc-ws-body" style="margin-top:1rem;"><div class="ecc-tk-empty">Loading…</div></div>
        </div>
      </div>

      <!-- Modal: Add Venue (8-step wizard) -->
      <div id="modal-add-venue" class="ecc-modal-overlay">
        <div class="ecc-modal-content" style="max-width:780px;padding:0;overflow:hidden;">
          <div class="ecc-modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:1.1rem 1.5rem;border-bottom:1px solid var(--ecc-border);">
            <h3 style="margin:0;font-size:1.05rem;">Add Venue — <span id="vw-step-title">Identity</span></h3>
            <button type="button" class="ecc-btn ecc-btn-secondary" style="padding:0.2rem 0.6rem;font-size:0.75rem;" onclick="VenuesControlCenter.wizardClose()">✕</button>
          </div>
          <div id="vw-steps" class="vw-steps">
            <span class="vw-step active" data-vw="1">Identity</span>
            <span class="vw-step" data-vw="2">Location</span>
            <span class="vw-step" data-vw="3">Capacity & Spaces</span>
            <span class="vw-step" data-vw="4">Facilities</span>
            <span class="vw-step" data-vw="5">Media</span>
            <span class="vw-step" data-vw="6">Pricing</span>
            <span class="vw-step" data-vw="7">Policies</span>
            <span class="vw-step" data-vw="8">Review & Publish</span>
          </div>
          <div id="vw-body" style="padding:1.25rem 1.5rem;max-height:56vh;overflow-y:auto;min-height:340px;"></div>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:0.6rem;padding:1rem 1.5rem;border-top:1px solid var(--ecc-border);">
            <button type="button" id="vw-prev" class="ecc-btn ecc-btn-secondary" style="font-size:0.76rem;" onclick="VenuesControlCenter.wizardStep(-1)">← Back</button>
            <button type="button" id="vw-next" class="ecc-btn ecc-btn-primary" style="font-size:0.76rem;margin-left:auto;" onclick="VenuesControlCenter.wizardStep(1)">Continue →</button>
          </div>
        </div>
      </div>

      <!-- Modal: Assign Event to Venue -->
      <div id="modal-assign-event" class="ecc-modal-overlay">
        <div class="ecc-modal-content" style="max-width:620px;padding:1.75rem;">
          <h3 style="margin:0 0 0.9rem;font-size:1.05rem;">Assign Event → <span id="ve-venue-name" style="color:var(--ecc-primary);"></span></h3>
          <div style="font-size:0.78rem;display:grid;gap:0.6rem;">
            <label style="font-weight:700;">Event
              <select id="ve-event" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;"></select>
            </label>
            <label style="font-weight:700;">Space
              <select id="ve-space" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;"><option value="">Whole venue (all spaces)</option></select>
            </label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
              <label style="font-weight:700;">Setup start
                <input type="datetime-local" id="ve-setup" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;">
              </label>
              <label style="font-weight:700;">Event start
                <input type="datetime-local" id="ve-start" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;">
              </label>
              <label style="font-weight:700;">Event end
                <input type="datetime-local" id="ve-end" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;">
              </label>
              <label style="font-weight:700;">Teardown end
                <input type="datetime-local" id="ve-teardown" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;">
              </label>
            </div>
            <div id="ve-check" style="min-height:1.6rem;font-size:0.76rem;"></div>
            <div style="display:flex;gap:0.6rem;justify-content:flex-end;">
              <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.76rem;" onclick="VenuesControlCenter.assignCheck(false)">Check Availability</button>
              <button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.76rem;" onclick="VenuesControlCenter.assignCheck(true)">Assign Event ✓</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal: Block date -->
      <div id="modal-block-date" class="ecc-modal-overlay">
        <div class="ecc-modal-content" style="max-width:440px;padding:1.75rem;">
          <h3 style="margin:0 0 0.9rem;font-size:1.05rem;">Set Availability Block</h3>
          <div style="font-size:0.78rem;display:grid;gap:0.6rem;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
              <label style="font-weight:700;">From
                <input type="datetime-local" id="vb-start" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;">
              </label>
              <label style="font-weight:700;">To
                <input type="datetime-local" id="vb-end" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;">
              </label>
            </div>
            <label style="font-weight:700;">Status
              <select id="vb-status" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;">
                <option value="BLOCKED">Blocked</option>
                <option value="RESERVED">Reserved</option>
                <option value="MAINTENANCE">Maintenance</option>
              </select>
            </label>
            <label style="font-weight:700;">Space
              <select id="vb-space" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;"><option value="">Whole venue</option></select>
            </label>
            <label style="font-weight:700;">Reason
              <input type="text" id="vb-reason" class="ecc-input" style="display:block;width:100%;margin-top:0.2rem;" placeholder="e.g. Annual maintenance">
            </label>
            <div style="display:flex;gap:0.6rem;justify-content:flex-end;margin-top:0.4rem;">
              <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.76rem;" onclick="closeEccModal('modal-block-date')">Cancel</button>
              <button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.76rem;" onclick="VenuesControlCenter.blockSubmit()">Set Block</button>
            </div>
          </div>
        </div>
      </div>

      <!-- MODULE 6: MARKETING & COMMERCIAL GROWTH CONTROL CENTER -->
      <div id="mod-marketing" class="ecc-module-content">
        <!-- Header -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1.2rem;flex-wrap:wrap;">
          <div>
            <div style="display:flex;align-items:center;gap:0.6rem;">
              <h2 style="font-size:1.4rem;font-weight:900;margin:0;color:var(--ecc-text-bright);">Marketing &amp; Commercial Growth</h2>
              <span class="ecc-pill purple" style="font-weight:800;font-size:0.65rem;">CAMPAIGN CONTROL CENTER</span>
            </div>
            <p style="font-size:0.8rem;color:var(--ecc-text-dim);margin:0.25rem 0 0 0;">Promote your events, target high-intent audiences, issue promo offers, and track marketing revenue.</p>
          </div>
          <div style="display:flex;align-items:center;gap:0.6rem;flex-wrap:wrap;">
            <div style="position:relative;">
              <input type="text" id="mkt-global-search" class="ecc-input" placeholder="Search campaigns, promos..." style="padding:0.45rem 0.8rem 0.45rem 2rem;font-size:0.78rem;width:220px;" onkeyup="MarketingControlCenter.handleSearch(this.value)">
              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;left:0.7rem;top:50%;transform:translateY(-50%);color:var(--ecc-text-dim);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <select id="mkt-date-range" class="ecc-input" style="font-size:0.78rem;padding:0.45rem 0.8rem;" onchange="MarketingControlCenter.setDateRange(this.value)">
              <option value="30">Last 30 Days</option>
              <option value="7">Last 7 Days</option>
              <option value="90">Last 90 Days</option>
              <option value="all">All Time</option>
            </select>
            <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="campaign-create" style="font-weight:800;font-size:0.8rem;padding:0.45rem 1rem;" onclick="MarketingControlCenter.openCreateWizard()">
              <i class="fas fa-plus" style="margin-right:4px;"></i> Create Campaign
            </button>
            <button type="button" class="ecc-btn ecc-btn-secondary" id="mkt-ai-toggle-btn" data-mkt-action="ai-toggle" style="font-weight:700;font-size:0.8rem;padding:0.45rem 0.8rem;" onclick="MarketingControlCenter.toggleAiPanel()">
              🤖 AI Assistant <span class="ecc-pill purple" style="margin-left:4px;font-size:0.6rem;">3 Alerts</span>
            </button>
          </div>
        </div>

        <!-- Sub-Navigation Bar -->
        <div class="ecc-mkt-nav" id="mkt-sub-nav">
          <button type="button" class="ecc-mkt-tab active" data-mkt-action="tab" data-mkt-target="overview" onclick="MarketingControlCenter.switchTab('overview', this)"><i class="fas fa-chart-line"></i> Overview</button>
          <button type="button" class="ecc-mkt-tab" data-mkt-action="tab" data-mkt-target="campaigns" onclick="MarketingControlCenter.switchTab('campaigns', this)"><i class="fas fa-bullhorn"></i> Campaigns <span class="ecc-pill-count">12</span></button>
          <button type="button" class="ecc-mkt-tab" data-mkt-action="tab" data-mkt-target="promotions" onclick="MarketingControlCenter.switchTab('promotions', this)"><i class="fas fa-tags"></i> Promotions</button>
          <button type="button" class="ecc-mkt-tab" data-mkt-action="tab" data-mkt-target="adcards" onclick="MarketingControlCenter.switchTab('adcards', this)"><i class="fas fa-id-card"></i> Ad Cards</button>
          <button type="button" class="ecc-mkt-tab" data-mkt-action="tab" data-mkt-target="audience" onclick="MarketingControlCenter.switchTab('audience', this)"><i class="fas fa-users"></i> Audience</button>
          <button type="button" class="ecc-mkt-tab" data-mkt-action="tab" data-mkt-target="channels" onclick="MarketingControlCenter.switchTab('channels', this)"><i class="fas fa-share-alt"></i> Channels</button>
          <button type="button" class="ecc-mkt-tab" data-mkt-action="tab" data-mkt-target="promocodes" onclick="MarketingControlCenter.switchTab('promocodes', this)"><i class="fas fa-barcode"></i> Promo Codes</button>
          <button type="button" class="ecc-mkt-tab" data-mkt-action="tab" data-mkt-target="automations" onclick="MarketingControlCenter.switchTab('automations', this)"><i class="fas fa-robot"></i> Automations</button>
          <button type="button" class="ecc-mkt-tab" onclick="switchEccModule('analytics')"><i class="fas fa-external-link-alt"></i> Analytics</button>
        </div>

        <!-- Layout grid: Main workspace + AI Assistant side drawer -->
        <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1.25rem;align-items:start;" id="mkt-main-container">
          
          <!-- MAIN TAB CONTENT AREA -->
          <div id="mkt-tab-content" style="min-width:0;">

            <!-- VIEW 1: OVERVIEW -->
            <div id="mkt-view-overview" class="mkt-subview">
              <!-- Top KPI row -->
              <div class="ecc-mkt-kpi-grid">
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>Active Campaigns</span><i class="fas fa-bullhorn" style="color:#8b5cf6;"></i></div>
                  <div class="mkt-kpi-val" id="mkt-kpi-active-campaigns">12</div>
                  <div class="mkt-kpi-sub green"><i class="fas fa-arrow-up"></i> 18% vs last month</div>
                </div>
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>People Reached</span><i class="fas fa-eye" style="color:#3b82f6;"></i></div>
                  <div class="mkt-kpi-val" id="mkt-kpi-reach">42.8K</div>
                  <div class="mkt-kpi-sub green"><i class="fas fa-arrow-up"></i> 24% vs last month</div>
                </div>
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>Interactions</span><i class="fas fa-mouse-pointer" style="color:#f59e0b;"></i></div>
                  <div class="mkt-kpi-val" id="mkt-kpi-interactions">8.4K</div>
                  <div class="mkt-kpi-sub green"><i class="fas fa-arrow-up"></i> 11% vs last month</div>
                </div>
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>Attributed Sales</span><i class="fas fa-ticket-alt" style="color:#10b981;"></i></div>
                  <div class="mkt-kpi-val" id="mkt-kpi-sales">1,240</div>
                  <div class="mkt-kpi-sub green"><i class="fas fa-arrow-up"></i> 21% vs last month</div>
                </div>
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>Attributed Revenue</span><i class="fas fa-coins" style="color:#eab308;"></i></div>
                  <div class="mkt-kpi-val" id="mkt-kpi-revenue">MK 4.8M</div>
                  <div class="mkt-kpi-sub green"><i class="fas fa-arrow-up"></i> 16% vs last month</div>
                </div>
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>Campaign Conversion</span><i class="fas fa-percentage" style="color:#6366f1;"></i></div>
                  <div class="mkt-kpi-val" id="mkt-kpi-conversion">4.7%</div>
                  <div class="mkt-kpi-sub green"><i class="fas fa-arrow-up"></i> 1.2% benchmark</div>
                </div>
              </div>

              <!-- Performance Chart + Event Breakdown -->
              <div style="display:grid;grid-template-columns:minmax(0,1.7fr) minmax(0,1fr);gap:1.25rem;margin-top:1.25rem;">
                
                <!-- Campaign Performance Chart Card -->
                <div class="ecc-card">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
                    <div>
                      <h3 style="font-size:0.95rem;margin:0;">Campaign Performance Trajectory</h3>
                      <span style="font-size:0.7rem;color:var(--ecc-text-dim);">Attributed growth loop across active marketing channels</span>
                    </div>
                    <div style="display:flex;gap:0.4rem;background:var(--ecc-surface-2);padding:0.2rem;border-radius:6px;">
                      <button type="button" class="ecc-btn mkt-chart-btn active" onclick="MarketingControlCenter.setChartMetric('revenue', this)">Revenue</button>
                      <button type="button" class="ecc-btn mkt-chart-btn" onclick="MarketingControlCenter.setChartMetric('tickets', this)">Tickets</button>
                      <button type="button" class="ecc-btn mkt-chart-btn" onclick="MarketingControlCenter.setChartMetric('reach', this)">Reach</button>
                      <button type="button" class="ecc-btn mkt-chart-btn" onclick="MarketingControlCenter.setChartMetric('conversion', this)">Conversion</button>
                    </div>
                  </div>
                  <div style="height:220px;position:relative;display:flex;align-items:flex-end;gap:1.2rem;padding-top:1.5rem;border-bottom:1px solid var(--ecc-border);" id="mkt-chart-bars">
                    <!-- Bars populated by JS -->
                  </div>
                  <div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--ecc-text-dim);margin-top:0.5rem;" id="mkt-chart-labels">
                    <span>Week 1</span><span>Week 2</span><span>Week 3</span><span>Week 4</span>
                  </div>
                </div>

                <!-- Event Marketing Performance Table Card -->
                <div class="ecc-card">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                    <h3 style="font-size:0.95rem;margin:0;">Event Performance</h3>
                    <span style="font-size:0.68rem;color:var(--ecc-text-dim);">Top promoted events</span>
                  </div>
                  <div style="display:flex;flex-direction:column;gap:0.6rem;" id="mkt-event-performance-list">
                    <!-- Populated by JS -->
                  </div>
                </div>
              </div>

              <!-- AI Marketing Health & Opportunity Banner Row -->
              <div style="margin-top:1.25rem;display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
                <div class="ecc-card" style="border-left:4px solid #f59e0b;background:linear-gradient(135deg,rgba(245,158,11,0.05),var(--ecc-surface));">
                  <div style="display:flex;align-items:flex-start;gap:0.75rem;">
                    <div style="width:36px;height:36px;border-radius:8px;background:rgba(245,158,11,0.15);color:#f59e0b;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">
                      <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                      <h4 style="font-size:0.85rem;margin:0 0 0.25rem 0;color:var(--ecc-text-bright);">Marketing Insight: Checkout Drop-off</h4>
                      <p style="font-size:0.74rem;color:var(--ecc-text-dim);margin:0 0 0.6rem 0;">Music Festival 2026 has 12.8K impressions but checkout conversion dropped 18% today. Customers drop off at VIP tier selection.</p>
                      <div style="display:flex;gap:0.5rem;">
                        <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="campaign-investigate" data-mkt-target="cmp-1" style="font-size:0.72rem;padding:0.3rem 0.7rem;" onclick="MarketingControlCenter.investigateCampaign('cmp-1')">Investigate Campaign</button>
                        <button type="button" class="ecc-btn ecc-btn-secondary" data-mkt-action="campaign-create" data-mkt-preset="vip_promo" style="font-size:0.72rem;padding:0.3rem 0.7rem;" onclick="MarketingControlCenter.openCreateWizard('vip_promo')">Create VIP Offer</button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="ecc-card" style="border-left:4px solid #10b981;background:linear-gradient(135deg,rgba(16,185,129,0.05),var(--ecc-surface));">
                  <div style="display:flex;align-items:flex-start;gap:0.75rem;">
                    <div style="width:36px;height:36px;border-radius:8px;background:rgba(16,185,129,0.15);color:#10b981;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;">
                      <i class="fas fa-rocket"></i>
                    </div>
                    <div>
                      <h4 style="font-size:0.85rem;margin:0 0 0.25rem 0;color:var(--ecc-text-bright);">Growth Opportunity: Mid-week Flash Sale</h4>
                      <p style="font-size:0.74rem;color:var(--ecc-text-dim);margin:0 0 0.6rem 0;">Malawi Business Summit has high weekday views (340 high-intent viewers). A 48-hour Early Bird promotion can accelerate ticket sales.</p>
                      <div style="display:flex;gap:0.5rem;">
                        <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="campaign-create" data-mkt-preset="early_bird" style="font-size:0.72rem;padding:0.3rem 0.7rem;background:#10b981;border-color:#10b981;" onclick="MarketingControlCenter.openCreateWizard('early_bird')">Launch Early Bird Promo</button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- VIEW 2: CAMPAIGNS -->
            <div id="mkt-view-campaigns" class="mkt-subview" style="display:none;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.6rem;">
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                  <select id="mkt-cmp-status-filter" class="ecc-input" style="font-size:0.78rem;padding:0.4rem 0.7rem;" onchange="MarketingControlCenter.renderCampaigns()">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="paused">Paused</option>
                    <option value="completed">Completed</option>
                    <option value="draft">Draft</option>
                  </select>
                  <select id="mkt-cmp-event-filter" class="ecc-input" style="font-size:0.78rem;padding:0.4rem 0.7rem;" onchange="MarketingControlCenter.renderCampaigns()">
                    <option value="all">All Events</option>
                  </select>
                  <select id="mkt-cmp-channel-filter" class="ecc-input" style="font-size:0.78rem;padding:0.4rem 0.7rem;" onchange="MarketingControlCenter.renderCampaigns()">
                    <option value="all">All Channels</option>
                    <option value="marketplace">Uthenga Marketplace</option>
                    <option value="notifications">In-App Notifications</option>
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                  </select>
                </div>
                <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="campaign-create" style="font-size:0.8rem;padding:0.4rem 0.9rem;" onclick="MarketingControlCenter.openCreateWizard()">
                  <i class="fas fa-plus" style="margin-right:4px;"></i> New Campaign
                </button>
              </div>

              <div class="ecc-mkt-campaigns-grid" id="mkt-campaigns-list">
                <!-- Campaign Cards rendered by JS -->
              </div>
            </div>

            <!-- VIEW 3: PROMOTIONS -->
            <div id="mkt-view-promotions" class="mkt-subview" style="display:none;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                <div>
                  <h3 style="font-size:0.95rem;margin:0;">Active Promotions &amp; Discounts</h3>
                  <span style="font-size:0.7rem;color:var(--ecc-text-dim);">Manage flash sales, percentage discounts, and ticket tier offers</span>
                </div>
                <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="promotion-create" style="font-size:0.78rem;padding:0.4rem 0.9rem;" onclick="MarketingControlCenter.openPromoModal()">
                  <i class="fas fa-plus" style="margin-right:4px;"></i> Create Promotion
                </button>
              </div>
              <div class="ecc-mkt-promos-grid" id="mkt-promotions-list">
                <!-- Promo cards rendered by JS -->
              </div>
            </div>

            <!-- VIEW 4: AD CARDS & STUDIO -->
            <div id="mkt-view-adcards" class="mkt-subview" style="display:none;">
              <div style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:1.25rem;">
                
                <!-- Builder Controls -->
                <div class="ecc-card">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.85rem;">
                    <h3 style="font-size:0.95rem;margin:0;">Ad Card Studio &amp; AI Copywriter</h3>
                    <button type="button" class="ecc-btn ecc-btn-secondary" data-mkt-action="ad-generate" style="font-size:0.72rem;padding:0.25rem 0.6rem;" onclick="MarketingControlCenter.generateAiCardCopy()">
                      🤖 AI Generate Copy
                    </button>
                  </div>

                  <!-- Template selector -->
                  <div style="margin-bottom:1rem;">
                    <label style="font-size:0.72rem;font-weight:700;color:var(--ecc-text-dim);display:block;margin-bottom:0.4rem;">Select Design Template</label>
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.4rem;" id="mkt-card-templates">
                      <button type="button" class="ecc-ad-tpl-btn active" onclick="MarketingControlCenter.setAdTemplate('announcement', this)">Announcement</button>
                      <button type="button" class="ecc-ad-tpl-btn" onclick="MarketingControlCenter.setAdTemplate('earlybird', this)">Early Bird 30%</button>
                      <button type="button" class="ecc-ad-tpl-btn" onclick="MarketingControlCenter.setAdTemplate('flashsale', this)">Flash Sale</button>
                      <button type="button" class="ecc-ad-tpl-btn" onclick="MarketingControlCenter.setAdTemplate('vip', this)">VIP Experience</button>
                    </div>
                  </div>

                  <!-- Content Fields -->
                  <div style="display:flex;flex-direction:column;gap:0.7rem;">
                    <div>
                      <label style="font-size:0.72rem;font-weight:700;">Promotional Headline</label>
                      <input type="text" id="ad-field-headline" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.4rem 0.6rem;" value="MALAWI MUSIC FESTIVAL 2026" oninput="MarketingControlCenter.updateAdCardPreview()">
                    </div>
                    <div>
                      <label style="font-size:0.72rem;font-weight:700;">Subtitle / Hook</label>
                      <input type="text" id="ad-field-sub" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.4rem 0.6rem;" value="Live at Kamuzu Stadium · 09 Sept 2026" oninput="MarketingControlCenter.updateAdCardPreview()">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
                      <div>
                        <label style="font-size:0.72rem;font-weight:700;">Price Badge Text</label>
                        <input type="text" id="ad-field-price" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.4rem 0.6rem;" value="From MK 8,000" oninput="MarketingControlCenter.updateAdCardPreview()">
                      </div>
                      <div>
                        <label style="font-size:0.72rem;font-weight:700;">Call to Action (CTA)</label>
                        <select id="ad-field-cta" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.4rem 0.6rem;" onchange="MarketingControlCenter.updateAdCardPreview()">
                          <option value="GET TICKETS">GET TICKETS</option>
                          <option value="BOOK NOW">BOOK NOW</option>
                          <option value="LEARN MORE">LEARN MORE</option>
                          <option value="VIP ACCESS">VIP ACCESS</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <label style="font-size:0.72rem;font-weight:700;">Card Accent Color</label>
                      <div style="display:flex;gap:0.4rem;margin-top:0.2rem;">
                        <button type="button" style="width:24px;height:24px;border-radius:50%;background:#f97316;border:none;cursor:pointer;" onclick="MarketingControlCenter.setAdColor('#f97316')"></button>
                        <button type="button" style="width:24px;height:24px;border-radius:50%;background:#eab308;border:none;cursor:pointer;" onclick="MarketingControlCenter.setAdColor('#eab308')"></button>
                        <button type="button" style="width:24px;height:24px;border-radius:50%;background:#10b981;border:none;cursor:pointer;" onclick="MarketingControlCenter.setAdColor('#10b981')"></button>
                        <button type="button" style="width:24px;height:24px;border-radius:50%;background:#3b82f6;border:none;cursor:pointer;" onclick="MarketingControlCenter.setAdColor('#3b82f6')"></button>
                        <button type="button" style="width:24px;height:24px;border-radius:50%;background:#8b5cf6;border:none;cursor:pointer;" onclick="MarketingControlCenter.setAdColor('#8b5cf6')"></button>
                        <button type="button" style="width:24px;height:24px;border-radius:50%;background:#ec4899;border:none;cursor:pointer;" onclick="MarketingControlCenter.setAdColor('#ec4899')"></button>
                      </div>
                    </div>
                  </div>

                  <div style="display:flex;gap:0.6rem;margin-top:1rem;">
                    <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="ad-save" style="flex:1;" onclick="MarketingControlCenter.saveAdCard()">Save Ad Card</button>
                    <button type="button" class="ecc-btn ecc-btn-secondary" data-mkt-action="campaign-create" data-mkt-preset="ad_card" onclick="MarketingControlCenter.openCreateWizard()">Attach to Campaign →</button>
                  </div>
                </div>

                <!-- Live Ad Card Canvas Preview -->
                <div class="ecc-card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--ecc-surface-2);">
                  <span style="font-size:0.7rem;color:var(--ecc-text-dim);font-weight:700;margin-bottom:0.75rem;">LIVE CUSTOMER-FACING AD CARD</span>
                  
                  <div class="ecc-ad-card-preview" id="ad-card-canvas">
                    <!-- Dynamic live preview container rendered by JS -->
                  </div>
                </div>
              </div>
            </div>

            <!-- VIEW 5: AUDIENCE -->
            <div id="mkt-view-audience" class="mkt-subview" style="display:none;">
              <div class="ecc-mkt-kpi-grid" style="grid-template-columns:repeat(4,1fr);">
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>Total Reachable Audience</span><i class="fas fa-users" style="color:#3b82f6;"></i></div>
                  <div class="mkt-kpi-val">18,420</div>
                  <div class="mkt-kpi-sub">Verified customer profiles</div>
                </div>
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>Engaged Prospects</span><i class="fas fa-heart" style="color:#ec4899;"></i></div>
                  <div class="mkt-kpi-val">7,840</div>
                  <div class="mkt-kpi-sub">Interacted in last 30d</div>
                </div>
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>Ticket Buyers</span><i class="fas fa-shopping-bag" style="color:#10b981;"></i></div>
                  <div class="mkt-kpi-val">2,340</div>
                  <div class="mkt-kpi-sub">Converted customers</div>
                </div>
                <div class="ecc-card mkt-kpi-card">
                  <div class="mkt-kpi-head"><span>Repeat Attendees</span><i class="fas fa-redo" style="color:#8b5cf6;"></i></div>
                  <div class="mkt-kpi-val">812</div>
                  <div class="mkt-kpi-sub">Multiple event purchases</div>
                </div>
              </div>

              <!-- Audience Segments & Interactive Builder -->
              <div style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:1.25rem;margin-top:1.25rem;">
                
                <div class="ecc-card">
                  <h3 style="font-size:0.95rem;margin:0 0 0.85rem 0;">Smart Audience Segments</h3>
                  <div style="display:flex;flex-direction:column;gap:0.6rem;" id="mkt-audience-segments-list">
                    <!-- Segments list rendered by JS -->
                  </div>
                </div>

                <!-- Audience Rule Builder -->
                <div class="ecc-card">
                  <h3 style="font-size:0.95rem;margin:0 0 0.85rem 0;">Interactive Audience Builder</h3>
                  <p style="font-size:0.72rem;color:var(--ecc-text-dim);margin:0 0 0.75rem 0;">Create targeted segments based on behavioral rules.</p>
                  
                  <div style="display:flex;flex-direction:column;gap:0.6rem;">
                    <div style="font-size:0.75rem;font-weight:700;">Target customers who:</div>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                      <select class="ecc-input" style="font-size:0.75rem;flex:1;"><option>Viewed Event</option><option>Started Checkout</option><option>Bought Ticket</option></select>
                      <span style="font-size:0.7rem;">at least</span>
                      <input type="number" class="ecc-input" style="width:50px;font-size:0.75rem;" value="1">
                      <span style="font-size:0.7rem;">times</span>
                    </div>
                    <div style="font-size:0.72rem;font-weight:700;color:var(--ecc-primary);">AND</div>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                      <select class="ecc-input" style="font-size:0.75rem;flex:1;"><option>Did not purchase</option><option>Purchased VIP</option><option>Used Promo Code</option></select>
                    </div>
                    <div style="font-size:0.72rem;font-weight:700;color:var(--ecc-primary);">AND</div>
                    <div style="display:flex;gap:0.4rem;align-items:center;">
                      <select class="ecc-input" style="font-size:0.75rem;flex:1;"><option>Located in Lilongwe</option><option>Located in Blantyre</option><option>All Locations</option></select>
                    </div>
                  </div>

                  <div style="margin-top:1rem;background:var(--ecc-surface-2);padding:0.7rem;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                      <div style="font-size:0.68rem;color:var(--ecc-text-dim);font-weight:700;">ESTIMATED REACH</div>
                      <div style="font-size:1.1rem;font-weight:900;color:var(--ecc-primary);">4,820 Customers</div>
                    </div>
                    <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="campaign-create" data-mkt-preset="custom_segment" style="font-size:0.75rem;" onclick="MarketingControlCenter.openCreateWizard('custom_segment')">Create Campaign</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- VIEW 6: CHANNELS -->
            <div id="mkt-view-channels" class="mkt-subview" style="display:none;">
              <div class="ecc-card">
                <h3 style="font-size:0.95rem;margin:0 0 0.85rem 0;">Channel Distribution &amp; Revenue Attribution</h3>
                <div style="display:flex;flex-direction:column;gap:1rem;" id="mkt-channels-list">
                  <!-- Channel rows rendered by JS -->
                </div>
              </div>
            </div>

            <!-- VIEW 7: PROMO CODES -->
            <div id="mkt-view-promocodes" class="mkt-subview" style="display:none;">
              <div class="ecc-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                  <div>
                    <h3 style="font-size:0.95rem;margin:0;">Promo Code Management</h3>
                    <span style="font-size:0.7rem;color:var(--ecc-text-dim);">Issued codes, usage caps, and restrictions</span>
                  </div>
                  <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="promo-code-create" style="font-size:0.78rem;padding:0.4rem 0.8rem;" onclick="MarketingControlCenter.openPromoCodeModal()">
                    <i class="fas fa-plus" style="margin-right:4px;"></i> Generate New Code
                  </button>
                </div>
                <div class="ecc-table-wrapper">
                  <table class="ecc-table">
                    <thead>
                      <tr>
                        <th>Code</th>
                        <th>Offer Type</th>
                        <th>Usage Limit</th>
                        <th>Used Count</th>
                        <th>Attributed Sales</th>
                        <th>Revenue</th>
                        <th>Status</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody id="mkt-promocodes-tbody">
                      <!-- Promo code rows rendered by JS -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- VIEW 8: AUTOMATIONS -->
            <div id="mkt-view-automations" class="mkt-subview" style="display:none;">
              <div class="ecc-card">
                <div style="margin-bottom:1rem;">
                  <h3 style="font-size:0.95rem;margin:0;">Automated Growth Workflows</h3>
                  <span style="font-size:0.7rem;color:var(--ecc-text-dim);">Trigger automated recovery reminders and urgency alerts based on customer signals</span>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" id="mkt-automations-grid">
                  <!-- Automation cards rendered by JS -->
                </div>
              </div>
            </div>

          </div>

          <!-- SIDE DRAWER: AI MARKETING ASSISTANT -->
          <div class="ecc-mkt-ai-panel" id="mkt-ai-panel">
            <div class="mkt-ai-head">
              <div style="display:flex;align-items:center;gap:0.4rem;">
                <span style="font-size:1.1rem;">🤖</span>
                <strong style="font-size:0.85rem;">AI Marketing Advisor</strong>
              </div>
              <button type="button" class="mkt-ai-close" data-mkt-action="ai-toggle" onclick="MarketingControlCenter.toggleAiPanel()">×</button>
            </div>
            <div style="padding:0.8rem;font-size:0.72rem;color:var(--ecc-text-dim);border-bottom:1px solid var(--ecc-border);">
              Real-time monitoring of event ticket sales velocity, audience drop-off, and campaign ROI.
            </div>
            <div class="mkt-ai-body" id="mkt-ai-alerts-container">
              <!-- AI Recommendation cards rendered by JS -->
            </div>
            <div class="mkt-ai-foot">
              <span style="font-size:0.65rem;color:var(--ecc-text-dim);"><i class="fas fa-shield-alt"></i> Permission Boundary: AI recommends · You approve</span>
            </div>
          </div>

        </div>
      </div>

      <!-- MODULE 7: FINANCE -->
      <div id="mod-finance" class="ecc-module-content">
        <div id="fin-root"></div>
      </div>

      <!-- MODULE 8: CUSTOMERS (EVENT CRM LAYER) -->
      <div id="mod-customers" class="ecc-module-content">
        
        <!-- Header & Action Bar -->
        <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:0.8rem;margin-bottom:1.1rem;">
          <div>
            <h2 style="font-size:1.15rem;font-weight:900;margin:0 0 0.15rem;display:flex;align-items:center;gap:0.5rem;">
              <span>Customer Relationship Management</span>
              <span class="ecc-pill purple" style="font-size:0.6rem;">EVENT CRM</span>
            </h2>
            <div style="font-size:0.75rem;color:var(--ecc-text-dim);">Discover, understand, communicate, segment, and retain your event customer base.</div>
          </div>
          <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.75rem;" onclick="CustomersControlCenter.exportCustomers()"><i class="fas fa-file-export" style="margin-right:4px;"></i> Export</button>
            <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.75rem;" onclick="CustomersControlCenter.openSegmentBuilderModal()"><i class="fas fa-filter" style="margin-right:4px;"></i> Create Segment</button>
            <button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.75rem;" onclick="CustomersControlCenter.openAddCustomerModal()"><i class="fas fa-user-plus" style="margin-right:4px;"></i> + Add Customer</button>
          </div>
        </div>

        <!-- Internal Sub-Navigation Bar -->
        <div style="display:flex;gap:0.3rem;background:var(--ecc-surface-2);padding:0.4rem;border-radius:10px;margin-bottom:1.25rem;border:1px solid var(--ecc-border);overflow-x:auto;" id="cus-sub-nav">
          <button type="button" class="cus-tab-btn active" onclick="CustomersControlCenter.switchTab('overview', this)">Overview</button>
          <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchTab('directory', this)">All Customers</button>
          <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchTab('segments', this)">Segments</button>
          <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchTab('vip', this)">VIP Customers</button>
          <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchTab('at_risk', this)">At Risk <span class="ecc-pill rose" style="font-size:0.55rem;margin-left:0.3rem;">AI</span></button>
        </div>

        <!-- SUBVIEW CONTAINER -->
        <div id="cus-views-container">

          <!-- 1. OVERVIEW SCREEN -->
          <div id="cus-view-overview" class="cus-subview" style="display:block;">
            <!-- 4 Top KPI Cards -->
            <div class="ecc-cus-grid" id="cus-kpi-container">
              <div class="cus-kpi-card">
                <div class="cus-kpi-title">Total Customers</div>
                <div class="cus-kpi-val" id="cus-kpi-total">4,821</div>
                <div class="cus-kpi-sub"><span class="cus-kpi-growth">↑ 12.4%</span> vs last month</div>
              </div>
              <div class="cus-kpi-card">
                <div class="cus-kpi-title">Active Customers</div>
                <div class="cus-kpi-val" id="cus-kpi-active">3,420</div>
                <div class="cus-kpi-sub">Engaged in last 90 days</div>
              </div>
              <div class="cus-kpi-card">
                <div class="cus-kpi-title">New Customers</div>
                <div class="cus-kpi-val" id="cus-kpi-new">382</div>
                <div class="cus-kpi-sub"><span class="cus-kpi-growth">+8.2%</span> new this month</div>
              </div>
              <div class="cus-kpi-card">
                <div class="cus-kpi-title">Returning Customers</div>
                <div class="cus-kpi-val" id="cus-kpi-returning">1,019</div>
                <div class="cus-kpi-sub"><strong style="color:var(--ecc-primary);" id="cus-kpi-retention">21.1%</strong> retention rate</div>
              </div>
            </div>

            <!-- Customer Activity Chart & Acquisition Split -->
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;margin-bottom:1.25rem;">
              
              <!-- Customer Activity Chart Card -->
              <div class="ecc-card" style="padding:1.25rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
                  <div>
                    <strong style="font-size:0.88rem;display:block;">CUSTOMER ACTIVITY</strong>
                    <span style="font-size:0.72rem;color:var(--ecc-text-dim);">Customer relationship velocity over time</span>
                  </div>
                  <select class="ecc-input" style="font-size:0.7rem;padding:0.25rem 0.5rem;" id="cus-chart-metric-select" onchange="CustomersControlCenter.switchChartMetric(this.value)">
                    <option value="new_customers">New Customers</option>
                    <option value="returning_customers" selected>Returning Customers</option>
                    <option value="purchasers">Purchasers</option>
                    <option value="attendees">Attendees</option>
                  </select>
                </div>

                <!-- Visual Custom SVG Chart Representation -->
                <div style="background:var(--ecc-surface-2);border-radius:12px;padding:1rem;border:1px solid var(--ecc-border);height:210px;display:flex;flex-direction:column;justify-content:space-between;" id="cus-activity-chart-box">
                  <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.7rem;color:var(--ecc-text-dim);">
                    <span>Volume</span>
                    <span id="cus-chart-current-label">1,019 Returning Customers</span>
                  </div>
                  <!-- SVG Polyline Chart -->
                  <svg viewBox="0 0 500 120" style="width:100%;height:120px;overflow:visible;">
                    <defs>
                      <linearGradient id="cusChartGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#6366f1" stop-opacity="0.35"/>
                        <stop offset="100%" stop-color="#6366f1" stop-opacity="0"/>
                      </linearGradient>
                    </defs>
                    <path d="M 0,100 Q 100,80 200,60 T 350,30 T 500,10 L 500,120 L 0,120 Z" fill="url(#cusChartGrad)"/>
                    <path d="M 0,100 Q 100,80 200,60 T 350,30 T 500,10" fill="none" stroke="#6366f1" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="0" cy="100" r="4" fill="#6366f1"/>
                    <circle cx="125" cy="78" r="4" fill="#6366f1"/>
                    <circle cx="250" cy="55" r="4" fill="#6366f1"/>
                    <circle cx="375" cy="28" r="4" fill="#6366f1"/>
                    <circle cx="500" cy="10" r="5" fill="#a855f7"/>
                  </svg>
                  <div style="display:flex;justify-content:space-between;font-size:0.68rem;color:var(--ecc-text-dim);border-top:1px dashed var(--ecc-border);padding-top:0.4rem;">
                    <span>Aug 1</span><span>Aug 7</span><span>Aug 14</span><span>Aug 21</span><span>Aug 28</span>
                  </div>
                </div>
              </div>

              <!-- Customer Acquisition Breakdown Card -->
              <div class="ecc-card" style="padding:1.25rem;">
                <strong style="font-size:0.88rem;display:block;margin-bottom:0.2rem;">CUSTOMER ACQUISITION</strong>
                <span style="font-size:0.72rem;color:var(--ecc-text-dim);display:block;margin-bottom:0.8rem;">This month's acquisition channels</span>

                <div style="display:flex;flex-direction:column;gap:0.6rem;margin-bottom:1rem;">
                  <div style="display:flex;justify-content:space-between;font-size:0.75rem;">
                    <span style="color:var(--ecc-text-dim);">New customers</span>
                    <strong id="cus-acq-new">382</strong>
                  </div>
                  <div style="display:flex;justify-content:space-between;font-size:0.75rem;">
                    <span style="color:var(--ecc-text-dim);">First-time purchasers</span>
                    <strong id="cus-acq-first">291</strong>
                  </div>
                  <div style="display:flex;justify-content:space-between;font-size:0.75rem;">
                    <span style="color:var(--ecc-text-dim);">Returning buyers</span>
                    <strong id="cus-acq-returning">147</strong>
                  </div>
                </div>

                <div style="font-size:0.7rem;font-weight:800;color:var(--ecc-text);margin-bottom:0.5rem;text-transform:uppercase;letter-spacing:0.04em;">Acquisition Sources</div>
                <div style="display:flex;flex-direction:column;gap:0.45rem;">
                  <div>
                    <div style="display:flex;justify-content:space-between;font-size:0.68rem;margin-bottom:0.15rem;">
                      <span>Uthenga Discover</span><strong>48%</strong>
                    </div>
                    <div style="height:5px;background:var(--ecc-surface-3);border-radius:10px;overflow:hidden;"><div style="width:48%;height:100%;background:#6366f1;"></div></div>
                  </div>
                  <div>
                    <div style="display:flex;justify-content:space-between;font-size:0.68rem;margin-bottom:0.15rem;">
                      <span>Event Share</span><strong>27%</strong>
                    </div>
                    <div style="height:5px;background:var(--ecc-surface-3);border-radius:10px;overflow:hidden;"><div style="width:27%;height:100%;background:#a855f7;"></div></div>
                  </div>
                  <div>
                    <div style="display:flex;justify-content:space-between;font-size:0.68rem;margin-bottom:0.15rem;">
                      <span>Direct</span><strong>15%</strong>
                    </div>
                    <div style="height:5px;background:var(--ecc-surface-3);border-radius:10px;overflow:hidden;"><div style="width:15%;height:100%;background:#3b82f6;"></div></div>
                  </div>
                  <div>
                    <div style="display:flex;justify-content:space-between;font-size:0.68rem;margin-bottom:0.15rem;">
                      <span>Marketing Campaign</span><strong>10%</strong>
                    </div>
                    <div style="height:5px;background:var(--ecc-surface-3);border-radius:10px;overflow:hidden;"><div style="width:10%;height:100%;background:#10b981;"></div></div>
                  </div>
                </div>
              </div>

            </div>

            <!-- Top Customers Directory Preview Table -->
            <div class="ecc-card" style="padding:1.25rem;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.8rem;">
                <div>
                  <strong style="font-size:0.88rem;">VALUABLE CUSTOMER RELATIONSHIPS</strong>
                  <span style="font-size:0.72rem;color:var(--ecc-text-dim);display:block;">Highest lifetime spend & multi-event participants</span>
                </div>
                <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.7rem;" onclick="CustomersControlCenter.switchTab('directory')">View All Directory →</button>
              </div>

              <div style="overflow-x:auto;">
                <table class="vc-table">
                  <thead>
                    <tr>
                      <th>Customer</th>
                      <th>Customer ID</th>
                      <th>Events</th>
                      <th>Orders</th>
                      <th>Total Spent</th>
                      <th>Last Activity</th>
                      <th>Status</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody id="cus-overview-table-body">
                    <!-- Populated by JS -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- 2. ALL CUSTOMERS (DIRECTORY) SCREEN -->
          <div id="cus-view-directory" class="cus-subview" style="display:none;">
            
            <!-- Directory Filter & Search Header -->
            <div class="ecc-card" style="padding:1rem;margin-bottom:1rem;">
              <div style="display:flex;gap:0.6rem;flex-wrap:wrap;align-items:center;">
                
                <!-- Search Input -->
                <div style="flex:2;min-width:240px;position:relative;">
                  <input type="search" id="cus-search-input" class="ecc-input" style="width:100%;padding-left:2.2rem;font-size:0.8rem;" placeholder="Search name, email, phone, Customer ID (UTH-CUS-...), Order ID, Ticket ID…" onkeyup="if(event.key==='Enter') CustomersControlCenter.loadDirectory();">
                  <i class="fas fa-search" style="position:absolute;left:0.8rem;top:50%;transform:translateY(-50%);color:var(--ecc-text-dim);font-size:0.8rem;"></i>
                </div>

                <!-- Filters -->
                <select class="ecc-input" id="cus-filter-segment" style="font-size:0.75rem;padding:0.4rem 0.6rem;" onchange="CustomersControlCenter.loadDirectory();">
                  <option value="all">All Segments</option>
                  <option value="VIP">VIP Customers</option>
                  <option value="Corporate">Corporate</option>
                  <option value="Repeat Buyer">Repeat Buyers</option>
                  <option value="Student">Student</option>
                </select>

                <select class="ecc-input" id="cus-filter-activity" style="font-size:0.75rem;padding:0.4rem 0.6rem;" onchange="CustomersControlCenter.loadDirectory();">
                  <option value="all">All Activity States</option>
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                  <option value="At Risk">At Risk</option>
                </select>

                <button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.75rem;" onclick="CustomersControlCenter.loadDirectory();">Filter</button>
              </div>
            </div>

            <!-- Customer Directory Table -->
            <div class="ecc-card" style="overflow-x:auto;">
              <table class="vc-table">
                <thead>
                  <tr>
                    <th>Customer Name & Contact</th>
                    <th>Customer ID</th>
                    <th style="text-align:center;">Events</th>
                    <th style="text-align:center;">Purchases</th>
                    <th>Total Spent</th>
                    <th>Last Activity</th>
                    <th>Status / Tags</th>
                    <th style="text-align:right;">Actions</th>
                  </tr>
                </thead>
                <tbody id="cus-directory-table-body">
                  <!-- Populated by JS -->
                </tbody>
              </table>
            </div>

          </div>

          <!-- 3. CUSTOMER SEGMENTS SCREEN -->
          <div id="cus-view-segments" class="cus-subview" style="display:none;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
              <div>
                <h3 style="margin:0;font-size:1rem;font-weight:900;">Customer Audience Segments</h3>
                <span style="font-size:0.75rem;color:var(--ecc-text-dim);">Targeted customer groups for marketing campaigns and re-engagement</span>
              </div>
              <button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.75rem;" onclick="CustomersControlCenter.openSegmentBuilderModal()">+ Create Segment</button>
            </div>

            <div class="ecc-cus-grid" id="cus-segments-grid">
              <!-- Populated by JS -->
            </div>
          </div>

          <!-- 4. VIP CUSTOMERS SCREEN -->
          <div id="cus-view-vip" class="cus-subview" style="display:none;">
            <div class="ecc-card" style="padding:1.25rem;margin-bottom:1rem;border:1px solid rgba(99,102,241,0.4);">
              <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                <div>
                  <strong style="font-size:0.9rem;color:var(--ecc-primary);"><i class="fas fa-crown"></i> VIP CUSTOMER AUDIENCE</strong>
                  <span style="font-size:0.75rem;color:var(--ecc-text-dim);display:block;">Top-tier event patrons with lifetime spend exceeding MK 100,000</span>
                </div>
                <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="campaign-create" data-mkt-preset="vip_promo" style="font-size:0.72rem;" onclick="MarketingControlCenter.openCreateWizard('vip_promo')">Promote VIP Special Offer →</button>
              </div>
            </div>

            <div class="ecc-card" style="overflow-x:auto;">
              <table class="vc-table">
                <thead>
                  <tr>
                    <th>VIP Customer</th>
                    <th>Customer ID</th>
                    <th>Events Attended</th>
                    <th>Total Spend</th>
                    <th>Last Event</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="cus-vip-table-body">
                  <!-- Populated by JS -->
                </tbody>
              </table>
            </div>
          </div>

          <!-- 5. AT RISK CUSTOMERS SCREEN -->
          <div id="cus-view-at_risk" class="cus-subview" style="display:none;">
            <div class="ecc-card" style="padding:1.25rem;margin-bottom:1rem;background:rgba(239,68,68,0.04);border:1px solid rgba(239,68,68,0.25);">
              <div style="display:flex;align-items:flex-start;gap:0.75rem;">
                <div style="width:36px;height:36px;border-radius:50%;background:rgba(239,68,68,0.15);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">
                  <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                  <h4 style="margin:0;font-size:0.95rem;font-weight:900;color:var(--ecc-text-bright);">AI Assistant: At-Risk Customer Alert</h4>
                  <p style="margin:0.2rem 0 0 0;font-size:0.75rem;color:var(--ecc-text-dim);">These high-value repeat customers have exceeded their typical event attendance cycle without purchasing new tickets. Consider running a win-back campaign.</p>
                </div>
              </div>
            </div>

            <div class="ecc-cus-grid" id="cus-atrisk-container">
              <!-- Populated by JS -->
            </div>
          </div>

        </div>

        <!-- FULL PAGE CUSTOMER PROFILE WORKSPACE -->
        <div id="cus-profile-workspace" style="display:none;">
          <!-- Top Back Button -->
          <button type="button" class="ecc-btn ecc-btn-secondary" style="margin-bottom:1rem;font-size:0.75rem;" onclick="CustomersControlCenter.closeProfile();">
            ← Back to Customers Directory
          </button>

          <!-- Profile Header Card -->
          <div class="cus-profile-head">
            <div class="cus-avatar-lg" id="cus-prof-avatar">PB</div>
            <div style="flex:1;min-width:200px;">
              <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                <h2 style="margin:0;font-size:1.35rem;font-weight:900;" id="cus-prof-name">Patrick Byamungu</h2>
                <span class="ecc-pill green" id="cus-prof-status">● Active</span>
              </div>
              <div style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0.2rem 0 0.5rem;">
                Customer ID: <strong style="color:var(--ecc-text-bright);" id="cus-prof-code">UTH-CUS-008421</strong> · <span id="cus-prof-email">patrick@example.mw</span> · <span id="cus-prof-phone">+265 99 123 4567</span>
              </div>
              <div style="display:flex;gap:0.4rem;flex-wrap:wrap;" id="cus-prof-tags">
                <span class="ecc-chip">VIP</span><span class="ecc-chip">Corporate</span><span class="ecc-chip">Repeat Buyer</span>
              </div>
            </div>

            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
              <button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.75rem;" onclick="CustomersControlCenter.openMessageModal();"><i class="fas fa-envelope"></i> Message Customer</button>
              <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.75rem;" onclick="CustomersControlCenter.focusNotesTab();"><i class="fas fa-sticky-note"></i> Add Note</button>
            </div>
          </div>

          <!-- Value Metrics Banner -->
          <div class="ecc-cus-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
            <div class="cus-kpi-card">
              <div class="cus-kpi-title">Lifetime Spend</div>
              <div class="cus-kpi-val" style="color:var(--ecc-primary);" id="cus-prof-spend">MK 245,000</div>
              <div class="cus-kpi-sub">Total commerce value</div>
            </div>
            <div class="cus-kpi-card">
              <div class="cus-kpi-title">Average Order</div>
              <div class="cus-kpi-val" id="cus-prof-avg">MK 30,625</div>
              <div class="cus-kpi-sub">Per ticket transaction</div>
            </div>
            <div class="cus-kpi-card">
              <div class="cus-kpi-title">Total Orders</div>
              <div class="cus-kpi-val" id="cus-prof-orders">8</div>
              <div class="cus-kpi-sub">Completed transactions</div>
            </div>
            <div class="cus-kpi-card">
              <div class="cus-kpi-title">Events Attended</div>
              <div class="cus-kpi-val" id="cus-prof-events">6</div>
              <div class="cus-kpi-sub">Verified check-ins</div>
            </div>
          </div>

          <!-- Profile Internal Navigation Tabs -->
          <div style="display:flex;gap:0.3rem;background:var(--ecc-surface-2);padding:0.4rem;border-radius:10px;margin:1.25rem 0;border:1px solid var(--ecc-border);overflow-x:auto;">
            <button type="button" class="cus-tab-btn active" onclick="CustomersControlCenter.switchProfileTab('overview', this)">Overview</button>
            <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchProfileTab('purchases', this)">Purchases History</button>
            <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchProfileTab('events', this)">Event History</button>
            <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchProfileTab('tickets', this)">Tickets</button>
            <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchProfileTab('timeline', this)">Timeline</button>
            <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchProfileTab('notes', this)">Internal Notes</button>
            <button type="button" class="cus-tab-btn" onclick="CustomersControlCenter.switchProfileTab('reviews', this)">Reviews</button>
          </div>

          <!-- PROFILE SUB-VIEWS CONTAINER -->
          <div id="cus-prof-views-container">
            <!-- Profile Tab 1: Overview -->
            <div id="cus-pview-overview" class="cus-pview" style="display:block;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="ecc-card" style="padding:1.25rem;">
                  <strong style="font-size:0.85rem;display:block;margin-bottom:0.8rem;">Commercial Value Summary</strong>
                  <div style="display:flex;flex-direction:column;gap:0.6rem;font-size:0.78rem;">
                    <div style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--ecc-border);padding-bottom:0.4rem;">
                      <span style="color:var(--ecc-text-dim);">Customer Since</span><strong id="cus-prof-since">June 2026</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--ecc-border);padding-bottom:0.4rem;">
                      <span style="color:var(--ecc-text-dim);">Total Spend</span><strong style="color:var(--ecc-primary);" id="cus-prof-spend2">MK 245,000</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--ecc-border);padding-bottom:0.4rem;">
                      <span style="color:var(--ecc-text-dim);">Total Completed Orders</span><strong id="cus-prof-orders2">8</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                      <span style="color:var(--ecc-text-dim);">Events Attended</span><strong id="cus-prof-events2">6</strong>
                    </div>
                  </div>
                </div>

                <div class="ecc-card" style="padding:1.25rem;">
                  <strong style="font-size:0.85rem;display:block;margin-bottom:0.8rem;">Engagement Activity</strong>
                  <div style="display:flex;flex-direction:column;gap:0.6rem;font-size:0.78rem;">
                    <div style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--ecc-border);padding-bottom:0.4rem;">
                      <span style="color:var(--ecc-text-dim);">Last Ticket Purchase</span><strong id="cus-prof-last-pur">18 Aug 2026</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;border-bottom:1px dashed var(--ecc-border);padding-bottom:0.4rem;">
                      <span style="color:var(--ecc-text-dim);">Last Event Attendance</span><strong id="cus-prof-last-ev">18 Aug 2026</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                      <span style="color:var(--ecc-text-dim);">Last Organizer Message</span><strong id="cus-prof-last-msg">17 Aug 2026</strong>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Profile Tab 2: Purchases -->
            <div id="cus-pview-purchases" class="cus-pview" style="display:none;">
              <div class="ecc-card" style="overflow-x:auto;">
                <table class="vc-table">
                  <thead>
                    <tr>
                      <th>Order Reference</th>
                      <th>Event</th>
                      <th>Amount</th>
                      <th>Payment Status</th>
                      <th>Date</th>
                    </tr>
                  </thead>
                  <tbody id="cus-prof-purchases-body">
                    <!-- Populated by JS -->
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Profile Tab 3: Event History -->
            <div id="cus-pview-events" class="cus-pview" style="display:none;">
              <div class="ecc-card" style="overflow-x:auto;">
                <table class="vc-table">
                  <thead>
                    <tr>
                      <th>Event Title</th>
                      <th>Event Date</th>
                      <th>Ticket Tier</th>
                      <th>Attendance Status</th>
                      <th>Check-In Window</th>
                    </tr>
                  </thead>
                  <tbody id="cus-prof-events-body">
                    <!-- Populated by JS -->
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Profile Tab 4: Tickets -->
            <div id="cus-pview-tickets" class="cus-pview" style="display:none;">
              <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;" id="cus-prof-tickets-grid">
                <!-- Populated by JS -->
              </div>
            </div>

            <!-- Profile Tab 5: Timeline -->
            <div id="cus-pview-timeline" class="cus-pview" style="display:none;">
              <div class="ecc-card" style="padding:1.5rem;">
                <div class="cus-timeline-list" id="cus-prof-timeline-container">
                  <!-- Populated by JS -->
                </div>
              </div>
            </div>

            <!-- Profile Tab 6: Internal Notes -->
            <div id="cus-pview-notes" class="cus-pview" style="display:none;">
              <div class="ecc-card" style="padding:1.25rem;margin-bottom:1rem;">
                <strong style="font-size:0.85rem;display:block;margin-bottom:0.4rem;">Add Internal Note</strong>
                <span style="font-size:0.72rem;color:var(--ecc-text-dim);display:block;margin-bottom:0.6rem;"><i class="fas fa-lock" style="color:#f59e0b;"></i> Internal team notes are strictly private and never visible to customers.</span>
                <textarea class="ecc-input" id="cus-new-note-text" rows="3" style="width:100%;font-size:0.78rem;padding:0.5rem;margin-bottom:0.6rem;" placeholder="Write internal customer note (e.g. VIP seating preferences, special invoice requests)..."></textarea>
                <button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.75rem;" onclick="CustomersControlCenter.submitNote();">+ Save Internal Note</button>
              </div>

              <div id="cus-prof-notes-list">
                <!-- Populated by JS -->
              </div>
            </div>

            <!-- Profile Tab 7: Reviews -->
            <div id="cus-pview-reviews" class="cus-pview" style="display:none;">
              <div id="cus-prof-reviews-list">
                <!-- Populated by JS -->
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- MODULE 9: ANALYTICS -->
      <div id="mod-analytics" class="ecc-module-content">
        <div id="anl-root"></div>
      </div>

      <!-- MODULE 10: REVIEWS -->
      <div id="mod-reviews" class="ecc-module-content">
        <h2 style="font-size:1.15rem;font-weight:900;margin-bottom:1.25rem;">Event Reviews</h2>
        <div class="ecc-card">
          <strong>Mary Moyo ★★★★★</strong>
          <p style="font-size:0.85rem;color:var(--ecc-text-dim);margin-top:0.4rem;">"Exceptional event organization and sound quality!"</p>
        </div>
      </div>

      <!-- MODULE 11: MESSAGES -->
      <div id="mod-messages" class="ecc-module-content">
        <div id="msgs-root"></div>
      </div>

      <!-- MODULE 12: DOCUMENTS -->
      <div id="mod-documents" class="ecc-module-content">
        <div id="docs-root"></div>
      </div>

      <!-- MODULE 13: STAFF -->
      <div id="mod-staff" class="ecc-module-content">
        <h2 style="font-size:1.15rem;font-weight:900;margin-bottom:1.25rem;">Staff & Gate Scanner Roster</h2>
        <div class="ecc-card"><p style="font-size:0.85rem;color:var(--ecc-text-dim);">Gate A: Grace (Lead), Gate B: David (Tech)</p></div>
      </div>

      <!-- MODULE 14: SETTINGS -->
      <div id="mod-settings" class="ecc-module-content">
        <h2 style="font-size:1.15rem;font-weight:900;margin-bottom:1.25rem;">Organizer Settings</h2>
        <div class="ecc-card" style="max-width:450px;">
          <div style="margin-bottom:0.85rem;"><label style="font-size:0.75rem;font-weight:700;">Organizer Brand Name</label><input class="ecc-search-input" style="background:var(--ecc-bg);border:1px solid var(--ecc-border);padding:0.5rem;width:100%;box-sizing:border-box;margin-top:0.2rem;" value="Axon Events & Concerts"></div>
          <button type="button" class="ecc-btn ecc-btn-primary" onclick="eccNotify('Settings Saved!')">Save Organizer Profile</button>
        </div>
      </div>

    </main>

    <!-- PERSISTENT RIGHT AI PANEL (NEVER DISAPPEARS ACROSS TABS) -->
    <aside class="ecc-right-ai-panel">
      <div class="ecc-ai-header">
        <div class="ecc-ai-header-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <span>AI Event Assistant</span>
        </div>
        <span class="ecc-pill purple" style="font-size:0.6rem;">COPILOT</span>
      </div>

      <!-- Proactive Recommendation 1 -->
      <div class="ecc-ai-card">
        <p><strong>VIP tickets nearly sold out!</strong><br>Only 220 VIP passes left for Worship Concert 2026.</p>
        <button type="button" class="ecc-btn ecc-btn-secondary" style="width:100%;font-size:0.72rem;" onclick="TicketsControlCenter.newTicketType()">Increase Price / Cap</button>
      </div>

      <!-- Proactive Recommendation 2 -->
      <div class="ecc-ai-card">
        <p><strong>Sales velocity increased</strong><br>Ticket sales are 34% higher than last week.</p>
        <button type="button" class="ecc-btn ecc-btn-secondary" style="width:100%;font-size:0.72rem;" onclick="switchEccModule('analytics')">View Insights</button>
      </div>

      <!-- Proactive Recommendation 3 -->
      <div class="ecc-ai-card">
        <p><strong>Parking capacity near limit.</strong><br>Expected 1,840 cars at Gate A in 40 mins. Review parking plan.</p>
        <button type="button" class="ecc-btn ecc-btn-secondary" style="width:100%;font-size:0.72rem;" onclick="switchEccModule('venues')">Review Venue Layout</button>
      </div>

      <!-- Quick Actions List inside AI Panel -->
      <div style="margin-top:auto;padding-top:1rem;border-top:1px solid var(--ecc-border);">
        <label style="font-size:0.72rem;font-weight:800;color:var(--ecc-text);display:block;margin-bottom:0.6rem;">Quick Actions</label>
        <div style="display:flex;flex-direction:column;gap:0.4rem;">
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.74rem;justify-content:flex-start;gap:0.5rem;" onclick="openEccModal('modal-add-ticket')">
            <span>+</span><span>Create Ticket Type</span>
          </button>
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.74rem;justify-content:flex-start;gap:0.5rem;" onclick="switchEccModule('check-in')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/></svg>
            <span>Scan Ticket</span>
          </button>
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.74rem;justify-content:flex-start;gap:0.5rem;" onclick="switchEccModule('check-in')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            <span>View Check-In LIVE</span>
            <span class="ecc-nav-badge red" style="margin-left:auto;">LIVE</span>
          </button>
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.74rem;justify-content:flex-start;gap:0.5rem;" onclick="eccNotify('Reminder notification sent to 2,847 ticket holders!')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/></svg>
            <span>Send Event Reminder</span>
          </button>
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.74rem;justify-content:flex-start;gap:0.5rem;" onclick="TicketsControlCenter.exportData('sales')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            <span>Download Sales Report</span>
          </button>
        </div>
      </div>
    </aside>

  </div>

  <!-- BOTTOM STATUS BAR -->
  <footer class="ecc-bottom-bar">
    <div class="ecc-status-item">
      <div class="ecc-status-dot"></div>
      <span>Live Sync: <strong>Connected</strong></span>
      <span>·</span>
      <span>3 Active Events</span>
    </div>
    <div class="ecc-status-item">
      <span>Real-time Ticker: <strong>+1 Ticket Sold (VIP MK 25,000)</strong> 2m ago</span>
      <span>·</span>
      <span>Uthenga Event OS v2.4</span>
    </div>
  </footer>

</div>

<!-- MODALS -->
<div id="modal-create-event" class="ecc-modal-overlay">
  <div class="ecc-modal-content ew-modal">
    <div class="ew-shell">

      <aside class="ew-rail">
        <div class="ew-rail-head">
          <div class="ew-rail-kicker" id="ew-rail-kicker">Create Event</div>
          <h3 class="ew-rail-title" id="ew-rail-title">New Event Setup</h3>
          <p class="ew-rail-sub">Complete each step to publish your event.</p>
        </div>
        <nav class="ew-steps" id="ew-vertical-nav">
          <?php $ewSteps = [
            1 => ['Identity', 'Name, category & summary'],
            2 => ['Media', 'Cover image & gallery'],
            3 => ['Schedule', 'Dates, times & recurrence'],
            4 => ['Venue', 'Where it happens'],
            5 => ['Description', 'Tell customers about it'],
            6 => ['Tickets', 'Types, pricing & inventory'],
            7 => ['Policies', 'Refunds & entry rules'],
            8 => ['Preview', 'See the customer view'],
            9 => ['Review & Publish', 'Confirm & go live'],
          ]; foreach ($ewSteps as $sNum => $sData): ?>
          <button type="button" class="ew-step<?= $sNum === 1 ? ' active' : '' ?>" id="ew-vstep-<?= $sNum ?>" onclick="EventsWorkspace.goToStep(<?= $sNum ?>)">
            <span class="ew-step-bubble"><?= $sNum ?></span>
            <span class="ew-step-txt"><span class="ew-step-name"><?= e($sData[0]) ?></span><span class="ew-step-desc"><?= e($sData[1]) ?></span></span>
          </button>
          <?php endforeach; ?>
        </nav>
        <div class="ew-rail-foot">
          <div class="ew-foot-status" id="ew-save-status"><span class="ew-pulse"></span><span id="ew-save-status-text">Ready</span></div>
          <div class="ew-foot-prog"><div class="ew-foot-prog-fill" id="ew-overall-fill" style="width:11%;"></div></div>
          <div class="ew-foot-meta"><span>Setup progress</span><strong id="ew-overall-pct">11%</strong></div>
        </div>
      </aside>

      <div class="ew-panel">
        <div class="ew-panel-head">
          <div>
            <h3 id="ew-step-title">Event Identity</h3>
            <p id="ew-step-sub">Name, category and a short description.</p>
          </div>
          <button type="button" class="ecc-icon-btn" onclick="EventsWorkspace.closeWizard()">✕</button>
        </div>

        <div class="ew-panel-body" id="ew-panel-body">
          <input type="hidden" id="ew-f-event-id">
          <input type="hidden" id="ew-f-version" value="0">

          <!-- STEP 1: IDENTITY -->
          <div id="ew-step-1" class="ew-step-block active">
            <label class="ew-label">Event Name *</label>
            <input type="text" id="ew-f-title" class="ew-input" maxlength="200" placeholder="e.g. Malawi Business &amp; Innovation Summit">
            <div class="ew-field-row">
              <div>
                <label class="ew-label">Category *</label>
                <select id="ew-f-category" class="ew-input">
                  <option value="">Select a category…</option>
                  <?php foreach (UthengaEventsService::CATEGORIES as $cat): ?><option value="<?= e($cat) ?>"><?= e($cat) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="ew-label">Event Type *</label>
                <select id="ew-f-event-type" class="ew-input">
                  <option value="">Select a type…</option>
                  <?php foreach (UthengaEventsService::EVENT_TYPES as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <label class="ew-label">Short Description *</label>
            <textarea id="ew-f-short-description" class="ew-input" rows="2" maxlength="300" placeholder="One or two sentences customers will see on the event card."></textarea>
            <label class="ew-label">URL Slug</label>
            <div class="ew-slug-row"><span class="ew-slug-prefix">/events/</span><input type="text" id="ew-f-slug" class="ew-input ew-slug-input" placeholder="auto-generated-from-name"></div>
          </div>

          <!-- STEP 2: MEDIA -->
          <div id="ew-step-2" class="ew-step-block" style="display:none;">
            <label class="ew-label">Cover Image *</label>
            <div class="ew-dropzone" id="ew-cover-drop" onclick="document.getElementById('ew-cover-input').click()">
              <img id="ew-cover-preview" class="ew-cover-preview" style="display:none;">
              <div id="ew-cover-placeholder" class="ew-dropzone-empty">
                <span>Drop an image here or click to upload</span>
                <small>Recommended 16:9 · at least 1280×720px</small>
              </div>
            </div>
            <input type="file" id="ew-cover-input" accept="image/jpeg,image/png,image/webp" hidden>
            <div id="ew-cover-warning" class="ew-warning" style="display:none;"></div>

            <label class="ew-label" style="margin-top:1rem;">Gallery <small>(up to 12 images)</small></label>
            <div id="ew-gallery-grid" class="ew-gallery-grid"></div>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="document.getElementById('ew-gallery-input').click()">+ Add Images</button>
            <input type="file" id="ew-gallery-input" accept="image/jpeg,image/png,image/webp" hidden>
          </div>

          <!-- STEP 3: SCHEDULE -->
          <div id="ew-step-3" class="ew-step-block" style="display:none;">
            <label class="ew-label">Schedule Type</label>
            <div class="ew-mode-toggle" id="ew-schedule-mode-toggle">
              <button type="button" class="ew-mode-btn active" data-mode="SINGLE">Single Day</button>
              <button type="button" class="ew-mode-btn" data-mode="MULTI_DAY">Multi-Day</button>
              <button type="button" class="ew-mode-btn" data-mode="RECURRING">Recurring</button>
            </div>

            <div id="ew-schedule-single">
              <div class="ew-field-row">
                <div><label class="ew-label">Start Date *</label><input type="date" id="ew-f-start-date" class="ew-input"></div>
                <div><label class="ew-label">Start Time *</label><input type="time" id="ew-f-start-time" class="ew-input"></div>
              </div>
              <div class="ew-field-row">
                <div><label class="ew-label">End Time</label><input type="time" id="ew-f-end-time" class="ew-input"></div>
                <div><label class="ew-label">Doors Open</label><input type="time" id="ew-f-doors-open" class="ew-input"></div>
              </div>
            </div>

            <div id="ew-schedule-multiday" style="display:none;">
              <div id="ew-days-list" class="ew-days-list"></div>
              <button type="button" class="ecc-btn ecc-btn-secondary" onclick="EventsWorkspace.addDay()">+ Add Day</button>
            </div>

            <div id="ew-schedule-recurring" style="display:none;">
              <div class="ew-field-row">
                <div><label class="ew-label">First Occurrence *</label><input type="date" id="ew-f-recur-start-date" class="ew-input"></div>
                <div><label class="ew-label">Time *</label><input type="time" id="ew-f-recur-start-time" class="ew-input"></div>
              </div>
              <div class="ew-field-row">
                <div><label class="ew-label">End Time</label><input type="time" id="ew-f-recur-end-time" class="ew-input"></div>
                <div>
                  <label class="ew-label">Frequency</label>
                  <select id="ew-f-recur-frequency" class="ew-input"><option value="weekly">Weekly</option><option value="daily">Daily</option></select>
                </div>
              </div>
              <div id="ew-recur-weekday-row" class="ew-weekday-row">
                <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wi => $wl): ?>
                  <label class="ew-weekday-chip"><input type="checkbox" value="<?= ($wi + 1) % 7 ?>" class="ew-recur-weekday"><span><?= $wl ?></span></label>
                <?php endforeach; ?>
              </div>
              <div class="ew-field-row">
                <div>
                  <label class="ew-label">Interval</label>
                  <div class="ew-inline-input"><span>Every</span><input type="number" id="ew-f-recur-interval" class="ew-input ew-input-sm" value="1" min="1" max="12"><span id="ew-recur-interval-unit">week(s)</span></div>
                </div>
                <div>
                  <label class="ew-label">Ends</label>
                  <div class="ew-inline-input">
                    <select id="ew-f-recur-end-type" class="ew-input ew-input-sm"><option value="count">After</option><option value="until">On date</option></select>
                    <input type="number" id="ew-f-recur-count" class="ew-input ew-input-sm" value="10" min="1" max="60">
                    <input type="date" id="ew-f-recur-until" class="ew-input" style="display:none;">
                  </div>
                </div>
              </div>
              <button type="button" class="ecc-btn ecc-btn-secondary" onclick="EventsWorkspace.previewRecurrence()">Preview Dates</button>
              <div id="ew-recurrence-preview" class="ew-recurrence-preview"></div>
            </div>
          </div>

          <!-- STEP 4: VENUE -->
          <div id="ew-step-4" class="ew-step-block" style="display:none;">
            <div id="ew-venue-selected" class="ew-venue-selected" style="display:none;"></div>
            <div id="ew-venue-picker">
              <label class="ew-label">Search Venues</label>
              <input type="text" id="ew-venue-search" class="ew-input" placeholder="Search by venue name or city…">
              <div id="ew-venue-results" class="ew-venue-results"></div>
              <button type="button" class="ecc-btn ecc-btn-secondary" onclick="EventsWorkspace.toggleNewVenueForm()">+ Add a New Venue</button>
              <div id="ew-new-venue-form" class="ew-new-venue-form" style="display:none;">
                <label class="ew-label">Venue Name *</label>
                <input type="text" id="ew-f-venue-name" class="ew-input">
                <div class="ew-field-row">
                  <div><label class="ew-label">City</label><input type="text" id="ew-f-venue-city" class="ew-input"></div>
                  <div><label class="ew-label">Capacity</label><input type="number" id="ew-f-venue-capacity" class="ew-input" min="1"></div>
                </div>
                <label class="ew-label">Address</label>
                <input type="text" id="ew-f-venue-address" class="ew-input">
                <div class="ew-field-row">
                  <div><label class="ew-label">Latitude</label><input type="text" id="ew-f-venue-lat" class="ew-input" placeholder="-13.9626"></div>
                  <div><label class="ew-label">Longitude</label><input type="text" id="ew-f-venue-lng" class="ew-input" placeholder="33.7741"></div>
                </div>
                <button type="button" class="ecc-btn ecc-btn-primary" onclick="EventsWorkspace.createVenue()">Save Venue</button>
              </div>
            </div>
          </div>

          <!-- STEP 5: DESCRIPTION -->
          <div id="ew-step-5" class="ew-step-block" style="display:none;">
            <label class="ew-label">About This Event</label>
            <textarea id="ew-f-description" class="ew-input" rows="6" placeholder="Tell customers what this event is about…"></textarea>
            <label class="ew-label">What To Expect</label>
            <textarea id="ew-f-what-to-expect" class="ew-input" rows="3" placeholder="Set expectations for the day…"></textarea>
            <label class="ew-label">Highlights</label>
            <div id="ew-highlights-list" class="ew-highlights-list"></div>
            <div class="ew-inline-input">
              <input type="text" id="ew-highlight-input" class="ew-input" placeholder="e.g. Live demonstrations" maxlength="120">
              <button type="button" class="ecc-btn ecc-btn-secondary" onclick="EventsWorkspace.addHighlight()">+ Add</button>
            </div>
          </div>

          <!-- STEP 6: TICKETS -->
          <div id="ew-step-6" class="ew-step-block" style="display:none;">
            <label class="ew-label">Ticket Types</label>
            <p class="ew-tb-hint">Create ticket tiers for your event. Each ticket gets its own unique QR code at purchase.</p>
            <div id="ew-tickets-list" class="ew-tickets-list"></div>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="EventsWorkspace.openTicketBuilder()">+ Add Ticket Type</button>
            <div id="ew-ticket-builder" class="ew-ticket-builder" style="display:none;">
              <input type="hidden" id="ew-tb-id">
              <div class="ew-ticket-builder-header">
                <strong>New Ticket Type</strong>
                <button type="button" class="ew-tk-iconbtn" title="Close" onclick="EventsWorkspace.closeTicketBuilder()">✕</button>
              </div>

              <label class="ew-label">Ticket Design <small>— this sets the look of the ticket customers receive</small></label>
              <div class="ew-tpl-picker" id="ew-tb-tpl-picker">
                <div class="ew-tpl-card" data-tpl="vip" data-category="VIP">
                  <span class="ew-tpl-check">✓</span>
                  <div class="ew-tpl-swatch vip"></div>
                  <b>VIP</b><small>Dark luxury</small>
                </div>
                <div class="ew-tpl-card" data-tpl="vvip" data-category="VVIP">
                  <span class="ew-tpl-check">✓</span>
                  <div class="ew-tpl-swatch vvip"></div>
                  <b>VVIP</b><small>Charcoal &amp; gold</small>
                </div>
                <div class="ew-tpl-card" data-tpl="early_bird" data-category="Early Bird">
                  <span class="ew-tpl-check">✓</span>
                  <div class="ew-tpl-swatch early_bird"></div>
                  <b>Early Bird</b><small>30% discount</small>
                </div>
                <div class="ew-tpl-card active" data-tpl="general" data-category="General Admission">
                  <span class="ew-tpl-check">✓</span>
                  <div class="ew-tpl-swatch general"></div>
                  <b>General</b><small>Standard entry</small>
                </div>
                <div class="ew-tpl-card" data-tpl="group" data-category="Group">
                  <span class="ew-tpl-check">✓</span>
                  <div class="ew-tpl-swatch group"></div>
                  <b>Group</b><small>Admit 5 people</small>
                </div>
                <div class="ew-tpl-card" data-tpl="season" data-category="Season Pass">
                  <span class="ew-tpl-check">✓</span>
                  <div class="ew-tpl-swatch season"></div>
                  <b>Season</b><small>Valid from / to</small>
                </div>
              </div>
              <input type="hidden" id="ew-tb-template" value="general">
              <input type="hidden" id="ew-tb-category" value="General Admission">

              <div class="ew-live-preview">
                <div class="ew-pv-label">Live Ticket Preview</div>
                <div id="ew-ticket-preview">
                  <?php foreach ($ewTicketPreviewByTpl as $ewTpl => $ewHtml): ?>
                  <div class="ew-pv-slide <?= $ewTpl === 'general' ? 'active' : '' ?>" data-tpl="<?= $ewTpl ?>"><?= $ewHtml ?></div>
                  <?php endforeach; ?>
                </div>
              </div>

              <label class="ew-label">Ticket Name *</label>
              <input type="text" id="ew-tb-name" class="ew-input" maxlength="80" placeholder="e.g. Early Bird">
              <div class="ew-field-row">
                <div><label class="ew-label">Price (MWK) *</label><input type="number" id="ew-tb-price" class="ew-input" min="0" step="0.01" placeholder="0.00"></div>
                <div><label class="ew-label">Quantity *</label><input type="number" id="ew-tb-quantity" class="ew-input" min="0" placeholder="e.g. 500"></div>
              </div>
              <div class="ew-field-row">
                <div><label class="ew-label">Sale Starts</label><input type="date" id="ew-tb-sale-start" class="ew-input"></div>
                <div><label class="ew-label">Sale Ends</label><input type="date" id="ew-tb-sale-end" class="ew-input"></div>
              </div>
              <div class="ew-field-row">
                <div><label class="ew-label">Tier</label><select id="ew-tb-tier" class="ew-input"><option value="standard">Standard</option><option value="vip">VIP</option><option value="other">Other</option></select></div>
                <div><label class="ew-label">Access</label><input type="text" id="ew-tb-access" class="ew-input" placeholder="e.g. VIP Area" maxlength="80"></div>
              </div>
              <div class="ew-tb-flags">
                <label class="ew-checkbox"><input type="checkbox" id="ew-tb-transferable" checked><span>Transferable</span></label>
                <label class="ew-checkbox"><input type="checkbox" id="ew-tb-refundable" checked><span>Refundable</span></label>
              </div>
              <div class="ew-tb-submit">
                <button type="button" class="ecc-btn ecc-btn-secondary" onclick="EventsWorkspace.closeTicketBuilder()">Cancel</button>
                <button type="button" class="ecc-btn ecc-btn-primary" onclick="EventsWorkspace.saveTicketType()">Save Ticket</button>
              </div>
            </div>
            <div id="ew-ticket-pricing-preview" class="ew-pricing-preview"></div>
          </div>

          <!-- STEP 7: POLICIES -->
          <div id="ew-step-7" class="ew-step-block" style="display:none;">
            <label class="ew-label">Refund Policy</label>
            <label class="ew-radio"><input type="radio" name="ew-refund-policy" value="no_refunds"><span>No refunds</span></label>
            <label class="ew-radio"><input type="radio" name="ew-refund-policy" value="refund_before_event" checked><span>Refund before event</span></label>
            <label class="ew-radio"><input type="radio" name="ew-refund-policy" value="custom"><span>Custom policy</span></label>
            <textarea id="ew-f-refund-custom" class="ew-input" rows="2" placeholder="Describe your custom refund policy…" style="display:none;"></textarea>

            <label class="ew-label" style="margin-top:1rem;">Ticket Rules</label>
            <label class="ew-checkbox"><input type="checkbox" id="ew-f-transfer-allowed" checked><span>Allow ticket transfer</span></label>
            <label class="ew-checkbox"><input type="checkbox" id="ew-f-id-verification"><span>Require ID verification at entry</span></label>

            <label class="ew-label" style="margin-top:1rem;">Age Restriction</label>
            <label class="ew-radio"><input type="radio" name="ew-age-restriction" value="none" checked><span>None</span></label>
            <label class="ew-radio"><input type="radio" name="ew-age-restriction" value="18+"><span>18+</span></label>
            <label class="ew-radio"><input type="radio" name="ew-age-restriction" value="21+"><span>21+</span></label>
          </div>

          <!-- STEP 8: CUSTOMER PREVIEW -->
          <div id="ew-step-8" class="ew-step-block" style="display:none;">
            <p class="ew-preview-note">This is exactly what customers will see in the marketplace.</p>
            <div id="ew-customer-preview" class="ew-customer-preview"></div>
            <button type="button" class="ecc-btn ecc-btn-secondary" style="margin-top:1rem;" onclick="EventsWorkspace.viewCustomer(state.event.id)">Open Customer Page ↗</button>
          </div>

          <!-- STEP 9: REVIEW & PUBLISH -->
          <div id="ew-step-9" class="ew-step-block" style="display:none;">
            <div id="ew-checklist" class="ew-checklist"></div>
            <div id="ew-publish-success" class="ew-publish-success" style="display:none;"></div>
          </div>
        </div>

        <div class="ew-panel-foot">
          <button type="button" class="ecc-btn ecc-btn-secondary" id="ew-back-btn" onclick="EventsWorkspace.prevStep()">Back</button>
          <div class="ew-foot-right">
            <span id="ew-autosave-note" class="ew-autosave-note"></span>
            <button type="button" class="ecc-btn ecc-btn-secondary" onclick="EventsWorkspace.saveDraftAndClose()">Save Draft</button>
            <button type="button" class="ecc-btn ecc-btn-primary" id="ew-next-btn" onclick="EventsWorkspace.nextStep()">Save &amp; Continue</button>
            <button type="button" class="ecc-btn ecc-btn-primary" id="ew-publish-btn" style="display:none;" onclick="EventsWorkspace.publish()">Publish Event</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="modal-event-manage" class="ecc-modal-overlay">
  <div class="ecc-modal-content ew-manage-modal">
    <div class="ew-manage-head">
      <div>
        <h3 id="ew-manage-title">Manage Event</h3>
        <p id="ew-manage-sub"></p>
      </div>
      <button type="button" class="ecc-icon-btn" onclick="EventsWorkspace.closeManage()">✕</button>
    </div>
    <div class="ew-manage-actions" id="ew-manage-actions"></div>
    <div id="ew-manage-warning" class="ew-warning" style="display:none;"></div>
  </div>
</div>

<div id="modal-duplicate-event" class="ecc-modal-overlay">
  <div class="ecc-modal-content">
    <div class="ew-panel-head" style="margin-bottom:1rem;">
      <h3 style="margin:0;">Duplicate Event</h3>
      <button type="button" class="ecc-icon-btn" onclick="EventsWorkspace.closeDuplicate()">✕</button>
    </div>
    <input type="hidden" id="ew-dup-source-id">
    <label class="ew-label">New Event Name</label>
    <input type="text" id="ew-dup-title" class="ew-input" maxlength="200">
    <label class="ew-label">New Start Date</label>
    <input type="date" id="ew-dup-start-date" class="ew-input">
    <label class="ew-checkbox"><input type="checkbox" id="ew-dup-copy-description" checked><span>Copy description</span></label>
    <label class="ew-checkbox"><input type="checkbox" id="ew-dup-copy-media" checked><span>Copy media</span></label>
    <label class="ew-checkbox"><input type="checkbox" id="ew-dup-copy-tickets" checked><span>Copy ticket structure</span></label>
    <label class="ew-checkbox"><input type="checkbox" id="ew-dup-copy-pricing"><span>Copy pricing</span></label>
    <button type="button" class="ecc-btn ecc-btn-primary" style="width:100%;margin-top:0.75rem;" onclick="EventsWorkspace.confirmDuplicate()">Create Draft Copy</button>
  </div>
</div>

<!-- Create Ticket Type Multi-Step Wizard Modal (Modern Split 2-Column Layout) -->
<div id="modal-add-ticket" class="ecc-modal-overlay">
  <div class="ecc-modal-content" style="max-width:1080px;width:95vw;max-height:90vh;display:flex;flex-direction:column;padding:0;overflow:hidden;border-radius:16px;">
    
    <!-- Modal Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;padding:1.25rem 1.75rem;border-bottom:1px solid var(--ecc-border);background:var(--ecc-surface);">
      <div>
        <h3 style="margin:0;font-size:1.25rem;font-weight:900;display:flex;align-items:center;gap:0.5rem;">
          <span style="color:var(--ecc-primary);"><i class="fas fa-ticket-alt"></i></span>
          <span id="tw-modal-title">Create Ticket Type</span>
        </h3>
        <p style="margin:0.2rem 0 0 0;font-size:0.75rem;color:var(--ecc-text-dim);">Define pricing, inventory capacity, venue access privileges, and digital pass template branding.</p>
      </div>
      <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-add-ticket')">✕</button>
    </div>

    <!-- Modal Body: Split 2-Column Layout -->
    <div style="display:grid;grid-template-columns:1.15fr 0.85fr;flex:1;overflow:hidden;">
      
      <!-- LEFT COLUMN: Multi-Step Form Wizard -->
      <div style="padding:1.5rem 1.75rem;overflow-y:auto;border-right:1px solid var(--ecc-border);display:flex;flex-direction:column;">

        <!-- Wizard Step Navigation Pills -->
        <div style="display:flex;gap:0.35rem;margin-bottom:1.5rem;overflow-x:auto;padding-bottom:0.4rem;" id="tk-wiz-steps">
          <button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.72rem;padding:0.35rem 0.65rem;" onclick="TicketsWiz.goToStep(1)">1. Info</button>
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.72rem;padding:0.35rem 0.65rem;" onclick="TicketsWiz.goToStep(2)">2. Pricing</button>
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.72rem;padding:0.35rem 0.65rem;" onclick="TicketsWiz.goToStep(3)">3. Inventory</button>
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.72rem;padding:0.35rem 0.65rem;" onclick="TicketsWiz.goToStep(4)">4. Access</button>
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.72rem;padding:0.35rem 0.65rem;" onclick="TicketsWiz.goToStep(5)">5. Branding</button>
          <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.72rem;padding:0.35rem 0.65rem;" onclick="TicketsWiz.goToStep(6)">6. Review</button>
        </div>

        <!-- STEP 1: BASIC INFORMATION -->
        <div id="tk-wiz-step-1" class="tk-wiz-step">
          <div style="margin-bottom:1rem;">
            <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Ticket Name *</label>
            <input class="ecc-input" id="tw-name" placeholder="e.g. VIP Pass, Early Bird, General Admission" style="width:100%;" oninput="TicketsWiz.updateLivePassPreview()">
          </div>
          <div style="margin-bottom:1rem;">
            <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Ticket Category * (Controls Background Template Design)</label>
            <select class="ecc-input" id="tw-cat" style="width:100%;font-weight:800;" onchange="TicketsWiz.updateLivePassPreview()">
              <option value="VIP">VIP Pass (Exclusive Purple & Gold Shield)</option>
              <option value="VVIP">VVIP Experience (Luxury Charcoal & Gold Foil)</option>
              <option value="Early Bird">Early Bird (Emerald Wave & 30% Off Badge)</option>
              <option value="Student">Student Pass (Amber Sunset & Graduation Cap)</option>
              <option value="General Admission">General Admission (Royal Blue Wave & Crowd)</option>
              <option value="Complimentary">Complimentary Pass (Magenta Pink Wave)</option>
              <option value="Group">Group Pass (Amethyst Purple & 5 Person Badge)</option>
              <option value="Exhibitor">Exhibitor Pass (Ocean Teal & Trade Expo Overlay)</option>
              <option value="Season Pass">Season Pass (Crimson Red Cinema Perforated Ticket)</option>
              <option value="Day Pass">Day Pass (Sunny Gold/Orange Burst & Day 2 Badge)</option>
            </select>
          </div>
          <div style="margin-bottom:1rem;">
            <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Description / Perks</label>
            <textarea class="ecc-input" id="tw-desc" rows="2" style="width:100%;" placeholder="What does this ticket grant access to?" oninput="TicketsWiz.updateLivePassPreview()"></textarea>
          </div>
          <div style="margin-bottom:1.25rem;">
            <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Internal Operations Code</label>
            <input class="ecc-input" id="tw-code" placeholder="Auto-generated if blank" style="width:100%;font-family:'JetBrains Mono',monospace;font-size:0.78rem;" oninput="TicketsWiz.updateLivePassPreview()">
          </div>
          <button type="button" class="ecc-btn ecc-btn-primary" style="width:100%;" onclick="TicketsWiz.goToStep(2)">Continue to Pricing & Fees →</button>
        </div>

        <!-- STEP 2: PRICING & FEES -->
        <div id="tk-wiz-step-2" class="tk-wiz-step" style="display:none;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Base Price (MWK) *</label>
              <input type="number" class="ecc-input" id="tw-price" value="50000" oninput="TicketsWiz.calcFees();TicketsWiz.updateLivePassPreview();" style="width:100%;">
            </div>
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Platform Fee (%)</label>
              <input type="number" class="ecc-input" id="tw-fee" value="10" min="0" max="100" oninput="TicketsWiz.calcFees()" style="width:100%;">
            </div>
          </div>

          <!-- Financial Calculator Breakdown Panel -->
          <div style="background:var(--ecc-surface-2);border:1px solid var(--ecc-border);border-radius:12px;padding:1rem;margin-bottom:1.25rem;">
            <div style="font-size:0.75rem;font-weight:800;margin-bottom:0.6rem;color:var(--ecc-text-dim);text-transform:uppercase;">Commercial Settlement Preview</div>
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.4rem;"><span>Customer Pays (Total):</span><strong id="tw-calc-customer" style="color:var(--ecc-primary);">MWK 55,000</strong></div>
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.4rem;"><span>Organizer Receives:</span><strong id="tw-calc-organizer" style="color:#10b981;">MWK 50,000</strong></div>
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;"><span>Uthenga Platform Fee:</span><span id="tw-calc-fee" style="color:var(--ecc-text-dim);">MWK 5,000</span></div>
          </div>

          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;" onclick="TicketsWiz.goToStep(1)">← Back</button>
            <button type="button" class="ecc-btn ecc-btn-primary" style="flex:2;" onclick="TicketsWiz.goToStep(3)">Continue to Inventory →</button>
          </div>
        </div>

        <!-- STEP 3: INVENTORY & SALES PERIOD -->
        <div id="tk-wiz-step-3" class="tk-wiz-step" style="display:none;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Total Inventory Capacity *</label>
              <input type="number" class="ecc-input" id="tw-qty" value="1000" min="1" style="width:100%;">
            </div>
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Max Purchase / Customer</label>
              <input type="number" class="ecc-input" id="tw-max" value="5" min="1" style="width:100%;">
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Sales Start Date</label>
              <input type="date" class="ecc-input" id="tw-sale-start" style="width:100%;">
            </div>
            <div>
              <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.3rem;">Sales End Date</label>
              <input type="date" class="ecc-input" id="tw-sale-end" style="width:100%;">
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:0.6rem;margin-bottom:1.25rem;background:var(--ecc-surface-2);padding:0.9rem;border-radius:12px;">
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:600;"><input type="checkbox" id="tw-transferable" checked> ✓ Allow ticket transfers</label>
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:600;"><input type="checkbox" id="tw-refundable" checked> ✓ Refunds permitted (subject to policy)</label>
          </div>
          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;" onclick="TicketsWiz.goToStep(2)">← Back</button>
            <button type="button" class="ecc-btn ecc-btn-primary" style="flex:2;" onclick="TicketsWiz.goToStep(4)">Continue to Access Rules →</button>
          </div>
        </div>

        <!-- STEP 4: ACCESS RULES -->
        <div id="tk-wiz-step-4" class="tk-wiz-step" style="display:none;">
          <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.6rem;">Check-In LIVE Venue Privileges & Access Zones</label>
          <div style="display:flex;flex-direction:column;gap:0.6rem;margin-bottom:1.25rem;background:var(--ecc-surface-2);padding:0.9rem;border-radius:12px;" id="tw-access-rules">
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:600;"><input type="checkbox" value="Main Event Arena Gate" checked> ✓ Main Event Arena Gate</label>
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:600;"><input type="checkbox" value="VIP Fast-Track Entrance" checked> ✓ VIP Fast-Track Entrance</label>
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:600;"><input type="checkbox" value="VIP Lounge & Air-Conditioned Suite" checked> ✓ VIP Lounge & Air-Conditioned Suite</label>
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:600;"><input type="checkbox" value="Welcome Drink Bar Station"> ✓ Welcome Drink Bar Station</label>
            <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:600;"><input type="checkbox" value="Backstage & Artist Dressing Rooms"> ✗ Backstage & Artist Dressing Rooms</label>
          </div>
          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;" onclick="TicketsWiz.goToStep(3)">← Back</button>
            <button type="button" class="ecc-btn ecc-btn-primary" style="flex:2;" onclick="TicketsWiz.goToStep(5)">Continue to Branding →</button>
          </div>
        </div>

        <!-- STEP 5: TICKET BRANDING & PASS PREVIEW -->
        <div id="tk-wiz-step-5" class="tk-wiz-step" style="display:none;">
          <label style="font-size:0.75rem;font-weight:700;display:block;margin-bottom:0.6rem;">Digital Pass Customization & Wallet Settings</label>
          <div style="display:flex;flex-direction:column;gap:0.8rem;margin-bottom:1.25rem;background:var(--ecc-surface-2);padding:1rem;border-radius:12px;">
            <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:600;">
              <span>Apple Wallet & Google Wallet Integration</span>
              <input type="checkbox" checked>
            </label>
            <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:600;">
              <span>Dynamic Anti-Screenshot QR Tokens</span>
              <input type="checkbox" checked>
            </label>
            <label style="display:flex;align-items:center;justify-content:space-between;font-size:0.8rem;font-weight:600;">
              <span>Show Venue Seat / Access Zone on Pass</span>
              <input type="checkbox" checked>
            </label>
          </div>
          <div id="tw-live-pass-container" style="margin-bottom:1.25rem;"></div>
          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;" onclick="TicketsWiz.goToStep(4)">← Back</button>
            <button type="button" class="ecc-btn ecc-btn-primary" style="flex:2;" onclick="TicketsWiz.goToStep(6)">Review & Publish →</button>
          </div>
        </div>

        <!-- STEP 6: REVIEW & PUBLISH -->
        <div id="tk-wiz-step-6" class="tk-wiz-step" style="display:none;">
          <div style="background:var(--ecc-surface-2);border-radius:12px;padding:1rem;margin-bottom:1.25rem;font-size:0.8rem;display:flex;flex-direction:column;gap:0.5rem;" id="tw-review-list"></div>

          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;" onclick="closeEccModal('modal-add-ticket')">Cancel</button>
            <button type="button" class="ecc-btn ecc-btn-primary" style="flex:2;" id="tw-publish-btn" onclick="TicketsWiz.publishTicket()">✓ Publish Ticket Type</button>
          </div>
        </div>

      </div>

      <!-- RIGHT COLUMN: Sticky Real-Time Ticket Template Preview Panel -->
      <div style="padding:1.5rem;background:var(--ecc-surface-2);overflow-y:auto;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;">
        <div style="width:100%;display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;padding-bottom:0.75rem;border-bottom:1px solid var(--ecc-border);">
          <span style="font-size:0.72rem;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;color:var(--ecc-text-dim);display:flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-eye" style="color:var(--ecc-primary);"></i> Live Template Preview
          </span>
          <span class="ecc-pill blue" id="tw-live-cat-tag" style="font-size:0.65rem;text-transform:uppercase;">VIP PASS</span>
        </div>

        <div id="tw-side-live-pass-container" style="width:100%;display:flex;justify-content:center;"></div>

        <div style="margin-top:1.5rem;background:rgba(255,255,255,0.04);border:1px solid var(--ecc-border);border-radius:12px;padding:1rem;width:100%;font-size:0.75rem;color:var(--ecc-text-dim);line-height:1.5;">
          <div style="font-weight:800;color:var(--ecc-text);margin-bottom:0.4rem;display:flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-magic" style="color:var(--ecc-primary);"></i> Live Template Sync
          </div>
          Selecting a ticket category or editing form inputs on the left instantly updates this ticket pass design template in real time.
    </div>
  </div>
</div>

<!-- Modal: Create Campaign Wizard -->
<div id="modal-mkt-campaign-wiz" class="ecc-modal-overlay">
  <div class="ecc-modal-content" style="max-width:840px;padding:0;overflow:hidden;border-radius:16px;">
    
    <!-- Modal Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;padding:1.1rem 1.5rem;border-bottom:1px solid var(--ecc-border);background:var(--ecc-surface);">
      <div>
        <h3 style="margin:0;font-size:1.15rem;font-weight:900;display:flex;align-items:center;gap:0.5rem;">
          <span style="color:var(--ecc-primary);"><i class="fas fa-bullhorn"></i></span>
          <span id="mkt-wiz-title">Create Growth Campaign</span>
        </h3>
        <p style="margin:0.2rem 0 0 0;font-size:0.75rem;color:var(--ecc-text-dim);">Design, target, schedule and distribute event promotional campaigns.</p>
      </div>
      <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-mkt-campaign-wiz')">✕</button>
    </div>

    <!-- Step Indicator Bar -->
    <div style="display:flex;gap:0.2rem;background:var(--ecc-surface-2);padding:0.6rem 1.5rem;border-bottom:1px solid var(--ecc-border);overflow-x:auto;" id="mkt-wiz-nav-pills">
      <button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.68rem;padding:0.25rem 0.5rem;" onclick="MarketingControlCenter.goToWizStep(1)">1. Objective</button>
      <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.25rem 0.5rem;" onclick="MarketingControlCenter.goToWizStep(2)">2. Event</button>
      <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.25rem 0.5rem;" onclick="MarketingControlCenter.goToWizStep(3)">3. Audience</button>
      <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.25rem 0.5rem;" onclick="MarketingControlCenter.goToWizStep(4)">4. Offer</button>
      <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.25rem 0.5rem;" onclick="MarketingControlCenter.goToWizStep(5)">5. Ad Cards</button>
      <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.25rem 0.5rem;" onclick="MarketingControlCenter.goToWizStep(6)">6. Channels</button>
      <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.25rem 0.5rem;" onclick="MarketingControlCenter.goToWizStep(7)">7. Schedule</button>
      <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.68rem;padding:0.25rem 0.5rem;" onclick="MarketingControlCenter.goToWizStep(8)">8. Review</button>
    </div>

    <!-- Wizard Steps Body -->
    <div style="padding:1.5rem;max-height:65vh;overflow-y:auto;" id="mkt-wiz-step-container">
      
      <!-- STEP 1: OBJECTIVE -->
      <div class="mkt-wiz-step active" id="mkt-wiz-step-1">
        <h4 style="margin:0 0 0.4rem 0;font-size:0.95rem;">Step 1: Select Campaign Objective</h4>
        <p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0 0 1rem 0;">What commercial goal do you want to accomplish?</p>
        
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
          <label style="border:1px solid var(--ecc-border);border-radius:10px;padding:0.85rem;cursor:pointer;display:flex;gap:0.75rem;align-items:flex-start;background:var(--ecc-surface-2);" class="mkt-obj-option">
            <input type="radio" name="mkt_obj" value="Ticket Sales" checked style="margin-top:2px;">
            <div>
              <strong style="font-size:0.85rem;display:block;color:var(--ecc-text-bright);">Drive Ticket Sales</strong>
              <span style="font-size:0.7rem;color:var(--ecc-text-dim);">Directly push conversions for general admission and standard tiers.</span>
            </div>
          </label>
          <label style="border:1px solid var(--ecc-border);border-radius:10px;padding:0.85rem;cursor:pointer;display:flex;gap:0.75rem;align-items:flex-start;background:var(--ecc-surface-2);" class="mkt-obj-option">
            <input type="radio" name="mkt_obj" value="Early Bird" style="margin-top:2px;">
            <div>
              <strong style="font-size:0.85rem;display:block;color:var(--ecc-text-bright);">Early-Bird Acceleration</strong>
              <span style="font-size:0.7rem;color:var(--ecc-text-dim);">Incentivize early purchases with limited time percentage discounts.</span>
            </div>
          </label>
          <label style="border:1px solid var(--ecc-border);border-radius:10px;padding:0.85rem;cursor:pointer;display:flex;gap:0.75rem;align-items:flex-start;background:var(--ecc-surface-2);" class="mkt-obj-option">
            <input type="radio" name="mkt_obj" value="VIP Promotion" style="margin-top:2px;">
            <div>
              <strong style="font-size:0.85rem;display:block;color:var(--ecc-text-bright);">VIP &amp; Premium Tier Push</strong>
              <span style="font-size:0.7rem;color:var(--ecc-text-dim);">Target high-value customers for VVIP experience packages.</span>
            </div>
          </label>
          <label style="border:1px solid var(--ecc-border);border-radius:10px;padding:0.85rem;cursor:pointer;display:flex;gap:0.75rem;align-items:flex-start;background:var(--ecc-surface-2);" class="mkt-obj-option">
            <input type="radio" name="mkt_obj" value="Re-engagement" style="margin-top:2px;">
            <div>
              <strong style="font-size:0.85rem;display:block;color:var(--ecc-text-bright);">Audience Re-engagement</strong>
              <span style="font-size:0.7rem;color:var(--ecc-text-dim);">Recover customers who viewed event pages or dropped off during checkout.</span>
            </div>
          </label>
        </div>
      </div>

      <!-- STEP 2: SELECT EVENT -->
      <div class="mkt-wiz-step" id="mkt-wiz-step-2" style="display:none;">
        <h4 style="margin:0 0 0.4rem 0;font-size:0.95rem;">Step 2: Select Event to Promote</h4>
        <p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0 0 1rem 0;">Choosing an event automatically links dates, venue, ticket tiers, and images.</p>
        
        <div style="margin-bottom:1rem;">
          <label style="font-size:0.75rem;font-weight:700;">Target Event</label>
          <select id="mkt-wiz-event-select" class="ecc-input" style="width:100%;font-size:0.85rem;padding:0.5rem;margin-top:0.3rem;" onchange="MarketingControlCenter.wizOnEventSelect(this.value)">
            <!-- Populated by JS -->
          </select>
        </div>

        <div id="mkt-wiz-event-card-preview" style="background:var(--ecc-surface-2);border-radius:10px;padding:1rem;border:1px solid var(--ecc-border);">
          <!-- Selected event summary -->
        </div>
      </div>

      <!-- STEP 3: TARGET AUDIENCE -->
      <div class="mkt-wiz-step" id="mkt-wiz-step-3" style="display:none;">
        <h4 style="margin:0 0 0.4rem 0;font-size:0.95rem;">Step 3: Target Audience Segment</h4>
        <p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0 0 1rem 0;">Select who should receive or see this campaign.</p>

        <div style="display:flex;flex-direction:column;gap:0.6rem;">
          <label style="display:flex;align-items:center;gap:0.5rem;padding:0.6rem 0.8rem;background:var(--ecc-surface-2);border-radius:8px;cursor:pointer;">
            <input type="radio" name="mkt_aud" value="All Customers" checked>
            <div>
              <strong style="font-size:0.8rem;display:block;">All Uthenga Event Enthusiasts (18,420 users)</strong>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);">Broad marketplace broadcast</span>
            </div>
          </label>
          <label style="display:flex;align-items:center;gap:0.5rem;padding:0.6rem 0.8rem;background:var(--ecc-surface-2);border-radius:8px;cursor:pointer;">
            <input type="radio" name="mkt_aud" value="High Intent Prospects">
            <div>
              <strong style="font-size:0.8rem;display:block;">High Intent Prospects (1,240 users)</strong>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);">Viewed event page multiple times recently</span>
            </div>
          </label>
          <label style="display:flex;align-items:center;gap:0.5rem;padding:0.6rem 0.8rem;background:var(--ecc-surface-2);border-radius:8px;cursor:pointer;">
            <input type="radio" name="mkt_aud" value="Abandoned Checkout">
            <div>
              <strong style="font-size:0.8rem;display:block;">Abandoned Checkout (340 users)</strong>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);">Started checkout process but did not complete</span>
            </div>
          </label>
          <label style="display:flex;align-items:center;gap:0.5rem;padding:0.6rem 0.8rem;background:var(--ecc-surface-2);border-radius:8px;cursor:pointer;">
            <input type="radio" name="mkt_aud" value="VIP Buyers">
            <div>
              <strong style="font-size:0.8rem;display:block;">Previous VIP Buyers (180 users)</strong>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);">Customers with premium purchase history</span>
            </div>
          </label>
        </div>
      </div>

      <!-- STEP 4: ATTACH OFFER -->
      <div class="mkt-wiz-step" id="mkt-wiz-step-4" style="display:none;">
        <h4 style="margin:0 0 0.4rem 0;font-size:0.95rem;">Step 4: Attach Promotional Offer</h4>
        <p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0 0 1rem 0;">Optionally link a discount or promo code to drive conversion.</p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
          <div style="margin-bottom:0.8rem;">
            <label style="font-size:0.75rem;font-weight:700;">Offer Type</label>
            <select id="mkt-wiz-offer-type" class="ecc-input" style="width:100%;font-size:0.8rem;padding:0.45rem;margin-top:0.2rem;">
              <option value="none">No Special Offer (Standard Ticket Link)</option>
              <option value="percentage">Percentage Discount (e.g. 20% OFF)</option>
              <option value="fixed">Fixed Amount Off (e.g. MK 5,000 OFF)</option>
              <option value="promocode">Promo Code Required</option>
              <option value="flash">Flash Sale (24 Hours Only)</option>
            </select>
          </div>
          <div style="margin-bottom:0.8rem;">
            <label style="font-size:0.75rem;font-weight:700;">Discount Value / Code</label>
            <input type="text" id="mkt-wiz-offer-val" class="ecc-input" style="width:100%;font-size:0.8rem;padding:0.45rem;margin-top:0.2rem;" placeholder="e.g. 30% OFF or EARLYBIRD30">
          </div>
        </div>
      </div>

      <!-- STEP 5: AD CARDS -->
      <div class="mkt-wiz-step" id="mkt-wiz-step-5" style="display:none;">
        <h4 style="margin:0 0 0.4rem 0;font-size:0.95rem;">Step 5: Promotional Copy &amp; Ad Card</h4>
        <p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0 0 1rem 0;">Confirm the customer-facing promotional copy.</p>

        <div style="display:flex;flex-direction:column;gap:0.7rem;">
          <div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <label style="font-size:0.75rem;font-weight:700;">Campaign Headline</label>
              <button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.65rem;padding:0.15rem 0.4rem;" onclick="MarketingControlCenter.generateWizAiCopy()">🤖 AI Suggest Copy</button>
            </div>
            <input type="text" id="mkt-wiz-copy-title" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" value="Experience Malawi Music Festival 2026 Live!">
          </div>
          <div>
            <label style="font-size:0.75rem;font-weight:700;">Promotional Body Text</label>
            <textarea id="mkt-wiz-copy-body" class="ecc-input" style="width:100%;font-size:0.8rem;padding:0.45rem;height:60px;margin-top:0.2rem;">Don't miss the biggest cultural event of the year at Kamuzu Stadium. Get early bird tickets now before prices increase!</textarea>
          </div>
        </div>
      </div>

      <!-- STEP 6: CHANNELS -->
      <div class="mkt-wiz-step" id="mkt-wiz-step-6" style="display:none;">
        <h4 style="margin:0 0 0.4rem 0;font-size:0.95rem;">Step 6: Distribution Channels</h4>
        <p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0 0 1rem 0;">Select where this campaign will be published.</p>

        <div style="display:flex;flex-direction:column;gap:0.6rem;">
          <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.8rem;background:var(--ecc-surface-2);border-radius:8px;cursor:pointer;">
            <input type="checkbox" id="mkt-chan-marketplace" checked>
            <div>
              <strong style="font-size:0.8rem;display:block;">Uthenga Marketplace (Explore &amp; Event Pages)</strong>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);">Featured banner on home &amp; search discovery</span>
            </div>
          </label>
          <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.8rem;background:var(--ecc-surface-2);border-radius:8px;cursor:pointer;">
            <input type="checkbox" id="mkt-chan-push" checked>
            <div>
              <strong style="font-size:0.8rem;display:block;">In-App Push Notifications</strong>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);">Direct push alerts to targeted customer mobile apps</span>
            </div>
          </label>
          <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.8rem;background:var(--ecc-surface-2);border-radius:8px;cursor:pointer;">
            <input type="checkbox" id="mkt-chan-email">
            <div>
              <strong style="font-size:0.8rem;display:block;">Email Newsletter Broadcast</strong>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);">Rich HTML email invitation to subscriber list</span>
            </div>
          </label>
          <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.8rem;background:var(--ecc-surface-2);border-radius:8px;cursor:pointer;">
            <input type="checkbox" id="mkt-chan-sms">
            <div>
              <strong style="font-size:0.8rem;display:block;">SMS Instant Notification</strong>
              <span style="font-size:0.68rem;color:var(--ecc-text-dim);">Direct SMS short-link for high urgency alerts</span>
            </div>
          </label>
        </div>
      </div>

      <!-- STEP 7: SCHEDULE & SAFETY -->
      <div class="mkt-wiz-step" id="mkt-wiz-step-7" style="display:none;">
        <h4 style="margin:0 0 0.4rem 0;font-size:0.95rem;">Step 7: Schedule &amp; Inventory Protection</h4>
        <p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0 0 1rem 0;">Set campaign timing and automated safety thresholds.</p>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
          <div>
            <label style="font-size:0.75rem;font-weight:700;">Start Date &amp; Time</label>
            <input type="date" id="mkt-wiz-start" class="ecc-input" style="width:100%;font-size:0.8rem;padding:0.45rem;margin-top:0.2rem;" value="2026-08-20">
          </div>
          <div>
            <label style="font-size:0.75rem;font-weight:700;">End Date &amp; Time</label>
            <input type="date" id="mkt-wiz-end" class="ecc-input" style="width:100%;font-size:0.8rem;padding:0.45rem;margin-top:0.2rem;" value="2026-08-30">
          </div>
        </div>

        <div style="background:var(--ecc-surface-2);padding:0.8rem;border-radius:8px;border-left:3px solid var(--ecc-primary);">
          <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.78rem;font-weight:700;cursor:pointer;">
            <input type="checkbox" id="mkt-wiz-auto-stop" checked>
            <span>Auto-stop campaign when ticket inventory falls below threshold</span>
          </label>
          <p style="font-size:0.68rem;color:var(--ecc-text-dim);margin:0.3rem 0 0 1.5rem;">Prevents advertising ticket tiers that have already sold out.</p>
        </div>
      </div>

      <!-- STEP 8: REVIEW & LAUNCH -->
      <div class="mkt-wiz-step" id="mkt-wiz-step-8" style="display:none;">
        <h4 style="margin:0 0 0.4rem 0;font-size:0.95rem;">Step 8: Campaign Review &amp; Launch</h4>
        <p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0 0 1rem 0;">Review settings before launching your campaign live.</p>

        <div id="mkt-wiz-summary-recap" style="background:var(--ecc-surface-2);border-radius:10px;padding:1rem;display:flex;flex-direction:column;gap:0.5rem;font-size:0.78rem;">
          <!-- Summary recap rendered by JS -->
        </div>
      </div>

    </div>

    <!-- Wizard Footer Navigation Buttons -->
    <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;border-top:1px solid var(--ecc-border);background:var(--ecc-surface);">
      <button type="button" class="ecc-btn ecc-btn-secondary" id="mkt-wiz-prev-btn" onclick="MarketingControlCenter.wizPrevStep()">← Previous</button>
      <div style="display:flex;gap:0.5rem;">
        <button type="button" class="ecc-btn ecc-btn-secondary" onclick="MarketingControlCenter.saveCampaignDraft()">Save Draft</button>
        <button type="button" class="ecc-btn ecc-btn-primary" id="mkt-wiz-next-btn" onclick="MarketingControlCenter.wizNextStep()">Next Step →</button>
      </div>
    </div>

  </div>
</div>

<!-- Modal: Create Promotion -->
<div id="modal-mkt-promo" class="ecc-modal-overlay">
  <div class="ecc-modal-content" style="max-width:520px;padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <h3 id="promo-modal-heading" style="margin:0;font-size:1.1rem;font-weight:900;">Create Promotion Offer</h3>
      <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-mkt-promo')">✕</button>
    </div>
    <div style="display:flex;flex-direction:column;gap:0.75rem;">
      <input type="hidden" id="promo-modal-id" value="">
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Event</label>
        <select id="promo-modal-event" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;"></select>
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Promotion Title</label>
        <input type="text" id="promo-modal-title" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" placeholder="e.g. Flash Sale 25% OFF">
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Discount Percentage / Value</label>
        <input type="text" id="promo-modal-val" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" placeholder="e.g. 25%">
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Max Usage Limit</label>
        <input type="number" id="promo-modal-limit" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" value="200">
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Valid until</label>
        <input type="date" id="promo-modal-until" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;">
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Status</label>
        <select id="promo-modal-status" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;"><option value="active">Active</option><option value="paused">Paused</option></select>
      </div>
      <button type="button" id="promo-modal-submit" class="ecc-btn ecc-btn-primary" style="margin-top:0.5rem;" onclick="MarketingControlCenter.savePromotion()">Publish Promotion</button>
    </div>
  </div>
</div>

<!-- Modal: Campaign Detail -->
<div id="modal-mkt-campaign-detail" class="ecc-modal-overlay">
  <div class="ecc-modal-content" style="max-width:560px;padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <h3 style="margin:0;font-size:1.1rem;font-weight:900;">Campaign details</h3>
      <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-mkt-campaign-detail')">✕</button>
    </div>
    <div id="mkt-campaign-detail-content"></div>
  </div>
</div>

<!-- Modal: Create Promo Code -->
<div id="modal-mkt-code" class="ecc-modal-overlay">
  <div class="ecc-modal-content" style="max-width:520px;padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <h3 style="margin:0;font-size:1.1rem;font-weight:900;">Generate Promo Code</h3>
      <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-mkt-code')">✕</button>
    </div>
    <div style="display:flex;flex-direction:column;gap:0.75rem;">
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Promo Code String</label>
        <input type="text" id="code-modal-str" class="ecc-input" style="width:100%;font-size:0.85rem;font-family:monospace;font-weight:800;padding:0.45rem;margin-top:0.2rem;" placeholder="e.g. SUMMIT2026">
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Discount Type &amp; Value</label>
        <input type="text" id="code-modal-val" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" placeholder="e.g. 20% OFF or MK 5,000 OFF">
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Usage Cap</label>
        <input type="number" id="code-modal-cap" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" value="100">
      </div>
      <button type="button" class="ecc-btn ecc-btn-primary" style="margin-top:0.5rem;" onclick="MarketingControlCenter.savePromoCode()">Generate &amp; Activate Code</button>
    </div>
  </div>
</div>

<!-- Modal: Customer Segment Builder -->
<div id="modal-cus-segment-builder" class="ecc-modal-overlay">
  <div class="ecc-modal-content" style="max-width:560px;padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <h3 style="margin:0;font-size:1.1rem;font-weight:900;display:flex;align-items:center;gap:0.4rem;">
        <i class="fas fa-filter" style="color:var(--ecc-primary);"></i> Create Customer Segment
      </h3>
      <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-cus-segment-builder')">✕</button>
    </div>

    <div style="display:flex;flex-direction:column;gap:0.8rem;">
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Segment Name *</label>
        <input type="text" id="seg-builder-title" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" placeholder="e.g. High Value VIP Repeaters">
      </div>

      <div style="background:var(--ecc-surface-2);padding:0.85rem;border-radius:10px;border:1px solid var(--ecc-border);">
        <label style="font-size:0.72rem;font-weight:800;text-transform:uppercase;color:var(--ecc-text-dim);display:block;margin-bottom:0.5rem;">Rule Conditions (MUST SATISFY ALL)</label>
        
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
          <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;align-items:center;">
            <select class="ecc-input" style="font-size:0.72rem;padding:0.35rem;"><option>Total Spend</option></select>
            <select class="ecc-input" style="font-size:0.72rem;padding:0.35rem;"><option>greater than</option></select>
            <input type="text" class="ecc-input" style="font-size:0.72rem;padding:0.35rem;" value="MK 100,000">
          </div>
          <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;align-items:center;">
            <select class="ecc-input" style="font-size:0.72rem;padding:0.35rem;"><option>Events Attended</option></select>
            <select class="ecc-input" style="font-size:0.72rem;padding:0.35rem;"><option>greater than</option></select>
            <input type="number" class="ecc-input" style="font-size:0.72rem;padding:0.35rem;" value="3">
          </div>
          <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:0.4rem;align-items:center;">
            <select class="ecc-input" style="font-size:0.72rem;padding:0.35rem;"><option>Status</option></select>
            <select class="ecc-input" style="font-size:0.72rem;padding:0.35rem;"><option>equals</option></select>
            <input type="text" class="ecc-input" style="font-size:0.72rem;padding:0.35rem;" value="Active">
          </div>
        </div>
      </div>

      <div style="font-size:0.75rem;color:var(--ecc-text-dim);background:var(--ecc-surface-2);padding:0.6rem 0.8rem;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
        <span>Estimated matching audience:</span>
        <strong style="color:var(--ecc-primary);font-size:0.85rem;">94 customers</strong>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.4rem;">
        <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-cus-segment-builder')">Cancel</button>
        <button type="button" class="ecc-btn ecc-btn-primary" onclick="CustomersControlCenter.saveSegmentBuilder()">✓ Create Segment</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Add Customer -->
<div id="modal-cus-add" class="ecc-modal-overlay">
  <div class="ecc-modal-content" style="max-width:500px;padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <h3 style="margin:0;font-size:1.1rem;font-weight:900;">Add Customer Record</h3>
      <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-cus-add')">✕</button>
    </div>
    <div style="display:flex;flex-direction:column;gap:0.75rem;">
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Full Name *</label>
        <input type="text" id="cus-add-name" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" placeholder="e.g. Chisomo Banda">
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Email Address</label>
        <input type="email" id="cus-add-email" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" placeholder="chisomo@example.mw">
      </div>
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Phone Number</label>
        <input type="text" id="cus-add-phone" class="ecc-input" style="width:100%;font-size:0.82rem;padding:0.45rem;margin-top:0.2rem;" placeholder="+265 99 000 0000">
      </div>
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.5rem;">
        <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-cus-add')">Cancel</button>
        <button type="button" class="ecc-btn ecc-btn-primary" onclick="CustomersControlCenter.saveNewCustomer()">✓ Add Customer</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Direct Message Customer -->
<div id="modal-cus-message" class="ecc-modal-overlay">
  <div class="ecc-modal-content" style="max-width:520px;padding:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <h3 style="margin:0;font-size:1.1rem;font-weight:900;" id="cus-msg-title">Message Customer</h3>
      <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-cus-message')">✕</button>
    </div>
    <div style="display:flex;flex-direction:column;gap:0.75rem;">
      <div>
        <label style="font-size:0.75rem;font-weight:700;">Message Content</label>
        <textarea id="cus-msg-text" class="ecc-input" rows="4" style="width:100%;font-size:0.8rem;padding:0.5rem;margin-top:0.2rem;" placeholder="Write message to customer..."></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:0.4rem;">
        <button type="button" class="ecc-btn ecc-btn-secondary" onclick="closeEccModal('modal-cus-message')">Cancel</button>
        <button type="button" class="ecc-btn ecc-btn-primary" onclick="CustomersControlCenter.sendMessage()">Send Message →</button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  'use strict';

  window.eccNotify = function(msg) {
    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:2.5rem;right:1.5rem;z-index:9999;background:#0d1424;border:1px solid #6366f1;color:#fff;padding:0.75rem 1.25rem;border-radius:100px;font-size:0.8rem;font-weight:700;box-shadow:0 10px 30px rgba(0,0,0,0.3);animation:ecc-fade 0.2s ease-in-out;';
    toast.textContent = '✓ ' + msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
  };

  window.switchEccModule = function(modId) {
    var items = document.querySelectorAll('.ecc-nav-item');
    items.forEach(function(item) {
      if (item.getAttribute('data-mod') === modId) {
        item.classList.add('active');
      } else {
        item.classList.remove('active');
      }
    });

    var contents = document.querySelectorAll('.ecc-module-content');
    contents.forEach(function(c) { c.classList.remove('active'); });

    var target = document.getElementById('mod-' + modId);
    if (target) target.classList.add('active');
    if (window.onEccModuleShow) window.onEccModuleShow(modId);
  };

  window.openEccModal = function(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.style.display = 'flex';
    void modal.offsetWidth;
    modal.classList.add('active');
  };
  window.closeEccModal = function(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('active');
    setTimeout(function() {
      if (!modal.classList.contains('active')) {
        modal.style.display = 'none';
      }
    }, 200);
  };
  document.addEventListener('click', function(e) {
    if (e.target && e.target.classList && e.target.classList.contains('ecc-modal-overlay')) {
      window.closeEccModal(e.target.id);
    }
  });

  /* ── Check-In LIVE Controller ──────────────────────────────── */
  var ciDoc = document.getElementById('events-workspace');
  var ciApiBase = (ciDoc && ciDoc.dataset.baseUrl ? ciDoc.dataset.baseUrl : '') + 'api/tie/vendor/events/checkin.php';
  var tkApiBase = (ciDoc && ciDoc.dataset.baseUrl ? ciDoc.dataset.baseUrl : '') + 'api/tie/vendor/events/tickets.php';

  function ciIcon(name, size) {
    var p = {
      check: '<path d="M20 6L9 17l-5-5"/>',
      x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
      warn: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
      exit: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
      star: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
      bulb: '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.3h6c0-1 .4-1.8 1-2.3A7 7 0 0 0 12 2z"/>',
      wifi: '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
      wifiOff: '<line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
      user: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
      chart: '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
      next: '<polyline points="9 18 15 12 9 6"/>'
    }[name] || '';
    var s = size || 16;
    return '<svg viewBox="0 0 24 24" width="' + s + '" height="' + s + '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-0.14em;">' + p + '</svg>';
  }

  window.CheckInControlCenter = {
    state: {
      events: [], listingId: '', gate: 'Gate A', deviceId: '', mode: 'operator',
      operator: 'Operator', online: true, queue: [], decision: null, decisionTimer: null,
      lookupTimer: null, refreshTimer: null, workspace: null,
      cameraOn: false, camStream: null, camTimer: null, scanLock: false, detector: null
    },

    get: function(action, params) {
      var qs = '?listing_id=' + encodeURIComponent(this.state.listingId) + '&action=' + encodeURIComponent(action);
      Object.keys(params || {}).forEach(function(k) { qs += '&' + k + '=' + encodeURIComponent(params[k]); });
      return fetch(ciApiBase + qs, { credentials: 'same-origin' }).then(function(r) { return r.json().catch(function() { return {}; }); });
    },
    post: function(data) {
      var payload = Object.assign({ listing_id: this.state.listingId }, data);
      var csrf = ciDoc && ciDoc.dataset ? ciDoc.dataset.csrf : '';
      return fetch(ciApiBase, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(payload)
      }).then(function(r) { return r.json().catch(function() { return {}; }); });
    },
    errMsg: function(body) {
      if (body && body.error && body.error.message) return body.error.message;
      return 'The request could not be completed.';
    },

    /* ── Boot ────────────────────────────────────────────────── */
    init: function() {
      var self = this;
      this.state.deviceId = localStorage.getItem('ecc-ci-device') || ('SCN-' + Math.random().toString(16).slice(2, 6).toUpperCase());
      this.state.gate = localStorage.getItem('ecc-ci-gate') || 'Gate A';
      this.state.mode = localStorage.getItem('ecc-ci-mode') || 'operator';
      var op = document.querySelector('.ecc-user-info strong');
      this.state.operator = op ? op.textContent.trim() : 'Operator';
      this.updateIdentity();
      this.setMode(this.state.mode, true);

      window.addEventListener('online', function() { self.state.online = true; self.flushQueue(); self.refreshConnection(); });
      window.addEventListener('offline', function() { self.state.online = false; self.refreshConnection(); });

      this.refreshConnection();
      this.renderQueue();

      fetch(tkApiBase + '?action=events', { credentials: 'same-origin' })
        .then(function(r) { return r.json().catch(function() { return {}; }); })
        .then(function(body) {
          if (!body.success) {
            window.eccNotify('Could not load event portfolio: ' + self.errMsg(body));
            return;
          }
          self.state.events = (body.result && body.result.events) || [];
          var sel = document.getElementById('ci-event-select');
          if (!sel) return;
          sel.innerHTML = '';
          var pub = self.state.events.filter(function(e) { return e.status === 'published' || e.listing_active; });
          var list = (pub.length ? pub : self.state.events);
          list.forEach(function(e) {
            var opt = document.createElement('option');
            opt.value = e.listing_id || e.id;
            opt.textContent = e.title + (e.status ? ' (' + e.status + ')' : '');
            sel.appendChild(opt);
          });
          if (list.length === 0) {
            sel.innerHTML = '<option value="">No events available</option>';
            return;
          }
          var pre = localStorage.getItem('ecc-ci-event') || '';
          var preferred = self.state.events.some(function(e) { return (e.listing_id || e.id) === pre; }) ? pre : (list[0].listing_id || list[0].id);
          sel.value = preferred;
          self.switchEvent(preferred);
        });

      this.state.refreshTimer = setInterval(function() {
        var mod = document.getElementById('mod-check-in');
        if (!mod || mod.offsetParent === null || !self.state.listingId) return;
        if (self.state.decision && self.state.decision.decision !== 'ALLOW') return;
        self.loadWorkspace();
      }, 15000);

      var input = document.getElementById('ci-scan-input');
      if (input) input.addEventListener('keydown', function(ev) { if (ev.key === 'Enter') { ev.preventDefault(); self.scanInput(); } });

      this.setupCamera();
    },

    updateIdentity: function() {
      var d = document.getElementById('ci-device-badge');
      if (d) d.textContent = 'DEV: ' + this.state.deviceId;
      var l = document.getElementById('ci-device-line');
      if (l) l.textContent = 'Device: ' + this.state.deviceId;
      var o = document.getElementById('ci-operator-line');
      if (o) o.textContent = 'Operator: ' + this.state.operator;
      var ob = document.getElementById('ci-operator-badge');
      if (ob) {
        var ot = document.getElementById('ci-operator-text');
        if (ot) ot.textContent = this.state.operator;
      }
    },

    newDeviceId: function() {
      this.state.deviceId = 'SCN-' + Math.random().toString(16).slice(2, 6).toUpperCase();
      localStorage.setItem('ecc-ci-device', this.state.deviceId);
      this.updateIdentity();
      window.eccNotify('Scanner identity: ' + this.state.deviceId);
    },

    switchEvent: function(id) {
      if (this.state.decision && this.state.decision.decision !== 'ALLOW') {
        if (!window.confirm('A pending scan decision will be cleared. Switch event?')) {
          var sel = document.getElementById('ci-event-select');
          if (sel) sel.value = this.state.listingId;
          return;
        }
      }
      this.state.listingId = id || '';
      this.hideDecision();
      if (this.state.cameraOn) this.stopCamera();
      if (!this.state.listingId) return;
      localStorage.setItem('ecc-ci-event', this.state.listingId);
      this.loadWorkspace();
      var input = document.getElementById('ci-scan-input');
      if (input) input.focus();
    },

    setGate: function(g) {
      this.state.gate = g || 'Gate A';
      localStorage.setItem('ecc-ci-gate', this.state.gate);
    },

    setMode: function(mode, boot) {
      this.state.mode = mode;
      if (!boot) localStorage.setItem('ecc-ci-mode', mode);
      var op = document.getElementById('ci-view-operator');
      var cmd = document.getElementById('ci-view-command');
      var btnOp = document.getElementById('ci-mode-op');
      var btnCmd = document.getElementById('ci-mode-cmd');
      if (op) op.style.display = mode === 'operator' ? 'grid' : 'none';
      if (cmd) cmd.style.display = mode === 'command' ? 'flex' : 'none';
      if (btnOp) btnOp.className = mode === 'operator' ? 'ecc-btn ecc-btn-primary ci-icon-btn' : 'ecc-btn ecc-btn-secondary ci-icon-btn';
      if (btnCmd) btnCmd.className = mode === 'command' ? 'ecc-btn ecc-btn-primary ci-icon-btn' : 'ecc-btn ecc-btn-secondary ci-icon-btn';
      if (btnCmd) btnCmd.style.border = 'none';
      if (mode !== 'operator' && this.state.cameraOn) this.stopCamera();
      if (mode === 'operator') {
        var input = document.getElementById('ci-scan-input');
        if (input) input.focus();
      }
    },

    refreshConnection: function() {
      var el = document.getElementById('ci-conn-status');
      var text = document.getElementById('ci-conn-text');
      var dot = document.getElementById('ci-live-dot');
      var banner = document.getElementById('ci-offline-banner');
      var online = this.state.online && navigator.onLine !== false;
      if (!online) {
        if (el) { el.style.background = 'rgba(234,88,12,0.1)'; el.style.color = '#ea580c'; }
        if (text) text.textContent = 'Offline Mode';
        if (dot && dot.textContent !== 'CLOSED') dot.style.color = '#ea580c';
        if (banner) { banner.style.display = 'flex'; this.renderQueue(); }
      } else {
        if (el) { el.style.background = 'rgba(16,185,129,0.1)'; el.style.color = '#10b981'; }
        if (text) text.textContent = 'Connected';
        if (dot && dot.textContent !== 'CLOSED') dot.style.color = '#10b981';
        if (banner) banner.style.display = 'none';
      }
    },

    /* ── Workspace ──────────────────────────────────────────── */
    loadWorkspace: function() {
      var self = this;
      this.get('workspace').then(function(body) {
        if (!body.success) {
          self.state.online = false;
          self.refreshConnection();
          return;
        }
        self.state.online = true;
        self.refreshConnection();
        var w = body.result || {};
        self.state.workspace = w;
        self.renderCounters(w);
        self.renderOperatorPanel(w);
        self.renderCommand(w);
        self.populateGates(w.gates_available);
      }).catch(function() {
        self.state.online = false;
        self.refreshConnection();
      });
    },

    renderCounters: function(w) {
      var c = w.counters || {};
      var set = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = (val === null || val === undefined) ? '—' : String(val); };
      set('ci-kpi-checked', Number(c.checked_in || 0).toLocaleString());
      set('ci-kpi-expected', Number(c.total || 0).toLocaleString());
      set('ci-kpi-remaining', Number(c.remaining || 0).toLocaleString());
      set('ci-kpi-today', Number(c.today || 0).toLocaleString());
      set('ci-kpi-last15', Number(c.last_15 || 0).toLocaleString());
      set('ci-kpi-checked-rate', (c.checkin_rate || 0) + '% of expected');
      set('ci-rate-min', (c.rate_per_min || 0) + '');
      set('ci-rate-peak', c.peak_rate_per_min || 0);
      var fill = document.getElementById('ci-arrival-fill');
      if (fill) fill.style.width = Math.min(c.checkin_rate || 0, 100) + '%';
      var label = document.getElementById('ci-arrival-label');
      if (label) label.textContent = Number(c.checked_in || 0).toLocaleString() + ' arrived of ' + Number(c.total || 0).toLocaleString();
      var el = document.getElementById('ci-event-line');
      if (el && w.listing) el.textContent = (w.listing.title || '') + (w.listing.venue_name ? ' · ' + w.listing.venue_name : '');
    },

    renderOperatorPanel: function(w) {
      if (w.phase === 'closed') {
        var dot = document.getElementById('ci-live-dot');
        if (dot) { dot.textContent = 'CLOSED'; dot.style.color = '#64748b'; }
      }
    },

    renderCommand: function(w) {
      var c = w.counters || {};
      var set = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = (val === null || val === undefined) ? '—' : String(val); };
      set('cmd-kpi-checked', Number(c.checked_in || 0).toLocaleString());
      set('cmd-kpi-checked-sub', (c.checkin_rate || 0) + '% · ' + Number(c.today || 0) + ' today');
      set('cmd-kpi-expected', Number(c.total || 0).toLocaleString());
      set('cmd-kpi-remaining', Number(c.remaining || 0).toLocaleString());
      set('cmd-kpi-rate', (c.checkin_rate || 0) + '%');
      set('cmd-kpi-ratepermin', c.rate_per_min || 0);
      set('cmd-kpi-ratepermin-sub', 'people/min · peak ' + (c.peak_rate_per_min || 0));
      var st = w.stats || {};
      set('cmd-kpi-scans', st.total_scans || 0);
      set('cmd-kpi-scans-sub', (st.rejection_rate || 0) + '% rejected · ' + (st.duplicates || 0) + ' dup attempts');
      this.renderGates(w.gates || []);
      this.renderActivity(w.activity || []);
      this.renderDevices(w.devices || []);
      this.renderInsights(w.insights || []);
      this.renderAudit(w.activity || []);
      this.renderFinalReport(w);
    },

    renderGates: function(gates) {
      var el = document.getElementById('cmd-gates-list');
      if (!el) return;
      if (gates.length === 0) { el.innerHTML = '<div class="ecc-tk-empty">No gate data yet.</div>'; return; }
      var max = Math.max.apply(null, gates.map(function(g) { return g.count; })) || 1;
      var h = '';
      gates.forEach(function(g) {
        var pct = Math.round(100 * g.count / max);
        var active = (g.rate_per_min || 0) > 0;
        h += '<div style="display:flex;align-items:center;gap:0.7rem;">';
        h += '<span style="width:8px;height:8px;border-radius:50%;background:' + (active ? '#10b981' : '#64748b') + ';' + (active ? 'box-shadow:0 0 0 3px rgba(16,185,129,0.2);' : '') + ';flex-shrink:0;"></span>';
        h += '<div style="flex:1;">';
        h += '<div style="display:flex;justify-content:space-between;font-size:0.76rem;"><strong>' + tkEsc(g.gate) + '</strong><span style="color:var(--ecc-text-dim);font-weight:600;">' + g.count + ' · ' + (g.rate_per_min || 0) + '/min</span></div>';
        h += '<div style="height:7px;background:var(--ecc-surface-2);border-radius:999px;overflow:hidden;margin-top:0.25rem;"><div style="width:' + pct + '%;height:100%;background:#10b981;border-radius:999px;"></div></div>';
        h += '</div></div>';
      });
      el.innerHTML = h;
    },

    renderActivity: function(activity) {
      var el = document.getElementById('cmd-activity-list');
      if (!el) return;
      if (activity.length === 0) { el.innerHTML = '<div class="ecc-tk-empty">No scans yet — activity will appear here in real time.</div>'; return; }
      var h = '';
      activity.slice(0, 15).forEach(function(a) {
        var icon = ciIcon('check', 12), color = '#10b981';
        if (a.decision === 'DENY') { icon = ciIcon('x', 12); color = '#ef4444'; }
        else if (a.decision === 'REVIEW') { icon = ciIcon('warn', 12); color = '#f59e0b'; }
        else if (a.source === 'exit') { icon = ciIcon('exit', 12); color = '#8b5cf6'; }
        else if (a.source === 'override') { icon = ciIcon('star', 12); color = '#8b5cf6'; }
        h += '<div style="display:flex;align-items:center;gap:0.6rem;padding:0.4rem 0.5rem;border-radius:8px;background:var(--ecc-surface-2);">';
        h += '<span style="width:22px;height:22px;border-radius:50%;background:' + color + ';color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' + icon + '</span>';
        h += '<div style="flex:1;min-width:0;"><div style="font-size:0.78rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + tkEsc(a.holder_name || (a.ticket_id || 'Unknown')) + (a.reason_code ? ' <span style="color:' + color + ';">' + tkEsc(a.reason_code.replace(/_/g, ' ')) + '</span>' : '') + '</div>';
        h += '<div style="font-size:0.65rem;color:var(--ecc-text-dim);">' + tkEsc(a.ticket_type_name || '') + (a.gate ? ' · ' + tkEsc(a.gate) : '') + ' · ' + tkEsc(a.source) + '</div></div>';
        h += '<div style="font-size:0.65rem;color:var(--ecc-text-dim);white-space:nowrap;">' + tkDateTime(a.at) + '</div>';
        h += '</div>';
      });
      el.innerHTML = h;
    },

    renderDevices: function(devices) {
      var el = document.getElementById('cmd-devices-list');
      if (!el) return;
      if (devices.length === 0) { el.innerHTML = '<div class="ecc-tk-empty">No scanner devices recorded in the last 24 hours.</div>'; return; }
      var h = '';
      devices.forEach(function(d) {
        h += '<div style="display:flex;align-items:center;gap:0.6rem;padding:0.45rem 0.6rem;border-radius:8px;background:var(--ecc-surface-2);">';
        h += '<span style="width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,0.2);flex-shrink:0;"></span>';
        h += '<div style="flex:1;"><div style="font-size:0.78rem;font-weight:700;font-family:monospace;">' + tkEsc(d.device_id) + '</div>';
        h += '<div style="font-size:0.65rem;color:var(--ecc-text-dim);">' + d.scans + ' scans · last seen ' + tkDateTime(d.last_seen) + '</div></div>';
        h += '</div>';
      });
      el.innerHTML = h;
    },

    renderInsights: function(insights) {
      var el = document.getElementById('cmd-insights');
      if (!el) return;
      if (insights.length === 0) { el.innerHTML = '<div class="ecc-tk-empty">No operational insights yet.</div>'; return; }
      var h = '';
      insights.forEach(function(i) {
        var cls = i.level === 'warn' ? 'ecc-tk-insight warn' : 'ecc-tk-insight info';
        h += '<div class="' + cls + '" style="display:flex;align-items:flex-start;gap:0.45rem;"><span style="display:inline-flex;margin-top:1px;">' + (i.level === 'warn' ? ciIcon('warn', 13) : ciIcon('bulb', 13)) + '</span><span>' + tkEsc(i.message) + '</span></div>';
      });
      el.innerHTML = h;
    },

    renderAudit: function(activity) {
      var body = document.getElementById('cmd-audit-body');
      if (!body) return;
      if (activity.length === 0) { body.innerHTML = '<tr><td colspan="8"><div class="ecc-tk-empty">No scans recorded yet.</div></td></tr>'; return; }
      var pill = function(d, r) {
        var cls = 'green';
        if (d === 'DENY') cls = 'rose';
        else if (d === 'REVIEW') cls = 'amber';
        else if (r === 'SUPERVISOR_OVERRIDE' || r === 'EXIT') cls = 'purple';
        return '<span class="ecc-pill ' + cls + '" style="font-size:0.6rem;">' + tkEsc(d === 'ALLOW' ? (r === 'SUPERVISOR_OVERRIDE' ? 'ALLOW*' : (r === 'EXIT' ? 'EXIT' : 'ALLOW')) : d) + '</span>';
      };
      var h = '';
      activity.forEach(function(a) {
        h += '<tr>';
        h += '<td style="font-size:0.7rem;white-space:nowrap;">' + tkDateTime(a.at) + '</td>';
        h += '<td>' + pill(a.decision, a.reason_code) + '</td>';
        h += '<td style="font-size:0.7rem;font-family:monospace;">' + tkEsc(a.ticket_id || '—') + '</td>';
        h += '<td style="font-size:0.7rem;color:var(--ecc-text-dim);">' + tkEsc(a.reason_code || '—') + '</td>';
        h += '<td style="font-size:0.7rem;">' + tkEsc(a.gate || '—') + '</td>';
        h += '<td style="font-size:0.7rem;font-family:monospace;">' + tkEsc(a.device_id || '—') + '</td>';
        h += '<td style="font-size:0.7rem;">' + tkEsc(a.operator || '—') + '</td>';
        h += '<td style="font-size:0.7rem;">' + tkEsc(a.source) + '</td>';
        h += '</tr>';
      });
      body.innerHTML = h;
    },

    renderFinalReport: function(w) {
      var panel = document.getElementById('cmd-final-report');
      if (!panel) return;
      if (w.phase !== 'closed' || !w.final_report) { panel.style.display = 'none'; return; }
      var r = w.final_report;
      var h = '';
      h += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;margin-bottom:0.9rem;">';
      h += '<div style="background:var(--ecc-surface-2);border-radius:10px;padding:0.8rem;text-align:center;"><div style="font-size:1.3rem;font-weight:900;color:var(--ecc-primary);">' + Number(r.checked_in || 0).toLocaleString() + '</div><div style="font-size:0.66rem;color:var(--ecc-text-dim);font-weight:700;">CHECKED IN</div></div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:10px;padding:0.8rem;text-align:center;"><div style="font-size:1.3rem;font-weight:900;">' + (r.attendance_rate || 0) + '%</div><div style="font-size:0.66rem;color:var(--ecc-text-dim);font-weight:700;">ATTENDANCE</div></div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:10px;padding:0.8rem;text-align:center;"><div style="font-size:1.3rem;font-weight:900;color:#f59e0b;">' + Number(r.did_not_arrive || 0).toLocaleString() + '</div><div style="font-size:0.66rem;color:var(--ecc-text-dim);font-weight:700;">DID NOT ARRIVE</div></div>';
      h += '</div>';
      (r.by_type || []).forEach(function(t) {
        h += '<div style="display:flex;justify-content:space-between;font-size:0.78rem;padding:0.3rem 0;border-bottom:1px dashed var(--ecc-border);"><span><strong>' + tkEsc(t.name) + '</strong> <span style="color:var(--ecc-text-dim);font-size:0.7rem;">(' + tkEsc(t.category || '—') + ')</span></span><span>' + t.arrived + '/' + t.total + ' · ' + t.rate + '%</span></div>';
      });
      h += '<div style="margin-top:0.9rem;"><button type="button" class="ecc-btn ecc-btn-secondary ci-icon-btn" style="font-size:0.74rem;" onclick="switchEccModule(\'analytics\')">View Attendance Report ' + ciIcon('next', 13) + '</button></div>';
      panel.style.display = 'block';
      panel.innerHTML = '<div class="ecc-card" style="background:linear-gradient(135deg,var(--ecc-surface-2),var(--ecc-surface));"><h3 style="font-size:1rem;margin:0 0 0.4rem 0;">EVENT CHECK-IN CLOSED — Final Attendance</h3>' + h + '</div>';
    },

    populateGates: function(available) {
      var sel = document.getElementById('ci-gate-select');
      if (!sel || !available || available.length === 0) return;
      var cur = this.state.gate;
      sel.innerHTML = '';
      available.forEach(function(g) {
        var opt = document.createElement('option');
        opt.value = g;
        opt.textContent = g;
        sel.appendChild(opt);
      });
      if (cur && sel.querySelector('option[value="' + cur + '"]')) sel.value = cur;
      else { sel.value = available[0]; this.state.gate = available[0]; }
    },

    /* ── Scanning ──────────────────────────────────────────── */
    scanInput: function() {
      var input = document.getElementById('ci-scan-input');
      if (!input) return;
      var code = String(input.value || '').trim();
      if (code === '') { window.eccNotify('Enter or scan a ticket code.'); input.focus(); return; }
      input.value = '';
      this.submitScan(code);
    },

    submitScan: function(code) {
      var self = this;
      var key = 'ck-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
      if (!this.state.online || navigator.onLine === false) {
        this.state.queue.push({ code: code, gate: this.state.gate, device_id: this.state.deviceId, idempotency_key: key });
        this.persistQueue();
        this.renderQueue();
        this.refreshConnection();
        window.eccNotify('Offline — scan queued for synchronization (' + this.state.queue.length + ' pending)');
        return;
      }
      this.showScanning();
      this.post({ action: 'scan', code: code, gate: this.state.gate, device_id: this.state.deviceId, idempotency_key: key })
        .then(function(body) {
          if (!body.success) {
            if (body.error && body.error.type === 'network') { self.state.online = false; self.refreshConnection(); self.submitScanOffline(code, key); return; }
            window.eccNotify(self.errMsg(body));
            self.hideDecision();
            return;
          }
          var d = (body.result && body.result.decision) || {};
          self.state.online = true;
          self.refreshConnection();
          self.renderDecision(d);
        })
        .catch(function() {
          self.submitScanOffline(code, key);
        });
    },

    submitScanOffline: function(code, key) {
      this.state.queue.push({ code: code, gate: this.state.gate, device_id: this.state.deviceId, idempotency_key: key });
      this.persistQueue();
      this.renderQueue();
      this.state.online = false;
      this.refreshConnection();
      window.eccNotify('Connection lost — scan queued locally.');
    },

    persistQueue: function() {
      try { localStorage.setItem('ecc-ci-queue', JSON.stringify(this.state.queue.slice(0, 100))); } catch (e) {}
    },

    renderQueue: function() {
      var el = document.getElementById('ci-queue-count');
      if (el && this.state.queue.length > 0) el.textContent = this.state.queue.length + ' scans pending synchronization.';
      if (el && this.state.queue.length === 0) el.textContent = '';
    },

    flushQueue: function() {
      var self = this;
      if (this.state.queue.length === 0) return;
      var sync = document.getElementById('ci-sync-banner');
      var prog = document.getElementById('ci-sync-progress');
      var total = this.state.queue.length;
      var done = 0;
      if (sync) sync.style.display = 'block';
      if (prog) prog.textContent = '0 / ' + total;
      var next = function() {
        var item = self.state.queue[0];
        if (!item) {
          if (sync) setTimeout(function() { sync.style.display = 'none'; }, 2500);
          self.persistQueue();
          self.loadWorkspace();
          return;
        }
        self.post({ action: 'scan', code: item.code, gate: item.gate, device_id: item.device_id, idempotency_key: item.idempotency_key })
          .then(function(body) {
            self.state.queue.shift();
            done++;
            if (prog) prog.textContent = done + ' / ' + total;
            if (!body.success && (!body.error || body.error.type !== 'network')) window.eccNotify(self.errMsg(body));
            next();
          })
          .catch(function() { next(); });
      };
      next();
    },

    /* ── Decision card ─────────────────────────────────────── */
    showScanning: function() {
      var card = document.getElementById('ci-decision-card');
      var body = document.getElementById('ci-decision-body');
      if (card) card.style.display = 'block';
      if (body) body.innerHTML = '<div style="text-align:center;padding:2rem;"><div style="font-size:1.2rem;font-weight:900;">Validating…</div><div style="font-size:0.75rem;color:var(--ecc-text-dim);margin-top:0.3rem;">server-side ticket validation pipeline</div></div>';
    },

    renderDecision: function(d) {
      var self = this;
      this.state.decision = d;
      var card = document.getElementById('ci-decision-card');
      var body = document.getElementById('ci-decision-body');
      if (!card || !body) return;
      card.style.display = 'block';
      clearTimeout(this.state.decisionTimer);

      var attendee = d.attendee || {};
      var ticket = d.ticket || {};
      var att = d.attendance || {};
      var det = d.details || {};
      var now = new Date();
      var time = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      var h = '';

      if (d.decision === 'ALLOW') {
        var override = d.reason_code === 'SUPERVISOR_OVERRIDE';
        h += '<div class="ecc-ci-verdict allow"><div class="ecc-ci-verdict-icon">' + ciIcon('check', 56) + '</div>';
        h += '<div class="ecc-ci-verdict-title">' + (override ? 'ADMITTED (OVERRIDE)' : 'ADMITTED') + '</div>';
        h += '<div class="ecc-ci-verdict-name">' + tkEsc(attendee.name || 'Attendee') + '</div>';
        h += '<div class="ecc-ci-verdict-type">' + tkEsc(ticket.type || 'Ticket') + (d.access && d.access.zone ? ' · Access: ' + tkEsc(d.access.zone) : '') + '</div>';
        h += '<div class="ecc-ci-verdict-meta">' + tkEsc(d.gate.name || '') + ' • ' + time + (override ? '<br><span style="font-size:0.68rem;">' + tkEsc(d.override && d.override.reason || '') + '</span>' : '') + '</div>';
        h += '<div class="ecc-ci-verdict-req">request: ' + tkEsc(d.request_id) + '</div>';
        h += '<button type="button" class="ecc-btn ecc-btn-secondary ecc-ci-next-btn ci-icon-btn" onclick="CheckInControlCenter.dismissDecision()">Next Person ' + ciIcon('next', 13) + '</button>';
        h += '</div>';
        body.innerHTML = h;
        card.style.borderColor = 'var(--ecc-green)';
        card.style.background = 'linear-gradient(160deg, #065f46, #064e3b)';
        this.beep(true);
        this.state.decisionTimer = setTimeout(function() { self.dismissDecision(); }, 4000);
      } else {
        var isReview = d.decision === 'REVIEW';
        var code = d.reason_code || '';
        var title = 'ENTRY DENIED';
        if (code === 'ALREADY_CHECKED_IN') title = 'ALREADY CHECKED IN';
        else if (code === 'PAYMENT_PENDING') title = 'PAYMENT INCOMPLETE';
        else if (code === 'ACCESS_RESTRICTED') title = 'ACCESS RESTRICTED';
        else if (code === 'WRONG_EVENT') title = 'WRONG EVENT';
        else if (code === 'TICKET_CANCELLED') title = 'TICKET CANCELLED';
        else if (code === 'TICKET_REFUNDED') title = 'TICKET REFUNDED';
        else if (code === 'PAYMENT_FAILED') title = 'PAYMENT FAILED';
        else if (code === 'SIGNATURE_MISMATCH') title = 'SECURITY CHECK FAILED';
        var tone = isReview ? '#b45309' : '#b91c1c';
        var bg = isReview ? 'linear-gradient(160deg, #78350f, #451a03)' : 'linear-gradient(160deg, #7f1d1d, #450a0a)';
        h += '<div class="ecc-ci-verdict ' + (isReview ? 'review' : 'deny') + '"><div class="ecc-ci-verdict-icon">' + (isReview ? ciIcon('warn', 56) : ciIcon('x', 56)) + '</div>';
        h += '<div class="ecc-ci-verdict-title">' + title + '</div>';
        h += '<div class="ecc-ci-verdict-meta">' + tkEsc(d.message || '') + '</div>';
        if (attendee.name) h += '<div class="ecc-ci-verdict-name">' + tkEsc(attendee.name) + '</div>';
        if (ticket.type) h += '<div class="ecc-ci-verdict-type">' + tkEsc(ticket.type) + ' · ' + tkEsc(d.ticket.id || '') + '</div>';
        if (code === 'WRONG_EVENT' && det.ticket_event) {
          h += '<div style="font-size:0.78rem;margin-top:0.5rem;color:var(--ecc-text-dim);">This ticket belongs to: <strong>' + tkEsc(det.ticket_event) + '</strong><br>Current event: <strong>' + tkEsc(d.event && d.event.title || '') + '</strong></div>';
        }
        if (code === 'ACCESS_RESTRICTED') {
          h += '<div style="font-size:0.78rem;margin-top:0.5rem;color:var(--ecc-text-dim);">Requested: <strong>' + tkEsc(det.requested_gate || d.gate.name || '') + '</strong> · Allowed: <strong>' + tkEsc((det.allowed_zones || []).join(', ')) + '</strong></div>';
        }
        if (code === 'ALREADY_CHECKED_IN') {
          h += '<div style="font-size:0.78rem;margin-top:0.5rem;background:rgba(180,83,9,0.15);border-radius:8px;padding:0.5rem 0.7rem;">';
          h += 'First entry: <strong>' + tkEsc(att.first_entry_gate || '—') + '</strong> at <strong>' + tkDateTime(att.first_entry_at) + '</strong>';
          h += '<br><span style="color:var(--ecc-text-dim);font-size:0.7rem;">Current attempt: ' + tkEsc(d.gate.name || '') + ' • ' + time + '</span>';
          h += '</div>';
        }
        h += '<div class="ecc-ci-verdict-req">request: ' + tkEsc(d.request_id) + '</div>';
        h += '<div style="display:flex;gap:0.5rem;flex-wrap:wrap;justify-content:center;margin-top:1.1rem;">';
        if (d.ticket && d.ticket.id) {
          h += '<button type="button" class="ecc-btn ecc-btn-secondary ecc-ci-next-btn" style="background:rgba(255,255,255,0.12);border:none;" onclick="CheckInControlCenter.reviewAttendee(\'' + tkEsc(d.ticket.id) + '\')">Review Attendee</button>';
          if (isReview) h += '<button type="button" class="ecc-btn ecc-btn-primary ecc-ci-next-btn" onclick="CheckInControlCenter.openOverride(\'' + tkEsc(d.ticket.id) + '\')">Supervisor Override</button>';
          if (code === 'ALREADY_CHECKED_IN' || (att.status === 'CHECKED_IN')) h += '<button type="button" class="ecc-btn ecc-btn-secondary ecc-ci-next-btn" style="background:rgba(255,255,255,0.12);border:none;" onclick="CheckInControlCenter.recordExit(\'' + tkEsc(d.ticket.id) + '\')">Record Exit</button>';
        }
        h += '<button type="button" class="ecc-btn ecc-btn-secondary ecc-ci-next-btn" style="background:rgba(255,255,255,0.12);border:none;" onclick="CheckInControlCenter.openLookup()">Manual Lookup</button>';
        h += '<button type="button" class="ecc-btn ecc-btn-secondary ecc-ci-next-btn ci-icon-btn" style="background:rgba(255,255,255,0.12);border:none;" onclick="CheckInControlCenter.dismissDecision()">Next Person ' + ciIcon('next', 13) + '</button>';
        h += '</div></div>';
        body.innerHTML = h;
        card.style.borderColor = tone;
        card.style.background = bg;
        this.beep(false);
      }
      this.loadWorkspace();
    },

    dismissDecision: function() {
      this.state.decision = null;
      clearTimeout(this.state.decisionTimer);
      var card = document.getElementById('ci-decision-card');
      if (card) card.style.display = 'none';
      var input = document.getElementById('ci-scan-input');
      if (input) input.focus();
      this.loadWorkspace();
    },

    hideDecision: function() {
      clearTimeout(this.state.decisionTimer);
      this.state.decision = null;
      var card = document.getElementById('ci-decision-card');
      if (card) card.style.display = 'none';
    },

    reviewAttendee: function(ticketId) {
      this.hideDecision();
      if (window.AttendeesControlCenter) AttendeesControlCenter.openDrawer(ticketId);
    },

    recordExit: function(ticketId) {
      var self = this;
      this.post({ action: 'exit', ticket_id: ticketId, gate: this.state.gate, device_id: this.state.deviceId }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Exit recorded for ' + ticketId);
        self.dismissDecision();
      });
    },

    beep: function(ok) {
      try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        if (ok) {
          osc.frequency.value = 880;
          gain.gain.value = 0.12;
          osc.start();
          osc.stop(ctx.currentTime + 0.15);
        } else {
          osc.type = 'square';
          osc.frequency.value = 220;
          gain.gain.value = 0.1;
          osc.start();
          osc.stop(ctx.currentTime + 0.4);
        }
      } catch (e) {}
    },

    /* ── Camera QR scanning ────────────────────────────────── */
    cameraSupported: function() {
      return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    },

    setupCamera: function() {
      var btn = document.getElementById('ci-cam-btn');
      if (!btn) return;
      btn.style.display = 'inline-flex';
      try {
        if ('BarcodeDetector' in window) {
          this.state.detector = new BarcodeDetector({ formats: ['qr_code'] });
        }
      } catch (e) {}
    },

    scanDemoTicket: function() {
      var code = 'UTH-VIP-004821';
      var input = document.getElementById('ci-scan-input');
      if (input) input.value = code;
      this.submitScan(code);
    },

    toggleCamera: function() {
      if (this.state.cameraOn) { this.stopCamera(); return; }
      var self = this;
      var btn = document.getElementById('ci-cam-btn');
      var hint = document.getElementById('ci-scan-hint');
      if (btn) btn.classList.add('active');
      if (hint) hint.textContent = 'Point the ticket QR at the camera…';

      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        window.eccNotify('Camera device not available on this context — enter ticket ID or use Test Scan.');
        return;
      }

      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } } })
        .then(function(stream) {
          self.state.camStream = stream;
          self.state.cameraOn = true;
          var video = document.getElementById('ci-cam-video');
          var ph = document.getElementById('ci-scanner-placeholder');
          var line = document.getElementById('ci-scanline');
          var err = document.getElementById('ci-cam-error');
          var frame = document.getElementById('ci-scanner-frame');
          if (video) { video.srcObject = stream; video.style.display = 'block'; video.play().catch(function() {}); }
          if (ph) ph.style.display = 'none';
          if (err) err.style.display = 'none';
          if (line) line.style.display = 'block';
          if (frame) frame.classList.add('scanning');
          self.camLoop();
        })
        .catch(function(e) {
          var err = document.getElementById('ci-cam-error');
          if (err) {
            err.style.display = 'block';
            err.textContent = 'Camera stream active / type ticket ID or click Test Scan below.';
          }
          if (btn) btn.classList.remove('active');
          if (hint) hint.textContent = 'Position ticket QR in frame or type the ticket ID';
        });
    },

    stopCamera: function() {
      this.state.cameraOn = false;
      if (this.state.camTimer) { clearInterval(this.state.camTimer); this.state.camTimer = null; }
      if (this.state.camStream) {
        this.state.camStream.getTracks().forEach(function(t) { t.stop(); });
        this.state.camStream = null;
      }
      var video = document.getElementById('ci-cam-video');
      var ph = document.getElementById('ci-scanner-placeholder');
      var line = document.getElementById('ci-scanline');
      var err = document.getElementById('ci-cam-error');
      var frame = document.getElementById('ci-scanner-frame');
      var btn = document.getElementById('ci-cam-btn');
      var hint = document.getElementById('ci-scan-hint');
      if (video) { video.style.display = 'none'; video.srcObject = null; }
      if (ph) ph.style.display = '';
      if (line) line.style.display = 'none';
      if (err) err.style.display = 'none';
      if (frame) frame.classList.remove('scanning');
      if (btn) btn.classList.remove('active');
      if (hint) hint.textContent = 'Position ticket QR in frame or type the ticket ID';
    },

    camLoop: function() {
      var self = this;
      if (this.state.camTimer) clearInterval(this.state.camTimer);
      this.state.camTimer = setInterval(function() {
        if (!self.state.cameraOn || !self.state.detector || self.state.scanLock) return;
        var video = document.getElementById('ci-cam-video');
        if (!video || video.readyState < 2) return;
        var card = document.getElementById('ci-decision-card');
        if (card && card.style.display === 'block') return;
        self.state.detector.detect(video).then(function(codes) {
          if (!self.state.cameraOn || self.state.scanLock) return;
          for (var i = 0; i < codes.length; i++) {
            var v = String(codes[i].rawValue || '').trim();
            if (!v) continue;
            self.state.scanLock = true;
            self.submitScan(v);
            setTimeout(function() { self.state.scanLock = false; }, 1600);
            return;
          }
        }).catch(function() {});
      }, 350);
    },

    /* ── Manual lookup & admission ─────────────────────────── */
    openLookup: function() {
      var input = document.getElementById('ci-lookup-search');
      if (input) input.value = '';
      var res = document.getElementById('ci-lookup-results');
      if (res) res.innerHTML = '<div class="ecc-tk-empty">Type at least 2 characters to search.</div>';
      openEccModal('modal-ci-lookup');
      if (input) setTimeout(function() { input.focus(); }, 120);
    },

    lookupSearch: function(ev) {
      var self = this;
      var q = String(ev.target.value || '').trim();
      clearTimeout(this.state.lookupTimer);
      if (q.length < 2) {
        var res = document.getElementById('ci-lookup-results');
        if (res) res.innerHTML = '<div class="ecc-tk-empty">Type at least 2 characters to search.</div>';
        return;
      }
      this.state.lookupTimer = setTimeout(function() {
        self.get('lookup', { q: q, limit: 12 }).then(function(body) {
          var res = document.getElementById('ci-lookup-results');
          if (!res) return;
          if (!body.success) { res.innerHTML = '<div class="ecc-tk-empty">' + tkEsc(self.errMsg(body)) + '</div>'; return; }
          var list = (body.result && body.result.results) || [];
          if (list.length === 0) { res.innerHTML = '<div class="ecc-tk-empty">No attendees match “' + tkEsc(q) + '”.</div>'; return; }
          var h = '';
          list.forEach(function(r) {
            var attCls = 'green';
            var attTxt = 'CHECKED IN';
            if (r.attendance_status !== 'CHECKED_IN') { attCls = 'amber'; attTxt = 'NOT CHECKED IN'; }
            var payCls = r.payment_status === 'Complimentary' ? 'purple' : (r.payment_status === 'Paid' ? 'green' : 'amber');
            h += '<div style="display:flex;align-items:center;gap:0.8rem;background:var(--ecc-surface-2);border-radius:10px;padding:0.7rem 0.9rem;">';
            h += '<div style="flex:1;min-width:0;">';
            h += '<div style="font-weight:700;font-size:0.84rem;">' + tkEsc(r.name) + '</div>';
            h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);">' + tkEsc(r.ticket_type_name) + ' · <span style="font-family:monospace;">' + tkEsc(r.ticket_id) + '</span></div>';
            h += '<div style="display:flex;gap:0.4rem;margin-top:0.3rem;"><span class="ecc-pill ' + payCls + '" style="font-size:0.58rem;">' + tkEsc(r.payment_status) + '</span><span class="ecc-pill ' + attCls + '" style="font-size:0.58rem;">' + attTxt + '</span></div>';
            h += '</div>';
            h += '<button type="button" class="ecc-btn ' + (r.attendance_status === 'CHECKED_IN' ? 'ecc-btn-secondary' : 'ecc-btn-primary') + '" style="font-size:0.72rem;padding:0.3rem 0.8rem;white-space:nowrap;" onclick="CheckInControlCenter.admit(\'' + tkEsc(r.ticket_id) + '\')">' + (r.attendance_status === 'CHECKED_IN' ? 'Review' : 'Admit') + '</button>';
            h += '</div>';
          });
          res.innerHTML = h;
        });
      }, 300);
    },

    admit: function(ticketId) {
      var self = this;
      closeEccModal('modal-ci-lookup');
      this.showScanning();
      this.post({ action: 'manual', ticket_id: ticketId, gate: this.state.gate, device_id: this.state.deviceId })
        .then(function(body) {
          if (!body.success) { window.eccNotify(self.errMsg(body)); self.hideDecision(); return; }
          self.renderDecision((body.result && body.result.decision) || {});
        });
    },

    /* ── Supervisor override ───────────────────────────────── */
    openOverride: function(ticketId) {
      var info = document.getElementById('ci-override-info');
      if (info) info.innerHTML = 'Ticket: <strong style="font-family:monospace;">' + tkEsc(ticketId) + '</strong><br>Original decision: <strong>ALREADY CHECKED IN / CONFLICT</strong><br>Operator: <strong>' + tkEsc(this.state.operator) + '</strong> · Gate: <strong>' + tkEsc(this.state.gate) + '</strong> · Device: <strong>' + tkEsc(this.state.deviceId) + '</strong>';
      var reason = document.getElementById('ci-override-reason');
      if (reason) reason.value = '';
      this.state.overrideTicketId = ticketId;
      openEccModal('modal-ci-override');
    },

    submitOverride: function() {
      var self = this;
      var reason = document.getElementById('ci-override-reason');
      if (!String(reason.value || '').trim()) { window.eccNotify('An override reason is required.'); reason.focus(); return; }
      closeEccModal('modal-ci-override');
      this.showScanning();
      this.post({ action: 'override', ticket_id: this.state.overrideTicketId, reason: String(reason.value).trim(), gate: this.state.gate, device_id: this.state.deviceId })
        .then(function(body) {
          if (!body.success) { window.eccNotify(self.errMsg(body)); self.hideDecision(); return; }
          self.renderDecision((body.result && body.result.decision) || {});
        });
    }
  };

  CheckInControlCenter.init();

  /* ── Tickets Control Center Controller ───────────────────────── */
  var tkDoc = document.getElementById('events-workspace');
  var TK_COLORS = ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#0ea5e9', '#ef4444', '#64748b'];

  function tkEsc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function tkMoney(n, compact) {
    n = Number(n) || 0;
    if (compact && n >= 1000000) return 'MWK ' + (n / 1000000).toFixed(1) + 'M';
    if (compact && n >= 1000) return 'MWK ' + (n / 1000).toFixed(1) + 'K';
    return 'MWK ' + n.toLocaleString('en-MW', { maximumFractionDigits: 0 });
  }
  function tkDate(d) {
    if (!d) return '—';
    var dt = new Date(String(d).replace(' ', 'T'));
    if (isNaN(dt.getTime())) return String(d);
    return dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  function tkDateTime(d) {
    if (!d) return '—';
    var dt = new Date(String(d).replace(' ', 'T'));
    if (isNaN(dt.getTime())) return String(d);
    return dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + ' ' + dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
  }
  window.tkEsc = tkEsc;
  window.tkMoney = tkMoney;
  window.tkDate = tkDate;
  window.tkDateTime = tkDateTime;
  function tkStatusPill(status) {
    var s = String(status || '').toLowerCase();
    var cls = 'purple';
    if (s === 'active' || s === 'paid' || s === 'completed' || s === 'processed' || s === 'verified' || s === 'issued') cls = 'green';
    else if (s === 'sold_out' || s === 'failed' || s === 'cancelled' || s === 'rejected' || s === 'refunded' || s === 'closed' || s === 'archived' || s === 'unissued') cls = 'rose';
    else if (s === 'pending' || s === 'draft' || s === 'scheduled' || s === 'paused' || s === 'pending approval') cls = 'amber';
    else if (s === 'checked_in') cls = 'blue';
    return '<span class="ecc-pill ' + cls + '">' + tkEsc(String(status || '').toUpperCase()) + '</span>';
  }

  window.TicketsControlCenter = {
    state: {
      events: [], listingId: '', range: '7D',
      ordersFilter: 'all', ordersSearch: '',
      issuedFilter: 'all', issuedSearch: '',
      ordersLoaded: false, issuedLoaded: false, transfersLoaded: false, refundsLoaded: false,
      types: [], listing: null, kpis: null
    },

    get: function(action, params) {
      var qs = '?listing_id=' + encodeURIComponent(this.state.listingId) + '&action=' + encodeURIComponent(action);
      Object.keys(params || {}).forEach(function(k) { qs += '&' + k + '=' + encodeURIComponent(params[k]); });
      return fetch(tkApiBase + qs, { credentials: 'same-origin' }).then(function(r) { return r.json().catch(function() { return {}; }); });
    },
    post: function(data) {
      var payload = Object.assign({ listing_id: this.state.listingId }, data);
      var csrf = tkDoc && tkDoc.dataset ? tkDoc.dataset.csrf : '';
      return fetch(tkApiBase, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(payload)
      }).then(function(r) { return r.json().catch(function() { return {}; }); });
    },
    errMsg: function(body) {
      if (body && body.error && body.error.message) return body.error.message;
      return 'The request could not be completed.';
    },

    /* ── Boot & event context ─────────────────────────────── */
    init: function() {
      var self = this;
      fetch(tkApiBase + '?action=events', { credentials: 'same-origin' })
        .then(function(r) { return r.json().catch(function() { return {}; }); })
        .then(function(body) {
          if (!body.success) {
            window.eccNotify('Could not load event portfolio: ' + self.errMsg(body));
            return;
          }
          self.state.events = (body.result && body.result.events) || [];
          var sel = document.getElementById('ecc-tk-event-select');
          if (!sel) return;
          sel.innerHTML = '';
          var pub = self.state.events.filter(function(e) { return e.status === 'published' || e.listing_active; });
          var list = (pub.length ? pub : self.state.events);
          list.forEach(function(e) {
            var opt = document.createElement('option');
            opt.value = e.listing_id || e.id;
            opt.textContent = e.title + (e.status ? ' (' + e.status + ')' : '');
            sel.appendChild(opt);
          });
          if (list.length === 0) {
            sel.innerHTML = '<option value="">No events yet</option>';
            document.getElementById('ecc-tk-event-meta').textContent = 'Create an event to start selling tickets';
            return;
          }
          var preferred = list.filter(function(e) { return (e.tickets_total || 0) > 0; });
          var first = (preferred.length ? preferred : list)[0];
          sel.value = first.listing_id || first.id;
          self.switchEvent(sel.value);
        });
    },

    switchEvent: function(listingId) {
      if (!listingId) return;
      var ev = this.state.events.filter(function(e) { return (e.listing_id || e.id) === listingId; })[0] || {};
      this.state.listingId = listingId;
      this.state.ordersLoaded = this.state.issuedLoaded = this.state.transfersLoaded = this.state.refundsLoaded = false;
      var meta = document.getElementById('ecc-tk-event-meta');
      if (meta) {
        var bits = [];
        if (ev.start_date) bits.push('📅 ' + tkDate(ev.start_date));
        if (ev.venue_name) bits.push('📍 ' + ev.venue_name + (ev.venue_city ? ', ' + ev.venue_city : ''));
        meta.textContent = bits.length ? bits.join(' • ') : '—';
      }
      this.loadWorkspace();
    },

    /* ── Workspace render ─────────────────────────────────── */
    loadWorkspace: function() {
      var self = this;
      var chart = document.getElementById('tk-velocity-chart');
      if (chart) chart.innerHTML = '<div class="ecc-tk-empty">Loading workspace…</div>';
      this.get('workspace').then(function(body) {
        if (!body.success) {
          window.eccNotify('Workspace load failed: ' + self.errMsg(body));
          return;
        }
        var r = body.result || {};
        self.state.listing = r.listing || null;
        self.state.kpis = r.kpis || null;
        self.state.types = r.types || [];
        self.state.velocity = r.velocity || null;
        self.renderKpis();
        self.renderVelocity(self.state.velocity);
        self.renderDonut(self.state.types);
        self.renderInsights(r.insights || []);
        self.renderChannels(r.channels || []);
        self.renderTypes();
        self.renderTypesTable(r.types || []);
        self.renderRecentOrders(r.recent_orders || []);
        window.eccNotify('Tickets workspace refreshed');
      });
    },

    renderKpis: function() {
      var k = this.state.kpis;
      if (!k) return;
      var set = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
      var capacity = k.active_capacity || k.capacity || 0;
      set('tk-kpi-sold', Number(k.sold || 0).toLocaleString());
      set('tk-kpi-sold-sub', 'of ' + Number(k.capacity || 0).toLocaleString() + ' total');
      set('tk-kpi-revenue', tkMoney(k.revenue, true));
      set('tk-kpi-revenue-sub', 'Gross ticket sales');
      set('tk-kpi-avail', Number(k.available || 0).toLocaleString());
      set('tk-kpi-avail-sub', capacity ? Math.round(100 * k.available / capacity) + '% of active capacity' : 'of active capacity');
      set('tk-kpi-sellthrough', (Number(k.sell_through) || 0).toFixed(1) + '%');
      set('tk-kpi-sellthrough-sub', 'of total capacity sold');
      set('tk-kpi-pending', Number(k.pending_orders || 0).toLocaleString());
      set('tk-kpi-pending-sub', 'awaiting payment');
      set('tk-kpi-refunded', Number(k.refunded_count || 0).toLocaleString());
      set('tk-kpi-refunded-sub', 'tickets refunded');
    },

    renderVelocity: function(v) {
      var holder = document.getElementById('tk-velocity-chart');
      var labels = document.getElementById('tk-velocity-labels');
      if (!holder) return;
      if (!v || !v.series || !v.series.length) {
        holder.innerHTML = '<div class="ecc-tk-empty">No velocity data yet.</div>';
        if (labels) labels.innerHTML = '';
        return;
      }
      var series = v.series;
      var max = Math.max.apply(null, series.map(function(s) { return Number(s.count) || 0; }));
      var total = series.reduce(function(a, s) { return a + (Number(s.count) || 0); }, 0);
      if (max <= 0) {
        holder.innerHTML = '<div class="ecc-tk-empty">No tickets sold in the selected period.</div>';
        if (labels) labels.innerHTML = '';
        return;
      }
      var html = '<div class="ecc-tk-velocity-bars">';
      series.forEach(function(s) {
        var h = Math.max((Number(s.count) || 0) / max * 100, 2);
        html += '<div class="ecc-tk-velocity-bar" title="' + tkEsc(s.date) + ': ' + (Number(s.count) || 0) + ' tickets · ' + tkMoney(s.amount) + '"><div style="height:' + h.toFixed(1) + '%;"></div></div>';
      });
      html += '</div>';
      holder.innerHTML = html;
      if (labels) {
        var n = series.length;
        var pick = function(i) { return tkEsc(String(series[i].date).slice(5)); };
        var idxs = n <= 7 ? series.map(function(_, i) { return i; }) : [0, Math.floor((n - 1) / 3), Math.floor(2 * (n - 1) / 3), n - 1];
        labels.innerHTML = idxs.map(function(i) { return '<span>' + pick(i) + '</span>'; }).join('');
      }
      var peak = series[n - 1];
      this.state.velocity = v;
    },

    renderDonut: function(types) {
      var segs = document.getElementById('tk-donut-segments');
      var legend = document.getElementById('tk-donut-legend');
      var total = document.getElementById('tk-donut-total');
      if (!segs || !types || !types.length) {
        if (segs) segs.innerHTML = '';
        if (legend) legend.innerHTML = '<div class="ecc-tk-empty">No ticket types yet.</div>';
        if (total) total.textContent = '—';
        return;
      }
      var sold = types.map(function(t) { return Math.max(Number(t.sold) || 0, 0); });
      var sum = sold.reduce(function(a, b) { return a + b; }, 0);
      if (total) total.textContent = sum.toLocaleString();
      var CIRC = 2 * Math.PI * 15.91549430918954;
      var offset = 25;
      var segHtml = '';
      types.forEach(function(t, i) {
        var frac = sum > 0 ? sold[i] / sum : 0;
        if (frac <= 0) return;
        segHtml += '<circle cx="21" cy="21" r="15.91549430918954" fill="transparent" stroke="' + TK_COLORS[i % TK_COLORS.length] + '" stroke-width="6.5" stroke-dasharray="' + (frac * CIRC).toFixed(2) + ' ' + (CIRC - frac * CIRC).toFixed(2) + '" stroke-dashoffset="' + offset.toFixed(2) + '"/>';
        offset -= frac * CIRC;
      });
      segs.innerHTML = segHtml;
      var legHtml = '';
      types.forEach(function(t, i) {
        var frac = sum > 0 ? sold[i] / sum : 0;
        legHtml += '<div class="ecc-donut-legend-item"><span><span class="ecc-donut-dot" style="background:' + TK_COLORS[i % TK_COLORS.length] + ';"></span>' + tkEsc(t.name) + '</span><span>' + sold[i].toLocaleString() + ' (' + (frac * 100).toFixed(1) + '%)</span></div>';
      });
      legend.innerHTML = legHtml;
    },

    renderInsights: function(insights) {
      var el = document.getElementById('tk-insights');
      if (!el) return;
      if (!insights || !insights.length) { el.innerHTML = '<div class="ecc-tk-empty">No insights yet.</div>'; return; }
      el.innerHTML = insights.map(function(i) {
        var cls = i.level === 'warn' ? 'ecc-tk-insight warn' : (i.level === 'critical' ? 'ecc-tk-insight critical' : 'ecc-tk-insight');
        return '<div class="' + cls + '"><b>' + tkEsc((i.level || 'info').toUpperCase()) + '</b> ' + tkEsc(i.message) + '</div>';
      }).join('');
    },

    renderChannels: function(channels) {
      var el = document.getElementById('tk-channels');
      if (!el) return;
      if (!channels || !channels.length) { el.innerHTML = '<div class="ecc-tk-empty">No payment data yet.</div>'; return; }
      el.innerHTML = channels.map(function(c) {
        return '<div class="ecc-tk-channel"><div style="display:flex;justify-content:space-between;font-size:0.75rem;"><b>' + tkEsc(c.channel) + '</b><span>' + (Number(c.n) || 0) + ' orders</span></div>' +
          '<div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--ecc-text-dim);margin-top:0.15rem;"><span>' + tkMoney(c.amount) + '</span><span>' + (Number(c.share) || 0).toFixed(1) + '%</span></div></div>';
      }).join('');
    },

    renderTypesTable: function(types) {
      var body = document.getElementById('tk-types-table-body');
      if (!body) return;
      if (!types || !types.length) {
        body.innerHTML = '<tr><td colspan="8"><div class="ecc-tk-empty">No ticket types yet — create one with “+ Create Ticket Type”.</div></td></tr>';
        return;
      }
      var self = this;
      body.innerHTML = types.map(function(t, i) {
        var sold = Number(t.sold) || 0, cap = Number(t.total_quantity) || 0;
        var pct = cap > 0 ? Math.round(100 * sold / cap) : 0;
        return '<tr>' +
          '<td><div style="display:flex;align-items:center;gap:0.5rem;"><span class="ecc-donut-dot" style="background:' + TK_COLORS[i % TK_COLORS.length] + ';width:8px;height:8px;"></span><div><strong>' + tkEsc(t.name) + '</strong><div style="font-size:0.68rem;color:var(--ecc-text-dim);">' + tkEsc(t.description || t.category || '') + '</div></div></div></td>' +
          '<td><strong>' + tkMoney(t.price) + '</strong>' + (Number(t.fee_percent) ? '<div style="font-size:0.65rem;color:var(--ecc-text-dim);">' + (t.fee_percent) + '% fee</div>' : '') + '</td>' +
          '<td><div style="display:flex;flex-direction:column;gap:0.2rem;"><div style="display:flex;justify-content:space-between;font-size:0.7rem;font-weight:700;"><span>' + sold.toLocaleString() + ' / ' + cap.toLocaleString() + '</span><span style="color:var(--ecc-text-dim);">' + pct + '%</span></div><div class="ecc-progress-bar-wrap" style="height:5px;"><div class="ecc-progress-fill" style="width:' + pct + '%;background:' + TK_COLORS[i % TK_COLORS.length] + ';"></div></div></div></td>' +
          '<td><strong style="color:var(--ecc-text);">' + (Number(t.available) || 0).toLocaleString() + '</strong></td>' +
          '<td><strong>' + tkMoney(t.revenue, true) + '</strong></td>' +
          '<td><span>' + (Number(t.checked_in_count) || 0) + ' (' + (sold > 0 ? Math.round(100 * (Number(t.checked_in_count) || 0) / sold) : 0) + '%)</span></td>' +
          '<td>' + tkStatusPill(t.ticket_status) + '</td>' +
          '<td style="text-align:right;"><button type="button" class="ecc-btn ecc-btn-secondary" style="padding:0.25rem 0.65rem;font-size:0.72rem;" onclick="TicketsControlCenter.manageTicket(' + t.id + ')">Manage</button></td>' +
          '</tr>';
      }).join('');
    },

    renderRecentOrders: function(orders) {
      var el = document.getElementById('tk-recent-orders');
      if (el && orders && orders.length) {
        el.innerHTML = '<table class="ecc-table"><thead><tr><th>Order</th><th>Customer</th><th>Qty</th><th>Amount</th><th>Status</th></tr></thead><tbody>' + orders.map(function(o) {
          return '<tr><td><strong>' + tkEsc(o.id) + '</strong></td><td>' + tkEsc(o.customer_name) + '</td><td>' + (o.quantity) + '</td><td><strong>' + tkMoney(o.amount) + '</strong></td><td>' + tkStatusPill(o.status) + '</td></tr>';
        }).join('') + '</tbody></table>';
      }
    },

    setRange: function(range, btn) {
      this.state.range = range;
      var grp = btn.parentNode;
      Array.prototype.forEach.call(grp.children, function(b) {
        b.className = b === btn ? 'ecc-btn ecc-btn-primary' : 'ecc-btn ecc-btn-secondary';
        b.style.padding = '0.2rem 0.5rem'; b.style.fontSize = '0.68rem';
        if (b !== btn) b.style.border = 'none';
      });
      var self = this;
      this.get('velocity', { range: range }).then(function(body) {
        if (body.success && body.result && body.result.velocity) {
          self.renderVelocity(body.result.velocity);
        } else {
          window.eccNotify(self.errMsg(body));
        }
      });
    },

    /* ── Subtabs ──────────────────────────────────────────── */
    switchSubtab: function(tabId) {
      document.querySelectorAll('.ecc-tk-subtab').forEach(function(b) {
        b.classList.toggle('active', b.dataset.subtab === tabId);
      });
      document.querySelectorAll('.ecc-tk-tab-content').forEach(function(c) {
        c.style.display = c.id === ('ecc-tk-tab-' + tabId) ? 'block' : 'none';
      });
      if (tabId === 'orders' && !this.state.ordersLoaded) { this.state.ordersLoaded = true; this.loadOrders(); }
      if (tabId === 'issued' && !this.state.issuedLoaded) { this.state.issuedLoaded = true; this.loadIssued(); }
      if (tabId === 'transfers' && !this.state.transfersLoaded) { this.state.transfersLoaded = true; this.loadTransfers(); }
      if (tabId === 'refunds' && !this.state.refundsLoaded) { this.state.refundsLoaded = true; this.loadRefunds(); }
    },

    loadOrders: function() {
      var self = this;
      this.get('orders', { filter: this.state.ordersFilter, q: this.state.ordersSearch }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        var orders = (body.result && body.result.orders) || [];
        var tbody = document.getElementById('tk-orders-body');
        if (!tbody) return;
        if (!orders.length) {
          tbody.innerHTML = '<tr><td colspan="9"><div class="ecc-tk-empty">No orders match this view.</div></td></tr>';
          return;
        }
        tbody.innerHTML = orders.map(function(o) {
          return '<tr>' +
            '<td><strong>' + tkEsc(o.id) + '</strong></td>' +
            '<td>' + tkEsc(o.customer_name) + '<div style="font-size:0.68rem;color:var(--ecc-text-dim);">' + tkEsc(o.customer_email || '') + '</div></td>' +
            '<td>' + o.quantity + ' × ' + tkEsc(o.ticket_type_name || 'Ticket') + '</td>' +
            '<td><strong>' + tkMoney(o.amount) + '</strong></td>' +
            '<td>' + tkEsc(o.gateway) + '</td>' +
            '<td>' + tkStatusPill(o.status) + '</td>' +
            '<td>' + (o.issued_full ? '<span class="ecc-pill green">ISSUED</span>' : '<span class="ecc-pill amber">' + o.issued + '/' + o.quantity + ' ISSUED</span>') + '</td>' +
            '<td style="font-size:0.72rem;color:var(--ecc-text-dim);">' + tkDateTime(o.booked_at) + '</td>' +
            '<td><button class="ecc-btn ecc-btn-secondary" style="padding:0.25rem 0.65rem;font-size:0.72rem;" onclick="TicketsControlCenter.openDrawer(\'' + tkEsc(o.id) + '\')">View</button></td>' +
            '</tr>';
        }).join('');
      });
    },

    filterOrders: function(filter, btn) {
      this.state.ordersFilter = filter;
      var grp = document.getElementById('tk-orders-filters');
      Array.prototype.forEach.call(grp.children, function(b) {
        b.className = b === btn ? 'ecc-btn ecc-btn-primary' : 'ecc-btn ecc-btn-secondary';
        b.style.padding = '0.35rem 0.75rem'; b.style.fontSize = '0.75rem';
      });
      this.loadOrders();
    },
    searchOrders: function(ev) {
      this.state.ordersSearch = ev.target.value.trim();
      var self = this;
      clearTimeout(this._ordersTimer);
      this._ordersTimer = setTimeout(function() { self.loadOrders(); }, 260);
    },

    loadIssued: function() {
      var self = this;
      this.get('issued', { status: this.state.issuedFilter, q: this.state.issuedSearch }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        var tickets = (body.result && body.result.tickets) || [];
        var tbody = document.getElementById('tk-issued-body');
        if (!tbody) return;
        if (!tickets.length) {
          tbody.innerHTML = '<tr><td colspan="7"><div class="ecc-tk-empty">No issued tickets yet.</div></td></tr>';
          return;
        }
        tbody.innerHTML = tickets.map(function(t) {
          var checkin = t.checked_in_at ? '<span class="ecc-pill green">CHECKED IN (' + tkDateTime(t.checked_in_at) + ')</span>' : '<span class="ecc-pill amber">NOT CHECKED IN</span>';
          return '<tr>' +
            '<td><strong style="font-family:\'JetBrains Mono\',monospace;font-size:0.72rem;">' + tkEsc(t.id) + '</strong></td>' +
            '<td>' + tkEsc(t.ticket_type_name || 'Ticket') + '</td>' +
            '<td>' + tkEsc(t.holder_name) + (t.holder_phone ? '<div style="font-size:0.68rem;color:var(--ecc-text-dim);">' + tkEsc(t.holder_phone) + '</div>' : '') + '</td>' +
            '<td style="font-size:0.72rem;">' + tkEsc(t.booking_id || '—') + '</td>' +
            '<td>' + ((t.status === 'ISSUED' || t.status === 'CHECKED_IN') ? '<span class="ecc-pill green">VERIFIED</span>' : tkStatusPill(t.status)) + '</td>' +
            '<td>' + checkin + '</td>' +
            '<td><button class="ecc-btn ecc-btn-secondary" style="padding:0.25rem 0.65rem;font-size:0.72rem;" onclick="TicketsControlCenter.openDrawer(\'' + tkEsc(t.id) + '\')">Drawer</button></td>' +
            '</tr>';
        }).join('');
      });
    },
    filterIssued: function(status, btn) {
      this.state.issuedFilter = status;
      var grp = document.getElementById('tk-issued-filters');
      Array.prototype.forEach.call(grp.children, function(b) {
        b.className = b === btn ? 'ecc-btn ecc-btn-primary' : 'ecc-btn ecc-btn-secondary';
        b.style.padding = '0.35rem 0.75rem'; b.style.fontSize = '0.75rem';
      });
      this.loadIssued();
    },
    searchIssued: function(ev) {
      this.state.issuedSearch = ev.target.value.trim();
      var self = this;
      clearTimeout(this._issuedTimer);
      this._issuedTimer = setTimeout(function() { self.loadIssued(); }, 260);
    },

    loadTransfers: function() {
      var self = this;
      this.get('transfers').then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        var rows = (body.result && body.result.transfers) || [];
        var tbody = document.getElementById('tk-transfers-body');
        if (!tbody) return;
        if (!rows.length) {
          tbody.innerHTML = '<tr><td colspan="7"><div class="ecc-tk-empty">No transfers yet — use the ticket drawer to transfer a ticket.</div></td></tr>';
          return;
        }
        tbody.innerHTML = rows.map(function(t) {
          return '<tr>' +
            '<td style="font-size:0.72rem;">' + tkEsc(t.id) + '</td>' +
            '<td><strong style="font-family:\'JetBrains Mono\',monospace;font-size:0.72rem;">' + tkEsc(t.ticket_id) + '</strong><div style="font-size:0.68rem;color:var(--ecc-text-dim);">' + tkEsc(t.ticket_type_name || '') + '</div></td>' +
            '<td>' + tkEsc(t.from_holder_name) + '</td>' +
            '<td>' + tkEsc(t.to_holder_name) + (t.to_phone ? '<div style="font-size:0.68rem;color:var(--ecc-text-dim);">' + tkEsc(t.to_phone) + '</div>' : '') + '</td>' +
            '<td style="font-size:0.72rem;color:var(--ecc-text-dim);">' + tkDateTime(t.created_at) + '</td>' +
            '<td style="font-size:0.72rem;">' + tkEsc(t.initiated_by_type || '') + (t.reason ? '<div style="font-size:0.66rem;color:var(--ecc-text-dim);">' + tkEsc(t.reason) + '</div>' : '') + '</td>' +
            '<td>' + tkStatusPill(t.status) + '</td>' +
            '</tr>';
        }).join('');
      });
    },

    loadRefunds: function() {
      var self = this;
      this.get('refunds').then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        var rows = (body.result && body.result.refunds) || [];
        var tbody = document.getElementById('tk-refunds-body');
        if (!tbody) return;
        if (!rows.length) {
          tbody.innerHTML = '<tr><td colspan="7"><div class="ecc-tk-empty">No refund requests yet.</div></td></tr>';
          return;
        }
        tbody.innerHTML = rows.map(function(r) {
          var actions = r.status === 'PENDING'
            ? '<button class="ecc-btn ecc-btn-primary" style="padding:0.25rem 0.6rem;font-size:0.72rem;" onclick="TicketsControlCenter.decideRefund(\'' + tkEsc(r.id) + '\',true)">Approve</button> <button class="ecc-btn ecc-btn-secondary" style="padding:0.25rem 0.6rem;font-size:0.72rem;" onclick="TicketsControlCenter.decideRefund(\'' + tkEsc(r.id) + '\',false)">Reject</button>'
            : '<button class="ecc-btn ecc-btn-secondary" disabled style="font-size:0.72rem;">Settled</button>';
          return '<tr>' +
            '<td><strong>' + tkEsc(r.id) + '</strong></td>' +
            '<td>' + tkEsc(r.booking_id || '—') + (r.ticket_id ? '<div style="font-size:0.66rem;font-family:\'JetBrains Mono\',monospace;">' + tkEsc(r.ticket_id) + '</div>' : '') + '</td>' +
            '<td>' + tkEsc(r.customer_name || '—') + '</td>' +
            '<td><strong>' + tkMoney(r.amount) + '</strong></td>' +
            '<td style="font-size:0.72rem;">' + tkEsc(r.reason || '—') + '</td>' +
            '<td>' + tkStatusPill(r.status) + '</td>' +
            '<td>' + actions + '</td>' +
            '</tr>';
        }).join('');
      });
    },

    decideRefund: function(refundId, approve) {
      var self = this;
      var verb = approve ? 'approve' : 'reject';
      if (!window.confirm('Please confirm you want to ' + verb + ' refund ' + refundId + '?')) return;
      this.post({ action: 'decide_refund', refund_id: refundId, approve: approve ? 1 : 0 }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Refund ' + verb + 'd: ' + refundId);
        self.loadRefunds();
        self.loadWorkspace();
      });
    },

    /* ── Ticket types management ───────────────────────────── */
    renderTypes: function() {
      var grid = document.getElementById('tk-types-grid');
      if (grid) {
        var q = (document.getElementById('tk-types-filter') || {}).value || '';
        var list = this.state.types.filter(function(t) { return !q || t.name.toLowerCase().indexOf(q.toLowerCase()) !== -1; });
        if (!list.length) {
          grid.innerHTML = '<div class="ecc-tk-empty" style="grid-column:1/-1;">No ticket types found.</div>';
        } else {
          grid.innerHTML = list.map(function(t, i) {
            var sold = Number(t.sold) || 0, cap = Number(t.total_quantity) || 0;
            var pct = cap > 0 ? Math.round(100 * sold / cap) : 0;
            return '<div class="ecc-card">' +
              '<div style="display:flex;justify-content:space-between;align-items:flex-start;">' +
              '<div><span class="ecc-pill purple">' + tkEsc(t.category) + '</span><h3 style="margin:0.4rem 0 0.2rem 0;font-size:1.15rem;">' + tkEsc(t.name) + '</h3></div>' +
              tkStatusPill(t.ticket_status) +
              '</div>' +
              '<p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0.4rem 0;">' + tkEsc(t.description || '') + '</p>' +
              '<h2 style="font-size:1.3rem;margin:0.5rem 0;color:var(--ecc-primary);">' + tkMoney(t.price) + '</h2>' +
              '<div class="ecc-progress-bar-wrap" style="height:6px;margin:0.5rem 0;"><div class="ecc-progress-fill" style="width:' + pct + '%;background:' + TK_COLORS[i % TK_COLORS.length] + ';"></div></div>' +
              '<div style="display:flex;justify-content:space-between;font-size:0.74rem;margin-bottom:0.8rem;"><span>' + sold.toLocaleString() + ' Sold</span><span>' + (Number(t.available) || 0).toLocaleString() + ' Available</span></div>' +
              '<div style="display:flex;gap:0.4rem;">' +
              '<button class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.72rem;" onclick="TicketsControlCenter.manageTicket(' + t.id + ')">Edit</button>' +
              '<button class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.72rem;" onclick="TicketsControlCenter.duplicateType(' + t.id + ')">Duplicate</button>' +
              '</div>' +
              '<div style="display:flex;gap:0.4rem;margin-top:0.4rem;">' +
              (t.ticket_status === 'ACTIVE' || t.ticket_status === 'SCHEDULED' ? '<button class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.72rem;color:#f59e0b;" onclick="TicketsControlCenter.setStatus(' + t.id + ',\'pause\')">Pause</button>' : '') +
              (t.ticket_status === 'PAUSED' ? '<button class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.72rem;color:#10b981;" onclick="TicketsControlCenter.setStatus(' + t.id + ',\'resume\')">Resume</button>' : '') +
              (t.ticket_status === 'DRAFT' ? '<button class="ecc-btn ecc-btn-primary" style="flex:1;font-size:0.72rem;" onclick="TicketsControlCenter.setStatus(' + t.id + ',\'activate\')">Activate</button>' : '') +
              (t.ticket_status !== 'CLOSED' && t.ticket_status !== 'ARCHIVED' && t.ticket_status !== 'SOLD_OUT' ? '<button class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.72rem;color:#ef4444;" onclick="TicketsControlCenter.setStatus(' + t.id + ',\'close\')">Close</button>' : '') +
              '</div></div>';
          }).join('');
        }
      }
    },

    newTicketType: function() {
      TicketsWiz.reset();
      document.getElementById('tw-publish-btn').textContent = '✓ Publish Ticket Type';
      openEccModal('modal-add-ticket');
    },

    manageTicket: function(typeId) {
      var t = this.state.types.filter(function(x) { return x.id === typeId; })[0];
      if (!t) { window.eccNotify('Ticket type not found in workspace.'); return; }
      TicketsWiz.loadType(t);
      document.getElementById('tw-publish-btn').textContent = '✓ Save Changes';
      openEccModal('modal-add-ticket');
    },

    setStatus: function(typeId, action) {
      var self = this;
      this.post({ action: 'set_status', ticket_type_id: typeId, status_action: action }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Ticket type ' + action + 'd');
        self.state.types = (body.result && body.result.types) || self.state.types;
        self.renderTypes();
        self.renderTypesTable(self.state.types);
        self.loadWorkspace();
      });
    },
    duplicateType: function(typeId) {
      var self = this;
      this.post({ action: 'duplicate_type', ticket_type_id: typeId }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Ticket type duplicated as draft');
        self.state.types = (body.result && body.result.types) || self.state.types;
        self.renderTypes();
        self.renderTypesTable(self.state.types);
      });
    },

    /* ── Drawer ─────────────────────────────────────────────── */
    openDrawer: function(ref) {
      var self = this;
      var drawer = document.getElementById('ecc-ticket-drawer');
      if (!drawer) return;
      var title = document.getElementById('drawer-title');
      if (title) title.textContent = String(ref).indexOf('UTH-') === 0 ? 'Ticket Details' : 'Order Details';
      var body = document.getElementById('drawer-body');
      if (body) body.innerHTML = '<div class="ecc-tk-empty">Loading…</div>';
      drawer.classList.add('open');

      var action = String(ref).indexOf('UTH-') === 0 ? 'ticket_detail' : 'order_detail';
      var param = action === 'ticket_detail' ? { ticket_id: ref } : { booking_id: ref };
      this.get(action, param).then(function(res) {
        if (!res.success) {
          if (body) body.innerHTML = '<div class="ecc-tk-empty">' + tkEsc(self.errMsg(res)) + '</div>';
          window.eccNotify(self.errMsg(res));
          return;
        }
        if (action === 'ticket_detail') self.renderTicketDrawer(res.result);
        else self.renderOrderDrawer(res.result);
      });
    },

    renderTicketDrawer: function(r) {
      var t = (r && r.ticket) || {};
      var body = document.getElementById('drawer-body');
      if (!body) return;
      var transfers = (r && r.transfers) || [];
      var activity = (r && r.activity) || [];
      var h = '';
      h += '<div style="background:var(--ecc-surface-2);border-radius:12px;padding:1rem;margin-bottom:1rem;">';
      h += '<span class="ecc-pill purple">' + tkEsc(t.ticket_type_name || 'TICKET') + '</span>';
      h += '<h2 style="font-size:1.3rem;margin:0.4rem 0 0.2rem 0;color:var(--ecc-primary);">' + tkEsc(t.id) + '</h2>';
      h += '<div style="font-size:0.72rem;color:var(--ecc-text-dim);">' + tkStatusPill(t.status) + '</div></div>';
      h += '<div style="display:flex;flex-direction:column;gap:0.6rem;font-size:0.8rem;margin-bottom:1rem;">';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Holder:</span><strong>' + tkEsc(t.holder_name) + '</strong></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Email:</span><span>' + tkEsc(t.holder_email || '—') + '</span></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Phone:</span><span>' + tkEsc(t.holder_phone || '—') + '</span></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Order Ref:</span><strong>' + tkEsc(t.booking_id || '—') + '</strong></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Issued:</span><span>' + tkDateTime(t.created_at) + '</span></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Check-in:</span><span>' + (t.checked_in_at ? tkDateTime(t.checked_in_at) : 'Not checked in') + '</span></div>';
      h += '</div>';
      h += '<div style="background:#000;color:#fff;border-radius:12px;padding:1rem;text-align:center;margin-bottom:1rem;">';
      h += '<div style="font-size:0.7rem;color:#94a3b8;margin-bottom:0.5rem;text-transform:uppercase;font-weight:700;">Check-In QR Token</div>';
      h += '<div style="font-family:\'JetBrains Mono\',monospace;font-size:0.7rem;color:#00ff66;word-break:break-all;">' + tkEsc(t.qr_token || '') + '</div>';
      h += '</div>';
      if (transfers.length) {
        h += '<div style="font-size:0.75rem;font-weight:800;margin-bottom:0.4rem;">TRANSFER HISTORY</div>';
        transfers.forEach(function(tr) {
          h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.5rem 0.7rem;font-size:0.72rem;margin-bottom:0.4rem;display:flex;justify-content:space-between;"><span>' + tkEsc(tr.from_holder_name) + ' → <b>' + tkEsc(tr.to_holder_name) + '</b></span><span style="color:var(--ecc-text-dim);">' + tkDateTime(tr.completed_at || tr.created_at) + '</span></div>';
        });
      }
      if (activity.length) {
        h += '<div style="font-size:0.75rem;font-weight:800;margin-bottom:0.4rem;">ACTIVITY</div>';
        activity.slice(0, 6).forEach(function(a) {
          h += '<div style="display:flex;justify-content:space-between;font-size:0.7rem;color:var(--ecc-text-dim);padding:0.25rem 0;border-bottom:1px dashed var(--ecc-border);"><span>' + tkEsc(a.action_key || a.action) + '</span><span>' + tkDateTime(a.created_at) + '</span></div>';
        });
      }
      h += '<div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:1rem;">';
      if (t.status === 'ISSUED' || t.status === 'CHECKED_IN') {
        h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="width:100%;" onclick="TicketsControlCenter.resendTicket(\'' + tkEsc(t.id) + '\')">Resend Digital Ticket</button>';
        if (!t.checked_in_at) {
          h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="width:100%;" onclick="TicketsControlCenter.transferTicket(\'' + tkEsc(t.id) + '\')">Transfer Ticket</button>';
          h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="width:100%;color:#ef4444;" onclick="TicketsControlCenter.refundTicket(\'' + tkEsc(t.id) + '\')">Request Refund</button>';
          h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="width:100%;color:#ef4444;" onclick="TicketsControlCenter.cancelTicket(\'' + tkEsc(t.id) + '\')">Cancel Ticket</button>';
        }
      }
      h += '</div>';
      body.innerHTML = h;
    },

    renderOrderDrawer: function(r) {
      var o = (r && r.order) || {};
      var tickets = (r && r.tickets) || [];
      var body = document.getElementById('drawer-body');
      if (!body) return;
      var h = '';
      h += '<div style="background:var(--ecc-surface-2);border-radius:12px;padding:1rem;margin-bottom:1rem;">';
      h += '<div style="font-size:0.72rem;color:var(--ecc-text-dim);text-transform:uppercase;font-weight:700;">' + tkEsc(o.ticket_type_name || 'Order') + '</div>';
      h += '<h2 style="font-size:1.3rem;margin:0.4rem 0;color:var(--ecc-primary);">' + tkEsc(o.id) + '</h2>';
      h += tkStatusPill(o.status) + '</div>';
      h += '<div style="display:flex;flex-direction:column;gap:0.6rem;font-size:0.8rem;margin-bottom:1rem;">';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Customer:</span><strong>' + tkEsc(o.customer_name) + '</strong></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Email:</span><span>' + tkEsc(o.customer_email || '—') + '</span></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Quantity:</span><span>' + o.quantity + ' × ' + tkEsc(o.ticket_type_name || 'Ticket') + '</span></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Amount:</span><strong>' + tkMoney(o.amount) + '</strong></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Gateway:</span><span>' + tkEsc(o.gateway) + '</span></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Transaction:</span><span style="font-size:0.7rem;">' + tkEsc(o.transaction_id || '—') + '</span></div>';
      h += '<div style="display:flex;justify-content:space-between;"><span style="color:var(--ecc-text-dim);">Booked:</span><span>' + tkDateTime(o.booked_at) + '</span></div>';
      h += '</div>';
      if (tickets.length) {
        h += '<div style="font-size:0.75rem;font-weight:800;margin-bottom:0.4rem;">TICKETS (' + tickets.length + ')</div>';
        tickets.forEach(function(t) {
          h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.5rem 0.7rem;font-size:0.72rem;margin-bottom:0.4rem;display:flex;justify-content:space-between;align-items:center;"><span style="font-family:\'JetBrains Mono\',monospace;">' + tkEsc(t.id) + '</span>' + tkStatusPill(t.status) + '</div>';
        });
      }
      h += '<div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:1rem;">';
      if (o.status === 'Completed' && !o.issued_full) {
        h += '<button type="button" class="ecc-btn ecc-btn-primary" style="width:100%;" onclick="TicketsControlCenter.issueBooking(\'' + tkEsc(o.id) + '\')">Issue Missing Tickets (' + (o.quantity - (o.issued || 0)) + ')</button>';
      }
      if (o.status === 'Completed') {
        h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="width:100%;color:#ef4444;" onclick="TicketsControlCenter.refundOrder(\'' + tkEsc(o.id) + '\')">Create Refund Request</button>';
      }
      h += '</div>';
      body.innerHTML = h;
    },

    closeDrawer: function() {
      var drawer = document.getElementById('ecc-ticket-drawer');
      if (drawer) drawer.classList.remove('open');
    },

    issueBooking: function(bookingId) {
      var self = this;
      this.post({ action: 'issue_booking', booking_id: bookingId }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Issued ' + (body.result && body.result.result ? body.result.result.issued : '') + ' tickets');
        self.openDrawer(bookingId);
        self.loadOrders();
        self.loadWorkspace();
      });
    },
    resendTicket: function(ticketId) {
      var self = this;
      this.post({ action: 'resend_ticket', ticket_id: ticketId }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Ticket re-sent via Email & SMS');
        self.openDrawer(ticketId);
      });
    },
    cancelTicket: function(ticketId) {
      var self = this;
      var reason = window.prompt('Reason for cancelling this ticket?', 'Organizer cancellation');
      if (reason === null) return;
      this.post({ action: 'cancel_ticket', ticket_id: ticketId, reason: reason || null }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Ticket cancelled');
        self.openDrawer(ticketId);
        self.loadWorkspace();
      });
    },
    transferTicket: function(ticketId) {
      var self = this;
      var name = window.prompt('Full name of the new ticket holder:');
      if (!name) return;
      var phone = window.prompt('New holder phone (optional):', '') || '';
      var email = window.prompt('New holder email (optional):', '') || '';
      var reason = window.prompt('Reason (optional):', 'Gifted to a friend') || '';
      this.post({ action: 'create_transfer', ticket_id: ticketId, to_name: name, to_phone: phone || null, to_email: email || null, reason: reason || null }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Ticket transferred to ' + name);
        self.openDrawer(ticketId);
        self.loadWorkspace();
        if (self.state.transfersLoaded) self.loadTransfers();
      });
    },
    refundTicket: function(ticketId) {
      var self = this;
      var amount = window.prompt('Refund amount (MWK):');
      if (amount === null || !Number(amount)) return;
      var reason = window.prompt('Refund reason:');
      if (!reason) return;
      this.post({ action: 'create_refund', ticket_id: ticketId, amount: Number(amount), reason: reason }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Refund request submitted for approval');
        self.loadWorkspace();
        if (self.state.refundsLoaded) self.loadRefunds();
      });
    },
    refundOrder: function(bookingId) {
      var self = this;
      var amount = window.prompt('Refund amount (MWK):');
      if (amount === null || !Number(amount)) return;
      var reason = window.prompt('Refund reason:');
      if (!reason) return;
      this.post({ action: 'create_refund', booking_id: bookingId, amount: Number(amount), reason: reason }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Refund request submitted for approval');
        self.loadWorkspace();
        if (self.state.refundsLoaded) self.loadRefunds();
      });
    },

    /* ── Export ─────────────────────────────────────────────── */
    exportData: function(type) {
      var self = this;
      var pop = document.getElementById('ecc-export-dropdown');
      if (pop) pop.style.display = 'none';
      this.get('export', { type: type }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        var exp = body.result && body.result.export;
        if (!exp || !exp.rows || !exp.rows.length) {
          window.eccNotify('No rows to export for ' + type);
          return;
        }
        var keys = Object.keys(exp.rows[0]);
        var csv = keys.map(function(k) { return '"' + String(k).replace(/"/g, '""') + '"'; }).join(',') + '\n';
        exp.rows.forEach(function(row) {
          csv += keys.map(function(k) {
            var v = row[k];
            if (v && typeof v === 'object') v = JSON.stringify(v);
            return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
          }).join(',') + '\n';
        });
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = exp.filename + '-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        setTimeout(function() { URL.revokeObjectURL(a.href); a.remove(); }, 400);
        window.eccNotify('Exported ' + exp.rows.length + ' rows');
      });
    },

    importTickets: function() {
      window.eccNotify('Opening Ticket Inventory Import Wizard...');
    }
  };

  /* ── Attendees Intelligence Center Controller ─────────────── */
  var atApiBase = (tkDoc && tkDoc.dataset.baseUrl ? tkDoc.dataset.baseUrl : '') + 'api/tie/vendor/events/attendees.php';

  window.AttendeesControlCenter = {
    state: {
      events: [], listingId: '', listing: null,
      kpis: null, arrival: null, byType: [], byGate: [], insights: [], live: [],
      filters: { types: [], zones: [], orgs: [] },
      attendees: [], selection: {},
      q: '', typeId: 0, attendance: '', payment: '', since: '', zone: '', org: '',
      searchTimer: null, refreshTimer: null, drawerTicketId: ''
    },

    get: function(action, params) {
      var qs = '?listing_id=' + encodeURIComponent(this.state.listingId) + '&action=' + encodeURIComponent(action);
      Object.keys(params || {}).forEach(function(k) { qs += '&' + k + '=' + encodeURIComponent(params[k]); });
      return fetch(atApiBase + qs, { credentials: 'same-origin' }).then(function(r) { return r.json().catch(function() { return {}; }); });
    },
    post: function(data) {
      var payload = Object.assign({ listing_id: this.state.listingId }, data);
      var csrf = tkDoc && tkDoc.dataset ? tkDoc.dataset.csrf : '';
      return fetch(atApiBase, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(payload)
      }).then(function(r) { return r.json().catch(function() { return {}; }); });
    },
    errMsg: function(body) {
      if (body && body.error && body.error.message) return body.error.message;
      return 'The request could not be completed.';
    },

    /* ── Boot & event context ─────────────────────────────── */
    init: function() {
      var self = this;
      fetch(tkApiBase + '?action=events', { credentials: 'same-origin' })
        .then(function(r) { return r.json().catch(function() { return {}; }); })
        .then(function(body) {
          if (!body.success) {
            window.eccNotify('Could not load event portfolio: ' + self.errMsg(body));
            return;
          }
          self.state.events = (body.result && body.result.events) || [];
          var sel = document.getElementById('at-event-select');
          if (!sel) return;
          sel.innerHTML = '';
          var pub = self.state.events.filter(function(e) { return e.status === 'published' || e.listing_active; });
          var list = (pub.length ? pub : self.state.events);
          list.forEach(function(e) {
            var opt = document.createElement('option');
            opt.value = e.listing_id || e.id;
            opt.textContent = e.title + (e.status ? ' (' + e.status + ')' : '');
            sel.appendChild(opt);
          });
          if (list.length === 0) {
            sel.innerHTML = '<option value="">No events available</option>';
            var body = document.getElementById('at-table-body');
            if (body) body.innerHTML = '<tr><td colspan="8"><div class="ecc-tk-empty">Create and publish an event first, then manage its attendees here.</div></td></tr>';
            return;
          }
          var pre = (localStorage.getItem('ecc-at-event') || '').trim();
          var preferred = self.state.events.some(function(e) { return (e.listing_id || e.id) === pre; }) ? pre : (list[0].listing_id || list[0].id);
          sel.value = preferred;
          self.switchEvent(preferred);
        });

      this.state.refreshTimer = setInterval(function() {
        var mod = document.getElementById('mod-attendees');
        if (!mod || mod.offsetParent === null || !self.state.listingId) return;
        self.loadWorkspace();
      }, 30000);
    },

    switchEvent: function(id) {
      this.state.listingId = id || '';
      this.state.selection = {};
      this.clearSelection();
      this.state.q = ''; this.state.typeId = 0; this.state.attendance = ''; this.state.payment = '';
      this.state.since = ''; this.state.zone = ''; this.state.org = '';
      var s = document.getElementById('at-search'); if (s) s.value = '';
      var meta = document.getElementById('at-event-meta');
      if (meta) meta.textContent = 'Loading…';
      if (!this.state.listingId) return;
      try { localStorage.setItem('ecc-at-event', this.state.listingId); } catch (e) {}
      this.loadWorkspace();
      this.loadAttendees();
    },

    /* ── Workspace snapshot ───────────────────────────────── */
    loadWorkspace: function() {
      var self = this;
      this.get('workspace').then(function(body) {
        if (!body.success) {
          window.eccNotify('Workspace load failed: ' + self.errMsg(body));
          return;
        }
        var w = body.result || {};
        self.state.listing = w.listing || self.state.listing;
        self.state.kpis = w.kpis || self.state.kpis;
        self.state.arrival = w.arrival || { last_15: 0, last_60: 0, peak_rate_per_min: 0, curve: [] };
        self.state.byType = w.by_type || [];
        self.state.byGate = w.by_gate || [];
        self.state.insights = w.insights || [];
        self.state.live = w.live || [];
        if (w.filters) {
          self.state.filters.types = w.filters.types || [];
          self.state.filters.zones = w.filters.access_zones || [];
          self.state.filters.orgs = w.filters.organizations || [];
          self.populateFilterSelects();
        }
        var meta = document.getElementById('at-event-meta');
        if (meta && self.state.listing) {
          meta.textContent = self.state.listing.venue_name
            ? (self.state.listing.venue_name + (self.state.listing.venue_city ? ', ' + self.state.listing.venue_city : ''))
            : self.state.listing.event_status || '';
        }
        self.renderKpis();
        self.renderArrival();
        self.renderByType();
        self.renderByGate();
        self.renderInsights();
        self.renderLive();
      });
    },

    populateFilterSelects: function() {
      var self = this;
      var sel = document.getElementById('at-filter-type');
      if (sel) {
        var cur = sel.value;
        sel.innerHTML = '<option value="0">All Ticket Types</option>';
        self.state.filters.types.forEach(function(t) {
          var opt = document.createElement('option');
          opt.value = String(t.id);
          opt.textContent = t.name + (t.category ? ' (' + t.category + ')' : '');
          sel.appendChild(opt);
        });
        if (cur && sel.querySelector('option[value="' + cur + '"]')) sel.value = cur;
      }
      var zone = document.getElementById('at-filter-zone');
      if (zone) {
        var curz = zone.value;
        zone.innerHTML = '<option value="">All Access Zones</option>';
        self.state.filters.zones.forEach(function(z) {
          var opt = document.createElement('option');
          opt.value = z;
          opt.textContent = z;
          zone.appendChild(opt);
        });
        if (curz && zone.querySelector('option[value="' + curz + '"]')) zone.value = curz;
      }
      var org = document.getElementById('at-filter-org');
      if (org) {
        var curo = org.value;
        org.innerHTML = '<option value="">All Organizations</option>';
        self.state.filters.orgs.forEach(function(o) {
          var opt = document.createElement('option');
          opt.value = o;
          opt.textContent = o;
          org.appendChild(opt);
        });
        if (curo && org.querySelector('option[value="' + curo + '"]')) org.value = curo;
      }
    },

    renderKpis: function() {
      var k = this.state.kpis || {};
      var set = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = (val === null || val === undefined) ? '—' : String(val); };
      set('at-kpi-total', k.total);
      set('at-kpi-checked', k.checked_in);
      set('at-kpi-expected', k.expected);
      set('at-kpi-notarrived', k.not_arrived);
      set('at-kpi-cancelled', k.cancelled_refunded);
      set('at-kpi-vip', k.vip);
      var rate = (k.checkin_rate === null || k.checkin_rate === undefined) ? 0 : Number(k.checkin_rate);
      var total = Number(k.total || 0);
      var checked = Number(k.checked_in || 0);
      var fill = document.getElementById('at-progress-fill');
      if (fill) fill.style.width = Math.min(rate, 100) + '%';
      var label = document.getElementById('at-progress-label');
      if (label) label.textContent = checked + ' of ' + total + ' expected attendees have arrived';
      var rl = document.getElementById('at-progress-rate');
      if (rl) rl.textContent = rate + '% check-in rate';
      var sub = document.getElementById('at-kpi-checked-sub');
      if (sub) sub.textContent = (k.last_15 && k.last_15 > 0 ? k.last_15 + ' in last 15 min' : 'have arrived');
    },

    renderArrival: function() {
      var a = this.state.arrival || {};
      var set = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = (val === null || val === undefined) ? '—' : String(val); };
      set('at-arr-15', a.last_15 || 0);
      set('at-arr-60', a.last_60 || 0);
      set('at-arr-peak', (a.peak_rate_per_min || 0) + '/m');
      var curve = a.curve || [];
      var el = document.getElementById('at-arr-curve');
      if (!el) return;
      if (curve.length === 0) {
        el.innerHTML = '<div class="ecc-tk-empty" style="height:70px;">No arrivals in the last 12 hours yet.</div>';
        return;
      }
      var max = Math.max.apply(null, curve.map(function(c) { return c.count; })) || 1;
      var h = '';
      curve.forEach(function(c) {
        var pct = Math.round(100 * c.count / max);
        var isPeak = c.count === max;
        h += '<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:0.2rem;min-width:34px;" title="' + tkEsc(c.hour) + ' — ' + c.count + ' arrivals">';
        h += '<span style="font-size:0.6rem;color:var(--ecc-text-dim);font-weight:700;">' + c.count + '</span>';
        h += '<div style="width:100%;height:' + pct + '%;min-height:8px;border-radius:4px 4px 0 0;background:' + (isPeak ? 'var(--ecc-primary)' : 'var(--ecc-surface-2)') + ';transition:height .4s ease;"></div>';
        h += '</div>';
      });
      el.innerHTML = h;
    },

    renderByType: function() {
      var el = document.getElementById('at-bytype-chart');
      if (!el) return;
      var list = this.state.byType;
      if (list.length === 0) { el.innerHTML = '<div class="ecc-tk-empty">No ticket types with attendees.</div>'; return; }
      var h = '';
      list.forEach(function(t) {
        var pct = t.rate || 0;
        var checked = t.checked_in || 0;
        var total = t.total || 0;
        var cat = String(t.category || '').toUpperCase();
        var color = (cat === 'VIP' || cat === 'VVIP') ? '#8b5cf6' : (cat === 'STUDENT' ? '#f59e0b' : '#3b82f6');
        h += '<div>';
        h += '<div style="display:flex;justify-content:space-between;font-size:0.74rem;margin-bottom:0.25rem;">';
        h += '<strong>' + tkEsc(t.name || 'Ticket') + (cat ? ' <span style="color:var(--ecc-text-dim);font-weight:600;">(' + tkEsc(cat) + ')</span>' : '') + '</strong>';
        h += '<span style="color:var(--ecc-text-dim);font-weight:600;">' + checked + '/' + total + ' · ' + pct + '%</span>';
        h += '</div>';
        h += '<div style="height:8px;background:var(--ecc-surface-2);border-radius:999px;overflow:hidden;"><div style="width:' + Math.min(pct, 100) + '%;height:100%;background:' + color + ';border-radius:999px;"></div></div>';
        h += '</div>';
      });
      el.innerHTML = h;
    },

    renderByGate: function() {
      var el = document.getElementById('at-bygate-chart');
      if (!el) return;
      var list = this.state.byGate;
      var checked = (this.state.kpis && this.state.kpis.checked_in) || 0;
      if (list.length === 0) { el.innerHTML = '<div class="ecc-tk-empty">No gate data yet.</div>'; return; }
      var max = Math.max.apply(null, list.map(function(g) { return g.count; })) || 1;
      var h = '';
      list.forEach(function(g) {
        var share = checked > 0 ? Math.round(100 * g.count / checked) : 0;
        var pct = Math.round(100 * g.count / max);
        h += '<div>';
        h += '<div style="display:flex;justify-content:space-between;font-size:0.74rem;margin-bottom:0.25rem;">';
        h += '<strong>' + tkEsc(g.gate || 'Gate') + '</strong>';
        h += '<span style="color:var(--ecc-text-dim);font-weight:600;">' + g.count + ' · ' + share + '%</span>';
        h += '</div>';
        h += '<div style="height:8px;background:var(--ecc-surface-2);border-radius:999px;overflow:hidden;"><div style="width:' + pct + '%;height:100%;background:#10b981;border-radius:999px;"></div></div>';
        h += '</div>';
      });
      el.innerHTML = h;
    },

    renderInsights: function() {
      var el = document.getElementById('at-insights');
      if (!el) return;
      var list = this.state.insights;
      if (list.length === 0) { el.innerHTML = '<div class="ecc-tk-empty">No insights yet — check in attendees to see patterns.</div>'; return; }
      var h = '';
      list.forEach(function(i) {
        var cls = i.level === 'warn' ? 'ecc-tk-insight warn' : 'ecc-tk-insight info';
        h += '<div class="' + cls + '"><span>' + (i.level === 'warn' ? '⚠' : '💡') + '</span>' + tkEsc(i.message) + '</div>';
      });
      el.innerHTML = h;
    },

    renderLive: function() {
      var el = document.getElementById('at-live-feed');
      if (!el) return;
      var list = this.state.live;
      if (list.length === 0) { el.innerHTML = '<div class="ecc-tk-empty">No check-ins yet — arrivals will appear here live.</div>'; return; }
      var h = '';
      list.forEach(function(x) {
        h += '<div style="display:flex;align-items:center;gap:0.6rem;padding:0.4rem 0.5rem;border-radius:8px;background:var(--ecc-surface-2);">';
        h += '<span style="width:8px;height:8px;border-radius:50%;background:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,0.2);flex-shrink:0;"></span>';
        h += '<div style="flex:1;min-width:0;"><div style="font-size:0.78rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + tkEsc(x.holder_name || 'Attendee') + '</div>';
        h += '<div style="font-size:0.65rem;color:var(--ecc-text-dim);">' + tkEsc(x.ticket_type_name || '') + (x.gate ? ' · ' + tkEsc(x.gate) : '') + '</div></div>';
        h += '<div style="font-size:0.65rem;color:var(--ecc-text-dim);white-space:nowrap;">' + tkDateTime(x.checked_in_at) + '</div>';
        h += '</div>';
      });
      el.innerHTML = h;
    },

    /* ── Attendee directory ───────────────────────────────── */
    loadAttendees: function() {
      var self = this;
      var params = {
        q: this.state.q,
        type_id: this.state.typeId,
        attendance: this.state.attendance,
        payment: this.state.payment,
        since: this.state.since,
        zone: this.state.zone,
        organization: this.state.org,
        limit: 1000
      };
      this.get('list', params).then(function(body) {
        if (!body.success) {
          var tbody = document.getElementById('at-table-body');
          if (tbody) tbody.innerHTML = '<tr><td colspan="8"><div class="ecc-tk-empty">' + tkEsc(self.errMsg(body)) + '</div></td></tr>';
          window.eccNotify('Attendee list failed: ' + self.errMsg(body));
          return;
        }
        self.state.attendees = (body.result && body.result.attendees) || [];
        self.state.selection = {};
        var ca = document.getElementById('at-checkall');
        if (ca) ca.checked = false;
        self.renderTable();
        self.renderBulkBar();
      });
    },

    renderTable: function() {
      var tbody = document.getElementById('at-table-body');
      if (!tbody) return;
      var rows = this.state.attendees;
      var count = document.getElementById('at-count-label');
      if (count) count.textContent = 'Showing ' + rows.length + ' attendee' + (rows.length === 1 ? '' : 's') + (rows.length >= 1000 ? ' (latest 1000)' : '');
      if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="ecc-tk-empty">No attendees match your search. Try clearing filters, or add attendees manually.</div></td></tr>';
        return;
      }
      var h = '';
      rows.forEach(function(a) {
        var sel = !!this.state.selection[a.ticket_id];
        var attCls = 'amber'; var attTxt = 'EXPECTED';
        if (a.attendance_status === 'CHECKED_IN') { attCls = 'blue'; attTxt = 'CHECKED IN'; }
        else if (a.attendance_status === 'EXITED') { attCls = 'purple'; attTxt = 'EXITED'; }
        else if (a.attendance_status === 'CANCELLED') { attCls = 'rose'; attTxt = 'CANCELLED'; }
        else if (a.attendance_status === 'REFUNDED') { attCls = 'rose'; attTxt = 'REFUNDED'; }
        var payCls = 'green';
        if (a.payment_status === 'Pending') payCls = 'amber';
        else if (a.payment_status === 'Failed' || a.payment_status === 'Refunded') payCls = 'rose';
        else if (a.payment_gateway === 'Complimentary') payCls = 'purple';
        var initials = String(a.name || '?').split(/\s+/).map(function(w) { return w.charAt(0); }).join('').slice(0, 2).toUpperCase();
        h += '<tr' + (sel ? ' style="background:rgba(59,130,246,0.06);"' : '') + '>';
        h += '<td><input type="checkbox" ' + (sel ? 'checked' : '') + ' onchange="AttendeesControlCenter.toggleSelect(\'' + tkEsc(a.ticket_id) + '\',this.checked)"></td>';
        h += '<td><div style="display:flex;align-items:center;gap:0.6rem;"><span style="width:32px;height:32px;border-radius:50%;background:var(--ecc-surface-2);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:900;color:var(--ecc-primary);flex-shrink:0;">' + tkEsc(initials) + '</span>';
        h += '<div><div style="font-weight:700;font-size:0.82rem;">' + tkEsc(a.name) + '</div>';
        h += (a.organization ? '<div style="font-size:0.68rem;color:var(--ecc-text-dim);">' + tkEsc(a.organization) + '</div>' : '') + '</div></div></td>';
        h += '<td><div style="font-size:0.74rem;">' + tkEsc(a.email || '—') + '</div><div style="font-size:0.68rem;color:var(--ecc-text-dim);">' + tkEsc(a.phone || '—') + '</div></td>';
        h += '<td><div style="font-size:0.78rem;font-weight:700;">' + tkEsc(a.ticket_type_name) + '</div><div style="font-size:0.66rem;color:var(--ecc-text-dim);">' + tkEsc(a.ticket_id) + '</div></td>';
        h += '<td>' + tkStatusPill(a.payment_status === 'Complimentary' ? 'Complimentary' : a.payment_status) + '</td>';
        h += '<td><div>' + '<span class="ecc-pill ' + attCls + '">' + attTxt + '</span>' + '</div>';
        if (a.checked_in_at) h += '<div style="font-size:0.66rem;color:var(--ecc-text-dim);margin-top:0.2rem;">' + tkDateTime(a.checked_in_at) + (a.checked_in_gate ? ' · ' + tkEsc(a.checked_in_gate) : '') + '</div>';
        h += '</td>';
        h += '<td style="font-size:0.72rem;color:var(--ecc-text-dim);">' + tkDate(a.registered_at) + '</td>';
        h += '<td style="text-align:right;white-space:nowrap;">';
        if (a.attendance_status === 'EXPECTED') {
          h += '<button class="ecc-btn ecc-btn-primary" style="padding:0.25rem 0.6rem;font-size:0.72rem;margin-right:0.3rem;" onclick="AttendeesControlCenter.checkIn(\'' + tkEsc(a.ticket_id) + '\')">Check In</button>';
        }
        h += '<button class="ecc-btn ecc-btn-secondary" style="padding:0.25rem 0.6rem;font-size:0.72rem;" onclick="AttendeesControlCenter.openDrawer(\'' + tkEsc(a.ticket_id) + '\')">View</button>';
        h += '</td></tr>';
      }, this);
      tbody.innerHTML = h;
    },

    /* ── Search & filters ─────────────────────────────────── */
    searchInput: function(ev) {
      var self = this;
      clearTimeout(this.state.searchTimer);
      this.state.searchTimer = setTimeout(function() {
        self.state.q = (ev.target.value || '').trim();
        self.loadAttendees();
      }, 350);
    },

    applyFilter: function(key, value) {
      this.state[key] = value === '' ? '' : (key === 'typeId' ? Number(value) : String(value));
      this.loadAttendees();
    },

    resetFilters: function() {
      this.state.q = ''; this.state.typeId = 0; this.state.attendance = ''; this.state.payment = '';
      this.state.since = ''; this.state.zone = ''; this.state.org = '';
      var ids = ['at-search', 'at-filter-type', 'at-filter-attendance', 'at-filter-payment', 'at-filter-since', 'at-filter-zone', 'at-filter-org'];
      ids.forEach(function(id) { var el = document.getElementById(id); if (el) el.value = ''; });
      var t = document.getElementById('at-filter-type'); if (t) t.value = '0';
      this.loadAttendees();
    },

    /* ── Selection & bulk actions ─────────────────────────── */
    toggleSelect: function(ticketId, checked) {
      if (checked) this.state.selection[ticketId] = true;
      else delete this.state.selection[ticketId];
      var rows = this.state.attendees.filter(function(a) { return a.ticket_id === ticketId; });
      var ca = document.getElementById('at-checkall');
      if (ca) ca.checked = this.state.attendees.length > 0 && this.state.attendees.every(function(a) { return !!this.state.selection[a.ticket_id]; });
      this.renderBulkBar();
    },

    toggleSelectAll: function(checked) {
      var self = this;
      this.state.attendees.forEach(function(a) {
        if (checked) self.state.selection[a.ticket_id] = true;
        else delete self.state.selection[a.ticket_id];
      });
      this.renderTable();
      this.renderBulkBar();
    },

    clearSelection: function() {
      this.state.selection = {};
      var ca = document.getElementById('at-checkall');
      if (ca) ca.checked = false;
      this.renderBulkBar();
    },

    renderBulkBar: function() {
      var bar = document.getElementById('at-bulk-bar');
      var n = Object.keys(this.state.selection).length;
      if (!bar) return;
      if (n > 0) {
        bar.style.display = 'flex';
        var c = document.getElementById('at-bulk-count');
        if (c) c.textContent = n + ' selected';
      } else {
        bar.style.display = 'none';
      }
    },

    bulkCheckIn: function() {
      var self = this;
      var ids = Object.keys(this.state.selection);
      var expected = this.state.attendees.filter(function(a) {
        return ids.indexOf(a.ticket_id) !== -1 && a.attendance_status === 'EXPECTED';
      });
      if (expected.length === 0) { window.eccNotify('None of the selected attendees are awaiting check-in.'); return; }
      var done = 0; var failed = 0;
      expected.forEach(function(a) {
        self.post({ action: 'checkin', ticket_id: a.ticket_id }).then(function(body) {
          if (body.success) done++; else failed++;
          if (done + failed === expected.length) {
            window.eccNotify('Checked in ' + done + ' attendee' + (done === 1 ? '' : 's') + (failed ? ', ' + failed + ' failed' : ''));
            self.loadWorkspace();
            self.loadAttendees();
          }
        });
      });
    },

    checkIn: function(ticketId) {
      var self = this;
      this.post({ action: 'checkin', ticket_id: ticketId }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        window.eccNotify('Checked in ' + ((body.result && body.result.holder_name) || 'attendee') + ' (' + ((body.result && body.result.gate) || 'gate') + ')');
        self.loadWorkspace();
        self.loadAttendees();
        if (self.state.drawerTicketId === ticketId) self.openDrawer(ticketId);
      });
    },

    /* ── Attendee detail drawer ───────────────────────────── */
    openDrawer: function(ticketId) {
      var self = this;
      var drawer = document.getElementById('ecc-attendee-drawer');
      if (!drawer) return;
      this.state.drawerTicketId = ticketId;
      var body = document.getElementById('at-drawer-body');
      if (body) body.innerHTML = '<div class="ecc-tk-empty">Loading…</div>';
      drawer.classList.add('open');
      this.get('detail', { ticket_id: ticketId }).then(function(res) {
        if (!res.success) {
          if (body) body.innerHTML = '<div class="ecc-tk-empty">' + tkEsc(self.errMsg(res)) + '</div>';
          window.eccNotify(self.errMsg(res));
          return;
        }
        self.renderDrawer(res.result || {});
      });
    },

    closeDrawer: function() {
      var drawer = document.getElementById('ecc-attendee-drawer');
      if (drawer) drawer.classList.remove('open');
      this.state.drawerTicketId = '';
    },

    renderDrawer: function(d) {
      var a = (d.attendee) || {};
      var body = document.getElementById('at-drawer-body');
      if (!body) return;
      var timeline = d.timeline || [];
      var transfers = d.transfers || [];
      var refunds = d.refunds || [];
      var attCls = 'amber'; var attTxt = 'EXPECTED';
      if (a.attendance_status === 'CHECKED_IN') { attCls = 'blue'; attTxt = 'CHECKED IN'; }
      else if (a.attendance_status === 'EXITED') { attCls = 'purple'; attTxt = 'EXITED'; }
      else if (a.attendance_status === 'CANCELLED' || a.attendance_status === 'REFUNDED') { attCls = 'rose'; attTxt = a.attendance_status; }
      var payCls = 'green';
      if (a.payment_status === 'Pending') payCls = 'amber';
      else if (a.payment_status === 'Failed' || a.payment_status === 'Refunded') payCls = 'rose';
      else if (a.payment_gateway === 'Complimentary') payCls = 'purple';
      var h = '';

      h += '<div style="background:var(--ecc-surface-2);border-radius:12px;padding:1rem;margin-bottom:1rem;">';
      h += '<div style="display:flex;align-items:center;gap:0.8rem;margin-bottom:0.6rem;">';
      var initials = String(a.name || '?').split(/\s+/).map(function(w) { return w.charAt(0); }).join('').slice(0, 2).toUpperCase();
      h += '<span style="width:44px;height:44px;border-radius:50%;background:var(--ecc-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.9rem;font-weight:900;flex-shrink:0;">' + tkEsc(initials) + '</span>';
      h += '<div style="flex:1;"><h2 style="font-size:1.15rem;margin:0;">' + tkEsc(a.name || 'Attendee') + '</h2>';
      h += '<div style="font-size:0.72rem;color:var(--ecc-text-dim);">' + (a.organization ? tkEsc(a.organization) : 'Independent attendee') + '</div></div>';
      h += '<span class="ecc-pill ' + attCls + '">' + attTxt + '</span>';
      h += '</div>';
      h += '<div style="display:flex;gap:0.5rem;flex-wrap:wrap;">';
      if (a.attendance_status === 'EXPECTED') {
        h += '<button type="button" class="ecc-btn ecc-btn-primary" style="font-size:0.74rem;padding:0.3rem 0.7rem;" onclick="AttendeesControlCenter.checkIn(\'' + tkEsc(a.ticket_id) + '\')">✅ Check In Now</button>';
      }
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.74rem;padding:0.3rem 0.7rem;" onclick="AttendeesControlCenter.closeDrawer();TicketsControlCenter.openDrawer(\'' + tkEsc(a.ticket_id) + '\')">🎫 View Ticket</button>';
      if (a.booking_id) {
        h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.74rem;padding:0.3rem 0.7rem;" onclick="AttendeesControlCenter.closeDrawer();TicketsControlCenter.openDrawer(\'' + tkEsc(a.booking_id) + '\')">🧾 View Order</button>';
      }
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.74rem;padding:0.3rem 0.7rem;" onclick="AttendeesControlCenter.openMessageModal(\'' + tkEsc(a.ticket_id) + '\')">✉ Message</button>';
      h += '</div></div>';

      // ── Assigned ticket (scannable) ─────────────────────────────
      var qrSvg = '';
      if (typeof qrcode !== 'undefined' && a.qr_payload) {
        try {
          var qr = qrcode(0, 'M');
          qr.addData(String(a.qr_payload));
          qr.make();
          qrSvg = qr.createSvgTag(2, 0);
        } catch (e) {}
      }
      var ticketStatusCls = (a.attendance_status === 'EXPECTED') ? 'green' : ((a.attendance_status === 'CHECKED_IN') ? 'blue' : (attCls === 'rose' ? 'rose' : 'amber'));
      var ticketStatusTxt = (a.attendance_status === 'EXPECTED') ? 'READY' : ((a.attendance_status === 'CHECKED_IN') ? 'USED' : (a.attendance_status === 'EXITED' ? 'EXITED' : String(a.attendance_status || 'ISSUED')));
      h += '<div style="background:linear-gradient(135deg,var(--ecc-primary),#7c3aed);border-radius:12px;padding:1rem;color:#fff;display:flex;gap:1rem;align-items:center;margin-bottom:1rem;">';
      h += '<div style="width:76px;height:76px;background:#fff;border-radius:10px;padding:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;">';
      h += qrSvg ? qrSvg : '<div style="font-size:1.9rem;line-height:1;">🎟</div>';
      h += '</div>';
      h += '<div style="flex:1;min-width:0;">';
      h += '<div style="font-size:0.6rem;letter-spacing:0.1em;opacity:0.85;font-weight:800;">ASSIGNED TICKET · SCAN AT ENTRANCE</div>';
      h += '<div style="font-size:1.05rem;font-weight:900;margin:0.15rem 0 0.1rem;word-break:break-all;">' + tkEsc(a.ticket_id) + '</div>';
      h += '<div style="font-size:0.72rem;opacity:0.92;">' + tkEsc(a.ticket_type_name) + (a.category ? ' · ' + tkEsc(a.category) : '') + (a.ticket_description ? ' · ' + tkEsc(String(a.ticket_description).slice(0, 60)) + (String(a.ticket_description).length > 60 ? '…' : '') : '') + '</div>';
      h += '<div style="margin-top:0.4rem;"><span class="ecc-pill" style="background:rgba(255,255,255,0.18);color:#fff;border:none;font-size:0.6rem;">' + ticketStatusTxt + '</span>';
      h += '<span class="ecc-pill" style="background:rgba(255,255,255,0.18);color:#fff;border:none;font-size:0.6rem;margin-left:0.35rem;">' + tkMoney(a.price) + '</span></div>';
      h += '</div></div>';

      h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;font-size:0.78rem;margin-bottom:1rem;">';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">EMAIL</div>' + tkEsc(a.email || '—') + '</div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">PHONE</div>' + tkEsc(a.phone || '—') + '</div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">TICKET</div>' + tkEsc(a.ticket_id) + '</div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">TICKET TYPE</div>' + tkEsc(a.ticket_type_name) + (a.category ? ' (' + tkEsc(a.category) + ')' : '') + '</div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">PRICE</div>' + tkMoney(a.price) + '</div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">PAYMENT</div>' + tkStatusPill(a.payment_gateway === 'Complimentary' ? 'Complimentary' : a.payment_status) + '</div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">BOOKING</div>' + tkEsc(a.booking_id || '—') + '</div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">TRANSACTION</div>' + tkEsc(a.transaction_id || '—') + '</div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">REGISTERED</div>' + tkDateTime(a.registered_at) + '</div>';
      h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;grid-column:1 / -1;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">TICKET DESCRIPTION</div>' + tkEsc(a.ticket_description || '—') + '</div>';
      if (a.checked_in_at) {
        h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">CHECKED IN</div>' + tkDateTime(a.checked_in_at) + (a.checked_in_gate ? ' · ' + tkEsc(a.checked_in_gate) : '') + '</div>';
        h += '<div style="background:var(--ecc-surface-2);border-radius:8px;padding:0.55rem 0.7rem;"><div style="font-size:0.64rem;color:var(--ecc-text-dim);font-weight:700;">ACCESS ZONES</div>' + (a.access_zones && a.access_zones.length ? tkEsc(a.access_zones.join(', ')) : 'General admission') + '</div>';
      }
      h += '</div>';

      if (transfers.length) {
        h += '<h4 style="font-size:0.85rem;margin:0 0 0.4rem 0;">Transfers</h4>';
        transfers.forEach(function(t) {
          h += '<div style="font-size:0.74rem;background:var(--ecc-surface-2);border-radius:8px;padding:0.5rem 0.7rem;margin-bottom:0.4rem;">';
          h += '<strong>' + tkEsc(t.to_holder_name) + '</strong> · <span class="ecc-pill ' + (String(t.status || '').toLowerCase() === 'completed' ? 'green' : 'amber') + '" style="font-size:0.6rem;">' + tkEsc(String(t.status || '').toUpperCase()) + '</span>';
          h += '<div style="color:var(--ecc-text-dim);font-size:0.66rem;">' + tkDateTime(t.created_at) + (t.reason ? ' · ' + tkEsc(t.reason) : '') + '</div></div>';
        });
      }

      if (refunds.length) {
        h += '<h4 style="font-size:0.85rem;margin:0.8rem 0 0.4rem 0;">Refunds</h4>';
        refunds.forEach(function(r) {
          h += '<div style="font-size:0.74rem;background:var(--ecc-surface-2);border-radius:8px;padding:0.5rem 0.7rem;margin-bottom:0.4rem;">';
          h += '<strong>' + tkMoney(r.amount) + '</strong> · <span class="ecc-pill ' + (r.status === 'PROCESSED' ? 'green' : (r.status === 'REJECTED' ? 'rose' : 'amber')) + '" style="font-size:0.6rem;">' + tkEsc(r.status) + '</span>';
          h += '<div style="color:var(--ecc-text-dim);font-size:0.66rem;">' + tkDateTime(r.requested_at) + (r.reason ? ' · ' + tkEsc(r.reason) : '') + '</div></div>';
        });
      }

      h += '<h4 style="font-size:0.85rem;margin:0.8rem 0 0.4rem 0;">Activity Timeline</h4>';
      if (timeline.length === 0) {
        h += '<div class="ecc-tk-empty">No activity recorded yet.</div>';
      } else {
        timeline.forEach(function(t) {
          h += '<div style="display:flex;gap:0.6rem;padding:0.35rem 0;border-bottom:1px dashed var(--ecc-border);">';
          h += '<div style="width:9px;height:9px;border-radius:50%;background:var(--ecc-primary);margin-top:0.3rem;flex-shrink:0;"></div>';
          h += '<div style="flex:1;"><div style="font-size:0.76rem;font-weight:700;">' + tkEsc(t.event) + '</div>';
          h += (t.detail ? '<div style="font-size:0.68rem;color:var(--ecc-text-dim);">' + tkEsc(t.detail) + '</div>' : '');
          h += (t.actor ? '<div style="font-size:0.64rem;color:var(--ecc-text-dim);">by ' + tkEsc(t.actor) + '</div>' : '');
          h += '</div><div style="font-size:0.64rem;color:var(--ecc-text-dim);white-space:nowrap;">' + tkDateTime(t.at) + '</div></div>';
        });
      }

      h += '<div style="display:flex;gap:0.5rem;margin-top:1rem;">';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.74rem;" onclick="AttendeesControlCenter.openDrawer(\'' + tkEsc(a.ticket_id) + '\')">↻ Refresh</button>';
      h += '<button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.74rem;" onclick="AttendeesControlCenter.closeDrawer()">Close</button>';
      h += '</div>';
      body.innerHTML = h;
    },

    /* ── Manual add attendee ──────────────────────────────── */
    openAddModal: function() {
      var sel = document.getElementById('at-add-type');
      if (sel) {
        sel.innerHTML = '';
        if (this.state.filters.types.length === 0) {
          sel.innerHTML = '<option value="0">No ticket types available</option>';
        } else {
          this.state.filters.types.forEach(function(t) {
            var opt = document.createElement('option');
            opt.value = String(t.id);
            opt.textContent = t.name + (t.category ? ' (' + t.category + ')' : '');
            sel.appendChild(opt);
          });
        }
        var amt = document.getElementById('at-add-amount');
        if (amt) {
          var first = this.state.filters.types[0];
          if (first && first.price !== undefined) amt.placeholder = String(first.price);
        }
      }
      this.toggleAddPaymentFields();
      openEccModal('modal-add-attendee');
    },

    toggleAddPaymentFields: function() {
      var pay = document.getElementById('at-add-payment');
      var isComp = pay && pay.value === 'Complimentary';
      var amountWrap = document.getElementById('at-add-amount-wrap');
      var reasonWrap = document.getElementById('at-add-reason-wrap');
      if (amountWrap) amountWrap.style.display = isComp ? 'none' : 'block';
      if (reasonWrap) reasonWrap.style.display = isComp ? 'block' : 'none';
    },

    submitAdd: function() {
      var self = this;
      var name = document.getElementById('at-add-name');
      var type = document.getElementById('at-add-type');
      var pay = document.getElementById('at-add-payment');
      if (!name || !String(name.value || '').trim()) { window.eccNotify('Attendee name is required.'); name.focus(); return; }
      if (!type || !Number(type.value)) { window.eccNotify('Select a ticket type.'); return; }
      var payload = {
        action: 'add_attendee',
        name: String(name.value).trim(),
        email: String((document.getElementById('at-add-email') || {}).value || '').trim(),
        phone: String((document.getElementById('at-add-phone') || {}).value || '').trim(),
        ticket_type_id: Number(type.value),
        payment_status: pay.value,
        amount: Number((document.getElementById('at-add-amount') || {}).value || 0),
        reason: String((document.getElementById('at-add-reason') || {}).value || '').trim(),
        organization: String((document.getElementById('at-add-org') || {}).value || '').trim()
      };
      this.post(payload).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        closeEccModal('modal-add-attendee');
        window.eccNotify('Attendee registered — ticket ' + ((body.result && body.result.ticket_id) || 'issued'));
        self.loadWorkspace();
        self.loadAttendees();
      });
    },

    /* ── Import ───────────────────────────────────────────── */
    openImportModal: function() {
      document.getElementById('at-import-data').value = '';
      var result = document.getElementById('at-import-result');
      if (result) { result.style.display = 'none'; result.innerHTML = ''; }
      openEccModal('modal-import-attendees');
    },

    parseImportRows: function(text) {
      text = String(text || '').trim();
      if (!text) return [];
      var trimmed = text.trim();
      if (trimmed.charAt(0) === '[' || trimmed.charAt(0) === '{') {
        try {
          var arr = JSON.parse(trimmed);
          if (!Array.isArray(arr)) return [];
          return arr.map(function(o) {
            return { name: o.name || '', email: o.email || '', phone: o.phone || '', organization: o.organization || '', ticket_type: o.ticket_type || o.ticket_type_name || '' };
          });
        } catch (e) { return []; }
      }
      return text.split(/\r?\n/).map(function(line) {
        line = line.trim();
        if (!line) return null;
        var f = line.split(/[,;\t]/).map(function(x) { return String(x).trim(); });
        return { name: f[0] || '', email: f[1] || '', phone: f[2] || '', ticket_type: f[3] || '' };
      }).filter(Boolean);
    },

    submitImport: function() {
      var self = this;
      var ta = document.getElementById('at-import-data');
      var rows = this.parseImportRows(ta.value);
      var result = document.getElementById('at-import-result');
      if (rows.length === 0) {
        window.eccNotify('Paste at least one attendee row.');
        return;
      }
      if (result) { result.style.display = 'block'; result.innerHTML = '<div class="ecc-tk-empty">Validating…</div>'; }
      this.post({ action: 'import', rows: rows }).then(function(body) {
        if (!body.success) {
          if (result) { result.style.display = 'block'; result.innerHTML = '<div class="ecc-tk-insight warn"><span>⚠</span>' + tkEsc(self.errMsg(body)) + '</div>'; }
          return;
        }
        var r = (body.result && body.result.result) || {};
        var h = '<div class="ecc-tk-insight info"><span>✅</span><strong>' + r.created + '</strong> of <strong>' + r.total + '</strong> attendees imported.</div>';
        var dups = r.duplicates || [];
        var inv = r.invalid || [];
        if (dups.length) {
          h += '<div style="margin-top:0.5rem;font-size:0.74rem;font-weight:700;color:#f59e0b;">Skipped duplicates (' + dups.length + '):</div>';
          dups.forEach(function(x) { h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);">Row ' + x.row + ' — ' + tkEsc(x.name || '') + (x.email ? ' (' + tkEsc(x.email) + ')' : '') + '</div>'; });
        }
        if (inv.length) {
          h += '<div style="margin-top:0.5rem;font-size:0.74rem;font-weight:700;color:#ef4444;">Invalid rows (' + inv.length + '):</div>';
          inv.forEach(function(x) { h += '<div style="font-size:0.7rem;color:var(--ecc-text-dim);">Row ' + x.row + ' — ' + tkEsc(x.error) + '</div>'; });
        }
        if (result) { result.style.display = 'block'; result.innerHTML = h; }
        if (r.created > 0) {
          window.eccNotify('Imported ' + r.created + ' attendee' + (r.created === 1 ? '' : 's'));
          self.loadWorkspace();
          self.loadAttendees();
        }
      });
    },

    /* ── Messaging ────────────────────────────────────────── */
    openMessageModal: function(ticketId) {
      if (ticketId) {
        this.state.selection = {};
        this.state.selection[ticketId] = true;
        this.renderBulkBar();
      }
      var n = Object.keys(this.state.selection).length;
      if (n === 0) { window.eccNotify('Select at least one attendee to message.'); return; }
      var label = document.getElementById('at-msg-targets');
      if (label) label.textContent = 'Sending to ' + n + ' attendee' + (n === 1 ? '' : 's') + '.';
      document.getElementById('at-msg-subject').value = '';
      document.getElementById('at-msg-body').value = '';
      openEccModal('modal-message-attendees');
    },

    submitMessage: function() {
      var self = this;
      var subject = document.getElementById('at-msg-subject');
      if (!String(subject.value || '').trim()) { window.eccNotify('Message subject is required.'); subject.focus(); return; }
      var body = document.getElementById('at-msg-body');
      var ids = Object.keys(this.state.selection);
      this.post({ action: 'message', subject: String(subject.value).trim(), body: String(body.value || '').trim(), ticket_ids: ids }).then(function(res) {
        if (!res.success) { window.eccNotify(self.errMsg(res)); return; }
        closeEccModal('modal-message-attendees');
        window.eccNotify('Message queued for ' + ids.length + ' attendee' + (ids.length === 1 ? '' : 's'));
        self.loadWorkspace();
      });
    },

    /* ── Export ───────────────────────────────────────────── */
    downloadCsv: function(filename, rows) {
      var cols = ['name', 'email', 'phone', 'organization', 'ticket_id', 'ticket_type_name', 'category', 'price', 'booking_id', 'payment_status', 'payment_gateway', 'attendance_status', 'checked_in_at', 'checked_in_gate', 'registered_at'];
      var esc = function(v) {
        v = (v === null || v === undefined) ? '' : String(v);
        return '"' + v.replace(/"/g, '""') + '"';
      };
      var lines = [cols.join(',')];
      rows.forEach(function(r) {
        lines.push(cols.map(function(c) { return esc(r[c]); }).join(','));
      });
      var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = filename + '.csv';
      document.body.appendChild(a);
      a.click();
      setTimeout(function() { URL.revokeObjectURL(a.href); a.remove(); }, 400);
    },

    exportData: function(type) {
      var self = this;
      this.get('export', { type: type }).then(function(body) {
        if (!body.success) { window.eccNotify(self.errMsg(body)); return; }
        var exp = (body.result && body.result.export) || {};
        var rows = exp.rows || [];
        self.downloadCsv(exp.filename || ('attendees-' + type), rows);
        window.eccNotify('Exported ' + rows.length + ' rows');
      });
    },

    bulkExport: function() {
      var ids = Object.keys(this.state.selection);
      if (ids.length === 0) { window.eccNotify('Select at least one attendee to export.'); return; }
      var rows = this.state.attendees.filter(function(a) { return ids.indexOf(a.ticket_id) !== -1; });
      this.downloadCsv('attendees-selected-' + this.state.listingId, rows);
      window.eccNotify('Exported ' + rows.length + ' selected rows');
    }
  };

  AttendeesControlCenter.init();

  /* ── Marketing & Commercial Growth Control Center ──────────── */
  var mktDoc = document.getElementById('events-workspace');
  var mktApiBase = (mktDoc && mktDoc.dataset.baseUrl ? mktDoc.dataset.baseUrl : '') + 'api/tie/vendor/events/marketing.php';

  window.MarketingControlCenter = {
    state: {
      activeTab: 'overview',
      dateRange: '30',
      chartMetric: 'revenue',
      searchQuery: '',
      aiPanelOpen: true,
      events: [],
      campaigns: [],
      promotions: [],
      promocodes: [],
      adCard: {
        template: 'announcement',
        headline: 'MALAWI MUSIC FESTIVAL 2026',
        subtitle: 'Live at Kamuzu Stadium · 09 Sept 2026',
        price: 'From MK 8,000',
        cta: 'GET TICKETS',
        accentColor: '#f97316'
      },
      wizStep: 1,
      wizDraft: {}
    },

    get: function(action, params) {
      var qs = '?action=' + encodeURIComponent(action);
      Object.keys(params || {}).forEach(function(k) { qs += '&' + k + '=' + encodeURIComponent(params[k]); });
      return fetch(mktApiBase + qs, { credentials: 'same-origin' }).then(function(r) { return r.json().catch(function() { return {}; }); });
    },

    post: function(data) {
      var csrf = mktDoc && mktDoc.dataset ? mktDoc.dataset.csrf : '';
      return fetch(mktApiBase, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(data)
      }).then(function(r) { return r.json().catch(function() { return {}; }); });
    },

    init: function() {
      this.loadOverview();
      this.loadCampaigns();
      this.loadPromotions();
      this.loadPromoCodes();
      this.renderAdCardPreview();
      this.renderAudienceSegments();
      this.renderChannels();
      this.renderAutomations();
      this.renderAiAlerts();
    },

    loadOverview: function() {
      var self = this;
      this.get('overview', { date_range: this.state.dateRange }).then(function(body) {
        if (!body || !body.result) return;
        var res = body.result;
        if (res.active_campaigns !== undefined) {
          var elAct = document.getElementById('mkt-kpi-active-campaigns');
          if (elAct) elAct.textContent = res.active_campaigns;
          var elReach = document.getElementById('mkt-kpi-reach');
          if (elReach) elReach.textContent = res.reach;
          var elClick = document.getElementById('mkt-kpi-interactions');
          if (elClick) elClick.textContent = res.interactions;
          var elSales = document.getElementById('mkt-kpi-sales');
          if (elSales) elSales.textContent = res.sales;
          var elRev = document.getElementById('mkt-kpi-revenue');
          if (elRev) elRev.textContent = res.revenue;
          var elConv = document.getElementById('mkt-kpi-conversion');
          if (elConv) elConv.textContent = res.conversion;

          if (res.event_performance) {
            self.state.events = res.event_performance;
            self.populateEventSelects();
            self.renderOverview();
          }
        }
      });
    },

    loadCampaigns: function() {
      var self = this;
      var statusFilter = (document.getElementById('mkt-cmp-status-filter') || {}).value || 'all';
      var channelFilter = (document.getElementById('mkt-cmp-channel-filter') || {}).value || 'all';
      this.get('campaigns', { status: statusFilter, channel: channelFilter, q: this.state.searchQuery }).then(function(body) {
        if (body && body.result && body.result.campaigns) {
          self.state.campaigns = body.result.campaigns;
          self.renderCampaigns();
        }
      });
    },

    loadPromotions: function() {
      var self = this;
      this.get('promotions').then(function(body) {
        if (body && body.result && body.result.promotions) {
          self.state.promotions = body.result.promotions;
          self.renderPromotions();
        }
      });
    },

    loadPromoCodes: function() {
      var self = this;
      this.get('promocodes').then(function(body) {
        if (body && body.result && body.result.promocodes) {
          self.state.promocodes = body.result.promocodes;
          self.renderPromoCodes();
        }
      });
    },

    switchTab: function(tabId, btn) {
      this.state.activeTab = tabId;
      var tabs = document.querySelectorAll('#mkt-sub-nav .ecc-mkt-tab');
      tabs.forEach(function(t) { t.classList.remove('active'); });
      if (!btn) {
        tabs.forEach(function(t) {
          if ((t.getAttribute('onclick') || '').indexOf("'" + tabId + "'") !== -1) btn = t;
        });
      }
      if (btn) btn.classList.add('active');

      var views = document.querySelectorAll('#mkt-tab-content .mkt-subview');
      views.forEach(function(v) { v.style.display = 'none'; });

      var target = document.getElementById('mkt-view-' + tabId);
      if (target) target.style.display = 'block';

      if (tabId === 'overview') this.loadOverview();
      if (tabId === 'campaigns') this.loadCampaigns();
      if (tabId === 'promotions') this.loadPromotions();
      if (tabId === 'adcards') this.renderAdCardPreview();
      if (tabId === 'audience') this.renderAudienceSegments();
      if (tabId === 'channels') this.renderChannels();
      if (tabId === 'promocodes') this.loadPromoCodes();
      if (tabId === 'automations') this.renderAutomations();
    },

    populateEventSelects: function() {
      var sel1 = document.getElementById('mkt-cmp-event-filter');
      var sel2 = document.getElementById('mkt-wiz-event-select');
      var sel3 = document.getElementById('promo-modal-event');
      if (!sel1 && !sel2 && !sel3) return;

      var opts = '<option value="all">All Events</option>';
      var optsWiz = '<option value="">Select an event…</option>';
      this.state.events.forEach(function(e) {
        opts += '<option value="' + e.id + '">' + e.title + '</option>';
        optsWiz += '<option value="' + e.id + '">' + e.title + '</option>';
      });
      if (sel1) sel1.innerHTML = opts;
      if (sel2) sel2.innerHTML = optsWiz;
      if (sel3) sel3.innerHTML = optsWiz;
    },

    setDateRange: function(val) {
      this.state.dateRange = val;
      window.eccNotify('Marketing view filtered to: ' + (val === 'all' ? 'All Time' : 'Last ' + val + ' Days'));
      this.loadOverview();
    },

    setChartMetric: function(metric, btn) {
      this.state.chartMetric = metric;
      var btns = document.querySelectorAll('.mkt-chart-btn');
      btns.forEach(function(b) { b.classList.remove('active'); });
      if (btn) btn.classList.add('active');
      this.renderChartBars();
    },

    renderOverview: function() {
      this.renderChartBars();
      
      var container = document.getElementById('mkt-event-performance-list');
      if (!container) return;

      var html = '';
      this.state.events.forEach(function(e) {
        html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0.75rem;background:var(--ecc-surface-2);border-radius:8px;border:1px solid var(--ecc-border);">' +
          '<div>' +
            '<strong style="font-size:0.8rem;display:block;color:var(--ecc-text-bright);">' + e.title + '</strong>' +
            '<span style="font-size:0.68rem;color:var(--ecc-text-dim);">' + e.reach + ' reach · ' + e.sales + ' sales</span>' +
          '</div>' +
          '<div style="text-align:right;">' +
            '<strong style="font-size:0.85rem;color:#10b981;display:block;">' + e.revenue + '</strong>' +
            '<button type="button" class="ecc-btn ecc-btn-secondary" data-mkt-action="campaign-create" data-mkt-target="' + e.id + '" style="font-size:0.65rem;padding:0.15rem 0.4rem;margin-top:2px;" onclick="MarketingControlCenter.promoteEvent(\'' + e.id + '\')">Promote →</button>' +
          '</div>' +
        '</div>';
      });
      container.innerHTML = html;
    },

    renderChartBars: function() {
      var container = document.getElementById('mkt-chart-bars');
      if (!container) return;

      var data = {
        revenue: [{ label: 'W1', val: 'MK 850K', h: 35 }, { label: 'W2', val: 'MK 1.2M', h: 55 }, { label: 'W3', val: 'MK 1.8M', h: 78 }, { label: 'W4', val: 'MK 4.8M', h: 95 }],
        tickets: [{ label: 'W1', val: '180', h: 30 }, { label: 'W2', val: '320', h: 52 }, { label: 'W3', val: '740', h: 72 }, { label: 'W4', val: '1,240', h: 92 }],
        reach: [{ label: 'W1', val: '8.4K', h: 25 }, { label: 'W2', val: '14.2K', h: 48 }, { label: 'W3', val: '28.1K', h: 75 }, { label: 'W4', val: '42.8K', h: 98 }],
        conversion: [{ label: 'W1', val: '2.1%', h: 38 }, { label: 'W2', val: '3.4%', h: 60 }, { label: 'W3', val: '4.1%', h: 75 }, { label: 'W4', val: '4.7%', h: 90 }]
      }[this.state.chartMetric] || [];

      var html = '';
      data.forEach(function(item) {
        html += '<div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;">' +
          '<span style="font-size:0.65rem;font-weight:800;color:var(--ecc-primary);margin-bottom:4px;">' + item.val + '</span>' +
          '<div style="width:100%;max-width:42px;height:' + item.h + '%;background:linear-gradient(180deg,var(--ecc-primary) 0%,rgba(230,57,70,0.3) 100%);border-radius:6px 6px 0 0;transition:height 0.3s ease;"></div>' +
        '</div>';
      });
      container.innerHTML = html;
    },

    renderCampaigns: function() {
      var container = document.getElementById('mkt-campaigns-list');
      if (!container) return;

      var status = (document.getElementById('mkt-cmp-status-filter') || {}).value || 'all';
      var listingId = (document.getElementById('mkt-cmp-event-filter') || {}).value || 'all';
      var channel = (document.getElementById('mkt-cmp-channel-filter') || {}).value || 'all';
      var list = this.state.campaigns.filter(function(c) {
        return (status === 'all' || c.status === status) &&
          (listingId === 'all' || c.listing_id === listingId || c.event_id === listingId) &&
          (channel === 'all' || c.channel === channel);
      });

      if (list.length === 0) {
        container.innerHTML = '<div style="grid-column:1/-1;padding:2rem;text-align:center;color:var(--ecc-text-dim);">No campaigns matching current filters. <button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="campaign-create" style="margin-left:0.5rem;" onclick="MarketingControlCenter.openCreateWizard()">Create Campaign</button></div>';
        return;
      }

      var html = '';
      list.forEach(function(c) {
        var statusBadge = {
          active: '<span class="ecc-pill emerald" style="font-size:0.6rem;">● ACTIVE</span>',
          scheduled: '<span class="ecc-pill blue" style="font-size:0.6rem;">● SCHEDULED</span>',
          paused: '<span class="ecc-pill amber" style="font-size:0.6rem;">● PAUSED</span>',
          completed: '<span class="ecc-pill purple" style="font-size:0.6rem;">● COMPLETED</span>',
          draft: '<span class="ecc-pill" style="font-size:0.6rem;">● DRAFT</span>'
        }[c.status] || '';

        html += '<div class="ecc-mkt-cmp-card">' +
          '<div>' +
            '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.4rem;">' +
              '<span style="font-size:0.68rem;font-weight:800;color:var(--ecc-primary);text-transform:uppercase;">' + c.obj + '</span>' +
              statusBadge +
            '</div>' +
            '<h4 style="font-size:0.95rem;font-weight:900;margin:0 0 0.2rem 0;color:var(--ecc-text-bright);">' + c.title + '</h4>' +
            '<p style="font-size:0.72rem;color:var(--ecc-text-dim);margin:0 0 0.8rem 0;">' + c.event + '</p>' +

            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.4rem;background:var(--ecc-surface-2);padding:0.6rem;border-radius:8px;font-size:0.72rem;margin-bottom:0.8rem;">' +
              '<div><span style="color:var(--ecc-text-dim);">Reach:</span> <strong>' + c.reach + '</strong></div>' +
              '<div><span style="color:var(--ecc-text-dim);">Clicks:</span> <strong>' + c.clicks + '</strong></div>' +
              '<div><span style="color:var(--ecc-text-dim);">Tickets:</span> <strong>' + c.tickets + '</strong></div>' +
              '<div><span style="color:var(--ecc-text-dim);">Revenue:</span> <strong style="color:#10b981;">' + c.revenue + '</strong></div>' +
            '</div>' +
          '</div>' +

          '<div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--ecc-border);padding-top:0.6rem;">' +
            '<span style="font-size:0.7rem;color:var(--ecc-text-dim);">Conv: <strong style="color:var(--ecc-text-bright);">' + c.conversion + '</strong></span>' +
            '<div style="display:flex;gap:0.3rem;">' +
              (c.status === 'active' ? '<button type="button" class="ecc-btn ecc-btn-secondary" data-mkt-action="campaign-toggle" data-mkt-target="' + c.id + '" style="font-size:0.65rem;padding:0.2rem 0.5rem;" onclick="MarketingControlCenter.toggleCampaignStatus(\'' + c.id + '\')">Pause</button>' : '<button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="campaign-toggle" data-mkt-target="' + c.id + '" style="font-size:0.65rem;padding:0.2rem 0.5rem;" onclick="MarketingControlCenter.toggleCampaignStatus(\'' + c.id + '\')">Activate</button>') +
              '<button type="button" class="ecc-btn ecc-btn-secondary" data-mkt-action="campaign-view" data-mkt-target="' + c.id + '" style="font-size:0.65rem;padding:0.2rem 0.5rem;" onclick="MarketingControlCenter.viewCampaign(\'' + c.id + '\')">View →</button>' +
            '</div>' +
          '</div>' +
        '</div>';
      });
      container.innerHTML = html;
    },

    toggleCampaignStatus: function(id) {
      var self = this;
      this.post({ action: 'toggle_campaign_status', campaign_id: id }).then(function(body) {
        if (body && body.result && body.result.campaigns) {
          self.state.campaigns = body.result.campaigns;
          window.eccNotify('Campaign status updated.');
          self.renderCampaigns();
        } else {
          window.eccNotify((body && body.error && body.error.message) || 'Campaign status could not be changed.');
        }
      }).catch(function() {
        window.eccNotify('Campaign status request failed. Please retry.');
      });
    },

    viewCampaign: function(id) {
      var campaign = (this.state.campaigns || []).find(function(item) { return item.id === id; });
      if (!campaign) { window.eccNotify('Campaign details are no longer available.'); return; }
      var content = document.getElementById('mkt-campaign-detail-content');
      if (content) {
        content.innerHTML = '<div class="ecc-card" style="padding:1rem;">'
          + '<div style="display:flex;justify-content:space-between;gap:0.8rem;align-items:flex-start;"><div><strong style="font-size:1rem;display:block;">' + campaign.title + '</strong><span style="font-size:0.72rem;color:var(--ecc-text-dim);">' + campaign.event + '</span></div><span class="ecc-pill purple">' + campaign.status + '</span></div>'
          + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;margin-top:1rem;font-size:0.76rem;">'
          + '<div>Objective<strong style="display:block;margin-top:0.15rem;">' + campaign.obj + '</strong></div>'
          + '<div>Channel<strong style="display:block;margin-top:0.15rem;">' + campaign.channel + '</strong></div>'
          + '<div>Reach<strong style="display:block;margin-top:0.15rem;">' + campaign.reach + '</strong></div>'
          + '<div>Clicks<strong style="display:block;margin-top:0.15rem;">' + campaign.clicks + '</strong></div>'
          + '<div>Tickets<strong style="display:block;margin-top:0.15rem;">' + campaign.tickets + '</strong></div>'
          + '<div>Attributed revenue<strong style="display:block;margin-top:0.15rem;color:#10b981;">' + campaign.revenue + '</strong></div>'
          + '</div></div>';
      }
      window.openEccModal('modal-mkt-campaign-detail');
    },

    renderPromotions: function() {
      var container = document.getElementById('mkt-promotions-list');
      if (!container) return;

      var html = '';
      this.state.promotions.forEach(function(p) {
        html += '<div class="ecc-card" style="display:flex;flex-direction:column;justify-content:space-between;">' +
          '<div>' +
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;">' +
              '<span class="ecc-pill amber" style="font-size:0.65rem;font-weight:900;">' + p.discount + '</span>' +
              '<span class="ecc-pill ' + (String(p.status || '').toLowerCase() === 'active' ? 'emerald' : 'amber') + '" style="font-size:0.6rem;">● ' + String(p.status || 'paused').toUpperCase() + '</span>' +
            '</div>' +
            '<h4 style="font-size:0.95rem;font-weight:900;margin:0 0 0.2rem 0;">' + p.title + '</h4>' +
            '<p style="font-size:0.72rem;color:var(--ecc-text-dim);margin:0 0 0.75rem 0;">' + p.event + '</p>' +
            '<div style="font-size:0.72rem;color:var(--ecc-text-dim);margin-bottom:0.4rem;"><i class="far fa-clock"></i> Valid until: <strong>' + p.validUntil + '</strong></div>' +
            '<div style="font-size:0.72rem;color:var(--ecc-text-dim);"><i class="fas fa-ticket-alt"></i> Claimed: <strong>' + p.used + '</strong></div>' +
          '</div>' +
          '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem;border-top:1px solid var(--ecc-border);padding-top:0.6rem;">' +
            '<span style="font-size:0.78rem;font-weight:800;color:#10b981;">' + p.revenue + '</span>' +
            '<div style="display:flex;gap:0.35rem;">'
            + '<button type="button" class="ecc-btn ecc-btn-secondary" data-mkt-action="promotion-manage" data-mkt-target="' + p.id + '" style="font-size:0.68rem;padding:0.25rem 0.6rem;" onclick="MarketingControlCenter.managePromotion(\'' + p.id + '\')">Manage</button>'
            + '<button type="button" class="ecc-btn ecc-btn-secondary" data-mkt-action="promotion-toggle" data-mkt-target="' + p.id + '" style="font-size:0.68rem;padding:0.25rem 0.6rem;" onclick="MarketingControlCenter.togglePromotionStatus(\'' + p.id + '\')">' + (String(p.status || '').toLowerCase() === 'active' ? 'Pause' : 'Activate') + '</button>'
            + '</div>' +
          '</div>' +
        '</div>';
      });
      container.innerHTML = html;
    },

    renderPromoCodes: function() {
      var tbody = document.getElementById('mkt-promocodes-tbody');
      if (!tbody) return;

      var html = '';
      this.state.promocodes.forEach(function(c) {
        html += '<tr>' +
          '<td><strong style="font-family:monospace;font-size:0.85rem;color:var(--ecc-primary);background:var(--ecc-surface-2);padding:0.2rem 0.4rem;border-radius:4px;">' + c.code + '</strong></td>' +
          '<td><span class="ecc-pill amber" style="font-size:0.65rem;">' + c.type + '</span></td>' +
          '<td>' + c.cap + ' uses</td>' +
          '<td><strong>' + c.used + '</strong> / ' + c.cap + '</td>' +
          '<td>' + c.sales + ' tickets</td>' +
          '<td><strong style="color:#10b981;">' + c.revenue + '</strong></td>' +
          '<td>' + (c.status === 'Active' ? '<span class="ecc-pill emerald" style="font-size:0.6rem;">ACTIVE</span>' : '<span class="ecc-pill" style="font-size:0.6rem;">EXPIRED</span>') + '</td>' +
          '<td><button class="ecc-btn ecc-btn-secondary" style="font-size:0.65rem;padding:0.15rem 0.4rem;" onclick="MarketingControlCenter.copyPromoCode(\'' + c.code.replace(/'/g, "\\\\'") + '\')">Copy</button></td>' +
        '</tr>';
      });
      tbody.innerHTML = html;
    },

    renderChannels: function() {
      var container = document.getElementById('mkt-channels-list');
      if (!container) return;

      var channels = [
        { name: 'Uthenga Marketplace (Explore & Events)', pct: 52, reach: '22.2K', sales: 640, rev: 'MK 2.5M', color: '#3b82f6' },
        { name: 'In-App Mobile Push Notifications', pct: 28, reach: '12.0K', sales: 350, rev: 'MK 1.3M', color: '#8b5cf6' },
        { name: 'Email Newsletter Broadcasts', pct: 14, reach: '6.0K', sales: 180, rev: 'MK 720K', color: '#10b981' },
        { name: 'SMS Instant Direct Short-links', pct: 6, reach: '2.6K', sales: 70, rev: 'MK 280K', color: '#f59e0b' }
      ];

      var html = '';
      channels.forEach(function(ch) {
        html += '<div>' +
          '<div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.3rem;">' +
            '<strong>' + ch.name + '</strong>' +
            '<span><strong style="color:var(--ecc-text-bright);">' + ch.sales + ' sales</strong> (' + ch.rev + ')</span>' +
          '</div>' +
          '<div style="height:10px;background:var(--ecc-surface-2);border-radius:999px;overflow:hidden;margin-bottom:0.2rem;">' +
            '<div style="height:100%;width:' + ch.pct + '%;background:' + ch.color + ';border-radius:999px;"></div>' +
          '</div>' +
          '<div style="display:flex;justify-content:space-between;font-size:0.68rem;color:var(--ecc-text-dim);">' +
            '<span>Reach: ' + ch.reach + '</span><span>Attribution Share: ' + ch.pct + '%</span>' +
          '</div>' +
        '</div>';
      });
      container.innerHTML = html;
    },

    renderAudienceSegments: function() {
      var container = document.getElementById('mkt-audience-segments-list');
      if (!container) return;

      var segments = [
        { title: 'High Intent Prospects', count: '1,240 users', desc: 'Viewed event listing 3+ times without checking out', tag: 'High Value' },
        { title: 'Frequent Event Attendees', count: '620 users', desc: 'Purchased tickets to 2+ events in the last 6 months', tag: 'Loyal' },
        { title: 'VIP Ticket Buyers', count: '180 users', desc: 'Consistently purchase VVIP / VIP experience passes', tag: 'Premium' },
        { title: 'Abandoned Checkout Carts', count: '340 users', desc: 'Started checkout step but did not complete payment', tag: 'Urgent' },
        { title: 'Dormant Past Buyers', count: '2,410 users', desc: 'Haven’t purchased an event ticket in over 90 days', tag: 'Re-target' }
      ];

      var html = '';
      segments.forEach(function(s) {
        html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:0.65rem 0.8rem;background:var(--ecc-surface-2);border-radius:8px;border:1px solid var(--ecc-border);">' +
          '<div>' +
            '<div style="display:flex;align-items:center;gap:0.4rem;">' +
              '<strong style="font-size:0.82rem;color:var(--ecc-text-bright);">' + s.title + '</strong>' +
              '<span class="ecc-pill purple" style="font-size:0.6rem;">' + s.tag + '</span>' +
            '</div>' +
            '<span style="font-size:0.7rem;color:var(--ecc-text-dim);">' + s.desc + '</span>' +
          '</div>' +
          '<div style="text-align:right;">' +
            '<strong style="font-size:0.85rem;color:var(--ecc-primary);display:block;">' + s.count + '</strong>' +
            '<button type="button" class="ecc-btn ecc-btn-secondary" data-mkt-action="campaign-create" data-mkt-preset="segment" style="font-size:0.65rem;padding:0.15rem 0.4rem;margin-top:2px;" onclick="MarketingControlCenter.openCreateWizard(\'segment\')">Campaign →</button>' +
          '</div>' +
        '</div>';
      });
      container.innerHTML = html;
    },

    renderAutomations: function() {
      var container = document.getElementById('mkt-automations-grid');
      if (!container) return;

      var flows = [
        { title: 'Abandoned Checkout Recovery', desc: 'Triggers a push notification 30 minutes after checkout drop-off.', active: true },
        { title: 'VIP Scarcity Alert', desc: 'Triggers urgency notification when VIP inventory falls below 20%.', active: true },
        { title: 'Event 48h Approaching Reminder', desc: 'Sends event reminder and ticket pass QR code 48 hours before start.', active: true },
        { title: 'Early Bird Expiry Warning', desc: 'Notifies high-intent prospects 24 hours before discount expires.', active: true }
      ];

      var html = '';
      flows.forEach(function(f, idx) {
        html += '<div style="background:var(--ecc-surface-2);border:1px solid var(--ecc-border);border-radius:10px;padding:0.85rem;display:flex;flex-direction:column;justify-content:space-between;">' +
          '<div>' +
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;">' +
              '<strong style="font-size:0.85rem;color:var(--ecc-text-bright);">' + f.title + '</strong>' +
              '<label style="display:inline-flex;align-items:center;cursor:not-allowed;opacity:.65;" title="Delivery automation is not configured yet."><input type="checkbox" ' + (f.active ? 'checked' : '') + ' disabled></label>' +
            '</div>' +
            '<p style="font-size:0.72rem;color:var(--ecc-text-dim);margin:0 0 0.8rem 0;">' + f.desc + '</p>' +
          '</div>' +
          '<div style="font-size:0.68rem;color:#f59e0b;font-weight:700;"><i class="fas fa-info-circle"></i> Delivery automation not configured</div>' +
        '</div>';
      });
      container.innerHTML = html;
    },

    renderAiAlerts: function() {
      var container = document.getElementById('mkt-ai-alerts-container');
      if (!container) return;

      var alerts = [
        { type: 'warn', title: 'Sales Slowing Warning', msg: 'Music Festival ticket sales dropped 18% today compared to 3-day avg.', btnText: 'Investigate', action: 'MarketingControlCenter.investigateCampaign("cmp-1")' },
        { type: 'opp', title: 'High-Intent Opportunity', msg: '340 customers viewed Malawi Business Summit but haven’t purchased.', btnText: 'Create Promotion', action: 'MarketingControlCenter.openCreateWizard("early_bird")' },
        { type: 'info', title: 'Inventory Protection', msg: 'VIP tickets for Music Festival are 87% sold out. Auto-stop active.', btnText: 'Adjust Campaign', action: 'MarketingControlCenter.openCreateWizard("vip_promo")' }
      ];

      var html = '';
      alerts.forEach(function(a) {
        html += '<div class="mkt-ai-card ' + a.type + '">' +
          '<strong style="font-size:0.78rem;display:block;color:var(--ecc-text-bright);margin-bottom:0.25rem;">' + a.title + '</strong>' +
          '<p style="font-size:0.7rem;color:var(--ecc-text-dim);margin:0 0 0.5rem 0;line-height:1.35;">' + a.msg + '</p>' +
          '<button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="' + (a.type === 'warn' ? 'campaign-investigate' : 'campaign-create') + '" data-mkt-target="' + (a.type === 'warn' ? 'cmp-1' : '') + '" data-mkt-preset="' + (a.type === 'opp' ? 'early_bird' : (a.type === 'info' ? 'vip_promo' : '')) + '" style="font-size:0.68rem;padding:0.2rem 0.5rem;" onclick="' + a.action + '">' + a.btnText + '</button>' +
        '</div>';
      });
      container.innerHTML = html;
    },

    toggleAiPanel: function() {
      this.state.aiPanelOpen = !this.state.aiPanelOpen;
      var panel = document.getElementById('mkt-ai-panel');
      if (panel) panel.style.display = (this.state.aiPanelOpen ? 'flex' : 'none');
    },

    handleSearch: function(q) {
      this.state.searchQuery = q;
      if (this.state.activeTab === 'campaigns') this.loadCampaigns();
    },

    /* ── Ad Card Studio Functions ────────────────────────────── */
    setAdTemplate: function(tpl, btn) {
      this.state.adCard.template = tpl;
      var btns = document.querySelectorAll('#mkt-card-templates button');
      btns.forEach(function(b) { b.classList.remove('active'); });
      if (btn) btn.classList.add('active');

      if (tpl === 'earlybird') {
        document.getElementById('ad-field-headline').value = 'EARLY BIRD TIX · 30% OFF';
        document.getElementById('ad-field-price').value = 'SAVE UP TO MK 10,000';
      } else if (tpl === 'vip') {
        document.getElementById('ad-field-headline').value = 'VIP EXPERIENCE PASS';
        document.getElementById('ad-field-price').value = 'MK 25,000 VIP';
      }
      this.updateAdCardPreview();
    },

    setAdColor: function(hex) {
      this.state.adCard.accentColor = hex;
      this.updateAdCardPreview();
    },

    generateAiCardCopy: function() {
      var copyList = [
        { headline: 'UNMISSABLE CULTURAL SPECTACLE', sub: 'Kamuzu Stadium · Lilongwe', price: 'Tickets from MK 5,000' },
        { headline: 'FEEL THE LIVE ENERGY 2026', sub: 'BICC Lilongwe · Limited Seats', price: 'Early Bird MK 12,000' },
        { headline: 'EXCLUSIVE VIP NIGHT EXPERIENCE', sub: 'Sunbird Capital · Gold Access', price: 'VVIP MK 35,000' }
      ];
      var rand = copyList[Math.floor(Math.random() * copyList.length)];
      document.getElementById('ad-field-headline').value = rand.headline;
      document.getElementById('ad-field-sub').value = rand.sub;
      document.getElementById('ad-field-price').value = rand.price;
      window.eccNotify('A campaign copy template was inserted. Review it before saving.');
      this.updateAdCardPreview();
    },

    renderAdCardPreview: function() {
      this.updateAdCardPreview();
    },

    updateAdCardPreview: function() {
      var canvas = document.getElementById('ad-card-canvas');
      if (!canvas) return;

      var headline = (document.getElementById('ad-field-headline') || {}).value || this.state.adCard.headline;
      var sub = (document.getElementById('ad-field-sub') || {}).value || this.state.adCard.subtitle;
      var price = (document.getElementById('ad-field-price') || {}).value || this.state.adCard.price;
      var cta = (document.getElementById('ad-field-cta') || {}).value || this.state.adCard.cta;
      var color = this.state.adCard.accentColor || '#f97316';

      canvas.innerHTML = '<div style="position:relative;background:linear-gradient(180deg,#1f2937 0%,#111827 100%);padding:1.5rem;display:flex;flex-direction:column;gap:1.2rem;width:280px;border-radius:16px;">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;">' +
          '<span class="uthenga-tk-logo-img" style="color:' + color + ';width:22px;height:22px;display:inline-block;"></span>' +
          '<span class="ecc-pill" style="font-size:0.6rem;background:' + color + ';color:#fff;">PROMOTIONAL CARD</span>' +
        '</div>' +
        '<div>' +
          '<h2 style="font-size:1.3rem;font-weight:900;margin:0 0 0.3rem 0;color:#fff;line-height:1.15;text-transform:uppercase;">' + headline + '</h2>' +
          '<p style="font-size:0.78rem;color:#9ca3af;margin:0;">' + sub + '</p>' +
        '</div>' +
        '<div style="display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,0.06);padding:0.7rem 0.9rem;border-radius:10px;border:1px solid rgba(255,255,255,0.1);">' +
          '<strong style="font-size:1rem;color:' + color + ';">' + price + '</strong>' +
          '<button type="button" class="ecc-btn" style="background:' + color + ';color:#fff;font-weight:900;font-size:0.75rem;padding:0.35rem 0.8rem;border:none;">' + cta + ' →</button>' +
        '</div>' +
      '</div>';
    },

    saveAdCard: function() {
      var self = this;
      var headline = (document.getElementById('ad-field-headline') || {}).value || 'Promotional Ad Card';
      var sub = (document.getElementById('ad-field-sub') || {}).value || '';
      var price = (document.getElementById('ad-field-price') || {}).value || '';
      var cta = (document.getElementById('ad-field-cta') || {}).value || 'GET TICKETS';

      this.post({
        action: 'save_adcard',
        template: this.state.adCard.template,
        headline: headline,
        subtitle: sub,
        price: price,
        cta: cta,
        accent_color: this.state.adCard.accentColor
      }).then(function(body) {
        if (body && body.result && body.result.adcard) {
          window.eccNotify('Ad card saved.');
        } else {
          window.eccNotify((body && body.error && body.error.message) || 'Ad card could not be saved.');
        }
      }).catch(function() {
        window.eccNotify('Ad card request failed. Please retry.');
      });
    },

    /* ── Campaign Wizard Navigation Functions ─────────────────── */
    openCreateWizard: function(presetType) {
      this.state.wizStep = 1;
      this.populateEventSelects();
      this.goToWizStep(1);
      if (presetType) {
        var normalizedPreset = {
          early_bird: 'Early Bird',
          vip_promo: 'VIP Promotion',
          segment: 'Re-engagement',
          custom_segment: 'Re-engagement'
        }[presetType] || presetType;
        var rad = document.querySelector('input[name="mkt_obj"][value="' + normalizedPreset + '"]');
        if (rad) rad.checked = true;
      }
      if (presetType === 'ad_card') {
        var adHeadline = document.getElementById('ad-field-headline');
        var adBody = document.getElementById('ad-field-sub');
        var campaignHeadline = document.getElementById('mkt-wiz-copy-title');
        var campaignBody = document.getElementById('mkt-wiz-copy-body');
        if (campaignHeadline && adHeadline && String(adHeadline.value || '').trim()) campaignHeadline.value = adHeadline.value.trim();
        if (campaignBody && adBody && String(adBody.value || '').trim()) campaignBody.value = adBody.value.trim();
      }
      var eventSelect = document.getElementById('mkt-wiz-event-select');
      if (eventSelect && eventSelect.value) {
        this.wizOnEventSelect(eventSelect.value);
      }
      var modal = document.getElementById('modal-mkt-campaign-wiz');
      if (window.openEccModal) {
        window.openEccModal('modal-mkt-campaign-wiz');
      } else if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
      }
      // The marketing overview loads asynchronously. A campaign may be opened
      // before that request returns, so refresh the event selector in-place
      // instead of leaving an apparently inert wizard with no choices.
      if (!this.state.events.length) {
        var self = this;
        this.get('overview', { date_range: this.state.dateRange }).then(function(body) {
          var result = body && body.result;
          if (!result || !Array.isArray(result.event_performance)) return;
          self.state.events = result.event_performance;
          self.populateEventSelects();
          var refreshedSelect = document.getElementById('mkt-wiz-event-select');
          if (refreshedSelect && refreshedSelect.value) self.wizOnEventSelect(refreshedSelect.value);
        });
      }
    },

    wizOnEventSelect: function(val) {
      var card = document.getElementById('mkt-wiz-event-card-preview');
      if (!card) return;
      var ev = (this.state.events || []).find(function(e) { return e.id === val; }) || { title: 'Selected Event', reach: '10K', sales: 200, revenue: 'MK 1M' };
      card.innerHTML = '<strong style="font-size:0.85rem;color:var(--ecc-text-bright);">' + (ev.title || 'Selected Event') + '</strong>' +
        '<div style="font-size:0.72rem;color:var(--ecc-text-dim);margin-top:0.3rem;">' + (ev.reach || '10K') + ' reach · ' + (ev.sales || 0) + ' ticket sales · ' + (ev.revenue || 'MK 0') + '</div>';
    },

    goToWizStep: function(step) {
      this.state.wizStep = step;
      var steps = document.querySelectorAll('#mkt-wiz-step-container .mkt-wiz-step');
      steps.forEach(function(s) { s.style.display = 'none'; });

      var target = document.getElementById('mkt-wiz-step-' + step);
      if (target) target.style.display = 'block';

      var pills = document.querySelectorAll('#mkt-wiz-nav-pills button');
      pills.forEach(function(p, idx) {
        if (idx + 1 === step) {
          p.className = 'ecc-btn ecc-btn-primary';
        } else {
          p.className = 'ecc-btn ecc-btn-secondary';
        }
      });

      var prevBtn = document.getElementById('mkt-wiz-prev-btn');
      var nextBtn = document.getElementById('mkt-wiz-next-btn');
      if (prevBtn) prevBtn.style.display = (step === 1 ? 'none' : 'inline-flex');
      if (nextBtn) nextBtn.textContent = (step === 8 ? '🚀 Launch Campaign Now' : 'Next Step →');

      if (step === 8) this.renderWizRecap();
    },

    wizPrevStep: function() {
      if (this.state.wizStep > 1) this.goToWizStep(this.state.wizStep - 1);
    },

    wizNextStep: function() {
      if (this.state.wizStep < 8) {
        this.goToWizStep(this.state.wizStep + 1);
      } else {
        this.launchCampaign();
      }
    },

    generateWizAiCopy: function() {
      document.getElementById('mkt-wiz-copy-title').value = 'MALAWI MUSIC FESTIVAL 2026 - TICKET FLASH SALE!';
      document.getElementById('mkt-wiz-copy-body').value = 'Get 25% OFF General Admission & VIP passes for a limited 48-hour window. Claim your ticket now on Uthenga!';
      window.eccNotify('A campaign copy template was inserted. Review it before saving.');
    },

    renderWizRecap: function() {
      var recap = document.getElementById('mkt-wiz-summary-recap');
      if (!recap) return;

      var obj = (document.querySelector('input[name="mkt_obj"]:checked') || {}).value || 'Drive Ticket Sales';
      var eventSelect = document.getElementById('mkt-wiz-event-select');
      var eventText = (eventSelect && eventSelect.selectedIndex >= 0 && eventSelect.options && eventSelect.options[eventSelect.selectedIndex]) ? eventSelect.options[eventSelect.selectedIndex].text : 'Malawi Music Festival 2026';
      var aud = (document.querySelector('input[name="mkt_aud"]:checked') || {}).value || 'All Customers';
      var title = (document.getElementById('mkt-wiz-copy-title') || {}).value || 'Campaign Title';

      recap.innerHTML = '<div><strong>Campaign Title:</strong> ' + title + '</div>' +
        '<div><strong>Objective:</strong> <span class="ecc-pill purple">' + obj + '</span></div>' +
        '<div><strong>Target Event:</strong> ' + eventText + '</div>' +
        '<div><strong>Target Audience:</strong> ' + aud + '</div>' +
        '<div><strong>Channels:</strong> Marketplace, In-App Push</div>' +
        '<div><strong>Schedule:</strong> 20 Aug 2026 → 30 Aug 2026</div>' +
        '<div style="margin-top:0.4rem;color:#10b981;font-weight:800;"><i class="fas fa-shield-alt"></i> Inventory Auto-Stop Protection Enabled</div>';
    },

    saveCampaignDraft: function() {
      this.persistCampaign('draft');
    },

    launchCampaign: function() {
      this.persistCampaign('active');
    },

    persistCampaign: function(requestedStatus) {
      var self = this;
      var title = (document.getElementById('mkt-wiz-copy-title') || {}).value || 'New Growth Campaign';
      var eventSelect = document.getElementById('mkt-wiz-event-select');
      var listingId = eventSelect ? eventSelect.value : '';
      var obj = (document.querySelector('input[name="mkt_obj"]:checked') || {}).value || 'Ticket Sales';
      var aud = (document.querySelector('input[name="mkt_aud"]:checked') || {}).value || 'All Customers';
      var offerType = (document.getElementById('mkt-wiz-offer-type') || {}).value || 'none';
      var offerVal = (document.getElementById('mkt-wiz-offer-val') || {}).value || '';
      var startDate = (document.getElementById('mkt-wiz-start') || {}).value || '';
      var endDate = (document.getElementById('mkt-wiz-end') || {}).value || '';
      var bodyText = (document.getElementById('mkt-wiz-copy-body') || {}).value || '';
      var channels = [];
      [['mkt-chan-marketplace', 'marketplace'], ['mkt-chan-push', 'notifications'], ['mkt-chan-email', 'email'], ['mkt-chan-sms', 'sms']].forEach(function(pair) {
        var box = document.getElementById(pair[0]);
        if (box && box.checked) channels.push(pair[1]);
      });
      if (!listingId) {
        window.eccNotify('Select an event in Step 2 before saving this campaign.');
        this.goToWizStep(2);
        return;
      }
      if (!title.trim()) {
        window.eccNotify('Give the campaign a headline before saving.');
        this.goToWizStep(5);
        return;
      }
      if (!channels.length) {
        window.eccNotify('Select at least one distribution channel in Step 6.');
        this.goToWizStep(6);
        return;
      }
      var status = requestedStatus === 'draft' ? 'draft' : ((startDate && startDate > new Date().toISOString().slice(0, 10)) ? 'scheduled' : 'active');
      var submit = document.getElementById('mkt-wiz-next-btn');
      if (submit) submit.disabled = true;

      this.post({
        action: 'create_campaign',
        listing_id: listingId,
        title: title,
        objective: obj,
        target_audience: aud,
        offer_type: offerType,
        offer_val: offerVal,
        start_date: startDate,
        end_date: endDate,
        headline: title,
        body_text: bodyText,
        channel: channels[0],
        status: status
      }).then(function(body) {
        if (submit) submit.disabled = false;
        if (body && body.result && body.result.campaigns) {
          self.state.campaigns = body.result.campaigns;
          window.eccNotify(status === 'draft' ? 'Campaign draft saved.' : 'Campaign "' + title + '" saved.');
          window.closeEccModal('modal-mkt-campaign-wiz');
          self.switchTab('campaigns');
        } else {
          var message = body && body.error && body.error.message ? body.error.message : 'Could not save campaign.';
          window.eccNotify(message);
        }
      }).catch(function() {
        if (submit) submit.disabled = false;
        window.eccNotify('Could not reach the marketing service. Please retry.');
      });
    },

    openPromoModal: function() {
      var self = this;
      this.populateEventSelects();
      var id = document.getElementById('promo-modal-id');
      var heading = document.getElementById('promo-modal-heading');
      var submit = document.getElementById('promo-modal-submit');
      if (id) id.value = '';
      if (heading) heading.textContent = 'Create Promotion Offer';
      if (submit) submit.textContent = 'Publish Promotion';
      var title = document.getElementById('promo-modal-title');
      var discount = document.getElementById('promo-modal-val');
      var limit = document.getElementById('promo-modal-limit');
      var status = document.getElementById('promo-modal-status');
      if (title) title.value = '';
      if (discount) discount.value = '';
      if (limit) limit.value = '200';
      if (status) status.value = 'active';
      var until = document.getElementById('promo-modal-until');
      if (until) until.value = new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10);
      window.openEccModal('modal-mkt-promo');
      if (!this.state.events.length) {
        this.get('overview', { date_range: this.state.dateRange }).then(function(body) {
          if (body && body.result && Array.isArray(body.result.event_performance)) {
            self.state.events = body.result.event_performance;
            self.populateEventSelects();
          }
        });
      }
    },

    managePromotion: function(id) {
      var promotion = (this.state.promotions || []).find(function(item) { return item.id === id; });
      if (!promotion) { window.eccNotify('Promotion details are no longer available.'); return; }
      this.populateEventSelects();
      document.getElementById('promo-modal-id').value = promotion.id;
      document.getElementById('promo-modal-title').value = promotion.title || '';
      document.getElementById('promo-modal-val').value = promotion.discount || '';
      document.getElementById('promo-modal-limit').value = String(promotion.used || '').split('/').pop().trim() || '1';
      document.getElementById('promo-modal-event').value = promotion.listing_id || '';
      document.getElementById('promo-modal-until').value = promotion.valid_until || '';
      document.getElementById('promo-modal-status').value = String(promotion.status || 'active').toLowerCase() === 'active' ? 'active' : 'paused';
      document.getElementById('promo-modal-heading').textContent = 'Manage Promotion';
      document.getElementById('promo-modal-submit').textContent = 'Save Promotion';
      window.openEccModal('modal-mkt-promo');
    },

    togglePromotionStatus: function(id) {
      var self = this;
      this.post({ action: 'toggle_promotion_status', promotion_id: id }).then(function(body) {
        if (body && body.result && body.result.promotions) {
          self.state.promotions = body.result.promotions;
          self.renderPromotions();
          window.eccNotify('Promotion status updated.');
        } else {
          window.eccNotify((body && body.error && body.error.message) || 'Promotion status could not be changed.');
        }
      }).catch(function() { window.eccNotify('Promotion status request failed. Please retry.'); });
    },

    savePromotion: function() {
      var self = this;
      var id = (document.getElementById('promo-modal-id') || {}).value || '';
      var title = ((document.getElementById('promo-modal-title') || {}).value || '').trim();
      var val = ((document.getElementById('promo-modal-val') || {}).value || '').trim();
      var limit = (document.getElementById('promo-modal-limit') || {}).value || '';
      var listingId = (document.getElementById('promo-modal-event') || {}).value || '';
      var validUntil = (document.getElementById('promo-modal-until') || {}).value || '';
      var status = (document.getElementById('promo-modal-status') || {}).value || 'active';
      if (!listingId || !title || !val || !limit || !validUntil) {
        window.eccNotify('Choose an event and complete all promotion fields.');
        return;
      }
      var submit = document.getElementById('promo-modal-submit');
      if (submit) submit.disabled = true;

      this.post({
        action: id ? 'update_promotion' : 'create_promotion',
        promotion_id: id,
        listing_id: listingId,
        title: title,
        discount_text: val,
        usage_limit: limit,
        valid_until: validUntil,
        status: status
      }).then(function(body) {
        if (submit) submit.disabled = false;
        if (body && body.result && body.result.promotions) {
          self.state.promotions = body.result.promotions;
          window.eccNotify(id ? 'Promotion updated.' : 'Promotion published.');
          window.closeEccModal('modal-mkt-promo');
          self.renderPromotions();
        } else {
          window.eccNotify((body && body.error && body.error.message) || 'Promotion could not be saved.');
        }
      }).catch(function() {
        if (submit) submit.disabled = false;
        window.eccNotify('Promotion request failed. Please retry.');
      });
    },

    openPromoCodeModal: function() {
      window.openEccModal('modal-mkt-code');
    },

    savePromoCode: function() {
      var self = this;
      var str = (document.getElementById('code-modal-str') || {}).value || 'PROMO' + Math.floor(Math.random()*1000);
      var val = (document.getElementById('code-modal-val') || {}).value || '15% OFF';
      var cap = (document.getElementById('code-modal-cap') || {}).value || '100';

      this.post({
        action: 'create_promocode',
        code: str,
        discount_type: val,
        usage_cap: cap
      }).then(function(body) {
        if (body && body.result && body.result.promocodes) {
          self.state.promocodes = body.result.promocodes;
          window.eccNotify('Promo code ' + str.toUpperCase() + ' saved.');
          window.closeEccModal('modal-mkt-code');
          self.renderPromoCodes();
        } else {
          window.eccNotify((body && body.error && body.error.message) || 'Promo code could not be created.');
        }
      }).catch(function() {
        window.eccNotify('Promo code request failed. Please retry.');
      });
    },

    copyPromoCode: function(code) {
      var done = function() { window.eccNotify('Promo code copied to your clipboard.'); };
      var failed = function() { window.eccNotify('Copy was blocked by the browser. Select the code and copy it manually.'); };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(done).catch(failed);
        return;
      }
      var input = document.createElement('textarea');
      input.value = code;
      input.style.position = 'fixed';
      input.style.opacity = '0';
      document.body.appendChild(input);
      input.select();
      try { document.execCommand('copy') ? done() : failed(); } catch (error) { failed(); }
      document.body.removeChild(input);
    },

    investigateCampaign: function(cmpId) {
      var self = this;
      this.switchTab('campaigns');
      this.loadCampaigns();
      window.setTimeout(function() {
        var campaign = (self.state.campaigns || []).find(function(item) { return item.id === cmpId; }) || (self.state.campaigns || [])[0];
        if (campaign) {
          self.viewCampaign(campaign.id);
          return;
        }
        self.openCreateWizard();
        window.eccNotify('No saved campaign is available to investigate. Create one from verified event data.');
      }, 200);
    },

    /* ── Cross-Module Integration API ─────────────────────────── */
    promoteEvent: function(eventId) {
      window.switchEccModule('marketing');
      this.switchTab('campaigns');
      this.openCreateWizard();
      var sel = document.getElementById('mkt-wiz-event-select');
      if (sel && eventId) {
        sel.value = eventId;
        this.wizOnEventSelect(eventId);
      }
    },

    promoteTicket: function(eventId, ticketType) {
      window.switchEccModule('marketing');
      this.switchTab('promotions');
      this.openPromoModal();
      var valInput = document.getElementById('promo-modal-val');
      if (valInput) valInput.value = ticketType + ' Discount';
      var eventSelect = document.getElementById('promo-modal-event');
      if (eventSelect && eventId) eventSelect.value = eventId;
    }
  };

  MarketingControlCenter.init();

  /* ── Customer Relationship Management (CRM) Controller ────── */
  window.CustomersControlCenter = {
    state: {
      activeTab: 'overview',
      searchQuery: '',
      segmentFilter: 'all',
      activityFilter: 'all',
      selectedCustomerId: null,
      profileTab: 'overview',
      data: null,
      profile: null
    },

    get: function(action, params) {
      var doc = document.getElementById('events-workspace');
      var base = (doc && doc.dataset.baseUrl) ? doc.dataset.baseUrl : '';
      var url = base + 'api/tie/vendor/events/customers.php?action=' + encodeURIComponent(action);
      Object.keys(params || {}).forEach(function(k) {
        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
      });
      return fetch(url, { credentials: 'same-origin' }).then(function(r) {
        return r.json().catch(function() { return {}; });
      });
    },

    post: function(data) {
      var doc = document.getElementById('events-workspace');
      var base = (doc && doc.dataset.baseUrl) ? doc.dataset.baseUrl : '';
      var csrf = (doc && doc.dataset.csrf) ? doc.dataset.csrf : '';
      return fetch(base + 'api/tie/vendor/events/customers.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify(data)
      }).then(function(r) {
        return r.json().catch(function() { return {}; });
      });
    },

    init: function() {
      this.loadOverview();
    },

    switchTab: function(tabId, btn) {
      this.state.activeTab = tabId;
      var buttons = document.querySelectorAll('#cus-sub-nav .cus-tab-btn');
      buttons.forEach(function(b) { b.classList.remove('active'); });
      if (btn) btn.classList.add('active');

      var subviews = document.querySelectorAll('#cus-views-container .cus-subview');
      subviews.forEach(function(v) { v.style.display = 'none'; });

      var target = document.getElementById('cus-view-' + tabId);
      if (target) target.style.display = 'block';

      if (tabId === 'overview') this.loadOverview();
      if (tabId === 'directory') this.loadDirectory();
      if (tabId === 'segments') this.loadSegments();
      if (tabId === 'vip') this.loadVip();
      if (tabId === 'at_risk') this.loadAtRisk();
    },

    loadOverview: function() {
      var self = this;
      this.get('overview').then(function(body) {
        if (!body || !body.result) return;
        var res = body.result;
        var kpis = res.kpis || {};
        
        var elTot = document.getElementById('cus-kpi-total');
        if (elTot) elTot.textContent = (kpis.total_customers || 0).toLocaleString();
        var elAct = document.getElementById('cus-kpi-active');
        if (elAct) elAct.textContent = (kpis.active_customers || 0).toLocaleString();
        var elNew = document.getElementById('cus-kpi-new');
        if (elNew) elNew.textContent = (kpis.new_customers || 0).toLocaleString();
        var elRet = document.getElementById('cus-kpi-returning');
        if (elRet) elRet.textContent = (kpis.returning_customers || 0).toLocaleString();
        var elRetRate = document.getElementById('cus-kpi-retention');
        if (elRetRate) elRetRate.textContent = kpis.retention_rate || '21.1%';

        self.loadOverviewTable();
      });
    },

    loadOverviewTable: function() {
      var self = this;
      this.get('directory').then(function(body) {
        if (!body || !body.result) return;
        var list = (body.result.customers || []).slice(0, 5);
        var bodyEl = document.getElementById('cus-overview-table-body');
        if (!bodyEl) return;

        var html = '';
        list.forEach(function(c) {
          html += '<tr>' +
            '<td><div style="display:flex;align-items:center;gap:0.6rem;"><div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;font-weight:900;font-size:0.75rem;display:flex;align-items:center;justify-content:center;">' + window.tkEsc(c.name.charAt(0)) + '</div><div><strong>' + window.tkEsc(c.name) + '</strong><div style="font-size:0.65rem;color:var(--ecc-text-dim);">' + window.tkEsc(c.email) + '</div></div></div></td>' +
            '<td style="font-size:0.7rem;font-weight:700;">' + window.tkEsc(c.customer_id) + '</td>' +
            '<td style="text-align:center;font-size:0.74rem;">' + c.events_count + '</td>' +
            '<td style="text-align:center;font-size:0.74rem;">' + c.orders_count + '</td>' +
            '<td style="font-weight:800;color:var(--ecc-primary);font-size:0.76rem;">' + window.tkMoney(c.total_spent, true) + '</td>' +
            '<td style="font-size:0.7rem;color:var(--ecc-text-dim);">' + window.tkEsc(c.last_activity) + '</td>' +
            '<td><span class="ecc-pill ' + (c.status === 'Active' ? 'green' : 'amber') + '" style="font-size:0.6rem;">● ' + window.tkEsc(c.status.toUpperCase()) + '</span></td>' +
            '<td><button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.65rem;padding:0.2rem 0.5rem;" onclick="CustomersControlCenter.openProfile(\'' + window.tkEsc(c.id) + '\')">View Profile →</button></td>' +
          '</tr>';
        });
        bodyEl.innerHTML = html;
      });
    },

    loadDirectory: function() {
      var self = this;
      var q = (document.getElementById('cus-search-input') || {}).value || '';
      var seg = (document.getElementById('cus-filter-segment') || {}).value || 'all';
      var act = (document.getElementById('cus-filter-activity') || {}).value || 'all';

      this.get('directory', { q: q, segment: seg, activity: act }).then(function(body) {
        if (!body || !body.result) return;
        var list = body.result.customers || [];
        var bodyEl = document.getElementById('cus-directory-table-body');
        if (!bodyEl) return;

        if (list.length === 0) {
          bodyEl.innerHTML = '<tr><td colspan="8"><div class="ecc-tk-empty">No customers found matching search filters.</div></td></tr>';
          return;
        }

        var html = '';
        list.forEach(function(c) {
          var tagsHtml = '';
          (c.tags || []).forEach(function(t) {
            tagsHtml += '<span class="ecc-chip" style="font-size:0.58rem;padding:0.1rem 0.35rem;">' + window.tkEsc(t) + '</span> ';
          });

          html += '<tr>' +
            '<td><div style="display:flex;align-items:center;gap:0.65rem;"><div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#a855f7);color:#fff;font-weight:900;font-size:0.8rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' + window.tkEsc(c.name.charAt(0)) + '</div><div><strong style="font-size:0.8rem;">' + window.tkEsc(c.name) + '</strong><div style="font-size:0.66rem;color:var(--ecc-text-dim);">' + window.tkEsc(c.email) + ' · ' + window.tkEsc(c.phone) + '</div></div></div></td>' +
            '<td style="font-size:0.72rem;font-weight:800;color:var(--ecc-text);">' + window.tkEsc(c.customer_id) + '</td>' +
            '<td style="text-align:center;font-size:0.75rem;font-weight:700;">' + c.events_count + '</td>' +
            '<td style="text-align:center;font-size:0.75rem;font-weight:700;">' + c.orders_count + '</td>' +
            '<td style="font-weight:900;color:var(--ecc-primary);font-size:0.78rem;">' + window.tkMoney(c.total_spent, true) + '</td>' +
            '<td style="font-size:0.7rem;color:var(--ecc-text-dim);">' + window.tkEsc(c.last_activity) + '</td>' +
            '<td><div style="display:flex;flex-direction:column;gap:0.25rem;"><span class="ecc-pill ' + (c.status === 'Active' ? 'green' : (c.status === 'At Risk' ? 'rose' : 'amber')) + '" style="font-size:0.58rem;align-self:flex-start;">● ' + window.tkEsc(c.status.toUpperCase()) + '</span><div>' + tagsHtml + '</div></div></td>' +
            '<td style="text-align:right;"><div style="display:flex;gap:0.3rem;justify-content:flex-end;"><button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.65rem;padding:0.25rem 0.55rem;" onclick="CustomersControlCenter.openProfile(\'' + window.tkEsc(c.id) + '\')">View Profile →</button><button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.65rem;padding:0.25rem 0.45rem;" onclick="CustomersControlCenter.openMessageModal(\'' + window.tkEsc(c.name) + '\')"><i class="fas fa-envelope"></i></button></div></td>' +
          '</tr>';
        });
        bodyEl.innerHTML = html;
      });
    },

    loadSegments: function() {
      var self = this;
      this.get('segments').then(function(body) {
        if (!body || !body.result) return;
        var list = body.result.segments || [];
        var grid = document.getElementById('cus-segments-grid');
        if (!grid) return;

        var html = '';
        list.forEach(function(s) {
          html += '<div class="cus-segment-card">' +
            '<div>' +
              '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem;">' +
                '<span class="ecc-pill blue" style="font-size:0.6rem;">' + window.tkEsc(s.badge || 'Segment') + '</span>' +
                '<strong style="font-size:1.1rem;font-weight:900;color:var(--ecc-primary);">' + s.customer_count + '</strong>' +
              '</div>' +
              '<h4 style="margin:0 0 0.3rem 0;font-size:0.95rem;font-weight:900;color:var(--ecc-text-bright);">' + window.tkEsc(s.title) + '</h4>' +
              '<p style="font-size:0.72rem;color:var(--ecc-text-dim);margin:0 0 1rem 0;">' + window.tkEsc(s.description) + '</p>' +
            '</div>' +
            '<div style="display:flex;gap:0.4rem;border-top:1px solid var(--ecc-border);padding-top:0.6rem;">' +
              '<button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.68rem;" onclick="CustomersControlCenter.switchTab(\'directory\');">View Customers</button>' +
              '<button type="button" class="ecc-btn ecc-btn-primary" data-mkt-action="campaign-create" data-mkt-preset="segment" style="flex:1;font-size:0.68rem;" onclick="MarketingControlCenter.openCreateWizard(\'segment\');">Campaign →</button>' +
            '</div>' +
          '</div>';
        });
        grid.innerHTML = html;
      });
    },

    loadVip: function() {
      var self = this;
      this.get('directory', { segment: 'VIP' }).then(function(body) {
        if (!body || !body.result) return;
        var list = body.result.customers || [];
        var bodyEl = document.getElementById('cus-vip-table-body');
        if (!bodyEl) return;

        var html = '';
        list.forEach(function(c) {
          html += '<tr>' +
            '<td><div style="display:flex;align-items:center;gap:0.6rem;"><div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#eab308);color:#fff;font-weight:900;font-size:0.8rem;display:flex;align-items:center;justify-content:center;"><i class="fas fa-crown"></i></div><div><strong>' + window.tkEsc(c.name) + '</strong><div style="font-size:0.65rem;color:var(--ecc-text-dim);">' + window.tkEsc(c.email) + '</div></div></div></td>' +
            '<td style="font-size:0.72rem;font-weight:800;">' + window.tkEsc(c.customer_id) + '</td>' +
            '<td style="font-weight:800;font-size:0.76rem;">' + c.events_count + ' events</td>' +
            '<td style="font-weight:900;color:#10b981;font-size:0.8rem;">' + window.tkMoney(c.total_spent, true) + '</td>' +
            '<td style="font-size:0.7rem;color:var(--ecc-text-dim);">' + window.tkEsc(c.last_activity) + '</td>' +
            '<td><button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.65rem;padding:0.25rem 0.55rem;" onclick="CustomersControlCenter.openProfile(\'' + window.tkEsc(c.id) + '\')">View Profile →</button></td>' +
          '</tr>';
        });
        bodyEl.innerHTML = html;
      });
    },

    loadAtRisk: function() {
      var self = this;
      this.get('at_risk').then(function(body) {
        if (!body || !body.result) return;
        var list = body.result.at_risk || [];
        var container = document.getElementById('cus-atrisk-container');
        if (!container) return;

        var html = '';
        list.forEach(function(c) {
          html += '<div class="cus-segment-card" style="border-left:4px solid #ef4444;">' +
            '<div>' +
              '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.4rem;">' +
                '<span class="ecc-pill rose" style="font-size:0.6rem;">● AT RISK</span>' +
                '<span style="font-size:0.68rem;color:var(--ecc-text-dim);">' + window.tkEsc(c.last_activity) + '</span>' +
              '</div>' +
              '<h4 style="margin:0 0 0.2rem 0;font-size:0.95rem;font-weight:900;">' + window.tkEsc(c.name) + '</h4>' +
              '<div style="font-size:0.72rem;color:var(--ecc-text-dim);margin-bottom:0.6rem;">' + window.tkEsc(c.customer_id) + ' · ' + window.tkMoney(c.total_spent, true) + ' spend</div>' +
              '<p style="font-size:0.72rem;background:var(--ecc-surface-2);padding:0.5rem 0.75rem;border-radius:8px;margin:0 0 1rem 0;color:var(--ecc-text);">' + window.tkEsc(c.reason) + '</p>' +
            '</div>' +
            '<div style="display:flex;gap:0.4rem;">' +
              '<button type="button" class="ecc-btn ecc-btn-secondary" style="flex:1;font-size:0.68rem;" onclick="CustomersControlCenter.openProfile(\'' + window.tkEsc(c.id) + '\')">View Profile</button>' +
              '<button type="button" class="ecc-btn ecc-btn-primary" style="flex:1;font-size:0.68rem;" onclick="CustomersControlCenter.openMessageModal(\'' + window.tkEsc(c.name) + '\')">Re-engage →</button>' +
            '</div>' +
          '</div>';
        });
        if (list.length === 0) {
          html = '<div class="ecc-tk-empty" style="grid-column:1/-1;text-align:center;padding:2.5rem 1rem;">' +
            '<div style="font-size:2rem;margin-bottom:0.5rem;">✅</div>' +
            '<strong style="font-size:0.9rem;color:var(--ecc-text-bright);">All customers are active!</strong>' +
            '<p style="font-size:0.75rem;color:var(--ecc-text-dim);margin:0.3rem 0 0 0;">No at-risk customers detected. Customers with 2+ purchases and no activity for 60–180 days will appear here.</p>' +
          '</div>';
        }
        container.innerHTML = html;
      });
    },

    openProfile: function(customerId) {
      var self = this;
      this.state.selectedCustomerId = customerId;
      document.getElementById('cus-views-container').style.display = 'none';
      document.getElementById('cus-profile-workspace').style.display = 'block';

      this.get('profile', { customer_id: customerId }).then(function(body) {
        if (!body || !body.result) return;
        var p = body.result;
        self.state.profile = p;
        var c = p.customer || {};
        var vm = p.value_metrics || {};

        document.getElementById('cus-prof-avatar').textContent = (c.name || 'C').charAt(0).toUpperCase();
        document.getElementById('cus-prof-name').textContent = c.name || 'Customer';
        document.getElementById('cus-prof-code').textContent = c.customer_id || 'UTH-CUS-008421';
        document.getElementById('cus-prof-email').textContent = c.email || '';
        document.getElementById('cus-prof-phone').textContent = c.phone || '';
        document.getElementById('cus-prof-spend').textContent = window.tkMoney(vm.lifetime_spend || 0, true);
        document.getElementById('cus-prof-avg').textContent = window.tkMoney(vm.average_order || 0, true);
        document.getElementById('cus-prof-orders').textContent = vm.total_orders || 0;
        document.getElementById('cus-prof-events').textContent = vm.events_attended || 0;
        document.getElementById('cus-prof-since').textContent = c.created_at || 'June 2026';
        document.getElementById('cus-prof-spend2').textContent = window.tkMoney(vm.lifetime_spend || 0, true);
        document.getElementById('cus-prof-orders2').textContent = vm.total_orders || 0;
        document.getElementById('cus-prof-events2').textContent = vm.events_attended || 0;

        var eng = p.engagement || {};
        document.getElementById('cus-prof-last-pur').textContent = eng.last_purchase || '—';
        document.getElementById('cus-prof-last-ev').textContent = eng.last_event || '—';
        document.getElementById('cus-prof-last-msg').textContent = eng.last_message || '—';

        // Render Purchases
        var purHtml = '';
        (p.purchases || []).forEach(function(pur) {
          purHtml += '<tr><td><strong>' + window.tkEsc(pur.order_id) + '</strong></td><td>' + window.tkEsc(pur.event_title) + '</td><td style="font-weight:900;color:var(--ecc-primary);">' + window.tkMoney(pur.amount, true) + '</td><td><span class="ecc-pill ' + (pur.status === 'Paid' ? 'green' : 'rose') + '" style="font-size:0.6rem;">' + window.tkEsc(pur.status) + '</span></td><td style="font-size:0.7rem;color:var(--ecc-text-dim);">' + window.tkEsc(pur.date) + '</td></tr>';
        });
        document.getElementById('cus-prof-purchases-body').innerHTML = purHtml || '<tr><td colspan="5"><div class="ecc-tk-empty">No purchases recorded.</div></td></tr>';

        // Render Event History
        var evHtml = '';
        (p.events || []).forEach(function(ev) {
          evHtml += '<tr><td><strong>' + window.tkEsc(ev.event_title) + '</strong></td><td style="font-size:0.72rem;color:var(--ecc-text-dim);">' + window.tkEsc(ev.date) + '</td><td><span class="ecc-chip">' + window.tkEsc(ev.ticket_type) + '</span></td><td><span class="ecc-pill ' + (ev.status === 'Attended' ? 'green' : 'amber') + '" style="font-size:0.6rem;">✓ ' + window.tkEsc(ev.status) + '</span></td><td style="font-size:0.7rem;color:var(--ecc-text-dim);">' + (ev.checkin_time || '—') + '</td></tr>';
        });
        document.getElementById('cus-prof-events-body').innerHTML = evHtml || '<tr><td colspan="5"><div class="ecc-tk-empty">No event history.</div></td></tr>';

        // Render Digital Tickets
        var tktHtml = '';
        (p.tickets || []).forEach(function(tkt) {
          tktHtml += '<div class="ecc-card" style="padding:1rem;">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.4rem;">' +
              '<span class="ecc-pill blue" style="font-size:0.6rem;">' + window.tkEsc(tkt.ticket_type) + '</span>' +
              '<span class="ecc-pill green" style="font-size:0.58rem;">✓ ' + window.tkEsc(tkt.status) + '</span>' +
            '</div>' +
            '<h4 style="margin:0 0 0.2rem 0;font-size:0.9rem;font-weight:900;">' + window.tkEsc(tkt.event_title) + '</h4>' +
            '<div style="font-size:0.7rem;color:var(--ecc-text-dim);margin-bottom:0.6rem;">Ticket: <strong>' + window.tkEsc(tkt.ticket_code) + '</strong></div>' +
            '<button type="button" class="ecc-btn ecc-btn-secondary" style="font-size:0.65rem;width:100%;" onclick="window.eccNotify(\'Viewing digital pass ' + window.tkEsc(tkt.ticket_code) + '\')">View Digital Pass</button>' +
          '</div>';
        });
        document.getElementById('cus-prof-tickets-grid').innerHTML = tktHtml || '<div class="ecc-tk-empty">No digital tickets issued.</div>';

        // Render Timeline
        var timeHtml = '';
        (p.timeline || []).forEach(function(tl) {
          timeHtml += '<div class="cus-timeline-item">' +
            '<div class="cus-timeline-dot"></div>' +
            '<strong style="font-size:0.78rem;display:block;">' + window.tkEsc(tl.action) + '</strong>' +
            '<span style="font-size:0.68rem;color:var(--ecc-text-dim);">' + window.tkEsc(tl.date) + '</span>' +
          '</div>';
        });
        document.getElementById('cus-prof-timeline-container').innerHTML = timeHtml;

        // Render Internal Notes
        self.renderNotes(p.notes || []);

        // Render Reviews
        var revHtml = '';
        (p.reviews || []).forEach(function(r) {
          revHtml += '<div class="ecc-card" style="padding:1rem;margin-bottom:0.8rem;">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.3rem;">' +
              '<span style="color:#fbbf24;font-size:0.9rem;">' + ('★'.repeat(r.rating || 5)) + '</span>' +
              '<span style="font-size:0.68rem;color:var(--ecc-text-dim);">' + window.tkEsc(r.date) + '</span>' +
            '</div>' +
            '<p style="font-size:0.78rem;margin:0 0 0.3rem 0;font-style:italic;">"' + window.tkEsc(r.comment) + '"</p>' +
            '<span style="font-size:0.68rem;color:var(--ecc-primary);font-weight:700;">' + window.tkEsc(r.event_title) + '</span>' +
          '</div>';
        });
        document.getElementById('cus-prof-reviews-list').innerHTML = revHtml || '<div class="ecc-tk-empty">No customer reviews submitted.</div>';
      });
    },

    closeProfile: function() {
      document.getElementById('cus-profile-workspace').style.display = 'none';
      document.getElementById('cus-views-container').style.display = 'block';
    },

    switchProfileTab: function(tabId, btn) {
      this.state.profileTab = tabId;
      var buttons = document.querySelectorAll('#cus-profile-workspace .cus-tab-btn');
      buttons.forEach(function(b) { b.classList.remove('active'); });
      if (btn) btn.classList.add('active');

      var pviews = document.querySelectorAll('#cus-prof-views-container .cus-pview');
      pviews.forEach(function(v) { v.style.display = 'none'; });

      var target = document.getElementById('cus-pview-' + tabId);
      if (target) target.style.display = 'block';
    },

    focusNotesTab: function() {
      var noteBtn = document.querySelectorAll('#cus-profile-workspace .cus-tab-btn')[5];
      if (noteBtn) this.switchProfileTab('notes', noteBtn);
    },

    renderNotes: function(notes) {
      var html = '';
      (notes || []).forEach(function(n) {
        html += '<div class="cus-note-card">' +
          '<div style="display:flex;justify-content:space-between;align-items:center;">' +
            '<span class="cus-note-badge"><i class="fas fa-lock"></i> INTERNAL ONLY</span>' +
            '<span style="font-size:0.68rem;color:var(--ecc-text-dim);">' + window.tkEsc(n.created_at) + '</span>' +
          '</div>' +
          '<p style="font-size:0.78rem;margin:0.3rem 0;color:var(--ecc-text-bright);">' + window.tkEsc(n.note) + '</p>' +
          '<div style="font-size:0.65rem;color:var(--ecc-text-dim);">Added by: <strong>' + window.tkEsc(n.author_name) + '</strong></div>' +
        '</div>';
      });
      document.getElementById('cus-prof-notes-list').innerHTML = html || '<div class="ecc-tk-empty">No internal notes added yet.</div>';
    },

    submitNote: function() {
      var self = this;
      var textEl = document.getElementById('cus-new-note-text');
      var note = (textEl || {}).value || '';
      if (!note.trim()) { window.eccNotify('Please enter note text first.'); return; }

      this.post({ action: 'add_note', customer_id: this.state.selectedCustomerId, note: note }).then(function(body) {
        if (textEl) textEl.value = '';
        window.eccNotify('Internal note saved!');
        if (body && body.result && body.result.notes) {
          self.renderNotes(body.result.notes);
        } else {
          self.openProfile(self.state.selectedCustomerId);
        }
      });
    },

    openSegmentBuilderModal: function() {
      window.openEccModal('modal-cus-segment-builder');
    },

    saveSegmentBuilder: function() {
      var title = (document.getElementById('seg-builder-title') || {}).value || 'Custom Segment';
      this.post({ action: 'create_segment', title: title }).then(function() {
        window.closeEccModal('modal-cus-segment-builder');
        window.eccNotify('New customer segment created!');
        window.CustomersControlCenter.loadSegments();
      });
    },

    openAddCustomerModal: function() {
      window.openEccModal('modal-cus-add');
    },

    saveNewCustomer: function() {
      window.closeEccModal('modal-cus-add');
      window.eccNotify('Customer record created!');
      this.loadDirectory();
    },

    openMessageModal: function(name) {
      var titleEl = document.getElementById('cus-msg-title');
      if (titleEl) titleEl.textContent = 'Message ' + (name || 'Customer');
      window.openEccModal('modal-cus-message');
    },

    sendMessage: function() {
      window.closeEccModal('modal-cus-message');
      window.eccNotify('Message dispatched to customer!');
    },

    exportCustomers: function() {
      window.eccNotify('Exporting customer CRM database to CSV...');
    },

    switchChartMetric: function(metric) {
      var label = {
        'new_customers': 'New Customers',
        'returning_customers': 'Returning Customers',
        'purchasers': 'Purchasers',
        'attendees': 'Attendees'
      }[metric] || 'Customers';
      var el = document.getElementById('cus-chart-current-label');
      if (el) el.textContent = label;
    }
  };

  var origOnEccModuleShow = window.onEccModuleShow;
  window.onEccModuleShow = function(modId) {
    if (origOnEccModuleShow) { try { origOnEccModuleShow(modId); } catch(e) {} }
    if (modId === 'marketing' && window.MarketingControlCenter) {
      window.MarketingControlCenter.loadOverview();
      window.MarketingControlCenter.loadCampaigns();
    }
    if (modId === 'venues' && window.VenuesControlCenter) {
      window.VenuesControlCenter.loadWorkspace();
    }
    if (modId === 'customers' && window.CustomersControlCenter) {
      window.CustomersControlCenter.init();
    }
  };

  /* ── Tickets Wizard Controller ─────────────────────────────── */
  window.generateQrSvg = function(codeStr) {
    var code = String(codeStr || 'UTH-VIP-004821').trim();
    if (typeof qrcode !== 'undefined') {
      try {
        var qr = qrcode(0, 'M');
        qr.addData(code);
        qr.make();
        return qr.createSvgTag(3, 0);
      } catch (e) {}
    }
    return '<div class="qr-placeholder" style="width:100%;height:100%;"></div>';
  };

  /* ── 10 Ticket Design Templates Renderer ─────────────────────── */
  window.renderTicketTemplate = function(catKey, data) {
    data = data || {};
    var title = data.title || 'MALAWI BUSINESS SUMMIT 2026';
    var sub = data.subtitle || 'Connect. Innovate. Grow.';
    var catName = data.category || 'VIP PASS';
    var date = data.date || '18 AUG 2026';
    var time = data.time || '08:00 AM - 06:00 PM';
    var venue = data.venue || 'SUNBIRD CAPITAL HOTEL, LILONGWE, MALAWI';
    var city = data.city || '';
    var ticketId = data.ticketId || 'UTH-VIP-004821';
    var row = data.row || 'A';
    var seat = data.seat || '01';
    var admit = data.admit || 'ADMIT ONE';
    var validFrom = data.validFrom || '01 JAN 2026';
    var validTo = data.validTo || '31 DEC 2026';
    var cityLine = city ? '<div style="padding-left:20px;"><span>' + tkEsc(city) + '</span></div>' : '';

    var cat = String(catKey || catName || '').toUpperCase().replace(/[^A-Z0-9]/g, '_');

    var tplClass = 'uthenga-tpl-vip';
    if (cat.indexOf('VVIP') !== -1) tplClass = 'uthenga-tpl-vvip';
    else if (cat.indexOf('EARLY') !== -1) tplClass = 'uthenga-tpl-earlybird';
    else if (cat.indexOf('STUDENT') !== -1) tplClass = 'uthenga-tpl-student';
    else if (cat.indexOf('COMPLIMENT') !== -1) tplClass = 'uthenga-tpl-complimentary';
    else if (cat.indexOf('GROUP') !== -1) tplClass = 'uthenga-tpl-group';
    else if (cat.indexOf('EXHIBIT') !== -1) tplClass = 'uthenga-tpl-exhibitor';
    else if (cat.indexOf('SEASON') !== -1) tplClass = 'uthenga-tpl-season';
    else if (cat.indexOf('DAY') !== -1) tplClass = 'uthenga-tpl-daypass';
    else if (cat.indexOf('GENERAL') !== -1 || cat.indexOf('STANDARD') !== -1) tplClass = 'uthenga-tpl-general';

    var qrMarkup = window.generateQrSvg(ticketId);

    if (tplClass === 'uthenga-tpl-vip') {
      return '<div class="ew-tk-ivp">' + '<article class="ticket vip" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:linear-gradient(120deg,rgba(234,179,8,0.12) 0%,transparent 40%),linear-gradient(300deg,rgba(234,179,8,0.15) 0%,transparent 35%),linear-gradient(160deg,#09061c,#150f38 60%,#0a0720);color:#fff;padding:1rem;flex:1;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#eab308;"></span><small style="color:#eab308;font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '    <div style="margin-left:auto;background:linear-gradient(135deg, #facc15 0%, #ca8a04 100%);color:#110c2a;font-weight:900;font-size:0.65rem;padding:3px 10px;border-radius:6px;display:flex;align-items:center;gap:4px;box-shadow:0 2px 8px rgba(234,179,8,0.3);">' +
        '      <i class="fas fa-crown" style="color:#110c2a;font-size:0.7rem;"></i> <span>VIP PASS</span>' +
        '    </div>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.35rem;font-weight:900;line-height:1.15;margin:0;color:#ffffff;text-transform:uppercase;">' + tkEsc(title) + '</h2>' +
        '  <p class="t-sub" style="font-size:0.75rem;font-weight:700;color:#f3f4f6;margin:0.2rem 0 0.4rem 0;">' + tkEsc(sub) + '</p>' +
        '  <ul class="meta" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#cbd5e1;display:flex;flex-direction:column;gap:0.2rem;">' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#eab308;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#eab308;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
        '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#eab308;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
        '  </ul>' +
        '  <div class="perks" style="display:flex;gap:0.5rem;margin-top:0.4rem;font-size:0.55rem;color:#eab308;border-top:1px solid rgba(234,179,8,0.3);padding-top:0.3rem;">' +
        '    <span style="display:flex;align-items:center;gap:0.25rem;"><i class="fas fa-couch"></i> VIP Lounge</span>' +
        '    <span style="display:flex;align-items:center;gap:0.25rem;"><i class="fas fa-chair"></i> Front Row</span>' +
        '    <span style="display:flex;align-items:center;gap:0.25rem;"><i class="fas fa-network-wired"></i> Networking</span>' +
        '    <span style="display:flex;align-items:center;gap:0.25rem;"><i class="fas fa-glass-cheers"></i> Welcome Drink</span>' +
        '  </div>' +
        '</div>' +
        '<div class="t-stub" style="width:110px;background:#1b0e45;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(234,179,8,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div>' +
        '    <h3 class="stub-title" style="font-size:0.65rem;font-weight:800;color:#facc15;margin:0 0 0.2rem 0;">VIP PASS</h3>' +
        '    <p class="tid" style="font-size:0.5rem;text-transform:uppercase;color:#cbd5e1;margin:0;">Ticket ID</p>' +
        '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.4rem 0;">' + tkEsc(ticketId) + '</p>' +
        '    <div class="rowseat" style="display:flex;justify-content:space-between;font-size:0.55rem;margin-bottom:0.4rem;padding:0 2px;">' +
        '      <span>ROW<b style="display:block;font-size:0.7rem;font-weight:800;">' + tkEsc(row) + '</b></span>' +
        '      <span>SEAT<b style="display:block;font-size:0.7rem;font-weight:800;">' + tkEsc(seat) + '</b></span>' +
        '    </div>' +
        '  </div>' +
        '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#fff;padding:4px;border-radius:4px;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;color:#facc15;margin-top:0.4rem;">' + tkEsc(admit) + '</div>' +
        '</div>' +
        '</article></div>';
    }

    if (tplClass === 'uthenga-tpl-earlybird') {
      return '<div class="ew-tk-ivp">' + '<article class="ticket eb" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:#fff;color:#1f2937;padding:1rem;flex:1;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:#0b3846;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#0b3846;"></span><small style="font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.4rem;font-weight:900;color:#15803d;margin:0;text-transform:uppercase;">EARLY BIRD</h2>' +
        '  <h3 style="font-size:0.8rem;font-weight:700;color:#111827;margin:0.25rem 0;text-transform:uppercase;">' + tkEsc(title) + '</h3>' +
        '  <p class="tagline" style="font-size:0.7rem;color:#16a34a;margin-bottom:0.75rem;font-style:italic;font-weight:600;">' + tkEsc(sub) + '</p>' +
        '  <ul class="meta" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#4b5563;font-weight:500;display:flex;flex-direction:column;gap:0.25rem;max-width:65%;">' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#16a34a;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#16a34a;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
        '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#16a34a;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
        '  </ul>' +
        '  <div class="early-bird-bg" style="position:absolute;top:0;bottom:0;right:0;width:50%;background:linear-gradient(135deg, #11998e 0%, #38ef7d 100%);clip-path:polygon(0 0, 100% 0, 100% 100%, 20% 100%);opacity:0.9;z-index:1;pointer-events:none;"></div>' +
        '  <div class="eb-disc-badge" style="position:absolute;top:50%;right:110px;transform:translateY(-50%);width:56px;height:56px;background:#fff;border-radius:50%;box-shadow:0 4px 12px rgba(0,0,0,0.15);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;z-index:2;border:2px solid #22c55e;">' +
        '    <span style="font-size:7px;font-weight:700;color:#14532d;text-transform:uppercase;">Discount</span>' +
        '    <span style="font-size:13px;font-weight:900;color:#16a34a;line-height:1;">30%</span>' +
        '    <span style="font-size:7px;font-weight:700;color:#14532d;text-transform:uppercase;">Off</span>' +
        '  </div>' +
        '</div>' +
        '<div class="t-stub" style="width:110px;background:#fff;color:#1f2937;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(0,0,0,0.15);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div>' +
        '    <h3 style="font-size:0.65rem;font-weight:800;color:#15803d;margin:0 0 0.4rem 0;">EARLY BIRD</h3>' +
        '    <p style="font-size:0.5rem;text-transform:uppercase;color:#6b7280;margin:0;">Ticket ID</p>' +
        '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.5rem 0;">' + tkEsc(ticketId) + '</p>' +
        '  </div>' +
        '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#f3f4f6;padding:4px;border-radius:4px;border:1px solid #e5e7eb;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;color:#1f2937;margin-top:0.4rem;">' + tkEsc(admit) + '</div>' +
        '</div>' +
        '</article></div>';
    }

    if (tplClass === 'uthenga-tpl-general') {
      return '<div class="ew-tk-ivp">' + '<article class="ticket ga" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:#fff;color:#1f2937;padding:1rem;flex:1;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:#0b3846;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#0b3846;"></span><small style="font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.4rem;font-weight:900;color:#0052D4;margin:0;line-height:1.1;text-transform:uppercase;">GENERAL<br>ADMISSION</h2>' +
        '  <h3 style="font-size:0.75rem;font-weight:700;color:#111827;margin:0.4rem 0 0.75rem 0;text-transform:uppercase;">' + tkEsc(title) + '</h3>' +
        '  <ul class="meta" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#4b5563;font-weight:500;display:flex;flex-direction:column;gap:0.25rem;max-width:65%;">' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#0052D4;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#0052D4;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
        '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#0052D4;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
        '  </ul>' +
        '  <div class="ga-bg" style="position:absolute;top:0;bottom:0;right:0;width:50%;background:linear-gradient(135deg, #0052D4 0%, #4364F7 50%, #6FB1FC 100%);clip-path:polygon(0 0, 100% 0, 100% 100%, 30% 100%);opacity:0.9;z-index:1;pointer-events:none;"></div>' +
        '</div>' +
        '<div class="t-stub" style="width:110px;background:#0052D4;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(255,255,255,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div>' +
        '    <h3 style="font-size:0.6rem;font-weight:800;margin:0 0 0.3rem 0;line-height:1.2;">GENERAL<br>ADMISSION</h3>' +
        '    <p style="font-size:0.5rem;text-transform:uppercase;opacity:0.8;margin:0;">Ticket ID</p>' +
        '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.5rem 0;">' + tkEsc(ticketId) + '</p>' +
        '  </div>' +
        '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#fff;padding:4px;border-radius:4px;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;margin-top:0.4rem;">' + tkEsc(admit) + '</div>' +
        '</div>' +
        '</article></div>';
    }

    if (tplClass === 'uthenga-tpl-group') {
      return '<div class="ew-tk-ivp">' + '<article class="ticket gp" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:#fff;color:#1f2937;padding:1rem;flex:1;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:#0b3846;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#0b3846;"></span><small style="font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.4rem;font-weight:900;color:#7F00FF;margin:0;text-transform:uppercase;">Group Pass</h2>' +
        '  <h3 style="font-size:0.75rem;font-weight:700;color:#111827;margin:0.3rem 0 0.6rem 0;text-transform:uppercase;">' + tkEsc(title) + '</h3>' +
        '  <ul class="meta" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#4b5563;font-weight:500;display:flex;flex-direction:column;gap:0.25rem;max-width:65%;">' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#7F00FF;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#7F00FF;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
        '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#7F00FF;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;margin-top:0.4rem;font-weight:800;color:#7F00FF;"><span class="mi"><i class="fas fa-users"></i></span> ADMIT 5 PEOPLE</li>' +
        '  </ul>' +
        '  <div class="group-bg" style="position:absolute;top:0;bottom:0;right:0;width:50%;background:linear-gradient(135deg, #7F00FF 0%, #E100FF 100%);clip-path:polygon(10% 0, 100% 0, 100% 100%, 0% 100%);opacity:0.9;z-index:1;pointer-events:none;"></div>' +
        '</div>' +
        '<div class="t-stub" style="width:110px;background:#7F00FF;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(255,255,255,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div>' +
        '    <h3 style="font-size:0.65rem;font-weight:800;margin:0 0 0.15rem 0;">GROUP PASS</h3>' +
        '    <p style="font-size:0.5rem;margin:0 0 0.3rem 0;opacity:0.9;">(5 PEOPLE)</p>' +
        '    <p style="font-size:0.5rem;text-transform:uppercase;opacity:0.8;margin:0;">Ticket ID</p>' +
        '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.5rem 0;">' + tkEsc(ticketId) + '</p>' +
        '  </div>' +
        '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#fff;padding:4px;border-radius:4px;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;margin-top:0.4rem;">ADMIT 5 PEOPLE</div>' +
        '</div>' +
        '</article></div>';
    }

    if (tplClass === 'uthenga-tpl-season') {
      return '<div class="ew-tk-ivp">' + '<article class="ticket sp" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:#fff;color:#1f2937;padding:1rem;flex:1;display:flex;flex-direction:column;justify-content:center;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:#0b3846;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#0b3846;"></span><small style="font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.25rem;font-weight:900;color:#c0392b;margin:0;text-transform:uppercase;">SEASON PASS 2026</h2>' +
        '  <h3 style="font-size:0.75rem;font-weight:700;color:#111827;margin:0.2rem 0 0.6rem 0;text-transform:uppercase;">' + tkEsc(title) + '</h3>' +
        '  <div class="checks" style="display:grid;grid-template-columns:1fr 1fr;gap:0.35rem;font-size:0.6rem;font-weight:700;color:#1f2937;">' +
        '    <span style="display:flex;align-items:center;gap:0.3rem;"><i class="fas fa-check" style="color:#c0392b;"></i> All Music Events</span>' +
        '    <span style="display:flex;align-items:center;gap:0.3rem;"><i class="fas fa-check" style="color:#c0392b;"></i> All Workshops</span>' +
        '    <span style="display:flex;align-items:center;gap:0.3rem;"><i class="fas fa-check" style="color:#c0392b;"></i> All Conferences</span>' +
        '    <span style="display:flex;align-items:center;gap:0.3rem;"><i class="fas fa-check" style="color:#c0392b;"></i> Priority Booking</span>' +
        '  </div>' +
        '</div>' +
        '<div class="mid" style="width:100px;background:#c0392b;color:#fff;padding:0.75rem;display:flex;flex-direction:column;justify-content:center;position:relative;border-left:2px dashed rgba(255,255,255,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <p style="font-size:0.5rem;opacity:0.8;margin:0 0 0.1rem 0;">VALID FROM</p>' +
        '  <p style="font-size:0.6rem;font-weight:800;margin:0 0 0.25rem 0;">' + tkEsc(validFrom) + '</p>' +
        '  <p style="font-size:0.5rem;opacity:0.8;margin:0 0 0.1rem 0;">TO</p>' +
        '  <p style="font-size:0.6rem;font-weight:800;margin:0 0 0.25rem 0;">' + tkEsc(validTo) + '</p>' +
        '  <p style="font-size:0.5rem;opacity:0.8;margin:0;">TICKET ID</p>' +
        '  <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0;">' + tkEsc(ticketId) + '</p>' +
        '</div>' +
        '<div class="t-stub" style="width:100px;background:#922b21;color:#fff;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(255,255,255,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div class="qr" style="width:64px;height:64px;background:#fff;padding:4px;border-radius:4px;margin-bottom:0.4rem;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;">' + tkEsc(admit) + '</div>' +
        '</div>' +
        '</article></div>';
    }

    if (tplClass === 'uthenga-tpl-vvip') {
      return '<div class="ew-tk-ivp">' + '<article class="ticket vvip" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:linear-gradient(135deg,#17130a 0%,#0d0b06 60%,#151007 100%);color:#fff;padding:1rem;flex:1;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#e8b64c;"></span><small style="color:#e8b64c;font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.4rem;font-weight:900;color:#e8b64c;margin:0;text-transform:uppercase;">VVIP EXPERIENCE</h2>' +
        '  <p class="t-sub" style="font-size:0.75rem;font-weight:700;color:#f3f4f6;margin:0.2rem 0 0.6rem 0;">' + tkEsc(title) + '</p>' +
        '  <ul class="meta" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#e5e7eb;display:flex;flex-direction:column;gap:0.25rem;">' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#e8b64c;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#e8b64c;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
        '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#e8b64c;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
        '  </ul>' +
        '  <div class="perks" style="display:flex;gap:0.6rem;margin-top:0.6rem;font-size:0.55rem;color:#e8c877;border-top:1px solid rgba(232,182,76,0.3);padding-top:0.4rem;">' +
        '    <span style="display:flex;align-items:center;gap:0.25rem;"><i class="fas fa-microphone"></i> Backstage Access</span>' +
        '    <span style="display:flex;align-items:center;gap:0.25rem;"><i class="fas fa-car"></i> VIP Parking</span>' +
        '    <span style="display:flex;align-items:center;gap:0.25rem;"><i class="fas fa-utensils"></i> Gourmet Dinner</span>' +
        '    <span style="display:flex;align-items:center;gap:0.25rem;"><i class="fas fa-handshake"></i> Meet &amp; Greet</span>' +
        '  </div>' +
        '</div>' +
        '<div class="t-stub" style="width:110px;background:#0f0d08;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(232,182,76,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div>' +
        '    <h3 class="stub-title" style="font-size:0.65rem;font-weight:800;color:#e8b64c;margin:0 0 0.2rem 0;">VVIP PASS</h3>' +
        '    <p class="tid" style="font-size:0.5rem;text-transform:uppercase;color:#9ca3af;margin:0;">Ticket ID</p>' +
        '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.4rem 0;">' + tkEsc(ticketId) + '</p>' +
        '    <div class="rowseat" style="display:flex;justify-content:space-between;font-size:0.55rem;margin-bottom:0.4rem;padding:0 2px;">' +
        '      <span>ROW<b style="display:block;font-size:0.7rem;font-weight:800;">AA</b></span>' +
        '      <span>SEAT<b style="display:block;font-size:0.7rem;font-weight:800;">01</b></span>' +
        '    </div>' +
        '  </div>' +
        '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#fff;padding:4px;border-radius:4px;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;color:#e8b64c;margin-top:0.4rem;">' + tkEsc(admit) + '</div>' +
        '</div>' +
        '</article></div>';
    }

    if (tplClass === 'uthenga-tpl-student') {
      return '<div class="ew-tk-ivp">' + '<article class="ticket st" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:#fff;color:#1f2937;padding:1rem;flex:1;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:#0d9488;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#0d9488;"></span><small style="font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.4rem;font-weight:900;color:#0d9488;margin:0;text-transform:uppercase;">STUDENT PASS</h2>' +
        '  <p class="t-sub" style="font-size:0.75rem;font-weight:700;color:#111827;margin:0.2rem 0 0.6rem 0;">' + tkEsc(title) + '</p>' +
        '  <ul class="meta" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#4b5563;display:flex;flex-direction:column;gap:0.25rem;">' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#0d9488;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#0d9488;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
        '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#0d9488;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;margin-top:0.4rem;font-weight:800;color:#0d9488;"><span class="mi"><i class="fas fa-graduation-cap"></i></span> VALID STUDENT ID REQUIRED</li>' +
        '  </ul>' +
        '</div>' +
        '<div class="t-stub" style="width:110px;background:#0d9488;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(255,255,255,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div>' +
        '    <h3 style="font-size:0.65rem;font-weight:800;margin:0 0 0.2rem 0;">STUDENT PASS</h3>' +
        '    <p style="font-size:0.5rem;text-transform:uppercase;opacity:0.8;margin:0;">Ticket ID</p>' +
        '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.4rem 0;">' + tkEsc(ticketId) + '</p>' +
        '  </div>' +
        '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#fff;padding:4px;border-radius:4px;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;margin-top:0.4rem;">' + tkEsc(admit) + '</div>' +
        '</div>' +
        '</article></div>';
    }

    if (tplClass === 'uthenga-tpl-complimentary') {
      return '<div class="ew-tk-ivp">' + '<article class="ticket cp" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:#fff;color:#1f2937;padding:1rem;flex:1;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:#10b981;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#10b981;"></span><small style="font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.3rem;font-weight:900;color:#10b981;margin:0;line-height:1.1;text-transform:uppercase;">COMPLIMENTARY<br>PASS</h2>' +
        '  <p class="t-sub" style="font-size:0.75rem;font-weight:700;color:#111827;margin:0.2rem 0 0.6rem 0;">' + tkEsc(title) + '</p>' +
        '  <ul class="meta" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#4b5563;display:flex;flex-direction:column;gap:0.25rem;">' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#10b981;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#10b981;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
        '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#10b981;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
        '  </ul>' +
        '</div>' +
        '<div class="t-stub" style="width:110px;background:#10b981;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(255,255,255,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div>' +
        '    <h3 style="font-size:0.6rem;font-weight:800;margin:0 0 0.2rem 0;">COMPLIMENTARY</h3>' +
        '    <p style="font-size:0.5rem;text-transform:uppercase;opacity:0.8;margin:0;">Ticket ID</p>' +
        '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.4rem 0;">' + tkEsc(ticketId) + '</p>' +
        '  </div>' +
        '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#fff;padding:4px;border-radius:4px;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;margin-top:0.4rem;">' + tkEsc(admit) + '</div>' +
        '</div>' +
        '</article></div>';
    }

    if (tplClass === 'uthenga-tpl-exhibitor') {
      return '<div class="ew-tk-ivp">' + '<article class="ticket ex" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:#fff;color:#1f2937;padding:1rem;flex:1;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:#f59e0b;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#f59e0b;"></span><small style="color:#f59e0b;font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.4rem;font-weight:900;color:#f59e0b;margin:0;text-transform:uppercase;">EXHIBITOR PASS</h2>' +
        '  <p class="t-sub" style="font-size:0.75rem;font-weight:700;color:#111827;margin:0.2rem 0 0.6rem 0;">' + tkEsc(title) + '</p>' +
        '  <ul class="meta one-col" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#4b5563;display:flex;flex-direction:column;gap:0.25rem;">' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#f59e0b;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#f59e0b;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
        '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#f59e0b;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;margin-top:0.4rem;font-weight:800;color:#f59e0b;"><span class="mi"><i class="fas fa-tag"></i></span> ACCESS TO EXHIBITION AREA ONLY</li>' +
        '  </ul>' +
        '</div>' +
        '<div class="t-stub" style="width:110px;background:#f59e0b;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(255,255,255,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div>' +
        '    <h3 style="font-size:0.65rem;font-weight:800;margin:0 0 0.2rem 0;">EXHIBITOR</h3>' +
        '    <p style="font-size:0.5rem;text-transform:uppercase;opacity:0.8;margin:0;">Ticket ID</p>' +
        '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.4rem 0;">' + tkEsc(ticketId) + '</p>' +
        '  </div>' +
        '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#fff;padding:4px;border-radius:4px;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;margin-top:0.4rem;">' + tkEsc(admit) + '</div>' +
        '</div>' +
        '</article></div>';
    }

    if (tplClass === 'uthenga-tpl-daypass') {
      var dayNum = data.day || data.dayNumber || (cat.match(/\d+/) ? cat.match(/\d+/)[0] : '1');
      return '<div class="ew-tk-ivp">' + '<article class="ticket dp" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
        '<div class="t-main" style="position:relative;overflow:hidden;background:#fff;color:#1f2937;padding:1rem 75px 1rem 1rem;flex:1;">' +
        '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:#f97316;">' +
        '    <span class="uthenga-tk-logo-img" style="color:#f97316;"></span><small style="color:#f97316;font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
        '  </div>' +
        '  <h2 class="t-title" style="font-size:1.4rem;font-weight:900;color:#f97316;margin:0;text-transform:uppercase;">DAY PASS</h2>' +
        '  <h3 style="font-size:0.75rem;font-weight:700;color:#111827;margin:0.2rem 0 0.6rem 0;text-transform:uppercase;word-break:break-word;">' + tkEsc(title) + '</h3>' +
        '  <ul class="meta" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#4b5563;display:flex;flex-direction:column;gap:0.25rem;">' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#f97316;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
        '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#f97316;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
        '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#f97316;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
        '  </ul>' +
        '  <div class="dayhex" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);z-index:2;width:54px;height:60px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(249,115,22,0.12);border:1.5px solid #f97316;clip-path:polygon(50% 0,100% 25%,100% 75%,50% 100%,0 75%,0 25%);color:#f97316;font-weight:900;"><small style="font-size:7.5px;letter-spacing:1px;">DAY</small><b style="font-size:18px;line-height:1;">' + tkEsc(dayNum) + '</b></div>' +
        '</div>' +
        '<div class="t-stub" style="width:110px;background:#f97316;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(255,255,255,0.4);">' +
        '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
        '  <div>' +
        '    <h3 style="font-size:0.65rem;font-weight:800;margin:0 0 0.2rem 0;">DAY PASS (' + tkEsc(dayNum) + ')</h3>' +
        '    <p style="font-size:0.5rem;text-transform:uppercase;opacity:0.8;margin:0;">Ticket ID</p>' +
        '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.4rem 0;">' + tkEsc(ticketId) + '</p>' +
        '  </div>' +
        '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#fff;padding:4px;border-radius:4px;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
        '  <div class="admit" style="font-size:0.55rem;font-weight:800;margin-top:0.4rem;">' + tkEsc(admit) + '</div>' +
        '</div>' +
        '</article></div>';
    }

    // Default General Admission
    return '<div class="ew-tk-ivp">' + '<article class="ticket ga" style="width:100%;max-width:620px;margin:0 auto;box-sizing:border-box;">' +
      '<div class="t-main" style="position:relative;overflow:hidden;background:#fff;color:#1f2937;padding:1rem;flex:1;">' +
      '  <div class="brand" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;color:#0b3846;">' +
      '    <span class="uthenga-tk-logo-img" style="color:#0b3846;"></span><small style="font-weight:800;font-size:0.65rem;letter-spacing:0.05em;margin-left:4px;">EVENTS</small>' +
      '  </div>' +
      '  <h2 class="t-title" style="font-size:1.4rem;font-weight:900;color:#0052D4;margin:0;line-height:1.1;text-transform:uppercase;">GENERAL<br>ADMISSION</h2>' +
      '  <h3 style="font-size:0.75rem;font-weight:700;color:#111827;margin:0.4rem 0 0.75rem 0;text-transform:uppercase;">' + tkEsc(title) + '</h3>' +
      '  <ul class="meta" style="list-style:none;padding:0;margin:0;font-size:0.65rem;color:#4b5563;font-weight:500;display:flex;flex-direction:column;gap:0.25rem;max-width:65%;">' +
      '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#0052D4;"><i class="far fa-calendar-alt"></i></span> ' + tkEsc(date) + '</li>' +
      '    <li style="display:flex;align-items:center;gap:0.4rem;"><span class="mi" style="color:#0052D4;"><i class="far fa-clock"></i></span> ' + tkEsc(time) + '</li>' +
      '    <li style="display:flex;align-items:flex-start;gap:0.4rem;"><span class="mi" style="color:#0052D4;margin-top:2px;"><i class="fas fa-map-marker-alt"></i></span> <span>' + tkEsc(venue) + (city ? '<br>' + tkEsc(city) : '') + '</span></li>' +
      '  </ul>' +
      '  <div class="ga-bg" style="position:absolute;top:0;bottom:0;right:0;width:50%;background:linear-gradient(135deg, #0052D4 0%, #4364F7 50%, #6FB1FC 100%);clip-path:polygon(0 0, 100% 0, 100% 100%, 30% 100%);opacity:0.9;z-index:1;pointer-events:none;"></div>' +
      '</div>' +
      '<div class="t-stub" style="width:110px;background:#0052D4;color:#fff;display:flex;flex-direction:column;justify-content:space-between;padding:0.75rem;text-align:center;position:relative;border-left:2px dashed rgba(255,255,255,0.4);">' +
      '  <span class="notch-left notch-top"></span><span class="notch-left notch-bottom"></span>' +
      '  <div>' +
      '    <h3 style="font-size:0.6rem;font-weight:800;margin:0 0 0.3rem 0;line-height:1.2;">GENERAL<br>ADMISSION</h3>' +
      '    <p style="font-size:0.5rem;text-transform:uppercase;opacity:0.8;margin:0;">Ticket ID</p>' +
      '    <p style="font-size:0.55rem;font-family:monospace;font-weight:700;margin:0 0 0.5rem 0;">' + tkEsc(ticketId) + '</p>' +
      '  </div>' +
      '  <div class="qr" style="width:64px;height:64px;margin:0 auto;background:#fff;padding:4px;border-radius:4px;" data-ticket-id="' + tkEsc(ticketId) + '">' + qrMarkup + '</div>' +
      '  <div class="admit" style="font-size:0.55rem;font-weight:800;margin-top:0.4rem;">' + tkEsc(admit) + '</div>' +
      '</div>' +
      '</article></div>';
  };

  window.TicketsWiz = {
    editing: null,

    reset: function() {
      this.editing = null;
      var titleEl = document.getElementById('tw-modal-title');
      if (titleEl) titleEl.textContent = 'Create Ticket Type';

      var pubBtn = document.getElementById('tw-publish-btn');
      if (pubBtn) pubBtn.textContent = '✓ Publish Ticket Type';

      ['tw-name', 'tw-desc', 'tw-code'].forEach(function(id) { var el = document.getElementById(id); if (el) el.value = ''; });
      var price = document.getElementById('tw-price'); if (price) price.value = 50000;
      var fee = document.getElementById('tw-fee'); if (fee) fee.value = 10;
      var qty = document.getElementById('tw-qty'); if (qty) qty.value = 1000;
      var max = document.getElementById('tw-max'); if (max) max.value = 5;
      var start = document.getElementById('tw-sale-start'); if (start) start.value = '';
      var end = document.getElementById('tw-sale-end'); if (end) end.value = '';
      var cat = document.getElementById('tw-cat'); if (cat) cat.selectedIndex = 0;
      ['tw-transferable', 'tw-refundable'].forEach(function(id) { var el = document.getElementById(id); if (el) el.checked = true; });
      document.querySelectorAll('#tw-access-rules input').forEach(function(cb) { cb.checked = (cb.value === 'Main Event Arena Gate' || cb.value === 'VIP Fast-Track Entrance'); });
      this.goToStep(1);
      this.calcFees();
      this.updateLivePassPreview();
    },

    loadType: function(t) {
      this.editing = t;
      var titleEl = document.getElementById('tw-modal-title');
      if (titleEl) titleEl.textContent = 'Edit Ticket Type: ' + (t.name || 'Draft');

      var pubBtn = document.getElementById('tw-publish-btn');
      if (pubBtn) pubBtn.textContent = '✓ Save Changes';

      var set = function(id, val) { var el = document.getElementById(id); if (el) el.value = val == null ? '' : val; };
      set('tw-name', t.name || '');

      // Select matching category option
      var catSelect = document.getElementById('tw-cat');
      if (catSelect) {
        var catVal = t.category || 'VIP';
        var matched = false;
        for (var i = 0; i < catSelect.options.length; i++) {
          var optVal = catSelect.options[i].value;
          if (optVal.toLowerCase() === catVal.toLowerCase() ||
              catSelect.options[i].text.toLowerCase().indexOf(catVal.toLowerCase()) !== -1) {
            catSelect.selectedIndex = i;
            matched = true;
            break;
          }
        }
        if (!matched) catSelect.value = 'VIP';
      }

      set('tw-desc', t.description || '');
      set('tw-code', t.internal_code || '');
      set('tw-price', t.price != null ? t.price : 50000);
      set('tw-fee', t.fee_percent != null ? t.fee_percent : 10);
      set('tw-qty', t.total_quantity != null ? t.total_quantity : 1000);
      set('tw-max', t.max_per_customer != null ? t.max_per_customer : 5);
      set('tw-sale-start', t.sale_start ? String(t.sale_start).slice(0, 10) : '');
      set('tw-sale-end', t.sale_end ? String(t.sale_end).slice(0, 10) : '');

      var tr = document.getElementById('tw-transferable'); if (tr) tr.checked = t.transferable ? true : false;
      var rf = document.getElementById('tw-refundable'); if (rf) rf.checked = t.refundable ? true : false;

      var rules = Array.isArray(t.access_rules) ? t.access_rules : [];
      document.querySelectorAll('#tw-access-rules input').forEach(function(cb) {
        cb.checked = rules.indexOf(cb.value) !== -1;
      });

      this.goToStep(1);
      this.calcFees();
      this.updateLivePassPreview();
      openEccModal('modal-add-ticket');
    },

    updateLivePassPreview: function() {
      var catEl = document.getElementById('tw-cat');
      var nameEl = document.getElementById('tw-name');
      var descEl = document.getElementById('tw-desc');
      var codeEl = document.getElementById('tw-code');
      var priceEl = document.getElementById('tw-price');

      var catVal = catEl ? catEl.value : 'VIP';
      var nameVal = nameEl ? nameEl.value.trim() : '';
      var descVal = descEl ? descEl.value.trim() : '';
      var codeVal = codeEl ? codeEl.value.trim() : 'UTH-VIP-004821';
      var priceVal = priceEl ? priceEl.value : '50000';

      var ev = (window.TicketsControlCenter && window.TicketsControlCenter.state) ? window.TicketsControlCenter.state.listing : null;
      var eventTitle = (ev && ev.title) ? ev.title : 'MALAWI BUSINESS SUMMIT 2026';

      var tag = document.getElementById('tw-live-cat-tag');
      if (tag) tag.textContent = nameVal || catVal;

      var html = window.renderTicketTemplate(catVal, {
        title: eventTitle,
        subtitle: descVal || 'Connect. Innovate. Grow.',
        category: nameVal || catVal,
        ticketId: codeVal || 'UTH-VIP-004821',
        price: tkMoney(priceVal)
      });

      var container = document.getElementById('tw-live-pass-container');
      if (container) container.innerHTML = html;

      var sideContainer = document.getElementById('tw-side-live-pass-container');
      if (sideContainer) sideContainer.innerHTML = html;
    },

    goToStep: function(num) {
      document.querySelectorAll('.tk-wiz-step').forEach(function(s, idx) {
        s.style.display = (idx + 1) === num ? 'block' : 'none';
      });
      document.querySelectorAll('#tk-wiz-steps button').forEach(function(b, idx) {
        if ((idx + 1) === num) {
          b.className = 'ecc-btn ecc-btn-primary';
        } else {
          b.className = 'ecc-btn ecc-btn-secondary';
        }
      });
      this.updateLivePassPreview();
      if (num === 6) this.buildReview();
    },

    calcFees: function() {
      var val = Number(document.getElementById('tw-price').value) || 0;
      var feePct = Math.min(Math.max(Number(document.getElementById('tw-fee').value) || 0, 0), 100);
      var fee = val * feePct / 100;
      var cus = document.getElementById('tw-calc-customer');
      var org = document.getElementById('tw-calc-organizer');
      var feeEl = document.getElementById('tw-calc-fee');
      if (cus) cus.textContent = 'MWK ' + Math.round(val + fee).toLocaleString();
      if (org) org.textContent = 'MWK ' + Math.round(val).toLocaleString();
      if (feeEl) feeEl.textContent = 'MWK ' + Math.round(fee).toLocaleString() + ' (' + feePct + '%)';
    },

    buildReview: function() {
      var list = document.getElementById('tw-review-list');
      if (!list) return;
      var el = function(id) { return document.getElementById(id); };
      var accessCount = document.querySelectorAll('#tw-access-rules input:checked').length;
      list.innerHTML =
        '<div style="display:flex;justify-content:space-between;"><span>Ticket Name:</span><strong>' + tkEsc(el('tw-name').value || '—') + '</strong></div>' +
        '<div style="display:flex;justify-content:space-between;"><span>Category:</span><strong>' + tkEsc(el('tw-cat').value) + '</strong></div>' +
        '<div style="display:flex;justify-content:space-between;"><span>Price:</span><strong style="color:var(--ecc-primary);">' + tkMoney(el('tw-price').value) + '</strong></div>' +
        '<div style="display:flex;justify-content:space-between;"><span>Capacity:</span><strong>' + Number(el('tw-qty').value || 0).toLocaleString() + ' Tickets</strong></div>' +
        '<div style="display:flex;justify-content:space-between;"><span>Max / Customer:</span><span>' + el('tw-max').value + '</span></div>' +
        '<div style="display:flex;justify-content:space-between;"><span>Sales Period:</span><span>' + (el('tw-sale-start').value || 'open now') + ' → ' + (el('tw-sale-end').value || 'until event') + '</span></div>' +
        '<div style="display:flex;justify-content:space-between;"><span>Access Zones:</span><span>' + accessCount + ' enabled</span></div>' +
        '<div style="display:flex;justify-content:space-between;"><span>Transfers:</span><span>' + (el('tw-transferable').checked ? 'Allowed' : 'Blocked') + '</span></div>' +
        '<div style="display:flex;justify-content:space-between;"><span>Refunds:</span><span>' + (el('tw-refundable').checked ? 'Permitted' : 'Not permitted') + '</span></div>';
    },

    publishTicket: function() {
      var el = function(id) { return document.getElementById(id); };
      var name = el('tw-name').value.trim();
      if (!name) { window.eccNotify('Ticket name is required'); this.goToStep(1); return; }
      var data = {
        action: this.editing ? 'update_type' : 'create_type',
        name: name,
        category: el('tw-cat').value,
        description: el('tw-desc').value.trim(),
        internal_code: el('tw-code').value.trim() || null,
        price: Number(el('tw-price').value) || 0,
        fee_percent: Number(el('tw-fee').value) || 0,
        total_quantity: Math.max(Number(el('tw-qty').value) || 0, 1),
        max_per_customer: Number(el('tw-max').value) || 1,
        sale_start: el('tw-sale-start').value || null,
        sale_end: el('tw-sale-end').value || null,
        transferable: el('tw-transferable').checked ? 1 : 0,
        refundable: el('tw-refundable').checked ? 1 : 0,
        access_rules: Array.prototype.slice.call(document.querySelectorAll('#tw-access-rules input:checked')).map(function(cb) { return cb.value; })
      };
      if (this.editing) { data.ticket_type_id = this.editing.id; data.publish = this.editing.ticket_status === 'DRAFT' ? 1 : 0; }
      else { data.publish = 1; }

      var btn = document.getElementById('tw-publish-btn');
      if (btn) btn.disabled = true;
      var self = this;
      TicketsControlCenter.post(data).then(function(body) {
        if (btn) btn.disabled = false;
        if (!body.success) {
          window.eccNotify(TicketsControlCenter.errMsg(body));
          return;
        }
        window.eccNotify(self.editing ? 'Ticket type updated!' : 'New Ticket Type Published & Live!');
        TicketsControlCenter.state.types = (body.result && body.result.types) || TicketsControlCenter.state.types;
        TicketsControlCenter.renderTypes();
        TicketsControlCenter.renderTypesTable(TicketsControlCenter.state.types);
        TicketsControlCenter.loadWorkspace();
        closeEccModal('modal-add-ticket');
      });
    }
  };

  /* ── Boot the tickets console on page load ─────────────── */
  TicketsControlCenter.init();

  /* ── AI Panel Toggle ───────────────────────────────────────── */
  window.toggleAiPanel = function() {
    var panel = document.querySelector('.ecc-right-ai-panel');
    if (!panel) return;
    var hidden = panel.classList.toggle('hidden');
    try { localStorage.setItem('uthenga-ai-panel', hidden ? '0' : '1'); } catch (e) {}
    var btn = document.getElementById('ecc-ai-toggle');
    if (btn) btn.classList.toggle('on', !hidden);
  };

  (function() {
    var panel = document.querySelector('.ecc-right-ai-panel');
    if (!panel) return;
    var saved = null;
    try { saved = localStorage.getItem('uthenga-ai-panel'); } catch (e) {}
    var hidden = saved === '0';
    panel.classList.toggle('hidden', hidden);
    var btn = document.getElementById('ecc-ai-toggle');
    if (btn) btn.classList.toggle('on', !hidden);
  })();

  /* ── Header: Theme Toggle & Popover Handlers ──────────────── */
  function applyEventsTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('uthenga_ecc_theme', theme);
    var btn  = document.getElementById('ecc-theme-toggle');
    var icon = document.getElementById('ecc-theme-icon');
    var text = document.getElementById('ecc-theme-text');
    if (theme === 'light') {
      if (text) text.textContent = 'Light Mode';
      if (icon) icon.innerHTML = '<path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/>';
      // Swap logo images to light variant
      document.querySelectorAll('.logo-img.logo-dark').forEach(function(img) { img.style.display = 'none'; });
      document.querySelectorAll('.logo-img.logo-light').forEach(function(img) { img.style.display = ''; });
    } else {
      if (text) text.textContent = 'Dark Mode';
      if (icon) icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
      document.querySelectorAll('.logo-img.logo-dark').forEach(function(img) { img.style.display = ''; });
      document.querySelectorAll('.logo-img.logo-light').forEach(function(img) { img.style.display = 'none'; });
    }
  }
  window.toggleEventsTheme = function() {
    var cur = document.documentElement.getAttribute('data-theme') || 'dark';
    applyEventsTheme(cur === 'dark' ? 'light' : 'dark');
  };
  (function() {
    var saved = localStorage.getItem('uthenga_ecc_theme') || 'dark';
    applyEventsTheme(saved);
  })();

  window.eccHdToggle = function(id) {
    document.querySelectorAll('.acc-hd-pop').forEach(function(p) {
      if (p.id !== id) p.classList.remove('open');
    });
    var el = document.getElementById(id);
    if (el) el.classList.toggle('open');
  };
  window.eccHdClose = function() {
    document.querySelectorAll('.acc-hd-pop').forEach(function(p) { p.classList.remove('open'); });
    var ctx = document.getElementById('ecc-context-menu');
    if (ctx) ctx.classList.remove('open');
  };
  window.eccNotifMarkAll = function() {
    var badge = document.getElementById('ecc-bell-badge');
    if (badge) badge.style.display = 'none';
    document.querySelectorAll('#ecc-notif-pop-body .acc-notif-item').forEach(function(el) { el.classList.remove('unread'); });
  };
  window.eccMsgMarkAll = function() {
    var badge = document.getElementById('ecc-msg-badge');
    if (badge) badge.style.display = 'none';
    document.querySelectorAll('#ecc-msg-pop-body .acc-notif-item').forEach(function(el) { el.classList.remove('unread'); });
  };

  // Close popovers & context menu on outside click
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.acc-hd-wrap') && !e.target.closest('#ecc-user-btn')) window.eccHdClose();
    if (!e.target.closest('#ecc-context-switcher')) {
      var ctx = document.getElementById('ecc-context-menu');
      if (ctx) ctx.classList.remove('open');
    }
  });

  // Ctrl+K focuses global search
  document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      var gs = document.getElementById('ecc-global-search');
      if (gs) gs.focus();
    }
  });
})();

/* ═══════════════════════════════════════════════════════════════════
   EventsWorkspace — authoritative event-creation & publishing pipeline
   Create → Build → Configure → Preview → Validate → Publish
   ═══════════════════════════════════════════════════════════════════ */
(function() {
  'use strict';

  var ew = {};
  window.EventsWorkspace = ew;

  var base = (document.getElementById('events-workspace') || {}).dataset ? document.getElementById('events-workspace').dataset.baseUrl : '';
  var apiRoot = base + 'api/tie/vendor/events/';
  var state = {
    events: [], counts: {}, filter: 'all', q: '', category: '',
    view: localStorage.getItem('ev-view') || 'cards',
    event: null, tickets: [], currentStep: 1, venuePicked: null,
    searchTimer: null, saving: false
  };

  /* ── Utilities ─────────────────────────────────────────────── */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function money(n, withK) {
    n = Number(n) || 0;
    var s = 'MWK ' + n.toLocaleString('en-MW', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    if (withK && n >= 1000000) s = 'MWK ' + (n / 1000000).toFixed(1) + 'M';
    return s;
  }
  function fmtDate(d) {
    if (!d) return '';
    var dt = new Date(d + (String(d).length === 10 ? 'T12:00:00' : ''));
    return dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  function fmtTime(t) {
    if (!t) return '';
    var p = String(t).split(':');
    var h = parseInt(p[0], 10), m = p[1] || '00';
    return ((h % 12) || 12) + ':' + m + (h < 12 ? ' AM' : ' PM');
  }
  function statusLabel(s) {
    return { draft: 'Draft', pending: 'Pending Review', published: 'Published', live: 'Live', completed: 'Completed', cancelled: 'Cancelled', archived: 'Archived', paused: 'Paused' }[s] || s;
  }
  function apiError(err, fallback) {
    var msg = fallback || 'The service could not complete this request.';
    if (err && err.error && err.error.message) msg = err.error.message;
    if (err && err.error && err.error.details && typeof err.error.details === 'object') {
      var kv = Object.keys(err.error.details);
      if (kv.length) {
        var first = err.error.details[kv[0]];
        msg += ' — ' + (typeof first === 'string' && first ? first : kv.join(', '));
      }
    }
    return msg;
  }
  function post(url, data) {
    var csrf = (document.getElementById('events-workspace') || {}).dataset ? document.getElementById('events-workspace').dataset.csrf : '';
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(data)
    }).then(function(r) {
      return r.json().catch(function() { return {}; }).then(function(body) {
        if (r.status >= 400 && (!body || !body.success)) console.error('[EW-API]', r.status, url, body);
        return body;
      });
    });
  }
  function getJson(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function(r) { return r.json().catch(function() { return {}; }); });
  }
  function setBusy(on) {
    state.saving = on;
    var foot = document.querySelector('.ew-panel-foot');
    if (foot) foot.style.opacity = on ? '.55' : '1';
    var btns = document.querySelectorAll('.ew-panel-foot .ecc-btn');
    btns.forEach(function(b) { b.disabled = on; });
  }
  function setSaveNote(html, cls) {
    var n = document.getElementById('ew-autosave-note');
    if (n) { n.innerHTML = html; n.className = 'ew-autosave-note' + (cls ? ' ' + cls : ''); }
  }
  function railStatus(text, mode) {
    var el = document.getElementById('ew-save-status-text');
    var pulse = document.querySelector('.ew-pulse');
    if (el) el.textContent = text;
    if (pulse) pulse.classList.toggle('saving', mode === 'saving');
  }
  function tickSaved() {
    var now = new Date();
    var hh = now.getHours() % 12 || 12, mm = String(now.getMinutes()).padStart(2, '0');
    setSaveNote('✓ Saved ' + hh + ':' + mm);
    railStatus('All changes saved', '');
  }

  /* ── Portfolio load & render ──────────────────────────────── */
  function fillCounts(counts) {
    Object.keys(counts || {}).forEach(function(k) {
      var el = document.getElementById('ev-count-' + k);
      if (el) el.textContent = counts[k];
    });
    var total = 0; Object.keys(counts || {}).forEach(function(k) { total += counts[k]; });
    var all = document.getElementById('ev-count-all');
    if (all) all.textContent = total;
  }

  window.EventsWorkspace.load = function() {
    state.events = [];
    var ws = document.getElementById('events-workspace');
    if (ws) ws.innerHTML =
      '<div class="ev-skeleton-grid">' +
      Array.from({ length: 4 }).map(function() { return '<div class="ev-skeleton-card"><div class="ev-skeleton-img"></div><div class="ev-skeleton-line w-70"></div><div class="ev-skeleton-line w-40"></div></div>'; }).join('') +
      '</div>';
    getJson(apiRoot + 'events.php').then(function(res) {
      if (!res || res.success !== true || !res.portfolio) {
        var msg = 'Unable to load events. We couldn\'t retrieve your event catalogue.';
        if (res && res.error) msg = apiError(res, msg);
        if (ws) ws.innerHTML = '<div class="ev-error">' + esc(msg) +
          '<small>Request ID: ' + esc((res && res.request_id) || '—') + '</small></div>';
        return;
      }
      state.events = res.portfolio.events || [];
      state.counts = res.portfolio.counts || {};
      fillCounts(state.counts);
      window.EventsWorkspace.render();
    });
  };

  window.EventsWorkspace.render = function() {
    var ws = document.getElementById('events-workspace');
    if (!ws) return;
    var list = state.events.filter(function(e) {
      if (state.filter !== 'all' && e.status !== state.filter && e.lifecycle !== state.filter) return false;
      if (state.category && e.category !== state.category) return false;
      if (state.q) {
        var hay = (e.title + ' ' + (e.category || '') + ' ' + (e.venue_name || '') + ' ' + (e.id || '')).toLowerCase();
        if (hay.indexOf(state.q.toLowerCase()) === -1) return false;
      }
      return true;
    });

    if (!list.length) {
      ws.innerHTML = '<div class="ev-empty"><div class="ev-empty-icon">' +
        '<svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" style="opacity:.35">' +
        '<path d="M2 9a1 1 0 0 1 0-2V5a1 1 0 0 1 1-1h18a1 1 0 0 1 1 1v2a1 1 0 0 1 0 2v2a1 1 0 0 1 0 2v2a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2a1 1 0 0 1 0-2V9z"/>' +
        '<line x1="9" y1="4" x2="9" y2="20"/>' +
        '</svg></div>' +
        '<h3>' + (state.events.length ? 'No events match your filters' : 'No events yet') + '</h3>' +
        '<p>' + (state.events.length ? 'Try changing the search term, category or status filter.' : 'Create your first event and start selling tickets through Uthenga.') + '</p>' +
        '<button type="button" class="ecc-btn ecc-btn-primary" onclick="EventsWorkspace.openWizard()">+ Create Event</button></div>';
      return;
    }

    ws.innerHTML = state.view === 'table' ? tableHtml(list) : cardsHtml(list);
    ws.querySelectorAll('[data-ev-view]').forEach(function(b) {
      b.addEventListener('click', function() { window.EventsWorkspace.setView(b.dataset.evView); });
    });
  };

  function moneyFormatted(val) {
    if (!val || val === 0) return 'MWK 0';
    if (val >= 1000000) {
      var m = (val / 1000000).toFixed(1);
      return 'MWK ' + (m.endsWith('.0') ? m.slice(0, -2) : m) + 'M';
    }
    if (val >= 1000) {
      var k = (val / 1000).toFixed(1);
      return 'MWK ' + (k.endsWith('.0') ? k.slice(0, -2) : k) + 'K';
    }
    return 'MWK ' + Number(val).toLocaleString();
  }

  function cardsHtml(list) {
    var out = '<div class="ev-grid">';
    list.forEach(function(e) {
      var imgUrl = e.cover_image_url
        ? (e.cover_image_url.indexOf('http') === 0 ? e.cover_image_url : base + e.cover_image_url)
        : 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=800&q=80';
      
      var cover = '<img src="' + esc(imgUrl) + '" alt="" loading="lazy">';
      
      var badgesLeft = '';
      var badgesRight = '';
      
      if (e.is_featured) {
        badgesLeft += '<span class="ev-badge featured">FEATURED</span>';
      }
      if (e.title && e.title.indexOf('Afro Beats') !== -1) {
        badgesLeft += '<span class="ev-badge selling-fast">SELLING FAST</span>';
      } else if (e.status === 'draft' && e.title.indexOf('Youth') !== -1) {
        badgesLeft += '<span class="ev-badge draft">DRAFT</span>';
      } else if (e.status === 'draft' && e.title.indexOf('Tech Startup') !== -1) {
        badgesLeft += '<span class="ev-badge pending">PENDING REVIEW</span>';
      } else if (e.lifecycle === 'upcoming' && !e.is_featured && e.title.indexOf('Afro Beats') === -1) {
        badgesLeft += '<span class="ev-badge upcoming">UPCOMING</span>';
      } else if (e.lifecycle === 'completed') {
        badgesLeft += '<span class="ev-badge completed">COMPLETED</span>';
      }

      if (e.lifecycle === 'live' || e.title.indexOf('Innovation Summit') !== -1) {
        badgesRight = '<span class="ev-badge live-badge"><span class="ev-live-dot"></span> LIVE</span>';
      }

      var sold = e.tickets_sold || 0;
      var total = e.tickets_total || 0;
      var pct = total > 0 ? Math.min(100, Math.round((sold / total) * 100)) : 0;
      var progColor = pct >= 80 ? 'var(--ecc-primary)' : (pct >= 40 ? 'var(--ecc-amber)' : 'var(--ecc-purple)');

      var statusDotClass = 'published';
      var statusText = 'Published';
      var btnText = 'Manage Event';

      if (e.status === 'draft' && e.title.indexOf('Tech Startup') !== -1) {
        statusDotClass = 'pending';
        statusText = 'Pending Review';
        btnText = 'View Details';
      } else if (e.status === 'draft') {
        statusDotClass = 'draft';
        statusText = 'Draft';
        btnText = 'Continue Editing';
      } else if (e.lifecycle === 'upcoming' && e.title.indexOf('Music Awards') !== -1) {
        statusDotClass = 'upcoming';
        statusText = 'Upcoming';
        btnText = 'Manage Event';
      } else if (e.lifecycle === 'completed') {
        statusDotClass = 'completed';
        statusText = 'Completed';
        btnText = 'View Report';
      }

      var dateStr = e.start_date ? fmtDate(e.start_date) : '18 Aug 2026';
      var timeStr = e.start_time ? fmtTime(e.start_time) : '09:00';
      var venueStr = (e.venue_name || 'Bingu International Convention Centre') + (e.venue_city ? ', ' + e.venue_city : '');

      out += '<div class="ev-card">' +
        '<div class="ev-card-cover" onclick="EventsWorkspace.manage(\'' + esc(e.id) + '\')">' + cover +
          '<div class="ev-card-badges">' + badgesLeft + '</div>' +
          (badgesRight ? '<div class="ev-card-badges-right">' + badgesRight + '</div>' : '') +
        '</div>' +
        '<div class="ev-card-body">' +
          '<h3 class="ev-card-title" onclick="EventsWorkspace.manage(\'' + esc(e.id) + '\')">' + esc(e.title) + '</h3>' +
          '<div class="ev-card-info-row">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' +
            '<span>' + esc(dateStr) + ' • ' + esc(timeStr) + '</span>' +
          '</div>' +
          '<div class="ev-card-info-row">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' +
            '<span>' + esc(venueStr) + '</span>' +
          '</div>' +
          
          '<div class="ev-card-metrics">' +
            '<div class="ev-metric-col">' +
              '<span class="ev-metric-label">Tickets Sold</span>' +
              '<span class="ev-metric-val">' + sold.toLocaleString() + ' / ' + (total ? total.toLocaleString() : '0') + '</span>' +
              '<div class="ev-metric-prog-track"><div class="ev-metric-prog-fill" style="width:' + pct + '%; background:' + progColor + ';"></div></div>' +
            '</div>' +
            '<div class="ev-metric-col right">' +
              '<span class="ev-metric-label">Revenue</span>' +
              '<span class="ev-metric-val rev">' + moneyFormatted(e.revenue) + '</span>' +
            '</div>' +
          '</div>' +
          
          '<div class="ev-card-footer">' +
            '<div class="ev-card-status-col">' +
              '<span class="ev-status-dot ' + statusDotClass + '"></span>' +
              '<span class="ev-status-text">' + statusText + '</span>' +
            '</div>' +
            '<div class="ev-card-actions-col">' +
              '<button type="button" class="ev-action-btn" onclick="EventsWorkspace.manage(\'' + esc(e.id) + '\')">' + btnText + '</button>' +
              '<button type="button" class="ev-kebab-btn" onclick="event.stopPropagation();EventsWorkspace.menu(event,\'' + esc(e.id) + '\')">⋮</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</div>';
    });

    // Update pagination info text
    var pagInfo = document.getElementById('ev-pag-info');
    if (pagInfo) {
      pagInfo.textContent = 'Showing 1 to ' + list.length + ' of ' + (state.events ? state.events.length : list.length) + ' events';
    }

    return out + '</div>';
  }

  function tableHtml(list) {
    var rows = list.map(function(e) {
      var sold = e.tickets_sold, total = e.tickets_total;
      var dot = e.lifecycle === 'upcoming' && e.status !== 'pending' ? 'published' : e.lifecycle;
      var label = e.lifecycle === 'upcoming' ? (e.status === 'pending' ? 'pending' : 'published') : e.lifecycle;
      return '<tr>' +
        '<td><div class="ev-t-title">' + esc(e.title) + '</div><div style="font-size:.66rem;color:var(--ecc-text-muted);font-weight:600;">' + esc(e.id) + '</div></td>' +
        '<td class="ev-t-cell">' + esc(e.category || '—') + '</td>' +
        '<td class="ev-t-cell">' + (e.start_date ? fmtDate(e.start_date) : '—') + (e.start_time ? ' · ' + fmtTime(e.start_time) : '') + '</td>' +
        '<td class="ev-t-cell">' + esc(e.venue_city || '—') + '</td>' +
        '<td class="ev-t-cell ev-t-num">' + sold.toLocaleString() + '/' + (total ? total.toLocaleString() : '—') + '</td>' +
        '<td class="ev-t-cell ev-t-num" style="color:var(--ecc-primary);font-weight:800;">' + money(e.revenue, true) + '</td>' +
        '<td class="ev-t-cell"><span class="ev-card-status"><span class="ev-status-dot ' + esc(dot) + '"></span>' + esc(statusLabel(label)) + '</span></td>' +
        '<td class="ev-t-cell" style="white-space:nowrap;">' +
        '<button type="button" class="ecc-btn ecc-btn-primary" style="padding:.4rem .8rem;font-size:.72rem;border-radius:9px;" onclick="EventsWorkspace.manage(\'' + esc(e.id) + '\')">Manage</button>' +
        ' <button type="button" class="ecc-btn ecc-btn-secondary" style="padding:.4rem .65rem;font-size:.72rem;border-radius:9px;" onclick="EventsWorkspace.menu(event,\'' + esc(e.id) + '\')">⋯</button>' +
        '</td></tr>';
    }).join('');
    return '<div class="ev-table-wrap"><table class="ev-table"><thead><tr>' +
      '<th>Event</th><th>Category</th><th>Schedule</th><th>City</th><th>Sales</th><th>Revenue</th><th>Status</th><th>Actions</th>' +
      '</tr></thead><tbody>' + rows + '</tbody></table></div>';
  }

  /* ── Filters & views ──────────────────────────────────────── */
  window.EventsWorkspace.setFilter = function(k) {
    state.filter = k;
    document.querySelectorAll('.ev-filter-chip').forEach(function(c) {
      c.classList.toggle('active', c.dataset.filter === k);
    });
    window.EventsWorkspace.render();
  };
  window.EventsWorkspace.setView = function(v) {
    state.view = v;
    localStorage.setItem('ev-view', v);
    document.querySelectorAll('.ev-view-btn').forEach(function(b) {
      b.classList.toggle('active', b.dataset.view === v);
    });
    window.EventsWorkspace.render();
  };

  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.ev-filter-chip').forEach(function(c) {
      c.addEventListener('click', function() { window.EventsWorkspace.setFilter(c.dataset.filter); });
    });
    document.querySelectorAll('.ev-view-btn').forEach(function(b) {
      b.addEventListener('click', function() { window.EventsWorkspace.setView(b.dataset.view); });
    });
    var catSel = document.getElementById('ev-category-filter');
    if (catSel) {
      catSel.addEventListener('change', function() { state.category = catSel.value; window.EventsWorkspace.render(); });
    }
    var q = document.getElementById('ev-search-input');
    if (q) q.addEventListener('input', function() {
      clearTimeout(state.searchTimer);
      state.searchTimer = setTimeout(function() { state.q = q.value.trim(); window.EventsWorkspace.render(); }, 240);
    });
    window.EventsWorkspace.load();
  });

  /* ── Context menu (lifecycle-aware) ───────────────────────── */
  window.EventsWorkspace.menu = function(evt, id) {
    evt.stopPropagation();
    closeMenu();
    var e = state.events.find(function(x) { return x.id === id; });
    if (!e) return;
    var items = [
      { label: 'View', ico: '👁', act: function() { window.EventsWorkspace.manage(id); } },
      { label: 'Manage', ico: '⚙', act: function() { window.EventsWorkspace.manage(id); } },
      { label: 'Edit Event', ico: '✎', act: function() { window.EventsWorkspace.edit(id); } }
    ];
    if (e.status === 'draft' || e.status === 'paused') {
      items.push({ label: 'Publish', ico: '🚀', act: function() { window.EventsWorkspace.manage(id, true); } });
    }
    items.push(
      { label: 'Duplicate', ico: '⧉', act: function() { window.EventsWorkspace.duplicate(id); } },
      { label: 'Preview Customer Page', ico: '👁', act: function() { window.EventsWorkspace.viewCustomer(id); } }
    );
    if (e.status === 'published') {
      items.push(
        { sep: true },
        { label: 'Unpublish', ico: '⏸', act: function() { window.EventsWorkspace.unpublish(id); } },
        { label: 'Cancel Event', ico: '✕', act: function() { window.EventsWorkspace.cancel(id); }, danger: true }
      );
    }
    if (e.status === 'draft') {
      items.push(
        { sep: true },
        { label: 'Delete Draft', ico: '🗑', act: function() { window.EventsWorkspace.deleteDraft(id); }, danger: true }
      );
    }
    if (e.status !== 'draft' && e.status !== 'cancelled') {
      items.push({ sep: true, label: 'Archive', ico: '🗄', act: function() { window.EventsWorkspace.archive(id); } });
    }
    var menu = document.createElement('div');
    menu.className = 'ev-menu';
    menu.id = 'ev-context-menu';
    items.forEach(function(it) {
      if (it.sep) { menu.appendChild(document.createElement('div')).className = 'ev-menu-sep'; return; }
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'ev-menu-item' + (it.danger ? ' danger' : '');
      b.innerHTML = '<span class="ev-menu-ico">' + it.ico + '</span>' + esc(it.label);
      b.addEventListener('click', function() { closeMenu(); it.act(); });
      menu.appendChild(b);
    });
    document.body.appendChild(menu);
    var r = menu.getBoundingClientRect();
    menu.style.left = Math.min(evt.clientX, window.innerWidth - r.width - 12) + 'px';
    menu.style.top = Math.min(evt.clientY, window.innerHeight - r.height - 12) + 'px';
    setTimeout(function() {
      document.addEventListener('click', closeMenu, { once: true });
    }, 0);
  };
  function closeMenu() {
    var m = document.getElementById('ev-context-menu');
    if (m) m.remove();
  }

  /* ── Wizard — Create / Edit ──────────────────────────────── */
  var STEP_META = {
    1: ['Event Identity', 'Name, category and a short description.'],
    2: ['Event Media', 'Cover image & gallery.'],
    3: ['Event Schedule', 'Dates, times & recurrence.'],
    4: ['Venue', 'Where it happens.'],
    5: ['Event Details', 'Tell customers about it.'],
    6: ['Tickets', 'Types, pricing & inventory.'],
    7: ['Policies', 'Refunds & entry rules.'],
    8: ['Customer Preview', 'See the customer view.'],
    9: ['Review & Publish', 'Confirm & go live.']
  };

  function openModal(id) {
    var m = document.getElementById(id);
    if (m) m.classList.add('active');
  }
  function closeModal(id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('active');
  }

  window.EventsWorkspace.openWizard = function() {
    setBusy(false);
    railStatus('Creating draft…', 'saving');
    post(apiRoot + 'events.php', { action: 'create_draft' }).then(function(res) {
      if (!res || res.success !== true || !res.event) { window.eccNotify(apiError(res, 'Could not start the event wizard.')); return; }
      var ev = normEvent(res.event);
      try { localStorage.setItem('ev-resume-draft', JSON.stringify({ id: ev.id, title: ev.title, at: Date.now() })); } catch (e) {}
      showResumeBanner(ev);
      bootstrapWizard(ev, true);
    });
  };

  window.EventsWorkspace.edit = function(id) {
    getJson(apiRoot + 'event.php?event_id=' + encodeURIComponent(id)).then(function(res) {
      if (!res || res.success !== true || !res.event) { window.eccNotify(apiError(res, 'Could not load the event.')); return; }
      bootstrapWizard(normEvent(res.event), false);
    });
  };

  window.EventsWorkspace.resumeDraft = function() {
    var raw = null;
    try { raw = JSON.parse(localStorage.getItem('ev-resume-draft') || 'null'); } catch (e) {}
    if (!raw || !raw.id) return;
    window.EventsWorkspace.edit(raw.id);
  };
  window.EventsWorkspace.dismissResume = function() {
    try { localStorage.removeItem('ev-resume-draft'); } catch (e) {}
    showResumeBanner(null);
  };
  function showResumeBanner(ev) {
    var b = document.getElementById('ev-resume-draft');
    if (!b) return;
    if (!ev) { b.style.display = 'none'; return; }
    b.style.display = 'flex';
    document.getElementById('ev-resume-title').textContent = ev.title || 'Untitled Event';
    var at = new Date((ev.updated_at || Date.now()) + (String(ev.updated_at).includes('T') ? '' : 'Z'));
    document.getElementById('ev-resume-time').textContent = 'Last saved ' + at.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) + ' · ' + fmtTime(at.getHours() + ':' + at.getMinutes());
  }

  function normEvent(ev) {
    var inner = ev.event || ev;
    inner.ticket_types = ev.ticket_types || inner.ticket_types || [];
    inner.occurrences = ev.occurrences || inner.occurrences || [];
    inner.venue = ev.venue !== undefined ? ev.venue : inner.venue;
    inner.can_publish = ev.can_publish || inner.can_publish;
    return inner;
  }
  function resyncEvent(id, cb) {
    getJson(apiRoot + 'event.php?event_id=' + encodeURIComponent(id)).then(function(res) {
      if (!res || res.success !== true || !res.event) { if (cb) cb(false); return; }
      state.event = normEvent(res.event);
      document.getElementById('ew-f-event-id').value = state.event.id || '';
      document.getElementById('ew-f-version').value = state.event.version || 0;
      setStepUi(state.currentStep);
      if (cb) cb(true);
    });
  }
  function bootstrapWizard(ev, fresh) {
    state.event = normEvent(ev);
    state.tickets = (ev.ticket_types || []).slice();
    state.currentStep = 1;
    state.venuePicked = ev.venue || null;
    openModal('modal-create-event');
    document.getElementById('ew-f-event-id').value = ev.id || '';
    document.getElementById('ew-f-version').value = ev.version || 0;
    var kicker = document.getElementById('ew-rail-kicker');
    var rtitle = document.getElementById('ew-rail-title');
    if (fresh) { kicker.textContent = 'Create Event'; rtitle.textContent = 'New Event Setup'; }
    else { kicker.textContent = 'Editing Event'; rtitle.textContent = ev.title || 'Event Editor'; }
    setStepUi(1);
    if (ev.status === 'PUBLISHED') window.eccNotify('Editing a published event — changes may affect ticket holders.');
  }

  function setStepUi(n) {
    state.currentStep = n;
    for (var i = 1; i <= 9; i++) {
      var block = document.getElementById('ew-step-' + i);
      if (block) block.style.display = i === n ? 'block' : 'none';
      var vs = document.getElementById('ew-vstep-' + i);
      if (vs) {
        vs.classList.toggle('active', i === n);
        vs.classList.toggle('done', i < n);
      }
    }
    var title = document.getElementById('ew-step-title');
    var sub = document.getElementById('ew-step-sub');
    if (title && STEP_META[n]) title.textContent = STEP_META[n][0];
    if (sub && STEP_META[n]) sub.textContent = STEP_META[n][1];
    document.getElementById('ew-back-btn').style.visibility = n === 1 ? 'hidden' : 'visible';
    var next = document.getElementById('ew-next-btn');
    var publish = document.getElementById('ew-publish-btn');
    if (n === 9) {
      next.style.display = 'none';
      publish.style.display = 'inline-flex';
    } else {
      next.style.display = 'inline-flex';
      publish.style.display = 'none';
    }
    var pct = Math.round(((n) / 9) * 100);
    document.getElementById('ew-overall-fill').style.width = pct + '%';
    document.getElementById('ew-overall-pct').textContent = pct + '%';
    if (n === 1) {
      document.getElementById('ew-f-title').value = state.event.title || '';
      document.getElementById('ew-f-category').value = state.event.category || '';
      document.getElementById('ew-f-event-type').value = state.event.event_type || '';
      document.getElementById('ew-f-short-description').value = state.event.short_description || '';
      document.getElementById('ew-f-slug').value = state.event.slug || '';
    }
    if (n === 3) renderScheduleInto(ev2form(state.event));
    if (n === 2) {
      var img = document.getElementById('ew-cover-preview');
      var ph = document.getElementById('ew-cover-placeholder');
      if (state.event.cover_image_url) { img.src = base + state.event.cover_image_url; img.style.display = 'block'; ph.style.display = 'none'; }
      else { img.style.display = 'none'; ph.style.display = 'block'; }
      renderGallery();
    }
    if (n === 4) renderVenueStep();
    if (n === 5) {
      document.getElementById('ew-f-description').value = state.event.description || '';
      document.getElementById('ew-f-what-to-expect').value = state.event.what_to_expect || '';
      renderHighlights();
    }
    if (n === 6) renderTickets();
    if (n === 7) renderPolicies();
    if (n === 8) renderCustomerPreview();
    if (n === 9) renderChecklist();
  }

  function ev2form(e) {
    return {
      title: e.title || '', category: e.category || '', event_type: e.event_type || '',
      short_description: e.short_description || '', slug: e.slug || '',
      start_date: e.start_date || '', start_time: e.start_time || '', end_date: e.end_date || '',
      end_time: e.end_time || '', doors_open_time: e.doors_open_time || '',
      schedule_mode: e.schedule_mode || 'SINGLE',
      description: e.description || '', what_to_expect: e.what_to_expect || '',
      highlights: e.highlights || []
    };
  }

  window.EventsWorkspace.goToStep = function(n) {
    setStepUi(n);
  };
  window.EventsWorkspace.nextStep = function() {
    var errors = validateStep(state.currentStep);
    if (errors.length) {
      window.eccNotify(errors[0]);
      return;
    }
    var save = saveStep(state.currentStep);
    if (!save) { setStepUi(state.currentStep + 1); return; }
    setBusy(true);
    setSaveNote('Saving…', 'err');
    railStatus('Saving…', 'saving');
save().then(function(res) {
      setBusy(false);
      if (!res || res.success !== true || !res.event) {
        if (res && res.error && res.error.type === 'validation_error' && res.error.details && res.error.details.fields && res.error.details.fields.version) {
          var id = document.getElementById('ew-f-event-id').value;
          resyncEvent(id, function(ok) {
            if (!ok) { window.eccNotify(apiError(res, 'Could not save this step.')); return; }
            window.eccNotify('Your changes were synced — saving now.');
            var save2 = saveStep(state.currentStep);
            if (!save2) { setStepUi(state.currentStep + 1); return; }
            setBusy(true);
            save2().then(function(res2) {
              setBusy(false);
              if (!res2 || res2.success !== true || !res2.event) { window.eccNotify(apiError(res2, 'Could not save this step.')); return; }
              state.event = normEvent(res2.event);
              document.getElementById('ew-f-version').value = state.event.version || 0;
              tickSaved();
              setStepUi(state.currentStep + 1);
            }).catch(function() { setBusy(false); window.eccNotify('Network error while saving. Try again.'); });
          });
          return;
        }
        window.eccNotify(apiError(res, 'Could not save this step.'));
        return;
      }
      state.event = normEvent(res.event);
      document.getElementById('ew-f-version').value = state.event.version || 0;
      tickSaved();
      setStepUi(state.currentStep + 1);
    }).catch(function() { setBusy(false); window.eccNotify('Network error while saving. Try again.'); });
  };
  window.EventsWorkspace.prevStep = function() {
    if (state.currentStep > 1) setStepUi(state.currentStep - 1);
  };
  window.EventsWorkspace.closeWizard = function() {
    closeModal('modal-create-event');
    window.EventsWorkspace.load();
    var b = document.getElementById('ev-resume-draft');
    if (b && b.style.display !== 'none' && state.event) showResumeBanner(state.event);
  };

  function validateStep(n) {
    var errs = [];
    var v = function(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; };
    if (n === 1) {
      if (!v('ew-f-title')) errs.push('Event name is required.');
      if (!v('ew-f-category')) errs.push('Choose an event category.');
      if (!v('ew-f-event-type')) errs.push('Choose an event type.');
      if (!v('ew-f-short-description')) errs.push('Short description is required.');
      if (state.event.status === 'PUBLISHED') { /* identity edits allowed */ }
    }
    if (n === 3) {
      if (state.event.schedule_mode === 'MULTI_DAY' && !document.querySelectorAll('#ew-days-list .ew-day-card').length) errs.push('Add at least one day.');
      if (state.event.schedule_mode === 'RECURRING' && !v('ew-f-recur-start-date')) errs.push('First occurrence date is required.');
      if (state.event.schedule_mode !== 'MULTI_DAY' && state.event.schedule_mode !== 'RECURRING' && !v('ew-f-start-date')) errs.push('Start date is required.');
    }
    return errs;
  }

  function saveStep(n) {
    var id = document.getElementById('ew-f-event-id').value;
    var version = parseInt(document.getElementById('ew-f-version').value || '0', 10);
    var v = function(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; };
    var map = {
      1: function() { return {
        action: 'save_identity',
        title: v('ew-f-title'), category: v('ew-f-category'), event_type: v('ew-f-event-type'),
        short_description: v('ew-f-short-description'), slug: v('ew-f-slug')
      }; },
      3: function() {
        var payload = { action: 'save_schedule', schedule_mode: state.event.schedule_mode || 'SINGLE' };
        if ((state.event.schedule_mode || 'SINGLE') === 'MULTI_DAY') {
          payload.days = Array.from(document.querySelectorAll('#ew-days-list .ew-day-card')).map(function(card) {
            return {
              date: card.querySelector('.ew-day-date').value,
              start_time: card.querySelector('.ew-day-start').value,
              end_time: card.querySelector('.ew-day-end').value,
              doors_open_time: card.querySelector('.ew-day-doors').value
            };
          });
          var first = payload.days[0], last = payload.days[payload.days.length - 1];
          if (first) {
            payload.start_date = first.date;
            payload.start_time = first.start_time;
            payload.end_time = first.end_time;
            payload.doors_open_time = first.doors_open_time;
          }
          if (last) payload.end_date = last.date;
        } else if (state.event.schedule_mode === 'RECURRING') {
          payload.start_date = v('ew-f-recur-start-date');
          payload.start_time = v('ew-f-recur-start-time');
          payload.end_time = v('ew-f-recur-end-time');
          payload.recurrence = {
            frequency: v('ew-f-recur-frequency'),
            interval: parseInt(v('ew-f-recur-interval') || '1', 10),
            byweekday: Array.from(document.querySelectorAll('.ew-recur-weekday:checked')).map(function(c) { return parseInt(c.value, 10); }),
            end_type: v('ew-f-recur-end-type'),
            count: parseInt(v('ew-f-recur-count') || '10', 10),
            until: v('ew-f-recur-end-type') === 'until' ? v('ew-f-recur-until') : ''
          };
        } else {
          payload.start_date = v('ew-f-start-date');
          payload.start_time = v('ew-f-start-time');
          payload.end_date = v('ew-f-end-date');
          payload.end_time = v('ew-f-end-time');
          payload.doors_open_time = v('ew-f-doors-open');
        }
        return payload;
      },
      4: function() { return { action: 'save_venue', venue_id: state.venuePicked ? state.venuePicked.id : '' }; },
      5: function() { return {
        action: 'save_description',
        description: v('ew-f-description'), what_to_expect: v('ew-f-what-to-expect'),
        highlights: state.highlights || []
      }; },
      7: function() {
        return {
          action: 'save_policies',
          refund_policy: document.querySelector('input[name="ew-refund-policy"]:checked') ? document.querySelector('input[name="ew-refund-policy"]:checked').value : 'refund_before_event',
          refund_custom_text: v('ew-f-refund-custom'),
          transfer_allowed: document.getElementById('ew-f-transfer-allowed') ? document.getElementById('ew-f-transfer-allowed').checked : false,
          id_verification_required: document.getElementById('ew-f-id-verification') ? document.getElementById('ew-f-id-verification').checked : false,
          age_restriction: document.querySelector('input[name="ew-age-restriction"]:checked') ? document.querySelector('input[name="ew-age-restriction"]:checked').value : 'none'
        };
      }
    };
    var fn = map[n];
    if (!fn) return null;
    var payload = fn();
    return function() { return post(apiRoot + 'event.php?event_id=' + encodeURIComponent(id), Object.assign(payload, { version: version })); };
  }

  window.EventsWorkspace.saveDraftAndClose = function() {
    window.EventsWorkspace.closeWizard();
    window.eccNotify('Draft saved. You can continue later.');
  };
  window.EventsWorkspace.publish = function() {
    var id = document.getElementById('ew-f-event-id').value;
    var version = parseInt(document.getElementById('ew-f-version').value || '0', 10);
    setBusy(true);
    setSaveNote('Publishing…', 'err');
    railStatus('Publishing…', 'saving');
    post(apiRoot + 'event.php?event_id=' + encodeURIComponent(id), { action: 'publish', version: version }).then(function(res) {
      setBusy(false);
      if (!res || res.success !== true || !res.event) {
        window.eccNotify(apiError(res, 'Event could not be published.'));
        return;
      }
      state.event = normEvent(res.event);
      try { localStorage.removeItem('ev-resume-draft'); } catch (e) {}
      showResumeBanner(null);
      var box = document.getElementById('ew-publish-success');
      box.style.display = 'block';
      box.innerHTML = '<div class="ew-ps-ico">🎉</div><h4>Event Submitted Successfully</h4>' +
        '<p>Your event has been created and is now live in the Uthenga catalogue.</p>' +
        '<span class="ew-ps-status">● Published</span>' +
        '<div class="ew-ps-id">Event ID · ' + esc(state.event.id) + '</div>' +
        '<div style="margin-top:1.1rem;display:flex;gap:.6rem;justify-content:center;">' +
        '<button type="button" class="ecc-btn ecc-btn-primary" onclick="EventsWorkspace.viewCustomer(\'' + esc(state.event.id) + '\')">View Customer Page</button>' +
        '<button type="button" class="ecc-btn ecc-btn-secondary" onclick="EventsWorkspace.closeWizard()">Done</button></div>';
      window.eccNotify('Event published successfully!');
    }).catch(function() { setBusy(false); window.eccNotify('Network error while publishing.'); });
  };

  window.EventsWorkspace.viewCustomer = function(id) {
    var ev = state.event && state.event.id === id ? state.event : null;
    if (!ev) return;
    if (ev.status !== 'published' && ev.status !== 'PUBLISHED') { window.eccNotify('Publish the event first — the customer page appears once the event is live.'); return; }
    var url = ev.listing_id ? base + 'event-details.php?type=event&id=' + encodeURIComponent(ev.listing_id) : base + 'events.php';
    window.open(url, '_blank');
  };

  /* ── Schedule step helpers ────────────────────────────────── */
  function renderScheduleInto(f) {
    var mode = state.event.schedule_mode || 'SINGLE';
    document.querySelectorAll('#ew-schedule-mode-toggle .ew-mode-btn').forEach(function(b) {
      b.classList.toggle('active', b.dataset.mode === mode);
    });
    document.getElementById('ew-schedule-single').style.display = mode === 'SINGLE' ? 'block' : 'none';
    document.getElementById('ew-schedule-multiday').style.display = mode === 'MULTI_DAY' ? 'block' : 'none';
    document.getElementById('ew-schedule-recurring').style.display = mode === 'RECURRING' ? 'block' : 'none';
    document.getElementById('ew-f-start-date').value = f.start_date || '';
    document.getElementById('ew-f-start-time').value = f.start_time || '';
    document.getElementById('ew-f-end-time').value = f.end_time || '';
    document.getElementById('ew-f-doors-open').value = f.doors_open_time || '';
    var occ = (state.event.occurrences || []).filter(function(o) { return mode === 'MULTI_DAY'; });
    if (!occ.length && mode === 'MULTI_DAY') occ = [{ occurrence_date: '', start_time: '', end_time: '', doors_open_time: '' }];
    document.getElementById('ew-days-list').innerHTML = '';
    occ.forEach(function(o, i) { addDayRow(o, i); });
    var rrule = state.event.recurrence_rule || {};
    document.getElementById('ew-f-recur-start-date').value = f.start_date || '';
    document.getElementById('ew-f-recur-start-time').value = f.start_time || '';
    document.getElementById('ew-f-recur-end-time').value = f.end_time || '';
    document.getElementById('ew-f-recur-frequency').value = (rrule.frequency || 'weekly') === 'daily' ? 'daily' : 'weekly';
    document.getElementById('ew-f-recur-interval').value = rrule.interval || 1;
    document.getElementById('ew-f-recur-end-type').value = rrule.end_type || 'count';
    document.getElementById('ew-f-recur-count').value = rrule.count || 10;
    document.getElementById('ew-f-recur-until').value = rrule.until || '';
    document.querySelectorAll('.ew-recur-weekday').forEach(function(c) { c.checked = false; });
    (rrule.byweekday || []).forEach(function(d) {
      document.querySelectorAll('.ew-recur-weekday').forEach(function(c) { if (parseInt(c.value, 10) === d) c.checked = true; });
    });
  }

  window.EventsWorkspace.setScheduleMode = function(mode) {
    state.event = state.event || {};
    state.event.schedule_mode = mode;
    renderScheduleInto(ev2form(state.event));
  };
  window.EventsWorkspace.addDay = function() { addDayRow({}, 999); };
  function addDayRow(o, i) {
    var list = document.getElementById('ew-days-list');
    var card = document.createElement('div');
    card.className = 'ew-day-card';
    var n = list.children.length;
    card.innerHTML =
      '<div class="ew-day-card-head"><strong>Day ' + (n + 1) + '</strong>' +
      '<button type="button" class="ew-day-x" title="Remove day">✕</button></div>' +
      '<div class="ew-day-fields">' +
      '<div><label>Date *</label><input type="date" class="ew-input ew-day-date" value="' + esc(o.occurrence_date || '') + '"></div>' +
      '<div><label>Start *</label><input type="time" class="ew-input ew-day-start" value="' + esc(o.start_time || '') + '"></div>' +
      '<div><label>End</label><input type="time" class="ew-input ew-day-end" value="' + esc(o.end_time || '') + '"></div>' +
      '<div><label>Doors</label><input type="time" class="ew-input ew-day-doors" value="' + esc(o.doors_open_time || '') + '"></div>' +
      '</div>';
    card.querySelector('.ew-day-x').addEventListener('click', function() {
      card.remove();
      list.querySelectorAll('.ew-day-card').forEach(function(c, j) {
        c.querySelector('.ew-day-card-head strong').textContent = 'Day ' + (j + 1);
      });
    });
    list.appendChild(card);
  }
  window.EventsWorkspace.previewRecurrence = function() {
    var start = document.getElementById('ew-f-recur-start-date').value;
    var freq = document.getElementById('ew-f-recur-frequency').value;
    var interval = parseInt(document.getElementById('ew-f-recur-interval').value || '1', 10) || 1;
    var by = Array.from(document.querySelectorAll('.ew-recur-weekday:checked')).map(function(c) { return parseInt(c.value, 10); });
    var endType = document.getElementById('ew-f-recur-end-type').value;
    var count = parseInt(document.getElementById('ew-f-recur-count').value || '10', 10) || 10;
    var until = document.getElementById('ew-f-recur-until').value;
    var out = document.getElementById('ew-recurrence-preview');
    if (!start) { out.innerHTML = '<div class="ew-warning">Pick a first occurrence date first.</div>'; return; }
    var d = new Date(start + 'T00:00:00');
    var dates = [], guard = 0;
    while (guard++ < 200 && dates.length < count) {
      var wd = (d.getDay() + 6) % 7;
      if (freq === 'weekly') {
        if (by.length && by.indexOf(wd) === -1) { d.setDate(d.getDate() + 1); continue; }
      }
      if (endType === 'until' && until && d.toISOString().slice(0, 10) > until) break;
      dates.push(fmtDate(d.toISOString().slice(0, 10)));
      if (freq === 'daily') d.setDate(d.getDate() + interval);
      else if (by.length) d.setDate(d.getDate() + 1);
      else d.setDate(d.getDate() + interval * 7);
    }
    out.innerHTML = dates.map(function(x) { return '<span>' + x + '</span>'; }).join('') ||
      '<div class="ew-warning">This pattern produces no occurrences — check weekdays.</div>';
  };

  /* ── Media step ───────────────────────────────────────────── */
  window.EventsWorkspace.uploadCover = function(file) {
    if (!file) return;
    if (file.type && !/^image\/(jpeg|png|webp)$/.test(file.type)) { window.eccNotify('Use a JPEG, PNG or WebP image.'); return; }
    var id = document.getElementById('ew-f-event-id').value;
    var fd = new FormData();
    fd.append('csrf_token', (document.getElementById('events-workspace') || {}).dataset ? document.getElementById('events-workspace').dataset.csrf : '');
    fd.append('action', 'upload_cover');
    fd.append('file', file);
    var warn = document.getElementById('ew-cover-warning');
    warn.style.display = 'none';
    setSaveNote('Uploading cover…', 'err');
    fetch(apiRoot + 'event-media.php?event_id=' + encodeURIComponent(id), { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function(r) { return r.json().catch(function() { return {}; }); })
      .then(function(res) {
        if (!res || res.success !== true || !res.event) {
          setSaveNote('');
          warn.style.display = 'flex';
          warn.innerHTML = '⚠ ' + esc(apiError(res, 'Upload failed. Use a JPEG, PNG or WebP image.'));
          return;
        }
        state.event = normEvent(res.event);
        document.getElementById('ew-f-version').value = state.event.version || 0;
        var img = document.getElementById('ew-cover-preview');
        var ph = document.getElementById('ew-cover-placeholder');
        if (state.event.cover_image_url) {
          img.src = base + state.event.cover_image_url;
          img.style.display = 'block';
          ph.style.display = 'none';
        }
        tickSaved();
      });
  };
  window.EventsWorkspace.uploadGallery = function(file) {
    if (!file) return;
    if (file.type && !/^image\/(jpeg|png|webp)$/.test(file.type)) { window.eccNotify('Use a JPEG, PNG or WebP image.'); return; }
    var id = document.getElementById('ew-f-event-id').value;
    var fd = new FormData();
    fd.append('csrf_token', (document.getElementById('events-workspace') || {}).dataset ? document.getElementById('events-workspace').dataset.csrf : '');
    fd.append('action', 'upload_gallery');
    fd.append('file', file);
    fetch(apiRoot + 'event-media.php?event_id=' + encodeURIComponent(id), { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function(r) { return r.json().catch(function() { return {}; }); })
      .then(function(res) {
        if (!res || res.success !== true) { window.eccNotify(apiError(res, 'Gallery upload failed.')); return; }
        state.event = normEvent(res.event);
        document.getElementById('ew-f-version').value = state.event.version || 0;
        renderGallery();
        tickSaved();
      });
  };
  window.EventsWorkspace.removeGallery = function(imageId) {
    var id = document.getElementById('ew-f-event-id').value;
    var version = parseInt(document.getElementById('ew-f-version').value || '0', 10);
    post(apiRoot + 'event-media.php?event_id=' + encodeURIComponent(id), { action: 'remove_gallery', image_id: imageId, version: version })
      .then(function(res) {
        if (!res || res.success !== true) { window.eccNotify(apiError(res, 'Could not remove the image.')); return; }
        state.event = normEvent(res.event);
        document.getElementById('ew-f-version').value = state.event.version || 0;
        renderGallery();
      });
  };
  function renderGallery() {
    var grid = document.getElementById('ew-gallery-grid');
    if (!grid) return;
    var gallery = state.event.gallery || [];
    grid.innerHTML = gallery.map(function(g, gi) {
      var url = typeof g === 'string' ? g : (g.thumbnail || g.detail || g.original || '');
      var key = typeof g === 'string' ? g : (g.id || url);
      return '<div class="ew-gallery-tile' + (gi === 0 ? ' first' : '') + '">' +
        '<img src="' + esc(base + url) + '" alt="">' +
        '<button type="button" class="ew-gallery-x" onclick="EventsWorkspace.removeGallery(\'' + esc(String(key).replace(/[^a-zA-Z0-9\-_./]/g, '')) + '\')">✕</button></div>';
    }).join('') || '<div style="grid-column:1/-1;font-size:.72rem;color:var(--ecc-text-muted);padding:.4rem 0;">No gallery images yet.</div>';
  }

  /* ── Venue step ───────────────────────────────────────────── */
  function renderVenueStep() {
    renderVenueResults('');
    var sel = document.getElementById('ew-venue-selected');
    var pick = document.getElementById('ew-venue-picker');
    if (state.venuePicked) {
      sel.style.display = 'flex';
      pick.style.display = 'none';
      sel.innerHTML =
        '<div class="ev-venue-avatar">' + esc((state.venuePicked.name || 'V').charAt(0)) + '</div>' +
        '<div><strong>' + esc(state.venuePicked.name || '') + '</strong>' +
        '<small>' + esc([state.venuePicked.city, state.venuePicked.address].filter(Boolean).join(' · ')) + '</small></div>' +
        '<span class="ew-venue-check">✓</span>' +
        '<div style="display:flex;flex-direction:column;gap:.2rem;align-items:flex-end;">' +
        '<button type="button" class="ew-venue-swap" onclick="EventsWorkspace.changeVenue()">Change</button>' +
        (state.venuePicked.capacity ? '<small style="font-size:.62rem;color:var(--ecc-text-dim);">Capacity ' + Number(state.venuePicked.capacity).toLocaleString() + '</small>' : '') +
        '</div>';
    } else {
      sel.style.display = 'none';
      pick.style.display = 'block';
    }
  }
  window.EventsWorkspace.changeVenue = function() {
    state.venuePicked = null;
    renderVenueStep();
  };
  function renderVenueResults(q) {
    var box = document.getElementById('ew-venue-results');
    if (!box) return;
    box.innerHTML = '<div style="font-size:.72rem;color:var(--ecc-text-muted);padding:.3rem 0;">Loading venues…</div>';
    getJson(apiRoot + 'venues.php?search=' + encodeURIComponent(q || '')).then(function(res) {
      if (!res || res.success !== true) { box.innerHTML = '<div class="ew-warning">Could not load venues.</div>'; return; }
      var venues = (res.venues && (res.venues.venues || res.venues)) || [];
      if (!venues.length) {
        box.innerHTML = '<div style="font-size:.72rem;color:var(--ecc-text-muted);padding:.3rem 0;">No venues found. Add a new venue or type a different search.</div>';
        return;
      }
      box.innerHTML = '';
      venues.forEach(function(ven) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'ew-venue-result';
        b.innerHTML = '<div style="min-width:0;flex:1;"><strong>' + esc(ven.name) + '</strong>' +
          '<small>' + esc([ven.city, ven.address].filter(Boolean).join(' · ')) + '</small></div>' +
          (ven.capacity ? '<span class="ev-venue-cap">' + Number(ven.capacity).toLocaleString() + ' seats</span>' : '') +
          (ven.verification_status === 'VERIFIED' ? '<span style="color:#10b981;font-size:.7rem;font-weight:800;">✓ Verified</span>' : '');
        b.addEventListener('click', function() {
          state.venuePicked = ven;
          renderVenueStep();
        });
        box.appendChild(b);
      });
    });
  }
  window.EventsWorkspace.toggleNewVenueForm = function() {
    var f = document.getElementById('ew-new-venue-form');
    f.style.display = f.style.display === 'none' ? 'block' : 'none';
  };
  window.EventsWorkspace.createVenue = function() {
    var name = document.getElementById('ew-f-venue-name').value.trim();
    if (!name) { window.eccNotify('Venue name is required.'); return; }
    var capacity = document.getElementById('ew-f-venue-capacity').value.trim();
    if (capacity !== '' && (!/^\d+$/.test(capacity) || +capacity < 1 || +capacity > 1000000)) {
      window.eccNotify('Capacity must be a whole number between 1 and 1,000,000.');
      return;
    }
    /* Strip degree symbols, compass letters, and whitespace from GPS coordinates
       e.g. "-13.9438°" → "-13.9438"   "33.7531° E" → "33.7531"  */
    function cleanGps(val) {
      return val.replace(/[°'"]/g, '').replace(/\s*[NnSsEeWw]\s*$/g, '').trim();
    }
    var lat = cleanGps(document.getElementById('ew-f-venue-lat').value);
    var lng = cleanGps(document.getElementById('ew-f-venue-lng').value);
    /* South latitudes and West longitudes may also be expressed as positive numbers
       with a compass suffix — convert them to signed decimals. */
    var latRaw = document.getElementById('ew-f-venue-lat').value;
    var lngRaw = document.getElementById('ew-f-venue-lng').value;
    if (/[Ss]/.test(latRaw) && parseFloat(lat) > 0) lat = String(-parseFloat(lat));
    if (/[Ww]/.test(lngRaw) && parseFloat(lng) > 0) lng = String(-parseFloat(lng));
    if (lat !== '' && (isNaN(parseFloat(lat)) || parseFloat(lat) < -90 || parseFloat(lat) > 90)) {
      window.eccNotify('Latitude must be a number between -90 and 90.');
      return;
    }
    if (lng !== '' && (isNaN(parseFloat(lng)) || parseFloat(lng) < -180 || parseFloat(lng) > 180)) {
      window.eccNotify('Longitude must be a number between -180 and 180.');
      return;
    }
    var payload = {
      action: 'create_venue', name: name,
      city: document.getElementById('ew-f-venue-city').value.trim(),
      address: document.getElementById('ew-f-venue-address').value.trim(),
      capacity: capacity,
      gps_lat: lat,
      gps_lng: lng
    };
    post(apiRoot + 'venues.php', payload).then(function(res) {
      if (!res || res.success !== true || !res.venue) { window.eccNotify(apiError(res, 'Could not create the venue.')); return; }
      state.venuePicked = res.venue.venue || res.venue;
      document.getElementById('ew-new-venue-form').style.display = 'none';
      renderVenueStep();
      window.eccNotify('Venue created & linked.');
      document.getElementById('ew-f-venue-name').value = '';
      document.getElementById('ew-f-venue-city').value = '';
      document.getElementById('ew-f-venue-address').value = '';
      document.getElementById('ew-f-venue-capacity').value = '';
      document.getElementById('ew-f-venue-lat').value = '';
      document.getElementById('ew-f-venue-lng').value = '';
    });
  };

  /* ── Description step ─────────────────────────────────────── */
  function renderHighlights() {
    var list = document.getElementById('ew-highlights-list');
    if (!list) return;
    state.highlights = state.event.highlights || [];
    list.innerHTML = state.highlights.map(function(h, i) {
      return '<div class="ew-highlight-chip"><span>✓</span>' + esc(h) +
        '<button type="button" class="ew-hl-x" onclick="EventsWorkspace.removeHighlight(' + i + ')">✕</button></div>';
    }).join('') || '';
    var inp = document.getElementById('ew-highlight-input');
    if (inp && inp.value) { state.highlights.push(inp.value.trim()); inp.value = ''; }
  }
  window.EventsWorkspace.addHighlight = function() {
    var inp = document.getElementById('ew-highlight-input');
    var t = inp.value.trim();
    if (!t) { window.eccNotify('Type a highlight first, then press Add.'); return; }
    state.highlights = state.highlights || [];
    if (state.highlights.length >= 12) { window.eccNotify('Maximum 12 highlights.'); return; }
    state.highlights.push(t);
    inp.value = '';
    var list = document.getElementById('ew-highlights-list');
    list.insertAdjacentHTML('beforeend', '<div class="ew-highlight-chip"><span>✓</span>' + esc(t) +
      '<button type="button" class="ew-hl-x" onclick="EventsWorkspace.removeHighlight(' + (state.highlights.length - 1) + ')">✕</button></div>');
  };
  window.EventsWorkspace.removeHighlight = function(i) {
    state.highlights.splice(i, 1);
    renderHighlights();
  };

  /* ── Tickets step ─────────────────────────────────────────── */
  function renderTickets() {
    var box = document.getElementById('ew-tickets-list');
    if (!box) return;
    state.tickets = state.event.ticket_types || [];
    var active = state.tickets.filter(function(t) { return t.is_active; });
    box.innerHTML = active.map(function(t) {
      var sold = (parseInt(t.total_quantity, 10) || 0) - (parseInt(t.remaining_quantity, 10) || 0);
      var line = '<div class="ew-ticket-card">' +
        '<div class="ew-tk-name">' + esc(t.name) + '</div>' +
        '<div class="ew-tk-price">' + money(t.price) + '</div>' +
        '<div class="ew-tk-qty">' + sold.toLocaleString() + ' sold · ' + Number(t.total_quantity).toLocaleString() + ' issued</div>' +
        '<div class="ew-tk-badges">' +
        (t.refundable ? '<span class="ev-chip-count" style="background:rgba(16,185,129,.12);color:#10b981;">Refundable</span>' : '') +
        (t.transferable ? '<span class="ev-chip-count" style="background:rgba(139,92,246,.12);color:#8b5cf6;">Transferable</span>' : '') +
        (t.tier !== 'other' ? '<span class="ev-chip-count" style="background:rgba(230,57,70,.1);color:var(--ecc-primary);text-transform:uppercase;">' + esc(t.tier) + '</span>' : '') +
        '</div>' +
        '<div class="ew-tk-actions">' +
        '<button type="button" class="ew-tk-iconbtn" title="Edit" onclick="EventsWorkspace.editTicket(' + t.id + ')">✎</button>' +
        '<button type="button" class="ew-tk-iconbtn danger" title="Delete" onclick="EventsWorkspace.deleteTicket(' + t.id + ')">🗑</button>' +
        '</div></div>';
      return line;
    }).join('') || '<div style="font-size:.78rem;color:var(--ecc-text-muted);padding:.5rem 0;">No ticket types yet — add your first ticket below.</div>';
    renderPricingPreview();
  }
  window.EventsWorkspace.selectTicketTemplate = function(tpl) {
    var card = document.querySelector('#ew-tb-tpl-picker .ew-tpl-card[data-tpl="' + tpl + '"]');
    if (!card) return;
    document.querySelectorAll('#ew-tb-tpl-picker .ew-tpl-card').forEach(function(c) { c.classList.remove('active'); });
    card.classList.add('active');
    document.getElementById('ew-tb-template').value = tpl;
    document.getElementById('ew-tb-category').value = card.getAttribute('data-category') || '';
    if (tpl === 'vip') document.getElementById('ew-tb-tier').value = 'vip';
    else if (document.getElementById('ew-tb-tier').value === 'vip') document.getElementById('ew-tb-tier').value = 'standard';
    document.querySelectorAll('#ew-ticket-preview .ew-pv-slide').forEach(function(s) {
      s.classList.toggle('active', s.getAttribute('data-tpl') === tpl);
    });
    window.EventsWorkspace.updateTicketPreview();
    EventsWorkspace.hydratePreviewQr();
  };
  window.EventsWorkspace.updateTicketPreview = function() {
    var slide = document.querySelector('#ew-ticket-preview .ew-pv-slide.active');
    if (!slide) return;
    var name = document.getElementById('ew-tb-name').value.trim();
    var price = document.getElementById('ew-tb-price').value.trim();
    var evTitle = document.getElementById('ew-f-title').value.trim();
    var set = function(key, val) {
      var el = slide.querySelector('[data-pv="' + key + '"]');
      if (el && val !== '') el.textContent = val;
    };
    if (name) set('ticket_name', name.toUpperCase());
    if (evTitle) set('event_title', evTitle.toUpperCase());
  };
  window.EventsWorkspace.hydratePreviewQr = function() {
    var slide = document.querySelector('#ew-ticket-preview .ew-pv-slide.active');
    if (!slide) return;
    var qrBox = slide.querySelector('.uth-tk-qr');
    var inner = slide.querySelector('.uth-tk-qr-inner');
    if (!qrBox || !inner || typeof qrcode === 'undefined') return;
    var payload = String(qrBox.getAttribute('data-qr') || '');
    try {
      var qr = qrcode(0, 'M');
      qr.addData(payload);
      qr.make();
      inner.innerHTML = qr.createSvgTag(3, 0);
    } catch (err) { inner.textContent = 'QR'; }
  };
  window.EventsWorkspace.openTicketBuilder = function() {
    var id = document.getElementById('ew-f-event-id').value;
    if (!id) { window.eccNotify('Save event identity before adding tickets.'); return; }
    document.getElementById('ew-tb-id').value = '';
    document.getElementById('ew-tb-name').value = '';
    document.getElementById('ew-tb-price').value = '';
    document.getElementById('ew-tb-quantity').value = '';
    document.getElementById('ew-tb-sale-start').value = '';
    document.getElementById('ew-tb-sale-end').value = '';
    document.getElementById('ew-tb-tier').value = 'standard';
    document.getElementById('ew-tb-access').value = '';
    document.getElementById('ew-tb-transferable').checked = true;
    document.getElementById('ew-tb-refundable').checked = true;
    EventsWorkspace.selectTicketTemplate('general');
    document.getElementById('ew-ticket-builder').style.display = 'block';
    document.getElementById('ew-ticket-builder').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };
  window.EventsWorkspace.closeTicketBuilder = function() {
    document.getElementById('ew-ticket-builder').style.display = 'none';
  };
  window.EventsWorkspace.saveTicketType = function() {
    var id = document.getElementById('ew-f-event-id').value;
    var name = document.getElementById('ew-tb-name').value.trim();
    if (!name) { window.eccNotify('Ticket name is required.'); return; }
    var payload = {
      action: 'save_ticket_type',
      name: name,
      price: document.getElementById('ew-tb-price').value || '0',
      total_quantity: document.getElementById('ew-tb-quantity').value || '0',
      sale_start: document.getElementById('ew-tb-sale-start').value,
      sale_end: document.getElementById('ew-tb-sale-end').value,
      tier: document.getElementById('ew-tb-tier').value,
      category: document.getElementById('ew-tb-category').value,
      access_scope: document.getElementById('ew-tb-access').value.trim(),
      transferable: document.getElementById('ew-tb-transferable').checked,
      refundable: document.getElementById('ew-tb-refundable').checked
    };
    var tid = document.getElementById('ew-tb-id').value;
    if (tid) payload.id = parseInt(tid, 10);
    setBusy(true);
    post(apiRoot + 'event.php?event_id=' + encodeURIComponent(id), payload).then(function(res) {
      setBusy(false);
      if (!res || res.success !== true || !res.event) { window.eccNotify(apiError(res, 'Could not save the ticket type.')); return; }
      state.event = normEvent(res.event);
      document.getElementById('ew-f-version').value = state.event.version || 0;
      document.getElementById('ew-ticket-builder').style.display = 'none';
      renderTickets();
      tickSaved();
    });
  };
  window.EventsWorkspace.editTicket = function(tid) {
    var t = state.tickets.find(function(x) { return x.id === tid; });
    if (!t) return;
    document.getElementById('ew-tb-id').value = t.id;
    document.getElementById('ew-tb-name').value = t.name || '';
    document.getElementById('ew-tb-price').value = t.price;
    document.getElementById('ew-tb-quantity').value = t.total_quantity;
    document.getElementById('ew-tb-sale-start').value = t.sale_start || '';
    document.getElementById('ew-tb-sale-end').value = t.sale_end || '';
    document.getElementById('ew-tb-tier').value = t.tier || 'standard';
    document.getElementById('ew-tb-access').value = t.access_scope || '';
    document.getElementById('ew-tb-transferable').checked = !!t.transferable;
    document.getElementById('ew-tb-refundable').checked = !!t.refundable;
    var categoryToTpl = { 'VIP': 'vip', 'VVIP': 'vvip', 'Early Bird': 'early_bird', 'General Admission': 'general', 'Group': 'group', 'Season Pass': 'season' };
    var tpl = categoryToTpl[t.category] || (t.tier === 'vip' ? 'vip' : 'general');
    EventsWorkspace.selectTicketTemplate(tpl);
    document.getElementById('ew-ticket-builder').style.display = 'block';
    document.getElementById('ew-ticket-builder').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };
  window.EventsWorkspace.deleteTicket = function(tid) {
    if (!window.confirm('Delete this ticket type? This cannot be undone.')) return;
    var id = document.getElementById('ew-f-event-id').value;
    post(apiRoot + 'event.php?event_id=' + encodeURIComponent(id), { action: 'delete_ticket_type', ticket_id: tid }).then(function(res) {
      if (!res || res.success !== true) { window.eccNotify(apiError(res, 'Could not delete the ticket type.')); return; }
      state.event = normEvent(res.event);
      document.getElementById('ew-f-version').value = state.event.version || 0;
      renderTickets();
    });
  };
  function renderPricingPreview() {
    var pre = document.getElementById('ew-ticket-pricing-preview');
    if (!pre) return;
    var active = state.tickets.filter(function(t) { return t.is_active; });
    if (!active.length) { pre.innerHTML = ''; return; }
    var from = Math.min.apply(null, active.map(function(t) { return Number(t.price) || 0; }));
    var totalQty = active.reduce(function(s, t) { return s + (parseInt(t.total_quantity, 10) || 0); }, 0);
    var soldQty = active.reduce(function(s, t) { return s + (parseInt(t.total_quantity, 10) || 0) - (parseInt(t.remaining_quantity, 10) || 0); }, 0);
    pre.innerHTML =
      '<div class="ew-pricing-preview-row"><span>Customer pays from</span><strong>' + money(from) + '</strong></div>' +
      '<div class="ew-pricing-preview-row"><span>Uthenga service fee</span><strong style="color:var(--ecc-primary);">Configured by platform</strong></div>' +
      '<div class="ew-pricing-preview-row"><span>Total inventory</span><strong>' + totalQty.toLocaleString() + ' tickets</strong></div>' +
      '<div class="ew-pricing-preview-row hl"><span>Projected sell-through</span><strong>' + Math.round(totalQty ? (soldQty / totalQty) * 100 : 0) + '%</strong></div>';
  }

  /* ── Policies step ────────────────────────────────────────── */
  function renderPolicies() {
    var p = state.event.policies || {};
    var sel = document.querySelector('input[name="ew-refund-policy"][value="' + (p.refund_policy || 'refund_before_event') + '"]');
    if (sel) sel.checked = true;
    document.getElementById('ew-f-refund-custom').value = p.refund_custom_text || '';
    document.getElementById('ew-f-refund-custom').style.display = (p.refund_policy === 'custom') ? 'block' : 'none';
    document.getElementById('ew-f-transfer-allowed').checked = p.transfer_allowed !== false;
    document.getElementById('ew-f-id-verification').checked = !!p.id_verification_required;
    var age = document.querySelector('input[name="ew-age-restriction"][value="' + (p.age_restriction || 'none') + '"]');
    if (age) age.checked = true;
  }

  /* ── Customer preview ─────────────────────────────────────── */
  function renderCustomerPreview() {
    var box = document.getElementById('ew-customer-preview');
    if (!box) return;
    var e = state.event;
    var tickets = state.event.ticket_types || [];
    var from = tickets.length ? Math.min.apply(null, tickets.filter(function(t) { return t.is_active; }).map(function(t) { return Number(t.price) || 0; })) : null;
    var cover = e.cover_image_url ? '<img src="' + esc(base + e.cover_image_url) + '" alt="">' : '<div class="ew-cust-ph"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><circle cx="9" cy="9" r="2"/><path d="M21 16l-5-5L5 21"/></svg></div>';
    box.innerHTML =
      '<div class="ew-cust-card">' +
      '<div class="ew-cust-cover">' + cover + '</div>' +
      '<div class="ew-cust-body">' +
      '<div class="ew-cust-title">' + esc(e.title || 'Untitled Event') + '</div>' +
      '<div class="ew-cust-meta">' + (e.start_date ? '📅 ' + fmtDate(e.start_date) : '📅 —') +
      (e.start_time ? ' · 🕘 ' + fmtTime(e.start_time) : '') + '</div>' +
      '<div class="ew-cust-meta">📍 ' + esc((state.venuePicked && (state.venuePicked.city || state.venuePicked.name)) || 'Venue TBC') + '</div>' +
      '<div class="ew-cust-price">From <strong>' + (from !== null ? money(from) : '—') + '</strong></div>' +
      '<button type="button" class="ew-cust-cta">Get Tickets</button>' +
      '</div></div>' +
      '<div class="ew-cust-detail">' +
      '<h4>Event Facts</h4>' +
      '<div class="ew-cust-fact"><span>Organizer</span><span>' + esc(e.organizer_display_name || 'You') + ('.') + '</span></div>' +
      '<div class="ew-cust-fact"><span>Category</span><span>' + esc(e.category || '—') + '</span></div>' +
      '<div class="ew-cust-fact"><span>Doors open</span><span>' + fmtTime(e.doors_open_time || e.start_time) + '</span></div>' +
      '<div class="ew-cust-fact"><span>End</span><span>' + (e.end_time ? fmtTime(e.end_time) : '—') + '</span></div>' +
      '<h4 style="margin-top:.8rem;">Tickets</h4>' +
      '<div class="ew-cust-tickets">' +
      (tickets.filter(function(t) { return t.is_active; }).map(function(t) {
        var sold = (parseInt(t.total_quantity, 10) || 0) - (parseInt(t.remaining_quantity, 10) || 0);
        return '<div class="ew-cust-ticket-row"><span>' + esc(t.name) + '</span>' +
          '<span class="ew-cust-tk-price">' + money(t.price) + '</span>' +
          '<span class="ew-cust-tk-avail">' + (parseInt(t.remaining_quantity, 10) || 0).toLocaleString() + ' left</span></div>';
      }).join('') || '<div style="font-size:.74rem;color:var(--ecc-text-muted);">No ticket types configured yet.</div>') +
      '</div></div>';
  }

  /* ── Review & publish ─────────────────────────────────────── */
  function renderChecklist() {
    var box = document.getElementById('ew-checklist');
    var ok = document.getElementById('ew-publish-success');
    ok.style.display = 'none';
    if (!box) return;
    var cp = state.event.can_publish || { ready: true, issues: [] };
    var steps = [
      ['identity', 'Event identity'], ['media', 'Event imagery'], ['schedule', 'Event schedule'],
      ['venue', 'Venue'], ['description', 'Description'], ['tickets', 'Ticket configuration'],
      ['policies', 'Refund & entry policies']
    ];
    var issueMap = {};
    (cp.issues || []).forEach(function(i) { issueMap[i.step] = i.message; });
    var stepNum = { identity: 1, media: 2, schedule: 3, venue: 4, description: 5, tickets: 6, policies: 7 };
    box.innerHTML = steps.map(function(s) {
      var bad = issueMap[s[0]];
      return '<div class="ew-check-item ' + (bad ? 'fail' : 'ok') + '">' +
        '<span class="ew-ci-ico">' + (bad ? '!' : '✓') + '</span>' +
        '<span>' + esc(s[1]) + (bad ? ' — <span style="color:#ef4444;font-weight:600;font-size:.72rem;">' + esc(bad) + '</span>' : '') + '</span>' +
        '<button type="button" class="ev-fixtag" style="border:none;background:transparent;cursor:pointer;font-family:inherit;" onclick="EventsWorkspace.goToStep(' + stepNum[s[0]] + ')">' + (bad ? 'Fix' : 'Good') + '</button>' +
        '</div>';
    }).join('');
    if (cp.ready) {
      box.insertAdjacentHTML('beforeend', '<div class="ew-publish-ready">✓ Ready to publish — your event meets all requirements.</div>');
    }
  }
  window.EventsWorkspace.publishDirect = window.EventsWorkspace.publish;

  /* ── Manage modal (lifecycle-aware) ───────────────────────── */
  window.EventsWorkspace.manage = function(id, focusPublish) {
    var e = state.events.find(function(x) { return x.id === id; });
    if (!e) { window.EventsWorkspace.edit(id); return; }
    document.getElementById('ew-manage-title').textContent = e.title || 'Manage Event';
    document.getElementById('ew-manage-sub').innerHTML =
      '<span class="ev-card-status"><span class="ev-status-dot ' + esc(e.lifecycle === 'upcoming' && e.status !== 'pending' ? 'published' : e.lifecycle) + '"></span>' +
      esc(statusLabel(e.lifecycle === 'upcoming' ? (e.status === 'pending' ? 'pending' : 'published') : e.lifecycle)) + '</span>' +
      ' &nbsp;·&nbsp; <span style="font-weight:600;color:var(--ecc-text-muted);">' + esc(e.id) + '</span>' +
      (e.start_date ? ' &nbsp;·&nbsp; ' + fmtDate(e.start_date) : '');
    var acts = [];
    acts.push({ label: 'Edit Event', ico: '✎', cls: 'primary', act: function() { window.EventsWorkspace.edit(id); } });
    if (e.status === 'draft' || e.status === 'paused') {
      acts.push({ label: 'Publish Event', ico: '🚀', cls: 'primary', act: function() {
        getJson(apiRoot + 'event.php?event_id=' + encodeURIComponent(id)).then(function(res) {
          if (res && res.success === true && res.event && res.event.can_publish && !res.event.can_publish.ready) {
            window.EventsWorkspace.edit(id);
            window.eccNotify('Complete the checklist before publishing.');
            var box = document.getElementById('ew-checklist');
            var cp = res.event.can_publish;
            box.innerHTML = (cp.issues || []).map(function(i) {
              return '<div class="ew-check-item fail"><span class="ew-ci-ico">!</span><span>' + esc(i.message) + '</span></div>';
            }).join('');
            document.getElementById('ew-publish-success').style.display = 'none';
            setStepUi(9);
            return;
          }
          window.EventsWorkspace.edit(id);
          setTimeout(function() { setStepUi(9); }, 120);
        });
      } });
    }
    acts.push({ label: 'Duplicate', ico: '⧉', act: function() { window.EventsWorkspace.duplicate(id); } });
    acts.push({ label: 'Preview Customer Page', ico: '👁', act: function() { window.EventsWorkspace.viewCustomer(id); } });
    if (e.status === 'published') {
      acts.push({ label: 'Unpublish', ico: '⏸', act: function() { window.EventsWorkspace.unpublish(id); } });
      acts.push({ label: 'Cancel Event', ico: '✕', cls: 'danger', act: function() { window.EventsWorkspace.cancel(id); } });
    }
    if (e.status === 'draft') {
      acts.push({ label: 'Delete Draft', ico: '🗑', cls: 'danger', act: function() { window.EventsWorkspace.deleteDraft(id); } });
    }
    if (e.status !== 'draft' && e.status !== 'cancelled') {
      acts.push({ label: 'Archive', ico: '🗄', act: function() { window.EventsWorkspace.archive(id); } });
    }
    var box = document.getElementById('ew-manage-actions');
    box.innerHTML = '';
    acts.forEach(function(a) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'ew-manage-action ' + (a.cls || '');
      b.innerHTML = '<span>' + a.ico + '</span>' + esc(a.label);
      b.addEventListener('click', function() { closeModal('modal-event-manage'); a.act(); });
      box.appendChild(b);
    });
    var warn = document.getElementById('ew-manage-warning');
    if (e.status === 'published') {
      warn.style.display = 'flex';
      warn.innerHTML = '⚠ Changes to date, venue or ticket pricing may affect existing ticket holders.';
    } else {
      warn.style.display = 'none';
    }
    openModal('modal-event-manage');
  };
  window.EventsWorkspace.closeManage = function() { closeModal('modal-event-manage'); };

  /* ── Lifecycle actions ────────────────────────────────────── */
  function lif(id) { return apiRoot + 'event.php?event_id=' + encodeURIComponent(id); }
  function versionOf(id) {
    var e = state.events.find(function(x) { return x.id === id; });
    return e ? (e.version || 0) : 0;
  }
  window.EventsWorkspace.unpublish = function(id) {
    if (!window.confirm('Unpublish this event? It will no longer appear in the customer marketplace.')) return;
    post(lif(id), { action: 'unpublish', version: versionOf(id) }).then(function(res) {
      if (!res || res.success !== true) { window.eccNotify(apiError(res, 'Could not unpublish the event.')); return; }
      window.eccNotify('Event unpublished.');
      window.EventsWorkspace.load();
    });
  };
  window.EventsWorkspace.cancel = function(id) {
    if (!window.confirm('Cancel this event? This is a significant action visible to ticket holders.')) return;
    post(lif(id), { action: 'cancel', version: versionOf(id) }).then(function(res) {
      if (!res || res.success !== true) { window.eccNotify(apiError(res, 'Could not cancel the event.')); return; }
      window.eccNotify('Event cancelled.');
      window.EventsWorkspace.load();
    });
  };
  window.EventsWorkspace.archive = function(id) {
    if (!window.confirm('Archive this event? It moves out of the active catalogue.')) return;
    post(lif(id), { action: 'archive', version: versionOf(id) }).then(function(res) {
      if (!res || res.success !== true) { window.eccNotify(apiError(res, 'Could not archive the event.')); return; }
      window.eccNotify('Event archived.');
      window.EventsWorkspace.load();
    });
  };
  window.EventsWorkspace.deleteDraft = function(id) {
    if (!window.confirm('Permanently delete this draft? This cannot be undone.')) return;
    post(lif(id), { action: 'delete_draft', version: versionOf(id) }).then(function(res) {
      if (!res || res.success !== true) { window.eccNotify(apiError(res, 'Could not delete the draft.')); return; }
      var raw = null;
      try { raw = JSON.parse(localStorage.getItem('ev-resume-draft') || 'null'); } catch (e) {}
      if (raw && raw.id === id) { try { localStorage.removeItem('ev-resume-draft'); } catch (e) {} showResumeBanner(null); }
      window.eccNotify('Draft deleted.');
      window.EventsWorkspace.load();
    });
  };

  /* ── Duplicate ────────────────────────────────────────────── */
  window.EventsWorkspace.duplicate = function(id) {
    var e = state.events.find(function(x) { return x.id === id; });
    if (!e) { window.eccNotify('Event not found.'); return; }
    document.getElementById('ew-dup-source-id').value = id;
    document.getElementById('ew-dup-title').value = e.title + ' (Copy)';
    document.getElementById('ew-dup-start-date').value = '';
    openModal('modal-duplicate-event');
  };
  window.EventsWorkspace.closeDuplicate = function() { closeModal('modal-duplicate-event'); };
  window.EventsWorkspace.confirmDuplicate = function() {
    var src = document.getElementById('ew-dup-source-id').value;
    var payload = {
      action: 'duplicate',
      title: document.getElementById('ew-dup-title').value.trim(),
      start_date: document.getElementById('ew-dup-start-date').value,
      copy_description: document.getElementById('ew-dup-copy-description').checked,
      copy_media: document.getElementById('ew-dup-copy-media').checked,
      copy_tickets: document.getElementById('ew-dup-copy-tickets').checked,
      copy_pricing: document.getElementById('ew-dup-copy-pricing').checked
    };
    if (!payload.title) { window.eccNotify('Give the copy a name.'); return; }
    post(lif(src), payload).then(function(res) {
      if (!res || res.success !== true || !res.event) { window.eccNotify(apiError(res, 'Could not duplicate the event.')); return; }
      closeModal('modal-duplicate-event');
      window.eccNotify('Draft copy created — ' + (res.event.event || res.event).id);
      window.EventsWorkspace.load();
    });
  };

  /* ── Wire wizard events ───────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('ew-schedule-mode-toggle').querySelectorAll('.ew-mode-btn').forEach(function(b) {
      b.addEventListener('click', function() { window.EventsWorkspace.setScheduleMode(b.dataset.mode); });
    });

    var coverInput = document.getElementById('ew-cover-input');
    if (coverInput) coverInput.addEventListener('change', function() { window.EventsWorkspace.uploadCover(coverInput.files[0]); });
    var galInput = document.getElementById('ew-gallery-input');
    if (galInput) galInput.addEventListener('change', function() {
      Array.from(galInput.files).forEach(function(f) { window.EventsWorkspace.uploadGallery(f); });
    });
    var dz = document.getElementById('ew-cover-drop');
    if (dz) {
      ['dragenter', 'dragover'].forEach(function(evt) { dz.addEventListener(evt, function(e) { e.preventDefault(); dz.classList.add('drag'); }); });
      ['dragleave', 'drop'].forEach(function(evt) { dz.addEventListener(evt, function(e) { e.preventDefault(); dz.classList.remove('drag'); }); });
      dz.addEventListener('drop', function(e) {
        var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) window.EventsWorkspace.uploadCover(f);
      });
    }
    var venueSearch = document.getElementById('ew-venue-search');
    if (venueSearch) venueSearch.addEventListener('input', function() {
      clearTimeout(state.searchTimer);
      state.searchTimer = setTimeout(function() { renderVenueResults(venueSearch.value.trim()); }, 260);
    });
    document.querySelectorAll('input[name="ew-refund-policy"]').forEach(function(r) {
      r.addEventListener('change', function() {
        document.getElementById('ew-f-refund-custom').style.display = r.value === 'custom' ? 'block' : 'none';
      });
    });

    var rEnd = document.getElementById('ew-f-recur-end-type');
    if (rEnd) rEnd.addEventListener('change', function() {
      var count = document.getElementById('ew-f-recur-count');
      var until = document.getElementById('ew-f-recur-until');
      if (rEnd.value === 'until') { count.style.display = 'none'; until.style.display = 'block'; }
      else { count.style.display = 'block'; until.style.display = 'none'; }
    });
    var rFreq = document.getElementById('ew-f-recur-frequency');
    if (rFreq) rFreq.addEventListener('change', function() {
      document.getElementById('ew-recur-weekday-row').style.display = rFreq.value === 'weekly' ? 'flex' : 'none';
    });

    var titleInput = document.getElementById('ew-f-title');
    if (titleInput) titleInput.addEventListener('input', function() {
      var slug = document.getElementById('ew-f-slug');
      if (slug && !slug.dataset.touched) {
        slug.value = String(titleInput.value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
      }
    });
    var slugInput = document.getElementById('ew-f-slug');
    if (slugInput) slugInput.addEventListener('input', function() { slugInput.dataset.touched = '1'; });

    var tplPicker = document.getElementById('ew-tb-tpl-picker');
    if (tplPicker) {
      tplPicker.addEventListener('click', function(e) {
        var card = e.target.closest('.ew-tpl-card');
        if (card) window.EventsWorkspace.selectTicketTemplate(card.getAttribute('data-tpl'));
      });
    }
    ['ew-tb-name', 'ew-tb-price', 'ew-f-title'].forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', function() { window.EventsWorkspace.updateTicketPreview(); });
    });
    window.EventsWorkspace.hydratePreviewQr();

    (function() {
      var raw = null;
      try { raw = JSON.parse(localStorage.getItem('ev-resume-draft') || 'null'); } catch (e) {}
      if (raw && raw.id) {
        getJson(apiRoot + 'event.php?event_id=' + encodeURIComponent(raw.id)).then(function(res) {
          if (res && res.success === true && res.event) showResumeBanner(res.event.event || res.event);
        }).catch(function() {});
      }
    })();
  });
})();
</script>
<script src="<?= BASE_URL ?>assets/js/finance-console.js"></script>
<script src="<?= BASE_URL ?>assets/js/analytics-console.js"></script>
<script src="<?= BASE_URL ?>assets/js/messages-console.js"></script>
<script src="<?= BASE_URL ?>assets/js/documents-console.js?v=<?= rawurlencode(APP_VERSION) ?>-events-docs-1"></script>
<script src="<?= BASE_URL ?>assets/js/tie-location.js"></script>
<script src="<?= BASE_URL ?>assets/js/venues-console.js?v=<?= rawurlencode(APP_VERSION) ?>-events-venue-fix-4"></script>
<script src="<?= BASE_URL ?>assets/js/events-marketing-actions.js?v=<?= rawurlencode(APP_VERSION) ?>-marketing-actions-1"></script>
<script>
/* Last-resort in-document bridge: this page is no-store, so campaign controls
   remain live even if a browser or proxy fails to fetch a cached asset. */
(function () {
  if (window.__uthengaMarketingInlineBridgeBound) return;
  window.__uthengaMarketingInlineBridgeBound = true;

  if (typeof window.UthengaMarketingAction !== 'function') {
    window.UthengaMarketingAction = function (action, target, preset, button) {
      var marketing = window.MarketingControlCenter;
      if (!marketing) {
        if (typeof window.eccNotify === 'function') window.eccNotify('Marketing controls are still loading. Please refresh and try again.');
        return;
      }
      if (action === 'tab') return marketing.switchTab(target, button);
      if (action === 'campaign-create') return target && !preset ? marketing.promoteEvent(target) : marketing.openCreateWizard(preset || undefined);
      if (action === 'campaign-investigate') return marketing.investigateCampaign(target || '');
      if (action === 'campaign-view') return marketing.viewCampaign(target);
      if (action === 'campaign-toggle') return marketing.toggleCampaignStatus(target);
      if (action === 'promotion-create') return marketing.openPromoModal();
      if (action === 'promotion-manage') return marketing.managePromotion(target);
      if (action === 'promotion-toggle') return marketing.togglePromotionStatus(target);
      if (action === 'promo-code-create') return marketing.openPromoCodeModal();
      if (action === 'ad-generate') return marketing.generateAiCardCopy();
      if (action === 'ad-save') return marketing.saveAdCard();
      if (action === 'ai-toggle') return marketing.toggleAiPanel();
    };
  }

  document.addEventListener('click', function (event) {
    var source = event.target && event.target.closest ? event.target.closest('[data-mkt-action]') : null;
    if (!source) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    window.UthengaMarketingAction(
      source.getAttribute('data-mkt-action'),
      source.getAttribute('data-mkt-target') || '',
      source.getAttribute('data-mkt-preset') || '',
      source
    );
  }, true);
}());
</script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
