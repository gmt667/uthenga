<?php
/**
 * Uthenga — Tourism & Travel Hub
 * Interactive map, weather, destination guides, and itinerary tools
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/malawi_locations.php';

$pageTitle = 'Tourism & Travel';
$activeNav = 'tourism';

// Fetch destination guides
$guides = uthenga_table_exists('destination_guides')
    ? (dbQuery("SELECT * FROM destination_guides WHERE is_active = 1 ORDER BY is_featured DESC, created_at DESC LIMIT 6") ?: [])
    : [];

// Fetch featured map points for initial display
$mapPoints = uthenga_table_exists('map_points')
    ? (dbQuery("SELECT * FROM map_points WHERE is_active = 1 ORDER BY is_featured DESC, name ASC") ?: [])
    : [];

// Malawi major cities for weather widget
$weatherCities = [
    ['name' => 'Blantyre',  'lat' => -15.7861, 'lon' => 35.0058],
    ['name' => 'Lilongwe',  'lat' => -13.9626, 'lon' => 33.7741],
    ['name' => 'Mzuzu',     'lat' => -11.4655, 'lon' => 33.9952],
    ['name' => 'Mangochi',  'lat' => -14.4778, 'lon' => 35.2653],
    ['name' => 'Zomba',     'lat' => -15.3833, 'lon' => 35.3167],
];

// Featured tours
$featuredTours = uthenga_table_exists('tour_packages')
    ? (dbQuery("SELECT * FROM tour_packages WHERE status = 'published' ORDER BY created_at DESC LIMIT 6") ?: [])
    : [];

$citySpotlights = uthenga_malawi_featured_cities();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Explore Malawi's top destinations, attractions, and travel guides. Plan your trip with interactive maps, weather info, and downloadable itineraries.">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <!-- Leaflet.js for interactive maps -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <style>
    /* ── Tourism Page Styles ─────────────────────────────────────────── */
    .tourism-hero {
      background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
      color: #fff;
      padding: 4rem 0 3rem;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .tourism-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url('https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=1600&fit=crop&q=60') center/cover;
      opacity: 0.2;
    }
    .tourism-hero > * { position: relative; }
    .tourism-hero h1 { font-size: clamp(2rem, 5vw, 3.5rem); margin-bottom: .75rem; }
    .tourism-hero p { font-size: 1.1rem; opacity: .85; max-width: 600px; margin: 0 auto 2rem; }

    /* Quick category buttons */
    .tourism-cats {
      display: flex; flex-wrap: wrap; gap: .75rem;
      justify-content: center; margin-top: 1.5rem;
    }
    .tourism-cat-btn {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .5rem 1.1rem; border-radius: 999px; font-size: .875rem; font-weight: 600;
      border: 2px solid rgba(255,255,255,.4); background: rgba(255,255,255,.1);
      color: #fff; cursor: pointer; transition: all .2s;
    }
    .tourism-cat-btn:hover, .tourism-cat-btn.active {
      background: var(--clr-primary); border-color: var(--clr-primary); color: #fff;
    }

    /* Interactive Map */
    #tourism-map {
      height: 520px; width: 100%; border-radius: 1rem;
      box-shadow: 0 8px 32px rgba(0,0,0,.18);
    }
    .map-section { padding: 3rem 0; }
    .map-toolbar {
      display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem;
    }
    .map-filter-btn {
      padding: .35rem .85rem; border-radius: 6px; font-size: .8rem; font-weight: 600;
      cursor: pointer; border: 1.5px solid var(--clr-border);
      background: var(--clr-surface); color: var(--clr-text);
      transition: all .18s;
    }
    .map-filter-btn.active, .map-filter-btn:hover {
      background: var(--clr-primary); color: #fff; border-color: var(--clr-primary);
    }

    /* Weather Widget */
    .weather-section { padding: 2.5rem 0; background: var(--clr-surface); }
    .weather-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 1rem;
    }
    .weather-card {
      background: var(--clr-bg); border: 1px solid var(--clr-border);
      border-radius: .75rem; padding: 1.25rem; text-align: center;
      cursor: pointer; transition: transform .2s, box-shadow .2s;
    }
    .weather-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
    .weather-city { font-weight: 700; font-size: 1rem; margin-bottom: .25rem; }
    .weather-temp { font-size: 2.2rem; font-weight: 800; color: var(--clr-primary); }
    .weather-desc { font-size: .8rem; color: var(--clr-muted); margin-top: .25rem; }
    .weather-icon { font-size: 2rem; margin-bottom: .25rem; }

    /* Destination Guides */
    .guides-section { padding: 3rem 0; }
    .guide-card {
      border-radius: 1rem; overflow: hidden;
      background: var(--clr-surface); border: 1px solid var(--clr-border);
      transition: transform .2s, box-shadow .2s;
      display: flex; flex-direction: column;
    }
    .guide-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.12); }
    .guide-card img { width: 100%; height: 180px; object-fit: cover; }
    .guide-card-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
    .guide-card-city { font-size: .75rem; font-weight: 700; color: var(--clr-primary); text-transform: uppercase; letter-spacing: .05em; }
    .guide-card-title { font-size: 1.05rem; font-weight: 700; margin: .25rem 0 .5rem; }
    .guide-card-summary { font-size: .85rem; color: var(--clr-muted); flex: 1; }
    .guide-card-footer { margin-top: 1rem; }

    .city-spotlight-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
    }
    .city-spotlight-card {
      border-radius: 1rem;
      overflow: hidden;
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      text-decoration: none;
      color: inherit;
      transition: transform .2s, box-shadow .2s;
    }
    .city-spotlight-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.12); }
    .city-spotlight-card img { width: 100%; height: 150px; object-fit: cover; display: block; }
    .city-spotlight-body { padding: 1rem; }
    .city-spotlight-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: var(--clr-primary); margin-bottom: .25rem; }
    .city-spotlight-body strong { display: block; margin-bottom: .35rem; }
    .city-spotlight-body p { margin: 0; font-size: .85rem; color: var(--clr-muted); line-height: 1.5; }

    /* Itinerary CTA */
    .itinerary-cta {
      background: linear-gradient(135deg, var(--clr-primary) 0%, var(--clr-cyan) 100%);
      color: #fff; border-radius: 1.25rem; padding: 3rem 2rem;
      text-align: center; margin: 2rem 0;
    }
    .itinerary-cta h2 { font-size: 2rem; margin-bottom: .75rem; }
    .itinerary-cta p { opacity: .9; margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto; }

    /* Travel Tips */
    .tips-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.25rem;
    }
    .tip-card {
      background: var(--clr-surface); border: 1px solid var(--clr-border);
      border-radius: .75rem; padding: 1.5rem;
      border-left: 4px solid var(--clr-primary);
    }
    .tip-card h4 { font-weight: 700; margin-bottom: .5rem; display: flex; align-items: center; gap: .5rem; }
    .tip-card p { font-size: .875rem; color: var(--clr-muted); line-height: 1.6; }

    /* Point type colors for map markers */
    .marker-icon { font-size: 1.5rem; }

    @media (max-width: 768px) {
      #tourism-map { height: 360px; }
      .weather-grid { grid-template-columns: repeat(2, 1fr); }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- ── Hero ─────────────────────────────────────────────────────────── -->
<section class="tourism-hero">
  <div class="container">
    <div class="section-label">Discover Malawi</div>
    <h1><?= uthenga_public_icon_svg('globe') ?> Explore the Warm Heart of Africa</h1>
    <p>Interactive maps, destination guides, weather info, and AI-powered trip planning - all in one place.</p>
    <div class="tourism-cats">
      <button class="tourism-cat-btn active" data-filter="all"><?= uthenga_public_icon_svg('map') ?> All</button>
      <button class="tourism-cat-btn" data-filter="attraction"><?= uthenga_public_icon_svg('sparkles') ?> Attractions</button>
      <button class="tourism-cat-btn" data-filter="hotel"><?= uthenga_public_icon_svg('hotel') ?> Hotels</button>
      <button class="tourism-cat-btn" data-filter="restaurant"><?= uthenga_public_icon_svg('restaurant') ?> Restaurants</button>
      <button class="tourism-cat-btn" data-filter="hospital"><?= uthenga_public_icon_svg('info') ?> Hospitals</button>
      <button class="tourism-cat-btn" data-filter="fuel_station"><?= uthenga_public_icon_svg('wallet') ?> Fuel</button>
      <button class="tourism-cat-btn" data-filter="atm"><?= uthenga_public_icon_svg('wallet') ?> ATMs</button>
      <button class="tourism-cat-btn" data-filter="transport"><?= uthenga_public_icon_svg('bus') ?> Transport</button>
      <button class="tourism-cat-btn" data-filter="airport"><?= uthenga_public_icon_svg('plane') ?> Airports</button>
    </div>
  </div>
</section>

<!-- ── Interactive Map ───────────────────────────────────────────────── -->
<section class="map-section">
  <div class="container">
    <div class="section-label">Interactive Map</div>
    <h2 style="margin-bottom:1rem;">Explore Malawi on the Map</h2>
    <p style="color:var(--clr-muted);margin-bottom:1.5rem;">Click any pin to see details. Use the filters above or the buttons below to find what you need.</p>

    <div class="map-toolbar" id="map-toolbar">
      <button class="map-filter-btn active" data-type="all">All Points</button>
      <button class="map-filter-btn" data-type="attraction"><?= uthenga_public_icon_svg('sparkles') ?> Attractions</button>
      <button class="map-filter-btn" data-type="hotel"><?= uthenga_public_icon_svg('hotel') ?> Hotels</button>
      <button class="map-filter-btn" data-type="restaurant"><?= uthenga_public_icon_svg('restaurant') ?> Restaurants</button>
      <button class="map-filter-btn" data-type="hospital"><?= uthenga_public_icon_svg('info') ?> Hospitals</button>
      <button class="map-filter-btn" data-type="fuel_station"><?= uthenga_public_icon_svg('wallet') ?> Fuel</button>
      <button class="map-filter-btn" data-type="atm"><?= uthenga_public_icon_svg('wallet') ?> ATMs</button>
      <button class="map-filter-btn" data-type="transport"><?= uthenga_public_icon_svg('bus') ?> Transport</button>
      <button class="map-filter-btn" data-type="airport"><?= uthenga_public_icon_svg('plane') ?> Airport</button>
    </div>

    <div id="tourism-map"></div>
  </div>
</section>

<!-- ── Weather ───────────────────────────────────────────────────────── -->
<section class="weather-section">
  <div class="container">
    <div class="section-label">Current Weather</div>
    <h2 style="margin-bottom:1.5rem;">Weather Across Malawi</h2>
    <div class="weather-grid" id="weather-grid">
      <?php foreach ($weatherCities as $city): ?>
      <div class="weather-card" data-lat="<?= $city['lat'] ?>" data-lon="<?= $city['lon'] ?>" data-city="<?= e($city['name']) ?>">
        <div class="weather-icon" id="wi-<?= e(strtolower($city['name'])) ?>"><?= uthenga_public_icon_svg('globe') ?></div>
        <div class="weather-city"><?= e($city['name']) ?></div>
        <div class="weather-temp" id="wt-<?= e(strtolower($city['name'])) ?>">--°C</div>
        <div class="weather-desc" id="wd-<?= e(strtolower($city['name'])) ?>">Loading...</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Destination Guides ────────────────────────────────────────────── -->
<section style="padding:3rem 0;">
  <div class="container">
    <div style="display:flex;align-items:end;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
      <div>
        <div class="section-label">City Spotlights</div>
        <h2 style="margin-bottom:.35rem;">Explore major Malawi hubs</h2>
      </div>
      <a href="<?= BASE_URL ?>destination-guide.php" class="btn btn-secondary btn-sm">Browse all districts</a>
    </div>
    <div class="city-spotlight-grid">
      <?php foreach ($citySpotlights as $city): ?>
        <a href="<?= BASE_URL ?>trip-planner.php?destination=<?= urlencode($city['city']) ?>" class="city-spotlight-card">
          <img src="<?= e($city['image']) ?>" alt="<?= e($city['city']) ?>" loading="lazy">
          <div class="city-spotlight-body">
            <div class="city-spotlight-label"><?= e($city['district']) ?></div>
            <strong><?= e($city['city']) ?></strong>
            <p><?= e($city['summary']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="guides-section">
  <div class="container">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;">
      <div>
        <div class="section-label">Travel Guides</div>
        <h2>Destination Guides</h2>
      </div>
      <a href="<?= BASE_URL ?>destination-guide.php" class="btn btn-secondary">View All Guides</a>
    </div>

    <?php if (empty($guides)): ?>
      <div class="guide-card" style="padding:2rem;text-align:center;grid-column:1/-1;">
        <h3 style="margin-bottom:0.5rem;">No destination guides yet</h3>
        <p class="text-muted" style="margin:0;">The travel guides will appear here once they are added in the admin dashboard.</p>
      </div>
    <?php else: ?>
    <div class="grid grid-cols-3 gap-4">
      <?php foreach ($guides as $g): ?>
      <div class="guide-card">
        <?php if (!empty($g['cover_image'])): ?>
        <img src="<?= e($g['cover_image']) ?>" alt="<?= e($g['title']) ?>" loading="lazy">
        <?php endif; ?>
        <div class="guide-card-body">
          <div class="guide-card-city"><?= e($g['city']) ?></div>
          <div class="guide-card-title"><?= e($g['title']) ?></div>
          <div class="guide-card-summary"><?= e($g['summary'] ?? '') ?></div>
          <div class="guide-card-footer">
            <a href="<?= BASE_URL ?>destination-guide.php?slug=<?= e($g['slug']) ?>" class="btn btn-sm btn-secondary" style="width:100%">Read Guide</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ── AI Trip Planner CTA ───────────────────────────────────────────── -->
<section style="padding:2rem 0;">
  <div class="container">
    <div class="itinerary-cta">
      <h2><?= uthenga_public_icon_svg('sparkles') ?> Plan Your Perfect Malawi Trip</h2>
      <p>Use our AI-powered trip planner to get a personalized itinerary, budget estimate, and downloadable PDF — in seconds.</p>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>trip-planner.php" class="btn btn-white btn-lg">ðŸ—“ Plan My Trip</a>
        <a href="<?= BASE_URL ?>ai/chat.php" class="btn btn-outline-white btn-lg">ðŸ¤– Ask AI Assistant</a>
      </div>
    </div>
  </div>
</section>

<!-- ── Travel Tips ───────────────────────────────────────────────────── -->
<section style="padding:2rem 0 3rem;">
  <div class="container">
    <div class="section-label">Tips & Advice</div>
    <h2 style="margin-bottom:1.5rem;">Essential Malawi Travel Tips</h2>
    <div class="tips-grid">
      <div class="tip-card">
        <h4><?= uthenga_public_icon_svg('wallet') ?> Currency</h4>
        <p>The Malawi Kwacha (MWK) is the local currency. ATMs are available in major cities. USD and ZAR are sometimes accepted in tourist areas.</p>
      </div>
      <div class="tip-card">
        <h4><?= uthenga_public_icon_svg('calendar') ?> Best Time to Visit</h4>
        <p>May to October (dry season) is ideal. The rainy season (November–April) brings lush greenery but some roads become impassable.</p>
      </div>
      <div class="tip-card">
        <h4><?= uthenga_public_icon_svg('warning') ?> Health</h4>
        <p>Malaria is present — take prophylaxis and use mosquito repellent. Drink bottled or purified water. Travel insurance is strongly recommended.</p>
      </div>
      <div class="tip-card">
        <h4><?= uthenga_public_icon_svg('bus') ?> Getting Around</h4>
        <p>Minibuses (matola) are cheapest. Private taxis and Mbanda (ride-share) are available in cities. AXA and Shire buses cover long routes.</p>
      </div>
      <div class="tip-card">
        <h4><?= uthenga_public_icon_svg('globe') ?> Lake Safety</h4>
        <p>Lake Malawi is generally safe for swimming on sandy shores. Avoid reedy areas (bilharzia risk). Lifeguards are rare — swim with care.</p>
      </div>
      <div class="tip-card">
        <h4><?= uthenga_public_icon_svg('phone') ?> Connectivity</h4>
        <p>Airtel and TNM provide mobile data. Coverage is good in cities and along major roads. Free WiFi is available at most hotels and cafes.</p>
      </div>
      <div class="tip-card">
        <h4><?= uthenga_public_icon_svg('heart') ?> Culture</h4>
        <p>Malawians are renowned for their warmth and friendliness — "the warm heart of Africa." Dress modestly in rural areas and markets.</p>
      </div>
      <div class="tip-card">
        <h4><?= uthenga_public_icon_svg('heart') ?> Wildlife</h4>
        <p>Never approach wildlife on foot. Always follow guide instructions. Book reputable licensed tour operators for game drives and boat safaris.</p>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- Map Points Data -->
<script>
const MAP_POINTS = <?= json_encode($mapPoints, JSON_UNESCAPED_UNICODE) ?>;

// ── Leaflet Map ────────────────────────────────────────────────────────
const map = L.map('tourism-map', {
  center: [-13.5, 33.9],
  zoom: 7,
  zoomControl: true
});

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '(c) <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  maxZoom: 18
}).addTo(map);

// Icon mapping
const iconMap = {
  attraction: `<?= addslashes(uthenga_public_icon_svg('sparkles')) ?>`,
  hotel: `<?= addslashes(uthenga_public_icon_svg('hotel')) ?>`,
  restaurant: `<?= addslashes(uthenga_public_icon_svg('restaurant')) ?>`,
  hospital: `<?= addslashes(uthenga_public_icon_svg('warning')) ?>`,
  fuel_station: `<?= addslashes(uthenga_public_icon_svg('wallet')) ?>`,
  atm: `<?= addslashes(uthenga_public_icon_svg('wallet')) ?>`,
  transport: `<?= addslashes(uthenga_public_icon_svg('bus')) ?>`,
  airport: `<?= addslashes(uthenga_public_icon_svg('plane')) ?>`,
  marina: `<?= addslashes(uthenga_public_icon_svg('map')) ?>`,
  curio_shop: `<?= addslashes(uthenga_public_icon_svg('shop')) ?>`,
  cafe: `<?= addslashes(uthenga_public_icon_svg('restaurant')) ?>`,
  other: `<?= addslashes(uthenga_public_icon_svg('pin')) ?>`
};

const colorMap = {
  attraction: '#3b82f6',
  hotel: '#8b5cf6',
  restaurant: '#f59e0b',
  hospital: '#ef4444',
  fuel_station: '#10b981',
  atm: '#f97316',
  transport: '#06b6d4',
  airport: '#6366f1',
  marina: '#0ea5e9',
  other: '#64748b'
};

function makeIcon(type) {
  const iconSvg = iconMap[type] || iconMap.other;
  const color = colorMap[type] || '#64748b';
  return L.divIcon({
    html: `<div style="
      width:36px;height:36px;border-radius:50% 50% 50% 0;
      background:${color};display:flex;align-items:center;justify-content:center;
      transform:rotate(-45deg);
      box-shadow:0 2px 8px rgba(0,0,0,.3);border:2px solid #fff;
    "><span style="transform:rotate(45deg);display:inline-flex">${iconSvg}</span></div>`,
    iconSize: [36, 36],
    iconAnchor: [18, 36],
    popupAnchor: [0, -36],
    className: `marker-${type}`
  });
}

let markers = [];

function renderMarkers(filterType) {
  markers.forEach(m => map.removeLayer(m));
  markers = [];

  const pts = filterType === 'all'
    ? MAP_POINTS
    : MAP_POINTS.filter(p => p.point_type === filterType);

  pts.forEach(p => {
    if (!p.latitude || !p.longitude) return;
    const icon = makeIcon(p.point_type);
    const m = L.marker([p.latitude, p.longitude], {icon}).addTo(map);
    const phone = p.phone ? `<br>Phone: ${p.phone}` : '';
    const web = p.website ? `<br><a href="${p.website}" target="_blank">Website</a>` : '';
    m.bindPopup(`
      <strong>${p.name}</strong><br>
      <em>${(p.point_type||'').replace('_',' ')}</em>
      ${p.city ? '<br>Location: ' + p.city : ''}
      ${p.address ? '<br>' + p.address : ''}
      ${phone}${web}
      ${p.description ? '<br><small style="color:#666">' + p.description.substring(0,100) + (p.description.length>100?'...':'') + '</small>' : ''}
    `, {maxWidth: 260});
    markers.push(m);
  });
}

renderMarkers('all');

// Map filter buttons
document.querySelectorAll('.map-filter-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.map-filter-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    renderMarkers(this.dataset.type);
  });
});

// Hero category buttons also filter map
document.querySelectorAll('.tourism-cat-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tourism-cat-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const filter = this.dataset.filter;
    renderMarkers(filter);
    // Sync map toolbar
    document.querySelectorAll('.map-filter-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.type === filter);
    });
    // Scroll to map
    document.getElementById('tourism-map').scrollIntoView({behavior:'smooth', block:'center'});
  });
});

// ── Weather Fetch ──────────────────────────────────────────────────────
const weatherCards = document.querySelectorAll('.weather-card');

const weatherCodes = {};
const weatherDescs = {
  0:'Clear sky',1:'Mainly clear',2:'Partly cloudy',3:'Overcast',
  45:'Foggy',48:'Foggy',51:'Light drizzle',53:'Drizzle',55:'Heavy drizzle',
  61:'Slight rain',63:'Moderate rain',65:'Heavy rain',
  71:'Light snow',73:'Moderate snow',75:'Heavy snow',
  80:'Showers',81:'Rain showers',82:'Violent showers',
  95:'Thunderstorm',96:'Thunderstorm',99:'Thunderstorm'
};

weatherCards.forEach(card => {
  const lat = card.dataset.lat;
  const lon = card.dataset.lon;
  const city = card.dataset.city.toLowerCase();
  const iconEl = document.getElementById('wi-' + city);
  const tempEl = document.getElementById('wt-' + city);
  const descEl = document.getElementById('wd-' + city);

  fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true&timezone=Africa/Blantyre`)
    .then(r => r.json())
    .then(data => {
      const w = data.current_weather;
      const code = w.weathercode;
      if (iconEl) iconEl.innerHTML = `<?= addslashes(uthenga_public_icon_svg('globe')) ?>`;
      if (tempEl) tempEl.textContent = Math.round(w.temperature) + '°C';
      if (descEl) descEl.textContent = weatherDescs[code] || 'Weather data';
    })
    .catch(() => {
      if (tempEl) tempEl.textContent = '--°C';
      if (descEl) descEl.textContent = 'Unavailable';
      if (iconEl) iconEl.innerHTML = `<?= addslashes(uthenga_public_icon_svg('globe')) ?>`;
    });
});
</script>
</body>
</html>


