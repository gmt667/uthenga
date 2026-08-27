<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_coordination_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

$badLocationRejected = false;
try { UthengaTieCoordinationContracts::requestSession(['run_id' => '00000000-0000-4000-8000-000000000000', 'location' => ['permission' => 'DENIED']]); } catch (UthengaTieException $error) { $badLocationRejected = $error->type() === 'validation_error'; }
tie_coordination_assert($badLocationRejected, 'Quick Travel rejects a request without explicit foreground location consent.');
$coarseLocationAccepted = UthengaTieCoordinationContracts::discover(['destination' => 'Mzuzu', 'passenger_count' => 1, 'location' => ['permission' => 'GRANTED', 'source' => 'browser_geolocation', 'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 50000]]);
tie_coordination_assert((float) $coarseLocationAccepted['location']['accuracy_m'] === 50000.0, 'Coarse but valid consented browser locations remain usable for live departure discovery.');
$manualLocationAccepted = UthengaTieCoordinationContracts::discover(['destination' => 'Mzuzu', 'passenger_count' => 1, 'location' => ['permission' => 'GRANTED', 'source' => 'manual_location', 'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => null]]);
tie_coordination_assert($manualLocationAccepted['location']['source'] === 'manual_location' && $manualLocationAccepted['location']['accuracy_m'] === null, 'An explicit, session-only map pin is accepted when a browser cannot obtain precise device GPS.');
$workspaceDirective = UthengaTieCoordinationWorkspace::session(['status' => 'ACCEPTED', 'run' => ['status' => 'LOADING']]);
tie_coordination_assert($workspaceDirective['state'] === 'DRIVER_CONFIRMED' && in_array('EN_ROUTE', $workspaceDirective['allowed_actions'], true), 'The Agent Workspace receives state-derived UI directives, not inferred next actions.');

if (!$pdo instanceof PDO) { echo "TIE coordination contract tests passed (database integration skipped).\n"; exit(0); }
$service = new UthengaTieCoordinationService($pdo); $runId = null; $sessionId = null;
try {
    $seat = $pdo->query("SELECT id FROM seat_classes WHERE listing_id='trans-1' AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    if (!$seat) throw new RuntimeException('A transport seat class is required for the RTCP integration test.');
    $run = $service->createRun(['service_id' => 'trans-1', 'seat_class_id' => (int) $seat, 'remaining_seats' => 5, 'loading_location' => 'Integration test terminal', 'planned_departure_at' => gmdate('c', time() + 7200)], 'v-4'); $runId = $run['run']['id']; tie_coordination_assert($run['run']['status'] === 'LOADING', 'The guided driver session opens in the loading state.');
    $duplicateRejected = false; try { $service->createRun(['service_id' => 'trans-1', 'seat_class_id' => (int) $seat, 'remaining_seats' => 5, 'loading_location' => 'Integration test terminal', 'planned_departure_at' => gmdate('c', time() + 10800)], 'v-4'); } catch (UthengaTieException $error) { $duplicateRejected = $error->type() === 'validation_error'; } tie_coordination_assert($duplicateRejected, 'A driver cannot start two active transport sessions at once.');
    $created = $service->request(['run_id' => $runId, 'passenger_count' => 2, 'location' => ['permission' => 'GRANTED', 'source' => 'browser_geolocation', 'latitude' => -13.9626, 'longitude' => 33.7741, 'accuracy_m' => 15]], 'c-1'); $sessionId = $created['session']['id']; tie_coordination_assert($created['session']['status'] === 'PENDING_VENDOR' && ($created['workspace']['state'] ?? null) === 'AWAITING_DRIVER', 'Seat requests wait for vendor action and publish the correct workspace state.');
    $accepted = $service->vendorDecision(['session_id' => $sessionId, 'decision' => 'ACCEPT'], 'v-4'); tie_coordination_assert($accepted['session']['status'] === 'ACCEPTED' && $accepted['session']['run']['remaining_seats'] === 3, 'Vendor acceptance atomically holds only the requested seats.');
    $capacityProtected = false; try { $service->updateRun(['run_id' => $runId, 'remaining_seats' => $accepted['session']['run']['capacity'] + 1], 'v-4'); } catch (UthengaTieException $error) { $capacityProtected = $error->type() === 'validation_error'; } tie_coordination_assert($capacityProtected, 'Manual seat updates cannot exceed the configured vehicle capacity.');
    $service->customerAction(['session_id' => $sessionId, 'action' => 'EN_ROUTE'], 'c-1'); $arrived = $service->customerAction(['session_id' => $sessionId, 'action' => 'ARRIVED_AT_PICKUP'], 'c-1'); tie_coordination_assert($arrived['session']['status'] === 'ARRIVED_AT_PICKUP', 'Customer arrival follows the explicit session state machine.');
    $message = $service->sendMessage(['session_id' => $sessionId, 'body' => 'I am at the east entrance.'], 'c-1'); tie_coordination_assert($message['message_id'] !== '', 'Customer and vendor can exchange session-scoped messages.');
    $call = $service->requestCall(['session_id' => $sessionId], 'c-1'); $acceptedCall = $service->decideCall(['call_request_id' => $call['call_request_id'], 'decision' => 'ACCEPT'], 'v-4'); tie_coordination_assert($acceptedCall['status'] === 'ACCEPTED', 'Call contact requires recipient consent.');
    $boarded = $service->customerAction(['session_id' => $sessionId, 'action' => 'BOARDED'], 'c-1'); tie_coordination_assert($boarded['session']['status'] === 'BOARDED' && $boarded['session']['reservation_state'] === 'CONSUMED', 'Boarding consumes the accepted seat request without creating a booking.');
    $travelling = $service->updateRun(['run_id' => $runId, 'status' => 'TRAVELLING'], 'v-4'); tie_coordination_assert($travelling['run']['status'] === 'TRAVELLING', 'Only the driver can move a session from loading to travelling.');
    $completed = $service->updateRun(['run_id' => $runId, 'status' => 'COMPLETED'], 'v-4'); tie_coordination_assert($completed['run']['status'] === 'COMPLETED', 'A travelling driver session can be completed.');
    echo "TIE Phase 11 coordination tests passed.\n";
} finally {
    if ($sessionId) { foreach (['tie_transport_session_events', 'tie_transport_location_snapshots', 'tie_transport_messages', 'tie_transport_call_requests'] as $table) { $stmt = $pdo->prepare("DELETE FROM {$table} WHERE session_id=?"); $stmt->execute([$sessionId]); } $pdo->prepare('DELETE FROM tie_transport_sessions WHERE id=?')->execute([$sessionId]); }
    if ($runId) $pdo->prepare('DELETE FROM tie_transport_runs WHERE id=?')->execute([$runId]);
}
