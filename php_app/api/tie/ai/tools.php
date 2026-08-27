<?php
/**
 * Internal-only PHP tool gateway. It permits deterministic read operations
 * only; booking, payment, inventory, and marketplace mutation have no route.
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';
require_once __DIR__ . '/../../../includes/tie/AiServiceGateway.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    $serviceToken = UthengaTieConfig::string('TIE_AI_TOOL_TOKEN', '');
    if (strlen($serviceToken) < 32 || !hash_equals('Bearer ' . $serviceToken, (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''))) throw UthengaTieErrors::authorization();
    $input = UthengaTieApi::input(); $tool = strtolower(trim((string) ($input['tool'] ?? '')));
    $scope = UthengaTieAiServiceCapability::verify((string) ($input['capability'] ?? ''), $tool); $arguments = (array) ($input['arguments'] ?? []); $kernel = new UthengaTieKernel();
    $result = match ($tool) {
        'travel_context', 'location_context' => (function () use ($kernel, $arguments, $scope, $tool): array { $context = $kernel->travelContext->build($scope['user_id'], UthengaTieContextContracts::build($arguments, $scope['user_id']))->toArray(); return $tool === 'location_context' ? ['location' => $context['location'] ?? null, 'geographic_context' => $context['geographic_context'] ?? null] : $context; })(),
        'recommendations' => (function () use ($kernel, $arguments, $scope): array { $request = UthengaTieRecommendationContracts::request($arguments, $scope['user_id']); $context = $kernel->travelContext->build($scope['user_id'], $request->contextRequest); return $kernel->recommendation->rank($request, $context)->toArray(); })(),
        'availability' => $kernel->availability->validate(UthengaTieAvailabilityContracts::request($arguments)),
        'trip_plan' => $kernel->plans->view(UthengaTiePlanContracts::planId($arguments), $scope['user_id'])->toArray(),
        default => throw UthengaTieErrors::authorization(),
    };
    UthengaTieObservability::log('ai.tool.executed', $requestId, ['tool' => $tool]);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'tool' => $tool, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
