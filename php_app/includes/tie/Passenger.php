<?php
/**
 * Quick Taxi Operations — Passengers.
 *
 * There is no dedicated passenger identity table yet (most Quick Taxi trips
 * today come from the driver's own "+ Manual Trip" flow, not a customer
 * account). This module derives everyone shown in the Passengers workspace by
 * aggregating tie_trips for the current driver, keyed by phone number when
 * one was recorded, or by name for a walk-in trip with no phone. A driver's
 * private notes on a passenger are the one thing that must outlive any single
 * trip, so those get their own small, append-only table.
 */

final class UthengaTiePassengerContracts
{
    public static function key(string $value): array
    {
        $value = trim($value);
        if (str_starts_with($value, 'phone:') && strlen($value) > 6) return ['phone', substr($value, 6)];
        if (str_starts_with($value, 'name:') && strlen($value) > 5) return ['name', substr($value, 5)];
        if (str_starts_with($value, 'coord:') && strlen($value) > 6) return ['coord', substr($value, 6)];
        if (str_starts_with($value, 'walkin:') && strlen($value) > 7) return ['walkin', substr($value, 7)];
        throw UthengaTieErrors::validation(['passenger_key' => 'A valid passenger reference is required.']);
    }

    public static function note(array $input): string
    {
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') throw UthengaTieErrors::validation(['body' => 'A note cannot be empty.']);
        return mb_substr($body, 0, 1000);
    }
}

final class UthengaTiePassengerService
{
    private const KEY_EXPR = "CASE WHEN passenger_phone IS NOT NULL AND passenger_phone <> '' THEN CONCAT('phone:', passenger_phone) ELSE CONCAT('name:', LOWER(TRIM(passenger_name))) END";
    private const ACTIVE_EXPR = "(status IN ('EN_ROUTE','ARRIVED','ONBOARD','IN_PROGRESS')) OR (status='ACCEPTED' AND NOT (is_scheduled=1 AND scheduled_at > UTC_TIMESTAMP()))";
    // A passenger is "frequent" once they have ridden with this driver at
    // least this many times — a plain, disclosed threshold, not a fabricated
    // rating or score.
    private const FREQUENT_TRIP_THRESHOLD = 3;

    public function __construct(private ?PDO $db) {}

    public function summary(string $driverUserId): array
    {
        $this->db();
        $stmt = $this->db->prepare(
            'SELECT COUNT(DISTINCT CASE WHEN DATE(requested_at) = CURDATE() THEN key_expr END) AS today_passengers, COUNT(DISTINCT key_expr) AS total_served
             FROM (SELECT ' . self::KEY_EXPR . ' AS key_expr, requested_at FROM tie_trips WHERE driver_user_id = ?) t'
        );
        $stmt->execute([$driverUserId]); $row = $stmt->fetch() ?: [];

        $repeat = $this->db->prepare(
            'SELECT COUNT(*) FROM (SELECT ' . self::KEY_EXPR . ' AS key_expr FROM tie_trips WHERE driver_user_id = ? GROUP BY key_expr HAVING COUNT(*) >= 2) t'
        );
        $repeat->execute([$driverUserId]);

        $active = $this->db->prepare('SELECT COUNT(*) FROM tie_trips WHERE driver_user_id = ? AND (' . self::ACTIVE_EXPR . ')');
        $active->execute([$driverUserId]);

        $coordRows = $this->coordinationRows($driverUserId);
        $todayDate = gmdate('Y-m-d');
        $coordToday = 0; $coordRepeat = 0; $coordActive = 0;
        foreach ($coordRows as $coordRow) {
            if ($coordRow['last_trip_at'] !== null && substr((string) $coordRow['last_trip_at'], 0, 10) === $todayDate) $coordToday++;
            if ((int) $coordRow['trip_count'] >= 2) $coordRepeat++;
            if ((int) $coordRow['is_active'] === 1) $coordActive++;
        }

        return [
            'schema_version' => 'tie-passenger-summary/v1',
            'today_passengers' => (int) ($row['today_passengers'] ?? 0) + $coordToday,
            'repeat_passengers' => (int) $repeat->fetchColumn() + $coordRepeat,
            'active_passenger' => ((int) $active->fetchColumn() > 0 || $coordActive > 0) ? 1 : 0,
            'total_served' => (int) ($row['total_served'] ?? 0) + count($coordRows),
        ];
    }

    public function list(string $driverUserId, array $filters): array
    {
        $this->db();
        $where = ['driver_user_id = ?']; $params = [$driverUserId];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') { $where[] = '(passenger_name LIKE ? OR passenger_phone LIKE ? OR trip_code LIKE ?)'; $like = '%' . $q . '%'; array_push($params, $like, $like, $like); }
        $sql = 'SELECT ' . self::KEY_EXPR . ' AS passenger_key, MAX(passenger_name) AS passenger_name, MAX(passenger_phone) AS passenger_phone,
                COUNT(*) AS trip_count, SUM(status = \'COMPLETED\') AS completed_count, SUM(status IN (\'CANCELLED\',\'NO_SHOW\')) AS cancelled_count,
                MAX(requested_at) AS last_trip_at, MAX(CASE WHEN ' . self::ACTIVE_EXPR . ' THEN 1 ELSE 0 END) AS is_active
                FROM tie_trips WHERE ' . implode(' AND ', $where) . ' GROUP BY passenger_key ORDER BY last_trip_at DESC LIMIT 200';
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $tab = strtolower((string) ($filters['tab'] ?? 'all'));
        $counts = ['all' => 0, 'active' => 0, 'frequent' => 0, 'previous' => 0];
        $passengers = [];
        foreach ($rows as $row) {
            $counts['all']++; $status = $this->statusOf($row);
            $counts[$status]++;
            if ($tab !== 'all' && $tab !== $status) continue;
            $passengers[] = $this->publicPassenger($row, $status) + ['source' => 'trip', 'active_session_id' => null];
        }
        // Quick Taxi departures (Coordination) contribute a second, distinct
        // passenger population — merged into the same list, tagged by
        // source, per the Phase 4 data-model merge.
        foreach ($this->coordinationRows($driverUserId, $q) as $row) {
            $counts['all']++; $status = $this->statusOf($row);
            $counts[$status]++;
            if ($tab !== 'all' && $tab !== $status) continue;
            $passengers[] = $this->publicCoordinationPassenger($row, $status);
        }
        usort($passengers, fn(array $a, array $b): int => strcmp((string) ($b['last_trip_at'] ?? ''), (string) ($a['last_trip_at'] ?? '')));
        return ['schema_version' => 'tie-passenger-list/v1', 'passengers' => array_slice($passengers, 0, 200), 'counts' => $counts];
    }

    public function detail(string $driverUserId, string $passengerKey): array
    {
        $this->db(); [$type, $value] = UthengaTiePassengerContracts::key($passengerKey);
        if ($type === 'coord' || $type === 'walkin') return $this->coordinationDetail($driverUserId, $type, $value, $passengerKey);
        [$condition, $params] = $this->keyCondition($type, $value);

        $summary = $this->db->prepare(
            'SELECT MAX(passenger_name) AS passenger_name, MAX(passenger_phone) AS passenger_phone, COUNT(*) AS trip_count,
             SUM(status = \'COMPLETED\') AS completed_count, SUM(status IN (\'CANCELLED\',\'NO_SHOW\')) AS cancelled_count, MAX(requested_at) AS last_trip_at,
             MAX(CASE WHEN ' . self::ACTIVE_EXPR . ' THEN 1 ELSE 0 END) AS is_active
             FROM tie_trips WHERE driver_user_id = ? AND ' . $condition
        );
        $summary->execute(array_merge([$driverUserId], $params));
        $row = $summary->fetch();
        if (!is_array($row) || (int) $row['trip_count'] === 0) throw UthengaTieErrors::validation(['passenger_key' => 'Passenger not found.']);

        $history = $this->db->prepare(
            'SELECT id, trip_code, status, requested_at, completed_at, pickup_location, destination_location, final_fare, estimated_fare, cancellation_reason
             FROM tie_trips WHERE driver_user_id = ? AND ' . $condition . ' ORDER BY requested_at DESC LIMIT 100'
        );
        $history->execute(array_merge([$driverUserId], $params));
        $trips = $history->fetchAll();

        $currentTrip = null; $previousIssue = null;
        foreach ($trips as $trip) {
            if ($currentTrip === null && $this->isActiveTripRow($trip)) $currentTrip = $this->publicTripSummary($trip);
            if ($previousIssue === null && in_array($trip['status'], ['CANCELLED', 'NO_SHOW', 'DISPUTED'], true) && !empty($trip['cancellation_reason'])) {
                $previousIssue = ['trip_code' => (string) $trip['trip_code'], 'status' => (string) $trip['status'], 'reason' => (string) $trip['cancellation_reason'], 'occurred_at' => $this->utcIso($trip['requested_at'])];
            }
        }

        $notes = $this->db->prepare('SELECT id, author_id, body, created_at FROM tie_trip_passenger_notes WHERE driver_user_id = ? AND passenger_key = ? ORDER BY id DESC');
        $notes->execute([$driverUserId, $type . ':' . $value]);

        return [
            'schema_version' => 'tie-passenger-detail/v1',
            'passenger' => $this->publicPassenger($row, $this->statusOf($row), $type . ':' . $value) + ['source' => 'trip', 'active_session_id' => null],
            'current_trip' => $currentTrip,
            'history' => array_map(fn(array $trip): array => $this->publicTripSummary($trip), $trips),
            'notes' => array_map(fn(array $note): array => ['id' => (int) $note['id'], 'author_id' => (string) $note['author_id'], 'body' => (string) $note['body'], 'created_at' => $this->utcIso($note['created_at'])], $notes->fetchAll()),
            'previous_issue' => $previousIssue,
        ];
    }

    public function addNote(string $driverUserId, string $passengerKey, array $input): array
    {
        $this->db(); [$type, $value] = UthengaTiePassengerContracts::key($passengerKey);
        $body = UthengaTiePassengerContracts::note($input);
        $this->db->prepare('INSERT INTO tie_trip_passenger_notes (driver_user_id, passenger_key, author_id, body) VALUES (?, ?, ?, ?)')
            ->execute([$driverUserId, $type . ':' . $value, $driverUserId, $body]);
        return $this->detail($driverUserId, $passengerKey);
    }

    // One row per distinct Coordination passenger (real customer or walk-in)
    // this driver has ever carried, aggregated across all their departures.
    private function coordinationRows(string $driverUserId, string $q = ''): array
    {
        $having = ''; $params = [$driverUserId];
        if ($q !== '') { $having = ' HAVING passenger_name LIKE ?'; $params[] = '%' . $q . '%'; }
        $sql = "SELECT
            CASE WHEN s.customer_id IS NOT NULL THEN CONCAT('coord:', s.customer_id) ELSE CONCAT('walkin:', LOWER(TRIM(s.walk_in_name))) END AS passenger_key,
            COALESCE(MAX(u.name), MAX(s.walk_in_name)) AS passenger_name,
            COUNT(DISTINCT s.run_id) AS trip_count,
            SUM(s.status = 'COMPLETED' OR (s.status = 'BOARDED' AND r.status = 'COMPLETED')) AS completed_count,
            SUM(s.status IN ('NO_SHOW','CUSTOMER_CANCELLED','DECLINED')) AS cancelled_count,
            MAX(s.created_at) AS last_trip_at,
            MAX(CASE WHEN r.status IN ('SCHEDULED','LOADING','TRAVELLING') AND s.status NOT IN ('NO_SHOW','CUSTOMER_CANCELLED','DECLINED','EXPIRED','COMPLETED') THEN 1 ELSE 0 END) AS is_active,
            MAX(CASE WHEN s.customer_id IS NOT NULL AND r.status IN ('SCHEDULED','LOADING','TRAVELLING') AND s.status NOT IN ('NO_SHOW','CUSTOMER_CANCELLED','DECLINED','EXPIRED','COMPLETED') THEN s.id END) AS active_session_id
            FROM tie_transport_sessions s
            INNER JOIN tie_transport_runs r ON r.id = s.run_id
            LEFT JOIN users u ON u.id = s.customer_id
            WHERE s.vendor_id = ?
            GROUP BY passenger_key{$having}
            ORDER BY last_trip_at DESC LIMIT 200";
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function publicCoordinationPassenger(array $row, string $status): array
    {
        return [
            'passenger_key' => (string) $row['passenger_key'],
            'passenger_name' => (string) $row['passenger_name'],
            // Never a phone number here — Coordination is Uthenga-mediated
            // contact only, same principle as calling/messaging.
            'passenger_phone' => null,
            'trip_count' => (int) $row['trip_count'],
            'completed_count' => (int) $row['completed_count'],
            'cancelled_count' => (int) $row['cancelled_count'],
            'last_trip_at' => $this->utcIso($row['last_trip_at']),
            'status' => $status,
            'source' => 'coordination',
            'active_session_id' => $row['active_session_id'] !== null ? (string) $row['active_session_id'] : null,
        ];
    }

    private function coordinationDetail(string $driverUserId, string $type, string $value, string $passengerKey): array
    {
        $condition = $type === 'coord' ? 's.customer_id = ?' : "s.customer_id IS NULL AND LOWER(TRIM(s.walk_in_name)) = ?";
        $rows = $this->db->prepare(
            "SELECT s.id, s.run_id, s.status, s.created_at, s.boarded_at, s.passenger_count, r.status AS run_status, r.loading_location, r.completed_at AS run_completed_at,
             (SELECT p.amount FROM tie_transport_payments p WHERE p.session_id = s.id AND p.state='PAID' ORDER BY p.created_at DESC LIMIT 1) AS paid_amount
             FROM tie_transport_sessions s INNER JOIN tie_transport_runs r ON r.id = s.run_id
             WHERE s.vendor_id = ? AND {$condition} ORDER BY s.created_at DESC LIMIT 100"
        );
        $rows->execute([$driverUserId, $value]);
        $sessions = $rows->fetchAll();
        if (count($sessions) === 0) throw UthengaTieErrors::validation(['passenger_key' => 'Passenger not found.']);

        $name = 'Passenger';
        if ($type === 'coord') { $nameStmt = $this->db->prepare('SELECT name FROM users WHERE id=? LIMIT 1'); $nameStmt->execute([$value]); $found = $nameStmt->fetchColumn(); if ($found !== false) $name = (string) $found; }
        else { $name = ucwords($value); }

        $completedCount = 0; $cancelledCount = 0; $currentTrip = null; $activeSessionId = null; $history = [];
        foreach ($sessions as $row) {
            if ($row['status'] === 'COMPLETED' || ($row['status'] === 'BOARDED' && $row['run_status'] === 'COMPLETED')) $completedCount++;
            if (in_array($row['status'], ['NO_SHOW', 'CUSTOMER_CANCELLED', 'DECLINED'], true)) $cancelledCount++;
            $isActive = in_array($row['run_status'], ['SCHEDULED', 'LOADING', 'TRAVELLING'], true) && !in_array($row['status'], ['NO_SHOW', 'CUSTOMER_CANCELLED', 'DECLINED', 'EXPIRED', 'COMPLETED'], true);
            if ($isActive) { if ($currentTrip === null) $currentTrip = $this->publicCoordinationTripSummary($row); if ($type === 'coord' && $activeSessionId === null) $activeSessionId = (string) $row['id']; }
            $history[] = $this->publicCoordinationTripSummary($row);
        }
        $tripCount = count(array_unique(array_column($sessions, 'run_id')));

        $notes = $this->db->prepare('SELECT id, author_id, body, created_at FROM tie_trip_passenger_notes WHERE driver_user_id = ? AND passenger_key = ? ORDER BY id DESC');
        $notes->execute([$driverUserId, $type . ':' . $value]);

        return [
            'schema_version' => 'tie-passenger-detail/v1',
            'passenger' => [
                'passenger_key' => $passengerKey, 'passenger_name' => $name, 'passenger_phone' => null,
                'trip_count' => $tripCount, 'completed_count' => $completedCount, 'cancelled_count' => $cancelledCount,
                'last_trip_at' => $this->utcIso($sessions[0]['created_at']),
                'status' => $this->statusOf(['is_active' => $currentTrip !== null ? 1 : 0, 'trip_count' => $tripCount]),
                'source' => 'coordination', 'active_session_id' => $activeSessionId,
            ],
            'current_trip' => $currentTrip,
            'history' => $history,
            'notes' => array_map(fn(array $note): array => ['id' => (int) $note['id'], 'author_id' => (string) $note['author_id'], 'body' => (string) $note['body'], 'created_at' => $this->utcIso($note['created_at'])], $notes->fetchAll()),
            'previous_issue' => null,
        ];
    }

    private function publicCoordinationTripSummary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'trip_code' => 'DEP-' . strtoupper(substr((string) $row['run_id'], 0, 8)),
            'status' => (string) $row['status'],
            'requested_at' => $this->utcIso($row['created_at']),
            'completed_at' => $row['run_completed_at'] ? $this->utcIso($row['run_completed_at']) : null,
            'pickup_location' => (string) $row['loading_location'],
            'destination_location' => '—',
            'fare' => $row['paid_amount'] !== null ? (float) $row['paid_amount'] : null,
        ];
    }

    private function keyCondition(string $type, string $value): array
    {
        return $type === 'phone'
            ? ['passenger_phone = ?', [$value]]
            : ["(passenger_phone IS NULL OR passenger_phone = '') AND LOWER(TRIM(passenger_name)) = ?", [$value]];
    }

    private function isActiveTripRow(array $trip): bool
    {
        $status = (string) $trip['status'];
        return in_array($status, ['EN_ROUTE', 'ARRIVED', 'ONBOARD', 'IN_PROGRESS'], true) || $status === 'ACCEPTED';
    }

    private function statusOf(array $row): string
    {
        if ((int) ($row['is_active'] ?? 0) === 1) return 'active';
        if ((int) ($row['trip_count'] ?? 0) >= self::FREQUENT_TRIP_THRESHOLD) return 'frequent';
        return 'previous';
    }

    private function publicPassenger(array $row, string $status, ?string $passengerKey = null): array
    {
        return [
            'passenger_key' => $passengerKey ?? (string) $row['passenger_key'],
            'passenger_name' => (string) $row['passenger_name'],
            'passenger_phone' => $row['passenger_phone'] !== null ? (string) $row['passenger_phone'] : null,
            'trip_count' => (int) $row['trip_count'],
            'completed_count' => (int) $row['completed_count'],
            'cancelled_count' => (int) $row['cancelled_count'],
            'last_trip_at' => $this->utcIso($row['last_trip_at']),
            'status' => $status,
        ];
    }

    private function publicTripSummary(array $trip): array
    {
        return [
            'id' => (string) $trip['id'],
            'trip_code' => (string) $trip['trip_code'],
            'status' => (string) $trip['status'],
            'requested_at' => $this->utcIso($trip['requested_at']),
            'completed_at' => $this->utcIso($trip['completed_at'] ?? null),
            'pickup_location' => (string) $trip['pickup_location'],
            'destination_location' => (string) $trip['destination_location'],
            'fare' => $trip['final_fare'] !== null ? (float) $trip['final_fare'] : ($trip['estimated_fare'] !== null ? (float) $trip['estimated_fare'] : null),
        ];
    }

    private function utcIso($value): ?string { if (!is_string($value) || trim($value) === '') return null; return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c'); }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('passenger'); }
}
