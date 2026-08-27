<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']); UthengaTieApi::requireFeature('payments'); $user = UthengaTieApi::requireAuthenticatedUser();
    $intent = (new UthengaTieKernel())->payments->status(UthengaTiePaymentContracts::intentId(UthengaTieApi::query()), $user['id'])->toArray(); UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'payment_intent' => $intent]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
