<?php
/**
 * Uthenga - Admin Login Page
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    $current = dbQueryOne('SELECT is_approved FROM users WHERE id = ?', [$_SESSION['user_id']]);
    if (($_SESSION['user_role'] ?? '') === ROLE_SUPER_ADMIN) {
        redirect(BASE_URL . 'admin/super-dashboard.php');
    } elseif (in_array($_SESSION['user_role'], ADMIN_ROLES, true)) {
        redirect(BASE_URL . 'admin/dashboard.php');
    } elseif (in_array($_SESSION['user_role'], VENDOR_ROLES, true)) {
        redirect(($current && !$current['is_approved']) ? BASE_URL . 'vendor/pending.php' : BASE_URL . 'vendor/dashboard.php');
    } else {
        redirect(BASE_URL . 'dashboard.php');
    }
}

$error = '';
$success = '';
$isSuperPortal = (string) ($_GET['super'] ?? '') === '1';
$safeRedirect = uthenga_safe_redirect_url((string) ($_GET['redirect'] ?? ''), '');
$socialLoginEnabled = (
    (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '' && defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== '') ||
    (defined('FACEBOOK_APP_ID') && FACEBOOK_APP_ID !== '' && defined('FACEBOOK_APP_SECRET') && FACEBOOK_APP_SECRET !== '') ||
    (defined('MICROSOFT_CLIENT_ID') && MICROSOFT_CLIENT_ID !== '' && defined('MICROSOFT_CLIENT_SECRET') && MICROSOFT_CLIENT_SECRET !== '')
);

if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}
if (isset($_GET['session_revoked'])) {
    $error = $isSuperPortal
        ? 'Your super admin session has expired. Please sign in again.'
        : 'Your admin session has expired. Please sign in again.';
}
if (isset($_GET['pending'])) {
    $success = 'Your vendor account is pending approval. You will be notified once approved.';
}
if (isset($_GET['suspended'])) {
    $error = 'Your account has been suspended. Please contact support.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Please enter your email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $result = authenticateAdmin($email, $password);

            if (!$result['success']) {
                $error = $result['error'] ?? 'Invalid email or password. Please try again.';
            } else {
                $user = $result['user'] ?? [];

                if (($result['via'] ?? '') === 'database' && !empty($user['two_factor_enabled'])) {
                    session_regenerate_id(true);
                    $_SESSION['2fa_pending']   = true;
                    $_SESSION['2fa_user_id']   = $user['id'];
                    $_SESSION['2fa_user_role'] = $user['role'];
                    $_SESSION['2fa_user_name'] = $user['name'];
                    redirect(BASE_URL . 'auth/2fa-verify.php');
                }

                startAdminSession($user);
                require_once __DIR__ . '/../includes/security_helper.php';
                registerDeviceSession((string) $user['id']);

                if (!empty($user['must_change_pw'])) {
                    redirect(BASE_URL . 'change_password.php');
                }

                $redirect = $safeRedirect;
                if (($user['role'] ?? '') === ROLE_SUPER_ADMIN) {
                    redirect($redirect !== '' ? $redirect : BASE_URL . 'admin/super-dashboard.php');
                }

                redirect($redirect !== '' ? $redirect : BASE_URL . 'admin/dashboard.php');
            }
        }
    }
}

$pageTitle = $isSuperPortal ? 'Super Admin Login' : 'Admin Login';
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
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .admin-login-body {
      background: radial-gradient(ellipse at top, rgba(14,165,233,0.14), transparent 40%), linear-gradient(180deg, #f8fafc, #eef3f8);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    html[data-theme="dark"] .admin-login-body {
      background: radial-gradient(ellipse at top, rgba(14,165,233,0.14), transparent 40%), linear-gradient(180deg, #0b1120, #111827);
    }
    .admin-login-card {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      backdrop-filter: blur(24px);
      border-radius: var(--radius-xl);
      padding: 2.5rem;
      width: 100%;
      max-width: 460px;
      box-shadow: var(--shadow-lg);
    }
    .admin-login-title {
      font-size: 1.6rem;
      font-weight: 800;
      text-align: center;
      margin-bottom: 0.25rem;
      color: var(--clr-text);
    }
    .portal-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      margin: 0 auto 1rem;
      padding: 0.35rem 0.8rem;
      border-radius: 999px;
      background: rgba(14,165,233,.1);
      color: var(--clr-cyan2);
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
    }
    .login-btn-inner {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.55rem;
    }
    .login-spinner {
      width: 0.95rem;
      height: 0.95rem;
      border-radius: 999px;
      border: 2px solid rgba(255,255,255,.35);
      border-top-color: #fff;
      animation: loginSpin 0.8s linear infinite;
      display: none;
    }
    .btn.is-loading .login-spinner { display: inline-block; }
    @keyframes loginSpin { to { transform: rotate(360deg); } }
    @media (max-width: 480px) {
      .admin-login-body { padding: 1rem; }
      .admin-login-card { padding: 1.25rem; }
      .admin-login-title { font-size: 1.35rem; }
    }
  </style>
</head>
<body class="admin-login-body">
<?php require_once __DIR__ . '/../includes/page_loader.php'; ?>
<div style="position:fixed;top:1rem;right:1rem;z-index:20;">
  <button type="button" class="btn btn-sm btn-secondary btn-icon theme-toggle" data-theme-toggle aria-label="Toggle light and dark mode" aria-pressed="false">
    <span class="theme-toggle-icon" aria-hidden="true"></span>
    <span class="theme-toggle-label">Dark</span>
  </button>
</div>
<div class="admin-login-card animate-in">
  <div class="auth-logo" style="margin-bottom:1rem;">
    <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/../includes/logo.php'; ?>
  </div>
  <div class="portal-badge"><?= $isSuperPortal ? 'Super Admin Portal' : 'Admin Portal' ?></div>
  <h1 class="admin-login-title"><?= $isSuperPortal ? 'Super Admin Command Center' : APP_NAME . ' Command Center' ?></h1>
  <p class="text-xs text-muted" style="text-align:center;margin-bottom:2rem;"><?= $isSuperPortal ? 'Sign in with the Super Administrator account.' : 'Sign in with an Administrator or Super Administrator account.' ?></p>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:1.25rem;" role="alert"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom:1.25rem;" role="status"><?= e($success) ?></div>
  <?php endif; ?>

  <form method="POST" action="" id="admin-login-form">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

    <div class="form-group">
      <label class="form-label" for="email"><?= $isSuperPortal ? 'Super Admin Email' : 'Admin Email' ?></label>
      <input
        type="email"
        id="email"
        name="email"
        class="form-control"
        placeholder="admin@uthenga.com"
        required
        autocomplete="email"
        autofocus
        value="<?= e($_POST['email'] ?? '') ?>"
        oninvalid="this.setCustomValidity('Please enter your administrator email address.')"
        oninput="this.setCustomValidity('')"
      >
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <div class="pw-wrapper">
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          placeholder="Your password"
          autocomplete="current-password"
          required
          oninvalid="this.setCustomValidity('Please enter your password.')"
          oninput="this.setCustomValidity('')"
        >
        <button type="button" class="pw-toggle" onclick="utPwToggle('password',this)" aria-label="Show/hide password">
          <svg class="pw-eye-off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          <svg class="pw-eye-on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg" id="admin-login-submit" style="width:100%;margin-top:0.5rem;">
      <span class="login-btn-inner">
        <span class="login-spinner" aria-hidden="true"></span>
        <span class="login-btn-label">Sign In</span>
      </span>
    </button>
  </form>

  <div class="alert alert-info" style="margin-top:1.5rem;">
    <strong>Super administrator access</strong><br>
    <span class="text-xs"><?= $isSuperPortal ? 'Use admin@uthenga.com to enter the super admin command center.' : 'Use a verified Super Administrator account only.' ?></span>
  </div>

  <p style="text-align:center;margin-top:0.9rem;font-size:0.875rem;">
    <a href="<?= BASE_URL ?>admin/forgot-password.php" style="font-weight:600;">Forgot admin password?</a>
  </p>

  <p style="text-align:center;margin-top:1.5rem;font-size:0.875rem;">
    <?php if ($isSuperPortal): ?>
      <a href="<?= BASE_URL ?>admin/login.php" style="font-weight:600;">Standard admin login</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>admin/super-login.php" style="font-weight:600;">Super admin login</a>
    <?php endif; ?>
  </p>
  <p style="text-align:center;margin-top:0.5rem;font-size:0.875rem;">
    <a href="<?= BASE_URL ?>index.php" style="color:var(--clr-text-muted);">Back to marketplace</a>
  </p>
</div>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>
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

(function () {
  var form = document.getElementById('admin-login-form');
  var submit = document.getElementById('admin-login-submit');
  if (!form || !submit) return;

  form.addEventListener('submit', function (event) {
    if (!form.checkValidity()) {
      return;
    }
    submit.classList.add('is-loading');
    submit.setAttribute('aria-busy', 'true');
    submit.disabled = true;
    var label = submit.querySelector('.login-btn-label');
    if (label) label.textContent = 'Signing In...';
  });
})();
</script>
</body>
</html>
