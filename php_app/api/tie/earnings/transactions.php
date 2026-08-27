<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser();
    $period = UthengaTieEarningsContracts::period($_GET['period'] ?? 'today');
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'transactions' => (new UthengaTieKernel())->earnings->transactions($user['id'], $period)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
