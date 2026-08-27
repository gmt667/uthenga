<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'list' => (new UthengaTieKernel())->passengers->list($user['id'], UthengaTieApi::query())]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
