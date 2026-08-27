<?php
/**
 * Dashboard Service — pulls live data for the organizer command centre.
 * Sources: tie_events_events, bookings, tie_event_ticket_types,
 *          tie_event_checkins, tie_event_messages, tie_event_reviews.
 */
final class UthengaDashboard
{
    public function __construct(private PDO $db) {}

    private function money(mixed $v): float { return round((float) $v, 2); }
    private function n(mixed $v): int       { return (int) $v; }
    private function fmt(float $v): string  { return number_format($v, 0, '.', ','); }

    private function listings(string $vendorId): string
    {
        $stmt = $this->db->prepare('SELECT listing_id FROM tie_events_events WHERE vendor_id=? AND listing_id IS NOT NULL');
        $stmt->execute([$vendorId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) return "''";
        return implode(',', array_map(fn($id) => $this->db->quote((string)$id), $ids));
    }

    private function eventIds(string $vendorId): string
    {
        $stmt = $this->db->prepare('SELECT id FROM tie_events_events WHERE vendor_id=?');
        $stmt->execute([$vendorId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) return "''";
        return implode(',', array_map(fn($id) => $this->db->quote((string)$id), $ids));
    }

    public function overview(string $vendorId): array
    {
        $listings = $this->listings($vendorId);
        $eventIds = $this->eventIds($vendorId);
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $revToday     = $this->money($this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND DATE(created_at)='$today'")->fetchColumn());
        $revYesterday = $this->money($this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND DATE(created_at)='$yesterday'")->fetchColumn());
        $revPct = $revYesterday > 0 ? round(($revToday - $revYesterday) / $revYesterday * 100, 1) : ($revToday > 0 ? 100 : 0);

        $ticketsToday     = $this->n($this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND DATE(created_at)='$today'")->fetchColumn());
        $ticketsYesterday = $this->n($this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND DATE(created_at)='$yesterday'")->fetchColumn());
        $ticketsPct = $ticketsYesterday > 0 ? round(($ticketsToday - $ticketsYesterday) / $ticketsYesterday * 100, 1) : ($ticketsToday > 0 ? 100 : 0);

        $liveCount     = $this->n($this->db->query("SELECT COUNT(*) FROM tie_events_events WHERE vendor_id=" . $this->db->quote($vendorId) . " AND status='live'")->fetchColumn());
        $upcomingCount = $this->n($this->db->query("SELECT COUNT(*) FROM tie_events_events WHERE vendor_id=" . $this->db->quote($vendorId) . " AND status IN ('published','upcoming')")->fetchColumn());

        $checkinsToday   = 0;
        $expectedCheckins = $this->n($this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid'")->fetchColumn());
        try {
            $checkinsToday = $this->n($this->db->query("SELECT COUNT(*) FROM tie_event_checkins WHERE event_id IN ($eventIds) AND DATE(checked_in_at)='$today' AND status='success'")->fetchColumn());
        } catch (Throwable) {}
        $checkinPct = $expectedCheckins > 0 ? round($checkinsToday / $expectedCheckins * 100, 1) : 0;

        $pendingMessages = 0;
        try {
            $pendingMessages = $this->n($this->db->query("SELECT COUNT(*) FROM tie_event_messages WHERE event_id IN ($eventIds) AND direction='inbound' AND status IN ('unread','open')")->fetchColumn());
        } catch (Throwable) {}

        return [
            'revenue_today'     => $revToday,
            'revenue_today_fmt' => 'MK ' . $this->fmt($revToday),
            'revenue_pct'       => $revPct,
            'tickets_today'     => $ticketsToday,
            'tickets_pct'       => $ticketsPct,
            'active_events'     => $liveCount + $upcomingCount,
            'live_count'        => $liveCount,
            'upcoming_count'    => $upcomingCount,
            'checkins_today'    => $checkinsToday,
            'checkin_pct'       => $checkinPct,
            'pending_messages'  => $pendingMessages,
        ];
    }

    public function liveEvent(string $vendorId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.id, e.title, e.status, e.listing_id, e.start_date, e.start_time,
                    COALESCE(v.name, 'Bingu International Convention Centre - Hall A') AS venue_name,
                    COALESCE(v.city, 'Lilongwe') AS city,
                    COALESCE(l.venue_capacity, 200) AS capacity,
                    COALESCE(e.cover_image_url, l.image, '') AS cover_image_url,
                    COALESCE(SUM(CASE WHEN b.payment_status='Paid' THEN b.total_price ELSE 0 END),0) AS revenue,
                    COUNT(DISTINCT b.id) AS tickets_sold
             FROM tie_events_events e
             LEFT JOIN listings l ON l.id=e.listing_id
             LEFT JOIN tie_venues v ON v.id=e.venue_id
             LEFT JOIN bookings b ON b.listing_id=e.listing_id AND b.payment_status='Paid'
             WHERE e.vendor_id=? AND UPPER(e.status) IN ('LIVE','PUBLISHED','UPCOMING')
             GROUP BY e.id ORDER BY e.start_date ASC LIMIT 1"
        );
        $stmt->execute([$vendorId]);
        $ev = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ev) return null;

        $checkins = 0;
        try {
            $checkins = $this->n($this->db->query("SELECT COUNT(*) FROM tie_event_checkins WHERE event_id=" . $this->db->quote($ev['id']) . " AND status='success'")->fetchColumn());
        } catch (Throwable) {}
        $cap = max(1, $this->n($ev['capacity']));

        return [
            'id'          => $ev['id'],
            'title'       => $ev['title'],
            'status'      => strtolower((string)$ev['status']),
            'venue'       => trim(($ev['venue_name'] ?? '') . ($ev['city'] ? ' · ' . $ev['city'] : '')),
            'date'        => $ev['start_date'] ?? '',
            'time'        => $ev['start_time'] ?? '',
            'capacity'    => $cap,
            'tickets_sold'=> $this->n($ev['tickets_sold']),
            'checkins'    => $checkins,
            'checkin_pct' => round($checkins / $cap * 100, 1),
            'revenue'     => $this->money($ev['revenue']),
            'revenue_fmt' => 'MK ' . $this->fmt($this->money($ev['revenue'])),
            'cover'       => $ev['cover_image_url'] ?: '',
        ];
    }

    public function upcomingEvents(string $vendorId, int $limit = 3): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.id, e.title, e.status, e.start_date, e.end_date,
                    COALESCE(v.name, 'Crossroads Hotel') AS venue_name,
                    COALESCE(v.city, 'Lilongwe') AS city,
                    COALESCE(e.cover_image_url, l.image, '') AS cover_image_url,
                    COUNT(DISTINCT b.id) AS tickets_sold,
                    COALESCE(l.venue_capacity, 200) AS capacity
             FROM tie_events_events e
             LEFT JOIN listings l ON l.id=e.listing_id
             LEFT JOIN tie_venues v ON v.id=e.venue_id
             LEFT JOIN bookings b ON b.listing_id=e.listing_id AND b.payment_status='Paid'
             WHERE e.vendor_id=? AND UPPER(e.status) IN ('PUBLISHED','UPCOMING','LIVE')
             GROUP BY e.id ORDER BY e.start_date ASC LIMIT ?"
        );
        $stmt->execute([$vendorId, $limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $now = new DateTime();
        return array_map(function($ev) use ($now) {
            $start = new DateTime($ev['start_date'] ?? date('Y-m-d'));
            $diff  = $now->diff($start);
            $days  = (int) $diff->format('%r%a');
            $label = match(true) {
                $days === 0  => 'Today',
                $days === 1  => 'Tomorrow',
                $days < 0   => abs($days) . 'd ago',
                default      => 'Starts in ' . $days . ' days',
            };
            return [
                'id'          => $ev['id'],
                'title'       => $ev['title'],
                'venue'       => trim(($ev['venue_name'] ?? '') . ($ev['city'] ? ' · ' . $ev['city'] : '')),
                'start_date'  => $ev['start_date'],
                'end_date'    => $ev['end_date'] ?? $ev['start_date'],
                'cover'       => $ev['cover_image_url'] ?: '',
                'tickets_sold'=> (int)$ev['tickets_sold'],
                'capacity'    => (int)$ev['capacity'],
                'days_label'  => $label,
                'days'        => $days,
            ];
        }, $rows);
    }

    public function todaysSchedule(string $vendorId, int $limit = 5): array
    {
        $today = date('Y-m-d');
        $rows  = [];
        try {
            $stmt = $this->db->prepare(
                "SELECT s.title, s.start_time, s.location, s.status, e.id AS event_id
                 FROM tie_event_schedule s
                 JOIN tie_events_events e ON e.id=s.event_id
                 WHERE e.vendor_id=? AND s.schedule_date=?
                 ORDER BY s.start_time ASC LIMIT ?"
            );
            $stmt->execute([$vendorId, $today, $limit]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {}

        if (!$rows) {
            $stmt = $this->db->prepare(
                "SELECT title, COALESCE(start_time,'08:00:00') AS start_time, 'Main Venue' AS location, status, id AS event_id
                 FROM tie_events_events
                 WHERE vendor_id=? AND (start_date=? OR status='live')
                 ORDER BY start_time ASC LIMIT ?"
            );
            $stmt->execute([$vendorId, $today, $limit]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return array_map(fn($r) => [
            'title'      => $r['title'],
            'start_time' => substr($r['start_time'] ?? '00:00', 0, 5),
            'location'   => $r['location'] ?? '',
            'status'     => $r['status'] ?? 'upcoming',
            'event_id'   => $r['event_id'] ?? '',
        ], $rows);
    }

    public function recentBookings(string $vendorId, int $limit = 6): array
    {
        $listings = $this->listings($vendorId);
        $rows = [];
        try {
            $stmt = $this->db->query(
                "SELECT b.id, b.customer_name, b.payment_status, b.total_price, b.created_at,
                        b.listing_title AS event_title,
                        'Standard' AS ticket_type
                 FROM bookings b
                 WHERE b.listing_id IN ($listings)
                 ORDER BY b.created_at DESC LIMIT $limit"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {}

        return array_map(fn($r) => [
            'id'          => $r['id'],
            'customer'    => $r['customer_name'] ?: 'Guest',
            'event'       => $r['event_title'] ?: '—',
            'ticket_type' => $r['ticket_type'] ?? 'Standard',
            'amount_fmt'  => 'MK ' . $this->fmt($this->money($r['total_price'])),
            'status'      => $r['payment_status'],
            'created_at'  => $r['created_at'],
        ], $rows);
    }

    public function weekOverview(string $vendorId): array
    {
        $listings = $this->listings($vendorId);
        $eventIds = $this->eventIds($vendorId);
        $weekAgo  = date('Y-m-d', strtotime('-7 days'));
        $prevStart = date('Y-m-d', strtotime('-14 days'));
        $vq = $this->db->quote($vendorId);

        $revYTD   = $this->money($this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid'")->fetchColumn());
        $revWeek  = $this->money($this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND created_at>='$weekAgo'")->fetchColumn());
        $revPrev  = $this->money($this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND created_at>='$prevStart' AND created_at<'$weekAgo'")->fetchColumn());
        $revPct   = $revPrev > 0 ? round(($revWeek - $revPrev) / $revPrev * 100, 1) : ($revWeek > 0 ? 100 : 0);

        $tickYTD  = $this->n($this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid'")->fetchColumn());
        $tickWeek = $this->n($this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND created_at>='$weekAgo'")->fetchColumn());
        $tickPrev = $this->n($this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND created_at>='$prevStart' AND created_at<'$weekAgo'")->fetchColumn());
        $tickPct  = $tickPrev > 0 ? round(($tickWeek - $tickPrev) / $tickPrev * 100, 1) : ($tickWeek > 0 ? 100 : 0);

        $evMonth  = $this->n($this->db->query("SELECT COUNT(*) FROM tie_events_events WHERE vendor_id=$vq AND MONTH(start_date)=MONTH(NOW()) AND YEAR(start_date)=YEAR(NOW())")->fetchColumn());
        $evPrev   = $this->n($this->db->query("SELECT COUNT(*) FROM tie_events_events WHERE vendor_id=$vq AND MONTH(start_date)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(start_date)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))")->fetchColumn());

        $totalCI  = 0;
        $ciRate   = 0.0;
        try {
            $totalCI = $this->n($this->db->query("SELECT COUNT(*) FROM tie_event_checkins WHERE event_id IN ($eventIds) AND status='success'")->fetchColumn());
            $ciRate  = $tickYTD > 0 ? round($totalCI / $tickYTD * 100, 1) : 0;
        } catch (Throwable) {}

        $avgRating = 0.0;
        try {
            $r = $this->db->query("SELECT AVG(rating) FROM tie_event_reviews WHERE event_id IN ($eventIds) AND status='published'")->fetchColumn();
            $avgRating = round((float)($r ?? 0), 1);
        } catch (Throwable) {}

        return [
            'revenue_ytd_fmt' => 'MK ' . $this->fmt($revYTD),
            'revenue_pct'     => $revPct,
            'tickets_ytd'     => $tickYTD,
            'tickets_pct'     => $tickPct,
            'events_month'    => $evMonth,
            'events_prev'     => $evPrev,
            'attendees_ytd'   => $tickYTD,
            'attendees_pct'   => $tickPct,
            'checkin_rate'    => $ciRate,
            'avg_rating'      => $avgRating,
        ];
    }

    public function insights(string $vendorId): array
    {
        $listings = $this->listings($vendorId);
        $eventIds = $this->eventIds($vendorId);
        $alerts   = [];
        $vq       = $this->db->quote($vendorId);

        // Low-stock VIP check
        try {
            $vip = $this->db->query(
                "SELECT tt.name, tt.total_quantity - COALESCE(SUM(CASE WHEN b.payment_status='Paid' THEN 1 ELSE 0 END),0) AS rem, e.title
                 FROM tie_event_ticket_types tt
                 JOIN tie_events_events e ON e.id=tt.event_id
                 LEFT JOIN bookings b ON b.listing_id=e.listing_id AND b.ticket_type_id=tt.id
                 WHERE e.vendor_id=$vq AND UPPER(tt.name) LIKE '%VIP%' AND e.status IN ('live','published')
                 GROUP BY tt.id ORDER BY rem ASC LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            if ($vip && (int)$vip['rem'] < 20) {
                $alerts[] = ['type'=>'urgent','icon'=>'rocket','title'=> ($vip['name'].' tickets nearly sold out!'), 'body'=>'Only '.max(0,(int)$vip['rem']).' left for '.$vip['title'],'action'=>'Increase Price / Cap','action_mod'=>'tickets'];
            }
        } catch (Throwable) {}

        // Sales velocity
        $today = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-7 days'));
        $t0 = $this->n($this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND DATE(created_at)='$today'")->fetchColumn());
        $t7 = $this->n($this->db->query("SELECT COUNT(*) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND created_at>='$weekAgo'")->fetchColumn());
        $avg = $t7 / 7;
        if ($avg > 0 && $t0 > $avg * 1.3) {
            $pct = round(($t0 - $avg) / $avg * 100);
            $alerts[] = ['type'=>'good','icon'=>'trending','title'=>'Sales velocity increased','body'=>"Ticket sales are {$pct}% higher than last week.",'action'=>'View Insights','action_mod'=>'analytics'];
        }

        // Pending messages
        $pm = 0;
        try { $pm = $this->n($this->db->query("SELECT COUNT(*) FROM tie_event_messages WHERE event_id IN ($eventIds) AND direction='inbound' AND status IN ('unread','open')")->fetchColumn()); } catch (Throwable) {}
        if ($pm > 3) {
            $alerts[] = ['type'=>'warn','icon'=>'mail','title'=>"{$pm} unread customer messages",'body'=>'Customers are waiting. Response time affects your reviews.','action'=>'Open Inbox','action_mod'=>'messages'];
        }

        if (empty($alerts)) {
            $alerts[] = ['type'=>'info','icon'=>'sparkle','title'=>'All systems operational','body'=>'No critical alerts. Your events are running smoothly.','action'=>'View Analytics','action_mod'=>'analytics'];
        }
        return $alerts;
    }

    public function sparkData(string $vendorId): array
    {
        $listings = $this->listings($vendorId);
        $pts = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $pts[] = $this->money($this->db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE listing_id IN ($listings) AND payment_status='Paid' AND DATE(created_at)='$d'")->fetchColumn());
        }
        return $pts;
    }
}
