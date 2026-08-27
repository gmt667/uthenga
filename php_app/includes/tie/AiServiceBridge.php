<?php
/** Optional FastAPI explanation bridge. PHP still builds all marketplace evidence. */
final class UthengaTieFastApiConversationBridge
{
    public function chat(UthengaTieAiConversationRequest $request, string $userId, UthengaTieKernel $kernel): UthengaTieAiConversationResponse
    {
        $url = UthengaTieConfig::string('TIE_FASTAPI_URL', '');
        $token = UthengaTieConfig::string('TIE_FASTAPI_SERVICE_TOKEN', '');
        if ($url === '' || strlen($token) < 32 || !function_exists('curl_init')) throw UthengaTieErrors::providerUnavailable('fastapi_ai');
        $evidence = (new UthengaTieAiToolOrchestrator($kernel->travelContext, $kernel->recommendation, $kernel->budget))->execute($userId, $request);
        $memory = new UthengaTieConversationMemory();
        $payload = ['conversation_id' => $request->conversationId, 'message' => $request->message, 'approved_context' => ['trip' => $evidence['travel_context']['trip'] ?? [], 'destination' => $evidence['travel_context']['trip']['destination'] ?? null, 'recommendations' => $evidence['recommendations'] ?? [], 'budget' => $evidence['budget'] ?? null, 'conversation_history' => $memory->history($userId, $request->conversationId)], 'requested_tools' => []];
        $handle = curl_init($url); curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(1, UthengaTieConfig::integer('TIE_FASTAPI_TIMEOUT', 15)), CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES)]);
        $raw = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); curl_close($handle); $response = is_string($raw) ? json_decode($raw, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($response) || !is_string($response['message'] ?? null)) throw UthengaTieErrors::providerUnavailable('fastapi_ai');
        $message = $this->safeMessage((string) $response['message'], $evidence);
        $fallback = (bool) ($response['fallback'] ?? false) || $message === null;
        if ($fallback) $message = $this->fallbackMessage($evidence);
        $result = new UthengaTieAiConversationResponse(['schema_version' => UthengaTieAiConversationResponse::SCHEMA_VERSION, 'conversation_id' => $request->conversationId, 'message' => $message, 'recommendations' => $evidence['recommendations'] ?? [], 'suggested_actions' => $evidence['recommendations'] ? ['view_recommendation', 'refine_preferences'] : ['refine_preferences'], 'follow_up_questions' => $evidence['recommendations'] ? ['Would you like to compare the verified options or refine your trip?'] : ['Would you like to adjust your dates, budget, or destination?'], 'confidence' => $evidence['recommendations'] ? 'MEDIUM' : 'LOW', 'budget' => $evidence['budget'] ?? null, 'diagnostics' => ['provider' => 'fastapi', 'model' => UthengaTieConfig::string('TIE_FASTAPI_MODEL', 'provider-managed'), 'tool_calls' => $evidence['tool_calls'], 'usage' => [], 'validation_status' => $fallback ? 'fallback' : 'valid', 'fallback_used' => $fallback, 'duration_ms' => null], 'provenance' => $evidence['provenance'] + ['provider_output' => 'fastapi_validated_explanation', 'conversation_memory' => 'session_only_bounded']]);
        $memory->append($userId, $request->conversationId, $request->message, $result->toArray());
        return $result;
    }

    private function safeMessage(string $message, array $evidence): ?string
    {
        $message = UthengaTieAiSanitizer::text($message, 1200);
        if ($message === '' || preg_match('/\b(booked|booking confirmed|payment confirmed|payment captured|reservation confirmed|guaranteed)\b/i', $message)) return null;
        return $message;
    }
    private function fallbackMessage(array $evidence): string { return empty($evidence['recommendations']) ? 'I could not find a validated Uthenga option for this travel context. You can adjust your dates, budget, or destination.' : 'I found validated Uthenga options below. Review them before making any booking decision.'; }
}
