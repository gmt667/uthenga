<?php
/**
 * Trip Planning Assistant — Preferences. Notification channel toggles
 * (push/email/SMS) already exist and are already wired end-to-end —
 * users.push_notify/email_notify/sms_notify, read by profile.php's
 * "Update Preferences" form and by UthengaTieNotificationService's own
 * delivery gate (Notifications.php). This service reads and writes those
 * SAME columns rather than duplicating them. Everything else here — travel
 * style, interests, accommodation/transport/food/accessibility, trip
 * defaults, AI personalization toggles — is genuinely new and has nowhere
 * else to live, so it's stored as one JSON blob per customer, the same
 * "flexible structured settings" pattern trip_itineraries.itinerary_data
 * already uses.
 *
 * Honesty note: UthengaTieRecommendationService::rank() does not currently
 * consume any of these preference fields in its scoring — they are
 * genuinely persisted and shown back to the customer, but do not yet shape
 * AI recommendations. The one real behavioural connection this service
 * enables is prefilling a new Trip Planner intake from trip_defaults.
 */
final class UthengaTieCustomerPreferencesContracts
{
    private const ALLOWED_KEYS = [
        'pace', 'planning_style', 'interests', 'accommodation_types', 'accommodation_level', 'accommodation_facilities',
        'transport_modes', 'transport_priority', 'food_cuisines', 'dining_style', 'dietary', 'budget_style',
        'accessibility_mobility', 'accessibility_notes', 'ai_use_preferences', 'ai_use_saved_places', 'ai_use_history', 'ai_auto_add_recommendations',
        'units_distance', 'units_temperature', 'language', 'region',
        'trip_defaults',
    ];

    public static function preferences($input): array
    {
        $preferences = is_array($input) ? $input : [];
        $unknown = array_values(array_diff(array_keys($preferences), self::ALLOWED_KEYS));
        if ($unknown) throw UthengaTieErrors::validation(['preferences' => 'Unsupported preference field(s): ' . implode(', ', $unknown) . '.']);
        foreach ($preferences as $key => $value) {
            if (is_string($value) && mb_strlen($value) > 2000) throw UthengaTieErrors::validation([$key => 'That value is too long.']);
        }
        return $preferences;
    }
}

final class UthengaTieCustomerPreferencesService
{
    public function __construct(private ?PDO $db)
    {
    }

    public function get(string $customerId): array
    {
        $this->db();
        $stmt = $this->db->prepare('SELECT preferences, updated_at FROM customer_travel_preferences WHERE customer_id = ? LIMIT 1');
        $stmt->execute([$customerId]);
        $row = $stmt->fetch();
        $preferences = $row && is_string($row['preferences']) ? (json_decode($row['preferences'], true) ?: []) : [];

        $notify = $this->db->prepare('SELECT push_notify, email_notify, sms_notify FROM users WHERE id = ? LIMIT 1');
        $notify->execute([$customerId]);
        $notifyRow = $notify->fetch();

        return [
            'schema_version' => 'tie-customer-preferences/v1',
            'preferences' => $preferences,
            'notifications' => [
                'push' => $notifyRow ? (bool) $notifyRow['push_notify'] : true,
                'email' => $notifyRow ? (bool) $notifyRow['email_notify'] : true,
                'sms' => $notifyRow ? (bool) $notifyRow['sms_notify'] : false,
            ],
            'updated_at' => $row ? $this->utcIso((string) $row['updated_at']) : null,
        ];
    }

    public function save(string $customerId, array $input): array
    {
        $this->db();
        $preferences = UthengaTieCustomerPreferencesContracts::preferences($input['preferences'] ?? []);
        $stmt = $this->db->prepare('INSERT INTO customer_travel_preferences (customer_id, preferences) VALUES (?, ?) ON DUPLICATE KEY UPDATE preferences = VALUES(preferences)');
        $stmt->execute([$customerId, json_encode($preferences, JSON_UNESCAPED_SLASHES)]);

        if (isset($input['notifications']) && is_array($input['notifications'])) {
            $notifications = $input['notifications'];
            $this->db->prepare('UPDATE users SET push_notify = ?, email_notify = ?, sms_notify = ? WHERE id = ?')
                ->execute([!empty($notifications['push']) ? 1 : 0, !empty($notifications['email']) ? 1 : 0, !empty($notifications['sms']) ? 1 : 0, $customerId]);
        }

        return $this->get($customerId);
    }

    private function utcIso(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
    }

    private function db(): void
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('customer_preferences');
    }
}
