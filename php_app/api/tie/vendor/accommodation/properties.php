<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user,, $requestId] = accommodation_v2_context();
    $workspace = new UthengaAccommodationPropertyWorkspace($GLOBALS['pdo']);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        accommodation_v2_respond($requestId, 'portfolio', $workspace->workspace($user['id']));
    }
    $input = accommodation_v2_write('accommodation_property_portfolio', $requestId);
    $propertyId = UthengaAccommodationContracts::id($input['property_id'] ?? '', 'property_id');
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'activate_context' => $workspace->activate($propertyId, $user['id'], $requestId),
        'duplicate' => $workspace->duplicate($propertyId, $user['id'], $requestId),
        default => throw UthengaTieErrors::validation(['action' => 'Use activate_context or duplicate.']),
    };
    accommodation_v2_respond($requestId, 'result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
