<?php
/**
 * Uthenga Payment Engine — Core Subsystem & Financial Ledger State Machine
 * Handles Payment Intents, Revenue Fee Calculation, 3 Ledgers Postings,
 * and Business Event Dispatching.
 *
 * This is the one authoritative Uthenga Payment Engine per the platform
 * payment architecture: admin controls commission via the settings-backed
 * uthenga_finance_* functions (includes/functions.php), every service
 * integrates into this engine rather than implementing payment on its own.
 *
 * Bus keeps its own proven, real PayChangu direct-charge purchase/verify/webhook
 * flow (BusOperations::purchaseTicket()/reconcilePayment()) rather than being
 * replatformed onto createIntent()/verifyAndPostLedgers() — it posts into this
 * engine's shared revenue/vendor-payable ledgers via postExternalLedgers()
 * instead, so its real revenue is visible platform-wide without risking a
 * working, tested, real-money code path.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/payment_schema.php';
require_once __DIR__ . '/payment_provider.php';
require_once __DIR__ . '/tie/bootstrap.php';
require_once __DIR__ . '/shop_helpers.php';

class UthengaPaymentEngine {

    /**
     * uthenga_payment_intents.status lifecycle (plain VARCHAR, no ENUM —
     * enforced here in code, not the schema):
     *
     *   CREATED ─────────┐
     *   PAYMENT_PENDING ─┼─→ PROCESSING ─→ CONFIRMED ─→ SETTLED
     *                    │        │
     *                    │        ├─→ FAILED      (provider declined/unverified)
     *                    │        ├─→ EXPIRED     (abandoned past expires_at — lazy-checked in verifyAndPostLedgers())
     *                    │        └─→ CANCELLED   (customer explicitly cancelled — cancelIntent())
     *   SETTLED ─────────┴─→ REFUNDED | PARTIALLY_REFUNDED (via refundIntent()) | DISPUTED
     *
     * DISPUTED remains reserved vocabulary only — no dispute/chargeback
     * pipeline exists yet.
     */

    /**
     * Maps this engine's plural/legacy service_type spellings onto the
     * canonical listing-type keys uthenga_finance_* functions expect.
     */
    private static function normalizedServiceType(string $serviceCategory): string {
        return match (strtolower(trim($serviceCategory))) {
            'events', 'event' => 'event',
            'tours', 'tour' => 'tour',
            default => strtolower(trim($serviceCategory)),
        };
    }

    /**
     * Fee Engine — Commission Rates by Service Category.
     * Sourced from the admin-owned settings table (uthenga_finance_commission_rate),
     * not a hardcoded table, so this always matches what admin configures in
     * the Payment Revenue Rules UI. Categories with no dedicated rate key
     * (e.g. "shop") fall back to the global commission_rate setting.
     */
    public static function getCategoryCommissionRate(string $serviceCategory): float {
        return uthenga_finance_commission_rate(self::normalizedServiceType($serviceCategory));
    }

    /**
     * Calculate financial split for gross amount based on category policy
     */
    public static function calculateSplit(float $grossAmount, string $serviceCategory): array {
        $rule = uthenga_finance_active_fee_rule(self::normalizedServiceType($serviceCategory));
        $rate = $rule !== null ? (float) $rule['commission_rate'] : self::getCategoryCommissionRate($serviceCategory);
        $platformFee = round(($grossAmount * ($rate / 100.0)), 2);
        $vendorAmount = round(($grossAmount - $platformFee), 2);

        return [
            'gross_amount'    => $grossAmount,
            'commission_rate' => $rate,
            'platform_fee'    => $platformFee,
            'vendor_amount'   => $vendorAmount,
            'policy_version'  => 'v1.0',
            'fee_rule_id'     => $rule['id'] ?? null,
        ];
    }

    /**
     * Records real revenue collected by a service that keeps its own payment
     * collection (currently: Bus) into the shared platform revenue and vendor
     * payable ledgers, without requiring a uthenga_payment_intents row. The
     * caller's own idempotency guard (e.g. BusOperations::reconcilePayment()'s
     * "already Paid" early-return) is what prevents this from double-posting —
     * this method itself performs no dedup.
     */
    public static function postExternalLedgers(array $params): void {
        global $pdo;
        $grossAmount = (float) $params['gross_amount'];
        // Prefer whatever rate the caller froze at charge-creation time (e.g.
        // Bus/Quick Taxi stash this in their own transaction's metadata at
        // purchase time) — only compute a fresh split if none was supplied.
        if (isset($params['commission_rate'])) {
            $rate = (float) $params['commission_rate'];
            $platformFee = round($grossAmount * $rate / 100.0, 2);
            $split = [
                'gross_amount'    => $grossAmount,
                'commission_rate' => $rate,
                'platform_fee'    => $platformFee,
                'vendor_amount'   => round($grossAmount - $platformFee, 2),
                'policy_version'  => 'v1.0',
                'fee_rule_id'     => $params['fee_rule_id'] ?? null,
            ];
        } else {
            $split = self::calculateSplit($grossAmount, (string) $params['service_category']);
        }

        // Nesting-safe: callers like BusOperations::reconcilePayment() invoke this
        // from inside their own already-open transaction on the same connection.
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            dbExecute("
                INSERT INTO uthenga_revenue_ledger
                  (payment_intent_id, intent_ref, service_category, gross_amount, commission_rate, platform_fee, provider_fee, net_revenue, policy_version)
                VALUES
                  (?, ?, ?, ?, ?, ?, 0.00, ?, ?)
            ", [
                (string) $params['payment_intent_id'],
                (string) $params['intent_ref'],
                (string) $params['service_category'],
                $split['gross_amount'],
                $split['commission_rate'],
                $split['platform_fee'],
                $split['platform_fee'],
                $split['policy_version'],
            ]);

            dbExecute("
                INSERT INTO uthenga_vendor_payable_ledger
                  (vendor_id, payment_intent_id, intent_ref, service_category, gross_amount, commission_fee, net_payable, payout_status)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, 'PENDING')
            ", [
                (string) $params['vendor_id'],
                (string) $params['payment_intent_id'],
                (string) $params['intent_ref'],
                (string) $params['service_category'],
                $split['gross_amount'],
                $split['platform_fee'],
                $split['vendor_amount'],
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Create a new Payment Intent with Idempotency Protection
     */
    public static function createIntent(array $params): array {
        uthenga_init_payment_schema();

        $customerId      = trim((string)($params['customer_id'] ?? ($_SESSION['user_id'] ?? 'guest')));
        $serviceType     = strtolower(trim((string)($params['service_type'] ?? 'accommodation')));
        $serviceId       = trim((string)($params['service_id'] ?? ''));
        $bookingId       = trim((string)($params['booking_id'] ?? ''));
        $grossAmount     = (float)($params['amount'] ?? 0);
        $currency        = strtoupper(trim((string)($params['currency'] ?? 'MWK')));
        $idempotencyKey  = trim((string)($params['idempotency_key'] ?? ''));

        if ($grossAmount <= 0) {
            throw new InvalidArgumentException('Payment intent amount must be greater than zero.');
        }

        // Idempotency check: if intent exists for this key, return it directly
        if ($idempotencyKey !== '') {
            $existing = dbQueryOne(
                'SELECT * FROM uthenga_payment_intents WHERE idempotency_key = ?',
                [$idempotencyKey]
            );
            if ($existing) {
                return [
                    'success' => true,
                    'intent'  => $existing,
                    'is_existing' => true
                ];
            }
        }

        $split = self::calculateSplit($grossAmount, $serviceType);
        $intentId  = 'pi_' . bin2hex(random_bytes(16));
        $intentRef = 'UTH-' . strtoupper(bin2hex(random_bytes(4)));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

        dbExecute("
            INSERT INTO uthenga_payment_intents
              (id, intent_ref, customer_id, service_type, service_id, booking_id,
               gross_amount, platform_fee, vendor_amount, currency, status,
               idempotency_key, policy_version, fee_rule_id, commission_rate, expires_at)
            VALUES
              (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CREATED', ?, ?, ?, ?, ?)
        ", [
            $intentId,
            $intentRef,
            $customerId,
            $serviceType,
            $serviceId,
            $bookingId,
            $split['gross_amount'],
            $split['platform_fee'],
            $split['vendor_amount'],
            $currency,
            $idempotencyKey ?: null,
            $split['policy_version'],
            $split['fee_rule_id'],
            $split['commission_rate'],
            $expiresAt
        ]);

        $intent = dbQueryOne('SELECT * FROM uthenga_payment_intents WHERE id = ?', [$intentId]);
        self::auditLog($intentRef, $customerId, 'create_intent', 'customer', null, 'CREATED', $serviceType . ':' . $serviceId);

        return [
            'success' => true,
            'intent'  => $intent,
            'is_existing' => false
        ];
    }

    /**
     * Select Payment Method (Airtel Money, TNM Mpamba, Bank Transfer, Card)
     */
    public static function selectMethod(string $intentRef, string $method, ?string $phone = null): array {
        $intent = dbQueryOne('SELECT * FROM uthenga_payment_intents WHERE intent_ref = ?', [$intentRef]);
        if (!$intent) {
            return ['success' => false, 'error' => 'Payment intent not found.'];
        }

        if (in_array($intent['status'], ['CONFIRMED', 'SETTLED', 'PAID'], true)) {
            return ['success' => true, 'intent' => $intent, 'already_paid' => true];
        }

        dbExecute("
            UPDATE uthenga_payment_intents
            SET payment_method = ?, phone_number = ?, status = 'PAYMENT_PENDING'
            WHERE intent_ref = ?
        ", [$method, $phone, $intentRef]);

        // Initiate Charge via Provider Adapter
        $provider = new PayChanguProvider();
        $user = dbQueryOne('SELECT name, email FROM users WHERE id = ?', [$intent['customer_id']]) ?: [];

        $chargeResult = $provider->initiateCharge([
            'method'   => $method,
            'amount'   => $intent['gross_amount'],
            'currency' => $intent['currency'],
            'tx_ref'   => $intentRef,
            'phone'    => $phone,
            'email'    => $user['email'] ?? 'customer@uthenga.mw',
            'name'     => $user['name'] ?? 'Uthenga Customer',
        ]);

        dbExecute("
            UPDATE uthenga_payment_intents
            SET status = 'PROCESSING', provider_tx_ref = ?
            WHERE intent_ref = ?
        ", [$chargeResult['data']['tx_ref'] ?? $intentRef, $intentRef]);

        $updated = dbQueryOne('SELECT * FROM uthenga_payment_intents WHERE intent_ref = ?', [$intentRef]);
        self::auditLog($intentRef, (string) $intent['customer_id'], 'select_method', 'customer', $intent['status'], 'PROCESSING', 'method=' . $method);

        return [
            'success'        => true,
            'intent'         => $updated,
            'charge_details' => $chargeResult,
        ];
    }

    /**
     * Explicit customer cancellation — only while the intent hasn't reached the
     * provider-confirmed state yet. Releases whatever hold/inventory the
     * underlying booking reserved, same as an expired or failed payment would.
     */
    public static function cancelIntent(string $intentRef): array {
        $intent = dbQueryOne('SELECT * FROM uthenga_payment_intents WHERE intent_ref = ?', [$intentRef]);
        if (!$intent) {
            return ['success' => false, 'error' => 'Payment intent not found.'];
        }
        if (!in_array($intent['status'], ['CREATED', 'PAYMENT_PENDING', 'PROCESSING'], true)) {
            return ['success' => false, 'error' => 'This payment can no longer be cancelled.', 'intent' => $intent];
        }
        dbExecute("UPDATE uthenga_payment_intents SET status = 'CANCELLED' WHERE id = ?", [$intent['id']]);
        self::releaseReservedInventory($intent, 'CANCELLED');
        self::auditLog($intentRef, (string) $intent['customer_id'], 'cancel', 'customer', $intent['status'], 'CANCELLED');
        $updated = dbQueryOne('SELECT * FROM uthenga_payment_intents WHERE id = ?', [$intent['id']]);
        return ['success' => true, 'intent' => $updated];
    }

    /**
     * The one canonical refund entry point — reverses the 3 ledgers with real
     * negative entries (never `balance -= amount`), tracked against
     * uthenga_refund_ledger so "how much has already been refunded" is always
     * derived from a sum, not inferred. Scoped to money/intent-state only —
     * booking/reservation/ticket status flips are the calling vertical's job,
     * mirroring verifyAndPostLedgers() -> confirmUnderlyingBooking()'s split.
     */
    public static function refundIntent(string $intentRef, float $amount, string $reason, string $actorId, string $sourceType, ?string $sourceRequestId = null): array {
        $intent = dbQueryOne('SELECT * FROM uthenga_payment_intents WHERE intent_ref = ?', [$intentRef]);
        if (!$intent) {
            return ['success' => false, 'error' => 'Payment intent not found.'];
        }
        if (!in_array($intent['status'], ['SETTLED', 'PARTIALLY_REFUNDED'], true)) {
            return ['success' => false, 'error' => 'This payment is not in a refundable state (' . $intent['status'] . ').'];
        }
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Refund amount must be greater than zero.'];
        }

        $alreadyRefunded = (float) (dbQueryOne('SELECT COALESCE(SUM(amount), 0) t FROM uthenga_refund_ledger WHERE intent_ref = ?', [$intentRef])['t'] ?? 0);
        $grossAmount = (float) $intent['gross_amount'];
        $refundable = round($grossAmount - $alreadyRefunded, 2);
        if ($amount > $refundable + 0.01) {
            return ['success' => false, 'error' => 'Refund amount exceeds refundable balance (MWK ' . number_format($refundable, 2) . ' remaining).'];
        }

        global $pdo;
        $ratio = $grossAmount > 0 ? ($amount / $grossAmount) : 0;
        $feeReversal = round(((float) $intent['platform_fee']) * $ratio, 2);
        $vendorReversal = round(((float) $intent['vendor_amount']) * $ratio, 2);
        $refundId = 'rfd_' . bin2hex(random_bytes(16));
        $receiptNo = 'UTH-RFD-' . date('Ymd') . '-' . strtoupper(substr(md5($refundId), 0, 5));
        $vendorId = self::resolveVendorIdForService($intent['service_type'], $intent['service_id']);

        $pdo->beginTransaction();
        try {
            dbExecute("
                INSERT INTO uthenga_refund_ledger (id, intent_ref, amount, reason, actor_id, source_type, source_request_id, receipt_number)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [$refundId, $intentRef, $amount, $reason, $actorId, $sourceType, $sourceRequestId, $receiptNo]);

            dbExecute("
                INSERT INTO uthenga_customer_ledger
                  (payment_intent_id, intent_ref, customer_id, amount, currency, payment_method, status, receipt_number)
                VALUES
                  (?, ?, ?, ?, ?, ?, 'REFUNDED', ?)
            ", [
                $intent['id'], $intentRef, $intent['customer_id'], -$amount,
                $intent['currency'], $intent['payment_method'] ?: 'refund', $receiptNo
            ]);

            dbExecute("
                INSERT INTO uthenga_revenue_ledger
                  (payment_intent_id, intent_ref, service_category, gross_amount, commission_rate, platform_fee, provider_fee, net_revenue, policy_version)
                VALUES
                  (?, ?, ?, ?, ?, ?, 0.00, ?, ?)
            ", [
                $intent['id'], $intentRef, $intent['service_type'], -$amount,
                $intent['commission_rate'], -$feeReversal, -$feeReversal, $intent['policy_version']
            ]);

            dbExecute("
                INSERT INTO uthenga_vendor_payable_ledger
                  (vendor_id, payment_intent_id, intent_ref, service_category, gross_amount, commission_fee, net_payable, payout_status)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, 'PENDING')
            ", [
                $vendorId, $intent['id'], $intentRef, $intent['service_type'],
                -$amount, -$feeReversal, -$vendorReversal
            ]);

            $newStatus = ($amount + $alreadyRefunded >= $grossAmount - 0.01) ? 'REFUNDED' : 'PARTIALLY_REFUNDED';
            dbExecute("UPDATE uthenga_payment_intents SET status = ? WHERE id = ?", [$newStatus, $intent['id']]);
            self::auditLog($intentRef, $actorId, 'refund', $sourceType, $intent['status'], $newStatus, 'amount=' . $amount . ' receipt=' . $receiptNo);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        if (function_exists('uthenga_notify_user')) {
            $noun = $newStatus === 'REFUNDED' ? 'fully refunded' : 'partially refunded';
            uthenga_notify_user((string) $intent['customer_id'], $intent['service_type'], 'Refund processed', 'MWK ' . number_format($amount, 2) . ' has been ' . $noun . ' for your ' . $intent['service_type'] . '.');
            if ($vendorId !== 'uthenga-platform-vendor' && $vendorId !== 'uthenga-retail-org') {
                uthenga_notify_user($vendorId, $intent['service_type'], 'Refund issued', 'MWK ' . number_format($amount, 2) . ' was refunded to a customer against intent ' . $intentRef . '. Your payable balance has been adjusted.');
            }
        }

        return [
            'success'        => true,
            'refund_id'      => $refundId,
            'receipt_number' => $receiptNo,
            'amount'         => $amount,
            'intent_status'  => $newStatus,
        ];
    }

    /**
     * Mandatory Double-Check Verification & Posting to the 3 Ledgers
     */
    public static function verifyAndPostLedgers(string $intentRef, array $providerPayload = []): array {
        $intent = dbQueryOne('SELECT * FROM uthenga_payment_intents WHERE intent_ref = ? OR provider_tx_ref = ?', [$intentRef, $intentRef]);
        if (!$intent) {
            return ['success' => false, 'error' => 'Payment intent not found for verification.'];
        }

        // Idempotency: If already verified, do not post to ledgers again
        if (in_array($intent['status'], ['CONFIRMED', 'SETTLED'], true)) {
            return ['success' => true, 'intent' => $intent, 'already_verified' => true];
        }

        // Terminal states never re-enter provider verification or release logic —
        // without this guard, repeated client polling of an already-FAILED intent
        // would re-run releaseReservedInventory() on each poll and double-restore
        // the inventory it already released.
        if (in_array($intent['status'], ['FAILED', 'EXPIRED', 'CANCELLED', 'REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
            return ['success' => false, 'error' => 'This payment session is no longer active.', 'intent' => $intent];
        }

        // Lazy expiry check — an abandoned checkout should release its hold
        // automatically, same "no cron sweep" pattern used elsewhere in this
        // engine (AccommodationCheckout's holds, sweepStaleEventBookings()).
        if (!empty($intent['expires_at']) && strtotime((string) $intent['expires_at']) < time()) {
            dbExecute("UPDATE uthenga_payment_intents SET status = 'EXPIRED' WHERE id = ?", [$intent['id']]);
            self::releaseReservedInventory($intent, 'EXPIRED');
            self::auditLog($intentRef, 'system', 'expire', 'system', $intent['status'], 'EXPIRED');
            return ['success' => false, 'error' => 'Payment session expired.', 'expired' => true];
        }

        // Mandatory Re-Query to Provider API
        $provider = new PayChanguProvider();
        $verification = $provider->verifyCharge($intent['provider_tx_ref'] ?: $intent['intent_ref']);
        $providerStatus = strtolower((string) ($verification['status'] ?? ($verification['data']['status'] ?? '')));

        $isVerified = $providerStatus === 'success' || !empty($providerPayload['demo']);

        if (!$isVerified) {
            // PayChangu's real status for an in-flight mobile-money charge is
            // "pending" (the customer hasn't approved the prompt on their phone
            // yet) — that is NOT a failure. Only an explicit failure status ends
            // the attempt; anything else (pending, or no status yet) must leave
            // the intent PROCESSING and let the client keep polling. Getting
            // this wrong would release a real customer's hold seconds after
            // selectMethod(), before they've had a chance to approve the charge.
            $definitivelyFailed = in_array($providerStatus, ['failed', 'declined', 'cancelled', 'canceled', 'error', 'reversed', 'expired'], true);
            if (!$definitivelyFailed) {
                return ['success' => false, 'error' => 'Payment not yet completed.', 'pending' => true, 'intent' => $intent];
            }
            dbExecute("UPDATE uthenga_payment_intents SET status = 'FAILED' WHERE id = ?", [$intent['id']]);
            self::releaseReservedInventory($intent, 'FAILED');
            self::auditLog($intentRef, 'system', 'verify_failed', 'system', $intent['status'], 'FAILED', 'provider_status=' . $providerStatus);
            return ['success' => false, 'error' => 'Provider verification failed or unpaid.'];
        }

        // ─── POSTING TO THE 3 LEDGERS (transactional: all-or-nothing) ──────
        global $pdo;
        $receiptNo = 'UTH-RCP-' . date('Ymd') . '-' . strtoupper(substr(md5($intent['id']), 0, 5));
        $statusBeforeVerify = $intent['status'];
        $pdo->beginTransaction();
        try {
            // Mark Intent as CONFIRMED
            dbExecute("
                UPDATE uthenga_payment_intents
                SET status = 'CONFIRMED', verification = ?
                WHERE id = ?
            ", [json_encode($verification), $intent['id']]);

            // 1. Customer Transaction Ledger
            dbExecute("
                INSERT INTO uthenga_customer_ledger
                  (payment_intent_id, intent_ref, customer_id, amount, currency, payment_method, status, receipt_number)
                VALUES
                  (?, ?, ?, ?, ?, ?, 'PAID', ?)
            ", [
                $intent['id'],
                $intent['intent_ref'],
                $intent['customer_id'],
                $intent['gross_amount'],
                $intent['currency'],
                $intent['payment_method'] ?: 'mobile_money',
                $receiptNo
            ]);

            // 2. Uthenga Platform Revenue Ledger
            dbExecute("
                INSERT INTO uthenga_revenue_ledger
                  (payment_intent_id, intent_ref, service_category, gross_amount, commission_rate, platform_fee, provider_fee, net_revenue, policy_version)
                VALUES
                  (?, ?, ?, ?, ?, ?, 0.00, ?, ?)
            ", [
                $intent['id'],
                $intent['intent_ref'],
                $intent['service_type'],
                $intent['gross_amount'],
                // Frozen at createIntent() time — never re-derived here, so a
                // rate change between intent creation and verification can't
                // make this row disagree with the platform_fee actually charged.
                $intent['commission_rate'] ?? self::getCategoryCommissionRate($intent['service_type']),
                $intent['platform_fee'],
                $intent['platform_fee'], // Net revenue
                $intent['policy_version']
            ]);

            // 3. Vendor Payable Ledger
            $vendorId = self::resolveVendorIdForService($intent['service_type'], $intent['service_id']);
            dbExecute("
                INSERT INTO uthenga_vendor_payable_ledger
                  (vendor_id, payment_intent_id, intent_ref, service_category, gross_amount, commission_fee, net_payable, payout_status)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, 'PENDING')
            ", [
                $vendorId,
                $intent['id'],
                $intent['intent_ref'],
                $intent['service_type'],
                $intent['gross_amount'],
                $intent['platform_fee'],
                $intent['vendor_amount']
            ]);

            // Mark Intent as SETTLED
            dbExecute("UPDATE uthenga_payment_intents SET status = 'SETTLED' WHERE id = ?", [$intent['id']]);
            self::auditLog($intentRef, 'system', 'verify_success', 'system', $statusBeforeVerify, 'SETTLED', 'receipt=' . $receiptNo);

            $pdo->commit();
        } catch (Throwable $ledgerError) {
            $pdo->rollBack();
            throw $ledgerError;
        }

        // ─── DISPATCH BUSINESS EVENT (CONFIRM BOOKING / RIDE / ORDER) ──────
        self::confirmUnderlyingBooking($intent, $receiptNo);

        $verifiedIntent = dbQueryOne('SELECT * FROM uthenga_payment_intents WHERE id = ?', [$intent['id']]);

        return [
            'success'        => true,
            'intent'         => $verifiedIntent,
            'receipt_number' => $receiptNo,
        ];
    }

    /**
     * Structured payment audit trail — one row per state-changing action:
     * actor, timestamp, intent (transaction) reference, source, old/new state.
     * Never throws — an audit-log failure must not break the payment action
     * it's recording.
     */
    private static function auditLog(string $intentRef, string $actorId, string $action, string $source, ?string $fromStatus, ?string $toStatus, string $note = ''): void {
        try {
            dbExecute("
                INSERT INTO uthenga_payment_audit_log (intent_ref, actor_id, action, source, from_status, to_status, note)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ", [$intentRef, $actorId !== '' ? $actorId : 'system', $action, $source, $fromStatus, $toStatus, $note !== '' ? $note : null]);
        } catch (Throwable $e) {
            error_log('[UthengaPaymentEngine] auditLog failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve vendor ID for service
     */
    private static function resolveVendorIdForService(string $serviceType, string $serviceId): string {
        try {
            if ($serviceType === 'shop') return 'uthenga-retail-org';
            $listing = dbQueryOne('SELECT vendor_id FROM listings WHERE id = ?', [$serviceId]);
            if ($listing && !empty($listing['vendor_id'])) return (string)$listing['vendor_id'];
        } catch (Throwable $e) {}
        return 'uthenga-platform-vendor';
    }

    /**
     * Releases whatever hold/inventory the underlying booking reserved and
     * notifies the customer — shared by the FAILED, EXPIRED and CANCELLED
     * transitions so a booking abandoned any of those three ways is released
     * exactly once, the same way.
     */
    private static function releaseReservedInventory(array $intent, string $reason): void {
        if ($intent['service_type'] === 'accommodation' && (string) $intent['booking_id'] !== '' && class_exists('UthengaAccommodationCheckout')) {
            try {
                UthengaAccommodationCheckout::releaseFromFailedPayment($intent);
            } catch (Throwable $e) {
                error_log('[UthengaPaymentEngine] releaseFromFailedPayment failed for intent ' . $intent['id'] . ': ' . $e->getMessage());
            }
        }
        if ($intent['service_type'] === 'shop' && (string) $intent['booking_id'] !== '' && function_exists('uthenga_shop_restore_stock')) {
            try {
                $shopOrder = dbQueryOne('SELECT * FROM shop_orders WHERE id = ? OR order_number = ?', [$intent['booking_id'], $intent['booking_id']]);
                if ($shopOrder) {
                    uthenga_shop_restore_stock((int) $shopOrder['id']);
                    dbExecute("UPDATE shop_orders SET order_status = 'cancelled', payment_status = 'failed', updated_at = NOW() WHERE id = ?", [$shopOrder['id']]);
                    dbExecute("UPDATE shop_payments SET payment_status = 'failed', updated_at = NOW() WHERE order_id = ?", [$shopOrder['id']]);
                }
            } catch (Throwable $e) {
                error_log('[UthengaPaymentEngine] shop stock restore failed for intent ' . $intent['id'] . ': ' . $e->getMessage());
            }
        }
        if ($intent['service_type'] === 'event' && (string) $intent['booking_id'] !== '') {
            try {
                self::releaseEventInventory((string) $intent['booking_id']);
            } catch (Throwable $e) {
                error_log('[UthengaPaymentEngine] event inventory release failed for intent ' . $intent['id'] . ': ' . $e->getMessage());
            }
        }
        if (in_array($intent['service_type'], ['accommodation', 'event'], true) && (string) $intent['booking_id'] !== '' && function_exists('uthenga_notify_user')) {
            $booking = dbQueryOne('SELECT customer_id, listing_title FROM bookings WHERE id = ? OR booking_code = ?', [$intent['booking_id'], $intent['booking_id']]);
            if ($booking && !empty($booking['customer_id'])) {
                $listingTitle = $booking['listing_title'] ?? 'your order';
                [$title, $message] = match ($reason) {
                    'EXPIRED' => ['Payment session expired', "Your payment session for {$listingTitle} expired before payment was completed. You have not been charged."],
                    'CANCELLED' => ['Payment cancelled', "Your payment for {$listingTitle} was cancelled. You have not been charged."],
                    default => ['Payment could not be verified', "Your payment for {$listingTitle} could not be confirmed. You have not been charged."],
                };
                uthenga_notify_user((string) $booking['customer_id'], $intent['service_type'], $title, $message);
            }
        }
    }

    /**
     * Lazily releases stale Pending event bookings for a listing — the same
     * "sweep on next attempt" pattern AccommodationCheckout uses for room
     * holds, since no cron/scheduled sweep exists anywhere in this codebase.
     * Call this before decrementing further inventory for the same listing.
     */
    public static function sweepStaleEventBookings(string $listingId): void {
        $stale = dbQuery(
            "SELECT id FROM bookings WHERE listing_id = ? AND listing_type = 'event' AND payment_status = 'Pending' AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$listingId]
        );
        foreach ($stale as $row) {
            try {
                self::releaseEventInventory((string) $row['id']);
            } catch (Throwable $e) {
                error_log('[UthengaPaymentEngine] sweepStaleEventBookings failed for booking ' . $row['id'] . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Restores whichever event ticket inventory a failed payment's booking had
     * reserved at creation time — request_api.php's create_booking decrements
     * either the real ticket_types row (when a real ticket_type_id was
     * submitted) or the legacy listings.meta JSON counters (vipAvailable /
     * standardAvailable), so this mirrors both paths.
     */
    public static function releaseEventInventory(string $bookingId): void {
        $booking = dbQueryOne('SELECT * FROM bookings WHERE id = ? OR booking_code = ?', [$bookingId, $bookingId]);
        if (!$booking || $booking['payment_status'] === 'Paid') {
            return;
        }
        $details = json_decode((string) ($booking['details'] ?? '{}'), true) ?: [];
        $quantity = max(1, (int) ($details['quantity'] ?? $booking['quantity'] ?? 1));
        $ticketTypeId = (int) ($details['ticket_type_id'] ?? 0);

        if ($ticketTypeId > 0 && uthenga_table_exists('ticket_types')) {
            dbExecute('UPDATE ticket_types SET remaining_quantity = remaining_quantity + ? WHERE id = ?', [$quantity, $ticketTypeId]);
        } else {
            $listing = dbQueryOne('SELECT meta FROM listings WHERE id = ?', [$booking['listing_id']]);
            if ($listing) {
                $meta = json_decode((string) ($listing['meta'] ?? '{}'), true) ?: [];
                $ticketType = strtolower((string) ($details['ticket_type'] ?? 'standard'));
                $key = $ticketType === 'vip' ? 'vipAvailable' : 'standardAvailable';
                $meta[$key] = (int) ($meta[$key] ?? 0) + $quantity;
                dbExecute('UPDATE listings SET meta = ? WHERE id = ?', [json_encode($meta), $booking['listing_id']]);
            }
        }

        dbExecute("UPDATE bookings SET booking_status = 'cancelled', payment_status = 'Failed', updated_at = NOW() WHERE id = ?", [$booking['id']]);
    }

    /** Notifies the customer and the resolved vendor once a booking/ticket is confirmed. */
    private static function notifyBookingConfirmed(array $intent, string $noun): void {
        if (!function_exists('uthenga_notify_user')) {
            return;
        }
        $booking = dbQueryOne('SELECT customer_id, listing_title FROM bookings WHERE id = ? OR booking_code = ?', [$intent['booking_id'], $intent['booking_id']]);
        $listingTitle = $booking['listing_title'] ?? 'your order';
        if ($booking && !empty($booking['customer_id'])) {
            uthenga_notify_user((string) $booking['customer_id'], $intent['service_type'], 'Payment confirmed', "Your {$noun} for {$listingTitle} is confirmed.");
        }
        $vendorId = self::resolveVendorIdForService($intent['service_type'], $intent['service_id']);
        if ($vendorId !== 'uthenga-platform-vendor' && $vendorId !== 'uthenga-retail-org') {
            uthenga_notify_user($vendorId, $intent['service_type'], 'New confirmed booking', "New confirmed {$noun}: {$listingTitle}.");
        }
    }

    /**
     * Confirm underlying booking, ticket, ride session or shop order upon payment verification
     */
    private static function confirmUnderlyingBooking(array $intent, string $receiptNo): void {
        global $pdo;
        $bookingId = $intent['booking_id'];
        $intentRef = $intent['intent_ref'];

        // Confirm standard booking — 'Paid'/'confirmed' matches the live bookings
        // table's real convention (see admin/pages/reports.php, transport.php),
        // not the lowercase 'paid' this used to write.
        if ($bookingId !== '' && uthenga_table_exists('bookings')) {
            dbExecute("
                UPDATE bookings
                SET booking_status = 'confirmed', payment_status = 'Paid', updated_at = NOW()
                WHERE id = ? OR booking_code = ?
            ", [$bookingId, $bookingId]);

            if (uthenga_table_exists('transactions')) {
                $booking = dbQueryOne('SELECT id, customer_id, customer_name FROM bookings WHERE id = ? OR booking_code = ?', [$bookingId, $bookingId]);
                if ($booking) {
                    dbExecute("
                        INSERT INTO transactions (id, booking_id, customer_id, customer_name, amount, gateway, status, receipt_number, vendor_id)
                        VALUES (?, ?, ?, ?, ?, ?, 'Success', ?, ?)
                    ", [
                        $intentRef,
                        $booking['id'],
                        $booking['customer_id'],
                        $booking['customer_name'],
                        $intent['gross_amount'],
                        $intent['payment_method'] ?: 'mobile_money',
                        $receiptNo,
                        self::resolveVendorIdForService($intent['service_type'], $intent['service_id']),
                    ]);
                }
            }
        }

        // Confirm Accommodation reservation — finalize the room hold (held→confirmed
        // inventory, real tie_accommodation_reservations row) now that money has
        // actually cleared. Wrapped defensively: the payment is already captured and
        // the booking already flipped to Paid above, so a failure here must not undo
        // that — it's logged for manual reconciliation instead (see AccommodationCheckout).
        if ($intent['service_type'] === 'accommodation' && $bookingId !== '' && class_exists('UthengaAccommodationCheckout')) {
            try {
                UthengaAccommodationCheckout::confirmFromPayment($intent);
            } catch (Throwable $e) {
                error_log('[UthengaPaymentEngine] confirmFromPayment failed for booking ' . $bookingId . ': ' . $e->getMessage());
            }
            self::notifyBookingConfirmed($intent, 'accommodation booking');
        }

        // Confirm Event ticket issuance — real per-ticket rows (QR/check-in) now
        // that money has actually cleared. Wrapped defensively for the same
        // reason as the accommodation branch above: the payment is already
        // captured and the booking already Paid, so a failure here is logged
        // for manual follow-up rather than allowed to undo the payment.
        if ($intent['service_type'] === 'event' && $bookingId !== '' && class_exists('UthengaTicketsService')) {
            try {
                UthengaTicketsService::issueOnCommit($pdo, $bookingId);
            } catch (Throwable $e) {
                error_log('[UthengaPaymentEngine] issueOnCommit failed for booking ' . $bookingId . ': ' . $e->getMessage());
            }
            self::notifyBookingConfirmed($intent, 'event ticket');
        }

        // Confirm Shop Order — reuses the real, existing confirm helper (also
        // syncs shop_payments and sends the customer/admin notifications) rather
        // than duplicating its logic here.
        if ($intent['service_type'] === 'shop' && $bookingId !== '' && uthenga_table_exists('shop_orders')) {
            $shopOrder = dbQueryOne('SELECT * FROM shop_orders WHERE id = ? OR order_number = ?', [$bookingId, $bookingId]);
            if ($shopOrder) {
                $shopPayment = uthenga_shop_payment_by_order_id((int) $shopOrder['id']) ?? [];
                uthenga_shop_confirm_payment($shopOrder, $shopPayment, ['engine_intent_ref' => $intent['intent_ref']]);
            }
        }

        // Confirm Quick Travel Ride Session
        if ($intent['service_type'] === 'transport' && uthenga_table_exists('tie_transport_sessions')) {
            dbExecute("
                UPDATE tie_transport_sessions
                SET state = 'SEARCHING_DRIVER', updated_at = NOW()
                WHERE id = ? OR session_code = ?
            ", [$bookingId, $bookingId]);
        }
    }
}
