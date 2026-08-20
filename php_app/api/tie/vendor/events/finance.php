<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Finance.php';
require_once __DIR__ . '/../../../../includes/tie/Kernel.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    $fin = new UthengaTieEventFinance($service->db());

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'overview'));
        if ($action === 'advisor') UthengaTieApi::requireRateLimit('finance_advisor', 10, 60, $requestId);
        $result = match ($action) {
            'overview' => $fin->overview($user['id'], $user['id']),
            'events' => $fin->events($user['id']),
            'transactions' => $fin->transactions($user['id'], [
                'q' => (string) ($_GET['q'] ?? ''), 'event' => (string) ($_GET['event'] ?? ''),
                'status' => (string) ($_GET['status'] ?? ''), 'method' => (string) ($_GET['method'] ?? ''),
                'from' => (string) ($_GET['from'] ?? ''), 'to' => (string) ($_GET['to'] ?? ''),
            ], (int) ($_GET['limit'] ?? 50), (int) ($_GET['offset'] ?? 0)),
            'transaction_detail' => $fin->transactionDetail($user['id'], (string) ($_GET['ref'] ?? '')),
            'revenue' => $fin->revenue($user['id'], [
                'range' => (string) ($_GET['range'] ?? '30d'),
                'from' => (string) ($_GET['from'] ?? ''), 'to' => (string) ($_GET['to'] ?? ''),
            ]),
            'refunds' => $fin->refunds($user['id']),
            'fees' => $fin->fees($user['id']),
            'settlements' => $fin->settlements($user['id']),
            'withdrawals' => $fin->withdrawals($user['id']),
            'accounts' => $fin->accounts($user['id']),
            'documents' => $fin->documents($user['id']),
            'document' => $fin->document($user['id'], (string) ($_GET['id'] ?? '')),
            'reconciliation' => $fin->reconciliationStatus($user['id']),
            'exceptions' => $fin->exceptions($user['id'], (string) ($_GET['status'] ?? 'OPEN')),
            'advisor' => $fin->advisor($user['id'], (string) ($_GET['message'] ?? 'Ask about my event finance.')),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown finance action.']),
        };
        events_v2_respond($requestId, 'finance_result', $result);
    }

    $input = events_v2_write('finance_ops', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    if ($action === 'csv_export') {
        $csv = $fin->exportCsv($user['id'], [
            'q' => (string) ($input['q'] ?? ''), 'event' => (string) ($input['event'] ?? ''),
            'status' => (string) ($input['status'] ?? ''), 'method' => (string) ($input['method'] ?? ''),
            'from' => (string) ($input['from'] ?? ''), 'to' => (string) ($input['to'] ?? ''),
        ]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="uthenga-transactions-' . date('Ymd-His') . '.csv"');
        echo $csv;
        exit;
    }
    $result = match ($action) {
        'batch_create' => $fin->createBatch($user['id'], $user['id']),
        'batch_paid' => $fin->markBatchPaid($user['id'], $user['id'], $input),
        'account_save' => $fin->saveAccount($user['id'], $user['id'], $input),
        'withdrawal_request' => $fin->requestWithdrawal($user['id'], $user['id'], $input),
        'exception_resolve' => $fin->resolveException($user['id'], $user['id'], $input),
        'reconciliation_run' => $fin->runReconciliation($user['id'], $user['id']),
        'doc_generate' => $fin->generateDocument($user['id'], $user['id'], $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown finance action.']),
    };
    events_v2_respond($requestId, 'finance_result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}