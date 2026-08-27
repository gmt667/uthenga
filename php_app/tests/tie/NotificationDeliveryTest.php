<?php
/** Notification leases, truth states, retries, receipts, and evidence. */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function tie_notification_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

final class TieTestFailingNotificationAdapter implements UthengaTieNotificationAdapter
{
    public function channel(): string { return 'sms'; }
    public function provider(): string { return 'test_failure'; }
    public function health(): array { return ['instrumented' => true, 'provider' => $this->provider(), 'reason' => 'test']; }
    public function send(array $notification, array $recipient): UthengaTieNotificationDeliveryResult
    {
        return new UthengaTieNotificationDeliveryResult(UthengaTieNotificationDeliveryResult::FAILED, errorCode: 'temporary_provider_error');
    }
}

final class TieTestSentNotificationAdapter implements UthengaTieNotificationAdapter
{
    public function channel(): string { return 'push'; }
    public function provider(): string { return 'test_receipts'; }
    public function health(): array { return ['instrumented' => true, 'provider' => $this->provider(), 'reason' => 'test']; }
    public function send(array $notification, array $recipient): UthengaTieNotificationDeliveryResult
    {
        return new UthengaTieNotificationDeliveryResult(UthengaTieNotificationDeliveryResult::SENT, 'provider-message-test-1');
    }
}

tie_notification_assert($pdo instanceof PDO, 'Configured database is available.');
$user = $pdo->query('SELECT id FROM users LIMIT 1')->fetch();
tie_notification_assert(is_array($user), 'A user exists for scoped notification delivery.');
$userId = (string) $user['id'];

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE users SET notifications_enabled=1,email_notify=1,sms_notify=1,push_notify=1 WHERE id=?')->execute([$userId]);
    $outbox = new UthengaTieNotificationOutbox($pdo);

    $inAppId = $outbox->enqueue($userId, [
        'channel' => 'in_app', 'title' => 'Delivery test', 'body' => 'Visible in-app evidence.',
        'idempotency_key' => 'notification-test-in-app',
    ]);
    $duplicateId = $outbox->enqueue($userId, [
        'channel' => 'in_app', 'title' => 'A duplicate body may differ', 'body' => 'Idempotency still wins.',
        'idempotency_key' => 'notification-test-in-app',
    ]);
    tie_notification_assert($duplicateId === $inAppId, 'Explicit idempotency returns the original notification.');

    $dispatcher = new UthengaTieNotificationDispatcher($pdo, [new UthengaTieInAppNotificationAdapter($pdo)]);
    tie_notification_assert($dispatcher->dispatch($inAppId, 'test-worker') === 'DELIVERED', 'Database-backed in-app notification is truthfully delivered.');
    $inApp = $pdo->prepare('SELECT * FROM tie_notification_outbox WHERE id=?');
    $inApp->execute([$inAppId]);
    $inAppRow = $inApp->fetch();
    tie_notification_assert($inAppRow['status'] === 'DELIVERED' && (int) $inAppRow['attempts'] === 1, 'Delivered outbox row records one attempt.');
    $visible = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE id=? AND user_id=?');
    $visible->execute([$inAppRow['provider_message_id'], $userId]);
    tie_notification_assert((int) $visible->fetchColumn() === 1, 'In-app destination contains the delivered record.');

    $emailId = $outbox->enqueue($userId, [
        'channel' => 'email', 'title' => 'Uninstrumented test', 'body' => 'This must not leave the process.',
        'idempotency_key' => 'notification-test-email',
    ]);
    $noEmail = new UthengaTieNotificationDispatcher($pdo, [new UthengaTieUninstrumentedNotificationAdapter('email', 'email_test_disabled')]);
    tie_notification_assert($noEmail->dispatch($emailId, 'test-worker') === 'UNINSTRUMENTED', 'Disabled external email is never presented as sent.');

    $smsId = $outbox->enqueue($userId, [
        'channel' => 'sms', 'title' => 'Retry test', 'body' => 'No SMS is actually sent.',
        'idempotency_key' => 'notification-test-sms',
    ]);
    $retry = new UthengaTieNotificationDispatcher($pdo, [new TieTestFailingNotificationAdapter()], 2, 1, 30);
    tie_notification_assert($retry->dispatch($smsId, 'test-worker') === 'FAILED', 'Retryable provider failure remains pending for retry.');
    $pdo->prepare('UPDATE tie_notification_outbox SET next_attempt_at=UTC_TIMESTAMP() WHERE id=?')->execute([$smsId]);
    tie_notification_assert($retry->dispatch($smsId, 'test-worker') === 'DEAD', 'Final retry becomes DEAD rather than falsely delivered.');

    $pushId = $outbox->enqueue($userId, [
        'channel' => 'push', 'title' => 'Receipt test', 'body' => 'Provider acceptance is not delivery.',
        'idempotency_key' => 'notification-test-push',
    ]);
    $receiptDispatcher = new UthengaTieNotificationDispatcher($pdo, [new TieTestSentNotificationAdapter()]);
    tie_notification_assert($receiptDispatcher->dispatch($pushId, 'test-worker') === 'SENT', 'Provider acceptance remains SENT until a receipt arrives.');
    tie_notification_assert($receiptDispatcher->recordDeliveryReceipt('test_receipts', 'provider-message-test-1', 'DELIVERED', 'receipt-event-1', 'test-receipt-request'), 'Authenticated receipt can mark the accepted message delivered.');
    tie_notification_assert($receiptDispatcher->recordDeliveryReceipt('test_receipts', 'provider-message-test-1', 'DELIVERED', 'receipt-event-1', 'test-receipt-request'), 'Duplicate receipt is idempotently acknowledged.');
    $push = $pdo->prepare('SELECT status FROM tie_notification_outbox WHERE id=?');
    $push->execute([$pushId]);
    tie_notification_assert($push->fetchColumn() === 'DELIVERED', 'Receipt advances SENT to DELIVERED.');

    $attempts = $pdo->prepare('SELECT outcome,provider_message_hash,error_code FROM tie_notification_delivery_attempts WHERE notification_id IN (?,?,?,?) ORDER BY id');
    $attempts->execute([$inAppId, $emailId, $smsId, $pushId]);
    $evidence = $attempts->fetchAll();
    tie_notification_assert(count($evidence) === 5, 'Every worker attempt has durable evidence.');
    tie_notification_assert(!str_contains(json_encode($evidence), 'Visible in-app evidence.'), 'Attempt evidence stores no message body.');
    tie_notification_assert(!str_contains(json_encode($evidence), 'private@example.invalid'), 'Attempt evidence stores no recipient address.');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}

echo "TIE notification delivery tests passed.\n";
