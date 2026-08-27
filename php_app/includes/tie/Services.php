<?php
/** Logical-module interfaces keep dependency direction explicit and testable. */

interface UthengaTieContextModule { public function getForUser(string $userId, string $role): UthengaTieUserContext; }
interface UthengaTieQueryModule { public function findCandidates(UthengaTieTripRequest $request): array; public function search(UthengaTieCatalogueQuery $criteria): array; public function vendors(UthengaTieCatalogueQuery $criteria): array; public function categories(): array; public function serviceForValidation(string $serviceId): ?UthengaTieVendorCandidate; }
interface UthengaTieAvailabilityModule { public function check(UthengaTieVendorCandidate $candidate, UthengaTieTripRequest $request): array; public function validate(UthengaTieAvailabilityRequest $request): array; public function validateCandidates(array $candidates, UthengaTieAvailabilityRequest $request): array; }
interface UthengaTieBudgetModule { public function summarize(array $items, UthengaTieTripRequest $request): array; }
interface UthengaTieConflictModule { public function analyze(array $activities, array $trip, int $maximumDailyActivities, array $budget): array; }
interface UthengaTieRecommendationModule { public function rank(UthengaTieRecommendationRequest $request, UthengaTieTravelContext $context): UthengaTieRecommendationResult; }
interface UthengaTiePlanModule { public function create(UthengaTiePlanCreateRequest $request, string $userId): UthengaTieTripPlanResult; public function list(string $userId): array; public function view(string $planId, string $userId): UthengaTieTripPlanResult; public function update(string $planId, string $userId, array $input): UthengaTieTripPlanResult; public function validate(string $planId, string $userId): UthengaTieTripPlanResult; public function approve(string $planId, string $userId): UthengaTieTripPlanResult; public function export(string $planId, string $userId): array; }
interface UthengaTieBookingModule { public function validate(UthengaTieBookingRequest $request, string $userId): UthengaTieBookingResult; public function execute(UthengaTieBookingRequest $request, string $userId): UthengaTieBookingResult; public function cancel(string $executionId, string $userId): UthengaTieBookingResult; public function status(string $executionId, string $userId): UthengaTieBookingResult; }
interface UthengaTiePaymentModule { public function options(string $planId, string $userId): array; public function start(UthengaTiePaymentIntentRequest $request, string $userId): UthengaTiePaymentIntentResult; public function status(string $intentId, string $userId): UthengaTiePaymentIntentResult; public function receiveWebhook(string $payload, string $signature): array; public function reconcilePending(int $limit = 25): array; }
interface UthengaTieInventoryHoldProvider { public function quote(array $plan, array $selections): array; public function acquire(array $plan, array $quote): array; public function release(string $holdId): void; public function consume(string $holdId, string $paymentIntentId, ?string $bookingId = null): void; }
interface UthengaTieTripPlanningModule { public function createDraft(UthengaTieTripRequest $request, UthengaTieUserContext $context): UthengaTieTripPlan; }
interface UthengaTieLocationModule { public function normalize(UthengaTieLocationContext $location): UthengaTieLocationContext; public function context(UthengaTieLocationRequest $request): UthengaTieLocationContext; }
interface UthengaTieRoutingModule { public function route(UthengaTieLocationContext $origin, UthengaTieLocationContext $destination): UthengaTieRoute; }
interface UthengaTieJourneyModule { public function get(string $journeyId, string $userId): ?UthengaTieJourneyState; }
interface UthengaTieConversationModule { public function chat(UthengaTieAiConversationRequest $request, string $userId): UthengaTieAiConversationResponse; }
interface UthengaTieNotificationModule { public function notify(string $userId, array $message): void; }
interface UthengaTiePromptModule { public function build(string $name, array $context): array; }
interface UthengaTieValidationModule { public function validateResponse(string $contract, array $payload): array; }

final class UthengaTieContextService implements UthengaTieContextModule
{
    public function getForUser(string $userId, string $role): UthengaTieUserContext
    {
        return new UthengaTieUserContext([
            'user_id' => $userId,
            'role' => $role,
            'profile' => [],
            'preferences' => [],
            'booking_history' => [],
            'current_bookings' => [],
            'data_scope' => 'foundation_only',
        ]);
    }
}

final class UthengaTieQueryService implements UthengaTieQueryModule
{
    private UthengaTieCatalogueService $catalogue;

    public function __construct(?PDO $db = null)
    {
        $this->catalogue = new UthengaTieCatalogueService($db);
    }

    public function findCandidates(UthengaTieTripRequest $request): array
    {
        return $this->search(new UthengaTieCatalogueQuery([
            'query' => null, 'destination' => $request->data['destination'], 'origin' => $request->data['origin'],
            'category' => null, 'vendor_id' => null, 'date' => $request->data['start_date'],
            'min_price' => null, 'max_price' => $request->data['budget'], 'availability' => 'all',
            'latitude' => null, 'longitude' => null, 'radius_km' => null, 'page' => 1, 'page_size' => 20,
        ]));
    }

    public function search(UthengaTieCatalogueQuery $criteria): array { return $this->catalogue->services($criteria); }
    public function vendors(UthengaTieCatalogueQuery $criteria): array { return $this->catalogue->vendors($criteria); }
    public function categories(): array { return $this->catalogue->categories(); }
    public function serviceForValidation(string $serviceId): ?UthengaTieVendorCandidate { return $this->catalogue->serviceForValidation($serviceId); }
}

final class UthengaTieAvailabilityService implements UthengaTieAvailabilityModule
{
    private UthengaTieQueryModule $query;
    private UthengaTieAvailabilityEngine $engine;

    public function __construct(UthengaTieQueryModule $query)
    {
        $this->query = $query;
        $this->engine = new UthengaTieAvailabilityEngine();
    }

    public function check(UthengaTieVendorCandidate $candidate, UthengaTieTripRequest $request): array
    {
        $validation = $this->engine->validateCandidate($candidate, new UthengaTieAvailabilityRequest([
            'service_id' => $candidate->data['service_id'], 'quantity' => $request->data['travellers'],
            'start_date' => $request->data['start_date'], 'end_date' => $request->data['end_date'],
            'origin' => $request->data['origin'], 'destination' => $request->data['destination'], 'inventory_option' => 'standard',
        ]));
        return ['status' => $validation['availability']['status'], 'bookable' => $validation['eligible'], 'validation' => $validation];
    }

    public function validate(UthengaTieAvailabilityRequest $request): array
    {
        $candidate = $this->query->serviceForValidation($request->data['service_id']);
        if ($candidate === null) throw new UthengaTieException('not_found', 'The requested service was not found.', 404);
        return $this->engine->validateCandidate($candidate, $request);
    }
    public function validateCandidates(array $candidates, UthengaTieAvailabilityRequest $request): array { return $this->engine->validateCandidates($candidates, $request); }
}

final class UthengaTieLocationService implements UthengaTieLocationModule
{
    private UthengaTieLocationEngine $engine;
    public function __construct(UthengaTieLocationEngine $engine) { $this->engine = $engine; }
    public function normalize(UthengaTieLocationContext $location): UthengaTieLocationContext { return $location; }
    public function context(UthengaTieLocationRequest $request): UthengaTieLocationContext { return $this->engine->context($request); }
    public function engine(): UthengaTieLocationEngine { return $this->engine; }
}

final class UthengaTieRoutingService implements UthengaTieRoutingModule
{
    private UthengaTieRoutingProvider $provider;
    public function __construct(UthengaTieRoutingProvider $provider) { $this->provider = $provider; }
    public function route(UthengaTieLocationContext $origin, UthengaTieLocationContext $destination): UthengaTieRoute
    {
        if (!UthengaTieFeatureFlags::enabled('routing')) throw UthengaTieErrors::featureDisabled('routing');
        return $this->provider->directions($origin, $destination);
    }
}

final class UthengaTieJourneyService implements UthengaTieJourneyModule
{
    public function __construct(private ?PDO $db = null) {}
    public function get(string $journeyId, string $userId): ?UthengaTieJourneyState { return $this->db instanceof PDO ? (new UthengaTieJourneyRepository($this->db))->journey($journeyId, $userId) : null; }
    public function current(string $userId): array { return $this->db instanceof PDO ? (new UthengaTieJourneyRepository($this->db))->current($userId) : ['journeys' => [], 'status' => 'database_unavailable']; }
}

final class UthengaTieNotificationService implements UthengaTieNotificationModule
{
    public function __construct(private ?PDO $db = null) {}
    public function notify(string $userId, array $message): void { if (!($this->db instanceof PDO) || !UthengaTieFeatureFlags::enabled('notifications')) return; (new UthengaTieNotificationOutbox($this->db))->enqueue($userId, $message); UthengaTieMetrics::record('notification_outbox_enqueued', 1, UthengaTieObservability::requestId(), ['module' => 'notifications', 'status' => 'queued']); }
}

final class UthengaTiePromptService implements UthengaTiePromptModule
{
    public function build(string $name, array $context): array
    {
        return ['name' => $name, 'version' => 'v1', 'context' => $context];
    }
}

final class UthengaTieValidationService implements UthengaTieValidationModule
{
    public function validateResponse(string $contract, array $payload): array
    {
        if ($contract === 'trip_plan' && (!isset($payload['id']) || !isset($payload['status']))) {
            throw UthengaTieErrors::validation(['response' => 'Trip plan response does not meet its contract.']);
        }
        return $payload;
    }
}

final class UthengaTieTripPlanningService implements UthengaTieTripPlanningModule
{
    private UthengaTieQueryModule $query;
    private UthengaTieValidationModule $validation;

    public function __construct(UthengaTieQueryModule $query, UthengaTieValidationModule $validation)
    {
        $this->query = $query;
        $this->validation = $validation;
    }

    public function createDraft(UthengaTieTripRequest $request, UthengaTieUserContext $context): UthengaTieTripPlan
    {
        $queryResult = $this->query->findCandidates($request);
        $payload = [
            'id' => 'tie-draft-' . bin2hex(random_bytes(8)),
            'status' => 'draft',
            'request' => $request->toArray(),
            'origin' => $request->data['origin'],
            'destination' => $request->data['destination'],
            'start_date' => $request->data['start_date'],
            'end_date' => $request->data['end_date'],
            'travellers' => $request->data['travellers'],
            'itinerary' => [],
            'transport' => [],
            'accommodation' => [],
            'activities' => [],
            'estimated_cost' => null,
            'currency' => $request->data['currency'],
            'metadata' => [
                'persistence' => 'not_persisted',
                'query_status' => $queryResult['source'],
                'warnings' => $queryResult['warnings'],
                'context_scope' => $context->data['data_scope'],
            ],
        ];
        return new UthengaTieTripPlan($this->validation->validateResponse('trip_plan', $payload));
    }
}

final class UthengaTieLlmGateway
{
    private UthengaTieLlmProvider $provider;
    public function __construct(UthengaTieLlmProvider $provider) { $this->provider = $provider; }
    public function health(): array { return $this->provider->healthCheck(); }
    public function generateStructured(array $request, array $schema): array { return $this->provider->generateStructured($request, $schema); }
    public function provider(): string { return (string) ($this->provider->healthCheck()['provider'] ?? 'unconfigured'); }
}
