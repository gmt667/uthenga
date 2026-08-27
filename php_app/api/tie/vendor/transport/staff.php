<?php
/**
 * User Management for the Bus Operations Center — a thin pass-through to
 * UthengaStaffService, the exact same RBAC engine the Events Control Center
 * already uses in production (tie_staff/tie_staff_roles/tie_staff_invitations,
 * organization-scoped, no event dependency). Nothing here is bus-specific;
 * only the module labels the frontend renders are relabeled for display.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Staff.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, , $requestId] = bus_ops_context();
    global $pdo;
    $staff = new UthengaStaffService($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'staff'));
        $result = match ($action) {
            'staff' => $staff->staffMembers($user['id']),
            'roles' => $staff->roles($user['id']),
            'invitations' => $staff->invitations($user['id'], (string) ($_GET['status'] ?? '')),
            'enums' => $staff->enums(),
            'role_detail' => $staff->roleDetail($user['id'], (string) ($_GET['role_id'] ?? '')),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown staff action.']),
        };
        bus_ops_respond($requestId, 'result', $result);
        exit;
    }

    $input = bus_ops_write('bus_ops_staff', $requestId);
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'invite' => $staff->invite($user, $input),
        'set_status' => $staff->setStatus($user, $input),
        'change_role' => $staff->changeRole($user, $input),
        'resend_invitation' => $staff->invitationResend($user, $input),
        'revoke_invitation' => $staff->invitationRevoke($user, $input),
        'save_role' => $staff->saveRole($user, $input),
        'delete_role' => $staff->deleteRole($user, $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown staff action.']),
    };
    bus_ops_respond($requestId, 'result', $result);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
