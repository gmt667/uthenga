<?php
/**
 * Uthenga — Ticket Commerce & Ticket Lifecycle API (Events V2).
 *
 * GET  ?listing_id=...&action=workspace|types|velocity|orders|issued|transfers|refunds|order_detail|ticket_detail|export|events
 * POST {action: create_type|update_type|set_status|adjust_inventory|duplicate_type|
 *            issue_booking|resend_ticket|cancel_ticket|create_transfer|create_refund|decide_refund|reconcile}
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Tickets.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $eventsService, $requestId] = events_v2_context();
    global $pdo;
    $tickets = new UthengaTicketsService($pdo);

    $listingId = trim((string) ($_GET['listing_id'] ?? ($_POST['listing_id'] ?? '')));

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'workspace'));
        $result = match ($action) {
            'events' => $eventsService->workspace($user['id']),
            'workspace', 'summary' => $tickets->workspace($listingId, $user['id']),
            'types' => ['listing_id' => $listingId, 'types' => $tickets->typesList($listingId)],
            'velocity' => ['listing_id' => $listingId, 'velocity' => $tickets->velocity($listingId, strtoupper((string) ($_GET['range'] ?? '7D')))],
            'orders' => ['listing_id' => $listingId, 'orders' => $tickets->ordersList($listingId, (string) ($_GET['filter'] ?? 'all'), (string) ($_GET['q'] ?? ''))],
            'issued' => ['listing_id' => $listingId, 'tickets' => $tickets->issuedList($listingId, (string) ($_GET['status'] ?? 'all'), (string) ($_GET['q'] ?? ''))],
            'transfers' => ['listing_id' => $listingId, 'transfers' => $tickets->transfersList($listingId)],
            'refunds' => ['listing_id' => $listingId, 'refunds' => $tickets->refundsList($listingId)],
            'order_detail' => ['detail' => $tickets->orderDetail($listingId, $user['id'], (string) ($_GET['booking_id'] ?? ''))],
            'ticket_detail' => ['detail' => $tickets->ticketDetail($listingId, $user['id'], (string) ($_GET['ticket_id'] ?? ''))],
            'export' => ['export' => $tickets->export($listingId, $user['id'], (string) ($_GET['type'] ?? 'inventory'))],
            default => throw UthengaTieErrors::validation(['action' => 'Unknown tickets action.']),
        };
        events_v2_respond($requestId, 'result', $result);
    }

    $input = events_v2_write('tickets_ops', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $listingId = trim((string) ($input['listing_id'] ?? $listingId));
    if ($listingId === '') throw UthengaTieErrors::validation(['listing_id' => 'An event must be selected.']);

    $result = match ($action) {
        'create_type' => ['listing_id' => $listingId, 'types' => $tickets->createType($listingId, $user['id'], $user, $input)],
        'update_type' => ['listing_id' => $listingId, 'types' => $tickets->updateType($listingId, $user['id'], $user, $input)],
        'set_status' => ['listing_id' => $listingId, 'types' => $tickets->setStatus($listingId, $user['id'], $user, $input)],
        'adjust_inventory' => ['listing_id' => $listingId, 'types' => $tickets->adjustInventory($listingId, $user['id'], $user, $input)],
        'duplicate_type' => ['listing_id' => $listingId, 'types' => $tickets->duplicateType($listingId, $user['id'], $user, $input)],
        'issue_booking' => ['result' => $tickets->issueForBooking($listingId, $user['id'], $user, (string) ($input['booking_id'] ?? ''))],
        'resend_ticket' => ['ticket' => $tickets->resendTicket($listingId, $user['id'], $user, (string) ($input['ticket_id'] ?? ''))],
        'cancel_ticket' => ['ticket' => $tickets->cancelTicket($listingId, $user['id'], $user, $input)],
        'create_transfer' => ['listing_id' => $listingId, 'transfers' => $tickets->createTransfer($listingId, $user['id'], $user, $input)],
        'create_refund' => ['listing_id' => $listingId, 'refunds' => $tickets->createRefund($listingId, $user['id'], $user, $input)],
        'decide_refund' => ['listing_id' => $listingId, 'refunds' => $tickets->decideRefund($listingId, $user['id'], $user, $input)],
        'reconcile' => ['listing_id' => $listingId, 'types' => $tickets->reconcileInventory($listingId, $user['id'], $user)],
        default => throw UthengaTieErrors::validation(['action' => 'Unknown tickets action.']),
    };
    events_v2_respond($requestId, 'result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}