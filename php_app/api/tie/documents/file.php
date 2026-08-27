<?php
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/tie/bootstrap.php';

try {
    if (!isLoggedIn()) { http_response_code(404); exit; }
    $documentId = trim((string) ($_GET['document_id'] ?? ''));
    if ($documentId === '') { http_response_code(404); exit; }
    $meta = (new UthengaTieKernel())->customerDocuments->fileMeta((string) $_SESSION['user_id'], $documentId);
    if (!is_file($meta['path'])) { http_response_code(404); exit; }
    header('Content-Type: ' . $meta['mime_type']);
    header('Content-Length: ' . (string) filesize($meta['path']));
    header('Content-Disposition: inline; filename="' . rawurlencode($meta['original_name']) . '"');
    header('Cache-Control: private, max-age=0, no-cache');
    header('X-Content-Type-Options: nosniff');
    readfile($meta['path']);
} catch (Throwable) {
    http_response_code(404);
}
