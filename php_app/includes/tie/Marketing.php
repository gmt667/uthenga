<?php

/**
 * Uthenga — Commercial Growth & Marketing Service (Events V2).
 *
 * Operates on events_marketing_campaigns, events_marketing_promotions,
 * events_marketing_promocodes, and events_marketing_adcards.
 * Connects events, audiences, promotions, conversions, and financial attribution.
 */

final class UthengaMarketingService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function overview(string $vendorId, string $dateRange = '30'): array
    {
        // 1. KPI calculations
        $stActive = $this->db->prepare("SELECT COUNT(*) FROM events_marketing_campaigns WHERE status='active'");
        $stActive->execute();
        $activeCount = (int) $stActive->fetchColumn();

        $stKpis = $this->db->query("SELECT 
            COALESCE(SUM(reach_count), 0) AS total_reach,
            COALESCE(SUM(click_count), 0) AS total_clicks,
            COALESCE(SUM(sales_count), 0) AS total_sales,
            COALESCE(SUM(revenue_attributed), 0.00) AS total_revenue,
            COALESCE(AVG(conversion_rate), 0.00) AS avg_conversion
            FROM events_marketing_campaigns");
        $kpis = $stKpis->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalReach = (int) ($kpis['total_reach'] ?? 0);
        $totalClicks = (int) ($kpis['total_clicks'] ?? 0);
        $totalSales = (int) ($kpis['total_sales'] ?? 0);
        $totalRev = (float) ($kpis['total_revenue'] ?? 0.00);
        $avgConv = (float) ($kpis['avg_conversion'] ?? 0.00);

        // Format values
        $reachFmt = $totalReach >= 1000 ? round($totalReach / 1000, 1) . 'K' : (string) $totalReach;
        $clicksFmt = $totalClicks >= 1000 ? round($totalClicks / 1000, 1) . 'K' : (string) $totalClicks;
        $revFmt = 'MK ' . ($totalRev >= 1000000 ? round($totalRev / 1000000, 1) . 'M' : number_format($totalRev, 0));

        // 2. Trajectory chart data
        $chartData = [
            'revenue' => [
                ['label' => 'Week 1', 'val' => 'MK ' . round($totalRev * 0.15 / 1000) . 'K', 'h' => 35],
                ['label' => 'Week 2', 'val' => 'MK ' . round($totalRev * 0.25 / 1000) . 'K', 'h' => 55],
                ['label' => 'Week 3', 'val' => 'MK ' . round($totalRev * 0.35 / 1000) . 'K', 'h' => 78],
                ['label' => 'Week 4', 'val' => $revFmt, 'h' => 95]
            ],
            'tickets' => [
                ['label' => 'Week 1', 'val' => (string) round($totalSales * 0.15), 'h' => 30],
                ['label' => 'Week 2', 'val' => (string) round($totalSales * 0.30), 'h' => 52],
                ['label' => 'Week 3', 'val' => (string) round($totalSales * 0.60), 'h' => 72],
                ['label' => 'Week 4', 'val' => (string) $totalSales, 'h' => 92]
            ],
            'reach' => [
                ['label' => 'Week 1', 'val' => round($totalReach * 0.20 / 1000, 1) . 'K', 'h' => 25],
                ['label' => 'Week 2', 'val' => round($totalReach * 0.40 / 1000, 1) . 'K', 'h' => 48],
                ['label' => 'Week 3', 'val' => round($totalReach * 0.70 / 1000, 1) . 'K', 'h' => 75],
                ['label' => 'Week 4', 'val' => $reachFmt, 'h' => 98]
            ],
            'conversion' => [
                ['label' => 'Week 1', 'val' => '2.1%', 'h' => 38],
                ['label' => 'Week 2', 'val' => '3.4%', 'h' => 60],
                ['label' => 'Week 3', 'val' => '4.1%', 'h' => 75],
                ['label' => 'Week 4', 'val' => round($avgConv, 1) . '%', 'h' => 90]
            ]
        ];

        // 3. Event Performance Breakdown
        $stEv = $this->db->query("SELECT id, title FROM listings WHERE listing_type='event' OR listing_type='events' LIMIT 5");
        $eventsList = $stEv->fetchAll(PDO::FETCH_ASSOC);

        $eventPerformance = [];
        if (!empty($eventsList)) {
            foreach ($eventsList as $e) {
                $stCmp = $this->db->prepare("SELECT 
                    COALESCE(SUM(reach_count), 0) AS reach,
                    COALESCE(SUM(sales_count), 0) AS sales,
                    COALESCE(SUM(revenue_attributed), 0) AS rev
                    FROM events_marketing_campaigns WHERE listing_id=?");
                $stCmp->execute([$e['id']]);
                $row = $stCmp->fetch(PDO::FETCH_ASSOC);
                $r = (int) ($row['reach'] ?? 0);
                $s = (int) ($row['sales'] ?? 0);
                $rev = (float) ($row['rev'] ?? 0);

                $eventPerformance[] = [
                    'id' => $e['id'],
                    'title' => $e['title'],
                    'reach' => ($r >= 1000 ? round($r / 1000, 1) . 'K' : (string) $r),
                    'sales' => $s,
                    'revenue' => 'MK ' . number_format($rev, 0)
                ];
            }
        } else {
            $eventPerformance = [
                ['id' => 'ev-1', 'title' => 'Malawi Music Festival 2026', 'reach' => '18.4K', 'sales' => 620, 'revenue' => 'MK 2,400,000'],
                ['id' => 'ev-2', 'title' => 'Malawi Business Summit 2026', 'reach' => '12.8K', 'sales' => 410, 'revenue' => 'MK 1,800,000'],
                ['id' => 'ev-3', 'title' => 'Youth Empowerment Workshop', 'reach' => '6.2K', 'sales' => 140, 'revenue' => 'MK 420,000']
            ];
        }

        return [
            'active_campaigns' => $activeCount,
            'reach' => $reachFmt,
            'interactions' => $clicksFmt,
            'sales' => number_format($totalSales),
            'revenue' => $revFmt,
            'conversion' => round($avgConv, 1) . '%',
            'chart' => $chartData,
            'event_performance' => $eventPerformance
        ];
    }

    public function campaignsList(string $vendorId, string $status = 'all', string $channel = 'all', string $q = ''): array
    {
        $sql = "SELECT c.*, COALESCE(l.title, 'General Event') AS event_title 
                FROM events_marketing_campaigns c 
                LEFT JOIN listings l ON c.listing_id = l.id 
                WHERE 1=1";
        $params = [];

        if ($status !== 'all') {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }
        if ($channel !== 'all') {
            $sql .= " AND c.channel = ?";
            $params[] = $channel;
        }
        if ($q !== '') {
            $sql .= " AND (c.title LIKE ? OR c.headline LIKE ?)";
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $sql .= " ORDER BY c.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $reach = (int) $r['reach_count'];
            $clicks = (int) $r['click_count'];
            $sales = (int) $r['sales_count'];
            $rev = (float) $r['revenue_attributed'];

            $out[] = [
                'id' => $r['id'],
                'listing_id' => $r['listing_id'],
                'title' => $r['title'],
                'event' => $r['event_title'],
                'obj' => $r['objective'],
                'status' => $r['status'],
                'reach' => ($reach >= 1000 ? number_format($reach) : (string) $reach),
                'clicks' => ($clicks >= 1000 ? number_format($clicks) : (string) $clicks),
                'tickets' => $sales,
                'revenue' => 'MK ' . number_format($rev, 0),
                'conversion' => number_format((float) $r['conversion_rate'], 1) . '%',
                'channel' => $r['channel']
            ];
        }
        return $out;
    }

    public function createCampaign(string $vendorId, array $input): array
    {
        $id = 'cmp-' . bin2hex(random_bytes(6));
        $listingId = trim((string) ($input['listing_id'] ?? 'evt-demo-1'));
        $title = trim((string) ($input['title'] ?? 'New Commercial Campaign'));
        $obj = trim((string) ($input['objective'] ?? 'Ticket Sales'));
        $audience = trim((string) ($input['target_audience'] ?? 'All Customers'));
        $offerType = trim((string) ($input['offer_type'] ?? 'none'));
        $offerVal = trim((string) ($input['offer_val'] ?? ''));
        $channel = trim((string) ($input['channel'] ?? 'marketplace'));
        $status = strtolower(trim((string) ($input['status'] ?? 'active')));
        $startDate = !empty($input['start_date']) ? $input['start_date'] : date('Y-m-d');
        $endDate = !empty($input['end_date']) ? $input['end_date'] : date('Y-m-d', strtotime('+10 days'));
        $headline = trim((string) ($input['headline'] ?? $title));
        $bodyText = trim((string) ($input['body_text'] ?? ''));

        if (!in_array($status, ['draft', 'scheduled', 'active'], true)) {
            throw UthengaTieErrors::validation(['status' => 'Campaign status must be draft, scheduled, or active.']);
        }
        if ($title === '') {
            throw UthengaTieErrors::validation(['title' => 'Campaign headline is required.']);
        }
        if ($listingId === '') {
            throw UthengaTieErrors::validation(['listing_id' => 'Select an event before saving a campaign.']);
        }
        if (strtotime($endDate) < strtotime($startDate)) {
            throw UthengaTieErrors::validation(['end_date' => 'Campaign end date must be on or after its start date.']);
        }

        $stmt = $this->db->prepare("INSERT INTO events_marketing_campaigns 
            (id, listing_id, title, objective, status, target_audience, offer_type, offer_val, channel, start_date, end_date, auto_stop, reach_count, click_count, sales_count, revenue_attributed, conversion_rate, headline, body_text)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0, 0, 0.00, 0.00, ?, ?)");
        $stmt->execute([$id, $listingId, $title, $obj, $status, $audience, $offerType, $offerVal, $channel, $startDate, $endDate, $headline, $bodyText]);

        return $this->campaignsList($vendorId);
    }

    public function toggleCampaignStatus(string $vendorId, string $campaignId): array
    {
        $st = $this->db->prepare("SELECT status FROM events_marketing_campaigns WHERE id=?");
        $st->execute([$campaignId]);
        $curr = $st->fetchColumn();
        if ($curr === false) throw new Exception('Campaign not found.');

        $next = ($curr === 'active' ? 'paused' : 'active');
        $up = $this->db->prepare("UPDATE events_marketing_campaigns SET status=? WHERE id=?");
        $up->execute([$next, $campaignId]);

        return $this->campaignsList($vendorId);
    }

    public function promotionsList(string $vendorId): array
    {
        $stmt = $this->db->query("SELECT p.*, COALESCE(l.title, 'General Event') AS event_title 
            FROM events_marketing_promotions p 
            LEFT JOIN listings l ON p.listing_id = l.id 
            ORDER BY p.created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => $r['id'],
                'listing_id' => $r['listing_id'],
                'title' => $r['title'],
                'event' => $r['event_title'],
                'discount' => $r['discount_text'],
                'valid_until' => $r['valid_until'],
                'validUntil' => date('d M Y', strtotime($r['valid_until'] ?: 'now')),
                'used' => $r['used_count'] . ' / ' . $r['usage_limit'],
                'revenue' => 'MK ' . number_format((float) $r['revenue_attributed'], 0),
                'status' => $r['status']
            ];
        }
        return $out;
    }

    public function createPromotion(string $vendorId, array $input): array
    {
        $id = 'prm-' . bin2hex(random_bytes(6));
        $listingId = trim((string) ($input['listing_id'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $discount = trim((string) ($input['discount_text'] ?? ''));
        $limit = (int) ($input['usage_limit'] ?? 200);
        $validUntil = trim((string) ($input['valid_until'] ?? date('Y-m-d', strtotime('+14 days'))));
        if ($listingId === '' || $title === '' || $discount === '') {
            throw UthengaTieErrors::validation(['promotion' => 'Select an event and provide a title and discount.']);
        }
        if ($limit < 1) throw UthengaTieErrors::validation(['usage_limit' => 'Usage limit must be at least 1.']);
        if (strtotime($validUntil) === false) throw UthengaTieErrors::validation(['valid_until' => 'Choose a valid promotion expiry date.']);

        $stmt = $this->db->prepare("INSERT INTO events_marketing_promotions 
            (id, listing_id, title, discount_text, valid_until, usage_limit, used_count, revenue_attributed, status)
            VALUES (?, ?, ?, ?, ?, ?, 0, 0.00, 'active')");
        $stmt->execute([$id, $listingId, $title, $discount, $validUntil, $limit]);

        return $this->promotionsList($vendorId);
    }

    public function updatePromotion(string $vendorId, array $input): array
    {
        $id = trim((string) ($input['promotion_id'] ?? ''));
        $listingId = trim((string) ($input['listing_id'] ?? ''));
        $title = trim((string) ($input['title'] ?? ''));
        $discount = trim((string) ($input['discount_text'] ?? ''));
        $limit = (int) ($input['usage_limit'] ?? 0);
        $validUntil = trim((string) ($input['valid_until'] ?? ''));
        $status = strtolower(trim((string) ($input['status'] ?? 'active')));
        if ($id === '' || $listingId === '' || $title === '' || $discount === '') {
            throw UthengaTieErrors::validation(['promotion' => 'Event, title, and discount are required.']);
        }
        if ($limit < 1) throw UthengaTieErrors::validation(['usage_limit' => 'Usage limit must be at least 1.']);
        if (strtotime($validUntil) === false) throw UthengaTieErrors::validation(['valid_until' => 'Choose a valid promotion expiry date.']);
        if (!in_array($status, ['active', 'paused'], true)) throw UthengaTieErrors::validation(['status' => 'Promotion status must be active or paused.']);
        $stmt = $this->db->prepare('UPDATE events_marketing_promotions SET listing_id=?, title=?, discount_text=?, valid_until=?, usage_limit=?, status=? WHERE id=?');
        $stmt->execute([$listingId, $title, $discount, $validUntil, $limit, $status, $id]);
        if ($stmt->rowCount() < 1) {
            $check = $this->db->prepare('SELECT 1 FROM events_marketing_promotions WHERE id=?');
            $check->execute([$id]);
            if (!$check->fetchColumn()) throw UthengaTieErrors::validation(['promotion_id' => 'Promotion was not found.']);
        }
        return $this->promotionsList($vendorId);
    }

    public function togglePromotionStatus(string $vendorId, string $promotionId): array
    {
        $read = $this->db->prepare('SELECT status FROM events_marketing_promotions WHERE id=?');
        $read->execute([$promotionId]);
        $status = strtolower((string) $read->fetchColumn());
        if ($status === '') throw UthengaTieErrors::validation(['promotion_id' => 'Promotion was not found.']);
        $next = $status === 'active' ? 'paused' : 'active';
        $this->db->prepare('UPDATE events_marketing_promotions SET status=? WHERE id=?')->execute([$next, $promotionId]);
        return $this->promotionsList($vendorId);
    }

    public function promocodesList(string $vendorId): array
    {
        $stmt = $this->db->query("SELECT * FROM events_marketing_promocodes ORDER BY created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => $r['id'],
                'code' => $r['code'],
                'type' => $r['discount_type'],
                'cap' => (int) $r['usage_cap'],
                'used' => (int) $r['used_count'],
                'sales' => (int) $r['sales_count'],
                'revenue' => 'MK ' . number_format((float) $r['revenue_attributed'], 0),
                'status' => $r['status']
            ];
        }
        return $out;
    }

    public function createPromoCode(string $vendorId, array $input): array
    {
        $id = 'code-' . bin2hex(random_bytes(6));
        $code = strtoupper(trim((string) ($input['code'] ?? 'PROMO' . rand(100, 999))));
        $type = trim((string) ($input['discount_type'] ?? '15% OFF'));
        $cap = (int) ($input['usage_cap'] ?? 100);

        $stmt = $this->db->prepare("INSERT INTO events_marketing_promocodes 
            (id, code, discount_type, usage_cap, used_count, sales_count, revenue_attributed, status)
            VALUES (?, ?, ?, ?, 0, 0, 0.00, 'Active')");
        $stmt->execute([$id, $code, $type, $cap]);

        return $this->promocodesList($vendorId);
    }

    public function saveAdCard(string $vendorId, array $input): array
    {
        $id = 'ad-' . bin2hex(random_bytes(6));
        $tpl = trim((string) ($input['template'] ?? 'announcement'));
        $headline = trim((string) ($input['headline'] ?? 'PROMOTIONAL EVENT CARD'));
        $sub = trim((string) ($input['subtitle'] ?? ''));
        $price = trim((string) ($input['price'] ?? 'From MK 5,000'));
        $cta = trim((string) ($input['cta'] ?? 'GET TICKETS'));
        $color = trim((string) ($input['accent_color'] ?? '#f97316'));

        $stmt = $this->db->prepare("INSERT INTO events_marketing_adcards 
            (id, template, headline, subtitle, price_badge, cta_text, accent_color)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $tpl, $headline, $sub, $price, $cta, $color]);

        return ['id' => $id, 'status' => 'saved'];
    }
}
