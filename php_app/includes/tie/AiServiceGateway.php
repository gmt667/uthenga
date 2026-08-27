<?php
/** Internal capability boundary between PHP and the FastAPI AI service. */
final class UthengaTieAiServiceCapability
{
    private const ALLOWED_TOOLS = ['travel_context', 'recommendations', 'availability', 'trip_plan', 'location_context'];

    public static function issue(string $userId, array $tools): string
    {
        $secret = self::secret();
        $tools = self::tools($tools);
        $payload = ['user_id' => $userId, 'tools' => $tools, 'expires_at' => time() + 60, 'nonce' => bin2hex(random_bytes(12))];
        $encoded = self::encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $encoded . '.' . hash_hmac('sha256', $encoded, $secret);
    }

    public static function verify(string $capability, string $tool): array
    {
        $secret = self::secret();
        [$encoded, $signature] = array_pad(explode('.', $capability, 2), 2, '');
        if ($encoded === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $encoded, $secret), $signature)) throw UthengaTieErrors::authorization();
        $json = self::decode($encoded); $payload = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($payload) || !isset($payload['user_id'], $payload['expires_at'], $payload['tools']) || (int) $payload['expires_at'] < time()) throw UthengaTieErrors::authorization();
        $tools = self::tools((array) $payload['tools']);
        if (!in_array($tool, $tools, true)) throw UthengaTieErrors::authorization();
        return ['user_id' => (string) $payload['user_id'], 'tools' => $tools];
    }

    public static function tools(array $tools): array
    {
        $tools = array_values(array_unique(array_map(static fn($tool): string => strtolower(trim((string) $tool)), $tools)));
        if ($tools === [] || array_diff($tools, self::ALLOWED_TOOLS)) throw UthengaTieErrors::validation(['tools' => 'Only approved, read-only AI tools may be requested.']);
        return $tools;
    }

    private static function secret(): string
    {
        $secret = UthengaTieConfig::string('TIE_AI_CAPABILITY_SECRET', '');
        if (strlen($secret) < 32) throw UthengaTieErrors::providerUnavailable('ai_capability_configuration');
        return $secret;
    }
    private static function encode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
    private static function decode(string $value): ?string { $padding = strlen($value) % 4; return base64_decode(strtr($value . ($padding ? str_repeat('=', 4 - $padding) : ''), '-_', '+/'), true) ?: null; }
}
