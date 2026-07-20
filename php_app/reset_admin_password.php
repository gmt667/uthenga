<?php
/**
 * ONE-TIME admin password reset script.
 *
 * Usage:
 *   1. Drop this file into php_app/ (same level as config.php).
 *   2. Visit: http://127.0.0.1/uthenga/reset_admin_password.php?token=reset-me-now
 *   3. Confirm the success message showing the new password.
 *   4. Log in at admin/login.php with that email/password.
 *   5. DELETE THIS FILE IMMEDIATELY AFTER USE — it is not safe to leave on
 *      any server, local or production, since anyone who finds the URL
 *      (with the token) can reset the admin password.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ── Safety guard: requires a token so this can't be triggered by accident ──
$REQUIRED_TOKEN = 'reset-me-now';
if (($_GET['token'] ?? '') !== $REQUIRED_TOKEN) {
    http_response_code(403);
    exit('Missing or incorrect token. Append ?token=' . $REQUIRED_TOKEN . ' to the URL.');
}

// ── Config: which account, and what to set the password to ──
$targetEmail = 'admin@uthenga.com';
$newPassword = 'Uthenga123admin';

if (!uthenga_db_is_available()) {
    exit('Database connection is not available. Check config.php / db.php.');
}

$user = dbQueryOne('SELECT id, email, role FROM users WHERE email = ?', [strtolower($targetEmail)]);

if (!$user) {
    exit("No user found with email: " . htmlspecialchars($targetEmail));
}

$newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$ok = dbExecute('UPDATE users SET password_hash = ?, must_change_pw = 0 WHERE id = ?', [$newHash, $user['id']]);

if ($ok) {
    echo "<h2>✅ Password reset successful</h2>";
    echo "<p><strong>User ID:</strong> " . htmlspecialchars($user['id']) . "</p>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
    echo "<p><strong>Role:</strong> " . htmlspecialchars($user['role']) . "</p>";
    echo "<p><strong>New password:</strong> " . htmlspecialchars($newPassword) . "</p>";
    echo "<p style='color:red;font-weight:bold;'>Delete this file (reset_admin_password.php) right now — do not leave it on the server.</p>";
} else {
    echo "<h2>❌ Update failed</h2><p>Check PHP error logs for details.</p>";
}