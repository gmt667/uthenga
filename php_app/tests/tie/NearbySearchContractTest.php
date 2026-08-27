<?php
/** Phase 6.15 public nearby-search request/response contract coverage. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_nearby_contract_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

$input = [
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 18,
    'captured_at' => gmdate(DATE_ATOM), 'source' => 'browser_geolocation', 'permission' => 'granted',
    'radius_km' => 5, 'category' => 'accommodation', 'date' => '2026-08-01', 'quantity' => 2, 'availability' => 'available',
];
$request = UthengaTieNearbyContracts::request($input);
$serialized = $request->toArray();
tie_nearby_contract_assert($serialized['schema_version'] === 'nearby-search-request/v1', 'Nearby request is versioned.');
tie_nearby_contract_assert($serialized['category'] === 'accommodation' && $serialized['radius_km'] === 5.0, 'Nearby request reuses normalized query criteria.');
tie_nearby_contract_assert(($serialized['query_filters']['availability'] ?? null) === 'available', 'Phase 3 filters are retained in the request contract.');
tie_nearby_contract_assert(array_column(UthengaTieNearbyCategoryRegistry::supported(), 'code') === ['event', 'accommodation', 'tour', 'transport'], 'Category registry reflects deployed marketplace inventory.');

try {
    UthengaTieNearbyContracts::request($input + ['unrecognized_filter' => 'x']);
    throw new RuntimeException('Unknown nearby filter was accepted.');
} catch (UthengaTieException $error) {
    tie_nearby_contract_assert($error->type() === 'validation_error', 'Unknown nearby filters fail before retrieval.');
}
try {
    UthengaTieNearbyContracts::request(array_merge($input, ['category' => 'restaurant']));
    throw new RuntimeException('Unsupported category was accepted.');
} catch (UthengaTieException $error) {
    tie_nearby_contract_assert($error->type() === 'validation_error', 'Unsupported categories are rejected.');
}

$raw = [
    'candidate' => ['service_id' => 'LST-42', 'category' => ['code' => 'accommodation'], 'location' => ['coordinate_status' => 'listing_coordinates', 'quality' => 'VERIFIED']],
    'distance_km' => 3.2, 'validation' => ['eligible' => true], 'provenance' => ['system' => 'uthenga'],
];
$result = UthengaTieNearbySearchResult::fromRaw($raw);
tie_nearby_contract_assert($result['distance'] === ['value_km' => 3.2, 'type' => 'GEOGRAPHIC', 'unit' => 'km'], 'Distance is explicitly geographic and unit-bearing.');
tie_nearby_contract_assert($result['location']['quality'] === 'VERIFIED' && $result['validation']['eligible'] === true, 'Result retains location and Phase 4 validation contracts.');

if ($pdo instanceof PDO) {
    $kernel = new UthengaTieKernel();
    $response = $kernel->nearby->search($request);
    tie_nearby_contract_assert($response['schema_version'] === 'nearby-search-response/v1', 'Service exposes the versioned response contract.');
    tie_nearby_contract_assert($response['distance_semantics']['type'] === 'GEOGRAPHIC', 'Response declares geographic distance semantics.');
    tie_nearby_contract_assert(($response['metadata']['category_registry'] ?? null) === UthengaTieNearbyCategoryRegistry::PROFILE, 'Response identifies the deployed category registry.');
}
echo "TIE Phase 6.15 nearby-search contract tests passed.\n";
