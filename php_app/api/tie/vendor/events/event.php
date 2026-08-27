<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        events_v2_respond($requestId, 'event', $service->get(events_v2_event(), $user['id']));
    }

    $input = events_v2_write('events_event_write', $requestId);
    $eventId = events_v2_event($input);
    $action = strtolower((string) ($input['action'] ?? ''));
    $version = isset($input['version']) ? UthengaEventsContracts::integer($input['version'], 0, PHP_INT_MAX, 'version') : 0;

    $result = match ($action) {
        'save_identity' => $service->saveIdentity($eventId, $user['id'], $user['id'], $input, $version),
        'save_schedule' => $service->saveSchedule($eventId, $user['id'], $user['id'], $input, $version),
        'save_venue' => $service->saveVenue($eventId, $user['id'], $user['id'], $input, $version),
        'save_description' => $service->saveDescription($eventId, $user['id'], $user['id'], $input, $version),
        'save_policies' => $service->savePolicies($eventId, $user['id'], $user['id'], $input, $version),
        'save_ticket_type' => $service->saveTicketType($eventId, $user['id'], $user['id'], $input),
        'delete_ticket_type' => $service->deleteTicketType($eventId, $user['id'], $user['id'], (int) ($input['ticket_id'] ?? 0)),
        'publish' => $service->publish($eventId, $user['id'], $user['id'], $version),
        'unpublish' => $service->unpublish($eventId, $user['id'], $user['id'], $version),
        'cancel' => $service->cancel($eventId, $user['id'], $user['id'], $version, UthengaEventsContracts::nullableText($input['reason'] ?? null, 500)),
        'archive' => $service->archive($eventId, $user['id'], $user['id'], $version),
        'duplicate' => $service->duplicate($eventId, $user['id'], $user, $input),
        'delete_draft' => (function () use ($service, $eventId, $user, $requestId) {
            $service->deleteDraft($eventId, $user['id'], $user['id']);
            events_v2_respond($requestId, 'deleted', ['event_id' => $eventId]);
        })(),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported event action.']),
    };
    events_v2_respond($requestId, 'event', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
