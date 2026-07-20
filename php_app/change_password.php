<?php
/**
 * Uthenga — Forced Password Change
 * Shown after login when must_change_pw = 1 (new admins, etc.)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth_check.php';

if (!isLoggedIn()) redirect(BASE_URL . 'login.php');

// If they don't need to change — skip
$user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
if (!$user || !$user['must_change_pw']) {
    redirect(BASE_URL . 'index.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token error. Please refresh.';
    } else {
        $current  = $_POST['current_password']  ?? '';
        $new      = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            $error = 'All fields are required.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < MIN_PASSWORD_LEN) {
            $error = 'New password must be at least ' . MIN_PASSWORD_LEN . ' characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif ($new === $current) {
            $error = 'New password must be different from your current password.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            dbExecute('UPDATE users SET password_hash = ?, must_change_pw = 0 WHERE id = ?', [$hash, $user['id']]);
            logAction('Password Changed', 'User changed password from must_change_pw prompt.');
            $success = 'Password updated successfully! Redirecting…';
            // Redirect after 1.5 seconds via meta refresh
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php if ($success): ?>
    <meta http-equiv="refresh" content="2;url=<?= BASE_URL ?>index.php">
  <?php endif; ?>
  <title>Change Password | <?= APP_NAME ?></title>
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

    <div style="text-align:center;margin-bottom:1.5rem;">
      <div style="font-size:2.5rem;margin-bottom:0.75rem;">🔐</div>
      <h1 class="auth-title">Security Requirement</h1>
      <p class="auth-subtitle">You must set a new password before continuing.</p>
    </div>

    <div class="alert alert-warning">
      Your account requires a password change. This is a one-time requirement for account security.
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">✕ <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success">✓ <?= e($success) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="" id="change-pw-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <div class="form-group">
        <label class="form-label" for="current_password">Current Password</label>
        <div class="pw-wrapper">
          <input
            type="password"
            id="current_password"
            name="current_password"
            class="form-control"
            placeholder="Your current / temporary password"
            autocomplete="current-password"
            required
          >
          <button type="button" class="pw-toggle" onclick="utPwToggle('current_password',this)" aria-label="Toggle password visibility">
            <svg class="pw-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            <svg class="pw-eye-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="new_password">New Password</label>
        <div class="pw-wrapper">
          <input
            type="password"
            id="new_password"
            name="new_password"
            class="form-control"
            placeholder="Minimum <?= MIN_PASSWORD_LEN ?> characters"
            autocomplete="new-password"
            required
            minlength="<?= MIN_PASSWORD_LEN ?>"
          >
          <button type="button" class="pw-toggle" onclick="utPwToggle('new_password',this)" aria-label="Toggle password visibility">
            <svg class="pw-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            <svg class="pw-eye-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm New Password</label>
        <div class="pw-wrapper">
          <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            class="form-control"
            placeholder="Repeat new password"
            autocomplete="new-password"
            required
          >
          <button type="button" class="pw-toggle" onclick="utPwToggle('confirm_password',this)" aria-label="Toggle password visibility">
            <svg class="pw-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            <svg class="pw-eye-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div id="pw-match-msg" class="text-xs" style="margin-top:0.35rem;"></div>
      </div>

      <button type="submit" id="change-pw-btn" class="btn btn-primary btn-lg" style="width:100%;">
        Set New Password
      </button>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:0.8rem;color:var(--clr-text-muted);">
      You cannot bypass this step. <a href="<?= BASE_URL ?>logout.php">Log out instead</a>
    </p>
    <?php endif; ?>
  </div>
</div>

<script>
const newPw  = document.getElementById('new_password');
const confPw = document.getElementById('confirm_password');
const msg    = document.getElementById('pw-match-msg');
function checkMatch() {
  if (!confPw.value) { msg.textContent = ''; return; }
  if (newPw.value === confPw.value) {
    msg.textContent = '✓ Passwords match';
    msg.style.color = 'var(--clr-green)';
  } else {
    msg.textContent = '✕ Passwords do not match';
    msg.style.color = 'var(--clr-red, #ef4444)';
  }
}
if (newPw) newPw.addEventListener('input', checkMatch);
if (confPw) confPw.addEventListener('input', checkMatch);

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
