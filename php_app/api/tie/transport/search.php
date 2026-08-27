<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('bus_operations');
    $query = UthengaTieApi::query();
    $filters = ['origin' => (string) ($query['origin'] ?? ''), 'destination' => (string) ($query['destination'] ?? ''), 'date' => (string) ($query['date'] ?? '')];
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => (new UthengaTieKernel())->busOperations->searchDepartures($filters)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
