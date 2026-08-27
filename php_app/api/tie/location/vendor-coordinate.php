<?php
/** Explicit vendor/admin coordinate enrichment; never stores customer location. */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('location');
    $user = UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('location_vendor_coordinate', UthengaTieConfig::integer('TIE_LOCATION_VENDOR_COORDINATE_RATE_LIMIT', 10), 60, $requestId);
    $input = UthengaTieApi::input();
    $serviceId = trim((string) ($input['service_id'] ?? ''));
    if ($serviceId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $serviceId)) throw UthengaTieErrors::validation(['service_id' => 'A valid service_id is required.']);
    $isAdmin = in_array($user['role'], ADMIN_ROLES, true);
    if (!$isAdmin && !in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();
    $source = UthengaTieVendorCoordinateGovernance::source((string) ($input['source'] ?? 'vendor_input'), $isAdmin);
    $location = UthengaTieLocationContracts::request([
        'latitude' => $input['latitude'] ?? null, 'longitude' => $input['longitude'] ?? null,
        'accuracy_m' => $input['accuracy_m'] ?? null, 'captured_at' => $input['captured_at'] ?? gmdate(DATE_ATOM),
        'source' => 'vendor_location', 'permission_state' => 'NOT_REQUESTED',
    ]);
    $listing = dbQueryOne('SELECT vendor_id FROM listings WHERE id = ? LIMIT 1', [$serviceId]);
    if (!is_array($listing) || (!$isAdmin && (string) $listing['vendor_id'] !== $user['id'])) throw UthengaTieErrors::authorization();
    $status = $source === 'admin_verified' ? 'verified' : 'pending_review';
    $capturedAt = (new DateTimeImmutable($location->data['captured_at']))->format('Y-m-d H:i:s');
    $verifiedAt = $status === 'verified' ? date('Y-m-d H:i:s') : null;
    dbExecute('UPDATE listings SET gps_lat = ?, gps_lng = ?, location_source = ?, location_accuracy_m = ?, location_captured_at = ?, location_verified_at = ?, location_verification_status = ?, location_verified_by = ? WHERE id = ?', [
        $location->data['latitude'], $location->data['longitude'], $source, $location->data['accuracy_m'] ?? null, $capturedAt, $verifiedAt, $status, $status === 'verified' ? $user['id'] : null, $serviceId,
    ]);
    UthengaTieVendorCoordinateGovernance::audit($pdo, $serviceId, $user['id'], 'coordinate_submitted', $source, $status, $location->data['accuracy_m'] ?? null, $capturedAt);
    UthengaTieObservability::log('location.vendor_coordinate_updated', $requestId, ['module' => 'location', 'status' => $status]);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'service_id' => $serviceId, 'coordinate_status' => $status]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
