<?php
/**
 * Returns a normalised road route for a foreground location and a verified
 * marketplace pickup point. This endpoint never stores the coordinates.
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

function uthenga_tie_routing_location(array $input, string $source): UthengaTieLocationContext
{
    $latitude = $input['latitude'] ?? null; $longitude = $input['longitude'] ?? null;
    if (!is_numeric($latitude) || !is_numeric($longitude)) throw UthengaTieErrors::validation(['location' => 'Both route coordinates are required.']);
    return new UthengaTieLocationContext([
        'latitude' => (float) $latitude,
        'longitude' => (float) $longitude,
        'accuracy_m' => isset($input['accuracy_m']) && is_numeric($input['accuracy_m']) ? (float) $input['accuracy_m'] : null,
        'captured_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'source' => $source,
    ]);
}

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('routing'); UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('routing_route', UthengaTieConfig::integer('TIE_ROUTING_RATE_LIMIT', 30), 60, $requestId);
    $input = UthengaTieApi::input();
    $origin = uthenga_tie_routing_location(is_array($input['origin'] ?? null) ? $input['origin'] : [], 'browser_geolocation');
    $destination = uthenga_tie_routing_location(is_array($input['destination'] ?? null) ? $input['destination'] : [], 'vendor_location');
    $started = microtime(true); $route = (new UthengaTieKernel())->routing->route($origin, $destination)->toArray();
    $duration = round((microtime(true) - $started) * 1000, 2);
    UthengaTieMetrics::record('routing_requests', 1, $requestId, ['module' => 'routing', 'provider' => $route['provider'] ?? 'unknown', 'status' => 'ok']);
    UthengaTieMetrics::record('routing_latency_ms', $duration, $requestId, ['module' => 'routing', 'provider' => $route['provider'] ?? 'unknown', 'status' => 'ok']);
    UthengaTieObservability::log('routing.route_resolved', $requestId, ['module' => 'routing', 'provider' => $route['provider'] ?? 'unknown', 'duration_ms' => $duration]);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'route' => $route]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
