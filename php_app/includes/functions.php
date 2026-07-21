<?php
/**
 * Uthenga — Shared Helper Functions
 * PHP 7.3+ compatible — no match(), never, or union types
 * Include this after config.php and db.php
 */

/**
 * Render star rating as Unicode characters
 */
if (!function_exists('renderStars')) {
    function renderStars($rating) {
        $rating = (float) $rating;
        $full   = (int) floor($rating);
        $half   = (($rating - $full) >= 0.5) ? 1 : 0;
        $empty  = 5 - $full - $half;
        return str_repeat('★', $full)
             . str_repeat('½', $half)
             . str_repeat('☆', $empty);
    }
}

/**
 * Get display price for an event listing
 */
if (!function_exists('getEventPrice')) {
    function getEventPrice($listing) {
        $meta = is_array($listing['meta']) ? $listing['meta'] : json_decode($listing['meta'], true);
        $std  = (float) ($meta['standardTicketPrice'] ?? 0);
        if ($std == 0) {
            return 'Free Event';
        }
        return 'From ' . formatMWK($std);
    }
}

/**
 * Get display price for any listing type (PHP 7.3 version of match())
 */
if (!function_exists('getListingPrice')) {
    function getListingPrice($listing) {
        $meta = is_array($listing['meta']) ? $listing['meta'] : json_decode($listing['meta'], true);
        $type = $listing['type'] ?? ($listing['listing_type'] ?? '');
        if ($type === 'event') {
            $p = (float) ($meta['standardTicketPrice'] ?? 0);
            return $p > 0 ? 'From ' . formatMWK($p) : 'Free';
        }
        if ($type === 'accommodation') {
            $rooms = $meta['rooms'] ?? [];
            $p     = (float) ($rooms[0]['pricePerNight'] ?? 0);
            return formatMWK($p) . '/night';
        }
        if ($type === 'tour') {
            $p = (float) ($meta['pricePerPerson'] ?? 0);
            return formatMWK($p) . '/person';
        }
        if ($type === 'transport') {
            $p = (float) ($meta['pricePerSeat'] ?? 0);
            return formatMWK($p) . '/seat';
        }
        return 'MK 0';
    }
}

/**
 * Format a date string for display
 */
if (!function_exists('formatDate')) {
    function formatDate($dateStr, $format = 'D, d M Y') {
        if (empty($dateStr)) {
            return 'TBC';
        }
        $ts = strtotime($dateStr);
        if ($ts === false) {
            return htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8');
        }
        return date($format, $ts);
    }
}

/**
 * Get the base booking price for a listing (PHP 7.3 compatible)
 */
if (!function_exists('getBasePrice')) {
    function getBasePrice($type, $meta) {
        if ($type === 'accommodation') {
            $type = 'property';
        }
        if ($type === 'event') {
            return (float) ($meta['standardTicketPrice'] ?? 0);
        }
        if ($type === 'property') {
            $rooms = $meta['rooms'] ?? [];
            return (float) ($rooms[0]['pricePerNight'] ?? 0);
        }
        if ($type === 'tour') {
            return (float) ($meta['pricePerPerson'] ?? 0);
        }
        if ($type === 'transport') {
            return (float) ($meta['pricePerSeat'] ?? 0);
        }
        return 0.0;
    }
}

/**
 * Get active advertisements for a placement slot
 */
if (!function_exists('getActiveAds')) {
    function getActiveAds($placement = 'banner', $limit = 5) {
        try {
            return dbQuery(
                "SELECT * FROM advertisements
                 WHERE status = 'active'
                   AND ad_type = ?
                   AND start_date <= CURDATE()
                   AND end_date >= CURDATE()
                 ORDER BY id DESC
                 LIMIT " . (int) $limit,
                [$placement]
            );
        } catch (Exception $e) {
            return [];
        }
    }
}

/**
 * Generate a unique gate session ID
 */
if (!function_exists('generateSessionId')) {
    function generateSessionId() {
        return 'GS-' . strtoupper(bin2hex(random_bytes(6)));
    }
}

/**
 * Get the active gate session for an event (if any)
 */
if (!function_exists('getActiveSession')) {
    function getActiveSession($listingId) {
        try {
            return dbQueryOne(
                "SELECT * FROM gate_sessions
                 WHERE listing_id = ? AND status IN ('active','paused')
                 ORDER BY started_at DESC LIMIT 1",
                [$listingId]
            );
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('uthenga_booking_btn_label')) {
    function uthenga_booking_btn_label(string $listingType, bool $immediate = false): string {
        $labels = [
            'event' => 'Buy Ticket',
            'accommodation' => 'Book Now',
            'property' => 'Book Now',
            'tour' => 'Book Tour',
            'transport' => 'Book Seat',
        ];

        $label = $labels[$listingType] ?? 'Book Now';
        if ($immediate) {
            return 'Book & Pay Now';
        }

        return $label;
    }
}

if (!function_exists('uthenga_finance_normalize_listing_type')) {
    function uthenga_finance_normalize_listing_type(string $listingType): string {
        $listingType = strtolower(trim($listingType));
        if (in_array($listingType, ['property', 'accommodation', 'hotel', 'lodging', 'stay'], true)) {
            return 'accommodation';
        }
        if (in_array($listingType, ['event', 'tour', 'transport'], true)) {
            return $listingType;
        }
        return 'event';
    }
}

if (!function_exists('uthenga_finance_commission_rate_key')) {
    function uthenga_finance_commission_rate_key(string $listingType): string {
        switch (uthenga_finance_normalize_listing_type($listingType)) {
            case 'accommodation':
                return 'commission_rate_accommodation';
            case 'tour':
                return 'commission_rate_tour';
            case 'transport':
                return 'commission_rate_transport';
            case 'event':
            default:
                return 'commission_rate_event';
        }
    }
}

if (!function_exists('uthenga_finance_service_fee_key')) {
    function uthenga_finance_service_fee_key(string $listingType): string {
        switch (uthenga_finance_normalize_listing_type($listingType)) {
            case 'accommodation':
                return 'service_fee_accommodation';
            case 'tour':
                return 'service_fee_tour';
            case 'transport':
                return 'service_fee_transport';
            case 'event':
            default:
                return 'service_fee_event';
        }
    }
}

if (!function_exists('uthenga_finance_commission_rate')) {
    function uthenga_finance_commission_rate(string $listingType): float {
        $rate = getSetting(uthenga_finance_commission_rate_key($listingType), null);
        if ($rate === null || $rate === '') {
            $rate = getSetting('commission_rate', COMMISSION_RATE);
        }
        return max(0.0, (float) $rate);
    }
}

if (!function_exists('uthenga_finance_service_fee')) {
    function uthenga_finance_service_fee(string $listingType): float {
        $fee = getSetting(uthenga_finance_service_fee_key($listingType), null);
        if ($fee === null || $fee === '') {
            $fee = getSetting('service_fee', 0);
        }
        return max(0.0, (float) $fee);
    }
}

if (!function_exists('uthenga_finance_split_amounts')) {
    function uthenga_finance_split_amounts(float $grossAmount, string $listingType): array {
        $grossAmount = max(0.0, round($grossAmount, 2));
        $commissionRate = uthenga_finance_commission_rate($listingType);
        $serviceFee = uthenga_finance_service_fee($listingType);
        $commissionAmount = round(($grossAmount * $commissionRate) / 100, 2);
        $vendorNetAmount = round(max(0.0, $grossAmount - $commissionAmount), 2);
        $platformRevenue = round($commissionAmount + $serviceFee, 2);

        return [
            'gross_amount' => $grossAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'service_fee' => $serviceFee,
            'vendor_net_amount' => $vendorNetAmount,
            'platform_revenue' => $platformRevenue,
            'customer_total' => round($grossAmount + $serviceFee, 2),
        ];
    }
}

if (!function_exists('uthenga_vendor_record_for_user')) {
    function uthenga_vendor_record_for_user(int $userId) {
        if ($userId <= 0) {
            return null;
        }

        if (!uthenga_table_exists('vendors')) {
            return null;
        }

        return dbQueryOne('SELECT * FROM vendors WHERE user_id = ? LIMIT 1', [$userId]) ?: null;
    }
}

if (!function_exists('uthenga_finance_ensure_vendor_wallet')) {
    function uthenga_finance_ensure_vendor_wallet(int $vendorId, string $currency = 'MWK') {
        if ($vendorId <= 0 || !uthenga_table_exists('vendor_wallets')) {
            return null;
        }

        $wallet = dbQueryOne('SELECT * FROM vendor_wallets WHERE vendor_id = ? LIMIT 1', [$vendorId]);
        if (!$wallet) {
            dbExecute(
                'INSERT INTO vendor_wallets (vendor_id, currency, balance, pending_balance, created_at, updated_at)
                 VALUES (?, ?, 0, 0, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE currency = VALUES(currency), updated_at = NOW()',
                [$vendorId, $currency]
            );
            $wallet = dbQueryOne('SELECT * FROM vendor_wallets WHERE vendor_id = ? LIMIT 1', [$vendorId]);
        }

        return $wallet ?: null;
    }
}

if (!function_exists('uthenga_finance_record_commission')) {
    function uthenga_finance_record_commission(array $data): bool {
        if (!uthenga_table_exists('commissions')) {
            return false;
        }

        $bookingId = (int)($data['booking_id'] ?? 0);
        $vendorId = (int)($data['vendor_id'] ?? 0);
        if ($bookingId <= 0 || $vendorId <= 0) {
            return false;
        }

        $existing = dbQueryOne('SELECT id FROM commissions WHERE booking_id = ? AND vendor_id = ? LIMIT 1', [$bookingId, $vendorId]);
        $payload = [
            $bookingId,
            $vendorId,
            (float)($data['gross_amount'] ?? 0),
            (float)($data['commission_rate'] ?? 0),
            (float)($data['commission_amount'] ?? 0),
            (float)($data['net_vendor_amount'] ?? 0),
            $data['settlement_status'] ?? 'pending',
            $data['settled_at'] ?? null,
        ];

        try {
            if ($existing) {
                dbExecute(
                    'UPDATE commissions
                     SET gross_amount = ?, commission_rate = ?, commission_amount = ?, net_vendor_amount = ?,
                         settlement_status = ?, settled_at = ?, created_at = created_at
                     WHERE id = ?',
                    [
                        $payload[2],
                        $payload[3],
                        $payload[4],
                        $payload[5],
                        $payload[6],
                        $payload[7],
                        $existing['id'],
                    ]
                );
                return true;
            }

            dbExecute(
                'INSERT INTO commissions
                    (booking_id, vendor_id, gross_amount, commission_rate, commission_amount, net_vendor_amount, settlement_status, settled_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                $payload
            );
            return true;
        } catch (Throwable $e) {
            error_log('[Uthenga finance commission] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('uthenga_finance_credit_vendor_wallet')) {
    function uthenga_finance_credit_vendor_wallet(int $vendorId, float $amount, string $currency = 'MWK', string $bucket = 'pending'): bool {
        if ($vendorId <= 0 || $amount <= 0 || !uthenga_table_exists('vendor_wallets')) {
            return false;
        }

        uthenga_finance_ensure_vendor_wallet($vendorId, $currency);
        $amount = round($amount, 2);
        $bucket = strtolower(trim($bucket));

        if ($bucket === 'available') {
            return dbExecute(
                'UPDATE vendor_wallets SET balance = balance + ?, currency = ?, updated_at = NOW() WHERE vendor_id = ?',
                [$amount, $currency, $vendorId]
            );
        }

        return dbExecute(
            'UPDATE vendor_wallets SET pending_balance = pending_balance + ?, currency = ?, updated_at = NOW() WHERE vendor_id = ?',
            [$amount, $currency, $vendorId]
        );
    }
}

if (!function_exists('uthenga_finance_reverse_vendor_wallet')) {
    function uthenga_finance_reverse_vendor_wallet(int $vendorId, float $amount, string $currency = 'MWK', string $bucket = 'pending'): bool {
        if ($vendorId <= 0 || $amount <= 0 || !uthenga_table_exists('vendor_wallets')) {
            return false;
        }

        $amount = round($amount, 2);
        $bucket = strtolower(trim($bucket));

        if ($bucket === 'available') {
            return dbExecute(
                'UPDATE vendor_wallets SET balance = GREATEST(0, balance - ?), currency = ?, updated_at = NOW() WHERE vendor_id = ?',
                [$amount, $currency, $vendorId]
            );
        }

        return dbExecute(
            'UPDATE vendor_wallets SET pending_balance = GREATEST(0, pending_balance - ?), currency = ?, updated_at = NOW() WHERE vendor_id = ?',
            [$amount, $currency, $vendorId]
        );
    }
}

if (!function_exists('uthenga_finance_record_sale')) {
    function uthenga_finance_record_sale(array $context): array {
        $bookingId = (int)($context['booking_id'] ?? 0);
        $vendorId = (int)($context['vendor_id'] ?? 0);
        $listingType = uthenga_finance_normalize_listing_type((string)($context['listing_type'] ?? 'event'));
        $currency = trim((string)($context['currency'] ?? 'MWK')) ?: 'MWK';
        $grossAmount = max(0.0, (float)($context['gross_amount'] ?? 0));
        $discountAmount = max(0.0, (float)($context['discount_amount'] ?? 0));
        $netAmount = max(0.0, round($grossAmount - $discountAmount, 2));
        $split = uthenga_finance_split_amounts($netAmount, $listingType);
        $serviceFee = (float)$split['service_fee'];
        $customerTotal = round($netAmount + $serviceFee, 2);
        $transactionReference = trim((string)($context['transaction_reference'] ?? ''));
        $transactionId = trim((string)($context['transaction_id'] ?? ''));
        $status = strtolower(trim((string)($context['status'] ?? 'pending')));
        $walletBucket = strtolower(trim((string)($context['wallet_bucket'] ?? 'pending')));

        if ($bookingId <= 0 || $vendorId <= 0) {
            return [
                'success' => false,
                'message' => 'Missing booking or vendor reference.',
            ];
        }

        $existing = uthenga_table_exists('commissions')
            ? dbQueryOne('SELECT * FROM commissions WHERE booking_id = ? AND vendor_id = ? LIMIT 1', [$bookingId, $vendorId])
            : null;

        if ($existing) {
            return [
                'success' => true,
                'already_recorded' => true,
                'commission' => $existing,
                'wallet_bucket' => $walletBucket,
                'customer_total' => $customerTotal,
                'platform_revenue' => (float)($existing['commission_amount'] ?? 0) + $serviceFee,
            ];
        }

        uthenga_finance_ensure_vendor_wallet($vendorId, $currency);

        uthenga_finance_record_commission([
            'booking_id' => $bookingId,
            'vendor_id' => $vendorId,
            'gross_amount' => $netAmount,
            'commission_rate' => $split['commission_rate'],
            'commission_amount' => $split['commission_amount'],
            'net_vendor_amount' => $split['vendor_net_amount'],
            'settlement_status' => 'pending',
        ]);

        uthenga_finance_credit_vendor_wallet($vendorId, (float)$split['vendor_net_amount'], $currency, $walletBucket);

        if ($transactionReference !== '' && uthenga_table_exists('transactions')) {
            $metadata = [
                'booking_id' => $bookingId,
                'vendor_id' => $vendorId,
                'listing_type' => $listingType,
                'gross_amount' => $netAmount,
                'discount_amount' => $discountAmount,
                'service_fee' => $serviceFee,
                'commission_rate' => $split['commission_rate'],
                'commission_amount' => $split['commission_amount'],
                'vendor_net_amount' => $split['vendor_net_amount'],
                'platform_revenue' => $split['platform_revenue'],
                'customer_total' => $customerTotal,
                'status' => $status,
            ];

            dbExecute(
                'UPDATE transactions
                 SET vendor_id = ?, amount = ?, metadata = ?, updated_at = NOW()
                 WHERE transaction_reference = ? OR id = ?',
                [
                    $vendorId,
                    $customerTotal,
                    json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    $transactionReference,
                    $transactionId,
                ]
            );
        }

        return [
            'success' => true,
            'already_recorded' => false,
            'commission_rate' => $split['commission_rate'],
            'commission_amount' => $split['commission_amount'],
            'service_fee' => $serviceFee,
            'vendor_net_amount' => $split['vendor_net_amount'],
            'customer_total' => $customerTotal,
            'platform_revenue' => $split['platform_revenue'],
        ];
    }
}

if (!function_exists('uthenga_finance_context_from_booking')) {
    function uthenga_finance_context_from_booking(array $transaction): array {
        $bookingId = (int)($transaction['booking_id'] ?? 0);
        if ($bookingId <= 0) {
            return [];
        }

        $booking = dbQueryOne('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$bookingId]);
        if (!$booking) {
            return [];
        }

        $item = null;
        if (uthenga_table_exists('booking_items')) {
            $item = dbQueryOne('SELECT * FROM booking_items WHERE booking_id = ? ORDER BY id ASC LIMIT 1', [$bookingId]) ?: null;
        }

        $itemType = (string)($item['item_type'] ?? '');
        $listingType = 'event';
        if ($itemType === 'property_room') {
            $listingType = 'accommodation';
        } elseif ($itemType === 'transport_seat') {
            $listingType = 'transport';
        } elseif ($itemType === 'tour_package') {
            $listingType = 'tour';
        } elseif ($itemType === 'vendor_service') {
            $listingType = 'event';
        }

        $vendorId = (int)($item['vendor_id'] ?? 0);
        $details = json_decode((string)($booking['details'] ?? '{}'), true);
        $details = is_array($details) ? $details : [];

        return [
            'booking_id' => $bookingId,
            'vendor_id' => $vendorId,
            'listing_type' => $listingType,
            'currency' => (string)($booking['currency'] ?? $transaction['currency'] ?? 'MWK'),
            'gross_amount' => (float)($booking['total_amount'] ?? $booking['grand_total'] ?? $transaction['amount'] ?? 0),
            'discount_amount' => (float)($booking['discount_amount'] ?? 0),
            'service_fee' => (float)($booking['tax_amount'] ?? 0),
            'transaction_reference' => (string)($transaction['transaction_reference'] ?? $transaction['id'] ?? ''),
            'transaction_id' => (string)($transaction['id'] ?? ''),
            'status' => (string)($transaction['status'] ?? 'success'),
            'details' => $details,
            'booking' => $booking,
            'item' => $item,
        ];
    }
}

if (!function_exists('uthenga_finance_reverse_sale')) {
    function uthenga_finance_reverse_sale(array $booking, array $commission = null): bool {
        $bookingId = (int)($booking['id'] ?? 0);
        if ($bookingId <= 0 || !uthenga_table_exists('commissions')) {
            return false;
        }

        $commissionRow = $commission ?: dbQueryOne('SELECT * FROM commissions WHERE booking_id = ? LIMIT 1', [$bookingId]);
        if (!$commissionRow) {
            return false;
        }

        $vendorId = (int)($commissionRow['vendor_id'] ?? 0);
        $amount = (float)($commissionRow['net_vendor_amount'] ?? 0);
        if ($vendorId > 0 && $amount > 0) {
            $bucket = strtolower((string)($commissionRow['settlement_status'] ?? 'pending')) === 'settled' ? 'available' : 'pending';
            uthenga_finance_reverse_vendor_wallet($vendorId, $amount, (string)($booking['currency'] ?? 'MWK'), $bucket);
        }

        dbExecute(
            "UPDATE commissions
             SET settlement_status = 'reversed',
                 settled_at = NULL
             WHERE booking_id = ?",
            [$bookingId]
        );

        return true;
    }
}

if (!function_exists('uthenga_record_transaction_analytics')) {
    function uthenga_record_transaction_analytics(array $txn, string $eventType = 'created'): bool {
        if (!uthenga_table_exists('transaction_analytics')) {
            return false;
        }

        $transactionReference = trim((string)($txn['transaction_reference'] ?? $txn['id'] ?? ''));
        if ($transactionReference === '') {
            return false;
        }

        $status = strtolower(trim((string)($txn['status'] ?? 'pending')));
        if ($status === '') {
            $status = 'pending';
        }

        $paymentMethod = trim((string)($txn['gateway'] ?? $txn['gateway_name'] ?? $txn['payment_gateway'] ?? ''));
        if ($paymentMethod === '') {
            $paymentMethod = 'unknown';
        }

        $amount = (float)($txn['amount'] ?? 0);
        $bookingId = $txn['booking_id'] ?? null;
        $userId = $txn['user_id'] ?? null;
        $recordedAt = $txn['created_at'] ?? date('Y-m-d H:i:s');
        $recordDate = substr((string)$recordedAt, 0, 10);
        $recordMonth = substr((string)$recordedAt, 0, 7);
        $bookingCount = !empty($bookingId) ? 1 : 0;

        try {
            dbExecute(
                'INSERT INTO transaction_analytics
                    (transaction_reference, booking_id, user_id, payment_method, payment_status, amount, booking_count, event_type, event_timestamp, event_date, event_month, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    booking_id = VALUES(booking_id),
                    user_id = VALUES(user_id),
                    payment_method = VALUES(payment_method),
                    payment_status = VALUES(payment_status),
                    amount = VALUES(amount),
                    booking_count = VALUES(booking_count),
                    event_type = VALUES(event_type),
                    event_timestamp = VALUES(event_timestamp),
                    event_date = VALUES(event_date),
                    event_month = VALUES(event_month),
                    updated_at = NOW()',
                [
                    $transactionReference,
                    $bookingId,
                    $userId,
                    $paymentMethod,
                    $status,
                    $amount,
                    $bookingCount,
                    $eventType,
                    $recordedAt,
                    $recordDate,
                    $recordMonth,
                ]
            );
            return true;
        } catch (Throwable $e) {
            error_log('[Uthenga analytics] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('uthenga_transaction_status_summary')) {
    function uthenga_transaction_status_summary(int $days = 30): array {
        $days = max(1, $days);
        $summary = [
            'total_transactions' => 0,
            'successful_payments' => 0,
            'failed_payments' => 0,
            'pending_payments' => 0,
            'revenue' => 0.0,
            'booking_count' => 0,
            'daily' => [],
            'monthly' => [],
            'by_method' => [],
        ];

        if (!uthenga_table_exists('transaction_analytics')) {
            return $summary;
        }

        try {
            $row = dbQueryOne(
                "SELECT
                    COUNT(*) AS total_transactions,
                    COALESCE(SUM(CASE WHEN LOWER(payment_status) IN ('success','paid') THEN 1 ELSE 0 END), 0) AS successful_payments,
                    COALESCE(SUM(CASE WHEN LOWER(payment_status) = 'failed' THEN 1 ELSE 0 END), 0) AS failed_payments,
                    COALESCE(SUM(CASE WHEN LOWER(payment_status) = 'pending' THEN 1 ELSE 0 END), 0) AS pending_payments,
                    COALESCE(SUM(CASE WHEN LOWER(payment_status) IN ('success','paid') THEN amount ELSE 0 END), 0) AS revenue,
                    COALESCE(SUM(booking_count), 0) AS booking_count
                 FROM transaction_analytics"
            );
            if ($row) {
                $summary['total_transactions'] = (int)($row['total_transactions'] ?? 0);
                $summary['successful_payments'] = (int)($row['successful_payments'] ?? 0);
                $summary['failed_payments'] = (int)($row['failed_payments'] ?? 0);
                $summary['pending_payments'] = (int)($row['pending_payments'] ?? 0);
                $summary['revenue'] = (float)($row['revenue'] ?? 0);
                $summary['booking_count'] = (int)($row['booking_count'] ?? 0);
            }

            $summary['daily'] = dbQuery(
                "SELECT event_date, COUNT(*) AS total_transactions,
                        COALESCE(SUM(CASE WHEN LOWER(payment_status) IN ('success','paid') THEN 1 ELSE 0 END), 0) AS successful_payments,
                        COALESCE(SUM(CASE WHEN LOWER(payment_status) = 'failed' THEN 1 ELSE 0 END), 0) AS failed_payments,
                        COALESCE(SUM(CASE WHEN LOWER(payment_status) = 'pending' THEN 1 ELSE 0 END), 0) AS pending_payments,
                        COALESCE(SUM(CASE WHEN LOWER(payment_status) IN ('success','paid') THEN amount ELSE 0 END), 0) AS revenue,
                        COALESCE(SUM(booking_count), 0) AS booking_count
                 FROM transaction_analytics
                 WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 GROUP BY event_date
                 ORDER BY event_date ASC",
                [$days]
            ) ?: [];

            $summary['monthly'] = dbQuery(
                "SELECT event_month, COUNT(*) AS total_transactions,
                        COALESCE(SUM(CASE WHEN LOWER(payment_status) IN ('success','paid') THEN amount ELSE 0 END), 0) AS revenue,
                        COALESCE(SUM(booking_count), 0) AS booking_count
                 FROM transaction_analytics
                 GROUP BY event_month
                 ORDER BY event_month DESC
                 LIMIT 12"
            ) ?: [];

            $summary['by_method'] = dbQuery(
                "SELECT payment_method, COUNT(*) AS total_transactions,
                        COALESCE(SUM(CASE WHEN LOWER(payment_status) IN ('success','paid') THEN 1 ELSE 0 END), 0) AS successful_payments,
                        COALESCE(SUM(CASE WHEN LOWER(payment_status) = 'failed' THEN 1 ELSE 0 END), 0) AS failed_payments,
                        COALESCE(SUM(CASE WHEN LOWER(payment_status) = 'pending' THEN 1 ELSE 0 END), 0) AS pending_payments,
                        COALESCE(SUM(CASE WHEN LOWER(payment_status) IN ('success','paid') THEN amount ELSE 0 END), 0) AS revenue
                 FROM transaction_analytics
                 GROUP BY payment_method
                 ORDER BY revenue DESC, total_transactions DESC"
            ) ?: [];
        } catch (Throwable $e) {
            error_log('[Uthenga analytics summary] ' . $e->getMessage());
        }

        return $summary;
    }
}
