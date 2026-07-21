<?php
/**
 * Uthenga â€” Events Directory (Enhanced)
 * Features: Hero Slider, Ad Strip, Filters, Grid/Map View Toggle
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Explore Events';
$activeNav = 'events';

// Search & filter parameters
$search      = trim($_GET['q'] ?? '');
$location    = trim($_GET['location'] ?? '');
$category    = trim($_GET['category'] ?? '');
$datePreset  = trim($_GET['date_preset'] ?? '');
$minPrice    = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : null;
$maxPrice    = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : null;
$free        = isset($_GET['free'])     && $_GET['free']     === '1';
$paid        = isset($_GET['paid'])     && $_GET['paid']     === '1';
$upcoming    = isset($_GET['upcoming']) && $_GET['upcoming'] === '1';
$featured    = isset($_GET['featured']) && $_GET['featured'] === '1';
$viewMode    = ($_GET['view'] ?? 'grid') === 'map' ? 'map' : 'grid';

// Use AI-ranked events (falls back to latest when no analytics data exists)
$listings = marketplace_fetch_ranked_events($search, 0, ($search === ''));
$allLocations = dbQuery("
    SELECT DISTINCT location
    FROM listings
    WHERE listing_type = 'event' AND is_active = 1 AND location <> ''
    ORDER BY location ASC
");
$categoriesList = [
    'Sports Matches', 'Football Games', 'Basketball Games',
    'Music Festivals', 'Food Festivals', 'Cultural Festivals',
    'Conferences', 'Religious Gatherings', 'Entertainment Shows', 'Tourism Events'
];
$sliderEvents = marketplace_fetch_events('', 5, true);

if (
    empty($listings) &&
    $search === '' &&
    $category === '' &&
    $location === '' &&
    $datePreset === '' &&
    $minPrice === null &&
    $maxPrice === null &&
    !$free &&
    !$paid &&
    !$upcoming &&
    !$featured
) {
    $listings = [
        [
            'id' => 'evt-mock-1',
            'listing_type' => 'event',
            'type' => 'event',
            'title' => 'Lake of Stars Festival 2026',
            'description' => 'A premier lakeside celebration of music, arts, food and Malawi culture.',
            'location' => 'Mangochi Beach Resort, Lake Malawi',
            'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=900&fit=crop&q=80',
            'gallery' => null,
            'vendor_name' => 'Lake Malawi Festivals Ltd',
            'rating' => 4.9,
            'featured' => 1,
            'is_active' => 1,
            'meta' => json_encode(['category' => 'Music Festivals', 'date' => '2026-09-25', 'time' => '12:00 PM - 11:30 PM', 'standardTicketPrice' => 45000, 'vipTicketPrice' => 120000, 'standardAvailable' => 680, 'vipAvailable' => 150]),
            'price_amount' => 45000,
            'price_label' => 'From MK 45,000',
            'type_label' => 'Event',
            'badge_class' => 'badge-event',
            'detail_url' => 'event-details.php?type=event&id=evt-mock-1',
        ],
        [
            'id' => 'evt-mock-2',
            'listing_type' => 'event',
            'type' => 'event',
            'title' => 'Malawi Tech Innovation Summit',
            'description' => 'Digital leaders, founders and policy makers coming together for tech talks and demos.',
            'location' => 'BICC, Lilongwe',
            'image' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=900&fit=crop&q=80',
            'gallery' => null,
            'vendor_name' => 'GIANTPLUS',
            'rating' => 4.7,
            'featured' => 1,
            'is_active' => 1,
            'meta' => json_encode(['category' => 'Conferences', 'date' => '2026-10-15', 'time' => '08:30 AM - 05:00 PM', 'standardTicketPrice' => 25000, 'vipTicketPrice' => 65000, 'standardAvailable' => 350, 'vipAvailable' => 80]),
            'price_amount' => 25000,
            'price_label' => 'From MK 25,000',
            'type_label' => 'Event',
            'badge_class' => 'badge-event',
            'detail_url' => 'event-details.php?type=event&id=evt-mock-2',
        ],
        [
            'id' => 'evt-mock-3',
            'listing_type' => 'event',
            'type' => 'event',
            'title' => 'Blantyre Football Derby',
            'description' => 'The biggest football rivalry in Malawi with a charged stadium atmosphere.',
            'location' => 'Kamuzu Stadium, Blantyre',
            'image' => 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=900&fit=crop&q=80',
            'gallery' => null,
            'vendor_name' => 'Lake Malawi Festivals Ltd',
            'rating' => 4.8,
            'featured' => 1,
            'is_active' => 1,
            'meta' => json_encode(['category' => 'Football Games', 'date' => '2026-07-19', 'time' => '02:30 PM - 05:00 PM', 'standardTicketPrice' => 8000, 'vipTicketPrice' => 25000, 'standardAvailable' => 1500, 'vipAvailable' => 200]),
            'price_amount' => 8000,
            'price_label' => 'From MK 8,000',
            'type_label' => 'Event',
            'badge_class' => 'badge-event',
            'detail_url' => 'event-details.php?type=event&id=evt-mock-3',
        ],
    ];
}

if (empty($sliderEvents)) {
    $sliderEvents = array_slice($listings, 0, 3);
}

if (empty($allLocations)) {
    $allLocations = [
        ['location' => 'Mangochi Beach Resort, Lake Malawi'],
        ['location' => 'BICC, Lilongwe'],
        ['location' => 'Kamuzu Stadium, Blantyre'],
    ];
}

// Active advertisements for the strip
$activeAds = getActiveAds('banner', 6);

// Placeholder ad data when no real ads exist
$placeholderAds = [
    ['title' => 'ðŸŽ‰ Featured Event â€” Blantyre Jazz Festival', 'link_url' => '#', 'image_url' => ''],
    ['title' => 'ðŸŸï¸ Nyasa Big Bullets vs Silver Strikers â€” Book Now!', 'link_url' => '#', 'image_url' => ''],
    ['title' => 'ðŸŽ¤ Malawi Music Awards 2026 â€” Limited VIP Seats', 'link_url' => '#', 'image_url' => ''],
];
$displayAds = !empty($activeAds) ? $activeAds : $placeholderAds;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Find concerts, festivals, cultural celebrations and sports events in Malawi. Book your event tickets on Uthenga.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <!-- Leaflet CSS for Map View -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <style>
    /* â”€â”€â”€ Hero Slider â”€â”€â”€ */
    .hero-slider-container {
      position: relative; width: 100%; height: 480px; overflow: hidden;
      border-radius: var(--radius-xl); margin-bottom: 0;
      background: #000; box-shadow: var(--shadow-lg);
    }
    .slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%;
      opacity: 0; visibility: hidden; transition: opacity 0.8s ease-in-out, transform 0.8s ease-in-out; transform: scale(1.03); }
    .slide.active { opacity: 1; visibility: visible; transform: scale(1); }
    .slide-img-wrap { width: 100%; height: 100%; position: relative; }
    .slide-img { width: 100%; height: 100%; object-fit: cover; }
    .slide-overlay { position: absolute; inset: 0; background: linear-gradient(to right, rgba(10,10,15,0.95) 0%, rgba(10,10,15,0.4) 60%, rgba(10,10,15,0.85) 100%); }
    .slide-content { position: absolute; left: 3rem; top: 50%; transform: translateY(-50%); max-width: 550px; z-index: 10;
      padding: 2.5rem; border-radius: var(--radius-lg); background: rgba(18,18,26,0.75);
      backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.1); box-shadow: var(--shadow-lg); }
    .slide-category { display: inline-block; padding: 0.35rem 0.8rem; background: var(--clr-accent); color: #000;
      font-weight: 700; text-transform: uppercase; font-size: 0.72rem; border-radius: var(--radius-sm);
      margin-bottom: 1rem; letter-spacing: 0.05em; }
    .slide-title { font-size: 2rem; font-weight: 800; margin-bottom: 0.75rem; line-height: 1.25; color: #fff; }
    .slide-meta { display: flex; gap: 1rem; font-size: 0.85rem; color: var(--clr-text-soft); margin-bottom: 1rem; flex-wrap: wrap; }
    .slide-desc { font-size: 0.9rem; color: var(--clr-text-soft); margin-bottom: 1.25rem; line-height: 1.5;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .slide-price { font-size: 1.3rem; font-weight: 800; color: var(--clr-accent); margin-bottom: 1.5rem; }
    .slide-btns { display: flex; gap: 1rem; }
    .slider-arrow { position: absolute; top: 50%; transform: translateY(-50%); width: 44px; height: 44px;
      border-radius: 50%; background: rgba(18,18,26,0.6); border: 1px solid rgba(255,255,255,0.1);
      color: #fff; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;
      cursor: pointer; z-index: 20; transition: var(--transition); }
    .slider-arrow svg { width: 1rem; height: 1rem; flex: none; }
    .slider-arrow:hover { background: var(--clr-accent); color: #000; }
    .slider-arrow.prev { left: 1.5rem; } .slider-arrow.next { right: 1.5rem; }
    .slider-dots { position: absolute; bottom: 1.5rem; right: 3rem; display: flex; gap: 0.5rem; z-index: 20; }
    .slider-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.3); cursor: pointer; transition: var(--transition); }
    .slider-dot.active { background: var(--clr-accent); width: 20px; border-radius: 8px; }

    /* â”€â”€â”€ Ad Strip â”€â”€â”€ */
    .ad-strip { background: var(--clr-surface); border-top: 2px solid var(--clr-accent); border-bottom: 1px solid var(--clr-border);
      padding: 0.6rem 0; overflow: hidden; position: relative; }
    .ad-strip-track { display: flex; gap: 0; white-space: nowrap; animation: adScroll 28s linear infinite; }
    .ad-strip-track:hover { animation-play-state: paused; }
    .ad-strip-item { display: inline-flex; align-items: center; gap: 0.6rem; padding: 0.25rem 2.5rem;
      font-size: 0.82rem; font-weight: 600; color: var(--clr-text); border-right: 1px solid var(--clr-border);
      text-decoration: none; transition: var(--transition); cursor: pointer; }
    .ad-strip-item:hover { color: var(--clr-accent); }
    .ad-strip-label { font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;
      color: #fff; background: var(--clr-accent); padding: 0.15rem 0.5rem; border-radius: 4px; }
    @keyframes adScroll { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

    /* â”€â”€â”€ Filters â”€â”€â”€ */
    .filters-wrapper { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-lg); padding: 1.75rem; margin-bottom: 2rem; }
    .filter-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 1.25rem; }
    .filter-checkboxes { display: flex; flex-wrap: wrap; gap: 1.5rem; padding-top: 0.5rem; border-top: 1px solid var(--clr-border); }
    .checkbox-label { display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.88rem; cursor: pointer; user-select: none; }
    .checkbox-label input { accent-color: var(--clr-accent); width: 16px; height: 16px; }
    .filter-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem; }

    /* â”€â”€â”€ View Toggle â”€â”€â”€ */
    .view-toggle-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .view-toggle-btns { display: flex; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); overflow: hidden; }
    .view-toggle-btn { padding: 0.5rem 1.1rem; font-size: 0.85rem; font-weight: 600; cursor: pointer;
      background: var(--clr-surface); color: var(--clr-text-soft); border: none; transition: var(--transition); display: flex; align-items: center; gap: 0.4rem; }
    .view-toggle-btn.active { background: var(--clr-accent); color: #000; }

    /* â”€â”€â”€ Event Cards â”€â”€â”€ */
    .card-category-badge { display: inline-block; padding: 0.25rem 0.5rem; font-size: 0.72rem; font-weight: 700;
      color: #000; background: var(--clr-accent); border-radius: 4px; text-transform: uppercase; margin-bottom: 0.5rem; }
    .card-tickets-left { font-size: 0.78rem; font-weight: 600; color: var(--clr-green); margin-top: 0.5rem; }
    .card-tickets-left.low { color: var(--clr-red); }
    .card-short-desc { font-size: 0.8rem; color: var(--clr-text-muted); margin-top: 0.4rem;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.45; }

    /* â”€â”€â”€ Map View â”€â”€â”€ */
    .map-view-container { display: none; border-radius: var(--radius-lg); overflow: hidden;
      border: 1px solid var(--clr-border); box-shadow: var(--shadow-md); height: 580px; }
    .map-view-container.visible { display: block; }
    #events-map { width: 100%; height: 100%; }
    .leaflet-popup-content-wrapper { background: var(--clr-surface); border: 1px solid var(--clr-border);
      border-radius: var(--radius-md) !important; box-shadow: var(--shadow-md) !important; color: var(--clr-text); }
    .leaflet-popup-tip { background: var(--clr-surface) !important; }
    .map-popup-img { width: 100%; height: 100px; object-fit: cover; border-radius: 8px 8px 0 0; margin-bottom: 0.6rem; }
    .map-popup-title { font-weight: 700; font-size: 0.92rem; color: var(--clr-text); margin-bottom: 0.3rem; }
    .map-popup-venue { font-size: 0.78rem; color: var(--clr-text-muted); margin-bottom: 0.3rem; }
    .map-popup-price { font-size: 0.85rem; font-weight: 700; color: var(--clr-accent); margin-bottom: 0.6rem; }
    .map-popup-btn { display: block; text-align: center; padding: 0.4rem 0.8rem; background: var(--clr-accent);
      color: #000; font-weight: 700; font-size: 0.8rem; border-radius: var(--radius-sm); text-decoration: none; }

    /* â”€â”€â”€ Responsive â”€â”€â”€ */
    @media (max-width: 992px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 768px) {
      .hero-slider-container { height: 380px; }
      .slide-content { left: 1rem; right: 1rem; padding: 1.25rem; max-width: 100%; top: auto; bottom: 1rem; transform: none; }
      .slide-title { font-size: 1.4rem; }
      .slider-arrow { display: none; }
      .slider-dots { right: 50%; transform: translateX(50%); bottom: 0.5rem; }
      .filter-grid { grid-template-columns: 1fr; }
      .filters-wrapper { padding: 1.25rem; }
      .filter-checkboxes { gap: 0.9rem; }
      .filter-actions { flex-direction: column; }
      .filter-actions .btn { width: 100%; justify-content: center; }
      .view-toggle-bar { flex-direction: column; align-items: stretch; }
      .view-toggle-btns { width: 100%; }
      .view-toggle-btn { flex: 1 1 50%; justify-content: center; }
      .map-view-container { height: 360px; }
      .modal-overlay { padding: 0.85rem; align-items: flex-end; }
      .modal { width: 100%; max-width: none; max-height: calc(100vh - 1.7rem); border-radius: 1rem 1rem 0.75rem 0.75rem; }
      .modal-header, .modal-body, .modal-footer { padding-left: 1rem; padding-right: 1rem; }
      .modal-body .grid.grid-cols-2 { grid-template-columns: 1fr; }
      #booking-success { left: 0.85rem; right: 0.85rem; bottom: 0.85rem; max-width: none; }
    }
    @media (max-width: 480px) {
      .hero-slider-container { height: 320px; }
      .slide-meta { gap: 0.5rem; }
      .slide-btns { flex-direction: column; gap: 0.65rem; }
      .slide-btns .btn { width: 100%; }
      .view-toggle-btns { display: grid; grid-template-columns: 1fr 1fr; }
      .filter-grid .form-control { min-height: 44px; }
      .filters-wrapper { padding: 1rem; }
      .map-view-container { height: 300px; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<!-- â”€â”€â”€ Hero Slider â”€â”€â”€ -->
<?php if (!empty($sliderEvents)): ?>
<section style="padding-top: 1.5rem;">
  <div class="container">
    <div class="hero-slider-container">
      <?php foreach ($sliderEvents as $index => $se):
        $sm       = json_decode($se['meta'], true);
        $seActive = ($index === 0) ? 'active' : '';
        $ctas     = ['Get Your Ticket Today', 'Limited Tickets Available', 'Book Your Seat Now'];
        $ctaText  = $ctas[$index % count($ctas)];
      ?>
      <div class="slide <?= $seActive ?>" data-index="<?= $index ?>">
        <div class="slide-img-wrap">
          <img src="<?= e($se['image']) ?>" alt="<?= e($se['title']) ?>" class="slide-img" loading="eager">
          <div class="slide-overlay"></div>
        </div>
        <div class="slide-content">
          <span class="slide-category"><?= e($sm['category'] ?? 'Event') ?></span>
          <h2 class="slide-title"><?= e($se['title']) ?></h2>
          <div class="slide-meta">
            <span>ðŸ“… <?= e($sm['date'] ?? 'TBC') ?></span>
            <span>ðŸ“ <?= e($se['location']) ?></span>
            <?php if (!empty($sm['time'])): ?><span>â° <?= e($sm['time']) ?></span><?php endif; ?>
          </div>
          <p class="slide-desc"><?= e($se['description']) ?></p>
          <div class="slide-price"><?= getEventPrice($se) ?></div>
          <div class="slide-btns">
            <?php if (isLoggedIn()): ?>
              <button class="btn btn-primary" onclick="openBookingModal('<?= e($se['id']) ?>','event','<?= addslashes(e($se['title'])) ?>',<?= (float)($sm['standardTicketPrice'] ?? 0) ?>,<?= (float)($sm['vipTicketPrice'] ?? 0) ?>); if (window.trackEventMetric) { window.trackEventMetric('<?= e($se['id']) ?>', 'click'); }"><?= uthenga_public_icon_svg('ticket') ?> <?= $ctaText ?></button>
            <?php else: ?>
              <a href="<?= BASE_URL ?>login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-primary"><?= uthenga_public_icon_svg('ticket') ?> Sign In to Book</a>
            <?php endif; ?>
            <a href="event-details.php?id=<?= e($se['id']) ?>" class="btn btn-secondary" data-track-event-click="<?= e($se['id']) ?>">View Details</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <button class="slider-arrow prev" aria-label="Previous slide"><?= uthenga_public_icon_svg('chevron-left') ?></button>
      <button class="slider-arrow next" aria-label="Next slide"><?= uthenga_public_icon_svg('chevron-right') ?></button>
      <div class="slider-dots">
        <?php foreach ($sliderEvents as $index => $se): ?>
          <span class="slider-dot <?= ($index === 0) ? 'active' : '' ?>" data-index="<?= $index ?>"></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- â”€â”€â”€ Advertisement Strip â”€â”€â”€ -->
<div class="ad-strip" role="complementary" aria-label="Sponsored events">
  <div class="container" style="padding: 0;">
    <div class="ad-strip-track" id="ad-strip-track">
      <?php foreach ($displayAds as $ad): ?>
        <a class="ad-strip-item" href="<?= e($ad['link_url'] ?? '#') ?>">
          <span class="ad-strip-label">Sponsored</span>
          <?= e($ad['title']) ?>
        </a>
      <?php endforeach; ?>
      <?php /* Duplicate for seamless loop */ foreach ($displayAds as $ad): ?>
        <a class="ad-strip-item" href="<?= e($ad['link_url'] ?? '#') ?>">
          <span class="ad-strip-label">Sponsored</span>
          <?= e($ad['title']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- â”€â”€â”€ Main Content â”€â”€â”€ -->
<div class="container" style="padding-top: 2rem; padding-bottom: 4rem;">

  <!-- Filters -->
  <div class="filters-wrapper">
    <form method="GET" action="events.php" class="filter-form" id="events-filter-form">
      <input type="hidden" name="view" value="<?= e($viewMode) ?>">
      <div class="filter-grid">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Search Keyword</label>
          <input type="text" name="q" class="form-control" placeholder="Search title, venue, organizer..." value="<?= e($search) ?>" id="filter-search">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Category</label>
          <select name="category" class="form-control" id="filter-category">
            <option value="">All Categories</option>
            <?php foreach ($categoriesList as $cat): ?>
              <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Location</label>
          <select name="location" class="form-control" id="filter-location">
            <option value="">All Locations</option>
            <?php foreach ($allLocations as $loc): ?>
              <option value="<?= e($loc['location']) ?>" <?= $location === $loc['location'] ? 'selected' : '' ?>><?= e($loc['location']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Date</label>
          <select name="date_preset" class="form-control" id="filter-date">
            <option value="">Any Date</option>
            <option value="today" <?= $datePreset === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="this_week" <?= $datePreset === 'this_week' ? 'selected' : '' ?>>This Week</option>
            <option value="this_month" <?= $datePreset === 'this_month' ? 'selected' : '' ?>>This Month</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Min Price (MK)</label>
          <input type="number" name="min_price" class="form-control" placeholder="Min" value="<?= $minPrice !== null ? e($minPrice) : '' ?>" min="0" id="filter-min-price">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">Max Price (MK)</label>
          <input type="number" name="max_price" class="form-control" placeholder="Max" value="<?= $maxPrice !== null ? e($maxPrice) : '' ?>" min="0" id="filter-max-price">
        </div>
      </div>
      <div class="filter-checkboxes">
        <label class="checkbox-label"><input type="checkbox" name="free" value="1" <?= $free ? 'checked' : '' ?>> <span>Free Events Only</span></label>
        <label class="checkbox-label"><input type="checkbox" name="paid" value="1" <?= $paid ? 'checked' : '' ?>> <span>Paid Events Only</span></label>
        <label class="checkbox-label"><input type="checkbox" name="upcoming" value="1" <?= $upcoming ? 'checked' : '' ?>> <span>Upcoming Only</span></label>
        <label class="checkbox-label"><input type="checkbox" name="featured" value="1" <?= $featured ? 'checked' : '' ?>> <span>Featured Only</span></label>
      </div>
      <div class="filter-actions">
        <a href="events.php" class="btn btn-secondary" id="events-reset-btn">Reset Filters</a>
        <button type="submit" class="btn btn-primary" id="events-apply-btn">Apply Filters</button>
      </div>
    </form>
  </div>

  <!-- View Toggle Bar -->
  <div class="view-toggle-bar">
    <div>
      <strong style="font-size: 0.95rem;"><?= count($listings) ?> event<?= count($listings) !== 1 ? 's' : '' ?> found</strong>
      <?php if ($search || $category || $location || $datePreset): ?>
        <span class="text-muted" style="font-size: 0.82rem; margin-left: 0.5rem;">for your filters</span>
      <?php endif; ?>
    </div>
    <div class="view-toggle-btns" role="group" aria-label="View mode">
      <button class="view-toggle-btn <?= $viewMode === 'grid' ? 'active' : '' ?>" id="btn-grid-view" onclick="switchView('grid')" aria-label="Grid view">
        âŠž Grid
      </button>
      <button class="view-toggle-btn <?= $viewMode === 'map' ? 'active' : '' ?>" id="btn-map-view" onclick="switchView('map')" aria-label="Map view">
        ðŸ—ºï¸ Map
      </button>
    </div>
  </div>

  <!-- Grid View -->
  <div id="events-grid-view" style="display: <?= $viewMode === 'grid' ? 'block' : 'none' ?>;">
    <?php if (empty($listings)): ?>
      <div style="text-align: center; padding: 4rem 0;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">ðŸ”</div>
        <h3>No events found</h3>
        <p class="text-muted">Try adjusting your search criteria or clear the filters.</p>
        <a href="events.php" class="btn btn-secondary" style="margin-top: 1rem;" id="events-no-results-reset">Reset Filters</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-4 gap-3">
        <?php foreach ($listings as $listing):
          $meta      = json_decode($listing['meta'], true);
          $vipAvail  = (int) ($meta['vipAvailable'] ?? 0);
          $stdAvail  = (int) ($meta['standardAvailable'] ?? 0);
          $totalAvail = $vipAvail + $stdAvail;
          $isLowStock = $totalAvail > 0 && $totalAvail <= 50;
        ?>
        <div class="listing-card-wrap">
          <div class="card" id="listing-<?= e($listing['id']) ?>">
            <div class="card-img-wrap">
              <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
              <span class="card-badge badge-event">Event</span>
              <?php if (!empty($listing['is_trending'])): ?><span class="card-badge badge-trending" style="left:auto;right:0.75rem;">ðŸ”¥ Trending</span><?php endif; ?>
              <?php if ($listing['featured'] && empty($listing['is_trending'])): ?><span class="card-badge badge-featured" style="left:auto;right:0.75rem;">â­ Featured</span><?php endif; ?>
            </div>
            <div class="card-body">
              <div class="card-category-badge"><?= e($meta['category'] ?? 'Event') ?></div>
              <div class="card-title"><?= e($listing['title']) ?></div>
              <div class="card-loc">ðŸ“ <?= e($listing['location']) ?></div>
              <div style="font-size: 0.8rem; color: var(--clr-accent); margin-bottom: 0.4rem;">
                ðŸ“… <?= e($meta['date'] ?? 'TBC') ?> Â· â° <?= e($meta['time'] ?? 'TBC') ?>
              </div>
              <div class="flex items-center gap-1" style="margin-bottom: 0.4rem;">
                <span class="stars"><?= renderStars((float)$listing['rating']) ?></span>
                <span class="text-xs text-muted"><?= e($listing['rating']) ?></span>
              </div>
              <?php if (!empty($listing['description'])): ?>
                <div class="card-short-desc"><?= e(mb_substr($listing['description'], 0, 120)) ?><?= mb_strlen($listing['description']) > 120 ? 'â€¦' : '' ?></div>
              <?php endif; ?>
              <div class="card-price"><?= getEventPrice($listing) ?></div>
              <?php if ($totalAvail > 0): ?>
                <div class="card-tickets-left <?= $isLowStock ? 'low' : '' ?>">
                  ðŸŽŸï¸ <?= number_format($totalAvail) ?> tickets left<?= $isLowStock ? ' â€” Almost sold out!' : '' ?>
                </div>
              <?php else: ?>
                <div class="card-tickets-left low" style="font-weight: 700;">âŒ Sold Out</div>
              <?php endif; ?>
            </div>
            <div class="card-footer">
              <a href="event-details.php?id=<?= e($listing['id']) ?>" class="btn btn-sm btn-secondary" style="flex:1;" data-track-event-click="<?= e($listing['id']) ?>">Details</a>
              <?php if ($totalAvail > 0): ?>
                <?php if (isLoggedIn()): ?>
                  <button class="btn btn-sm btn-primary"
                    onclick="openBookingModal('<?= e($listing['id']) ?>','event','<?= addslashes(e($listing['title'])) ?>',<?= (float)($meta['standardTicketPrice'] ?? 0) ?>,<?= (float)($meta['vipTicketPrice'] ?? 0) ?>); if (window.trackEventMetric) { window.trackEventMetric('<?= e($listing['id']) ?>', 'click'); }"
                    style="flex:1;">Buy Ticket</button>
                <?php else: ?>
                  <a href="<?= BASE_URL ?>login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-sm btn-primary" style="flex:1;">Buy Ticket</a>
                <?php endif; ?>
              <?php else: ?>
                <button class="btn btn-sm btn-primary" disabled style="flex:1;">Sold Out</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Map View -->
  <div id="events-map-view" class="map-view-container <?= $viewMode === 'map' ? 'visible' : '' ?>">
    <div id="events-map"></div>
  </div>
</div>

<!-- Booking Modals (logged-in only) -->
<?php if (isLoggedIn()): ?>
<div class="modal-overlay" id="booking-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <h3 id="bk-modal-title">Book Ticket</h3>
      <button class="modal-close" onclick="closeModal('booking-modal')">âœ•</button>
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
          <label class="form-label" for="bk-quantity">Number of Tickets</label>
          <input type="number" id="bk-quantity" name="quantity" class="form-control" value="1" min="1" max="10">
        </div>
        <div id="bk-event-fields" style="display:none;">
          <div class="form-group">
            <label class="form-label">Ticket Type</label>
            <select name="ticket_type" class="form-control" id="bk-ticket-type">
              <option value="Standard">Standard</option>
              <option value="VIP">VIP</option>
            </select>
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
        <button type="button" id="proceed-to-payment" class="btn btn-primary">Continue to Payment â†’</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="payment-modal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal">
    <div class="modal-header">
      <h3>Confirm Payment</h3>
      <button class="modal-close" onclick="closeModal('payment-modal')">âœ•</button>
    </div>
    <div class="modal-body" style="text-align:center;">
      <div style="font-size:3rem;margin-bottom:1rem;">ðŸ’³</div>
      <h4 id="pm-title" style="margin-bottom:0.5rem;"></h4>
      <div style="font-size:2rem;font-weight:800;color:var(--clr-accent);margin-bottom:0.5rem;" id="pm-total">MK 0</div>
      <div class="text-sm text-muted">via <strong id="pm-gateway"></strong></div>
      <div class="alert alert-info" style="margin-top:1.5rem;text-align:left;"><div><strong>Simulation Mode:</strong> No real payment will be charged.</div></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeModal('payment-modal')">Back</button>
      <button type="submit" form="booking-form" id="confirm-payment-btn" class="btn btn-primary">âœ“ Pay Now</button>
    </div>
  </div>
</div>

<div id="booking-success" style="display:none;position:fixed;bottom:2rem;right:2rem;background:var(--clr-surface);border:1px solid var(--clr-green);border-radius:var(--radius-lg);padding:1.5rem;max-width:340px;box-shadow:var(--shadow-lg);z-index:300;">
  <div style="font-size:1.5rem;margin-bottom:0.5rem;">ðŸŽ‰</div>
  <h4 style="color:var(--clr-green);margin-bottom:0.25rem;">Booking Confirmed!</h4>
  <div class="text-sm text-muted" style="margin-bottom:0.75rem;">ID: <strong id="success-booking-id" class="text-accent"></strong></div>
  <div class="qr-block"><div class="text-xs text-muted" style="margin-bottom:0.5rem;">Digital Ticket</div><div class="qr-string" id="success-qr-code"></div></div>
  <div style="margin-top:0.75rem;font-size:0.85rem;">Total: <strong id="success-total" class="text-accent"></strong></div>
  <button onclick="this.parentElement.style.display='none'" class="btn btn-secondary btn-sm" style="margin-top:1rem;width:100%;">Close</button>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Map Data JSON -->
<script>
var EVENTS_MAP_DATA = <?php
$mapEvents = [];
foreach ($listings as $l) {
    $m = json_decode($l['meta'], true);
    $lat = (float) ($m['lat'] ?? 0);
    $lng = (float) ($m['lng'] ?? 0);
    // If no coordinates stored, generate plausible Malawi coordinates
    // Malawi is roughly -13 to -17 lat, 33 to 36 lng
    if ($lat == 0 && $lng == 0) {
        $lat = -13.5 + (crc32($l['id']) % 4000) / 1000;
        $lng =  33.8 + (crc32($l['title']) % 2200) / 1000;
    }
    $mapEvents[] = [
        'id'    => $l['id'],
        'title' => $l['title'],
        'image' => $l['image'],
        'venue' => $l['location'],
        'date'  => $m['date'] ?? 'TBC',
        'price' => getEventPrice($l),
        'url'   => 'event-details.php?id=' . urlencode($l['id']),
        'buyUrl'=> isLoggedIn() ? null : BASE_URL . 'login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']),
        'lat'   => round($lat, 6),
        'lng'   => round($lng, 6),
    ];
}
echo json_encode($mapEvents, JSON_HEX_TAG | JSON_HEX_AMP);
?>;
var BASE_URL = '<?= BASE_URL ?>';
var IS_LOGGED_IN = <?= isLoggedIn() ? 'true' : 'false' ?>;
</script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/TDI=" crossorigin=""></script>

<script>
(function () {
  // â”€â”€â”€ Slider â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  var slides      = document.querySelectorAll('.slide');
  var dots        = document.querySelectorAll('.slider-dot');
  var prevBtn     = document.querySelector('.slider-arrow.prev');
  var nextBtn     = document.querySelector('.slider-arrow.next');
  var curIdx      = 0;
  var slideTimer  = null;

  function showSlide(i) {
    slides.forEach(function(s) { s.classList.remove('active'); });
    dots.forEach(function(d) { d.classList.remove('active'); });
    curIdx = (i + slides.length) % slides.length;
    slides[curIdx].classList.add('active');
    if (dots[curIdx]) dots[curIdx].classList.add('active');
  }
  function startAuto() { stopAuto(); slideTimer = setInterval(function(){ showSlide(curIdx + 1); }, 5000); }
  function stopAuto() { if (slideTimer) { clearInterval(slideTimer); slideTimer = null; } }

  if (slides.length > 0) {
    startAuto();
    var cnt = document.querySelector('.hero-slider-container');
    if (cnt) {
      cnt.addEventListener('mouseenter', stopAuto);
      cnt.addEventListener('mouseleave', startAuto);
      var sx = 0;
      cnt.addEventListener('touchstart', function(e) { sx = e.changedTouches[0].screenX; }, {passive: true});
      cnt.addEventListener('touchend',   function(e) {
        var ex = e.changedTouches[0].screenX;
        if (sx - ex > 50) showSlide(curIdx + 1);
        else if (ex - sx > 50) showSlide(curIdx - 1);
      }, {passive: true});
    }
    if (prevBtn) prevBtn.addEventListener('click', function(){ showSlide(curIdx - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function(){ showSlide(curIdx + 1); });
    dots.forEach(function(d){ d.addEventListener('click', function(){ showSlide(parseInt(d.dataset.index)); }); });
  }

  // â”€â”€â”€ View Toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  var gridView  = document.getElementById('events-grid-view');
  var mapView   = document.getElementById('events-map-view');
  var btnGrid   = document.getElementById('btn-grid-view');
  var btnMap    = document.getElementById('btn-map-view');
  var filterFrm = document.getElementById('events-filter-form');
  var viewInput = filterFrm ? filterFrm.querySelector('input[name="view"]') : null;
  var leafletMap = null;

  function switchView(mode) {
    if (mode === 'map') {
      gridView.style.display = 'none';
      mapView.classList.add('visible');
      btnGrid.classList.remove('active');
      btnMap.classList.add('active');
      if (viewInput) viewInput.value = 'map';
      initMap();
    } else {
      mapView.classList.remove('visible');
      gridView.style.display = 'block';
      btnGrid.classList.add('active');
      btnMap.classList.remove('active');
      if (viewInput) viewInput.value = 'grid';
    }
  }
  window.switchView = switchView;

  function initMap() {
    if (leafletMap) return; // Already initialised
    leafletMap = L.map('events-map').setView([-13.96, 33.78], 7);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: 'Â© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 18
    }).addTo(leafletMap);

    var icon = L.divIcon({
      className: '',
      html: '<div style="background:var(--clr-accent,#f59e0b);color:#000;border-radius:50% 50% 50% 0;width:34px;height:34px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;transform:rotate(-45deg);box-shadow:0 2px 8px rgba(0,0,0,0.4);border:2px solid rgba(0,0,0,0.15);">ðŸŽ«</div>',
      iconSize: [34, 34],
      iconAnchor: [17, 34]
    });

    EVENTS_MAP_DATA.forEach(function(ev) {
      if (!ev.lat || !ev.lng) return;
      var imgTag = ev.image ? '<img src="' + ev.image + '" alt="" class="map-popup-img">' : '';
      var buyBtn = IS_LOGGED_IN
        ? '<button class="map-popup-btn" onclick="openBookingModal(\'' + ev.id + '\',\'event\',\'' + ev.title.replace(/'/g, "\\'") + '\',0,0)">ðŸŽ« Buy Ticket</button>'
        : '<a class="map-popup-btn" href="' + (ev.buyUrl || '#') + '">ðŸŽ« Sign In to Book</a>';
      var popupHtml = '<div style="min-width:200px;">' + imgTag +
        '<div class="map-popup-title">' + ev.title + '</div>' +
        '<div class="map-popup-venue">ðŸ“ ' + ev.venue + '</div>' +
        '<div class="map-popup-venue">ðŸ“… ' + ev.date + '</div>' +
        '<div class="map-popup-price">' + ev.price + '</div>' +
        '<div style="display:flex;gap:0.4rem;">' +
          '<a class="map-popup-btn" href="' + ev.url + '" style="flex:1;background:var(--clr-surface2,#1a1a28);color:var(--clr-text,#f0f0f5);border:1px solid rgba(255,255,255,0.1);">Details</a>' +
          '<div style="flex:1;">' + buyBtn + '</div>' +
        '</div></div>';
      L.marker([ev.lat, ev.lng], {icon: icon}).addTo(leafletMap).bindPopup(popupHtml, {maxWidth: 240});
    });

    setTimeout(function(){ leafletMap.invalidateSize(); }, 200);
  }

  // Init map if on map view
  if ('<?= $viewMode ?>' === 'map') initMap();
})();
</script>
</body>
</html>

