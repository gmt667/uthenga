<?php
/**
 * Read-only bridge for the incremental React frontend.
 *
 * The browser keeps using the existing PHP session cookie.  This endpoint
 * exposes only the UI bootstrap data that the React client needs; it does not
 * turn the session into a bearer token or expose marketplace records.
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    }

    $authenticated = isLoggedIn();
    $payload = [
        'success' => true,
        'request_id' => $requestId,
        'authenticated' => $authenticated,
        'features' => UthengaTieFeatureFlags::all(),
        'legacy_fallbacks' => [
            'login' => BASE_URL . 'login.php',
            'main_dashboard' => BASE_URL . 'dashboard.php',
            'customer_workspace' => BASE_URL . 'quick-travel.php',
            'vendor_workspace' => BASE_URL . 'vendor/dashboard.php',
            'driver_workspace' => BASE_URL . 'ai.php#/driver',
            'admin_workspace' => BASE_URL . 'ai.php#/admin',
        ],
        // A Maps JavaScript API key is intentionally a browser key. It is not
        // a server/provider secret and must be restricted in Google Cloud to
        // this application's HTTP referrers and to the Maps JavaScript API.
        'maps' => [
            'provider' => UthengaTieConfig::string('TIE_MAP_PROVIDER', 'google_maps'),
            'enabled' => UthengaTieConfig::string('TIE_MAP_PROVIDER', 'google_maps') === 'google_maps'
                && UthengaTieConfig::string('TIE_GOOGLE_MAPS_BROWSER_KEY') !== '',
            'browser_key' => $authenticated ? UthengaTieConfig::string('TIE_GOOGLE_MAPS_BROWSER_KEY') : '',
        ],
    ];

    if ($authenticated) {
        $payload['user'] = [
            'id' => (string) ($_SESSION['user_id'] ?? ''),
            'name' => (string) ($_SESSION['user_name'] ?? 'Uthenga traveller'),
            'role' => (string) ($_SESSION['user_role'] ?? ''),
        ];
        // The token is intentionally returned only to the authenticated,
        // same-origin session and is required by PHP for every write request.
        $payload['csrf_token'] = (string) ($_SESSION['csrf_token'] ?? '');
    }

    UthengaTieObservability::log('frontend.bootstrap', $requestId, ['authenticated' => $authenticated]);
    UthengaTieApi::respond($payload);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
