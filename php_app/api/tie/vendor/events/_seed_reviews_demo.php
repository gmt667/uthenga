<?php
/**
 * Uthenga — Reviews intelligence demo data seed (one-time, idempotent).
 *
 * Generates realistic customer reviews for the demo vendor's past events so
 * the Events Control Center → Reviews workspace shows live data:
 *   * ratings weighted toward positive (68/21/7/2/2 for 5..1 stars),
 *   * bodies written against the deterministic theme/keyword classifier
 *     (venue, organization, check-in, entertainment, food_beverage,
 *     transport, pricing),
 *   * Verified Attendee ONLY when a real paid booking + checked-in ticket
 *     exists — a purchase alone is not attendance,
 *   * an organizer response for ~92% of published reviews, leaving the
 *     high-priority 1–2 star ones intentionally unanswered,
 *   * a review-request funnel (SENT → OPENED → STARTED → SUBMITTED) for the
 *     eligible attendees, plus a vendor config row and a couple of flags.
 *
 * Run: /opt/lampp/bin/php api/tie/vendor/events/_seed_reviews_demo.php
 */
$db = new PDO(
    'mysql:unix_socket=/opt/lampp/var/mysql/mysql.sock;dbname=uthenga-db;charset=utf8mb4',
    'uthenga_user',
    'uthenga@646',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function rev_val(PDO $db, string $sql, array $params = []): mixed
{
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

// ── target vendor: the one owning the most event listings ────────────────────
$vendorId = rev_val($db, "SELECT l.vendor_id FROM listings l
                          JOIN tie_events_events e ON e.listing_id = l.id
                          WHERE l.listing_type='event' AND l.is_active=1
                          GROUP BY l.vendor_id ORDER BY COUNT(*) DESC LIMIT 1");
if (!$vendorId) { echo "No event listings found.\n"; exit; }
$vendorName = rev_val($db, 'SELECT name FROM users WHERE id=?', [$vendorId]);

$existing = rev_val($db, "SELECT COUNT(*) FROM tie_reviews_reviews WHERE vendor_id=?", [$vendorId]);
if ((int) $existing > 0) { echo "vendor {$vendorName} ({$vendorId}) already has {$existing} reviews — skipping.\n"; exit; }

// ── events eligible for reviews: completed/archived/published with tickets ──
$events = $db->query("
    SELECT l.id AS listing_id, l.title AS listing_title, e.id AS event_id, e.title, e.start_date
    FROM tie_events_events e
    JOIN listings l ON l.id = e.listing_id
    JOIN (SELECT DISTINCT listing_id FROM event_tickets) t ON t.listing_id = l.id
    WHERE e.vendor_id = " . $db->quote($vendorId) . "
      AND e.status IN ('COMPLETED','ARCHIVED','PUBLISHED')
    ORDER BY e.start_date ASC
")->fetchAll();
if (!$events) { echo "No events with tickets found for vendor {$vendorId}.\n"; exit; }

// ── builder pools ────────────────────────────────────────────────────────────
$positive = [
    [5, 'Well organized from start to finish', 'The event was very well organized. Check-in was quick and the performances started on time. Great atmosphere all evening.'],
    [5, 'Smooth check-in and great vibe', 'Seamless entry and friendly staff at the gate. The entertainment was top notch and the venue sound was excellent.'],
    [5, 'Best event of the season', 'Everything ran on time and the organization was impressive. The venue atmosphere was amazing and the music lineup was fantastic.'],
    [5, 'Quick entry, amazing show', 'We arrived and were inside within minutes thanks to the efficient ticketing and scanning. The performance was incredible and the staff were helpful.'],
    [4, 'Great event, minor queue at the bar', 'Overall excellent event. Check-in was fast and the entertainment was great. The only small issue was the queue at the drinks bar.'],
    [5, 'Perfect family afternoon', 'Very well organized event with friendly volunteers everywhere. Clean venue and comfortable seats. The food and drinks were good, water was easy to find.'],
    [4, 'Very good, would attend again', 'Good organization and professional staff. The acoustics were great for the speakers. Food was decent, value was fair for the price.'],
    [5, 'Fantastic crowd and atmosphere', 'Amazing atmosphere, excellent entertainment and a well run schedule. Best value event we have attended this year.'],
    [4, 'Enjoyed it', 'Great lineup and a fun crowd. Check-in was smooth. Slight wait for refreshments but otherwise a wonderful experience.'],
    [5, 'Professional and fun', 'You can tell the planning was thorough. Everything from ticketing to entry to the show itself ran beautifully. Highly recommend.'],
    [4, 'Good show, warm venue', 'The music and speakers were wonderful. The venue felt a bit warm at times but the organization was excellent.'],
    [5, 'Loved every minute', 'Perfect event. Professional staff, quick scanning, great entertainment and a lively atmosphere. We are already planning the next one.'],
];
$positive[] = [4, 'Great event, but the bathrooms were overcrowded', 'Great event, but the bathrooms were overcrowded. The check-in and organization were excellent though, and the music was amazing.']; // 4★ with negative nuance

$mixed = [
    [3, 'Decent event with room to improve', 'The event started about 40 minutes late which was disappointing. When it got going the entertainment was good and organization was okay.'],
    [3, 'Okay experience', 'Queues at entry took longer than expected but the staff handled it professionally. The show was good but parking was difficult.'],
    [3, 'Average', 'The lineup was decent and the atmosphere was fine, but the check-in process was slow and the bar ran out of drinks early.'],
    [3, 'Long wait at the gate', 'We queued for almost an hour at the gate. The scanning equipment seemed slow. The event itself was enjoyable once we got in.'],
    [3, 'Good music, messy parking', 'The music was excellent and the venue atmosphere was lively. Parking and traffic around the venue were chaotic though.'],
];

$negative = [
    [2, 'Started two hours late', 'The event started two hours late with no announcement. The organization was poor and we almost left. The music was good when it finally started.'],
    [1, 'Terrible wait, bad entry', 'Chaotic check-in, massive queues and rude security at the gate. We missed the opening act. Very disappointed with the organization.'],
    [2, 'Poor parking and long queues', 'Parking was terrible and the queue at entry stretched for blocks. Cold and disorganized. Not worth the price.'],
    [1, 'Waste of money', 'The event was cancelled late and communication was confusing. Terrible experience, requested a refund and still no response.'],
    [2, 'Underwhelming', 'The show was delayed and the venue was overcrowded and uncomfortable. Staff were unhelpful.'],
];

$insightsPool = [
    [5, 'Excellent organization, will return', 'The event was very well organized. Check-in was quick and the performances started on time.'],
    [5, 'Great vibe', 'Loved the atmosphere and the venue sound was clear. Staff were friendly and helpful.'],
];

// ── customers: only those with bookings at these listings ───────────────────
$customerMap = [];
foreach ($events as $e) {
    $st = $db->prepare("SELECT DISTINCT b.customer_id AS id, b.customer_name AS name, b.customer_email AS email
                        FROM bookings b
                        WHERE b.listing_id=? AND b.payment_status='Paid' AND b.deleted_at IS NULL
                          AND b.customer_id IN (SELECT id FROM users WHERE role='Customer')");
    $st->execute([$e['listing_id']]);
    foreach ($st->fetchAll() as $c) $customerMap[$c['id']] = $c;
}
$customerMap = array_values($customerMap);
if (!$customerMap) { echo "No event customers found.\n"; exit; }

// Precompute checked-in vs purchased-only for the verification nuance.
$checkedIn = [];
foreach ($customerMap as $c) {
    $st = $db->prepare("SELECT COUNT(*) FROM bookings b
                        JOIN event_tickets t ON t.booking_id = b.id
                        WHERE b.customer_id=? AND t.checked_in_at IS NOT NULL");
    $st->execute([$c['id']]);
    if ((int) $st->fetchColumn() > 0) $checkedIn[$c['id']] = true;
}

// ── helpers ─────────────────────────────────────────────────────────────────
$checkinBy = [];
function rev_checkin(PDO $db, array &$checkinBy, string $customerId, string $listingId): ?array
{
    if (isset($checkinBy[$customerId . '|' . $listingId])) return $checkinBy[$customerId . '|' . $listingId];
    $st = $db->prepare("SELECT b.id AS booking_id, t.id AS ticket_id, t.ticket_type_id, t.checked_in_at, tt.name AS ticket_type_name
                        FROM bookings b
                        JOIN event_tickets t ON t.booking_id = b.id
                        LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
                        WHERE b.customer_id=? AND b.listing_id=? AND b.payment_status='Paid'
                          AND t.status NOT IN ('CANCELLED','REFUNDED')
                        ORDER BY (t.checked_in_at IS NULL) ASC, t.checked_in_at ASC, t.created_at ASC LIMIT 1");
    $st->execute([$customerId, $listingId]);
    $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    $checkinBy[$customerId . '|' . $listingId] = $row;
    return $row;
}

function rev_insert(PDO $db, array $rev, string $vendorId): string
{
    $db->prepare("INSERT INTO tie_reviews_reviews
        (id, vendor_id, event_id, listing_id, customer_id, customer_name, customer_email,
         rating, title, body, sentiment, themes, verified_attendee, verification,
         helpful_count, request_opened_at, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$rev['id'], $vendorId, $rev['event_id'], $rev['listing_id'],
            $rev['customer_id'], $rev['customer_name'], $rev['customer_email'],
            $rev['rating'], $rev['title'], $rev['body'], $rev['sentiment'],
            json_encode($rev['themes'], JSON_UNESCAPED_UNICODE), (int) $rev['verified'],
            $rev['verification'] ? json_encode($rev['verification']) : null,
            $rev['helpful'], $rev['request_opened_at'], $rev['created_at']]);
    return $rev['id'];
}

function rev_classify(string $body, int $rating): array
{
    $text = ' ' . strtolower($body) . ' ';
    $kw = [
        'venue'         => ['venue', 'stage', 'sound', 'atmosphere', 'location', 'hall', 'lighting', 'acoustics', 'seats', 'ambience'],
        'organization'  => ['organiz', 'organis', 'logistics', 'staff', 'volunteer', 'coord', 'manage', 'planning', 'professional'],
        'check-in'      => ['check-in', 'checkin', 'check in', 'queue', 'entry', 'entrance', 'gate', 'ticketing', 'security', 'scanning', 'wristband', 'lines'],
        'entertainment' => ['music', 'performance', 'artist', 'band', 'dj', 'entertainment', 'lineup', 'show', 'concert', 'speaker', 'host'],
        'food_beverage' => ['food', 'drink', 'beverage', 'snack', 'refreshment', 'water', 'bar', 'catering', 'meal'],
        'transport'     => ['parking', 'transport', 'traffic', 'shuttle', 'taxi', 'car park'],
        'pricing'       => ['price', 'pricing', 'expensive', 'cost', 'value', 'fee', 'money', 'cheap', 'overpriced'],
    ];
    $labels = ['venue' => 'Venue', 'organization' => 'Organization', 'check-in' => 'Check-in',
        'entertainment' => 'Entertainment', 'food_beverage' => 'Food & Beverage',
        'transport' => 'Transport & Parking', 'pricing' => 'Pricing & Value'];
    $pos = ['great', 'excellent', 'amazing', 'awesome', 'fantastic', 'wonderful', 'good', 'smooth', 'quick', 'fast', 'friendly', 'helpful', 'enjoyed', 'loved', 'organized', 'organised', 'on time', 'easy', 'seamless', 'perfect', 'incredible', 'clean', 'comfortable', 'recommend', 'best', 'professional', 'impressive', 'fun'];
    $neg = ['late', 'delay', 'delayed', 'queue', 'queues', 'crowded', 'overcrowded', 'dirty', 'bad', 'poor', 'terrible', 'awful', 'disappoint', 'refund', 'rude', 'stuck', 'slow', 'worse', 'waste', 'uncomfortable', 'disorganized', 'chaotic', 'confusion', 'cancelled', 'unprofessional', 'overpriced', 'parking was', 'expensive'];

    $themes = [];
    foreach ($kw as $theme => $words) {
        $c = 0;
        foreach ($words as $w) $c += substr_count($text, $w);
        if ($c > 0) $themes[] = $theme;
    }
    if (!$themes) $themes = ['organization'];
    $ph = 0; $nh = 0;
    foreach ($pos as $w) $ph += substr_count($text, $w);
    foreach ($neg as $w) $nh += substr_count($text, $w);
    $polarity = $nh > $ph ? 'negative' : ($ph > 0 ? 'positive' : 'neutral');
    $themeList = array_map(fn($t) => ['theme' => $t, 'label' => $labels[$t], 'count' => 1, 'polarity' => $polarity], $themes);

    $sentiment = $rating <= 2 ? 'NEGATIVE' : ($rating === 3 ? 'NEUTRAL' : 'POSITIVE');
    if ($rating >= 4 && $nh >= 2 && $nh > $ph) $sentiment = 'NEUTRAL';
    if ($rating === 3 && $ph > $nh) $sentiment = 'POSITIVE';
    if ($rating === 3 && $nh > $ph) $sentiment = 'NEGATIVE';
    return ['sentiment' => $sentiment, 'themes' => $themeList];
}

// ── build the review set per event ──────────────────────────────────────────
$reviewPool = array_merge($positive, $mixed, $negative, $insightsPool);
$fname = ['Patrick', 'Mary', 'John', 'Christopher', 'Grace', 'David', 'Limbani', 'Chisomo', 'Eve', 'Bob', 'Carol', 'Peter', 'Agness', 'Tamanda'];
$lname = ['Byamasu', 'Moyo', 'Phiri', 'Banda', 'Malunga', 'Tembo', 'Chimwaza', 'Kachale', 'Nyirenda', 'Gondwe', 'Jere', 'Mbewe', 'Nkhoma', 'Chilima'];
$seq = 1;

$insertResp = $db->prepare("INSERT INTO tie_reviews_responses
    (id, vendor_id, review_id, body, ai_drafted, status, created_by, created_by_name, created_at)
    VALUES (?,?,?,?,?,?,?,?,?)");
$insertReq = $db->prepare("INSERT INTO tie_reviews_requests
    (id, vendor_id, event_id, listing_id, customer_id, status, channel, sent_at, opened_at, started_at, submitted_at)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)");

mt_srand(crc32($vendorId . '-rev'));
foreach ($events as $e) {
    // Per-event deterministic customer subset.
    $poolCustomers = array_values(array_filter($customerMap, function ($c) use ($db, $e) {
        $st = $db->prepare('SELECT COUNT(*) FROM bookings WHERE customer_id=? AND listing_id=? AND payment_status=\'Paid\' AND deleted_at IS NULL');
        $st->execute([$c['id'], $e['listing_id']]);
        return (int) $st->fetchColumn() > 0;
    }));
    if (!$poolCustomers) continue;

    $count = max(8, min(60, intval(count($poolCustomers) * 0.65)));
    // Weighted star draw (68/21/7/2/2).
    $weights = [5 => 68, 4 => 21, 3 => 7, 2 => 2, 1 => 2];
    for ($i = 0; $i < $count; $i++) {
        $c = $poolCustomers[$i % count($poolCustomers)];
        $roll = mt_rand(1, 100);
        $rating = 5;
        foreach ($weights as $star => $w) {
            if ($roll <= $w) { $rating = $star; break; }
            $roll -= $w;
        }
        $cand = $rating <= 2 ? $negative : ($rating === 3 ? $mixed : $positive);
        $tpl = $cand[mt_rand(0, count($cand) - 1)];
        // Slight body variation so identical texts are rare.
        $body = $tpl[2];
        if (mt_rand(0, 3) === 0) {
            $body = "I attended {$e['title']}. " . $body;
        }
        $title = $tpl[1];
        $rating = $tpl[0] === $rating ? $rating : $tpl[0];

        $class = rev_classify($body, $rating);
        $row = rev_checkin($db, $checkinBy, $c['id'], $e['listing_id']);
        $verified = $row !== null && !empty($row['checked_in_at']);
        $verification = null;
        if ($row) {
            $verification = [
                'booking_id' => $row['booking_id'],
                'ticket_id' => $row['ticket_id'],
                'ticket_type_id' => (int) $row['ticket_type_id'],
                'ticket_type_name' => $row['ticket_type_name'],
                'checked_in_at' => $row['checked_in_at'],
                'payment_status' => 'Paid',
            ];
        }
        // ~8% of stored reviews intentionally lack a checked-in record —
        // they must NOT get the badge (ticket purchase alone is not attendance).
        if (!$row || mt_rand(1, 100) <= 4) $verified = false;

        $daysAgo = mt_rand(2, 75);
        $created = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days") - mt_rand(0, 82800));
        $id = sprintf('REV-%04d', $seq++);
        $reviewId = rev_insert($db, [
            'id' => $id, 'event_id' => $e['event_id'], 'listing_id' => $e['listing_id'],
            'customer_id' => $c['id'], 'customer_name' => $c['name'], 'customer_email' => $c['email'],
            'rating' => $rating, 'title' => $title, 'body' => $body,
            'sentiment' => $class['sentiment'], 'themes' => $class['themes'],
            'verified' => $verified, 'verification' => $verification,
            'helpful' => mt_rand(0, 34), 'request_opened_at' => date('Y-m-d H:i:s', strtotime($created) - 86400),
            'created_at' => $created,
        ], $vendorId);

        // Response for ~92% of reviews — but NOT for most 1–2 star ones
        // (they represent the "needs attention" queue).
        $responded = mt_rand(1, 100) <= 92 && !($rating <= 2 && mt_rand(1, 100) <= 78);
        if ($responded) {
            $rbody = 'Thank you for your feedback! We are glad you joined us and we take every review seriously to make future events even better.';
            $insertResp->execute([sprintf('RSP-%04d', $seq), $vendorId, $reviewId, $rbody, 0, 'PUBLISHED', $vendorId, $vendorName,
                date('Y-m-d H:i:s', strtotime($created) + 43200)]);
            $db->prepare('UPDATE tie_reviews_reviews SET responded_at=? WHERE id=?')
                ->execute([date('Y-m-d H:i:s', strtotime($created) + 43200), $reviewId]);
        }
    }
}

// ── review request funnel per eligible attendee ─────────────────────────────
$reqSeq = 1;
$reqDupes = [];
foreach ($events as $e) {
    $st = $db->prepare("SELECT b.customer_id AS id FROM bookings b
                        WHERE b.listing_id=? AND b.payment_status='Paid' AND b.deleted_at IS NULL
                        GROUP BY b.customer_id");
    $st->execute([$e['listing_id']]);
    $attendees = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($attendees as $at) {
        $key = $e['listing_id'] . '|' . $at['id'];
        if (isset($reqDupes[$key])) continue;
        $reqDupes[$key] = true;
        if (mt_rand(1, 100) > 82) continue; // 82% request rate
        $statusRoll = mt_rand(1, 100);
        // most requests open, fewer start/submit — a realistic funnel
        $status = $statusRoll <= 52 ? 'OPENED' : ($statusRoll <= 70 ? 'STARTED' : ($statusRoll <= 84 ? 'SUBMITTED' : 'SENT'));
        $submitted = $status === 'SUBMITTED';
        $opened = in_array($status, ['OPENED', 'STARTED', 'SUBMITTED']);
        $started = in_array($status, ['STARTED', 'SUBMITTED']);
        $sent = date('Y-m-d H:i:s', strtotime('-' . mt_rand(8, 70) . ' days'));
        $insertReq->execute([
            sprintf('REQ-%04d', $reqSeq++), $vendorId, $e['event_id'], $e['listing_id'], $at['id'],
            $status, ['UTHENGA', 'EMAIL', 'SMS'][mt_rand(0, 2)], $sent,
            $opened ? date('Y-m-d H:i:s', strtotime($sent) + 3600) : null,
            $started ? date('Y-m-d H:i:s', strtotime($sent) + 7200) : null,
            $submitted ? date('Y-m-d H:i:s', strtotime($sent) + 10800) : null,
        ]);
    }
}

// ── flags (organizer moderation, platform decides) ─────────────────────────
$flagReviews = $db->query("SELECT id FROM tie_reviews_reviews WHERE vendor_id=" . $db->quote($vendorId) . " AND rating=1 LIMIT 2")->fetchAll();
$flagReasons = ['Potential spam', 'Inappropriate language'];
foreach ($flagReviews as $i => $fr) {
    $db->prepare("UPDATE tie_reviews_reviews SET moderation='FLAGGED', flag_reason=? WHERE id=?")
        ->execute([$flagReasons[$i] ?? 'Potential spam', $fr['id']]);
    $db->prepare("INSERT INTO tie_reviews_flags
        (review_id, vendor_id, flagged_by, flagged_by_name, reason, notes, status)
        VALUES (?,?,?,?,?,?,?)")
        ->execute([$fr['id'], $vendorId, $vendorId, $vendorName, $flagReasons[$i] ?? 'Potential spam',
            'Seeded moderation case — review content does not match the event.', 'UNDER_REVIEW']);
}

// ── config ──────────────────────────────────────────────────────────────────
$db->prepare("INSERT INTO tie_reviews_config
    (vendor_id, collect_enabled, request_delay_hours, channel_uthenga, channel_email, channel_sms,
     publish_mode, notify_new, notify_negative, notify_reply, incentive_enabled,
     critical_max, high_max, normal_max, low_max)
    VALUES (?,1,24,1,1,0,'AUTO',1,1,1,0,2,3,4,5)")
    ->execute([$vendorId]);

$totals = $db->query("SELECT COUNT(*) c, COALESCE(AVG(rating),0) a FROM tie_reviews_reviews WHERE vendor_id=" . $db->quote($vendorId))->fetch();
echo "Seeded reviews intelligence for {$vendorName}: {$totals['c']} reviews (avg " . round($totals['a'], 2) . "), + requests, flags, config.\n";
echo "Note: seeded " . count($checkinBy) . " attendance lookups; verified attendees only where checked-in tickets exist.\n";
