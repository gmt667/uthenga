<?php
/**
 * Phases 15/16: payment intent and verified-checkout boundary.
 *
 * This module is intentionally separate from legacy transactions and bookings.
 * It never treats a redirect or webhook payload as payment proof: provider
 * verification and exact quote comparison are mandatory.
 */

final class UthengaTiePaymentIntentRequest
{
    public string $planId; public string $idempotencyKey; public array $selections;
    public function __construct(string $planId, string $idempotencyKey, array $selections) { $this->planId = $planId; $this->idempotencyKey = $idempotencyKey; $this->selections = $selections; }
}

final class UthengaTiePaymentIntentResult
{
    public const SCHEMA_VERSION = 'payment-intent/v1'; public array $data;
    public function __construct(array $data) { $this->data = $data; } public function toArray(): array { return $this->data; }
}

final class UthengaTiePaymentContracts
{
    private const FIELDS = ['plan_id', 'idempotency_key', 'selections', 'csrf_token'];
    public static function start(array $input): UthengaTiePaymentIntentRequest
    {
        $unknown = array_values(array_diff(array_keys($input), self::FIELDS));
        if ($unknown !== []) throw UthengaTieErrors::validation(['request' => 'Unsupported payment field(s): ' . implode(', ', $unknown) . '.']);
        $planId = UthengaTiePlanContracts::planId($input); $key = trim((string) ($input['idempotency_key'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._-]{16,128}$/', $key)) throw UthengaTieErrors::validation(['idempotency_key' => 'Use a unique 16-128 character idempotency key.']);
        return new UthengaTiePaymentIntentRequest($planId, $key, UthengaTieInventorySelectionContracts::selections($input['selections'] ?? null));
    }
    public static function intentId(array $input): string
    {
        $id = trim((string) ($input['payment_intent_id'] ?? ''));
        if (!preg_match('/^[a-f0-9-]{36}$/i', $id)) throw UthengaTieErrors::validation(['payment_intent_id' => 'A valid payment intent ID is required.']);
        return $id;
    }
}

final class UthengaTiePaymentState
{
    public const QUOTED = 'QUOTED'; public const HOLD_ACQUIRED = 'HOLD_ACQUIRED'; public const CHECKOUT_READY = 'CHECKOUT_READY'; public const PAYMENT_PENDING = 'PAYMENT_PENDING'; public const VERIFYING = 'VERIFYING'; public const VERIFIED = 'VERIFIED'; public const BOOKING_PENDING = 'BOOKING_PENDING'; public const BOOKED = 'BOOKED'; public const FAILED = 'FAILED'; public const CANCELLED = 'CANCELLED'; public const REFUND_REQUIRED = 'REFUND_REQUIRED'; public const REFUNDED = 'REFUNDED'; public const MANUAL_REVIEW = 'MANUAL_REVIEW';
    public static function transition(string $from, string $to): bool
    {
        $map = [self::QUOTED => [self::HOLD_ACQUIRED, self::FAILED, self::CANCELLED], self::HOLD_ACQUIRED => [self::CHECKOUT_READY, self::FAILED, self::CANCELLED], self::CHECKOUT_READY => [self::VERIFYING, self::PAYMENT_PENDING, self::FAILED, self::CANCELLED], self::PAYMENT_PENDING => [self::VERIFYING, self::VERIFIED, self::FAILED, self::CANCELLED, self::MANUAL_REVIEW], self::VERIFYING => [self::PAYMENT_PENDING, self::VERIFIED, self::FAILED, self::CANCELLED, self::MANUAL_REVIEW], self::VERIFIED => [self::BOOKING_PENDING, self::REFUND_REQUIRED, self::MANUAL_REVIEW], self::BOOKING_PENDING => [self::BOOKED, self::REFUND_REQUIRED, self::MANUAL_REVIEW], self::BOOKED => [self::REFUND_REQUIRED, self::MANUAL_REVIEW], self::REFUND_REQUIRED => [self::REFUNDED, self::MANUAL_REVIEW], self::FAILED => [], self::CANCELLED => [], self::REFUNDED => [], self::MANUAL_REVIEW => [self::VERIFYING]];
        return in_array($to, $map[$from] ?? [], true);
    }
}

interface UthengaTiePaymentGateway
{
    public function name(): string;
    public function checkout(array $intent): array;
    public function verify(string $transactionReference): array;
    public function verifyWebhookSignature(string $payload, string $signature): bool;
    /** Real, live PayChangu mobile-money operators (GET /mobile-money) — never a hardcoded list. */
    public function listMobileMoneyOperators(): array;
    public function chargeMobileMoney(array $params): array;
    public function verifyMobileMoneyCharge(string $chargeId): array;
    /** Transient, amount-specific virtual account for one charge (used at purchase time). */
    public function chargeBankTransfer(array $params): array;
    /** Persistent virtual account tied to the customer, reusable across future charges. */
    public function provisionPermanentBankAccount(array $params): array;
    /** Real, live PayChangu payout bank directory (includes Airtel Money/TNM Mpamba as payout destinations) — never hardcoded. */
    public function listSupportedPayoutBanks(): array;
}

final class UthengaTieUnavailableInventoryHoldProvider implements UthengaTieInventoryHoldProvider
{
    public function quote(array $plan, array $selections): array { throw UthengaTieErrors::providerUnavailable('inventory_hold_provider'); }
    public function acquire(array $plan, array $quote): array { throw UthengaTieErrors::providerUnavailable('inventory_hold_provider'); }
    public function release(string $holdId): void {}
    public function consume(string $holdId, string $paymentIntentId, ?string $bookingId = null): void { throw UthengaTieErrors::providerUnavailable('inventory_hold_provider'); }
}

final class UthengaTieUnavailablePaymentGateway implements UthengaTiePaymentGateway
{
    public function __construct(private string $name = 'unconfigured') {}
    public function name(): string { return $this->name; }
    public function checkout(array $intent): array { throw UthengaTieErrors::providerUnavailable($this->name); }
    public function verify(string $transactionReference): array { throw UthengaTieErrors::providerUnavailable($this->name); }
    public function verifyWebhookSignature(string $payload, string $signature): bool { return false; }
    public function listMobileMoneyOperators(): array { throw UthengaTieErrors::providerUnavailable($this->name); }
    public function chargeMobileMoney(array $params): array { throw UthengaTieErrors::providerUnavailable($this->name); }
    public function verifyMobileMoneyCharge(string $chargeId): array { throw UthengaTieErrors::providerUnavailable($this->name); }
    public function chargeBankTransfer(array $params): array { throw UthengaTieErrors::providerUnavailable($this->name); }
    public function provisionPermanentBankAccount(array $params): array { throw UthengaTieErrors::providerUnavailable($this->name); }
    public function listSupportedPayoutBanks(): array { throw UthengaTieErrors::providerUnavailable($this->name); }
}

final class UthengaTiePaychanguGateway implements UthengaTiePaymentGateway
{
    private string $secret; private string $webhookSecret; private string $baseUrl;
    public function __construct(string $secret, string $webhookSecret, string $baseUrl = 'https://api.paychangu.com') { $this->secret = $secret; $this->webhookSecret = $webhookSecret; $this->baseUrl = rtrim($baseUrl, '/'); }
    public function name(): string { return 'paychangu'; }
    public function checkout(array $intent): array
    {
        if ($this->secret === '' || !function_exists('curl_init')) throw UthengaTieErrors::providerUnavailable('paychangu');
        $payload = ['amount' => $intent['amount'], 'currency' => $intent['currency'], 'tx_ref' => $intent['provider_tx_ref'], 'callback_url' => $this->callbackUrl((string) $intent['id']), 'return_url' => $this->returnUrl((string) $intent['id']), 'customization' => ['title' => APP_NAME, 'description' => 'Uthenga trip payment'], 'meta' => ['payment_intent_id' => $intent['id'], 'quote_hash' => $intent['quote_hash']]];
        if ($payload['callback_url'] === '' || $payload['return_url'] === '') throw UthengaTieErrors::validation(['payment' => 'PayChangu public webhook and return URLs must be configured.']);
        $response = $this->request('POST', '/payment', $payload); $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $url = trim((string) ($response['checkout_url'] ?? $data['checkout_url'] ?? $data['link'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) throw UthengaTieErrors::providerUnavailable('paychangu_checkout');
        return ['checkout_url' => $url, 'provider_reference' => (string) ($data['data']['tx_ref'] ?? $data['tx_ref'] ?? $intent['provider_tx_ref']), 'status' => 'pending'];
    }
    public function verify(string $transactionReference): array
    {
        $response = $this->request('GET', '/verify-payment/' . rawurlencode($transactionReference)); $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $authorization=is_array($data['authorization']??null)?$data['authorization']:[];
        return ['reference' => (string) ($data['tx_ref'] ?? $transactionReference), 'status' => strtolower((string) ($data['status'] ?? 'pending')), 'amount' => is_numeric($data['amount'] ?? null) ? (float) $data['amount'] : null, 'currency' => (string) ($data['currency'] ?? ''), 'provider_reference' => (string) ($data['reference'] ?? ''), 'mode' => (string) ($data['mode'] ?? ''), 'event_type'=>(string)($data['event_type']??''), 'channel'=>(string)($authorization['channel']??'')];
    }
    public function verifyWebhookSignature(string $payload, string $signature): bool { return $this->webhookSecret !== '' && $signature !== '' && hash_equals(hash_hmac('sha256', $payload, $this->webhookSecret), $signature); }
    public function listMobileMoneyOperators(): array
    {
        $response = $this->request('GET', '/mobile-money');
        $operators = is_array($response['data'] ?? null) ? $response['data'] : [];
        $out = [];
        foreach ($operators as $operator) $out[] = ['ref_id' => (string) ($operator['ref_id'] ?? ''), 'name' => (string) ($operator['name'] ?? ''), 'short_code' => (string) ($operator['short_code'] ?? '')];
        return array_values(array_filter($out, fn ($o) => $o['ref_id'] !== '' && $o['name'] !== ''));
    }
    public function chargeMobileMoney(array $params): array
    {
        $payload = ['mobile' => $params['mobile'], 'mobile_money_operator_ref_id' => $params['operator_ref_id'], 'amount' => (string) $params['amount'], 'charge_id' => $params['charge_id'], 'email' => $params['email'] ?? '', 'first_name' => $params['first_name'] ?? '', 'last_name' => $params['last_name'] ?? ''];
        $response = $this->requestExpectingBody('POST', '/mobile-money/payments/initialize', $payload);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        if (strtolower((string) ($response['status'] ?? '')) !== 'success' || (string) ($data['charge_id'] ?? '') === '') throw UthengaTieErrors::validation(['payment' => $this->errorMessage($response, 'Mobile money charge could not be started.')]);
        return ['charge_id' => (string) $data['charge_id'], 'reference' => (string) ($data['ref_id'] ?? ''), 'status' => strtolower((string) ($data['status'] ?? 'pending')), 'amount' => is_numeric($data['amount'] ?? null) ? (float) $data['amount'] : null];
    }
    public function verifyMobileMoneyCharge(string $chargeId): array
    {
        $response = $this->request('GET', '/mobile-money/payments/' . rawurlencode($chargeId) . '/verify');
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        return ['reference' => (string) ($data['ref_id'] ?? $chargeId), 'status' => strtolower((string) ($data['status'] ?? 'pending')), 'amount' => is_numeric($data['amount'] ?? null) ? (float) $data['amount'] : null];
    }
    public function chargeBankTransfer(array $params): array
    {
        $payload = ['amount' => (string) $params['amount'], 'currency' => $params['currency'], 'payment_method' => 'mobile_bank_transfer', 'charge_id' => $params['charge_id'], 'email' => $params['email'] ?? '', 'first_name' => $params['first_name'] ?? '', 'last_name' => $params['last_name'] ?? ''];
        $response = $this->requestExpectingBody('POST', '/direct-charge/payments/initialize', $payload);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $account = is_array($data['payment_account_details'] ?? null) ? $data['payment_account_details'] : [];
        $transaction = is_array($data['transaction'] ?? null) ? $data['transaction'] : [];
        if (strtolower((string) ($response['status'] ?? '')) !== 'success' || (string) ($transaction['charge_id'] ?? '') === '') throw UthengaTieErrors::validation(['payment' => $this->errorMessage($response, 'Bank transfer could not be started.')]);
        return ['charge_id' => (string) $transaction['charge_id'], 'reference' => (string) ($transaction['ref_id'] ?? ''), 'status' => strtolower((string) ($transaction['status'] ?? 'pending')), 'bank_name' => (string) ($account['bank_name'] ?? ''), 'account_number' => (string) ($account['account_number'] ?? ''), 'account_name' => (string) ($account['account_name'] ?? ''), 'expires_at' => isset($account['account_expiration_timestamp']) ? (int) $account['account_expiration_timestamp'] : null];
    }
    public function provisionPermanentBankAccount(array $params): array
    {
        $payload = ['amount' => (string) ($params['amount'] ?? '1000'), 'currency' => $params['currency'], 'payment_method' => 'mobile_bank_transfer', 'charge_id' => $params['charge_id'], 'create_permanent_account' => true, 'email' => $params['email'] ?? '', 'first_name' => $params['first_name'] ?? '', 'last_name' => $params['last_name'] ?? ''];
        $response = $this->requestExpectingBody('POST', '/direct-charge/payments/initialize', $payload);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $account = is_array($data['payment_account_details'] ?? null) ? $data['payment_account_details'] : [];
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        if (strtolower((string) ($response['status'] ?? '')) !== 'success' || (string) ($account['account_number'] ?? '') === '') throw UthengaTieErrors::validation(['payment' => $this->errorMessage($response, 'A bank account could not be provisioned.')]);
        return ['bank_name' => (string) ($account['bank_name'] ?? ''), 'account_number' => (string) $account['account_number'], 'account_name' => (string) ($account['account_name'] ?? ''), 'customer_reference' => (string) ($customer['customer_ref'] ?? '')];
    }
    public function listSupportedPayoutBanks(): array
    {
        $response = $this->request('GET', '/direct-charge/payouts/supported-banks?currency=' . rawurlencode(APP_CURRENCY));
        $banks = is_array($response['data'] ?? null) ? $response['data'] : [];
        $out = [];
        foreach ($banks as $bank) $out[] = ['uuid' => (string) ($bank['uuid'] ?? ''), 'name' => (string) ($bank['name'] ?? '')];
        return array_values(array_filter($out, fn ($b) => $b['uuid'] !== '' && $b['name'] !== ''));
    }
    /** Keep the browser return correlated to its server-owned payment intent. */
    private function returnUrl(string $paymentIntentId): string
    {
        return $this->appendPaymentIntent(UthengaTieConfig::string('TIE_PAYCHANGU_RETURN_URL'), $paymentIntentId);
    }
    /** PayChangu can return a browser to callback_url after checkout. */
    private function callbackUrl(string $paymentIntentId): string
    {
        return $this->appendPaymentIntent(UthengaTieConfig::string('TIE_PAYCHANGU_WEBHOOK_URL'), $paymentIntentId);
    }
    private function appendPaymentIntent(string $url, string $paymentIntentId): string
    {
        if ($url === '') return '';
        return $url . (str_contains($url, '?') ? '&' : '?') . 'payment_intent_id=' . rawurlencode($paymentIntentId);
    }
    private function request(string $method, string $path, ?array $body = null): array
    {
        $handle = curl_init($this->baseUrl . $path); $headers = ['Accept: application/json', 'Authorization: Bearer ' . $this->secret];
        if ($method === 'POST') $headers[] = 'Content-Type: application/json';
        curl_setopt_array($handle, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(1, UthengaTieConfig::integer('TIE_PAYCHANGU_TIMEOUT', 20)), CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES)]);
        $raw = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); curl_close($handle); $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($decoded)) throw UthengaTieErrors::providerUnavailable('paychangu'); return $decoded;
    }
    /**
     * Direct-charge endpoints return real, customer-facing validation errors
     * (e.g. "Enter a valid mobile number") as an ordinary 4xx JSON body —
     * unlike checkout()/verify(), a 4xx here must not be swallowed into a
     * generic "provider unavailable", or the customer never learns why their
     * charge was rejected. Only a genuinely malformed/non-JSON response or a
     * 5xx is treated as provider unavailability.
     */
    /** PayChangu's error "message" is sometimes a plain string, sometimes {field: [errors]} — flatten either into one readable sentence. */
    private function errorMessage(array $response, string $fallback): string
    {
        $message = $response['message'] ?? null;
        if (is_string($message) && $message !== '') return $message;
        if (is_array($message)) {
            $parts = [];
            array_walk_recursive($message, function ($value) use (&$parts) { if (is_string($value)) $parts[] = $value; });
            if ($parts !== []) return implode(' ', $parts);
        }
        return $fallback;
    }
    private function requestExpectingBody(string $method, string $path, ?array $body = null): array
    {
        $handle = curl_init($this->baseUrl . $path); $headers = ['Accept: application/json', 'Authorization: Bearer ' . $this->secret];
        if ($method === 'POST') $headers[] = 'Content-Type: application/json';
        curl_setopt_array($handle, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(1, UthengaTieConfig::integer('TIE_PAYCHANGU_TIMEOUT', 20)), CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES)]);
        $raw = curl_exec($handle); $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE); curl_close($handle); $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) throw UthengaTieErrors::providerUnavailable('paychangu');
        if ($status >= 500) throw UthengaTieErrors::providerUnavailable('paychangu');
        return $decoded;
    }
}

final class UthengaTiePaychanguGatewayFactory
{
    public static function configured(): UthengaTiePaymentGateway
    {
        if (!UthengaTieConfig::boolean('TIE_PAYCHANGU_ENABLED')) return new UthengaTieUnavailablePaymentGateway('paychangu_disabled');
        $secret = UthengaTieConfig::string('TIE_PAYCHANGU_SECRET_KEY'); $webhook = UthengaTieConfig::string('TIE_PAYCHANGU_WEBHOOK_SECRET');
        if ($secret === '' || $webhook === '') return new UthengaTieUnavailablePaymentGateway('paychangu_unconfigured');
        return new UthengaTiePaychanguGateway($secret, $webhook, UthengaTieConfig::string('TIE_PAYCHANGU_API_BASE_URL', 'https://api.paychangu.com'));
    }
}

final class UthengaTiePaymentRepository
{
    public function __construct(private PDO $db) {}
    public function byKey(string $userId, string $key): ?array { $stmt = $this->db->prepare('SELECT * FROM tie_payment_intents WHERE user_id = ? AND idempotency_key = ? LIMIT 1'); $stmt->execute([$userId, $key]); return $this->hydrate($stmt->fetch()); }
    public function byId(string $id, string $userId): ?array { $stmt = $this->db->prepare('SELECT * FROM tie_payment_intents WHERE id = ? AND user_id = ? LIMIT 1'); $stmt->execute([$id, $userId]); return $this->hydrate($stmt->fetch()); }
    public function byReference(string $reference): ?array { $stmt = $this->db->prepare('SELECT * FROM tie_payment_intents WHERE provider_tx_ref = ? LIMIT 1'); $stmt->execute([$reference]); return $this->hydrate($stmt->fetch()); }
    public function byIdUnscoped(string $id): ?array { $stmt = $this->db->prepare('SELECT * FROM tie_payment_intents WHERE id = ? LIMIT 1'); $stmt->execute([$id]); return $this->hydrate($stmt->fetch()); }
    public function reconciliationCandidates(int $limit): array
    {
        $sql = "SELECT * FROM tie_payment_intents
                WHERE state IN ('CHECKOUT_READY','PAYMENT_PENDING')
                   OR (state='VERIFYING' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))
                ORDER BY updated_at ASC LIMIT ?";
        $stmt = $this->db->prepare($sql); $stmt->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT); $stmt->execute();
        return array_values(array_filter(array_map(fn($row) => $this->hydrate($row), $stmt->fetchAll())));
    }
    public function claimForVerification(string $id): ?array
    {
        $stmt = $this->db->prepare("UPDATE tie_payment_intents SET state='VERIFYING', updated_at=UTC_TIMESTAMP()
            WHERE id=? AND (state IN ('CHECKOUT_READY','PAYMENT_PENDING','MANUAL_REVIEW') OR (state='VERIFYING' AND updated_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)))");
        $stmt->execute([$id]); return $stmt->rowCount() === 1 ? $this->byIdUnscoped($id) : null;
    }
    public function create(array $intent): void { $stmt = $this->db->prepare('INSERT INTO tie_payment_intents (id, plan_id, user_id, idempotency_key, provider_name, provider_tx_ref, state, amount, currency, quote_hash, quote_snapshot, inventory_hold_id, checkout_url, diagnostics, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'); $stmt->execute([$intent['id'], $intent['plan_id'], $intent['user_id'], $intent['idempotency_key'], $intent['provider_name'], $intent['provider_tx_ref'], $intent['state'], $intent['amount'], $intent['currency'], $intent['quote_hash'], json_encode($intent['quote_snapshot']), $intent['inventory_hold_id'], $intent['checkout_url'], json_encode($intent['diagnostics']), $intent['expires_at']]); }
    public function save(array $intent): void { $stmt = $this->db->prepare('UPDATE tie_payment_intents SET state = ?, checkout_url = ?, provider_reference_hash = ?, verification = ?, diagnostics = ?, updated_at = NOW() WHERE id = ?'); $stmt->execute([$intent['state'], $intent['checkout_url'], $intent['provider_reference_hash'], json_encode($intent['verification']), json_encode($intent['diagnostics']), $intent['id']]); }
    /** Returns false for a duplicate provider event without exposing the payload. */
    public function recordEvent(string $intentId, string $eventKey, string $eventType, string $payloadHash): bool { try { $stmt = $this->db->prepare('INSERT INTO tie_payment_events (payment_intent_id, event_key, event_type, payload_hash, processing_status) VALUES (?, ?, ?, ?, ?)'); $stmt->execute([$intentId, $eventKey, $eventType, $payloadHash, 'received']); return true; } catch (PDOException $error) { if ($error->getCode() === '23000') return false; throw $error; } }
    public function completeEvent(string $eventKey, string $status): void { $this->db->prepare('UPDATE tie_payment_events SET processing_status=?, processed_at=UTC_TIMESTAMP() WHERE event_key=?')->execute([substr($status,0,30),$eventKey]); }
    public function recordReconciliation(string $intentId, string $source, string $status, ?string $errorCode, float $durationMs): void
    {
        $this->db->prepare('INSERT INTO tie_payment_reconciliation_attempts (payment_intent_id,source_name,result_status,error_code,duration_ms) VALUES (?,?,?,?,?)')
            ->execute([$intentId,substr($source,0,30),substr($status,0,30),$errorCode===null?null:substr($errorCode,0,80),round($durationMs,2)]);
    }
    private function hydrate($row): ?array { if (!is_array($row)) return null; foreach (['quote_snapshot', 'verification', 'diagnostics'] as $field) $row[$field] = json_decode((string) ($row[$field] ?? '{}'), true) ?: []; $row['amount'] = (float) $row['amount']; return $row; }
}

final class UthengaTiePaymentService implements UthengaTiePaymentModule
{
    private ?UthengaTiePaymentRepository $repository; private ?PDO $db;
    public function __construct(?PDO $db, private UthengaTiePlanModule $plans, private UthengaTieBudgetModule $budget, private UthengaTiePaymentGateway $gateway, private UthengaTieInventoryHoldProvider $holds, private UthengaTieBookingCommitProvider $commit) { $this->db = $db; $this->repository = $db instanceof PDO ? new UthengaTiePaymentRepository($db) : null; }
    /** Published options for the approved plan only; prices are re-quoted at start. */
    public function options(string $planId, string $userId): array
    {
        if (!UthengaTieFeatureFlags::enabled('payments')) throw UthengaTieErrors::featureDisabled('payments');
        if (!($this->db instanceof PDO)) throw UthengaTieErrors::providerUnavailable('uthenga_database');
        $plan = $this->plans->view($planId, $userId)->toArray();
        if (($plan['lifecycle'] ?? '') !== UthengaTiePlanLifecycle::APPROVED) throw UthengaTieErrors::validation(['plan' => 'Approve and revalidate this plan before selecting payment options.']);
        $map = ['event' => ['ticket_type', 'ticket_types', 'name', 'price', 'remaining_quantity'], 'transport' => ['seat_class', 'seat_classes', 'class_name', 'price', 'remaining_seats'], 'accommodation' => ['room_type', 'room_types', 'room_name', 'price_per_night', 'available_rooms']];
        $activities = [];
        foreach (($plan['activities'] ?? []) as $activity) {
            $category = (string) ($activity['category'] ?? ''); $serviceId = (string) ($activity['service_id'] ?? '');
            $entry = ['service_id' => $serviceId, 'title' => (string) ($activity['title'] ?? 'Uthenga service'), 'category' => $category, 'resource_type' => null, 'options' => [], 'reason' => null];
            if (!isset($map[$category])) { $entry['reason'] = 'This service category does not yet have an authoritative payment inventory provider.'; $activities[] = $entry; continue; }
            [$type, $table, $name, $price, $remaining] = $map[$category];
            if ($category === 'accommodation' && UthengaTieFeatureFlags::enabled('accommodation_v2')) {
                $stmt=$this->db->prepare('SELECT id,room_name FROM room_types WHERE listing_id=? AND is_active=1 ORDER BY sort_order,id');$stmt->execute([$serviceId]);$trip=$plan['trip_summary']??[];
                foreach($stmt->fetchAll() as $row){try{$quoted=(new UthengaAccommodationService($this->db))->quoteListing($serviceId,(int)$row['id'],1,(string)($trip['start_date']??''),(string)($trip['end_date']??''));$entry['options'][]=['resource_type'=>'room_type','resource_id'=>(int)$row['id'],'name'=>(string)$row['room_name'],'price'=>round((float)$quoted['total']/max(1,(int)$quoted['nights']),2),'stay_total'=>(float)$quoted['total'],'payable_now'=>(float)$quoted['deposit_required'],'remaining'=>min(array_column($quoted['nightly'],'sellable')),'rate_plan_id'=>(string)$quoted['rate_plan']['id'],'payment_mode'=>(string)$quoted['rate_plan']['payment_mode']];}catch(UthengaTieException $ignore){}}
                if($entry['options']===[])$entry['reason']='No room type is available for every requested night.';$entry['resource_type']='room_type';$activities[]=$entry;continue;
            }
            $sql = "SELECT id, $name AS name, $price AS price, $remaining AS remaining FROM $table WHERE listing_id=? AND is_active=1 AND $remaining > 0 ORDER BY sort_order, id";
            $stmt = $this->db->prepare($sql); $stmt->execute([$serviceId]);
            foreach ($stmt->fetchAll() as $row) $entry['options'][] = ['resource_type' => $type, 'resource_id' => (int) $row['id'], 'name' => (string) $row['name'], 'price' => (float) $row['price'], 'remaining' => (int) $row['remaining']];
            if ($entry['options'] === []) $entry['reason'] = 'No active, payable inventory option is currently published for this service.';
            $entry['resource_type'] = $type; $activities[] = $entry;
        }
        return ['schema_version' => 'payment-options/v1', 'plan_id' => $planId, 'currency' => (string) (($plan['trip_summary']['currency'] ?? APP_CURRENCY)), 'activities' => $activities, 'provenance' => ['options' => 'published_inventory', 'quote' => 'recalculated_at_payment_start']];
    }
    public function start(UthengaTiePaymentIntentRequest $request, string $userId): UthengaTiePaymentIntentResult
    {
        if (!UthengaTieFeatureFlags::enabled('payments') || !UthengaTieConfig::boolean('TIE_PAYMENT_COMMIT_ENABLED')) throw UthengaTieErrors::featureDisabled('payments');
        $existing = $this->repo()->byKey($userId, $request->idempotencyKey); if ($existing !== null) return new UthengaTiePaymentIntentResult($this->public($existing));
        $plan = $this->plans->validate($request->planId, $userId)->toArray(); $plan['user_id'] = $userId;
        if (($plan['lifecycle'] ?? '') !== UthengaTiePlanLifecycle::APPROVED) throw UthengaTieErrors::validation(['plan' => 'Only an approved, currently valid plan can enter payment.']);
        $quote = $this->quote($plan, $request->selections); $hold = $this->holds->acquire($plan, $quote);
        $intent = ['id' => $this->uuid(), 'plan_id' => $request->planId, 'user_id' => $userId, 'idempotency_key' => $request->idempotencyKey, 'provider_name' => $this->gateway->name(), 'provider_tx_ref' => 'TIEPAY-' . strtoupper(bin2hex(random_bytes(10))), 'state' => UthengaTiePaymentState::HOLD_ACQUIRED, 'amount' => $quote['amount'], 'currency' => $quote['currency'], 'quote_hash' => $quote['hash'], 'quote_snapshot' => $quote, 'inventory_hold_id' => (string) ($hold['hold_id'] ?? ''), 'checkout_url' => null, 'provider_reference_hash' => null, 'verification' => [], 'diagnostics' => ['payment_version' => 'payment-intent/v1', 'inventory_hold' => 'acquired', 'hold_ids' => $hold['hold_ids'] ?? [], 'hold_resources' => $hold['resources'] ?? []], 'expires_at' => $hold['expires_at'] ?? gmdate('Y-m-d H:i:s', time() + 900)];
        if ($intent['inventory_hold_id'] === '') throw UthengaTieErrors::providerUnavailable('inventory_hold_provider');
        // Persist the generated provider reference before calling the provider so a
        // callback can always be correlated, even if the client disconnects.
        $this->repo()->create($intent);
        try {
            $checkout = $this->gateway->checkout($intent); $intent['state'] = UthengaTiePaymentState::CHECKOUT_READY; $intent['checkout_url'] = $checkout['checkout_url'];
            if (!empty($checkout['provider_reference']) && (string) $checkout['provider_reference'] !== $intent['provider_tx_ref']) throw UthengaTieErrors::validation(['payment' => 'PayChangu returned an unexpected transaction reference.']);
            $this->repo()->save($intent);
        } catch (Throwable $error) {
            $intent['state'] = UthengaTiePaymentState::FAILED; $intent['diagnostics']['checkout'] = 'provider_initialization_failed'; $this->repo()->save($intent); $this->holds->release($intent['inventory_hold_id']); throw $error;
        }
        return new UthengaTiePaymentIntentResult($this->public($intent));
    }
    public function status(string $intentId, string $userId): UthengaTiePaymentIntentResult { $intent = $this->repo()->byId($intentId, $userId); if ($intent === null) throw new UthengaTieException('not_found', 'The requested payment intent was not found.', 404); return new UthengaTiePaymentIntentResult($this->public($intent)); }
    public function receiveWebhook(string $payload, string $signature): array
    {
        if (strlen($payload) > 65536 || !$this->gateway->verifyWebhookSignature($payload, $signature)) throw UthengaTieErrors::authorization();
        if (!uthenga_financial_callback_commit_allowed()) { uthenga_financial_callback_block('tie_payment_webhook_service'); throw UthengaTieErrors::providerUnavailable('financial_callback_controls'); }
        $decoded = json_decode($payload, true); if (!is_array($decoded)) throw UthengaTieErrors::validation(['payload' => 'A JSON webhook payload is required.']); $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
        $reference = trim((string) ($data['tx_ref'] ?? $decoded['tx_ref'] ?? $data['reference'] ?? '')); if ($reference === '') throw UthengaTieErrors::validation(['payload' => 'PayChangu transaction reference is required.']);
        // PayChangu's dashboard test events do not correspond to a local payment
        // intent. A signed unknown event is acknowledged and ignored; it cannot
        // alter bookings or payment state.
        $intent = $this->repo()->byReference($reference); if ($intent === null) return ['ignored' => true, 'status' => 'ignored'];
        $payloadHash = hash('sha256', $payload); $eventKey = hash('sha256', $reference . ':' . $payloadHash); if (!$this->repo()->recordEvent($intent['id'], $eventKey, (string) ($decoded['event_type'] ?? $decoded['event'] ?? 'payment'), $payloadHash)) return ['duplicate' => true, 'payment_intent_id' => $intent['id']];
        $result = $this->reconcileIntent($intent['id'], 'webhook'); $this->repo()->completeEvent($eventKey, (string) ($result['status'] ?? 'processed')); return $result;
    }
    public function reconcilePending(int $limit = 25): array
    {
        if (!uthenga_financial_callback_commit_allowed()) { uthenga_financial_callback_block('tie_payment_poller'); throw UthengaTieErrors::providerUnavailable('financial_callback_controls'); }
        $summary = ['checked'=>0,'booked'=>0,'pending'=>0,'manual_review'=>0,'refund_required'=>0,'errors'=>0,'results'=>[]];
        foreach ($this->repo()->reconciliationCandidates($limit) as $candidate) {
            $summary['checked']++;
            try {
                $result = $this->reconcileIntent((string)$candidate['id'], 'poller'); $status = strtolower((string)($result['status']??'pending'));
                if ($status === 'booked') $summary['booked']++; elseif ($status === 'manual_review') $summary['manual_review']++; elseif ($status === 'refund_required') $summary['refund_required']++; else $summary['pending']++;
                $summary['results'][] = ['payment_intent_id'=>$candidate['id'],'status'=>$result['status']??'unknown'];
            } catch (Throwable $error) { $summary['errors']++; $summary['results'][]=['payment_intent_id'=>$candidate['id'],'status'=>'error','error_type'=>$error instanceof UthengaTieException?$error->type():'internal_error']; }
        }
        return ['schema_version'=>'payment-reconciliation/v1']+$summary;
    }
    private function reconcileIntent(string $intentId, string $source): array
    {
        $started=microtime(true);$claimed=$this->repo()->claimForVerification($intentId);
        if($claimed===null){$current=$this->repo()->byIdUnscoped($intentId);if($current===null)throw new UthengaTieException('not_found','Payment intent was not found.',404);return ['accepted'=>true,'payment_intent_id'=>$intentId,'status'=>$current['state'],'booking_effect'=>$current['state']===UthengaTiePaymentState::BOOKED?'already_created':'none'];}
        try{$verified=$this->gateway->verify((string)$claimed['provider_tx_ref']);}
        catch(Throwable $error){$claimed['state']=UthengaTiePaymentState::PAYMENT_PENDING;$claimed['diagnostics']['last_reconciliation']='provider_unavailable';$this->repo()->save($claimed);$this->repo()->recordReconciliation($intentId,$source,'provider_unavailable',$error instanceof UthengaTieException?$error->type():'provider_error',(microtime(true)-$started)*1000);throw $error;}
        $status=strtolower((string)($verified['status']??'pending'));$expected=(float)$claimed['amount'];$reference=(string)$claimed['provider_tx_ref'];$currency=(string)($verified['currency']??'');if($currency==='MK')$currency='MWK';
        if(!in_array($status,['success','successful'],true)){$claimed['state']=UthengaTiePaymentState::PAYMENT_PENDING;$claimed['diagnostics']['last_provider_status']=$status?:'pending';$this->repo()->save($claimed);$this->repo()->recordReconciliation($intentId,$source,'pending',null,(microtime(true)-$started)*1000);return ['accepted'=>true,'payment_intent_id'=>$intentId,'status'=>$claimed['state'],'booking_effect'=>'none'];}
        $matches=(string)($verified['reference']??'')===$reference&&$currency===(string)$claimed['currency']&&is_numeric($verified['amount']??null)&&(float)$verified['amount']>=$expected;
        if(!$matches){$claimed['state']=UthengaTiePaymentState::MANUAL_REVIEW;$claimed['diagnostics']['verification']='successful_provider_response_mismatch';$this->repo()->save($claimed);$this->repo()->recordReconciliation($intentId,$source,'manual_review','verification_mismatch',(microtime(true)-$started)*1000);return ['accepted'=>true,'payment_intent_id'=>$intentId,'status'=>$claimed['state'],'booking_effect'=>'none'];}
        $claimed['state']=UthengaTiePaymentState::VERIFIED;$claimed['provider_reference_hash']=hash('sha256',(string)($verified['provider_reference']??$reference));$claimed['verification']=['status'=>'verified','amount'=>$expected,'currency'=>$claimed['currency'],'mode'=>(string)($verified['mode']??''),'channel'=>(string)($verified['channel']??''),'event_type'=>(string)($verified['event_type']??''),'verified_at'=>gmdate('c')];$this->repo()->save($claimed);
        try{$claimed['state']=UthengaTiePaymentState::BOOKING_PENDING;$this->repo()->save($claimed);$plan=$this->plans->view($claimed['plan_id'],$claimed['user_id'])->toArray();$claimed['diagnostics']['bookings']=$this->commit->commit($plan,$claimed);$claimed['state']=UthengaTiePaymentState::BOOKED;$this->repo()->save($claimed);$this->repo()->recordReconciliation($intentId,$source,'booked',null,(microtime(true)-$started)*1000);return ['accepted'=>true,'payment_intent_id'=>$intentId,'status'=>$claimed['state'],'booking_effect'=>'created'];}
        catch(Throwable $error){$claimed['state']=UthengaTiePaymentState::REFUND_REQUIRED;$claimed['diagnostics']['booking_commit']='failed_refund_required';foreach(($claimed['diagnostics']['hold_ids']??[])as $holdId)try{$this->holds->release((string)$holdId);}catch(Throwable $ignored){}$this->repo()->save($claimed);$this->repo()->recordReconciliation($intentId,$source,'refund_required',$error instanceof UthengaTieException?$error->type():'booking_commit_error',(microtime(true)-$started)*1000);return ['accepted'=>true,'payment_intent_id'=>$intentId,'status'=>$claimed['state'],'booking_effect'=>'refund_required'];}
    }
    private function quote(array $plan, array $selections): array
    {
        $quoted = $this->holds->quote($plan, $selections); $amount = (float) ($quoted['amount'] ?? 0); $currency = (string) ($quoted['currency'] ?? ''); if ($amount <= 0 || !in_array($currency, ['MWK', 'USD'], true)) throw UthengaTieErrors::validation(['plan' => 'The selected inventory does not have a payment-ready total.']);
        $snapshot = ['schema_version' => 'payment-quote/v2', 'plan_id' => $plan['plan_id'], 'amount' => $amount, 'currency' => $currency, 'line_items' => $quoted['line_items'] ?? [], 'selections' => $selections, 'created_at' => gmdate('c')]; $snapshot['hash'] = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES)); return $snapshot;
    }
    private function public(array $intent): array { return ['schema_version' => UthengaTiePaymentIntentResult::SCHEMA_VERSION, 'payment_intent_id' => $intent['id'], 'plan_id' => $intent['plan_id'], 'provider' => $intent['provider_name'], 'status' => $intent['state'], 'amount' => (float) $intent['amount'], 'currency' => $intent['currency'], 'checkout_url' => $intent['checkout_url'], 'expires_at' => $intent['expires_at'], 'provenance' => ['quote' => 'payment-quote/v2', 'provider_verification_required' => true, 'booking_effect' => $intent['state']===UthengaTiePaymentState::BOOKED?'created':'none']]; }
    private function repo(): UthengaTiePaymentRepository { if ($this->repository === null) throw UthengaTieErrors::providerUnavailable('uthenga_database'); return $this->repository; }
    private function uuid(): string { $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)); }
}
