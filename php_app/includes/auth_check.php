<?php
/**
 * Uthenga - Authentication & Authorization Guard
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

function redirectByRole(string $role): void {
    if ($role === ROLE_SUPER_ADMIN || $role === ROLE_ADMIN) {
        redirect(BASE_URL . 'admin/dashboard.php');
    }
    if (in_array($role, VENDOR_ROLES, true)) {
        redirect(BASE_URL . 'vendor/dashboard.php');
    }
    redirect(BASE_URL . 'dashboard.php');
}

function uthenga_login_url_for_request(array $allowedRoles = []): string {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $isSuperOnly = count($allowedRoles) === 1 && ($allowedRoles[0] ?? '') === ROLE_SUPER_ADMIN;

    if ($isSuperOnly) {
        return BASE_URL . 'admin/super-login.php';
    }

    if (preg_match('~/(?:admin)(?:/|$)~i', $requestUri)) {
        return BASE_URL . 'admin/login.php';
    }

    return BASE_URL . 'login.php';
}

function requireLogin(array $allowedRoles = []): void {
    if (!isLoggedIn()) {
        $loginUrl = uthenga_login_url_for_request($allowedRoles);
        redirect($loginUrl . '?redirect=' . urlencode((string) ($_SERVER['REQUEST_URI'] ?? '')));
    }

    // Validate active device session
    if (isset($_SESSION['device_session_token']) && uthenga_table_exists('device_sessions')) {
        $validSession = dbQueryOne(
            "SELECT id FROM device_sessions WHERE user_id = ? AND session_token = ?",
            [$_SESSION['user_id'], $_SESSION['device_session_token']]
        );
        if (!$validSession) {
            if (($_SESSION['user_role'] ?? '') === ROLE_SUPER_ADMIN) {
                try {
                    require_once __DIR__ . '/security_helper.php';
                    registerDeviceSession((string) $_SESSION['user_id']);
                    $validSession = true;
                } catch (Throwable $e) {
                    $validSession = false;
                }
            }
        }

        if (!$validSession) {
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_role'], $_SESSION['user_email'], $_SESSION['device_session_token']);
            $loginUrl = uthenga_login_url_for_request($allowedRoles);
            redirect($loginUrl . '?session_revoked=1');
        }
    }

    $currentPage = basename($_SERVER['PHP_SELF']);
    $exempt = ['change_password.php', 'logout.php'];
    if (!in_array($currentPage, $exempt, true)) {
        $mustChange = null;
        try {
            $mustChange = dbQueryOne('SELECT must_change_pw AS must_change_required FROM users WHERE id = ?', [$_SESSION['user_id']]);
        } catch (Throwable $e) {
            try {
                $mustChange = dbQueryOne('SELECT must_change_password AS must_change_required FROM users WHERE id = ?', [$_SESSION['user_id']]);
            } catch (Throwable $e2) {
                $mustChange = null;
            }
        }

        if ($mustChange && !empty($mustChange['must_change_required'])) {
            redirect(BASE_URL . 'change_password.php');
        }
    }

    if (!empty($allowedRoles) && !hasRole($allowedRoles)) {
        redirectByRole($_SESSION['user_role'] ?? '');
    }
}

function requireAdmin(): void {
    requireLogin(ADMIN_ROLES);
    if (!adminCurrentAccount()) {
        adminDenyPermission('overview.view');
    }
}

/** Canonical server-side permissions for administrator routes and actions. */
function adminPermissionRegistry(): array {
    return [
        'overview.view' => ['label' => 'View overview', 'group' => 'Overview'],
        'admins.view' => ['label' => 'View administrators', 'group' => 'Users and access'],
        'admins.manage' => ['label' => 'Manage administrator accounts', 'group' => 'Users and access'],
        'permissions.manage' => ['label' => 'Manage administrator permissions', 'group' => 'Users and access'],
        'vendors.view' => ['label' => 'View vendors', 'group' => 'Vendors and services'],
        'vendors.review' => ['label' => 'Review vendor applications', 'group' => 'Vendors and services'],
        'vendors.manage' => ['label' => 'Manage vendor operations', 'group' => 'Vendors and services'],
        'events.view' => ['label' => 'View events', 'group' => 'Marketplace operations'],
        'events.manage' => ['label' => 'Manage events', 'group' => 'Marketplace operations'],
        'stays.view' => ['label' => 'View accommodation', 'group' => 'Marketplace operations'],
        'stays.manage' => ['label' => 'Manage accommodation', 'group' => 'Marketplace operations'],
        'transport.view' => ['label' => 'View transport', 'group' => 'Marketplace operations'],
        'transport.manage' => ['label' => 'Manage transport', 'group' => 'Marketplace operations'],
        'quick_taxi.view' => ['label' => 'View Quick Taxi operations', 'group' => 'Marketplace operations'],
        'quick_taxi.manage' => ['label' => 'Manage Quick Taxi operations', 'group' => 'Marketplace operations'],
        'shop.view' => ['label' => 'View shop operations', 'group' => 'Marketplace operations'],
        'shop.manage' => ['label' => 'Manage shop operations', 'group' => 'Marketplace operations'],
        'bookings.view' => ['label' => 'View bookings', 'group' => 'Bookings and orders'],
        'payments.view' => ['label' => 'View payments and transactions', 'group' => 'Payments and finance'],
        'finance.manage' => ['label' => 'Manage finance controls', 'group' => 'Payments and finance'],
        'settlements.review' => ['label' => 'Review vendor settlements', 'group' => 'Payments and finance'],
        'marketing.view' => ['label' => 'View marketing content', 'group' => 'Content and marketing'],
        'marketing.manage' => ['label' => 'Manage marketing content', 'group' => 'Content and marketing'],
        'support.view' => ['label' => 'View support operations', 'group' => 'Support and communication'],
        'support.manage' => ['label' => 'Manage support operations', 'group' => 'Support and communication'],
        'reports.view' => ['label' => 'View reports and analytics', 'group' => 'Reports and analytics'],
        'security.view' => ['label' => 'View security operations', 'group' => 'Security and audit'],
        'security.manage' => ['label' => 'Manage security operations', 'group' => 'Security and audit'],
        'audit.view' => ['label' => 'View audit logs', 'group' => 'Security and audit'],
        'platform_health.view' => ['label' => 'View platform health', 'group' => 'Platform settings'],
        'settings.view' => ['label' => 'View platform settings', 'group' => 'Platform settings'],
        'settings.manage' => ['label' => 'Manage platform settings', 'group' => 'Platform settings'],
    ];
}

/** Legacy storage remains supported but only maps to explicit, conservative capabilities. */
function adminLegacyPermissionMap(): array {
    return [
        'admin_users' => ['admins.view'],
        'vendor_review' => ['vendors.view', 'vendors.review'],
        'listings' => ['events.view', 'stays.view', 'transport.view', 'quick_taxi.view'],
        'mbanda' => ['quick_taxi.view'],
        'quick_taxi' => ['quick_taxi.view'],
        'bookings' => ['bookings.view', 'payments.view'],
        'support' => ['support.view'],
        'reports' => ['reports.view', 'platform_health.view'],
        'settings' => ['settings.view'],
        'logs' => ['audit.view'],
    ];
}

function adminCurrentAccount(): ?array {
    if (!isLoggedIn() || !in_array((string) ($_SESSION['user_role'] ?? ''), ADMIN_ROLES, true)) return null;
    try {
        $columns = 'id, name, role, is_approved';
        if (uthenga_column_exists('users', 'two_factor_enabled')) {
            $columns .= ', two_factor_enabled';
        }
        $account = dbQueryOne("SELECT {$columns} FROM users WHERE id = ?", [$_SESSION['user_id'] ?? '']);
        if ($account) $account['two_factor_enabled'] = !empty($account['two_factor_enabled']);
        if (!$account || empty($account['is_approved']) || !in_array((string) ($account['role'] ?? ''), ADMIN_ROLES, true)) return null;
        return $account;
    } catch (Throwable $error) {
        return null;
    }
}

function adminPermissionsForCurrentAdmin(): array {
    $account = adminCurrentAccount();
    if (!$account) return [];
    $registry = adminPermissionRegistry();
    if (($account['role'] ?? '') === ROLE_SUPER_ADMIN) return array_keys($registry);
    if (!uthenga_table_exists('admin_permissions')) return [];
    try {
        $row = dbQueryOne('SELECT permissions FROM admin_permissions WHERE user_id = ?', [$account['id']]);
        $stored = json_decode((string) ($row['permissions'] ?? '[]'), true);
        if (!is_array($stored)) return [];
        return adminNormalizePermissionList($stored);
    } catch (Throwable $error) {
        return [];
    }
}

function adminNormalizePermissionList(array $stored): array {
    $registry = adminPermissionRegistry();
    $granted = [];
    foreach ($stored as $permission) {
        $permission = strtolower(trim((string) $permission));
        if (isset($registry[$permission])) $granted[] = $permission;
        foreach (adminLegacyPermissionMap()[$permission] ?? [] as $mapped) $granted[] = $mapped;
    }
    return array_values(array_unique(array_filter($granted, static fn($key) => isset($registry[$key]))));
}

function adminHasPermission(string $permission): bool {
    $permission = strtolower(trim($permission));
    if (!isset(adminPermissionRegistry()[$permission])) return false;
    return in_array($permission, adminPermissionsForCurrentAdmin(), true);
}

function adminLogAuthorizationDenied(string $permission): void {
    try {
        dbExecute('INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)', [
            $_SESSION['user_id'] ?? null,
            $_SESSION['user_name'] ?? 'Unknown',
            $_SESSION['user_role'] ?? 'Unknown',
            'Admin Access Denied',
            'permission=' . preg_replace('/[^a-z0-9._-]/', '', strtolower($permission)),
        ]);
    } catch (Throwable $error) { /* Best-effort audit logging. */ }
}

function adminDenyPermission(string $permission): void {
    adminLogAuthorizationDenied($permission);
    if (isApiRequest()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'You are not authorized to perform this action.']);
        exit;
    }
    sendFriendlyError('You are not authorized to access this area.', 403);
}

function requireAdminPermission(string $permission): void {
    requireAdmin();
    if (!adminHasPermission($permission)) adminDenyPermission($permission);
}

function requireAnyAdminPermission(array $permissions): void {
    requireAdmin();
    foreach ($permissions as $permission) if (adminHasPermission((string) $permission)) return;
    adminDenyPermission((string) ($permissions[0] ?? 'unknown'));
}

function requireAllAdminPermissions(array $permissions): void {
    requireAdmin();
    foreach ($permissions as $permission) if (!adminHasPermission((string) $permission)) adminDenyPermission((string) $permission);
}

function requireSuperAdmin(): void {
    requireAdmin();
    $account = adminCurrentAccount();
    if (!$account || ($account['role'] ?? '') !== ROLE_SUPER_ADMIN) adminDenyPermission('admins.manage');
}

function adminPagePermission(?string $path = null): ?string {
    $path = str_replace('\\', '/', $path ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
    foreach ([
        'dashboard.php' => 'overview.view', 'users.php' => 'admins.view', 'vendors.php' => 'vendors.view',
        'events.php' => 'events.view', 'stays.php' => 'stays.view', 'transport.php' => 'transport.view',
        'shop.php' => 'shop.view', 'shop-product.php' => 'shop.view', 'shop-order.php' => 'shop.view',
        'bookings.php' => 'bookings.view', 'payments.php' => 'payments.view', 'transactions.php' => 'payments.view',
        'reconciliation.php' => 'payments.view', 'settlements.php' => 'settlements.review',
        'marketing.php' => 'marketing.view', 'popup_manager.php' => 'marketing.view', 'announcements.php' => 'marketing.view', 'blog.php' => 'marketing.view', 'advertisements.php' => 'marketing.view',
        'support.php' => 'support.view', 'notifications.php' => 'support.view', 'reports.php' => 'reports.view', 'analytics.php' => 'reports.view', 'event_report.php' => 'reports.view', 'event-organizer-analytics.php' => 'reports.view', 'transaction-stats.php' => 'reports.view',
        'security.php' => 'security.view', 'audit-logs.php' => 'audit.view', 'system-monitor.php' => 'platform_health.view',
        'settings.php' => 'settings.view', 'gate_session.php' => 'events.view',
    ] as $file => $permission) if (str_ends_with($path, '/' . $file) || str_ends_with($path, $file)) return $permission;
    return null;
}

function requireAdminPagePermission(?string $path = null): void {
    $permission = adminPagePermission($path);
    if ($permission !== null) requireAdminPermission($permission); else requireAdmin();
}

function adminRecentReauthenticationIsValid(string $category, int $seconds = 600): bool {
    return !empty($_SESSION['admin_reauth'][$category]) && (int) $_SESSION['admin_reauth'][$category] >= time() - $seconds;
}

function requireRecentAdminReauthentication(string $category): void {
    if (adminRecentReauthenticationIsValid($category)) return;
    if (isApiRequest()) adminDenyPermission('security.manage');
    $returnTo = uthenga_safe_redirect_url((string) ($_SERVER['HTTP_REFERER'] ?? $_SERVER['REQUEST_URI'] ?? ''), BASE_URL . 'admin/dashboard.php');
    redirect(BASE_URL . 'admin/reauthenticate.php?category=' . rawurlencode($category) . '&return=' . rawurlencode($returnTo));
}

function requireVendor(): void {
    requireLogin(VENDOR_ROLES);

    $vendor = null;
    try {
        $vendor = dbQueryOne('SELECT status FROM vendors WHERE user_id = ?', [$_SESSION['user_id']]);
    } catch (Throwable $e) { /* table absent */ }

    $profile = null;
    try {
        $profile = dbQueryOne('SELECT approval_status FROM vendor_profiles WHERE vendor_id = ?', [$_SESSION['user_id']]);
    } catch (Throwable $e) { /* table absent */ }

    $user = null;
    try {
        $user = dbQueryOne('SELECT is_approved FROM users WHERE id = ?', [$_SESSION['user_id']]);
    } catch (Throwable $e) { /* fallback */ }

    $vendorApproved = $vendor && strtolower((string) $vendor['status']) === 'approved';
    $legacyApproved = $profile && strtolower((string) $profile['approval_status']) === 'approved';
    $userApproved   = $user && !empty($user['is_approved']);

    if (!$vendorApproved && !$legacyApproved && !$userApproved) {
        redirect(BASE_URL . 'vendor/pending.php');
    }
}

function requireCustomer(): void {
    requireLogin([ROLE_CUSTOMER]);
}

function requireApprovedVendor(): void {
    requireVendor();
}

function guestWall(string $redirectAfter = ''): void {
    if (!isLoggedIn()) {
        $target = uthenga_safe_redirect_url($redirectAfter, '');
        if ($target === '') {
            $raw = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $target = preg_match('~^[/?A-Za-z0-9._~=-]+(?:\?[A-Za-z0-9._~=&%-]*)?$~', $raw) ? $raw : '';
        }
        redirect(BASE_URL . 'login.php?redirect=' . urlencode($target));
    }
}

function logAction(string $action, string $details): void {
    dbExecute(
        'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
        [
            $_SESSION['user_id'] ?? null,
            $_SESSION['user_name'] ?? 'System',
            $_SESSION['user_role'] ?? 'System',
            $action,
            $details
        ]
    );
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return dbQueryOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
}
