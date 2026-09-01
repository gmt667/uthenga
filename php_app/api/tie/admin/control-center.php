<?php
/** Authenticated read-only contract for the canonical PHP Admin Overview. */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';
require_once __DIR__ . '/../../../admin/includes/control_center_data.php';

$requestId = UthengaTieObservability::requestId();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') throw UthengaTieErrors::validation(['method' => 'GET is required.']);
    $user = UthengaTieApi::requireAuthenticatedUser();
    requireAdmin();
    if (!adminHasPermission('overview.view')) adminDenyPermission('overview.view');
    $permissions = array_keys(array_filter(
        adminPermissionRegistry(),
        static fn(array $definition, string $permission): bool => adminHasPermission($permission),
        ARRAY_FILTER_USE_BOTH
    ));
    $overview = acc_admin_overview_data($permissions);
    UthengaTieObservability::log('admin.overview_read', $requestId, ['module' => 'admin', 'role' => $user['role']]);
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'overview' => $overview]);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}
