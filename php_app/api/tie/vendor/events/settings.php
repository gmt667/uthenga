<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Settings.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    $stgService = new UthengaSettings($service->db());
    $vid = $user['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $settings = $stgService->getSettings($vid);
        events_v2_respond($requestId, 'settings', $settings);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = events_v2_write('events_settings', $requestId);
        $updated = $stgService->saveSettings($vid, $input, $user['id']);
        events_v2_respond($requestId, 'settings', $updated);
        exit;
    }

    throw UthengaTieErrors::validation(['method' => 'GET or POST required.']);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
