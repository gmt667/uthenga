<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('plans'); $user = UthengaTieApi::requireAuthenticatedUser();
    $bookingId = trim((string) (UthengaTieApi::query()['booking_id'] ?? ''));
    if ($bookingId === '' || strlen($bookingId) > 20) throw UthengaTieErrors::validation(['booking_id' => 'A valid booking is required.']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => (new UthengaTieKernel())->customerBookings->detail($user['id'], $bookingId)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
