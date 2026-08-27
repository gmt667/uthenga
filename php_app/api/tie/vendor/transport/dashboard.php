<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = bus_ops_context();
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    bus_ops_respond($requestId, 'result', $service->dashboard($user['id']));
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
