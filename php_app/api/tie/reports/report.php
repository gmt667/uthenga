<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser();
    $type = (string) ($_GET['type'] ?? 'trips');
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'report' => (new UthengaTieKernel())->reports->report($user['id'], $type, $_GET)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
