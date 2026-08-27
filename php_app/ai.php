<?php
/**
 * Static React entry point for the incremental Uthenga frontend.
 *
 * In development use Vite directly on :5173. In Apache/XAMPP production run
 * the frontend build deployment helper, then open this same-origin shell.
 */
require_once __DIR__ . '/config.php';

// The shell resolves the current Vite manifest on every request. Do not let a
// browser reuse an older HTML document after a frontend deployment; the hashed
// assets themselves remain safely cacheable.
header('Cache-Control: no-store, max-age=0, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$manifestPath = __DIR__ . '/frontend/.vite/manifest.json';
if (!is_file($manifestPath)) {
    http_response_code(503);
    ?><!doctype html><meta charset="utf-8"><title>Uthenga frontend unavailable</title><body style="font-family:system-ui;background:#07101d;color:#eaf6ff;padding:3rem"><h1>Uthenga frontend is not deployed yet.</h1><p>Build the React app, deploy its static assets, then refresh this page.</p><p><a style="color:#45d9e8" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES) ?>vendor/portal.php">Use the current Uthenga workspace</a></p></body><?php
    exit;
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);
$entry = is_array($manifest) ? ($manifest['index.html'] ?? $manifest['src/main.tsx'] ?? null) : null;
if (!is_array($entry) || empty($entry['file'])) {
    throw new RuntimeException('React asset manifest is invalid.');
}
$assetBase = BASE_URL . 'frontend/';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <title>Uthenga Agent</title>
  <?php foreach (($entry['css'] ?? []) as $stylesheet): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . $stylesheet . '?v=' . time(), ENT_QUOTES) ?>">
  <?php endforeach; ?>
</head>
<body>
  <div id="root"></div>
  <script>window.UTHENGA_BASE_URL = <?= json_encode(BASE_URL, JSON_UNESCAPED_SLASHES) ?>;</script>
  <script type="module" src="<?= htmlspecialchars($assetBase . $entry['file'] . '?v=' . time(), ENT_QUOTES) ?>"></script>
</body>
</html>
