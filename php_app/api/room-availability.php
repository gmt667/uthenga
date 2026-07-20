<?php
/**
 * Uthenga — Room Availability API
 * Supports:
 * - legacy listings.meta.rooms arrays
 * - room_types table
 * - property_rooms / room_availability tables
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

$listingId = trim((string)($_GET['property_id'] ?? $_GET['listing_id'] ?? ''));
$checkIn = trim((string)($_GET['check_in'] ?? ''));
$checkOut = trim((string)($_GET['check_out'] ?? ''));

if ($listingId === '' || $checkIn === '' || $checkOut === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields: property_id, check_in, check_out.']);
    exit;
}

$start = strtotime($checkIn);
$end = strtotime($checkOut);
if ($start === false || $end === false || $start >= $end) {
    echo json_encode(['success' => false, 'message' => 'Invalid date range specified.']);
    exit;
}

$listing = dbQueryOne("SELECT * FROM listings WHERE id = ? LIMIT 1", [$listingId]);
if (!$listing) {
    echo json_encode(['success' => false, 'message' => 'Property not found.']);
    exit;
}

$rooms = [];

if (uthenga_table_exists('room_types')) {
    $rooms = dbQuery(
        "SELECT id, room_name AS room_name, description, price_per_night, total_rooms AS total_units, available_rooms AS available_units, max_occupancy
         FROM room_types
         WHERE listing_id = ? AND is_active = 1
         ORDER BY sort_order ASC, id ASC",
        [$listingId]
    ) ?: [];
} elseif (uthenga_table_exists('property_rooms')) {
    $rooms = dbQuery(
        "SELECT id, room_name, room_type, price_per_night, total_units, max_occupancy, status
         FROM property_rooms
         WHERE property_id = ? AND status = 'active'
         ORDER BY id ASC",
        [$listingId]
    ) ?: [];
} else {
    $meta = json_decode($listing['meta'] ?? '{}', true) ?: [];
    $legacyRooms = $meta['rooms'] ?? [];
    foreach ($legacyRooms as $idx => $room) {
        $rooms[] = [
            'id' => $room['id'] ?? ('legacy-room-' . ($idx + 1)),
            'room_name' => $room['name'] ?? ('Room ' . ($idx + 1)),
            'price_per_night' => (float)($room['pricePerNight'] ?? 0),
            'total_units' => (int)($room['availableRooms'] ?? 1),
            'available_units' => (int)($room['availableRooms'] ?? 1),
            'max_occupancy' => (int)($room['capacity'] ?? 2),
        ];
    }
}

$availabilityResult = [];

foreach ($rooms as $r) {
    $roomId = (string)($r['id'] ?? '');
    $maxUnits = (int)($r['total_units'] ?? $r['total_rooms'] ?? 1);
    $availableUnits = isset($r['available_units']) ? (int)$r['available_units'] : $maxUnits;
    $isAvailable = $availableUnits > 0;

    if ($isAvailable && uthenga_table_exists('room_availability') && is_numeric($roomId)) {
        $curr = $start;
        while ($curr < $end) {
            $dateStr = date('Y-m-d', $curr);
            $avail = dbQueryOne(
                "SELECT available_units, blocked_units FROM room_availability WHERE property_room_id = ? AND stay_date = ?",
                [$roomId, $dateStr]
            );

            if ($avail) {
                $available = (int)($avail['available_units'] ?? 0);
                $blocked = (int)($avail['blocked_units'] ?? 0);
                if (($available - $blocked) <= 0) {
                    $isAvailable = false;
                    break;
                }
            }
            $curr += 86400;
        }
    }

    $availabilityResult[] = [
        'room_id' => $roomId,
        'room_name' => $r['room_name'] ?? $r['room_type'] ?? 'Room',
        'price_per_night' => (float)($r['price_per_night'] ?? 0),
        'max_occupancy' => (int)($r['max_occupancy'] ?? 2),
        'available' => $isAvailable
    ];
}

echo json_encode([
    'success' => true,
    'property_id' => $listingId,
    'check_in' => $checkIn,
    'check_out' => $checkOut,
    'rooms' => $availabilityResult
]);
exit;
