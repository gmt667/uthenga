<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        events_v2_respond($requestId, 'portfolio', $service->workspace($user['id']));
    }
    $input = events_v2_write('events_portfolio', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'create_draft' => $service->createDraft($user['id'], $user, $input),
        default => throw UthengaTieErrors::validation(['action' => 'Use create_draft.']),
    };
    events_v2_respond($requestId, 'event', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
