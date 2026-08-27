<?php
/** Phases 17/19/20: journey derivation and durable notification outbox. */
require_once __DIR__ . '/../../config.php'; require_once __DIR__ . '/../../db.php'; require_once __DIR__ . '/../../includes/tie/bootstrap.php';
function tie_operations_assert(bool $condition, string $message): void { if (!$condition) throw new RuntimeException('Assertion failed: ' . $message); }

$kernel = new UthengaTieKernel(); tie_operations_assert($kernel->journey instanceof UthengaTieJourneyService, 'Kernel exposes booking-derived journey service.');

if ($pdo instanceof PDO) {
    $user = $pdo->query('SELECT id FROM users LIMIT 1')->fetch(); tie_operations_assert(is_array($user), 'Configured database has a user for operational integration.'); $pdo->beginTransaction();
    try {
        $outbox = new UthengaTieNotificationOutbox($pdo); try { $outbox->enqueue('USR-TEST', ['channel' => 'invalid', 'body' => 'x']); throw new RuntimeException('Unsupported notification channel was accepted.'); } catch (UthengaTieException $error) { tie_operations_assert($error->type() === 'validation_error', 'Notification outbox validates delivery channels.'); } $outbox->enqueue((string) $user['id'], ['channel' => 'in_app', 'title' => 'Journey update', 'body' => 'Your journey is ready.']); tie_operations_assert(count($outbox->pending()) >= 1, 'Notification outbox persists pending delivery without sending it directly.');
        $dashboard = $kernel->journey->current((string) $user['id']); tie_operations_assert(($dashboard['schema_version'] ?? '') === 'journey-dashboard/v1', 'Journey dashboard has a versioned contract.');
    } finally { $pdo->rollBack(); }
}
echo "TIE Program 3 operations tests passed.\n";
