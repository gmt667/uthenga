<?php
/** Phase 5/6 location, permission, consent, and nearby-search boundary. */

final class UthengaTieLocationPermission
{
    public const STATES = ['NOT_REQUESTED', 'REQUESTED', 'GRANTED', 'DENIED', 'UNAVAILABLE', 'RESTRICTED', 'EXPIRED'];

    public static function normalize(string $platform, string $rawState): string
    {
        $platform = strtolower(trim($platform));
        $raw = strtolower(str_replace([' ', '-'], '_', trim($rawState)));
        $maps = [
            'browser' => ['granted' => 'GRANTED', 'denied' => 'DENIED', 'prompt' => 'NOT_REQUESTED', 'requested' => 'REQUESTED', 'unavailable' => 'UNAVAILABLE'],
            'android' => ['granted' => 'GRANTED', 'denied' => 'DENIED', 'permanently_denied' => 'DENIED', 'restricted' => 'RESTRICTED', 'unavailable' => 'UNAVAILABLE', 'requested' => 'REQUESTED'],
            'ios' => ['when_in_use' => 'GRANTED', 'always' => 'GRANTED', 'granted' => 'GRANTED', 'denied' => 'DENIED', 'restricted' => 'RESTRICTED', 'unavailable' => 'UNAVAILABLE', 'requested' => 'REQUESTED'],
        ];
        if (isset($maps[$platform][$raw])) return $maps[$platform][$raw];
        $canonical = strtoupper($raw);
        if (in_array($canonical, self::STATES, true)) return $canonical;
        throw UthengaTieErrors::validation(['permission' => 'Permission state is not supported for this platform.']);
    }

    public static function transition(string $next, string $platform): array
    {
        $current = self::current()['state'];
        $allowed = [
            'NOT_REQUESTED' => ['NOT_REQUESTED', 'REQUESTED', 'GRANTED', 'DENIED', 'UNAVAILABLE', 'RESTRICTED'],
            'REQUESTED' => ['REQUESTED', 'GRANTED', 'DENIED', 'UNAVAILABLE', 'RESTRICTED'],
            'GRANTED' => ['GRANTED', 'EXPIRED', 'DENIED', 'UNAVAILABLE', 'RESTRICTED'],
            'DENIED' => ['DENIED', 'REQUESTED', 'RESTRICTED'],
            'UNAVAILABLE' => ['UNAVAILABLE', 'REQUESTED'],
            'RESTRICTED' => ['RESTRICTED', 'REQUESTED'],
            'EXPIRED' => ['EXPIRED', 'REQUESTED', 'GRANTED', 'DENIED', 'UNAVAILABLE', 'RESTRICTED'],
        ];
        if (!in_array($next, $allowed[$current] ?? [], true)) throw UthengaTieErrors::validation(['permission' => "Invalid permission transition from {$current} to {$next}."]);
        $state = ['state' => $next, 'platform' => strtolower($platform), 'updated_at' => gmdate('c'), 'scope' => 'session'];
        if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['tie_location_permission'] = $state;
        return $state;
    }

    public static function current(): array
    {
        if (session_status() === PHP_SESSION_ACTIVE && is_array($_SESSION['tie_location_permission'] ?? null)) return $_SESSION['tie_location_permission'];
        return ['state' => 'NOT_REQUESTED', 'platform' => null, 'updated_at' => null, 'scope' => 'session'];
    }
}

final class UthengaTieLocationPermissionContracts
{
    public static function request(array $input): array
    {
        $allowed = ['platform', 'permission_state', 'permission', 'csrf_token'];
        $unknown = array_values(array_diff(array_keys($input), $allowed));
        if ($unknown) throw UthengaTieErrors::validation(['permission' => 'Permission updates do not accept: ' . implode(', ', $unknown) . '.']);
        $platform = strtolower(trim((string) ($input['platform'] ?? 'browser')));
        if (!in_array($platform, ['browser', 'android', 'ios', 'native'], true)) throw UthengaTieErrors::validation(['platform' => 'Location platform is not supported.']);
        $raw = (string) ($input['permission_state'] ?? $input['permission'] ?? 'NOT_REQUESTED');
        $state = UthengaTieLocationPermission::normalize($platform, $raw);
        return UthengaTieLocationPermission::transition($state, $platform);
    }
}

final class UthengaTieLocationRequest
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieLocationContracts
{
    public static function request(array $input): UthengaTieLocationRequest
    {
        $coordinate = UthengaTieCoordinateValidator::observation($input);
        $errors = [];
        $source = $coordinate['source'];
        $platform = self::text($input['platform'] ?? self::defaultPlatform($source), 20) ?: self::defaultPlatform($source);
        if (!in_array($platform, ['browser', 'android', 'ios', 'native', 'manual', 'saved', 'vendor', 'geocoder'], true)) $errors['platform'] = 'Location platform is not supported.';
        $hasPermissionState = array_key_exists('permission_state', $input) || array_key_exists('permission', $input);
        if (in_array($source, ['browser_geolocation', 'device_gps'], true) && !$hasPermissionState) $errors['permission'] = 'Device location requires an explicit permission state.';
        if (in_array($source, ['browser_geolocation', 'device_gps'], true) && !array_key_exists('accuracy_m', $coordinate)) $errors['accuracy_m'] = 'Device location requires an accuracy value.';
        try { $permission = UthengaTieLocationPermission::normalize($platform, (string) ($input['permission_state'] ?? $input['permission'] ?? 'NOT_REQUESTED')); }
        catch (UthengaTieException $error) { $errors['permission'] = 'Location permission state is not supported.'; $permission = 'NOT_REQUESTED'; }
        if ($source === 'browser_geolocation' && $platform !== 'browser') $errors['platform'] = 'Browser geolocation requires the browser platform.';
        if (in_array($source, ['browser_geolocation', 'device_gps'], true) && $permission !== 'GRANTED') $errors['permission'] = 'Device location requires explicit granted permission.';
        if ($errors) throw UthengaTieErrors::validation($errors);
        $isDeviceLocation = in_array($source, ['browser_geolocation', 'device_gps'], true);
        $permissionContext = $isDeviceLocation
            ? UthengaTieLocationPermission::transition($permission, $platform)
            : ['state' => $permission, 'platform' => $platform, 'updated_at' => null, 'scope' => 'session'];
        return new UthengaTieLocationRequest(array_merge($coordinate, [
            'source' => $source, 'permission' => $permission, 'permission_state' => $permission,
            'permission_context' => $permissionContext, 'platform' => $platform, 'provider' => self::provider($source, $platform), 'ephemeral' => true,
        ]));
    }

    private static function text($value, int $max): ?string { if (!is_string($value) && !is_numeric($value)) return null; $value = trim((string) $value); return $value === '' ? null : substr($value, 0, $max); }
    private static function defaultPlatform(string $source): string { return match ($source) { 'browser_geolocation' => 'browser', 'device_gps' => 'native', 'saved_location' => 'saved', 'vendor_location' => 'vendor', 'geocoded_address' => 'geocoder', default => 'manual' }; }
    private static function provider(string $source, string $platform): string { return match ($source) { 'browser_geolocation' => 'browser', 'device_gps' => $platform, 'geocoded_address' => 'geocoder', 'vendor_location' => 'vendor', default => 'user' }; }
}

/** Phase 6.16: explicit no-location fallback; it never acquires or stores a coordinate. */
final class UthengaTieLocationFallback
{
    public static function forPermission(string $state): array
    {
        $state = strtoupper($state);
        $message = match ($state) {
            'DENIED' => 'Location permission was denied. You can still search by destination or choose a location manually.',
            'UNAVAILABLE' => 'This device cannot provide location. You can still search by destination or choose a location manually.',
            'RESTRICTED' => 'Location access is restricted by this device or policy. You can still search by destination or choose a location manually.',
            'EXPIRED' => 'The previous location observation has expired. Reacquire it or continue by choosing a destination manually.',
            default => 'Location has not been supplied. You can continue with destination-based or manual discovery.',
        };
        return [
            'location_available' => false, 'permission_state' => $state, 'message' => $message,
            'alternatives' => ['search_by_city', 'search_by_district', 'search_by_destination', 'manual_map_selection'],
            'booking_blocked' => false, 'tracking_started' => false,
        ];
    }
}

/** Phase 6.16 versioned, strict public Location Context API request boundary. */
final class UthengaTieLocationContextApiRequest
{
    public const SCHEMA_VERSION = 'location-context-request/v1';
    public ?UthengaTieLocationRequest $location;
    public ?array $fallback;
    public function __construct(?UthengaTieLocationRequest $location, ?array $fallback) { $this->location = $location; $this->fallback = $fallback; }
}

final class UthengaTieLocationContextApiContracts
{
    private const FIELDS = ['latitude', 'longitude', 'accuracy_m', 'accuracy_meters', 'captured_at', 'source', 'permission', 'permission_state', 'platform', 'altitude_m', 'heading', 'speed_mps', 'csrf_token'];

    public static function request(array $input): UthengaTieLocationContextApiRequest
    {
        $unknown = array_values(array_diff(array_keys($input), self::FIELDS));
        if ($unknown) throw UthengaTieErrors::validation(['request' => 'Unsupported location-context field(s): ' . implode(', ', $unknown) . '.']);
        $hasLatitude = array_key_exists('latitude', $input) && $input['latitude'] !== '' && $input['latitude'] !== null;
        $hasLongitude = array_key_exists('longitude', $input) && $input['longitude'] !== '' && $input['longitude'] !== null;
        if ($hasLatitude || $hasLongitude) return new UthengaTieLocationContextApiRequest(UthengaTieLocationContracts::request($input), null);

        $current = UthengaTieLocationPermission::current();
        $platform = strtolower(trim((string) ($input['platform'] ?? $current['platform'] ?? 'browser')));
        if (!in_array($platform, ['browser', 'android', 'ios', 'native'], true)) throw UthengaTieErrors::validation(['platform' => 'Location platform is not supported.']);
        $raw = $input['permission_state'] ?? $input['permission'] ?? $current['state'];
        $state = UthengaTieLocationPermission::normalize($platform, (string) $raw);
        if ($state === 'GRANTED') throw UthengaTieErrors::validation(['coordinates' => 'Granted location context requires latitude and longitude.']);
        $permission = UthengaTieLocationPermission::transition($state, $platform);
        return new UthengaTieLocationContextApiRequest(null, UthengaTieLocationFallback::forPermission($permission['state']));
    }
}

/** Phase 6.16 public response: a minimized view, not the internal coordinate DTO. */
final class UthengaTieLocationContextPublicResponse
{
    public const SCHEMA_VERSION = 'location-context-response/v1';

    public static function fromContext(UthengaTieLocationContext $context): array
    {
        $data = $context->toArray();
        $geography = $data['geographic_context'] ?? [];
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'location' => [
                'valid' => true, 'confidence' => $data['confidence'], 'freshness' => $data['freshness'],
                'accuracy_classification' => $data['accuracy_classification'], 'permission_state' => $data['permission'],
                'usable_for_nearby' => $data['usable_for_nearby'], 'ephemeral' => true,
            ],
            'geographic_context' => [
                'schema_version' => $geography['schema_version'] ?? UthengaTieGeographicNormalizer::VERSION,
                'status' => $geography['status'] ?? 'unresolved',
                'country' => $geography['country'] ?? null, 'region' => $geography['region'] ?? null,
                'district' => $geography['district'] ?? null, 'city' => $geography['city'] ?? null, 'area' => $geography['area'] ?? null,
            ],
            'quality' => ['accuracy_classification' => $data['accuracy_classification'], 'freshness' => $data['freshness'], 'usable_for_nearby' => $data['usable_for_nearby']],
            'confidence' => $data['confidence'],
            'provenance' => ['source' => $data['source'], 'provider' => $data['provider'], 'ephemeral' => true],
            'diagnostics' => ['persistence' => 'ephemeral_request_only', 'validation_status' => $data['metadata']['validation_status'] ?? 'VALIDATED', 'coordinate_exposure' => 'omitted_from_public_response'],
        ];
    }

    public static function fallback(array $fallback): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'location' => ['valid' => false, 'confidence' => 'UNKNOWN', 'freshness' => null, 'accuracy_classification' => 'UNKNOWN', 'permission_state' => $fallback['permission_state'], 'usable_for_nearby' => false, 'ephemeral' => true],
            'geographic_context' => UthengaTieGeographicNormalizer::unavailable('not_requested', 'not_available'),
            'quality' => ['accuracy_classification' => 'UNKNOWN', 'freshness' => null, 'usable_for_nearby' => false],
            'confidence' => 'UNKNOWN', 'provenance' => ['source' => null, 'provider' => null, 'ephemeral' => true],
            'diagnostics' => ['persistence' => 'ephemeral_request_only', 'validation_status' => 'NO_LOCATION_OBSERVATION', 'coordinate_exposure' => 'omitted_from_public_response'],
            'fallback' => $fallback,
        ];
    }
}

/** Phase 6.13 marketplace-coordinate verification and audit boundary. */
final class UthengaTieVendorCoordinateGovernance
{
    public const VENDOR_SOURCES = ['vendor_input', 'vendor_gps', 'geocoded_address'];
    public const ADMIN_SOURCES = ['admin_verified', 'imported'];
    public const STATUSES = ['unverified', 'pending_review', 'verified', 'rejected'];

    public static function source(string $source, bool $isAdmin): string
    {
        $source = strtolower(trim($source));
        if (in_array($source, self::ADMIN_SOURCES, true)) {
            if ($isAdmin) return $source;
            throw UthengaTieErrors::authorization();
        }
        if (!in_array($source, self::VENDOR_SOURCES, true)) throw UthengaTieErrors::validation(['source' => 'Coordinate source is not supported.']);
        return $source;
    }

    public static function audit(PDO $db, string $listingId, ?string $actorId, string $action, ?string $source, string $status, ?float $accuracy, ?string $capturedAt, ?string $note = null): void
    {
        if (!in_array($status, self::STATUSES, true)) throw new InvalidArgumentException('Unsupported coordinate verification status.');
        $stmt = $db->prepare('INSERT INTO listing_location_audit (listing_id, actor_user_id, action, acquisition_source, verification_status, accuracy_m, captured_at, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$listingId, $actorId, $action, $source, $status, $accuracy, $capturedAt, $note]);
    }
}

/** Phase 6.14: one deterministic quality view over marketplace coordinates. */
final class UthengaTieVendorLocationQuality
{
    public const STATES = ['VERIFIED', 'UNVERIFIED', 'MISSING', 'STALE', 'INVALID'];

    public static function assess($latitude, $longitude, string $verificationStatus, ?string $capturedAt, ?string $verifiedAt): array
    {
        if ($latitude === null && $longitude === null) return self::result('MISSING');
        if ($latitude === null || $longitude === null) return self::result('INVALID');
        try { UthengaTieCoordinateValidator::searchAnchor($latitude, $longitude); }
        catch (Throwable $error) { return self::result('INVALID'); }

        if (strtolower($verificationStatus) !== 'verified') return self::result('UNVERIFIED');
        $reviewedAt = $verifiedAt ?: $capturedAt;
        if ($reviewedAt === null || self::isStale($reviewedAt)) return self::result('STALE', true, $reviewedAt);
        return self::result('VERIFIED', false, $reviewedAt);
    }

    public static function staleCutoff(): string
    {
        return gmdate('Y-m-d H:i:s', time() - (self::staleDays() * 86400));
    }

    public static function distribution(PDO $db): array
    {
        $rows = $db->query('SELECT gps_lat, gps_lng, location_verification_status, location_captured_at, location_verified_at FROM listings')->fetchAll();
        $distribution = array_fill_keys(self::STATES, 0);
        foreach ($rows as $row) {
            $quality = self::assess($row['gps_lat'], $row['gps_lng'], (string) ($row['location_verification_status'] ?? 'unverified'), $row['location_captured_at'] ?: null, $row['location_verified_at'] ?: null);
            $distribution[$quality['state']]++;
        }
        return $distribution;
    }

    private static function isStale(string $value): bool
    {
        try { return (new DateTimeImmutable($value))->getTimestamp() < strtotime(self::staleCutoff()); }
        catch (Throwable $error) { return true; }
    }

    private static function staleDays(): int
    {
        return max(1, min(3650, UthengaTieConfig::integer('TIE_VENDOR_LOCATION_STALE_DAYS', 365)));
    }

    private static function result(string $state, bool $refreshRequired = false, ?string $reviewedAt = null): array
    {
        return ['state' => $state, 'precision_eligible' => $state === 'VERIFIED', 'refresh_required' => $refreshRequired, 'reviewed_at' => $reviewedAt];
    }
}

/** Phase 6.15: deployed marketplace category boundary for nearby discovery. */
final class UthengaTieNearbyCategoryRegistry
{
    public const PROFILE = 'nearby-marketplace-categories/v1';
    public static function supported(): array { return UthengaTieCatalogueContracts::supportedCategories(); }
}

/** Phase 6.17: observational input only; marketplace facts are server-derived. */
final class UthengaTieLocationObservationPolicy
{
    private const MARKETPLACE_FACTS = ['vendor_distance', 'vendor_available', 'vendor_verified', 'distance_km', 'distance_type', 'eligibility', 'availability_status', 'rating', 'quality', 'candidate', 'service', 'results'];
    public static function rejectMarketplaceFacts(array $input): void
    {
        $facts = array_values(array_intersect(array_keys($input), self::MARKETPLACE_FACTS));
        if ($facts) throw UthengaTieErrors::validation(['server_authority' => 'Client-supplied marketplace facts are not accepted: ' . implode(', ', $facts) . '.']);
    }
}

/** Standard graceful-degradation response for an unavailable geographic search provider. */
final class UthengaTieLocationFailureHandling
{
    public static function geographicSearchUnavailable(): array
    {
        return ['nearby_available' => false, 'message' => 'Nearby search is temporarily unavailable. You can continue with normal marketplace search.', 'alternatives' => ['catalogue_search', 'search_by_destination', 'search_by_category'], 'booking_blocked' => false];
    }
}

/** Phase 6.15 public request contract; it reuses the Phase 3 criteria DTO. */
final class UthengaTieNearbySearchRequest
{
    public const SCHEMA_VERSION = 'nearby-search-request/v1';
    public UthengaTieLocationRequest $location;
    public UthengaTieCatalogueQuery $criteria;
    public array $data;
    public function __construct(UthengaTieLocationRequest $location, UthengaTieCatalogueQuery $criteria, array $data) { $this->location = $location; $this->criteria = $criteria; $this->data = $data; }
    public function toArray(): array
    {
        $criteria = $this->criteria->toArray();
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'location' => $this->location->toArray(),
            'radius_km' => $criteria['radius_km'], 'category' => $criteria['category'], 'date' => $criteria['date'],
            'quantity' => $this->data['quantity'],
            'query_filters' => array_intersect_key($criteria, array_flip(['query', 'destination', 'origin', 'vendor_id', 'min_price', 'max_price', 'availability', 'page', 'page_size'])),
            'request_metadata' => ['end_date' => $this->data['end_date'], 'inventory_option' => $this->data['inventory_option']],
        ];
    }
}

final class UthengaTieNearbyContracts
{
    private const INPUT_FIELDS = [
        'latitude', 'longitude', 'accuracy_m', 'accuracy_meters', 'captured_at', 'source', 'permission', 'permission_state', 'platform', 'altitude_m', 'heading', 'speed_mps',
        'radius_km', 'category', 'date', 'quantity', 'end_date', 'inventory_option', 'q', 'destination', 'origin', 'vendor_id', 'min_price', 'max_price', 'availability', 'page', 'page_size', 'csrf_token',
    ];

    public static function request(array $input): UthengaTieNearbySearchRequest
    {
        UthengaTieLocationObservationPolicy::rejectMarketplaceFacts($input);
        $unknown = array_values(array_diff(array_keys($input), self::INPUT_FIELDS));
        if ($unknown) throw UthengaTieErrors::validation(['request' => 'Unsupported nearby-search field(s): ' . implode(', ', $unknown) . '.']);
        $location = UthengaTieLocationContracts::request($input);
        $criteriaInput = $input;
        $criteriaInput['latitude'] = $location->data['latitude'];
        $criteriaInput['longitude'] = $location->data['longitude'];
        $criteria = UthengaTieCatalogueContracts::services($criteriaInput);
        if ($criteria->data['radius_km'] === null) throw UthengaTieErrors::validation(['radius_km' => 'Nearby search requires radius_km.']);
        $quantity = $input['quantity'] ?? 1;
        if (!filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]])) throw UthengaTieErrors::validation(['quantity' => 'Quantity must be an integer from 1 to 10000.']);
        return new UthengaTieNearbySearchRequest($location, $criteria, [
            'quantity' => (int) $quantity, 'start_date' => $criteria->data['date'], 'end_date' => self::date($input['end_date'] ?? null),
            'origin' => self::text($input['origin'] ?? null, 120), 'destination' => self::text($input['destination'] ?? null, 120),
            'inventory_option' => strtolower(self::text($input['inventory_option'] ?? null, 80) ?: 'standard'),
        ]);
    }
    private static function text($value, int $max): ?string { if (!is_string($value) && !is_numeric($value)) return null; $value = trim((string) $value); return $value === '' ? null : substr($value, 0, $max); }
    private static function date($value): ?string { if ($value === null || $value === '') return null; if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) throw UthengaTieErrors::validation(['end_date' => 'End date must use YYYY-MM-DD.']); return $value; }
}

/** Phase 6.15 versioned result item; routing semantics are deliberately absent. */
final class UthengaTieNearbySearchResult
{
    public const DISTANCE_TYPE = 'GEOGRAPHIC';
    public static function fromRaw(array $raw): array
    {
        return [
            'candidate' => $raw['candidate'],
            'distance' => ['value_km' => $raw['distance_km'], 'type' => self::DISTANCE_TYPE, 'unit' => 'km'],
            'location' => $raw['candidate']['location'],
            'validation' => $raw['validation'],
            'provenance' => $raw['provenance'],
            'metadata' => ['distance_semantics' => 'straight_line_geographic'],
        ];
    }
}

/** Phase 6.15 versioned response boundary for downstream TIE modules. */
final class UthengaTieNearbySearchResponse
{
    public const SCHEMA_VERSION = 'nearby-search-response/v1';
    public static function build(UthengaTieLocationContext $context, array $rawResults, array $excluded, array $catalogue, UthengaTieNearbySearchRequest $request): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'distance_semantics' => ['type' => UthengaTieNearbySearchResult::DISTANCE_TYPE, 'unit' => 'km', 'description' => 'Straight-line geographic distance; not a route, travel distance, or ETA.'],
            'location_context' => $context->toArray(),
            'results' => array_map([UthengaTieNearbySearchResult::class, 'fromRaw'], $rawResults),
            'count' => count($rawResults), 'excluded' => $excluded,
            'search_diagnostics' => ['candidate_count' => count($catalogue['candidates']), 'eligible_service_count' => count($rawResults), 'validation_rejection_count' => $excluded['validation'], 'missing_coordinate_count' => $excluded['missing_coordinates'], 'radius_km' => $request->criteria->data['radius_km']],
            'metadata' => [
                'request_schema_version' => UthengaTieNearbySearchRequest::SCHEMA_VERSION,
                'category_registry' => UthengaTieNearbyCategoryRegistry::PROFILE,
                'supported_categories' => UthengaTieNearbyCategoryRegistry::supported(),
                'ordering' => ['distance' => 'ascending', 'availability' => 'declared_units_descending_on_distance_tie', 'service_id' => 'ascending_on_full_tie'],
                'source' => $catalogue['source'],
            ],
            'warnings' => array_merge($catalogue['warnings'], ['Distances are straight-line geographic distances, not road distances or travel times.']),
        ];
    }
}

/** Phase 6.5: provider-neutral GPS accuracy classification. */
final class UthengaTieLocationAccuracy
{
    public const CLASSES = ['EXCELLENT', 'GOOD', 'MODERATE', 'POOR', 'UNKNOWN'];

    public static function evaluate(?float $accuracyMeters): array
    {
        if ($accuracyMeters === null) return ['classification' => 'UNKNOWN', 'thresholds_m' => self::thresholds()];
        if (!is_finite($accuracyMeters) || $accuracyMeters < 0) throw UthengaTieErrors::validation(['accuracy_m' => ['code' => 'INVALID_ACCURACY', 'message' => 'Accuracy must be a finite non-negative number.']]);
        $thresholds = self::thresholds();
        $classification = $accuracyMeters <= $thresholds['excellent'] ? 'EXCELLENT'
            : ($accuracyMeters <= $thresholds['good'] ? 'GOOD'
            : ($accuracyMeters <= $thresholds['moderate'] ? 'MODERATE' : 'POOR'));
        return ['classification' => $classification, 'thresholds_m' => $thresholds];
    }

    private static function thresholds(): array
    {
        $thresholds = [
            'excellent' => UthengaTieConfig::integer('TIE_LOCATION_ACCURACY_EXCELLENT_MAX_METERS', 25),
            'good' => UthengaTieConfig::integer('TIE_LOCATION_ACCURACY_GOOD_MAX_METERS', UthengaTieConfig::integer('TIE_LOCATION_NEARBY_MAX_ACCURACY_METERS', 1000)),
            'moderate' => UthengaTieConfig::integer('TIE_LOCATION_ACCURACY_MODERATE_MAX_METERS', 5000),
            'poor' => UthengaTieConfig::integer('TIE_LOCATION_ACCURACY_POOR_MAX_METERS', 20000),
        ];
        if ($thresholds['excellent'] < 0 || $thresholds['excellent'] > $thresholds['good'] || $thresholds['good'] > $thresholds['moderate'] || $thresholds['moderate'] > $thresholds['poor']) {
            throw new UthengaTieException('configuration_error', 'Location accuracy thresholds are invalid.', 500);
        }
        return $thresholds;
    }
}

/** Phase 6.6: timestamp age classification; acquisition remains client-owned. */
final class UthengaTieLocationFreshness
{
    public const STATES = ['FRESH', 'AGING', 'STALE', 'EXPIRED'];

    public static function evaluate(string $capturedAt, ?int $now = null): array
    {
        $thresholds = self::thresholds();
        try { $captured = new DateTimeImmutable($capturedAt); }
        catch (Throwable $error) { throw UthengaTieErrors::validation(['captured_at' => ['code' => 'INVALID_TIMESTAMP', 'message' => 'Captured timestamp must be ISO-8601.']]); }
        $age = max(0, ($now ?? time()) - $captured->getTimestamp());
        $classification = $age <= $thresholds['fresh'] ? 'FRESH'
            : ($age <= $thresholds['aging'] ? 'AGING'
            : ($age <= $thresholds['stale'] ? 'STALE' : 'EXPIRED'));
        return ['classification' => $classification, 'age_seconds' => $age, 'reacquisition_required' => $classification === 'EXPIRED', 'thresholds_seconds' => $thresholds];
    }

    private static function thresholds(): array
    {
        $thresholds = [
            'fresh' => UthengaTieConfig::integer('TIE_LOCATION_FRESH_SECONDS', 300),
            'aging' => UthengaTieConfig::integer('TIE_LOCATION_AGING_SECONDS', 900),
            'stale' => UthengaTieConfig::integer('TIE_LOCATION_EXPIRED_SECONDS', 3600),
        ];
        if ($thresholds['fresh'] < 0 || $thresholds['fresh'] > $thresholds['aging'] || $thresholds['aging'] > $thresholds['stale']) {
            throw new UthengaTieException('configuration_error', 'Location freshness thresholds are invalid.', 500);
        }
        return $thresholds;
    }
}

/** All location-aware modules use these profiles rather than raw quality values. */
final class UthengaTieLocationOperationProfiles
{
    private const ACCURACY = [
        'nearby_search' => ['EXCELLENT', 'GOOD'], 'trip_planning' => ['EXCELLENT', 'GOOD', 'MODERATE'],
        'regional_context' => ['EXCELLENT', 'GOOD', 'MODERATE', 'POOR'], 'routing' => ['EXCELLENT', 'GOOD'],
        'live_journey_tracking' => ['EXCELLENT'],
    ];
    private const FRESHNESS = [
        'nearby_search' => ['FRESH', 'AGING'], 'trip_planning' => ['FRESH', 'AGING', 'STALE'],
        'regional_context' => ['AGING', 'STALE'], 'routing' => ['FRESH'], 'live_journey_tracking' => ['FRESH'],
    ];

    public static function evaluate(string $operation, string $accuracy, string $freshness): array
    {
        if (!isset(self::ACCURACY[$operation])) throw new InvalidArgumentException('Unsupported location operation profile.');
        $accuracyAccepted = in_array($accuracy, self::ACCURACY[$operation], true);
        $freshnessAccepted = in_array($freshness, self::FRESHNESS[$operation], true);
        return ['operation' => $operation, 'eligible' => $accuracyAccepted && $freshnessAccepted, 'accuracy_accepted' => $accuracyAccepted, 'freshness_accepted' => $freshnessAccepted];
    }

    public static function all(string $accuracy, string $freshness): array
    {
        $profiles = [];
        foreach (array_keys(self::ACCURACY) as $operation) $profiles[$operation] = self::evaluate($operation, $accuracy, $freshness);
        return $profiles;
    }
}

/** Phase 6.7: deterministic confidence derived from validated location metadata. */
final class UthengaTieLocationConfidence
{
    public const LEVELS = ['HIGH', 'MEDIUM', 'LOW', 'UNKNOWN'];
    private const RANK = ['UNKNOWN' => 0, 'LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3];

    public static function evaluate(string $accuracy, string $freshness, string $source, string $permission, bool $validated): array
    {
        $policy = self::policy();
        $knownSource = in_array($source, UthengaTieCoordinateValidator::SOURCES, true);
        $deviceSource = in_array($source, ['browser_geolocation', 'device_gps'], true);
        $permissionValid = !$deviceSource || $permission === 'GRANTED';
        if (!$validated || !$knownSource || !in_array($accuracy, UthengaTieLocationAccuracy::CLASSES, true) || !in_array($freshness, UthengaTieLocationFreshness::STATES, true) || $accuracy === 'UNKNOWN') $confidence = $policy['incomplete'];
        elseif (in_array($accuracy, $policy['high_accuracy'], true) && in_array($freshness, $policy['high_freshness'], true) && $permissionValid && in_array($source, $policy['high_sources'], true)) $confidence = 'HIGH';
        elseif (in_array($accuracy, $policy['medium_accuracy'], true) && in_array($freshness, $policy['medium_freshness'], true) && $permissionValid && in_array($source, $policy['medium_sources'], true)) $confidence = 'MEDIUM';
        else $confidence = 'LOW';
        return ['classification' => $confidence, 'policy_version' => $policy['version'], 'validated' => $validated, 'inputs' => ['accuracy_classification' => $accuracy, 'freshness' => $freshness, 'source' => $source, 'permission_state' => $permission]];
    }

    public static function operation(string $operation, string $confidence): array
    {
        if (!isset(self::RANK[$confidence])) throw new InvalidArgumentException('Unsupported confidence level.');
        $minimum = strtoupper(UthengaTieConfig::string('TIE_LOCATION_CONFIDENCE_' . strtoupper($operation) . '_MINIMUM', self::minimum($operation)));
        if (!isset(self::RANK[$minimum])) throw new UthengaTieException('configuration_error', 'Location confidence operation policy is invalid.', 500);
        return ['minimum_confidence' => $minimum, 'confidence_accepted' => self::RANK[$confidence] >= self::RANK[$minimum]];
    }

    private static function policy(): array
    {
        return [
            'version' => UthengaTieConfig::string('TIE_LOCATION_CONFIDENCE_POLICY_VERSION', 'v1'),
            'high_sources' => UthengaTieConfig::csv('TIE_LOCATION_CONFIDENCE_HIGH_SOURCES', ['browser_geolocation', 'device_gps']),
            'medium_sources' => UthengaTieConfig::csv('TIE_LOCATION_CONFIDENCE_MEDIUM_SOURCES', UthengaTieCoordinateValidator::SOURCES),
            'high_accuracy' => self::classes('TIE_LOCATION_CONFIDENCE_HIGH_ACCURACY', ['EXCELLENT', 'GOOD'], UthengaTieLocationAccuracy::CLASSES),
            'high_freshness' => self::classes('TIE_LOCATION_CONFIDENCE_HIGH_FRESHNESS', ['FRESH'], UthengaTieLocationFreshness::STATES),
            'medium_accuracy' => self::classes('TIE_LOCATION_CONFIDENCE_MEDIUM_ACCURACY', ['EXCELLENT', 'GOOD', 'MODERATE'], UthengaTieLocationAccuracy::CLASSES),
            'medium_freshness' => self::classes('TIE_LOCATION_CONFIDENCE_MEDIUM_FRESHNESS', ['FRESH', 'AGING'], UthengaTieLocationFreshness::STATES),
            'incomplete' => self::incomplete(),
        ];
    }

    private static function classes(string $key, array $default, array $allowed): array
    {
        $values = array_map('strtoupper', UthengaTieConfig::csv($key, $default));
        if ($values === [] || array_diff($values, $allowed)) throw new UthengaTieException('configuration_error', 'Location confidence mapping policy is invalid.', 500);
        return $values;
    }

    private static function incomplete(): string
    {
        $value = strtoupper(UthengaTieConfig::string('TIE_LOCATION_CONFIDENCE_INCOMPLETE', 'UNKNOWN'));
        if (!isset(self::RANK[$value])) throw new UthengaTieException('configuration_error', 'Location confidence incomplete-data policy is invalid.', 500);
        return $value;
    }

    private static function minimum(string $operation): string
    {
        return match ($operation) {
            'nearby_search' => 'MEDIUM', 'trip_planning' => 'LOW', 'regional_context' => 'LOW',
            'routing', 'live_journey_tracking' => 'HIGH', default => throw new InvalidArgumentException('Unsupported location operation profile.'),
        };
    }
}

/** Phase 6.8: normalized provider-neutral administrative geographic context. */
final class UthengaTieGeographicContext
{
    private const FIELDS = ['country', 'region', 'district', 'city', 'area', 'address'];

    public static function resolved(array $raw, string $provider, string $cacheStatus = 'disabled'): array
    {
        return UthengaTieGeographicNormalizer::normalize($raw, $provider, $cacheStatus);
    }

    public static function unavailable(string $provider, string $status): array
    {
        return UthengaTieGeographicNormalizer::unavailable($provider, $status);
    }
}

/** Phase 6.10 canonical hierarchy; provider-specific fields do not leave this boundary. */
final class UthengaTieGeographicNormalizer
{
    public const VERSION = 'geographic-context/v1';
    private const FIELDS = ['country', 'region', 'district', 'city', 'area', 'address'];
    private const MAP = [
        'country' => ['country'], 'region' => ['region', 'state', 'province'],
        'district' => ['district', 'county', 'state_district'], 'city' => ['city', 'town', 'village', 'municipality'],
        'area' => ['area', 'suburb', 'neighbourhood', 'locality'], 'address' => ['address', 'display_name'],
    ];

    public static function normalize(array $raw, string $provider, string $cacheStatus): array
    {
        $context = ['schema_version' => self::VERSION, 'status' => 'unresolved', 'provider' => $provider]; $hasValue = false;
        foreach (self::MAP as $canonical => $aliases) {
            $context[$canonical] = null;
            foreach ($aliases as $alias) {
                $value = $raw[$alias] ?? null;
                if (is_string($value) && trim($value) !== '') { $context[$canonical] = trim($value); $hasValue = true; break; }
            }
        }
        $context['status'] = $hasValue ? 'resolved' : 'unresolved';
        $context['provenance'] = ['provider' => $provider, 'source_type' => 'reverse_geocoding', 'resolution_status' => $context['status'], 'normalization_version' => self::VERSION, 'resolved_at' => gmdate('c'), 'cache' => $cacheStatus];
        return $context;
    }

    public static function unavailable(string $provider, string $status): array
    {
        $context = ['schema_version' => self::VERSION, 'status' => $status, 'provider' => $provider];
        foreach (self::FIELDS as $field) $context[$field] = null;
        $context['provenance'] = ['provider' => $provider, 'source_type' => 'reverse_geocoding', 'resolution_status' => $status, 'normalization_version' => self::VERSION, 'resolved_at' => null, 'cache' => 'not_cached'];
        return $context;
    }
}

/** Process-local optional cache; entries contain no user identity and failures are never cached. */
final class UthengaTieGeographicContextCache
{
    private static array $entries = [];

    public static function get(float $latitude, float $longitude, string $provider): ?array
    {
        if (self::ttl() <= 0) return null;
        $key = self::key($latitude, $longitude, $provider); $entry = self::$entries[$key] ?? null;
        return is_array($entry) && $entry['expires_at'] >= time() ? $entry['value'] : null;
    }

    public static function put(float $latitude, float $longitude, string $provider, array $value): void
    {
        if (self::ttl() <= 0) return;
        $key = self::key($latitude, $longitude, $provider);
        $max = max(1, UthengaTieConfig::integer('TIE_GEOGRAPHIC_CONTEXT_CACHE_MAX_ENTRIES', 1000));
        if (!isset(self::$entries[$key]) && count(self::$entries) >= $max) array_shift(self::$entries);
        self::$entries[$key] = ['expires_at' => time() + self::ttl(), 'provider' => $provider, 'value' => $value];
    }

    /** Intended for deployment/admin maintenance hooks; no user identity is involved. */
    public static function invalidate(?string $provider = null): void
    {
        if ($provider === null || $provider === '') { self::$entries = []; return; }
        foreach (self::$entries as $key => $entry) if (($entry['provider'] ?? null) === $provider) unset(self::$entries[$key]);
    }

    private static function ttl(): int { return max(0, UthengaTieConfig::integer('TIE_GEOGRAPHIC_CONTEXT_CACHE_SECONDS', 0)); }
    private static function key(float $latitude, float $longitude, string $provider): string { return hash('sha256', $provider . '|' . round($latitude, 4) . '|' . round($longitude, 4)); }
}

final class UthengaTieLocationEngine
{
    private UthengaTieReverseGeocodingService $reverseGeocoding;
    public function __construct(UthengaTieGeocodingProvider|UthengaTieReverseGeocodingService $geocoder) { $this->reverseGeocoding = $geocoder instanceof UthengaTieReverseGeocodingService ? $geocoder : new UthengaTieReverseGeocodingService([$geocoder]); }

    public function context(UthengaTieLocationRequest $request): UthengaTieLocationContext
    {
        $data = $request->data;
        $accuracy = UthengaTieLocationAccuracy::evaluate($data['accuracy_m'] ?? null);
        $freshness = UthengaTieLocationFreshness::evaluate($data['captured_at']);
        $profiles = UthengaTieLocationOperationProfiles::all($accuracy['classification'], $freshness['classification']);
        $permissionState = $data['permission_state'] ?? $data['permission'];
        if (in_array($data['source'], ['browser_geolocation', 'device_gps'], true) && $freshness['classification'] === 'EXPIRED') {
            $permissionState = 'EXPIRED';
            UthengaTieLocationPermission::transition('EXPIRED', $data['platform']);
        }
        $confidence = UthengaTieLocationConfidence::evaluate($accuracy['classification'], $freshness['classification'], $data['source'], $permissionState, true);
        foreach ($profiles as $operation => $profile) {
            $confidenceDecision = UthengaTieLocationConfidence::operation($operation, $confidence['classification']);
            $profiles[$operation] = array_merge($profile, $confidenceDecision, ['eligible' => $profile['eligible'] && $confidenceDecision['confidence_accepted']]);
        }
        $geography = $this->geographicContext($data['latitude'], $data['longitude']);
        $dto = [
            'latitude' => $data['latitude'], 'longitude' => $data['longitude'],
            'captured_at' => $data['captured_at'], 'source' => $data['source'], 'permission' => $permissionState,
            'provider' => $data['provider'], 'ephemeral' => true,
            'consent' => [
                'permission_state' => $permissionState, 'observed_permission_state' => $data['permission_state'] ?? $data['permission'],
                'platform' => $data['platform'], 'provider' => $data['provider'], 'ephemeral' => true,
                'session_scope' => true, 'session_permission' => UthengaTieLocationPermission::current(),
            ],
            'provenance' => array_filter([
                'source' => $data['source'], 'provider' => $data['provider'], 'captured_at' => $data['captured_at'],
                'accuracy_m' => $data['accuracy_m'] ?? null, 'coordinate_precision_decimal_places' => $this->precision($data['latitude'], $data['longitude']), 'ephemeral' => true,
            ], static fn($value) => $value !== null),
            'freshness' => $freshness['classification'], 'freshness_age_seconds' => $freshness['age_seconds'], 'accuracy_classification' => $accuracy['classification'], 'confidence' => $confidence['classification'],
            'usable_for_nearby' => $profiles['nearby_search']['eligible'] && (!in_array($data['source'], ['browser_geolocation', 'device_gps'], true) || $permissionState === 'GRANTED'),
            'geographic_context' => $geography,
            'metadata' => ['coordinate_precision_decimal_places' => $this->precision($data['latitude'], $data['longitude']), 'persistence' => 'ephemeral_request_only', 'validation_status' => 'VALIDATED', 'accuracy' => $accuracy, 'freshness' => $freshness, 'confidence' => $confidence, 'operation_profiles' => $profiles],
        ];
        if (array_key_exists('accuracy_m', $data)) $dto['accuracy_m'] = $data['accuracy_m'];
        return new UthengaTieLocationContext(array_merge($dto, array_filter([
            'altitude_m' => $data['altitude_m'] ?? null, 'heading' => $data['heading'] ?? null, 'speed_mps' => $data['speed_mps'] ?? null,
        ], static fn($value) => $value !== null)));
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0088; $latDelta = deg2rad($lat2 - $lat1); $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function geographicContext(float $latitude, float $longitude): array
    {
        return $this->reverseGeocoding->resolve($latitude, $longitude);
    }
    private function precision(float $latitude, float $longitude): int
    {
        $count = static function (float $value): int { $text = rtrim(rtrim(sprintf('%.8F', $value), '0'), '.'); $dot = strpos($text, '.'); return $dot === false ? 0 : strlen($text) - $dot - 1; };
        return min($count($latitude), $count($longitude));
    }
}

final class UthengaTieNearbySearchService
{
    private UthengaTieGeographicSearchProvider $geographicSearch;
    private UthengaTieAvailabilityModule $availability;
    private UthengaTieLocationEngine $location;
    public function __construct(UthengaTieGeographicSearchProvider $geographicSearch, UthengaTieAvailabilityModule $availability, UthengaTieLocationEngine $location) { $this->geographicSearch = $geographicSearch; $this->availability = $availability; $this->location = $location; }

    public function search(UthengaTieNearbySearchRequest $request): array
    {
        $context = $this->location->context($request->location);
        if (!$context->data['usable_for_nearby']) throw UthengaTieErrors::validation(['location' => 'Location accuracy or freshness is insufficient for nearby search.']);
        $catalogue = $this->geographicSearch->search($request->criteria);
        $results = []; $excluded = ['validation' => 0, 'missing_coordinates' => 0];
        foreach ($catalogue['candidates'] as $candidate) {
            if (($candidate['location']['latitude'] ?? null) === null || ($candidate['location']['longitude'] ?? null) === null) { $excluded['missing_coordinates']++; continue; }
            $availabilityRequest = new UthengaTieAvailabilityRequest(array_merge($request->data, ['service_id' => $candidate['service_id']]));
            $validation = $this->availability->validateCandidates([$candidate], $availabilityRequest)[0];
            if (!$validation['eligible']) { $excluded['validation']++; continue; }
            $distance = self::distance($context->data, $candidate['location']);
            $results[] = ['candidate' => $candidate, 'distance_km' => $distance, 'distance_type' => 'straight_line', 'validation' => $validation, 'provenance' => $candidate['source']];
        }
        usort($results, [self::class, 'compareResults']);
        return UthengaTieNearbySearchResponse::build($context, $results, $excluded, $catalogue, $request);
    }
    private static function distance(array $origin, array $destination): float { return round(UthengaTieLocationEngine::distanceKm((float) $origin['latitude'], (float) $origin['longitude'], (float) $destination['latitude'], (float) $destination['longitude']), 3); }
    /** Stable contract: distance ascending, declared availability descending, then service id. */
    public static function compareResults(array $a, array $b): int
    {
        $distance = $a['distance_km'] <=> $b['distance_km'];
        if ($distance !== 0) return $distance;
        $aUnits = self::availabilityUnits($a);
        $bUnits = self::availabilityUnits($b);
        if ($aUnits !== $bUnits) return $bUnits <=> $aUnits;
        return strcmp((string) $a['candidate']['service_id'], (string) $b['candidate']['service_id']);
    }
    private static function availabilityUnits(array $result): int
    {
        $units = $result['candidate']['availability']['declared_units'] ?? null;
        return is_numeric($units) ? (int) $units : -1;
    }
}
