<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';

try {
    $id = UthengaAccommodationContracts::id($_GET['id'] ?? '', 'id');
    $workspace = new UthengaAccommodationPropertyWorkspace($GLOBALS['pdo']);
    $actor = isLoggedIn() ? (string) $_SESSION['user_id'] : null;
    $row = $workspace->document($id, $actor);
    $file = dbQueryOne('SELECT storage_name,mime_type,original_name FROM tie_accommodation_documents WHERE id=? LIMIT 1', [$id]);
    if (!is_array($file)) { http_response_code(404); exit; }
    $path = __DIR__ . '/../../../storage/accommodation-documents/' . basename((string) $file['storage_name']);
    if (!is_file($path)) { http_response_code(404); exit; }
    $mime = in_array((string) $file['mime_type'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true) ? (string) $file['mime_type'] : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\- ]+/', '', basename((string) $file['original_name'])) . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (Throwable) {
    http_response_code(404);
}
