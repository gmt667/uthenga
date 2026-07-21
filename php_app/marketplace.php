<?php
/**
 * Uthenga - Local Business Marketplace
 * Directory of restaurants, cafes, tour guides, car hire, photographers,
 * curio shops, boat operators and more - with map view.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/malawi_locations.php';

$pageTitle = 'Local Business Marketplace';
$activeNav = 'marketplace';

// â”€â”€ Filter params â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$type   = trim(is_array($_GET['type']   ?? null) ? '' : (string)($_GET['type']   ?? ''));
$search = trim(is_array($_GET['search'] ?? null) ? '' : (string)($_GET['search'] ?? ''));
$city   = trim(is_array($_GET['city']   ?? null) ? '' : (string)($_GET['city']   ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$businessTypes = [
    ''             => 'All Categories',
    'restaurant'   => 'Restaurant',
    'cafe'         => 'Cafe & Bar',
    'tour_guide'   => 'Tour Guide',
    'car_hire'     => 'Car Hire',
    'photographer' => 'Photographer',
    'curio_shop'   => 'Curio Shop',
    'boat_operator'=> 'Boat Operator',
    'spa_wellness' => 'Spa & Wellness',
    'other'        => 'Other',
];

$businessTypeIcons = [
    ''             => 'map',
    'restaurant'   => 'restaurant',
    'cafe'         => 'restaurant',
    'tour_guide'   => 'tour',
    'car_hire'     => 'car',
    'photographer' => 'camera',
    'curio_shop'   => 'shop',
    'boat_operator'=> 'map',
    'spa_wellness' => 'spa',
    'other'        => 'info',
];

$featuredCities = uthenga_malawi_featured_cities();

$hasLocalBusinessListings = uthenga_table_exists('local_business_listings');
$marketplaceSchemaMissing = !$hasLocalBusinessListings;

// â”€â”€ Validate filters â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($type !== '' && !array_key_exists($type, $businessTypes)) {
    $type = '';
}

// â”€â”€ Resolve specific business details if view is set â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$viewId = trim($_GET['view'] ?? '');
$viewListing = null;

// Define default/mock listings
$mockListingsList = [
    [
        'id' => 'biz-mock-1',
        'business_name' => 'Sunbird Riverside Grill',
        'business_type' => 'restaurant',
        'description' => 'Lakefront dining with Malawian and continental dishes, sunset views, and live acoustic evenings.',
        'city' => 'Lilongwe',
        'address' => 'Area 3, Lilongwe',
        'phone' => '+265 999 111 222',
        'website' => 'https://example.com',
        'opening_hours' => 'Daily 11:00 - 22:00',
        'price_range' => 'Mid-range',
        'cover_image' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=900&fit=crop&q=80',
        'lat' => -13.9676,
        'lng' => 33.7874,
        'is_active' => 1,
        'is_featured' => 1,
        'avg_rating' => 4.8,
        'review_count' => 87,
        'vendor_name' => 'Sunbird Hospitality',
    ],
    [
        'id' => 'biz-mock-2',
        'business_name' => 'Zomba Plateau Guides',
        'business_type' => 'tour_guide',
        'description' => 'Guided hikes, birdwatching, and heritage walks on the beautiful Zomba Plateau.',
        'city' => 'Zomba',
        'address' => 'Zomba Plateau Visitor Centre',
        'phone' => '+265 888 222 333',
        'website' => '',
        'opening_hours' => 'Mon-Sat 08:00 - 17:00',
        'price_range' => 'Budget',
        'cover_image' => 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?w=900&fit=crop&q=80',
        'lat' => -15.3895,
        'lng' => 35.3283,
        'is_active' => 1,
        'is_featured' => 1,
        'avg_rating' => 4.9,
        'review_count' => 64,
        'vendor_name' => 'Zomba Plateau Guides',
    ],
    [
        'id' => 'biz-mock-3',
        'business_name' => 'Kaya Motion Car Hire',
        'business_type' => 'car_hire',
        'description' => 'Reliable airport pickups, self-drive cars, and long-distance travel across Malawi.',
        'city' => 'Blantyre',
        'address' => 'Limbe, Blantyre',
        'phone' => '+265 994 444 555',
        'website' => '',
        'opening_hours' => '24/7',
        'price_range' => 'Premium',
        'cover_image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=900&fit=crop&q=80',
        'lat' => -15.8044,
        'lng' => 35.0196,
        'is_active' => 1,
        'is_featured' => 0,
        'avg_rating' => 4.7,
        'review_count' => 39,
        'vendor_name' => 'Kaya Motion',
    ],
    [
        'id' => 'biz-mock-4',
        'business_name' => 'Gosheni Lakeside Kitchen',
        'business_type' => 'restaurant',
        'description' => 'Fresh chambo, nsima specials, and lakeshore dining with a relaxed Mangochi vibe.',
        'city' => 'Mangochi / Gosheni City',
        'address' => 'Cape Maclear Road, Mangochi',
        'phone' => '+265 997 555 909',
        'website' => '',
        'opening_hours' => 'Daily 08:00 - 21:00',
        'price_range' => 'Mid-range',
        'cover_image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=900&fit=crop&q=80',
        'lat' => -14.4849,
        'lng' => 35.2670,
        'is_active' => 1,
        'is_featured' => 1,
        'avg_rating' => 4.8,
        'review_count' => 58,
        'vendor_name' => 'Gosheni Culinary Group',
    ],
    [
        'id' => 'biz-mock-5',
        'business_name' => 'Mzuzu Heritage Walks',
        'business_type' => 'tour_guide',
        'description' => 'Northern city tours, coffee experiences, and easy day trips around Mzimba and the lakeshore.',
        'city' => 'Mzuzu',
        'address' => 'Mzuzu City Centre',
        'phone' => '+265 998 777 444',
        'website' => '',
        'opening_hours' => 'Mon-Sat 07:30 - 18:00',
        'price_range' => 'Budget',
        'cover_image' => 'https://images.unsplash.com/photo-1504893524553-b855a49c4e3d?w=900&fit=crop&q=80',
        'lat' => -11.4610,
        'lng' => 34.0150,
        'is_active' => 1,
        'is_featured' => 0,
        'avg_rating' => 4.6,
        'review_count' => 31,
        'vendor_name' => 'Mzuzu Heritage Walks',
    ],
];

// â”€â”€ Build query â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$conditions = ['lbl.is_active = 1'];
$params     = [];
$total      = 0;
$totalPages  = 1;
$listings    = [];
$mapListings = [];
$mapJson     = '[]';
$cities      = [];

if ($hasLocalBusinessListings) {
    if ($type !== '') {
        $conditions[] = 'lbl.business_type = ?';
        $params[]     = $type;
    }
    if ($search !== '') {
        $conditions[] = '(lbl.business_name LIKE ? OR lbl.description LIKE ? OR lbl.city LIKE ?)';
        $like = '%' . $search . '%';
        $params[]     = $like;
        $params[]     = $like;
        $params[]     = $like;
    }
    if ($city !== '') {
        $conditions[] = 'lbl.city LIKE ?';
        $params[]     = '%' . $city . '%';
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);

    try {
        // Count total
        $total = (int)(dbQueryOne(
            "SELECT COUNT(*) as cnt FROM local_business_listings lbl $where",
            $params
        )['cnt'] ?? 0);

        $totalPages = max(1, (int)ceil($total / $perPage));

        // Fetch listings
        $listings = dbQuery(
            "SELECT lbl.*, vp.city AS vendor_city, vp.phone AS vendor_phone,
                    u.name AS vendor_name
             FROM local_business_listings lbl
             LEFT JOIN vendor_profiles vp ON vp.vendor_id = lbl.vendor_id
             LEFT JOIN users u ON u.id = lbl.vendor_id
             $where
             ORDER BY lbl.is_featured DESC, lbl.avg_rating DESC, lbl.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        ) ?: [];

        // Fetch map points
        $mapListings = dbQuery(
            "SELECT id, business_name, business_type, city, lat, lng, avg_rating, cover_image
             FROM local_business_listings
             WHERE is_active = 1 AND lat IS NOT NULL AND lng IS NOT NULL
             LIMIT 200"
        ) ?: [];
        
        // Fetch unique cities
        $cities = dbQuery(
            "SELECT DISTINCT city FROM local_business_listings WHERE is_active = 1 AND city IS NOT NULL AND city != '' ORDER BY city"
        ) ?: [];
    } catch (Throwable $e) {
        $listings = [];
        $total = 0;
    }
}

// Fallback: If DB table is absent, or has no entries, filter the mock listings in memory.
// This ensures all page filters work perfectly even with empty/missing tables!
if (empty($listings)) {
    $filteredMocks = [];
    foreach ($mockListingsList as $biz) {
        if ($type !== '' && $biz['business_type'] !== $type) {
            continue;
        }
        if ($city !== '' && stripos($biz['city'], $city) === false) {
            continue;
        }
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $nameMatch = mb_strpos(mb_strtolower($biz['business_name']), $searchLower) !== false;
            $descMatch = mb_strpos(mb_strtolower($biz['description']), $searchLower) !== false;
            $cityMatch = mb_strpos(mb_strtolower($biz['city']), $searchLower) !== false;
            if (!$nameMatch && !$descMatch && !$cityMatch) {
                continue;
            }
        }
        $filteredMocks[] = $biz;
    }
    
    // Paginate mock listings
    $total = count($filteredMocks);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $listings = array_slice($filteredMocks, $offset, $perPage);
    
    // Set up mock map listings
    $mapListings = [];
    foreach ($mockListingsList as $biz) {
        if (!empty($biz['lat']) && !empty($biz['lng'])) {
            $mapListings[] = [
                'id' => $biz['id'],
                'business_name' => $biz['business_name'],
                'business_type' => $biz['business_type'],
                'city' => $biz['city'],
                'lat' => $biz['lat'],
                'lng' => $biz['lng'],
                'avg_rating' => $biz['avg_rating'],
                'cover_image' => $biz['cover_image']
            ];
        }
    }
}

$mapJson = json_encode($mapListings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

if (empty($cities)) {
    $cities = [
        ['city' => 'Lilongwe'],
        ['city' => 'Blantyre'],
        ['city' => 'Zomba'],
        ['city' => 'Mzuzu'],
        ['city' => 'Mangochi / Gosheni City'],
    ];
}

// â”€â”€ Resolve specific business details if view is set â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($viewId !== '') {
    if ($hasLocalBusinessListings) {
        try {
            $viewListing = dbQueryOne("
                SELECT lbl.*, vp.city AS vendor_city, vp.phone AS vendor_phone, u.name AS vendor_name
                FROM local_business_listings lbl
                LEFT JOIN vendor_profiles vp ON vp.vendor_id = lbl.vendor_id
                LEFT JOIN users u ON u.id = lbl.vendor_id
                WHERE lbl.id = ? AND lbl.is_active = 1 LIMIT 1", [$viewId]);
        } catch (Throwable $e) {
            $viewListing = null;
        }
    }
    if (!$viewListing) {
        foreach ($mockListingsList as $mock) {
            if ($mock['id'] === $viewId) {
                $viewListing = $mock;
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <meta name="description" content="Discover local businesses in Malawi â€” restaurants, cafes, tour guides, car hire, photographers, curio shops and boat operators on Uthenga.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    /* â”€â”€ Marketplace Styles â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */
    .mp-hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
               padding: 4rem 0 2.5rem; text-align: center; margin-bottom: 0; }
    .mp-hero h1 { font-size: clamp(1.8rem, 4vw, 2.8rem); font-weight: 800; margin-bottom: .75rem;
                  background: linear-gradient(135deg, #38bdf8, #a78bfa); -webkit-background-clip: text;
                  -webkit-text-fill-color: transparent; background-clip: text; }
    .mp-hero p { color: rgba(255,255,255,.65); max-width: 560px; margin: 0 auto 2rem; }

    .mp-search-bar { display: flex; gap: .5rem; max-width: 640px; margin: 0 auto; flex-wrap: wrap; }
    .mp-search-bar input { flex: 1; min-width: 200px; padding: .65rem 1rem; border-radius: var(--radius-md);
                           border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.08);
                           color: #fff; font-size: .9rem; outline: none; }
    .mp-search-bar input::placeholder { color: rgba(255,255,255,.4); }
    .mp-search-bar input:focus { border-color: var(--clr-cyan, #38bdf8); }
    .mp-search-bar button { padding: .65rem 1.4rem; background: var(--clr-cyan, #38bdf8);
                            border: none; border-radius: var(--radius-md); color: #000; font-weight: 700;
                            cursor: pointer; font-size: .9rem; transition: opacity .2s; }
    .mp-search-bar button:hover { opacity: .85; }

    /* Filter strip */
    .mp-filters { display: flex; gap: .5rem; flex-wrap: wrap; padding: 1.25rem 0; border-bottom: 1px solid var(--clr-border); }
    .filter-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .4rem .9rem; border-radius: 100px; font-size: .78rem; font-weight: 600;
                   border: 1px solid var(--clr-border); background: var(--clr-surface2);
                   color: var(--clr-text-soft); cursor: pointer; text-decoration: none; transition: all .2s; }
    .filter-chip:hover, .filter-chip.active { background: var(--clr-cyan, #38bdf8); border-color: var(--clr-cyan, #38bdf8);
                                              color: #000; }
    /* Map toggle */
    .view-toggle { display: flex; gap: .4rem; margin-left: auto; }
    .view-btn { padding: .4rem .8rem; border-radius: var(--radius-sm); font-size: .78rem; font-weight: 600;
                border: 1px solid var(--clr-border); background: var(--clr-surface2); color: var(--clr-text-soft);
                cursor: pointer; transition: all .2s; }
    .view-btn.active { background: var(--clr-primary, #06b6d4); border-color: var(--clr-primary); color: #fff; }

    /* Map */
    #mp-map { height: 420px; border-radius: var(--radius-lg); overflow: hidden; margin: 1.5rem 0;
              border: 1px solid var(--clr-border); display: none; }
    #mp-map.visible { display: block; }

    /* Business cards grid */
    .biz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 1.25rem; margin-top: 1.5rem; }
    .biz-card { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-lg);
                overflow: hidden; transition: transform .25s, box-shadow .25s; display: flex; flex-direction: column; }
    .biz-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,0,0,.25); }
    .biz-card-img { height: 160px; background: linear-gradient(135deg, #1e293b, #334155);
                    object-fit: cover; width: 100%; }
    .biz-card-img-placeholder { height: 160px; display: flex; align-items: center; justify-content: center;
                                  font-size: 3rem; background: linear-gradient(135deg, #1e293b, #0f172a); }
    .biz-card-body { padding: 1rem 1.1rem; flex: 1; display: flex; flex-direction: column; }
    .biz-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: .68rem; font-weight: 700; text-transform: uppercase;
                 letter-spacing: .08em; padding: .2rem .6rem; border-radius: 100px;
                 background: rgba(56, 189, 248, .15); color: var(--clr-cyan, #38bdf8); margin-bottom: .5rem; }
    .biz-badge.featured { background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; }
    .biz-name { font-size: 1rem; font-weight: 700; margin-bottom: .25rem; color: var(--clr-text); line-height: 1.3; }
    .biz-location { font-size: .78rem; color: var(--clr-text-soft); margin-bottom: .5rem; }
    .biz-description { font-size: .82rem; color: var(--clr-text-soft); margin-bottom: .75rem; flex: 1;
                        line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .biz-rating { display: flex; align-items: center; gap: .4rem; margin-bottom: .75rem; }
    .biz-stars { color: #f59e0b; font-size: .85rem; letter-spacing: .05em; }
    .biz-rating-count { font-size: .75rem; color: var(--clr-text-soft); }
    .biz-actions { display: flex; gap: .5rem; }
    .biz-actions a { flex: 1; text-align: center; padding: .45rem; font-size: .8rem; font-weight: 600;
                     border-radius: var(--radius-sm); text-decoration: none; transition: all .2s; }
    .biz-btn-primary { background: var(--clr-cyan, #38bdf8); color: #000; }
    .biz-btn-primary:hover { opacity: .85; }
    .biz-btn-ghost { border: 1px solid var(--clr-border); color: var(--clr-text-soft); }
    .biz-btn-ghost:hover { border-color: var(--clr-cyan, #38bdf8); color: var(--clr-cyan, #38bdf8); }

    /* Empty state */
    .mp-empty { text-align: center; padding: 4rem 1rem; color: var(--clr-text-soft); }
    .mp-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 3rem; height: 3rem; margin-bottom: 1rem; color: var(--clr-cyan, #38bdf8); }

    /* Stats bar */
    .mp-stats { display: flex; gap: 2rem; padding: 1rem 0; flex-wrap: wrap; }
    .mp-stat { text-align: center; }
    .mp-stat-num { font-size: 1.4rem; font-weight: 800; color: var(--clr-cyan, #38bdf8); }
    .mp-stat-label { font-size: .72rem; color: var(--clr-text-soft); text-transform: uppercase; letter-spacing: .06em; }

    .spotlight-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 1rem; margin: 1.25rem 0 0.5rem; }
    .spotlight-card { display: block; overflow: hidden; border-radius: var(--radius-lg); border: 1px solid var(--clr-border); background: var(--clr-surface); color: inherit; text-decoration: none; transition: transform .2s ease, box-shadow .2s ease; }
    .spotlight-card:hover { transform: translateY(-3px); box-shadow: 0 10px 26px rgba(0,0,0,.15); }
    .spotlight-card img { width: 100%; height: 128px; object-fit: cover; display: block; }
    .spotlight-card-body { padding: .9rem; }
    .spotlight-label { font-size: .68rem; text-transform: uppercase; font-weight: 700; color: var(--clr-accent); letter-spacing: .08em; margin-bottom: .25rem; }
    .spotlight-card strong { display: block; font-size: .98rem; margin-bottom: .3rem; }
    .spotlight-card p { font-size: .8rem; color: var(--clr-muted); margin: 0; line-height: 1.45; }

    @keyframes fadeUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    .biz-card { animation: fadeUp .4s ease both; }
    @media (max-width: 768px) {
      .mp-hero { padding: 2.5rem 0 1.5rem; }
      .mp-hero p { margin-bottom: 1.25rem; }
      .mp-search-bar { flex-direction: column; max-width: 100%; }
      .mp-search-bar input,
      .mp-search-bar button { width: 100%; min-width: 0; }
      .mp-filters { flex-direction: column; align-items: stretch; }
      .view-toggle { margin-left: 0; width: 100%; }
      .view-toggle-btns { width: 100%; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .view-btn { justify-content: center; }
      .biz-grid { grid-template-columns: 1fr; }
      .biz-actions { flex-direction: column; }
      .biz-actions a { width: 100%; }
      .mp-stats { justify-content: space-between; gap: 1rem; }
      #mp-map { height: 320px; }
      .spotlight-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 480px) {
      .mp-search-bar { gap: .75rem; }
      .spotlight-grid { grid-template-columns: 1fr; }
      .biz-card-img,
      .biz-card-img-placeholder { height: 150px; }
      .biz-card-body { padding: .9rem; }
      .mp-empty { padding: 3rem 1rem; }
      .mp-stats { flex-direction: column; align-items: stretch; }
      .mp-stat { text-align: left; }
    }
    <?php foreach (range(1, 12) as $i): ?>
    .biz-card:nth-child(<?= $i ?>) { animation-delay: <?= ($i - 1) * 0.05 ?>s; }
    <?php endforeach; ?>
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<!-- Hero -->
<section class="mp-hero">
  <div class="container">
    <span class="section-label">LOCAL MARKETPLACE</span>
    <h1>Discover Local Businesses</h1>
    <p>Find the best restaurants, tour guides, photographers, curio shops, and more across Malawi.</p>
    <form method="GET" action="<?= BASE_URL ?>marketplace.php" class="mp-search-bar">
      <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
      <input type="text" name="search" placeholder="Search businesses, places..." value="<?= e($search) ?>">
      <button type="submit">Search</button>
    </form>
  </div>
</section>

<section style="padding:1.5rem 0 0;">
  <div class="container">
    <div style="display:flex;align-items:end;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
      <div>
        <div class="section-label">City Spotlights</div>
        <h2 style="margin-top:.35rem;">Browse by Malawi city</h2>
      </div>
      <a href="<?= BASE_URL ?>destination-guide.php" class="btn btn-secondary btn-sm">See all districts</a>
    </div>
    <div class="spotlight-grid">
      <?php foreach ($featuredCities as $featCity): ?>
        <a href="<?= BASE_URL ?>marketplace.php?city=<?= urlencode($featCity['city']) ?>" class="spotlight-card">
          <img src="<?= e($featCity['image']) ?>" alt="<?= e($featCity['city']) ?>" loading="lazy">
          <div class="spotlight-card-body">
            <div class="spotlight-label"><?= e($featCity['district']) ?></div>
            <strong><?= e($featCity['city']) ?></strong>
            <p><?= e($featCity['summary']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<main class="container" style="padding-top: 1.5rem; padding-bottom: 5rem;">

  <!-- Stats -->
  <div class="mp-stats">
    <div class="mp-stat">
      <div class="mp-stat-num"><?= number_format($total) ?></div>
      <div class="mp-stat-label">Listings</div>
    </div>
    <div class="mp-stat">
      <div class="mp-stat-num"><?= count($cities) ?></div>
      <div class="mp-stat-label">Cities</div>
    </div>
    <div class="mp-stat">
      <div class="mp-stat-num"><?= count($businessTypes) - 1 ?></div>
      <div class="mp-stat-label">Categories</div>
    </div>
  </div>

  <?php if ($marketplaceSchemaMissing): ?>
    <div class="card" style="padding:1rem 1.25rem;margin-bottom:1.5rem;border:1px solid rgba(245,158,11,.35);background:rgba(245,158,11,.08);">
      The local marketplace tables are not installed yet. Run the base installer or migrations to enable marketplace listings.
    </div>
  <?php endif; ?>

  <!-- Filter chips + View toggle -->
  <div class="mp-filters">
    <?php foreach ($businessTypes as $val => $label):
      $params_chip = array_filter(['type' => $val, 'search' => $search, 'city' => $city]);
      $isActive = ($type === $val) || ($val === '' && $type === '');
    ?>
        <a href="<?= BASE_URL ?>marketplace.php?<?= http_build_query($params_chip) ?>"
         class="filter-chip <?= $isActive ? 'active' : '' ?>">
        <?= uthenga_public_icon_svg($businessTypeIcons[$val] ?? 'map') ?><span><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>

    <!-- City filter -->
    <?php if ($cities): ?>
    <div style="margin-left: .5rem;">
      <form method="GET" action="<?= BASE_URL ?>marketplace.php" style="display:inline;">
        <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
        <?php if ($search): ?><input type="hidden" name="search" value="<?= e($search) ?>"><?php endif; ?>
        <select name="city" onchange="this.form.submit()" class="filter-chip" style="cursor:pointer;">
          <option value="">All Cities</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?= e($c['city']) ?>" <?= $city === $c['city'] ? 'selected' : '' ?>>
              <?= e($c['city']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <?php endif; ?>

    <!-- View toggle -->
    <div class="view-toggle">
      <button class="view-btn active" id="grid-btn" onclick="setView('grid')">Grid</button>
      <button class="view-btn" id="map-btn" onclick="setView('map')">Map</button>
    </div>
  </div>

  <!-- Map view -->
  <div id="mp-map"></div>

  <!-- Listings grid -->
  <?php if (empty($listings)): ?>
    <div class="mp-empty">
      <div class="mp-empty-icon"><?= uthenga_public_icon_svg('shop') ?></div>
      <h3>No listings found</h3>
      <p>Try adjusting your filters or <a href="<?= BASE_URL ?>marketplace.php" style="color:var(--clr-cyan)">clear all</a>.</p>
      <a href="<?= BASE_URL ?>vendor/register.php" class="btn btn-cyan" style="margin-top:1rem;">
        + List Your Business
      </a>
    </div>
  <?php else: ?>
    <div class="biz-grid">
      <?php foreach ($listings as $biz):
        $bizType    = $biz['business_type'] ?? '';
        $typeLabel  = $businessTypes[$bizType] ?? 'Other';
        $typeIcon   = $businessTypeIcons[$bizType] ?? 'info';
        $rating     = (float)($biz['avg_rating'] ?? 0);
        $ratingCount = (int)($biz['review_count'] ?? 0);
        $stars = str_repeat('★', min(5, max(0, round($rating)))) . str_repeat('☆', 5 - min(5, max(0, round($rating))));
        $coverImg = $biz['cover_image'] ?? '';
        $phone  = $biz['phone']  ?? $biz['vendor_phone'] ?? '';
        $waLink = $phone ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone) : '';
      ?>
      <div class="biz-card">
        <?php if ($coverImg): ?>
          <img src="<?= e($coverImg) ?>" alt="<?= e($biz['business_name'] ?? 'Business') ?>" class="biz-card-img" loading="lazy">
        <?php else: ?>
          <div class="biz-card-img-placeholder"><?= substr($typeLabel, 0, 2) ?></div>
        <?php endif; ?>

        <div class="biz-card-body">
          <div>
            <?php if (!empty($biz['is_featured'])): ?>
              <span class="biz-badge featured"><?= uthenga_public_icon_svg('star') ?> Featured</span>
            <?php endif; ?>
            <span class="biz-badge"><?= uthenga_public_icon_svg($typeIcon) ?><?= e($typeLabel) ?></span>
          </div>
          <div class="biz-name"><?= e($biz['business_name'] ?? 'Unnamed Business') ?></div>
          <div class="biz-location"><?= uthenga_public_icon_svg('pin') ?> <?= e($biz['city'] ?? 'Malawi') ?></div>
          <?php if (!empty($biz['description'])): ?>
            <div class="biz-description"><?= e($biz['description']) ?></div>
          <?php endif; ?>

          <div class="biz-rating">
            <span class="biz-stars"><?= $stars ?></span>
            <span style="font-size:.85rem; font-weight:700;"><?= number_format($rating, 1) ?></span>
            <?php if ($ratingCount > 0): ?>
              <span class="biz-rating-count">(<?= $ratingCount ?> reviews)</span>
            <?php endif; ?>
          </div>

          <?php if ($phone || !empty($biz['website'])): ?>
          <div style="font-size:.75rem; color:var(--clr-text-soft); margin-bottom:.65rem;">
            <?php if ($phone): ?><?= uthenga_public_icon_svg('phone') ?> <?= e($phone) ?><?php endif; ?>
            <?php if (!empty($biz['website'])): ?> · <?= uthenga_public_icon_svg('globe') ?> <a href="<?= e($biz['website']) ?>" target="_blank" style="color:var(--clr-cyan);"><?= e(parse_url($biz['website'], PHP_URL_HOST) ?: $biz['website']) ?></a><?php endif; ?>
          </div>
          <?php endif; ?>

          <div class="biz-actions">
            <?php
              $vParams = array_filter(['type' => $type, 'search' => $search, 'city' => $city, 'view' => $biz['id'] ?? '']);
            ?>
            <a href="<?= BASE_URL ?>marketplace.php?<?= http_build_query($vParams) ?>" class="biz-btn-primary">View Details</a>
            <?php if ($waLink): ?>
              <a href="<?= $waLink ?>" target="_blank" rel="noopener" class="biz-btn-ghost">WhatsApp</a>
            <?php elseif (!empty($biz['website'])): ?>
              <a href="<?= e($biz['website']) ?>" target="_blank" rel="noopener" class="biz-btn-ghost">Visit Site</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex; justify-content:center; gap:.5rem; margin-top:2.5rem; flex-wrap:wrap;">
      <?php for ($p = 1; $p <= $totalPages; $p++):
        $pParams = array_filter(['type' => $type, 'search' => $search, 'city' => $city, 'page' => $p]);
      ?>
        <a href="<?= BASE_URL ?>marketplace.php?<?= http_build_query($pParams) ?>"
           style="padding:.45rem .85rem; border-radius:var(--radius-sm); border:1px solid var(--clr-border);
                  font-weight:600; font-size:.82rem; text-decoration:none;
                  <?= $p === $page ? 'background:var(--clr-cyan);color:#000;' : 'color:var(--clr-text-soft);' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- CTA to list your business -->
  <div style="margin-top:3rem; text-align:center; padding:2rem; background:var(--clr-surface2);
              border:1px solid var(--clr-border); border-radius:var(--radius-lg);">
    <h3 style="margin-bottom:.5rem;display:inline-flex;align-items:center;gap:.4rem;justify-content:center;"><?= uthenga_public_icon_svg('megaphone') ?> List Your Business on Uthenga</h3>
    <p class="text-muted" style="margin-bottom:1.25rem;">Reach thousands of tourists and locals. Register as a vendor and create your listing today.</p>
    <a href="<?= BASE_URL ?>vendor/register.php" class="btn btn-cyan" style="margin-right:.5rem;">Get Started - Free</a>
    <a href="<?= BASE_URL ?>about.php" class="btn btn-secondary">Learn More</a>
  </div>

  <!-- Business details modal if view param present -->
  <?php if ($viewListing): 
    $vlTypeLabel = $businessTypes[$viewListing['business_type'] ?? ''] ?? 'Other';
    $vlTypeIcon  = $businessTypeIcons[$viewListing['business_type'] ?? ''] ?? 'info';
    $vlRating = (float)($viewListing['avg_rating'] ?? 0);
    $vlRatingCount = (int)($viewListing['review_count'] ?? 0);
    $vlStars = str_repeat('★', min(5, max(0, round($vlRating)))) . str_repeat('☆', 5 - min(5, max(0, round($vlRating))));
    $vlPhone = $viewListing['phone'] ?? $viewListing['vendor_phone'] ?? '';
    $vlCleanUrl = BASE_URL . 'marketplace.php?' . http_build_query(array_filter(['type' => $type, 'search' => $search, 'city' => $city]));
    $vlWaLink = $vlPhone ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $vlPhone) : '';
  ?>
  <div class="modal-overlay" id="biz-detail-modal" style="position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1.5rem;" onclick="location.href='<?= $vlCleanUrl ?>'">
    <div class="modal" style="background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-lg);max-width:540px;width:100%;overflow:hidden;box-shadow:0 20px 50px rgba(0,0,0,0.5);animation:fadeUp 0.3s;" onclick="event.stopPropagation()">
      <?php if (!empty($viewListing['cover_image'])): ?>
        <img src="<?= e($viewListing['cover_image']) ?>" style="width:100%;height:220px;object-fit:cover;" alt="<?= e($viewListing['business_name'] ?? 'Business') ?>">
      <?php endif; ?>
      <div style="padding:2rem;">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:0.75rem;">
          <div>
            <span class="biz-badge" style="margin-bottom:0.35rem;"><?= uthenga_public_icon_svg($vlTypeIcon) ?><?= e($vlTypeLabel) ?></span>
            <h2 style="margin:0;font-size:1.4rem;color:#fff;"><?= e($viewListing['business_name'] ?? 'Unnamed Business') ?></h2>
          </div>
          <button onclick="location.href='<?= $vlCleanUrl ?>'" aria-label="Close details" style="background:none;border:none;color:var(--clr-text-soft);font-size:1.5rem;cursor:pointer;line-height:1;padding:0.25rem;display:inline-flex;align-items:center;justify-content:center;"><?= uthenga_public_icon_svg('x') ?></button>
        </div>
        <p style="font-size:0.88rem;color:var(--clr-text-soft);margin-bottom:1rem;line-height:1.5;"><?= e($viewListing['description'] ?? '') ?></p>
        
        <div style="display:grid;gap:0.75rem;margin-bottom:1.5rem;border-top:1px solid var(--clr-border);padding-top:1rem;font-size:0.85rem;">
          <div><?= uthenga_public_icon_svg('pin') ?> <strong>Location/Address:</strong> <?= e($viewListing['address'] ?? ($viewListing['city'] ?? 'Malawi')) ?></div>
          <?php if (!empty($viewListing['opening_hours'])): ?>
            <div><?= uthenga_public_icon_svg('calendar') ?> <strong>Opening Hours:</strong> <?= e($viewListing['opening_hours']) ?></div>
          <?php endif; ?>
          <?php if (!empty($viewListing['price_range'])): ?>
            <div><?= uthenga_public_icon_svg('wallet') ?> <strong>Price Range:</strong> <?= e($viewListing['price_range']) ?></div>
          <?php endif; ?>
          <div style="display:flex;align-items:center;gap:0.5rem;">
            <span style="color:#f59e0b;"><?= $vlStars ?></span>
            <strong><?= number_format($vlRating, 1) ?></strong> (<?= $vlRatingCount ?> reviews)
          </div>
        </div>

        <div style="display:flex;gap:0.75rem;">
          <?php if ($vlWaLink): ?>
            <a href="<?= $vlWaLink ?>" target="_blank" rel="noopener" class="btn btn-primary" style="flex:1;text-align:center;text-decoration:none;">WhatsApp</a>
          <?php endif; ?>
          <?php if (!empty($viewListing['website'])): ?>
            <a href="<?= e($viewListing['website']) ?>" target="_blank" rel="noopener" class="btn btn-secondary" style="flex:1;text-align:center;text-decoration:none;">Website</a>
          <?php endif; ?>
          <button onclick="location.href='<?= $vlCleanUrl ?>'" class="btn btn-secondary" style="flex:1;">Close</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// â”€â”€ Map / Grid View Toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let mapInitialized = false;
const mapData = <?= $mapJson ?>;

function setView(v) {
  const mapEl  = document.getElementById('mp-map');
  const gridEl = document.querySelector('.biz-grid');
  const gridBtn = document.getElementById('grid-btn');
  const mapBtn  = document.getElementById('map-btn');

  if (v === 'map') {
    mapEl?.classList.add('visible');
    if (gridEl) gridEl.style.display = 'none';
    gridBtn.classList.remove('active');
    mapBtn.classList.add('active');
    if (!mapInitialized) initMap();
  } else {
    mapEl?.classList.remove('visible');
    if (gridEl) gridEl.style.display = '';
    gridBtn.classList.add('active');
    mapBtn.classList.remove('active');
  }
}

function initMap() {
  mapInitialized = true;
  const map = L.map('mp-map').setView([-13.9669, 33.7873], 7); // Malawi centre
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Â© OpenStreetMap contributors', maxZoom: 18
  }).addTo(map);

  const categoryColors = {
    restaurant: '#ef4444', cafe: '#f59e0b', tour_guide: '#10b981',
    car_hire: '#3b82f6', photographer: '#a855f7', curio_shop: '#ec4899',
    boat_operator: '#06b6d4', spa_wellness: '#8b5cf6', other: '#64748b'
  };

  mapData.forEach(biz => {
    if (!biz.lat || !biz.lng) return;
    const color = categoryColors[biz.business_type] || '#64748b';
    const icon = L.divIcon({
      html: `<div style="background:${color};width:12px;height:12px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.4);"></div>`,
      className: '', iconSize: [12, 12], iconAnchor: [6, 6]
    });
    const marker = L.marker([biz.lat, biz.lng], { icon }).addTo(map);
    const stars = 'â˜…'.repeat(Math.round(biz.avg_rating || 0)) + 'â˜†'.repeat(5 - Math.round(biz.avg_rating || 0));
    marker.bindPopup(`
      <div style="min-width:160px;">
        ${biz.cover_image ? `<img src="${biz.cover_image}" style="width:100%;height:80px;object-fit:cover;border-radius:4px;margin-bottom:.5rem;">` : ''}
        <strong>${biz.business_name}</strong><br>
        <small style="color:#64748b;">${biz.city || ''}</small><br>
        <span style="color:#f59e0b;">${stars}</span><br>
        <a href="<?= BASE_URL ?>marketplace.php?view=${biz.id}" style="color:#38bdf8;font-size:.8rem;">View Details â†’</a>
      </div>
    `);
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
