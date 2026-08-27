<?php
/**
 * Trip Planning Assistant — Messages. One shared group thread per trip,
 * readable and postable by anyone with real access to the plan (owner or an
 * accepted collaborator — see TripCollaboration.php). This is genuinely new:
 * every other messaging table in this codebase (Events, Quick Taxi
 * coordination, Quick Taxi direct messages, accommodation ops) is a strict
 * 1:1 pair; a trip can have more than two real participants.
 */
final class UthengaTieTripConversationContracts
{
    public static function body($value): string
    {
        $body = is_string($value) ? trim($value) : '';
        if ($body === '' || mb_strlen($body) > 2000) throw UthengaTieErrors::validation(['body' => 'A message between 1 and 2000 characters is required.']);
        return $body;
    }
}

final class UthengaTieTripMessagingService
{
    public function __construct(private ?PDO $db, private UthengaTieTripCollaborationService $collaboration)
    {
    }

    public function list(string $planId, string $userId): array
    {
        $this->db();
        if ($this->collaboration->accessFor($planId, $userId) === null) throw UthengaTieErrors::authorization();
        $stmt = $this->db->prepare('SELECT m.id, m.sender_user_id, u.name AS sender_name, m.body, m.created_at FROM trip_conversation_messages m JOIN users u ON u.id = m.sender_user_id WHERE m.plan_id = ? ORDER BY m.created_at ASC LIMIT 500');
        $stmt->execute([$planId]);
        return [
            'schema_version' => 'tie-trip-messages/v1', 'plan_id' => $planId, 'viewer_id' => $userId,
            'messages' => array_map(fn(array $row): array => [
                'id' => (int) $row['id'], 'sender_id' => (string) $row['sender_user_id'], 'sender_name' => (string) $row['sender_name'],
                'body' => (string) $row['body'], 'created_at' => $this->utcIso($row['created_at']),
            ], $stmt->fetchAll()),
        ];
    }

    public function send(array $input, string $userId): array
    {
        $this->db();
        $planId = UthengaTiePlanContracts::planId($input);
        $body = UthengaTieTripConversationContracts::body($input['body'] ?? null);
        if ($this->collaboration->accessFor($planId, $userId) === null) throw UthengaTieErrors::authorization();
        $this->db->prepare('INSERT INTO trip_conversation_messages (plan_id, sender_user_id, body) VALUES (?, ?, ?)')->execute([$planId, $userId, $body]);
        return $this->list($planId, $userId);
    }

    private function utcIso($value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
    }

    private function db(): void
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('trip_conversation');
    }
}
