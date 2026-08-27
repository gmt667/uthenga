<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('direct_message_action', UthengaTieConfig::integer('TIE_DIRECT_MESSAGE_ACTION_RATE_LIMIT', 60), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? ''))); $service = (new UthengaTieKernel())->messaging;
    $result = match ($action) {
        'start' => $service->startDirectThread($input, $user['id']),
        'send' => $service->sendDirectMessage($input, $user['id']),
        'mark_read' => $service->markDirectThreadRead($input, $user['id']),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported direct message action.']),
    };
    UthengaTieObservability::log('direct_message.action', $requestId, ['module' => 'quick_travel', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
