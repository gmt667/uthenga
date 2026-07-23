<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/restoration_helpers.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/security_helper.php';
require_once __DIR__ . '/includes/brand_icons.php';

$pageTitle = 'Sign In';
$activeNav = '';
$error = '';
$success = '';
$redirect = uthenga_safe_redirect_url((string)($_GET['redirect'] ?? ''), '');
$socialLoginEnabled = (
    (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '' && defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== '') ||
    (defined('FACEBOOK_APP_ID') && FACEBOOK_APP_ID !== '' && defined('FACEBOOK_APP_SECRET') && FACEBOOK_APP_SECRET !== '') ||
    (defined('MICROSOFT_CLIENT_ID') && MICROSOFT_CLIENT_ID !== '' && defined('MICROSOFT_CLIENT_SECRET') && MICROSOFT_CLIENT_SECRET !== '')
);

if (isLoggedIn()) {
    redirectByRole(currentRole());
}

if (isset($_GET['logout'])) {
    $success = 'You have been logged out successfully.';
}
if (isset($_GET['session_revoked'])) {
    $error = 'Your session has expired or was revoked. Please sign in again.';
}
if (isset($_GET['registered'])) {
    $success = 'Account created successfully! Please sign in with your credentials.';
}
if (isset($_GET['reset'])) {
    $success = 'Password updated successfully! Please sign in with your new password.';
}
if (isset($_GET['oauth_error'])) {
    $errCode = (string)$_GET['oauth_error'];
    if ($errCode === 'access_denied') {
        $error = 'Social login was cancelled or denied.';
    } elseif ($errCode === 'extension_missing') {
        $error = 'Server cURL extension is missing. Please contact support.';
    } else {
        $error = 'Social sign-in could not be completed (' . e($errCode) . '). Please try again or sign in with your email.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token mismatch or session expired. Please refresh and try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $redirect = uthenga_safe_redirect_url((string)($_POST['redirect'] ?? ''), '');

        try {
            if ($email === '' || $password === '') {
                throw new RuntimeException('Email address and password are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid email address.');
            }

            $user = uthenga_auth_find_user_by_email($email);
            if (!$user || !password_verify($password, (string)$user['password_hash'])) {
                throw new RuntimeException('Invalid email or password. Please try again.');
            }

            if (in_array($user['role'], ADMIN_ROLES, true)) {
                throw new RuntimeException('Admin accounts must sign in via the Admin Portal.');
            }

            if (in_array($user['role'], VENDOR_ROLES, true) && empty($user['is_approved'])) {
                uthenga_auth_login_user($user);
                redirect(BASE_URL . 'vendor/pending.php');
            }

            uthenga_auth_login_user($user);
            logAction('User Login', 'Successful login for ' . $user['email']);

            if (!empty($user['must_change_pw'])) {
                redirect(BASE_URL . 'change_password.php');
            }

            if ($redirect !== '') {
                redirect($redirect);
            }

            redirectByRole((string)$user['role']);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
  .login-shell {
    display: grid;
    grid-template-columns: minmax(0, 1.05fr) minmax(280px, 0.95fr);
    gap: 1rem;
    align-items: stretch;
  }
  .login-panel {
    border-radius: 24px;
    border: 1px solid var(--clr-border);
    overflow: hidden;
    box-shadow: var(--shadow-md);
  }
  .login-panel-form {
    padding: 2rem;
    background:
      radial-gradient(circle at top left, rgba(6,182,212,.14), transparent 40%),
      linear-gradient(180deg, rgba(255,255,255,.98), rgba(244,247,250,.96));
  }
  html[data-theme="dark"] .login-panel-form {
    background:
      radial-gradient(circle at top left, rgba(6,182,212,.14), transparent 40%),
      linear-gradient(180deg, rgba(17,24,39,.98), rgba(11,17,32,.96));
  }
  .login-panel-brand {
    padding: 2rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.25rem;
    background:
      radial-gradient(circle at top right, rgba(245,158,11,.18), transparent 38%),
      linear-gradient(135deg, rgba(6,182,212,.16), rgba(16,185,129,.12));
  }
  .login-brand-card {
    padding: 1.25rem;
    border-radius: 20px;
    background: rgba(255,255,255,.72);
    border: 1px solid rgba(255,255,255,.55);
    backdrop-filter: blur(14px);
  }
  html[data-theme="dark"] .login-brand-card {
    background: rgba(17,24,39,.55);
    border-color: rgba(255,255,255,.08);
  }
  .login-brand-logo {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    margin-bottom: 0.75rem;
  }
  .login-brand-copy h2 {
    margin: 0 0 0.6rem;
    font-size: 1.55rem;
  }
  .login-brand-copy p {
    margin: 0;
    color: var(--clr-text-muted);
    line-height: 1.6;
  }
  .login-brand-list {
    display: grid;
    gap: .7rem;
  }
  .login-brand-item {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: .8rem .9rem;
    border-radius: 16px;
    background: rgba(255,255,255,.52);
    border: 1px solid rgba(255,255,255,.55);
    font-size: .9rem;
    font-weight: 600;
  }
  html[data-theme="dark"] .login-brand-item {
    background: rgba(17,24,39,.48);
    border-color: rgba(255,255,255,.08);
  }
  .login-password-links {
    display: flex;
    gap: .9rem;
    flex-wrap: wrap;
    margin-top: .9rem;
    font-size: .875rem;
  }
  .login-password-links a {
    font-weight: 600;
  }
  .password-input-wrap {
    position: relative;
  }
  .password-toggle-btn {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: var(--clr-text-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    transition: color 0.2s ease;
  }
  .password-toggle-btn:hover {
    color: var(--clr-primary);
  }
  @media (max-width: 900px) {
    .login-shell { grid-template-columns: 1fr; }
  }
</style>
<section class="section" style="padding:3rem 0 4rem;">
  <div class="container" style="max-width:1120px;">
    <div class="login-shell">
      <div class="login-panel login-panel-form">
        <div class="section-label">Welcome back</div>
        <h1 style="margin:0.5rem 0 1rem;">Sign in to your Uthenga account</h1>
        <p class="text-muted" style="max-width:520px;">Access your dashboard, orders, vendor tools, and support from one account.</p>
        <?php if ($error): ?>
          <div class="alert alert-error" style="margin-top:1rem;">
            <?= e($error) ?>
            <?php if (strpos($error, 'Admin Portal') !== false): ?>
              <div style="margin-top:0.5rem;">
                <a href="<?= BASE_URL ?>admin/login.php" class="btn btn-sm btn-outline" style="display:inline-block;margin-top:0.25rem;">Go to Admin Portal &rarr;</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success" style="margin-top:1rem;"><?= e($success) ?></div><?php endif; ?>
        <?php if ($socialLoginEnabled): ?>
        <div style="display:grid;gap:0.75rem;margin-top:1.25rem;margin-bottom:1rem;">
          <?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '' && defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== ''): ?>
            <a href="<?= BASE_URL ?>auth/google.php?role=customer" class="oauth-btn oauth-google" id="btn-google-login">
              <?= uthenga_brand_icon_svg('google') ?>
              <span>Continue with Google</span>
            </a>
          <?php endif; ?>
          <?php if (defined('FACEBOOK_APP_ID') && FACEBOOK_APP_ID !== '' && defined('FACEBOOK_APP_SECRET') && FACEBOOK_APP_SECRET !== ''): ?>
            <a href="<?= BASE_URL ?>auth/facebook.php?role=customer" class="oauth-btn oauth-facebook" id="btn-facebook-login">
              <?= uthenga_brand_icon_svg('facebook') ?>
              <span>Continue with Facebook</span>
            </a>
          <?php endif; ?>
          <?php if (defined('MICROSOFT_CLIENT_ID') && MICROSOFT_CLIENT_ID !== '' && defined('MICROSOFT_CLIENT_SECRET') && MICROSOFT_CLIENT_SECRET !== ''): ?>
            <a href="<?= BASE_URL ?>auth/microsoft.php?role=customer" class="oauth-btn oauth-microsoft" id="btn-microsoft-login">
              <?= uthenga_brand_icon_svg('microsoft') ?>
              <span>Continue with Microsoft</span>
            </a>
          <?php endif; ?>
          <div style="display:flex;align-items:center;gap:0.75rem;margin:0.25rem 0 0.5rem;">
            <div style="flex:1;height:1px;background:var(--clr-border);"></div>
            <span class="text-xs text-muted" style="white-space:nowrap;">or sign in with email</span>
            <div style="flex:1;height:1px;background:var(--clr-border);"></div>
          </div>
        </div>
        <?php endif; ?>
        <form method="post" style="margin-top:1.25rem;display:grid;gap:1rem;" id="loginForm">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
          <div class="form-group">
            <label class="form-label" for="login-email">Email Address</label>
            <input type="email" id="login-email" name="email" class="form-control" required autocomplete="username" placeholder="name@example.com" value="<?= e($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.35rem;">
              <label class="form-label" for="login-password" style="margin-bottom:0;">Password</label>
              <a href="<?= BASE_URL ?>forgot_password.php" class="text-xs" style="color:var(--clr-primary);font-weight:600;">Forgot password?</a>
            </div>
            <div class="password-input-wrap">
              <input type="password" id="login-password" name="password" class="form-control" required autocomplete="current-password" placeholder="••••••••" style="padding-right:2.75rem;">
              <button type="button" class="password-toggle-btn" id="togglePassword" aria-label="Toggle password visibility">
                <svg id="eyeIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <button type="submit" class="btn btn-primary" id="btnSubmit">Sign In</button>
        </form>
        <div class="text-sm text-muted" style="margin-top:1.25rem;">
          Don't have an account? <a href="<?= BASE_URL ?>register.php" style="font-weight:600;">Register</a>
          or <a href="<?= BASE_URL ?>vendor/register.php" style="font-weight:600;">register as a vendor</a>.
        </div>
      </div>
      <div class="login-panel login-panel-brand">
        <div class="login-brand-card">
          <div class="login-brand-logo">
            <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/includes/logo.php'; ?>
          </div>
          <div class="login-brand-copy">
            <h2>Secure access for customers, vendors, and admins.</h2>
            <p>Use your Uthenga account to manage orders, bookings, marketplace activity, and vendor operations from one place.</p>
          </div>
        </div>
        <div class="login-brand-list">
          <div class="login-brand-item"><?= uthenga_public_icon_svg('check') ?> Customer dashboard access</div>
          <div class="login-brand-item"><?= uthenga_public_icon_svg('check') ?> Vendor portal access</div>
          <div class="login-brand-item"><?= uthenga_public_icon_svg('check') ?> Fast account recovery</div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const togglePassword = document.getElementById('togglePassword');
  const passwordInput = document.getElementById('login-password');
  const eyeIcon = document.getElementById('eyeIcon');

  if (togglePassword && passwordInput && eyeIcon) {
    togglePassword.addEventListener('click', function() {
      const isPassword = passwordInput.getAttribute('type') === 'password';
      passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
      eyeIcon.innerHTML = isPassword
        ? '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    });
  }
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
