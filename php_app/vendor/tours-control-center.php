<?php
/**
 * UTHENGA TOURS SERVICE CONTROL CENTER
 * Full 20-module Tour Management Operating System
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (!isLoggedIn()) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['user_id']   = 'demo_tour_operator';
    $_SESSION['user_name'] = 'Christopher Ngoma';
    $_SESSION['user_role'] = 'vendor';
}

$pageTitle = 'Tours Control Center | Uthenga';
$userName  = (string)($_SESSION['user_name'] ?? 'Christopher Ngoma');
$firstName = explode(' ', $userName)[0];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e(uthenga_theme_preference()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="Uthenga Tours Service Control Center — complete tour management operating system">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/tours-control-center.css?v=<?= rawurlencode(APP_VERSION) ?>">
  <script>!function(){var t=document.documentElement,s=null;try{s=localStorage.getItem('uthenga-theme')}catch(e){}if(!s){var c=document.cookie.split('; ').find(function(r){return r.startsWith('uthenga-theme=')});if(c)s=decodeURIComponent(c.split('=').slice(1).join('='))}if(s==='dark'||s==='light')t.dataset.theme=s}();</script>
</head>
<body class="tcc-page">
<div class="tcc-shell">

<!-- ═══════════════════════════════ TOP NAV BAR ═══════════════════════════════ -->
<header class="tcc-top-bar">
  <div class="tcc-top-left">
    <a href="<?= BASE_URL ?>vendor/dashboard.php" class="tcc-brand-top" title="Go to Vendor Dashboard">
      <?php $logoSize = 'sm'; $logoLink = false; require __DIR__ . '/../includes/logo.php'; ?>
      <div class="tcc-brand-top-text"><strong>UTHENGA</strong><small>Tours Control Center</small></div>
    </a>
    <div class="tcc-org-select-top">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      <span>Axon Tours &amp; Safaris</span>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>
    <div class="tcc-search-box">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input class="tcc-search-input" id="tcc-global-search" type="text" placeholder="Search tours, bookings, guides, customers…">
      <span class="tcc-search-kbd">Ctrl+K</span>
    </div>
  </div>

  <div class="tcc-header-right">
    <!-- Return to Main Dashboard Button -->
    <a href="<?= BASE_URL ?>vendor/dashboard.php" class="tcc-btn tcc-btn-primary" style="text-decoration:none;font-size:0.75rem;padding:0.4rem 0.85rem;display:inline-flex;align-items:center;gap:0.4rem;font-weight:700;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Return to Main Dashboard
    </a>

    <button type="button" class="tcc-icon-btn theme-toggle" data-theme-toggle title="Toggle light and dark mode" style="font-size:0.75rem;">
      <span class="theme-toggle-icon" aria-hidden="true">☀️</span>
    </button>
    <div class="tcc-date-pill">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
      <span id="tcc-live-date">Loading…</span>
    </div>
    <button type="button" class="tcc-icon-btn" title="Alerts" onclick="switchTccModule('alerts-panel')">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span class="tcc-btn-badge">5</span>
    </button>
    <button type="button" class="tcc-icon-btn" title="Messages" onclick="switchTccModule('messages')">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      <span class="tcc-btn-badge" style="background:var(--tcc-primary);">12</span>
    </button>
    <div class="tcc-user-select" onclick="switchTccModule('settings')">
      <div class="tcc-profile-avatar-placeholder" style="width:30px;height:30px;border-radius:50%;font-size:0.78rem;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#dbeafe,#cffaff);color:#1d4ed8;font-weight:900;"><?= e(strtoupper(substr($firstName,0,1))) ?></div>
      <div class="tcc-user-text"><strong><?= e($userName) ?></strong><small>Tour Operator</small></div>
    </div>
  </div>
</header>

<!-- ═══════════════════════════ THREE-PANEL BODY ═══════════════════════════ -->
<div class="tcc-body">

<!-- ═══════════════════════════ LEFT SIDEBAR NAV ═══════════════════════════ -->
<aside class="tcc-sidebar">
  <div>
    <div class="tcc-nav-section-label">Operations</div>
    <div class="tcc-nav-list">
      <a class="tcc-nav-item active" data-mod="dashboard" onclick="switchTccModule('dashboard')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="listings" onclick="switchTccModule('listings')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Tour Listings</span></div>
        <span class="tcc-nav-badge">24</span>
      </a>
      <a class="tcc-nav-item" data-mod="builder" onclick="switchTccModule('builder')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg><span>Tour Builder</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="itineraries" onclick="switchTccModule('itineraries')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 6 12 12 16 14"/><circle cx="12" cy="12" r="10"/></svg><span>Itinerary Builder</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="schedules" onclick="switchTccModule('schedules')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><span>Schedules</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="pricing" onclick="switchTccModule('pricing')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Pricing Center</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="availability" onclick="switchTccModule('availability')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span>Availability</span></div>
      </a>
    </div>

    <div class="tcc-nav-section-label" style="margin-top:0.5rem;">Customers &amp; Staff</div>
    <div class="tcc-nav-list">
      <a class="tcc-nav-item" data-mod="bookings" onclick="switchTccModule('bookings')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/></svg><span>Bookings</span></div>
        <span class="tcc-nav-badge amber">17</span>
      </a>
      <a class="tcc-nav-item" data-mod="customers" onclick="switchTccModule('customers')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Customers</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="guides" onclick="switchTccModule('guides')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg><span>Guides</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="transport" onclick="switchTccModule('transport')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg><span>Fleet &amp; Transport</span></div>
      </a>
    </div>

    <div class="tcc-nav-section-label" style="margin-top:0.5rem;">Content &amp; Marketing</div>
    <div class="tcc-nav-list">
      <a class="tcc-nav-item" data-mod="destinations" onclick="switchTccModule('destinations')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span>Destinations</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="media" onclick="switchTccModule('media')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg><span>Media Gallery</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="advertisements" onclick="switchTccModule('advertisements')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><span>Ad Studio</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="reviews" onclick="switchTccModule('reviews')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Reviews</span></div>
      </a>
    </div>

    <div class="tcc-nav-section-label" style="margin-top:0.5rem;">Finance &amp; Insights</div>
    <div class="tcc-nav-list">
      <a class="tcc-nav-item" data-mod="analytics" onclick="switchTccModule('analytics')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Analytics</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="payments" onclick="switchTccModule('payments')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg><span>Payments</span></div>
      </a>
      <a class="tcc-nav-item" data-mod="messages" onclick="switchTccModule('messages')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg><span>Messages</span></div>
        <span class="tcc-nav-badge purple">12</span>
      </a>
      <a class="tcc-nav-item" data-mod="documents" onclick="switchTccModule('documents')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span>Documents</span></div>
        <span class="tcc-nav-badge rose">2</span>
      </a>
      <a class="tcc-nav-item" data-mod="settings" onclick="switchTccModule('settings')">
        <div class="tcc-nav-left"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span>Settings</span></div>
      </a>
    </div>
  </div>

  <div class="tcc-plan-card">
    <label>Business Plan</label>
    <strong>Premium Plan</strong>
    <small>Renews Aug 12, 2025</small>
    <button type="button" class="tcc-btn tcc-btn-primary" style="width:100%;font-size:0.7rem;justify-content:center;" onclick="tccNotify('Opening Plan Manager…')">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      Upgrade Plan
    </button>
  </div>
</aside>

<!-- ═══════════════════════════ CENTER WORKSPACE ═══════════════════════════ -->
<main class="tcc-workspace">

<!-- ════════════════════ MODULE 1: DASHBOARD ════════════════════ -->
<div id="mod-dashboard" class="tcc-module-content active">

  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.1rem;">
    <div class="tcc-hero-greeting">
      <h1>Good morning, <?= e($firstName) ?>! ☀️</h1>
      <p>Here's everything happening with your tours today — <?= date('l, F j, Y') ?>.</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
      <button type="button" class="tcc-btn tcc-btn-secondary" onclick="switchTccModule('schedules')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg> Schedule</button>
      <button type="button" class="tcc-btn tcc-btn-primary" onclick="switchTccModule('builder')">+ New Tour</button>
    </div>
  </div>

  <!-- 13 KPI Cards -->
  <div class="tcc-stats-row" style="grid-template-columns:repeat(auto-fill,minmax(155px,1fr));">
    <div class="tcc-stat-card" onclick="switchTccModule('listings')">
      <div class="tcc-stat-info"><label>Active Tours</label><h2>24</h2><div class="tcc-stat-sub" style="color:var(--tcc-text-dim);">16 Published · 8 Draft</div></div>
      <div class="tcc-stat-icon-wrap blue"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('schedules')">
      <div class="tcc-stat-info"><label>Upcoming Departures</label><h2>8</h2><div class="tcc-stat-sub" style="color:var(--tcc-green);">Next: 10:00 AM today</div></div>
      <div class="tcc-stat-icon-wrap green"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('schedules')">
      <div class="tcc-stat-info"><label>Running Today</label><h2>3</h2><div class="tcc-stat-sub" style="color:var(--tcc-primary);">All on schedule</div></div>
      <div class="tcc-stat-icon-wrap cyan"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 6 12 12 16 14"/><circle cx="12" cy="12" r="10"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('bookings')">
      <div class="tcc-stat-info"><label>Pending Bookings</label><h2>17</h2><div class="tcc-stat-sub" style="color:var(--tcc-amber);">Awaiting confirm</div></div>
      <div class="tcc-stat-icon-wrap amber"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('bookings')">
      <div class="tcc-stat-info"><label>Confirmed</label><h2>186</h2><div class="tcc-stat-sub" style="color:var(--tcc-green);">+23 this week</div></div>
      <div class="tcc-stat-icon-wrap green"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('customers')">
      <div class="tcc-stat-info"><label>Today's Travelers</label><h2>152</h2><div class="tcc-stat-sub" style="color:var(--tcc-purple);">Across 3 tours</div></div>
      <div class="tcc-stat-icon-wrap purple"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('payments')">
      <div class="tcc-stat-info"><label>Monthly Revenue</label><h2 style="font-size:1.1rem;">MK 4.85M</h2><div class="tcc-stat-sub" style="color:var(--tcc-green);">↑ 18% vs last month</div></div>
      <div class="tcc-stat-icon-wrap teal"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('payments')">
      <div class="tcc-stat-info"><label>Monthly Profit</label><h2 style="font-size:1.1rem;">MK 1.62M</h2><div class="tcc-stat-sub" style="color:var(--tcc-green);">33.4% margin</div></div>
      <div class="tcc-stat-icon-wrap green"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('availability')">
      <div class="tcc-stat-info"><label>Avg Occupancy</label><h2>84%</h2><div class="tcc-stat-sub" style="color:var(--tcc-primary);">Target: 80%</div></div>
      <div class="tcc-stat-icon-wrap blue"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('reviews')">
      <div class="tcc-stat-info"><label>Avg Rating</label><h2>4.8 ★</h2><div class="tcc-stat-sub" style="color:var(--tcc-amber);">From 1,240 reviews</div></div>
      <div class="tcc-stat-icon-wrap amber"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('guides')">
      <div class="tcc-stat-info"><label>Active Guides</label><h2>12</h2><div class="tcc-stat-sub" style="color:var(--tcc-text-dim);">8 on duty today</div></div>
      <div class="tcc-stat-icon-wrap orange"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('advertisements')">
      <div class="tcc-stat-info"><label>Ad Performance</label><h2>8.4%</h2><div class="tcc-stat-sub" style="color:var(--tcc-green);">CTR · 42K impressions</div></div>
      <div class="tcc-stat-icon-wrap pink"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
    </div>
    <div class="tcc-stat-card" onclick="switchTccModule('transport')">
      <div class="tcc-stat-info"><label>Fleet Status</label><h2>7/9</h2><div class="tcc-stat-sub" style="color:var(--tcc-amber);">1 maintenance, 1 standby</div></div>
      <div class="tcc-stat-icon-wrap cyan"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
    </div>
  </div>

  <!-- Row 2: Today's Timeline + Live Operations -->
  <div class="tcc-two-col" style="margin-bottom:1.1rem;">
    <!-- Today's Operations Timeline -->
    <div class="tcc-card">
      <div class="tcc-card-head">
        <h3>📅 Today's Departure Timeline</h3>
        <a href="#" onclick="switchTccModule('schedules')" class="tcc-card-link">View Full Calendar →</a>
      </div>
      <div class="tcc-timeline-list">
        <?php
        $departures = [
          ['time'=>'08:00 AM','tour'=>'Lake Malawi 3-Day Explorer','from'=>'Monkey Bay','guests'=>18,'guide'=>'John Kamanga','vehicle'=>'Safari Cruiser #1','status'=>'live','color'=>'green'],
          ['time'=>'10:00 AM','tour'=>'Liwonde Wildlife Safari','from'=>'Makanjira Gate','guests'=>24,'guide'=>'Patrick Banda','vehicle'=>'Land Rover #2','status'=>'live','color'=>'green'],
          ['time'=>'01:00 PM','tour'=>'Zomba Plateau Hike','from'=>'Zomba Town','guests'=>12,'guide'=>'Agnes Phiri','vehicle'=>'Coaster Bus #3','status'=>'upcoming','color'=>'amber'],
          ['time'=>'04:00 PM','tour'=>'City Heritage Walk – Blantyre','from'=>'Blantyre City Centre','guests'=>15,'guide'=>'Davie Nkhata','vehicle'=>'Minibus #4','status'=>'scheduled','color'=>'blue'],
          ['time'=>'06:00 PM','tour'=>'Cape Maclear Sunset Cruise','from'=>'Cape Maclear Pier','guests'=>8,'guide'=>'Estelle Chirwa','vehicle'=>'Boat MV-12','status'=>'scheduled','color'=>'blue'],
        ];
        foreach ($departures as $dep): ?>
        <div class="tcc-timeline-item" style="cursor:pointer;" onclick="tccExpandDeparture(this)" title="Click to expand">
          <div class="tcc-timeline-node <?= $dep['status']==='live'?'live':($dep['status']==='upcoming'?'upcoming':'') ?>"></div>
          <div style="flex:1;">
            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
              <strong style="font-size:0.8rem;"><?= e($dep['time']) ?> — <?= e($dep['tour']) ?></strong>
              <?php if($dep['status']==='live'): ?><span class="tcc-pill green"><span class="tcc-pill-dot"></span>LIVE</span><?php elseif($dep['status']==='upcoming'): ?><span class="tcc-pill amber">SOON</span><?php endif; ?>
            </div>
            <small style="color:var(--tcc-text-dim);font-size:0.68rem;">From <?= e($dep['from']) ?></small>
            <div class="tcc-timeline-detail" style="display:none;margin-top:0.5rem;display:none;">
              <div style="display:grid;grid-template-columns:repeat(4,auto);gap:0.5rem 1.25rem;font-size:0.7rem;background:var(--tcc-surface-2);border-radius:6px;padding:0.6rem 0.75rem;margin-top:0.35rem;">
                <span>👤 Guide: <strong><?= e($dep['guide']) ?></strong></span>
                <span>🚌 Vehicle: <strong><?= e($dep['vehicle']) ?></strong></span>
                <span>🧳 Guests: <strong><?= e($dep['guests']) ?></strong></span>
                <span>✅ Status: <strong style="color:var(--tcc-<?= $dep['color'] ?>);"><?= ucfirst($dep['status']) ?></strong></span>
              </div>
            </div>
          </div>
          <span class="tcc-pill <?= e($dep['color']) ?>"><?= e($dep['guests']) ?> Guests</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Live Operations Cards -->
    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
        <h3 style="font-size:0.88rem;font-weight:800;">🔴 Live Tour Operations</h3>
        <a href="#" onclick="switchTccModule('listings')" class="tcc-card-link" style="font-size:0.72rem;">All Tours →</a>
      </div>
      <div class="tcc-live-ops-grid" style="grid-template-columns:1fr;">
        <div class="tcc-live-op-card" style="margin-bottom:0.75rem;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.6rem;">
            <div>
              <span class="tcc-pill green" style="font-size:0.58rem;"><span class="tcc-pill-dot"></span>LIVE NOW</span>
              <h4 style="font-size:0.92rem;font-weight:900;margin:0.3rem 0 0.1rem;">Lake Malawi 3-Day Explorer</h4>
              <small style="color:#94a3b8;font-size:0.68rem;">📍 Current Stop: Cape Maclear Beach · Guide: John Kamanga</small>
            </div>
            <strong style="color:#10b981;font-size:0.88rem;">18/25</strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.65rem;color:#94a3b8;margin-bottom:0.3rem;">
            <span>Progress</span><span>65% Complete</span>
          </div>
          <div class="tcc-progress-bar-wrap" style="margin-bottom:0.6rem;"><div class="tcc-progress-fill" style="width:65%;background:linear-gradient(90deg,#10b981,#06b6d4);"></div></div>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;font-size:0.65rem;color:#94a3b8;">
            <span>🌤️ 26°C Partly Cloudy</span><span>·</span><span>⏱️ Next: Snorkeling 2:00 PM</span><span>·</span><span>🏁 Est. Completion: Day 3 5:00 PM</span>
          </div>
        </div>
        <div class="tcc-live-op-card" style="margin-bottom:0.75rem;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.6rem;">
            <div>
              <span class="tcc-pill green" style="font-size:0.58rem;"><span class="tcc-pill-dot"></span>LIVE NOW</span>
              <h4 style="font-size:0.92rem;font-weight:900;margin:0.3rem 0 0.1rem;">Liwonde Wildlife Safari</h4>
              <small style="color:#94a3b8;font-size:0.68rem;">📍 Liwonde National Park · Guide: Patrick Banda</small>
            </div>
            <strong style="color:#10b981;font-size:0.88rem;">24/30</strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.65rem;color:#94a3b8;margin-bottom:0.3rem;">
            <span>Progress</span><span>40% Complete</span>
          </div>
          <div class="tcc-progress-bar-wrap" style="margin-bottom:0.6rem;"><div class="tcc-progress-fill" style="width:40%;background:linear-gradient(90deg,#f59e0b,#f97316);"></div></div>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;font-size:0.65rem;color:#94a3b8;">
            <span>🌤️ 28°C Sunny</span><span>·</span><span>⏱️ Next: Hippo Sunset Cruise 4:00 PM</span><span>·</span><span>🏁 Est. Completion: Tomorrow 10:00 AM</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Row 3: Revenue + Bookings Overview + Top Destinations -->
  <div class="tcc-three-col" style="margin-bottom:1.1rem;">
    <div class="tcc-card">
      <div class="tcc-card-head">
        <h3>Revenue Snapshot</h3>
        <select class="tcc-input tcc-select" style="width:auto;padding:0.25rem 1.8rem 0.25rem 0.5rem;font-size:0.7rem;font-weight:700;border:none;background:transparent;">
          <option>This Month</option><option>This Year</option><option>Weekly</option>
        </select>
      </div>
      <div style="height:130px;position:relative;">
        <svg style="width:100%;height:100%;" viewBox="0 0 300 120" preserveAspectRatio="none">
          <defs>
            <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#2563eb" stop-opacity="0.3"/><stop offset="100%" stop-color="#2563eb" stop-opacity="0"/></linearGradient>
            <linearGradient id="profGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#10b981" stop-opacity="0.2"/><stop offset="100%" stop-color="#10b981" stop-opacity="0"/></linearGradient>
          </defs>
          <path d="M0,100 L30,88 L60,92 L90,60 L120,65 L150,40 L180,48 L210,22 L240,30 L270,12 L300,18 L300,120 L0,120Z" fill="url(#revGrad)"/>
          <path d="M0,100 L30,88 L60,92 L90,60 L120,65 L150,40 L180,48 L210,22 L240,30 L270,12 L300,18" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linejoin="round"/>
          <path d="M0,108 L30,100 L60,104 L90,82 L120,85 L150,68 L180,72 L210,55 L240,60 L270,42 L300,45 L300,120 L0,120Z" fill="url(#profGrad)"/>
          <path d="M0,108 L30,100 L60,104 L90,82 L120,85 L150,68 L180,72 L210,55 L240,60 L270,42 L300,45" fill="none" stroke="#10b981" stroke-width="1.8" stroke-linejoin="round" stroke-dasharray="4,3"/>
          <circle cx="270" cy="12" r="4" fill="#2563eb"/>
        </svg>
      </div>
      <div style="display:flex;gap:1rem;margin-top:0.4rem;font-size:0.65rem;">
        <span style="display:flex;align-items:center;gap:0.3rem;"><span style="width:10px;height:2px;background:#2563eb;display:inline-block;"></span>Revenue</span>
        <span style="display:flex;align-items:center;gap:0.3rem;"><span style="width:10px;height:2px;background:#10b981;display:inline-block;border-top:1px dashed #10b981;"></span>Profit</span>
      </div>
    </div>

    <div class="tcc-card">
      <div class="tcc-card-head"><h3>Bookings Overview</h3></div>
      <div class="tcc-donut-wrap">
        <div style="width:90px;height:90px;position:relative;flex-shrink:0;">
          <svg viewBox="0 0 36 36" style="width:100%;height:100%;transform:rotate(-90deg);">
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#e2e8f0" stroke-width="3.8"/>
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#2563eb" stroke-width="3.8" stroke-dasharray="65 100"/>
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#f59e0b" stroke-width="3.8" stroke-dasharray="20 100" stroke-dashoffset="-65"/>
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#f43f5e" stroke-width="3.8" stroke-dasharray="8 100" stroke-dashoffset="-85"/>
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#10b981" stroke-width="3.8" stroke-dasharray="7 100" stroke-dashoffset="-93"/>
          </svg>
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:900;font-size:0.82rem;">286<small style="font-size:0.52rem;color:var(--tcc-text-dim);">Total</small></div>
        </div>
        <div class="tcc-donut-legend">
          <div class="tcc-donut-legend-item"><div class="tcc-donut-dot" style="background:#2563eb;"></div><span>Confirmed</span><strong style="margin-left:auto;font-size:0.78rem;">186 (65%)</strong></div>
          <div class="tcc-donut-legend-item"><div class="tcc-donut-dot" style="background:#f59e0b;"></div><span>Pending</span><strong style="margin-left:auto;font-size:0.78rem;">58 (20%)</strong></div>
          <div class="tcc-donut-legend-item"><div class="tcc-donut-dot" style="background:#f43f5e;"></div><span>Cancelled</span><strong style="margin-left:auto;font-size:0.78rem;">24 (8%)</strong></div>
          <div class="tcc-donut-legend-item"><div class="tcc-donut-dot" style="background:#10b981;"></div><span>Completed</span><strong style="margin-left:auto;font-size:0.78rem;">18 (7%)</strong></div>
        </div>
      </div>
    </div>

    <div class="tcc-card">
      <div class="tcc-card-head"><h3>Top Destinations</h3><a href="#" onclick="switchTccModule('destinations')" class="tcc-card-link">All →</a></div>
      <div style="display:flex;flex-direction:column;gap:0.6rem;font-size:0.75rem;">
        <?php $dests=[['Lake Malawi','128','45','var(--tcc-primary)'],['Liwonde NP','82','29','var(--tcc-green)'],['Zomba Plateau','46','16','var(--tcc-purple)'],['Mulanje Mountain','30','10','var(--tcc-amber)']]; foreach($dests as $d): ?>
        <div><div style="display:flex;justify-content:space-between;margin-bottom:0.18rem;"><span><?= e($d[0]) ?></span><strong><?= e($d[1]) ?> bookings</strong></div><div class="tcc-progress-bar-wrap"><div class="tcc-progress-fill" style="width:<?= e($d[2]) ?>%;background:<?= $d[3] ?>;"></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Row 4: AI Insights + Alerts -->
  <div class="tcc-two-col" style="margin-bottom:1.1rem;">
    <div class="tcc-card">
      <div class="tcc-card-head"><h3>🤖 AI Operations Insights</h3><span class="tcc-pill purple" style="font-size:0.58rem;">LIVE</span></div>
      <div style="display:flex;flex-direction:column;gap:0.6rem;">
        <div class="tcc-ai-card">
          <p><strong>Liwonde Safari Tour is nearly full.</strong><br>Only 6 seats remain for May 16. Consider increasing price by 8% — predicted demand stays strong.</p>
          <div style="display:flex;gap:0.4rem;"><button class="tcc-btn tcc-btn-primary tcc-btn-sm" onclick="switchTccModule('pricing')">Adjust Price</button><button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="switchTccModule('listings')">View Tour</button></div>
        </div>
        <div class="tcc-ai-card">
          <p><strong>Weekend bookings trending ↑ 31%.</strong><br>Open another Saturday departure for Lake Malawi Explorer — estimated 20 bookings within 48h.</p>
          <div style="display:flex;gap:0.4rem;"><button class="tcc-btn tcc-btn-primary tcc-btn-sm" onclick="switchTccModule('schedules')">Add Departure</button></div>
        </div>
        <div class="tcc-ai-card">
          <p><strong>Heavy rain forecast for Zomba — May 15.</strong><br>12 travelers on tomorrow's Plateau Hike. Recommend sending a weather advisory now.</p>
          <div style="display:flex;gap:0.4rem;"><button class="tcc-btn tcc-btn-success tcc-btn-sm" onclick="tccNotify('Advisory sent to 12 travelers!')">Send Advisory</button></div>
        </div>
      </div>
    </div>

    <div class="tcc-card">
      <div class="tcc-card-head"><h3>⚠️ Alerts &amp; Notifications</h3><button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="tccNotify('All alerts cleared!')">Clear All</button></div>
      <div class="tcc-alerts-list">
        <div class="tcc-alert-item danger"><div class="tcc-alert-icon danger"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><div style="flex:1;"><strong style="font-size:0.75rem;">Guide Absent — John Kamanga</strong><small style="font-size:0.65rem;color:var(--tcc-text-dim);">May 16 Lake Malawi tour requires reassignment</small></div><button class="tcc-btn tcc-btn-sm tcc-btn-danger" onclick="switchTccModule('guides')">Fix</button></div>
        <div class="tcc-alert-item warn"><div class="tcc-alert-icon warn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div><div style="flex:1;"><strong style="font-size:0.75rem;">Vehicle Maintenance Due</strong><small style="font-size:0.65rem;color:var(--tcc-text-dim);">Safari Cruiser #1 — 800km service overdue</small></div><button class="tcc-btn tcc-btn-sm tcc-btn-secondary" onclick="switchTccModule('transport')">View</button></div>
        <div class="tcc-alert-item warn"><div class="tcc-alert-icon warn"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></div><div style="flex:1;"><strong style="font-size:0.75rem;">Tour License Expiring</strong><small style="font-size:0.65rem;color:var(--tcc-text-dim);">Tourism Operator License #TO-9941 expires in 45 days</small></div><button class="tcc-btn tcc-btn-sm tcc-btn-secondary" onclick="switchTccModule('documents')">Renew</button></div>
        <div class="tcc-alert-item info"><div class="tcc-alert-icon info"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><div style="flex:1;"><strong style="font-size:0.75rem;">Ad Campaign Expiring</strong><small style="font-size:0.65rem;color:var(--tcc-text-dim);">Lake Malawi Summer Escape ad ends in 3 days</small></div><button class="tcc-btn tcc-btn-sm tcc-btn-secondary" onclick="switchTccModule('advertisements')">Extend</button></div>
        <div class="tcc-alert-item info"><div class="tcc-alert-icon info"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div><div style="flex:1;"><strong style="font-size:0.75rem;">Zomba Plateau Nearly Full</strong><small style="font-size:0.65rem;color:var(--tcc-text-dim);">May 17 departure — 2 seats remaining of 14</small></div><button class="tcc-btn tcc-btn-sm tcc-btn-secondary" onclick="switchTccModule('availability')">Manage</button></div>
      </div>
    </div>
  </div>

  <!-- Row 5: Recent Bookings Table -->
  <div class="tcc-card">
    <div class="tcc-card-head"><h3>Recent Bookings</h3><a href="#" onclick="switchTccModule('bookings')" class="tcc-card-link">View All Bookings →</a></div>
    <table class="tcc-table">
      <thead><tr><th>Booking ID</th><th>Customer</th><th>Tour</th><th>Departure</th><th>Guests</th><th>Amount</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead>
      <tbody>
        <?php $bks=[['#BK-4587','Grace Mwale','Lake Malawi Explorer','May 14 10:00 AM','2 Adults','MK 350,000','Confirmed','Paid','green'],['#BK-4586','James Phiri','Liwonde Safari Tour','May 14 10:00 AM','1 Adult, 1 Child','MK 420,000','Confirmed','Paid','green'],['#BK-4585','Sarah Banda','Zomba Plateau Hike','May 14 01:00 PM','3 Adults','MK 270,000','Pending','Partial','amber'],['#BK-4584','Kelvin Mbewe','City Heritage Walk','May 14 04:00 PM','2 Adults','MK 140,000','Confirmed','Paid','green'],['#BK-4583','Thandiwe Chirwa','Sunset Boat Cruise','May 14 06:00 PM','2 Adults','MK 120,000','Pending','Online','amber']]; foreach($bks as $b): ?>
        <tr onclick="switchTccModule('bookings')" style="cursor:pointer;">
          <td><strong><?= e($b[0]) ?></strong></td>
          <td><div style="display:flex;align-items:center;gap:0.4rem;"><div style="width:24px;height:24px;border-radius:50%;background:var(--tcc-primary-light);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.62rem;color:var(--tcc-primary);"><?= e(strtoupper(substr($b[1],0,1))) ?></div><?= e($b[1]) ?></div></td>
          <td><?= e($b[2]) ?></td><td><?= e($b[3]) ?></td><td><?= e($b[4]) ?></td>
          <td><strong><?= e($b[5]) ?></strong></td>
          <td><span class="tcc-pill <?= e($b[8]) ?>"><?= e($b[6]) ?></span></td>
          <td><span class="tcc-pill <?= e($b[8]) ?>"><?= e($b[7]) ?></span></td>
          <td><div style="display:flex;gap:0.3rem;"><button class="tcc-btn tcc-btn-xs tcc-btn-primary" onclick="event.stopPropagation();tccNotify('Opening booking…')">View</button><button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="event.stopPropagation();tccNotify('Contact sent!')">Contact</button></div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div><!-- End Dashboard -->

<!-- ════════════════════ MODULE 2: TOUR LISTINGS ════════════════════ -->
<div id="mod-listings" class="tcc-module-content">
  <div class="tcc-mod-header">
    <div><h2>Tour Catalogue</h2><p style="font-size:0.78rem;color:var(--tcc-text-dim);margin-top:0.2rem;">24 tours · 16 published · 8 drafts</p></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('Export complete!')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export</button>
      <button class="tcc-btn tcc-btn-primary" onclick="switchTccModule('builder')">+ Create New Tour</button>
    </div>
  </div>

  <div class="tcc-toolbar">
    <div class="tcc-toolbar-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Search tours by name, destination, category…"></div>
    <div class="tcc-toolbar-sep"></div>
    <button class="tcc-filter-btn active">All (24)</button>
    <button class="tcc-filter-btn">Published (16)</button>
    <button class="tcc-filter-btn">Drafts (8)</button>
    <button class="tcc-filter-btn">Archived</button>
    <div class="tcc-toolbar-sep"></div>
    <select class="tcc-input tcc-select" style="width:auto;font-size:0.75rem;padding:0.35rem 1.8rem 0.35rem 0.6rem;">
      <option>All Categories</option><option>Safari</option><option>Cultural</option><option>Adventure</option><option>Beach</option><option>Mountain</option>
    </select>
    <div class="tcc-toolbar-sep"></div>
    <div class="tcc-view-toggle">
      <button class="tcc-view-btn active" title="Grid view"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></button>
      <button class="tcc-view-btn" title="List view"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></button>
    </div>
  </div>

  <div class="tcc-tour-grid">
    <?php
    $tours = [
      ['Lake Malawi 3-Day Explorer','https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=600&q=80','3 Days','Moderate','Beach & Lake','MK 350,000','16','Published','4.9','1,240','128','MK 44.8M','green'],
      ['Liwonde Wildlife Safari','https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=600&q=80','2 Days','Easy','Wildlife','MK 420,000','24','Published','4.8','820','82','MK 34.4M','green'],
      ['Zomba Plateau Hike','https://images.unsplash.com/photo-1578662996442-48f60103fc96?auto=format&fit=crop&w=600&q=80','1 Day','Strenuous','Adventure','MK 85,000','12','Published','4.7','540','46','MK 3.9M','green'],
      ['Mulanje Mountain Trek','https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=600&q=80','4 Days','Expert','Mountain','MK 650,000','8','Published','4.9','320','30','MK 19.5M','green'],
      ['City Heritage Walk – Blantyre','https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=600&q=80','Half Day','Easy','Cultural','MK 45,000','20','Published','4.6','210','45','MK 2.0M','green'],
      ['Sunset Boat Cruise – Cape Maclear','https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=600&q=80','3 Hours','Easy','Beach','MK 65,000','15','Published','4.8','180','38','MK 2.5M','green'],
      ['Northern Malawi Explorer','https://images.unsplash.com/photo-1523805009345-7448845a9e53?auto=format&fit=crop&w=600&q=80','7 Days','Moderate','Cultural','MK 1,200,000','10','Draft','—','0','0','—','amber'],
      ['Livingstone Trail','https://images.unsplash.com/photo-1518544866330-4e716499f800?auto=format&fit=crop&w=600&q=80','5 Days','Moderate','History','MK 890,000','12','Draft','—','0','0','—','amber'],
    ];
    foreach ($tours as $t): ?>
    <div class="tcc-tour-card">
      <div class="tcc-tour-card-img">
        <img src="<?= e($t[1]) ?>" alt="<?= e($t[0]) ?>" loading="lazy">
        <div class="tcc-tour-card-badges">
          <span class="tcc-pill <?= e($t[12]) ?>"><?= e($t[7]) ?></span>
          <?php if($t[7]==='Published'): ?><span class="tcc-pill blue"><?= e($t[2]) ?></span><?php endif; ?>
        </div>
        <div class="tcc-tour-card-actions-overlay">
          <button class="tcc-btn tcc-btn-sm tcc-btn-secondary tcc-btn-icon" onclick="tccNotify('Previewing tour…')" title="Preview"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
          <button class="tcc-btn tcc-btn-sm tcc-btn-secondary tcc-btn-icon" onclick="switchTccModule('advertisements')" title="Advertise"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
        </div>
      </div>
      <div class="tcc-tour-card-body">
        <div class="tcc-tour-card-meta">
          <span>📍 <?= e($t[5]) ?></span>
          <span>· <?= e($t[3]) ?></span>
          <span>· <?= e($t[4]) ?></span>
        </div>
        <div class="tcc-tour-card-title"><?= e($t[0]) ?></div>
        <div style="display:flex;align-items:center;justify-content:space-between;">
          <div class="tcc-tour-card-price"><?= e($t[5]) ?></div>
          <div class="tcc-stars"><?= $t[8]!=='—'?'★ '.$t[8]:'Draft' ?></div>
        </div>
        <div class="tcc-tour-card-stats">
          <div class="tcc-tour-card-stat"><label>Views</label><strong><?= e($t[9]) ?></strong></div>
          <div class="tcc-tour-card-stat"><label>Bookings</label><strong><?= e($t[10]) ?></strong></div>
          <div class="tcc-tour-card-stat"><label>Revenue</label><strong style="font-size:0.68rem;"><?= e($t[11]) ?></strong></div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.68rem;color:var(--tcc-text-dim);margin-bottom:0.6rem;">
          <span>💺 <?= e($t[6]) ?> seats</span>
        </div>
        <div class="tcc-tour-card-footer">
          <button class="tcc-btn tcc-btn-primary tcc-btn-sm" onclick="switchTccModule('builder')">✏️ Edit</button>
          <button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="tccNotify('Tour duplicated!')">⧉ Dupe</button>
          <button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="switchTccModule('analytics')">📊 Stats</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div><!-- End Listings -->

<!-- ════════════════════ MODULE 3: TOUR BUILDER ════════════════════ -->
<div id="mod-builder" class="tcc-module-content">
  <div class="tcc-mod-header">
    <div><h2>Tour Builder</h2><p style="font-size:0.78rem;color:var(--tcc-text-dim);margin-top:0.2rem;">Create &amp; configure a complete tour product</p></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('Draft saved!')">Save Draft</button>
      <button class="tcc-btn tcc-btn-success" onclick="tccNotify('Tour published!')">✓ Publish Tour</button>
    </div>
  </div>

  <div style="max-width:860px;">
    <!-- Section 1: Basic Information -->
    <div class="tcc-accordion-item open">
      <div class="tcc-accordion-head" onclick="tccToggleAccordion(this)">
        <span>📋 1. Basic Information</span>
        <svg class="tcc-acc-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="tcc-accordion-body">
        <div class="tcc-form-row cols-2">
          <div><label class="tcc-label">Tour Name *</label><input class="tcc-input" placeholder="e.g. Lake Malawi 3-Day Explorer" value="Lake Malawi 3-Day Explorer"></div>
          <div><label class="tcc-label">Subtitle / Tagline</label><input class="tcc-input" placeholder="Short promotional description" value="The Ultimate Freshwater Lake Experience"></div>
        </div>
        <div class="tcc-form-row cols-3">
          <div><label class="tcc-label">Category</label><select class="tcc-input tcc-select"><option selected>Beach &amp; Lake</option><option>Wildlife Safari</option><option>Mountain &amp; Hiking</option><option>Cultural &amp; Heritage</option><option>Adventure</option><option>Family</option></select></div>
          <div><label class="tcc-label">Duration</label><select class="tcc-input tcc-select"><option>Half Day</option><option>1 Day</option><option>2 Days</option><option selected>3 Days</option><option>4 Days</option><option>7 Days</option></select></div>
          <div><label class="tcc-label">Difficulty Level</label><select class="tcc-input tcc-select"><option>Easy</option><option selected>Moderate</option><option>Strenuous</option><option>Expert</option></select></div>
        </div>
        <div class="tcc-form-row cols-3">
          <div><label class="tcc-label">Capacity (Max Guests)</label><input class="tcc-input" type="number" value="25"></div>
          <div><label class="tcc-label">Min Guests</label><input class="tcc-input" type="number" value="4"></div>
          <div><label class="tcc-label">Guide Language(s)</label><input class="tcc-input" value="English, Chichewa"></div>
        </div>
        <div class="tcc-form-row cols-3">
          <div><label class="tcc-label">Min Age</label><input class="tcc-input" type="number" value="8"></div>
          <div><label class="tcc-label">Max Age</label><input class="tcc-input" type="number" placeholder="No limit"></div>
          <div><label class="tcc-label">Wheelchair Accessible?</label><select class="tcc-input tcc-select"><option>Partially</option><option>Yes</option><option selected>No</option></select></div>
        </div>
        <div class="tcc-form-row cols-2">
          <div><label class="tcc-label">URL Slug</label><input class="tcc-input" value="lake-malawi-3-day-explorer"></div>
          <div><label class="tcc-label">Physical Difficulty Description</label><input class="tcc-input" value="Light swimming, boat riding, some short walking on sand"></div>
        </div>
      </div>
    </div>

    <!-- Section 2: Description -->
    <div class="tcc-accordion-item">
      <div class="tcc-accordion-head" onclick="tccToggleAccordion(this)">
        <span>📝 2. Tour Description</span>
        <svg class="tcc-acc-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="tcc-accordion-body">
        <label class="tcc-label">Full Description (Rich Text)</label>
        <div style="border:1px solid var(--tcc-border);border-radius:var(--tcc-radius-sm);overflow:hidden;margin-bottom:0.9rem;">
          <div style="display:flex;gap:0.25rem;padding:0.5rem 0.75rem;background:var(--tcc-surface-2);border-bottom:1px solid var(--tcc-border);flex-wrap:wrap;">
            <?php foreach(['B','I','U','H1','H2','• List','1. List','Link','Image','Table'] as $fmt): ?>
            <button class="tcc-btn tcc-btn-secondary tcc-btn-xs" onclick="tccNotify('Formatting applied: <?= $fmt ?>')"><?= $fmt ?></button>
            <?php endforeach; ?>
          </div>
          <textarea class="tcc-input" style="border:none;border-radius:0;resize:vertical;min-height:120px;font-size:0.82rem;line-height:1.6;" placeholder="Describe the full tour experience…">Experience the crystal-clear waters of Lake Malawi, UNESCO-listed as a World Heritage Site. Swim with colorful cichlid fish, relax on pristine beaches, and watch magnificent sunsets over Africa's third-largest lake.</textarea>
        </div>
        <div class="tcc-form-row cols-2">
          <div><label class="tcc-label">Key Highlights (one per line)</label><textarea class="tcc-input" style="resize:vertical;min-height:80px;font-size:0.78rem;" placeholder="• Snorkeling with endemic cichlids&#10;• Sunset boat cruise&#10;• Beach bonfire evening">• Snorkeling with endemic cichlids
• UNESCO World Heritage beach
• Sunset boat cruise with local fishers
• Kayaking on the lake</textarea></div>
          <div><label class="tcc-label">Important Tips / Warnings</label><textarea class="tcc-input" style="resize:vertical;min-height:80px;font-size:0.78rem;" placeholder="⚠️ Bring reef-safe sunscreen&#10;⚠️ Water shoes recommended">⚠️ Bring reef-safe sunscreen only
⚠️ Water shoes strongly recommended
⚠️ Bilharzia risk — use treated water entry points</textarea></div>
        </div>
      </div>
    </div>

    <!-- Section 3: Included/Excluded Services -->
    <div class="tcc-accordion-item">
      <div class="tcc-accordion-head" onclick="tccToggleAccordion(this)">
        <span>✅ 3. Included &amp; Excluded Services</span>
        <svg class="tcc-acc-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="tcc-accordion-body">
        <label class="tcc-label" style="margin-bottom:0.5rem;">✅ Included (click to toggle)</label>
        <div class="tcc-services-grid" style="margin-bottom:1rem;">
          <?php $services=[['🏨','Accommodation'],['🍽️','Meals'],['🚌','Transport'],['👨‍🏫','Guide'],['🛡️','Insurance'],['🎫','Entry Tickets'],['⛺','Equipment'],['🧃','Refreshments'],['📸','Photography'],['🚑','Emergency Support'],['⛵','Boat Rides'],['🐠','Snorkeling Gear']]; foreach($services as $i=>$s): ?>
          <div class="tcc-service-chip <?= in_array($i,[0,1,2,3,7,8,10,11])?'selected':'' ?>" onclick="this.classList.toggle('selected')">
            <span><?= $s[0] ?></span><span><?= $s[1] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <label class="tcc-label" style="margin-bottom:0.5rem;">❌ Excluded</label>
        <div class="tcc-services-grid">
          <?php $excl=[['✈️','Flights'],['🛂','Visa Fees'],['🛍️','Shopping'],['🍺','Alcohol'],['💳','Personal Expenses'],['💰','Gratuities']]; foreach($excl as $s): ?>
          <div class="tcc-service-chip selected" style="border-color:var(--tcc-rose);background:var(--tcc-rose-light);color:#9f1239;" onclick="this.classList.toggle('selected')"><?= $s[0] ?> <?= $s[1] ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Section 4: Policies -->
    <div class="tcc-accordion-item">
      <div class="tcc-accordion-head" onclick="tccToggleAccordion(this)">
        <span>📜 4. Policies &amp; Travel Advice</span>
        <svg class="tcc-acc-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="tcc-accordion-body">
        <div class="tcc-form-row cols-2">
          <div><label class="tcc-label">Cancellation Policy</label><select class="tcc-input tcc-select"><option selected>Free cancellation up to 48h before</option><option>No refund within 24h</option><option>Non-refundable</option><option>Custom policy</option></select></div>
          <div><label class="tcc-label">Refund Policy</label><select class="tcc-input tcc-select"><option selected>Full refund > 72h, 50% > 24h</option><option>No refund</option><option>Custom</option></select></div>
        </div>
        <div class="tcc-form-row cols-2">
          <div><label class="tcc-label">Packing List</label><textarea class="tcc-input" style="resize:vertical;min-height:70px;font-size:0.75rem;">Swimwear, sunscreen, hat, light clothing, camera, water bottle</textarea></div>
          <div><label class="tcc-label">Health &amp; Medical Notes</label><textarea class="tcc-input" style="resize:vertical;min-height:70px;font-size:0.75rem;">Guests with heart conditions or recent surgery must consult a doctor before booking.</textarea></div>
        </div>
        <div class="tcc-form-row cols-3">
          <div><label class="tcc-label">Currency</label><input class="tcc-input" value="MWK (Malawian Kwacha)"></div>
          <div><label class="tcc-label">Emergency Contact</label><input class="tcc-input" value="+265 999 123 456"></div>
          <div><label class="tcc-label">Late Arrival Policy</label><select class="tcc-input tcc-select"><option selected>Forfeit — no refund</option><option>30min grace period</option></select></div>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:0.75rem;margin-top:1.25rem;">
      <button class="tcc-btn tcc-btn-primary" onclick="tccNotify('Tour saved & published!')">✓ Publish Tour</button>
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('Draft saved!')">Save Draft</button>
      <button class="tcc-btn tcc-btn-secondary" onclick="switchTccModule('itineraries')">Build Itinerary →</button>
    </div>
  </div>
</div><!-- End Builder -->

<!-- ════════════════════ MODULE 4: ITINERARY BUILDER ════════════════════ -->
<div id="mod-itineraries" class="tcc-module-content">
  <div class="tcc-mod-header">
    <div><h2>Itinerary Builder</h2><p style="font-size:0.78rem;color:var(--tcc-text-dim);">Lake Malawi 3-Day Explorer · Visual day-by-day planner</p></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('+ Day 4 added!')">+ Add Day</button>
      <button class="tcc-btn tcc-btn-success" onclick="tccNotify('Itinerary saved!')">Save Itinerary</button>
    </div>
  </div>

  <div class="tcc-day-tabs">
    <div class="tcc-day-tab active" onclick="tccSelectDay(this,'day1')">Day 1<br><small style="font-weight:400;font-size:0.6rem;">Departure &amp; Arrival</small></div>
    <div class="tcc-day-tab" onclick="tccSelectDay(this,'day2')">Day 2<br><small style="font-weight:400;font-size:0.6rem;">Lake Exploration</small></div>
    <div class="tcc-day-tab" onclick="tccSelectDay(this,'day3')">Day 3<br><small style="font-weight:400;font-size:0.6rem;">Return Journey</small></div>
    <div class="tcc-day-tab" style="border-style:dashed;" onclick="tccNotify('+ Day added!')">+ Add Day</div>
  </div>

  <div id="itinerary-day1">
    <div class="tcc-day-section-label">🌅 Morning</div>
    <div class="tcc-activity-card">
      <div class="tcc-activity-time">06:30 AM</div>
      <div class="tcc-activity-info"><strong>Hotel Pickup – Lilongwe City</strong><small>Vehicle: Safari Cruiser #1 · Guide: John Kamanga · Dress: Casual comfortable</small></div>
      <div style="display:flex;gap:0.25rem;align-items:center;"><span class="tcc-pill blue">Transport</span><button class="tcc-btn tcc-btn-icon tcc-btn-secondary" onclick="tccNotify('Edit activity…')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div>
    </div>
    <div class="tcc-activity-card">
      <div class="tcc-activity-time">08:00 AM</div>
      <div class="tcc-activity-info"><strong>Roadside Breakfast Stop – Salima</strong><small>Meal: Breakfast included · Duration: 45 min · Notes: Local restaurant with lake views</small></div>
      <div style="display:flex;gap:0.25rem;"><span class="tcc-pill amber">Meal</span><button class="tcc-btn tcc-btn-icon tcc-btn-secondary" onclick="tccNotify('Edit…')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div>
    </div>

    <div class="tcc-day-section-label">🌞 Afternoon</div>
    <div class="tcc-activity-card">
      <div class="tcc-activity-time">12:30 PM</div>
      <div class="tcc-activity-info"><strong>Arrive Cape Maclear – Check In</strong><small>Accommodation: Lake View Lodge · GPS: -14.0266, 34.8256 · Meals: Lunch on arrival</small></div>
      <div style="display:flex;gap:0.25rem;"><span class="tcc-pill purple">Hotel</span><button class="tcc-btn tcc-btn-icon tcc-btn-secondary" onclick="tccNotify('Edit…')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div>
    </div>
    <div class="tcc-activity-card">
      <div class="tcc-activity-time">02:30 PM</div>
      <div class="tcc-activity-info"><strong>Snorkeling – Chembe Village Marine Reserve</strong><small>Equipment: Included · Weather: Best conditions after 2PM · Guide briefing 30min prior</small></div>
      <div style="display:flex;gap:0.25rem;"><span class="tcc-pill cyan">Activity</span><button class="tcc-btn tcc-btn-icon tcc-btn-secondary" onclick="tccNotify('Edit…')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div>
    </div>

    <div class="tcc-day-section-label">🌙 Evening</div>
    <div class="tcc-activity-card">
      <div class="tcc-activity-time">05:00 PM</div>
      <div class="tcc-activity-info"><strong>Sunset Boat Cruise</strong><small>Meal: Sundowner snacks &amp; drinks included · Duration: 90 min · Dress: Light layers for breeze</small></div>
      <div style="display:flex;gap:0.25rem;"><span class="tcc-pill green">Cruise</span><button class="tcc-btn tcc-btn-icon tcc-btn-secondary" onclick="tccNotify('Edit…')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div>
    </div>
    <div class="tcc-activity-card">
      <div class="tcc-activity-time">07:30 PM</div>
      <div class="tcc-activity-info"><strong>Dinner &amp; Beach Bonfire – Lake View Lodge</strong><small>Meal: Traditional Malawian fish dinner · Entertainment: Local cultural performance</small></div>
      <div style="display:flex;gap:0.25rem;"><span class="tcc-pill amber">Dinner</span><button class="tcc-btn tcc-btn-icon tcc-btn-secondary" onclick="tccNotify('Edit…')"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div>
    </div>

    <button class="tcc-btn tcc-btn-secondary" style="margin-top:0.5rem;" onclick="tccNotify('+ Activity added!')">+ Add Activity</button>

    <!-- Journey Visualization -->
    <div style="margin-top:1.5rem;"><div class="tcc-section-title">Journey Route Visualization — Day 1</div></div>
    <div class="tcc-journey-viz">
      <div class="tcc-journey-stop"><div class="tcc-journey-dot start"></div><div class="tcc-journey-label">🏙️ Lilongwe Hotel Pickup</div></div>
      <div class="tcc-journey-connector"></div>
      <div class="tcc-journey-stop"><div class="tcc-journey-dot"></div><div class="tcc-journey-label">🍽️ Salima Breakfast</div></div>
      <div class="tcc-journey-connector"></div>
      <div class="tcc-journey-stop"><div class="tcc-journey-dot hotel"></div><div class="tcc-journey-label">🏨 Cape Maclear Check-in</div></div>
      <div class="tcc-journey-connector"></div>
      <div class="tcc-journey-stop"><div class="tcc-journey-dot"></div><div class="tcc-journey-label">🐠 Snorkeling Reserve</div></div>
      <div class="tcc-journey-connector"></div>
      <div class="tcc-journey-stop"><div class="tcc-journey-dot"></div><div class="tcc-journey-label">⛵ Sunset Cruise</div></div>
      <div class="tcc-journey-connector"></div>
      <div class="tcc-journey-stop"><div class="tcc-journey-dot end"></div><div class="tcc-journey-label">🔥 Beach Bonfire Dinner</div></div>
    </div>
  </div>
</div><!-- End Itineraries -->

<!-- ════════════════════ MODULE 5: SCHEDULES ════════════════════ -->
<div id="mod-schedules" class="tcc-module-content">
  <div class="tcc-mod-header">
    <h2>Departure &amp; Schedule Center</h2>
    <div style="display:flex;gap:0.5rem;">
      <div style="display:flex;background:var(--tcc-surface);border:1px solid var(--tcc-border);border-radius:var(--tcc-radius-sm);overflow:hidden;">
        <?php foreach(['Day','Week','Month','Season'] as $v): ?><button class="tcc-btn tcc-btn-secondary" style="border-radius:0;border:none;font-size:0.72rem;" onclick="tccNotify('Switched to <?= $v ?> view')"><?= $v ?></button><?php endforeach; ?>
      </div>
      <button class="tcc-btn tcc-btn-primary" onclick="tccNotify('+ New departure scheduled!')">+ Schedule Departure</button>
    </div>
  </div>

  <div class="tcc-two-col-wide">
    <!-- Calendar -->
    <div class="tcc-card">
      <div class="tcc-card-head"><h3>📅 May 2025</h3><div style="display:flex;gap:0.3rem;"><button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="tccNotify('Previous month')">‹</button><button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="tccNotify('Next month')">›</button></div></div>
      <div class="tcc-calendar-grid">
        <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?><div class="tcc-cal-header"><?= $d ?></div><?php endforeach; ?>
        <?php
        $events=[14=>['Lake Malawi (3d)','Liwonde Safari'],15=>['Zomba Hike'],16=>['Boat Cruise','Lake Malawi'],17=>['Liwonde Safari'],18=>['City Walk'],20=>['Lake Malawi (3d)'],21=>['Zomba Hike'],23=>['Liwonde Safari']];
        $startDay=3; // May 1 starts Thursday
        for($i=0;$i<$startDay;$i++) echo '<div class="tcc-cal-day other-month"><div class="tcc-cal-date">'.($i+28).'</div></div>';
        for($d=1;$d<=31;$d++):
          $today=$d===14;
        ?><div class="tcc-cal-day <?= $today?'today':'' ?>"><div class="tcc-cal-date"><?= $d ?></div><?php if(isset($events[$d])) foreach(array_slice($events[$d],0,2) as $ei=>$ev): ?><div class="tcc-cal-event <?= $ei===0?'blue':'green' ?>" onclick="tccNotify('<?= htmlspecialchars($ev) ?>')"><?= htmlspecialchars($ev) ?></div><?php endforeach; ?></div><?php endfor; ?>
      </div>
    </div>

    <!-- Departure List -->
    <div>
      <div class="tcc-section-title">Upcoming Departures (Next 14 Days)</div>
      <?php
      $deps2=[
        ['May 14','10:00 AM','Lake Malawi 3-Day Explorer','25','18','Safari Cruiser #1','John Kamanga','Open','green'],
        ['May 14','10:00 AM','Liwonde Wildlife Safari','30','24','Land Rover #2','Patrick Banda','Open','green'],
        ['May 15','01:00 PM','Zomba Plateau Hike','14','12','Coaster Bus #3','Agnes Phiri','Almost Full','amber'],
        ['May 16','10:00 AM','Lake Malawi 3-Day Explorer','25','25','Safari Cruiser #1','TBD — Reassign','Full','rose'],
        ['May 17','09:00 AM','Liwonde Wildlife Safari','30','14','Land Rover #2','Patrick Banda','Open','green'],
        ['May 18','04:00 PM','City Heritage Walk','20','8','Minibus #4','Davie Nkhata','Open','green'],
        ['May 20','10:00 AM','Lake Malawi 3-Day Explorer','25','22','Safari Cruiser #2','John Kamanga','Open','green'],
      ];
      foreach($deps2 as $dep): ?>
      <div class="tcc-card" style="margin-bottom:0.5rem;padding:0.85rem;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;">
          <div>
            <div style="display:flex;align-items:center;gap:0.5rem;"><strong style="font-size:0.82rem;"><?= e($dep[2]) ?></strong><span class="tcc-pill <?= e($dep[8]) ?>"><?= e($dep[7]) ?></span></div>
            <small style="color:var(--tcc-text-dim);font-size:0.68rem;"><?= e($dep[0]) ?> · <?= e($dep[1]) ?> · Guide: <?= e($dep[6]) ?> · Vehicle: <?= e($dep[5]) ?></small>
          </div>
          <div style="display:flex;align-items:center;gap:0.75rem;">
            <div style="text-align:center;"><strong style="display:block;font-size:0.9rem;"><?= e($dep[4]) ?>/<?= e($dep[3]) ?></strong><small style="font-size:0.62rem;color:var(--tcc-text-dim);">Booked</small></div>
            <div style="display:flex;gap:0.3rem;">
              <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Opening departure details…')">Details</button>
              <button class="tcc-btn tcc-btn-xs tcc-btn-primary" onclick="tccNotify('Editing departure…')">Edit</button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div><!-- End Schedules -->

<!-- ════════════════════ MODULE 6: PRICING CENTER ════════════════════ -->
<div id="mod-pricing" class="tcc-module-content">
  <div class="tcc-mod-header">
    <div><h2>Pricing Engine</h2><p style="font-size:0.78rem;color:var(--tcc-text-dim);">Lake Malawi 3-Day Explorer — Configure all pricing tiers</p></div>
    <button class="tcc-btn tcc-btn-primary" onclick="tccNotify('Prices saved!')">Save All Prices</button>
  </div>

  <!-- AI Price Suggestions -->
  <div class="tcc-card" style="margin-bottom:1.1rem;background:linear-gradient(135deg,rgba(37,99,235,0.05),rgba(139,92,246,0.05));border-color:rgba(37,99,235,0.2);">
    <div class="tcc-card-head"><h3>🤖 AI Price Optimization Suggestions</h3></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:0.75rem;">
      <div style="background:var(--tcc-surface);border-radius:var(--tcc-radius-sm);padding:0.75rem;border:1px solid var(--tcc-border);"><strong style="font-size:0.78rem;">Peak Weekend — Increase 8%</strong><p style="font-size:0.68rem;color:var(--tcc-text-dim);margin:0.25rem 0;">Demand is 31% above average on weekends. Current MK 350,000 is below market.</p><button class="tcc-btn tcc-btn-primary tcc-btn-sm" onclick="tccNotify('Price updated to MK 378,000!')">Apply MK 378,000</button></div>
      <div style="background:var(--tcc-surface);border-radius:var(--tcc-radius-sm);padding:0.75rem;border:1px solid var(--tcc-border);"><strong style="font-size:0.78rem;">Corporate — Add Tier</strong><p style="font-size:0.68rem;color:var(--tcc-text-dim);margin:0.25rem 0;">3 corporate inquiries this month. Adding a group corporate tier could yield +MK 800K/month.</p><button class="tcc-btn tcc-btn-success tcc-btn-sm" onclick="tccNotify('Corporate tier added!')">Add Corporate Tier</button></div>
      <div style="background:var(--tcc-surface);border-radius:var(--tcc-radius-sm);padding:0.75rem;border:1px solid var(--tcc-border);"><strong style="font-size:0.78rem;">Early Bird Discount — July</strong><p style="font-size:0.68rem;color:var(--tcc-text-dim);margin:0.25rem 0;">Activate 10% early bird for July departures to fill capacity 6 weeks in advance.</p><button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="tccNotify('Early bird promo created!')">Create Promo</button></div>
    </div>
  </div>

  <div class="tcc-pricing-grid">
    <?php
    $tiers=[
      ['Standard','MK 350,000','Adult standard rate','Year-round','featured'],
      ['Weekend','MK 378,000','Fri–Sun premium','Weekends only',''],
      ['Holiday','MK 420,000','Public holiday rate','14 days/year',''],
      ['Peak Season','MK 450,000','June–Aug high season','Jun 1–Aug 31',''],
      ['Children','MK 175,000','Under 12 years','Year-round',''],
      ['Student','MK 280,000','Valid student ID required','Year-round',''],
      ['Corporate','MK 320,000','Group 10+ people','Min 10 pax',''],
      ['Family Pack','MK 900,000','2 Adults + 2 Children','Year-round',''],
      ['Resident','MK 280,000','Malawian nationals','Year-round',''],
      ['International','MK 430,000','Non-resident adults','Year-round',''],
      ['VIP','MK 680,000','Private guide, premium boat','On request',''],
    ];
    foreach($tiers as $tier): ?>
    <div class="tcc-price-card <?= e($tier[4]) ?>">
      <div class="tcc-price-card-label"><?= e($tier[0]) ?></div>
      <div style="margin-bottom:0.3rem;">
        <span class="tcc-price-card-currency">MK</span>
        <span class="tcc-price-card-amount"><?= e(str_replace('MK ','',$tier[1])) ?></span>
      </div>
      <div class="tcc-price-card-meta"><?= e($tier[2]) ?></div>
      <div class="tcc-price-card-validity">📅 <?= e($tier[3]) ?></div>
      <div class="tcc-divider"></div>
      <div style="display:flex;gap:0.3rem;">
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Editing <?= e($tier[0]) ?> price…')">Edit</button>
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('<?= e($tier[0]) ?> tier toggled!')">Toggle</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div><!-- End Pricing -->

<!-- ════════════════════ MODULE 7: AVAILABILITY ════════════════════ -->
<div id="mod-availability" class="tcc-module-content">
  <div class="tcc-mod-header"><h2>Availability &amp; Inventory Center</h2><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('Availability updated!')">Update Inventory</button></div>

  <div class="tcc-stats-row" style="grid-template-columns:repeat(5,1fr);margin-bottom:1.1rem;">
    <div class="tcc-stat-card"><div class="tcc-stat-info"><label>Total Capacity</label><h2>580</h2></div><div class="tcc-stat-icon-wrap blue"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div></div>
    <div class="tcc-stat-card"><div class="tcc-stat-info"><label>Confirmed</label><h2>368</h2><div class="tcc-stat-sub" style="color:var(--tcc-primary);">63%</div></div><div class="tcc-stat-icon-wrap green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div></div>
    <div class="tcc-stat-card"><div class="tcc-stat-info"><label>Reserved</label><h2>54</h2><div class="tcc-stat-sub" style="color:var(--tcc-amber);">9%</div></div><div class="tcc-stat-icon-wrap amber"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
    <div class="tcc-stat-card"><div class="tcc-stat-info"><label>Available</label><h2>128</h2><div class="tcc-stat-sub" style="color:var(--tcc-green);">22% open</div></div><div class="tcc-stat-icon-wrap green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div></div>
    <div class="tcc-stat-card"><div class="tcc-stat-info"><label>Waitlisted</label><h2>30</h2><div class="tcc-stat-sub" style="color:var(--tcc-rose);">Across 3 tours</div></div><div class="tcc-stat-icon-wrap rose"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></div></div>
  </div>

  <div class="tcc-card">
    <div class="tcc-card-head"><h3>Departure Inventory Table</h3><div style="display:flex;gap:0.4rem;"><span class="tcc-pill green">Open</span><span class="tcc-pill amber">Almost Full</span><span class="tcc-pill rose">Full</span><span class="tcc-pill gray">Cancelled</span><span class="tcc-pill blue">Completed</span></div></div>
    <table class="tcc-table">
      <thead><tr><th>Departure</th><th>Tour</th><th>Capacity</th><th>Confirmed</th><th>Reserved</th><th>Remaining</th><th>Waitlist</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php $avail=[
          ['May 14 10:00AM','Lake Malawi Explorer',25,18,3,4,0,'Open','green'],
          ['May 14 10:00AM','Liwonde Safari',30,24,3,3,2,'Almost Full','amber'],
          ['May 15 01:00PM','Zomba Plateau Hike',14,12,0,2,5,'Almost Full','amber'],
          ['May 16 10:00AM','Lake Malawi Explorer',25,25,0,0,8,'Full','rose'],
          ['May 17 09:00AM','Liwonde Safari',30,14,6,10,0,'Open','green'],
          ['May 18 04:00PM','City Heritage Walk',20,8,4,8,0,'Open','green'],
          ['May 20 10:00AM','Lake Malawi Explorer',25,22,2,1,3,'Almost Full','amber'],
        ]; foreach($avail as $a): ?>
        <tr>
          <td><?= e($a[0]) ?></td><td><?= e($a[1]) ?></td>
          <td><strong><?= e($a[2]) ?></strong></td>
          <td><strong style="color:var(--tcc-primary);"><?= e($a[3]) ?></strong></td>
          <td><?= e($a[4]) ?></td>
          <td><strong style="color:<?= $a[6]===0?'var(--tcc-text-dim)':($a[6]<=3?'var(--tcc-rose)':'var(--tcc-green)') ?>;"><?= e($a[5]) ?></strong></td>
          <td><?= $a[6]>0?"<strong style='color:var(--tcc-amber);'>$a[6]</strong>":'—' ?></td>
          <td><span class="tcc-pill <?= e($a[8]) ?>"><?= e($a[7]) ?></span></td>
          <td><div style="display:flex;gap:0.25rem;"><button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Editing availability…')">Edit</button><?php if($a[6]>0): ?><button class="tcc-btn tcc-btn-xs tcc-btn-primary" onclick="tccNotify('Waitlist notified!')">Notify WL</button><?php endif; ?></div></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div><!-- End Availability -->

<!-- ════════════════════ MODULE 8: BOOKINGS ════════════════════ -->
<div id="mod-bookings" class="tcc-module-content">
  <div class="tcc-mod-header">
    <div><h2>Booking Reservations</h2><p style="font-size:0.78rem;color:var(--tcc-text-dim);">286 total · 17 pending confirmation</p></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('Exported to CSV!')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export</button>
      <button class="tcc-btn tcc-btn-primary" onclick="tccNotify('Manual booking created!')">+ Manual Booking</button>
    </div>
  </div>

  <div class="tcc-toolbar" style="margin-bottom:1rem;">
    <div class="tcc-toolbar-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Search by name, booking ID, tour…"></div>
    <button class="tcc-filter-btn active">All (286)</button>
    <button class="tcc-filter-btn">Pending (17)</button>
    <button class="tcc-filter-btn">Confirmed (186)</button>
    <button class="tcc-filter-btn">Completed (65)</button>
    <button class="tcc-filter-btn">Cancelled (18)</button>
  </div>

  <div class="tcc-card">
    <table class="tcc-table">
      <thead><tr><th></th><th>Booking ID</th><th>Customer</th><th>Tour</th><th>Departure</th><th>Guests</th><th>Amount</th><th>Status</th><th>Payment</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php
        $bookings=[
          ['#BK-4587','Grace Mwale','Lake Malawi Explorer','May 14 10:00AM','2 Adults','MK 350,000','Confirmed','Paid','May 10','green','gm@example.com','No dietary restrictions','All docs received'],
          ['#BK-4586','James Phiri','Liwonde Safari','May 14 10:00AM','1 Adult 1 Child','MK 420,000','Confirmed','Paid','May 10','green','jp@example.com','Vegetarian meal','Insurance pending'],
          ['#BK-4585','Sarah Banda','Zomba Plateau Hike','May 14 1:00PM','3 Adults','MK 270,000','Pending','Partial','May 11','amber','sb@example.com','None','Waiver pending'],
          ['#BK-4584','Kelvin Mbewe','City Heritage Walk','May 14 4:00PM','2 Adults','MK 140,000','Confirmed','Paid','May 11','green','km@example.com','None','Complete'],
          ['#BK-4583','Thandiwe Chirwa','Sunset Boat Cruise','May 14 6:00PM','2 Adults','MK 120,000','Pending','Unpaid','May 12','amber','tc@example.com','None','Awaiting payment'],
          ['#BK-4582','David Nkosi','Lake Malawi Explorer','May 16 10:00AM','4 Adults','MK 700,000','Confirmed','Paid','May 8','green','dn@example.com','Gluten-free','All docs received'],
        ];
        foreach($bookings as $i=>$b): ?>
        <tr class="expandable-row" onclick="tccToggleBooking('bk-detail-<?= $i ?>')">
          <td><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg></td>
          <td><strong><?= e($b[0]) ?></strong></td>
          <td><div style="display:flex;align-items:center;gap:0.4rem;"><div style="width:22px;height:22px;border-radius:50%;background:var(--tcc-primary-light);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.58rem;color:var(--tcc-primary);flex-shrink:0;"><?= e(strtoupper(substr($b[1],0,1))) ?></div><span style="font-size:0.78rem;"><?= e($b[1]) ?></span></div></td>
          <td><?= e($b[2]) ?></td><td><?= e($b[3]) ?></td><td><?= e($b[4]) ?></td>
          <td><strong><?= e($b[5]) ?></strong></td>
          <td><span class="tcc-pill <?= e($b[9]) ?>"><?= e($b[6]) ?></span></td>
          <td><span class="tcc-pill <?= e($b[9]) ?>"><?= e($b[7]) ?></span></td>
          <td style="font-size:0.72rem;color:var(--tcc-text-dim);"><?= e($b[8]) ?></td>
          <td onclick="event.stopPropagation()"><div style="display:flex;gap:0.25rem;">
            <?php if($b[6]==='Pending'): ?><button class="tcc-btn tcc-btn-xs tcc-btn-success" onclick="tccNotify('Booking approved!')">✓</button><button class="tcc-btn tcc-btn-xs tcc-btn-danger" onclick="tccNotify('Booking rejected')">✗</button><?php endif; ?>
            <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Message sent!')">💬</button>
          </div></td>
        </tr>
        <tr id="bk-detail-<?= $i ?>" class="tcc-booking-detail">
          <td colspan="11">
            <div class="tcc-booking-detail-grid">
              <div class="tcc-booking-detail-section"><h5>Customer Details</h5><p><?= e($b[1]) ?><br><small><?= e($b[10]) ?></small></p></div>
              <div class="tcc-booking-detail-section"><h5>Dietary / Medical</h5><p><?= e($b[11]) ?></p></div>
              <div class="tcc-booking-detail-section"><h5>Documents</h5><p><?= e($b[12]) ?></p></div>
              <div class="tcc-booking-detail-section"><h5>Actions</h5>
                <div style="display:flex;flex-wrap:wrap;gap:0.3rem;">
                  <button class="tcc-btn tcc-btn-xs tcc-btn-primary" onclick="tccNotify('Reassigning tour…')">Reassign</button>
                  <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Refund initiated!')">Refund</button>
                  <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Invoice downloaded!')">Invoice</button>
                </div>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div><!-- End Bookings -->

<!-- ════════════════════ MODULE 9: CUSTOMERS ════════════════════ -->
<div id="mod-customers" class="tcc-module-content">
  <div class="tcc-mod-header"><h2>Customer CRM</h2><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('+ New customer profile!')">+ Add Customer</button></div>

  <div class="tcc-toolbar" style="margin-bottom:1rem;">
    <div class="tcc-toolbar-search"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Search customers…"></div>
    <button class="tcc-filter-btn active">All</button><button class="tcc-filter-btn">VIP</button><button class="tcc-filter-btn">Repeat Travelers</button><button class="tcc-filter-btn">First-Time</button>
  </div>

  <div class="tcc-profile-grid">
    <?php $customers=[
      ['Grace Mwale','MW','Malawian','English, Chichewa','VIP','5','MK 1.75M','4.9','grace@email.com'],
      ['James Phiri','MW','Malawian','English','Regular','3','MK 820,000','4.7','james@email.com'],
      ['Sarah Banda','MW','Malawian','English, French','Regular','2','MK 540,000','5.0','sarah@email.com'],
      ['Kelvin Mbewe','MW','Malawian','English','Regular','4','MK 980,000','4.8','kelvin@email.com'],
      ['Anna Schultz','DE','German','German, English','International','1','MK 450,000','5.0','anna@email.de'],
      ['Mike Thompson','GB','British','English','International','2','MK 900,000','4.6','mike@email.co.uk'],
    ]; foreach($customers as $c): ?>
    <div class="tcc-profile-card" onclick="tccNotify('Opening profile: <?= e($c[0]) ?>…')">
      <div class="tcc-profile-avatar">
        <div class="tcc-profile-avatar-placeholder"><?= e(strtoupper(substr($c[0],0,1))) ?></div>
      </div>
      <div class="tcc-profile-name"><?= e($c[0]) ?></div>
      <div class="tcc-profile-role">🌍 <?= e($c[2]) ?> · 🗣️ <?= e($c[3]) ?></div>
      <span class="tcc-pill <?= $c[4]==='VIP'?'amber':'blue' ?>" style="margin:0 auto 0.5rem;"><?= e($c[4]) ?></span>
      <div class="tcc-profile-stats">
        <div><span class="tcc-profile-stat-val"><?= e($c[5]) ?></span><span class="tcc-profile-stat-label">Trips</span></div>
        <div><span class="tcc-profile-stat-val" style="font-size:0.72rem;"><?= e($c[6]) ?></span><span class="tcc-profile-stat-label">Spent</span></div>
        <div><span class="tcc-profile-stat-val"><?= e($c[7]) ?>★</span><span class="tcc-profile-stat-label">Rating</span></div>
      </div>
      <div style="display:flex;gap:0.3rem;justify-content:center;margin-top:0.5rem;">
        <button class="tcc-btn tcc-btn-xs tcc-btn-primary" onclick="event.stopPropagation();switchTccModule('messages')">Message</button>
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="event.stopPropagation();switchTccModule('bookings')">History</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div><!-- End Customers -->

<!-- ════════════════════ MODULE 10: GUIDES ════════════════════ -->
<div id="mod-guides" class="tcc-module-content">
  <div class="tcc-mod-header"><h2>Tour Guides Roster</h2><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('+ Guide profile created!')">+ Add Guide</button></div>

  <div class="tcc-profile-grid">
    <?php $guides=[
      ['John Kamanga','Wildlife &amp; Marine Specialist','English, Chichewa','4.9','8 yrs','On Tour','Lake Malawi Explorer','Certified SATSA'],
      ['Patrick Banda','Cultural &amp; Heritage Expert','English, Chichewa, Tumbuka','4.8','6 yrs','On Tour','Liwonde Safari','Certified WFR'],
      ['Agnes Phiri','Mountain &amp; Adventure Guide','English, Chichewa','4.9','5 yrs','Available','—','Certified FA'],
      ['Davie Nkhata','City &amp; History Guide','English, Chichewa, French','4.7','4 yrs','Available','—','Certified TGA'],
      ['Estelle Chirwa','Water &amp; Lake Expert','English, Chichewa','4.8','3 yrs','On Tour','Sunset Cruise','Certified PADI'],
      ['Moses Gondwe','Safari &amp; Birding Expert','English, Chichewa, Lomwe','5.0','10 yrs','Leave','—','Certified FGASA'],
    ]; foreach($guides as $g): ?>
    <div class="tcc-profile-card">
      <div class="tcc-profile-avatar"><div class="tcc-profile-avatar-placeholder"><?= e(strtoupper(substr($g[0],0,1))) ?></div></div>
      <div class="tcc-profile-name"><?= e($g[0]) ?></div>
      <div class="tcc-profile-role"><?= e($g[1]) ?></div>
      <span class="tcc-pill <?= $g[5]==='On Tour'?'green':($g[5]==='Leave'?'amber':'blue') ?>" style="margin:0 auto 0.4rem;"><?= e($g[5]) ?></span>
      <?php if($g[6]!=='—'): ?><small style="font-size:0.65rem;color:var(--tcc-text-dim);">📍 <?= e($g[6]) ?></small><?php endif; ?>
      <div class="tcc-profile-stats" style="margin-top:0.5rem;">
        <div><span class="tcc-profile-stat-val"><?= e($g[3]) ?>★</span><span class="tcc-profile-stat-label">Rating</span></div>
        <div><span class="tcc-profile-stat-val"><?= e($g[4]) ?></span><span class="tcc-profile-stat-label">Exp</span></div>
      </div>
      <small style="font-size:0.62rem;color:var(--tcc-green);font-weight:700;">✓ <?= e($g[7]) ?></small>
      <div style="display:flex;gap:0.3rem;justify-content:center;margin-top:0.6rem;">
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Assigning guide…')">Assign</button>
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="switchTccModule('messages')">Message</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div><!-- End Guides -->

<!-- ════════════════════ MODULE 11: TRANSPORT ════════════════════ -->
<div id="mod-transport" class="tcc-module-content">
  <div class="tcc-mod-header"><h2>Fleet &amp; Vehicle Management</h2><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('+ Vehicle added!')">+ Add Vehicle</button></div>

  <div class="tcc-profile-grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));">
    <?php $vehicles=[
      ['Safari Cruiser #1','Toyota Land Cruiser 200','Reg: BU-4512','25','On Tour','green','Insurance: Valid to Dec 2025','Service: Due soon ⚠️','Driver: John M.'],
      ['Land Rover #2','Land Rover Defender 110','Reg: BZ-1188','12','On Tour','green','Insurance: Valid to Mar 2026','Service: Current ✓','Driver: Peter K.'],
      ['Coaster Bus #3','Toyota Coaster 30-Seat','Reg: CZ-8819','30','Available','blue','Insurance: Valid to Jun 2026','Service: Current ✓','Driver: Available'],
      ['Minibus #4','Nissan Caravan NV350','Reg: DL-2244','15','Available','blue','Insurance: Valid to Aug 2025','Service: Current ✓','Driver: Davie N.'],
      ['Safari Cruiser #5','Mitsubishi Pajero SWB','Reg: EN-5567','8','Maintenance','amber','Insurance: Valid to Jan 2026','Service: In workshop ⚠️','Driver: Standby'],
      ['Boat MV-12','Fibreglass Speedboat 8-seater','Reg: MW-BOAT-12','8','Available','blue','Insurance: Valid to Dec 2025','Service: Current ✓','Driver: Captain M.'],
    ]; foreach($vehicles as $v): ?>
    <div class="tcc-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.7rem;">
        <div>
          <div style="font-weight:800;font-size:0.88rem;"><?= e($v[0]) ?></div>
          <div style="font-size:0.68rem;color:var(--tcc-text-dim);"><?= e($v[1]) ?> · <?= e($v[2]) ?></div>
        </div>
        <span class="tcc-pill <?= e($v[4]) ?>"><?= e($v[3]) ?></span>
      </div>
      <div style="display:flex;gap:0.5rem;margin-bottom:0.6rem;">
        <div style="text-align:center;flex:1;padding:0.4rem;background:var(--tcc-surface-2);border-radius:var(--tcc-radius-sm);">
          <strong style="display:block;font-size:0.88rem;"><?= e($v[3]) ?></strong>
          <small style="font-size:0.6rem;color:var(--tcc-text-dim);">Capacity</small>
        </div>
        <div style="text-align:center;flex:1;padding:0.4rem;background:var(--tcc-surface-2);border-radius:var(--tcc-radius-sm);">
          <strong style="display:block;font-size:0.75rem;"><?= e($v[4]) === 'amber' ? '🔧' : ($v[4]==='green'?'🟢':'🔵') ?></strong>
          <small style="font-size:0.6rem;color:var(--tcc-text-dim);"><?= $v[4]==='green'?'On Tour':($v[4]==='amber'?'Maintenance':'Available') ?></small>
        </div>
      </div>
      <div style="font-size:0.65rem;color:var(--tcc-text-dim);display:flex;flex-direction:column;gap:0.2rem;margin-bottom:0.6rem;">
        <span>🛡️ <?= e($v[5]) ?></span>
        <span>🔧 <?= e($v[6]) ?></span>
        <span>👤 <?= e($v[7]) ?></span>
      </div>
      <div style="display:flex;gap:0.3rem;">
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Opening vehicle record…')">Details</button>
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Scheduling maintenance…')">Maintenance</button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div><!-- End Transport -->

<!-- ════════════════════ MODULE 12: DESTINATIONS ════════════════════ -->
<div id="mod-destinations" class="tcc-module-content">
  <div class="tcc-mod-header"><h2>Destination Knowledge Base</h2><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('+ Destination added!')">+ Add Destination</button></div>

  <?php $regions=['Southern Region'=>[['Cape Maclear','https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=600&q=80','World Heritage lake beach · Cichlid snorkeling · Sunset cruises'],['Liwonde NP','https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=600&q=80','Hippos, elephants, birds · Rhino sanctuary · Safari drives'],['Zomba Plateau','https://images.unsplash.com/photo-1578662996442-48f60103fc96?auto=format&fit=crop&w=600&q=80','Cool highland escape · Pine forest trails · Botanical garden'],['Blantyre City','https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=600&q=80','Colonial heritage · Markets · St Michael Cathedral']],'Central Region'=>[['Lilongwe Old Town','https://images.unsplash.com/photo-1523805009345-7448845a9e53?auto=format&fit=crop&w=600&q=80','Cultural capital · Malawi Museum · City Market'],['Dedza Pottery','https://images.unsplash.com/photo-1518544866330-4e716499f800?auto=format&fit=crop&w=600&q=80','Artisan pottery · Rock paintings · Cool highland air']],'Northern Region'=>[['Nyika Plateau','https://images.unsplash.com/photo-1506905925346-21bda4d32df4?auto=format&fit=crop&w=600&q=80','Highest plateau in Central Africa · Roan antelope · Rolling grasslands'],['Livingstonia','https://images.unsplash.com/photo-1548013146-72479768bada?auto=format&fit=crop&w=600&q=80','Historic mission station · Manchewe Falls · Lake views']]]; ?>
  <?php foreach($regions as $region=>$dests): ?>
  <div class="tcc-section-title">📍 <?= e($region) ?></div>
  <div class="tcc-dest-grid" style="margin-bottom:1.25rem;">
    <?php foreach($dests as $d): ?>
    <div class="tcc-dest-card">
      <div class="tcc-dest-img"><img src="<?= e($d[1]) ?>" alt="<?= e($d[0]) ?>" loading="lazy"></div>
      <div class="tcc-dest-body">
        <h4><?= e($d[0]) ?></h4>
        <p><?= e($d[2]) ?></p>
        <div style="display:flex;gap:0.3rem;margin-top:0.6rem;">
          <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Viewing destination details…')">View Details</button>
          <button class="tcc-btn tcc-btn-xs tcc-btn-primary" onclick="switchTccModule('listings')">Add to Tour</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div><!-- End Destinations -->

<!-- ════════════════════ MODULE 13: MEDIA GALLERY ════════════════════ -->
<div id="mod-media" class="tcc-module-content">
  <div class="tcc-mod-header"><h2>Media Asset Library</h2><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('Upload dialog opening…')">+ Upload Media</button></div>

  <div class="tcc-media-folders">
    <?php $folders=[['🏞️','Tour Covers','42 images'],['📸','Destination Photos','186 images'],['🚁','Drone Footage','24 videos'],['🎥','Tour Videos','18 videos'],['📢','Marketing Assets','63 files'],['📄','Brochures &amp; PDFs','12 files'],['👥','Customer Uploads','120 images'],['⭐','Featured/Premium','28 files']]; foreach($folders as $f): ?>
    <div class="tcc-folder-card" onclick="tccNotify('Opening folder: <?= strip_tags($f[1]) ?>…')">
      <div class="tcc-folder-icon"><?= $f[0] ?></div>
      <div class="tcc-folder-name"><?= $f[1] ?></div>
      <div class="tcc-folder-count"><?= $f[2] ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="tcc-drop-zone" id="tcc-drop-zone" ondragover="event.preventDefault();this.classList.add('dragging')" ondragleave="this.classList.remove('dragging')" ondrop="this.classList.remove('dragging');tccNotify('Files uploaded successfully!')" onclick="tccNotify('File picker opening…')" style="margin-bottom:1.25rem;">
    <div class="tcc-drop-zone-icon">☁️</div>
    <strong style="display:block;margin-bottom:0.25rem;">Drag &amp; Drop to Upload</strong>
    <small style="color:var(--tcc-text-dim);">Supports JPG, PNG, MP4, PDF · Max 50MB per file · Auto-compression &amp; watermarking</small>
  </div>

  <div class="tcc-section-title">Tour Covers — Recent</div>
  <div class="tcc-media-preview-grid">
    <?php $imgs=['photo-1544620347-c4fd4a3d5957','photo-1516426122078-c23e76319801','photo-1578662996442-48f60103fc96','photo-1506905925346-21bda4d32df4','photo-1529156069898-49953e39b3ac','photo-1566073771259-6a8506099945','photo-1523805009345-7448845a9e53','photo-1518544866330-4e716499f800','photo-1548013146-72479768bada','photo-1469474968028-56623f02e42e','photo-1440688807730-73e4e2169fb8','photo-1533130061792-64b345e4a833']; foreach($imgs as $img): ?>
    <div class="tcc-media-thumb" onclick="tccNotify('Image preview opened')">
      <img src="https://images.unsplash.com/<?= $img ?>?auto=format&fit=crop&w=200&q=70" alt="Media" loading="lazy">
      <div class="tcc-media-thumb-overlay"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
    </div>
    <?php endforeach; ?>
  </div>
</div><!-- End Media -->

<!-- ════════════════════ MODULE 14: ADVERTISEMENT STUDIO ════════════════════ -->
<div id="mod-advertisements" class="tcc-module-content">
  <div class="tcc-mod-header">
    <div><h2>Advertisement Studio</h2><p style="font-size:0.78rem;color:var(--tcc-text-dim);">Visual campaign designer — changes update the preview instantly</p></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('Draft saved!')">Save Draft</button>
      <button class="tcc-btn tcc-btn-success" onclick="tccNotify('Campaign published to Uthenga marketplace!')">🚀 Publish Campaign</button>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:200px 1fr 220px;gap:0;border:1px solid var(--tcc-border);border-radius:var(--tcc-radius);overflow:hidden;min-height:560px;">

    <!-- Assets Panel -->
    <div style="background:var(--tcc-surface-2);border-right:1px solid var(--tcc-border);padding:0.85rem;overflow-y:auto;">
      <div style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--tcc-text-dim);margin-bottom:0.6rem;">📁 Asset Library</div>
      <div style="font-size:0.7rem;font-weight:700;margin-bottom:0.4rem;">Templates</div>
      <?php $tmpls=['Adventure','Luxury','Family','Cultural','Wildlife Safari','Weekend Escape','Beach Holiday','Business Retreat']; foreach($tmpls as $i=>$t): ?>
      <div style="padding:0.4rem 0.6rem;border-radius:var(--tcc-radius-sm);margin-bottom:0.2rem;font-size:0.72rem;cursor:pointer;background:<?= $i===4?'var(--tcc-primary-light)':'transparent' ?>;color:<?= $i===4?'var(--tcc-primary)':'var(--tcc-text)' ?>;font-weight:<?= $i===4?'700':'400' ?>;" onclick="tccNotify('Template applied: <?= $t ?>')"><?= $t ?></div>
      <?php endforeach; ?>
      <div class="tcc-divider"></div>
      <div style="font-size:0.7rem;font-weight:700;margin-bottom:0.4rem;">Cover Images</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.3rem;">
        <?php foreach(['photo-1544620347-c4fd4a3d5957','photo-1516426122078-c23e76319801','photo-1566073771259-6a8506099945','photo-1578662996442-48f60103fc96'] as $img): ?>
        <div style="aspect-ratio:1;border-radius:4px;overflow:hidden;cursor:pointer;border:1px solid var(--tcc-border);" onclick="tccNotify('Cover image selected')"><img src="https://images.unsplash.com/<?= $img ?>?auto=format&fit=crop&w=100&q=60" style="width:100%;height:100%;object-fit:cover;" alt="Cover"></div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Live Preview Panel -->
    <div style="background:var(--tcc-bg);padding:1.25rem;display:flex;flex-direction:column;align-items:center;gap:1rem;overflow-y:auto;">
      <div style="display:flex;gap:0.5rem;margin-bottom:0.5rem;">
        <button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="tccNotify('Desktop view')">🖥️ Desktop</button>
        <button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="tccNotify('Mobile view')">📱 Mobile</button>
        <button class="tcc-btn tcc-btn-secondary tcc-btn-sm" onclick="tccNotify('Search result card')">🔍 Search Card</button>
      </div>

      <!-- Marketplace Card Preview -->
      <div style="font-size:0.7rem;font-weight:700;color:var(--tcc-text-dim);text-transform:uppercase;letter-spacing:0.05em;align-self:flex-start;">Live Preview — Marketplace Card</div>
      <div class="tcc-ad-preview-card" style="width:320px;">
        <div class="tcc-ad-preview-img">
          <img src="https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=640&q=80" alt="Ad Preview" style="width:100%;height:100%;object-fit:cover;">
          <div class="tcc-ad-preview-img-overlay"></div>
          <div style="position:absolute;top:0.75rem;left:0.75rem;"><span class="tcc-pill amber" style="font-size:0.6rem;">⭐ TOP RATED</span></div>
          <div style="position:absolute;bottom:0.75rem;left:0.75rem;right:0.75rem;">
            <div style="font-size:0.65rem;color:rgba(255,255,255,0.7);margin-bottom:0.2rem;">📍 Liwonde National Park · 2 Days</div>
            <div style="font-size:1.05rem;font-weight:900;color:#fff;margin-bottom:0.15rem;">Liwonde Wildlife Safari</div>
            <div style="font-size:0.72rem;color:rgba(255,255,255,0.8);">Hippos · Elephants · Big 5</div>
          </div>
        </div>
        <div style="padding:0.9rem;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.6rem;">
            <div><span style="font-size:1.15rem;font-weight:900;color:#38bdf8;">MK 420,000</span><span style="font-size:0.7rem;color:#94a3b8;"> /person</span></div>
            <div style="color:#fbbf24;font-size:0.78rem;">★ 4.8 (82 reviews)</div>
          </div>
          <div style="display:flex;gap:0.4rem;font-size:0.65rem;margin-bottom:0.75rem;flex-wrap:wrap;">
            <span style="background:rgba(255,255,255,0.1);padding:0.2rem 0.5rem;border-radius:100px;color:#94a3b8;">🍽️ Meals Included</span>
            <span style="background:rgba(255,255,255,0.1);padding:0.2rem 0.5rem;border-radius:100px;color:#94a3b8;">🚌 Transport</span>
            <span style="background:rgba(255,255,255,0.1);padding:0.2rem 0.5rem;border-radius:100px;color:#94a3b8;">👨‍🏫 Guide</span>
          </div>
          <button class="tcc-btn tcc-btn-success" style="width:100%;justify-content:center;" onclick="tccNotify('Preview CTA clicked!')">Book Now — 6 Seats Left!</button>
        </div>
      </div>

      <!-- Performance Stats -->
      <div style="background:var(--tcc-surface);border:1px solid var(--tcc-border);border-radius:var(--tcc-radius-sm);padding:0.85rem;width:320px;">
        <div style="font-size:0.7rem;font-weight:800;text-transform:uppercase;color:var(--tcc-text-dim);margin-bottom:0.6rem;">Active Campaign Performance</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;font-size:0.72rem;">
          <?php $perf=['Impressions'=>'42,100','Click-Through'=>'8.4%','Bookings'=>'24','Revenue Generated'=>'MK 10.08M','Audience Reach'=>'18,440','Campaign ROI'=>'12.4×']; foreach($perf as $k=>$v): ?>
          <div style="background:var(--tcc-bg);border-radius:var(--tcc-radius-sm);padding:0.5rem;"><strong style="display:block;font-size:0.82rem;"><?= $v ?></strong><small style="color:var(--tcc-text-dim);"><?= $k ?></small></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Properties Inspector Panel -->
    <div style="background:var(--tcc-surface);border-left:1px solid var(--tcc-border);padding:0.85rem;overflow-y:auto;">
      <div style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--tcc-text-dim);margin-bottom:0.75rem;">⚙️ Campaign Properties</div>
      <div style="margin-bottom:0.75rem;"><label class="tcc-label">Campaign Name</label><input class="tcc-input" style="font-size:0.75rem;" value="Liwonde Safari — May Special"></div>
      <div style="margin-bottom:0.75rem;"><label class="tcc-label">Featured Badge</label><select class="tcc-input tcc-select" style="font-size:0.75rem;"><option>⭐ Top Rated</option><option>🔥 Limited Seats</option><option>🎉 Weekend Special</option><option>💎 Premium</option></select></div>
      <div style="margin-bottom:0.75rem;"><label class="tcc-label">Promotional Tagline</label><input class="tcc-input" style="font-size:0.75rem;" value="Hippos · Elephants · Big 5"></div>
      <div style="margin-bottom:0.75rem;"><label class="tcc-label">CTA Button Text</label><input class="tcc-input" style="font-size:0.75rem;" value="Book Now — 6 Seats Left!"></div>
      <div class="tcc-divider"></div>
      <div style="font-size:0.68rem;font-weight:800;text-transform:uppercase;color:var(--tcc-text-dim);margin:0.5rem 0 0.6rem;">Campaign Window</div>
      <div style="margin-bottom:0.5rem;"><label class="tcc-label">Start Date</label><input class="tcc-input" style="font-size:0.75rem;" type="date" value="2025-05-14"></div>
      <div style="margin-bottom:0.5rem;"><label class="tcc-label">End Date</label><input class="tcc-input" style="font-size:0.75rem;" type="date" value="2025-05-28"></div>
      <div style="margin-bottom:0.5rem;"><label class="tcc-label">Target Region</label><select class="tcc-input tcc-select" style="font-size:0.75rem;"><option selected>All Malawi</option><option>Lilongwe</option><option>Blantyre</option><option>International</option></select></div>
      <div style="margin-bottom:0.75rem;"><label class="tcc-label">Budget (MK)</label><input class="tcc-input" style="font-size:0.75rem;" value="150,000"></div>
      <div style="margin-bottom:0.5rem;"><label class="tcc-label">Priority</label><select class="tcc-input tcc-select" style="font-size:0.75rem;"><option>Standard</option><option selected>Sponsored</option><option>Premium Placement</option></select></div>
    </div>
  </div>
</div><!-- End Advertisements -->

<!-- ════════════════════ MODULE 15: REVIEWS ════════════════════ -->
<div id="mod-reviews" class="tcc-module-content">
  <div class="tcc-mod-header">
    <div><h2>Reviews &amp; Reputation Center</h2><p style="font-size:0.78rem;color:var(--tcc-text-dim);">Overall Rating: 4.8★ · 1,240 reviews</p></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="tcc-filter-btn active">All (1,240)</button>
      <button class="tcc-filter-btn">Pending Reply (32)</button>
      <button class="tcc-filter-btn">5★ (820)</button>
      <button class="tcc-filter-btn">Needs Action (5)</button>
    </div>
  </div>

  <?php $reviews=[
    ['Grace Mwale','Lake Malawi Explorer','5','Positive','2 days ago','The experience was absolutely incredible! Our guide John was knowledgeable, funny, and genuinely passionate about the lake. Snorkeling with the cichlids was a once-in-a-lifetime experience. I will be back!','John Kamanga','sunset-cruise-photo'],
    ['James Phiri','Liwonde Safari','5','Positive','3 days ago','Best safari I\'ve ever done in Malawi. Patrick\'s expertise on the birds and hippos was exceptional. My son was amazed. Worth every kwacha!','Patrick Banda',''],
    ['Sarah B.','Zomba Plateau Hike','3','Neutral','5 days ago','The views from the plateau were stunning but the trail was more challenging than described. Good guide, but clearer difficulty information would help.','Agnes Phiri',''],
    ['Mike T.','Lake Malawi Explorer','5','Positive','1 week ago','Incredible value. Coming from UK, I was amazed at how pristine Lake Malawi still is. Professional, safe, beautiful. Highly recommended!','John Kamanga',''],
    ['Anonymous','City Heritage Walk','2','Negative','1 week ago','The tour was too short and the guide arrived late. Missed the museum. Expected more for the price.','Davie Nkhata',''],
  ]; foreach($reviews as $r): ?>
  <div class="tcc-review-card">
    <div class="tcc-review-header">
      <div class="tcc-review-author">
        <div class="tcc-review-avatar"><?= e(strtoupper(substr($r[0],0,1))) ?></div>
        <div>
          <strong style="font-size:0.82rem;"><?= e($r[0]) ?></strong>
          <div style="font-size:0.68rem;color:var(--tcc-text-dim);"><?= e($r[1]) ?> · <?= e($r[4]) ?></div>
          <div class="tcc-stars"><?= str_repeat('★',(int)$r[2]).str_repeat('☆',5-(int)$r[2]) ?> <span style="font-size:0.65rem;color:var(--tcc-text-dim);"><?= e($r[2]) ?>/5</span></div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:0.5rem;">
        <span class="tcc-sentiment-badge <?= strtolower($r[2]) ?>"><?= e($r[2]) ?></span>
        <span style="font-size:0.68rem;color:var(--tcc-text-dim);">Guide: <?= e($r[5]) ?></span>
      </div>
    </div>
    <p class="tcc-review-text">"<?= e($r[3]) ?>"</p>
    <div class="tcc-review-actions">
      <button class="tcc-btn tcc-btn-xs tcc-btn-primary" onclick="tccNotify('Reply dialog opened!')">💬 Reply</button>
      <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Review hidden!')">🙈 Hide</button>
      <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Escalated to support!')">⚠️ Escalate</button>
      <?php if((int)$r[2]>=4): ?><button class="tcc-btn tcc-btn-xs tcc-btn-success" onclick="tccNotify('Thank-you reward sent!')">🎁 Reward</button><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div><!-- End Reviews -->

<!-- ════════════════════ MODULE 16: ANALYTICS ════════════════════ -->
<div id="mod-analytics" class="tcc-module-content">
  <div class="tcc-mod-header">
    <div><h2>Analytics &amp; Business Intelligence</h2></div>
    <div style="display:flex;gap:0.5rem;">
      <select class="tcc-input tcc-select" style="width:auto;font-size:0.75rem;padding:0.35rem 1.8rem 0.35rem 0.6rem;"><option>Last 30 Days</option><option>This Month</option><option>Last Quarter</option><option>This Year</option></select>
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('Report exported!')">Export Report</button>
    </div>
  </div>

  <div class="tcc-analytics-grid" style="margin-bottom:1rem;">
    <!-- Revenue Trend Chart -->
    <div class="tcc-chart-container">
      <div class="tcc-chart-header"><h3>Revenue Trend</h3><span class="tcc-pill green">↑ 18%</span></div>
      <div class="tcc-bar-chart" id="revenue-chart">
        <?php $months=['Jan','Feb','Mar','Apr','May','Jun']; $vals=[62,74,55,88,100,91]; foreach($months as $i=>$m): $h=($vals[$i]/100)*110; ?><div class="tcc-bar" style="height:<?= $h ?>px;background:<?= $i===4?'var(--tcc-primary)':'var(--tcc-primary-light)' ?>;"><span class="tcc-bar-label"><?= $m ?></span></div><?php endforeach; ?>
      </div>
    </div>

    <!-- Bookings by Category -->
    <div class="tcc-chart-container">
      <div class="tcc-chart-header"><h3>Bookings by Tour Type</h3></div>
      <div class="tcc-donut-wrap">
        <div style="width:100px;height:100px;position:relative;flex-shrink:0;">
          <svg viewBox="0 0 36 36" style="width:100%;height:100%;transform:rotate(-90deg);">
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#e2e8f0" stroke-width="3.8"/>
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#10b981" stroke-width="3.8" stroke-dasharray="38 100"/>
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#2563eb" stroke-width="3.8" stroke-dasharray="27 100" stroke-dashoffset="-38"/>
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#8b5cf6" stroke-width="3.8" stroke-dasharray="18 100" stroke-dashoffset="-65"/>
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#f59e0b" stroke-width="3.8" stroke-dasharray="10 100" stroke-dashoffset="-83"/>
            <circle cx="18" cy="18" r="15.9" fill="transparent" stroke="#f43f5e" stroke-width="3.8" stroke-dasharray="7 100" stroke-dashoffset="-93"/>
          </svg>
          <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:0.82rem;">286</div>
        </div>
        <div class="tcc-donut-legend">
          <?php $cats=[['Wildlife Safari','38%','#10b981'],['Beach &amp; Lake','27%','#2563eb'],['Cultural','18%','#8b5cf6'],['Mountain Hike','10%','#f59e0b'],['City Tour','7%','#f43f5e']]; foreach($cats as $c): ?>
          <div class="tcc-donut-legend-item"><div class="tcc-donut-dot" style="background:<?= $c[2] ?>;"></div><span style="font-size:0.72rem;"><?= $c[0] ?></span><strong style="margin-left:auto;font-size:0.72rem;"><?= $c[1] ?></strong></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="tcc-analytics-grid" style="margin-bottom:1rem;">
    <!-- Guide Performance -->
    <div class="tcc-chart-container">
      <div class="tcc-chart-header"><h3>Guide Performance</h3></div>
      <div style="display:flex;flex-direction:column;gap:0.6rem;font-size:0.75rem;">
        <?php $gperf=[['Moses Gondwe','5.0★','48 tours','MK 6.2M'],['John Kamanga','4.9★','128 tours','MK 15.4M'],['Agnes Phiri','4.9★','46 tours','MK 3.9M'],['Patrick Banda','4.8★','82 tours','MK 9.8M'],['Estelle Chirwa','4.8★','38 tours','MK 2.5M']]; foreach($gperf as $i=>$g): ?>
        <div style="display:flex;align-items:center;gap:0.75rem;">
          <div style="width:22px;height:22px;border-radius:50%;background:var(--tcc-primary-light);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.6rem;color:var(--tcc-primary);flex-shrink:0;"><?= $i+1 ?></div>
          <div style="flex:1;"><div style="display:flex;justify-content:space-between;margin-bottom:0.15rem;"><span><?= e($g[0]) ?></span><strong><?= e($g[1]) ?></strong></div><div class="tcc-progress-bar-wrap"><div class="tcc-progress-fill" style="width:<?= [100,96,94,90,85][$i] ?>%;background:var(--tcc-primary);"></div></div></div>
          <span style="color:var(--tcc-text-dim);font-size:0.68rem;width:55px;text-align:right;"><?= e($g[3]) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Occupancy Rate -->
    <div class="tcc-chart-container">
      <div class="tcc-chart-header"><h3>Occupancy Rate by Tour</h3></div>
      <div style="display:flex;flex-direction:column;gap:0.55rem;font-size:0.75rem;">
        <?php $occ=[['Lake Malawi Explorer','92%','var(--tcc-green)'],['Liwonde Safari','87%','var(--tcc-primary)'],['Zomba Plateau Hike','82%','var(--tcc-purple)'],['Mulanje Trek','74%','var(--tcc-amber)'],['City Heritage Walk','68%','var(--tcc-orange)'],['Sunset Cruise','60%','var(--tcc-rose)']]; foreach($occ as $o): ?>
        <div><div style="display:flex;justify-content:space-between;margin-bottom:0.15rem;"><span><?= e($o[0]) ?></span><strong style="color:<?= $o[2] ?>;"><?= e($o[1]) ?></strong></div><div class="tcc-progress-bar-wrap"><div class="tcc-progress-fill" style="width:<?= e($o[1]) ?>;background:<?= $o[2] ?>;"></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Geographic Demand -->
  <div class="tcc-card" style="margin-bottom:1rem;">
    <div class="tcc-card-head"><h3>Geographic Customer Demand (Top Markets)</h3></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:0.6rem;font-size:0.75rem;">
      <?php $markets=[['🇲🇼','Malawi','52%','var(--tcc-primary)'],['🇬🇧','United Kingdom','14%','var(--tcc-green)'],['🇩🇪','Germany','11%','var(--tcc-purple)'],['🇺🇸','United States','9%','var(--tcc-amber)'],['🇿🇦','South Africa','7%','var(--tcc-orange)'],['🇫🇷','France','4%','var(--tcc-rose)'],['🇳🇱','Netherlands','3%','var(--tcc-cyan)']]; foreach($markets as $m): ?>
      <div style="background:var(--tcc-surface-2);border-radius:var(--tcc-radius-sm);padding:0.7rem;text-align:center;"><div style="font-size:1.5rem;margin-bottom:0.25rem;"><?= $m[0] ?></div><strong><?= $m[1] ?></strong><div style="color:<?= $m[3] ?>;font-weight:900;font-size:0.88rem;"><?= $m[2] ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div><!-- End Analytics -->

<!-- ════════════════════ MODULE 17: PAYMENTS ════════════════════ -->
<div id="mod-payments" class="tcc-module-content">
  <div class="tcc-mod-header">
    <div><h2>Financial Settlements</h2></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('CSV exported!')">CSV</button>
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('Excel exported!')">Excel</button>
      <button class="tcc-btn tcc-btn-secondary" onclick="tccNotify('PDF generated!')">PDF</button>
      <button class="tcc-btn tcc-btn-primary" onclick="tccNotify('Payout requested!')">Request Payout</button>
    </div>
  </div>

  <div class="tcc-payment-kpis">
    <?php $pkpis=[['Pending Payout','MK 1,228,250','+MK 180,000 today','up'],['Completed Payouts','MK 3,621,750','This month','up'],['Total Revenue','MK 4,850,000','May 2025','up'],['Refunds Issued','MK 240,000','8 refunds','down'],['Platform Commission','MK 242,500','5% platform fee',''],['Net Profit','MK 1,622,250','33.4% margin','up'],['Avg Booking Value','MK 322,000','+12% vs April','up'],['Gateway Status','All Operational','Airtel · TNM · Cards','']]; foreach($pkpis as $p): ?>
    <div class="tcc-payment-kpi"><label><?= e($p[0]) ?></label><div class="amount"><?= e($p[1]) ?></div><div class="delta <?= e($p[3]) ?>"><?= e($p[2]) ?></div></div>
    <?php endforeach; ?>
  </div>

  <div class="tcc-card">
    <div class="tcc-card-head"><h3>Recent Transactions</h3><button class="tcc-btn tcc-btn-sm tcc-btn-secondary" onclick="tccNotify('Filter applied')">Filter</button></div>
    <table class="tcc-table">
      <thead><tr><th>Transaction ID</th><th>Booking</th><th>Customer</th><th>Tour</th><th>Amount</th><th>Gateway</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php $txns=[['TXN-8821','#BK-4587','Grace Mwale','Lake Malawi Explorer','MK 350,000','Airtel Money','Settled','May 10'],['TXN-8820','#BK-4586','James Phiri','Liwonde Safari','MK 420,000','TNM Mpamba','Settled','May 10'],['TXN-8819','#BK-4585','Sarah Banda','Zomba Hike','MK 135,000','Card','Partial','May 11'],['TXN-8818','#BK-4584','Kelvin Mbewe','City Walk','MK 140,000','Airtel Money','Settled','May 11'],['TXN-8817','#BK-4582','David Nkosi','Lake Malawi Explorer','MK 700,000','Bank Transfer','Settled','May 8'],['TXN-8815','#BK-4575','Anna Schultz','Lake Malawi Explorer','MK 450,000','Card (USD)','Settled','May 6']]; foreach($txns as $t): ?>
        <tr><td><code style="font-size:0.7rem;"><?= e($t[0]) ?></code></td><td><?= e($t[1]) ?></td><td><?= e($t[2]) ?></td><td><?= e($t[3]) ?></td><td><strong><?= e($t[4]) ?></strong></td><td><?= e($t[5]) ?></td><td><span class="tcc-pill <?= $t[6]==='Settled'?'green':'amber' ?>"><?= e($t[6]) ?></span></td><td style="font-size:0.72rem;color:var(--tcc-text-dim);"><?= e($t[7]) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div><!-- End Payments -->

<!-- ════════════════════ MODULE 18: MESSAGES ════════════════════ -->
<div id="mod-messages" class="tcc-module-content">
  <div class="tcc-mod-header"><h2>Unified Communication Hub</h2><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('New conversation started!')">+ New Conversation</button></div>

  <div class="tcc-messages-layout">
    <div class="tcc-msg-sidebar">
      <div class="tcc-msg-search"><div class="tcc-toolbar-search"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><input type="text" placeholder="Search conversations…"></div></div>
      <div class="tcc-msg-tabs">
        <div class="tcc-msg-tab active">Customers</div>
        <div class="tcc-msg-tab">Guides</div>
        <div class="tcc-msg-tab">Drivers</div>
        <div class="tcc-msg-tab">Staff</div>
      </div>
      <div class="tcc-msg-list">
        <?php $convs=[['Grace Mwale','What should we pack for the boat cruise?','2m','3','green','active'],['James Phiri','Can we add an extra day to the safari?','15m','1','blue',''],['Sarah Banda','Thank you for the wonderful experience!','1h','0','purple',''],['Anna Schultz','Is the lake safe for swimming?','2h','2','amber',''],['Mike Thompson','Any chance of upgrading our accommodation?','3h','0','cyan',''],['Agnes Phiri (Guide)','Confirmed for May 17 Zomba departure','5h','0','green','']]; foreach($convs as $c): ?>
        <div class="tcc-msg-item <?= $c[5] ?>" onclick="tccNotify('Opening conversation with <?= e($c[0]) ?>…')">
          <div class="tcc-msg-avatar"><?= e(strtoupper(substr($c[0],0,1))) ?></div>
          <div class="tcc-msg-meta" style="flex:1;min-width:0;">
            <strong><?= e($c[0]) ?></strong>
            <small style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($c[1]) ?></small>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.2rem;flex-shrink:0;">
            <small style="font-size:0.6rem;color:var(--tcc-text-muted);"><?= e($c[2]) ?></small>
            <?php if($c[3]>0): ?><span style="background:var(--tcc-primary);color:#fff;font-size:0.58rem;font-weight:800;width:16px;height:16px;border-radius:50%;display:flex;align-items:center;justify-content:center;"><?= e($c[3]) ?></span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="tcc-msg-chat">
      <div class="tcc-msg-chat-header">
        <div class="tcc-msg-avatar">G</div>
        <div><strong>Grace Mwale</strong><small style="display:block;font-size:0.65rem;color:var(--tcc-green);">● Online · Lake Malawi Explorer · May 14</small></div>
        <div style="margin-left:auto;display:flex;gap:0.3rem;">
          <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="switchTccModule('bookings')">View Booking</button>
          <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Customer profile opened')">Profile</button>
        </div>
      </div>
      <div class="tcc-msg-chat-messages">
        <div class="tcc-chat-bubble incoming">Hello! I'm so excited about the Lake Malawi tour tomorrow. Could you advise what to pack for the sunset boat cruise?</div>
        <div class="tcc-chat-bubble outgoing">Hi Grace! Great to hear from you! 🌊 For the sunset cruise, we recommend: light layers (it gets breezy), camera, sunscreen, and comfortable sandals. We provide all snorkeling equipment.</div>
        <div class="tcc-chat-bubble incoming">Perfect! Should I bring cash for extra purchases at the lodge?</div>
        <div class="tcc-chat-bubble outgoing">Yes, the lodge has a small curio shop. Bring some MWK cash for souvenirs. Most activities are fully included in your tour price though! See you tomorrow at 6:30 AM at your hotel. 😊</div>
        <div class="tcc-chat-bubble incoming">What should we pack for the boat cruise?</div>
      </div>
      <div class="tcc-msg-input-bar">
        <input class="tcc-input" style="flex:1;" placeholder="Type a message… or use AI-assist">
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('AI reply suggested!')">🤖 AI Assist</button>
        <button class="tcc-btn tcc-btn-primary tcc-btn-sm" onclick="tccNotify('Message sent!')">Send</button>
      </div>
    </div>
  </div>
</div><!-- End Messages -->

<!-- ════════════════════ MODULE 19: DOCUMENTS ════════════════════ -->
<div id="mod-documents" class="tcc-module-content">
  <div class="tcc-mod-header"><h2>Document Repository</h2><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('Upload dialog opened!')">+ Upload Document</button></div>

  <div class="tcc-doc-grid">
    <?php $docs=[
      ['Tourism Operator License','#TO-9941','Valid to Dec 31, 2025','45 days','warn','📜','green'],
      ['Business Registration','#BRS-2024-0081','Valid to Dec 31, 2026','Renews in 596 days','safe','🏢','blue'],
      ['Fleet Insurance Certificate','Policy #FI-20025','Valid to Mar 15, 2026','All vehicles covered','safe','🛡️','green'],
      ['Guide Certification — John Kamanga','SATSA #JK-7821','Valid to Sep 30, 2025','Renews in 139 days','safe','👨‍🏫','purple'],
      ['Guide Certification — Patrick Banda','WFR #PB-4412','Valid to Jun 30, 2025','Expires in 47 days!','danger','👨‍🏫','rose'],
      ['Vehicle Permits — Fleet','Permit #VP-2025','Valid to Dec 31, 2025','All 9 vehicles','safe','🚌','blue'],
      ['Emergency Procedures Manual','EMP-v3.2','Updated Jan 2025','Guides trained ✓','safe','🚑','green'],
      ['Customer Waivers Template','CWT-v2.1','Updated Mar 2025','Auto-attached to bookings','safe','📋','amber'],
      ['Marketing Brochure 2025','MKT-2025-Q2','Updated May 2025','Digital &amp; Print versions','safe','📄','cyan'],
    ]; foreach($docs as $d): ?>
    <div class="tcc-doc-card">
      <div style="display:flex;align-items:center;gap:0.6rem;">
        <div class="tcc-doc-icon" style="background:var(--tcc-<?= $d[6] ?>-light);"><span style="font-size:1.2rem;"><?= $d[0][0] === 'G' ? $d[4][0] : $d[0][0] ?></span><?= $d[0][0] ?></div>
        <div style="font-size:1.5rem;"><?= $d[4] ?></div>
        <div>
          <div class="tcc-doc-name"><?= e($d[0]) ?></div>
          <div class="tcc-doc-meta"><?= e($d[1]) ?></div>
        </div>
      </div>
      <div class="tcc-expiry-badge <?= e($d[5]) ?>">⏰ <?= e($d[3]) ?></div>
      <div style="font-size:0.65rem;color:var(--tcc-text-dim);">📅 <?= e($d[2]) ?></div>
      <div style="display:flex;gap:0.3rem;margin-top:0.25rem;">
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Document downloaded!')">Download</button>
        <button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Document updated!')">Update</button>
        <?php if($d[5]==='danger'||$d[5]==='warn'): ?><button class="tcc-btn tcc-btn-xs tcc-btn-danger" onclick="tccNotify('Renewal initiated!')">Renew!</button><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div><!-- End Documents -->

<!-- ════════════════════ MODULE 20: SETTINGS ════════════════════ -->
<div id="mod-settings" class="tcc-module-content">
  <div class="tcc-mod-header"><h2>Business Settings</h2><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('Settings saved!')">Save All Changes</button></div>

  <div class="tcc-settings-grid">
    <div class="tcc-settings-nav">
      <?php $snav=[['profile','🏢 Business Profile'],['regions','🗺️ Operating Regions'],['policies','📜 Booking Policies'],['payment','💳 Payment Gateway'],['notifications','🔔 Notifications'],['branding','🎨 Branding'],['team','👥 Team &amp; Roles'],['security','🔐 Security'],['integrations','🔌 Integrations'],['data','💾 Data &amp; Backup']]; foreach($snav as $i=>[$id,$label]): ?>
      <div class="tcc-settings-nav-item <?= $i===0?'active':'' ?>" onclick="tccSelectSettingsSection('<?= $id ?>', this)"><?= $label ?></div>
      <?php endforeach; ?>
    </div>

    <div>
      <div class="tcc-settings-panel">
        <div id="settings-profile" class="tcc-settings-section active">
          <h3 style="margin-bottom:1rem;">Business Profile</h3>
          <div class="tcc-form-row cols-2"><div><label class="tcc-label">Business Name</label><input class="tcc-input" value="Axon Tours &amp; Safaris"></div><div><label class="tcc-label">Registration Number</label><input class="tcc-input" value="BRS-2024-0081"></div></div>
          <div class="tcc-form-row cols-2"><div><label class="tcc-label">Primary Contact</label><input class="tcc-input" value="+265 999 123 456"></div><div><label class="tcc-label">Email</label><input class="tcc-input" type="email" value="info@axontours.mw"></div></div>
          <div class="tcc-form-row cols-2"><div><label class="tcc-label">Website</label><input class="tcc-input" value="https://axontours.mw"></div><div><label class="tcc-label">Base Location</label><input class="tcc-input" value="Lilongwe, Malawi"></div></div>
          <div><label class="tcc-label">Business Description</label><textarea class="tcc-input" style="resize:vertical;min-height:80px;">Malawi's premier tour operator specializing in lake, wildlife, mountain, and cultural tours. Licensed since 2018.</textarea></div>
          <button class="tcc-btn tcc-btn-primary" style="margin-top:0.75rem;" onclick="tccNotify('Profile saved!')">Save Profile</button>
        </div>

        <div id="settings-regions" class="tcc-settings-section"><h3 style="margin-bottom:1rem;">Operating Regions</h3><p style="color:var(--tcc-text-dim);font-size:0.82rem;">Configure which regions your tours operate in and set local contact details.</p><div style="margin-top:1rem;display:flex;flex-wrap:wrap;gap:0.5rem;"><?php foreach(['Southern Malawi','Central Malawi','Northern Malawi','Lake Malawi National Park','Liwonde NP','Nyika Plateau','International'] as $r): ?><div class="tcc-service-chip selected" onclick="this.classList.toggle('selected')"><?= e($r) ?></div><?php endforeach; ?></div><button class="tcc-btn tcc-btn-primary" style="margin-top:1rem;" onclick="tccNotify('Regions saved!')">Save Regions</button></div>

        <div id="settings-policies" class="tcc-settings-section"><h3 style="margin-bottom:1rem;">Booking &amp; Cancellation Policies</h3>
          <div class="tcc-form-row cols-2"><div><label class="tcc-label">Minimum Advance Booking</label><select class="tcc-input tcc-select"><option selected>24 hours</option><option>48 hours</option><option>72 hours</option></select></div><div><label class="tcc-label">Max Group Size Override</label><input class="tcc-input" value="30"></div></div>
          <div class="tcc-form-row cols-2"><div><label class="tcc-label">Default Cancellation Policy</label><select class="tcc-input tcc-select"><option selected>Free up to 48h before</option><option>No refund within 24h</option><option>Non-refundable</option></select></div><div><label class="tcc-label">No-Show Policy</label><select class="tcc-input tcc-select"><option selected>Forfeit — no refund</option><option>50% refund</option></select></div></div>
          <button class="tcc-btn tcc-btn-primary" style="margin-top:0.5rem;" onclick="tccNotify('Policies saved!')">Save Policies</button></div>

        <div id="settings-payment" class="tcc-settings-section"><h3 style="margin-bottom:1rem;">Payment Gateway Configuration</h3>
          <?php foreach(['Airtel Money'=>'Active ✓','TNM Mpamba'=>'Active ✓','National Bank Card'=>'Active ✓','PayChangu (USD)'=>'Inactive'] as $gw=>$status): ?>
          <div class="tcc-card" style="margin-bottom:0.5rem;padding:0.75rem;"><div style="display:flex;justify-content:space-between;align-items:center;"><strong style="font-size:0.82rem;"><?= e($gw) ?></strong><div style="display:flex;gap:0.5rem;align-items:center;"><span class="tcc-pill <?= strpos($status,'Active')===0?'green':'gray' ?>"><?= e($status) ?></span><button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Configuring <?= e($gw) ?>…')">Configure</button></div></div></div>
          <?php endforeach; ?></div>

        <div id="settings-notifications" class="tcc-settings-section"><h3 style="margin-bottom:1rem;">Notification Preferences</h3><div style="display:flex;flex-direction:column;gap:0.6rem;"><?php foreach(['New booking received','Payment confirmed','Booking cancelled','Guide assigned','Vehicle maintenance due','Review received','Ad campaign expiring','License expiring'] as $notif): ?><div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid var(--tcc-border);"><span style="font-size:0.82rem;"><?= e($notif) ?></span><div style="display:flex;gap:0.5rem;"><label style="font-size:0.72rem;"><input type="checkbox" checked style="margin-right:0.3rem;">SMS</label><label style="font-size:0.72rem;"><input type="checkbox" checked style="margin-right:0.3rem;">Email</label><label style="font-size:0.72rem;"><input type="checkbox" style="margin-right:0.3rem;">Push</label></div></div><?php endforeach; ?></div><button class="tcc-btn tcc-btn-primary" style="margin-top:0.75rem;" onclick="tccNotify('Notification preferences saved!')">Save</button></div>

        <div id="settings-branding" class="tcc-settings-section"><h3 style="margin-bottom:1rem;">Branding &amp; Identity</h3><div class="tcc-form-row cols-2"><div><label class="tcc-label">Business Logo URL</label><input class="tcc-input" placeholder="https://…/logo.png"></div><div><label class="tcc-label">Primary Brand Color</label><input class="tcc-input" type="color" value="#10b981" style="height:42px;cursor:pointer;"></div></div><div><label class="tcc-label">Default Tour Brochure Template</label><select class="tcc-input tcc-select"><option selected>Adventure Green</option><option>Ocean Blue</option><option>Safari Gold</option><option>Minimal White</option></select></div><button class="tcc-btn tcc-btn-primary" style="margin-top:0.75rem;" onclick="tccNotify('Branding saved!')">Save Branding</button></div>

        <div id="settings-team" class="tcc-settings-section"><h3 style="margin-bottom:1rem;">Team &amp; Role Management</h3><table class="tcc-table" style="margin-bottom:0.75rem;"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach([['Christopher Ngoma','cn@axon.mw','Owner','Active',''],['Grace Admin','ga@axon.mw','Manager','Active',''],['Patrick Guide','pg@axon.mw','Guide','Active',''],['Agnes Guide','ag@axon.mw','Guide','Active',''],['David Accounts','da@axon.mw','Finance','Active','']] as $t): ?><tr><td><?= e($t[0]) ?></td><td><?= e($t[1]) ?></td><td><span class="tcc-pill blue"><?= e($t[2]) ?></span></td><td><span class="tcc-pill green"><?= e($t[3]) ?></span></td><td><button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Editing team member…')">Edit</button></td></tr><?php endforeach; ?></tbody></table><button class="tcc-btn tcc-btn-primary" onclick="tccNotify('+ Team member invited!')">+ Invite Member</button></div>

        <div id="settings-security" class="tcc-settings-section"><h3 style="margin-bottom:1rem;">Security &amp; Access</h3>
          <?php foreach(['Two-Factor Authentication (2FA)' => 'Enabled ✓','Login Alerts' => 'Enabled ✓','Session Timeout' => '4 hours','API Key Management' => '2 active keys'] as $s=>$v): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:0.65rem 0;border-bottom:1px solid var(--tcc-border);"><span style="font-size:0.82rem;"><?= e($s) ?></span><div style="display:flex;align-items:center;gap:0.5rem;"><span style="font-size:0.75rem;color:var(--tcc-text-dim);"><?= e($v) ?></span><button class="tcc-btn tcc-btn-xs tcc-btn-secondary" onclick="tccNotify('Configuring <?= e($s) ?>…')">Manage</button></div></div>
          <?php endforeach; ?></div>

        <?php foreach(['integrations'=>['🔌 Integrations','Configure third-party API integrations and webhooks.'],'data'=>['💾 Data &amp; Backup','Export all business data or configure automated backups.']] as $id=>[$title,$desc]): ?>
        <div id="settings-<?= $id ?>" class="tcc-settings-section"><h3 style="margin-bottom:0.5rem;"><?= $title ?></h3><p style="color:var(--tcc-text-dim);font-size:0.82rem;"><?= $desc ?></p><button class="tcc-btn tcc-btn-primary" style="margin-top:1rem;" onclick="tccNotify('<?= strip_tags($title) ?> opened!')"><?= $title === '🔌 Integrations' ? 'Manage Integrations' : 'Export All Data' ?></button></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div><!-- End Settings -->

</main><!-- End Center Workspace -->

<!-- ═══════════════════════════ RIGHT AI PANEL ═══════════════════════════ -->
<aside class="tcc-right-ai-panel">
  <div class="tcc-ai-header">
    <div class="tcc-ai-header-title">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      AI Operations Assistant
    </div>
    <span class="tcc-pill purple" style="font-size:0.58rem;">LIVE</span>
  </div>

  <div class="tcc-ai-card"><p><strong>Liwonde Safari nearly full.</strong><br>May 16 departure — 6 seats. Increase price 8% to optimize yield.</p><button class="tcc-btn tcc-btn-primary" style="width:100%;font-size:0.7rem;justify-content:center;" onclick="switchTccModule('pricing')">Adjust Pricing</button></div>
  <div class="tcc-ai-card"><p><strong>Weekend demand up 31%.</strong><br>Open another Lake Malawi Saturday departure — projected 20+ bookings.</p><button class="tcc-btn tcc-btn-secondary" style="width:100%;font-size:0.7rem;justify-content:center;" onclick="switchTccModule('schedules')">Add Departure</button></div>
  <div class="tcc-ai-card"><p><strong>Zomba rain alert — May 15.</strong><br>12 travelers on tomorrow's plateau hike. Send packing advisory now.</p><button class="tcc-btn tcc-btn-success" style="width:100%;font-size:0.7rem;justify-content:center;" onclick="tccNotify('Advisory sent to all 12 travelers!')">Send Advisory</button></div>
  <div class="tcc-ai-card"><p><strong>Patrick Banda certification expiring.</strong><br>WFR certification expires in 47 days. Schedule renewal before May 30.</p><button class="tcc-btn tcc-btn-secondary" style="width:100%;font-size:0.7rem;justify-content:center;" onclick="switchTccModule('documents')">View Documents</button></div>

  <a href="#" onclick="tccNotify('Opening all AI suggestions…')" style="font-size:0.72rem;font-weight:700;color:var(--tcc-primary);text-decoration:none;display:block;margin-bottom:0.85rem;">View all suggestions →</a>

  <div style="padding-top:0.75rem;border-top:1px solid var(--tcc-border);">
    <label style="font-size:0.65rem;font-weight:800;color:var(--tcc-text-dim);text-transform:uppercase;display:block;margin-bottom:0.5rem;">Quick Actions</label>
    <div class="tcc-qa-grid">
      <div class="tcc-qa-tile" onclick="switchTccModule('builder')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tcc-primary)" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span>New Tour</span></div>
      <div class="tcc-qa-tile" onclick="switchTccModule('schedules')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tcc-green)" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg><span>Schedule</span></div>
      <div class="tcc-qa-tile" onclick="switchTccModule('advertisements')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tcc-purple)" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg><span>Create Ad</span></div>
      <div class="tcc-qa-tile" onclick="switchTccModule('guides')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tcc-amber)" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg><span>Assign Guide</span></div>
      <div class="tcc-qa-tile" onclick="switchTccModule('pricing')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tcc-cyan)" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Pricing</span></div>
      <div class="tcc-qa-tile" onclick="switchTccModule('analytics')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tcc-primary)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Reports</span></div>
      <div class="tcc-qa-tile" onclick="switchTccModule('messages')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tcc-rose)" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span>Messages</span></div>
      <div class="tcc-qa-tile" onclick="switchTccModule('media')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--tcc-green)" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg><span>Upload</span></div>
    </div>
  </div>

  <div style="margin-top:0.9rem;padding-top:0.75rem;border-top:1px solid var(--tcc-border);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
      <label style="font-size:0.65rem;font-weight:800;color:var(--tcc-text-dim);text-transform:uppercase;">Recent Notifications</label>
      <a href="#" onclick="switchTccModule('messages')" class="tcc-card-link" style="font-size:0.65rem;">All</a>
    </div>
    <div class="tcc-notif-feed">
      <div class="tcc-notif-item"><div class="tcc-notif-icon green"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div><div style="flex:1;"><strong>New booking received</strong><small>Lake Malawi Explorer · MK 180,000</small></div><span style="font-size:0.58rem;color:var(--tcc-text-muted);">2m</span></div>
      <div class="tcc-notif-item"><div class="tcc-notif-icon blue"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><div style="flex:1;"><strong>Payment received</strong><small>Booking #BK-4586 · MK 420,000</small></div><span style="font-size:0.58rem;color:var(--tcc-text-muted);">15m</span></div>
      <div class="tcc-notif-item"><div class="tcc-notif-icon amber"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg></div><div style="flex:1;"><strong>Guide unavailable</strong><small>John Kamanga · May 16</small></div><span style="font-size:0.58rem;color:var(--tcc-text-muted);">1h</span></div>
      <div class="tcc-notif-item"><div class="tcc-notif-icon purple"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div><div style="flex:1;"><strong>5★ review received</strong><small>Liwonde Safari · Grace Mwale</small></div><span style="font-size:0.58rem;color:var(--tcc-text-muted);">2h</span></div>
    </div>
  </div>
</aside>

</div><!-- End tcc-body -->

<!-- BOTTOM STATUS BAR -->
<footer class="tcc-bottom-bar">
  <div class="tcc-status-item">
    <div class="tcc-status-dot"></div>
    <span>System: <strong>All Operational</strong></span>
    <span>·</span>
    <span>Gateway: <strong>Airtel ✓ · TNM ✓ · Card ✓</strong></span>
  </div>
  <div class="tcc-status-item">
    <span id="tcc-live-time">--:-- --</span>
    <span>·</span>
    <span>Weather: <strong>23°C Partly Cloudy · Lilongwe</strong></span>
    <span>·</span>
    <span>Support: <strong>+265 999 123 456</strong></span>
  </div>
</footer>

</div><!-- End tcc-shell -->

<script>
(function() {
  'use strict';

  // Live clock & date
  function tccUpdateClock() {
    var now = new Date();
    var dateEl = document.getElementById('tcc-live-date');
    var timeEl = document.getElementById('tcc-live-time');
    if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', {weekday:'short', year:'numeric', month:'short', day:'numeric'});
    if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
  }
  tccUpdateClock();
  setInterval(tccUpdateClock, 30000);

  // Toast notification
  window.tccNotify = function(msg) {
    var toast = document.createElement('div');
    toast.style.cssText = 'position:fixed;bottom:2.5rem;right:1.5rem;z-index:9999;background:#0a1120;border:1px solid #2563eb;color:#fff;padding:0.7rem 1.2rem;border-radius:100px;font-size:0.78rem;font-weight:700;box-shadow:0 10px 30px rgba(0,0,0,0.3);font-family:Inter,sans-serif;animation:tcc-fade 0.2s ease;';
    toast.textContent = '✓ ' + msg;
    document.body.appendChild(toast);
    setTimeout(function() { if(toast.parentNode) toast.remove(); }, 3000);
  };

  // Module switcher
  window.switchTccModule = function(modId) {
    document.querySelectorAll('.tcc-nav-item').forEach(function(item) {
      item.classList.toggle('active', item.getAttribute('data-mod') === modId);
    });
    document.querySelectorAll('.tcc-module-content').forEach(function(c) {
      c.classList.remove('active');
    });
    var target = document.getElementById('mod-' + modId);
    if (target) {
      target.classList.add('active');
      var ws = document.querySelector('.tcc-workspace');
      if (ws) ws.scrollTop = 0;
    }
  };

  // Accordion toggle
  window.tccToggleAccordion = function(head) {
    var item = head.closest('.tcc-accordion-item');
    if (item) item.classList.toggle('open');
  };

  // Expandable booking detail rows
  window.tccToggleBooking = function(id) {
    var row = document.getElementById(id);
    if (row) row.classList.toggle('open');
  };

  // Timeline departure expand
  window.tccExpandDeparture = function(el) {
    var detail = el.querySelector('.tcc-timeline-detail');
    if (detail) {
      detail.style.display = detail.style.display === 'none' ? 'block' : 'none';
    }
  };

  // Day selector for itinerary
  window.tccSelectDay = function(tab, dayId) {
    document.querySelectorAll('.tcc-day-tab').forEach(function(t) { t.classList.remove('active'); });
    tab.classList.add('active');
    var days = ['itinerary-day1', 'itinerary-day2', 'itinerary-day3'];
    days.forEach(function(d) {
      var el = document.getElementById(d);
      if (el) el.style.display = 'none';
    });
    var target = document.getElementById(dayId);
    if (target) target.style.display = 'block';
    else tccNotify('Day content coming soon!');
  };

  // Settings section switcher
  window.tccSelectSettingsSection = function(id, clickedNav) {
    document.querySelectorAll('.tcc-settings-nav-item').forEach(function(n) { n.classList.remove('active'); });
    if (clickedNav) clickedNav.classList.add('active');
    document.querySelectorAll('.tcc-settings-section').forEach(function(s) { s.classList.remove('active'); });
    var section = document.getElementById('settings-' + id);
    if (section) section.classList.add('active');
    else tccNotify('Section loading…');
  };

  // Keyboard shortcut Ctrl+K for search
  document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      var s = document.getElementById('tcc-global-search');
      if (s) s.focus();
    }
  });

})();
</script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
