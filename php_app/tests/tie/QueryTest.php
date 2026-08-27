<?php
/** Integration coverage for Phase 3's deployed unified-listings profile. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_query_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

tie_query_assert($pdo instanceof PDO, 'A configured database is required for QueryTest.');
$before = (int) dbCount('SELECT COUNT(*) FROM listings');
$kernel = new UthengaTieKernel();

$started = microtime(true);
$transport = $kernel->query->search(UthengaTieCatalogueContracts::services([
    'origin' => 'Lilongwe', 'destination' => 'Blantyre', 'category' => 'transport', 'availability' => 'available',
]));
$elapsedMs = round((microtime(true) - $started) * 1000, 2);
tie_query_assert(count($transport['candidates']) === 1, 'Route query returns the published Lilongwe to Blantyre transport service.');
$candidate = $transport['candidates'][0];
tie_query_assert($candidate['id'] === 'trans-1', 'Route query returns trans-1.');
tie_query_assert($candidate['price']['amount'] === 18000.0, 'Transport price is normalized from meta.');
tie_query_assert($candidate['availability']['validation_status'] === 'phase_4_required', 'Phase 3 does not claim booking validation.');
tie_query_assert($candidate['source']['profile'] === 'unified_listings_v1', 'Candidate retains source provenance.');

$event = $kernel->query->search(UthengaTieCatalogueContracts::services([
    'category' => 'event', 'date' => '2026-08-15', 'min_price' => 4000, 'max_price' => 6000,
]));
tie_query_assert(count($event['candidates']) === 1 && $event['candidates'][0]['id'] === 'evt-5', 'Date and price filters work together.');

$empty = $kernel->query->search(UthengaTieCatalogueContracts::services(['destination' => 'Nowhere Place']));
tie_query_assert($empty['candidates'] === [], 'Empty results are represented safely.');

$vendors = $kernel->query->vendors(UthengaTieCatalogueContracts::vendors(['category' => 'transport']));
tie_query_assert(count($vendors['vendors']) === 1 && $vendors['vendors'][0]['id'] === 'v-4', 'Vendor aggregation reuses the canonical inventory source.');

$categories = $kernel->query->categories();
tie_query_assert(count($categories['categories']) === 4, 'All live inventory categories are discoverable.');

try {
    UthengaTieCatalogueContracts::services(['category' => 'flight']);
    throw new RuntimeException('Invalid category was accepted.');
} catch (UthengaTieException $error) {
    tie_query_assert($error->type() === 'validation_error', 'Invalid criteria returns a validation error.');
}

try {
    UthengaTieCatalogueContracts::services(['radius_km' => 5]);
    throw new RuntimeException('Unsupported radius filter was accepted.');
} catch (UthengaTieException $error) {
    tie_query_assert($error->type() === 'validation_error', 'Unavailable geospatial capability is explicit.');
}

$after = (int) dbCount('SELECT COUNT(*) FROM listings');
tie_query_assert($after === $before, 'Catalogue reads do not mutate inventory.');
echo "TIE Phase 3 query tests passed ({$elapsedMs} ms for the route query).\n";
