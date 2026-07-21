<?php
/**
 * Uthenga AI Travel Assistant Chat API
 * POST: { message, history[] }
 * Returns: { reply, suggestions[] }
 *
 * Uses Gemini Flash if GEMINI_API_KEY is set, otherwise falls back to a
 * deterministic local responder that is grounded in the Uthenga catalog,
 * Malawi districts, featured cities, and approved vendors.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/malawi_locations.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim((string)($input['message'] ?? ''));
$history = is_array($input['history'] ?? null) ? $input['history'] : [];

if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Empty message.']);
    exit;
}

function uthenga_ai_summary_rows(array $rows): string {
    $out = [];
    foreach ($rows as $row) {
        $meta = json_decode((string)($row['meta'] ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        $price = (float)($meta['standardTicketPrice']
            ?? $meta['pricePerNight']
            ?? $meta['pricePerPerson']
            ?? $meta['pricePerSeat']
            ?? $meta['base_price']
            ?? $meta['baseFare']
            ?? 0);
        $title = trim((string)($row['title'] ?? 'Listing'));
        $location = trim((string)($row['location'] ?? 'Malawi'));
        $line = '- ' . $title . ' (' . $location . ')';
        if ($price > 0) {
            $line .= ' - MK ' . number_format($price);
        }
        $out[] = $line;
    }
    return implode("\n", $out) ?: 'None available.';
}

function uthenga_ai_vendor_rows(array $rows): string {
    $out = [];
    foreach ($rows as $row) {
        $name = trim((string)($row['name'] ?? 'Vendor'));
        $category = trim((string)($row['category'] ?? 'Service'));
        $city = trim((string)($row['city'] ?? ''));
        $phone = trim((string)($row['phone'] ?? ''));
        $line = '- ' . $name . ' (' . $category . ')';
        if ($city !== '') {
            $line .= ' - ' . $city;
        }
        if ($phone !== '') {
            $line .= ' - ' . $phone;
        }
        $out[] = $line;
    }
    return implode("\n", $out) ?: 'No approved vendors available yet.';
}

function uthenga_ai_location_rows(array $rows, int $limit = 10): string {
    $rows = array_slice($rows, 0, $limit);
    $out = [];
    foreach ($rows as $row) {
        $district = trim((string)($row['district'] ?? ''));
        $city = trim((string)($row['city'] ?? ''));
        $summary = trim((string)($row['summary'] ?? ''));
        $line = '- ' . ($district !== '' ? $district : 'Malawi');
        if ($city !== '') {
            $line .= ' / ' . $city;
        }
        if ($summary !== '') {
            $line .= ' - ' . $summary;
        }
        $out[] = $line;
    }
    return implode("\n", $out) ?: 'No location data available.';
}

function uthenga_ai_city_rows(array $rows, int $limit = 5): string {
    $rows = array_slice($rows, 0, $limit);
    $out = [];
    foreach ($rows as $row) {
        $city = trim((string)($row['city'] ?? ''));
        $summary = trim((string)($row['summary'] ?? ''));
        if ($city === '') {
            continue;
        }
        $line = '- ' . $city;
        if ($summary !== '') {
            $line .= ' - ' . $summary;
        }
        $out[] = $line;
    }
    return implode("\n", $out) ?: 'No city data available.';
}

function uthenga_ai_generate_suggestions(string $msg): array {
    $msg = strtolower($msg);

    if (str_contains($msg, 'hotel') || str_contains($msg, 'stay') || str_contains($msg, 'accommodation')) {
        return ['Check room availability', 'Best hotels in Lilongwe', 'Budget lodges near Lake Malawi'];
    }
    if (str_contains($msg, 'event')) {
        return ['Upcoming concerts', 'Sports events this month', 'Cultural festivals in Blantyre'];
    }
    if (str_contains($msg, 'tour') || str_contains($msg, 'safari')) {
        return ['Lake Malawi tours', 'Mount Mulanje hiking', 'Wildlife safaris'];
    }
    if (str_contains($msg, 'vendor') || str_contains($msg, 'provider') || str_contains($msg, 'business')) {
        return ['Approved vendors in Blantyre', 'Approved vendors in Lilongwe', 'Businesses by category'];
    }
    return ['Plan a weekend trip', 'Budget for 5 days in Malawi', 'Top attractions near Lilongwe'];
}

function uthenga_ai_local_reply(
    string $msg,
    array $events,
    array $accom,
    array $tours,
    array $vendors
): string {
    $msg = strtolower($msg);

    if (str_contains($msg, 'hello') || str_contains($msg, 'hi') || str_contains($msg, 'hie')) {
        return "Hello. I'm Amai, your Uthenga travel assistant. I can help you discover events, book accommodation, plan tours, and estimate budgets across Malawi. What would you like to explore today?";
    }

    if (str_contains($msg, 'event')) {
        $items = array_slice($events, 0, 3);
        $list = implode("\n", array_map(fn($e) => '- **' . ($e['title'] ?? 'Event') . '** - ' . ($e['location'] ?? 'Malawi'), $items));
        return "Here are some upcoming events on Uthenga:\n\n{$list}\n\nWould you like to know more about any of these, or should I help you book tickets?";
    }

    if (str_contains($msg, 'hotel') || str_contains($msg, 'stay') || str_contains($msg, 'accommodation') || str_contains($msg, 'lodge')) {
        $items = array_slice($accom, 0, 3);
        $list = implode("\n", array_map(fn($e) => '- **' . ($e['title'] ?? 'Accommodation') . '** - ' . ($e['location'] ?? 'Malawi'), $items));
        return "Here are some good accommodation options:\n\n{$list}\n\nI can also check prices for specific dates.";
    }

    if (str_contains($msg, 'tour') || str_contains($msg, 'safari') || str_contains($msg, 'trip')) {
        $items = array_slice($tours, 0, 3);
        $list = implode("\n", array_map(fn($e) => '- **' . ($e['title'] ?? 'Tour') . '** - ' . ($e['location'] ?? 'Malawi'), $items));
        return "Here are some popular tours available:\n\n{$list}\n\nWould you like details, pricing, or help booking any of these?";
    }

    if (str_contains($msg, 'vendor') || str_contains($msg, 'provider') || str_contains($msg, 'business')) {
        $items = array_slice($vendors, 0, 3);
        $list = implode("\n", array_map(function ($e) {
            $line = '- **' . ($e['name'] ?? 'Vendor') . '** (' . ($e['category'] ?? 'Service') . ')';
            $city = trim((string)($e['city'] ?? ''));
            if ($city !== '') {
                $line .= ' - ' . $city;
            }
            return $line;
        }, $items));
        return "Here are approved vendors currently available on Uthenga:\n\n{$list}\n\nI can also suggest vendors by city or category.";
    }

    if (str_contains($msg, 'budget') || str_contains($msg, 'cost') || str_contains($msg, 'price') || str_contains($msg, 'cheap')) {
        return "Here is a rough budget guide for a weekend in Malawi:\n\n- Budget accommodation: MK 25,000-60,000 per night\n- Mid-range hotel: MK 80,000-200,000 per night\n- Event ticket (standard): MK 5,000-30,000\n- Bus transfer: MK 10,000-40,000\n- Meals: MK 5,000-20,000 per day\n\nWould you like me to build a full budget estimate for your trip?";
    }

    if (str_contains($msg, 'mangochi') || str_contains($msg, 'lake')) {
        return "Mangochi and Lake Malawi are excellent travel destinations. Activities include:\n\n- Snorkelling and diving at Cape Maclear\n- Boat trips to Mumbo Island\n- Birdwatching at Lake Malawi National Park\n- Sport fishing\n\nI can show you available accommodation and tours in this area.";
    }

    if (str_contains($msg, 'lilongwe')) {
        return "Lilongwe, the capital, has plenty to offer:\n\n- Lilongwe Wildlife Centre\n- City Mall and Area 3 shopping\n- Kumbali Cultural Lodge\n- Great restaurants in Old Town\n\nLooking for events, hotels, or transport in Lilongwe?";
    }

    if (str_contains($msg, 'blantyre')) {
        return "Blantyre, Malawi's commercial hub, offers:\n\n- St. Michael's and All Angels Church\n- Soche Hill for panoramic views\n- Chichiri Mall\n- Live music and cultural events year-round\n\nCan I help you find accommodation or events in Blantyre?";
    }

    if (str_contains($msg, 'itinerary') || str_contains($msg, 'plan') || str_contains($msg, 'schedule')) {
        return "I would love to help plan your itinerary. Please tell me:\n\n1. How many days are you travelling?\n2. Which cities or regions do you want to visit?\n3. Your interests (adventure, relaxation, culture, etc.)\n4. Your approximate budget\n\nWith those details, I'll create a personalised day-by-day plan.";
    }

    if (str_contains($msg, 'weather')) {
        return "Malawi has a warm, tropical climate:\n\n- Dry season (May to October): perfect weather and cooler nights\n- Rainy season (November to April): lush landscapes and lower prices\n- Temperatures: about 20C to 35C year-round, cooler in the highlands\n\nWhich destination's weather would you like more detail on?";
    }

    if (str_contains($msg, 'transport') || str_contains($msg, 'bus') || str_contains($msg, 'taxi') || str_contains($msg, 'car')) {
        return "Transport options in Malawi include:\n\n- Express buses between Lilongwe, Blantyre, and Mzuzu\n- Car hire in major cities\n- Airport transfers through the platform\n- Mbanda ride-sharing for local trips\n\nWould you like help finding a route or booking transport?";
    }

    return "I can help with events, accommodation, tours, transport, budgeting, and trip planning anywhere in Malawi. Could you give me a bit more detail about what you are looking for?";
}

$serviceStatus = 'local_fallback';
$serviceMessage = 'Local AI fallback';

$destinations = dbQuery("SELECT DISTINCT location FROM listings WHERE is_active = 1 AND location != '' LIMIT 30");
$destList = implode(', ', array_unique(array_filter(array_column($destinations, 'location'))));

$topEvents = dbQuery("SELECT title, location, meta FROM listings WHERE listing_type='event' AND is_active=1 ORDER BY rating DESC LIMIT 5");
$topAccom  = dbQuery("SELECT title, location, meta FROM listings WHERE listing_type='accommodation' AND is_active=1 ORDER BY rating DESC LIMIT 5");
$topTours  = dbQuery("SELECT title, location, meta FROM listings WHERE listing_type='tour' AND is_active=1 ORDER BY rating DESC LIMIT 5");
$topVendors = [];

if (function_exists('uthenga_table_exists') && uthenga_table_exists('vendor_profiles')) {
    $topVendors = dbQuery("
        SELECT
            COALESCE(vp.business_name, u.name) AS name,
            COALESCE(vp.category, u.role) AS category,
            COALESCE(vp.city, '') AS city,
            COALESCE(vp.phone, u.phone, '') AS phone
        FROM vendor_profiles vp
        INNER JOIN users u ON u.id = vp.vendor_id
        WHERE LOWER(COALESCE(vp.approval_status, vp.status, 'approved')) = 'approved'
        ORDER BY COALESCE(vp.approved_at, vp.updated_at, vp.created_at) DESC
        LIMIT 10
    ");
}

$districts = uthenga_malawi_districts();
$featuredCities = uthenga_malawi_featured_cities();

$systemContext = <<<PROMPT
You are Amai, the Uthenga travel assistant for Malawi.

Use a professional, concise tone. Do not use emojis.
Answer only from the context provided below when possible. If data is missing, say so clearly instead of guessing.

Uthenga context:
Use approved vendors when the user asks for businesses, services, or providers.
Use Malawian Kwacha (MK) for all prices.
Prefer active listings and verified vendor data from the platform.
Keep travel advice specific to Malawi, the listed districts, and the live Uthenga inventory.

Live listings:
Top Events:
{$evtSummary}

Top Accommodation:
{$accomSummary}

Top Tours:
{$tourSummary}

Approved vendors:
{$vendorSummary}

Malawi districts and city notes:
{$locationSummary}

Featured cities:
{$citySummary}

Available destinations include: {$destList}

When relevant, suggest specific listings or approved vendors from the context above. If asked for budgets, use MK and keep ranges realistic for Malawi.
PROMPT;

$systemContext = str_replace(
    ['{$evtSummary}', '{$accomSummary}', '{$tourSummary}', '{$vendorSummary}', '{$locationSummary}', '{$citySummary}'],
    [
        uthenga_ai_summary_rows($topEvents),
        uthenga_ai_summary_rows($topAccom),
        uthenga_ai_summary_rows($topTours),
        uthenga_ai_vendor_rows($topVendors),
        uthenga_ai_location_rows($districts),
        uthenga_ai_city_rows($featuredCities),
    ],
    $systemContext
);

$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');

if ($apiKey !== '' && function_exists('curl_init')) {
    $messages = [];
    foreach ($history as $h) {
        if (!empty($h['role']) && !empty($h['content'])) {
            $messages[] = [
                'role' => $h['role'] === 'ai' ? 'model' : 'user',
                'parts' => [['text' => (string)$h['content']]],
            ];
        }
    }
    $messages[] = ['role' => 'user', 'parts' => [['text' => $message]]];

    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $systemContext]]],
        'contents' => $messages,
        'generationConfig' => ['maxOutputTokens' => 400, 'temperature' => 0.5],
    ]);

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $result = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $result) {
        $data = json_decode($result, true);
        $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($reply !== '') {
            echo json_encode([
                'success' => true,
                'reply' => $reply,
                'suggestions' => uthenga_ai_generate_suggestions($message),
                'service_status' => 'gemini',
                'service_message' => 'Gemini response',
            ]);
            exit;
        }
        $serviceMessage = 'Gemini returned an empty response';
    } else {
        $serviceMessage = 'Gemini request failed';
    }
} elseif ($apiKey !== '') {
    $serviceMessage = 'cURL extension unavailable';
}

$reply = uthenga_ai_local_reply($message, $topEvents, $topAccom, $topTours, $topVendors);
echo json_encode([
    'success' => true,
    'reply' => $reply,
    'suggestions' => uthenga_ai_generate_suggestions($message),
    'service_status' => $serviceStatus,
    'service_message' => $serviceMessage,
]);
