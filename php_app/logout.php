<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$isAdminLogout = isLoggedIn() && in_array((string) ($_SESSION['user_role'] ?? ''), ADMIN_ROLES, true);
if ($isAdminLogout && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'admin/login.php?logout=1');
}
if ($isAdminLogout && !validateCsrf()) {
    sendFriendlyError('Unable to complete that request. Please try again.', 403);
}

if ($isAdminLogout) {
    try {
        dbExecute(
            'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
            [
                $_SESSION['user_id'] ?? null,
                $_SESSION['user_name'] ?? 'Administrator',
                $_SESSION['user_role'] ?? 'Administrator',
                'Admin Logout',
                'result=success',
            ]
        );
    } catch (Throwable $error) {
        error_log('[Uthenga auth] Administrative logout audit write failed.');
    }
}

// Destroy session completely
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

redirect(BASE_URL . 'login.php?logout=1');
