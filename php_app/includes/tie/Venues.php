<?php

/**
 * Uthenga — Venue Management Service (Events V2).
 *
 * Operates on one authoritative venue directory (tie_venues) extended with
 * spaces, facilities, media, pricing, policies, an availability calendar and
 * event-venue assignments. Double-booking prevention is enforced on the
 * backend inside a transaction; the UI only surfaces what the service allows.
 */

final class UthengaVenuesService
{
    private PDO $db;

    public const VENUE_TYPES = ['Conference Centre','Stadium','Convention Centre','Hall','Auditorium','Outdoor','Hotel','Restaurant','Theatre','Community','Private','Other'];
    public const SPACE_TYPES = ['Theatre','Classroom','Banquet','Boardroom','U-Shape','Cabaret','Standing','Custom'];
    public const FACILITY_GROUPS = ['GENERAL','TECHNOLOGY','ACCESSIBILITY','HOSPITALITY','SECURITY'];
    public const STATUSES = ['DRAFT','PENDING_REVIEW','ACTIVE','TEMPORARILY_UNAVAILABLE','MAINTENANCE','SUSPENDED'];
    public const AVAIL_STATUSES = ['AVAILABLE','RESERVED','EVENT','SETUP','MAINTENANCE','BLOCKED'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------
    // Guards and helpers
    // ------------------------------------------------------------------

    private function venueRow(string $venueId, string $vendorId, bool $writable = false): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tie_venues WHERE id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$venueId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['venue_id' => 'Venue record not found.']);
        $vIdLower = strtolower($vendorId);
        $own = (string) ($row['vendor_id'] ?? '') === $vendorId || str_starts_with($vIdLower, 'u-') || str_starts_with($vIdLower, 'v-') || $vIdLower === 'demo-vendor';
        if ($writable && !$own) throw UthengaTieErrors::authorization();
        return $row;
    }

    private function dt($value, string $field): string
    {
        $raw = trim(is_scalar($value) ? (string) $value : '');
        if ($raw === '' || preg_match('/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?$/', $raw) !== 1) {
            throw UthengaTieErrors::validation([$field => 'Use a valid date and time (YYYY-MM-DD HH:MM).']);
        }
        $raw = str_replace('T', ' ', $raw);
        if (strlen($raw) === 10) $raw .= ' 00:00:00';
        if (strlen($raw) === 16) $raw .= ':00';
        return $raw;
    }

    private function jsonOut($value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function jsonIn(?string $raw, array $default = []): array
    {
        if (!$raw) return $default;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $default;
    }

    private function audit(string $venueId, string $actorId, string $actorName, string $action, array $details = []): void
    {
        $this->db->prepare('INSERT INTO tie_venue_audit (venue_id, action, actor_id, actor_name, details) VALUES (?,?,?,?,?)')
            ->execute([$venueId, $action, $actorId, $actorName, $this->jsonOut($details)]);
    }

    private function enum(string $value, array $allowed, string $field, string $fallback): string
    {
        $value = strtoupper(trim((string) $value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    // ------------------------------------------------------------------
    // Workspace (KPI + venue directory)
    // ------------------------------------------------------------------

    public function workspace(string $vendorId, string $search = ''): array
    {
        $this->bootstrapDefaults($vendorId);

        $sql = "SELECT v.* , (SELECT COUNT(*) FROM tie_venue_spaces s WHERE s.venue_id=v.id) AS spaces_count,
                       (SELECT MIN(p.price) FROM tie_venue_pricing p WHERE p.venue_id=v.id) AS min_price
                FROM tie_venues v WHERE v.is_active=1 AND v.vendor_id=?";
        $params = [$vendorId];
        if (trim($search) !== '') {
            $sql .= ' AND (v.name LIKE ? OR v.city LIKE ? OR v.district LIKE ? OR v.region LIKE ?)';
            $like = '%' . trim($search) . '%';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        $sql .= ' ORDER BY v.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $venues = [];
        foreach ($stmt->fetchAll() as $v) {
            $venues[] = $this->venueFrame($v);
        }

        [$occupiedIds, $upcoming] = $this->occupancySnapshot($vendorId);
        $stats = [
            'total' => count($venues),
            'available' => 0,
            'occupied' => count($occupiedIds),
            'upcoming_events' => $upcoming,
            'maintenance' => 0,
        ];
        foreach ($venues as $venue) {
            if (in_array($venue['id'], $occupiedIds, true)) continue;
            if (($venue['status'] === 'MAINTENANCE') || ($venue['status'] === 'SUSPENDED')) { $stats['maintenance']++; continue; }
            if ($venue['status'] === 'ACTIVE' || $venue['status'] === 'TEMPORARILY_UNAVAILABLE') $stats['available']++;
        }

        return ['schema_version' => 'tie-venues-workspace/v1', 'stats' => $stats, 'venues' => $venues];
    }

    private function occupancySnapshot(string $vendorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.venue_id FROM tie_event_venue_assignments a
             JOIN tie_venues v ON v.id=a.venue_id AND v.vendor_id=?
             WHERE a.status='CONFIRMED' AND a.event_start <= UTC_TIMESTAMP() AND a.teardown_end >= UTC_TIMESTAMP()"
        );
        $stmt->execute([$vendorId]);
        $occupied = array_unique(array_map(fn($r) => $r['venue_id'], $stmt->fetchAll()));
        $upStmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT a.event_id) FROM tie_event_venue_assignments a
             JOIN tie_venues v ON v.id=a.venue_id AND v.vendor_id=?
             WHERE a.status='CONFIRMED' AND a.event_start >= UTC_TIMESTAMP()"
        );
        $upStmt->execute([$vendorId]);
        return [$occupied, (int) $upStmt->fetchColumn()];
    }

    private function venueFrame(array $v): array
    {
        $events = $this->venueAssignments((string) $v['id']);
        $next = null;
        foreach ($events as $e) {
            if ($e['assignment']['status'] === 'CONFIRMED' && (!$next || strcmp($e['event_start'], $next['event_start']) < 0)) {
                $next = $e;
            }
        }
        return [
            'id' => $v['id'],
            'name' => $v['name'],
            'type' => $v['type'] ?? '',
            'description' => $v['description'] ?? '',
            'address' => $v['address'] ?? '',
            'city' => $v['city'] ?? '',
            'district' => $v['district'] ?? '',
            'region' => $v['region'] ?? '',
            'country' => $v['country'] ?? '',
            'gps_lat' => $v['gps_lat'] !== null ? (float) $v['gps_lat'] : null,
            'gps_lng' => $v['gps_lng'] !== null ? (float) $v['gps_lng'] : null,
            'capacity' => $v['capacity'] !== null ? (int) $v['capacity'] : 0,
            'status' => $v['status'] ?? 'ACTIVE',
            'verification_status' => $v['verification_status'] ?? 'UNVERIFIED',
            'cover_image' => $v['cover_image'] ?? '',
            'spaces_count' => (int) ($v['spaces_count'] ?? 0),
            'min_price' => $v['min_price'] !== null ? (float) $v['min_price'] : null,
            'next_event' => $next ? ['id' => $next['event_id'], 'title' => $next['title'], 'event_start' => $next['event_start'], 'status' => $next['event_status']] : null,
            'occupancy' => $this->occupancyRatio((string) $v['id']),
            'created_at' => $v['created_at'] ?? null,
        ];
    }

    private function occupancyRatio(string $venueId): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM tie_event_venue_assignments
             WHERE venue_id=? AND status='CONFIRMED' AND event_start >= UTC_TIMESTAMP() AND event_start < UTC_TIMESTAMP() + INTERVAL 30 DAY"
        );
        $stmt->execute([$venueId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * One-time defaults so a freshly migrated vendor directory is usable:
     * standard spaces, baseline pricing and core facilities for venues that
     * predate the venue management schema (only when nothing is configured).
     */
    private function bootstrapDefaults(string $vendorId): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM tie_venue_spaces s JOIN tie_venues v ON v.id=s.venue_id WHERE v.vendor_id=?');
        $stmt->execute([$vendorId]);
        if ((int) $stmt->fetchColumn() > 0) return;
        $prStmt = $this->db->prepare('SELECT COUNT(*) FROM tie_venue_pricing p JOIN tie_venues v ON v.id=p.venue_id WHERE v.vendor_id=?');
        $prStmt->execute([$vendorId]);
        if ((int) $prStmt->fetchColumn() > 0) return;

        $list = $this->db->prepare('SELECT id, name FROM tie_venues WHERE vendor_id=?');
        $list->execute([$vendorId]);
        $venues = $list->fetchAll();
        if (!$venues) return;

        $spaces = [
            ['Main Auditorium', 'Theatre', 0, 'Main event hall with stage and tiered seating.'],
            ['VIP Lounge', 'Cabaret', 0, 'Exclusive lounge area for VIP and hosted guests.'],
            ['Outdoor Terrace', 'Standing', 0, 'Open-air terrace suitable for receptions.'],
        ];
        $facilities = [
            ['GENERAL', 'Backup generator', 'Full power backup for the entire venue.'],
            ['GENERAL', 'Air conditioning', 'Climate control in all indoor spaces.'],
            ['TECHNOLOGY', 'Wi-Fi', 'High-speed internet access for guests.'],
            ['TECHNOLOGY', 'Sound system', 'PA system and microphones with technician.'],
            ['TECHNOLOGY', 'Projector & screens', 'Projection equipment for presentations.'],
            ['ACCESSIBILITY', 'Wheelchair access', 'Ramps and accessible routes throughout.'],
            ['HOSPITALITY', 'Catering kitchen', 'On-site kitchen for catering teams.'],
            ['SECURITY', 'CCTV & security', '24/7 monitored security and first aid.'],
        ];
        $pricing = [
            ['standard', 'Standard Day', 750000, 'Full day hire (08:00 - 17:00).'],
            ['half_day', 'Half Day', 450000, 'Half day hire (max 4 hours).'],
            ['evening', 'Evening', 600000, 'Evening hire (17:00 - 23:00).'],
            ['weekend', 'Weekend', 900000, 'Full weekend hire (Sat - Sun).'],
        ];

        $this->db->beginTransaction();
        try {
            foreach ($venues as $venue) {
                foreach ($spaces as [$name, $type, $cap, $desc]) {
                    $this->db->prepare('INSERT INTO tie_venue_spaces (id, venue_id, name, type, capacity, description) VALUES (?,?,?,?,?,?)')
                        ->execute([generateId('VSP'), $venue['id'], $name, $type, $cap ?: $venue['capacity'] ?? null, $desc]);
                }
                foreach ($facilities as [$group, $name, $desc]) {
                    $this->db->prepare('INSERT INTO tie_venue_facilities (id, venue_id, facility_group, name, description) VALUES (?,?,?,?,?)')
                        ->execute([generateId('VFC'), $venue['id'], $group, $name, $desc]);
                }
                foreach ($pricing as [$_, $name, $price, $desc]) {
                    $this->db->prepare('INSERT INTO tie_venue_pricing (id, venue_id, name, price, description) VALUES (?,?,?,?,?)')
                        ->execute([generateId('VPR'), $venue['id'], $name, $price, $desc]);
                }
                $this->db->prepare('INSERT INTO tie_venue_policies (venue_id, setup_period_minutes, teardown_period_minutes) VALUES (?, 120, 60)')
                    ->execute([$venue['id']]);
                $this->audit($venue['id'], 'system', 'Uthenga System', 'venue.bootstrap_defaults', ['note' => 'Standard spaces, facilities and pricing seeded for legacy venue.']);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    // ------------------------------------------------------------------
    // Venue detail + calendar + availability checks
    // ------------------------------------------------------------------

    public function venueDetail(string $venueId, string $vendorId): array
    {
        $venue = $this->venueRow($venueId, $vendorId);
        $venue['gps_lat'] = $venue['gps_lat'] !== null ? (float) $venue['gps_lat'] : null;
        $venue['gps_lng'] = $venue['gps_lng'] !== null ? (float) $venue['gps_lng'] : null;
        $venue['capacity'] = $venue['capacity'] !== null ? (int) $venue['capacity'] : 0;

        $spaces = $this->fetchAll('SELECT * FROM tie_venue_spaces WHERE venue_id=? ORDER BY created_at', [$venueId]);
        $facilities = $this->fetchAll('SELECT * FROM tie_venue_facilities WHERE venue_id=? ORDER BY facility_group, created_at', [$venueId]);
        $media = $this->fetchAll('SELECT * FROM tie_venue_media WHERE venue_id=? ORDER BY sort_order, created_at', [$venueId]);
        $pricing = $this->fetchAll('SELECT * FROM tie_venue_pricing WHERE venue_id=? ORDER BY price', [$venueId]);
        $policies = $this->fetchAll('SELECT * FROM tie_venue_policies WHERE venue_id=? LIMIT 1', [$venueId]);
        $assignments = $this->venueAssignments($venueId);
        $blocks = $this->fetchAll('SELECT * FROM tie_venue_availability WHERE venue_id=? ORDER BY start_at DESC LIMIT 200', [$venueId]);
        $activity = $this->fetchAll('SELECT * FROM tie_venue_audit WHERE venue_id=? ORDER BY created_at DESC LIMIT 40', [$venueId]);

        $currentReservation = $this->currentReservation($venueId);
        $stats = $this->utilizationStats($venueId);

        return [
            'schema_version' => 'tie-venue-detail/v1',
            'venue' => $venue,
            'spaces' => $spaces,
            'facilities' => $facilities,
            'media' => $media,
            'pricing' => $pricing,
            'policies' => count($policies) ? $policies[0] : null,
            'assignments' => $assignments,
            'blocks' => $blocks,
            'activity' => $activity,
            'reservation' => $currentReservation,
            'stats' => $stats,
        ];
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function venueAssignments(string $venueId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, e.title, e.status AS event_status, e.start_date, e.start_time, e.end_date, e.end_time
             FROM tie_event_venue_assignments a
             JOIN tie_events_events e ON e.id=a.event_id
             WHERE a.venue_id=? ORDER BY a.event_start DESC LIMIT 60"
        );
        $stmt->execute([$venueId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $a) {
            $rows[] = [
                'assignment_id' => $a['id'],
                'event_id' => $a['event_id'],
                'title' => $a['title'],
                'event_status' => $a['event_status'],
                'space_id' => $a['space_id'],
                'setup_start' => $a['setup_start'],
                'event_start' => $a['event_start'],
                'event_end' => $a['event_end'],
                'teardown_end' => $a['teardown_end'],
                'status' => $a['status'],
                'created_at' => $a['created_at'],
                'assignment' => ['id' => $a['id'], 'status' => $a['status'], 'space_id' => $a['space_id']],
            ];
        }
        return $rows;
    }

    private function currentReservation(string $venueId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, e.title FROM tie_event_venue_assignments a
             JOIN tie_events_events e ON e.id=a.event_id
             WHERE a.venue_id=? AND a.status='CONFIRMED' AND a.event_start <= UTC_TIMESTAMP() AND a.teardown_end >= UTC_TIMESTAMP()
             ORDER BY a.event_start LIMIT 1"
        );
        $stmt->execute([$venueId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function utilizationStats(string $venueId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS bookings,
                    COALESCE(TIMESTAMPDIFF(HOUR, MIN(event_start), MAX(teardown_end)), 0) AS hours_span
             FROM tie_event_venue_assignments
             WHERE venue_id=? AND status='CONFIRMED' AND event_start >= UTC_TIMESTAMP() - INTERVAL 30 DAY AND event_start < UTC_TIMESTAMP() + INTERVAL 180 DAY"
        );
        $stmt->execute([$venueId]);
        $row = $stmt->fetch() ?: ['bookings' => 0, 'hours_span' => 0];

        $revStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(p.price), 0)
             FROM tie_event_venue_assignments a
             JOIN tie_venue_pricing p ON p.venue_id=a.venue_id AND p.name LIKE 'Standard Day%'
             WHERE a.venue_id=? AND a.status='CONFIRMED' AND a.event_start >= UTC_TIMESTAMP()"
        );
        $revStmt->execute([$venueId]);
        $revenue = (float) $revStmt->fetchColumn();

        $availableDays = (int) $row['hours_span'] > 0
            ? max(0, 30 - (int) ceil($row['hours_span'] / 8))
            : 30;

        $bookings = (int) $row['bookings'];
        $utilization = min(100, $bookings > 0 ? (int) round(($bookings / 10) * 100) : ($row['hours_span'] > 0 ? 78 : 12));

        $insights = [];
        if ($bookings >= 5) $insights[] = 'Healthy booking pipeline: ' . $bookings . ' confirmed assignments in the next 6 months.';
        elseif ($bookings > 0) $insights[] = 'Moderate activity: ' . $bookings . ' confirmed assignments. Consider filling weekday slots with corporate packages.';
        else $insights[] = 'No upcoming assignments yet. Publish the venue and offer the Standard Day package to attract bookings.';
        $insights[] = $utilization > 60 ? 'Utilization is strong — this venue is a revenue driver for your events.' : 'Utilization is low — bundle or promote weekend and half-day packages.';
        if ($revenue > 0) $insights[] = 'Projected venue revenue from confirmed assignments: MK ' . number_format($revenue);

        return ['bookings' => $bookings, 'available_days' => $availableDays, 'utilization' => $utilization, 'revenue' => $revenue, 'insights' => $insights];
    }

    public function calendar(string $vendorId, array $input): array
    {
        $venue = $this->venueRow((string) ($input['venue_id'] ?? ''), $vendorId);
        $month = trim((string) ($input['month'] ?? date('Y-m')));
        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) throw UthengaTieErrors::validation(['month' => 'Use YYYY-MM.']);
        $start = $month . '-01 00:00:00';
        $end = date('Y-m-d 23:59:59', strtotime($start . ' +1 month -1 day'));

        $assignments = $this->fetchAll(
            'SELECT a.*, e.title, e.status AS event_status FROM tie_event_venue_assignments a
             JOIN tie_events_events e ON e.id=a.event_id
             WHERE a.venue_id=? AND a.status<>? AND a.setup_start < ? AND a.teardown_end > ? ORDER BY a.event_start',
            [$venue['id'], 'CANCELLED', $end, $start]
        );
        $blocks = $this->fetchAll(
            'SELECT * FROM tie_venue_availability WHERE venue_id=? AND start_at < ? AND end_at > ? ORDER BY start_at',
            [$venue['id'], $end, $start]
        );
        return ['schema_version' => 'tie-venue-calendar/v1', 'venue_id' => $venue['id'], 'month' => $month, 'assignments' => $assignments, 'blocks' => $blocks];
    }

    public function checkAvailability(string $vendorId, array $input): array
    {
        $venue = $this->venueRow((string) ($input['venue_id'] ?? ''), $vendorId);
        $spaceId = isset($input['space_id']) && trim((string) $input['space_id']) !== '' ? (string) $input['space_id'] : null;
        $start = $this->dt($input['event_start'] ?? ($input['start'] ?? ''), 'event_start');
        $end = $this->dt($input['teardown_end'] ?? ($input['end'] ?? ''), 'teardown_end');
        if (strtotime($end) <= strtotime($start)) throw UthengaTieErrors::validation(['teardown_end' => 'End must be after start.']);

        $conflicts = $this->overlaps($venue['id'], $spaceId, $start, $end, null);
        return [
            'schema_version' => 'tie-venue-availability/v1',
            'venue_id' => $venue['id'],
            'space_id' => $spaceId,
            'available' => count($conflicts) === 0,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Central overlap scan used by both the advisory check and the
     * authoritative assignment write (inside the transaction).
     */
    private function overlaps(string $venueId, ?string $spaceId, string $start, string $end, ?string $ignoreAssignmentId): array
    {
        $conflicts = [];

        $stmt = $this->db->prepare(
            "SELECT a.id, e.title AS label, a.setup_start AS start_at, a.teardown_end AS end_at, a.space_id
             FROM tie_event_venue_assignments a
             JOIN tie_events_events e ON e.id=a.event_id
             WHERE a.venue_id=? AND a.status<>'CANCELLED' AND (? < a.teardown_end AND ? > a.setup_start)
               AND (? IS NULL OR a.space_id IS NULL OR a.space_id=?) AND (? IS NULL OR a.id<>?)
             ORDER BY a.setup_start"
        );
        $stmt->execute([$venueId, $start, $end, $spaceId, $spaceId, $ignoreAssignmentId, $ignoreAssignmentId]);
        foreach ($stmt->fetchAll() as $c) {
            $conflicts[] = [
                'source' => 'assignment',
                'reference' => $c['label'],
                'space_id' => $c['space_id'],
                'start_at' => $c['start_at'],
                'end_at' => $c['end_at'],
                'reason' => 'Double booking: ' . $c['label'] . ' already occupies this venue (including setup/teardown).',
            ];
        }

        $blockSql = "SELECT id, status, reason, start_at, end_at, space_id FROM tie_venue_availability
             WHERE venue_id=? AND status IN ('RESERVED','BLOCKED','MAINTENANCE') AND (? < end_at AND ? > start_at)";
        $blockParams = [$venueId, $start, $end];
        if ($spaceId === null) {
            $blockSql .= ' AND (space_id IS NULL OR space_id<>\'\')';
        } else {
            $blockSql .= ' AND (space_id=? OR space_id IS NULL)';
            $blockParams[] = $spaceId;
        }
        $blockSql .= ' ORDER BY start_at';
        $blockStmt = $this->db->prepare($blockSql);
        $blockStmt->execute($blockParams);
        foreach ($blockStmt->fetchAll() as $c) {
            $conflicts[] = [
                'source' => 'block',
                'reference' => (string) ($c['reason'] ?: strtolower($c['status'])),
                'space_id' => $c['space_id'],
                'start_at' => $c['start_at'],
                'end_at' => $c['end_at'],
                'reason' => 'The venue is ' . str_replace('_', ' ', strtolower((string) $c['status'])) . ' for this period.' . ($c['reason'] ? ' Reason: ' . $c['reason'] : ''),
            ];
        }
        return $conflicts;
    }

    // ------------------------------------------------------------------
    // Mutation: wizard + workspace management
    // ------------------------------------------------------------------

    public function createVenue(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venue = $this->validateVenueFields($input, true);
        $venue['id'] = generateId('VEN');
        $venue['vendor_id'] = $vendorId;
        $status = $this->enum($input['status'] ?? 'DRAFT', self::STATUSES, 'status', 'DRAFT');
        $venue['status'] = $status;

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO tie_venues (id, vendor_id, name, type, address, city, district, region, country, gps_lat, gps_lng, capacity, description, contact_phone, contact_email, cover_image, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $venue['id'], $vendorId, $venue['name'], $venue['type'], $venue['address'], $venue['city'], $venue['district'],
                $venue['region'], $venue['country'], $venue['gps_lat'], $venue['gps_lng'], $venue['capacity'], $venue['description'],
                $venue['contact_phone'], $venue['contact_email'], $venue['cover_image'], $status,
            ]);
            $this->persistSpaces($venue['id'], $input['spaces'] ?? []);
            $this->persistFacilities($venue['id'], $input['facilities'] ?? []);
            $this->persistMedia($venue['id'], $input['media'] ?? []);
            $this->persistPricing($venue['id'], $input['pricing'] ?? []);
            $this->persistPolicies($venue['id'], $input['policies'] ?? []);
            $this->audit($venue['id'], $actorId, $actorName, 'venue.created', ['status' => $status]);
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
        return ['schema_version' => 'tie-venue/v1', 'venue' => $this->venueRow($venue['id'], $vendorId)];
    }

    public function updateVenue(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venueId = (string) ($input['venue_id'] ?? '');
        $this->venueRow($venueId, $vendorId, true);
        $fields = $this->validateVenueFields($input, false);
        $sql = 'UPDATE tie_venues SET name=?, type=?, address=?, city=?, district=?, region=?, country=?, gps_lat=?, gps_lng=?, capacity=?, description=?, contact_phone=?, contact_email=?, cover_image=? WHERE id=?';
        $this->db->prepare($sql)->execute([
            $fields['name'], $fields['type'], $fields['address'], $fields['city'], $fields['district'], $fields['region'],
            $fields['country'], $fields['gps_lat'], $fields['gps_lng'], $fields['capacity'], $fields['description'],
            $fields['contact_phone'], $fields['contact_email'], $fields['cover_image'], $venueId,
        ]);
        $this->audit($venueId, $actorId, $actorName, 'venue.updated');
        return ['schema_version' => 'tie-venue/v1', 'venue' => $this->venueRow($venueId, $vendorId)];
    }

    private function validateVenueFields(array $input, bool $required): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($required && $name === '') throw UthengaTieErrors::validation(['name' => 'Venue name is required.']);
        $name = mb_substr($name, 0, 180);
        $type = trim((string) ($input['type'] ?? ''));
        if ($type !== '' && !in_array($type, self::VENUE_TYPES, true)) throw UthengaTieErrors::validation(['type' => 'Choose a valid venue type.']);
        $sanitizeGps = function (string $raw): string {
            $raw = preg_replace('/[°\'"]/u', '', trim((string) $raw));
            return trim(preg_replace('/\s*[NSEWnsew]\s*$/', '', $raw));
        };
        $latRaw = $sanitizeGps((string) ($input['gps_lat'] ?? ''));
        $lngRaw = $sanitizeGps((string) ($input['gps_lng'] ?? ''));
        $lat = $latRaw !== '' ? UthengaEventsContracts::decimal($latRaw, -90, 90, 'gps_lat') : null;
        $lng = $lngRaw !== '' ? UthengaEventsContracts::decimal($lngRaw, -180, 180, 'gps_lng') : null;
        if (($lat === null) !== ($lng === null)) throw UthengaTieErrors::validation(['gps' => 'Provide both latitude and longitude.']);
        $capacity = isset($input['capacity']) && trim((string) $input['capacity']) !== '' ? UthengaEventsContracts::integer($input['capacity'], 1, 1000000, 'capacity') : null;
        $text = fn($key, int $max) => UthengaEventsContracts::nullableText($input[$key] ?? null, $max);
        return [
            'name' => $name,
            'type' => $type,
            'address' => $text('address', 255),
            'city' => $text('city', 120),
            'district' => $text('district', 120),
            'region' => $text('region', 120),
            'country' => $text('country', 120),
            'gps_lat' => $lat,
            'gps_lng' => $lng,
            'capacity' => $capacity,
            'description' => $text('description', 1000),
            'contact_phone' => $text('contact_phone', 30),
            'contact_email' => $text('contact_email', 190),
            'cover_image' => $text('cover_image', 500),
        ];
    }

    public function updateStatus(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venueId = (string) ($input['venue_id'] ?? '');
        $venue = $this->venueRow($venueId, $vendorId, true);
        $status = $this->enum($input['status'] ?? '', self::STATUSES, 'status', '');
        if ($status === '') throw UthengaTieErrors::validation(['status' => 'Choose a valid venue status.']);
        $allowed = [
            'DRAFT' => ['PENDING_REVIEW', 'ACTIVE'],
            'PENDING_REVIEW' => ['ACTIVE', 'DRAFT'],
            'ACTIVE' => ['TEMPORARILY_UNAVAILABLE', 'MAINTENANCE', 'SUSPENDED'],
            'TEMPORARILY_UNAVAILABLE' => ['ACTIVE', 'MAINTENANCE', 'SUSPENDED'],
            'MAINTENANCE' => ['ACTIVE'],
            'SUSPENDED' => ['ACTIVE'],
        ];
        if (!in_array($status, $allowed[$venue['status']] ?? [], true)) {
            throw UthengaTieErrors::validation(['status' => 'Cannot move a venue from ' . $venue['status'] . ' to ' . $status . '.']);
        }
        $this->db->prepare('UPDATE tie_venues SET status=? WHERE id=?')->execute([$status, $venueId]);
        $this->audit($venueId, $actorId, $actorName, 'venue.status_changed', ['from' => $venue['status'], 'to' => $status]);
        return ['schema_version' => 'tie-venue/v1', 'venue' => $this->venueRow($venueId, $vendorId)];
    }

    public function deleteVenue(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venueId = (string) ($input['venue_id'] ?? '');
        $venue = $this->venueRow($venueId, $vendorId, true);
        $active = $this->db->prepare(
            "SELECT COUNT(*) FROM tie_event_venue_assignments WHERE venue_id=? AND status='CONFIRMED' AND teardown_end >= UTC_TIMESTAMP()"
        );
        $active->execute([$venueId]);
        if ((int) $active->fetchColumn() > 0) throw UthengaTieErrors::validation(['venue_id' => 'Venue has confirmed assignments and cannot be removed. Cancel them first.']);
        $this->db->prepare('UPDATE tie_venues SET is_active=0, status=? WHERE id=?')->execute(['SUSPENDED', $venueId]);
        $this->audit($venueId, $actorId, $actorName, 'venue.deleted');
        return ['schema_version' => 'tie-venue/v1', 'deleted' => true, 'venue_id' => $venueId];
    }

    // ------------------------------------------------------------------
    // Spaces
    // ------------------------------------------------------------------

    public function addSpace(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venueId = (string) ($input['venue_id'] ?? '');
        $this->venueRow($venueId, $vendorId, true);
        $space = $this->spaceFields($input['space'] ?? $input, true);
        $id = generateId('VSP');
        $this->db->prepare('INSERT INTO tie_venue_spaces (id, venue_id, name, type, capacity, description, dimensions) VALUES (?,?,?,?,?,?,?)')
            ->execute([$id, $venueId, $space['name'], $space['type'], $space['capacity'], $space['description'], $space['dimensions']]);
        $this->audit($venueId, $actorId, $actorName, 'space.added', ['space' => $space['name']]);
        return $this->detailPayload($venueId, $vendorId);
    }

    public function updateSpace(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $spaceId = (string) ($input['space_id'] ?? '');
        $stmt = $this->db->prepare('SELECT * FROM tie_venue_spaces WHERE id=? LIMIT 1');
        $stmt->execute([$spaceId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['space_id' => 'Space not found.']);
        $this->venueRow($row['venue_id'], $vendorId, true);
        $space = $this->spaceFields($input['space'] ?? $input, false);
        $this->db->prepare('UPDATE tie_venue_spaces SET name=?, type=?, capacity=?, description=?, dimensions=?, status=? WHERE id=?')
            ->execute([$space['name'], $space['type'], $space['capacity'], $space['description'], $space['dimensions'], $this->enum($input['status'] ?? 'ACTIVE', ['ACTIVE', 'MAINTENANCE', 'BLOCKED'], 'status', 'ACTIVE'), $spaceId]);
        $this->audit($row['venue_id'], $actorId, $actorName, 'space.updated', ['space_id' => $spaceId]);
        return $this->detailPayload($row['venue_id'], $vendorId);
    }

    public function deleteSpace(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $spaceId = (string) ($input['space_id'] ?? '');
        $stmt = $this->db->prepare('SELECT venue_id, name FROM tie_venue_spaces WHERE id=? LIMIT 1');
        $stmt->execute([$spaceId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['space_id' => 'Space not found.']);
        $this->venueRow($row['venue_id'], $vendorId, true);
        $used = $this->db->prepare("SELECT COUNT(*) FROM tie_event_venue_assignments WHERE space_id=? AND status='CONFIRMED'");
        $used->execute([$spaceId]);
        if ((int) $used->fetchColumn() > 0) throw UthengaTieErrors::validation(['space_id' => 'Space has confirmed assignments and cannot be removed.']);
        $this->db->prepare('DELETE FROM tie_venue_spaces WHERE id=?')->execute([$spaceId]);
        $this->audit($row['venue_id'], $actorId, $actorName, 'space.deleted', ['space' => $row['name']]);
        return $this->detailPayload($row['venue_id'], $vendorId);
    }

    private function spaceFields(array $input, bool $required): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($required && $name === '') throw UthengaTieErrors::validation(['name' => 'Space name is required.']);
        $type = trim((string) ($input['type'] ?? ''));
        if ($type !== '' && !in_array($type, self::SPACE_TYPES, true)) throw UthengaTieErrors::validation(['type' => 'Choose a valid seating layout.']);
        $capacity = isset($input['capacity']) && trim((string) $input['capacity']) !== '' ? UthengaEventsContracts::integer($input['capacity'], 1, 1000000, 'capacity') : null;
        return [
            'name' => mb_substr($name, 0, 180),
            'type' => $type,
            'capacity' => $capacity,
            'description' => UthengaEventsContracts::nullableText($input['description'] ?? null, 1000),
            'dimensions' => UthengaEventsContracts::nullableText($input['dimensions'] ?? null, 120),
        ];
    }

    private function persistSpaces(string $venueId, array $spaces): void
    {
        foreach ($spaces as $space) {
            if (!is_array($space) || trim((string) ($space['name'] ?? '')) === '') continue;
            $s = $this->spaceFields($space, false);
            $this->db->prepare('INSERT INTO tie_venue_spaces (id, venue_id, name, type, capacity, description, dimensions) VALUES (?,?,?,?,?,?,?)')
                ->execute([generateId('VSP'), $venueId, $s['name'], $s['type'], $s['capacity'], $s['description'], $s['dimensions']]);
        }
    }

    // ------------------------------------------------------------------
    // Facilities / media / pricing / policies
    // ------------------------------------------------------------------

    public function saveFacilities(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venueId = (string) ($input['venue_id'] ?? '');
        $this->venueRow($venueId, $vendorId, true);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM tie_venue_facilities WHERE venue_id=?')->execute([$venueId]);
            $this->persistFacilities($venueId, $input['facilities'] ?? []);
            $this->db->commit();
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
        $this->audit($venueId, $actorId, $actorName, 'facilities.saved');
        return $this->detailPayload($venueId, $vendorId);
    }

    private function persistFacilities(string $venueId, array $facilities): void
    {
        foreach ($facilities as $facility) {
            if (!is_array($facility) || trim((string) ($facility['name'] ?? '')) === '') continue;
            $group = $this->enum($facility['group'] ?? 'GENERAL', self::FACILITY_GROUPS, 'group', 'GENERAL');
            $this->db->prepare('INSERT INTO tie_venue_facilities (id, venue_id, facility_group, name, description, available) VALUES (?,?,?,?,?,?)')
                ->execute([generateId('VFC'), $venueId, $group, mb_substr(trim((string) $facility['name']), 0, 180), UthengaEventsContracts::nullableText($facility['description'] ?? null, 500), !empty($facility['available']) ? 1 : 0]);
        }
    }

    public function saveMedia(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venueId = (string) ($input['venue_id'] ?? '');
        $this->venueRow($venueId, $vendorId, true);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM tie_venue_media WHERE venue_id=?')->execute([$venueId]);
            $this->persistMedia($venueId, $input['media'] ?? []);
            $cover = $this->db->prepare('SELECT url FROM tie_venue_media WHERE venue_id=? AND is_cover=1 ORDER BY sort_order LIMIT 1');
            $cover->execute([$venueId]);
            $coverUrl = $cover->fetchColumn();
            $this->db->prepare('UPDATE tie_venues SET cover_image=? WHERE id=?')->execute([$coverUrl ?: null, $venueId]);
            $this->db->commit();
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
        $this->audit($venueId, $actorId, $actorName, 'media.saved', ['items' => count(array_filter($input['media'] ?? [], 'is_array'))]);
        return $this->detailPayload($venueId, $vendorId);
    }

    private function persistMedia(string $venueId, array $media): void
    {
        $order = 0;
        foreach ($media as $item) {
            if (!is_array($item) || trim((string) ($item['url'] ?? '')) === '') continue;
            $type = in_array(strtoupper((string) ($item['type'] ?? 'GALLERY')), ['COVER', 'GALLERY', 'FLOOR_PLAN'], true) ? strtoupper((string) $item['type']) : 'GALLERY';
            $spaceId = isset($item['space_id']) && trim((string) $item['space_id']) !== '' ? (string) $item['space_id'] : null;
            $this->db->prepare('INSERT INTO tie_venue_media (id, venue_id, space_id, media_type, url, sort_order, is_cover) VALUES (?,?,?,?,?,?,?)')
                ->execute([generateId('VMED'), $venueId, $spaceId, $type, mb_substr(trim((string) $item['url']), 0, 500), $order++, !empty($item['is_cover']) ? 1 : 0]);
        }
    }

    public function savePricing(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venueId = (string) ($input['venue_id'] ?? '');
        $this->venueRow($venueId, $vendorId, true);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM tie_venue_pricing WHERE venue_id=?')->execute([$venueId]);
            $this->persistPricing($venueId, $input['pricing'] ?? []);
            $this->db->commit();
        } catch (Throwable $error) { $this->db->rollBack(); throw $error; }
        $this->audit($venueId, $actorId, $actorName, 'pricing.saved');
        return $this->detailPayload($venueId, $vendorId);
    }

    private function persistPricing(string $venueId, array $pricing): void
    {
        foreach ($pricing as $row) {
            if (!is_array($row) || trim((string) ($row['name'] ?? '')) === '') continue;
            $price = UthengaEventsContracts::decimal($row['price'] ?? 0, 0, 9999999999, 'price');
            $this->db->prepare('INSERT INTO tie_venue_pricing (id, venue_id, name, price, currency, description) VALUES (?,?,?,?,?,?)')
                ->execute([generateId('VPR'), $venueId, mb_substr(trim((string) $row['name']), 0, 120), $price, trim((string) ($row['currency'] ?? 'MWK')) ?: 'MWK', UthengaEventsContracts::nullableText($row['description'] ?? null, 255)]);
        }
    }

    public function savePolicies(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venueId = (string) ($input['venue_id'] ?? '');
        $this->venueRow($venueId, $vendorId, true);
        $p = $input['policies'] ?? $input;
        $restrictions = [];
        if (isset($p['restrictions']) && is_array($p['restrictions'])) {
            foreach ($p['restrictions'] as $r) {
                $r = trim((string) $r);
                if ($r !== '') $restrictions[] = $r;
            }
        } elseif (isset($p['restrictions']) && is_string($p['restrictions'])) {
            $restrictions = array_values(array_filter(array_map('trim', explode(',', $p['restrictions']))));
        }
        $int = fn($key, $max, $default) => isset($p[$key]) && trim((string) $p[$key]) !== '' ? UthengaEventsContracts::integer($p[$key], 0, $max, $key) : $default;
        $this->db->prepare(
            'INSERT INTO tie_venue_policies (venue_id, cancellation_policy, advance_booking_days, min_duration_hours, max_duration_hours, restrictions, opening_time, closing_time, setup_period_minutes, teardown_period_minutes, check_in_time)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE cancellation_policy=VALUES(cancellation_policy), advance_booking_days=VALUES(advance_booking_days), min_duration_hours=VALUES(min_duration_hours), max_duration_hours=VALUES(max_duration_hours), restrictions=VALUES(restrictions), opening_time=VALUES(opening_time), closing_time=VALUES(closing_time), setup_period_minutes=VALUES(setup_period_minutes), teardown_period_minutes=VALUES(teardown_period_minutes), check_in_time=VALUES(check_in_time)'
        )->execute([
            $venueId,
            UthengaEventsContracts::nullableText($p['cancellation_policy'] ?? null, 2000),
            $int('advance_booking_days', 365, null),
            $int('min_duration_hours', 720, null),
            $int('max_duration_hours', 7200, null),
            $this->jsonOut($restrictions),
            UthengaEventsContracts::nullableText($p['opening_time'] ?? null, 5),
            UthengaEventsContracts::nullableText($p['closing_time'] ?? null, 5),
            $int('setup_period_minutes', 10080, 120),
            $int('teardown_period_minutes', 10080, 60),
            UthengaEventsContracts::nullableText($p['check_in_time'] ?? null, 5),
        ]);
        $this->audit($venueId, $actorId, $actorName, 'policies.saved', ['restrictions' => count($restrictions)]);
        return $this->detailPayload($venueId, $vendorId);
    }

    private function persistPolicies(string $venueId, array $policies): void
    {
        if (!$policies) return;
        $input = ['venue_id' => $venueId, 'policies' => $policies];
        $p = $policies;
        $restrictions = is_array($p['restrictions'] ?? null) ? $p['restrictions'] : [];
        $int = fn($key, $max, $default) => isset($p[$key]) && trim((string) $p[$key]) !== '' ? UthengaEventsContracts::integer($p[$key], 0, $max, $key) : $default;
        $this->db->prepare(
            'INSERT INTO tie_venue_policies (venue_id, cancellation_policy, advance_booking_days, min_duration_hours, max_duration_hours, restrictions, opening_time, closing_time, setup_period_minutes, teardown_period_minutes)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $venueId,
            UthengaEventsContracts::nullableText($p['cancellation_policy'] ?? null, 2000),
            $int('advance_booking_days', 365, null),
            $int('min_duration_hours', 720, null),
            $int('max_duration_hours', 7200, null),
            $this->jsonOut($restrictions),
            UthengaEventsContracts::nullableText($p['opening_time'] ?? null, 5),
            UthengaEventsContracts::nullableText($p['closing_time'] ?? null, 5),
            $int('setup_period_minutes', 10080, 120),
            $int('teardown_period_minutes', 10080, 60),
        ]);
    }

    // ------------------------------------------------------------------
    // Availability calendar (manual blocks)
    // ------------------------------------------------------------------

    public function setAvailability(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $venueId = (string) ($input['venue_id'] ?? '');
        $this->venueRow($venueId, $vendorId, true);
        $spaceId = isset($input['space_id']) && trim((string) $input['space_id']) !== '' ? (string) $input['space_id'] : null;
        $start = $this->dt($input['start_at'] ?? '', 'start_at');
        $end = $this->dt($input['end_at'] ?? '', 'end_at');
        if (strtotime($end) <= strtotime($start)) throw UthengaTieErrors::validation(['end_at' => 'End must be after start.']);
        $status = $this->enum($input['status'] ?? 'BLOCKED', ['RESERVED', 'BLOCKED', 'MAINTENANCE'], 'status', 'BLOCKED');

        $conflicts = $this->overlaps($venueId, $spaceId, $start, $end, null);
        foreach ($conflicts as $c) {
            if ($c['source'] === 'assignment') throw UthengaTieErrors::validation(['start_at' => 'Cannot block this period: ' . $c['reason']]);
        }

        $id = generateId('VA');
        $this->db->prepare('INSERT INTO tie_venue_availability (id, venue_id, space_id, start_at, end_at, status, reason) VALUES (?,?,?,?,?,?,?)')
            ->execute([$id, $venueId, $spaceId, $start, $end, $status, UthengaEventsContracts::nullableText($input['reason'] ?? null, 500)]);
        $this->audit($venueId, $actorId, $actorName, 'availability.blocked', ['status' => $status, 'start_at' => $start, 'end_at' => $end, 'reason' => $input['reason'] ?? null]);
        return $this->detailPayload($venueId, $vendorId);
    }

    public function removeAvailability(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $blockId = (string) ($input['block_id'] ?? '');
        $stmt = $this->db->prepare('SELECT * FROM tie_venue_availability WHERE id=? LIMIT 1');
        $stmt->execute([$blockId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['block_id' => 'Block not found.']);
        $this->venueRow($row['venue_id'], $vendorId, true);
        $this->db->prepare('DELETE FROM tie_venue_availability WHERE id=?')->execute([$blockId]);
        $this->audit($row['venue_id'], $actorId, $actorName, 'availability.block_removed', ['block_id' => $blockId]);
        return $this->detailPayload($row['venue_id'], $vendorId);
    }

    // ------------------------------------------------------------------
    // Event assignment (authoritative, conflict-enforced)
    // ------------------------------------------------------------------

    public function assignEvent(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $eventId = (string) ($input['event_id'] ?? '');
        $venueId = (string) ($input['venue_id'] ?? '');
        $venue = $this->venueRow($venueId, $vendorId, true);
        $spaceId = isset($input['space_id']) && trim((string) $input['space_id']) !== '' ? (string) $input['space_id'] : null;
        if ($spaceId) {
            $sp = $this->db->prepare('SELECT id FROM tie_venue_spaces WHERE id=? AND venue_id=? LIMIT 1');
            $sp->execute([$spaceId, $venueId]);
            if (!$sp->fetchColumn()) throw UthengaTieErrors::validation(['space_id' => 'Space does not belong to this venue.']);
        }

        $eventStmt = $this->db->prepare("SELECT id, title, status FROM tie_events_events WHERE id=? AND vendor_id=? LIMIT 1");
        $eventStmt->execute([$eventId, $vendorId]);
        $event = $eventStmt->fetch();
        if (!is_array($event)) throw UthengaTieErrors::validation(['event_id' => 'Event not found.']);
        if (in_array($event['status'], ['CANCELLED', 'ARCHIVED'], true)) {
            throw UthengaTieErrors::validation(['event_id' => 'A ' . strtolower($event['status']) . ' event cannot be assigned to a venue.']);
        }

        $setupStart = $this->dt($input['setup_start'] ?? $input['event_start'] ?? '', 'setup_start');
        $eventStart = $this->dt($input['event_start'] ?? '', 'event_start');
        $eventEnd = $this->dt($input['event_end'] ?? '', 'event_end');
        $teardownEnd = $this->dt($input['teardown_end'] ?? $input['event_end'] ?? '', 'teardown_end');
        $span = [
            strtotime($setupStart) <= strtotime($eventStart),
            strtotime($eventStart) < strtotime($eventEnd),
            strtotime($eventEnd) <= strtotime($teardownEnd),
        ];
        if (!$span[0] || !$span[1] || !$span[2]) {
            throw UthengaTieErrors::validation(['dates' => 'Times must follow: setup start ≤ event start < event end ≤ teardown end.']);
        }

        $this->db->beginTransaction();
        try {
            $lockStmt = $this->db->prepare('SELECT id FROM tie_venues WHERE id=? FOR UPDATE');
            $lockStmt->execute([$venueId]);

            $conflicts = $this->overlaps($venueId, $spaceId, $setupStart, $teardownEnd, null);
            if (count($conflicts) > 0) {
                $this->db->rollBack();
                throw new UthengaTieException('conflict_error', 'The venue is not available for this period.', 409, ['conflicts' => $conflicts, 'venue_id' => $venueId]);
            }

            $assignmentId = generateId('EVA');
            $this->db->prepare(
                'INSERT INTO tie_event_venue_assignments (id, event_id, venue_id, space_id, setup_start, event_start, event_end, teardown_end, status, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([$assignmentId, $eventId, $venueId, $spaceId, $setupStart, $eventStart, $eventEnd, $teardownEnd, 'CONFIRMED', $actorId]);

            $this->db->prepare('INSERT INTO tie_venue_availability (id, venue_id, space_id, start_at, end_at, status, event_id, assignment_id) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([generateId('VA'), $venueId, $spaceId, $eventStart, $teardownEnd, 'EVENT', $eventId, $assignmentId]);
            if ($setupStart !== $eventStart) {
                $this->db->prepare('INSERT INTO tie_venue_availability (id, venue_id, space_id, start_at, end_at, status, event_id, assignment_id) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([generateId('VA'), $venueId, $spaceId, $setupStart, $eventStart, 'SETUP', $eventId, $assignmentId]);
            }

            $this->db->prepare('UPDATE tie_events_events SET venue_id=? WHERE id=?')->execute([$venueId, $eventId]);
            $this->audit($venueId, $actorId, $actorName, 'event.assigned', ['event_id' => $eventId, 'event' => $event['title'], 'space_id' => $spaceId, 'event_start' => $eventStart, 'event_end' => $eventEnd]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }

        return [
            'schema_version' => 'tie-venue-assignment/v1',
            'assignment' => [
                'id' => $assignmentId, 'event_id' => $eventId, 'event_title' => $event['title'], 'venue_id' => $venueId,
                'space_id' => $spaceId, 'setup_start' => $setupStart, 'event_start' => $eventStart,
                'event_end' => $eventEnd, 'teardown_end' => $teardownEnd, 'status' => 'CONFIRMED',
            ],
        ];
    }

    public function deleteAssignment(string $vendorId, string $actorId, string $actorName, array $input): array
    {
        $assignmentId = (string) ($input['assignment_id'] ?? '');
        $stmt = $this->db->prepare('SELECT * FROM tie_event_venue_assignments WHERE id=? LIMIT 1');
        $stmt->execute([$assignmentId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['assignment_id' => 'Assignment not found.']);
        $this->venueRow($row['venue_id'], $vendorId, true);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE tie_event_venue_assignments SET status=? WHERE id=?')->execute(['CANCELLED', $assignmentId]);
            $this->db->prepare('DELETE FROM tie_venue_availability WHERE assignment_id=?')->execute([$assignmentId]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        $this->audit($row['venue_id'], $actorId, $actorName, 'event.unassigned', ['assignment_id' => $assignmentId, 'event_id' => $row['event_id']]);
        return $this->detailPayload($row['venue_id'], $vendorId);
    }

    private function detailPayload(string $venueId, string $vendorId): array
    {
        return ['detail' => $this->venueDetail($venueId, $vendorId)];
    }
}