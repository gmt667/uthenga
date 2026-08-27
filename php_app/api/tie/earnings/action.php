<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('earnings_action', UthengaTieConfig::integer('TIE_EARNINGS_ACTION_RATE_LIMIT', 30), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? '')));
    $service = (new UthengaTieKernel())->earnings;
    $result = match ($action) {
        'set_goal' => $service->setGoal($user['id'], $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported earnings action.']),
    };
    UthengaTieObservability::log('earnings.action', $requestId, ['module' => 'earnings', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
