<?php
require_once __DIR__ . '/../../../config.php'; require_once __DIR__ . '/../../../db.php'; require_once __DIR__ . '/../../../includes/tie/bootstrap.php'; require_once __DIR__ . '/../../../includes/tie/Api.php';
$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    foreach (['payments', 'plans', 'availability'] as $feature) UthengaTieApi::requireFeature($feature);
    $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf(); UthengaTieApi::requireRateLimit('payments', UthengaTieConfig::integer('TIE_PAYMENT_RATE_LIMIT', 5), 60, $requestId);
    $started = microtime(true); $intent = (new UthengaTieKernel())->payments->start(UthengaTiePaymentContracts::start(UthengaTieApi::input()), $user['id'])->toArray(); $duration = round((microtime(true) - $started) * 1000, 2);
    UthengaTieMetrics::record('payment_intent_start_latency_ms', $duration, $requestId, ['module' => 'payments', 'provider' => $intent['provider'], 'status' => strtolower($intent['status'])]); UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'payment_intent' => $intent]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
