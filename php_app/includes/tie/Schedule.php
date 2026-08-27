<?php
/**
 * Quick Taxi Operations — Schedule.
 *
 * "Start Shift" / "End Shift" is the same online/offline toggle the
 * Dashboard already exposes through UthengaTieTripEngineService — this
 * module wraps that toggle with a session log so a driver's actual worked
 * time becomes visible history, and adds a driver-set weekly availability
 * template. There is no vendor-assigned roster or demand-forecasting system
 * in this codebase, so neither is fabricated here.
 */

final class UthengaTieScheduleContracts
{
    public static function availability(array $days): array
    {
        $result = [];
        foreach ($days as $entry) {
            if (!is_array($entry)) continue;
            $day = filter_var($entry['day_of_week'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
            if ($day === false) throw UthengaTieErrors::validation(['day_of_week' => 'day_of_week must be 1 (Monday) through 7 (Sunday).']);
            $isOff = filter_var($entry['is_off'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $start = $isOff ? null : self::time($entry['start_time'] ?? null, 'start_time');
            $end = $isOff ? null : self::time($entry['end_time'] ?? null, 'end_time');
            if (!$isOff && $start !== null && $end !== null && $start >= $end) throw UthengaTieErrors::validation(['end_time' => 'End time must be after start time.']);
            $result[(int) $day] = ['is_off' => $isOff, 'start_time' => $start, 'end_time' => $end];
        }
        return $result;
    }

    private static function time($value, string $field): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)) throw UthengaTieErrors::validation([$field => 'A valid time (HH:MM) is required.']);
        return $value . ':00';
    }
}

final class UthengaTieScheduleService
{
    private const DAY_NAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function __construct(private ?PDO $db, private UthengaTieTripEngineService $tripEngine) {}

    public function overview(string $driverUserId): array
    {
        $this->db();
        return [
            'schema_version' => 'tie-schedule-overview/v1',
            'current_session' => $this->currentSession($driverUserId),
            'history' => $this->history($driverUserId),
            'availability' => $this->availability($driverUserId),
            'next_available' => $this->nextAvailable($driverUserId),
        ];
    }

    public function startShift(string $driverUserId): array
    {
        $this->db();
        if ($this->openSession($driverUserId) === null) {
            $this->db->prepare('INSERT INTO tie_driver_shift_sessions (driver_user_id, started_at) VALUES (?, UTC_TIMESTAMP())')->execute([$driverUserId]);
        }
        $this->tripEngine->setOnlineStatus($driverUserId, true);
        return $this->overview($driverUserId);
    }

    public function endShift(string $driverUserId): array
    {
        $this->db(); $session = $this->openSession($driverUserId);
        if ($session !== null) {
            $stats = $this->db->prepare("SELECT COUNT(*) AS trips, COALESCE(SUM(final_fare), 0) AS earnings FROM tie_trips WHERE driver_user_id = ? AND status = 'COMPLETED' AND completed_at >= ?");
            $stats->execute([$driverUserId, $session['started_at']]);
            $row = $stats->fetch() ?: ['trips' => 0, 'earnings' => 0];
            $this->db->prepare('UPDATE tie_driver_shift_sessions SET ended_at = UTC_TIMESTAMP(), trips_count = ?, earnings = ? WHERE id = ?')->execute([(int) $row['trips'], (float) $row['earnings'], $session['id']]);
        }
        $this->tripEngine->setOnlineStatus($driverUserId, false);
        return $this->overview($driverUserId);
    }

    public function saveAvailability(string $driverUserId, array $input): array
    {
        $this->db(); $days = UthengaTieScheduleContracts::availability($input['days'] ?? []);
        foreach ($days as $day => $row) {
            $this->db->prepare(
                'INSERT INTO tie_driver_availability (driver_user_id, day_of_week, is_off, start_time, end_time) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_off = VALUES(is_off), start_time = VALUES(start_time), end_time = VALUES(end_time)'
            )->execute([$driverUserId, $day, $row['is_off'] ? 1 : 0, $row['start_time'], $row['end_time']]);
        }
        return $this->overview($driverUserId);
    }

    private function currentSession(string $driverUserId): ?array
    {
        $session = $this->openSession($driverUserId);
        if ($session === null) return null;
        $stats = $this->db->prepare("SELECT COUNT(*) AS trips, COALESCE(SUM(final_fare), 0) AS earnings FROM tie_trips WHERE driver_user_id = ? AND status = 'COMPLETED' AND completed_at >= ?");
        $stats->execute([$driverUserId, $session['started_at']]);
        $row = $stats->fetch() ?: ['trips' => 0, 'earnings' => 0];
        return [
            'started_at' => $this->utcIso($session['started_at']),
            'elapsed_minutes' => max(0, (int) round((time() - strtotime($session['started_at'] . ' UTC')) / 60)),
            'trips_count' => (int) $row['trips'],
            'earnings' => (float) $row['earnings'],
        ];
    }

    private function history(string $driverUserId): array
    {
        $stmt = $this->db->prepare('SELECT id, started_at, ended_at, trips_count, earnings FROM tie_driver_shift_sessions WHERE driver_user_id = ? AND ended_at IS NOT NULL ORDER BY started_at DESC LIMIT 30');
        $stmt->execute([$driverUserId]);
        return array_map(function (array $row): array {
            $minutes = max(0, (int) round((strtotime($row['ended_at'] . ' UTC') - strtotime($row['started_at'] . ' UTC')) / 60));
            return [
                'id' => (int) $row['id'], 'started_at' => $this->utcIso($row['started_at']), 'ended_at' => $this->utcIso($row['ended_at']),
                'duration_minutes' => $minutes, 'trips_count' => (int) ($row['trips_count'] ?? 0), 'earnings' => (float) ($row['earnings'] ?? 0),
            ];
        }, $stmt->fetchAll());
    }

    private function availability(string $driverUserId): array
    {
        $stmt = $this->db->prepare('SELECT day_of_week, is_off, start_time, end_time FROM tie_driver_availability WHERE driver_user_id = ?'); $stmt->execute([$driverUserId]);
        $byDay = [];
        foreach ($stmt->fetchAll() as $row) $byDay[(int) $row['day_of_week']] = ['is_off' => (bool) $row['is_off'], 'start_time' => $row['start_time'] !== null ? substr((string) $row['start_time'], 0, 5) : null, 'end_time' => $row['end_time'] !== null ? substr((string) $row['end_time'], 0, 5) : null];
        $days = [];
        for ($day = 1; $day <= 7; $day++) {
            $entry = $byDay[$day] ?? ['is_off' => true, 'start_time' => null, 'end_time' => null];
            $days[] = ['day_of_week' => $day, 'label' => self::DAY_NAMES[$day], 'is_off' => $entry['is_off'], 'start_time' => $entry['start_time'], 'end_time' => $entry['end_time']];
        }
        return $days;
    }

    private function nextAvailable(string $driverUserId): ?array
    {
        $availability = [];
        foreach ($this->availability($driverUserId) as $day) $availability[$day['day_of_week']] = $day;
        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        for ($offset = 1; $offset <= 7; $offset++) {
            $date = $today->modify("+{$offset} days");
            $dayOfWeek = (int) $date->format('N');
            $entry = $availability[$dayOfWeek] ?? null;
            if ($entry !== null && !$entry['is_off'] && $entry['start_time'] !== null) {
                return ['date' => $date->format('Y-m-d'), 'label' => $entry['label'], 'start_time' => $entry['start_time'], 'end_time' => $entry['end_time']];
            }
        }
        return null;
    }

    private function openSession(string $driverUserId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, started_at FROM tie_driver_shift_sessions WHERE driver_user_id = ? AND ended_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute([$driverUserId]); $row = $stmt->fetch(); return is_array($row) ? $row : null;
    }

    private function utcIso($value): ?string { if (!is_string($value) || trim($value) === '') return null; return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c'); }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('schedule'); }
}
