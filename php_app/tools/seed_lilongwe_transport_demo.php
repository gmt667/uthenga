<?php
/**
 * Development-only Lilongwe transport fixtures for the TIE Agent Workspace.
 *
 * It creates isolated demo vendors, their active transport profiles, normal
 * marketplace listings, seat classes, and live loading runs. It does not
 * alter any real vendor, listing, booking, or payment record.
 *
 * Run:
 *   /opt/lampp/bin/php php_app/tools/seed_lilongwe_transport_demo.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "Database connection is unavailable.\n");
    exit(1);
}

$services = [
    [
        'vendor_id' => 'tie-demo-ltaxi-a18',
        // This is the dedicated two-sided test route. Sign in as this driver,
        // then accept a customer's seat request from Driver Operations.
        'vendor_name' => 'Patrick Demo — Uthenga Test Driver',
        'vendor_email' => 'driver.demo@uthenga.test',
        'test_driver' => true,
        'profile_id' => 'f1c66288-8df4-4e7f-92d1-5c496d303201',
        'listing_id' => 'TIEDEMO-LTX-A18',
        'run_id' => '3e9412d6-a6d8-4945-875d-b19f37f53801',
        'title' => 'TIE Demo Taxi: Area 25 to Lilongwe City Centre',
        'description' => 'Development-only shared taxi currently loading passengers from Area 25.',
        'origin' => 'Area 25',
        'destination' => 'Lilongwe City Centre',
        'pickup' => 'Area 25 Community Ground, Lilongwe',
        'vehicle' => 'taxi',
        'capacity' => 4,
        'seats' => 3,
        'fare' => 2500.00,
        'departure_local' => '08:00',
        'latitude' => -13.91110000,
        'longitude' => 33.75640000,
    ],
    [
        'vendor_id' => 'tie-demo-ltaxi-a25',
        'vendor_name' => 'TIE Demo Area 25 Taxi',
        'vendor_email' => 'tie-demo-a25-taxi@example.test',
        'profile_id' => '02754f74-5491-4ff1-a8f5-1b96ec9f8302',
        'listing_id' => 'TIEDEMO-LTX-A25',
        'run_id' => '6a99a437-58dc-4c7d-9817-30b4dd4f9e02',
        'title' => 'TIE Demo Taxi: Area 25 to Gateway Mall',
        'description' => 'Development-only shared taxi currently loading passengers from Area 25.',
        'origin' => 'Area 25',
        'destination' => 'Gateway Mall',
        'pickup' => 'Area 25 Community Ground, Lilongwe',
        'vehicle' => 'taxi',
        'capacity' => 4,
        'seats' => 2,
        'fare' => 2200.00,
        'departure_local' => '06:15',
        'latitude' => -13.91110000,
        'longitude' => 33.75640000,
    ],
    [
        'vendor_id' => 'tie-demo-bus-city',
        'vendor_name' => 'TIE Demo City Minibus',
        'vendor_email' => 'tie-demo-city-bus@example.test',
        'profile_id' => '56b48b60-9907-4b88-8957-b1f6d518bd03',
        'listing_id' => 'TIEDEMO-BUS-A18',
        'run_id' => 'cb95dca9-f7ba-46d9-a892-ee0842c8e703',
        'title' => 'TIE Demo Minibus: Area 25 to Area 18',
        'description' => 'Development-only city minibus currently loading passengers from Area 25.',
        'origin' => 'Area 25',
        'destination' => 'Area 18',
        'pickup' => 'Area 25 Community Ground, Lilongwe',
        'vehicle' => 'minibus',
        'capacity' => 18,
        'seats' => 11,
        'fare' => 1500.00,
        'departure_local' => '06:30',
        'latitude' => -13.91110000,
        'longitude' => 33.75640000,
    ],
    [
        'vendor_id' => 'tie-demo-bus-chin',
        'vendor_name' => 'TIE Demo Chinsapo Bus',
        'vendor_email' => 'tie-demo-chinsapo-bus@example.test',
        'profile_id' => 'a019b431-4317-4c98-9cc3-f7d3d8d60504',
        'listing_id' => 'TIEDEMO-BUS-CHIN',
        'run_id' => 'e7c40b62-9f3a-451a-8dac-d6af56db1f04',
        'title' => 'TIE Demo Bus: Area 25 to Chinsapo',
        'description' => 'Development-only local bus currently loading passengers from Area 25 for Chinsapo.',
        'origin' => 'Area 25',
        'destination' => 'Chinsapo',
        'pickup' => 'Area 25 Community Ground, Lilongwe',
        'vehicle' => 'bus',
        'capacity' => 30,
        'seats' => 19,
        'fare' => 1800.00,
        'departure_local' => '06:45',
        'latitude' => -13.91110000,
        'longitude' => 33.75640000,
    ],
    [
        'vendor_id' => 'tie-demo-bus-kanengo',
        'vendor_name' => 'TIE Demo Kanengo Bus',
        'vendor_email' => 'tie-demo-kanengo-bus@example.test',
        'profile_id' => '9010f052-35a4-47fc-a1d8-5ed58c868105',
        'listing_id' => 'TIEDEMO-COACH-MZU',
        'run_id' => '960e98dd-709c-4f8f-9f1c-2262307b3a05',
        'title' => 'TIE Demo Bus: Area 25 to Kanengo',
        'description' => 'Development-only local bus currently loading passengers from Area 25 for Kanengo.',
        'origin' => 'Area 25',
        'destination' => 'Kanengo',
        'pickup' => 'Area 25 Community Ground, Lilongwe',
        'vehicle' => 'bus',
        'capacity' => 30,
        'seats' => 17,
        'fare' => 2000.00,
        'departure_local' => '07:00',
        'latitude' => -13.91110000,
        'longitude' => 33.75640000,
    ],
];

$passwordHash = password_hash('development-fixture-only', PASSWORD_BCRYPT);
$testDriverPasswordHash = password_hash('UthengaDemo!2026', PASSWORD_BCRYPT);
$created = ['vendors' => 0, 'listings' => 0, 'seats' => 0, 'profiles' => 0, 'runs' => 0];
$preservedRuns = 0;

$pdo->beginTransaction();
try {
    foreach ($services as $service) {
        $vendorExists = $pdo->prepare('SELECT id FROM users WHERE id=? LIMIT 1');
        $vendorExists->execute([$service['vendor_id']]);
        if (!$vendorExists->fetchColumn()) $created['vendors']++;
        // full_name is a generated column in the current XAMPP schema, so the
        // fixture writes the canonical name field only.
        $pdo->prepare("INSERT INTO users (id, name, email, password_hash, role, is_approved, account_status) VALUES (?, ?, ?, ?, 'Transport Provider', 1, 'active') ON DUPLICATE KEY UPDATE name=VALUES(name), email=VALUES(email), role='Transport Provider', is_approved=1, account_status='active'")
            ->execute([$service['vendor_id'], $service['vendor_name'], $service['vendor_email'], $passwordHash]);
        if (!empty($service['test_driver'])) {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$testDriverPasswordHash, $service['vendor_id']]);
        }

        $meta = [
            'development_fixture' => true,
            'fixture_source' => 'seed_lilongwe_transport_demo',
            'vehicleType' => ucfirst($service['vehicle']),
            'routeFrom' => $service['origin'],
            'routeTo' => $service['destination'],
            'departureTime' => $service['departure_local'],
            'arrivalTime' => null,
            'scheduleDays' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'pricePerSeat' => $service['fare'],
            'baseFare' => $service['fare'],
            'availableSeats' => $service['seats'],
            'totalSeats' => $service['capacity'],
            'pickupLocation' => $service['pickup'],
        ];
        $listingExists = $pdo->prepare('SELECT id FROM listings WHERE id=? LIMIT 1');
        $listingExists->execute([$service['listing_id']]);
        if (!$listingExists->fetchColumn()) $created['listings']++;
        $pdo->prepare('INSERT INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, is_active, meta, gps_lat, gps_lng, location_source, location_verification_status, driver_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 4.8, 0, 1, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), location=VALUES(location), vendor_name=VALUES(vendor_name), is_active=1, meta=VALUES(meta), gps_lat=VALUES(gps_lat), gps_lng=VALUES(gps_lng), location_source=VALUES(location_source), driver_name=VALUES(driver_name)')
            ->execute([$service['listing_id'], 'transport', $service['title'], $service['description'], $service['pickup'], 'assets/images/hero/transport-van.png', json_encode([]), $service['vendor_id'], $service['vendor_name'], json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $service['latitude'], $service['longitude'], 'development_fixture', 'unverified', $service['vendor_name']]);

        $seatName = 'TIE Demo Standard Seat';
        $seatLookup = $pdo->prepare('SELECT id FROM seat_classes WHERE listing_id=? AND class_name=? LIMIT 1');
        $seatLookup->execute([$service['listing_id'], $seatName]);
        $seatId = $seatLookup->fetchColumn();
        if (!$seatId) {
            $pdo->prepare('INSERT INTO seat_classes (listing_id, class_name, description, price, total_seats, remaining_seats, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 0, 1)')->execute([$service['listing_id'], $seatName, 'Development-only live transport seat.', $service['fare'], $service['capacity'], $service['seats']]);
            $seatId = $pdo->lastInsertId();
            $created['seats']++;
        } else {
            $pdo->prepare('UPDATE seat_classes SET description=?, price=?, total_seats=?, remaining_seats=LEAST(remaining_seats, ?), is_active=1 WHERE id=?')->execute(['Development-only live transport seat.', $service['fare'], $service['capacity'], $service['capacity'], $seatId]);
        }

        $profile = [
            'profile_name' => $service['title'], 'vehicle_type' => $service['vehicle'], 'origin' => $service['origin'],
            'destination' => $service['destination'], 'pickup_location' => $service['pickup'], 'departure_time' => '00:00',
            'fare_per_seat' => $service['fare'], 'total_seats' => $service['capacity'], 'schedule_days' => $meta['scheduleDays'],
            'description' => $service['description'], 'development_fixture' => true,
        ];
        $profileExists = $pdo->prepare('SELECT id FROM tie_vendor_service_profiles WHERE id=? LIMIT 1');
        $profileExists->execute([$service['profile_id']]);
        if (!$profileExists->fetchColumn()) $created['profiles']++;
        $pdo->prepare("INSERT INTO tie_vendor_service_profiles (id, vendor_id, profile_type, profile_name, status, is_active, listing_id, configuration, activated_at) VALUES (?, ?, 'transport', ?, 'ACTIVE', 1, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE vendor_id=VALUES(vendor_id), profile_name=VALUES(profile_name), status='ACTIVE', is_active=1, listing_id=VALUES(listing_id), configuration=VALUES(configuration), deactivated_at=NULL")
            ->execute([$service['profile_id'], $service['vendor_id'], $service['title'], $service['listing_id'], json_encode($profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);

        $runLookup = $pdo->prepare('SELECT id FROM tie_transport_runs WHERE id=? LIMIT 1 FOR UPDATE');
        $runLookup->execute([$service['run_id']]);
        $runExists = (bool) $runLookup->fetchColumn();
        $hasLiveSession = $pdo->prepare("SELECT COUNT(*) FROM tie_transport_sessions WHERE run_id=? AND status IN ('PENDING_VENDOR', 'ACCEPTED', 'ARRIVED', 'BOARDED')");
        $hasLiveSession->execute([$service['run_id']]);
        if ($runExists && (int) $hasLiveSession->fetchColumn() > 0) {
            $preservedRuns++;
            continue;
        }

        $malawiTimeZone = new DateTimeZone('Africa/Blantyre');
        $now = new DateTimeImmutable('now', $malawiTimeZone);
        $departureLocal = new DateTimeImmutable($now->format('Y-m-d') . ' ' . $service['departure_local'], $malawiTimeZone);
        if ($departureLocal <= $now) $departureLocal = $departureLocal->modify('+1 day');
        $departure = $departureLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        if (!$runExists) $created['runs']++;
        $pdo->prepare("INSERT INTO tie_transport_runs (id, service_id, vendor_id, driver_user_id, seat_class_id, service_date, planned_departure_at, capacity, remaining_seats, status, loading_status, loading_location, driver_note, loading_started_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'LOADING', 'LOADING', ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE service_id=VALUES(service_id), vendor_id=VALUES(vendor_id), driver_user_id=VALUES(driver_user_id), seat_class_id=VALUES(seat_class_id), service_date=VALUES(service_date), planned_departure_at=VALUES(planned_departure_at), capacity=VALUES(capacity), remaining_seats=VALUES(remaining_seats), status='LOADING', loading_status='LOADING', loading_location=VALUES(loading_location), driver_note=VALUES(driver_note), loading_started_at=UTC_TIMESTAMP(), expired_at=NULL, version=version+1")
            ->execute([$service['run_id'], $service['listing_id'], $service['vendor_id'], $service['vendor_id'], $seatId, substr($departure, 0, 10), $departure, $service['capacity'], $service['seats'], $service['pickup'], 'TIE demo: driver is loading passengers now.']);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $error;
}

echo "Lilongwe TIE transport demo seeded.\n";
echo "Created: {$created['vendors']} vendors, {$created['listings']} listings, {$created['seats']} seat classes, {$created['profiles']} profiles, {$created['runs']} live runs.\n";
if ($preservedRuns > 0) echo "Preserved {$preservedRuns} run(s) with active passenger sessions.\n";
echo "Dedicated two-sided test: Area 25 to Lilongwe City Centre at 08:00 CAT, assigned to Patrick Demo (driver.demo@uthenga.test).\n";
echo "Try these exact destinations in Quick Travel: Lilongwe City Centre, Gateway Mall, Area 18, Chinsapo, or Kanengo.\n";
