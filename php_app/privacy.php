<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Privacy Policy';
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
    <h1>Privacy Policy</h1>
    <div class="glass-panel" style="padding:1.5rem;margin-top:1.5rem;">
      <p class="text-sm">We only collect information required to manage accounts, bookings, vendor approvals, and support requests.</p>
      <p class="text-sm">Customer details are used to process orders and provide service updates.</p>
      <p class="text-sm">Vendor documentation is used for approval and compliance review.</p>
      <p class="text-sm">If you have questions about data handling, contact <?= e(SUPPORT_EMAIL) ?>.</p>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
