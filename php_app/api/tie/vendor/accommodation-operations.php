<?php
/** Retired accommodation operations v1 endpoint; v2 owns all operational writes. */
require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0, must-revalidate');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => [
        'code' => 'accommodation_operations_api_retired',
        'message' => 'Accommodation operations API v1 has been retired. Refresh the enterprise accommodation workspace.',
    ],
    'replacement' => BASE_URL . 'api/tie/vendor/accommodation/',
    'workspace' => BASE_URL . 'ai.php#/accommodation',
], JSON_UNESCAPED_SLASHES);
exit;
