<?php
/** Integration coverage for Phase 4's deterministic rules boundary. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_availability_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}
function tie_availability_codes(array $result): array { return array_column($result['violations'], 'rule_code'); }

tie_availability_assert($pdo instanceof PDO, 'A configured database is required for AvailabilityTest.');
$kernel = new UthengaTieKernel();

$event = $kernel->availability->validate(UthengaTieAvailabilityContracts::request([
    'service_id' => 'evt-5', 'start_date' => '2026-08-15', 'quantity' => 2, 'inventory_option' => 'standard',
]));
tie_availability_assert($event['eligible'] === true, 'A future event with enough legacy runtime inventory is eligible.');
tie_availability_assert($event['availability']['validation_status'] === 'validated', 'Event availability is validated against the existing booking fallback source.');
tie_availability_assert($event['revalidation_required'] === true, 'Every result requires final booking revalidation.');

$capacity = $kernel->availability->validate(UthengaTieAvailabilityContracts::request([
    'service_id' => 'evt-5', 'start_date' => '2026-08-15', 'quantity' => 401,
]));
tie_availability_assert($capacity['eligible'] === false && in_array('CAPACITY_EXCEEDED', tie_availability_codes($capacity), true), 'Event capacity rejects an excessive quantity.');

$past = $kernel->availability->validate(UthengaTieAvailabilityContracts::request([
    'service_id' => 'evt-2', 'start_date' => '2026-07-15', 'quantity' => 1,
]));
tie_availability_assert($past['eligible'] === false && in_array('SERVICE_EXPIRED', tie_availability_codes($past), true), 'Past events are rejected deterministically.');

$transport = $kernel->availability->validate(UthengaTieAvailabilityContracts::request([
    'service_id' => 'trans-1', 'start_date' => '2026-08-01', 'origin' => 'Lilongwe', 'destination' => 'Blantyre', 'quantity' => 2,
]));
tie_availability_assert($transport['eligible'] === false && in_array('AVAILABILITY_UNKNOWN', tie_availability_codes($transport), true), 'Transport fails closed without a deployed authoritative seat source.');

$accommodation = $kernel->availability->validate(UthengaTieAvailabilityContracts::request([
    'service_id' => 'acc-2', 'start_date' => '2026-08-01', 'end_date' => '2026-08-03', 'quantity' => 1,
]));
tie_availability_assert($accommodation['eligible'] === false && in_array('AVAILABILITY_UNKNOWN', tie_availability_codes($accommodation), true), 'Accommodation fails closed without date-based room inventory.');

try {
    UthengaTieAvailabilityContracts::request(['service_id' => 'evt-5', 'quantity' => 0]);
    throw new RuntimeException('Invalid quantity was accepted.');
} catch (UthengaTieException $error) {
    tie_availability_assert($error->type() === 'validation_error', 'Quantity validation uses the common error contract.');
}

try {
    UthengaTieAvailabilityContracts::request(['service_id' => 'acc-2', 'start_date' => '2026-08-03', 'end_date' => '2026-08-03']);
    throw new RuntimeException('Invalid accommodation range was accepted.');
} catch (UthengaTieException $error) {
    tie_availability_assert($error->type() === 'validation_error', 'Date-range validation rejects same-day check-in/check-out.');
}

echo "TIE Phase 4 availability tests passed.\n";
