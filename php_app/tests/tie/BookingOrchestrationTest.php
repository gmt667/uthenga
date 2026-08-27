<?php
/** Phase 10 booking orchestration: no real marketplace or payment call is made. */
require_once __DIR__ . '/../../config.php'; require_once __DIR__ . '/../../db.php'; require_once __DIR__ . '/../../includes/tie/bootstrap.php';
function tie_booking_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException('Assertion failed: ' . $message); }
final class TieBookingTestPlans implements UthengaTiePlanModule {
    public array $plan; public function __construct() { $this->plan = ['plan_id' => 'TIEPLAN-TEST-10', 'lifecycle' => UthengaTiePlanLifecycle::APPROVED, 'trip_summary' => ['origin' => null, 'destination' => 'Lilongwe', 'start_date' => null, 'end_date' => null, 'travellers' => 1], 'activities' => [['activity_id' => 'activity-one', 'service_id' => 'SERVICE-ONE', 'category' => 'event', 'start_at' => '2026-08-01T10:00:00'], ['activity_id' => 'activity-two', 'service_id' => 'SERVICE-TWO', 'category' => 'tour', 'start_at' => '2026-08-01T14:00:00']], 'diagnostics' => ['validation' => ['blocking_conflicts' => 0]]]; }
    public function create(UthengaTiePlanCreateRequest $request, string $userId): UthengaTieTripPlanResult { return new UthengaTieTripPlanResult($this->plan); }
    public function list(string $userId): array { return ['schema_version' => 'tie-trip-plan-list/v1', 'trips' => []]; }
    public function view(string $planId, string $userId): UthengaTieTripPlanResult { return new UthengaTieTripPlanResult($this->plan); }
    public function update(string $planId, string $userId, array $input): UthengaTieTripPlanResult { return new UthengaTieTripPlanResult($this->plan); }
    public function validate(string $planId, string $userId): UthengaTieTripPlanResult { return new UthengaTieTripPlanResult($this->plan); }
    public function approve(string $planId, string $userId): UthengaTieTripPlanResult { return new UthengaTieTripPlanResult($this->plan); }
    public function export(string $planId, string $userId): array { return []; }
}
final class TieBookingTestQuery implements UthengaTieQueryModule { public function findCandidates(UthengaTieTripRequest $request): array { return []; } public function search(UthengaTieCatalogueQuery $criteria): array { return []; } public function vendors(UthengaTieCatalogueQuery $criteria): array { return []; } public function categories(): array { return []; } public function serviceForValidation(string $serviceId): ?UthengaTieVendorCandidate { return null; } }
final class TieBookingTestAvailability implements UthengaTieAvailabilityModule { public function check(UthengaTieVendorCandidate $candidate, UthengaTieTripRequest $request): array { return []; } public function validate(UthengaTieAvailabilityRequest $request): array { return ['eligible' => true]; } public function validateCandidates(array $candidates, UthengaTieAvailabilityRequest $request): array { return []; } }
final class TieBookingTestProvider implements UthengaTieMarketplaceBookingProvider { public array $calls = []; public function name(): string { return 'test_marketplace_provider'; } public function create(array $operation): array { $this->calls[] = $operation; return ['success' => true, 'booking' => ['id' => 'MARKET-' . count($this->calls)]]; } public function cancel(string $bookingId): array { return ['success' => true]; } }

tie_booking_assert(UthengaTieBookingState::transition('PENDING', 'VALIDATING'), 'Booking state permits final validation.');
tie_booking_assert(!UthengaTieBookingState::transition('PENDING', 'BOOKED'), 'Booking state cannot skip validation and execution.');
$contract = UthengaTieBookingContracts::request(['plan_id' => 'TIEPLAN-TEST-10', 'idempotency_key' => 'booking-test-key-0001', 'gateway' => 'airtel', 'payment_reference' => 'reference-not-stored-raw']);
tie_booking_assert($contract->paymentReference !== null, 'Payment reference is accepted only for correlation.');
try { UthengaTieBookingContracts::request(['plan_id' => 'TIEPLAN-TEST-10', 'idempotency_key' => 'short', 'gateway' => 'airtel']); throw new RuntimeException('Invalid idempotency key accepted.'); } catch (UthengaTieException $error) { tie_booking_assert($error->type() === 'validation_error', 'Idempotency key is enforced.'); }

if ($pdo instanceof PDO) {
    $pdo->beginTransaction();
    try {
        $provider = new TieBookingTestProvider(); $engine = new UthengaTieBookingOrchestrator($pdo, new TieBookingTestPlans(), new TieBookingTestQuery(), new TieBookingTestAvailability(), $provider);
        putenv('TIE_BOOKING_LEGACY_IMMEDIATE_CAPTURE_ENABLED=false'); $blocked = $engine->execute($contract, 'BOOKING-TEST-USER')->toArray();
        tie_booking_assert($blocked['status'] === 'FAILED' && ($blocked['diagnostics']['failure'] ?? '') === 'PAYMENT_HANDOFF_REQUIRED', 'Execution fails closed while payment capture is not approved.');
        tie_booking_assert($provider->calls === [], 'Fail-closed payment policy does not invoke marketplace booking.');
        $duplicate = $engine->execute($contract, 'BOOKING-TEST-USER')->toArray(); tie_booking_assert($duplicate['execution_id'] === $blocked['execution_id'], 'Repeated idempotency key returns the original execution.');
        putenv('TIE_BOOKING_LEGACY_IMMEDIATE_CAPTURE_ENABLED=true'); $liveContract = UthengaTieBookingContracts::request(['plan_id' => 'TIEPLAN-TEST-10', 'idempotency_key' => 'booking-test-key-0002', 'gateway' => 'airtel', 'payment_reference' => 'different-reference']);
        $completed = $engine->execute($liveContract, 'BOOKING-TEST-USER')->toArray(); tie_booking_assert($completed['status'] === 'BOOKED', 'Approved, validated plan executes through the marketplace provider adapter.'); tie_booking_assert(count($completed['bookings']) === 2 && count($provider->calls) === 2, 'Each planned activity is independently tracked and executed.'); tie_booking_assert(($completed['diagnostics']['payment_reference_present'] ?? false) === true, 'Execution diagnostics retain only payment-reference presence.');
    } finally { $pdo->rollBack(); putenv('TIE_BOOKING_LEGACY_IMMEDIATE_CAPTURE_ENABLED'); }
}
echo "TIE Phase 10 booking orchestration tests passed.\n";
