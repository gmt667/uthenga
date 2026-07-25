<?php
/**
 * Uthenga - Clean Home Page
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Explore Malawi';
$activeNav = 'home';
$search = trim($_GET['q'] ?? '');
$searchResults = marketplace_fetch_home_feed($search, 12);

// Session-based cache for expensive homepage featured queries.
// Cache key includes a 10-minute bucket so it auto-expires.
$homeCacheKey = 'home_featured_' . floor(time() / 600);
if (!isset($_SESSION[$homeCacheKey])) {
    // Clear any previous bucket's data
    foreach (array_keys($_SESSION) as $k) {
        if (strpos($k, 'home_featured_') === 0) {
            unset($_SESSION[$k]);
        }
    }
    $_SESSION[$homeCacheKey] = [
        'events'         => marketplace_fetch_ranked_events('', 4, true),
        'stays'          => marketplace_fetch_properties('', 4, true),
        'transport'      => marketplace_fetch_transport_routes('', 4, true),
        'total_listings' => dbCount("SELECT COUNT(*) FROM listings WHERE is_active = 1"),
        'total_bookings' => dbCount("SELECT COUNT(*) FROM bookings"),
        'total_vendors'  => dbCount("SELECT COUNT(*) FROM users WHERE role <> 'Customer'"),
    ];
}
$featuredEvents    = $_SESSION[$homeCacheKey]['events'];
$featuredStays     = $_SESSION[$homeCacheKey]['stays'];
$featuredTransport = $_SESSION[$homeCacheKey]['transport'];
$featuredMbanda    = function_exists('marketplace_fetch_mbanda') ? marketplace_fetch_mbanda('', 4, true) : [];
$totalListings     = $_SESSION[$homeCacheKey]['total_listings'];
$totalBookings     = $_SESSION[$homeCacheKey]['total_bookings'];
$totalVendors      = $_SESSION[$homeCacheKey]['total_vendors'];
$featuredEventsFallback = [
    [
        'id' => 'home-event-1',
        'title' => 'Lake Shore Music Night',
        'location' => 'Mangochi, Lake Malawi',
        'price_label' => 'From MK 20,000',
        'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=900&fit=crop&q=80',
        'badge_class' => 'badge-event',
        'type_label' => 'Event',
        'detail_url' => BASE_URL . 'events.php',
        'is_trending' => true,
    ],
    [
        'id' => 'home-event-2',
        'title' => 'Mzuzu Street Food Festival',
        'location' => 'Mzuzu City',
        'price_label' => 'From MK 12,000',
        'image' => 'https://images.unsplash.com/photo-1541544181051-e46607bc22a4?w=900&fit=crop&q=80',
        'badge_class' => 'badge-event',
        'type_label' => 'Event',
        'detail_url' => BASE_URL . 'events.php',
        'is_trending' => false,
    ],
    [
        'id' => 'home-event-3',
        'title' => 'Blantyre Live Showcase',
        'location' => 'Blantyre',
        'price_label' => 'From MK 15,000',
        'image' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=900&fit=crop&q=80',
        'badge_class' => 'badge-event',
        'type_label' => 'Event',
        'detail_url' => BASE_URL . 'events.php',
        'is_trending' => false,
    ],
    [
        'id' => 'home-event-4',
        'title' => 'Lilongwe Weekend Expo',
        'location' => 'Lilongwe',
        'price_label' => 'From MK 10,000',
        'image' => 'https://images.unsplash.com/photo-1511578314322-379afb476865?w=900&fit=crop&q=80',
        'badge_class' => 'badge-event',
        'type_label' => 'Event',
        'detail_url' => BASE_URL . 'events.php',
        'is_trending' => true,
    ],
];
$featuredStaysFallback = [
    [
        'id' => 'home-stay-1',
        'title' => 'Sunbird Waterfront Lodge',
        'location' => 'Lilongwe',
        'price_label' => 'From MK 120,000',
        'image' => 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=900&fit=crop&q=80',
        'badge_class' => 'badge-stay',
        'type_label' => 'Stay',
        'detail_url' => BASE_URL . 'hotels.php',
    ],
    [
        'id' => 'home-stay-2',
        'title' => 'Cape Maclear Beach Retreat',
        'location' => 'Mangochi',
        'price_label' => 'From MK 95,000',
        'image' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=900&fit=crop&q=80',
        'badge_class' => 'badge-stay',
        'type_label' => 'Stay',
        'detail_url' => BASE_URL . 'hotels.php',
    ],
    [
        'id' => 'home-stay-3',
        'title' => 'Zomba Plateau Guest House',
        'location' => 'Zomba',
        'price_label' => 'From MK 70,000',
        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=900&fit=crop&q=80',
        'badge_class' => 'badge-stay',
        'type_label' => 'Stay',
        'detail_url' => BASE_URL . 'hotels.php',
    ],
    [
        'id' => 'home-stay-4',
        'title' => 'Mzuzu Executive Suites',
        'location' => 'Mzuzu',
        'price_label' => 'From MK 110,000',
        'image' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=900&fit=crop&q=80',
        'badge_class' => 'badge-stay',
        'type_label' => 'Stay',
        'detail_url' => BASE_URL . 'hotels.php',
    ],
];
$featuredTransportFallback = [
    [
        'id' => 'home-transport-1',
        'title' => 'Lake Shuttle Express',
        'location' => 'Lilongwe - Mangochi',
        'price_label' => 'From MK 18,500',
        'image' => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=900&fit=crop&q=80',
        'badge_class' => 'badge-transport',
        'type_label' => 'Transport',
        'detail_url' => BASE_URL . 'transport.php',
    ],
    [
        'id' => 'home-transport-2',
        'title' => 'Airport Connect',
        'location' => 'Blantyre',
        'price_label' => 'From MK 22,000',
        'image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=900&fit=crop&q=80',
        'badge_class' => 'badge-transport',
        'type_label' => 'Transport',
        'detail_url' => BASE_URL . 'transport.php',
    ],
    [
        'id' => 'home-transport-3',
        'title' => 'City Ride Mbanda',
        'location' => 'Lilongwe',
        'price_label' => 'From MK 8,000',
        'image' => 'https://images.unsplash.com/photo-1502877338535-766e1452684a?w=900&fit=crop&q=80',
        'badge_class' => 'badge-transport',
        'type_label' => 'Transport',
        'detail_url' => BASE_URL . 'transport.php',
    ],
    [
        'id' => 'home-transport-4',
        'title' => 'Southern Route Coaches',
        'location' => 'Blantyre',
        'price_label' => 'From MK 14,000',
        'image' => 'https://images.unsplash.com/photo-1504609813442-a8924e83f76e?w=900&fit=crop&q=80',
        'badge_class' => 'badge-transport',
        'type_label' => 'Transport',
        'detail_url' => BASE_URL . 'transport.php',
    ],
];
$featuredMbandaFallback = [
    [
        'id' => 'home-mbanda-1',
        'title' => 'Sunrise Share Ride',
        'location' => 'Blantyre',
        'price_label' => 'Split from MK 6,000',
        'image' => 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=900&fit=crop&q=80',
        'badge_class' => 'badge-mbanda',
        'type_label' => 'Mbanda',
        'detail_url' => BASE_URL . 'mbanda/index.php',
    ],
    [
        'id' => 'home-mbanda-2',
        'title' => 'Lake Road Pool',
        'location' => 'Mangochi',
        'price_label' => 'Split from MK 5,500',
        'image' => 'https://images.unsplash.com/photo-1493238792000-8113da705763?w=900&fit=crop&q=80',
        'badge_class' => 'badge-mbanda',
        'type_label' => 'Mbanda',
        'detail_url' => BASE_URL . 'mbanda/index.php',
    ],
    [
        'id' => 'home-mbanda-3',
        'title' => 'City Commute Share',
        'location' => 'Lilongwe',
        'price_label' => 'Split from MK 4,000',
        'image' => 'https://images.unsplash.com/photo-1523906834658-6e24ef2386f9?w=900&fit=crop&q=80',
        'badge_class' => 'badge-mbanda',
        'type_label' => 'Mbanda',
        'detail_url' => BASE_URL . 'mbanda/index.php',
    ],
    [
        'id' => 'home-mbanda-4',
        'title' => 'Airport Ride Share',
        'location' => 'Blantyre Airport',
        'price_label' => 'Split from MK 7,500',
        'image' => 'https://images.unsplash.com/photo-1511919884226-fd3cad34687c?w=900&fit=crop&q=80',
        'badge_class' => 'badge-mbanda',
        'type_label' => 'Mbanda',
        'detail_url' => BASE_URL . 'mbanda/index.php',
    ],
];

if (empty($featuredEvents)) {
    $featuredEvents = $featuredEventsFallback;
}
if (empty($featuredStays)) {
    $featuredStays = $featuredStaysFallback;
}
if (empty($featuredTransport)) {
    $featuredTransport = $featuredTransportFallback;
}
if (empty($featuredMbanda)) {
    $featuredMbanda = $featuredMbandaFallback;
}

$popularCategories = [
    ['label' => 'Events', 'href' => 'events.php', 'note' => 'Concerts, festivals, and sports', 'icon' => 'calendar'],
    ['label' => 'Stays', 'href' => 'hotels.php', 'note' => 'Hotels, lodges, and apartments', 'icon' => 'hotel'],
    ['label' => 'Transport', 'href' => 'transport.php', 'note' => 'Bus, shuttle, and route bookings', 'icon' => 'bus'],
    ['label' => 'Explore', 'href' => 'tours.php', 'note' => 'Tours and curated experiences', 'icon' => 'map'],
];

?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<section class="section" style="padding-top:3rem;padding-bottom:2rem;">
  <div class="container">
    <div class="grid grid-cols-2 gap-4" style="align-items:center;">
      <div>
        <div class="section-label">Marketplace</div>
        <h1 style="margin-bottom:1rem;">Find events, stays, and transport without the clutter.</h1>
        <p style="max-width:620px;margin-bottom:1.5rem;">
          Search, compare, and book in a few clear steps. Uthenga keeps the experience simple so people can move from discovery to checkout quickly.
        </p>
        <form method="get" action="index.php" class="search-bar" style="max-width:680px;">
          <span>Search</span>
          <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search events, places, or routes" aria-label="Search marketplace">
          <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
      </div>

      <!-- Stats card with live background slideshow -->
      <div class="malawi-bg-card" id="malawi-bg-card">

        <?php
        $malawiSlides = [
          'https://images.unsplash.com/photo-1612892483236-52d32a0e0ac1?w=700&fit=crop&q=80',
          'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=700&fit=crop&q=80',
          'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=700&fit=crop&q=80',
          'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=700&fit=crop&q=80',
          'https://images.unsplash.com/photo-1516026672322-bc52d61a55d5?w=700&fit=crop&q=80',
        ];
        foreach ($malawiSlides as $i => $url): ?>
          <div class="malawi-bg-slide <?= $i === 0 ? 'active' : '' ?>"
               style="background-image:url('<?= e($url) ?>')"></div>
        <?php endforeach; ?>

        <!-- dark overlay so text stays legible -->
        <div class="malawi-bg-overlay"></div>

        <!-- stats on top -->
        <div class="malawi-bg-stats">
          <div class="malawi-stat">
            <div class="malawi-stat-value"><?= number_format($totalListings) ?></div>
            <div class="malawi-stat-label">Listings</div>
          </div>
          <div class="malawi-stat">
            <div class="malawi-stat-value"><?= number_format($totalBookings) ?></div>
            <div class="malawi-stat-label">Bookings</div>
          </div>
          <div class="malawi-stat">
            <div class="malawi-stat-value"><?= number_format($totalVendors) ?></div>
            <div class="malawi-stat-label">Vendors</div>
          </div>
          <div class="malawi-stat">
            <div class="malawi-stat-value"><?= uthenga_public_icon_svg('sparkles') ?></div>
            <div class="malawi-stat-label">Fast checkout</div>
          </div>
        </div>

      </div>

      <script>
      (function(){
        var slides = document.querySelectorAll('#malawi-bg-card .malawi-bg-slide');
        var cur = 0;
        setInterval(function(){
          slides[cur].classList.remove('active');
          cur = (cur + 1) % slides.length;
          slides[cur].classList.add('active');
        }, 4500);
      })();
      </script>


    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="grid grid-cols-4 gap-3">
      <?php foreach ($popularCategories as $category): ?>
        <a href="<?= BASE_URL . $category['href'] ?>" class="card" style="padding:1.25rem;text-decoration:none;">
          <div style="display:inline-flex;align-items:center;justify-content:center;width:2.25rem;height:2.25rem;border-radius:999px;background:var(--clr-surface);color:var(--clr-primary);margin-bottom:.75rem;">
            <?= uthenga_public_icon_svg($category['icon']) ?>
          </div>
          <div class="card-title" style="margin-bottom:0.35rem;"><?= e($category['label']) ?></div>
          <div class="text-sm text-muted"><?= e($category['note']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($search !== ''): ?>
  <section class="section" id="results">
    <div class="container">
      <div class="section-header">
        <div>
          <div class="section-label">Search Results</div>
          <h2 style="margin-top:0.25rem;"><?= e($search) ?></h2>
        </div>
        <a href="<?= BASE_URL ?>index.php" class="btn btn-secondary btn-sm">Clear</a>
      </div>

      <?php if (empty($searchResults)): ?>
        <div class="card" style="padding:2rem;text-align:center;">
          <h3>No matching results</h3>
          <p style="margin-top:0.5rem;">Try a different keyword or browse a category below.</p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-4 gap-3">
          <?php foreach ($searchResults as $listing): ?>
            <article class="card">
              <div class="card-img-wrap">
                <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
                <span class="card-badge <?= e($listing['badge_class']) ?>"><?= e($listing['type_label']) ?></span>
                <?php if (!empty($listing['is_trending'])): ?><span class="card-badge badge-trending" style="left:auto;right:0.75rem;display:inline-flex;align-items:center;gap:.25rem;"><?= uthenga_public_icon_svg('sparkles') ?> Trending</span><?php endif; ?>
              </div>
              <div class="card-body">
                <div class="card-title"><?= e($listing['title']) ?></div>
                <div class="card-loc">Location: <?= e($listing['location']) ?></div>
                <div class="card-price"><?= e($listing['price_label']) ?></div>
              </div>
              <div class="card-footer">
                <a href="<?= e($listing['detail_url']) ?>" class="btn btn-secondary btn-sm" style="width:100%;" <?= (($listing['listing_type'] ?? $listing['type'] ?? '') === 'event') ? 'data-track-event-click="' . e($listing['id']) . '"' : '' ?>>View Details</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php else: ?>
  <section class="section">
    <div class="container">
      <div class="section-header">
        <div>
          <div class="section-label">Featured</div>
          <h2>Featured Events</h2>
        </div>
        <a href="<?= BASE_URL ?>events.php" class="btn btn-secondary btn-sm">View All Events</a>
      </div>
      <div class="grid grid-cols-4 gap-3">
        <?php foreach ($featuredEvents as $listing): ?>
          <article class="card">
            <div class="card-img-wrap">
              <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
              <span class="card-badge <?= e($listing['badge_class']) ?>"><?= e($listing['type_label']) ?></span>
              <?php if (!empty($listing['is_trending'])): ?><span class="card-badge badge-trending" style="left:auto;right:0.75rem;display:inline-flex;align-items:center;gap:.25rem;"><?= uthenga_public_icon_svg('sparkles') ?> Trending</span><?php endif; ?>
            </div>

            <div class="card-body">
              <div class="card-title"><?= e($listing['title']) ?></div>
              <div class="card-loc"><?= e($listing['location']) ?></div>
              <div class="card-price"><?= e($listing['price_label']) ?></div>
            </div>
            <div class="card-footer">
              <a href="<?= e($listing['detail_url']) ?>" class="btn btn-secondary btn-sm" style="width:100%;" data-track-event-click="<?= e($listing['id']) ?>">View Details</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section" style="background:var(--clr-surface);border-top:1px solid var(--clr-border);border-bottom:1px solid var(--clr-border);">
    <div class="container">
      <div class="section-header">
        <div>
          <div class="section-label">Featured</div>
          <h2>Featured Stays</h2>
        </div>
        <a href="<?= BASE_URL ?>hotels.php" class="btn btn-secondary btn-sm">View All Stays</a>
      </div>
      <div class="grid grid-cols-4 gap-3">
        <?php foreach ($featuredStays as $listing): ?>
          <article class="card">
            <div class="card-img-wrap">
              <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
              <span class="card-badge <?= e($listing['badge_class']) ?>"><?= e($listing['type_label']) ?></span>
            </div>
            <div class="card-body">
              <div class="card-title"><?= e($listing['title']) ?></div>
              <div class="card-loc"><?= e($listing['location']) ?></div>
              <div class="card-price"><?= e($listing['price_label']) ?></div>
            </div>
            <div class="card-footer">
              <a href="<?= e($listing['detail_url']) ?>" class="btn btn-secondary btn-sm" style="width:100%;">View Details</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-header">
        <div>
          <div class="section-label">Featured</div>
          <h2>Featured Transport</h2>
        </div>
        <a href="<?= BASE_URL ?>transport.php" class="btn btn-secondary btn-sm">View Transport</a>
      </div>
      <div class="grid grid-cols-4 gap-3">
        <?php foreach ($featuredTransport as $listing): ?>
          <article class="card">
            <div class="card-img-wrap">
              <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
              <span class="card-badge <?= e($listing['badge_class']) ?>"><?= e($listing['type_label']) ?></span>
            </div>
            <div class="card-body">
              <div class="card-title"><?= e($listing['title']) ?></div>
              <div class="card-loc"><?= e($listing['location']) ?></div>
              <div class="card-price"><?= e($listing['price_label']) ?></div>
            </div>
            <div class="card-footer">
              <a href="<?= e($listing['detail_url']) ?>" class="btn btn-secondary btn-sm" style="width:100%;">View Details</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section" style="padding-top:0;">
    <div class="container">
      <div class="section-header">
        <div>
          <div class="section-label">Featured</div>
          <h2>Mbanda</h2>
        </div>
        <a href="<?= BASE_URL ?>mbanda/index.php" class="btn btn-secondary btn-sm">View Mbanda</a>
      </div>

      <?php if (!empty($featuredMbanda)): ?>
        <div class="grid grid-cols-4 gap-3">
          <?php foreach ($featuredMbanda as $listing): ?>
            <article class="card">
              <div class="card-img-wrap">
                <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
                <span class="card-badge <?= e($listing['badge_class']) ?>"><?= e($listing['type_label']) ?></span>
              </div>
              <div class="card-body">
                <div class="card-title"><?= e($listing['title']) ?></div>
                <div class="card-loc"><?= e($listing['location']) ?></div>
                <div class="card-price"><?= e($listing['price_label']) ?></div>
              </div>
              <div class="card-footer">
                <a href="<?= e($listing['detail_url']) ?>" class="btn btn-secondary btn-sm" style="width:100%;">View Details</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="card" style="padding:2rem;background:linear-gradient(135deg,rgba(14,165,233,.12),rgba(230,57,70,.08));border:1px solid rgba(14,165,233,.18);">
          <div class="grid grid-cols-2 gap-4" style="align-items:center;">
            <div>
              <h2 style="margin-top:0.35rem;">Share rides across Malawi.</h2>
              <p style="margin:0.75rem 0 1.25rem;max-width:560px;">
                Find or offer Mbanda rides, split travel costs, and get to your destination with less hassle.
              </p>
              <div style="display:flex;gap:0.75rem;flex-wrap:wrap;">
                <a href="<?= BASE_URL ?>mbanda/index.php" class="btn btn-primary">Explore Mbanda</a>
                <a href="<?= BASE_URL ?>mbanda/create_trip.php" class="btn btn-secondary">Offer a Ride</a>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div class="card" style="padding:1rem;">
                <div class="card-title" style="margin-bottom:0.35rem;">Browse trips</div>
                <div class="text-sm text-muted">See active departures and seat availability.</div>
              </div>
              <div class="card" style="padding:1rem;">
                <div class="card-title" style="margin-bottom:0.35rem;">Share costs</div>
                <div class="text-sm text-muted">Travel together and cut transport expenses.</div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!isLoggedIn()): ?>
    <section class="section" style="padding-top:1rem;">
      <div class="container">
        <div class="card" style="padding:2rem;text-align:center;">
          <div class="section-label">Become a vendor</div>
          <h2 style="margin-top:0.35rem;">List your service on Uthenga.</h2>
          <p style="max-width:620px;margin:0.75rem auto 1.5rem;">
            Create listings for events, stays, or transport and reach customers who are ready to book.
          </p>
          <div style="display:flex;gap:0.75rem;justify-content:center;flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>vendor/register.php" class="btn btn-primary">Become a Vendor</a>
            <a href="<?= BASE_URL ?>register.php" class="btn btn-secondary">Register</a>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>

<!-- ─── Paid Promotional Popup Ad ───────────────────────────────────────────── -->
<style>
.uthenga-popup-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.78); backdrop-filter: blur(6px);
  z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.uthenga-popup-card {
  position: relative; background: var(--clr-surface, #1e1e2d); border: 1px solid var(--clr-border, rgba(255,255,255,0.15));
  border-radius: 16px; width: 100%; max-width: 440px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.5);
  animation: popupSlideIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes popupSlideIn {
  from { transform: translateY(20px) scale(0.95); opacity: 0; }
  to { transform: translateY(0) scale(1); opacity: 1; }
}
#uthenga-popup-close {
  position: absolute; top: 12px; right: 12px; z-index: 10; width: 32px; height: 32px;
  border-radius: 50%; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2);
  color: #fff; font-size: 20px; line-height: 1; display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: all 0.2s ease;
}
#uthenga-popup-close:hover { background: #e63946; color: #fff; transform: scale(1.1); }
.uthenga-popup-header {
  padding: 1rem 1.25rem 0.6rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
  border-bottom: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02);
}
.uthenga-ad-badge {
  font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;
  background: linear-gradient(135deg, #ff6b35, #f72585); color: #fff; padding: 0.2rem 0.55rem; border-radius: 20px;
}
.uthenga-ad-sponsor { font-size: 0.78rem; font-weight: 600; color: var(--clr-accent, #ff6b35); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.uthenga-popup-img-wrap { width: 100%; height: 180px; overflow: hidden; position: relative; background: #000; }
.uthenga-popup-img-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
.uthenga-popup-card:hover .uthenga-popup-img-wrap img { transform: scale(1.03); }
.uthenga-popup-body { padding: 1.25rem; }
.uthenga-popup-body h3 { font-size: 1.2rem; font-weight: 700; color: #fff; margin-bottom: 0.5rem; line-height: 1.3; }
.uthenga-popup-body p { font-size: 0.88rem; color: rgba(255,255,255,0.75); margin-bottom: 1rem; line-height: 1.5; }
.uthenga-popup-vendor-link { font-size: 0.78rem; color: rgba(255,255,255,0.5); text-align: center; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed rgba(255,255,255,0.1); }
.uthenga-popup-vendor-link a { color: var(--clr-accent, #ff6b35); font-weight: 600; text-decoration: underline; }
</style>

<div id="uthenga-popup-overlay" class="uthenga-popup-overlay" role="dialog" aria-modal="true" aria-label="Paid Advertisement" style="display:none; opacity:0; transition: opacity 0.3s ease;">
  <div id="uthenga-popup-card" class="uthenga-popup-card">
    <button id="uthenga-popup-close" aria-label="Close promotion">&times;</button>
    <div class="uthenga-popup-header">
      <span class="uthenga-ad-badge">⭐ Paid Advertisement</span>
      <span id="uthenga-popup-sponsor" class="uthenga-ad-sponsor">Sponsored by Partner</span>
    </div>
    <div id="uthenga-popup-img-wrap" class="uthenga-popup-img-wrap" style="display:none;">
      <img id="uthenga-popup-img" src="" alt="Paid Advertisement" loading="lazy">
    </div>
    <div id="uthenga-popup-body" class="uthenga-popup-body">
      <h3 id="uthenga-popup-title"></h3>
      <p id="uthenga-popup-desc"></p>
      <div class="uthenga-popup-actions">
        <a id="uthenga-popup-cta" href="#" class="btn btn-primary" style="width:100%; justify-content:center; text-align:center;">Learn More</a>
        <button id="uthenga-popup-dismiss-btn" class="btn btn-secondary" style="width:100%; justify-content:center; text-align:center; margin-top:0.4rem; background:transparent; border:1px solid rgba(255,255,255,0.2);">Cancel / Close</button>
      </div>
      <div class="uthenga-popup-vendor-link">
        Are you a Vendor, Customer, or Business Owner? <a href="<?= BASE_URL ?>vendor/ads.php">Create a Paid Ad</a>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  'use strict';
  var POPUP_INTERVAL_MS = 180000; // 3 minutes (180 seconds)
  var adPool = [];
  var timerId = null;

  function getRandomAd() {
    if (!adPool || adPool.length === 0) return null;
    var randomIndex = Math.floor(Math.random() * adPool.length);
    return adPool[randomIndex];
  }

  function dismissPopup() {
    var overlay = document.getElementById('uthenga-popup-overlay');
    if (overlay) {
      overlay.style.opacity = '0';
      setTimeout(function () { overlay.style.display = 'none'; }, 300);
    }
    
    // Record cancellation timestamp
    try {
      localStorage.setItem('uthenga_ad_cancelled_time', String(Date.now()));
    } catch (e) {}

    // Schedule next random ad after 3 minutes (180,000ms)
    scheduleNextPopup(POPUP_INTERVAL_MS);
  }

  function scheduleNextPopup(delayMs) {
    if (timerId) clearTimeout(timerId);

    timerId = setTimeout(function () {
      showRandomPopup();
    }, delayMs);
  }

  function showPopup(data) {
    if (!data) return;

    var overlay = document.getElementById('uthenga-popup-overlay');
    if (!overlay) return;

    var title   = document.getElementById('uthenga-popup-title');
    var desc    = document.getElementById('uthenga-popup-desc');
    var cta     = document.getElementById('uthenga-popup-cta');
    var sponsor = document.getElementById('uthenga-popup-sponsor');
    var imgW    = document.getElementById('uthenga-popup-img-wrap');
    var img     = document.getElementById('uthenga-popup-img');

    if (title)   title.textContent   = data.title || '';
    if (desc)    desc.textContent    = data.description || '';
    if (sponsor) sponsor.textContent = 'Paid Ad by ' + (data.sponsor_name || 'Verified Vendor');
    
    if (cta) {
      cta.textContent = data.cta_text || 'Learn More';
      cta.href = data.cta_url || '#';
    }
    
    if (imgW && img && data.image_url) {
      img.src = data.image_url;
      imgW.style.display = 'block';
    } else if (imgW) {
      imgW.style.display = 'none';
    }

    overlay.style.display = 'flex';
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        overlay.style.opacity = '1';
      });
    });
  }

  function showRandomPopup() {
    var ad = getRandomAd();
    if (ad) {
      showPopup(ad);
    }
  }

  function fetchAdsAndStart() {
    fetch('<?= BASE_URL ?>api/get_active_popup.php')
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.items && res.items.length > 0) {
          adPool = res.items;
          if (res.repopup_ms) {
            POPUP_INTERVAL_MS = res.repopup_ms; // 180,000 ms (3 minutes)
          }
          
          var lastCancelled = 0;
          try {
            lastCancelled = parseInt(localStorage.getItem('uthenga_ad_cancelled_time') || '0', 10);
          } catch(e) {}

          var elapsed = Date.now() - lastCancelled;
          if (lastCancelled > 0 && elapsed < POPUP_INTERVAL_MS) {
            var remaining = POPUP_INTERVAL_MS - elapsed;
            scheduleNextPopup(remaining);
          } else {
            // Initial show after 3 seconds
            setTimeout(function () {
              showRandomPopup();
            }, 3000);
          }
        }
      })
      .catch(function (err) {
        console.error('Failed to load popup ads', err);
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var closeBtn   = document.getElementById('uthenga-popup-close');
    var dismissBtn = document.getElementById('uthenga-popup-dismiss-btn');
    var overlay    = document.getElementById('uthenga-popup-overlay');

    if (closeBtn) closeBtn.addEventListener('click', dismissPopup);
    if (dismissBtn) dismissBtn.addEventListener('click', dismissPopup);
    if (overlay) {
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) dismissPopup();
      });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') dismissPopup();
    });

    fetchAdsAndStart();
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
