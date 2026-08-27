<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser();
    $callRequestId = UthengaTieCoordinationContracts::callRequestId($_GET['call_request_id'] ?? null);
    $sinceId = (int) ($_GET['since_id'] ?? 0);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => (new UthengaTieKernel())->coordination->signals($callRequestId, $user['id'], $sinceId)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
