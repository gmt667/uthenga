<?php
/**
 * Uthenga — Destination Guide Detail Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/malawi_locations.php';

$slug = trim($_GET['slug'] ?? '');

// Try DB first
$guide = null;
if ($slug !== '') {
    $guide = dbQueryOne("SELECT * FROM destination_guides WHERE slug = ? AND is_active = 1", [$slug]);
}

// Fallback static content per slug
$staticGuides = [
    'blantyre-travel-guide' => [
        'city' => 'Blantyre', 'title' => 'Blantyre Travel Guide',
        'cover_image' => 'https://images.unsplash.com/photo-1612892483236-52d32a0e0ac1?w=1400&fit=crop&q=80',
        'summary' => 'Commercial capital, gateway to Mount Mulanje and Zomba Plateau.',
        'best_time' => 'May to October (dry season)',
        'travel_tips' => ['Always carry small change for matola rides','Drink bottled or purified water','Mosquito repellent is essential','Negotiate taxi fares before boarding'],
        'content' => '<p>Blantyre is the commercial heart of Malawi and a vibrant city with colonial history, bustling markets, and easy access to some of the country\'s most spectacular natural attractions.</p>
<h3>Getting Around</h3><p>Minibuses (matola) are the most common form of transport. Taxis are available but negotiate fares upfront. Ride-sharing through Mbanda is increasingly popular. The city is fairly compact — most central areas are walkable.</p>
<h3>Attractions</h3><ul><li><strong>St. Michael and All Angels Church</strong> — A beautiful Victorian church built in 1891 with local materials.</li><li><strong>Blantyre Market</strong> — Vibrant central market with crafts, fabrics, and food.</li><li><strong>Mandala House</strong> — One of the oldest buildings in Malawi, now a restaurant.</li><li><strong>Limbe</strong> — Neighboring town with tobacco auction floors and a botanical garden.</li></ul>
<h3>Day Trips</h3><p>Zomba Plateau (1 hour), Mount Mulanje (1.5 hours), Majete Wildlife Reserve (2 hours), and Cape Maclear on Lake Malawi (3 hours) are all excellent day trip or weekend destinations from Blantyre.</p>
<h3>Where to Eat</h3><p>The city offers everything from nsima and chambo at local joints to pizza and sushi at modern restaurants. The Shoprite area, Mandala, and Livingstone Ave have good options for most budgets.</p>',
    ],
    'lilongwe-city-guide' => [
        'city' => 'Lilongwe', 'title' => 'Lilongwe City Guide',
        'cover_image' => 'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=1400&fit=crop&q=80',
        'summary' => 'Political capital with vibrant Old Town and modern City Centre.',
        'best_time' => 'April to October',
        'travel_tips' => ['Old Town and City Centre are very spread out — use taxis or Mbanda','Area 47 and Area 10 have good supermarkets','Book accommodation in advance during major events'],
        'content' => '<p>Lilongwe is Malawi\'s political capital and has a fascinating dual character — the bustling, chaotic Old Town market area contrasts with the planned, spacious City Centre with wide boulevards and government buildings.</p>
<h3>Must Visit</h3><ul><li><strong>Lilongwe Wildlife Centre</strong> — Rehabilitates primates and other wildlife; excellent guided tours.</li><li><strong>Old Town Market</strong> — Vibrant and authentic, great for curios and fabrics.</li><li><strong>Area 10 Shopping Centre</strong> — Modern mall with restaurants, cinema, and shopping.</li><li><strong>Capital Hill</strong> — Impressive government complex in City Centre.</li></ul>
<h3>Nature Escapes</h3><p>Dzalanyama Forest Reserve is just 45 minutes from the city centre and offers excellent hiking and birdwatching. The Lilongwe River Nature Sanctuary in the city itself is a peaceful green retreat.</p>',
    ],
    'lake-malawi-mangochi-guide' => [
        'city' => 'Mangochi / Lake Malawi', 'title' => 'Lake Malawi & Mangochi Guide',
        'cover_image' => 'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=1400&fit=crop&q=80',
        'summary' => 'The Lake of Stars — beaches, water sports, and fresh chambo fish.',
        'best_time' => 'May to November',
        'travel_tips' => ['Bilharzia risk is low on sandy beaches but avoid reedy areas','Sunscreen is essential','Fresh chambo fish is a must-try','Book accommodation in advance in December-January'],
        'content' => '<p>Lake Malawi, called the "Lake of Stars", stretches along Malawi\'s eastern border and is one of Africa\'s most spectacular lakes. It\'s a UNESCO World Heritage Site known for its extraordinary diversity of cichlid fish.</p>
<h3>Activities</h3><ul><li><strong>Snorkeling & Diving</strong> — Crystal clear water with hundreds of colorful cichlid species.</li><li><strong>Kayaking & Sailing</strong> — Explore the lake\'s inlets and rocky shores.</li><li><strong>Island Day Trips</strong> — Many resorts offer boat trips to nearby islands.</li><li><strong>Beach Relaxation</strong> — White sandy beaches with fresh lake water.</li></ul>
<h3>Getting There</h3><p>Mangochi is about 3.5 hours from Blantyre and 5 hours from Lilongwe. Regular buses and minibuses operate from both cities. Senga Bay (near Salima) is closer to Lilongwe and equally beautiful.</p>',
    ],
];

if (!$guide && isset($staticGuides[$slug])) {
    $guide = $staticGuides[$slug];
    $guide['is_static'] = true;
}

if (!$guide) {
    // Show guide listing
    $guides = dbQuery("SELECT * FROM destination_guides WHERE is_active = 1 ORDER BY is_featured DESC, city ASC") ?: [];
    $pageTitle = 'Destination Guides';
} else {
    $pageTitle = $guide['title'] . ' | Destination Guide';
    if (!empty($guide['travel_tips']) && is_string($guide['travel_tips'])) {
        $guide['travel_tips'] = json_decode($guide['travel_tips'], true) ?: [];
    }
}

$activeNav = 'tourism';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    .guide-hero {
      position: relative; min-height: 340px; display: flex; align-items: flex-end;
      overflow: hidden; border-radius: 0; background: #1a1a2e;
    }
    .guide-hero-img {
      position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .55;
    }
    .guide-hero-content {
      position: relative; padding: 2.5rem var(--container-padding, 1.5rem);
      max-width: 900px; color: #fff;
    }
    .guide-hero-content h1 { font-size: clamp(1.8rem,4vw,3rem); margin: .5rem 0; }
    .guide-content-body { max-width: 860px; margin: 0 auto; }
    .guide-content-body h3 { margin-top: 1.75rem; margin-bottom: .5rem; }
    .guide-content-body ul { padding-left: 1.5rem; }
    .guide-content-body li { margin-bottom: .4rem; }
    .tips-list { list-style: none; padding: 0; }
    .tips-list li { display: flex; align-items: flex-start; gap: .75rem; padding: .6rem .9rem;
      background: var(--clr-surface); border-radius: .5rem; margin-bottom: .5rem; font-size: .9rem; }
    .tips-list li::before { content: '✅'; flex-shrink: 0; }
    .guide-sidebar { position: sticky; top: 80px; }
    .sidebar-card {
      background: var(--clr-surface); border: 1px solid var(--clr-border);
      border-radius: .75rem; padding: 1.25rem; margin-bottom: 1rem;
    }
    .sidebar-card h4 { margin-bottom: .75rem; font-size: .95rem; }
    .guide-index { list-style: none; padding: 0; }
    .guide-index li { padding: .35rem 0; border-bottom: 1px solid var(--clr-border); }
    .guide-index li:last-child { border: none; }
    .guide-index a { font-size: .875rem; color: var(--clr-primary); text-decoration: none; }
    .guide-index a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<?php if ($guide): ?>
<!-- ── Guide Hero ──────────────────────────────────────────────────── -->
<?php if (!empty($guide['cover_image'])): ?>
<div class="guide-hero">
  <img class="guide-hero-img" src="<?= e($guide['cover_image']) ?>" alt="<?= e($guide['title']) ?>">
  <div class="guide-hero-content container">
    <div class="section-label" style="color:rgba(255,255,255,.7)">Destination Guide</div>
    <h1><?= e($guide['title']) ?></h1>
    <?php if (!empty($guide['city'])): ?>
    <div style="display:flex;align-items:center;gap:.5rem;opacity:.85;">
      📍 <?= e($guide['city']) ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div style="display:grid;grid-template-columns:1fr 280px;gap:2.5rem;align-items:start;">

      <!-- Main content -->
      <div class="guide-content-body">
        <?php if (!empty($guide['summary'])): ?>
        <p style="font-size:1.1rem;color:var(--clr-muted);margin-bottom:1.5rem;"><?= e($guide['summary']) ?></p>
        <?php endif; ?>

        <?php if (!empty($guide['content'])): ?>
        <div class="prose"><?= $guide['content'] ?></div>
        <?php endif; ?>

        <?php if (!empty($guide['travel_tips'])): ?>
        <h3 style="margin-top:2rem;">💡 Essential Travel Tips</h3>
        <ul class="tips-list">
          <?php foreach ((array)$guide['travel_tips'] as $tip): ?>
          <li><?= e($tip) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:2.5rem;">
          <a href="<?= BASE_URL ?>tourism.php" class="btn btn-secondary">← Back to Tourism Hub</a>
          <a href="<?= BASE_URL ?>trip-planner.php?destination=<?= urlencode($guide['city'] ?? '') ?>" class="btn btn-primary">✨ Plan a Trip Here</a>
        </div>
      </div>

      <!-- Sidebar -->
      <aside class="guide-sidebar">
        <?php if (!empty($guide['best_time'])): ?>
        <div class="sidebar-card">
          <h4>🌤 Best Time to Visit</h4>
          <p style="font-size:.875rem;"><?= e($guide['best_time']) ?></p>
        </div>
        <?php endif; ?>
        <div class="sidebar-card">
          <h4>🔗 Quick Links</h4>
          <ul class="guide-index">
            <li><a href="<?= BASE_URL ?>hotels.php?q=<?= urlencode($guide['city'] ?? '') ?>">🏨 Hotels in <?= e($guide['city'] ?? 'this area') ?></a></li>
            <li><a href="<?= BASE_URL ?>tours.php?q=<?= urlencode($guide['city'] ?? '') ?>">🏞 Tours & Experiences</a></li>
            <li><a href="<?= BASE_URL ?>transport.php?destination=<?= urlencode($guide['city'] ?? '') ?>">🚌 Getting There</a></li>
            <li><a href="<?= BASE_URL ?>events.php?location=<?= urlencode($guide['city'] ?? '') ?>">🎉 Events</a></li>
            <li><a href="<?= BASE_URL ?>tourism.php">🗺 Interactive Map</a></li>
          </ul>
        </div>
        <div class="sidebar-card">
          <h4>📋 Plan Your Visit</h4>
          <a href="<?= BASE_URL ?>trip-planner.php?destination=<?= urlencode($guide['city'] ?? '') ?>" class="btn btn-primary" style="width:100%;margin-bottom:.5rem;">✨ AI Trip Planner</a>
          <a href="<?= BASE_URL ?>ai/chat.php" class="btn btn-secondary" style="width:100%;">🤖 Ask AI Assistant</a>
        </div>
      </aside>
    </div>
  </div>
</section>

<?php else: ?>
<!-- ── Guide Listing ───────────────────────────────────────────────── -->
<section class="section">
  <div class="container">
    <div class="section-label">Travel Resources</div>
    <h1>Destination Guides</h1>
    <p style="color:var(--clr-muted);margin-bottom:2rem;">In-depth guides to Malawi's top destinations with local tips, attractions, and practical advice.</p>

    <?php if (!$guide): ?>
    <div class="glass-panel" style="padding:1.25rem;margin-bottom:2rem;">
      <div class="page-header" style="margin-bottom:1rem;">
        <div>
          <h2 class="page-title" style="font-size:1.4rem;">Explore Malawi by District</h2>
          <p class="text-muted">All 28 districts are covered below, including major city hubs like Blantyre, Lilongwe, Zomba, Mzuzu, and Mangochi / Gosheni City.</p>
        </div>
      </div>
      <div class="grid grid-cols-4 gap-4">
        <?php foreach (uthenga_malawi_districts() as $district): ?>
          <a href="<?= BASE_URL ?>trip-planner.php?destination=<?= urlencode($district['city']) ?>" class="card" style="overflow:hidden;display:block;text-decoration:none;color:inherit;">
            <img src="<?= e($district['image']) ?>" alt="<?= e($district['district']) ?>" loading="lazy" style="width:100%;height:140px;object-fit:cover;">
            <div style="padding:1rem;">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--clr-accent);"><?= e($district['region']) ?></div>
              <h3 style="margin:.35rem 0 .25rem;font-size:1rem;"><?= e($district['district']) ?></h3>
              <div class="text-xs text-muted" style="margin-bottom:.5rem;"><?= e($district['city']) ?></div>
              <p style="font-size:.85rem;color:var(--clr-muted);margin:0;"><?= e($district['summary']) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="glass-panel" style="padding:1.25rem;margin-bottom:2rem;">
      <div class="page-header" style="margin-bottom:1rem;">
        <div>
          <h2 class="page-title" style="font-size:1.4rem;">Featured Cities</h2>
          <p class="text-muted">Mock city spotlight cards with images for quick inspiration and section previews.</p>
        </div>
      </div>
      <div class="grid grid-cols-5 gap-4">
        <?php foreach (uthenga_malawi_featured_cities() as $city): ?>
          <a class="card" href="<?= BASE_URL ?>trip-planner.php?destination=<?= urlencode($city['city']) ?>" style="overflow:hidden;display:block;text-decoration:none;color:inherit;">
            <img src="<?= e($city['image']) ?>" alt="<?= e($city['city']) ?>" loading="lazy" style="width:100%;height:150px;object-fit:cover;">
            <div style="padding:1rem;">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--clr-accent);"><?= e($city['district']) ?></div>
              <h3 style="margin:.35rem 0 .25rem;font-size:1rem;"><?= e($city['city']) ?></h3>
              <p style="font-size:.85rem;color:var(--clr-muted);margin:0;"><?= e($city['summary']) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php
    $allGuides = $guides ?? [];
    if (empty($allGuides)) {
        // Show static guide index
        $allGuides = [
            ['title'=>'Blantyre Travel Guide','city'=>'Blantyre','summary'=>'Commercial capital and gateway to southern adventures.','cover_image'=>'https://images.unsplash.com/photo-1612892483236-52d32a0e0ac1?w=600&fit=crop','slug'=>'blantyre-travel-guide'],
            ['title'=>'Lilongwe City Guide','city'=>'Lilongwe','summary'=>'Political capital with vibrant markets and wildlife.','cover_image'=>'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=600&fit=crop','slug'=>'lilongwe-city-guide'],
            ['title'=>'Lake Malawi & Mangochi','city'=>'Mangochi','summary'=>'The Lake of Stars — beaches, diving, and cichlid fish.','cover_image'=>'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=600&fit=crop','slug'=>'lake-malawi-mangochi-guide'],
        ];
    }
    ?>

    <div class="grid grid-cols-3 gap-4">
      <?php foreach ($allGuides as $g): ?>
      <div class="card">
        <?php if (!empty($g['cover_image'])): ?>
        <img src="<?= e($g['cover_image']) ?>" alt="<?= e($g['title']) ?>" style="width:100%;height:180px;object-fit:cover;border-radius:.5rem .5rem 0 0;" loading="lazy">
        <?php endif; ?>
        <div style="padding:1.25rem;">
          <div style="font-size:.75rem;font-weight:700;color:var(--clr-primary);text-transform:uppercase;"><?= e($g['city'] ?? '') ?></div>
          <h3 style="font-size:1rem;margin:.25rem 0 .5rem;"><?= e($g['title']) ?></h3>
          <p style="font-size:.85rem;color:var(--clr-muted);margin-bottom:1rem;"><?= e($g['summary'] ?? '') ?></p>
          <a href="?slug=<?= e($g['slug']) ?>" class="btn btn-sm btn-primary" style="width:100%">Read Guide →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:2rem;text-align:center;">
      <a href="<?= BASE_URL ?>tourism.php" class="btn btn-secondary">← Back to Tourism Hub</a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
