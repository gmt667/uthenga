<?php
/**
 * Uthenga — Destination Weather API
 * Fetches and caches Open-Meteo weather data.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$city = trim($_GET['city'] ?? '');
if ($city === '') {
    echo json_encode(['success' => false, 'message' => 'City parameter is required.']);
    exit;
}

// Major cities coordinates lookup
$cityCoords = [
    'blantyre'  => ['lat' => -15.7861, 'lon' => 35.0058],
    'lilongwe'  => ['lat' => -13.9626, 'lon' => 33.7741],
    'mzuzu'     => ['lat' => -11.4655, 'lon' => 33.9952],
    'mangochi'  => ['lat' => -14.4778, 'lon' => 35.2653],
    'zomba'     => ['lat' => -15.3833, 'lon' => 35.3167],
    'nkhata bay'=> ['lat' => -11.6067, 'lon' => 34.2917],
    'mulanje'   => ['lat' => -15.9416, 'lon' => 35.6413],
    'liwonde'   => ['lat' => -15.0667, 'lon' => 35.2333],
    'salima'    => ['lat' => -13.7833, 'lon' => 34.4333],
];

$normalizedCity = strtolower($city);
$coords = $cityCoords[$normalizedCity] ?? null;

if (!$coords) {
    // If not found in static list, default to Lilongwe
    $coords = $cityCoords['lilongwe'];
}

// Check cache
$now = date('Y-m-d H:i:s');
$cached = dbQueryOne("SELECT * FROM weather_cache WHERE LOWER(city) = ? AND expires_at > ?", [$normalizedCity, $now]);

if ($cached) {
    echo json_encode([
        'success' => true,
        'city' => $cached['city'],
        'weather' => json_decode($cached['weather_data'], true),
        'cached' => true
    ]);
    exit;
}

// Fetch from Open-Meteo
$lat = $coords['lat'];
$lon = $coords['lon'];
$url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current_weather=true&timezone=Africa/Blantyre";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_USERAGENT, 'UthengaMarketplace/1.0');
$response = curl_exec($ch);
curl_close($ch);

if ($response) {
    $data = json_decode($response, true);
    if (isset($data['current_weather'])) {
        $weatherData = $data['current_weather'];
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour cache TTL

        // Save to cache
        dbExecute("INSERT INTO weather_cache (city, latitude, longitude, weather_data, fetched_at, expires_at)
                   VALUES (?, ?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), weather_data = VALUES(weather_data), fetched_at = VALUES(fetched_at), expires_at = VALUES(expires_at)",
                  [$city, $lat, $lon, json_encode($weatherData), $now, $expires]);

        echo json_encode([
            'success' => true,
            'city' => $city,
            'weather' => $weatherData,
            'cached' => false
        ]);
        exit;
    }
}

// Fallback in case of API failure
echo json_encode([
    'success' => false,
    'message' => 'Failed to retrieve real-time weather information.'
]);
exit;
