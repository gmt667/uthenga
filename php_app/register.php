<?php
/**
 * Uthenga - Customer Registration Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/brand_icons.php';

if (isLoggedIn()) redirect(BASE_URL . 'dashboard.php');

$errors = [];
$old = [];
$refCodeVal = trim($_GET['ref'] ?? ($_POST['referral_code'] ?? ''));
$socialLoginEnabled = (
    (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '' && defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== '') ||
    (defined('FACEBOOK_APP_ID') && FACEBOOK_APP_ID !== '' && defined('FACEBOOK_APP_SECRET') && FACEBOOK_APP_SECRET !== '') ||
    (defined('MICROSOFT_CLIENT_ID') && MICROSOFT_CLIENT_ID !== '' && defined('MICROSOFT_CLIENT_SECRET') && MICROSOFT_CLIENT_SECRET !== '')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    } else {
        $old = [
            'name'  => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
        ];
        $password     = $_POST['password'] ?? '';
        $password2    = $_POST['password2'] ?? '';
        $referralCode = trim($_POST['referral_code'] ?? '');

        if (empty($old['name'])) $errors[] = 'Full name is required.';
        if (strlen($old['name']) < 2) $errors[] = 'Name must be at least 2 characters.';
        if (empty($old['email'])) $errors[] = 'Email address is required.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (empty($old['phone'])) $errors[] = 'Phone number is required.';
        if ($old['phone'] !== '' && !preg_match('/^[0-9+()\-\s]{7,30}$/', $old['phone'])) $errors[] = 'Please enter a valid phone number.';
        if (strlen($password) < MIN_PASSWORD_LEN) $errors[] = 'Password must be at least ' . MIN_PASSWORD_LEN . ' characters.';
        if ($password !== $password2) $errors[] = 'Passwords do not match.';

        if (!empty($referralCode)) {
            $refExists = dbQueryOne('SELECT id FROM referral_codes WHERE code = ? AND is_active = 1', [$referralCode]);
            if (!$refExists) {
                $errors[] = 'Invalid or inactive referral code.';
            }
        }

        if (empty($errors)) {
            $existing = dbQueryOne('SELECT id FROM users WHERE email = ?', [strtolower($old['email'])]);
            if ($existing) $errors[] = 'An account with this email already exists.';
        }

        if (empty($errors)) {
            $userId = generateId('U');
            $hashPw = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $hasJoinedDate = uthenga_column_exists('users', 'joined_date');

            if ($hasJoinedDate) {
                dbExecute(
                    'INSERT INTO users (id, name, email, phone, password_hash, role, is_approved, joined_date) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())',
                    [$userId, $old['name'], strtolower($old['email']), $old['phone'], $hashPw, ROLE_CUSTOMER, 1]
                );
            } else {
                dbExecute(
                    'INSERT INTO users (id, name, email, phone, password_hash, role, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$userId, $old['name'], strtolower($old['email']), $old['phone'], $hashPw, ROLE_CUSTOMER, 1]
                );
            }

            dbExecute(
                'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
                [$userId, $old['name'], ROLE_CUSTOMER, 'Customer Registration', "New customer account registered: {$old['email']}"]
            );

            // Process Referral
            if (!empty($referralCode)) {
                $refCodeRow = dbQueryOne('SELECT * FROM referral_codes WHERE code = ? AND is_active = 1', [$referralCode]);
                if ($refCodeRow && $refCodeRow['user_id'] !== $userId) {
                    $refUseId = generateId('RFU');
                    dbExecute("
                        INSERT INTO referral_uses (id, referral_code_id, referred_user_id, referrer_rewarded, referee_rewarded, created_at)
                        VALUES (?, ?, ?, 1, 1, NOW())
                    ", [$refUseId, $refCodeRow['id'], $userId]);

                    // Reward Referrer
                    dbExecute("UPDATE users SET loyalty_points = loyalty_points + 500 WHERE id = ?", [$refCodeRow['user_id']]);
                    dbExecute("
                        INSERT INTO loyalty_transactions (user_id, points, reason, description, created_at)
                        VALUES (?, 500, 'referral', 'Referral bonus for inviting friend', NOW())
                    ", [$refCodeRow['user_id']]);

                    // Reward Referee (New user)
                    dbExecute("UPDATE users SET loyalty_points = loyalty_points + 500 WHERE id = ?", [$userId]);
                    dbExecute("
                        INSERT INTO loyalty_transactions (user_id, points, reason, description, created_at)
                        VALUES (?, 500, 'signup', 'Sign-up bonus using referral code', NOW())
                    ", [$userId]);

                    // Increment uses count
                    dbExecute("UPDATE referral_codes SET uses_count = uses_count + 1 WHERE id = ?", [$refCodeRow['id']]);
                }
            }

            // Send Welcome Email to the User
            $welcomeSubject = 'Welcome to Uthenga!';
            $welcomeHtml = "
            <div style='font-family:sans-serif;max-width:600px;margin:auto;padding:1.5rem;border:1px solid #e2e8f0;border-radius:8px;'>
              <h2 style='color:#06b6d4;'>Welcome to Uthenga!</h2>
              <p>Hello <strong>" . e($old['name']) . "</strong>,</p>
              <p>Thank you for registering your account on Uthenga Marketplace. Below are your registration details:</p>
              <table style='width:100%;border-collapse:collapse;margin:1rem 0;'>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Full Name:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['name']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Email:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['email']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Phone:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['phone']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Account Type:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>Customer</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Registration Date:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . date('Y-m-d') . "</td></tr>
              </table>
              <p style='margin-top:1.5rem;'>You can now log in to search and book properties, tours, transport, and events.</p>
              <p>Best regards,<br>The Uthenga Team</p>
            </div>";
            @uthenga_send_mail($old['email'], $welcomeSubject, $welcomeHtml);

            // Notify Administrator
            $adminSubject = 'New User Registration: ' . $old['name'];
            $adminHtml = "
            <div style='font-family:sans-serif;max-width:600px;margin:auto;padding:1.5rem;border:1px solid #e2e8f0;border-radius:8px;'>
              <h2 style='color:#06b6d4;'>New User Registered</h2>
              <p>An administrator notification: A new user has registered on Uthenga Marketplace.</p>
              <table style='width:100%;border-collapse:collapse;margin:1rem 0;'>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Full Name:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['name']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Email:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['email']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Phone:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['phone']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Account Type:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>Customer</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Registration Date:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . date('Y-m-d') . "</td></tr>
              </table>
            </div>";
            @uthenga_send_mail(SUPPORT_EMAIL, $adminSubject, $adminHtml);

            redirect(BASE_URL . 'login.php?registered=1');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token']) ?>">
  <title>Create Account | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= rawurlencode(APP_VERSION) ?>">
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
<?php require_once __DIR__ . '/includes/page_loader.php'; ?>
<div class="auth-page">
  <div class="auth-card animate-in">
    <div class="auth-logo">
      <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/includes/logo.php'; ?>
    </div>

    <h1 class="auth-title">Create your account</h1>
    <p class="auth-subtitle">Create a customer account to book, save listings, and manage your trips.</p>

    <?php if ($socialLoginEnabled): ?>
    <div style="display:grid;gap:0.75rem;margin:1rem 0 1.5rem;">
      <?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '' && defined('GOOGLE_CLIENT_SECRET') && GOOGLE_CLIENT_SECRET !== ''): ?>
      <a href="<?= BASE_URL ?>auth/google.php?role=customer" id="btn-google-register" class="oauth-btn oauth-google">
        <?= uthenga_brand_icon_svg('google') ?>
        <span>Continue with Google</span>
      </a>
      <?php endif; ?>
      <?php if (defined('FACEBOOK_APP_ID') && FACEBOOK_APP_ID !== '' && defined('FACEBOOK_APP_SECRET') && FACEBOOK_APP_SECRET !== ''): ?>
      <a href="<?= BASE_URL ?>auth/facebook.php?role=customer" id="btn-facebook-register" class="oauth-btn oauth-facebook">
        <?= uthenga_brand_icon_svg('facebook') ?>
        <span>Continue with Facebook</span>
      </a>
      <?php endif; ?>
      <?php if (defined('MICROSOFT_CLIENT_ID') && MICROSOFT_CLIENT_ID !== '' && defined('MICROSOFT_CLIENT_SECRET') && MICROSOFT_CLIENT_SECRET !== ''): ?>
      <a href="<?= BASE_URL ?>auth/microsoft.php?role=customer" id="btn-microsoft-register" class="oauth-btn oauth-microsoft">
        <?= uthenga_brand_icon_svg('microsoft') ?>
        <span>Continue with Microsoft</span>
      </a>
      <?php endif; ?>

      <div style="display:flex;align-items:center;gap:0.75rem;margin:0.25rem 0;">
        <div style="flex:1;height:1px;background:var(--clr-border);"></div>
        <span class="text-xs text-muted" style="white-space:nowrap;">or register with email</span>
        <div style="flex:1;height:1px;background:var(--clr-border);"></div>
      </div>
    </div>
    <?php endif; ?>


    <?php if ($errors): ?>
      <div class="alert alert-error">
        <div>
          <?php foreach ($errors as $err): ?>
            <div>&times; <?= e($err) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <form method="POST" action="" id="register-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <div class="form-group">
        <label class="form-label" for="name">Full Name</label>
        <input
          type="text"
          id="name"
          name="name"
          class="form-control"
          placeholder="e.g. Grace Banda"
          value="<?= e($old['name'] ?? '') ?>"
          autocomplete="name"
          required
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control"
          placeholder="you@example.com"
          value="<?= e($old['email'] ?? '') ?>"
          autocomplete="email"
          required
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="phone">Phone Number</label>
        <input
          type="tel"
          id="phone"
          name="phone"
          class="form-control"
          placeholder="+265 999 123 456"
          value="<?= e($old['phone'] ?? '') ?>"
          autocomplete="tel"
          required
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
            placeholder="Minimum 8 characters"
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
        <label class="form-label" for="password2">Confirm Password</label>
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

      <div class="form-group">
        <label class="form-label" for="referral_code">Referral Code <span class="text-muted">(Optional)</span></label>
        <input
          type="text"
          id="referral_code"
          name="referral_code"
          class="form-control"
          placeholder="e.g. UTH12345"
          value="<?= e($refCodeVal) ?>"

    <p style="text-align:center;margin-top:1.5rem;font-size:0.875rem;color:var(--clr-text-muted);">
      Already have an account?
      <a href="<?= BASE_URL ?>login.php" style="font-weight:600;">Sign in</a>
    </p>
    <p style="text-align:center;margin-top:0.75rem;font-size:0.875rem;">
      <a href="<?= BASE_URL ?>vendor/register.php" style="color:var(--clr-text-muted);">Register as a vendor</a>
    </p>
    <p style="text-align:center;margin-top:0.5rem;font-size:0.875rem;">
      <a href="<?= BASE_URL ?>index.php" style="color:var(--clr-text-muted);">← Back to marketplace</a>
    </p>
  </div>
</div>
<script src="<?= BASE_URL ?>assets/js/main.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
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

