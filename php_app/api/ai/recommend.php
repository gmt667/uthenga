<?php
/**
 * Uthenga — AI Recommendation Engine
 * GET/POST: { user_id?, listing_type?, location?, budget? }
 * Returns personalised listing recommendations
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

$userId      = trim($_REQUEST['user_id']      ?? '');
$listingType = trim($_REQUEST['listing_type'] ?? 'all');
$location    = trim($_REQUEST['location']     ?? '');
$budget      = (float)($_REQUEST['budget']    ?? 0);
$limit       = max(1, min((int)($_REQUEST['limit'] ?? 6), 20));

// ── Build query ────────────────────────────────────────────────────────────────
$where  = ['l.is_active = 1'];
$params = [];

if ($listingType !== 'all' && in_array($listingType, ['event','accommodation','tour','transport'])) {
    $where[]  = 'l.listing_type = ?';
    $params[] = $listingType;
}

if ($location !== '') {
    $where[]  = 'l.location LIKE ?';
    $params[] = '%' . $location . '%';
}

// ── Personalisation: boost items user hasn't booked ───────────────────────────
$bookedIds = [];
if ($userId !== '') {
    $booked    = dbQuery("SELECT DISTINCT listing_id FROM bookings WHERE customer_id = ?", [$userId]);
    $bookedIds = array_column($booked, 'listing_id');
}

$whereStr = implode(' AND ', $where);
$listings = dbQuery("
    SELECT l.id, l.listing_type, l.title, l.description, l.location, l.image, l.rating, l.price, l.meta
    FROM listings l
    WHERE $whereStr
    ORDER BY l.rating DESC, l.created_at DESC
    LIMIT 50
", $params);

// ── Score and sort ─────────────────────────────────────────────────────────────
$scored = [];
foreach ($listings as $listing) {
    $score = (float)$listing['rating'];

    // Discount if already booked
    if (in_array($listing['id'], $bookedIds)) {
        $score -= 2.0;
    }

    // Budget filter — parse price from meta
    if ($budget > 0) {
        $m = json_decode($listing['meta'] ?? '{}', true) ?? [];
        $price = (float)($m['standardTicketPrice'] ?? $m['pricePerNight'] ?? $m['pricePerPerson'] ?? $m['pricePerSeat'] ?? $listing['price'] ?? 0);
        if ($price > $budget) {
            continue; // over budget — skip
        }
        // Closer to budget = slightly better
        if ($price > 0 && $budget > 0) {
            $score += min(1.0, $price / $budget);
        }
    }

    $listing['_score'] = $score;
    $scored[] = $listing;
}

usort($scored, fn($a, $b) => $b['_score'] <=> $a['_score']);
$results = array_slice($scored, 0, $limit);

// Strip internal scoring field
foreach ($results as &$r) {
    unset($r['_score']);
    $r['meta'] = json_decode($r['meta'] ?? '{}', true) ?? [];
}
unset($r);

echo json_encode(['success' => true, 'count' => count($results), 'data' => $results]);
