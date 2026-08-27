<?php
/**
 * Phase 3 catalogue query boundary.
 *
 * The running XAMPP profile uses listings as the canonical published-service
 * inventory. Category-specific facts live in listings.meta.  This file is the
 * only TIE layer that understands that storage shape; callers receive stable,
 * normalized candidates instead of raw rows.
 */

final class UthengaTieCatalogueQuery
{
    public array $data;

    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieCatalogueContracts
{
    private const CATEGORIES = ['event', 'accommodation', 'tour', 'transport'];

    /** Canonical deployed inventory categories; no downstream module infers extras. */
    public static function supportedCategories(): array
    {
        return array_map(static fn(string $code): array => ['code' => $code, 'label' => UthengaTieCategoryNormalizer::label($code)], self::CATEGORIES);
    }

    public static function services(array $input): UthengaTieCatalogueQuery
    {
        $errors = [];
        $category = self::text($input['category'] ?? null, 30);
        if ($category === 'property') $category = 'accommodation';
        if ($category !== null && !in_array($category, self::CATEGORIES, true)) {
            $errors['category'] = 'Category must be event, accommodation, tour, or transport.';
        }

        $availability = self::text($input['availability'] ?? 'all', 20) ?: 'all';
        if (!in_array($availability, ['all', 'available'], true)) {
            $errors['availability'] = 'Availability must be all or available.';
        }

        $minPrice = self::money($input['min_price'] ?? null, 'min_price', $errors);
        $maxPrice = self::money($input['max_price'] ?? null, 'max_price', $errors);
        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            $errors['max_price'] = 'Maximum price must be greater than or equal to minimum price.';
        }

        $date = self::date($input['date'] ?? null, 'date', $errors);
        $page = self::positiveInteger($input['page'] ?? 1, 1, 100000, 'page', $errors);
        $pageSize = self::positiveInteger($input['page_size'] ?? 20, 1, 50, 'page_size', $errors);
        $anchor = UthengaTieCoordinateValidator::searchAnchor($input['latitude'] ?? null, $input['longitude'] ?? null);
        $latitude = $anchor['latitude'];
        $longitude = $anchor['longitude'];
        $radius = self::money($input['radius_km'] ?? null, 'radius_km', $errors);
        if (($latitude === null) !== ($longitude === null)) $errors['location'] = 'Latitude and longitude must be supplied together.';
        if ($radius !== null && ($latitude === null || $longitude === null)) $errors['radius_km'] = 'Radius search requires latitude and longitude.';
        if ($radius !== null && ($radius <= 0 || $radius > 500)) $errors['radius_km'] = 'Radius must be greater than zero and at most 500 km.';

        if ($errors) throw UthengaTieErrors::validation($errors);

        return new UthengaTieCatalogueQuery([
            'query' => self::text($input['q'] ?? null, 120),
            'destination' => self::text($input['destination'] ?? null, 120),
            'origin' => self::text($input['origin'] ?? null, 120),
            'category' => $category,
            'vendor_id' => self::text($input['vendor_id'] ?? null, 30),
            'date' => $date,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'availability' => $availability,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_km' => $radius,
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public static function vendors(array $input): UthengaTieCatalogueQuery
    {
        $base = self::services($input)->toArray();
        unset($base['origin'], $base['destination'], $base['date'], $base['min_price'], $base['max_price'], $base['availability'], $base['latitude'], $base['longitude'], $base['radius_km']);
        return new UthengaTieCatalogueQuery($base);
    }

    private static function text($value, int $maxLength): ?string
    {
        if (!is_string($value) && !is_numeric($value)) return null;
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, $maxLength);
    }

    private static function money($value, string $field, array &$errors): ?float
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value) || (float) $value < 0) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be a non-negative number.';
            return null;
        }
        return round((float) $value, 2);
    }

    private static function date($value, string $field, array &$errors): ?string
    {
        $value = self::text($value, 10);
        if ($value === null) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $errors[$field] = 'Date must use YYYY-MM-DD.';
            return null;
        }
        return $value;
    }

    private static function positiveInteger($value, int $min, int $max, string $field, array &$errors): int
    {
        if (!filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]])) {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . " must be an integer from {$min} to {$max}.";
            return $min;
        }
        return (int) $value;
    }
}

final class UthengaTieCategoryNormalizer
{
    private const LABELS = [
        'event' => 'Event', 'accommodation' => 'Accommodation',
        'tour' => 'Tour or activity', 'transport' => 'Transport',
    ];

    public static function normalize(string $raw, array $meta): array
    {
        return [
            'code' => $raw,
            'label' => self::label($raw),
            'vendor_category' => self::text($meta['category'] ?? null),
        ];
    }

    public static function label(string $code): string { return self::LABELS[$code] ?? ucfirst($code); }

    private static function text($value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

/** All raw SQL is isolated here. Every query is read-only and parameterized. */
final class UthengaTieListingsRepository
{
    private PDO $db;
    private const PROFILE = 'unified_listings_v1';
    private const FETCH_WINDOW = 250;

    public function __construct(PDO $db) { $this->db = $db; }

    public function services(UthengaTieCatalogueQuery $criteria): array
    {
        $filter = $criteria->data;
        $where = ['l.is_active = 1', 'u.is_approved = 1', "u.account_status = 'active'"];
        $params = [];

        if ($filter['category'] !== null) { $where[] = 'l.listing_type = ?'; $params[] = $filter['category']; }
        if ($filter['vendor_id'] !== null) { $where[] = 'l.vendor_id = ?'; $params[] = $filter['vendor_id']; }
        if ($filter['query'] !== null) {
            $where[] = '(l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ? OR l.vendor_name LIKE ?)';
            $like = '%' . $filter['query'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($filter['destination'] !== null) {
            $where[] = "(l.location LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(l.meta, '$.routeTo')) LIKE ?)";
            $like = '%' . $filter['destination'] . '%';
            array_push($params, $like, $like);
        }
        if ($filter['origin'] !== null) {
            $where[] = "JSON_UNQUOTE(JSON_EXTRACT(l.meta, '$.routeFrom')) LIKE ?";
            $params[] = '%' . $filter['origin'] . '%';
        }
        if ($filter['radius_km'] !== null) {
            // Index-friendly bounding box prefilter. matches() applies exact Haversine distance before returning a result.
            $latDelta = $filter['radius_km'] / 110.574;
            $longitudeBase = max(0.00001, abs(cos(deg2rad($filter['latitude']))));
            $lngDelta = $filter['radius_km'] / (111.320 * $longitudeBase);
            $where[] = "l.location_verification_status = 'verified' AND COALESCE(l.location_verified_at, l.location_captured_at) >= ? AND l.gps_lat BETWEEN ? AND ? AND l.gps_lng BETWEEN ? AND ?";
            array_push($params, UthengaTieVendorLocationQuality::staleCutoff(), $filter['latitude'] - $latDelta, $filter['latitude'] + $latDelta, $filter['longitude'] - $lngDelta, $filter['longitude'] + $lngDelta);
        }

        $sql = 'SELECT l.id, l.listing_type, l.title, l.description, l.location, l.gps_lat, l.gps_lng, l.location_source, l.location_accuracy_m, l.location_captured_at, l.location_verified_at, l.location_verification_status, l.image, l.gallery, l.vendor_id, l.vendor_name, l.rating, l.featured, l.is_active AS service_active, l.meta, l.created_at, l.updated_at, u.role AS vendor_role, u.is_approved AS vendor_approved, u.account_status AS vendor_status'
            . ' FROM listings l INNER JOIN users u ON u.id = l.vendor_id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY l.updated_at DESC, l.id ASC LIMIT ' . self::FETCH_WINDOW;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $candidates = [];
        foreach ($rows as $row) {
            $candidate = $this->candidate($row)->toArray();
            if ($this->matches($candidate, $filter)) $candidates[] = $candidate;
        }
        $total = count($candidates);
        $offset = ($filter['page'] - 1) * $filter['page_size'];
        return [
            'candidates' => array_slice($candidates, $offset, $filter['page_size']),
            'pagination' => [
                'page' => $filter['page'], 'page_size' => $filter['page_size'], 'total' => $total,
                'has_more' => $offset + $filter['page_size'] < $total,
                'source_window_limit' => self::FETCH_WINDOW,
            ],
            'source' => self::PROFILE,
            'warnings' => $this->warnings($filter, count($rows)),
        ];
    }

    /** Internal diagnostic path. Unlike catalogue search it returns an
     * inactive or ineligible record so the rules engine can explain rejection. */
    public function serviceForValidation(string $serviceId): ?UthengaTieVendorCandidate
    {
        $stmt = $this->db->prepare(
            'SELECT l.id, l.listing_type, l.title, l.description, l.location, l.gps_lat, l.gps_lng, l.location_source, l.location_accuracy_m, l.location_captured_at, l.location_verified_at, l.location_verification_status, l.image, l.gallery, l.vendor_id, l.vendor_name, l.rating, l.featured, l.is_active AS service_active, l.meta, l.created_at, l.updated_at, u.role AS vendor_role, u.is_approved AS vendor_approved, u.account_status AS vendor_status'
            . ' FROM listings l LEFT JOIN users u ON u.id = l.vendor_id WHERE l.id = ? LIMIT 1'
        );
        $stmt->execute([$serviceId]);
        $row = $stmt->fetch();
        return is_array($row) ? $this->candidate($row) : null;
    }

    public function vendors(UthengaTieCatalogueQuery $criteria): array
    {
        $all = $this->services(new UthengaTieCatalogueQuery(array_merge($criteria->toArray(), [
            'origin' => null, 'destination' => null, 'date' => null, 'min_price' => null, 'max_price' => null, 'availability' => 'all',
            'latitude' => null, 'longitude' => null, 'radius_km' => null,
            'page' => 1, 'page_size' => self::FETCH_WINDOW,
        ])));
        $vendors = [];
        foreach ($all['candidates'] as $data) {
            $id = $data['vendor']['id'];
            if (!isset($vendors[$id])) {
                $vendors[$id] = [
                    'id' => $id, 'name' => $data['vendor']['name'], 'role' => $data['vendor']['role'],
                    'eligibility' => $data['vendor']['eligibility'], 'service_count' => 0, 'categories' => [],
                    'source' => $data['source'],
                ];
            }
            $vendors[$id]['service_count']++;
            $vendors[$id]['categories'][$data['category']['code']] = $data['category']['label'];
        }
        foreach ($vendors as &$vendor) $vendor['categories'] = array_values($vendor['categories']);
        unset($vendor);
        $vendors = array_values($vendors);
        $offset = ($criteria->data['page'] - 1) * $criteria->data['page_size'];
        return [
            'vendors' => array_slice($vendors, $offset, $criteria->data['page_size']),
            'pagination' => ['page' => $criteria->data['page'], 'page_size' => $criteria->data['page_size'], 'total' => count($vendors), 'has_more' => $offset + $criteria->data['page_size'] < count($vendors)],
            'source' => self::PROFILE, 'warnings' => $all['warnings'],
        ];
    }

    public function categories(): array
    {
        $stmt = $this->db->prepare('SELECT listing_type, COUNT(*) AS service_count FROM listings WHERE is_active = 1 GROUP BY listing_type ORDER BY listing_type');
        $stmt->execute();
        $categories = [];
        foreach ($stmt->fetchAll() as $row) {
            $normal = UthengaTieCategoryNormalizer::normalize((string) $row['listing_type'], []);
            $normal['service_count'] = (int) $row['service_count'];
            $categories[] = $normal;
        }
        return ['categories' => $categories, 'source' => self::PROFILE, 'cached' => false];
    }

    private function candidate(array $row): UthengaTieVendorCandidate
    {
        $meta = json_decode((string) $row['meta'], true);
        $meta = is_array($meta) ? $meta : [];
        $type = (string) $row['listing_type'];
        $price = $this->price($type, $meta);
        $availability = $this->declaredAvailability($type, $meta);
        $schedule = $this->schedule($type, $meta);
        $updated = (string) $row['updated_at'];
        $vendorExists = $row['vendor_role'] !== null;
        $vendorApproved = (int) ($row['vendor_approved'] ?? 0) === 1;
        $vendorStatus = strtolower((string) ($row['vendor_status'] ?? 'missing'));
        $serviceActive = (int) ($row['service_active'] ?? 0) === 1;
        $vendorEligible = $vendorExists && $vendorApproved && $vendorStatus === 'active';
        $location = $this->listingLocation($row['gps_lat'], $row['gps_lng'], (string) ($row['location_verification_status'] ?? 'unverified'), $row['location_captured_at'] ?: null, $row['location_verified_at'] ?: null);
        return new UthengaTieVendorCandidate([
            'id' => (string) $row['id'],
            'service_id' => (string) $row['id'],
            'title' => (string) $row['title'],
            'description' => (string) $row['description'],
            'category' => UthengaTieCategoryNormalizer::normalize($type, $meta),
            'vendor' => [
                'id' => (string) $row['vendor_id'], 'name' => (string) $row['vendor_name'],
                'role' => $row['vendor_role'] ?: null, 'exists' => $vendorExists, 'approved' => $vendorApproved,
                'status' => $vendorStatus, 'eligibility' => $vendorEligible ? 'eligible' : 'ineligible',
            ],
            'service' => ['is_active' => $serviceActive, 'lifecycle_status' => $serviceActive ? 'active' : 'inactive'],
            'location' => [
                'display_name' => (string) $row['location'],
                'latitude' => $location['latitude'], 'longitude' => $location['longitude'], 'coordinate_status' => $location['status'], 'quality' => $location['quality'], 'refresh_required' => $location['refresh_required'],
            ],
            'price' => $price,
            'availability' => $availability,
            'schedule' => $schedule,
            'rating' => ['value' => round((float) $row['rating'], 1), 'source' => 'listings.rating'],
            'media' => ['primary_image' => $row['image'] ?: null],
            'source' => ['system' => 'uthenga', 'profile' => self::PROFILE, 'table' => 'listings', 'record_id' => (string) $row['id'], 'updated_at' => $updated],
            'freshness' => ['observed_at' => gmdate('c'), 'source_updated_at' => $updated],
        ]);
    }

    /** Legacy listing coordinates are treated as untrusted until normalized. */
    private function listingLocation($latitude, $longitude, string $verificationStatus, ?string $capturedAt, ?string $verifiedAt): array
    {
        $quality = UthengaTieVendorLocationQuality::assess($latitude, $longitude, $verificationStatus, $capturedAt, $verifiedAt);
        if (!$quality['precision_eligible']) {
            $status = match ($quality['state']) {
                'MISSING' => 'not_available', 'STALE' => 'stale_coordinates', 'INVALID' => 'invalid_rejected', default => 'unverified_coordinates',
            };
            return ['latitude' => null, 'longitude' => null, 'status' => $status, 'quality' => $quality['state'], 'refresh_required' => $quality['refresh_required']];
        }
        try {
            $location = UthengaTieCoordinateValidator::searchAnchor($latitude, $longitude);
            return ['latitude' => $location['latitude'], 'longitude' => $location['longitude'], 'status' => 'listing_coordinates', 'quality' => $quality['state'], 'refresh_required' => $quality['refresh_required']];
        } catch (UthengaTieException $error) {
            return ['latitude' => null, 'longitude' => null, 'status' => 'invalid_rejected', 'quality' => 'INVALID', 'refresh_required' => false];
        }
    }

    private function price(string $type, array $meta): array
    {
        $amount = null; $unit = 'service';
        if ($type === 'accommodation') {
            $unit = 'night';
            $prices = [];
            foreach (($meta['rooms'] ?? []) as $room) if (is_array($room) && is_numeric($room['pricePerNight'] ?? null)) $prices[] = (float) $room['pricePerNight'];
            if ($prices) $amount = min($prices);
            elseif (is_numeric($meta['pricePerNight'] ?? null)) $amount = (float) $meta['pricePerNight'];
        } elseif ($type === 'event') {
            $unit = 'ticket';
            foreach (['standardTicketPrice', 'vipTicketPrice', 'pricePerPerson', 'price'] as $key) if (is_numeric($meta[$key] ?? null)) { $amount = (float) $meta[$key]; break; }
        } elseif ($type === 'tour') {
            $unit = 'person';
            foreach (['pricePerPerson', 'basePrice', 'price'] as $key) if (is_numeric($meta[$key] ?? null)) { $amount = (float) $meta[$key]; break; }
        } elseif ($type === 'transport') {
            $unit = 'seat';
            foreach (['pricePerSeat', 'baseFare', 'price'] as $key) if (is_numeric($meta[$key] ?? null)) { $amount = (float) $meta[$key]; break; }
        }
        return ['amount' => $amount, 'currency' => APP_CURRENCY, 'unit' => $unit, 'source' => 'listings.meta', 'validation_status' => 'not_validated'];
    }

    private function declaredAvailability(string $type, array $meta): array
    {
        $units = null;
        $options = [];
        if ($type === 'accommodation') {
            $units = 0; $known = false;
            foreach (($meta['rooms'] ?? []) as $room) {
                if (!is_array($room) || !is_numeric($room['availableRooms'] ?? null)) continue;
                $roomUnits = (int) $room['availableRooms'];
                $units += $roomUnits; $known = true;
                $options[] = [
                    'code' => (string) ($room['id'] ?? $room['name'] ?? 'room'),
                    'name' => $room['name'] ?? null,
                    'declared_units' => $roomUnits,
                    'max_occupancy' => is_numeric($room['capacity'] ?? null) ? (int) $room['capacity'] : null,
                ];
            }
            if (!$known) $units = null;
        } elseif ($type === 'event') {
            $values = array_filter([$meta['standardAvailable'] ?? null, $meta['vipAvailable'] ?? null], 'is_numeric');
            $units = $values ? array_sum(array_map('intval', $values)) : null;
            foreach (['standard' => 'standardAvailable', 'vip' => 'vipAvailable'] as $code => $field) {
                if (is_numeric($meta[$field] ?? null)) {
                    $options[] = ['code' => $code, 'name' => strtoupper($code), 'declared_units' => (int) $meta[$field]];
                }
            }
        } elseif ($type === 'transport') {
            $units = is_numeric($meta['availableSeats'] ?? null) ? (int) $meta['availableSeats'] : null;
            if ($units !== null) $options[] = ['code' => 'standard', 'name' => 'Standard', 'declared_units' => $units];
        }
        return ['declared_units' => $units, 'options' => $options, 'status' => $units === null ? 'unknown' : ($units > 0 ? 'declared_available' : 'declared_unavailable'), 'source' => 'listings.meta', 'validation_status' => 'phase_4_required'];
    }

    private function schedule(string $type, array $meta): array
    {
        if ($type === 'event') return ['date' => $meta['date'] ?? null, 'time' => $meta['time'] ?? null];
        if ($type === 'tour') return ['dates_available' => is_array($meta['datesAvailable'] ?? null) ? array_values($meta['datesAvailable']) : [], 'duration_days' => $meta['durationDays'] ?? null];
        if ($type === 'transport') return ['origin' => $meta['routeFrom'] ?? null, 'destination' => $meta['routeTo'] ?? null, 'departure_time' => $meta['departureTime'] ?? null, 'arrival_time' => $meta['arrivalTime'] ?? null, 'days' => is_array($meta['scheduleDays'] ?? null) ? array_values($meta['scheduleDays']) : []];
        return ['dates_available' => null, 'note' => 'Accommodation dates require Phase 4 inventory validation.'];
    }

    private function matches(array $candidate, array $filter): bool
    {
        $amount = $candidate['price']['amount'];
        if ($filter['min_price'] !== null && ($amount === null || $amount < $filter['min_price'])) return false;
        if ($filter['max_price'] !== null && ($amount === null || $amount > $filter['max_price'])) return false;
        if ($filter['availability'] === 'available' && $candidate['availability']['status'] !== 'declared_available') return false;
        if ($filter['radius_km'] !== null) {
            if ($candidate['location']['latitude'] === null || $candidate['location']['longitude'] === null) return false;
            if ($this->distanceKm($filter['latitude'], $filter['longitude'], $candidate['location']['latitude'], $candidate['location']['longitude']) > $filter['radius_km']) return false;
        }
        if ($filter['date'] === null) return true;
        $schedule = $candidate['schedule'];
        if ($candidate['category']['code'] === 'event') return ($schedule['date'] ?? null) === $filter['date'];
        if ($candidate['category']['code'] === 'tour') return in_array($filter['date'], $schedule['dates_available'] ?? [], true);
        if ($candidate['category']['code'] === 'transport') return in_array((new DateTimeImmutable($filter['date']))->format('l'), $schedule['days'] ?? [], true);
        return true; // Accommodation dates are intentionally left for Phase 4 validation.
    }

    private function warnings(array $filter, int $rowCount): array
    {
        $warnings = ['Prices and declared availability are marketplace values and are not booking validation. Phase 4 must revalidate before booking.'];
        if ($filter['date'] !== null) $warnings[] = 'Accommodation date availability is not evaluated in Phase 3.';
        if ($filter['radius_km'] !== null) $warnings[] = 'Radius search uses published listing coordinates only; the current seed profile has no populated coordinates.';
        if ($rowCount === self::FETCH_WINDOW) $warnings[] = 'The result source window reached its safety limit; refine filters for exhaustive results.';
        return $warnings;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0088;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

final class UthengaTieCatalogueService
{
    private ?UthengaTieListingsRepository $repository;

    public function __construct(?PDO $db)
    {
        $this->repository = $db instanceof PDO ? new UthengaTieListingsRepository($db) : null;
    }

    public function services(UthengaTieCatalogueQuery $criteria): array
    {
        if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database');
        return $this->repository->services($criteria);
    }
    public function vendors(UthengaTieCatalogueQuery $criteria): array
    {
        if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database');
        return $this->repository->vendors($criteria);
    }
    public function categories(): array
    {
        if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database');
        return $this->repository->categories();
    }
    public function serviceForValidation(string $serviceId): ?UthengaTieVendorCandidate
    {
        if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database');
        return $this->repository->serviceForValidation($serviceId);
    }
}
