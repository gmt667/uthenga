<?php
/**
 * Uthenga Payment Engine — Provider Adapter Architecture
 * Clean abstraction separating Uthenga Pay Core from PayChangu Infrastructure.
 *
 * PayChanguProvider delegates to UthengaTiePaychanguGateway (includes/tie/Payment.php)
 * — the same, already-proven PayChangu client used by the AI Trip Planner — rather
 * than maintaining a second, independent PayChangu API client. This is intentional:
 * the platform payment architecture forbids duplicate per-service payment
 * implementations, and the standalone client this replaced called the wrong
 * PayChangu endpoint for mobile-money verification and silently fell back to a
 * mock "success" response whenever its secret key was empty.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/tie/bootstrap.php';

interface PaymentProviderInterface {
    public function initiateCharge(array $params): array;
    public function verifyCharge(string $txRef): array;
    public function getChargeStatus(string $txRef): array;
    public function refundCharge(string $txRef, float $amount): array;
}

/**
 * PayChangu Infrastructure Provider Implementation
 */
class PayChanguProvider implements PaymentProviderInterface {
    private UthengaTiePaymentGateway $gateway;

    public function __construct() {
        $this->gateway = UthengaTiePaychanguGatewayFactory::configured();
    }

    public function initiateCharge(array $params): array {
        $method   = strtolower(trim((string)($params['method'] ?? 'airtel')));
        $amount   = (float)($params['amount'] ?? 0);
        $currency = strtoupper(trim((string)($params['currency'] ?? 'MWK')));
        $txRef    = trim((string)($params['tx_ref'] ?? ''));
        $phone    = trim((string)($params['phone'] ?? ''));
        $email    = trim((string)($params['email'] ?? 'customer@uthenga.mw'));
        $name     = trim((string)($params['name'] ?? 'Uthenga Customer'));

        try {
            // Mobile Money (Airtel Money / TNM Mpamba)
            if (in_array($method, ['airtel', 'mpamba', 'mobile_money'], true)) {
                $operatorRefId = $this->resolveMobileMoneyOperatorRefId($method, $phone);
                $result = $this->gateway->chargeMobileMoney([
                    'mobile'          => $phone,
                    'operator_ref_id' => $operatorRefId,
                    'amount'          => $amount,
                    'charge_id'       => $txRef,
                    'email'           => $email,
                    'first_name'      => explode(' ', $name)[0] ?: 'Customer',
                    'last_name'       => explode(' ', $name)[1] ?? 'Uthenga',
                ]);
                return ['status' => 'success', 'data' => ['tx_ref' => $result['charge_id'], 'operator_status' => $result['status'], 'reference' => $result['reference']]];
            }

            // Bank Transfer (transient, amount-specific virtual account)
            if ($method === 'bank') {
                $result = $this->gateway->chargeBankTransfer([
                    'amount'    => $amount,
                    'currency'  => $currency,
                    'charge_id' => $txRef,
                    'email'     => $email,
                ]);
                return ['status' => 'success', 'data' => ['tx_ref' => $result['charge_id'], 'bank_name' => $result['bank_name'], 'account_number' => $result['account_number'], 'account_name' => $result['account_name'], 'expires_at' => $result['expires_at']]];
            }

            // Default: hosted checkout redirect
            $result = $this->gateway->checkout([
                'id'              => $txRef,
                'amount'          => $amount,
                'currency'        => $currency,
                'provider_tx_ref' => $txRef,
                'quote_hash'      => hash('sha256', $txRef . '|' . $amount),
            ]);
            return ['status' => 'success', 'data' => ['tx_ref' => $result['provider_reference'], 'checkout_url' => $result['checkout_url']]];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    public function verifyCharge(string $txRef): array {
        try {
            $paymentMethod = strtolower(trim((string)($this->lookupPaymentMethod($txRef))));
            if (in_array($paymentMethod, ['airtel', 'mpamba', 'mobile_money'], true)) {
                $result = $this->gateway->verifyMobileMoneyCharge($txRef);
            } else {
                $result = $this->gateway->verify($txRef);
            }
            return ['status' => $result['status'] === 'success' ? 'success' : $result['status'], 'data' => $result];
        } catch (Throwable $e) {
            // A thrown error here (provider unreachable, an unknown-reference
            // lookup for a charge the customer hasn't completed yet, etc.) is a
            // communication failure, not the provider telling us the charge was
            // declined — mirrors BusOperations::reconcilePayment()'s identical
            // reasoning for the same gateway. Reporting it as 'pending' keeps
            // the engine's poll loop waiting rather than releasing a real hold
            // over a transient/technical error.
            return ['status' => 'pending', 'message' => $e->getMessage()];
        }
    }

    public function getChargeStatus(string $txRef): array {
        return $this->verifyCharge($txRef);
    }

    public function refundCharge(string $txRef, float $amount): array {
        // No refund endpoint is exposed by UthengaTiePaymentGateway yet — refunds
        // for this engine are out of scope for the "consolidate the engine" phase.
        return ['status' => 'failed', 'message' => 'Refunds are not yet supported by the Uthenga Payment Engine.'];
    }

    private function resolveMobileMoneyOperatorRefId(string $method, string $phone): string {
        $isMpamba = $method === 'mpamba'
            || str_starts_with($phone, '088') || str_starts_with($phone, '089')
            || str_starts_with($phone, '26588') || str_starts_with($phone, '26589');
        foreach ($this->gateway->listMobileMoneyOperators() as $operator) {
            $name = strtolower($operator['name']);
            if ($isMpamba && str_contains($name, 'tnm')) return $operator['ref_id'];
            if (!$isMpamba && str_contains($name, 'airtel')) return $operator['ref_id'];
        }
        return '';
    }

    /** The stored payment_method on the intent decides which verify endpoint applies. */
    private function lookupPaymentMethod(string $txRef): string {
        if (!function_exists('dbQueryOne')) return '';
        $intent = dbQueryOne('SELECT payment_method FROM uthenga_payment_intents WHERE provider_tx_ref = ? OR intent_ref = ?', [$txRef, $txRef]);
        return $intent['payment_method'] ?? '';
    }
}

/**
 * Mock Provider for Local Sandbox & Fallback Testing
 */
class MockPaymentProvider implements PaymentProviderInterface {
    public function initiateCharge(array $params): array {
        $method = $params['method'] ?? 'airtel';
        $txRef  = $params['tx_ref'] ?? ('UTH-' . strtoupper(bin2hex(random_bytes(4))));

        if ($method === 'bank') {
            return [
                'status' => 'success',
                'message' => 'Virtual bank account details generated.',
                'data' => [
                    'bank_name' => 'National Bank of Malawi',
                    'account_number' => '1004829103',
                    'account_name' => 'Uthenga Payments Escrow',
                    'reference' => $txRef,
                    'expires_in_minutes' => 60,
                ]
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Payment push notification dispatched to mobile device.',
            'data' => [
                'tx_ref' => $txRef,
                'operator_status' => 'PENDING_AUTHORIZATION',
                'prompt_message' => 'Please confirm the USSD prompt on your phone.',
            ]
        ];
    }

    public function verifyCharge(string $txRef): array {
        return [
            'status' => 'success',
            'message' => 'Payment verified successfully (Mock Engine).',
            'data' => [
                'tx_ref' => $txRef,
                'status' => 'success',
                'currency' => 'MWK',
                'paid_at' => date('Y-m-d H:i:s'),
            ]
        ];
    }

    public function getChargeStatus(string $txRef): array {
        return $this->verifyCharge($txRef);
    }

    public function refundCharge(string $txRef, float $amount): array {
        return [
            'status' => 'success',
            'message' => "Refunded MK $amount for transaction $txRef.",
        ];
    }
}
