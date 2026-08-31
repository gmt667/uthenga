<?php
/**
 * Database-backed administrator authentication.
 *
 * Privileged account recovery must be performed through a future CLI-only,
 * expiring and audited procedure. Web login never creates, promotes,
 * reactivates or resets administrator accounts.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

const ADMIN_LOGIN_FAILURE_MESSAGE = 'Unable to sign in with those credentials.';

/**
 * Attempt to authenticate an Administrator or Super Administrator.
 *
 * @return array{success:bool, error?:string, user?:array, via?:string}
 */
function authenticateAdmin(string $email, string $password): array {
    $email = strtolower(trim($email));

    if ($email === '' || $password === '') {
        return ['success' => false, 'error' => ADMIN_LOGIN_FAILURE_MESSAGE];
    }

    try {
        $user = dbQueryOne('SELECT * FROM users WHERE email = ?', [$email]);

        if (!$user) {
            logUnknownAdminAuthAttempt('Admin Login Failed', 'reason=account_not_found');
            return ['success' => false, 'error' => ADMIN_LOGIN_FAILURE_MESSAGE];
        }

        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            logAdminAuthAttempt($user, 'Failed Admin Login', 'reason=invalid_credentials');
            return ['success' => false, 'error' => ADMIN_LOGIN_FAILURE_MESSAGE];
        }

        if (!in_array((string) ($user['role'] ?? ''), ADMIN_ROLES, true)) {
            logAdminAuthAttempt($user, 'Admin Access Denied', 'reason=non_admin_role');
            return ['success' => false, 'error' => ADMIN_LOGIN_FAILURE_MESSAGE];
        }

        if (empty($user['is_approved'])) {
            logAdminAuthAttempt($user, 'Admin Login Denied', 'reason=account_disabled');
            return ['success' => false, 'error' => ADMIN_LOGIN_FAILURE_MESSAGE];
        }

        logAdminAuthAttempt($user, 'Admin Login', 'result=success ip=' . adminAuthClientIp());
        return ['success' => true, 'user' => $user, 'via' => 'database'];
    } catch (Throwable $error) {
        error_log('[Uthenga auth] Database-backed admin authentication failed.');
        logUnknownAdminAuthAttempt('Admin Login Failed', 'reason=authentication_service_error');
        return ['success' => false, 'error' => ADMIN_LOGIN_FAILURE_MESSAGE];
    }
}

function startAdminSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['name'];
    $_SESSION['user_role']    = $user['role'];
    $_SESSION['user_email']   = $user['email'];
    $_SESSION['user_balance'] = $user['balance'] ?? 0;
}

function adminAuthClientIp(): string {
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/** Best-effort audit logging; authentication does not depend on audit storage. */
function logAdminAuthAttempt(array $user, string $action, string $details): void {
    try {
        dbExecute(
            'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
            [$user['id'] ?? null, $user['name'] ?? 'Unknown', $user['role'] ?? 'Unknown', $action, $details]
        );
    } catch (Throwable $error) {
        error_log('[Uthenga auth] Administrative authentication audit write failed.');
    }
}

function logUnknownAdminAuthAttempt(string $action, string $details): void {
    try {
        dbExecute(
            'INSERT INTO audit_logs (user_name, user_role, action, details) VALUES (?, ?, ?, ?)',
            ['Unknown', 'Guest', $action, $details . ' ip=' . adminAuthClientIp()]
        );
    } catch (Throwable $error) {
        error_log('[Uthenga auth] Administrative authentication audit write failed.');
    }
}
