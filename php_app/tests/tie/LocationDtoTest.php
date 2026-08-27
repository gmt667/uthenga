<?php
/** Phase 6.3/6.4 canonical DTO and coordinate-validation coverage. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_location_dto_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

function tie_location_dto_error(array $input, string $field, string $code): void
{
    try {
        UthengaTieCoordinateValidator::observation($input);
        throw new RuntimeException('Expected coordinate validation error.');
    } catch (UthengaTieException $error) {
        $details = $error->details();
        tie_location_dto_assert($error->type() === 'validation_error', 'Coordinate errors use the validation error type.');
        tie_location_dto_assert(($details['fields'][$field]['code'] ?? null) === $code, "Expected {$code} on {$field}.");
    }
}

$valid = [
    'latitude' => '-13.96261234', 'longitude' => '33.77419876', 'accuracy_m' => '12.345',
    'captured_at' => '2020-07-29T08:30:00+02:00', 'source' => 'manual_location',
];
$normalized = UthengaTieCoordinateValidator::observation($valid);
tie_location_dto_assert($normalized['latitude'] === -13.962612 && $normalized['longitude'] === 33.774199, 'Coordinates are normalized to six decimal places.');
tie_location_dto_assert($normalized['accuracy_m'] === 12.35 && $normalized['captured_at'] === '2020-07-29T06:30:00Z', 'Accuracy and timestamp serialization are deterministic.');

$boundary = UthengaTieCoordinateValidator::observation(array_merge($valid, ['latitude' => -90, 'longitude' => 180]));
tie_location_dto_assert($boundary['latitude'] === -90.0 && $boundary['longitude'] === 180.0, 'Inclusive coordinate boundaries are accepted.');
tie_location_dto_error(array_merge($valid, ['latitude' => 90.01]), 'latitude', 'INVALID_LATITUDE');
tie_location_dto_error(array_merge($valid, ['longitude' => ['33.7']]), 'longitude', 'MALFORMED_COORDINATES');
tie_location_dto_error(array_merge($valid, ['source' => 'ip_inference']), 'source', 'INVALID_SOURCE');
tie_location_dto_error(array_merge($valid, ['captured_at' => 'today']), 'captured_at', 'INVALID_TIMESTAMP');

$location = UthengaTieContracts::locationContext($valid);
$serialized = $location->toArray();
tie_location_dto_assert($serialized['schema_version'] === 'location-context/v1', 'Canonical DTO is versioned.');
tie_location_dto_assert(!array_key_exists('altitude_m', $serialized) && !array_key_exists('heading', $serialized) && !array_key_exists('speed_mps', $serialized), 'Unsupported optional telemetry is omitted.');
tie_location_dto_assert(json_encode($serialized, JSON_UNESCAPED_SLASHES) === json_encode($location, JSON_UNESCAPED_SLASHES), 'DTO JSON serialization is deterministic.');
echo "TIE Phase 6.3/6.4 location DTO tests passed.\n";
