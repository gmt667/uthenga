<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf(); UthengaTieApi::requireRateLimit('coordination_action', UthengaTieConfig::integer('TIE_COORDINATION_ACTION_RATE_LIMIT', 40), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? ''))); $service = (new UthengaTieKernel())->coordination;
    $result = match ($action) {
        'create_run' => $service->createRun($input, $user['id']),
        'request_seat' => $service->request($input, $user['id']),
        'vendor_decision' => $service->vendorDecision($input, $user['id']),
        // The endpoint action selects the handler; the customer transition is
        // carried separately so it cannot be mistaken for the endpoint action.
        'customer_action' => $service->customerAction(array_replace($input, ['action' => $input['customer_action'] ?? null]), $user['id']),
        'update_run' => $service->updateRun($input, $user['id']),
        'location' => $service->location($input, $user['id']),
        'message' => $service->sendMessage($input, $user['id']),
        'request_call' => $service->requestCall($input, $user['id']),
        'decide_call' => $service->decideCall($input, $user['id']),
        'call_signal' => $service->postSignal($input, $user['id']),
        'confirm_boarding' => $service->confirmBoarding($input, $user['id']),
        'mark_no_show' => $service->vendorMarkNoShow($input, $user['id']),
        'report_issue' => $service->reportIssue($input, $user['id']),
        'add_walk_in' => $service->addWalkIn($input, $user['id']),
        'confirm_dropped_off' => $service->confirmDroppedOff($input, $user['id']),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported Quick Travel action.']),
    };
    UthengaTieObservability::log('coordination.action', $requestId, ['module' => 'quick_travel', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
