<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); $sessionId = strtolower(trim((string) ($_GET['session_id'] ?? '')));
    if (!preg_match('/^[a-f0-9-]{36}$/', $sessionId)) throw UthengaTieErrors::validation(['session_id' => 'A valid coordination session is required.']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'coordination' => (new UthengaTieKernel())->coordination->session($sessionId, $user['id'])]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
