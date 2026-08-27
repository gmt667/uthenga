<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = bus_ops_context();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'session_stats'));
        $sessionId = (string) ($_GET['session_id'] ?? '');
        if ($sessionId === '') throw UthengaTieErrors::validation(['session_id' => 'A session id is required.']);
        $result = match ($action) {
            'session_stats' => $service->sessionStats($user['id'], $sessionId),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown boarding action.']),
        };
        bus_ops_respond($requestId, 'result', $result);
        exit;
    }

    $input = bus_ops_write('bus_ops_boarding', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'start_session' => $service->startBoardingSession($user['id'], (string) ($input['departure_id'] ?? '')),
        'stop_session' => $service->stopBoardingSession($user['id'], (string) ($input['session_id'] ?? '')),
        'verify' => $service->verifyTicket($user['id'], $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown boarding action.']),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
