<?php
/**
 * Uthenga - Admin Dashboard Sidebar
 * Expects $activeNav to determine highlighted option
 */
$activeNav = $activeNav ?? 'admin-dashboard';
$isSuperAdmin = ($_SESSION['user_role'] ?? '') === ROLE_SUPER_ADMIN;
require_once __DIR__ . '/admin_icons.php';

$menuGroups = $isSuperAdmin ? [
    [
        'label' => 'Core',
        'items' => [
            ['key' => 'super-dashboard', 'label' => 'System Dashboard', 'href' => 'admin/super-dashboard.php', 'icon' => 'grid'],
            ['key' => 'admin-users', 'label' => 'Admin Management', 'href' => 'admin/users.php', 'icon' => 'users'],
            ['key' => 'admin-security', 'label' => 'System Health', 'href' => 'admin/security.php', 'icon' => 'shield'],
            ['key' => 'admin-system-monitor', 'label' => 'System Monitor', 'href' => 'admin/system-monitor.php', 'icon' => 'activity'],
        ],
    ],
    [
        'label' => 'Platform',
        'items' => [
            ['key' => 'admin-dashboard', 'label' => 'Dashboard', 'href' => 'admin/dashboard.php', 'icon' => 'grid'],
            ['key' => 'admin-vendors', 'label' => 'Vendor Management', 'href' => 'admin/vendors.php', 'icon' => 'store'],
            ['key' => 'admin-bookings', 'label' => 'Bookings', 'href' => 'admin/bookings.php', 'icon' => 'file'],
            ['key' => 'admin-transactions', 'label' => 'Transactions', 'href' => 'admin/transactions.php', 'icon' => 'credit-card'],
            ['key' => 'admin-settlements', 'label' => 'Vendor Settlements', 'href' => 'admin/finance/settlements.php', 'icon' => 'wallet'],
            ['key' => 'admin-transaction-stats', 'label' => 'Transaction Stats', 'href' => 'admin/transaction-stats.php', 'icon' => 'chart'],
            ['key' => 'admin-promotions', 'label' => 'Promotional Popups', 'href' => 'admin/popup_manager.php', 'icon' => 'announcement'],
            ['key' => 'admin-marketing', 'label' => 'Marketing Control', 'href' => 'admin/marketing.php', 'icon' => 'announcement'],
            ['key' => 'admin-reports', 'label' => 'Reports', 'href' => 'admin/reports.php', 'icon' => 'chart'],
            ['key' => 'event-analytics', 'label' => 'Event Analytics', 'href' => 'admin/event-organizer-analytics.php', 'icon' => 'chart'],
        ],
    ],
    [
        'label' => 'Operations',
        'items' => [
            ['key' => 'admin-logs', 'label' => 'Audit Logs', 'href' => 'admin/logs.php', 'icon' => 'activity'],
            ['key' => 'admin-settings', 'label' => 'System Settings', 'href' => 'admin/settings.php', 'icon' => 'settings'],
            ['key' => 'admin-profile', 'label' => 'Profile', 'href' => 'admin/profile.php', 'icon' => 'user'],
        ],
    ],
] : [
    [
        'label' => 'Core',
        'items' => [
            ['key' => 'admin-dashboard', 'label' => 'Dashboard',       'href' => 'admin/dashboard.php',  'icon' => 'grid'],
            ['key' => 'admin-users',     'label' => 'Users',            'href' => 'admin/users.php',      'icon' => 'users'],
            ['key' => 'admin-vendors',   'label' => 'Vendors',          'href' => 'admin/vendors.php',    'icon' => 'store'],
            ['key' => 'admin-bookings',  'label' => 'Bookings',         'href' => 'admin/bookings.php',   'icon' => 'file'],
            ['key' => 'admin-transactions', 'label' => 'Transactions',      'href' => 'admin/transactions.php', 'icon' => 'credit-card'],
            ['key' => 'admin-settlements', 'label' => 'Vendor Settlements', 'href' => 'admin/finance/settlements.php', 'icon' => 'wallet'],
            ['key' => 'admin-transaction-stats', 'label' => 'Txn Statistics', 'href' => 'admin/transaction-stats.php', 'icon' => 'chart'],
        ],
    ],
    [
        'label' => 'Listings',
        'items' => [
            ['key' => 'admin-events',    'label' => 'Events',           'href' => 'admin/listings.php?type=event',   'icon' => 'calendar'],
            ['key' => 'admin-stays',     'label' => 'Accommodation',    'href' => 'admin/stays.php',      'icon' => 'home'],
            ['key' => 'admin-transport', 'label' => 'Transport',        'href' => 'admin/transport.php',  'icon' => 'transport'],
            ['key' => 'admin-tours',     'label' => 'Tours',            'href' => 'admin/listings.php?type=tour',    'icon' => 'map'],
        ],
    ],
    [
        'label' => 'Operations',
        'items' => [
            ['key' => 'admin-payments',  'label' => 'Payments',         'href' => 'admin/payments.php',   'icon' => 'credit-card'],
            ['key' => 'admin-promotions', 'label' => 'Promotional Popups', 'href' => 'admin/popup_manager.php', 'icon' => 'announcement'],
            ['key' => 'admin-marketing',  'label' => 'Marketing Control', 'href' => 'admin/marketing.php',   'icon' => 'announcement'],
            ['key' => 'admin-support',   'label' => 'Support Tickets',  'href' => 'admin/support.php',    'icon' => 'help'],
            ['key' => 'admin-reports',   'label' => 'Reports',          'href' => 'admin/reports.php',    'icon' => 'chart'],
            ['key' => 'event-analytics', 'label' => 'Event Analytics',   'href' => 'admin/event-organizer-analytics.php', 'icon' => 'chart'],
            ['key' => 'admin-logs',      'label' => 'Audit Logs',       'href' => 'admin/logs.php',       'icon' => 'activity'],
            ['key' => 'admin-system-monitor', 'label' => 'System Monitor', 'href' => 'admin/system-monitor.php', 'icon' => 'activity'],
        ],
    ],
    [
        'label' => 'Settings',
        'items' => [
            ['key' => 'admin-profile',  'label' => 'Profile',          'href' => 'admin/profile.php',   'icon' => 'user'],
            ['key' => 'admin-settings',  'label' => 'Settings',         'href' => 'admin/settings.php',   'icon' => 'settings'],
        ],
    ],
];
?>
<aside class="sidebar admin-sidebar" aria-label="Admin navigation">
  <div class="sidebar-hero">
    <div class="sidebar-hero-mark"><?php $logoSize = 'sm'; $logoLink = false; require __DIR__ . '/../../includes/logo.php'; ?></div>
    <div>
      <div class="sidebar-role-title"><?= $isSuperAdmin ? 'Super Admin' : 'Administrator' ?></div>
      <div class="sidebar-role-meta">Secure platform controls</div>
    </div>
  </div>

  <?php foreach ($menuGroups as $group): ?>
    <div class="sidebar-section">
      <div class="sidebar-label"><?= e($group['label']) ?></div>
      <?php foreach ($group['items'] as $item): ?>
        <?php $isActive = $activeNav === $item['key']; ?>
        <a href="<?= BASE_URL . $item['href'] ?>" class="sidebar-link <?= $isActive ? 'active' : '' ?>" id="side-<?= e(str_replace('admin-', '', $item['key'])) ?>">
          <span class="sidebar-link-icon" aria-hidden="true"><?= admin_icon_svg($item['icon']) ?></span>
          <span class="sidebar-link-copy"><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="sidebar-section sidebar-section-footer">
    <a href="<?= BASE_URL ?>index.php" class="sidebar-link" id="side-home">
      <span class="sidebar-link-icon" aria-hidden="true"><?= admin_icon_svg('link') ?></span>
      <span class="sidebar-link-copy">View Website</span>
    </a>
    <a href="<?= BASE_URL ?>logout.php" class="sidebar-link" id="side-logout">
      <span class="sidebar-link-icon" aria-hidden="true"><?= admin_icon_svg('logout') ?></span>
      <span class="sidebar-link-copy">Sign Out</span>
    </a>
  </div>
</aside>
<div class="admin-content">
