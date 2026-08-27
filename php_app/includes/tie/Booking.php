<?php
/** Phase 10: transactional orchestration over the existing marketplace API. */

interface UthengaTieMarketplaceBookingProvider { public function name(): string; public function create(array $operation): array; public function cancel(string $bookingId): array; }

final class UthengaTieBookingRequest
{
    public const SCHEMA_VERSION = 'booking-request/v1';
    public string $planId; public string $idempotencyKey; public string $gateway; public ?string $paymentReference;
    public function __construct(string $planId, string $idempotencyKey, string $gateway, ?string $paymentReference) { $this->planId = $planId; $this->idempotencyKey = $idempotencyKey; $this->gateway = $gateway; $this->paymentReference = $paymentReference; }
}

final class UthengaTieBookingResult
{
    public const SCHEMA_VERSION = 'booking-result/v1'; public array $data;
    public function __construct(array $data) { $this->data = $data; } public function toArray(): array { return $this->data; }
}

final class UthengaTieBookingContracts
{
    private const REQUEST_FIELDS = ['plan_id', 'idempotency_key', 'gateway', 'payment_reference', 'csrf_token'];
    public static function request(array $input): UthengaTieBookingRequest
    {
        $unknown = array_values(array_diff(array_keys($input), self::REQUEST_FIELDS)); if ($unknown) throw UthengaTieErrors::validation(['request' => 'Unsupported booking field(s): ' . implode(', ', $unknown) . '.']);
        $planId = UthengaTiePlanContracts::planId($input); $key = trim((string) ($input['idempotency_key'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._-]{16,128}$/', $key)) throw UthengaTieErrors::validation(['idempotency_key' => 'Use a unique 16-128 character idempotency key.']);
        $gateway = strtolower(trim((string) ($input['gateway'] ?? ''))); $allowed = ['airtel', 'tnm', 'card', 'direct nbs transfer', 'uthenga pay'];
        if (!in_array($gateway, $allowed, true)) throw UthengaTieErrors::validation(['gateway' => 'A supported existing marketplace payment method is required.']);
        $reference = trim((string) ($input['payment_reference'] ?? '')); if ($reference === '' || strlen($reference) > 160) throw UthengaTieErrors::validation(['payment_reference' => 'A payment correlation reference of at most 160 characters is required.']);
        return new UthengaTieBookingRequest($planId, $key, $gateway, $reference);
    }
    public static function executionId(array $input): string { $id = trim((string) ($input['execution_id'] ?? '')); if (!preg_match('/^[a-f0-9-]{36}$/i', $id)) throw UthengaTieErrors::validation(['execution_id' => 'A valid booking execution ID is required.']); return $id; }
}

final class UthengaTieBookingState
{
    public const PENDING = 'PENDING'; public const VALIDATING = 'VALIDATING'; public const BOOKING = 'BOOKING'; public const PARTIALLY_BOOKED = 'PARTIALLY_BOOKED'; public const BOOKED = 'BOOKED'; public const FAILED = 'FAILED'; public const CANCELLED = 'CANCELLED'; public const ROLLED_BACK = 'ROLLED_BACK';
    public static function transition(string $from, string $to): bool { $map = [self::PENDING => [self::VALIDATING, self::CANCELLED], self::VALIDATING => [self::BOOKING, self::FAILED], self::BOOKING => [self::PARTIALLY_BOOKED, self::BOOKED, self::FAILED, self::ROLLED_BACK], self::PARTIALLY_BOOKED => [self::BOOKING, self::ROLLED_BACK, self::FAILED], self::BOOKED => [self::CANCELLED, self::ROLLED_BACK], self::FAILED => [], self::CANCELLED => [], self::ROLLED_BACK => []]; return in_array($to, $map[$from] ?? [], true); }
}

/** Calls the existing authenticated request router; it does not write booking tables. */
final class UthengaTieLegacyMarketplaceBookingProvider implements UthengaTieMarketplaceBookingProvider
{
    private string $sessionId; private string $csrf;
    public function __construct(string $sessionId, string $csrf) { $this->sessionId = $sessionId; $this->csrf = $csrf; }
    public function name(): string { return 'legacy_request_api'; }
    public function create(array $operation): array { return $this->call(array_merge(['action' => 'create_booking', 'csrf_token' => $this->csrf], $operation)); }
    public function cancel(string $bookingId): array { return $this->call(['action' => 'cancel_booking', 'booking_id' => $bookingId, 'csrf_token' => $this->csrf]); }
    private function call(array $payload): array
    {
        if (!function_exists('curl_init') || $this->sessionId === '' || $this->csrf === '') throw UthengaTieErrors::providerUnavailable('legacy_marketplace_booking');
        $url = rtrim(BASE_URL, '/') . '/request_api.php'; $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(1, UthengaTieConfig::integer('TIE_BOOKING_TIMEOUT', 20)), CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_COOKIE => session_name() . '=' . $this->sessionId, CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986)]);
        $raw = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); curl_close($handle); $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) throw UthengaTieErrors::providerUnavailable('legacy_marketplace_booking'); return $decoded;
    }
}

final class UthengaTieUnavailableMarketplaceBookingProvider implements UthengaTieMarketplaceBookingProvider
{
    public function name(): string { return 'unconfigured'; } public function create(array $operation): array { throw UthengaTieErrors::providerUnavailable('marketplace_booking'); } public function cancel(string $bookingId): array { throw UthengaTieErrors::providerUnavailable('marketplace_booking'); }
}

final class UthengaTieMarketplaceBookingProviderFactory
{
    public static function configured(): UthengaTieMarketplaceBookingProvider
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['csrf_token'])) return new UthengaTieUnavailableMarketplaceBookingProvider();
        return new UthengaTieLegacyMarketplaceBookingProvider(session_id(), (string) $_SESSION['csrf_token']);
    }
}

final class UthengaTieBookingRepository
{
    private PDO $db; public function __construct(PDO $db) { $this->db = $db; }
    public function byKey(string $userId, string $key): ?array { $stmt = $this->db->prepare('SELECT * FROM tie_booking_executions WHERE user_id = ? AND idempotency_key = ? LIMIT 1'); $stmt->execute([$userId, $key]); return $this->hydrate($stmt->fetch()); }
    public function byId(string $id, string $userId): ?array { $stmt = $this->db->prepare('SELECT * FROM tie_booking_executions WHERE id = ? AND user_id = ? LIMIT 1'); $stmt->execute([$id, $userId]); return $this->hydrate($stmt->fetch()); }
    public function create(array $execution): void { $stmt = $this->db->prepare('INSERT INTO tie_booking_executions (id, plan_id, user_id, idempotency_key, state, rollback_policy, payment_reference_hash, journey_state, diagnostics) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'); $stmt->execute([$execution['execution_id'], $execution['plan_id'], $execution['user_id'], $execution['idempotency_key'], $execution['state'], $execution['rollback_policy'], $execution['payment_reference_hash'], json_encode($execution['journey_state']), json_encode($execution['diagnostics'])]); }
    public function save(array $execution): void { $stmt = $this->db->prepare('UPDATE tie_booking_executions SET state = ?, journey_state = ?, diagnostics = ? WHERE id = ? AND user_id = ?'); $stmt->execute([$execution['state'], json_encode($execution['journey_state']), json_encode($execution['diagnostics']), $execution['execution_id'], $execution['user_id']]); }
    public function operations(string $executionId): array { $stmt = $this->db->prepare('SELECT * FROM tie_booking_operations WHERE execution_id = ? ORDER BY id'); $stmt->execute([$executionId]); return array_map(fn(array $row): array => ['activity_id' => $row['activity_id'], 'service_id' => $row['service_id'], 'booking_id' => $row['booking_id'], 'status' => $row['operation_state'], 'attempt_count' => (int) $row['attempt_count'], 'provider' => $row['provider_name'], 'diagnostics' => json_decode((string) ($row['diagnostics'] ?? '{}'), true) ?: []], $stmt->fetchAll()); }
    public function operation(string $executionId, array $operation): void { $stmt = $this->db->prepare('INSERT INTO tie_booking_operations (execution_id, activity_id, service_id, booking_id, operation_state, attempt_count, provider_name, diagnostics) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE booking_id=VALUES(booking_id), operation_state=VALUES(operation_state), attempt_count=VALUES(attempt_count), diagnostics=VALUES(diagnostics)'); $stmt->execute([$executionId, $operation['activity_id'], $operation['service_id'], $operation['booking_id'] ?? null, $operation['status'], $operation['attempt_count'], $operation['provider'], json_encode($operation['diagnostics'])]); }
    private function hydrate($row): ?array { if (!is_array($row)) return null; return ['execution_id' => $row['id'], 'plan_id' => $row['plan_id'], 'user_id' => $row['user_id'], 'idempotency_key' => $row['idempotency_key'], 'state' => $row['state'], 'rollback_policy' => $row['rollback_policy'], 'payment_reference_hash' => $row['payment_reference_hash'], 'journey_state' => json_decode((string) ($row['journey_state'] ?? '{}'), true) ?: [], 'diagnostics' => json_decode((string) ($row['diagnostics'] ?? '{}'), true) ?: []]; }
}

final class UthengaTieBookingOrchestrator implements UthengaTieBookingModule
{
    private ?UthengaTieBookingRepository $repository; private UthengaTiePlanModule $plans; private UthengaTieQueryModule $query; private UthengaTieAvailabilityModule $availability; private UthengaTieMarketplaceBookingProvider $provider;
    public function __construct(?PDO $db, UthengaTiePlanModule $plans, UthengaTieQueryModule $query, UthengaTieAvailabilityModule $availability, UthengaTieMarketplaceBookingProvider $provider) { $this->repository = $db instanceof PDO ? new UthengaTieBookingRepository($db) : null; $this->plans = $plans; $this->query = $query; $this->availability = $availability; $this->provider = $provider; }
    public function validate(UthengaTieBookingRequest $request, string $userId): UthengaTieBookingResult { $execution = $this->prepare($request, $userId); $this->finalValidate($execution); return new UthengaTieBookingResult($this->result($execution)); }
    public function execute(UthengaTieBookingRequest $request, string $userId): UthengaTieBookingResult
    {
        $existing = $this->repo()->byKey($userId, $request->idempotencyKey); if ($existing !== null) return new UthengaTieBookingResult($this->result($existing));
        $execution = $this->prepare($request, $userId); $this->repo()->create($execution); $this->finalValidate($execution);
        if (!UthengaTieConfig::boolean('TIE_BOOKING_LEGACY_IMMEDIATE_CAPTURE_ENABLED')) { $execution['state'] = UthengaTieBookingState::FAILED; $execution['diagnostics']['failure'] = 'PAYMENT_HANDOFF_REQUIRED'; $this->repo()->save($execution); return new UthengaTieBookingResult($this->result($execution)); }
        $execution['state'] = UthengaTieBookingState::BOOKING; $this->repo()->save($execution); $plan = $this->plans->view($request->planId, $userId)->toArray(); $successful = []; $failed = [];
        foreach ($plan['activities'] as $activity) {
            $operation = ['activity_id' => $activity['activity_id'], 'service_id' => $activity['service_id'], 'booking_id' => null, 'status' => 'BOOKING', 'attempt_count' => 1, 'provider' => $this->provider->name(), 'diagnostics' => []]; $this->repo()->operation($execution['execution_id'], $operation);
            try { $response = $this->provider->create($this->providerPayload($activity, $plan, $request)); if (($response['success'] ?? false) !== true) throw new RuntimeException('Marketplace booking rejected.'); $operation['booking_id'] = (string) ($response['booking']['booking_id'] ?? $response['booking']['id'] ?? ''); if ($operation['booking_id'] === '') throw new RuntimeException('Marketplace booking returned no ID.'); $operation['status'] = 'BOOKED'; $operation['diagnostics'] = ['marketplace_status' => 'success']; $successful[] = $operation; } catch (Throwable $error) { $operation['status'] = 'FAILED'; $operation['diagnostics'] = ['error_type' => $error instanceof UthengaTieException ? $error->type() : 'booking_failed']; $failed[] = $operation; } $this->repo()->operation($execution['execution_id'], $operation);
            if ($failed !== [] && in_array($execution['rollback_policy'], ['stop', 'rollback', 'manual_review'], true)) break;
        }
        if ($failed === []) { $execution['state'] = UthengaTieBookingState::BOOKED; $execution['journey_state'] = ['status' => 'READY', 'booked_operations' => count($successful)]; }
        elseif ($successful === []) $execution['state'] = UthengaTieBookingState::FAILED;
        else { $execution['state'] = UthengaTieBookingState::PARTIALLY_BOOKED; if ($execution['rollback_policy'] === 'rollback') $this->rollback($execution, $successful); else $execution['journey_state'] = ['status' => 'MANUAL_REVIEW_REQUIRED', 'booked_operations' => count($successful), 'failed_operations' => count($failed)]; }
        $execution['diagnostics']['completed_at'] = gmdate('c'); $execution['diagnostics']['successful_operations'] = count($successful); $execution['diagnostics']['failed_operations'] = count($failed); $this->repo()->save($execution); return new UthengaTieBookingResult($this->result($execution));
    }
    public function cancel(string $executionId, string $userId): UthengaTieBookingResult { $execution = $this->find($executionId, $userId); $successful = array_filter($this->repo()->operations($executionId), static fn(array $op): bool => $op['status'] === 'BOOKED' && !empty($op['booking_id'])); $this->rollback($execution, $successful); $this->repo()->save($execution); return new UthengaTieBookingResult($this->result($execution)); }
    public function status(string $executionId, string $userId): UthengaTieBookingResult { return new UthengaTieBookingResult($this->result($this->find($executionId, $userId))); }
    private function prepare(UthengaTieBookingRequest $request, string $userId): array { $plan = $this->plans->view($request->planId, $userId)->toArray(); if (($plan['lifecycle'] ?? '') !== UthengaTiePlanLifecycle::APPROVED) throw UthengaTieErrors::validation(['plan' => 'Only an approved trip plan may be booked.']); return ['execution_id' => $this->uuid(), 'plan_id' => $request->planId, 'user_id' => $userId, 'idempotency_key' => $request->idempotencyKey, 'state' => UthengaTieBookingState::PENDING, 'rollback_policy' => strtolower(UthengaTieConfig::string('TIE_BOOKING_ROLLBACK_POLICY', 'manual_review')), 'payment_reference_hash' => $request->paymentReference === null ? null : hash('sha256', $request->paymentReference), 'journey_state' => ['status' => 'NOT_READY'], 'diagnostics' => ['version' => UthengaTieConfig::string('TIE_BOOKING_VERSION', 'booking-orchestration/v1'), 'provider' => $this->provider->name(), 'plan_lifecycle' => $plan['lifecycle'], 'payment_reference_present' => $request->paymentReference !== null]]; }
    private function finalValidate(array &$execution): void { $execution['state'] = UthengaTieBookingState::VALIDATING; $plan = $this->plans->validate($execution['plan_id'], $execution['user_id'])->toArray(); $blocking = (int) ($plan['diagnostics']['validation']['blocking_conflicts'] ?? 0); if ($blocking > 0 || ($plan['lifecycle'] ?? '') !== UthengaTiePlanLifecycle::APPROVED) { $execution['state'] = UthengaTieBookingState::FAILED; $execution['diagnostics']['validation'] = ['status' => 'failed', 'blocking_conflicts' => $blocking]; if ($this->repository !== null && $this->repo()->byId($execution['execution_id'], $execution['user_id']) !== null) $this->repo()->save($execution); throw UthengaTieErrors::validation(['plan' => 'The approved plan no longer passes final availability validation.']); } $execution['diagnostics']['validation'] = ['status' => 'passed', 'at' => gmdate('c')]; }
    private function providerPayload(array $activity, array $plan, UthengaTieBookingRequest $request): array { $type = $activity['category'] === 'property' ? 'accommodation' : $activity['category']; $trip = $plan['trip_summary']; $payload = ['listing_id' => $activity['service_id'], 'listing_type' => $type, 'gateway' => $request->gateway, 'quantity' => max(1, (int) $trip['travellers'])]; if ($type === 'accommodation') { $payload['check_in_date'] = $trip['start_date']; $payload['check_out_date'] = $trip['end_date']; } elseif ($type === 'tour') $payload['tour_date'] = substr($activity['start_at'], 0, 10); return $payload; }
    private function rollback(array &$execution, array $operations): void { $all = true; foreach ($operations as $operation) try { $response = $this->provider->cancel((string) $operation['booking_id']); if (($response['success'] ?? false) !== true) $all = false; else { $operation['status'] = 'ROLLED_BACK'; $operation['diagnostics']['rollback'] = 'cancelled_via_marketplace'; $this->repo()->operation($execution['execution_id'], $operation); } } catch (Throwable $error) { $all = false; } $execution['state'] = $all ? UthengaTieBookingState::ROLLED_BACK : UthengaTieBookingState::PARTIALLY_BOOKED; $execution['journey_state'] = ['status' => $all ? 'ROLLED_BACK' : 'MANUAL_REVIEW_REQUIRED']; }
    private function result(array $execution): array { return ['schema_version' => UthengaTieBookingResult::SCHEMA_VERSION, 'execution_id' => $execution['execution_id'], 'plan_id' => $execution['plan_id'], 'status' => $execution['state'], 'bookings' => $this->repo()->operations($execution['execution_id']), 'warnings' => $execution['state'] === UthengaTieBookingState::PARTIALLY_BOOKED ? ['One or more services require manual resolution.'] : [], 'diagnostics' => $execution['diagnostics'], 'provenance' => ['plan' => 'trip-plan-result/v1', 'marketplace_booking' => 'existing_request_api', 'payment_processing' => 'outside_tie']]; }
    private function find(string $id, string $userId): array { $execution = $this->repo()->byId($id, $userId); if ($execution === null) throw new UthengaTieException('not_found', 'The requested booking execution was not found.', 404); return $execution; }
    private function repo(): UthengaTieBookingRepository { if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database'); return $this->repository; }
    private function uuid(): string { $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)); }
}
