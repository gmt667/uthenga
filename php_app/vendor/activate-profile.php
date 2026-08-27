<?php
/**
 * Activate Profile — permanently retired in favour of vendor/dashboard.php
 */
require_once __DIR__ . '/../config.php';

header('Location: ' . BASE_URL . 'vendor/dashboard.php', true, 301);
exit;
