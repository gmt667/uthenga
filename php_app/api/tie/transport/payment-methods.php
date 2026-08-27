<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    UthengaTieApi::requireFeature('bus_operations');
    $user = UthengaTieApi::requireAuthenticatedUser();
    $service = (new UthengaTieKernel())->customerPaymentMethods;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) (UthengaTieApi::query()['action'] ?? 'list'));
        // The frontend fires list+operators concurrently — release the
        // session lock before the (potentially slow) live PayChangu call so
        // one request never blocks the other behind PHP's file session lock.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        $result = match ($action) {
            'list' => $service->list($user['id']),
            'operators' => $service->listOperators(),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown payment method action.']),
        };
        UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'GET or POST is required.']);
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('customer_payment_methods', 5, 60, $requestId);
    $input = UthengaTieApi::input();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'add_mobile_money' => $service->addMobileMoney($user['id'], $input),
        'add_bank_transfer' => $service->addBankTransfer($user['id']),
        'remove' => $service->remove($user['id'], trim((string) ($input['id'] ?? ''))),
        'set_default' => $service->setDefault($user['id'], trim((string) ($input['id'] ?? ''))),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown payment method action.']),
    };
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
