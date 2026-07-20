<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'FAQ';
$activeNav = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<section class="section">
  <div class="container" style="max-width:900px;">
    <h1>Frequently Asked Questions</h1>
    <div class="glass-panel" style="padding:1.5rem;margin-top:1.5rem;">
      <h3>How do bookings work?</h3>
      <p class="text-sm">Customers sign in before booking so orders, tickets, and payments can be tracked securely.</p>
      <h3 style="margin-top:1rem;">How do vendors join?</h3>
      <p class="text-sm">Vendors apply at <a href="<?= BASE_URL ?>vendor/register.php">/vendor/register</a> and wait for approval.</p>
      <h3 style="margin-top:1rem;">Need more help?</h3>
      <p class="text-sm">Use the <a href="<?= BASE_URL ?>support.php">Help Centre</a> or <a href="<?= BASE_URL ?>contact.php">Contact Us</a>.</p>
      <p class="text-sm" style="margin-top:0.75rem;">
        Email <a href="mailto:<?= e(SUPPORT_CONTACT['email']) ?>"><?= e(SUPPORT_CONTACT['email']) ?></a>
        or call <a href="tel:<?= e(SUPPORT_CONTACT['phone']) ?>"><?= e(SUPPORT_CONTACT['phone']) ?></a>.
      </p>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
