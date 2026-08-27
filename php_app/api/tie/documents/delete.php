<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('plans'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    $input = UthengaTieApi::input();
    $documentId = trim((string) ($input['document_id'] ?? ''));
    if ($documentId === '') throw UthengaTieErrors::validation(['document_id' => 'A document is required.']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => (new UthengaTieKernel())->customerDocuments->delete($user['id'], $documentId)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
