<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = bus_ops_context();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $filters = ['listing_id' => (string) ($_GET['listing_id'] ?? ''), 'from_date' => (string) ($_GET['from_date'] ?? '')];
        bus_ops_respond($requestId, 'result', $service->listDepartures($user['id'], $filters));
        exit;
    }

    $input = bus_ops_write('bus_ops_departures', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'create' => $service->createDeparture($user['id'], $input),
        'cancel' => $service->cancelDeparture($user['id'], (string) ($input['departure_id'] ?? ''), $input['reason'] ?? null),
        'assign' => (new UthengaTieKernel())->busFleet->assignDeparture($user['id'], (string) ($input['departure_id'] ?? ''), $input['vehicle_id'] ?? null, $input['driver_id'] ?? null),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown departure action.']),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
