<?php
/**
 * Local development fixtures for exercising TIE discovery, availability UI,
 * inventory holds, and PayChangu test checkout. This script never changes
 * non-TIE inventory and is safe to run repeatedly.
 *
 * Run: /opt/lampp/bin/php php_app/tools/seed_tie_test_inventory.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection is unavailable.\n");
    exit(1);
}

$fixtures = [
    'ticket_types' => [
        ['evt-1', 'TIE Demo General Admission', 'Development-only general admission ticket.', 5000.00, 80, 80, 0],
        ['evt-1', 'TIE Demo VIP Admission', 'Development-only VIP admission ticket.', 15000.00, 20, 20, 1],
        ['evt-3', 'TIE Demo Hiker Pass', 'Development-only hiking event pass.', 3500.00, 40, 40, 0],
        ['evt-3', 'TIE Demo Acoustic Pass', 'Development-only evening-session pass.', 6500.00, 25, 25, 1],
        ['evt-5', 'TIE Demo VIP Ticket', 'Development-only VIP sports ticket.', 15000.00, 12, 12, 1],
    ],
    'seat_classes' => [
        ['trans-1', 'TIE Demo Standard Seat', 'Development-only coach seat.', 18000.00, 30, 30, 0],
        ['trans-1', 'TIE Demo Comfort Seat', 'Development-only upgraded coach seat.', 26000.00, 12, 12, 1],
        ['trans-2', 'TIE Demo Shuttle Seat', 'Development-only express-shuttle seat.', 22000.00, 14, 14, 0],
        ['trans-2', 'TIE Demo Shuttle Comfort', 'Development-only front-row shuttle seat.', 28000.00, 6, 6, 1],
    ],
    'room_types' => [
        ['acc-1', 'TIE Demo Garden Room', 'Development-only lodge room.', 45000.00, 6, 6, 2, 0],
        ['acc-1', 'TIE Demo Family Villa', 'Development-only family villa.', 90000.00, 2, 2, 4, 1],
        ['acc-2', 'TIE Demo City King', 'Development-only city-hotel room.', 95000.00, 8, 8, 2, 0],
        ['acc-2', 'TIE Demo Executive Suite', 'Development-only executive suite.', 150000.00, 3, 3, 2, 1],
    ],
];

$listingTypes = ['ticket_types' => 'event', 'seat_classes' => 'transport', 'room_types' => 'accommodation'];
$created = 0;
$existing = 0;
$pdo->beginTransaction();
try {
    foreach ($fixtures as $table => $rows) {
        foreach ($rows as $row) {
            $listingId = $row[0];
            $listing = $pdo->prepare('SELECT listing_type FROM listings WHERE id = ? AND is_active = 1 LIMIT 1');
            $listing->execute([$listingId]);
            if ($listing->fetchColumn() !== $listingTypes[$table]) {
                throw new RuntimeException("Expected active {$listingTypes[$table]} listing {$listingId} was not found.");
            }
            $name = $row[1];
            $check = $pdo->prepare("SELECT id FROM {$table} WHERE listing_id = ? AND " . ($table === 'seat_classes' ? 'class_name' : ($table === 'room_types' ? 'room_name' : 'name')) . ' = ? LIMIT 1');
            $check->execute([$listingId, $name]);
            if ($check->fetchColumn()) {
                $existing++;
                continue;
            }
            if ($table === 'ticket_types') {
                $insert = $pdo->prepare('INSERT INTO ticket_types (listing_id, name, description, price, total_quantity, remaining_quantity, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)');
                $insert->execute($row);
            } elseif ($table === 'seat_classes') {
                $insert = $pdo->prepare('INSERT INTO seat_classes (listing_id, class_name, description, price, total_seats, remaining_seats, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
                $insert->execute($row);
            } else {
                [$listingId, $name, $description, $price, $total, $available, $occupancy, $sort] = $row;
                $insert = $pdo->prepare('INSERT INTO room_types (listing_id, room_name, description, price_per_night, total_rooms, available_rooms, max_occupancy, amenities, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
                $insert->execute([$listingId, $name, $description, $price, $total, $available, $occupancy, json_encode(['development_fixture' => true, 'source' => 'tie_test_inventory']), $sort]);
            }
            $created++;
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}

echo "TIE test inventory seeded: {$created} created, {$existing} already present.\n";
