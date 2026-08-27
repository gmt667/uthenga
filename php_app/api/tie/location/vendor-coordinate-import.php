<?php
/** Controlled bulk import for marketplace coordinates; imported rows require later review. */
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
    UthengaTieApi::requireRateLimit('location_vendor_coordinate_import', UthengaTieConfig::integer('TIE_LOCATION_VENDOR_COORDINATE_IMPORT_RATE_LIMIT', 10), 60, $requestId);
    if (!in_array($user['role'], ADMIN_ROLES, true)) throw UthengaTieErrors::authorization();
    if (!$pdo instanceof PDO) throw new RuntimeException('A database connection is required for coordinate import.');

    $input = UthengaTieApi::input();
    $entries = $input['entries'] ?? null;
    $maxEntries = max(1, min(500, UthengaTieConfig::integer('TIE_VENDOR_COORDINATE_IMPORT_MAX_ENTRIES', 100)));
    if (!is_array($entries) || $entries === [] || count($entries) > $maxEntries) {
        throw UthengaTieErrors::validation(['entries' => "entries must contain between 1 and {$maxEntries} coordinate records."]);
    }
    UthengaTieVendorCoordinateGovernance::source('imported', true);
    $listingLookup = $pdo->prepare('SELECT id FROM listings WHERE id = ? LIMIT 1');
    $update = $pdo->prepare('UPDATE listings SET gps_lat = ?, gps_lng = ?, location_source = ?, location_accuracy_m = ?, location_captured_at = ?, location_verified_at = NULL, location_verification_status = ?, location_verified_by = NULL WHERE id = ?');

    $pdo->beginTransaction();
    $importedIds = [];
    try {
        foreach ($entries as $index => $entry) {
            if (!is_array($entry)) throw UthengaTieErrors::validation(["entries.{$index}" => 'Each import entry must be an object.']);
            $serviceId = trim((string) ($entry['service_id'] ?? ''));
            if ($serviceId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $serviceId)) throw UthengaTieErrors::validation(["entries.{$index}.service_id" => 'A valid service_id is required.']);
            $listingLookup->execute([$serviceId]);
            if (!$listingLookup->fetch()) throw UthengaTieErrors::validation(["entries.{$index}.service_id" => 'Listing does not exist.']);
            $location = UthengaTieLocationContracts::request([
                'latitude' => $entry['latitude'] ?? null,
                'longitude' => $entry['longitude'] ?? null,
                'accuracy_m' => $entry['accuracy_m'] ?? null,
                'captured_at' => $entry['captured_at'] ?? gmdate(DATE_ATOM),
                'source' => 'vendor_location',
                'permission_state' => 'NOT_REQUESTED',
            ])->data;
            $capturedAt = (new DateTimeImmutable($location['captured_at']))->format('Y-m-d H:i:s');
            $update->execute([$location['latitude'], $location['longitude'], 'imported', $location['accuracy_m'] ?? null, $capturedAt, 'pending_review', $serviceId]);
            UthengaTieVendorCoordinateGovernance::audit($pdo, $serviceId, $user['id'], 'coordinate_imported', 'imported', 'pending_review', $location['accuracy_m'] ?? null, $capturedAt, 'bulk import');
            $importedIds[] = $serviceId;
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    UthengaTieObservability::log('location.vendor_coordinate_imported', $requestId, ['module' => 'location', 'status' => 'pending_review']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'imported_count' => count($importedIds), 'coordinate_status' => 'pending_review']);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
