<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('query');
    $criteria = UthengaTieCatalogueContracts::vendors(UthengaTieApi::query());
    $result = (new UthengaTieKernel())->query->vendors($criteria);
    UthengaTieObservability::log('catalogue.vendors_read', $requestId, ['module' => 'query', 'status' => 'ok', 'result_count' => count($result['vendors'])]);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'query', 'resource' => 'vendors', 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'query' => $criteria->toArray(), 'data' => $result]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
