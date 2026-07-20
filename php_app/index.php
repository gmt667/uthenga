<?php
/**
 * Uthenga - Clean Home Page
 */
require_once __DIR__ . '/config.php';

$pageTitle = 'Explore Malawi';
$activeNav = 'home';
$search = trim($_GET['q'] ?? '');
$searchResults = marketplace_fetch_home_feed($search, 12);

// ?? Session-based cache for expensive homepage featured queries ???????????????
// Cache key includes a 10-minute bucket so it auto-expires
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
// ?????????????????????????????????????????????????????????????????????????????

$popularCategories = [
    ['label' => 'Events', 'href' => 'events.php', 'note' => 'Concerts, festivals, and sports'],
    ['label' => 'Stays', 'href' => 'hotels.php', 'note' => 'Hotels, lodges, and apartments'],
    ['label' => 'Transport', 'href' => 'transport.php', 'note' => 'Bus, shuttle, and route bookings'],
    ['label' => 'Explore', 'href' => 'tours.php', 'note' => 'Tours and curated experiences'],
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
            <div class="malawi-stat-value">?</div>
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
      <div class="section-header" style="margin-bottom:1.25rem;">
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
                <?php if (!empty($listing['is_trending'])): ?><span class="card-badge badge-trending" style="left:auto;right:0.75rem;">?? Trending</span><?php endif; ?>
              </div>
              <div class="card-body">
                <div class="card-title"><?= e($listing['title']) ?></div>
                <div class="card-loc">Location: <?= e($listing['location']) ?></div>
                <div class="text-sm text-muted" style="margin-top:0.5rem;">Rating: <?= e(isset($listing['rating']) ? $listing['rating'] : 0) ?>/5</div>
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
              <?php if (!empty($listing['is_trending'])): ?><span class="card-badge badge-trending" style="left:auto;right:0.75rem;">?? Trending</span><?php endif; ?>
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

<!-- ??? Promotional Popup ??????????????????????????????????????????????????? -->
<div id="uthenga-popup-overlay" role="dialog" aria-modal="true" aria-label="Promotion" style="display:none;">
  <div id="uthenga-popup-card">
    <button id="uthenga-popup-close" aria-label="Close promotion">&times;</button>
    <div id="uthenga-popup-img-wrap" style="display:none;">
      <img id="uthenga-popup-img" src="" alt="" loading="lazy">
    </div>
    <div id="uthenga-popup-body">
      <div id="uthenga-popup-title"></div>
      <p id="uthenga-popup-desc"></p>
      <a id="uthenga-popup-cta" href="#" class="btn btn-primary" style="width:100%;justify-content:center;">Learn More</a>
    </div>
  </div>
</div>
<script>
(function () {
  'use strict';
  var SUPPRESS_KEY = 'uthenga_popup_dismissed_at';
  var SUPPRESS_MS  = 10 * 60 * 1000; // 10 minutes
  var AUTO_CLOSE_MS = 15000;          // 15 seconds

  // Check LocalStorage suppression
  try {
    var dismissed = localStorage.getItem(SUPPRESS_KEY);
    if (dismissed && (Date.now() - parseInt(dismissed, 10)) < SUPPRESS_MS) {
      return; // still within suppression window
    }
  } catch (e) {}

  function dismissPopup() {
    var overlay = document.getElementById('uthenga-popup-overlay');
    if (overlay) {
      overlay.style.opacity = '0';
      setTimeout(function () { overlay.style.display = 'none'; }, 300);
    }
    try { localStorage.setItem(SUPPRESS_KEY, String(Date.now())); } catch (e) {}
  }

  function showPopup(data) {
    var overlay = document.getElementById('uthenga-popup-overlay');
    if (!overlay) return;

    // Populate content
    var title = document.getElementById('uthenga-popup-title');
    var desc  = document.getElementById('uthenga-popup-desc');
    var cta   = document.getElementById('uthenga-popup-cta');
    var imgW  = document.getElementById('uthenga-popup-img-wrap');
    var img   = document.getElementById('uthenga-popup-img');

    if (title) title.textContent = data.title || '';
    if (desc)  desc.textContent  = data.description || '';
    if (cta) {
      cta.textContent = data.cta_text || 'Learn More';
      cta.href = data.cta_url || '#';
    }
    if (imgW && img && data.image_url) {
      img.src = data.image_url;
      imgW.style.display = 'block';
    }

    overlay.style.display = 'flex';
    // Trigger transition
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        overlay.style.opacity = '1';
      });
    });

    // Auto-close after 15 seconds
    setTimeout(dismissPopup, AUTO_CLOSE_MS);
  }

  function fetchAndShow() {
    fetch('<?= BASE_URL ?>api/get_active_popup.php')
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.active) {
          var delay = Math.max(3000, (parseInt(data.delay_seconds, 10) || 3) * 1000);
          setTimeout(function () {
            showPopup(data);
          }, delay);
        }
      })
      .catch(function () {});
  }

  // Wire up close button and overlay click
  document.addEventListener('DOMContentLoaded', function () {
    var closeBtn = document.getElementById('uthenga-popup-close');
    var overlay  = document.getElementById('uthenga-popup-overlay');
    if (closeBtn) closeBtn.addEventListener('click', dismissPopup);
    if (overlay)  overlay.addEventListener('click', function (e) {
      if (e.target === overlay) dismissPopup();
    });
    // Keyboard ESC
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') dismissPopup();
    });
    fetchAndShow(); // Show after the configured delay (minimum 3 seconds)
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
