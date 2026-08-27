<?php
/** Phase 6.5/6.6 accuracy, freshness, and operation-profile coverage. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_location_quality_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

$unknown = UthengaTieLocationAccuracy::evaluate(null);
tie_location_quality_assert($unknown['classification'] === 'UNKNOWN', 'Missing accuracy is explicitly UNKNOWN.');
$thresholds = UthengaTieLocationAccuracy::evaluate(0)['thresholds_m'];
tie_location_quality_assert($thresholds['excellent'] <= $thresholds['good'] && $thresholds['good'] <= $thresholds['moderate'] && $thresholds['moderate'] <= $thresholds['poor'], 'Accuracy policy loads ascending configured thresholds.');
tie_location_quality_assert(UthengaTieLocationAccuracy::evaluate((float) $thresholds['excellent'])['classification'] === 'EXCELLENT', 'Excellent boundary is inclusive.');
tie_location_quality_assert(UthengaTieLocationAccuracy::evaluate((float) $thresholds['good'])['classification'] === 'GOOD', 'Good boundary is inclusive.');
tie_location_quality_assert(UthengaTieLocationAccuracy::evaluate((float) $thresholds['moderate'])['classification'] === 'MODERATE', 'Moderate boundary is inclusive.');
tie_location_quality_assert(UthengaTieLocationAccuracy::evaluate((float) $thresholds['poor'] + 1)['classification'] === 'POOR', 'Values beyond the poor threshold remain POOR.');
try {
    UthengaTieLocationAccuracy::evaluate(-1.0);
    throw new RuntimeException('Negative accuracy was accepted.');
} catch (UthengaTieException $error) {
    tie_location_quality_assert(($error->details()['fields']['accuracy_m']['code'] ?? null) === 'INVALID_ACCURACY', 'Invalid accuracy has a machine-readable error.');
}

$freshPolicy = UthengaTieLocationFreshness::evaluate(gmdate(DATE_ATOM, 1000), 1000);
tie_location_quality_assert($freshPolicy['classification'] === 'FRESH' && $freshPolicy['reacquisition_required'] === false, 'Current observation is FRESH.');
$freshnessThresholds = $freshPolicy['thresholds_seconds'];
$aging = UthengaTieLocationFreshness::evaluate(gmdate(DATE_ATOM, 1000 - $freshnessThresholds['fresh'] - 1), 1000);
$stale = UthengaTieLocationFreshness::evaluate(gmdate(DATE_ATOM, 1000 - $freshnessThresholds['aging'] - 1), 1000);
$expired = UthengaTieLocationFreshness::evaluate(gmdate(DATE_ATOM, 1000 - $freshnessThresholds['stale'] - 1), 1000);
tie_location_quality_assert($aging['classification'] === 'AGING' && $stale['classification'] === 'STALE', 'Freshness boundary transitions are deterministic.');
tie_location_quality_assert($expired['classification'] === 'EXPIRED' && $expired['reacquisition_required'] === true, 'Expired observations require reacquisition without triggering it.');

tie_location_quality_assert(UthengaTieLocationOperationProfiles::evaluate('nearby_search', 'GOOD', 'AGING')['eligible'] === true, 'Nearby accepts GOOD and AGING observations.');
tie_location_quality_assert(UthengaTieLocationOperationProfiles::evaluate('nearby_search', 'MODERATE', 'FRESH')['eligible'] === false, 'Nearby rejects MODERATE observations.');
tie_location_quality_assert(UthengaTieLocationOperationProfiles::evaluate('live_journey_tracking', 'GOOD', 'FRESH')['eligible'] === false, 'Live tracking requires EXCELLENT accuracy.');

$kernel = new UthengaTieKernel();
$request = UthengaTieLocationContracts::request([
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 18, 'captured_at' => gmdate(DATE_ATOM),
    'source' => 'browser_geolocation', 'permission_state' => 'granted',
]);
$context = $kernel->location->context($request)->toArray();
tie_location_quality_assert(in_array($context['accuracy_classification'], UthengaTieLocationAccuracy::CLASSES, true), 'Canonical DTO contains an accuracy classification.');
tie_location_quality_assert(in_array($context['freshness'], UthengaTieLocationFreshness::STATES, true), 'Canonical DTO contains a freshness classification.');
tie_location_quality_assert(isset($context['metadata']['operation_profiles']['trip_planning']), 'Canonical DTO contains shared operation profiles.');
$manual = UthengaTieLocationContracts::request([
    'latitude' => -13.9626, 'longitude' => 33.7741, 'captured_at' => gmdate(DATE_ATOM),
    'source' => 'manual_location', 'permission_state' => 'NOT_REQUESTED',
]);
$manualContext = $kernel->location->context($manual)->toArray();
tie_location_quality_assert(!isset($manualContext['accuracy_m']) && $manualContext['accuracy_classification'] === 'UNKNOWN', 'A non-device observation without accuracy remains valid but explicitly UNKNOWN.');
echo "TIE Phase 6.5/6.6 location quality tests passed.\n";
