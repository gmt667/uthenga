<?php
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user,, $requestId] = accommodation_v2_context();
    $workspace = new UthengaAccommodationPropertyWorkspace($GLOBALS['pdo']);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        accommodation_v2_respond($requestId, 'detail', $workspace->detail(accommodation_v2_property(), $user['id']));
    }
    $input = accommodation_v2_write('accommodation_property_profile', $requestId);
    $property = $workspace->saveProfile(accommodation_v2_property($input), $user['id'], $input, $requestId);
    accommodation_v2_respond($requestId, 'property', $property);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
