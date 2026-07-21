<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/restoration_helpers.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $redirect = uthenga_safe_redirect_url((string)($_POST['redirect'] ?? ''), '');

    try {
        if ($email === '' || $password === '') {
            throw new RuntimeException('Email and password are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }

        $user = uthenga_auth_find_user_by_email($email);
        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            throw new RuntimeException('Invalid email or password. Please try again.');
        }

        if (in_array($user['role'], ADMIN_ROLES, true)) {
            throw new RuntimeException('Please use the private admin portal to sign in.');
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

require_once __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding:3rem 0 4rem;">
  <div class="container" style="max-width:920px;">
    <div class="grid grid-cols-2 gap-4" style="align-items:stretch;">
      <div class="card" style="padding:2rem;">
        <div class="section-label">Welcome back</div>
        <h1 style="margin:0.5rem 0 1rem;">Sign in to your Uthenga account</h1>
        <p class="text-muted" style="max-width:520px;">Access bookings, vendor tools, dashboards, and support from one account.</p>
        <?php if ($error): ?><div class="alert alert-error" style="margin-top:1rem;"><?= e($error) ?></div><?php endif; ?>
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
        <form method="post" style="margin-top:1.25rem;display:grid;gap:1rem;">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary">Sign In</button>
        </form>
        <div class="text-sm text-muted" style="margin-top:1rem;">
          Don't have an account? <a href="<?= BASE_URL ?>register.php">Register</a>
          or <a href="<?= BASE_URL ?>vendor/register.php">register as a vendor</a>.
        </div>
      </div>
      <div class="card" style="padding:2rem;background:linear-gradient(135deg,rgba(6,182,212,.12),rgba(16,185,129,.10));">
        <div class="section-label">Demo access</div>
        <h2 style="margin:0.5rem 0 1rem;">Seeded accounts are ready</h2>
        <div class="simple-list">
          <div class="simple-list-item"><strong>Super Admin</strong><span class="text-xs text-muted">mwalwandadesire5@gmail.com</span></div>
          <div class="simple-list-item"><strong>Customer</strong><span class="text-xs text-muted">grace.banda@gmail.com</span></div>
          <div class="simple-list-item"><strong>Vendor</strong><span class="text-xs text-muted">events@lakemalawi.com</span></div>
          <div class="simple-list-item"><strong>Password</strong><span class="text-xs text-muted">Use the freshly generated seed passwords from install/generate_hashes.php</span></div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
