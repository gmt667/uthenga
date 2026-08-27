<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('query');
    $result = (new UthengaTieKernel())->query->categories();
    UthengaTieObservability::log('catalogue.categories_read', $requestId, ['module' => 'query', 'status' => 'ok', 'result_count' => count($result['categories'])]);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'query', 'resource' => 'categories', 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'data' => $result]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
