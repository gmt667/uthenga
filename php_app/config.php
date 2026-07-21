<?php
/**
 * Uthenga Marketplace - System Configuration
 * Shared bootstrap for environment loading, constants, sessions, and helpers.
 */

if (!function_exists('uthenga_env')) {
    function uthenga_env(string $key, $default = null) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        return $default;
    }
}

if (!function_exists('uthenga_load_env_file')) {
    function uthenga_load_env_file(string $path): void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            $hashPos = strpos($value, ' #');
            if ($hashPos !== false) {
                $value = trim(substr($value, 0, $hashPos));
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

if (!function_exists('uthenga_apply_config_array')) {
    function uthenga_apply_config_array(array $config): void {
        foreach ($config as $name => $value) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $value = is_scalar($value) || $value === null ? (string) $value : '';
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

if (!function_exists('uthenga_load_php_config_file')) {
    function uthenga_load_php_config_file(string $path): void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $config = include $path;
        if (is_array($config)) {
            uthenga_apply_config_array($config);
        }
    }
}

if (!function_exists('uthenga_is_https')) {
    function uthenga_is_https(): bool {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwarded === 'https';
    }
}

if (!function_exists('uthenga_normalize_base_url')) {
    function uthenga_normalize_base_url(string $baseUrl): string {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return '';
        }
        return rtrim($baseUrl, '/') . '/';
    }
}

if (!function_exists('uthenga_theme_preference')) {
    function uthenga_theme_preference(): string {
        $candidates = [
            $_COOKIE['uthenga-theme'] ?? null,
            $_SESSION['theme_preference'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $candidate = is_string($candidate) ? strtolower(trim($candidate)) : '';
            if (in_array($candidate, ['light', 'dark'], true)) {
                return $candidate;
            }
        }

        return 'light';
    }
}

uthenga_load_env_file(dirname(__DIR__) . '/.env');
uthenga_load_env_file(__DIR__ . '/.env');
uthenga_load_php_config_file(dirname(__DIR__) . '/config.local.php');
uthenga_load_php_config_file(__DIR__ . '/config.local.php');
require_once __DIR__ . '/includes/public_icons.php';

$appEnv = uthenga_env('UTHENGA_ENV', uthenga_env('APP_ENV', ''));
if ($appEnv === '') {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $appEnv = ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) ? 'development' : 'production';
}
define('APP_ENV', in_array($appEnv, ['development', 'production'], true) ? $appEnv : 'development');

// Application
define('APP_NAME', 'Uthenga');
define('APP_TAGLINE', 'Malawi\'s Premier Marketplace - Events, Stays, Tours & Transport');
define('APP_VERSION', '1.0.0');
define('APP_CURRENCY', 'MWK');
define('APP_CURRENCY_SYMBOL', 'MK');
define('APP_LOCALE', 'en-MW');

// URL
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = uthenga_is_https() ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $appRoot = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $basePath = '/';

    if ($docRoot !== '') {
        $docRoot = rtrim($docRoot, '/');
        if (strpos($appRoot, $docRoot) === 0) {
            $relative = substr($appRoot, strlen($docRoot));
            $basePath = '/' . trim($relative, '/') . '/';
        }
    }

    if ($basePath === '/') {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = dirname($scriptName);
        if (preg_match('~/((?:uthenga|php_app))(?:/|$)~', $dir, $matches, PREG_OFFSET_CAPTURE)) {
            $matchPos = $matches[0][1];
            $matchLen = strlen($matches[0][0]);
            $basePath = substr($dir, 0, $matchPos + $matchLen);
            if (substr($basePath, -1) !== '/') {
                $basePath .= '/';
            }
        } elseif ($dir !== '/' && $dir !== '\\') {
            $basePath = rtrim($dir, '/') . '/';
        }
    }

    define('BASE_URL', uthenga_normalize_base_url(uthenga_env('UTHENGA_BASE_URL', $protocol . $host . $basePath)));
} else {
    define('BASE_URL', uthenga_normalize_base_url(uthenga_env('UTHENGA_BASE_URL', 'http://localhost:8080/uthenga/')));
}

// Database
define('DB_HOST',    uthenga_env('DB_HOST',   uthenga_env('UTHENGA_DB_HOST',   'localhost')));
define('DB_PORT',    uthenga_env('DB_PORT',   uthenga_env('UTHENGA_DB_PORT',   '3306')));
define('DB_NAME',    uthenga_env('DB_NAME',   uthenga_env('UTHENGA_DB_NAME',   'uthenga_db')));
define('DB_USER',    uthenga_env('DB_USER',   uthenga_env('UTHENGA_DB_USER',   'root')));
define('DB_PASS',    uthenga_env('DB_PASS',   uthenga_env('UTHENGA_DB_PASS',   '')));
define('DB_SOCKET',  uthenga_env('DB_SOCKET', uthenga_env('UTHENGA_DB_SOCKET', '')));
define('DB_CHARSET', 'utf8mb4');

// Mail and password reset
define('SUPPORT_EMAIL',     uthenga_env('UTHENGA_SUPPORT_EMAIL', 'support@uthenga.co'));
define('MAIL_FROM_EMAIL',   uthenga_env('UTHENGA_MAIL_FROM_EMAIL', SUPPORT_EMAIL));
define('MAIL_FROM_NAME',    uthenga_env('UTHENGA_MAIL_FROM_NAME', APP_NAME));
define('SUPPORT_PHONE',     uthenga_env('UTHENGA_SUPPORT_PHONE', '+265 (0) 888 123 456'));
define('SUPPORT_PHONE_ALT', uthenga_env('UTHENGA_SUPPORT_PHONE_ALT', '+265 (0) 1 832 940'));
define('PASSWORD_RESET_TTL_MINUTES', max(15, (int) uthenga_env('UTHENGA_PASSWORD_RESET_TTL_MINUTES', 60)));
define('SUPPORT_CONTACT', [
    'email' => SUPPORT_EMAIL,
    'phone' => SUPPORT_PHONE,
    'phone_alt' => SUPPORT_PHONE_ALT,
]);

// PayChangu integration defaults
define('PAYCHANGU_API_BASE_URL', uthenga_env('PAYCHANGU_API_BASE_URL', 'https://api.paychangu.com'));
define('PAYCHANGU_PUBLIC_KEY', uthenga_env('PAYCHANGU_PUBLIC_KEY', uthenga_env('PAYCHANGU_KEY', '')));
define('PAYCHANGU_SECRET_KEY', uthenga_env('PAYCHANGU_SECRET_KEY', ''));
define('PAYCHANGU_INIT_PATH', uthenga_env('PAYCHANGU_INIT_PATH', '/api/v1/checkout'));
define('PAYCHANGU_RETURN_URL', uthenga_env('PAYCHANGU_RETURN_URL', BASE_URL . 'payments/paychangu-callback.php'));
define('PAYCHANGU_CALLBACK_URL', uthenga_env('PAYCHANGU_CALLBACK_URL', BASE_URL . 'payments/paychangu-callback.php'));
define('PAYCHANGU_WEBHOOK_SIGNATURE_HEADER', 'HTTP_X_PAYCHANGU_SIGNATURE');

// Google OAuth 2.0
define('GOOGLE_CLIENT_ID', uthenga_env('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', uthenga_env('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', BASE_URL . 'auth/google_callback.php');

// Facebook OAuth 2.0
define('FACEBOOK_APP_ID', uthenga_env('FACEBOOK_APP_ID', ''));
define('FACEBOOK_APP_SECRET', uthenga_env('FACEBOOK_APP_SECRET', ''));
define('FACEBOOK_REDIRECT_URI', BASE_URL . 'auth/facebook_callback.php');

// Microsoft OAuth 2.0
define('MICROSOFT_CLIENT_ID', uthenga_env('MICROSOFT_CLIENT_ID', ''));
define('MICROSOFT_CLIENT_SECRET', uthenga_env('MICROSOFT_CLIENT_SECRET', ''));
define('MICROSOFT_REDIRECT_URI', BASE_URL . 'auth/microsoft_callback.php');

// Session
define('SESSION_NAME', 'uthenga_sess');
define('SESSION_LIFETIME', 7200);

// Business rules
define('COMMISSION_RATE', 10);
define('MIN_PASSWORD_LEN', 8);
define('ITEMS_PER_PAGE', 12);

// Security
define('BCRYPT_COST', 12);

// Roles
define('ROLE_SUPER_ADMIN', 'Super Administrator');
define('ROLE_ADMIN', 'Administrator');
define('ROLE_VENDOR', 'Vendor');
define('ROLE_EVENT_ORG', 'Event Organizer');
define('ROLE_HOTEL_MGR', 'Hotel/Lodge Manager');
define('ROLE_TOUR_OP', 'Tour Operator');
define('ROLE_TRANSPORT', 'Transport Provider');
define('ROLE_CUSTOMER', 'Customer');

define('ADMIN_ROLES', [ROLE_SUPER_ADMIN, ROLE_ADMIN]);
define('VENDOR_ROLES', [ROLE_VENDOR, ROLE_EVENT_ORG, ROLE_HOTEL_MGR, ROLE_TOUR_OP, ROLE_TRANSPORT]);
define('ALL_ROLES', array_merge(ADMIN_ROLES, VENDOR_ROLES, [ROLE_CUSTOMER]));

// Error reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Session initialization
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $sessionDir = __DIR__ . '/cache/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0755, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => uthenga_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function isApiRequest(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return (strpos($uri, '/api/') !== false)
        || (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'request_api.php')
        || (stripos($accept, 'application/json') !== false);
}

function sendFriendlyError(string $message = 'A system error occurred. Please try again later.', int $status = 500): void {
    if (!headers_sent()) {
        http_response_code($status);
    }

    if (isApiRequest()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    echo '<div style="padding:2rem;font-family:system-ui,sans-serif;max-width:720px;margin:2rem auto;">
            <h2 style="margin:0 0 0.75rem;">Service temporarily unavailable</h2>
            <p style="margin:0;">' . htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>
          </div>';
    exit;
}

if (APP_ENV !== 'development') {
    set_exception_handler(function (Throwable $e): void {
        error_log('[Uthenga exception] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        sendFriendlyError();
    });
}

if (!function_exists('uthenga_send_mail')) {
    function uthenga_send_mail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
        $fromEmail = SUPPORT_EMAIL !== '' ? SUPPORT_EMAIL : MAIL_FROM_EMAIL;
        if ($fromEmail === '') {
            error_log('[Uthenga mail] Missing from address for message to ' . $to);
            return false;
        }

        $fromName = MAIL_FROM_NAME !== '' ? MAIL_FROM_NAME : APP_NAME;
        $headers = [
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];

        $body = $htmlBody;
        if ($textBody !== '') {
            $body .= "\n\n" . $textBody;
        }

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$sent) {
            error_log('[Uthenga mail] Failed to send "' . $subject . '" to ' . $to);
        }

        return $sent;
    }
}

function getSetting(string $key, $default = null) {
    try {
        if (function_exists('getDB')) {
            foreach ([
                "SELECT setting_value AS val FROM system_settings WHERE setting_key = ?",
                "SELECT `value` AS val FROM settings WHERE `key` = ?",
            ] as $sql) {
                try {
                    $stmt = getDB()->prepare($sql);
                    $stmt->execute([$key]);
                    $row = $stmt->fetch();
                    if ($row !== false && array_key_exists('val', $row)) {
                        return $row['val'];
                    }
                } catch (Exception $inner) {
                    continue;
                }
            }
        }
    } catch (Exception $e) {
        // Fallback
    }

    return $default;
}

function setSetting($key, $value, $updatedBy = null) {
    try {
        if (function_exists('getDB')) {
            $valueType = 'string';
            if (is_bool($value)) {
                $valueType = 'boolean';
                $value = $value ? '1' : '0';
            } elseif (is_int($value) || is_float($value)) {
                $valueType = 'number';
                $value = (string) $value;
            } elseif (is_array($value) || is_object($value)) {
                $valueType = 'json';
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            } else {
                $value = (string) $value;
            }

            try {
                $stmt = getDB()->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, value_type, updated_by)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
                ");
                return $stmt->execute([$key, (string) $value, $valueType, $updatedBy]);
            } catch (Exception $inner) {
                $stmt = getDB()->prepare("
                    INSERT INTO settings (`key`, `value`)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
                ");
                return $stmt->execute([$key, (string) $value]);
            }
        }
    } catch (Exception $e) {
        // Fallback
    }

    return false;
}

function formatMWK(float $amount): string {
    return 'MK ' . number_format($amount, 0, '.', ',');
}

 function e($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    $roles = (array) $roles;
    return in_array($_SESSION['user_role'], $roles, true);
}

function currentRole(): string {
    return $_SESSION['user_role'] ?? '';
}

function validateCsrf(): bool {
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function generateId(string $prefix = ''): string {
    return strtoupper($prefix) . '-' . strtoupper(bin2hex(random_bytes(4)));
}

function uthenga_safe_redirect_url(string $target, string $fallback = ''): string {
    $target = trim($target);
    if ($target === '') {
        return $fallback;
    }

    if (preg_match('~^https?://~i', $target)) {
        if (strpos($target, BASE_URL) === 0) {
            return $target;
        }
        return $fallback;
    }

    // Root-relative paths (leading '/') are how $_SERVER['REQUEST_URI']
    // values arrive, and REQUEST_URI already includes the app's base path
    // (e.g. '/uthenga/php_app/admin/dashboard.php'). Prepending BASE_URL
    // here would duplicate that base path, so we only add protocol+host.
    if ($target[0] === '/') {
        static $siteRoot = null;
        if ($siteRoot === null) {
            $parts = parse_url(BASE_URL);
            $siteRoot = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '')
                . (isset($parts['port']) ? ':' . $parts['port'] : '');
        }
        return $siteRoot . $target;
    }

    // Otherwise this is an app-relative path (e.g. 'admin/dashboard.php')
    // and should be resolved against BASE_URL as before.
    $target = ltrim($target, '/');
    if ($target === '') {
        return $fallback;
    }

    return BASE_URL . $target;
}

require_once __DIR__ . '/db.php';

if (!function_exists('uthenga_table_exists')) {
    function uthenga_table_exists(string $table): bool {
        static $cache = [];
        $table = trim($table);
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $row = dbQueryOne(
                "SELECT COUNT(*) AS cnt
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?",
                [$table]
            );
            $cache[$table] = !empty($row) && (int)($row['cnt'] ?? 0) > 0;
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }
}

if (!function_exists('uthenga_column_exists')) {
    function uthenga_column_exists(string $table, string $column): bool {
        static $cache = [];
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') {
            return false;
        }
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $row = dbQueryOne(
                "SELECT COUNT(*) AS cnt
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
                [$table, $column]
            );
            $cache[$key] = !empty($row) && (int)($row['cnt'] ?? 0) > 0;
        } catch (Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }
}

if (!function_exists('uthenga_first_existing_table')) {
    function uthenga_first_existing_table(array $tables): string {
        foreach ($tables as $table) {
            if (uthenga_table_exists((string) $table)) {
                return (string) $table;
            }
        }
        return '';
    }
}

if (!function_exists('getDB')) {
    function getDB() {
        global $pdo;
        return $pdo;
    }
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/catalog.php';
