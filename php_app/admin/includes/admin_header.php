<?php
/**
 * Uthenga - Admin Dashboard Header
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/admin_icons.php';

requireAdmin();

$pageTitle = $pageTitle ?? 'Admin Control Panel';
$activeNav = $activeNav ?? 'admin';
$adminName = $_SESSION['user_name'] ?? 'Admin';
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
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?> Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
</head>
<body class="dashboard-page" data-dashboard-role="admin">
  <?php require_once __DIR__ . '/../../includes/page_loader.php'; ?>
  <div class="dashboard-shell">
    <header class="dashboard-topbar">
      <a href="<?= BASE_URL . (($_SESSION['user_role'] ?? '') === ROLE_SUPER_ADMIN ? 'admin/super-dashboard.php' : 'admin/dashboard.php') ?>" class="dashboard-brand">
        <span class="dashboard-brand-logo" aria-hidden="true"><?php $logoSize = 'sm'; $logoLink = false; require __DIR__ . '/../../includes/logo.php'; ?></span>
        <span class="dashboard-brand-copy">
          <strong><?= APP_NAME ?></strong>
          <span>Admin Console</span>
        </span>
      </a>

      <button type="button" class="dashboard-sidebar-toggle" data-dashboard-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="false">
        <?= admin_icon_svg('menu') ?>
      </button>

      <form class="dashboard-search" action="<?= BASE_URL ?>index.php" method="get" role="search">
        <?= admin_icon_svg('search') ?>
        <input type="search" name="q" placeholder="Search admin records..." aria-label="Search admin">
      </form>

      <div class="dashboard-topbar-actions">
        <button type="button" class="btn btn-sm btn-secondary btn-icon theme-toggle" data-theme-toggle aria-label="Toggle light and dark mode" aria-pressed="false">
          <span class="theme-toggle-icon" aria-hidden="true"></span>
          <span class="theme-toggle-label">Dark</span>
        </button>
        <a class="dashboard-icon-link" href="<?= BASE_URL ?>admin/notifications.php" title="Notifications" aria-label="Notifications">
          <?= admin_icon_svg('bell') ?>
        </a>
          <div class="profile-dropdown dashboard-profile">
            <button class="profile-dropdown-btn" id="profile-dropdown-trigger" aria-haspopup="true" aria-expanded="false" type="button">
              <span class="nav-avatar-fallback"><?= e(strtoupper(substr($adminName, 0, 1))) ?></span>
              <span><?= e($adminName) ?></span>
              <span class="arrow" aria-hidden="true">&#9662;</span>
            </button>
            <div class="profile-dropdown-content" role="menu" aria-label="Account menu">
            <a href="<?= BASE_URL ?>admin/profile.php" role="menuitem">Profile</a>
            <a href="<?= BASE_URL ?>admin/settings.php" role="menuitem">Settings</a>
            <hr>
            <a href="<?= BASE_URL ?>logout.php" class="logout-link" role="menuitem">Logout</a>
          </div>
        </div>
      </div>
    </header>

    <div class="admin-layout">
      <?php require __DIR__ . '/admin_sidebar.php'; ?>
