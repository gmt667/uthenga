<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('events_media', 30, 60, $requestId);

    $eventId = events_v2_event($_POST);
    $input = UthengaTieApi::input();
    if (!is_array($input)) $input = $_POST;
    $action = strtolower((string) ($input['action'] ?? ($_POST['action'] ?? '')));

    if ($action === 'upload_cover') {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) throw UthengaTieErrors::validation(['file' => 'Choose an image to upload.']);
        events_v2_respond($requestId, 'event', $service->attachCoverImage($eventId, $user['id'], $user['id'], $_FILES['file']));
    }
    if ($action === 'upload_gallery') {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) throw UthengaTieErrors::validation(['file' => 'Choose an image to upload.']);
        events_v2_respond($requestId, 'event', $service->addGalleryImage($eventId, $user['id'], $user['id'], $_FILES['file']));
    }
    if ($action === 'remove_gallery') {
        $version = UthengaEventsContracts::integer($input['version'] ?? 0, 0, PHP_INT_MAX, 'version');
        events_v2_respond($requestId, 'event', $service->removeGalleryImage($eventId, $user['id'], $user['id'], (string) ($input['image_id'] ?? ''), $version));
    }
    if ($action === 'reorder_gallery') {
        $version = UthengaEventsContracts::integer($input['version'] ?? 0, 0, PHP_INT_MAX, 'version');
        $order = $input['order'] ?? [];
        if (is_string($order)) $order = json_decode($order, true);
        if (!is_array($order)) throw UthengaTieErrors::validation(['order' => 'Provide an ordered list of image ids.']);
        events_v2_respond($requestId, 'event', $service->reorderGallery($eventId, $user['id'], $user['id'], $order, $version));
    }
    throw UthengaTieErrors::validation(['action' => 'Use upload_cover, upload_gallery, remove_gallery or reorder_gallery.']);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
