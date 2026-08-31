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
$pageStyles = ['assets/css/home.css'];
$search    = trim($_GET['q'] ?? '');

// Session-based cache (10-minute buckets)
$homeCacheKey = 'home_featured_' . floor(time() / 600);
if (!isset($_SESSION[$homeCacheKey])) {
    foreach (array_keys($_SESSION) as $k) {
        if (strpos($k, 'home_featured_') === 0) unset($_SESSION[$k]);
    }
    $_SESSION[$homeCacheKey] = [
        'events'    => marketplace_fetch_ranked_events('', 4, true),
        'stays'     => marketplace_fetch_properties('', 4, true),
        'transport' => marketplace_fetch_transport_routes('', 4, true),
    ];
}

$featuredEvents    = $_SESSION[$homeCacheKey]['events'];
$featuredStays     = $_SESSION[$homeCacheKey]['stays'];
$featuredTransport = $_SESSION[$homeCacheKey]['transport'];

// ── Explore / Tours (from tie-explore or tours)
$featuredExplore = [];

// ── Fallback data ───────────────────────────────────────────────────────
if (false && empty($featuredEvents)) {
    $featuredEvents = [
        ['id'=>'e1','title'=>'Lake Shore Music Night','location'=>'Mangochi, Lake Malawi','price_label'=>'From MK 20,000','image'=>'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=900&fit=crop&q=80','badge_class'=>'lp-badge-event','type_label'=>'Event','detail_url'=>BASE_URL.'events.php','is_trending'=>true],
        ['id'=>'e2','title'=>'Mzuzu Street Food Festival','location'=>'Mzuzu City','price_label'=>'From MK 12,000','image'=>'https://images.unsplash.com/photo-1541544181051-e46607bc22a4?w=900&fit=crop&q=80','badge_class'=>'lp-badge-event','type_label'=>'Event','detail_url'=>BASE_URL.'events.php','is_trending'=>false],
        ['id'=>'e3','title'=>'Blantyre Live Showcase','location'=>'Blantyre','price_label'=>'From MK 15,000','image'=>'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=900&fit=crop&q=80','badge_class'=>'lp-badge-event','type_label'=>'Event','detail_url'=>BASE_URL.'events.php','is_trending'=>false],
        ['id'=>'e4','title'=>'Lilongwe Weekend Expo','location'=>'Lilongwe','price_label'=>'From MK 10,000','image'=>'https://images.unsplash.com/photo-1511578314322-379afb476865?w=900&fit=crop&q=80','badge_class'=>'lp-badge-event','type_label'=>'Event','detail_url'=>BASE_URL.'events.php','is_trending'=>true],
    ];
}
if (false && empty($featuredStays)) {
    $featuredStays = [
        ['id'=>'s1','title'=>'Sunbird Waterfront Lodge','location'=>'Lilongwe','price_label'=>'From MK 120,000','image'=>'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=900&fit=crop&q=80','badge_class'=>'lp-badge-stay','type_label'=>'Stay','detail_url'=>BASE_URL.'hotels.php','is_trending'=>false],
        ['id'=>'s2','title'=>'Cape Maclear Beach Retreat','location'=>'Mangochi','price_label'=>'From MK 95,000','image'=>'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=900&fit=crop&q=80','badge_class'=>'lp-badge-stay','type_label'=>'Stay','detail_url'=>BASE_URL.'hotels.php','is_trending'=>true],
        ['id'=>'s3','title'=>'Zomba Plateau Guest House','location'=>'Zomba','price_label'=>'From MK 70,000','image'=>'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=900&fit=crop&q=80','badge_class'=>'lp-badge-stay','type_label'=>'Stay','detail_url'=>BASE_URL.'hotels.php','is_trending'=>false],
        ['id'=>'s4','title'=>'Mzuzu Executive Suites','location'=>'Mzuzu','price_label'=>'From MK 110,000','image'=>'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=900&fit=crop&q=80','badge_class'=>'lp-badge-stay','type_label'=>'Stay','detail_url'=>BASE_URL.'hotels.php','is_trending'=>false],
    ];
}
if (false && empty($featuredTransport)) {
    $featuredTransport = [
        ['id'=>'t1','title'=>'Lake Shuttle Express','location'=>'Lilongwe - Mangochi','price_label'=>'From MK 18,500','image'=>'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=900&fit=crop&q=80','badge_class'=>'lp-badge-transport','type_label'=>'Transport','detail_url'=>BASE_URL.'transport.php','is_trending'=>true],
        ['id'=>'t2','title'=>'Airport Connect','location'=>'Blantyre Airport','price_label'=>'From MK 22,000','image'=>'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=900&fit=crop&q=80','badge_class'=>'lp-badge-transport','type_label'=>'Transport','detail_url'=>BASE_URL.'transport.php','is_trending'=>false],
        ['id'=>'t3','title'=>'City Ride Express','location'=>'Lilongwe','price_label'=>'From MK 8,000','image'=>'https://images.unsplash.com/photo-1502877338535-766e1452684a?w=900&fit=crop&q=80','badge_class'=>'lp-badge-transport','type_label'=>'Transport','detail_url'=>BASE_URL.'transport.php','is_trending'=>false],
        ['id'=>'t4','title'=>'Southern Route Coaches','location'=>'Blantyre','price_label'=>'From MK 14,000','image'=>'https://images.unsplash.com/photo-1504609813442-a8924e83f76e?w=900&fit=crop&q=80','badge_class'=>'lp-badge-transport','type_label'=>'Transport','detail_url'=>BASE_URL.'transport.php','is_trending'=>false],
    ];
}
if (false && empty($featuredExplore)) {
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
$malawiSlides = array_slice(array_values(array_unique($malawiSlides)), 0, 3);

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
        ? '<span class="lp-trending-badge">' . uthenga_public_icon_svg('star') . ' Trending</span>'
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
    <h3 class="lp-card-title">{$title}</h3>
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
        <h1 class="lp-hero-h1">Find your next experience in Malawi.</h1>
        <p class="lp-hero-sub">
          Discover events, find a stay, plan transport, or build a complete trip from one trusted local marketplace.
        </p>

        <div class="lp-hero-ctas">
          <?php if ($isGuest): ?>
            <a href="#services" class="lp-btn-primary" id="hero-cta-explore"><?= uthenga_public_icon_svg('search') ?> Explore services</a>
            <a href="<?= BASE_URL ?>ai.php#/planner" class="lp-btn-ghost" id="hero-cta-planner"><?= uthenga_public_icon_svg('map') ?> Plan a trip</a>
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
        <form class="lp-search-wrap" method="get" action="<?= BASE_URL ?>index.php" id="lp-search-form" role="search">
          <label for="lp-search-input" class="lp-search-label">Search the marketplace</label>
          <input
            type="search"
            name="q"
            value="<?= e($search) ?>"
            placeholder="Search the marketplace — events, stays, routes…"
            class="lp-search-input"
            id="lp-search-input"
            aria-label="Search Uthenga marketplace"
          >
          <button type="submit" class="lp-search-btn" id="lp-search-submit">Search</button>
        </form>
      </div>

      <figure class="lp-hero-portrait" aria-label="Uthenga travel host">
        <img src="<?= BASE_URL ?>assets/images/uthenga-hero-host.png" alt="Uthenga host wearing a Uthenga shirt" width="1449" height="1085" fetchpriority="high">
      </figure>

    </div><!-- /.lp-hero-inner -->
  </div><!-- /.lp-hero-content -->
</section>

<!-- ══════════════════════════════════════════════════════════
     SERVICE SHORTCUT STRIP
     ══════════════════════════════════════════════════════════ -->
<section class="lp-services" id="services" aria-labelledby="services-title">
  <div class="lp-services-inner">
    <div class="lp-section-intro">
      <p class="lp-section-kicker">Explore Uthenga</p>
      <h2 id="services-title">What do you need today?</h2>
      <p>Choose a service and continue to the relevant search or workspace.</p>
    </div>
    <nav class="lp-service-grid" aria-label="Uthenga services">
      <a href="<?= BASE_URL ?>events.php"><?= uthenga_public_icon_svg('ticket') ?><span><strong>Events</strong><small>Discover events across Malawi</small></span></a>
      <a href="<?= BASE_URL ?>hotels.php"><?= uthenga_public_icon_svg('hotel') ?><span><strong>Stays</strong><small>Find hotels, lodges and rooms</small></span></a>
      <a href="<?= BASE_URL ?>transport.php"><?= uthenga_public_icon_svg('bus') ?><span><strong>Transport</strong><small>Compare routes and departures</small></span></a>
      <a href="<?= BASE_URL ?>ai.php#/driver"><?= uthenga_public_icon_svg('car') ?><span><strong>Quick Taxi</strong><small>Arrange local pickup and travel</small></span></a>
      <a href="<?= BASE_URL ?>shop.php"><?= uthenga_public_icon_svg('shop') ?><span><strong>Shop</strong><small>Browse products for delivery</small></span></a>
      <a href="<?= BASE_URL ?>ai.php#/planner"><?= uthenga_public_icon_svg('map') ?><span><strong>Trip Planner</strong><small>Build a practical Malawi itinerary</small></span></a>
      <a href="<?= BASE_URL ?>tourism.php"><?= uthenga_public_icon_svg('globe') ?><span><strong>Discover Malawi</strong><small>Explore destinations and travel guides</small></span></a>
    </nav>
  </div>
</section>

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
      <h2 class="lp-section-title">Search Results</h2>
      <div class="lp-section-sub">Showing results for "<?= e($search) ?>"</div>
    </div>
    <a href="<?= BASE_URL ?>index.php" class="lp-view-all">
      <?= uthenga_public_icon_svg('x') ?> Clear search
    </a>
  </div>
  <?php if (empty($searchResults)): ?>
    <div style="text-align:center;padding:4rem 2rem;background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-lg);">
      <div class="lp-empty-icon"><?= uthenga_public_icon_svg('search') ?></div>
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
            tabindex="0"
            data-tab="events">
      <?= uthenga_public_icon_svg('ticket') ?> Events
      <span class="lp-tab-count"><?= count($featuredEvents) ?></span>
    </button>
    <button type="button" class="lp-tab-btn" id="tab-btn-stays"
            role="tab" aria-selected="false" aria-controls="lp-section-stays"
            tabindex="-1"
            data-tab="stays">
      <?= uthenga_public_icon_svg('hotel') ?> Stays
      <span class="lp-tab-count"><?= count($featuredStays) ?></span>
    </button>
    <button type="button" class="lp-tab-btn" id="tab-btn-transport"
            role="tab" aria-selected="false" aria-controls="lp-section-transport"
            tabindex="-1"
            data-tab="transport">
      <?= uthenga_public_icon_svg('bus') ?> Transport
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
        <h2 class="lp-section-title">Featured Events</h2>
        <div class="lp-section-sub">Concerts, festivals, sports &amp; more across Malawi</div>
      </div>
      <a href="<?= BASE_URL ?>events.php" class="lp-view-all" id="view-all-events">
        View all events →
      </a>
    </div>
    <?php if ($featuredEvents): ?><div class="lp-cards-grid"><?php foreach ($featuredEvents as $listing): echo renderLpCard($listing, $isGuest); endforeach; ?></div>
    <?php else: ?><div class="lp-empty-state"><?= uthenga_public_icon_svg('calendar') ?><h3>No featured events yet</h3><p>Browse the events directory to see everything currently available.</p><a href="<?= BASE_URL ?>events.php">Explore events</a></div><?php endif; ?>
  </div>

  <!-- Stays -->
  <div class="lp-section" id="lp-section-stays" role="tabpanel" aria-labelledby="tab-btn-stays">
    <div class="lp-section-header">
      <div>
        <h2 class="lp-section-title">Featured Stays</h2>
        <div class="lp-section-sub">Hotels, lodges &amp; apartments across Malawi</div>
      </div>
      <a href="<?= BASE_URL ?>hotels.php" class="lp-view-all" id="view-all-stays">
        View all stays →
      </a>
    </div>
    <?php if ($featuredStays): ?><div class="lp-cards-grid"><?php foreach ($featuredStays as $listing): echo renderLpCard($listing, $isGuest); endforeach; ?></div>
    <?php else: ?><div class="lp-empty-state"><?= uthenga_public_icon_svg('hotel') ?><h3>No featured stays yet</h3><p>Browse accommodation to see properties currently available.</p><a href="<?= BASE_URL ?>hotels.php">Find a stay</a></div><?php endif; ?>
  </div>

  <!-- Transport -->
  <div class="lp-section" id="lp-section-transport" role="tabpanel" aria-labelledby="tab-btn-transport">
    <div class="lp-section-header">
      <div>
        <h2 class="lp-section-title">Featured Transport</h2>
        <div class="lp-section-sub">Bus, shuttle &amp; route bookings across Malawi</div>
      </div>
      <a href="<?= BASE_URL ?>transport.php" class="lp-view-all" id="view-all-transport">
        View all routes →
      </a>
    </div>
    <?php if ($featuredTransport): ?><div class="lp-cards-grid"><?php foreach ($featuredTransport as $listing): echo renderLpCard($listing, $isGuest); endforeach; ?></div>
    <?php else: ?><div class="lp-empty-state"><?= uthenga_public_icon_svg('bus') ?><h3>No featured routes yet</h3><p>Open Transport to search the latest routes and departure options.</p><a href="<?= BASE_URL ?>transport.php">Plan transport</a></div><?php endif; ?>
  </div>

</div><!-- /.lp-content -->

<!-- ══════════════════════════════════════════════════════════
     WHY UTHENGA
     ══════════════════════════════════════════════════════════ -->
<section class="lp-features-strip" aria-label="Why Uthenga">
  <div class="lp-features-inner">
    <div class="lp-features-eyebrow">Why Uthenga</div>
    <h2 class="lp-features-heading">A clear route from search to booking</h2>
    <p class="lp-features-sub">
      Browse current marketplace listings, review the details that matter, and continue through the relevant Uthenga booking flow.
    </p>
    <div class="lp-features-grid">
      <div class="lp-feature-item">
        <div class="lp-feature-icon red"><?= uthenga_public_icon_svg('search') ?></div>
        <div>
          <div class="lp-feature-title">Discover</div>
          <div class="lp-feature-desc">Browse current events, stays, tours, transport and local marketplace services in one place.</div>
        </div>
      </div>
      <div class="lp-feature-item">
        <div class="lp-feature-icon blue"><?= uthenga_public_icon_svg('check') ?></div>
        <div>
          <div class="lp-feature-title">Compare</div>
          <div class="lp-feature-desc">Review location, pricing and service details before choosing the option that suits your plans.</div>
        </div>
      </div>
      <div class="lp-feature-item">
        <div class="lp-feature-icon green"><?= uthenga_public_icon_svg('ticket') ?></div>
        <div>
          <div class="lp-feature-title">Continue securely</div>
          <div class="lp-feature-desc">Sign in and continue through the supported booking and payment flow for the service you selected.</div>
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
<div id="lp-auth-gate-overlay" class="lp-auth-gate-overlay" role="dialog" aria-modal="true" aria-label="Sign in required" aria-hidden="true">
  <div class="lp-auth-gate-card" id="lp-auth-gate-card">
    <button id="lp-auth-gate-close" class="lp-auth-gate-close" aria-label="Close">&times;</button>
    <div class="lp-auth-gate-icon"><?= uthenga_public_icon_svg('lock') ?></div>
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
      <span class="uthenga-ad-badge"><?= uthenga_public_icon_svg('star') ?> Paid Advertisement</span>
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
    if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
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
      btn.tabIndex = isActive ? 0 : -1;
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
    btn.addEventListener('keydown', function(event) {
      if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
      event.preventDefault();
      var current = tabBtns.indexOf(btn);
      var next = event.key === 'Home' ? 0 : event.key === 'End' ? tabBtns.length - 1 : event.key === 'ArrowRight' ? (current + 1) % tabBtns.length : (current - 1 + tabBtns.length) % tabBtns.length;
      tabBtns[next].focus();
      activateTab(tabBtns[next].dataset.tab);
    });
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
  var authReturnFocus = null;

  function showAuthGate() {
    if (!authGate) return;
    authReturnFocus = document.activeElement;
    authGate.classList.add('visible');
    authGate.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (authClose) authClose.focus();
  }
  function hideAuthGate() {
    if (!authGate) return;
    authGate.classList.remove('visible');
    authGate.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (authReturnFocus && authReturnFocus.focus) authReturnFocus.focus();
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
    if (e.key === 'Tab' && authGate && authGate.classList.contains('visible')) {
      var items = Array.from(authGate.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),[tabindex]:not([tabindex="-1"])'));
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
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
