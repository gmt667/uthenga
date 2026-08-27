<?php
/** Dependency-free TIE contract tests. Run: php php_app/tests/tie/ContractTest.php */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_test_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function tie_test_throws(callable $operation, string $type): void
{
    try {
        $operation();
    } catch (UthengaTieException $error) {
        tie_test_assert($error->type() === $type, 'Expected ' . $type . ', got ' . $error->type());
        return;
    }
    throw new RuntimeException('Expected exception ' . $type);
}

$request = UthengaTieContracts::tripRequest([
    'origin' => 'Lilongwe', 'destination' => 'Blantyre',
    'start_date' => '2026-08-01', 'end_date' => '2026-08-03',
    'travellers' => 2, 'budget' => 250000,
    'preferences' => ['family', 'comfortable'],
], 'user-1');
tie_test_assert($request->data['user_id'] === 'user-1', 'Trip request must use the trusted user ID.');
tie_test_assert($request->data['destination'] === 'Blantyre', 'Destination was not preserved.');
tie_test_throws(function () { UthengaTieContracts::tripRequest(['destination' => 'Lilongwe', 'start_date' => 'invalid'], 'user-1'); }, 'validation_error');
tie_test_throws(function () { UthengaTieContracts::locationContext(['latitude' => -13.9]); }, 'validation_error');

$kernel = new UthengaTieKernel();
$context = $kernel->context->getForUser('user-1', ROLE_CUSTOMER);
$plan = $kernel->tripPlanning->createDraft($request, $context);
tie_test_assert($plan->data['status'] === 'draft', 'Foundation planner must only create drafts.');
tie_test_assert($plan->data['itinerary'] === [], 'Foundation planner must not generate an itinerary.');
tie_test_assert($plan->data['metadata']['query_status'] === 'unified_listings_v1', 'Trip drafts must use the Phase 3 canonical inventory boundary.');
echo "TIE contract tests passed\n";
