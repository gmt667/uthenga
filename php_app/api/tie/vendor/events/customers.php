<?php
/**
 * Uthenga — Customer Relationship Management (CRM) API (Events V2).
 *
 * GET  ?action=overview|directory|profile|segments|at_risk
 * POST {action: add_note|create_segment|add_tag|remove_tag}
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Customers.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $eventsService, $requestId] = events_v2_context();
    global $pdo;
    $customersService = new UthengaCustomersService($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'overview'));
        $result = match ($action) {
            'overview' => $customersService->overview($user['id']),
            'directory' => $customersService->directory($user['id'], $_GET),
            'profile' => $customersService->profile($user['id'], (string) ($_GET['customer_id'] ?? '')),
            'segments' => ['segments' => $customersService->segmentsList($user['id'])],
            'at_risk' => ['at_risk' => $customersService->getAtRiskCustomers($user['id'])],
            default => throw UthengaTieErrors::validation(['action' => 'Unknown customers action.']),
        };
        events_v2_respond($requestId, 'result', $result);
    }

    $input = events_v2_write('customers_ops', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));

    $result = match ($action) {
        'add_note' => $customersService->addNote($user['id'], (string) ($input['customer_id'] ?? ''), (string) ($input['note'] ?? ''), (string) ($user['name'] ?? 'Organizer')),
        'create_segment' => ['segments' => $customersService->createSegment($user['id'], $input)],
        default => throw UthengaTieErrors::validation(['action' => 'Unknown customers action.']),
    };
    events_v2_respond($requestId, 'result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
