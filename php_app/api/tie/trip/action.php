<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('trip_engine_action', UthengaTieConfig::integer('TIE_TRIP_ENGINE_ACTION_RATE_LIMIT', 60), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? ''))); $service = (new UthengaTieKernel())->trips;
    $result = match ($action) {
        'create_manual_trip' => $service->createManualTrip($input, $user['id']),
        'accept' => $service->acceptTrip(UthengaTieTripEngineContracts::tripId($input['trip_id'] ?? null), $user['id']),
        'advance' => $service->advance(UthengaTieTripEngineContracts::tripId($input['trip_id'] ?? null), $user['id'], (string) ($input['target_status'] ?? '')),
        'complete' => $service->completeTrip(UthengaTieTripEngineContracts::tripId($input['trip_id'] ?? null), $user['id'], $input),
        'cancel' => $service->cancelTrip(UthengaTieTripEngineContracts::tripId($input['trip_id'] ?? null), $user['id'], $input),
        'no_show' => $service->markNoShow(UthengaTieTripEngineContracts::tripId($input['trip_id'] ?? null), $user['id']),
        'set_online' => $service->setOnlineStatus($user['id'], filter_var($input['is_online'] ?? false, FILTER_VALIDATE_BOOLEAN)),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported trip action.']),
    };
    UthengaTieObservability::log('trip_engine.action', $requestId, ['module' => 'trip_engine', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
