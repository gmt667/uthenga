<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Careers';
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
    <h1>Careers</h1>
    <div class="glass-panel" style="padding:1.5rem;margin-top:1.5rem;">
      <p class="text-sm">We are building a modern travel marketplace for Malawi. If you'd like to work with us, send a note through the contact page.</p>
      <p class="text-sm">Tell us about engineering, support, partnerships, or operations experience you can bring to Uthenga.</p>
      <a href="<?= BASE_URL ?>contact.php" class="btn btn-primary" style="margin-top:1rem;">Contact Us</a>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
