<?php
/**
 * Uthenga - Hostels Directory
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Explore Hostels';
$activeNav = 'hostels';

$accommodations = marketplace_fetch_properties('', 0, false);

$hostels = [];
foreach ($accommodations as $listing) {
    $meta = json_decode($listing['meta'] ?? '[]', true) ?: [];
    $category = strtolower((string)($meta['category'] ?? ''));
    $haystack = strtolower(($listing['title'] ?? '') . ' ' . ($listing['description'] ?? ''));
    if (strpos($haystack, 'hostel') !== false || strpos($category, 'hostel') !== false) {
        $hostels[] = $listing;
    }
}

if (empty($hostels)) {
    $hostels = $accommodations;
}

function hostelPrice(array $listing): string {
    $meta = json_decode($listing['meta'] ?? '[]', true) ?: [];
    return 'From ' . formatMWK($meta['rooms'][0]['pricePerNight'] ?? 0) . '/night';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Discover affordable and comfortable hostels across Malawi on Uthenga.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="hero" style="padding:3rem 0;">
  <div class="container">
    <div class="hero-content animate-in">
      <div class="hero-eyebrow">Affordable Stays</div>
      <h1>Hostels in Malawi</h1>
      <p>Find budget-friendly places to stay for solo travelers, students, and backpackers.</p>
      <div class="hero-btns">
        <a href="<?= BASE_URL ?>register.php" class="btn btn-primary btn-lg">Create Customer Account</a>
        <a href="<?= BASE_URL ?>login.php" class="btn btn-secondary btn-lg">Sign In</a>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <?php if (empty($hostels)): ?>
      <div class="glass-panel" style="padding:2rem;text-align:center;">
        <h3>No hostels found</h3>
        <p class="text-muted" style="margin-top:0.5rem;">Try checking back later or explore all accommodation listings.</p>
        <a href="<?= BASE_URL ?>hotels.php" class="btn btn-primary" style="margin-top:1rem;">Browse Hotels</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-4 gap-3">
        <?php foreach ($hostels as $listing): ?>
          <div class="card">
            <div class="card-img-wrap">
              <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
              <span class="card-badge badge-accommodation">Hostel</span>
            </div>
            <div class="card-body">
              <div class="card-title"><?= e($listing['title']) ?></div>
              <div class="card-loc">ðŸ“ <?= e($listing['location']) ?></div>
              <div class="card-price"><?= hostelPrice($listing) ?></div>
            </div>
            <div class="card-footer">
              <a href="<?= e($listing['detail_url']) ?>" class="btn btn-sm btn-secondary" style="width:100%;text-align:center;">View Details</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

