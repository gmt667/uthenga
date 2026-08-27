<?php
require_once __DIR__ . '/../../../../config.php';
require_once __DIR__ . '/../../../../db.php';
require_once __DIR__ . '/../../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Api.php';

function bus_ops_context(): array
{
    UthengaTieApi::requireFeature('bus_operations');
    $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();
    global $pdo;
    if (!$pdo instanceof PDO) throw UthengaTieErrors::providerUnavailable('database');
    $name = $pdo->prepare('SELECT name FROM users WHERE id=?');
    $name->execute([$user['id']]);
    $user['name'] = (string) ($name->fetchColumn() ?: '');
    return [$user, (new UthengaTieKernel())->busOperations, UthengaTieObservability::requestId()];
}

function bus_ops_write(string $bucket, string $requestId): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw UthengaTieErrors::validation(['method' => 'POST is required.']);
    UthengaTieApi::requireCsrf();
    UthengaTieApi::requireRateLimit($bucket, 60, 60, $requestId);
    return UthengaTieApi::input();
}

function bus_ops_respond(string $requestId, string $key, array $value): void
{
    UthengaTieApi::respond(['success' => true, 'schema_version' => 'tie-bus-ops/v1', 'request_id' => $requestId, $key => $value]);
}
