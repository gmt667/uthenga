<?php
/** Durable, provider-neutral notification delivery with truthful outcomes. */

final class UthengaTieNotificationDeliveryResult
{
    public const SENT = 'SENT';
    public const DELIVERED = 'DELIVERED';
    public const FAILED = 'FAILED';
    public const UNINSTRUMENTED = 'UNINSTRUMENTED';

    public function __construct(
        public readonly string $outcome,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorCode = null,
        public readonly ?int $httpStatus = null,
        public readonly bool $retryable = true,
    ) {
        if (!in_array($outcome, [self::SENT, self::DELIVERED, self::FAILED, self::UNINSTRUMENTED], true)) {
            throw new InvalidArgumentException('Unsupported notification delivery outcome.');
        }
    }
}

interface UthengaTieNotificationAdapter
{
    public function channel(): string;
    public function provider(): string;
    /** @return array{instrumented:bool,provider:string,reason:string} */
    public function health(): array;
    public function send(array $notification, array $recipient): UthengaTieNotificationDeliveryResult;
}

final class UthengaTieInAppNotificationAdapter implements UthengaTieNotificationAdapter
{
    public function __construct(private PDO $db) {}
    public function channel(): string { return 'in_app'; }
    public function provider(): string { return 'uthenga_database'; }
    public function health(): array
    {
        try {
            $query = $this->db->prepare(
                'SELECT 1 FROM information_schema.tables
                 WHERE table_schema=DATABASE() AND table_name=? LIMIT 1'
            );
            $query->execute(['notifications']);
            return ['instrumented' => (bool) $query->fetchColumn(), 'provider' => $this->provider(), 'reason' => 'database_destination'];
        } catch (Throwable $error) {
            return ['instrumented' => false, 'provider' => $this->provider(), 'reason' => 'notification_table_unavailable'];
        }
    }
    public function send(array $notification, array $recipient): UthengaTieNotificationDeliveryResult
    {
        if (($this->health()['instrumented'] ?? false) !== true) {
            return new UthengaTieNotificationDeliveryResult(
                UthengaTieNotificationDeliveryResult::UNINSTRUMENTED,
                errorCode: 'notification_table_unavailable',
                retryable: false,
            );
        }
        try {
            $statement = $this->db->prepare(
                'INSERT INTO notifications (user_id,title,message,type,is_read,created_at)
                 VALUES (?,?,?,\'tie\',0,UTC_TIMESTAMP())'
            );
            $statement->execute([
                (string) $notification['user_id'],
                (string) $notification['title'],
                (string) $notification['body'],
            ]);
            return new UthengaTieNotificationDeliveryResult(
                UthengaTieNotificationDeliveryResult::DELIVERED,
                (string) $this->db->lastInsertId(),
                retryable: false,
            );
        } catch (Throwable $error) {
            return new UthengaTieNotificationDeliveryResult(
                UthengaTieNotificationDeliveryResult::FAILED,
                errorCode: 'in_app_persist_failed',
            );
        }
    }
}

final class UthengaTiePhpMailNotificationAdapter implements UthengaTieNotificationAdapter
{
    public function channel(): string { return 'email'; }
    public function provider(): string { return 'php_mail'; }
    public function health(): array
    {
        if (!UthengaTieConfig::boolean('TIE_NOTIFICATION_EMAIL_ENABLED')) {
            return ['instrumented' => false, 'provider' => $this->provider(), 'reason' => 'email_channel_disabled'];
        }
        if (strtolower(UthengaTieConfig::string('TIE_NOTIFICATION_EMAIL_PROVIDER', 'php_mail')) !== 'php_mail') {
            return ['instrumented' => false, 'provider' => $this->provider(), 'reason' => 'email_provider_mismatch'];
        }
        if (!function_exists('uthenga_send_mail')) {
            return ['instrumented' => false, 'provider' => $this->provider(), 'reason' => 'php_mail_unavailable'];
        }
        return ['instrumented' => true, 'provider' => $this->provider(), 'reason' => 'configured'];
    }
    public function send(array $notification, array $recipient): UthengaTieNotificationDeliveryResult
    {
        $email = trim((string) ($recipient['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new UthengaTieNotificationDeliveryResult(
                UthengaTieNotificationDeliveryResult::FAILED,
                errorCode: 'recipient_email_missing',
                retryable: false,
            );
        }
        $sent = uthenga_send_mail(
            $email,
            (string) $notification['title'],
            nl2br(htmlspecialchars((string) $notification['body'], ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            (string) $notification['body'],
        );
        // PHP mail only proves hand-off to the local mail transport. It is never
        // represented as DELIVERED without an external delivery receipt.
        return $sent
            ? new UthengaTieNotificationDeliveryResult(UthengaTieNotificationDeliveryResult::SENT)
            : new UthengaTieNotificationDeliveryResult(UthengaTieNotificationDeliveryResult::FAILED, errorCode: 'php_mail_rejected');
    }
}

final class UthengaTieHttpSmsNotificationAdapter implements UthengaTieNotificationAdapter
{
    public function channel(): string { return 'sms'; }
    public function provider(): string
    {
        return UthengaTieConfig::string('TIE_NOTIFICATION_SMS_PROVIDER', 'generic_http');
    }
    public function health(): array
    {
        $endpoint = UthengaTieConfig::string('TIE_NOTIFICATION_SMS_ENDPOINT');
        if (!UthengaTieConfig::boolean('TIE_NOTIFICATION_SMS_ENABLED')) {
            return ['instrumented' => false, 'provider' => $this->provider(), 'reason' => 'sms_channel_disabled'];
        }
        if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL) || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
            return ['instrumented' => false, 'provider' => $this->provider(), 'reason' => 'sms_https_endpoint_missing'];
        }
        if (UthengaTieConfig::string('TIE_NOTIFICATION_SMS_BEARER_TOKEN') === '' || UthengaTieConfig::string('TIE_NOTIFICATION_SMS_SENDER') === '') {
            return ['instrumented' => false, 'provider' => $this->provider(), 'reason' => 'sms_credentials_missing'];
        }
        if (!function_exists('curl_init')) {
            return ['instrumented' => false, 'provider' => $this->provider(), 'reason' => 'curl_unavailable'];
        }
        return ['instrumented' => true, 'provider' => $this->provider(), 'reason' => 'configured'];
    }
    public function send(array $notification, array $recipient): UthengaTieNotificationDeliveryResult
    {
        $phone = trim((string) ($recipient['phone'] ?? ''));
        if ($phone === '') {
            return new UthengaTieNotificationDeliveryResult(
                UthengaTieNotificationDeliveryResult::FAILED,
                errorCode: 'recipient_phone_missing',
                retryable: false,
            );
        }
        $payload = json_encode([
            'to' => $phone,
            'message' => (string) $notification['body'],
            'sender' => UthengaTieConfig::string('TIE_NOTIFICATION_SMS_SENDER'),
            'client_reference' => (string) $notification['id'],
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return new UthengaTieNotificationDeliveryResult(UthengaTieNotificationDeliveryResult::FAILED, errorCode: 'sms_payload_invalid', retryable: false);
        }
        $handle = curl_init(UthengaTieConfig::string('TIE_NOTIFICATION_SMS_ENDPOINT'));
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max(1, UthengaTieConfig::integer('TIE_NOTIFICATION_SMS_TIMEOUT', 10)),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . UthengaTieConfig::string('TIE_NOTIFICATION_SMS_BEARER_TOKEN'),
            ],
        ]);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($handle);
        curl_close($handle);
        if ($curlError !== 0 || $status < 200 || $status >= 300) {
            return new UthengaTieNotificationDeliveryResult(
                UthengaTieNotificationDeliveryResult::FAILED,
                errorCode: $curlError !== 0 ? 'sms_transport_failed' : 'sms_http_' . $status,
                httpStatus: $status > 0 ? $status : null,
            );
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $providerMessageId = null;
        if (is_array($decoded)) {
            $providerMessageId = $decoded['message_id'] ?? $decoded['id'] ?? ($decoded['data']['message_id'] ?? null);
            $providerMessageId = is_scalar($providerMessageId) ? substr(trim((string) $providerMessageId), 0, 160) : null;
        }
        // A successful HTTP response proves provider acceptance only.
        return new UthengaTieNotificationDeliveryResult(
            UthengaTieNotificationDeliveryResult::SENT,
            $providerMessageId ?: null,
            httpStatus: $status,
        );
    }
}

final class UthengaTieUninstrumentedNotificationAdapter implements UthengaTieNotificationAdapter
{
    public function __construct(private string $channelName, private string $reason = 'channel_adapter_unavailable') {}
    public function channel(): string { return $this->channelName; }
    public function provider(): string { return 'unconfigured'; }
    public function health(): array { return ['instrumented' => false, 'provider' => $this->provider(), 'reason' => $this->reason]; }
    public function send(array $notification, array $recipient): UthengaTieNotificationDeliveryResult
    {
        return new UthengaTieNotificationDeliveryResult(
            UthengaTieNotificationDeliveryResult::UNINSTRUMENTED,
            errorCode: $this->reason,
            retryable: false,
        );
    }
}

final class UthengaTieNotificationOutbox
{
    public function __construct(private PDO $db) {}

    public function enqueue(string $userId, array $message): string
    {
        $title = trim((string) ($message['title'] ?? 'Uthenga update'));
        $body = trim((string) ($message['body'] ?? ''));
        $channel = strtolower(trim((string) ($message['channel'] ?? 'in_app')));
        if ($body === '' || !in_array($channel, ['in_app', 'email', 'sms', 'push'], true)) {
            throw UthengaTieErrors::validation(['notification' => 'A supported notification message is required.']);
        }
        $idempotencySource = trim((string) ($message['idempotency_key'] ?? ''));
        $idempotencyKey = $idempotencySource !== ''
            ? hash('sha256', $userId . '|' . $channel . '|' . $idempotencySource)
            : hash('sha256', $userId . '|' . $channel . '|' . $title . '|' . $body);
        $id = 'TIENT-' . bin2hex(random_bytes(10));
        $statement = $this->db->prepare(
            'INSERT IGNORE INTO tie_notification_outbox
             (id,user_id,channel,title,body,status,idempotency_key,next_attempt_at)
             VALUES (?,?,?,?,?,\'PENDING\',?,UTC_TIMESTAMP())'
        );
        $statement->execute([$id, $userId, $channel, substr($title, 0, 255), substr($body, 0, 2000), $idempotencyKey]);
        if ($statement->rowCount() === 1) return $id;
        $existing = $this->db->prepare('SELECT id FROM tie_notification_outbox WHERE idempotency_key=? LIMIT 1');
        $existing->execute([$idempotencyKey]);
        $existingId = $existing->fetchColumn();
        if (!is_string($existingId) || $existingId === '') throw new RuntimeException('Notification idempotency lookup failed.');
        return $existingId;
    }

    public function pending(int $limit = 50): array
    {
        $statement = $this->db->prepare(
            "SELECT id,user_id,channel,title,body,status,attempts,next_attempt_at
             FROM tie_notification_outbox
             WHERE status IN ('PENDING','FAILED')
               AND COALESCE(next_attempt_at,scheduled_at,created_at)<=UTC_TIMESTAMP()
             ORDER BY created_at LIMIT ?"
        );
        $statement->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }
}

final class UthengaTieNotificationDispatcher
{
    /** @var array<string,UthengaTieNotificationAdapter> */
    private array $adapters = [];

    /** @param list<UthengaTieNotificationAdapter> $adapters */
    public function __construct(
        private PDO $db,
        array $adapters,
        private int $maxAttempts = 5,
        private int $baseBackoffSeconds = 60,
        private int $leaseSeconds = 120,
    ) {
        foreach ($adapters as $adapter) $this->adapters[$adapter->channel()] = $adapter;
        $this->maxAttempts = max(1, min(20, $this->maxAttempts));
        $this->baseBackoffSeconds = max(1, min(86400, $this->baseBackoffSeconds));
        $this->leaseSeconds = max(15, min(3600, $this->leaseSeconds));
    }

    public static function configured(PDO $db): self
    {
        return new self($db, [
            new UthengaTieInAppNotificationAdapter($db),
            new UthengaTiePhpMailNotificationAdapter(),
            new UthengaTieHttpSmsNotificationAdapter(),
            new UthengaTieUninstrumentedNotificationAdapter('push', 'push_adapter_unavailable'),
        ], UthengaTieConfig::integer('TIE_NOTIFICATION_MAX_ATTEMPTS', 5),
           UthengaTieConfig::integer('TIE_NOTIFICATION_BACKOFF_SECONDS', 60),
           UthengaTieConfig::integer('TIE_NOTIFICATION_LEASE_SECONDS', 120));
    }

    public function run(int $limit = 25, ?string $workerId = null): array
    {
        $workerId = $this->safeWorkerId($workerId);
        $summary = ['claimed' => 0, 'sent' => 0, 'delivered' => 0, 'failed' => 0, 'dead' => 0, 'uninstrumented' => 0];
        for ($index = 0; $index < max(1, min(100, $limit)); $index++) {
            $notification = $this->claim(null, $workerId);
            if ($notification === null) break;
            $summary['claimed']++;
            $status = strtolower($this->deliver($notification));
            if (isset($summary[$status])) $summary[$status]++;
        }
        return $summary;
    }

    public function dispatch(string $notificationId, ?string $workerId = null): ?string
    {
        $notification = $this->claim($notificationId, $this->safeWorkerId($workerId));
        return $notification === null ? null : $this->deliver($notification);
    }

    public function health(): array
    {
        $channels = [];
        foreach (['in_app', 'email', 'sms', 'push'] as $channel) {
            $adapter = $this->adapters[$channel] ?? new UthengaTieUninstrumentedNotificationAdapter($channel);
            $channels[$channel] = $adapter->health();
        }
        $counts = [];
        try {
            $query = $this->db->query('SELECT status,COUNT(*) total FROM tie_notification_outbox GROUP BY status ORDER BY status');
            foreach ($query->fetchAll() as $row) $counts[(string) $row['status']] = (int) $row['total'];
            $stale = (int) $this->db->query("SELECT COUNT(*) FROM tie_notification_outbox WHERE status='PROCESSING' AND lease_expires_at<UTC_TIMESTAMP()")->fetchColumn();
        } catch (Throwable $error) {
            $stale = 0;
        }
        return ['schema_version' => 'notification-health/v1', 'channels' => $channels, 'queue' => $counts, 'stale_leases' => $stale];
    }

    /** Record a provider-authenticated receipt; never call this from a browser return. */
    public function recordDeliveryReceipt(string $provider, string $providerMessageId, string $outcome, string $eventKey, string $requestId, ?string $errorCode = null): bool
    {
        $provider = substr(trim($provider), 0, 80);
        $providerMessageId = substr(trim($providerMessageId), 0, 160);
        $outcome = strtoupper(trim($outcome));
        if ($provider === '' || $providerMessageId === '' || !in_array($outcome, ['DELIVERED', 'FAILED'], true)) return false;
        $eventHash = hash('sha256', $provider . '|' . $eventKey);
        $messageHash = hash('sha256', $providerMessageId);
        $owns = !$this->db->inTransaction();
        if ($owns) $this->db->beginTransaction();
        try {
            $query = $this->db->prepare('SELECT * FROM tie_notification_outbox WHERE provider_name=? AND provider_message_id=? LIMIT 1 FOR UPDATE');
            $query->execute([$provider, $providerMessageId]);
            $notification = $query->fetch();
            if (!is_array($notification)) { if ($owns) $this->db->rollBack(); return false; }
            $receipt = $this->db->prepare(
                'INSERT IGNORE INTO tie_notification_delivery_receipts
                 (notification_id,provider_name,provider_message_hash,event_key,outcome,request_id,error_code)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $receipt->execute([$notification['id'], $provider, $messageHash, $eventHash, $outcome, substr($requestId, 0, 100), $this->safeCode($errorCode)]);
            if ($receipt->rowCount() !== 1) { if ($owns) $this->db->commit(); return true; }
            if ($outcome === 'DELIVERED') {
                $this->db->prepare("UPDATE tie_notification_outbox SET status='DELIVERED',delivered_at=UTC_TIMESTAMP(),terminal_at=UTC_TIMESTAMP(),last_error_code=NULL,status_reason='provider_receipt',updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$notification['id']]);
            } else {
                $next = (int) $notification['attempts'] >= $this->maxAttempts ? 'DEAD' : 'FAILED';
                $nextAt = gmdate('Y-m-d H:i:s', time() + $this->backoff((int) $notification['attempts']));
                $this->db->prepare('UPDATE tie_notification_outbox SET status=?,last_error_code=?,status_reason=\'provider_failure_receipt\',next_attempt_at=?,terminal_at=IF(?=\'DEAD\',UTC_TIMESTAMP(),NULL),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$next, $this->safeCode($errorCode) ?: 'provider_delivery_failed', $nextAt, $next, $notification['id']]);
            }
            if ($owns) $this->db->commit();
            return true;
        } catch (Throwable $error) {
            if ($owns && $this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function claim(?string $notificationId, string $workerId): ?array
    {
        $owns = !$this->db->inTransaction();
        if ($owns) $this->db->beginTransaction();
        try {
            $whereId = $notificationId === null ? '' : ' AND id=?';
            $sql = "SELECT * FROM tie_notification_outbox
                    WHERE attempts < ? {$whereId}
                      AND (((status IN ('PENDING','FAILED')) AND COALESCE(next_attempt_at,scheduled_at,created_at)<=UTC_TIMESTAMP())
                        OR (status='PROCESSING' AND lease_expires_at<UTC_TIMESTAMP()))
                    ORDER BY created_at LIMIT 1 FOR UPDATE";
            $query = $this->db->prepare($sql);
            $params = [$this->maxAttempts];
            if ($notificationId !== null) $params[] = $notificationId;
            $query->execute($params);
            $row = $query->fetch();
            if (!is_array($row)) { if ($owns) $this->db->commit(); return null; }
            if ($row['status'] === 'PROCESSING') {
                $this->db->prepare("UPDATE tie_notification_delivery_attempts SET outcome='FAILED',error_code='lease_expired',finished_at=UTC_TIMESTAMP(6) WHERE notification_id=? AND attempt_number=? AND outcome='PROCESSING'")->execute([$row['id'], $row['attempts']]);
            }
            $token = hash('sha256', $workerId . '|' . $row['id'] . '|' . random_bytes(16));
            $leaseExpiry = gmdate('Y-m-d H:i:s', time() + $this->leaseSeconds);
            $this->db->prepare("UPDATE tie_notification_outbox SET status='PROCESSING',attempts=attempts+1,lease_token=?,lease_expires_at=?,status_reason='worker_claimed',updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$token, $leaseExpiry, $row['id']]);
            $claimed = $this->db->prepare('SELECT * FROM tie_notification_outbox WHERE id=? LIMIT 1');
            $claimed->execute([$row['id']]);
            $row = $claimed->fetch();
            $requestId = UthengaTieObservability::requestId();
            $adapter = $this->adapters[$row['channel']] ?? new UthengaTieUninstrumentedNotificationAdapter((string) $row['channel']);
            $this->db->prepare("INSERT INTO tie_notification_delivery_attempts (notification_id,attempt_number,channel,provider_name,outcome,request_id,started_at) VALUES (?,?,?,?, 'PROCESSING',?,UTC_TIMESTAMP(6))")->execute([$row['id'], $row['attempts'], $row['channel'], $adapter->provider(), $requestId]);
            $row['lease_token'] = $token;
            $row['request_id'] = $requestId;
            if ($owns) $this->db->commit();
            return $row;
        } catch (Throwable $error) {
            if ($owns && $this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function deliver(array $notification): string
    {
        $adapter = $this->adapters[$notification['channel']] ?? new UthengaTieUninstrumentedNotificationAdapter((string) $notification['channel']);
        $health = $adapter->health();
        $started = microtime(true);
        if (($health['instrumented'] ?? false) !== true) {
            $result = new UthengaTieNotificationDeliveryResult(
                UthengaTieNotificationDeliveryResult::UNINSTRUMENTED,
                errorCode: (string) ($health['reason'] ?? 'channel_uninstrumented'),
                retryable: false,
            );
        } else {
            $recipient = $this->recipient((string) $notification['user_id']);
            if ($recipient === null) {
                $result = new UthengaTieNotificationDeliveryResult(UthengaTieNotificationDeliveryResult::FAILED, errorCode: 'recipient_not_found', retryable: false);
            } elseif (!$this->recipientAllows((string) $notification['channel'], $recipient)) {
                $result = new UthengaTieNotificationDeliveryResult(UthengaTieNotificationDeliveryResult::UNINSTRUMENTED, errorCode: 'recipient_channel_disabled', retryable: false);
            } else {
                try {
                    $result = $adapter->send($notification, $recipient);
                } catch (Throwable $error) {
                    $result = new UthengaTieNotificationDeliveryResult(UthengaTieNotificationDeliveryResult::FAILED, errorCode: 'adapter_exception');
                }
            }
        }
        $latency = round((microtime(true) - $started) * 1000, 2);
        return $this->finalize($notification, $adapter, $result, $latency);
    }

    private function finalize(array $notification, UthengaTieNotificationAdapter $adapter, UthengaTieNotificationDeliveryResult $result, float $latency): string
    {
        $status = $result->outcome;
        if ($status === UthengaTieNotificationDeliveryResult::FAILED && (!$result->retryable || (int) $notification['attempts'] >= $this->maxAttempts)) $status = 'DEAD';
        $nextAt = $status === 'FAILED' ? gmdate('Y-m-d H:i:s', time() + $this->backoff((int) $notification['attempts'])) : null;
        $messageId = $result->providerMessageId === null ? null : substr($result->providerMessageId, 0, 160);
        $errorCode = $this->safeCode($result->errorCode);
        $terminal = in_array($status, ['DELIVERED', 'DEAD', 'UNINSTRUMENTED'], true);
        $statement = $this->db->prepare(
            'UPDATE tie_notification_outbox SET status=?,provider_name=?,provider_message_id=?,last_error_code=?,status_reason=?,
             next_attempt_at=?,lease_token=NULL,lease_expires_at=NULL,
             sent_at=IF(? IN (\'SENT\',\'DELIVERED\'),COALESCE(sent_at,UTC_TIMESTAMP()),sent_at),
             delivered_at=IF(?=\'DELIVERED\',UTC_TIMESTAMP(),delivered_at),
             terminal_at=IF(?=1,UTC_TIMESTAMP(),NULL),updated_at=UTC_TIMESTAMP()
             WHERE id=? AND lease_token=?'
        );
        $statement->execute([$status, $adapter->provider(), $messageId, $errorCode, strtolower($status), $nextAt, $status, $status, $terminal ? 1 : 0, $notification['id'], $notification['lease_token']]);
        if ($statement->rowCount() !== 1) return 'FAILED';
        $attempt = $this->db->prepare(
            'UPDATE tie_notification_delivery_attempts SET outcome=?,provider_name=?,provider_message_hash=?,http_status=?,error_code=?,latency_ms=?,finished_at=UTC_TIMESTAMP(6)
             WHERE notification_id=? AND attempt_number=? AND outcome=\'PROCESSING\''
        );
        $attempt->execute([$status, $adapter->provider(), $messageId === null ? null : hash('sha256', $messageId), $result->httpStatus, $errorCode, $latency, $notification['id'], $notification['attempts']]);
        UthengaTieObservability::log('notification.delivery_attempted', (string) $notification['request_id'], [
            'module' => 'notifications', 'provider' => $adapter->provider(), 'status' => strtolower($status), 'duration_ms' => $latency,
        ]);
        return $status;
    }

    private function recipient(string $userId): ?array
    {
        $query = $this->db->prepare('SELECT id,email,phone,notifications_enabled,email_notify,sms_notify,push_notify FROM users WHERE id=? LIMIT 1');
        $query->execute([$userId]);
        $recipient = $query->fetch();
        return is_array($recipient) ? $recipient : null;
    }

    private function recipientAllows(string $channel, array $recipient): bool
    {
        if ($channel === 'in_app') return (int) ($recipient['notifications_enabled'] ?? 1) === 1;
        $preference = ['email' => 'email_notify', 'sms' => 'sms_notify', 'push' => 'push_notify'][$channel] ?? null;
        return $preference !== null && (int) ($recipient['notifications_enabled'] ?? 1) === 1 && (int) ($recipient[$preference] ?? 0) === 1;
    }

    private function backoff(int $attempt): int
    {
        return min(86400, $this->baseBackoffSeconds * (2 ** max(0, min(10, $attempt - 1))));
    }

    private function safeCode(?string $code): ?string
    {
        if ($code === null) return null;
        $code = preg_replace('/[^a-z0-9_.:-]/i', '_', trim($code));
        return $code === '' ? null : substr($code, 0, 80);
    }

    private function safeWorkerId(?string $workerId): string
    {
        $workerId = trim((string) $workerId);
        return preg_match('/^[A-Za-z0-9._:-]{3,100}$/', $workerId) ? $workerId : 'worker-' . bin2hex(random_bytes(6));
    }
}
