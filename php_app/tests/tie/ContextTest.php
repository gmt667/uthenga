<?php
/** Read-only integration coverage for the Phase 6 TravelContext contract. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_context_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

tie_context_assert($pdo instanceof PDO, 'A configured database is required for ContextTest.');
$before = (int) dbCount('SELECT COUNT(*) FROM listings');
$kernel = new UthengaTieKernel();
$request = UthengaTieContextContracts::build([
    'origin' => 'Lilongwe', 'destination' => 'Zomba', 'start_date' => '2026-08-08', 'end_date' => '2026-08-10',
    'travellers' => 2, 'budget' => 300000, 'preferences' => ['culture'],
], 'c-1');
$context = $kernel->travelContext->build('c-1', $request)->toArray();

tie_context_assert($context['schema_version'] === 'travel-context/v1', 'TravelContext is versioned.');
tie_context_assert($context['trip']['user_id'] === 'c-1' && $context['trip']['destination'] === 'Zomba', 'Trusted identity and trip state are normalized.');
tie_context_assert($context['bookings']['count'] === 1, 'Active bookings are normalized from the deployed booking source.');
tie_context_assert($context['candidates']['count'] === 1 && $context['candidates']['eligible'][0]['candidate']['id'] === 'evt-3', 'Query and availability produce only the eligible Zomba event.');
tie_context_assert($context['metadata']['llm_used'] === false && $context['metadata']['recommendation_used'] === false, 'Context build does not invoke AI or ranking.');
tie_context_assert(!isset($context['user']['email']) && !isset($context['user']['name']), 'TravelContext excludes unnecessary user PII.');
tie_context_assert(isset($context['freshness']['candidates']) && isset($context['metadata']['duration_ms']), 'Context components carry freshness and duration metadata.');

$withLocation = UthengaTieContextContracts::build([
    'destination' => 'Zomba', 'start_date' => '2026-08-08', 'travellers' => 1,
    'location' => ['latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 18, 'captured_at' => gmdate(DATE_ATOM), 'source' => 'browser_geolocation', 'permission' => 'granted'],
    'nearby_radius_km' => 5,
], 'c-1');
$locationContext = $kernel->travelContext->build('c-1', $withLocation)->toArray();
tie_context_assert($locationContext['location']['schema_version'] === 'location-context/v1' && $locationContext['location']['metadata']['persistence'] === 'ephemeral_request_only', 'TravelContext embeds the canonical ephemeral LocationContext.');
tie_context_assert($locationContext['location']['geographic_context']['status'] === 'not_configured', 'TravelContext embeds normalized geographic-context status without provider-specific data.');
tie_context_assert($locationContext['candidates']['count'] === 0, 'Unlocated listings cannot appear in location-radius context.');

try {
    UthengaTieContextContracts::build(['travellers' => 1], 'c-1');
    throw new RuntimeException('Context without destination was accepted.');
} catch (UthengaTieException $error) {
    tie_context_assert($error->type() === 'validation_error', 'Required trip context is validated deterministically.');
}

$after = (int) dbCount('SELECT COUNT(*) FROM listings');
tie_context_assert($after === $before, 'Context builds do not mutate marketplace inventory.');
echo "TIE Phase 6 context tests passed.\n";
