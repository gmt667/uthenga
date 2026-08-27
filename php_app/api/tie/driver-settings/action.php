<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('quick_travel'); $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('driver_settings_action', UthengaTieConfig::integer('TIE_DRIVER_SETTINGS_ACTION_RATE_LIMIT', 30), 60, $requestId);
    $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? '')));
    $service = (new UthengaTieKernel())->driverSettings;
    $result = match ($action) {
        'save_preferences' => $service->savePreferences($user['id'], $input),
        'request_deactivation' => $service->requestDeactivation($user['id'], $input),
        'cancel_deactivation' => $service->cancelDeactivation($user['id']),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported settings action.']),
    };
    UthengaTieObservability::log('driver_settings.action', $requestId, ['module' => 'driver_settings', 'action' => $action, 'status' => 'ok']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
