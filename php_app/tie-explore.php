<?php
/** Public, read-only TIE catalogue discovery. It never ranks or books. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/tie/bootstrap.php';

$pageTitle = 'Explore with Uthenga';
$activeNav = 'tie-explore';
$pageStyles = ['assets/css/tie-experience.css'];
define('SKIP_AI_WIDGET', true);
$tieFeatures = UthengaTieFeatureFlags::all();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<section class="tie-experience" aria-labelledby="tie-explore-title">
  <div class="tie-shell">
    <div class="tie-hero">
      <div><div class="tie-eyebrow">Verified Uthenga inventory</div><h1 id="tie-explore-title">Explore what is available.</h1><p>Use Uthenga’s normalized marketplace catalogue to search active, approved services. For personalised ranking and trip planning, use the Trip Planner.</p></div>
      <ul class="tie-trust-list"><li><strong>Current catalogue</strong><br>Published marketplace services only.</li><li><strong>Clear filters</strong><br>Filters are sent to the Query Engine.</li><li><strong>Ready when you are</strong><br>Open a listing for its latest details and booking flow.</li></ul>
    </div>
    <?php if ($tieFeatures['query'] ?? false): ?>
      <div class="tie-panel tie-planner">
        <form id="tie-explore-form">
          <div class="tie-field-grid"><div><label class="tie-field-label" for="tie-explore-query">What are you looking for?</label><input class="tie-input" id="tie-explore-query" name="q" maxlength="120" placeholder="Hotel, tour, event…"></div><div><label class="tie-field-label" for="tie-explore-destination">Destination</label><input class="tie-input" id="tie-explore-destination" name="destination" maxlength="120" placeholder="City or district"></div></div>
          <div class="tie-field-grid"><div><label class="tie-field-label" for="tie-explore-category">Category</label><select class="tie-input" id="tie-explore-category" name="category"><option value="">All categories</option></select></div><div><label class="tie-field-label" for="tie-explore-date">Date <span class="text-muted">(optional)</span></label><input class="tie-input" type="date" id="tie-explore-date" name="date"></div></div>
          <div class="tie-field-grid"><div><label class="tie-field-label" for="tie-explore-max-price">Maximum price (MWK)</label><input class="tie-input" type="number" min="0" id="tie-explore-max-price" name="max_price" placeholder="e.g. 100000"></div><div><label class="tie-field-label" for="tie-explore-availability">Availability</label><select class="tie-input" id="tie-explore-availability" name="availability"><option value="all">All active services</option><option value="available">Available only</option></select></div></div>
          <div class="tie-actions"><a class="tie-button tie-button--quiet" href="<?= BASE_URL ?>ai.php#/planner">Plan with Uthenga</a><button class="tie-button" type="submit" id="tie-explore-submit">Search verified services</button></div>
        </form>
        <div class="tie-alert" id="tie-explore-alert" role="alert"></div>
      </div>
      <section class="tie-results is-visible" aria-live="polite"><div class="tie-results-heading"><div><div class="tie-eyebrow">Catalogue results</div><h2>Available Uthenga services</h2><p id="tie-explore-copy">Loading current catalogue…</p></div></div><div class="tie-card-grid" id="tie-explore-results"></div></section>
    <?php else: ?>
      <div class="tie-empty">TIE catalogue search is unavailable in this environment. You can still browse <a href="<?= BASE_URL ?>events.php">events</a>, <a href="<?= BASE_URL ?>hotels.php">stays</a>, <a href="<?= BASE_URL ?>tours.php">tours</a>, and <a href="<?= BASE_URL ?>transport.php">transport</a>.</div>
    <?php endif; ?>
  </div>
</section>
<script>window.UthengaTieUiConfig = <?= json_encode(['baseUrl' => BASE_URL, 'csrfToken' => $_SESSION['csrf_token'] ?? '', 'authenticated' => isLoggedIn(), 'features' => $tieFeatures], JSON_UNESCAPED_SLASHES) ?>;</script>
<?php if ($tieFeatures['query'] ?? false): ?>
<script src="<?= BASE_URL ?>assets/js/tie-client.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
<script>
(function () {
  'use strict';
  var client = window.UthengaTieClient.create(window.UthengaTieUiConfig), form = document.getElementById('tie-explore-form'), results = document.getElementById('tie-explore-results'), copy = document.getElementById('tie-explore-copy'), alertBox = document.getElementById('tie-explore-alert'), category = document.getElementById('tie-explore-category'), submit = document.getElementById('tie-explore-submit');
  function text(value) { return String(value || '').replace(/\s+/g, ' ').trim(); }
  function money(value, currency) { return new Intl.NumberFormat('en-MW', { style: 'currency', currency: currency || 'MWK', maximumFractionDigits: 0 }).format(Number(value || 0)); }
  function url(candidate) { var type = candidate.category && candidate.category.code || ''; return window.UthengaTieUiConfig.baseUrl + 'event-details.php?listing_id=' + encodeURIComponent(candidate.service_id || '') + '&listing_type=' + encodeURIComponent(type === 'property' ? 'accommodation' : type); }
  function alert(message) { alertBox.textContent = message; alertBox.className = 'tie-alert is-visible is-error'; }
  function card(candidate) { var item = document.createElement('article'); item.className = 'tie-result-card'; if (candidate.media && candidate.media.primary_image) { var image = document.createElement('img'); image.className = 'tie-result-image'; image.src = candidate.media.primary_image; image.alt = ''; image.loading = 'lazy'; item.appendChild(image); } var body = document.createElement('div'); body.className = 'tie-result-body'; var meta = document.createElement('div'); meta.className = 'tie-card-meta'; meta.textContent = candidate.category && (candidate.category.label || candidate.category.code) || 'Marketplace service'; var title = document.createElement('h3'); title.textContent = text(candidate.title); var location = document.createElement('p'); location.className = 'tie-result-location'; location.textContent = text(candidate.location && candidate.location.display_name); var price = document.createElement('p'); price.className = 'tie-result-price'; price.textContent = candidate.price && candidate.price.amount !== null && candidate.price.amount !== undefined ? money(candidate.price.amount, candidate.price.currency) : 'Price on listing'; var footer = document.createElement('div'); footer.className = 'tie-card-footer'; var status = document.createElement('span'); status.className = 'tie-availability'; status.textContent = candidate.vendor && candidate.vendor.eligibility === 'eligible' ? 'Published service' : 'Review listing'; var link = document.createElement('a'); link.className = 'tie-button tie-button--quiet tie-button--small'; link.href = url(candidate); link.textContent = 'View'; footer.appendChild(status); footer.appendChild(link); body.appendChild(meta); body.appendChild(title); if (location.textContent) body.appendChild(location); body.appendChild(price); body.appendChild(footer); item.appendChild(body); return item; }
  function search() { var values = new FormData(form), query = { page: 1, page_size: 18 }; ['q', 'destination', 'category', 'date', 'max_price', 'availability'].forEach(function (key) { var value = text(values.get(key)); if (value) query[key] = value; }); submit.disabled = true; submit.textContent = 'Searching…'; alertBox.className = 'tie-alert'; return client.services(query).then(function (response) { var candidates = response.data && response.data.candidates || []; results.innerHTML = ''; if (!candidates.length) { var empty = document.createElement('div'); empty.className = 'tie-empty'; empty.textContent = 'No active Uthenga services matched these filters. Try a broader search or plan a trip for personalised recommendations.'; results.appendChild(empty); } candidates.forEach(function (candidate) { results.appendChild(card(candidate)); }); copy.textContent = candidates.length + ' active Uthenga service' + (candidates.length === 1 ? '' : 's') + ' found.'; }).catch(function (error) { alert(error.message || 'Catalogue search is unavailable.'); copy.textContent = 'Search could not be completed.'; }).finally(function () { submit.disabled = false; submit.textContent = 'Search verified services'; }); }
  client.categories().then(function (response) { ((response.data && response.data.categories) || []).forEach(function (item) { var option = document.createElement('option'); option.value = item.code; option.textContent = item.label + (typeof item.service_count === 'number' ? ' (' + item.service_count + ')' : ''); category.appendChild(option); }); }).catch(function () {});
  form.addEventListener('submit', function (event) { event.preventDefault(); search(); }); search();
}());
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
