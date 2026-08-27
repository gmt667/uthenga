<?php
/**
 * Uthenga - Customer Dashboard (Travel Operating System Command Center)
 * Enterprise-grade authenticated workspace for travelers.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

requireCustomer();

$pageTitle = 'Customer Dashboard';
$activeNav = 'dashboard';

// users.id is VARCHAR(30) (e.g. "c-1"), never an integer — (int) casting it
// silently produces 0, which matches no real customer and made every query
// below (user lookup, bookings, payments, wishlist, support tickets, the
// settings save) always return empty against a real logged-in customer.
$userId = (string) ($_SESSION['user_id'] ?? '');
$user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]) ?: [];
$userName = (string)($user['name'] ?? $_SESSION['user_name'] ?? 'Patrick Mwale');
$userFirstName = explode(' ', trim($userName))[0] ?: 'Patrick';
$userEmail = (string)($user['email'] ?? $_SESSION['user_email'] ?? 'patrick@uthenga.mw');
$userPhone = (string)($user['phone'] ?? '+265 999 123 456');
$userAvatar = (string)($user['avatar'] ?? $_SESSION['user_avatar'] ?? BASE_URL . 'assets/images/avatars/christopher.svg');

$activeTab = $_GET['tab'] ?? 'overview';

// Check database table capabilities
$hasBookingItems = uthenga_table_exists('booking_items');
$hasSupportTickets = uthenga_table_exists('support_tickets');
$hasLoyaltyTransactions = uthenga_table_exists('loyalty_transactions');

// Marketplace listings for the Accommodation & Events tabs
$accomListings = marketplace_fetch_properties('', 0, false);
$eventListings = marketplace_fetch_events('', 0, false);
$tourListings = marketplace_fetch_tours('', 0, false);
require_once __DIR__ . '/includes/shop_helpers.php';
$shopProducts = uthenga_shop_products(['in_stock' => true, 'limit' => 12]);

// 1. Fetch Bookings
$bookingItemJoin = $hasBookingItems ? "
 LEFT JOIN (
     SELECT booking_id,
            SUBSTRING_INDEX(GROUP_CONCAT(item_name ORDER BY id SEPARATOR '||'), '||', 1) AS item_name,
            SUBSTRING_INDEX(GROUP_CONCAT(item_type ORDER BY id SEPARATOR '||'), '||', 1) AS item_type
     FROM booking_items
     GROUP BY booking_id
 ) ref ON ref.booking_id = b.id" : '';

$recentBookings = dbQuery(
    "SELECT b.*, COALESCE(" . ($hasBookingItems ? "ref.item_name, " : "") . "b.reference_name, b.booking_code) AS booking_title,
            COALESCE(" . ($hasBookingItems ? "ref.item_type, " : "") . "'booking') AS booking_type
     FROM bookings b
     {$bookingItemJoin}
     WHERE b.customer_id = ?
     ORDER BY b.created_at DESC LIMIT 5",
    [$userId]
);

$allBookings = dbQuery(
    "SELECT b.*, COALESCE(" . ($hasBookingItems ? "ref.item_name, " : "") . "b.reference_name, b.booking_code) AS booking_title,
            COALESCE(" . ($hasBookingItems ? "ref.item_type, " : "") . "'booking') AS booking_type
     FROM bookings b
     {$bookingItemJoin}
     WHERE b.customer_id = ?
     ORDER BY b.created_at DESC",
    [$userId]
);

// 2. Fetch Support Tickets
$supportTickets = $hasSupportTickets
    ? dbQuery('SELECT * FROM support_tickets WHERE requester_user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC', [$userId])
    : [];

// 3. Fetch Wishlist / Saved Items
// Fetch wishlist / saved items safely
$wishlist = [];
try {
    if (uthenga_table_exists('favorites')) {
        $wishlist = dbQuery('SELECT f.*, l.title, l.type, l.price FROM favorites f LEFT JOIN listings l ON l.id = f.listing_id WHERE f.user_id = ? ORDER BY f.created_at DESC LIMIT 20', [$userId]) ?: [];
    }
} catch (Throwable $wishlistErr) {
    $wishlist = [];
}

// 4. Fetch Payments / Transactions
$payments = dbQuery(
    "SELECT t.*, COALESCE(t.gateway_name, 'PayChangu') AS gateway_label,
            COALESCE(t.transaction_reference, CONCAT('REC-', t.id)) AS receipt_number,
            b.reference_name AS booking_title
     FROM transactions t
     LEFT JOIN bookings b ON b.id = t.booking_id
     WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 10",
    [$userId]
);

$totalSpent = (float)(dbQueryOne(
    "SELECT COALESCE(SUM(grand_total),0) AS t FROM bookings WHERE customer_id = ? AND LOWER(payment_status) IN ('paid','success','authorized','partially_paid')",
    [$userId]
)['t'] ?? 0);

// Loyalty Points Balance
$loyaltyPoints = $hasLoyaltyTransactions ? (int)(dbQueryOne(
    'SELECT COALESCE(SUM(points), 0) AS bal FROM loyalty_transactions WHERE user_id = ?',
    [$userId]
)['bal'] ?? $user['loyalty_points'] ?? 2450) : (int)($user['loyalty_points'] ?? 2450);

// Default Wallet Balance
$walletBalance = isset($user['wallet_balance']) ? (float)$user['wallet_balance'] : 125600.00;
$pendingRefunds = 18000.00;

// Profile Form Update Handler
$profileUpdateSuccess = '';
$profileUpdateError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_dashboard_profile'])) {
    if (!validateCsrf()) {
        $profileUpdateError = 'Security error. Please refresh and try again.';
    } else {
        $name   = trim((string)($_POST['name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone  = trim((string)($_POST['phone'] ?? ''));
        $avatar = trim((string)($_POST['avatar'] ?? ''));

        if (strlen($name) < 2) {
            $profileUpdateError = 'Name must be at least 2 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileUpdateError = 'Please enter a valid email address.';
        } elseif ($phone === '') {
            $profileUpdateError = 'Please enter a phone number.';
        } else {
            dbExecute(
                'UPDATE users SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?',
                [$name, $email, $phone, $avatar !== '' ? $avatar : ($user['avatar'] ?? null), $userId]
            );
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;
            if ($avatar !== '') {
                $_SESSION['user_avatar'] = $avatar;
            }
            $user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]) ?: $user;
            $userName = $name;
            $userFirstName = explode(' ', trim($name))[0];
            $userEmail = $email;
            $userPhone = $phone;
            $profileUpdateSuccess = 'Profile updated successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e(uthenga_theme_preference()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= rawurlencode(APP_VERSION) ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/customer-dashboard.css?v=<?= rawurlencode(APP_VERSION) ?>">
  <script>
    (function () {
      try {
        var stored = localStorage.getItem('uthenga-theme');
        var theme = (stored === 'light' || stored === 'dark') ? stored : (document.documentElement.dataset.theme || 'light');
        document.documentElement.dataset.theme = theme;
      } catch (e) {}
    })();
  </script>
</head>
<body>

<div class="cd-body-wrapper">

  <!-- =========================================================================
       SIDEBAR NAVIGATION
       ========================================================================= -->
  <aside class="cd-sidebar" id="cd-sidebar" aria-label="Customer Workspace Sidebar">
    <div class="cd-sidebar-header">
      <a href="<?= BASE_URL ?>dashboard.php" class="cd-sidebar-brand">
        <?php $logoSize = 'sm'; $logoLink = false; require __DIR__ . '/includes/logo.php'; ?>
      </a>
    </div>

    <div class="cd-sidebar-menu">
      <div class="cd-menu-label">Main Workspace</div>

      <!-- Dashboard -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'overview' ? 'active' : '' ?>" data-tab="overview" onclick="switchTab('overview')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></span> Dashboard
      </a>

      <!-- Quick Taxi -->
      <a href="<?= BASE_URL ?>ai.php" class="cd-nav-item <?= $activeTab === 'quick-travel' ? 'active' : '' ?>" data-tab="quick-travel">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span> Quick Taxi
      </a>

      <!-- Trip Planner -->
      <a href="<?= BASE_URL ?>ai.php#/planner" class="cd-nav-item <?= $activeTab === 'trip-planner' ? 'active' : '' ?>" data-tab="trip-planner">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg></span> Trip Planner
      </a>

      <!-- Accommodation -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'accommodation' ? 'active' : '' ?>" data-tab="accommodation" onclick="switchTab('accommodation')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M3 21h18M3 7v14M21 7v14M6 11h4M14 11h4M6 15h4M14 15h4M9 21v-4h6v4M3 7l9-4 9 4"/></svg></span> Accommodation
      </a>

      <!-- Events -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'events' ? 'active' : '' ?>" data-tab="events" onclick="switchTab('events')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span> Events
      </a>

      <!-- Transport -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'transport' ? 'active' : '' ?>" data-tab="transport" onclick="switchTab('transport')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="11" rx="2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M17 11H7M3 11h18"/></svg></span> Transport
      </a>

      <!-- Tours -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'tours' ? 'active' : '' ?>" data-tab="tours" onclick="switchTab('tours')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/><circle cx="12" cy="12" r="3"/></svg></span> Tours
      </a>

      <!-- Shop -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'shop' ? 'active' : '' ?>" data-tab="shop" onclick="switchTab('shop')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span> Shop <span class="cd-badge-new">New</span>
      </a>

      <div class="cd-menu-label" style="margin-top:0.75rem;">My Records</div>

      <!-- Bookings -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'bookings' ? 'active' : '' ?>" data-tab="bookings" onclick="switchTab('bookings')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span> My Bookings
      </a>

      <!-- Tickets -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'tickets' ? 'active' : '' ?>" data-tab="tickets" onclick="switchTab('tickets')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/><line x1="9" y1="12" x2="15" y2="12"/></svg></span> My Tickets
      </a>

      <!-- Payments -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'payments' ? 'active' : '' ?>" data-tab="payments" onclick="switchTab('payments')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span> Payments
      </a>

      <!-- Saved -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'saved' ? 'active' : '' ?>" data-tab="saved" onclick="switchTab('saved')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></span> Saved
      </a>

      <!-- AI Assistant -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'ai' ? 'active' : '' ?>" data-tab="ai" onclick="switchTab('ai')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/><path d="M12 12L2.5 7.5"/><path d="M12 12v9.5"/></svg></span> AI Assistant
      </a>

      <!-- Messages -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'messages' ? 'active' : '' ?>" data-tab="messages" onclick="switchTab('messages')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span> Messages
      </a>

      <!-- Support -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'support' ? 'active' : '' ?>" data-tab="support" onclick="switchTab('support')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg></span> Support
      </a>

      <!-- Settings -->
      <a href="javascript:void(0)" class="cd-nav-item <?= $activeTab === 'settings' ? 'active' : '' ?>" data-tab="settings" onclick="switchTab('settings')">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span> Settings
      </a>

      <!-- Logout -->
      <a href="<?= BASE_URL ?>logout.php" class="cd-nav-item" style="color:#e63946;">
        <span class="cd-nav-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span> Logout
      </a>
    </div>

    <!-- Sidebar Invite & Earn Card -->
    <div class="cd-sidebar-promo">
      <div style="font-size:1.5rem;margin-bottom:0.4rem;">
        <svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:28px;height:28px;color:#f59e0b;"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
      </div>
      <div class="cd-sidebar-promo-title">Invite &amp; Earn</div>
      <div class="cd-sidebar-promo-desc">Share Uthenga with friends and earn MWK 5,000 travel credits.</div>
      <button type="button" class="cd-btn-promo" onclick="alert('Referral code copied to clipboard!')">Invite Friends</button>
    </div>
  </aside>

  <!-- =========================================================================
       MAIN CONTENT CONTAINER
       ========================================================================= -->
  <main class="cd-main-content">

    <!-- TOP HEADER NAVBAR -->
    <header class="cd-header">
      <div class="cd-header-left">
        <button type="button" class="cd-hamburger" id="cd-hamburger-btn" aria-label="Toggle navigation">☰</button>
        <div class="cd-search-box">
          <span class="cd-search-icon"><svg class="cd-svg-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
          <input type="search" class="cd-search-input" placeholder="Search places, stays, events, transport, tours..." id="cd-global-search">
          <span class="cd-search-shortcut">⌘K</span>
        </div>
      </div>

      <div class="cd-header-right">
        <!-- AI Status Indicator -->
        <a href="javascript:void(0)" onclick="switchTab('ai')" class="cd-ai-pill" title="AI Assistant Status">
          <span class="cd-ai-dot"></span>
          <span>✨ AI • Ready</span>
        </a>

        <!-- Theme Toggle -->
        <button type="button" class="cd-icon-btn" id="dashboard-theme-toggle" title="Toggle Light / Dark Mode">
          <svg class="cd-svg-icon" viewBox="0 0 24 24" id="theme-icon-svg"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>

        <!-- Notifications Bell -->
        <a href="#" class="cd-icon-btn" title="Notifications" onclick="alert('You have 8 unread notifications'); return false;">
          <svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="cd-icon-badge">8</span>
        </a>

        <!-- Messages Chat Bubble -->
        <a href="javascript:void(0)" onclick="switchTab('messages')" class="cd-icon-btn" title="Messages">
          <svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <span class="cd-icon-badge">3</span>
        </a>

        <!-- Wallet Balance Dropdown -->
        <div class="cd-wallet-pill" title="Wallet Balance" onclick="switchTab('payments')">
          <svg class="cd-svg-icon" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20"/></svg>
          <div class="cd-wallet-info">
            <strong>MWK <?= number_format($walletBalance) ?></strong>
            <small>Wallet ∨</small>
          </div>
        </div>

        <!-- User Profile -->
        <div class="cd-user-profile" onclick="switchTab('settings')">
          <img src="<?= e($userAvatar) ?>" alt="<?= e($userName) ?>" class="cd-avatar">
          <div class="cd-user-details">
            <span class="cd-user-name"><?= e($userName) ?></span>
            <span class="cd-user-role">Premium Member</span>
          </div>
        </div>

        <!-- Logout -->
        <a href="<?= BASE_URL ?>logout.php" class="cd-icon-btn" title="Logout">
          <svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
      </div>
    </header>

    <!-- WORKSPACE CANVAS -->
    <div class="cd-workspace">

      <!-- =====================================================================
           TAB 1: OVERVIEW SCREEN (DEFAULT WORKSPACE)
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'overview' ? 'active' : '' ?>" id="panel-overview">

        <!-- GREETING ROW (EMOJIS PRESERVED ON GREETING & WEATHER STATEMENT ONLY) -->
        <div class="cd-greeting-row">
          <div>
            <h1 class="cd-greeting-title">Good morning, <?= e($userFirstName) ?>! 👋</h1>
            <p class="cd-greeting-sub">Ready for another amazing journey across Malawi?</p>
          </div>

          <div class="cd-weather-card">
            <span style="font-size:1.5rem;">☀️</span>
            <div class="cd-weather-temp">24°C</div>
            <div class="cd-weather-meta">
              <strong>Blantyre, Malawi</strong>
              <small>Partly Cloudy · Wed, May 14</small>
            </div>
          </div>
        </div>

        <!-- GRID ROW 1: TODAY'S JOURNEY + QUICK ACTIONS + WALLET OVERVIEW -->
        <div class="cd-grid-row">

          <!-- WIDGET 1: Today's Journey -->
          <div class="cd-card cd-col-journey">
            <div class="cd-card-header">
              <div class="cd-card-title">
                Today's Journey
                <span class="cd-journey-badge">● On Time</span>
              </div>
              <a href="javascript:void(0)" onclick="switchTab('quick-travel')" class="cd-card-link">Live Tracker &rsaquo;</a>
            </div>

            <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=900&fit=crop&q=80" alt="Express Coach" class="cd-journey-hero-img">

            <div class="cd-journey-route">
              <div class="cd-route-stop">
                <strong>Blantyre</strong>
                <small>Chileka Bus Terminal</small>
              </div>
              <div class="cd-route-line"></div>
              <div class="cd-route-stop" style="text-align:right;">
                <strong>Zomba</strong>
                <small>Zomba Bus Station</small>
              </div>
            </div>

            <div class="cd-journey-metrics">
              <div class="cd-metric-item">
                <small>Departure</small>
                <strong>09:00 AM</strong>
              </div>
              <div class="cd-metric-item">
                <small>Seats</small>
                <strong>1 Adult</strong>
              </div>
              <div class="cd-metric-item">
                <small>Ref</small>
                <strong>TR-89342</strong>
              </div>
              <div class="cd-metric-item">
                <small>Starts In</small>
                <strong style="color:var(--cd-primary);">1h 18m</strong>
              </div>
            </div>

            <a href="javascript:void(0)" onclick="switchTab('quick-travel')" class="cd-journey-btn">
              View Journey Details &rarr;
            </a>
          </div>

          <!-- WIDGET 2: Quick Actions -->
          <div class="cd-card cd-col-actions">
            <div class="cd-card-header">
              <div class="cd-card-title">Quick Actions</div>
            </div>

            <div class="cd-actions-grid">
              <a href="javascript:void(0)" onclick="switchTab('transport')" class="cd-action-card">
                <div class="cd-action-icon blue"><svg class="cd-svg-icon" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="11" rx="2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg></div>
                <div class="cd-action-label">Book Transport</div>
              </a>
              <a href="javascript:void(0)" onclick="switchTab('accommodation')" class="cd-action-card">
                <div class="cd-action-icon orange"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M3 21h18M3 7v14M21 7v14M6 11h4M14 11h4"/></svg></div>
                <div class="cd-action-label">Find Accommodation</div>
              </a>
              <a href="javascript:void(0)" onclick="switchTab('events')" class="cd-action-card">
                <div class="cd-action-icon pink"><svg class="cd-svg-icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg></div>
                <div class="cd-action-label">Buy Event Ticket</div>
              </a>
              <a href="javascript:void(0)" onclick="switchTab('tours')" class="cd-action-card">
                <div class="cd-action-icon green"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3"/><circle cx="12" cy="12" r="3"/></svg></div>
                <div class="cd-action-label">Explore Tours</div>
              </a>
              <a href="javascript:void(0)" onclick="switchTab('shop')" class="cd-action-card">
                <div class="cd-action-icon purple"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></div>
                <div class="cd-action-label">Shop Drinks</div>
              </a>
              <a href="javascript:void(0)" onclick="switchTab('ai')" class="cd-action-card">
                <div class="cd-action-icon blue"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/></svg></div>
                <div class="cd-action-label">Talk to AI</div>
              </a>
            </div>
          </div>

          <!-- WIDGET 3: Wallet Overview -->
          <div class="cd-card cd-col-wallet">
            <div class="cd-card-header">
              <div class="cd-card-title">Wallet Overview</div>
              <a href="javascript:void(0)" onclick="switchTab('payments')" class="cd-card-link">View All &rsaquo;</a>
            </div>

            <div class="cd-wallet-balance-row">
              <div class="cd-balance-label">Available Balance</div>
              <div class="cd-balance-val">
                <span id="balance-num">MWK <?= number_format($walletBalance) ?></span>
                <span style="font-size:1.1rem;cursor:pointer;" onclick="toggleBalanceVisibility()"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
              </div>
              <a href="javascript:void(0)" onclick="switchTab('payments')" class="cd-btn-add-money">+ Add Money</a>
            </div>

            <div class="cd-wallet-sub-list">
              <div class="cd-wallet-sub-item">
                <small>Reward Points</small>
                <strong><?= number_format($loyaltyPoints) ?> pts</strong>
              </div>
              <div class="cd-wallet-sub-item">
                <small>Pending Refunds</small>
                <strong style="color:var(--cd-green);">MWK <?= number_format($pendingRefunds) ?></strong>
              </div>
              <div class="cd-wallet-sub-item" style="flex-direction:column;align-items:flex-start;gap:0.15rem;">
                <small>Last Transaction</small>
                <div style="font-size:0.78rem;font-weight:700;">Payment to Sunrise Lodge</div>
                <div style="font-size:0.75rem;color:var(--cd-primary);font-weight:800;">-MWK 90,000 <span style="font-weight:400;color:var(--cd-text-muted);">(May 13, 2025)</span></div>
              </div>
            </div>
          </div>

        </div><!-- /.cd-grid-row 1 -->

        <!-- GRID ROW 2: UPCOMING BOOKINGS + MY TICKETS + RECENT ACTIVITY + OFFERS -->
        <div class="cd-grid-row">

          <!-- WIDGET 5: Upcoming Bookings -->
          <div class="cd-card cd-col-bookings">
            <div class="cd-card-header">
              <div class="cd-card-title">Upcoming Bookings</div>
              <a href="javascript:void(0)" onclick="switchTab('bookings')" class="cd-card-link">View All &rsaquo;</a>
            </div>

            <div class="cd-booking-item">
              <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=200&fit=crop&q=80" class="cd-booking-img" alt="Hotel">
              <div class="cd-booking-info">
                <div class="cd-booking-title">Sunrise Lodge</div>
                <div class="cd-booking-sub">Zomba, Malawi · Hotel</div>
              </div>
              <div class="cd-booking-date">
                <strong>May 16</strong>
                <span class="cd-booking-status status-confirmed">Confirmed</span>
              </div>
            </div>

            <div class="cd-booking-item">
              <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=200&fit=crop&q=80" class="cd-booking-img" alt="Transport">
              <div class="cd-booking-info">
                <div class="cd-booking-title">Blantyre &rarr; Zomba</div>
                <div class="cd-booking-sub">Express Coach · Transport</div>
              </div>
              <div class="cd-booking-date">
                <strong>May 14</strong>
                <span class="cd-booking-status status-ontime">On Time</span>
              </div>
            </div>

            <div class="cd-booking-item">
              <img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=200&fit=crop&q=80" class="cd-booking-img" alt="Tour">
              <div class="cd-booking-info">
                <div class="cd-booking-title">Lake Malawi Explorer</div>
                <div class="cd-booking-sub">3 Days Adventure · Tour</div>
              </div>
              <div class="cd-booking-date">
                <strong>May 20</strong>
                <span class="cd-booking-status status-pending">Pending</span>
              </div>
            </div>
          </div>

          <!-- WIDGET 6: My Tickets (Stub UI) -->
          <div class="cd-card cd-col-tickets" style="background:none;border:none;padding:0;box-shadow:none;">
            <div class="cd-ticket-stub">
              <div class="cd-ticket-header">
                <span class="cd-ticket-tag">CONCERT</span>
                <span style="font-size:0.75rem;opacity:0.8;">Pass #UTH-8842</span>
              </div>

              <div>
                <div class="cd-ticket-title">Sauti Sol Live in Blantyre</div>
                <div class="cd-ticket-meta">
                  Saturday, May 24, 2025 · 06:00 PM<br>
                  Kamuzu Stadium, Blantyre
                </div>
              </div>

              <div class="cd-ticket-body">
                <div class="cd-ticket-seat-info">
                  <small>SEAT DETAILS</small>
                  <strong>VIP · Gate A · Row 3 · Seat 12</strong>
                </div>

                <div class="cd-qr-wrap">
                  <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=AUTH_TICKET_SAUTI_SOL_8842" alt="QR Code">
                </div>
              </div>

              <a href="javascript:void(0)" onclick="switchTab('tickets')" class="cd-journey-btn" style="margin-top:0.85rem;background:rgba(255,255,255,0.15);color:#ffffff;border:none;">
                View / Download Ticket &darr;
              </a>
            </div>
          </div>

          <!-- WIDGET 7: Recent Activity -->
          <div class="cd-card cd-col-activity">
            <div class="cd-card-header">
              <div class="cd-card-title">Recent Activity</div>
              <a href="javascript:void(0)" onclick="switchTab('bookings')" class="cd-card-link">View All &rsaquo;</a>
            </div>

            <div class="cd-activity-list">
              <div class="cd-activity-item">
                <div class="cd-activity-icon hotel"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M3 21h18M3 7v14M21 7v14"/></svg></div>
                <div class="cd-activity-details">
                  <strong>Hotel booked</strong>
                  <small>Sunrise Lodge, Zomba · May 13, 08:45 AM</small>
                </div>
              </div>

              <div class="cd-activity-item">
                <div class="cd-activity-icon payment"><svg class="cd-svg-icon" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
                <div class="cd-activity-details">
                  <strong>Payment successful</strong>
                  <small>MWK 90,000 paid · May 13, 08:45 AM</small>
                </div>
              </div>

              <div class="cd-activity-item">
                <div class="cd-activity-icon ticket"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg></div>
                <div class="cd-activity-details">
                  <strong>Ticket downloaded</strong>
                  <small>Sauti Sol Concert · May 12, 09:15 PM</small>
                </div>
              </div>

              <div class="cd-activity-item">
                <div class="cd-activity-icon hotel"><svg class="cd-svg-icon" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="11" rx="2"/></svg></div>
                <div class="cd-activity-details">
                  <strong>Transport booked</strong>
                  <small>Blantyre to Zomba · May 12, 07:30 PM</small>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /.cd-grid-row 2 -->

        <!-- GRID ROW 3: RECOMMENDED FOR YOU + PAYMENTS SHORTCUTS -->
        <div class="cd-grid-row">

          <!-- WIDGET 9: Recommended For You -->
          <div class="cd-card cd-col-recommendations">
            <div class="cd-card-header">
              <div>
                <div class="cd-card-title">Recommended For You</div>
                <div style="font-size:0.75rem;color:var(--cd-text-muted);">Based on your recent travel preferences</div>
              </div>
              <a href="javascript:void(0)" onclick="switchTab('accommodation')" class="cd-card-link">Explore More &rsaquo;</a>
            </div>

            <div class="cd-recom-grid">
              <a href="javascript:void(0)" onclick="switchTab('accommodation')" class="cd-recom-card">
                <img src="https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=400&fit=crop&q=80" class="cd-recom-img" alt="Kaya Mawa">
                <div class="cd-recom-body">
                  <div class="cd-recom-title">Kaya Mawa Lodge</div>
                  <div class="cd-recom-sub">Likoma Island</div>
                  <div class="cd-recom-footer">
                    <span class="cd-recom-price">MWK 185,000 /night</span>
                    <span class="cd-recom-rating">★ 4.8</span>
                  </div>
                </div>
              </a>

              <a href="javascript:void(0)" onclick="switchTab('tours')" class="cd-recom-card">
                <img src="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&fit=crop&q=80" class="cd-recom-img" alt="Mulanje">
                <div class="cd-recom-body">
                  <div class="cd-recom-title">Mount Mulanje Hike</div>
                  <div class="cd-recom-sub">2 Days Adventure</div>
                  <div class="cd-recom-footer">
                    <span class="cd-recom-price">From MWK 65,000</span>
                    <span class="cd-recom-rating">★ 4.7</span>
                  </div>
                </div>
              </a>

              <a href="javascript:void(0)" onclick="switchTab('events')" class="cd-recom-card">
                <img src="https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400&fit=crop&q=80" class="cd-recom-img" alt="Lake of Stars">
                <div class="cd-recom-body">
                  <div class="cd-recom-title">Lake of Stars Festival</div>
                  <div class="cd-recom-sub">Oct 10 - Oct 13, 2025</div>
                  <div class="cd-recom-footer">
                    <span class="cd-recom-price">MWK 45,000</span>
                    <span class="cd-recom-rating">★ 4.6</span>
                  </div>
                </div>
              </a>

              <a href="javascript:void(0)" onclick="switchTab('transport')" class="cd-recom-card">
                <img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=400&fit=crop&q=80" class="cd-recom-img" alt="Airport Taxi">
                <div class="cd-recom-body">
                  <div class="cd-recom-title">Private Airport Taxi</div>
                  <div class="cd-recom-sub">Blantyre Airport</div>
                  <div class="cd-recom-footer">
                    <span class="cd-recom-price">MWK 25,000</span>
                    <span class="cd-recom-rating">★ 4.9</span>
                  </div>
                </div>
              </a>
            </div>
          </div>

          <!-- WIDGET 10: Payment Shortcuts -->
          <div class="cd-card cd-col-shortcuts">
            <div class="cd-card-header">
              <div class="cd-card-title">Payment Shortcuts</div>
            </div>

            <div class="cd-shortcut-list">
              <a href="javascript:void(0)" onclick="switchTab('payments')" class="cd-shortcut-item">
                <div class="cd-shortcut-info">
                  <strong>Pay Booking Balance</strong>
                  <small>You have 1 pending payment</small>
                </div>
                <span>&rarr;</span>
              </a>

              <a href="javascript:void(0)" onclick="switchTab('payments')" class="cd-shortcut-item">
                <div class="cd-shortcut-info">
                  <strong>Transaction History</strong>
                  <small>View all payments &amp; refunds</small>
                </div>
                <span>&rarr;</span>
              </a>

              <a href="javascript:void(0)" onclick="switchTab('payments')" class="cd-shortcut-item">
                <div class="cd-shortcut-info">
                  <strong>Saved Payment Methods</strong>
                  <small>Manage cards &amp; wallets</small>
                </div>
                <span>&rarr;</span>
              </a>

              <a href="javascript:void(0)" onclick="switchTab('payments')" class="cd-shortcut-item">
                <div class="cd-shortcut-info">
                  <strong>My Invoices</strong>
                  <small>Download invoices &amp; receipts</small>
                </div>
                <span>&rarr;</span>
              </a>
            </div>
          </div>

        </div><!-- /.cd-grid-row 3 -->

      </div><!-- /#panel-overview -->


      <!-- =====================================================================
           TAB: ACCOMMODATION WORKSPACE
           ===================================================================== -->
      <!-- =====================================================================
           TAB: ACCOMMODATION WORKSPACE
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'accommodation' ? 'active' : '' ?>" id="panel-accommodation">
        <div class="cd-workspace-body">

          <!-- Workspace Header -->
          <div class="cd-ws-header">
            <div>
              <div class="cd-ws-title">Accommodation</div>
              <div class="cd-ws-sub">Find, compare and reserve stays across Malawi — hotels, lodges, guesthouses and resorts.</div>
            </div>
            <a href="javascript:void(0)" onclick="switchTab('ai')" class="cd-ai-pill">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/><path d="M12 12L2.5 7.5"/><path d="M12 12v9.5"/></svg>
              Ask AI for Hotel Recs
            </a>
          </div>

          <!-- Sub-navigation -->
          <nav class="cd-sub-nav-bar" id="accom-subnav">
            <button class="cd-sub-nav-btn active" onclick="switchSubTab('accom','explore')" id="accom-btn-explore">Explore</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('accom','recommended')" id="accom-btn-recommended">Recommended</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('accom','nearby')" id="accom-btn-nearby">Nearby</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('accom','saved')" id="accom-btn-saved">Saved Stays</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('accom','mystays')" id="accom-btn-mystays">My Stays</button>
          </nav>

          <!-- EXPLORE sub-panel -->
          <div class="cd-sub-panel active" id="accom-sub-explore">

            <!-- Search bar -->
            <div class="cd-search-section">
              <div class="cd-search-row">
                <div class="cd-field-group">
                  <label>Where</label>
                  <input type="text" class="cd-field-input" placeholder="Blantyre, Lilongwe, Zomba..." id="accom-filter-where">
                </div>
                <div class="cd-field-group">
                  <label>Check-in</label>
                  <input type="date" class="cd-field-input" id="accom-filter-checkin">
                </div>
                <div class="cd-field-group">
                  <label>Check-out</label>
                  <input type="date" class="cd-field-input" id="accom-filter-checkout">
                </div>
                <div class="cd-field-group">
                  <label>Guests</label>
                  <select class="cd-field-input" id="accom-filter-guests">
                    <option>1 Guest</option><option selected>2 Guests</option><option>3 Guests</option><option>4+ Guests</option>
                  </select>
                </div>
                <button class="cd-search-btn" onclick="searchAccom()">Search</button>
              </div>
            </div>

            <!-- Filter + Results -->
            <div class="cd-with-sidebar">

              <!-- Filter sidebar -->
              <aside class="cd-filter-panel">
                <h4>Price / Night</h4>
                <input type="range" class="cd-price-range" min="0" max="500000" value="200000" oninput="document.getElementById('accom-price-val').textContent='MK '+Number(this.value).toLocaleString()">
                <div style="font-size:.78rem;color:var(--cd-text-muted);margin-top:.35rem;">Up to <span id="accom-price-val">MK 200,000</span></div>

                <h4>Property Type</h4>
                <label class="cd-filter-check"><input type="checkbox" checked> Hotel</label>
                <label class="cd-filter-check"><input type="checkbox" checked> Lodge</label>
                <label class="cd-filter-check"><input type="checkbox"> Guesthouse</label>
                <label class="cd-filter-check"><input type="checkbox"> Resort</label>
                <label class="cd-filter-check"><input type="checkbox"> Apartment</label>

                <h4>Rating</h4>
                <label class="cd-filter-check"><input type="checkbox" checked> 4★ &amp; above</label>
                <label class="cd-filter-check"><input type="checkbox"> 3★ &amp; above</label>
                <label class="cd-filter-check"><input type="checkbox"> Any rating</label>

                <h4>Amenities</h4>
                <label class="cd-filter-check"><input type="checkbox" checked> Wi-Fi</label>
                <label class="cd-filter-check"><input type="checkbox"> Breakfast</label>
                <label class="cd-filter-check"><input type="checkbox"> Parking</label>
                <label class="cd-filter-check"><input type="checkbox"> Pool</label>
                <label class="cd-filter-check"><input type="checkbox"> Air Con</label>

                <div style="margin-top:1rem;">
                  <button class="cd-search-btn" style="width:100%;font-size:.8rem;" onclick="searchAccom()">Apply Filters</button>
                </div>
              </aside>

              <!-- Results grid -->
              <div>
                <div class="cd-section-hd">
                  <h3><?= count($accomListings) ?> Stays Found</h3>
                  <select class="cd-field-input" style="width:auto;font-size:.78rem;">
                    <option>Recommended</option><option>Price: Low to High</option><option>Price: High to Low</option><option>Rating</option>
                  </select>
                </div>

                <div class="cd-listing-grid" id="accom-results-grid">

                  <?php foreach ($accomListings as $p):
                    $pMeta   = json_decode($p['meta'] ?? '{}', true) ?: [];
                    $pRating = (float)($p['rating'] ?? 0);
                    $pAmen   = array_slice(array_values(array_filter((array)($pMeta['amenities'] ?? []))), 0, 3);
                    $pUrl    = $p['detail_url'] ?? '#';
                  ?>
                  <div class="cd-listing-card" onclick="AccommodationCheckout.open('<?= e($p['id']) ?>','<?= e(addslashes($p['title'])) ?>')">
                    <img src="<?= e($p['image']) ?>" alt="<?= e($p['title']) ?>" class="cd-listing-card-img" loading="lazy">
                    <div class="cd-listing-card-body">
                      <div class="cd-listing-card-top">
                        <div class="cd-listing-card-title"><?= e($p['title']) ?></div>
                        <button class="cd-listing-heart" onclick="event.stopPropagation();this.classList.toggle('active')">♡</button>
                      </div>
                      <div class="cd-listing-location">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?= e($p['location']) ?>
                      </div>
                      <div class="cd-listing-rating"><?= $pRating > 0 ? '★ ' . number_format($pRating, 1) : 'New listing' ?> <span style="color:var(--cd-text-muted);font-weight:400;"><?= e($p['type_label']) ?></span></div>
                      <?php if ($pAmen): ?>
                      <div class="cd-listing-amenities">
                        <?php foreach ($pAmen as $tag): ?><span class="cd-amenity-tag"><?= e($tag) ?></span><?php endforeach; ?>
                      </div>
                      <?php endif; ?>
                      <div class="cd-listing-price-row">
                        <div class="cd-listing-price"><?= e($p['price_label']) ?></div>
                        <span class="cd-listing-avail avail"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right:2px;"><polyline points="20 6 9 17 4 12"/></svg> Available</span>
                      </div>
                      <div class="cd-listing-actions">
                        <button class="cd-btn-outline" onclick="event.stopPropagation();AccommodationCheckout.open('<?= e($p['id']) ?>','<?= e(addslashes($p['title'])) ?>')">Details &amp; Book</button>
                        <button class="cd-btn-solid" onclick="event.stopPropagation();AccommodationCheckout.open('<?= e($p['id']) ?>','<?= e(addslashes($p['title'])) ?>')">Reserve</button>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>

                  <?php if (empty($accomListings)): ?>
                  <div style="grid-column:1/-1;text-align:center;padding:3rem 0;color:var(--cd-text-muted);">
                    <div style="font-size:2.5rem;margin-bottom:.75rem;">🏠</div>
                    <div style="font-weight:800;color:var(--cd-text);margin-bottom:.25rem;">No stays found</div>
                    <div style="font-size:.85rem;">Check back soon — accommodation providers publish new stays regularly.</div>
                  </div>
                  <?php endif; ?>

                </div><!-- /listing-grid -->

              </div><!-- /results col -->
            </div><!-- /with-sidebar -->
          </div><!-- /explore -->

          <!-- RECOMMENDED sub-panel -->
          <div class="cd-sub-panel" id="accom-sub-recommended">
            <div style="background:linear-gradient(135deg,rgba(37,99,235,.06),rgba(14,165,233,.04));border:1px solid rgba(37,99,235,.15);border-radius:var(--cd-radius-md);padding:1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.75rem;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--cd-blue)" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/><path d="M12 12L2.5 7.5"/></svg>
              <div><strong>AI Recommendations</strong> — Based on your travel history and preferences, here are the best-fit accommodations for you.</div>
            </div>
            <div class="cd-listing-grid">
              <div class="cd-listing-card">
                <img src="https://images.unsplash.com/photo-1582719508461-905c673771fd?w=600&q=80" alt="" class="cd-listing-card-img">
                <div class="cd-listing-card-body">
                  <div class="cd-listing-card-top"><div class="cd-listing-card-title">Kumbali Lodge</div><button class="cd-listing-heart"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></button></div>
                  <div class="cd-listing-location">Lilongwe, Malawi</div>
                  <div class="cd-listing-rating">★ 4.9 <span style="color:var(--cd-text-muted);font-weight:400;">(311 reviews)</span></div>
                  <div style="font-size:.75rem;color:var(--cd-blue);background:rgba(37,99,235,.08);border-radius:6px;padding:.35rem .6rem;margin-bottom:.5rem;">AI: Fits your budget and central to your planned events</div>
                  <div class="cd-listing-price-row"><div class="cd-listing-price">MK 95,000 <small>/night</small></div><span class="cd-listing-avail avail">✓ Available</span></div>
                  <div class="cd-listing-actions"><span class="text-xs text-muted" style="padding:.4rem 0;">Sample recommendation</span></div>
                </div>
              </div>
            </div>
          </div>

          <!-- NEARBY sub-panel -->
          <div class="cd-sub-panel" id="accom-sub-nearby">
            <div style="border:1.5px dashed var(--cd-border);border-radius:var(--cd-radius-md);padding:2rem;text-align:center;color:var(--cd-text-muted);">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4;margin-bottom:.75rem;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              <div style="font-weight:700;margin-bottom:.35rem;">Enable Location</div>
              <div style="font-size:.82rem;">Allow location access to discover stays near you.</div>
              <button class="cd-search-btn" style="margin-top:1rem;" onclick="alert('Requesting location access...')">Enable Location</button>
            </div>
          </div>

          <!-- SAVED sub-panel -->
          <div class="cd-sub-panel" id="accom-sub-saved">
            <div style="border:1.5px dashed var(--cd-border);border-radius:var(--cd-radius-md);padding:2.5rem;text-align:center;color:var(--cd-text-muted);">
              <div style="margin-bottom:.5rem;"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4;"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
              <div style="font-weight:700;">No saved stays yet</div>
              <div style="font-size:.82rem;margin-top:.25rem;">Click the heart icon on any property to save it here.</div>
            </div>
          </div>

          <!-- MY STAYS sub-panel -->
          <div class="cd-sub-panel" id="accom-sub-mystays">
            <div class="cd-act-tabs">
              <button class="cd-act-tab active" onclick="filterActTab(this,'stays','upcoming')">Upcoming</button>
              <button class="cd-act-tab" onclick="filterActTab(this,'stays','active')">Active</button>
              <button class="cd-act-tab" onclick="filterActTab(this,'stays','completed')">Completed</button>
              <button class="cd-act-tab" onclick="filterActTab(this,'stays','cancelled')">Cancelled</button>
            </div>
            <div style="border:1.5px dashed var(--cd-border);border-radius:var(--cd-radius-md);padding:2.5rem;text-align:center;color:var(--cd-text-muted);">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4;margin-bottom:.75rem;"><path d="M3 21h18M3 7v14M21 7v14M6 11h4M14 11h4M6 15h4M14 15h4M9 21v-4h6v4M3 7l9-4 9 4"/></svg>
              <div style="font-weight:700;">No upcoming stays</div>
              <div style="font-size:.82rem;margin-top:.25rem;">Your accommodation bookings will appear here.</div>
              <button class="cd-btn-solid" style="margin-top:1rem;" onclick="switchSubTab('accom','explore')">Find a Stay</button>
            </div>
          </div>

        </div><!-- /workspace-body -->
      </div><!-- /panel-accommodation -->


      <!-- =====================================================================
           TAB: EVENTS TICKETING WORKSPACE
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'events' ? 'active' : '' ?>" id="panel-events">
        <div class="cd-workspace-body">

          <div class="cd-ws-header">
            <div>
              <div class="cd-ws-title">Events &amp; Ticketing</div>
              <div class="cd-ws-sub">Discover concerts, festivals, sports, conferences and cultural events across Malawi.</div>
            </div>
            <a href="javascript:void(0)" onclick="switchTab('tickets')" class="cd-ai-pill">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg>
              My Tickets
            </a>
          </div>

          <!-- Sub-nav -->
          <nav class="cd-sub-nav-bar">
            <button class="cd-sub-nav-btn active" onclick="switchSubTab('events','discover')" id="events-btn-discover">Discover</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('events','categories')" id="events-btn-categories">Categories</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('events','mytickets')" id="events-btn-mytickets">My Tickets</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('events','saved')" id="events-btn-saved">Saved Events</button>
          </nav>

          <!-- DISCOVER sub-panel -->
          <div class="cd-sub-panel active" id="events-sub-discover">

            <!-- Search -->
            <div class="cd-search-section">
              <div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:.75rem;align-items:end;">
                <div class="cd-field-group">
                  <label>Search Events</label>
                  <input type="text" class="cd-field-input" placeholder="Concerts, festivals, sports, conferences...">
                </div>
                <div class="cd-field-group">
                  <label>Location</label>
                  <input type="text" class="cd-field-input" placeholder="All Malawi">
                </div>
                <div class="cd-field-group">
                  <label>Date</label>
                  <input type="date" class="cd-field-input">
                </div>
                <button class="cd-search-btn">Search</button>
              </div>
            </div>

            <!-- Category chips -->
            <div class="cd-category-chips">
              <span class="cd-category-chip active"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/></svg> All</span>
              <span class="cd-category-chip"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg> Music</span>
              <span class="cd-category-chip"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Festivals</span>
              <span class="cd-category-chip"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20M2 12h20"/></svg> Sports</span>
              <span class="cd-category-chip"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg> Business</span>
              <span class="cd-category-chip"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg> Cultural</span>
              <span class="cd-category-chip"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><path d="M12 2v20M17 7H7"/></svg> Religious</span>
              <span class="cd-category-chip"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 2 6 2 6 2s6 0 6-2v-5"/></svg> Education</span>
              <span class="cd-category-chip"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.92 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-1 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-4.96-4.49-9-10-9z"/></svg> Arts</span>
              <span class="cd-category-chip"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="width:12px;height:12px;margin-right:2px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Community</span>
            </div>

            <!-- Featured Events -->
            <div class="cd-section-hd"><h3>Featured Events</h3><span style="font-size:.78rem;color:var(--cd-text-muted);"><?= count($eventListings) ?> events on Uthenga</span></div>
            <div class="cd-event-grid">

              <?php
              $evBadgeMap = [
                  'concert' => 'concert', 'music' => 'music', 'gig' => 'concert',
                  'festival' => 'festival', 'fair' => 'festival',
                  'sport' => 'sports', 'football' => 'sports', 'netball' => 'sports', 'athletics' => 'sports', 'games' => 'sports', 'match' => 'sports',
                  'conference' => 'conference', 'summit' => 'conference', 'business' => 'conference', 'expo' => 'conference', 'exhibition' => 'conference',
                  'community' => 'community', 'charity' => 'community', 'networking' => 'community', 'meetup' => 'community',
              ];
              foreach ($eventListings as $ev):
                $evMeta  = json_decode($ev['meta'] ?? '{}', true) ?: [];
                $evCat   = (string)($evMeta['category'] ?? $ev['type_label'] ?? 'Event');
                $evBadge = 'music';
                $evCatLower = strtolower($evCat);
                foreach ($evBadgeMap as $kw => $cls) {
                    if (strpos($evCatLower, $kw) !== false) { $evBadge = $cls; break; }
                }
                $evDateStr = !empty($evMeta['date']) ? date('D, d M Y', strtotime((string)$evMeta['date'])) : '';
                $evTimeStr = !empty($evMeta['time']) ? trim((string)$evMeta['time']) : '';
                $evUrl = $ev['detail_url'] ?? '#';
              ?>
              <div class="cd-event-card" onclick="EventCheckout.open('<?= e($ev['id']) ?>','<?= e(addslashes($ev['title'])) ?>')">
                <img src="<?= e($ev['image']) ?>" alt="<?= e($ev['title']) ?>" class="cd-event-card-img" loading="lazy">
                <div class="cd-event-card-body">
                  <span class="cd-event-badge <?= $evBadge ?>" style="margin-bottom:.5rem;display:inline-block;"><?= e($evCat) ?></span>
                  <div style="font-size:1rem;font-weight:800;margin-bottom:.3rem;"><?= e($ev['title']) ?></div>
                  <div style="font-size:.78rem;color:var(--cd-text-muted);margin-bottom:.5rem;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?= e($evDateStr) ?><?= $evTimeStr !== '' ? ' • ' . e($evTimeStr) : '' ?>
                  </div>
                  <div style="font-size:.78rem;color:var(--cd-text-muted);margin-bottom:.75rem;">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?= e($ev['location']) ?>
                  </div>
                  <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="font-size:.95rem;font-weight:800;color:var(--cd-primary);"><?= e($ev['price_label']) ?></div>
                    <button class="cd-btn-solid" style="padding:.4rem .85rem;font-size:.78rem;" onclick="event.stopPropagation();EventCheckout.open('<?= e($ev['id']) ?>','<?= e(addslashes($ev['title'])) ?>')">Get Tickets</button>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>

              <?php if (empty($eventListings)): ?>
              <div style="grid-column:1/-1;text-align:center;padding:3rem 0;color:var(--cd-text-muted);">
                <div style="font-size:2.5rem;margin-bottom:.75rem;">🎟️</div>
                <div style="font-weight:800;color:var(--cd-text);margin-bottom:.25rem;">No events yet</div>
                <div style="font-size:.85rem;">Organizers are preparing new events — check back soon.</div>
              </div>
              <?php endif; ?>

            </div><!-- /event-grid -->

          </div><!-- /discover -->

          <!-- CATEGORIES sub-panel -->
          <div class="cd-sub-panel" id="events-sub-categories">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:1rem;">
              <?php foreach (['🎶 Music','🎪 Festivals','⚽ Sports','💼 Business','🎭 Cultural','🙏 Religious','🎓 Education','🎨 Arts','🏘 Community','🎬 Entertainment'] as $cat): ?>
              <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.25rem;text-align:center;cursor:pointer;transition:all .2s;" onmouseover="this.style.borderColor='var(--cd-primary)'" onmouseout="this.style.borderColor='var(--cd-border)'">
                <div style="font-size:1.5rem;margin-bottom:.35rem;"><?= explode(' ',$cat)[0] ?></div>
                <div style="font-size:.82rem;font-weight:700;"><?= implode(' ',array_slice(explode(' ',$cat),1)) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- MY TICKETS sub-panel -->
          <div class="cd-sub-panel" id="events-sub-mytickets">
            <div class="cd-act-tabs">
              <button class="cd-act-tab active">Upcoming</button>
              <button class="cd-act-tab">Used</button>
              <button class="cd-act-tab">Expired</button>
              <button class="cd-act-tab">Cancelled</button>
            </div>
            <div style="border:1.5px dashed var(--cd-border);border-radius:var(--cd-radius-md);padding:2.5rem;text-align:center;color:var(--cd-text-muted);">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4;margin-bottom:.75rem;"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/></svg>
              <div style="font-weight:700;">No upcoming tickets</div>
              <div style="font-size:.82rem;margin-top:.25rem;">Your event tickets will appear here after purchase.</div>
              <button class="cd-btn-solid" style="margin-top:1rem;" onclick="switchSubTab('events','discover')">Browse Events</button>
            </div>
          </div>

          <!-- SAVED sub-panel -->
          <div class="cd-sub-panel" id="events-sub-saved">
            <div style="border:1.5px dashed var(--cd-border);border-radius:var(--cd-radius-md);padding:2.5rem;text-align:center;color:var(--cd-text-muted);">
              <div style="font-size:2rem;margin-bottom:.5rem;">📌</div>
              <div style="font-weight:700;">No saved events</div>
              <div style="font-size:.82rem;margin-top:.25rem;">Save events to find them here quickly.</div>
            </div>
          </div>

        </div>
      </div><!-- /panel-events -->


      <!-- =====================================================================
           TAB: TRANSPORT HUB WORKSPACE
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'transport' ? 'active' : '' ?>" id="panel-transport">
        <div class="cd-workspace-body">

          <!-- TRANSPORT HUB HEADER & MODE NAVIGATION -->
          <div class="cd-ws-header" style="margin-bottom:1.25rem;">
            <div>
              <div class="cd-ws-title" id="tr-header-title">Transport</div>
              <div class="cd-ws-sub" id="tr-header-sub">Move around Malawi your way. Choose the best transport for your journey.</div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;">
              <button onclick="switchTransportMode('tickets')" style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:99px;padding:.4rem .9rem;font-size:.78rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.4rem;color:var(--cd-text);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                My Tickets <span style="background:#10b981;color:#fff;font-size:.62rem;font-weight:900;padding:.15rem .45rem;border-radius:99px;" id="tr-ticket-count-badge">2</span>
              </button>
              <div style="display:flex;align-items:center;gap:.4rem;background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:99px;padding:.4rem .85rem;font-size:.78rem;font-weight:600;cursor:pointer;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                Malawi
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
          </div>

          <!-- SUB-NAVIGATION BAR (IN-PLACE MODE TOGGLE) -->
          <div style="display:flex;gap:.5rem;border-bottom:1px solid var(--cd-border);padding-bottom:.65rem;margin-bottom:1.5rem;">
            <button onclick="switchTransportMode('home')" class="cd-sub-nav-btn active" id="tr-nav-home" style="border:none;background:transparent;padding:.4rem .9rem;font-size:.82rem;font-weight:800;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
              Transport Home
            </button>
            <button onclick="switchTransportMode('search')" class="cd-sub-nav-btn" id="tr-nav-search" style="border:none;background:transparent;padding:.4rem .9rem;font-size:.82rem;font-weight:800;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
              Search Buses
            </button>
            <button onclick="switchTransportMode('tickets')" class="cd-sub-nav-btn" id="tr-nav-tickets" style="border:none;background:transparent;padding:.4rem .9rem;font-size:.82rem;font-weight:800;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
              My Bus Tickets
            </button>
          </div>

          <!-- ===================================================================
               VIEW 1: TRANSPORT HOME (HUB HOME MODE)
               =================================================================== -->
          <div id="tr-view-home" class="tr-mode-view">

            <!-- TOP 3 PRIMARY ACTION CARDS -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.1rem;margin-bottom:1.5rem;">

              <!-- QUICK TAXI CARD -->
              <a href="<?= BASE_URL ?>ai.php#/driver" style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);display:flex;justify-content:space-between;align-items:stretch;text-decoration:none;color:inherit;overflow:hidden;transition:all .22s;min-height:140px;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 28px rgba(0,0,0,.08)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                <div style="flex:1;padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;">
                  <div>
                    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
                      <div style="width:28px;height:28px;border-radius:8px;background:rgba(99,102,241,.12);color:#6366f1;display:flex;align-items:center;justify-content:center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                      </div>
                      <span style="font-size:1rem;font-weight:800;">Quick Taxi</span>
                    </div>
                    <div style="font-size:.78rem;color:var(--cd-text-muted);line-height:1.45;margin-bottom:1rem;">Need a ride now?<br>Get a taxi from your current location.</div>
                  </div>
                  <div style="background:#6366f1;color:#fff;display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1rem;border-radius:9px;font-size:.78rem;font-weight:800;width:fit-content;">
                    Request Taxi
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                  </div>
                </div>
                <div style="width:145px;flex-shrink:0;overflow:hidden;position:relative;background:linear-gradient(135deg,#e0e7ff 0%,#c7d2fe 100%);">
                  <img src="https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=300&h=200&fit=crop&q=80" alt="Taxi" style="width:100%;height:100%;object-fit:cover;object-position:center;">
                </div>
              </a>

              <!-- BUS TICKETS CARD -->
              <div onclick="switchTransportMode('search')" style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);display:flex;justify-content:space-between;align-items:stretch;text-decoration:none;color:inherit;overflow:hidden;transition:all .22s;min-height:140px;cursor:pointer;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 28px rgba(0,0,0,.08)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                <div style="flex:1;padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;">
                  <div>
                    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
                      <div style="width:28px;height:28px;border-radius:8px;background:rgba(16,185,129,.12);color:#10b981;display:flex;align-items:center;justify-content:center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                      </div>
                      <span style="font-size:1rem;font-weight:800;">Bus Tickets</span>
                    </div>
                    <div style="font-size:.78rem;color:var(--cd-text-muted);line-height:1.45;margin-bottom:1rem;">Travel by bus<br>Search schedules, compare prices and buy your ticket.</div>
                  </div>
                  <div style="background:#10b981;color:#fff;display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1rem;border-radius:99px;font-size:.78rem;font-weight:800;width:fit-content;">
                    Find Bus Tickets
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                  </div>
                </div>
                <div style="width:145px;flex-shrink:0;overflow:hidden;position:relative;background:#d1fae5;">
                  <img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=300&h=200&fit=crop&q=80" alt="Bus" style="width:100%;height:100%;object-fit:cover;object-position:center;">
                </div>
              </div>

              <!-- PLAN A TRIP CARD -->
              <a href="<?= BASE_URL ?>ai.php#/planner" style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);display:flex;justify-content:space-between;align-items:stretch;text-decoration:none;color:inherit;overflow:hidden;transition:all .22s;min-height:140px;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 28px rgba(0,0,0,.08)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                <div style="flex:1;padding:1.25rem;display:flex;flex-direction:column;justify-content:space-between;">
                  <div>
                    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
                      <div style="width:28px;height:28px;border-radius:8px;background:rgba(59,130,246,.12);color:#3b82f6;display:flex;align-items:center;justify-content:center;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                      </div>
                      <span style="font-size:1rem;font-weight:800;">Plan a Trip</span>
                    </div>
                    <div style="font-size:.78rem;color:var(--cd-text-muted);line-height:1.45;margin-bottom:1rem;">Going somewhere for several days?<br>Plan your full journey with stays, tours and transport.</div>
                  </div>
                  <div style="background:#3b82f6;color:#fff;display:inline-flex;align-items:center;gap:.4rem;padding:.55rem 1rem;border-radius:99px;font-size:.78rem;font-weight:800;width:fit-content;">
                    Open Trip Planner
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                  </div>
                </div>
                <div style="width:145px;flex-shrink:0;overflow:hidden;position:relative;background:#dbeafe;">
                  <img src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=300&h=200&fit=crop&q=80" alt="Travel" style="width:100%;height:100%;object-fit:cover;object-position:center;">
                </div>
              </a>
            </div>

            <!-- SEARCH BUS TICKETS IN-PLACE FORM + PROMO BANNER -->
            <div style="display:grid;grid-template-columns:2.3fr 1fr;gap:1.1rem;margin-bottom:1.5rem;">

              <!-- SEARCH WIDGET -->
              <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.35rem;">
                <div style="display:flex;align-items:center;gap:.5rem;font-size:.95rem;font-weight:800;margin-bottom:1.1rem;">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                  Search Bus Tickets
                </div>
                <form onsubmit="event.preventDefault(); executeInPlaceBusSearch(this);">
                  <div style="display:grid;grid-template-columns:1fr 1fr 1.1fr 1fr auto;gap:.6rem;align-items:flex-end;margin-bottom:.9rem;">
                    <div>
                      <div style="font-size:.68rem;font-weight:700;color:var(--cd-text-muted);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.03em;">From</div>
                      <div style="position:relative;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" style="position:absolute;left:.6rem;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 6.9 8 11.7z"/></svg>
                        <select name="from" id="tr-input-from" class="cd-field-input" style="height:38px;font-size:.82rem;padding-left:1.6rem;">
                          <option value="Lilongwe">Lilongwe</option>
                          <option value="Blantyre">Blantyre</option>
                          <option value="Mzuzu">Mzuzu</option>
                          <option value="Zomba">Zomba</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <div style="font-size:.68rem;font-weight:700;color:var(--cd-text-muted);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.03em;">To</div>
                      <div style="position:relative;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" style="position:absolute;left:.6rem;top:50%;transform:translateY(-50%);pointer-events:none;"><circle cx="12" cy="10" r="3"/><path d="M12 21.7C17.3 17 20 13 20 10a8 8 0 1 0-16 0c0 3 2.7 6.9 8 11.7z"/></svg>
                        <select name="to" id="tr-input-to" class="cd-field-input" style="height:38px;font-size:.82rem;padding-left:1.6rem;">
                          <option value="Blantyre">Blantyre</option>
                          <option value="Lilongwe">Lilongwe</option>
                          <option value="Mzuzu">Mzuzu</option>
                          <option value="Zomba">Zomba</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <div style="font-size:.68rem;font-weight:700;color:var(--cd-text-muted);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.03em;">Departure Date</div>
                      <div style="position:relative;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" style="position:absolute;left:.6rem;top:50%;transform:translateY(-50%);pointer-events:none;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <input type="date" name="date" id="tr-input-date" class="cd-field-input" style="height:38px;font-size:.82rem;padding-left:1.6rem;">
                      </div>
                    </div>
                    <div>
                      <div style="font-size:.68rem;font-weight:700;color:var(--cd-text-muted);margin-bottom:.3rem;text-transform:uppercase;letter-spacing:.03em;">Passengers</div>
                      <div style="position:relative;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" style="position:absolute;left:.6rem;top:50%;transform:translateY(-50%);pointer-events:none;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <select name="passengers" id="tr-input-passengers" class="cd-field-input" style="height:38px;font-size:.82rem;padding-left:1.6rem;">
                          <option value="1">1 Adult</option>
                          <option value="2">2 Adults</option>
                          <option value="3">3 Adults</option>
                        </select>
                      </div>
                    </div>
                    <button type="submit" style="height:38px;padding:0 1.1rem;background:#10b981;color:#fff;border:none;border-radius:9px;font-size:.82rem;font-weight:800;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:.35rem;">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                      Search Buses
                    </button>
                  </div>
                </form>
                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;font-size:.75rem;">
                  <span style="font-weight:700;color:var(--cd-text-muted);">Quick searches:</span>
                  <button onclick="triggerQuickRouteSearch('Lilongwe','Blantyre')" style="background:var(--cd-surface-2);border:1px solid var(--cd-border);border-radius:20px;padding:.22rem .65rem;font-weight:700;color:var(--cd-text-soft);cursor:pointer;font-family:inherit;">Lilongwe → Blantyre</button>
                  <button onclick="triggerQuickRouteSearch('Lilongwe','Mzuzu')" style="background:var(--cd-surface-2);border:1px solid var(--cd-border);border-radius:20px;padding:.22rem .65rem;font-weight:700;color:var(--cd-text-soft);cursor:pointer;font-family:inherit;">Lilongwe → Mzuzu</button>
                  <button onclick="triggerQuickRouteSearch('Blantyre','Lilongwe')" style="background:var(--cd-surface-2);border:1px solid var(--cd-border);border-radius:20px;padding:.22rem .65rem;font-weight:700;color:var(--cd-text-soft);cursor:pointer;font-family:inherit;">Blantyre → Lilongwe</button>
                  <button onclick="triggerQuickRouteSearch('Mzuzu','Lilongwe')" style="background:var(--cd-surface-2);border:1px solid var(--cd-border);border-radius:20px;padding:.22rem .65rem;font-weight:700;color:var(--cd-text-soft);cursor:pointer;font-family:inherit;">Mzuzu → Lilongwe</button>
                </div>
              </div>

              <!-- PROMO BANNER WITH BUS PHOTO -->
              <div style="border-radius:var(--cd-radius-md);overflow:hidden;position:relative;min-height:160px;display:flex;flex-direction:column;justify-content:flex-end;">
                <img src="https://images.unsplash.com/photo-1570125909517-53cb21c89ff2?w=600&h=340&fit=crop&q=80" alt="Travel farther" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;">
                <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(10,15,40,.82) 0%,rgba(10,15,40,.3) 60%,transparent 100%);"></div>
                <div style="position:relative;z-index:2;padding:1.25rem;">
                  <div style="font-size:1.2rem;font-weight:900;color:#fff;line-height:1.2;margin-bottom:.3rem;">Travel farther<br>for less</div>
                  <div style="font-size:.72rem;color:rgba(255,255,255,.7);margin-bottom:1rem;">Great routes. Best prices.<br>Trusted partners.</div>
                  <button onclick="switchTransportMode('search')" style="background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);border-radius:8px;color:#fff;font-size:.75rem;font-weight:700;padding:.4rem .85rem;cursor:pointer;backdrop-filter:blur(4px);font-family:inherit;">Explore routes</button>
                </div>
              </div>
            </div>

            <!-- MAIN TWO-COLUMN LAYOUT -->
            <div style="display:grid;grid-template-columns:2.3fr 1fr;gap:1.1rem;align-items:start;">

              <!-- LEFT COLUMN -->
              <div>

                <!-- UPCOMING TRAVEL -->
                <div style="margin-bottom:1.5rem;">
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.9rem;">
                    <div style="display:flex;align-items:center;gap:.45rem;font-size:.9rem;font-weight:800;">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                      Upcoming Travel
                    </div>
                    <button onclick="switchTransportMode('tickets')" style="font-size:.75rem;color:var(--cd-primary);font-weight:700;background:none;border:none;cursor:pointer;font-family:inherit;">View all</button>
                  </div>

                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">

                    <!-- BUS TICKET CARD -->
                    <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1rem;display:flex;align-items:flex-start;justify-content:space-between;gap:.65rem;">
                      <div style="display:flex;gap:.65rem;align-items:flex-start;flex:1;">
                        <div style="width:36px;height:36px;border-radius:9px;background:rgba(16,185,129,.1);color:#10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        </div>
                        <div style="min-width:0;flex:1;">
                          <span style="background:rgba(16,185,129,.12);color:#10b981;font-size:.58rem;font-weight:900;padding:.18rem .42rem;border-radius:4px;text-transform:uppercase;letter-spacing:.04em;display:inline-block;margin-bottom:.3rem;">TOMORROW</span>
                          <div style="font-size:.92rem;font-weight:900;margin-bottom:.1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Lilongwe → Blantyre</div>
                          <div style="font-size:.7rem;color:var(--cd-text-muted);margin-bottom:.35rem;">Skyways Express • Executive Coach</div>
                          <div style="font-size:.68rem;font-weight:700;color:var(--cd-text-soft);display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem;">
                            <span>📅 18 Aug 2026</span>
                            <span>🕒 06:30</span>
                            <span>💺 Seat B14</span>
                          </div>
                          <button onclick="openDigitalStationPass('UTH-BUS-48291','Lilongwe','Blantyre','Skyways Express','18 Aug 2026','06:30','Christopher Ngoma','B14')" style="background:rgba(16,185,129,.1);color:#10b981;border:1px solid rgba(16,185,129,.3);border-radius:6px;padding:.25rem .55rem;font-size:.68rem;font-weight:800;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:.3rem;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="7" y="7" width="3" height="3"/><rect x="14" y="7" width="3" height="3"/></svg>
                            View Digital Station Pass
                          </button>
                        </div>
                      </div>
                    </div>

                    <!-- TAXI TO AIRPORT CARD -->
                    <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1rem;display:flex;align-items:center;justify-content:space-between;gap:.65rem;">
                      <div style="display:flex;gap:.65rem;align-items:center;">
                        <div style="width:36px;height:36px;border-radius:9px;background:rgba(99,102,241,.1);color:#6366f1;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                        </div>
                        <div>
                          <div style="font-size:.92rem;font-weight:900;margin-bottom:.12rem;">Taxi to Airport</div>
                          <div style="font-size:.7rem;color:var(--cd-text-muted);">Pickup at 08:15 • From Area 10</div>
                        </div>
                      </div>
                      <div style="text-align:right;flex-shrink:0;">
                        <span style="font-size:.6rem;font-weight:800;color:#6366f1;background:rgba(99,102,241,.08);padding:.16rem .42rem;border-radius:4px;display:inline-block;margin-bottom:.35rem;">Scheduled</span>
                        <div><a href="<?= BASE_URL ?>ai.php#/driver" style="font-size:.68rem;font-weight:700;color:var(--cd-primary);text-decoration:none;">View Details</a></div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- POPULAR BUS ROUTES -->
                <div>
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.9rem;">
                    <div style="display:flex;align-items:center;gap:.45rem;font-size:.9rem;font-weight:800;">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                      Popular Bus Routes
                    </div>
                    <button onclick="switchTransportMode('search')" style="font-size:.75rem;color:var(--cd-primary);font-weight:700;background:none;border:none;cursor:pointer;font-family:inherit;">View all</button>
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
                    <?php foreach ([
                      ['Lilongwe','Blantyre','MK 25,000',18,'https://images.unsplash.com/photo-1627554022247-c2d694e87b9c?w=500&h=220&fit=crop&q=80'],
                      ['Lilongwe','Mzuzu','MK 22,000',12,'https://images.unsplash.com/photo-1580674684081-7617fbf3d745?w=500&h=220&fit=crop&q=80'],
                      ['Blantyre','Lilongwe','MK 25,000',14,'https://images.unsplash.com/photo-1483729558449-99ef09a8c325?w=500&h=220&fit=crop&q=80'],
                      ['Mzuzu','Lilongwe','MK 22,000',9,'https://images.unsplash.com/photo-1502920514313-52581002a659?w=500&h=220&fit=crop&q=80'],
                    ] as $r): ?>
                    <div onclick="triggerQuickRouteSearch('<?= $r[0] ?>','<?= $r[1] ?>')" style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);overflow:hidden;text-decoration:none;color:inherit;display:flex;flex-direction:column;transition:all .22s;cursor:pointer;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 18px rgba(0,0,0,.07)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
                      <img src="<?= e($r[4]) ?>" alt="<?= e($r[0]) ?> to <?= e($r[1]) ?>" style="width:100%;height:105px;object-fit:cover;display:block;">
                      <div style="padding:.85rem;">
                        <div style="font-size:.88rem;font-weight:800;margin-bottom:.18rem;"><?= e($r[0]) ?> → <?= e($r[1]) ?></div>
                        <div style="font-size:.75rem;color:var(--cd-text-muted);margin-bottom:.6rem;">From <strong style="color:var(--cd-text);"><?= e($r[2]) ?></strong></div>
                        <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--cd-border);padding-top:.5rem;">
                          <div style="display:flex;align-items:center;gap:.3rem;font-size:.7rem;font-weight:700;color:#10b981;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <?= $r[3] ?> buses available
                          </div>
                          <div style="width:22px;height:22px;border-radius:50%;background:var(--cd-surface-2);border:1px solid var(--cd-border);display:flex;align-items:center;justify-content:center;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

              <!-- RIGHT SIDEBAR -->
              <div style="display:flex;flex-direction:column;gap:1rem;">

                <!-- WHY BOOK ON UTHENGA -->
                <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.1rem;">
                  <div style="font-size:.85rem;font-weight:800;margin-bottom:.8rem;display:flex;align-items:center;gap:.4rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    Why book on Uthenga?
                  </div>
                  <ul style="list-style:none;padding:0;margin:0 0 .8rem 0;font-size:.77rem;color:var(--cd-text-soft);display:flex;flex-direction:column;gap:.45rem;">
                    <li style="display:flex;align-items:flex-start;gap:.4rem;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" style="flex-shrink:0;margin-top:.1rem;"><polyline points="20 6 9 17 4 12"/></svg>
                      Verified transport operators
                    </li>
                    <li style="display:flex;align-items:flex-start;gap:.4rem;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" style="flex-shrink:0;margin-top:.1rem;"><polyline points="20 6 9 17 4 12"/></svg>
                      Secure payments
                    </li>
                    <li style="display:flex;align-items:flex-start;gap:.4rem;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" style="flex-shrink:0;margin-top:.1rem;"><polyline points="20 6 9 17 4 12"/></svg>
                      Instant e-ticket delivery
                    </li>
                    <li style="display:flex;align-items:flex-start;gap:.4rem;">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" style="flex-shrink:0;margin-top:.1rem;"><polyline points="20 6 9 17 4 12"/></svg>
                      Easy refunds &amp; support
                    </li>
                  </ul>
                </div>

                <!-- CHAT WITH TIE -->
                <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.1rem;display:flex;align-items:flex-start;gap:.75rem;">
                  <div style="flex:1;">
                    <div style="font-size:.85rem;font-weight:800;margin-bottom:.2rem;">Need help choosing?</div>
                    <p style="font-size:.73rem;color:var(--cd-text-muted);margin-bottom:.85rem;line-height:1.45;">Ask TIE, your travel assistant, for the best options.</p>
                    <a href="<?= BASE_URL ?>ai.php#/planner" style="display:inline-flex;align-items:center;gap:.3rem;font-size:.73rem;font-weight:700;color:var(--cd-primary);text-decoration:none;">
                      Chat with TIE
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                  </div>
                  <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><circle cx="15.5" cy="8.5" r="1.5"/><path d="M8.5 14.5s1 2 3.5 2 3.5-2 3.5-2"/></svg>
                  </div>
                </div>

                <!-- TRAVEL TIPS -->
                <div style="background:#fefce8;border:1px solid #fef08a;border-radius:var(--cd-radius-md);padding:1.1rem;color:#854d0e;">
                  <div style="font-weight:800;font-size:.82rem;margin-bottom:.3rem;display:flex;align-items:center;gap:.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ca8a04" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    Travel Tips
                  </div>
                  <p style="font-size:.77rem;margin-bottom:.5rem;line-height:1.45;">Book early for weekend trips to get the best seats and prices.</p>
                </div>

              </div>
            </div>

          </div><!-- /#tr-view-home -->


          <!-- ===================================================================
               VIEW 2: BUS SEARCH RESULTS (IN-PLACE BUS SEARCH MODE)
               =================================================================== -->
          <div id="tr-view-search" class="tr-mode-view" style="display:none;">

            <!-- STICKY SEARCH CONSOLE BAR -->
            <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.1rem;margin-bottom:1.5rem;box-shadow:0 4px 14px rgba(0,0,0,.04);">
              <form onsubmit="event.preventDefault(); updateActiveBusSearch();">
                <div style="display:grid;grid-template-columns:1.2fr 1.2fr 1fr 1fr auto;gap:.75rem;align-items:end;">
                  <div>
                    <label style="font-size:.68rem;font-weight:800;color:var(--cd-text-muted);display:block;margin-bottom:.25rem;text-transform:uppercase;">From</label>
                    <select id="srch-from" class="cd-field-input" style="height:38px;font-size:.85rem;font-weight:700;">
                      <option value="Lilongwe" selected>📍 Lilongwe</option>
                      <option value="Blantyre">📍 Blantyre</option>
                      <option value="Mzuzu">📍 Mzuzu</option>
                      <option value="Zomba">📍 Zomba</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-size:.68rem;font-weight:800;color:var(--cd-text-muted);display:block;margin-bottom:.25rem;text-transform:uppercase;">To</label>
                    <select id="srch-to" class="cd-field-input" style="height:38px;font-size:.85rem;font-weight:700;">
                      <option value="Blantyre" selected>📍 Blantyre</option>
                      <option value="Lilongwe">📍 Lilongwe</option>
                      <option value="Mzuzu">📍 Mzuzu</option>
                      <option value="Zomba">📍 Zomba</option>
                    </select>
                  </div>
                  <div>
                    <label style="font-size:.68rem;font-weight:800;color:var(--cd-text-muted);display:block;margin-bottom:.25rem;text-transform:uppercase;">Departure Date</label>
                    <input type="date" id="srch-date" class="cd-field-input" value="2026-08-18" style="height:38px;font-size:.85rem;font-weight:700;">
                  </div>
                  <div>
                    <label style="font-size:.68rem;font-weight:800;color:var(--cd-text-muted);display:block;margin-bottom:.25rem;text-transform:uppercase;">Passengers</label>
                    <select id="srch-passengers" class="cd-field-input" style="height:38px;font-size:.85rem;font-weight:700;">
                      <option value="1">👤 1 Adult</option>
                      <option value="2">👤 2 Adults</option>
                      <option value="3">👤 3 Adults</option>
                    </select>
                  </div>
                  <button type="submit" style="height:38px;padding:0 1.25rem;background:#10b981;color:#fff;border:none;border-radius:9px;font-size:.82rem;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:.4rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    Update Search
                  </button>
                </div>
              </form>
            </div>

            <!-- SEARCH RESULTS MAIN 2-COLUMN LAYOUT -->
            <div style="display:grid;grid-template-columns:260px 1fr;gap:1.25rem;align-items:start;">

              <!-- LEFT SIDEBAR FILTERS -->
              <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.25rem;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid var(--cd-border);">
                  <span style="font-size:.85rem;font-weight:800;display:flex;align-items:center;gap:.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    Filters
                  </span>
                  <button onclick="resetSearchFilters()" style="font-size:.7rem;color:var(--cd-primary);background:none;border:none;cursor:pointer;font-weight:700;font-family:inherit;">Reset</button>
                </div>

                <!-- Departure Time Filter -->
                <div style="margin-bottom:1.25rem;">
                  <div style="font-size:.78rem;font-weight:800;margin-bottom:.5rem;">Departure Time</div>
                  <div style="display:flex;flex-direction:column;gap:.4rem;font-size:.78rem;">
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox" checked onchange="filterBusResults()"> Morning (06:00 - 12:00)
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox" checked onchange="filterBusResults()"> Afternoon (12:00 - 18:00)
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox" checked onchange="filterBusResults()"> Evening (18:00 - 00:00)
                    </label>
                  </div>
                </div>

                <!-- Price Filter -->
                <div style="margin-bottom:1.25rem;">
                  <div style="font-size:.78rem;font-weight:800;margin-bottom:.5rem;">Price Range</div>
                  <div style="font-size:.72rem;color:var(--cd-text-muted);margin-bottom:.4rem;">Up to MK 30,000</div>
                  <input type="range" min="15000" max="35000" value="30000" step="1000" style="width:100%;accent-color:#10b981;">
                </div>

                <!-- Operator Filter -->
                <div style="margin-bottom:1.25rem;">
                  <div style="font-size:.78rem;font-weight:800;margin-bottom:.5rem;">Bus Operator</div>
                  <div style="display:flex;flex-direction:column;gap:.4rem;font-size:.78rem;">
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox" checked onchange="filterBusResults()"> Skyways Express
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox" checked onchange="filterBusResults()"> AXA Coach
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox" checked onchange="filterBusResults()"> Sososo Express
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox" checked onchange="filterBusResults()"> Executive Line
                    </label>
                  </div>
                </div>

                <!-- Amenities Filter -->
                <div>
                  <div style="font-size:.78rem;font-weight:800;margin-bottom:.5rem;">Amenities</div>
                  <div style="display:flex;flex-direction:column;gap:.4rem;font-size:.78rem;">
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox" checked> Air Conditioning
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox" checked> Free Wi-Fi
                    </label>
                    <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">
                      <input type="checkbox"> USB Power Outlets
                    </label>
                  </div>
                </div>
              </div>

              <!-- RIGHT: BUS RESULTS FEED -->
              <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                  <div style="font-size:.9rem;font-weight:800;" id="bus-results-title">
                    Available Buses: <span style="color:#10b981;">Lilongwe → Blantyre</span> (4 buses found)
                  </div>
                  <div style="font-size:.75rem;color:var(--cd-text-muted);">Sorted by: <strong>Departure Time</strong></div>
                </div>

                <!-- BUS RESULT CARD 1 -->
                <div class="bus-result-item" style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.25rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;transition:all .2s;" onmouseover="this.style.borderColor='#10b981'" onmouseout="this.style.borderColor='var(--cd-border)'">
                  <div style="display:flex;gap:1.1rem;align-items:center;flex:1;">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(16,185,129,.12);color:#10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </div>
                    <div>
                      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.2rem;">
                        <span style="font-size:.95rem;font-weight:900;">Skyways Express</span>
                        <span style="background:rgba(16,185,129,.12);color:#10b981;font-size:.62rem;font-weight:800;padding:.15rem .45rem;border-radius:4px;">★ 4.8</span>
                      </div>
                      <div style="font-size:.78rem;color:var(--cd-text-muted);margin-bottom:.4rem;">Executive Coach • AC • Free Wi-Fi • Reclining Seats</div>
                      <div style="font-size:.75rem;font-weight:700;display:flex;align-items:center;gap:1rem;color:var(--cd-text-soft);">
                        <span>Departure: <strong style="color:var(--cd-text);">07:00</strong> (Lilongwe Central)</span>
                        <span>Arrival: <strong style="color:var(--cd-text);">11:30</strong> (Blantyre Wenela)</span>
                        <span style="color:#ef4444;font-weight:800;">🔥 2 seats left</span>
                      </div>
                    </div>
                  </div>
                  <div style="text-align:right;flex-shrink:0;border-left:1px solid var(--cd-border);padding-left:1.25rem;">
                    <div style="font-size:1.15rem;font-weight:900;color:var(--cd-text);margin-bottom:.2rem;">MK 25,000</div>
                    <div style="font-size:.68rem;color:var(--cd-text-muted);margin-bottom:.65rem;">per passenger</div>
                    <button onclick="openBusDetailsDrawer('Skyways Express','07:00','11:30','Executive Coach','MK 25,000','Lilongwe Central','Blantyre Wenela','2')" style="background:#10b981;color:#fff;border:none;border-radius:8px;padding:.5rem 1.1rem;font-size:.78rem;font-weight:800;cursor:pointer;font-family:inherit;">
                      View Details
                    </button>
                  </div>
                </div>

                <!-- BUS RESULT CARD 2 -->
                <div class="bus-result-item" style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.25rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;transition:all .2s;" onmouseover="this.style.borderColor='#10b981'" onmouseout="this.style.borderColor='var(--cd-border)'">
                  <div style="display:flex;gap:1.1rem;align-items:center;flex:1;">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(59,130,246,.12);color:#3b82f6;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </div>
                    <div>
                      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.2rem;">
                        <span style="font-size:.95rem;font-weight:900;">AXA Coach Services</span>
                        <span style="background:rgba(59,130,246,.12);color:#3b82f6;font-size:.62rem;font-weight:800;padding:.15rem .45rem;border-radius:4px;">★ 4.6</span>
                      </div>
                      <div style="font-size:.78rem;color:var(--cd-text-muted);margin-bottom:.4rem;">Standard Coach • AC • On-board Refreshments</div>
                      <div style="font-size:.75rem;font-weight:700;display:flex;align-items:center;gap:1rem;color:var(--cd-text-soft);">
                        <span>Departure: <strong style="color:var(--cd-text);">09:30</strong> (Lilongwe Depot)</span>
                        <span>Arrival: <strong style="color:var(--cd-text);">14:15</strong> (Blantyre Depot)</span>
                        <span style="color:#10b981;font-weight:800;">8 seats left</span>
                      </div>
                    </div>
                  </div>
                  <div style="text-align:right;flex-shrink:0;border-left:1px solid var(--cd-border);padding-left:1.25rem;">
                    <div style="font-size:1.15rem;font-weight:900;color:var(--cd-text);margin-bottom:.2rem;">MK 22,000</div>
                    <div style="font-size:.68rem;color:var(--cd-text-muted);margin-bottom:.65rem;">per passenger</div>
                    <button onclick="openBusDetailsDrawer('AXA Coach Services','09:30','14:15','Standard Coach','MK 22,000','Lilongwe Depot','Blantyre Depot','8')" style="background:#10b981;color:#fff;border:none;border-radius:8px;padding:.5rem 1.1rem;font-size:.78rem;font-weight:800;cursor:pointer;font-family:inherit;">
                      View Details
                    </button>
                  </div>
                </div>

                <!-- BUS RESULT CARD 3 -->
                <div class="bus-result-item" style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.25rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;transition:all .2s;" onmouseover="this.style.borderColor='#10b981'" onmouseout="this.style.borderColor='var(--cd-border)'">
                  <div style="display:flex;gap:1.1rem;align-items:center;flex:1;">
                    <div style="width:48px;height:48px;border-radius:12px;background:rgba(139,92,246,.12);color:#8b5cf6;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </div>
                    <div>
                      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.2rem;">
                        <span style="font-size:.95rem;font-weight:900;">Sososo Express</span>
                        <span style="background:rgba(139,92,246,.12);color:#8b5cf6;font-size:.62rem;font-weight:800;padding:.15rem .45rem;border-radius:4px;">★ 4.9 VIP</span>
                      </div>
                      <div style="font-size:.78rem;color:var(--cd-text-muted);margin-bottom:.4rem;">VIP Luxury Coach • Premium AC • High-speed Wi-Fi • USB</div>
                      <div style="font-size:.75rem;font-weight:700;display:flex;align-items:center;gap:1rem;color:var(--cd-text-soft);">
                        <span>Departure: <strong style="color:var(--cd-text);">12:00</strong> (Lilongwe VIP Terminal)</span>
                        <span>Arrival: <strong style="color:var(--cd-text);">16:30</strong> (Blantyre Central)</span>
                        <span style="color:#10b981;font-weight:800;">5 seats left</span>
                      </div>
                    </div>
                  </div>
                  <div style="text-align:right;flex-shrink:0;border-left:1px solid var(--cd-border);padding-left:1.25rem;">
                    <div style="font-size:1.15rem;font-weight:900;color:var(--cd-text);margin-bottom:.2rem;">MK 28,000</div>
                    <div style="font-size:.68rem;color:var(--cd-text-muted);margin-bottom:.65rem;">per passenger</div>
                    <button onclick="openBusDetailsDrawer('Sososo Express','12:00','16:30','VIP Luxury Coach','MK 28,000','Lilongwe VIP Terminal','Blantyre Central','5')" style="background:#10b981;color:#fff;border:none;border-radius:8px;padding:.5rem 1.1rem;font-size:.78rem;font-weight:800;cursor:pointer;font-family:inherit;">
                      View Details
                    </button>
                  </div>
                </div>

              </div>
            </div>

          </div><!-- /#tr-view-search -->


          <!-- ===================================================================
               VIEW 3: MY TICKETS WORKSPACE (MY BUS TICKETS MODE)
               =================================================================== -->
          <div id="tr-view-tickets" class="tr-mode-view" style="display:none;">

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
              <div style="font-size:1.05rem;font-weight:900;">My Bus Tickets</div>
              <div style="display:flex;gap:.5rem;">
                <button class="cd-sub-nav-btn active" style="font-size:.75rem;padding:.3rem .75rem;border-radius:20px;border:none;cursor:pointer;">Upcoming (2)</button>
                <button class="cd-sub-nav-btn" style="font-size:.75rem;padding:.3rem .75rem;border-radius:20px;border:none;cursor:pointer;">Completed (4)</button>
              </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

              <!-- TICKET CARD 1 -->
              <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.35rem;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 4px 14px rgba(0,0,0,.04);">
                <div>
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid var(--cd-border);">
                    <div style="display:flex;align-items:center;gap:.5rem;">
                      <div style="width:32px;height:32px;border-radius:8px;background:rgba(16,185,129,.12);color:#10b981;display:flex;align-items:center;justify-content:center;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                      </div>
                      <span style="font-weight:900;font-size:.95rem;">Skyways Express</span>
                    </div>
                    <span style="background:rgba(16,185,129,.12);color:#10b981;font-size:.65rem;font-weight:900;padding:.2rem .5rem;border-radius:4px;text-transform:uppercase;">CONFIRMED</span>
                  </div>

                  <div style="font-size:1.1rem;font-weight:900;margin-bottom:.3rem;">Lilongwe → Blantyre</div>
                  <div style="font-size:.78rem;color:var(--cd-text-muted);margin-bottom:.85rem;">18 Aug 2026 at 06:30 • Passenger: Christopher Ngoma</div>

                  <div style="background:var(--cd-surface-2);border:1px solid var(--cd-border);border-radius:8px;padding:.75rem;font-size:.75rem;display:flex;justify-content:space-between;margin-bottom:1rem;">
                    <div><span style="color:var(--cd-text-muted);">Ticket ID:</span> <strong>UTH-BUS-48291</strong></div>
                    <div><span style="color:var(--cd-text-muted);">Seat:</span> <strong>B14</strong></div>
                    <div><span style="color:var(--cd-text-muted);">Fare:</span> <strong>MK 25,000</strong></div>
                  </div>
                </div>

                <div style="display:flex;gap:.5rem;">
                  <button onclick="openDigitalStationPass('UTH-BUS-48291','Lilongwe','Blantyre','Skyways Express','18 Aug 2026','06:30','Christopher Ngoma','B14')" style="flex:1;background:#10b981;color:#fff;border:none;border-radius:8px;padding:.55rem;font-size:.78rem;font-weight:800;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:.35rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="7" y="7" width="3" height="3"/><rect x="14" y="7" width="3" height="3"/></svg>
                    View Station Pass
                  </button>
                  <button onclick="alert('Opening Lilongwe Central Bus Depot map directions...')" style="background:var(--cd-surface-2);border:1px solid var(--cd-border);color:var(--cd-text);border-radius:8px;padding:.55rem .85rem;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;">
                    Directions
                  </button>
                </div>
              </div>

              <!-- TICKET CARD 2 -->
              <div style="background:var(--cd-surface);border:1px solid var(--cd-border);border-radius:var(--cd-radius-md);padding:1.35rem;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 4px 14px rgba(0,0,0,.04);">
                <div>
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid var(--cd-border);">
                    <div style="display:flex;align-items:center;gap:.5rem;">
                      <div style="width:32px;height:32px;border-radius:8px;background:rgba(139,92,246,.12);color:#8b5cf6;display:flex;align-items:center;justify-content:center;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                      </div>
                      <span style="font-weight:900;font-size:.95rem;">Sososo Express</span>
                    </div>
                    <span style="background:rgba(16,185,129,.12);color:#10b981;font-size:.65rem;font-weight:900;padding:.2rem .5rem;border-radius:4px;text-transform:uppercase;">CONFIRMED</span>
                  </div>

                  <div style="font-size:1.1rem;font-weight:900;margin-bottom:.3rem;">Blantyre → Mzuzu</div>
                  <div style="font-size:.78rem;color:var(--cd-text-muted);margin-bottom:.85rem;">25 Aug 2026 at 08:00 • Passenger: Christopher Ngoma</div>

                  <div style="background:var(--cd-surface-2);border:1px solid var(--cd-border);border-radius:8px;padding:.75rem;font-size:.75rem;display:flex;justify-content:space-between;margin-bottom:1rem;">
                    <div><span style="color:var(--cd-text-muted);">Ticket ID:</span> <strong>UTH-BUS-8F92K</strong></div>
                    <div><span style="color:var(--cd-text-muted);">Seat:</span> <strong>A04</strong></div>
                    <div><span style="color:var(--cd-text-muted);">Fare:</span> <strong>MK 28,000</strong></div>
                  </div>
                </div>

                <div style="display:flex;gap:.5rem;">
                  <button onclick="openDigitalStationPass('UTH-BUS-8F92K','Blantyre','Mzuzu','Sososo Express','25 Aug 2026','08:00','Christopher Ngoma','A04')" style="flex:1;background:#10b981;color:#fff;border:none;border-radius:8px;padding:.55rem;font-size:.78rem;font-weight:800;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:.35rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="7" y="7" width="3" height="3"/><rect x="14" y="7" width="3" height="3"/></svg>
                    View Station Pass
                  </button>
                  <button onclick="alert('Opening Blantyre Wenela Depot map directions...')" style="background:var(--cd-surface-2);border:1px solid var(--cd-border);color:var(--cd-text);border-radius:8px;padding:.55rem .85rem;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;">
                    Directions
                  </button>
                </div>
              </div>

            </div>

          </div><!-- /#tr-view-tickets -->

        </div>
      </div><!-- /panel-transport -->


      <!-- ===================================================================
           OVERLAY 1: BUS DETAILS SLIDE-OVER DRAWER
           =================================================================== -->
      <div id="bus-details-drawer" class="cd-drawer-overlay">
        <div class="cd-drawer-panel">
          <div style="padding:1.25rem;border-bottom:1px solid var(--cd-border);display:flex;align-items:center;justify-content:space-between;">
            <div>
              <div style="font-size:1.05rem;font-weight:900;" id="drw-operator">Skyways Express</div>
              <div style="font-size:.75rem;color:var(--cd-text-muted);" id="drw-class">Executive Coach</div>
            </div>
            <button onclick="closeBusDetailsDrawer()" style="background:var(--cd-surface-2);border:1px solid var(--cd-border);border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--cd-text);">✕</button>
          </div>

          <div style="padding:1.25rem;flex:1;overflow-y:auto;">

            <!-- Route Timeline -->
            <div style="background:var(--cd-surface-2);border:1px solid var(--cd-border);border-radius:12px;padding:1.1rem;margin-bottom:1.25rem;">
              <div style="display:flex;align-items:flex-start;gap:.85rem;margin-bottom:.85rem;">
                <div style="width:12px;height:12px;border-radius:50%;background:#10b981;margin-top:.2rem;"></div>
                <div>
                  <div style="font-size:.9rem;font-weight:900;" id="drw-deptime">07:00</div>
                  <div style="font-size:.8rem;font-weight:700;" id="drw-depstation">Lilongwe Central Depot</div>
                  <div style="font-size:.72rem;color:var(--cd-text-muted);">Boarding starts 20 mins before departure</div>
                </div>
              </div>
              <div style="border-left:2px dashed var(--cd-border);margin-left:5px;height:24px;margin-bottom:.85rem;"></div>
              <div style="display:flex;align-items:flex-start;gap:.85rem;">
                <div style="width:12px;height:12px;border-radius:50%;background:var(--cd-primary);margin-top:.2rem;"></div>
                <div>
                  <div style="font-size:.9rem;font-weight:900;" id="drw-arrtime">11:30</div>
                  <div style="font-size:.8rem;font-weight:700;" id="drw-arrstation">Blantyre Wenela Depot</div>
                  <div style="font-size:.72rem;color:var(--cd-text-muted);">Estimated arrival time</div>
                </div>
              </div>
            </div>

            <!-- TIE Recommendation Badge -->
            <div style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);border-radius:10px;padding:.85rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.65rem;">
              <span style="font-size:1.2rem;">🤖</span>
              <div style="font-size:.75rem;color:var(--cd-text-soft);line-height:1.45;">
                <strong style="color:#6366f1;">TIE Assistant Recommendation:</strong> Fastest executive bus on this route with a 98% on-time departure score.
              </div>
            </div>

            <!-- Vehicle Specs & Amenities -->
            <div style="margin-bottom:1.25rem;">
              <div style="font-size:.82rem;font-weight:800;margin-bottom:.5rem;">On-Board Amenities</div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;font-size:.78rem;color:var(--cd-text-soft);">
                <div style="display:flex;align-items:center;gap:.35rem;">✓ Air Conditioning</div>
                <div style="display:flex;align-items:center;gap:.35rem;">✓ Free High-Speed Wi-Fi</div>
                <div style="display:flex;align-items:center;gap:.35rem;">✓ Reclining Seats</div>
                <div style="display:flex;align-items:center;gap:.35rem;">✓ 20kg Free Luggage</div>
              </div>
            </div>

            <!-- Cancellation Policy -->
            <div style="background:var(--cd-surface-2);border-radius:10px;padding:.85rem;font-size:.75rem;color:var(--cd-text-muted);">
              <strong>Cancellation Policy:</strong> 100% full refund up to 2 hours before scheduled departure via instant Uthenga Wallet return.
            </div>

          </div>

          <div style="padding:1.25rem;border-top:1px solid var(--cd-border);display:flex;align-items:center;justify-content:space-between;">
            <div>
              <div style="font-size:.68rem;color:var(--cd-text-muted);text-transform:uppercase;font-weight:700;">Total Fare</div>
              <div style="font-size:1.25rem;font-weight:900;color:var(--cd-text);" id="drw-price">MK 25,000</div>
            </div>
            <button onclick="startBusCheckoutFromDrawer()" style="background:#10b981;color:#fff;border:none;border-radius:10px;padding:.75rem 1.4rem;font-size:.85rem;font-weight:900;cursor:pointer;font-family:inherit;">
              Continue to Purchase →
            </button>
          </div>
        </div>
      </div>


      <!-- ===================================================================
           OVERLAY 2: MULTI-STEP BUS CHECKOUT & ABSTRACTED PAYMENT MODAL
           =================================================================== -->
      <div id="bus-checkout-modal" class="cd-modal-overlay">
        <div class="cd-modal-container">
          <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--cd-border);display:flex;align-items:center;justify-content:space-between;">
            <div style="font-size:1.05rem;font-weight:900;">Bus Ticket Checkout</div>
            <button onclick="closeBusCheckoutModal()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--cd-text-muted);">✕</button>
          </div>

          <div style="padding:1.5rem;">

            <!-- STEP 1: PASSENGER & SEAT SELECTION -->
            <div id="chk-step-1">
              <div style="font-size:.88rem;font-weight:800;margin-bottom:.85rem;">1. Passenger Details &amp; Seat Selection</div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
                <div>
                  <label style="font-size:.72rem;font-weight:700;color:var(--cd-text-muted);display:block;margin-bottom:.25rem;">Full Name</label>
                  <input type="text" id="chk-name" class="cd-field-input" value="Christopher Ngoma" style="height:38px;font-size:.82rem;">
                </div>
                <div>
                  <label style="font-size:.72rem;font-weight:700;color:var(--cd-text-muted);display:block;margin-bottom:.25rem;">Phone Number</label>
                  <input type="text" id="chk-phone" class="cd-field-input" value="+265 99 847 2910" style="height:38px;font-size:.82rem;">
                </div>
              </div>

              <div style="font-size:.78rem;font-weight:800;margin-bottom:.4rem;">Select Your Seat</div>
              <div style="font-size:.72rem;color:var(--cd-text-muted);margin-bottom:.65rem;">Click an available seat on the coach map:</div>

              <div class="cd-seat-grid">
                <button onclick="selectSeat(this,'A01')" class="cd-seat-btn taken">A01</button>
                <button onclick="selectSeat(this,'A02')" class="cd-seat-btn taken">A02</button>
                <button onclick="selectSeat(this,'A03')" class="cd-seat-btn">A03</button>
                <button onclick="selectSeat(this,'A04')" class="cd-seat-btn">A04</button>

                <button onclick="selectSeat(this,'B11')" class="cd-seat-btn">B11</button>
                <button onclick="selectSeat(this,'B12')" class="cd-seat-btn">B12</button>
                <button onclick="selectSeat(this,'B13')" class="cd-seat-btn">B13</button>
                <button onclick="selectSeat(this,'B14')" class="cd-seat-btn selected">B14</button>
              </div>

              <button onclick="proceedToCheckoutStep(2)" style="width:100%;margin-top:1rem;background:#10b981;color:#fff;border:none;border-radius:10px;padding:.75rem;font-weight:900;cursor:pointer;font-family:inherit;font-size:.85rem;">
                Next: Review Fare →
              </button>
            </div>

            <!-- STEP 2: REVIEW & ABSTRACTED PAYMENT METHOD -->
            <div id="chk-step-2" style="display:none;">
              <div style="font-size:.88rem;font-weight:800;margin-bottom:.85rem;">2. Select Abstracted Payment Method</div>

              <!-- Ticket Summary Box -->
              <div style="background:var(--cd-surface-2);border:1px solid var(--cd-border);border-radius:12px;padding:1rem;margin-bottom:1.25rem;">
                <div style="font-size:.85rem;font-weight:900;margin-bottom:.2rem;" id="chk-sum-route">Lilongwe → Blantyre</div>
                <div style="font-size:.75rem;color:var(--cd-text-muted);margin-bottom:.5rem;" id="chk-sum-operator">Skyways Express • 18 Aug 2026 at 06:30 • Seat B14</div>
                <div style="display:flex;justify-content:space-between;font-size:.85rem;font-weight:900;border-top:1px solid var(--cd-border);padding-top:.5rem;">
                  <span>Total Amount Due</span>
                  <span style="color:#10b981;" id="chk-sum-total">MK 25,000</span>
                </div>
              </div>

              <!-- Abstracted Payment Radio Options (NO PayChangu branding) -->
              <div style="display:flex;flex-direction:column;gap:.65rem;margin-bottom:1.25rem;">

                <label style="border:1.5px solid #10b981;background:rgba(16,185,129,.05);border-radius:10px;padding:.85rem;display:flex;align-items:center;gap:.75rem;cursor:pointer;">
                  <input type="radio" name="paymethod" value="airtel" checked style="accent-color:#10b981;">
                  <div>
                    <div style="font-weight:800;font-size:.82rem;">Airtel Money</div>
                    <div style="font-size:.72rem;color:var(--cd-text-muted);">Instant mobile wallet prompt</div>
                  </div>
                </label>

                <label style="border:1px solid var(--cd-border);background:var(--cd-surface-2);border-radius:10px;padding:.85rem;display:flex;align-items:center;gap:.75rem;cursor:pointer;">
                  <input type="radio" name="paymethod" value="mpamba" style="accent-color:#10b981;">
                  <div>
                    <div style="font-weight:800;font-size:.82rem;">TNM Mpamba</div>
                    <div style="font-size:.72rem;color:var(--cd-text-muted);">Instant mobile wallet prompt</div>
                  </div>
                </label>

                <label style="border:1px solid var(--cd-border);background:var(--cd-surface-2);border-radius:10px;padding:.85rem;display:flex;align-items:center;gap:.75rem;cursor:pointer;">
                  <input type="radio" name="paymethod" value="bank" style="accent-color:#10b981;">
                  <div>
                    <div style="font-weight:800;font-size:.82rem;">Bank Transfer / Card</div>
                    <div style="font-size:.72rem;color:var(--cd-text-muted);">National Bank, Standard Bank, FDH</div>
                  </div>
                </label>

              </div>

              <div style="display:flex;gap:.65rem;">
                <button onclick="proceedToCheckoutStep(1)" style="background:var(--cd-surface-2);border:1px solid var(--cd-border);color:var(--cd-text);border-radius:10px;padding:.75rem 1rem;font-weight:700;cursor:pointer;font-family:inherit;font-size:.8rem;">
                  ← Back
                </button>
                <button onclick="confirmBusTicketPayment()" style="flex:1;background:#10b981;color:#fff;border:none;border-radius:10px;padding:.75rem;font-weight:900;cursor:pointer;font-family:inherit;font-size:.85rem;">
                  Pay &amp; Get Ticket (MK 25,000)
                </button>
              </div>
            </div>

            <!-- STEP 3: SUCCESS CONFIRMATION -->
            <div id="chk-step-3" style="display:none;text-align:center;padding:1rem 0;">
              <div style="width:56px;height:56px;border-radius:50%;background:rgba(16,185,129,.12);color:#10b981;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 1rem;">✓</div>
              <div style="font-size:1.1rem;font-weight:900;margin-bottom:.3rem;">Ticket Issued Successfully!</div>
              <div style="font-size:.78rem;color:var(--cd-text-muted);margin-bottom:1.5rem;">Your bus booking is confirmed. Your station boarding pass is ready.</div>

              <div style="display:flex;gap:.65rem;justify-content:center;">
                <button onclick="closeBusCheckoutModal(); openDigitalStationPass('UTH-BUS-99212','Lilongwe','Blantyre','Skyways Express','18 Aug 2026','07:00','Christopher Ngoma','B14');" style="background:#10b981;color:#fff;border:none;border-radius:10px;padding:.7rem 1.25rem;font-size:.82rem;font-weight:800;cursor:pointer;font-family:inherit;">
                  View Digital Station Pass
                </button>
                <button onclick="closeBusCheckoutModal(); switchTransportMode('tickets');" style="background:var(--cd-surface-2);border:1px solid var(--cd-border);color:var(--cd-text);border-radius:10px;padding:.7rem 1.25rem;font-size:.82rem;font-weight:800;cursor:pointer;font-family:inherit;">
                  Go to My Tickets
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>


      <!-- ===================================================================
           OVERLAY 3: DIGITAL BUS TICKET / STATION BOARDING PASS MODAL
           =================================================================== -->
      <div id="bus-station-pass-modal" class="cd-modal-overlay">
        <div style="width:100%;max-width:390px;">
          <div class="cd-station-pass-card">

            <div class="cd-station-pass-header">
              <div style="font-size:.7rem;font-weight:900;letter-spacing:.15em;text-transform:uppercase;opacity:.85;margin-bottom:.2rem;">UTHENGA TRANSPORT</div>
              <div style="font-size:1.15rem;font-weight:900;">BUS BOARDING PASS</div>
            </div>

            <div style="padding:1.5rem;">

              <div style="text-align:center;margin-bottom:1rem;">
                <div style="font-size:1.25rem;font-weight:900;color:#0f172a;" id="pass-route">LILONGWE → BLANTYRE</div>
                <div style="font-size:.78rem;color:#64748b;font-weight:700;" id="pass-operator">Skyways Express • Executive Coach</div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;margin-bottom:1rem;font-size:.78rem;">
                <div>
                  <div style="font-size:.65rem;color:#64748b;font-weight:800;text-transform:uppercase;">Departure Date</div>
                  <div style="font-weight:900;color:#0f172a;" id="pass-date">18 AUG 2026</div>
                </div>
                <div>
                  <div style="font-size:.65rem;color:#64748b;font-weight:800;text-transform:uppercase;">Time</div>
                  <div style="font-weight:900;color:#0f172a;" id="pass-time">06:30</div>
                </div>
                <div>
                  <div style="font-size:.65rem;color:#64748b;font-weight:800;text-transform:uppercase;">Passenger</div>
                  <div style="font-weight:900;color:#0f172a;" id="pass-name">Christopher Ngoma</div>
                </div>
                <div>
                  <div style="font-size:.65rem;color:#64748b;font-weight:800;text-transform:uppercase;">Seat No.</div>
                  <div style="font-weight:900;color:#10b981;font-size:1.05rem;" id="pass-seat">B14</div>
                </div>
              </div>

              <!-- STATION QR CODE BLOCK -->
              <div class="cd-station-barcode">
                <div style="font-size:.68rem;font-weight:800;color:#64748b;margin-bottom:.5rem;">SCAN FOR STATION BOARDING VERIFICATION</div>
                <div style="display:flex;justify-content:center;align-items:center;margin-bottom:.5rem;">
                  <!-- SVG QR / BARCODE SYMBOL -->
                  <svg width="140" height="140" viewBox="0 0 100 100" fill="#0f172a">
                    <rect x="5" y="5" width="25" height="25" rx="3" fill="#0f172a"/><rect x="10" y="10" width="15" height="15" fill="#fff"/><rect x="13" y="13" width="9" height="9" fill="#0f172a"/>
                    <rect x="70" y="5" width="25" height="25" rx="3" fill="#0f172a"/><rect x="75" y="10" width="15" height="15" fill="#fff"/><rect x="78" y="13" width="9" height="9" fill="#0f172a"/>
                    <rect x="5" y="70" width="25" height="25" rx="3" fill="#0f172a"/><rect x="10" y="75" width="15" height="15" fill="#fff"/><rect x="13" y="78" width="9" height="9" fill="#0f172a"/>
                    <rect x="35" y="10" width="6" height="20"/><rect x="45" y="5" width="15" height="6"/><rect x="40" y="35" width="20" height="20"/><rect x="65" y="40" width="10" height="10"/>
                    <rect x="35" y="70" width="15" height="15"/><rect x="55" y="75" width="20" height="10"/><rect x="80" y="70" width="15" height="15"/>
                  </svg>
                </div>
                <div style="font-family:monospace;font-size:.9rem;font-weight:900;letter-spacing:.1em;color:#0f172a;" id="pass-id">UTH-BUS-48291</div>
                <div style="font-size:.65rem;color:#64748b;margin-top:.2rem;">Verification Code: <strong>8942</strong></div>
              </div>

              <div style="display:flex;gap:.5rem;margin-top:1.25rem;">
                <button onclick="alert('Verification Code: 8942. Show this code to the bus conductor at boarding.')" style="flex:1;background:#f1f5f9;color:#0f172a;border:1px solid #cbd5e1;border-radius:8px;padding:.55rem;font-size:.75rem;font-weight:800;cursor:pointer;font-family:inherit;">
                  Show Verification Code
                </button>
                <button onclick="closeDigitalStationPass()" style="background:#0f172a;color:#fff;border:none;border-radius:8px;padding:.55rem 1rem;font-size:.75rem;font-weight:800;cursor:pointer;font-family:inherit;">
                  Close
                </button>
              </div>

            </div>
          </div>
        </div>
      </div>


      <!-- IN-PLACE TRANSPORT WORKSPACE CONTROLLER SCRIPT -->
      <script>
      function switchTransportMode(mode) {
        if (mode === 'search') {
          // Real bus search/purchase now happens in the BusCheckout modal
          // (real departures, real seats, real payment) instead of the old
          // in-place fake results view below.
          BusCheckout.open();
          var fromEl = document.getElementById('tr-input-from');
          var toEl = document.getElementById('tr-input-to');
          var dateEl = document.getElementById('tr-input-date');
          if (fromEl && fromEl.value) document.getElementById('bus-search-from').value = fromEl.value;
          if (toEl && toEl.value) document.getElementById('bus-search-to').value = toEl.value;
          if (dateEl && dateEl.value) document.getElementById('bus-search-date').value = dateEl.value;
          return;
        }
        document.querySelectorAll('.tr-mode-view').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.cd-sub-nav-btn').forEach(btn => {
          if (btn.id.startsWith('tr-nav-')) btn.classList.remove('active');
        });

        const targetView = document.getElementById('tr-view-' + mode);
        const targetBtn = document.getElementById('tr-nav-' + mode);
        if (targetView) targetView.style.display = 'block';
        if (targetBtn) targetBtn.classList.add('active');

        // Dynamic Header titles
        const hTitle = document.getElementById('tr-header-title');
        const hSub = document.getElementById('tr-header-sub');
        if (mode === 'home') {
          if (hTitle) hTitle.textContent = 'Transport';
          if (hSub) hSub.textContent = 'Move around Malawi your way. Choose the best transport for your journey.';
        } else if (mode === 'search') {
          if (hTitle) hTitle.textContent = 'Bus Tickets Search';
          if (hSub) hSub.textContent = 'Compare operators, check available seats and buy tickets in-place.';
        } else if (mode === 'tickets') {
          if (hTitle) hTitle.textContent = 'My Bus Tickets Workspace';
          if (hSub) hSub.textContent = 'Manage your upcoming bus journeys and access your digital station boarding passes.';
        }
      }

      function executeInPlaceBusSearch(formEl) {
        const from = document.getElementById('tr-input-from').value;
        const to = document.getElementById('tr-input-to').value;
        const date = document.getElementById('tr-input-date').value;
        const pass = document.getElementById('tr-input-passengers').value;

        document.getElementById('srch-from').value = from;
        document.getElementById('srch-to').value = to;
        document.getElementById('srch-date').value = date;
        document.getElementById('srch-passengers').value = pass;

        updateActiveBusSearch();
        switchTransportMode('search');
      }

      function triggerQuickRouteSearch(from, to) {
        document.getElementById('srch-from').value = from;
        document.getElementById('srch-to').value = to;
        updateActiveBusSearch();
        switchTransportMode('search');
      }

      function updateActiveBusSearch() {
        const from = document.getElementById('srch-from').value;
        const to = document.getElementById('srch-to').value;
        const titleEl = document.getElementById('bus-results-title');
        if (titleEl) {
          titleEl.innerHTML = `Available Buses: <span style="color:#10b981;">${from} → ${to}</span> (4 buses found)`;
        }
      }

      let activeDrawerData = {};

      function openBusDetailsDrawer(operator, deptime, arrtime, bclass, price, depstation, arrstation, seats) {
        activeDrawerData = { operator, deptime, arrtime, bclass, price, depstation, arrstation, seats };

        document.getElementById('drw-operator').textContent = operator;
        document.getElementById('drw-class').textContent = bclass;
        document.getElementById('drw-deptime').textContent = deptime;
        document.getElementById('drw-arrtime').textContent = arrtime;
        document.getElementById('drw-depstation').textContent = depstation;
        document.getElementById('drw-arrstation').textContent = arrstation;
        document.getElementById('drw-price').textContent = price;

        document.getElementById('bus-details-drawer').classList.add('active');
      }

      function closeBusDetailsDrawer() {
        document.getElementById('bus-details-drawer').classList.remove('active');
      }

      function startBusCheckoutFromDrawer() {
        closeBusDetailsDrawer();
        const routeStr = (document.getElementById('srch-from')?.value || 'Lilongwe') + ' → ' + (document.getElementById('srch-to')?.value || 'Blantyre');
        document.getElementById('chk-sum-route').textContent = routeStr;
        document.getElementById('chk-sum-operator').textContent = activeDrawerData.operator + ' • ' + (document.getElementById('srch-date')?.value || '18 Aug 2026') + ' at ' + activeDrawerData.deptime + ' • Seat B14';
        document.getElementById('chk-sum-total').textContent = activeDrawerData.price || 'MK 25,000';

        proceedToCheckoutStep(1);
        document.getElementById('bus-checkout-modal').classList.add('active');
      }

      function closeBusCheckoutModal() {
        document.getElementById('bus-checkout-modal').classList.remove('active');
      }

      function proceedToCheckoutStep(stepNum) {
        document.getElementById('chk-step-1').style.display = (stepNum === 1) ? 'block' : 'none';
        document.getElementById('chk-step-2').style.display = (stepNum === 2) ? 'block' : 'none';
        document.getElementById('chk-step-3').style.display = (stepNum === 3) ? 'block' : 'none';
      }

      function selectSeat(btnEl, seatNo) {
        if (btnEl.classList.contains('taken')) return;
        document.querySelectorAll('.cd-seat-btn').forEach(b => {
          if (!b.classList.contains('taken')) b.classList.remove('selected');
        });
        btnEl.classList.add('selected');
      }

      function confirmBusTicketPayment() {
        proceedToCheckoutStep(3);
      }

      function openDigitalStationPass(ticketId, from, to, operator, date, time, passenger, seat) {
        document.getElementById('pass-route').textContent = `${from.toUpperCase()} → ${to.toUpperCase()}`;
        document.getElementById('pass-operator').textContent = `${operator} • Executive Coach`;
        document.getElementById('pass-date').textContent = date.toUpperCase();
        document.getElementById('pass-time').textContent = time;
        document.getElementById('pass-name').textContent = passenger;
        document.getElementById('pass-seat').textContent = seat;
        document.getElementById('pass-id').textContent = ticketId;

        document.getElementById('bus-station-pass-modal').classList.add('active');
      }

      function closeDigitalStationPass() {
        document.getElementById('bus-station-pass-modal').classList.remove('active');
      }
      </script>






      <!-- =====================================================================
           TAB: TOURS WORKSPACE
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'tours' ? 'active' : '' ?>" id="panel-tours">
        <div class="cd-workspace-body">

          <div class="cd-ws-header">
            <div>
              <div class="cd-ws-title">Explore Malawi</div>
              <div class="cd-ws-sub">Discover curated tours and unique experiences across the Warm Heart of Africa.</div>
            </div>
            <a href="javascript:void(0)" onclick="switchTab('ai')" class="cd-ai-pill">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/><path d="M12 12L2.5 7.5"/></svg>
              Plan with AI
            </a>
          </div>

          <!-- Sub-nav -->
          <nav class="cd-sub-nav-bar">
            <button class="cd-sub-nav-btn active" onclick="switchSubTab('tours','featured')" id="tours-btn-featured">Featured</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('tours','adventure')" id="tours-btn-adventure">Adventure</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('tours','cultural')" id="tours-btn-cultural">Cultural</button>
            <button class="cd-sub-nav-btn" onclick="switchSubTab('tours','mybookings')" id="tours-btn-mybookings">My Tours</button>
          </nav>

          <!-- FEATURED sub-panel -->
          <div class="cd-sub-panel active" id="tours-sub-featured">

            <!-- Search -->
            <div class="cd-search-section">
              <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:.75rem;align-items:end;">
                <div class="cd-field-group"><label>Destination / Experience</label><input type="text" class="cd-field-input" placeholder="Lake Malawi, Liwonde, Zomba..."></div>
                <div class="cd-field-group"><label>Duration</label><select class="cd-field-input"><option>Any</option><option>1 Day</option><option>2-3 Days</option><option>4-7 Days</option><option>1 Week+</option></select></div>
                <div class="cd-field-group"><label>Budget</label><select class="cd-field-input"><option>Any</option><option>Under MK 100K</option><option>MK 100K–300K</option><option>MK 300K+</option></select></div>
                <div class="cd-field-group"><label>Type</label><select class="cd-field-input"><option>All</option><option>Safari</option><option>Beach</option><option>Cultural</option><option>Adventure</option></select></div>
                <button class="cd-search-btn">Search</button>
              </div>
            </div>

            <div class="cd-section-hd"><h3>Featured Experiences</h3><span style="font-size:.78rem;color:var(--cd-text-muted);"><?= count($tourListings) ?> tours on Uthenga</span></div>

            <div class="cd-tour-grid">
              <?php if (empty($tourListings)): ?>
              <div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--cd-text-muted);">
                <div style="font-weight:700;margin-bottom:.35rem;">No tours published yet.</div>
                <div style="font-size:.85rem;">Tour operators are preparing new experiences — check back soon.</div>
              </div>
              <?php endif; ?>
              <?php foreach ($tourListings as $t):
                $tMeta = json_decode($t['meta'] ?? '{}', true) ?: [];
                $tPrice = (float) ($tMeta['pricePerPerson'] ?? $tMeta['base_price'] ?? 0);
                $tRating = (float) ($t['rating'] ?? 0);
              ?>
              <div class="cd-tour-card" onclick="TourCheckout.open('<?= e($t['id']) ?>','<?= e(addslashes($t['title'])) ?>',<?= $tPrice ?>)">
                <img src="<?= e($t['image']) ?>" alt="<?= e($t['title']) ?>" class="cd-tour-img" loading="lazy">
                <div class="cd-tour-body">
                  <div class="cd-tour-badges">
                    <div style="margin-left:auto;font-size:.8rem;color:var(--cd-yellow);font-weight:700;"><?= $tRating > 0 ? '★ ' . number_format($tRating, 1) : 'New' ?></div>
                  </div>
                  <div class="cd-tour-title"><?= e($t['title']) ?></div>
                  <div class="cd-tour-sub"><?= e($t['location']) ?></div>
                  <div class="cd-tour-footer">
                    <div class="cd-tour-price">MK <?= number_format($tPrice) ?> <small>/ person</small></div>
                    <div style="display:flex;gap:.4rem;">
                      <button class="cd-btn-solid" style="padding:.4rem .85rem;font-size:.78rem;" onclick="event.stopPropagation();TourCheckout.open('<?= e($t['id']) ?>','<?= e(addslashes($t['title'])) ?>',<?= $tPrice ?>)">Book Now</button>
                    </div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div><!-- /tour-grid -->

          </div><!-- /featured -->

          <!-- ADVENTURE sub-panel -->
          <div class="cd-sub-panel" id="tours-sub-adventure">
            <div class="cd-tour-grid">
              <div class="cd-tour-card">
                <img src="https://images.unsplash.com/photo-1551632811-561732d1e306?w=600&q=80" alt="Mulanje Trek" class="cd-tour-img">
                <div class="cd-tour-body">
                  <div class="cd-tour-badges"><span class="cd-tour-badge challenging">Challenging</span><div style="margin-left:auto;color:var(--cd-yellow);font-weight:700;font-size:.8rem;">★ 4.8</div></div>
                  <div class="cd-tour-title">Mount Mulanje Summit</div>
                  <div class="cd-tour-sub">Mulanje District</div>
                  <div class="cd-tour-meta"><span>4 Days</span><span>•</span><span>Up to 10 people</span></div>
                  <div class="cd-tour-footer"><div class="cd-tour-price">From MK 240,000</div><button class="cd-btn-solid" style="padding:.4rem .85rem;font-size:.78rem;">Explore</button></div>
                </div>
              </div>
            </div>
          </div>

          <!-- CULTURAL sub-panel -->
          <div class="cd-sub-panel" id="tours-sub-cultural">
            <div class="cd-tour-grid">
              <div class="cd-tour-card">
                <img src="https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=600&q=80" alt="Cultural Tour" class="cd-tour-img">
                <div class="cd-tour-body">
                  <div class="cd-tour-badges"><span class="cd-tour-badge easy">Easy</span><div style="margin-left:auto;color:var(--cd-yellow);font-weight:700;font-size:.8rem;">★ 4.6</div></div>
                  <div class="cd-tour-title">Malawi Cultural Heritage</div>
                  <div class="cd-tour-sub">Mua Mission, Dedza</div>
                  <div class="cd-tour-meta"><span>1 Day</span><span>•</span><span>Any group size</span></div>
                  <div class="cd-tour-footer"><div class="cd-tour-price">From MK 45,000</div><button class="cd-btn-solid" style="padding:.4rem .85rem;font-size:.78rem;">Explore</button></div>
                </div>
              </div>
            </div>
          </div>

          <!-- MY TOURS sub-panel -->
          <div class="cd-sub-panel" id="tours-sub-mybookings">
            <div style="border:1.5px dashed var(--cd-border);border-radius:var(--cd-radius-md);padding:2.5rem;text-align:center;color:var(--cd-text-muted);">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.4;margin-bottom:.75rem;"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/><circle cx="12" cy="12" r="3"/></svg>
              <div style="font-weight:700;">No upcoming tours</div>
              <div style="font-size:.82rem;margin-top:.25rem;">Your tour bookings will appear here.</div>
              <button class="cd-btn-solid" style="margin-top:1rem;" onclick="switchSubTab('tours','featured')">Explore Tours</button>
            </div>
          </div>

        </div>
      </div><!-- /panel-tours -->


      <!-- =====================================================================
           TAB: SHOP WORKSPACE
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'shop' ? 'active' : '' ?>" id="panel-shop">
        <div class="cd-workspace-body">

          <div class="cd-ws-header">
            <div>
              <div class="cd-ws-title">Uthenga Shop</div>
              <div class="cd-ws-sub">Drinks, snacks and bundles — order now for delivery or collection.</div>
            </div>
            <button class="cd-ai-pill" onclick="ShopCheckout.openCheckout()" id="shop-cart-toggle" style="position:relative;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
              Cart <span class="cd-cart-count-badge" id="shop-cart-count" style="display:none;">0</span>
            </button>
          </div>

          <div class="cd-search-section" style="padding:.85rem 1.1rem;">
            <input type="text" class="cd-field-input" placeholder="Search products..." style="max-width:320px;">
          </div>

          <div class="cd-product-grid">
            <?php if (empty($shopProducts)): ?>
            <div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--cd-text-muted);">
              <div style="font-weight:700;margin-bottom:.35rem;">No products in stock right now.</div>
              <div style="font-size:.85rem;">Check back soon.</div>
            </div>
            <?php endif; ?>
            <?php foreach ($shopProducts as $prod): ?>
            <div class="cd-product-card">
              <img src="<?= e($prod['primary_image_url'] ?? '') ?>" alt="<?= e($prod['name']) ?>" class="cd-product-img" loading="lazy">
              <div class="cd-product-body">
                <div class="cd-product-name"><?= e($prod['name']) ?></div>
                <div class="cd-product-price">MK <?= number_format((float) $prod['price']) ?></div>
                <button class="cd-add-cart-btn" onclick="ShopCheckout.addToCart(<?= (int) $prod['id'] ?>, this)">Add to Cart</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

        </div>
      </div><!-- /panel-shop -->


      <!-- =====================================================================
           TAB: MESSAGES WORKSPACE
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'messages' ? 'active' : '' ?>" id="panel-messages">
        <div class="cd-workspace-body">

          <div class="cd-ws-header" style="margin-bottom:1rem;">
            <div>
              <div class="cd-ws-title">Messages</div>
              <div class="cd-ws-sub">Communicate with drivers, properties, tour operators and support — all in one place.</div>
            </div>
            <button class="cd-ai-pill" onclick="selectConversation('ai')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/><path d="M12 12L2.5 7.5"/></svg>
              Ask AI
            </button>
          </div>

          <div class="cd-chat-layout">

            <!-- Conversation list -->
            <div class="cd-conv-list">
              <div class="cd-conv-list-header">Conversations</div>

              <div class="cd-conv-category-label">Transport</div>
              <div class="cd-conv-item active" id="conv-driver" onclick="selectConversation('driver')">
                <div class="cd-conv-avatar" style="background:linear-gradient(135deg,#1e3a8a,#2563eb);">JK</div>
                <div style="flex:1;min-width:0;">
                  <div class="cd-conv-name">John (Driver)</div>
                  <div class="cd-conv-preview">I'll arrive in 8 minutes.</div>
                </div>
                <div class="cd-conv-unread">2</div>
              </div>

              <div class="cd-conv-category-label">Accommodation</div>
              <div class="cd-conv-item" id="conv-hotel" onclick="selectConversation('hotel')">
                <div class="cd-conv-avatar" style="background:linear-gradient(135deg,#065f46,#059669);">SR</div>
                <div style="flex:1;min-width:0;">
                  <div class="cd-conv-name">Sunrise Lodge</div>
                  <div class="cd-conv-preview">Your room is ready for check-in.</div>
                </div>
              </div>

              <div class="cd-conv-category-label">Tours</div>
              <div class="cd-conv-item" id="conv-tour" onclick="selectConversation('tour')">
                <div class="cd-conv-avatar" style="background:linear-gradient(135deg,#7c3aed,#5b21b6);">LM</div>
                <div style="flex:1;min-width:0;">
                  <div class="cd-conv-name">Lake Malawi Tours</div>
                  <div class="cd-conv-preview">Meeting point confirmed: Mangochi...</div>
                </div>
              </div>

              <div class="cd-conv-category-label">Support</div>
              <div class="cd-conv-item" id="conv-support" onclick="selectConversation('support')">
                <div class="cd-conv-avatar" style="background:linear-gradient(135deg,#e63946,#c1121f);">UT</div>
                <div style="flex:1;min-width:0;">
                  <div class="cd-conv-name">Uthenga Support</div>
                  <div class="cd-conv-preview">How can we help you today?</div>
                </div>
              </div>

              <div class="cd-conv-category-label">AI Assistant</div>
              <div class="cd-conv-item" id="conv-ai" onclick="selectConversation('ai')">
                <div class="cd-conv-avatar" style="background:linear-gradient(135deg,#06b6d4,#a855f7);">AI</div>
                <div style="flex:1;min-width:0;">
                  <div class="cd-conv-name">Amai — AI Assistant</div>
                  <div class="cd-conv-preview">Ask me anything about travel in Malawi...</div>
                </div>
              </div>
            </div><!-- /conv-list -->

            <!-- Active chat pane -->
            <div class="cd-chat-pane">
              <div class="cd-chat-pane-header">
                <div>
                  <div class="cd-chat-pane-name" id="chat-pane-name">John (Driver) — Express Coach</div>
                  <div class="cd-chat-pane-ctx" id="chat-pane-ctx">Trip: Lilongwe → Blantyre • Booking: UT-BK-29384 • Active</div>
                </div>
                <div class="cd-chat-hdr-actions">
                  <button class="cd-chat-hdr-btn" onclick="switchTab('bookings')">View Booking</button>
                  <button class="cd-chat-hdr-btn" onclick="switchTab('tickets')">View Ticket</button>
                  <button class="cd-chat-hdr-btn" onclick="switchTab('support')">Support</button>
                </div>
              </div>

              <div class="cd-messages-scroll" id="chat-messages-area">
                <!-- System messages -->
                <div class="cd-msg sys">✓ Booking confirmed — UT-BK-29384</div>
                <div class="cd-msg sys">✓ Driver accepted your journey request</div>

                <!-- Conversation messages -->
                <div>
                  <div class="cd-msg incoming">Hello! I'm John, your driver for today's Lilongwe → Blantyre route. I'll be there soon.</div>
                  <div class="cd-msg-time" style="font-size:.63rem;color:var(--cd-text-muted);padding-left:.5rem;">10:12 AM</div>
                </div>
                <div>
                  <div class="cd-msg outgoing">Hi John! Thank you. I'm at the bus terminal.</div>
                  <div class="cd-msg-time" style="font-size:.63rem;color:rgba(255,255,255,.6);text-align:right;padding-right:.5rem;">10:14 AM</div>
                </div>
                <div>
                  <div class="cd-msg incoming">I'll arrive in 8 minutes. I'm in a blue coach, registration number ML 4329.</div>
                  <div class="cd-msg-time" style="font-size:.63rem;color:var(--cd-text-muted);padding-left:.5rem;">10:18 AM</div>
                </div>
                <div class="cd-msg sys">⚠ Driver is 1.2 km away</div>
              </div>

              <div class="cd-chat-input-bar">
                <input type="text" class="cd-chat-text-input" id="chat-text-input" placeholder="Type a message..." onkeydown="if(event.key==='Enter')sendChatMessage()">
                <button class="cd-chat-send-btn" onclick="sendChatMessage()" title="Send">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
              </div>
            </div><!-- /chat-pane -->

          </div><!-- /chat-layout -->

        </div>
      </div><!-- /panel-messages -->




      <!-- =====================================================================
           TAB: MY BOOKINGS WORKSPACE
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'bookings' ? 'active' : '' ?>" id="panel-bookings">
        <div class="cd-card">
          <div class="cd-card-header">
            <div class="cd-card-title">📅 Central Booking Manager</div>
            <a href="javascript:void(0)" onclick="switchTab('accommodation')" class="cd-btn-add-money">+ Book New Service</a>
          </div>

          <?php if (empty($allBookings)): ?>
            <div style="text-align:center;padding:3rem 1rem;">
              <div style="font-size:3rem;margin-bottom:1rem;">🎒</div>
              <h3>No bookings found yet</h3>
              <p style="color:var(--cd-text-muted);">Explore transport, stays, and events to make your first booking.</p>
            </div>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                <thead>
                  <tr style="border-bottom:1px solid var(--cd-border);text-align:left;color:var(--cd-text-muted);">
                    <th style="padding:0.75rem;">Booking Code</th>
                    <th style="padding:0.75rem;">Service</th>
                    <th style="padding:0.75rem;">Date</th>
                    <th style="padding:0.75rem;">Amount</th>
                    <th style="padding:0.75rem;">Status</th>
                    <th style="padding:0.75rem;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($allBookings as $b): ?>
                    <tr style="border-bottom:1px solid var(--cd-border);">
                      <td style="padding:0.85rem;font-weight:800;"><?= e($b['booking_code'] ?? 'UTH-'.$b['id']) ?></td>
                      <td style="padding:0.85rem;"><?= e($b['booking_title'] ?? 'Travel Service') ?></td>
                      <td style="padding:0.85rem;"><?= e(date('M d, Y', strtotime($b['created_at'] ?? 'now'))) ?></td>
                      <td style="padding:0.85rem;font-weight:800;color:var(--cd-primary);">MWK <?= number_format((float)($b['grand_total'] ?? 0)) ?></td>
                      <td style="padding:0.85rem;">
                        <span class="cd-booking-status status-confirmed"><?= e(strtoupper($b['status'] ?? 'CONFIRMED')) ?></span>
                      </td>
                      <td style="padding:0.85rem;">
                        <a href="#" onclick="alert('Viewing booking details for <?= e($b['booking_code'] ?? 'this item') ?>'); return false;" class="cd-card-link">Details &rsaquo;</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>


      <!-- =====================================================================
           TAB: MY TICKETS DIGITAL WALLET
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'tickets' ? 'active' : '' ?>" id="panel-tickets">
        <div class="cd-card-header" style="margin-bottom:1.5rem;">
          <div class="cd-card-title">🎫 My Digital Tickets &amp; Vouchers</div>
        </div>

        <div class="cd-grid-row">
          <div class="cd-col-tickets" style="background:none;border:none;padding:0;">
            <div class="cd-ticket-stub">
              <div class="cd-ticket-header">
                <span class="cd-ticket-tag">CONCERT</span>
                <span style="font-size:0.75rem;opacity:0.8;">Pass #UTH-8842</span>
              </div>
              <div>
                <div class="cd-ticket-title">Sauti Sol Live in Blantyre</div>
                <div class="cd-ticket-meta">Saturday, May 24, 2025 · 06:00 PM<br>Kamuzu Stadium, Blantyre</div>
              </div>
              <div class="cd-ticket-body">
                <div class="cd-ticket-seat-info">
                  <small>SEAT DETAILS</small>
                  <strong>VIP · Gate A · Row 3 · Seat 12</strong>
                </div>
                <div class="cd-qr-wrap">
                  <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=AUTH_TICKET_SAUTI_SOL_8842" alt="QR Code">
                </div>
              </div>
              <button class="cd-journey-btn" style="margin-top:0.85rem;background:rgba(255,255,255,0.15);color:#ffffff;border:none;" onclick="alert('Ticket downloaded as PDF!')">Download Printable Pass (PDF)</button>
            </div>
          </div>

          <div class="cd-col-tickets" style="background:none;border:none;padding:0;">
            <div class="cd-ticket-stub" style="background:linear-gradient(135deg, #064e3b 0%, #047857 100%);">
              <div class="cd-ticket-header">
                <span class="cd-ticket-tag" style="background:rgba(255,255,255,0.2);">BUS PASS</span>
                <span style="font-size:0.75rem;opacity:0.8;">Pass #TR-89342</span>
              </div>
              <div>
                <div class="cd-ticket-title">Express Coach: Blantyre &rarr; Zomba</div>
                <div class="cd-ticket-meta">Wednesday, May 14, 2025 · 09:00 AM<br>Chileka Bus Terminal</div>
              </div>
              <div class="cd-ticket-body">
                <div class="cd-ticket-seat-info">
                  <small>PASSENGER</small>
                  <strong>1 Adult · Seat 14B</strong>
                </div>
                <div class="cd-qr-wrap">
                  <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=AUTH_BUS_TICKET_89342" alt="QR Code">
                </div>
              </div>
              <button class="cd-journey-btn" style="margin-top:0.85rem;background:rgba(255,255,255,0.15);color:#ffffff;border:none;" onclick="alert('Bus pass downloaded!')">Download Boarding Pass</button>
            </div>
          </div>
        </div>
      </div>


      <!-- =====================================================================
           TAB: PAYMENTS FINANCIAL CENTER
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'payments' ? 'active' : '' ?>" id="panel-payments">
        <div class="cd-card">
          <div class="cd-card-header">
            <div class="cd-card-title">💳 Financial &amp; Wallet Center</div>
            <button class="cd-btn-add-money" onclick="typeof UthengaPay !== 'undefined' ? UthengaPay.initiate({serviceType:'wallet',serviceId:'wallet-topup',bookingId:'',amount:10000,title:'Wallet Top-Up',sub:'Add funds to your Uthenga wallet'}) : alert('Uthenga Pay initializing...')">+ Top Up Wallet</button>
          </div>

          <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
              <thead>
                <tr style="border-bottom:1px solid var(--cd-border);text-align:left;color:var(--cd-text-muted);">
                  <th style="padding:0.75rem;">Receipt #</th>
                  <th style="padding:0.75rem;">Gateway</th>
                  <th style="padding:0.75rem;">Booking</th>
                  <th style="padding:0.75rem;">Amount</th>
                  <th style="padding:0.75rem;">Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr style="border-bottom:1px solid var(--cd-border);">
                    <td style="padding:0.85rem;">REC-992384</td>
                    <td style="padding:0.85rem;">PayChangu (Airtel Money)</td>
                    <td style="padding:0.85rem;">Sunrise Lodge Hotel Booking</td>
                    <td style="padding:0.85rem;font-weight:800;color:var(--cd-green);">MWK 90,000</td>
                    <td style="padding:0.85rem;">May 13, 2025</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($payments as $p): ?>
                    <tr style="border-bottom:1px solid var(--cd-border);">
                      <td style="padding:0.85rem;font-weight:800;"><?= e($p['receipt_number'] ?? 'REC-99120') ?></td>
                      <td style="padding:0.85rem;"><?= e($p['gateway_label'] ?? 'PayChangu') ?></td>
                      <td style="padding:0.85rem;"><?= e($p['booking_title'] ?? 'Uthenga Service') ?></td>
                      <td style="padding:0.85rem;font-weight:800;color:var(--cd-green);">MWK <?= number_format((float)($p['amount'] ?? 0)) ?></td>
                      <td style="padding:0.85rem;"><?= e(date('M d, Y', strtotime($p['created_at'] ?? 'now'))) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>


      <!-- =====================================================================
           TAB: SAVED ITEMS / WISHLIST
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'saved' ? 'active' : '' ?>" id="panel-saved">
        <div class="cd-card-header" style="margin-bottom:1.5rem;">
          <div class="cd-card-title">❤️ Saved Places, Stays &amp; Experiences</div>
        </div>

        <div class="cd-recom-grid">
          <a href="javascript:void(0)" onclick="switchTab('accommodation')" class="cd-recom-card">
            <img src="https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=400&fit=crop&q=80" class="cd-recom-img" alt="Sunbird">
            <div class="cd-recom-body">
              <div class="cd-recom-title">Sunbird Capital Hotel</div>
              <div class="cd-recom-sub">Lilongwe</div>
              <div class="cd-recom-footer">
                <span class="cd-recom-price">MWK 120,000</span>
                <span class="cd-recom-rating">★ 4.9</span>
              </div>
            </div>
          </a>

          <a href="javascript:void(0)" onclick="switchTab('tours')" class="cd-recom-card">
            <img src="https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=400&fit=crop&q=80" class="cd-recom-img" alt="Cape Maclear">
            <div class="cd-recom-body">
              <div class="cd-recom-title">Cape Maclear Beach Tour</div>
              <div class="cd-recom-sub">Mangochi</div>
              <div class="cd-recom-footer">
                <span class="cd-recom-price">MWK 95,000</span>
                <span class="cd-recom-rating">★ 4.8</span>
              </div>
            </div>
          </a>

          <a href="javascript:void(0)" onclick="switchTab('events')" class="cd-recom-card">
            <img src="https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=400&fit=crop&q=80" class="cd-recom-img" alt="Jazz">
            <div class="cd-recom-body">
              <div class="cd-recom-title">Africa Jazz Festival</div>
              <div class="cd-recom-sub">Blantyre</div>
              <div class="cd-recom-footer">
                <span class="cd-recom-price">MWK 25,000</span>
                <span class="cd-recom-rating">★ 4.7</span>
              </div>
            </div>
          </a>
        </div>
      </div>


      <!-- =====================================================================
           TAB: AI ASSISTANT CONSOLE
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'ai' ? 'active' : '' ?>" id="panel-ai">
        <div class="cd-card">
          <div class="cd-card-header">
            <div class="cd-card-title">🤖 AI Assistant Workspace</div>
            <a href="<?= BASE_URL ?>ai.php" class="cd-card-link">Open Full AI Console &rsaquo;</a>
          </div>

          <div class="cd-ai-box" style="min-height:350px;">
            <div class="cd-ai-chat-bubble">
              <div class="cd-ai-avatar"><svg class="cd-svg-icon" viewBox="0 0 24 24" style="color:#ffffff;"><path d="M12 2a10 10 0 1 0 10 10H12V2z"/></svg></div>
              <div class="cd-ai-msg">
                Hello <strong><?= e($userFirstName) ?></strong>! I am your Uthenga Travel Intelligence assistant. How can I assist you with your journeys, hotel bookings, or transport schedules today?
              </div>
            </div>

            <div class="cd-ai-prompts">
              <a href="javascript:void(0)" onclick="switchTab('accommodation')" class="cd-ai-chip">Find affordable hotels in Zomba</a>
              <a href="javascript:void(0)" onclick="switchTab('transport')" class="cd-ai-chip">Book a taxi to Chileka Airport</a>
              <a href="javascript:void(0)" onclick="switchTab('tours')" class="cd-ai-chip">Plan a 3-day weekend trip to Mangochi</a>
              <a href="javascript:void(0)" onclick="switchTab('events')" class="cd-ai-chip">Check event tickets near me</a>
            </div>

            <div class="cd-ai-input-wrap">
              <input type="text" class="cd-ai-input" placeholder="Type a request (e.g. 'Book a shuttle from Lilongwe to Blantyre for tomorrow')">
              <button type="button" class="cd-ai-send-btn" onclick="window.location.href='<?= BASE_URL ?>ai.php'">➔</button>
            </div>
          </div>
        </div>
      </div>


      <!-- =====================================================================
           TAB: SUPPORT
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'support' ? 'active' : '' ?>" id="panel-support">
        <div class="cd-card">
          <div class="cd-card-header">
            <div class="cd-card-title">🎧 Customer Assistance Center</div>
          </div>

          <div class="cd-grid-row">
            <div class="cd-col-actions" style="grid-column:span 4;">
              <div class="cd-action-card" onclick="switchTab('messages')">
                <div class="cd-action-icon blue"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
                <div class="cd-action-label">Live Chat Support</div>
              </div>
            </div>
            <div class="cd-col-actions" style="grid-column:span 4;">
              <div class="cd-action-card" onclick="alert('Calling Uthenga Hotline: +265 999 123 456')">
                <div class="cd-action-icon green"><svg class="cd-svg-icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
                <div class="cd-action-label">Call Support (+265 999 123 456)</div>
              </div>
            </div>
            <div class="cd-col-actions" style="grid-column:span 4;">
              <a href="<?= BASE_URL ?>faq.php" class="cd-action-card">
                <div class="cd-action-icon purple"><svg class="cd-svg-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                <div class="cd-action-label">Browse FAQs</div>
              </a>
            </div>
          </div>
        </div>
      </div>


      <!-- =====================================================================
           TAB: SETTINGS & PROFILE FORM
           ===================================================================== -->
      <div class="cd-tab-panel <?= $activeTab === 'settings' ? 'active' : '' ?>" id="panel-settings">
        <div class="cd-card">
          <div class="cd-card-header">
            <div class="cd-card-title">⚙️ Profile &amp; Preferences</div>
          </div>

          <?php if ($profileUpdateSuccess): ?>
            <div style="padding:0.85rem;background:rgba(16,185,129,0.12);color:var(--cd-green);border-radius:8px;margin-bottom:1rem;font-weight:700;">
              <?= e($profileUpdateSuccess) ?>
            </div>
          <?php endif; ?>
          <?php if ($profileUpdateError): ?>
            <div style="padding:0.85rem;background:rgba(230,57,70,0.12);color:var(--cd-primary);border-radius:8px;margin-bottom:1rem;font-weight:700;">
              <?= e($profileUpdateError) ?>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= BASE_URL ?>dashboard.php?tab=settings" style="max-width:600px;">
            <?php
              if (empty($_SESSION['csrf_token'])) {
                  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
              }
            ?>
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="update_dashboard_profile" value="1">

            <div style="margin-bottom:1.1rem;">
              <label style="display:block;font-size:0.85rem;font-weight:700;margin-bottom:0.35rem;">Full Name</label>
              <input type="text" name="name" value="<?= e($userName) ?>" class="cd-search-input" style="border-radius:10px;padding:0.75rem 1rem;" required>
            </div>

            <div style="margin-bottom:1.1rem;">
              <label style="display:block;font-size:0.85rem;font-weight:700;margin-bottom:0.35rem;">Email Address</label>
              <input type="email" name="email" value="<?= e($userEmail) ?>" class="cd-search-input" style="border-radius:10px;padding:0.75rem 1rem;" required>
            </div>

            <div style="margin-bottom:1.1rem;">
              <label style="display:block;font-size:0.85rem;font-weight:700;margin-bottom:0.35rem;">Phone Number</label>
              <input type="tel" name="phone" value="<?= e($userPhone) ?>" class="cd-search-input" style="border-radius:10px;padding:0.75rem 1rem;" required>
            </div>

            <button type="submit" class="cd-btn-promo" style="max-width:220px;padding:0.85rem;">Save Changes</button>
          </form>
        </div>
      </div>

      <!-- FOOTER -->
      <footer class="cd-footer">
        <div>
          &copy; <?= date('Y') ?> Uthenga. All rights reserved. Powered by Giant Plus IT.
        </div>

        <div class="cd-footer-badges">
          <span class="cd-footer-badge">🛡️ Secure Payments (Uthenga Pay)</span>
          <span class="cd-footer-badge">✓ Verified Partners</span>
          <span class="cd-footer-badge">🎧 24/7 Support</span>
        </div>
      </footer>

    </div><!-- /.cd-workspace -->

  </main><!-- /.cd-main-content -->

</div><!-- /.cd-body-wrapper -->

<script>
(function() {
  'use strict';

  // =========================================================================
  // WORKSPACE MODULE INTERACTIVE HANDLERS
  // =========================================================================

  // 1. Sub-tab switcher for modules
  window.switchSubTab = function(prefix, subId) {
    var parent = document.getElementById('panel-' + prefix);
    if (!parent) return;

    // Sub nav buttons
    var btns = parent.querySelectorAll('.cd-sub-nav-btn');
    btns.forEach(function(b) { b.classList.remove('active'); });
    var activeBtn = document.getElementById(prefix + '-btn-' + subId);
    if (activeBtn) activeBtn.classList.add('active');

    // Sub panels
    var panels = parent.querySelectorAll('.cd-sub-panel');
    panels.forEach(function(p) { p.classList.remove('active'); });
    var targetPanel = document.getElementById(prefix + '-sub-' + subId);
    if (targetPanel) targetPanel.classList.add('active');
  };

  // 2. Activity status sub-filter
  window.filterActTab = function(btn, group, status) {
    var siblingBtns = btn.parentElement.querySelectorAll('.cd-act-tab');
    siblingBtns.forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
  };

  // 5. Transport Workspace
  window.searchTransport = function() {
    alert('Searching available routes...');
  };

  // 8. Messages / Chat Workspace
  var chatThreads = {
    'driver': { name: 'John (Driver) — Express Coach', ctx: 'Trip: Lilongwe → Blantyre • Booking: UT-BK-29384 • Active', msgs: [
      { type: 'sys', text: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg> Booking confirmed — UT-BK-29384' },
      { type: 'incoming', text: "Hello! I'm John, your driver for today's Lilongwe → Blantyre route. I'll be there soon.", time: '10:12 AM' },
      { type: 'outgoing', text: "Hi John! Thank you. I'm at the bus terminal.", time: '10:14 AM' },
      { type: 'incoming', text: "I'll arrive in 8 minutes. I'm in a blue coach, registration number ML 4329.", time: '10:18 AM' },
      { type: 'sys', text: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Driver is 1.2 km away' }
    ]},
    'hotel': { name: 'Sunrise Lodge Concierge', ctx: 'Reservation: UT-AC-84920 • Check-in: Aug 12', msgs: [
      { type: 'sys', text: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg> Room reservation confirmed' },
      { type: 'incoming', text: 'Greetings from Sunrise Lodge! We look forward to welcoming you on Aug 12.', time: '09:00 AM' },
      { type: 'outgoing', text: 'Hello! Is early check-in at 12:00 PM possible?', time: '09:15 AM' },
      { type: 'incoming', text: 'Yes, your room will be ready at 12:00 PM at no extra charge.', time: '09:20 AM' }
    ]},
    'tour': { name: 'Lake Malawi Tours Operator', ctx: 'Tour: Lake Malawi Explorer • Booking: UT-TR-30219', msgs: [
      { type: 'sys', text: '✓ Tour booking confirmed' },
      { type: 'incoming', text: 'Hi! The boat departs Mangochi jetty at 08:30 AM tomorrow. Please bring swimwear and sunscreen.', time: '04:00 PM' }
    ]},
    'support': { name: 'Uthenga Customer Support', ctx: 'Ticket #SUP-4920 • Priority Support', msgs: [
      { type: 'incoming', text: 'Hello! How can the Uthenga support team assist you today?', time: 'Just now' }
    ]},
    'ai': { name: 'Amai — Uthenga AI Travel Assistant', ctx: 'Personalized Travel OS Intelligence', msgs: [
      { type: 'incoming', text: "Hello! I am Amai, your AI travel assistant. Ask me anything about routes, accommodations, event tickets, or recommendations across Malawi!", time: 'Just now' }
    ]}
  };

  var activeConvKey = 'driver';

  window.selectConversation = function(key) {
    activeConvKey = key;
    var thread = chatThreads[key];
    if (!thread) return;

    // Highlight item
    var items = document.querySelectorAll('.cd-conv-item');
    items.forEach(function(i) { i.classList.remove('active'); });
    var activeItem = document.getElementById('conv-' + key);
    if (activeItem) activeItem.classList.add('active');

    // Set header
    document.getElementById('chat-pane-name').textContent = thread.name;
    document.getElementById('chat-pane-ctx').textContent = thread.ctx;

    // Render messages
    renderChatMessages(thread.msgs);
  };

  function renderChatMessages(msgs) {
    var area = document.getElementById('chat-messages-area');
    if (!area) return;
    var html = '';
    msgs.forEach(function(m) {
      if (m.type === 'sys') {
        html += '<div class="cd-msg sys">' + m.text + '</div>';
      } else if (m.type === 'incoming') {
        html += '<div><div class="cd-msg incoming">' + m.text + '</div><div class="cd-msg-time" style="font-size:.63rem;color:var(--cd-text-muted);padding-left:.5rem;">' + (m.time || '') + '</div></div>';
      } else if (m.type === 'outgoing') {
        html += '<div><div class="cd-msg outgoing">' + m.text + '</div><div class="cd-msg-time" style="font-size:.63rem;color:rgba(255,255,255,.6);text-align:right;padding-right:.5rem;">' + (m.time || '') + '</div></div>';
      }
    });
    area.innerHTML = html;
    area.scrollTop = area.scrollHeight;
  }

  window.sendChatMessage = function() {
    var input = document.getElementById('chat-text-input');
    if (!input || !input.value.trim()) return;
    var text = input.value.trim();
    input.value = '';

    var thread = chatThreads[activeConvKey];
    if (!thread) return;

    var timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    thread.msgs.push({ type: 'outgoing', text: text, time: timeNow });
    renderChatMessages(thread.msgs);

    // Auto reply after 1 sec
    setTimeout(function() {
      var replies = {
        'driver': 'Got it! I am on my way.',
        'hotel': 'Thank you! We have logged your message.',
        'tour': 'Noted! See you at the jetty.',
        'support': 'An agent has received your request and will respond shortly.',
        'ai': 'I can help you with that! Let me check the best options for you right now.'
      };
      var replyText = replies[activeConvKey] || 'Message received.';
      thread.msgs.push({ type: 'incoming', text: replyText, time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) });
      renderChatMessages(thread.msgs);
    }, 1000);
  };



  // Toggle Sidebar on mobile
  var hamburger = document.getElementById('cd-hamburger-btn');
  var sidebar   = document.getElementById('cd-sidebar');
  if (hamburger && sidebar) {
    hamburger.addEventListener('click', function() {
      sidebar.classList.toggle('open');
    });
  }

  // Switch Tab function — handles both embedded panels and external pages
  window.switchTab = function(tabId) {
    // External routes that don't have embedded panels
    var externalRoutes = {
      'quick-travel': '<?= BASE_URL ?>ai.php',
      'trip-planner': '<?= BASE_URL ?>ai.php#/planner'
    };
    if (externalRoutes[tabId]) {
      window.location.href = externalRoutes[tabId];
      return;
    }

    var tabs = document.querySelectorAll('.cd-tab-panel');
    var navItems = document.querySelectorAll('.cd-nav-item[data-tab]');
    
    tabs.forEach(function(panel) {
      panel.classList.remove('active');
    });
    navItems.forEach(function(item) {
      item.classList.remove('active');
    });

    var targetPanel = document.getElementById('panel-' + tabId);
    if (targetPanel) {
      targetPanel.classList.add('active');
    }

    var targetNav = document.querySelector('.cd-nav-item[data-tab="' + tabId + '"]');
    if (targetNav) {
      targetNav.classList.add('active');
    }

    // Close mobile sidebar if open
    if (sidebar) {
      sidebar.classList.remove('open');
    }

    // Scroll window smoothly to workspace top
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Update URL parameter without full reload
    if (window.history && window.history.pushState) {
      window.history.pushState(null, '', 'dashboard.php?tab=' + tabId);
    }
  };

  // Light / Dark Theme Switcher
  var themeBtn = document.getElementById('dashboard-theme-toggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', function() {
      var currentTheme = document.documentElement.dataset.theme || 'light';
      var nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = nextTheme;
      document.documentElement.style.colorScheme = nextTheme;
      try {
        localStorage.setItem('uthenga-theme', nextTheme);
        document.cookie = 'uthenga-theme=' + nextTheme + ';path=/;max-age=31536000';
      } catch (e) {}
    });
  }

  // Toggle Balance Eye Visibility
  window.toggleBalanceVisibility = function() {
    var numEl = document.getElementById('balance-num');
    if (numEl) {
      if (numEl.dataset.hidden === 'true') {
        numEl.textContent = 'MWK <?= number_format($walletBalance) ?>';
        numEl.dataset.hidden = 'false';
      } else {
        numEl.textContent = 'MWK ••••••••';
        numEl.dataset.hidden = 'true';
      }
    }
  };

  // Shortcut key ⌘K / Ctrl+K focus search
  document.addEventListener('keydown', function(e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault();
      var searchInput = document.getElementById('cd-global-search');
      if (searchInput) searchInput.focus();
    }
  });

})();
</script>

<!-- Real Accommodation Checkout — dates -> real room availability/pricing
     -> real time-bound hold -> Uthenga Checkout. Same proven flow as hotels.php. -->
<div class="cd-modal-overlay" id="accom-checkout-modal">
  <div class="cd-modal-container">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--cd-border);">
      <h3 id="accom-checkout-title" style="font-size:1.05rem;font-weight:900;">Book Your Stay</h3>
      <button onclick="closeModal('accom-checkout-modal')" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--cd-text-muted);">✕</button>
    </div>
    <div style="padding:1.5rem;">
      <input type="hidden" id="accom-checkout-listing-id" value="">
      <div class="cd-field-group" style="margin-bottom:.75rem;"><label>Check-in Date</label><input type="date" id="accom-checkin" class="cd-field-input" required></div>
      <div class="cd-field-group" style="margin-bottom:1rem;"><label>Check-out Date</label><input type="date" id="accom-checkout" class="cd-field-input" required></div>
      <button type="button" class="cd-btn-solid" style="width:100%;margin-bottom:1rem;" onclick="AccommodationCheckout.checkAvailability()">Check Availability</button>
      <div id="accom-checkout-msg" class="text-sm text-muted" style="margin-bottom:0.75rem;"></div>
      <div id="accom-checkout-rooms"></div>
    </div>
  </div>
</div>

<!-- Real Event Ticket Checkout — real ticket types -> the same proven
     request_api.php create_booking + UthengaPay flow event-details.php uses. -->
<div class="cd-modal-overlay" id="evt-checkout-modal">
  <div class="cd-modal-container">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--cd-border);">
      <h3 id="evt-checkout-title" style="font-size:1.05rem;font-weight:900;">Get Tickets</h3>
      <button onclick="closeModal('evt-checkout-modal')" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--cd-text-muted);">✕</button>
    </div>
    <div style="padding:1.5rem;">
      <div id="evt-checkout-msg" class="text-sm text-muted" style="margin-bottom:0.75rem;"></div>
      <div id="evt-checkout-types"></div>
    </div>
  </div>
</div>

<!-- Real Tour Booking — real listing -> the now-fixed request_api.php
     create_booking path (tours now collect real payment, same as events). -->
<div class="cd-modal-overlay" id="tour-checkout-modal">
  <div class="cd-modal-container">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--cd-border);">
      <h3 id="tour-checkout-title" style="font-size:1.05rem;font-weight:900;">Book Tour</h3>
      <button onclick="closeModal('tour-checkout-modal')" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--cd-text-muted);">✕</button>
    </div>
    <div style="padding:1.5rem;">
      <div class="cd-field-group" style="margin-bottom:.75rem;"><label>Tour Date</label><input type="date" id="tour-checkout-date" class="cd-field-input"></div>
      <div class="cd-field-group" style="margin-bottom:1rem;"><label>Number of Participants</label><input type="number" id="tour-checkout-qty" class="cd-field-input" min="1" value="1" oninput="TourCheckout.updateTotal()"></div>
      <div style="display:flex;justify-content:space-between;font-weight:800;font-size:1.05rem;margin-bottom:1rem;"><span>Total</span><span id="tour-checkout-total" style="color:var(--cd-primary);"></span></div>
      <div id="tour-checkout-msg" class="text-sm text-muted" style="margin-bottom:0.75rem;"></div>
      <button type="button" class="cd-btn-solid" style="width:100%;" onclick="TourCheckout.reserve()">Book This Tour</button>
    </div>
  </div>
</div>

<!-- Real Shop Checkout — real cart (api/shop/cart.php) -> real order
     (api/shop/checkout.php, the same order-creation logic shop-checkout.php
     uses) -> Uthenga Checkout for pay_online orders. -->
<div class="cd-modal-overlay" id="shop-checkout-modal">
  <div class="cd-modal-container">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--cd-border);">
      <h3 style="font-size:1.05rem;font-weight:900;">Checkout</h3>
      <button onclick="closeModal('shop-checkout-modal')" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--cd-text-muted);">✕</button>
    </div>
    <div style="padding:1.5rem;">
      <div id="shop-checkout-summary" style="margin-bottom:1rem;"></div>
      <div id="shop-checkout-form-fields">
        <div class="cd-field-group" style="margin-bottom:.65rem;"><label>Full Name</label><input type="text" id="shop-checkout-name" class="cd-field-input" value="<?= e($userName) ?>"></div>
        <div class="cd-field-group" style="margin-bottom:.65rem;"><label>Email</label><input type="email" id="shop-checkout-email" class="cd-field-input" value="<?= e($userEmail) ?>"></div>
        <div class="cd-field-group" style="margin-bottom:.65rem;"><label>Phone</label><input type="tel" id="shop-checkout-phone" class="cd-field-input" placeholder="099X XXX XXX or +265 99X XXX XXX"></div>
        <div class="cd-field-group" style="margin-bottom:.65rem;"><label>Delivery Address</label><textarea id="shop-checkout-address" class="cd-field-input" rows="2"></textarea></div>
        <div class="cd-field-group" style="margin-bottom:1rem;">
          <label>Payment Method</label>
          <select id="shop-checkout-method" class="cd-field-input">
            <option value="cash_on_delivery">Cash on Delivery</option>
            <option value="pay_online">Pay Online (Airtel Money / TNM Mpamba / Bank)</option>
          </select>
        </div>
      </div>
      <div id="shop-checkout-msg" class="text-sm text-muted" style="margin-bottom:0.75rem;"></div>
      <button type="button" class="cd-btn-solid" style="width:100%;" onclick="ShopCheckout.submit()">Place Order</button>
    </div>
  </div>
</div>

<!-- Real Bus Ticket Purchase — real search (api/tie/transport/search.php) ->
     real seat-class selection -> real saved payment method
     (api/tie/transport/payment-methods.php) -> real charge
     (api/tie/transport/purchase.php) -> real poll
     (api/tie/transport/purchase-status.php). Same proven backend
     bus-tickets.php uses; only the UI is new and dashboard-native. -->
<div class="cd-modal-overlay" id="real-bus-checkout-modal">
  <div class="cd-modal-container" style="max-width:640px;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1.25rem 1.5rem;border-bottom:1px solid var(--cd-border);">
      <h3 style="font-size:1.05rem;font-weight:900;">Bus Tickets</h3>
      <button onclick="closeModal('real-bus-checkout-modal')" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--cd-text-muted);">✕</button>
    </div>
    <div style="padding:1.5rem;">

      <div id="bus-step-search">
        <div class="cd-field-group" style="margin-bottom:.65rem;"><label>From</label><input type="text" id="bus-search-from" class="cd-field-input" placeholder="e.g. Lilongwe"></div>
        <div class="cd-field-group" style="margin-bottom:.65rem;"><label>To</label><input type="text" id="bus-search-to" class="cd-field-input" placeholder="e.g. Blantyre"></div>
        <div class="cd-field-group" style="margin-bottom:1rem;"><label>Date</label><input type="date" id="bus-search-date" class="cd-field-input"></div>
        <button type="button" class="cd-btn-solid" style="width:100%;" onclick="BusCheckout.search()">Search Departures</button>
      </div>

      <div id="bus-step-results" style="display:none;">
        <button type="button" class="cd-btn-outline" style="margin-bottom:.75rem;" onclick="BusCheckout.showStep('search')">← New Search</button>
        <div id="bus-results-msg" class="text-sm text-muted" style="margin-bottom:0.75rem;"></div>
        <div id="bus-results-list"></div>
      </div>

      <div id="bus-step-seat" style="display:none;">
        <button type="button" class="cd-btn-outline" style="margin-bottom:.75rem;" onclick="BusCheckout.showStep('results')">← Back</button>
        <div id="bus-seat-summary" style="margin-bottom:1rem;font-size:.9rem;"></div>
        <div class="cd-field-group" style="margin-bottom:.65rem;"><label>Number of Seats</label><input type="number" id="bus-passenger-qty" class="cd-field-input" min="1" value="1"></div>
        <div class="cd-field-group" style="margin-bottom:.65rem;"><label>Lead Passenger Name</label><input type="text" id="bus-passenger-name" class="cd-field-input"></div>
        <div class="cd-field-group" style="margin-bottom:1rem;"><label>Passenger Phone (optional)</label><input type="tel" id="bus-passenger-phone" class="cd-field-input" placeholder="099X XXX XXX or +265 99X XXX XXX"></div>
        <div id="bus-seat-msg" class="text-sm text-muted" style="margin-bottom:0.75rem;"></div>
        <button type="button" class="cd-btn-solid" style="width:100%;" onclick="BusCheckout.proceedToPayment()">Continue to Payment</button>
      </div>

      <div id="bus-step-payment" style="display:none;">
        <button type="button" class="cd-btn-outline" style="margin-bottom:.75rem;" onclick="BusCheckout.showStep('seat')">← Back</button>
        <div id="bus-payment-msg" class="text-sm text-muted" style="margin-bottom:0.75rem;"></div>
        <div id="bus-payment-methods"></div>
        <div id="bus-add-method-form" style="display:none;margin-top:.75rem;border-top:1px solid var(--cd-border);padding-top:.75rem;">
          <div class="cd-field-group" style="margin-bottom:.65rem;"><label>Mobile Money Operator</label><select id="bus-operator-select" class="cd-field-input"></select></div>
          <div class="cd-field-group" style="margin-bottom:.65rem;"><label>Mobile Number</label><input type="tel" id="bus-new-method-phone" class="cd-field-input" placeholder="099X XXX XXX or +265 99X XXX XXX"></div>
          <button type="button" class="cd-btn-solid" style="width:100%;" onclick="BusCheckout.addPaymentMethod()">Save Payment Method</button>
        </div>
        <button type="button" class="cd-btn-solid" id="bus-purchase-btn" style="width:100%;margin-top:1rem;display:none;" onclick="BusCheckout.purchase()">Pay &amp; Buy Ticket</button>
      </div>

      <div id="bus-step-waiting" style="display:none;text-align:center;">
        <div class="uth-pay-spinner" style="margin:0 auto 1rem;"></div>
        <p id="bus-waiting-msg"></p>
      </div>

      <div id="bus-step-ticket" style="display:none;">
        <h4 style="font-weight:800;margin-bottom:.75rem;color:#10b981;">✓ Ticket Confirmed</h4>
        <div id="bus-ticket-details"></div>
        <button type="button" class="cd-btn-solid" style="width:100%;margin-top:1rem;" onclick="closeModal('real-bus-checkout-modal');location.reload();">Done</button>
      </div>

    </div>
  </div>
</div>

<script>
  // Generic modal helpers — dashboard.php previously only had bespoke
  // per-modal close functions; the reused AccommodationCheckout/EventCheckout
  // widgets (shared with hotels.php's pattern) call these two generically.
  function openModal(id) { var el = document.getElementById(id); if (el) el.classList.add('active'); }
  // Real client-side filter over the already-rendered real listing cards —
  // matches the "Where" text against each card's own title/location text.
  window.searchAccom = function () {
    var query = (document.getElementById('accom-filter-where').value || '').trim().toLowerCase();
    document.querySelectorAll('#accom-results-grid .cd-listing-card').forEach(function (card) {
      var text = card.textContent.toLowerCase();
      card.style.display = (!query || text.indexOf(query) !== -1) ? '' : 'none';
    });
  };
  function closeModal(id) { var el = document.getElementById(id); if (el) el.classList.remove('active'); }
</script>
<script src="<?= BASE_URL ?>assets/js/accommodation-checkout.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
<script src="<?= BASE_URL ?>assets/js/event-checkout.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
<script src="<?= BASE_URL ?>assets/js/tour-checkout.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
<script src="<?= BASE_URL ?>assets/js/shop-checkout.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
<script src="<?= BASE_URL ?>assets/js/bus-checkout.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
<?php require_once __DIR__ . '/includes/payment_modal.php'; ?>

</body>
</html>
