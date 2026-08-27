<?php

final class UthengaTieObservability
{
    private static array $tableAvailability = [];
    private const STRING_CONTEXT_LENGTHS = [
        'module' => 60, 'feature' => 60, 'status' => 60, 'provider' => 80,
        'model' => 120, 'error_type' => 80, 'permission_state' => 40,
        'platform' => 40, 'cache' => 40, 'quality' => 40,
        'ranking_version' => 40, 'validation_status' => 40,
        'conversation_memory' => 40, 'resource' => 60, 'action' => 60,
    ];
    private const NUMERIC_CONTEXT_KEYS = [
        'duration_ms', 'candidate_count', 'eligible_count', 'rejected_count',
        'radius_km', 'tool_count', 'retry_count', 'journey_count', 'outbox_count',
    ];
    public static function requestId(): string
    {
        $incoming = trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
        if ($incoming !== '' && preg_match('/^[A-Za-z0-9._-]{8,100}$/', $incoming)) return $incoming;
        return 'tie-' . bin2hex(random_bytes(10));
    }

    public static function log(string $event, string $requestId, array $context = []): void
    {
        // Do not pass raw prompts, contact data, booking data, or coordinates.
        $safe = self::sanitizeContext($context);
        error_log('[Uthenga TIE] ' . json_encode([
            'event' => self::safeString($event, 100) ?? 'unknown',
            'request_id' => self::safeString($requestId, 100) ?? 'unknown',
        ] + $safe));
        self::persistTrace($requestId, $safe);
    }

    /**
     * Reduce telemetry to an explicit scalar allow-list before it reaches either
     * the error log or the database. Arrays and objects are never serialized.
     */
    public static function sanitizeContext(array $context): array
    {
        $safe = [];
        foreach (self::STRING_CONTEXT_LENGTHS as $key => $length) {
            $value = self::safeString($context[$key] ?? null, $length);
            if ($value !== null) $safe[$key] = $value;
        }
        foreach (self::NUMERIC_CONTEXT_KEYS as $key) {
            if (isset($context[$key]) && is_numeric($context[$key])) {
                $safe[$key] = (float) $context[$key];
            }
        }
        return $safe;
    }

    /**
     * Best-effort trace persistence. Telemetry must never interrupt a customer
     * request, and the stored fields are deliberately metadata-only.
     */
    public static function persistTrace(string $requestId, array $context): void
    {
        global $pdo;
        if (!$pdo instanceof PDO || !self::tableAvailable($pdo, 'tie_request_traces')) return;

        $context = self::sanitizeContext($context);

        $duration = isset($context['duration_ms']) && is_numeric($context['duration_ms']) ? (float) $context['duration_ms'] : null;
        try {
            $statement = $pdo->prepare(
                'INSERT INTO tie_request_traces (request_id, module_name, feature_name, status_name, provider_name, model_name, duration_ms, error_type)
                 VALUES (:request_id, :module_name, :feature_name, :status_name, :provider_name, :model_name, :duration_ms, :error_type)
                 ON DUPLICATE KEY UPDATE
                    module_name = COALESCE(NULLIF(VALUES(module_name), \'\'), module_name),
                    feature_name = COALESCE(NULLIF(VALUES(feature_name), \'\'), feature_name),
                    status_name = COALESCE(NULLIF(VALUES(status_name), \'\'), status_name),
                    provider_name = COALESCE(NULLIF(VALUES(provider_name), \'\'), provider_name),
                    model_name = COALESCE(NULLIF(VALUES(model_name), \'\'), model_name),
                    duration_ms = COALESCE(VALUES(duration_ms), duration_ms),
                    error_type = COALESCE(NULLIF(VALUES(error_type), \'\'), error_type),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $statement->execute([
                ':request_id' => $requestId,
                ':module_name' => self::safeString($context['module'] ?? null, 60),
                ':feature_name' => self::safeString($context['feature'] ?? null, 60),
                ':status_name' => self::safeString($context['status'] ?? null, 60),
                ':provider_name' => self::safeString($context['provider'] ?? null, 80),
                ':model_name' => self::safeString($context['model'] ?? null, 120),
                ':duration_ms' => $duration,
                ':error_type' => self::safeString($context['error_type'] ?? null, 80),
            ]);
        } catch (Throwable $error) {
            // Never recurse through the logger here; observability is optional.
        }
    }

    public static function metricTableAvailable(): bool
    {
        global $pdo;
        return $pdo instanceof PDO && self::tableAvailable($pdo, 'tie_metric_events');
    }

    private static function tableAvailable(PDO $pdo, string $table): bool
    {
        if (array_key_exists($table, self::$tableAvailability)) return self::$tableAvailability[$table];
        if (!preg_match('/^[a-z0-9_]{1,64}$/i', $table)) return false;
        try {
            // Native MariaDB prepared statements reject `SHOW TABLES LIKE ?`.
            // information_schema supports bound values and avoids identifier
            // interpolation while working in both MariaDB and MySQL.
            $query = $pdo->prepare(
                'SELECT 1 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
            );
            $query->execute([$table]);
            return self::$tableAvailability[$table] = (bool) $query->fetchColumn();
        } catch (Throwable $error) {
            return self::$tableAvailability[$table] = false;
        }
    }

    private static function safeString(mixed $value, int $length): ?string
    {
        if (!is_scalar($value)) return null;
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, $length);
    }
}

/**
 * A provider-neutral metrics seam. Metrics are persisted only when migration
 * 026 is applied; error-log output remains available during rollout.
 */
final class UthengaTieMetrics
{
    public static function record(string $metric, float $value, string $requestId, array $dimensions = []): void
    {
        $allowed = ['requests', 'latency_ms', 'errors', 'provider_failures', 'geocoder_cache_hits', 'geocoder_cache_misses', 'geocoder_fallbacks', 'geocoder_rate_limited', 'nearby_search_latency_ms', 'nearby_candidates', 'nearby_eligible', 'nearby_validation_rejections', 'nearby_missing_coordinates', 'nearby_radius_km', 'vendor_location_quality', 'location_context_latency_ms', 'location_context_validation_failures', 'location_permission_denied', 'location_fallback_usage', 'rate_limit_events', 'nearby_successful_responses', 'recommendation_candidates', 'recommendation_excluded', 'recommendation_latency_ms', 'recommendation_successful_responses', 'ai_chat_latency_ms', 'ai_tool_calls', 'ai_provider_failures', 'ai_response_validation_failures', 'ai_chat_successful_responses', 'ai_input_tokens', 'ai_output_tokens', 'plan_create_latency_ms', 'plan_validation_latency_ms', 'plan_conflicts', 'booking_validation_latency_ms', 'booking_execution_latency_ms', 'booking_rollbacks', 'booking_idempotency_hits', 'journey_dashboard_reads', 'notification_outbox_enqueued', 'payment_intent_start_latency_ms'];
        if (!in_array($metric, $allowed, true)) return;
        $safe = UthengaTieObservability::sanitizeContext(
            array_intersect_key($dimensions, array_flip(['module', 'feature', 'provider', 'model', 'status', 'quality']))
        );
        error_log('[Uthenga TIE metric] ' . json_encode(['metric' => $metric, 'value' => $value, 'request_id' => $requestId] + $safe));
        global $pdo;
        if (!$pdo instanceof PDO || !UthengaTieObservability::metricTableAvailable()) return;
        try {
            $statement = $pdo->prepare(
                'INSERT INTO tie_metric_events (metric, value, request_id, module_name, feature_name, provider_name, model_name, status_name, quality_name)
                 VALUES (:metric, :value, :request_id, :module_name, :feature_name, :provider_name, :model_name, :status_name, :quality_name)'
            );
            $statement->execute([
                ':metric' => $metric,
                ':value' => $value,
                ':request_id' => $requestId,
                ':module_name' => self::dimension($safe, 'module', 60),
                ':feature_name' => self::dimension($safe, 'feature', 60),
                ':provider_name' => self::dimension($safe, 'provider', 80),
                ':model_name' => self::dimension($safe, 'model', 120),
                ':status_name' => self::dimension($safe, 'status', 60),
                ':quality_name' => self::dimension($safe, 'quality', 40),
            ]);
        } catch (Throwable $error) {
            // Telemetry persistence is intentionally non-blocking.
        }
        UthengaTieObservability::persistTrace($requestId, $safe + [
            'duration_ms' => str_ends_with($metric, '_latency_ms') ? $value : null,
        ]);
    }

    private static function dimension(array $dimensions, string $key, int $length): ?string
    {
        $value = $dimensions[$key] ?? null;
        if (!is_scalar($value)) return null;
        $value = trim((string) $value);
        return $value === '' ? null : substr($value, 0, $length);
    }
}
