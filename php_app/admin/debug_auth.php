<?php
/**
 * Uthenga — TEMPORARY Auth Diagnostic Script
 *
 * Visit: http://127.0.0.1/uthenga/php_app/admin/debug-auth.php
 * (adjust path to match your actual folder structure)
 *
 * DELETE THIS FILE once you're done debugging — it reveals internal
 * state and must never exist on a production/public server.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Uthenga Admin Auth Diagnostic ===\n\n";

echo "1) Is includes/auth.php present and loadable?\n";
$authPath = __DIR__ . '/../includes/auth.php';
if (!file_exists($authPath)) {
    echo "   ✕ NOT FOUND at: $authPath\n";
    echo "   → This is your problem: auth.php was never placed there, so admin/login.php\n";
    echo "     is still running old logic (or fatal-erroring on the require).\n";
    exit;
}
echo "   ✓ Found at: $authPath\n\n";
require_once $authPath;

echo "2) Are the embedded constants defined correctly?\n";
echo "   EMBEDDED_SUPER_ADMIN_EMAIL    = " . (defined('EMBEDDED_SUPER_ADMIN_EMAIL') ? var_export(EMBEDDED_SUPER_ADMIN_EMAIL, true) : 'NOT DEFINED') . "\n";
echo "   EMBEDDED_SUPER_ADMIN_PASSWORD = " . (defined('EMBEDDED_SUPER_ADMIN_PASSWORD') ? var_export(EMBEDDED_SUPER_ADMIN_PASSWORD, true) : 'NOT DEFINED') . "\n\n";

$testEmail    = 'admin@uthenga.com';
$testPassword = 'uthenga123admin';

echo "3) Does the database connect at all?\n";
try {
    $pdo = getDB();
    echo "   ✓ Connected. DB_NAME=" . DB_NAME . " DB_HOST=" . DB_HOST . "\n\n";
} catch (Throwable $e) {
    echo "   ✕ Connection failed: " . $e->getMessage() . "\n\n";
}

echo "4) Does a users row exist for '$testEmail'?\n";
try {
    $user = dbQueryOne('SELECT id, email, role, is_approved, must_change_pw, password_hash FROM users WHERE email = ?', [strtolower($testEmail)]);
    if (!$user) {
        echo "   ✕ NO ROW FOUND. The users table is empty (or the seed data was never inserted —\n";
        echo "     likely because an earlier CREATE TABLE statement further down setup.sql failed\n";
        echo "     and phpMyAdmin stopped before reaching the INSERT INTO users block).\n\n";
    } else {
        echo "   ✓ Row found: id={$user['id']} role={$user['role']} is_approved={$user['is_approved']} must_change_pw={$user['must_change_pw']}\n";
        echo "   password_hash = {$user['password_hash']}\n";
        $verified = password_verify($testPassword, $user['password_hash']);
        echo "   password_verify('$testPassword', hash) = " . ($verified ? 'TRUE ✓' : 'FALSE ✕') . "\n\n";
    }
} catch (Throwable $e) {
    echo "   ✕ Query threw an exception: " . $e->getMessage() . "\n\n";
}

echo "5) Does the embedded fallback match manually?\n";
$emailMatch = defined('EMBEDDED_SUPER_ADMIN_EMAIL') && strcasecmp($testEmail, EMBEDDED_SUPER_ADMIN_EMAIL) === 0;
$passMatch  = defined('EMBEDDED_SUPER_ADMIN_PASSWORD') && hash_equals(EMBEDDED_SUPER_ADMIN_PASSWORD, $testPassword);
echo "   email match = " . ($emailMatch ? 'TRUE ✓' : 'FALSE ✕') . "\n";
echo "   password match = " . ($passMatch ? 'TRUE ✓' : 'FALSE ✕') . "\n\n";

echo "6) Full authenticateAdmin() result:\n";
$result = authenticateAdmin($testEmail, $testPassword);
echo "   " . var_export($result, true) . "\n\n";

echo "=== End of diagnostic. Delete this file now. ===\n";