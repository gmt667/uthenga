<?php
/**
 * Uthenga - Public Navigation Bar
 */
require_once __DIR__ . '/../config.php';

$userName    = $_SESSION['user_name'] ?? '';
$userRole    = $_SESSION['user_role'] ?? '';
$isLoggedIn  = isLoggedIn();
$activeNav   = $activeNav ?? '';
$isCustomer  = $isLoggedIn && $userRole === ROLE_CUSTOMER;
$isVendor    = $isLoggedIn && in_array($userRole, VENDOR_ROLES, true);
$displayName = trim(explode(' ', $userName)[0] ?? '');
$displayName = $displayName !== '' ? $displayName : 'Account';
$themePreference = uthenga_theme_preference();
?>
<nav class="navbar" role="navigation" aria-label="Main navigation" id="main-navbar">
  <div class="navbar-inner">
    <?php $logoSize = 'md'; $logoLink = true; require __DIR__ . '/logo.php'; ?>

    <div class="navbar-mobile-actions" aria-label="Mobile actions">
      <button
        type="button"
        class="btn btn-sm btn-secondary btn-icon theme-toggle"
        data-theme-toggle
        aria-label="Toggle light and dark mode"
        aria-pressed="false"
        title="Toggle light and dark mode"
      >
        <span class="theme-toggle-icon" aria-hidden="true"></span>
        <span class="theme-toggle-label">Dark</span>
      </button>
      <?php if ($isLoggedIn): ?>
        <a href="<?= BASE_URL . ($userRole === ROLE_CUSTOMER ? 'dashboard.php' : (in_array($userRole, ADMIN_ROLES, true) ? ($userRole === ROLE_SUPER_ADMIN ? 'admin/super-dashboard.php' : 'admin/dashboard.php') : 'vendor/dashboard.php')) ?>" class="btn btn-sm btn-primary">Dashboard</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-secondary">Sign In</a>
        <a href="<?= BASE_URL ?>register.php" class="btn btn-sm btn-primary">Register</a>
      <?php endif; ?>
    </div>

    <button
      type="button"
      class="navbar-hamburger"
      id="navbar-hamburger"
      aria-label="Toggle navigation menu"
      aria-expanded="false"
      aria-controls="navbar-mobile-menu"
    >
      <span class="hamburger-bar"></span>
      <span class="hamburger-bar"></span>
      <span class="hamburger-bar"></span>
    </button>

    <ul class="navbar-links" role="list" id="navbar-mobile-menu">
      <li><a href="<?= BASE_URL ?>index.php" id="nav-home" class="<?= $activeNav === 'home' ? 'active' : '' ?>">Home</a></li>
      <li><a href="<?= BASE_URL ?>events.php" id="nav-events" class="<?= $activeNav === 'events' ? 'active' : '' ?>">Events</a></li>
      <li><a href="<?= BASE_URL ?>hotels.php" id="nav-stays" class="<?= $activeNav === 'stays' ? 'active' : '' ?>">Stays</a></li>
      <li><a href="<?= BASE_URL ?>transport.php" id="nav-transport" class="<?= $activeNav === 'transport' ? 'active' : '' ?>">Transport</a></li>
      <li><a href="<?= BASE_URL ?>mbanda/index.php" id="nav-mbanda" class="<?= $activeNav === 'mbanda' ? 'active' : '' ?>">Mbanda</a></li>
      <li><a href="<?= BASE_URL ?>marketplace.php" id="nav-marketplace" class="<?= $activeNav === 'marketplace' ? 'active' : '' ?>">Marketplace</a></li>
      <li><a href="<?= BASE_URL ?>shop.php" id="nav-shop" class="<?= $activeNav === 'shop' ? 'active' : '' ?>"><?= uthenga_public_icon_svg('shop') ?> Shop</a></li>
      <li><a href="<?= BASE_URL ?>trip-planner.php" id="nav-planner" class="<?= $activeNav === 'trip-planner' ? 'active' : '' ?>" style="color:var(--clr-cyan);">Trip Planner</a></li>
      <li><a href="<?= BASE_URL ?>tours.php" id="nav-explore" class="<?= $activeNav === 'explore' ? 'active' : '' ?>">Explore</a></li>
    </ul>

    <div class="navbar-actions" id="navbar-actions">
      <button
        type="button"
        class="btn btn-sm btn-secondary btn-icon theme-toggle"
        data-theme-toggle
        aria-label="Toggle light and dark mode"
        aria-pressed="false"
        title="Toggle light and dark mode"
      >
        <span class="theme-toggle-icon" aria-hidden="true"></span>
        <span class="theme-toggle-label">Dark</span>
      </button>

      <?php if ($isLoggedIn): ?>
        <div class="profile-dropdown">
          <button class="profile-dropdown-btn" id="profile-dropdown-trigger" aria-haspopup="true" aria-expanded="false" type="button">
            <span class="nav-avatar-fallback"><?= e(strtoupper(substr($displayName, 0, 1))) ?></span>
            <span><?= e($displayName) ?></span>
            <span class="arrow" aria-hidden="true"><?= uthenga_public_icon_svg('chevron-down') ?></span>
          </button>
          <div class="profile-dropdown-content" role="menu" aria-label="Account menu">
            <?php if ($isCustomer): ?>
              <a href="<?= BASE_URL ?>dashboard.php" role="menuitem">Dashboard</a>
              <a href="<?= BASE_URL ?>shop-orders.php" role="menuitem">My Orders</a>
              <a href="<?= BASE_URL ?>shop.php" role="menuitem">Shop</a>
              <a href="<?= BASE_URL ?>bookings.php" role="menuitem">My Bookings</a>
              <a href="<?= BASE_URL ?>mbanda/my_bookings.php" role="menuitem">My Rides</a>
              <a href="<?= BASE_URL ?>support.php" role="menuitem">My Tickets</a>
              <a href="<?= BASE_URL ?>profile.php" role="menuitem">Profile</a>
            <?php elseif ($isVendor): ?>
              <a href="<?= BASE_URL ?>vendor/dashboard.php" role="menuitem">Vendor Dashboard</a>
              <a href="<?= BASE_URL ?>vendor/business-listing.php" role="menuitem">My Business Listing</a>
              <a href="<?= BASE_URL ?>profile.php" role="menuitem">Profile</a>
            <?php elseif (in_array($userRole, ADMIN_ROLES, true)): ?>
              <a href="<?= BASE_URL . ($userRole === ROLE_SUPER_ADMIN ? 'admin/super-dashboard.php' : 'admin/dashboard.php') ?>" role="menuitem">Admin Dashboard</a>
              <a href="<?= BASE_URL ?>profile.php" role="menuitem">Profile</a>
            <?php endif; ?>
            <hr>
            <a href="<?= BASE_URL ?>logout.php" class="logout-link" role="menuitem">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= BASE_URL ?>vendor/register.php" class="btn btn-sm btn-ghost" id="nav-become-vendor">Vendor</a>
        <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-secondary" id="nav-login">Sign In</a>
        <a href="<?= BASE_URL ?>register.php" class="btn btn-sm btn-primary" id="nav-register">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<script>
(function(){
  var hamburger = document.getElementById('navbar-hamburger');
  var navbar    = document.getElementById('main-navbar');
  if (!hamburger || !navbar) return;

  // Remove any legacy duplicate hamburger that may come from an older cached copy.
  var legacyHamburger = document.querySelector('.nav-hamburger');
  if (legacyHamburger) legacyHamburger.remove();

  // Keep the navbar closed by default so mobile never boots into an expanded state.
  navbar.classList.remove('navbar-mobile-open');
  hamburger.setAttribute('aria-expanded', 'false');

  function openMenu() {
    navbar.classList.add('navbar-mobile-open');
    document.body.classList.add('navbar-menu-open');
    hamburger.setAttribute('aria-expanded', 'true');
  }
  function closeMenu() {
    navbar.classList.remove('navbar-mobile-open');
    document.body.classList.remove('navbar-menu-open');
    hamburger.setAttribute('aria-expanded', 'false');
  }

  hamburger.addEventListener('click', function() {
    if (navbar.classList.contains('navbar-mobile-open')) closeMenu();
    else openMenu();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMenu();
  });

  document.addEventListener('click', function(e) {
    if (!navbar.contains(e.target)) {
      closeMenu();
    }
  });

  document.querySelectorAll('#navbar-mobile-menu a').forEach(function(link) {
    link.addEventListener('click', function() {
      closeMenu();
    });
  });
})();
</script>
