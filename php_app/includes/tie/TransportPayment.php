<?php
/**
 * Quick Taxi: per-passenger payment ledger for a departure.
 *
 * Deliberately independent of UthengaTiePaymentService (Payment.php) — that
 * system is a plan/quote-based booking-payment engine for events/transport/
 * accommodation trip plans, and Coordination sessions never create a plan or
 * quote. This class reuses only the pluggable gateway (UthengaTiePaymentGateway
 * / UthengaTiePaychanguGatewayFactory) and its webhook-signature pattern.
 *
 * Backend enforces payment truth, never the browser: an electronic payment
 * only ever reaches PAID via a verified gateway webhook/poll; a cash payment
 * only ever reaches PAID via an explicit driver confirmation, and can never
 * overwrite an already-PAID electronic row.
 */

final class UthengaTieTransportPaymentContracts
{
    public static function sessionId($value): string { return self::uuid($value, 'session_id'); }
    public static function runId($value): string { return self::uuid($value, 'run_id'); }
    public static function method($value): string
    {
        $method = strtolower(trim((string) $value));
        if (!in_array($method, ['mobile_money', 'bank'], true)) throw UthengaTieErrors::validation(['method' => 'Payment method must be mobile_money or bank.']);
        return $method;
    }
    private static function uuid($value, string $field): string { $value = trim((string) $value); if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value)) throw UthengaTieErrors::validation([$field => 'A valid identifier is required.']); return strtolower($value); }
}

final class UthengaTieTransportPaymentState
{
    private const TRANSITIONS = [
        'PENDING' => ['CHECKOUT_READY', 'FAILED'],
        'CHECKOUT_READY' => ['VERIFYING', 'PAID', 'FAILED'],
        'VERIFYING' => ['PAID', 'FAILED'],
        'CASH_PENDING' => ['PAID'],
    ];
    public static function transition(string $from, string $to): void
    {
        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) throw new RuntimeException("Illegal transport payment transition {$from} -> {$to}.");
    }
}

final class UthengaTieTransportPaymentRepository
{
    public function __construct(private PDO $db) {}

    public function insert(array $row): void
    {
        $this->db->prepare('INSERT INTO tie_transport_payments (id, session_id, amount, currency, method, state, provider_name, provider_reference, checkout_url, metadata) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$row['id'], $row['session_id'], $row['amount'], $row['currency'], $row['method'], $row['state'], $row['provider_name'] ?? null, $row['provider_reference'] ?? null, $row['checkout_url'] ?? null, $row['metadata'] ?? null]);
    }

    public function updateState(string $id, string $state, array $extra = []): void
    {
        $fields = ['state=?']; $params = [$state];
        foreach (['provider_reference', 'checkout_url', 'confirmed_by', 'confirmed_at'] as $field) {
            if (array_key_exists($field, $extra)) { $fields[] = "{$field}=?"; $params[] = $extra[$field]; }
        }
        if (array_key_exists('verification', $extra)) { $fields[] = 'verification=?'; $params[] = json_encode($extra['verification'], JSON_UNESCAPED_SLASHES); }
        $params[] = $id;
        $this->db->prepare('UPDATE tie_transport_payments SET ' . implode(', ', $fields) . ' WHERE id=?')->execute($params);
    }

    public function latestForSession(string $sessionId): ?array { return $this->one('SELECT * FROM tie_transport_payments WHERE session_id=? ORDER BY created_at DESC, id DESC LIMIT 1', [$sessionId]); }
    public function lockLatestForSession(string $sessionId): ?array { return $this->one('SELECT * FROM tie_transport_payments WHERE session_id=? ORDER BY created_at DESC, id DESC LIMIT 1 FOR UPDATE', [$sessionId]); }
    public function lockById(string $id): ?array { return $this->one('SELECT * FROM tie_transport_payments WHERE id=? LIMIT 1 FOR UPDATE', [$id]); }
    public function byProviderReference(string $reference): ?array { return $this->one('SELECT * FROM tie_transport_payments WHERE provider_reference=? ORDER BY created_at DESC LIMIT 1', [$reference]); }

    // Idempotent webhook replay guard: returns false (already processed) on a
    // UNIQUE-constraint collision, mirroring tie_payment_events' pattern.
    public function recordEvent(string $paymentId, string $eventKey, string $eventType, array $payload): bool
    {
        try {
            $this->db->prepare('INSERT INTO tie_transport_payment_events (id, payment_id, event_key, event_type, payload) VALUES (?, ?, ?, ?, ?)')
                ->execute([self::newId(), $paymentId, $eventKey, $eventType, json_encode($payload, JSON_UNESCAPED_SLASHES)]);
            return true;
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') return false;
            throw $error;
        }
    }

    private function one(string $sql, array $params): ?array { $stmt = $this->db->prepare($sql); $stmt->execute($params); $row = $stmt->fetch(); return is_array($row) ? $row : null; }
    public static function newId(): string { $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)); }
}

final class UthengaTieTransportPaymentService
{
    public function __construct(private ?PDO $db, private UthengaTiePaymentGateway $gateway) {}

    public function start(array $input, string $customerId): array
    {
        $sessionId = UthengaTieTransportPaymentContracts::sessionId($input['session_id'] ?? null);
        $method = UthengaTieTransportPaymentContracts::method($input['method'] ?? null);
        $db = $this->db(); $db->beginTransaction();
        try {
            $session = $this->lockedSessionWithFare($sessionId);
            if ((string) $session['customer_id'] !== $customerId) throw UthengaTieErrors::authorization();
            if ($session['status'] !== 'BOARDED') throw UthengaTieErrors::validation(['session' => 'Payment is only available once the driver has confirmed boarding.']);
            $repo = new UthengaTieTransportPaymentRepository($db);
            $latest = $repo->lockLatestForSession($sessionId);
            if ($latest && $latest['state'] === 'PAID') throw UthengaTieErrors::validation(['payment' => 'This trip has already been paid for.']);
            if ($session['fare'] === null) throw UthengaTieErrors::validation(['fare' => 'This departure has no fare configured yet.']);
            $amount = round($session['fare'] * (int) $session['passenger_count'], 2);
            $id = UthengaTieTransportPaymentRepository::newId();
            // Freeze the commission rate now — reconcile() reuses this exact
            // rate when posting to the shared ledger, so an admin rate change
            // in between charge and verification can't retroactively apply.
            $feeRule = function_exists('uthenga_finance_active_fee_rule') ? uthenga_finance_active_fee_rule('transport') : null;
            $commissionRate = $feeRule !== null ? (float) $feeRule['commission_rate'] : (function_exists('uthenga_finance_commission_rate') ? uthenga_finance_commission_rate('transport') : 0.0);
            $feeMetadata = json_encode(['fee_rule_id' => $feeRule['id'] ?? null, 'commission_rate' => $commissionRate], JSON_UNESCAPED_SLASHES);
            $repo->insert(['id' => $id, 'session_id' => $sessionId, 'amount' => $amount, 'currency' => APP_CURRENCY, 'method' => $method, 'state' => 'PENDING', 'metadata' => $feeMetadata]);
            try {
                $checkout = $this->gateway->checkout(['id' => $id, 'amount' => $amount, 'currency' => APP_CURRENCY, 'provider_tx_ref' => $id, 'quote_hash' => hash('sha256', $sessionId . '|' . $amount)]);
            } catch (Throwable $error) {
                $repo->updateState($id, 'FAILED');
                $db->commit();
                throw $error;
            }
            $repo->updateState($id, 'CHECKOUT_READY', ['provider_reference' => $checkout['provider_reference'], 'checkout_url' => $checkout['checkout_url']]);
            $db->commit();
            return $this->status(['session_id' => $sessionId], $customerId);
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
    }

    // Driver-only exception path. Hard invariant: never overwrite an
    // already-PAID (electronically verified) row with cash.
    public function confirmCash(array $input, string $vendorId): array
    {
        $sessionId = UthengaTieTransportPaymentContracts::sessionId($input['session_id'] ?? null);
        $db = $this->db(); $db->beginTransaction();
        try {
            $session = $this->lockedSessionWithFare($sessionId);
            if ((string) $session['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
            if ($session['status'] !== 'BOARDED') throw UthengaTieErrors::validation(['session' => 'Cash can only be recorded once boarding is confirmed.']);
            $repo = new UthengaTieTransportPaymentRepository($db);
            $latest = $repo->lockLatestForSession($sessionId);
            if ($latest && $latest['state'] === 'PAID') throw UthengaTieErrors::validation(['payment' => 'This trip has already been paid for and cannot be overwritten with cash.']);
            if ($session['fare'] === null) throw UthengaTieErrors::validation(['fare' => 'This departure has no fare configured yet.']);
            $amount = round($session['fare'] * (int) $session['passenger_count'], 2);
            $id = UthengaTieTransportPaymentRepository::newId();
            $repo->insert(['id' => $id, 'session_id' => $sessionId, 'amount' => $amount, 'currency' => APP_CURRENCY, 'method' => 'cash', 'state' => 'CASH_PENDING']);
            $repo->updateState($id, 'PAID', ['confirmed_by' => $vendorId, 'confirmed_at' => gmdate('Y-m-d H:i:s')]);
            $db->commit();
            return $this->status(['session_id' => $sessionId], $vendorId);
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
    }

    public function receiveWebhook(string $payload, string $signature): array
    {
        if (!$this->gateway->verifyWebhookSignature($payload, $signature)) throw UthengaTieErrors::authorization();
        $data = json_decode($payload, true); $data = is_array($data) ? $data : [];
        $reference = (string) ($data['tx_ref'] ?? ($data['data']['tx_ref'] ?? ''));
        if ($reference === '') throw UthengaTieErrors::validation(['tx_ref' => 'A transaction reference is required.']);
        $db = $this->db(); $repo = new UthengaTieTransportPaymentRepository($db);
        $payment = $repo->byProviderReference($reference);
        if (!$payment) throw UthengaTieErrors::validation(['tx_ref' => 'Unknown transaction reference.']);
        $isNew = $repo->recordEvent((string) $payment['id'], 'webhook:' . $reference . ':' . hash('sha256', $payload), 'webhook_received', $data);
        if (!$isNew) return ['processed' => false, 'reason' => 'duplicate'];
        return $this->reconcile((string) $payment['id']);
    }

    public function reconcile(string $paymentId): array
    {
        $db = $this->db(); $repo = new UthengaTieTransportPaymentRepository($db);
        $before = $repo->lockById($paymentId);
        if (!is_array($before)) throw UthengaTieErrors::validation(['payment_id' => 'Payment not found.']);
        if ($before['state'] === 'PAID') return ['processed' => true, 'state' => 'PAID'];
        $verification = $this->gateway->verify((string) $before['provider_reference']);
        $db->beginTransaction();
        try {
            $locked = $repo->lockById($paymentId);
            if ($locked['state'] === 'PAID') { $db->commit(); return ['processed' => true, 'state' => 'PAID']; }
            $verifiedOk = strtolower((string) ($verification['status'] ?? '')) === 'success' && $verification['amount'] !== null && abs(((float) $verification['amount']) - (float) $locked['amount']) < 0.01;
            $next = $verifiedOk ? 'PAID' : 'FAILED';
            UthengaTieTransportPaymentState::transition($locked['state'] === 'PENDING' ? 'CHECKOUT_READY' : $locked['state'], $next);
            $repo->updateState($locked['id'], $next, ['verification' => $verification, 'confirmed_by' => 'system_gateway', 'confirmed_at' => $verifiedOk ? gmdate('Y-m-d H:i:s') : null]);
            if ($verifiedOk && class_exists('UthengaPaymentEngine')) {
                $vendorStmt = $db->prepare('SELECT vendor_id FROM tie_transport_sessions WHERE id = ?');
                $vendorStmt->execute([$locked['session_id']]);
                $vendorId = (string) $vendorStmt->fetchColumn();
                if ($vendorId !== '') {
                    $feeMeta = json_decode((string) ($locked['metadata'] ?? '{}'), true) ?: [];
                    UthengaPaymentEngine::postExternalLedgers([
                        'payment_intent_id' => $locked['id'],
                        'intent_ref'        => (string) $locked['provider_reference'],
                        'service_category'  => 'transport',
                        'gross_amount'      => (float) $locked['amount'],
                        'vendor_id'         => $vendorId,
                        'fee_rule_id'       => $feeMeta['fee_rule_id'] ?? null,
                        'commission_rate'   => $feeMeta['commission_rate'] ?? null,
                    ]);
                }
            }
            $db->commit();
            return ['processed' => true, 'state' => $next];
        } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
    }

    public function status(array $input, string $actorId): array
    {
        $sessionId = UthengaTieTransportPaymentContracts::sessionId($input['session_id'] ?? null);
        $db = $this->db();
        $stmt = $db->prepare('SELECT customer_id, vendor_id FROM tie_transport_sessions WHERE id=? LIMIT 1'); $stmt->execute([$sessionId]); $session = $stmt->fetch();
        if (!is_array($session) || ((string) $session['customer_id'] !== $actorId && (string) $session['vendor_id'] !== $actorId)) throw UthengaTieErrors::authorization();
        return ['payment' => $this->publicPayment((new UthengaTieTransportPaymentRepository($db))->latestForSession($sessionId))];
    }

    public function ledger(array $input, string $vendorId): array
    {
        $runId = UthengaTieTransportPaymentContracts::runId($input['run_id'] ?? null);
        return $this->ledgerFor($runId, $vendorId);
    }

    public function runReadiness(string $runId, string $vendorId): array
    {
        $db = $this->db();
        $counts = $db->prepare("SELECT SUM(CASE WHEN status IN ('PENDING_VENDOR','ACCEPTED','CUSTOMER_EN_ROUTE','ARRIVED_AT_PICKUP','BOARDING_REQUESTED') THEN 1 ELSE 0 END) AS unresolved, SUM(CASE WHEN status = 'BOARDED' THEN 1 ELSE 0 END) AS boarded FROM tie_transport_sessions WHERE run_id=?");
        $counts->execute([$runId]); $counted = $counts->fetch() ?: ['unresolved' => 0, 'boarded' => 0];
        $ledger = $this->ledgerFor($runId, $vendorId);
        $ready = (int) $counted['unresolved'] === 0 && $ledger['outstanding_count'] === 0 && (int) $counted['boarded'] > 0;
        return ['boarded' => (int) $counted['boarded'], 'unresolved' => (int) $counted['unresolved'], 'paid_count' => $ledger['paid_count'], 'outstanding_count' => $ledger['outstanding_count'], 'outstanding_amount' => $ledger['outstanding_amount'], 'ready' => $ready];
    }

    private function ledgerFor(string $runId, string $vendorId): array
    {
        $db = $this->db();
        $run = $db->prepare('SELECT vendor_id FROM tie_transport_runs WHERE id=? LIMIT 1'); $run->execute([$runId]); $runRow = $run->fetch();
        if (!is_array($runRow) || (string) $runRow['vendor_id'] !== $vendorId) throw UthengaTieErrors::authorization();
        $stmt = $db->prepare("SELECT s.id AS session_id, s.customer_id, s.passenger_count, s.status AS boarding_status, p.amount, p.method, p.state, p.confirmed_at
            FROM tie_transport_sessions s
            LEFT JOIN tie_transport_payments p ON p.id = (SELECT id FROM tie_transport_payments WHERE session_id = s.id ORDER BY created_at DESC, id DESC LIMIT 1)
            WHERE s.run_id = ? AND s.status IN ('BOARDING_REQUESTED', 'BOARDED')");
        $stmt->execute([$runId]);
        $paidCount = 0; $outstandingCount = 0; $outstandingAmount = 0.0; $cashAmount = 0.0; $digitalAmount = 0.0; $entries = [];
        foreach ($stmt->fetchAll() as $row) {
            $isPaid = ($row['state'] ?? null) === 'PAID';
            if ($row['boarding_status'] === 'BOARDED') {
                if ($isPaid) { $paidCount++; if ($row['method'] === 'cash') $cashAmount += (float) $row['amount']; else $digitalAmount += (float) $row['amount']; }
                else $outstandingCount++;
            }
            $entries[] = ['session_id' => (string) $row['session_id'], 'boarding_status' => (string) $row['boarding_status'], 'payment_state' => $row['state'] ?? 'NONE', 'amount' => $row['amount'] !== null ? (float) $row['amount'] : null, 'method' => $row['method']];
        }
        return ['paid_count' => $paidCount, 'outstanding_count' => $outstandingCount, 'outstanding_amount' => round($outstandingAmount, 2), 'cash_amount' => round($cashAmount, 2), 'digital_amount' => round($digitalAmount, 2), 'entries' => $entries];
    }

    private function publicPayment(?array $row): array
    {
        if (!$row) return ['state' => 'NONE', 'amount' => null, 'currency' => null, 'method' => null, 'checkout_url' => null, 'confirmed_by' => null, 'confirmed_at' => null];
        return [
            'state' => (string) $row['state'], 'amount' => (float) $row['amount'], 'currency' => (string) $row['currency'], 'method' => (string) $row['method'],
            'checkout_url' => $row['checkout_url'] ?? null, 'confirmed_by' => $row['confirmed_by'] ?? null,
            'confirmed_at' => $row['confirmed_at'] ? gmdate('c', strtotime($row['confirmed_at'] . ' UTC')) : null,
        ];
    }

    private function lockedSessionWithFare(string $sessionId): array
    {
        $db = $this->db();
        $stmt = $db->prepare('SELECT * FROM tie_transport_sessions WHERE id=? LIMIT 1 FOR UPDATE'); $stmt->execute([$sessionId]); $session = $stmt->fetch();
        if (!is_array($session)) throw UthengaTieErrors::validation(['session_id' => 'Coordination session not found.']);
        $fareStmt = $db->prepare('SELECT sc.price AS fare FROM tie_transport_runs r LEFT JOIN seat_classes sc ON sc.id = r.seat_class_id AND sc.is_active = 1 WHERE r.id = ? LIMIT 1');
        $fareStmt->execute([$session['run_id']]); $fareRow = $fareStmt->fetch();
        $session['fare'] = ($fareRow && $fareRow['fare'] !== null) ? (float) $fareRow['fare'] : null;
        return $session;
    }

    private function db(): PDO { if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('transport_payment'); return $this->db; }
}
