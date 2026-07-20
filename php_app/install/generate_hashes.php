<?php
/**
 * Uthenga - Password Hash Generator
 * Local-only helper for refreshing demo account hashes after importing setup.sql.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (APP_ENV !== 'development' && PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('403 Forbidden - this utility is local/development only.');
}

function uthenga_random_password(int $length = 12): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#';
    $max = strlen($alphabet) - 1;
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}

$accounts = [
    'u-super-admin',
    'u-1',
    'u-2',
    'v-1',
    'v-2',
    'v-3',
    'v-4',
    'c-1',
    'c-2',
];

$passwordMap = [];
foreach ($accounts as $userId) {
    $passwordMap[$userId] = uthenga_random_password();
}

$updated = 0;
foreach ($passwordMap as $userId => $plaintext) {
    $hash = password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $updated += dbExecute('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $userId]);
}

dbExecute("UPDATE users SET must_change_pw = 1 WHERE id = 'u-super-admin'");

if (PHP_SAPI === 'cli') {
    echo "Updated {$updated} password hashes\n\n";
    foreach ($passwordMap as $userId => $plaintext) {
        echo $userId . ' => ' . $plaintext . "\n";
    }
    exit(0);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<title>Hash Generator | Uthenga</title>
<style>
  body { font-family: monospace; background: #0a0a0f; color: #f0f0f5; padding: 2rem; }
  .ok  { color: #10b981; }
  .box { background: #12121a; border: 1px solid #222; border-radius: 8px; padding: 1.5rem; max-width: 720px; }
  h2   { color: #f59e0b; }
  a    { color: #f59e0b; }
  code { color: #f8fafc; }
</style>
</head>
<body>
<div class="box">
  <h2>Password Hashes Updated</h2>
  <p class="ok">Successfully updated <strong><?= (int) $updated ?></strong> user password hashes in the database.</p>
  <hr style="border-color:#222;margin:1rem 0;">
  <p><strong>Temporary passwords generated for local setup:</strong></p>
  <ul>
    <?php foreach ($passwordMap as $userId => $plaintext): ?>
      <li><code><?= e($userId) ?></code> = <code><?= e($plaintext) ?></code></li>
    <?php endforeach; ?>
  </ul>
  <hr style="border-color:#222;margin:1rem 0;">
  <p style="color:#ef4444;">Use this utility only during local setup. Delete or disable it on shared hosting.</p>
  <p><a href="<?= BASE_URL ?>login.php">Go to Login</a></p>
</div>
</body>
</html>
