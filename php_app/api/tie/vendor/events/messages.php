<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Messages.php';

$requestId = UthengaTieObservability::requestId();
try {
    [$user, $service, $requestId] = events_v2_context();
    $msgs = new UthengaMessagesService($service->db());

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = strtolower((string) ($_GET['action'] ?? 'overview'));
        $audConfig = [
            'audience' => (string) ($_GET['audience'] ?? 'ALL_CUSTOMERS'),
            'event_id' => (string) ($_GET['event_id'] ?? ''),
            'filters' => [
                'ticket_type_id' => (int) ($_GET['ticket_type_id'] ?? 0),
                'payment_status' => (string) ($_GET['payment_status'] ?? ''),
                'checkin' => (string) ($_GET['checkin'] ?? ''),
            ],
        ];
        $result = match ($action) {
            'overview' => $msgs->inbox($user['id'], [
                'view' => (string) ($_GET['view'] ?? 'all'),
                'q' => (string) ($_GET['q'] ?? ''),
                'event_id' => (string) ($_GET['event_id'] ?? ''),
                'tag' => (string) ($_GET['tag'] ?? ''),
            ]),
            'conversation' => $msgs->conversation($user['id'], (string) ($_GET['id'] ?? '')),
            'assist' => $msgs->assist($user['id'], (string) ($_GET['id'] ?? '')),
            'search' => $msgs->search($user['id'], (string) ($_GET['q'] ?? '')),
            'events' => $msgs->eventsList($user['id']),
            'templates' => $msgs->templates($user['id']),
            'broadcasts' => $msgs->broadcasts($user['id'], (string) ($_GET['kind'] ?? '')),
            'broadcast' => $msgs->broadcastDetail($user['id'], (string) ($_GET['id'] ?? '')),
            'estimate_audience' => $msgs->estimateAudience($user['id'], $audConfig),
            'automations' => $msgs->automations($user['id']),
            'recent' => $msgs->recent($user['id'], (int) ($_GET['limit'] ?? 5)),
            default => throw UthengaTieErrors::validation(['action' => 'Unknown messages action.']),
        };
        events_v2_respond($requestId, 'messages_result', $result);
    }

    $input = events_v2_write('messages_ops', $requestId);
    foreach (['payload', 'audience_config', 'filters'] as $k) {
        if (isset($input[$k]) && is_string($input[$k]) && $input[$k] !== '') {
            $decoded = json_decode($input[$k], true);
            if (is_array($decoded)) $input[$k] = $decoded;
        }
    }
    $action = strtolower((string) ($input['action'] ?? ''));
    $actorName = $user['name'] ?: 'Organizer';
    $result = match ($action) {
        'start' => $msgs->start($user, $input),
        'reply' => $msgs->reply($user, $input),
        'inbound' => $msgs->customerInbound($user, $input),
        'mark_read' => $msgs->markRead($user['id'], (string) ($input['conversation_id'] ?? '')),
        'status' => $msgs->updateStatus($user['id'], $actorName, (string) ($input['conversation_id'] ?? ''), (string) ($input['status'] ?? '')),
        'priority' => $msgs->setPriority($user['id'], $actorName, (string) ($input['conversation_id'] ?? ''), (string) ($input['priority'] ?? '')),
        'assign' => $msgs->assign($user['id'], $actorName, (string) ($input['conversation_id'] ?? ''), (string) ($input['assigned_to'] ?? '')),
        'mute' => $msgs->toggleMute($user['id'], (string) ($input['conversation_id'] ?? '')),
        'tag' => $msgs->addTag($user['id'], (string) ($input['conversation_id'] ?? ''), (string) ($input['tag'] ?? '')),
        'untag' => $msgs->removeTag($user['id'], (string) ($input['conversation_id'] ?? ''), (string) ($input['tag'] ?? '')),
        'note' => $msgs->addNote($user['id'], $actorName, (string) ($input['conversation_id'] ?? ''), (string) ($input['body'] ?? '')),
        'template_save' => $msgs->saveTemplate($user['id'], $input),
        'template_delete' => $msgs->deleteTemplate($user['id'], (string) ($input['id'] ?? '')),
        'broadcast_create' => $msgs->createBroadcast($user, $input),
        'broadcast_delete' => $msgs->deleteBroadcast($user['id'], (string) ($input['id'] ?? '')),
        'automation_save' => $msgs->saveAutomation($user['id'], $input),
        'automation_delete' => $msgs->deleteAutomation($user['id'], (string) ($input['id'] ?? '')),
        default => throw UthengaTieErrors::validation(['action' => 'Unknown messages action.']),
    };
    events_v2_respond($requestId, 'messages_result', $result);
} catch (Throwable $error) {
    UthengaTieApi::handleError($error, $requestId);
}