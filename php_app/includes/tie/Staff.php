<?php
/** Staff — the access-control center of the Events Control Center.
 *
 *  Staff is NOT a separate identity system: a staff row references a platform
 *  `users` row and adds organization membership, an RBAC role with a
 *  per-module permission matrix, event-scoped assignments with optional
 *  time-bounded access, invitations (single-use, expiring, email-tied,
 *  revocable) and a full staff/security activity audit.
 *
 *  Resolution chain enforced here:
 *      user -> organization -> role -> permission -> event scope -> allow/deny
 *  All mutation endpoints require the actor to hold staff-module management
 *  permission (or be the organization owner). */

final class UthengaStaffService
{
    public const SCHEMA = 'tie-staff/v1';

    public const MODULE_GROUPS = [
        'Event management' => ['events', 'venues'],
        'Tickets & sales' => ['tickets'],
        'Attendees & CRM' => ['attendees', 'customers'],
        'Check-In' => ['checkin'],
        'Finance' => ['finance'],
        'Marketing & comms' => ['marketing', 'messages'],
        'Operations' => ['dashboard', 'analytics', 'reviews', 'documents'],
        'Access control' => ['staff', 'settings'],
    ];
    public const MODULE_LABELS = [
        'dashboard' => 'Dashboard', 'events' => 'Events', 'tickets' => 'Tickets',
        'attendees' => 'Attendees', 'checkin' => 'Check-In', 'venues' => 'Venues',
        'marketing' => 'Marketing', 'finance' => 'Finance', 'customers' => 'Customers',
        'analytics' => 'Analytics', 'reviews' => 'Reviews', 'messages' => 'Messages',
        'documents' => 'Documents', 'staff' => 'Staff', 'settings' => 'Settings',
    ];
    public const LEVELS = ['none', 'view', 'create', 'edit', 'manage', 'approve', 'export', 'delete'];
    public const LEVEL_RANK = ['none' => 0, 'view' => 1, 'create' => 2, 'edit' => 3, 'manage' => 4, 'approve' => 5, 'export' => 6, 'delete' => 7];
    public const SCOPE_LABELS = [
        'organization' => 'Organization', 'events' => 'Events', 'event_operations' => 'Event Operations',
        'marketing' => 'Marketing', 'finance' => 'Finance', 'checkin' => 'Check-In',
        'support' => 'Support', 'viewer' => 'Read-only', 'custom' => 'Custom',
    ];
    private const INVITE_EXPIRY_DAYS = 7;
    private const STAFF_STATUSES = ['active', 'pending', 'suspended', 'expired', 'removed'];
    private const INVITE_STATUSES = ['pending', 'accepted', 'expired', 'revoked'];

    /** Baseline roles for a fresh organization (custom roles supported later). */
    private const BASELINE_ROLES = [
        'owner' => ['name' => 'Owner', 'scope_type' => 'organization', 'description' => 'Highest business-level access. Manages organization, staff and all modules.', 'permissions' => ['dashboard' => 'manage', 'events' => 'delete', 'tickets' => 'approve', 'attendees' => 'manage', 'checkin' => 'manage', 'venues' => 'manage', 'marketing' => 'manage', 'finance' => 'approve', 'customers' => 'manage', 'analytics' => 'manage', 'reviews' => 'approve', 'messages' => 'manage', 'documents' => 'manage', 'staff' => 'manage', 'settings' => 'manage']],
        'event_manager' => ['name' => 'Event Manager', 'scope_type' => 'events', 'description' => 'Manages event configuration and day-to-day event operations.', 'permissions' => ['dashboard' => 'manage', 'events' => 'manage', 'tickets' => 'edit', 'attendees' => 'edit', 'checkin' => 'manage', 'venues' => 'edit', 'marketing' => 'view', 'finance' => 'view', 'customers' => 'view', 'analytics' => 'view', 'reviews' => 'edit', 'messages' => 'create', 'documents' => 'edit', 'staff' => 'none', 'settings' => 'none']],
        'operations_manager' => ['name' => 'Operations Manager', 'scope_type' => 'event_operations', 'description' => 'Manages operational activity: events, attendees, check-in and venues — no financial settings.', 'permissions' => ['dashboard' => 'view', 'events' => 'edit', 'tickets' => 'view', 'attendees' => 'edit', 'checkin' => 'manage', 'venues' => 'edit', 'marketing' => 'none', 'finance' => 'view', 'customers' => 'view', 'analytics' => 'view', 'reviews' => 'view', 'messages' => 'create', 'documents' => 'create', 'staff' => 'none', 'settings' => 'none']],
        'marketing_manager' => ['name' => 'Marketing Manager', 'scope_type' => 'marketing', 'description' => 'Marketing and communication: campaigns, broadcasts and customer insight.', 'permissions' => ['dashboard' => 'view', 'events' => 'view', 'tickets' => 'view', 'attendees' => 'view', 'checkin' => 'none', 'venues' => 'view', 'marketing' => 'manage', 'finance' => 'none', 'customers' => 'view', 'analytics' => 'export', 'reviews' => 'view', 'messages' => 'manage', 'documents' => 'create', 'staff' => 'none', 'settings' => 'none']],
        'finance_manager' => ['name' => 'Finance Manager', 'scope_type' => 'finance', 'description' => 'Financial operations: settlements, payouts, refunds and financial documents.', 'permissions' => ['dashboard' => 'view', 'events' => 'view', 'tickets' => 'view', 'attendees' => 'none', 'checkin' => 'none', 'venues' => 'none', 'marketing' => 'none', 'finance' => 'approve', 'customers' => 'view', 'analytics' => 'export', 'reviews' => 'none', 'messages' => 'none', 'documents' => 'view', 'staff' => 'none', 'settings' => 'none']],
        'checkin_staff' => ['name' => 'Check-In Staff', 'scope_type' => 'checkin', 'description' => 'On-site ticket verification and attendee check-in only.', 'permissions' => ['dashboard' => 'view', 'events' => 'view', 'tickets' => 'view', 'attendees' => 'view', 'checkin' => 'manage', 'venues' => 'view', 'marketing' => 'none', 'finance' => 'none', 'customers' => 'none', 'analytics' => 'none', 'reviews' => 'none', 'messages' => 'none', 'documents' => 'none', 'staff' => 'none', 'settings' => 'none']],
        'support_staff' => ['name' => 'Support Staff', 'scope_type' => 'support', 'description' => 'Customer and ticket support: attendees, orders and outbound help.', 'permissions' => ['dashboard' => 'view', 'events' => 'view', 'tickets' => 'view', 'attendees' => 'edit', 'checkin' => 'view', 'venues' => 'view', 'marketing' => 'none', 'finance' => 'none', 'customers' => 'edit', 'analytics' => 'none', 'reviews' => 'none', 'messages' => 'create', 'documents' => 'none', 'staff' => 'none', 'settings' => 'none']],
        'viewer' => ['name' => 'Viewer', 'scope_type' => 'viewer', 'description' => 'Read-only access across the workspace.', 'permissions' => ['dashboard' => 'view', 'events' => 'view', 'tickets' => 'view', 'attendees' => 'view', 'checkin' => 'view', 'venues' => 'view', 'marketing' => 'view', 'finance' => 'none', 'customers' => 'view', 'analytics' => 'view', 'reviews' => 'view', 'messages' => 'view', 'documents' => 'view', 'staff' => 'none', 'settings' => 'none']],
    ];

    public function __construct(private PDO $db)
    {
    }

    /* ── shared helpers ─────────────────────────────────────────────── */

    public function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public function enums(): array
    {
        return [
            'levels' => self::LEVELS,
            'modules' => self::MODULE_LABELS,
            'module_groups' => self::MODULE_GROUPS,
            'scopes' => self::SCOPE_LABELS,
            'staff_statuses' => self::STAFF_STATUSES,
            'invite_statuses' => self::INVITE_STATUSES,
            'invite_expiry_days' => self::INVITE_EXPIRY_DAYS,
        ];
    }

    /** All of this vendor's events with listing_id (event scope targets). */
    private function eventRows(string $vendorId): array
    {
        $s = $this->db->prepare('SELECT e.listing_id, e.id AS event_id, e.title, e.status, e.start_date
                                 FROM tie_events_events e
                                 WHERE e.vendor_id=? AND e.listing_id IS NOT NULL
                                 ORDER BY e.start_date ASC, e.title ASC');
        $s->execute([$vendorId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function eventsList(string $vendorId): array
    {
        return array_map(fn($e) => [
            'event_id' => $e['event_id'], 'listing_id' => $e['listing_id'],
            'title' => $e['title'], 'start_date' => $e['start_date'], 'status' => $e['status'],
        ], $this->eventRows($vendorId));
    }

    private function eventTitle(?string $eventId, string $vendorId): ?string
    {
        if (!$eventId) return null;
        $s = $this->db->prepare('SELECT title FROM tie_events_events WHERE id=? AND vendor_id=?');
        $s->execute([$eventId, $vendorId]);
        $t = $s->fetchColumn();
        return $t === false ? null : (string) $t;
    }

    private function eventExists(string $eventId, string $vendorId): bool
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM tie_events_events WHERE id=? AND vendor_id=?');
        $s->execute([$eventId, $vendorId]);
        return (int) $s->fetchColumn() > 0;
    }

    private function userIdByEmail(string $email): ?string
    {
        $s = $this->db->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
        $s->execute([$email]);
        $id = $s->fetchColumn();
        return $id === false ? null : (string) $id;
    }

    private function relTime(?string $dt): ?string
    {
        if (!$dt) return null;
        $ts = strtotime($dt . ' UTC');
        if ($ts === false) return null;
        $d = time() - $ts;
        if ($d < 0) $d = 0;
        if ($d < 60) return 'just now';
        if ($d < 3600) return (string) ((int) floor($d / 60)) . 'm ago';
        if ($d < 86400) return (string) ((int) floor($d / 3600)) . 'h ago';
        return (string) ((int) floor($d / 86400)) . 'd ago';
    }

    private function fmtDate(?string $dt): ?string
    {
        if (!$dt) return null;
        $ts = strtotime($dt . ' UTC');
        return $ts === false ? null : gmdate('Y-m-d H:i', $ts);
    }

    /* ── roles: baseline + persistence ──────────────────────────────── */

    public function ensureBaselineRoles(string $vendorId): int
    {
        $created = 0;
        $stmt = $this->db->prepare('INSERT INTO tie_staff_roles (id, vendor_id, role_key, name, description, scope_type, permissions, is_system, created_by)
                                    VALUES (?,?,?,?,?,?,?,1,?)
                                    ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)');
        foreach (self::BASELINE_ROLES as $key => $def) {
            $stmt->execute([
                $this->uuid(), $vendorId, $key, $def['name'], $def['description'],
                $def['scope_type'], json_encode($def['permissions']), $vendorId,
            ]);
            $created++;
        }
        return $created;
    }

    private function roleById(string $vendorId, string $roleId, string $label = 'role_id'): array
    {
        $s = $this->db->prepare('SELECT * FROM tie_staff_roles WHERE id=? AND vendor_id=? LIMIT 1');
        $s->execute([$roleId, $vendorId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)) throw UthengaTieErrors::validation([$label => 'Unknown role.']);
        $r['permissions'] = json_decode((string) $r['permissions'], true) ?: [];
        return $r;
    }

    private function validatePermissions(array $permissions, bool $any = false): bool
    {
        if (!$permissions) return !$any ? true && false : false;
        foreach ($permissions as $mod => $lvl) {
            if (!isset(self::MODULE_LABELS[$mod]) || !isset(self::LEVEL_RANK[(string) $lvl])) return false;
        }
        return true;
    }

    public function roles(string $vendorId): array
    {
        $this->ensureBaselineRoles($vendorId);
        $s = $this->db->prepare('SELECT r.*,
                                        (SELECT COUNT(*) FROM tie_staff st
                                         WHERE st.role_id=r.id AND st.vendor_id=? AND st.status<>\'removed\') AS members
                                 FROM tie_staff_roles r
                                 WHERE r.vendor_id=?
                                 ORDER BY r.is_system DESC, r.scope_type ASC, r.name ASC');
        $s->execute([$vendorId, $vendorId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($r) => [
            'id' => $r['id'], 'role_key' => $r['role_key'], 'name' => $r['name'],
            'description' => $r['description'], 'scope_type' => $r['scope_type'],
            'scope_label' => self::SCOPE_LABELS[$r['scope_type']] ?? $r['scope_type'],
            'permissions' => json_decode((string) $r['permissions'], true) ?: [],
            'is_system' => (bool) $r['is_system'], 'is_active' => (bool) $r['is_active'],
            'members' => (int) $r['members'],
        ], $rows);
    }

    public function roleDetail(string $vendorId, string $roleId): array
    {
        $this->ensureBaselineRoles($vendorId);
        $role = $this->roleById($vendorId, $roleId);
        $s = $this->db->prepare('SELECT st.id AS staff_id, st.status, u.id AS user_id, u.name, u.email,
                                        st.last_active_at
                                 FROM tie_staff st JOIN users u ON u.id = st.user_id COLLATE utf8mb4_general_ci
                                 WHERE st.role_id=? AND st.vendor_id=? AND st.status<>\'removed\'
                                 ORDER BY st.last_active_at DESC, u.name ASC');
        $s->execute([$roleId, $vendorId]);
        $members = array_map(fn($m) => [
            'staff_id' => $m['staff_id'], 'user_id' => $m['user_id'], 'name' => $m['name'],
            'email' => $m['email'], 'status' => $m['status'],
            'last_active' => $this->relTime($m['last_active_at']),
        ], $s->fetchAll(PDO::FETCH_ASSOC) ?: []);
        return [
            'id' => $role['id'], 'role_key' => $role['role_key'], 'name' => $role['name'],
            'description' => $role['description'], 'scope_type' => $role['scope_type'],
            'scope_label' => self::SCOPE_LABELS[$role['scope_type']] ?? $role['scope_type'],
            'is_system' => (bool) $role['is_system'], 'is_active' => (bool) $role['is_active'],
            'permissions' => $role['permissions'], 'members' => $members,
        ];
    }

    public function saveRole(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $input['vendor_id'] ?? $actor['id']);
        $vendorId = $actor['id'];
        $this->ensureBaselineRoles($vendorId);
        $permissions = $input['permissions'] ?? [];
        if (!is_array($permissions) || !$this->validatePermissions($permissions, true)) {
            throw UthengaTieErrors::validation(['permissions' => 'Invalid permission matrix.']);
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') throw UthengaTieErrors::validation(['name' => 'Role name is required.']);
        $scopeType = in_array((string) ($input['scope_type'] ?? 'custom'), ['organization', 'events', 'event_operations', 'marketing', 'finance', 'checkin', 'support', 'viewer', 'custom'], true)
            ? (string) $input['scope_type'] : 'custom';
        $description = trim((string) ($input['description'] ?? ''));
        $roleId = (string) ($input['role_id'] ?? '');
        $actorName = (string) ($actor['name'] ?? '');

        if ($roleId !== '') {
            $role = $this->roleById($vendorId, $roleId);
            $isSystem = (bool) $role['is_system'];
            $stmt = $this->db->prepare('UPDATE tie_staff_roles
                                        SET name=?, description=?, scope_type=?, permissions=?, updated_at=UTC_TIMESTAMP()
                                        WHERE id=? AND vendor_id=?');
            $stmt->execute([$name, $description, $scopeType, json_encode($permissions), $roleId, $vendorId]);
            $this->audit($vendorId, $actor, ($isSystem ? 'role_permissions_updated' : 'role_updated'),
                null, null, 'staff', true, ['role_id' => $roleId, 'role_name' => $name, 'permissions' => $permissions]);
            return ['role_id' => $roleId, 'saved' => true];
        }

        $key = 'custom_' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $name));
        $key = trim($key, '_');
        if ($key === '') $key = 'custom_role';
        $id = $this->uuid();
        $stmt = $this->db->prepare('INSERT INTO tie_staff_roles (id, vendor_id, role_key, name, description, scope_type, permissions, is_system, created_by)
                                    VALUES (?,?,?,?,?,?,?,0,?)');
        $stmt->execute([$id, $vendorId, $key, $name, $description, $scopeType, json_encode($permissions), $actorName]);
        $this->audit($vendorId, $actor, 'role_created', null, null, 'staff', true,
            ['role_id' => $id, 'role_name' => $name, 'permissions' => $permissions]);
        return ['role_id' => $id, 'saved' => true];
    }

    public function deleteRole(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $role = $this->roleById($actor['id'], (string) ($input['role_id'] ?? ''));
        if ((bool) $role['is_system']) throw UthengaTieErrors::validation(['role_id' => 'System roles cannot be deleted.']);
        $s = $this->db->prepare('SELECT COUNT(*) FROM tie_staff WHERE role_id=? AND vendor_id=? AND status<>\'removed\'');
        $s->execute([$role['id'], $actor['id']]);
        if ((int) $s->fetchColumn() > 0) {
            throw UthengaTieErrors::validation(['role_id' => 'Role still has members. Reassign them first.']);
        }
        $this->db->prepare('DELETE FROM tie_staff_roles WHERE id=? AND vendor_id=?')->execute([$role['id'], $actor['id']]);
        $this->audit($actor['id'], $actor, 'role_deleted', null, null, 'staff', true,
            ['role_id' => $role['id'], 'role_name' => $role['name']]);
        return ['deleted' => true];
    }

    /* ── staff members ──────────────────────────────────────────────── */

    private function staffRow(string $vendorId, string $staffId): array
    {
        $s = $this->db->prepare('SELECT st.*, u.name, u.email, u.avatar, u.joined_date, u.last_login_at,
                                        u.account_status, u.two_factor_enabled,
                                        r.name AS role_name, r.role_key, r.scope_type,
                                        r.permissions AS role_permissions
                                 FROM tie_staff st
                                 JOIN users u ON u.id = st.user_id COLLATE utf8mb4_general_ci
                                 JOIN tie_staff_roles r ON r.id=st.role_id
                                 WHERE st.id=? AND st.vendor_id=? LIMIT 1');
        $s->execute([$staffId, $vendorId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw UthengaTieErrors::validation(['id' => 'Staff member not found.']);
        $row['role_permissions'] = json_decode((string) $row['role_permissions'], true) ?: [];
        return $row;
    }

    private function userRow(string $userId): array
    {
        $s = $this->db->prepare('SELECT id, name, email, phone, avatar, role, account_status,
                                        joined_date, last_login_at, two_factor_enabled, is_approved
                                 FROM users WHERE id=? LIMIT 1');
        $s->execute([$userId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw UthengaTieErrors::validation(['user_id' => 'User not found.']);
        return $row;
    }

    private function staffAssignments(string $vendorId, string $staffId): array
    {
        $s = $this->db->prepare('SELECT a.id, a.event_id, e.title AS event_title, e.status AS event_status,
                                        a.role_id, r.name AS role_name, a.status, a.access_start_at, a.access_end_at
                                 FROM tie_staff_assignments a
                                 JOIN tie_events_events e ON e.id = a.event_id COLLATE utf8mb4_general_ci
                                 JOIN tie_staff_roles r ON r.id=a.role_id
                                 WHERE a.staff_id=? AND a.vendor_id=? AND a.status<>\'removed\'
                                 ORDER BY e.start_date ASC, e.title ASC');
        $s->execute([$staffId, $vendorId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function expireSweep(string $vendorId, ?string $staffId = null, ?string $eventId = null): void
    {
        $sql = 'UPDATE tie_staff_assignments SET status=\'expired\', updated_at=UTC_TIMESTAMP()
                WHERE vendor_id=? AND status<>\'expired\' AND status<>\'removed\'
                      AND access_end_at IS NOT NULL AND access_end_at < UTC_TIMESTAMP()';
        $p = [$vendorId];
        if ($staffId) { $sql .= ' AND staff_id=?'; $p[] = $staffId; }
        if ($eventId) { $sql .= ' AND event_id=?'; $p[] = $eventId; }
        $this->db->prepare($sql)->execute($p);
    }

    private function effectiveStatus(array $staff, array $assignments): string
    {
        if ($staff['status'] !== 'active' && $staff['status'] !== 'pending') return (string) $staff['status'];
        if ($staff['status'] === 'pending') return 'pending';
        $live = $assignments;
        if (!$live) return 'active';
        foreach ($live as $a) {
            if ($a['status'] === 'active' || $a['status'] === 'scheduled') return 'active';
        }
        return 'expired';
    }

    public function staffMembers(string $vendorId, array $f = []): array
    {
        $this->ensureBaselineRoles($vendorId);
        $this->expireSweep($vendorId);
        $where = ['st.vendor_id=?', 'st.status<>?'];
        $p = [$vendorId, 'removed'];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.id LIKE ? OR st.department LIKE ? OR st.position_title LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%");
        }
        $role = (string) ($f['role'] ?? '');
        if ($role !== '') { $where[] = 'st.role_id=?'; $p[] = $role; }
        $status = (string) ($f['status'] ?? '');
        if ($status !== '') {
            $status = strtolower($status);
            if ($status === 'active') {
                $where[] = 'st.status IN (\'active\',\'suspended\',\'expired\') AND st.status<>?';
                $p[] = 'removed';
            } elseif (in_array($status, self::STAFF_STATUSES, true)) {
                $where[] = 'st.status=?';
                $p[] = $status;
            } else {
                throw UthengaTieErrors::validation(['status' => 'Invalid staff status filter.']);
            }
        }
        $event = (string) ($f['event'] ?? '');
        if ($event !== '') {
            $where[] = "EXISTS (SELECT 1 FROM tie_staff_assignments ea WHERE ea.staff_id=st.id AND ea.event_id=? AND ea.status<>'removed')";
            $p[] = $event;
        }
        $access = (string) ($f['access'] ?? '');
        if ($access === 'temporary') {
            $where[] = 'EXISTS (SELECT 1 FROM tie_staff_assignments ea WHERE ea.staff_id=st.id AND ea.status<>\'removed\'
                                 AND (ea.access_end_at IS NOT NULL OR ea.access_start_at IS NOT NULL))';
        }
        $sort = (string) ($f['sort'] ?? 'recent');
        $order = match ($sort) {
            'name' => 'u.name ASC',
            'role' => 'r.name ASC, u.name ASC',
            'joined' => 'st.added_at DESC, u.name ASC',
            default => 'st.last_active_at DESC, st.created_at DESC',
        };

        $sql = 'SELECT st.id AS staff_id, st.status AS raw_status, st.department, st.position_title,
                       st.last_active_at, st.role_id, r.name AS role_name, r.role_key, r.scope_type,
                       u.id AS user_id, u.name, u.email, u.avatar, u.last_login_at,
                       (SELECT COUNT(*) FROM tie_staff_assignments ea
                        WHERE ea.staff_id=st.id AND ea.status<>\'removed\') AS assignment_count,
                       (SELECT MIN(ea.access_end_at) FROM tie_staff_assignments ea
                        WHERE ea.staff_id=st.id AND ea.status<>\'removed\' AND ea.access_end_at IS NOT NULL) AS next_expiry
                FROM tie_staff st
                JOIN users u ON u.id = st.user_id COLLATE utf8mb4_general_ci
                JOIN tie_staff_roles r ON r.id=st.role_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $order . '
                LIMIT ' . min(max((int) ($f['limit'] ?? 50), 1), 200);
        $s = $this->db->prepare($sql);
        $s->execute($p);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $roleMap = [];
        $rs = $this->db->prepare('SELECT id, permissions FROM tie_staff_roles WHERE vendor_id=?');
        $rs->execute([$vendorId]);
        foreach ($rs->fetchAll(PDO::FETCH_ASSOC) ?: [] as $rr) {
            $roleMap[(string) $rr['id']] = json_decode((string) $rr['permissions'], true) ?: [];
        }

        $items = [];
        foreach ($rows as $r) {
            $assignments = $this->staffAssignments($vendorId, $r['staff_id']);
            $staff = ['status' => $r['raw_status']];
            $status = $this->effectiveStatus($staff, $assignments);
            $perms = $roleMap[(string) $r['role_id']] ?? [];
            $accessModules = count(array_filter($perms, fn($lvl) => (self::LEVEL_RANK[(string) $lvl] ?? 0) > 0));
            $items[] = [
                'staff_id' => $r['staff_id'], 'user_id' => $r['user_id'], 'name' => $r['name'],
                'email' => $r['email'], 'avatar' => $r['avatar'],
                'department' => $r['department'], 'position_title' => $r['position_title'],
                'role_id' => $r['role_id'], 'role_name' => $r['role_name'], 'role_key' => $r['role_key'],
                'scope_type' => $r['scope_type'], 'scope_label' => self::SCOPE_LABELS[$r['scope_type']] ?? $r['scope_type'],
                'status' => $status, 'raw_status' => $r['raw_status'],
                'last_active' => $this->relTime($r['last_active_at']),
                'assignment_count' => (int) $r['assignment_count'],
                'next_expiry' => $this->fmtDate($r['next_expiry']),
                'access_modules' => $accessModules,
            ];
        }
        return ['items' => $items, 'total' => count($items)];
    }

    /** Resolved module->level access for a user (role + any event scope). */
    public function resolvedPermissions(string $vendorId, string $userId): array
    {
        $s = $this->db->prepare('SELECT r.permissions FROM tie_staff st
                                 JOIN tie_staff_roles r ON r.id=st.role_id
                                 WHERE st.vendor_id=? AND st.user_id=? AND st.status<>\'removed\'
                                 LIMIT 1');
        $s->execute([$vendorId, $userId]);
        $perms = $s->fetchColumn();
        return $perms ? (json_decode((string) $perms, true) ?: []) : [];
    }

    public function staffDetail(string $vendorId, string $staffId): array
    {
        $this->ensureBaselineRoles($vendorId);
        $this->expireSweep($vendorId, $staffId);
        $st = $this->staffRow($vendorId, $staffId);
        $u = $this->userRow((string) $st['user_id']);
        $assignments = $this->staffAssignments($vendorId, $staffId);
        $perms = is_array($st['role_permissions'] ?? null) ? $st['role_permissions'] : [];

        $ac = $this->db->prepare('SELECT COUNT(*) FROM tie_docs_documents WHERE vendor_id=? AND created_by_id=?');
        $ac->execute([$vendorId, $st['user_id']]);
        $docCreated = (int) $ac->fetchColumn();
        $sh = $this->db->prepare('SELECT COUNT(*) FROM tie_docs_shares s
                                  WHERE s.vendor_id=? AND s.sharee_name=?');
        $sh->execute([$vendorId, $u['email']]);
        $docShared = (int) $sh->fetchColumn();
        $pr = $this->db->prepare('SELECT COUNT(*) FROM tie_docs_documents WHERE vendor_id=? AND created_by_id=? AND status=\'PENDING_REVIEW\'');
        $pr->execute([$vendorId, $st['user_id']]);
        $docPending = (int) $pr->fetchColumn();

        $act = $this->db->prepare('SELECT actor_name, staff_id, event_id, module, action, security, detail, created_at
                                   FROM tie_staff_activity WHERE vendor_id=? AND staff_id=?
                                   ORDER BY created_at DESC LIMIT 12');
        $act->execute([$vendorId, $staffId]);
        $activity = array_map(fn($a) => [
            'actor_name' => $a['actor_name'], 'action' => $a['action'], 'module' => $a['module'],
            'security' => (bool) $a['security'], 'detail' => json_decode((string) $a['detail'], true) ?: [],
            'created' => $this->relTime($a['created_at']),
        ], $act->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $status = $this->effectiveStatus($st, $assignments);

        return [
            'staff_id' => $st['id'], 'user_id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'],
            'phone' => $u['phone'], 'avatar' => $u['avatar'], 'status' => $status,
            'raw_status' => $st['status'], 'joined_date' => $st['joined_date'],
            'last_login_at' => $this->relTime($st['last_login_at']), 'last_active' => $this->relTime($st['last_active_at']),
            'account_status' => $u['account_status'], 'two_factor_enabled' => (bool) $u['two_factor_enabled'],
            'is_approved' => (bool) $u['is_approved'],
            'department' => $st['department'], 'position_title' => $st['position_title'],
            'phone_staff' => $st['phone'], 'notes' => $st['notes'], 'timezone' => $st['timezone'],
            'added_by' => $st['added_by'], 'added_at' => $this->fmtDate($st['added_at']),
            'role_id' => $st['role_id'], 'role_name' => $st['role_name'], 'role_key' => $st['role_key'],
            'scope_type' => $st['scope_type'], 'scope_label' => self::SCOPE_LABELS[$st['scope_type']] ?? $st['scope_type'],
            'permissions' => $perms,
            'assignments' => array_map(fn($a) => [
                'assignment_id' => $a['id'], 'event_id' => $a['event_id'], 'event_title' => $a['event_title'],
                'event_status' => $a['event_status'], 'role_id' => $a['role_id'], 'role_name' => $a['role_name'],
                'status' => $a['status'], 'access_start_at' => $this->fmtDate($a['access_start_at']),
                'access_end_at' => $this->fmtDate($a['access_end_at']),
            ], $assignments),
            'documents' => ['created' => $docCreated, 'shared' => $docShared, 'pending_approvals' => $docPending],
            'activity' => $activity,
        ];
    }

    public function usersPool(string $vendorId, string $q = ''): array
    {
        $where = "u.id NOT IN (SELECT user_id COLLATE utf8mb4_general_ci FROM tie_staff WHERE vendor_id=?) AND u.account_status='active'
                  AND u.role IN ('Vendor','Event Organizer','Hotel/Lodge Manager','Tour Operator','Transport Provider','Customer')";
        $p = [$vendorId];
        if ($q !== '') {
            $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            array_push($p, "%$q%", "%$q%");
        }
        $s = $this->db->prepare("SELECT u.id AS user_id, u.name, u.email, u.role
                                 FROM users u WHERE $where ORDER BY u.name ASC LIMIT 25");
        $s->execute($p);
        return array_map(fn($r) => ['user_id' => $r['user_id'], 'name' => $r['name'], 'email' => $r['email'], 'platform_role' => $r['role']],
            $s->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function verifyStaffAccess(array $actor, string $vendorId): void
    {
        if ((string) $actor['id'] === $vendorId) return;
        $perms = $this->resolvedPermissions($vendorId, (string) $actor['id']);
        $level = $perms['staff'] ?? 'none';
        if (self::LEVEL_RANK[$level] < self::LEVEL_RANK['manage']) {
            throw UthengaTieErrors::authorization();
        }
    }

    public function addStaff(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $this->ensureBaselineRoles($vendorId);
        $role = $this->roleById($vendorId, (string) ($input['role_id'] ?? ''), 'role_id');
        $userId = (string) ($input['user_id'] ?? '');
        if ($userId === '') throw UthengaTieErrors::validation(['user_id' => 'User is required.']);
        $user = $this->userRow($userId);
        $check = $this->db->prepare('SELECT COUNT(*) FROM tie_staff WHERE vendor_id=? AND user_id=?');
        $check->execute([$vendorId, $userId]);
        if ((int) $check->fetchColumn() > 0) {
            throw UthengaTieErrors::validation(['user_id' => 'This user is already a staff member.']);
        }
        $staffId = $this->uuid();
        $status = 'active';
        $this->db->prepare('INSERT INTO tie_staff (id, vendor_id, user_id, role_id, status, department, position_title, phone, added_by, added_at, timezone, notes)
                            VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),?,?)')
            ->execute([
                $staffId, $vendorId, $userId, $role['id'], $status,
                trim((string) ($input['department'] ?? '')), trim((string) ($input['position_title'] ?? '')),
                trim((string) ($input['phone'] ?? '')), (string) ($actor['name'] ?? ''),
                trim((string) ($input['timezone'] ?? '')), trim((string) ($input['notes'] ?? '')),
            ]);
        $this->applyScopedAssignments($actor, $vendorId, $staffId, $input, $role);
        $this->audit($vendorId, $actor, 'staff_added', $staffId, null, 'staff', true,
            ['user_id' => $userId, 'name' => $user['name'], 'email' => $user['email'], 'role' => $role['name']]);
        return ['staff_id' => $staffId, 'status' => $status];
    }

    public function updateProfile(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $st = $this->staffRow($vendorId, (string) ($input['staff_id'] ?? ''));
        $this->db->prepare('UPDATE tie_staff SET department=?, position_title=?, phone=?, timezone=?, notes=?, updated_at=UTC_TIMESTAMP()
                            WHERE id=? AND vendor_id=?')
            ->execute([
                trim((string) ($input['department'] ?? '')), trim((string) ($input['position_title'] ?? '')),
                trim((string) ($input['phone'] ?? '')), trim((string) ($input['timezone'] ?? '')),
                trim((string) ($input['notes'] ?? '')), $st['id'], $vendorId,
            ]);
        $this->audit($vendorId, $actor, 'profile_updated', $st['id'], null, 'staff', false,
            ['fields' => array_keys(array_filter([
                'department' => isset($input['department']), 'position_title' => isset($input['position_title']),
                'phone' => isset($input['phone']), 'timezone' => isset($input['timezone']), 'notes' => isset($input['notes']),
            ]))]);
        return ['staff_id' => $st['id'], 'updated' => true];
    }

    private function sanitizeStaffStatus(string $status): string
    {
        $status = strtolower($status);
        if (!in_array($status, ['active', 'suspended', 'removed'], true)) {
            throw UthengaTieErrors::validation(['status' => 'Invalid staff status.']);
        }
        return $status;
    }

    public function setStatus(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $st = $this->staffRow($vendorId, (string) ($input['staff_id'] ?? ''));
        $to = $this->sanitizeStaffStatus((string) ($input['status'] ?? ''));
        if ((string) $st['user_id'] === (string) $actor['id'] && $to !== 'active') {
            throw UthengaTieErrors::validation(['status' => 'You cannot suspend or remove your own account.']);
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        $prev = (string) $st['status'];
        $this->db->prepare('UPDATE tie_staff SET status=?, removed_at=IF(?=\'removed\', UTC_TIMESTAMP(), NULL),
                            updated_at=UTC_TIMESTAMP() WHERE id=? AND vendor_id=?')
            ->execute([$to, $to, $st['id'], $vendorId]);
        if ($to === 'removed') {
            $this->db->prepare("UPDATE tie_staff_assignments SET status='removed', updated_at=UTC_TIMESTAMP()
                                WHERE staff_id=? AND vendor_id=? AND status<>'removed'")->execute([$st['id'], $vendorId]);
        }
        $action = match ($to) { 'suspended' => 'suspended', 'removed' => 'removed', default => 'reactivated' };
        $this->audit($vendorId, $actor, $action, $st['id'], null, 'staff', true,
            ['name' => $st['name'], 'previous_status' => $prev, 'new_status' => $to, 'reason' => $reason]);
        return ['staff_id' => $st['id'], 'status' => $to, 'previous_status' => $prev];
    }

    public function changeRole(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $st = $this->staffRow($vendorId, (string) ($input['staff_id'] ?? ''));
        $role = $this->roleById($vendorId, (string) ($input['role_id'] ?? ''), 'role_id');
        $prevRole = $st['role_name'];
        $this->db->prepare('UPDATE tie_staff SET role_id=?, updated_at=UTC_TIMESTAMP() WHERE id=? AND vendor_id=?')
            ->execute([$role['id'], $st['id'], $vendorId]);
        $this->audit($vendorId, $actor, 'role_changed', $st['id'], null, 'staff', true, [
            'staff_name' => $st['name'], 'previous_role' => $prevRole, 'new_role' => $role['name'],
            'previous_role_id' => $st['role_id'], 'new_role_id' => $role['id'],
            'changed_by' => (string) ($actor['name'] ?? ''),
        ]);
        return ['staff_id' => $st['id'], 'previous_role' => $prevRole, 'new_role' => $role['name']];
    }

    /* ── invitations ────────────────────────────────────────────────── */

    public function invitations(string $vendorId, string $status = ''): array
    {
        $this->expireSweep($vendorId);
        $this->db->prepare("UPDATE tie_staff_invitations SET status='expired'
                            WHERE vendor_id=? AND status='pending' AND expires_at < UTC_TIMESTAMP()")
            ->execute([$vendorId]);
        $where = 'i.vendor_id=?';
        $p = [$vendorId];
        if ($status !== '' && in_array($status, self::INVITE_STATUSES, true)) {
            $where .= ' AND i.status=?';
            $p[] = $status;
        }
        $s = $this->db->prepare("SELECT i.*, r.name AS role_name, r.scope_type
                                 FROM tie_staff_invitations i JOIN tie_staff_roles r ON r.id=i.role_id
                                 WHERE $where ORDER BY i.sent_at DESC");
        $s->execute($p);
        return array_map(fn($i) => [
            'id' => $i['id'], 'email' => $i['email'], 'first_name' => $i['first_name'], 'last_name' => $i['last_name'],
            'name' => trim((string) $i['first_name'] . ' ' . (string) $i['last_name']) ?: $i['email'],
            'role_id' => $i['role_id'], 'role_name' => $i['role_name'], 'scope_type' => $i['scope_type'],
            'scope_label' => self::SCOPE_LABELS[$i['scope_type']] ?? $i['scope_type'],
            'event_ids' => json_decode((string) $i['event_ids'], true) ?: [],
            'event_titles' => array_values(array_filter(array_map(
                fn($eid) => $this->eventTitle((string) $eid, $vendorId), json_decode((string) $i['event_ids'], true) ?: []
            ))),
            'status' => $i['status'], 'token' => $i['token'], 'sent_at' => $this->fmtDate($i['sent_at']),
            'expires_at' => $this->fmtDate($i['expires_at']), 'accepted_at' => $this->fmtDate($i['accepted_at']),
            'resend_count' => (int) $i['resend_count'],
        ], $s->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function invitationById(string $vendorId, string $inviteId): array
    {
        $s = $this->db->prepare('SELECT * FROM tie_staff_invitations WHERE id=? AND vendor_id=? LIMIT 1');
        $s->execute([$inviteId, $vendorId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($r)) throw UthengaTieErrors::validation(['invitation_id' => 'Invitation not found.']);
        return $r;
    }

    public function invite(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $this->ensureBaselineRoles($vendorId);
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw UthengaTieErrors::validation(['email' => 'A valid email is required.']);
        }
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        if ($firstName === '' || $lastName === '') {
            throw UthengaTieErrors::validation(['name' => 'First and last name are required.']);
        }
        $role = $this->roleById($vendorId, (string) ($input['role_id'] ?? ''), 'role_id');
        $scopeType = in_array((string) ($input['scope_type'] ?? 'organization'), ['organization', 'events'], true)
            ? (string) $input['scope_type'] : 'organization';
        $eventIds = $this->validatedEventIds($vendorId, $input['event_ids'] ?? [], $scopeType);

        $existingUser = $this->userIdByEmail($email);
        if ($existingUser) {
            $s = $this->db->prepare('SELECT COUNT(*) FROM tie_staff WHERE vendor_id=? AND user_id=?');
            $s->execute([$vendorId, $existingUser]);
            if ((int) $s->fetchColumn() > 0) {
                throw UthengaTieErrors::validation(['email' => 'This person is already staff for this organization.']);
            }
        }
        $dup = $this->db->prepare("SELECT COUNT(*) FROM tie_staff_invitations WHERE vendor_id=? AND email=? AND status IN ('pending','accepted')");
        $dup->execute([$vendorId, $email]);
        if ((int) $dup->fetchColumn() > 0) {
            throw UthengaTieErrors::validation(['email' => 'An invitation for this email already exists.']);
        }

        $id = $this->uuid();
        $token = $this->newToken();
        $expiry = gmdate('Y-m-d H:i:s', time() + self::INVITE_EXPIRY_DAYS * 86400);
        $this->db->prepare('INSERT INTO tie_staff_invitations (id, vendor_id, email, first_name, last_name, role_id, scope_type, event_ids, token, status, sent_at, expires_at, created_by)
                            VALUES (?,?,?,?,?,?,?,?,?,\'pending\',UTC_TIMESTAMP(),?,?)')
            ->execute([$id, $vendorId, $email, $firstName, $lastName, $role['id'], $scopeType,
                json_encode($eventIds), $token, $expiry, (string) ($actor['name'] ?? '')]);
        $this->audit($vendorId, $actor, 'invited', null, null, 'staff', true, [
            'invitation_id' => $id, 'email' => $email, 'name' => trim("$firstName $lastName"),
            'role' => $role['name'], 'scope_type' => $scopeType, 'event_ids' => $eventIds,
        ]);
        return [
            'invitation_id' => $id, 'email' => $email, 'status' => 'pending',
            'expires_at' => $expiry, 'invite_url' => BASE_URL . 'app/account/invite.php?token=' . $token,
        ];
    }

    private function validatedEventIds(string $vendorId, mixed $eventIds, string $scopeType): array
    {
        if ($scopeType === 'organization') return [];
        $ids = is_array($eventIds) ? array_values(array_unique(array_filter(array_map('strval', $eventIds)))) : [];
        if (!$ids) throw UthengaTieErrors::validation(['event_ids' => 'Select at least one event for scoped access.']);
        foreach ($ids as $id) {
            if (!$this->eventExists($id, $vendorId)) {
                throw UthengaTieErrors::validation(['event_ids' => 'Unknown event: ' . $id]);
            }
        }
        return $ids;
    }

    public function invitationResend(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $inv = $this->invitationById($vendorId, (string) ($input['invitation_id'] ?? ''));
        if ($inv['status'] !== 'pending') {
            throw UthengaTieErrors::validation(['invitation_id' => 'Only pending invitations can be resent.']);
        }
        $token = $this->newToken();
        $expiry = gmdate('Y-m-d H:i:s', time() + self::INVITE_EXPIRY_DAYS * 86400);
        $this->db->prepare('UPDATE tie_staff_invitations SET token=?, expires_at=?, resend_count=resend_count+1, sent_at=UTC_TIMESTAMP()
                            WHERE id=? AND vendor_id=?')
            ->execute([$token, $expiry, $inv['id'], $vendorId]);
        $this->audit($vendorId, $actor, 'invitation_resent', null, null, 'staff', true,
            ['invitation_id' => $inv['id'], 'email' => $inv['email']]);
        return ['invitation_id' => $inv['id'], 'expires_at' => $expiry, 'invite_url' => BASE_URL . 'app/account/invite.php?token=' . $token];
    }

    public function invitationRevoke(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $inv = $this->invitationById($vendorId, (string) ($input['invitation_id'] ?? ''));
        if ($inv['status'] !== 'pending') {
            throw UthengaTieErrors::validation(['invitation_id' => 'Only pending invitations can be revoked.']);
        }
        $this->db->prepare("UPDATE tie_staff_invitations SET status='revoked' WHERE id=? AND vendor_id=?")
            ->execute([$inv['id'], $vendorId]);
        $this->audit($vendorId, $actor, 'invitation_revoked', null, null, 'staff', true,
            ['invitation_id' => $inv['id'], 'email' => $inv['email']]);
        return ['invitation_id' => $inv['id'], 'status' => 'revoked'];
    }

    /** Single-use, expiring, email-tied accept. The caller must be the
     *  invited user themselves (their authenticated identity matches the
     *  invitation email), so a leaked link cannot be redeemed by others. */
    public function invitationAccept(array $actor, array $input): array
    {
        $vendorId = (string) ($input['vendor_id'] ?? '');
        if ($vendorId === '') throw UthengaTieErrors::validation(['vendor_id' => 'Organization is required.']);
        $token = (string) ($input['token'] ?? '');
        $s = $this->db->prepare('SELECT * FROM tie_staff_invitations WHERE vendor_id=? AND token=? LIMIT 1');
        $s->execute([$vendorId, $token]);
        $inv = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($inv)) throw UthengaTieErrors::validation(['token' => 'Invitation link is invalid or already used.']);
        if ($inv['status'] === 'accepted') throw UthengaTieErrors::validation(['token' => 'Invitation already accepted.']);
        if ($inv['status'] === 'revoked') throw UthengaTieErrors::validation(['token' => 'Invitation was revoked.']);
        if (strtotime((string) $inv['expires_at'] . ' UTC') < time()) {
            $this->db->prepare("UPDATE tie_staff_invitations SET status='expired' WHERE id=?")->execute([$inv['id']]);
            throw UthengaTieErrors::validation(['token' => 'Invitation has expired.']);
        }
        $email = strtolower((string) $inv['email']);
        $actorEmail = strtolower((string) ($actor['email'] ?? ''));
        if ($actorEmail === '' || $actorEmail !== $email) {
            throw UthengaTieErrors::authorization();
        }
        $userId = $this->userIdByEmail($email);
        if (!$userId) throw UthengaTieErrors::validation(['email' => 'Account not found for ' . $email . '.']);
        $st = $this->db->prepare('SELECT id FROM tie_staff WHERE vendor_id=? AND user_id=? LIMIT 1');
        $st->execute([$inv['vendor_id'], $userId]);
        $staffId = (string) ($st->fetchColumn() ?: '');
        if ($staffId === '') {
            $staffId = $this->uuid();
            $this->db->prepare('INSERT INTO tie_staff (id, vendor_id, user_id, role_id, status, added_by, added_at)
                                VALUES (?,?,?,?,?,\'acceptance\',UTC_TIMESTAMP())')
                ->execute([$staffId, $inv['vendor_id'], $userId, $inv['role_id'], 'active']);
        } else {
            $this->db->prepare('UPDATE tie_staff SET status=\'active\', role_id=? WHERE id=? AND vendor_id=?')
                ->execute([$inv['role_id'], $staffId, $inv['vendor_id']]);
        }
        $eventIds = json_decode((string) $inv['event_ids'], true) ?: [];
        foreach ($eventIds as $eventId) {
            $ok = $this->db->prepare('SELECT COUNT(*) FROM tie_staff_assignments WHERE staff_id=? AND event_id=?');
            $ok->execute([$staffId, $eventId]);
            if ((int) $ok->fetchColumn() === 0) {
                $this->db->prepare('INSERT INTO tie_staff_assignments (id, vendor_id, staff_id, event_id, role_id, status, assigned_by)
                                    VALUES (?,?,?,?,?,\'active\',\'invitation\')')
                    ->execute([$this->uuid(), $inv['vendor_id'], $staffId, $eventId, $inv['role_id']]);
            }
        }
        $this->db->prepare("UPDATE tie_staff_invitations SET status='accepted', accepted_at=UTC_TIMESTAMP(), accepted_user_id=?
                            WHERE id=?")
            ->execute([$userId, $inv['id']]);
        $this->audit($inv['vendor_id'], $actor, 'invitation_accepted', $staffId, null, 'staff', true,
            ['invitation_id' => $inv['id'], 'email' => $email, 'role_id' => $inv['role_id']]);
        return ['staff_id' => $staffId, 'status' => 'active', 'role_id' => $inv['role_id']];
    }

    /* ── assignments ────────────────────────────────────────────────── */

    public function assignmentsByEvent(string $vendorId): array
    {
        $this->expireSweep($vendorId);
        $s = $this->db->prepare('SELECT e.id AS event_id, e.title AS event_title,
                                        e.status AS event_status, e.start_date,
                                        (SELECT COUNT(*) FROM tie_staff_assignments a
                                         WHERE a.event_id COLLATE utf8mb4_general_ci=e.id AND a.status<>\'removed\') AS staff_count
                                 FROM tie_events_events e
                                 WHERE e.vendor_id=? AND e.listing_id IS NOT NULL
                                       AND EXISTS (SELECT 1 FROM tie_staff_assignments a
                                                   WHERE a.event_id COLLATE utf8mb4_general_ci=e.id AND a.status<>\'removed\')
                                 ORDER BY e.start_date ASC, e.title ASC');
        $s->execute([$vendorId]);
        $byEvent = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $e) {
            $roles = [];
            $rs = $this->db->prepare('SELECT r.name AS role_name, r.id AS role_id, COUNT(*) AS cnt
                                      FROM tie_staff_assignments a
                                      JOIN tie_staff_roles r ON r.id=a.role_id
                                      WHERE a.event_id=? AND a.status<>\'removed\'
                                      GROUP BY r.id, r.name ORDER BY cnt DESC');
            $rs->execute([$e['event_id']]);
foreach ($rs->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $roles[] = ['role_id' => $r['role_id'], 'role_name' => $r['role_name'], 'count' => (int) $r['cnt']];
        }
            $team = [];
            $ts = $this->db->prepare('SELECT st.id AS staff_id, u.name, u.email, r.name AS role_name,
                                             a.status AS assignment_status, a.access_start_at, a.access_end_at
                                      FROM tie_staff_assignments a
                                      JOIN tie_staff st ON st.id=a.staff_id
                                      JOIN users u ON u.id = st.user_id COLLATE utf8mb4_general_ci
                                      JOIN tie_staff_roles r ON r.id=a.role_id
                                      WHERE a.event_id=? AND a.status<>\'removed\'
                                      ORDER BY u.name ASC');
            $ts->execute([$e['event_id']]);
            foreach ($ts->fetchAll(PDO::FETCH_ASSOC) ?: [] as $m) {
                $team[] = [
                    'staff_id' => $m['staff_id'], 'name' => $m['name'], 'email' => $m['email'],
                    'role_name' => $m['role_name'], 'assignment_status' => $m['assignment_status'],
                    'access_start_at' => $this->fmtDate($m['access_start_at']), 'access_end_at' => $this->fmtDate($m['access_end_at']),
                ];
            }
            $byEvent[] = [
                'event_id' => $e['event_id'], 'event_title' => $e['event_title'],
                'event_status' => $e['event_status'], 'start_date' => $e['start_date'],
                'staff_count' => (int) $e['staff_count'], 'roles' => $roles, 'team' => $team,
            ];
        }
        return $byEvent;
    }

    public function assignmentMatrix(string $vendorId): array
    {
        $events = $this->assignmentsByEvent($vendorId);
        $s = $this->db->prepare("SELECT st.id AS staff_id, u.name,
                                        (SELECT COUNT(*) FROM tie_staff_assignments a
                                         WHERE a.staff_id=st.id AND a.status<>'removed') AS assignment_count
                                 FROM tie_staff st JOIN users u ON u.id = st.user_id COLLATE utf8mb4_general_ci
                                 WHERE st.vendor_id=? AND st.status<>'removed' AND st.status<>'pending'
                                 ORDER BY u.name ASC");
        $s->execute([$vendorId]);
        $staff = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $cells = [];
        foreach ($staff as $m) {
            $as = $this->db->prepare("SELECT event_id, status FROM tie_staff_assignments
                                      WHERE staff_id=? AND vendor_id=? AND status<>'removed'");
            $as->execute([$m['staff_id'], $vendorId]);
            foreach ($as->fetchAll(PDO::FETCH_ASSOC) ?: [] as $a) {
                $cells[$m['staff_id']][$a['event_id']] = $a['status'];
            }
        }
        return [
            'events' => array_map(fn($e) => ['event_id' => $e['event_id'], 'event_title' => $e['event_title'], 'staff_count' => $e['staff_count']], $events),
            'staff' => array_map(fn($m) => ['staff_id' => $m['staff_id'], 'name' => $m['name'], 'assignment_count' => (int) $m['assignment_count']], $staff),
            'matrix' => array_map(fn($m) => [
                'staff_id' => $m['staff_id'],
                'assignments' => $cells[$m['staff_id']] ?? [],
            ], $staff),
        ];
    }

    private function applyScopedAssignments(array $actor, string $vendorId, string $staffId, array $input, array $role, string $source = 'admin'): int
    {
        $scopeType = (string) ($input['scope_type'] ?? '') === 'organization' ? 'organization' : 'events';
        $eventIds = $this->validatedEventIds($vendorId, $input['event_ids'] ?? [], $scopeType);
        if (!$eventIds) return 0;
        $start = $this->parseDate($input['access_start_at'] ?? '');
        $end = $this->parseDate($input['access_end_at'] ?? '');
        if ($end && $start && $end < $start) {
            throw UthengaTieErrors::validation(['access_end_at' => 'Access end must be after access start.']);
        }
        $now = time();
        if (!empty($input['replace_scope'])) {
            $this->db->prepare("UPDATE tie_staff_assignments SET status='removed', updated_at=UTC_TIMESTAMP()
                                WHERE staff_id=? AND vendor_id=? AND status<>'removed'
                                      AND event_id COLLATE utf8mb4_general_ci NOT IN ("
                                      . implode(',', array_map(fn($eid) => $this->db->quote((string) $eid), $eventIds)) . ')')
                ->execute([$staffId, $vendorId]);
        }
        foreach ($eventIds as $eventId) {
            $status = ($start && strtotime($start . ' UTC') > $now) ? 'scheduled' : 'active';
            if ($end && strtotime($end . ' UTC') <= $now) $status = 'expired';
            $check = $this->db->prepare('SELECT id FROM tie_staff_assignments WHERE staff_id=? AND event_id=?');
            $check->execute([$staffId, $eventId]);
            $existing = $check->fetchColumn();
            if ($existing !== false) {
                $this->db->prepare('UPDATE tie_staff_assignments
                                    SET role_id=?, status=?, access_start_at=?, access_end_at=?, assigned_by=?, updated_at=UTC_TIMESTAMP()
                                    WHERE id=?')
                    ->execute([$role['id'], $status, $start, $end, (string) ($actor['name'] ?? ''), (string) $existing]);
            } else {
                $this->db->prepare('INSERT INTO tie_staff_assignments (id, vendor_id, staff_id, event_id, role_id, status, access_start_at, access_end_at, assigned_by)
                                    VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$this->uuid(), $vendorId, $staffId, $eventId, $role['id'], $status, $start, $end, (string) ($actor['name'] ?? '')]);
            }
        }
        $this->audit($vendorId, $actor, 'assigned', $staffId, null, 'staff', false, [
            'event_ids' => $eventIds, 'role' => $role['name'],
            'access_start_at' => $start, 'access_end_at' => $end, 'source' => $source,
        ]);
        return count($eventIds);
    }

    public function assign(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $st = $this->staffRow($vendorId, (string) ($input['staff_id'] ?? ''));
        $role = $this->roleById($vendorId, (string) ($input['role_id'] ?? '') ?: (string) $st['role_id'], 'role_id');
        $count = $this->applyScopedAssignments($actor, $vendorId, $st['id'], $input, $role);
        return ['staff_id' => $st['id'], 'assigned_events' => $count];
    }

    public function assignmentUpdate(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $assignmentId = (string) ($input['assignment_id'] ?? '');
        $s = $this->db->prepare('SELECT * FROM tie_staff_assignments WHERE id=? AND vendor_id=? LIMIT 1');
        $s->execute([$assignmentId, $vendorId]);
        $a = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($a)) throw UthengaTieErrors::validation(['assignment_id' => 'Assignment not found.']);

        $sets = [];
        $p = [];
        if (isset($input['role_id']) && (string) $input['role_id'] !== '') {
            $role = $this->roleById($vendorId, (string) $input['role_id'], 'role_id');
            $sets[] = 'role_id=?';
            $p[] = $role['id'];
        }
        foreach (['access_start_at' => 'access_start_at', 'access_end_at' => 'access_end_at'] as $k => $col) {
            if (array_key_exists($k, $input)) {
                $v = $this->parseDate((string) $input[$k]);
                $sets[] = "$col=?";
                $p[] = $v;
            }
        }
        if (isset($input['status'])) {
            $st = strtolower((string) $input['status']);
            if (!in_array($st, ['active', 'scheduled', 'expired', 'removed'], true)) {
                throw UthengaTieErrors::validation(['status' => 'Invalid assignment status.']);
            }
            $sets[] = 'status=?';
            $p[] = $st;
        }
        $sets[] = 'updated_at=UTC_TIMESTAMP()';
        $p[] = $assignmentId;
        if (count($sets) > 1) {
            $this->db->prepare('UPDATE tie_staff_assignments SET ' . implode(', ', $sets) . ' WHERE id=?')->execute($p);
        }
        $this->audit($vendorId, $actor, 'assignment_changed', $a['staff_id'], $a['event_id'], 'staff', false, [
            'assignment_id' => $assignmentId, 'event_id' => $a['event_id'],
            'changes' => array_keys(array_filter([
                'role_id' => isset($input['role_id']), 'access_start_at' => array_key_exists('access_start_at', $input),
                'access_end_at' => array_key_exists('access_end_at', $input), 'status' => isset($input['status']),
            ])),
        ]);
        return ['assignment_id' => $assignmentId, 'updated' => true];
    }

    public function assignmentRemove(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $assignmentId = (string) ($input['assignment_id'] ?? '');
        $s = $this->db->prepare('SELECT staff_id, event_id FROM tie_staff_assignments WHERE id=? AND vendor_id=? LIMIT 1');
        $s->execute([$assignmentId, $vendorId]);
        $a = $s->fetch(PDO::FETCH_ASSOC);
        if (!is_array($a)) throw UthengaTieErrors::validation(['assignment_id' => 'Assignment not found.']);
        $this->db->prepare("UPDATE tie_staff_assignments SET status='removed', updated_at=UTC_TIMESTAMP() WHERE id=?")
            ->execute([$assignmentId]);
        $this->audit($vendorId, $actor, 'assignment_removed', $a['staff_id'], $a['event_id'], 'staff', true, [
            'assignment_id' => $assignmentId, 'event_id' => $a['event_id'],
        ]);
        return ['assignment_id' => $assignmentId, 'removed' => true];
    }

    public function bulk(array $actor, array $input): array
    {
        $this->verifyStaffAccess($actor, $actor['id']);
        $vendorId = $actor['id'];
        $action = strtolower((string) ($input['action'] ?? ''));
        $staffIds = is_array($input['staff_ids'] ?? null) ? array_values(array_filter(array_map('strval', $input['staff_ids']))) : [];
        if (!$staffIds) throw UthengaTieErrors::validation(['staff_ids' => 'Select at least one staff member.']);
        $results = [];
        foreach ($staffIds as $staffId) {
            $per = ['i' => $staffId] + $input;
            try {
                $out = match ($action) {
                    'suspend' => $this->setStatus($actor, ['staff_id' => $staffId, 'status' => 'suspended', 'reason' => (string) ($input['reason'] ?? '')]),
                    'activate' => $this->setStatus($actor, ['staff_id' => $staffId, 'status' => 'active', 'reason' => (string) ($input['reason'] ?? '')]),
                    'change_role' => $this->changeRole($actor, ['staff_id' => $staffId, 'role_id' => (string) ($input['role_id'] ?? '')]),
                    'assign_event' => $this->assign($actor, ['staff_id' => $staffId, 'role_id' => (string) ($input['role_id'] ?? ''), 'event_ids' => $input['event_ids'] ?? [], 'access_start_at' => (string) ($input['access_start_at'] ?? ''), 'access_end_at' => (string) ($input['access_end_at'] ?? '')]),
                    'remove' => $this->setStatus($actor, ['staff_id' => $staffId, 'status' => 'removed', 'reason' => (string) ($input['reason'] ?? '')]),
                    default => throw UthengaTieErrors::validation(['action' => 'Unknown bulk action.']),
                };
                $results[] = ['staff_id' => $staffId, 'ok' => true, 'result' => $out];
            } catch (Throwable $e) {
                $results[] = ['staff_id' => $staffId, 'ok' => false, 'error' => $e->getMessage()];
            }
        }
        return ['results' => $results, 'succeeded' => count(array_filter($results, fn($r) => $r['ok']))];
    }

    /* ── overview + activity ────────────────────────────────────────── */

    public function overview(string $vendorId): array
    {
        $this->ensureBaselineRoles($vendorId);
        $this->expireSweep($vendorId);
        $c = fn(string $sql, array $p = []) => (int) (function () use ($sql, $p) {
            $s = $this->db->prepare($sql);
            $s->execute($p);
            return $s->fetchColumn() ?: 0;
        })();

        $active = $c("SELECT COUNT(*) FROM tie_staff st WHERE st.vendor_id=? AND st.status='active'", [$vendorId]);
        $pending = $c("SELECT COUNT(*) FROM tie_staff st WHERE st.vendor_id=? AND st.status='pending'", [$vendorId]);
        $suspended = $c("SELECT COUNT(*) FROM tie_staff st WHERE st.vendor_id=? AND st.status='suspended'", [$vendorId]);
        $removed = $c("SELECT COUNT(*) FROM tie_staff st WHERE st.vendor_id=? AND st.status='removed'", [$vendorId]);
        $invitesPending = $c("SELECT COUNT(*) FROM tie_staff_invitations i WHERE i.vendor_id=? AND i.status='pending' AND i.expires_at >= UTC_TIMESTAMP()", [$vendorId]);
        $invitesTotal = $c("SELECT COUNT(*) FROM tie_staff_invitations i WHERE i.vendor_id=? AND i.status<>'revoked'", [$vendorId]);
        $assignments = $c("SELECT COUNT(*) FROM tie_staff_assignments a WHERE a.vendor_id=? AND a.status<>'removed' AND a.status<>'expired'", [$vendorId]);
        $expiredNow = $c("SELECT COUNT(*) FROM tie_staff_assignments a WHERE a.vendor_id=? AND a.status='expired'", [$vendorId]);
        $roles = $c('SELECT COUNT(*) FROM tie_staff_roles r WHERE r.vendor_id=?', [$vendorId]);

        $recent = [];
        $s = $this->db->prepare('SELECT actor_name, staff_id, module, action, security, created_at
                                 FROM tie_staff_activity WHERE vendor_id=?
                                 ORDER BY created_at DESC LIMIT 6');
        $s->execute([$vendorId]);
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) ?: [] as $a) {
            $recent[] = [
                'actor_name' => $a['actor_name'], 'action' => $a['action'], 'module' => $a['module'],
                'security' => (bool) $a['security'], 'created' => $this->relTime($a['created_at']),
            ];
        }

        return [
            'active' => $active, 'pending' => $pending, 'invites_pending' => $invitesPending,
            'invites_total' => $invitesTotal, 'assignments' => $assignments, 'expired_now' => $expiredNow,
            'suspended' => $suspended, 'removed' => $removed, 'roles' => $roles,
            'recent_activity' => $recent,
        ];
    }

    private function parseDate(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') return null;
        $ts = strtotime($v . (preg_match('/[Zz]|[+-]\d{2}:?\d{2}$/', $v) ? '' : ' UTC'));
        return $ts === false ? null : gmdate('Y-m-d H:i:s', $ts);
    }

    public function activity(string $vendorId, array $f = []): array
    {
        $where = ['a.vendor_id=?'];
        $p = [$vendorId];
        $scope = strtolower((string) ($f['scope'] ?? 'all'));
        if ($scope === 'security') {
            $where[] = 'a.security=1';
        } elseif ($scope !== 'all') {
            throw UthengaTieErrors::validation(['scope' => 'Invalid scope.']);
        }
        if (!empty($f['staff_id'])) { $where[] = 'a.staff_id=?'; $p[] = (string) $f['staff_id']; }
        if (!empty($f['event_id'])) { $where[] = 'a.event_id=?'; $p[] = (string) $f['event_id']; }
        if (!empty($f['module'])) { $where[] = 'a.module=?'; $p[] = (string) $f['module']; }
        if (!empty($f['action'])) { $where[] = 'a.action=?'; $p[] = (string) $f['action']; }
        $limit = min(max((int) ($f['limit'] ?? 40), 1), 150);
        $s = $this->db->prepare('SELECT a.id, a.actor_id, a.actor_name, a.staff_id, a.event_id, a.module,
                                        a.action, a.security, a.detail, a.ip_address, a.created_at
                                 FROM tie_staff_activity a
                                 WHERE ' . implode(' AND ', $where) . '
                                 ORDER BY a.created_at DESC LIMIT ' . $limit);
        $s->execute($p);
        return array_map(fn($a) => [
            'id' => (int) $a['id'], 'actor_name' => $a['actor_name'], 'staff_id' => $a['staff_id'],
            'event_id' => $a['event_id'], 'event_title' => $this->eventTitle((string) $a['event_id'], $vendorId),
            'module' => $a['module'], 'action' => $a['action'], 'security' => (bool) $a['security'],
            'detail' => json_decode((string) $a['detail'], true) ?: [],
            'ip_address' => $a['ip_address'], 'created' => $this->relTime($a['created_at']),
        ], $s->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function audit(string $vendorId, array $actor, string $action, ?string $staffId = null,
                           ?string $eventId = null, ?string $module = null, bool $security = false,
                           array $detail = []): void
    {
        $this->db->prepare('INSERT INTO tie_staff_activity
                            (vendor_id, actor_id, actor_name, staff_id, event_id, module, action, security, detail, ip_address)
                            VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $vendorId, (string) ($actor['id'] ?? ''), (string) ($actor['name'] ?? ''),
                $staffId, $eventId, $module, $action, $security ? 1 : 0,
                $detail ? json_encode($detail) : null,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
            ]);
    }
}