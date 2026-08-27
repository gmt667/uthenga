<?php
/**
 * Phase 8: bounded, provider-neutral conversational planning.
 *
 * This module deliberately receives marketplace facts only through the
 * TravelContext and Recommendation modules. Provider output is untrusted: it
 * can select canonical recommendation identifiers, but it cannot create or
 * alter marketplace facts, bookings, prices, or availability.
 */

final class UthengaTieAiConversationRequest
{
    public const SCHEMA_VERSION = 'ai-conversation-request/v1';
    public UthengaTieRecommendationRequest $recommendationRequest;
    public string $message;
    public string $conversationId;

    public function __construct(UthengaTieRecommendationRequest $recommendationRequest, string $message, string $conversationId)
    {
        $this->recommendationRequest = $recommendationRequest;
        $this->message = $message;
        $this->conversationId = $conversationId;
    }
}

final class UthengaTieAiConversationResponse
{
    public const SCHEMA_VERSION = 'ai-conversation-response/v1';
    public array $data;
    public function __construct(array $data) { $this->data = $data; }
    public function toArray(): array { return $this->data; }
}

final class UthengaTieAiConversationContracts
{
    private const FIELDS = ['message', 'conversation_id', 'destination', 'origin', 'start_date', 'end_date', 'travellers', 'budget', 'currency', 'preferences', 'travel_mode', 'location', 'nearby_radius_km', 'category', 'limit', 'csrf_token'];

    public static function request(array $input, string $userId): UthengaTieAiConversationRequest
    {
        $unknown = array_values(array_diff(array_keys($input), self::FIELDS));
        if ($unknown) throw UthengaTieErrors::validation(['request' => 'Unsupported conversation field(s): ' . implode(', ', $unknown) . '. Marketplace facts and conversation history are server-derived.']);
        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '' || strlen($message) > 1200) throw UthengaTieErrors::validation(['message' => 'Message is required and must be at most 1200 characters.']);
        $conversationId = trim((string) ($input['conversation_id'] ?? ''));
        if ($conversationId === '') $conversationId = 'tie-conversation-' . bin2hex(random_bytes(10));
        if (!preg_match('/^[A-Za-z0-9._-]{8,100}$/', $conversationId)) throw UthengaTieErrors::validation(['conversation_id' => 'Conversation ID must use 8-100 letters, numbers, dots, underscores, or hyphens.']);
        $recommendationInput = array_intersect_key($input, array_flip(['destination', 'origin', 'start_date', 'end_date', 'travellers', 'budget', 'currency', 'preferences', 'travel_mode', 'location', 'nearby_radius_km', 'category', 'limit', 'csrf_token']));
        return new UthengaTieAiConversationRequest(UthengaTieRecommendationContracts::request($recommendationInput, $userId), $message, $conversationId);
    }
}

/** A bounded, session-only store. It is not a customer profile or database. */
final class UthengaTieConversationMemory
{
    public function history(string $userId, string $conversationId): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return [];
        $this->prune();
        $entry = $_SESSION['tie_ai_conversations'][$this->userKey($userId)][$conversationId] ?? null;
        return is_array($entry['turns'] ?? null) ? $entry['turns'] : [];
    }

    public function append(string $userId, string $conversationId, string $message, array $response): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        $this->prune(); $key = $this->userKey($userId);
        $turns = $this->history($userId, $conversationId);
        $turns[] = ['role' => 'user', 'content' => UthengaTieAiSanitizer::text($message, 600)];
        $turns[] = ['role' => 'assistant', 'content' => UthengaTieAiSanitizer::text((string) ($response['message'] ?? ''), 600), 'recommendation_ids' => array_values(array_filter(array_map(static fn(array $item): string => (string) ($item['id'] ?? ''), $response['recommendations'] ?? [])))];
        $maxTurns = max(2, UthengaTieConfig::integer('TIE_AI_MAX_HISTORY', 8) * 2);
        $_SESSION['tie_ai_conversations'][$key][$conversationId] = ['expires_at' => time() + max(60, UthengaTieConfig::integer('TIE_AI_MEMORY_SECONDS', 1800)), 'turns' => array_slice($turns, -$maxTurns)];
    }

    private function prune(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        $conversations = $_SESSION['tie_ai_conversations'] ?? [];
        if (!is_array($conversations)) { $_SESSION['tie_ai_conversations'] = []; return; }
        foreach ($conversations as $userKey => $items) foreach (is_array($items) ? $items : [] as $id => $entry) {
            if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) < time()) unset($conversations[$userKey][$id]);
        }
        $_SESSION['tie_ai_conversations'] = $conversations;
    }
    private function userKey(string $userId): string { return hash('sha256', 'tie-ai-memory:' . $userId); }
}

/** Removes common direct identifiers before anything is sent to an LLM. */
final class UthengaTieAiSanitizer
{
    public static function text(string $value, int $maxLength = 1000): string
    {
        $value = preg_replace('/[\r\n\t]+/', ' ', trim($value)) ?? '';
        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $value) ?? $value;
        $value = preg_replace('/(?<!\d)(?:\+?\d[\d\s\-()]{6,}\d)(?!\d)/', '[redacted-phone]', $value) ?? $value;
        return substr($value, 0, $maxLength);
    }
}

/** Fixed tools are server invoked; no provider can issue arbitrary calls. */
final class UthengaTieAiToolOrchestrator
{
    private UthengaTieTravelContextService $travelContext;
    private UthengaTieRecommendationModule $recommendation;
    private UthengaTieBudgetModule $budget;

    public function __construct(UthengaTieTravelContextService $travelContext, UthengaTieRecommendationModule $recommendation, UthengaTieBudgetModule $budget)
    {
        $this->travelContext = $travelContext; $this->recommendation = $recommendation; $this->budget = $budget;
    }

    public function execute(string $userId, UthengaTieAiConversationRequest $request): array
    {
        $started = microtime(true);
        $context = $this->travelContext->build($userId, $request->recommendationRequest->contextRequest);
        $recommendation = $this->recommendation->rank($request->recommendationRequest, $context)->toArray();
        $recommendations = [];
        foreach (($recommendation['recommendations'] ?? []) as $item) $recommendations[] = $this->publicRecommendation($item);
        $budget = $this->budget->summarize($recommendation['recommendations'] ?? [], $request->recommendationRequest->contextRequest->trip);
        return [
            'travel_context' => $this->publicContext($context->toArray()),
            'recommendations' => $recommendations,
            'budget' => $this->publicBudget($budget),
            'tool_calls' => [
                ['name' => 'travel_context', 'status' => 'ok'],
                ['name' => 'availability_validation', 'status' => 'embedded_in_travel_context'],
                ['name' => 'recommendations', 'status' => 'ok'],
                ['name' => 'budget_analysis', 'status' => 'ok'],
                ['name' => 'location_context', 'status' => $context->data['location'] === null ? 'not_supplied' : 'sanitized'],
            ],
            'provenance' => ['travel_context' => 'travel-context/v1', 'recommendations' => UthengaTieRecommendationResult::SCHEMA_VERSION, 'budget' => UthengaTieBudgetService::VERSION, 'marketplace_facts' => 'server_authoritative'],
            'duration_ms' => round((microtime(true) - $started) * 1000, 2),
        ];
    }

    private function publicContext(array $context): array
    {
        $trip = $context['trip'] ?? []; $location = $context['location'] ?? null;
        $geography = is_array($location) ? ($location['geographic_context'] ?? []) : [];
        return [
            'trip' => array_intersect_key(is_array($trip) ? $trip : [], array_flip(['origin', 'destination', 'start_date', 'end_date', 'travellers', 'budget', 'currency', 'preferences', 'travel_mode'])),
            'active_booking_count' => (int) ($context['bookings']['count'] ?? 0),
            'location' => is_array($geography) ? array_filter(array_intersect_key($geography, array_flip(['country', 'region', 'district', 'city', 'area', 'status'])), static fn($value): bool => $value !== null && $value !== '') : null,
        ];
    }

    private function publicRecommendation(array $item): array
    {
        $candidate = is_array($item['candidate'] ?? null) ? $item['candidate'] : [];
        $location = is_array($candidate['location'] ?? null) ? $candidate['location'] : [];
        $vendor = is_array($candidate['vendor'] ?? null) ? $candidate['vendor'] : [];
        return [
            'id' => (string) ($candidate['service_id'] ?? ''),
            'title' => (string) ($candidate['title'] ?? ''),
            'category' => (string) ($candidate['category']['code'] ?? ''),
            'vendor' => UthengaTieAiSanitizer::text((string) ($vendor['name'] ?? $vendor['business_name'] ?? ''), 160),
            'price' => array_intersect_key(is_array($candidate['price'] ?? null) ? $candidate['price'] : [], array_flip(['amount', 'currency'])),
            'location' => array_intersect_key($location, array_flip(['display_name', 'city', 'district', 'region', 'country'])),
            'availability' => array_intersect_key(is_array($item['availability'] ?? null) ? $item['availability'] : [], array_flip(['status', 'declared_units'])),
            'score' => (float) ($item['recommendation_score']['weighted_score'] ?? 0),
            'explanation' => array_values(array_map(static fn(array $reason): array => array_intersect_key($reason, array_flip(['code', 'message'])), is_array($item['explanation'] ?? null) ? $item['explanation'] : [])),
        ];
    }

    private function publicBudget(array $budget): array
    {
        return array_intersect_key($budget, array_flip(['schema_version', 'currency', 'trip_days', 'travellers', 'components', 'estimated_total', 'budget', 'remaining_budget', 'status', 'shortfall', 'warnings', 'provenance']));
    }
}

final class UthengaTieAiPromptBuilder
{
    public const VERSION = 'ai-conversation-prompt/v1';
    public function build(UthengaTieAiConversationRequest $request, array $evidence, array $history): array
    {
        return [
            'prompt_version' => UthengaTieConfig::string('TIE_AI_PROMPT_VERSION', self::VERSION),
            'system' => 'You are Uthenga Travel Intelligence. Use only supplied Uthenga marketplace evidence. Never suggest external providers, other websites, Google, or services outside Uthenga. If evidence is empty, clearly say that no matching Uthenga inventory is currently available and suggest only changing the supplied destination, dates, budget, preferences, or category. Do not claim a service is booked, reserved, confirmed, or guaranteed. Do not invent vendors, prices, availability, routes, taxes, allowances, or marketplace facts. Budget values are deterministic estimates, not payment quotes. Explain any budget status using the supplied budget evidence. Return JSON matching the supplied response schema and reference recommendations only by id.',
            'user_message' => UthengaTieAiSanitizer::text($request->message),
            'conversation_history' => array_slice($history, -max(0, UthengaTieConfig::integer('TIE_AI_MAX_HISTORY', 8) * 2)),
            'evidence' => ['travel_context' => $evidence['travel_context'], 'recommendations' => $evidence['recommendations'], 'budget' => $evidence['budget'] ?? null],
            'tool_mode' => UthengaTieConfig::string('TIE_AI_TOOL_MODE', 'server_orchestrated'),
        ];
    }
    public static function schema(): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['message', 'recommendation_ids', 'suggested_actions', 'follow_up_questions', 'confidence'], 'properties' => [
            'message' => ['type' => 'string'], 'recommendation_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
            'suggested_actions' => ['type' => 'array', 'items' => ['type' => 'string']], 'follow_up_questions' => ['type' => 'array', 'items' => ['type' => 'string']], 'confidence' => ['type' => 'string'],
        ]];
    }
}

/** Explicit deterministic test/development provider; it never contacts a third party. */
final class UthengaTieMockLlmProvider implements UthengaTieLlmProvider
{
    public function generate(array $request): array { return $this->generateStructured($request, []); }
    public function generateStructured(array $request, array $schema): array
    {
        $recommendations = is_array($request['evidence']['recommendations'] ?? null) ? $request['evidence']['recommendations'] : [];
        $ids = array_values(array_filter(array_map(static fn(array $item): string => (string) ($item['id'] ?? ''), $recommendations)));
        return [
            'message' => $ids === [] ? 'I could not find a validated option for the current travel context. You can adjust the dates, budget, or destination.' : 'I found ' . count($ids) . ' validated option(s) that match your current travel context. Review the ranked options below before booking.',
            'recommendation_ids' => $ids,
            'suggested_actions' => $ids === [] ? ['refine_preferences'] : ['view_recommendation', 'refine_preferences'],
            'follow_up_questions' => $ids === [] ? ['Would you like to adjust your dates, budget, or destination?'] : ['Would you like to compare these options or change your preferences?'],
            'confidence' => $ids === [] ? 'LOW' : 'MEDIUM',
            'usage' => ['input_tokens' => 0, 'output_tokens' => 0, 'mock' => true],
        ];
    }
    public function healthCheck(): array { return ['available' => true, 'provider' => 'mock']; }
}

/** OpenAI Responses adapter. It is selected only with a local secret and model. */
final class UthengaTieOpenAiLlmProvider implements UthengaTieLlmProvider
{
    private string $apiKey; private string $model;
    public function __construct(string $apiKey, string $model) { $this->apiKey = $apiKey; $this->model = $model; }
    public function generate(array $request): array { return $this->generateStructured($request, UthengaTieAiPromptBuilder::schema()); }
    public function generateStructured(array $request, array $schema): array
    {
        if (!function_exists('curl_init')) throw UthengaTieErrors::providerUnavailable('openai_curl');
        $body = [
            'model' => $this->model,
            'instructions' => (string) ($request['system'] ?? ''),
            'input' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => json_encode(['message' => $request['user_message'] ?? '', 'history' => $request['conversation_history'] ?? [], 'evidence' => $request['evidence'] ?? []], JSON_UNESCAPED_SLASHES),
                ]],
            ]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'uthenga_ai_response', 'strict' => true, 'schema' => $schema]],
            'store' => false,
            'max_output_tokens' => max(64, UthengaTieConfig::integer('TIE_AI_MAX_TOKENS', 800)),
            'temperature' => max(0.0, min(2.0, UthengaTieConfig::decimal('TIE_AI_TEMPERATURE', 0.0))),
        ];
        $handle = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(1, UthengaTieConfig::integer('TIE_AI_TIMEOUT', 15)), CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES)]);
        $raw = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); curl_close($handle);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) throw UthengaTieErrors::providerUnavailable('openai');
        $text = is_string($decoded['output_text'] ?? null) ? $decoded['output_text'] : $this->outputText($decoded['output'] ?? []);
        $response = is_string($text) ? json_decode($text, true) : null;
        if (!is_array($response)) throw UthengaTieErrors::providerUnavailable('openai_malformed_response');
        $response['usage'] = array_intersect_key(is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [], array_flip(['input_tokens', 'output_tokens', 'total_tokens']));
        return $response;
    }
    public function healthCheck(): array { return ['available' => true, 'provider' => 'openai']; }
    private function outputText(array $output): ?string
    {
        foreach ($output as $item) foreach (is_array($item['content'] ?? null) ? $item['content'] : [] as $content) if (is_string($content['text'] ?? null)) return $content['text'];
        return null;
    }
}

/**
 * Groq's OpenAI-compatible Chat Completions adapter. Strict JSON schema is
 * supported by the selected GPT-OSS production model; provider tools remain
 * disabled because TIE invokes its own fixed server-side tools first.
 */
final class UthengaTieGroqLlmProvider implements UthengaTieLlmProvider
{
    private string $apiKey; private string $model;
    public function __construct(string $apiKey, string $model) { $this->apiKey = $apiKey; $this->model = $model; }
    public function generate(array $request): array { return $this->generateStructured($request, UthengaTieAiPromptBuilder::schema()); }
    public function generateStructured(array $request, array $schema): array
    {
        if (!function_exists('curl_init')) throw UthengaTieErrors::providerUnavailable('groq_curl');
        $context = json_encode(['message' => $request['user_message'] ?? '', 'history' => $request['conversation_history'] ?? [], 'evidence' => $request['evidence'] ?? []], JSON_UNESCAPED_SLASHES);
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => (string) ($request['system'] ?? '')],
                ['role' => 'user', 'content' => $context],
            ],
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'uthenga_ai_response', 'strict' => true, 'schema' => $schema]],
            'store' => false,
            'max_completion_tokens' => max(64, UthengaTieConfig::integer('TIE_AI_MAX_TOKENS', 800)),
            // Groq normalizes zero; use the smallest positive deterministic value.
            'temperature' => max(0.00000001, min(2.0, UthengaTieConfig::decimal('TIE_AI_TEMPERATURE', 0.0))),
        ];
        $handle = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(1, UthengaTieConfig::integer('TIE_AI_TIMEOUT', 15)), CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey, 'Content-Type: application/json', 'Groq-Beta: inference-metrics'], CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES)]);
        $raw = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); curl_close($handle);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) throw UthengaTieErrors::providerUnavailable('groq');
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        $response = is_string($content) ? json_decode($content, true) : null;
        if (!is_array($response)) throw UthengaTieErrors::providerUnavailable('groq_malformed_response');
        $response['usage'] = array_intersect_key(is_array($decoded['usage'] ?? null) ? $decoded['usage'] : [], array_flip(['prompt_tokens', 'completion_tokens', 'total_tokens']));
        if (is_numeric($response['usage']['prompt_tokens'] ?? null)) $response['usage']['input_tokens'] = $response['usage']['prompt_tokens'];
        if (is_numeric($response['usage']['completion_tokens'] ?? null)) $response['usage']['output_tokens'] = $response['usage']['completion_tokens'];
        return $response;
    }
    public function healthCheck(): array { return ['available' => true, 'provider' => 'groq']; }
}

/** The factory is the only provider-selection boundary. */
final class UthengaTieLlmProviderFactory
{
    public static function configured(): UthengaTieLlmProvider
    {
        $provider = strtolower(UthengaTieConfig::string('TIE_AI_PROVIDER', UthengaTieConfig::string('TIE_LLM_PROVIDER')));
        return match ($provider) {
            'mock' => new UthengaTieMockLlmProvider(),
            'openai' => self::openAi(),
            'groq' => self::groq(),
            'gemini', 'claude', 'anthropic', 'azure_openai', 'ollama' => new UthengaTieUnavailableLlmProvider($provider),
            default => new UthengaTieUnavailableLlmProvider(),
        };
    }
    private static function openAi(): UthengaTieLlmProvider
    {
        $key = UthengaTieConfig::string('TIE_OPENAI_API_KEY'); $model = UthengaTieConfig::string('TIE_AI_MODEL', UthengaTieConfig::string('TIE_LLM_MODEL'));
        return $key !== '' && $model !== '' ? new UthengaTieOpenAiLlmProvider($key, $model) : new UthengaTieUnavailableLlmProvider('openai_unconfigured');
    }
    private static function groq(): UthengaTieLlmProvider
    {
        $key = UthengaTieConfig::string('TIE_GROQ_API_KEY'); $model = UthengaTieConfig::string('TIE_AI_MODEL', 'openai/gpt-oss-20b');
        return $key !== '' ? new UthengaTieGroqLlmProvider($key, $model) : new UthengaTieUnavailableLlmProvider('groq_unconfigured');
    }
}

final class UthengaTieAiResponseValidator
{
    private const ACTIONS = ['view_recommendation', 'refine_preferences', 'change_dates', 'change_budget', 'book_through_marketplace'];
    public function validate(array $payload, array $evidence): array
    {
        $allowed = array_flip(array_filter(array_map(static fn(array $item): string => (string) ($item['id'] ?? ''), $evidence['recommendations'] ?? [])));
        $message = is_string($payload['message'] ?? null) ? UthengaTieAiSanitizer::text($payload['message'], 1000) : '';
        if ($message === '' || preg_match('/\b(booked|reserved|guaranteed)\b|\b(?:booking|reservation)\s+(?:is\s+)?confirmed\b|\b(other sources?|external (?:source|provider|website)|outside Uthenga|google|booking\.com)\b/i', $message)) return ['valid' => false, 'reason' => 'unsafe_or_missing_message'];
        $ids = $payload['recommendation_ids'] ?? [];
        if (!is_array($ids) || array_diff(array_map('strval', $ids), array_keys($allowed))) return ['valid' => false, 'reason' => 'unsupported_recommendation_reference'];
        $actions = $payload['suggested_actions'] ?? [];
        if (!is_array($actions) || array_diff(array_map('strval', $actions), self::ACTIONS)) return ['valid' => false, 'reason' => 'unsupported_action'];
        $questions = $payload['follow_up_questions'] ?? [];
        if (!is_array($questions)) return ['valid' => false, 'reason' => 'invalid_follow_up_questions'];
        $questions = array_slice(array_values(array_filter(array_map(static fn($value): string => UthengaTieAiSanitizer::text((string) $value, 240), $questions), static fn(string $value): bool => $value !== '')), 0, 3);
        $confidence = strtoupper((string) ($payload['confidence'] ?? 'LOW'));
        if (!in_array($confidence, ['LOW', 'MEDIUM', 'HIGH'], true)) $confidence = 'LOW';
        $canonical = [];
        foreach ($evidence['recommendations'] as $item) if (in_array((string) $item['id'], $ids, true)) $canonical[] = $item;
        return ['valid' => true, 'response' => ['message' => $message, 'recommendations' => $canonical, 'suggested_actions' => array_values(array_unique(array_map('strval', $actions))), 'follow_up_questions' => $questions, 'confidence' => $confidence]];
    }

    public function fallback(array $evidence): array
    {
        $recommendations = $evidence['recommendations'] ?? [];
        return [
            'message' => $recommendations === [] ? 'I could not prepare a conversational response, but no validated recommendation is currently available for this context.' : 'Here are the validated, ranked options for your current travel context. Please review the details before booking.',
            'recommendations' => $recommendations,
            'suggested_actions' => $recommendations === [] ? ['refine_preferences'] : ['view_recommendation', 'refine_preferences'],
            'follow_up_questions' => $recommendations === [] ? ['Would you like to adjust your dates, budget, or destination?'] : ['Would you like to refine these recommendations?'],
            'confidence' => $recommendations === [] ? 'LOW' : 'MEDIUM',
        ];
    }
}

final class UthengaTieConversationService implements UthengaTieConversationModule
{
    private UthengaTieAiToolOrchestrator $tools;
    private UthengaTieLlmGateway $gateway;
    private UthengaTieConversationMemory $memory;
    private UthengaTieAiPromptBuilder $prompts;
    private UthengaTieAiResponseValidator $validator;

    public function __construct(UthengaTieTravelContextService $travelContext, UthengaTieRecommendationModule $recommendation, UthengaTieBudgetModule $budget, UthengaTieLlmGateway $gateway, UthengaTieConversationMemory $memory, UthengaTieAiPromptBuilder $prompts, UthengaTieAiResponseValidator $validator)
    {
        $this->tools = new UthengaTieAiToolOrchestrator($travelContext, $recommendation, $budget);
        $this->gateway = $gateway; $this->memory = $memory; $this->prompts = $prompts; $this->validator = $validator;
    }

    public function chat(UthengaTieAiConversationRequest $request, string $userId): UthengaTieAiConversationResponse
    {
        $started = microtime(true); $requestId = UthengaTieObservability::requestId();
        $history = $this->memory->history($userId, $request->conversationId);
        $evidence = $this->tools->execute($userId, $request);
        $prompt = $this->prompts->build($request, $evidence, $history);
        $provider = $this->gateway->provider(); $fallback = false; $validationStatus = 'valid'; $retryCount = 0; $usage = [];
        try {
            $raw = $this->gateway->generateStructured($prompt, UthengaTieAiPromptBuilder::schema());
            $usage = array_intersect_key(is_array($raw['usage'] ?? null) ? $raw['usage'] : [], array_flip(['input_tokens', 'output_tokens', 'total_tokens']));
            $checked = $this->validator->validate($raw, $evidence);
            if (($checked['valid'] ?? false) !== true) {
                $response = $this->validator->fallback($evidence); $fallback = true; $validationStatus = (string) ($checked['reason'] ?? 'invalid_provider_response');
                UthengaTieMetrics::record('ai_response_validation_failures', 1, $requestId, ['module' => 'ai', 'feature' => 'conversation', 'provider' => $provider, 'status' => $validationStatus]);
            } else $response = $checked['response'];
        } catch (Throwable $error) {
            $response = $this->validator->fallback($evidence); $fallback = true; $validationStatus = 'provider_unavailable';
            UthengaTieMetrics::record('ai_provider_failures', 1, $requestId, ['module' => 'ai', 'feature' => 'conversation', 'provider' => $provider, 'status' => 'provider_unavailable']);
        }
        $duration = round((microtime(true) - $started) * 1000, 2);
        $result = new UthengaTieAiConversationResponse([
            'schema_version' => UthengaTieAiConversationResponse::SCHEMA_VERSION,
            'conversation_id' => $request->conversationId,
            'message' => $response['message'], 'recommendations' => $response['recommendations'], 'suggested_actions' => $response['suggested_actions'], 'follow_up_questions' => $response['follow_up_questions'], 'confidence' => $response['confidence'], 'budget' => $evidence['budget'],
            'diagnostics' => ['provider' => $provider, 'model' => UthengaTieConfig::string('TIE_AI_MODEL', UthengaTieConfig::string('TIE_LLM_MODEL')), 'tool_calls' => $evidence['tool_calls'], 'usage' => $usage, 'validation_status' => $validationStatus, 'fallback_used' => $fallback, 'duration_ms' => $duration],
            'provenance' => $evidence['provenance'] + ['conversation_memory' => 'session_only_bounded', 'provider_output' => 'validated_untrusted_explanation'],
        ]);
        $this->memory->append($userId, $request->conversationId, $request->message, $result->toArray());
        UthengaTieObservability::log('ai.conversation_completed', $requestId, ['module' => 'ai', 'feature' => 'conversation', 'status' => $fallback ? 'fallback' : 'ok', 'duration_ms' => $duration, 'provider' => $provider, 'model' => UthengaTieConfig::string('TIE_AI_MODEL', UthengaTieConfig::string('TIE_LLM_MODEL')), 'tool_count' => count($evidence['tool_calls']), 'retry_count' => $retryCount, 'validation_status' => $validationStatus, 'conversation_memory' => 'session_only']);
        UthengaTieMetrics::record('ai_tool_calls', count($evidence['tool_calls']), $requestId, ['module' => 'ai', 'feature' => 'conversation', 'provider' => $provider, 'status' => 'ok']);
        if (is_numeric($usage['input_tokens'] ?? null)) UthengaTieMetrics::record('ai_input_tokens', (float) $usage['input_tokens'], $requestId, ['module' => 'ai', 'feature' => 'conversation', 'provider' => $provider, 'status' => 'ok']);
        if (is_numeric($usage['output_tokens'] ?? null)) UthengaTieMetrics::record('ai_output_tokens', (float) $usage['output_tokens'], $requestId, ['module' => 'ai', 'feature' => 'conversation', 'provider' => $provider, 'status' => 'ok']);
        UthengaTieMetrics::record('ai_chat_latency_ms', $duration, $requestId, ['module' => 'ai', 'feature' => 'conversation', 'provider' => $provider, 'status' => $fallback ? 'fallback' : 'ok']);
        UthengaTieMetrics::record('ai_chat_successful_responses', 1, $requestId, ['module' => 'ai', 'feature' => 'conversation', 'provider' => $provider, 'status' => $fallback ? 'fallback' : 'ok']);
        return $result;
    }
}
