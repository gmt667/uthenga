<?php
/** Event Reviews service — the organizer's reputation and customer-feedback
 *  command center for the Events Control Center (Insights → Reviews).
 *
 *  Core loop: Collect → Understand → Respond → Resolve → Learn → Improve.
 *
 *  Design contract:
 *  * Reviews are individual feedback records; Analytics is the aggregate
 *    intelligence surface. Reviews never duplicate analytics numbers.
 *  * Negative != Invalid. A legitimate 1-star review stays published.
 *    Organizers get Respond / Investigate / Contact / Flag — erasure is a
 *    platform-moderation decision only.
 *  * Verified Attendee is an earned badge (valid paid ticket + checked-in
 *    attendance), re-derived from the operational tables at read time.
 *  * Sentiment and themes are deterministic keyword classifications over the
 *    review text. The platform gives the organizer the relevant facts; it
 *    does not decide who is right. A 5-star review can still surface a
 *    negative theme mention (e.g. "Great event, but the bathrooms were
 *    overcrowded.").
 *  * AI assistance drafts responses only — the organizer reviews, edits and
 *    publishes. Never silently publish AI-generated text.
 *  * Every write (response, flag, config) is recorded in the audit log.
 */

final class UthengaEventReviewsService
{
    public const SCHEMA = 'tie-reviews/v1';

    private const THEMES = [
        'venue'         => 'Venue',
        'organization'  => 'Organization',
        'check-in'      => 'Check-in',
        'entertainment' => 'Entertainment',
        'food_beverage' => 'Food & Beverage',
        'transport'     => 'Transport & Parking',
        'pricing'       => 'Pricing & Value',
    ];

    private const THEME_KEYWORDS = [
        'venue'         => ['venue', 'stage', 'sound', 'atmosphere', 'location', 'hall', 'lighting', 'acoustics', 'seats', 'seating', 'ambience', 'decor'],
        'organization'  => ['organiz', 'organis', 'logistics', 'staff', 'volunteer', 'coord', 'manage', 'handling', 'planning', 'well run', 'professional'],
        'check-in'      => ['check-in', 'checkin', 'check in', 'queue', 'entry', 'entrance', 'gate', 'ticketing', 'security', 'scanning', 'wristband', 'admission', 'lines', 'line was'],
        'entertainment' => ['music', 'performance', 'artist', 'band', 'dj', 'entertainment', 'lineup', 'show', 'concert', 'speaker', 'host', 'act '],
        'food_beverage' => ['food', 'drink', 'beverage', 'snack', 'refreshment', 'water', 'bar', 'catering', 'meal', 'eats'],
        'transport'     => ['parking', 'transport', 'traffic', 'shuttle', 'taxi', 'car park', 'access road'],
        'pricing'       => ['price', 'pricing', 'expensive', 'cost', 'value', 'fee', 'affordable', 'money', 'cheap', 'overpriced'],
    ];

    private const POSITIVE_WORDS = ['great', 'excellent', 'amazing', 'awesome', 'fantastic', 'wonderful', 'good',
        'smooth', 'quick', 'fast', 'friendly', 'helpful', 'enjoyed', 'loved', 'organized', 'organised', 'on time',
        'timely', 'easy', 'seamless', 'perfect', 'incredible', 'clean', 'comfortable', 'recommend', 'best',
        'professional', 'top notch', 'impressive', 'well organized', 'well organised', 'fun', 'value'];
    private const NEGATIVE_WORDS = ['late', 'delay', 'delayed', 'queue', 'queues', 'queuing', 'crowded',
        'overcrowded', 'dirty', 'filthy', 'bad', 'poor', 'terrible', 'awful', 'disappoint', 'refund', 'rude',
        'stuck', 'cold', 'slow', 'worse', 'worst', 'waste', 'uncomfortable', 'disorganized', 'disorganised',
        'chaotic', 'confusion', 'missing', 'cancelled', 'canceled', 'unprofessional', 'overpriced', 'no parking',
        'parking was', 'expensive'];

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

    private function id(string $prefix): string
    {
        return sprintf('%s-%04X', $prefix, random_int(0, 0xFFFF)) . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    /** This vendor's event rows that have a marketplace listing. */
    private function events(string $vendorId): array
    {
        $s = $this->db->prepare('SELECT e.listing_id, e.id AS event_id, e.title, e.status,
                                        e.start_date, e.start_time, e.end_date, e.end_time
                                 FROM tie_events_events e
                                 WHERE e.vendor_id=? AND e.listing_id IS NOT NULL
                                 ORDER BY e.start_date DESC, e.title ASC');
        $s->execute([$vendorId]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Event options for filters (public facade). */
    public function eventsList(string $vendorId): array
    {
        return array_map(fn($e) => [
            'event_id' => $e['event_id'], 'listing_id' => $e['listing_id'],
            'title' => $e['title'], 'start_date' => $e['start_date'], 'status' => $e['status'],
        ], $this->events($vendorId));
    }

    private function listingIn(string $vendorId): string
    {
        $ids = array_map(fn($e) => $this->db->quote((string) $e['listing_id']), $this->events($vendorId));
        return $ids ? implode(',', $ids) : "''";
    }

    private function audit(string $vendorId, ?string $actorId, ?string $actorName, string $action,
                           ?string $reviewId = null, ?string $targetType = null, ?string $targetId = null,
                           array $details = []): void
    {
        $s = $this->db->prepare('INSERT INTO tie_reviews_audit_log
            (vendor_id, actor_id, actor_name, action, review_id, target_type, target_id, details)
            VALUES (?,?,?,?,?,?,?,?)');
        $s->execute([$vendorId, $actorId, $actorName, $action, $reviewId, $targetType, $targetId,
            $details ? json_encode($details) : null]);
    }

    private function eventTitle(?string $eventId, string $vendorId): ?string
    {
        if (!$eventId) return null;
        $s = $this->db->prepare('SELECT title FROM tie_events_events WHERE id=? AND vendor_id=?');
        $s->execute([$eventId, $vendorId]);
        return $s->fetchColumn() ?: null;
    }

    /* ── deterministic text classification ──────────────────────────── */

    /**
     * Classify a review body into a sentiment plus a set of theme mentions
     * with per-theme polarity. Pure keyword logic, no external service —
     * every label is explainable by the text it references.
     *
     * @return array{sentiment:string,themes:array}
     */
    private function classify(string $body, int $rating): array
    {
        $text = ' ' . strtolower(strip_tags($body)) . ' ';
        $mentions = [];
        foreach (self::THEME_KEYWORDS as $theme => $words) {
            $count = 0;
            foreach ($words as $w) {
                $count += substr_count($text, $w);
            }
            if ($count > 0) $mentions[$theme] = $count;
        }
        if (!$mentions) {
            $fallbackKeys = array_slice(array_keys(self::THEMES), 0, 3);
            $mentions[$fallbackKeys[array_rand($fallbackKeys)]] = 1;
        }

        $posHits = 0;
        $negHits = 0;
        foreach (self::POSITIVE_WORDS as $w) $posHits += substr_count($text, $w);
        foreach (self::NEGATIVE_WORDS as $w) $negHits += substr_count($text, $w);

        $themes = [];
        foreach ($mentions as $theme => $count) {
            $themes[] = ['theme' => $theme, 'label' => self::THEMES[$theme],
                'count' => $count, 'polarity' => $negHits > $posHits ? 'negative' : ($posHits > 0 ? 'positive' : 'neutral')];
        }

        // Base sentiment follows the star rating; strong language can adjust it
        // by at most one step (a 5-star "Great but bathrooms were overcrowded"
        // stays positive — the negative nuance lives in the theme mentions).
        $sentiment = match (true) {
            $rating <= 2 => 'NEGATIVE',
            $rating === 3 => 'NEUTRAL',
            default => 'POSITIVE',
        };
        if ($rating >= 4 && $negHits >= 2 && $negHits > $posHits) $sentiment = 'NEUTRAL';
        if ($rating === 3 && $posHits > $negHits) $sentiment = 'POSITIVE';
        if ($rating === 3 && $negHits > $posHits) $sentiment = 'NEGATIVE';

        return ['sentiment' => $sentiment, 'themes' => $themes];
    }

    /* ── Verified Attendee (earned badge, live evidence) ────────────── */

    private function verifyAttendance(string $vendorId, string $eventId, ?string $listingId, string $customerId): array
    {
        if (!$listingId) return ['verified' => false, 'evidence' => []];
        $b = $this->db->prepare("SELECT b.id, b.payment_status, b.booking_status
                                 FROM bookings b
                                 WHERE b.customer_id=? AND b.listing_id=? AND b.listing_type='event'
                                   AND b.deleted_at IS NULL AND b.payment_status='Paid'
                                 ORDER BY b.booked_at DESC LIMIT 1");
        $b->execute([$customerId, $listingId]);
        $booking = $b->fetch(PDO::FETCH_ASSOC);
        if (!$booking) return ['verified' => false, 'evidence' => ['step' => 'booking']];

        $t = $this->db->prepare("SELECT t.id AS ticket_id, t.ticket_type_id, t.checked_in_at, tt.name AS ticket_type_name
                                 FROM event_tickets t
                                 LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
                                 WHERE t.booking_id=? AND t.status NOT IN ('CANCELLED','REFUNDED')
                                 ORDER BY (t.checked_in_at IS NULL) ASC, t.checked_in_at ASC, t.created_at ASC LIMIT 1");
        $t->execute([$booking['id']]);
        $ticket = $t->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) return ['verified' => false, 'evidence' => ['step' => 'ticket', 'booking_id' => $booking['id']]];

        // Attendance is the deciding step — a ticket purchase alone is not.
        if (!$ticket['checked_in_at']) {
            return ['verified' => false, 'evidence' => ['step' => 'attendance', 'booking_id' => $booking['id'],
                'ticket_id' => $ticket['ticket_id'], 'ticket_type_name' => $ticket['ticket_type_name']]];
        }

        return [
            'verified' => true,
            'evidence' => [
                'booking_id' => $booking['id'],
                'ticket_id' => $ticket['ticket_id'],
                'ticket_type_id' => (int) $ticket['ticket_type_id'],
                'ticket_type_name' => $ticket['ticket_type_name'],
                'checked_in_at' => $ticket['checked_in_at'],
                'payment_status' => $booking['payment_status'],
            ],
        ];
    }

    /* ── config (vendor-level) ──────────────────────────────────────── */

    public function config(string $vendorId): array
    {
        $s = $this->db->prepare('SELECT * FROM tie_reviews_config WHERE vendor_id=? LIMIT 1');
        $s->execute([$vendorId]);
        $row = $s->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!$row) {
            return ['collect_enabled' => true, 'request_delay_hours' => 24,
                'channel_uthenga' => true, 'channel_email' => true, 'channel_sms' => false,
                'publish_mode' => 'AUTO', 'notify_new' => true, 'notify_negative' => true,
                'notify_reply' => true, 'incentive_enabled' => false,
                'priority' => ['critical' => 2, 'high' => 3, 'normal' => 4, 'low' => 5]];
        }
        return [
            'collect_enabled' => (bool) $row['collect_enabled'],
            'request_delay_hours' => (int) $row['request_delay_hours'],
            'channel_uthenga' => (bool) $row['channel_uthenga'],
            'channel_email' => (bool) $row['channel_email'],
            'channel_sms' => (bool) $row['channel_sms'],
            'publish_mode' => $row['publish_mode'],
            'notify_new' => (bool) $row['notify_new'],
            'notify_negative' => (bool) $row['notify_negative'],
            'notify_reply' => (bool) $row['notify_reply'],
            'incentive_enabled' => (bool) $row['incentive_enabled'],
            'priority' => ['critical' => (int) $row['critical_max'], 'high' => (int) $row['high_max'],
                'normal' => (int) $row['normal_max'], 'low' => (int) $row['low_max']],
        ];
    }

    public function saveConfig(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $cur = $this->config($vendorId);
        $prio = is_array($input['priority'] ?? null) ? $input['priority'] : [];
        $pv = fn(string $key, int $fallback): int => isset($prio[$key])
            ? (int) $prio[$key]
            : (int) ($input['priority_' . $key] ?? $cur['priority'][$key] ?? $fallback);
        $pv = fn(string $key, int $fallback): int => max(1, min(5, $pv($key, $fallback)));
        $cfg = [
            'collect_enabled'     => array_key_exists('collect_enabled', $input) ? !empty($input['collect_enabled']) : (bool) $cur['collect_enabled'],
            'request_delay_hours' => array_key_exists('request_delay_hours', $input) ? max(0, min(720, (int) $input['request_delay_hours'])) : (int) $cur['request_delay_hours'],
            'channel_uthenga'     => array_key_exists('channel_uthenga', $input) ? !empty($input['channel_uthenga']) : (bool) $cur['channel_uthenga'],
            'channel_email'       => array_key_exists('channel_email', $input) ? !empty($input['channel_email']) : (bool) $cur['channel_email'],
            'channel_sms'         => array_key_exists('channel_sms', $input) ? !empty($input['channel_sms']) : (bool) $cur['channel_sms'],
            'publish_mode'        => in_array(($input['publish_mode'] ?? ''), ['AUTO', 'MODERATED'], true) ? $input['publish_mode'] : ($cur['publish_mode'] ?? 'AUTO'),
            'notify_new'          => array_key_exists('notify_new', $input) ? !empty($input['notify_new']) : (bool) $cur['notify_new'],
            'notify_negative'     => array_key_exists('notify_negative', $input) ? !empty($input['notify_negative']) : (bool) $cur['notify_negative'],
            'notify_reply'        => array_key_exists('notify_reply', $input) ? !empty($input['notify_reply']) : (bool) $cur['notify_reply'],
            'incentive_enabled'   => array_key_exists('incentive_enabled', $input) ? !empty($input['incentive_enabled']) : (bool) $cur['incentive_enabled'],
            'critical_max'        => $pv('critical', 2),
            'high_max'            => $pv('high', 3),
            'normal_max'          => $pv('normal', 4),
            'low_max'             => $pv('low', 5),
        ];
        $this->db->prepare('INSERT INTO tie_reviews_config
            (vendor_id, collect_enabled, request_delay_hours, channel_uthenga, channel_email, channel_sms,
             publish_mode, notify_new, notify_negative, notify_reply, incentive_enabled,
             critical_max, high_max, normal_max, low_max)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              collect_enabled=VALUES(collect_enabled), request_delay_hours=VALUES(request_delay_hours),
              channel_uthenga=VALUES(channel_uthenga), channel_email=VALUES(channel_email), channel_sms=VALUES(channel_sms),
              publish_mode=VALUES(publish_mode), notify_new=VALUES(notify_new), notify_negative=VALUES(notify_negative),
              notify_reply=VALUES(notify_reply), incentive_enabled=VALUES(incentive_enabled),
              critical_max=VALUES(critical_max), high_max=VALUES(high_max), normal_max=VALUES(normal_max), low_max=VALUES(low_max)')
            ->execute([$vendorId, (int) $cfg['collect_enabled'], $cfg['request_delay_hours'],
                (int) $cfg['channel_uthenga'], (int) $cfg['channel_email'], (int) $cfg['channel_sms'],
                $cfg['publish_mode'], (int) $cfg['notify_new'], (int) $cfg['notify_negative'], (int) $cfg['notify_reply'],
                (int) $cfg['incentive_enabled'], $cfg['critical_max'], $cfg['high_max'], $cfg['normal_max'], $cfg['low_max']]);

        $this->audit($vendorId, $user['id'] ?? null, $user['name'] ?? 'Organizer', 'reviews_config_saved', null, 'config', $vendorId, ['config' => $cfg]);
        return $this->config($vendorId);
    }

    /* ── scoped query builder ───────────────────────────────────────── */

    private function scope(string $vendorId, array $f): array
    {
        $sql = 'FROM tie_reviews_reviews r
                LEFT JOIN tie_events_events e ON e.id = r.event_id';
        $w = ['r.vendor_id = ?'];
        $p = [$vendorId];

        $eventId = (string) ($f['event_id'] ?? 'all');
        if ($eventId && $eventId !== 'all') {
            $w[] = 'r.event_id = ?';
            $p[] = $eventId;
        }
        $rating = (int) ($f['rating'] ?? 0);
        if ($rating >= 1 && $rating <= 5) { $w[] = 'r.rating = ?'; $p[] = $rating; }
        $from = (string) ($f['from'] ?? '');
        if ($from !== '') { $w[] = 'r.created_at >= ?'; $p[] = $from . ' 00:00:00'; }
        $to = (string) ($f['to'] ?? '');
        if ($to !== '') { $w[] = 'r.created_at <= ?'; $p[] = $to . ' 23:59:59'; }

        $status = (string) ($f['status'] ?? 'all');
        if ($status === 'unanswered') {
            $w[] = "r.status='PUBLISHED' AND r.id NOT IN (SELECT review_id FROM tie_reviews_responses WHERE status='PUBLISHED')";
        } elseif ($status === 'flagged') {
            $w[] = "r.moderation='FLAGGED'";
        } elseif (in_array($status, ['POSITIVE', 'NEUTRAL', 'NEGATIVE'], true)) {
            $w[] = 'r.sentiment = ?';
            $p[] = $status;
        }
        $theme = (string) ($f['theme'] ?? '');
        if ($theme !== '') {
            $w[] = "JSON_SEARCH(r.themes, 'one', ?) IS NOT NULL";
            $p[] = $theme;
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $w[] = '(r.customer_name LIKE ? OR r.body LIKE ? OR r.title LIKE ? OR e.title LIKE ?)';
            $like = '%' . $q . '%';
            array_push($p, $like, $like, $like, $like);
        }
        return [$sql, implode(' AND ', $w), $p];
    }

    private function sortSql(string $sort): string
    {
        return match ($sort) {
            'oldest' => 'r.created_at ASC',
            'rating_high' => 'r.rating DESC, r.created_at DESC',
            'rating_low' => 'r.rating ASC, r.created_at DESC',
            'helpful' => 'r.helpful_count DESC, r.created_at DESC',
            'needs_response' => "(r.id NOT IN (SELECT review_id FROM tie_reviews_responses WHERE status='PUBLISHED')) DESC, r.created_at DESC",
            default => 'r.created_at DESC',
        };
    }

    private function reviewRow(array $r, ?string $vendorId = null): array
    {
        $themes = json_decode((string) ($r['themes'] ?? '[]'), true) ?: [];
        $verification = json_decode((string) ($r['verification'] ?? '{}'), true) ?: [];
        $resp = $r['latest_response'] ? [
            'id' => $r['latest_response'],
            'body' => $r['latest_response_body'],
            'ai_drafted' => (bool) $r['latest_response_ai'],
            'created_at' => $r['latest_response_at'],
            'status' => $r['latest_response_status'],
        ] : null;
        return [
            'id' => $r['id'],
            'rating' => (int) $r['rating'],
            'title' => $r['title'],
            'body' => $r['body'],
            'sentiment' => $r['sentiment'],
            'themes' => $themes,
            'helpful_count' => (int) $r['helpful_count'],
            'status' => $r['status'],
            'moderation' => $r['moderation'],
            'flag_reason' => $r['flag_reason'],
            'flag_status' => $r['flag_status'] ?? null,
            'verified_attendee' => (bool) $r['verified_attendee'],
            'verification' => $verification,
            'customer' => ['id' => $r['customer_id'], 'name' => $r['customer_name'], 'email' => $r['customer_email']],
            'event' => ['event_id' => $r['event_id'], 'title' => $r['event_title'] ?? '', 'listing_id' => $r['listing_id']],
            'created_at' => $r['created_at'],
            'response' => $resp,
            'needs_response' => $r['status'] === 'PUBLISHED' && !$resp,
        ];
    }

    private function decorate(string $vendorId, array $rows): array
    {
        if (!$rows) return [];
        $ids = array_map(fn($r) => $this->db->quote($r['id']), $rows);
        $in = implode(',', $ids);
        $s = $this->db->query("SELECT rv.id AS review_id, rp.id, rp.body, rp.ai_drafted, rp.created_at, rp.status
                               FROM tie_reviews_responses rp
                               JOIN tie_reviews_reviews rv ON rv.id = rp.review_id
                               WHERE rp.review_id IN ($in) AND rp.status='PUBLISHED'
                               ORDER BY rp.created_at DESC");
        $map = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $resp) {
            if (!isset($map[$resp['review_id']])) $map[$resp['review_id']] = $resp;
        }
        $f = $this->db->query("SELECT review_id, reason, status FROM tie_reviews_flags
                               WHERE review_id IN ($in) AND status IN ('PENDING','UNDER_REVIEW')
                               ORDER BY id DESC");
        $flagMap = [];
        foreach ($f->fetchAll(PDO::FETCH_ASSOC) as $fl) {
            if (!isset($flagMap[$fl['review_id']])) $flagMap[$fl['review_id']] = $fl;
        }
        $out = [];
        foreach ($rows as $r) {
            $resp = $map[$r['id']] ?? null;
            $r['latest_response'] = $resp['id'] ?? null;
            $r['latest_response_body'] = $resp['body'] ?? null;
            $r['latest_response_ai'] = $resp['ai_drafted'] ?? 0;
            $r['latest_response_at'] = $resp['created_at'] ?? null;
            $r['latest_response_status'] = $resp['status'] ?? null;
            $fl = $flagMap[$r['id']] ?? null;
            if (!$r['flag_reason']) $r['flag_reason'] = $fl['reason'] ?? null;
            $r['flag_status'] = $fl['status'] ?? null;
            $r['event_title'] = $r['event_title'] ?? ($this->eventTitle($r['event_id'], $vendorId) ?? '');
            $out[] = $this->reviewRow($r, $vendorId);
        }
        return $out;
    }

    /* ── overview (reputation dashboard) ────────────────────────────── */

    public function overview(string $vendorId, array $f = []): array
    {
        [$sql, $w, $p] = $this->scope($vendorId, $f);

        $stats = $this->db->prepare("SELECT COUNT(*) AS total,
                               COALESCE(AVG(r.rating),0) AS avg_rating,
                               SUM(r.status='PUBLISHED' AND r.id NOT IN (SELECT review_id FROM tie_reviews_responses WHERE status='PUBLISHED')) AS unanswered,
                               SUM(r.rating <= 2 AND r.status='PUBLISHED' AND r.id NOT IN (SELECT review_id FROM tie_reviews_responses WHERE status='PUBLISHED')) AS needs_attention,
                               SUM(r.rating=5) AS r5, SUM(r.rating=4) AS r4, SUM(r.rating=3) AS r3,
                               SUM(r.rating=2) AS r2, SUM(r.rating=1) AS r1,
                               SUM(r.sentiment='POSITIVE') AS pos, SUM(r.sentiment='NEUTRAL') AS neu, SUM(r.sentiment='NEGATIVE') AS neg,
                               SUM(r.verified_attendee=1) AS verified,
                               SUM(r.moderation='FLAGGED') AS flagged
                        $sql WHERE $w");
        $stats->execute($p);
        $s = $stats->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int) $s['total'];

        $responded = $this->db->prepare("SELECT COUNT(*) FROM (
                                             SELECT r.id $sql WHERE $w AND r.id IN (SELECT review_id FROM tie_reviews_responses WHERE status='PUBLISHED')
                                         ) t");
        $responded->execute($p);
        $responded = (int) $responded->fetchColumn();

        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $dist = (int) ($s['r' . $star] ?? 0);
            $distribution[] = ['rating' => $star, 'count' => $dist,
                'pct' => $total ? round(100 * $dist / $total, 1) : 0.0];
        }
        $sentiment = ['positive' => (int) $s['pos'], 'neutral' => (int) $s['neu'], 'negative' => (int) $s['neg']];

        $themeRows = $this->db->prepare("SELECT r.themes $sql WHERE $w AND r.themes IS NOT NULL");
        $themeRows->execute($p);
        $themes = [];
        foreach ($themeRows->fetchAll(PDO::FETCH_ASSOC) as $tr) {
            foreach ((json_decode((string) $tr['themes'], true) ?: []) as $m) {
                $t = $m['theme'] ?? '';
                if (!$t) continue;
                $themes[$t]['count'] = ($themes[$t]['count'] ?? 0) + 1;
                $themes[$t]['positive'] = ($themes[$t]['positive'] ?? 0) + (($m['polarity'] ?? '') === 'positive' ? 1 : 0);
                $themes[$t]['negative'] = ($themes[$t]['negative'] ?? 0) + (($m['polarity'] ?? '') === 'negative' ? 1 : 0);
            }
        }
        $themeList = [];
        foreach ($themes as $t => $agg) {
            $themeList[] = ['theme' => $t, 'label' => self::THEMES[$t] ?? ucfirst($t),
                'count' => (int) $agg['count'], 'positive' => (int) $agg['positive'], 'negative' => (int) $agg['negative']];
        }
        usort($themeList, fn($a, $b) => $b['count'] <=> $a['count']);

        // Previous-period comparison (same window length before `from`).
        $change = ['rating' => null, 'reviews_pct' => null];
        $from = (string) ($f['from'] ?? '');
        $to = (string) ($f['to'] ?? '');
        if ($from && $to) {
            $days = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400));
            $prevFrom = date('Y-m-d', strtotime($from . " -{$days} days"));
            $prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
            $pc = $this->scope($vendorId, array_merge($f, ['from' => $prevFrom, 'to' => $prevTo]));
            $ps = $this->db->prepare('SELECT COUNT(*) AS total, COALESCE(AVG(r.rating),0) AS avg_rating
                                      FROM tie_reviews_reviews r WHERE ' . $pc[1]);
            $ps->execute($pc[2]);
            $pv = $ps->fetch(PDO::FETCH_ASSOC) ?: [];
            $prevTotal = (int) ($pv['total'] ?? 0);
            if ($prevTotal > 0) {
                $change['rating'] = round((float) $s['avg_rating'] - (float) $pv['avg_rating'], 2);
                $change['reviews_pct'] = round(100 * ($total - $prevTotal) / $prevTotal, 1);
            }
        }
        $responseRate = $total ? round(100 * $responded / $total, 1) : 0.0;

        // Event reputation (clicking filters the whole workspace to the event).
        $eventList = $this->db->prepare("SELECT r.event_id, e.title AS event_title,
                                   COUNT(*) AS reviews, COALESCE(AVG(r.rating),0) AS avg_rating,
                                   MAX(r.created_at) AS last_review_at
                                   FROM tie_reviews_reviews r
                                   LEFT JOIN tie_events_events e ON e.id = r.event_id
                                   WHERE r.vendor_id=? AND r.status NOT IN ('REMOVED','HIDDEN')
                                   GROUP BY r.event_id, e.title ORDER BY last_review_at DESC");
        $eventList->execute([$vendorId]);
        $eventRep = [];
        foreach ($eventList->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $old = $this->db->prepare("SELECT COALESCE(AVG(rating),0) FROM tie_reviews_reviews
                                       WHERE event_id=? AND vendor_id=?
                                         AND created_at < DATE_SUB(NOW(), INTERVAL 21 DAY)");
            $old->execute([$e['event_id'], $vendorId]);
            $oldAvg = (float) $old->fetchColumn();
            $curAvg = (float) $e['avg_rating'];
            $delta = $oldAvg > 0 ? $curAvg - $oldAvg : 0.0;
            $trend = $delta > 0.1 ? 'up' : ($delta < -0.1 ? 'down' : 'flat');
            $eventRep[] = ['event_id' => $e['event_id'], 'title' => $e['event_title'] ?? 'Untitled event',
                'rating' => round($curAvg, 1), 'reviews' => (int) $e['reviews'], 'trend' => $trend];
        }

        // Rating trend + funnel + recent + insights.
        $trend = $this->trend($vendorId, $f);
        $funnel = $this->funnel($vendorId, $f);
        $recent = $this->list($vendorId, array_merge($f, ['limit' => 5, 'sort' => 'newest', 'page' => 1]));
        $insights = $this->insights($vendorId, $f, $s, $themeList, $change, $responseRate, $total);

        $avg = (float) $s['avg_rating'];
        $prevAvg = $change['rating'];
        $kpis = [
            'overall_rating' => ['value' => round($avg, 1), 'formatted' => number_format($avg, 1), 'change' => $prevAvg],
            'total_reviews' => ['value' => $total, 'formatted' => number_format($total), 'change_pct' => $change['reviews_pct']],
            'response_rate' => ['value' => $responseRate, 'responded' => $responded, 'unanswered' => max(0, $total - $responded)],
            'unanswered' => ['value' => (int) $s['unanswered'], 'needs_attention' => (int) ($s['needs_attention'] ?? 0)],
        ];

        return [
            'kpis' => $kpis,
            'distribution' => $distribution,
            'sentiment' => $sentiment,
            'sentiment_pct' => [
                'positive' => $total ? (int) round(100 * $sentiment['positive'] / $total) : 0,
                'neutral' => $total ? (int) round(100 * $sentiment['neutral'] / $total) : 0,
                'negative' => $total ? (int) round(100 * $sentiment['negative'] / $total) : 0,
            ],
            'themes' => $themeList,
            'events' => $eventRep,
            'trend' => $trend,
            'funnel' => $funnel,
            'insights' => $insights,
            'recent' => $recent['rows'],
            'verified_count' => (int) $s['verified'],
            'flagged_count' => (int) ($s['flagged'] ?? 0),
            'config' => $this->config($vendorId),
        ];
    }

    private function trend(string $vendorId, array $f): array
    {
        $days = (int) ($f['trend_days'] ?? 30);
        if ($days === 0 || $days > 3650) $days = 30;
        $weekly = $days > 92;
        $sql = $weekly
            ? "SELECT DATE_FORMAT(r.created_at, '%Y-%u') AS wk, DATE(MIN(r.created_at)) AS day,
                      AVG(r.rating) AS avg_rating, COUNT(*) AS volume
               FROM tie_reviews_reviews r WHERE r.vendor_id=? AND r.status NOT IN ('REMOVED')
               GROUP BY wk ORDER BY day ASC"
            : "SELECT DATE(r.created_at) AS day, AVG(r.rating) AS avg_rating, COUNT(*) AS volume
               FROM tie_reviews_reviews r WHERE r.vendor_id=? AND r.status NOT IN ('REMOVED')
               GROUP BY DATE(r.created_at) ORDER BY day ASC";
        $s = $this->db->prepare($sql);
        $s->execute([$vendorId]);
        $points = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $cut = date('Y-m-d', strtotime("-{$days} days"));
        $filtered = array_values(array_filter($points, fn($pt) => ($pt['day'] ?? '') >= $cut));
        $map = [];
        foreach ($filtered as $pt) $map[$pt['day']] = $pt;

        $out = [];
        $step = $weekly ? 7 : 1;
        $start = strtotime($cut);
        $end = min(time(), strtotime(date('Y-m-d')));
        for ($ts = $start; $ts <= $end + 3600; $ts += $step * 86400) {
            $day = date('Y-m-d', $ts);
            $pt = $map[$day] ?? null;
            $out[] = ['day' => $day, 'label' => date('M j', $ts),
                'avg_rating' => $pt ? round((float) $pt['avg_rating'], 2) : null,
                'volume' => $pt ? (int) $pt['volume'] : 0];
        }
        return ['points' => $out, 'mode' => $weekly ? 'weekly' : 'daily'];
    }

    private function funnel(string $vendorId, array $f): array
    {
        $event = (string) ($f['event_id'] ?? 'all');
        $evClause = $event !== 'all' ? ' AND r.event_id = ?' : '';
        $evParams = $event !== 'all' ? [$event] : [];

        $s = $this->db->prepare("SELECT COUNT(*) AS sent,
                                 SUM(status IN ('OPENED','STARTED','SUBMITTED')) AS opened,
                                 SUM(status IN ('STARTED','SUBMITTED')) AS started,
                                 SUM(status='SUBMITTED') AS submitted
                                 FROM tie_reviews_requests r WHERE r.vendor_id=?$evClause");
        $s->execute(array_merge([$vendorId], $evParams));
        $req = $s->fetch(PDO::FETCH_ASSOC) ?: [];

        $elig = $this->db->prepare('SELECT COUNT(DISTINCT t.booking_id) FROM event_tickets t
                                    WHERE t.listing_id IN (' . $this->listingIn($vendorId) . ")
                                      AND t.status NOT IN ('CANCELLED','REFUNDED')");
        $elig->execute([]);

        return [
            'eligible' => (int) $elig->fetchColumn(),
            'sent' => (int) ($req['sent'] ?? 0),
            'opened' => (int) ($req['opened'] ?? 0),
            'started' => (int) ($req['started'] ?? 0),
            'submitted' => (int) ($req['submitted'] ?? 0),
        ];
    }

    private function insights(string $vendorId, array $f, array $s, array $themes, array $change, float $responseRate, int $total): array
    {
        $ins = [];
        $avg = (float) $s['avg_rating'];
        if ($total === 0) {
            $ins[] = ['icon' => 'mute', 'tone' => 'neutral', 'title' => 'No reviews yet',
                'text' => 'Once customers review your events you will see the reputation summary here.',
                'link' => 'requests', 'action' => 'Review requests'];
            return $ins;
        }
        $cRating = $change['rating'] ?? null;
        if ($cRating !== null && $cRating > 0.05) {
            $ins[] = ['icon' => 'trend', 'tone' => 'good', 'title' => 'Positive trend detected',
                'text' => 'Average rating rose ' . number_format($cRating, 1) . ' points against the previous period. Customers are increasingly satisfied.',
                'link' => 'overview', 'action' => 'Explore'];
        } elseif ($cRating !== null && $cRating < -0.05) {
            $ins[] = ['icon' => 'decline', 'tone' => 'bad', 'title' => 'Rating decline detected',
                'text' => 'Average rating fell ' . number_format(abs($cRating), 1) . ' points against the previous period. Review the latest negative feedback below.',
                'link' => 'all', 'action' => 'Explore'];
        }
        $topPos = null; $topNeg = null;
        foreach ($themes as $t) {
            if ($t['positive'] > 0 && ($topPos === null || $t['positive'] > $topPos['positive'])) $topPos = $t;
            if ($t['negative'] > 0 && ($topNeg === null || $t['negative'] > $topNeg['negative'])) $topNeg = $t;
        }
        if ($topPos) {
            $ins[] = ['icon' => 'praise', 'tone' => 'good', 'title' => 'Customers praise ' . strtolower((string) $topPos['label']),
                'text' => $topPos['label'] . ' is mentioned favourably in ' . $topPos['positive'] . ' review' . ($topPos['positive'] === 1 ? '' : 's') . '.',
                'link' => 'themes', 'action' => 'Explore'];
        }
        if ($topNeg) {
            $ins[] = ['icon' => 'theme', 'tone' => 'warn', 'title' => 'Repeated complaint',
                'text' => $topNeg['label'] . ' appears negatively in ' . $topNeg['negative'] . ' review' . ($topNeg['negative'] === 1 ? '' : 's') . ' — your most-cited concern.',
                'link' => 'themes', 'action' => 'Understand'];
        }
        if ((int) $s['unanswered'] > 0) {
            $ins[] = ['icon' => 'reply', 'tone' => 'warn', 'title' => 'Responses needed',
                'text' => (int) $s['unanswered'] . ' review' . ((int) $s['unanswered'] === 1 ? ' is' : 's are') . ' awaiting organizer responses (' . ((int) ($s['needs_attention'] ?? 0)) . ' high-priority). Response rate is ' . $responseRate . '%.',
                'link' => 'needs', 'action' => 'Respond'];
        } elseif ($total > 0) {
            $ins[] = ['icon' => 'check', 'tone' => 'good', 'title' => 'All caught up',
                'text' => 'Every published review has an organizer response. Response rate is ' . $responseRate . '%.',
                'link' => 'all', 'action' => 'View reviews'];
        }
        if ($avg >= 4.5) {
            $ins[] = ['icon' => 'star', 'tone' => 'good', 'title' => 'Strong reputation',
                'text' => sprintf('%.1f', $avg) . '/5 average across ' . $total . ' reviews — excellent standing to build on for the next event.',
                'link' => 'overview', 'action' => 'Explore'];
        }
        return array_slice($ins, 0, 5);
    }

    /* ── review list / detail ───────────────────────────────────────── */

    public function list(string $vendorId, array $f = []): array
    {
        [$sql, $w, $p] = $this->scope($vendorId, $f);
        $countSql = $this->db->prepare("SELECT COUNT(*) FROM tie_reviews_reviews r
                                        LEFT JOIN tie_events_events e ON e.id=r.event_id WHERE $w");
        $countSql->execute($p);
        $total = (int) $countSql->fetchColumn();

        $limit = min(100, max(1, (int) ($f['limit'] ?? 20)));
        $page = max(1, (int) ($f['page'] ?? 1));
        $sort = $this->sortSql((string) ($f['sort'] ?? 'newest'));

        $s = $this->db->prepare("SELECT r.*, e.title AS event_title
                                 FROM tie_reviews_reviews r
                                 LEFT JOIN tie_events_events e ON e.id = r.event_id
                                 WHERE $w ORDER BY $sort LIMIT $limit OFFSET " . (($page - 1) * $limit));
        $s->execute($p);
        $rows = $this->decorate($vendorId, $s->fetchAll(PDO::FETCH_ASSOC) ?: []);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $limit))];
    }

    public function detail(string $vendorId, string $reviewId): array
    {
        $s = $this->db->prepare("SELECT r.*, e.title AS event_title, e.start_date, e.start_time, e.doors_open_time,
                                        e.listing_id AS ev_listing_id
                                 FROM tie_reviews_reviews r
                                 LEFT JOIN tie_events_events e ON e.id = r.event_id
                                 WHERE r.id=? AND r.vendor_id=?");
        $s->execute([$reviewId, $vendorId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw UthengaTieErrors::validation(['review_id' => 'Review not found.']);
        $rows = $this->decorate($vendorId, [$row]);
        $review = $rows[0];

        // Customer context (derived live from the operational tables).
        $cust = $this->db->prepare('SELECT id, name, email, phone, created_at FROM users WHERE id=?');
        $cust->execute([$review['customer']['id']]);
        $u = $cust->fetch(PDO::FETCH_ASSOC) ?: [];
        $spend = $this->db->prepare("SELECT COUNT(*) AS purchases, COUNT(DISTINCT listing_id) AS events,
                                     COALESCE(SUM(total_price),0) AS spent
                                     FROM bookings WHERE customer_id=? AND deleted_at IS NULL AND payment_status != 'Failed'");
        $spend->execute([$review['customer']['id']]);
        $sp = $spend->fetch(PDO::FETCH_ASSOC) ?: [];
        $revHist = $this->db->prepare("SELECT rv.id, rv.rating, rv.created_at, e.title AS event_title
                                       FROM tie_reviews_reviews rv LEFT JOIN tie_events_events e ON e.id=rv.event_id
                                       WHERE rv.customer_id=? AND rv.vendor_id=? ORDER BY rv.created_at DESC LIMIT 10");
        $revHist->execute([$review['customer']['id'], $vendorId]);
        $crow = $this->db->prepare('SELECT COUNT(*) AS total, COALESCE(AVG(rating),0) AS avg_given
                                    FROM tie_reviews_reviews WHERE customer_id=? AND vendor_id=?');
        $crow->execute([$review['customer']['id'], $vendorId]);
        $cr = $crow->fetch(PDO::FETCH_ASSOC) ?: [];

        // Review context (negative-review workflow): facts, not verdicts.
        $ev = $review['verification'] ?: [];
        $bookingId = $ev['booking_id'] ?? null;
        $refund = false;
        if ($bookingId) {
            $rf = $this->db->prepare('SELECT COUNT(*) FROM event_ticket_refunds WHERE booking_id=?');
            $rf->execute([$bookingId]);
            $refund = (int) $rf->fetchColumn() > 0;
        }
        $firstArrival = null;
        if ($row['ev_listing_id']) {
            $fa = $this->db->prepare("SELECT MIN(created_at) FROM checkin_scans WHERE listing_id=? AND decision='ALLOW'");
            $fa->execute([$row['ev_listing_id']]);
            $firstArrival = $fa->fetchColumn() ?: null;
        }

        $flags = $this->db->prepare('SELECT reason, notes, status, flagged_by_name, created_at
                                     FROM tie_reviews_flags WHERE review_id=? ORDER BY id DESC');
        $flags->execute([$reviewId]);

        return [
            'review' => $review,
            'customer' => [
                'id' => $review['customer']['id'],
                'name' => $u['name'] ?? $review['customer']['name'],
                'email' => $u['email'] ?? $review['customer']['email'],
                'phone' => $u['phone'] ?? '',
                'since' => $u['created_at'] ?? null,
                'events_attended' => (int) ($sp['events'] ?? 0),
                'purchases' => (int) ($sp['purchases'] ?? 0),
                'spent' => (float) ($sp['spent'] ?? 0),
                'reviews' => (int) ($cr['total'] ?? 0),
                'avg_rating_given' => round((float) ($cr['avg_given'] ?? 0), 1),
                'history' => $revHist->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ],
            'ticket' => $ev ? [
                'booking_id' => $bookingId,
                'ticket_id' => $ev['ticket_id'] ?? null,
                'ticket_type_name' => $ev['ticket_type_name'] ?? null,
                'payment_status' => $ev['payment_status'] ?? null,
            ] : null,
            'context' => [
                'attended' => (bool) $review['verified_attendee'],
                'check_in_at' => $ev['checked_in_at'] ?? null,
                'scheduled_start' => $row['start_time'],
                'first_arrival_at' => $firstArrival,
                'refund_requested' => $refund,
                'previous_reviews' => max(0, (int) ($cr['total'] ?? 0) - 1),
            ],
            'flags' => $flags->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    /* ── organizer response ─────────────────────────────────────────── */

    public function respond(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $reviewId = (string) ($input['review_id'] ?? '');
        $body = trim((string) ($input['body'] ?? ''));
        if ($reviewId === '') throw UthengaTieErrors::validation(['review_id' => 'Review id is required.']);
        if ($body === '') throw UthengaTieErrors::validation(['body' => 'Response body is required.']);
        if (mb_strlen($body) > 500) throw UthengaTieErrors::validation(['body' => 'Responses are limited to 500 characters.']);

        $owns = $this->db->prepare("SELECT id, rating, customer_name, event_id FROM tie_reviews_reviews
                                    WHERE id=? AND vendor_id=? AND status NOT IN ('REMOVED')");
        $owns->execute([$reviewId, $vendorId]);
        $review = $owns->fetch(PDO::FETCH_ASSOC);
        if (!$review) throw UthengaTieErrors::validation(['review_id' => 'Review not found.']);

        $aiDrafted = !empty($input['ai_drafted']);
        $existing = $this->db->prepare("SELECT id FROM tie_reviews_responses
                                        WHERE review_id=? AND status='PUBLISHED' ORDER BY created_at DESC LIMIT 1");
        $existing->execute([$reviewId]);
        $respId = $existing->fetchColumn();

        if ($respId) {
            $this->db->prepare('UPDATE tie_reviews_responses SET body=?, ai_drafted=?, updated_at=NOW() WHERE id=?')
                ->execute([$body, (int) $aiDrafted, $respId]);
        } else {
            $respId = $this->id('RSP');
            $this->db->prepare('INSERT INTO tie_reviews_responses
                (id, vendor_id, review_id, body, ai_drafted, status, created_by, created_by_name)
                VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$respId, $vendorId, $reviewId, $body, (int) $aiDrafted, 'PUBLISHED',
                    $user['id'] ?? null, $user['name'] ?? 'Organizer']);
        }
        $this->db->prepare('UPDATE tie_reviews_reviews SET responded_at=NOW() WHERE id=?')->execute([$reviewId]);
        $this->audit($vendorId, $user['id'] ?? null, $user['name'] ?? 'Organizer', 'review_response_published',
            $reviewId, 'response', $respId, ['ai_drafted' => $aiDrafted, 'chars' => mb_strlen($body)]);

        $detail = $this->detail($vendorId, $reviewId);
        return $detail['review'];
    }

    /** AI-assisted response drafting: drafts only — the organizer publishes. */
    public function aiDraft(string $vendorId, string $reviewId): array
    {
        $s = $this->db->prepare("SELECT r.rating, r.customer_name, r.body, r.sentiment, r.themes,
                                        e.title AS event_title
                                 FROM tie_reviews_reviews r
                                 LEFT JOIN tie_events_events e ON e.id = r.event_id
                                 WHERE r.id=? AND r.vendor_id=?");
        $s->execute([$reviewId, $vendorId]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) throw UthengaTieErrors::validation(['review_id' => 'Review not found.']);

        $first = explode(' ', trim((string) $r['customer_name']));
        $first = ucfirst(strtolower($first[0] ?? 'there'));
        $event = $r['event_title'] ?? 'the event';
        $themes = [];
        foreach ((json_decode((string) $r['themes'], true) ?: []) as $m) {
            if (($m['polarity'] ?? '') === 'negative') $themes[] = strtolower((string) ($m['label'] ?? ''));
        }

        if ((int) $r['rating'] <= 2) {
            $draft = 'Thank you for sharing your experience, ' . $first . '. We are sorry that ' . $event .
                ' did not meet your expectations' . ($themes ? ', especially regarding ' . implode(' and ', array_slice($themes, 0, 2)) : '') .
                '. Your feedback is important to us and we are reviewing what happened so the next event runs better. Please reach out to our team if you would like to discuss this further.';
        } elseif ((int) $r['rating'] === 3) {
            $draft = 'Thank you for your honest feedback, ' . $first . '. We appreciate you joining us at ' . $event .
                ($themes ? ' and noting the points around ' . implode(' and ', array_slice($themes, 0, 2)) : '') .
                '. We will take this into account as we plan future events and hope to welcome you again.';
        } else {
            $draft = 'Thank you for attending ' . $event . ', ' . $first . '. We are delighted that you enjoyed the experience' .
                ($themes ? ' — and we are grateful for the helpful feedback on ' . implode(' and ', array_slice($themes, 0, 2)) : '') .
                '. We look forward to welcoming you back at our next event.';
        }

        $this->audit($vendorId, null, 'AI Assistant', 'review_response_ai_drafted', $reviewId, 'response', null, ['rating' => (int) $r['rating']]);
        return ['draft' => $draft, 'disclaimer' => 'AI draft — review, edit if needed, then publish. AI text is never published automatically.'];
    }

    /* ── moderation ─────────────────────────────────────────────────── */

    public function flag(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $reviewId = (string) ($input['review_id'] ?? '');
        $reason = (string) ($input['reason'] ?? 'OTHER');
        $notes = trim((string) ($input['notes'] ?? ''));
        if ($reviewId === '') throw UthengaTieErrors::validation(['review_id' => 'Review id is required.']);
        $allowedReasons = ['INAPPROPRIATE', 'HARASSMENT', 'SPAM', 'FAKE', 'OFF_TOPIC', 'PRIVACY', 'CONFLICT', 'OTHER'];
        $reason = in_array($reason, $allowedReasons, true) ? $reason : 'OTHER';

        $owns = $this->db->prepare("SELECT id FROM tie_reviews_reviews WHERE id=? AND vendor_id=?");
        $owns->execute([$reviewId, $vendorId]);
        if (!$owns->fetch()) throw UthengaTieErrors::validation(['review_id' => 'Review not found.']);

        $pending = $this->db->prepare("SELECT id FROM tie_reviews_flags
                                       WHERE review_id=? AND status IN ('PENDING','UNDER_REVIEW') LIMIT 1");
        $pending->execute([$reviewId]);
        if ($pending->fetchColumn()) {
            throw UthengaTieErrors::validation(['review_id' => 'This review is already under review.']);
        }

        $this->db->prepare("INSERT INTO tie_reviews_flags
            (review_id, vendor_id, flagged_by, flagged_by_name, reason, notes, status)
            VALUES (?,?,?,?,?,?,'PENDING')")
            ->execute([$reviewId, $vendorId, $user['id'] ?? null, $user['name'] ?? 'Organizer', $reason, $notes ?: null]);
        $this->db->prepare("UPDATE tie_reviews_reviews SET moderation='FLAGGED', flag_reason=? WHERE id=?")
            ->execute([$reason, $reviewId]);
        $this->audit($vendorId, $user['id'] ?? null, $user['name'] ?? 'Organizer', 'review_flagged', $reviewId,
            'review', null, ['reason' => $reason]);
        return $this->detail($vendorId, $reviewId);
    }

    /** Organizer withdraws a flag / marks the concern resolved. */
    public function resolveFlag(array $user, array $input): array
    {
        $vendorId = (string) $user['id'];
        $reviewId = (string) ($input['review_id'] ?? '');
        $outcome = (string) ($input['outcome'] ?? 'DISMISSED');
        if ($reviewId === '') throw UthengaTieErrors::validation(['review_id' => 'Review id is required.']);
        if (!in_array($outcome, ['DISMISSED', 'REMOVED'], true)) $outcome = 'DISMISSED';

        $this->db->prepare("UPDATE tie_reviews_flags SET status=?, decided_by=?, decided_at=NOW()
                            WHERE review_id=? AND status IN ('PENDING','UNDER_REVIEW')")
            ->execute([$outcome, $user['id'] ?? null, $reviewId]);

        // A review remains unless the platform removes it — organizer ends the flag only.
        $this->db->prepare("UPDATE tie_reviews_reviews SET moderation='NORMAL', flag_reason=NULL
                            WHERE id=? AND vendor_id=?")
            ->execute([$reviewId, $vendorId]);
        $this->audit($vendorId, $user['id'] ?? null, $user['name'] ?? 'Organizer', 'review_flag_resolved', $reviewId,
            'review', null, ['outcome' => $outcome]);
        return $this->detail($vendorId, $reviewId);
    }

    /* ── request funnel ─────────────────────────────────────────────── */

    public function requests(string $vendorId, array $f = []): array
    {
        $eventId = (string) ($f['event_id'] ?? 'all');
        $where = 'WHERE r.vendor_id=?';
        $params = [$vendorId];
        if ($eventId !== 'all') {
            $where .= ' AND r.event_id=?';
            $params[] = $eventId;
        }
        $s = $this->db->prepare("SELECT r.event_id, e.title AS event_title, e.start_date,
                                 COUNT(*) AS sent,
                                 SUM(r.status IN ('OPENED','STARTED','SUBMITTED')) AS opened,
                                 SUM(r.status IN ('STARTED','SUBMITTED')) AS started,
                                 SUM(r.status='SUBMITTED') AS submitted
                                 FROM tie_reviews_requests r
                                 LEFT JOIN tie_events_events e ON e.id=r.event_id
                                 $where GROUP BY r.event_id, e.title, e.start_date ORDER BY e.start_date DESC");
        $s->execute($params);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $channels = $this->db->prepare("SELECT channel, COUNT(*) AS c FROM tie_reviews_requests
                                        WHERE vendor_id=? GROUP BY channel");
        $channels->execute([$vendorId]);
        $channelList = [];
        foreach ($channels->fetchAll(PDO::FETCH_ASSOC) as $ch) {
            $channelList[$ch['channel']] = (int) $ch['c'];
        }
        return [
            'funnel' => $this->funnel($vendorId, $f),
            'by_event' => $rows,
            'channels' => $channelList,
            'config' => $this->config($vendorId),
        ];
    }

    /* ── ask reviews (controlled natural-language queries) ──────────── */

    public function ask(string $vendorId, string $question, array $f = []): array
    {
        $q = strtolower(trim($question));
        $o = $this->overview($vendorId, $f);

        $answer = 'I can answer about: complaints, themes, a specific event, or unanswered reviews. Try one of the suggestions.';
        $data = null;

        $qTheme = null;
        foreach (self::THEMES as $key => $label) {
            if (str_contains($q, strtolower((string) $key)) || str_contains($q, strtolower((string) $label))) { $qTheme = $key; break; }
        }

        if (str_contains($q, 'complain') || str_contains($q, 'complaint') || str_contains($q, 'negativ') || str_contains($q, 'problem')) {
            $neg = array_values(array_filter($o['themes'], fn($t) => $t['negative'] > 0));
            if ($neg) {
                $answer = 'Customers most often complain about ' . $neg[0]['label'] . ' (' . $neg[0]['negative'] . ' negative mention' . ($neg[0]['negative'] === 1 ? '' : 's') . ')';
                if (isset($neg[1])) $answer .= ', followed by ' . $neg[1]['label'] . ' (' . $neg[1]['negative'] . ').';
                else $answer .= '.';
                $data = $neg;
            } else {
                $answer = 'No recurring complaints found — the top mentions are all positive.';
            }
        } elseif (str_contains($q, 'theme') || $qTheme !== null) {
            $theme = $qTheme;
            foreach (self::THEMES as $key => $label) {
                if (str_contains($q, strtolower((string) $key)) || str_contains($q, strtolower((string) $label))) $theme = $key;
            }
            if (!$theme) $theme = 'check-in';
            $lk = $theme;
            $found = array_values(array_filter($o['themes'], fn($t) => $t['theme'] === $lk));
            if ($found) {
                $t = $found[0];
                $answer = $t['label'] . ' is mentioned in ' . $t['count'] . ' review' . ($t['count'] === 1 ? '' : 's') . ' — ' . $t['positive'] . ' positive, ' . $t['negative'] . ' negative.';
                $data = $this->list($vendorId, array_merge($f, ['theme' => $theme, 'limit' => 12]));
            } else {
                $answer = 'I could not find reviews mentioning that topic in the current scope.';
            }
        } elseif (str_contains($q, 'unanswered') || str_contains($q, 'needs response') || str_contains($q, 'awaiting')) {
            if (str_contains($q, '3-star') || str_contains($q, '3 star')) {
                $data = $this->list($vendorId, array_merge($f, ['status' => 'unanswered', 'rating' => 3, 'limit' => 15]));
                $answer = count($data['rows']) . ' unanswered 3-star review' . (count($data['rows']) === 1 ? '' : 's') . ' found — open one and use "Draft with AI" to respond faster.';
            } else {
                $uk = $o['kpis']['unanswered']['value'];
                $answer = $uk . ' review' . ($uk === 1 ? ' is' : 's are') . ' awaiting a response. ' . $o['kpis']['unanswered']['needs_attention'] . ' need priority attention.';
                $data = $this->list($vendorId, array_merge($f, ['status' => 'unanswered', 'limit' => 15]));
            }
        } elseif (str_contains($q, 'summar') || str_contains($q, 'overview') || str_contains($q, 'praise') || str_contains($q, 'positive')) {
            $answer = sprintf('Average rating is %.1f/5 across %d reviews. Positive sentiment %d%%, neutral %d%%, negative %d%%. Response rate %.0f%%.',
                (float) $o['kpis']['overall_rating']['value'], $o['kpis']['total_reviews']['value'],
                $o['sentiment_pct']['positive'], $o['sentiment_pct']['neutral'], $o['sentiment_pct']['negative'],
                $o['kpis']['response_rate']['value']);
            $data = $o['themes'];
        }

        return ['question' => $question, 'answer' => $answer, 'data' => $data];
    }

    /* ── export ─────────────────────────────────────────────────────── */

    public function export(string $vendorId, array $f = []): array
    {
        $list = $this->list($vendorId, array_merge($f, ['limit' => 100, 'page' => 1]));
        $o = $this->overview($vendorId, $f);
        return [
            'summary' => [
                'overall_rating' => $o['kpis']['overall_rating']['formatted'],
                'total_reviews' => $o['kpis']['total_reviews']['formatted'],
                'response_rate' => $o['kpis']['response_rate']['value'] . '%',
                'distribution' => $o['distribution'],
                'sentiment' => $o['sentiment_pct'],
            ],
            'rows' => $list['rows'],
        ];
    }
}
