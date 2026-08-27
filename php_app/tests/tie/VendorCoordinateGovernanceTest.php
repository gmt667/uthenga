<?php
/** Phase 6.13 verified-coordinate and audit-flow coverage; all writes roll back. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_vendor_coordinate_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

tie_vendor_coordinate_assert($pdo instanceof PDO, 'A configured database is required for coordinate-governance tests.');
$columns = $pdo->query("SHOW COLUMNS FROM listings LIKE 'location_verification_status'")->fetchAll();
tie_vendor_coordinate_assert(count($columns) === 1, 'Coordinate verification migration is present.');
$listing = dbQueryOne("SELECT l.id FROM listings l INNER JOIN users u ON u.id = l.vendor_id WHERE l.is_active = 1 AND u.is_approved = 1 AND u.account_status = 'active' LIMIT 1");
tie_vendor_coordinate_assert(is_array($listing), 'An eligible seed listing is required.');

$pdo->beginTransaction();
try {
    $listingId = (string) $listing['id'];
    $capturedAt = gmdate('Y-m-d H:i:s');
    $pdo->prepare("UPDATE listings SET gps_lat = ?, gps_lng = ?, location_captured_at = ?, location_verified_at = NULL, location_verification_status = 'pending_review' WHERE id = ?")->execute([-13.9626, 33.7741, $capturedAt, $listingId]);
    $service = new UthengaTieQueryService($pdo);
    $criteria = UthengaTieCatalogueContracts::services(['latitude' => -13.9626, 'longitude' => 33.7741, 'radius_km' => 5, 'page' => 1, 'page_size' => 20]);
    $pending = $service->search($criteria);
    tie_vendor_coordinate_assert(!in_array($listingId, array_column($pending['candidates'], 'id'), true), 'Pending coordinates are excluded from precision radius search.');

    $pdo->prepare("UPDATE listings SET location_verification_status = 'verified', location_verified_at = ? WHERE id = ?")->execute([$capturedAt, $listingId]);
    $verified = $service->search($criteria);
    tie_vendor_coordinate_assert(in_array($listingId, array_column($verified['candidates'], 'id'), true), 'Verified coordinates participate in radius search.');
    UthengaTieVendorCoordinateGovernance::audit($pdo, $listingId, 'admin-test', 'coordinate_verified', 'vendor_gps', 'verified', 10.0, gmdate('Y-m-d H:i:s'), 'test review');
    tie_vendor_coordinate_assert((int) $pdo->query("SELECT COUNT(*) FROM listing_location_audit WHERE listing_id = " . $pdo->quote($listingId))->fetchColumn() >= 1, 'Coordinate review action is auditable.');
    $pdo->rollBack();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}
echo "TIE Phase 6.13 vendor coordinate governance tests passed.\n";
