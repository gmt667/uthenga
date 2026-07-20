<?php
/**
 * Uthenga — AI Budget Planner API
 * POST: { destination, days, travellers, style (budget|mid-range|luxury) }
 * Returns: itemised budget breakdown
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json; charset=utf-8');

$input       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$destination = trim($input['destination'] ?? 'Malawi');
$days        = max(1, min((int)($input['days'] ?? 3), 30));
$travellers  = max(1, min((int)($input['travellers'] ?? 1), 10));
$style       = in_array($input['style'] ?? 'mid-range', ['budget','mid-range','luxury']) ? ($input['style'] ?? 'mid-range') : 'mid-range';

// ── Cost tables (MWK) per person per day ──────────────────────────────────────
$costs = [
    'budget' => [
        'accommodation' => 28000,
        'meals'         => 8000,
        'transport'     => 12000,
        'activities'    => 5000,
        'misc'          => 3000,
    ],
    'mid-range' => [
        'accommodation' => 75000,
        'meals'         => 20000,
        'transport'     => 25000,
        'activities'    => 18000,
        'misc'          => 8000,
    ],
    'luxury' => [
        'accommodation' => 250000,
        'meals'         => 60000,
        'transport'     => 80000,
        'activities'    => 60000,
        'misc'          => 25000,
    ],
];

// Location modifiers (some destinations cost more)
$locationMod = 1.0;
$destLower   = strtolower($destination);
if (str_contains($destLower, 'mangochi') || str_contains($destLower, 'lake malawi')) {
    $locationMod = 1.1;
} elseif (str_contains($destLower, 'mzuzu') || str_contains($destLower, 'northern')) {
    $locationMod = 0.95;
}

$base = $costs[$style];

// ── Calculate items ───────────────────────────────────────────────────────────
$items = [];
$total = 0;

foreach ($base as $category => $costPerPersonPerDay) {
    $amount     = (int)round($costPerPersonPerDay * $locationMod * $travellers * $days);
    $items[]    = [
        'category'     => ucfirst($category),
        'amount'       => $amount,
        'per_person'   => (int)round($costPerPersonPerDay * $locationMod),
        'days'         => $days,
        'travellers'   => $travellers,
    ];
    $total += $amount;
}

// ── Pull live Uthenga prices for comparison ───────────────────────────────────
$liveListings = dbQuery("
    SELECT listing_type, meta
    FROM listings
    WHERE is_active = 1
      AND location LIKE ?
    LIMIT 20
", ['%' . $destination . '%']);

$livePrices = ['accommodation' => [], 'event' => [], 'tour' => []];
foreach ($liveListings as $ll) {
    $m = json_decode($ll['meta'] ?? '{}', true) ?? [];
    $type = $ll['listing_type'];
    if ($type === 'accommodation' && isset($m['rooms'][0]['pricePerNight'])) {
        $livePrices['accommodation'][] = (float)$m['rooms'][0]['pricePerNight'];
    } elseif ($type === 'event' && isset($m['standardTicketPrice'])) {
        $livePrices['event'][] = (float)$m['standardTicketPrice'];
    } elseif ($type === 'tour' && isset($m['pricePerPerson'])) {
        $livePrices['tour'][] = (float)$m['pricePerPerson'];
    }
}

$liveAvg = [];
foreach ($livePrices as $k => $vals) {
    $liveAvg[$k] = count($vals) > 0 ? (int)round(array_sum($vals) / count($vals)) : null;
}

echo json_encode([
    'success'     => true,
    'destination' => $destination,
    'days'        => $days,
    'travellers'  => $travellers,
    'style'       => $style,
    'currency'    => 'MWK',
    'items'       => $items,
    'total'       => $total,
    'per_person'  => (int)round($total / $travellers),
    'live_prices' => $liveAvg,
    'tips'        => [
        "Travelling in dry season (May–Oct) reduces accommodation costs by ~20%",
        "Book transport 48hrs in advance for best prices on Uthenga",
        "Use promo codes at checkout for event ticket discounts",
    ]
]);
