<?php
/**
 * Uthenga - Services Overview
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Services';
$activeNav = 'services';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Explore travel services, booking support, vendor tools, and platform help on Uthenga.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="hero" style="padding:3.5rem 0;">
  <div class="container">
    <div class="hero-content animate-in">
      <div class="hero-eyebrow">Platform Services</div>
      <h1>Services that power the marketplace</h1>
      <p>Everything customers and vendors need, from secure bookings to approvals and support.</p>
      <div class="hero-btns">
        <a href="<?= BASE_URL ?>register.php" class="btn btn-primary btn-lg">Customer Registration</a>
        <a href="<?= BASE_URL ?>vendor/register.php" class="btn btn-secondary btn-lg">Vendor Registration</a>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-cols-4 gap-3">
      <div class="glass-panel" style="padding:1.5rem;">
        <h3>Secure Booking</h3>
        <p class="text-sm" style="margin-top:0.5rem;">Guests can browse freely, but booking and saving require sign-in.</p>
      </div>
      <div class="glass-panel" style="padding:1.5rem;">
        <h3>Vendor Onboarding</h3>
        <p class="text-sm" style="margin-top:0.5rem;">Businesses apply at <code>/vendor/register</code> and wait for approval.</p>
      </div>
      <div class="glass-panel" style="padding:1.5rem;">
        <h3>Customer Dashboard</h3>
        <p class="text-sm" style="margin-top:0.5rem;">Bookings, tickets, payments, and notifications live in one place.</p>
      </div>
      <div class="glass-panel" style="padding:1.5rem;">
        <h3>Support</h3>
        <p class="text-sm" style="margin-top:0.5rem;">Need help? Use the contact page or customer support center.</p>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
