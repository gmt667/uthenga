<?php
/**
 * Quick Taxi Operations — Earnings.
 *
 * There is no platform-commission, bonus, or payout-gateway model anywhere in
 * this codebase (UthengaTiePaymentService handles customer-facing marketplace
 * bookings, not driver payout disbursement) — so this module never invents a
 * commission split, a payout balance, or a "Request Payout" action. Every
 * figure here is a real aggregate — of tie_trips.final_fare for manual/1:1
 * trips, and of tie_transport_payments (state=PAID) for Quick Taxi
 * departures — merged together since both represent real fares this driver
 * collected. The one thing that is genuinely a driver-owned setting rather
 * than a computed figure is the weekly earnings goal.
 */

final class UthengaTieEarningsContracts
{
    public static function period($value): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['today', 'week', 'month'], true) ? $value : 'today';
    }

    public static function goalAmount($value): float
    {
        if (!is_numeric($value) || (float) $value <= 0) throw UthengaTieErrors::validation(['weekly_goal' => 'A valid positive weekly goal is required.']);
        return round((float) $value, 2);
    }
}

final class UthengaTieEarningsService
{
    public function __construct(private ?PDO $db) {}

    public function summary(string $driverUserId, string $period): array
    {
        $this->db(); [$start, $end] = $this->periodRange($period);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS trips, COALESCE(SUM(final_fare), 0) AS earnings, COALESCE(SUM(distance_km), 0) AS distance_km,
             SUM(payment_method = 'digital') AS digital_count, SUM(payment_method = 'cash') AS cash_count
             FROM tie_trips WHERE driver_user_id = ? AND status = 'COMPLETED' AND completed_at >= ? AND completed_at < ?"
        );
        $stmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $row = $stmt->fetch() ?: [];
        $coord = $this->coordinationSummary($driverUserId, $start, $end);

        $cancelled = $this->db->prepare("SELECT COUNT(*) FROM tie_trips WHERE driver_user_id = ? AND status IN ('CANCELLED', 'NO_SHOW') AND requested_at >= ? AND requested_at < ?");
        $cancelled->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);

        $trips = (int) ($row['trips'] ?? 0) + (int) ($coord['trips'] ?? 0);
        $earnings = (float) ($row['earnings'] ?? 0) + (float) ($coord['earnings'] ?? 0);
        return [
            'schema_version' => 'tie-earnings-summary/v1',
            'period' => $period,
            'range' => ['start' => $start->format('c'), 'end' => $end->format('c')],
            'trips' => $trips,
            'cancelled' => (int) $cancelled->fetchColumn(),
            'earnings' => $earnings,
            'average_fare' => $trips > 0 ? $earnings / $trips : 0.0,
            // Quick Taxi departures (tie_transport_runs) don't track a
            // per-passenger distance today — only tie_trips does.
            'distance_km' => (float) ($row['distance_km'] ?? 0),
            'digital_count' => (int) ($row['digital_count'] ?? 0) + (int) ($coord['digital_count'] ?? 0),
            'cash_count' => (int) ($row['cash_count'] ?? 0) + (int) ($coord['cash_count'] ?? 0),
        ];
    }

    public function trend(string $driverUserId, int $days): array
    {
        $this->db(); $days = max(7, min(60, $days));
        $end = (new DateTimeImmutable('tomorrow', new DateTimeZone('UTC')));
        $start = $end->modify("-{$days} days");
        $stmt = $this->db->prepare("SELECT DATE(completed_at) AS d, COALESCE(SUM(final_fare), 0) AS earnings, COUNT(*) AS trips FROM tie_trips WHERE driver_user_id = ? AND status = 'COMPLETED' AND completed_at >= ? AND completed_at < ? GROUP BY DATE(completed_at)");
        $stmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $byDay = [];
        foreach ($stmt->fetchAll() as $row) $byDay[(string) $row['d']] = ['earnings' => (float) $row['earnings'], 'trips' => (int) $row['trips']];

        $coordStmt = $this->db->prepare(
            "SELECT DATE(p.confirmed_at) AS d, COALESCE(SUM(p.amount), 0) AS earnings, COUNT(*) AS trips
             FROM tie_transport_payments p INNER JOIN tie_transport_sessions s ON s.id = p.session_id INNER JOIN tie_transport_runs r ON r.id = s.run_id
             WHERE r.vendor_id = ? AND p.state = 'PAID' AND p.confirmed_at >= ? AND p.confirmed_at < ? GROUP BY DATE(p.confirmed_at)"
        );
        $coordStmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        foreach ($coordStmt->fetchAll() as $row) {
            $key = (string) $row['d'];
            $byDay[$key] = ['earnings' => ($byDay[$key]['earnings'] ?? 0.0) + (float) $row['earnings'], 'trips' => ($byDay[$key]['trips'] ?? 0) + (int) $row['trips']];
        }

        $series = [];
        for ($cursor = $start; $cursor < $end; $cursor = $cursor->modify('+1 day')) {
            $key = $cursor->format('Y-m-d');
            $series[] = ['date' => $key, 'earnings' => $byDay[$key]['earnings'] ?? 0.0, 'trips' => $byDay[$key]['trips'] ?? 0];
        }
        return ['schema_version' => 'tie-earnings-trend/v1', 'days' => $days, 'series' => $series];
    }

    public function transactions(string $driverUserId, string $period): array
    {
        $this->db(); [$start, $end] = $this->periodRange($period);
        $stmt = $this->db->prepare(
            "SELECT id, trip_code, completed_at, pickup_location, destination_location, final_fare, payment_method
             FROM tie_trips WHERE driver_user_id = ? AND status = 'COMPLETED' AND completed_at >= ? AND completed_at < ? ORDER BY completed_at DESC LIMIT 200"
        );
        $stmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $transactions = array_map(fn(array $row): array => [
            'id' => (string) $row['id'],
            'trip_code' => (string) $row['trip_code'],
            'completed_at' => $this->utcIso($row['completed_at']),
            'route' => $row['pickup_location'] . ' → ' . $row['destination_location'],
            'fare' => (float) $row['final_fare'],
            'payment_method' => $row['payment_method'] ?? null,
            'source' => 'trip',
        ], $stmt->fetchAll());

        $coordStmt = $this->db->prepare(
            "SELECT p.id, p.confirmed_at, r.loading_location, p.amount, p.method
             FROM tie_transport_payments p INNER JOIN tie_transport_sessions s ON s.id = p.session_id INNER JOIN tie_transport_runs r ON r.id = s.run_id
             WHERE r.vendor_id = ? AND p.state = 'PAID' AND p.confirmed_at >= ? AND p.confirmed_at < ? ORDER BY p.confirmed_at DESC LIMIT 200"
        );
        $coordStmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        foreach ($coordStmt->fetchAll() as $row) {
            $transactions[] = [
                'id' => (string) $row['id'],
                'trip_code' => 'DEP-' . strtoupper(substr((string) $row['id'], 0, 8)),
                'completed_at' => $this->utcIso($row['confirmed_at']),
                'route' => 'Loading at ' . $row['loading_location'],
                'fare' => (float) $row['amount'],
                'payment_method' => $row['method'] === 'cash' ? 'cash' : 'digital',
                'source' => 'departure',
            ];
        }
        usort($transactions, fn(array $a, array $b) => strcmp($b['completed_at'] ?? '', $a['completed_at'] ?? ''));
        return ['schema_version' => 'tie-earnings-transactions/v1', 'period' => $period, 'transactions' => array_slice($transactions, 0, 200)];
    }

    public function goal(string $driverUserId): array
    {
        $this->db();
        $stmt = $this->db->prepare('SELECT weekly_goal FROM tie_trip_earnings_goals WHERE driver_user_id = ?'); $stmt->execute([$driverUserId]);
        $goal = $stmt->fetchColumn();
        [$start, $end] = $this->periodRange('week');
        $earned = $this->db->prepare("SELECT COALESCE(SUM(final_fare), 0) FROM tie_trips WHERE driver_user_id = ? AND status = 'COMPLETED' AND completed_at >= ? AND completed_at < ?");
        $earned->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $coord = $this->coordinationSummary($driverUserId, $start, $end);
        return ['schema_version' => 'tie-earnings-goal/v1', 'weekly_goal' => $goal !== false ? (float) $goal : null, 'week_earnings' => (float) $earned->fetchColumn() + (float) ($coord['earnings'] ?? 0)];
    }

    // Quick Taxi departures' paid ledger (tie_transport_payments), scoped to
    // this driver as the run's vendor — folded into every earnings figure
    // alongside the older tie_trips model, per the Phase 4 data-model merge.
    private function coordinationSummary(string $driverUserId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS trips, COALESCE(SUM(p.amount), 0) AS earnings, SUM(p.method != 'cash') AS digital_count, SUM(p.method = 'cash') AS cash_count
             FROM tie_transport_payments p INNER JOIN tie_transport_sessions s ON s.id = p.session_id INNER JOIN tie_transport_runs r ON r.id = s.run_id
             WHERE r.vendor_id = ? AND p.state = 'PAID' AND p.confirmed_at >= ? AND p.confirmed_at < ?"
        );
        $stmt->execute([$driverUserId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        return $stmt->fetch() ?: [];
    }

    public function setGoal(string $driverUserId, array $input): array
    {
        $this->db(); $amount = UthengaTieEarningsContracts::goalAmount($input['weekly_goal'] ?? null);
        $this->db->prepare('INSERT INTO tie_trip_earnings_goals (driver_user_id, weekly_goal) VALUES (?, ?) ON DUPLICATE KEY UPDATE weekly_goal = VALUES(weekly_goal)')->execute([$driverUserId, $amount]);
        return $this->goal($driverUserId);
    }

    private function periodRange(string $period): array
    {
        $today = (new DateTimeImmutable('today', new DateTimeZone('UTC')));
        // ISO-8601 day-of-week (1=Monday..7=Sunday) computed explicitly —
        // PHP's "monday this week" relative format is ambiguous when today
        // already is Monday, so this is done with plain arithmetic instead.
        $monday = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
        return match (UthengaTieEarningsContracts::period($period)) {
            'week' => [$monday, $monday->modify('+7 days')],
            'month' => [$today->modify('first day of this month'), $today->modify('first day of next month')],
            default => [$today, $today->modify('+1 day')],
        };
    }

    private function utcIso($value): ?string { if (!is_string($value) || trim($value) === '') return null; return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c'); }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('earnings'); }
}
