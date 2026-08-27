<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser();
    $kernel = new UthengaTieKernel();
    $query = UthengaTieApi::query();
    $runId = UthengaTieTransportPaymentContracts::runId($query['run_id'] ?? null);
    $result = ['ledger' => $kernel->transportPayments->ledger($query, $user['id']), 'readiness' => $kernel->transportPayments->runReadiness($runId, $user['id'])];
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
