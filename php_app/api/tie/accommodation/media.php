<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';

try {
    $id = UthengaAccommodationContracts::id($_GET['id'] ?? '', 'id');
    $workspace = new UthengaAccommodationPropertyWorkspace($GLOBALS['pdo']);
    $actor = isLoggedIn() ? (string) $_SESSION['user_id'] : null;
    $media = $workspace->mediaForDelivery($id, $actor);
    $row = dbQueryOne('SELECT storage_name,mime_type FROM tie_accommodation_property_media WHERE id=? LIMIT 1', [$id]);
    if (!is_array($row)) { http_response_code(404); exit; }
    $path = __DIR__ . '/../../../storage/accommodation-media/' . basename((string) $row['storage_name']);
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: ' . $row['mime_type']);
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (Throwable) {
    http_response_code(404);
}
