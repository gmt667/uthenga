<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('passenger_action', UthengaTieConfig::integer('TIE_PASSENGER_ACTION_RATE_LIMIT', 60), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? '')));
    $service = (new UthengaTieKernel())->passengers;
    $result = match ($action) {
        'add_note' => $service->addNote($user['id'], (string) ($input['passenger_key'] ?? ''), $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported passenger action.']),
    };
    UthengaTieObservability::log('passenger.action', $requestId, ['module' => 'passenger', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
