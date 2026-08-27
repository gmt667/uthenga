<?php
/**
 * Quick Taxi Operations — Settings.
 *
 * Deliberately narrow: no payout-method selection (no payout gateway exists
 * anywhere in this codebase), no password/2FA/session management (that is
 * the platform-wide account security system, not a driver-console concern),
 * and no "SOS" that pretends to dispatch real emergency services — an
 * emergency contact is just a stored number the driver can call directly.
 * Everything here is either a real read (profile/verification, from `users`
 * and the optional `driver_profiles` table) or a driver preference that
 * genuinely changes app behavior (the notification sound toggle actually
 * gates the Messages notification chime).
 */

final class UthengaTieDriverSettingsContracts
{
    public static function preferences(array $input): array
    {
        return [
            'notification_sound' => filter_var($input['notification_sound'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'emergency_contact_name' => self::text($input['emergency_contact_name'] ?? null, 120),
            'emergency_contact_phone' => self::text($input['emergency_contact_phone'] ?? null, 30),
        ];
    }

    public static function deactivationReason(array $input): ?string
    {
        return self::text($input['reason'] ?? null, 300);
    }

    private static function text($value, int $maximum): ?string { if (!is_string($value) && !is_numeric($value)) return null; $value = trim((string) $value); return $value === '' ? null : mb_substr($value, 0, $maximum); }
}

final class UthengaTieDriverSettingsService
{
    public function __construct(private ?PDO $db) {}

    public function overview(string $driverUserId): array
    {
        $this->db();
        return [
            'schema_version' => 'tie-driver-settings/v1',
            'profile' => $this->profile($driverUserId),
            'preferences' => $this->preferences($driverUserId),
            'deactivation' => $this->deactivation($driverUserId),
        ];
    }

    public function savePreferences(string $driverUserId, array $input): array
    {
        $this->db(); $request = UthengaTieDriverSettingsContracts::preferences($input);
        $this->db->prepare(
            'INSERT INTO tie_driver_settings (driver_user_id, notification_sound, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE notification_sound = VALUES(notification_sound), emergency_contact_name = VALUES(emergency_contact_name), emergency_contact_phone = VALUES(emergency_contact_phone)'
        )->execute([$driverUserId, $request['notification_sound'] ? 1 : 0, $request['emergency_contact_name'], $request['emergency_contact_phone']]);
        return $this->overview($driverUserId);
    }

    public function requestDeactivation(string $driverUserId, array $input): array
    {
        $this->db(); $reason = UthengaTieDriverSettingsContracts::deactivationReason($input);
        $this->db->prepare(
            'INSERT INTO tie_driver_settings (driver_user_id, deactivation_requested_at, deactivation_reason) VALUES (?, UTC_TIMESTAMP(), ?)
             ON DUPLICATE KEY UPDATE deactivation_requested_at = UTC_TIMESTAMP(), deactivation_reason = VALUES(deactivation_reason)'
        )->execute([$driverUserId, $reason]);
        return $this->overview($driverUserId);
    }

    public function cancelDeactivation(string $driverUserId): array
    {
        $this->db();
        $this->db->prepare('UPDATE tie_driver_settings SET deactivation_requested_at = NULL, deactivation_reason = NULL WHERE driver_user_id = ?')->execute([$driverUserId]);
        return $this->overview($driverUserId);
    }

    private function profile(string $driverUserId): array
    {
        $user = $this->db->prepare('SELECT name, email, phone FROM users WHERE id = ? LIMIT 1'); $user->execute([$driverUserId]); $userRow = $user->fetch();
        // driver_profiles is owned by an older, unrelated part of the app and
        // is not guaranteed to exist (see UthengaTieTripEngineService::readiness()
        // for the same defensive pattern) — a missing table or row degrades
        // to "no verification record", never a fatal error.
        try {
            $driver = $this->db->prepare('SELECT driver_code, license_number, is_verified, rating_average, rating_count, total_trips FROM driver_profiles WHERE user_id = ? LIMIT 1');
            $driver->execute([$driverUserId]); $driverRow = $driver->fetch();
        } catch (Throwable $error) { $driverRow = null; }
        return [
            'name' => is_array($userRow) ? (string) $userRow['name'] : null,
            'email' => is_array($userRow) ? (string) ($userRow['email'] ?? '') : null,
            'phone' => is_array($userRow) ? (string) ($userRow['phone'] ?? '') : null,
            'driver_code' => is_array($driverRow) ? (string) $driverRow['driver_code'] : null,
            'license_number' => is_array($driverRow) ? (string) $driverRow['license_number'] : null,
            'is_verified' => is_array($driverRow) ? (bool) $driverRow['is_verified'] : false,
            'rating_average' => is_array($driverRow) ? (float) $driverRow['rating_average'] : null,
            'rating_count' => is_array($driverRow) ? (int) $driverRow['rating_count'] : null,
            'total_trips' => is_array($driverRow) ? (int) $driverRow['total_trips'] : null,
            'has_driver_profile' => is_array($driverRow),
        ];
    }

    private function preferences(string $driverUserId): array
    {
        $stmt = $this->db->prepare('SELECT notification_sound, emergency_contact_name, emergency_contact_phone FROM tie_driver_settings WHERE driver_user_id = ?'); $stmt->execute([$driverUserId]);
        $row = $stmt->fetch();
        return [
            'notification_sound' => is_array($row) ? (bool) $row['notification_sound'] : true,
            'emergency_contact_name' => is_array($row) ? ($row['emergency_contact_name'] ?? null) : null,
            'emergency_contact_phone' => is_array($row) ? ($row['emergency_contact_phone'] ?? null) : null,
        ];
    }

    private function deactivation(string $driverUserId): ?array
    {
        $stmt = $this->db->prepare('SELECT deactivation_requested_at, deactivation_reason FROM tie_driver_settings WHERE driver_user_id = ? AND deactivation_requested_at IS NOT NULL'); $stmt->execute([$driverUserId]);
        $row = $stmt->fetch();
        if (!is_array($row)) return null;
        return ['requested_at' => $this->utcIso($row['deactivation_requested_at']), 'reason' => $row['deactivation_reason'] ?? null];
    }

    private function utcIso($value): ?string { if (!is_string($value) || trim($value) === '') return null; return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c'); }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('driver_settings'); }
}
