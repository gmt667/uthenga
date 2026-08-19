<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    $venues = new UthengaVenuesService($service->db());

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'workspace'));
        $result = match ($action) {
            'workspace', 'list' => $venues->workspace($user['id'], (string) ($_GET['search'] ?? '')),
            'detail' => $venues->venueDetail((string) ($_GET['venue_id'] ?? ''), $user['id']),
            'calendar' => $venues->calendar($user['id'], ['venue_id' => (string) ($_GET['venue_id'] ?? ''), 'month' => (string) ($_GET['month'] ?? '')]),
            'check_availability' => $venues->checkAvailability($user['id'], ['venue_id' => (string) ($_GET['venue_id'] ?? ''), 'space_id' => (string) ($_GET['space_id'] ?? ''), 'event_start' => (string) ($_GET['event_start'] ?? ''), 'teardown_end' => (string) ($_GET['teardown_end'] ?? '')]),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown venues action.']),
        };
        events_v2_respond($requestId, 'venue_result', $result);
    }

    $input = events_v2_write('venues_ops', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'create_venue' => $venues->createVenue($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'update_venue' => $venues->updateVenue($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'update_status' => $venues->updateStatus($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'delete_venue' => $venues->deleteVenue($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'add_space' => $venues->addSpace($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'update_space' => $venues->updateSpace($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'delete_space' => $venues->deleteSpace($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'save_facilities' => $venues->saveFacilities($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'save_media' => $venues->saveMedia($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'save_pricing' => $venues->savePricing($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'save_policies' => $venues->savePolicies($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'set_availability' => $venues->setAvailability($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'remove_availability' => $venues->removeAvailability($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'assign_event' => $venues->assignEvent($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        'delete_assignment' => $venues->deleteAssignment($user['id'], $user['id'], (string) ($user['name'] ?? ''), $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown venues action.']),
    };
    events_v2_respond($requestId, 'venue_result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}