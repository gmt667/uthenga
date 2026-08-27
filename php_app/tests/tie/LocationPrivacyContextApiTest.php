<?php
/** Phase 6.16 privacy-minimized Location Context API contract coverage. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_location_privacy_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
unset($_SESSION['tie_location_permission']);
$input = [
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 15,
    'captured_at' => gmdate(DATE_ATOM), 'source' => 'browser_geolocation', 'platform' => 'browser', 'permission_state' => 'granted',
];
$request = UthengaTieLocationContextApiContracts::request($input);
tie_location_privacy_assert($request->location instanceof UthengaTieLocationRequest && $request->fallback === null, 'Coordinate-bearing API request uses the canonical internal request.');
$context = (new UthengaTieKernel())->location->context($request->location);
$public = UthengaTieLocationContextPublicResponse::fromContext($context);
tie_location_privacy_assert($public['schema_version'] === 'location-context-response/v1', 'Public location response is versioned.');
tie_location_privacy_assert($public['location']['valid'] === true && $public['diagnostics']['coordinate_exposure'] === 'omitted_from_public_response', 'Public response reports validated ephemeral context.');
$encoded = json_encode($public, JSON_UNESCAPED_SLASHES);
foreach (['latitude', 'longitude', 'captured_at', 'accuracy_m', 'altitude_m', 'heading', 'speed_mps'] as $sensitiveField) {
    tie_location_privacy_assert(!str_contains($encoded, '"' . $sensitiveField . '"'), "Public response omits {$sensitiveField}.");
}

UthengaTieLocationPermission::transition('DENIED', 'browser');
$fallbackRequest = UthengaTieLocationContextApiContracts::request(['platform' => 'browser', 'permission_state' => 'denied']);
tie_location_privacy_assert($fallbackRequest->location === null && $fallbackRequest->fallback['permission_state'] === 'DENIED', 'Denied permission becomes a no-location fallback, not a validation failure.');
$fallback = UthengaTieLocationContextPublicResponse::fallback($fallbackRequest->fallback);
tie_location_privacy_assert($fallback['location']['valid'] === false && $fallback['fallback']['booking_blocked'] === false, 'Fallback keeps marketplace use available.');
tie_location_privacy_assert(in_array('search_by_destination', $fallback['fallback']['alternatives'], true), 'Fallback supplies destination discovery.');

try {
    UthengaTieLocationContextApiContracts::request($input + ['unsupported' => 'x']);
    throw new RuntimeException('Unsupported Location Context API field was accepted.');
} catch (UthengaTieException $error) {
    tie_location_privacy_assert($error->type() === 'validation_error', 'Unsupported public request fields are rejected.');
}
try {
    UthengaTieLocationPermissionContracts::request(['platform' => 'browser', 'permission_state' => 'denied', 'latitude' => -13.9626]);
    throw new RuntimeException('Permission endpoint accepted a coordinate.');
} catch (UthengaTieException $error) {
    tie_location_privacy_assert($error->type() === 'validation_error', 'Permission updates reject coordinates.');
}
echo "TIE Phase 6.16 location privacy/context API tests passed.\n";
