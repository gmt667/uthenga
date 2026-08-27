<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('context');
    $user = UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    $request = UthengaTieContextContracts::build(UthengaTieApi::input(), $user['id']);
    $context = (new UthengaTieKernel())->travelContext->build($user['id'], $request);
    UthengaTieObservability::log('context.built', $requestId, ['module' => 'context', 'status' => 'ok', 'duration_ms' => $context->data['metadata']['duration_ms']]);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'context', 'feature' => 'build', 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'context' => $context->toArray()]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
