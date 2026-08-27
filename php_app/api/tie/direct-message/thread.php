<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser();
    $threadId = strtolower(trim((string) ($_GET['thread_id'] ?? '')));
    if (!preg_match('/^[a-f0-9-]{36}$/', $threadId)) throw UthengaTieErrors::validation(['thread_id' => 'A valid conversation is required.']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => (new UthengaTieKernel())->messaging->directThread($threadId, $user['id'])]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
