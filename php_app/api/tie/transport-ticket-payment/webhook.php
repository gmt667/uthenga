<?php
/** Public provider endpoint: no browser session, CSRF token, or response details. */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';
require_once __DIR__ . '/../../../includes/payment_engine.php';
require_once __DIR__ . '/../../../includes/financial_controls.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('bus_operations');
    if (!uthenga_financial_callback_commit_allowed()) { uthenga_financial_callback_block('bus_ticket_payment_webhook'); throw UthengaTieErrors::providerUnavailable('financial_callback_controls'); }
    $signature = trim((string) ($_SERVER['HTTP_SIGNATURE'] ?? ($_SERVER['HTTP_X_PAYCHANGU_SIGNATURE'] ?? '')));
    $payload = (string) file_get_contents('php://input');
    $result = (new UthengaTieKernel())->busOperations->receiveWebhook($payload, $signature);
    UthengaTieObservability::log('bus_ticket_payment.webhook_processed', $requestId, ['module' => 'bus_operations', 'provider' => 'paychangu', 'payment_status' => (string) ($result['payment_status'] ?? '')]);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
