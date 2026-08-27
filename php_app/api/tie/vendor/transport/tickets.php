<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = bus_ops_context();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'search'));
        if ($action === 'overview') { bus_ops_respond($requestId, 'result', $service->ticketsOverview($user['id'])); exit; }
        $filters = ['code' => (string) ($_GET['code'] ?? ''), 'passenger' => (string) ($_GET['passenger'] ?? ''), 'route' => (string) ($_GET['route'] ?? ''), 'status' => (string) ($_GET['status'] ?? ''), 'date_from' => (string) ($_GET['date_from'] ?? ''), 'date_to' => (string) ($_GET['date_to'] ?? '')];
        bus_ops_respond($requestId, 'result', $service->listAllTickets($user['id'], $filters));
        exit;
    }

    $input = bus_ops_write('bus_ops_tickets', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'cancel' => $service->cancelTicket($user['id'], (string) ($input['ticket_id'] ?? ''), $input['reason'] ?? null),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown ticket action.']),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
