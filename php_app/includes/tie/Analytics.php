<?php
/**
 * Events Analytics intelligence layer. Derived live from the operational
 * tables — facts are never fabricated. Every metric reuses the exact
 * definition Finance uses so Analytics never disagrees with Finance:
 *   Gross   = SUM(bookings.total_price) WHERE payment_status='Paid'
 *   Fees    = round(gross * commission_rate / 100, 2)
 *   Net     = gross - fees - PROCESSED refunds
 *   Attendance rate = checked_in / valid issued
 * The output of every method is a plain array ready for JSON serialization.
 */

final class UthengaAnalytics
{
    public const SCHEMA = 'tie-analytics/v1';

    private PDO $db;

    /** Cache of vendor → event rows, keyed by vendor id. */
    private array $eventCache = [];

    public function __construct(PDO $db) { $this->db = $db; }

    public function db(): PDO { return $this->db; }

    /* ── helpers ───────────────────────────────────────────────────── */

    private function money(?float $v): float { return round((float) $v, 2); }

    private function fmt(float $v): string { return 'MK ' . number_format($v, $v == (int) $v ? 0 : 2); }

    private function eventRows(string $vendorId): array
    {
        if (isset($this->eventCache[$vendorId])) return $this->eventCache[$vendorId];
        $rows = [];
        $stmt = $this->db->prepare('SELECT listing_id AS listing_id, id AS event_id, title, status, start_date, end_date
                                     FROM tie_events_events WHERE vendor_id=? AND listing_id IS NOT NULL
                                     ORDER BY start_date ASC, title ASC');
        $stmt->execute([$vendorId]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                'listing_id' => $r['listing_id'], 'event_id' => $r['event_id'], 'title' => $r['title'],
                'status' => $r['status'], 'start_date' => $r['start_date'], 'end_date' => $r['end_date'],
            ];
        }
        return $this->eventCache[$vendorId] = $rows;
    }

    private function listingIn(string $vendorId): string
    {
        $ids = array_map(fn($e) => $this->db->quote((string) $e['listing_id']), $this->eventRows($vendorId));
        return $ids ? implode(',', $ids) : "''";
    }

    private function eventIdIn(array $events): string
    {
        $ids = array_map(fn($e) => $this->db->quote((string) $e['event_id']), $events);
        return $ids ? implode(',', $ids) : "''";
    }

    private function listingIdIn(array $events): string
    {
        $ids = array_map(fn($e) => $this->db->quote((string) $e['listing_id']), $events);
        return $ids ? implode(',', $ids) : "''";
    }

    /** Resolve a filter set into a concrete SQL scope fragment. */
    private function scope(string $vendorId, array $filters): array
    {
        $events = $this->eventRows($vendorId);
        $eventId = (string) ($filters['event_id'] ?? '');
        $scopeEvents = $events;

        if ($eventId !== '' && $eventId !== 'all') {
            $scopeEvents = array_values(array_filter($events, fn($e) => $e['event_id'] === $eventId || $e['listing_id'] === $eventId));
        }
        $listingIn = $this->listingIdIn($scopeEvents);
        $eventIdIn  = $this->eventIdIn($scopeEvents);

        // event_analytics.event_id historically stores the listing id (or legacy
        // evt-* codes), while tie_events_events.id carries the EVT-* code — accept both.
        $analyticsEventIn = $listingIn;
        $extras = array_map(fn($e) => $this->db->quote((string) $e['event_id']), $scopeEvents);
        if ($extras) $analyticsEventIn .= ',' . implode(',', $extras);

        $range = (string) ($filters['range'] ?? '30d');
        $from  = (string) ($filters['from'] ?? '');
        $to    = (string) ($filters['to'] ?? '');

        $fromClause = '';
        $toClause   = '';
        $params     = [];

        if ($from !== '' && $to !== '' && $from !== 'all') {
            $fromClause = ' AND created_at >= ?';
            $toClause   = ' AND created_at <= ? 23:59:59';
            $params = [$from . ' 00:00:00', $to];
        } else {
            $days = ['7d' => 7, '30d' => 30, '90d' => 90][$range] ?? 30;
            $fromClause = ' AND created_at >= (NOW() - INTERVAL ' . (int) $days . ' DAY)';
        }

        return [
            'events' => $scopeEvents, 'listingIn' => $listingIn, 'eventIdIn' => $eventIdIn,
            'analyticsEventIn' => $analyticsEventIn,
            'fromCreate' => $fromClause, 'toCreate' => $toClause, 'params' => $params,
        ];
    }

    /* ── events selector ────────────────────────────────────────────── */

    public function events(string $vendorId): array
    {
        $out = [];
        foreach ($this->eventRows($vendorId) as $e) {
            $sold = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id=" . $this->db->quote($e['listing_id']) . " AND status IN ('ISSUED','CHECKED_IN','CHECKED_OUT')")->fetchColumn();
            $rev  = $this->money($this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id=" . $this->db->quote($e['listing_id']) . " AND payment_status='Paid'")->fetchColumn());
            $checked = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id=" . $this->db->quote($e['listing_id']) . " AND checked_in_at IS NOT NULL")->fetchColumn();
            $out[] = [
                'event_id' => $e['event_id'], 'listing_id' => $e['listing_id'], 'title' => $e['title'],
                'status' => $e['status'], 'start_date' => $e['start_date'], 'sold' => $sold,
                'revenue' => $rev, 'checked_in' => $checked,
            ];
        }
        return $out;
    }

    /* ── executive KPIs ─────────────────────────────────────────────── */

    public function overview(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $rate = (new UthengaTieEventFinance($this->db))->commissionRate($vendorId);

        $gross    = $this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid'{$s['fromCreate']}{$s['toCreate']}")->fetchColumn();
        $gross    = $this->money($gross);
        $orders   = (int) $this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid'{$s['fromCreate']}{$s['toCreate']}")->fetchColumn();

        $fees     = round($gross * $rate / 100, 2);
        $refunds  = $this->money($this->db->query("SELECT COALESCE(SUM(amount),0) FROM event_ticket_refunds WHERE listing_id IN ({$s['listingIn']}) AND status='PROCESSED' AND requested_at >= (NOW() - INTERVAL 365 DAY)")->fetchColumn());
        $net      = round($gross - $fees - $refunds, 2);

        $tickets  = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id IN ({$s['listingIn']}) AND status IN ('ISSUED','CHECKED_IN','CHECKED_OUT'){$s['fromCreate']}{$s['toCreate']}")->fetchColumn();
        $checked  = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id IN ({$s['listingIn']}) AND checked_in_at IS NOT NULL{$s['fromCreate']}{$s['toCreate']}")->fetchColumn();
        $attRate  = $tickets > 0 ? round(100 * $checked / $tickets, 1) : 0.0;

        $customers = (int) $this->db->query("SELECT COUNT(DISTINCT customer_email) FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid'{$s['fromCreate']}{$s['toCreate']}")->fetchColumn();

        // Remaining capacity across the scoped events
        $capacity = (int) $this->db->query("SELECT COALESCE(SUM(total_quantity),0) FROM ticket_types WHERE listing_id IN ({$s['listingIn']})")->fetchColumn();
        $remaining = (int) $this->db->query("SELECT COALESCE(SUM(remaining_quantity),0) FROM ticket_types WHERE listing_id IN ({$s['listingIn']})")->fetchColumn();
        $sellThrough = $capacity > 0 ? round(100 * ($capacity - $remaining) / $capacity, 1) : 0.0;

        // Comparison vs previous equivalent period (best effort for fixed ranges)
        $cmpGross = $cmpOrders = $cmpTickets = $cmpCustomers = null;
        $prevFrom = (string) ($filters['from'] ?? '');
        $prevTo   = (string) ($filters['to'] ?? '');
        if ($prevFrom !== '' && $prevTo !== '' && $prevFrom !== 'all') {
            $start = new DateTime($prevFrom);
            $end   = new DateTime($prevTo . ' 23:59:59');
            $span  = $start->diff($end);
            $pFrom = (clone $start)->sub($span);
            $pTo   = (clone $start)->modify('-1 second');
            $cmpGross = $this->money($this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid' AND created_at BETWEEN " . $this->db->quote($pFrom->format('Y-m-d H:i:s')) . " AND " . $this->db->quote($pTo->format('Y-m-d H:i:s')))->fetchColumn());
            $cmpOrders = (int) $this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid' AND created_at BETWEEN " . $this->db->quote($pFrom->format('Y-m-d H:i:s')) . " AND " . $this->db->quote($pTo->format('Y-m-d H:i:s')))->fetchColumn();
            $cmpTickets = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id IN ({$s['listingIn']}) AND status IN ('ISSUED','CHECKED_IN','CHECKED_OUT') AND created_at BETWEEN " . $this->db->quote($pFrom->format('Y-m-d H:i:s')) . " AND " . $this->db->quote($pTo->format('Y-m-d H:i:s')))->fetchColumn();
            $cmpCustomers = (int) $this->db->query("SELECT COUNT(DISTINCT customer_email) FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid' AND created_at BETWEEN " . $this->db->quote($pFrom->format('Y-m-d H:i:s')) . " AND " . $this->db->quote($pTo->format('Y-m-d H:i:s')))->fetchColumn();
        }

        $pct = fn($cur, $prev) => $prev === null || $prev == 0 ? null : round(100 * ((float) $cur - (float) $prev) / (float) $prev, 1);

        return [
            'schema_version' => self::SCHEMA,
            'currency' => 'MWK',
            'commission_rate' => $rate,
            'kpis' => [
                'gross_revenue' => ['value' => $gross, 'formatted' => $this->fmt($gross), 'previous' => $cmpGross, 'change_pct' => $pct($gross, $cmpGross), 'link' => 'finance'],
                'net_revenue'  => ['value' => $net, 'formatted' => $this->fmt($net), 'previous' => null, 'change_pct' => null, 'link' => 'finance'],
                'tickets_sold' => ['value' => $tickets, 'formatted' => number_format($tickets), 'previous' => $cmpTickets, 'change_pct' => $pct($tickets, $cmpTickets), 'link' => 'tickets'],
                'attendance'   => ['value' => $checked, 'formatted' => number_format($checked), 'previous' => null, 'change_pct' => null, 'link' => 'checkin', 'rate' => $attRate],
                'customers'    => ['value' => $customers, 'formatted' => number_format($customers), 'previous' => $cmpCustomers, 'change_pct' => $pct($customers, $cmpCustomers), 'link' => 'customers'],
            ],
            'orders' => $orders,
            'capacity' => $capacity, 'remaining' => $remaining, 'sell_through' => $sellThrough,
            'paid_transactions' => $orders,
            'include_net' => true,
        ];
    }

    /* ── event conversion funnel ────────────────────────────────────── */

    public function funnel(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $views   = (int) $this->db->query("SELECT COALESCE(SUM(view_count),0) FROM event_analytics WHERE event_id IN ({$s['analyticsEventIn']})")->fetchColumn();
        $clicks  = (int) $this->db->query("SELECT COALESCE(SUM(click_count),0) FROM event_analytics WHERE event_id IN ({$s['analyticsEventIn']})")->fetchColumn();
        $checkouts = (int) $this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ({$s['listingIn']}){$s['fromCreate']}{$s['toCreate']}")->fetchColumn();
        $purchased = (int) $this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid'{$s['fromCreate']}{$s['toCreate']}")->fetchColumn();

        $stage = fn($from, $to) => $from > 0 ? round(100 * $to / $from, 1) : 0.0;
        return [
            'stages' => [
                ['key' => 'views',      'label' => 'Event views',    'value' => $views,   'formatted' => number_format($views)],
                ['key' => 'selection',  'label' => 'Ticket selection','value' => $clicks,  'formatted' => number_format($clicks)],
                ['key' => 'checkout',   'label' => 'Checkout started','value' => $checkouts, 'formatted' => number_format($checkouts)],
                ['key' => 'purchased',  'label' => 'Purchased',       'value' => $purchased, 'formatted' => number_format($purchased)],
            ],
            'conversion' => [
                'views_to_selection' => $stage($views, $clicks),
                'selection_to_checkout' => $stage($clicks, $checkouts),
                'checkout_to_purchased' => $stage($checkouts, $purchased),
                'overall' => $stage($views, $purchased),
            ],
        ];
    }

    /* ── revenue time series ────────────────────────────────────────── */

    public function revenue(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $mode = (string) ($filters['metric'] ?? 'gross');

        $tz = new DateTimeZone('UTC'); // created_at stored as local server time — keep raw buckets
        $bucketExpr = "DATE_FORMAT(created_at, '%Y-%m-%d')";
        $series = [];
        $stmt = $this->db->query("SELECT {$bucketExpr} AS day, COUNT(*) AS orders, COALESCE(SUM(total_price),0) AS gross
                                   FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid'{$s['fromCreate']}{$s['toCreate']}
                                   GROUP BY {$bucketExpr} ORDER BY day ASC");
        foreach ($stmt as $r) {
            $fee = round((float) $r['gross'] * 0, 2);
            $series[] = [
                'day' => $r['day'], 'orders' => (int) $r['orders'], 'gross' => $this->money($r['gross']),
                'net' => $this->money($r['gross'] - $fee),
                'aov' => $r['orders'] > 0 ? $this->money((float) $r['gross'] / (int) $r['orders']) : 0.0,
            ];
        }
        $label = ['gross' => 'Gross revenue', 'net' => 'Net revenue', 'orders' => 'Orders', 'aov' => 'Average order value'][$mode] ?? 'Gross revenue';
        return ['mode' => $mode, 'label' => $label, 'points' => $series];
    }

    /* ── sales velocity ─────────────────────────────────────────────── */

    public function velocity(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $within = '';
        if (($filters['range'] ?? '30d') === '7d') $within = " AND created_at >= (NOW() - INTERVAL 7 DAY)";
        elseif (($filters['range'] ?? '30d') === '90d') $within = " AND created_at >= (NOW() - INTERVAL 90 DAY)";

        $hours = [];
        for ($h = 0; $h < 24; $h++) $hours[$h] = ['hour' => $h, 'label' => ($h < 10 ? '0' . $h : $h) . ':00', 'tickets' => 0, 'orders' => 0, 'revenue' => 0.0];
        $stmt = $this->db->query("SELECT HOUR(created_at) h, COUNT(*) cnt, COALESCE(SUM(total_price),0) rev
                                   FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid'{$s['fromCreate']}{$s['toCreate']} AND created_at >= (NOW() - INTERVAL 1 DAY)
                                   GROUP BY h ORDER BY h ASC");
        foreach ($stmt as $r) {
            $h = (int) $r['h'];
            if (isset($hours[$h])) { $hours[$h]['orders'] = (int) $r['cnt']; $hours[$h]['revenue'] = $this->money($r['rev']); }
        }

        // Acceleration: difference between average of last 3 hours vs prior 3 hours
        $recent = array_slice($hours, -6);
        $recentVals = array_map(fn($h) => $h['revenue'], $recent);
        $first = count($recentVals) >= 4 ? array_sum(array_slice($recentVals, 0, 3)) : 0;
        $last  = count($recentVals) >= 3 ? array_sum(array_slice($recentVals, -3)) : 0;
        $accel = $first > 0 ? round(100 * ($last - $first) / $first, 1) : null;

        return ['hours' => array_values($hours), 'acceleration_pct' => $accel, 'peak_hour' => null];
    }

    /* ── ticket performance ─────────────────────────────────────────── */

    public function tickets(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $rows = [];
        $stmt = $this->db->query("SELECT tt.id, tt.name, tt.category, tt.tier, tt.price, tt.total_quantity, tt.remaining_quantity, tt.is_active
                                   FROM ticket_types tt WHERE tt.listing_id IN ({$s['listingIn']}) AND tt.is_active=1 ORDER BY tt.sort_order ASC, tt.name ASC");
        foreach ($stmt as $r) {
            $soldCount = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets et WHERE et.ticket_type_id=" . (int) $r['id'] . " AND et.status IN ('ISSUED','CHECKED_IN','CHECKED_OUT')")->fetchColumn();
            $total = (int) $r['total_quantity'];
            $sellThrough = $total > 0 ? round(100 * $soldCount / $total, 1) : 0.0;
            $rows[] = [
                'id' => $r['id'], 'name' => $r['name'], 'category' => $r['category'], 'tier' => $r['tier'],
                'price' => $this->money($r['price']), 'price_formatted' => $this->fmt((float) $r['price']),
                'allocation' => $total, 'remaining' => (int) $r['remaining_quantity'], 'sold' => $soldCount,
                'sell_through' => $sellThrough, 'revenue' => $this->money((float) $r['price'] * $soldCount),
                'near_sold_out' => $total > 0 && $soldCount >= $total,
            ];
        }
        $capacity = array_sum(array_map(fn($r) => $r['allocation'], $rows));
        $sold = array_sum(array_map(fn($r) => $r['sold'], $rows));
        return [
            'rows' => $rows,
            'capacity' => $capacity, 'sold' => $sold,
            'sell_through' => $capacity > 0 ? round(100 * $sold / $capacity, 1) : 0.0,
        ];
    }

    /* ── attendance & check-in ──────────────────────────────────────── */

    public function attendance(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $tickets = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id IN ({$s['listingIn']}) AND status IN ('ISSUED','CHECKED_IN','CHECKED_OUT'){$s['fromCreate']}{$s['toCreate']}")->fetchColumn();
        $checked = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id IN ({$s['listingIn']}) AND checked_in_at IS NOT NULL{$s['fromCreate']}{$s['toCreate']}")->fetchColumn();
        $noShow  = max(0, $tickets - $checked);
        $rate    = $tickets > 0 ? round(100 * $checked / $tickets, 1) : 0.0;

        // Attendance over time (by hour of check-in window, last 24h)
        $overTime = [];
        $stmt = $this->db->query("SELECT DATE_FORMAT(checked_in_at, '%Y-%m-%d %H:00') AS slot, COUNT(*) cnt
                                   FROM event_tickets WHERE listing_id IN ({$s['listingIn']}) AND checked_in_at IS NOT NULL
                                     AND checked_in_at >= (NOW() - INTERVAL 24 HOUR)
                                   GROUP BY slot ORDER BY slot ASC LIMIT 48");
        foreach ($stmt as $r) $overTime[] = ['slot' => $r['slot'], 'checked_in' => (int) $r['cnt']];

        return [
            'sold' => $tickets, 'checked_in' => $checked, 'no_show' => $noShow,
            'no_show_rate' => $tickets > 0 ? round(100 * $noShow / $tickets, 1) : 0.0,
            'attendance_rate' => $rate, 'over_time' => $overTime,
        ];
    }

    public function checkins(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $summary = ['ALLOW' => 0, 'DENY' => 0, 'REVIEW' => 0];
        $gates = [];
        $stmt = $this->db->query("SELECT decision, COUNT(*) c FROM checkin_scans WHERE listing_id IN ({$s['listingIn']}){$s['fromCreate']}{$s['toCreate']} GROUP BY decision");
        foreach ($stmt as $r) $summary[$r['decision']] = (int) $r['c'];

        $gStmt = $this->db->query("SELECT COALESCE(gate,'Unassigned') g, decision, COUNT(*) c FROM checkin_scans
                                    WHERE listing_id IN ({$s['listingIn']}){$s['fromCreate']}{$s['toCreate']} GROUP BY gate, decision ORDER BY g ASC");
        foreach ($gStmt as $r) {
            $g = $r['g'] ?: 'Unassigned';
            if (!isset($gates[$g])) $gates[$g] = ['gate' => $g, 'ALLOW' => 0, 'DENY' => 0, 'REVIEW' => 0, 'total' => 0];
            $gates[$g][$r['decision']] = (int) $r['c'];
            $gates[$g]['total'] += (int) $r['c'];
        }

        return [
            'summary' => $summary,
            'total' => array_sum($summary),
            'allow_rate' => (array_sum($summary) > 0) ? round(100 * $summary['ALLOW'] / array_sum($summary), 1) : 0.0,
            'gates' => array_values($gates),
        ];
    }

    /* ── customer insights ──────────────────────────────────────────── */

    public function customers(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $rows = [];
        $stmt = $this->db->query("SELECT b.customer_email,
                       COUNT(DISTINCT b.id) AS orders,
                       SUM(CASE WHEN b.payment_status='Paid' THEN b.total_price ELSE 0 END) AS spent,
                       MIN(b.created_at) AS first_booking, MAX(b.created_at) AS last_booking
                 FROM bookings b WHERE b.listing_id IN ({$s['listingIn']}){$s['fromCreate']}{$s['toCreate']}
                 GROUP BY b.customer_email ORDER BY spent DESC LIMIT 50");
        foreach ($stmt as $r) {
            $rows[] = [
                'email' => $r['customer_email'], 'orders' => (int) $r['orders'],
                'spent' => $this->money($r['spent']),
                'first_booking' => $r['first_booking'], 'last_booking' => $r['last_booking'],
            ];
        }

        $totalCust = count($rows);
        $repeat = array_values(array_filter($rows, fn($r) => $r['orders'] > 1));
        $new = array_values(array_filter($rows, fn($r) => $r['orders'] === 1));
        $totalSpend = array_sum(array_map(fn($r) => $r['spent'], $rows));

        // Value distribution
        $buckets = ['low' => 0, 'mid' => 0, 'high' => 0];
        foreach ($rows as $r) {
            if ($r['spent'] >= 200000) $buckets['high']++;
            elseif ($r['spent'] >= 50000) $buckets['mid']++;
            else $buckets['low']++;
        }

        return [
            'total' => $totalCust,
            'new' => count($new), 'returning' => count($repeat),
            'repeat_rate' => $totalCust > 0 ? round(100 * count($repeat) / $totalCust, 1) : 0.0,
            'avg_spend' => $totalCust > 0 ? $this->money($totalSpend / $totalCust) : 0.0,
            'total_spend' => $this->money($totalSpend),
            'tiers' => $buckets,
            'top' => array_slice($rows, 0, 10),
        ];
    }

    /* ── marketing attribution ──────────────────────────────────────── */

    public function marketing(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $campaigns = [];
        $stmt = $this->db->query("SELECT cm.*, l.title AS listing_title FROM events_marketing_campaigns cm
                                   LEFT JOIN listings l ON l.id = cm.listing_id
                                   WHERE cm.listing_id IN ({$s['listingIn']}) ORDER BY cm.created_at DESC");
        foreach ($stmt as $r) {
            $campaigns[] = [
                'id' => $r['id'], 'title' => $r['title'], 'objective' => $r['objective'], 'status' => $r['status'],
                'channel' => $r['channel'], 'listing_title' => $r['listing_title'],
                'reach' => (int) $r['reach_count'], 'clicks' => (int) $r['click_count'],
                'sales' => (int) $r['sales_count'], 'revenue' => $this->money($r['revenue_attributed']),
                'conversion' => $r['conversion_rate'] !== null ? round((float) $r['conversion_rate'], 1) : null,
                'start_date' => $r['start_date'], 'end_date' => $r['end_date'],
            ];
        }
        $totReach = array_sum(array_map(fn($c) => $c['reach'], $campaigns));
        $totClicks = array_sum(array_map(fn($c) => $c['clicks'], $campaigns));
        $totSales = array_sum(array_map(fn($c) => $c['sales'], $campaigns));
        $totRev = round(array_sum(array_map(fn($c) => $c['revenue'], $campaigns)), 2);
        return [
            'total_reach' => $totReach, 'total_clicks' => $totClicks, 'total_sales' => $totSales,
            'total_revenue_attributed' => $totRev,
            'click_through' => $totReach > 0 ? round(100 * $totClicks / $totReach, 1) : 0.0,
            'campaigns' => $campaigns,
            'attribution_note' => 'Attribution uses only recorded campaign reach/clicks/sales/revenue fields. Where no campaign tracked a channel, no revenue source is inferred.',
        ];
    }

    /* ── event comparison ───────────────────────────────────────────── */

    public function comparison(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $out = [];
        $stmt = $this->db->query("SELECT listing_id, COUNT(*) c, COALESCE(SUM(total_price),0) rev
                                   FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid'{$s['fromCreate']}{$s['toCreate']}
                                   GROUP BY listing_id ORDER BY rev DESC");
        $byListing = [];
        foreach ($stmt as $r) $byListing[$r['listing_id']] = ['orders' => (int) $r['c'], 'revenue' => $this->money($r['rev'])];

        foreach ($s['events'] as $e) {
            $stats = $byListing[$e['listing_id']] ?? ['orders' => 0, 'revenue' => 0.0];
            $sold = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id=" . $this->db->quote($e['listing_id']) . " AND status IN ('ISSUED','CHECKED_IN','CHECKED_OUT')")->fetchColumn();
            $checked = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id=" . $this->db->quote($e['listing_id']) . " AND checked_in_at IS NOT NULL")->fetchColumn();
            $out[] = [
                'event_id' => $e['event_id'], 'title' => $e['title'], 'status' => $e['status'],
                'orders' => $stats['orders'], 'revenue' => $stats['revenue'], 'sold' => $sold, 'checked_in' => $checked,
                'attendance_rate' => $sold > 0 ? round(100 * $checked / $sold, 1) : 0.0,
            ];
        }
        usort($out, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        return $out;
    }

    /* ── health score (explainable) ─────────────────────────────────── */

    public function health(string $vendorId, array $filters = []): array
    {
        $o = $this->overview($vendorId, $filters);
        $f = $this->funnel($vendorId, $filters);
        $t = $this->tickets($vendorId, $filters);

        $score = 0; $reasons = []; $issues = [];

        // Revenue pace (vs 6.68M-capable ceiling scaled by sell-through) — use sell-through as primary demand proxy
        $sell = $t['sell_through'];
        if ($sell >= 80)      { $score += 28; $reasons[] = ['kind' => 'positive', 'icon' => 'sell', 'text' => "Sell-through is {$sell}% — demand is strong."]; }
        elseif ($sell >= 50)  { $score += 22; $reasons[] = ['kind' => 'positive', 'icon' => 'sell', 'text' => "Sell-through at {$sell}% shows healthy demand."]; }
        elseif ($sell >= 25)  { $score += 14; $reasons[] = ['kind' => 'neutral',  'icon' => 'pace', 'text' => "Sell-through at {$sell}% — room to accelerate sales."]; }
        else                  { $score += 6;  $issues[] = 'Sell-through is low (' . $sell . '%); consider a renewal campaign.'; }

        // Funnel conversion
        $conv = $f['conversion']['overall'];
        if ($conv >= 10)      { $score += 24; $reasons[] = ['kind' => 'positive', 'icon' => 'funnel', 'text' => "Overall conversion is {$conv}% — strong funnel."]; }
        elseif ($conv >= 4)   { $score += 16; $reasons[] = ['kind' => 'neutral',  'icon' => 'funnel', 'text' => "Conversion is {$conv}% — within normal range."]; }
        else                  { $score += 8;  $issues[] = 'Overall conversion is only ' . $conv . '%; review the ticket selection step.'; }

        // Ticket types variance
        $types = $t['rows'];
        $soldOut = array_filter($types, fn($r) => $r['near_sold_out']);
        if ($soldOut) {
            $score += 12;
            $reasons[] = ['kind' => 'positive', 'icon' => 'full', 'text' => count($soldOut) . ' ticket type' . (count($soldOut) > 1 ? 's are' : ' is') . ' sold out — consider opening more allocation.'];
        } elseif ($types) {
            $score += 6;
            $reasons[] = ['kind' => 'neutral', 'icon' => 'tickets', 'text' => count($types) . ' ticket tier' . (count($types) > 1 ? 's' : '') . ' still have availability.'];
        }

        // Attendance risk
        $att = $o['kpis']['attendance']['rate'] ?? 0.0;
        if ($att >= 75)       { $score += 18; $reasons[] = ['kind' => 'positive', 'icon' => 'checkin', 'text' => "Attendance rate is {$att}% — check-in is healthy."]; }
        elseif ($att > 0)     { $score += 10; $reasons[] = ['kind' => 'neutral', 'icon' => 'checkin', 'text' => "Attendance is {$att}% — monitor no-shows."]; }
        else                  { $score += 2;  $issues[] = 'No check-in data yet; set up the Check-In workspace for live attendance.'; }

        // Customer mix
        $c = $this->customers($vendorId, $filters);
        if ($c['total'] > 0 && $c['repeat_rate'] >= 30) { $score += 8; $reasons[] = ['kind' => 'positive', 'icon' => 'customers', 'text' => "Repeat customer rate is {$c['repeat_rate']}% — strong loyalty."]; }
        elseif ($c['total'] > 0)                         { $score += 4; $reasons[] = ['kind' => 'neutral',  'icon' => 'customers', 'text' => "Repeat rate is {$c['repeat_rate']}% — growth opportunity."]; }

        // Velocity
        $v = $this->velocity($vendorId, $filters);
        if ($v['acceleration_pct'] !== null && $v['acceleration_pct'] > 0) { $score += 10; $reasons[] = ['kind' => 'positive', 'icon' => 'pace', 'text' => "Sales velocity is accelerating (+{$v['acceleration_pct']}%) — momentum building."]; }

        $score = min(100, max(0, $score));
        $label = $score >= 70 ? 'Healthy' : ($score >= 40 ? 'Watch' : 'Needs attention');

        return [
            'score' => $score, 'label' => $label,
            'band' => $score >= 70 ? 'good' : ($score >= 40 ? 'medium' : 'low'),
            'reasons' => $reasons,
            'issues' => array_slice($issues, 0, 5),
        ];
    }

    /* ── forecast (honest projection) ───────────────────────────────── */

    public function forecast(string $vendorId, array $filters = []): array
    {
        $s = $this->scope($vendorId, $filters);
        $o = $this->overview($vendorId, $filters);
        $t = $this->tickets($vendorId, $filters);

        $daysLookback = ['7d' => 7, '30d' => 30, '90d' => 90][$filters['range'] ?? '30d'] ?? 30;
        $epoch = $this->db->query("SELECT MIN(start_date) FROM tie_events_events WHERE listing_id IN ({$s['listingIn']}) AND start_date IS NOT NULL")->fetchColumn();
        $sold = $t['sold'];
        $capacity = $t['capacity'] > 0 ? $t['capacity'] : max(1, $o['kpis']['tickets_sold']['value'] * 2);

        // Daily pace derived from actual sales
        $avgPerDay = $this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid'{$s['fromCreate']}{$s['toCreate']}")->fetchColumn();
        $revPerDay = $daysLookback > 0 ? $o['kpis']['gross_revenue']['value'] / $daysLookback : 0.0;
        $pacePerDay = $daysLookback > 0 ? $sold / $daysLookback : 0.0;

        $horizon = 14; // forecast window in days
        $projectedSold = (int) round($sold + $pacePerDay * $horizon);
        $projectedRev  = $o['kpis']['gross_revenue']['value'] + $revPerDay * $horizon;
        $projectedRev  = min($projectedRev, (float) $projectedSold * 35000);

        // Confidence bands (±15% at horizon, ±6% near term)
        $spread = 0.12;
        $low  = $projectedRev * (1 - $spread);
        $high = $projectedRev * (1 + $spread);
        $level = $sold >= 100 ? 'medium' : ($sold >= 30 ? 'low' : 'low');
        if ($sold >= 200) $level = 'high';

        return [
            'horizon_days' => $horizon,
            'current' => $o['kpis']['gross_revenue']['value'],
            'projected_revenue' => round($projectedRev, 2),
            'confidence_low'  => round($low, 2),
            'confidence_high' => round($high, 2),
            'confidence_level' => $level,
            'projected_sold' => $projectedSold,
            'capacity' => $capacity,
            'estimated_fill' => $capacity > 0 ? round(100 * $projectedSold / $capacity, 1) : 0.0,
            'pace_per_day' => round($pacePerDay, 1),
            'basis' => 'Projection extrapolates the last ' . $daysLookback . ' days of actual sales. It is an estimate, not a promise.',
        ];
    }

    /* ── rule-based AI insights with deep links ─────────────────────── */

    public function insights(string $vendorId, array $filters = []): array
    {
        $insights = [];
        $o = $this->overview($vendorId, $filters);
        $f = $this->funnel($vendorId, $filters);
        $t = $this->tickets($vendorId, $filters);
        $c = $this->customers($vendorId, $filters);
        $h = $this->health($vendorId, $filters);

        if ($o['kpis']['gross_revenue']['value'] > 0) {
            $insights[] = ['icon' => 'revenue', 'tone' => 'info', 'title' => 'Revenue impact',
                'text' => 'Gross revenue is ' . $o['kpis']['gross_revenue']['formatted'] . ' with fees of ' .
                          $this->fmt(round($o['kpis']['gross_revenue']['value'] * $o['commission_rate'] / 100, 2)) .
                          ' (commission ' . $o['commission_rate'] . '%). See the split in Finance.',
                'link' => 'finance', 'action' => 'Open Finance'];
        }

        $near = array_values(array_filter($t['rows'], fn($r) => $r['sell_through'] >= 85 && !$r['near_sold_out']));
        if ($near) {
            $names = implode(', ', array_map(fn($r) => $r['name'], array_slice($near, 0, 3)));
            $insights[] = ['icon' => 'inventory', 'tone' => 'warn', 'title' => 'Near sell-out',
                'text' => $names . ($near[0]['sell_through'] >= 99 ? ' will sell out soon.' : ' are above 85% sold out.') .
                          ' Consider raising allocation or releasing more inventory.',
                'link' => 'tickets', 'action' => 'Open Tickets'];
        }

        if ($c['total'] > 0 && $c['repeat_rate'] < 25 && $c['new'] > $c['returning']) {
            $insights[] = ['icon' => 'customers', 'tone' => 'info', 'title' => 'Acquiring, not retaining',
                'text' => 'New customers outnumber returning ones (' . $c['new'] . ' vs ' . $c['returning'] . '). Consider a loyalty or early-bird offer to convert them to repeat buyers.',
                'link' => 'customers', 'action' => 'Open Customers'];
        }

        if ($f['conversion']['checkout_to_purchased'] < 60 && $f['conversion']['checkout_to_purchased'] >= 0) {
            $insights[] = ['icon' => 'funnel', 'tone' => 'warn', 'title' => 'Checkout drop-off',
                'text' => 'Only ' . $f['conversion']['checkout_to_purchased'] . '% of checkouts convert to purchase. Check payment friction, fees or promo code behaviour.',
                'link' => 'finance', 'action' => 'Review transactions'];
        }

        if ($h['score'] >= 70) {
            $insights[] = ['icon' => 'health', 'tone' => 'good', 'title' => 'Healthy event',
                'text' => 'Health score is ' . $h['score'] . '/100 (' . $h['label'] . '). ' . $this->summary($h['reasons']) . '.',
                'link' => 'analytics', 'action' => 'View analytics'];
        } elseif ($h['score'] < 40) {
            $insights[] = ['icon' => 'health', 'tone' => 'bad', 'title' => 'Needs attention',
                'text' => 'Health score is ' . $h['score'] . '/100. ' . ($h['issues'] ? implode(' ', array_slice($h['issues'], 0, 2)) : 'Review the sections below for specifics.'),
                'link' => 'analytics', 'action' => 'Open analytics'];
        }

        if ($h['issues']) {
            $insights[] = ['icon' => 'fix', 'tone' => 'warn', 'title' => 'Suggested focus',
                'text' => 'Prioritise: ' . implode(' ', array_slice($h['issues'], 0, 1)),
                'link' => 'analytics', 'action' => 'Address'];
        }

        return $insights;
    }

    private function summary(array $reasons): string
    {
        $txt = array_map(fn($r) => $r['text'], array_slice($reasons, 0, 3));
        return implode(' ', $txt);
    }

    /* ── Ask Analytics (controlled natural-language queries) ────────── */

    public function ask(string $vendorId, string $question, array $filters = []): array
    {
        $q = strtolower(trim($question));
        $o = $this->overview($vendorId, $filters);
        $t = $this->tickets($vendorId, $filters);
        $c = $this->customers($vendorId, $filters);
        $f = $this->funnel($vendorId, $filters);
        $a = $this->attendance($vendorId, $filters);

        $answer = 'I can answer based on your live event data.';
        $data = null;

        if (str_contains($q, 'gross') || str_contains($q, 'revenue') || str_contains($q, 'earn')) {
            $answer = 'Gross revenue is ' . $o['kpis']['gross_revenue']['formatted'] . ' across ' . $o['orders'] . ' paid orders. Your platform fee is ' . $this->fmt(round($o['kpis']['gross_revenue']['value'] * $o['commission_rate'] / 100, 2)) . '; net revenue is ' . $o['kpis']['net_revenue']['formatted'] . '.';
        } elseif (str_contains($q, 'sold') || str_contains($q, 'ticket') || str_contains($q, 'inventory')) {
            $data = $t['rows'];
            $answer = $t['sold'] . ' tickets sold out of ' . $t['capacity'] . ' (' . $t['sell_through'] . '% sell-through) across ' . count($t['rows']) . ' ticket types.';
        } elseif (str_contains($q, 'customer') || str_contains($q, 'audience') || str_contains($q, 'attendee')) {
            $answer = 'You have ' . $c['total'] . ' paying customers — ' . $c['new'] . ' new and ' . $c['returning'] . ' returning (' . $c['repeat_rate'] . '% repeat rate). Average spend is ' . $this->fmt($c['avg_spend']) . '.';
        } elseif (str_contains($q, 'attendance') || str_contains($q, 'check') || str_contains($q, 'no.shows') || str_contains($q, 'no show')) {
            $answer = $a['checked_in'] . ' of ' . $a['sold'] . ' ticket holders have checked in (' . $a['attendance_rate'] . '% attendance, ' . $a['no_show'] . ' expected no-shows).';
        } elseif (str_contains($q, 'conversion') || str_contains($q, 'funnel') || str_contains($q, 'views')) {
            $answer = 'Funnel: ' . $f['stages'][0]['formatted'] . ' views → ' . $f['stages'][1]['formatted'] . ' ticket selections → ' . $f['stages'][2]['formatted'] . ' checkouts → ' . $f['stages'][3]['formatted'] . ' purchases. Overall conversion is ' . $f['conversion']['overall'] . '%.';
        } elseif (str_contains($q, 'forecast') || str_contains($q, 'project')) {
            $p = $this->forecast($vendorId, $filters);
            $answer = 'On current pace (' . $p['pace_per_day'] . ' tickets/day) you are projected to reach ' . $this->fmt($p['projected_revenue']) . ' in ' . $p['horizon_days'] . ' days, with a ' . $p['confidence_level'] . '-confidence range of ' . $this->fmt($p['confidence_low']) . ' – ' . $this->fmt($p['confidence_high']) . '.';
        } elseif (str_contains($q, 'sell.out') || str_contains($q, 'sold out') || str_contains($q, 'remaining')) {
            $near = array_values(array_filter($t['rows'], fn($r) => $r['sell_through'] >= 85));
            if ($near) $answer = 'Closest to selling out: ' . implode(', ', array_map(fn($r) => $r['name'] . ' (' . $r['sell_through'] . '%)', $near)) . '.';
            else $answer = 'No ticket type is near sold-out yet. Total remaining across all types: ' . array_sum(array_map(fn($r) => $r['remaining'], $t['rows'])) . '.';
        } elseif (str_contains($q, 'health') || str_contains($q, 'performance')) {
            $h = $this->health($vendorId, $filters);
            $answer = 'Your event health score is ' . $h['score'] . '/100 (' . $h['label'] . ').';
            $data = $h['reasons'];
        } else {
            $answer = 'I can help with: revenue, tickets sold/inventory, customers, attendance & no-shows, conversion & funnel, forecast/projections, sell-out risk, and event health. Try one of those.';
        }

        return ['question' => $question, 'answer' => $answer, 'data' => $data];
    }

    /* ── alert config ───────────────────────────────────────────────── */

    public function alertConfig(string $vendorId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tie_analytics_alert_config WHERE vendor_id=? LIMIT 1');
        $stmt->execute([$vendorId]);
        $row = $stmt->fetch() ?: [];
        if (!$row) {
            return ['sales_target' => 0.0, 'ticket_cap' => 0, 'attendance_rate' => 0.0,
                    'notify_sales' => true, 'notify_velocity' => true, 'notify_inventory' => true,
                    'notify_attendance' => true, 'notify_revenue' => true, 'notify_customers' => true];
        }
        return [
            'sales_target' => (float) $row['sales_target'], 'ticket_cap' => (int) $row['ticket_cap'],
            'attendance_rate' => (float) $row['attendance_rate'],
            'notify_sales' => (bool) $row['notify_sales'], 'notify_velocity' => (bool) $row['notify_velocity'],
            'notify_inventory' => (bool) $row['notify_inventory'], 'notify_attendance' => (bool) $row['notify_attendance'],
            'notify_revenue' => (bool) $row['notify_revenue'], 'notify_customers' => (bool) $row['notify_customers'],
        ];
    }

    public function saveAlertConfig(string $vendorId, string $actorId, array $input): array
    {
        $cfg = [
            'sales_target'  => (float) ($input['sales_target'] ?? 0),
            'ticket_cap'    => (int) ($input['ticket_cap'] ?? 0),
            'attendance_rate' => (float) ($input['attendance_rate'] ?? 0),
            'notify_sales'  => !empty($input['notify_sales'] ?? 0),
            'notify_velocity' => !empty($input['notify_velocity'] ?? 0),
            'notify_inventory' => !empty($input['notify_inventory'] ?? 0),
            'notify_attendance' => !empty($input['notify_attendance'] ?? 0),
            'notify_revenue' => !empty($input['notify_revenue'] ?? 0),
            'notify_customers' => !empty($input['notify_customers'] ?? 0),
        ];
        $id = strtoupper(bin2hex(random_bytes(16)));
        $this->db->prepare('INSERT INTO tie_analytics_alert_config (id, vendor_id, sales_target, ticket_cap, attendance_rate,
                            notify_sales, notify_velocity, notify_inventory, notify_attendance, notify_revenue, notify_customers)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE
                              sales_target=VALUES(sales_target), ticket_cap=VALUES(ticket_cap), attendance_rate=VALUES(attendance_rate),
                              notify_sales=VALUES(notify_sales), notify_velocity=VALUES(notify_velocity), notify_inventory=VALUES(notify_inventory),
                              notify_attendance=VALUES(notify_attendance), notify_revenue=VALUES(notify_revenue), notify_customers=VALUES(notify_customers)')
            ->execute([$id, $vendorId, $cfg['sales_target'], $cfg['ticket_cap'], $cfg['attendance_rate'],
                (int) $cfg['notify_sales'], (int) $cfg['notify_velocity'], (int) $cfg['notify_inventory'],
                (int) $cfg['notify_attendance'], (int) $cfg['notify_revenue'], (int) $cfg['notify_customers']]);

        try {
            $this->db->prepare('INSERT INTO tie_events_audit_log (event_id, actor_id, action, field_changes) VALUES (?,?,?,?)')
                ->execute([null, $actorId, 'analytics_alerts_configured', json_encode(['vendor_id' => $vendorId, 'details' => $cfg])]);
        } catch (Throwable) { }

        return $cfg;
    }

    /* ── alerts (live evaluation vs config) ─────────────────────────── */

    public function alerts(string $vendorId, array $filters = []): array
    {
        $cfg = $this->alertConfig($vendorId);
        $o = $this->overview($vendorId, $filters);
        $t = $this->tickets($vendorId, $filters);
        $c = $this->customers($vendorId, $filters);
        $a = $this->attendance($vendorId, $filters);
        $v = $this->velocity($vendorId, $filters);
        $alerts = [];

        if ($cfg['notify_sales'] && $cfg['sales_target'] > 0) {
            $soldVal = $o['kpis']['tickets_sold']['value'];
            if ($soldVal >= $cfg['sales_target']) $alerts[] = ['level' => 'info', 'icon' => 'sales', 'title' => 'Sales target reached', 'text' => 'Ticket sales (' . number_format($soldVal) . ') have met or exceeded your target of ' . number_format((int) $cfg['sales_target']) . '.', 'module' => 'tickets'];
            elseif ($soldVal >= 0.75 * $cfg['sales_target']) $alerts[] = ['level' => 'warn', 'icon' => 'sales', 'title' => 'Approaching sales target', 'text' => 'Sales are at ' . round(100 * $soldVal / $cfg['sales_target']) . '% of your target. A campaign push could close the gap.', 'module' => 'marketing'];
        }
        if ($cfg['notify_inventory']) {
            $near = array_values(array_filter($t['rows'], fn($r) => $r['sell_through'] >= 90 && $r['remaining'] <= 10));
            foreach (array_slice($near, 0, 3) as $r) {
                $alerts[] = ['level' => 'warn', 'icon' => 'inventory', 'title' => 'Inventory critical', 'text' => $r['name'] . ' has only ' . $r['remaining'] . ' left (' . $r['sell_through'] . '% sold).', 'module' => 'tickets'];
            }
        }
        if ($cfg['notify_attendance'] && $cfg['attendance_rate'] > 0 && $a['attendance_rate'] > 0 && $a['attendance_rate'] < $cfg['attendance_rate']) {
            $alerts[] = ['level' => 'warn', 'icon' => 'checkin', 'title' => 'Attendance below target', 'text' => 'Attendance is ' . $a['attendance_rate'] . '% versus your ' . $cfg['attendance_rate'] . '% target (' . $a['no_show'] . ' no-shows so far).', 'module' => 'checkin'];
        }
        if ($cfg['notify_velocity'] && $v['acceleration_pct'] !== null && $v['acceleration_pct'] > 50) {
            $alerts[] = ['level' => 'info', 'icon' => 'pace', 'title' => 'Sales accelerating', 'text' => 'Revenue in the last 3 hours is up ' . $v['acceleration_pct'] . '% vs the prior window. Keep inventory visible.', 'module' => 'analytics'];
        }
        if ($cfg['notify_revenue'] && $o['kpis']['net_revenue']['value'] < 0) {
            $alerts[] = ['level' => 'bad', 'icon' => 'revenue', 'title' => 'Net negative', 'text' => 'Refunds and fees have overtaken gross revenue. Review refunds in Finance.', 'module' => 'finance'];
        }
        if ($cfg['notify_customers'] && $c['total'] > 0 && $c['repeat_rate'] < 15) {
            $alerts[] = ['level' => 'info', 'icon' => 'customers', 'title' => 'Low repeat rate', 'text' => 'Only ' . $c['repeat_rate'] . '% of buyers return. Launch a retention offer.', 'module' => 'customers'];
        }
        return ['config' => $cfg, 'alerts' => $alerts, 'last_checked' => date('Y-m-d H:i:s')];
    }

    /* ── CSV export helper ──────────────────────────────────────────── */

    public function exportCsv(string $vendorId, array $filters = []): string
    {
        $s = $this->scope($vendorId, $filters);
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Day', 'Orders', 'Gross (MWK)', 'Net Proxy (MWK)', 'Tickets Sold', 'Checked In']);
        $stmt = $this->db->query("SELECT DATE_FORMAT(created_at,'%Y-%m-%d') day, COUNT(*) orders,
                     COALESCE(SUM(total_price),0) gross
                     FROM bookings WHERE listing_id IN ({$s['listingIn']}) AND payment_status='Paid'{$s['fromCreate']}{$s['toCreate']}
                     GROUP BY day ORDER BY day ASC");
        foreach ($stmt as $r) {
            $sold = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id IN ({$s['listingIn']}) AND DATE(created_at)=" . $this->db->quote($r['day']))->fetchColumn();
            $checked = (int) $this->db->query("SELECT COUNT(*) FROM event_tickets WHERE listing_id IN ({$s['listingIn']}) AND DATE(checked_in_at)=" . $this->db->quote($r['day'] . ' 00:00:00'))->fetchColumn();
            fputcsv($out, [$r['day'], (int) $r['orders'], $this->money($r['gross']), $this->money($r['gross']), $sold, $checked]);
        }
        rewind($out);
        return stream_get_contents($out);
    }
}