<?php
/**
 * Uthenga — Attendee Intelligence & Participant Management API (Events V2).
 *
 * GET  ?listing_id=...&action=workspace|list|detail|export
 * POST {action: checkin|add_attendee|import|message}
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Attendees.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $eventsService, $requestId] = events_v2_context();
    global $pdo;
    $attendees = new UthengaAttendeesService($pdo);

    $listingId = trim((string) ($_GET['listing_id'] ?? ($_POST['listing_id'] ?? '')));

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'workspace'));
        $result = match ($action) {
            'workspace', 'summary' => $attendees->workspace($listingId, $user['id']),
            'list' => ['listing_id' => $listingId, 'attendees' => $attendees->list($listingId, $user['id'], [
                'q' => (string) ($_GET['q'] ?? ''), 'type_id' => (int) ($_GET['type_id'] ?? 0),
                'attendance' => (string) ($_GET['attendance'] ?? ''), 'payment' => (string) ($_GET['payment'] ?? ''),
                'since' => (string) ($_GET['since'] ?? ''), 'zone' => (string) ($_GET['zone'] ?? ''),
                'organization' => (string) ($_GET['organization'] ?? ''), 'limit' => (int) ($_GET['limit'] ?? 0),
            ])],
            'detail' => ['detail' => $attendees->detail($listingId, $user['id'], (string) ($_GET['ticket_id'] ?? ''))],
            'export' => ['export' => $attendees->export($listingId, $user['id'], (string) ($_GET['type'] ?? 'all'))],
            default => throw UthengaTieErrors::validation(['action' => 'Unknown attendees action.']),
        };
        events_v2_respond($requestId, 'result', $result);
    }

    $input = events_v2_write('attendees_ops', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $listingId = trim((string) ($input['listing_id'] ?? $listingId));
    if ($listingId === '') throw UthengaTieErrors::validation(['listing_id' => 'An event must be selected.']);

    $result = match ($action) {
        'checkin' => ['result' => $attendees->checkIn($listingId, $user['id'], $user, $input)],
        'add_attendee' => ['result' => $attendees->addAttendee($listingId, $user['id'], $user, $input)],
        'import' => ['result' => $attendees->import($listingId, $user['id'], $user, $input)],
        'message' => ['result' => $attendees->message($listingId, $user['id'], $user, $input)],
        default => throw UthengaTieErrors::validation(['action' => 'Unknown attendees action.']),
    };
    events_v2_respond($requestId, 'result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}