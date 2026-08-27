<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = bus_ops_context();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        bus_ops_respond($requestId, 'result', $service->listRoutes($user['id']));
        exit;
    }

    $input = bus_ops_write('bus_ops_routes', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'create' => $service->createRoute($user['id'], $user['name'], $input),
        'update' => $service->updateRoute($user['id'], $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown route action.']),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
