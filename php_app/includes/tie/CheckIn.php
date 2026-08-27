<?php
/**
 * Uthenga — Check-In LIVE: Operational Command Center.
 *
 * Digital gate-control system for physical admission. Every scan is validated
 * server-side through a deterministic pipeline and recorded in an immutable
 * scan log (checkin_scans). The frontend never decides admission.
 *
 *   QR received → decode → lookup → signature verify → event → lifecycle
 *   → payment → access zone → previous check-in → policy → ALLOW / DENY / REVIEW
 *
 * Relationship: Tickets (sold) → Attendees (registered) → Check-In LIVE (arriving).
 */
class UthengaCheckInService
{
    private PDO $db;

    private const GATES = ['Gate A', 'Gate B', 'Gate C', 'Gate D', 'VIP Entrance', 'Staff Entrance', 'Backstage', 'Press', 'Conference Entrance'];

    // Requested gate → access zone keyword
    private const GATE_ZONES = [
        'VIP Entrance' => 'VIP',
        'Staff Entrance' => 'Staff',
        'Backstage' => 'Backstage',
        'Press' => 'Press',
        'Conference Entrance' => 'General',
        'Gate A' => 'General', 'Gate B' => 'General', 'Gate C' => 'General', 'Gate D' => 'General',
    ];

    private const ZONE_TICKET_KEYWORDS = ['VIP' => 'VIP', 'VVIP' => 'VIP', 'Staff' => 'Staff', 'Backstage' => 'Backstage', 'Press' => 'Press'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------
    // Shared internals
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

    private function recordScan(string $requestId, string $listingId, array $rec, array $user): void
    {
        $this->db->prepare(
            'INSERT INTO checkin_scans (request_id, listing_id, ticket_id, booking_id, decision, reason_code, gate, device_id, operator_id, operator_name, source, idempotency_key, details)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $requestId, $listingId, $rec['ticket_id'] ?? null, $rec['booking_id'] ?? null,
            $rec['decision'], $rec['reason_code'] ?? null, $rec['gate'] ?? null, $rec['device_id'] ?? null,
            $user['id'], $user['name'] ?? '', $rec['source'] ?? 'scan', $rec['idempotency_key'] ?? null,
            isset($rec['details']) ? json_encode($rec['details'], JSON_UNESCAPED_SLASHES) : null,
        ]);
    }

    private function operatorName(string $actorId): string
    {
        if ($actorId === '') return '';
        $stmt = $this->db->prepare('SELECT name FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$actorId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

    // ------------------------------------------------------------------
    // Scan code decoding
    // ------------------------------------------------------------------

    private function decodeCode(string $code): array
    {
        $code = trim($code);
        if ($code === '') return ['ok' => false];
        if (str_starts_with(strtoupper($code), 'UTHENGA|')) {
            $parts = explode('|', $code);
            if (count($parts) >= 2) return ['ok' => true, 'ticket_id' => trim($parts[1]), 'token' => $parts[2] ?? '', 'signature' => $parts[3] ?? ''];
        }
        if (preg_match('/^[a-f0-9]{16,64}$/i', $code)) return ['ok' => true, 'qr_token' => $code];
        return ['ok' => true, 'ticket_id' => $code];
    }

    private function lookupTicket(string $ticketId, ?string $qrToken = null, ?string $token = null, ?string $signature = null): ?array
    {
        $ticket = null;
        if ($ticketId !== '') {
            $stmt = $this->db->prepare(
                "SELECT et.*, tt.name AS ticket_type_name, tt.category, tt.price, tt.access_rules,
                        b.payment_status, b.payment_gateway, b.transaction_id, b.details AS booking_details,
                        l.title AS listing_title
                 FROM event_tickets et
                 JOIN ticket_types tt ON tt.id = et.ticket_type_id
                 LEFT JOIN bookings b ON b.id = et.booking_id
                 LEFT JOIN listings l ON l.id = et.listing_id
                 WHERE et.id = ? LIMIT 1"
            );
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch() ?: null;
            if ($ticket && $token !== '' && $signature !== '') {
                $expected = hash_hmac('sha256', $ticket['id'] . '.' . $token, 'uthenga-tie-ticket-v1');
                if (!hash_equals((string) $signature, $expected) || !hash_equals((string) $ticket['qr_token'], $token)) {
                    $ticket['_signature_bad'] = true;
                }
            }
        }
        if (!$ticket && $qrToken !== null) {
            $stmt = $this->db->prepare(
                "SELECT et.*, tt.name AS ticket_type_name, tt.category, tt.price, tt.access_rules,
                        b.payment_status, b.payment_gateway, b.transaction_id, b.details AS booking_details,
                        l.title AS listing_title
                 FROM event_tickets et
                 JOIN ticket_types tt ON tt.id = et.ticket_type_id
                 LEFT JOIN bookings b ON b.id = et.booking_id
                 LEFT JOIN listings l ON l.id = et.listing_id
                 WHERE et.qr_token = ? LIMIT 1"
            );
            $stmt->execute([$qrToken]);
            $ticket = $stmt->fetch() ?: null;
        }
        return $ticket ?: null;
    }

    // ------------------------------------------------------------------
    // Deterministic validation pipeline
    // ------------------------------------------------------------------

    private function pipeline(string $listingId, array $listing, string $code, string $gate): array
    {
        $decoded = $this->decodeCode($code);
        if (!$decoded['ok']) {
            return ['decision' => 'DENY', 'reason_code' => 'SCAN_MALFORMED', 'message' => 'The code could not be read. Please rescan or use manual lookup.', 'ticket_id' => null, 'booking_id' => null];
        }

        $ticket = $this->lookupTicket($decoded['ticket_id'] ?? '', $decoded['qr_token'] ?? null, $decoded['token'] ?? '', $decoded['signature'] ?? '');
        if (!$ticket) {
            return ['decision' => 'DENY', 'reason_code' => 'TICKET_NOT_FOUND', 'message' => 'Ticket not recognized.', 'ticket_id' => $decoded['ticket_id'] ?? $decoded['qr_token'] ?? $code, 'booking_id' => null];
        }
        if (!empty($ticket['_signature_bad'])) {
            return ['decision' => 'DENY', 'reason_code' => 'SIGNATURE_MISMATCH', 'message' => 'Ticket authenticity could not be verified.', 'ticket_id' => $ticket['id'], 'booking_id' => $ticket['booking_id']];
        }

        $ticketId = (string) $ticket['id'];
        $status = strtoupper((string) $ticket['status']);

        // Event validation
        if ($ticket['listing_id'] !== $listingId) {
            return [
                'decision' => 'DENY', 'reason_code' => 'WRONG_EVENT',
                'message' => 'This ticket belongs to a different event.',
                'ticket_id' => $ticketId, 'booking_id' => $ticket['booking_id'],
                'ticket' => $ticket, 'details' => ['ticket_event' => (string) ($ticket['listing_title'] ?? 'Unknown event'), 'current_event' => $listing['title']],
            ];
        }

        // Lifecycle
        if ($status === 'CANCELLED') {
            return ['decision' => 'DENY', 'reason_code' => 'TICKET_CANCELLED', 'message' => 'This ticket was cancelled and can no longer be used.', 'ticket_id' => $ticketId, 'booking_id' => $ticket['booking_id'], 'ticket' => $ticket];
        }
        if ($status === 'REFUNDED') {
            return ['decision' => 'DENY', 'reason_code' => 'TICKET_REFUNDED', 'message' => 'This ticket has been refunded and is no longer valid.', 'ticket_id' => $ticketId, 'booking_id' => $ticket['booking_id'], 'ticket' => $ticket];
        }

        // Payment
        $paymentStatus = strtolower((string) ($ticket['payment_status'] ?? 'Paid'));
        $gateway = strtolower((string) ($ticket['payment_gateway'] ?? ''));
        if ($gateway === 'complimentary') {
            $paymentStatus = 'paid';
        }
        if ($paymentStatus === 'pending') {
            return ['decision' => 'REVIEW', 'reason_code' => 'PAYMENT_PENDING', 'message' => 'Payment for this ticket is still pending.', 'ticket_id' => $ticketId, 'booking_id' => $ticket['booking_id'], 'ticket' => $ticket];
        }
        if ($paymentStatus === 'failed') {
            return ['decision' => 'DENY', 'reason_code' => 'PAYMENT_FAILED', 'message' => 'Payment for this ticket failed.', 'ticket_id' => $ticketId, 'booking_id' => $ticket['booking_id'], 'ticket' => $ticket];
        }

        // Access zone
        $zone = self::GATE_ZONES[$gate] ?? 'General';
        $allowed = $this->accessZonesOf($ticket);
        if (count($allowed) > 0 && !in_array($zone, $allowed, true)) {
            return [
                'decision' => 'DENY', 'reason_code' => 'ACCESS_RESTRICTED',
                'message' => 'This ticket does not permit entry through ' . $gate . '.',
                'ticket_id' => $ticketId, 'booking_id' => $ticket['booking_id'], 'ticket' => $ticket,
                'details' => ['requested_gate' => $gate, 'requested_zone' => $zone, 'allowed_zones' => $allowed],
            ];
        }

        // Previous check-in
        if (!empty($ticket['checked_in_at'])) {
            return [
                'decision' => 'REVIEW', 'reason_code' => 'ALREADY_CHECKED_IN',
                'message' => 'This ticket has already been checked in.',
                'ticket_id' => $ticketId, 'booking_id' => $ticket['booking_id'], 'ticket' => $ticket,
                'details' => [
                    'first_entry_gate' => $ticket['checked_in_gate'] ?? null,
                    'first_entry_at' => $ticket['checked_in_at'],
                    'first_entry_by' => $ticket['checked_in_by'] ?? null,
                ],
            ];
        }

        return ['decision' => 'ALLOW', 'reason_code' => null, 'message' => 'Admitted.', 'ticket_id' => $ticketId, 'booking_id' => $ticket['booking_id'], 'ticket' => $ticket, 'zone' => $zone];
    }

    private function accessZonesOf(array $ticket): array
    {
        $rules = json_decode((string) ($ticket['access_rules'] ?? '[]'), true);
        if (!is_array($rules)) $rules = [];
        $rules = array_values(array_map('strval', $rules));
        $category = strtoupper((string) ($ticket['category'] ?? ''));
        foreach (self::ZONE_TICKET_KEYWORDS as $keyword => $zone) {
            if (str_contains($category, $keyword) && !in_array($zone, $rules, true)) $rules[] = $zone;
        }
        return array_values(array_unique($rules));
    }

    // ------------------------------------------------------------------
    // The scan operation (idempotent, audit-logged)
    // ------------------------------------------------------------------

    public function scan(string $listingId, string $vendorId, array $user, array $input, string $requestId): array
    {
        $listing = $this->listingRow($listingId, $vendorId);
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') throw UthengaTieErrors::validation(['code' => 'A ticket code is required.']);

        $gate = trim((string) ($input['gate'] ?? ''));
        if ($gate === '') $gate = self::GATES[array_rand(self::GATES)];
        $deviceId = substr(trim((string) ($input['device_id'] ?? '')), 0, 40);
        $idempotencyKey = substr(trim((string) ($input['idempotency_key'] ?? '')), 0, 48);

        // Idempotency: replay protection — a retransmitted scan must not admit twice.
        if ($idempotencyKey !== '') {
            $stmt = $this->db->prepare('SELECT decision, reason_code, gate, ticket_id, details FROM checkin_scans WHERE idempotency_key=? AND listing_id=? AND source IN (\'scan\',\'manual\') ORDER BY id DESC LIMIT 1');
            $stmt->execute([$idempotencyKey, $listingId]);
            $prev = $stmt->fetch();
            if (is_array($prev)) {
                return $this->decisionView($listing, $prev['decision'], $prev['reason_code'], $prev['ticket_id'] ?? '', $prev['gate'], $requestId, true);
            }
        }

        $result = $this->pipeline($listingId, $listing, $code, $gate);
        $decision = $result['decision'];
        $ticket = $result['ticket'] ?? null;

        if ($decision === 'ALLOW' && $ticket) {
            $this->db->prepare("UPDATE event_tickets SET status='CHECKED_IN', checked_in_at=NOW(), checked_in_by=?, checked_in_gate=? WHERE id=?")
                ->execute([$user['id'], $gate, $ticket['id']]);
            $ticket['checked_in_at'] = date('Y-m-d H:i:s');
            $ticket['checked_in_gate'] = $gate;
            $this->audit($listingId, $user['id'], 'checkin.scan_allow', ['ticket_id' => $ticket['id'], 'gate' => $gate, 'device_id' => $deviceId, 'request_id' => $requestId], (int) $ticket['ticket_type_id'], $ticket['id'], $ticket['booking_id']);
        } elseif ($ticket) {
            $this->audit($listingId, $user['id'], 'checkin.scan_' . strtolower($result['reason_code']), array_merge(['ticket_id' => $ticket['id'], 'gate' => $gate, 'device_id' => $deviceId, 'request_id' => $requestId], $result['details'] ?? []), (int) $ticket['ticket_type_id'], $ticket['id'], $ticket['booking_id']);
        }

        $this->recordScan($requestId, $listingId, [
            'decision' => $decision, 'reason_code' => $result['reason_code'] ?? null,
            'ticket_id' => $result['ticket_id'] ?? null, 'booking_id' => $result['booking_id'] ?? null,
            'gate' => $gate, 'device_id' => $deviceId, 'source' => 'scan', 'idempotency_key' => $idempotencyKey,
            'details' => array_merge($result['details'] ?? [], ['code_preview' => substr($code, 0, 40)]),
        ], $user);

        return $this->decisionView($listing, $decision, $result['reason_code'] ?? null, $result['ticket_id'] ?? '', $gate, $requestId, false, $ticket ?: null) + (empty($result['details']) ? [] : ['details' => $result['details']]);
    }

    private function decisionView(array $listing, string $decision, ?string $reasonCode, string $ticketId, ?string $gate, string $requestId, bool $replayed, ?array $ticket = null): array
    {
        $view = [
            'decision' => $decision,
            'reason_code' => $reasonCode,
            'message' => $reasonCode ? $this->reasonMessage($reasonCode) : 'Admitted.',
            'request_id' => $requestId,
            'replayed' => $replayed,
            'gate' => ['name' => $gate ?? ''],
            'event' => ['id' => $listing['event_id'] ?? null, 'title' => $listing['title'], 'start_date' => $listing['start_date'], 'start_time' => $listing['start_time']],
        ];
        if ($ticketId !== '') {
            $t = $ticket ?: $this->loadTicket($ticketId);
            if ($t) {
                $view['attendee'] = ['id' => $t['id'], 'name' => $t['holder_name'], 'email' => $t['holder_email'], 'phone' => $t['holder_phone']];
                $view['ticket'] = ['id' => $t['id'], 'type' => $t['ticket_type_name'], 'category' => $t['category'], 'price' => (float) $t['price']];
                $view['access'] = ['zone' => $gate ? (self::GATE_ZONES[$gate] ?? 'General') : null, 'allowed' => $this->accessZonesOf($t)];
                $view['attendance'] = [
                    'status' => strtoupper((string) $t['status']),
                    'checked_in_at' => $t['checked_in_at'],
                    'checked_in_gate' => $t['checked_in_gate'],
                    'first_entry_at' => $t['checked_in_at'],
                    'first_entry_gate' => $t['checked_in_gate'],
                ];
                $view['booking_id'] = $t['booking_id'];
            }
        }
        return $view;
    }

    private function reasonMessage(string $code): string
    {
        return match ($code) {
            'TICKET_NOT_FOUND' => 'Ticket not recognized.',
            'SIGNATURE_MISMATCH' => 'Ticket authenticity could not be verified.',
            'WRONG_EVENT' => 'This ticket belongs to a different event.',
            'TICKET_CANCELLED' => 'This ticket was cancelled and can no longer be used.',
            'TICKET_REFUNDED' => 'This ticket has been refunded and is no longer valid.',
            'PAYMENT_PENDING' => 'Payment for this ticket is still pending.',
            'PAYMENT_FAILED' => 'Payment for this ticket failed.',
            'ACCESS_RESTRICTED' => 'This ticket does not permit entry through this gate.',
            'ALREADY_CHECKED_IN' => 'This ticket has already been checked in.',
            'SUPERVISOR_OVERRIDE' => 'Override approved — entry granted by supervisor.',
            default => 'Entry cannot be granted for this ticket.',
        };
    }

    private function loadTicket(string $ticketId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT et.*, tt.name AS ticket_type_name, tt.category, tt.price, tt.access_rules,
                    b.payment_status, b.payment_gateway
             FROM event_tickets et
             JOIN ticket_types tt ON tt.id = et.ticket_type_id
             LEFT JOIN bookings b ON b.id = et.booking_id
             WHERE et.id = ? LIMIT 1"
        );
        $stmt->execute([$ticketId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    // ------------------------------------------------------------------
    // Manual lookup + manual admission
    // ------------------------------------------------------------------

    public function lookup(string $listingId, string $vendorId, string $q, int $limit = 12): array
    {
        $this->listingRow($listingId, $vendorId);
        $q = trim($q);
        if ($q === '') return [];
        $like = '%' . $q . '%';
        $stmt = $this->db->prepare(
            "SELECT et.id, et.holder_name, et.holder_email, et.holder_phone, et.status, et.checked_in_at, et.checked_in_gate,
                    tt.name AS ticket_type_name, tt.category, tt.price,
                    b.payment_status, b.payment_gateway
             FROM event_tickets et
             JOIN ticket_types tt ON tt.id = et.ticket_type_id
             LEFT JOIN bookings b ON b.id = et.booking_id
             WHERE et.listing_id = ? AND (et.id LIKE ? OR et.holder_name LIKE ? OR et.holder_email LIKE ? OR et.holder_phone LIKE ? OR et.booking_id LIKE ? OR et.qr_token LIKE ?)
             ORDER BY et.created_at DESC LIMIT " . min(max($limit, 1), 25)
        );
        $stmt->execute([$listingId, $like, $like, $like, $like, $like, $like]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = [
                'ticket_id' => $r['id'], 'name' => $r['holder_name'], 'email' => $r['holder_email'], 'phone' => $r['holder_phone'],
                'ticket_type_name' => $r['ticket_type_name'], 'category' => $r['category'], 'price' => (float) $r['price'],
                'payment_status' => $r['payment_gateway'] === 'Complimentary' ? 'Complimentary' : $r['payment_status'],
                'attendance_status' => !empty($r['checked_in_at']) ? 'CHECKED_IN' : strtoupper((string) $r['status']),
                'checked_in_at' => $r['checked_in_at'], 'checked_in_gate' => $r['checked_in_gate'],
                'allowed_zones' => $this->accessZonesOf($r),
            ];
        }
        return $out;
    }

    public function manual(string $listingId, string $vendorId, array $user, array $input, string $requestId): array
    {
        $listing = $this->listingRow($listingId, $vendorId);
        $ticketId = trim((string) ($input['ticket_id'] ?? ''));
        if ($ticketId === '') throw UthengaTieErrors::validation(['ticket_id' => 'Select an attendee to admit.']);
        $gate = trim((string) ($input['gate'] ?? ''));
        if ($gate === '') $gate = self::GATES[array_rand(self::GATES)];
        $deviceId = substr(trim((string) ($input['device_id'] ?? '')), 0, 40);

        $result = $this->pipeline($listingId, $listing, $ticketId, $gate);
        $ticket = $result['ticket'] ?? null;
        if ($result['decision'] !== 'ALLOW') {
            // Manual admit cannot bypass the pipeline without an override.
            $this->recordScan($requestId, $listingId, [
                'decision' => $result['decision'], 'reason_code' => $result['reason_code'] ?? null,
                'ticket_id' => $result['ticket_id'] ?? null, 'booking_id' => $result['booking_id'] ?? null,
                'gate' => $gate, 'device_id' => $deviceId, 'source' => 'manual',
                'details' => $result['details'] ?? [],
            ], $user);
            return $this->decisionView($listing, $result['decision'], $result['reason_code'], $result['ticket_id'] ?? '', $gate, $requestId, false, $ticket) + (empty($result['details']) ? [] : ['details' => $result['details']]);
        }

        $this->db->prepare("UPDATE event_tickets SET status='CHECKED_IN', checked_in_at=NOW(), checked_in_by=?, checked_in_gate=? WHERE id=?")
            ->execute([$user['id'], $gate, $ticket['id']]);
        $this->audit($listingId, $user['id'], 'checkin.manual_admit', ['ticket_id' => $ticket['id'], 'gate' => $gate, 'device_id' => $deviceId, 'request_id' => $requestId], (int) $ticket['ticket_type_id'], $ticket['id'], $ticket['booking_id']);
        $this->recordScan($requestId, $listingId, [
            'decision' => 'ALLOW', 'reason_code' => null, 'ticket_id' => $ticket['id'], 'booking_id' => $ticket['booking_id'],
            'gate' => $gate, 'device_id' => $deviceId, 'source' => 'manual',
        ], $user);
        return $this->decisionView($listing, 'ALLOW', null, $ticket['id'], $gate, $requestId, false, $ticket);
    }

    // ------------------------------------------------------------------
    // Supervisor override (explicit reason + full audit trail)
    // ------------------------------------------------------------------

    public function override(string $listingId, string $vendorId, array $user, array $input, string $requestId): array
    {
        $listing = $this->listingRow($listingId, $vendorId);
        $ticketId = trim((string) ($input['ticket_id'] ?? ''));
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($ticketId === '') throw UthengaTieErrors::validation(['ticket_id' => 'A ticket is required.']);
        if ($reason === '') throw UthengaTieErrors::validation(['reason' => 'An override reason is required for the audit trail.']);
        $gate = trim((string) ($input['gate'] ?? ''));
        if ($gate === '') $gate = self::GATES[array_rand(self::GATES)];
        $deviceId = substr(trim((string) ($input['device_id'] ?? '')), 0, 40);

        $ticket = $this->loadTicket($ticketId);
        if (!$ticket || $ticket['listing_id'] !== $listingId) throw UthengaTieErrors::validation(['ticket_id' => 'Attendee not found for this event.']);

        $original = [
            'status' => strtoupper((string) $ticket['status']),
            'checked_in_at' => $ticket['checked_in_at'], 'checked_in_gate' => $ticket['checked_in_gate'],
            'checked_in_by' => $ticket['checked_in_by'],
        ];
        $now = date('Y-m-d H:i:s');

        if (strtoupper((string) $ticket['status']) !== 'CHECKED_IN') {
            $this->db->prepare("UPDATE event_tickets SET status='CHECKED_IN', checked_in_at=?, checked_in_by=?, checked_in_gate=? WHERE id=?")
                ->execute([$now, $user['id'], $gate, $ticket['id']]);
            $ticket['checked_in_at'] = $now;
            $ticket['checked_in_gate'] = $gate;
            $ticket['checked_in_by'] = $user['id'];
        }

        $this->audit($listingId, $user['id'], 'checkin.override', [
            'ticket_id' => $ticketId, 'gate' => $gate, 'device_id' => $deviceId, 'request_id' => $requestId,
            'original_state' => $original, 'override_reason' => $reason, 'approving_staff' => $user['id'],
        ], (int) $ticket['ticket_type_id'], $ticketId, $ticket['booking_id']);

        $this->recordScan($requestId, $listingId, [
            'decision' => 'ALLOW', 'reason_code' => 'SUPERVISOR_OVERRIDE', 'ticket_id' => $ticketId,
            'booking_id' => $ticket['booking_id'], 'gate' => $gate, 'device_id' => $deviceId, 'source' => 'override',
            'details' => ['original_state' => $original, 'override_reason' => $reason],
        ], $user);

        $view = $this->decisionView($listing, 'ALLOW', 'SUPERVISOR_OVERRIDE', $ticketId, $gate, $requestId, false, $ticket);
        $view['override'] = ['reason' => $reason, 'original_state' => $original];
        return $view;
    }

    // ------------------------------------------------------------------
    // Exit tracking (event-configurable; one-entry events need not use it)
    // ------------------------------------------------------------------

    public function exit(string $listingId, string $vendorId, array $user, array $input, string $requestId): array
    {
        $this->listingRow($listingId, $vendorId);
        $ticketId = trim((string) ($input['ticket_id'] ?? ''));
        if ($ticketId === '') throw UthengaTieErrors::validation(['ticket_id' => 'A ticket is required.']);
        $gate = trim((string) ($input['gate'] ?? ''));
        if ($gate === '') $gate = 'Exit';

        $stmt = $this->db->prepare('SELECT * FROM event_tickets WHERE id=? AND listing_id=? LIMIT 1');
        $stmt->execute([$ticketId, $listingId]);
        $ticket = $stmt->fetch();
        if (!is_array($ticket)) throw UthengaTieErrors::validation(['ticket_id' => 'Attendee not found for this event.']);
        if (strtoupper((string) $ticket['status']) === 'CHECKED_OUT') throw UthengaTieErrors::validation(['ticket_id' => 'This attendee has already exited.']);

        $this->db->prepare("UPDATE event_tickets SET status='CHECKED_OUT', checked_out_at=NOW(), checked_out_by=?, checked_out_gate=? WHERE id=?")
            ->execute([$user['id'], $gate, $ticket['id']]);
        $this->audit($listingId, $user['id'], 'checkin.exit', ['ticket_id' => $ticketId, 'gate' => $gate, 'device_id' => (string) ($input['device_id'] ?? ''), 'request_id' => $requestId], (int) $ticket['ticket_type_id'], $ticketId, $ticket['booking_id']);
        $this->recordScan($requestId, $listingId, [
            'decision' => 'ALLOW', 'reason_code' => 'EXIT', 'ticket_id' => $ticketId, 'booking_id' => $ticket['booking_id'],
            'gate' => $gate, 'device_id' => substr(trim((string) ($input['device_id'] ?? '')), 0, 40), 'source' => 'exit',
        ], $user);
        return ['ticket_id' => $ticketId, 'status' => 'CHECKED_OUT', 'gate' => $gate, 'exited_at' => date('Y-m-d H:i:s')];
    }

    // ------------------------------------------------------------------
    // Command-center workspace
    // ------------------------------------------------------------------

    public function workspace(string $listingId, string $vendorId): array
    {
        $listing = $this->listingRow($listingId, $vendorId);

        $total = $this->countVal("SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND status IN ('ISSUED','CHECKED_IN','CHECKED_OUT')", [$listingId]);
        $checkedIn = $this->countVal("SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at IS NOT NULL AND status IN ('ISSUED','CHECKED_IN','CHECKED_OUT')", [$listingId]);
        $last15 = $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at >= (NOW() - INTERVAL 15 MINUTE)', [$listingId]);
        $last60 = $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at >= (NOW() - INTERVAL 60 MINUTE)', [$listingId]);
        $today = $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at >= CURDATE()', [$listingId]);

        $peak = 0.0;
        $pkStmt = $this->db->prepare(
            "SELECT MAX(cnt) FROM (SELECT DATE_FORMAT(checked_in_at, '%Y-%m-%d %H:%i') AS m, COUNT(*) cnt
             FROM event_tickets WHERE listing_id=? AND checked_in_at >= (NOW() - INTERVAL 60 MINUTE) GROUP BY m) t"
        );
        $pkStmt->execute([$listingId]);
        $peak = (int) ($pkStmt->fetchColumn() ?: 0);
        $rate = $last15 > 0 ? round($last15 / 15, 1) : 0.0;

        // Phase
        $status = strtoupper((string) ($listing['event_status'] ?? ''));
        $phase = 'live';
        if (in_array($status, ['CLOSED', 'ENDED', 'CANCELLED', 'COMPLETED'], true)) $phase = 'closed';
        else {
            $start = $listing['start_date'] && $listing['start_time'] ? strtotime($listing['start_date'] . ' ' . $listing['start_time']) : 0;
            if ($start > time()) $phase = 'upcoming';
        }

        $gates = [];
        $gStmt = $this->db->prepare(
            "SELECT COALESCE(checked_in_gate, 'Unassigned') AS gate, COUNT(*) AS cnt,
                    SUM(checked_in_at >= (NOW() - INTERVAL 15 MINUTE)) AS recent
             FROM event_tickets WHERE listing_id=? AND checked_in_at IS NOT NULL GROUP BY gate ORDER BY cnt DESC"
        );
        $gStmt->execute([$listingId]);
        foreach ($gStmt->fetchAll() as $r) {
            $gates[] = ['gate' => $r['gate'], 'count' => (int) $r['cnt'], 'rate_per_min' => round((int) ($r['recent'] ?? 0) / 15, 1)];
        }

        $activity = [];
        $aStmt = $this->db->prepare(
            "SELECT id, request_id, ticket_id, decision, reason_code, gate, device_id, operator_name, source, details, created_at
             FROM checkin_scans WHERE listing_id=? ORDER BY id DESC LIMIT 20"
        );
        $aStmt->execute([$listingId]);
        foreach ($aStmt->fetchAll() as $r) {
            $details = json_decode((string) ($r['details'] ?? '{}'), true);
            $name = null;
            $type = null;
            if ($r['ticket_id']) {
                $tStmt = $this->db->prepare('SELECT et.holder_name, tt.name AS tname FROM event_tickets et JOIN ticket_types tt ON tt.id=et.ticket_type_id WHERE et.id=? LIMIT 1');
                $tStmt->execute([$r['ticket_id']]);
                $tRow = $tStmt->fetch();
                if (is_array($tRow)) { $name = $tRow['holder_name']; $type = $tRow['tname']; }
            }
            $activity[] = [
                'id' => (int) $r['id'], 'request_id' => $r['request_id'], 'ticket_id' => $r['ticket_id'],
                'decision' => $r['decision'], 'reason_code' => $r['reason_code'], 'gate' => $r['gate'],
                'device_id' => $r['device_id'], 'operator' => $r['operator_name'], 'source' => $r['source'],
                'holder_name' => $name, 'ticket_type_name' => $type,
                'at' => $r['created_at'],
                'details' => is_array($details) ? $details : [],
            ];
        }

        $devices = [];
        $dStmt = $this->db->prepare(
            "SELECT device_id, COUNT(*) AS scans, MAX(created_at) AS last_seen
             FROM checkin_scans WHERE listing_id=? AND device_id IS NOT NULL AND device_id <> ''
             AND created_at >= (NOW() - INTERVAL 24 HOUR)
             GROUP BY device_id ORDER BY last_seen DESC LIMIT 12"
        );
        $dStmt->execute([$listingId]);
        foreach ($dStmt->fetchAll() as $r) {
            $devices[] = ['device_id' => $r['device_id'], 'scans' => (int) $r['scans'], 'last_seen' => $r['last_seen']];
        }

        $stats = $this->scanStats($listingId);

        return [
            'listing' => [
                'id' => $listingId, 'title' => $listing['title'], 'event_id' => $listing['event_id'],
                'event_status' => $listing['event_status'], 'start_date' => $listing['start_date'],
                'start_time' => $listing['start_time'], 'venue_name' => $listing['venue_name'],
                'venue_city' => $listing['venue_city'],
            ],
            'phase' => $phase,
            'counters' => [
                'total' => $total, 'checked_in' => $checkedIn, 'remaining' => max($total - $checkedIn, 0),
                'checkin_rate' => $total > 0 ? round(100 * $checkedIn / $total, 1) : 0.0,
                'today' => $today, 'last_15' => $last15, 'last_60' => $last60,
                'rate_per_min' => $rate, 'peak_rate_per_min' => $peak,
            ],
            'gates' => $gates,
            'activity' => $activity,
            'devices' => $devices,
            'stats' => $stats,
            'insights' => $this->insights($total, $checkedIn, $rate, $gates, $stats),
            'gates_available' => self::GATES,
            'final_report' => $phase === 'closed' ? $this->finalReport($listingId, $total, $checkedIn) : null,
            'schema_version' => 'tie-checkin-command/v1',
        ];
    }

    private function scanStats(string $listingId): array
    {
        $window = 'NOW() - INTERVAL 30 MINUTE';
        $stmt = $this->db->prepare("SELECT decision, reason_code, source, COUNT(*) cnt FROM checkin_scans WHERE listing_id=? AND created_at >= $window GROUP BY decision, reason_code, source");
        $stmt->execute([$listingId]);
        $rows = $stmt->fetchAll();
        $total = array_sum(array_map(fn($r) => (int) $r['cnt'], $rows));
        $byCode = [];
        foreach ($rows as $r) $byCode[$r['reason_code'] ?? $r['decision']] = (int) $r['cnt'];
        $exits = array_sum(array_map(fn($r) => ($r['source'] === 'exit') ? (int) $r['cnt'] : 0, $rows));
        $overrides = (int) ($byCode['SUPERVISOR_OVERRIDE'] ?? 0);
        $allowed = (int) ($byCode['ALLOW'] ?? 0);
        $denied = max($total - $exits - $overrides - $allowed, 0);
        $admissions = $allowed + $overrides;
        return [
            'window_minutes' => 30,
            'total_scans' => $total,
            'allowed' => $allowed,
            'overrides' => $overrides,
            'exits' => $exits,
            'denied' => $denied,
            'admission_attempts' => $admissions + $denied,
            'duplicates' => (int) ($byCode['ALREADY_CHECKED_IN'] ?? 0),
            'rejection_rate' => ($admissions + $denied) > 0 ? round(100 * $denied / ($admissions + $denied), 1) : 0.0,
            'by_code' => $byCode,
        ];
    }

    private function insights(int $total, int $checkedIn, float $rate, array $gates, array $stats): array
    {
        $out = [];
        if ($total > 0) {
            $remaining = $total - $checkedIn;
            if ($rate > 0) {
                $queue = round($rate * 15);
                $out[] = ['level' => 'info', 'message' => sprintf('Processing about %.0f people/min — roughly %d attendees may still be in queue over the next 15 minutes.', $rate, $queue)];
            } else {
                $out[] = ['level' => 'info', 'message' => 'No arrivals detected in the last 15 minutes.'];
            }
        }
        if (count($gates) > 1) {
            $max = $gates[0];
            $min = $gates[count($gates) - 1];
            if ($max['count'] > 0 && $min['count'] > 0) {
                $gap = 100 - round(100 * $min['count'] / $max['count']);
                if ($gap >= 30) {
                    $out[] = ['level' => 'warn', 'message' => sprintf('%s is processing %d%% fewer attendees than %s — consider redirecting incoming traffic.', $min['gate'], $gap, $max['gate'])];
                }
            }
        }
        if ($stats['rejection_rate'] >= 15) {
            $out[] = ['level' => 'warn', 'message' => sprintf('%.1f%% of scans in the last %d minutes were rejected (%d). Check for ticket distribution problems.', $stats['rejection_rate'], $stats['window_minutes'], $stats['denied'])];
        }
        if ($stats['duplicates'] >= 3) {
            $out[] = ['level' => 'warn', 'message' => $stats['duplicates'] . ' duplicate-scan attempts in the last ' . $stats['window_minutes'] . ' minutes — possible ticket sharing. Review activity feed.'];

        }
        if ($stats['total_scans'] > 0 && $stats['allowed'] === $stats['total_scans']) {
            $out[] = ['level' => 'info', 'message' => 'All scans in the last ' . $stats['window_minutes'] . ' minutes were admitted cleanly.'];
        }
        return $out;
    }

    private function finalReport(string $listingId, int $total, int $checkedIn): array
    {
        $report = [];
        $stmt = $this->db->prepare(
            "SELECT tt.name, tt.category,
                    SUM(et.status IN ('ISSUED','CHECKED_IN','CHECKED_OUT')) AS total,
                    SUM(et.checked_in_at IS NOT NULL AND et.status IN ('ISSUED','CHECKED_IN','CHECKED_OUT')) AS arrived
             FROM event_tickets et JOIN ticket_types tt ON tt.id = et.ticket_type_id
             WHERE et.listing_id=? GROUP BY tt.name, tt.category ORDER BY total DESC"
        );
        $stmt->execute([$listingId]);
        foreach ($stmt->fetchAll() as $r) {
            $t = (int) ($r['total'] ?? 0);
            $a = (int) ($r['arrived'] ?? 0);
            $report[] = ['name' => $r['name'], 'category' => $r['category'], 'total' => $t, 'arrived' => $a, 'rate' => $t > 0 ? round(100 * $a / $t, 1) : 0.0];
        }
        return [
            'checked_in' => $checkedIn, 'attendance_rate' => $total > 0 ? round(100 * $checkedIn / $total, 1) : 0.0,
            'did_not_arrive' => max($total - $checkedIn, 0), 'by_type' => $report,
        ];
    }

    public function auditLog(string $listingId, string $vendorId, int $limit = 50): array
    {
        $this->listingRow($listingId, $vendorId);
        $stmt = $this->db->prepare(
            "SELECT request_id, ticket_id, decision, reason_code, gate, device_id, operator_name, source, details, created_at
             FROM checkin_scans WHERE listing_id=? ORDER BY id DESC LIMIT " . min(max($limit, 1), 200)
        );
        $stmt->execute([$listingId]);
        return $stmt->fetchAll();
    }
}
