<?php
/** Public provider endpoint: no browser session, CSRF token, or response details. */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/payment_engine.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel');
    $signature = trim((string) ($_SERVER['HTTP_SIGNATURE'] ?? ($_SERVER['HTTP_X_PAYCHANGU_SIGNATURE'] ?? '')));
    $payload = (string) file_get_contents('php://input');
    $result = (new UthengaTieKernel())->transportPayments->receiveWebhook($payload, $signature);
    UthengaTieObservability::log('transport_payment.webhook_processed', $requestId, ['module' => 'transport_payment', 'provider' => 'paychangu', 'processed' => (bool) ($result['processed'] ?? false)]);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
