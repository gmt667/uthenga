<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    UthengaTieApi::requireFeature('bus_operations');
    $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();
    $fleet = (new UthengaTieKernel())->busFleet;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'vehicles'));
        $result = match ($action) {
            'vehicles' => $fleet->listVehicles($user['id']),
            'overview' => $fleet->fleetOverview($user['id']),
            'documents' => $fleet->documents($user['id'], (string) ($_GET['vehicle_id'] ?? '')),
            'maintenance' => $fleet->maintenanceHistory($user['id'], !empty($_GET['vehicle_id']) ? (string) $_GET['vehicle_id'] : null),
            'issues' => $fleet->issues($user['id'], !empty($_GET['vehicle_id']) ? (string) $_GET['vehicle_id'] : null, !empty($_GET['status']) ? (string) $_GET['status'] : null),
            'assignments' => $fleet->vehicleAssignments($user['id'], (string) ($_GET['vehicle_id'] ?? '')),
            'maintenance_overview' => $fleet->maintenanceOverview($user['id']),
            'all_document_issues' => $fleet->allDocumentIssues($user['id']),
            'assignment_eligibility' => $fleet->assignmentEligibility($user['id'], !empty($_GET['departure_id']) ? (string) $_GET['departure_id'] : null, !empty($_GET['departure_at']) ? (string) $_GET['departure_at'] : null),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown fleet action.']),
        };
        bus_ops_respond($requestId, 'result', $result);
        exit;
    }

    $input = bus_ops_write('bus_ops_fleet', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'create_vehicle' => $fleet->createVehicle($user['id'], $input),
        'update_vehicle' => $fleet->updateVehicle($user['id'], $input),
        'save_document' => $fleet->saveDocument($user['id'], $input),
        'log_maintenance' => $fleet->logMaintenance($user['id'], $input),
        'report_issue' => $fleet->reportIssue($user['id'], $input),
        'resolve_issue' => $fleet->resolveIssue($user['id'], (int) ($input['issue_id'] ?? 0)),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown fleet action.']),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
