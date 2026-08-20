<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Documents.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    $docs = new UthengaDocumentsService($service->db());

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'overview'));
        $result = match ($action) {
            'overview' => $docs->overview($user['id']),
            'documents' => $docs->documents($user['id'], [
                'view' => (string) ($_GET['view'] ?? 'all'),
                'q' => (string) ($_GET['q'] ?? ''),
                'category' => (string) ($_GET['category'] ?? ''),
                'event_id' => (string) ($_GET['event_id'] ?? ''),
                'doc_type' => (string) ($_GET['doc_type'] ?? ''),
                'status' => (string) ($_GET['status'] ?? ''),
                'creator' => (string) ($_GET['creator'] ?? ''),
                'tag' => (string) ($_GET['tag'] ?? ''),
                'sort' => (string) ($_GET['sort'] ?? 'updated'),
                'limit' => (int) ($_GET['limit'] ?? 50),
            ]),
            'detail' => $docs->detail($user['id'], (string) ($_GET['id'] ?? '')),
            'file' => $docs->file($user['id'], (string) ($_GET['id'] ?? ''),
                (string) ($_GET['kind'] ?? 'preview'), $user['id']),
            'events' => $docs->eventsList($user['id']),
            'templates' => $docs->templates($user['id'], (bool) ($_GET['active'] ?? 0)),
            'activity' => $docs->activityFeed($user['id'], (int) ($_GET['limit'] ?? 20)),
            'filters' => $docs->filterOptions($user['id']),
            'enums' => $docs->enums(),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown documents action.']),
        };
        events_v2_respond($requestId, 'documents_result', $result);
    }

    $input = events_v2_write('documents_ops', $requestId);
    foreach (['tags', 'files', 'file', 'payload'] as $k) {
        if (isset($input[$k]) && is_string($input[$k]) && $input[$k] !== '') {
            $decoded = json_decode($input[$k], true);
            if (is_array($decoded)) $input[$k] = $decoded;
        }
    }
    if (isset($_FILES['file']) && is_array($_FILES['file']) && !empty($_FILES['file']['tmp_name'])) {
        $input['file'] = [
            'name' => (string) ($_FILES['file']['name'] ?? 'document.bin'),
            'tmp_name' => (string) $_FILES['file']['tmp_name'],
        ];
    }
    $action = strtolower((string) ($input['action'] ?? ''));
    $result = match ($action) {
        'create' => $docs->create($user, $input),
        'upload' => $docs->upload($user, $input),
        'rename' => $docs->rename($user, $input),
        'move' => $docs->move($user, $input),
        'status' => $docs->setStatus($user, $input),
        'archive' => $docs->archive($user, $input),
        'unarchive' => $docs->unarchive($user, $input),
        'delete' => $docs->delete($user, $input),
        'lock' => $docs->lock($user, $input),
        'tags' => $docs->updateTags($user, $input),
        'version_upload' => $docs->versionUpload($user, $input),
        'version_restore' => $docs->versionRestore($user, $input),
        'share' => $docs->share($user, $input),
        'unshare' => $docs->unshare($user, $input),
        'template_save' => $docs->saveTemplate($user, $input),
        'template_delete' => $docs->deleteTemplate($user, $input),
        'generate' => $docs->generate($user, $input),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown documents action.']),
    };
    events_v2_respond($requestId, 'documents_result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}