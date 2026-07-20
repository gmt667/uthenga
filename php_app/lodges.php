<?php
/**
 * Uthenga - Lodges Directory
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Explore Lodges';
$activeNav = 'stays';

$search = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$rating = trim($_GET['rating'] ?? '');

$accommodations = marketplace_fetch_properties($search, 0, false);
$lodges = [];

foreach ($accommodations as $listing) {
    $meta = json_decode($listing['meta'] ?? '[]', true) ?: [];
    $haystack = strtolower(($listing['title'] ?? '') . ' ' . ($listing['description'] ?? '') . ' ' . ($meta['category'] ?? ''));
    if (strpos($haystack, 'lodge') !== false || strpos($haystack, 'lodges') !== false) {
        $lodges[] = $listing;
    }
}

if ($location !== '' || $rating !== '') {
    $lodges = array_values(array_filter($lodges, function ($item) use ($location, $rating) {
        if ($location !== '' && stripos((string)($item['location'] ?? ''), $location) === false) {
            return false;
        }
        if ($rating !== '' && (float)($item['rating'] ?? 0) < (float)$rating) {
            return false;
        }
        return true;
    }));
}

if (empty($lodges)) {
    $lodges = array_values(array_filter($accommodations, function ($listing) {
        $meta = json_decode($listing['meta'] ?? '[]', true) ?: [];
        $haystack = strtolower(($listing['title'] ?? '') . ' ' . ($listing['description'] ?? '') . ' ' . ($meta['category'] ?? ''));
        return strpos($haystack, 'hotel') !== false || strpos($haystack, 'lodge') !== false;
    }));
}

$allLocations = dbQuery("
    SELECT DISTINCT location
    FROM listings
    WHERE listing_type = 'accommodation' AND is_active = 1 AND location <> ''
    ORDER BY location ASC
");

function lodgeStars(float $rating): string {
    $full = (int) floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    return str_repeat('*', $full) . str_repeat('½', $half) . str_repeat('·', 5 - $full - $half);
}

function lodgePrice(array $listing): string {
    $meta = json_decode($listing['meta'] ?? '[]', true) ?: [];
    return 'From ' . formatMWK($meta['rooms'][0]['pricePerNight'] ?? 0) . '/night';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Discover the best lodges across Malawi on Uthenga.">
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
      <div class="hero-eyebrow">Scenic Stays</div>
      <h1>Lodges in Malawi</h1>
      <p>Explore lakeshore lodges, mountain retreats, and relaxed hideaways across Malawi.</p>
      <div class="hero-btns">
        <a href="<?= BASE_URL ?>hotels.php" class="btn btn-primary btn-lg">Browse All Stays</a>
        <a href="<?= BASE_URL ?>hostels.php" class="btn btn-secondary btn-lg">View Hostels</a>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="filters-wrapper" style="margin-bottom:2rem;">
      <form method="GET" action="lodges.php" class="filter-form">
        <div class="filter-grid" style="grid-template-columns:2fr 1fr 1fr auto;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Search Keyword</label>
            <input type="text" name="q" class="form-control" placeholder="Search by lodge name, amenity..." value="<?= e($search) ?>">
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Location</label>
            <select name="location" class="form-control">
              <option value="">All Locations</option>
              <?php foreach ($allLocations as $loc): ?>
                <option value="<?= e($loc['location']) ?>" <?= $location === $loc['location'] ? 'selected' : '' ?>><?= e($loc['location']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Min Rating</label>
            <select name="rating" class="form-control">
              <option value="">Any Rating</option>
              <option value="4" <?= $rating === '4' ? 'selected' : '' ?>>4.0+ Stars</option>
              <option value="4.5" <?= $rating === '4.5' ? 'selected' : '' ?>>4.5+ Stars</option>
            </select>
          </div>
          <div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Filter</button>
          </div>
        </div>
      </form>
    </div>

    <?php if (empty($lodges)): ?>
      <div style="text-align:center; padding:4rem 0;">
        <h3>No lodges found</h3>
        <p class="text-muted">Try adjusting your search criteria or explore all stays.</p>
        <a href="lodges.php" class="btn btn-secondary" style="margin-top:1rem;">Reset Filters</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-4 gap-3">
        <?php foreach ($lodges as $listing): $meta = json_decode($listing['meta'] ?? '[]', true) ?: []; ?>
          <div class="listing-card-wrap">
            <div class="card">
              <div class="card-img-wrap">
                <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
                <span class="card-badge badge-accommodation">Lodge</span>
                <?php if (!empty($listing['featured'])): ?><span class="card-badge badge-featured" style="left:auto;right:0.75rem;">Featured</span><?php endif; ?>
              </div>
              <div class="card-body">
                <div class="card-title"><?= e($listing['title']) ?></div>
                <div class="card-loc">Location: <?= e($listing["location"]) ?></div>
                <div class="flex items-center gap-1" style="margin-bottom:0.75rem;">
                  <span class="stars"><?= lodgeStars((float)$listing['rating']) ?></span>
                  <span class="text-xs text-muted"><?= e($listing['rating']) ?></span>
                </div>
                <div class="card-price"><?= lodgePrice($listing) ?></div>
              </div>
              <div class="card-footer">
                <a href="<?= e($listing['detail_url']) ?>" class="btn btn-sm btn-secondary" style="flex:1;">Details</a>
                <?php if (isLoggedIn()): ?>
                  <button class="btn btn-sm btn-primary" onclick="openBookingModal('<?= e($listing['id']) ?>','accommodation','<?= addslashes(e($listing['title'])) ?>',<?= (float)($meta['rooms'][0]['pricePerNight'] ?? 0) ?>)">Book Now</button>
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
</section>

<?php if (isLoggedIn()): ?>
<div class="modal-overlay" id="booking-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <h3 id="bk-modal-title">Book Stay</h3>
      <button class="modal-close" onclick="closeModal('booking-modal')">Close</button>
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
        <input type="hidden" id="bk-quantity" name="quantity" value="1">

        <div id="bk-accom-fields">
          <div class="form-group">
            <label class="form-label" for="bk-checkin">Check-in Date</label>
            <input type="date" id="bk-checkin" name="check_in_date" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="bk-checkout">Check-out Date</label>
            <input type="date" id="bk-checkout" name="check_out_date" class="form-control" required>
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
            <button type="button" class="gateway-btn btn btn-secondary btn-sm" data-gateway="<?= e($gw) ?>"><?= e($gw) ?></button>
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
        <button type="button" id="proceed-to-payment" class="btn btn-primary">Continue to Payment</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="payment-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <h3>Confirm Payment</h3>
      <button class="modal-close" onclick="closeModal('payment-modal')">Close</button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <h4 id="pm-title" style="margin-bottom:0.5rem;"></h4>
      <div style="font-size:2rem;font-weight:800;color:var(--clr-accent);margin-bottom:0.5rem;" id="pm-total">MK 0</div>
      <div class="text-sm text-muted">via <strong id="pm-gateway"></strong></div>
      <div class="alert alert-info" style="margin-top:1.5rem;text-align:left;">
        <div><strong>Simulation Mode:</strong> No real payment will be charged.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('payment-modal')">Back</button>
      <button type="submit" form="booking-form" id="confirm-payment-btn" class="btn btn-primary">Pay Now</button>
    </div>
  </div>
</div>

<div id="booking-success" style="display:none;position:fixed;bottom:2rem;right:2rem;background:var(--clr-surface);border:1px solid var(--clr-green);border-radius:var(--radius-lg);padding:1.5rem;max-width:340px;box-shadow:var(--shadow-lg);z-index:300;">
  <h4 style="color:var(--clr-green);margin-bottom:0.25rem;">Booking Confirmed</h4>
  <div class="text-sm text-muted" style="margin-bottom:0.75rem;">ID: <strong id="success-booking-id" class="text-accent"></strong></div>
  <div class="qr-block"><div class="text-xs text-muted" style="margin-bottom:0.5rem;">Digital Ticket</div><div class="qr-string" id="success-qr-code"></div></div>
  <div style="margin-top:0.75rem;font-size:0.85rem;">Total: <strong id="success-total" class="text-accent"></strong></div>
  <button onclick="this.parentElement.style.display='none'" class="btn btn-secondary btn-sm" style="margin-top:1rem;width:100%;">Close</button>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
