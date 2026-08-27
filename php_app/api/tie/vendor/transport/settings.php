<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    UthengaTieApi::requireFeature('bus_operations');
    $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();
    $settings = (new UthengaTieKernel())->busSettings;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'get'));
        $result = match ($action) {
            'ticket_template' => $settings->getTicketTemplate($user['id']),
            default => $settings->get($user['id']),
        };
        bus_ops_respond($requestId, 'result', $result);
        exit;
    }

    $input = bus_ops_write('bus_ops_settings', $requestId);
    $action = strtolower((string) ($input['action'] ?? 'save'));
    $result = match ($action) {
        'save_ticket_template' => $settings->saveTicketTemplate($user['id'], $input),
        default => $settings->save($user['id'], $input),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
