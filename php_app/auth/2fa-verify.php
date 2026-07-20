<?php
/**
 * Uthenga — 2FA Verification Page
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/totp_helper.php';
require_once __DIR__ . '/../includes/security_helper.php';

// If they are already logged in, redirect them out
if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

// Ensure 2FA verification is actually pending
if (empty($_SESSION['2fa_pending']) || empty($_SESSION['2fa_user_id'])) {
    redirect(BASE_URL . 'login.php');
}

$userId = $_SESSION['2fa_user_id'];
$error = '';
$useBackup = isset($_GET['backup']) && $_GET['backup'] === '1';

// Fetch user two-factor configuration
$tfConfig = dbQueryOne('SELECT secret, backup_codes FROM two_factor_auth WHERE user_id = ?', [$userId]);
if (!$tfConfig) {
    // Session is invalid or DB state is corrupted; reset and go back
    unset($_SESSION['2fa_pending'], $_SESSION['2fa_user_id']);
    redirect(BASE_URL . 'login.php?error=2fa_not_configured');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token mismatch. Please try again.';
    } else {
        if ($useBackup) {
            $code = trim($_POST['backup_code'] ?? '');
            $codes = json_decode($tfConfig['backup_codes'] ?? '[]', true) ?: [];
            $foundIndex = -1;

            foreach ($codes as $index => $hashedCode) {
                // Check if it matches hashed code or plaintext fallback
                if (password_verify($code, $hashedCode) || $code === $hashedCode) {
                    $foundIndex = $index;
                    break;
                }
            }

            if ($foundIndex !== -1) {
                // Success! Consume the backup code
                unset($codes[$foundIndex]);
                $codes = array_values($codes);
                dbExecute('UPDATE two_factor_auth SET backup_codes = ? WHERE user_id = ?', [json_encode($codes), $userId]);

                // Log audit action
                dbExecute('INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
                    [$userId, $_SESSION['2fa_user_name'], $_SESSION['2fa_user_role'], '2FA Backup Code Used', 'User authenticated using a 2FA backup code.']);

                completeLogin($userId);
            } else {
                $error = 'Invalid backup code. Please try again.';
            }
        } else {
            $code = trim($_POST['code'] ?? '');
            if (TotpHelper::verifyCode($tfConfig['secret'], $code)) {
                // Success!
                dbExecute('UPDATE two_factor_auth SET last_used_at = NOW() WHERE user_id = ?', [$userId]);

                // Log audit action
                dbExecute('INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
                    [$userId, $_SESSION['2fa_user_name'], $_SESSION['2fa_user_role'], '2FA Authenticated', 'Successful two-factor authentication.']);

                completeLogin($userId);
            } else {
                $error = 'Invalid authentication code. Please try again.';
            }
        }
    }
}

function completeLogin(string $userId): void {
    // Fetch full user record to restore standard session fields
    $user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);

    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['name'];
    $_SESSION['user_role']    = $user['role'];
    $_SESSION['user_email']   = $user['email'];
    $_SESSION['user_balance'] = $user['balance'];
    $_SESSION['user_avatar']  = $user['avatar'] ?? '';

    // Clear 2FA pending flag
    unset($_SESSION['2fa_pending'], $_SESSION['2fa_user_id'], $_SESSION['2fa_user_role'], $_SESSION['2fa_user_name']);

    // Register device session & login alerts
    registerDeviceSession($userId);

    // Redirect to dashboard or appropriate page
    $role = $user['role'];
    $redirectUrl = BASE_URL . 'dashboard.php';
    if ($role === ROLE_SUPER_ADMIN) {
        $redirectUrl = BASE_URL . 'admin/super-dashboard.php';
    } elseif (in_array($role, ADMIN_ROLES, true)) {
        $redirectUrl = BASE_URL . 'admin/dashboard.php';
    } elseif (in_array($role, VENDOR_ROLES)) {
        $redirectUrl = BASE_URL . 'vendor/dashboard.php';
    }

    redirect($redirectUrl);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Two-Factor Authentication | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    .auth-card {
      max-width: 420px;
      margin: 5rem auto;
      padding: 2.5rem;
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
  </style>
</head>
<body>

<main class="container">
  <div class="auth-card">
    <div style="text-align: center; margin-bottom: 2rem;">
      <div style="font-size: 3rem; margin-bottom: 1rem;">🔐</div>
      <h2 style="font-weight: 800;">Two-Factor Authentication</h2>
      <p class="text-muted" style="font-size: 0.875rem; margin-top: 0.25rem;">
        <?= $useBackup ? 'Enter one of your emergency backup codes.' : 'Open your authenticator app to get the verification code.' ?>
      </p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom: 1.5rem;">✕ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <?php if ($useBackup): ?>
        <div class="form-group">
          <label class="form-label" for="backup_code">Backup Code</label>
          <input type="text" id="backup_code" name="backup_code" class="form-control" 
                 placeholder="e.g. 1234-5678" style="text-align: center; font-family: monospace; font-size: 1.1rem; letter-spacing: 0.1em;" required autocomplete="off" autofocus>
        </div>
      <?php else: ?>
        <div class="form-group">
          <label class="form-label" for="code">Verification Code</label>
          <input type="text" id="code" name="code" class="form-control" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" 
                 placeholder="123456" style="text-align: center; font-size: 1.5rem; letter-spacing: 0.2em; font-weight: 700;" required autocomplete="one-time-code" autofocus>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem; font-size: 1rem;">
        Verify &amp; Continue
      </button>
    </form>

    <div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem;">
      <?php if ($useBackup): ?>
        <a href="?backup=0" style="font-weight: 600;">Use Authenticator App Instead</a>
      <?php else: ?>
        <a href="?backup=1" style="font-weight: 600; color: var(--clr-cyan);">Use a Backup Code</a>
      <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 1rem; border-top: 1px solid var(--clr-border); padding-top: 1rem;">
      <a href="<?= BASE_URL ?>logout.php" class="text-muted" style="font-size: 0.875rem;">Cancel &amp; Sign Out</a>
    </div>
  </div>
</main>

</body>
</html>
