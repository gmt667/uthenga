<?php
/** Phase 7: deterministic, explainable recommendation and ranking boundary. */

final class UthengaTieRecommendationRequest
{
    public const SCHEMA_VERSION = 'recommendation-request/v1';
    public UthengaTieContextBuildRequest $contextRequest;
    public ?string $category;
    public int $limit;
    public function __construct(UthengaTieContextBuildRequest $contextRequest, ?string $category, int $limit)
    {
        $this->contextRequest = $contextRequest; $this->category = $category; $this->limit = $limit;
    }
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'trip' => $this->contextRequest->trip->toArray(), 'category' => $this->category, 'limit' => $this->limit,
            'nearby_radius_km' => $this->contextRequest->nearbyRadiusKm,
            'location_supplied' => $this->contextRequest->location !== null,
        ];
    }
}

final class UthengaTieRecommendationResult
{
    public const SCHEMA_VERSION = 'recommendation-result/v1';
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieRecommendationContracts
{
    private const FIELDS = ['destination', 'origin', 'start_date', 'end_date', 'travellers', 'budget', 'currency', 'preferences', 'travel_mode', 'location', 'nearby_radius_km', 'category', 'limit', 'csrf_token'];

    public static function request(array $input, string $userId): UthengaTieRecommendationRequest
    {
        $unknown = array_values(array_diff(array_keys($input), self::FIELDS));
        if ($unknown) throw UthengaTieErrors::validation(['request' => 'Unsupported recommendation field(s): ' . implode(', ', $unknown) . '. Candidates and marketplace facts are server-derived.']);
        $contextInput = array_intersect_key($input, array_flip(['destination', 'origin', 'start_date', 'end_date', 'travellers', 'budget', 'currency', 'preferences', 'travel_mode', 'location', 'nearby_radius_km']));
        $contextRequest = UthengaTieContextContracts::build($contextInput, $userId);
        $category = strtolower(trim((string) ($input['category'] ?? '')));
        if ($category === 'property') $category = 'accommodation';
        if ($category === '') $category = null;
        $supported = array_column(UthengaTieCatalogueContracts::supportedCategories(), 'code');
        if ($category !== null && !in_array($category, $supported, true)) throw UthengaTieErrors::validation(['category' => 'Recommendation category must be one of the deployed marketplace categories.']);
        $limit = $input['limit'] ?? 10;
        if (!filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]])) throw UthengaTieErrors::validation(['limit' => 'Recommendation limit must be an integer from 1 to 20.']);
        return new UthengaTieRecommendationRequest($contextRequest, $category, (int) $limit);
    }
}

final class UthengaTieRecommendationPolicy
{
    public const VERSION = 'deterministic-ranking/v1';
    public static function configuration(): array
    {
        $weights = [
            'distance' => self::weight('TIE_RECOMMENDATION_DISTANCE_WEIGHT', 20),
            'availability' => self::weight('TIE_RECOMMENDATION_AVAILABILITY_WEIGHT', 20),
            'price' => self::weight('TIE_RECOMMENDATION_PRICE_WEIGHT', 15),
            'category' => self::weight('TIE_RECOMMENDATION_CATEGORY_WEIGHT', 15),
            'date' => self::weight('TIE_RECOMMENDATION_DATE_WEIGHT', 10),
            'vendor' => self::weight('TIE_RECOMMENDATION_VENDOR_WEIGHT', 10),
            'context' => self::weight('TIE_RECOMMENDATION_CONTEXT_WEIGHT', 10),
        ];
        $total = array_sum($weights);
        if ($total <= 0) throw new UthengaTieException('configuration_error', 'Recommendation weights must have a positive total.', 500);
        return [
            'version' => UthengaTieConfig::string('TIE_RECOMMENDATION_VERSION', self::VERSION),
            'weights' => $weights, 'weight_total' => $total,
            'distance_reference_km' => max(1.0, UthengaTieConfig::decimal('TIE_RECOMMENDATION_DISTANCE_REFERENCE_KM', 50.0)),
            'price_reference' => max(1.0, UthengaTieConfig::decimal('TIE_RECOMMENDATION_PRICE_REFERENCE', 100000.0)),
        ];
    }
    private static function weight(string $key, float $default): float
    {
        $value = UthengaTieConfig::decimal($key, $default);
        if ($value < 0) throw new UthengaTieException('configuration_error', 'Recommendation weights cannot be negative.', 500);
        return $value;
    }
}

final class UthengaTieRecommendationService implements UthengaTieRecommendationModule
{
    public function rank(UthengaTieRecommendationRequest $request, UthengaTieTravelContext $context): UthengaTieRecommendationResult
    {
        $started = microtime(true); $policy = UthengaTieRecommendationPolicy::configuration();
        $trip = $request->contextRequest->trip->data;
        $bookedServiceIds = array_flip(array_map(static fn(array $booking): string => (string) $booking['service_id'], $context->data['bookings']['active'] ?? []));
        $ranked = []; $excluded = [];
        foreach (($context->data['candidates']['eligible'] ?? []) as $entry) {
            $candidate = $entry['candidate'] ?? [];
            $serviceId = (string) ($candidate['service_id'] ?? '');
            $reasons = $this->exclude($candidate, $entry['validation'] ?? [], $trip, $request->category, $bookedServiceIds);
            if ($reasons) { $excluded[] = ['service_id' => $serviceId, 'reasons' => $reasons]; continue; }
            $scored = $this->score($candidate, $entry, $trip, $request->category, $policy);
            $ranked[] = [
                'candidate' => $candidate, 'eligibility' => $entry['validation'], 'location' => $candidate['location'] ?? [], 'availability' => $candidate['availability'] ?? [],
                'recommendation_score' => $scored['score'], 'explanation' => $scored['explanation'], 'diagnostics' => $scored['diagnostics'],
            ];
        }
        usort($ranked, static function (array $a, array $b): int {
            $score = $b['recommendation_score']['weighted_score'] <=> $a['recommendation_score']['weighted_score'];
            if ($score !== 0) return $score;
            $aDistance = $a['diagnostics']['distance_km'] ?? INF; $bDistance = $b['diagnostics']['distance_km'] ?? INF;
            $distance = $aDistance <=> $bDistance;
            if ($distance !== 0) return $distance;
            $aUnits = is_numeric($a['availability']['declared_units'] ?? null) ? (int) $a['availability']['declared_units'] : -1;
            $bUnits = is_numeric($b['availability']['declared_units'] ?? null) ? (int) $b['availability']['declared_units'] : -1;
            if ($aUnits !== $bUnits) return $bUnits <=> $aUnits;
            return strcmp((string) $a['candidate']['service_id'], (string) $b['candidate']['service_id']);
        });
        $ranked = array_slice($ranked, 0, $request->limit);
        return new UthengaTieRecommendationResult([
            'schema_version' => UthengaTieRecommendationResult::SCHEMA_VERSION,
            'recommendations' => $ranked,
            'metadata' => [
                'ranking_version' => $policy['version'], 'limit' => $request->limit,
                'ordering' => ['recommendation_score' => 'descending', 'distance' => 'ascending_on_score_tie', 'availability' => 'declared_units_descending_on_distance_tie', 'service_id' => 'ascending_on_full_tie'],
                'llm_used' => false, 'persistence' => 'none',
            ],
            'diagnostics' => [
                'input_candidate_count' => count($context->data['candidates']['eligible'] ?? []), 'recommended_count' => count($ranked),
                'excluded_count' => count($excluded), 'excluded' => $excluded, 'policy' => $policy,
                'duration_ms' => round((microtime(true) - $started) * 1000, 2),
            ],
            'provenance' => ['candidate_set' => 'travel-context/v1', 'modules' => ['context', 'query', 'availability', 'location', 'recommendation'], 'marketplace_facts' => 'server_authoritative'],
        ]);
    }

    private function exclude(array $candidate, array $validation, array $trip, ?string $category, array $bookedServiceIds): array
    {
        $reasons = []; $serviceId = (string) ($candidate['service_id'] ?? '');
        if (($validation['eligible'] ?? false) !== true) $reasons[] = 'FAILED_BUSINESS_RULES';
        if (($candidate['vendor']['eligibility'] ?? null) !== 'eligible') $reasons[] = 'VENDOR_INELIGIBLE';
        if (($candidate['service']['lifecycle_status'] ?? null) !== 'active') $reasons[] = 'SERVICE_INACTIVE';
        if ($category !== null && ($candidate['category']['code'] ?? null) !== $category) $reasons[] = 'WRONG_CATEGORY';
        if (isset($bookedServiceIds[$serviceId])) $reasons[] = 'DUPLICATE_ACTIVE_BOOKING';
        $price = $candidate['price']['amount'] ?? null;
        if ($trip['budget'] !== null && is_numeric($price) && (float) $price > (float) $trip['budget']) $reasons[] = 'OUTSIDE_BUDGET';
        return array_values(array_unique($reasons));
    }

    private function score(array $candidate, array $entry, array $trip, ?string $category, array $policy): array
    {
        $distance = is_numeric($entry['distance_km'] ?? null) ? (float) $entry['distance_km'] : null;
        $price = is_numeric($candidate['price']['amount'] ?? null) ? (float) $candidate['price']['amount'] : null;
        $destination = strtolower((string) ($trip['destination'] ?? ''));
        $displayLocation = strtolower((string) ($candidate['location']['display_name'] ?? ''));
        $signals = [
            'distance' => $distance === null ? 0.5 : max(0.0, 1.0 - ($distance / $policy['distance_reference_km'])),
            'availability' => 1.0,
            'price' => $this->priceSignal($price, $trip['budget'], $policy['price_reference']),
            'category' => $category === null ? 0.5 : 1.0,
            'date' => $trip['start_date'] === null ? 0.5 : 1.0,
            'vendor' => 1.0,
            'context' => ($destination !== '' && $displayLocation !== '' && (str_contains($displayLocation, $destination) || str_contains($destination, $displayLocation))) ? 1.0 : 0.5,
        ];
        $contributions = []; $weighted = 0.0;
        foreach ($signals as $name => $value) {
            $contribution = $value * $policy['weights'][$name]; $weighted += $contribution;
            $contributions[$name] = ['signal' => round($value, 4), 'weight' => $policy['weights'][$name], 'contribution' => round($contribution, 4)];
        }
        $score = round(($weighted / $policy['weight_total']) * 100, 2);
        return [
            'score' => ['raw_score' => round($weighted, 4), 'weighted_score' => $score, 'scale' => '0_to_100'],
            'explanation' => $this->explanation($distance, $price, $trip['budget'], $category, $trip['start_date']),
            'diagnostics' => ['ranking_version' => $policy['version'], 'rule_contributions' => $contributions, 'distance_km' => $distance, 'price_known' => $price !== null, 'policy_applied' => $policy['version']],
        ];
    }

    private function priceSignal(?float $price, ?float $budget, float $reference): float
    {
        if ($price === null) return 0.5;
        $base = $budget !== null && $budget > 0 ? $budget : $reference;
        return max(0.0, min(1.0, 1.0 - ($price / $base)));
    }

    private function explanation(?float $distance, ?float $price, ?float $budget, ?string $category, ?string $startDate): array
    {
        $items = [
            ['code' => 'AVAILABLE', 'message' => 'Available under the current business rules.'],
            ['code' => 'VENDOR_APPROVED', 'message' => 'Provided by an approved active vendor.'],
        ];
        if ($distance !== null) $items[] = ['code' => 'GEOGRAPHIC_DISTANCE', 'message' => 'Located ' . number_format($distance, 3, '.', '') . ' km away by straight-line geographic distance.'];
        if ($price !== null && ($budget === null || $price <= $budget)) $items[] = ['code' => 'PRICE_COMPATIBLE', 'message' => 'Price is compatible with the current budget context.'];
        if ($category !== null) $items[] = ['code' => 'CATEGORY_MATCH', 'message' => 'Matches the requested marketplace category.'];
        if ($startDate !== null) $items[] = ['code' => 'DATE_COMPATIBLE', 'message' => 'Passed validation for the requested travel date context.'];
        return $items;
    }
}
