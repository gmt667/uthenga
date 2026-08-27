<?php
/**
 * One bounded notification delivery batch. Schedule this command with cron or a
 * process supervisor; every run uses database leases and is safe to overlap.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/tie/bootstrap.php';

$limit = 25;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--limit=(\d+)$/', $argument, $matches)) $limit = (int) $matches[1];
}
$limit = max(1, min(100, $limit));

if (!UthengaTieFeatureFlags::enabled('notifications')) {
    fwrite(STDERR, "Notification delivery is feature-disabled.\n");
    exit(2);
}
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "The configured Uthenga database is unavailable.\n");
    exit(3);
}

try {
    $workerId = 'cli-' . (string) getmypid() . '-' . bin2hex(random_bytes(4));
    $summary = UthengaTieNotificationDispatcher::configured($pdo)->run($limit, $workerId);
    echo json_encode([
        'success' => true,
        'schema_version' => 'notification-worker/v1',
        'processed_at' => gmdate(DATE_ATOM),
        'summary' => $summary,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    UthengaTieObservability::log('notification.worker_failed', UthengaTieObservability::requestId(), [
        'module' => 'notifications',
        'status' => 'failed',
        'error_type' => $error instanceof UthengaTieException ? $error->type() : 'internal_error',
    ]);
    fwrite(STDERR, "Notification worker failed without exposing message or recipient data.\n");
    exit(1);
}
