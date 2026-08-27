<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('plans'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('customer_document_upload', UthengaTieConfig::integer('TIE_DOCUMENT_UPLOAD_RATE_LIMIT', 20), 60, $requestId);
    $input = UthengaTieApi::input();
    if (empty($_FILES['file'])) throw UthengaTieErrors::validation(['file' => 'Choose a file to upload.']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => (new UthengaTieKernel())->customerDocuments->upload($user, $_FILES['file'], $input)]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
