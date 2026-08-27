<?php
/**
 * Real, customer-configurable payment credentials (PayChangu Direct Charge
 * mobile money + bank transfer) that must exist before a bus ticket
 * purchase is allowed. No card data is ever accepted or stored — PayChangu
 * has no card tokenization, so a "saved card" would require raw PAN/CVV on
 * every charge, which is out of scope (PCI-DSS exposure we don't carry).
 *
 * Mobile money operators are always fetched live from PayChangu
 * (GET /mobile-money) rather than hardcoded, and a customer-submitted
 * operator_ref_id is re-validated against that live list before being
 * trusted — the client is never the source of truth for which operators
 * are real. A bank-transfer method's virtual account is provisioned for
 * real, immediately, via PayChangu's create_permanent_account mode (which
 * — confirmed against the live sandbox — creates no pending transaction,
 * so nothing is fabricated or charged just by adding the method).
 */

final class UthengaTieCustomerPaymentMethodsContracts
{
    public const MAX_METHODS_PER_CUSTOMER = 5;

    public static function normalizeMobile(string $value): string
    {
        $digits = preg_replace('/[^0-9+]/', '', trim($value));
        if (str_starts_with($digits, '+265') && strlen($digits) === 13) return $digits;
        if (str_starts_with($digits, '265') && strlen($digits) === 12) return '+' . $digits;
        if (str_starts_with($digits, '0') && strlen($digits) === 10) return '+265' . substr($digits, 1);
        if (preg_match('/^[0-9]{9}$/', $digits)) return '+265' . $digits;
        throw UthengaTieErrors::validation(['mobile' => 'Enter a valid Malawi mobile number, e.g. 0991234567.']);
    }

    /** PayChangu's charge API rejects the +265-prefixed form we store — it wants the bare 9-digit local number. */
    public static function toGatewayMobile(string $normalizedMobile): string
    {
        return str_starts_with($normalizedMobile, '+265') ? substr($normalizedMobile, 4) : $normalizedMobile;
    }

    public static function maskMobile(string $mobile): string
    {
        $len = strlen($mobile);
        if ($len <= 6) return $mobile;
        return substr($mobile, 0, 4) . '•••••' . substr($mobile, -2);
    }

    public static function newId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

final class UthengaTieCustomerPaymentMethodsService
{
    public function __construct(private ?PDO $db, private UthengaTiePaymentGateway $gateway) {}

    public function listOperators(): array
    {
        try { return ['schema_version' => 'tie-payment-methods/v1', 'operators' => $this->gateway->listMobileMoneyOperators()]; }
        catch (Throwable) { return ['schema_version' => 'tie-payment-methods/v1', 'operators' => [], 'operators_unavailable' => true]; }
    }

    public function list(string $customerId): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM tie_customer_payment_methods WHERE customer_id=? AND status != 'disabled' ORDER BY is_default DESC, created_at ASC");
        $stmt->execute([$customerId]);
        $methods = [];
        foreach ($stmt->fetchAll() as $row) $methods[] = $this->publicMethod($row);
        return ['schema_version' => 'tie-payment-methods/v1', 'methods' => $methods, 'has_configured_method' => count($methods) > 0];
    }

    public function hasConfiguredMethod(string $customerId): bool
    {
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM tie_customer_payment_methods WHERE customer_id=? AND status != 'disabled'");
        $stmt->execute([$customerId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** Ownership-checked lookup used by BusOperations::purchaseTicket() — never trusts a bare id. */
    public function ownedMethod(string $customerId, string $id): array
    {
        $stmt = $this->db()->prepare("SELECT * FROM tie_customer_payment_methods WHERE id=? AND customer_id=? AND status != 'disabled' LIMIT 1");
        $stmt->execute([$id, $customerId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['payment_method_id' => 'Add a payment method before purchasing a ticket.']);
        return $row;
    }

    public function addMobileMoney(string $customerId, array $input): array
    {
        $mobile = UthengaTieCustomerPaymentMethodsContracts::normalizeMobile((string) ($input['mobile'] ?? ''));
        $operatorRefId = trim((string) ($input['operator_ref_id'] ?? ''));
        if ($operatorRefId === '') throw UthengaTieErrors::validation(['operator_ref_id' => 'Select a mobile money operator.']);

        $operators = $this->gateway->listMobileMoneyOperators();
        $operator = null;
        foreach ($operators as $candidate) if ($candidate['ref_id'] === $operatorRefId) { $operator = $candidate; break; }
        if ($operator === null) throw UthengaTieErrors::validation(['operator_ref_id' => 'That mobile money operator is not currently supported.']);

        $this->enforceMethodCap($customerId);
        $id = UthengaTieCustomerPaymentMethodsContracts::newId();
        $isDefault = !$this->hasConfiguredMethod($customerId);
        try {
            $this->db()->prepare('INSERT INTO tie_customer_payment_methods (id, customer_id, channel, mobile_number, operator_ref_id, operator_name, status, is_default) VALUES (?, ?, \'mobile_money\', ?, ?, ?, \'pending_verification\', ?)')
                ->execute([$id, $customerId, $mobile, $operatorRefId, $operator['name'], $isDefault ? 1 : 0]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') throw UthengaTieErrors::validation(['mobile' => 'You already saved this number with this operator.']);
            throw $error;
        }
        return $this->publicMethod($this->rowById($id));
    }

    public function addBankTransfer(string $customerId): array
    {
        $stmt = $this->db()->prepare("SELECT id FROM tie_customer_payment_methods WHERE customer_id=? AND channel='bank_transfer' AND status != 'disabled' LIMIT 1");
        $stmt->execute([$customerId]);
        $existing = $stmt->fetchColumn();
        if ($existing) return $this->publicMethod($this->rowById((string) $existing));

        $this->enforceMethodCap($customerId);
        $customer = $this->customerInfo($customerId);
        $id = UthengaTieCustomerPaymentMethodsContracts::newId();
        $provisioned = $this->gateway->provisionPermanentBankAccount(['amount' => '1000', 'currency' => APP_CURRENCY, 'charge_id' => 'ACCT-' . strtoupper(bin2hex(random_bytes(6))), 'email' => $customer['email'], 'first_name' => $customer['first_name'], 'last_name' => $customer['last_name']]);
        $isDefault = !$this->hasConfiguredMethod($customerId);
        $this->db()->prepare('INSERT INTO tie_customer_payment_methods (id, customer_id, channel, bank_name, account_number, account_name, provider_reference, status, is_default, verified_at) VALUES (?, ?, \'bank_transfer\', ?, ?, ?, ?, \'active\', ?, NOW())')
            ->execute([$id, $customerId, $provisioned['bank_name'], $provisioned['account_number'], $provisioned['account_name'], $provisioned['customer_reference'], $isDefault ? 1 : 0]);
        return $this->publicMethod($this->rowById($id));
    }

    public function remove(string $customerId, string $id): array
    {
        $method = $this->ownedMethod($customerId, $id);
        $this->db()->prepare("UPDATE tie_customer_payment_methods SET status='disabled', is_default=0 WHERE id=?")->execute([$id]);
        if ((int) $method['is_default'] === 1) {
            $next = $this->db()->prepare("SELECT id FROM tie_customer_payment_methods WHERE customer_id=? AND status != 'disabled' ORDER BY created_at ASC LIMIT 1");
            $next->execute([$customerId]);
            $nextId = $next->fetchColumn();
            if ($nextId) $this->db()->prepare('UPDATE tie_customer_payment_methods SET is_default=1 WHERE id=?')->execute([$nextId]);
        }
        return $this->list($customerId);
    }

    public function setDefault(string $customerId, string $id): array
    {
        $this->ownedMethod($customerId, $id);
        $this->db()->prepare('UPDATE tie_customer_payment_methods SET is_default=0 WHERE customer_id=?')->execute([$customerId]);
        $this->db()->prepare('UPDATE tie_customer_payment_methods SET is_default=1 WHERE id=?')->execute([$id]);
        return $this->list($customerId);
    }

    public function recordProvisionedBankAccount(string $methodId, string $bankName, string $accountNumber, string $accountName, string $providerReference): void
    {
        $this->db()->prepare("UPDATE tie_customer_payment_methods SET bank_name=?, account_number=?, account_name=?, provider_reference=?, status='active' WHERE id=?")
            ->execute([$bankName, $accountNumber, $accountName, $providerReference, $methodId]);
    }

    public function markVerified(string $methodId): void
    {
        $this->db()->prepare("UPDATE tie_customer_payment_methods SET status='active', verified_at=NOW() WHERE id=? AND verified_at IS NULL")->execute([$methodId]);
    }

    private function enforceMethodCap(string $customerId): void
    {
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM tie_customer_payment_methods WHERE customer_id=? AND status != 'disabled'");
        $stmt->execute([$customerId]);
        if ((int) $stmt->fetchColumn() >= UthengaTieCustomerPaymentMethodsContracts::MAX_METHODS_PER_CUSTOMER) throw UthengaTieErrors::validation(['payment_method' => 'You have reached the maximum number of saved payment methods.']);
    }

    private function customerInfo(string $customerId): array
    {
        $stmt = $this->db()->prepare('SELECT name, email FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$customerId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::authentication();
        $parts = preg_split('/\s+/', trim((string) $row['name']), 2);
        return ['email' => (string) $row['email'], 'first_name' => (string) ($parts[0] ?? 'Customer'), 'last_name' => (string) ($parts[1] ?? '')];
    }

    private function rowById(string $id): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tie_customer_payment_methods WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::providerUnavailable('customer_payment_methods');
        return $row;
    }

    private function publicMethod(array $row): array
    {
        $out = ['id' => $row['id'], 'channel' => $row['channel'], 'status' => $row['status'], 'is_default' => (bool) $row['is_default'], 'verified' => $row['verified_at'] !== null, 'created_at' => $this->utcIso((string) $row['created_at'])];
        if ($row['channel'] === 'mobile_money') { $out['mobile_number_masked'] = UthengaTieCustomerPaymentMethodsContracts::maskMobile((string) $row['mobile_number']); $out['operator_name'] = $row['operator_name']; }
        else { $out['bank_name'] = $row['bank_name']; $out['account_number'] = $row['account_number']; $out['account_name'] = $row['account_name']; }
        return $out;
    }

    private function utcIso(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
    }

    private function db(): PDO
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('customer_payment_methods');
        return $this->db;
    }
}
