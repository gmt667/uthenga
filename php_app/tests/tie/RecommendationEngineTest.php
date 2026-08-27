<?php
/** Phase 7 deterministic scoring, exclusions, diagnostics, and integration coverage. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_recommendation_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

function tie_recommendation_candidate(string $id, string $category, float $price, int $units, string $location = 'Lilongwe'): array
{
    return [
        'service_id' => $id, 'title' => $id, 'category' => ['code' => $category],
        'vendor' => ['eligibility' => 'eligible'], 'service' => ['lifecycle_status' => 'active'],
        'location' => ['display_name' => $location], 'price' => ['amount' => $price, 'currency' => 'MWK'],
        'availability' => ['declared_units' => $units],
    ];
}

$trip = new UthengaTieTripRequest([
    'user_id' => 'USR-TEST', 'origin' => null, 'destination' => 'Lilongwe', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02',
    'travellers' => 1, 'budget' => 100000.0, 'currency' => 'MWK', 'preferences' => [], 'travel_mode' => 'any',
]);
$contextRequest = new UthengaTieContextBuildRequest($trip, null, null);
$request = new UthengaTieRecommendationRequest($contextRequest, 'accommodation', 10);
$context = new UthengaTieTravelContext([
    'schema_version' => 'travel-context/v1', 'bookings' => ['active' => [['service_id' => 'DUPLICATE']]],
    'candidates' => ['eligible' => [
        ['candidate' => tie_recommendation_candidate('CLOSE', 'accommodation', 50000, 2), 'validation' => ['eligible' => true], 'distance_km' => 2.0],
        ['candidate' => tie_recommendation_candidate('CHEAP', 'accommodation', 20000, 6), 'validation' => ['eligible' => true], 'distance_km' => 10.0],
        ['candidate' => tie_recommendation_candidate('DUPLICATE', 'accommodation', 10000, 8), 'validation' => ['eligible' => true], 'distance_km' => 1.0],
        ['candidate' => tie_recommendation_candidate('WRONG_CATEGORY', 'tour', 10000, 8), 'validation' => ['eligible' => true], 'distance_km' => 1.0],
        ['candidate' => tie_recommendation_candidate('OVER_BUDGET', 'accommodation', 150000, 8), 'validation' => ['eligible' => true], 'distance_km' => 1.0],
    ]],
]);
$result = (new UthengaTieRecommendationService())->rank($request, $context)->toArray();
tie_recommendation_assert($result['schema_version'] === 'recommendation-result/v1', 'Recommendation result is versioned.');
tie_recommendation_assert($result['metadata']['llm_used'] === false && $result['metadata']['persistence'] === 'none', 'Ranking does not use an LLM or persistence.');
tie_recommendation_assert(array_column($result['recommendations'], 'candidate')[0]['service_id'] === 'CHEAP', 'Configured deterministic score orders the cheaper candidate ahead of the closer one.');
tie_recommendation_assert($result['recommendations'][0]['recommendation_score']['weighted_score'] > 0, 'Ranked candidate has a deterministic score.');
tie_recommendation_assert(isset($result['recommendations'][0]['diagnostics']['rule_contributions']['price']), 'Rule contributions are exposed for explanation.');
$exclusions = array_column($result['diagnostics']['excluded'], 'reasons', 'service_id');
tie_recommendation_assert(in_array('DUPLICATE_ACTIVE_BOOKING', $exclusions['DUPLICATE'], true), 'Existing booking duplicate is excluded.');
tie_recommendation_assert(in_array('WRONG_CATEGORY', $exclusions['WRONG_CATEGORY'], true), 'Wrong category is excluded.');
tie_recommendation_assert(in_array('OUTSIDE_BUDGET', $exclusions['OVER_BUDGET'], true), 'Over-budget candidate is excluded.');

$apiInput = ['destination' => 'Lilongwe', 'travellers' => 1, 'category' => 'accommodation', 'limit' => 3];
$contract = UthengaTieRecommendationContracts::request($apiInput, 'USR-TEST');
tie_recommendation_assert($contract->toArray()['schema_version'] === 'recommendation-request/v1', 'Recommendation request is versioned.');
try {
    UthengaTieRecommendationContracts::request($apiInput + ['candidates' => []], 'USR-TEST');
    throw new RuntimeException('Client candidate set was accepted.');
} catch (UthengaTieException $error) {
    tie_recommendation_assert($error->type() === 'validation_error', 'Client candidate set is rejected as server-derived.');
}
try {
    UthengaTieRecommendationContracts::request(array_merge($apiInput, ['category' => 'restaurant']), 'USR-TEST');
    throw new RuntimeException('Unsupported recommendation category was accepted.');
} catch (UthengaTieException $error) {
    tie_recommendation_assert($error->type() === 'validation_error', 'Recommendation category is constrained to deployed inventory.');
}

if ($pdo instanceof PDO) {
    $kernel = new UthengaTieKernel();
    $user = $pdo->query('SELECT id FROM users LIMIT 1')->fetch();
    tie_recommendation_assert(is_array($user), 'Configured database has a user for TravelContext integration.');
    $integrationContract = UthengaTieRecommendationContracts::request($apiInput, (string) $user['id']);
    $integrationContext = $kernel->travelContext->build((string) $user['id'], $integrationContract->contextRequest);
    $integration = $kernel->recommendation->rank($integrationContract, $integrationContext)->toArray();
    tie_recommendation_assert($integration['provenance']['candidate_set'] === 'travel-context/v1', 'Recommendation consumes the TravelContext candidate set.');
}

$benchmarks = [];
foreach ([10, 100, 1000] as $count) {
    $entries = [];
    for ($index = 0; $index < $count; $index++) {
        $entries[] = ['candidate' => tie_recommendation_candidate('BENCH-' . $index, 'accommodation', 1000 + $index, 1 + ($index % 10)), 'validation' => ['eligible' => true], 'distance_km' => (float) ($index % 50)];
    }
    $benchmarkContext = new UthengaTieTravelContext(['schema_version' => 'travel-context/v1', 'bookings' => ['active' => []], 'candidates' => ['eligible' => $entries]]);
    $startedAt = microtime(true);
    $benchmark = (new UthengaTieRecommendationService())->rank($request, $benchmarkContext)->toArray();
    $benchmarks[$count] = round((microtime(true) - $startedAt) * 1000, 3);
    tie_recommendation_assert(count($benchmark['recommendations']) === 10, "{$count}-candidate ranking respects the requested limit.");
}
echo 'TIE Phase 7 recommendation engine tests passed (ranking baseline ms: ' . json_encode($benchmarks) . ").\n";
