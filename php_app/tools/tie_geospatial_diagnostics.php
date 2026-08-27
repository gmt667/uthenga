<?php
/** Safe operational snapshot for a cron job or an operator using XAMPP PHP. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/tie/bootstrap.php';

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "A configured database connection is required.\n");
    exit(1);
}
$requestId = UthengaTieObservability::requestId();
$distribution = UthengaTieVendorLocationQuality::distribution($pdo);
foreach ($distribution as $quality => $count) {
    UthengaTieMetrics::record('vendor_location_quality', $count, $requestId, ['module' => 'location', 'feature' => 'geospatial_diagnostics', 'status' => 'ok', 'quality' => strtolower($quality)]);
}
echo json_encode([
    'generated_at' => gmdate(DATE_ATOM),
    'stale_cutoff' => UthengaTieVendorLocationQuality::staleCutoff(),
    'vendor_location_quality' => $distribution,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
