<?php
/** Phase 6.1/6.2 unit-style coverage for session permission and consent context. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_permission_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
unset($_SESSION['tie_location_permission']);

tie_permission_assert(UthengaTieLocationPermission::normalize('browser', 'prompt') === 'NOT_REQUESTED', 'Browser prompt normalizes to NOT_REQUESTED.');
tie_permission_assert(UthengaTieLocationPermission::normalize('android', 'permanently denied') === 'DENIED', 'Android permanent denial normalizes to DENIED.');
tie_permission_assert(UthengaTieLocationPermission::normalize('ios', 'when in use') === 'GRANTED', 'iOS when-in-use normalizes to GRANTED.');

$requested = UthengaTieLocationPermissionContracts::request(['platform' => 'browser', 'permission_state' => 'requested']);
tie_permission_assert($requested['state'] === 'REQUESTED', 'Permission request enters REQUESTED state.');
$granted = UthengaTieLocationPermissionContracts::request(['platform' => 'browser', 'permission_state' => 'granted']);
tie_permission_assert($granted['state'] === 'GRANTED' && UthengaTieLocationPermission::current()['state'] === 'GRANTED', 'Grant is session-scoped.');

try {
    UthengaTieLocationContracts::request([
        'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 15, 'captured_at' => gmdate(DATE_ATOM),
        'source' => 'browser_geolocation', 'platform' => 'browser',
    ]);
    throw new RuntimeException('Coordinates incorrectly implied device permission.');
} catch (UthengaTieException $error) {
    tie_permission_assert($error->type() === 'validation_error', 'Device coordinates require explicit permission state.');
}

$kernel = new UthengaTieKernel();
$fresh = UthengaTieLocationContracts::request([
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 15, 'captured_at' => gmdate(DATE_ATOM),
    'source' => 'browser_geolocation', 'platform' => 'browser', 'permission_state' => 'granted',
]);
$context = $kernel->location->context($fresh);
tie_permission_assert($context->data['consent']['permission_state'] === 'GRANTED', 'Device location carries GRANTED consent.');
tie_permission_assert($context->data['consent']['session_scope'] === true && $context->data['provenance']['ephemeral'] === true, 'Consent and provenance are session/ephemeral only.');
tie_permission_assert($context->data['provenance']['provider'] === 'browser' && $context->data['provenance']['coordinate_precision_decimal_places'] >= 1, 'Provider and coordinate precision are represented.');

$expired = UthengaTieLocationContracts::request([
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 15, 'captured_at' => gmdate(DATE_ATOM, time() - 7200),
    'source' => 'browser_geolocation', 'platform' => 'browser', 'permission_state' => 'granted',
]);
$expiredContext = $kernel->location->context($expired);
tie_permission_assert($expiredContext->data['consent']['permission_state'] === 'EXPIRED' && $expiredContext->data['usable_for_nearby'] === false, 'Expired captured device location requires reacquisition.');

UthengaTieLocationPermissionContracts::request(['platform' => 'browser', 'permission_state' => 'denied']);
try {
    UthengaTieLocationPermissionContracts::request(['platform' => 'browser', 'permission_state' => 'granted']);
    throw new RuntimeException('DENIED/EXPIRED lifecycle incorrectly permitted a direct grant.');
} catch (UthengaTieException $error) {
    tie_permission_assert($error->type() === 'validation_error', 'Invalid permission transition is rejected.');
}

UthengaTieLocationContracts::request([
    'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 15, 'captured_at' => gmdate(DATE_ATOM),
    'source' => 'manual_location', 'permission_state' => 'NOT_REQUESTED',
]);
tie_permission_assert(UthengaTieLocationPermission::current()['state'] === 'DENIED', 'Manual locations do not overwrite device permission state.');
echo "TIE Phase 6.1/6.2 permission tests passed.\n";
