<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    UthengaTieApi::requireFeature('bus_operations');
    $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();
    $finance = (new UthengaTieKernel())->busFinance;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'overview'));
        $result = match ($action) {
            'overview' => $finance->overview($user['id']),
            'trend' => $finance->trend($user['id'], (int) ($_GET['days'] ?? 30)),
            'transactions' => $finance->transactions($user['id'], (int) ($_GET['limit'] ?? 25)),
            'accounts' => $finance->accounts($user['id']),
            'banks' => $finance->supportedBanks(),
            'withdrawals' => $finance->withdrawals($user['id']),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown finance action.']),
        };
        bus_ops_respond($requestId, 'result', $result);
        exit;
    }

    $input = bus_ops_write('bus_ops_finance', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'save_account' => $finance->saveAccount($user['id'], $input),
        'request_withdrawal' => $finance->requestWithdrawal($user['id'], $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown finance action.']),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
