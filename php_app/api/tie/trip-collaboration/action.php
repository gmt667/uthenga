<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('plans'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('trip_collaboration_action', UthengaTieConfig::integer('TIE_TRIP_COLLABORATION_RATE_LIMIT', 30), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? ''))); $service = (new UthengaTieKernel())->tripCollaboration;
    $result = match ($action) {
        'invite' => $service->invite($input, $user['id']),
        'change_role' => $service->changeRole($input, $user['id']),
        'revoke' => $service->revoke($input, $user['id']),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported trip collaboration action.']),
    };
    UthengaTieObservability::log('trip_collaboration.action', $requestId, ['module' => 'plans', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
