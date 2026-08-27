<?php
/**
 * Quick Taxi Operations — Trip Engine.
 *
 * A single canonical taxi-trip lifecycle (Requested -> Assigned -> Accepted ->
 * En Route -> Arrived -> Onboard -> In Progress -> Completed, with
 * Cancelled/No-show/Disputed exits) that Dashboard, Trips, and later
 * Passengers/Messages/Earnings/Reports all read from. Deliberately separate
 * from UthengaTieCoordinationService, which models bus/coach loading — a taxi
 * trip has its own vocabulary and does not fit the run/session shape there.
 */

final class UthengaTieTripEngineContracts
{
    public static function createManualTrip(array $input): array
    {
        $passengerName = self::text($input['passenger_name'] ?? null, 120);
        if ($passengerName === null) throw UthengaTieErrors::validation(['passenger_name' => 'A passenger name is required.']);
        $pickup = self::text($input['pickup_location'] ?? null, 200);
        if ($pickup === null) throw UthengaTieErrors::validation(['pickup_location' => 'A pickup location is required.']);
        $destination = self::text($input['destination_location'] ?? null, 200);
        if ($destination === null) throw UthengaTieErrors::validation(['destination_location' => 'A destination is required.']);
        $isScheduled = filter_var($input['is_scheduled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $scheduledAt = null;
        if ($isScheduled) {
            $scheduledAt = self::dateTime($input['scheduled_at'] ?? null, 'scheduled_at');
            if ($scheduledAt <= new DateTimeImmutable('now')) throw UthengaTieErrors::validation(['scheduled_at' => 'A scheduled trip must be in the future.']);
        }
        return [
            'passenger_name' => $passengerName,
            'passenger_phone' => self::text($input['passenger_phone'] ?? null, 30),
            'pickup_location' => $pickup,
            'destination_location' => $destination,
            'vehicle_label' => self::text($input['vehicle_label'] ?? null, 120),
            'vehicle_plate' => self::text($input['vehicle_plate'] ?? null, 30),
            'is_scheduled' => $isScheduled,
            'scheduled_at' => $scheduledAt,
            'estimated_fare' => self::money($input['estimated_fare'] ?? null, 'estimated_fare', false),
        ];
    }

    public static function complete(array $input): array
    {
        return [
            'final_fare' => self::money($input['final_fare'] ?? null, 'final_fare', true),
            'payment_method' => self::enum($input['payment_method'] ?? null, ['digital', 'cash'], 'payment_method'),
            'distance_km' => self::decimal($input['distance_km'] ?? null, 'distance_km'),
            'duration_seconds' => self::positiveInt($input['duration_seconds'] ?? null, 'duration_seconds', 86400),
        ];
    }

    public static function cancel(array $input): array
    {
        return ['reason' => self::text($input['reason'] ?? null, 300)];
    }

    public static function tripId($value): string { return self::uuid($value, 'trip_id'); }

    private static function enum($value, array $allowed, string $field): string
    {
        $value = strtolower(trim((string) $value));
        if (!in_array($value, $allowed, true)) throw UthengaTieErrors::validation([$field => "{$field} must be one of: " . implode(', ', $allowed) . '.']);
        return $value;
    }
    private static function money($value, string $field, bool $required): ?float
    {
        if ($value === null || $value === '') { if ($required) throw UthengaTieErrors::validation([$field => 'A valid amount is required.']); return null; }
        if (!is_numeric($value) || (float) $value < 0) throw UthengaTieErrors::validation([$field => 'A valid non-negative amount is required.']);
        return round((float) $value, 2);
    }
    private static function decimal($value, string $field): ?float
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value) || (float) $value < 0) throw UthengaTieErrors::validation([$field => 'A valid non-negative number is required.']);
        return round((float) $value, 2);
    }
    private static function positiveInt($value, string $field, int $max): ?int
    {
        if ($value === null || $value === '') return null;
        if (!filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $max]])) throw UthengaTieErrors::validation([$field => "{$field} must be a valid whole number."]);
        return (int) $value;
    }
    private static function uuid($value, string $field): string { $value = trim((string) $value); if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value)) throw UthengaTieErrors::validation([$field => 'A valid identifier is required.']); return strtolower($value); }
    private static function dateTime($value, string $field): DateTimeImmutable { try { $date = new DateTimeImmutable(trim((string) $value)); } catch (Throwable $error) { throw UthengaTieErrors::validation([$field => 'A valid date and time is required.']); } return $date->setTimezone(new DateTimeZone('UTC')); }
    private static function text($value, int $maximum): ?string { if (!is_string($value) && !is_numeric($value)) return null; $value = trim((string) $value); return $value === '' ? null : mb_substr($value, 0, $maximum); }
}

final class UthengaTieTripEngineService
{
    private const FORWARD_TRANSITIONS = [
        'REQUESTED' => ['ASSIGNED', 'CANCELLED'],
        'ASSIGNED' => ['ACCEPTED', 'CANCELLED'],
        'ACCEPTED' => ['EN_ROUTE', 'CANCELLED'],
        'EN_ROUTE' => ['ARRIVED', 'CANCELLED'],
        'ARRIVED' => ['ONBOARD', 'NO_SHOW', 'CANCELLED'],
        'ONBOARD' => ['IN_PROGRESS'],
        'IN_PROGRESS' => ['COMPLETED', 'DISPUTED'],
        'COMPLETED' => ['DISPUTED'],
    ];
    private const CANCELLABLE = ['REQUESTED', 'ASSIGNED', 'ACCEPTED', 'EN_ROUTE', 'ARRIVED'];
    private const ADVANCE_TARGETS = ['EN_ROUTE', 'ARRIVED', 'ONBOARD', 'IN_PROGRESS'];
    // Every tab in the Trips workspace maps to one SQL condition, reused
    // verbatim for both the filtered list and the tab-count summary.
    private const TAB_CONDITIONS = [
        'requested' => "status IN ('REQUESTED','ASSIGNED')",
        'upcoming' => "status='ACCEPTED' AND is_scheduled=1 AND scheduled_at > UTC_TIMESTAMP()",
        'active' => "(status IN ('EN_ROUTE','ARRIVED','ONBOARD','IN_PROGRESS')) OR (status='ACCEPTED' AND NOT (is_scheduled=1 AND scheduled_at > UTC_TIMESTAMP()))",
        'completed' => "status='COMPLETED'",
        'cancelled' => "status IN ('CANCELLED','NO_SHOW','DISPUTED')",
    ];

    public function __construct(private ?PDO $db) {}

    public function dashboard(string $driverUserId): array
    {
        $this->db(); $this->seedIfEmpty($driverUserId);
        $status = $this->driverStatus($driverUserId);
        $active = $this->activeTripRow($driverUserId);
        $next = $this->nextScheduledRow($driverUserId);
        return [
            'schema_version' => 'tie-trip-dashboard/v1',
            'is_online' => $status['is_online'],
            'online_since' => $this->utcIso($status['online_since']),
            'active_trip' => $active ? $this->publicTrip($active) : null,
            'today' => $this->todaySummary($driverUserId),
            'yesterday' => $this->yesterdaySummary($driverUserId),
            'readiness' => $this->readiness($driverUserId),
            'next_scheduled' => $next ? $this->publicTrip($next) : null,
        ];
    }

    public function list(string $driverUserId, array $filters): array
    {
        $this->db(); $this->seedIfEmpty($driverUserId);
        $tab = strtolower((string) ($filters['status'] ?? 'all'));
        $where = ['driver_user_id = ?']; $params = [$driverUserId];
        if (isset(self::TAB_CONDITIONS[$tab])) $where[] = self::TAB_CONDITIONS[$tab];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') { $where[] = '(trip_code LIKE ? OR passenger_name LIKE ? OR passenger_phone LIKE ? OR pickup_location LIKE ? OR destination_location LIKE ?)'; $like = '%' . $q . '%'; array_push($params, $like, $like, $like, $like, $like); }
        $paymentStatus = strtolower((string) ($filters['payment_status'] ?? ''));
        if (in_array($paymentStatus, ['pending', 'paid', 'failed'], true)) { $where[] = 'payment_status = ?'; $params[] = $paymentStatus; }
        $date = (string) ($filters['date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $where[] = 'DATE(COALESCE(scheduled_at, requested_at)) = ?'; $params[] = $date; }
        $sort = match (strtolower((string) ($filters['sort'] ?? 'newest'))) {
            'pickup' => 'COALESCE(scheduled_at, requested_at) ASC',
            'fare' => 'COALESCE(final_fare, estimated_fare, 0) DESC',
            'distance' => 'COALESCE(distance_km, 0) DESC',
            'status' => 'status ASC',
            default => 'requested_at DESC',
        };
        $stmt = $this->db->prepare('SELECT * FROM tie_trips WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $sort . ' LIMIT 200');
        $stmt->execute($params);
        $trips = array_map(fn(array $row): array => $this->publicTrip($row), $stmt->fetchAll());
        return ['schema_version' => 'tie-trip-list/v1', 'trips' => $trips, 'counts' => $this->counts($driverUserId)];
    }

    public function detail(string $tripId, string $driverUserId): array
    {
        $this->db(); $row = $this->tripRow($tripId, $driverUserId);
        $stmt = $this->db->prepare('SELECT event_type, actor_type, previous_status, new_status, reason, created_at FROM tie_trip_events WHERE trip_id=? ORDER BY id ASC');
        $stmt->execute([$tripId]);
        $timeline = array_map(fn(array $event): array => [
            'event_type' => (string) $event['event_type'],
            'actor_type' => (string) $event['actor_type'],
            'previous_status' => $event['previous_status'] ?? null,
            'new_status' => $event['new_status'] ?? null,
            'reason' => $event['reason'] ?? null,
            'created_at' => $this->utcIso($event['created_at']),
        ], $stmt->fetchAll());
        return ['schema_version' => 'tie-trip-detail/v1', 'trip' => $this->publicTrip($row), 'timeline' => $timeline];
    }

    public function createManualTrip(array $input, string $driverUserId): array
    {
        $this->db(); $request = UthengaTieTripEngineContracts::createManualTrip($input); $this->db->beginTransaction();
        try {
            $id = $this->id(); $code = $this->generateTripCode();
            $this->db->prepare('INSERT INTO tie_trips (id, trip_code, driver_user_id, passenger_name, passenger_phone, pickup_location, destination_location, vehicle_label, vehicle_plate, status, is_scheduled, scheduled_at, requested_at, assigned_at, accepted_at, estimated_fare) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),?)')
                ->execute([$id, $code, $driverUserId, $request['passenger_name'], $request['passenger_phone'], $request['pickup_location'], $request['destination_location'], $request['vehicle_label'], $request['vehicle_plate'], 'ACCEPTED', $request['is_scheduled'] ? 1 : 0, $request['scheduled_at']?->format('Y-m-d H:i:s'), $request['estimated_fare']]);
            $this->event($id, 'TRIP_CREATED', 'driver', $driverUserId, null, 'ACCEPTED', null, ['manual' => true]);
            $this->db->commit(); return $this->detail($id, $driverUserId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function acceptTrip(string $tripId, string $driverUserId): array
    {
        $this->db(); $this->db->beginTransaction();
        try {
            $trip = $this->lockedTrip($tripId, $driverUserId); $current = (string) $trip['status'];
            if (!in_array($current, ['REQUESTED', 'ASSIGNED'], true)) throw UthengaTieErrors::validation(['status' => "A trip cannot be accepted from {$current}."]);
            $this->db->prepare("UPDATE tie_trips SET status='ACCEPTED', assigned_at=COALESCE(assigned_at, UTC_TIMESTAMP()), accepted_at=UTC_TIMESTAMP(), version=version+1 WHERE id=?")->execute([$tripId]);
            $this->event($tripId, 'TRIP_ACCEPTED', 'driver', $driverUserId, $current, 'ACCEPTED', null);
            $this->db->commit(); return $this->detail($tripId, $driverUserId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function advance(string $tripId, string $driverUserId, string $targetStatus): array
    {
        $this->db(); $target = strtoupper(trim($targetStatus));
        if (!in_array($target, self::ADVANCE_TARGETS, true)) throw UthengaTieErrors::validation(['status' => 'Unsupported trip advance target.']);
        $this->db->beginTransaction();
        try {
            $trip = $this->lockedTrip($tripId, $driverUserId); $current = (string) $trip['status'];
            if (!in_array($target, self::FORWARD_TRANSITIONS[$current] ?? [], true)) throw UthengaTieErrors::validation(['status' => "A trip cannot move from {$current} to {$target}."]);
            $column = ['EN_ROUTE' => 'en_route_at', 'ARRIVED' => 'arrived_at', 'ONBOARD' => 'onboard_at', 'IN_PROGRESS' => 'started_at'][$target];
            $this->db->prepare("UPDATE tie_trips SET status=?, {$column}=UTC_TIMESTAMP(), version=version+1 WHERE id=?")->execute([$target, $tripId]);
            $this->event($tripId, 'TRIP_' . $target, 'driver', $driverUserId, $current, $target, null);
            $this->db->commit(); return $this->detail($tripId, $driverUserId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function completeTrip(string $tripId, string $driverUserId, array $input): array
    {
        $this->db(); $request = UthengaTieTripEngineContracts::complete($input); $this->db->beginTransaction();
        try {
            $trip = $this->lockedTrip($tripId, $driverUserId); $current = (string) $trip['status'];
            if ($current !== 'IN_PROGRESS') throw UthengaTieErrors::validation(['status' => "A trip cannot be completed from {$current}."]);
            $this->db->prepare("UPDATE tie_trips SET status='COMPLETED', completed_at=UTC_TIMESTAMP(), final_fare=?, payment_method=?, payment_status='paid', distance_km=?, duration_seconds=?, version=version+1 WHERE id=?")
                ->execute([$request['final_fare'], $request['payment_method'], $request['distance_km'], $request['duration_seconds'], $tripId]);
            $this->event($tripId, 'TRIP_COMPLETED', 'driver', $driverUserId, $current, 'COMPLETED', null, ['final_fare' => $request['final_fare'], 'payment_method' => $request['payment_method']]);
            $this->db->commit(); return $this->detail($tripId, $driverUserId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function cancelTrip(string $tripId, string $driverUserId, array $input): array
    {
        $this->db(); $request = UthengaTieTripEngineContracts::cancel($input); $this->db->beginTransaction();
        try {
            $trip = $this->lockedTrip($tripId, $driverUserId); $current = (string) $trip['status'];
            if (!in_array($current, self::CANCELLABLE, true)) throw UthengaTieErrors::validation(['status' => "A trip cannot be cancelled from {$current}."]);
            $this->db->prepare("UPDATE tie_trips SET status='CANCELLED', cancelled_at=UTC_TIMESTAMP(), cancellation_actor='driver', cancellation_reason=?, version=version+1 WHERE id=?")->execute([$request['reason'], $tripId]);
            $this->event($tripId, 'TRIP_CANCELLED', 'driver', $driverUserId, $current, 'CANCELLED', $request['reason']);
            $this->db->commit(); return $this->detail($tripId, $driverUserId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function markNoShow(string $tripId, string $driverUserId): array
    {
        $this->db(); $this->db->beginTransaction();
        try {
            $trip = $this->lockedTrip($tripId, $driverUserId); $current = (string) $trip['status'];
            if ($current !== 'ARRIVED') throw UthengaTieErrors::validation(['status' => 'A no-show can only be recorded once the driver has arrived at pickup.']);
            $this->db->prepare("UPDATE tie_trips SET status='NO_SHOW', cancelled_at=UTC_TIMESTAMP(), cancellation_actor='driver', cancellation_reason='Passenger did not show up.', version=version+1 WHERE id=?")->execute([$tripId]);
            $this->event($tripId, 'TRIP_NO_SHOW', 'driver', $driverUserId, $current, 'NO_SHOW', null);
            $this->db->commit(); return $this->detail($tripId, $driverUserId);
        } catch (Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function setOnlineStatus(string $driverUserId, bool $isOnline): array
    {
        $this->db();
        $current = $this->driverStatus($driverUserId);
        $onlineSince = $isOnline ? ($current['is_online'] ? $current['online_since'] : gmdate('Y-m-d H:i:s')) : null;
        $this->db->prepare('INSERT INTO tie_trip_driver_status (driver_user_id, is_online, online_since) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_online = VALUES(is_online), online_since = VALUES(online_since)')
            ->execute([$driverUserId, $isOnline ? 1 : 0, $onlineSince]);
        return $this->dashboard($driverUserId);
    }

    private function driverStatus(string $driverUserId): array
    {
        $stmt = $this->db->prepare('SELECT is_online, online_since FROM tie_trip_driver_status WHERE driver_user_id=? LIMIT 1');
        $stmt->execute([$driverUserId]); $row = $stmt->fetch();
        return ['is_online' => $row ? (bool) $row['is_online'] : false, 'online_since' => $row['online_since'] ?? null];
    }

    private function activeTripRow(string $driverUserId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tie_trips WHERE driver_user_id=? AND ({$this->tabCondition('active')}) ORDER BY requested_at DESC LIMIT 1");
        $stmt->execute([$driverUserId]); $row = $stmt->fetch(); return is_array($row) ? $row : null;
    }

    private function nextScheduledRow(string $driverUserId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tie_trips WHERE driver_user_id=? AND {$this->tabCondition('upcoming')} ORDER BY scheduled_at ASC LIMIT 1");
        $stmt->execute([$driverUserId]); $row = $stmt->fetch(); return is_array($row) ? $row : null;
    }

    private function tabCondition(string $tab): string { return self::TAB_CONDITIONS[$tab]; }

    private function readiness(string $driverUserId): array
    {
        // driver_profiles is owned by an older, unrelated part of the app and
        // is not guaranteed to exist in every environment (or for every
        // account) — this module only ever reads it, so a missing table or
        // missing row must degrade to "no profile on file", never fail the
        // dashboard.
        try {
            $stmt = $this->db->prepare('SELECT is_verified FROM driver_profiles WHERE user_id=? LIMIT 1');
            $stmt->execute([$driverUserId]); $row = $stmt->fetch();
        } catch (Throwable $error) { $row = null; }
        return ['has_profile' => is_array($row), 'is_verified' => is_array($row) ? (bool) $row['is_verified'] : false];
    }

    private function todaySummary(string $driverUserId): array { return $this->daySummary($driverUserId, 'CURDATE()'); }
    private function yesterdaySummary(string $driverUserId): array { return $this->daySummary($driverUserId, 'CURDATE() - INTERVAL 1 DAY'); }

    private function daySummary(string $driverUserId, string $dateExpr): array
    {
        $stmt = $this->db->prepare("SELECT
            SUM(CASE WHEN DATE(requested_at) = {$dateExpr} THEN 1 ELSE 0 END) AS trips,
            SUM(CASE WHEN status='COMPLETED' AND DATE(completed_at)={$dateExpr} THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status IN ('CANCELLED','NO_SHOW') AND DATE(cancelled_at)={$dateExpr} THEN 1 ELSE 0 END) AS cancelled,
            COALESCE(SUM(CASE WHEN status='COMPLETED' AND DATE(completed_at)={$dateExpr} THEN final_fare ELSE 0 END), 0) AS earnings,
            COALESCE(SUM(CASE WHEN status='COMPLETED' AND DATE(completed_at)={$dateExpr} THEN distance_km ELSE 0 END), 0) AS distance_km
            FROM tie_trips WHERE driver_user_id=?");
        $stmt->execute([$driverUserId]); $row = $stmt->fetch() ?: [];
        return [
            'trips' => (int) ($row['trips'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'earnings' => (float) ($row['earnings'] ?? 0),
            'distance_km' => (float) ($row['distance_km'] ?? 0),
        ];
    }

    private function counts(string $driverUserId): array
    {
        $select = ['COUNT(*) AS all_count'];
        foreach (self::TAB_CONDITIONS as $key => $condition) $select[] = "SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END) AS {$key}_count";
        $stmt = $this->db->prepare('SELECT ' . implode(', ', $select) . ' FROM tie_trips WHERE driver_user_id = ?');
        $stmt->execute([$driverUserId]); $row = $stmt->fetch() ?: [];
        $counts = ['all' => (int) ($row['all_count'] ?? 0)];
        foreach (self::TAB_CONDITIONS as $key => $condition) $counts[$key] = (int) ($row["{$key}_count"] ?? 0);
        return $counts;
    }

    private function bucket(array $row): string
    {
        foreach (['requested', 'upcoming', 'active', 'completed'] as $key) if ($this->matchesBucket($row, $key)) return $key;
        return 'cancelled';
    }

    private function matchesBucket(array $row, string $key): bool
    {
        $status = (string) $row['status'];
        $scheduledInFuture = (int) ($row['is_scheduled'] ?? 0) === 1 && $row['scheduled_at'] !== null && strtotime($row['scheduled_at'] . ' UTC') > time();
        return match ($key) {
            'requested' => in_array($status, ['REQUESTED', 'ASSIGNED'], true),
            'upcoming' => $status === 'ACCEPTED' && $scheduledInFuture,
            'active' => in_array($status, ['EN_ROUTE', 'ARRIVED', 'ONBOARD', 'IN_PROGRESS'], true) || ($status === 'ACCEPTED' && !$scheduledInFuture),
            'completed' => $status === 'COMPLETED',
            default => false,
        };
    }

    private function publicTrip(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'trip_code' => (string) $row['trip_code'],
            'status' => (string) $row['status'],
            'bucket' => $this->bucket($row),
            'passenger_name' => (string) $row['passenger_name'],
            'passenger_phone' => $row['passenger_phone'] !== null ? (string) $row['passenger_phone'] : null,
            'pickup_location' => (string) $row['pickup_location'],
            'destination_location' => (string) $row['destination_location'],
            'vehicle_label' => $row['vehicle_label'] !== null ? (string) $row['vehicle_label'] : null,
            'vehicle_plate' => $row['vehicle_plate'] !== null ? (string) $row['vehicle_plate'] : null,
            'is_scheduled' => (bool) $row['is_scheduled'],
            'scheduled_at' => $this->utcIso($row['scheduled_at'] ?? null),
            'requested_at' => $this->utcIso($row['requested_at']),
            'assigned_at' => $this->utcIso($row['assigned_at'] ?? null),
            'accepted_at' => $this->utcIso($row['accepted_at'] ?? null),
            'en_route_at' => $this->utcIso($row['en_route_at'] ?? null),
            'arrived_at' => $this->utcIso($row['arrived_at'] ?? null),
            'onboard_at' => $this->utcIso($row['onboard_at'] ?? null),
            'started_at' => $this->utcIso($row['started_at'] ?? null),
            'completed_at' => $this->utcIso($row['completed_at'] ?? null),
            'cancelled_at' => $this->utcIso($row['cancelled_at'] ?? null),
            'cancellation_actor' => $row['cancellation_actor'] ?? null,
            'cancellation_reason' => $row['cancellation_reason'] ?? null,
            'estimated_fare' => $row['estimated_fare'] !== null ? (float) $row['estimated_fare'] : null,
            'final_fare' => $row['final_fare'] !== null ? (float) $row['final_fare'] : null,
            'distance_km' => $row['distance_km'] !== null ? (float) $row['distance_km'] : null,
            'duration_seconds' => $row['duration_seconds'] !== null ? (int) $row['duration_seconds'] : null,
            'payment_method' => $row['payment_method'] ?? null,
            'payment_status' => (string) $row['payment_status'],
        ];
    }

    private function seedIfEmpty(string $driverUserId): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM tie_trips WHERE driver_user_id=?'); $stmt->execute([$driverUserId]);
        if ((int) $stmt->fetchColumn() > 0) return;
        try {
            // A first-time driver should see a working console, not an empty
            // shell — mirrors the mock-driver bootstrap in driver-profile.php.
            $seeds = [
                ['Mary Banda', '+265991112233', 'Area 3', 'City Centre', 'COMPLETED', -240, 8500, 8.4, 1320, false],
                ['Grace Moyo', '+265991223344', 'Area 18', 'Old Town', 'COMPLETED', -420, 7500, 6.1, 1080, false],
                ['John Phiri', '+265991334455', 'Area 10', 'Kamuzu International Airport', 'IN_PROGRESS', -15, 14000, null, null, false],
                ['Chikondi Zulu', '+265991445566', 'Umodzi Park', 'Area 25', 'ACCEPTED', 90, 6500, null, null, true],
                ['Thandiwe Nyirenda', '+265991556677', 'Area 47', 'Bwandilo', 'REQUESTED', 5, 5200, null, null, false],
                ['Kondwani Kaunda', '+265991667788', 'Area 12', 'Kanengo', 'CANCELLED', -60, 4800, null, null, false],
            ];
            foreach ($seeds as $index => [$name, $phone, $pickup, $destination, $status, $offsetMinutes, $fare, $distance, $duration, $isScheduled]) {
                $id = $this->id(); $code = $this->generateTripCode();
                $anchor = gmdate('Y-m-d H:i:s', time() + ($offsetMinutes * 60));
                $requestedAt = $offsetMinutes > 0 ? gmdate('Y-m-d H:i:s') : $anchor;
                $has = fn(array $statuses): bool => in_array($status, $statuses, true);
                $this->db->prepare('INSERT INTO tie_trips (id, trip_code, driver_user_id, passenger_name, passenger_phone, pickup_location, destination_location, vehicle_label, vehicle_plate, status, is_scheduled, scheduled_at, requested_at, assigned_at, accepted_at, en_route_at, arrived_at, onboard_at, started_at, completed_at, cancelled_at, cancellation_actor, cancellation_reason, estimated_fare, final_fare, distance_km, duration_seconds, payment_method, payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([
                        $id, $code, $driverUserId, $name, $phone, $pickup, $destination, 'Toyota Corolla', 'MW AX 1234',
                        $status, $isScheduled ? 1 : 0, $isScheduled ? $anchor : null, $requestedAt,
                        $has(['ASSIGNED', 'ACCEPTED', 'EN_ROUTE', 'ARRIVED', 'ONBOARD', 'IN_PROGRESS', 'COMPLETED']) ? $requestedAt : null,
                        $has(['ACCEPTED', 'EN_ROUTE', 'ARRIVED', 'ONBOARD', 'IN_PROGRESS', 'COMPLETED']) ? $requestedAt : null,
                        $has(['EN_ROUTE', 'ARRIVED', 'ONBOARD', 'IN_PROGRESS', 'COMPLETED']) ? $anchor : null,
                        $has(['ARRIVED', 'ONBOARD', 'IN_PROGRESS', 'COMPLETED']) ? $anchor : null,
                        $has(['ONBOARD', 'IN_PROGRESS', 'COMPLETED']) ? $anchor : null,
                        $has(['IN_PROGRESS', 'COMPLETED']) ? $anchor : null,
                        $status === 'COMPLETED' ? $anchor : null,
                        $has(['CANCELLED', 'NO_SHOW']) ? $anchor : null,
                        $has(['CANCELLED', 'NO_SHOW']) ? 'driver' : null,
                        $status === 'CANCELLED' ? 'Passenger requested cancellation.' : ($status === 'NO_SHOW' ? 'Passenger did not show up.' : null),
                        $fare, $status === 'COMPLETED' ? $fare : null, $distance, $duration,
                        $status === 'COMPLETED' ? ($index % 2 === 0 ? 'digital' : 'cash') : null,
                        $status === 'COMPLETED' ? 'paid' : 'pending',
                    ]);
                $this->event($id, 'TRIP_CREATED', 'system', null, null, 'REQUESTED', null, ['seed' => true]);
                if ($status !== 'REQUESTED') $this->event($id, 'TRIP_' . $status, 'system', null, 'REQUESTED', $status, null, ['seed' => true]);
            }
        } catch (Throwable $error) {
            // Demo seeding is best-effort; a real trip history must never be
            // blocked by a failure to seed sample data.
        }
    }

    private function generateTripCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = 'UTH-TX-' . random_int(10000, 99999);
            $stmt = $this->db->prepare('SELECT 1 FROM tie_trips WHERE trip_code=? LIMIT 1'); $stmt->execute([$code]);
            if (!$stmt->fetchColumn()) return $code;
        }
        throw UthengaTieErrors::providerUnavailable('trip_engine');
    }

    private function lockedTrip(string $id, string $driverUserId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tie_trips WHERE id=? AND driver_user_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$id, $driverUserId]); $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['trip_id' => 'Trip not found.']);
        return $row;
    }

    private function tripRow(string $id, string $driverUserId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tie_trips WHERE id=? AND driver_user_id=? LIMIT 1');
        $stmt->execute([$id, $driverUserId]); $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['trip_id' => 'Trip not found.']);
        return $row;
    }

    private function event(string $tripId, string $type, string $actorType, ?string $actorId, ?string $previousStatus, ?string $newStatus, ?string $reason, array $metadata = []): void
    {
        $this->db->prepare('INSERT INTO tie_trip_events (trip_id, event_type, actor_type, actor_id, previous_status, new_status, reason, metadata) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$tripId, $type, $actorType, $actorId, $previousStatus, $newStatus, $reason, $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES)]);
    }

    private function id(): string { $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)); }
    private function utcIso($value): ?string { if (!is_string($value) || trim($value) === '') return null; return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c'); }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('trip_engine'); }
}
