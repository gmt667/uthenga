<?php
/**
 * Bus Operations Center — Revenue tab. Reuses the exact tables the Events
 * Control Center's real Finance tab (Finance.php) already uses for payout
 * accounts and withdrawal requests — tie_payment_accounts/tie_event_withdrawals
 * are genuinely generic (vendor_id only, no event-specific columns or FKs),
 * so a bus vendor's payout account is a real row in the same tables, not a
 * parallel system. The heavier settlement-batch/refund-eligibility-window
 * machinery Finance.php also has is deliberately NOT mirrored here: every
 * paid bus ticket's revenue is honestly immediately available, since this
 * phase has no refund/dispute workflow yet to justify holding it back.
 */
final class UthengaTieBusFinanceService
{
    /** Real, live PayChangu payout directory — matched by name, never hardcoded. */
    private const MOBILE_MONEY_PROVIDERS = ['Airtel Money', 'TNM Mpamba'];

    public function __construct(private ?PDO $db, private UthengaTiePaymentGateway $gateway) {}

    /** A cancelled ticket's seat price is excluded from every revenue figure —
     *  cancelTicket() releases the seat but never touches the booking's own
     *  payment_status, so summing raw bookings.total_price would keep counting
     *  cancelled tickets' money as revenue forever. This is the one real join
     *  every revenue computation in this class needs. */
    private const RECONCILED_REVENUE_JOIN = "FROM tie_bus_tickets t
        INNER JOIN tie_bus_departure_seats ds ON ds.id=t.departure_seat_id
        INNER JOIN tie_bus_departures d ON d.id=t.departure_id
        INNER JOIN listings l ON l.id=d.listing_id
        INNER JOIN bookings b ON b.id=t.booking_id
        WHERE l.vendor_id=? AND l.listing_type='transport' AND b.payment_status='Paid' AND t.status != 'cancelled'";

    public function overview(string $vendorId): array
    {
        $db = $this->db();
        $g = $db->prepare('SELECT COUNT(*) c, COALESCE(SUM(ds.price),0) t ' . self::RECONCILED_REVENUE_JOIN);
        $g->execute([$vendorId]); $gRow = $g->fetch();
        $gross = round((float) $gRow['t'], 2); $paidCount = (int) $gRow['c'];

        $rate = uthenga_finance_commission_rate('transport');
        $commission = round($gross * $rate / 100, 2);
        $net = round($gross - $commission, 2);

        $withdrawnStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM tie_event_withdrawals WHERE vendor_id=? AND status IN ('PROCESSING','PAID')");
        $withdrawnStmt->execute([$vendorId]);
        $withdrawn = round((float) $withdrawnStmt->fetchColumn(), 2);
        $available = max(0.0, round($net - $withdrawn, 2));

        $countsStmt = $db->prepare("SELECT payment_status, COUNT(*) c FROM bookings WHERE listing_id IN (SELECT id FROM listings WHERE vendor_id=? AND listing_type='transport') GROUP BY payment_status");
        $countsStmt->execute([$vendorId]);
        $counts = [];
        foreach ($countsStmt->fetchAll() as $row) $counts[(string) $row['payment_status']] = (int) $row['c'];

        return [
            'schema_version' => 'tie-bus-finance/v1', 'currency' => APP_CURRENCY, 'commission_rate' => $rate,
            'gross_revenue' => $gross, 'commission_amount' => $commission, 'net_revenue' => $net,
            'withdrawn_total' => $withdrawn, 'available_balance' => $available, 'paid_ticket_count' => $paidCount,
            'transaction_counts' => $counts,
        ];
    }

    public function trend(string $vendorId, int $days = 30): array
    {
        $days = max(7, min(90, $days));
        $stmt = $this->db()->prepare('SELECT DATE(t.created_at) d, COALESCE(SUM(ds.price),0) t ' . self::RECONCILED_REVENUE_JOIN . ' AND t.created_at >= (UTC_TIMESTAMP() - INTERVAL ? DAY) GROUP BY DATE(t.created_at)');
        $stmt->execute([$vendorId, $days]);
        $byDate = [];
        foreach ($stmt->fetchAll() as $row) $byDate[(string) $row['d']] = (float) $row['t'];
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $series[] = ['date' => $date, 'amount' => round($byDate[$date] ?? 0.0, 2)];
        }
        return ['schema_version' => 'tie-bus-finance/v1', 'days' => $days, 'series' => $series];
    }

    public function transactions(string $vendorId, int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db()->prepare("SELECT b.id, b.listing_title, b.customer_name, b.total_price, b.payment_gateway, b.payment_status, b.created_at,
                COUNT(t.id) AS ticket_count, SUM(CASE WHEN t.status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN t.status != 'cancelled' THEN ds.price ELSE 0 END) AS net_amount
            FROM bookings b
            LEFT JOIN tie_bus_tickets t ON t.booking_id=b.id
            LEFT JOIN tie_bus_departure_seats ds ON ds.id=t.departure_seat_id
            WHERE b.listing_id IN (SELECT id FROM listings WHERE vendor_id=? AND listing_type='transport')
            GROUP BY b.id, b.listing_title, b.customer_name, b.total_price, b.payment_gateway, b.payment_status, b.created_at
            ORDER BY b.created_at DESC LIMIT {$limit}");
        $stmt->execute([$vendorId]);
        return ['schema_version' => 'tie-bus-finance/v1', 'items' => array_map(fn(array $row) => [
            'booking_id' => (string) $row['id'], 'route' => (string) $row['listing_title'], 'customer_name' => (string) $row['customer_name'],
            'amount' => (int) $row['ticket_count'] > 0 ? round((float) $row['net_amount'], 2) : (float) $row['total_price'],
            'gross_amount' => (float) $row['total_price'], 'cancelled_count' => (int) $row['cancelled_count'],
            'gateway' => (string) $row['payment_gateway'], 'status' => (string) $row['payment_status'],
            'created_at' => $this->utcIso((string) $row['created_at']),
        ], $stmt->fetchAll())];
    }

    public function accounts(string $vendorId): array
    {
        $stmt = $this->db()->prepare('SELECT id, method, label, account_name, account_number, provider, is_default, is_verified FROM tie_payment_accounts WHERE vendor_id=? ORDER BY is_default DESC, created_at ASC');
        $stmt->execute([$vendorId]);
        $items = array_map(fn(array $r) => [
            'id' => (string) $r['id'], 'method' => (string) $r['method'], 'label' => (string) $r['label'],
            'account_number_masked' => '••••' . substr((string) $r['account_number'], -4), 'account_name' => (string) $r['account_name'],
            'provider' => $r['provider'], 'is_default' => (bool) $r['is_default'], 'is_verified' => (bool) $r['is_verified'],
        ], $stmt->fetchAll());
        return ['schema_version' => 'tie-bus-finance/v1', 'items' => $items, 'readiness' => $this->payoutReadiness($vendorId)];
    }

    public function supportedBanks(): array
    {
        try {
            $all = $this->gateway->listSupportedPayoutBanks();
        } catch (Throwable) {
            return ['schema_version' => 'tie-bus-finance/v1', 'mobile_money' => [], 'banks' => [], 'unavailable' => true];
        }
        $mobileMoney = []; $banks = [];
        foreach ($all as $entry) {
            if (in_array($entry['name'], self::MOBILE_MONEY_PROVIDERS, true)) $mobileMoney[] = $entry; else $banks[] = $entry;
        }
        return ['schema_version' => 'tie-bus-finance/v1', 'mobile_money' => $mobileMoney, 'banks' => $banks];
    }

    public function payoutReadiness(string $vendorId): array
    {
        $stmt = $this->db()->prepare("SELECT method, provider FROM tie_payment_accounts WHERE vendor_id=?");
        $stmt->execute([$vendorId]);
        $rows = $stmt->fetchAll();
        $mobileMoney = [];
        foreach (self::MOBILE_MONEY_PROVIDERS as $provider) {
            $mobileMoney[$provider] = (bool) array_filter($rows, fn ($r) => $r['method'] === 'MOBILE_MONEY' && $r['provider'] === $provider);
        }
        $hasBank = (bool) array_filter($rows, fn ($r) => $r['method'] === 'BANK');
        return ['mobile_money' => $mobileMoney, 'mobile_money_ready' => !in_array(false, $mobileMoney, true), 'bank_ready' => $hasBank];
    }

    public function saveAccount(string $vendorId, array $input): array
    {
        $method = strtoupper((string) ($input['method'] ?? 'BANK'));
        if (!in_array($method, ['BANK', 'MOBILE_MONEY'], true)) throw UthengaTieErrors::validation(['method' => 'Choose a valid payout method.']);
        $accountName = trim((string) ($input['account_name'] ?? ''));
        $accountNumber = trim((string) ($input['account_number'] ?? ''));
        $provider = trim((string) ($input['provider'] ?? ''));
        if ($accountName === '' || $accountNumber === '') throw UthengaTieErrors::validation(['account_name' => 'Account name and number are required.']);
        if ($provider === '') throw UthengaTieErrors::validation(['provider' => $method === 'MOBILE_MONEY' ? 'Choose a mobile money operator.' : 'Choose a bank.']);

        // Real validation, never fabricated: the chosen provider must match
        // PayChangu's own live payout registry, and the account/agent code
        // must be a plausible digit string — is_verified only ever becomes
        // true when both checks genuinely pass, since PayChangu has no
        // account-ownership verification API to lean on for anything stronger.
        $registry = array_column($this->gateway->listSupportedPayoutBanks(), 'name');
        if (!in_array($provider, $registry, true)) throw UthengaTieErrors::validation(['provider' => 'Choose a real option from the list — "' . $provider . '" is not recognized.']);
        $isMobileMoneyProvider = in_array($provider, self::MOBILE_MONEY_PROVIDERS, true);
        if ($method === 'MOBILE_MONEY' && !$isMobileMoneyProvider) throw UthengaTieErrors::validation(['provider' => 'Choose Airtel Money or TNM Mpamba for mobile money payouts.']);
        if ($method === 'BANK' && $isMobileMoneyProvider) throw UthengaTieErrors::validation(['provider' => 'Choose a bank, not a mobile money operator, for bank payouts.']);
        if (!preg_match('/^[0-9]{6,20}$/', $accountNumber)) throw UthengaTieErrors::validation(['account_number' => $method === 'MOBILE_MONEY' ? 'Enter a valid agent code (6-20 digits).' : 'Enter a valid account number (6-20 digits).']);

        $label = trim((string) ($input['label'] ?? '')) ?: $provider;
        $id = $this->uuid();
        $this->db()->prepare('INSERT INTO tie_payment_accounts (id, vendor_id, method, label, account_name, account_number, provider, is_default, is_verified, created_at) VALUES (?,?,?,?,?,?,?,?,1,NOW())')
            ->execute([$id, $vendorId, $method, $label, $accountName, $accountNumber, $provider, !empty($input['is_default']) ? 1 : 0]);
        return $this->accounts($vendorId);
    }

    public function withdrawals(string $vendorId): array
    {
        $stmt = $this->db()->prepare('SELECT id, amount, method, destination_label, account_number_masked, status, reference, requested_at, processed_at FROM tie_event_withdrawals WHERE vendor_id=? ORDER BY requested_at DESC LIMIT 200');
        $stmt->execute([$vendorId]);
        return ['schema_version' => 'tie-bus-finance/v1', 'items' => array_map(fn(array $r) => [
            'id' => (string) $r['id'], 'amount' => (float) $r['amount'], 'method' => (string) $r['method'], 'destination_label' => (string) $r['destination_label'],
            'account_number_masked' => (string) $r['account_number_masked'], 'status' => (string) $r['status'], 'reference' => $r['reference'],
            'requested_at' => $this->utcIso((string) $r['requested_at']), 'processed_at' => $r['processed_at'] ? $this->utcIso((string) $r['processed_at']) : null,
        ], $stmt->fetchAll())];
    }

    public function requestWithdrawal(string $vendorId, array $input): array
    {
        $amount = round((float) ($input['amount'] ?? 0), 2);
        $available = $this->overview($vendorId)['available_balance'];
        if ($amount <= 0 || $amount > $available) throw UthengaTieErrors::validation(['amount' => 'Enter a valid amount within your available balance.']);
        $account = null;
        if (!empty($input['account_id'])) {
            $stmt = $this->db()->prepare('SELECT id, method, label, account_number FROM tie_payment_accounts WHERE id=? AND vendor_id=? LIMIT 1');
            $stmt->execute([$input['account_id'], $vendorId]);
            $account = $stmt->fetch();
            if (!is_array($account)) throw UthengaTieErrors::validation(['account_id' => 'Payout account not found.']);
        }
        $method = $account ? (string) $account['method'] : strtoupper((string) ($input['method'] ?? 'BANK'));
        $id = $this->uuid();
        $this->db()->prepare('INSERT INTO tie_event_withdrawals (id, vendor_id, amount, method, destination_label, account_number_masked, status, requested_at) VALUES (?,?,?,?,?,?,?,NOW())')
            ->execute([$id, $vendorId, $amount, $method,
                $account ? ((string) $account['label'] ?: (string) $account['method']) : ((string) ($input['destination'] ?? 'Bank account')),
                $account ? ('••••' . substr((string) $account['account_number'], -4)) : '••••0000',
                'REQUESTED']);
        return $this->withdrawals($vendorId);
    }

    private function utcIso(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('c');
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function db(): PDO
    {
        if (!$this->db instanceof PDO) throw UthengaTieErrors::providerUnavailable('bus_finance');
        return $this->db;
    }
}
