<?php
require_once __DIR__ . '/../../../../config.php';
require_once __DIR__ . '/../../../../db.php';
require_once __DIR__ . '/../../../../includes/auth_check.php';
require_once __DIR__ . '/../../../../includes/tie/bootstrap.php';
require_once __DIR__ . '/../../../../includes/tie/Api.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'POST') {
    try {
        if (session_status() === PHP_SESSION_NONE) session_start();
        UthengaTieApi::requireFeature('events_v2');
        UthengaTieApi::requireAuthenticatedUser();
        UthengaTieApi::requireCsrf();
        UthengaTieApi::requireRateLimit('venues_uploads', 30, 60, UthengaTieObservability::requestId());

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            throw UthengaTieErrors::validation(['file' => 'Choose an image to upload.']);
        }
        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            throw UthengaTieErrors::validation(['file' => 'Choose a valid uploaded file.']);
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > 10 * 1024 * 1024) {
            throw UthengaTieErrors::validation(['file' => 'The file must be smaller than 10 MB.']);
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if ($ext === null || @getimagesize((string) $file['tmp_name']) === false) {
            throw UthengaTieErrors::validation(['file' => 'That image type is not permitted.']);
        }

        $folder = __DIR__ . '/../../../../storage/venue-media';
        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw UthengaTieErrors::providerUnavailable('secure_file_storage');
        }
        @chmod($folder, 0775);
        $name = bin2hex(random_bytes(18)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $folder . '/' . $name)) {
            throw UthengaTieErrors::providerUnavailable('secure_file_storage');
        }
        @chmod($folder . '/' . $name, 0644);

        UthengaTieApi::respond(['success' => true, 'url' => rtrim(BASE_URL, '/') . '/api/tie/vendor/events/cover-image.php?name=' . rawurlencode($name)]);
    } catch (Throwable $error) {
        UthengaTieApi::handleError($error, UthengaTieObservability::requestId());
    }
    exit;
}

$name = (string) ($_GET['name'] ?? '');
if (!preg_match('/^[a-f0-9]{36}\.(jpg|png|webp)$/', $name)) {
    http_response_code(404);
    exit;
}
$path = __DIR__ . '/../../../../storage/venue-media/' . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}
$mime = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'application/octet-stream',
};
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);