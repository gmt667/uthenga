<?php
/**
 * Uthenga — Universal Listing Details Page
 */
require_once __DIR__ . '/config.php';

$type = trim($_GET['type'] ?? $_GET['listing_type'] ?? '');
$id = trim($_GET['id'] ?? $_GET['listing_id'] ?? '');

$listing = null;
if ($id !== '') {
    $validTypes = ['event', 'property', 'accommodation', 'tour', 'transport'];
    if ($type !== '' && in_array($type, $validTypes, true)) {
        $listing = marketplace_resolve_entity($type, $id);
    } else {
        foreach (['event', 'property', 'tour', 'transport'] as $candidateType) {
            $listing = marketplace_resolve_entity($candidateType, $id);
            if ($listing) {
                break;
            }
        }
    }
}
if (!$listing) {
    $pageTitle = 'Event Not Found';
    $activeNav = '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Event Not Found | Uthenga</title>
      <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    </head>
    <body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    <div class="container" style="padding: 4rem 0; text-align: center;">
      <h2>Event Not Found</h2>
      <p class="text-muted">The event ID is missing, invalid, or the listing has been removed.</p>
      <a href="<?= BASE_URL ?>events.php" class="btn btn-primary" style="margin-top: 1rem;">Browse Events</a>
      <a href="<?= BASE_URL ?>index.php" class="btn btn-secondary" style="margin-top: 1rem;">Back to Explore</a>
    </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    </body>
    </html>
    <?php
    exit;
}

$meta = json_decode($listing['meta'] ?? '{}', true) ?? [];
$gallery = json_decode($listing['gallery'] ?? '[]', true) ?? [];
$ticketCodeFormat = strtolower(trim((string)($meta['ticketCodeFormat'] ?? $meta['ticket_code_format'] ?? $meta['scanFormat'] ?? $meta['scan_format'] ?? 'qr')));
if (!in_array($ticketCodeFormat, ['qr', 'barcode', 'code'], true)) {
    $ticketCodeFormat = 'qr';
}
$reviews = [];
if (uthenga_table_exists('customer_reviews')) {
    $reviews = dbQuery(
        "SELECT cr.*, u.full_name AS user_name, DATE_FORMAT(cr.created_at, '%d %b %Y') AS review_date
         FROM customer_reviews cr
         LEFT JOIN users u ON u.id = cr.user_id
         WHERE cr.reference_id = ? AND cr.status = 'published'
         ORDER BY cr.created_at DESC",
        [$id]
    ) ?: [];
} elseif (uthenga_table_exists('reviews')) {
    $reviews = dbQuery(
        "SELECT r.*, r.user_name, DATE_FORMAT(r.review_date, '%d %b %Y') AS review_date
         FROM reviews r
         WHERE r.listing_id = ?
         ORDER BY r.review_date DESC",
        [$id]
    ) ?: [];
}

// Safely count related bookings — guard against missing booking_items table
$relCount = 0;
if (uthenga_table_exists('booking_items')) {
    try {
        if ($listing['listing_type'] === 'event') {
            $relCount = dbCount("SELECT COUNT(*) FROM booking_items WHERE item_type = 'event_ticket' AND reference_id = ?", [$id]);
        } elseif ($listing['listing_type'] === 'accommodation') {
            $relCount = dbCount("SELECT COUNT(*) FROM booking_items WHERE item_type = 'property_room' AND reference_id = ?", [$id]);
        } elseif ($listing['listing_type'] === 'tour') {
            $relCount = dbCount("SELECT COUNT(*) FROM booking_items WHERE item_type = 'tour_package' AND reference_id = ?", [$id]);
        } else {
            $relCount = dbCount("SELECT COUNT(*) FROM booking_items WHERE item_type = 'transport_seat' AND reference_id = ?", [$id]);
        }
    } catch (Throwable $e) {
        // Graceful fallback — non-critical stat
        $relCount = 0;
    }
}

$pageTitle = $listing['title'];
$activeNav = 'events';

// Fetch vendor profile
$vendor = dbQueryOne("SELECT email, avatar, joined_date FROM users WHERE id = ?", [$listing['vendor_id']]);

// Fetch database-driven inventory types
$dbTicketTypes = [];
$dbSeatClasses = [];
$dbRoomTypes   = [];

if ($listing['listing_type'] === 'event') {
    $dbTicketTypes = dbQuery("SELECT id, listing_id, name, description, price, total_quantity, remaining_quantity, sale_start, sale_end, is_active, sort_order FROM ticket_types WHERE listing_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC", [$id]);
} elseif ($listing['listing_type'] === 'transport') {
    $dbSeatClasses = dbQuery("SELECT id, listing_id, class_name, description, price, total_seats, remaining_seats, sort_order, is_active FROM seat_classes WHERE listing_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC", [$id]);
} elseif ($listing['listing_type'] === 'accommodation') {
    if (uthenga_table_exists('room_types')) {
        $dbRoomTypes = dbQuery("SELECT id, listing_id, room_name, description, price_per_night, total_rooms, available_rooms, max_occupancy, amenities, room_images, sort_order, is_active FROM room_types WHERE listing_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC", [$id]) ?: [];
    } else {
        $legacyRooms = $meta['rooms'] ?? [];
        foreach ($legacyRooms as $idx => $room) {
            $dbRoomTypes[] = [
                'id' => $room['id'] ?? ('legacy-room-' . ($idx + 1)),
                'listing_id' => $id,
                'room_name' => $room['name'] ?? ('Room ' . ($idx + 1)),
                'description' => $room['description'] ?? '',
                'price_per_night' => $room['pricePerNight'] ?? 0,
                'total_rooms' => $room['availableRooms'] ?? 1,
                'available_rooms' => $room['availableRooms'] ?? 1,
                'max_occupancy' => $room['capacity'] ?? 2,
                'amenities' => $room['amenities'] ?? [],
                'room_images' => $room['images'] ?? [],
                'sort_order' => $idx + 1,
                'is_active' => 1,
            ];
        }
    }
}

// Check if item is in favorites
$inWishlist = false;
if (isLoggedIn()) {
    if (uthenga_table_exists('favorites')) {
        $inWishlist = dbCount(
            "SELECT COUNT(*) FROM favorites WHERE user_id = ? AND reference_id = ? AND favorite_type = ?",
            [$_SESSION['user_id'], $id, $listing['listing_type'] === 'accommodation' ? 'property' : $listing['listing_type']]
        ) > 0;
    } else {
        $inWishlist = dbCount("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND listing_id = ?", [$_SESSION['user_id'], $id]) > 0;
    }
}

function detailPrice(array $listing, array $meta): string {
    if ($listing['listing_type'] === 'event') {
        return 'From ' . formatMWK($meta['price_amount'] ?? 0);
    }
    if ($listing['listing_type'] === 'accommodation') {
        return 'From ' . formatMWK($meta['rooms'][0]['pricePerNight'] ?? 0) . '/night';
    }
    if ($listing['listing_type'] === 'tour') {
        return formatMWK($meta['pricePerPerson'] ?? $meta['base_price'] ?? 0) . '/person';
    }
    if ($listing['listing_type'] === 'transport') {
        return formatMWK($meta['pricePerSeat'] ?? $meta['base_fare'] ?? 0) . '/seat';
    }
    return 'MK 0';
}

function bookingBasePrice(string $type, array $meta): float {
    if ($type === 'event') {
        return (float) ($meta['price_amount'] ?? 0);
    }
    if ($type === 'accommodation') {
        return (float) ($meta['rooms'][0]['pricePerNight'] ?? 0);
    }
    if ($type === 'tour') {
        return (float) ($meta['pricePerPerson'] ?? $meta['base_price'] ?? 0);
    }
    if ($type === 'transport') {
        return (float) ($meta['pricePerSeat'] ?? $meta['base_fare'] ?? 0);
    }
    return 0;
}

if (!function_exists('renderStars')) {
function renderStars(float $rating): string {
    $full = (int)floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    return str_repeat('★', $full) . str_repeat('½', $half) . str_repeat('☆', 5 - $full - $half);
}
}

/**
 * Returns the context-aware booking button label for a given listing type.
 * @param string $listingType  event | accommodation | tour | transport
 * @param bool   $immediate    When true, appends " & Pay Now" to indicate immediate payment.
 */
if (!function_exists('uthenga_booking_btn_label')) {
function uthenga_booking_btn_label(string $listingType, bool $immediate = false): string {
    $labels = [
        'event'         => 'ðŸŽ« Buy Ticket',
        'accommodation' => 'ðŸ¨ Book Now',
        'tour'          => 'ðŸŒ Book Tour',
        'transport'     => 'ðŸšŒ Book Seat',
    ];
    $label = $labels[$listingType] ?? 'ðŸ“‹ Book Now';
    return $immediate ? 'Book & Pay Now' : $label;
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e(substr($listing['description'], 0, 155)) ?>">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title><?= e($listing['title']) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <!-- Leaflet Map CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <style>
    .detail-hero { position:relative; height:420px; overflow:hidden; border-radius: var(--radius-xl); margin-bottom:2rem; background: #000; }
    .detail-hero img { width:100%; height:100%; object-fit:cover; transition: opacity 0.25s ease-in-out; }
    .detail-hero-overlay { position:absolute; inset:0; background:linear-gradient(to top, rgba(0,0,0,0.85) 0%, transparent 60%); }
    .detail-hero-info { position:absolute; bottom:0; left:0; right:0; padding:2.5rem; }
    .gallery-strip { display:flex; gap:0.75rem; margin-bottom:2rem; overflow-x:auto; padding-bottom:0.5rem; }
    .gallery-strip img { height:100px; width:150px; object-fit:cover; border-radius:var(--radius-md); flex-shrink:0; cursor:pointer; transition:transform 0.2s; border: 2px solid transparent; }
    .gallery-strip img:hover { transform:scale(1.04); }
    .gallery-strip img.active { border-color: var(--clr-accent); }
    .detail-grid { display:grid; grid-template-columns:1fr 360px; gap:2.5rem; align-items:start; }
    .detail-sidebar { position:sticky; top:80px; }
    .meta-pill { display:inline-flex; align-items:center; gap:0.35rem; padding:0.3rem 0.75rem; background:var(--clr-surface2); border:1px solid var(--clr-border); border-radius:100px; font-size:0.78rem; color:var(--clr-text-soft); margin:0.2rem; }
    .review-card { background:var(--clr-surface2); border:1px solid var(--clr-border); border-radius:var(--radius-md); padding:1rem; margin-bottom:0.75rem; }
    @media(max-width:900px){ .detail-grid{grid-template-columns:1fr;} .detail-sidebar{position:static;} }
    .wishlist-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      background: var(--clr-surface2);
      border: 1px solid var(--clr-border);
      color: var(--clr-text);
      padding: 0.6rem 1.2rem;
      border-radius: var(--radius-md);
      cursor: pointer;
      font-weight: 600;
      transition: var(--transition);
    }
    .wishlist-btn:hover {
      background: rgba(255,255,255,0.05);
      border-color: var(--clr-text-muted);
    }
    .wishlist-btn.active {
      color: var(--clr-red);
      border-color: rgba(239, 68, 68, 0.4);
      background: rgba(239, 68, 68, 0.08);
    }
    .action-btn-row {
      display: flex;
      gap: 0.75rem;
      margin-bottom: 0.75rem;
    }
    
    /* ─── Timeline Styles ─── */
    .timeline {
      position: relative;
      padding-left: 2rem;
      margin: 1.5rem 0;
      border-left: 2px solid var(--clr-border);
    }
    .timeline-item {
      position: relative;
      margin-bottom: 1.5rem;
    }
    .timeline-item::before {
      content: '';
      position: absolute;
      left: calc(-2rem - 6px);
      top: 4px;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--clr-accent);
      border: 2px solid var(--clr-bg);
    }
    .timeline-time {
      font-size: 0.78rem;
      color: var(--clr-accent);
      font-weight: 700;
      margin-bottom: 0.2rem;
    }
    .timeline-title {
      font-size: 0.92rem;
      font-weight: 600;
      color: #fff;
    }
    
    /* ─── Map Styles ─── */
    .map-container {
      margin-bottom: 2.5rem;
    }
    .map-box {
      height: 250px;
      border-radius: var(--radius-md);
      border: 1px solid var(--clr-border);
      background: var(--clr-surface2);
      z-index: 1;
    }

    /* ─── Share Feedback Message ─── */
    .share-toast {
      position: fixed;
      bottom: 2rem;
      left: 50%;
      transform: translateX(-50%) translateY(100px);
      background: var(--clr-surface2);
      border: 1px solid var(--clr-green);
      color: var(--clr-text);
      padding: 0.75rem 1.5rem;
      border-radius: var(--radius-md);
      font-size: 0.88rem;
      font-weight: 600;
      z-index: 999;
      box-shadow: var(--shadow-md);
      opacity: 0;
      transition: all 0.3s ease;
    }
    .share-toast.show {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
    }
    .seat-map {
      display: grid;
      gap: 0.5rem;
      margin: 0.75rem 0;
      padding: 0.75rem;
      background: var(--clr-surface2);
      border-radius: var(--radius-md);
      border: 1px solid var(--clr-border);
      justify-content: center;
    }
    .seat-item {
      width: 32px;
      height: 32px;
      background: var(--clr-bg);
      border: 1px solid var(--clr-border);
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.7rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
    }
    .seat-item.booked {
      background: rgba(239, 68, 68, 0.15);
      border-color: rgba(239, 68, 68, 0.3);
      color: var(--clr-text-soft);
      cursor: not-allowed;
    }
    .seat-item.selected {
      background: var(--clr-primary);
      border-color: var(--clr-primary);
      color: #fff;
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container" style="padding-top:2rem;padding-bottom:3rem;">

  <!-- Breadcrumb -->
  <nav style="font-size:0.8rem;color:var(--clr-text-muted);margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div>
      <a href="<?= BASE_URL ?>index.php">Explore</a>
      <span style="margin:0 0.4rem;">›</span>
      <span style="margin:0 0.4rem;">›</span>
      <a href="<?= BASE_URL ?><?= e($listing['listing_type']) ?>s.php"><?= ucfirst(e($listing['listing_type'])) ?>s</a>
      <span style="margin:0 0.4rem;">›</span>
      <span style="color:var(--clr-text-soft);"><?= e($listing['title']) ?></span>
    </div>
    <div style="display:flex; gap:0.5rem;">
      <?php if (isLoggedIn()): ?>
        <button class="wishlist-btn <?= $inWishlist ? 'active' : '' ?>" id="wishlist-toggle-btn" data-id="<?= e($id) ?>">
          <?= $inWishlist ? '❤️ Saved' : '🤍 Save to Wishlist' ?>
        </button>
      <?php else: ?>
        <a href="<?= BASE_URL ?>login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="wishlist-btn">🤍 Save to Wishlist</a>
      <?php endif; ?>
      <button class="wishlist-btn" id="share-listing-btn">🔗 Share</button>
    </div>
  </nav>

  <!-- Hero Image -->
  <div class="detail-hero animate-in">
    <img id="main-detail-img" src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>">
    <div class="detail-hero-overlay"></div>
    <div class="detail-hero-info">
      <?php
      $lType = strtolower($listing['listing_type'] ?? '');
      $typeBadge = ($lType === 'event') ? 'badge-event' : (($lType === 'accommodation') ? 'badge-accommodation' : (($lType === 'tour') ? 'badge-tour' : (($lType === 'transport') ? 'badge-transport' : '')));
      ?>
      <span class="card-badge <?= $typeBadge ?>" style="position:static;display:inline-flex;margin-bottom:0.5rem;"><?= ucfirst(e($listing['listing_type'])) ?></span>
      <h1 style="font-size:clamp(1.4rem,4vw,2.2rem);color:#fff;margin-bottom:0.4rem;text-shadow:0 2px 8px rgba(0,0,0,0.5);"><?= e($listing['title']) ?></h1>
      <div style="display:flex;align-items:center;gap:1rem;color:rgba(255,255,255,0.75);font-size:0.9rem;flex-wrap:wrap;">
        <span>📍 <?= e($listing['location']) ?></span>
        <span style="color:var(--clr-accent);">★ <?= e($listing['rating']) ?></span>
        <span><?= count($reviews) ?> review<?= count($reviews) !== 1 ? 's' : '' ?></span>
        <span>🎟 <?= number_format($relCount) ?> booked</span>
        <?php if ($listing['featured']): ?><span style="color:var(--clr-accent);">★ Featured</span><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Gallery Strip -->
  <?php if (!empty($gallery)): ?>
  <div class="gallery-strip">
    <img src="<?= e($listing['image']) ?>" alt="Main cover" class="active" onclick="selectGalleryImg(this)">
    <?php foreach ($gallery as $img): ?>
      <img src="<?= e($img) ?>" alt="Gallery image" loading="lazy" onclick="selectGalleryImg(this)">
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Main Content Grid -->
  <div class="detail-grid">

    <!-- Left: Details -->
    <div>
      <!-- Description -->
      <section style="margin-bottom:2.5rem;">
        <h2 style="font-size:1.3rem;margin-bottom:1rem;">About This <?= ucfirst(e($listing['listing_type'])) ?></h2>
        <p style="line-height:1.8;color:var(--clr-text-soft);"><?= nl2br(e($listing['description'])) ?></p>
      </section>

      <!-- Type-specific Details Panel -->
      <section style="margin-bottom:2.5rem;">
        <h2 style="font-size:1.3rem;margin-bottom:1rem;">Specifications</h2>
        <div class="glass-panel" style="padding:1.5rem;">
        <?php if ($listing['listing_type'] === 'event'): ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div><div class="text-xs text-muted">📅 Date</div><div style="font-weight:600;margin-top:0.2rem;"><?= e($meta['date'] ?? 'TBC') ?></div></div>
            <div><div class="text-xs text-muted">⏰ Time</div><div style="font-weight:600;margin-top:0.2rem;"><?= e($meta['time'] ?? 'TBC') ?></div></div>
            <div><div class="text-xs text-muted">ðŸŸ Category</div><div style="font-weight:600;margin-top:0.2rem;"><?= e($meta['category'] ?? '') ?></div></div>
            <div><div class="text-xs text-muted">ðŸ‘¥ Capacity</div><div style="font-weight:600;margin-top:0.2rem;"><?= number_format($meta['venueCapacity'] ?? 0) ?> seats</div></div>
          </div>
          <?php if (!empty($dbTicketTypes)): ?>
            <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--clr-border);">
              <div class="text-xs text-muted" style="margin-bottom:0.75rem;">Ticket Options</div>
              <div class="ticket-type-grid" style="margin:0;">
                <?php foreach ($dbTicketTypes as $tt):
                  $pct = $tt['total_quantity'] > 0 ? round(($tt['remaining_quantity'] / $tt['total_quantity']) * 100) : 0;
                ?>
                <div class="ticket-type-card <?= $tt['remaining_quantity'] == 0 ? 'sold-out' : '' ?>" style="cursor:default;">
                  <div class="ticket-type-name"><?= e($tt['name']) ?></div>
                  <div class="ticket-type-price">MK <?= number_format($tt['price']) ?></div>
                  <div class="ticket-type-avail"><?= number_format($tt['remaining_quantity']) ?> / <?= number_format($tt['total_quantity']) ?> remaining</div>
                  <div class="availability-bar">
                    <div class="availability-bar-fill <?= $pct < 20 ? 'low' : ($pct < 50 ? 'medium' : 'good') ?>" style="width:<?= $pct ?>%"></div>
                  </div>
                  <?php if ($tt['remaining_quantity'] == 0): ?>
                    <span class="ticket-sold-out-badge">Sold Out</span>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--clr-border);">
              <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:var(--radius-md);padding:1rem;text-align:center;">
                <div class="text-xs text-muted">Standard Ticket</div>
                <div style="font-size:1.3rem;font-weight:800;color:var(--clr-accent);margin:0.25rem 0;">MK <?= number_format($meta['standardTicketPrice'] ?? 0) ?></div>
                <div class="text-xs text-muted"><?= number_format($meta['standardAvailable'] ?? 0) ?> remaining</div>
              </div>
              <div style="background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2);border-radius:var(--radius-md);padding:1rem;text-align:center;">
                <div class="text-xs text-muted">VIP Ticket</div>
                <div style="font-size:1.3rem;font-weight:800;color:#a78bfa;margin:0.25rem 0;">MK <?= number_format($meta['vipTicketPrice'] ?? 0) ?></div>
                <div class="text-xs text-muted"><?= number_format($meta['vipAvailable'] ?? 0) ?> remaining</div>
              </div>
            </div>
          <?php endif; ?>

        <?php elseif ($listing['listing_type'] === 'accommodation'): ?>
          <!-- Room Availability Calendar & Date Check -->
          <div class="glass-panel" style="padding: 1.25rem; margin-bottom: 1.5rem; background: rgba(6, 182, 212, 0.05); border: 1px solid rgba(6, 182, 212, 0.15);">
            <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.35rem;">
              <span>📅</span> Check-in & Room Availability Calendar
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.75rem; align-items: flex-end;">
              <div class="form-group" style="margin-bottom: 0;">
                <label class="text-xs text-muted" style="display: block; margin-bottom: 0.25rem;">Check In</label>
                <input type="date" id="avail-checkin" class="form-control text-xs" min="<?= date('Y-m-d') ?>" style="padding: 0.4rem;">
              </div>
              <div class="form-group" style="margin-bottom: 0;">
                <label class="text-xs text-muted" style="display: block; margin-bottom: 0.25rem;">Check Out</label>
                <input type="date" id="avail-checkout" class="form-control text-xs" min="<?= date('Y-m-d', time()+86400) ?>" style="padding: 0.4rem;">
              </div>
              <button type="button" class="btn btn-primary btn-sm" onclick="checkAccommodationAvailability(<?= (int)$listing['id'] ?>)">Check Availability</button>
            </div>
            <div id="avail-status-msg" class="text-xs text-muted" style="margin-top: 0.75rem;">Select check-in and check-out dates to confirm availability.</div>
          </div>

          <?php if (!empty($meta['amenities'])): ?>
          <div style="margin-bottom:1.25rem;">
            <div class="text-xs text-muted" style="margin-bottom:0.5rem;">Amenities</div>
            <div>
              <?php foreach ($meta['amenities'] as $a): ?>
                <span class="meta-pill">✓ <?= e($a) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($dbRoomTypes)): ?>
            <div>
              <div class="text-xs text-muted" style="margin-bottom:0.75rem;">Room Options</div>
              <div style="display:grid;gap:0.75rem;">
                <?php foreach ($dbRoomTypes as $room):
                  $roomPct = $room['total_rooms'] > 0 ? round(($room['available_rooms'] / $room['total_rooms']) * 100) : 0;
                  $amenities = json_decode($room['amenities'] ?? '[]', true) ?? [];
                ?>
                <div style="padding:1rem;background:var(--clr-surface2);border:1px solid var(--clr-border);border-radius:var(--radius-md);">
                  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:0.5rem;">
                    <div style="flex:1;">
                      <div style="font-weight:700;font-size:0.95rem;color:#fff;"><?= e($room['room_name']) ?></div>
                      <div class="text-xs text-muted" style="margin-top:0.25rem;">Max guests: <?= $room['max_occupancy'] ?> · <?= number_format($room['available_rooms']) ?> / <?= number_format($room['total_rooms']) ?> rooms available</div>
                      <?php if (!empty($amenities)): ?>
                        <div class="room-amenities">
                          <?php foreach (array_slice($amenities, 0, 4) as $am): ?>
                            <span class="room-amenity-tag"><?= e($am) ?></span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div style="text-align:right;">
                      <div style="font-weight:800;color:var(--clr-cyan);font-size:1.15rem;">MK <?= number_format($room['price_per_night']) ?></div>
                      <div class="text-xs text-muted">per night</div>
                    </div>
                  </div>
                  <div class="availability-bar" style="margin-top:0.6rem;">
                    <div class="availability-bar-fill <?= $roomPct < 20 ? 'low' : ($roomPct < 50 ? 'medium' : 'good') ?>" style="width:<?= $roomPct ?>%"></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php elseif (!empty($meta['rooms'])): ?>
          <div>
            <div class="text-xs text-muted" style="margin-bottom:0.75rem;">Room Options</div>
            <div style="display:grid;gap:0.75rem;">
              <?php foreach ($meta['rooms'] as $room): ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:0.85rem;background:var(--clr-surface2);border:1px solid var(--clr-border);border-radius:var(--radius-md);">
                <div>
                  <div style="font-weight:600;font-size:0.9rem;"><?= e($room['name']) ?></div>
                  <div class="text-xs text-muted">Capacity: <?= (int)($room['capacity'] ?? 2) ?> guests · <?= (int)($room['availableRooms'] ?? 0) ?> available</div>
                </div>
                <div style="text-align:right;">
                  <div style="font-weight:700;color:var(--clr-accent);">MK <?= number_format($room['pricePerNight'] ?? 0) ?></div>
                  <div class="text-xs text-muted">per night</div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

        <?php elseif ($listing['listing_type'] === 'tour'): ?>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
            <div style="text-align:center;padding:1rem;background:var(--clr-surface2);border-radius:var(--radius-md);">
              <div style="font-size:1.5rem;">ðŸ—“</div>
              <div style="font-weight:700;margin:0.25rem 0;"><?= (int)($meta['durationDays'] ?? 1) ?></div>
              <div class="text-xs text-muted">Days</div>
            </div>
            <div style="text-align:center;padding:1rem;background:var(--clr-surface2);border-radius:var(--radius-md);">
              <div style="font-size:1.5rem;">ðŸ‘¥</div>
              <div style="font-weight:700;margin:0.25rem 0;">Max <?= (int)($meta['maxGroupSize'] ?? 0) ?></div>
              <div class="text-xs text-muted">Per Group</div>
            </div>
            <div style="text-align:center;padding:1rem;background:var(--clr-surface2);border-radius:var(--radius-md);">
              <div style="font-size:1.5rem;">ðŸ’°</div>
              <div style="font-weight:700;margin:0.25rem 0;color:var(--clr-accent);">MK <?= number_format($meta['pricePerPerson'] ?? 0) ?></div>
              <div class="text-xs text-muted">Per Person</div>
            </div>
          </div>
          <?php if (!empty($meta['datesAvailable'])): ?>
          <div>
            <div class="text-xs text-muted" style="margin-bottom:0.5rem;">Available Dates</div>
            <div>
              <?php foreach ($meta['datesAvailable'] as $d): ?>
                <span class="meta-pill">📅 <?= e($d) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if (!empty($meta['itinerary'])): ?>
          <div style="margin-top:1.25rem;border-top:1px solid var(--clr-border);padding-top:1.25rem;">
            <div class="text-xs text-muted" style="margin-bottom:0.75rem;">Itinerary</div>
            <?php foreach ($meta['itinerary'] as $day): ?>
            <div style="display:flex;gap:0.75rem;margin-bottom:0.75rem;">
              <div style="width:28px;height:28px;border-radius:50%;background:var(--clr-accent);color:#000;font-size:0.75rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <?= (int)($day['day'] ?? 1) ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:0.875rem;"><?= e($day['title'] ?? '') ?></div>
                <div class="text-xs text-muted"><?= e($day['description'] ?? '') ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

        <?php elseif ($listing['listing_type'] === 'transport'): ?>
          <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:1rem;align-items:center;margin-bottom:1.25rem;">
            <div style="text-align:center;padding:1rem;background:var(--clr-surface2);border-radius:var(--radius-md);">
              <div style="font-weight:700;font-size:1.1rem;"><?= e($meta['routeFrom'] ?? '') ?></div>
              <div class="text-xs text-muted"><?= e($meta['departureTime'] ?? '') ?></div>
            </div>
            <div style="text-align:center;color:var(--clr-accent);font-size:1.5rem;">→</div>
            <div style="text-align:center;padding:1rem;background:var(--clr-surface2);border-radius:var(--radius-md);">
              <div style="font-weight:700;font-size:1.1rem;"><?= e($meta['routeTo'] ?? '') ?></div>
              <div class="text-xs text-muted"><?= e($meta['arrivalTime'] ?? '') ?></div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem;">
            <div><div class="text-xs text-muted">Vehicle</div><div style="font-weight:600;margin-top:0.2rem;"><?= e($meta['vehicleType'] ?? '') ?></div></div>
            <div><div class="text-xs text-muted">Total Seats</div><div style="font-weight:600;margin-top:0.2rem;"><?= number_format($meta['totalSeats'] ?? 0) ?></div></div>
          </div>
          <?php if (!empty($dbSeatClasses)): ?>
            <div>
              <div class="text-xs text-muted" style="margin-bottom:0.75rem;">Seat Class Options</div>
              <div style="display:grid;gap:0.6rem;">
                <?php foreach ($dbSeatClasses as $sc):
                  $scPct = $sc['total_seats'] > 0 ? round(($sc['remaining_seats'] / $sc['total_seats']) * 100) : 0;
                ?>
                <div style="padding:0.75rem 1rem;background:var(--clr-surface2);border:1px solid var(--clr-border);border-radius:var(--radius-sm);">
                  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                    <div style="flex:1;">
                      <strong style="font-size:0.9rem;"><?= e($sc['class_name']) ?></strong>
                      <div class="text-xs text-muted" style="margin-top:0.2rem;"><?= number_format($sc['remaining_seats']) ?> / <?= number_format($sc['total_seats']) ?> seats remaining</div>
                    </div>
                    <div>
                      <strong style="color:var(--clr-cyan);font-size:1rem;">MK <?= number_format($sc['price']) ?></strong>
                    </div>
                  </div>
                  <div class="availability-bar" style="margin-top:0.5rem;">
                    <div class="availability-bar-fill <?= $scPct < 20 ? 'low' : ($scPct < 50 ? 'medium' : 'good') ?>" style="width:<?= $scPct ?>%"></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php elseif (!empty($meta['scheduleDays'])): ?>
          <div style="margin-top:1rem;">
            <div class="text-xs text-muted" style="margin-bottom:0.5rem;">Schedule</div>
            <?php foreach ($meta['scheduleDays'] as $day): ?><span class="meta-pill"><?= e($day) ?></span><?php endforeach; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>
        </div>
      </section>

      <!-- Event Schedule Timeline for Event category -->
      <?php if ($listing['listing_type'] === 'event'): ?>
      <section style="margin-bottom:2.5rem;">
        <h2 style="font-size:1.3rem;margin-bottom:1rem;">Event Schedule & Programme</h2>
        <div class="glass-panel" style="padding:1.5rem;">
          <div class="timeline">
            <div class="timeline-item">
              <div class="timeline-time">12:00 PM</div>
              <div class="timeline-title">Gates & Registration Desk Open</div>
              <p class="text-xs text-muted">Security checks and digital ticket scanning. Standard and VIP registration queues active.</p>
            </div>
            <div class="timeline-item">
              <div class="timeline-time">02:00 PM</div>
              <div class="timeline-title">Opening Acts & Panel Discussion</div>
              <p class="text-xs text-muted">Local acts and guest panelists take the main stage for opening keynotes and presentations.</p>
            </div>
            <div class="timeline-item">
              <div class="timeline-time">05:30 PM</div>
              <div class="timeline-title">Networking Sunset & Appetizers</div>
              <p class="text-xs text-muted">Interact with vendors, attendees, and organizers. Premium catering stands open.</p>
            </div>
            <div class="timeline-item">
              <div class="timeline-time">08:00 PM</div>
              <div class="timeline-title">Main Performances & Headline Showcase</div>
              <p class="text-xs text-muted">Headline performance, announcements, and primary showcase. VIP lounge amenities fully open.</p>
            </div>
            <div class="timeline-item" style="margin-bottom:0;">
              <div class="timeline-time">11:30 PM</div>
              <div class="timeline-title">Closing Ceremony & Event Curfew</div>
              <p class="text-xs text-muted">Final remarks, closing remarks by organizers, and secure venue evacuation protocols.</p>
            </div>
          </div>
        </div>
      </section>
      <?php endif; ?>

      <!-- Map Location Section -->
      <section class="map-container">
        <h2 style="font-size:1.3rem;margin-bottom:1rem;">Venue & Location Map</h2>
        <div class="glass-panel" style="padding: 1rem;">
          <div style="font-size:0.85rem; color:var(--clr-text-soft); margin-bottom:0.75rem;">ðŸ“ <strong><?= e($listing['location']) ?></strong></div>
          <div id="map" class="map-box"></div>
        </div>
      </section>

      <!-- Organizer Profile Card -->
      <section style="margin-bottom:2.5rem;">
        <h2 style="font-size:1.3rem;margin-bottom:1rem;">Organizer Profile</h2>
        <div class="glass-panel" style="padding:1.5rem; display:flex; gap:1.25rem; align-items:center; flex-wrap:wrap;">
          <div style="width:72px; height:72px; border-radius:50%; overflow:hidden; border: 2px solid var(--clr-accent); flex-shrink:0;">
            <img src="<?= e($vendor['avatar'] ?? 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=150&fit=crop&q=80') ?>" alt="<?= e($listing['vendor_name']) ?>" style="width:100%; height:100%; object-fit:cover;">
          </div>
          <div>
            <h3 style="font-size:1.1rem; margin-bottom:0.25rem; color:#fff;"><?= e($listing['vendor_name']) ?></h3>
            <div style="font-size:0.78rem; color:var(--clr-green); font-weight:700; margin-bottom:0.4rem;">✓ Verified Service Provider</div>
            <p class="text-xs text-muted" style="margin-bottom:0.2rem;">ðŸ“§ Email: <?= e($vendor['email'] ?? 'info@uthenga.com') ?></p>
            <p class="text-xs text-muted">Joined: <?= e($vendor['joined_date'] ?? '2026-01-01') ?></p>
          </div>
        </div>
      </section>

      <!-- Reviews -->
      <section>
        <h2 style="font-size:1.3rem;margin-bottom:1rem;">Reviews (<?= count($reviews) ?>)</h2>
        <?php if (empty($reviews)): ?>
          <p class="text-muted">No reviews yet. Be the first to book and leave a review!</p>
        <?php else: ?>
          <?php foreach ($reviews as $rev): ?>
          <div class="review-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.4rem;">
              <span style="font-weight:600;font-size:0.875rem;"><?= e($rev['user_name']) ?></span>
              <div style="display:flex;align-items:center;gap:0.5rem;">
                <span style="color:var(--clr-accent);"><?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', 5-(int)$rev['rating']) ?></span>
                <span class="text-xs text-muted"><?= e($rev['review_date']) ?></span>
              </div>
            </div>
            <p class="text-sm"><?= e($rev['comment']) ?></p>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Write Review Form -->
        <?php if (isLoggedIn()): ?>
          <div class="glass-panel" style="padding: 1.5rem; margin-top: 1.5rem; border: 1px solid var(--clr-border);">
            <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">Write a Review</h3>
            <form id="submit-review-form" onsubmit="submitReview(event)">
              <input type="hidden" name="reference_id" value="<?= e($listing['id']) ?>">
              <input type="hidden" name="type" value="<?= e($listing['listing_type']) ?>">
              
              <div class="grid grid-cols-2 gap-4" style="margin-bottom: 1rem;">
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.25rem;">Rating</label>
                  <select name="rating" class="form-control text-sm" required style="padding: 0.4rem;">
                    <option value="5">★★★★★ (5 Stars)</option>
                    <option value="4">★★★★☆ (4 Stars)</option>
                    <option value="3">★★★☆☆ (3 Stars)</option>
                    <option value="2">★★☆☆☆ (2 Stars)</option>
                    <option value="1">★☆☆☆☆ (1 Star)</option>
                  </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                  <label class="form-label" style="display: block; margin-bottom: 0.25rem;">Review Title</label>
                  <input type="text" name="title" class="form-control text-sm" placeholder="e.g. Amazing experience!" style="padding: 0.4rem;">
                </div>
              </div>
              
              <div class="form-group">
                <label class="form-label" style="display: block; margin-bottom: 0.25rem;">Your Comment</label>
                <textarea name="comment" class="form-control text-sm" rows="3" required placeholder="Write your review here..."></textarea>
              </div>
              
              <button type="submit" class="btn btn-primary btn-sm">Submit Review</button>
              <span id="review-status" class="text-xs text-muted" style="margin-left: 1rem;"></span>
            </form>
          </div>
        <?php else: ?>
          <div style="text-align: center; margin-top: 1.5rem; padding: 1rem; border: 1px dashed var(--clr-border); border-radius: var(--radius-md);">
            <span class="text-sm text-muted">You must be logged in to write a review. <a href="<?= BASE_URL ?>login.php" style="color: var(--clr-primary);">Sign In</a></span>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <!-- Right: Booking Sidebar -->
    <div class="detail-sidebar">
      <div class="glass-panel" style="padding:1.75rem;">
        <div style="text-align:center;margin-bottom:1.5rem;">
          <div class="text-xs text-muted" style="margin-bottom:0.25rem;">Starting From</div>
          <div style="font-size:2rem;font-weight:800;color:var(--clr-accent);"><?= detailPrice($listing, $meta) ?></div>
          <?php if ($listing['listing_type'] === 'event'): ?>
            <div class="text-xs text-muted">Standard ticket · VIP also available</div>
          <?php endif; ?>
        </div>

        <!-- Event Countdown Timer -->
        <?php if ($listing['listing_type'] === 'event' && !empty($meta['date'])): ?>
          <div style="padding:1rem; margin-bottom:1.5rem; text-align:center; background: rgba(6, 182, 212, 0.1); border: 1px solid var(--clr-primary); border-radius: var(--radius-md);">
            <div class="text-xs text-muted" style="margin-bottom:0.4rem; text-transform:uppercase; font-weight:700; color:var(--clr-primary);">⏳ Event Countdown</div>
            <div id="event-countdown" style="font-size:1.3rem; font-weight:800; font-family:monospace; color:#fff; letter-spacing: 0.05em;">--d --h --m --s</div>
          </div>
          <script>
          (function() {
            var eventDateStr = "<?= e($meta['date']) ?>";
            var eventTimeStr = "<?= e($meta['time'] ?? '12:00 PM') ?>";
            var targetDate = new Date(eventDateStr + ' ' + eventTimeStr);
            var countdownEl = document.getElementById('event-countdown');
            
            function updateTimer() {
              var now = new Date();
              var diff = targetDate - now;
              
              if (diff <= 0) {
                if (countdownEl) countdownEl.textContent = 'EVENT STARTED';
                clearInterval(timerInterval);
                return;
              }
              
              var days = Math.floor(diff / 86400000);
              var hours = Math.floor((diff % 86400000) / 3600000);
              var minutes = Math.floor((diff % 3600000) / 60000);
              var seconds = Math.floor((diff % 60000) / 1000);
              
              if (countdownEl) {
                countdownEl.textContent = days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
              }
            }
            
            updateTimer();
            var timerInterval = setInterval(updateTimer, 1000);
          })();
          </script>
        <?php endif; ?>

        <!-- Vendor Info -->
        <div style="display:flex;align-items:center;gap:0.75rem;padding:0.85rem;background:var(--clr-surface2);border-radius:var(--radius-md);margin-bottom:1.5rem;">
          <div style="width:38px;height:38px;border-radius:50%;background:var(--clr-accent);color:#000;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;">ðŸª</div>
          <div>
            <div style="font-weight:600;font-size:0.85rem;"><?= e($listing['vendor_name']) ?></div>
            <div class="text-xs text-muted">Verified Provider</div>
          </div>
        </div>

        <!-- CTA / Auth Wall -->
        <?php if (isLoggedIn()): ?>
          <button
            class="btn btn-primary btn-lg pulse-glow"
            style="width:100%;margin-bottom:0.75rem;"
            id="detail-book-btn"
            onclick="openBookingModal(
              '<?= e($listing['id']) ?>',
              '<?= e($listing['listing_type']) ?>',
              '<?= addslashes(e($listing['title'])) ?>',
              <?= bookingBasePrice($listing['listing_type'], $meta) ?>,
              <?= (float)($meta['vipTicketPrice'] ?? 0) ?>
            ); if (window.trackEventMetric) { window.trackEventMetric('<?= e($listing['id']) ?>', 'click'); }"
          ><?= uthenga_booking_btn_label($listing['listing_type']) ?></button>
        <?php else: ?>
          <a href="<?= BASE_URL ?>login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
             class="btn btn-primary btn-lg pulse-glow"
             style="width:100%;text-align:center;margin-bottom:0.75rem;"
             id="detail-login-btn">
            Sign In to Book
          </a>
          <a href="<?= BASE_URL ?>register.php" class="btn btn-secondary btn-lg" style="width:100%;text-align:center;" id="detail-register-btn">Create Account</a>
        <?php endif; ?>

        <div class="text-xs text-muted" style="text-align:center;margin-top:0.75rem;">
          🔒 Secure booking · Instant confirmation
        </div>

        <!-- Quick Facts -->
        <div style="border-top:1px solid var(--clr-border);margin-top:1.5rem;padding-top:1.25rem;">
          <div style="display:grid;gap:0.6rem;">
            <div class="flex items-center justify-between text-sm">
              <span class="text-muted">Rating</span>
              <span style="color:var(--clr-accent);">★ <?= e($listing['rating']) ?>/5.0</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-muted">Bookings</span>
              <span><?= number_format($relCount) ?> completed</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-muted">Type</span>
              <span style="text-transform:capitalize;"><?= e($listing['listing_type']) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Back Link -->
      <a href="<?= BASE_URL ?><?= e($listing['listing_type']) ?>s.php" class="btn btn-secondary" style="width:100%;text-align:center;margin-top:1rem;" id="back-to-listings">
        ← Back to <?= ucfirst(e($listing['listing_type'])) ?>s
      </a>
    </div>
  </div>
</div>

<!-- Booking Modal -->
<?php if (isLoggedIn()): ?>
<div class="modal-overlay" id="booking-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <h3 id="bk-modal-title"><?= uthenga_booking_btn_label($listing['listing_type']) ?></h3>
      <button class="modal-close" onclick="closeModal('booking-modal')" aria-label="Close">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>request_api.php" id="booking-form">
      <div class="modal-body">
        <input type="hidden" name="action" value="create_booking">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" id="bk-listing-id" name="listing_id" value="">
        <input type="hidden" id="bk-listing-type" name="listing_type" value="">
        <input type="hidden" id="bk-listing-title" name="listing_title" value="">
        <input type="hidden" id="bk-base-price" value="0">
        <input type="hidden" id="bk-total-price" name="total_price" value="0">
        <input type="hidden" id="bk-discount" name="discount" value="0">
        <input type="hidden" id="bk-gateway" name="gateway" value="">
        
        <div class="form-group">
          <label class="form-label" for="bk-quantity">Quantity</label>
          <input type="number" id="bk-quantity" name="quantity" class="form-control" value="1" min="1" max="20">
        </div>
        
        <div id="bk-event-fields" style="display:none;">
          <div class="form-group">
            <label class="form-label">Ticket Type</label>
            <select name="ticket_type" class="form-control" id="bk-ticket-type"
              data-standard-price="<?= (float)($meta['standardTicketPrice'] ?? 0) ?>"
              data-vip-price="<?= (float)($meta['vipTicketPrice'] ?? 0) ?>">
              <option value="Standard">Standard — MK <?= number_format($meta['standardTicketPrice'] ?? 0) ?></option>
              <?php if (($meta['vipTicketPrice'] ?? 0) > 0): ?>
              <option value="VIP">VIP — MK <?= number_format($meta['vipTicketPrice'] ?? 0) ?></option>
              <?php endif; ?>
            </select>
            <div class="text-xs text-muted" style="margin-top:0.35rem;">
              Ticket scan format: <strong><?= e(strtoupper($ticketCodeFormat)) ?></strong>. The customer ticket will stay ready for print, share, or venue scanning.
            </div>
          </div>

          <?php if (($meta['has_seat_selection'] ?? 0) == 1): ?>
          <!-- Seat Selection Widget -->
          <div class="form-group" id="seat-selection-panel">
            <label class="form-label">Select Your Seat(s)</label>
            <div class="text-xs text-muted" style="margin-bottom:0.5rem;">
              ðŸŸ¦ Available &nbsp; ðŸŸ¥ Booked &nbsp; ðŸŸ© Selected
            </div>
            <?php
              $totalSeats = (int)($meta['venueCapacity'] ?? $meta['standardAvailable'] ?? 30);
              $totalSeats = max(10, min($totalSeats, 60));
              $cols = 8;
              $bookedSeats = [];
              // Load already-booked seat numbers from DB
              $bookedRows = dbQuery("SELECT seat_numbers FROM event_seat_bookings WHERE listing_id = ? AND status = 'confirmed'", [$id]);
              foreach ($bookedRows as $br) {
                  $nums = json_decode($br['seat_numbers'] ?? '[]', true) ?? [];
                  $bookedSeats = array_merge($bookedSeats, $nums);
              }
              $bookedSeats = array_unique($bookedSeats);
            ?>
            <div class="seat-map" id="event-seat-map"
                 style="grid-template-columns: repeat(<?= $cols ?>, 32px);">
              <?php for ($s = 1; $s <= $totalSeats; $s++): ?>
                <?php $isBooked = in_array($s, $bookedSeats); ?>
                <div class="seat-item<?= $isBooked ? ' booked' : '' ?>"
                     data-seat="<?= $s ?>"
                     title="Seat <?= $s ?><?= $isBooked ? ' (Booked)' : '' ?>"
                     onclick="<?= $isBooked ? '' : 'toggleSeat(this)' ?>">
                  <?= $s ?>
                </div>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="selected_seats" id="bk-selected-seats" value="">
            <div class="text-xs text-muted" id="seat-summary" style="margin-top:0.4rem;">No seats selected</div>
          </div>
          <?php endif; ?>
        </div>

        
        <div id="bk-accom-fields" style="display:none;">
          <div class="form-group">
            <label class="form-label" for="bk-checkin">Check-in Date</label>
            <input type="date" id="bk-checkin" name="check_in_date" class="form-control" min="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="bk-checkout">Check-out Date</label>
            <input type="date" id="bk-checkout" name="check_out_date" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
          </div>
        </div>
        
        <div id="bk-tour-fields" style="display:none;">
          <div class="form-group">
            <label class="form-label" for="bk-tour-date">Tour Date</label>
            <select id="bk-tour-date" name="tour_date" class="form-control">
              <?php foreach (($meta['datesAvailable'] ?? []) as $d): ?>
                <option value="<?= e($d) ?>"><?= e($d) ?></option>
              <?php endforeach; ?>
              <?php if (empty($meta['datesAvailable'] ?? [])): ?>
                <option value="">No scheduled dates — contact vendor</option>
              <?php endif; ?>
            </select>
          </div>
        </div>
        
        <div id="bk-transport-fields" style="display:none;">
          <div class="form-group">
            <label class="form-label">Seats</label>
            <input type="number" name="seats" class="form-control" value="1" min="1" max="<?= (int)($meta['availableSeats'] ?? 10) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Travel Date</label>
            <input type="date" name="travel_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">Coupon Code (optional)</label>
          <div style="display:flex;gap:0.5rem;">
            <input type="text" id="coupon-code" name="coupon_code" class="form-control" placeholder="e.g. WELCOME10" style="flex:1;">
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
      <div style="font-size:3rem;margin-bottom:1rem;">ðŸ’³</div>
      <h4 id="pm-title" style="margin-bottom:0.5rem;"></h4>
      <div style="font-size:2rem;font-weight:800;color:var(--clr-accent);margin-bottom:0.5rem;" id="pm-total">MK 0</div>
      <div class="text-sm text-muted">via <strong id="pm-gateway"></strong></div>
      <div class="alert alert-info" style="margin-top:1.5rem;text-align:left;"><div><strong>Simulation Mode:</strong> No real payment will be charged. Click "Pay Now" to simulate a successful transaction.</div></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('payment-modal')">Back</button>
      <button type="submit" form="booking-form" id="confirm-payment-btn" class="btn btn-primary">✓ Pay Now</button>
    </div>
  </div>
</div>

<div id="booking-success" style="display:none;position:fixed;bottom:2rem;right:2rem;background:var(--clr-surface);border:1px solid var(--clr-green);border-radius:var(--radius-lg);padding:1.5rem;max-width:360px;box-shadow:var(--shadow-lg);z-index:300;">
  <div style="font-size:1.5rem;margin-bottom:0.5rem;">ðŸŽ‰</div>
  <h4 style="color:var(--clr-green);margin-bottom:0.25rem;">Booking Confirmed!</h4>
  <div class="text-sm text-muted" style="margin-bottom:0.75rem;">ID: <strong id="success-booking-id" class="text-accent"></strong></div>
  <div class="text-xs text-muted" style="margin-bottom:0.35rem;">Ticket Format: <strong id="success-ticket-format" style="text-transform:uppercase;">QR</strong></div>
  <div id="success-ticket-preview" style="display:flex;gap:0.75rem;align-items:center;padding:0.85rem;border:1px solid rgba(15,23,42,0.08);border-radius:12px;background:rgba(15,23,42,0.03);margin-bottom:0.25rem;">
    <div id="success-ticket-thumb" style="width:72px;height:72px;display:grid;place-items:center;flex-shrink:0;background:#fff;border:1px solid rgba(15,23,42,0.08);border-radius:10px;overflow:hidden;"></div>
    <div style="min-width:0;flex:1;">
      <div class="text-xs text-muted" style="margin-bottom:0.25rem;">Digital Ticket</div>
      <div style="font-size:0.78rem;color:var(--clr-text-soft);margin-bottom:0.35rem;" id="success-ticket-mini"></div>
      <div class="qr-string" id="success-qr-code" style="font-size:0.75rem;word-break:break-all;"></div>
    </div>
  </div>
  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.75rem;">
    <button id="success-ticket-print" class="btn btn-secondary btn-sm" type="button" style="flex:1;">Print</button>
    <button id="success-ticket-share" class="btn btn-secondary btn-sm" type="button" style="flex:1;">Share</button>
    <button id="success-ticket-copy" class="btn btn-secondary btn-sm" type="button" style="flex:1;">Copy</button>
  </div>
  <div style="margin-top:0.75rem;font-size:0.85rem;">Total: <strong id="success-total" class="text-accent"></strong></div>
  <div style="display:flex;gap:0.5rem;margin-top:1rem;">
    <a href="<?= BASE_URL ?>bookings.php" class="btn btn-primary btn-sm" style="flex:1;">My Bookings</a>
    <button onclick="this.closest('#booking-success').style.display='none'" class="btn btn-secondary btn-sm" style="flex:1;">Close</button>
  </div>
</div>
<?php endif; ?>

<!-- Share Success Toast -->
<div id="share-toast" class="share-toast">✓ Link copied to clipboard!</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<!-- Wishlist AJAX Handler & Map Script -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
// Interactive gallery switcher with fade effect
function selectGalleryImg(imgElement) {
  const mainImg = document.getElementById('main-detail-img');
  if (mainImg && imgElement) {
    // Remove active class from all gallery items
    document.querySelectorAll('.gallery-strip img').forEach(el => el.classList.remove('active'));
    
    // Add active class to clicked item
    imgElement.classList.add('active');
    
    // Fade transition
    mainImg.style.opacity = 0;
    setTimeout(() => {
      mainImg.src = imgElement.src;
      mainImg.style.opacity = 1;
    }, 200);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  // Share link copy action
  const shareBtn = document.getElementById('share-listing-btn');
  const shareToast = document.getElementById('share-toast');
  if (shareBtn && shareToast) {
    shareBtn.addEventListener('click', () => {
      navigator.clipboard.writeText(window.location.href).then(() => {
        shareToast.classList.add('show');
        setTimeout(() => {
          shareToast.classList.remove('show');
        }, 2500);
      }).catch(err => {
        console.error('Could not copy link: ', err);
      });
    });
  }

  // Map Initialization
  const mapElement = document.getElementById('map');
  if (mapElement) {
    const defaultCoords = [-13.9626, 33.7741]; // Lilongwe
    const locString = "<?= addslashes($listing['location']) ?>".toLowerCase();
    
    let centerCoords = defaultCoords;
    if (locString.includes('mangochi')) {
      centerCoords = [-14.4781, 35.2635];
    } else if (locString.includes('blantyre') || locString.includes('kamuzu stadium')) {
      centerCoords = [-15.7861, 35.0058];
    } else if (locString.includes('zomba') || locString.includes('plateau')) {
      centerCoords = [-15.3875, 35.3181];
    } else if (locString.includes('mzuzu')) {
      centerCoords = [-11.4584, 34.0150];
    }

    const map = L.map('map').setView(centerCoords, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    L.marker(centerCoords).addTo(map)
      .bindPopup("<b><?= addslashes(e($listing['title'])) ?></b><br><?= addslashes(e($listing['location'])) ?>")
      .openPopup();
  }

  // Dynamic ticket price update on modal selection
  const ticketTypeSelect = document.getElementById('bk-ticket-type');
  if (ticketTypeSelect) {
    ticketTypeSelect.addEventListener('change', () => {
      const type = ticketTypeSelect.value;
      const stdPrice = <?= (float)($meta['standardTicketPrice'] ?? 0) ?>;
      const vipPrice = <?= (float)($meta['vipTicketPrice'] ?? 0) ?>;
      const basePrice = (type === 'VIP') ? vipPrice : stdPrice;
      
      document.getElementById('bk-base-price').value = basePrice;
      if (typeof updateBookingTotal === 'function') {
        updateBookingTotal();
      }
    });
  }

  // Wishlist AJAX
  const wishlistBtn = document.getElementById('wishlist-toggle-btn');
  if (wishlistBtn) {
    wishlistBtn.addEventListener('click', async () => {
      const listingId = wishlistBtn.dataset.id;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
      
      const formData = new FormData();
      formData.append('action', 'toggle_wishlist');
      formData.append('listing_id', listingId);
      if (csrfToken) formData.append('csrf_token', csrfToken);
      
      try {
        const res = await fetch('<?= BASE_URL ?>request_api.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          if (data.added) {
            wishlistBtn.classList.add('active');
            wishlistBtn.textContent = '❤️ Saved';
          } else {
            wishlistBtn.classList.remove('active');
            wishlistBtn.textContent = 'ðŸ¤ Save to Wishlist';
          }
        } else {
          alert(data.message || 'Error updating wishlist.');
        }
      } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
      }
    });
  }
});
</script>

<script>
// ── Seat Selection toggle ─────────────────────────────────────────────────────
function toggleSeat(el) {
  el.classList.toggle('selected');
  const selected = Array.from(document.querySelectorAll('.seat-item.selected')).map(s => s.dataset.seat);
  const hiddenInput = document.getElementById('bk-selected-seats');
  const summaryEl   = document.getElementById('seat-summary');
  if (hiddenInput) hiddenInput.value = selected.join(',');
  if (summaryEl)   summaryEl.textContent = selected.length > 0 ? 'Selected: Seat(s) ' + selected.join(', ') : 'No seats selected';
  const qtyInput = document.getElementById('bk-quantity');
  if (qtyInput && selected.length > 0) {
    qtyInput.value = selected.length;
    if (typeof updateBookingTotal === 'function') updateBookingTotal();
  }
}

// ── Promo Code Validation (via api/validate-promo.php) ────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const promoBtn = document.getElementById('apply-coupon');
  if (promoBtn) {
    promoBtn.addEventListener('click', async () => {
      const codeInput = document.getElementById('coupon-code');
      const msgEl     = document.getElementById('coupon-msg');
      const code      = codeInput?.value?.trim()?.toUpperCase();
      const listingId = document.getElementById('bk-listing-id')?.value || '';
      const subtotal  = parseFloat(document.getElementById('bk-total-price')?.value || 0);

      if (!code) {
        if (msgEl) { msgEl.textContent = 'Please enter a promo code.'; msgEl.style.color = ''; }
        return;
      }

      promoBtn.textContent = '…';
      promoBtn.disabled = true;

      try {
        const BASE = document.querySelector('meta[name="base-url"]')?.content || '';
        const fd = new FormData();
        fd.append('code', code);
        fd.append('event_id', listingId);
        fd.append('subtotal', subtotal);

        const res  = await fetch(BASE + 'api/validate-promo.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
          if (msgEl) { msgEl.textContent = '✓ ' + data.message; msgEl.style.color = 'var(--clr-green)'; }
          const discountInput = document.getElementById('bk-discount');
          if (discountInput) discountInput.value = data.discount_amount;
          if (typeof updateBookingTotal === 'function') updateBookingTotal();
        } else {
          if (msgEl) { msgEl.textContent = '✗ ' + data.message; msgEl.style.color = 'var(--clr-red)'; }
          const discountInput = document.getElementById('bk-discount');
          if (discountInput) discountInput.value = 0;
          if (typeof updateBookingTotal === 'function') updateBookingTotal();
        }
      } catch (err) {
        console.error(err);
        if (msgEl) { msgEl.textContent = 'Network error. Try again.'; }
      } finally {
        promoBtn.textContent = 'Apply';
        promoBtn.disabled = false;
      }
    });
  }
});
</script>

<?php if (($listing['listing_type'] ?? $listing['type'] ?? '') === 'event' && !empty($listing['id'])): ?>
<script>
// ── Non-blocking view tracking for AI event ranking ──────────────────────────
(function () {
  try {
    var url  = '<?= BASE_URL ?>api/track_event_view.php';
    var data = 'event_id=<?= urlencode($listing['id']) ?>&metric=view';
    if (navigator.sendBeacon) {
      var blob = new Blob([data], { type: 'application/x-www-form-urlencoded' });
      navigator.sendBeacon(url, blob);
    } else {
      fetch(url, { method: 'POST', body: data,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        keepalive: true
      }).catch(function () {});
    }
  } catch (e) {}
})();
</script>
<?php endif; ?>

<script>
function checkAccommodationAvailability(propertyId) {
  var checkin = document.getElementById('avail-checkin').value;
  var checkout = document.getElementById('avail-checkout').value;
  var statusMsg = document.getElementById('avail-status-msg');
  
  if (!checkin || !checkout) {
    statusMsg.textContent = '❌ Please select check-in and check-out dates.';
    statusMsg.style.color = '#ef4444';
    return;
  }
  
  statusMsg.textContent = '⏳ Checking room availability...';
  statusMsg.style.color = 'var(--clr-muted)';
  
  fetch('<?= BASE_URL ?>api/room-availability.php?property_id=' + propertyId + '&check_in=' + checkin + '&check_out=' + checkout)
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        var availableRooms = data.rooms.filter(r => r.available);
        if (availableRooms.length > 0) {
          var names = availableRooms.map(r => r.room_name + ' (MK ' + r.price_per_night.toLocaleString() + ')').join(', ');
          statusMsg.textContent = '✅ Available rooms: ' + names;
          statusMsg.style.color = '#10b981';
        } else {
          statusMsg.textContent = '❌ No rooms available for the selected dates.';
          statusMsg.style.color = '#ef4444';
        }
      } else {
        statusMsg.textContent = '❌ ' + data.message;
        statusMsg.style.color = '#ef4444';
      }
    })
    .catch(err => {
      statusMsg.textContent = '❌ Failed to check room availability.';
      statusMsg.style.color = '#ef4444';
    });
}

function submitReview(event) {
  event.preventDefault();
  var form = document.getElementById('submit-review-form');
  var status = document.getElementById('review-status');
  var formData = new FormData(form);
  
  status.textContent = 'Submitting...';
  status.style.color = 'var(--clr-muted)';
  
  fetch('<?= BASE_URL ?>api/submit-review.php', {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      status.textContent = '✅ Review submitted!';
      status.style.color = '#10b981';
      form.reset();
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      status.textContent = '❌ ' + data.message;
      status.style.color = '#ef4444';
    }
  })
  .catch(err => {
    status.textContent = '❌ Failed to submit review.';
    status.style.color = '#ef4444';
  });
}
</script>
</body>
</html>


