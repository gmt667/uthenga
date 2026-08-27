<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('vehicle_action', UthengaTieConfig::integer('TIE_VEHICLE_ACTION_RATE_LIMIT', 60), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? '')));
    $service = (new UthengaTieKernel())->vehicle;
    $result = match ($action) {
        'save_profile' => $service->saveProfile($user['id'], $input),
        'set_status' => $service->setStatus($user['id'], (string) ($input['status'] ?? 'active')),
        'update_mileage' => $service->updateMileage($user['id'], $input['current_mileage_km'] ?? null),
        'save_document' => $service->saveDocument($user['id'], $input),
        'add_maintenance' => $service->addMaintenance($user['id'], $input),
        'report_issue' => $service->reportIssue($user['id'], $input),
        'resolve_issue' => $service->resolveIssue($user['id'], $input['issue_id'] ?? null),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported vehicle action.']),
    };
    UthengaTieObservability::log('vehicle.action', $requestId, ['module' => 'vehicle', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
