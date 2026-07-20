<?php
/**
 * Uthenga - Shared HTML Header & Navbar
 * Variables expected from including page:
 *   $pageTitle (string) - browser tab title
 *   $activeNav (string) - current nav key for highlight
 */
require_once __DIR__ . '/../config.php';

$pageTitle  = $pageTitle  ?? APP_NAME;
$activeNav  = $activeNav  ?? '';
$userName   = $_SESSION['user_name'] ?? '';
$userRole   = $_SESSION['user_role'] ?? '';
$isLoggedIn = isLoggedIn();
$themePreference = uthenga_theme_preference();

// Determine role CSS class
$roleCss = 'role-customer';
if (in_array($userRole, ADMIN_ROLES, true)) {
    $roleCss = 'role-admin';
}
if (in_array($userRole, VENDOR_ROLES, true)) {
    $roleCss = 'role-vendor';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($themePreference) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Uthenga - Malawi's premier marketplace for events, accommodation, tours & transport bookings.">
  <meta name="theme-color" content="<?= $themePreference === 'dark' ? '#0b1120' : '#f8fafc' ?>">
  <meta name="color-scheme" content="dark light">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <script>
    (function () {
      try {
        var stored = localStorage.getItem('uthenga-theme');
        var cookie = document.cookie.match(/(?:^|;\s*)uthenga-theme=(light|dark)(?:;|$)/);
        var serverTheme = document.documentElement.dataset.theme;
        var theme = (stored === 'light' || stored === 'dark')
          ? stored
          : ((cookie && (cookie[1] === 'light' || cookie[1] === 'dark')) ? cookie[1] : (serverTheme === 'dark' ? 'dark' : 'light'));
        document.documentElement.dataset.theme = theme;
        document.documentElement.style.colorScheme = theme;

        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
          meta.setAttribute('content', theme === 'light' ? '#f8fafc' : '#0b1120');
        }
      } catch (e) {}
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>

<?php require_once __DIR__ . '/page_loader.php'; ?>
<?php require_once __DIR__ . '/navbar.php'; ?>
<main>
