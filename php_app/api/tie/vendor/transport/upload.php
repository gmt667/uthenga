<?php
ob_start();
require_once __DIR__ . '/_bootstrap.php';

$requestId = UthengaTieObservability::requestId();
try {
    UthengaTieApi::requireFeature('bus_operations');
    $user = UthengaTieApi::requireAuthenticatedUser();
    if (!in_array($user['role'], VENDOR_ROLES, true)) throw UthengaTieErrors::authorization();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw UthengaTieErrors::validation(['method' => 'POST method required.']);
    }

    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        throw UthengaTieErrors::validation(['file' => 'Choose a file to upload.']);
    }

    $file = $_FILES['file'];
    $tmpFile = (string) ($file['tmp_name'] ?? '');
    if (empty($tmpFile) || !file_exists($tmpFile)) {
        throw UthengaTieErrors::validation(['file' => 'Upload failed. Please choose a valid file.']);
    }

    $type = strtolower((string) ($_POST['type'] ?? 'image'));
    $uploadDir = __DIR__ . '/../../../../uploads/bus_ops';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $origName = basename((string) ($file['name'] ?? 'file'));
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if ($type === 'image') {
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        if (!in_array($ext, $allowedExts, true)) {
            throw UthengaTieErrors::validation(['file' => 'Only JPG, PNG, WEBP and SVG images are supported.']);
        }
    } else {
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
        if (!in_array($ext, $allowedExts, true)) {
            throw UthengaTieErrors::validation(['file' => 'Only PDF, DOCX and image files are supported for documents.']);
        }
    }

    $filename = uniqid('bus_ops_' . $type . '_', true) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $filename;

    $moved = @move_uploaded_file($tmpFile, $targetPath) || @copy($tmpFile, $targetPath);
    if (!$moved) {
        throw UthengaTieErrors::validation(['file' => 'Could not save file to disk. Please try again.']);
    }

    @chmod($targetPath, 0644);
    $url = BASE_URL . 'uploads/bus_ops/' . $filename;

    ob_clean();
    bus_ops_respond($requestId, 'result', [
        'success' => true,
        'url' => $url,
        'filename' => $filename,
        'original_name' => $origName,
        'size' => (int) ($file['size'] ?? 0),
    ]);
} catch (Throwable $error) {
    ob_clean();
    UthengaTieApi::handleError($error, $requestId);
}
