<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('trip_planner');
    $user = UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    $request = UthengaTieContracts::tripRequest(UthengaTieApi::input(), $user['id']);
    $kernel = new UthengaTieKernel();
    $context = $kernel->context->getForUser($user['id'], $user['role']);
    $plan = $kernel->tripPlanning->createDraft($request, $context);
    UthengaTieObservability::log('trip.draft_created', $requestId, ['module' => 'trip_planning', 'status' => 'ok']);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'trip_planning', 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'trip_plan' => $plan->toArray()], 201);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
