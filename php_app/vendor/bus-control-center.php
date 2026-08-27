<?php
/**
 * Uthenga - Bus Operations Control Center
 * Enterprise Operating Console for Bus & Coach Service Vendors
 * Designed exactly as specified in the reference layout with Reddish Primary Color Palette.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/tie/bootstrap.php';

requireApprovedVendor();

$pageTitle = 'Bus Operations';
$vendorId = (string) ($_SESSION['user_id'] ?? '');
$vendor = dbQueryOne('SELECT * FROM users WHERE id = ?', [$vendorId]) ?: [];
$validTabs = ['overview', 'buses', 'routes', 'trips', 'tickets', 'boarding', 'passengers', 'drivers', 'maintenance', 'revenue', 'analytics', 'reports', 'company', 'users'];
$activeTab = in_array($_GET['tab'] ?? '', $validTabs, true) ? $_GET['tab'] : 'overview';
$csrfToken = (string) ($_SESSION['csrf_token'] ?? '');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bus Operations Control Center — Uthenga Vendor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bus-ticket-templates.css?v=<?= rawurlencode(APP_VERSION) ?>">
  <style>
    
    :root {
      --boc-bg: #070a12;
      --boc-sidebar: #0b0f19;
      --boc-card: #0f1523;
      --boc-card-hover: #161e31;
      --boc-border: rgba(255, 255, 255, 0.07);
      --boc-text: #f8fafc;
      --boc-text-soft: #94a3b8;
      --boc-text-muted: #64748b;
      
      /* Primary Brand Color: Uthenga Reddish Crimson */
      --boc-primary: #e63946;
      --boc-primary-hover: #c52a36;
      --boc-primary-rgb: 230, 57, 70;

      /* Secondary Chart & Status Colors */
      --boc-blue: #3b82f6;
      --boc-green: #10b981;
      --boc-purple: #8b5cf6;
      --boc-orange: #f59e0b;
      --boc-red: #ef4444;
      --boc-cyan: #06b6d4;
      --boc-font: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: var(--boc-font);
      background-color: var(--boc-bg);
      color: var(--boc-text);
      display: flex;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ════════════════════════════════════════════════════════════════════
       1. PERSISTENT SIDEBAR NAVIGATION
       ════════════════════════════════════════════════════════════════════ */
    .boc-sidebar {
      width: 250px;
      background: var(--boc-sidebar);
      border-right: 1px solid var(--boc-border);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
    }
    .boc-brand {
      padding: 1.15rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--boc-border);
      text-decoration: none;
      color: #fff;
    }
    .boc-brand-left {
      display: flex;
      align-items: center;
      gap: 0.65rem;
    }
    .boc-brand-title { font-weight: 900; font-size: 1.05rem; letter-spacing: -0.01em; }
    .boc-brand-sub { font-size: 0.68rem; color: var(--boc-primary); font-weight: 800; text-transform: uppercase; }

    .boc-nav { flex: 1; padding: 1rem 0.65rem; overflow-y: auto; }
    .boc-nav-section { margin-bottom: 1.25rem; }
    .boc-nav-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: var(--boc-text-muted); padding: 0 0.65rem 0.4rem; letter-spacing: 0.05em; }
    .boc-nav-item {
      display: flex;
      align-items: center;
      gap: 0.65rem;
      padding: 0.55rem 0.75rem;
      border-radius: 8px;
      color: var(--boc-text-soft);
      text-decoration: none;
      font-size: 0.82rem;
      font-weight: 600;
      transition: all 0.15s ease;
      cursor: pointer;
    }
    .boc-nav-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .boc-nav-item.active {
      background: rgba(var(--boc-primary-rgb), 0.15);
      color: var(--boc-primary);
      font-weight: 700;
      border-left: 3px solid var(--boc-primary);
    }
    .boc-nav-icon { width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

    .boc-sidebar-footer {
      padding: 1rem 1.25rem;
      border-top: 1px solid var(--boc-border);
      font-size: 0.8rem;
      color: var(--boc-text-soft);
      display: flex;
      align-items: center;
      justify-content: space-between;
      cursor: pointer;
    }

    /* ════════════════════════════════════════════════════════════════════
       2. MAIN CONTENT AREA & HEADER
       ════════════════════════════════════════════════════════════════════ */
    .boc-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
    .boc-header {
      height: 60px;
      background: var(--boc-sidebar);
      border-bottom: 1px solid var(--boc-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.5rem;
    }
    .boc-header-left { display: flex; align-items: center; gap: 1rem; }
    .boc-page-pill {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-weight: 800;
      font-size: 0.95rem;
    }

    .boc-search-wrap { position: relative; width: 380px; }
    .boc-search-input {
      width: 100%; height: 36px; background: var(--boc-bg); border: 1px solid var(--boc-border);
      border-radius: 8px; color: #fff; font-size: 0.8rem; padding: 0 4.5rem 0 2.2rem;
    }
    .boc-search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--boc-text-muted); }
    .boc-search-kbd {
      position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%);
      font-size: 0.65rem; font-weight: 700; color: var(--boc-text-muted); background: rgba(255,255,255,0.06);
      padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid var(--boc-border);
    }

    .boc-top-actions { display: flex; align-items: center; gap: 1rem; }
    .boc-icon-btn {
      width: 34px; height: 34px; border-radius: 8px; background: var(--boc-bg);
      border: 1px solid var(--boc-border); color: var(--boc-text-soft); display: flex;
      align-items: center; justify-content: center; position: relative; cursor: pointer;
    }
    .boc-icon-btn:hover { color: #fff; border-color: rgba(255,255,255,0.2); }
    .boc-badge-num {
      position: absolute; top: -4px; right: -4px; background: var(--boc-primary); color: #fff;
      font-size: 0.6rem; font-weight: 900; padding: 1px 4px; border-radius: 10px; border: 2px solid var(--boc-sidebar);
    }

    .boc-wallet-pill {
      background: var(--boc-bg); border: 1px solid var(--boc-border); border-radius: 8px;
      padding: 0.35rem 0.75rem; font-size: 0.78rem; font-weight: 700; display: flex; align-items: center; gap: 0.4rem;
    }
    .boc-user-pill { display: flex; align-items: center; gap: 0.6rem; cursor: pointer; }
    .boc-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--boc-primary); }

    /* CONTENT BODY & HEADER */
    .boc-content { padding: 1.5rem; flex: 1; overflow-y: auto; }
    
    .boc-title-bar {
      display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;
    }
    .boc-main-title { font-size: 1.4rem; font-weight: 900; letter-spacing: -0.01em; }
    .boc-main-sub { font-size: 0.82rem; color: var(--boc-text-soft); margin-top: 2px; }

    .boc-btn-primary {
      background: var(--boc-primary); color: #fff; border: none; padding: 0.6rem 1.25rem;
      border-radius: 8px; font-weight: 800; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 0.4rem;
      box-shadow: 0 4px 12px rgba(var(--boc-primary-rgb), 0.3); transition: all 0.15s ease;
    }
    .boc-btn-primary:hover { background: var(--boc-primary-hover); transform: translateY(-1px); }
    .boc-btn-primary:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    .boc-btn-solid {
      background: var(--boc-bg); color: var(--boc-text); border: 1px solid var(--boc-border); padding: 0.6rem 1.25rem;
      border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.4rem;
    }
    .boc-btn-solid:hover { border-color: rgba(255,255,255,0.25); }
    .boc-btn-solid:disabled { opacity: .5; cursor: not-allowed; }

    .boc-btn-date {
      background: var(--boc-card); border: 1px solid var(--boc-border); color: #fff;
      padding: 0.55rem 1rem; border-radius: 8px; font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;
    }

    /* ════════════════════════════════════════════════════════════════════
       3. KPI CARDS STRIP (6 COLUMNS MATCHING REFERENCE IMAGE)
       ════════════════════════════════════════════════════════════════════ */
    .boc-kpi-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.9rem; margin-bottom: 1.25rem; }
    @media (max-width: 1400px) { .boc-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 768px) { .boc-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

    .boc-kpi-card {
      background: var(--boc-card); border: 1px solid var(--boc-border); border-radius: 12px; padding: 1.1rem;
      display: flex; gap: 0.85rem; align-items: flex-start; transition: border-color 0.2s ease;
    }
    .boc-kpi-card:hover { border-color: rgba(255,255,255,0.15); }

    .boc-kpi-icon {
      width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; font-size: 1.1rem;
    }
    .kpi-icon-blue { background: rgba(59, 130, 246, 0.15); color: var(--boc-blue); }
    .kpi-icon-cyan { background: rgba(6, 182, 212, 0.15); color: var(--boc-cyan); }
    .kpi-icon-purple { background: rgba(139, 92, 246, 0.15); color: var(--boc-purple); }
    .kpi-icon-green { background: rgba(16, 185, 129, 0.15); color: var(--boc-green); }
    .kpi-icon-pink { background: rgba(236, 72, 153, 0.15); color: #ec4899; }
    .kpi-icon-orange { background: rgba(245, 158, 11, 0.15); color: var(--boc-orange); }

    .boc-kpi-title { font-size: 0.68rem; font-weight: 700; color: var(--boc-text-muted); text-transform: uppercase; letter-spacing: 0.03em; }
    .boc-kpi-val { font-size: 1.45rem; font-weight: 900; color: #fff; margin: 2px 0; letter-spacing: -0.02em; }
    .boc-kpi-sub { font-size: 0.68rem; color: var(--boc-green); font-weight: 700; display: flex; align-items: center; gap: 0.2rem; }

    /* ════════════════════════════════════════════════════════════════════
       4. MIDDLE LAYOUT GRID (TODAY'S OPERATIONS | REVENUE | ALERTS)
       ════════════════════════════════════════════════════════════════════ */
    .boc-grid-mid { display: grid; grid-template-columns: 1.45fr 1.25fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
    @media (max-width: 1200px) { .boc-grid-mid { grid-template-columns: 1fr; } }

    .boc-card { background: var(--boc-card); border: 1px solid var(--boc-border); border-radius: 12px; padding: 1.25rem; }
    .boc-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
    .boc-card-title { font-size: 0.95rem; font-weight: 800; color: #fff; }
    .boc-card-link { font-size: 0.75rem; color: var(--boc-primary); text-decoration: none; font-weight: 700; cursor: pointer; }
    .boc-card-link:hover { text-decoration: underline; }

    /* Quick actions (Overview) */
    .boc-quick-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.9rem; margin-bottom: 1.25rem; }
    @media (max-width: 900px) { .boc-quick-actions { grid-template-columns: repeat(2, 1fr); } }
    .boc-quick-action { background: var(--boc-card); border: 1px solid var(--boc-border); border-radius: 12px; padding: 1rem 1.1rem; display: flex; align-items: center; gap: 0.75rem; cursor: pointer; text-align: left; transition: border-color .15s ease, transform .15s ease; }
    .boc-quick-action:hover { border-color: var(--boc-primary); transform: translateY(-1px); }
    .boc-quick-action-icon { width: 38px; height: 38px; border-radius: 10px; background: rgba(var(--boc-primary-rgb), 0.15); color: var(--boc-primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .boc-quick-action-label { font-size: 0.82rem; font-weight: 800; color: #fff; }
    .boc-quick-action-sub { font-size: 0.68rem; color: var(--boc-text-muted); margin-top: 1px; }

    /* Wizard */
    .boc-wizard-steps { display: flex; align-items: center; gap: 0.4rem; padding: 0 1.2rem 1rem; }
    .boc-wizard-dot { flex: 1; height: 4px; border-radius: 2px; background: var(--boc-border); }
    .boc-wizard-dot.done, .boc-wizard-dot.active { background: var(--boc-primary); }
    .boc-wizard-step-label { display: flex; justify-content: space-between; padding: 0 1.2rem 0.6rem; font-size: 0.7rem; font-weight: 700; color: var(--boc-text-muted); text-transform: uppercase; letter-spacing: 0.03em; }
    .boc-wizard-step-label span.active { color: var(--boc-primary); }
    .boc-wizard-panel { display: none; }
    .boc-wizard-panel.active { display: flex; flex-direction: column; gap: 0.8rem; }
    .boc-wizard-review-row { display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid var(--boc-border); font-size: 0.8rem; }
    .boc-wizard-review-row span:first-child { color: var(--boc-text-muted); }
    .boc-wizard-review-row span:last-child { color: #fff; font-weight: 700; }

    /* Revenue payout method toggle */
    .rev-method-toggle.active { background: var(--boc-primary); color: #fff; border-color: var(--boc-primary); }

    /* Vehicle detail modal tabs */
    .veh-tab-btn { background:none; border:0; border-bottom:2px solid transparent; color:var(--boc-text-muted); font-weight:700; font-size:0.78rem; padding:0.5rem 0.7rem; cursor:pointer; }
    .veh-tab-btn.active { color:var(--boc-primary); border-bottom-color:var(--boc-primary); }
    .veh-tab-panel.active { display:block !important; }
    .drv-tab-btn { background:none; border:0; border-bottom:2px solid transparent; color:var(--boc-text-muted); font-weight:700; font-size:0.78rem; padding:0.5rem 0.7rem; cursor:pointer; }
    .drv-tab-btn.active { color:var(--boc-primary); border-bottom-color:var(--boc-primary); }
    .drv-tab-panel.active { display:block !important; }

    /* Ticket template style gallery */
    .tt-style-swatch { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.6rem; border: 1px solid var(--boc-border); border-radius: 8px; font-size: 0.72rem; font-weight: 700; cursor: pointer; }
    .tt-style-swatch span { width: 22px; height: 22px; border-radius: 5px; flex-shrink: 0; }
    .tt-style-swatch.active { border-color: var(--boc-primary); background: rgba(var(--boc-primary-rgb), 0.08); }

    /* Customer listing step */
    .boc-style-option { display:flex; align-items:center; justify-content:center; gap:0.35rem; padding:0.5rem; border:1px solid var(--boc-border); border-radius:8px; font-size:0.78rem; font-weight:700; cursor:pointer; }
    .boc-style-option input { accent-color: var(--boc-primary); }
    .boc-style-option:has(input:checked) { border-color: var(--boc-primary); color: var(--boc-primary); }
    .boc-preview-card { display:flex; gap:0.8rem; align-items:center; background:var(--boc-bg); border:1px solid var(--boc-border); border-radius:12px; padding:0.8rem; }
    .boc-preview-thumb { width:56px; height:56px; border-radius:8px; object-fit:cover; background:var(--boc-card); flex-shrink:0; }
    .boc-preview-premium .boc-preview-thumb { width:90px; height:64px; }
    .boc-preview-title { font-weight:800; color:#fff; font-size:0.85rem; }
    .boc-preview-sub { font-size:0.72rem; color:var(--boc-text-muted); margin-top:2px; }
    .boc-preview-chip { display:inline-block; background:rgba(var(--boc-primary-rgb),0.15); color:var(--boc-primary); font-size:0.62rem; font-weight:700; padding:0.15rem 0.5rem; border-radius:999px; margin:0.25rem 0.25rem 0 0; }

    /* TODAY'S OPERATIONS ITEMS */
    .boc-ops-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .boc-ops-item {
      padding: 0.75rem 0.85rem; background: var(--boc-bg); border-radius: 10px; border: 1px solid var(--boc-border);
      display: flex; flex-direction: column; gap: 0.4rem;
    }
    .boc-ops-top { display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem; }
    .boc-ops-route { font-weight: 800; color: #fff; }
    .boc-ops-meta { font-size: 0.72rem; color: var(--boc-text-muted); }
    .boc-ops-bar-wrap { display: flex; align-items: center; gap: 0.75rem; font-size: 0.75rem; }
    .boc-progress { flex: 1; height: 6px; background: rgba(255,255,255,0.08); border-radius: 3px; overflow: hidden; }
    .boc-progress-fill { height: 100%; background: linear-gradient(90deg, var(--boc-primary), var(--boc-green)); border-radius: 3px; }

    .boc-tag { display: inline-block; padding: 0.18rem 0.5rem; border-radius: 5px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.03em; }
    .tag-boarding { background: rgba(16, 185, 129, 0.15); color: var(--boc-green); }
    .tag-scheduled { background: rgba(59, 130, 246, 0.15); color: var(--boc-blue); }
    .tag-low { background: rgba(239, 68, 68, 0.15); color: var(--boc-red); }

    /* ALERTS QUEUE */
    .boc-alerts-list { display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.78rem; }
    .boc-alert-item { display: flex; gap: 0.75rem; align-items: flex-start; padding-bottom: 0.75rem; border-bottom: 1px solid var(--boc-border); }
    .boc-alert-item:last-child { border-bottom: none; padding-bottom: 0; }
    .boc-alert-icon { width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.85rem; }
    .alert-icon-warning { background: rgba(245, 158, 11, 0.15); color: var(--boc-orange); }
    .alert-icon-danger { background: rgba(239, 68, 68, 0.15); color: var(--boc-red); }
    .alert-icon-success { background: rgba(16, 185, 129, 0.15); color: var(--boc-green); }
    .alert-icon-info { background: rgba(59, 130, 246, 0.15); color: var(--boc-blue); }

    /* ════════════════════════════════════════════════════════════════════
       5. LOWER LAYOUT GRID (CHARTS & TIMELINE)
       ════════════════════════════════════════════════════════════════════ */
    .boc-grid-lower { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
    @media (max-width: 1400px) { .boc-grid-lower { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 768px) { .boc-grid-lower { grid-template-columns: 1fr; } }

    /* DONUT CHART SIMULATOR */
    .boc-donut-wrap { display: flex; align-items: center; justify-content: center; margin: 1rem 0; position: relative; }
    .boc-donut-center { position: absolute; text-align: center; }
    .boc-donut-num { font-size: 1.4rem; font-weight: 900; color: #fff; }
    .boc-donut-lbl { font-size: 0.65rem; color: var(--boc-text-muted); text-transform: uppercase; }

    .boc-legend-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem 0.75rem; font-size: 0.72rem; color: var(--boc-text-soft); }
    .boc-legend-item { display: flex; align-items: center; justify-content: space-between; }
    .boc-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 0.35rem; }

    /* TOP ROUTES PROGRESS */
    .boc-route-row { margin-bottom: 0.75rem; }
    .boc-route-top { display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 4px; }
    .boc-route-name { font-weight: 700; color: #fff; }
    .boc-route-tickets { color: var(--boc-text-muted); font-size: 0.72rem; }

    /* ════════════════════════════════════════════════════════════════════
       6. BOTTOM ROW: RECENT BOOKINGS & QUICK ACTIONS 8-GRID
       ════════════════════════════════════════════════════════════════════ */
    .boc-grid-bottom { display: grid; grid-template-columns: 2.2fr 1fr; gap: 1.25rem; }
    @media (max-width: 1100px) { .boc-grid-bottom { grid-template-columns: 1fr; } }

    .boc-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
    .boc-table th { text-align: left; padding: 0.75rem 0.6rem; color: var(--boc-text-muted); font-size: 0.68rem; font-weight: 800; text-transform: uppercase; border-bottom: 1px solid var(--boc-border); }
    .boc-table td { padding: 0.7rem 0.6rem; border-bottom: 1px solid var(--boc-border); color: var(--boc-text-soft); vertical-align: middle; }

    .boc-actions-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 0.65rem; }
    .boc-action-btn {
      background: var(--boc-bg); border: 1px solid var(--boc-border); border-radius: 10px;
      padding: 0.85rem 0.5rem; text-align: center; cursor: pointer; transition: all 0.15s ease;
      display: flex; flex-direction: column; align-items: center; gap: 0.4rem; color: var(--boc-text-soft);
      font-size: 0.7rem; font-weight: 700; text-decoration: none;
    }
    .boc-action-btn:hover { background: rgba(255,255,255,0.06); color: #fff; border-color: rgba(255,255,255,0.15); transform: translateY(-2px); }
    .boc-action-icon { font-size: 1.2rem; color: var(--boc-primary); }

    .boc-select-small {
      background: var(--boc-bg); border: 1px solid var(--boc-border); color: var(--boc-text-soft);
      font-size: 0.72rem; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 700;
    }

    /* Fix search input text color to use variable */
    .boc-search-input { color: var(--boc-text); }

    /* ═══════════════════════════════════════════════════════════════════
       REUSABLE 2-COLUMN WIZARD SHELL SYSTEM
       ═══════════════════════════════════════════════════════════════════ */
    .boc-wiz-modal {
      display: none;
      position: fixed;
      inset: 0;
      z-index: 9999;
      background: rgba(4, 9, 20, 0.88);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .boc-wiz-shell {
      width: 760px;
      max-width: 97vw;
      max-height: 88vh;
      background: var(--boc-card);
      border: 1px solid var(--boc-border);
      border-radius: 20px;
      box-shadow: 0 32px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.04);
      display: flex;
      flex-direction: column;
      overflow: hidden;
      position: relative;
    }
    .boc-wiz-header {
      padding: 1rem 1.5rem;
      border-bottom: 1px solid var(--boc-border);
      background: var(--boc-sidebar);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    .boc-wiz-header-title {
      font-size: 1.05rem;
      font-weight: 900;
      color: var(--boc-text);
      margin: 0;
      letter-spacing: -0.01em;
    }
    .boc-wiz-header-sub {
      font-size: 0.65rem;
      font-weight: 800;
      color: var(--boc-primary);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 0.15rem;
    }
    .boc-wiz-body {
      display: grid;
      grid-template-columns: 190px 1fr;
      flex: 1;
      overflow: hidden;
      min-height: 0;
    }
    .boc-wiz-sidebar {
      background: var(--boc-sidebar);
      border-right: 1px solid var(--boc-border);
      padding: 1rem 0.65rem;
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
      overflow-y: auto;
      flex-shrink: 0;
    }
    .boc-wiz-side-step {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.55rem 0.7rem;
      border-radius: 10px;
      color: var(--boc-text-soft);
      font-size: 0.75rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.18s ease;
      border: 1px solid transparent;
      user-select: none;
    }
    .boc-wiz-side-step:hover {
      background: rgba(255,255,255,0.05);
      color: var(--boc-text);
    }
    .boc-wiz-side-step.active {
      background: rgba(230,57,70,0.13);
      color: var(--boc-primary);
      border-color: rgba(230,57,70,0.28);
    }
    .boc-wiz-side-num {
      width: 22px;
      height: 22px;
      border-radius: 6px;
      background: rgba(255,255,255,0.07);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.68rem;
      font-weight: 900;
      flex-shrink: 0;
      transition: all 0.18s ease;
    }
    .boc-wiz-side-step.active .boc-wiz-side-num {
      background: var(--boc-primary);
      color: #fff;
    }
    .boc-wiz-main {
      padding: 1.4rem 1.6rem;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }
    .boc-wiz-footer {
      padding: 0.9rem 1.5rem;
      border-top: 1px solid var(--boc-border);
      background: var(--boc-sidebar);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }

    /* Wizard form fields */
    .boc-wiz-field-row {
      display: grid;
      gap: 0.85rem;
      margin-bottom: 0.85rem;
    }
    .boc-wiz-field-row.cols-2 { grid-template-columns: 1fr 1fr; }
    .boc-wiz-field-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .boc-wiz-label {
      display: block;
      font-size: 0.7rem;
      font-weight: 700;
      color: var(--boc-text-muted);
      text-transform: uppercase;
      letter-spacing: 0.04em;
      margin-bottom: 0.3rem;
    }
    .boc-wiz-input {
      width: 100%;
      height: 38px;
      background: var(--boc-bg);
      border: 1px solid var(--boc-border);
      border-radius: 8px;
      color: var(--boc-text);
      font-size: 0.82rem;
      font-family: var(--boc-font);
      padding: 0 0.75rem;
      transition: border-color 0.15s ease, box-shadow 0.15s ease;
      outline: none;
    }
    .boc-wiz-input:focus {
      border-color: var(--boc-primary);
      box-shadow: 0 0 0 3px rgba(230,57,70,0.15);
    }
    .boc-wiz-select {
      width: 100%;
      height: 38px;
      background: var(--boc-bg);
      border: 1px solid var(--boc-border);
      border-radius: 8px;
      color: var(--boc-text);
      font-size: 0.82rem;
      font-family: var(--boc-font);
      padding: 0 0.75rem;
      outline: none;
      cursor: pointer;
    }
    .boc-wiz-section-title {
      font-size: 0.72rem;
      font-weight: 900;
      color: var(--boc-primary);
      text-transform: uppercase;
      letter-spacing: 0.07em;
      margin: 1rem 0 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .boc-wiz-section-title:first-child { margin-top: 0; }

    /* Dropzone upload area */
    .boc-wiz-dropzone {
      border: 2px dashed var(--boc-border);
      border-radius: 12px;
      padding: 1.4rem 1rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease;
      background: rgba(255,255,255,0.02);
    }
    .boc-wiz-dropzone:hover {
      border-color: var(--boc-primary);
      background: rgba(230,57,70,0.04);
    }
    .boc-wiz-dropzone-icon {
      width: 36px; height: 36px; margin: 0 auto 0.6rem;
      background: rgba(230,57,70,0.12);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      color: var(--boc-primary);
    }
    .boc-wiz-preview-box {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.75rem 1rem;
      background: rgba(16,185,129,0.07);
      border: 1.5px solid #10b981;
      border-radius: 12px;
      margin-top: 0.6rem;
    }
    .boc-wiz-preview-thumb {
      width: 72px; height: 52px;
      border-radius: 8px;
      object-fit: cover;
      border: 2px solid #10b981;
      box-shadow: 0 3px 8px rgba(0,0,0,0.3);
      flex-shrink: 0;
    }

    /* Wizard step pane */
    .boc-wiz-pane { display: none; }
    .boc-wiz-pane.active { display: block; }

    /* Driver / Bus selection cards */
    .boc-wiz-select-card {
      background: var(--boc-sidebar);
      border: 1.5px solid var(--boc-border);
      border-radius: 10px;
      padding: 0.8rem 1rem;
      display: flex;
      align-items: center;
      gap: 0.8rem;
      cursor: pointer;
      transition: all 0.18s ease;
      margin-bottom: 0.5rem;
    }
    .boc-wiz-select-card:hover { border-color: rgba(230,57,70,0.4); }
    .boc-wiz-select-card.selected { border-color: var(--boc-primary); background: rgba(230,57,70,0.06); }
    .boc-wiz-select-card.disabled { opacity: 0.45; cursor: not-allowed; }
    .boc-wiz-select-card-radio { flex-shrink: 0; }
    .boc-wiz-select-card-info { flex: 1; }
    .boc-wiz-select-card-name { font-size: 0.85rem; font-weight: 800; color: var(--boc-text); display: block; }
    .boc-wiz-select-card-sub { font-size: 0.72rem; color: var(--boc-text-soft); margin-top: 0.1rem; display: block; }
    .boc-wiz-select-card-badge { font-size: 0.68rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 5px; }
    .badge-green { background: rgba(16,185,129,0.15); color: #10b981; }
    .badge-orange { background: rgba(245,158,11,0.15); color: #f59e0b; }
    .badge-red { background: rgba(239,68,68,0.12); color: #ef4444; }

    /* Review checklist */
    .boc-wiz-review-card {
      background: var(--boc-sidebar);
      border: 1px solid var(--boc-border);
      border-radius: 14px;
      padding: 1.2rem 1.4rem;
    }
    .boc-wiz-review-item {
      display: flex; align-items: center; gap: 0.6rem;
      font-size: 0.8rem; color: var(--boc-text-soft);
      padding: 0.3rem 0;
      border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .boc-wiz-review-item:last-child { border-bottom: none; }
    .boc-wiz-review-item .check { color: #10b981; font-weight: 900; }

    /* Customer listing preview card */
    .boc-listing-preview-card {
      background: linear-gradient(135deg, rgba(230,57,70,0.08), rgba(59,130,246,0.05));
      border: 1px solid var(--boc-border);
      border-radius: 14px;
      overflow: hidden;
    }
    .boc-listing-preview-img {
      width: 100%; height: 110px;
      background: linear-gradient(135deg, #1a1f35, #0f1523);
      display: flex; align-items: center; justify-content: center;
      color: var(--boc-text-muted);
      font-size: 0.75rem;
      font-weight: 700;
    }
    .boc-listing-preview-img img { width: 100%; height: 100%; object-fit: cover; }
    .boc-listing-preview-body { padding: 0.9rem 1rem; }

    /* ═══════════════════════════════════════════════════════════════════
       COMPREHENSIVE LIGHT MODE THEME OVERRIDES
       ═══════════════════════════════════════════════════════════════════ */
    html[data-theme="light"] {
      --boc-bg: #f8fafc;
      --boc-sidebar: #ffffff;
      --boc-card: #ffffff;
      --boc-card-hover: #f1f5f9;
      --boc-border: #e2e8f0;
      --boc-text: #0f172a;
      --boc-text-soft: #475569;
      --boc-text-muted: #94a3b8;
    }
    html[data-theme="light"] body { background-color: #f8fafc; color: #0f172a; }
    html[data-theme="light"] .boc-sidebar { background: #fff; border-right-color: #e2e8f0; }
    html[data-theme="light"] .boc-header { background: #fff; border-bottom-color: #e2e8f0; }
    html[data-theme="light"] .boc-brand { color: #0f172a; border-bottom-color: #e2e8f0; }
    html[data-theme="light"] .boc-nav-item { color: #475569; }
    html[data-theme="light"] .boc-nav-item:hover { background: rgba(0,0,0,0.04); color: #0f172a; }
    html[data-theme="light"] .boc-sidebar-footer { color: #475569; border-top-color: #e2e8f0; }
    html[data-theme="light"] .boc-content { background: #f8fafc; }
    html[data-theme="light"] .boc-card, html[data-theme="light"] .boc-kpi-card { background: #ffffff; border-color: #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
    html[data-theme="light"] .boc-table th { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
    html[data-theme="light"] .boc-table td { color: #334155; border-color: #f1f5f9; }
    html[data-theme="light"] .boc-table tr:hover td { background: #f8fafc; }
    html[data-theme="light"] .boc-search-input { background: #f1f5f9; border-color: #cbd5e1; color: #0f172a; }
    html[data-theme="light"] .boc-search-input:focus { background: #fff; border-color: var(--boc-primary); }
    html[data-theme="light"] .boc-search-input::placeholder { color: #94a3b8; }
    html[data-theme="light"] .boc-search-icon { color: #64748b; }
    html[data-theme="light"] .boc-btn-solid { background: #f1f5f9; border-color: #e2e8f0; color: #334155; }
    html[data-theme="light"] .boc-btn-solid:hover { background: #e2e8f0; color: #0f172a; }
    html[data-theme="light"] .boc-icon-btn { background: #f8fafc; border-color: #e2e8f0; color: #475569; }
    html[data-theme="light"] .boc-wallet-pill { background: #f8fafc; border-color: #e2e8f0; color: #0f172a; }
    html[data-theme="light"] .boc-tabs { border-bottom-color: #e2e8f0; }
    html[data-theme="light"] .boc-tab-btn { color: #475569; }
    html[data-theme="light"] .boc-tab-btn.active { color: var(--boc-primary); border-bottom-color: var(--boc-primary); }
    /* Wizard light mode */
    html[data-theme="light"] .boc-wiz-shell { background: #fff; border-color: #e2e8f0; box-shadow: 0 20px 60px rgba(0,0,0,0.12); }
    html[data-theme="light"] .boc-wiz-header { background: #f8fafc; border-bottom-color: #e2e8f0; }
    html[data-theme="light"] .boc-wiz-sidebar { background: #f8fafc; border-right-color: #e2e8f0; }
    html[data-theme="light"] .boc-wiz-footer { background: #f8fafc; border-top-color: #e2e8f0; }
    html[data-theme="light"] .boc-wiz-input { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; }
    html[data-theme="light"] .boc-wiz-select { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; }
    html[data-theme="light"] .boc-wiz-select-card { background: #f8fafc; border-color: #e2e8f0; }
    html[data-theme="light"] .boc-wiz-select-card-name { color: #0f172a; }
    html[data-theme="light"] .boc-wiz-review-card { background: #f8fafc; border-color: #e2e8f0; }
    html[data-theme="light"] .boc-wiz-side-step { color: #475569; }
    html[data-theme="light"] .boc-wiz-side-step:hover { background: rgba(0,0,0,0.04); color: #0f172a; }
    html[data-theme="light"] .boc-wiz-side-num { background: #e2e8f0; }
    html[data-theme="light"] .boc-dropzone { background: #f8fafc; border-color: #cbd5e1; }
    html[data-theme="light"] .boc-dropzone:hover { border-color: var(--boc-primary); background: rgba(230,57,70,0.02); }
    html[data-theme="light"] .boc-listing-preview-card { border-color: #e2e8f0; }
  </style>
</head>
<body data-base-url="<?= e(BASE_URL) ?>" data-csrf="<?= e($csrfToken) ?>" data-active-tab="<?= e($activeTab) ?>">

<!-- ════════════════════════════════════════════════════════════════════
     1. PERSISTENT SIDEBAR NAVIGATION (MATCHING EXACT MENU TREE)
     ════════════════════════════════════════════════════════════════════ -->
<aside class="boc-sidebar">
  <a href="<?= BASE_URL ?>vendor/portal.php" class="boc-brand">
    <div class="boc-brand-left">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--boc-primary)" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
      <div>
        <div class="boc-brand-title">Uthenga Vendor</div>
      </div>
    </div>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--boc-text-muted)" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
  </a>

  <nav class="boc-nav">
    <!-- MAIN -->
    <div class="boc-nav-section">
      <div class="boc-nav-label">MAIN</div>
      <a href="<?= BASE_URL ?>vendor/portal.php" class="boc-nav-item">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></span> Dashboard
      </a>
      <a href="<?= BASE_URL ?>ai.php#/driver" class="boc-nav-item">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span> Quick Taxi
      </a>
    </div>

    <!-- BUS OPERATIONS -->
    <div class="boc-nav-section">
      <div class="boc-nav-label">BUS OPERATIONS</div>
      <a onclick="switchSubTab('overview', this)" class="boc-nav-item <?= $activeTab === 'overview' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-1.1 0-2 .9-2 2v7c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg></span> Overview
      </a>
      <a onclick="switchSubTab('buses', this)" class="boc-nav-item <?= $activeTab === 'buses' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/></svg></span> Buses (Fleet)
      </a>
      <a onclick="switchSubTab('routes', this)" class="boc-nav-item <?= $activeTab === 'routes' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/></svg></span> Routes
      </a>
      <a onclick="switchSubTab('trips', this)" class="boc-nav-item <?= $activeTab === 'trips' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span> Trips / Departures
      </a>
      <a onclick="switchSubTab('tickets', this)" class="boc-nav-item <?= $activeTab === 'tickets' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/></svg></span> Tickets
      </a>
      <a onclick="switchSubTab('boarding', this)" class="boc-nav-item <?= $activeTab === 'boarding' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h3v3H7z"/><path d="M14 7h3v3h-3z"/><path d="M7 14h3v3H7z"/></svg></span> Boarding / Check-in
      </a>
      <a onclick="switchSubTab('passengers', this)" class="boc-nav-item <?= $activeTab === 'passengers' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></span> Passengers
      </a>
      <a onclick="switchSubTab('drivers', this)" class="boc-nav-item <?= $activeTab === 'drivers' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> Drivers
      </a>
      <a onclick="switchSubTab('maintenance', this)" class="boc-nav-item <?= $activeTab === 'maintenance' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span> Maintenance
      </a>
    </div>

    <!-- FINANCE -->
    <div class="boc-nav-section">
      <div class="boc-nav-label">FINANCE</div>
      <a onclick="switchSubTab('revenue', this)" class="boc-nav-item <?= $activeTab === 'revenue' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span> Revenue
      </a>
    </div>

    <!-- INSIGHTS -->
    <div class="boc-nav-section">
      <div class="boc-nav-label">INSIGHTS</div>
      <a onclick="switchSubTab('analytics', this)" class="boc-nav-item <?= $activeTab === 'analytics' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span> Analytics
      </a>
      <a onclick="switchSubTab('reports', this)" class="boc-nav-item <?= $activeTab === 'reports' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 18 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></span> Reports
      </a>
    </div>

    <!-- SETTINGS -->
    <div class="boc-nav-section">
      <div class="boc-nav-label">SETTINGS</div>
      <a onclick="switchSubTab('company', this)" class="boc-nav-item <?= $activeTab === 'company' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> Company Settings
      </a>
      <a onclick="switchSubTab('users', this)" class="boc-nav-item <?= $activeTab === 'users' ? 'active' : '' ?>">
        <span class="boc-nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></span> User Management
      </a>
    </div>
  </nav>

  <div class="boc-sidebar-footer">
    <div style="display:flex;align-items:center;gap:0.5rem;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>Help &amp; Support</span>
    </div>
    <span>▾</span>
  </div>
</aside>

<!-- ════════════════════════════════════════════════════════════════════
     2. MAIN CONTENT AREA
     ════════════════════════════════════════════════════════════════════ -->
<main class="boc-main">
  <!-- TOP HEADER BAR -->
  <header class="boc-header">
    <div class="boc-header-left">
      <div class="boc-page-pill">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--boc-primary)" stroke-width="2.5"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-1.1 0-2 .9-2 2v7c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
        <span>Bus Operations</span>
      </div>

      <div class="boc-search-wrap">
        <svg class="boc-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" class="boc-search-input" placeholder="Search trips, tickets, passengers...">
        <span class="boc-search-kbd">Ctrl + K</span>
      </div>
    </div>

    <div class="boc-top-actions">
        <button type="button" class="boc-btn-solid" id="boc-theme-toggle" onclick="toggleBusTheme()" style="display:flex;align-items:center;gap:0.4rem;padding:0.4rem 0.85rem;">
          <svg id="boc-theme-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <span id="boc-theme-text">Dark Mode</span>
        </button>
      <button class="boc-icon-btn" title="Alerts">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="boc-badge-num">8</span>
      </button>

      <button class="boc-icon-btn" title="Messages">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        <span class="boc-badge-num" style="background:var(--boc-blue);">15</span>
      </button>

      <div class="boc-wallet-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--boc-primary)" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        <span>+ Wallet</span>
        <span style="font-size:0.65rem;color:var(--boc-text-muted);">▾</span>
      </div>

      <div class="boc-user-pill">
        <div class="boc-avatar" style="display:flex;align-items:center;justify-content:center;background:var(--boc-bg);font-weight:800;"><?= e(mb_substr((string) ($vendor['name'] ?? 'U'), 0, 1)) ?></div>
        <div>
          <div style="font-size:0.78rem;font-weight:800;color:#fff;"><?= e((string) ($vendor['name'] ?? 'Operator')) ?></div>
          <div style="font-size:0.65rem;color:var(--boc-text-muted);">Transport Company</div>
        </div>
      </div>
    </div>
  </header>

  <!-- CONTENT CONTAINER -->
  <div class="boc-content">
    
        <!-- SUB-PANEL 1: OVERVIEW (MORNING CONTROL ROOM) -->
    <div id="boc-panel-overview" class="boc-panel" style="<?= $activeTab === 'overview' ? '' : 'display:none;' ?>">
      
      <!-- PAGE HEADER BAR -->
      <div class="boc-title-bar" style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <div>
          <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.25rem;">
            <h1 class="boc-main-title" style="font-size:1.8rem;font-weight:900;">Good morning, <?= e((string) ($vendor['name'] ?? 'there')) ?>.</h1>
            <span class="boc-tag tag-boarding" style="padding:0.25rem 0.6rem;font-size:0.72rem;">● System Operational</span>
          </div>
          <p class="boc-main-sub" style="color:var(--boc-text-muted);font-size:0.85rem;" id="ov-header-sub">Loading your operations summary…</p>
        </div>

        <div style="display:flex;align-items:center;gap:0.75rem;">
          <div class="boc-search-wrap" style="width:260px;">
            <svg class="boc-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="ov-global-search" class="boc-search-input" placeholder="Search trips, tickets, passengers..." onkeydown="if(event.key==='Enter'){switchSubTab('trips');}">
          </div>
          <button class="boc-btn-primary" type="button" onclick="BusOps.openDepartureModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>+ Schedule Trip</span>
          </button>
        </div>
      </div>

      <!-- KPI STRIP — CLICKABLE CARDS -->
      <div class="boc-kpi-grid" style="grid-template-columns:repeat(auto-fit, minmax(170px, 1fr));gap:0.85rem;margin-bottom:1.5rem;">
        <div class="boc-kpi-card" onclick="switchSubTab('trips')" style="cursor:pointer;">
          <div class="boc-kpi-icon kpi-icon-blue"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          <div><div class="boc-kpi-title">TODAY'S DEPARTURES</div><div class="boc-kpi-val" id="kpi-today-departures">7</div></div>
        </div>
        <div class="boc-kpi-card" onclick="switchSubTab('buses')" style="cursor:pointer;">
          <div class="boc-kpi-icon kpi-icon-orange"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/></svg></div>
          <div><div class="boc-kpi-title">ACTIVE BUSES</div><div class="boc-kpi-val" id="kpi-active-buses">—</div></div>
        </div>
        <div class="boc-kpi-card" onclick="switchSubTab('tickets')" style="cursor:pointer;">
          <div class="boc-kpi-icon kpi-icon-cyan"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/></svg></div>
          <div><div class="boc-kpi-title">TICKETS SOLD</div><div class="boc-kpi-val" id="kpi-tickets-sold">—</div></div>
        </div>
        <div class="boc-kpi-card" onclick="switchSubTab('passengers')" style="cursor:pointer;">
          <div class="boc-kpi-icon kpi-icon-purple"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          <div><div class="boc-kpi-title">PASSENGERS TODAY</div><div class="boc-kpi-val" id="kpi-passengers">—</div></div>
        </div>
        <div class="boc-kpi-card" onclick="switchSubTab('revenue')" style="cursor:pointer;">
          <div class="boc-kpi-icon kpi-icon-green"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
          <div><div class="boc-kpi-title">REVENUE</div><div class="boc-kpi-val" style="color:var(--boc-green);" id="kpi-revenue">—</div></div>
        </div>
        <div class="boc-kpi-card" onclick="switchSubTab('maintenance')" style="cursor:pointer;">
          <div class="boc-kpi-icon kpi-icon-pink"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
          <div><div class="boc-kpi-title">PENDING ISSUES</div><div class="boc-kpi-val" style="color:var(--boc-orange);" id="kpi-pending-issues">—</div></div>
        </div>
      </div>

      <!-- MAIN CONTROL ROOM GRID (LEFT: LIVE DEPARTURES | RIGHT: FLEET STATUS & AI ASSISTANT) -->
      <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:1.25rem;">
        
        <!-- LEFT COLUMN: LIVE DEPARTURES DOMINANT WIDGET -->
        <div class="boc-card">
          <div class="boc-card-header">
            <div>
              <h3 class="boc-card-title">Live Operating Departures</h3>
              <div style="font-size:0.75rem;color:var(--boc-text-muted);">Real-time trip objects with seat inventory, boarding manifest, and status control.</div>
            </div>
            <button class="boc-btn-solid" type="button" onclick="switchSubTab('trips')">View All Trips →</button>
          </div>
          <div id="boc-today-departures" style="display:flex;flex-direction:column;gap:0.85rem;">
            <div style="color:var(--boc-text-muted);font-size:0.8rem;">Loading today's live departures…</div>
          </div>
        </div>

        <!-- RIGHT COLUMN: FLEET STATUS, BOOKING HEALTH & AI OPERATIONS ASSISTANT -->
        <div style="display:flex;flex-direction:column;gap:1.25rem;">
          
          <!-- FLEET STATUS SUMMARY -->
          <div class="boc-card">
            <div class="boc-card-header">
              <h3 class="boc-card-title">Fleet Asset Readiness</h3>
              <button class="boc-btn-solid" type="button" onclick="switchSubTab('buses')">Manage Fleet</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
              <div style="background:var(--boc-bg);border-radius:10px;padding:0.7rem;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:0.78rem;color:var(--boc-text-soft);">● On Trip</span><strong style="color:var(--boc-green);" id="ov-fleet-on-trip">—</strong>
              </div>
              <div style="background:var(--boc-bg);border-radius:10px;padding:0.7rem;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:0.78rem;color:var(--boc-text-soft);">● Available</span><strong style="color:var(--boc-blue);" id="ov-fleet-available">—</strong>
              </div>
              <div style="background:var(--boc-bg);border-radius:10px;padding:0.7rem;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:0.78rem;color:var(--boc-text-soft);">⚠ Maintenance</span><strong style="color:var(--boc-orange);" id="ov-fleet-maintenance">—</strong>
              </div>
              <div style="background:var(--boc-bg);border-radius:10px;padding:0.7rem;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:0.78rem;color:var(--boc-text-soft);">⚠ Document Issues</span><strong style="color:var(--boc-red);" id="ov-fleet-doc-issues">—</strong>
              </div>
            </div>
          </div>

          <!-- BOOKING HEALTH -->
          <div class="boc-card">
            <div class="boc-card-header"><h3 class="boc-card-title">Ticket Sales &amp; Capacity</h3></div>
            <div style="margin-bottom:0.75rem;">
              <div style="display:flex;justify-content:space-between;font-size:0.78rem;color:var(--boc-text-soft);margin-bottom:0.3rem;">
                <span>Total Capacity: <strong id="ov-capacity-total">—</strong></span>
                <span style="color:var(--boc-green);font-weight:800;" id="ov-capacity-pct">—</span>
              </div>
              <div class="boc-progress" style="height:8px;"><div class="boc-progress-fill" id="ov-capacity-bar" style="width:0%;background:var(--boc-green);"></div></div>
            </div>
            <div id="ov-capacity-alert" style="font-size:0.75rem;color:var(--boc-orange);font-weight:700;display:none;align-items:center;gap:0.4rem;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              <span id="ov-capacity-alert-text"></span>
            </div>
          </div>

        </div>

      </div>

    </div><!-- END SUB-PANEL 1: OVERVIEW -->

    <!-- SUB-PANEL 2: BUSES (FLEET) -->
    <div id="boc-panel-buses" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">Fleet</h2><button class="boc-btn-primary" id="boc-new-vehicle-btn" type="button">+ Add Vehicle</button></div>
      <div class="boc-kpi-grid" id="fleet-kpi-grid">
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Total Buses</div><div class="boc-kpi-val" id="fleet-kpi-total">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Available</div><div class="boc-kpi-val" style="color:var(--boc-green);" id="fleet-kpi-available">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">On Trip</div><div class="boc-kpi-val" id="fleet-kpi-on-trip">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Maintenance</div><div class="boc-kpi-val" style="color:var(--boc-orange);" id="fleet-kpi-maintenance">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Document Issues</div><div class="boc-kpi-val" style="color:var(--boc-red);" id="fleet-kpi-doc-issues">—</div></div></div>
      </div>
      <div class="boc-kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));" id="fleet-vehicles-grid"><div style="color:var(--boc-text-muted);font-size:0.8rem;">Loading…</div></div>
    </div>

    <!-- SUB-PANEL 3: ROUTES -->
    <div id="boc-panel-routes" class="boc-panel" style="display:none;">
      <div class="boc-card-header">
        <h2 class="boc-card-title">Routes &amp; Schedule</h2>
        <div style="display:flex;gap:0.6rem;">
          <button class="boc-btn-solid" id="boc-new-departure-btn" type="button">+ Schedule Departure</button>
          <button class="boc-btn-primary" id="boc-new-route-btn" type="button">+ Create Route</button>
        </div>
      </div>

      <div class="boc-kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));margin-bottom:1.25rem;" id="boc-routes-grid"><div style="color:var(--boc-text-muted);font-size:0.8rem;">Loading…</div></div>

      <div class="boc-card">
        <div class="boc-card-header"><h3 class="boc-card-title">Scheduled Departures</h3></div>
        <table class="boc-table">
          <thead><tr><th>Route</th><th>Departs</th><th>Status</th><th>Seats</th><th></th></tr></thead>
          <tbody id="boc-departures-body"><tr><td colspan="5" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- SUB-PANEL 3.5: TRIPS / DEPARTURES (DEDICATED OPERATIONAL WORKSPACE) -->
    <div id="boc-panel-trips" class="boc-panel" style="display:none;">
      <div class="boc-card-header">
        <div>
          <h2 class="boc-card-title">Trips &amp; Departures Workspace</h2>
          <div style="font-size:0.78rem;color:var(--boc-text-muted);margin-top:2px;">Central operational record tying route, bus, driver, seat inventory, manifest and boarding together.</div>
        </div>
        <button class="boc-btn-primary" type="button" onclick="BusOps.openDepartureModal()">+ Schedule Trip</button>
      </div>

      <div class="boc-kpi-grid" style="margin-bottom:1.25rem;">
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Total Scheduled</div><div class="boc-kpi-val" id="trips-kpi-total">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Open for Booking</div><div class="boc-kpi-val" style="color:var(--boc-blue);" id="trips-kpi-open">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Boarding</div><div class="boc-kpi-val" style="color:var(--boc-green);" id="trips-kpi-boarding">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">In Transit</div><div class="boc-kpi-val" style="color:var(--boc-cyan);" id="trips-kpi-transit">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Completed</div><div class="boc-kpi-val" style="color:var(--boc-purple);" id="trips-kpi-completed">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Cancelled</div><div class="boc-kpi-val" style="color:var(--boc-red);" id="trips-kpi-cancelled">—</div></div></div>
      </div>

      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
          <div style="display:flex;gap:0.4rem;" id="trips-filter-pills">
            <button type="button" class="boc-btn-solid active-pill" data-status="all" onclick="BusOps.setTripsFilter('all', this)">All Trips</button>
            <button type="button" class="boc-btn-solid" data-status="today" onclick="BusOps.setTripsFilter('today', this)">Today</button>
            <button type="button" class="boc-btn-solid" data-status="tomorrow" onclick="BusOps.setTripsFilter('tomorrow', this)">Tomorrow</button>
            <button type="button" class="boc-btn-solid" data-status="upcoming" onclick="BusOps.setTripsFilter('upcoming', this)">Upcoming</button>
            <button type="button" class="boc-btn-solid" data-status="completed" onclick="BusOps.setTripsFilter('completed', this)">Completed</button>
            <button type="button" class="boc-btn-solid" data-status="cancelled" onclick="BusOps.setTripsFilter('cancelled', this)">Cancelled</button>
          </div>
          <div class="boc-search-wrap" style="width:280px;">
            <svg class="boc-search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="trips-search-input" class="boc-search-input" placeholder="Filter route, bus, driver..." oninput="BusOps.renderTripsTable()">
          </div>
        </div>
      </div>

      <div class="boc-card">
        <table class="boc-table">
          <thead>
            <tr>
              <th>Departure Time</th>
              <th>Route</th>
              <th>Bus &amp; Driver</th>
              <th>Occupancy / Seats</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="boc-trips-table-body">
            <tr><td colspan="6" style="color:var(--boc-text-muted);">Loading trips…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SUB-PANEL 4: BOARDING & TICKETS -->
    <div id="boc-panel-boarding" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">Boarding &amp; Tickets</h2></div>

      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:end;">
          <div>
            <label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.4rem;">Departure</label>
            <select id="boc-boarding-departure" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="">Loading…</option></select>
          </div>
          <div style="display:flex;gap:0.5rem;">
            <button class="boc-btn-primary" id="boc-start-session" type="button">Start Session</button>
            <button class="boc-btn-solid" id="boc-stop-session" type="button" disabled>Close Boarding</button>
          </div>
        </div>
      </div>

      <div id="boc-final-manifest" class="boc-card" style="display:none;margin-bottom:1.25rem;background:var(--boc-bg);">
        <div class="boc-card-header"><h3 class="boc-card-title">Final Manifest</h3></div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.6rem;">
          <div style="background:var(--boc-card);border-radius:10px;padding:0.6rem;text-align:center;"><div style="font-size:1.2rem;font-weight:900;" id="boc-final-booked">0</div><div style="font-size:0.62rem;text-transform:uppercase;color:var(--boc-text-muted);">Booked</div></div>
          <div style="background:var(--boc-card);border-radius:10px;padding:0.6rem;text-align:center;"><div style="font-size:1.2rem;font-weight:900;color:var(--boc-green);" id="boc-final-boarded">0</div><div style="font-size:0.62rem;text-transform:uppercase;color:var(--boc-text-muted);">Boarded</div></div>
          <div style="background:var(--boc-card);border-radius:10px;padding:0.6rem;text-align:center;"><div style="font-size:1.2rem;font-weight:900;color:var(--boc-orange);" id="boc-final-noshow">0</div><div style="font-size:0.62rem;text-transform:uppercase;color:var(--boc-text-muted);">No-show</div></div>
          <div style="background:var(--boc-card);border-radius:10px;padding:0.6rem;text-align:center;"><div style="font-size:1.2rem;font-weight:900;color:var(--boc-red);" id="boc-final-cancelled">0</div><div style="font-size:0.62rem;text-transform:uppercase;color:var(--boc-text-muted);">Cancelled</div></div>
        </div>
      </div>

      <div class="boc-grid-mid" style="grid-template-columns:1.05fr .95fr;">
        <div class="boc-card" id="boc-scan-panel" style="opacity:.45;pointer-events:none;">
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.6rem;margin-bottom:1rem;">
            <div style="background:var(--boc-bg);border-radius:10px;padding:0.6rem;text-align:center;"><div style="font-size:1.2rem;font-weight:900;" id="boc-count-scanned">0</div><div style="font-size:0.62rem;text-transform:uppercase;color:var(--boc-text-muted);">Scanned</div></div>
            <div style="background:var(--boc-bg);border-radius:10px;padding:0.6rem;text-align:center;"><div style="font-size:1.2rem;font-weight:900;color:var(--boc-green);" id="boc-count-valid">0</div><div style="font-size:0.62rem;text-transform:uppercase;color:var(--boc-text-muted);">Valid</div></div>
            <div style="background:var(--boc-bg);border-radius:10px;padding:0.6rem;text-align:center;"><div style="font-size:1.2rem;font-weight:900;color:var(--boc-red);" id="boc-count-invalid">0</div><div style="font-size:0.62rem;text-transform:uppercase;color:var(--boc-text-muted);">Invalid</div></div>
            <div style="background:var(--boc-bg);border-radius:10px;padding:0.6rem;text-align:center;"><div style="font-size:1.2rem;font-weight:900;color:var(--boc-orange);" id="boc-count-duplicate">0</div><div style="font-size:0.62rem;text-transform:uppercase;color:var(--boc-text-muted);">Duplicate</div></div>
          </div>
          <div style="display:flex;gap:0.6rem;margin-bottom:0.8rem;">
            <input type="text" id="boc-code-input" class="boc-search-input" style="padding-left:0.75rem;flex:1;" placeholder="Enter ticket code, e.g. UTH-BUS-A1B2C3" autocomplete="off">
            <button class="boc-btn-primary" id="boc-verify-code" type="button">Verify</button>
          </div>
          <button class="boc-btn-solid" id="boc-toggle-camera" type="button" style="width:100%;justify-content:center;margin-bottom:0.8rem;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="7" y1="12" x2="17" y2="12"/></svg>
            Scan QR with Camera
          </button>
          <div id="boc-camera-wrap" style="display:none;position:relative;border-radius:12px;overflow:hidden;background:#000;aspect-ratio:4/3;margin-bottom:0.8rem;">
            <video id="boc-camera-video" playsinline muted style="width:100%;height:100%;object-fit:cover;"></video>
            <div style="position:absolute;inset:12%;border:3px solid rgba(255,255,255,.7);border-radius:14px;pointer-events:none;"></div>
          </div>
          <canvas id="boc-camera-canvas" style="display:none;"></canvas>
          <div id="boc-scan-result" style="display:none;border-radius:10px;padding:0.85rem 1rem;font-weight:800;"></div>
        </div>

        <div class="boc-card">
          <div class="boc-card-header"><h3 class="boc-card-title">Departure Manifest</h3></div>
          <div style="max-height:320px;overflow-y:auto;">
            <table class="boc-table">
              <thead><tr><th>Ticket</th><th>Passenger</th><th>Seat</th><th>Status</th></tr></thead>
              <tbody id="boc-manifest-body"><tr><td colspan="4" style="color:var(--boc-text-muted);">Start a session to see the manifest.</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="boc-card" style="margin-top:1.25rem;">
        <div class="boc-card-header"><h3 class="boc-card-title">Recent Scans</h3></div>
        <table class="boc-table">
          <thead><tr><th>Result</th><th>Code</th><th>Method</th><th>When</th></tr></thead>
          <tbody id="boc-recent-scans-body"><tr><td colspan="4" style="color:var(--boc-text-muted);">No scans yet.</td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- SUB-PANEL 5: REVENUE -->
    <div id="boc-panel-revenue" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">Revenue</h2></div>
      <div class="boc-kpi-grid" id="boc-revenue-kpis">
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Gross Revenue</div><div class="boc-kpi-val" id="rev-gross">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Commission</div><div class="boc-kpi-val" id="rev-commission">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Net Revenue</div><div class="boc-kpi-val" style="color:var(--boc-green);" id="rev-net">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Available Balance</div><div class="boc-kpi-val" style="color:var(--boc-green);" id="rev-available">—</div></div></div>
      </div>

      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div class="boc-card-header"><h3 class="boc-card-title">Last 30 Days</h3></div>
        <svg id="rev-trend-svg" width="100%" height="120" viewBox="0 0 600 120" preserveAspectRatio="none" style="display:block;"></svg>
      </div>

      <div class="boc-grid-mid" style="grid-template-columns:1.3fr 1fr;">
        <div class="boc-card">
          <div class="boc-card-header"><h3 class="boc-card-title">Recent Transactions</h3></div>
          <table class="boc-table"><thead><tr><th>Route</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody id="rev-transactions-body"><tr><td colspan="5" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
        </div>

        <div class="boc-card">
          <div class="boc-card-header"><h3 class="boc-card-title">Payout Accounts</h3></div>
          <div id="rev-accounts-list" style="margin-bottom:0.8rem;"><div style="color:var(--boc-text-muted);font-size:0.8rem;">Loading…</div></div>
          <div id="rev-readiness" style="margin-bottom:0.8rem;"></div>

          <div style="display:flex;gap:0.4rem;margin-bottom:0.9rem;border-top:1px solid var(--boc-border);padding-top:0.9rem;">
            <button type="button" class="boc-btn-solid rev-method-toggle active" data-method="MOBILE_MONEY" style="flex:1;justify-content:center;">Mobile Money</button>
            <button type="button" class="boc-btn-solid rev-method-toggle" data-method="BANK" style="flex:1;justify-content:center;">Bank</button>
          </div>

          <div id="rev-mobile-money-forms">
            <div style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);margin-bottom:0.35rem;display:flex;justify-content:space-between;">Airtel Money agent code <span id="rev-mm-status-airtel" class="boc-tag tag-low">Not configured</span></div>
            <form class="rev-mm-form" data-provider="Airtel Money" style="display:flex;gap:0.4rem;margin-bottom:0.9rem;">
              <input type="text" name="account_name" required placeholder="Account holder name" class="boc-search-input" style="padding-left:0.6rem;flex:1;">
              <input type="text" name="account_number" required pattern="[0-9]{6,20}" title="6-20 digit agent code" placeholder="Agent code" class="boc-search-input" style="padding-left:0.6rem;flex:1;">
              <button type="submit" class="boc-btn-solid">Save</button>
            </form>
            <div style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);margin-bottom:0.35rem;display:flex;justify-content:space-between;">TNM Mpamba agent code <span id="rev-mm-status-tnm" class="boc-tag tag-low">Not configured</span></div>
            <form class="rev-mm-form" data-provider="TNM Mpamba" style="display:flex;gap:0.4rem;">
              <input type="text" name="account_name" required placeholder="Account holder name" class="boc-search-input" style="padding-left:0.6rem;flex:1;">
              <input type="text" name="account_number" required pattern="[0-9]{6,20}" title="6-20 digit agent code" placeholder="Agent code" class="boc-search-input" style="padding-left:0.6rem;flex:1;">
              <button type="submit" class="boc-btn-solid">Save</button>
            </form>
            <p class="text-muted" style="font-size:0.68rem;margin-top:0.6rem;color:var(--boc-text-muted);">Both Airtel Money and TNM Mpamba must be configured before mobile money payouts are ready.</p>
          </div>

          <form id="rev-bank-form" style="display:none;flex-direction:column;gap:0.5rem;">
            <select name="provider" id="rev-bank-select" required class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="">Loading banks…</option></select>
            <input type="text" name="account_name" required placeholder="Account holder name" class="boc-search-input" style="padding-left:0.75rem;width:100%;">
            <input type="text" name="account_number" required pattern="[0-9]{6,20}" title="6-20 digit account number" placeholder="Account number" class="boc-search-input" style="padding-left:0.75rem;width:100%;">
            <button type="submit" class="boc-btn-solid" style="justify-content:center;">+ Add Bank Account</button>
          </form>
        </div>
      </div>

      <div class="boc-card" style="margin-top:1.25rem;">
        <div class="boc-card-header">
          <h3 class="boc-card-title">Withdrawals</h3>
          <div style="display:flex;gap:0.5rem;align-items:center;">
            <input type="number" id="rev-withdraw-amount" placeholder="Amount" class="boc-search-input" style="padding-left:0.75rem;width:140px;">
            <select id="rev-withdraw-account" class="boc-search-input" style="padding-left:0.75rem;width:180px;"></select>
            <button class="boc-btn-primary" id="rev-request-withdrawal" type="button">Request Withdrawal</button>
          </div>
        </div>
        <table class="boc-table"><thead><tr><th>Amount</th><th>Destination</th><th>Status</th><th>Requested</th></tr></thead><tbody id="rev-withdrawals-body"><tr><td colspan="4" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>
    </div>

    <!-- SUB-PANEL 6: ANALYTICS -->
    <div id="boc-panel-analytics" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">Analytics</h2></div>
      <div class="boc-kpi-grid" id="boc-analytics-kpis">
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Gross Revenue</div><div class="boc-kpi-val" id="an-gross">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Tickets Sold</div><div class="boc-kpi-val" id="an-tickets">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Average Ticket Price</div><div class="boc-kpi-val" id="an-avg">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Active Routes</div><div class="boc-kpi-val" id="an-routes">—</div></div></div>
      </div>
      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div class="boc-card-header"><h3 class="boc-card-title">Last 30 Days</h3></div>
        <svg id="an-trend-svg" width="100%" height="120" viewBox="0 0 600 120" preserveAspectRatio="none" style="display:block;"></svg>
      </div>
      <div class="boc-card">
        <div class="boc-card-header"><h3 class="boc-card-title">Revenue by Route</h3></div>
        <table class="boc-table"><thead><tr><th>Route</th><th>Tickets</th><th>Revenue</th></tr></thead><tbody id="an-routes-body"><tr><td colspan="3" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>
    </div>

    <!-- SUB-PANEL 6.5: REPORTS EXPORT CENTER -->
    <div id="boc-panel-reports" class="boc-panel" style="display:none;">
      <div class="boc-card-header">
        <div>
          <h2 class="boc-card-title">Reports &amp; Operational Exports</h2>
          <div style="font-size:0.78rem;color:var(--boc-text-muted);margin-top:2px;">Generate and export structured operational, ticketing, boarding, revenue and fleet reports.</div>
        </div>
      </div>

      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:0.8rem;align-items:end;">
          <div>
            <label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">Report Category</label>
            <select id="rpt-type-select" class="boc-search-input" style="padding-left:0.75rem;width:100%;">
              <option value="trips">Trip Operational Manifest</option>
              <option value="tickets">Ticket Sales &amp; Revenue</option>
              <option value="passengers">Passenger Manifest History</option>
              <option value="boarding">Boarding Check-in Verification</option>
              <option value="fleet">Fleet Assets &amp; Availability</option>
              <option value="drivers">Driver Duty &amp; Hours Log</option>
              <option value="maintenance">Vehicle Maintenance &amp; Service</option>
            </select>
          </div>
          <div>
            <label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">From Date</label>
            <input type="date" id="rpt-from-date" class="boc-search-input" style="padding-left:0.75rem;width:100%;">
          </div>
          <div>
            <label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">To Date</label>
            <input type="date" id="rpt-to-date" class="boc-search-input" style="padding-left:0.75rem;width:100%;">
          </div>
          <div style="display:flex;gap:0.4rem;">
            <button class="boc-btn-primary" type="button" onclick="BusOps.generateReport()">Generate</button>
            <button class="boc-btn-solid" type="button" onclick="BusOps.exportReport('csv')">CSV Export</button>
            <button class="boc-btn-solid" type="button" onclick="BusOps.exportReport('pdf')">PDF Export</button>
          </div>
        </div>
      </div>

      <div class="boc-card">
        <div class="boc-card-header"><h3 class="boc-card-title" id="rpt-title">Report Results Preview</h3></div>
        <div id="rpt-output-wrap" style="overflow-x:auto;">
          <table class="boc-table">
            <thead id="rpt-table-head"><tr><th>Select report parameters above and click Generate.</th></tr></thead>
            <tbody id="rpt-table-body"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- SUB-PANEL 7: PASSENGERS -->
    <div id="boc-panel-passengers" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">Passengers</h2></div>
      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div style="display:flex;gap:0.6rem;align-items:end;">
          <div style="flex:1;">
            <label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">Search</label>
            <input type="text" id="pax-search" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="Name or phone">
          </div>
          <button class="boc-btn-primary" id="pax-search-btn" type="button">Search</button>
        </div>
      </div>
      <div class="boc-card">
        <table class="boc-table"><thead><tr><th>Name</th><th>Phone</th><th>Tickets</th><th>Spend</th><th>Routes</th><th>Last Trip</th></tr></thead><tbody id="pax-body"><tr><td colspan="6" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>
    </div>

    <!-- SUB-PANEL 8: DRIVERS -->
    <div id="boc-panel-drivers" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">Drivers</h2><button class="boc-btn-primary" id="boc-new-driver-btn" type="button">+ Add Driver</button></div>
      <div class="boc-kpi-grid" id="drv-kpi-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Total Drivers</div><div class="boc-kpi-val" id="drv-kpi-total">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Active</div><div class="boc-kpi-val" style="color:var(--boc-green);" id="drv-kpi-active">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">On Trip</div><div class="boc-kpi-val" id="drv-kpi-on-trip">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">License Issues</div><div class="boc-kpi-val" style="color:var(--boc-red);" id="drv-kpi-license-issues">—</div></div></div>
      </div>
      <div class="boc-card">
        <table class="boc-table"><thead><tr><th>Name</th><th>Phone</th><th>License</th><th>License Expiry</th><th>Status</th><th></th></tr></thead><tbody id="drv-body"><tr><td colspan="6" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>
    </div>

    <!-- SUB-PANEL 9: MAINTENANCE -->
    <div id="boc-panel-maintenance" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">Maintenance</h2></div>
      <div class="boc-kpi-grid" id="mnt-kpi-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Document Issues</div><div class="boc-kpi-val" style="color:var(--boc-red);" id="mnt-kpi-doc-issues">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Open Issues</div><div class="boc-kpi-val" style="color:var(--boc-orange);" id="mnt-kpi-open-issues">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Serviced Last 30 Days</div><div class="boc-kpi-val" style="color:var(--boc-green);" id="mnt-kpi-services">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Total Cost (30d)</div><div class="boc-kpi-val" id="mnt-kpi-cost">—</div></div></div>
      </div>
      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div class="boc-card-header"><h3 class="boc-card-title">Document Expiry</h3></div>
        <table class="boc-table"><thead><tr><th>Vehicle</th><th>Document</th><th>Expires</th><th>Status</th></tr></thead><tbody id="mnt-documents-body"><tr><td colspan="4" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>
      <div class="boc-grid-mid" style="grid-template-columns:1fr 1fr;">
        <div class="boc-card">
          <div class="boc-card-header"><h3 class="boc-card-title">Recent Service History</h3></div>
          <table class="boc-table"><thead><tr><th>Vehicle</th><th>Service</th><th>Date</th><th>Mileage</th><th>Cost</th></tr></thead><tbody id="mnt-history-body"><tr><td colspan="5" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
          <form id="mnt-maintenance-form" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-top:0.6rem;">
            <select name="vehicle_id" required class="boc-search-input" style="padding-left:0.6rem;width:100%;grid-column:1/-1;"><option value="">Select vehicle…</option></select>
            <input type="text" name="service_type" required placeholder="e.g. Oil change" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
            <input type="date" name="serviced_at" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
            <input type="number" name="mileage_km" placeholder="Mileage" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
            <input type="number" name="cost" min="0" step="0.01" placeholder="Cost (MWK)" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
            <button type="submit" class="boc-btn-solid" style="justify-content:center;">+ Log Service</button>
          </form>
        </div>
        <div class="boc-card">
          <div class="boc-card-header"><h3 class="boc-card-title">Open Issues</h3></div>
          <table class="boc-table"><thead><tr><th>Vehicle</th><th>Issue</th><th>Severity</th><th>Cost</th><th></th></tr></thead><tbody id="mnt-issues-body"><tr><td colspan="5" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
          <form id="mnt-issue-form" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-top:0.6rem;">
            <select name="vehicle_id" required class="boc-search-input" style="padding-left:0.6rem;width:100%;grid-column:1/-1;"><option value="">Select vehicle…</option></select>
            <input type="text" name="category" required placeholder="e.g. Brakes" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
            <input type="text" name="description" required placeholder="Describe the issue" class="boc-search-input" style="padding-left:0.6rem;width:100%;grid-column:1/-1;">
            <select name="severity" class="boc-search-input" style="padding-left:0.6rem;width:100%;"><option value="low">Low</option><option value="medium">Medium</option><option value="critical">Critical</option></select>
            <input type="number" name="cost" min="0" step="0.01" placeholder="Cost (MWK)" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
            <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.78rem;color:var(--boc-text-soft);grid-column:1/-1;cursor:pointer;"><input type="checkbox" name="mark_out_of_service"> Mark this bus out of service now</label>
            <button type="submit" class="boc-btn-solid" style="justify-content:center;grid-column:1/-1;">+ Report Issue</button>
          </form>
        </div>
      </div>
    </div>

    <!-- SUB-PANEL 10: TICKETS (standalone search/cancel) -->
    <div id="boc-panel-tickets" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">Tickets</h2></div>
      <div class="boc-kpi-grid" id="tix-kpi-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Today's Departures</div><div class="boc-kpi-val" id="tix-kpi-departures">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Tickets Sold</div><div class="boc-kpi-val" id="tix-kpi-sold">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Available Seats</div><div class="boc-kpi-val" id="tix-kpi-seats">—</div></div></div>
        <div class="boc-kpi-card"><div><div class="boc-kpi-title">Revenue</div><div class="boc-kpi-val" style="color:var(--boc-green);" id="tix-kpi-revenue">—</div></div></div>
      </div>
      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto auto auto;gap:0.6rem;align-items:end;">
          <div><label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">Ticket code</label><input type="text" id="tix-filter-code" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="UTH-BUS-…"></div>
          <div><label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">Passenger</label><input type="text" id="tix-filter-passenger" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="Name or phone"></div>
          <div><label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">Route</label><input type="text" id="tix-filter-route" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. Blantyre"></div>
          <div><label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">Status</label><select id="tix-filter-status" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="">Any</option><option value="issued">Issued</option><option value="boarded">Boarded</option><option value="no_show">No-show</option><option value="cancelled">Cancelled</option></select></div>
          <div><label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">From</label><input type="date" id="tix-filter-date-from" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);display:block;margin-bottom:0.3rem;">To</label><input type="date" id="tix-filter-date-to" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <button class="boc-btn-primary" id="tix-search-btn" type="button">Search</button>
        </div>
      </div>
      <div class="boc-card">
        <table class="boc-table"><thead><tr><th>Ticket</th><th>Passenger</th><th>Route</th><th>Departs</th><th>Status</th><th></th></tr></thead><tbody id="tix-body"><tr><td colspan="6" style="color:var(--boc-text-muted);">Search to see tickets.</td></tr></tbody></table>
      </div>
    </div>

    <!-- Ticket detail modal -->
    <div id="boc-ticket-detail-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(6,16,29,.6);align-items:center;justify-content:center;padding:1.4rem;">
      <div style="width:440px;max-width:100%;background:var(--boc-card);border:1px solid var(--boc-border);border-radius:16px;">
        <div class="boc-card-header" style="padding:1rem 1.2rem;border-bottom:1px solid var(--boc-border);"><h3 class="boc-card-title">Ticket Detail</h3><button type="button" onclick="document.getElementById('boc-ticket-detail-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.3rem;cursor:pointer;">&times;</button></div>
        <div id="tix-detail-body" style="padding:1.1rem 1.2rem;"></div>
        <div style="padding:1rem 1.2rem;border-top:1px solid var(--boc-border);display:flex;justify-content:flex-end;gap:0.6rem;">
          <button type="button" class="boc-btn-solid" onclick="document.getElementById('boc-ticket-detail-modal').style.display='none'">Close</button>
          <button type="button" class="boc-btn-primary" id="tix-detail-cancel-btn" style="background:var(--boc-red);">Cancel Ticket</button>
        </div>
      </div>
    </div>

    <!-- SUB-PANEL 11: COMPANY SETTINGS -->
    <div id="boc-panel-company" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">Company Settings</h2></div>
      <div class="boc-card" style="max-width:520px;">
        <form id="settings-form" style="display:flex;flex-direction:column;gap:0.8rem;">
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Business / operator name</label><input type="text" name="business_name" required class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Account email</label><input type="text" id="settings-email" disabled class="boc-search-input" style="padding-left:0.75rem;width:100%;opacity:0.6;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Phone</label><input type="text" name="phone" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Address</label><input type="text" name="address" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">City</label><input type="text" name="city" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Description</label><textarea name="description" rows="3" class="boc-search-input" style="padding:0.6rem 0.75rem;width:100%;height:auto;"></textarea></div>
          <div style="display:flex;align-items:center;gap:0.5rem;"><span style="font-size:0.72rem;color:var(--boc-text-soft);">Approval status:</span><span class="boc-tag" id="settings-approval-badge">—</span></div>
          <button type="submit" class="boc-btn-primary" style="justify-content:center;">Save Changes</button>
        </form>
      </div>

      <div class="boc-card" style="max-width:900px;margin-top:1.25rem;">
        <div class="boc-card-header"><h3 class="boc-card-title">Ticket Template</h3></div>
        <p class="text-muted" style="font-size:0.78rem;color:var(--boc-text-soft);margin-bottom:1rem;">Choose how your customers' bus tickets look. Your logo and business name always lead — the ticket number and QR code used for boarding are identical across every style.</p>
        <div style="display:grid;grid-template-columns:280px 1fr;gap:1.5rem;align-items:start;">
          <form id="ticket-template-form" style="display:flex;flex-direction:column;gap:0.7rem;">
            <div id="tt-style-gallery" style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.4rem;">
              <label class="tt-style-swatch" data-style="classic_blue"><span style="background:#1d4ed8;"></span>Classic Blue</label>
              <label class="tt-style-swatch" data-style="modern_card"><span style="background:#0b1120;"></span>Modern Card</label>
              <label class="tt-style-swatch" data-style="minimal_white"><span style="background:#f8fafc;border:1px solid #e5e7eb;"></span>Minimal White</label>
              <label class="tt-style-swatch" data-style="premium_dark"><span style="background:#0a0a0a;"></span>Premium Dark</label>
              <label class="tt-style-swatch" data-style="mobile_wallet"><span style="background:#6d28d9;"></span>Mobile Wallet</label>
            </div>
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Logo URL (optional)</label><input type="url" id="tt-logo-url" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="https://example.com/logo.png"></div>
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Accent color</label><input type="color" id="tt-accent-color" style="width:100%;height:38px;border:1px solid var(--boc-border);border-radius:8px;background:var(--boc-bg);"></div>
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Footer message (optional)</label><textarea id="tt-footer-message" maxlength="300" rows="2" class="boc-search-input" style="padding:0.5rem 0.75rem;width:100%;height:auto;" placeholder="Thank you for traveling with us."></textarea></div>
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Contact phone (optional)</label><input type="text" id="tt-contact-phone" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Contact email (optional)</label><input type="email" id="tt-contact-email" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
            <button type="submit" class="boc-btn-primary" style="justify-content:center;">Save Ticket Template</button>
          </form>
          <div>
            <div style="font-size:0.68rem;font-weight:700;color:var(--boc-text-muted);text-transform:uppercase;letter-spacing:0.03em;margin-bottom:0.6rem;">Live preview</div>
            <div id="tt-preview"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SUB-PANEL 12: USER MANAGEMENT -->
    <div id="boc-panel-users" class="boc-panel" style="display:none;">
      <div class="boc-card-header"><h2 class="boc-card-title">User Management</h2><button class="boc-btn-primary" id="um-invite-btn" type="button">+ Invite Staff</button></div>
      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div class="boc-card-header"><h3 class="boc-card-title">Team</h3></div>
        <table class="boc-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody id="um-staff-body"><tr><td colspan="5" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>
      <div class="boc-card" style="margin-bottom:1.25rem;">
        <div class="boc-card-header"><h3 class="boc-card-title">Pending Invitations</h3></div>
        <table class="boc-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Expires</th><th></th></tr></thead><tbody id="um-invitations-body"><tr><td colspan="6" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>
      <div class="boc-card">
        <div class="boc-card-header"><h3 class="boc-card-title">Roles &amp; Permissions</h3><button class="boc-btn-solid" id="um-new-role-btn" type="button">+ New Role</button></div>
        <table class="boc-table"><thead><tr><th>Role</th><th>Scope</th><th>Members</th><th></th></tr></thead><tbody id="um-roles-body"><tr><td colspan="4" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>
    </div>

  </div>
</main>


<!-- ═══════════════ 1. ADD BUS WIZARD (2-COLUMN SHELL) ═══════════════ -->
<div id="boc-vehicle-modal" class="boc-wiz-modal" style="display:none;">
  <div class="boc-wiz-shell">
    
    <div class="boc-wiz-header">
      <div>
        <div class="boc-wiz-header-sub">STEP <span id="veh-step-num">1</span> OF 6</div>
        <h3 class="boc-wiz-header-title">Add Bus Asset</h3>
      </div>
      <button type="button" onclick="document.getElementById('boc-vehicle-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.6rem;cursor:pointer;">&times;</button>
    </div>

    <div class="boc-wiz-body">
      <!-- Left Progress Sidebar -->
      <div class="boc-wiz-sidebar">
        <div class="boc-wiz-side-step active" data-wiz-step="1" onclick="BusOps.setWizStep('veh', 1)"><span class="boc-wiz-side-num">01</span><span>Identity</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="2" onclick="BusOps.setWizStep('veh', 2)"><span class="boc-wiz-side-num">02</span><span>Specifications</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="3" onclick="BusOps.setWizStep('veh', 3)"><span class="boc-wiz-side-num">03</span><span>Seat Layout</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="4" onclick="BusOps.setWizStep('veh', 4)"><span class="boc-wiz-side-num">04</span><span>Documents</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="5" onclick="BusOps.setWizStep('veh', 5)"><span class="boc-wiz-side-num">05</span><span>Operations</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="6" onclick="BusOps.setWizStep('veh', 6)"><span class="boc-wiz-side-num">06</span><span>Review &amp; Activate</span></div>
      </div>

      <!-- Right Main Form Content -->
      <form id="boc-vehicle-form" class="boc-wiz-main">
        <div>
          <!-- STEP 1: IDENTITY -->
          <div class="wiz-step-pane active" data-wiz-pane="veh-1">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">01 BUS IDENTITY</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.9rem;">
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Registration Number</label><input type="text" name="reg_number" required class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. NA 4821"></div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Fleet Number</label><input type="text" name="fleet_number" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. FLEET-21"></div>
              <div>
                <label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Vehicle Type</label>
                <select name="vehicle_type" class="boc-search-input" style="padding-left:0.75rem;width:100%;">
                  <option value="">Select a type…</option>
                  <option value="Coaster">Coaster</option>
                  <option value="Coach Bus">Intercity Coach Bus</option>
                  <option value="Luxury Cruiser">VIP Luxury Cruiser</option>
                  <option value="Shuttle">Express Shuttle</option>
                  <option value="Minibus">Minibus</option>
                </select>
              </div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Manufacturer</label><input type="text" name="manufacturer" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. Toyota"></div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Model</label><input type="text" name="make_model" required class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. Coaster"></div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Year</label><input type="number" name="year" min="1980" max="2100" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. 2024"></div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Colour</label><input type="text" name="color" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. White"></div>
            </div>

            <div style="margin-top:1rem;">
              <label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Upload Bus Image</label>
              <div class="boc-dropzone" onclick="document.getElementById('veh-image-file').click()" style="padding:1rem;text-align:center;border:2px dashed var(--boc-border);border-radius:12px;cursor:pointer;">
                <input type="file" id="veh-image-file" accept="image/*" style="display:none" onchange="BusOps.handleFileUpload(this, 'image', 'veh-image-url', 'veh-image-preview')">
                <input type="hidden" id="veh-image-url" name="photo_url">
                <div id="veh-image-preview">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--boc-primary);"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                  <div style="font-size:0.8rem;font-weight:700;color:var(--boc-text);margin-top:0.3rem;">Click or drag bus image here to upload</div>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 2: SPECIFICATIONS -->
          <div class="wiz-step-pane" data-wiz-pane="veh-2" style="display:none;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">02 SPECIFICATIONS</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.9rem;margin-bottom:1.2rem;">
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Passenger Capacity</label><input type="number" name="capacity" id="veh-cap-input" min="1" required class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. 29" oninput="BusOps.rebuildSeatGrid()"></div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Standing Capacity</label><input type="number" name="standing_capacity" min="0" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="0"></div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Luggage Capacity</label><input type="text" name="luggage_capacity" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. 12 bags"></div>
            </div>

            <h5 style="font-size:0.8rem;font-weight:800;color:var(--boc-primary);margin-bottom:0.6rem;">Accessibility</h5>
            <div style="display:flex;gap:1.2rem;margin-bottom:1rem;">
              <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;color:var(--boc-text);cursor:pointer;"><input type="checkbox" name="acc_wheelchair"> Wheelchair accessible</label>
              <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;color:var(--boc-text);cursor:pointer;"><input type="checkbox" name="acc_step"> Step access</label>
            </div>

            <h5 style="font-size:0.8rem;font-weight:800;color:var(--boc-primary);margin-bottom:0.6rem;">Amenities</h5>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
              <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;color:var(--boc-text);cursor:pointer;"><input type="checkbox" name="amenity_ac"> Air conditioning</label>
              <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;color:var(--boc-text);cursor:pointer;"><input type="checkbox" name="amenity_usb"> USB charging</label>
              <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;color:var(--boc-text);cursor:pointer;"><input type="checkbox" name="amenity_wifi"> Wi-Fi</label>
              <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;color:var(--boc-text);cursor:pointer;"><input type="checkbox" name="amenity_luggage"> Luggage compartment</label>
            </div>
          </div>

          <!-- STEP 3: SEAT LAYOUT BUILDER -->
          <div class="wiz-step-pane" data-wiz-pane="veh-3" style="display:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.8rem;flex-wrap:wrap;gap:0.4rem;">
              <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin:0;">03 SEAT LAYOUT BUILDER</h4>
              <div style="font-size:0.75rem;font-weight:800;color:var(--boc-green);" id="veh-seat-count-val">Physical seats: 0 · Sellable: 0 · Blocked: 0</div>
            </div>
            <div style="font-size:0.72rem;color:var(--boc-text-muted);margin-bottom:0.8rem;">Click a seat to select it, then use Mark/Unmark Blocked. Only the total seat count is saved as this bus's capacity — the layout below is a visual planning aid.</div>

            <div style="display:flex;gap:0.4rem;margin-bottom:1rem;flex-wrap:wrap;">
              <button class="boc-btn-solid" type="button" onclick="BusOps.seatGridAddRow()">+ Add Row</button>
              <button class="boc-btn-solid" type="button" onclick="BusOps.seatGridRemoveRow()">− Remove Row</button>
              <button class="boc-btn-solid" type="button" onclick="BusOps.seatGridAddSeat()">+ Add Seat</button>
              <button class="boc-btn-solid" type="button" onclick="BusOps.seatGridRemoveSeat()">− Remove Seat</button>
              <button class="boc-btn-solid" type="button" onclick="BusOps.seatGridToggleBlocked()">Mark/Unmark Blocked</button>
            </div>

            <div style="background:var(--boc-sidebar);border:1px solid var(--boc-border);border-radius:12px;padding:1.2rem;display:flex;flex-direction:column;align-items:center;">
              <div style="font-size:0.68rem;font-weight:800;color:var(--boc-text-muted);text-transform:uppercase;margin-bottom:0.8rem;">FRONT ↓ [ DRIVER ]</div>
              <div id="veh-wiz-seat-grid" style="display:grid;grid-template-columns:repeat(4, 44px);gap:8px;justify-content:center;"></div>
            </div>
          </div>

          <!-- STEP 4: DOCUMENTS -->
          <div class="wiz-step-pane" data-wiz-pane="veh-4" style="display:none;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:0.4rem;">04 BUS DOCUMENTS</h4>
            <div style="font-size:0.72rem;color:var(--boc-text-muted);margin-bottom:1rem;">Optional — add expiry dates now, or skip and add them later from the Fleet tab.</div>
            <div style="display:flex;flex-direction:column;gap:0.8rem;">
              <div style="background:var(--boc-sidebar);border:1px solid var(--boc-border);border-radius:10px;padding:0.85rem;display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;">
                <strong style="color:var(--boc-text);font-size:0.82rem;flex:1;min-width:140px;">Vehicle Registration</strong>
                <input type="date" class="boc-search-input" style="padding-left:0.6rem;width:150px;" data-doc-type="registration" data-doc-expiry>
                <button class="boc-btn-solid" type="button" onclick="document.getElementById('doc-reg-file').click()">Upload</button>
                <input type="file" id="doc-reg-file" style="display:none" onchange="BusOps.handleFileUpload(this, 'document', 'doc-reg-url', 'doc-reg-status')">
                <input type="hidden" data-doc-type="registration" data-doc-file id="doc-reg-url">
                <span id="doc-reg-status" style="font-size:0.72rem;color:var(--boc-text-muted);">Not yet added</span>
              </div>
              <div style="background:var(--boc-sidebar);border:1px solid var(--boc-border);border-radius:10px;padding:0.85rem;display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;">
                <strong style="color:var(--boc-text);font-size:0.82rem;flex:1;min-width:140px;">Insurance</strong>
                <input type="date" class="boc-search-input" style="padding-left:0.6rem;width:150px;" data-doc-type="insurance" data-doc-expiry>
                <button class="boc-btn-solid" type="button" onclick="document.getElementById('doc-ins-file').click()">Upload</button>
                <input type="file" id="doc-ins-file" style="display:none" onchange="BusOps.handleFileUpload(this, 'document', 'doc-ins-url', 'doc-ins-status')">
                <input type="hidden" data-doc-type="insurance" data-doc-file id="doc-ins-url">
                <span id="doc-ins-status" style="font-size:0.72rem;color:var(--boc-text-muted);">Not yet added</span>
              </div>
              <div style="background:var(--boc-sidebar);border:1px solid var(--boc-border);border-radius:10px;padding:0.85rem;display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;">
                <strong style="color:var(--boc-text);font-size:0.82rem;flex:1;min-width:140px;">Roadworthiness</strong>
                <input type="date" class="boc-search-input" style="padding-left:0.6rem;width:150px;" data-doc-type="roadworthiness" data-doc-expiry>
                <button class="boc-btn-solid" type="button" onclick="document.getElementById('doc-rw-file').click()">Upload</button>
                <input type="file" id="doc-rw-file" style="display:none" onchange="BusOps.handleFileUpload(this, 'document', 'doc-rw-url', 'doc-rw-status')">
                <input type="hidden" data-doc-type="roadworthiness" data-doc-file id="doc-rw-url">
                <span id="doc-rw-status" style="font-size:0.72rem;color:var(--boc-text-muted);">Not yet added</span>
              </div>
              <div style="background:var(--boc-sidebar);border:1px solid var(--boc-border);border-radius:10px;padding:0.85rem;display:flex;align-items:center;gap:0.7rem;flex-wrap:wrap;">
                <strong style="color:var(--boc-text);font-size:0.82rem;flex:1;min-width:140px;">Operating Permit</strong>
                <input type="date" class="boc-search-input" style="padding-left:0.6rem;width:150px;" data-doc-type="operating_permit" data-doc-expiry>
                <button class="boc-btn-solid" type="button" onclick="document.getElementById('doc-op-file').click()">Upload</button>
                <input type="file" id="doc-op-file" style="display:none" onchange="BusOps.handleFileUpload(this, 'document', 'doc-op-url', 'doc-op-status')">
                <input type="hidden" data-doc-type="operating_permit" data-doc-file id="doc-op-url">
                <span id="doc-op-status" style="font-size:0.72rem;color:var(--boc-text-muted);">Not yet added</span>
              </div>
            </div>
          </div>

          <!-- STEP 5: OPERATIONS -->
          <div class="wiz-step-pane" data-wiz-pane="veh-5" style="display:none;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">05 OPERATIONAL SETTINGS</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.9rem;">
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Default Status</label><select name="status" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="active">Available</option><option value="maintenance">Maintenance</option><option value="inactive">Inactive</option></select></div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Maintenance Threshold (km)</label><input type="number" name="maintenance_threshold_km" min="0" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. 85000"></div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Default Boarding Buffer (minutes)</label><input type="number" name="boarding_buffer_minutes" min="0" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. 30"></div>
              <div style="display:flex;align-items:center;margin-top:1.2rem;"><label style="font-size:0.8rem;color:var(--boc-text);cursor:pointer;"><input type="checkbox" name="gps_enabled"> GPS-enabled tracking</label></div>
            </div>
          </div>

          <!-- STEP 6: REVIEW & ACTIVATE -->
          <div class="wiz-step-pane" data-wiz-pane="veh-6" style="display:none;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">06 REVIEW &amp; ACTIVATE</h4>
            <div id="veh-wiz-review" class="boc-wiz-review-card"></div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="boc-wiz-footer">
          <button type="button" class="boc-btn-solid" id="veh-wiz-prev-btn" onclick="BusOps.stepWiz('veh', -1)" style="visibility:hidden;">Back</button>
          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="boc-btn-solid" onclick="document.getElementById('boc-vehicle-modal').style.display='none'">Cancel</button>
            <button type="button" class="boc-btn-primary" id="veh-wiz-next-btn" onclick="BusOps.stepWiz('veh', 1)">Continue</button>
            <button type="submit" class="boc-btn-primary" id="veh-wiz-submit-btn" style="display:none;">Activate Bus</button>
          </div>
        </div>
      </form>
    </div>

  </div>
</div>

<!-- ═══════════════ Vehicle detail modal (tabbed: Overview / Documents / Maintenance / Issues / Assignments) ═══════════════ -->
<div id="boc-vehicle-detail-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(6,16,29,.6);align-items:center;justify-content:center;padding:1.4rem;">
  <div style="width:600px;max-width:100%;max-height:88vh;overflow-y:auto;background:var(--boc-card);border:1px solid var(--boc-border);border-radius:16px;">
    <div class="boc-card-header" style="padding:1rem 1.2rem;border-bottom:1px solid var(--boc-border);"><h3 class="boc-card-title" id="veh-detail-title">Vehicle</h3><button type="button" onclick="document.getElementById('boc-vehicle-detail-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.3rem;cursor:pointer;">&times;</button></div>
    <div style="display:flex;gap:0.3rem;padding:0.8rem 1.2rem 0;border-bottom:1px solid var(--boc-border);">
      <button type="button" class="veh-tab-btn active" data-veh-tab="overview">Overview</button>
      <button type="button" class="veh-tab-btn" data-veh-tab="documents">Documents</button>
      <button type="button" class="veh-tab-btn" data-veh-tab="maintenance">Maintenance</button>
      <button type="button" class="veh-tab-btn" data-veh-tab="issues">Issues</button>
      <button type="button" class="veh-tab-btn" data-veh-tab="assignments">Assignments</button>
    </div>
    <div style="padding:1.1rem 1.2rem;">

      <div class="veh-tab-panel active" data-veh-panel="overview">
        <div id="veh-overview-summary" style="margin-bottom:1rem;font-size:0.85rem;color:var(--boc-text-soft);"></div>
        <label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Status</label>
        <select id="veh-status-select" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="active">Active</option><option value="maintenance">In Maintenance</option><option value="inactive">Inactive</option></select>
      </div>

      <div class="veh-tab-panel" data-veh-panel="documents" style="display:none;">
        <table class="boc-table"><thead><tr><th>Type</th><th>Expires</th><th>Status</th></tr></thead><tbody id="veh-documents-body"></tbody></table>
        <form id="veh-document-form" style="display:grid;grid-template-columns:1.2fr 1fr auto;gap:0.5rem;margin-top:0.6rem;">
          <input type="text" name="document_type" required placeholder="e.g. Road Worthiness" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
          <input type="date" name="expiry_date" required class="boc-search-input" style="padding-left:0.6rem;width:100%;">
          <button type="submit" class="boc-btn-solid">Save</button>
        </form>
      </div>

      <div class="veh-tab-panel" data-veh-panel="maintenance" style="display:none;">
        <table class="boc-table"><thead><tr><th>Service</th><th>Date</th><th>Mileage</th><th>Cost</th></tr></thead><tbody id="veh-maintenance-body"><tr><td colspan="4" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
        <form id="veh-maintenance-form" style="display:grid;grid-template-columns:1.2fr .9fr .7fr .7fr auto;gap:0.5rem;margin-top:0.6rem;">
          <input type="text" name="service_type" required placeholder="e.g. Oil change" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
          <input type="date" name="serviced_at" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
          <input type="number" name="mileage_km" placeholder="Mileage" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
          <input type="number" name="cost" min="0" step="0.01" placeholder="Cost" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
          <button type="submit" class="boc-btn-solid">Log</button>
        </form>
      </div>

      <div class="veh-tab-panel" data-veh-panel="issues" style="display:none;">
        <table class="boc-table"><thead><tr><th>Category</th><th>Severity</th><th>Cost</th><th>Status</th><th></th></tr></thead><tbody id="veh-issues-body"><tr><td colspan="5" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
        <form id="veh-issue-form" style="display:grid;grid-template-columns:1fr 1.2fr .7fr .7fr auto;gap:0.5rem;margin-top:0.6rem;">
          <input type="text" name="category" required placeholder="e.g. Brakes" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
          <input type="text" name="description" required placeholder="Describe the issue" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
          <select name="severity" class="boc-search-input" style="padding-left:0.6rem;width:100%;"><option value="low">Low</option><option value="medium">Medium</option><option value="critical">Critical</option></select>
          <input type="number" name="cost" min="0" step="0.01" placeholder="Cost" class="boc-search-input" style="padding-left:0.6rem;width:100%;">
          <button type="submit" class="boc-btn-solid">Report</button>
        </form>
      </div>

      <div class="veh-tab-panel" data-veh-panel="assignments" style="display:none;">
        <table class="boc-table"><thead><tr><th>Trip</th><th>Departure</th><th>Status</th></tr></thead><tbody id="veh-assignments-body"><tr><td colspan="3" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>

    </div>
  </div>
</div>

<!-- ═══════════════ 5. ADD DRIVER WIZARD (2-COLUMN SHELL) ═══════════════ -->
<div id="boc-driver-modal" class="boc-wiz-modal" style="display:none;">
  <div class="boc-wiz-shell">
    
    <div class="boc-wiz-header">
      <div>
        <div class="boc-wiz-header-sub">STEP <span id="drv-step-num">1</span> OF 6</div>
        <h3 class="boc-wiz-header-title">Add Driver</h3>
      </div>
      <button type="button" onclick="document.getElementById('boc-driver-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.6rem;cursor:pointer;">&times;</button>
    </div>

    <div class="boc-wiz-body">
      <div class="boc-wiz-sidebar">
        <div class="boc-wiz-side-step active" data-wiz-step="1" onclick="BusOps.setWizStep('drv', 1)"><span class="boc-wiz-side-num">01</span><span>Identity</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="2" onclick="BusOps.setWizStep('drv', 2)"><span class="boc-wiz-side-num">02</span><span>Contact</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="3" onclick="BusOps.setWizStep('drv', 3)"><span class="boc-wiz-side-num">03</span><span>License &amp; Docs</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="4" onclick="BusOps.setWizStep('drv', 4)"><span class="boc-wiz-side-num">04</span><span>Availability</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="5" onclick="BusOps.setWizStep('drv', 5)"><span class="boc-wiz-side-num">05</span><span>Assignment</span></div>
        <div class="boc-wiz-side-step" data-wiz-step="6" onclick="BusOps.setWizStep('drv', 6)"><span class="boc-wiz-side-num">06</span><span>Review</span></div>
      </div>

      <form id="boc-driver-form" class="boc-wiz-main">
        <div>
          <!-- STEP 1: IDENTITY -->
          <div class="wiz-step-pane active" data-wiz-pane="drv-1">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">01 DRIVER IDENTITY</h4>
            <div style="display:flex;flex-direction:column;gap:0.9rem;">
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Full Name</label><input type="text" name="name" required class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. John Phiri"></div>
            </div>
          </div>

          <!-- STEP 2: CONTACT -->
          <div class="wiz-step-pane" data-wiz-pane="drv-2" style="display:none;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">02 CONTACT INFORMATION</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.9rem;">
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Phone Number</label><input type="text" name="phone" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. +265 999 111 222"></div>
            </div>
          </div>

          <!-- STEP 3: LICENSE & DOCS -->
          <div class="wiz-step-pane" data-wiz-pane="drv-3" style="display:none;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">03 LICENSE &amp; DOCUMENTS</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.9rem;">
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Driver License Number</label><input type="text" name="license_number" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. LIC-MW-8821"></div>
              <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">License Expiry</label><input type="date" name="license_expiry" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
            </div>
          </div>

          <!-- STEP 4: AVAILABILITY -->
          <div class="wiz-step-pane" data-wiz-pane="drv-4" style="display:none;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">04 AVAILABILITY</h4>
            <div style="font-size:0.8rem;color:var(--boc-text-soft);margin-bottom:0.6rem;">Default Status: <strong style="color:var(--boc-green);">Available for Assignment</strong></div>
          </div>

          <!-- STEP 5: ASSIGNMENT -->
          <div class="wiz-step-pane" data-wiz-pane="drv-5" style="display:none;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">05 ASSIGNMENT</h4>
            <div style="font-size:0.8rem;color:var(--boc-text-soft);">Driver-to-trip assignment happens from the Create Departure wizard. This driver will be available to select once created.</div>
          </div>

          <!-- STEP 6: REVIEW -->
          <div class="wiz-step-pane" data-wiz-pane="drv-6" style="display:none;">
            <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">06 REVIEW DRIVER</h4>
            <div id="drv-wiz-review" class="boc-wiz-review-card"></div>
          </div>
        </div>

        <div class="boc-wiz-footer">
          <button type="button" class="boc-btn-solid" id="drv-wiz-prev-btn" onclick="BusOps.stepWiz('drv', -1)" style="visibility:hidden;">Back</button>
          <div style="display:flex;gap:0.5rem;">
            <button type="button" class="boc-btn-solid" onclick="document.getElementById('boc-driver-modal').style.display='none'">Cancel</button>
            <button type="button" class="boc-btn-primary" id="drv-wiz-next-btn" onclick="BusOps.stepWiz('drv', 1)">Continue</button>
            <button type="submit" class="boc-btn-primary" id="drv-wiz-submit-btn" style="display:none;">Save Driver</button>
          </div>
        </div>
      </form>
    </div>

  </div>
</div>

<!-- ═══════════════ Driver detail modal (tabbed: Overview / Assignments) ═══════════════ -->
<div id="boc-driver-detail-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(6,16,29,.6);align-items:center;justify-content:center;padding:1.4rem;">
  <div style="width:440px;max-width:100%;max-height:88vh;overflow-y:auto;background:var(--boc-card);border:1px solid var(--boc-border);border-radius:16px;">
    <div class="boc-card-header" style="padding:1rem 1.2rem;border-bottom:1px solid var(--boc-border);"><h3 class="boc-card-title" id="drv-detail-title">Driver</h3><button type="button" onclick="document.getElementById('boc-driver-detail-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.3rem;cursor:pointer;">&times;</button></div>
    <div style="display:flex;gap:0.3rem;padding:0.8rem 1.2rem 0;border-bottom:1px solid var(--boc-border);">
      <button type="button" class="drv-tab-btn active" data-drv-tab="overview">Overview</button>
      <button type="button" class="drv-tab-btn" data-drv-tab="assignments">Assignments</button>
    </div>
    <div style="padding:1.1rem 1.2rem;">

      <div class="drv-tab-panel active" data-drv-panel="overview">
        <form id="drv-detail-form" style="display:flex;flex-direction:column;gap:0.7rem;">
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Full name</label><input type="text" name="name" required class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Phone</label><input type="text" name="phone" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">License number</label><input type="text" name="license_number" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">License expiry</label><input type="date" name="license_expiry" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Status</label><select name="status" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
          <button type="submit" class="boc-btn-primary" style="justify-content:center;">Save Changes</button>
        </form>
      </div>

      <div class="drv-tab-panel" data-drv-panel="assignments" style="display:none;">
        <table class="boc-table"><thead><tr><th>Trip</th><th>Departure</th><th>Status</th></tr></thead><tbody id="drv-assignments-body"><tr><td colspan="3" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
      </div>

    </div>
  </div>
</div>

<!-- ═══════════════ Passenger detail modal ═══════════════ -->
<div id="boc-passenger-detail-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(6,16,29,.6);align-items:center;justify-content:center;padding:1.4rem;">
  <div style="width:480px;max-width:100%;max-height:88vh;overflow-y:auto;background:var(--boc-card);border:1px solid var(--boc-border);border-radius:16px;">
    <div class="boc-card-header" style="padding:1rem 1.2rem;border-bottom:1px solid var(--boc-border);"><h3 class="boc-card-title" id="pax-detail-title">Passenger</h3><button type="button" onclick="document.getElementById('boc-passenger-detail-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.3rem;cursor:pointer;">&times;</button></div>
    <div style="padding:1.1rem 1.2rem;">
      <table class="boc-table"><thead><tr><th>Ticket</th><th>Route</th><th>Departs</th><th>Amount</th><th>Status</th></tr></thead><tbody id="pax-detail-body"><tr><td colspan="5" style="color:var(--boc-text-muted);">Loading…</td></tr></tbody></table>
    </div>
  </div>
</div>

<!-- ═══════════════ Assign vehicle/driver modal ═══════════════ -->
<div id="boc-assign-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(6,16,29,.6);align-items:center;justify-content:center;padding:1.4rem;">
  <div style="width:380px;max-width:100%;background:var(--boc-card);border:1px solid var(--boc-border);border-radius:16px;">
    <div class="boc-card-header" style="padding:1rem 1.2rem;border-bottom:1px solid var(--boc-border);"><h3 class="boc-card-title">Assign Bus &amp; Driver</h3><button type="button" onclick="document.getElementById('boc-assign-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.3rem;cursor:pointer;">&times;</button></div>
    <div style="padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:0.8rem;">
      <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Vehicle</label><select id="assign-vehicle-select" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="">Unassigned</option></select></div>
      <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Driver</label><select id="assign-driver-select" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="">Unassigned</option></select></div>
    </div>
    <div style="padding:1rem 1.2rem;border-top:1px solid var(--boc-border);display:flex;justify-content:flex-end;gap:0.6rem;">
      <button type="button" class="boc-btn-solid" onclick="document.getElementById('boc-assign-modal').style.display='none'">Cancel</button>
      <button type="button" class="boc-btn-primary" id="boc-save-assignment">Save Assignment</button>
    </div>
  </div>
</div>

<!-- ═══════════════ Cancel Trip modal ═══════════════ -->
<div id="boc-cancel-trip-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(6,16,29,.6);align-items:center;justify-content:center;padding:1.4rem;">
  <div style="width:400px;max-width:100%;background:var(--boc-card);border:1px solid var(--boc-border);border-radius:16px;">
    <div class="boc-card-header" style="padding:1rem 1.2rem;border-bottom:1px solid var(--boc-border);"><h3 class="boc-card-title">Cancel Departure</h3><button type="button" onclick="document.getElementById('boc-cancel-trip-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.3rem;cursor:pointer;">&times;</button></div>
    <div style="padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:0.8rem;">
      <div id="boc-cancel-trip-summary" style="font-size:0.82rem;color:var(--boc-text-soft);"></div>
      <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Reason (optional, kept with the departure's internal notes)</label><textarea id="boc-cancel-trip-reason" rows="3" class="boc-search-input" style="padding:0.5rem 0.75rem;width:100%;height:auto;" placeholder="e.g. Mechanical issue with assigned bus"></textarea></div>
      <div style="font-size:0.72rem;color:var(--boc-text-muted);">Any ticket already boarded stays boarded. Every other ticket for this departure will be marked cancelled and its seat released — customer refunds are not processed automatically yet.</div>
    </div>
    <div style="padding:1rem 1.2rem;border-top:1px solid var(--boc-border);display:flex;justify-content:flex-end;gap:0.6rem;">
      <button type="button" class="boc-btn-solid" onclick="document.getElementById('boc-cancel-trip-modal').style.display='none'">Keep Departure</button>
      <button type="button" class="boc-btn-primary" id="boc-confirm-cancel-trip" style="background:var(--boc-red);">Cancel Departure</button>
    </div>
  </div>
</div>

<!-- ═══════════════ Invite Staff modal ═══════════════ -->
<div id="um-invite-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(6,16,29,.6);align-items:center;justify-content:center;padding:1.4rem;">
  <div style="width:420px;max-width:100%;background:var(--boc-card);border:1px solid var(--boc-border);border-radius:16px;">
    <div class="boc-card-header" style="padding:1rem 1.2rem;border-bottom:1px solid var(--boc-border);"><h3 class="boc-card-title">Invite Staff</h3><button type="button" onclick="document.getElementById('um-invite-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.3rem;cursor:pointer;">&times;</button></div>
    <form id="um-invite-form">
      <div style="padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:0.8rem;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">First name</label><input type="text" name="first_name" required class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Last name</label><input type="text" name="last_name" required class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
        </div>
        <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Email</label><input type="email" name="email" required class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
        <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Role</label><select name="role_id" id="um-invite-role" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></select></div>
      </div>
      <div style="padding:1rem 1.2rem;border-top:1px solid var(--boc-border);display:flex;justify-content:flex-end;gap:0.6rem;">
        <button type="button" class="boc-btn-solid" onclick="document.getElementById('um-invite-modal').style.display='none'">Cancel</button>
        <button type="submit" class="boc-btn-primary">Send Invite</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════ Role permissions modal ═══════════════ -->
<div id="um-role-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(6,16,29,.6);align-items:center;justify-content:center;padding:1.4rem;">
  <div style="width:560px;max-width:100%;max-height:88vh;overflow-y:auto;background:var(--boc-card);border:1px solid var(--boc-border);border-radius:16px;">
    <div class="boc-card-header" style="padding:1rem 1.2rem;border-bottom:1px solid var(--boc-border);"><h3 class="boc-card-title" id="um-role-modal-title">Role</h3><button type="button" onclick="document.getElementById('um-role-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.3rem;cursor:pointer;">&times;</button></div>
    <form id="um-role-form">
      <input type="hidden" name="role_id">
      <div style="padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:0.8rem;">
        <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Role name</label><input type="text" name="name" required class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
        <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Description</label><input type="text" name="description" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
        <div>
          <label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.4rem;">Permissions</label>
          <div id="um-role-permissions-body" style="display:flex;flex-direction:column;gap:0.6rem;"></div>
        </div>
      </div>
      <div style="padding:1rem 1.2rem;border-top:1px solid var(--boc-border);display:flex;gap:0.6rem;">
        <button type="button" class="boc-btn-solid" id="um-role-delete-btn" style="background:var(--boc-red);display:none;">Delete Role</button>
        <div style="display:flex;gap:0.6rem;margin-left:auto;">
          <button type="button" class="boc-btn-solid" onclick="document.getElementById('um-role-modal').style.display='none'">Cancel</button>
          <button type="submit" class="boc-btn-primary">Save Role</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════ New Route modal ═══════════════ -->
<div id="boc-route-modal" class="boc-wiz-modal" style="display:none;" data-step="0">
  <div class="boc-wiz-shell">
    <div class="boc-wiz-header">
      <div>
        <div class="boc-wiz-header-sub">STEP <span id="route-step-num">1</span> OF 3</div>
        <h3 class="boc-wiz-header-title">New Bus Route</h3>
      </div>
      <button type="button" onclick="document.getElementById('boc-route-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.6rem;cursor:pointer;">&times;</button>
    </div>
    <div class="boc-wiz-body">
      <div class="boc-wiz-sidebar" id="boc-route-sidebar">
        <div class="boc-wiz-side-step active" data-step-index="0"><span class="boc-wiz-side-num">01</span><span>Basics</span></div>
        <div class="boc-wiz-side-step" data-step-index="1"><span class="boc-wiz-side-num">02</span><span>Schedule</span></div>
        <div class="boc-wiz-side-step" data-step-index="2"><span class="boc-wiz-side-num">03</span><span>Seats &amp; Review</span></div>
      </div>
      <form id="boc-route-form" class="boc-wiz-main">
        <div>

        <div class="boc-wizard-panel active">
          <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">01 ROUTE BASICS</h4>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Route title</label><input type="text" name="title" required class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. AXA Executive Coach: Lilongwe → Blantyre"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Origin</label><input type="text" name="origin" required class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="Lilongwe"></div>
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Destination</label><input type="text" name="destination" required class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="Blantyre"></div>
          </div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Vehicle type</label><select name="vehicle_type" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option>Coach Bus</option><option>Shuttle</option><option>Minibus</option></select></div>
        </div>

        <div class="boc-wizard-panel">
          <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">02 SCHEDULE</h4>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Pickup location</label><input type="text" name="pickup_location" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. Wenela Terminal, Lilongwe"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Usual departure time</label><input type="text" name="departure_time" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. 06:30 AM"></div>
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Usual arrival time</label><input type="text" name="arrival_time" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="e.g. 12:00 PM"></div>
          </div>
          <div>
            <label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Route Cover Image Upload</label>
            <div class="boc-dropzone" onclick="document.getElementById('route-cover-file').click()">
              <input type="file" id="route-cover-file" accept="image/*" style="display:none" onchange="BusOps.handleFileUpload(this, 'image', 'route-cover-url', 'route-cover-preview')">
              <input type="hidden" id="route-cover-url" name="image" value="">
              <div id="route-cover-preview" class="boc-dropzone-preview">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span>Click or drag image file here to upload</span>
              </div>
            </div>
          </div>
        </div>

        <div class="boc-wizard-panel">
          <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">03 SEATS &amp; REVIEW</h4>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Seat classes</label></div>
          <div id="boc-seat-rows"></div>
          <button type="button" id="boc-add-seat-row" style="align-self:flex-start;background:none;border:0;color:var(--boc-primary);font-weight:700;cursor:pointer;font-size:0.8rem;">+ Add seat class</button>
          <div id="boc-route-review" class="boc-wiz-review-card" style="margin-top:0.8rem;"></div>
        </div>

        </div>
        <div class="boc-wiz-footer">
          <button type="button" class="boc-btn-solid" id="boc-route-back" style="visibility:hidden;" onclick="BusOps.wizardStep('boc-route-modal', -1)">Back</button>
          <div style="display:flex;gap:0.6rem;">
            <button type="button" class="boc-btn-solid" onclick="document.getElementById('boc-route-modal').style.display='none'">Cancel</button>
            <button type="button" class="boc-btn-primary" id="boc-route-next" onclick="BusOps.wizardStep('boc-route-modal', 1)">Next</button>
            <button type="submit" class="boc-btn-primary" id="boc-route-submit" style="display:none;">Create Route</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════ Schedule Departure ("Trip") wizard ═══════════════ -->
<div id="boc-departure-modal" class="boc-wiz-modal" style="display:none;" data-step="0">
  <div class="boc-wiz-shell">
    <div class="boc-wiz-header">
      <div>
        <div class="boc-wiz-header-sub">STEP <span id="dep-step-num">1</span> OF 4</div>
        <h3 class="boc-wiz-header-title">Schedule a Trip</h3>
      </div>
      <button type="button" onclick="document.getElementById('boc-departure-modal').style.display='none'" style="background:none;border:0;color:var(--boc-text-muted);font-size:1.6rem;cursor:pointer;">&times;</button>
    </div>
    <div class="boc-wiz-body">
      <div class="boc-wiz-sidebar" id="boc-departure-sidebar">
        <div class="boc-wiz-side-step active" data-step-index="0"><span class="boc-wiz-side-num">01</span><span>Route &amp; Schedule</span></div>
        <div class="boc-wiz-side-step" data-step-index="1"><span class="boc-wiz-side-num">02</span><span>Vehicle &amp; Driver</span></div>
        <div class="boc-wiz-side-step" data-step-index="2"><span class="boc-wiz-side-num">03</span><span>Customer Listing</span></div>
        <div class="boc-wiz-side-step" data-step-index="3"><span class="boc-wiz-side-num">04</span><span>Review</span></div>
      </div>
      <form id="boc-departure-form" class="boc-wiz-main">
        <div>

        <div class="boc-wizard-panel active">
          <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">01 ROUTE &amp; SCHEDULE</h4>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Route</label><select id="boc-departure-listing" name="listing_id" class="boc-search-input" style="padding-left:0.75rem;width:100%;"></select></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Departure date</label><input type="date" name="departure_date" required class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
            <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Departure time</label><input type="time" name="departure_time" required class="boc-search-input" style="padding-left:0.75rem;width:100%;"></div>
          </div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Notes (optional, internal)</label><textarea name="notes" rows="2" class="boc-search-input" style="padding:0.5rem 0.75rem;width:100%;height:auto;"></textarea></div>
        </div>

        <div class="boc-wizard-panel">
          <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">02 VEHICLE &amp; DRIVER</h4>
          <p class="text-muted" style="font-size:0.78rem;color:var(--boc-text-soft);margin-bottom:0.2rem;">Every trip needs a bus assigned before customers can buy tickets for it.</p>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Vehicle <span style="color:var(--boc-red);">*</span></label><select id="dep-wizard-vehicle" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="">Choose a bus…</option></select></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Driver (optional)</label><select id="dep-wizard-driver" class="boc-search-input" style="padding-left:0.75rem;width:100%;"><option value="">Unassigned</option></select></div>
        </div>

        <div class="boc-wizard-panel">
          <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">03 CUSTOMER LISTING</h4>
          <p class="text-muted" style="font-size:0.78rem;color:var(--boc-text-soft);margin-bottom:0.6rem;">Configure how this specific trip appears to customers. Leave a field blank to inherit the route's own presentation.</p>
          <div>
            <label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Trip Customer Listing Photo Upload</label>
            <div class="boc-dropzone" onclick="document.getElementById('dep-cover-file').click()">
              <input type="file" id="dep-cover-file" accept="image/*" style="display:none" onchange="BusOps.handleFileUpload(this, 'image', 'dep-listing-image', 'dep-cover-preview')">
              <input type="hidden" id="dep-listing-image" value="">
              <div id="dep-cover-preview" class="boc-dropzone-preview">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <span>Click or drag image file here to upload</span>
              </div>
            </div>
          </div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Listing title</label><input type="text" id="dep-listing-title" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="Use route's title"></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Short description (optional)</label><textarea id="dep-listing-description" maxlength="500" rows="2" class="boc-search-input" style="padding:0.5rem 0.75rem;width:100%;height:auto;" placeholder="e.g. Comfortable morning service via Dedza and Ntcheu."></textarea></div>
          <div><label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Highlights (optional, comma-separated)</label><input type="text" id="dep-listing-highlights" maxlength="300" class="boc-search-input" style="padding-left:0.75rem;width:100%;" placeholder="Air conditioning, Reserved seating, Luggage space"></div>
          <div>
            <label style="font-size:0.72rem;font-weight:700;color:var(--boc-text-soft);display:block;margin-bottom:0.3rem;">Card style</label>
            <div style="display:flex;gap:0.5rem;">
              <label class="boc-style-option" style="flex:1;"><input type="radio" name="dep-card-style" value="standard" checked> Standard</label>
              <label class="boc-style-option" style="flex:1;"><input type="radio" name="dep-card-style" value="premium"> Premium</label>
              <label class="boc-style-option" style="flex:1;"><input type="radio" name="dep-card-style" value="compact"> Compact</label>
            </div>
          </div>
          <div style="margin-top:0.6rem;">
            <div style="font-size:0.68rem;font-weight:700;color:var(--boc-text-muted);text-transform:uppercase;letter-spacing:0.03em;margin-bottom:0.4rem;">Live preview — what customers will see</div>
            <div id="boc-listing-preview"></div>
          </div>
        </div>

        <div class="boc-wizard-panel">
          <h4 style="font-size:0.95rem;font-weight:800;color:var(--boc-text);margin-bottom:1rem;">04 REVIEW</h4>
          <div id="boc-departure-review" class="boc-wiz-review-card"></div>
        </div>

        </div>
        <div class="boc-wiz-footer">
          <button type="button" class="boc-btn-solid" id="boc-departure-back" style="visibility:hidden;" onclick="BusOps.wizardStep('boc-departure-modal', -1)">Back</button>
          <div style="display:flex;gap:0.6rem;">
            <button type="button" class="boc-btn-solid" onclick="document.getElementById('boc-departure-modal').style.display='none'">Cancel</button>
            <button type="button" class="boc-btn-primary" id="boc-departure-next" onclick="BusOps.wizardStep('boc-departure-modal', 1)">Next</button>
            <button type="submit" class="boc-btn-primary" id="boc-departure-submit" style="display:none;">Schedule Trip</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function switchSubTab(tabId, el) {
  var panels = document.querySelectorAll('.boc-panel');
  panels.forEach(function (p) { p.style.display = 'none'; });

  var target = document.getElementById('boc-panel-' + tabId);
  if (target) target.style.display = 'block';
  else document.getElementById('boc-panel-overview').style.display = 'block';

  if (el) {
    var items = document.querySelectorAll('.boc-nav-item');
    items.forEach(function (item) { item.classList.remove('active'); });
    el.classList.add('active');
  }

  if (!window.BusOps) return;
  if (tabId === 'overview') BusOps.loadDashboard();
  if (tabId === 'routes') BusOps.loadRoutesAndDepartures();
  if (tabId === 'trips') BusOps.loadTrips();
  if (tabId === 'reports') BusOps.loadReports();
  if (tabId === 'boarding') BusOps.loadBoardingDepartures(); else BusOps.stopCamera();
  if (tabId === 'revenue') BusOps.loadRevenue();
  if (tabId === 'analytics') BusOps.loadAnalytics();
  if (tabId === 'passengers') BusOps.loadPassengers();
  if (tabId === 'buses') BusOps.loadFleet();
  if (tabId === 'drivers') BusOps.loadDrivers();
  if (tabId === 'maintenance') BusOps.loadMaintenance();
  if (tabId === 'tickets') BusOps.searchTickets();
  if (tabId === 'company') BusOps.loadSettings();
  if (tabId === 'users') BusOps.loadStaff();
}
</script>

<script src="<?= BASE_URL ?>assets/js/vendor/jsQR.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/qrcode-generator.js"></script>
<script>
var BusOps = (function () {
  'use strict';
  var base = document.body.dataset.baseUrl || '';
  var initialTab = document.body.dataset.activeTab || 'overview';
  var csrf = document.body.dataset.csrf || '';
  var dashboardApi = base + 'api/tie/vendor/transport/dashboard.php';
  var routesApi = base + 'api/tie/vendor/transport/routes.php';
  var departuresApi = base + 'api/tie/vendor/transport/departures.php';
  var boardingApi = base + 'api/tie/vendor/transport/boarding.php';
  var state = { routes: [], departures: [], boardingDepartures: [], sessionId: null, scanning: false, stream: null, videoTimer: null, tickets: [] };

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function money(n) { return 'MWK ' + Math.round(Number(n) || 0).toLocaleString(); }
  function fmtDate(iso) { if (!iso) return '—'; var d = new Date(iso); return d.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }); }
  function notify(msg, isError) {
    var el = document.createElement('div');
    el.style.cssText = 'position:fixed;bottom:1.2rem;right:1.2rem;z-index:900;padding:0.7rem 1rem;border-radius:10px;font-weight:700;font-size:0.82rem;color:#fff;background:' + (isError ? 'var(--boc-red)' : 'var(--boc-green)') + ';box-shadow:0 10px 30px rgba(0,0,0,.35);';
    el.textContent = msg; document.body.appendChild(el); setTimeout(function () { el.remove(); }, 3600);
  }
  function getJson(url) { return fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(unwrap); }
  function postJson(url, body) {
    return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body) })
      .then(function (r) { return r.json(); }).then(unwrap);
  }
  function unwrap(j) { if (!j || j.success !== true) throw new Error((j && j.error && j.error.message) || 'Request failed.'); return j.result; }

  // ── Overview ─────────────────────────────────────────────────────
  function loadDashboard() {
    getJson(financeApi + '?action=trend&days=14').then(function (trend) {
      renderTrendSvg(document.getElementById('ov-trend-svg'), trend.series);
    }).catch(function () {});
    getJson(fleetApi + '?action=overview').then(function (o) {
      document.getElementById('kpi-active-buses').textContent = o.available + o.on_trip;
      document.getElementById('ov-fleet-on-trip').textContent = o.on_trip;
      document.getElementById('ov-fleet-available').textContent = o.available;
      document.getElementById('ov-fleet-maintenance').textContent = o.maintenance;
      document.getElementById('ov-fleet-doc-issues').textContent = o.document_issues;
    }).catch(function () {});
    getJson(fleetApi + '?action=maintenance_overview').then(function (o) {
      document.getElementById('kpi-pending-issues').textContent = o.open_issues;
    }).catch(function () {});
    getJson(dashboardApi).then(function (d) {
      document.getElementById('kpi-today-departures').textContent = d.today_departures.length;
      document.getElementById('kpi-tickets-sold').textContent = d.tickets_sold;
      document.getElementById('kpi-revenue').textContent = money(d.revenue_paid);
      document.getElementById('kpi-passengers').textContent = d.passengers_today;
      document.getElementById('ov-header-sub').textContent = d.today_departures.length
        ? 'Your fleet has ' + d.today_departures.length + ' departure' + (d.today_departures.length === 1 ? '' : 's') + ' scheduled today across your route network.'
        : 'No departures are scheduled today.';

      var capacityTotal = d.seats_sold + d.seats_remaining;
      var capacityPct = capacityTotal ? Math.round((d.seats_sold / capacityTotal) * 100) : 0;
      document.getElementById('ov-capacity-total').textContent = capacityTotal + ' seats';
      document.getElementById('ov-capacity-pct').textContent = capacityPct + '% Occupancy';
      document.getElementById('ov-capacity-bar').style.width = capacityPct + '%';
      var nearCapacity = d.today_departures.filter(function (dep) {
        var sold = dep.seat_classes.reduce(function (s, c) { return s + (c.total_seats - c.remaining_seats); }, 0);
        var total = dep.seat_classes.reduce(function (s, c) { return s + c.total_seats; }, 0);
        return total > 0 && (sold / total) >= 0.9;
      }).length;
      var alertEl = document.getElementById('ov-capacity-alert');
      if (nearCapacity > 0) {
        alertEl.style.display = 'flex';
        document.getElementById('ov-capacity-alert-text').textContent = nearCapacity + ' departure' + (nearCapacity === 1 ? ' is' : 's are') + ' approaching capacity (>90% full) today.';
      } else {
        alertEl.style.display = 'none';
      }

      var depWrap = document.getElementById('boc-today-departures');
      depWrap.innerHTML = d.today_departures.length ? d.today_departures.map(function (dep) {
        var sold = dep.seat_classes.reduce(function (s, c) { return s + (c.total_seats - c.remaining_seats); }, 0);
        var total = dep.seat_classes.reduce(function (s, c) { return s + c.total_seats; }, 0);
        var pct = total ? Math.round((sold / total) * 100) : 0;
        var crew = dep.vehicle ? esc(dep.vehicle.reg_number) + (dep.driver ? ' · ' + esc(dep.driver.name) : '') : '';
        return '<div class="boc-ops-item" data-departure-id="' + esc(dep.departure_id) + '"><div class="boc-ops-top"><div style="display:flex;align-items:center;gap:0.5rem;"><span style="font-weight:900;">' + fmtDate(dep.departure_at).split(', ')[1] + '</span><span class="boc-tag ' + (dep.status === 'boarding' ? 'tag-boarding' : 'tag-scheduled') + '">' + esc(dep.status.toUpperCase()) + '</span></div></div><div class="boc-ops-route">' + esc(dep.origin) + ' → ' + esc(dep.destination) + '</div><div class="boc-ops-bar-wrap"><div style="font-weight:700;color:#fff;">' + sold + ' / ' + total + '</div><div class="boc-progress"><div class="boc-progress-fill" style="width:' + pct + '%;"></div></div></div>' + (crew ? '<div style="font-size:0.68rem;color:var(--boc-text-muted);margin-top:0.3rem;">' + crew + '</div>' : '') + '</div>';
      }).join('') : '<div style="color:var(--boc-text-muted);font-size:0.82rem;">No departures scheduled for today.</div>';

      var scanWrap = document.getElementById('boc-recent-scans-body');
      scanWrap.innerHTML = d.recent_scans.length ? d.recent_scans.map(function (s) {
        var badgeCls = s.result === 'valid' ? 'tag-boarding' : s.result === 'duplicate' ? 'tag-scheduled' : 'tag-low';
        return '<tr><td><span class="boc-tag ' + badgeCls + '">' + esc(s.result.toUpperCase()) + '</span>' + (s.passenger_name ? ' <span style="color:var(--boc-text-muted);font-size:0.72rem;">' + esc(s.passenger_name) + '</span>' : '') + '</td><td>' + esc(s.code) + '</td><td>' + esc(s.method) + '</td><td>' + fmtDate(s.scanned_at) + '</td></tr>';
      }).join('') : '<tr><td colspan="4" style="color:var(--boc-text-muted);">No scans yet.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }

  // ── Routes & Schedule ────────────────────────────────────────────
  function loadRoutesAndDepartures() {
    return Promise.all([getJson(routesApi), getJson(departuresApi)]).then(function (r) {
      state.routes = r[0].routes; state.departures = r[1].departures;
      renderRoutes(); renderDepartures(); populateDepartureListingSelect();
    }).catch(function (err) { notify(err.message, true); });
  }
  function renderRoutes() {
    var grid = document.getElementById('boc-routes-grid');
    if (!state.routes.length) { grid.innerHTML = '<div style="color:var(--boc-text-muted);font-size:0.82rem;">No routes yet.</div>'; return; }
    grid.innerHTML = state.routes.map(function (r) {
      var chips = r.seat_classes.map(function (c) { return '<span style="display:inline-block;border:1px solid var(--boc-border);border-radius:8px;padding:0.2rem 0.5rem;font-size:0.66rem;font-weight:700;color:var(--boc-text-soft);margin:0.15rem 0.25rem 0 0;">' + esc(c.class_name) + ' · ' + money(c.price) + ' · ' + c.remaining_seats + '/' + c.total_seats + '</span>'; }).join('');
      return '<div class="boc-kpi-card" style="flex-direction:column;align-items:stretch;gap:0.5rem;">' +
        '<div style="font-weight:800;color:#fff;">' + esc(r.title) + '</div>' +
        '<div style="font-size:0.76rem;color:var(--boc-text-soft);">' + esc(r.origin) + ' → ' + esc(r.destination) + '</div>' +
        '<div>' + chips + '</div>' +
        '<div style="display:flex;gap:0.5rem;margin-top:0.4rem;">' +
        '<button class="boc-btn-solid" style="flex:1;justify-content:center;font-size:0.72rem;padding:0.45rem 0.6rem;" type="button" data-schedule="' + esc(r.listing_id) + '">Schedule</button>' +
        '<button class="boc-btn-solid" style="flex:1;justify-content:center;font-size:0.72rem;padding:0.45rem 0.6rem;" type="button" data-add-class="' + esc(r.listing_id) + '">+ Seat Class</button>' +
        '</div></div>';
    }).join('');
  }
  function renderDepartures() {
    var body = document.getElementById('boc-departures-body');
    if (!state.departures.length) { body.innerHTML = '<tr><td colspan="6" style="color:var(--boc-text-muted);">No departures scheduled yet.</td></tr>'; return; }
    var sorted = state.departures.slice().sort(function (a, b) { return new Date(a.departure_at) - new Date(b.departure_at); });
    body.innerHTML = sorted.map(function (dep) {
      var seats = dep.seat_classes.map(function (c) { return c.class_name + ' ' + c.remaining_seats + '/' + c.total_seats; }).join(', ');
      var cancellable = dep.status === 'scheduled' || dep.status === 'boarding';
      var assignment = (dep.vehicle ? esc(dep.vehicle.reg_number) : '<span style="color:var(--boc-text-muted);">No bus</span>') + '<br><small style="color:var(--boc-text-muted);">' + (dep.driver ? esc(dep.driver.name) : 'No driver') + '</small>';
      return '<tr><td>' + esc(dep.title) + '</td><td>' + fmtDate(dep.departure_at) + '</td><td><span class="boc-tag ' + (dep.status === 'cancelled' ? 'tag-low' : dep.status === 'boarding' ? 'tag-boarding' : 'tag-scheduled') + '">' + esc(dep.status.toUpperCase()) + '</span></td><td>' + esc(seats) + '</td><td>' + assignment + '</td>' +
        '<td><button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;margin-right:0.3rem;" type="button" data-assign="' + esc(dep.departure_id) + '">Assign</button>' + (cancellable ? '<button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;" type="button" data-cancel="' + esc(dep.departure_id) + '">Cancel</button>' : '') + '</td></tr>';
    }).join('');
  }
  var ELIGIBILITY_REASON_LABEL = { maintenance: 'Maintenance', inactive: 'Inactive', license_expired: 'License expired', conflict: 'Already assigned nearby' };
  function eligibilityOptionsHtml(items, currentId, nameKey) {
    return items.map(function (item) {
      var label = esc(nameKey === 'vehicle' ? item.reg_number + ' — ' + item.make_model : item.name);
      if (!item.eligible) label += ' (' + esc(ELIGIBILITY_REASON_LABEL[item.reason_code] || 'Unavailable') + ')';
      var selected = item.id === currentId;
      return '<option value="' + esc(item.id) + '"' + (selected ? ' selected' : '') + (!item.eligible && !selected ? ' disabled' : '') + '>' + (item.eligible ? '✓ ' : '⚠ ') + label + '</option>';
    }).join('');
  }
  function openAssignModal(departureId) {
    var dep = state.departures.find(function (d) { return d.departure_id === departureId; });
    getJson(fleetApi + '?action=assignment_eligibility&departure_id=' + encodeURIComponent(departureId)).then(function (elig) {
      document.getElementById('assign-vehicle-select').innerHTML = '<option value="">Unassigned</option>' + eligibilityOptionsHtml(elig.vehicles, dep && dep.vehicle ? dep.vehicle.id : null, 'vehicle');
      document.getElementById('assign-driver-select').innerHTML = '<option value="">Unassigned</option>' + eligibilityOptionsHtml(elig.drivers, dep && dep.driver ? dep.driver.id : null, 'driver');
      document.getElementById('boc-assign-modal').style.display = 'flex';
      document.getElementById('boc-save-assignment').setAttribute('data-departure', departureId);
    }).catch(function (err) { notify(err.message, true); });
  }
  function saveAssignment() {
    var departureId = document.getElementById('boc-save-assignment').getAttribute('data-departure');
    var vehicleId = document.getElementById('assign-vehicle-select').value;
    var driverId = document.getElementById('assign-driver-select').value;
    postJson(departuresApi, { action: 'assign', departure_id: departureId, vehicle_id: vehicleId || null, driver_id: driverId || null })
      .then(function () { return loadRoutesAndDepartures(); }).then(function () { document.getElementById('boc-assign-modal').style.display = 'none'; notify('Assignment saved.'); })
      .catch(function (err) { notify(err.message, true); });
  }
  function populateDepartureListingSelect() {
    var select = document.getElementById('boc-departure-listing');
    if (!select) return;
    select.innerHTML = state.routes.map(function (r) { return '<option value="' + esc(r.listing_id) + '">' + esc(r.title) + '</option>'; }).join('');
  }
  // ── Wizard (shared by Route + Departure modals) ───────────────────
  function wizardButtons(modalId) {
    var prefix = modalId === 'boc-route-modal' ? 'boc-route' : 'boc-departure';
    return { back: document.getElementById(prefix + '-back'), next: document.getElementById(prefix + '-next'), submit: document.getElementById(prefix + '-submit') };
  }
  function wizardStepNumEl(modalId) {
    return document.getElementById(modalId === 'boc-route-modal' ? 'route-step-num' : 'dep-step-num');
  }
  function wizardSidebarEl(modalId) {
    return document.getElementById(modalId === 'boc-route-modal' ? 'boc-route-sidebar' : 'boc-departure-sidebar');
  }
  function wizardReset(modalId) {
    var modal = document.getElementById(modalId);
    modal.setAttribute('data-step', '0');
    modal.querySelectorAll('.boc-wizard-panel').forEach(function (p, i) { p.classList.toggle('active', i === 0); });
    wizardSidebarEl(modalId).querySelectorAll('.boc-wiz-side-step').forEach(function (s, i) { s.classList.toggle('active', i === 0); });
    var numEl = wizardStepNumEl(modalId); if (numEl) numEl.textContent = '1';
    var btns = wizardButtons(modalId);
    btns.back.style.visibility = 'hidden'; btns.next.style.display = ''; btns.submit.style.display = 'none';
  }
  function wizardStep(modalId, direction) {
    var modal = document.getElementById(modalId);
    var panels = modal.querySelectorAll('.boc-wizard-panel');
    var current = parseInt(modal.getAttribute('data-step'), 10) || 0;
    if (direction > 0) {
      var invalidField = Array.prototype.slice.call(panels[current].querySelectorAll('[required]')).find(function (el) { return el.offsetParent !== null && !el.reportValidity(); });
      if (invalidField) return;
      if (modalId === 'boc-departure-modal' && current === 1 && !document.getElementById('dep-wizard-vehicle').value) { notify('Choose a vehicle for this trip before continuing.', true); return; }
    }
    var next = Math.max(0, Math.min(panels.length - 1, current + direction));
    modal.setAttribute('data-step', String(next));
    panels.forEach(function (p, i) { p.classList.toggle('active', i === next); });
    wizardSidebarEl(modalId).querySelectorAll('.boc-wiz-side-step').forEach(function (s, i) { s.classList.toggle('active', i === next); });
    var numEl = wizardStepNumEl(modalId); if (numEl) numEl.textContent = String(next + 1);
    var btns = wizardButtons(modalId);
    var isLast = next === panels.length - 1;
    btns.back.style.visibility = next === 0 ? 'hidden' : 'visible';
    btns.next.style.display = isLast ? 'none' : '';
    btns.submit.style.display = isLast ? '' : 'none';
    if (modalId === 'boc-departure-modal' && next === 1) loadDepartureWizardVehicles();
    if (modalId === 'boc-departure-modal' && next === 2) renderListingPreview();
    if (isLast && modalId === 'boc-route-modal') renderRouteReview();
    if (isLast && modalId === 'boc-departure-modal') renderDepartureReview();
  }
  function renderRouteReview() {
    var form = document.getElementById('boc-route-form');
    document.getElementById('boc-route-review').innerHTML =
      '<div class="boc-wizard-review-row"><span>Route</span><span>' + esc(form.title.value) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Origin → Destination</span><span>' + esc(form.origin.value) + ' → ' + esc(form.destination.value) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Vehicle type</span><span>' + esc(form.vehicle_type.value) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Pickup</span><span>' + esc(form.pickup_location.value || '—') + '</span></div>';
  }
  function loadDepartureWizardVehicles() {
    var form = document.getElementById('boc-departure-form');
    var departureAt = form.departure_date.value && form.departure_time.value ? form.departure_date.value + ' ' + form.departure_time.value + ':00' : '';
    getJson(fleetApi + '?action=assignment_eligibility&departure_at=' + encodeURIComponent(departureAt)).then(function (elig) {
      document.getElementById('dep-wizard-vehicle').innerHTML = '<option value="">Choose a bus…</option>' + eligibilityOptionsHtml(elig.vehicles, null, 'vehicle');
      document.getElementById('dep-wizard-driver').innerHTML = '<option value="">Unassigned</option>' + eligibilityOptionsHtml(elig.drivers, null, 'driver');
    }).catch(function (err) { notify(err.message, true); });
  }
  function renderDepartureReview() {
    var form = document.getElementById('boc-departure-form');
    var routeSelect = document.getElementById('boc-departure-listing');
    var routeTitle = routeSelect.options[routeSelect.selectedIndex] ? routeSelect.options[routeSelect.selectedIndex].text : '';
    var vehicleSelect = document.getElementById('dep-wizard-vehicle');
    var vehicleLabel = vehicleSelect.options[vehicleSelect.selectedIndex] ? vehicleSelect.options[vehicleSelect.selectedIndex].text : 'Not selected';
    var driverSelect = document.getElementById('dep-wizard-driver');
    var driverLabel = driverSelect.options[driverSelect.selectedIndex] ? driverSelect.options[driverSelect.selectedIndex].text : 'Unassigned';
    var styleLabel = document.querySelector('input[name="dep-card-style"]:checked').value;
    document.getElementById('boc-departure-review').innerHTML =
      '<div class="boc-wizard-review-row"><span>Route</span><span>' + esc(routeTitle) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Departure</span><span>' + esc(form.departure_date.value) + ' ' + esc(form.departure_time.value) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Vehicle</span><span>' + esc(vehicleLabel) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Driver</span><span>' + esc(driverLabel) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Listing title</span><span>' + esc(document.getElementById('dep-listing-title').value.trim() || 'Uses route title') + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Card style</span><span>' + esc(styleLabel.charAt(0).toUpperCase() + styleLabel.slice(1)) + '</span></div>';
  }
  var DEP_IMAGE_MAP = { axon_bus: 'assets/images/buses/axon_bus.svg', malawi_express: 'assets/images/buses/malawi_express.svg', speed_coaster: 'assets/images/buses/speed_coaster.svg' };
  function renderListingPreview() {
    var routeSelect = document.getElementById('boc-departure-listing');
    var route = state.routes.find(function (r) { return r.listing_id === routeSelect.value; });
    var imageKey = document.getElementById('dep-listing-image').value;
    var image = imageKey ? (base + DEP_IMAGE_MAP[imageKey]) : (route ? route.image : '');
    var title = document.getElementById('dep-listing-title').value.trim() || (route ? route.title : 'Route title');
    var description = document.getElementById('dep-listing-description').value.trim();
    var highlights = document.getElementById('dep-listing-highlights').value.split(',').map(function (h) { return h.trim(); }).filter(Boolean);
    var style = document.querySelector('input[name="dep-card-style"]:checked').value;

    var html = '<div class="boc-preview-card boc-preview-' + style + '">';
    html += image ? '<img class="boc-preview-thumb" src="' + esc(image) + '" onerror="this.style.display=\'none\'">' : '<div class="boc-preview-thumb"></div>';
    html += '<div><div class="boc-preview-title">' + esc(title) + '</div>';
    if (style !== 'compact') html += '<div class="boc-preview-sub">' + esc(route ? route.origin + ' → ' + route.destination : '') + '</div>';
    if (style === 'premium') {
      if (description) html += '<div class="boc-preview-sub">' + esc(description) + '</div>';
      if (highlights.length) html += '<div>' + highlights.map(function (h) { return '<span class="boc-preview-chip">' + esc(h) + '</span>'; }).join('') + '</div>';
    }
    html += '</div></div>';
    document.getElementById('boc-listing-preview').innerHTML = html;
  }

  function openRouteModal() { document.getElementById('boc-route-modal').style.display = 'flex'; document.getElementById('boc-route-form').reset(); resetSeatRows(); wizardReset('boc-route-modal'); }
  function resetSeatRows() { var wrap = document.getElementById('boc-seat-rows'); wrap.innerHTML = ''; addSeatRow(); addSeatRow(); }
  function addSeatRow() {
    var wrap = document.getElementById('boc-seat-rows');
    var row = document.createElement('div');
    row.style.cssText = 'display:grid;grid-template-columns:1.3fr .8fr .7fr auto;gap:0.5rem;align-items:end;margin-bottom:0.5rem;';
    row.innerHTML = '<div><label style="font-size:0.68rem;color:var(--boc-text-muted);display:block;margin-bottom:0.2rem;">Class name</label><input type="text" class="boc-seat-name boc-search-input" style="padding-left:0.6rem;width:100%;" placeholder="e.g. Standard"></div>' +
      '<div><label style="font-size:0.68rem;color:var(--boc-text-muted);display:block;margin-bottom:0.2rem;">Price (MWK)</label><input type="number" min="1" class="boc-seat-price boc-search-input" style="padding-left:0.6rem;width:100%;"></div>' +
      '<div><label style="font-size:0.68rem;color:var(--boc-text-muted);display:block;margin-bottom:0.2rem;">Seats</label><input type="number" min="1" class="boc-seat-total boc-search-input" style="padding-left:0.6rem;width:100%;"></div>' +
      '<button type="button" class="boc-btn-solid" style="padding:0.5rem;" data-remove-seat-row>×</button>';
    wrap.appendChild(row);
  }
  function submitRoute(e) {
    e.preventDefault();
    var form = e.target;
    var seatClasses = Array.prototype.slice.call(document.querySelectorAll('#boc-seat-rows > div')).map(function (row) {
      return { class_name: row.querySelector('.boc-seat-name').value.trim(), price: Number(row.querySelector('.boc-seat-price').value), total_seats: Number(row.querySelector('.boc-seat-total').value) };
    }).filter(function (c) { return c.class_name && c.price > 0 && c.total_seats > 0; });
    if (!seatClasses.length) { notify('Add at least one valid seat class.', true); return; }
    postJson(routesApi, {
      action: 'create', title: form.title.value.trim(), origin: form.origin.value.trim(), destination: form.destination.value.trim(),
      vehicle_type: form.vehicle_type.value, departure_time: form.departure_time.value.trim(), arrival_time: form.arrival_time.value.trim(),
      pickup_location: form.pickup_location.value.trim(), image: form.image.value, seat_classes: seatClasses,
    }).then(function (result) { state.routes = result.routes; renderRoutes(); populateDepartureListingSelect(); document.getElementById('boc-route-modal').style.display = 'none'; notify('Route created.'); })
      .catch(function (err) { notify(err.message, true); });
  }
  function openDepartureModal(listingId) {
    document.getElementById('boc-departure-form').reset();
    wizardReset('boc-departure-modal');
    document.getElementById('boc-departure-modal').style.display = 'flex';
    // Opened from Overview's quick action (not the Routes tab), state.routes may
    // still be empty — always (re)load it so the Route select is never blank.
    (state.routes.length ? Promise.resolve() : loadRoutesAndDepartures()).then(function () {
      if (listingId) document.getElementById('boc-departure-listing').value = listingId;
    });
  }
  function submitDeparture(e) {
    e.preventDefault();
    var form = e.target;
    var date = form.departure_date.value, time = form.departure_time.value;
    if (!date || !time) { notify('Pick a departure date and time.', true); return; }
    var vehicleId = document.getElementById('dep-wizard-vehicle').value;
    if (!vehicleId) { notify('Choose a vehicle for this trip before scheduling it.', true); return; }
    var driverId = document.getElementById('dep-wizard-driver').value;
    var submitBtn = document.getElementById('boc-departure-submit');
    submitBtn.disabled = true; submitBtn.textContent = 'Scheduling…';
    postJson(departuresApi, {
      action: 'create', listing_id: form.listing_id.value, departure_at: date + ' ' + time + ':00', notes: form.notes.value.trim(),
      listing_title: document.getElementById('dep-listing-title').value.trim(), image: document.getElementById('dep-listing-image').value,
      customer_description: document.getElementById('dep-listing-description').value.trim(), highlights: document.getElementById('dep-listing-highlights').value.trim(),
      card_style: document.querySelector('input[name="dep-card-style"]:checked').value,
    })
      .then(function (result) { return postJson(departuresApi, { action: 'assign', departure_id: result.created_departure_id, vehicle_id: vehicleId, driver_id: driverId || null }); })
      .then(function () { return loadRoutesAndDepartures(); })
      .then(function () {
        submitBtn.disabled = false; submitBtn.textContent = 'Schedule Trip';
        document.getElementById('boc-departure-modal').style.display = 'none';
        notify('Trip scheduled and vehicle assigned.');
      })
      .catch(function (err) {
        submitBtn.disabled = false; submitBtn.textContent = 'Schedule Trip';
        notify(err.message, true);
        loadRoutesAndDepartures();
      });
  }
  function cancelDeparture(departureId) {
    var dep = state.departures.find(function (d) { return d.departure_id === departureId; });
    document.getElementById('boc-cancel-trip-summary').textContent = dep ? (dep.title + ' — ' + fmtDate(dep.departure_at)) : 'This departure';
    document.getElementById('boc-cancel-trip-reason').value = '';
    document.getElementById('boc-confirm-cancel-trip').setAttribute('data-departure', departureId);
    document.getElementById('boc-cancel-trip-modal').style.display = 'flex';
  }
  function confirmCancelDeparture() {
    var departureId = document.getElementById('boc-confirm-cancel-trip').getAttribute('data-departure');
    var reason = document.getElementById('boc-cancel-trip-reason').value.trim();
    postJson(departuresApi, { action: 'cancel', departure_id: departureId, reason: reason })
      .then(function (result) {
        document.getElementById('boc-cancel-trip-modal').style.display = 'none';
        var count = result.cancelled_ticket_count || 0;
        return Promise.resolve().then(function () { return loadRoutesAndDepartures(); }).then(function () { return typeof loadTrips === 'function' ? loadTrips() : null; }).then(function () {
          notify('Departure cancelled' + (count ? ' — ' + count + ' ticket(s) released.' : '.'));
        });
      })
      .catch(function (err) { notify(err.message, true); });
  }
  function addSeatClassToRoute(listingId) {
    var name = window.prompt('Seat class name (e.g. VIP):'); if (!name) return;
    var price = Number(window.prompt('Price per seat (MWK):', '0')); if (!price || price <= 0) { notify('Enter a valid price.', true); return; }
    var seats = Number(window.prompt('Total seats:', '0')); if (!seats || seats <= 0) { notify('Enter a valid seat count.', true); return; }
    postJson(routesApi, { action: 'update', listing_id: listingId, add_seat_class: { class_name: name, price: price, total_seats: seats } })
      .then(function (result) { state.routes = result.routes; renderRoutes(); notify('Seat class added.'); }).catch(function (err) { notify(err.message, true); });
  }

  // ── Boarding & Tickets ───────────────────────────────────────────
  function loadBoardingDepartures() {
    return getJson(departuresApi).then(function (r) {
      state.boardingDepartures = r.departures.filter(function (d) { return d.status === 'scheduled' || d.status === 'boarding'; });
      var select = document.getElementById('boc-boarding-departure');
      select.innerHTML = state.boardingDepartures.length ? state.boardingDepartures.map(function (d) { return '<option value="' + esc(d.departure_id) + '">' + esc(d.title) + ' — ' + fmtDate(d.departure_at) + (d.vehicle ? ' (' + esc(d.vehicle.reg_number) + ')' : '') + '</option>'; }).join('') : '<option value="">No upcoming departures</option>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function startSession() {
    var departureId = document.getElementById('boc-boarding-departure').value;
    if (!departureId) { notify('Choose a departure first.', true); return; }
    postJson(boardingApi, { action: 'start_session', departure_id: departureId }).then(function (result) {
      state.sessionId = result.session.id; renderSession(result); setSessionUiActive(true);
      document.getElementById('boc-final-manifest').style.display = 'none';
      document.getElementById('boc-scan-result').style.display = 'none';
      notify('Boarding session started.');
    }).catch(function (err) { notify(err.message, true); });
  }
  function stopSession() {
    if (!state.sessionId) return;
    if (!window.confirm('Close boarding for this departure? Any tickets not yet scanned will be marked no-show and the trip will move to Departed.')) return;
    postJson(boardingApi, { action: 'stop_session', session_id: state.sessionId }).then(function (result) {
      renderSession(result); setSessionUiActive(false); stopCamera();
      var fm = result.final_manifest || { booked: 0, boarded: 0, no_show: 0, cancelled: 0 };
      document.getElementById('boc-final-booked').textContent = fm.booked;
      document.getElementById('boc-final-boarded').textContent = fm.boarded;
      document.getElementById('boc-final-noshow').textContent = fm.no_show;
      document.getElementById('boc-final-cancelled').textContent = fm.cancelled;
      document.getElementById('boc-final-manifest').style.display = 'block';
      notify('Boarding closed — ' + fm.boarded + ' boarded, ' + fm.no_show + ' no-show.');
    }).catch(function (err) { notify(err.message, true); });
  }
  function setSessionUiActive(active) {
    document.getElementById('boc-start-session').disabled = active;
    document.getElementById('boc-stop-session').disabled = !active;
    document.getElementById('boc-boarding-departure').disabled = active;
    document.getElementById('boc-scan-panel').style.opacity = active ? '1' : '.45';
    document.getElementById('boc-scan-panel').style.pointerEvents = active ? 'auto' : 'none';
  }
  function renderSession(result) {
    var s = result.session;
    document.getElementById('boc-count-scanned').textContent = s.total_scanned;
    document.getElementById('boc-count-valid').textContent = s.total_valid;
    document.getElementById('boc-count-invalid').textContent = s.total_invalid;
    document.getElementById('boc-count-duplicate').textContent = s.total_duplicate;
    var manifestBody = document.getElementById('boc-manifest-body');
    manifestBody.innerHTML = result.manifest.length ? result.manifest.map(function (t) {
      return '<tr><td>' + esc(t.ticket_id) + '</td><td>' + esc(t.passenger_name) + '</td><td>' + esc(t.seat_class) + (t.seat_label ? ' · ' + esc(t.seat_label) : '') + '</td><td><span class="boc-tag ' + (t.status === 'boarded' ? 'tag-boarding' : 'tag-scheduled') + '">' + esc(t.status.toUpperCase()) + '</span></td></tr>';
    }).join('') : '<tr><td colspan="4" style="color:var(--boc-text-muted);">No tickets issued for this departure yet.</td></tr>';
    var scansBody = document.getElementById('boc-recent-scans-body');
    scansBody.innerHTML = result.recent_scans.length ? result.recent_scans.map(function (sc) {
      return '<tr><td><span class="boc-tag ' + (sc.result === 'valid' ? 'tag-boarding' : sc.result === 'duplicate' ? 'tag-scheduled' : 'tag-low') + '">' + esc(sc.result.toUpperCase()) + '</span></td><td>' + esc(sc.code) + '</td><td>' + esc(sc.method) + '</td><td>' + fmtDate(sc.scanned_at) + '</td></tr>';
    }).join('') : '<tr><td colspan="4" style="color:var(--boc-text-muted);">No scans yet.</td></tr>';
  }
  function verify(code, method) {
    if (!state.sessionId) { notify('Start a boarding session first.', true); return; }
    code = (code || '').trim(); if (!code) return;
    postJson(boardingApi, { action: 'verify', session_id: state.sessionId, code: code, method: method }).then(function (result) {
      showScanResult(result);
      return getJson(boardingApi + '?action=session_stats&session_id=' + encodeURIComponent(state.sessionId));
    }).then(renderSession).catch(function (err) { notify(err.message, true); });
  }
  var SCAN_REASON_META = {
    valid: { label: 'Boarded', color: 'var(--boc-green)' },
    duplicate: { label: 'Already Boarded', color: 'var(--boc-orange)' },
    wrong_departure: { label: 'Wrong Departure', color: 'var(--boc-orange)' },
    cancelled: { label: 'Cancelled Ticket', color: 'var(--boc-red)' },
    no_show: { label: 'Marked No-show', color: 'var(--boc-orange)' },
    signature_mismatch: { label: 'Invalid QR Code', color: 'var(--boc-red)' },
    not_found: { label: 'Ticket Not Found', color: 'var(--boc-red)' }
  };
  function showScanResult(result) {
    var el = document.getElementById('boc-scan-result');
    var meta = SCAN_REASON_META[result.reason_code] || (result.scan_result === 'valid' ? SCAN_REASON_META.valid : { label: 'Invalid Ticket', color: 'var(--boc-red)' });
    var name = result.ticket ? esc(result.ticket.passenger_name) + (result.ticket.seat_label ? ' · Seat ' + esc(result.ticket.seat_label) : '') : (result.notes ? esc(result.notes) : '');
    el.style.display = 'block'; el.style.background = 'rgba(0,0,0,.15)'; el.style.color = meta.color;
    el.innerHTML = '<div>' + meta.label + '</div><div style="font-weight:600;font-size:0.8rem;color:var(--boc-text-soft);">' + name + '</div>';
  }
  function toggleCamera() { if (state.scanning) stopCamera(); else startCamera(); }
  function startCamera() {
    if (typeof jsQR !== 'function') { notify('QR scanning library failed to load.', true); return; }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { notify('Camera access is not available on this device.', true); return; }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (stream) {
      state.stream = stream; state.scanning = true;
      var video = document.getElementById('boc-camera-video');
      video.srcObject = stream; video.play();
      document.getElementById('boc-camera-wrap').style.display = 'block';
      document.getElementById('boc-toggle-camera').textContent = 'Stop Camera';
      tickCamera();
    }).catch(function () { notify('Could not access the camera. Check browser permissions.', true); });
  }
  function stopCamera() {
    state.scanning = false;
    if (state.videoTimer) cancelAnimationFrame(state.videoTimer);
    if (state.stream) state.stream.getTracks().forEach(function (t) { t.stop(); });
    state.stream = null;
    var wrap = document.getElementById('boc-camera-wrap'); if (wrap) wrap.style.display = 'none';
    var btn = document.getElementById('boc-toggle-camera'); if (btn) btn.textContent = 'Scan QR with Camera';
  }
  function tickCamera() {
    if (!state.scanning) return;
    var video = document.getElementById('boc-camera-video');
    var canvas = document.getElementById('boc-camera-canvas');
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
      canvas.width = video.videoWidth; canvas.height = video.videoHeight;
      var ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      var frame = ctx.getImageData(0, 0, canvas.width, canvas.height);
      var code = jsQR(frame.data, frame.width, frame.height);
      if (code && code.data) { document.getElementById('boc-code-input').value = code.data; verify(code.data, 'qr'); stopCamera(); return; }
    }
    state.videoTimer = requestAnimationFrame(tickCamera);
  }

  // ── Revenue ──────────────────────────────────────────────────────
  var financeApi = base + 'api/tie/vendor/transport/finance.php';
  var analyticsApi = base + 'api/tie/vendor/transport/analytics.php';
  var passengersApi = base + 'api/tie/vendor/transport/passengers.php';
  var fleetApi = base + 'api/tie/vendor/transport/fleet.php';
  var driversApi = base + 'api/tie/vendor/transport/drivers.php';
  var ticketsApi = base + 'api/tie/vendor/transport/tickets.php';
  var settingsApi = base + 'api/tie/vendor/transport/settings.php';
  var staffApi = base + 'api/tie/vendor/transport/staff.php';
  var financeState = { accounts: [] };
  var fleetState = { vehicles: [], activeVehicleId: null };

  function renderTrendSvg(svgEl, series) {
    var max = Math.max.apply(null, series.map(function (p) { return p.amount; }).concat([1]));
    var w = 600, h = 120, step = w / Math.max(1, series.length - 1);
    var points = series.map(function (p, i) { return (i * step).toFixed(1) + ',' + (h - (p.amount / max) * (h - 10) - 5).toFixed(1); });
    var linePath = 'M' + points.join(' L');
    var areaPath = linePath + ' L' + w + ',' + h + ' L0,' + h + ' Z';
    svgEl.innerHTML = '<path d="' + areaPath + '" fill="var(--boc-primary)" fill-opacity="0.12"></path><path d="' + linePath + '" fill="none" stroke="var(--boc-primary)" stroke-width="2.5"></path>';
  }

  function loadRevenue() {
    Promise.all([getJson(financeApi + '?action=overview'), getJson(financeApi + '?action=trend'), getJson(financeApi + '?action=transactions'), getJson(financeApi + '?action=accounts'), getJson(financeApi + '?action=withdrawals'), getJson(financeApi + '?action=banks')])
      .then(function (r) {
        var overview = r[0], trend = r[1], txns = r[2], accounts = r[3], withdrawals = r[4], banks = r[5];
        document.getElementById('rev-gross').textContent = money(overview.gross_revenue);
        document.getElementById('rev-commission').textContent = money(overview.commission_amount) + ' (' + overview.commission_rate + '%)';
        document.getElementById('rev-net').textContent = money(overview.net_revenue);
        document.getElementById('rev-available').textContent = money(overview.available_balance);
        renderTrendSvg(document.getElementById('rev-trend-svg'), trend.series);

        document.getElementById('rev-transactions-body').innerHTML = txns.items.length ? txns.items.map(function (t) {
          var amountCell = money(t.amount) + (t.cancelled_count > 0 ? ' <span class="boc-tag tag-low" title="' + t.cancelled_count + ' seat(s) in this booking were cancelled; gross was ' + money(t.gross_amount) + '" style="margin-left:0.3rem;">' + t.cancelled_count + ' CANCELLED</span>' : '');
          return '<tr><td>' + esc(t.route) + '</td><td>' + esc(t.customer_name) + '</td><td>' + amountCell + '</td><td><span class="boc-tag ' + (t.status === 'Paid' ? 'tag-boarding' : 'tag-low') + '">' + esc(t.status.toUpperCase()) + '</span></td><td>' + fmtDate(t.created_at) + '</td></tr>';
        }).join('') : '<tr><td colspan="5" style="color:var(--boc-text-muted);">No transactions yet.</td></tr>';

        financeState.accounts = accounts.items;
        document.getElementById('rev-accounts-list').innerHTML = accounts.items.length ? accounts.items.map(function (a) {
          return '<div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--boc-border);"><div><strong style="color:#fff;">' + esc(a.account_name) + '</strong><div style="font-size:0.7rem;color:var(--boc-text-muted);">' + esc(a.method) + ' · ' + esc(a.account_number_masked) + (a.provider ? ' · ' + esc(a.provider) : '') + (a.is_verified ? ' · Matched to PayChangu registry' : '') + '</div></div>' + (a.is_default ? '<span class="boc-tag tag-boarding">DEFAULT</span>' : '') + '</div>';
        }).join('') : '<div style="color:var(--boc-text-muted);font-size:0.8rem;">No payout accounts yet.</div>';
        var withdrawSelect = document.getElementById('rev-withdraw-account');
        withdrawSelect.innerHTML = accounts.items.length ? accounts.items.map(function (a) { return '<option value="' + esc(a.id) + '">' + esc(a.account_name) + ' (' + esc(a.account_number_masked) + ')</option>'; }).join('') : '<option value="">Add a payout account first</option>';

        document.getElementById('rev-withdrawals-body').innerHTML = withdrawals.items.length ? withdrawals.items.map(function (w) {
          return '<tr><td>' + money(w.amount) + '</td><td>' + esc(w.destination_label) + ' ' + esc(w.account_number_masked) + '</td><td><span class="boc-tag ' + (w.status === 'PAID' ? 'tag-boarding' : w.status === 'REJECTED' ? 'tag-low' : 'tag-scheduled') + '">' + esc(w.status) + '</span></td><td>' + fmtDate(w.requested_at) + '</td></tr>';
        }).join('') : '<tr><td colspan="4" style="color:var(--boc-text-muted);">No withdrawals requested yet.</td></tr>';

        var bankSelect = document.getElementById('rev-bank-select');
        bankSelect.innerHTML = banks.banks && banks.banks.length ? '<option value="">Choose a bank…</option>' + banks.banks.map(function (b) { return '<option value="' + esc(b.name) + '">' + esc(b.name) + '</option>'; }).join('') : '<option value="">Banks unavailable — try again shortly</option>';

        var readiness = accounts.readiness || { mobile_money: {}, mobile_money_ready: false, bank_ready: false };
        var airtelReady = !!readiness.mobile_money['Airtel Money'], tnmReady = !!readiness.mobile_money['TNM Mpamba'];
        setReadinessTag('rev-mm-status-airtel', airtelReady);
        setReadinessTag('rev-mm-status-tnm', tnmReady);
        document.getElementById('rev-readiness').innerHTML =
          '<span class="boc-tag ' + (readiness.mobile_money_ready ? 'tag-boarding' : 'tag-low') + '" style="margin-right:0.4rem;">Mobile Money ' + (readiness.mobile_money_ready ? 'Ready' : 'Incomplete') + '</span>' +
          '<span class="boc-tag ' + (readiness.bank_ready ? 'tag-boarding' : 'tag-low') + '">Bank ' + (readiness.bank_ready ? 'Configured' : 'Not set up') + '</span>';
      }).catch(function (err) { notify(err.message, true); });
  }
  function setReadinessTag(elId, ready) {
    var el = document.getElementById(elId);
    if (!el) return;
    el.textContent = ready ? 'Configured' : 'Not configured';
    el.className = 'boc-tag ' + (ready ? 'tag-boarding' : 'tag-low');
  }
  function submitMobileMoneyAccount(e) {
    e.preventDefault();
    var form = e.target;
    var provider = form.getAttribute('data-provider');
    var btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    postJson(financeApi, { action: 'save_account', method: 'MOBILE_MONEY', provider: provider, account_name: form.account_name.value.trim(), account_number: form.account_number.value.trim() })
      .then(function () { form.reset(); return loadRevenue(); }).then(function () { btn.disabled = false; notify(provider + ' agent code saved.'); })
      .catch(function (err) { btn.disabled = false; notify(err.message, true); });
  }
  function submitBankAccount(e) {
    e.preventDefault();
    var form = e.target;
    var btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    postJson(financeApi, { action: 'save_account', method: 'BANK', provider: form.provider.value, account_name: form.account_name.value.trim(), account_number: form.account_number.value.trim() })
      .then(function () { form.reset(); return loadRevenue(); }).then(function () { btn.disabled = false; notify('Bank account saved.'); })
      .catch(function (err) { btn.disabled = false; notify(err.message, true); });
  }
  function toggleRevMethod(method) {
    document.querySelectorAll('.rev-method-toggle').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-method') === method); });
    document.getElementById('rev-mobile-money-forms').style.display = method === 'MOBILE_MONEY' ? '' : 'none';
    document.getElementById('rev-bank-form').style.display = method === 'BANK' ? 'flex' : 'none';
  }
  function requestWithdrawal() {
    var amount = Number(document.getElementById('rev-withdraw-amount').value);
    var accountId = document.getElementById('rev-withdraw-account').value;
    if (!amount || amount <= 0) { notify('Enter a valid withdrawal amount.', true); return; }
    postJson(financeApi, { action: 'request_withdrawal', amount: amount, account_id: accountId || null })
      .then(function () { document.getElementById('rev-withdraw-amount').value = ''; return loadRevenue(); }).then(function () { notify('Withdrawal requested.'); }).catch(function (err) { notify(err.message, true); });
  }

  // ── Analytics ────────────────────────────────────────────────────
  function loadAnalytics() {
    Promise.all([getJson(analyticsApi + '?action=overview'), getJson(analyticsApi + '?action=trend')]).then(function (r) {
      var overview = r[0], trend = r[1];
      document.getElementById('an-gross').textContent = money(overview.gross_revenue);
      document.getElementById('an-tickets').textContent = overview.tickets_sold;
      document.getElementById('an-avg').textContent = money(overview.average_ticket_price);
      document.getElementById('an-routes').textContent = overview.active_routes;
      renderTrendSvg(document.getElementById('an-trend-svg'), trend.series);
      document.getElementById('an-routes-body').innerHTML = overview.routes.length ? overview.routes.map(function (r) {
        return '<tr><td>' + esc(r.origin) + ' → ' + esc(r.destination) + '</td><td>' + r.tickets + '</td><td>' + money(r.revenue) + '</td></tr>';
      }).join('') : '<tr><td colspan="3" style="color:var(--boc-text-muted);">No ticket sales yet.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }

  // ── Passengers ───────────────────────────────────────────────────
  function loadPassengers() {
    var search = document.getElementById('pax-search').value.trim();
    getJson(passengersApi + '?action=list&search=' + encodeURIComponent(search)).then(function (r) {
      document.getElementById('pax-body').innerHTML = r.passengers.length ? r.passengers.map(function (p) {
        return '<tr style="cursor:pointer;" data-view-passenger="' + esc(p.identity) + '"><td>' + esc(p.name) + '</td><td>' + esc(p.phone || '—') + '</td><td>' + p.ticket_count + '</td><td>' + money(p.total_spend) + '</td><td>' + esc(p.routes.join(', ')) + '</td><td>' + fmtDate(p.last_trip_at) + '</td></tr>';
      }).join('') : '<tr><td colspan="6" style="color:var(--boc-text-muted);">No passengers yet.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function showPassengerDetail(identity) {
    getJson(passengersApi + '?action=detail&identity=' + encodeURIComponent(identity)).then(function (r) {
      document.getElementById('pax-detail-title').textContent = r.name;
      document.getElementById('pax-detail-body').innerHTML = r.items.length ? r.items.map(function (t) {
        return '<tr><td>' + esc(t.ticket_id) + '</td><td>' + esc(t.route) + '</td><td>' + fmtDate(t.departure_at) + '</td><td>' + (t.amount !== null ? money(t.amount) : '—') + '</td><td><span class="boc-tag ' + (TICKET_BADGE[t.status] || 'tag-scheduled') + '">' + esc(t.status.toUpperCase().replace('_', ' ')) + '</span></td></tr>';
      }).join('') : '<tr><td colspan="5" style="color:var(--boc-text-muted);">No tickets found.</td></tr>';
      document.getElementById('boc-passenger-detail-modal').style.display = 'flex';
    }).catch(function (err) { notify(err.message, true); });
  }

  // ── Fleet ────────────────────────────────────────────────────────
  var DOC_BADGE = { valid: 'tag-boarding', expiring_soon: 'tag-scheduled', expired: 'tag-low' };
  function loadFleet() {
    getJson(fleetApi + '?action=overview').then(function (o) {
      document.getElementById('fleet-kpi-total').textContent = o.total;
      document.getElementById('fleet-kpi-available').textContent = o.available;
      document.getElementById('fleet-kpi-on-trip').textContent = o.on_trip;
      document.getElementById('fleet-kpi-maintenance').textContent = o.maintenance;
      document.getElementById('fleet-kpi-doc-issues').textContent = o.document_issues;
    }).catch(function () {});
    getJson(fleetApi + '?action=vehicles').then(function (r) {
      fleetState.vehicles = r.vehicles;
      var grid = document.getElementById('fleet-vehicles-grid');
      grid.innerHTML = r.vehicles.length ? r.vehicles.map(function (v) {
        var docBadge = v.next_document ? '<span class="boc-tag ' + (DOC_BADGE[v.next_document.status] || 'tag-scheduled') + '">' + esc(v.next_document.type) + ' ' + esc(v.next_document.status.replace('_', ' ')) + '</span>' : '<span style="color:var(--boc-text-muted);font-size:0.7rem;">No documents on file</span>';
        var issueBadge = v.open_issue_count > 0 ? '<span class="boc-tag tag-low">' + v.open_issue_count + ' open issue' + (v.open_issue_count === 1 ? '' : 's') + '</span>' : '';
        var nextDep = v.next_departure ? '<div style="font-size:0.7rem;color:var(--boc-text-soft);">Next: ' + esc(v.next_departure.title) + ' · ' + fmtDate(v.next_departure.departure_at) + '</div>' : '<div style="font-size:0.7rem;color:var(--boc-text-muted);">No upcoming departure</div>';
        return '<div class="boc-kpi-card" style="flex-direction:column;align-items:stretch;gap:0.5rem;">' +
          '<div style="display:flex;justify-content:space-between;align-items:start;"><div><div style="font-weight:800;color:#fff;">' + esc(v.reg_number) + '</div><div style="font-size:0.76rem;color:var(--boc-text-soft);">' + esc(v.make_model) + ' · ' + v.capacity + ' seats</div></div><span class="boc-tag ' + (v.status === 'active' ? 'tag-boarding' : v.status === 'maintenance' ? 'tag-scheduled' : 'tag-low') + '">' + esc(v.status.toUpperCase()) + '</span></div>' +
          '<div>' + docBadge + ' ' + issueBadge + '</div>' + nextDep +
          '<button class="boc-btn-solid" style="justify-content:center;" type="button" data-open-vehicle="' + esc(v.id) + '">Manage</button>' +
          '</div>';
      }).join('') : '<div style="color:var(--boc-text-muted);font-size:0.82rem;">No vehicles in your fleet yet.</div>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function submitVehicle(e) {
    e.preventDefault();
    var form = e.target;
    var amenities = Array.prototype.filter.call(form.querySelectorAll('input[type="checkbox"][name^="amenity_"], input[type="checkbox"][name^="acc_"]'), function (cb) { return cb.checked; }).map(function (cb) { return cb.name; });
    var payload = {
      action: 'create_vehicle', reg_number: form.reg_number.value.trim(), fleet_number: form.fleet_number.value.trim(),
      make_model: form.make_model.value.trim(), vehicle_type: form.vehicle_type.value, manufacturer: form.manufacturer.value.trim(),
      year: form.year.value, color: form.color.value.trim(), capacity: Number(form.capacity.value),
      standing_capacity: form.standing_capacity.value, luggage_capacity: form.luggage_capacity.value.trim(),
      amenities: amenities, gps_enabled: form.gps_enabled.checked, maintenance_threshold_km: form.maintenance_threshold_km.value,
      boarding_buffer_minutes: form.boarding_buffer_minutes.value, status: form.status.value, photo_url: form.photo_url.value,
    };
    postJson(fleetApi, payload).then(function (result) {
      var vehicleId = result.created_vehicle_id;
      var docSaves = Array.prototype.map.call(document.querySelectorAll('[data-doc-expiry]'), function (dateInput) {
        if (!dateInput.value) return Promise.resolve();
        var type = dateInput.getAttribute('data-doc-type');
        var fileInput = document.querySelector('[data-doc-file][data-doc-type="' + type + '"]');
        return postJson(fleetApi, { action: 'save_document', vehicle_id: vehicleId, document_type: type, expiry_date: dateInput.value, file_url: fileInput ? fileInput.value : '' });
      });
      return Promise.all(docSaves);
    }).then(function () {
      resetVehicleWizard();
      document.getElementById('boc-vehicle-modal').style.display = 'none';
      return loadFleet();
    }).then(function () { notify('Bus activated.'); })
      .catch(function (err) { notify(err.message, true); });
  }
  function switchVehTab(tabName) {
    document.querySelectorAll('.veh-tab-btn').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-veh-tab') === tabName); });
    document.querySelectorAll('.veh-tab-panel').forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-veh-panel') === tabName); p.style.display = p.getAttribute('data-veh-panel') === tabName ? 'block' : 'none'; });
    if (tabName === 'documents') loadVehicleDocuments();
    if (tabName === 'maintenance') loadVehicleMaintenance();
    if (tabName === 'issues') loadVehicleIssues();
    if (tabName === 'assignments') loadVehicleAssignments();
  }
  function openVehicleDetail(vehicleId) {
    fleetState.activeVehicleId = vehicleId;
    var v = fleetState.vehicles.find(function (x) { return x.id === vehicleId; });
    document.getElementById('veh-detail-title').textContent = v ? v.reg_number + ' — ' + v.make_model : 'Vehicle';
    document.getElementById('veh-status-select').value = v ? v.status : 'active';
    document.getElementById('veh-overview-summary').innerHTML = v ? ('<div><strong style="color:#fff;">' + esc(v.reg_number) + '</strong> · ' + esc(v.make_model) + ' · ' + v.capacity + ' seats</div><div style="margin-top:0.3rem;">' + (v.next_departure ? 'Next departure: ' + esc(v.next_departure.title) + ' · ' + fmtDate(v.next_departure.departure_at) : 'No upcoming departure') + '</div>') : '';
    switchVehTab('overview');
    document.getElementById('boc-vehicle-detail-modal').style.display = 'flex';
  }
  function loadVehicleDocuments() {
    getJson(fleetApi + '?action=documents&vehicle_id=' + encodeURIComponent(fleetState.activeVehicleId)).then(function (r) {
      document.getElementById('veh-documents-body').innerHTML = r.documents.length ? r.documents.map(function (d) {
        return '<tr><td>' + esc(d.type) + '</td><td>' + esc(d.expiry_date) + '</td><td><span class="boc-tag ' + (DOC_BADGE[d.status] || 'tag-scheduled') + '">' + esc(d.status.replace('_', ' ').toUpperCase()) + '</span></td></tr>';
      }).join('') : '<tr><td colspan="3" style="color:var(--boc-text-muted);font-size:0.78rem;">No documents on file.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function loadVehicleMaintenance() {
    getJson(fleetApi + '?action=maintenance&vehicle_id=' + encodeURIComponent(fleetState.activeVehicleId)).then(function (r) {
      document.getElementById('veh-maintenance-body').innerHTML = r.items.length ? r.items.map(function (m) {
        return '<tr><td>' + esc(m.service_type) + '</td><td>' + esc(m.serviced_at) + '</td><td>' + (m.mileage_km !== null ? m.mileage_km.toLocaleString() + ' km' : '—') + '</td><td>' + (m.cost !== null ? money(m.cost) : '—') + '</td></tr>';
      }).join('') : '<tr><td colspan="4" style="color:var(--boc-text-muted);font-size:0.78rem;">No service history logged yet.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function loadVehicleIssues() {
    getJson(fleetApi + '?action=issues&vehicle_id=' + encodeURIComponent(fleetState.activeVehicleId)).then(function (r) {
      document.getElementById('veh-issues-body').innerHTML = r.items.length ? r.items.map(function (i) {
        return '<tr><td>' + esc(i.category) + '</td><td><span class="boc-tag ' + (i.severity === 'critical' ? 'tag-low' : 'tag-scheduled') + '">' + esc(i.severity.toUpperCase()) + '</span></td><td>' + (i.cost !== null ? money(i.cost) : '—') + '</td><td><span class="boc-tag ' + (i.status === 'open' ? 'tag-low' : 'tag-boarding') + '">' + esc(i.status.toUpperCase()) + '</span></td><td>' + (i.status === 'open' ? '<button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;" type="button" data-resolve-issue="' + i.id + '">Resolve</button>' : '—') + '</td></tr>';
      }).join('') : '<tr><td colspan="5" style="color:var(--boc-text-muted);font-size:0.78rem;">No issues reported.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function loadVehicleAssignments() {
    getJson(fleetApi + '?action=assignments&vehicle_id=' + encodeURIComponent(fleetState.activeVehicleId)).then(function (r) {
      document.getElementById('veh-assignments-body').innerHTML = r.items.length ? r.items.map(function (a) {
        return '<tr><td>' + esc(a.title) + '</td><td>' + fmtDate(a.departure_at) + '</td><td><span class="boc-tag ' + (a.status === 'cancelled' ? 'tag-low' : a.status === 'boarding' || a.status === 'departed' ? 'tag-boarding' : 'tag-scheduled') + '">' + esc(a.status.toUpperCase()) + '</span></td></tr>';
      }).join('') : '<tr><td colspan="3" style="color:var(--boc-text-muted);font-size:0.78rem;">This vehicle has never been assigned to a trip.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function submitVehicleStatus() {
    postJson(fleetApi, { action: 'update_vehicle', vehicle_id: fleetState.activeVehicleId, status: document.getElementById('veh-status-select').value })
      .then(function () { return loadFleet(); }).then(function () { notify('Vehicle status updated.'); }).catch(function (err) { notify(err.message, true); });
  }
  function submitVehicleDocument(e) {
    e.preventDefault();
    var form = e.target;
    postJson(fleetApi, { action: 'save_document', vehicle_id: fleetState.activeVehicleId, document_type: form.document_type.value.trim(), expiry_date: form.expiry_date.value })
      .then(function () { form.reset(); loadVehicleDocuments(); return loadFleet(); }).then(function () { notify('Document saved.'); }).catch(function (err) { notify(err.message, true); });
  }
  function submitVehicleMaintenance(e) {
    e.preventDefault();
    var form = e.target;
    postJson(fleetApi, { action: 'log_maintenance', vehicle_id: fleetState.activeVehicleId, service_type: form.service_type.value.trim(), serviced_at: form.serviced_at.value, mileage_km: form.mileage_km.value, cost: form.cost.value })
      .then(function () { form.reset(); loadVehicleMaintenance(); notify('Service logged.'); }).catch(function (err) { notify(err.message, true); });
  }
  function submitVehicleIssue(e) {
    e.preventDefault();
    var form = e.target;
    postJson(fleetApi, { action: 'report_issue', vehicle_id: fleetState.activeVehicleId, category: form.category.value.trim(), description: form.description.value.trim(), severity: form.severity.value, cost: form.cost.value })
      .then(function () { form.reset(); loadVehicleIssues(); return loadFleet(); }).then(function () { notify('Issue reported.'); }).catch(function (err) { notify(err.message, true); });
  }
  function resolveVehicleIssue(issueId) {
    postJson(fleetApi, { action: 'resolve_issue', issue_id: issueId }).then(function () { loadVehicleIssues(); return loadFleet(); }).then(function () { notify('Issue resolved.'); }).catch(function (err) { notify(err.message, true); });
  }

  // ── Drivers ──────────────────────────────────────────────────────
  var LICENSE_BADGE = { valid: 'tag-boarding', expiring_soon: 'tag-scheduled', expired: 'tag-low' };
  var driverState = { drivers: [], activeDriverId: null };
  function loadDrivers() {
    return Promise.all([getJson(driversApi + '?action=list'), getJson(driversApi + '?action=overview')]).then(function (r) {
      driverState.drivers = r[0].drivers;
      document.getElementById('drv-kpi-total').textContent = r[1].total;
      document.getElementById('drv-kpi-active').textContent = r[1].active;
      document.getElementById('drv-kpi-on-trip').textContent = r[1].on_trip;
      document.getElementById('drv-kpi-license-issues').textContent = r[1].license_issues;
      document.getElementById('drv-body').innerHTML = driverState.drivers.length ? driverState.drivers.map(function (d) {
        var expiryCell = d.license_expiry ? esc(d.license_expiry) + (d.license_status !== 'valid' ? ' <span class="boc-tag ' + (LICENSE_BADGE[d.license_status] || 'tag-scheduled') + '" style="margin-left:0.3rem;">' + esc(d.license_status.replace('_', ' ').toUpperCase()) + '</span>' : '') : '—';
        return '<tr style="cursor:pointer;" data-view-driver="' + esc(d.id) + '"><td>' + esc(d.name) + '</td><td>' + esc(d.phone || '—') + '</td><td>' + esc(d.license_number || '—') + '</td><td>' + expiryCell + '</td><td><span class="boc-tag ' + (d.status === 'active' ? 'tag-boarding' : 'tag-low') + '">' + esc(d.status.toUpperCase()) + '</span></td>' +
          '<td><button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;" type="button" data-toggle-driver="' + esc(d.id) + '" data-status="' + esc(d.status) + '">' + (d.status === 'active' ? 'Deactivate' : 'Activate') + '</button></td></tr>';
      }).join('') : '<tr><td colspan="6" style="color:var(--boc-text-muted);">No drivers yet.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function submitDriver(e) {
    e.preventDefault();
    var form = e.target;
    postJson(driversApi, { action: 'create', name: form.name.value.trim(), phone: form.phone.value.trim(), license_number: form.license_number.value.trim(), license_expiry: form.license_expiry.value })
      .then(function () { form.reset(); setWizStep('drv', 1); document.getElementById('boc-driver-modal').style.display = 'none'; return loadDrivers(); }).then(function () { notify('Driver added.'); })
      .catch(function (err) { notify(err.message, true); });
  }
  function toggleDriverStatus(driverId, currentStatus) {
    postJson(driversApi, { action: 'update', driver_id: driverId, status: currentStatus === 'active' ? 'inactive' : 'active' })
      .then(function () { return loadDrivers(); }).catch(function (err) { notify(err.message, true); });
  }
  function switchDrvTab(tabName) {
    document.querySelectorAll('.drv-tab-btn').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-drv-tab') === tabName); });
    document.querySelectorAll('.drv-tab-panel').forEach(function (p) { p.classList.toggle('active', p.getAttribute('data-drv-panel') === tabName); p.style.display = p.getAttribute('data-drv-panel') === tabName ? 'block' : 'none'; });
    if (tabName === 'assignments') loadDriverAssignments();
  }
  function openDriverDetail(driverId) {
    driverState.activeDriverId = driverId;
    var d = driverState.drivers.find(function (x) { return x.id === driverId; });
    document.getElementById('drv-detail-title').textContent = d ? d.name : 'Driver';
    var form = document.getElementById('drv-detail-form');
    form.name.value = d ? d.name : ''; form.phone.value = d ? (d.phone || '') : ''; form.license_number.value = d ? (d.license_number || '') : ''; form.license_expiry.value = d ? (d.license_expiry || '') : ''; form.status.value = d ? d.status : 'active';
    switchDrvTab('overview');
    document.getElementById('boc-driver-detail-modal').style.display = 'flex';
  }
  function loadDriverAssignments() {
    getJson(driversApi + '?action=assignments&driver_id=' + encodeURIComponent(driverState.activeDriverId)).then(function (r) {
      document.getElementById('drv-assignments-body').innerHTML = r.items.length ? r.items.map(function (a) {
        return '<tr><td>' + esc(a.title) + '</td><td>' + fmtDate(a.departure_at) + '</td><td><span class="boc-tag ' + (a.status === 'cancelled' ? 'tag-low' : a.status === 'boarding' || a.status === 'departed' ? 'tag-boarding' : 'tag-scheduled') + '">' + esc(a.status.toUpperCase()) + '</span></td></tr>';
      }).join('') : '<tr><td colspan="3" style="color:var(--boc-text-muted);font-size:0.78rem;">This driver has never been assigned to a trip.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function submitDriverDetail(e) {
    e.preventDefault();
    var form = e.target;
    postJson(driversApi, { action: 'update', driver_id: driverState.activeDriverId, name: form.name.value.trim(), phone: form.phone.value.trim(), license_number: form.license_number.value.trim(), license_expiry: form.license_expiry.value, status: form.status.value })
      .then(function () { document.getElementById('boc-driver-detail-modal').style.display = 'none'; return loadDrivers(); }).then(function () { notify('Driver updated.'); })
      .catch(function (err) { notify(err.message, true); });
  }

  // ── Maintenance (fleet-wide) ─────────────────────────────────────
  function loadMaintenance() {
    Promise.all([getJson(fleetApi + '?action=vehicles'), getJson(fleetApi + '?action=maintenance'), getJson(fleetApi + '?action=issues&status=open'), getJson(fleetApi + '?action=all_document_issues'), getJson(fleetApi + '?action=maintenance_overview')]).then(function (r) {
      var vehicles = r[0].vehicles, history = r[1].items, issues = r[2].items, docIssues = r[3].items, overview = r[4];

      document.getElementById('mnt-kpi-doc-issues').textContent = overview.document_issues;
      document.getElementById('mnt-kpi-open-issues').textContent = overview.open_issues;
      document.getElementById('mnt-kpi-services').textContent = overview.services_last_30_days;
      document.getElementById('mnt-kpi-cost').textContent = money(overview.total_cost_last_30_days);

      var vehicleOptions = '<option value="">Select vehicle…</option>' + vehicles.map(function (v) { return '<option value="' + esc(v.id) + '">' + esc(v.reg_number) + ' — ' + esc(v.make_model) + '</option>'; }).join('');
      document.querySelector('#mnt-maintenance-form select[name="vehicle_id"]').innerHTML = vehicleOptions;
      document.querySelector('#mnt-issue-form select[name="vehicle_id"]').innerHTML = vehicleOptions;

      document.getElementById('mnt-documents-body').innerHTML = docIssues.length ? docIssues.map(function (r) {
        return '<tr><td>' + esc(r.reg_number) + '</td><td>' + esc(r.type) + '</td><td>' + esc(r.expiry_date) + '</td><td><span class="boc-tag ' + (DOC_BADGE[r.status] || 'tag-scheduled') + '">' + esc(r.status.replace('_', ' ').toUpperCase()) + '</span></td></tr>';
      }).join('') : '<tr><td colspan="4" style="color:var(--boc-text-muted);">No documents expiring or expired across your fleet.</td></tr>';

      document.getElementById('mnt-history-body').innerHTML = history.length ? history.map(function (h) {
        return '<tr><td>' + esc(h.reg_number) + '</td><td>' + esc(h.service_type) + '</td><td>' + esc(h.serviced_at) + '</td><td>' + (h.mileage_km !== null ? h.mileage_km.toLocaleString() + ' km' : '—') + '</td><td>' + (h.cost !== null ? money(h.cost) : '—') + '</td></tr>';
      }).join('') : '<tr><td colspan="5" style="color:var(--boc-text-muted);">No service history yet.</td></tr>';

      document.getElementById('mnt-issues-body').innerHTML = issues.length ? issues.map(function (i) {
        return '<tr><td>' + esc(i.reg_number) + '</td><td>' + esc(i.category) + ': ' + esc(i.description) + '</td><td><span class="boc-tag ' + (i.severity === 'critical' ? 'tag-low' : i.severity === 'medium' ? 'tag-scheduled' : 'tag-boarding') + '">' + esc(i.severity.toUpperCase()) + '</span></td><td>' + (i.cost !== null ? money(i.cost) : '—') + '</td><td><button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;" type="button" data-resolve-issue="' + i.id + '">Resolve</button></td></tr>';
      }).join('') : '<tr><td colspan="5" style="color:var(--boc-text-muted);">No open issues.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function resolveIssue(issueId) {
    postJson(fleetApi, { action: 'resolve_issue', issue_id: issueId }).then(function () { return loadMaintenance(); }).then(function () { notify('Issue resolved.'); }).catch(function (err) { notify(err.message, true); });
  }
  function submitMaintenanceLog(e) {
    e.preventDefault();
    var form = e.target;
    postJson(fleetApi, { action: 'log_maintenance', vehicle_id: form.vehicle_id.value, service_type: form.service_type.value.trim(), serviced_at: form.serviced_at.value, mileage_km: form.mileage_km.value, cost: form.cost.value })
      .then(function () { form.reset(); return loadMaintenance(); }).then(function () { notify('Service logged.'); }).catch(function (err) { notify(err.message, true); });
  }
  function submitIssueReport(e) {
    e.preventDefault();
    var form = e.target;
    postJson(fleetApi, { action: 'report_issue', vehicle_id: form.vehicle_id.value, category: form.category.value.trim(), description: form.description.value.trim(), severity: form.severity.value, cost: form.cost.value, mark_out_of_service: form.mark_out_of_service.checked })
      .then(function () { form.reset(); return loadMaintenance(); }).then(function () { notify('Issue reported.'); }).catch(function (err) { notify(err.message, true); });
  }

  // ── Tickets (standalone search/cancel) ──────────────────────────
  var TICKET_BADGE = { issued: 'tag-scheduled', boarded: 'tag-boarding', cancelled: 'tag-low', no_show: 'tag-low' };
  function loadTicketsOverview() {
    getJson(ticketsApi + '?action=overview').then(function (o) {
      document.getElementById('tix-kpi-departures').textContent = o.today_departures;
      document.getElementById('tix-kpi-sold').textContent = o.tickets_sold;
      document.getElementById('tix-kpi-seats').textContent = o.available_seats;
      document.getElementById('tix-kpi-revenue').textContent = money(o.revenue_paid);
    }).catch(function () {});
  }
  function searchTickets() {
    loadTicketsOverview();
    var qs = 'action=search&code=' + encodeURIComponent(document.getElementById('tix-filter-code').value) +
      '&passenger=' + encodeURIComponent(document.getElementById('tix-filter-passenger').value) +
      '&route=' + encodeURIComponent(document.getElementById('tix-filter-route').value) +
      '&status=' + encodeURIComponent(document.getElementById('tix-filter-status').value) +
      '&date_from=' + encodeURIComponent(document.getElementById('tix-filter-date-from').value) +
      '&date_to=' + encodeURIComponent(document.getElementById('tix-filter-date-to').value);
    getJson(ticketsApi + '?' + qs).then(function (r) {
      state.tickets = r.tickets;
      document.getElementById('tix-body').innerHTML = r.tickets.length ? r.tickets.map(function (t) {
        return '<tr style="cursor:pointer;" data-view-ticket="' + esc(t.ticket_id) + '"><td>' + esc(t.ticket_id) + '</td><td>' + esc(t.passenger_name) + '</td><td>' + esc(t.origin) + ' → ' + esc(t.destination) + '</td><td>' + fmtDate(t.departure_at) + '</td><td><span class="boc-tag ' + (TICKET_BADGE[t.status] || 'tag-scheduled') + '">' + esc(t.status.toUpperCase().replace('_',' ')) + '</span></td>' +
          '<td><button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;" type="button" data-view-ticket="' + esc(t.ticket_id) + '">View</button></td></tr>';
      }).join('') : '<tr><td colspan="6" style="color:var(--boc-text-muted);">No tickets match this search.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function cancelTicketRow(ticketId) {
    if (!window.confirm('Cancel ticket ' + ticketId + '? The seat will be released back to inventory.')) return;
    postJson(ticketsApi, { action: 'cancel', ticket_id: ticketId }).then(function () { document.getElementById('boc-ticket-detail-modal').style.display = 'none'; return searchTickets(); }).then(function () { notify('Ticket cancelled.'); }).catch(function (err) { notify(err.message, true); });
  }
  function showTicketDetail(ticketId) {
    var t = state.tickets.find(function (x) { return x.ticket_id === ticketId; });
    if (!t) return;
    document.getElementById('tix-detail-body').innerHTML =
      '<div class="boc-wizard-review-row"><span>Booking ID</span><span>' + esc(t.booking_id || '—') + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Ticket ID</span><span>' + esc(t.ticket_id) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Passenger</span><span>' + esc(t.passenger_name) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Route</span><span>' + esc(t.origin) + ' → ' + esc(t.destination) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Departure</span><span>' + fmtDate(t.departure_at) + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Seat</span><span>' + esc(t.seat_label || '—') + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Amount</span><span>' + (t.amount !== null ? money(t.amount) : '—') + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Payment Status</span><span>' + esc(t.payment_status || '—') + '</span></div>' +
      '<div class="boc-wizard-review-row"><span>Ticket Status</span><span>' + esc(t.status.toUpperCase().replace('_',' ')) + '</span></div>';
    var cancelBtn = document.getElementById('tix-detail-cancel-btn');
    cancelBtn.style.display = t.status === 'issued' ? '' : 'none';
    cancelBtn.setAttribute('data-cancel-ticket', t.ticket_id);
    document.getElementById('boc-ticket-detail-modal').style.display = 'flex';
  }

  // ── Company Settings ─────────────────────────────────────────────
  function loadSettings() {
    getJson(settingsApi).then(function (r) {
      var form = document.getElementById('settings-form');
      form.business_name.value = r.business_name; form.phone.value = r.phone; form.address.value = r.address; form.city.value = r.city; form.description.value = r.description;
      document.getElementById('settings-email').value = r.account_email;
      var badge = document.getElementById('settings-approval-badge');
      badge.textContent = r.approval_status.toUpperCase();
      badge.className = 'boc-tag ' + (r.approval_status === 'approved' ? 'tag-boarding' : r.approval_status === 'rejected' ? 'tag-low' : 'tag-scheduled');
      ttState.businessName = r.business_name;
      renderTicketTemplatePreview();
    }).catch(function (err) { notify(err.message, true); });
    loadTicketTemplate();
  }

  // ── Ticket Template ────────────────────────────────────────────
  var ttState = { style: 'classic_blue', businessName: '' };
  var UTHENGA_LOGO_LIGHT = base + 'assets/images/logo-light.png';
  var UTHENGA_LOGO_DARK = base + 'assets/images/logo-dark.png';
  function selectTicketStyle(style) {
    ttState.style = style;
    document.querySelectorAll('.tt-style-swatch').forEach(function (el) { el.classList.toggle('active', el.getAttribute('data-style') === style); });
    renderTicketTemplatePreview();
  }
  function loadTicketTemplate() {
    getJson(settingsApi + '?action=ticket_template').then(function (r) {
      document.getElementById('tt-logo-url').value = r.logo_url || '';
      document.getElementById('tt-accent-color').value = r.accent_color || '#e63946';
      document.getElementById('tt-footer-message').value = r.footer_message || '';
      document.getElementById('tt-contact-phone').value = r.contact_phone || '';
      document.getElementById('tt-contact-email').value = r.contact_email || '';
      selectTicketStyle(r.template_style);
    }).catch(function (err) { notify(err.message, true); });
  }
  function submitTicketTemplate(e) {
    e.preventDefault();
    var btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true;
    postJson(settingsApi, {
      action: 'save_ticket_template', template_style: ttState.style,
      logo_url: document.getElementById('tt-logo-url').value.trim(), accent_color: document.getElementById('tt-accent-color').value,
      footer_message: document.getElementById('tt-footer-message').value.trim(), contact_phone: document.getElementById('tt-contact-phone').value.trim(), contact_email: document.getElementById('tt-contact-email').value.trim(),
    }).then(function () { btn.disabled = false; notify('Ticket template saved.'); }).catch(function (err) { btn.disabled = false; notify(err.message, true); });
  }
  function renderTicketTemplatePreview() {
    var style = ttState.style;
    var logoUrl = document.getElementById('tt-logo-url').value.trim();
    var accent = document.getElementById('tt-accent-color').value;
    var footer = document.getElementById('tt-footer-message').value.trim();
    var phone = document.getElementById('tt-contact-phone').value.trim();
    var email = document.getElementById('tt-contact-email').value.trim();
    var companyName = ttState.businessName || 'Your Company';
    var uthengaLogo = style === 'minimal_white' ? UTHENGA_LOGO_DARK : UTHENGA_LOGO_LIGHT;
    var logoHtml = logoUrl ? '<img class="btt-logo-img" src="' + esc(logoUrl) + '" alt="">' : '<div class="btt-logo-fallback">' + esc(companyName.charAt(0).toUpperCase() || 'U') + '</div>';
    var photoHtml = (style === 'modern_card' || style === 'premium_dark') ? '<div class="btt-photo-strip" style="background:linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.02));"></div>' : '';
    var eyebrow = style === 'modern_card' ? '<div class="btt-eyebrow">Your Journey</div>' : '';
    var footerParts = [];
    if (footer) footerParts.push(esc(footer));
    var contactParts = [phone, email].filter(Boolean);
    if (contactParts.length) footerParts.push(esc(contactParts.join(' · ')));
    var footerText = footerParts.length ? footerParts.join(' — ') : 'Safe travels.';
    var badgeLabel = style === 'premium_dark' ? 'Premium' : (style === 'minimal_white' ? 'Standard' : 'Economy');
    var qrSvg = '';
    try { var qr = qrcode(0, 'M'); qr.addData('UTH-BUS-SAMPLE.preview'); qr.make(); qrSvg = qr.createSvgTag(2, 0); } catch (e) {}

    var html = '' +
      '<div class="btt-card btt-card--' + style + '" style="--btt-accent-override:' + esc(accent) + ';">' +
        '<div class="btt-header">' +
          '<div class="btt-brand">' + logoHtml + '<div><div class="btt-company-name">' + esc(companyName) + '</div>' + (style !== 'classic_blue' && style !== 'mobile_wallet' ? '<div class="btt-company-sub">Bus Ticket</div>' : '') + '</div></div>' +
          '<span class="btt-badge">' + badgeLabel + '</span>' +
        '</div>' +
        '<div class="btt-body">' + eyebrow + photoHtml +
          '<div class="btt-route">Lilongwe <span class="btt-route-arrow">→</span> Blantyre</div>' +
          '<div class="btt-meta-grid">' +
            '<div class="btt-meta-item"><div><div class="btt-meta-value">26 Aug 2026</div><div class="btt-meta-label">Date</div></div></div>' +
            '<div class="btt-meta-item"><div><div class="btt-meta-value">08:00</div><div class="btt-meta-label">Departure</div></div></div>' +
            '<div class="btt-meta-item"><div><div class="btt-meta-value">BUS-0021</div><div class="btt-meta-label">Toyota Coaster</div></div></div>' +
            '<div class="btt-meta-item"><div><div class="btt-meta-value">12B</div><div class="btt-meta-label">Seat</div></div></div>' +
            '<div class="btt-meta-item"><div><div class="btt-meta-value">UTH-BUS-48291</div><div class="btt-meta-label">Booking ID</div></div></div>' +
            '<div class="btt-meta-item"><div><div class="btt-meta-value">MWK 20,000</div><div class="btt-meta-label">Fare</div></div></div>' +
          '</div>' +
          '<div class="btt-passenger">John Phiri</div>' +
          '<div class="btt-stub"><div class="btt-qr-box">' + qrSvg + '</div><div class="btt-ticket-id-block"><div class="btt-ticket-id">UTB10048291</div><div class="btt-scan-hint">Scan to verify</div></div></div>' +
        '</div>' +
        '<div class="btt-footer"><span>' + footerText + '</span><span class="btt-powered-by"><img src="' + esc(uthengaLogo) + '" alt=""> Powered by Uthenga</span></div>' +
      '</div>';
    document.getElementById('tt-preview').innerHTML = html;
  }
  function submitSettings(e) {
    e.preventDefault();
    var form = e.target;
    postJson(settingsApi, { business_name: form.business_name.value.trim(), phone: form.phone.value.trim(), address: form.address.value.trim(), city: form.city.value.trim(), description: form.description.value.trim() })
      .then(function () { return loadSettings(); }).then(function () { notify('Settings saved.'); document.querySelector('.boc-user-pill div div').textContent = form.business_name.value.trim(); }).catch(function (err) { notify(err.message, true); });
  }

  // ── User Management (real RBAC via UthengaStaffService, organization-scoped) ──
  var umState = { roles: [], enums: null };
  function loadStaff() {
    return Promise.all([getJson(staffApi + '?action=roles'), getJson(staffApi + '?action=staff'), getJson(staffApi + '?action=invitations'), getJson(staffApi + '?action=enums')]).then(function (r) {
      var roles = r[0], members = r[1].items || [], invitations = r[2], enums = r[3];
      umState.roles = roles; umState.enums = enums;
      var roleOptions = roles.map(function (role) { return '<option value="' + esc(role.id) + '">' + esc(role.name) + '</option>'; }).join('');
      document.getElementById('um-invite-role').innerHTML = roleOptions;

      document.getElementById('um-staff-body').innerHTML = members.length ? members.map(function (m) {
        var roleSelect = '<select class="boc-search-input" style="padding:0.15rem 0.4rem;font-size:0.72rem;width:auto;" data-change-role="' + esc(m.staff_id) + '">' + roles.map(function (role) { return '<option value="' + esc(role.id) + '"' + (role.id === m.role_id ? ' selected' : '') + '>' + esc(role.name) + '</option>'; }).join('') + '</select>';
        return '<tr><td>' + esc(m.name) + '</td><td>' + esc(m.email) + '</td><td>' + roleSelect + '</td><td><span class="boc-tag ' + (m.status === 'active' ? 'tag-boarding' : 'tag-low') + '">' + esc(String(m.status).toUpperCase()) + '</span></td>' +
          '<td><button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;" type="button" data-toggle-staff="' + esc(m.staff_id) + '" data-status="' + esc(m.status) + '">' + (m.status === 'active' ? 'Suspend' : 'Reactivate') + '</button></td></tr>';
      }).join('') : '<tr><td colspan="5" style="color:var(--boc-text-muted);">Just you so far — invite your team below.</td></tr>';

      document.getElementById('um-invitations-body').innerHTML = invitations.length ? invitations.map(function (i) {
        var actions = i.status === 'pending' ? '<button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;margin-right:0.3rem;" type="button" data-resend-invite="' + esc(i.id) + '">Resend</button><button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;background:var(--boc-red);" type="button" data-revoke-invite="' + esc(i.id) + '">Revoke</button>' : '—';
        return '<tr><td>' + esc(i.name) + '</td><td>' + esc(i.email) + '</td><td>' + esc(i.role_name || '') + '</td><td><span class="boc-tag tag-scheduled">' + esc(String(i.status).toUpperCase()) + '</span></td><td>' + fmtDate(i.expires_at) + '</td><td>' + actions + '</td></tr>';
      }).join('') : '<tr><td colspan="6" style="color:var(--boc-text-muted);">No pending invitations.</td></tr>';

      document.getElementById('um-roles-body').innerHTML = roles.length ? roles.map(function (role) {
        return '<tr><td>' + esc(role.name) + (role.is_system ? ' <span class="boc-tag tag-scheduled" style="margin-left:0.3rem;">SYSTEM</span>' : '') + '</td><td>' + esc(role.scope_label) + '</td><td>' + role.members + '</td>' +
          '<td><button class="boc-btn-solid" style="padding:0.2rem 0.5rem;font-size:0.68rem;" type="button" data-edit-role="' + esc(role.id) + '">Permissions</button></td></tr>';
      }).join('') : '<tr><td colspan="4" style="color:var(--boc-text-muted);">No roles yet.</td></tr>';
    }).catch(function (err) { notify(err.message, true); });
  }
  function submitInvite(e) {
    e.preventDefault();
    var form = e.target;
    postJson(staffApi, { action: 'invite', first_name: form.first_name.value.trim(), last_name: form.last_name.value.trim(), email: form.email.value.trim(), role_id: form.role_id.value, scope_type: 'organization' })
      .then(function () { form.reset(); document.getElementById('um-invite-modal').style.display = 'none'; return loadStaff(); }).then(function () { notify('Invitation sent.'); })
      .catch(function (err) { notify(err.message, true); });
  }
  function toggleStaffStatusRow(staffId, currentStatus) {
    postJson(staffApi, { action: 'set_status', staff_id: staffId, status: currentStatus === 'active' ? 'suspended' : 'active' })
      .then(function () { return loadStaff(); }).catch(function (err) { notify(err.message, true); });
  }
  function changeStaffRole(staffId, roleId) {
    postJson(staffApi, { action: 'change_role', staff_id: staffId, role_id: roleId })
      .then(function () { return loadStaff(); }).then(function () { notify('Role updated.'); }).catch(function (err) { notify(err.message, true); loadStaff(); });
  }
  function resendInvitation(invitationId) {
    postJson(staffApi, { action: 'resend_invitation', invitation_id: invitationId })
      .then(function () { return loadStaff(); }).then(function () { notify('Invitation resent.'); }).catch(function (err) { notify(err.message, true); });
  }
  function revokeInvitation(invitationId) {
    postJson(staffApi, { action: 'revoke_invitation', invitation_id: invitationId })
      .then(function () { return loadStaff(); }).then(function () { notify('Invitation revoked.'); }).catch(function (err) { notify(err.message, true); });
  }
  function renderRolePermissionsForm(permissions) {
    permissions = permissions || {};
    var enums = umState.enums;
    var levelOptions = enums.levels.map(function (lvl) { return '<option value="' + esc(lvl) + '">' + esc(lvl.charAt(0).toUpperCase() + lvl.slice(1)) + '</option>'; }).join('');
    var groupsHtml = Object.keys(enums.module_groups).map(function (groupName) {
      var rows = enums.module_groups[groupName].map(function (mod) {
        var current = permissions[mod] || 'none';
        var select = '<select name="perm_' + esc(mod) + '" class="boc-search-input" style="padding:0.2rem 0.5rem;font-size:0.72rem;width:auto;">' + levelOptions.replace('value="' + current + '"', 'value="' + current + '" selected') + '</select>';
        return '<div style="display:flex;justify-content:space-between;align-items:center;padding:0.25rem 0;"><span style="font-size:0.78rem;color:var(--boc-text-soft);">' + esc(enums.modules[mod] || mod) + '</span>' + select + '</div>';
      }).join('');
      return '<div><div style="font-size:0.66rem;font-weight:800;text-transform:uppercase;color:var(--boc-text-muted);margin-bottom:0.2rem;">' + esc(groupName) + '</div>' + rows + '</div>';
    }).join('');
    document.getElementById('um-role-permissions-body').innerHTML = groupsHtml;
  }
  function openRoleModal(roleId) {
    var form = document.getElementById('um-role-form');
    form.reset(); form.role_id.value = roleId || '';
    if (roleId) {
      getJson(staffApi + '?action=role_detail&role_id=' + encodeURIComponent(roleId)).then(function (role) {
        document.getElementById('um-role-modal-title').textContent = role.name;
        form.name.value = role.name; form.description.value = role.description || '';
        renderRolePermissionsForm(role.permissions);
        document.getElementById('um-role-delete-btn').style.display = role.is_system ? 'none' : 'inline-flex';
        document.getElementById('um-role-delete-btn').setAttribute('data-delete-role', role.id);
        document.getElementById('um-role-modal').style.display = 'flex';
      }).catch(function (err) { notify(err.message, true); });
    } else {
      document.getElementById('um-role-modal-title').textContent = 'New Role';
      renderRolePermissionsForm({});
      document.getElementById('um-role-delete-btn').style.display = 'none';
      document.getElementById('um-role-modal').style.display = 'flex';
    }
  }
  function submitRoleForm(e) {
    e.preventDefault();
    var form = e.target;
    var permissions = {};
    umState.enums.levels && Object.keys(umState.enums.modules).forEach(function (mod) {
      var field = form.querySelector('[name="perm_' + mod + '"]');
      if (field) permissions[mod] = field.value;
    });
    postJson(staffApi, { action: 'save_role', role_id: form.role_id.value, name: form.name.value.trim(), description: form.description.value.trim(), permissions: permissions })
      .then(function () { document.getElementById('um-role-modal').style.display = 'none'; return loadStaff(); }).then(function () { notify('Role saved.'); })
      .catch(function (err) { notify(err.message, true); });
  }
  function deleteRoleFromModal(roleId) {
    if (!window.confirm('Delete this role? This only works if no staff members currently hold it.')) return;
    postJson(staffApi, { action: 'delete_role', role_id: roleId })
      .then(function () { document.getElementById('um-role-modal').style.display = 'none'; return loadStaff(); }).then(function () { notify('Role deleted.'); })
      .catch(function (err) { notify(err.message, true); });
  }


  // ── Trips & Departures ──────────────────────────────────────────
  var tripsFilter = 'all';
  function loadTrips() {
    return getJson(departuresApi).then(function (r) {
      state.departures = r.departures || [];
      var total = state.departures.length;
      var open = 0, boarding = 0, transit = 0, completed = 0, cancelled = 0;
      state.departures.forEach(function (d) {
        var st = (d.status || '').toLowerCase();
        if (st === 'scheduled' || st === 'open') open++;
        else if (st === 'boarding') boarding++;
        else if (st === 'in_transit' || st === 'departed') transit++;
        else if (st === 'completed') completed++;
        else if (st === 'cancelled') cancelled++;
      });
      document.getElementById('trips-kpi-total').textContent = total;
      document.getElementById('trips-kpi-open').textContent = open;
      document.getElementById('trips-kpi-boarding').textContent = boarding;
      document.getElementById('trips-kpi-transit').textContent = transit;
      document.getElementById('trips-kpi-completed').textContent = completed;
      document.getElementById('trips-kpi-cancelled').textContent = cancelled;
      renderTripsTable();
    }).catch(function (err) { notify(err.message, true); });
  }

  function setTripsFilter(filter, el) {
    tripsFilter = filter;
    if (el) {
      var pills = document.querySelectorAll('#trips-filter-pills button');
      pills.forEach(function (p) { p.classList.remove('active-pill'); });
      el.classList.add('active-pill');
    }
    renderTripsTable();
  }

    function renderTripsTable() {
    var body = document.getElementById('boc-trips-table-body');
    if (!body) return;
    var searchEl = document.getElementById('trips-search-input');
    var query = (searchEl ? searchEl.value : '').toLowerCase().trim();
    var now = new Date();
    var todayStr = now.toISOString().slice(0, 10);

    var filtered = state.departures.filter(function (d) {
      var st = (d.status || '').toLowerCase();
      var dDate = (d.departure_at || '').slice(0, 10);
      if (tripsFilter === 'today' && dDate !== todayStr) return false;
      if (tripsFilter === 'upcoming' && dDate < todayStr) return false;
      if (tripsFilter === 'completed' && st !== 'completed') return false;
      if (tripsFilter === 'cancelled' && st !== 'cancelled') return false;

      if (query) {
        var text = (d.title + ' ' + d.origin + ' ' + d.destination + ' ' + (d.vehicle ? d.vehicle.reg_number : '') + ' ' + (d.driver ? d.driver.name : '')).toLowerCase();
        if (text.indexOf(query) === -1) return false;
      }
      return true;
    });

    if (!filtered.length) {
      body.innerHTML = '<tr><td colspan="6" style="color:var(--boc-text-muted);">No matching trips found.</td></tr>';
      return;
    }

    body.innerHTML = filtered.map(function (dep) {
      var sold = dep.seat_classes.reduce(function (s, c) { return s + (c.total_seats - c.remaining_seats); }, 0);
      var total = dep.seat_classes.reduce(function (s, c) { return s + c.total_seats; }, 0);
      var pct = total ? Math.round((sold / total) * 100) : 0;
      var statusCls = dep.status === 'boarding' ? 'tag-boarding' : dep.status === 'cancelled' ? 'tag-low' : 'tag-scheduled';
      var assignment = (dep.vehicle ? esc(dep.vehicle.reg_number) : '<span style="color:var(--boc-text-muted);">No bus</span>') +
        '<br><small style="color:var(--boc-text-muted);">' + (dep.driver ? esc(dep.driver.name) : 'No driver') + '</small>';

      return '<tr data-departure-id="' + esc(dep.departure_id) + '">' +
        '<td><strong style="color:#fff;">' + fmtDate(dep.departure_at) + '</strong></td>' +
        '<td><div style="font-weight:700;color:#fff;">' + esc(dep.title) + '</div><small style="color:var(--boc-text-muted);">' + esc(dep.origin) + ' → ' + esc(dep.destination) + '</small></td>' +
        '<td>' + assignment + '</td>' +
        '<td><div style="font-weight:700;color:#fff;">' + sold + ' / ' + total + ' (' + pct + '%)</div><div class="boc-progress" style="margin-top:4px;"><div class="boc-progress-fill" style="width:' + pct + '%;"></div></div></td>' +
        '<td><span class="boc-tag ' + statusCls + '">' + esc((dep.status || 'Scheduled').toUpperCase()) + '</span></td>' +
        '<td>' +
        '<button class="boc-btn-solid" style="padding:0.25rem 0.6rem;font-size:0.72rem;margin-right:0.3rem;" type="button" data-dep-assign="' + esc(dep.departure_id) + '">Assign</button>' +
        (dep.status === 'scheduled' || dep.status === 'boarding' ? '<button class="boc-btn-solid" style="padding:0.25rem 0.6rem;font-size:0.72rem;background:var(--boc-red);" type="button" data-dep-cancel="' + esc(dep.departure_id) + '">Cancel</button>' : '') +
        '</td>' +
        '</tr>';
    }).join('');
  }

  // ── Reports ──────────────────────────────────────────
  function loadReports() {
    var today = new Date().toISOString().slice(0, 10);
    var dFrom = document.getElementById('rpt-from-date');
    var dTo = document.getElementById('rpt-to-date');
    if (dFrom && !dFrom.value) dFrom.value = today;
    if (dTo && !dTo.value) dTo.value = today;
    generateReport();
  }

  function generateReport() {
    var typeEl = document.getElementById('rpt-type-select');
    var head = document.getElementById('rpt-table-head');
    var body = document.getElementById('rpt-table-body');
    var title = document.getElementById('rpt-title');
    if (!typeEl || !head || !body || !title) return;
    var type = typeEl.value;

    if (type === 'trips') {
      title.textContent = 'Trip Operational Manifest Report';
      head.innerHTML = '<tr><th>Departure</th><th>Route</th><th>Bus</th><th>Driver</th><th>Occupancy</th><th>Status</th></tr>';
      body.innerHTML = state.departures.map(function (d) {
        var sold = d.seat_classes.reduce(function (s, c) { return s + (c.total_seats - c.remaining_seats); }, 0);
        var total = d.seat_classes.reduce(function (s, c) { return s + c.total_seats; }, 0);
        return '<tr><td>' + fmtDate(d.departure_at) + '</td><td>' + esc(d.origin) + ' → ' + esc(d.destination) + '</td><td>' + (d.vehicle ? esc(d.vehicle.reg_number) : '—') + '</td><td>' + (d.driver ? esc(d.driver.name) : '—') + '</td><td>' + sold + '/' + total + '</td><td>' + esc(d.status) + '</td></tr>';
      }).join('') || '<tr><td colspan="6" style="color:var(--boc-text-muted);">No trips recorded.</td></tr>';
    } else if (type === 'tickets') {
      title.textContent = 'Ticket Sales & Revenue Report';
      head.innerHTML = '<tr><th>Ticket ID</th><th>Passenger</th><th>Route</th><th>Departure</th><th>Status</th><th>Fare</th></tr>';
      body.innerHTML = (state.tickets || []).map(function (t) {
        return '<tr><td>' + esc(t.ticket_id || t.id) + '</td><td>' + esc(t.passenger_name || '—') + '</td><td>' + esc(t.origin || '') + ' → ' + esc(t.destination || '') + '</td><td>' + fmtDate(t.departure_at) + '</td><td>' + esc(t.status) + '</td><td>' + money(t.fare || 0) + '</td></tr>';
      }).join('') || '<tr><td colspan="6" style="color:var(--boc-text-muted);">No ticket data available. Load tickets in Tickets tab first.</td></tr>';
    } else {
      title.textContent = 'Operational Report Summary';
      head.innerHTML = '<tr><th>Metric</th><th>Value</th><th>Status</th></tr>';
      body.innerHTML = '<tr><td>Total Active Routes</td><td>' + state.routes.length + '</td><td>Active</td></tr>' +
        '<tr><td>Total Scheduled Trips</td><td>' + state.departures.length + '</td><td>Operational</td></tr>' +
        '<tr><td>System Status</td><td>Connected &amp; Verified</td><td>Normal</td></tr>';
    }
  }

  function exportReport(format) {
    if (format === 'pdf') {
      window.print();
      return;
    }
    var rows = [];
    var headEls = document.querySelectorAll('#rpt-table-head th');
    var headers = Array.prototype.slice.call(headEls).map(function (th) { return '"' + th.textContent.replace(/"/g, '""') + '"'; });
    rows.push(headers.join(','));

    var rowEls = document.querySelectorAll('#rpt-table-body tr');
    rowEls.forEach(function (tr) {
      var cols = Array.prototype.slice.call(tr.querySelectorAll('td')).map(function (td) { return '"' + td.textContent.replace(/"/g, '""') + '"'; });
      if (cols.length) rows.push(cols.join(','));
    });

    var csvContent = 'data:text/csv;charset=utf-8,' + encodeURIComponent(rows.join('\n'));
    var link = document.createElement('a');
    link.setAttribute('href', csvContent);
    link.setAttribute('download', 'uthenga_bus_report_' + new Date().toISOString().slice(0, 10) + '.csv');
    document.body.appendChild(link);
    link.click();
    link.remove();
    notify('Report exported to CSV.');
  }

    // ── Drag & Drop File Upload Handler ─────────────────────────────
  function handleFileUpload(fileInput, type, targetInputId, previewId) {
    var file = fileInput.files && fileInput.files[0];
    if (!file) return;
    var preview = document.getElementById(previewId);
    if (preview) preview.innerHTML = '<div style="color:var(--boc-primary);font-weight:700;padding:0.8rem;text-align:center;">Uploading ' + esc(file.name) + '…</div>';
    
    var fd = new FormData();
    fd.append('file', file);
    fd.append('type', type || 'image');

    fetch(base + 'api/tie/vendor/transport/upload.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrf },
      body: fd
    }).then(function (r) { return r.text(); })
      .then(function (text) {
        var res;
        try {
          var cleanText = text.substring(text.indexOf('{'), text.lastIndexOf('}') + 1);
          res = JSON.parse(cleanText || text);
        } catch(e) {
          throw new Error('Upload server error: ' + text.replace(/<[^>]*>?/gm, '').slice(0, 100));
        }
        var payload = res.result || res;
        if (!payload || !payload.url) throw new Error((res && res.error && res.error.message) || 'Upload failed.');

        var fileUrl = payload.url;
        if (targetInputId) {
          var target = document.getElementById(targetInputId);
          if (target) {
            target.value = fileUrl;
            target.dispatchEvent(new Event('input'));
          }
        }
        if (preview) {
          if (type === 'image') {
            preview.innerHTML = '<div style="display:flex;flex-direction:column;align-items:center;padding:0.6rem;background:rgba(16,185,129,0.06);border:2px solid #10b981;border-radius:12px;"><img src="' + esc(fileUrl) + '" alt="Cover Photo" style="max-height:140px;max-width:100%;border-radius:8px;object-fit:cover;margin-bottom:0.5rem;box-shadow:0 4px 12px rgba(0,0,0,0.25);"><div style="color:#10b981;font-weight:800;font-size:0.78rem;">✓ Photo Uploaded Successfully</div><div style="font-size:0.7rem;color:var(--boc-text-muted);margin-top:2px;">Click dropzone to replace</div></div>';
          } else {
            preview.innerHTML = '<div style="padding:0.8rem;background:rgba(16,185,129,0.06);border:1px solid #10b981;border-radius:10px;color:#10b981;font-weight:800;font-size:0.78rem;">✓ File Uploaded: ' + esc(payload.original_name || 'Document') + '</div>';
          }
        }
        notify('File uploaded successfully!');
      }).catch(function (err) {
        if (preview) preview.innerHTML = '<div style="color:var(--boc-red);padding:0.8rem;font-weight:700;">Upload failed: ' + esc(err.message) + '</div>';
        notify(err.message, true);
      });
  }

  // ── Multi-Step Wizard Shell Engine ──────────────────────────────
  var wizCurrentStep = { veh: 1, route: 1, dep: 1, drv: 1, mnt: 1 };
  var wizTotalSteps = { veh: 6, route: 6, dep: 7, drv: 6, mnt: 6 };

  function setWizStep(wizardKey, stepNum) {
    wizCurrentStep[wizardKey] = stepNum;
    var total = wizTotalSteps[wizardKey] || 6;
    var modalId = wizardKey === 'veh' ? 'boc-vehicle-modal' : wizardKey === 'route' ? 'boc-route-modal' : wizardKey === 'dep' ? 'boc-departure-modal' : wizardKey === 'drv' ? 'boc-driver-modal' : 'boc-maintenance-modal';
    var modalEl = document.getElementById(modalId);
    if (!modalEl) return;

    var numEl = modalEl.querySelector('#' + wizardKey + '-step-num');
    if (numEl) numEl.textContent = stepNum;

    // Update sidebar step highlights
    modalEl.querySelectorAll('.boc-wiz-side-step').forEach(function(stepEl) {
      var s = Number(stepEl.getAttribute('data-wiz-step'));
      stepEl.classList.toggle('active', s === stepNum);
    });

    // Update main step pane
    modalEl.querySelectorAll('.wiz-step-pane').forEach(function(paneEl) {
      var p = paneEl.getAttribute('data-wiz-pane');
      paneEl.style.display = (p === wizardKey + '-' + stepNum) ? 'block' : 'none';
    });

    // Update footer navigation buttons
    var prevBtn = modalEl.querySelector('#' + wizardKey + '-wiz-prev-btn');
    var nextBtn = modalEl.querySelector('#' + wizardKey + '-wiz-next-btn');
    var submitBtn = modalEl.querySelector('#' + wizardKey + '-wiz-submit-btn');

    if (prevBtn) prevBtn.style.visibility = (stepNum > 1) ? 'visible' : 'hidden';
    if (nextBtn) nextBtn.style.display = (stepNum < total) ? 'inline-flex' : 'none';
    if (submitBtn) submitBtn.style.display = (stepNum === total) ? 'inline-flex' : 'none';

    if (wizardKey === 'veh' && stepNum === 6) renderVehicleReview();
    if (wizardKey === 'drv' && stepNum === 6) renderDriverReview();
  }

  function stepWiz(wizardKey, dir) {
    var cur = wizCurrentStep[wizardKey] || 1;
    var total = wizTotalSteps[wizardKey] || 6;
    if (dir > 0) {
      var modalId = wizardKey === 'veh' ? 'boc-vehicle-modal' : wizardKey === 'route' ? 'boc-route-modal' : wizardKey === 'dep' ? 'boc-departure-modal' : wizardKey === 'drv' ? 'boc-driver-modal' : 'boc-maintenance-modal';
      var modalEl = document.getElementById(modalId);
      var currentPane = modalEl ? modalEl.querySelector('[data-wiz-pane="' + wizardKey + '-' + cur + '"]') : null;
      if (currentPane) {
        var invalidField = Array.prototype.slice.call(currentPane.querySelectorAll('[required]')).find(function (el) { return el.offsetParent !== null && !el.reportValidity(); });
        if (invalidField) return;
      }
    }
    var target = Math.max(1, Math.min(total, cur + dir));
    setWizStep(wizardKey, target);
  }


  // ── Seat layout builder (visual planning aid — only the total count is persisted) ──
  var seatGridState = { rows: [], selected: null };
  function seatGridRowsFromCapacity(cap) {
    var rows = [];
    var remaining = Math.max(1, cap);
    while (remaining > 0) {
      var n = Math.min(4, remaining);
      var row = [];
      for (var i = 0; i < n; i++) row.push('sellable');
      rows.push(row);
      remaining -= n;
    }
    return rows;
  }
  function rebuildSeatGrid() {
    var cap = Number(document.getElementById('veh-cap-input').value) || 0;
    seatGridState.rows = seatGridRowsFromCapacity(cap);
    seatGridState.selected = null;
    renderSeatGrid();
  }
  function renderSeatGrid() {
    var grid = document.getElementById('veh-wiz-seat-grid');
    if (!grid) return;
    var html = '';
    var physical = 0, blocked = 0;
    seatGridState.rows.forEach(function (row, r) {
      row.forEach(function (status, c) {
        physical++;
        if (status === 'blocked') blocked++;
        var seatNo = (r + 1 < 10 ? '0' + (r + 1) : (r + 1)) + ['A', 'B', 'C', 'D'][c];
        var isSelected = seatGridState.selected && seatGridState.selected[0] === r && seatGridState.selected[1] === c;
        var bg = status === 'blocked' ? 'var(--boc-orange)' : 'var(--boc-green)';
        var border = isSelected ? '2px solid #fff' : '2px solid transparent';
        html += '<div style="width:44px;height:44px;border-radius:8px;background:' + bg + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:900;cursor:pointer;border:' + border + ';" title="Seat ' + seatNo + (status === 'blocked' ? ' (blocked)' : '') + '" data-seat-row="' + r + '" data-seat-col="' + c + '">' + seatNo + '</div>';
      });
    });
    grid.innerHTML = html;
    var sellable = physical - blocked;
    document.getElementById('veh-seat-count-val').textContent = 'Physical seats: ' + physical + ' · Sellable: ' + sellable + ' · Blocked: ' + blocked;
    document.getElementById('veh-cap-input').value = physical || '';
  }
  function seatGridAddRow() {
    seatGridState.rows.push(['sellable', 'sellable', 'sellable', 'sellable']);
    renderSeatGrid();
  }
  function seatGridRemoveRow() {
    if (seatGridState.rows.length > 1) seatGridState.rows.pop();
    seatGridState.selected = null;
    renderSeatGrid();
  }
  function seatGridAddSeat() {
    var last = seatGridState.rows[seatGridState.rows.length - 1];
    if (last && last.length < 4) last.push('sellable');
    else seatGridState.rows.push(['sellable']);
    renderSeatGrid();
  }
  function seatGridRemoveSeat() {
    var last = seatGridState.rows[seatGridState.rows.length - 1];
    if (!last) return;
    if (last.length > 1) last.pop();
    else if (seatGridState.rows.length > 1) seatGridState.rows.pop();
    seatGridState.selected = null;
    renderSeatGrid();
  }
  function seatGridToggleBlocked() {
    if (!seatGridState.selected) { notify('Click a seat first, then Mark/Unmark Blocked.', true); return; }
    var r = seatGridState.selected[0], c = seatGridState.selected[1];
    var row = seatGridState.rows[r];
    if (!row) return;
    row[c] = row[c] === 'blocked' ? 'sellable' : 'blocked';
    renderSeatGrid();
  }
  function resetVehicleWizard() {
    document.getElementById('boc-vehicle-form').reset();
    seatGridState = { rows: [], selected: null };
    document.getElementById('veh-wiz-seat-grid').innerHTML = '';
    document.getElementById('veh-seat-count-val').textContent = 'Physical seats: 0 · Sellable: 0 · Blocked: 0';
    ['reg', 'ins', 'rw', 'op'].forEach(function (key) {
      document.getElementById('doc-' + key + '-status').textContent = 'Not yet added';
      document.getElementById('doc-' + key + '-status').style.color = 'var(--boc-text-muted)';
      document.getElementById('doc-' + key + '-url').value = '';
    });
    document.getElementById('veh-image-preview').innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--boc-primary);"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg><div style="font-size:0.8rem;font-weight:700;color:var(--boc-text);margin-top:0.3rem;">Click or drag bus image here to upload</div>';
    document.getElementById('veh-image-url').value = '';
    setWizStep('veh', 1);
  }
  function renderVehicleReview() {
    var form = document.getElementById('boc-vehicle-form');
    var physical = seatGridState.rows.reduce(function (n, row) { return n + row.length; }, 0);
    var blocked = seatGridState.rows.reduce(function (n, row) { return n + row.filter(function (s) { return s === 'blocked'; }).length; }, 0);
    var docsFilled = document.querySelectorAll('[data-doc-expiry]');
    var docsFilledCount = Array.prototype.filter.call(docsFilled, function (el) { return el.value; }).length;
    var name = [form.manufacturer.value.trim(), form.make_model.value.trim()].filter(Boolean).join(' ') || 'Unnamed bus';
    var items = [
      { label: (form.reg_number.value.trim() || 'No registration number entered') + (form.fleet_number.value.trim() ? ' · ' + form.fleet_number.value.trim() : ''), ok: !!form.reg_number.value.trim() },
      { label: name, ok: true },
      { label: physical + ' seats (' + (physical - blocked) + ' sellable' + (blocked ? ', ' + blocked + ' blocked' : '') + ')', ok: physical > 0 },
      { label: docsFilledCount + ' of 4 document types on file', ok: true },
      { label: 'Default status: ' + (form.status.value === 'active' ? 'Available' : form.status.value === 'maintenance' ? 'Maintenance' : 'Inactive'), ok: true },
    ];
    document.getElementById('veh-wiz-review').innerHTML = items.map(function (it) {
      return '<div class="boc-wiz-review-item"><span class="check">' + (it.ok ? '✓' : '⚠') + '</span><span>' + esc(it.label) + '</span></div>';
    }).join('');
  }
  function renderDriverReview() {
    var form = document.getElementById('boc-driver-form');
    var items = [
      { label: form.name.value.trim() || 'No name entered', ok: !!form.name.value.trim() },
      { label: form.phone.value.trim() ? 'Phone: ' + form.phone.value.trim() : 'No phone number entered', ok: !!form.phone.value.trim() },
      { label: form.license_number.value.trim() ? 'License: ' + form.license_number.value.trim() : 'No license number entered', ok: !!form.license_number.value.trim() },
      { label: form.license_expiry.value ? 'Expires ' + form.license_expiry.value : 'No license expiry entered', ok: !!form.license_expiry.value },
    ];
    document.getElementById('drv-wiz-review').innerHTML = items.map(function (it) {
      return '<div class="boc-wiz-review-item"><span class="check">' + (it.ok ? '✓' : '⚠') + '</span><span>' + esc(it.label) + '</span></div>';
    }).join('');
  }

  
  // ── Theme Switching Engine ──
  function applyBusTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('uthenga_bus_theme', theme);
    document.cookie = 'uthenga_theme=' + theme + '; path=/; max-age=31536000';
    var icon = document.getElementById('boc-theme-icon');
    var text = document.getElementById('boc-theme-text');
    if (theme === 'light') {
      if (icon) icon.innerHTML = '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
      if (text) text.textContent = 'Light Mode';
    } else {
      if (icon) icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>';
      if (text) text.textContent = 'Dark Mode';
    }
  }

  
  window.toggleBusTheme = toggleBusTheme;

  function toggleBusTheme() {
    var current = document.documentElement.getAttribute('data-theme') || 'dark';
    var next = (current === 'light') ? 'dark' : 'light';
    applyBusTheme(next);
  }

  // Load saved theme on boot
  (function() {
    var saved = localStorage.getItem('uthenga_bus_theme') || 'dark';
    applyBusTheme(saved);
  })();

  // A departure row/card click jumps to the real Boarding tab with that
  // departure pre-selected, instead of opening a per-trip workspace — this
  // project doesn't have a real single-screen "everything about this trip"
  // view yet, and the Boarding/Tickets/Passengers tabs already show the
  // real data for a departure without duplicating it in a second surface.
  function jumpToDeparture(departureId) {
    var navEl = document.querySelector('.boc-nav-item[onclick*="\'boarding\'"]');
    switchSubTab('boarding', navEl);
    loadBoardingDepartures().then(function () {
      var select = document.getElementById('boc-boarding-departure');
      var hasOption = Array.prototype.some.call(select.options, function (o) { return o.value === departureId; });
      if (hasOption) select.value = departureId;
    });
  }

  function bind() {
    document.getElementById('boc-today-departures').addEventListener('click', function(e) {
      var card = e.target.closest('.boc-ops-item');
      if (card && card.getAttribute('data-departure-id')) jumpToDeparture(card.getAttribute('data-departure-id'));
    });
    var tripsBody = document.getElementById('boc-trips-table-body');
    if (tripsBody) {
      tripsBody.addEventListener('click', function(e) {
        var assignBtn = e.target.closest('[data-dep-assign]');
        if (assignBtn) { openAssignModal(assignBtn.getAttribute('data-dep-assign')); return; }
        var cancelBtn = e.target.closest('[data-dep-cancel]');
        if (cancelBtn) { cancelDeparture(cancelBtn.getAttribute('data-dep-cancel')); return; }
        var row = e.target.closest('tr[data-departure-id]');
        if (row) jumpToDeparture(row.getAttribute('data-departure-id'));
      });
    }
    document.getElementById('boc-new-route-btn').addEventListener('click', openRouteModal);
    document.getElementById('boc-route-form').addEventListener('submit', submitRoute);
    document.getElementById('boc-add-seat-row').addEventListener('click', function () { addSeatRow(); });
    document.getElementById('boc-seat-rows').addEventListener('click', function (e) { if (e.target.closest('[data-remove-seat-row]')) e.target.closest('div').remove(); });
    document.getElementById('boc-new-departure-btn').addEventListener('click', function () { openDepartureModal(); });
    document.getElementById('boc-departure-form').addEventListener('submit', submitDeparture);
    ['dep-listing-image', 'dep-listing-title', 'dep-listing-description', 'dep-listing-highlights'].forEach(function (id) {
      document.getElementById(id).addEventListener('input', renderListingPreview);
    });
    document.querySelectorAll('input[name="dep-card-style"]').forEach(function (r) { r.addEventListener('change', renderListingPreview); });
    document.getElementById('boc-routes-grid').addEventListener('click', function (e) {
      var s = e.target.closest('[data-schedule]'); if (s) { openDepartureModal(s.getAttribute('data-schedule')); return; }
      var a = e.target.closest('[data-add-class]'); if (a) { addSeatClassToRoute(a.getAttribute('data-add-class')); return; }
    });
    document.getElementById('boc-departures-body').addEventListener('click', function (e) {
      var c = e.target.closest('[data-cancel]'); if (c) { cancelDeparture(c.getAttribute('data-cancel')); return; }
      var a = e.target.closest('[data-assign]'); if (a) openAssignModal(a.getAttribute('data-assign'));
    });
    document.getElementById('boc-save-assignment').addEventListener('click', saveAssignment);
    document.getElementById('boc-confirm-cancel-trip').addEventListener('click', confirmCancelDeparture);
    document.getElementById('boc-start-session').addEventListener('click', startSession);
    document.getElementById('boc-stop-session').addEventListener('click', stopSession);
    document.getElementById('boc-toggle-camera').addEventListener('click', toggleCamera);
    document.getElementById('boc-verify-code').addEventListener('click', function () { verify(document.getElementById('boc-code-input').value, 'manual'); });
    document.getElementById('boc-code-input').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); verify(e.target.value, 'manual'); } });
    setSessionUiActive(false);

    document.querySelectorAll('.rev-mm-form').forEach(function (f) { f.addEventListener('submit', submitMobileMoneyAccount); });
    document.getElementById('rev-bank-form').addEventListener('submit', submitBankAccount);
    document.querySelectorAll('.rev-method-toggle').forEach(function (b) { b.addEventListener('click', function () { toggleRevMethod(b.getAttribute('data-method')); }); });
    document.getElementById('rev-request-withdrawal').addEventListener('click', requestWithdrawal);

    document.getElementById('boc-new-vehicle-btn').addEventListener('click', function () { resetVehicleWizard(); document.getElementById('boc-vehicle-modal').style.display = 'flex'; });
    document.getElementById('veh-wiz-seat-grid').addEventListener('click', function (e) {
      var seatEl = e.target.closest('[data-seat-row]');
      if (!seatEl) return;
      seatGridState.selected = [Number(seatEl.getAttribute('data-seat-row')), Number(seatEl.getAttribute('data-seat-col'))];
      renderSeatGrid();
    });
    document.getElementById('boc-vehicle-form').addEventListener('submit', submitVehicle);
    document.getElementById('fleet-vehicles-grid').addEventListener('click', function (e) { var b = e.target.closest('[data-open-vehicle]'); if (b) openVehicleDetail(b.getAttribute('data-open-vehicle')); });
    document.querySelectorAll('.veh-tab-btn').forEach(function (b) { b.addEventListener('click', function () { switchVehTab(b.getAttribute('data-veh-tab')); }); });
    document.getElementById('veh-issues-body').addEventListener('click', function (e) { var b = e.target.closest('[data-resolve-issue]'); if (b) resolveVehicleIssue(Number(b.getAttribute('data-resolve-issue'))); });
    document.getElementById('veh-status-select').addEventListener('change', submitVehicleStatus);
    document.getElementById('veh-document-form').addEventListener('submit', submitVehicleDocument);
    document.getElementById('veh-maintenance-form').addEventListener('submit', submitVehicleMaintenance);
    document.getElementById('veh-issue-form').addEventListener('submit', submitVehicleIssue);

    document.getElementById('boc-new-driver-btn').addEventListener('click', function () { document.getElementById('boc-driver-form').reset(); setWizStep('drv', 1); document.getElementById('boc-driver-modal').style.display = 'flex'; });
    document.getElementById('boc-driver-form').addEventListener('submit', submitDriver);
    document.getElementById('drv-body').addEventListener('click', function (e) {
      var b = e.target.closest('[data-toggle-driver]'); if (b) { toggleDriverStatus(b.getAttribute('data-toggle-driver'), b.getAttribute('data-status')); return; }
      var row = e.target.closest('[data-view-driver]'); if (row) openDriverDetail(row.getAttribute('data-view-driver'));
    });
    document.querySelectorAll('.drv-tab-btn').forEach(function (b) { b.addEventListener('click', function () { switchDrvTab(b.getAttribute('data-drv-tab')); }); });
    document.getElementById('drv-detail-form').addEventListener('submit', submitDriverDetail);
    document.getElementById('pax-search-btn').addEventListener('click', loadPassengers);
    document.getElementById('pax-search').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); loadPassengers(); } });
    document.getElementById('pax-body').addEventListener('click', function (e) { var row = e.target.closest('[data-view-passenger]'); if (row) showPassengerDetail(row.getAttribute('data-view-passenger')); });

    document.getElementById('mnt-issues-body').addEventListener('click', function (e) { var b = e.target.closest('[data-resolve-issue]'); if (b) resolveIssue(Number(b.getAttribute('data-resolve-issue'))); });
    document.getElementById('mnt-maintenance-form').addEventListener('submit', submitMaintenanceLog);
    document.getElementById('mnt-issue-form').addEventListener('submit', submitIssueReport);

    document.getElementById('tix-search-btn').addEventListener('click', searchTickets);
    document.getElementById('tix-body').addEventListener('click', function (e) { var v = e.target.closest('[data-view-ticket]'); if (v) showTicketDetail(v.getAttribute('data-view-ticket')); });
    document.getElementById('tix-detail-cancel-btn').addEventListener('click', function (e) { cancelTicketRow(e.target.getAttribute('data-cancel-ticket')); });

    document.getElementById('settings-form').addEventListener('submit', submitSettings);
    document.getElementById('ticket-template-form').addEventListener('submit', submitTicketTemplate);
    document.querySelectorAll('.tt-style-swatch').forEach(function (el) { el.addEventListener('click', function () { selectTicketStyle(el.getAttribute('data-style')); }); });
    ['tt-logo-url', 'tt-accent-color', 'tt-footer-message', 'tt-contact-phone', 'tt-contact-email'].forEach(function (id) {
      document.getElementById(id).addEventListener('input', renderTicketTemplatePreview);
    });

    document.getElementById('um-invite-btn').addEventListener('click', function () { document.getElementById('um-invite-form').reset(); document.getElementById('um-invite-modal').style.display = 'flex'; });
    document.getElementById('um-invite-form').addEventListener('submit', submitInvite);
    document.getElementById('um-staff-body').addEventListener('click', function (e) { var b = e.target.closest('[data-toggle-staff]'); if (b) toggleStaffStatusRow(b.getAttribute('data-toggle-staff'), b.getAttribute('data-status')); });
    document.getElementById('um-staff-body').addEventListener('change', function (e) { var s = e.target.closest('[data-change-role]'); if (s) changeStaffRole(s.getAttribute('data-change-role'), s.value); });
    document.getElementById('um-invitations-body').addEventListener('click', function (e) {
      var r = e.target.closest('[data-resend-invite]'); if (r) { resendInvitation(r.getAttribute('data-resend-invite')); return; }
      var v = e.target.closest('[data-revoke-invite]'); if (v) revokeInvitation(v.getAttribute('data-revoke-invite'));
    });
    document.getElementById('um-roles-body').addEventListener('click', function (e) { var b = e.target.closest('[data-edit-role]'); if (b) openRoleModal(b.getAttribute('data-edit-role')); });
    document.getElementById('um-new-role-btn').addEventListener('click', function () { openRoleModal(null); });
    document.getElementById('um-role-form').addEventListener('submit', submitRoleForm);
    document.getElementById('um-role-delete-btn').addEventListener('click', function (e) { deleteRoleFromModal(e.target.getAttribute('data-delete-role')); });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bind();
    // Deep-linking to ?tab=X (bookmark, refresh, direct nav) must show that
    // panel and load its real data — not just highlight the nav item while
    // silently leaving the panel hidden and its form fields empty/default.
    switchSubTab(initialTab, document.querySelector('.boc-nav-item.active'));
  });
  return { loadDashboard: loadDashboard, setWizStep: setWizStep, stepWiz: stepWiz, rebuildSeatGrid: rebuildSeatGrid, seatGridAddRow: seatGridAddRow, seatGridRemoveRow: seatGridRemoveRow, seatGridAddSeat: seatGridAddSeat, seatGridRemoveSeat: seatGridRemoveSeat, seatGridToggleBlocked: seatGridToggleBlocked,  loadTrips: loadTrips, setTripsFilter: setTripsFilter, renderTripsTable: renderTripsTable, loadReports: loadReports, generateReport: generateReport, exportReport: exportReport, handleFileUpload: handleFileUpload, openAssignModal: openAssignModal, cancelDeparture: cancelDeparture,  loadRoutesAndDepartures: loadRoutesAndDepartures, loadBoardingDepartures: loadBoardingDepartures, stopCamera: stopCamera, loadRevenue: loadRevenue, loadAnalytics: loadAnalytics, loadPassengers: loadPassengers, loadFleet: loadFleet, loadDrivers: loadDrivers, loadMaintenance: loadMaintenance, searchTickets: searchTickets, loadSettings: loadSettings, loadStaff: loadStaff, openRouteModal: openRouteModal, openDepartureModal: openDepartureModal, wizardStep: wizardStep };
})();
</script>



</body>
</html>
