<?php
/**
 * Uthenga â€” AI Travel Assistant Chat API
 * POST: { message, history[] }
 * Returns: { reply, suggestions[] }
 *
 * Uses Google Gemini Flash (free tier) via REST if GEMINI_API_KEY is set,
 * otherwise falls back to a deterministic local responder so the UI always works.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim($input['message'] ?? '');
$history = is_array($input['history'] ?? null) ? $input['history'] : [];

if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Empty message.']);
    exit;
}

$serviceStatus = 'local_fallback';
$serviceMessage = 'Local AI fallback';

// Gather live context from the database when available.
$destinations = dbQuery("SELECT DISTINCT location FROM listings WHERE is_active = 1 AND location != '' LIMIT 30");
$destList = implode(', ', array_unique(array_column($destinations, 'location')));

$topEvents = dbQuery("SELECT title, location, meta FROM listings WHERE listing_type='event' AND is_active=1 ORDER BY rating DESC LIMIT 5");
$topAccom  = dbQuery("SELECT title, location, meta FROM listings WHERE listing_type='accommodation' AND is_active=1 ORDER BY rating DESC LIMIT 5");
$topTours  = dbQuery("SELECT title, location, meta FROM listings WHERE listing_type='tour' AND is_active=1 ORDER BY rating DESC LIMIT 5");

function listingsSummary(array $rows): string {
    $out = [];
    foreach ($rows as $r) {
        $m = json_decode($r['meta'] ?? '{}', true) ?? [];
        $price = $m['standardTicketPrice'] ?? $m['pricePerNight'] ?? $m['pricePerPerson'] ?? $m['pricePerSeat'] ?? 0;
        $out[] = '- ' . ($r['title'] ?? 'Listing') . ' (' . ($r['location'] ?? 'Malawi') . ') â€” MK ' . number_format((float) $price);
    }
    return implode("\n", $out) ?: 'None available.';
}

$systemContext = <<<PROMPT
You are Amai, a helpful and friendly AI Travel Assistant for Uthenga â€” Malawi's premier marketplace for events, accommodation, tours, and transport.

Your job is to help users discover attractions, plan trips, estimate budgets, and book experiences in Malawi. Always be concise, warm, and helpful. Use emojis where they help readability.

Current live listings on Uthenga:

Top Events:
{$evtSummary}

Top Accommodation:
{$accomSummary}

Top Tours:
{$tourSummary}

Available destinations include: {$destList}

When relevant, suggest specific listings from the above. If asked for budget estimates, use Malawian Kwacha (MK). If you don't know something, say so honestly and offer to help with something else.
PROMPT;

$systemContext = str_replace(
    ['{$evtSummary}', '{$accomSummary}', '{$tourSummary}'],
    [listingsSummary($topEvents), listingsSummary($topAccom), listingsSummary($topTours)],
    $systemContext
);

$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: '');

if ($apiKey !== '' && function_exists('curl_init')) {
    $messages = [];
    foreach ($history as $h) {
        if (!empty($h['role']) && !empty($h['content'])) {
            $messages[] = ['role' => $h['role'] === 'ai' ? 'model' : 'user', 'parts' => [['text' => $h['content']]]];
        }
    }
    $messages[] = ['role' => 'user', 'parts' => [['text' => $message]]];

    $payload = json_encode([
        'system_instruction' => ['parts' => [['text' => $systemContext]]],
        'contents' => $messages,
        'generationConfig' => ['maxOutputTokens' => 400, 'temperature' => 0.7],
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $result) {
        $data = json_decode($result, true);
        $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($reply !== '') {
            echo json_encode([
                'success' => true,
                'reply' => $reply,
                'suggestions' => generateSuggestions($message),
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

// Local fallback responder
function localRespond(string $msg, array $events, array $accom, array $tours): string {
    $msg = strtolower($msg);

    if (str_contains($msg, 'hello') || str_contains($msg, 'hi') || str_contains($msg, 'hie')) {
        return "ðŸ‘‹ Hello! I'm Amai, your Uthenga travel assistant. I can help you discover events, book accommodation, plan tours, and estimate budgets across Malawi. What would you like to explore today?";
    }
    if (str_contains($msg, 'event')) {
        $items = array_slice($events, 0, 3);
        $list = implode("\n", array_map(fn($e) => "ðŸŽ« **{$e['title']}** â€” {$e['location']}", $items));
        return "Here are some upcoming events on Uthenga:\n\n{$list}\n\nWould you like to know more about any of these, or shall I help you book tickets?";
    }
    if (str_contains($msg, 'hotel') || str_contains($msg, 'stay') || str_contains($msg, 'accommodation') || str_contains($msg, 'lodge')) {
        $items = array_slice($accom, 0, 3);
        $list = implode("\n", array_map(fn($e) => "ðŸ¨ **{$e['title']}** â€” {$e['location']}", $items));
        return "Great choices for accommodation:\n\n{$list}\n\nI can check availability and pricing for specific dates. When are you planning to visit?";
    }
    if (str_contains($msg, 'tour') || str_contains($msg, 'safari') || str_contains($msg, 'trip')) {
        $items = array_slice($tours, 0, 3);
        $list = implode("\n", array_map(fn($e) => "ðŸŒ **{$e['title']}** â€” {$e['location']}", $items));
        return "Here are some popular tours available:\n\n{$list}\n\nWould you like details, pricing, or help booking any of these?";
    }
    if (str_contains($msg, 'budget') || str_contains($msg, 'cost') || str_contains($msg, 'price') || str_contains($msg, 'cheap')) {
        return "ðŸ’° Here's a rough budget guide for a weekend in Malawi:\n\n- ðŸ¨ Budget accommodation: MK 25,000â€“60,000/night\n- ðŸ¨ Mid-range hotel: MK 80,000â€“200,000/night\n- ðŸŽ« Event ticket (standard): MK 5,000â€“30,000\n- ðŸšŒ Bus transfer: MK 10,000â€“40,000\n- ðŸ½ï¸ Meals: MK 5,000â€“20,000/day\n\nWould you like me to build a full budget estimate for your trip?";
    }
    if (str_contains($msg, 'mangochi') || str_contains($msg, 'lake')) {
        return "ðŸŒŠ Mangochi and Lake Malawi are stunning! Activities include:\n\n- ðŸ¤¿ Snorkelling & diving at Cape Maclear\n- ðŸš¤ Boat trips to Mumbo Island\n- ðŸ¦… Birdwatching at Lake Malawi National Park\n- ðŸŽ£ Sport fishing\n\nI can show you available accommodation and tours in this area. Interested?";
    }
    if (str_contains($msg, 'lilongwe')) {
        return "ðŸ›ï¸ Lilongwe, the capital, has plenty to offer:\n\n- ðŸ¦“ Lilongwe Wildlife Centre\n- ðŸ›ï¸ City Mall & Area 3 shopping\n- ðŸŒ¿ Kumbali Cultural Lodge\n- ðŸ½ï¸ Great restaurants in Old Town\n\nLooking for events, hotels, or transport in Lilongwe?";
    }
    if (str_contains($msg, 'blantyre')) {
        return "ðŸ™ï¸ Blantyre, Malawi's commercial hub:\n\n- ðŸ•Œ St. Michael's & All Angels Church (oldest in Central Africa)\n- ðŸ”ï¸ Soche Hill for panoramic views\n- ðŸ›’ Chichiri Mall\n- ðŸŽµ Live music and cultural events year-round\n\nCan I help you find accommodation or events in Blantyre?";
    }
    if (str_contains($msg, 'itinerary') || str_contains($msg, 'plan') || str_contains($msg, 'schedule')) {
        return "ðŸ“… I'd love to help plan your itinerary! Please tell me:\n\n1ï¸âƒ£ How many days are you travelling?\n2ï¸âƒ£ Which cities or regions do you want to visit?\n3ï¸âƒ£ Your interests (adventure, relaxation, culture, etc.)\n4ï¸âƒ£ Your approximate budget\n\nWith those details, I'll create a personalised day-by-day plan!";
    }
    if (str_contains($msg, 'weather')) {
        return "ðŸŒ¤ï¸ Malawi has a warm, tropical climate:\n\n- â˜€ï¸ **Dry season** (Mayâ€“October): Perfect weather, cooler nights\n- ðŸŒ§ï¸ **Rainy season** (Novemberâ€“April): Lush landscapes, lower prices\n- ðŸŒ¡ï¸ Temperatures: 20Â°Câ€“35Â°C year-round (cooler in highlands)\n\nWhich destination's weather would you like more detail on?";
    }
    if (str_contains($msg, 'transport') || str_contains($msg, 'bus') || str_contains($msg, 'taxi') || str_contains($msg, 'car')) {
        return "ðŸšŒ Transport options in Malawi:\n\n- ðŸšŒ **Express buses**: Lilongwe â†” Blantyre â†” Mzuzu daily\n- ðŸš— **Car hire**: Available in major cities\n- âœˆï¸ **Airport transfers**: Book via our airport-transfer page\n- ðŸ›º **Mbanda ride-sharing**: Our popular carpooling service\n\nWould you like to book any of these? I can show available options.";
    }
    return "ðŸ¤” That's a great question! I can help you with events, accommodation, tours, transport, budgeting, and trip planning anywhere in Malawi. Could you give me a bit more detail about what you're looking for?";
}

function generateSuggestions(string $msg): array {
    $msg = strtolower($msg);
    if (str_contains($msg, 'hotel') || str_contains($msg, 'stay')) {
        return ['Check room availability', 'Best hotels in Lilongwe', 'Budget lodges near Lake Malawi'];
    }
    if (str_contains($msg, 'event')) {
        return ['Upcoming concerts', 'Sports events this month', 'Cultural festivals in Blantyre'];
    }
    if (str_contains($msg, 'tour') || str_contains($msg, 'safari')) {
        return ['Lake Malawi tours', 'Mount Mulanje hiking', 'Wildlife safaris'];
    }
    return ['Plan a weekend trip', 'Budget for 5 days in Malawi', 'Top attractions near Lilongwe'];
}

$reply = localRespond($message, $topEvents, $topAccom, $topTours);
echo json_encode([
    'success' => true,
    'reply' => $reply,
    'suggestions' => generateSuggestions($message),
    'service_status' => $serviceStatus,
    'service_message' => $serviceMessage,
]);
