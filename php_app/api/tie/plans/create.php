<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    foreach (['plans', 'recommendations', 'context', 'query', 'availability'] as $feature) UthengaTieApi::requireFeature($feature);
    $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf(); UthengaTieApi::requireRateLimit('plans', UthengaTieConfig::integer('TIE_PLAN_RATE_LIMIT', 10), 60, $requestId);
    $request = UthengaTiePlanContracts::create(UthengaTieApi::input(), $user['id']); if ($request->recommendationRequest->contextRequest->location !== null) UthengaTieApi::requireFeature('location');
    $started = microtime(true); $result = (new UthengaTieKernel())->plans->create($request, $user['id'])->toArray(); $duration = round((microtime(true) - $started) * 1000, 2);
    UthengaTieObservability::log('plan.created', $requestId, ['module' => 'planning', 'feature' => 'plans', 'status' => 'ok', 'duration_ms' => $duration, 'candidate_count' => $result['diagnostics']['recommendations_consumed'], 'rejected_count' => $result['diagnostics']['conflicts_detected']]);
    UthengaTieMetrics::record('plan_create_latency_ms', $duration, $requestId, ['module' => 'planning', 'feature' => 'plans', 'status' => 'ok']); UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'plan' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
