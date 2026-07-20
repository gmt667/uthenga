<?php
/**
 * Uthenga — Database Connection
 * Uses DB_* constants defined in config.php.
 * If included before config.php defines them, falls back to env vars.
 */

if (!isset($pdo)) {
    $dbHost    = defined('DB_HOST')    ? DB_HOST    : (getenv('DB_HOST')    ?: 'localhost');
    $dbPort    = defined('DB_PORT')    ? DB_PORT    : (getenv('DB_PORT')    ?: '3306');
    $dbName    = defined('DB_NAME')    ? DB_NAME    : (getenv('DB_NAME')    ?: 'uthenga_app');
    $dbUser    = defined('DB_USER')    ? DB_USER    : (getenv('DB_USER')    ?: 'uthenga_user');
    $dbPass    = defined('DB_PASS')    ? DB_PASS    : (getenv('DB_PASS')    ?: '');
    $dbSocket  = defined('DB_SOCKET')  ? DB_SOCKET  : (getenv('DB_SOCKET')  ?: '');
    $dbCharset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

    // Build DSN — prefer Unix socket if provided
    if ($dbSocket !== '') {
        $dsn = "mysql:unix_socket={$dbSocket};dbname={$dbName};charset={$dbCharset}";
    } else {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";
    }

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        $pdo = null;
        $GLOBALS['uthenga_db_available'] = false;
    }
} else {
    $GLOBALS['uthenga_db_available'] = $pdo instanceof PDO;
}

if (!function_exists('uthenga_db_is_available')) {
    function uthenga_db_is_available(): bool {
        global $pdo;
        return $pdo instanceof PDO;
    }
}

if (!class_exists('Uthenga_NullDbStatement')) {
    class Uthenga_NullDbStatement {
        public function execute($params = []): bool { return true; }
        public function fetch($mode = null) { return false; }
        public function fetchAll($mode = null) { return []; }
        public function fetchColumn($column_number = 0) { return false; }
        public function rowCount(): int { return 0; }
        public function bindValue($param, $value, $type = null): bool { return true; }
        public function bindParam($param, &$var, $type = null, $length = null, $driver_options = null): bool { return true; }
    }
}

if (!class_exists('Uthenga_NullDb')) {
    class Uthenga_NullDb {
        public function prepare(string $sql) { return new Uthenga_NullDbStatement(); }
        public function beginTransaction(): bool { return true; }
        public function commit(): bool { return true; }
        public function rollBack(): bool { return true; }
        public function inTransaction(): bool { return false; }
        public function lastInsertId($name = null) { return '0'; }
    }
}

if (!isset($pdo)) {
    $pdo = new Uthenga_NullDb();
}

// ── Convenience query helpers ──────────────────────────────────────────────────

if (!function_exists('dbQuery')) {
    function dbQuery(string $sql, array $params = []): array {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return [];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

if (!function_exists('dbQueryOne')) {
    function dbQueryOne(string $sql, array $params = []) {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return false;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
}

if (!function_exists('dbExecute')) {
    function dbExecute(string $sql, array $params = []): bool {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return true;
        }
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
}

if (!function_exists('dbExecuteAffected')) {
    function dbExecuteAffected(string $sql, array $params = []): int {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return 0;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}

if (!function_exists('dbCount')) {
    function dbCount(string $sql, array $params = []): int {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return 0;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('dbLastId')) {
    function dbLastId(): string {
        global $pdo;
        if (!($pdo instanceof PDO)) {
            return '0';
        }
        return $pdo->lastInsertId();
    }
}
