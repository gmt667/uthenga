<?php
/**
 * Uthenga - Shared dashboard chrome for customer and vendor portals.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_check.php';

if (!function_exists('dashboard_role_label')) {
    function dashboard_role_label(string $role): string {
        switch ($role) {
            case ROLE_CUSTOMER:
                return 'Customer Dashboard';
            case ROLE_VENDOR:
            case ROLE_EVENT_ORG:
            case ROLE_HOTEL_MGR:
            case ROLE_TOUR_OP:
            case ROLE_TRANSPORT:
                return 'Vendor Dashboard';
            case ROLE_SUPER_ADMIN:
                return 'Super Admin Dashboard';
            default:
                return 'Admin Dashboard';
        }
    }
}

if (!function_exists('dashboard_sidebar_items')) {
    function dashboard_sidebar_items(string $role): array {
        $isCustomer = $role === ROLE_CUSTOMER;
        $isVendor   = in_array($role, VENDOR_ROLES, true);

        if ($isCustomer) {
            return [
                ['label' => 'Overview',        'href' => 'dashboard.php',                'icon' => 'home'],
                ['label' => 'My Bookings',     'href' => 'dashboard.php?tab=bookings',   'icon' => 'calendar'],
                ['label' => 'My Tickets',      'href' => 'dashboard.php?tab=tickets',    'icon' => 'ticket'],
                ['label' => 'Favorites',       'href' => 'dashboard.php?tab=favorites',  'icon' => 'heart'],
                ['label' => 'Payments',        'href' => 'dashboard.php?tab=payments',   'icon' => 'wallet'],
                ['label' => 'Recently Viewed', 'href' => 'dashboard.php?tab=viewed',     'icon' => 'list'],
                ['label' => 'Loyalty',         'href' => 'dashboard.php?tab=loyalty',    'icon' => 'chart'],
                ['label' => 'Profile',         'href' => 'profile.php',                  'icon' => 'user'],
            ];
        }

        if ($isVendor) {
            return [
                ['label' => 'Overview', 'href' => 'vendor/dashboard.php', 'icon' => 'home'],
                ['label' => 'Listings', 'href' => 'vendor/dashboard.php?tab=listings', 'icon' => 'list'],
                ['label' => 'Bookings', 'href' => 'vendor/dashboard.php?tab=bookings', 'icon' => 'calendar'],
                ['label' => 'Revenue', 'href' => 'vendor/dashboard.php?tab=revenue', 'icon' => 'wallet'],
                ['label' => 'Analytics', 'href' => 'vendor/dashboard.php?tab=analytics', 'icon' => 'chart'],
                ['label' => 'Settings', 'href' => 'profile.php', 'icon' => 'settings'],
            ];
        }

        return [];
    }
}

if (!function_exists('dashboard_icon_svg')) {
    function dashboard_icon_svg(string $icon): string {
        $paths = [
            'home' => '<path d="M4 11.5 12 4l8 7.5V20a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z" fill="currentColor"/>',
            'calendar' => '<path d="M7 2v2H5a2 2 0 0 0-2 2v2h18V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2zM3 12v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-8z" fill="currentColor"/>',
            'ticket' => '<path d="M3 7a2 2 0 0 1 2-2h14v3a2 2 0 1 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 1 0 0-4z" fill="currentColor"/>',
            'wallet' => '<path d="M4 6a3 3 0 0 1 3-3h12v3H7a1 1 0 0 0 0 2h13v10H7a3 3 0 0 1-3-3zm14 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z" fill="currentColor"/>',
            'heart' => '<path d="M12 21s-7.5-4.5-9.5-9A5.7 5.7 0 0 1 12 5.5 5.7 5.7 0 0 1 21.5 12c-2 4.5-9.5 9-9.5 9z" fill="currentColor"/>',
            'settings' => '<path d="M19.4 13.5a7.8 7.8 0 0 0 .1-1.5 7.8 7.8 0 0 0-.1-1.5l2-1.5-2-3.5-2.4 1a7.7 7.7 0 0 0-2.6-1.5l-.4-2.6H9l-.4 2.6a7.7 7.7 0 0 0-2.6 1.5l-2.4-1-2 3.5 2 1.5a7.8 7.8 0 0 0-.1 1.5 7.8 7.8 0 0 0 .1 1.5l-2 1.5 2 3.5 2.4-1a7.7 7.7 0 0 0 2.6 1.5l.4 2.6h4.8l.4-2.6a7.7 7.7 0 0 0 2.6-1.5l2.4 1 2-3.5zM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7z" fill="currentColor"/>',
            'list' => '<path d="M5 6h14v2H5zm0 5h14v2H5zm0 5h14v2H5z" fill="currentColor"/>',
            'chart' => '<path d="M5 19V9h3v10H5zm5 0V5h3v14h-3zm5 0v-7h3v7h-3z" fill="currentColor"/>',
            'users' => '<path d="M9 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm8 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 21v-2a5 5 0 0 1 5-5h1a7 7 0 0 0 7 0h1a5 5 0 0 1 5 5v2z" fill="currentColor"/>',
            'store' => '<path d="M3 7h18l-1 4H4L3 7zm2 6h14v7H5z" fill="currentColor"/>',
            'shield' => '<path d="M12 2 4 5v6c0 5 3.5 8.8 8 11 4.5-2.2 8-6 8-11V5z" fill="currentColor"/>',
            'user' => '<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-7 2-7 5v1h14v-1c0-3-3-5-7-5z" fill="currentColor"/>',
        ];

        return '<svg class="dash-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$icon] ?? $paths['home']) . '</svg>';
    }
}

if (!function_exists('renderDashboardChromeStart')) {
    function renderDashboardChromeStart(array $options = []): void {
        $role = $options['role'] ?? currentRole();
        $title = $options['title'] ?? dashboard_role_label($role);
        $active = $options['active'] ?? '';
        $searchEnabled = $options['search'] ?? true;
        $userName = trim($_SESSION['user_name'] ?? 'Account');
        $firstName = explode(' ', $userName)[0] ?? 'Account';
        $searchPlaceholder = $options['searchPlaceholder'] ?? 'Search dashboard...';
        $status = $options['status'] ?? 'Active';
        $items = dashboard_sidebar_items($role);
        $themePreference = uthenga_theme_preference();
        ?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($themePreference) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <meta name="theme-color" content="<?= $themePreference === 'dark' ? '#0b1120' : '#f8fafc' ?>">
  <title><?= e($title) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
</head>
<body class="dashboard-page" data-dashboard-role="<?= e($role) ?>">
  <div class="dashboard-shell">
    <header class="dashboard-topbar">
      <?php $logoSize = 'sm'; $logoLink = true; require __DIR__ . '/logo.php'; ?>

      <?php if ($searchEnabled): ?>
        <form class="dashboard-search" action="<?= BASE_URL ?>index.php" method="get" role="search">
          <input type="search" name="q" placeholder="<?= e($searchPlaceholder) ?>" aria-label="Search dashboard">
        </form>
      <?php endif; ?>

      <div class="dashboard-topbar-actions">
        <button type="button" class="dashboard-sidebar-toggle" data-dashboard-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="true">
          <span></span><span></span><span></span>
        </button>
        <button type="button" class="btn btn-sm btn-secondary btn-icon theme-toggle" data-theme-toggle aria-label="Toggle light and dark mode" aria-pressed="false">
          <span class="theme-toggle-icon" aria-hidden="true"></span>
          <span class="theme-toggle-label">Dark</span>
        </button>
        <div class="profile-dropdown dashboard-profile">
          <button class="profile-dropdown-btn" id="profile-dropdown-trigger" aria-haspopup="true" aria-expanded="false" type="button">
            <span class="nav-avatar-fallback"><?= e(strtoupper(substr($firstName, 0, 1))) ?></span>
            <span><?= e($firstName) ?></span>
            <span class="arrow" aria-hidden="true">&#9662;</span>
          </button>
          <div class="profile-dropdown-content" role="menu" aria-label="Account menu">
            <a href="<?= BASE_URL ?>profile.php" role="menuitem">Profile</a>
            <a href="<?= BASE_URL ?>profile.php#settings" role="menuitem">Settings</a>
            <hr>
            <a href="<?= BASE_URL ?>logout.php" class="logout-link" role="menuitem">Logout</a>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-layout">
      <aside class="sidebar admin-sidebar" aria-label="Dashboard navigation">
        <?php $heroIcon = ($role === ROLE_CUSTOMER) ? 'user' : 'store'; ?>
        <div class="sidebar-hero">
          <div class="sidebar-hero-mark"><?= dashboard_icon_svg($heroIcon) ?></div>
          <div>
            <div class="sidebar-role-title"><?= e($title) ?></div>
            <div class="sidebar-role-meta"><?= e($status) ?></div>
          </div>
        </div>
        <nav class="dashboard-sidebar-nav">
          <?php foreach ($items as $item): ?>
            <?php $isActive = ($active === $item['href']); ?>
            <a class="sidebar-link <?= $isActive ? 'active' : '' ?>" href="<?= BASE_URL . $item['href'] ?>">
              <span class="sidebar-link-icon" aria-hidden="true"><?= dashboard_icon_svg($item['icon']) ?></span>
              <span class="sidebar-link-copy"><?= e($item['label']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </aside>
      <div class="admin-content">
        <?php
    }
}

if (!function_exists('renderDashboardChromeEnd')) {
    function renderDashboardChromeEnd(): void {
        ?>
        </div>
      </div>
    <footer class="dashboard-footer">
      <div><?= APP_NAME ?> &copy; <?= date('Y') ?></div>
      <div>Version <?= e(APP_VERSION) ?> | <a href="<?= BASE_URL ?>support.php">Support</a></div>
    </footer>
  </div>
  <script src="<?= BASE_URL ?>assets/js/main.js"></script>
</body>
</html>
        <?php
    }
}
