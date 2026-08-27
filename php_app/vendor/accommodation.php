<?php
/** Retired vendor accommodation URL retained only as an authenticated redirect. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireApprovedVendor();
header('Cache-Control: no-store, max-age=0, must-revalidate');
header('Location: ' . BASE_URL . 'vendor/accommodation-control-center.php', true, 302);
exit;
