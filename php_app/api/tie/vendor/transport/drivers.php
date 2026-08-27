<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    UthengaTieApi::requireFeature('bus_operations');
    $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();
    $fleet = (new UthengaTieKernel())->busFleet;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'list'));
        $result = match ($action) {
            'list' => $fleet->listDrivers($user['id']),
            'overview' => $fleet->driverOverview($user['id']),
            'assignments' => $fleet->driverAssignments($user['id'], (string) ($_GET['driver_id'] ?? '')),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown driver action.']),
        };
        bus_ops_respond($requestId, 'result', $result);
        exit;
    }

    $input = bus_ops_write('bus_ops_drivers', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'create' => $fleet->createDriver($user['id'], $input),
        'update' => $fleet->updateDriver($user['id'], $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown driver action.']),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
