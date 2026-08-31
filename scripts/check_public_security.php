<?php
/** Fail deployment when privileged web maintenance artifacts reappear. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$forbiddenPaths = [
    'php_app/admin/debug_auth.php',
    'php_app/admin/includes/debug_auth.php',
    'php_app/admin/seed_admin.php',
    'php_app/reset_admin_password.php',
    'php_app/admin/reset_admin_password.php',
];
$forbiddenPatterns = [
    'embedded privileged administrator credential constant' => '/EMBEDDED_SUPER_ADMIN_(?:EMAIL|PASSWORD)/',
    'embedded administrator login fallback' => '/Admin Login \(Embedded Fallback\)/',
    'source-defined privileged seed credential' => '/SEED_ADMIN_PASSWORD/',
];

$failures = [];
foreach ($forbiddenPaths as $relativePath) {
    if (is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
        $failures[] = 'Forbidden public artifact exists: ' . $relativePath;
    }
}

$scanRoot = $root . DIRECTORY_SEPARATOR . 'php_app';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $contents = file_get_contents($file->getPathname());
    if ($contents === false) {
        $failures[] = 'Unable to inspect a PHP source file.';
        continue;
    }
    foreach ($forbiddenPatterns as $label => $pattern) {
        if (preg_match($pattern, $contents) === 1) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $failures[] = 'Forbidden pattern detected (' . $label . '): ' . $relative;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Public security deployment check failed.\n");
    foreach (array_unique($failures) as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Public security deployment check passed.\n");
