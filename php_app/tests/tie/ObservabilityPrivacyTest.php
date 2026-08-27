<?php
/** Privacy and MariaDB compatibility coverage for operational telemetry. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_observability_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

tie_observability_assert($pdo instanceof PDO, 'Configured database is available.');
tie_observability_assert(UthengaTieObservability::metricTableAvailable(), 'MariaDB information_schema table detection finds the metrics table.');

$safe = UthengaTieObservability::sanitizeContext([
    'module' => 'payments',
    'provider' => str_repeat('p', 200),
    'duration_ms' => '12.5',
    'prompt' => 'private prompt',
    'email' => 'private@example.invalid',
    'coordinates' => ['latitude' => -13.9, 'longitude' => 33.8],
    'status' => ['nested' => 'must not be logged'],
]);
tie_observability_assert(($safe['module'] ?? '') === 'payments', 'Allowed scalar metadata is retained.');
tie_observability_assert(strlen((string) ($safe['provider'] ?? '')) === 80, 'Provider metadata is bounded.');
tie_observability_assert(($safe['duration_ms'] ?? null) === 12.5, 'Numeric metadata is normalized.');
tie_observability_assert(!isset($safe['prompt'], $safe['email'], $safe['coordinates'], $safe['status']), 'Prompts, contact data, coordinates, and nested values are rejected.');

$pdo->beginTransaction();
try {
    $requestId = 'privacy-test-' . bin2hex(random_bytes(8));
    UthengaTieObservability::log('privacy.test', $requestId, [
        'module' => 'operations',
        'status' => 'ok',
        'email' => 'private@example.invalid',
        'booking' => ['id' => 'private-booking'],
        'api_key' => 'never-store-this',
    ]);
    UthengaTieMetrics::record('requests', 1, $requestId, [
        'module' => 'operations',
        'status' => 'ok',
        'user_id' => 'private-user',
    ]);

    $trace = $pdo->prepare('SELECT * FROM tie_request_traces WHERE request_id=? LIMIT 1');
    $trace->execute([$requestId]);
    $traceRow = $trace->fetch();
    tie_observability_assert(is_array($traceRow), 'Trace metadata is persisted after MariaDB-safe table detection.');
    tie_observability_assert(($traceRow['module_name'] ?? '') === 'operations' && ($traceRow['status_name'] ?? '') === 'ok', 'Only approved trace dimensions are persisted.');
    tie_observability_assert(!str_contains(json_encode($traceRow), 'private@example.invalid'), 'Trace rows contain no contact data.');
    tie_observability_assert(!str_contains(json_encode($traceRow), 'never-store-this'), 'Trace rows contain no secrets.');

    $metric = $pdo->prepare('SELECT * FROM tie_metric_events WHERE request_id=? LIMIT 1');
    $metric->execute([$requestId]);
    $metricRow = $metric->fetch();
    tie_observability_assert(is_array($metricRow) && $metricRow['metric'] === 'requests', 'Metric metadata is persisted.');
    tie_observability_assert(!str_contains(json_encode($metricRow), 'private-user'), 'Metric rows reject user identifiers.');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

echo "TIE observability privacy tests passed.\n";
