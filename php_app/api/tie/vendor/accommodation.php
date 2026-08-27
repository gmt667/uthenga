<?php
/**
 * Retired accommodation v1 control endpoint.
 *
 * Kept temporarily as an explicit tombstone so stale clients fail safely and
 * cannot mutate the compatibility projections that v2 replaced.
 */
require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0, must-revalidate');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => [
        'code' => 'accommodation_api_retired',
        'message' => 'Accommodation API v1 has been retired. Refresh the enterprise accommodation workspace.',
    ],
    'replacement' => BASE_URL . 'api/tie/vendor/accommodation/',
    'workspace' => BASE_URL . 'ai.php#/accommodation',
], JSON_UNESCAPED_SLASHES);
exit;
