<?php
/**
 * Uthenga — Admin Account Seeder
 *
 * Run ONCE to create the predefined Super Administrator account in the DB.
 * Usage: /opt/lampp/bin/php admin/seed_admin.php
 *
 * DELETE THIS FILE after running it in production.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// ─── Credentials ──────────────────────────────────────────────────────────
define('SEED_ADMIN_NAME',     'Christopher Admin');
define('SEED_ADMIN_EMAIL',    'admin@uthenga.mw');
define('SEED_ADMIN_PASSWORD', 'Uthenga@2026!');
define('SEED_ADMIN_ROLE',     ROLE_SUPER_ADMIN);

$hash = password_hash(SEED_ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

try {
    $existing = dbQueryOne('SELECT id, role, is_approved FROM users WHERE email = ?', [SEED_ADMIN_EMAIL]);

    if ($existing) {
        // Update existing account — only write to real (non-generated) columns
        dbExecute(
            'UPDATE users
             SET name = ?, password_hash = ?, role = ?,
                 is_approved = 1, must_change_pw = 0, account_status = ?
             WHERE id = ?',
            [SEED_ADMIN_NAME, $hash, SEED_ADMIN_ROLE, 'active', $existing['id']]
        );
        echo "✓ Admin account updated successfully.\n";
        echo "Account ID: {$existing['id']}\n";

    } else {
        // Insert fresh record — skip all STORED GENERATED columns
        $id = 'ua-' . bin2hex(random_bytes(6));
        dbExecute(
            'INSERT INTO users
               (id, name, email, password_hash, role, is_approved, must_change_pw, account_status, joined_date)
             VALUES
               (?, ?, ?, ?, ?, 1, 0, ?, CURDATE())',
            [$id, SEED_ADMIN_NAME, SEED_ADMIN_EMAIL, $hash, SEED_ADMIN_ROLE, 'active']
        );
        echo "✓ Admin account created successfully.\n";
        echo "Account ID: $id\n";
    }

    echo "\n";
    echo "╔══════════════════════════════════════════════╗\n";
    echo "║           UTHENGA ADMIN CREDENTIALS          ║\n";
    echo "╠══════════════════════════════════════════════╣\n";
    echo "║ Login URL : " . str_pad(BASE_URL . "admin/login.php", 33) . "║\n";
    echo "║ Email     : " . str_pad(SEED_ADMIN_EMAIL, 33) . "║\n";
    echo "║ Password  : " . str_pad(SEED_ADMIN_PASSWORD, 33) . "║\n";
    echo "║ Role      : " . str_pad(SEED_ADMIN_ROLE, 33) . "║\n";
    echo "╚══════════════════════════════════════════════╝\n\n";
    echo "⚠  Delete this file after seeding: " . basename(__FILE__) . "\n";

} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
