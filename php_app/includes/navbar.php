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
      <li><a href="<?= BASE_URL ?>index.php" id="nav-home" class="<?= $activeNav === 'home' ? 'active' : '' ?>" <?= $activeNav === 'home' ? 'aria-current="page"' : '' ?>>Home</a></li>
      <li><a href="<?= BASE_URL ?>events.php" id="nav-events" class="<?= $activeNav === 'events' ? 'active' : '' ?>" <?= $activeNav === 'events' ? 'aria-current="page"' : '' ?>>Events</a></li>
      <li><a href="<?= BASE_URL ?>hotels.php" id="nav-stays" class="<?= $activeNav === 'stays' ? 'active' : '' ?>" <?= $activeNav === 'stays' ? 'aria-current="page"' : '' ?>>Stays</a></li>
      <li><a href="<?= BASE_URL ?>transport.php" id="nav-transport" class="<?= $activeNav === 'transport' ? 'active' : '' ?>" <?= $activeNav === 'transport' ? 'aria-current="page"' : '' ?>>Transport</a></li>
      <li><a href="<?= BASE_URL ?>tourism.php" id="nav-tourism" class="<?= $activeNav === 'tourism' ? 'active' : '' ?>" <?= $activeNav === 'tourism' ? 'aria-current="page"' : '' ?>>Tourism</a></li>
      <li><a href="<?= BASE_URL ?>ai.php#/driver" id="nav-quick-travel" class="nav-feature-link <?= $activeNav === 'quick-travel' ? 'active' : '' ?>" <?= $activeNav === 'quick-travel' ? 'aria-current="page"' : '' ?>>Quick Taxi</a></li>
      <li><a href="<?= BASE_URL ?>shop.php" id="nav-shop" class="<?= $activeNav === 'shop' ? 'active' : '' ?>" <?= $activeNav === 'shop' ? 'aria-current="page"' : '' ?>><?= uthenga_public_icon_svg('shop') ?> Shop</a></li>
      <li><a href="<?= BASE_URL ?>ai.php#/planner" id="nav-planner" class="nav-feature-link <?= $activeNav === 'trip-planner' ? 'active' : '' ?>" <?= $activeNav === 'trip-planner' ? 'aria-current="page"' : '' ?>>Trip Planner</a></li>
      <li class="navbar-menu-actions" aria-label="Account actions">
        <?php if ($isLoggedIn): ?>
          <a href="<?= BASE_URL ?>dashboard.php"><?= uthenga_public_icon_svg('user') ?> My account</a>
          <?php if (in_array($userRole, ADMIN_ROLES, true)): ?><form method="post" action="<?= BASE_URL ?>logout.php"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>"><button type="submit" class="logout-link">Sign out</button></form><?php else: ?><a href="<?= BASE_URL ?>logout.php" class="logout-link">Sign out</a><?php endif; ?>
        <?php else: ?>
          <a href="<?= BASE_URL ?>login.php"><?= uthenga_public_icon_svg('user') ?> Sign in</a>
          <a href="<?= BASE_URL ?>register.php" class="navbar-menu-primary">Create account</a>
          <a href="<?= BASE_URL ?>vendor/register.php">Become a vendor</a>
        <?php endif; ?>
      </li>
    </ul>

    <div class="navbar-actions" id="navbar-actions">
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
              <a href="<?= BASE_URL ?>payment-history.php" role="menuitem">Payment History</a>
              <a href="<?= BASE_URL ?>support.php" role="menuitem">My Tickets</a>
              <a href="<?= BASE_URL ?>profile.php" role="menuitem">Profile</a>
            <?php elseif ($isVendor): ?>
              <a href="<?= BASE_URL ?>vendor/dashboard.php" role="menuitem">Vendor Dashboard</a>
              <a href="<?= BASE_URL ?>ai.php#/driver" role="menuitem">Quick Travel Operations</a>
              <a href="<?= BASE_URL ?>vendor/business-listing.php" role="menuitem">My Business Listing</a>
              <a href="<?= BASE_URL ?>vendor/payment-settings.php" role="menuitem">Payment Settlement</a>
              <a href="<?= BASE_URL ?>profile.php" role="menuitem">Profile</a>
            <?php elseif (in_array($userRole, ADMIN_ROLES, true)): ?>
              <a href="<?= BASE_URL ?>admin/dashboard.php" role="menuitem">Admin Overview</a>
              <a href="<?= BASE_URL ?>profile.php" role="menuitem">Profile</a>
            <?php endif; ?>
            <hr>
            <?php if (in_array($userRole, ADMIN_ROLES, true)): ?><form method="post" action="<?= BASE_URL ?>logout.php" role="none"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>"><button type="submit" class="logout-link" role="menuitem">Logout</button></form><?php else: ?><a href="<?= BASE_URL ?>logout.php" class="logout-link" role="menuitem">Logout</a><?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <button
          type="button"
          class="btn btn-sm btn-secondary btn-icon theme-toggle"
          data-theme-toggle
          aria-label="Toggle light and dark mode"
          aria-pressed="false"
          title="Toggle light and dark mode"
        >
          <span class="theme-toggle-icon" aria-hidden="true"><?= uthenga_public_icon_svg($themePreference === 'dark' ? 'moon' : 'sun') ?></span>
          <span class="theme-toggle-label">Dark</span>
        </button>
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
  var scrollY = 0;
  var hero = null;
  var focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  function updateNavbarState() {
    if (!hero) hero = document.querySelector('.lp-hero, .hero-slider-container, .directory-hero, .transport-hero, .shop-hero, .mp-hero, .hero');
    var heroBottom = hero ? hero.getBoundingClientRect().bottom : 0;
    var overlapsHero = !!hero && heroBottom > navbar.offsetHeight + 8 && window.scrollY < Math.max(hero.offsetHeight - navbar.offsetHeight, 24);
    navbar.classList.toggle('navbar-over-hero', overlapsHero);
    navbar.classList.toggle('navbar-over-light', overlapsHero && hero && hero.getAttribute('data-navbar-theme') === 'light');
    // Apply the glass treatment as soon as the page moves, including while
    // the navigation is still over a hero image, so links remain readable.
    navbar.classList.toggle('navbar-scrolled', window.scrollY > 2);
    document.body.classList.toggle('navbar-has-hero', !!hero);
    document.body.classList.toggle('navbar-no-hero', !hero);
  }

  function openMenu() {
    scrollY = window.scrollY;
    navbar.classList.add('navbar-mobile-open');
    document.body.classList.add('navbar-menu-open');
    document.documentElement.setAttribute('data-scroll-lock', 'true');
    document.body.style.top = '-' + scrollY + 'px';
    hamburger.setAttribute('aria-expanded', 'true');
    var first = navbar.querySelector('#navbar-mobile-menu a');
    if (first) window.requestAnimationFrame(function(){ first.focus(); });
  }
  function closeMenu() {
    var wasOpen = navbar.classList.contains('navbar-mobile-open');
    navbar.classList.remove('navbar-mobile-open');
    document.body.classList.remove('navbar-menu-open');
    document.documentElement.removeAttribute('data-scroll-lock');
    document.body.style.top = '';
    if (wasOpen) window.scrollTo(0, scrollY);
    hamburger.setAttribute('aria-expanded', 'false');
  }

  hamburger.addEventListener('click', function() {
    if (navbar.classList.contains('navbar-mobile-open')) closeMenu();
    else openMenu();
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeMenu(); hamburger.focus(); }
    if (e.key === 'Tab' && navbar.classList.contains('navbar-mobile-open')) {
      var focusable = Array.prototype.slice.call(navbar.querySelectorAll(focusableSelector)).filter(function(node) { return node.offsetParent !== null; });
      if (!focusable.length) return;
      var first = focusable[0], last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
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
  updateNavbarState();
  document.addEventListener('DOMContentLoaded', updateNavbarState, { once: true });
  window.addEventListener('scroll', updateNavbarState, { passive: true });
  window.addEventListener('resize', function(){ updateNavbarState(); if (window.innerWidth > 768) closeMenu(); }, { passive: true });
})();
</script>
