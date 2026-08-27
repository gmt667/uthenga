<?php
/**
 * Uthenga - Accommodation Directory
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Explore Accommodation';
$activeNav = 'stays';

// Search & filter parameters
$search = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$rating = trim($_GET['rating'] ?? '');

$listings = marketplace_fetch_properties($search, 0, false);
if ($location !== '' || $rating !== '') {
    $listings = array_values(array_filter($listings, function ($item) use ($location, $rating) {
        if ($location !== '' && stripos((string) ($item['location'] ?? ''), $location) === false) {
            return false;
        }
        if ($rating !== '' && (float) ($item['rating'] ?? 0) < (float) $rating) {
            return false;
        }
        return true;
    }));
}

$allLocations = dbQuery("
    SELECT DISTINCT location
    FROM listings
    WHERE listing_type = 'accommodation' AND is_active = 1 AND location <> ''
    ORDER BY location ASC
");

if (empty($allLocations)) {
    $allLocations = [
        ['location' => 'Lilongwe'],
        ['location' => 'Blantyre'],
        ['location' => 'Mangochi'],
    ];
}

if (empty($listings) && $search === '' && $location === '' && $rating === '') {
    $listings = array_map('marketplace_normalize_item', [
        [
            'id' => 'stay-mock-1',
            'listing_type' => 'accommodation',
            'type' => 'property',
            'title' => 'Sunbird Capital Hotel',
            'location' => 'Lilongwe',
            'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=900&fit=crop&q=80',
            'rating' => 4.8,
            'featured' => 1,
            'meta' => json_encode(['rooms' => [['pricePerNight' => 125000]]]),
        ],
        [
            'id' => 'stay-mock-2',
            'listing_type' => 'accommodation',
            'type' => 'property',
            'title' => 'Mango Lodge',
            'location' => 'Mangochi',
            'image' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=900&fit=crop&q=80',
            'rating' => 4.6,
            'featured' => 0,
            'meta' => json_encode(['rooms' => [['pricePerNight' => 85000]]]),
        ],
        [
            'id' => 'stay-mock-3',
            'listing_type' => 'accommodation',
            'type' => 'property',
            'title' => 'Blantyre Boutique Apartments',
            'location' => 'Blantyre',
            'image' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=900&fit=crop&q=80',
            'rating' => 4.7,
            'featured' => 1,
            'meta' => json_encode(['rooms' => [['pricePerNight' => 99000]]]),
        ],
    ]);
}

function renderStars(float $rating): string {
    $full = (int)floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    return str_repeat('★', $full) . str_repeat('½', $half) . str_repeat('☆', 5 - $full - $half);
}

function getPrice(array $listing): string {
    $meta = json_decode($listing['meta'], true);
    return 'From ' . formatMWK($meta['rooms'][0]['pricePerNight'] ?? 0) . '/night';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Discover hotels, resorts, lakeshore lodges and guesthouses in Malawi. Book your stay on Uthenga.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .directory-hero {
      background: linear-gradient(135deg, #064e3b 0%, #022c22 100%);
      padding: 3rem 0;
      border-bottom: 1px solid var(--clr-border);
      margin-bottom: 2rem;
    }
    .filters-wrapper {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      margin-bottom: 2rem;
    }
    .filter-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr auto;
      gap: 1rem;
      align-items: flex-end;
    }
    @media (max-width: 768px) {
      .filter-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="directory-hero">
  <div class="container">
    <h1 style="font-size: 2.2rem; margin-bottom: 0.5rem; display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;"><?= uthenga_public_icon_svg('hotel') ?> Accommodation & Stays</h1>
    <p style="color: var(--clr-text-soft);">Find the best hotels, lakeshore cottages, lodges, and luxury resorts in Malawi.</p>
  </div>
</section>

<div class="container" style="padding-bottom: 4rem;">
  
  <!-- Advanced Filters -->
  <div class="filters-wrapper">
    <form method="GET" action="hotels.php" class="filter-form">
      <div class="filter-grid">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Search Keyword</label>
          <input type="text" name="q" class="form-control" placeholder="Search by name, amenities..." value="<?= e($search) ?>">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Location</label>
          <select name="location" class="form-control">
            <option value="">All Locations</option>
            <?php foreach ($allLocations as $loc): ?>
              <option value="<?= e($loc['location']) ?>" <?= $location === $loc['location'] ? 'selected' : '' ?>><?= e($loc['location']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Min Rating</label>
          <select name="rating" class="form-control">
            <option value="">Any Rating</option>
            <option value="4" <?= $rating === '4' ? 'selected' : '' ?>>4.0+ Stars</option>
            <option value="4.5" <?= $rating === '4.5' ? 'selected' : '' ?>>4.5+ Stars</option>
          </select>
        </div>
        <div>
          <button type="submit" class="btn btn-primary" style="width: 100%;">Filter</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Listings -->
  <?php if (empty($listings)): ?>
    <div style="text-align: center; padding: 4rem 0;">
      <div style="font-size: 3rem; margin-bottom: 1rem;"><?= uthenga_public_icon_svg('search') ?></div>
      <h3>No hotels or lodges found</h3>
      <p class="text-muted">Try adjusting your search criteria or clear the filters.</p>
      <a href="hotels.php" class="btn btn-secondary" style="margin-top: 1rem;">Reset Filters</a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-4 gap-3">
      <?php foreach ($listings as $listing): 
        $meta = json_decode($listing['meta'], true);
      ?>
      <div class="listing-card-wrap">
        <div class="card" id="listing-<?= e($listing['id']) ?>">
          <div class="card-img-wrap">
            <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
            <span class="card-badge badge-accommodation">Stay</span>
            <?php if ($listing['featured']): ?><span class="card-badge badge-featured" style="left:auto;right:0.75rem;"><?= uthenga_public_icon_svg('star') ?> Featured</span><?php endif; ?>
          </div>
          <div class="card-body">
            <div class="card-title"><?= e($listing['title']) ?></div>
            <div class="card-loc"><?= uthenga_public_icon_svg('pin') ?> <?= e($listing['location']) ?></div>
            <div class="card-price"><?= getPrice($listing) ?></div>
          </div>
          <div class="card-footer">
            <a href="<?= e($listing['detail_url']) ?>" class="btn btn-sm btn-secondary" style="flex:1;">Details</a>
            <?php if (isLoggedIn()): ?>
              <button
                class="btn btn-sm btn-primary"
                onclick="AccommodationCheckout.open('<?= e($listing['id']) ?>','<?= addslashes(e($listing['title'])) ?>')"
              >Book Now</button>
            <?php else: ?>
              <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-primary">Book Now</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (isLoggedIn()): ?>
<?php require_once __DIR__ . '/includes/accommodation_checkout_modal.php'; ?>
<script src="<?= BASE_URL ?>assets/js/accommodation-checkout.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>

