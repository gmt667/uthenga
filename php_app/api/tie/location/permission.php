<?php
/** Session-scoped permission lifecycle reporting; no coordinates are accepted. */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('location');
    UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('location_permission', UthengaTieConfig::integer('TIE_LOCATION_PERMISSION_RATE_LIMIT', 20), 60, $requestId);
    $permission = UthengaTieLocationPermissionContracts::request(UthengaTieApi::input());
    $fallback = $permission['state'] === 'GRANTED' ? null : UthengaTieLocationFallback::forPermission($permission['state']);
    UthengaTieObservability::log('location.permission_updated', $requestId, ['module' => 'location', 'status' => 'ok', 'permission_state' => $permission['state'], 'platform' => $permission['platform']]);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'location', 'feature' => 'permission', 'status' => 'ok']);
    if ($permission['state'] === 'DENIED') UthengaTieMetrics::record('location_permission_denied', 1, $requestId, ['module' => 'location', 'feature' => 'permission', 'status' => 'denied']);
    if ($fallback !== null) UthengaTieMetrics::record('location_fallback_usage', 1, $requestId, ['module' => 'location', 'feature' => 'permission', 'status' => strtolower($permission['state'])]);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'permission' => $permission, 'fallback' => $fallback]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
