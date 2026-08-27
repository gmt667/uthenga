<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../includes/tie/Api.php';

$requestId = UthengaTieObservability::requestId();
try {
    UthengaTieApi::requireFeature('vendor_profiles'); $user = UthengaTieApi::requireAuthenticatedUser(); $service = (new UthengaTieKernel())->vendorProfiles;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'vendor_profiles' => $service->dashboard($user['id'])]); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'GET or POST is required.']);
    UthengaTieApi::requireCsrf(); $input = UthengaTieApi::input(); $action = strtolower(trim((string) ($input['action'] ?? '')));
    $result = match ($action) {
        'create_draft' => $service->createDraft((string) ($input['profile_type'] ?? ''), $user['id'], isset($input['profile_name']) ? (string) $input['profile_name'] : null),
        'activate_transport' => $service->activateTransport($input, $user['id']),
        'activate_profile' => $service->activate((string) ($input['profile_id'] ?? ''), $user['id']),
        'save_transport_settings' => $service->saveTransportSettings($input, $user['id']),
        'submit_for_review' => $service->submitForReview((string) ($input['profile_id'] ?? ''), $user['id']),
        'pause_profile' => $service->pause((string) ($input['profile_id'] ?? ''), $user['id']),
        'archive_profile' => $service->archive((string) ($input['profile_id'] ?? ''), $user['id']),
        'transport_session_options' => $service->transportSessionOptions($user['id']),
        'review_profile' => in_array($user['role'], ADMIN_ROLES, true) ? $service->review((string) ($input['profile_id'] ?? ''), (string) ($input['decision'] ?? ''), (string) ($input['note'] ?? ''), $user['id']) : throw UthengaTieErrors::authorization(),
        'draft_transport' => (new UthengaTieKernel())->vendorProfileDrafts->transport((string) ($input['message'] ?? '')),
        default => throw UthengaTieErrors::validation(['action' => 'Unsupported vendor profile action.']),
    };
    UthengaTieApi::respond(['success' => true, 'request_id' => $requestId, 'result' => $result]);
} catch (Throwable $error) { UthengaTieApi::handleError($error, $requestId); }
