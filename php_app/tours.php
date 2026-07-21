<?php
/**
 * Uthenga — Tours & Activities Directory
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Explore Tours';
$activeNav = 'explore';

// Search & filter parameters
$search = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$rating = trim($_GET['rating'] ?? '');

$listings = marketplace_fetch_tours($search, 0, false);
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
    WHERE listing_type = 'tour' AND is_active = 1 AND location <> ''
    ORDER BY location ASC
");

if (empty($allLocations)) {
    $allLocations = [
        ['location' => 'Zomba Plateau'],
        ['location' => 'Cape Maclear'],
        ['location' => 'Nyika National Park'],
    ];
}

if (empty($listings) && $search === '' && $location === '' && $rating === '') {
    $listings = array_map('marketplace_normalize_item', [
        [
            'id' => 'tour-mock-1',
            'listing_type' => 'tour',
            'type' => 'tour',
            'title' => 'Zomba Plateau Sunrise Hike',
            'location' => 'Zomba Plateau',
            'image' => 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=900&fit=crop&q=80',
            'rating' => 4.9,
            'featured' => 1,
            'meta' => json_encode(['pricePerPerson' => 28000]),
        ],
        [
            'id' => 'tour-mock-2',
            'listing_type' => 'tour',
            'type' => 'tour',
            'title' => 'Lake Malawi Boat Safari',
            'location' => 'Cape Maclear',
            'image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=900&fit=crop&q=80',
            'rating' => 4.8,
            'featured' => 1,
            'meta' => json_encode(['pricePerPerson' => 45000]),
        ],
        [
            'id' => 'tour-mock-3',
            'listing_type' => 'tour',
            'type' => 'tour',
            'title' => 'Nyika Wildlife Day Trip',
            'location' => 'Nyika National Park',
            'image' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=900&fit=crop&q=80',
            'rating' => 4.7,
            'featured' => 0,
            'meta' => json_encode(['pricePerPerson' => 52000]),
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
    return formatMWK($meta['pricePerPerson'] ?? 0) . '/person';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Book wildlife safaris, hiking, cultural excursions and boat cruises in Malawi. Plan your adventure on Uthenga.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .directory-hero {
      background: linear-gradient(135deg, #78350f 0%, #451a03 100%);
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
    <h1 style="font-size: 2.2rem; margin-bottom: 0.5rem;">🌿 Tours & Adventures</h1>
    <p style="color: var(--clr-text-soft);">Discover safaris, hiking trails, national parks, and lakeshore experiences in Malawi.</p>
  </div>
</section>

<div class="container" style="padding-bottom: 4rem;">
  
  <!-- Advanced Filters -->
  <div class="filters-wrapper">
    <form method="GET" action="tours.php" class="filter-form">
      <div class="filter-grid">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Search Keyword</label>
          <input type="text" name="q" class="form-control" placeholder="Search by safari, park, guide..." value="<?= e($search) ?>">
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
      <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
      <h3>No tours found</h3>
      <p class="text-muted">Try adjusting your search criteria or clear the filters.</p>
      <a href="tours.php" class="btn btn-secondary" style="margin-top: 1rem;">Reset Filters</a>
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
            <span class="card-badge badge-tour">Tour</span>
            <?php if ($listing['featured']): ?><span class="card-badge badge-featured" style="left:auto;right:0.75rem;">⭐ Featured</span><?php endif; ?>
          </div>
          <div class="card-body">
            <div class="card-title"><?= e($listing['title']) ?></div>
            <div class="card-loc">📍 <?= e($listing['location']) ?></div>
            <div class="flex items-center gap-1" style="margin-bottom: 0.75rem;">
              <span class="stars"><?= renderStars((float)$listing['rating']) ?></span>
              <span class="text-xs text-muted"><?= e($listing['rating']) ?></span>
            </div>
            <div class="card-price"><?= getPrice($listing) ?></div>
          </div>
          <div class="card-footer">
            <a href="<?= e($listing['detail_url']) ?>" class="btn btn-sm btn-secondary" style="flex:1;">Details</a>
            <?php if (isLoggedIn()): ?>
              <button
                class="btn btn-sm btn-primary"
                onclick="openBookingModal('<?= e($listing['id']) ?>','tour','<?= addslashes(e($listing['title'])) ?>',<?= (float)($meta['pricePerPerson'] ?? 0) ?>)"
              >Book Tour</button>
            <?php else: ?>
              <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-primary">Book Tour</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Include standard booking modals and scripts -->
<?php if (isLoggedIn()): ?>
<div class="modal-overlay" id="booking-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <h3 id="bk-modal-title">Book Tour</h3>
      <button class="modal-close" onclick="closeModal('booking-modal')">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>request_api.php" id="booking-form">
      <div class="modal-body">
        <input type="hidden" name="action" value="create_booking">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" id="bk-listing-id" name="listing_id" value="">
        <input type="hidden" id="bk-listing-type" name="listing_type" value="">
        <input type="hidden" id="bk-listing-title" name="listing_title" value="">
        <input type="hidden" id="bk-base-price" value="0">
        <input type="hidden" id="bk-total-price" name="total_price" value="0">
        <input type="hidden" id="bk-discount" name="discount" value="0">
        <input type="hidden" id="bk-gateway" name="gateway" value="">
        
        <div class="form-group">
          <label class="form-label" for="bk-quantity">Number of Persons</label>
          <input type="number" id="bk-quantity" name="quantity" class="form-control" value="1" min="1" max="10">
        </div>
        
        <div id="bk-tour-fields" style="display:none;">
          <div class="form-group">
            <label class="form-label" for="bk-tour-date">Tour Date</label>
            <input type="date" id="bk-tour-date" name="tour_date" class="form-control" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Coupon Code</label>
          <div style="display:flex;gap:0.5rem;">
            <input type="text" id="coupon-code" name="coupon_code" class="form-control" placeholder="WELCOME10" style="flex:1;">
            <button type="button" id="apply-coupon" class="btn btn-secondary btn-sm">Apply</button>
          </div>
          <div id="coupon-msg" class="text-xs text-muted" style="margin-top:0.35rem;"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Payment Method</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
            <?php foreach (['Airtel Money','TNM Mpamba','Bank Card','Direct NBS Transfer','Uthenga Pay'] as $gw): ?>
            <button type="button" class="gateway-btn btn btn-secondary btn-sm" data-gateway="<?= e($gw) ?>" id="gw-<?= str_replace([' ','/'],'_',$gw) ?>"><?= e($gw) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="glass-panel" style="padding:1rem;text-align:center;margin-top:0.5rem;">
          <div class="text-xs text-muted" style="margin-bottom:0.25rem;">Total Amount</div>
          <div id="bk-total" style="font-size:1.5rem;font-weight:800;color:var(--clr-accent);">MK 0</div>
          <div class="text-xs text-muted" style="margin-top:0.25rem;">10% platform commission included</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('booking-modal')">Cancel</button>
        <button type="button" id="proceed-to-payment" class="btn btn-primary">Continue to Payment →</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="payment-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <h3>Confirm Payment</h3>
      <button class="modal-close" onclick="closeModal('payment-modal')">✕</button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <div style="font-size:3rem;margin-bottom:1rem;">💳</div>
      <h4 id="pm-title" style="margin-bottom:0.5rem;"></h4>
      <div style="font-size:2rem;font-weight:800;color:var(--clr-accent);margin-bottom:0.5rem;" id="pm-total">MK 0</div>
      <div class="text-sm text-muted">via <strong id="pm-gateway"></strong></div>
      <div class="alert alert-info" style="margin-top:1.5rem;text-align:left;"><div><strong>Simulation Mode:</strong> No real payment will be charged.</div></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('payment-modal')">Back</button>
      <button type="submit" form="booking-form" id="confirm-payment-btn" class="btn btn-primary">✓ Pay Now</button>
    </div>
  </div>
</div>

<div id="booking-success" style="display:none;position:fixed;bottom:2rem;right:2rem;background:var(--clr-surface);border:1px solid var(--clr-green);border-radius:var(--radius-lg);padding:1.5rem;max-width:340px;box-shadow:var(--shadow-lg);z-index:300;">
  <div style="font-size:1.5rem;margin-bottom:0.5rem;">🎉</div>
  <h4 style="color:var(--clr-green);margin-bottom:0.25rem;">Booking Confirmed!</h4>
  <div class="text-sm text-muted" style="margin-bottom:0.75rem;">ID: <strong id="success-booking-id" class="text-accent"></strong></div>
  <div class="qr-block"><div class="text-xs text-muted" style="margin-bottom:0.5rem;">Digital Ticket</div><div class="qr-string" id="success-qr-code"></div></div>
  <div style="margin-top:0.75rem;font-size:0.85rem;">Total: <strong id="success-total" class="text-accent"></strong></div>
  <button onclick="this.parentElement.style.display='none'" class="btn btn-secondary btn-sm" style="margin-top:1rem;width:100%;">Close</button>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
