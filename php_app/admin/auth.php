<?php
/**
 * Uthenga — Admin Authentication Module
 *
 * Centralizes admin/super-admin login logic used by admin/login.php.
 *
 * Includes an EMBEDDED super-admin credential fallback so the console
 * always remains accessible with:
 *   Email:    admin@uthenga.com
 *   Password: uthenga123admin
 * even if the `users` row for the super admin is missing, locked out,
 * or the database is temporarily inconsistent. This is a recovery/
 * bootstrap safety net, not a replacement for the database-backed
 * account — the DB user is still tried first.
 *
 * ⚠️ SECURITY NOTE: Change or remove the embedded credentials below
 * before deploying to a public/production server. Anyone who reads
 * this file (or guesses the values) can log in as Super Administrator.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// ─── Embedded super-admin fallback credentials ────────────────────────────
// Only used when no matching, approved user is found in the database.
define('EMBEDDED_SUPER_ADMIN_EMAIL', 'admin@uthenga.com');
define('EMBEDDED_SUPER_ADMIN_PASSWORD', 'uthenga123admin');

/**
 * Attempt to authenticate an admin (Administrator or Super Administrator).
 *
 * @param string $email
 * @param string $password
 * @return array{success:bool, error?:string, user?:array, via?:string}
 */
function authenticateAdmin(string $email, string $password): array {
    $email = trim($email);

    if ($email === '' || $password === '') {
        return ['success' => false, 'error' => 'Please enter your administrator email and password.'];
    }

    // 1) Try the database-backed account first.
    try {
        $user = dbQueryOne('SELECT * FROM users WHERE email = ?', [strtolower($email)]);

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!in_array($user['role'], ADMIN_ROLES, true)) {
                logAdminAuthAttempt($user, 'Admin Access Denied', 'Non-admin attempted to login to admin console');
                return ['success' => false, 'error' => 'Access denied. This login is reserved for administrators.'];
            }
            if (!$user['is_approved']) {
                return ['success' => false, 'error' => 'Your administrator account has been suspended.'];
            }

            logAdminAuthAttempt($user, 'Admin Login', 'Admin logged in successfully from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return ['success' => true, 'user' => $user, 'via' => 'database'];
        }
    } catch (Throwable $e) {
        // Database unavailable/inconsistent — fall through to the embedded check below.
        error_log('[Uthenga auth] DB lookup failed during admin login: ' . $e->getMessage());
    }

    // 2) Fall back to the embedded super-admin credentials.
    if (
        strcasecmp($email, EMBEDDED_SUPER_ADMIN_EMAIL) === 0 &&
        hash_equals(EMBEDDED_SUPER_ADMIN_PASSWORD, $password)
    ) {
        $embeddedHash = password_hash(EMBEDDED_SUPER_ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $embeddedUser = dbQueryOne('SELECT * FROM users WHERE email = ?', [strtolower(EMBEDDED_SUPER_ADMIN_EMAIL)]);

        if ($embeddedUser) {
            try {
                dbExecute(
                    'UPDATE users SET name = ?, role = ?, is_approved = 1, must_change_pw = 1, password_hash = ? WHERE id = ?',
                    ['Super Admin', ROLE_SUPER_ADMIN, $embeddedHash, $embeddedUser['id']]
                );
                $embeddedUser = dbQueryOne('SELECT * FROM users WHERE id = ?', [$embeddedUser['id']]);
            } catch (Throwable $e) {
                error_log('[Uthenga auth] Failed to refresh embedded super admin record: ' . $e->getMessage());
            }
        } else {
            $embeddedUser = [
                'id'             => 'u-super-admin',
                'name'           => 'Super Admin',
                'email'          => EMBEDDED_SUPER_ADMIN_EMAIL,
                'role'           => ROLE_SUPER_ADMIN,
                'balance'        => 0,
                'must_change_pw' => 1,
                'is_approved'    => 1,
                'password_hash'  => $embeddedHash,
            ];

            try {
                dbExecute(
                    'INSERT INTO users (id, name, email, password_hash, role, is_approved, must_change_pw, joined_date) VALUES (?, ?, ?, ?, ?, 1, 1, CURDATE())',
                    [$embeddedUser['id'], $embeddedUser['name'], $embeddedUser['email'], $embeddedHash, $embeddedUser['role']]
                );
            } catch (Throwable $e) {
                error_log('[Uthenga auth] Failed to create embedded super admin record: ' . $e->getMessage());
            }
        }

        logAdminAuthAttempt($embeddedUser, 'Admin Login (Embedded Fallback)', 'Super admin authenticated via embedded fallback credentials from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return ['success' => true, 'user' => $embeddedUser, 'via' => 'embedded'];
    }

    // 3) Invalid credentials.
    try {
        dbExecute('INSERT INTO audit_logs (user_name, user_role, action, details) VALUES (?, ?, ?, ?)',
            ['Unknown', 'Guest', 'Admin Login Failed', "Failed admin login attempt for email: $email"]);
    } catch (Throwable $e) {
        // Ignore logging failures — never block the response on this.
    }

    return ['success' => false, 'error' => 'Invalid email or password.'];
}

/**
 * Establish the admin session after a successful authenticateAdmin() call.
 */
function startAdminSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['name'];
    $_SESSION['user_role']    = $user['role'];
    $_SESSION['user_email']   = $user['email'];
    $_SESSION['user_balance'] = $user['balance'] ?? 0;
}

/**
 * Best-effort audit log write for admin auth attempts. Never throws.
 */
function logAdminAuthAttempt(array $user, string $action, string $details): void {
    try {
        dbExecute('INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
            [$user['id'], $user['name'], $user['role'], $action, $details]);
    } catch (Throwable $e) {
        // Ignore — logging should never block login.
    }
}
