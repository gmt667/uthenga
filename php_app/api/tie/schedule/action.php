<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('schedule_action', UthengaTieConfig::integer('TIE_SCHEDULE_ACTION_RATE_LIMIT', 60), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? '')));
    $service = (new UthengaTieKernel())->schedule;
    $result = match ($action) {
        'start_shift' => $service->startShift($user['id']),
        'end_shift' => $service->endShift($user['id']),
        'save_availability' => $service->saveAvailability($user['id'], $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported schedule action.']),
    };
    UthengaTieObservability::log('schedule.action', $requestId, ['module' => 'schedule', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
