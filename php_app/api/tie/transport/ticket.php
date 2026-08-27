<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('bus_operations'); $user = UthengaTieApi::requireAuthenticatedUser();
    $ticketId = (string) (UthengaTieApi::query()['ticket_id'] ?? '');
    if ($ticketId === '') throw UthengaTieErrors::validation(['ticket_id' => 'A ticket id is required.']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => (new UthengaTieKernel())->busOperations->ticketDetail($user['id'], $ticketId)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
