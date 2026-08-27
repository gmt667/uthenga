<?php
/** Phase 8 provider-neutral conversational planning and safety coverage. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_ai_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

$input = ['message' => 'Find a comfortable place in Lilongwe. Email me at name@example.test.', 'destination' => 'Lilongwe', 'travellers' => 1, 'category' => 'accommodation', 'limit' => 3];
$request = UthengaTieAiConversationContracts::request($input, 'USR-TEST');
tie_ai_assert($request->conversationId !== '', 'Conversation contract creates a server conversation ID.');
try {
    UthengaTieAiConversationContracts::request($input + ['recommendations' => []], 'USR-TEST');
    throw new RuntimeException('Client supplied recommendation evidence was accepted.');
} catch (UthengaTieException $error) {
    tie_ai_assert($error->type() === 'validation_error', 'Client supplied marketplace evidence is rejected.');
}

$evidence = [
    'travel_context' => ['trip' => ['destination' => 'Lilongwe', 'travellers' => 1], 'location' => ['city' => 'Lilongwe']],
    'recommendations' => [[
        'id' => 'SERVICE-1', 'title' => 'Verified Stay', 'category' => 'accommodation', 'vendor' => 'Verified Vendor',
        'price' => ['amount' => 50000, 'currency' => 'MWK'], 'location' => ['display_name' => 'Lilongwe'],
        'availability' => ['status' => 'available'], 'score' => 82.0, 'explanation' => [['code' => 'AVAILABLE', 'message' => 'Available under current rules.']],
    ]],
];
$prompt = (new UthengaTieAiPromptBuilder())->build($request, $evidence, []);
tie_ai_assert(strpos($prompt['user_message'], 'name@example.test') === false, 'Common email addresses are redacted before provider prompts.');
tie_ai_assert(!str_contains(json_encode($prompt), 'latitude') && !str_contains(json_encode($prompt), 'longitude'), 'Prompt evidence has no coordinates.');

$mock = new UthengaTieMockLlmProvider();
$raw = $mock->generateStructured($prompt, UthengaTieAiPromptBuilder::schema());
$validator = new UthengaTieAiResponseValidator();
$validated = $validator->validate($raw, $evidence);
tie_ai_assert($validated['valid'] === true, 'Mock provider response validates against canonical evidence.');
tie_ai_assert($validated['response']['recommendations'][0]['id'] === 'SERVICE-1', 'Responses return canonical recommendation data rather than provider facts.');

$unsupported = $validator->validate(['message' => 'Here is an option.', 'recommendation_ids' => ['FAKE-SERVICE'], 'suggested_actions' => [], 'follow_up_questions' => [], 'confidence' => 'HIGH'], $evidence);
tie_ai_assert($unsupported['valid'] === false && $unsupported['reason'] === 'unsupported_recommendation_reference', 'Hallucinated recommendation identifiers are rejected.');
$unsafe = $validator->validate(['message' => 'Your booking is confirmed.', 'recommendation_ids' => ['SERVICE-1'], 'suggested_actions' => [], 'follow_up_questions' => [], 'confidence' => 'HIGH'], $evidence);
tie_ai_assert($unsafe['valid'] === false, 'Booking-confirmation claims are rejected.');

$fallback = $validator->fallback($evidence);
tie_ai_assert($fallback['recommendations'][0]['id'] === 'SERVICE-1', 'Provider failure fallback preserves validated evidence.');
tie_ai_assert((new UthengaTieUnavailableLlmProvider('openai'))->healthCheck()['provider'] === 'openai', 'Provider adapter boundary identifies unavailable configured providers without network access.');
tie_ai_assert((new UthengaTieGroqLlmProvider('test-key-not-used', 'openai/gpt-oss-20b'))->healthCheck()['provider'] === 'groq', 'Groq adapter stays behind the provider-neutral gateway boundary.');

if (session_status() === PHP_SESSION_ACTIVE) {
    $memory = new UthengaTieConversationMemory();
    $memory->append('USER-A', 'conversation-a', 'first message', $fallback);
    tie_ai_assert(count($memory->history('USER-A', 'conversation-a')) === 2, 'Memory is bounded and session-scoped.');
    tie_ai_assert($memory->history('USER-B', 'conversation-a') === [], 'Conversation memory is isolated by user.');
}

if ($pdo instanceof PDO) {
    $user = $pdo->query('SELECT id FROM users LIMIT 1')->fetch();
    tie_ai_assert(is_array($user), 'Configured database has a user for AI integration.');
    $integration = UthengaTieAiConversationContracts::request(['message' => 'Show validated stays.', 'destination' => 'Lilongwe', 'travellers' => 1, 'category' => 'accommodation', 'limit' => 3], (string) $user['id']);
    $kernel = new UthengaTieKernel();
    $conversation = new UthengaTieConversationService($kernel->travelContext, $kernel->recommendation, $kernel->budget, new UthengaTieLlmGateway(new UthengaTieMockLlmProvider()), new UthengaTieConversationMemory(), new UthengaTieAiPromptBuilder(), new UthengaTieAiResponseValidator());
    $response = $conversation->chat($integration, (string) $user['id'])->toArray();
    tie_ai_assert($response['schema_version'] === 'ai-conversation-response/v1', 'Conversation engine returns a versioned response.');
    tie_ai_assert($response['provenance']['marketplace_facts'] === 'server_authoritative', 'Conversation response keeps deterministic marketplace provenance.');
}

echo "TIE Phase 8 AI conversation tests passed.\n";
