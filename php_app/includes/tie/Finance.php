<?php
/** Event finance engine — a read/compute layer over the existing booking, payment,
 *  ticket and refund stone. It never talks to a payment gateway directly; it consumes
 *  the Uthenga payment trail (bookings / transactions / event_ticket_refunds) and
 *  records the settlement, withdrawal, document and reconciliation layer on top. */

final class UthengaTieEventFinance
{
    public const SCHEMA = 'tie-finance/v1';
    private const BATCH_STATUSES = ['PENDING', 'ELIGIBLE', 'PAID', 'CANCELLED'];

    public function __construct(private PDO $db)
    {
    }

    /* ── shared helpers ─────────────────────────────────────────────── */

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function money(mixed $v): float { return round((float) $v, 2); }

    private function intId(string $prefix, int $chars = 4): string
    {
        return $prefix . '-' . strtoupper(bin2hex(random_bytes($chars)));
    }

    /** This vendor's event listings with their money identity. */
    public function events(string $vendorId): array
    {
        return array_map(fn($e) => [
            'listing_id' => $e['listing_id'], 'event_id' => $e['event_id'],
            'title' => $e['title'], 'status' => $e['status'],
        ], $this->eventRows($vendorId));
    }

    private function eventRows(string $vendorId): array
    {
        $rows = [];
        $stmt = $this->db->prepare('SELECT listing_id AS listing_id, id AS event_id, title, status
                                     FROM tie_events_events WHERE vendor_id=? AND listing_id IS NOT NULL
                                     ORDER BY title ASC');
        $stmt->execute([$vendorId]);
        foreach ($stmt->fetchAll() as $r) {
            $rows[$r['listing_id']] = ['listing_id' => $r['listing_id'], 'event_id' => $r['event_id'], 'title' => $r['title'], 'status' => $r['status']];
        }
        return array_values($rows);
    }

    private function listingIn(string $vendorId): string
    {
        $ids = array_map(fn($e) => $this->db->quote((string) $e['listing_id']), $this->eventRows($vendorId));
        return $ids ? implode(',', $ids) : "''";
    }

    /** Commission rate for event sales — settings-driven, matching the central engine. */
    public function commissionRate(?string $vendorId = null): float
    {
        // Delegates to the one shared, versioned rate lookup (includes/functions.php)
        // instead of reading `settings` directly — this used to be a second,
        // independent source of truth that could silently disagree with it.
        return uthenga_finance_commission_rate('event');
    }

    /** Gross → [platform_fee, net] using the central commission model. */
    public function split(float $gross, ?string $vendorId = null): array
    {
        $rate = $this->commissionRate($vendorId);
        $fee = round($gross * $rate / 100, 2);
        return [$fee, round($gross - $fee, 2), $rate];
    }

    private function audit(string $vendorId, string $actor, string $action, array $details): void
    {
        try {
            $stmt = $this->db->prepare('INSERT INTO tie_events_audit_log (event_id,actor_id,action,field_changes) VALUES (?,?,?,?)');
            $stmt->execute([null, $actor, $action, json_encode(['vendor_id' => $vendorId, 'details' => $details])]);
        } catch (Throwable) { /* audit must never break the operation */ }
    }

    /* ── overview ───────────────────────────────────────────────────── */

    public function overview(string $vendorId, string $actorId): array
    {
        $listings = $this->listingIn($vendorId);
        $rate = $this->commissionRate($vendorId);

        $g = $this->db->query("SELECT COUNT(*) c, COALESCE(SUM(total_price),0) t
                                 FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid'")->fetch();
        $gross = $this->money($g['t']); $paidCount = (int) $g['c'];

        $fp = $this->db->query("SELECT COUNT(*) c, COALESCE(SUM(total_price),0) t
                                  FROM bookings WHERE listing_id IN ($listings) AND payment_status IN ('Failed','Cancelled')")->fetch();
        $pend = $this->db->query("SELECT COUNT(*) c, COALESCE(SUM(total_price),0) t
                                    FROM bookings WHERE listing_id IN ($listings) AND payment_status='Pending'")->fetch();
        $refTotal = $this->money($this->db->query("SELECT COALESCE(SUM(amount),0) FROM event_ticket_refunds WHERE listing_id IN ($listings) AND status='PROCESSED'")->fetchColumn());
        $fees = round($gross * $rate / 100, 2);
        $net = round($gross - $fees - $refTotal, 2);

        $settlement = $this->settlementSnapshot($vendorId, $gross, $net, $refTotal);
        $recon = $this->reconciliationStatus($vendorId);

        $refundPending = (int) $this->db->query("SELECT COUNT(*) FROM event_ticket_refunds WHERE listing_id IN ($listings) AND status='PENDING'")->fetchColumn();
        $recent = $this->recentActivity($vendorId, 8);

        $methods = [];
        foreach ($this->db->query("SELECT payment_gateway g, COUNT(*) c, COALESCE(SUM(total_price),0) t FROM bookings
                                     WHERE listing_id IN ($listings) AND payment_status='Paid' GROUP BY payment_gateway ORDER BY t DESC") as $r) {
            $pct = $gross > 0 ? round(100 * (float) $r['t'] / $gross, 1) : 0;
            $methods[] = ['method' => $r['g'], 'count' => (int) $r['c'], 'amount' => $this->money($r['t']), 'percent' => $pct];
        }

        $byStatus = [];
        foreach ($this->db->query("SELECT payment_status s, COUNT(*) c, COALESCE(SUM(total_price),0) t FROM bookings
                                    WHERE listing_id IN ($listings) GROUP BY payment_status") as $r) $byStatus[$r['s']] = ['count' => (int) $r['c'], 'amount' => $this->money($r['t'])];

        $alerts = [];
        if ($recon['open_exceptions'] > 0) $alerts[] = ['type' => 'warn', 'title' => 'Reconciliation alert', 'body' => $recon['open_exceptions'] . ' financial record' . ($recon['open_exceptions'] === 1 ? '' : 's') . ' require review.', 'action' => 'reconciliation'];
        if ($refundPending > 0) $alerts[] = ['type' => 'notice', 'title' => 'Refund activity', 'body' => $refundPending . ' refund' . ($refundPending === 1 ? '' : 's') . ' waiting for your approval.', 'action' => 'refunds'];
        if ($settlement['pending_count'] > 0) $alerts[] = ['type' => 'notice', 'title' => 'Settlement ready', 'body' => 'MK ' . number_format($settlement['pending_net']) . ' is pending settlement from ' . $settlement['pending_count'] . ' completed transactions.', 'action' => 'settlements'];
        if($gross>0)$alerts[]=['type'=>'info','title'=>'Fees on track','body'=>"Platform commission is applied at {$rate}% and is visible in the Fees tab.",'action'=>'fees'];

        return [
            'schema_version' => self::SCHEMA,
            'currency' => 'MWK',
            'commission_rate' => $rate,
            'gross_revenue' => $gross,
            'platform_fee' => $fees,
            'processing_fee' => $settlement['processing_fee'],
            'refunds_total' => $refTotal,
            'net_revenue' => $net,
            'paid_transactions' => $paidCount,
            'transaction_counts' => [
                'total' => $paidCount + (int) $fp['c'] + (int) $pend['c'],
                'successful' => $paidCount,
                'failed' => (int) $fp['c'],
                'pending' => (int) $pend['c'],
                'refunded' => (int) $this->db->query("SELECT COUNT(*) FROM event_ticket_refunds WHERE listing_id IN ($listings) AND status='PROCESSED'")->fetchColumn(),
            ],
            'settlement' => $settlement,
            'reconciliation' => $recon,
            'refund_pending' => $refundPending,
            'payment_methods' => $methods,
            'recent_activity' => $recent,
            'by_payment_status' => $byStatus,
            'alerts' => $alerts,
        ];
    }

    private function recentActivity(string $vendorId, int $limit): array
    {
        $listings = $this->listingIn($vendorId);
        $out = [];
        foreach ($this->db->query("SELECT id, total_price, payment_gateway, transaction_id, created_at FROM bookings
                                    WHERE listing_id IN ($listings) AND payment_status='Paid'
                                    ORDER BY created_at DESC LIMIT " . (int) $limit) as $r) {
            $out[] = ['type' => 'payment', 'title' => 'Ticket payment', 'amount' => $this->money($r['total_price']), 'method' => $r['payment_gateway'], 'ref' => $r['transaction_id'] ?: $r['id'], 'at' => $r['created_at']];
        }
        foreach ($this->db->query("SELECT id, amount, reason, requested_at, status FROM event_ticket_refunds WHERE listing_id IN ($listings) ORDER BY requested_at DESC LIMIT " . (int) $limit) as $r) {
            $out[] = ['type' => 'refund', 'title' => 'Refund ' . strtolower($r['status']), 'amount' => -1 * $this->money($r['amount']), 'reason' => $r['reason'], 'ref' => $r['id'], 'at' => $r['requested_at']];
        }
        usort($out, fn($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
        return array_slice($out, 0, $limit);
    }

    /* ── settlement snapshot ────────────────────────────────────────── */

    private function settlementSnapshot(string $vendorId, float $gross, float $netRevenue, float $refundsTotal): array
    {
        $batches = [];
        $eligibleNet = 0.0; $paidNet = 0.0;
        $stmt = $this->db->prepare('SELECT id, period_start, period_end, gross_amount, platform_fee, processing_fee, refunds_total, net_amount, status, paid_at, created_at
                                     FROM tie_event_settlement_batches WHERE vendor_id=? ORDER BY created_at DESC LIMIT 200');
        $stmt->execute([$vendorId]);
        foreach ($stmt->fetchAll() as $r) {
            $net = $this->money($r['net_amount']);
            $batches[] = [
                'id' => $r['id'], 'period_start' => $r['period_start'], 'period_end' => $r['period_end'],
                'gross_amount' => $this->money($r['gross_amount']), 'platform_fee' => $this->money($r['platform_fee']),
                'processing_fee' => $this->money($r['processing_fee']), 'refunds_total' => $this->money($r['refunds_total']),
                'net_amount' => $net, 'status' => $r['status'], 'paid_at' => $r['paid_at'],
            ];
            if ($r['status'] === 'ELIGIBLE') $eligibleNet += $net;
            if ($r['status'] === 'PAID') $paidNet += $net;
        }

        $withdrawnStmt = $this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM tie_event_withdrawals WHERE vendor_id=? AND status IN (?,?)');
        $withdrawnStmt->execute([$vendorId, 'PROCESSING', 'PAID']);
        $withdrawn = $this->money($withdrawnStmt->fetchColumn());

        $pending = $this->pendingSettlement($vendorId);

        return [
            'available_balance' => max(0.0, round($eligibleNet - $withdrawn, 2)),
            'pending_net' => $pending['net'],
            'pending_count' => $pending['count'],
            'paid_out_total' => $paidNet,
            'withdrawn_total' => $withdrawn,
            'next_settlement' => [
                'estimated' => $pending['net'],
                'transactions' => $pending['count'],
            ],
            'eligible_count' => count(array_filter($batches, fn($b) => $b['status'] === 'ELIGIBLE')),
            'batches' => $batches,
            'worst_case' => $refundsTotal + $pending['refunds'],
            'processing_fee' => 0.0,
        ];
    }

    private function pendingSettlement(string $vendorId): array
    {
        $listings = $this->listingIn($vendorId);
        $rate = $this->commissionRate($vendorId);
        $gross = 0.0; $count = 0;
        $stmt = $this->db->prepare("SELECT id, total_price, created_at FROM bookings
                                      WHERE listing_id IN ($listings) AND payment_status='Paid'
                                        AND NOT EXISTS (
                                          SELECT 1 FROM tie_event_settlement_batches b
                                          WHERE b.vendor_id=? AND b.status IN ('ELIGIBLE','PAID')
                                            AND b.period_start <= DATE(bookings.created_at) AND b.period_end >= DATE(bookings.created_at)
                                        )");
        $stmt->execute([$vendorId]);
        foreach ($stmt->fetchAll() as $r) { $gross += $this->money($r['total_price']); $count++; }
        $fee = round($gross * $rate / 100, 2);
        $refundsPending = $this->money($this->db->query("SELECT COALESCE(SUM(amount),0) FROM event_ticket_refunds WHERE listing_id IN ($listings) AND status='PENDING'")->fetchColumn());
        return ['gross' => $gross, 'fee' => $fee, 'net' => round($gross - $fee, 2), 'count' => $count, 'refunds' => $refundsPending];
    }

    public function createBatch(string $vendorId, string $actorId): array
    {
        $listings = $this->listingIn($vendorId);
        $rate = $this->commissionRate($vendorId);
        $rows = [];
        $stmt = $this->db->prepare("SELECT id, total_price, created_at FROM bookings
                                      WHERE listing_id IN ($listings) AND payment_status='Paid'
                                        AND NOT EXISTS (
                                          SELECT 1 FROM tie_event_settlement_batches b
                                          WHERE b.vendor_id=? AND b.status IN ('ELIGIBLE','PAID')
                                            AND b.period_start <= DATE(bookings.created_at) AND b.period_end >= DATE(bookings.created_at)
                                        )");
        $stmt->execute([$vendorId]);
        foreach ($stmt->fetchAll() as $r) $rows[] = $r;
        if (!$rows) throw UthengaTieErrors::validation(['batch' => 'There is no eligible revenue to settle.']);

        $start = $rows[0]['created_at']; $end = $rows[0]['created_at'];
        $gross = 0.0; $count = 0;
        foreach ($rows as $r) {
            if ($r['created_at'] < $start) $start = $r['created_at'];
            if ($r['created_at'] > $end) $end = $r['created_at'];
            $gross += $this->money($r['total_price']); $count++;
        }
        $days = max(1, (int) ceil((strtotime($end) - strtotime($start)) / 86400));
        $end = date('Y-m-d', strtotime($start) + $days * 86400 - 1);
        $fee = round($gross * $rate / 100, 2);
        $refunds = 0.0;
        $rStmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM event_ticket_refunds WHERE listing_id IN ($listings) AND status='PROCESSED'");
        $rStmt->execute();
        $refunds = $this->money($rStmt->fetchColumn());
        $net = round($gross - $fee - $refunds, 2);

        $id = $this->uuid();
        $ins = $this->db->prepare('INSERT INTO tie_event_settlement_batches (id, vendor_id, period_start, period_end, gross_amount, platform_fee, processing_fee, refunds_total, net_amount, status, created_at)
                                   VALUES (?,?,?,?,?,?,?,?,?,\'ELIGIBLE\',NOW())');
        $ins->execute([$id, $vendorId, date('Y-m-d', strtotime($start)), $end, $gross, $fee, 0.00, $refunds, $net]);
        $this->audit($vendorId, $actorId, 'finance.batch_created', ['batch_id' => $id, 'period' => [date('Y-m-d', strtotime($start)), $end], 'net' => $net]);

        UthengaTieMetrics::record('event_finance_batch', 1, UthengaTieObservability::requestId(), ['module' => 'events', 'feature' => 'finance', 'status' => 'created']);
        return $this->overview($vendorId, $actorId)['settlement'];
    }

    /** Public settlement view for the settlements tab. */
    public function settlements(string $vendorId): array
    {
        return $this->overview($vendorId, $vendorId)['settlement'];
    }

    /* ── transactions ───────────────────────────────────────────────── */

    public function transactions(string $vendorId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $listings = $this->listingIn($vendorId);
        $where = ["b.listing_id IN ($listings)"];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') { $where[] = '(b.id LIKE ? OR b.transaction_id LIKE ? OR b.holder_email LIKE ? OR b.holder_name LIKE ?)'; $like = '%' . $q . '%'; array_push($params, $like, $like, $like, $like); }
        if (!empty($filters['event'])) { $where[] = 'b.listing_id = ?'; $params[] = $filters['event']; }
        if (!empty($filters['status'])) { $where[] = 'b.payment_status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['method'])) { $where[] = 'b.payment_gateway = ?'; $params[] = $filters['method']; }
        if (!empty($filters['from'])) { $where[] = 'b.created_at >= ?'; $params[] = $filters['from'] . ' 00:00:00'; }
        if (!empty($filters['to'])) { $where[] = 'b.created_at <= ?'; $params[] = $filters['to'] . ' 23:59:59'; }

        $count = $this->db->prepare('SELECT COUNT(*) FROM bookings b WHERE ' . implode(' AND ', $where));
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $sql = 'SELECT b.id, b.listing_id, b.total_price, b.payment_status, b.payment_gateway, b.transaction_id, b.created_at,
                       ev.title AS event_title
                FROM bookings b
                LEFT JOIN tie_events_events ev ON ev.listing_id = b.listing_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY b.created_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = [];
        foreach ($stmt->fetchAll() as $r) {
            $items[] = [
                'id' => $r['id'], 'reference' => $r['transaction_id'] ?: $r['id'], 'event' => $r['event_title'] ?: $r['listing_id'],
                'amount' => $this->money($r['total_price']), 'status' => $r['payment_status'], 'method' => $r['payment_gateway'], 'date' => $r['created_at'],
            ];
        }
        return ['total' => $total, 'limit' => $limit, 'offset' => $offset, 'items' => $items];
    }

    public function transactionDetail(string $vendorId, string $ref): array
    {
        $listings = $this->listingIn($vendorId);
        $stmt = $this->db->prepare("SELECT b.*, ev.title AS event_title FROM bookings b
                                     LEFT JOIN tie_events_events ev ON ev.listing_id = b.listing_id
                                     WHERE b.listing_id IN ($listings) AND (b.id = ? OR b.transaction_id = ?) LIMIT 1");
        $stmt->execute([$ref, $ref]);
        $b = $stmt->fetch();
        if (!is_array($b)) throw UthengaTieErrors::validation(['ref' => 'Transaction not found.']);

        $gross = $this->money($b['total_price']);
        [$fee, $net, $rate] = $this->split($gross, $vendorId);
        $refunds = 0.0;
        $rs = $this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM event_ticket_refunds WHERE booking_id=? AND status=?');
        $rs->execute([$b['id'], 'PROCESSED']); $refunds = $this->money($rs->fetchColumn());

        $tickets = [];
        $ts = $this->db->prepare("SELECT t.id, t.status, tt.name AS ticket_type FROM event_tickets t
                                   LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id WHERE t.booking_id=?");
        $ts->execute([$b['id']]);
        foreach ($ts->fetchAll() as $t) $tickets[] = ['id' => $t['id'], 'type' => $t['ticket_type'] ?: 'General', 'status' => $t['status']];

        $timeline = [['at' => $b['created_at'], 'label' => 'Payment initiated', 'state' => 'done']];
        $timeline[] = ['at' => $b['created_at'], 'label' => 'Payment confirmed', 'state' => 'done'];
        if ($tickets) $timeline[] = ['at' => $b['created_at'], 'label' => 'Ticket' . (count($tickets) > 1 ? 's' : '') . ' issued', 'state' => 'done'];
        $timeline[] = ['at' => $b['created_at'], 'label' => 'Revenue recorded', 'state' => 'done'];
        if ($refunds > 0) $timeline[] = ['at' => $b['created_at'], 'label' => 'Refund processed', 'state' => 'done', 'amount' => $refunds];

        return [
            'reference' => $b['transaction_id'] ?: $b['id'], 'booking_id' => $b['id'], 'event' => $b['event_title'] ?: $b['listing_id'],
            'status' => $b['payment_status'], 'method' => $b['payment_gateway'],
            'gross' => $gross, 'platform_fee' => $fee, 'net' => $net, 'refunds' => $refunds, 'rate' => $rate,
            'date' => $b['created_at'], 'card_reference' => $b['transaction_id'] ? substr($b['transaction_id'], -4) : '—',
            'tickets' => $tickets, 'timeline' => $timeline,
        ];
    }

    /* ── revenue ────────────────────────────────────────────────────── */

    public function revenue(string $vendorId, array $in = []): array
    {
        $listings = $this->listingIn($vendorId);
        $rate = $this->commissionRate($vendorId);

        $range = (string) ($in['range'] ?? '30d');
        $from = null; $to = date('Y-m-d');
        if ($range === '7d') $from = date('Y-m-d', strtotime('-6 days'));
        elseif ($range === '30d') $from = date('Y-m-d', strtotime('-29 days'));
        elseif ($range === '90d') $from = date('Y-m-d', strtotime('-89 days'));
        elseif ($range === 'custom' && !empty($in['from']) && !empty($in['to'])) { $from = $in['from']; $to = $in['to']; }
        $where = "listing_id IN ($listings) AND payment_status='Paid'";
        if ($from) $where .= ' AND DATE(created_at) >= ' . $this->db->quote($from);
        if ($to) $where .= ' AND DATE(created_at) <= ' . $this->db->quote($to);

        $series = [];
        $byEvent = [];
        $perDay = $this->db->query("SELECT DATE(created_at) d, COUNT(*) c, COALESCE(SUM(total_price),0) t FROM bookings WHERE $where GROUP BY DATE(created_at) ORDER BY d");
        $dayTotals = [];
        foreach ($perDay as $r) $dayTotals[$r['d']] = ['count' => (int) $r['c'], 'gross' => $this->money($r['t'])];
        $cursor = $from ?? date('Y-m-d', strtotime('-29 days'));
        for ($d = strtotime($cursor); $d <= strtotime($to); $d += 86400) {
            $key = date('Y-m-d', $d);
            $g = $dayTotals[$key]['gross'] ?? 0;
            $fee = round($g * $rate / 100, 2);
            $series[] = ['date' => $key, 'gross' => $g, 'fees' => $fee, 'net' => round($g - $fee, 2), 'count' => $dayTotals[$key]['count'] ?? 0];
        }

        $eventStmt = $this->db->query("SELECT b.listing_id, ev.title, ev.status, COUNT(DISTINCT b.id) c, COALESCE(SUM(b.total_price),0) t
                                        FROM bookings b LEFT JOIN tie_events_events ev ON ev.listing_id=b.listing_id
                                        WHERE b.listing_id IN ($listings) AND b.payment_status='Paid' GROUP BY b.listing_id, ev.title, ev.status ORDER BY t DESC");
        foreach ($eventStmt as $r) {
            $g = $this->money($r['t']); $fee = round($g * $rate / 100, 2);
            $byEvent[] = ['event' => $r['title'] ?: $r['listing_id'], 'listing_id' => $r['listing_id'], 'status' => $r['status'],
                          'tickets' => (int) $r['c'], 'gross' => $g, 'fees' => $fee, 'net' => round($g - $fee, 2)];
        }

        $byType = [];
        $typeStmt = $this->db->query("SELECT tt.name, COUNT(DISTINCT b.id) c, COALESCE(SUM(b.total_price),0) t
                                       FROM bookings b JOIN event_tickets et ON et.booking_id=b.id
                                       LEFT JOIN ticket_types tt ON tt.id=et.ticket_type_id
                                       WHERE b.listing_id IN ($listings) AND b.payment_status='Paid' GROUP BY tt.name ORDER BY t DESC");
        $maxT = 1;
        foreach ($typeStmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $g = $this->money($r['t']); $byType[] = ['type' => $r['name'] ?: 'General', 'count' => (int) $r['c'], 'gross' => $g, 'net' => round($g - (round($g * $rate / 100, 2)), 2)]; $maxT = max($maxT, $g); }
        foreach ($byType as &$t) $t['share'] = round(100 * $t['gross'] / max(1, $maxT), 1);
        unset($t);

        $totals = $this->overview($vendorId, $vendorId)['revenue_summary'] ?? null;
        return ['schema_version' => self::SCHEMA, 'range' => $range, 'from' => $cursor, 'to' => $to, 'rate' => $rate,
                'series' => $series, 'by_event' => $byEvent, 'by_ticket_type' => $byType];
    }

    /* ── refunds ────────────────────────────────────────────────────── */

    public function refunds(string $vendorId): array
    {
        $listings = $this->listingIn($vendorId);
        $stmt = $this->db->query("SELECT r.id, r.listing_id, r.booking_id, r.ticket_id, r.amount, r.reason, r.status, r.requested_by, r.requested_at, r.decided_at, ev.title AS event_title
                                   FROM event_ticket_refunds r LEFT JOIN tie_events_events ev ON ev.listing_id=r.listing_id
                                   WHERE r.listing_id IN ($listings) ORDER BY r.requested_at DESC LIMIT 300");
        $items = [];
        foreach ($stmt as $r) $items[] = ['id' => $r['id'], 'booking' => $r['booking_id'], 'ticket' => $r['ticket_id'], 'event' => $r['event_title'] ?: $r['listing_id'],
                                          'amount' => $this->money($r['amount']), 'reason' => $r['reason'], 'status' => $r['status'],
                                          'requested_at' => $r['requested_at'], 'decided_at' => $r['decided_at']];
        $summary = ['pending' => 0, 'processed' => 0, 'value' => 0.0];
        foreach ($items as $i) {
            if ($i['status'] === 'PENDING') { $summary['pending']++; $summary['value'] += $i['amount']; }
            elseif ($i['status'] === 'PROCESSED' || $i['status'] === 'APPROVED') { $summary['processed']++; $summary['value'] += $i['amount']; }
        }
        return ['schema_version' => self::SCHEMA, 'summary' => $summary, 'items' => $items];
    }

    /* ── fees ───────────────────────────────────────────────────────── */

    public function fees(string $vendorId): array
    {
        $listings = $this->listingIn($vendorId);
        $rate = $this->commissionRate($vendorId);
        $gross = $this->money($this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid'")->fetchColumn());
        $commission = round($gross * $rate / 100, 2);
        $refundFees = 0.0;
        $rStmt = $this->db->query("SELECT COALESCE(SUM(amount),0) FROM event_ticket_refunds WHERE listing_id IN ($listings) AND status IN ('PROCESSED','APPROVED')");
        $refundFees = $this->money($rStmt->fetchColumn());
        $processing = 0.0;
        $byEvent = [];
        $stmt = $this->db->query("SELECT b.listing_id, ev.title, COALESCE(SUM(b.total_price),0) t FROM bookings b
                                   LEFT JOIN tie_events_events ev ON ev.listing_id=b.listing_id
                                   WHERE b.listing_id IN ($listings) AND b.payment_status='Paid' GROUP BY b.listing_id, ev.title ORDER BY t DESC");
        foreach ($stmt as $r) {
            $g = $this->money($r['t']); $fee = round($g * $rate / 100, 2);
            $byEvent[] = ['event' => $r['title'] ?: $r['listing_id'], 'gross' => $g, 'fees' => $fee, 'net' => round($g - $fee, 2)];
        }
        return ['schema_version' => self::SCHEMA, 'rate' => $rate, 'gross' => $gross, 'commission' => $commission,
                'processing' => $processing, 'refund_charges' => $refundFees, 'other' => 0.0,
                'total' => round($commission + $processing + $refundFees, 2), 'by_event' => $byEvent];
    }

    /* ── withdrawals & payout accounts ──────────────────────────────── */

    public function withdrawals(string $vendorId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tie_event_withdrawals WHERE vendor_id=? ORDER BY requested_at DESC LIMIT 200');
        $stmt->execute([$vendorId]);
        $items = [];
        foreach ($stmt->fetchAll() as $r) $items[] = ['id' => $r['id'], 'amount' => $this->money($r['amount']), 'method' => $r['method'],
            'destination' => $r['destination_label'], 'reference' => $r['reference'], 'status' => $r['status'],
            'requested_at' => $r['requested_at'], 'processed_at' => $r['processed_at']];
        return ['schema_version' => self::SCHEMA, 'items' => $items];
    }

    public function accounts(string $vendorId): array
    {
        $stmt = $this->db->prepare('SELECT id, method, label, account_name, provider, is_default, is_verified FROM tie_payment_accounts WHERE vendor_id=? ORDER BY is_default DESC, created_at ASC');
        $stmt->execute([$vendorId]);
        $items = [];
        foreach ($stmt->fetchAll() as $r) $items[] = ['id' => $r['id'], 'method' => $r['method'], 'label' => $r['label'], 'account_number_masked' => '••••' . substr((string) $r['provider'] ?: '', 0, 0) . substr(md5((string) $r['id']), -4),
            'account_name' => $r['account_name'], 'provider' => $r['provider'], 'is_verified' => (bool) $r['is_verified'], 'is_default' => (bool) $r['is_default']];
        return ['schema_version' => self::SCHEMA, 'items' => $items];
    }

    public function saveAccount(string $vendorId, string $actorId, array $input): array
    {
        $method = strtoupper((string) ($input['method'] ?? 'BANK'));
        if (!in_array($method, ['BANK', 'MOBILE_MONEY'], true)) throw UthengaTieErrors::validation(['method' => 'Choose a valid payout method.']);
        $label = trim((string) ($input['label'] ?? ''));
        $accountName = trim((string) ($input['account_name'] ?? ''));
        $accountNumber = trim((string) ($input['account_number'] ?? ''));
        if ($accountName === '' || $accountNumber === '') throw UthengaTieErrors::validation(['account_name' => 'Account name and number are required.']);
        $id = $this->uuid();
        $ins = $this->db->prepare('INSERT INTO tie_payment_accounts (id, vendor_id, method, label, account_name, account_number, provider, is_default, is_verified, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
        $ins->execute([$id, $vendorId, $method, $label ?: ($method === 'BANK' ? 'Bank account' : 'Mobile money'), $accountName, $accountNumber, trim((string) ($input['provider'] ?? '')) ?: null, !empty($input['is_default']), 1]);
        $this->audit($vendorId, $actorId, 'finance.account_saved', ['account_id' => $id, 'method' => $method]);
        return $this->accounts($vendorId);
    }

    public function requestWithdrawal(string $vendorId, string $actorId, array $input): array
    {
        $amount = round((float) ($input['amount'] ?? 0), 2);
        $snapshot = $this->settlementSnapshot($vendorId, 0, 0, 0);
        $available = $snapshot['available_balance'];
        if ($amount <= 0 || $amount > $available) throw UthengaTieErrors::validation(['amount' => 'Enter a valid amount within your available balance.']);
        $account = null;
        if (!empty($input['account_id'])) {
            $stmt = $this->db->prepare('SELECT id, method, label, account_number FROM tie_payment_accounts WHERE id=? AND vendor_id=? LIMIT 1');
            $stmt->execute([$input['account_id'], $vendorId]);
            $account = $stmt->fetch();
            if (!is_array($account)) throw UthengaTieErrors::validation(['account_id' => 'Payout account not found.']);
        }
        $method = strtoupper((string) ($input['method'] ?? 'BANK'));
        $id = $this->uuid();
        $ins = $this->db->prepare('INSERT INTO tie_event_withdrawals (id, vendor_id, amount, method, destination_label, account_number_masked, status, requested_at)
                                   VALUES (?,?,?,?,?,?,?,NOW())');
        $ins->execute([$id, $vendorId, $amount, $method,
            $account ? ($account['label'] ?: $account['method']) : ((string) ($input['destination'] ?? 'Bank account')),
            $account ? ('••••' . substr((string) $account['account_number'], -4)) : ((string) ($input['destination_ref'] ?? '••••0000')),
            'REQUESTED']);
        $this->audit($vendorId, $actorId, 'finance.withdrawal_requested', ['withdrawal_id' => $id, 'amount' => $amount]);
        return $this->withdrawals($vendorId);
    }

    public function markBatchPaid(string $vendorId, string $actorId, array $input): array
    {
        $batchId = (string) ($input['batch_id'] ?? '');
        $reference = trim((string) ($input['reference'] ?? ''));
        $stmt = $this->db->prepare('SELECT * FROM tie_event_settlement_batches WHERE id=? AND vendor_id=? LIMIT 1');
        $stmt->execute([$batchId, $vendorId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['batch_id' => 'Settlement not found.']);
        $this->db->prepare('UPDATE tie_event_settlement_batches SET status=?, paid_at=NOW() WHERE id=?')->execute(['PAID', $batchId]);
        $this->audit($vendorId, $actorId, 'finance.batch_paid', ['batch_id' => $batchId, 'net' => $row['net_amount'], 'reference' => $reference]);
        return $this->settlementSnapshot($vendorId, 0, 0, 0);
    }

    /* ── documents / invoices ───────────────────────────────────────── */

    public function documents(string $vendorId): array
    {
        $stmt = $this->db->prepare('SELECT id, doc_type, reference, event_id, period_start, period_end, created_at FROM tie_finance_documents WHERE vendor_id=? ORDER BY created_at DESC LIMIT 200');
        $stmt->execute([$vendorId]);
        $items = [];
        foreach ($stmt->fetchAll() as $r) $items[] = ['id' => $r['id'], 'doc_type' => $r['doc_type'], 'reference' => $r['reference'], 'event_id' => $r['event_id'], 'period_start' => $r['period_start'], 'period_end' => $r['period_end'], 'created_at' => $r['created_at']];
        return ['schema_version' => self::SCHEMA, 'items' => $items];
    }

    public function generateDocument(string $vendorId, string $actorId, array $input): array
    {
        $type = strtoupper((string) ($input['doc_type'] ?? ''));
        if (!in_array($type, ['SETTLEMENT', 'COMMISSION', 'REFUND', 'EVENT_STATEMENT', 'RECEIPT'], true)) throw UthengaTieErrors::validation(['doc_type' => 'Choose a document type.']);
        $eventId = trim((string) ($input['event_id'] ?? ''));
        $rate = $this->commissionRate($vendorId);
        $payload = ['doc_type' => $type, 'generated_at' => date('c'), 'vendor_id' => $vendorId, 'sections' => []];
        $reference = '';
        $periodStart = null; $periodEnd = null;

        $listings = $this->listingIn($vendorId);
        if ($eventId !== '') {
            $stmt = $this->db->prepare('SELECT listing_id, title FROM tie_events_events WHERE id=? AND vendor_id=? LIMIT 1');
            $stmt->execute([$eventId, $vendorId]);
            $ev = $stmt->fetch();
            if (!is_array($ev)) throw UthengaTieErrors::validation(['event_id' => 'Event not found.']);
            $eventListing = (string) $ev['listing_id'];
            $title = (string) $ev['title'];
        } else { $eventListing = null; $title = null; }

        if ($type === 'EVENT_STATEMENT') {
            if ($eventListing === null) throw UthengaTieErrors::validation(['event_id' => 'Choose an event for the statement.']);
            $rows = [];
            $stmt = $this->db->query("SELECT tt.name, COUNT(DISTINCT b.id) c, COALESCE(SUM(b.total_price),0) t
                                       FROM bookings b JOIN event_tickets et ON et.booking_id=b.id LEFT JOIN ticket_types tt ON tt.id=et.ticket_type_id
                                       WHERE b.listing_id='" . $eventListing . "' AND b.payment_status='Paid' GROUP BY tt.name ORDER BY t DESC");
            foreach ($stmt as $r) $rows[] = ['label' => $r['name'] ?: 'General', 'amount' => $this->money($r['t'])];
            $gross = array_sum(array_column($rows, 'amount'));
            $fee = round($gross * $rate / 100, 2);
            $refStmt = $this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM event_ticket_refunds WHERE listing_id=? AND status=?');
            $refStmt->execute([$eventListing, 'PROCESSED']);
            $refunds = $this->money($refStmt->fetchColumn());
            $payload['title'] = $title . ' — Financial statement';
            $payload['sections'] = [
                ['heading' => 'Revenue', 'lines' => array_merge($rows, [['label' => 'Gross Revenue', 'amount' => $gross, 'total' => true]])],
                ['heading' => 'Deductions', 'lines' => [['label' => "Platform Fee ({$rate}%)", 'amount' => $fee], ['label' => 'Refunds', 'amount' => $refunds]]],
                ['heading' => 'Net Revenue', 'lines' => [['label' => 'Net Revenue', 'amount' => round($gross - $fee - $refunds, 2), 'total' => true]]],
            ];
            $reference = 'EVS-' . $eventId;
            $periodStart = date('Y-m-01'); $periodEnd = date('Y-m-d');
        } elseif ($type === 'COMMISSION') {
            $fees = $this->fees($vendorId);
            $payload['title'] = 'Commission statement';
            $payload['sections'] = [
                ['heading' => 'Uthenga deductions', 'lines' => [
                    ['label' => "Platform Commission ({$rate}%)", 'amount' => $fees['commission']],
                    ['label' => 'Payment Processing', 'amount' => $fees['processing']],
                    ['label' => 'Refund Charges', 'amount' => $fees['refund_charges']],
                    ['label' => 'Total Deductions', 'amount' => $fees['total'], 'total' => true],
                ]],
            ];
            $reference = 'COM-' . date('Ym');
            $periodStart = date('Y-m-01'); $periodEnd = date('Y-m-d');
        } elseif ($type === 'REFUND') {
            $ref = $this->refunds($vendorId);
            $payload['title'] = 'Refund statement';
            $payload['sections'] = [['heading' => 'Refunds', 'lines' => array_map(fn($i) => ['label' => $i['id'] . ($i['event'] ? ' — ' . $i['event'] : ''), 'amount' => $i['amount']], $ref['items'])],
                ['heading' => 'Totals', 'lines' => [['label' => 'Total Refunds', 'amount' => $ref['summary']['value'], 'total' => true]]]];
            $reference = 'REF-' . date('Ym');
            $periodStart = date('Y-m-01'); $periodEnd = date('Y-m-d');
        } else {
            $snap = $this->settlementSnapshot($vendorId, 0, 0, 0);
            $batch = $snap['batches'][0] ?? null;
            if ($batch) {
                $payload['title'] = 'Settlement statement — period ' . $batch['period_start'] . ' to ' . $batch['period_end'];
                $payload['sections'] = [['heading' => 'Settlement', 'lines' => [
                    ['label' => 'Gross Revenue', 'amount' => $batch['gross_amount']],
                    ['label' => 'Platform Fees', 'amount' => $batch['platform_fee']],
                    ['label' => 'Processing Fees', 'amount' => $batch['processing_fee']],
                    ['label' => 'Refunds', 'amount' => $batch['refunds_total']],
                    ['label' => 'Net Settlement', 'amount' => $batch['net_amount'], 'total' => true],
                ]]];
                $reference = 'STL-' . date('Ym');
            } else {
                $payload['title'] = 'Settlement statement (no settled period yet)';
                $payload['sections'] = [['heading' => 'Settlement', 'lines' => [['label' => 'Gross Revenue', 'amount' => $snap['pending_net']]]]];
                $reference = 'STL-' . date('Ym');
            }
        }

        $id = $this->uuid();
        $ins = $this->db->prepare('INSERT INTO tie_finance_documents (id, vendor_id, doc_type, reference, event_id, period_start, period_end, payload, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
        $ins->execute([$id, $vendorId, $type, $reference, $eventId !== '' ? $eventId : null, $periodStart, $periodEnd, json_encode($payload)]);
        $this->audit($vendorId, $actorId, 'finance.doc_generated', ['doc_id' => $id, 'type' => $type]);
        return ['schema_version' => self::SCHEMA, 'document' => array_merge($this->document($vendorId, $id), ['payload' => $payload])];
    }

    public function document(string $vendorId, string $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tie_finance_documents WHERE id=? AND vendor_id=? LIMIT 1');
        $stmt->execute([$id, $vendorId]);
        $r = $stmt->fetch();
        if (!is_array($r)) throw UthengaTieErrors::validation(['id' => 'Document not found.']);
        return ['id' => $r['id'], 'doc_type' => $r['doc_type'], 'reference' => $r['reference'], 'event_id' => $r['event_id'],
                'period_start' => $r['period_start'], 'period_end' => $r['period_end'], 'created_at' => $r['created_at'],
                'payload' => json_decode((string) $r['payload'], true) ?: []];
    }

    /* ── reconciliation ─────────────────────────────────────────────── */

    public function reconciliationStatus(string $vendorId): array
    {
        $listings = $this->listingIn($vendorId);
        $openStmt = $this->db->prepare('SELECT COUNT(*) FROM tie_reconciliation_exceptions WHERE vendor_id=? AND status=?');
        $openStmt->execute([$vendorId, 'OPEN']);
        $open = (int) $openStmt->fetchColumn();
        $last = $this->db->prepare('SELECT * FROM tie_reconciliation_runs WHERE vendor_id=? ORDER BY checked_at DESC LIMIT 1');
        $last->execute([$vendorId]);
        $row = $last->fetch();
        return [
            'status' => ($row ? (string) $row['result_status'] : 'BALANCED'),
            'checked_at' => $row ? $row['checked_at'] : null,
            'difference' => $row ? $this->money($row['difference']) : 0.0,
            'expected_amount' => $row ? $this->money($row['expected_amount']) : 0.0,
            'recorded_amount' => $row ? $this->money($row['recorded_amount']) : 0.0,
            'exception_count' => $row ? (int) $row['exception_count'] : (int) $open,
            'open_exceptions' => $open,
            'matches' => [
                ['label' => 'Ticket sales matched', 'ok' => $this->paidWithoutTickets($vendorId) === 0],
                ['label' => 'Payments matched', 'ok' => $this->paymentMismatches($vendorId) === 0],
                ['label' => 'Refunds matched', 'ok' => $this->refundMismatches($vendorId) === 0],
                ['label' => 'Fees matched', 'ok' => $this->feeMismatches($vendorId) === 0],
                ['label' => 'Settlement matched', 'ok' => $this->settlementMismatches($vendorId) === 0],
            ],
        ];
    }

    private function paidWithoutTickets(string $vendorId): int
    {
        $listings = $this->listingIn($vendorId);
        return (int) $this->db->query("SELECT COUNT(*) FROM bookings b WHERE b.listing_id IN ($listings) AND b.payment_status='Paid'
                                       AND NOT EXISTS (SELECT 1 FROM event_tickets t WHERE t.booking_id=b.id)")->fetchColumn();
    }

    private function paymentMismatches(string $vendorId): int
    {
        $listings = $this->listingIn($vendorId);
        $stmt = $this->db->query("SELECT b.id, b.total_price FROM bookings b WHERE b.listing_id IN ($listings) AND b.payment_status='Paid' AND b.transaction_id IS NOT NULL
                                  AND EXISTS (SELECT 1 FROM transactions t WHERE t.id=b.transaction_id)");
        $mismatch = 0;
        foreach ($stmt as $b) {
            $tx = $this->db->prepare('SELECT amount FROM transactions WHERE id=?');
            $tx->execute([$b['transaction_id']]);
            $amt = $tx->fetchColumn();
            if ($amt !== false && abs((float) $amt - (float) $b['total_price']) > 0.005) $mismatch++;
        }
        return $mismatch;
    }

    private function refundMismatches(string $vendorId): int
    {
        $listings = $this->listingIn($vendorId);
        $stmt = $this->db->query("SELECT r.* FROM event_ticket_refunds r WHERE r.listing_id IN ($listings) AND r.status='PROCESSED'");
        $bad = 0;
        foreach ($stmt as $r) {
            if ($r['booking_id']) {
                $b = $this->db->prepare('SELECT total_price FROM bookings WHERE id=?');
                $b->execute([$r['booking_id']]);
                $tot = $b->fetchColumn();
                if ($tot !== false && (float) $r['amount'] > (float) $tot + 0.005) { $bad++; continue; }
            }
            if ($r['ticket_id']) {
                $t = $this->db->prepare('SELECT status FROM event_tickets WHERE id=?');
                $t->execute([$r['ticket_id']]);
                $st = $t->fetchColumn();
                if ($st !== false && $st !== 'REFUNDED') $bad++;
            }
        }
        return $bad;
    }

    private function feeMismatches(string $vendorId): int
    {
        $snap = $this->settlementSnapshot($vendorId, 0, 0, 0);
        foreach ($snap['batches'] as $b) {
            $expected = round((float) $b['gross_amount'] * $this->commissionRate($vendorId) / 100, 2);
            if (abs($expected - (float) $b['platform_fee']) > 0.005) return 1;
        }
        return 0;
    }

    private function settlementMismatches(string $vendorId): int
    {
        $snap = $this->settlementSnapshot($vendorId, 0, 0, 0);
        return count(array_filter($snap['batches'], fn($b) => abs((float) $b['net_amount'] - ((float) $b['gross_amount'] - (float) $b['platform_fee'] - (float) $b['processing_fee'] - (float) $b['refunds_total'])) > 0.005));
    }

    public function runReconciliation(string $vendorId, string $actorId): array
    {
        $listings = $this->listingIn($vendorId);
        $expected = $this->money((float) $this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid'")->fetchColumn());
        $fees = round($expected * $this->commissionRate($vendorId) / 100, 2);
        $refunds = $this->money($this->db->query("SELECT COALESCE(SUM(amount),0) FROM event_ticket_refunds WHERE listing_id IN ($listings) AND status='PROCESSED'")->fetchColumn());
        $recorded = round($expected - $fees - $refunds, 2);

        $exceptions = [];

        $noTick = $this->db->query("SELECT b.id, b.total_price FROM bookings b WHERE b.listing_id IN ($listings) AND b.payment_status='Paid'
                                    AND NOT EXISTS (SELECT 1 FROM event_tickets t WHERE t.booking_id=b.id)");
        foreach ($noTick as $b) $exceptions[] = ['category' => 'TICKET', 'reference' => $b['id'], 'expected' => $this->money($b['total_price']), 'recorded' => 0.0, 'note' => 'Paid booking has no issued ticket.'];

        $pm = $this->db->query("SELECT b.id, b.total_price, b.transaction_id FROM bookings b WHERE b.listing_id IN ($listings) AND b.payment_status='Paid' AND b.transaction_id IS NOT NULL
                                AND EXISTS (SELECT 1 FROM transactions t WHERE t.id=b.transaction_id)");
        foreach ($pm as $b) {
            $tx = $this->db->prepare('SELECT amount FROM transactions WHERE id=?');
            $tx->execute([$b['transaction_id']]);
            $amt = $tx->fetchColumn();
            if ($amt !== false && abs((float) $amt - (float) $b['total_price']) > 0.005)
                $exceptions[] = ['category' => 'PAYMENT', 'reference' => $b['id'], 'expected' => $this->money($b['total_price']), 'recorded' => $this->money((float) $amt), 'note' => 'Transaction amount differs from booking.'];

        }
        $rm = $this->db->query("SELECT r.id, r.amount, r.booking_id, r.ticket_id FROM event_ticket_refunds r WHERE r.listing_id IN ($listings) AND r.status='PROCESSED' AND r.ticket_id IS NOT NULL");
        foreach ($rm as $r) {
            $t = $this->db->prepare('SELECT status FROM event_tickets WHERE id=?');
            $t->execute([$r['ticket_id']]);
            $st = $t->fetchColumn();
            if ($st !== false && $st !== 'REFUNDED') $exceptions[] = ['category' => 'REFUND', 'reference' => $r['id'], 'expected' => $this->money($r['amount']), 'recorded' => 0.0, 'note' => 'Processed refund but ticket is not marked REFUNDED.'];
        }

        $runId = $this->uuid();
        $ins = $this->db->prepare('INSERT INTO tie_reconciliation_runs (id, vendor_id, result_status, expected_amount, recorded_amount, difference, exception_count, summary, checked_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
        $ins->execute([$runId, $vendorId, $exceptions ? 'ISSUES' : 'BALANCED', $expected, $recorded, round($expected - $recorded, 2), count($exceptions), json_encode(array_column($exceptions, 'category'))]);
        foreach ($exceptions as $e) {
            $this->db->prepare('INSERT INTO tie_reconciliation_exceptions (id, vendor_id, run_id, category, reference, expected_amount, recorded_amount, status, resolution_note, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())')
                ->execute([$this->uuid(), $vendorId, $runId, $e['category'], $e['reference'], $e['expected'], $e['recorded'], 'OPEN', $e['note']]);
        }
        $this->audit($vendorId, $actorId, 'finance.reconciliation_run', ['run_id' => $runId, 'exceptions' => count($exceptions)]);

        return array_merge($this->reconciliationStatus($vendorId), [
            'run_id' => $runId, 'expected_amount' => $expected, 'recorded_amount' => $recorded,
            'difference' => round($expected - $recorded, 2), 'exception_count' => count($exceptions), 'summary' => $exceptions,
        ]);
    }

    public function exceptions(string $vendorId, string $status = 'OPEN'): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tie_reconciliation_exceptions WHERE vendor_id=? AND status=? ORDER BY created_at DESC LIMIT 200');
        $stmt->execute([$vendorId, $status]);
        $items = [];
        foreach ($stmt->fetchAll() as $r) $items[] = ['id' => $r['id'], 'category' => $r['category'], 'reference' => $r['reference'],
            'expected_amount' => $this->money($r['expected_amount']), 'recorded_amount' => $this->money($r['recorded_amount']),
            'status' => $r['status'], 'resolution_note' => $r['resolution_note'], 'created_at' => $r['created_at'], 'resolved_at' => $r['resolved_at']];
        return ['schema_version' => self::SCHEMA, 'items' => $items];
    }

    public function resolveException(string $vendorId, string $actorId, array $input): array
    {
        $id = (string) ($input['id'] ?? '');
        $note = trim((string) ($input['note'] ?? 'Resolved by organizer.'));
        $stmt = $this->db->prepare('SELECT * FROM tie_reconciliation_exceptions WHERE id=? AND vendor_id=? LIMIT 1');
        $stmt->execute([$id, $vendorId]);
        if (!$stmt->fetch()) throw UthengaTieErrors::validation(['id' => 'Exception not found.']);
        $this->db->prepare("UPDATE tie_reconciliation_exceptions SET status='RESOLVED', resolution_note=?, resolved_at=NOW() WHERE id=?")->execute([$note, $id]);
        $this->audit($vendorId, $actorId, 'finance.exception_resolved', ['exception_id' => $id]);
        return $this->exceptions($vendorId, 'OPEN');
    }

    /* ── CSV export ─────────────────────────────────────────────────── */

    public function exportCsv(string $vendorId, array $filters = []): string
    {
        $rows = $this->transactions($vendorId, $filters, 5000, 0);
        $out = "Transaction,Customer,Event,Amount,Status,Method,Ticket ref,Date\n";
        foreach ($rows['items'] as $i) {
            $out .= '"' . $i['reference'] . '","", "' . str_replace('"', '""', (string) $i['event']) . '","' . $i['amount'] . '","' . $i['status'] . '","' . str_replace('"', '""', (string) $i['method']) . '","","' . $i['date'] . "\"\n";
        }
        return $out;
    }

    /* ── finance AI advisor (read-only) ─────────────────────────────── */

    public function advisor(string $vendorId, string $message): array
    {
        $o = $this->overview($vendorId, $vendorId);
        $evidence = [
            'gross_revenue' => $o['gross_revenue'], 'platform_fee' => $o['platform_fee'], 'refunds_total' => $o['refunds_total'],
            'net_revenue' => $o['net_revenue'], 'commission_rate' => $o['commission_rate'], 'paid_transactions' => $o['paid_transactions'],
            'available_balance' => $o['settlement']['available_balance'], 'pending_settlement' => $o['settlement']['pending_net'],
            'pending_count' => $o['settlement']['pending_count'], 'open_reconciliation_exceptions' => $o['reconciliation']['open_exceptions'],
            'refund_pending' => $o['refund_pending'], 'top_method' => $o['payment_methods'][0]['method'] ?? '—',
            'alerts' => array_map(fn($a) => $a['title'], $o['alerts']),
        ];
        $prompt = [
            'prompt_version' => 'finance-advisor/v1',
            'system' => "You are Uthenga's event finance analyst. You answer organizers about THEIR event revenue using ONLY the supplied evidence. " .
                'You never issue refunds, initiate withdrawals, modify records, or change fees — you explain and recommend only. ' .
                'Money is in MWK. Be concise and cite evidence figures. If the answer is not in the evidence, say so.',
            'user_message' => mb_substr(trim($message), 0, 1200),
            'conversation_history' => [],
            'evidence' => ['finance' => $evidence],
        ];
        $schema = [
            'name' => 'finance_advisor_response',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'suggested_actions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'follow_up_questions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'confidence' => ['type' => 'string', 'enum' => ['HIGH', 'MEDIUM', 'LOW']],
                ],
                'required' => ['message', 'suggested_actions', 'follow_up_questions', 'confidence'],
                'additionalProperties' => false,
            ],
        ];

        $answer = null; $usedFallback = false;
        try {
            $raw = (new UthengaTieKernel())->llm->generateStructured($prompt, $schema);
            if (is_array($raw) && !empty($raw['message'])) {
                $m = mb_substr((string) $raw['message'], 0, 2000);
                if (!preg_match('/book|confirm|process|refund|withdraw|initiate|execute/i', $m)) {
                    $answer = ['message' => $m,
                        'suggested_actions' => array_slice(array_map('strval', $raw['suggested_actions'] ?? []), 0, 4),
                        'follow_up_questions' => array_slice(array_map('strval', $raw['follow_up_questions'] ?? []), 0, 3),
                        'confidence' => in_array($raw['confidence'] ?? '', ['HIGH', 'MEDIUM', 'LOW'], true) ? $raw['confidence'] : 'MEDIUM'];
                }
            }
        } catch (Throwable) { $usedFallback = true; }
        if ($answer === null) { $answer = $this->fallbackAdvisor($evidence, (string) $prompt['user_message']); $usedFallback = true; }
        $answer['evidence'] = $evidence;
        $answer['fallback_used'] = $usedFallback;
        return $answer;
    }

    private function fallbackAdvisor(array $e, string $message): array
    {
        $m = strtolower($message);
        $mk = fn(float $n) => 'MK ' . number_format($n, 0);
        if (str_contains($m, 'settle') || str_contains($m, 'pending')) {
            $body = $e['pending_settlement'] > 0
                ? "You have " . $mk((float) $e['pending_settlement']) . " pending settlement across " . $e['pending_count'] . " completed transactions. It becomes available once you create a settlement batch."
                : 'There is no revenue pending settlement right now.';
        } elseif (str_contains($m, 'refund')) {
            $body = ($e['refund_pending'] > 0 ? $e['refund_pending'] . " refunds are awaiting your approval. " : "") . "Refunds already processed total " . $mk((float) $e['refunds_total']) . ".";
        } elseif (str_contains($m, 'fee') || str_contains($m, 'commission')) {
            $body = "Uthenga commission is applied at " . $e['commission_rate'] . "% of gross ticket sales. So far that is " . $mk((float) $e['platform_fee']) . " on gross revenue of " . $mk((float) $e['gross_revenue']) . ".";
        } elseif (str_contains($m, 'available') || str_contains($m, 'withdraw')) {
            $body = "Your available balance is " . $mk((float) $e['available_balance']) . ". Request a withdrawal from the Settlements tab.";
        } elseif (str_contains($m, 'reconcil')) {
            $body = $e['open_reconciliation_exceptions'] > 0
                ? $e['open_reconciliation_exceptions'] . " reconciliation exception" . ($e['open_reconciliation_exceptions'] === 1 ? '' : 's') . " are open — review them in the Reconciliation tab."
                : "Reconciliation is balanced with a difference of MK 0.";
        } elseif (str_contains($m, 'event') || str_contains($m, 'much')) {
            $body = "Across your events, gross revenue is " . $mk((float) $e['gross_revenue']) . ", net revenue after Platform commission and refunds is " . $mk((float) $e['net_revenue']) . ", from " . $e['paid_transactions'] . " paid transactions.";
        } else {
            $body = "From your finance evidence: gross revenue " . $mk((float) $e['gross_revenue']) . ", net " . $mk((float) $e['net_revenue']) . ", available balance " . $mk((float) $e['available_balance']) . ", pending settlement " . $mk((float) $e['pending_settlement']) . ". Ask me about settlements, fees, refunds, reconciliation or specific events.";
        }
        return ['message' => $body, 'suggested_actions' => ['View Overview', 'Open Settlements', 'Run Reconciliation'], 'follow_up_questions' => ['How much is pending settlement?', 'What are my fees?'], 'confidence' => 'HIGH'];
    }
}