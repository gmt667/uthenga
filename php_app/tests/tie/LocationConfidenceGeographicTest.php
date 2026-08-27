<?php
/** Phase 6.7/6.8 confidence and normalized geographic-context coverage. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_location_confidence_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

final class TieTestGeocoder implements UthengaTieGeocodingProvider
{
    public function reverse(float $latitude, float $longitude): array { return ['country' => 'Malawi', 'region' => 'Central Region', 'city' => 'Lilongwe', 'area' => 'Capital City', 'unused' => 'ignored']; }
    public function name(): string { return 'test_geocoder'; }
}

final class TieFailingGeocoder implements UthengaTieGeocodingProvider
{
    public function reverse(float $latitude, float $longitude): array { throw UthengaTieErrors::providerUnavailable('test_geocoder'); }
    public function name(): string { return 'test_geocoder_failure'; }
}

$high = UthengaTieLocationConfidence::evaluate('EXCELLENT', 'FRESH', 'browser_geolocation', 'GRANTED', true);
tie_location_confidence_assert($high['classification'] === 'HIGH' && $high['policy_version'] === 'v1', 'Fresh, excellent, consented browser location is HIGH confidence.');
$medium = UthengaTieLocationConfidence::evaluate('MODERATE', 'AGING', 'manual_location', 'NOT_REQUESTED', true);
tie_location_confidence_assert($medium['classification'] === 'MEDIUM', 'Validated moderate manual location is MEDIUM confidence.');
$low = UthengaTieLocationConfidence::evaluate('POOR', 'STALE', 'vendor_location', 'NOT_REQUESTED', true);
tie_location_confidence_assert($low['classification'] === 'LOW', 'Poor or stale context is LOW confidence.');
$unknown = UthengaTieLocationConfidence::evaluate('UNKNOWN', 'FRESH', 'manual_location', 'NOT_REQUESTED', true);
tie_location_confidence_assert($unknown['classification'] === 'UNKNOWN', 'Incomplete quality metadata is UNKNOWN confidence.');
tie_location_confidence_assert(UthengaTieLocationConfidence::operation('routing', 'MEDIUM')['confidence_accepted'] === false, 'Routing requires HIGH confidence.');
tie_location_confidence_assert(UthengaTieLocationConfidence::operation('nearby_search', 'MEDIUM')['confidence_accepted'] === true, 'Nearby search accepts MEDIUM confidence.');

$request = UthengaTieLocationContracts::request([
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 10, 'captured_at' => gmdate(DATE_ATOM),
    'source' => 'browser_geolocation', 'permission_state' => 'granted',
]);
$engine = new UthengaTieLocationEngine(new TieTestGeocoder());
$location = $engine->context($request)->toArray();
tie_location_confidence_assert($location['confidence'] === 'HIGH', 'Canonical context exposes confidence classification.');
tie_location_confidence_assert($location['metadata']['operation_profiles']['nearby_search']['confidence_accepted'] === true, 'Operation profiles consume confidence policy.');
tie_location_confidence_assert($location['geographic_context']['country'] === 'Malawi' && $location['geographic_context']['city'] === 'Lilongwe', 'Provider response is normalized into geographic context.');
tie_location_confidence_assert($location['geographic_context']['district'] === null && $location['geographic_context']['provenance']['provider'] === 'test_geocoder', 'Unavailable administrative fields remain null with provenance retained.');

$failed = (new UthengaTieLocationEngine(new TieFailingGeocoder()))->context($request)->toArray();
tie_location_confidence_assert($failed['geographic_context']['status'] === 'provider_unavailable', 'Provider failure does not invalidate location context.');
$hierarchy = UthengaTieGeographicNormalizer::normalize(['country' => 'Malawi', 'province' => 'Central Region', 'county' => 'Lilongwe District', 'municipality' => 'Lilongwe', 'suburb' => 'Area 3', 'display_name' => 'Area 3, Lilongwe'], 'mapping_test', 'disabled');
tie_location_confidence_assert($hierarchy['schema_version'] === 'geographic-context/v1' && $hierarchy['region'] === 'Central Region' && $hierarchy['district'] === 'Lilongwe District' && $hierarchy['city'] === 'Lilongwe' && $hierarchy['area'] === 'Area 3', 'Provider-specific hierarchy fields normalize into canonical levels.');
$fallback = (new UthengaTieReverseGeocodingService([new TieFailingGeocoder(), new TieTestGeocoder()]))->resolve(-13.9626, 33.7741);
tie_location_confidence_assert($fallback['status'] === 'resolved' && $fallback['provider'] === 'test_geocoder', 'Reverse geocoding service falls back through provider abstraction.');
echo "TIE Phase 6.7/6.8 confidence and geographic-context tests passed.\n";
