<?php
/**
 * Quick Taxi Operations — Messages.
 *
 * A historical manual trip (tie_trips) has no authenticated passenger on the
 * other end, so it stays what it always was: a "Passengers" list of past
 * trips to call/open, plus a "System" feed of the driver's own trip
 * lifecycle events (tie_trip_events) presented as notifications with real
 * read/unread state.
 *
 * Real, authenticated Quick Taxi customers do exist now (Coordination), and
 * a live coordination session already carries its own two-way chat
 * (tie_transport_messages) — but that thread closes the moment the session
 * stops being interactive. The direct-message tables below give a driver and
 * a real passenger they have actually carried a persistent 1-1 thread that
 * survives past any single departure (e.g. a forgotten bag afterwards). A
 * thread may only ever be opened between a vendor and a customer who share a
 * real (non-walk-in) tie_transport_sessions row — never an arbitrary user.
 */

final class UthengaTieMessagingContracts
{
    public static function eventId($value): int
    {
        if (!filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) throw UthengaTieErrors::validation(['event_id' => 'A valid notification reference is required.']);
        return (int) $value;
    }

    public static function uuid($value, string $field): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9-]{36}$/', strtolower($value))) throw UthengaTieErrors::validation([$field => 'A valid reference is required.']);
        return strtolower($value);
    }

    // User primary keys in this codebase are not UUIDs (e.g. "U-6EF42F20",
    // "c-1", "tie-demo-ltaxi-a18"), so customer_id only needs to be a
    // plausible non-empty identifier — existence is enforced by the
    // "shared a real session" check in startDirectThread(), not here.
    public static function userId($value, string $field): string
    {
        $id = is_string($value) ? trim($value) : '';
        if ($id === '' || mb_strlen($id) > 64) throw UthengaTieErrors::validation([$field => 'A valid reference is required.']);
        return $id;
    }

    public static function directBody($value): string
    {
        $body = is_string($value) ? trim($value) : '';
        if ($body === '' || mb_strlen($body) > 1000) throw UthengaTieErrors::validation(['body' => 'A message between 1 and 1000 characters is required.']);
        return $body;
    }
}

final class UthengaTieMessagingService
{
    private const NOTIFICATION_TITLES = [
        'TRIP_CREATED' => 'New trip logged for %s',
        'TRIP_ACCEPTED' => 'Trip accepted for %s',
        'TRIP_EN_ROUTE' => 'En route to pick up %s',
        'TRIP_ARRIVED' => 'Arrived at pickup for %s',
        'TRIP_ONBOARD' => '%s is onboard',
        'TRIP_IN_PROGRESS' => 'Trip started with %s',
        'TRIP_COMPLETED' => 'Trip with %s completed',
        'TRIP_CANCELLED' => 'Trip with %s was cancelled',
        'TRIP_NO_SHOW' => '%s did not show up',
    ];

    public function __construct(private ?PDO $db) {}

    public function conversations(string $driverUserId): array
    {
        $this->db();
        $stmt = $this->db->prepare('SELECT id, trip_code, passenger_name, passenger_phone, pickup_location, destination_location, status, requested_at FROM tie_trips WHERE driver_user_id = ? ORDER BY requested_at DESC LIMIT 30');
        $stmt->execute([$driverUserId]);
        $conversations = array_map(fn(array $row): array => [
            'trip_id' => (string) $row['id'],
            'trip_code' => (string) $row['trip_code'],
            'passenger_name' => (string) $row['passenger_name'],
            'passenger_phone' => $row['passenger_phone'] !== null ? (string) $row['passenger_phone'] : null,
            'summary' => $row['pickup_location'] . ' → ' . $row['destination_location'],
            'status' => (string) $row['status'],
            'last_activity_at' => $this->utcIso($row['requested_at']),
        ], $stmt->fetchAll());
        return ['schema_version' => 'tie-message-conversations/v1', 'conversations' => $conversations];
    }

    public function notifications(string $driverUserId): array
    {
        $this->db();
        $stmt = $this->db->prepare(
            'SELECT e.id, e.event_type, e.reason, e.created_at, e.read_at, t.id AS trip_id, t.trip_code, t.passenger_name
             FROM tie_trip_events e JOIN tie_trips t ON t.id = e.trip_id
             WHERE t.driver_user_id = ? ORDER BY e.id DESC LIMIT 100'
        );
        $stmt->execute([$driverUserId]);
        $items = array_map(fn(array $row): array => [
            'id' => (int) $row['id'],
            'trip_id' => (string) $row['trip_id'],
            'trip_code' => (string) $row['trip_code'],
            'title' => $this->title((string) $row['event_type'], (string) $row['passenger_name']),
            'reason' => $row['reason'] ?? null,
            'created_at' => $this->utcIso($row['created_at']),
            'is_read' => $row['read_at'] !== null,
        ], $stmt->fetchAll());
        return ['schema_version' => 'tie-message-notifications/v1', 'notifications' => $items, 'unread_count' => count(array_filter($items, fn(array $item): bool => !$item['is_read']))];
    }

    public function markRead(string $driverUserId, int $eventId): array
    {
        $this->db();
        $this->db->prepare('UPDATE tie_trip_events e JOIN tie_trips t ON t.id = e.trip_id SET e.read_at = UTC_TIMESTAMP() WHERE e.id = ? AND t.driver_user_id = ? AND e.read_at IS NULL')
            ->execute([$eventId, $driverUserId]);
        return $this->notifications($driverUserId);
    }

    public function markAllRead(string $driverUserId): array
    {
        $this->db();
        $this->db->prepare('UPDATE tie_trip_events e JOIN tie_trips t ON t.id = e.trip_id SET e.read_at = UTC_TIMESTAMP() WHERE t.driver_user_id = ? AND e.read_at IS NULL')
            ->execute([$driverUserId]);
        return $this->notifications($driverUserId);
    }

    /* ── direct 1-1 messaging (persistent, independent of any single run) ── */

    /** Real (non-walk-in) passengers this vendor has actually carried — the
     *  only people a driver may open a direct thread with. */
    public function knownPassengers(string $vendorId): array
    {
        $this->db();
        $stmt = $this->db->prepare(
            'SELECT s.customer_id, u.name, MAX(s.created_at) AS last_session_at
             FROM tie_transport_sessions s JOIN users u ON u.id = s.customer_id
             WHERE s.vendor_id = ? AND s.customer_id IS NOT NULL
             GROUP BY s.customer_id, u.name ORDER BY last_session_at DESC LIMIT 100'
        );
        $stmt->execute([$vendorId]);
        return ['schema_version' => 'tie-direct-message-contacts/v1', 'passengers' => array_map(fn(array $row): array => [
            'customer_id' => (string) $row['customer_id'],
            'name' => (string) $row['name'],
            'last_session_at' => $this->utcIso($row['last_session_at']),
        ], $stmt->fetchAll())];
    }

    /** Start (or fetch the existing) direct thread between this vendor and a
     *  passenger they have actually carried before. Vendor-initiated only —
     *  a customer reaches a thread that already exists via directInbox(). */
    public function startDirectThread(array $input, string $vendorId): array
    {
        $this->db();
        $customerId = UthengaTieMessagingContracts::userId($input['customer_id'] ?? null, 'customer_id');
        $shared = $this->db->prepare('SELECT 1 FROM tie_transport_sessions WHERE vendor_id = ? AND customer_id = ? LIMIT 1');
        $shared->execute([$vendorId, $customerId]);
        if ($shared->fetchColumn() === false) throw UthengaTieErrors::validation(['customer_id' => 'You can only message a passenger you have actually carried.']);

        $existing = $this->db->prepare('SELECT id FROM tie_transport_direct_threads WHERE vendor_id = ? AND customer_id = ?');
        $existing->execute([$vendorId, $customerId]);
        $threadId = $existing->fetchColumn();
        if ($threadId === false) {
            $threadId = $this->uuid();
            $this->db->prepare('INSERT INTO tie_transport_direct_threads (id, vendor_id, customer_id) VALUES (?, ?, ?)')->execute([$threadId, $vendorId, $customerId]);
        }
        return $this->directThread((string) $threadId, $vendorId);
    }

    /** This user's direct-message inbox — works for either a vendor or a
     *  customer, since a thread row already names both sides. */
    public function directInbox(string $actorId): array
    {
        $this->db();
        $stmt = $this->db->prepare(
            'SELECT t.id, t.vendor_id, t.customer_id, t.last_message_at, t.last_message_preview,
                    CASE WHEN t.vendor_id = ? THEN t.vendor_unread_count ELSE t.customer_unread_count END AS unread_count,
                    CASE WHEN t.vendor_id = ? THEN cu.name ELSE vu.name END AS peer_name
             FROM tie_transport_direct_threads t
             JOIN users cu ON cu.id = t.customer_id
             JOIN users vu ON vu.id = t.vendor_id
             WHERE t.vendor_id = ? OR t.customer_id = ?
             ORDER BY COALESCE(t.last_message_at, t.created_at) DESC LIMIT 100'
        );
        $stmt->execute([$actorId, $actorId, $actorId, $actorId]);
        return ['schema_version' => 'tie-direct-message-inbox/v1', 'threads' => array_map(fn(array $row): array => [
            'id' => (string) $row['id'],
            'peer_name' => (string) $row['peer_name'],
            'last_message_at' => $this->utcIso($row['last_message_at']),
            'last_message_preview' => $row['last_message_preview'],
            'unread_count' => (int) $row['unread_count'],
        ], $stmt->fetchAll())];
    }

    /** One thread's messages, for whichever side is viewing. */
    public function directThread(string $threadId, string $actorId): array
    {
        $this->db();
        $thread = $this->directThreadRow($threadId);
        if ($thread === null) throw UthengaTieErrors::validation(['thread_id' => 'Conversation not found.']);
        $role = $this->directRole($thread, $actorId);
        if ($role === null) throw UthengaTieErrors::authorization();

        $peerId = $role === 'vendor' ? $thread['customer_id'] : $thread['vendor_id'];
        $peer = $this->db->prepare('SELECT name FROM users WHERE id = ?');
        $peer->execute([$peerId]);

        $messages = $this->db->prepare('SELECT id, sender_role, body, created_at FROM tie_transport_direct_messages WHERE thread_id = ? ORDER BY created_at ASC LIMIT 200');
        $messages->execute([$threadId]);

        return [
            'schema_version' => 'tie-direct-message-thread/v1',
            'thread_id' => (string) $thread['id'],
            'viewer_role' => $role,
            'peer_name' => (string) ($peer->fetchColumn() ?: 'Uthenga user'),
            'messages' => array_map(fn(array $row): array => [
                'id' => (string) $row['id'], 'sender_role' => (string) $row['sender_role'],
                'body' => (string) $row['body'], 'created_at' => $this->utcIso($row['created_at']),
            ], $messages->fetchAll()),
        ];
    }

    public function sendDirectMessage(array $input, string $actorId): array
    {
        $this->db();
        $threadId = UthengaTieMessagingContracts::uuid($input['thread_id'] ?? null, 'thread_id');
        $body = UthengaTieMessagingContracts::directBody($input['body'] ?? null);
        $thread = $this->directThreadRow($threadId);
        if ($thread === null) throw UthengaTieErrors::validation(['thread_id' => 'Conversation not found.']);
        $role = $this->directRole($thread, $actorId);
        if ($role === null) throw UthengaTieErrors::authorization();

        $this->db->prepare('INSERT INTO tie_transport_direct_messages (id, thread_id, sender_role, body) VALUES (?, ?, ?, ?)')
            ->execute([$this->uuid(), $threadId, $role, $body]);
        $unreadColumn = $role === 'vendor' ? 'customer_unread_count' : 'vendor_unread_count';
        $this->db->prepare("UPDATE tie_transport_direct_threads SET last_message_at = UTC_TIMESTAMP(), last_message_preview = ?, {$unreadColumn} = {$unreadColumn} + 1 WHERE id = ?")
            ->execute([mb_substr($body, 0, 255), $threadId]);

        return $this->directThread($threadId, $actorId);
    }

    public function markDirectThreadRead(array $input, string $actorId): array
    {
        $this->db();
        $threadId = UthengaTieMessagingContracts::uuid($input['thread_id'] ?? null, 'thread_id');
        $thread = $this->directThreadRow($threadId);
        if ($thread === null) throw UthengaTieErrors::validation(['thread_id' => 'Conversation not found.']);
        $role = $this->directRole($thread, $actorId);
        if ($role === null) throw UthengaTieErrors::authorization();
        $unreadColumn = $role === 'vendor' ? 'vendor_unread_count' : 'customer_unread_count';
        $this->db->prepare("UPDATE tie_transport_direct_threads SET {$unreadColumn} = 0 WHERE id = ?")->execute([$threadId]);
        return ['ok' => true];
    }

    private function directThreadRow(string $threadId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tie_transport_direct_threads WHERE id = ?');
        $stmt->execute([$threadId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function directRole(array $thread, string $actorId): ?string
    {
        return (string) $thread['vendor_id'] === $actorId ? 'vendor' : ((string) $thread['customer_id'] === $actorId ? 'customer' : null);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function title(string $eventType, string $passengerName): string
    {
        $template = self::NOTIFICATION_TITLES[$eventType] ?? null;
        return $template === null ? str_replace('_', ' ', $eventType) : sprintf($template, $passengerName);
    }

    private function utcIso($value): ?string { if (!is_string($value) || trim($value) === '') return null; return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c'); }
    private function db(): void { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('messaging'); }
}
