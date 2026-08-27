<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    if (!UthengaTieConfig::boolean('TIE_ENABLED')) throw UthengaTieErrors::featureDisabled('tie');
    $user = UthengaTieApi::requireAuthenticatedUser();
    $kernel = new UthengaTieKernel();
    $context = $kernel->context->getForUser($user['id'], $user['role']);
    UthengaTieObservability::log('context.read', $requestId, ['module' => 'context', 'status' => 'ok']);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'context', 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'context' => $context->toArray()]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
