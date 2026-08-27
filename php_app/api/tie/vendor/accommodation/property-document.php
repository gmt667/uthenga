<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user,, $requestId] = accommodation_v2_context();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('accommodation_property_document', 12, 60, $requestId);
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) throw UthengaTieErrors::validation(['file' => 'Choose a document to upload.']);
    $workspace = new UthengaAccommodationPropertyWorkspace($GLOBALS['pdo']);
    $document = $workspace->uploadDocument(accommodation_v2_property($_POST), $user['id'], $_POST, $_FILES['file'], $requestId);
    accommodation_v2_respond($requestId, 'document', $document);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
