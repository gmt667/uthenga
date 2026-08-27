<?php
/** One independently bootstrapped contender for the final-room concurrency test. */
declare(strict_types=1);

$fixturePath = (string) ($argv[1] ?? '');
$barrierDirectory = (string) ($argv[2] ?? '');
$workerId = (string) ($argv[3] ?? '');

if ($fixturePath === '' || $barrierDirectory === '' || !in_array($workerId, ['1', '2'], true)) {
    fwrite(STDERR, "Usage: accommodation-final-room-worker.php <fixture.json> <barrier-dir> <1|2>\n");
    exit(64);
}

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';

if (!$pdo instanceof PDO) {
    fwrite(STDOUT, json_encode(['success' => false, 'error_type' => 'database_unavailable']) . "\n");
    exit(2);
}

$fixture = json_decode((string) file_get_contents($fixturePath), true);
if (!is_array($fixture)) {
    fwrite(STDOUT, json_encode(['success' => false, 'error_type' => 'invalid_fixture']) . "\n");
    exit(2);
}

$readyPath = $barrierDirectory . '/ready-' . $workerId;
$goPath = $barrierDirectory . '/go';
file_put_contents($readyPath, (string) getmypid(), LOCK_EX);

$deadline = microtime(true) + 20.0;
while (!is_file($goPath)) {
    if (microtime(true) >= $deadline) {
        fwrite(STDOUT, json_encode(['success' => false, 'error_type' => 'barrier_timeout']) . "\n");
        exit(3);
    }
    usleep(10_000);
    clearstatcache(true, $goPath);
}

$holdId = (string) $fixture['hold_ids'][$workerId];
try {
    $pdo->beginTransaction();
    $service = new UthengaAccommodationService($pdo);
    $service->acquireExternalHold(
        $holdId,
        (string) $fixture['listing_id'],
        (int) $fixture['room_type_id'],
        1,
        (string) $fixture['check_in'],
        (string) $fixture['check_out'],
    );
    $insert = $pdo->prepare('INSERT INTO tie_inventory_holds (id,user_id,plan_id,resource_type,resource_id,listing_id,quantity,start_date,end_date,status,expires_at,metadata) VALUES (?,?,?,?,?,?,?,?,?,"ACTIVE",DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),?)');
    $insert->execute([
        $holdId,
        (string) $fixture['vendor_id'],
        (string) $fixture['plan_id'],
        'room_type',
        (int) $fixture['room_type_id'],
        (string) $fixture['listing_id'],
        1,
        (string) $fixture['check_in'],
        (string) $fixture['check_out'],
        json_encode(['test_fixture' => true, 'worker' => $workerId], JSON_UNESCAPED_SLASHES),
    ]);
    $pdo->commit();
    fwrite(STDOUT, json_encode(['success' => true, 'worker' => $workerId, 'hold_id' => $holdId]) . "\n");
    exit(0);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $type = $error instanceof UthengaTieException ? $error->type() : get_class($error);
    fwrite(STDOUT, json_encode([
        'success' => false,
        'worker' => $workerId,
        'error_type' => $type,
        'message' => $error->getMessage(),
    ], JSON_UNESCAPED_SLASHES) . "\n");
    exit($error instanceof UthengaTieException ? 10 : 11);
}
