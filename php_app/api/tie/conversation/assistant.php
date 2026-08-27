<?php
/** FastAPI-first conversation endpoint with the existing PHP engine as fallback. */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';
require_once __DIR__ . '/../../../includes/tie/AiServiceBridge.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    foreach (['ai', 'conversation', 'recommendations', 'context', 'query', 'availability'] as $feature) UthengaTieApi::requireFeature($feature);
    $user = UthengaTieApi::requireAuthenticatedUser(); UthengaTieApi::requireCsrf(); UthengaTieApi::requireRateLimit('ai_conversation', UthengaTieConfig::integer('TIE_AI_CHAT_RATE_LIMIT', 10), 60, $requestId);
    $request = UthengaTieAiConversationContracts::request(UthengaTieApi::input(), $user['id']); if ($request->recommendationRequest->contextRequest->location !== null) UthengaTieApi::requireFeature('location');
    $kernel = new UthengaTieKernel(); $usedFallback = false;
    if (UthengaTieConfig::boolean('TIE_FASTAPI_AI_ENABLED')) {
        try { $result = (new UthengaTieFastApiConversationBridge())->chat($request, $user['id'], $kernel); }
        catch (Throwable $error) { $result = $kernel->conversation->chat($request, $user['id']); $usedFallback = true; }
    } else { $result = $kernel->conversation->chat($request, $user['id']); $usedFallback = true; }
    $data = $result->toArray(); $data['diagnostics']['php_fallback_used'] = $usedFallback;
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'conversation' => $data]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
