<?php
/**
 * Travel Intelligence Engine configuration.
 *
 * Configuration deliberately reuses config.php's uthenga_env() mechanism so
 * TIE has no second secrets store or database configuration.
 */

final class UthengaTieConfig
{
    public static function string(string $key, string $default = ''): string
    {
        return trim((string) uthenga_env($key, $default));
    }

    public static function integer(string $key, int $default): int
    {
        $value = self::string($key, (string) $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function decimal(string $key, float $default): float
    {
        $value = self::string($key, (string) $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        $value = strtolower(self::string($key, $default ? 'true' : 'false'));
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'off', ''], true)) {
            return false;
        }
        return $default;
    }

    /** Comma-separated non-secret policy values. */
    public static function csv(string $key, array $default = []): array
    {
        $value = self::string($key, implode(',', $default));
        return array_values(array_unique(array_filter(array_map(static fn(string $item): string => strtolower(trim($item)), explode(',', $value)), static fn(string $item): bool => $item !== '')));
    }

    /** A safe subset for health responses and diagnostics; it contains no secrets. */
    public static function publicSnapshot(): array
    {
        return [
            'service' => self::string('TIE_SERVICE_NAME', 'travel-intelligence-engine'),
            'version' => self::string('TIE_API_VERSION', 'v1'),
            'enabled' => self::boolean('TIE_ENABLED'),
            'flags' => UthengaTieFeatureFlags::all(),
        ];
    }
}

final class UthengaTieFeatureFlags
{
    private const FLAGS = [
        'query' => 'TIE_QUERY_ENABLED',
        'availability' => 'TIE_AVAILABILITY_ENABLED',
        'context' => 'TIE_CONTEXT_ENABLED',
        'location' => 'TIE_LOCATION_ENABLED',
        'trip_planner' => 'TIE_TRIP_PLANNER_ENABLED',
        'recommendations' => 'TIE_RECOMMENDATIONS_ENABLED',
        'plans' => 'TIE_PLANS_ENABLED',
        'booking' => 'TIE_BOOKING_ENABLED',
        'payments' => 'TIE_PAYMENT_ENABLED',
        'ai' => 'TIE_AI_ENABLED',
        'conversation' => 'TIE_AI_ENABLED',
        'routing' => 'TIE_ROUTING_ENABLED',
        'llm' => 'TIE_LLM_ENABLED',
        'journey' => 'TIE_JOURNEY_ENABLED',
        'notifications' => 'TIE_NOTIFICATIONS_ENABLED',
        'quick_travel' => 'TIE_QUICK_TRAVEL_ENABLED',
        'vendor_profiles' => 'TIE_VENDOR_PROFILES_ENABLED',
        'accommodation_v2' => 'TIE_ACCOMMODATION_V2_ENABLED',
        'events_v2' => 'TIE_EVENTS_V2_ENABLED',
        'bus_operations' => 'TIE_BUS_OPERATIONS_ENABLED',
    ];

    public static function enabled(string $feature): bool
    {
        if (!UthengaTieConfig::boolean('TIE_ENABLED')) {
            return false;
        }
        if (!isset(self::FLAGS[$feature])) {
            return false;
        }
        return UthengaTieConfig::boolean(self::FLAGS[$feature]);
    }

    public static function all(): array
    {
        $flags = [];
        foreach (array_keys(self::FLAGS) as $feature) {
            $flags[$feature] = self::enabled($feature);
        }
        return $flags;
    }
}
