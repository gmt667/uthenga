<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('bus_operations'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('bus_ticket_purchase', 10, 60, $requestId);
    $input = UthengaTieApi::input();
    global $pdo;
    $userStmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id=? LIMIT 1'); $userStmt->execute([$user['id']]);
    $fullUser = $userStmt->fetch();
    if (!is_array($fullUser)) throw UthengaTieErrors::authentication();
    // purchaseTicket() makes a real, sometimes-slow PayChangu charge call —
    // release the session lock first so the status-poll request that follows
    // immediately after doesn't queue behind it.
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => (new UthengaTieKernel())->busOperations->purchaseTicket($input, $fullUser)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
