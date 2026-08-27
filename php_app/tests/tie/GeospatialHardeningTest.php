<?php
/** Phase 6.14 quality, stale policy, deterministic ordering, and index coverage. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_geospatial_hardening_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

tie_geospatial_hardening_assert($pdo instanceof PDO, 'A configured database is required for geospatial hardening tests.');
$listing = dbQueryOne("SELECT l.id FROM listings l INNER JOIN users u ON u.id = l.vendor_id WHERE l.is_active = 1 AND u.is_approved = 1 AND u.account_status = 'active' LIMIT 1");
tie_geospatial_hardening_assert(is_array($listing), 'An eligible listing is required.');
$now = gmdate('Y-m-d H:i:s');
$old = gmdate('Y-m-d H:i:s', strtotime(UthengaTieVendorLocationQuality::staleCutoff() . ' -1 day'));

tie_geospatial_hardening_assert(UthengaTieVendorLocationQuality::assess(null, null, 'unverified', null, null)['state'] === 'MISSING', 'No coordinate is MISSING.');
tie_geospatial_hardening_assert(UthengaTieVendorLocationQuality::assess(-13.9, null, 'verified', $now, $now)['state'] === 'INVALID', 'Partial coordinates are INVALID.');
tie_geospatial_hardening_assert(UthengaTieVendorLocationQuality::assess(-13.9, 33.7, 'pending_review', $now, null)['state'] === 'UNVERIFIED', 'Pending coordinates are UNVERIFIED.');
tie_geospatial_hardening_assert(UthengaTieVendorLocationQuality::assess(-13.9, 33.7, 'verified', $now, $now)['state'] === 'VERIFIED', 'Fresh reviewed coordinates are VERIFIED.');
tie_geospatial_hardening_assert(UthengaTieVendorLocationQuality::assess(-13.9, 33.7, 'verified', $old, $old)['state'] === 'STALE', 'Old reviewed coordinates are STALE.');
tie_geospatial_hardening_assert(UthengaTieVendorCoordinateGovernance::source('imported', true) === 'imported', 'Administrators may use the controlled import source.');
try {
    UthengaTieVendorCoordinateGovernance::source('imported', false);
    throw new RuntimeException('A vendor was allowed to use the import source.');
} catch (UthengaTieException $error) {
    tie_geospatial_hardening_assert($error->type() === 'authorization_error', 'Import source is administrator-only.');
}

$tie = static fn(string $id, int $units): array => ['distance_km' => 1.0, 'candidate' => ['service_id' => $id, 'availability' => ['declared_units' => $units]]];
tie_geospatial_hardening_assert(UthengaTieNearbySearchService::compareResults($tie('B', 2), $tie('A', 8)) > 0, 'Higher availability wins an equal-distance tie.');
tie_geospatial_hardening_assert(UthengaTieNearbySearchService::compareResults($tie('B', 8), $tie('A', 8)) > 0, 'Service id resolves a full tie.');
tie_geospatial_hardening_assert(UthengaTieNearbySearchService::compareResults(array_merge($tie('B', 99), ['distance_km' => 0.9]), $tie('A', 1)) < 0, 'Distance remains the primary order.');

$pdo->beginTransaction();
try {
    $listingId = (string) $listing['id'];
    $update = $pdo->prepare("UPDATE listings SET gps_lat = ?, gps_lng = ?, location_captured_at = ?, location_verified_at = ?, location_verification_status = 'verified' WHERE id = ?");
    $update->execute([-13.9626, 33.7741, $now, $now, $listingId]);
    $criteria = UthengaTieCatalogueContracts::services(['latitude' => -13.9626, 'longitude' => 33.7741, 'radius_km' => 5, 'page' => 1, 'page_size' => 20]);
    $query = new UthengaTieQueryService($pdo);
    $startedAt = microtime(true);
    $freshSearch = $query->search($criteria);
    $benchmarkMs = round((microtime(true) - $startedAt) * 1000, 3);
    tie_geospatial_hardening_assert(in_array($listingId, array_column($freshSearch['candidates'], 'service_id'), true), 'Fresh verified coordinate participates in radius search.');
    $update->execute([-13.9626, 33.7741, $old, $old, $listingId]);
    tie_geospatial_hardening_assert(!in_array($listingId, array_column($query->search($criteria)['candidates'], 'service_id'), true), 'Stale coordinate is excluded from precision search.');
    $plan = $pdo->prepare("EXPLAIN SELECT id FROM listings WHERE location_verification_status = 'verified' AND COALESCE(location_verified_at, location_captured_at) >= ? AND gps_lat BETWEEN ? AND ? AND gps_lng BETWEEN ? AND ?");
    $plan->execute([UthengaTieVendorLocationQuality::staleCutoff(), -14.1, -13.8, 33.5, 34.0]);
    $row = $plan->fetch();
    tie_geospatial_hardening_assert(str_contains((string) ($row['possible_keys'] ?? ''), 'idx_listings_geo_verified'), 'The verified-coordinate index is available to the radius query plan.');
    $pdo->rollBack();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
echo "TIE Phase 6.14 geospatial hardening tests passed (single-radius baseline: {$benchmarkMs} ms).\n";
