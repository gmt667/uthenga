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
