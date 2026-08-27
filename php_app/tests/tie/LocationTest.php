<?php
/** Read-only Phase 5 integration and deterministic location tests. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_location_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

tie_location_assert($pdo instanceof PDO, 'A configured database is required for LocationTest.');
$before = (int) dbCount('SELECT COUNT(*) FROM listings');
$kernel = new UthengaTieKernel();

$location = UthengaTieLocationContracts::request([
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 18,
    'captured_at' => gmdate(DATE_ATOM), 'source' => 'browser_geolocation', 'permission' => 'granted',
]);
$context = $kernel->location->context($location);
tie_location_assert($context instanceof UthengaTieLocationContext && $context->data['confidence'] === 'HIGH', 'Fresh, accurate, consented browser location is a high-confidence canonical DTO.');
tie_location_assert($context->data['schema_version'] === 'location-context/v1' && $context->data['metadata']['persistence'] === 'ephemeral_request_only', 'Location DTO is versioned and not persisted.');
tie_location_assert($context->data['geographic_context']['status'] === 'not_configured', 'Unconfigured geocoder does not invalidate coordinates.');

tie_location_assert(UthengaTieLocationEngine::distanceKm(-13.9626, 33.7741, -13.9626, 33.7741) === 0.0, 'Same coordinates have zero distance.');
tie_location_assert(UthengaTieLocationEngine::distanceKm(-13.9626, 33.7741, -15.7861, 35.0058) > 100, 'Haversine distance is calculated as geographic distance.');

try {
    UthengaTieLocationContracts::request(['latitude' => 91, 'longitude' => 33, 'captured_at' => gmdate(DATE_ATOM)]);
    throw new RuntimeException('Invalid latitude was accepted.');
} catch (UthengaTieException $error) {
    tie_location_assert($error->type() === 'validation_error', 'Invalid coordinates are rejected.');
}
try {
    UthengaTieLocationContracts::request(['latitude' => -13, 'longitude' => 33, 'source' => 'browser_geolocation', 'permission' => 'granted', 'captured_at' => gmdate(DATE_ATOM)]);
    throw new RuntimeException('GPS without accuracy was accepted.');
} catch (UthengaTieException $error) {
    tie_location_assert($error->type() === 'validation_error', 'Device location requires accuracy metadata.');
}

$oldLocation = UthengaTieLocationContracts::request([
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 18,
    'captured_at' => gmdate(DATE_ATOM, time() - 7200), 'source' => 'browser_geolocation', 'permission' => 'granted',
]);
tie_location_assert($kernel->location->context($oldLocation)->data['usable_for_nearby'] === false, 'Expired location cannot drive nearby search.');

$nearby = UthengaTieNearbyContracts::request([
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 18,
    'captured_at' => gmdate(DATE_ATOM), 'source' => 'browser_geolocation', 'permission' => 'granted',
    'radius_km' => 5, 'category' => 'event', 'date' => '2026-08-15', 'quantity' => 1,
]);
$result = $kernel->nearby->search($nearby);
tie_location_assert($result['results'] === [], 'No unlocated listing is presented as nearby.');
tie_location_assert($result['excluded']['missing_coordinates'] === 0, 'The indexed radius prefilter excludes unlocated rows before validation.');

$after = (int) dbCount('SELECT COUNT(*) FROM listings');
tie_location_assert($after === $before, 'Location context and nearby search do not mutate listings.');
echo "TIE Phase 5 location tests passed.\n";
