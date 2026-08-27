<?php
/** Payment choices are derived from the user's approved plan and active inventory. */
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    foreach (['payments', 'plans'] as $feature) UthengaTieApi::requireFeature($feature);
    $user = UthengaTieApi::requireAuthenticatedUser(); $planId = UthengaTiePlanContracts::planId(UthengaTieApi::query());
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'payment_options' => (new UthengaTieKernel())->payments->options($planId, $user['id'])]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
