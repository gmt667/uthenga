<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf(); UthengaTieApi::requireRateLimit('quick_travel_discover', UthengaTieConfig::integer('TIE_COORDINATION_ACTION_RATE_LIMIT', 40), 60, $requestId);
    $result = (new UthengaTieKernel())->coordination->discover(UthengaTieApi::input(), $user['id']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'discovery' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
