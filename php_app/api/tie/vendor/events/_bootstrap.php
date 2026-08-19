<?php
require_once __DIR__ . '/../../../../config.php';
require_once __DIR__ . '/../../../../db.php';
require_once __DIR__ . '/../../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Api.php';
require_once __DIR__ . '/../../../../includes/tie/Events.php';
require_once __DIR__ . '/../../../../includes/tie/Venues.php';

function events_v2_context(): array
{
    UthengaTieApi::requireFeature('events_v2');
    $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();
    global $pdo;
    if (!$pdo instanceof PDO) throw UthengaTieErrors::providerUnavailable('database');
    $name = $pdo->prepare('SELECT name FROM users WHERE id=?');
    $name->execute([$user['id']]);
    $user['name'] = (string) ($name->fetchColumn() ?: '');
    return [$user, new UthengaEventsService($pdo), UthengaTieObservability::requestId()];
}

function events_v2_input(): array { return UthengaTieApi::input(); }

function events_v2_event(array $input = []): string { return UthengaEventsContracts::id($input['event_id'] ?? ($_GET['event_id'] ?? ''), 'event_id'); }

function events_v2_write(string $bucket, string $requestId): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit($bucket, 60, 60, $requestId);
    return events_v2_input();
}

function events_v2_respond(string $requestId, string $key, array $value): void
{
    UthengaTieApi::respond(['success' => true, 'schema_version' => 'tie-events-api/v1', 'request_id' => $requestId, $key => $value]);
}
