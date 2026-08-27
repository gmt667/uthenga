<?php
/** Phase 9 composition, constraints, lifecycle, persistence, and revalidation coverage. */
require_once __DIR__ . '/../../config.php'; require_once __DIR__ . '/../../db.php'; require_once __DIR__ . '/../../includes/tie/bootstrap.php';
function tie_plan_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException('Assertion failed: ' . $message); }
function tie_plan_item(string $id, string $category, float $score): array { return ['candidate' => ['service_id' => $id, 'title' => $id, 'category' => ['code' => $category], 'location' => ['display_name' => 'Lilongwe'], 'price' => ['amount' => 1000, 'currency' => 'MWK']], 'eligibility' => ['eligible' => true], 'recommendation_score' => ['weighted_score' => $score]]; }

$trip = ['origin' => 'Lilongwe', 'destination' => 'Blantyre', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02', 'travellers' => 1, 'budget' => 100000, 'currency' => 'MWK'];
$composer = new UthengaTieTimelineComposer();
$activities = $composer->compose([tie_plan_item('TRANSPORT', 'transport', 95), tie_plan_item('HOTEL', 'accommodation', 90), tie_plan_item('TOUR', 'tour', 80), tie_plan_item('EVENT', 'event', 70)], $trip, 2);
tie_plan_assert($activities === array_values($activities), 'Timeline activity sequence is a stable array.');
tie_plan_assert($activities[0]['start_at'] <= $activities[count($activities) - 1]['start_at'], 'Timeline is chronological.');
tie_plan_assert(in_array('HOTEL', array_column($activities, 'service_id'), true), 'Accommodation is composed from ranked evidence.');
$issues = (new UthengaTiePlanConstraintEvaluator())->evaluate(array_merge($activities, [$activities[0]]), $trip, 2);
tie_plan_assert(in_array('DUPLICATE_SERVICE', array_column($issues, 'code'), true), 'Duplicate services become explicit conflicts.');
tie_plan_assert(UthengaTiePlanLifecycle::transition(UthengaTiePlanLifecycle::DRAFT, UthengaTiePlanLifecycle::VALIDATED), 'Draft may become validated.');
tie_plan_assert(!UthengaTiePlanLifecycle::transition(UthengaTiePlanLifecycle::DRAFT, UthengaTiePlanLifecycle::APPROVED), 'Draft cannot skip approval workflow.');
$benchmarks = [];
foreach ([10, 50, 100] as $count) { $items = []; for ($index = 0; $index < $count; $index++) $items[] = tie_plan_item('BENCH-' . $index, 'tour', 100 - $index); $started = microtime(true); $composed = $composer->compose($items, $trip, 12); $benchmarks[$count] = round((microtime(true) - $started) * 1000, 3); tie_plan_assert(count($composed) <= 24, "{$count}-candidate plan respects the configured daily activity limit."); }

$contract = UthengaTiePlanContracts::create(['title' => 'Weekend plan', 'destination' => 'Lilongwe', 'travellers' => 1, 'category' => 'accommodation'], 'USR-TEST');
tie_plan_assert($contract->maxDailyActivities >= 1, 'Plan contract applies a bounded planning policy.');
try { UthengaTiePlanContracts::create(['title' => 'x', 'destination' => 'Lilongwe', 'travellers' => 1, 'recommendations' => []], 'USR-TEST'); throw new RuntimeException('Client recommendation evidence was accepted.'); } catch (UthengaTieException $error) { tie_plan_assert($error->type() === 'validation_error', 'Plans reject client-supplied marketplace evidence.'); }

if ($pdo instanceof PDO) {
    $user = $pdo->query('SELECT id FROM users LIMIT 1')->fetch(); tie_plan_assert(is_array($user), 'Configured database has a user for plan integration.');
    $pdo->beginTransaction();
    try {
        $kernel = new UthengaTieKernel(); $request = UthengaTiePlanContracts::create(['title' => 'Phase 9 test plan', 'destination' => 'Lilongwe', 'travellers' => 1, 'category' => 'accommodation', 'limit' => 3], (string) $user['id']);
        $plan = $kernel->plans->create($request, (string) $user['id'])->toArray(); tie_plan_assert($plan['lifecycle'] === 'DRAFT', 'Created plans are proposals, not bookings.');
        tie_plan_assert($plan['provenance']['booking_effect'] === 'none', 'Planning has no booking side effect.');
        $viewed = $kernel->plans->view($plan['plan_id'], (string) $user['id'])->toArray(); tie_plan_assert($viewed['plan_id'] === $plan['plan_id'], 'Plan reads are owner-scoped.');
        $validated = $kernel->plans->validate($plan['plan_id'], (string) $user['id'])->toArray(); tie_plan_assert(in_array($validated['lifecycle'], ['VALIDATED', 'UPDATED'], true), 'Plan validation produces a lifecycle-safe result.');
    } finally { $pdo->rollBack(); }
}
echo 'TIE Phase 9 planning tests passed (composition baseline ms: ' . json_encode($benchmarks) . ").\n";
