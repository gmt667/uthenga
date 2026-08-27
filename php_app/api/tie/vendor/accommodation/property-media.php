<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user,, $requestId] = accommodation_v2_context();
    $workspace = new UthengaAccommodationPropertyWorkspace($GLOBALS['pdo']);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        accommodation_v2_respond($requestId, 'assets', $workspace->listMedia(accommodation_v2_property(), $user['id']));
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'GET or POST is required.']);
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('accommodation_property_media', 20, 60, $requestId);
    $propertyId = accommodation_v2_property($_POST);
    $action = strtolower((string) ($_POST['action'] ?? 'upload'));
    if ($action === 'upload') {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) throw UthengaTieErrors::validation(['file' => 'Choose an image to upload.']);
        accommodation_v2_respond($requestId, 'media', $workspace->uploadMedia($propertyId, $user['id'], $_POST, $_FILES['file'], $requestId));
    }
    if ($action === 'update') {
        accommodation_v2_respond($requestId, 'media', $workspace->updateMedia($propertyId, $user['id'], $_POST, $requestId));
    }
    if ($action === 'remove') {
        $workspace->removeMedia($propertyId, $user['id'], $_POST, $requestId);
        accommodation_v2_respond($requestId, 'removed', ['media_id' => (string) ($_POST['media_id'] ?? '')]);
    }
    throw UthengaTieErrors::validation(['action' => 'Use upload, update or remove.']);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
