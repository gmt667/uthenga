<?php
/**
 * Uthenga - Authentication & Authorization Guard
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

function redirectByRole(string $role): void {
    if ($role === ROLE_SUPER_ADMIN) {
        redirect(BASE_URL . 'admin/super-dashboard.php');
    }
    if ($role === ROLE_ADMIN) {
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

    if ($isSuperOnly || preg_match('~/(?:admin)(?:/|$)~i', $requestUri)) {
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
    if (isset($_SESSION['device_session_token'])) {
        $validSession = dbQueryOne(
            "SELECT id FROM device_sessions WHERE user_id = ? AND session_token = ?",
            [$_SESSION['user_id'], $_SESSION['device_session_token']]
        );
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
