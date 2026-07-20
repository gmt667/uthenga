<?php
/**
 * Uthenga - Password Reset Completion
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$error = '';
$success = '';
$resetRow = null;

if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    $resetRow = dbQueryOne(
        'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email, u.name
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.reset_token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
         LIMIT 1',
        [$tokenHash]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } elseif (!$resetRow) {
        $error = 'This password reset link is invalid or has expired.';
    } else {
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (strlen($password) < MIN_PASSWORD_LEN) {
            $error = 'Password must be at least ' . MIN_PASSWORD_LEN . ' characters.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            dbExecute('UPDATE users SET password_hash = ?, must_change_pw = 0 WHERE id = ?', [$hash, $resetRow['user_id']]);
            dbExecute('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$resetRow['id']]);

            logAction('Password Reset Completed', 'Password reset completed for account: ' . $resetRow['email']);
            $success = 'Your password has been updated successfully. You can now sign in with your new password.';
        }
    }
}

if ($success !== '' && !headers_sent()) {
    header('Refresh: 2; url=' . BASE_URL . 'login.php?reset=1');
}

$pageTitle = 'Set New Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token']) ?>">
  <title>Reset Password | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
  .pw-wrapper { position: relative; }
  .pw-toggle {
    position: absolute; right: 0.75rem; top: 50%;
    transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--clr-text-soft, #9ca3af); padding: 0.25rem; line-height: 1; transition: color 0.2s;
  }
  .pw-toggle:hover { color: var(--clr-text, #e2e8f0); }
  .pw-toggle svg { display: block; width: 18px; height: 18px; }
  .pw-wrapper .form-control { padding-right: 2.5rem; }
  </style>
</head>
<body>
<div class="auth-page">
  <div class="auth-card animate-in">
    <div class="auth-logo">
      <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/includes/logo.php'; ?>
    </div>

    <h1 class="auth-title">Set a new password</h1>
    <p class="auth-subtitle">Choose a secure password for your Uthenga account.</p>

    <?php if ($error): ?>
      <div class="alert alert-error">✖ <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success">✓ <?= e($success) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="form-group">
          <label class="form-label" for="password">New Password</label>
          <div class="pw-wrapper">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              placeholder="Minimum <?= MIN_PASSWORD_LEN ?> characters"
              autocomplete="new-password"
              required
              minlength="<?= MIN_PASSWORD_LEN ?>"
            >
            <button type="button" class="pw-toggle" onclick="utPwToggle('password',this)" aria-label="Toggle password visibility">
              <svg class="pw-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <svg class="pw-eye-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password2">Confirm New Password</label>
          <div class="pw-wrapper">
            <input
              type="password"
              id="password2"
              name="password2"
              class="form-control"
              placeholder="Repeat your password"
              autocomplete="new-password"
              required
            >
            <button type="button" class="pw-toggle" onclick="utPwToggle('password2',this)" aria-label="Toggle confirm password visibility">
              <svg class="pw-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <svg class="pw-eye-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Update Password</button>
      </form>
    <?php endif; ?>

    <p style="text-align:center;margin-top:1.25rem;font-size:0.875rem;color:var(--clr-text-muted);">
      <a href="<?= BASE_URL ?>login.php">Back to login</a>
    </p>
  </div>
</div>
<script>
function utPwToggle(inputId, btn) {
  var inp = document.getElementById(inputId);
  if (!inp) return;
  var isText = inp.type === 'text';
  inp.type = isText ? 'password' : 'text';
  var eyeOff = btn.querySelector('.pw-eye-off');
  var eyeOn  = btn.querySelector('.pw-eye-on');
  if (eyeOff) eyeOff.style.display = isText ? '' : 'none';
  if (eyeOn)  eyeOn.style.display  = isText ? 'none' : '';
}
</script>
</body>
</html>
