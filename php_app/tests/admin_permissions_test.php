<?php
/** Isolated checks for canonical permission names and legacy compatibility. */
require_once __DIR__ . '/../includes/auth_check.php';

function admin_permissions_assert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$registry = adminPermissionRegistry();
admin_permissions_assert(isset($registry['overview.view']), 'Overview permission is missing.');
admin_permissions_assert(isset($registry['quick_taxi.view']), 'Quick Taxi permission is missing.');
admin_permissions_assert(!isset($registry['mbanda.view']), 'Internal compatibility name must not be a canonical permission.');
admin_permissions_assert(adminNormalizePermissionList(['unknown.permission']) === [], 'Unknown permissions must deny by default.');
admin_permissions_assert(
    in_array('quick_taxi.view', adminNormalizePermissionList(['mbanda']), true),
    'Mbanda compatibility mapping must grant only Quick Taxi view access.'
);
admin_permissions_assert(
    in_array('vendors.review', adminNormalizePermissionList(['vendor_review']), true),
    'Legacy vendor review mapping is missing.'
);
admin_permissions_assert(
    !in_array('vendors.manage', adminNormalizePermissionList(['vendor_review']), true),
    'Legacy permissions must not silently grant vendor management.'
);

echo "Admin permission registry tests passed.\n";
