<?php
/**
 * Canonical TIE DTOs. They intentionally contain only normalized, purpose-
 * limited fields and are independent of the legacy listings.meta shape.
 */

final class UthengaTieLocationContext implements JsonSerializable
{
    public const SCHEMA_VERSION = 'location-context/v1';
    public array $data;

    /** A single verified observation, never a movement history or provider DTO. */
    public function __construct(array $data)
    {
        $verified = UthengaTieCoordinateValidator::observation($data);
        $required = [
            'schema_version' => self::SCHEMA_VERSION,
            'latitude' => $verified['latitude'], 'longitude' => $verified['longitude'],
            'captured_at' => $verified['captured_at'],
            'source' => $verified['source'],
        ];
        $optional = ['accuracy_m', 'altitude_m', 'heading', 'speed_mps', 'confidence', 'permission', 'provider', 'ephemeral', 'freshness', 'freshness_age_seconds', 'accuracy_classification', 'usable_for_nearby', 'consent', 'provenance', 'geographic_context', 'metadata'];
        $this->data = $required;
        foreach (['altitude_m', 'heading', 'speed_mps'] as $field) if (array_key_exists($field, $verified)) $data[$field] = $verified[$field];
        foreach ($optional as $field) if (array_key_exists($field, $data) && $data[$field] !== null) $this->data[$field] = $data[$field];
    }

    public function toArray(): array { return $this->data; }
    public function jsonSerialize(): array { return $this->toArray(); }
}

/** Deterministic server-side coordinate validation for every TIE observation. */
final class UthengaTieCoordinateValidator
{
    public const SOURCES = ['browser_geolocation', 'device_gps', 'manual_location', 'saved_location', 'vendor_location', 'geocoded_address'];
    private const PRECISION = 6;

    public static function observation(array $input): array
    {
        $errors = [];
        $latitude = self::coordinate($input['latitude'] ?? null, -90, 90, 'latitude', 'INVALID_LATITUDE', $errors);
        $longitude = self::coordinate($input['longitude'] ?? null, -180, 180, 'longitude', 'INVALID_LONGITUDE', $errors);
        if ($latitude === null && $longitude === null && (array_key_exists('latitude', $input) || array_key_exists('longitude', $input))) $errors['coordinates'] = self::error('MALFORMED_COORDINATES', 'Latitude and longitude must be supplied together.');
        $accuracy = self::number($input['accuracy_m'] ?? $input['accuracy_meters'] ?? null, 'accuracy_m', 'INVALID_ACCURACY', $errors, 0);
        $capturedAt = self::timestamp($input['captured_at'] ?? null, $errors);
        $source = self::source($input['source'] ?? null, $errors);
        $altitude = self::optionalNumber($input['altitude_m'] ?? null, 'altitude_m', 'INVALID_ALTITUDE', $errors);
        $heading = self::number($input['heading'] ?? null, 'heading', 'INVALID_HEADING', $errors, 0, 360, false);
        $speed = self::number($input['speed_mps'] ?? null, 'speed_mps', 'INVALID_SPEED', $errors, 0);
        if ($errors) throw UthengaTieErrors::validation($errors);
        return array_filter([
            'latitude' => round($latitude, self::PRECISION), 'longitude' => round($longitude, self::PRECISION),
            'accuracy_m' => $accuracy === null ? null : round($accuracy, 2), 'captured_at' => $capturedAt, 'source' => $source,
            'altitude_m' => $altitude === null ? null : round($altitude, 2), 'heading' => $heading === null ? null : round($heading, 2),
            'speed_mps' => $speed === null ? null : round($speed, 2),
        ], static fn($value) => $value !== null);
    }

    /** Validates coordinate pairs used as a search anchor, not a location observation. */
    public static function searchAnchor($latitudeValue, $longitudeValue): array
    {
        if (($latitudeValue === null || $latitudeValue === '') && ($longitudeValue === null || $longitudeValue === '')) return ['latitude' => null, 'longitude' => null];
        $errors = [];
        $latitude = self::coordinate($latitudeValue, -90, 90, 'latitude', 'INVALID_LATITUDE', $errors);
        $longitude = self::coordinate($longitudeValue, -180, 180, 'longitude', 'INVALID_LONGITUDE', $errors);
        if ($latitude === null || $longitude === null) $errors['coordinates'] = self::error('MALFORMED_COORDINATES', 'Latitude and longitude must be supplied together.');
        if ($errors) throw UthengaTieErrors::validation($errors);
        return ['latitude' => round($latitude, self::PRECISION), 'longitude' => round($longitude, self::PRECISION)];
    }

    private static function coordinate($value, float $min, float $max, string $field, string $code, array &$errors): ?float
    {
        if ($value === null || $value === '') { $errors[$field] = self::error($code, ucfirst($field) . ' is required.'); return null; }
        if (!is_int($value) && !is_float($value) && !is_string($value)) { $errors[$field] = self::error('MALFORMED_COORDINATES', ucfirst($field) . ' must be numeric.'); return null; }
        if (!is_numeric($value) || !is_finite((float) $value)) { $errors[$field] = self::error('MALFORMED_COORDINATES', ucfirst($field) . ' must be finite.'); return null; }
        $number = (float) $value;
        if ($number < $min || $number > $max) { $errors[$field] = self::error($code, ucfirst($field) . ' is outside its supported range.'); return null; }
        return $number;
    }

    private static function number($value, string $field, string $code, array &$errors, float $min, ?float $max = null, bool $inclusiveMax = true): ?float
    {
        if ($value === null || $value === '') return null;
        if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value) || !is_finite((float) $value)) { $errors[$field] = self::error($code, ucfirst(str_replace('_', ' ', $field)) . ' must be finite and numeric.'); return null; }
        $number = (float) $value;
        if ($number < $min || ($max !== null && ($inclusiveMax ? $number > $max : $number >= $max))) { $errors[$field] = self::error($code, ucfirst(str_replace('_', ' ', $field)) . ' is outside its supported range.'); return null; }
        return $number;
    }

    private static function optionalNumber($value, string $field, string $code, array &$errors): ?float { return self::number($value, $field, $code, $errors, -INF); }
    private static function source($value, array &$errors): ?string
    {
        if (!is_string($value) || trim($value) === '') { $errors['source'] = self::error('INVALID_SOURCE', 'Location source is required.'); return null; }
        $source = strtolower(trim($value));
        if (!in_array($source, self::SOURCES, true)) { $errors['source'] = self::error('INVALID_SOURCE', 'Location source is not supported.'); return null; }
        return $source;
    }

    private static function timestamp($value, array &$errors): ?string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value)) { $errors['captured_at'] = self::error('INVALID_TIMESTAMP', 'Captured timestamp must be ISO-8601.'); return null; }
        try {
            $date = new DateTimeImmutable($value);
            $clockSkew = UthengaTieConfig::integer('TIE_LOCATION_CLOCK_SKEW_SECONDS', 300);
            if ($date->getTimestamp() > time() + max(0, $clockSkew)) { $errors['captured_at'] = self::error('INVALID_TIMESTAMP', 'Captured timestamp exceeds the configured future clock-skew tolerance.'); return null; }
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
        } catch (Throwable $error) { $errors['captured_at'] = self::error('INVALID_TIMESTAMP', 'Captured timestamp must be ISO-8601.'); return null; }
    }

    private static function error(string $code, string $message): array { return ['code' => $code, 'message' => $message]; }
}

final class UthengaTieTripRequest
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieUserContext
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieVendorCandidate
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieRecommendation
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieTripPlan
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieRoute
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieJourneyState
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieConversation
{
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieContracts
{
    public static function tripRequest(array $input, string $userId): UthengaTieTripRequest
    {
        $errors = [];
        $destination = self::nullableText($input['destination'] ?? null, 200);
        $origin = self::nullableText($input['origin'] ?? null, 200);
        if ($destination === null) {
            $errors['destination'] = 'Destination is required.';
        }

        $startDate = self::date($input['start_date'] ?? null, 'start_date', $errors);
        $endDate = self::date($input['end_date'] ?? null, 'end_date', $errors);
        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            $errors['end_date'] = 'End date must be on or after start date.';
        }

        $travellers = $input['travellers'] ?? 1;
        if (!filter_var($travellers, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]])) {
            $errors['travellers'] = 'Travellers must be an integer between 1 and 20.';
        }

        $budget = $input['budget'] ?? null;
        if ($budget !== null && $budget !== '' && (!is_numeric($budget) || (float) $budget < 0)) {
            $errors['budget'] = 'Budget must be a non-negative number.';
        }

        $travelMode = self::nullableText($input['travel_mode'] ?? null, 40);
        $allowedModes = ['any', 'bus', 'car', 'flight', 'shuttle', 'ride_share'];
        if ($travelMode !== null && !in_array($travelMode, $allowedModes, true)) {
            $errors['travel_mode'] = 'Travel mode is not supported.';
        }

        $preferences = $input['preferences'] ?? [];
        if (!is_array($preferences) || count($preferences) > 20) {
            $errors['preferences'] = 'Preferences must be an array with at most 20 values.';
            $preferences = [];
        } else {
            $preferences = array_values(array_filter(array_map(function ($value) {
                return self::nullableText($value, 80);
            }, $preferences)));
        }

        if ($errors) {
            throw UthengaTieErrors::validation($errors);
        }

        return new UthengaTieTripRequest([
            'user_id' => $userId,
            'origin' => $origin,
            'destination' => $destination,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'travellers' => (int) $travellers,
            'budget' => ($budget === null || $budget === '') ? null : round((float) $budget, 2),
            'currency' => self::nullableText($input['currency'] ?? APP_CURRENCY, 3) ?: APP_CURRENCY,
            'preferences' => $preferences,
            'travel_mode' => $travelMode ?: 'any',
        ]);
    }

    public static function locationContext(array $input): UthengaTieLocationContext
    {
        $location = UthengaTieCoordinateValidator::observation($input);
        return new UthengaTieLocationContext(array_merge($location, [
            'confidence' => self::nullableText($input['confidence'] ?? null, 20),
            'permission' => self::nullableText($input['permission'] ?? null, 20),
            'provider' => self::nullableText($input['provider'] ?? null, 40),
            'ephemeral' => array_key_exists('ephemeral', $input) ? (bool) $input['ephemeral'] : true,
            'metadata' => array_filter([
                'country' => self::nullableText($input['country'] ?? null, 80), 'region' => self::nullableText($input['region'] ?? null, 120),
                'district' => self::nullableText($input['district'] ?? null, 120), 'city' => self::nullableText($input['city'] ?? null, 120),
                'address' => self::nullableText($input['address'] ?? null, 300),
            ], static fn($value) => $value !== null),
        ]));
    }

    private static function nullableText($value, int $maxLength): ?string
    {
        if (!is_string($value) && !is_numeric($value)) return null;
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, $maxLength);
    }

    private static function date($value, string $field, array &$errors): ?string
    {
        $value = self::nullableText($value, 10);
        if ($value === null) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $errors[$field] = 'Date must use YYYY-MM-DD.';
            return null;
        }
        return $value;
    }

    private static function coordinate($value, float $min, float $max, string $field, array &$errors): ?float
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            $errors[$field] = ucfirst($field) . ' is invalid.';
            return null;
        }
        return (float) $value;
    }

    private static function positiveNumber($value): ?float
    {
        return is_numeric($value) && (float) $value >= 0 ? (float) $value : null;
    }
}
