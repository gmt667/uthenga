<?php
/**
 * Uthenga — AI Itinerary Generator API
 * POST: { destination, days, interests[], budget_style }
 * Returns a day-by-day itinerary using live Uthenga listings where available
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

$input       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$destination = trim($input['destination'] ?? 'Lilongwe');
$days        = max(1, min((int)($input['days'] ?? 3), 14));
$interests   = is_array($input['interests'] ?? null) ? $input['interests'] : ['culture', 'nature'];
$style       = in_array($input['style'] ?? 'mid-range', ['budget','mid-range','luxury']) ? ($input['style'] ?? 'mid-range') : 'mid-range';

// ── Fetch relevant live listings ───────────────────────────────────────────────
$events = dbQuery("
    SELECT id, title, location, meta FROM listings
    WHERE listing_type = 'event' AND is_active = 1
      AND location LIKE ?
    ORDER BY rating DESC LIMIT 5
", ['%' . $destination . '%']);

$accom = dbQuery("
    SELECT id, title, location, meta FROM listings
    WHERE listing_type = 'accommodation' AND is_active = 1
      AND location LIKE ?
    ORDER BY rating DESC LIMIT 3
", ['%' . $destination . '%']);

$tours = dbQuery("
    SELECT id, title, location, meta FROM listings
    WHERE listing_type = 'tour' AND is_active = 1
      AND location LIKE ?
    ORDER BY rating DESC LIMIT 5
", ['%' . $destination . '%']);

$transport = dbQuery("
    SELECT id, title, location, meta FROM listings
    WHERE listing_type = 'transport' AND is_active = 1
    ORDER BY rating DESC LIMIT 3
");

// ── Local attraction database ──────────────────────────────────────────────────
$attractions = [
    'lilongwe' => [
        ['name' => 'Lilongwe Wildlife Centre', 'type' => 'nature', 'duration' => '3 hours'],
        ['name' => 'Old Town Market', 'type' => 'culture', 'duration' => '2 hours'],
        ['name' => 'City Mall', 'type' => 'shopping', 'duration' => '2 hours'],
        ['name' => 'Kumbali Cultural Lodge', 'type' => 'culture', 'duration' => '4 hours'],
        ['name' => 'Kamuzu Memorial', 'type' => 'history', 'duration' => '1 hour'],
    ],
    'blantyre' => [
        ['name' => "St Michael's & All Angels Church", 'type' => 'history', 'duration' => '1 hour'],
        ['name' => 'Soche Hill', 'type' => 'nature', 'duration' => '3 hours'],
        ['name' => 'Chichiri Mall', 'type' => 'shopping', 'duration' => '2 hours'],
        ['name' => 'Mandala House', 'type' => 'history', 'duration' => '1 hour'],
        ['name' => 'Limbe Market', 'type' => 'culture', 'duration' => '2 hours'],
    ],
    'mangochi' => [
        ['name' => 'Cape Maclear National Park', 'type' => 'nature', 'duration' => 'full day'],
        ['name' => 'Lake Malawi Beach', 'type' => 'relaxation', 'duration' => 'full day'],
        ['name' => 'Mumbo Island Trip', 'type' => 'adventure', 'duration' => 'full day'],
        ['name' => 'Fort Johnston Museum', 'type' => 'history', 'duration' => '2 hours'],
        ['name' => 'Snorkelling & Diving', 'type' => 'adventure', 'duration' => '3 hours'],
    ],
    'mzuzu' => [
        ['name' => 'Viphya Plateau', 'type' => 'nature', 'duration' => 'full day'],
        ['name' => 'Mzuzu Museum', 'type' => 'culture', 'duration' => '2 hours'],
        ['name' => 'Nkhata Bay', 'type' => 'relaxation', 'duration' => 'full day'],
        ['name' => 'Livingstonia Mission', 'type' => 'history', 'duration' => 'full day'],
    ],
];

$destKey = strtolower(trim($destination));
foreach ($attractions as $key => $attrList) {
    if (str_contains($destKey, $key)) {
        $localAttractions = $attrList;
        break;
    }
}
$localAttractions = $localAttractions ?? $attractions['lilongwe'];

// ── Build itinerary days ───────────────────────────────────────────────────────
$itinerary = [];
$usedAttr  = [];

for ($day = 1; $day <= $days; $day++) {
    $activities = [];

    if ($day === 1) {
        // Arrival day
        if (!empty($transport)) {
            $t = $transport[0];
            $activities[] = ['time' => 'Morning', 'activity' => "✈️ Arrival & Transfer to {$destination}", 'note' => "Book via Uthenga: {$t['title']}", 'link_id' => $t['id'], 'link_type' => 'transport'];
        }
        if (!empty($accom)) {
            $h = $accom[0];
            $activities[] = ['time' => 'Afternoon', 'activity' => "🏨 Check in at " . $h['title'], 'note' => 'Rest and freshen up after travel', 'link_id' => $h['id'], 'link_type' => 'accommodation'];
        }
        $activities[] = ['time' => 'Evening', 'activity' => "🍽️ Welcome dinner in {$destination}", 'note' => 'Try local Malawian cuisine — nsima, chambo, or nyama'];
    } elseif ($day === $days && $days > 1) {
        // Departure day
        $activities[] = ['time' => 'Morning', 'activity' => '🧳 Pack up & Checkout', 'note' => 'Checkout usually by 11:00 AM'];
        $activities[] = ['time' => 'Morning', 'activity' => "🛍️ Last-minute shopping at local market", 'note' => 'Pick up crafts, chitenji fabric, or local spices'];
        $activities[] = ['time' => 'Afternoon', 'activity' => "✈️ Departure", 'note' => 'Safe travels!'];
    } else {
        // Regular day
        $morning = array_values(array_filter($localAttractions, fn($a) => !in_array($a['name'], $usedAttr)));
        $mAttr   = !empty($morning) ? $morning[0] : ['name' => "Explore {$destination}", 'type' => 'general', 'duration' => '3 hours'];
        $usedAttr[] = $mAttr['name'];

        $activities[] = ['time' => 'Morning', 'activity' => "🌅 Visit " . $mAttr['name'], 'note' => "Duration: {$mAttr['duration']}"];

        // Insert a tour if available and relevant
        if (!empty($tours) && $day % 2 === 0) {
            $tour = $tours[min($day - 1, count($tours) - 1)];
            $activities[] = ['time' => 'Afternoon', 'activity' => "🌍 " . $tour['title'], 'note' => 'Guided tour — book on Uthenga', 'link_id' => $tour['id'], 'link_type' => 'tour'];
        } else {
            $afternoon = array_values(array_filter($localAttractions, fn($a) => !in_array($a['name'], $usedAttr)));
            $aAttr     = !empty($afternoon) ? $afternoon[0] : ['name' => "Relax and explore local area", 'duration' => 'free'];
            if (!empty($afternoon)) $usedAttr[] = $aAttr['name'];
            $activities[] = ['time' => 'Afternoon', 'activity' => "☀️ " . $aAttr['name'], 'note' => "Duration: " . ($aAttr['duration'] ?? 'flexible')];
        }

        // Insert event on last full day
        if (!empty($events) && $day === $days - 1) {
            $ev = $events[0];
            $activities[] = ['time' => 'Evening', 'activity' => "🎫 " . $ev['title'], 'note' => 'Book tickets on Uthenga', 'link_id' => $ev['id'], 'link_type' => 'event'];
        } else {
            $activities[] = ['time' => 'Evening', 'activity' => "🌙 Dinner & leisure", 'note' => 'Try a local restaurant or relax at your accommodation'];
        }
    }

    $itinerary[] = ['day' => $day, 'activities' => $activities];
}

// ── Accommodation suggestion ───────────────────────────────────────────────────
$recommendedAccom = null;
if (!empty($accom)) {
    $recommendedAccom = ['id' => $accom[0]['id'], 'title' => $accom[0]['title'], 'location' => $accom[0]['location']];
}

echo json_encode([
    'success'      => true,
    'destination'  => $destination,
    'days'         => $days,
    'style'        => $style,
    'interests'    => $interests,
    'itinerary'    => $itinerary,
    'accommodation'=> $recommendedAccom,
    'tips'         => [
        "Carry local Kwacha (MWK) for smaller markets and rural areas",
        "Sunscreen is essential — the Malawian sun is strong year-round",
        "Greet locals in Chichewa: 'Moni' (hello), 'Zikomo' (thank you)",
        "Book transport in advance to avoid peak season shortages",
    ]
]);
