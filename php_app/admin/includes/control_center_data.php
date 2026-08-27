<?php
/**
 * Authoritative data assembler for the Admin Control Center.
 *
 * This file intentionally contains no state-changing operations. It adapts to
 * both the legacy compatible schema and the newer normalized schema so the
 * dashboard never needs to manufacture operational figures when a table or
 * metric has not been instrumented yet.
 */

if (!function_exists('acc_safe_count')) {
    function acc_safe_count(string $sql, array $params = []): int {
        try { return dbCount($sql, $params); } catch (Throwable $error) { return 0; }
    }
}

if (!function_exists('acc_safe_row')) {
    function acc_safe_row(string $sql, array $params = []): array {
        try { return dbQueryOne($sql, $params) ?: []; } catch (Throwable $error) { return []; }
    }
}

if (!function_exists('acc_safe_rows')) {
    function acc_safe_rows(string $sql, array $params = []): array {
        try { return dbQuery($sql, $params); } catch (Throwable $error) { return []; }
    }
}

if (!function_exists('acc_control_center_data')) {
    function acc_control_center_data(): array {
        $hasUsers = uthenga_table_exists('users');
        $hasBookings = uthenga_table_exists('bookings');
        $hasListings = uthenga_table_exists('listings');
        $hasTransactions = uthenga_table_exists('transactions');
        $hasVendorProfiles = uthenga_table_exists('vendor_profiles');
        $hasVendors = uthenga_table_exists('vendors');
        $hasTelemetry = uthenga_table_exists('tie_metric_events');
        $hasTraces = uthenga_table_exists('tie_request_traces');
        $hasShopProducts = uthenga_table_exists('shop_products');
        $hasShopOrders = uthenga_table_exists('shop_orders');
        $bookingTime = $hasBookings && uthenga_column_exists('bookings', 'booked_at') ? 'booked_at' : 'created_at';
        $bookingAmount = $hasBookings && uthenga_column_exists('bookings', 'grand_total') ? 'grand_total' : 'total_price';
        $commissionColumn = $hasBookings && uthenga_column_exists('bookings', 'commission_amount') ? 'commission_amount' : null;

        $userStatusClause = $hasUsers && uthenga_column_exists('users', 'account_status') ? " AND COALESCE(account_status, 'active') <> 'deleted'" : '';
        $customers = $hasUsers ? acc_safe_count("SELECT COUNT(*) FROM users WHERE role = 'Customer'$userStatusClause") : 0;
        $vendors = 0;
        if ($hasVendors) {
            $vendorActiveClause = uthenga_column_exists('vendors', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
            $vendors = acc_safe_count("SELECT COUNT(*) FROM vendors WHERE status = 'approved'$vendorActiveClause");
        }
        elseif ($hasVendorProfiles) $vendors = acc_safe_count("SELECT COUNT(*) FROM vendor_profiles WHERE approval_status = 'approved'");
        elseif ($hasUsers) $vendors = acc_safe_count("SELECT COUNT(*) FROM users WHERE role IN ('Vendor','Event Organizer','Hotel/Lodge Manager','Tour Operator','Transport Provider') AND COALESCE(is_approved, 0) = 1");

        $activeBookings = $hasBookings ? acc_safe_count("SELECT COUNT(*) FROM bookings WHERE LOWER(booking_status) IN ('pending','confirmed')") : 0;
        $todayRevenue = $hasBookings ? (float) (acc_safe_row("SELECT COALESCE(SUM($bookingAmount),0) AS total FROM bookings WHERE DATE($bookingTime) = CURRENT_DATE() AND LOWER(payment_status) IN ('paid','authorized','success')")['total'] ?? 0) : 0.0;
        $todayCommission = $hasBookings && $commissionColumn !== null ? (float) (acc_safe_row("SELECT COALESCE(SUM($commissionColumn),0) AS total FROM bookings WHERE DATE($bookingTime) = CURRENT_DATE() AND LOWER(payment_status) IN ('paid','authorized','success')")['total'] ?? 0) : null;

        $pendingVendors = $hasVendors ? acc_safe_count("SELECT COUNT(*) FROM vendors WHERE status = 'pending'" . (uthenga_column_exists('vendors', 'deleted_at') ? ' AND deleted_at IS NULL' : '')) : ($hasVendorProfiles ? acc_safe_count("SELECT COUNT(*) FROM vendor_profiles WHERE approval_status = 'pending'") : 0);
        $paymentExceptions = $hasTransactions ? acc_safe_count("SELECT COUNT(*) FROM transactions WHERE LOWER(status) IN ('pending','failed')") : 0;
        $refundRequests = uthenga_table_exists('refunds') ? acc_safe_count("SELECT COUNT(*) FROM refunds WHERE LOWER(status) IN ('pending','approved')") : 0;
        $supportCases = uthenga_table_exists('support_tickets') ? acc_safe_count("SELECT COUNT(*) FROM support_tickets WHERE LOWER(status) IN ('open','in_progress','waiting_customer')") : 0;
        $suspendedListings = $hasListings && uthenga_column_exists('listings', 'is_active') ? acc_safe_count("SELECT COUNT(*) FROM listings WHERE is_active = 0") : 0;

        $listingDistribution = [];
        if ($hasListings) {
            $listingActive = uthenga_column_exists('listings', 'is_active') ? 'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)' : 'COUNT(*)';
            $listingDistribution = acc_safe_rows("SELECT listing_type, COUNT(*) AS total, $listingActive AS active_count FROM listings GROUP BY listing_type ORDER BY total DESC");
        }

        $vendorRows = [];
        if ($hasVendorProfiles && $hasUsers) {
            $business = uthenga_column_exists('vendor_profiles', 'business_name') ? 'COALESCE(vp.business_name, u.name)' : 'u.name';
            $vendorRows = acc_safe_rows("SELECT vp.vendor_id AS id, $business AS business_name, u.name AS owner_name, u.email, COALESCE(vp.category, u.role) AS category, vp.approval_status AS status, vp.created_at FROM vendor_profiles vp INNER JOIN users u ON u.id = vp.vendor_id ORDER BY FIELD(LOWER(vp.approval_status),'pending','rejected','approved'), vp.created_at ASC LIMIT 12");
        } elseif ($hasVendors && $hasUsers) {
            $vendorDeletedClause = uthenga_column_exists('vendors', 'deleted_at') ? ' WHERE v.deleted_at IS NULL' : '';
            $vendorRows = acc_safe_rows("SELECT v.user_id AS id, COALESCE(v.business_name, u.name) AS business_name, u.name AS owner_name, u.email, u.role AS category, v.status, v.created_at FROM vendors v INNER JOIN users u ON u.id = v.user_id$vendorDeletedClause ORDER BY FIELD(LOWER(v.status),'pending','rejected','approved'), v.created_at ASC LIMIT 12");
        }

        $recentBookings = [];
        if ($hasBookings) {
            $code = uthenga_column_exists('bookings', 'booking_code') ? 'booking_code' : 'id';
            $recentBookings = acc_safe_rows("SELECT id, $code AS booking_code, booking_status, payment_status, $bookingAmount AS grand_total, $bookingTime AS occurred_at FROM bookings ORDER BY $bookingTime DESC LIMIT 12");
        }

        $currencyColumn = $hasTransactions && uthenga_column_exists('transactions', 'currency') ? 'currency' : "'MWK'";
        $recentPayments = $hasTransactions ? acc_safe_rows("SELECT transaction_reference, status, amount, $currencyColumn AS currency, created_at FROM transactions ORDER BY created_at DESC LIMIT 8") : [];

        $paymentSummary = [
            'today_volume' => 0.0,
            'successful' => 0,
            'pending' => 0,
            'failed' => 0,
            'refunded' => 0,
            'success_rate' => null,
        ];
        if ($hasTransactions) {
            $paymentDate = uthenga_column_exists('transactions', 'transaction_date') ? 'transaction_date' : 'created_at';
            $summary = acc_safe_row("SELECT
                COALESCE(SUM(CASE WHEN DATE($paymentDate) = CURRENT_DATE() AND LOWER(status) IN ('successful','success','paid','captured','authorized') THEN amount ELSE 0 END), 0) AS today_volume,
                SUM(CASE WHEN LOWER(status) IN ('successful','success','paid','captured','authorized') THEN 1 ELSE 0 END) AS successful,
                SUM(CASE WHEN LOWER(status) IN ('pending','processing','initiated') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN LOWER(status) IN ('failed','declined','cancelled') THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN LOWER(status) IN ('refunded','partially_refunded') THEN 1 ELSE 0 END) AS refunded,
                COUNT(*) AS total
                FROM transactions");
            $paymentSummary = [
                'today_volume' => (float) ($summary['today_volume'] ?? 0),
                'successful' => (int) ($summary['successful'] ?? 0),
                'pending' => (int) ($summary['pending'] ?? 0),
                'failed' => (int) ($summary['failed'] ?? 0),
                'refunded' => (int) ($summary['refunded'] ?? 0),
                'success_rate' => (int) ($summary['total'] ?? 0) > 0
                    ? round(((int) ($summary['successful'] ?? 0) / (int) $summary['total']) * 100, 1)
                    : null,
            ];
        }

        $inventoryQuality = [
            'total' => 0,
            'complete' => 0,
            'missing_coordinates' => 0,
            'missing_location' => 0,
            'inactive' => 0,
            'invalid' => 0,
            'complete_percent' => null,
        ];
        if ($hasListings) {
            $quality = acc_safe_row("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN NULLIF(TRIM(title), '') IS NOT NULL AND NULLIF(TRIM(location), '') IS NOT NULL AND gps_lat IS NOT NULL AND gps_lng IS NOT NULL THEN 1 ELSE 0 END) AS complete,
                SUM(CASE WHEN gps_lat IS NULL OR gps_lng IS NULL THEN 1 ELSE 0 END) AS missing_coordinates,
                SUM(CASE WHEN NULLIF(TRIM(location), '') IS NULL THEN 1 ELSE 0 END) AS missing_location,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN NULLIF(TRIM(title), '') IS NULL OR listing_type NOT IN ('accommodation','event','transport','tour') THEN 1 ELSE 0 END) AS invalid
                FROM listings");
            $inventoryQuality = [
                'total' => (int) ($quality['total'] ?? 0),
                'complete' => (int) ($quality['complete'] ?? 0),
                'missing_coordinates' => (int) ($quality['missing_coordinates'] ?? 0),
                'missing_location' => (int) ($quality['missing_location'] ?? 0),
                'inactive' => (int) ($quality['inactive'] ?? 0),
                'invalid' => (int) ($quality['invalid'] ?? 0),
                'complete_percent' => (int) ($quality['total'] ?? 0) > 0
                    ? round(((int) ($quality['complete'] ?? 0) / (int) $quality['total']) * 100, 1)
                    : null,
            ];
        }

        $customerRows = $hasUsers ? acc_safe_rows("SELECT id, name, email, account_status, last_login_at, created_at FROM users WHERE role = 'Customer' ORDER BY created_at DESC LIMIT 10") : [];
        $supportRows = uthenga_table_exists('support_tickets')
            ? acc_safe_rows("SELECT id, ticket_code, subject, priority, status, category, created_at FROM support_tickets WHERE deleted_at IS NULL ORDER BY FIELD(LOWER(status),'open','in_progress','waiting_customer','closed'), created_at DESC LIMIT 10")
            : [];
        $auditRows = uthenga_table_exists('audit_logs')
            ? acc_safe_rows("SELECT id, user_name, user_role, action, details, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 12")
            : [];
        $notificationSummary = ['stored' => 0, 'unread' => 0, 'delivery_instrumented' => false];
        if (uthenga_table_exists('notifications')) {
            $notificationSummary['stored'] = acc_safe_count('SELECT COUNT(*) FROM notifications');
            $notificationSummary['unread'] = acc_safe_count('SELECT COUNT(*) FROM notifications WHERE is_read = 0');
        }
        $activity = [];
        foreach ($recentBookings as $booking) $activity[] = ['at' => (string)($booking['occurred_at'] ?? ''), 'type' => 'Booking', 'title' => 'Booking ' . (string)($booking['booking_code'] ?? $booking['id']), 'detail' => ucfirst((string)($booking['booking_status'] ?? 'recorded')) . ' · ' . (string)($booking['payment_status'] ?? 'payment pending')];
        foreach ($recentPayments as $payment) $activity[] = ['at' => (string)($payment['created_at'] ?? ''), 'type' => 'Payment', 'title' => 'Payment ' . (string)($payment['transaction_reference'] ?? ''), 'detail' => ucfirst((string)($payment['status'] ?? 'recorded')) . ' · ' . (string)($payment['currency'] ?? 'MWK') . ' ' . number_format((float)($payment['amount'] ?? 0))];
        foreach (array_slice($vendorRows, 0, 5) as $vendor) $activity[] = ['at' => (string)($vendor['created_at'] ?? ''), 'type' => 'Vendor', 'title' => (string)($vendor['business_name'] ?? 'Vendor profile'), 'detail' => ucfirst((string)($vendor['status'] ?? 'pending')) . ' vendor profile'];
        usort($activity, static fn(array $a, array $b): int => strcmp($b['at'], $a['at']));

        $tieEnabled = filter_var((string) uthenga_env('TIE_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
        $configuredAiProvider = trim((string) uthenga_env('TIE_AI_PROVIDER', ''));
        $configuredAiModel = trim((string) uthenga_env('TIE_AI_MODEL', uthenga_env('TIE_LLM_MODEL', '')));
        $health = [
            ['name' => 'Marketplace database', 'status' => $hasUsers && $hasListings ? 'healthy' : 'warning', 'detail' => $hasUsers && $hasListings ? 'Connected' : 'Schema capability missing'],
            ['name' => 'Booking engine', 'status' => $hasBookings ? 'healthy' : 'warning', 'detail' => $hasBookings ? 'Booking records available' : 'Bookings table unavailable'],
            ['name' => 'Payments', 'status' => $hasTransactions || uthenga_table_exists('tie_payment_intents') ? 'unknown' : 'warning', 'detail' => $hasTransactions || uthenga_table_exists('tie_payment_intents') ? 'Configured; provider probe not instrumented' : 'No payment ledger found'],
            ['name' => 'TIE intelligence', 'status' => $tieEnabled ? 'unknown' : 'warning', 'detail' => $tieEnabled ? 'Enabled; live health probe not instrumented' : 'Disabled or not configured'],
            ['name' => 'Notifications', 'status' => uthenga_table_exists('notifications') ? 'unknown' : 'warning', 'detail' => uthenga_table_exists('notifications') ? 'Queue storage available; delivery probe not instrumented' : 'Notification storage unavailable'],
        ];
        if (uthenga_table_exists('system_health_logs')) {
            $latestHealth = acc_safe_rows("SELECT health_key, status, notes, recorded_at FROM system_health_logs WHERE id IN (SELECT MAX(id) FROM system_health_logs GROUP BY health_key) ORDER BY recorded_at DESC LIMIT 8");
            foreach ($latestHealth as $record) $health[] = ['name' => (string)$record['health_key'], 'status' => (string)$record['status'], 'detail' => (string)($record['notes'] ?: ('Recorded ' . $record['recorded_at']))];
        }

        $telemetry = [
            'recording' => $hasTelemetry,
            'trace_recording' => $hasTraces,
            'requests_today' => null,
            'ai_requests_today' => null,
            'ai_latency_ms' => null,
            'provider_failures_today' => null,
            'input_tokens_today' => null,
            'output_tokens_today' => null,
            'recent_traces' => [],
            'providers' => [],
            'configuration' => [
                'tie_enabled' => $tieEnabled,
                'ai_provider' => $configuredAiProvider !== '' ? $configuredAiProvider : null,
                'ai_model' => $configuredAiModel !== '' ? $configuredAiModel : null,
            ],
        ];
        if ($hasTelemetry) {
            $summary = acc_safe_row("SELECT
                COALESCE(SUM(CASE WHEN metric = 'requests' THEN value ELSE 0 END), 0) AS requests_today,
                COALESCE(SUM(CASE WHEN metric = 'ai_chat_successful_responses' THEN value ELSE 0 END), 0) AS ai_requests_today,
                AVG(CASE WHEN metric = 'ai_chat_latency_ms' THEN value END) AS ai_latency_ms,
                COALESCE(SUM(CASE WHEN metric IN ('ai_provider_failures', 'provider_failures') THEN value ELSE 0 END), 0) AS provider_failures_today,
                COALESCE(SUM(CASE WHEN metric = 'ai_input_tokens' THEN value ELSE 0 END), 0) AS input_tokens_today,
                COALESCE(SUM(CASE WHEN metric = 'ai_output_tokens' THEN value ELSE 0 END), 0) AS output_tokens_today
                FROM tie_metric_events WHERE created_at >= CURRENT_DATE()");
            foreach (['requests_today', 'ai_requests_today', 'provider_failures_today', 'input_tokens_today', 'output_tokens_today'] as $key) $telemetry[$key] = (int) ($summary[$key] ?? 0);
            $telemetry['ai_latency_ms'] = isset($summary['ai_latency_ms']) ? (float) $summary['ai_latency_ms'] : null;
            $telemetry['providers'] = acc_safe_rows("SELECT COALESCE(NULLIF(provider_name, ''), 'not recorded') AS provider, COUNT(*) AS events, MAX(created_at) AS last_seen FROM tie_metric_events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY COALESCE(NULLIF(provider_name, ''), 'not recorded') ORDER BY events DESC LIMIT 8");
        }
        if ($hasTraces) {
            $telemetry['recent_traces'] = acc_safe_rows("SELECT request_id, module_name, feature_name, status_name, provider_name, model_name, duration_ms, error_type, created_at FROM tie_request_traces ORDER BY updated_at DESC LIMIT 12");
        }

        return [
            'metrics' => ['customers' => $customers, 'vendors' => $vendors, 'active_bookings' => $activeBookings, 'today_revenue' => $todayRevenue, 'today_commission' => $todayCommission, 'active_journeys' => null],
            'attention' => [
                ['key' => 'vendors', 'label' => 'Vendor applications', 'count' => $pendingVendors, 'priority' => 'high', 'href' => 'admin/vendors.php'],
                ['key' => 'payments', 'label' => 'Payment exceptions', 'count' => $paymentExceptions, 'priority' => 'high', 'href' => 'admin/payments.php?status=pending'],
                ['key' => 'support', 'label' => 'Open support cases', 'count' => $supportCases, 'priority' => 'medium', 'href' => 'admin/support.php'],
                ['key' => 'refunds', 'label' => 'Refund requests', 'count' => $refundRequests, 'priority' => 'medium', 'href' => 'admin/payments.php?status=refunded'],
                ['key' => 'listings', 'label' => 'Suspended listings', 'count' => $suspendedListings, 'priority' => 'low', 'href' => 'admin/listings.php'],
            ],
            'listing_distribution' => $listingDistribution,
            'inventory_quality' => $inventoryQuality,
            'vendors' => $vendorRows,
            'customers' => $customerRows,
            'bookings' => $recentBookings,
            'payments' => ['summary' => $paymentSummary, 'recent' => $recentPayments],
            'support' => $supportRows,
            'notifications' => $notificationSummary,
            'audit' => $auditRows,
            'activity' => array_slice($activity, 0, 12),
            'health' => $health,
            'telemetry' => $telemetry,
            'capabilities' => [
                'shop' => $hasShopProducts || $hasShopOrders,
                'journeys' => uthenga_table_exists('tie_journeys'),
                'payment_ledger' => $hasTransactions || uthenga_table_exists('tie_payment_intents'),
                'refund_workflow' => uthenga_table_exists('refunds'),
                'notification_delivery' => false,
                'telemetry' => $hasTelemetry,
                'request_tracing' => $hasTraces,
            ],
        ];
    }
}
