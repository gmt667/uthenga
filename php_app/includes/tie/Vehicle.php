<?php
/**
 * Quick Taxi Operations — Vehicle.
 *
 * There is no engine/brake/tyre telemetry or inspection system in this
 * codebase, so this module never fabricates a "vehicle health" score. It
 * models only what a driver can genuinely report: a vehicle profile,
 * documents with real expiry dates (status is computed from today's date,
 * not invented), a self-reported mileage/maintenance log, and issue reports.
 * Vehicle Activity is a real aggregate over the driver's own completed trips.
 */

final class UthengaTieVehicleContracts
{
    private const DOCUMENT_TYPES = ['registration', 'insurance', 'roadworthiness', 'permit'];
    private const ISSUE_CATEGORIES = ['brakes', 'engine', 'tyres', 'electrical', 'ac', 'body', 'other'];
    private const SEVERITIES = ['low', 'medium', 'critical'];

    public static function profile(array $input): array
    {
        $makeModel = self::text($input['make_model'] ?? null, 120);
        if ($makeModel === null) throw UthengaTieErrors::validation(['make_model' => 'A vehicle make and model is required.']);
        $plate = self::text($input['plate_number'] ?? null, 30);
        if ($plate === null) throw UthengaTieErrors::validation(['plate_number' => 'A plate number is required.']);
        return [
            'make_model' => $makeModel,
            'plate_number' => strtoupper($plate),
            'colour' => self::text($input['colour'] ?? null, 40),
            'category' => self::text($input['category'] ?? null, 40),
            'photo_url' => self::text($input['photo_url'] ?? null, 500),
        ];
    }

    public static function mileage($value): int
    {
        if (!filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2000000]])) throw UthengaTieErrors::validation(['current_mileage_km' => 'A valid mileage in kilometres is required.']);
        return (int) $value;
    }

    public static function document(array $input): array
    {
        $type = strtolower(trim((string) ($input['document_type'] ?? '')));
        if (!in_array($type, self::DOCUMENT_TYPES, true)) throw UthengaTieErrors::validation(['document_type' => 'Document type must be one of: ' . implode(', ', self::DOCUMENT_TYPES) . '.']);
        return ['document_type' => $type, 'expiry_date' => self::date($input['expiry_date'] ?? null, 'expiry_date')];
    }

    public static function maintenance(array $input): array
    {
        $serviceType = self::text($input['service_type'] ?? null, 120);
        if ($serviceType === null) throw UthengaTieErrors::validation(['service_type' => 'A service type is required.']);
        return [
            'service_type' => $serviceType,
            'serviced_at' => self::date($input['serviced_at'] ?? null, 'serviced_at'),
            'mileage_km' => isset($input['mileage_km']) && $input['mileage_km'] !== '' ? self::mileage($input['mileage_km']) : null,
            'notes' => self::text($input['notes'] ?? null, 500),
        ];
    }

    public static function issue(array $input): array
    {
        $category = strtolower(trim((string) ($input['category'] ?? '')));
        if (!in_array($category, self::ISSUE_CATEGORIES, true)) throw UthengaTieErrors::validation(['category' => 'Issue category must be one of: ' . implode(', ', self::ISSUE_CATEGORIES) . '.']);
        $description = self::text($input['description'] ?? null, 1000);
        if ($description === null) throw UthengaTieErrors::validation(['description' => 'A description of the issue is required.']);
        $severity = strtolower(trim((string) ($input['severity'] ?? 'low')));
        if (!in_array($severity, self::SEVERITIES, true)) $severity = 'low';
        return ['category' => $category, 'description' => $description, 'severity' => $severity];
    }

    public static function issueId($value): int
    {
        if (!filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw UthengaTieErrors::validation(['issue_id' => 'A valid issue reference is required.']);
        return (int) $value;
    }

    private static function date($value, string $field): string
    {
        try { $date = new DateTimeImmutable(trim((string) $value)); } catch (Throwable $error) { throw UthengaTieErrors::validation([$field => 'A valid date is required.']); }
        return $date->format('Y-m-d');
    }
    private static function text($value, int $maximum): ?string { if (!is_string($value) && !is_numeric($value)) return null; $value = trim((string) $value); return $value === '' ? null : mb_substr($value, 0, $maximum); }
}

final class UthengaTieVehicleService
{
    public function __construct(private ?PDO $db) {}

    public function overview(string $driverUserId): array
    {
        $this->db();
        $profile = $this->profileRow($driverUserId);
        $documents = $this->documents($driverUserId);
        $maintenance = $this->maintenance($driverUserId);
        $issues = $this->issues($driverUserId);
        return [
            'schema_version' => 'tie-vehicle-overview/v1',
            'vehicle' => $profile,
            'documents' => $documents,
            'maintenance' => $maintenance,
            'open_issues' => array_values(array_filter($issues, fn(array $issue): bool => $issue['status'] === 'open')),
            'resolved_issues' => array_values(array_filter($issues, fn(array $issue): bool => $issue['status'] === 'resolved')),
            'activity' => $this->activity($driverUserId),
            'mileage_since_service' => $this->mileageSinceService($profile, $maintenance),
        ];
    }

    public function saveProfile(string $driverUserId, array $input): array
    {
        $this->db(); $request = UthengaTieVehicleContracts::profile($input);
        $this->db->prepare(
            'INSERT INTO tie_driver_vehicles (driver_user_id, make_model, plate_number, colour, category, photo_url) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE make_model = VALUES(make_model), plate_number = VALUES(plate_number), colour = VALUES(colour), category = VALUES(category), photo_url = VALUES(photo_url)'
        )->execute([$driverUserId, $request['make_model'], $request['plate_number'], $request['colour'], $request['category'], $request['photo_url']]);
        return $this->overview($driverUserId);
    }

    public function setStatus(string $driverUserId, string $status): array
    {
        $this->db(); $status = strtolower($status) === 'inactive' ? 'inactive' : 'active';
        if ($this->profileRow($driverUserId) === null) throw UthengaTieErrors::validation(['vehicle' => 'Set up your vehicle profile first.']);
        $this->db->prepare('UPDATE tie_driver_vehicles SET status = ? WHERE driver_user_id = ?')->execute([$status, $driverUserId]);
        return $this->overview($driverUserId);
    }

    public function updateMileage(string $driverUserId, $mileage): array
    {
        $this->db(); $value = UthengaTieVehicleContracts::mileage($mileage);
        if ($this->profileRow($driverUserId) === null) throw UthengaTieErrors::validation(['vehicle' => 'Set up your vehicle profile first.']);
        $this->db->prepare('UPDATE tie_driver_vehicles SET current_mileage_km = ?, mileage_updated_at = UTC_TIMESTAMP() WHERE driver_user_id = ?')->execute([$value, $driverUserId]);
        return $this->overview($driverUserId);
    }

    public function saveDocument(string $driverUserId, array $input): array
    {
        $this->db(); $request = UthengaTieVehicleContracts::document($input);
        $this->db->prepare(
            'INSERT INTO tie_vehicle_documents (driver_user_id, document_type, expiry_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE expiry_date = VALUES(expiry_date)'
        )->execute([$driverUserId, $request['document_type'], $request['expiry_date']]);
        return $this->overview($driverUserId);
    }

    public function addMaintenance(string $driverUserId, array $input): array
    {
        $this->db(); $request = UthengaTieVehicleContracts::maintenance($input);
        $this->db->prepare('INSERT INTO tie_vehicle_maintenance (driver_user_id, service_type, serviced_at, mileage_km, notes) VALUES (?, ?, ?, ?, ?)')
            ->execute([$driverUserId, $request['service_type'], $request['serviced_at'], $request['mileage_km'], $request['notes']]);
        return $this->overview($driverUserId);
    }

    public function reportIssue(string $driverUserId, array $input): array
    {
        $this->db(); $request = UthengaTieVehicleContracts::issue($input);
        $this->db->prepare('INSERT INTO tie_vehicle_issues (driver_user_id, category, description, severity) VALUES (?, ?, ?, ?)')
            ->execute([$driverUserId, $request['category'], $request['description'], $request['severity']]);
        return $this->overview($driverUserId);
    }

    public function resolveIssue(string $driverUserId, $issueId): array
    {
        $this->db(); $id = UthengaTieVehicleContracts::issueId($issueId);
        $this->db->prepare("UPDATE tie_vehicle_issues SET status = 'resolved', resolved_at = UTC_TIMESTAMP() WHERE id = ? AND driver_user_id = ? AND status = 'open'")->execute([$id, $driverUserId]);
        return $this->overview($driverUserId);
    }

    private function profileRow(string $driverUserId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tie_driver_vehicles WHERE driver_user_id = ?'); $stmt->execute([$driverUserId]);
        $row = $stmt->fetch();
        if (!is_array($row)) return null;
        return [
            'make_model' => (string) $row['make_model'],
            'plate_number' => (string) $row['plate_number'],
            'colour' => $row['colour'] ?? null,
            'category' => $row['category'] ?? null,
            'photo_url' => $row['photo_url'] ?? null,
            'status' => (string) $row['status'],
            'current_mileage_km' => $row['current_mileage_km'] !== null ? (int) $row['current_mileage_km'] : null,
            'mileage_updated_at' => $this->utcIso($row['mileage_updated_at'] ?? null),
            'assigned_at' => $this->utcIso($row['created_at']),
        ];
    }

    private function documents(string $driverUserId): array
    {
        $stmt = $this->db->prepare('SELECT document_type, expiry_date FROM tie_vehicle_documents WHERE driver_user_id = ? ORDER BY expiry_date ASC'); $stmt->execute([$driverUserId]);
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        return array_map(function (array $row) use ($today): array {
            $expiry = new DateTimeImmutable((string) $row['expiry_date'], new DateTimeZone('UTC'));
            $daysLeft = (int) $today->diff($expiry)->format('%r%a');
            $status = $daysLeft < 0 ? 'expired' : ($daysLeft <= 30 ? 'expiring_soon' : 'valid');
            return ['document_type' => (string) $row['document_type'], 'expiry_date' => $row['expiry_date'], 'days_remaining' => $daysLeft, 'status' => $status];
        }, $stmt->fetchAll());
    }

    private function maintenance(string $driverUserId): array
    {
        $stmt = $this->db->prepare('SELECT id, service_type, serviced_at, mileage_km, notes FROM tie_vehicle_maintenance WHERE driver_user_id = ? ORDER BY serviced_at DESC, id DESC LIMIT 50');
        $stmt->execute([$driverUserId]);
        return array_map(fn(array $row): array => [
            'id' => (int) $row['id'], 'service_type' => (string) $row['service_type'], 'serviced_at' => $row['serviced_at'],
            'mileage_km' => $row['mileage_km'] !== null ? (int) $row['mileage_km'] : null, 'notes' => $row['notes'] ?? null,
        ], $stmt->fetchAll());
    }

    private function issues(string $driverUserId): array
    {
        $stmt = $this->db->prepare('SELECT id, category, description, severity, status, created_at, resolved_at FROM tie_vehicle_issues WHERE driver_user_id = ? ORDER BY created_at DESC LIMIT 100');
        $stmt->execute([$driverUserId]);
        return array_map(fn(array $row): array => [
            'id' => (int) $row['id'], 'category' => (string) $row['category'], 'description' => (string) $row['description'],
            'severity' => (string) $row['severity'], 'status' => (string) $row['status'],
            'created_at' => $this->utcIso($row['created_at']), 'resolved_at' => $this->utcIso($row['resolved_at'] ?? null),
        ], $stmt->fetchAll());
    }

    private function activity(string $driverUserId): array
    {
        $today = $this->db->prepare(
            "SELECT COUNT(*) AS trips, COALESCE(SUM(distance_km), 0) AS distance_km FROM tie_trips
             WHERE driver_user_id = ? AND status = 'COMPLETED' AND DATE(completed_at) = CURDATE()"
        );
        $today->execute([$driverUserId]); $todayRow = $today->fetch() ?: [];

        $average = $this->db->prepare("SELECT COALESCE(AVG(distance_km), 0) FROM tie_trips WHERE driver_user_id = ? AND status = 'COMPLETED' AND distance_km IS NOT NULL");
        $average->execute([$driverUserId]);

        $status = $this->db->prepare('SELECT is_online, online_since FROM tie_trip_driver_status WHERE driver_user_id = ?'); $status->execute([$driverUserId]);
        $statusRow = $status->fetch();
        $onlineSince = is_array($statusRow) && (bool) $statusRow['is_online'] ? $statusRow['online_since'] : null;
        $currentSessionMinutes = null;
        if ($onlineSince !== null) $currentSessionMinutes = max(0, (int) round((time() - strtotime($onlineSince . ' UTC')) / 60));

        return [
            'today_distance_km' => (float) ($todayRow['distance_km'] ?? 0),
            'today_trips' => (int) ($todayRow['trips'] ?? 0),
            'average_trip_distance_km' => (float) $average->fetchColumn(),
            'current_session_minutes' => $currentSessionMinutes,
        ];
    }

    private function mileageSinceService(?array $profile, array $maintenance): ?int
    {
        if ($profile === null || $profile['current_mileage_km'] === null) return null;
        $lastServiceMileage = null;
        foreach ($maintenance as $record) if ($record['mileage_km'] !== null) { $lastServiceMileage = $record['mileage_km']; break; }
        if ($lastServiceMileage === null) return null;
        return max(0, $profile['current_mileage_km'] - $lastServiceMileage);
    }

    private function utcIso($value): ?string { if (!is_string($value) || trim($value) === '') return null; return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c'); }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('vehicle'); }
}
