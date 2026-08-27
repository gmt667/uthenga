<?php
/**
 * Real MariaDB contention test: two PHP processes commit against one final room.
 *
 * Run directly:
 *   /opt/lampp/bin/php php_app/tests/tie/AccommodationFinalRoomConcurrencyTest.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/tie/bootstrap.php';

function final_room_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function final_room_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function final_room_cleanup(PDO $db, array $fixture): void
{
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $db->beginTransaction();
    try {
        if (!empty($fixture['room_type_id'])) {
            $db->prepare('DELETE FROM tie_accommodation_hold_nights WHERE room_type_id=?')->execute([(int) $fixture['room_type_id']]);
        }
        if (!empty($fixture['plan_id'])) {
            $db->prepare('DELETE FROM tie_inventory_holds WHERE plan_id=?')->execute([(string) $fixture['plan_id']]);
        }
        if (!empty($fixture['room_type_id'])) {
            $db->prepare('DELETE FROM tie_accommodation_inventory_nights WHERE room_type_id=?')->execute([(int) $fixture['room_type_id']]);
        }
        if (!empty($fixture['rate_plan_id'])) {
            $db->prepare('DELETE FROM tie_accommodation_rate_plans WHERE id=?')->execute([(string) $fixture['rate_plan_id']]);
        }
        if (!empty($fixture['policy_id'])) {
            $db->prepare('DELETE FROM tie_accommodation_cancellation_policies WHERE id=?')->execute([(string) $fixture['policy_id']]);
        }
        if (!empty($fixture['room_type_id'])) {
            $db->prepare('DELETE FROM room_types WHERE id=?')->execute([(int) $fixture['room_type_id']]);
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

final_room_assert($pdo instanceof PDO, 'A live MariaDB connection is required.');
final_room_assert(function_exists('proc_open'), 'proc_open is required for independent worker processes.');

$fixture = [];
$processes = [];
$temporaryDirectory = sys_get_temp_dir() . '/uthenga-final-room-' . bin2hex(random_bytes(6));
final_room_assert(mkdir($temporaryDirectory, 0700), 'The synchronization directory can be created.');
$fixturePath = $temporaryDirectory . '/fixture.json';
$workerPath = __DIR__ . '/workers/accommodation-final-room-worker.php';

try {
    $property = $pdo->query("SELECT id,vendor_id,listing_id FROM tie_accommodation_properties WHERE status IN ('PUBLISHED','ACTIVE') AND listing_id IS NOT NULL ORDER BY created_at,id LIMIT 1")->fetch();
    final_room_assert(is_array($property), 'A published accommodation property is available as the fixture parent.');

    $suffix = strtoupper(bin2hex(random_bytes(5)));
    $checkIn = (new DateTimeImmutable('+420 days'))->format('Y-m-d');
    $checkOut = (new DateTimeImmutable($checkIn))->modify('+1 day')->format('Y-m-d');
    $fixture = [
        'property_id' => (string) $property['id'],
        'vendor_id' => (string) $property['vendor_id'],
        'listing_id' => (string) $property['listing_id'],
        'policy_id' => final_room_uuid(),
        'rate_plan_id' => final_room_uuid(),
        'plan_id' => 'CONCUR-' . $suffix,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'hold_ids' => ['1' => final_room_uuid(), '2' => final_room_uuid()],
    ];

    $pdo->beginTransaction();
    $room = $pdo->prepare('INSERT INTO room_types (listing_id,property_id,room_name,description,price_per_night,total_rooms,available_rooms,max_occupancy,adults_capacity,children_capacity,amenities,room_images,sort_order,is_active) VALUES (?,?,?,?,10000,1,1,2,2,0,"[]","[]",255,1)');
    $room->execute([$fixture['listing_id'], $fixture['property_id'], 'Concurrency Fixture ' . $suffix, 'Isolated final-room contention fixture']);
    $fixture['room_type_id'] = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO tie_accommodation_cancellation_policies (id,property_id,name,free_cancel_hours,penalty_percent,no_show_percent) VALUES (?,?,?,24,100,100)')->execute([$fixture['policy_id'], $fixture['property_id'], 'Concurrency policy ' . $suffix]);
    $pdo->prepare('INSERT INTO tie_accommodation_rate_plans (id,property_id,room_type_id,cancellation_policy_id,name,base_rate,booking_mode,payment_mode,minimum_stay,maximum_stay) VALUES (?,?,?,?,?,10000,"INSTANT","FULL",1,30)')->execute([$fixture['rate_plan_id'], $fixture['property_id'], $fixture['room_type_id'], $fixture['policy_id'], 'Concurrency rate ' . $suffix]);
    $pdo->prepare('INSERT INTO tie_accommodation_inventory_nights (property_id,room_type_id,stay_date,capacity_rooms,manual_blocked_rooms,maintenance_blocked_rooms,blocked_rooms,held_rooms,confirmed_rooms,closed,version) VALUES (?,?,?,1,0,0,0,0,0,0,1)')->execute([$fixture['property_id'], $fixture['room_type_id'], $fixture['check_in']]);
    $pdo->commit();

    file_put_contents($fixturePath, json_encode($fixture, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), LOCK_EX);
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    foreach (['1', '2'] as $workerId) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, $workerPath, $fixturePath, $temporaryDirectory, $workerId], $descriptors, $pipes, __DIR__);
        final_room_assert(is_resource($process), 'Worker ' . $workerId . ' starts in an independent process.');
        fclose($pipes[0]);
        $processes[$workerId] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
    }

    $readyDeadline = microtime(true) + 15.0;
    while (!(is_file($temporaryDirectory . '/ready-1') && is_file($temporaryDirectory . '/ready-2'))) {
        final_room_assert(microtime(true) < $readyDeadline, 'Both workers reach the synchronized barrier.');
        usleep(10_000);
        clearstatcache();
    }
    file_put_contents($temporaryDirectory . '/go', 'commit', LOCK_EX);

    $results = [];
    foreach ($processes as $workerId => &$worker) {
        $stdout = trim((string) stream_get_contents($worker['stdout']));
        $stderr = trim((string) stream_get_contents($worker['stderr']));
        fclose($worker['stdout']);
        fclose($worker['stderr']);
        $exitCode = proc_close($worker['process']);
        $worker['process'] = null;
        $result = json_decode($stdout, true);
        final_room_assert(is_array($result), 'Worker ' . $workerId . ' returns structured evidence. stderr=' . $stderr);
        $result['exit_code'] = $exitCode;
        $results[$workerId] = $result;
    }
    unset($worker);

    $successes = array_values(array_filter($results, static fn(array $result): bool => ($result['success'] ?? false) === true));
    $failures = array_values(array_filter($results, static fn(array $result): bool => ($result['success'] ?? false) !== true));
    final_room_assert(count($successes) === 1, 'Exactly one independently committed hold wins the final room. Results=' . json_encode($results));
    final_room_assert(count($failures) === 1, 'Exactly one competing hold is rejected.');
    final_room_assert(($failures[0]['error_type'] ?? '') === 'validation_error', 'The losing contender fails through deterministic inventory validation.');

    $nightQuery = $pdo->prepare('SELECT capacity_rooms,blocked_rooms,held_rooms,confirmed_rooms,(capacity_rooms-blocked_rooms-held_rooms-confirmed_rooms) AS sellable_rooms FROM tie_accommodation_inventory_nights WHERE room_type_id=? AND stay_date=?');
    $nightQuery->execute([$fixture['room_type_id'], $fixture['check_in']]);
    $night = $nightQuery->fetch();
    final_room_assert(is_array($night), 'The isolated inventory night remains queryable.');
    final_room_assert((int) $night['held_rooms'] === 1, 'Only one room is held after both committed attempts.');
    final_room_assert((int) $night['sellable_rooms'] === 0, 'The final room has zero remaining sellable balance.');
    final_room_assert((int) $night['sellable_rooms'] >= 0, 'Sellable inventory never becomes negative.');

    $holds = $pdo->prepare("SELECT COUNT(*) FROM tie_inventory_holds WHERE plan_id=? AND status='ACTIVE'");
    $holds->execute([$fixture['plan_id']]);
    final_room_assert((int) $holds->fetchColumn() === 1, 'Exactly one generic inventory hold commits.');
    $holdNights = $pdo->prepare("SELECT COUNT(*) FROM tie_accommodation_hold_nights WHERE room_type_id=? AND stay_date=? AND status='ACTIVE'");
    $holdNights->execute([$fixture['room_type_id'], $fixture['check_in']]);
    final_room_assert((int) $holdNights->fetchColumn() === 1, 'Exactly one nightly accommodation hold commits.');

    echo "Parallel final-room contention passed: one committed hold, one validation rejection, sellable balance 0.\n";
} finally {
    foreach ($processes as &$worker) {
        if (is_resource($worker['stdout'] ?? null)) fclose($worker['stdout']);
        if (is_resource($worker['stderr'] ?? null)) fclose($worker['stderr']);
        if (is_resource($worker['process'] ?? null)) {
            $status = proc_get_status($worker['process']);
            if ($status['running']) proc_terminate($worker['process']);
            proc_close($worker['process']);
        }
    }
    unset($worker);
    if ($fixture !== []) {
        final_room_cleanup($pdo, $fixture);
        if (!empty($fixture['room_type_id'])) {
            $remaining = $pdo->prepare('SELECT (SELECT COUNT(*) FROM room_types WHERE id=?) + (SELECT COUNT(*) FROM tie_accommodation_inventory_nights WHERE room_type_id=?) + (SELECT COUNT(*) FROM tie_accommodation_hold_nights WHERE room_type_id=?)');
            $remaining->execute([$fixture['room_type_id'], $fixture['room_type_id'], $fixture['room_type_id']]);
            final_room_assert((int) $remaining->fetchColumn() === 0, 'All isolated inventory fixtures are removed.');
        }
    }
    foreach (glob($temporaryDirectory . '/*') ?: [] as $path) {
        if (is_file($path)) unlink($path);
    }
    if (is_dir($temporaryDirectory)) rmdir($temporaryDirectory);
}
