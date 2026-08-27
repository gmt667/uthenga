<?php
/** Phase 7 deterministic ranking endpoint; it never books, reserves, or invokes an LLM. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    $startedAt = microtime(true);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('recommendations');
    UthengaTieApi::requireFeature('context');
    UthengaTieApi::requireFeature('query');
    UthengaTieApi::requireFeature('availability');
    $user = UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('recommendations', UthengaTieConfig::integer('TIE_RECOMMENDATION_RATE_LIMIT', 10), 60, $requestId);
    $request = UthengaTieRecommendationContracts::request(UthengaTieApi::input(), $user['id']);
    if ($request->contextRequest->location !== null) UthengaTieApi::requireFeature('location');
    $kernel = new UthengaTieKernel();
    $context = $kernel->travelContext->build($user['id'], $request->contextRequest);
    $result = $kernel->recommendation->rank($request, $context);
    $data = $result->toArray(); $diagnostics = $data['diagnostics'];
    $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
    UthengaTieObservability::log('recommendation.ranked', $requestId, ['module' => 'recommendation', 'status' => 'ok', 'duration_ms' => $durationMs, 'candidate_count' => $diagnostics['input_candidate_count'], 'eligible_count' => $diagnostics['recommended_count'], 'rejected_count' => $diagnostics['excluded_count'], 'ranking_version' => $data['metadata']['ranking_version']]);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'recommendation', 'feature' => 'ranking', 'status' => 'ok']);
    UthengaTieMetrics::record('recommendation_candidates', $diagnostics['input_candidate_count'], $requestId, ['module' => 'recommendation', 'feature' => 'ranking', 'status' => 'ok']);
    UthengaTieMetrics::record('recommendation_excluded', $diagnostics['excluded_count'], $requestId, ['module' => 'recommendation', 'feature' => 'ranking', 'status' => 'ok']);
    UthengaTieMetrics::record('recommendation_latency_ms', $durationMs, $requestId, ['module' => 'recommendation', 'feature' => 'ranking', 'status' => 'ok']);
    UthengaTieMetrics::record('recommendation_successful_responses', 1, $requestId, ['module' => 'recommendation', 'feature' => 'ranking', 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'recommendation' => $data]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
