<?php
/**
 * Uthenga - Forced Password Change
 * Shown after login when must_change_pw = 1.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth_check.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . 'login.php');
}

$user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
if (!$user || empty($user['must_change_pw'])) {
    redirect(BASE_URL . 'index.php');
}

$isAdminAccount = in_array((string)($user['role'] ?? ''), ADMIN_ROLES, true);
$successRedirect = $isAdminAccount ? BASE_URL . 'admin/dashboard.php' : BASE_URL . 'dashboard.php';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token error. Please refresh and try again.';
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $profileName  = trim((string)($_POST['profile_name'] ?? ($user['name'] ?? '')));
        $profileEmail = trim((string)($_POST['profile_email'] ?? ($user['email'] ?? '')));
        $profilePhone = trim((string)($_POST['profile_phone'] ?? ($user['phone'] ?? '')));
        $profileAvatar = trim((string)($_POST['profile_avatar'] ?? ($user['avatar'] ?? '')));

        if ($current === '' || $new === '' || $confirm === '') {
            $error = 'All password fields are required.';
        } elseif (!password_verify($current, (string)$user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < MIN_PASSWORD_LEN) {
            $error = 'New password must be at least ' . MIN_PASSWORD_LEN . ' characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif ($new === $current) {
            $error = 'New password must be different from your current password.';
        } elseif ($isAdminAccount && $profileName === '') {
            $error = 'Please enter the administrator name.';
        } elseif ($isAdminAccount && $profilePhone === '') {
            $error = 'Please enter a phone number.';
        } elseif ($isAdminAccount && !filter_var($profileEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid official email address.';
        } elseif ($isAdminAccount && !preg_match('/^[0-9+()\-\s]{7,30}$/', $profilePhone)) {
            $error = 'Please enter a valid phone number.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

            if ($isAdminAccount) {
                dbExecute(
                    'UPDATE users SET name = ?, email = ?, phone = ?, avatar = ?, password_hash = ?, must_change_pw = 0 WHERE id = ?',
                    [
                        $profileName,
                        strtolower($profileEmail),
                        $profilePhone !== '' ? $profilePhone : null,
                        $profileAvatar !== '' ? $profileAvatar : ($user['avatar'] ?? null),
                        $hash,
                        $user['id']
                    ]
                );
                $_SESSION['user_name']  = $profileName;
                $_SESSION['user_email'] = strtolower($profileEmail);
                $user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$user['id']]);
            } else {
                dbExecute('UPDATE users SET password_hash = ?, must_change_pw = 0 WHERE id = ?', [$hash, $user['id']]);
            }

            logAction('Password Changed', 'User completed the required password change.');
            $success = 'Password updated successfully. Redirecting...';
        }
    }
}

$themePreference = uthenga_theme_preference();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($themePreference) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token']) ?>">
  <meta name="theme-color" content="<?= $themePreference === 'dark' ? '#0b1120' : '#f8fafc' ?>">
  <?php if ($success): ?>
    <meta http-equiv="refresh" content="2;url=<?= e($successRedirect) ?>">
  <?php endif; ?>
  <title>Change Password | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .auth-shell {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      background: radial-gradient(circle at top, rgba(14,165,233,0.12), transparent 35%), linear-gradient(180deg, #f8fafc, #eef3f8);
    }
    html[data-theme="dark"] .auth-shell {
      background: radial-gradient(circle at top, rgba(14,165,233,0.16), transparent 35%), linear-gradient(180deg, #0b1120, #111827);
    }
    .auth-card {
      width: 100%;
      max-width: 760px;
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-lg);
      padding: 2rem;
    }
    .pw-wrapper { position: relative; }
    .pw-toggle {
      position: absolute;
      right: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--clr-text-soft, #9ca3af);
      padding: 0.25rem;
      line-height: 1;
    }
    .pw-toggle svg { display: block; width: 18px; height: 18px; }
    .pw-wrapper .form-control { padding-right: 2.5rem; }
    .setup-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
    }
    .setup-note {
      padding: 1rem 1.1rem;
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-md);
      background: var(--clr-surface-2);
      margin-bottom: 1.25rem;
    }
    @media (max-width: 720px) {
      .auth-shell { padding: 1rem; }
      .auth-card { padding: 1.25rem; }
      .setup-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div class="auth-shell">
  <div class="auth-card animate-in">
    <div class="auth-logo" style="margin-bottom:1rem;">
      <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/includes/logo.php'; ?>
    </div>

    <div style="text-align:center;margin-bottom:1.5rem;">
      <div style="font-size:1rem;font-weight:800;letter-spacing:0.12em;text-transform:uppercase;color:var(--clr-accent);margin-bottom:0.75rem;">Security</div>
      <h1 class="auth-title">Security Requirement</h1>
      <p class="auth-subtitle">
        <?php if ($isAdminAccount): ?>
          Complete your administrator profile and set a new password before continuing.
        <?php else: ?>
          You must set a new password before continuing.
        <?php endif; ?>
      </p>
    </div>

    <div class="setup-note">
      <?php if ($isAdminAccount): ?>
        This account was created by a Super Administrator. Please confirm your profile details, then create a new password.
      <?php else: ?>
        Your account requires a password change. This is a one-time security step.
      <?php endif; ?>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom:1rem;">Error: <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom:1rem;">Success: <?= e($success) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="" id="change-pw-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <?php if ($isAdminAccount): ?>
      <div class="setup-grid">
        <div class="form-group">
          <label class="form-label" for="profile_name">Full Name</label>
          <input type="text" id="profile_name" name="profile_name" class="form-control" value="<?= e($user['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="profile_email">Official Email</label>
          <input type="email" id="profile_email" name="profile_email" class="form-control" value="<?= e($user['email'] ?? '') ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="profile_phone">Phone Number</label>
        <input type="tel" id="profile_phone" name="profile_phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+265 999 123 456" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="profile_avatar">Profile Photo URL <span class="text-muted">(optional)</span></label>
        <input type="url" id="profile_avatar" name="profile_avatar" class="form-control" value="<?= e($user['avatar'] ?? '') ?>" placeholder="https://...">
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label" for="current_password">Current Password</label>
        <div class="pw-wrapper">
          <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Your current or temporary password" autocomplete="current-password" required>
          <button type="button" class="pw-toggle" onclick="utPwToggle('current_password',this)" aria-label="Toggle password visibility">
            <svg class="pw-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            <svg class="pw-eye-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="new_password">New Password</label>
        <div class="pw-wrapper">
          <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Minimum <?= MIN_PASSWORD_LEN ?> characters" autocomplete="new-password" required minlength="<?= MIN_PASSWORD_LEN ?>">
          <button type="button" class="pw-toggle" onclick="utPwToggle('new_password',this)" aria-label="Toggle password visibility">
            <svg class="pw-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            <svg class="pw-eye-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm New Password</label>
        <div class="pw-wrapper">
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat new password" autocomplete="new-password" required>
          <button type="button" class="pw-toggle" onclick="utPwToggle('confirm_password',this)" aria-label="Toggle password visibility">
            <svg class="pw-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            <svg class="pw-eye-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div id="pw-match-msg" class="text-xs" style="margin-top:0.35rem;"></div>
      </div>

      <button type="submit" id="change-pw-btn" class="btn btn-primary btn-lg" style="width:100%;margin-top:0.25rem;">
        <?= $isAdminAccount ? 'Complete Profile and Set Password' : 'Set New Password' ?>
      </button>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:0.8rem;color:var(--clr-text-muted);">
      You cannot bypass this step. <a href="<?= BASE_URL ?>logout.php">Log out instead</a>
    </p>
    <?php endif; ?>
  </div>
</div>

<script>
const newPw = document.getElementById('new_password');
const confPw = document.getElementById('confirm_password');
const msg = document.getElementById('pw-match-msg');

function checkMatch() {
  if (!newPw || !confPw || !msg) {
    return;
  }
  if (!confPw.value) {
    msg.textContent = '';
    return;
  }
  if (newPw.value === confPw.value) {
    msg.textContent = 'Passwords match';
    msg.style.color = 'var(--clr-green)';
  } else {
    msg.textContent = 'Passwords do not match';
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
  var eyeOn = btn.querySelector('.pw-eye-on');
  if (eyeOff) eyeOff.style.display = isText ? '' : 'none';
  if (eyeOn) eyeOn.style.display = isText ? 'none' : '';
}
</script>
</body>
</html>
