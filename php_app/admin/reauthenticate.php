<?php
/** Short-lived, session-bound reauthentication for high-risk admin actions. */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/totp_helper.php';

requireAdmin();
$categories = ['admin_access', 'finance', 'settlements', 'security'];
$category = strtolower(trim((string) ($_GET['category'] ?? $_POST['category'] ?? '')));
if (!in_array($category, $categories, true)) sendFriendlyError('This confirmation request is not available.', 400);
$returnTo = uthenga_safe_redirect_url((string) ($_GET['return'] ?? $_POST['return'] ?? ''), BASE_URL . 'admin/dashboard.php');
$error = '';
$accountForForm = adminCurrentAccount();
$requiresTwoFactor = !empty($accountForForm['two_factor_enabled']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security check failed. Please refresh and try again.';
    } else {
        $account = adminCurrentAccount();
        $password = (string) ($_POST['password'] ?? '');
        $code = trim((string) ($_POST['code'] ?? ''));
        $twoFactorRequired = !empty($account['two_factor_enabled']);
        $twoFactorValid = !$twoFactorRequired;
        if ($twoFactorRequired && uthenga_table_exists('two_factor_auth')) {
            $config = dbQueryOne('SELECT secret FROM two_factor_auth WHERE user_id = ?', [$account['id']]);
            $twoFactorValid = $config && TotpHelper::verifyCode((string) $config['secret'], $code);
        }
        $stored = $account ? dbQueryOne('SELECT password_hash FROM users WHERE id = ?', [$account['id']]) : null;
        if (!$account || !$stored || !password_verify($password, (string) ($stored['password_hash'] ?? '')) || !$twoFactorValid) {
            adminLogAuthorizationDenied('reauthentication_failed');
            $error = 'Unable to confirm your identity. Please try again.';
        } else {
            $_SESSION['admin_reauth'][$category] = time();
            logAction('Admin Reauthenticated', 'category=' . $category);
            redirect($returnTo);
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirm identity | Uthenga</title><link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css"></head><body><main style="max-width:480px;margin:8vh auto;padding:1.5rem"><h1>Confirm your identity</h1><p>Confirm your account before continuing with this sensitive administrative action.</p><?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>"><input type="hidden" name="category" value="<?= e($category) ?>"><input type="hidden" name="return" value="<?= e($returnTo) ?>"><label>Password<input required type="password" name="password" autocomplete="current-password"></label><?php if ($requiresTwoFactor): ?><label>Authentication code<input required inputmode="numeric" name="code" autocomplete="one-time-code"></label><?php endif; ?><button type="submit">Confirm and continue</button></form></main></body></html>
