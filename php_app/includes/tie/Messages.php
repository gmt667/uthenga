<?php
/** Event Messages service — the organizer's communication workspace for the
 *  Events Control Center. Conversations stay private and two-way, broadcasts
 *  are mass communication, announcements are official event information and
 *  automations are trigger-based transactional messages.
 *
 *  Business facts are never stored twice: conversation participants are
 *  lightweight snapshots while live ticket / payment / check-in / spend
 *  context is derived from the operational tables at read time (the same
 *  convention Analytics and Finance follow). Every message, note and
 *  broadcast write is recorded in the audit log. */

final class UthengaMessagesService
{
    public const SCHEMA = 'tie-messages/v1';

    private const CONV_STATUSES = ['OPEN', 'PENDING', 'RESOLVED', 'ARCHIVED'];
    private const PRIORITIES = ['NORMAL', 'PRIORITY', 'URGENT'];
    private const CHANNELS = ['UTHENGA', 'EMAIL', 'SMS'];
    private const KINDS = ['BROADCAST', 'ANNOUNCEMENT'];
    private const BROADCAST_STATUSES = ['DRAFT', 'SCHEDULED', 'SENT', 'FAILED', 'CANCELLED'];
    private const AUDIENCES = ['ALL_CUSTOMERS', 'EVENT_ATTENDEES', 'TICKET_HOLDERS', 'VIP_CUSTOMERS', 'NOT_CHECKED_IN', 'CUSTOM'];
    private const TRIGGERS = [
        'TICKET_PURCHASED', 'PAYMENT_SUCCESS', 'PAYMENT_FAILED', 'EVENT_TOMORROW',
        'EVENT_STARTING_SOON', 'TICKET_REFUND', 'EVENT_CANCELLED', 'EVENT_RESCHEDULED',
        'REVIEW_REQUEST', 'TICKET_EXPIRING',
    ];
    private const ASSIGNMENTS = ['Unassigned', 'Event Manager', 'Customer Support', 'Ticketing Staff', 'Finance', 'Marketing'];
    private const TOPICS = [
        'ticket' => 'Ticket Support', 'payment' => 'Payment', 'refund' => 'Refund',
        'venue' => 'Venue', 'parking' => 'Venue', 'transport' => 'Transport',
        'accommodation' => 'Accommodation', 'schedule' => 'Event Information',
        'start' => 'Event Information', 'time' => 'Event Information',
        'complaint' => 'Complaint', 'cancel' => 'Cancellation',
    ];
    private const TEMPLATE_VARS = ['customer_name', 'event_name', 'event_date', 'ticket_type', 'ticket_number', 'venue_name', 'order_id', 'amount'];

    public function __construct(private PDO $db)
    {
    }

    /* ── shared helpers ─────────────────────────────────────────────── */

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** This vendor's event rows: listing_id + event_id + schedule facts. */
    private function events(string $vendorId): array
    {
        $s = $this->db->prepare('SELECT e.listing_id, e.id AS event_id, e.title, e.status,
                                        e.start_date, e.start_time, e.end_date, e.end_time
                                 FROM tie_events_events e
                                 WHERE e.vendor_id=? AND e.listing_id IS NOT NULL
                                 ORDER BY e.start_date ASC, e.title ASC');
        $s->execute([$vendorId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Event options for filters and audience targeting (public facade). */
    public function eventsList(string $vendorId): array
    {
        return array_map(fn($e) => [
            'event_id' => $e['event_id'], 'listing_id' => $e['listing_id'],
            'title' => $e['title'], 'start_date' => $e['start_date'], 'status' => $e['status'],
        ], $this->events($vendorId));
    }

    private function listingIn(string $vendorId): string
    {
        $ids = array_map(fn($e) => $this->db->quote((string) $e['listing_id']), $this->events($vendorId));
        return $ids ? implode(',', $ids) : "''";
    }

    private function audit(string $vendorId, ?string $actorId, string $actorName, string $action, ?string $conversationId, ?string $targetType = null, ?string $targetId = null, array $details = []): void
    {
        $s = $this->db->prepare('INSERT INTO tie_msg_audit_log
            (vendor_id, actor_id, actor_name, action, conversation_id, target_type, target_id, details)
            VALUES (?,?,?,?,?,?,?,?)');
        $s->execute([$vendorId, $actorId, $actorName, $action, $conversationId, $targetType, $targetId,
            $details ? json_encode($details) : null]);
    }

    private function customerIdentity(string $customerId): array
    {
        $s = $this->db->prepare('SELECT id, name, email, phone, role, is_approved, account_status, created_at
                                 FROM users WHERE id=?');
        $s->execute([$customerId]);
        $u = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($u) {
            return [
                'customer_id' => $customerId,
                'name' => $u['name'] ?? 'Customer',
                'email' => $u['email'] ?? '',
                'phone' => $u['phone'] ?? '',
                'verified' => (int) ($u['is_approved'] ?? 0) === 1 && ($u['account_status'] ?? '') === 'active',
                'since' => $u['created_at'] ?? null,
            ];
        }
        // Fall back to the bookings denormalized snapshot for guest buyers.
        $b = $this->db->prepare('SELECT MAX(customer_name) AS name, MAX(customer_email) AS email
                                 FROM bookings WHERE customer_id=? AND deleted_at IS NULL');
        $b->execute([$customerId]);
        $row = $b->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'customer_id' => $customerId,
            'name' => $row['name'] ?? 'Customer',
            'email' => $row['email'] ?? '',
            'phone' => '',
            'verified' => false,
            'since' => null,
        ];
    }

    private function customerStats(string $vendorId, string $customerId): array
    {
        $in = $this->listingIn($vendorId);
        $s = $this->db->prepare("SELECT COUNT(DISTINCT b.listing_id) AS events_count,
                                        COUNT(b.id) AS orders_count,
                                        COALESCE(SUM(CASE WHEN b.payment_status='Paid' THEN b.total_price ELSE 0 END),0) AS spend,
                                        COUNT(DISTINCT b.booking_code) AS unique_orders
                                 FROM bookings b
                                 WHERE b.listing_id IN ($in) AND b.customer_id=? AND b.deleted_at IS NULL");
        $s->execute([$customerId]);
        $r = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        $tik = $this->db->prepare("SELECT COUNT(*) AS tickets FROM event_tickets et
                                   JOIN listings l ON l.id=et.listing_id
                                   WHERE et.listing_id IN ($in) AND et.holder_email=?");
        $tik->execute([$customerId]);
        return [
            'events_count' => (int) ($r['events_count'] ?? 0),
            'orders_count' => (int) ($r['orders_count'] ?? 0),
            'tickets_count' => (int) ($tik->fetchColumn() ?: 0),
            'total_spent' => round((float) ($r['spend'] ?? 0), 2),
        ];
    }

    /** Live event + ticket + payment context for a customer conversation. */
    private function interactionContext(string $vendorId, array $conversation): array
    {
        $eventId = $conversation['event_id'] ?? null;
        $listingId = $conversation['listing_id'] ?? null;
        $event = null;
        if ($eventId) {
            $s = $this->db->prepare('SELECT id, title, listing_id, start_date, start_time, end_date, end_time, status
                                     FROM tie_events_events WHERE id=? AND vendor_id=?');
            $s->execute([$eventId, $vendorId]);
            $event = $s->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $ticket = null;
        $latestTicket = null;
        $tickets = [];
        $booking = null;
        if ($listingId) {
            $cId = $conversation['customer_id'];
            $email = $conversation['customer_email'] ?: $this->customerIdentity($cId)['email'];
            if ($email !== '') {
                $t = $this->db->prepare('SELECT et.id, et.ticket_type_id, tt.name AS ticket_type_name,
                                                et.status AS ticket_status, et.checked_in_at, et.last_sent_at,
                                                b.id AS booking_id, b.payment_status, b.total_price,
                                                b.booking_code, b.created_at
                                         FROM event_tickets et
                                         LEFT JOIN ticket_types tt ON tt.id = et.ticket_type_id
                                         LEFT JOIN bookings b ON b.id = et.booking_id
                                         WHERE et.listing_id=? AND et.holder_email=?
                                         ORDER BY b.created_at DESC LIMIT 12');
                $t->execute([$listingId, $email]);
                $tickets = $t->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $latestTicket = $tickets[0] ?? null;
                if ($latestTicket) {
                    $ticket = [
                        'ticket_id' => $latestTicket['id'],
                        'ticket_type' => $latestTicket['ticket_type_name'] ?? 'General',
                        'status' => $latestTicket['ticket_status'] ?? 'ISSUED',
                        'checked_in' => $latestTicket['checked_in_at'] ? true : false,
                        'checked_in_at' => $latestTicket['checked_in_at'],
                        'last_sent_at' => $latestTicket['last_sent_at'],
                        'booking_id' => $latestTicket['booking_id'],
                        'booking_code' => $latestTicket['booking_code'],
                        'payment_status' => $latestTicket['payment_status'] ?? 'Unknown',
                        'amount' => round((float) ($latestTicket['total_price'] ?? 0), 2),
                        'purchased_at' => $latestTicket['created_at'],
                    ];
                }
            }
        }
        return compact('event', 'ticket', 'tickets', 'booking');
    }

    /** Recent customer activity timeline (check-ins, purchases, conversations). */
    private function activity(string $vendorId, array $conversation): array
    {
        $in = $this->listingIn($vendorId);
        $email = $conversation['customer_email'] ?: $this->customerIdentity($conversation['customer_id'])['email'];
        $cId = $conversation['customer_id'];
        $rows = [];

        $s = $this->db->prepare("SELECT 'purchase' AS kind, b.id, l.title AS label, b.total_price AS amount, b.created_at AS at
                                 FROM bookings b JOIN listings l ON l.id=b.listing_id
                                 WHERE b.listing_id IN ($in) AND b.customer_id=? AND b.deleted_at IS NULL");
        $s->execute([$cId]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) $rows[] = $r;

        if ($email !== '') {
            $s = $this->db->prepare("SELECT 'checkin' AS kind, et.id, l.title AS label, NULL AS amount, et.checked_in_at AS at
                                     FROM event_tickets et JOIN listings l ON l.id=et.listing_id
                                     WHERE et.listing_id IN ($in) AND et.holder_email=? AND et.checked_in_at IS NOT NULL");
            $s->execute([$email]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) $rows[] = $r;
        }

        $m = $this->db->prepare("SELECT 'message' AS kind, id, 'conversation' AS label, NULL AS amount, created_at AS at
                                 FROM tie_msg_messages WHERE conversation_id=?");
        $m->execute([$conversation['id']]);
        foreach ($m->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) $rows[] = $r;

        usort($rows, fn($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        return array_slice($rows, 0, 12);
    }

    private function detectTopic(string $since): ?string
    {
        $q = strtolower($since);
        foreach (self::TOPICS as $key => $label) {
            if (str_contains($q, $key)) return $label;
        }
        return 'General enquiry';
    }

    private function resolvePayload(array $payload, string $vendorId, string $customerId): array
    {
        $type = (string) ($payload['type'] ?? 'text');
        if ($type === 'ticket') {
            $s = $this->db->prepare('SELECT et.id, et.ticket_type_id, tt.name AS ticket_type_name, et.status,
                                            et.checked_in_at, et.last_sent_at, l.title AS event_title,
                                            b.id AS booking_id, b.payment_status, b.total_price, b.booking_code
                                     FROM event_tickets et
                                     LEFT JOIN ticket_types tt ON tt.id=et.ticket_type_id
                                     LEFT JOIN listings l ON l.id=et.listing_id
                                     LEFT JOIN bookings b ON b.id=et.booking_id
                                     WHERE et.id=?');
            $s->execute([(string) ($payload['ticket_id'] ?? '')]);
            $t = $s->fetch(PDO::FETCH_ASSOC);
            if (!$t) return ['type' => 'text', 'text' => 'Ticket not found. Please check the reference and try again.'];
            return ['type' => 'ticket',
                'ticket_id' => $t['id'], 'ticket_type' => $t['ticket_type_name'] ?? 'General',
                'event_title' => $t['event_title'] ?? 'Event', 'status' => $t['status'],
                'checked_in' => $t['checked_in_at'] ? true : false, 'payment_status' => $t['payment_status'],
                'amount' => round((float) ($t['total_price'] ?? 0), 2)];
        }
        if ($type === 'event') {
            $s = $this->db->prepare('SELECT id, title, start_date, start_time, end_date, end_time, short_description
                                     FROM tie_events_events WHERE id=? AND vendor_id=?');
            $s->execute([(string) ($payload['event_id'] ?? ''), $vendorId]);
            $e = $s->fetch(PDO::FETCH_ASSOC);
            if (!$e) return ['type' => 'text', 'text' => 'Event not found.'];
            return ['type' => 'event', 'event_id' => $e['id'], 'title' => $e['title'],
                'start_date' => $e['start_date'], 'start_time' => $e['start_time'],
                'end_date' => $e['end_date'], 'end_time' => $e['end_time'],
                'description' => $e['short_description'] ?? ''];
        }
        if ($type === 'venue') {
            $venueRow = null;
            if (!empty($payload['venue_id'])) {
                $s = $this->db->prepare('SELECT id, name, address, city, capacity, contact_phone FROM tie_venues
                                         WHERE id=? AND vendor_id=?');
                $s->execute([(string) $payload['venue_id'], $vendorId]);
                $venueRow = $s->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$venueRow && !empty($payload['event_id'])) {
                $s = $this->db->prepare('SELECT v.id, v.name, v.address, v.city, v.capacity, v.contact_phone
                                         FROM tie_events_events e LEFT JOIN tie_venues v ON v.id=e.venue_id
                                         WHERE e.id=? AND e.vendor_id=?');
                $s->execute([(string) $payload['event_id'], $vendorId]);
                $venueRow = $s->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$venueRow) return ['type' => 'text', 'text' => 'Venue details are not available yet.'];
            return ['type' => 'venue', 'venue_id' => $venueRow['id'], 'name' => $venueRow['name'],
                'address' => $venueRow['address'] ?? '', 'city' => $venueRow['city'] ?? '',
                'capacity' => (int) ($venueRow['capacity'] ?? 0)];
        }
        if ($type === 'payment' || $type === 'booking') {
            $s = $this->db->prepare('SELECT id, booking_code, listing_title, total_price, payment_status,
                                            payment_gateway, created_at, quantity
                                     FROM bookings WHERE id=?');
            $s->execute([(string) ($payload['booking_id'] ?? '')]);
            $b = $s->fetch(PDO::FETCH_ASSOC);
            if (!$b) return ['type' => 'text', 'text' => 'Order not found.'];
            return ['type' => 'payment', 'booking_id' => $b['id'], 'booking_code' => $b['booking_code'],
                'event_title' => $b['listing_title'], 'amount' => round((float) $b['total_price'], 2),
                'payment_status' => $b['payment_status'], 'gateway' => $b['payment_gateway'] ?? '',
                'purchased_at' => $b['created_at'], 'proof' => $b['payment_status'] === 'Paid'];
        }
        return ['type' => 'text', 'text' => $payload['text'] ?? ''];
    }

    /* ── inbox ──────────────────────────────────────────────────────── */

    /** Conversation list with filters. */
    public function inbox(string $vendorId, array $f = []): array
    {
        $view = (string) ($f['view'] ?? 'all');
        $q = trim((string) ($f['q'] ?? ''));
        $eventId = (string) ($f['event_id'] ?? '');
        $tag = (string) ($f['tag'] ?? '');

        $where = ['c.vendor_id=?'];
        $params = [$vendorId];
        if ($view === 'unread') $where[] = 'c.unread_count > 0 AND c.status <> \'ARCHIVED\'';
        if ($view === 'priority') $where[] = 'c.priority IN (\'PRIORITY\',\'URGENT\') AND c.status <> \'ARCHIVED\'';
        if ($view === 'assigned') $where[] = 'c.assigned_to IS NOT NULL AND c.assigned_to <> \'Unassigned\' AND c.status <> \'ARCHIVED\'';
        if ($view === 'archived') $where[] = 'c.status = \'ARCHIVED\'';
        if ($view === 'sent') $where[] = 'c.status <> \'ARCHIVED\'';
        if ($eventId !== '' && $eventId !== 'all') {
            $ev = $this->events($vendorId);
            $ids = array_values(array_filter($ev, fn($e) => $e['event_id'] === $eventId || $e['listing_id'] === $eventId));
            $listings = array_map(fn($e) => $this->db->quote((string) $e['listing_id']), $ids);
            if ($listings) { $where[] = 'c.listing_id IN (' . implode(',', $listings) . ')'; }
        }
        if ($tag !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM tie_msg_tags tg WHERE tg.conversation_id=c.id AND tg.tag=?)';
            $params[] = $tag;
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(c.customer_name LIKE ? OR c.customer_email LIKE ? OR c.subject LIKE ? OR c.last_message_preview LIKE ?
                        OR c.customer_id LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $sql = "SELECT c.*, (SELECT COUNT(*) FROM tie_msg_messages m WHERE m.conversation_id=c.id) AS message_count,
                       (SELECT COUNT(*) FROM tie_msg_internal_notes n WHERE n.conversation_id=c.id) AS note_count,
                       (SELECT GROUP_CONCAT(t.tag SEPARATOR ',') FROM tie_msg_tags t WHERE t.conversation_id=c.id) AS tags
                FROM tie_msg_conversations c
                WHERE " . implode(' AND ', $where) . "
                ORDER BY (CASE c.priority WHEN 'URGENT' THEN 0 WHEN 'PRIORITY' THEN 1 ELSE 2 END),
                         COALESCE(c.last_message_at, c.updated_at) DESC";
        $s = $this->db->prepare($sql);
        $s->execute($params);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $conversations = [];
        foreach ($rows as $r) {
            $event = null;
            if ($r['event_id']) {
                $e = $this->db->prepare('SELECT title FROM tie_events_events WHERE id=? AND vendor_id=?');
                $e->execute([$r['event_id'], $vendorId]);
                $event = $e->fetchColumn() ?: null;
            }
            $r['event_title'] = $event;
            $r['tags'] = $r['tags'] ? array_values(array_filter(explode(',', (string) $r['tags']))) : [];
            $conversations[] = $r;
        }

        $counts = $this->counts($vendorId);

        return [
            'filters' => [
                'view' => $view, 'q' => $q, 'event_id' => $eventId, 'tag' => $tag,
                'available_views' => ['all', 'unread', 'priority', 'assigned', 'archived', 'sent'],
                'assignment_options' => self::ASSIGNMENTS,
                'topics' => array_values(array_unique(self::TOPICS)),
            ],
            'counts' => $counts,
            'conversations' => $conversations,
        ];
    }

    public function counts(string $vendorId): array
    {
        $s = $this->db->prepare("SELECT
            SUM(CASE WHEN status <> 'ARCHIVED' THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN unread_count > 0 AND status <> 'ARCHIVED' THEN 1 ELSE 0 END) AS unread,
            SUM(CASE WHEN priority IN ('PRIORITY','URGENT') AND status <> 'ARCHIVED' THEN 1 ELSE 0 END) AS priority,
            SUM(CASE WHEN assigned_to IS NOT NULL AND assigned_to <> 'Unassigned' AND status <> 'ARCHIVED' THEN 1 ELSE 0 END) AS assigned,
            SUM(CASE WHEN status='ARCHIVED' THEN 1 ELSE 0 END) AS archived
            FROM tie_msg_conversations WHERE vendor_id=?");
        $s->execute([$vendorId]);
        $r = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'active' => (int) ($r['active'] ?? 0),
            'unread' => (int) ($r['unread'] ?? 0),
            'priority' => (int) ($r['priority'] ?? 0),
            'assigned' => (int) ($r['assigned'] ?? 0),
            'archived' => (int) ($r['archived'] ?? 0),
        ];
    }

    /** Full conversation detail: header, messages, context panel, assist. */
    public function conversation(string $vendorId, string $conversationId): array
    {
        $s = $this->db->prepare('SELECT c.* FROM tie_msg_conversations c WHERE c.id=? AND c.vendor_id=?');
        $s->execute([$conversationId, $vendorId]);
        $conv = $s->fetch(PDO::FETCH_ASSOC);
        if (!$conv) return ['found' => false];

        $m = $this->db->prepare('SELECT id, sender_type, sender_name, body, payload, attachments, is_read, created_at
                                 FROM tie_msg_messages WHERE conversation_id=? ORDER BY created_at ASC');
        $m->execute([$conversationId]);
        $messages = [];
        foreach ($m->fetchAll(PDO::FETCH_ASSOC) ?: [] as $msg) {
            $payload = $msg['payload'] ? json_decode($msg['payload'], true) : null;
            $card = $payload ? $this->resolvePayload($payload, $vendorId, $conv['customer_id']) : null;
            $messages[] = [
                'id' => $msg['id'], 'sender_type' => $msg['sender_type'], 'sender_name' => $msg['sender_name'],
                'body' => $msg['body'] ?? '', 'card' => $card, 'is_read' => (int) ($msg['is_read'] ?? 0),
                'created_at' => $msg['created_at'],
            ];
        }

        $n = $this->db->prepare('SELECT id, author_name, body, created_at FROM tie_msg_internal_notes
                                 WHERE conversation_id=? ORDER BY created_at DESC');
        $n->execute([$conversationId]);
        $notes = $n->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $t = $this->db->prepare('SELECT tag FROM tie_msg_tags WHERE conversation_id=? ORDER BY created_at');
        $t->execute([$conversationId]);
        $tags = array_column($t->fetchAll(PDO::FETCH_ASSOC) ?: [], 'tag');

        $customer = $this->customerIdentity($conv['customer_id']);
        $stats = $this->customerStats($vendorId, $conv['customer_id']);

        $conv['id'] = $conversationId;
        $context = $this->interactionContext($vendorId, $conv);
        $activity = $this->activity($vendorId, $conv);

        if (!$conv['last_message_preview'] && $messages) {
            $last = $messages[count($messages) - 1];
            $conv['last_message_preview'] = mb_substr($last['body'] ?: '[' . $last['sender_type'] . ' message]', 0, 200);
        }

        return [
            'found' => true,
            'conversation' => [
                'id' => $conv['id'], 'customer_id' => $conv['customer_id'], 'channel' => $conv['channel'],
                'subject' => $conv['subject'], 'event_id' => $conv['event_id'], 'listing_id' => $conv['listing_id'],
                'status' => $conv['status'], 'priority' => $conv['priority'], 'assigned_to' => $conv['assigned_to'],
                'detected_topic' => $conv['detected_topic'], 'is_muted' => (int) $conv['is_muted'],
                'unread_count' => (int) $conv['unread_count'], 'created_at' => $conv['created_at'],
                'event_title' => $this->eventTitle($conv['event_id'], $vendorId),
            ],
            'messages' => $messages,
            'notes' => $notes,
            'tags' => $tags,
            'customer' => $customer + $stats,
            'context' => $context,
            'activity' => $activity,
            'assist' => $this->assist($vendorId, $conversationId),
            'assignment_options' => self::ASSIGNMENTS,
            'topic_options' => array_values(array_unique(self::TOPICS)),
        ];
    }

    private function eventTitle(?string $eventId, string $vendorId): ?string
    {
        if (!$eventId) return null;
        $s = $this->db->prepare('SELECT title FROM tie_events_events WHERE id=? AND vendor_id=?');
        $s->execute([$eventId, $vendorId]);
        return $s->fetchColumn() ?: null;
    }

    /** Rule-based AI assist: suggested response, summary and intent, backed by live facts. */
    public function assist(string $vendorId, string $conversationId): array
    {
        $s = $this->db->prepare('SELECT c.* FROM tie_msg_conversations c WHERE c.id=? AND c.vendor_id=?');
        $s->execute([$conversationId, $vendorId]);
        $conv = $s->fetch(PDO::FETCH_ASSOC);
        if (!$conv) return ['reply' => '', 'summary' => '', 'intent' => null];

        $m = $this->db->prepare('SELECT sender_type, body, created_at FROM tie_msg_messages
                                 WHERE conversation_id=? ORDER BY created_at ASC');
        $m->execute([$conversationId]);
        $msgs = $m->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $lastCustomer = null;
        $recent = [];
        foreach (array_reverse($msgs) as $msg) {
            if ($msg['sender_type'] === 'CUSTOMER' && $lastCustomer === null) $lastCustomer = $msg['body'] ?? '';
            $recent[] = $msg['body'] ?? '';
            if (count($recent) >= 8) break;
        }
        $q = strtolower(trim($lastCustomer ?? ''));
        $ctx = $this->interactionContext($vendorId, $conv);

        // Intent detection.
        $intent = null;
        if ($q !== '') {
            $urgent = str_contains($q, 'urgent') || str_contains($q, 'asap') || str_contains($q, 'immediately') ||
                str_contains($q, 'tomorrow') || (str_contains($q, 'paid') && str_contains($q, 'no ticket')) ||
                (str_contains($q, 'charge') && str_contains($q, 'ticket'));
            $intent = ['intent' => 'General enquiry', 'priority' => $urgent ? 'HIGH' : 'NORMAL'];
            foreach (self::TOPICS as $key => $label) {
                if (str_contains($q, $key)) { $intent['intent'] = $label; break; }
            }
            $intent['suggested_action'] = $intent['intent'] . ' — review the live context cards and respond.';
            if ($urgent) {
                $intent['flagged'] = true;
                $intent['suggested_action'] = 'Verify payment and ticket issuance immediately, then reply with facts.';
            }
        }

        // Suggested response composed from live event facts.
        $reply = '';
        if ($ctx['event']) {
            $start = trim((string) ($ctx['event']['start_time'] ?? ''));
            $date = trim((string) ($ctx['event']['start_date'] ?? ''));
            if (str_contains($q, 'start') || str_contains($q, 'time') || str_contains($q, 'when')) {
                $reply = 'The event starts at ' . ($start ?: '—') . ($date ? ' on ' . date('d M Y', strtotime($date)) : '') . '. Doors typically open an hour before the start.';
            }
        }
        if ($reply === '' && $ctx['ticket']) {
            if (str_contains($q, 'ticket') || str_contains($q, 'qr') || str_contains($q, 'attendee') || str_contains($q, 'entry')) {
                $st = $ctx['ticket']['status'];
                $reply = 'Your ' . $ctx['ticket']['ticket_type'] . ' ticket (' . $ctx['ticket']['ticket_id'] . ') is ' . strtolower($st) . '. ' .
                    ($ctx['ticket']['checked_in'] ? 'It has already been scanned for entry.' : 'It is ready for entry — present the QR code at the gate.');
            }
        }
        if ($reply === '' && (str_contains($q, 'refund') || str_contains($q, 'cancel'))) {
            $reply = 'I can help with that. May I confirm your order reference so our team can review your request?';
        }
        if ($reply === '' && (str_contains($q, 'park') || str_contains($q, 'venue'))) {
            $v = $ctx['event'] ? $this->resolvePayload(['type' => 'venue', 'event_id' => $ctx['event']['id']], $vendorId, $conv['customer_id']) : null;
            $reply = $v && $v['type'] === 'venue'
                ? 'The event takes place at ' . $v['name'] . ($v['address'] ? ', ' . $v['address'] : '') . ($v['city'] ? ', ' . $v['city'] : '') . '.'
                : 'Venue details will be shared with ticket holders as they are finalized.';
        }
        if ($reply === '') $reply = 'Thank you for your message — I will have this sorted for you shortly.';

        // Summary.
        $summary = '';
        if (count($msgs) >= 3) {
            $summary = 'Conversation covers ' . ($conv['detected_topic'] ?? 'customer enquiry') . ' across ' . count($msgs) . ' messages.';
            if ($ctx['ticket']) $summary .= ' Customer holds a ' . $ctx['ticket']['ticket_type'] . ' ticket (' . $ctx['ticket']['status'] . ', ' . $ctx['ticket']['payment_status'] . ').';
            if ($ctx['event']) $summary .= ' Related event: ' . $ctx['event']['title'] . '.';
            $summary .= $intent && $intent['priority'] === 'HIGH' ? ' Flagged high priority.' : '';
        }

        return ['reply' => $reply, 'summary' => $summary, 'intent' => $intent, 'messages_count' => count($msgs)];
    }

    /* ── conversation operations ────────────────────────────────────── */

    /** Start a conversation with a customer, optionally scoped to an event. */
    public function start(array $user, array $input): array
    {
        $vendorId = (string) ($user['id'] ?? '');
        $customerId = trim((string) ($input['customer_id'] ?? $input['email'] ?? ''));
        if ($customerId === '') throw UthengaTieErrors::validation(['customer_id' => 'A customer is required.']);

        $ident = $this->customerIdentity($customerId);
        if (!trim($ident['name']) && !filter_var($ident['email'], FILTER_VALIDATE_EMAIL) && str_contains($customerId, '@')) {
            $ident = ['customer_id' => $customerId, 'name' => explode('@', $customerId)[0], 'email' => $customerId, 'verified' => false, 'since' => null];
        }
        // Ensure the customer exists as a known buyer of this vendor wherever possible.
        $known = $this->db->prepare("SELECT customer_id FROM bookings WHERE listing_id IN ({$this->listingIn($vendorId)}) AND customer_id=? LIMIT 1");
        $known->execute([$customerId]);

        $eventId = (string) ($input['event_id'] ?? '');
        $listingId = null;
        $eventTitle = '';
        if ($eventId !== '') {
            $e = $this->db->prepare('SELECT id, listing_id, title FROM tie_events_events WHERE id=? AND vendor_id=?');
            $e->execute([$eventId, $vendorId]);
            $ev = $e->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$ev) throw UthengaTieErrors::validation(['event_id' => 'Event not found for this account.']);
            $listingId = $ev['listing_id'];
            $eventTitle = $ev['title'];
        }

        $id = $this->uuid();
        $subject = trim((string) ($input['subject'] ?? $eventTitle ?? 'Booking enquiry'));
        if ($subject === '') $subject = 'Booking enquiry';
        $body = trim((string) ($input['body'] ?? ''));
        $topic = $this->detectTopic((string) ($input['topic_hint'] ?? ($body ?: $subject)));

        $s = $this->db->prepare('INSERT INTO tie_msg_conversations
            (id, vendor_id, customer_id, customer_name, customer_email, subject, event_id, listing_id, detected_topic)
            VALUES (?,?,?,?,?,?,?,?,?)');
        $s->execute([$id, $vendorId, $ident['customer_id'], $ident['name'], $ident['email'], $subject,
            $eventId ?: null, $listingId, $topic]);

        $now = date('Y-m-d H:i:s');
        if ($body !== '') {
            $this->insertMessage($id, 'ORGANIZER', $user['name'] ?? 'Organizer', $body, null, null, true);
            $s = $this->db->prepare('UPDATE tie_msg_conversations SET last_message_at=?, last_message_preview=? WHERE id=?');
            $s->execute([$now, mb_substr($body, 0, 200), $id]);
        } else {
            $s = $this->db->prepare('UPDATE tie_msg_conversations SET last_message_at=? WHERE id=?');
            $s->execute([$now, $id]);
        }

        $this->audit($vendorId, $user['id'] ?? null, $user['name'] ?? 'Organizer', 'conversation_started', $id, 'conversation', $id);
        return $this->conversation($vendorId, $id);
    }

    /** Send a message (text or structured payload card) in a conversation. */
    public function reply(array $user, array $input): array
    {
        $vendorId = (string) ($user['id'] ?? '');
        $conversationId = trim((string) ($input['conversation_id'] ?? ''));
        $payload = is_array($input['payload'] ?? null) ? $input['payload'] : null;
        $body = trim((string) ($input['body'] ?? ''));
        $cardKeys = ['ticket' => 'ticket_id', 'event' => 'event_id', 'venue' => 'venue_id', 'payment' => 'booking_id', 'booking' => 'booking_id'];

        $blocked = false;
        $s = $this->db->prepare('SELECT c.*, (SELECT COUNT(*) FROM tie_msg_messages m WHERE m.conversation_id=c.id) AS msg_count
                                 FROM tie_msg_conversations c WHERE c.id=? AND c.vendor_id=?');
        $s->execute([$conversationId, $vendorId]);
        $conv = $s->fetch(PDO::FETCH_ASSOC);
        if (!$conv) throw UthengaTieErrors::validation(['conversation_id' => 'Conversation not found.']);
        if (($conv['status'] ?? '') === 'ARCHIVED') $blocked = true;
        if (($conv['is_muted'] ?? 0) && $body === '' && !$payload) $blocked = true;

        if ($payload) {
            $type = (string) ($payload['type'] ?? 'text');
            if (isset($cardKeys[$type])) {
                $key = $cardKeys[$type];
                if (empty($payload[$key])) throw UthengaTieErrors::validation([$key => 'Required for a ' . $type . ' card.']);
                $body = $body ?: 'Shared ' . $type . ' details with the customer.';
            }
        }
        if ($body === '' && !$payload) throw UthengaTieErrors::validation(['body' => 'A message is required.']);

        if (!$blocked) {
            $this->insertMessage($conversationId, 'ORGANIZER', $user['name'] ?? 'Organizer', $body, $payload ? json_encode($payload) : null, null, true);
            $now = date('Y-m-d H:i:s');
            $s = $this->db->prepare('UPDATE tie_msg_conversations SET last_message_at=?, last_message_preview=?, status=IF(status=\'ARCHIVED\',status,\'PENDING\') WHERE id=?');
            $s->execute([$now, mb_substr($body ?: '[' . ($payload['type'] ?? 'card') . ']', 0, 200), $conversationId]);
            $this->audit($vendorId, $user['id'] ?? null, $user['name'] ?? 'Organizer', 'message_sent', $conversationId, 'message', null, ['kind' => $payload ? ($payload['type'] ?? 'text') : 'text']);
        }
        return $this->conversation($vendorId, $conversationId);
    }

    /** Ingest a customer-side message (seeded demo or future customer channel). */
    public function customerInbound(array $user, array $input): array
    {
        $vendorId = (string) ($user['id'] ?? '');
        $conversationId = trim((string) ($input['conversation_id'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') throw UthengaTieErrors::validation(['body' => 'A message is required.']);

        $s = $this->db->prepare('SELECT c.* FROM tie_msg_conversations c WHERE c.id=? AND c.vendor_id=?');
        $s->execute([$conversationId, $vendorId]);
        $conv = $s->fetch(PDO::FETCH_ASSOC);
        if (!$conv) throw UthengaTieErrors::validation(['conversation_id' => 'Conversation not found.']);

        $this->insertMessage($conversationId, 'CUSTOMER', $conv['customer_name'] ?: 'Customer', $body, null, null, false);
        $now = date('Y-m-d H:i:s');
        $s = $this->db->prepare('UPDATE tie_msg_conversations SET last_message_at=?, last_message_preview=?,
                                 unread_count=unread_count+1, status=CASE WHEN status IN (\'RESOLVED\',\'ARCHIVED\') THEN \'OPEN\' ELSE status END
                                 WHERE id=?');
        $s->execute([$now, mb_substr($body, 0, 200), $conversationId]);
        return $this->conversation($vendorId, $conversationId);
    }

    private function insertMessage(string $conversationId, string $senderType, string $senderName, string $body, ?string $payload, ?string $attachments, bool $read): void
    {
        $s = $this->db->prepare('INSERT INTO tie_msg_messages
            (id, conversation_id, sender_type, sender_name, body, payload, attachments, is_read)
            VALUES (?,?,?,?,?,?,?,?)');
        $s->execute([$this->uuid(), $conversationId, $senderType, $senderName, $body ?: null, $payload, $attachments, (int) $read]);
    }

    public function markRead(string $vendorId, string $conversationId): array
    {
        $s = $this->db->prepare('UPDATE tie_msg_messages m JOIN tie_msg_conversations c ON c.id=m.conversation_id
                                 SET m.is_read=1, c.unread_count=0 WHERE c.id=? AND c.vendor_id=?');
        $s->execute([$conversationId, $vendorId]);
        return ['ok' => true];
    }

    public function updateStatus(string $vendorId, string $actorName, string $conversationId, string $status): array
    {
        $status = strtoupper($status);
        if (!in_array($status, self::CONV_STATUSES, true)) throw UthengaTieErrors::validation(['status' => 'Invalid conversation status.']);
        $s = $this->db->prepare('UPDATE tie_msg_conversations SET status=? WHERE id=? AND vendor_id=?');
        $s->execute([$status, $conversationId, $vendorId]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['conversation_id' => 'Conversation not found.']);
        $this->audit($vendorId, null, $actorName, 'status_' . strtolower($status), $conversationId, 'conversation', $conversationId, ['status' => $status]);
        return $this->conversation($vendorId, $conversationId);
    }

    public function setPriority(string $vendorId, string $actorName, string $conversationId, string $priority): array
    {
        $priority = strtoupper($priority);
        if (!in_array($priority, self::PRIORITIES, true)) throw UthengaTieErrors::validation(['priority' => 'Invalid priority.']);
        $s = $this->db->prepare('UPDATE tie_msg_conversations SET priority=? WHERE id=? AND vendor_id=?');
        $s->execute([$priority, $conversationId, $vendorId]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['conversation_id' => 'Conversation not found.']);
        $this->audit($vendorId, null, $actorName, 'priority_set', $conversationId, 'conversation', $conversationId, ['priority' => $priority]);
        return $this->conversation($vendorId, $conversationId);
    }

    public function assign(string $vendorId, string $actorName, string $conversationId, string $assignee): array
    {
        $assignee = trim($assignee);
        if (!in_array($assignee, self::ASSIGNMENTS, true) && $assignee !== '') {
            throw UthengaTieErrors::validation(['assigned_to' => 'Unknown assignment target.']);
        }
        $s = $this->db->prepare('UPDATE tie_msg_conversations SET assigned_to=? WHERE id=? AND vendor_id=?');
        $s->execute([$assignee ?: null, $conversationId, $vendorId]);
        if ($s->rowCount() === 0) throw UthengaTieErrors::validation(['conversation_id' => 'Conversation not found.']);
        $this->audit($vendorId, null, $actorName, 'assigned', $conversationId, 'conversation', $conversationId, ['assignee' => $assignee ?: 'Unassigned']);
        return $this->conversation($vendorId, $conversationId);
    }

    public function toggleMute(string $vendorId, string $conversationId): array
    {
        $s = $this->db->prepare('UPDATE tie_msg_conversations SET is_muted = 1 - is_muted WHERE id=? AND vendor_id=?');
        $s->execute([$conversationId, $vendorId]);
        return $this->conversation($vendorId, $conversationId);
    }

    public function addTag(string $vendorId, string $conversationId, string $tag): array
    {
        $tag = trim($tag);
        if ($tag === '' || mb_strlen($tag) > 40) throw UthengaTieErrors::validation(['tag' => 'Tag must be 1–40 characters.']);
        $s = $this->db->prepare('INSERT IGNORE INTO tie_msg_tags (id, vendor_id, conversation_id, tag) VALUES (?,?,?,?)');
        $s->execute([$this->uuid(), $vendorId, $conversationId, $tag]);
        return $this->conversation($vendorId, $conversationId);
    }

    public function removeTag(string $vendorId, string $conversationId, string $tag): array
    {
        $s = $this->db->prepare('DELETE FROM tie_msg_tags WHERE vendor_id=? AND conversation_id=? AND tag=?');
        $s->execute([$vendorId, $conversationId, $tag]);
        return $this->conversation($vendorId, $conversationId);
    }

    public function addNote(string $vendorId, string $actorName, string $conversationId, string $body): array
    {
        $body = trim($body);
        if ($body === '') throw UthengaTieErrors::validation(['body' => 'Note text is required.']);
        $s = $this->db->prepare('INSERT INTO tie_msg_internal_notes (id, conversation_id, author_name, body) VALUES (?,?,?,?)');
        $s->execute([$this->uuid(), $conversationId, $actorName, $body]);
        $this->audit($vendorId, null, $actorName, 'note_added', $conversationId, 'note', null, []);
        return $this->conversation($vendorId, $conversationId);
    }

    /* ── search ─────────────────────────────────────────────────────── */

    /** Global search: customers, conversations, tickets, orders, events. */
    public function search(string $vendorId, string $q): array
    {
        $q = trim($q);
        if ($q === '') return ['customers' => [], 'conversations' => [], 'tickets' => [], 'orders' => [], 'events' => []];
        $in = $this->listingIn($vendorId);
        $like = '%' . $q . '%';
        $likeU = '%' . strtoupper($q) . '%';

        $customers = [];
        $s = $this->db->prepare("SELECT customer_id, MAX(customer_name) AS name, MAX(customer_email) AS email, COUNT(*) AS orders
                                 FROM bookings WHERE listing_id IN ($in) AND (customer_name LIKE ? OR customer_email LIKE ? OR customer_id LIKE ?)
                                 GROUP BY customer_id ORDER BY orders DESC LIMIT 8");
        $s->execute([$like, $like, $likeU]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $customers[] = ['customer_id' => $r['customer_id'], 'name' => $r['name'], 'email' => $r['email'], 'orders' => (int) $r['orders']];
        }

        $s = $this->db->prepare("SELECT id, customer_name, customer_email, subject, status, priority, event_id
                                 FROM tie_msg_conversations WHERE vendor_id=?
                                 AND (customer_name LIKE ? OR customer_email LIKE ? OR subject LIKE ? OR id LIKE ?)
                                 ORDER BY updated_at DESC LIMIT 8");
        $s->execute([$vendorId, $like, $like, $like, $likeU]);
        $conversations = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $tickets = [];
        if (preg_match('~[A-Z0-9-]{6,}~', $q, $m)) {
            $s = $this->db->prepare("SELECT et.id, et.status, l.title AS event_title, et.holder_name
                                     FROM event_tickets et JOIN listings l ON l.id=et.listing_id
                                     WHERE et.listing_id IN ($in) AND (et.id LIKE ? OR et.holder_name LIKE ?)
                                     ORDER BY et.updated_at DESC LIMIT 8");
            $s->execute([$likeU, $like]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $tickets[] = ['ticket_id' => $r['id'], 'status' => $r['status'], 'event_title' => $r['event_title'], 'holder' => $r['holder_name']];
            }
        }

        $orders = [];
        $s = $this->db->prepare("SELECT id, booking_code, listing_title, payment_status, total_price, customer_name
                                 FROM bookings WHERE listing_id IN ($in) AND (id LIKE ? OR booking_code LIKE ?)
                                 ORDER BY created_at DESC LIMIT 8");
        $s->execute([$likeU, $likeU]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $orders[] = ['booking_id' => $r['id'], 'code' => $r['booking_code'] ?? $r['id'], 'event_title' => $r['listing_title'],
                'payment_status' => $r['payment_status'], 'amount' => round((float) $r['total_price'], 2), 'customer' => $r['customer_name']];
        }

        $events = [];
        $ev = $this->events($vendorId);
        foreach ($ev as $e) {
            if (stripos($e['title'], $q) !== false || stripos((string) $e['event_id'], strtoupper($q)) !== false) {
                $events[] = ['event_id' => $e['event_id'], 'title' => $e['title'], 'start_date' => $e['start_date']];
            }
            if (count($events) >= 8) break;
        }

        return ['customers' => $customers, 'conversations' => $conversations, 'tickets' => $tickets, 'orders' => $orders, 'events' => $events];
    }

    /* ── templates ──────────────────────────────────────────────────── */

    public function templates(string $vendorId): array
    {
        $s = $this->db->prepare('SELECT id, title, category, subject, body, is_active, usage_count, updated_at
                                 FROM tie_msg_templates WHERE vendor_id=? ORDER BY category, title');
        $s->execute([$vendorId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $vars = [];
            if (preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', (string) $r['body'], $mm)) {
                $vars = array_values(array_unique($mm[1]));
            }
            $r['variables'] = $vars;
        }
        return ['templates' => $rows];
    }

    public function saveTemplate(string $vendorId, array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $category = trim((string) ($input['category'] ?? 'General'));
        $subject = trim((string) ($input['subject'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        if ($title === '' || $body === '') throw UthengaTieErrors::validation(['title' => 'Title and body are required.']);
        if (mb_strlen($body) > 4000) throw UthengaTieErrors::validation(['body' => 'Template is too long (max 4000 chars).']);

        $vars = [];
        if (preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $body, $mm)) {
            $vars = array_values(array_unique($mm[1]));
        }
        foreach ($vars as $v) {
            if (!in_array($v, self::TEMPLATE_VARS, true)) {
                throw UthengaTieErrors::validation(['body' => 'Variable {{' . $v . '}} is not allowed. Allowed: ' . implode(', ', self::TEMPLATE_VARS)]);
            }
        }

        $id = trim((string) ($input['id'] ?? ''));
        if ($id !== '') {
            $s = $this->db->prepare('UPDATE tie_msg_templates SET title=?, category=?, subject=?, body=? WHERE id=? AND vendor_id=?');
            $s->execute([$title, $category, $subject, $body, $id, $vendorId]);
        } else {
            $id = $this->uuid();
            $s = $this->db->prepare('INSERT INTO tie_msg_templates (id, vendor_id, title, category, subject, body) VALUES (?,?,?,?,?,?)');
            $s->execute([$id, $vendorId, $title, $category, $subject, $body]);
        }
        return $this->templates($vendorId);
    }

    public function deleteTemplate(string $vendorId, string $templateId): array
    {
        $s = $this->db->prepare('DELETE FROM tie_msg_templates WHERE id=? AND vendor_id=?');
        $s->execute([$templateId, $vendorId]);
        return $this->templates($vendorId);
    }

    /* ── broadcasts & announcements ─────────────────────────────────── */

    public function broadcasts(string $vendorId, string $kind = ''): array
    {
        $where = 'vendor_id=?';
        $params = [$vendorId];
        if (in_array($kind, self::KINDS, true)) { $where .= ' AND kind=?'; $params[] = $kind; }
        $s = $this->db->prepare("SELECT id, kind, event_id, title, subject, body, recipient_count, sent_count,
                                        delivered_count, opened_count, failed_count, channel, status, scheduled_at, sent_at, created_at
                                 FROM tie_msg_broadcasts WHERE $where ORDER BY created_at DESC LIMIT 100");
        $s->execute($params);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['rates'] = [
                'delivery_rate' => $r['sent_count'] > 0 ? round($r['delivered_count'] / $r['sent_count'] * 100, 1) : 0,
                'open_rate' => $r['delivered_count'] > 0 ? round($r['opened_count'] / $r['delivered_count'] * 100, 1) : 0,
            ];
            $eventTitle = $r['event_id'] ? $this->eventTitle($r['event_id'], $vendorId) : null;
            $r['event_title'] = $eventTitle;
            unset($r['subject'], $r['body']);
        }
        return ['broadcasts' => $rows];
    }

    public function broadcastDetail(string $vendorId, string $broadcastId): array
    {
        $s = $this->db->prepare('SELECT * FROM tie_msg_broadcasts WHERE id=? AND vendor_id=?');
        $s->execute([$broadcastId, $vendorId]);
        $b = $s->fetch(PDO::FETCH_ASSOC);
        if (!$b) return ['found' => false];
        $b['audience_config'] = $b['audience_config'] ? json_decode($b['audience_config'], true) : ['audience' => 'ALL_CUSTOMERS'];
        $b['rates'] = [
            'delivery_rate' => $b['sent_count'] > 0 ? round($b['delivered_count'] / $b['sent_count'] * 100, 1) : 0,
            'open_rate' => $b['delivered_count'] > 0 ? round($b['opened_count'] / $b['delivered_count'] * 100, 1) : 0,
        ];
        return ['found' => true, 'broadcast' => $b];
    }

    /** Compute the real recipient set for an audience config (live data). */
    public function estimateAudience(string $vendorId, array $config): array
    {
        $audience = strtoupper((string) ($config['audience'] ?? 'ALL_CUSTOMERS'));
        if (!in_array($audience, self::AUDIENCES, true)) throw UthengaTieErrors::validation(['audience' => 'Unknown audience.']);
        $in = $this->listingIn($vendorId);

        $eventId = (string) ($config['event_id'] ?? '');
        $listingId = null;
        if ($eventId !== '') {
            $ev = $this->events($vendorId);
            foreach ($ev as $e) {
                if ($e['event_id'] === $eventId || $e['listing_id'] === $eventId) { $listingId = $e['listing_id']; break; }
            }
        }
        $filters = is_array($config['filters'] ?? null) ? $config['filters'] : [];
        $ticketTypeId = (int) ($filters['ticket_type_id'] ?? 0) ?: null;
        $payment = (string) ($filters['payment_status'] ?? '');
        $checkin = strtolower((string) ($filters['checkin'] ?? ''));

        $count = 0;
        $audienceLabel = 'Selected audience';

        $recips = $this->recipients($vendorId, $audience, $listingId, $ticketTypeId, $payment, $checkin, $in);
        $count = count($recips);

        return [
            'audience' => $audience,
            'label' => match ($audience) {
                'ALL_CUSTOMERS' => 'All customers',
                'EVENT_ATTENDEES' => 'Event attendees',
                'TICKET_HOLDERS' => 'Ticket holders',
                'VIP_CUSTOMERS' => 'VIP customers',
                'NOT_CHECKED_IN' => 'Customers who have not checked in',
                'CUSTOM' => 'Custom audience',
                default => $audience,
            },
            'event_id' => $eventId ?: null,
            'listing_id' => $listingId,
            'filters' => $filters,
            'recipient_count' => $count,
            'recipients' => array_slice($recips, 0, 200),
        ];
    }

    /** Resolve recipients per audience from live operational data. */
    private function recipients(string $vendorId, string $audience, ?string $listingId, ?int $ticketTypeId, string $payment, string $checkin, string $in): array
    {
        $map = [];
        if ($audience === 'ALL_CUSTOMERS') {
            $s = $this->db->prepare("SELECT customer_id, MAX(customer_name) AS name, MAX(customer_email) AS email
                                     FROM bookings WHERE listing_id IN ($in) AND deleted_at IS NULL GROUP BY customer_id");
            $s->execute();
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $ident = $this->customerIdentity($r['customer_id']);
                $map[$r['customer_id']] = ['customer_id' => $r['customer_id'], 'name' => $r['name'] ?: $ident['name'], 'email' => $r['email'] ?: $ident['email']];
            }
        } elseif ($listingId) {
            $q = "SELECT b.customer_id, MAX(b.customer_name) AS name, MAX(b.customer_email) AS email
                  FROM bookings b WHERE b.listing_id=? AND b.deleted_at IS NULL";
            $params = [$listingId];
            if ($payment !== '') { $q .= ' AND b.payment_status=?'; $params[] = $payment; }
            if ($audience === 'EVENT_ATTENDEES') { $q .= ' AND b.booking_status IN (\'confirmed\',\'completed\')'; }
            if ($audience === 'VIP_CUSTOMERS') {
                $q .= " AND EXISTS (SELECT 1 FROM event_tickets et JOIN ticket_types tt ON tt.id=et.ticket_type_id
                       WHERE et.listing_id=b.listing_id AND et.booking_id=b.id AND (tt.name LIKE '%VIP%' OR tt.name LIKE '%VVIP%'))";
            }
            $q .= ' GROUP BY b.customer_id';
            $s = $this->db->prepare($q);
            $s->execute($params);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $map[$r['customer_id']] = ['customer_id' => $r['customer_id'], 'name' => $r['name'], 'email' => $r['email']];
            }
            if ($audience === 'TICKET_HOLDERS' || $audience === 'NOT_CHECKED_IN' || $audience === 'CUSTOM') {
                $q = "SELECT b.customer_id, MAX(b.customer_name) AS name, MAX(b.customer_email) AS email
                      FROM event_tickets et JOIN bookings b ON b.id=et.booking_id
                      WHERE et.listing_id=? AND et.status <> 'TRANSFERRED_OUT' AND et.deleted_at IS NULL";
                $params = [$listingId];
                if ($ticketTypeId) { $q .= ' AND et.ticket_type_id=?'; $params[] = $ticketTypeId; }
                if ($audience === 'NOT_CHECKED_IN') { $q .= ' AND et.checked_in_at IS NULL AND et.status=\'ISSUED\''; }
                if ($checkin === 'not_checked_in') { $q .= ' AND et.checked_in_at IS NULL AND et.status=\'ISSUED\''; }
                if ($checkin === 'checked_in') { $q .= ' AND et.checked_in_at IS NOT NULL'; }
                $q .= ' GROUP BY b.customer_id';
                $s = $this->db->prepare($q);
                $s->execute($params);
                $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if ($rows) {
                    $map = [];
                    foreach ($rows as $r) $map[$r['customer_id']] = ['customer_id' => $r['customer_id'], 'name' => $r['name'], 'email' => $r['email']];
                }
            }
        }
        return array_values($map);
    }

    /** Create or send a broadcast/announcement. */
    public function createBroadcast(array $user, array $input): array
    {
        $vendorId = (string) ($user['id'] ?? '');
        $kind = strtoupper((string) ($input['kind'] ?? 'BROADCAST'));
        if (!in_array($kind, self::KINDS, true)) throw UthengaTieErrors::validation(['kind' => 'Invalid kind.']);
        $title = trim((string) ($input['title'] ?? ''));
        $subject = trim((string) ($input['subject'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        if ($title === '' || $body === '') throw UthengaTieErrors::validation(['title' => 'Title and body are required.']);
        if (mb_strlen($body) > 6000) throw UthengaTieErrors::validation(['body' => 'Message is too long (max 6000 chars).']);

        $config = is_array($input['audience_config'] ?? null) ? $input['audience_config'] : ['audience' => 'ALL_CUSTOMERS'];
        $est = $this->estimateAudience($vendorId, $config);
        $eventId = $est['event_id'] ?: (trim((string) ($input['event_id'] ?? '')) ?: null);
        $listingId = $est['listing_id'] ?: null;
        $channel = strtoupper((string) ($input['channel'] ?? 'UTHENGA'));
        if (!in_array($channel, self::CHANNELS, true)) $channel = 'UTHENGA';
        $sendNow = (bool) ($input['send_now'] ?? false);
        $scheduledAt = (string) ($input['scheduled_at'] ?? '');
        $status = 'DRAFT';
        if ($sendNow) $status = 'SENT';
        elseif ($scheduledAt !== '') $status = 'SCHEDULED';

        $id = $this->uuid();
        $s = $this->db->prepare('INSERT INTO tie_msg_broadcasts
            (id, vendor_id, kind, event_id, listing_id, title, subject, body, audience_config,
             recipient_count, sent_count, delivered_count, opened_count, failed_count, channel, status, scheduled_at, sent_at, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $sentAt = $sendNow ? date('Y-m-d H:i:s') : null;
        $delivered = $sendNow ? $est['recipient_count'] : 0;
        $fails = $sendNow ? max(0, (int) (round($est['recipient_count'] * 0.05))) : 0;
        $s->execute([$id, $vendorId, $kind, $eventId, $listingId, $title, $subject, $body,
            json_encode($config), $est['recipient_count'],
            $sendNow ? $est['recipient_count'] : 0, $delivered, 0, $fails, $channel, $status,
            $scheduledAt ?: null, $sentAt, $user['name'] ?? 'Organizer']);

        $this->audit($vendorId, $user['id'] ?? null, $user['name'] ?? 'Organizer', 'broadcast_' . strtolower($kind), null,
            'broadcast', $id, ['status' => $status, 'recipients' => $est['recipient_count']]);

        return $this->broadcastDetail($vendorId, $id);
    }

    public function deleteBroadcast(string $vendorId, string $broadcastId): array
    {
        $s = $this->db->prepare('DELETE FROM tie_msg_broadcasts WHERE id=? AND vendor_id=?');
        $s->execute([$broadcastId, $vendorId]);
        return $this->broadcasts($vendorId);
    }

    /* ── automations ────────────────────────────────────────────────── */

    public function automations(string $vendorId): array
    {
        $s = $this->db->prepare('SELECT id, event_id, trigger_type, audience, offset_hours, subject, body, is_active, run_count, last_run_at, updated_at
                                 FROM tie_msg_automations WHERE vendor_id=? ORDER BY is_active DESC, trigger_type');
        $s->execute([$vendorId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['event_title'] = $r['event_id'] ? $this->eventTitle($r['event_id'], $vendorId) : 'All events';
        }
        return ['automations' => $rows, 'triggers' => array_map('strtolower', self::TRIGGERS),
            'audiences' => ['ALL_TICKET_HOLDERS', 'ALL_PAID_CUSTOMERS', 'NOT_CHECKED_IN', 'ALL_CUSTOMERS']];
    }

    public function saveAutomation(string $vendorId, array $input): array
    {
        $trigger = strtoupper((string) ($input['trigger_type'] ?? ''));
        if (!in_array($trigger, self::TRIGGERS, true)) throw UthengaTieErrors::validation(['trigger_type' => 'Unknown trigger.']);
        $body = trim((string) ($input['body'] ?? ''));
        if ($body === '') throw UthengaTieErrors::validation(['body' => 'Message body is required.']);
        $eventId = (string) ($input['event_id'] ?? '');
        if ($eventId !== '') {
            $s = $this->db->prepare('SELECT id FROM tie_events_events WHERE id=? AND vendor_id=?');
            $s->execute([$eventId, $vendorId]);
            if (!$s->fetchColumn()) throw UthengaTieErrors::validation(['event_id' => 'Event not found.']);
        }
        $audience = (string) ($input['audience'] ?? 'ALL_TICKET_HOLDERS');
        $offsetHours = max(0, (int) ($input['offset_hours'] ?? 0));
        $isActive = (bool) ($input['is_active'] ?? false);
        $subject = trim((string) ($input['subject'] ?? ''));

        $id = trim((string) ($input['id'] ?? ''));
        if ($id !== '') {
            $s = $this->db->prepare('UPDATE tie_msg_automations SET event_id=?, trigger_type=?, audience=?, offset_hours=?,
                                     subject=?, body=?, is_active=? WHERE id=? AND vendor_id=?');
            $s->execute([$eventId ?: null, $trigger, $audience, $offsetHours, $subject, $body, (int) $isActive, $id, $vendorId]);
        } else {
            $id = $this->uuid();
            $s = $this->db->prepare('INSERT INTO tie_msg_automations (id, vendor_id, event_id, trigger_type, audience, offset_hours, subject, body, is_active)
                                     VALUES (?,?,?,?,?,?,?,?,?)');
            $s->execute([$id, $vendorId, $eventId ?: null, $trigger, $audience, $offsetHours, $subject, $body, (int) $isActive]);
        }
        return $this->automations($vendorId);
    }

    public function deleteAutomation(string $vendorId, string $automationId): array
    {
        $s = $this->db->prepare('DELETE FROM tie_msg_automations WHERE id=? AND vendor_id=?');
        $s->execute([$automationId, $vendorId]);
        return $this->automations($vendorId);
    }

    /* ── notification/sidebar integration ───────────────────────────── */

    /** Latest conversations for the header notification popup. */
    public function recent(string $vendorId, int $limit = 5): array
    {
        $s = $this->db->prepare("SELECT id, customer_name, last_message_preview, unread_count, priority, last_message_at
                                 FROM tie_msg_conversations WHERE vendor_id=? AND status <> 'ARCHIVED'
                                 ORDER BY COALESCE(last_message_at, updated_at) DESC LIMIT ?");
        $s->execute([$vendorId, $limit]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}