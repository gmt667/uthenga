<?php
/** Phase 6: deterministic, versioned TravelContext aggregation. */

final class UthengaTieTravelContext
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieContextBuildRequest
{
    public UthengaTieTripRequest $trip;
    public ?UthengaTieLocationRequest $location;
    public ?float $nearbyRadiusKm;
    public function __construct(UthengaTieTripRequest $trip, ?UthengaTieLocationRequest $location, ?float $nearbyRadiusKm) { $this->trip = $trip; $this->location = $location; $this->nearbyRadiusKm = $nearbyRadiusKm; }
}

final class UthengaTieContextContracts
{
    public static function build(array $input, string $userId): UthengaTieContextBuildRequest
    {
        $trip = UthengaTieContracts::tripRequest($input, $userId);
        $location = null;
        if (isset($input['location'])) {
            if (!is_array($input['location'])) throw UthengaTieErrors::validation(['location' => 'Location must be an object.']);
            $location = UthengaTieLocationContracts::request($input['location']);
        }
        $radius = $input['nearby_radius_km'] ?? null;
        if ($radius === '' || $radius === null) $radius = null;
        if ($radius !== null && (!is_numeric($radius) || (float) $radius <= 0 || (float) $radius > 500)) throw UthengaTieErrors::validation(['nearby_radius_km' => 'Nearby radius must be greater than zero and at most 500 km.']);
        if ($radius !== null && $location === null) throw UthengaTieErrors::validation(['nearby_radius_km' => 'Nearby radius requires a location object.']);
        return new UthengaTieContextBuildRequest($trip, $location, $radius === null ? null : (float) $radius);
    }
}

/** Raw SQL remains within this read-only repository. */
final class UthengaTieContextRepository
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function user(string $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, role, notifications_enabled, push_notify, email_notify, sms_notify, updated_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!is_array($row)) return null;
        return [
            'id' => (string) $row['id'], 'role' => (string) $row['role'], 'preferred_language' => null,
            'preferred_currency' => APP_CURRENCY,
            'notification_preferences' => ['enabled' => (bool) $row['notifications_enabled'], 'push' => (bool) $row['push_notify'], 'email' => (bool) $row['email_notify'], 'sms' => (bool) $row['sms_notify']],
            'source' => ['table' => 'users', 'updated_at' => $row['updated_at']],
        ];
    }

    public function activeBookings(string $userId): array
    {
        $stmt = $this->db->prepare("SELECT id, listing_id, listing_title, listing_type, booking_date, details, currency, total_price, payment_status, booking_status, confirmed_at, updated_at FROM bookings WHERE customer_id = ? AND LOWER(COALESCE(booking_status, '')) NOT IN ('cancelled', 'refunded', 'failed', 'expired') ORDER BY COALESCE(confirmed_at, booking_date) DESC LIMIT 20");
        $stmt->execute([$userId]);
        $bookings = [];
        foreach ($stmt->fetchAll() as $row) {
            $details = json_decode((string) ($row['details'] ?? '{}'), true);
            $details = is_array($details) ? $details : [];
            $bookings[] = [
                'id' => (string) $row['id'], 'service_id' => (string) $row['listing_id'], 'title' => (string) $row['listing_title'], 'category' => (string) $row['listing_type'],
                'booking_date' => $row['booking_date'], 'status' => strtolower((string) $row['booking_status']), 'payment_status' => strtolower((string) $row['payment_status']),
                'currency' => $row['currency'] ?: APP_CURRENCY, 'total_price' => (float) $row['total_price'],
                'travel_details' => array_filter(['quantity' => $details['quantity'] ?? null, 'check_in_date' => $details['check_in_date'] ?? null, 'check_out_date' => $details['check_out_date'] ?? null, 'tour_date' => $details['tour_date'] ?? null]),
                'source' => ['table' => 'bookings', 'updated_at' => $row['updated_at']],
            ];
        }
        return $bookings;
    }

    public function activeTrip(string $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, destination, days, budget_mk, created_at FROM trip_planner_sessions WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!is_array($row)) return null;
        return ['id' => (string) $row['id'], 'destination' => $row['destination'], 'days' => $row['days'] === null ? null : (int) $row['days'], 'budget' => $row['budget_mk'] === null ? null : (float) $row['budget_mk'], 'currency' => APP_CURRENCY, 'source' => ['table' => 'trip_planner_sessions', 'created_at' => $row['created_at']]];
    }
}

final class UthengaTieContextCache
{
    public static function get(string $key): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return null;
        $entry = $_SESSION['tie_context_cache'][$key] ?? null;
        return is_array($entry) && ($entry['expires_at'] ?? 0) >= time() ? $entry['value'] : null;
    }
    public static function put(string $key, array $value, int $ttl): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || $ttl <= 0) return;
        $_SESSION['tie_context_cache'][$key] = ['expires_at' => time() + $ttl, 'value' => $value];
    }
}

final class UthengaTieTravelContextService
{
    private ?UthengaTieContextRepository $repository;
    private UthengaTieQueryModule $query;
    private UthengaTieAvailabilityModule $availability;
    private UthengaTieLocationModule $location;

    public function __construct(?PDO $db, UthengaTieQueryModule $query, UthengaTieAvailabilityModule $availability, UthengaTieLocationModule $location)
    {
        $this->repository = $db instanceof PDO ? new UthengaTieContextRepository($db) : null;
        $this->query = $query; $this->availability = $availability; $this->location = $location;
    }

    public function build(string $userId, UthengaTieContextBuildRequest $request): UthengaTieTravelContext
    {
        if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database');
        $started = microtime(true); $warnings = []; $cache = [];
        [$user, $userCache] = $this->cachedUser($userId); $cache['user'] = $userCache;
        if ($user === null) throw new UthengaTieException('not_found', 'The authenticated user was not found.', 404);
        $bookings = $this->repository->activeBookings($userId);
        $activeTrip = $this->repository->activeTrip($userId);
        $location = $request->location === null ? null : $this->location->context($request->location);
        if ($location !== null && !$location->data['usable_for_nearby']) $warnings[] = $this->rule('LOCATION_NOT_USABLE', 'warning', 'The supplied location is not fresh or accurate enough for nearby filtering.');

        $catalogue = $this->catalogue($request, $location);
        $eligible = [];
        foreach ($catalogue['candidates'] as $candidate) {
            $availabilityRequest = new UthengaTieAvailabilityRequest([
                'service_id' => $candidate['service_id'], 'quantity' => $request->trip->data['travellers'],
                'start_date' => $request->trip->data['start_date'], 'end_date' => $request->trip->data['end_date'],
                'origin' => $request->trip->data['origin'], 'destination' => $request->trip->data['destination'], 'inventory_option' => 'standard',
            ]);
            $validation = $this->availability->validateCandidates([$candidate], $availabilityRequest)[0];
            if (!$validation['eligible']) continue;
            $entry = ['candidate' => $candidate, 'validation' => $validation, 'availability_checked_at' => $validation['checked_at']];
            if ($location !== null && ($candidate['location']['latitude'] ?? null) !== null) $entry['distance_km'] = round(UthengaTieLocationEngine::distanceKm((float) $location->data['latitude'], (float) $location->data['longitude'], (float) $candidate['location']['latitude'], (float) $candidate['location']['longitude']), 3);
            $eligible[] = $entry;
        }
        if ($eligible === []) $warnings[] = $this->rule('EMPTY_ELIGIBLE_CANDIDATE_SET', 'warning', 'No candidate passed the current deterministic validation rules.');
        usort($eligible, static fn(array $a, array $b): int => ($a['distance_km'] ?? INF) <=> ($b['distance_km'] ?? INF));

        $builtAt = gmdate('c');
        return new UthengaTieTravelContext([
            'schema_version' => 'travel-context/v1', 'context_id' => 'tie-context-' . bin2hex(random_bytes(8)), 'built_at' => $builtAt,
            'user' => $user, 'trip' => $request->trip->toArray(), 'active_trip' => $activeTrip,
            'bookings' => ['active' => $bookings, 'count' => count($bookings), 'retrieved_at' => $builtAt],
            'time' => ['utc_now' => $builtAt, 'application_timezone' => date_default_timezone_get(), 'application_local_time' => (new DateTimeImmutable('now'))->format(DATE_ATOM), 'day_of_week' => (new DateTimeImmutable('now'))->format('l'), 'destination_local_time' => null],
            'location' => $location === null ? null : $location->toArray(),
            'candidates' => ['eligible' => $eligible, 'count' => count($eligible), 'query_source' => $catalogue['source'], 'retrieved_at' => $builtAt],
            'freshness' => ['user' => $user['source']['updated_at'] ?? null, 'bookings' => $builtAt, 'active_trip' => $activeTrip['source']['created_at'] ?? null, 'candidates' => $builtAt, 'location' => $location === null ? 'not_supplied' : $location->data['freshness']],
            'cache' => $cache, 'warnings' => $warnings,
            'metadata' => ['modules' => ['query', 'availability', 'location', 'user', 'bookings', 'trip'], 'llm_used' => false, 'recommendation_used' => false, 'duration_ms' => round((microtime(true) - $started) * 1000, 2)],
        ]);
    }

    private function cachedUser(string $userId): array
    {
        $key = 'user_' . hash('sha256', $userId); $cached = UthengaTieContextCache::get($key);
        if ($cached !== null) return [$cached, 'hit'];
        $user = $this->repository->user($userId);
        if ($user !== null) UthengaTieContextCache::put($key, $user, UthengaTieConfig::integer('TIE_CONTEXT_USER_CACHE_SECONDS', 60));
        return [$user, 'miss'];
    }

    private function catalogue(UthengaTieContextBuildRequest $request, ?UthengaTieLocationContext $location): array
    {
        $trip = $request->trip->data;
        // Origin is a transport-only field in the deployed schema. Candidate
        // retrieval stays destination-wide; Phase 4 applies route validation
        // when it evaluates an individual transport candidate.
        $criteria = ['query' => null, 'destination' => $trip['destination'], 'origin' => null, 'category' => null, 'vendor_id' => null, 'date' => $trip['start_date'], 'min_price' => null, 'max_price' => $trip['budget'], 'availability' => 'all', 'page' => 1, 'page_size' => 20, 'latitude' => null, 'longitude' => null, 'radius_km' => null];
        if ($request->nearbyRadiusKm !== null && $location !== null && $location->data['usable_for_nearby']) {
            $criteria['latitude'] = $location->data['latitude']; $criteria['longitude'] = $location->data['longitude']; $criteria['radius_km'] = $request->nearbyRadiusKm;
        }
        return $this->query->search(new UthengaTieCatalogueQuery($criteria));
    }
    private function rule(string $code, string $severity, string $message): array { return ['rule_code' => $code, 'severity' => $severity, 'message' => $message]; }
}
