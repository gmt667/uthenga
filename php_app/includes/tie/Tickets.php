<?php
/**
 * Uthenga — Ticket Commerce & Ticket Lifecycle Service
 *
 * Bridges Events, Customers, Payments, Attendees and Check-In LIVE:
 * inventory, pricing, orders, issuance, transfers, refunds, audit trail.
 */

require_once __DIR__ . '/../payment_engine.php';

final class UthengaTicketsService
{
    public function __construct(private PDO $db) {}

    // ------------------------------------------------------------------
    // Context & helpers
    // ------------------------------------------------------------------

    private function listingRow(string $listingId, string $vendorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT l.*, e.id AS event_id, e.status AS event_status, e.start_date, e.start_time, e.venue_id,
                    v.name AS venue_name, v.city AS venue_city,
                    COALESCE(SUM(tt.total_quantity),0) AS capacity_total,
                    COALESCE(SUM(tt.total_quantity - tt.remaining_quantity),0) AS sold_total
             FROM listings l
             LEFT JOIN tie_events_events e ON e.listing_id = l.id
             LEFT JOIN tie_venues v ON v.id = e.venue_id
             LEFT JOIN ticket_types tt ON tt.listing_id = l.id
             WHERE l.id = ? AND l.listing_type = 'event'
             GROUP BY l.id
             LIMIT 1"
        );
        $stmt->execute([$listingId]);
        $row = $stmt->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['listing_id' => 'This event does not exist.']);

        $owner = $this->db->prepare('SELECT vendor_id FROM tie_events_events WHERE listing_id=? LIMIT 1');
        $owner->execute([$listingId]);
        $ownerId = (string) ($owner->fetchColumn() ?: ($row['vendor_id'] ?? ''));
        if ($ownerId !== '' && $ownerId !== $vendorId) throw UthengaTieErrors::authorization();
        return $row;
    }

    private function audit(string $listingId, string $actorId, string $action, array $details = [], ?int $ticketTypeId = null, ?string $ticketId = null, ?string $bookingId = null): void
    {
        $name = '';
        if ($actorId !== '') {
            $stmt = $this->db->prepare('SELECT name FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$actorId]);
            $name = (string) ($stmt->fetchColumn() ?: '');
        }
        $this->db->prepare(
            'INSERT INTO event_ticket_audit (listing_id, ticket_type_id, ticket_id, booking_id, actor_id, actor_name, action, details) VALUES (?,?,?,?,?,?,?,?)'
        )->execute([$listingId, $ticketTypeId, $ticketId, $bookingId, $actorId !== '' ? $actorId : null, $name, $action, json_encode($details, JSON_UNESCAPED_SLASHES)]);
    }

    private function ticketStatusAggregate(string $listingId): array
    {
        return [
            'issued'   => (int) $this->countVal("SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND status NOT IN ('CANCELLED','REFUNDED')", [$listingId]),
            'checked_in' => (int) $this->countVal("SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND checked_in_at IS NOT NULL", [$listingId]),
            'refunded'   => (int) $this->countVal("SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND status='REFUNDED'", [$listingId]),
            'cancelled'  => (int) $this->countVal("SELECT COUNT(*) FROM event_tickets WHERE listing_id=? AND status='CANCELLED'", [$listingId]),
        ];
    }

    private function countVal(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function typeName(int $ticketTypeId): string
    {
        $stmt = $this->db->prepare('SELECT name FROM ticket_types WHERE id=? LIMIT 1');
        $stmt->execute([$ticketTypeId]);
        return (string) ($stmt->fetchColumn() ?: 'Ticket');
    }

    private function activeTypesOnly(string $sql): string { return $sql; }

    // ------------------------------------------------------------------
    // Typed input helpers
    // ------------------------------------------------------------------

    private function positiveInt(mixed $v, string $field, int $max = PHP_INT_MAX, int $min = 0): int
    {
        $n = (int) ($v ?? 0);
        if ($n < $min || $n > $max) throw UthengaTieErrors::validation([$field => "Must be between $min and $max."]);
        return $n;
    }

    private function money(mixed $v, string $field): float
    {
        $f = round((float) ($v ?? 0), 2);
        if ($f < 0 || $f > 100_000_000) throw UthengaTieErrors::validation([$field => 'Price is out of range.']);
        return $f;
    }

    // ------------------------------------------------------------------
    // Workspace snapshot (KPIs, velocity, insights, channels, types)
    // ------------------------------------------------------------------

    public function workspace(string $listingId, string $vendorId): array
    {
        $listing = $this->listingRow($listingId, $vendorId);

        $types = $this->typesList($listingId);
        $issuedAgg = $this->ticketStatusAggregate($listingId);

        $capacity = 0; $available = 0; $activeCapacity = 0; $sold = 0; $revenue = 0; $typeRevenue = 0;
        foreach ($types as $t) {
            $capacity += $t['total_quantity'];
            $sold += $t['sold'];
            $typeRevenue += $t['revenue'];
            if ($t['is_active'] && in_array($t['ticket_status'], ['ACTIVE', 'SCHEDULED', 'SOLD_OUT'], true)) {
                $activeCapacity += $t['total_quantity'];
                $available += $t['available'];
            }
        }

        $st = $this->db->prepare(
            "SELECT
                COUNT(*) AS orders_total,
                SUM(CASE WHEN payment_status='Paid' THEN 1 ELSE 0 END) AS orders_paid,
                SUM(CASE WHEN payment_status NOT IN ('Paid','Refunded','Cancelled','Failed') THEN 1 ELSE 0 END) AS orders_pending,
                SUM(CASE WHEN payment_status IN ('Pending','Payment Pending','Processing') THEN 1 ELSE 0 END) AS orders_awaiting,
                SUM(CASE WHEN payment_status='Refunded' THEN 1 ELSE 0 END) AS orders_refunded,
                SUM(CASE WHEN payment_status='Failed' THEN 1 ELSE 0 END) AS orders_failed,
                SUM(CASE WHEN payment_status='Paid' THEN total_price ELSE 0 END) AS revenue_paid
             FROM bookings
             WHERE listing_id=? AND deleted_at IS NULL"
        );
        $st->execute([$listingId]);
        $orders = $st->fetch() ?: [];
        $revenue = (float) ($orders['revenue_paid'] ?? 0);

        $refundAgg = $this->db->prepare(
            "SELECT SUM(CASE WHEN status IN ('APPROVED','PROCESSED') THEN 1 ELSE 0 END) AS processed,
                    SUM(CASE WHEN amount IS NOT NULL AND status IN ('APPROVED','PROCESSED') THEN amount ELSE 0 END) AS amount
             FROM event_ticket_refunds WHERE listing_id=?"
        );
        $refundAgg->execute([$listingId]);
        $refunds = $refundAgg->fetch() ?: [];

        $pendingHolds = $this->countVal(
            "SELECT COUNT(*) FROM tie_inventory_holds h
             JOIN bookings b ON b.id = h.booking_id
             WHERE h.listing_id=? AND h.status='HELD' AND b.deleted_at IS NULL",
            [$listingId]
        );

        $sellThrough = $activeCapacity > 0 ? round(($sold / $activeCapacity) * 100, 1) : 0.0;
        $velocity = $this->velocity($listingId, '30D');

        return [
            'listing' => [
                'id' => $listing['id'],
                'title' => $listing['title'],
                'event_id' => $listing['event_id'],
                'event_status' => $listing['event_status'],
                'location' => $listing['location'],
                'start_date' => $listing['start_date'],
                'start_time' => $listing['start_time'],
                'venue_name' => $listing['venue_name'],
                'venue_city' => $listing['venue_city'],
                'is_published' => (int) $listing['is_active'] === 1,
            ],
            'kpis' => [
                'sold' => $sold,
                'capacity' => $capacity,
                'active_capacity' => $activeCapacity,
                'available' => $available,
                'sell_through' => $sellThrough,
                'revenue' => $revenue,
                'type_revenue' => $typeRevenue,
                'pending_orders' => (int) ($orders['orders_awaiting'] ?? 0),
                'orders_total' => (int) ($orders['orders_total'] ?? 0),
                'orders_paid' => (int) ($orders['orders_paid'] ?? 0),
                'orders_refunded' => (int) ($orders['orders_refunded'] ?? 0),
                'orders_failed' => (int) ($orders['orders_failed'] ?? 0),
                'refunded_count' => $issuedAgg['refunded'] + $issuedAgg['cancelled'] + (int) ($refunds['processed'] ?? 0),
                'issued' => $issuedAgg['issued'],
                'checked_in' => $issuedAgg['checked_in'],
                'checkin_rate' => $issuedAgg['issued'] > 0 ? round(($issuedAgg['checked_in'] / $issuedAgg['issued']) * 100, 1) : 0.0,
                'pending_holds' => $pendingHolds,
            ],
            'types' => $types,
            'velocity' => $velocity,
            'channels' => $this->channels($listingId),
            'insights' => $this->insights($listing, $types, $velocity, $sellThrough),
            'recent_orders' => $this->ordersList($listingId, 'all', '', 5),
            'refund_total' => (float) ($refunds['amount'] ?? 0),
            'schema_version' => 'tie-tickets-workspace/v1',
        ];
    }

    public function velocity(string $listingId, string $range = '7D'): array
    {
        $days = match ($range) { '24H' => 1, '7D' => 7, '30D' => 30, 'LIFETIME' => 0, default => 7 };
        $start = $days > 0 ? date('Y-m-d', strtotime("-$days days")) : '1970-01-01';
        $st = $this->db->prepare(
            "SELECT DATE(booked_at) AS d, COUNT(*) AS n, COALESCE(SUM(total_price),0) AS amount
             FROM bookings
             WHERE listing_id=? AND deleted_at IS NULL AND payment_status='Paid'
               AND booked_at >= ?
             GROUP BY DATE(booked_at) ORDER BY d ASC"
        );
        $st->execute([$listingId, $start . ' 00:00:00']);
        $rows = [];
        foreach ($st->fetchAll() as $r) $rows[$r['d']] = ['count' => (int) $r['n'], 'amount' => (float) $r['amount']];

        if ($days > 0) {
            $series = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $series[] = ['date' => $d, 'count' => (int) ($rows[$d]['count'] ?? 0), 'amount' => (float) ($rows[$d]['amount'] ?? 0)];
            }
            return ['range' => $range, 'days' => $days, 'series' => $series];
        }

        $series = [];
        foreach ($rows as $d => $r) $series[] = ['date' => $d, 'count' => $r['count'], 'amount' => $r['amount']];
        return ['range' => 'LIFETIME', 'days' => count($series), 'series' => $series];
    }

    private function channels(string $listingId): array
    {
        $st = $this->db->prepare(
            "SELECT COALESCE(NULLIF(payment_gateway,''),'Manual / Offline') AS channel, COUNT(*) AS n, COALESCE(SUM(total_price),0) AS amount
             FROM bookings
             WHERE listing_id=? AND deleted_at IS NULL AND payment_status='Paid'
             GROUP BY channel ORDER BY n DESC"
        );
        $st->execute([$listingId]);
        $rows = $st->fetchAll();
        $total = array_sum(array_column($rows, 'n'));
        foreach ($rows as &$r) {
            $r['share'] = $total > 0 ? round(((int) $r['n'] / $total) * 100, 1) : 0.0;
        }
        return $rows;
    }

    private function insights(array $listing, array $types, array $velocity, float $sellThrough): array
    {
        $out = [];
        $total = (int) ($listing['capacity_total'] ?? 0);
        $sold = (int) ($listing['sold_total'] ?? 0);
        $today = date('Y-m-d');

        foreach ($types as $t) {
            $name = $t['name'];
            if ($t['available'] <= 0 && $t['sold'] > 0 && $t['ticket_status'] !== 'CLOSED' && $t['ticket_status'] !== 'ARCHIVED') {
                $out[] = ['level' => 'success', 'message' => "$name tickets are sold out."];
            } elseif ($t['total_quantity'] > 0 && $t['sold'] / $t['total_quantity'] >= 0.8 && $t['ticket_status'] === 'ACTIVE') {
                $pct = round(($t['sold'] / $t['total_quantity']) * 100);
                $out[] = ['level' => 'warn', 'message' => "$name tickets are {$pct}% sold — low inventory."];
            }
            if ($t['sale_end'] && $t['ticket_status'] === 'ACTIVE' && substr((string) $t['sale_end'], 0, 10) === $today) {
                $out[] = ['level' => 'warn', 'message' => "Sales for $name close today."];
            }
            if ($t['sale_end'] && $t['ticket_status'] === 'SCHEDULED' && $t['sale_start'] && $t['sale_start'] <= $today . ' 23:59:59') {
                $out[] = ['level' => 'info', 'message' => "$name sales are now open."];
            }
        }

        $week = array_slice($velocity['series'] ?? [], -7);
        $weekCount = array_sum(array_column($week, 'count'));
        $prev = array_slice($velocity['series'] ?? [], -14, -7);
        $prevCount = array_sum(array_column($prev, 'count'));
        if ($prevCount > 0 && $weekCount > $prevCount) {
            $pct = round((($weekCount - $prevCount) / $prevCount) * 100);
            $out[] = ['level' => 'info', 'message' => "Ticket sales increased {$pct}% this week."];
        } elseif ($prevCount > 0 && $weekCount === 0) {
            $out[] = ['level' => 'info', 'message' => 'No confirmed sales in the last 7 days.'];
        }

        if ($sellThrough < 20 && $listing['start_date'] && $listing['start_date'] <= date('Y-m-d', strtotime('+14 days')) && $listing['start_date'] >= $today) {
            $out[] = ['level' => 'warn', 'message' => "Only " . round($sellThrough) . "% of available tickets have sold with the event less than 14 days away."];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Ticket types
    // ------------------------------------------------------------------

    public function typesList(string $listingId): array
    {
        $st = $this->db->prepare(
            "SELECT tt.*,
                    (tt.total_quantity - tt.remaining_quantity) AS sold,
                    tt.remaining_quantity AS available,
                    (SELECT COUNT(*) FROM event_tickets et WHERE et.ticket_type_id = tt.id AND et.status NOT IN ('CANCELLED','REFUNDED')) AS issued_count,
                    (SELECT COUNT(*) FROM event_tickets et WHERE et.ticket_type_id = tt.id AND et.checked_in_at IS NOT NULL) AS checked_in_count
             FROM ticket_types tt
             WHERE tt.listing_id = ?
             ORDER BY tt.created_at ASC"
        );
        $st->execute([$listingId]);
        return array_map(static function (array $t): array {
            return [
                'id' => (int) $t['id'],
                'name' => $t['name'],
                'description' => $t['description'],
                'category' => $t['category'] ?: (strtoupper((string) $t['tier']) ?: 'General Admission'),
                'internal_code' => $t['internal_code'],
                'price' => (float) $t['price'],
                'fee_percent' => (float) $t['fee_percent'],
                'total_quantity' => (int) $t['total_quantity'],
                'max_per_customer' => (int) $t['max_per_customer'],
                'min_qty' => (int) $t['min_qty'],
                'sale_start' => $t['sale_start'],
                'sale_end' => $t['sale_end'],
                'is_active' => (int) $t['is_active'],
                'ticket_status' => strtoupper((string) $t['ticket_status']),
                'tier' => $t['tier'],
                'access_scope' => $t['access_scope'],
                'transferable' => (int) $t['transferable'],
                'refundable' => (int) $t['refundable'],
                'access_rules' => json_decode((string) ($t['access_rules'] ?? '[]'), true) ?: [],
                'branding' => json_decode((string) ($t['branding'] ?? '{}'), true) ?: [],
                'sold' => (int) $t['sold'],
                'available' => (int) $t['available'],
                'revenue' => round(((float) $t['total_quantity'] - (float) $t['remaining_quantity']) * (float) $t['price'], 2),
                'checked_in_count' => (int) $t['checked_in_count'],
                'issued_count' => (int) $t['issued_count'],
                'sort_order' => (int) $t['sort_order'],
            ];
        }, $st->fetchAll());
    }

    private function normalizeTypeInput(array $input): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') $errors['name'] = 'Ticket name is required.';
        if (strlen($name) > 80) $errors['name'] = 'Ticket name is too long.';

        $category = trim((string) ($input['category'] ?? 'General Admission'));
        $allowedCats = ['General Admission', 'VIP', 'VVIP', 'Student', 'Group', 'Corporate', 'Complimentary', 'Early Bird', 'Season Pass', 'Press', 'Sponsor', 'Staff', 'Other'];
        if (!in_array($category, $allowedCats, true)) $category = 'Other';

        $price = $this->money($input['price'] ?? 0, 'price');
        $feePercent = round(min(max((float) ($input['fee_percent'] ?? 10.0), 0), 100), 2);
        $totalQty = $this->positiveInt($input['total_quantity'] ?? 0, 'total_quantity', 1_000_000, 1);
        $maxPerCustomer = $this->positiveInt($input['max_per_customer'] ?? 0, 'max_per_customer', 100_000);
        $minQty = $this->positiveInt($input['min_qty'] ?? 1, 'min_qty', 100, 1);
        $transferable = !empty($input['transferable']);
        $refundable = !empty($input['refundable']);

        $saleStart = null; $saleEnd = null;
        if (!empty($input['sale_start'])) {
            $saleStart = date('Y-m-d H:i:s', strtotime((string) $input['sale_start'])) ?: null;
        }
        if (!empty($input['sale_end'])) {
            $saleEnd = date('Y-m-d H:i:s', strtotime((string) $input['sale_end'])) ?: null;
        }
        if ($saleStart && $saleEnd && $saleEnd < $saleStart) $errors['sale_end'] = 'Sales end must be after sales start.';

        $tierMap = ['VIP' => 'vip', 'VVIP' => 'vip', 'Student' => 'student', 'Group' => 'group', 'Corporate' => 'corporate', 'Complimentary' => 'complimentary'];
        $tier = $tierMap[$category] ?? 'standard';

        $accessRules = $input['access_rules'] ?? [];
        if (is_string($accessRules)) { $decoded = json_decode($accessRules, true); $accessRules = is_array($decoded) ? $decoded : []; }
        $accessRules = array_values(array_filter(array_map('trim', array_map('strval', (array) $accessRules))));

        $branding = $input['branding'] ?? [];
        if (is_string($branding)) { $decoded = json_decode($branding, true); $branding = is_array($decoded) ? $decoded : []; }

        $internalCode = trim((string) ($input['internal_code'] ?? ''));
        if ($internalCode === '') {
            $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $category));
            $internalCode = substr($prefix ?: 'TKT', 0, 6) . '-' . date('Y') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
        }

        if ($errors) throw UthengaTieErrors::validation($errors);

        return [
            'name' => $name, 'description' => trim((string) ($input['description'] ?? '')),
            'category' => $category, 'internal_code' => substr($internalCode, 0, 40),
            'price' => $price, 'fee_percent' => $feePercent,
            'total_quantity' => $totalQty, 'max_per_customer' => $maxPerCustomer, 'min_qty' => $minQty,
            'sale_start' => $saleStart, 'sale_end' => $saleEnd,
            'transferable' => $transferable ? 1 : 0, 'refundable' => $refundable ? 1 : 0,
            'tier' => $tier, 'access_rules' => $accessRules, 'branding' => $branding,
        ];
    }

    public function createType(string $listingId, string $vendorId, array $user, array $input): array
    {
        $listing = $this->listingRow($listingId, $vendorId);
        $d = $this->normalizeTypeInput($input);
        $publish = !empty($input['publish']);
        $status = $publish ? 'ACTIVE' : 'DRAFT';
        $isActive = $publish ? 1 : 0;

        $this->db->prepare(
            'INSERT INTO ticket_types (listing_id, name, description, category, internal_code, price, fee_percent, total_quantity, remaining_quantity, max_per_customer, min_qty, sale_start, sale_end, is_active, ticket_status, transferable, refundable, tier, access_scope, access_rules, branding, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)'
        )->execute([
            $listingId, $d['name'], $d['description'], $d['category'], $d['internal_code'], $d['price'], $d['fee_percent'],
            $d['total_quantity'], $d['total_quantity'], $d['max_per_customer'], $d['min_qty'],
            $d['sale_start'], $d['sale_end'], $isActive, $status, $d['transferable'], $d['refundable'], $d['tier'],
            $input['access_scope'] ?? null, json_encode($d['access_rules']), json_encode($d['branding']),
        ]);
        $typeId = (int) $this->db->lastInsertId();

        $this->audit($listingId, $user['id'], 'ticket_type.created', ['ticket_type_id' => $typeId, 'name' => $d['name'], 'price' => $d['price'], 'status' => $status], $typeId);

        if ($listing['event_status'] !== 'PUBLISHED') {
            // still fine — configurations work pre-publish
        }
        return $this->typesList($listingId);
    }

    public function updateType(string $listingId, string $vendorId, array $user, array $input): array
    {
        $this->listingRow($listingId, $vendorId);
        $typeId = (int) ($input['ticket_type_id'] ?? 0);
        $current = $this->typeRow($listingId, $typeId);
        if ($current['ticket_status'] === 'ARCHIVED') throw UthengaTieErrors::validation(['ticket_type_id' => 'Archived ticket types cannot be edited.']);

        $d = $this->normalizeTypeInput($input);
        $sold = (int) $current['total_quantity'] - (int) $current['remaining_quantity'];
        if ($d['total_quantity'] < $sold) throw UthengaTieErrors::validation(['total_quantity' => "Capacity cannot be below $sold already-sold tickets."]);
        $remaining = $d['total_quantity'] - $sold;

        $status = $current['ticket_status'];
        if ($status === 'DRAFT' && !empty($input['publish'])) $status = 'ACTIVE';
        if ($status === 'SOLD_OUT' && $remaining > 0) $status = 'ACTIVE';

        $this->db->prepare(
            'UPDATE ticket_types SET name=?, description=?, category=?, internal_code=?, price=?, fee_percent=?, total_quantity=?, remaining_quantity=?, max_per_customer=?, min_qty=?, sale_start=?, sale_end=?, is_active=?, ticket_status=?, transferable=?, refundable=?, tier=?, access_scope=?, access_rules=?, branding=? WHERE id=?'
        )->execute([
            $d['name'], $d['description'], $d['category'], $d['internal_code'], $d['price'], $d['fee_percent'],
            $d['total_quantity'], $remaining, $d['max_per_customer'], $d['min_qty'],
            $d['sale_start'], $d['sale_end'],
            ($status === 'ACTIVE' || $status === 'SCHEDULED' || $status === 'SOLD_OUT') ? 1 : $current['is_active'],
            $status, $d['transferable'], $d['refundable'], $d['tier'], $input['access_scope'] ?? $current['access_scope'],
            json_encode($d['access_rules']), json_encode($d['branding']), $typeId
        ]);

        $this->audit($listingId, $user['id'], 'ticket_type.updated', ['ticket_type_id' => $typeId, 'name' => $d['name'], 'price' => $d['price'], 'capacity' => $d['total_quantity']], $typeId);
        return $this->typesList($listingId);
    }

    private function typeRow(string $listingId, int $typeId): array
    {
        $st = $this->db->prepare('SELECT * FROM ticket_types WHERE id=? AND listing_id=? LIMIT 1');
        $st->execute([$typeId, $listingId]);
        $row = $st->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['ticket_type_id' => 'Ticket type not found.']);
        return $row;
    }

    public function setStatus(string $listingId, string $vendorId, array $user, array $input): array
    {
        $this->listingRow($listingId, $vendorId);
        $typeId = (int) ($input['ticket_type_id'] ?? 0);
        $row = $this->typeRow($listingId, $typeId);

        $action = strtolower((string) ($input['status_action'] ?? ($input['action'] ?? '')));
        $map = [
            'scheduled' => ['SCHEDULED', 1],
            'activate' => ['ACTIVE', 1],
            'resume' => ['ACTIVE', 1],
            'pause' => ['PAUSED', 0],
            'close' => ['CLOSED', 0],
            'archive' => ['ARCHIVED', 0],
        ];
        if (!isset($map[$action])) throw UthengaTieErrors::validation(['action' => 'Use scheduled, activate, resume, pause, close or archive.']);
        [$status, $isActive] = $map[$action];

        if ($action === 'archive' && $row['remaining_quantity'] < $row['total_quantity'] && $row['ticket_status'] !== 'CLOSED' && $row['ticket_status'] !== 'ARCHIVED') {
            throw UthengaTieErrors::validation(['action' => 'Close this ticket type before archiving it.']);
        }

        $this->db->prepare('UPDATE ticket_types SET ticket_status=?, is_active=? WHERE id=?')->execute([$status, $isActive, $typeId]);
        $this->audit($listingId, $user['id'], 'ticket_type.' . strtolower($status), ['ticket_type_id' => $typeId, 'name' => $row['name']], $typeId);
        return $this->typesList($listingId);
    }

    public function adjustInventory(string $listingId, string $vendorId, array $user, array $input): array
    {
        $this->listingRow($listingId, $vendorId);
        $typeId = (int) ($input['ticket_type_id'] ?? 0);
        $row = $this->typeRow($listingId, $typeId);

        $delta = (int) ($input['delta'] ?? 0);
        $set = isset($input['set']) ? (int) $input['set'] : null;
        if ($delta === 0 && $set === null) throw UthengaTieErrors::validation(['delta' => 'Provide a delta (e.g. -10) or an absolute set value.']);

        $remaining = $set !== null ? min(max($set, 0), (int) $row['total_quantity']) : (int) $row['remaining_quantity'] + $delta;
        $remaining = min(max($remaining, 0), (int) $row['total_quantity']);

        $sold = (int) $row['total_quantity'] - $remaining;
        $status = $row['ticket_status'];
        if ($remaining === 0 && $sold > 0 && $status === 'ACTIVE') $status = 'SOLD_OUT';
        if ($remaining > 0 && $status === 'SOLD_OUT') $status = 'ACTIVE';

        $this->db->prepare('UPDATE ticket_types SET remaining_quantity=?, ticket_status=? WHERE id=?')->execute([$remaining, $status, $typeId]);
        $this->audit($listingId, $user['id'], 'ticket_type.inventory_adjusted', [
            'ticket_type_id' => $typeId, 'name' => $row['name'], 'delta' => $delta, 'set' => $set, 'remaining' => $remaining, 'reason' => $input['reason'] ?? null,
        ], $typeId);
        return $this->typesList($listingId);
    }

    public function duplicateType(string $listingId, string $vendorId, array $user, array $input): array
    {
        $this->listingRow($listingId, $vendorId);
        $typeId = (int) ($input['ticket_type_id'] ?? 0);
        $row = $this->typeRow($listingId, $typeId);

        $newName = (string) ($input['name'] ?? ($row['name'] . ' (Copy)'));
        $this->db->prepare(
            'INSERT INTO ticket_types (listing_id, name, description, category, internal_code, price, fee_percent, total_quantity, remaining_quantity, max_per_customer, min_qty, sale_start, sale_end, is_active, ticket_status, transferable, refundable, tier, access_scope, access_rules, branding, sort_order)
             SELECT listing_id, ?, description, category, ?, price, fee_percent, total_quantity, total_quantity, max_per_customer, min_qty, sale_start, sale_end, 0, \'DRAFT\', transferable, refundable, tier, access_scope, access_rules, branding, 0
             FROM ticket_types WHERE id=?'
        )->execute([
            substr($newName, 0, 80),
            substr('DUP-' . date('Y') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT), 0, 40),
            $typeId,
        ]);
        $newId = (int) $this->db->lastInsertId();
        $this->audit($listingId, $user['id'], 'ticket_type.duplicated', ['ticket_type_id' => $newId, 'source_type_id' => $typeId, 'name' => $newName], $newId);
        return $this->typesList($listingId);
    }

    // ------------------------------------------------------------------
    // Orders
    // ------------------------------------------------------------------

    public function ordersList(string $listingId, string $filter = 'all', string $search = '', int $limit = 0): array
    {
        $where = ['b.listing_id=?', 'b.deleted_at IS NULL'];
        $params = [$listingId];

        if ($filter === 'completed') { $where[] = "b.payment_status='Paid'"; }
        elseif ($filter === 'pending') { $where[] = "b.payment_status NOT IN ('Paid','Refunded','Cancelled','Failed')"; }
        elseif ($filter === 'refunded') { $where[] = "b.payment_status='Refunded'"; }
        elseif ($filter === 'failed') { $where[] = "b.payment_status='Failed'"; }

        if ($search !== '') {
            $where[] = "(b.id LIKE ? OR b.customer_name LIKE ? OR b.customer_email LIKE ? OR b.reference_name LIKE ? OR b.transaction_id LIKE ?)";
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }

        $limitClause = $limit > 0 ? " LIMIT {$limit}" : '';
        $sql = "SELECT b.*, tt.name AS ticket_type_name, tt.internal_code,
                       (SELECT COUNT(*) FROM event_tickets et WHERE et.booking_id=b.id) AS issued_count
                FROM bookings b
                LEFT JOIN ticket_types tt ON tt.id = b.ticket_type_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY b.booked_at DESC{$limitClause}";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map([$this, 'orderView'], $st->fetchAll());
    }

    private function orderView(array $b): array
    {
        $payment = strtolower((string) $b['payment_status']);
        if ($payment === 'refunded') $status = 'Refunded';
        elseif ($payment === 'failed') $status = 'Failed';
        elseif ($payment === 'paid') $status = 'Completed';
        elseif (in_array($payment, ['pending', 'payment pending', 'processing'], true)) $status = 'Payment Pending';
        else $status = ucfirst($b['payment_status'] ?: 'Pending');

        $details = json_decode((string) ($b['details'] ?? '{}'), true) ?: [];
        $issued = (int) ($b['issued_count'] ?? 0);
        return [
            'id' => $b['id'],
            'booking_code' => $b['booking_code'] ?? $b['id'],
            'transaction_id' => $b['transaction_id'],
            'customer_name' => $b['customer_name'],
            'customer_email' => $b['customer_email'],
            'quantity' => (int) $b['quantity'],
            'ticket_type_name' => $b['ticket_type_name'] ?? 'Ticket',
            'internal_code' => $b['internal_code'],
            'amount' => (float) $b['total_price'],
            'gateway' => $b['payment_gateway'] ?: 'Manual / Offline',
            'payment_status' => $b['payment_status'],
            'status' => $status,
            'issued' => $issued,
            'issued_full' => (int) $b['quantity'] > 0 && $issued >= (int) $b['quantity'],
            'booked_at' => $b['booked_at'],
            'check_in_date' => $details['check_in_date'] ?? null,
        ];
    }

    public function orderDetail(string $listingId, string $vendorId, string $bookingId): array
    {
        $this->listingRow($listingId, $vendorId);
        $st = $this->db->prepare(
            "SELECT b.*, tt.name AS ticket_type_name, tt.internal_code
             FROM bookings b LEFT JOIN ticket_types tt ON tt.id=b.ticket_type_id
             WHERE b.id=? AND b.listing_id=? LIMIT 1"
        );
        $st->execute([$bookingId, $listingId]);
        $row = $st->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['booking_id' => 'Order not found.']);

        $t = $this->db->prepare('SELECT * FROM event_tickets WHERE booking_id=? ORDER BY created_at ASC');
        $t->execute([$bookingId]);
        $tickets = array_map([$this, 'ticketView'], $t->fetchAll());

        $audit = $this->db->prepare("SELECT * FROM event_ticket_audit WHERE booking_id=? OR listing_id=? ORDER BY created_at DESC LIMIT 12");
        $audit->execute([$bookingId, $listingId]);

        return ['order' => $this->orderView($row), 'tickets' => $tickets, 'activity' => $audit->fetchAll()];
    }

    // ------------------------------------------------------------------
    // Issued tickets
    // ------------------------------------------------------------------

    private function ticketView(array $t): array
    {
        return [
            'id' => $t['id'],
            'ticket_type_id' => (int) $t['ticket_type_id'],
            'ticket_type_name' => $this->typeName((int) $t['ticket_type_id']),
            'booking_id' => $t['booking_id'],
            'holder_name' => $t['holder_name'],
            'holder_email' => $t['holder_email'],
            'holder_phone' => $t['holder_phone'],
            'qr_token' => $t['qr_token'],
            'verification_signature' => $t['verification_signature'],
            'status' => strtoupper((string) $t['status']),
            'checked_in_at' => $t['checked_in_at'],
            'checked_in_by' => $t['checked_in_by'],
            'last_sent_at' => $t['last_sent_at'],
            'created_at' => $t['created_at'],
        ];
    }

    public function issuedList(string $listingId, string $status = 'all', string $search = ''): array
    {
        $where = ['listing_id=?'];
        $params = [$listingId];
        if ($status === 'checked_in') $where[] = 'checked_in_at IS NOT NULL';
        elseif ($status === 'not_checked_in') $where[] = 'checked_in_at IS NULL';
        elseif ($status !== 'all') $where[] = 'status=?';
        if ($search !== '') {
            $where[] = '(id LIKE ? OR holder_name LIKE ? OR holder_email LIKE ? OR holder_phone LIKE ? OR booking_id LIKE ? OR qr_token LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        }
        $st = $this->db->prepare('SELECT * FROM event_tickets WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC');
        $st->execute($params);
        return array_map([$this, 'ticketView'], $st->fetchAll());
    }

    public function ticketDetail(string $listingId, string $vendorId, string $ticketId): array
    {
        $this->listingRow($listingId, $vendorId);
        $st = $this->db->prepare('SELECT * FROM event_tickets WHERE id=? AND listing_id=? LIMIT 1');
        $st->execute([$ticketId, $listingId]);
        $row = $st->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['ticket_id' => 'Ticket not found.']);

        $audit = $this->db->prepare("SELECT * FROM event_ticket_audit WHERE ticket_id=? OR (booking_id=? AND ticket_id IS NULL) ORDER BY created_at DESC LIMIT 20");
        $audit->execute([$ticketId, $row['booking_id']]);
        $transfers = $this->db->prepare('SELECT * FROM event_ticket_transfers WHERE ticket_id=? ORDER BY created_at DESC');
        $transfers->execute([$ticketId]);

        return [
            'ticket' => $this->ticketView($row),
            'activity' => $audit->fetchAll(),
            'transfers' => array_map(function (array $t): array {
                return ['id' => $t['id'], 'from_holder_name' => $t['from_holder_name'], 'to_holder_name' => $t['to_holder_name'], 'to_phone' => $t['to_phone'], 'to_email' => $t['to_email'], 'initiated_by' => $t['initiated_by'], 'initiated_by_type' => $t['initiated_by_type'], 'reason' => $t['reason'], 'status' => $t['status'], 'created_at' => $t['created_at'], 'completed_at' => $t['completed_at']];
            }, $transfers->fetchAll()),
        ];
    }

    public function issueForBooking(string $listingId, string $vendorId, array $user, string $bookingId): array
    {
        $this->listingRow($listingId, $vendorId);
        $st = $this->db->prepare('SELECT * FROM bookings WHERE id=? AND listing_id=? LIMIT 1');
        $st->execute([$bookingId, $listingId]);
        $booking = $st->fetch();
        if (!is_array($booking)) throw UthengaTieErrors::validation(['booking_id' => 'Order not found.']);
        if ($booking['payment_status'] !== 'Paid') throw UthengaTieErrors::validation(['payment' => 'Tickets can only be issued after payment is confirmed.']);
        if ((int) $booking['quantity'] <= 0) throw UthengaTieErrors::validation(['quantity' => 'Order has no ticket quantity.']);

        $issued = $this->issueTickets($booking);
        $this->audit($listingId, $user['id'], 'tickets.issued_manually', ['booking_id' => $bookingId, 'count' => $issued], (int) $booking['ticket_type_id'], null, $bookingId);
        return ['issued' => $issued];
    }

    /** Entry point used by the verified-payment commit flow to issue tickets automatically. */
    public static function issueOnCommit(PDO $db, string $bookingId): int
    {
        $st = $db->prepare('SELECT * FROM bookings WHERE id=? LIMIT 1');
        $st->execute([$bookingId]);
        $booking = $st->fetch();
        if (!is_array($booking) || $booking['listing_type'] !== 'event') return 0;
        if ($booking['payment_status'] !== 'Paid' || (int) $booking['quantity'] <= 0) return 0;
        return (new self($db))->issueTickets($booking);
    }

    public function issueTickets(array $booking): int
    {
        $existing = $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE booking_id=?', [$booking['id']]);
        $want = max((int) $booking['quantity'] - $existing, 0);
        if ($want <= 0) return 0;

        $type = null;
        if (!empty($booking['ticket_type_id'])) {
            $st = $this->db->prepare('SELECT * FROM ticket_types WHERE id=? LIMIT 1');
            $st->execute([(int) $booking['ticket_type_id']]);
            $type = $st->fetch() ?: null;
        }

        $listing = $this->db->prepare('SELECT title FROM listings WHERE id=? LIMIT 1');
        $listing->execute([$booking['listing_id']]);
        $listingTitle = (string) ($listing->fetchColumn() ?: '');

        $evtCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $listingTitle) ?: 'EVT', 0, 3));
        $digest = strtoupper(substr(hash('crc32b', $booking['listing_id']), 0, 4));
        $typeCode = $type ? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $type['name']) ?: 'TKT', 0, 3)) : 'TKT';
        $seqBase = $this->countVal('SELECT COUNT(*) FROM event_tickets WHERE ticket_type_id=?', [(int) ($type['id'] ?? 0)]) + 1;
        $details = json_decode((string) ($booking['details'] ?? '[]'), true) ?: json_decode((string) ($booking['details'] ?? '{}'), true) ?: [];

        $insert = $this->db->prepare(
            'INSERT INTO event_tickets (id, listing_id, ticket_type_id, booking_id, holder_name, holder_email, holder_phone, qr_token, verification_signature, status)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $issued = 0;
        for ($i = 0; $i < $want; $i++) {
            $seq = str_pad((string) ($seqBase + $i), 6, '0', STR_PAD_LEFT);
            $ticketId = 'UTH-' . $evtCode . '-' . $typeCode . '-' . $digest . '-' . $seq;
            $token = bin2hex(random_bytes(24));
            $signature = hash_hmac('sha256', $ticketId . '.' . $token, 'uthenga-tie-ticket-v1');
            $insert->execute([
                $ticketId, $booking['listing_id'], $booking['ticket_type_id'] ?? 0, $booking['id'],
                $booking['customer_name'] ?? 'Ticket Holder',
                $booking['customer_email'] ?? null,
                $details['phone'] ?? null,
                $token, $signature, 'ISSUED',
            ]);
            $this->audit($booking['listing_id'], '', 'ticket.issued', ['ticket_id' => $ticketId, 'booking_id' => $booking['id']], (int) ($booking['ticket_type_id'] ?? 0), $ticketId, $booking['id']);
            $issued++;
        }
        return $issued;
    }

    public function resendTicket(string $listingId, string $vendorId, array $user, string $ticketId): array
    {
        $t = $this->ticketRow($listingId, $ticketId);
        $this->db->prepare('UPDATE event_tickets SET last_sent_at=NOW() WHERE id=?')->execute([$ticketId]);
        $this->audit($listingId, $user['id'], 'ticket.resent', ['ticket_id' => $ticketId, 'holder_name' => $t['holder_name']], (int) $t['ticket_type_id'], $ticketId, $t['booking_id']);
        return $this->ticketDetail($listingId, $vendorId, $ticketId);
    }

    public function cancelTicket(string $listingId, string $vendorId, array $user, array $input): array
    {
        $ticketId = (string) ($input['ticket_id'] ?? '');
        $t = $this->ticketRow($listingId, $ticketId);
        if (in_array($t['status'], ['CANCELLED', 'REFUNDED'], true)) throw UthengaTieErrors::validation(['ticket_id' => 'This ticket is already closed.']);
        if ($t['checked_in_at'] !== null) throw UthengaTieErrors::validation(['ticket_id' => 'Checked-in tickets cannot be cancelled.']);

        $this->db->prepare("UPDATE event_tickets SET status='CANCELLED' WHERE id=?" )->execute([$ticketId]);
        if (!empty($t['ticket_type_id'])) {
            $this->db->prepare('UPDATE ticket_types SET remaining_quantity = LEAST(remaining_quantity + 1, total_quantity) WHERE id=?')->execute([(int) $t['ticket_type_id']]);
        }
        $this->audit($listingId, $user['id'], 'ticket.cancelled', ['ticket_id' => $ticketId, 'reason' => $input['reason'] ?? null], (int) $t['ticket_type_id'], $ticketId, $t['booking_id']);
        return $this->ticketDetail($listingId, $vendorId, $ticketId);
    }

    private function ticketRow(string $listingId, string $ticketId): array
    {
        $st = $this->db->prepare('SELECT * FROM event_tickets WHERE id=? AND listing_id=? LIMIT 1');
        $st->execute([$ticketId, $listingId]);
        $row = $st->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['ticket_id' => 'Ticket not found.']);
        return $row;
    }

    // ------------------------------------------------------------------
    // Transfers
    // ------------------------------------------------------------------

    public function transfersList(string $listingId): array
    {
        $st = $this->db->prepare(
            "SELECT tr.*, tt.name AS ticket_type_name
             FROM event_ticket_transfers tr
             LEFT JOIN event_tickets et ON et.id = tr.ticket_id
             LEFT JOIN ticket_types tt ON tt.id = et.ticket_type_id
             WHERE tr.listing_id=? ORDER BY tr.created_at DESC"
        );
        $st->execute([$listingId]);
        $rows = [];
        foreach ($st->fetchAll() as $r) {
            $rows[] = [
                'id' => $r['id'], 'ticket_id' => $r['ticket_id'], 'ticket_type_name' => $r['ticket_type_name'],
                'from_holder_name' => $r['from_holder_name'], 'to_holder_name' => $r['to_holder_name'],
                'to_phone' => $r['to_phone'], 'to_email' => $r['to_email'],
                'initiated_by' => $r['initiated_by'], 'initiated_by_type' => $r['initiated_by_type'],
                'reason' => $r['reason'], 'status' => $r['status'],
                'created_at' => $r['created_at'], 'completed_at' => $r['completed_at'],
            ];
        }
        return $rows;
    }

    public function createTransfer(string $listingId, string $vendorId, array $user, array $input): array
    {
        $ticketId = (string) ($input['ticket_id'] ?? '');
        $t = $this->ticketRow($listingId, $ticketId);
        if (in_array($t['status'], ['CANCELLED', 'REFUNDED'], true)) throw UthengaTieErrors::validation(['ticket_id' => 'Closed tickets cannot be transferred.']);
        if ($t['checked_in_at'] !== null) throw UthengaTieErrors::validation(['ticket_id' => 'Checked-in tickets cannot be transferred.']);

        $toName = trim((string) ($input['to_name'] ?? ''));
        if ($toName === '') throw UthengaTieErrors::validation(['to_name' => 'New holder name is required.']);
        if ($toName === $t['holder_name']) throw UthengaTieErrors::validation(['to_name' => 'The ticket already belongs to this holder.']);

        $id = 'TRF-' . strtoupper(bin2hex(random_bytes(5)));
        $this->db->prepare(
            'INSERT INTO event_ticket_transfers (id, listing_id, ticket_id, from_holder_name, to_holder_name, to_phone, to_email, initiated_by, initiated_by_type, reason, status, completed_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())'
        )->execute([
            $id, $listingId, $ticketId, $t['holder_name'], $toName,
            trim((string) ($input['to_phone'] ?? '')) ?: null, trim((string) ($input['to_email'] ?? '')) ?: null,
            $user['id'], 'ORGANIZER', trim((string) ($input['reason'] ?? '')) ?: null, 'COMPLETED',
        ]);

        $this->db->prepare('UPDATE event_tickets SET holder_name=?, holder_phone=?, holder_email=? WHERE id=?')
            ->execute([$toName, $input['to_phone'] ?? $t['holder_phone'], $input['to_email'] ?? $t['holder_email'], $ticketId]);

        $this->audit($listingId, $user['id'], 'ticket.transferred', [
            'ticket_id' => $ticketId, 'transfer_id' => $id, 'from' => $t['holder_name'], 'to' => $toName, 'reason' => $input['reason'] ?? null,
        ], (int) $t['ticket_type_id'], $ticketId, $t['booking_id']);
        return $this->transfersList($listingId);
    }

    // ------------------------------------------------------------------
    // Refunds
    // ------------------------------------------------------------------

    public function refundsList(string $listingId): array
    {
        $st = $this->db->prepare(
            "SELECT r.*, et.ticket_type_id, tt.name AS ticket_type_name, b.customer_name
             FROM event_ticket_refunds r
             LEFT JOIN event_tickets et ON et.id = r.ticket_id
             LEFT JOIN ticket_types tt ON tt.id = et.ticket_type_id
             LEFT JOIN bookings b ON b.id = r.booking_id
             WHERE r.listing_id=? ORDER BY r.requested_at DESC"
        );
        $st->execute([$listingId]);
        $rows = [];
        foreach ($st->fetchAll() as $r) {
            $rows[] = [
                'id' => $r['id'], 'booking_id' => $r['booking_id'], 'ticket_id' => $r['ticket_id'],
                'ticket_type_name' => $r['ticket_type_name'], 'customer_name' => $r['customer_name'],
                'amount' => (float) $r['amount'], 'currency' => $r['currency'], 'reason' => $r['reason'],
                'status' => strtoupper((string) $r['status']),
                'requested_by' => $r['requested_by'], 'requested_at' => $r['requested_at'],
                'decided_at' => $r['decided_at'], 'decided_by' => $r['decided_by'],
            ];
        }
        return $rows;
    }

    public function createRefund(string $listingId, string $vendorId, array $user, array $input): array
    {
        $ticketId = trim((string) ($input['ticket_id'] ?? ''));
        $bookingId = trim((string) ($input['booking_id'] ?? ''));
        if ($ticketId !== '') $this->ticketRow($listingId, $ticketId);
        if ($bookingId !== '') {
            $st = $this->db->prepare('SELECT payment_status FROM bookings WHERE id=? AND listing_id=? LIMIT 1');
            $st->execute([$bookingId, $listingId]);
            $status = $st->fetchColumn();
            if ($status === false) throw UthengaTieErrors::validation(['booking_id' => 'Order not found.']);
            if ($status === 'Refunded') throw UthengaTieErrors::validation(['booking_id' => 'This order is already refunded.']);
        }

        $amount = $this->money($input['amount'] ?? 0, 'amount');
        if ($amount <= 0) throw UthengaTieErrors::validation(['amount' => 'Refund amount must be greater than zero.']);
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') throw UthengaTieErrors::validation(['reason' => 'A refund reason is required.']);

        $id = 'RFD-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $intentRef = null;
        if ($bookingId !== '') {
            $intentRow = $this->db->prepare('SELECT intent_ref FROM uthenga_payment_intents WHERE booking_id = ? ORDER BY created_at DESC LIMIT 1');
            $intentRow->execute([$bookingId]);
            $intentRef = $intentRow->fetchColumn() ?: null;
        }
        $this->db->prepare(
            'INSERT INTO event_ticket_refunds (id, listing_id, booking_id, intent_ref, ticket_id, amount, currency, reason, status, requested_by) VALUES (?,?,?,?,?,?,?,?,?,?)'
        )->execute([$id, $listingId, $bookingId ?: null, $intentRef, $ticketId ?: null, $amount, $input['currency'] ?? 'MWK', $reason, 'PENDING', $user['id']]);
        $this->audit($listingId, $user['id'], 'refund.requested', ['refund_id' => $id, 'ticket_id' => $ticketId, 'booking_id' => $bookingId, 'amount' => $amount, 'reason' => $reason], null, $ticketId ?: null, $bookingId ?: null);
        return $this->refundsList($listingId);
    }

    public function decideRefund(string $listingId, string $vendorId, array $user, array $input): array
    {
        $refundId = (string) ($input['refund_id'] ?? '');
        $st = $this->db->prepare("SELECT * FROM event_ticket_refunds WHERE id=? AND listing_id=? LIMIT 1");
        $st->execute([$refundId, $listingId]);
        $row = $st->fetch();
        if (!is_array($row)) throw UthengaTieErrors::validation(['refund_id' => 'Refund request not found.']);
        if ($row['status'] !== 'PENDING') throw UthengaTieErrors::validation(['refund_id' => 'This refund request was already decided.']);

        $approve = !empty($input['approve']);

        if ($approve) {
            if (empty($row['intent_ref'])) {
                throw UthengaTieErrors::validation(['refund_id' => 'No payment-engine record found for this booking — this refund must be settled manually.']);
            }
            $result = UthengaPaymentEngine::refundIntent((string) $row['intent_ref'], (float) $row['amount'], (string) $row['reason'], $user['id'], 'event', $refundId);
            if (!$result['success']) {
                throw UthengaTieErrors::validation(['refund_id' => $result['error'] ?? 'Refund could not be processed.']);
            }
        }

        $status = $approve ? 'PROCESSED' : 'REJECTED';
        $this->db->prepare("UPDATE event_ticket_refunds SET status=?, decided_at=NOW(), decided_by=? WHERE id=?")->execute([$status, $user['id'], $refundId]);

        if ($approve) {
            if ($row['booking_id']) $this->db->prepare("UPDATE bookings SET payment_status='Refunded' WHERE id=?")->execute([$row['booking_id']]);
            if ($row['ticket_id']) {
                $tt = $this->ticketRow($listingId, $row['ticket_id']);
                if ($tt['checked_in_at'] === null) {
                    $this->db->prepare("UPDATE event_tickets SET status='REFUNDED' WHERE id=?")->execute([$row['ticket_id']]);
                    if (!empty($tt['ticket_type_id'])) {
                        $this->db->prepare('UPDATE ticket_types SET remaining_quantity = LEAST(remaining_quantity + 1, total_quantity) WHERE id=?')->execute([(int) $tt['ticket_type_id']]);
                    }
                }
            }
        }
        $this->audit($listingId, $user['id'], 'refund.' . ($approve ? 'processed' : 'rejected'), ['refund_id' => $refundId, 'amount' => (float) $row['amount']], null, $row['ticket_id'] ?: null, $row['booking_id'] ?: null);
        return $this->refundsList($listingId);
    }

    // ------------------------------------------------------------------
    // Export payloads
    // ------------------------------------------------------------------

    public function export(string $listingId, string $vendorId, string $type): array
    {
        $this->listingRow($listingId, $vendorId);
        return match ($type) {
            'inventory' => ['filename' => 'ticket-inventory-' . $listingId, 'rows' => $this->typesList($listingId)],
            'orders' => ['filename' => 'ticket-orders-' . $listingId, 'rows' => $this->ordersList($listingId, 'all', '', 0)],
            'issued' => ['filename' => 'issued-tickets-' . $listingId, 'rows' => $this->issuedList($listingId, 'all', '')],
            'transfers' => ['filename' => 'ticket-transfers-' . $listingId, 'rows' => $this->transfersList($listingId)],
            'refunds' => ['filename' => 'ticket-refunds-' . $listingId, 'rows' => $this->refundsList($listingId)],
            default => throw UthengaTieErrors::validation(['type' => 'Unknown export type.']),
        };
    }

    // ------------------------------------------------------------------
    // Reliability: reconcile sold counts from confirmed bookings
    // ------------------------------------------------------------------

    public function reconcileInventory(string $listingId, string $vendorId, array $user): array
    {
        $this->listingRow($listingId, $vendorId);
        $types = $this->typesList($listingId);
        foreach ($types as $t) {
            $st = $this->db->prepare(
                "SELECT COALESCE(SUM(CASE WHEN payment_status='Refunded' THEN 0 ELSE quantity END),0) AS sold FROM bookings WHERE listing_id=? AND ticket_type_id=? AND deleted_at IS NULL"
            );
            $st->execute([$listingId, $t['id']]);
            $sold = (int) ($st->fetchColumn() ?: 0);
            $remaining = max((int) $t['total_quantity'] - $sold, 0);
            $status = $t['ticket_status'];
            if ($remaining === 0 && $t['total_quantity'] > 0 && $sold > 0 && in_array($status, ['ACTIVE', 'SCHEDULED'], true)) $status = 'SOLD_OUT';
            $this->db->prepare('UPDATE ticket_types SET remaining_quantity=?, ticket_status=? WHERE id=?')->execute([$remaining, $status, $t['id']]);
        }
        return $this->typesList($listingId);
    }
}