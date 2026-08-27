<?php
/** Polled by the return page after a PayChangu checkout redirect, in case the webhook hasn't landed yet. */
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php'; require_once __DIR__ . '/../../../includes/payment_engine.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('bus_operations'); $user = UthengaTieApi::requireAuthenticatedUser();
    $bookingId = (string) (UthengaTieApi::query()['booking_id'] ?? '');
    if ($bookingId === '') throw UthengaTieErrors::validation(['booking_id' => 'A booking id is required.']);
    global $pdo;
    $owner = $pdo->prepare('SELECT customer_id FROM bookings WHERE id=? LIMIT 1'); $owner->execute([$bookingId]);
    if ((string) $owner->fetchColumn() !== $user['id']) throw UthengaTieErrors::authorization();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => (new UthengaTieKernel())->busOperations->reconcilePayment($bookingId)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
