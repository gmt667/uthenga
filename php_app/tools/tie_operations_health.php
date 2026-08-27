<?php
/** Privacy-safe CLI health snapshot for cron, support, and monitoring probes. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/tie/bootstrap.php';

function tie_ops_table(PDO $db, string $table): bool
{
    if (!preg_match('/^[a-z0-9_]{1,64}$/i', $table)) return false;
    $statement = $db->prepare(
        'SELECT 1 FROM information_schema.tables
         WHERE table_schema=DATABASE() AND table_name=? LIMIT 1'
    );
    $statement->execute([$table]);
    return (bool) $statement->fetchColumn();
}

function tie_ops_count(PDO $db, string $table): int
{
    if (!tie_ops_table($db, $table)) return 0;
    return (int) $db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}

if (!$pdo instanceof PDO) {
    echo json_encode(['status' => 'critical', 'database' => 'unavailable']) . PHP_EOL;
    exit(2);
}

$dispatcher = UthengaTieNotificationDispatcher::configured($pdo);
$notificationHealth = $dispatcher->health();
$telemetry = [
    'metrics_table' => tie_ops_table($pdo, 'tie_metric_events'),
    'traces_table' => tie_ops_table($pdo, 'tie_request_traces'),
    'metrics_recorded' => tie_ops_count($pdo, 'tie_metric_events'),
    'traces_recorded' => tie_ops_count($pdo, 'tie_request_traces'),
];
$uninstrumented = array_filter(
    $notificationHealth['channels'],
    static fn(array $channel): bool => ($channel['instrumented'] ?? false) !== true,
);
$status = ($notificationHealth['stale_leases'] ?? 0) > 0 ? 'warning' : 'ok';
if (!$telemetry['metrics_table'] || !$telemetry['traces_table']) $status = 'warning';

echo json_encode([
    'schema_version' => 'tie-operations-health/v1',
    'status' => $status,
    'checked_at' => gmdate(DATE_ATOM),
    'environment' => APP_ENV,
    'database' => 'available',
    'telemetry' => $telemetry,
    'notifications' => [
        'feature_enabled' => UthengaTieFeatureFlags::enabled('notifications'),
        'queue' => $notificationHealth['queue'],
        'stale_leases' => $notificationHealth['stale_leases'],
        'channels' => $notificationHealth['channels'],
        'uninstrumented_channel_count' => count($uninstrumented),
    ],
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
