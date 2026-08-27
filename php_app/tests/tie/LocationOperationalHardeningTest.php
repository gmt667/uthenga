<?php
/** Phase 6.17 provider, server-authority, rate-limit, and fallback coverage. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../includes/tie/Api.php';

function tie_location_operational_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

$observation = [
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 15,
    'captured_at' => gmdate(DATE_ATOM), 'source' => 'browser_geolocation', 'permission' => 'granted',
    'radius_km' => 5, 'category' => 'event', 'quantity' => 1,
];
$request = UthengaTieNearbyContracts::request($observation);
tie_location_operational_assert($request instanceof UthengaTieNearbySearchRequest, 'Observational nearby request is accepted.');
try {
    UthengaTieNearbyContracts::request($observation + ['distance_km' => 0.1]);
    throw new RuntimeException('Client-supplied marketplace fact was accepted.');
} catch (UthengaTieException $error) {
    tie_location_operational_assert($error->type() === 'validation_error', 'Client marketplace facts are rejected.');
    tie_location_operational_assert(isset($error->details()['fields']['server_authority']), 'Rejection identifies the server-authority policy.');
}

tie_location_operational_assert($pdo instanceof PDO, 'Configured database is required for geographic provider coverage.');
$kernel = new UthengaTieKernel();
$architecture = UthengaTieLocationProviderArchitecture::describe(new UthengaTieForegroundClientGeolocationProvider(), new UthengaTieMariaDbGeographicSearchProvider($kernel->query));
tie_location_operational_assert($architecture['schema_version'] === 'location-provider-architecture/v1', 'Provider architecture is versioned.');
tie_location_operational_assert($architecture['geolocation']['tracking'] === 'not_supported', 'Geolocation architecture does not enable tracking.');
tie_location_operational_assert($architecture['geographic_search']['provider'] === 'mariadb_verified_coordinate_search', 'Geographic search remains behind the MariaDB adapter.');

$fallback = UthengaTieLocationFailureHandling::geographicSearchUnavailable();
tie_location_operational_assert($fallback['nearby_available'] === false && $fallback['booking_blocked'] === false, 'Geographic provider failure does not block marketplace use.');
tie_location_operational_assert(in_array('catalogue_search', $fallback['alternatives'], true), 'Geographic provider fallback offers catalogue search.');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$bucket = 'phase_617_' . bin2hex(random_bytes(4));
UthengaTieApi::requireRateLimit($bucket, 1, 60, 'tie-phase-617-test');
try {
    UthengaTieApi::requireRateLimit($bucket, 1, 60, 'tie-phase-617-test');
    throw new RuntimeException('Rate limit was not enforced.');
} catch (UthengaTieException $error) {
    tie_location_operational_assert($error->type() === 'rate_limited', 'Endpoint rate-limit policy is enforced.');
}
echo "TIE Phase 6.17 location operational hardening tests passed.\n";
