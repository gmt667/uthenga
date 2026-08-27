<?php
/** Public provider endpoint: no browser session, CSRF token, or response details. */
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    // A browser return is never payment proof. Forward it to the authenticated
    // status page; POST notifications retain signed webhook validation below.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $intentId = trim((string) ($_GET['payment_intent_id'] ?? ''));
        if (!preg_match('/^[a-f0-9-]{36}$/i', $intentId)) throw UthengaTieErrors::validation(['payment_intent_id' => 'A valid payment intent ID is required.']);
        header('Location: ' . BASE_URL . 'tie-payment-return.php?payment_intent_id=' . rawurlencode($intentId), true, 303);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']); UthengaTieApi::requireFeature('payments');
    $signature = trim((string) ($_SERVER['HTTP_SIGNATURE'] ?? '')); $payload = (string) file_get_contents('php://input'); $result = (new UthengaTieKernel())->payments->receiveWebhook($payload, $signature);
    UthengaTieObservability::log('payment.webhook_processed', $requestId, ['module' => 'payments', 'provider' => 'paychangu', 'status' => (string) ($result['status'] ?? ($result['duplicate'] ?? false ? 'duplicate' : 'accepted'))]); UthengaTieApi::respond(['success' => true]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
