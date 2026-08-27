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
    UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('location_context', UthengaTieConfig::integer('TIE_LOCATION_CONTEXT_RATE_LIMIT', 20), 60, $requestId);
    $request = UthengaTieLocationContextApiContracts::request(UthengaTieApi::input());
    if ($request->fallback !== null) {
        $response = UthengaTieLocationContextPublicResponse::fallback($request->fallback);
        $status = strtolower((string) $request->fallback['permission_state']);
        UthengaTieMetrics::record('location_fallback_usage', 1, $requestId, ['module' => 'location', 'feature' => 'context', 'status' => $status]);
        if ($request->fallback['permission_state'] === 'DENIED') UthengaTieMetrics::record('location_permission_denied', 1, $requestId, ['module' => 'location', 'feature' => 'context', 'status' => 'denied']);
    } else {
        $context = (new UthengaTieKernel())->location->context($request->location);
        $response = UthengaTieLocationContextPublicResponse::fromContext($context);
        $status = strtolower((string) $context->data['confidence']);
    }
    $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
    UthengaTieObservability::log('location.context_read', $requestId, ['module' => 'location', 'status' => $status, 'duration_ms' => $durationMs]);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'location', 'feature' => 'context', 'status' => 'ok']);
    UthengaTieMetrics::record('location_context_latency_ms', $durationMs, $requestId, ['module' => 'location', 'feature' => 'context', 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'location_context' => $response]);
} catch (Throwable $error) {
    if ($error instanceof UthengaTieException && $error->type() === 'validation_error') UthengaTieMetrics::record('location_context_validation_failures', 1, $requestId, ['module' => 'location', 'feature' => 'context', 'status' => 'validation_error']);
    UthengaTieApi::handleError($error, $requestId);
}
