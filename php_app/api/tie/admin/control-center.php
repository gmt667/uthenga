<?php
/**
 * Read-only, session-authenticated Admin Control Center contract.
 *
 * All figures originate in the Uthenga database or explicit configuration.
 * Missing providers/telemetry are returned as unavailable capabilities; this
 * endpoint never manufactures operational data for presentation purposes.
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';
require_once __DIR__ . '/../../../admin/includes/control_center_data.php';

$requestId = UthengaTieObservability::requestId();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    }

    $user = UthengaTieApi::requireAuthenticatedUser();
    requireAdmin();

    $allSections = [
        'overview', 'operations', 'vendors', 'customers', 'marketplace',
        'bookings', 'payments', 'shop', 'tie', 'journeys', 'events',
        'content', 'notifications', 'analytics', 'system', 'security',
        'settings', 'support',
    ];
    $sectionPermissions = [
        'overview' => ['overview.view'], 'operations' => ['overview.view'], 'vendors' => ['vendors.view'],
        'customers' => ['support.view'], 'marketplace' => ['events.view', 'stays.view', 'transport.view', 'shop.view'],
        'bookings' => ['bookings.view'], 'payments' => ['payments.view'], 'shop' => ['shop.view'], 'tie' => ['reports.view'],
        'journeys' => ['quick_taxi.view'], 'events' => ['events.view'], 'content' => ['marketing.view'],
        'notifications' => ['support.view'], 'analytics' => ['reports.view'], 'system' => ['platform_health.view'],
        'security' => ['security.view'], 'settings' => ['settings.view'], 'support' => ['support.view'],
    ];
    $permissions = array_values(array_filter($allSections, static function (string $section) use ($sectionPermissions): bool {
        foreach ($sectionPermissions[$section] ?? [] as $permission) if (adminHasPermission($permission)) return true;
        return false;
    }));

    $data = acc_control_center_data();
    $base = rtrim(BASE_URL, '/') . '/';
    $data['environment'] = [
        'name' => APP_ENV,
        'label' => APP_ENV === 'production' ? 'Production' : 'Local / Development',
    ];
    $data['permissions'] = $permissions;
    $data['links'] = [
        'vendor_reviews' => $base . 'admin/vendors.php',
        'service_reviews' => $base . 'ai.php#/admin/service-reviews',
        'marketplace' => $base . 'admin/events.php',
        'bookings' => $base . 'admin/bookings.php',
        'payments' => $base . 'admin/payments.php',
        'shop' => $base . 'admin/shop.php',
        'customers' => $base . 'admin/customers.php',
        'events' => $base . 'admin/events.php',
        'content' => $base . 'admin/popup_manager.php',
        'notifications' => $base . 'admin/notifications.php',
        'analytics' => $base . 'admin/reports.php',
        'system' => $base . 'admin/system-monitor.php',
        'security' => $base . 'admin/security.php',
        'admin_users' => $base . 'admin/users.php',
        'settings' => $base . 'admin/settings.php',
        'support' => $base . 'admin/support.php',
        'profile' => $base . 'admin/profile.php',
        'logout' => $base . 'admin/login.php?logout=1',
    ];

    if (!in_array('vendors', $permissions, true)) $data['vendors'] = [];
    if (!in_array('customers', $permissions, true)) $data['customers'] = [];
    if (!in_array('bookings', $permissions, true)) $data['bookings'] = [];
    if (!in_array('payments', $permissions, true)) $data['payments'] = [];
    if (!in_array('support', $permissions, true)) $data['support'] = [];
    if (!in_array('security', $permissions, true)) $data['audit'] = [];

    UthengaTieObservability::log('admin.control_center_viewed', $requestId, [
        'module' => 'admin',
        'role' => $user['role'],
    ]);
    UthengaTieApi::respond([
        'success' => true,
        'request_id' => $requestId,
        'control_center' => $data,
    ]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
