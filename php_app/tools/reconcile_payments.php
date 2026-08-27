<?php
/** Poll pending PayChangu intents when a webhook was missed or delayed. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/tie/bootstrap.php';

$limit = isset($argv[1]) && ctype_digit((string)$argv[1]) ? max(1, min(100, (int)$argv[1])) : 25;
if (!UthengaTieFeatureFlags::enabled('payments')) { fwrite(STDERR, "Payments are disabled.\n"); exit(2); }
try {
    $result=(new UthengaTieKernel())->payments->reconcilePending($limit);
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(($result['errors']??0)>0?1:0);
} catch(Throwable $error) {
    fwrite(STDERR,json_encode(['success'=>false,'error_type'=>$error instanceof UthengaTieException?$error->type():'internal_error']).PHP_EOL);
    exit(1);
}
