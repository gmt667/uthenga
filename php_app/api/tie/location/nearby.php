<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    $startedAt = microtime(true);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('location');
    UthengaTieApi::requireFeature('availability');
    UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('location_nearby', UthengaTieConfig::integer('TIE_LOCATION_NEARBY_RATE_LIMIT', 10), 60, $requestId);
    $request = UthengaTieNearbyContracts::request(UthengaTieApi::input());
    $result = (new UthengaTieKernel())->nearby->search($request);
    $diagnostics = $result['search_diagnostics'];
    $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
    UthengaTieObservability::log('location.nearby_read', $requestId, ['module' => 'location', 'status' => 'ok', 'duration_ms' => $durationMs, 'candidate_count' => $diagnostics['candidate_count'], 'eligible_count' => $diagnostics['eligible_service_count'], 'rejected_count' => $diagnostics['validation_rejection_count'], 'radius_km' => $diagnostics['radius_km']]);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'ok']);
    UthengaTieMetrics::record('nearby_search_latency_ms', $durationMs, $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'ok']);
    UthengaTieMetrics::record('nearby_candidates', $diagnostics['candidate_count'], $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'ok']);
    UthengaTieMetrics::record('nearby_eligible', $diagnostics['eligible_service_count'], $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'ok']);
    UthengaTieMetrics::record('nearby_validation_rejections', $diagnostics['validation_rejection_count'], $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'ok']);
    UthengaTieMetrics::record('nearby_missing_coordinates', $diagnostics['missing_coordinate_count'], $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'ok']);
    UthengaTieMetrics::record('nearby_radius_km', $diagnostics['radius_km'], $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'ok']);
    UthengaTieMetrics::record('nearby_successful_responses', 1, $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'nearby' => $result]);
} catch (Throwable $error) {
    if ($error instanceof UthengaTieException && $error->type() === 'provider_error') {
        UthengaTieMetrics::record('provider_failures', 1, $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'geographic_search_unavailable']);
        UthengaTieMetrics::record('location_fallback_usage', 1, $requestId, ['module' => 'location', 'feature' => 'nearby', 'status' => 'catalogue_search']);
        $payload = UthengaTieErrors::response($error, $requestId);
        $payload['fallback'] = UthengaTieLocationFailureHandling::geographicSearchUnavailable();
        $status = (int) $payload['_http_status'];
        unset($payload['_http_status']);
        UthengaTieApi::respond($payload, $status);
    }
    UthengaTieApi::handleError($error, $requestId);
}
