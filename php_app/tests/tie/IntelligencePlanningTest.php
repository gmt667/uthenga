<?php
/** Phases 11-14: deterministic budget, conflict, and AI evidence coverage. */
require_once __DIR__ . '/../../config.php'; require_once __DIR__ . '/../../db.php'; require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_intelligence_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException('Assertion failed: ' . $message); }

$trip = new UthengaTieTripRequest([
    'origin' => 'Lilongwe', 'destination' => 'Lake Malawi', 'start_date' => '2026-08-01', 'end_date' => '2026-08-04',
    'travellers' => 2, 'budget' => 200000, 'currency' => 'MWK',
]);
$items = [
    ['service_id' => 'BUS-1', 'title' => 'Lake coach', 'category' => 'transport', 'price' => ['amount' => 20000, 'currency' => 'MWK', 'unit' => 'seat']],
    ['service_id' => 'HOTEL-1', 'title' => 'Lake lodge', 'category' => 'accommodation', 'price' => ['amount' => 50000, 'currency' => 'MWK', 'unit' => 'night']],
    ['service_id' => 'TOUR-1', 'title' => 'Lake cruise', 'category' => 'tour', 'price' => ['amount' => 5000, 'currency' => 'MWK', 'unit' => 'ticket']],
];
$budget = (new UthengaTieBudgetService())->summarize($items, $trip);
tie_intelligence_assert($budget['estimated_total'] === 200000.0, 'Budget estimate applies deterministic nights and per-person quantities.');
tie_intelligence_assert($budget['status'] === 'WITHIN_BUDGET' && $budget['remaining_budget'] === 0.0, 'Exact-budget plans produce an explicit zero remaining amount.');
tie_intelligence_assert($budget['components'][0]['amount'] === 40000.0 && $budget['components'][1]['amount'] === 150000.0, 'Budget components preserve transport and accommodation arithmetic.');
tie_intelligence_assert(in_array('MEAL_ALLOWANCE_NOT_CONFIGURED', array_column($budget['warnings'], 'code'), true), 'Unconfigured allowances are disclosed rather than invented.');

$activities = [
    ['service_id' => 'HOTEL-1', 'category' => 'accommodation', 'start_at' => '2026-08-01T14:00:00+00:00', 'end_at' => '2026-08-01T15:00:00+00:00', 'location' => ['display_name' => 'Lake Malawi']],
    ['service_id' => 'TOUR-1', 'category' => 'tour', 'start_at' => '2026-08-01T14:30:00+00:00', 'end_at' => '2026-08-01T17:00:00+00:00', 'location' => ['display_name' => 'Cape Maclear']],
    ['service_id' => 'TOUR-1', 'category' => 'tour', 'start_at' => '2026-08-01T17:05:00+00:00', 'end_at' => '2026-08-01T18:00:00+00:00', 'location' => ['display_name' => 'Cape Maclear']],
];
$overBudget = $budget; $overBudget['status'] = 'OVER_BUDGET'; $overBudget['shortfall'] = 10000.0;
$conflicts = (new UthengaTieConflictService())->analyze($activities, $trip->data, 3, $overBudget);
$codes = array_column($conflicts['issues'], 'code');
tie_intelligence_assert(in_array('SCHEDULE_OVERLAP', $codes, true), 'Overlapping activities become blocking conflicts.');
tie_intelligence_assert(in_array('DUPLICATE_SERVICE', $codes, true), 'Duplicate activities become explicit conflicts.');
tie_intelligence_assert(in_array('BUDGET_EXCEEDED', $codes, true), 'Budget status becomes a planning conflict.');
foreach ($conflicts['issues'] as $issue) tie_intelligence_assert(isset($issue['resolution']) && $issue['resolution'] !== '', 'Every conflict includes a deterministic resolution.');

$kernel = new UthengaTieKernel();
tie_intelligence_assert($kernel->budget instanceof UthengaTieBudgetService, 'Kernel exposes the deterministic budget service.');
tie_intelligence_assert($kernel->conflicts instanceof UthengaTieConflictService, 'Kernel exposes the deterministic conflict service.');

$request = UthengaTieAiConversationContracts::request(['message' => 'Can I afford this trip?', 'destination' => 'Lake Malawi', 'start_date' => '2026-08-01', 'end_date' => '2026-08-04', 'travellers' => 2, 'budget' => 200000, 'currency' => 'MWK'], 'USR-TEST');
$prompt = (new UthengaTieAiPromptBuilder())->build($request, ['travel_context' => ['trip' => ['destination' => 'Lake Malawi']], 'recommendations' => [], 'budget' => array_intersect_key($budget, array_flip(['status', 'estimated_total', 'budget', 'remaining_budget', 'currency']))], []);
tie_intelligence_assert(isset($prompt['evidence']['budget']['status'], $prompt['evidence']['budget']['estimated_total']), 'AI prompts receive normalized deterministic budget evidence.');
tie_intelligence_assert(!isset($prompt['evidence']['budget']['line_items']), 'AI budget prompts omit internal line-item detail.');

echo "TIE Phases 11-14 intelligence planning tests passed.\n";
