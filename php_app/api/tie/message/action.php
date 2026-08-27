<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('message_action', UthengaTieConfig::integer('TIE_MESSAGE_ACTION_RATE_LIMIT', 60), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? '')));
    $service = (new UthengaTieKernel())->messaging;
    $result = match ($action) {
        'mark_read' => $service->markRead($user['id'], UthengaTieMessagingContracts::eventId($input['event_id'] ?? null)),
        'mark_all_read' => $service->markAllRead($user['id']),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported message action.']),
    };
    UthengaTieObservability::log('message.action', $requestId, ['module' => 'messaging', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
