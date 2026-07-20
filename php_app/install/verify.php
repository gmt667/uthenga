<?php
/**
 * Uthenga — System Health & Installation Verification Checklist
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$checks = [];

// 1. PHP Version
$checks['php_version'] = [
    'title' => 'PHP Version 8.0+',
    'desc' => 'Current version: ' . PHP_VERSION,
    'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'pass' : 'fail'
];

// 2. PHP Extensions
$extensions = ['pdo', 'pdo_mysql', 'json', 'openssl', 'session', 'hash', 'filter'];
$missingExts = [];
foreach ($extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExts[] = $ext;
    }
}
$checks['php_exts'] = [
    'title' => 'Required PHP Extensions',
    'desc' => empty($missingExts) ? 'All required extensions loaded.' : 'Missing: ' . implode(', ', $missingExts),
    'status' => empty($missingExts) ? 'pass' : 'fail'
];

// 3. Database Connection
$dbConn = false;
try {
    require_once __DIR__ . '/../db.php';
    global $pdo;
    if ($pdo instanceof PDO) {
        $dbConn = true;
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
$checks['db_conn'] = [
    'title' => 'Database Connection',
    'desc' => $dbConn ? 'Successfully connected to database.' : 'Connection failed: ' . ($dbError ?? 'Unknown error'),
    'status' => $dbConn ? 'pass' : 'fail'
];

// 4. Critical Tables
$requiredTables = [
    'users', 'roles', 'user_roles', 'vendors', 'events', 'properties', 'tours', 
    'transport_providers', 'transport_routes', 'bookings', 'booking_items', 
    'transactions', 'two_factor_auth', 'device_sessions', 'login_alerts', 
    'loyalty_transactions', 'gift_vouchers', 'newsletter_subscribers', 
    'map_points', 'trip_itineraries'
];
$missingTables = [];
if ($dbConn) {
    $existingTables = [];
    try {
        $rows = dbQuery("SHOW TABLES");
        foreach ($rows as $row) {
            $existingTables[] = strtolower(current($row));
        }
        foreach ($requiredTables as $tbl) {
            if (!in_array(strtolower($tbl), $existingTables, true)) {
                $missingTables[] = $tbl;
            }
        }
    } catch (Throwable $e) {
        $missingTables = $requiredTables;
    }
} else {
    $missingTables = $requiredTables;
}
$checks['db_tables'] = [
    'title' => 'Database Tables',
    'desc' => empty($missingTables) ? 'All required tables are present.' : 'Missing: ' . implode(', ', $missingTables),
    'status' => ($dbConn && empty($missingTables)) ? 'pass' : 'fail'
];

// 5. Cache Dir Writable
$cacheDir = __DIR__ . '/../cache';
$isWritable = is_dir($cacheDir) && is_writable($cacheDir);
$checks['cache_writable'] = [
    'title' => 'Cache Directory Write Access',
    'desc' => $isWritable ? 'Cache directory exists and is writable.' : 'Cache directory not writable or does not exist.',
    'status' => $isWritable ? 'pass' : 'fail'
];

// Calculate overall status
$allPass = true;
foreach ($checks as $c) {
    if ($c['status'] !== 'pass') {
        $allPass = false;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Verification | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #030712;
      --surface: #0a0f1d;
      --border: rgba(255,255,255,0.08);
      --text: #f3f4f6;
      --text-muted: #9ca3af;
      --pass: #10b981;
      --fail: #ef4444;
      --primary: #38bdf8;
    }
    body { font-family: 'Inter', sans-serif; background-color: var(--bg); color: var(--text); padding: 3rem 1.5rem; margin: 0; }
    .card { max-width: 680px; margin: 0 auto; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 2.5rem; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }
    .title { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem; text-align: center; }
    .subtitle { text-align: center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 2.5rem; }
    .check-row { display: flex; align-items: start; gap: 1rem; padding: 1.25rem; border-bottom: 1px solid var(--border); }
    .check-row:last-child { border-bottom: none; }
    .check-status { font-size: 1.5rem; line-height: 1; }
    .check-body { flex: 1; }
    .check-title { font-weight: 700; font-size: 1rem; margin-bottom: 0.2rem; }
    .check-desc { font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; }
    .badge { font-weight: 700; font-size: 0.8rem; padding: 0.25rem 0.6rem; border-radius: 4px; text-transform: uppercase; }
    .badge-pass { background: rgba(16,185,129,0.15); color: var(--pass); }
    .badge-fail { background: rgba(239,68,68,0.15); color: var(--fail); }
    .summary-box { text-align: center; margin-top: 2.5rem; padding: 1.5rem; border-radius: 8px; font-weight: 700; }
    .summary-pass { background: rgba(16,185,129,0.1); border: 1px solid var(--pass); color: var(--pass); }
    .summary-fail { background: rgba(239,68,68,0.1); border: 1px solid var(--fail); color: var(--fail); }
  </style>
</head>
<body>

<div class="card">
  <div class="title">📋 Uthenga System Verification</div>
  <div class="subtitle">Real-time status checks on environment settings, database connectivity, and table integrity.</div>

  <div style="background: rgba(255,255,255,0.02); border-radius:8px; border: 1px solid var(--border); overflow:hidden;">
    <?php foreach ($checks as $key => $c): ?>
      <div class="check-row">
        <div class="check-status">
          <?= $c['status'] === 'pass' ? '✅' : '❌' ?>
        </div>
        <div class="check-body">
          <div class="check-title"><?= e($c['title']) ?></div>
          <div class="check-desc"><?= e($c['desc']) ?></div>
        </div>
        <div>
          <span class="badge badge-<?= $c['status'] ?>"><?= $c['status'] ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="summary-box summary-<?= $allPass ? 'pass' : 'fail' ?>">
    <?php if ($allPass): ?>
      🎉 System Check Passed! Uthenga is fully operational.
    <?php else: ?>
      ⚠️ System Check Failed. Please resolve the issues highlighted above.
    <?php endif; ?>
  </div>

  <div style="text-align:center; margin-top:2rem;">
    <a href="../index.php" style="color:var(--primary); text-decoration:none; font-weight:600; font-size:0.9rem;">Go to Marketplace Homepage →</a>
  </div>
</div>

</body>
</html>
