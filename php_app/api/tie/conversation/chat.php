<?php
/** Phase 8 conversational planning endpoint. It never books, reserves, or writes marketplace data. */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('ai');
    UthengaTieApi::requireFeature('llm');
    UthengaTieApi::requireFeature('recommendations');
    UthengaTieApi::requireFeature('context');
    UthengaTieApi::requireFeature('query');
    UthengaTieApi::requireFeature('availability');
    $user = UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit('ai_conversation', UthengaTieConfig::integer('TIE_AI_CHAT_RATE_LIMIT', 10), 60, $requestId);
    $request = UthengaTieAiConversationContracts::request(UthengaTieApi::input(), $user['id']);
    if ($request->recommendationRequest->contextRequest->location !== null) UthengaTieApi::requireFeature('location');
    $result = (new UthengaTieKernel())->conversation->chat($request, $user['id']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'conversation' => $result->toArray()]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
