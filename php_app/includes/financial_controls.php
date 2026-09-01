<?php
/** Phase D canonical financial state and maker-checker safeguards. */
require_once __DIR__ . '/../db.php';

final class UthengaFinancialState {
    public const PAYMENT = ['CREATED','PENDING','AUTHORIZED','PROCESSING','SUCCESSFUL','FAILED','CANCELLED','EXPIRED','PARTIALLY_REFUNDED','REFUNDED','DISPUTED','RECONCILED','SETTLED'];
    private const TRANSITIONS = [
        'CREATED' => ['PENDING','CANCELLED','EXPIRED'],
        'PENDING' => ['AUTHORIZED','PROCESSING','FAILED','CANCELLED','EXPIRED'],
        'AUTHORIZED' => ['PROCESSING','SUCCESSFUL','FAILED','CANCELLED','EXPIRED'],
        'PROCESSING' => ['SUCCESSFUL','FAILED','CANCELLED','EXPIRED'],
        'SUCCESSFUL' => ['RECONCILED','SETTLED','PARTIALLY_REFUNDED','REFUNDED','DISPUTED'],
        'RECONCILED' => ['SETTLED','PARTIALLY_REFUNDED','REFUNDED','DISPUTED'],
        'SETTLED' => ['PARTIALLY_REFUNDED','REFUNDED','DISPUTED'],
        'PARTIALLY_REFUNDED' => ['REFUNDED','DISPUTED'],
    ];
    public static function normalize(string $state): string {
        $state = strtoupper(trim($state));
        return match ($state) {
            'PAID','SUCCESS','CONFIRMED' => 'SUCCESSFUL',
            'PAYMENT_PENDING' => 'PENDING',
            default => in_array($state, self::PAYMENT, true) ? $state : 'UNKNOWN',
        };
    }
    public static function canTransition(string $from, string $to): bool {
        $from = self::normalize($from); $to = self::normalize($to);
        return $from !== 'UNKNOWN' && $to !== 'UNKNOWN' && in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
    public static function assertTransition(string $from, string $to): void {
        if (!self::canTransition($from, $to)) throw new InvalidArgumentException('The requested financial state transition is not allowed.');
    }
    public static function mwkToMinor(string $amount): int {
        if (!preg_match('/^(0|[1-9][0-9]*)(\.[0-9]{1,2})?$/', trim($amount), $matches)) throw new InvalidArgumentException('Enter a valid MWK amount.');
        $whole = (int) $matches[1]; $fraction = str_pad(ltrim($matches[2] ?? '', '.'), 2, '0');
        $minor = ($whole * 100) + (int) $fraction;
        if ($minor <= 0) throw new InvalidArgumentException('Amount must be greater than zero.');
        return $minor;
    }
}

/**
 * Phase D2.3 deployment gate for provider callbacks and reconciliation jobs.
 * Payment attempts remain pending during containment. The receipt-store
 * migration is a prerequisite for the follow-on transactional implementation;
 * this function intentionally cannot be enabled by configuration alone.
 */
function uthenga_financial_callback_commit_allowed(): bool {
    return false;
}

function uthenga_financial_callback_block(string $channel): void {
    error_log('[FinancialControls] Blocked callback/reconciliation attempt: ' . preg_replace('/[^a-z0-9_.-]/i', '_', $channel));
}

final class UthengaFinancialReview {
    private static function audit(string $id, string $from, string $to, int $minor, string $currency, string $reason, string $permission): void {
        dbExecute('INSERT INTO uthenga_financial_audit_log (review_request_id, actor_id, actor_role, permission_used, from_status, to_status, amount_minor, currency, reason, idempotency_key_hash) VALUES (?,?,?,?,?,?,?,?,?,?)', [$id, (string) ($_SESSION['user_id'] ?? 'system'), (string) ($_SESSION['user_role'] ?? 'system'), $permission, $from ?: null, $to, $minor, $currency, $reason ?: null, null]);
    }
    public static function submitManualSettlement(array $input, string $makerId): array {
        if (!uthenga_table_exists('uthenga_financial_review_requests')) throw new RuntimeException('Financial controls migration has not been applied.');
        $periodStart = trim((string) ($input['period_start'] ?? '')); $periodEnd = trim((string) ($input['period_end'] ?? ''));
        $reference = trim((string) ($input['external_reference'] ?? '')); $key = trim((string) ($input['idempotency_key'] ?? ''));
        $channel = trim((string) ($input['provider_or_channel'] ?? '')); $note = trim((string) ($input['supporting_note'] ?? ''));
        if (!$periodStart || !$periodEnd || $periodStart > $periodEnd) throw new InvalidArgumentException('Enter a valid, non-contradictory settlement period.');
        if ($reference === '' || $channel === '' || $note === '' || !preg_match('/^[A-Za-z0-9._-]{16,128}$/', $key)) throw new InvalidArgumentException('Reference, channel, supporting note and a valid idempotency key are required.');
        $minor = UthengaFinancialState::mwkToMinor((string) ($input['amount_mwk'] ?? ''));
        $existing = dbQueryOne('SELECT * FROM uthenga_financial_review_requests WHERE domain=? AND idempotency_key=? LIMIT 1', ['platform_settlement', $key]);
        if ($existing) return ['id' => $existing['id'], 'status' => $existing['status'], 'idempotent' => true];
        $duplicate = dbQueryOne('SELECT id FROM uthenga_financial_review_requests WHERE domain=? AND provider_or_channel=? AND external_reference=? LIMIT 1', ['platform_settlement', $channel, $reference]);
        if ($duplicate) throw new InvalidArgumentException('This provider/reference combination has already been recorded.');
        $overlap = dbQueryOne("SELECT id FROM uthenga_financial_review_requests WHERE domain='platform_settlement' AND status IN ('SUBMITTED','APPROVED','EXECUTED') AND period_start <= ? AND period_end >= ? LIMIT 1", [$periodEnd, $periodStart]);
        if ($overlap) throw new InvalidArgumentException('This settlement period overlaps an active settlement review.');
        $eligible = (int) (dbQueryOne("SELECT COALESCE(ROUND(SUM(net_revenue) * 100),0) AS value FROM uthenga_revenue_ledger")['value'] ?? 0) - (int) (dbQueryOne("SELECT COALESCE(ROUND(SUM(amount) * 100),0) AS value FROM uthenga_platform_settlements")['value'] ?? 0);
        if ($minor > $eligible) throw new InvalidArgumentException('Settlement amount exceeds the authoritative unsettled balance.');
        $id = 'frq_' . bin2hex(random_bytes(16));
        dbExecute('INSERT INTO uthenga_financial_review_requests (id, domain, status, amount_minor, currency, provider_or_channel, external_reference, idempotency_key, period_start, period_end, evidence_reference, supporting_note, maker_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [$id, 'platform_settlement', 'SUBMITTED', $minor, 'MWK', $channel, $reference, $key, $periodStart, $periodEnd, trim((string) ($input['evidence_reference'] ?? '')) ?: null, $note, $makerId]);
        self::audit($id, 'DRAFT', 'SUBMITTED', $minor, 'MWK', $note, 'finance.manage');
        return ['id' => $id, 'status' => 'SUBMITTED', 'idempotent' => false];
    }
    public static function approveSettlement(string $id, string $checkerId, string $reason): array {
        if (trim($reason) === '') throw new InvalidArgumentException('Approval requires a review reason.');
        global $pdo; $pdo->beginTransaction();
        try {
            $row = dbQueryOne('SELECT * FROM uthenga_financial_review_requests WHERE id=? AND domain=? FOR UPDATE', [$id, 'platform_settlement']);
            if (!$row) throw new InvalidArgumentException('Settlement review was not found.');
            if ($row['status'] !== 'SUBMITTED') throw new InvalidArgumentException('Only submitted settlement reviews can be approved.');
            if (hash_equals((string) $row['maker_id'], $checkerId)) throw new InvalidArgumentException('The maker cannot approve their own settlement request.');
            dbExecute("UPDATE uthenga_financial_review_requests SET status='APPROVED', checker_id=?, decision_reason=? WHERE id=?", [$checkerId, $reason, $id]);
            self::audit($id, 'SUBMITTED', 'APPROVED', (int) $row['amount_minor'], (string) $row['currency'], $reason, 'settlements.review');
            $pdo->commit();
            return ['id' => $id, 'status' => 'APPROVED'];
        } catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
    }
    public static function rejectSettlement(string $id, string $checkerId, string $reason): array {
        if (trim($reason) === '') throw new InvalidArgumentException('Rejection requires a reason.');
        $row = dbQueryOne('SELECT * FROM uthenga_financial_review_requests WHERE id=? AND domain=?', [$id, 'platform_settlement']);
        if (!$row || $row['status'] !== 'SUBMITTED') throw new InvalidArgumentException('This settlement review cannot be rejected.');
        if (hash_equals((string) $row['maker_id'], $checkerId)) throw new InvalidArgumentException('The maker cannot reject their own settlement request.');
        dbExecute("UPDATE uthenga_financial_review_requests SET status='REJECTED', checker_id=?, decision_reason=? WHERE id=?", [$checkerId, $reason, $id]);
        self::audit($id, 'SUBMITTED', 'REJECTED', (int) $row['amount_minor'], (string) $row['currency'], $reason, 'settlements.review');
        return ['id' => $id, 'status' => 'REJECTED'];
    }
}
