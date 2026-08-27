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
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';
require_once __DIR__ . '/../../../admin/includes/control_center_data.php';

$requestId = UthengaTieObservability::requestId();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    }

    $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], ADMIN_ROLES, true)) {
        throw UthengaTieErrors::authorization();
    }

    $allSections = [
        'overview', 'operations', 'vendors', 'customers', 'marketplace',
        'bookings', 'payments', 'shop', 'tie', 'journeys', 'events',
        'content', 'notifications', 'analytics', 'system', 'security',
        'settings', 'support',
    ];
    $permissions = [];
    if ($user['role'] === ROLE_SUPER_ADMIN) {
        $permissions = $allSections;
    } elseif (uthenga_table_exists('admin_permissions')) {
        $row = acc_safe_row('SELECT permissions FROM admin_permissions WHERE user_id = ?', [$user['id']]);
        $stored = json_decode((string) ($row['permissions'] ?? '[]'), true);
        $stored = is_array($stored) ? $stored : [];
        $mapping = [
            'vendor_review' => ['vendors'],
            'listings' => ['marketplace', 'events', 'content'],
            'bookings' => ['bookings', 'payments', 'journeys'],
            'support' => ['support', 'customers', 'notifications'],
            'reports' => ['analytics', 'tie', 'operations', 'system'],
            'settings' => ['settings'],
            'logs' => ['security'],
            'admin_users' => ['security'],
        ];
        $permissions = ['overview'];
        foreach ($stored as $permission) {
            $permissions = array_merge($permissions, $mapping[$permission] ?? []);
        }
        $permissions = array_values(array_unique($permissions));
    } else {
        $permissions = ['overview'];
    }

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
        'logout' => $base . 'logout.php',
    ];

    if (!in_array('vendors', $permissions, true)) $data['vendors'] = [];
    if (!in_array('customers', $permissions, true)) $data['customers'] = [];
    if (!in_array('bookings', $permissions, true)) $data['bookings'] = [];
    if (!in_array('payments', $permissions, true)) $data['payments']['recent'] = [];
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
