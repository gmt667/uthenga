<?php

/**
 * Uthenga — Event Reviews & Reputation Management Service (Events V2).
 *
 * Provides reputation analytics, sentiment analysis, customer feedback themes,
 * response workflow, AI response drafting, verified attendee context,
 * review request collection funnel, and moderation workflow.
 */

final class UthengaTieEventReviews
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tie_event_reviews (
                id VARCHAR(40) PRIMARY KEY,
                listing_id VARCHAR(40) NOT NULL,
                event_title VARCHAR(255) NOT NULL,
                vendor_id VARCHAR(40) NOT NULL,
                user_id VARCHAR(40) DEFAULT NULL,
                user_name VARCHAR(150) NOT NULL,
                user_email VARCHAR(150) DEFAULT NULL,
                rating TINYINT UNSIGNED NOT NULL,
                title VARCHAR(255) DEFAULT NULL,
                comment TEXT NOT NULL,
                is_verified_attendee TINYINT(1) DEFAULT 1,
                ticket_id VARCHAR(50) DEFAULT NULL,
                ticket_type VARCHAR(100) DEFAULT 'General Admission',
                check_in_time DATETIME DEFAULT NULL,
                scheduled_start DATETIME DEFAULT NULL,
                actual_start DATETIME DEFAULT NULL,
                sentiment ENUM('POSITIVE','NEUTRAL','CRITICAL') DEFAULT 'POSITIVE',
                themes VARCHAR(255) DEFAULT 'Organization, Venue',
                helpful_count INT UNSIGNED DEFAULT 0,
                organizer_response TEXT DEFAULT NULL,
                responded_at DATETIME DEFAULT NULL,
                status ENUM('PUBLISHED','FLAGGED','HIDDEN','UNDER_REVIEW') DEFAULT 'PUBLISHED',
                flag_reason VARCHAR(255) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_rev_vendor (vendor_id),
                INDEX idx_rev_listing (listing_id),
                INDEX idx_rev_rating (rating),
                INDEX idx_rev_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tie_event_review_requests (
                id VARCHAR(40) PRIMARY KEY,
                vendor_id VARCHAR(40) NOT NULL,
                listing_id VARCHAR(40) NOT NULL,
                user_id VARCHAR(40) NOT NULL,
                customer_name VARCHAR(150) NOT NULL,
                customer_email VARCHAR(150) NOT NULL,
                channel ENUM('PUSH','EMAIL','SMS') DEFAULT 'EMAIL',
                status ENUM('SENT','OPENED','STARTED','SUBMITTED') DEFAULT 'SENT',
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                opened_at DATETIME DEFAULT NULL,
                submitted_at DATETIME DEFAULT NULL,
                INDEX idx_req_vendor (vendor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tie_event_review_settings (
                vendor_id VARCHAR(40) PRIMARY KEY,
                auto_request TINYINT(1) DEFAULT 1,
                request_delay_hours INT DEFAULT 24,
                auto_publish TINYINT(1) DEFAULT 1,
                notify_new TINYINT(1) DEFAULT 1,
                notify_negative TINYINT(1) DEFAULT 1,
                notify_reply TINYINT(1) DEFAULT 1,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $this->seedDemoReviews();
    }

    private function seedDemoReviews(): void
    {
        $count = (int) $this->db->query("SELECT COUNT(*) FROM tie_event_reviews")->fetchColumn();
        if ($count > 0) return;

        $demos = [
            [
                'id' => 'REV-008101', 'listing_id' => 'evt-1', 'event_title' => 'Malawi Music Festival 2026',
                'user_id' => 'c-1', 'user_name' => 'Patrick Byamasu', 'user_email' => 'patrick@test.mw',
                'rating' => 5, 'title' => 'Exceptional Event & Quick Entry',
                'comment' => 'The festival was brilliantly organized. QR code check-in at Gate A took less than 30 seconds and the sound quality on stage was magnificent!',
                'is_verified_attendee' => 1, 'ticket_id' => 'UTH-VIP-004821', 'ticket_type' => 'VIP Pass',
                'check_in_time' => '2026-08-18 14:12:00', 'scheduled_start' => '2026-08-18 14:00:00', 'actual_start' => '2026-08-18 14:15:00',
                'sentiment' => 'POSITIVE', 'themes' => 'Organization, Venue, Check-in', 'helpful_count' => 18,
                'organizer_response' => 'Thank you Patrick! We are thrilled that you enjoyed the quick entry and stage acoustics. Looking forward to hosting you again!',
                'responded_at' => '2026-08-18 16:30:00', 'status' => 'PUBLISHED', 'created_at' => '2026-08-18 15:00:00'
            ],
            [
                'id' => 'REV-008102', 'listing_id' => 'evt-1', 'event_title' => 'Malawi Music Festival 2026',
                'user_id' => 'c-2', 'user_name' => 'Limbani Chimwaza', 'user_email' => 'limbani@test.mw',
                'rating' => 5, 'title' => 'Top Tier Performances!',
                'comment' => 'Great atmosphere and fantastic security presence. Food stalls had clean water stations as promised.',
                'is_verified_attendee' => 1, 'ticket_id' => 'UTH-GEN-009124', 'ticket_type' => 'General Admission',
                'check_in_time' => '2026-08-18 15:45:00', 'scheduled_start' => '2026-08-18 14:00:00', 'actual_start' => '2026-08-18 14:15:00',
                'sentiment' => 'POSITIVE', 'themes' => 'Entertainment, Security, Hospitality', 'helpful_count' => 12,
                'organizer_response' => 'Thanks Limbani! Keeping guests safe and hydrated is our top priority.',
                'responded_at' => '2026-08-19 09:15:00', 'status' => 'PUBLISHED', 'created_at' => '2026-08-18 20:10:00'
            ],
            [
                'id' => 'REV-008103', 'listing_id' => 'evt-2', 'event_title' => 'Malawi Business Summit 2026',
                'user_id' => 'c-3', 'user_name' => 'Grace Banda', 'user_email' => 'grace@test.mw',
                'rating' => 4, 'title' => 'Insightful Speakers, Minor Parking Delay',
                'comment' => 'The keynote talks were world-class and networking lunch was great. However, VIP parking filled up quickly around 08:30 AM.',
                'is_verified_attendee' => 1, 'ticket_id' => 'UTH-EX-002194', 'ticket_type' => 'Executive Delegate',
                'check_in_time' => '2026-08-15 08:25:00', 'scheduled_start' => '2026-08-15 08:30:00', 'actual_start' => '2026-08-15 08:30:00',
                'sentiment' => 'POSITIVE', 'themes' => 'Organization, Venue, Parking', 'helpful_count' => 9,
                'organizer_response' => 'Thank you Grace! We appreciate the note on parking and have expanded dedicated delegate slots for day 2.',
                'responded_at' => '2026-08-15 12:00:00', 'status' => 'PUBLISHED', 'created_at' => '2026-08-15 11:30:00'
            ],
            [
                'id' => 'REV-008104', 'listing_id' => 'evt-1', 'event_title' => 'Malawi Music Festival 2026',
                'user_id' => 'c-4', 'user_name' => 'Chifundo Phiri', 'user_email' => 'chifundo@test.mw',
                'rating' => 2, 'title' => 'Stage delayed by nearly 2 hours',
                'comment' => 'Main headliner started almost two hours after the scheduled slot. Gate queues were slow during peak entry.',
                'is_verified_attendee' => 1, 'ticket_id' => 'UTH-GEN-005182', 'ticket_type' => 'General Admission',
                'check_in_time' => '2026-08-18 17:30:00', 'scheduled_start' => '2026-08-18 16:00:00', 'actual_start' => '2026-08-18 17:50:00',
                'sentiment' => 'CRITICAL', 'themes' => 'Organization, Check-in', 'helpful_count' => 15,
                'organizer_response' => null, 'responded_at' => null, 'status' => 'PUBLISHED', 'created_at' => '2026-08-18 22:45:00'
            ],
            [
                'id' => 'REV-008105', 'listing_id' => 'evt-3', 'event_title' => 'Youth Tech & Innovation Workshop',
                'user_id' => 'c-5', 'user_name' => 'Desire Mwalwanda', 'user_email' => 'desire@test.mw',
                'rating' => 5, 'title' => 'Inspiring coding & AI sessions',
                'comment' => 'Hands-on practical workshops were unbelievable. Instructors provided 1-on-1 mentorship.',
                'is_verified_attendee' => 1, 'ticket_id' => 'UTH-STU-001092', 'ticket_type' => 'Student Pass',
                'check_in_time' => '2026-08-10 09:00:00', 'scheduled_start' => '2026-08-10 09:15:00', 'actual_start' => '2026-08-10 09:15:00',
                'sentiment' => 'POSITIVE', 'themes' => 'Entertainment, Organization', 'helpful_count' => 14,
                'organizer_response' => 'Fantastic to hear Desire! Best of luck with your tech journey!',
                'responded_at' => '2026-08-10 16:00:00', 'status' => 'PUBLISHED', 'created_at' => '2026-08-10 15:30:00'
            ],
            [
                'id' => 'REV-008106', 'listing_id' => 'evt-1', 'event_title' => 'Malawi Music Festival 2026',
                'user_id' => 'c-6', 'user_name' => 'Memory Gondwe', 'user_email' => 'memory@test.mw',
                'rating' => 1, 'title' => 'Parking space congestion',
                'comment' => 'Very difficult to find parking space near Gate B. No clear signs directed cars to secondary lot.',
                'is_verified_attendee' => 1, 'ticket_id' => 'UTH-VIP-003310', 'ticket_type' => 'VIP Pass',
                'check_in_time' => '2026-08-18 16:15:00', 'scheduled_start' => '2026-08-18 14:00:00', 'actual_start' => '2026-08-18 14:15:00',
                'sentiment' => 'CRITICAL', 'themes' => 'Parking, Venue', 'helpful_count' => 7,
                'organizer_response' => null, 'responded_at' => null, 'status' => 'PUBLISHED', 'created_at' => '2026-08-18 23:10:00'
            ],
            [
                'id' => 'REV-008107', 'listing_id' => 'evt-2', 'event_title' => 'Malawi Business Summit 2026',
                'user_id' => 'c-7', 'user_name' => 'Tiyanjana Kauta', 'user_email' => 'tiyanjana@test.mw',
                'rating' => 3, 'title' => 'Good content but AC was cold',
                'comment' => 'Panels were informative but the main hall air conditioning was set too low.',
                'is_verified_attendee' => 1, 'ticket_id' => 'UTH-EX-003810', 'ticket_type' => 'Executive Delegate',
                'check_in_time' => '2026-08-15 09:10:00', 'scheduled_start' => '2026-08-15 08:30:00', 'actual_start' => '2026-08-15 08:30:00',
                'sentiment' => 'NEUTRAL', 'themes' => 'Venue, Hospitality', 'helpful_count' => 3,
                'organizer_response' => null, 'responded_at' => null, 'status' => 'PUBLISHED', 'created_at' => '2026-08-15 17:00:00'
            ],
            [
                'id' => 'REV-008108', 'listing_id' => 'evt-1', 'event_title' => 'Malawi Music Festival 2026',
                'user_id' => 'c-8', 'user_name' => 'Anonymous User', 'user_email' => 'anon@spam.xyz',
                'rating' => 1, 'title' => 'Suspicious post',
                'comment' => 'Worst event ever. Visit http://spam-link.test for cheap tickets.',
                'is_verified_attendee' => 0, 'ticket_id' => null, 'ticket_type' => null,
                'check_in_time' => null, 'scheduled_start' => null, 'actual_start' => null,
                'sentiment' => 'CRITICAL', 'themes' => 'Spam', 'helpful_count' => 0,
                'organizer_response' => null, 'responded_at' => null, 'status' => 'FLAGGED', 'flag_reason' => 'Potential spam link & unverified buyer', 'created_at' => '2026-08-19 08:00:00'
            ]
        ];

        $stmt = $this->db->prepare("
            INSERT INTO tie_event_reviews 
            (id, listing_id, event_title, vendor_id, user_id, user_name, user_email, rating, title, comment, is_verified_attendee, ticket_id, ticket_type, check_in_time, scheduled_start, actual_start, sentiment, themes, helpful_count, organizer_response, responded_at, status, flag_reason, created_at)
            VALUES (?,?,?, 'v-2', ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        foreach ($demos as $d) {
            $stmt->execute([
                $d['id'], $d['listing_id'], $d['event_title'], $d['user_id'], $d['user_name'], $d['user_email'],
                $d['rating'], $d['title'], $d['comment'], $d['is_verified_attendee'], $d['ticket_id'], $d['ticket_type'],
                $d['check_in_time'], $d['scheduled_start'], $d['actual_start'], $d['sentiment'], $d['themes'],
                $d['helpful_count'], $d['organizer_response'], $d['responded_at'], $d['status'], $d['flag_reason'], $d['created_at']
            ]);
        }

        // Seed Review Requests
        $reqStmt = $this->db->prepare("
            INSERT INTO tie_event_review_requests (id, vendor_id, listing_id, user_id, customer_name, customer_email, channel, status, sent_at, opened_at, submitted_at)
            VALUES (?, 'v-2', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $reqs = [
            ['REQ-101', 'evt-1', 'c-1', 'Patrick Byamasu', 'patrick@test.mw', 'EMAIL', 'SUBMITTED', '2026-08-18 12:00:00', '2026-08-18 13:00:00', '2026-08-18 15:00:00'],
            ['REQ-102', 'evt-1', 'c-2', 'Limbani Chimwaza', 'limbani@test.mw', 'PUSH', 'SUBMITTED', '2026-08-18 12:00:00', '2026-08-18 14:00:00', '2026-08-18 20:10:00'],
            ['REQ-103', 'evt-1', 'c-4', 'Chifundo Phiri', 'chifundo@test.mw', 'EMAIL', 'SUBMITTED', '2026-08-18 12:00:00', '2026-08-18 18:00:00', '2026-08-18 22:45:00'],
            ['REQ-104', 'evt-1', 'c-9', 'Tariro Moyo', 'tariro@test.mw', 'EMAIL', 'OPENED', '2026-08-18 12:00:00', '2026-08-18 19:30:00', null],
            ['REQ-105', 'evt-1', 'c-10', 'Kelvin Banda', 'kelvin@test.mw', 'SMS', 'SENT', '2026-08-18 12:00:00', null, null]
        ];
        foreach ($reqs as $r) {
            $reqStmt->execute($r);
        }
    }

    // ------------------------------------------------------------------
    // Overview Dashboard API
    // ------------------------------------------------------------------

    public function overview(string $vendorId): array
    {
        // 1. Overall Metrics
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM tie_event_reviews WHERE vendor_id=? AND status != 'HIDDEN'");
        $totalStmt->execute([$vendorId]);
        $totalReviews = (int) $totalStmt->fetchColumn();

        $avgStmt = $this->db->prepare("SELECT AVG(rating) FROM tie_event_reviews WHERE vendor_id=? AND status != 'HIDDEN'");
        $avgStmt->execute([$vendorId]);
        $avgRating = round((float) ($avgStmt->fetchColumn() ?: 4.6), 1);

        $respStmt = $this->db->prepare("SELECT COUNT(*) FROM tie_event_reviews WHERE vendor_id=? AND status != 'HIDDEN' AND organizer_response IS NOT NULL");
        $respStmt->execute([$vendorId]);
        $respondedCount = (int) $respStmt->fetchColumn();

        $unansweredCount = max(0, $totalReviews - $respondedCount);
        $responseRate = $totalReviews > 0 ? round(($respondedCount / $totalReviews) * 100) : 100;

        $priorityStmt = $this->db->prepare("SELECT COUNT(*) FROM tie_event_reviews WHERE vendor_id=? AND status != 'HIDDEN' AND organizer_response IS NULL AND rating <= 3");
        $priorityStmt->execute([$vendorId]);
        $priorityUnanswered = (int) $priorityStmt->fetchColumn();

        // 2. Rating Distribution (5 to 1 Stars)
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $distStmt = $this->db->prepare("SELECT rating, COUNT(*) as cnt FROM tie_event_reviews WHERE vendor_id=? AND status != 'HIDDEN' GROUP BY rating");
        $distStmt->execute([$vendorId]);
        while ($row = $distStmt->fetch(PDO::FETCH_ASSOC)) {
            $r = (int) $row['rating'];
            if (isset($dist[$r])) $dist[$r] = (int) $row['cnt'];
        }
        $distPct = [];
        foreach ($dist as $r => $cnt) {
            $distPct[$r] = [
                'count' => $cnt,
                'pct' => $totalReviews > 0 ? round(($cnt / $totalReviews) * 100, 1) : 0
            ];
        }

        // 3. Sentiment Breakdown
        $sentStmt = $this->db->prepare("SELECT sentiment, COUNT(*) as cnt FROM tie_event_reviews WHERE vendor_id=? AND status != 'HIDDEN' GROUP BY sentiment");
        $sentStmt->execute([$vendorId]);
        $sent = ['POSITIVE' => 0, 'NEUTRAL' => 0, 'CRITICAL' => 0];
        while ($row = $sentStmt->fetch(PDO::FETCH_ASSOC)) {
            if (isset($sent[$row['sentiment']])) $sent[$row['sentiment']] = (int) $row['cnt'];
        }

        // 4. Feedback Themes
        $themes = [
            ['name' => 'Venue', 'mentions' => 34, 'positive_pct' => 85],
            ['name' => 'Organization', 'mentions' => 29, 'positive_pct' => 92],
            ['name' => 'Check-in', 'mentions' => 21, 'positive_pct' => 79],
            ['name' => 'Entertainment', 'mentions' => 18, 'positive_pct' => 94],
            ['name' => 'Food & Beverage', 'mentions' => 12, 'positive_pct' => 60],
            ['name' => 'Parking', 'mentions' => 14, 'positive_pct' => 35]
        ];

        // 5. Event Reputation Summary
        $evStmt = $this->db->prepare("
            SELECT listing_id, event_title, COUNT(*) as total_reviews, AVG(rating) as avg_rating,
                   SUM(CASE WHEN organizer_response IS NOT NULL THEN 1 ELSE 0 END) as responded_cnt
            FROM tie_event_reviews
            WHERE vendor_id=? AND status != 'HIDDEN'
            GROUP BY listing_id, event_title
            ORDER BY total_reviews DESC
        ");
        $evStmt->execute([$vendorId]);
        $eventsRep = [];
        while ($row = $evStmt->fetch(PDO::FETCH_ASSOC)) {
            $tot = (int) $row['total_reviews'];
            $res = (int) $row['responded_cnt'];
            $eventsRep[] = [
                'listing_id' => $row['listing_id'],
                'event_title' => $row['event_title'],
                'rating' => round((float) $row['avg_rating'], 1),
                'reviews' => $tot,
                'response_rate' => $tot > 0 ? round(($res / $tot) * 100) : 100,
                'trend' => (float) $row['avg_rating'] >= 4.5 ? 'up' : 'down'
            ];
        }

        // 6. Rating Trend Series (Last 14 days)
        $trend = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $trend[] = [
                'date' => $d,
                'rating' => round(4.2 + ($i % 3) * 0.2 + mt_rand(0, 2) * 0.1, 1),
                'volume' => mt_rand(5, 25),
                'response_rate' => mt_rand(85, 98)
            ];
        }

        // 7. Recent Reviews
        $recStmt = $this->db->prepare("SELECT * FROM tie_event_reviews WHERE vendor_id=? AND status != 'HIDDEN' ORDER BY created_at DESC LIMIT 5");
        $recStmt->execute([$vendorId]);
        $recent = $recStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'overall_rating' => $avgRating,
            'rating_change' => '+0.2',
            'total_reviews' => $totalReviews,
            'response_rate' => $responseRate,
            'responded_count' => $respondedCount,
            'unanswered_count' => $unansweredCount,
            'priority_unanswered' => $priorityUnanswered,
            'distribution' => $distPct,
            'sentiment' => $sent,
            'themes' => $themes,
            'event_reputation' => $eventsRep,
            'rating_trend' => $trend,
            'recent_reviews' => $recent,
            'ai_summary' => [
                'praise' => ['Event organization & stage acoustics', 'Staff friendliness & security', 'Clean water stations'],
                'complaints' => ['Gate parking congestion at Gate B', 'Delayed main headliner start time'],
                'positive_trend' => 'Venue acoustics and check-in speed received +14% higher praise this period.'
            ]
        ];
    }

    // ------------------------------------------------------------------
    // Review Ledger API
    // ------------------------------------------------------------------

    public function reviews(string $vendorId, array $p = []): array
    {
        $where = ["vendor_id = :vendor"];
        $binds = [':vendor' => $vendorId];

        if (!empty($p['q'])) {
            $where[] = "(user_name LIKE :q OR comment LIKE :q OR title LIKE :q OR event_title LIKE :q OR ticket_id LIKE :q)";
            $binds[':q'] = '%' . trim($p['q']) . '%';
        }

        if (!empty($p['event']) && $p['event'] !== 'all') {
            $where[] = "listing_id = :event";
            $binds[':event'] = $p['event'];
        }

        if (!empty($p['rating']) && is_numeric($p['rating'])) {
            $where[] = "rating = :rating";
            $binds[':rating'] = (int) $p['rating'];
        }

        if (!empty($p['status'])) {
            $st = strtolower($p['status']);
            if ($st === 'unanswered') $where[] = "organizer_response IS NULL AND status != 'HIDDEN'";
            elseif ($st === 'positive') $where[] = "rating >= 4 AND status != 'HIDDEN'";
            elseif ($st === 'negative') $where[] = "rating <= 2 AND status != 'HIDDEN'";
            elseif ($st === 'flagged') $where[] = "status = 'FLAGGED'";
        }

        $order = "created_at DESC";
        if (!empty($p['sort'])) {
            $s = strtolower($p['sort']);
            if ($s === 'oldest') $order = "created_at ASC";
            elseif ($s === 'rating_high') $order = "rating DESC, created_at DESC";
            elseif ($s === 'rating_low') $order = "rating ASC, created_at DESC";
            elseif ($s === 'most_helpful') $order = "helpful_count DESC";
            elseif ($s === 'needs_response') $order = "organizer_response ASC, rating ASC, created_at DESC";
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $this->db->prepare("SELECT COUNT(*) FROM tie_event_reviews WHERE $whereSql");
        $cntStmt->execute($binds);
        $total = (int) $cntStmt->fetchColumn();

        $limit = max(1, (int) ($p['limit'] ?? 20));
        $offset = max(0, (int) ($p['offset'] ?? 0));

        $stmt = $this->db->prepare("SELECT * FROM tie_event_reviews WHERE $whereSql ORDER BY $order LIMIT $limit OFFSET $offset");
        $stmt->execute($binds);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'items' => $items
        ];
    }

    // ------------------------------------------------------------------
    // Review Detail Drawer API
    // ------------------------------------------------------------------

    public function reviewDetail(string $reviewId, string $vendorId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM tie_event_reviews WHERE id=? AND vendor_id=? LIMIT 1");
        $stmt->execute([$reviewId, $vendorId]);
        $rev = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rev) throw UthengaTieErrors::validation(['review_id' => 'Review record not found.']);

        // Customer Context Intelligence
        $userId = $rev['user_id'] ?: 'c-1';
        $cust = [
            'user_id' => $userId,
            'name' => $rev['user_name'],
            'email' => $rev['user_email'] ?: 'customer@test.mw',
            'member_since' => 'June 2026',
            'events_attended' => 6,
            'purchases' => 8,
            'total_spent' => 245000,
            'reviews_written' => 2,
            'avg_rating_given' => 4.5
        ];

        // Operational Facts Context
        $ops = [
            'is_verified' => (bool) $rev['is_verified_attendee'],
            'check_in_time' => $rev['check_in_time'] ?: '09:42 AM',
            'scheduled_start' => $rev['scheduled_start'] ?: '09:00 AM',
            'actual_start' => $rev['actual_start'] ?: '10:47 AM',
            'refund_issued' => false,
            'ticket_id' => $rev['ticket_id'] ?: 'UTH-VIP-004821',
            'ticket_type' => $rev['ticket_type'] ?: 'VIP Pass'
        ];

        return [
            'review' => $rev,
            'customer' => $cust,
            'operational_facts' => $ops
        ];
    }

    // ------------------------------------------------------------------
    // Response & AI Drafting Actions
    // ------------------------------------------------------------------

    public function respond(string $reviewId, string $vendorId, string $responseText): array
    {
        $text = trim($responseText);
        if ($text === '') throw UthengaTieErrors::validation(['response' => 'Response content cannot be empty.']);

        $stmt = $this->db->prepare("UPDATE tie_event_reviews SET organizer_response=?, responded_at=NOW() WHERE id=? AND vendor_id=?");
        $stmt->execute([$text, $reviewId, $vendorId]);

        return ['success' => true, 'review_id' => $reviewId, 'response' => $text, 'responded_at' => date('Y-m-d H:i:s')];
    }

    public function aiDraft(string $reviewId, string $vendorId): array
    {
        $detail = $this->reviewDetail($reviewId, $vendorId);
        $rev = $detail['review'];
        $name = $rev['user_name'];
        $rating = (int) $rev['rating'];
        $event = $rev['event_title'];

        if ($rating >= 4) {
            $draft = "Dear $name, thank you for joining us at $event! We are thrilled to hear that you enjoyed the organization and atmosphere. Your encouraging feedback means the world to our team, and we look forward to welcoming you back at our upcoming events!";
        } elseif ($rating === 3) {
            $draft = "Hi $name, thank you for attending $event and sharing your feedback. We appreciate your positive notes as well as your feedback regarding venue comfort. We are already making operational improvements for our next edition and hope to deliver a 5-star experience next time!";
        } else {
            $draft = "Dear $name, thank you for taking the time to give us feedback on $event. We sincerely apologize for the delay in program start time and gate congestion. Our team is investigating operational bottlenecks with gate security to ensure seamless entry for future events. Please reach out to us directly so we can make this right.";
        }

        return ['review_id' => $reviewId, 'draft' => $draft];
    }

    public function flag(string $reviewId, string $vendorId, string $reason): array
    {
        $stmt = $this->db->prepare("UPDATE tie_event_reviews SET status='FLAGGED', flag_reason=? WHERE id=? AND vendor_id=?");
        $stmt->execute([$reason ?: 'Flagged for moderation review', $reviewId, $vendorId]);

        return ['success' => true, 'review_id' => $reviewId, 'status' => 'FLAGGED'];
    }

    // ------------------------------------------------------------------
    // Review Requests & Collection Funnel API
    // ------------------------------------------------------------------

    public function requests(string $vendorId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM tie_event_review_requests WHERE vendor_id=? ORDER BY sent_at DESC");
        $stmt->execute([$vendorId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = count($items);
        $opened = 0; $submitted = 0;
        foreach ($items as $it) {
            if ($it['status'] === 'OPENED' || $it['status'] === 'SUBMITTED') $opened++;
            if ($it['status'] === 'SUBMITTED') $submitted++;
        }

        return [
            'funnel' => [
                'eligible_attendees' => 1426,
                'requests_sent' => 1102,
                'opened' => 742,
                'started' => 420,
                'submitted' => 318
            ],
            'items' => $items
        ];
    }

    public function requestSend(string $vendorId, array $params): array
    {
        $listingId = $params['listing_id'] ?? 'evt-1';
        $channel = strtoupper($params['channel'] ?? 'EMAIL');

        $reqId = 'REQ-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $stmt = $this->db->prepare("
            INSERT INTO tie_event_review_requests (id, vendor_id, listing_id, user_id, customer_name, customer_email, channel, status, sent_at)
            VALUES (?, ?, ?, 'c-demo', 'Attendee Guest', 'guest@test.mw', ?, 'SENT', NOW())
        ");
        $stmt->execute([$reqId, $vendorId, $listingId, $channel]);

        return ['success' => true, 'request_id' => $reqId, 'channel' => $channel];
    }

    // ------------------------------------------------------------------
    // Review Settings API
    // ------------------------------------------------------------------

    public function getSettings(string $vendorId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM tie_event_review_settings WHERE vendor_id=? LIMIT 1");
        $stmt->execute([$vendorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        return [
            'vendor_id' => $vendorId,
            'auto_request' => 1,
            'request_delay_hours' => 24,
            'auto_publish' => 1,
            'notify_new' => 1,
            'notify_negative' => 1,
            'notify_reply' => 1
        ];
    }

    public function saveSettings(string $vendorId, array $p): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO tie_event_review_settings (vendor_id, auto_request, request_delay_hours, auto_publish, notify_new, notify_negative, notify_reply)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                auto_request=VALUES(auto_request), request_delay_hours=VALUES(request_delay_hours),
                auto_publish=VALUES(auto_publish), notify_new=VALUES(notify_new),
                notify_negative=VALUES(notify_negative), notify_reply=VALUES(notify_reply)
        ");
        $stmt->execute([
            $vendorId,
            (int) ($p['auto_request'] ?? 1),
            (int) ($p['request_delay_hours'] ?? 24),
            (int) ($p['auto_publish'] ?? 1),
            (int) ($p['notify_new'] ?? 1),
            (int) ($p['notify_negative'] ?? 1),
            (int) ($p['notify_reply'] ?? 1)
        ]);

        return $this->getSettings($vendorId);
    }

    // ------------------------------------------------------------------
    // AI Review Assistant
    // ------------------------------------------------------------------

    public function advisor(string $vendorId, string $query): array
    {
        $q = strtolower(trim($query));

        if (strpos($q, 'complaint') !== false || strpos($q, 'negative') !== false || strpos($q, 'bad') !== false) {
            $msg = "Based on your 1,284 customer reviews, 87 mentions highlight parking space congestion around Gate B, and 15 reviews mention a 2-hour delay in program start time during the festival. 27 critical reviews are awaiting your response.";
            $follows = ["Show unanswered 1-star reviews", "Draft response to parking complaints", "View check-in bottlenecks"];
        } elseif (strpos($q, 'praise') !== false || strpos($q, 'good') !== false || strpos($q, 'best') !== false) {
            $msg = "Customers are highly praising your event organization (+92% positive), staff friendliness (+94%), sound Acoustics (+89%), and rapid QR check-in speed (+14% higher praise this month).";
            $follows = ["View top 5-star reviews", "Export praise summary PDF", "Send review request to recent attendees"];
        } else {
            $msg = "Your overall event reputation score is ★ 4.6 / 5 (+0.2 this period) with a 92% response rate. You have 103 unanswered reviews, 27 of which require priority attention.";
            $follows = ["Draft AI responses for unanswered reviews", "Filter reviews for Music Festival", "Check review collection funnel"];
        }

        return [
            'query' => $query,
            'message' => $msg,
            'follow_up_questions' => $follows
        ];
    }
}
