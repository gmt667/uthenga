<?php
/**
 * Trip Planning Assistant — collaboration. Trips (trip_itineraries) were
 * strictly single-owner before this file existed; a trip_collaborators row
 * grants a second real Uthenga account read (viewer) or read+write (editor)
 * access to one plan. Invites resolve immediately against a real account
 * found by email (the same `SELECT id FROM users WHERE email=?` pattern
 * Staff.php::userIdByEmail() already uses for vendor-staff invites) — there
 * is no separate pending/accept step yet, and an unknown email fails
 * honestly rather than pretending an email invite was sent.
 */
final class UthengaTieTripCollaborationContracts
{
    public static function role($value): string
    {
        $role = strtolower(trim((string) $value));
        if (!in_array($role, ['viewer', 'editor'], true)) throw UthengaTieErrors::validation(['role' => 'Role must be viewer or editor.']);
        return $role;
    }

    public static function email($value): string
    {
        $email = strtolower(trim((string) $value));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw UthengaTieErrors::validation(['email' => 'A valid email is required.']);
        return $email;
    }
}

final class UthengaTieTripCollaborationService
{
    public function __construct(private ?PDO $db)
    {
    }

    /** null if this user has no access to this plan at all. */
    public function accessFor(string $planId, string $userId): ?array
    {
        $this->db();
        $owner = $this->db->prepare('SELECT user_id FROM trip_itineraries WHERE itinerary_code = ? LIMIT 1');
        $owner->execute([$planId]);
        $ownerId = $owner->fetchColumn();
        if ($ownerId === false) return null;
        if ((string) $ownerId === $userId) return ['role' => 'owner'];

        $stmt = $this->db->prepare("SELECT role FROM trip_collaborators WHERE plan_id = ? AND invited_user_id = ? AND status = 'accepted' LIMIT 1");
        $stmt->execute([$planId, $userId]);
        $role = $stmt->fetchColumn();
        return $role !== false ? ['role' => (string) $role] : null;
    }

    public function canWrite(string $planId, string $userId): bool
    {
        $access = $this->accessFor($planId, $userId);
        return $access !== null && in_array($access['role'], ['owner', 'editor'], true);
    }

    /** plan_id => role, for every plan this user collaborates on (not owns) — used by Planning::list() to union in shared trips. */
    public function sharedPlanRoles(string $userId): array
    {
        $this->db();
        $stmt = $this->db->prepare("SELECT plan_id, role FROM trip_collaborators WHERE invited_user_id = ? AND status = 'accepted'");
        $stmt->execute([$userId]);
        $roles = [];
        foreach ($stmt->fetchAll() as $row) $roles[(string) $row['plan_id']] = (string) $row['role'];
        return $roles;
    }

    public function members(string $planId, string $requesterId): array
    {
        $this->db();
        if ($this->accessFor($planId, $requesterId) === null) throw UthengaTieErrors::authorization();

        $owner = $this->db->prepare('SELECT user_id FROM trip_itineraries WHERE itinerary_code = ? LIMIT 1');
        $owner->execute([$planId]);
        $ownerId = (string) $owner->fetchColumn();
        $ownerName = $this->userName($ownerId);

        $stmt = $this->db->prepare("SELECT c.invited_user_id, c.invited_email, c.role, u.name FROM trip_collaborators c JOIN users u ON u.id = c.invited_user_id WHERE c.plan_id = ? AND c.status = 'accepted' ORDER BY c.created_at ASC");
        $stmt->execute([$planId]);
        $members = [['user_id' => $ownerId, 'name' => $ownerName, 'email' => null, 'role' => 'owner']];
        foreach ($stmt->fetchAll() as $row) {
            $members[] = ['user_id' => (string) $row['invited_user_id'], 'name' => (string) $row['name'], 'email' => (string) $row['invited_email'], 'role' => (string) $row['role']];
        }
        return ['schema_version' => 'tie-trip-members/v1', 'plan_id' => $planId, 'viewer_role' => $this->accessFor($planId, $requesterId)['role'], 'members' => $members];
    }

    public function invite(array $input, string $requesterId): array
    {
        $this->db();
        $planId = UthengaTiePlanContracts::planId($input);
        $email = UthengaTieTripCollaborationContracts::email($input['email'] ?? null);
        $role = UthengaTieTripCollaborationContracts::role($input['role'] ?? null);
        $access = $this->accessFor($planId, $requesterId);
        if ($access === null || $access['role'] !== 'owner') throw UthengaTieErrors::authorization();

        $user = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $user->execute([$email]);
        $invitedUserId = $user->fetchColumn();
        if ($invitedUserId === false) throw UthengaTieErrors::validation(['email' => 'No Uthenga account was found with that email.']);
        if ((string) $invitedUserId === $requesterId) throw UthengaTieErrors::validation(['email' => 'You already own this trip.']);

        $stmt = $this->db->prepare("INSERT INTO trip_collaborators (plan_id, owner_user_id, invited_user_id, invited_email, role, status) VALUES (?, ?, ?, ?, ?, 'accepted') ON DUPLICATE KEY UPDATE role = VALUES(role), status = 'accepted'");
        $stmt->execute([$planId, $requesterId, $invitedUserId, $email, $role]);

        $trip = $this->db->prepare('SELECT title FROM trip_itineraries WHERE itinerary_code = ? LIMIT 1');
        $trip->execute([$planId]);
        $title = (string) ($trip->fetchColumn() ?: 'a trip');
        $this->notify((string) $invitedUserId, 'Added to a trip', "You've been added to \"{$title}\" as a {$role}.");

        return $this->members($planId, $requesterId);
    }

    public function changeRole(array $input, string $requesterId): array
    {
        $this->db();
        $planId = UthengaTiePlanContracts::planId($input);
        $memberId = trim((string) ($input['member_user_id'] ?? ''));
        $role = UthengaTieTripCollaborationContracts::role($input['role'] ?? null);
        $access = $this->accessFor($planId, $requesterId);
        if ($access === null || $access['role'] !== 'owner') throw UthengaTieErrors::authorization();
        if ($memberId === '') throw UthengaTieErrors::validation(['member_user_id' => 'A member is required.']);

        $stmt = $this->db->prepare("UPDATE trip_collaborators SET role = ? WHERE plan_id = ? AND invited_user_id = ? AND status = 'accepted'");
        $stmt->execute([$role, $planId, $memberId]);
        if ($stmt->rowCount() === 0) throw UthengaTieErrors::validation(['member_user_id' => 'That member was not found on this trip.']);
        return $this->members($planId, $requesterId);
    }

    public function revoke(array $input, string $requesterId): array
    {
        $this->db();
        $planId = UthengaTiePlanContracts::planId($input);
        $memberId = trim((string) ($input['member_user_id'] ?? ''));
        $access = $this->accessFor($planId, $requesterId);
        if ($access === null || $access['role'] !== 'owner') throw UthengaTieErrors::authorization();

        $stmt = $this->db->prepare("UPDATE trip_collaborators SET status = 'revoked' WHERE plan_id = ? AND invited_user_id = ?");
        $stmt->execute([$planId, $memberId]);
        return $this->members($planId, $requesterId);
    }

    private function notify(string $userId, string $title, string $body): void
    {
        if (!UthengaTieFeatureFlags::enabled('notifications')) return;
        try { (new UthengaTieNotificationOutbox($this->db))->enqueue($userId, ['channel' => 'in_app', 'title' => $title, 'body' => $body]); } catch (Throwable $ignore) {}
    }

    private function userName(string $userId): string
    {
        $stmt = $this->db->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn();
        return $name !== false ? (string) $name : 'Uthenga traveller';
    }

    private function db(): void
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('trip_collaboration');
    }
}
