<?php

final class UthengaTieApi
{
    public static function input(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'application/json') !== false) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($decoded)) throw UthengaTieErrors::validation(['body' => 'A JSON object is required.']);
            return $decoded;
        }
        return $_POST;
    }

    public static function query(): array
    {
        return is_array($_GET) ? $_GET : [];
    }

    public static function requireAuthenticatedUser(): array
    {
        if (!isLoggedIn()) throw UthengaTieErrors::authentication();
        return ['id' => (string) $_SESSION['user_id'], 'role' => (string) ($_SESSION['user_role'] ?? '')];
    }

    public static function requireCsrf(): void
    {
        $token = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($token === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw UthengaTieErrors::authorization();
        }
    }

    public static function requireFeature(string $feature): void
    {
        if (!UthengaTieFeatureFlags::enabled($feature)) throw UthengaTieErrors::featureDisabled($feature);
    }

    /** Session-local limits are sufficient for Phase 5's explicit, one-shot location requests. */
    public static function requireRateLimit(string $bucket, int $limit, int $windowSeconds = 60, ?string $requestId = null): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        $now = time();
        $key = 'tie_rate_' . preg_replace('/[^a-z0-9_]/i', '_', $bucket);
        $state = $_SESSION[$key] ?? ['started_at' => $now, 'count' => 0];
        if (($now - (int) $state['started_at']) >= $windowSeconds) $state = ['started_at' => $now, 'count' => 0];
        $state['count']++;
        $_SESSION[$key] = $state;
        if ($state['count'] > $limit) {
            UthengaTieMetrics::record('rate_limit_events', 1, $requestId ?: UthengaTieObservability::requestId(), ['module' => 'api', 'feature' => 'location', 'status' => 'rate_limited']);
            throw UthengaTieErrors::rateLimited();
        }
    }

    public static function respond(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function handleError(Throwable $error, string $requestId): void
    {
        UthengaTieObservability::log('request.failed', $requestId, ['module' => 'api', 'error_type' => $error instanceof UthengaTieException ? $error->type() : 'internal_error']);
        UthengaTieMetrics::record('errors', 1, $requestId, ['module' => 'api', 'status' => 'error']);
        $payload = UthengaTieErrors::response($error, $requestId);
        $status = (int) ($payload['_http_status'] ?? 500);
        unset($payload['_http_status']);
        self::respond($payload, $status);
    }
}
