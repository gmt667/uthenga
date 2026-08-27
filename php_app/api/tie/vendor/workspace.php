<?php
/** Read-only React vendor-workspace projection. Marketplace records remain PHP/MySQL authority. */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('vendor_profiles'); $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();
    $profiles = (new UthengaTieKernel())->vendorProfiles->dashboard($user['id']);
    $inventory = dbQuery('SELECT id, listing_type, title, location, image, is_active, created_at FROM listings WHERE vendor_id=? ORDER BY created_at DESC LIMIT 30', [$user['id']]);
    $bookingCount = 0; $revenue = 0.0;
    if (uthenga_table_exists('booking_items')) {
        $summary = dbQueryOne('SELECT COUNT(DISTINCT b.id) AS booking_count, COALESCE(SUM(b.grand_total), 0) AS revenue FROM booking_items bi INNER JOIN bookings b ON b.id=bi.booking_id WHERE bi.vendor_id=?', [$user['id']]) ?: [];
        $bookingCount = (int) ($summary['booking_count'] ?? 0); $revenue = (float) ($summary['revenue'] ?? 0);
    }
    $activeServices = count(array_filter($profiles['profiles'] ?? [], static fn(array $profile): bool => !empty($profile['active'])));
    UthengaTieObservability::log('vendor.workspace_read', $requestId, ['module' => 'vendor_workspace', 'profile_count' => count($profiles['profiles'] ?? []), 'inventory_count' => count($inventory)]);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'workspace' => [
        'schema_version' => 'tie-vendor-workspace/v1',
        'profiles' => $profiles,
        'inventory' => array_map(static fn(array $item): array => ['id' => (string) $item['id'], 'type' => (string) $item['listing_type'], 'title' => (string) $item['title'], 'location' => (string) ($item['location'] ?? ''), 'image' => $item['image'] ? (string) $item['image'] : null, 'active' => (bool) $item['is_active'], 'created_at' => $item['created_at'] ? gmdate('c', strtotime($item['created_at'] . ' UTC')) : null], $inventory),
        'metrics' => ['active_services' => $activeServices, 'inventory_count' => count($inventory), 'booking_count' => $bookingCount, 'gross_revenue' => $revenue, 'currency' => APP_CURRENCY],
    ]]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
