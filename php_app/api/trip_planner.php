<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/malawi_locations.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_POST['query'] ?? $_GET['query'] ?? '');
if ($query === '') {
    echo json_encode(['success' => false, 'message' => 'Query is empty.']);
    exit;
}

// Parse the prompt for duration and budget.
$days = 3;
if (preg_match('/(\d+)\s*-?\s*day/i', $query, $matches)) {
    $days = max(1, min(14, (int) $matches[1]));
}

$budget = 500000;
if (preg_match('/(?:MK|MKK)?\s*([\d,]+)/i', $query, $matches)) {
    $cleanNum = str_replace(',', '', $matches[1]);
    if ((int) $cleanNum > 1000) {
        $budget = (float) $cleanNum;
    }
}

$matchedLocation = uthenga_malawi_find_location($query);
$matchedDest = $matchedLocation['city'] ?? 'Lake Malawi';
$matchedDistrict = $matchedLocation['district'] ?? '';
if ($matchedDistrict === 'Mangochi') {
    $matchedDest = 'Mangochi / Gosheni City';
}

// Use the district when available so we catch district-level listings.
$searchLocation = $matchedDistrict ?: $matchedDest;

// Fetch matched items from listings table safely.
$stays = [];
$tours = [];
$transports = [];
try {
    $stays = dbQuery(
        "SELECT * FROM listings
         WHERE listing_type = 'accommodation'
           AND is_active = 1
           AND (location LIKE ? OR title LIKE ?)
         LIMIT 3",
        ["%$searchLocation%", "%$searchLocation%"]
    ) ?: [];
    if (empty($stays)) {
        $stays = dbQuery("SELECT * FROM listings WHERE listing_type = 'accommodation' AND is_active = 1 LIMIT 3") ?: [];
    }
} catch (Throwable $e) {
    $stays = [];
}

try {
    $tours = dbQuery(
        "SELECT * FROM listings
         WHERE listing_type = 'tour'
           AND is_active = 1
           AND (location LIKE ? OR title LIKE ?)
         LIMIT 4",
        ["%$searchLocation%", "%$searchLocation%"]
    ) ?: [];
    if (empty($tours)) {
        $tours = dbQuery("SELECT * FROM listings WHERE listing_type = 'tour' AND is_active = 1 LIMIT 4") ?: [];
    }
} catch (Throwable $e) {
    $tours = [];
}

try {
    $transports = dbQuery(
        "SELECT * FROM listings
         WHERE listing_type = 'transport'
           AND is_active = 1
           AND (location LIKE ? OR title LIKE ?)
         LIMIT 2",
        ["%$searchLocation%", "%$searchLocation%"]
    ) ?: [];
    if (empty($transports)) {
        $transports = dbQuery("SELECT * FROM listings WHERE listing_type = 'transport' AND is_active = 1 LIMIT 2") ?: [];
    }
} catch (Throwable $e) {
    $transports = [];
}

$localFoodStops = [];
if (uthenga_table_exists('local_business_listings')) {
    try {
        $localFoodStops = dbQuery(
            "SELECT lbl.id, lbl.business_name, lbl.business_type, lbl.description, lbl.city, lbl.address, lbl.phone, lbl.website,
                    lbl.cover_image, lbl.avg_rating, lbl.price_range, lbl.is_featured, lbl.created_at, u.name AS vendor_name
             FROM local_business_listings lbl
             LEFT JOIN users u ON u.id = lbl.vendor_id
             WHERE lbl.is_active = 1
               AND lbl.business_type IN ('restaurant', 'cafe')
               AND (lbl.city LIKE ? OR lbl.business_name LIKE ? OR lbl.description LIKE ?)
             ORDER BY lbl.is_featured DESC, lbl.avg_rating DESC, lbl.created_at DESC
             LIMIT 3",
            ["%$searchLocation%", "%$searchLocation%", "%$searchLocation%"]
        ) ?: [];

        if (empty($localFoodStops)) {
            $localFoodStops = dbQuery(
                "SELECT lbl.id, lbl.business_name, lbl.business_type, lbl.description, lbl.city, lbl.address, lbl.phone, lbl.website,
                        lbl.cover_image, lbl.avg_rating, lbl.price_range, lbl.is_featured, lbl.created_at, u.name AS vendor_name
                 FROM local_business_listings lbl
                 LEFT JOIN users u ON u.id = lbl.vendor_id
                 WHERE lbl.is_active = 1
                   AND lbl.business_type IN ('restaurant', 'cafe')
                 ORDER BY lbl.is_featured DESC, lbl.avg_rating DESC, lbl.created_at DESC
                 LIMIT 3"
            ) ?: [];
        }
    } catch (Throwable $e) {
        $localFoodStops = [];
    }
}

// Build day-by-day itinerary.
$itinerary = [];
$totalEstimatedCost = 0;

$selectedStay = !empty($stays) ? $stays[0] : null;
$selectedStayItem = $selectedStay ? marketplace_normalize_item($selectedStay) : null;
$stayPrice = $selectedStay ? (float) marketplace_price_from_meta($selectedStay) : 45000;

$selectedTransport = !empty($transports) ? $transports[0] : null;
$selectedTransportItem = $selectedTransport ? marketplace_normalize_item($selectedTransport) : null;
$transportPrice = $selectedTransport ? (float) marketplace_price_from_meta($selectedTransport) : 15000;

$selectedFoodStop = !empty($localFoodStops) ? $localFoodStops[0] : null;

for ($day = 1; $day <= $days; $day++) {
    $activities = [];

    $tourIdx = ($day - 1) % max(1, count($tours));
    $tourItem = !empty($tours) ? $tours[$tourIdx] : null;
    $tourPrice = $tourItem ? (float) marketplace_price_from_meta($tourItem) : 12000;

    if ($day === 1) {
        $activities[] = [
            'time' => '08:00 AM',
            'title' => 'Departure & Transport',
            'description' => $selectedTransport
                ? 'Board your Uthenga-booked transport: ' . $selectedTransport['title'] . '.'
                : 'Depart towards ' . $matchedDest . ' via private coach.',
            'cost' => $transportPrice,
            'booking_url' => $selectedTransport ? 'event-details.php?type=transport&id=' . $selectedTransport['id'] : null,
        ];
        $totalEstimatedCost += $transportPrice;
    }

    if ($tourItem) {
        $activities[] = [
            'time' => '10:30 AM',
            'title' => $tourItem['title'],
            'description' => 'Explore local attractions and take part in activities: ' . substr($tourItem['description'], 0, 140) . '...',
            'cost' => $tourPrice,
            'booking_url' => 'event-details.php?type=tour&id=' . $tourItem['id'],
        ];
        $totalEstimatedCost += $tourPrice;
    } else {
        $activities[] = [
            'time' => '02:00 PM',
            'title' => 'Sightseeing at ' . $matchedDest,
            'description' => 'Enjoy scenic views, local curio shopping, and photography around ' . $matchedDest . '.',
            'cost' => 0,
            'booking_url' => null,
        ];
    }

    $restaurantFallbacks = [
        ['name' => 'Kaya Papaya Restaurant', 'style' => 'Local Malawian fish and curry'],
        ['name' => 'The Lakefront Grill', 'style' => 'Chambo fish and chips'],
        ['name' => 'Banja Bistro', 'style' => 'Traditional food and fresh juices'],
    ];
    $restIdx = ($day - 1) % max(1, count($restaurantFallbacks));
    $foodName = $selectedFoodStop['business_name'] ?? $restaurantFallbacks[$restIdx]['name'];
    $foodStyle = $selectedFoodStop
        ? trim(($selectedFoodStop['business_type'] ?? 'restaurant') . ' stop in ' . ($selectedFoodStop['city'] ?? $matchedDest))
        : $restaurantFallbacks[$restIdx]['style'];
    $foodUrl = $selectedFoodStop ? 'marketplace.php?view=' . $selectedFoodStop['id'] : null;

    $activities[] = [
        'time' => '01:00 PM',
        'title' => 'Lunch at ' . $foodName,
        'description' => $selectedFoodStop
            ? 'Recommended local dining experience from the marketplace. ' . ($selectedFoodStop['description'] ?? '')
            : 'Recommended local dining experience. Speciality: ' . $foodStyle . '.',
        'cost' => 0,
        'cost_text' => $selectedFoodStop['price_range'] ?? 'Reserve or contact the vendor',
        'booking_url' => $foodUrl,
    ];
    if (!$selectedFoodStop) {
        $totalEstimatedCost += 8500;
    }

    if ($day < $days && $selectedStay) {
        $activities[] = [
            'time' => '06:00 PM',
            'title' => 'Check-in and Overnight at ' . $selectedStay['title'],
            'description' => 'Relax at your accommodation: ' . $selectedStay['location'] . '.',
            'cost' => $stayPrice,
            'booking_url' => 'event-details.php?type=property&id=' . $selectedStay['id'],
        ];
        $totalEstimatedCost += $stayPrice;
    }

    $itinerary[] = [
        'day' => $day,
        'theme' => 'Day ' . $day . ': ' . ($day === 1 ? 'Arrival and Discovery' : ($day === $days ? 'Farewell and Departure' : 'Adventure and Explore')),
        'activities' => $activities,
    ];
}

// Direct booking suggestions from Uthenga.
$suggestions = [];
if ($selectedStay) {
    $suggestions[] = [
        'type' => 'Stay',
        'title' => $selectedStayItem['title'] ?? $selectedStay['title'],
        'location' => $selectedStayItem['location'] ?? $selectedStay['location'],
        'price' => $stayPrice,
        'image' => $selectedStayItem['image'] ?? '',
        'url' => 'event-details.php?type=property&id=' . $selectedStay['id'],
    ];
}
if (!empty($tours)) {
    $tourItem = marketplace_normalize_item($tours[0]);
    $suggestions[] = [
        'type' => 'Tour',
        'title' => $tourItem['title'],
        'location' => $tourItem['location'],
        'price' => (float) marketplace_price_from_meta($tours[0]),
        'image' => $tourItem['image'],
        'url' => 'event-details.php?type=tour&id=' . $tours[0]['id'],
    ];
}
if ($selectedTransport) {
    $suggestions[] = [
        'type' => 'Transport',
        'title' => $selectedTransportItem['title'] ?? $selectedTransport['title'],
        'location' => $selectedTransportItem['location'] ?? $selectedTransport['location'],
        'price' => $transportPrice,
        'image' => $selectedTransportItem['image'] ?? '',
        'url' => 'event-details.php?type=transport&id=' . $selectedTransport['id'],
    ];
}
if ($selectedFoodStop) {
    $suggestions[] = [
        'type' => 'Marketplace',
        'title' => $selectedFoodStop['business_name'],
        'location' => trim(($selectedFoodStop['city'] ?? '') . (($selectedFoodStop['address'] ?? '') !== '' ? ' · ' . $selectedFoodStop['address'] : '')),
        'price' => 0,
        'price_label' => $selectedFoodStop['price_range'] ?: 'Contact / reserve',
        'image' => $selectedFoodStop['cover_image'] ?: '',
        'url' => 'marketplace.php?view=' . $selectedFoodStop['id'],
    ];
}

// Save planner session to database safely.
$sessionKey = session_id();
$userId = $_SESSION['user_id'] ?? null;
$planJson = json_encode([
    'itinerary' => $itinerary,
    'suggestions' => $suggestions,
    'total_estimated_cost' => $totalEstimatedCost,
]);

$planId = rand(1000, 9999);
if (uthenga_table_exists('trip_planner_sessions')) {
    try {
        dbExecute(
            "INSERT INTO trip_planner_sessions (user_id, session_key, query_text, plan_json, days, budget_mk, destination)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$userId, $sessionKey, $query, $planJson, $days, $budget, $matchedDest]
        );
        $planId = dbLastId();
    } catch (Throwable $e) {
        // Safe fallback for session key
    }
}

echo json_encode([
    'success' => true,
    'id' => $planId,
    'days' => $days,
    'budget' => $budget,
    'destination' => $matchedDest,
    'district' => $matchedDistrict,
    'estimated_cost' => $totalEstimatedCost,
    'itinerary' => $itinerary,
    'suggestions' => $suggestions,
]);
exit;
