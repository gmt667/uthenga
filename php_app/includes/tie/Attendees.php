<?php
/**
 * Uthenga — Attendee Intelligence & Participant Management Service.
 *
 * The Attendees workspace manages the people attached to an event from the
 * moment their ticket is issued through attendance and post-event status.
 * It deliberately does NOT duplicate the ticket commerce system: attendee
 * records are derived views over authoritative entities (event_tickets,
 * bookings, ticket_types, event_ticket_audit), per the platform boundary:
 *
 *   Tickets   → "What was purchased?"
 *   Attendees → "Who is attending?"
 *   Check-In  → "Who has arrived?"
 *
 * Lifecycle: TICKET_ISSUED → EXPECTED → CHECKED_IN → ATTENDING → EVENT_COMPLETED
 *            (CANCELLED / REFUNDED exit the expected population)
 */
class UthengaAttendeesService
{
    private PDO $db;

    private const VALID_PAYMENTS = ['Paid', 'Pending', 'Failed', 'Refunded'];
    private const GATES = ['Gate A', 'Gate B', 'Gate C', 'Gate D'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------
    // Shared internals (mirror the tickets service contract)
    // ------------------------------------------------------------------

    private function listingRow(string $listingId, string $vendorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, e.id AS event_id, e.status AS event_status, e.start_date, e.start_time, e.venue_id,
                    v.name AS venue_name, v.city AS venue_city
             FROM listings l
             LEFT JOIN tie_events_events e ON e.listing_id = l.id
             LEFT JOIN tie_venues v ON v.id = e.venue_id
             WHERE l.id = ? AND l.listing_type = 'event'
             LIMIT 1"
        );
        $stmt->execute([$listingId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['listing_id' => 'This event does not exist.']);

        $owner = $this->db->prepare('SELECT vendor_id FROM tie_events_events WHERE listing_id=? LIMIT 1');
        $owner->execute([$listingId]);
        $ownerId = (string) ($owner->fetchColumn() ?: ($row['vendor_id'] ?? ''));
        if ($ownerId !== '' && $ownerId !== $vendorId) throw UthengaTieErrors::authorization();
        return $row;
    }

    private function countVal(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function audit(string $listingId, string $actorId, string $action, array $details = [], ?int $ticketTypeId = null, ?string $ticketId = null, ?string $bookingId = null): void
    {
        $name = '';
        if ($actorId !== '') {
            $stmt = $this->db->prepare('SELECT name FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$actorId]);
            $name = (string) ($stmt->fetchColumn() ?: '');
        }
        $this->db->prepare(
            'INSERT INTO event_ticket_audit (listing_id, ticket_type_id, ticket_id, booking_id, actor_id, actor_name, action, details) VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$listingId, $ticketTypeId, $ticketId, $bookingId, $actorId !== '' ? $actorId : null, $name, $action, json_encode($details, JSON_UNESCAPED_SLASHES)]);
    }

    private function money(mixed $v, string $field): float
    {
        $f = round((float) ($v ?? 0), 2);
        if ($f < 0 || $f > 100_000_000) throw UthengaTieErrors::validation([$field => 'Amount is out of range.']);
        return $f;
    }

    private function bookingDetails(array $booking, array $extra = []): string
    {
        $base = json_decode((string) ($booking['details'] ?? '[]'), true);
        if (!is_array($base)) $base = [];
        $details = array_merge($base, $extra);
        return json_encode($details, JSON_UNESCAPED_SLASHES);
    }

    private function accessZones(array $type): array
    {
        $rules = json_decode((string) ($type['access_rules'] ?? '[]'), true);
        return is_array($rules) ? array_values($rules) : [];
    }

    private function organizationOf(array $booking): ?string
    {
        $details = $booking['details'] ?? [];
        if (!is_array($details)) $details = json_decode((string) $details, true);
        if (!is_array($details)) return null;
        $org = trim((string) ($details['organization'] ?? ''));
        return $org !== '' ? $org : null;
    }

    private function issueTicketForBooking(array $booking, string $gatewayNote = ''): string
    {
        $type = null;
        if (!empty($booking['ticket_type_id'])) {
            $st = $this->db->prepare('SELECT * FROM ticket_types WHERE id=? LIMIT 1');
            $st->execute([(int) $booking['ticket_type_id']]);
            $type = $st->fetch() ?: null;
        }
        $title = (string) ($booking['listing_title'] ?? '');
        $evtCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $title) ?: 'EVT', 0, 3));
        $digest = strtoupper(substr(hash('crc32b', $booking['listing_id']), 0, 4));
        $typeCode = $type ? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $type['name']) ?: 'TKT', 0, 3)) : 'TKT';
        $seq = $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE ticket_type_id=?', [(int) ($type['id'] ?? 0)]) + 1;
        $ticketId = 'UTH-' . $evtCode . '-' . $typeCode . '-' . $digest . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        $token = bin2hex(random_bytes(24));
        $signature = hash_hmac('sha256', $ticketId . '.' . $token, 'uthenga-tie-ticket-v1');
        $details = json_decode((string) ($booking['details'] ?? '[]'), true) ?: [];

        $this->db->prepare(
            'INSERT INTO event_tickets (id, listing_id, ticket_type_id, booking_id, holder_name, holder_email, holder_phone, qr_token, verification_signature, status)
             VALUES (?,?,?,?,?,?,?,?,?,\'ISSUED\')'
        )->execute([
            $ticketId, $booking['listing_id'], $booking['ticket_type_id'] ?? 0, $booking['id'],
            $booking['customer_name'] ?? 'Ticket Holder', $booking['customer_email'] ?? null,
            $details['phone'] ?? null, $token, $signature,
        ]);
        $this->audit($booking['listing_id'], '', 'ticket.issued', ['ticket_id' => $ticketId, 'booking_id' => $booking['id'], 'source' => $gatewayNote ?: 'booking'], (int) ($booking['ticket_type_id'] ?? 0), $ticketId, $booking['id']);
        return $ticketId;
    }

    // ------------------------------------------------------------------
    // Workspace snapshot
    // ------------------------------------------------------------------

    public function workspace(string $listingId, string $vendorId): array
    {
        $listing = $this->listingRow($listingId, $vendorId);

        $total      = $this->countVal("SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND status IN ('ISSUED','CHECKED_IN')", [$listingId]);
        $checkedIn  = $this->countVal("SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at IS NOT NULL AND status IN ('ISSUED','CHECKED_IN')", [$listingId]);
        $cancelled  = $this->countVal("SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND status IN ('CANCELLED','REFUNDED')", [$listingId]);
        $vip        = $this->countVal(
            "SELECT COUNT(*) FROM event_tickets et JOIN ticket_types tt ON tt.id=et.ticket_type_id
             WHERE et.listing_id=? AND et.status IN ('ISSUED','CHECKED_IN') AND (tt.tier IN ('vip') OR tt.category IN ('VIP','VVIP'))",
            [$listingId]
        );

        $kpis = [
            'total' => $total,
            'expected' => $total,
            'checked_in' => $checkedIn,
            'not_arrived' => max($total - $checkedIn, 0),
            'cancelled_refunded' => $cancelled,
            'vip' => $vip,
            'checkin_rate' => $total > 0 ? round(100 * $checkedIn / $total, 1) : 0.0,
        ];

        // Arrival velocity
        $last15 = $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at >= (NOW() - INTERVAL 15 MINUTE)', [$listingId]);
        $last60 = $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at >= (NOW() - INTERVAL 60 MINUTE)', [$listingId]);
        $peak = 0.0;
        $pkStmt = $this->db->prepare(
            "SELECT MAX(cnt) FROM (SELECT DATE_FORMAT(checked_in_at, '%Y-%m-%d %H:%i') AS m, COUNT(*) cnt
             FROM event_tickets WHERE listing_id=? AND checked_in_at >= (NOW() - INTERVAL 60 MINUTE) GROUP BY m) t"
        );
        $pkStmt->execute([$listingId]);
        $peakMinute = (int) ($pkStmt->fetchColumn() ?: 0);
        if ($peakMinute > 0) $peak = round($peakMinute / 1, 1);

        $curve = [];
        $cvStmt = $this->db->prepare(
            "SELECT DATE_FORMAT(checked_in_at, '%H:00') AS hour, COUNT(*) AS cnt
             FROM event_tickets WHERE listing_id=? AND checked_in_at >= (NOW() - INTERVAL 12 HOUR)
             GROUP BY hour ORDER BY hour ASC"
        );
        $cvStmt->execute([$listingId]);
        foreach ($cvStmt->fetchAll() as $r) $curve[] = ['hour' => $r['hour'], 'count' => (int) $r['cnt']];

        $byType = [];
        $btStmt = $this->db->prepare(
            "SELECT tt.id, tt.name, tt.category, tt.price,
                    SUM(et.status IN ('ISSUED','CHECKED_IN')) AS total,
                    SUM(et.checked_in_at IS NOT NULL AND et.status IN ('ISSUED','CHECKED_IN')) AS checked_in
             FROM event_tickets et JOIN ticket_types tt ON tt.id = et.ticket_type_id
             WHERE et.listing_id=? GROUP BY tt.id, tt.name, tt.category, tt.price ORDER BY total DESC"
        );
        $btStmt->execute([$listingId]);
        foreach ($btStmt->fetchAll() as $r) {
            $t = (int) ($r['total'] ?? 0);
            $byType[] = [
                'ticket_type_id' => (int) $r['id'], 'name' => $r['name'], 'category' => $r['category'],
                'price' => (float) $r['price'], 'total' => $t,
                'checked_in' => (int) ($r['checked_in'] ?? 0),
                'rate' => $t > 0 ? round(100 * (int) ($r['checked_in'] ?? 0) / $t, 1) : 0.0,
            ];
        }

        $byGate = [];
        if ($checkedIn > 0) {
            $gateStmt = $this->db->prepare(
                "SELECT COALESCE(checked_in_gate, 'Unassigned') AS gate, COUNT(*) AS cnt
                 FROM event_tickets WHERE listing_id=? AND checked_in_at IS NOT NULL GROUP BY gate ORDER BY cnt DESC"
            );
            $gateStmt->execute([$listingId]);
            foreach ($gateStmt->fetchAll() as $r) $byGate[] = ['gate' => $r['gate'], 'count' => (int) $r['cnt']];
        }

        // Live feed (recent check-ins only)
        $live = [];
        if ($checkedIn > 0) {
            $liveStmt = $this->db->prepare(
                "SELECT et.id, et.holder_name, et.checked_in_at, et.checked_in_gate, tt.name AS ticket_type_name
                 FROM event_tickets et JOIN ticket_types tt ON tt.id = et.ticket_type_id
                 WHERE et.listing_id=? AND et.checked_in_at IS NOT NULL
                 ORDER BY et.checked_in_at DESC LIMIT 10"
            );
            $liveStmt->execute([$listingId]);
            foreach ($liveStmt->fetchAll() as $r) {
                $live[] = [
                    'ticket_id' => $r['id'], 'holder_name' => $r['holder_name'],
                    'ticket_type_name' => $r['ticket_type_name'], 'checked_in_at' => $r['checked_in_at'],
                    'gate' => $r['checked_in_gate'] ?? null,
                ];
            }
        }

        // Filter facets
        $types = [];
        $st = $this->db->prepare('SELECT id, name, category FROM ticket_types WHERE listing_id=? ORDER BY sort_order, id');
        $st->execute([$listingId]);
        foreach ($st->fetchAll() as $r) $types[] = ['id' => (int) $r['id'], 'name' => $r['name'], 'category' => $r['category']];

        $zones = [];
        $zoneStmt = $this->db->prepare('SELECT access_rules FROM ticket_types WHERE listing_id=? AND access_rules IS NOT NULL');
        $zoneStmt->execute([$listingId]);
        foreach ($zoneStmt->fetchAll() as $r) {
            $rules = json_decode((string) $r['access_rules'], true);
            if (is_array($rules)) $zones = array_values(array_unique(array_merge($zones, array_map('strval', $rules))));
        }

        $orgs = [];
        $orgStmt = $this->db->prepare(
            "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(details, '$.organization')) AS org FROM bookings WHERE listing_id=? AND details IS NOT NULL"
        );
        $orgStmt->execute([$listingId]);
        foreach ($orgStmt->fetchAll() as $r) {
            if (!empty($r['org'])) $orgs[] = $r['org'];
        }

        $insights = $this->insights($kpis, $byType, $byGate);

        return [
            'listing' => [
                'id' => $listingId, 'title' => $listing['title'], 'event_id' => $listing['event_id'],
                'event_status' => $listing['event_status'], 'is_published' => $listing['event_status'] === 'PUBLISHED',
                'start_date' => $listing['start_date'], 'start_time' => $listing['start_time'],
                'venue_name' => $listing['venue_name'], 'venue_city' => $listing['venue_city'],
            ],
            'kpis' => $kpis,
            'arrival' => ['last_15' => $last15, 'last_60' => $last60, 'peak_rate_per_min' => $peak, 'curve' => $curve],
            'by_type' => $byType,
            'by_gate' => $byGate,
            'insights' => $insights,
            'live' => $live,
            'filters' => ['types' => $types, 'access_zones' => $zones, 'organizations' => array_values(array_unique($orgs))],
            'schema_version' => 'tie-attendees-workspace/v1',
        ];
    }

    private function insights(array $kpis, array $byType, array $byGate): array
    {
        $out = [];
        $rate = (float) $kpis['checkin_rate'];
        if ($kpis['total'] > 0) {
            if ($rate < 40) $out[] = ['level' => 'warn', 'message' => round($rate, 1) . '% of expected attendees have arrived. ' . $kpis['not_arrived'] . ' remain expected.'];
            elseif ($rate >= 85) $out[] = ['level' => 'info', 'message' => round($rate, 1) . '% of expected attendees have arrived — venue is near full.'];
            else $out[] = ['level' => 'info', 'message' => round($rate, 1) . '% of expected attendees have arrived so far.'];
        }

        foreach ($byType as $t) {
            if (in_array(strtoupper((string) $t['category']), ['VIP', 'VVIP'], true) && $t['total'] > 0) {
                $remaining = $t['total'] - $t['checked_in'];
                $out[] = ['level' => $remaining > ($t['total'] * 0.25) ? 'warn' : 'info', 'message' => sprintf('%s%% of %s attendees have arrived. %d %s guest%s remain expected.', $t['rate'], $t['name'], $remaining, $t['name'], $remaining === 1 ? '' : 's')];
            }
            $othersTotal = array_sum(array_map(fn($x) => $x['total'], array_filter($byType, fn($x) => $x['category'] !== 'Student')));
            $othersIn = array_sum(array_map(fn($x) => $x['checked_in'], array_filter($byType, fn($x) => $x['category'] !== 'Student')));
            if (strtoupper((string) $t['category']) === 'STUDENT' && $t['total'] > 0 && $othersTotal > 0) {
                $studentNoShow = 100 - $t['rate'];
                $otherNoShow = 100 - ($othersTotal > 0 ? 100 * $othersIn / $othersTotal : 0);
                if ($studentNoShow > $otherNoShow + 8) {
                    $out[] = ['level' => 'warn', 'message' => sprintf('No-show pattern: %.0f%% of student-ticket holders have not arrived, compared with %.0f%% across all other ticket types.', $studentNoShow, $otherNoShow)];
                }
            }
        }

        foreach ($byGate as $g) {
            $share = $kpis['checked_in'] > 0 ? 100 * $g['count'] / $kpis['checked_in'] : 0;
            if ($share >= 40) {
                $out[] = ['level' => 'warn', 'message' => sprintf('%s is receiving %.0f%% of arrivals and may become congested.', $g['gate'], $share)];
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Attendee directory (searchable, filterable)
    // ------------------------------------------------------------------

    public function list(string $listingId, string $vendorId, array $f = []): array
    {
        $this->listingRow($listingId, $vendorId);
        $where = ['et.listing_id=?'];
        $params = [$listingId];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(et.id LIKE ? OR et.holder_name LIKE ? OR et.holder_email LIKE ? OR et.holder_phone LIKE ?
                          OR et.qr_token LIKE ? OR et.booking_id LIKE ? OR tt.name LIKE ?
                          OR JSON_UNQUOTE(JSON_EXTRACT(b.details, '$.organization')) LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like, $like]);
        }

        $typeId = (int) ($f['type_id'] ?? 0);
        if ($typeId > 0) { $where[] = 'et.ticket_type_id=?'; $params[] = $typeId; }

        $attendance = strtolower((string) ($f['attendance'] ?? ''));
        if ($attendance === 'checked_in' || $attendance === 'attending') $where[] = "et.checked_in_at IS NOT NULL AND et.status IN ('ISSUED','CHECKED_IN')";
        elseif ($attendance === 'expected') $where[] = "et.status='ISSUED' AND et.checked_in_at IS NULL";
        elseif ($attendance === 'not_arrived') $where[] = "et.status='ISSUED' AND et.checked_in_at IS NULL";
        elseif ($attendance === 'exited' || $attendance === 'checked_out') $where[] = "et.status='CHECKED_OUT'";
        elseif ($attendance === 'cancelled') $where[] = "et.status='CANCELLED'";
        elseif ($attendance === 'refunded') $where[] = "et.status='REFUNDED'";

        $payment = strtolower((string) ($f['payment'] ?? ''));
        if (in_array($payment, array_map('strtolower', self::VALID_PAYMENTS), true)) { $where[] = 'LOWER(b.payment_status)=?'; $params[] = $payment; }
        elseif ($payment === 'comp' || $payment === 'complementory' || $payment === 'complimentary') { $where[] = "b.payment_gateway='Complimentary'"; }

        $since = strtolower((string) ($f['since'] ?? ''));
        if ($since === 'today') { $where[] = 'et.created_at >= (CURDATE())'; }
        elseif ($since === '7d') { $where[] = 'et.created_at >= (NOW() - INTERVAL 7 DAY)'; }
        elseif ($since === '30d') { $where[] = 'et.created_at >= (NOW() - INTERVAL 30 DAY)'; }

        $zone = trim((string) ($f['zone'] ?? ''));
        if ($zone !== '') {
            $where[] = 'tt.access_rules IS NOT NULL AND JSON_CONTAINS(tt.access_rules, JSON_QUOTE(?))';
            $params[] = $zone;
        }

        $org = trim((string) ($f['organization'] ?? ''));
        if ($org !== '') {
            $where[] = "JSON_UNQUOTE(JSON_EXTRACT(b.details, '$.organization'))=?";
            $params[] = $org;
        }

        $limit = min(max((int) ($f['limit'] ?? 0), 0), 1000);
        $sql = "SELECT et.*, tt.name AS ticket_type_name, tt.category, tt.price, tt.access_rules,
                       b.payment_status, b.payment_gateway, b.transaction_id, b.details AS booking_details
                FROM event_tickets et
                JOIN ticket_types tt ON tt.id = et.ticket_type_id
                LEFT JOIN bookings b ON b.id = et.booking_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY et.created_at DESC" . ($limit > 0 ? " LIMIT $limit" : '');
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll() as $r) $rows[] = $this->attendeeView($r);
        return $rows;
    }

    private function attendeeView(array $r, bool $withQr = false): array
    {
        $status = strtoupper((string) ($r['status'] ?? 'ISSUED'));
        $attendance = match (true) {
            $status === 'CANCELLED' => 'CANCELLED',
            $status === 'REFUNDED' => 'REFUNDED',
            $status === 'CHECKED_OUT' => 'EXITED',
            !empty($r['checked_in_at']) => 'CHECKED_IN',
            default => 'EXPECTED',
        };
        $type = ['id' => (int) ($r['ticket_type_id'] ?? 0), 'name' => (string) ($r['ticket_type_name'] ?? 'Ticket'), 'category' => (string) ($r['category'] ?? ''), 'price' => (float) ($r['price'] ?? 0)];
        $booking = ['id' => $r['booking_id'], 'payment_status' => (string) ($r['payment_status'] ?? 'Paid'), 'payment_gateway' => (string) ($r['payment_gateway'] ?? ''), 'transaction_id' => $r['transaction_id'], 'details' => json_decode((string) ($r['booking_details'] ?? '[]'), true) ?: []];
        return [
            'attendee_id' => $r['id'],
            'name' => (string) ($r['holder_name'] ?? 'Ticket Holder'),
            'email' => $r['holder_email'],
            'phone' => $r['holder_phone'],
            'organization' => $this->organizationOf($booking),
            'ticket_id' => $r['id'],
            'ticket_type_id' => $type['id'],
            'ticket_type_name' => $type['name'],
            'category' => $type['category'],
            'price' => $type['price'],
            'booking_id' => $booking['id'],
            'payment_status' => $booking['payment_status'],
            'payment_gateway' => $booking['payment_gateway'],
            'transaction_id' => $booking['transaction_id'],
            'attendance_status' => $attendance,
            'checked_in_at' => $r['checked_in_at'],
            'checked_in_gate' => $r['checked_in_gate'],
            'checked_in_by' => $r['checked_in_by'],
            'access_zones' => $this->accessZones($r),
            'registered_at' => $r['created_at'],
        ];
        if ($withQr) {
            $view['qr_token'] = (string) ($r['qr_token'] ?? '');
            $view['verification_signature'] = (string) ($r['verification_signature'] ?? '');
            $view['qr_payload'] = 'UTHENGA|' . $r['id'] . '|' . ($r['qr_token'] ?? '') . '|' . ($r['verification_signature'] ?? '');
            $view['ticket_description'] = (string) ($r['ticket_description'] ?? '');
        }
        return $view;
    }

    // ------------------------------------------------------------------
    // Attendee detail record
    // ------------------------------------------------------------------

    public function detail(string $listingId, string $vendorId, string $ticketId): array
    {
        $this->listingRow($listingId, $vendorId);
        $stmt = $this->db->prepare(
            "SELECT et.*, tt.name AS ticket_type_name, tt.category, tt.price, tt.access_rules, tt.description AS ticket_description,
                    b.payment_status, b.payment_gateway, b.transaction_id, b.booked_at, b.confirmed_at, b.details AS booking_details
             FROM event_tickets et
             JOIN ticket_types tt ON tt.id = et.ticket_type_id
             LEFT JOIN bookings b ON b.id = et.booking_id
             WHERE et.id=? AND et.listing_id=? LIMIT 1"
        );
        $stmt->execute([$ticketId, $listingId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['ticket_id' => 'Attendee record not found.']);
        $view = $this->attendeeView($row, true);

        // Timeline: authoritative audit rows + synthesized lifecycle events
        $timeline = [];
        $push = function (?string $at, string $event, string $detail = '', string $actor = '') use (&$timeline) {
            if (!$at) return;
            $timeline[] = ['at' => $at, 'event' => $event, 'detail' => $detail, 'actor' => $actor];
        };
        $push($row['booked_at'] ?? null, 'Order placed', isset($row['booking_id']) ? $row['booking_id'] : '');
        $push($row['confirmed_at'] ?? null, 'Payment confirmed', (string) ($row['payment_gateway'] ?? ''));
        $push($row['created_at'], 'Ticket issued', $row['id']);
        $push($row['last_sent_at'], 'Ticket delivered', 'Email / SMS');

        $auditStmt = $this->db->prepare(
            "SELECT action, actor_name, actor_id, details, created_at FROM event_ticket_audit
             WHERE listing_id=? AND (ticket_id=? OR booking_id=?) ORDER BY created_at DESC LIMIT 30"
        );
        $auditStmt->execute([$listingId, $ticketId, $view['booking_id']]);
        foreach ($auditStmt->fetchAll() as $a) {
            $details = json_decode((string) ($a['details'] ?? '{}'), true) ?: [];
            $push($a['created_at'], (string) ($a['action'] ?? ''), is_string($details) ? $details : (is_array($details) ? (json_encode(array_slice($details, 0, 6))) : ''), (string) ($a['actor_name'] ?? ($a['actor_id'] ?? '')));
        }
        usort($timeline, fn($x, $y) => strcmp((string) $y['at'], (string) $x['at']));
        $timeline = array_slice($timeline, 0, 24);

        $transfers = [];
        $trStmt = $this->db->prepare('SELECT * FROM event_ticket_transfers WHERE ticket_id=? ORDER BY created_at DESC');
        $trStmt->execute([$ticketId]);
        foreach ($trStmt->fetchAll() as $t) {
            $transfers[] = [
                'id' => $t['id'], 'from_holder_name' => $t['from_holder_name'], 'to_holder_name' => $t['to_holder_name'],
                'to_phone' => $t['to_phone'], 'to_email' => $t['to_email'], 'initiated_by_type' => $t['initiated_by_type'],
                'reason' => $t['reason'], 'status' => $t['status'], 'created_at' => $t['created_at'], 'completed_at' => $t['completed_at'],
            ];
        }

        $refunds = [];
        $rfStmt = $this->db->prepare('SELECT id, amount, reason, status, requested_at, decided_at FROM event_ticket_refunds WHERE ticket_id=? OR booking_id=? ORDER BY requested_at DESC');
        $rfStmt->execute([$ticketId, $view['booking_id']]);
        foreach ($rfStmt->fetchAll() as $r) {
            $refunds[] = ['id' => $r['id'], 'amount' => (float) $r['amount'], 'reason' => $r['reason'], 'status' => strtoupper((string) $r['status']), 'requested_at' => $r['requested_at'], 'decided_at' => $r['decided_at']];
        }

        return ['attendee' => $view, 'timeline' => $timeline, 'transfers' => $transfers, 'refunds' => $refunds];
    }

    // ------------------------------------------------------------------
    // Check-in (recorded here; live scanning lives in Check-In LIVE)
    // ------------------------------------------------------------------

    public function checkIn(string $listingId, string $vendorId, array $user, array $input): array
    {
        $this->listingRow($listingId, $vendorId);
        $ticketId = trim((string) ($input['ticket_id'] ?? ''));
        $stmt = $this->db->prepare('SELECT * FROM event_tickets WHERE id=? AND listing_id=? LIMIT 1');
        $stmt->execute([$ticketId, $listingId]);
        $ticket = $stmt->fetch();
        if (!is_array($ticket)) throw UthengaTieErrors::validation(['ticket_id' => 'Attendee not found.']);
        if (in_array($ticket['status'], ['CANCELLED', 'REFUNDED'], true)) throw UthengaTieErrors::validation(['ticket_id' => 'Cancelled / refunded attendees cannot be checked in.']);
        if (!empty($ticket['checked_in_at'])) throw UthengaTieErrors::validation(['ticket_id' => 'This attendee is already checked in.']);

        $gate = trim((string) ($input['gate'] ?? ''));
        if ($gate === '') $gate = self::GATES[array_rand(self::GATES)];

        $this->db->prepare("UPDATE event_tickets SET status='CHECKED_IN', checked_in_at=NOW(), checked_in_by=?, checked_in_gate=? WHERE id=?")
            ->execute([$user['id'], $gate, $ticketId]);
        $this->audit($listingId, $user['id'], 'ticket.checked_in', ['ticket_id' => $ticketId, 'gate' => $gate], (int) $ticket['ticket_type_id'], $ticketId, $ticket['booking_id']);
        return ['ticket_id' => $ticketId, 'checked_in_at' => date('Y-m-d H:i:s'), 'gate' => $gate, 'holder_name' => $ticket['holder_name']];
    }

    // ------------------------------------------------------------------
    // Manual attendee registration
    // ------------------------------------------------------------------

    public function addAttendee(string $listingId, string $vendorId, array $user, array $input): array
    {
        $listing = $this->listingRow($listingId, $vendorId);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') throw UthengaTieErrors::validation(['name' => 'Attendee name is required.']);
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw UthengaTieErrors::validation(['email' => 'A valid email is required.']);
        $phone = trim((string) ($input['phone'] ?? ''));
        if ($email === '' && $phone === '') throw UthengaTieErrors::validation(['email' => 'Provide an email or phone number.']);

        $typeId = (int) ($input['ticket_type_id'] ?? 0);
        $typeStmt = $this->db->prepare('SELECT * FROM ticket_types WHERE id=? AND listing_id=? LIMIT 1');
        $typeStmt->execute([$typeId, $listingId]);
        $type = $typeStmt->fetch();
        if (!is_array($type)) throw UthengaTieErrors::validation(['ticket_type_id' => 'Ticket type not found for this event.']);

        $payment = ucfirst(strtolower(trim((string) ($input['payment_status'] ?? 'Complimentary'))));
        if (!in_array($payment, ['Paid', 'Pending', 'Complimentary'], true)) throw UthengaTieErrors::validation(['payment_status' => 'Use Paid, Pending or Complimentary.']);
        $isComp = $payment === 'Complimentary';

        $amount = $isComp ? 0.0 : $this->money($input['amount'] ?? $type['price'], 'amount');
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($isComp && $reason === '') throw UthengaTieErrors::validation(['reason' => 'A complimentary reason is required (Sponsor, Media, Staff, Guest, Partner, Other).']);

        $organization = trim((string) ($input['organization'] ?? ''));

        // Resolve customer (bookings.customer_id is required)
        $customerId = null;
        if ($email !== '') {
            $custStmt = $this->db->prepare("SELECT id FROM users WHERE LOWER(email)=? LIMIT 1");
            $custStmt->execute([$email]);
            $customerId = (string) ($custStmt->fetchColumn() ?: '');
        }
        if ($customerId === '') {
            $exists = $this->countVal('SELECT COUNT(*) FROM users WHERE email=?', [$email !== '' ? $email : ($phone . '@manual.attendee')]);
            if ($exists > 0) {
                $custStmt = $this->db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
                $custStmt->execute([$email !== '' ? $email : ($phone . '@manual.attendee')]);
                $customerId = (string) $custStmt->fetchColumn();
            } else {
                $customerId = 'c-' . strtolower(bin2hex(random_bytes(6)));
                $this->db->prepare("INSERT INTO users (id, name, email, role, password_hash, created_at) VALUES (?,?,?,'Customer',?,NOW())")
                    ->execute([$customerId, $name, $email !== '' ? $email : ($phone . '@manual.attendee'), password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT)]);
            }
        }

        $bookingId = 'BKG-' . strtoupper(bin2hex(random_bytes(4)));
        $details = ['quantity' => 1, 'ticket_type_id' => $typeId, 'phone' => $phone ?: null, 'organization' => $organization ?: null];
        if ($isComp) $details['comp_reason'] = $reason;
        $this->db->prepare(
            "INSERT INTO bookings (id, listing_id, ticket_type_id, quantity, listing_title, listing_image, listing_type, customer_id, customer_name, customer_email, booking_date, booked_at, details, currency, total_price, commission_paid, discount_amount, tax_amount, commission_amount, payment_status, payment_gateway, booking_status, reference_name, transaction_id, qr_code, confirmed_at)
             VALUES (?,?,?,1,?,?,'event',?,?,?,NOW(),NOW(),?,?,?,0,0,0,0,?,?,?,?,?,?,?)"
        )->execute([
            $bookingId, $listingId, $typeId, $listing['title'], (string) ($listing['image'] ?? ''),
            $customerId, $name, $email !== '' ? $email : null,
            json_encode($details, JSON_UNESCAPED_SLASHES), 'MWK', $amount,
            $isComp ? 'Paid' : ($payment === 'Paid' ? 'Paid' : 'Pending'),
            $isComp ? 'Complimentary' : 'Manual (Organizer)',
            $isComp ? 'confirmed' : ($payment === 'Paid' ? 'confirmed' : 'pending'),
            $isComp ? 'Complimentary: ' . $reason : 'Manual registration',
            null,
            $isComp ? 'COMP-' . strtoupper(bin2hex(random_bytes(3))) : null,
            $payment === 'Pending' ? null : date('Y-m-d H:i:s'),
        ]);

        $ticketId = $this->issueTicketForBooking([
            'id' => $bookingId, 'listing_id' => $listingId, 'listing_title' => $listing['title'],
            'ticket_type_id' => $typeId, 'customer_name' => $name, 'customer_email' => $email !== '' ? $email : null,
            'details' => json_encode($details, JSON_UNESCAPED_SLASHES),
        ], $isComp ? 'complimentary_' . strtolower(str_replace(' ', '_', $reason)) : 'manual');

        $this->audit($listingId, $user['id'], 'attendee.created', array_merge(
            ['attendee' => $name, 'email' => $email, 'ticket_id' => $ticketId, 'booking_id' => $bookingId, 'reason' => $isComp ? $reason : null],
            $isComp ? ['complimentary_by' => $user['id']] : []
        ), $typeId, $ticketId, $bookingId);

        return ['booking_id' => $bookingId, 'ticket_id' => $ticketId, 'attendee' => $name];
    }

    // ------------------------------------------------------------------
    // Batch import with validation report (never silently imports bad rows)
    // ------------------------------------------------------------------

    public function import(string $listingId, string $vendorId, array $user, array $input): array
    {
        $rows = $input['rows'] ?? [];
        if (!is_array($rows) || count($rows) === 0) throw UthengaTieErrors::validation(['rows' => 'Provide at least one attendee row.']);
        $rows = array_slice($rows, 0, 200);

        $types = [];
        $st = $this->db->prepare('SELECT id, name FROM ticket_types WHERE listing_id=? ORDER BY sort_order, id');
        $st->execute([$listingId]);
        foreach ($st->fetchAll() as $r) $types[$r['name']] = (int) $r['id'];

        $created = 0; $duplicates = []; $invalid = [];
        $seenEmails = [];
        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $phone = trim((string) ($row['phone'] ?? ''));
            $org = trim((string) ($row['organization'] ?? ''));
            $typeName = trim((string) ($row['ticket_type'] ?? ($row['ticket_type_name'] ?? '')));
            $typeId = (int) ($row['ticket_type_id'] ?? 0);

            if ($name === '') { $invalid[] = ['row' => $i + 1, 'error' => 'Name is required', 'name' => $name]; continue; }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $invalid[] = ['row' => $i + 1, 'error' => 'Invalid email', 'name' => $name]; continue; }
            $resolved = $typeId > 0 ? $typeId : ($typeName !== '' && isset($types[$typeName]) ? $types[$typeName] : 0);
            if (!$resolved) { $invalid[] = ['row' => $i + 1, 'error' => "Unknown ticket type: $typeName", 'name' => $name]; continue; }

            $dup = false;
            if ($email !== '' && (isset($seenEmails[$email]) || $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND LOWER(holder_email)=?', [$listingId, $email]) > 0)) $dup = true;
            if ($phone !== '' && !$dup && $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND holder_phone=?', [$listingId, $phone]) > 0) $dup = true;
            if ($dup) { $duplicates[] = ['row' => $i + 1, 'name' => $name, 'email' => $email, 'phone' => $phone]; continue; }

            $this->addAttendee($listingId, $vendorId, $user, [
                'name' => $name, 'email' => $email, 'phone' => $phone, 'organization' => $org,
                'ticket_type_id' => $resolved, 'payment_status' => 'Paid', 'reason' => '',
            ]);
            if ($email !== '') $seenEmails[$email] = true;
            $created++;
        }

        $this->audit($listingId, $user['id'], 'attendees.imported', ['total' => count($rows), 'created' => $created, 'duplicates' => count($duplicates), 'invalid' => count($invalid)]);
        return [
            'total' => count($rows), 'created' => $created,
            'duplicates' => $duplicates, 'invalid' => $invalid,
        ];
    }

    // ------------------------------------------------------------------
    // Export payloads
    // ------------------------------------------------------------------

    public function export(string $listingId, string $vendorId, string $type): array
    {
        $filters = ['limit' => 1000];
        if ($type === 'checked_in') $filters['attendance'] = 'checked_in';
        elseif ($type === 'not_arrived') $filters['attendance'] = 'not_arrived';
        if ($type === 'vip') {
            $rows = array_values(array_filter($this->list($listingId, $vendorId, ['limit' => 1000]), fn($a) => in_array(strtoupper((string) $a['category']), ['VIP', 'VVIP'], true)));
        } else {
            $rows = $this->list($listingId, $vendorId, $filters);
        }
        return ['filename' => 'attendees-' . $type . '-' . $listingId, 'rows' => $rows];
    }

    // ------------------------------------------------------------------
    // Centralized messaging passthrough (audited; delivery lives in Messages)
    // ------------------------------------------------------------------

    public function message(string $listingId, string $vendorId, array $user, array $input): array
    {
        $this->listingRow($listingId, $vendorId);
        $ticketIds = $input['ticket_ids'] ?? [];
        if (!is_array($ticketIds) || count($ticketIds) === 0) throw UthengaTieErrors::validation(['ticket_ids' => 'Select at least one attendee.']);
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') throw UthengaTieErrors::validation(['subject' => 'Message subject is required.']);
        $body = trim((string) ($input['body'] ?? ''));
        $ticketIds = array_slice($ticketIds, 0, 500);

        $meta = $this->db->prepare('SELECT ticket_type_id, booking_id, holder_name FROM event_tickets WHERE id=? AND listing_id=? LIMIT 1');
        foreach ($ticketIds as $tid) {
            $meta->execute([(string) $tid, $listingId]);
            $row = $meta->fetch();
            if (is_array($row)) {
                $this->audit($listingId, $user['id'], 'attendee.message_sent', [
                    'subject' => $subject, 'body' => mb_substr($body, 0, 120), 'ticket_id' => $tid, 'recipient' => $row['holder_name'],
                ], (int) ($row['ticket_type_id'] ?? 0), (string) $tid, (string) ($row['booking_id'] ?? ''));
            }
        }
        return ['sent' => count($ticketIds), 'channel' => 'Uthenga Notification (Messages hub fails over to Email/SMS)'];
    }
}