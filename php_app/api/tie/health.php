<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    $kernel = new UthengaTieKernel();
    UthengaTieObservability::log('health.checked', $requestId, ['module' => 'health', 'status' => 'ok']);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'health', 'status' => 'ok']);
    UthengaTieApi::respond([
        'success' => true,
        'status' => 'ok',
        'service' => UthengaTieConfig::string('TIE_SERVICE_NAME', 'travel-intelligence-engine'),
        'version' => UthengaTieConfig::string('TIE_API_VERSION', 'v1'),
        'enabled' => UthengaTieConfig::boolean('TIE_ENABLED'),
        'request_id' => $requestId,
        'dependencies' => ['database_available' => uthenga_db_is_available()],
        'features' => UthengaTieFeatureFlags::all(),
        'providers' => ['llm' => $kernel->llm->health(), 'routing' => UthengaTieConfig::string('TIE_ROUTING_PROVIDER', 'unconfigured'), 'notifications' => UthengaTieFeatureFlags::enabled('notifications') ? 'outbox_enabled' : 'disabled', 'payments' => UthengaTieFeatureFlags::enabled('payments') ? 'intent_enabled' : 'disabled'],
    ]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
