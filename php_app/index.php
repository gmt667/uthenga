<?php
/**
 * Uthenga — Public Landing Page
 * Premium hero + service discovery for unauthenticated visitors.
 * Logged-in customers are redirected to their customer dashboard.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/malawi_locations.php';

/* ── Customer redirect ─────────────────────────────────────────────────── */
if (isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === ROLE_CUSTOMER) {
    header('Location: ' . BASE_URL . 'dashboard.php', true, 302);
    exit;
}

$pageTitle = 'Explore Malawi';
$activeNav = 'home';
$search    = trim($_GET['q'] ?? '');

// Session-based cache (10-minute buckets)
$homeCacheKey = 'home_featured_' . floor(time() / 600);
if (!isset($_SESSION[$homeCacheKey])) {
    foreach (array_keys($_SESSION) as $k) {
        if (strpos($k, 'home_featured_') === 0) unset($_SESSION[$k]);
    }
    $_SESSION[$homeCacheKey] = [
        'events'         => marketplace_fetch_ranked_events('', 8, true),
        'stays'          => marketplace_fetch_properties('', 8, true),
        'transport'      => marketplace_fetch_transport_routes('', 8, true),
        'total_listings' => dbCount("SELECT COUNT(*) FROM listings WHERE is_active = 1"),
        'total_bookings' => dbCount("SELECT COUNT(*) FROM bookings"),
        'total_vendors'  => dbCount("SELECT COUNT(*) FROM users WHERE role <> 'Customer'"),
    ];
}

$featuredEvents    = $_SESSION[$homeCacheKey]['events'];
$featuredStays     = $_SESSION[$homeCacheKey]['stays'];
$featuredTransport = $_SESSION[$homeCacheKey]['transport'];
$totalListings     = $_SESSION[$homeCacheKey]['total_listings'];
$totalBookings     = $_SESSION[$homeCacheKey]['total_bookings'];
$totalVendors      = $_SESSION[$homeCacheKey]['total_vendors'];

// ── Explore / Tours (from tie-explore or tours)
$featuredExplore = [];
if (function_exists('marketplace_fetch_tours')) {
    $featuredExplore = marketplace_fetch_tours('', 8, true);
}

// ── Fallback data ───────────────────────────────────────────────────────
if (empty($featuredEvents)) {
    $featuredEvents = [
        ['id'=>'e1','title'=>'Lake Shore Music Night','location'=>'Mangochi, Lake Malawi','price_label'=>'From MK 20,000','image'=>'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=900&fit=crop&q=80','badge_class'=>'lp-badge-event','type_label'=>'Event','detail_url'=>BASE_URL.'events.php','is_trending'=>true],
        ['id'=>'e2','title'=>'Mzuzu Street Food Festival','location'=>'Mzuzu City','price_label'=>'From MK 12,000','image'=>'https://images.unsplash.com/photo-1541544181051-e46607bc22a4?w=900&fit=crop&q=80','badge_class'=>'lp-badge-event','type_label'=>'Event','detail_url'=>BASE_URL.'events.php','is_trending'=>false],
        ['id'=>'e3','title'=>'Blantyre Live Showcase','location'=>'Blantyre','price_label'=>'From MK 15,000','image'=>'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=900&fit=crop&q=80','badge_class'=>'lp-badge-event','type_label'=>'Event','detail_url'=>BASE_URL.'events.php','is_trending'=>false],
        ['id'=>'e4','title'=>'Lilongwe Weekend Expo','location'=>'Lilongwe','price_label'=>'From MK 10,000','image'=>'https://images.unsplash.com/photo-1511578314322-379afb476865?w=900&fit=crop&q=80','badge_class'=>'lp-badge-event','type_label'=>'Event','detail_url'=>BASE_URL.'events.php','is_trending'=>true],
    ];
}
if (empty($featuredStays)) {
    $featuredStays = [
        ['id'=>'s1','title'=>'Sunbird Waterfront Lodge','location'=>'Lilongwe','price_label'=>'From MK 120,000','image'=>'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=900&fit=crop&q=80','badge_class'=>'lp-badge-stay','type_label'=>'Stay','detail_url'=>BASE_URL.'hotels.php','is_trending'=>false],
        ['id'=>'s2','title'=>'Cape Maclear Beach Retreat','location'=>'Mangochi','price_label'=>'From MK 95,000','image'=>'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=900&fit=crop&q=80','badge_class'=>'lp-badge-stay','type_label'=>'Stay','detail_url'=>BASE_URL.'hotels.php','is_trending'=>true],
        ['id'=>'s3','title'=>'Zomba Plateau Guest House','location'=>'Zomba','price_label'=>'From MK 70,000','image'=>'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=900&fit=crop&q=80','badge_class'=>'lp-badge-stay','type_label'=>'Stay','detail_url'=>BASE_URL.'hotels.php','is_trending'=>false],
        ['id'=>'s4','title'=>'Mzuzu Executive Suites','location'=>'Mzuzu','price_label'=>'From MK 110,000','image'=>'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=900&fit=crop&q=80','badge_class'=>'lp-badge-stay','type_label'=>'Stay','detail_url'=>BASE_URL.'hotels.php','is_trending'=>false],
    ];
}
if (empty($featuredTransport)) {
    $featuredTransport = [
        ['id'=>'t1','title'=>'Lake Shuttle Express','location'=>'Lilongwe - Mangochi','price_label'=>'From MK 18,500','image'=>'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=900&fit=crop&q=80','badge_class'=>'lp-badge-transport','type_label'=>'Transport','detail_url'=>BASE_URL.'transport.php','is_trending'=>true],
        ['id'=>'t2','title'=>'Airport Connect','location'=>'Blantyre Airport','price_label'=>'From MK 22,000','image'=>'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=900&fit=crop&q=80','badge_class'=>'lp-badge-transport','type_label'=>'Transport','detail_url'=>BASE_URL.'transport.php','is_trending'=>false],
        ['id'=>'t3','title'=>'City Ride Express','location'=>'Lilongwe','price_label'=>'From MK 8,000','image'=>'https://images.unsplash.com/photo-1502877338535-766e1452684a?w=900&fit=crop&q=80','badge_class'=>'lp-badge-transport','type_label'=>'Transport','detail_url'=>BASE_URL.'transport.php','is_trending'=>false],
        ['id'=>'t4','title'=>'Southern Route Coaches','location'=>'Blantyre','price_label'=>'From MK 14,000','image'=>'https://images.unsplash.com/photo-1504609813442-a8924e83f76e?w=900&fit=crop&q=80','badge_class'=>'lp-badge-transport','type_label'=>'Transport','detail_url'=>BASE_URL.'transport.php','is_trending'=>false],
    ];
}
if (empty($featuredExplore)) {
    $featuredExplore = [
        ['id'=>'x1','title'=>'Mount Mulanje Hiking Trail','location'=>'Mulanje','price_label'=>'From MK 35,000','image'=>'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=900&fit=crop&q=80','badge_class'=>'lp-badge-explore','type_label'=>'Explore','detail_url'=>BASE_URL.'tie-explore.php','is_trending'=>true],
        ['id'=>'x2','title'=>'Lake Malawi Snorkeling','location'=>'Cape Maclear','price_label'=>'From MK 28,000','image'=>'https://images.unsplash.com/photo-1559827260-dc66d52bef19?w=900&fit=crop&q=80','badge_class'=>'lp-badge-explore','type_label'=>'Explore','detail_url'=>BASE_URL.'tie-explore.php','is_trending'=>false],
        ['id'=>'x3','title'=>'Liwonde Safari Park','location'=>'Liwonde','price_label'=>'From MK 55,000','image'=>'https://images.unsplash.com/photo-1549366021-9f761d040a94?w=900&fit=crop&q=80','badge_class'=>'lp-badge-explore','type_label'=>'Explore','detail_url'=>BASE_URL.'tie-explore.php','is_trending'=>true],
        ['id'=>'x4','title'=>'Nyika Plateau Tour','location'=>'Rumphi','price_label'=>'From MK 42,000','image'=>'https://images.unsplash.com/photo-1516026672322-bc52d61a55d5?w=900&fit=crop&q=80','badge_class'=>'lp-badge-explore','type_label'=>'Explore','detail_url'=>BASE_URL.'tie-explore.php','is_trending'=>false],
    ];
}

// Ensure badge_class uses lp- prefix for proper styling
$normalizeCards = function(array $cards, string $defaultBadge): array {
    return array_map(function($c) use ($defaultBadge) {
        if (!isset($c['badge_class']) || !str_starts_with((string)$c['badge_class'], 'lp-')) {
            $c['badge_class'] = $defaultBadge;
        }
        return $c;
    }, $cards);
};
$featuredEvents    = $normalizeCards($featuredEvents, 'lp-badge-event');
$featuredStays     = $normalizeCards($featuredStays, 'lp-badge-stay');
$featuredTransport = $normalizeCards($featuredTransport, 'lp-badge-transport');
$featuredExplore   = $normalizeCards($featuredExplore, 'lp-badge-explore');

// ── Malawi background slides ───────────────────────────────────────────
$malawiSlides = [];
if (function_exists('uthenga_malawi_featured_cities')) {
    foreach (uthenga_malawi_featured_cities() as $city) {
        if (!empty($city['image'])) $malawiSlides[] = $city['image'];
    }
}
if (count($malawiSlides) < 5) {
    $malawiSlides = array_merge($malawiSlides, [
        'https://images.unsplash.com/photo-1504701954957-2010ec3bcec1?w=1800&fit=crop&q=90',
        'https://images.unsplash.com/photo-1547471080-7cc2caa01a7e?w=1800&fit=crop&q=90',
        'https://images.unsplash.com/photo-1559827260-dc66d52bef19?w=1800&fit=crop&q=90',
        'https://images.unsplash.com/photo-1549366021-9f761d040a94?w=1800&fit=crop&q=90',
        'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1800&fit=crop&q=90',
    ]);
}
$malawiSlides = array_slice(array_values(array_unique($malawiSlides)), 0, 6);

$isGuest   = !isLoggedIn();
$isVendor  = isLoggedIn() && in_array($_SESSION['user_role'] ?? '', VENDOR_ROLES, true);
$isAdmin   = isLoggedIn() && in_array($_SESSION['user_role'] ?? '', ADMIN_ROLES, true);

/* ── Helper: render a card ──────────────────────────────────────────── */
function renderLpCard(array $l, bool $isGuest): string {
    $img    = e($l['image'] ?? '');
    $title  = e($l['title'] ?? '');
    $loc    = e($l['location'] ?? '');
    $price  = e($l['price_label'] ?? '');
    $badge  = e($l['badge_class'] ?? 'lp-badge-event');
    $type   = e($l['type_label'] ?? '');
    $url    = e($l['detail_url'] ?? '#');
    $trend  = !empty($l['is_trending']);
    $id     = e($l['id'] ?? uniqid('lpc'));

    $ctaAttr = $isGuest
        ? 'data-auth-gate="true" href="#"'
        : 'href="' . $url . '"';

    $trendBadge = $trend
        ? '<span class="lp-trending-badge">⭐ Trending</span>'
        : '';

    $ctaLabel = $isGuest ? 'Sign in to Book' : 'View Details';

    return <<<HTML
<article class="lp-card" id="lpc-{$id}">
  <div class="lp-card-img-wrap">
    <img src="{$img}" alt="{$title}" loading="lazy">
    <span class="lp-card-badge {$badge}">{$type}</span>
    {$trendBadge}
  </div>
  <div class="lp-card-body">
    <div class="lp-card-title">{$title}</div>
    <div class="lp-card-loc">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      {$loc}
    </div>
    <div class="lp-card-price">{$price}</div>
  </div>
  <div class="lp-card-footer">
    <a class="lp-card-cta" {$ctaAttr} data-item-url="{$url}">{$ctaLabel}</a>
  </div>
</article>
HTML;
}

?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<script>document.body.classList.add('uthenga-landing');</script>

<!-- ══════════════════════════════════════════════════════════
     HERO SECTION
     ══════════════════════════════════════════════════════════ -->
<section class="lp-hero" aria-label="Uthenga hero">
  <!-- Slideshow background -->
  <div class="lp-hero-slides" aria-hidden="true">
    <?php foreach ($malawiSlides as $i => $url): ?>
      <div class="lp-hero-slide <?= $i === 0 ? 'active' : '' ?>"
           style="background-image:url('<?= e($url) ?>')"></div>
    <?php endforeach; ?>
  </div>
  <div class="lp-hero-overlay" aria-hidden="true"></div>

  <div class="lp-hero-content">
    <div class="lp-hero-inner">
      <!-- Left: text + CTA -->
      <div class="lp-hero-left">
        <div class="lp-hero-eyebrow">
          <span></span>
          Malawi's Travel Marketplace
        </div>
        <h1 class="lp-hero-h1">
          Find <span class="accent">events, stays,</span><br>
          and transport<br>without the clutter.
        </h1>
        <p class="lp-hero-sub">
          Search, compare, and book in a few clear steps. Uthenga keeps the experience simple so people can move from discovery to checkout quickly.
        </p>

        <div class="lp-hero-ctas">
          <?php if ($isGuest): ?>
            <a href="<?= BASE_URL ?>login.php" class="lp-btn-primary" id="hero-cta-signin">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
              Sign In to Book
            </a>
            <a href="<?= BASE_URL ?>register.php" class="lp-btn-ghost" id="hero-cta-register">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
              Register Free
            </a>
          <?php elseif ($isVendor): ?>
            <a href="<?= BASE_URL ?>vendor/dashboard.php" class="lp-btn-primary" id="hero-cta-vendor">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
              Vendor Dashboard
            </a>
          <?php elseif ($isAdmin): ?>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="lp-btn-primary" id="hero-cta-admin">
              Go to Admin
            </a>
          <?php endif; ?>
        </div>

        <!-- Search bar -->
        <form class="lp-search-wrap" method="get" action="<?= BASE_URL ?>index.php" id="lp-search-form">
          <input
            type="search"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Search events, stays, or routes…"
            class="lp-search-input"
            id="lp-search-input"
            aria-label="Search Uthenga marketplace"
          >
          <button type="submit" class="lp-search-btn" id="lp-search-submit">Search</button>
        </form>
      </div>

      <!-- Right: Stat cards -->
      <div class="lp-stats-panel" aria-label="Platform stats">
        <div class="lp-stat-card accent-card">
          <div class="lp-stat-value"><?= number_format($totalListings) ?></div>
          <div class="lp-stat-label">Listings</div>
        </div>
        <div class="lp-stat-card">
          <div class="lp-stat-value"><?= number_format($totalBookings) ?></div>
          <div class="lp-stat-label">Bookings</div>
        </div>
        <div class="lp-stat-card">
          <div class="lp-stat-value"><?= number_format($totalVendors) ?></div>
          <div class="lp-stat-label">Vendors</div>
        </div>
        <div class="lp-stat-card">
          <div class="lp-stat-value">⚡</div>
          <div class="lp-stat-label">Fast Checkout</div>
        </div>
      </div>
    </div><!-- /.lp-hero-inner -->
  </div><!-- /.lp-hero-content -->
</section>

<!-- ══════════════════════════════════════════════════════════
     SERVICE SHORTCUT STRIP
     ══════════════════════════════════════════════════════════ -->
<nav class="lp-shortcuts" aria-label="Service categories">
  <div class="lp-shortcuts-inner">
    <a href="#" class="lp-shortcut active-tab" data-tab="events" id="sc-events">
      <div class="lp-shortcut-icon">🎟️</div>
      <div class="lp-shortcut-text">
        <strong>Events</strong>
        <small>Concerts, festivals &amp; sports</small>
      </div>
    </a>
    <a href="#" class="lp-shortcut" data-tab="stays" id="sc-stays">
      <div class="lp-shortcut-icon">🏨</div>
      <div class="lp-shortcut-text">
        <strong>Stays</strong>
        <small>Hotels, lodges &amp; apartments</small>
      </div>
    </a>
    <a href="#" class="lp-shortcut" data-tab="transport" id="sc-transport">
      <div class="lp-shortcut-icon">🚌</div>
      <div class="lp-shortcut-text">
        <strong>Transport</strong>
        <small>Bus, shuttle &amp; route bookings</small>
      </div>
    </a>
  </div>
</nav>

<?php if ($search !== ''): ?>
<!-- ══════════════════════════════════════════════════════════
     SEARCH RESULTS
     ══════════════════════════════════════════════════════════ -->
<?php
    $searchResults = marketplace_fetch_home_feed($search, 12);
?>
<div class="lp-content">
  <div class="lp-section-header">
    <div>
      <div class="lp-section-title">Search Results</div>
      <div class="lp-section-sub">Showing results for "<?= e($search) ?>"</div>
    </div>
    <a href="<?= BASE_URL ?>index.php" class="lp-view-all">
      ✕ Clear search
    </a>
  </div>
  <?php if (empty($searchResults)): ?>
    <div style="text-align:center;padding:4rem 2rem;background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-lg);">
      <div style="font-size:3rem;margin-bottom:1rem;">🔍</div>
      <h2 style="margin-bottom:0.5rem;font-size:1.4rem;">No results found</h2>
      <p style="color:var(--clr-text-muted);">Try a different keyword or browse a category above.</p>
    </div>
  <?php else: ?>
    <div class="lp-cards-grid">
      <?php foreach (array_slice($searchResults, 0, 12) as $listing): ?>
        <?php
          $listing['badge_class'] = 'lp-badge-' . strtolower(($listing['listing_type'] ?? $listing['type'] ?? 'event'));
          $listing['type_label']  = ucfirst(strtolower($listing['listing_type'] ?? $listing['type'] ?? 'Listing'));
          echo renderLpCard($listing, $isGuest);
        ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php else: ?>

<!-- ══════════════════════════════════════════════════════════
     TAB BAR
     ══════════════════════════════════════════════════════════ -->
<div class="lp-tabs-bar" role="tablist" aria-label="Browse by service">
  <div class="lp-tabs-inner">
    <button type="button" class="lp-tab-btn active" id="tab-btn-events"
            role="tab" aria-selected="true" aria-controls="lp-section-events"
            data-tab="events">
      🎟️ Events
      <span class="lp-tab-count"><?= count($featuredEvents) ?></span>
    </button>
    <button type="button" class="lp-tab-btn" id="tab-btn-stays"
            role="tab" aria-selected="false" aria-controls="lp-section-stays"
            data-tab="stays">
      🏨 Stays
      <span class="lp-tab-count"><?= count($featuredStays) ?></span>
    </button>
    <button type="button" class="lp-tab-btn" id="tab-btn-transport"
            role="tab" aria-selected="false" aria-controls="lp-section-transport"
            data-tab="transport">
      🚌 Transport
      <span class="lp-tab-count"><?= count($featuredTransport) ?></span>
    </button>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     CONTENT PANELS
     ══════════════════════════════════════════════════════════ -->
<div class="lp-content">

  <!-- Events -->
  <div class="lp-section active" id="lp-section-events" role="tabpanel" aria-labelledby="tab-btn-events">
    <div class="lp-section-header">
      <div>
        <div class="lp-section-title">Featured Events</div>
        <div class="lp-section-sub">Concerts, festivals, sports &amp; more across Malawi</div>
      </div>
      <a href="<?= BASE_URL ?>events.php" class="lp-view-all" id="view-all-events">
        View all events →
      </a>
    </div>
    <div class="lp-cards-grid">
      <?php foreach ($featuredEvents as $listing): echo renderLpCard($listing, $isGuest); endforeach; ?>
    </div>
  </div>

  <!-- Stays -->
  <div class="lp-section" id="lp-section-stays" role="tabpanel" aria-labelledby="tab-btn-stays">
    <div class="lp-section-header">
      <div>
        <div class="lp-section-title">Featured Stays</div>
        <div class="lp-section-sub">Hotels, lodges &amp; apartments across Malawi</div>
      </div>
      <a href="<?= BASE_URL ?>hotels.php" class="lp-view-all" id="view-all-stays">
        View all stays →
      </a>
    </div>
    <div class="lp-cards-grid">
      <?php foreach ($featuredStays as $listing): echo renderLpCard($listing, $isGuest); endforeach; ?>
    </div>
  </div>

  <!-- Transport -->
  <div class="lp-section" id="lp-section-transport" role="tabpanel" aria-labelledby="tab-btn-transport">
    <div class="lp-section-header">
      <div>
        <div class="lp-section-title">Featured Transport</div>
        <div class="lp-section-sub">Bus, shuttle &amp; route bookings across Malawi</div>
      </div>
      <a href="<?= BASE_URL ?>transport.php" class="lp-view-all" id="view-all-transport">
        View all routes →
      </a>
    </div>
    <div class="lp-cards-grid">
      <?php foreach ($featuredTransport as $listing): echo renderLpCard($listing, $isGuest); endforeach; ?>
    </div>
  </div>

</div><!-- /.lp-content -->

<!-- ══════════════════════════════════════════════════════════
     WHY UTHENGA
     ══════════════════════════════════════════════════════════ -->
<section class="lp-features-strip" aria-label="Why Uthenga">
  <div class="lp-features-inner">
    <div class="lp-features-eyebrow">Why Uthenga</div>
    <h2 class="lp-features-heading">The simplest way to experience Malawi</h2>
    <p class="lp-features-sub">
      One platform for events, stays, and transport. No clutter, no confusion — just fast, reliable discovery to booking.
    </p>
    <div class="lp-features-grid">
      <div class="lp-feature-item">
        <div class="lp-feature-icon red">🔍</div>
        <div>
          <div class="lp-feature-title">Discover</div>
          <div class="lp-feature-desc">Browse curated listings for events, stays, tours, and transport routes all in one place. No more jumping between sites.</div>
        </div>
      </div>
      <div class="lp-feature-item">
        <div class="lp-feature-icon blue">⚖️</div>
        <div>
          <div class="lp-feature-title">Compare</div>
          <div class="lp-feature-desc">See prices, availability, and reviews side by side. Make informed choices in seconds, not hours.</div>
        </div>
      </div>
      <div class="lp-feature-item">
        <div class="lp-feature-icon green">⚡</div>
        <div>
          <div class="lp-feature-title">Book Instantly</div>
          <div class="lp-feature-desc">Fast checkout with secure payment options. Get your tickets and confirmations delivered instantly to your device.</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════════
     VENDOR CTA (guests only)
     ══════════════════════════════════════════════════════════ -->
<?php if ($isGuest): ?>
<section class="lp-vendor-cta" aria-label="Become a vendor">
  <div class="lp-vendor-cta-inner">
    <div>
      <div class="lp-section-sub" style="color:#e63946;font-weight:700;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.5rem;">For Businesses</div>
      <h2 class="lp-vendor-cta-title">List your service on Uthenga.</h2>
      <p class="lp-vendor-cta-sub">
        Reach customers who are actively searching for events, stays, and transport. Create listings for free and start taking bookings today.
      </p>
    </div>
    <div class="lp-vendor-cta-btns">
      <a href="<?= BASE_URL ?>vendor/register.php" class="lp-btn-primary" id="vendor-cta-btn">
        Become a Vendor
      </a>
      <a href="<?= BASE_URL ?>register.php" class="lp-btn-ghost" id="register-cta-btn" style="background:var(--clr-surface2);color:var(--clr-text);border-color:var(--clr-border2);">
        Register as Customer
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php endif; /* end search vs browse */ ?>

<!-- ══════════════════════════════════════════════════════════
     AUTH GATE MODAL (shown when guest clicks CTA)
     ══════════════════════════════════════════════════════════ -->
<?php if ($isGuest): ?>
<div id="lp-auth-gate-overlay" class="lp-auth-gate-overlay" role="dialog" aria-modal="true" aria-label="Sign in required">
  <div class="lp-auth-gate-card" id="lp-auth-gate-card">
    <button id="lp-auth-gate-close" class="lp-auth-gate-close" aria-label="Close">&times;</button>
    <div class="lp-auth-gate-icon">🔐</div>
    <div class="lp-auth-gate-title">Sign in to continue</div>
    <p class="lp-auth-gate-desc">
      Create a free Uthenga account or sign in to browse full details, check availability, and book with ease.
    </p>
    <div class="lp-auth-gate-btns">
      <a href="<?= BASE_URL ?>login.php" class="btn-signin" id="auth-gate-signin-btn">Sign In</a>
      <div class="lp-auth-gate-divider">— or —</div>
      <a href="<?= BASE_URL ?>register.php" class="btn-register" id="auth-gate-register-btn">Create Free Account</a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     POPUP ADS (preserved from original)
     ══════════════════════════════════════════════════════════ -->
<style>
.uthenga-popup-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.78);backdrop-filter:blur(6px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem;}
.uthenga-popup-card{position:relative;background:var(--clr-surface,#1e1e2d);border:1px solid var(--clr-border,rgba(255,255,255,0.15));border-radius:16px;width:100%;max-width:440px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,0.5);animation:popupSlideIn 0.35s cubic-bezier(0.16,1,0.3,1);}
@keyframes popupSlideIn{from{transform:translateY(20px) scale(0.95);opacity:0}to{transform:translateY(0) scale(1);opacity:1}}
#uthenga-popup-close{position:absolute;top:12px;right:12px;z-index:10;width:32px;height:32px;border-radius:50%;background:rgba(0,0,0,0.5);border:1px solid rgba(255,255,255,0.2);color:#fff;font-size:20px;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.2s ease;}
#uthenga-popup-close:hover{background:#e63946;color:#fff;transform:scale(1.1);}
.uthenga-popup-header{padding:1rem 1.25rem 0.6rem;display:flex;align-items:center;justify-content:space-between;gap:0.5rem;border-bottom:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.02);}
.uthenga-ad-badge{font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:0.05em;background:linear-gradient(135deg,#ff6b35,#f72585);color:#fff;padding:0.2rem 0.55rem;border-radius:20px;}
.uthenga-ad-sponsor{font-size:0.78rem;font-weight:600;color:var(--clr-accent,#ff6b35);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.uthenga-popup-img-wrap{width:100%;height:180px;overflow:hidden;position:relative;background:#000;}
.uthenga-popup-img-wrap img{width:100%;height:100%;object-fit:cover;}
.uthenga-popup-body{padding:1.25rem;}
.uthenga-popup-body h3{font-size:1.2rem;font-weight:700;color:#fff;margin-bottom:0.5rem;line-height:1.3;}
.uthenga-popup-body p{font-size:0.88rem;color:rgba(255,255,255,0.75);margin-bottom:1rem;line-height:1.5;}
.uthenga-popup-vendor-link{font-size:0.78rem;color:rgba(255,255,255,0.5);text-align:center;margin-top:0.75rem;padding-top:0.75rem;border-top:1px dashed rgba(255,255,255,0.1);}
.uthenga-popup-vendor-link a{color:var(--clr-accent,#ff6b35);font-weight:600;text-decoration:underline;}
</style>
<div id="uthenga-popup-overlay" class="uthenga-popup-overlay" role="dialog" aria-modal="true" aria-label="Paid Advertisement" style="display:none;opacity:0;transition:opacity 0.3s ease;">
  <div id="uthenga-popup-card" class="uthenga-popup-card">
    <button id="uthenga-popup-close" aria-label="Close promotion">&times;</button>
    <div class="uthenga-popup-header">
      <span class="uthenga-ad-badge">⭐ Paid Advertisement</span>
      <span id="uthenga-popup-sponsor" class="uthenga-ad-sponsor">Sponsored by Partner</span>
    </div>
    <div id="uthenga-popup-img-wrap" class="uthenga-popup-img-wrap" style="display:none;"><img id="uthenga-popup-img" src="" alt="Paid Advertisement" loading="lazy"></div>
    <div id="uthenga-popup-body" class="uthenga-popup-body">
      <h3 id="uthenga-popup-title"></h3>
      <p id="uthenga-popup-desc"></p>
      <div class="uthenga-popup-actions">
        <a id="uthenga-popup-cta" href="#" class="btn btn-primary" style="width:100%;justify-content:center;text-align:center;">Learn More</a>
        <button id="uthenga-popup-dismiss-btn" class="btn btn-secondary" style="width:100%;justify-content:center;text-align:center;margin-top:0.4rem;background:transparent;border:1px solid rgba(255,255,255,0.2);">Cancel / Close</button>
      </div>
      <div class="uthenga-popup-vendor-link">Are you a Vendor or Business Owner? <a href="<?= BASE_URL ?>vendor/ads.php">Create a Paid Ad</a></div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  /* ── Background slideshow ─────────────────────────────── */
  (function() {
    var slides = document.querySelectorAll('.lp-hero-slide');
    var cur = 0;
    if (slides.length > 1) {
      setInterval(function() {
        slides[cur].classList.remove('active');
        cur = (cur + 1) % slides.length;
        slides[cur].classList.add('active');
      }, 5000);
    }
  })();

  /* ── Tab switching ────────────────────────────────────── */
  var tabBtns    = Array.from(document.querySelectorAll('.lp-tab-btn'));
  var shortcuts  = Array.from(document.querySelectorAll('.lp-shortcut[data-tab]'));
  var sections   = Array.from(document.querySelectorAll('.lp-section'));

  function activateTab(tabId) {
    tabBtns.forEach(function(btn) {
      var isActive = btn.dataset.tab === tabId;
      btn.classList.toggle('active', isActive);
      btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
    shortcuts.forEach(function(sc) {
      sc.classList.toggle('active-tab', sc.dataset.tab === tabId);
    });
    sections.forEach(function(sec) {
      var isActive = sec.id === 'lp-section-' + tabId;
      sec.classList.toggle('active', isActive);
    });
    // Smooth scroll to tabs bar on mobile
    var tabsBar = document.querySelector('.lp-tabs-bar');
    if (tabsBar && window.innerWidth <= 768) {
      tabsBar.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  tabBtns.forEach(function(btn) {
    btn.addEventListener('click', function() { activateTab(btn.dataset.tab); });
  });
  shortcuts.forEach(function(sc) {
    sc.addEventListener('click', function(e) {
      e.preventDefault();
      activateTab(sc.dataset.tab);
    });
  });

  /* ── Auth Gate Modal ──────────────────────────────────── */
  <?php if ($isGuest): ?>
  var authGate  = document.getElementById('lp-auth-gate-overlay');
  var authClose = document.getElementById('lp-auth-gate-close');

  function showAuthGate() {
    if (!authGate) return;
    authGate.classList.add('visible');
  }
  function hideAuthGate() {
    if (!authGate) return;
    authGate.classList.remove('visible');
  }

  // Attach to all auth-gated CTAs
  document.addEventListener('click', function(e) {
    var cta = e.target.closest('[data-auth-gate="true"]');
    if (cta) {
      e.preventDefault();
      showAuthGate();
    }
  });

  if (authClose) authClose.addEventListener('click', hideAuthGate);
  if (authGate) {
    authGate.addEventListener('click', function(e) {
      if (e.target === authGate) hideAuthGate();
    });
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') hideAuthGate();
  });
  <?php endif; ?>

  /* ── Popup Ads ────────────────────────────────────────── */
  var POPUP_INTERVAL_MS = 180000;
  var adPool = [];
  var timerId = null;

  function getRandomAd() {
    if (!adPool || !adPool.length) return null;
    return adPool[Math.floor(Math.random() * adPool.length)];
  }
  function dismissPopup() {
    var overlay = document.getElementById('uthenga-popup-overlay');
    if (overlay) { overlay.style.opacity = '0'; setTimeout(function(){overlay.style.display='none';},300); }
    try { localStorage.setItem('uthenga_ad_cancelled_time', String(Date.now())); } catch(e) {}
    scheduleNextPopup(POPUP_INTERVAL_MS);
  }
  function scheduleNextPopup(ms) {
    if (timerId) clearTimeout(timerId);
    timerId = setTimeout(showRandomPopup, ms);
  }
  function showPopup(data) {
    if (!data) return;
    var overlay = document.getElementById('uthenga-popup-overlay');
    if (!overlay) return;
    var el = function(id){ return document.getElementById(id); };
    if (el('uthenga-popup-title')) el('uthenga-popup-title').textContent = data.title||'';
    if (el('uthenga-popup-desc'))  el('uthenga-popup-desc').textContent  = data.description||'';
    if (el('uthenga-popup-sponsor')) el('uthenga-popup-sponsor').textContent = 'Paid Ad by '+(data.sponsor_name||'Verified Vendor');
    if (el('uthenga-popup-cta')) { el('uthenga-popup-cta').textContent = data.cta_text||'Learn More'; el('uthenga-popup-cta').href = data.cta_url||'#'; }
    var imgW = el('uthenga-popup-img-wrap'), img = el('uthenga-popup-img');
    if (imgW && img && data.image_url) { img.src=data.image_url; imgW.style.display='block'; } else if (imgW) { imgW.style.display='none'; }
    overlay.style.display='flex';
    requestAnimationFrame(function(){requestAnimationFrame(function(){overlay.style.opacity='1';});});
  }
  function showRandomPopup() { var ad = getRandomAd(); if (ad) showPopup(ad); }
  function fetchAdsAndStart() {
    fetch('<?= BASE_URL ?>api/get_active_popup.php')
      .then(function(r){return r.json();})
      .then(function(res){
        if (res && res.items && res.items.length > 0) {
          adPool = res.items;
          if (res.repopup_ms) POPUP_INTERVAL_MS = res.repopup_ms;
          var lastCancelled = 0;
          try { lastCancelled = parseInt(localStorage.getItem('uthenga_ad_cancelled_time')||'0',10); } catch(e) {}
          var elapsed = Date.now() - lastCancelled;
          if (lastCancelled > 0 && elapsed < POPUP_INTERVAL_MS) {
            scheduleNextPopup(POPUP_INTERVAL_MS - elapsed);
          } else {
            setTimeout(showRandomPopup, 3000);
          }
        }
      }).catch(function(){});
  }
  document.addEventListener('DOMContentLoaded', function() {
    var closeBtn   = document.getElementById('uthenga-popup-close');
    var dismissBtn = document.getElementById('uthenga-popup-dismiss-btn');
    var overlay    = document.getElementById('uthenga-popup-overlay');
    if (closeBtn)   closeBtn.addEventListener('click', dismissPopup);
    if (dismissBtn) dismissBtn.addEventListener('click', dismissPopup);
    if (overlay)    overlay.addEventListener('click', function(e){ if(e.target===overlay) dismissPopup(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') dismissPopup(); });
    fetchAdsAndStart();
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
