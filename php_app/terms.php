<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Terms & Conditions';
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
    <h1>Terms & Conditions</h1>
    <div class="glass-panel" style="padding:1.5rem;margin-top:1.5rem;">
      <p class="text-sm">Users must provide accurate account details and follow platform rules.</p>
      <p class="text-sm">Bookings and vendor approvals may be reviewed for security and compliance.</p>
      <p class="text-sm">We may suspend accounts that violate platform rules or abuse the service.</p>
      <p class="text-sm">By using Uthenga, you agree to the platform policies and booking requirements.</p>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
