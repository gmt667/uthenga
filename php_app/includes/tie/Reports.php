<?php
/**
 * Quick Taxi Operations — Reports.
 *
 * A consolidated, exportable view across an arbitrary date range, built
 * entirely from data the other Quick Taxi workspaces already write
 * (tie_trips, tie_transport_payments, tie_driver_shift_sessions,
 * tie_vehicle_maintenance, tie_driver_vehicles) — no new schema, no
 * fabricated ratings/on-time scoring, and no invented "generated report
 * archive" (no PDF/file storage exists in this codebase; CSV export happens
 * client-side like every other Quick Taxi workspace). The 'earnings' report
 * folds in real Quick Taxi departure payments alongside tie_trips fares
 * (Phase 4 data-model merge); 'trips'/'shifts'/'vehicle' stay scoped to
 * their original single source, since a multi-passenger departure manifest
 * doesn't fit a single-trip row shape.
 */

final class UthengaTieReportsContracts
{
    public static function type($value): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['trips', 'earnings', 'shifts', 'vehicle'], true) ? $value : 'trips';
    }

    public static function range(array $input): array
    {
        $start = self::date($input['start'] ?? null, 'start');
        $end = self::date($input['end'] ?? null, 'end');
        if ($start > $end) throw UthengaTieErrors::validation(['end' => 'End date must be on or after the start date.']);
        return [$start, $end->modify('+1 day')];
    }

    private static function date($value, string $field): DateTimeImmutable
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) throw UthengaTieErrors::validation([$field => 'A valid date (YYYY-MM-DD) is required.']);
        try { return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTime(0, 0); } catch (Throwable $error) { throw UthengaTieErrors::validation([$field => 'A valid date is required.']); }
    }
}

final class UthengaTieReportsService
{
    public function __construct(private ?PDO $db) {}

    public function report(string $driverUserId, string $type, array $input): array
    {
        $this->db(); $type = UthengaTieReportsContracts::type($type); [$start, $end] = UthengaTieReportsContracts::range($input);
        $payload = match ($type) {
            'earnings' => $this->earningsReport($driverUserId, $start, $end),
            'shifts' => $this->shiftReport($driverUserId, $start, $end),
            'vehicle' => $this->vehicleReport($driverUserId, $start, $end),
            default => $this->tripReport($driverUserId, $start, $end),
        };
        return array_merge(['schema_version' => 'tie-report/v1', 'type' => $type, 'range' => ['start' => $start->format('Y-m-d'), 'end' => $end->modify('-1 day')->format('Y-m-d')]], $payload);
    }

    private function tripSummary(string $driverUserId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS trips, SUM(status = 'COMPLETED') AS completed, SUM(status IN ('CANCELLED', 'NO_SHOW')) AS cancelled, SUM(status = 'DISPUTED') AS disputed,
             COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN final_fare ELSE 0 END), 0) AS gross_earnings,
             COALESCE(AVG(CASE WHEN status = 'COMPLETED' THEN final_fare END), 0) AS average_fare,
             COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN distance_km ELSE 0 END), 0) AS distance_km,
             COALESCE(AVG(CASE WHEN status = 'COMPLETED' THEN duration_seconds END), 0) AS average_duration_seconds
             FROM tie_trips WHERE driver_user_id = ? AND requested_at >= ? AND requested_at < ?"
        );
        $stmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $row = $stmt->fetch() ?: [];
        $decided = (int) ($row['completed'] ?? 0) + (int) ($row['cancelled'] ?? 0) + (int) ($row['disputed'] ?? 0);
        return [
            'trips' => (int) ($row['trips'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'disputed' => (int) ($row['disputed'] ?? 0),
            'completion_rate' => $decided > 0 ? round(((int) $row['completed']) / $decided * 100, 1) : null,
            'gross_earnings' => (float) ($row['gross_earnings'] ?? 0),
            'average_fare' => (float) ($row['average_fare'] ?? 0),
            'distance_km' => (float) ($row['distance_km'] ?? 0),
            'average_duration_seconds' => (int) round((float) ($row['average_duration_seconds'] ?? 0)),
        ];
    }

    private function onlineSummary(string $driverUserId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS shifts, COALESCE(SUM(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, UTC_TIMESTAMP()))), 0) AS online_minutes
             FROM tie_driver_shift_sessions WHERE driver_user_id = ? AND started_at >= ? AND started_at < ?'
        );
        $stmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $row = $stmt->fetch() ?: [];
        return ['shifts' => (int) ($row['shifts'] ?? 0), 'online_minutes' => (int) ($row['online_minutes'] ?? 0)];
    }

    private function tripReport(string $driverUserId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, trip_code, requested_at, pickup_location, destination_location, distance_km, duration_seconds, status, final_fare, estimated_fare, payment_method
             FROM tie_trips WHERE driver_user_id = ? AND requested_at >= ? AND requested_at < ? ORDER BY requested_at DESC LIMIT 500'
        );
        $stmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $rows = array_map(fn(array $row): array => [
            'id' => (string) $row['id'], 'trip_code' => (string) $row['trip_code'], 'requested_at' => $this->utcIso($row['requested_at']),
            'route' => $row['pickup_location'] . ' → ' . $row['destination_location'], 'distance_km' => $row['distance_km'] !== null ? (float) $row['distance_km'] : null,
            'duration_seconds' => $row['duration_seconds'] !== null ? (int) $row['duration_seconds'] : null, 'status' => (string) $row['status'],
            'fare' => $row['final_fare'] !== null ? (float) $row['final_fare'] : ($row['estimated_fare'] !== null ? (float) $row['estimated_fare'] : null),
            'payment_method' => $row['payment_method'] ?? null,
        ], $stmt->fetchAll());
        return ['summary' => array_merge($this->tripSummary($driverUserId, $start, $end), $this->onlineSummary($driverUserId, $start, $end)), 'rows' => $rows];
    }

    private function earningsReport(string $driverUserId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, trip_code, completed_at, pickup_location, destination_location, final_fare, payment_method
             FROM tie_trips WHERE driver_user_id = ? AND status = 'COMPLETED' AND completed_at >= ? AND completed_at < ? ORDER BY completed_at DESC LIMIT 500"
        );
        $stmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $rows = array_map(fn(array $row): array => [
            'id' => (string) $row['id'], 'trip_code' => (string) $row['trip_code'], 'completed_at' => $this->utcIso($row['completed_at']),
            'route' => $row['pickup_location'] . ' → ' . $row['destination_location'], 'fare' => (float) $row['final_fare'], 'payment_method' => $row['payment_method'] ?? null, 'source' => 'trip',
        ], $stmt->fetchAll());

        // Quick Taxi departures (tie_transport_payments) contribute a second,
        // real earnings stream — folded in per the Phase 4 data-model merge.
        $coordStmt = $this->db->prepare(
            "SELECT p.id, p.confirmed_at, r.loading_location, p.amount, p.method
             FROM tie_transport_payments p INNER JOIN tie_transport_sessions s ON s.id = p.session_id INNER JOIN tie_transport_runs r ON r.id = s.run_id
             WHERE r.vendor_id = ? AND p.state = 'PAID' AND p.confirmed_at >= ? AND p.confirmed_at < ? ORDER BY p.confirmed_at DESC LIMIT 500"
        );
        $coordStmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $coordAmount = 0.0; $coordCount = 0;
        foreach ($coordStmt->fetchAll() as $row) {
            $coordAmount += (float) $row['amount']; $coordCount++;
            $rows[] = [
                'id' => (string) $row['id'], 'trip_code' => 'DEP-' . strtoupper(substr((string) $row['id'], 0, 8)), 'completed_at' => $this->utcIso($row['confirmed_at']),
                'route' => 'Loading at ' . $row['loading_location'], 'fare' => (float) $row['amount'], 'payment_method' => $row['method'] === 'cash' ? 'cash' : 'digital', 'source' => 'departure',
            ];
        }
        usort($rows, fn(array $a, array $b) => strcmp($b['completed_at'] ?? '', $a['completed_at'] ?? ''));

        $summary = $this->tripSummary($driverUserId, $start, $end);
        $summary['trips'] += $coordCount;
        $summary['completed'] += $coordCount;
        $summary['gross_earnings'] += $coordAmount;
        $decided = $summary['completed'] + $summary['cancelled'] + $summary['disputed'];
        $summary['completion_rate'] = $decided > 0 ? round($summary['completed'] / $decided * 100, 1) : null;
        $summary['average_fare'] = ($summary['completed'] > 0) ? $summary['gross_earnings'] / $summary['completed'] : 0.0;

        return ['summary' => $summary, 'rows' => array_slice($rows, 0, 500)];
    }

    private function shiftReport(string $driverUserId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $stmt = $this->db->prepare('SELECT id, started_at, ended_at, trips_count, earnings FROM tie_driver_shift_sessions WHERE driver_user_id = ? AND started_at >= ? AND started_at < ? ORDER BY started_at DESC LIMIT 200');
        $stmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $rows = array_map(function (array $row): array {
            $endedAt = $row['ended_at'] ?? null;
            $minutes = $endedAt !== null ? max(0, (int) round((strtotime($endedAt . ' UTC') - strtotime($row['started_at'] . ' UTC')) / 60)) : null;
            return ['id' => (int) $row['id'], 'started_at' => $this->utcIso($row['started_at']), 'ended_at' => $this->utcIso($endedAt), 'duration_minutes' => $minutes, 'trips_count' => (int) ($row['trips_count'] ?? 0), 'earnings' => (float) ($row['earnings'] ?? 0)];
        }, $stmt->fetchAll());
        return ['summary' => $this->onlineSummary($driverUserId, $start, $end), 'rows' => $rows];
    }

    private function vehicleReport(string $driverUserId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $trip = $this->db->prepare("SELECT COUNT(*) AS trips, COALESCE(SUM(distance_km), 0) AS distance_km FROM tie_trips WHERE driver_user_id = ? AND status = 'COMPLETED' AND completed_at >= ? AND completed_at < ?");
        $trip->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $tripRow = $trip->fetch() ?: [];

        $maintenance = $this->db->prepare('SELECT id, service_type, serviced_at, mileage_km, notes FROM tie_vehicle_maintenance WHERE driver_user_id = ? AND serviced_at >= ? AND serviced_at < ? ORDER BY serviced_at DESC LIMIT 100');
        $maintenance->execute([$driverUserId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
        $maintenanceRows = array_map(fn(array $row): array => ['id' => (int) $row['id'], 'service_type' => (string) $row['service_type'], 'serviced_at' => $row['serviced_at'], 'mileage_km' => $row['mileage_km'] !== null ? (int) $row['mileage_km'] : null, 'notes' => $row['notes'] ?? null], $maintenance->fetchAll());

        $vehicle = $this->db->prepare('SELECT make_model, plate_number, current_mileage_km FROM tie_driver_vehicles WHERE driver_user_id = ?'); $vehicle->execute([$driverUserId]); $vehicleRow = $vehicle->fetch();

        return [
            'summary' => [
                'trips' => (int) ($tripRow['trips'] ?? 0),
                'distance_km' => (float) ($tripRow['distance_km'] ?? 0),
                'maintenance_events' => count($maintenanceRows),
                'vehicle' => is_array($vehicleRow) ? ['make_model' => (string) $vehicleRow['make_model'], 'plate_number' => (string) $vehicleRow['plate_number'], 'current_mileage_km' => $vehicleRow['current_mileage_km'] !== null ? (int) $vehicleRow['current_mileage_km'] : null] : null,
            ],
            'rows' => $maintenanceRows,
        ];
    }

    private function utcIso($value): ?string { if (!is_string($value) || trim($value) === '') return null; return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c'); }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('reports'); }
}
