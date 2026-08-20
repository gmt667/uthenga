<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Staff.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    $staff = new UthengaStaffService($service->db());

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'overview'));
        $result = match ($action) {
            'overview' => $staff->overview($user['id']),
            'staff' => $staff->staffMembers($user['id'], [
                'q' => (string) ($_GET['q'] ?? ''),
                'role' => (string) ($_GET['role'] ?? ''),
                'status' => (string) ($_GET['status'] ?? ''),
                'event' => (string) ($_GET['event'] ?? ''),
                'access' => (string) ($_GET['access'] ?? ''),
                'sort' => (string) ($_GET['sort'] ?? 'recent'),
                'limit' => (int) ($_GET['limit'] ?? 50),
            ]),
            'detail' => $staff->staffDetail($user['id'], (string) ($_GET['id'] ?? '')),
            'roles' => $staff->roles($user['id']),
            'role' => $staff->roleDetail($user['id'], (string) ($_GET['id'] ?? '')),
            'invitations' => $staff->invitations($user['id'], (string) ($_GET['status'] ?? '')),
            'events' => $staff->eventsList($user['id']),
            'users' => $staff->usersPool($user['id'], (string) ($_GET['q'] ?? '')),
            'assignments' => $staff->assignmentsByEvent($user['id']),
            'matrix' => $staff->assignmentMatrix($user['id']),
            'activity' => $staff->activity($user['id'], [
                'scope' => (string) ($_GET['scope'] ?? 'all'),
                'staff_id' => (string) ($_GET['staff_id'] ?? ''),
                'event_id' => (string) ($_GET['event_id'] ?? ''),
                'module' => (string) ($_GET['module'] ?? ''),
                'limit' => (int) ($_GET['limit'] ?? 40),
            ]),
            'permissions' => ['user_id' => $user['id'], 'vendor_id' => $user['id'], 'modules' => $staff->resolvedPermissions($user['id'], $user['id'])],
            'enums' => $staff->enums(),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown staff action.']),
        };
        events_v2_respond($requestId, 'staff_result', $result);
    }

    $input = events_v2_write('staff_ops', $requestId);
    foreach (['event_ids', 'permissions', 'staff_ids'] as $k) {
        if (isset($input[$k]) && is_string($input[$k]) && $input[$k] !== '') {
            $decoded = json_decode($input[$k], true);
            if (is_array($decoded)) $input[$k] = $decoded;
        }
    }
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'invite' => $staff->invite($user, $input),
        'invitation_resend' => $staff->invitationResend($user, $input),
        'invitation_revoke' => $staff->invitationRevoke($user, $input),
        'invitation_accept' => $staff->invitationAccept($user, $input),
        'add' => $staff->addStaff($user, $input),
        'update_profile' => $staff->updateProfile($user, $input),
        'status' => $staff->setStatus($user, $input),
        'role_change' => $staff->changeRole($user, $input),
        'assign' => $staff->assign($user, $input),
        'assignment_update' => $staff->assignmentUpdate($user, $input),
        'assignment_remove' => $staff->assignmentRemove($user, $input),
        'role_save' => $staff->saveRole($user, $input),
        'role_delete' => $staff->deleteRole($user, $input),
        'bulk' => $staff->bulk($user, $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown staff action.']),
    };
    events_v2_respond($requestId, 'staff_result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}