<?php
/** PHP issues a short-lived, user-scoped tool capability; it never exposes an AI service token. */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';
require_once __DIR__ . '/../../../includes/tie/AiServiceGateway.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('ai');
    $user = UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    $input = UthengaTieApi::input();
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'capability' => UthengaTieAiServiceCapability::issue($user['id'], (array) ($input['tools'] ?? [])), 'expires_in_seconds' => 60]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
