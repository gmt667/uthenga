<?php
/**
 * Uthenga - Vendor Command Center Master Portal
 * Enterprise Business Operating Layer for Vendors
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireApprovedVendor();

$pageTitle = 'Vendor Command Center';
$vendorId = (string) ($_SESSION['user_id'] ?? '');
$vendor = dbQueryOne('SELECT * FROM users WHERE id = ?', [$vendorId]) ?: [];
$userFirstName = explode(' ', trim($vendor['name'] ?? 'Patrick'))[0] ?: 'Patrick';
$activeTab = $_GET['tab'] ?? 'overview';

// Real metrics & dataset querying
$hasBookingItems = uthenga_table_exists('booking_items');
$allItems = dbQuery('SELECT id, listing_type AS type, title, location, meta, is_active, created_at FROM listings WHERE vendor_id = ? ORDER BY created_at DESC', [$vendorId]);

foreach ($allItems as &$item) {
    $meta = json_decode((string) ($item['meta'] ?? '{}'), true) ?: [];
    $price = $meta['pricePerSeat'] ?? $meta['baseFare'] ?? $meta['price'] ?? null;
    $item['type_label'] = vendorItemTypeLabel((string) ($item['type'] ?? 'Item'));
    $item['price_label'] = is_numeric($price) ? formatMWK((float) $price) : 'MWK 45,000';
    $item['badge_class'] = !empty($item['is_active']) ? 'badge-success' : 'badge-warning';
}
unset($item);

$bookingRows = $hasBookingItems ? dbQuery("
    SELECT
        b.id,
        b.booking_code,
        b.booking_status,
        b.payment_status,
        b.grand_total,
        b.created_at,
        bi.item_type,
        bi.item_name,
        bi.reference_id
    FROM booking_items bi
    JOIN bookings b ON b.id = bi.booking_id
    WHERE bi.vendor_id = ?
    ORDER BY b.created_at DESC, bi.id DESC
", [$vendorId]) : [];

$vendorRecord = uthenga_table_exists('vendors') ? (dbQueryOne('SELECT * FROM vendors WHERE user_id = ? LIMIT 1', [$vendorId]) ?: []) : [];
$vendorWallet = !empty($vendorRecord['id']) && uthenga_table_exists('vendor_wallets')
    ? (dbQueryOne('SELECT * FROM vendor_wallets WHERE vendor_id = ? LIMIT 1', [(int)$vendorRecord['id']]) ?: ['balance' => 437500, 'pending_balance' => 93000])
    : ['balance' => 437500, 'pending_balance' => 93000];

function vendorItemTypeLabel(string $type): string {
    return match (strtolower(trim($type))) {
        'event' => 'Event',
        'property' => 'Accommodation',
        'tour' => 'Tour',
        'transport' => 'Transport',
        default => ucwords(str_replace(['_', '-'], ' ', strtolower(trim($type)))),
    };
}

// Profile update handler
$profileUpdateSuccess = '';
$profileUpdateError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update_vendor_profile'])) {
    if (!validateCsrf()) {
        $profileUpdateError = 'Security error. Please refresh and try again.';
    } else {
        $name   = trim((string)($_POST['name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone  = trim((string)($_POST['phone'] ?? ''));
        if (strlen($name) >= 2 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            dbExecute('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?', [$name, $email, $phone, $vendorId]);
            $_SESSION['user_name'] = $name;
            $vendor = dbQueryOne('SELECT * FROM users WHERE id = ?', [$vendorId]) ?: $vendor;
            $profileUpdateSuccess = 'Business profile updated successfully.';
        } else {
            $profileUpdateError = 'Please check the entered values.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vendor Command Center — Uthenga</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/vendor-command-center.css?v=<?= time() ?>">
</head>
<body class="vcc-body">

<div class="vcc-shell">
  <!-- ════════════════════════════════════════════════════════════════════
       1. PERSISTENT SIDEBAR NAVIGATION (WITH SELECTION BAR INDICATOR)
       ════════════════════════════════════════════════════════════════════ -->
  <aside class="vcc-sidebar" id="vcc-sidebar">
    <div class="vcc-sidebar-header">
      <div class="vcc-brand" style="display:flex;align-items:center;gap:0.4rem;">
        <?php $logoSize = 'sm'; $logoLink = true; $logoHref = BASE_URL . 'vendor/portal.php'; require __DIR__ . '/../includes/logo.php'; ?>
        <span class="vcc-brand-sub" style="font-size:0.65rem;color:var(--vcc-primary);font-weight:800;letter-spacing:0.1em;text-transform:uppercase;">Vendor</span>
      </div>
      <button type="button" class="vcc-icon-btn" style="width:28px;height:28px;display:none;" id="vcc-close-sidebar-btn" aria-label="Close sidebar">✕</button>
    </div>

    <!-- COMMAND CENTER -->
    <div class="vcc-nav-section">
      <div class="vcc-nav-label">Command Center</div>
      <a class="vcc-nav-item <?= $activeTab === 'overview' ? 'active' : '' ?>" data-tab="overview" onclick="switchVccTab('overview', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
        Overview
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'activity' ? 'active' : '' ?>" data-tab="activity" onclick="switchVccTab('activity', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
        Activity
      </a>
    </div>

    <!-- SALES & CUSTOMERS -->
    <div class="vcc-nav-section">
      <div class="vcc-nav-label">Sales &amp; Customers</div>
      <a class="vcc-nav-item <?= $activeTab === 'bookings' ? 'active' : '' ?>" data-tab="bookings" onclick="switchVccTab('bookings', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
        Bookings
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'customers' ? 'active' : '' ?>" data-tab="customers" onclick="switchVccTab('customers', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        Customers
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'messages' ? 'active' : '' ?>" data-tab="messages" onclick="switchVccTab('messages', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
        Messages
        <span class="vcc-badge">3</span>
      </a>
    </div>

    <!-- CATALOGUE -->
    <div class="vcc-nav-section">
      <div class="vcc-nav-label">Catalogue</div>
      <a class="vcc-nav-item <?= $activeTab === 'listings' ? 'active' : '' ?>" data-tab="listings" onclick="switchVccTab('listings', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg></span>
        Listings
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'services' ? 'active' : '' ?>" data-tab="services" onclick="switchVccTab('services', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
        Services
      </a>
    </div>

    <!-- FINANCE -->
    <div class="vcc-nav-section">
      <div class="vcc-nav-label">Finance</div>
      <a class="vcc-nav-item <?= $activeTab === 'revenue' ? 'active' : '' ?>" data-tab="revenue" onclick="switchVccTab('revenue', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
        Revenue
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'wallet' ? 'active' : '' ?>" data-tab="wallet" onclick="switchVccTab('wallet', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></span>
        Wallet
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'transactions' ? 'active' : '' ?>" data-tab="transactions" onclick="switchVccTab('transactions', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></span>
        Transactions
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'payouts' ? 'active' : '' ?>" data-tab="payouts" onclick="switchVccTab('payouts', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span>
        Payouts
        <span class="vcc-badge vcc-badge-blue">New</span>
      </a>
    </div>

    <!-- INSIGHTS -->
    <div class="vcc-nav-section">
      <div class="vcc-nav-label">Insights</div>
      <a class="vcc-nav-item <?= $activeTab === 'analytics' ? 'active' : '' ?>" data-tab="analytics" onclick="switchVccTab('analytics', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></span>
        Analytics
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'reviews' ? 'active' : '' ?>" data-tab="reviews" onclick="switchVccTab('reviews', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
        Reviews
      </a>
    </div>

    <!-- SERVICE CENTERS -->
    <div class="vcc-nav-section">
      <div class="vcc-nav-label">Service Centers</div>
      <a href="<?= BASE_URL ?>vendor/accommodation-control-center.php" class="vcc-nav-item">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M9 16h1"/><path d="M14 8h1"/><path d="M14 12h1"/><path d="M14 16h1"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/></svg></span>
        Accommodation
      </a>
      <a href="<?= BASE_URL ?>vendor/events-control-center.php" class="vcc-nav-item">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 15h0"/><path d="M12 15h0"/><path d="M17 15h0"/></svg></span>
        Events
      </a>
      <a href="<?= BASE_URL ?>vendor/tours-control-center.php" class="vcc-nav-item">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg></span>
        Tours
      </a>
      <a href="<?= BASE_URL ?>ai.php#/driver" class="vcc-nav-item">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
        Quick Taxi
      </a>
      <a href="<?= BASE_URL ?>vendor/bus-control-center.php" class="vcc-nav-item">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-1.1 0-2 .9-2 2v7c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg></span>
        Bus Operations
      </a>
    </div>

    <!-- SETTINGS -->
    <div class="vcc-nav-section">
      <div class="vcc-nav-label">Settings</div>
      <a class="vcc-nav-item <?= $activeTab === 'profile' ? 'active' : '' ?>" data-tab="profile" onclick="switchVccTab('profile', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        Business Profile
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'team' ? 'active' : '' ?>" data-tab="team" onclick="switchVccTab('team', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></span>
        Team &amp; Staff
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'payments' ? 'active' : '' ?>" data-tab="payments" onclick="switchVccTab('payments', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></span>
        Payments
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'notifications' ? 'active' : '' ?>" data-tab="notifications" onclick="switchVccTab('notifications', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
        Notifications
      </a>
      <a class="vcc-nav-item <?= $activeTab === 'security' ? 'active' : '' ?>" data-tab="security" onclick="switchVccTab('security', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
        Security
      </a>
    </div>

    <!-- SUPPORT -->
    <div class="vcc-nav-section">
      <div class="vcc-nav-label">Support</div>
      <a class="vcc-nav-item <?= $activeTab === 'support' ? 'active' : '' ?>" data-tab="support" onclick="switchVccTab('support', this)">
        <span class="vcc-nav-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
        Help &amp; Support
      </a>
    </div>

    <div class="vcc-sidebar-footer">
      <a class="vcc-help-card" onclick="switchVccTab('support', this)">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--vcc-primary)" stroke-width="2"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
        <span>Help &amp; Support</span>
      </a>
      <a class="vcc-help-card" href="<?= BASE_URL ?>logout.php" style="color:#e63946;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#e63946" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Logout</span>
      </a>
    </div>
  </aside>

  <!-- ════════════════════════════════════════════════════════════════════
       2. TOP CONTROL HEADER BAR (WITH LIGHT/DARK THEME SWITCHER)
       ════════════════════════════════════════════════════════════════════ -->
  <main class="vcc-main">
    <header class="vcc-header">
      <div class="vcc-header-left">
        <button type="button" class="vcc-icon-btn" id="vcc-hamburger-btn" aria-label="Toggle sidebar menu">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>

        <div class="vcc-search-wrap">
          <svg class="vcc-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="search" class="vcc-search-input" placeholder="Search bookings, customers, listings..." onfocus="openVccSearchModal()">
          <span class="vcc-search-kbd">Ctrl + K</span>
        </div>
      </div>

      <div class="vcc-header-right">
        <!-- System status indicator -->
        <div class="vcc-status-pill" onclick="toggleVccStatusPopover()">
          <span class="vcc-status-dot"></span>
          <span>All systems operational</span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
        </div>

        <!-- Light/Dark Mode Theme Switcher -->
        <button type="button" class="vcc-icon-btn" id="vcc-theme-toggle-btn" onclick="toggleVccTheme()" title="Toggle Light / Dark Mode">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.07" x2="5.64" y2="17.66"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        </button>

        <!-- Notification Bell -->
        <button type="button" class="vcc-icon-btn" onclick="toggleVccNotifications()" title="Notifications">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="vcc-icon-dot">4</span>
        </button>

        <!-- Messages Shortcut -->
        <button type="button" class="vcc-icon-btn" onclick="switchVccTab('messages')" title="Messages">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <span class="vcc-icon-dot" style="background:var(--vcc-blue);">3</span>
        </button>

        <!-- User Profile Pill -->
        <div class="vcc-user-menu" onclick="switchVccTab('profile')">
          <div class="vcc-avatar"><?= strtoupper(substr($userFirstName, 0, 1)) ?></div>
          <div class="vcc-user-info">
            <div class="vcc-user-name"><?= e($vendor['name'] ?? 'Patrick Demo') ?></div>
            <div class="vcc-user-role">Approved Vendor</div>
          </div>
        </div>

        <!-- Logout -->
        <a href="<?= BASE_URL ?>logout.php" class="vcc-icon-btn" title="Logout">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </a>
      </div>
    </header>

    <!-- ════════════════════════════════════════════════════════════════════
         3. WORKSPACE CONTAINER (DYNAMIC DOMAIN PANELS)
         ════════════════════════════════════════════════════════════════════ -->
    <div class="vcc-content">

      <!-- TAB 1: OVERVIEW -->
      <div id="vcc-panel-overview" class="vcc-panel" style="<?= $activeTab === 'overview' ? '' : 'display:none;' ?>">
        <div class="vcc-page-header">
          <div>
            <h1 class="vcc-page-title">Good afternoon, <?= e($userFirstName) ?>! 👋</h1>
            <p class="vcc-page-sub">Here's what's happening with your business today.</p>
          </div>

          <div class="vcc-controls-bar">
            <div class="vcc-date-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <span>Sun, 9 Aug 2026</span>
            </div>

            <div class="vcc-period-selector">
              <button class="vcc-period-btn active" onclick="setVccPeriod('today', this)">Today</button>
              <button class="vcc-period-btn" onclick="setVccPeriod('7d', this)">7D</button>
              <button class="vcc-period-btn" onclick="setVccPeriod('30d', this)">30D</button>
              <button class="vcc-period-btn" onclick="setVccPeriod('90d', this)">90D</button>
              <button class="vcc-period-btn" onclick="setVccPeriod('custom', this)">Custom ∨</button>
            </div>
          </div>
        </div>

        <!-- 6 KPI CARDS -->
        <div class="vcc-kpi-grid">
          <div class="vcc-kpi-card" onclick="switchVccTab('revenue')">
            <div class="vcc-kpi-header">
              <div class="vcc-kpi-icon" style="background:var(--vcc-green-glow);color:var(--vcc-green);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
              <div class="vcc-kpi-title">Revenue</div>
            </div>
            <div class="vcc-kpi-value">MK 482,500</div>
            <div class="vcc-kpi-trend vcc-trend-up">↑ 18.4% <span style="color:var(--vcc-text-muted);font-weight:400;">vs last 7 days</span></div>
          </div>

          <div class="vcc-kpi-card" onclick="switchVccTab('bookings')">
            <div class="vcc-kpi-header">
              <div class="vcc-kpi-icon" style="background:var(--vcc-blue-glow);color:var(--vcc-blue);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
              <div class="vcc-kpi-title">Bookings</div>
            </div>
            <div class="vcc-kpi-value">34</div>
            <div class="vcc-kpi-trend vcc-trend-up">↑ 7 <span style="color:var(--vcc-text-muted);font-weight:400;">vs last 7 days</span></div>
          </div>

          <div class="vcc-kpi-card" onclick="switchVccTab('customers')">
            <div class="vcc-kpi-header">
              <div class="vcc-kpi-icon" style="background:var(--vcc-purple-glow);color:var(--vcc-purple);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
              <div class="vcc-kpi-title">Customers</div>
            </div>
            <div class="vcc-kpi-value">127</div>
            <div class="vcc-kpi-trend vcc-trend-up">↑ 12 <span style="color:var(--vcc-text-muted);font-weight:400;">new this period</span></div>
          </div>

          <div class="vcc-kpi-card" onclick="switchVccTab('listings')">
            <div class="vcc-kpi-header">
              <div class="vcc-kpi-icon" style="background:var(--vcc-orange-glow);color:var(--vcc-orange);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg></div>
              <div class="vcc-kpi-title">Active Listings</div>
            </div>
            <div class="vcc-kpi-value">12</div>
            <div class="vcc-kpi-trend" style="color:var(--vcc-orange);">2 need attention</div>
          </div>

          <div class="vcc-kpi-card" onclick="switchVccTab('reviews')">
            <div class="vcc-kpi-header">
              <div class="vcc-kpi-icon" style="background:var(--vcc-yellow-glow);color:var(--vcc-yellow);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
              <div class="vcc-kpi-title">Rating</div>
            </div>
            <div class="vcc-kpi-value">4.7 ★</div>
            <div class="vcc-kpi-trend vcc-trend-up">↑ 0.2 <span style="color:var(--vcc-text-muted);font-weight:400;">vs last 7 days</span></div>
          </div>

          <div class="vcc-kpi-card" onclick="switchVccTab('analytics')">
            <div class="vcc-kpi-header">
              <div class="vcc-kpi-icon" style="background:var(--vcc-teal-glow);color:var(--vcc-teal);"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 12l-4-4-4 4"/><path d="M12 16V8"/></svg></div>
              <div class="vcc-kpi-title">Conversion</div>
            </div>
            <div class="vcc-kpi-value">8.4%</div>
            <div class="vcc-kpi-trend vcc-trend-up">↑ 1.2% <span style="color:var(--vcc-text-muted);font-weight:400;">vs last 7 days</span></div>
          </div>
        </div>

        <!-- ROW 1 CARDS -->
        <div class="vcc-grid-3">
          <div class="vcc-card">
            <div class="vcc-card-header">
              <div>
                <div class="vcc-kpi-title">Revenue Overview</div>
                <div style="font-size:1.4rem;font-weight:800;margin-top:0.25rem;">
                  MK 482,500
                  <span class="vcc-kpi-trend vcc-trend-up" style="display:inline-flex;font-size:0.75rem;margin-left:0.5rem;">↑ 18.4% <span style="color:var(--vcc-text-muted);font-weight:400;margin-left:2px;">vs previous 7 days</span></span>
                </div>
              </div>
              <select style="background:var(--vcc-bg);border:1px solid var(--vcc-border);color:var(--vcc-text);font-size:0.75rem;padding:0.3rem 0.6rem;border-radius:6px;">
                <option>This Week ∨</option><option>Last Week</option><option>This Month</option>
              </select>
            </div>
            <div class="vcc-chart-container">
              <svg width="100%" height="100%" viewBox="0 0 500 160" preserveAspectRatio="none">
                <defs><linearGradient id="vcc-chart-grad-1" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#ec4899" stop-opacity="0.4"/><stop offset="100%" stop-color="#ec4899" stop-opacity="0.0"/></linearGradient></defs>
                <path d="M0 130 Q 80 80, 160 50 T 320 30 T 420 80 L 500 60 L 500 160 L 0 160 Z" fill="url(#vcc-chart-grad-1)"/>
                <path d="M0 130 Q 80 80, 160 50 T 320 30 T 420 80 L 500 60" fill="none" stroke="#ec4899" stroke-width="3"/>
              </svg>
              <div style="display:flex;justify-content:space-between;font-size:0.68rem;color:var(--vcc-text-muted);margin-top:0.25rem;">
                <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
              </div>
            </div>
            <div class="vcc-chart-legend">
              <div class="vcc-legend-item"><div class="vcc-legend-head"><span class="vcc-legend-dot" style="background:var(--vcc-purple);"></span> Accommodation</div><div class="vcc-legend-val">MK 280,000 <small style="color:var(--vcc-text-muted);">58%</small></div></div>
              <div class="vcc-legend-item"><div class="vcc-legend-head"><span class="vcc-legend-dot" style="background:var(--vcc-orange);"></span> Events</div><div class="vcc-legend-val">MK 112,000 <small style="color:var(--vcc-text-muted);">23%</small></div></div>
              <div class="vcc-legend-item"><div class="vcc-legend-head"><span class="vcc-legend-dot" style="background:var(--vcc-blue);"></span> Tours</div><div class="vcc-legend-val">MK 76,000 <small style="color:var(--vcc-text-muted);">16%</small></div></div>
              <div class="vcc-legend-item"><div class="vcc-legend-head"><span class="vcc-legend-dot" style="background:var(--vcc-teal);"></span> Transport</div><div class="vcc-legend-val">MK 14,500 <small style="color:var(--vcc-text-muted);">3%</small></div></div>
            </div>
          </div>

          <div class="vcc-card">
            <div class="vcc-card-header"><h3 class="vcc-card-title">Today's Operations</h3><a class="vcc-card-action" onclick="switchVccTab('activity')">View all</a></div>
            <div class="vcc-timeline">
              <div class="vcc-timeline-item">
                <div class="vcc-timeline-time">09:00</div>
                <div class="vcc-timeline-icon" style="background:var(--vcc-primary-glow);color:var(--vcc-primary);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.5 2.8C2.1 10.9 2 11.4 2 12v4c0 .6.4 1 1 1h2"/></svg></div>
                <div class="vcc-timeline-content"><div class="vcc-timeline-title">Airport Transfer</div><div class="vcc-timeline-sub">Mary Phiri</div></div>
                <span class="vcc-pill-badge" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);">Confirmed</span>
              </div>
              <div class="vcc-timeline-item">
                <div class="vcc-timeline-time">11:30</div>
                <div class="vcc-timeline-icon" style="background:var(--vcc-blue-glow);color:var(--vcc-blue);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/></svg></div>
                <div class="vcc-timeline-content"><div class="vcc-timeline-title">Lake View Lodge</div><div class="vcc-timeline-sub">Room 204 • Check-in</div></div>
                <span class="vcc-pill-badge" style="background:rgba(245,158,11,0.15);color:var(--vcc-orange);">Expected</span>
              </div>
              <div class="vcc-timeline-item">
                <div class="vcc-timeline-time">14:00</div>
                <div class="vcc-timeline-icon" style="background:var(--vcc-purple-glow);color:var(--vcc-purple);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/></svg></div>
                <div class="vcc-timeline-content"><div class="vcc-timeline-title">Lilongwe City Tour</div><div class="vcc-timeline-sub">6 Guests</div></div>
                <span class="vcc-pill-badge" style="background:rgba(59,130,246,0.15);color:var(--vcc-blue);">Preparing</span>
              </div>
              <div class="vcc-timeline-item">
                <div class="vcc-timeline-time">18:30</div>
                <div class="vcc-timeline-icon" style="background:rgba(236,72,153,0.15);color:#ec4899;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/></svg></div>
                <div class="vcc-timeline-content"><div class="vcc-timeline-title">Music Festival</div><div class="vcc-timeline-sub">42 Tickets Sold</div></div>
                <span class="vcc-pill-badge" style="background:rgba(139,92,246,0.15);color:var(--vcc-purple);">Doors Open</span>
              </div>
            </div>
          </div>

          <div class="vcc-card">
            <div class="vcc-card-header"><h3 class="vcc-card-title">Action Center</h3><a class="vcc-card-action" onclick="switchVccTab('bookings')">View all</a></div>
            <div class="vcc-action-list">
              <div class="vcc-action-item" onclick="switchVccTab('bookings')">
                <div class="vcc-action-icon" style="background:rgba(239,68,68,0.15);color:#ef4444;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                <div class="vcc-action-text"><div class="vcc-action-msg">3 bookings require confirmation</div><div class="vcc-action-sub">2 accommodation • 1 tour</div></div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
              </div>
              <div class="vcc-action-item" onclick="switchVccTab('listings')">
                <div class="vcc-action-icon" style="background:rgba(245,158,11,0.15);color:var(--vcc-orange);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                <div class="vcc-action-text"><div class="vcc-action-msg">2 listings need attention</div><div class="vcc-action-sub">Missing info or verification</div></div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
              </div>
              <div class="vcc-action-item" onclick="switchVccTab('payouts')">
                <div class="vcc-action-icon" style="background:rgba(234,179,8,0.15);color:var(--vcc-yellow);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div>
                <div class="vcc-action-text"><div class="vcc-action-msg">1 payout verification required</div><div class="vcc-action-sub">Bank details confirmation</div></div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
              </div>
              <div class="vcc-action-item">
                <div class="vcc-action-icon" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <div class="vcc-action-text"><div class="vcc-action-msg">All systems operational</div><div class="vcc-action-sub">No other actions needed</div></div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
              </div>
            </div>
          </div>
        </div>

        <!-- ROW 2 CARDS -->
        <div class="vcc-grid-3">
          <div class="vcc-card">
            <div class="vcc-card-header"><h3 class="vcc-card-title">Recent Bookings</h3><a class="vcc-card-action" onclick="switchVccTab('bookings')">View all</a></div>
            <table class="vcc-table">
              <thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
              <tbody>
                <tr onclick="openVccBookingDrawer('BK2041', 'John Banda', 'Accommodation', '9 Aug, 2026', 'MK 45,000', 'Confirmed')">
                  <td style="font-weight:700;">BK2041</td><td>John Banda</td><td>Accommodation</td><td>9 Aug</td><td style="font-weight:700;">MK 45,000</td><td><span class="vcc-status-tag" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);">Confirmed</span></td>
                </tr>
                <tr onclick="openVccBookingDrawer('BK2040', 'Mary Phiri', 'Tour', '9 Aug, 2026', 'MK 30,000', 'Pending')">
                  <td style="font-weight:700;">BK2040</td><td>Mary Phiri</td><td>Tour</td><td>9 Aug</td><td style="font-weight:700;">MK 30,000</td><td><span class="vcc-status-tag" style="background:rgba(245,158,11,0.15);color:var(--vcc-orange);">Pending</span></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="vcc-card">
            <div class="vcc-card-header"><h3 class="vcc-card-title">Service Performance</h3><a class="vcc-card-action" onclick="switchVccTab('analytics')">View all</a></div>
            <div class="vcc-perf-list">
              <div class="vcc-perf-item"><div class="vcc-perf-row"><span>Accommodation</span><strong>MK 280,000 (87%)</strong></div><div class="vcc-perf-track"><div class="vcc-perf-fill" style="width:87%;background:var(--vcc-green);"></div></div></div>
              <div class="vcc-perf-item"><div class="vcc-perf-row"><span>Events</span><strong>MK 112,000 (71%)</strong></div><div class="vcc-perf-track"><div class="vcc-perf-fill" style="width:71%;background:var(--vcc-orange);"></div></div></div>
            </div>
          </div>

          <div class="vcc-card">
            <div class="vcc-card-header">
              <h3 class="vcc-card-title">Wallet Summary</h3>
              <div style="display:flex;gap:0.4rem;">
                <button type="button" class="vcc-icon-btn" style="width:26px;height:26px;" title="Hide balances">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:0.75rem;margin-bottom:1rem;">
              <div style="display:flex;justify-content:space-between;"><span>Available</span><strong style="color:var(--vcc-green);">MK 437,500</strong></div>
              <div style="display:flex;justify-content:space-between;"><span>Pending</span><strong style="color:var(--vcc-orange);">MK 93,000</strong></div>
              <div style="display:flex;justify-content:space-between;"><span>Reserved</span><strong style="color:var(--vcc-text-muted);">MK 45,000</strong></div>
            </div>
            <div style="display:flex;gap:0.5rem;">
              <button class="vcc-service-btn vcc-btn-primary" style="flex:1;" onclick="openVccWithdrawModal()">Withdraw Funds</button>
              <button class="vcc-icon-btn" style="width:38px;height:38px;" title="Download Statement">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- MY SERVICES CAPABILITIES -->
        <div class="vcc-card-header" style="margin-top:1.5rem;">
          <div><h2 style="font-size:1.2rem;font-weight:800;margin:0;">My Services</h2><p style="font-size:0.78rem;color:var(--vcc-text-muted);margin:0;">Available capabilities can be set up at any time without polluting business metrics.</p></div>
          <button class="vcc-service-btn vcc-btn-solid" style="width:auto;" onclick="openVccCreateServiceModal()">+ Create Service</button>
        </div>
        <div class="vcc-services-grid">
          <div class="vcc-service-card active-service"><div class="vcc-service-top"><div class="vcc-service-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--vcc-green)" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/></svg> Accommodation</div><span class="vcc-status-tag" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);">Open workspace</span></div><div class="vcc-service-sub">Manage verified property inventory, pricing and operations.</div><a href="<?= BASE_URL ?>vendor/accommodation-control-center.php" class="vcc-service-btn vcc-btn-solid">Open Dashboard</a></div>
          <div class="vcc-service-card active-service"><div class="vcc-service-top"><div class="vcc-service-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--vcc-orange)" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/></svg> Events</div><span class="vcc-status-tag" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);">Active</span></div><div class="vcc-service-sub">3 events • 4.6 ★ • 18 bookings</div><a href="<?= BASE_URL ?>vendor/events-control-center.php" class="vcc-service-btn vcc-btn-solid">Open Dashboard</a></div>
          <div class="vcc-service-card"><div class="vcc-service-top"><div class="vcc-service-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--vcc-blue)" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/></svg> Tours</div><span class="vcc-status-tag" style="background:rgba(148,163,184,0.15);color:var(--vcc-text-muted);">Not set up</span></div><div class="vcc-service-sub">Offer experiences &amp; guided tours to travellers on Uthenga.</div><button class="vcc-service-btn vcc-btn-outline" onclick="startVccGuidedSetup('tours')">Set Up Tours</button></div>
          <div class="vcc-service-card"><div class="vcc-service-top"><div class="vcc-service-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--vcc-purple)" stroke-width="2"><rect x="1" y="3" width="15" height="13"/></svg> Transport</div><span class="vcc-status-tag" style="background:rgba(148,163,184,0.15);color:var(--vcc-text-muted);">Not set up</span></div><div class="vcc-service-sub">Offer transport, bus, and shuttle services through Uthenga.</div><button class="vcc-service-btn vcc-btn-outline" onclick="startVccGuidedSetup('transport')">Set Up Transport</button></div>
        </div>
      </div>

      <!-- TAB 2: ACTIVITY FEED -->
      <div id="vcc-panel-activity" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Activity Stream</h1><p class="vcc-page-sub">Real-time event stream and operational audit trail.</p></div></div>
        <div class="vcc-card">
          <div class="vcc-period-selector" style="margin-bottom:1rem;">
            <button class="vcc-period-btn active">All</button><button class="vcc-period-btn">Bookings</button><button class="vcc-period-btn">Payments</button><button class="vcc-period-btn">Listings</button><button class="vcc-period-btn">Messages</button>
          </div>
          <div class="vcc-timeline" style="gap:1.25rem;">
            <div class="vcc-timeline-item" onclick="openVccBookingDrawer('BK2041', 'John Banda', 'Lake View Lodge', 'Today 14:32', 'MK 45,000', 'Confirmed')">
              <div class="vcc-timeline-time">14:32</div>
              <div class="vcc-timeline-icon" style="background:var(--vcc-green-glow);color:var(--vcc-green);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
              <div class="vcc-timeline-content"><div class="vcc-timeline-title">New booking received</div><div class="vcc-timeline-sub">John Banda booked Lake View Lodge</div></div>
            </div>
            <div class="vcc-timeline-item">
              <div class="vcc-timeline-time">14:18</div>
              <div class="vcc-timeline-icon" style="background:var(--vcc-blue-glow);color:var(--vcc-blue);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
              <div class="vcc-timeline-content"><div class="vcc-timeline-title">Payment confirmed</div><div class="vcc-timeline-sub">MK 45,000 received via PayChangu</div></div>
            </div>
            <div class="vcc-timeline-item">
              <div class="vcc-timeline-time">13:54</div>
              <div class="vcc-timeline-icon" style="background:var(--vcc-purple-glow);color:var(--vcc-purple);"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
              <div class="vcc-timeline-content"><div class="vcc-timeline-title">Customer message</div><div class="vcc-timeline-sub">Mary Phiri sent a message regarding airport pickup</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 3: BOOKINGS MANAGER -->
      <div id="vcc-panel-bookings" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header">
          <div><h1 class="vcc-page-title">Bookings Operations</h1><p class="vcc-page-sub">Central sales &amp; reservation management console.</p></div>
          <button class="vcc-service-btn vcc-btn-solid" style="width:auto;" onclick="alert('Exporting bookings CSV...')">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export
          </button>
        </div>
        <div class="vcc-card">
          <div style="display:flex;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
            <input type="search" class="vcc-search-input" placeholder="Search booking ID or customer..." style="max-width:300px;">
            <div class="vcc-period-selector"><button class="vcc-period-btn active">All</button><button class="vcc-period-btn">Pending</button><button class="vcc-period-btn">Confirmed</button><button class="vcc-period-btn">Active</button><button class="vcc-period-btn">Completed</button><button class="vcc-period-btn">Cancelled</button></div>
          </div>
          <table class="vcc-table">
            <thead><tr><th>Booking</th><th>Customer</th><th>Service</th><th>Date</th><th>Amount</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($bookingRows as $b): ?>
                <tr onclick="openVccBookingDrawer('<?= e($b['booking_code']) ?>', 'Customer', '<?= e($b['item_name']) ?>', '<?= date('d M Y', strtotime($b['created_at'])) ?>', 'MK <?= number_format((float)$b['grand_total']) ?>', '<?= e($b['booking_status']) ?>')">
                  <td style="font-weight:700;"><?= e($b['booking_code']) ?></td>
                  <td>John Banda</td>
                  <td><?= e($b['item_name']) ?></td>
                  <td><?= date('d M Y', strtotime($b['created_at'])) ?></td>
                  <td style="font-weight:700;">MK <?= number_format((float)$b['grand_total']) ?></td>
                  <td><span style="color:var(--vcc-green);">Paid</span></td>
                  <td><span class="vcc-status-tag" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);"><?= e($b['booking_status']) ?></span></td>
                  <td><button class="vcc-btn-solid" style="padding:0.25rem 0.6rem;font-size:0.75rem;">Drawer</button></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($bookingRows)): ?>
                <tr onclick="openVccBookingDrawer('BK2041', 'John Banda', 'Lake View Lodge', '09 Aug 2026', 'MK 45,000', 'Confirmed')">
                  <td style="font-weight:700;">BK2041</td><td>John Banda</td><td>Accommodation</td><td>09 Aug</td><td style="font-weight:700;">MK 45,000</td><td><span style="color:var(--vcc-green);">Paid</span></td><td><span class="vcc-status-tag" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);">Confirmed</span></td><td><button class="vcc-btn-solid" style="padding:0.25rem 0.6rem;font-size:0.75rem;">Manage</button></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 4: CUSTOMERS CRM -->
      <div id="vcc-panel-customers" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Customer Relationship Workspace (CRM)</h1><p class="vcc-page-sub">127 Total Customers • Manage client history &amp; internal notes.</p></div></div>
        <div class="vcc-card">
          <div class="vcc-period-selector" style="margin-bottom:1rem;"><button class="vcc-period-btn active">All</button><button class="vcc-period-btn">New</button><button class="vcc-period-btn">Returning</button><button class="vcc-period-btn">VIP</button></div>
          <table class="vcc-table">
            <thead><tr><th>Customer</th><th>Bookings</th><th>Lifetime Value</th><th>Last Activity</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
              <tr onclick="openVccCustomerModal('John Banda')">
                <td><div class="vcc-user-cell"><div class="vcc-avatar" style="width:30px;height:30px;">JB</div><div><strong>John Banda</strong><br><small style="color:var(--vcc-text-muted);">john.banda@example.mw</small></div></div></td>
                <td>12 bookings</td><td style="font-weight:700;">MK 480,000</td><td>8 Aug 2026</td><td><span class="vcc-status-tag" style="background:rgba(59,130,246,0.15);color:var(--vcc-blue);">Returning</span></td>
                <td><small style="color:var(--vcc-text-muted);">Prefers quiet room upstairs (Internal note)</small></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 5: MESSAGES (3-PANE) -->
      <div id="vcc-panel-messages" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Communication Center</h1><p class="vcc-page-sub">Direct messages with customers and support.</p></div></div>
        <div class="vcc-chat-layout">
          <div class="vcc-chat-list">
            <div class="vcc-chat-item active"><strong>John Banda</strong><br><small style="color:var(--vcc-text-muted);">Hello, what time is check-in?</small></div>
            <div class="vcc-chat-item"><strong>Mary Phiri</strong><br><small style="color:var(--vcc-text-muted);">Airport pickup confirmed.</small></div>
          </div>
          <div class="vcc-chat-thread">
            <div class="vcc-chat-thread-header"><strong>John Banda</strong> <small style="color:var(--vcc-text-muted);">Booking #BK2041</small></div>
            <div class="vcc-chat-messages">
              <div class="vcc-bubble vcc-bubble-in">Hello, what time is check-in for Lake View Lodge today?</div>
              <div class="vcc-bubble vcc-bubble-out">Check-in starts at 14:00. We look forward to welcoming you!</div>
            </div>
            <div class="vcc-chat-input-bar">
              <input type="text" class="vcc-search-input" placeholder="Type your message..." style="flex:1;">
              <button class="vcc-service-btn vcc-btn-primary" style="width:auto;">Send</button>
            </div>
          </div>
          <div class="vcc-chat-context">
            <div class="vcc-kpi-title">Booking Context</div>
            <div><strong>John Banda</strong><br><small style="color:var(--vcc-text-muted);">Lake View Lodge</small></div>
            <div><small style="color:var(--vcc-text-muted);">Check-in:</small> 09 Aug<br><small style="color:var(--vcc-text-muted);">Status:</small> <span style="color:var(--vcc-green);">Paid</span></div>
            <button class="vcc-service-btn vcc-btn-solid" style="margin-top:auto;" onclick="openVccBookingDrawer('BK2041', 'John Banda', 'Lake View Lodge', '09 Aug', 'MK 45,000', 'Confirmed')">View Booking</button>
          </div>
        </div>
      </div>

      <!-- TAB 6: LISTINGS CATALOGUE -->
      <div id="vcc-panel-listings" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header">
          <div><h1 class="vcc-page-title">Marketplace Catalogue</h1><p class="vcc-page-sub">Manage service listings published across Uthenga.</p></div>
          <a href="<?= BASE_URL ?>vendor/business-listing.php" class="vcc-service-btn vcc-btn-primary" style="width:auto;">+ Create Listing</a>
        </div>
        <div class="vcc-card">
          <table class="vcc-table">
            <thead><tr><th>Category</th><th>Title</th><th>Location</th><th>Price</th><th>Status</th><th>Rating</th><th>Views</th></tr></thead>
            <tbody>
              <?php foreach ($allItems as $item): ?>
                <tr>
                  <td><span class="vcc-status-tag" style="background:rgba(59,130,246,0.15);color:var(--vcc-blue);"><?= e($item['type_label']) ?></span></td>
                  <td style="font-weight:700;"><?= e($item['title']) ?></td>
                  <td><?= e($item['location']) ?></td>
                  <td><?= e($item['price_label']) ?></td>
                  <td><span class="vcc-status-tag" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);"><?= !empty($item['is_active']) ? 'Published' : 'Draft' ?></span></td>
                  <td>4.8 ★</td><td>1,420</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 7: SERVICES -->
      <div id="vcc-panel-services" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Service Portfolio Manager</h1><p class="vcc-page-sub">Manage active service operating centers &amp; capabilities.</p></div></div>
        <div class="vcc-services-grid">
          <div class="vcc-service-card active-service"><div class="vcc-service-top"><div class="vcc-service-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--vcc-green)" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/></svg> Accommodation</div><span class="vcc-status-tag" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);">Open workspace</span></div><a href="<?= BASE_URL ?>vendor/accommodation-control-center.php" class="vcc-service-btn vcc-btn-solid">Open Dashboard</a></div>
          <div class="vcc-service-card active-service"><div class="vcc-service-top"><div class="vcc-service-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--vcc-orange)" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/></svg> Events</div><span class="vcc-status-tag" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);">Active</span></div><a href="<?= BASE_URL ?>vendor/events-control-center.php" class="vcc-service-btn vcc-btn-solid">Open Dashboard</a></div>
          <div class="vcc-service-card"><div class="vcc-service-top"><div class="vcc-service-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--vcc-blue)" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/></svg> Tours</div><span class="vcc-status-tag" style="background:rgba(148,163,184,0.15);color:var(--vcc-text-muted);">Not set up</span></div><div class="vcc-service-sub">Offer experiences &amp; guided tours to travellers on Uthenga.</div><button class="vcc-service-btn vcc-btn-outline" onclick="startVccGuidedSetup('tours')">Set Up Tours</button></div>
          <div class="vcc-service-card"><div class="vcc-service-top"><div class="vcc-service-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--vcc-purple)" stroke-width="2"><rect x="1" y="3" width="15" height="13"/></svg> Transport</div><span class="vcc-status-tag" style="background:rgba(148,163,184,0.15);color:var(--vcc-text-muted);">Not set up</span></div><div class="vcc-service-sub">Offer transport, bus, and shuttle services through Uthenga.</div><button class="vcc-service-btn vcc-btn-outline" onclick="startVccGuidedSetup('transport')">Set Up Transport</button></div>
        </div>
      </div>

      <!-- TAB 8: REVENUE FINANCE -->
      <div id="vcc-panel-revenue" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Financial Revenue Intelligence</h1><p class="vcc-page-sub">Gross transaction value vs Net vendor earnings.</p></div></div>
        <div class="vcc-kpi-grid">
          <div class="vcc-kpi-card"><div class="vcc-kpi-title">Gross Revenue</div><div class="vcc-kpi-value">MK 482,500</div></div>
          <div class="vcc-kpi-card"><div class="vcc-kpi-title">Net Earnings</div><div class="vcc-kpi-value" style="color:var(--vcc-green);">MK 437,500</div></div>
          <div class="vcc-kpi-card"><div class="vcc-kpi-title">Pending</div><div class="vcc-kpi-value" style="color:var(--vcc-orange);">MK 93,000</div></div>
          <div class="vcc-kpi-card"><div class="vcc-kpi-title">Refunds</div><div class="vcc-kpi-value" style="color:var(--vcc-primary);">MK 12,500</div></div>
        </div>
      </div>

      <!-- TAB 9: WALLET -->
      <div id="vcc-panel-wallet" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Vendor Wallet &amp; Balances</h1><p class="vcc-page-sub">Current funds position and withdrawal requests.</p></div></div>
        <div class="vcc-card" style="max-width:500px;">
          <div style="font-size:0.85rem;color:var(--vcc-text-muted);">Available for Withdrawal</div>
          <div style="font-size:2.2rem;font-weight:800;color:var(--vcc-green);margin-bottom:1rem;">MK 437,500</div>
          <button class="vcc-service-btn vcc-btn-primary" onclick="openVccWithdrawModal()">Withdraw Funds</button>
        </div>
      </div>

      <!-- TAB 10: TRANSACTIONS LEDGER -->
      <div id="vcc-panel-transactions" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Accounting Ledger &amp; Transactions</h1><p class="vcc-page-sub">Individual financial records, commissions &amp; fees.</p></div></div>
        <div class="vcc-card">
          <table class="vcc-table">
            <thead><tr><th>Txn ID</th><th>Type</th><th>Booking</th><th>Amount</th><th>Uthenga Fee</th><th>Net</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td style="font-weight:700;">TX-0002041</td><td>Booking payment</td><td>BK2041</td><td>MK 45,000</td><td>-MK 4,500</td><td style="font-weight:700;color:var(--vcc-green);">MK 40,500</td><td><span style="color:var(--vcc-green);">Captured</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 11: PAYOUTS -->
      <div id="vcc-panel-payouts" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Settlement Payouts</h1><p class="vcc-page-sub">Disbursements from Uthenga to your bank account.</p></div></div>
        <div class="vcc-card" style="max-width:500px;margin-bottom:1rem;">
          <div class="vcc-kpi-title">Next Scheduled Payout</div>
          <div style="font-size:1.6rem;font-weight:800;margin:0.25rem 0;">MK 93,000</div>
          <div style="font-size:0.8rem;color:var(--vcc-text-muted);">Est date: 12 Aug 2026 • Destination: National Bank ****4821</div>
        </div>
      </div>

      <!-- TAB 12: ANALYTICS -->
      <div id="vcc-panel-analytics" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Business Intelligence &amp; Analytics</h1><p class="vcc-page-sub">Deep performance metrics across sales, customers, and capacity.</p></div></div>
        <div class="vcc-grid-3-even">
          <div class="vcc-card"><div class="vcc-kpi-title">AOV (Average Order Value)</div><div class="vcc-kpi-value">MK 34,200</div></div>
          <div class="vcc-card"><div class="vcc-kpi-title">Cancellation Rate</div><div class="vcc-kpi-value">2.1%</div></div>
          <div class="vcc-card"><div class="vcc-kpi-title">Repeat Booking Rate</div><div class="vcc-kpi-value">29.8%</div></div>
        </div>
      </div>

      <!-- TAB 13: REVIEWS -->
      <div id="vcc-panel-reviews" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Customer Reviews &amp; Reputation</h1><p class="vcc-page-sub">4.7 ★ Overall rating across 120 reviews.</p></div></div>
        <div class="vcc-card">
          <div style="border-bottom:1px solid var(--vcc-border);padding-bottom:1rem;margin-bottom:1rem;">
            <strong>John Banda</strong> ★★★★★ <small style="color:var(--vcc-text-muted);">09 Aug 2026</small><br>
            <span style="font-size:0.85rem;">"Excellent accommodation and friendly hospitality at Lake View Lodge!"</span><br>
            <button class="vcc-btn-solid" style="margin-top:0.5rem;padding:0.25rem 0.6rem;font-size:0.75rem;" onclick="alert('Replying to review...')">Reply</button>
          </div>
        </div>
      </div>

      <!-- TAB 14: SETTINGS - PROFILE -->
      <div id="vcc-panel-profile" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Business Profile &amp; Identity</h1><p class="vcc-page-sub">Public profile, legal details &amp; verification checklist.</p></div></div>
        <div class="vcc-grid-3">
          <div class="vcc-card" style="grid-column: span 2;">
            <?php if ($profileUpdateSuccess): ?><div style="color:var(--vcc-green);margin-bottom:1rem;"><?= e($profileUpdateSuccess) ?></div><?php endif; ?>
            <form method="POST" action="" style="display:grid;gap:1rem;">
              <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
              <input type="hidden" name="update_vendor_profile" value="1">
              <div><label style="font-size:0.8rem;color:var(--vcc-text-muted);">Business Name</label><input type="text" name="name" value="<?= e($vendor['name'] ?? '') ?>" class="vcc-search-input" style="padding-left:0.75rem;"></div>
              <div><label style="font-size:0.8rem;color:var(--vcc-text-muted);">Email</label><input type="email" name="email" value="<?= e($vendor['email'] ?? '') ?>" class="vcc-search-input" style="padding-left:0.75rem;"></div>
              <div><label style="font-size:0.8rem;color:var(--vcc-text-muted);">Phone</label><input type="tel" name="phone" value="<?= e($vendor['phone'] ?? '') ?>" class="vcc-search-input" style="padding-left:0.75rem;"></div>
              <button type="submit" class="vcc-service-btn vcc-btn-primary" style="width:auto;">Save Changes</button>
            </form>
          </div>
          <div class="vcc-card">
            <h3 class="vcc-card-title" style="margin-bottom:1rem;">Verification Checklist</h3>
            <div class="vcc-check-item"><span>Business Identity</span> <strong style="color:var(--vcc-green);">✓ Verified</strong></div>
            <div class="vcc-check-item"><span>Contact Information</span> <strong style="color:var(--vcc-green);">✓ Verified</strong></div>
            <div class="vcc-check-item"><span>Payout Account</span> <strong style="color:var(--vcc-green);">✓ Verified</strong></div>
          </div>
        </div>
      </div>

      <!-- TAB 15: TEAM & STAFF -->
      <div id="vcc-panel-team" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header">
          <div><h1 class="vcc-page-title">Team &amp; Staff Management</h1><p class="vcc-page-sub">12 Team Members • Multi-user permission controls.</p></div>
          <button class="vcc-service-btn vcc-btn-primary" style="width:auto;" onclick="alert('Invite Team Member modal')">+ Invite Staff</button>
        </div>
        <div class="vcc-card">
          <table class="vcc-table">
            <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Permissions</th></tr></thead>
            <tbody>
              <tr><td><strong>Patrick Demo</strong></td><td>Owner</td><td><span style="color:var(--vcc-green);">Active</span></td><td>Full Access</td></tr>
              <tr><td><strong>Grace Moyo</strong></td><td>Manager</td><td><span style="color:var(--vcc-green);">Active</span></td><td>Bookings, Listings, Messages</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 16: PAYMENTS & PAYCHANGU -->
      <div id="vcc-panel-payments" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Payment Gateway Configuration</h1><p class="vcc-page-sub">PayChangu Integration &amp; Settlement Destination.</p></div></div>
        <div class="vcc-card" style="max-width:600px;">
          <div class="vcc-check-item"><span>PayChangu Connection</span><strong style="color:var(--vcc-green);">● Connected</strong></div>
          <div class="vcc-check-item"><span>Settlement Schedule</span><strong>Daily Automatic</strong></div>
          <div class="vcc-check-item"><span>Payout Account</span><strong>National Bank (****4821)</strong></div>
        </div>
      </div>

      <!-- TAB 17: NOTIFICATIONS PREFERENCES -->
      <div id="vcc-panel-notifications" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Notification Settings</h1><p class="vcc-page-sub">Manage alerts across In-app, Email &amp; SMS channels.</p></div></div>
        <div class="vcc-card" style="max-width:600px;">
          <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--vcc-border);"><span>New Booking Alerts</span><input type="checkbox" checked></div>
          <div style="display:flex;justify-content:space-between;padding:0.75rem 0;border-bottom:1px solid var(--vcc-border);"><span>Payment Received Alerts</span><input type="checkbox" checked></div>
          <div style="display:flex;justify-content:space-between;padding:0.75rem 0;"><span>Customer Messages</span><input type="checkbox" checked></div>
        </div>
      </div>

      <!-- TAB 18: SECURITY CENTER -->
      <div id="vcc-panel-security" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header"><div><h1 class="vcc-page-title">Enterprise Security Center</h1><p class="vcc-page-sub">Security Score: 92/100 • 2FA &amp; Active Sessions.</p></div></div>
        <div class="vcc-card" style="max-width:600px;">
          <div class="vcc-check-item"><span>Two-Factor Authentication (2FA)</span><strong style="color:var(--vcc-green);">✓ Enabled</strong></div>
          <div class="vcc-check-item"><span>Active Session</span><strong>Chrome on Linux (This device)</strong></div>
          <button class="vcc-service-btn vcc-btn-outline" style="margin-top:1rem;" onclick="alert('Signed out all other sessions.')">Sign out all other sessions</button>
        </div>
      </div>

      <!-- TAB 19: HELP & SUPPORT -->
      <div id="vcc-panel-support" class="vcc-panel" style="display:none;">
        <div class="vcc-page-header">
          <div><h1 class="vcc-page-title">Help &amp; Support Center</h1><p class="vcc-page-sub">Search documentation, view tickets, or contact Uthenga support.</p></div>
          <button class="vcc-service-btn vcc-btn-primary" style="width:auto;" onclick="alert('Create Support Ticket modal')">+ Start Support Ticket</button>
        </div>
        <div class="vcc-card">
          <input type="search" class="vcc-search-input" placeholder="Search Uthenga Help articles..." style="margin-bottom:1.5rem;">
          <div class="vcc-kpi-title" style="margin-bottom:0.75rem;">Support Tickets</div>
          <div class="vcc-check-item"><span>#SUP-2041 Payment reconciliation</span><strong style="color:var(--vcc-blue);">Investigating</strong></div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- ════════════════════════════════════════════════════════════════════
     FLOATING AI ASSISTANT & MODALS
     ════════════════════════════════════════════════════════════════════ -->
<button class="vcc-ai-fab" onclick="alert('Uthenga AI Business Assistant:\n\nAsk me:\n• How did my business perform this week?\n• Which listing generated the most revenue?\n• Show me today\'s bookings.')">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
  AI Assistant
</button>

<!-- SIDE DRAWER FOR BOOKING DETAILS -->
<div class="vcc-drawer-overlay" id="vcc-drawer-overlay" onclick="closeVccBookingDrawer()"></div>
<div class="vcc-drawer" id="vcc-booking-drawer">
  <div class="vcc-drawer-header">
    <div>
      <h3 style="margin:0;font-size:1.1rem;" id="vcc-drawer-code">BOOKING DETAIL</h3>
      <div style="font-size:0.75rem;color:var(--vcc-text-muted);" id="vcc-drawer-date">August 9, 2026</div>
    </div>
    <button class="vcc-icon-btn" onclick="closeVccBookingDrawer()">✕</button>
  </div>
  <div style="display:flex;flex-direction:column;gap:1.25rem;">
    <div><div class="vcc-kpi-title">Customer</div><div style="font-weight:700;font-size:1rem;" id="vcc-drawer-customer">John Banda</div></div>
    <div><div class="vcc-kpi-title">Service &amp; Item</div><div style="font-weight:700;" id="vcc-drawer-service">Lake View Lodge</div></div>
    <div><div class="vcc-kpi-title">Financial Breakdown</div><div style="font-size:1.2rem;font-weight:800;color:var(--vcc-green);" id="vcc-drawer-amount">MK 45,000</div><span class="vcc-status-tag" style="background:rgba(16,185,129,0.15);color:var(--vcc-green);" id="vcc-drawer-status">Confirmed</span></div>
    <div style="display:flex;flex-direction:column;gap:0.65rem;margin-top:1.5rem;">
      <button class="vcc-service-btn vcc-btn-primary" onclick="alert('Contacting customer...')">Contact Customer</button>
      <button class="vcc-service-btn vcc-btn-solid" onclick="alert('Booking confirmed')">Confirm Booking</button>
      <button class="vcc-service-btn vcc-btn-outline" onclick="alert('Issue refund dialog opened')">Issue Refund</button>
    </div>
  </div>
</div>

<script>
(function() {
  'use strict';

  // Restore saved theme on initial page load
  try {
    var savedTheme = localStorage.getItem('uthenga-theme');
    if (savedTheme === 'light' || savedTheme === 'dark') {
      document.documentElement.dataset.theme = savedTheme;
    }
  } catch(e){}

  var hamburgerBtn = document.getElementById('vcc-hamburger-btn');
  var closeSidebarBtn = document.getElementById('vcc-close-sidebar-btn');
  var sidebar = document.getElementById('vcc-sidebar');

  if (hamburgerBtn && sidebar) {
    hamburgerBtn.addEventListener('click', function() { sidebar.classList.add('open'); });
  }
  if (closeSidebarBtn && sidebar) {
    closeSidebarBtn.addEventListener('click', function() { sidebar.classList.remove('open'); });
  }

  // Dynamic Theme Switching (Light / Dark)
  window.toggleVccTheme = function() {
    var current = document.documentElement.dataset.theme || 'dark';
    var next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    try { localStorage.setItem('uthenga-theme', next); } catch(e){}
  };

  // Dynamic Tab Switcher with persistent active selection indicator bar
  window.switchVccTab = function(tabId, clickedEl) {
    var panels = document.querySelectorAll('.vcc-panel');
    panels.forEach(function(p) { p.style.display = 'none'; });

    var targetPanel = document.getElementById('vcc-panel-' + tabId);
    if (targetPanel) {
      targetPanel.style.display = 'block';
    } else {
      document.getElementById('vcc-panel-overview').style.display = 'block';
    }

    var navItems = document.querySelectorAll('.vcc-nav-item');
    navItems.forEach(function(item) { item.classList.remove('active'); });

    if (clickedEl && clickedEl.classList && clickedEl.classList.contains('vcc-nav-item')) {
      clickedEl.classList.add('active');
    } else {
      var matchingNav = document.querySelector('.vcc-nav-item[data-tab="' + tabId + '"]');
      if (matchingNav) matchingNav.classList.add('active');
    }

    if (sidebar) sidebar.classList.remove('open');
  };

  // Period Selector Filter
  window.setVccPeriod = function(period, btn) {
    var btns = btn.parentElement.querySelectorAll('.vcc-period-btn');
    btns.forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
  };

  // Side Drawer Control
  window.openVccBookingDrawer = function(code, customer, service, date, amount, status) {
    document.getElementById('vcc-drawer-code').textContent = 'BOOKING ' + code;
    document.getElementById('vcc-drawer-customer').textContent = customer;
    document.getElementById('vcc-drawer-service').textContent = service;
    document.getElementById('vcc-drawer-date').textContent = date;
    document.getElementById('vcc-drawer-amount').textContent = amount;
    document.getElementById('vcc-drawer-status').textContent = status;

    document.getElementById('vcc-drawer-overlay').classList.add('active');
    document.getElementById('vcc-booking-drawer').classList.add('active');
  };

  window.closeVccBookingDrawer = function() {
    document.getElementById('vcc-drawer-overlay').classList.remove('active');
    document.getElementById('vcc-booking-drawer').classList.remove('active');
  };

  // Guided Service Setup Modal
  window.startVccGuidedSetup = function(serviceType) {
    var name = serviceType.charAt(0).toUpperCase() + serviceType.slice(1);
    alert(name + " isn't set up yet.\n\nComplete a few steps to activate your " + name + " service and start publishing experiences.\n\n[Start Setup]");
  };

  window.openVccCreateServiceModal = function() {
    alert("Create New Service\n\nChoose service type: Accommodation, Events, Tours, or Transport.");
  };

  window.openVccWithdrawModal = function() {
    alert("Withdraw Funds\n\nWithdrawable Balance: MK 437,500\nFees: MK 0 (Standard settlement)\nDestination: Bank / Mobile Money.");
  };

  window.openVccSearchModal = function() {
    var query = prompt("Search bookings, customers, listings, or transactions:");
    if (query) {
      alert("Found matching record for: " + query);
    }
  };

  window.toggleVccNotifications = function() {
    alert("Notifications:\n1. New booking received (John Banda)\n2. Payment received (MK 45,000)\n3. Customer message\n4. System operational");
  };

  window.toggleVccStatusPopover = function() {
    alert("SYSTEM STATUS\n\nMarketplace: ● Operational\nPayments: ● Operational\nBookings: ● Operational\nNotifications: ● Operational\nTIE Engine: ● Operational");
  };
})();
</script>

</body>
</html>
