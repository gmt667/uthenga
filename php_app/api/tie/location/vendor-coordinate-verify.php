<?php
/** Administrator review for vendor-supplied marketplace coordinates. */
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
    UthengaTieApi::requireRateLimit('location_vendor_coordinate_review', UthengaTieConfig::integer('TIE_LOCATION_VENDOR_COORDINATE_REVIEW_RATE_LIMIT', 10), 60, $requestId);
    if (!in_array($user['role'], ADMIN_ROLES, true)) throw UthengaTieErrors::authorization();
    $input = UthengaTieApi::input();
    $serviceId = trim((string) ($input['service_id'] ?? ''));
    $status = strtolower(trim((string) ($input['verification_status'] ?? 'verified')));
    $note = trim((string) ($input['note'] ?? ''));
    if ($serviceId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $serviceId)) throw UthengaTieErrors::validation(['service_id' => 'A valid service_id is required.']);
    if (!in_array($status, ['verified', 'rejected'], true)) throw UthengaTieErrors::validation(['verification_status' => 'Verification status must be verified or rejected.']);
    if (strlen($note) > 500) throw UthengaTieErrors::validation(['note' => 'Review note must be at most 500 characters.']);
    $listing = dbQueryOne('SELECT location_source, location_accuracy_m, location_captured_at, gps_lat, gps_lng FROM listings WHERE id = ? LIMIT 1', [$serviceId]);
    if (!is_array($listing) || $listing['gps_lat'] === null || $listing['gps_lng'] === null) throw UthengaTieErrors::validation(['service_id' => 'A submitted coordinate is required before review.']);
    $verifiedAt = $status === 'verified' ? date('Y-m-d H:i:s') : null;
    dbExecute('UPDATE listings SET location_verification_status = ?, location_verified_at = ?, location_verified_by = ? WHERE id = ?', [$status, $verifiedAt, $status === 'verified' ? $user['id'] : null, $serviceId]);
    UthengaTieVendorCoordinateGovernance::audit($pdo, $serviceId, $user['id'], 'coordinate_' . $status, $listing['location_source'], $status, $listing['location_accuracy_m'] === null ? null : (float) $listing['location_accuracy_m'], $listing['location_captured_at'], $note === '' ? null : $note);
    UthengaTieObservability::log('location.vendor_coordinate_reviewed', $requestId, ['module' => 'location', 'status' => $status]);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'service_id' => $serviceId, 'coordinate_status' => $status]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
