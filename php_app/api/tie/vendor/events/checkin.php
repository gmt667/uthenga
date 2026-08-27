<?php
/**
 * Uthenga — Check-In LIVE: Operational Command Center API (Events V2).
 *
 * GET  ?listing_id=...&action=workspace|lookup|audit
 * POST {action: scan|manual|override|exit}
 *
 * Scan responses use the decision contract: {decision: ALLOW|DENY|REVIEW,
 * reason_code, message, attendee, ticket, event, access, attendance, gate,
 * request_id}. Admission is always decided server-side.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/CheckIn.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $eventsService, $requestId] = events_v2_context();
    global $pdo;
    $checkin = new UthengaCheckInService($pdo);

    $listingId = trim((string) ($_GET['listing_id'] ?? ($_POST['listing_id'] ?? '')));

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'workspace'));
        $result = match ($action) {
            'workspace', 'summary' => $checkin->workspace($listingId, $user['id']),
            'lookup' => ['listing_id' => $listingId, 'results' => $checkin->lookup($listingId, $user['id'], (string) ($_GET['q'] ?? ''), (int) ($_GET['limit'] ?? 12))],
            'audit' => ['listing_id' => $listingId, 'scans' => $checkin->auditLog($listingId, $user['id'], (int) ($_GET['limit'] ?? 50))],
            default => throw UthengaTieErrors::validation(['action' => 'Unknown check-in action.']),
        };
        events_v2_respond($requestId, 'result', $result);
    }

    $input = events_v2_write('checkin_scan', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $listingId = trim((string) ($input['listing_id'] ?? $listingId));
    if ($listingId === '') throw UthengaTieErrors::validation(['listing_id' => 'An event must be selected.']);

    $result = match ($action) {
        'scan' => ['decision' => $checkin->scan($listingId, $user['id'], $user, $input, $requestId)],
        'manual' => ['decision' => $checkin->manual($listingId, $user['id'], $user, $input, $requestId)],
        'override' => ['decision' => $checkin->override($listingId, $user['id'], $user, $input, $requestId)],
        'exit' => ['result' => $checkin->exit($listingId, $user['id'], $user, $input, $requestId)],
        default => throw UthengaTieErrors::validation(['action' => 'Unknown check-in action.']),
    };
    events_v2_respond($requestId, 'result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}