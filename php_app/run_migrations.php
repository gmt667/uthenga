<?php
/**
 * Uthenga Marketplace — Migration Runner
 * Runs all pending database migrations in sequence.
 * Access via: http://localhost/uthenga/php_app/run_migrations.php
 */

if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    exit('Forbidden: This script can only be run from localhost.');
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$isWeb = (php_sapi_name() !== 'cli');
$nl    = $isWeb ? '<br>' : "\n";

if ($isWeb) {
    echo "<!DOCTYPE html><html><head><title>Uthenga Migration Runner</title>";
    echo "<style>body{font-family:monospace;background:#111;color:#0f0;padding:20px;}
    .ok{color:#0f0;} .err{color:#f44;} .warn{color:#fa0;} .info{color:#09f;}
    h1{color:#fff;} hr{border-color:#333;}</style></head><body>";
    echo "<h1>🛠 Uthenga Migration Runner</h1><hr>";
}

function out(string $msg, string $type = 'info'): void {
    global $isWeb, $nl;
    if ($isWeb) {
        echo "<span class=\"{$type}\">{$msg}</span>{$nl}";
    } else {
        echo $msg . "\n";
    }
    flush();
}

// ── Attempt DB connection ──────────────────────────────────────────────────────
$appDb   = getenv('UTHENGA_DB_NAME') ?: getenv('DB_NAME') ?: 'uthenga_db';
$appUser = getenv('UTHENGA_DB_USER') ?: 'uthenga_user';
$appPass = getenv('UTHENGA_DB_PASS') ?: '';
$combos = [
    ['host' => 'localhost', 'user' => $appUser, 'pass' => $appPass],
    ['host' => 'localhost', 'user' => 'root',   'pass' => ''],
    ['host' => 'localhost', 'user' => 'root',   'pass' => 'root'],
    ['host' => '127.0.0.1', 'user' => $appUser, 'pass' => $appPass],
    ['host' => '127.0.0.1', 'user' => 'root',   'pass' => ''],
];

$conn      = null;
$connected = false;

foreach ($combos as $c) {
    try {
        $dsn  = "mysql:host={$c['host']};dbname={$appDb};charset=utf8mb4";
        $conn = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        out("✅ Connected: user={$c['user']} @ {$c['host']}", 'ok');
        $connected = true;
        break;
    } catch (PDOException $e) {
        // try next combo
    }
}

// If DB doesn't exist yet, try without dbname to create it
if (!$connected) {
    out("⚠ Could not connect to {$appDb}. Trying to create it...", 'warn');
    foreach ($combos as $c) {
        try {
            $dsn  = "mysql:host={$c['host']};charset=utf8mb4";
            $conn = new PDO($dsn, $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $conn->exec("CREATE DATABASE IF NOT EXISTS {$appDb} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->exec("USE {$appDb}");
            out("✅ Database created and selected: user={$c['user']} @ {$c['host']}", 'ok');
            $connected = true;
            break;
        } catch (PDOException $e) {
            // try next
        }
    }
}

if (!$connected || !$conn) {
    out("❌ FATAL: Could not connect to MySQL with any credential combo.", 'err');
    out("Please verify XAMPP MySQL is running and your credentials.", 'warn');
    if ($isWeb) echo "</body></html>";
    exit(1);
}

// ── Migration tracking table ───────────────────────────────────────────────────
$conn->exec("CREATE TABLE IF NOT EXISTS _migrations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename    VARCHAR(255) NOT NULL UNIQUE,
    applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

function hasRun(PDO $conn, string $filename): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM _migrations WHERE filename = ?");
    $stmt->execute([basename($filename)]);
    return $stmt->fetchColumn() > 0;
}

function markRun(PDO $conn, string $filename): void {
    $stmt = $conn->prepare("INSERT IGNORE INTO _migrations (filename) VALUES (?)");
    $stmt->execute([basename($filename)]);
}

// ── Migration file list (ordered) ──────────────────────────────────────────────
$base       = __DIR__ . '/database/migrations/';
$installSQL = __DIR__ . '/install/setup.sql';

// The install/setup.sql is treated as migration "000_setup"
$migrations = [
    '000_install_setup.sql'                     => $installSQL,  // Base schema (treated as first migration)
    '001_event_analytics.sql'                   => $base . '001_event_analytics.sql',
    '001_add_missing_tables.sql'                => $base . '001_add_missing_tables.sql',
    '002_add_social_accounts.sql'               => $base . '002_add_social_accounts.sql',
    '002_promotional_popups.sql'                => $base . '002_promotional_popups.sql',
    '003_gate_sessions.sql'                     => $base . '003_gate_sessions.sql',
    '004_admin_permissions_and_system_logs.sql' => $base . '004_admin_permissions_and_system_logs.sql',
    '005_core_schema_compatibility.sql'         => $base . '005_core_schema_compatibility.sql',
    '006_ticket_types_seats_inventory.sql'      => $base . '006_ticket_types_seats_inventory.sql',
    '007_ride_sharing_trip_planner_qr.sql'      => $base . '007_ride_sharing_trip_planner_qr.sql',
    '008_feature_enhancements.sql'              => $base . '008_feature_enhancements.sql',
    '009_marketing_security_phase.sql'          => $base . '009_marketing_security_phase.sql',
    '010_microsoft_oauth.sql'                   => $base . '010_microsoft_oauth.sql',
];

out("", 'info');
out("── Running Migrations ──", 'info');
$conn->exec("SET FOREIGN_KEY_CHECKS = 0");

foreach ($migrations as $name => $file) {
    if (!file_exists($file)) {
        out("⚠ File not found, skipping: {$name}", 'warn');
        continue;
    }

    if (hasRun($conn, $name)) {
        out("⏩ Already applied: {$name}", 'info');
        continue;
    }

    out("▶ Applying: {$name} ...", 'info');

    $sql        = file_get_contents($file);
    // Strip comment-only lines first to prevent chunks that start with comments from being filtered out
    $sql        = preg_replace('/^--.*$/m', '', $sql);
    $sql        = preg_replace('/^#.*$/m', '', $sql);
    $statements = array_filter(
        array_map('trim', preg_split('/;\s*\n/m', $sql)),
        fn($s) => $s !== ''
    );

    $errors = 0;
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;

        // For setup.sql skip CREATE TABLE (tables may already exist) — use CREATE TABLE IF NOT EXISTS
        // We wrap each statement so errors don't abort the whole migration
        try {
            $sth = $conn->prepare($stmt);
            $sth->execute();
            $sth->closeCursor();
        } catch (PDOException $e) {
            $code = $e->getCode();
            $msg  = $e->getMessage();

            // Ignore "already exists" / "duplicate column" / "duplicate key" errors gracefully
            if (in_array($code, ['42S01', '42S21', '42000', '23000'])
                || str_contains($msg, 'Duplicate column')
                || str_contains($msg, 'already exists')
                || str_contains($msg, 'Duplicate entry')
                || str_contains($msg, 'Can\'t DROP')
                || str_contains($msg, 'check that column')
            ) {
                // Benign — column/table already exists
            } else {
                out("  ⚠ Warning [{$code}]: " . htmlspecialchars(substr($msg, 0, 200)), 'warn');
                $errors++;
            }
        }
    }

    if ($errors === 0) {
        markRun($conn, $name);
        out("  ✅ Applied successfully: {$name}", 'ok');
    } else {
        out("  ⚠ Applied with {$errors} warning(s): {$name}", 'warn');
        out("  ⚠ Not marking migration as applied so it can be retried.", 'warn');
    }
}

$conn->exec("SET FOREIGN_KEY_CHECKS = 1");

// ── Verify critical tables ─────────────────────────────────────────────────────
out("", 'info');
out("── Table Verification ──", 'info');

$required = [
    'users', 'listings', 'bookings', 'transactions',
    'support_tickets', 'ticket_responses', 'blog_posts',
    'audit_logs', 'reviews', 'wishlist', 'notifications',
    'settings', 'vendor_profiles', 'gate_sessions', 'gate_scans',
    'ticket_types', 'seat_classes', 'event_analytics', 'promotional_popups',
    'coupons',
    // Phase 9-13 tables
    'two_factor_auth', 'device_sessions', 'login_alerts',
    'loyalty_transactions', 'gift_vouchers',
    'newsletter_subscribers', 'newsletter_campaigns',
    'referral_codes', 'referral_uses', 'fraud_alerts',
];

$stmt   = $conn->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$tableSet = array_flip(array_map('strtolower', $tables));

foreach ($required as $t) {
    if (isset($tableSet[$t])) {
        out("  ✅ {$t}", 'ok');
    } else {
        out("  ❌ MISSING: {$t}", 'err');
    }
}

// ── Count seed data ────────────────────────────────────────────────────────────
out("", 'info');
out("── Seed Data Check ──", 'info');

$checks = [
    'users'    => 'SELECT COUNT(*) FROM users',
    'listings' => 'SELECT COUNT(*) FROM listings',
    'settings' => 'SELECT COUNT(*) FROM settings',
];

foreach ($checks as $label => $query) {
    try {
        $count = $conn->query($query)->fetchColumn();
        $flag  = $count > 0 ? 'ok' : 'warn';
        $icon  = $count > 0 ? '✅' : '⚠';
        out("  {$icon} {$label}: {$count} rows", $flag);
    } catch (PDOException $e) {
        out("  ❌ {$label}: error — " . $e->getMessage(), 'err');
    }
}

out("", 'info');
out("══ Done! ══", 'ok');

if ($isWeb) {
    echo "<hr><p class='info'>Visit <a href='/uthenga/php_app/' style='color:#09f'>/uthenga/php_app/</a> to use the application.</p>";
    echo "</body></html>";
}
