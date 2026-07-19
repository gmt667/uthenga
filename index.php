<?php
/**
 * Uthenga - Root bootstrap for cPanel / GitHub deployments
 */

$entry = __DIR__ . '/php_app/index.php';

if (!is_file($entry)) {
    http_response_code(500);
    echo 'UTHENGA application files are missing.';
    exit;
}

require $entry;

