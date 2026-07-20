<?php
/**
 * Database Initializer & Migration runner
 */
$host = getenv('MYSQL_HOST') ?: 'localhost';
$root_user = getenv('MYSQL_ROOT_USER') ?: 'root';
$root_pass = getenv('MYSQL_ROOT_PASS') ?: '';
$app_user = getenv('UTHENGA_DB_USER') ?: 'uthenga_user';
$app_pass = getenv('UTHENGA_DB_PASS') ?: '';

try {
    $dsn = "mysql:host=$host;charset=utf8mb4";
    $conn = new PDO($dsn, $root_user, $root_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Connected to MySQL as root.\n";
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage() . "\n");
}

try {
    // 1. Create database
    $conn->exec("CREATE DATABASE IF NOT EXISTS uthenga_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database uthenga_app checked/created.\n";

    // 2. Create users and grant privileges
    $conn->exec("CREATE USER IF NOT EXISTS '{$app_user}'@'localhost' IDENTIFIED BY '{$app_pass}'");
    $conn->exec("GRANT ALL PRIVILEGES ON uthenga_app.* TO '{$app_user}'@'localhost'");
    
    // Also support default config pass
    try {
        $conn->exec("ALTER USER '{$app_user}'@'localhost' IDENTIFIED BY '{$app_pass}'");
    } catch (Exception $ex) {}
    
    $conn->exec("FLUSH PRIVILEGES");
    echo "Database user {$app_user} checked/created/updated with privileges.\n";
} catch (PDOException $e) {
    echo "Warning during user setup: " . $e->getMessage() . "\n";
}

// Reconnect to uthenga_app database
try {
    $conn->exec("USE uthenga_app");
} catch (PDOException $e) {
    die("Could not select uthenga_app database: " . $e->getMessage() . "\n");
}

// Helper to execute large SQL script with query splitting
function executeSqlFile($conn, $filePath) {
    if (!file_exists($filePath)) {
        echo "File not found: $filePath\n";
        return false;
    }
    echo "Importing $filePath...\n";
    $sql = file_get_contents($filePath);
    
    // Simple but robust SQL parser for XAMPP migrations: split by semicolon, ignore comments
    // Remove comments
    $sql = preg_replace('/--.*\n/', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    
    $queries = explode(';', $sql);
    $count = 0;
    foreach ($queries as $query) {
        $query = trim($query);
        if ($query !== '') {
            try {
                $conn->exec($query);
                $count++;
            } catch (PDOException $e) {
                // If it is 'table already exists' or similar warning we can continue
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "Query Error in $filePath: " . $e->getMessage() . "\nQuery: $query\n";
                }
            }
        }
    }
    echo "Executed $count queries from " . basename($filePath) . ".\n";
    return true;
}

// 3. Import the base installer schema and full migration chain
executeSqlFile($conn, __DIR__ . '/install/setup.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/001_event_analytics.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/001_add_missing_tables.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/002_add_social_accounts.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/002_promotional_popups.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/003_gate_sessions.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/004_admin_permissions_and_system_logs.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/005_core_schema_compatibility.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/006_ticket_types_seats_inventory.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/007_ride_sharing_trip_planner_qr.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/008_feature_enhancements.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/009_marketing_security_phase.sql');
executeSqlFile($conn, __DIR__ . '/database/migrations/002_support_and_stats.sql');

echo "Database initialization complete.\n";
