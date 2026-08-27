<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireFeature('availability');
    UthengaTieApi::requireAuthenticatedUser();
    UthengaTieApi::requireCsrf();
    $request = UthengaTieAvailabilityContracts::request(UthengaTieApi::input());
    $result = (new UthengaTieKernel())->availability->validate($request);
    $ruleCodes = array_map(static fn(array $rule): string => (string) $rule['rule_code'], $result['violations']);
    UthengaTieObservability::log('availability.validated', $requestId, ['module' => 'availability', 'status' => $result['eligible'] ? 'eligible' : 'ineligible', 'service_id' => $result['service_id'], 'rule_codes' => $ruleCodes, 'duration_ms' => $result['duration_ms']]);
    UthengaTieMetrics::record('requests', 1, $requestId, ['module' => 'availability', 'status' => $result['eligible'] ? 'eligible' : 'ineligible']);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'validation' => $result]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
