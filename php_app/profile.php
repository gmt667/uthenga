<?php
/**
 * Uthenga — User Profile Page
 * Allows users to update their name and change their password
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/totp_helper.php';
require_once __DIR__ . '/includes/malawi_locations.php';

requireLogin();

$userId  = $_SESSION['user_id'];
$user    = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);

$profileSuccess = '';
$profileError   = '';
$pwSuccess      = '';
$pwError        = '';

// ─── Update Profile ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCsrf()) {
        $profileError = 'Security error. Please refresh.';
    } else {
        $name   = trim((string)($_POST['name'] ?? ''));
        $email  = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone  = trim((string)($_POST['phone'] ?? ''));
        $avatar = trim((string)($_POST['avatar'] ?? ''));

        if (strlen($name) < 2) {
            $profileError = 'Name must be at least 2 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'Please enter a valid email address.';
        } elseif ($phone === '') {
            $profileError = 'Please enter a phone number.';
        } elseif (!preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
            $profileError = 'Please enter a valid phone number.';
        } else {
            $existingEmail = dbQueryOne(
                'SELECT id FROM users WHERE LOWER(email) = ? AND id <> ? LIMIT 1',
                [$email, $userId]
            );
            $existingPhone = dbQueryOne(
                'SELECT id FROM users WHERE phone = ? AND phone IS NOT NULL AND phone <> "" AND id <> ? LIMIT 1',
                [$phone, $userId]
            );

            if ($existingEmail) {
                $profileError = 'Another account already uses that email address.';
            } elseif ($existingPhone) {
                $profileError = 'Another account already uses that phone number.';
            } else {
                dbExecute(
                    'UPDATE users SET name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?',
                    [$name, $email, $phone, $avatar !== '' ? $avatar : ($user['avatar'] ?? null), $userId]
                );
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                if ($avatar !== '') {
                    $_SESSION['user_avatar'] = $avatar;
                }
                $user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);
                logAction('Profile Updated', 'User updated their profile identity and contact details.');
                $profileSuccess = 'Profile updated successfully!';
            }
        }
    }
}

// ─── Change Password ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!validateCsrf()) {
        $pwError = 'Security error. Please refresh.';
    } else {
        $current = $_POST['current_password']  ?? '';
        $new     = $_POST['new_password']       ?? '';
        $confirm = $_POST['confirm_password']   ?? '';

        if (empty($current) || empty($new) || empty($confirm)) {
            $pwError = 'All password fields are required.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $pwError = 'Current password is incorrect.';
        } elseif (strlen($new) < MIN_PASSWORD_LEN) {
            $pwError = 'New password must be at least ' . MIN_PASSWORD_LEN . ' characters.';
        } elseif ($new !== $confirm) {
            $pwError = 'New passwords do not match.';
        } elseif ($new === $current) {
            $pwError = 'New password must differ from current.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            dbExecute('UPDATE users SET password_hash = ?, must_change_pw = 0 WHERE id = ?', [$hash, $userId]);
            logAction('Password Changed', 'User voluntarily changed their password.');
            $pwSuccess = 'Password updated successfully!';
        }
    }
}

$securitySuccess = '';
$securityError   = '';

// ─── Enable 2FA ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_2fa'])) {
    if (!validateCsrf()) {
        $securityError = 'Security error. Please refresh.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $draftSecret = $_SESSION['2fa_draft_secret'] ?? '';

        if (empty($draftSecret)) {
            $securityError = '2FA session expired. Please refresh the page.';
        } elseif (empty($code)) {
            $securityError = 'Please enter the 6-digit code.';
        } elseif (!TotpHelper::verifyCode($draftSecret, $code)) {
            $securityError = 'Invalid verification code. Please try again.';
        } else {
            // Generate backup codes
            $rawBackupCodes = [];
            $hashedBackupCodes = [];
            for ($i = 0; $i < 8; $i++) {
                $bCode = strtoupper(bin2hex(random_bytes(4)));
                $rawBackupCodes[] = $bCode;
                $hashedBackupCodes[] = password_hash($bCode, PASSWORD_BCRYPT);
            }

            dbExecute("
                INSERT INTO two_factor_auth (user_id, secret, backup_codes, enabled_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE secret = VALUES(secret), backup_codes = VALUES(backup_codes), enabled_at = NOW()
            ", [$userId, $draftSecret, json_encode($hashedBackupCodes)]);

            dbExecute('UPDATE users SET two_factor_enabled = 1 WHERE id = ?', [$userId]);
            logAction('2FA Enabled', 'User enabled Two-Factor Authentication.');

            $_SESSION['2fa_backup_codes_show'] = $rawBackupCodes;
            $securitySuccess = 'Two-Factor Authentication has been enabled! Write down your backup codes below.';
            unset($_SESSION['2fa_draft_secret']);
            
            // Refresh user variable
            $user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);
        }
    }
}

// ─── Disable 2FA ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa'])) {
    if (!validateCsrf()) {
        $securityError = 'Security error. Please refresh.';
    } else {
        $password = $_POST['password'] ?? '';
        if (empty($password)) {
            $securityError = 'Please enter your password to disable 2FA.';
        } elseif (!password_verify($password, $user['password_hash'])) {
            $securityError = 'Incorrect password. Cannot disable 2FA.';
        } else {
            dbExecute('DELETE FROM two_factor_auth WHERE user_id = ?', [$userId]);
            dbExecute('UPDATE users SET two_factor_enabled = 0 WHERE id = ?', [$userId]);
            logAction('2FA Disabled', 'User disabled Two-Factor Authentication.');
            $securitySuccess = 'Two-Factor Authentication has been disabled.';
            
            // Refresh user variable
            $user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);
        }
    }
}

// ─── Revoke Device Session ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_session'])) {
    if (!validateCsrf()) {
        $securityError = 'Security error. Please refresh.';
    } else {
        $sessionToken = $_POST['session_token'] ?? '';
        if ($sessionToken) {
            dbExecute('DELETE FROM device_sessions WHERE user_id = ? AND session_token = ?', [$userId, $sessionToken]);
            logAction('Device Session Revoked', 'User terminated a device session.');
            $securitySuccess = 'Device session revoked successfully.';
        }
    }
}

// ─── Update Preferences ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    if (!validateCsrf()) {
        $securityError = 'Security error. Please refresh.';
    } else {
        $emailNotify  = isset($_POST['email_notify']) ? 1 : 0;
        $smsNotify    = isset($_POST['sms_notify']) ? 1 : 0;
        $pushNotify   = isset($_POST['push_notify']) ? 1 : 0;
        $alertEmail   = isset($_POST['login_alert_email']) ? 1 : 0;

        dbExecute("
            UPDATE users 
            SET email_notify = ?, sms_notify = ?, push_notify = ?, login_alert_email = ?
            WHERE id = ?
        ", [$emailNotify, $smsNotify, $pushNotify, $alertEmail, $userId]);

        logAction('Preferences Updated', 'User updated notification and security preferences.');
        $securitySuccess = 'Preferences updated successfully!';
        
        // Refresh user variable
        $user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);
    }
}

// Generate draft secret if 2FA setup is needed
if (empty($user['two_factor_enabled']) && empty($_SESSION['2fa_draft_secret'])) {
    $_SESSION['2fa_draft_secret'] = TotpHelper::generateSecret();
}

$pageTitle = 'My Profile';
$activeNav = '';

// Booking/wallet stats
$bookingCount = dbCount('SELECT COUNT(*) FROM bookings WHERE customer_id = ?', [$userId]);
$totalSpent   = dbQueryOne('SELECT COALESCE(SUM(total_price),0) AS total FROM bookings WHERE customer_id = ? AND payment_status = "Paid"', [$userId]);
$auditLogs    = dbQuery('SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 8', [$userId]);

// Fetch device sessions
$activeSessions = dbQuery("
    SELECT *, (session_token = ?) AS is_current 
    FROM device_sessions 
    WHERE user_id = ? 
    ORDER BY is_current DESC, last_active_at DESC
", [$_SESSION['device_session_token'] ?? '', $userId]);
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container" style="padding-top:2.5rem;padding-bottom:3rem;">

  <div class="page-header">
    <h1 class="page-title">My Profile</h1>
  </div>

  <!-- Profile Summary Card -->
  <div class="glass-panel" style="padding:2rem;display:flex;align-items:center;gap:2rem;margin-bottom:2.5rem;flex-wrap:wrap;">
    <div style="width:80px;height:80px;border-radius:50%;background:var(--clr-accent);display:flex;align-items:center;justify-content:center;font-size:2rem;color:#000;flex-shrink:0;overflow:hidden;">
      <?php if (!empty($user['avatar'])): ?>
        <img src="<?= e($user['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
      <?php else: ?>
        <?= mb_strtoupper(mb_substr($user['name'], 0, 1)) ?>
      <?php endif; ?>
    </div>
    <div style="flex:1;min-width:180px;">
      <h2 style="margin-bottom:0.25rem;"><?= e($user['name']) ?></h2>
      <div class="text-muted text-sm"><?= e($user['email']) ?></div>
      <div style="margin-top:0.5rem;">
        <?php
        $rc = 'role-customer';
        if (in_array($user['role'], ADMIN_ROLES))  $rc = 'role-admin';
        if (in_array($user['role'], VENDOR_ROLES)) $rc = 'role-vendor';
        ?>
        <span class="role-badge <?= $rc ?>"><?= e($user['role']) ?></span>
        <span class="status-badge <?= $user['is_approved'] ? 'status-confirmed' : 'status-cancelled' ?>" style="margin-left:0.5rem;">
          <?= $user['is_approved'] ? 'Active' : 'Pending Approval' ?>
        </span>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.5rem;text-align:center;">
      <div>
        <div style="font-size:1.4rem;font-weight:800;color:var(--clr-accent);"><?= number_format($bookingCount) ?></div>
        <div class="text-xs text-muted">Bookings</div>
      </div>
      <div>
        <div style="font-size:1.1rem;font-weight:800;color:var(--clr-green);"><?= formatMWK((float)$user['balance']) ?></div>
        <div class="text-xs text-muted">Wallet</div>
      </div>
      <div>
        <div style="font-size:1.1rem;font-weight:800;color:var(--clr-accent);"><?= formatMWK((float)($totalSpent['total'] ?? 0)) ?></div>
        <div class="text-xs text-muted">Total Spent</div>
      </div>
    </div>
  </div>

  <div class="glass-panel" style="padding:1.5rem;margin-bottom:2.5rem;">
    <div class="page-header" style="margin-bottom:1rem;">
      <div>
        <h3 class="page-title" style="font-size:1.35rem;">Explore Malawi</h3>
        <p class="text-muted">Mock city cards with images for the profile section and travel inspiration.</p>
      </div>
    </div>
    <div class="grid grid-cols-5 gap-4">
      <?php foreach (uthenga_malawi_featured_cities() as $city): ?>
        <a href="<?= BASE_URL ?>trip-planner.php?destination=<?= urlencode($city['city']) ?>" class="card" style="overflow:hidden;display:block;text-decoration:none;color:inherit;">
          <img src="<?= e($city['image']) ?>" alt="<?= e($city['city']) ?>" loading="lazy" style="width:100%;height:120px;object-fit:cover;">
          <div style="padding:0.9rem;">
            <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--clr-accent);"><?= e($city['district']) ?></div>
            <strong style="display:block;margin-top:.25rem;"><?= e($city['city']) ?></strong>
            <p class="text-xs text-muted" style="margin:0.35rem 0 0;"><?= e($city['summary']) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;align-items:start;flex-wrap:wrap;">

    <!-- Left: Edit Profile -->
    <div>
      <h3 style="margin-bottom:1.25rem;">Edit Profile</h3>

      <?php if ($profileSuccess): ?><div class="alert alert-success"><?= uthenga_public_icon_svg('check') ?> <?= e($profileSuccess) ?></div><?php endif; ?>
      <?php if ($profileError):   ?><div class="alert alert-error"><?= uthenga_public_icon_svg('x') ?> <?= e($profileError) ?></div><?php endif; ?>

      <form method="POST" action="" id="profile-form">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="update_profile" value="1">

        <div class="form-group">
          <label class="form-label" for="profile-name">Full Name</label>
          <input type="text" id="profile-name" name="name" class="form-control" value="<?= e($user['name']) ?>" required minlength="2">
        </div>

        <div class="form-group">
          <label class="form-label" for="profile-email">Email Address</label>
          <input type="email" id="profile-email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="profile-phone">Phone Number</label>
          <input type="tel" id="profile-phone" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+265 999 123 456" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="profile-avatar">Avatar URL (optional)</label>
          <input type="url" id="profile-avatar" name="avatar" class="form-control" value="<?= e($user['avatar'] ?? '') ?>" placeholder="https://example.com/photo.jpg">
          <p class="text-xs text-muted" style="margin-top:0.3rem;">Enter a public image URL for your avatar.</p>
        </div>

        <div class="form-group">
          <label class="form-label">Member Since</label>
          <input type="text" class="form-control" value="<?= e($user['joined_date']) ?>" disabled style="opacity:0.5;">
        </div>

        <button type="submit" id="save-profile-btn" class="btn btn-primary">Save Changes</button>
      </form>

      <!-- Change Password -->
      <h3 style="margin-top:2.5rem;margin-bottom:1.25rem;">Change Password</h3>

      <?php if ($pwSuccess): ?><div class="alert alert-success"><?= uthenga_public_icon_svg('check') ?> <?= e($pwSuccess) ?></div><?php endif; ?>
      <?php if ($pwError):   ?><div class="alert alert-error"><?= uthenga_public_icon_svg('x') ?> <?= e($pwError) ?></div><?php endif; ?>

      <form method="POST" action="" id="pw-form">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="change_password" value="1">

        <div class="form-group">
          <label class="form-label" for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password" class="form-control" placeholder="Your current password" autocomplete="current-password" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min <?= MIN_PASSWORD_LEN ?> characters" autocomplete="new-password" required minlength="<?= MIN_PASSWORD_LEN ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat new password" autocomplete="new-password" required>
          <div id="pw-match-indicator" class="text-xs" style="margin-top:0.35rem;"></div>
        </div>

        <button type="submit" id="change-pw-btn" class="btn btn-secondary">Update Password</button>
      </form>

      <!-- Preferences -->
      <h3 style="margin-top:2.5rem;margin-bottom:1.25rem;">Notification Preferences</h3>
      <?php if ($securitySuccess && isset($_POST['update_preferences'])): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem;"><?= uthenga_public_icon_svg('check') ?> <?= e($securitySuccess) ?></div>
      <?php endif; ?>
      <form method="POST" action="" id="pref-form">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="update_preferences" value="1">

        <div style="display:grid;gap:0.75rem;margin-bottom:1.5rem;">
          <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
            <input type="checkbox" name="email_notify" value="1" <?= $user['email_notify'] ? 'checked' : '' ?>>
            <span>Email Notifications</span>
          </label>
          <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
            <input type="checkbox" name="sms_notify" value="1" <?= $user['sms_notify'] ? 'checked' : '' ?>>
            <span>SMS Notifications (Travel Alerts)</span>
          </label>
          <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
            <input type="checkbox" name="push_notify" value="1" <?= $user['push_notify'] ? 'checked' : '' ?>>
            <span>In-app Push Notifications</span>
          </label>
          <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
            <input type="checkbox" name="login_alert_email" value="1" <?= $user['login_alert_email'] ? 'checked' : '' ?>>
            <span>Email alerts on suspicious logins</span>
          </label>
        </div>

        <button type="submit" class="btn btn-secondary">Save Preferences</button>
      </form>
    </div>

    <!-- Right: Security & Activity -->
    <div>
      <!-- 2FA Setup -->
      <h3 style="margin-bottom:1.25rem;">Two-Factor Authentication</h3>
      <?php if ($securityError): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem;"><?= uthenga_public_icon_svg('x') ?> <?= e($securityError) ?></div>
      <?php endif; ?>
      <?php if ($securitySuccess && (isset($_POST['enable_2fa']) || isset($_POST['disable_2fa']))): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem;"><?= uthenga_public_icon_svg('check') ?> <?= e($securitySuccess) ?></div>
      <?php endif; ?>

      <?php if (!empty($user['two_factor_enabled'])): ?>
        <div class="card" style="padding:1.5rem;border:1px solid var(--clr-green);background:rgba(16,185,129,0.05);margin-bottom:1rem;">
          <div style="display:flex;align-items:center;gap:0.5rem;font-weight:700;color:var(--clr-green);margin-bottom:0.5rem;">
            <span><?= uthenga_public_icon_svg('lock') ?></span> Two-Factor Authentication is Active
          </div>
          <p class="text-xs text-muted" style="margin-bottom:1rem;">Your account is protected by an additional verification step during login.</p>
          
          <form method="POST" action="" onsubmit="return confirm('Are you sure you want to disable 2FA? This decreases account security.');">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="disable_2fa" value="1">
            <div class="form-group" style="margin-bottom:0.75rem;">
              <label class="form-label" style="font-size:0.75rem;">Enter password to disable 2FA</label>
              <input type="password" name="password" class="form-control form-control-sm" placeholder="Current Password" required>
            </div>
            <button type="submit" class="btn btn-danger btn-sm">Disable 2FA</button>
          </form>
        </div>

        <?php if (!empty($_SESSION['2fa_backup_codes_show'])): ?>
          <div class="card" style="padding:1.5rem;border:1px solid var(--clr-accent);background:rgba(245,158,11,0.05);margin-bottom:1.5rem;">
            <h4 style="color:var(--clr-accent);margin-bottom:0.5rem;display:flex;align-items:center;gap:0.5rem;"><?= uthenga_public_icon_svg('warning') ?> Save Your Backup Codes</h4>
            <p class="text-xs text-muted" style="margin-bottom:0.75rem;">If you lose access to your authenticator app, these codes can be used to log in. Each code can only be used once.</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;font-family:monospace;font-size:1.1rem;text-align:center;background:rgba(0,0,0,0.1);padding:0.75rem;border-radius:var(--radius-sm);margin-bottom:0.75rem;">
              <?php foreach ($_SESSION['2fa_backup_codes_show'] as $bcode): ?>
                <div><?= e($bcode) ?></div>
              <?php endforeach; ?>
            </div>
            <p class="text-xs" style="color:var(--clr-accent);">Store these codes safely. They will not be shown again.</p>
          </div>
          <?php unset($_SESSION['2fa_backup_codes_show']); ?>
        <?php endif; ?>

      <?php else: ?>
        <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">
          <p class="text-sm text-muted" style="margin-bottom:1rem;">Protect your account with Google Authenticator, Authy, or any TOTP compatible app.</p>
          
          <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;margin-bottom:1.25rem;">
            <div style="background:#fff;padding:0.5rem;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;margin:0 auto;">
              <img src="<?= TotpHelper::getQrCodeUrl($user['email'], $_SESSION['2fa_draft_secret'] ?? '') ?>" alt="QR Code" style="width:130px;height:130px;">
            </div>
            <div style="flex:1;min-width:180px;">
              <div class="text-xs text-muted">Secret Key:</div>
              <div style="font-family:monospace;font-size:0.95rem;font-weight:700;letter-spacing:0.05em;background:var(--clr-surface2);padding:0.35rem 0.65rem;border-radius:4px;display:inline-block;margin-top:0.25rem;word-break:break-all;">
                <?= e(implode(' ', str_split($_SESSION['2fa_draft_secret'] ?? '', 4))) ?>
              </div>
              <p class="text-xs text-muted" style="margin-top:0.5rem;">Scan the QR code or enter the key manually to get started.</p>
            </div>
          </div>

          <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="enable_2fa" value="1">
            <div class="form-group" style="margin-bottom:0.75rem;">
              <label class="form-label" style="font-size:0.85rem;">Verification Code</label>
              <div style="display:flex;gap:0.5rem;">
                <input type="text" name="code" class="form-control" placeholder="123456" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" required style="letter-spacing:0.1em;text-align:center;font-size:1.1rem;">
                <button type="submit" class="btn btn-primary" style="white-space:nowrap;">Enable 2FA</button>
              </div>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <!-- Active Devices -->
      <h3 style="margin-top:2rem;margin-bottom:1rem;">Active Devices &amp; Sessions</h3>
      <?php if ($securitySuccess && isset($_POST['revoke_session'])): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem;"><?= uthenga_public_icon_svg('check') ?> <?= e($securitySuccess) ?></div>
      <?php endif; ?>
      <div style="display:grid;gap:0.75rem;margin-bottom:2rem;">
        <?php if (empty($activeSessions)): ?>
          <p class="text-sm text-muted">No device sessions tracked.</p>
        <?php else: ?>
          <?php foreach ($activeSessions as $sess): ?>
            <div class="card" style="padding:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;">
              <div style="display:flex;gap:0.75rem;align-items:center;">
                <span style="font-size:1.5rem;"><?= $sess['device_type'] === 'mobile' ? uthenga_public_icon_svg('phone') : uthenga_public_icon_svg('globe') ?></span>
                <div>
                  <div style="font-size:0.875rem;font-weight:600;">
                    <?= e($sess['device_name']) ?>
                    <?php if ($sess['is_current']): ?>
                      <span class="status-badge status-confirmed" style="font-size:0.65rem;margin-left:0.35rem;padding:0.1rem 0.4rem;background:var(--clr-green);color:#fff;">This Device</span>
                    <?php endif; ?>
                  </div>
                  <div class="text-xs text-muted" style="margin-top:0.1rem;">
                    IP: <?= e($sess['ip_address']) ?> &middot; Active: <?= e(date('M j, Y H:i', strtotime($sess['last_active_at']))) ?>
                  </div>
                </div>
              </div>
              
              <?php if (!$sess['is_current']): ?>
                <form method="POST" action="" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                  <input type="hidden" name="revoke_session" value="1">
                  <input type="hidden" name="session_token" value="<?= e($sess['session_token']) ?>">
                  <button type="submit" class="btn btn-sm btn-ghost" style="color:#ef4444;border-color:rgba(239,68,68,0.25);">Revoke</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <h3 style="margin-bottom:1.25rem;">Recent Activity</h3>
      <?php if (empty($auditLogs)): ?>
        <p class="text-muted text-sm">No recent activity recorded.</p>
      <?php else: ?>
        <div style="display:grid;gap:0.6rem;">
          <?php foreach ($auditLogs as $log): ?>
          <div style="display:flex;gap:0.75rem;padding:0.85rem;background:var(--clr-surface);border:1px solid var(--clr-border);border-radius:var(--radius-md);align-items:flex-start;">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--clr-surface2);display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0;">
              <?php
              $icon = match(true) {
                  str_contains($log['action'], 'Login')    => uthenga_public_icon_svg('lock'),
                  str_contains($log['action'], 'Booking')  => uthenga_public_icon_svg('ticket'),
                  str_contains($log['action'], 'Password') => uthenga_public_icon_svg('lock'),
                  str_contains($log['action'], 'Profile')  => uthenga_public_icon_svg('user'),
                  str_contains($log['action'], 'Ticket')   => uthenga_public_icon_svg('ticket'),
                  default                                   => uthenga_public_icon_svg('info'),
              };
              echo $icon;
              ?>
            </div>
            <div>
              <div style="font-size:0.85rem;font-weight:600;"><?= e($log['action']) ?></div>
              <div class="text-xs text-muted" style="margin-top:0.1rem;"><?= e(substr($log['details'], 0, 80)) ?><?= strlen($log['details']) > 80 ? '…' : '' ?></div>
              <div class="text-xs text-muted" style="margin-top:0.15rem;"><?= e(substr($log['created_at'], 0, 16)) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-secondary btn-sm" style="margin-top:1rem;width:100%;text-align:center;" id="view-bookings-btn">Go to Dashboard &rarr;</a>
      <?php endif; ?>

      <!-- Quick Links -->
      <h3 style="margin-top:2rem;margin-bottom:1rem;">Quick Links</h3>
      <div style="display:grid;gap:0.5rem;">
        <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-secondary" style="text-align:center;" id="profile-bookings-link"><?= uthenga_public_icon_svg('home') ?> Customer Dashboard</a>
        <a href="<?= BASE_URL ?>support.php" class="btn btn-secondary" style="text-align:center;" id="profile-support-link"><?= uthenga_public_icon_svg('mail') ?> Support Tickets</a>
        <a href="<?= BASE_URL ?>index.php" class="btn btn-secondary" style="text-align:center;" id="profile-explore-link"><?= uthenga_public_icon_svg('globe') ?> Explore Listings</a>
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-danger" style="text-align:center;" id="profile-logout-link"><?= uthenga_public_icon_svg('x') ?> Logout</a>
      </div>
    </div>
  </div>
</div>

<script>
const newPw  = document.getElementById('new_password');
const confPw = document.getElementById('confirm_password');
const ind    = document.getElementById('pw-match-indicator');
function checkMatch() {
  if (!confPw || !confPw.value) { if(ind) ind.textContent=''; return; }
  if (newPw.value === confPw.value) {
    ind.innerHTML = '<?= uthenga_public_icon_svg('check') ?> Passwords match';
    ind.style.color = 'var(--clr-green)';
  } else {
    ind.innerHTML = '<?= uthenga_public_icon_svg('x') ?> Passwords do not match';
    ind.style.color = '#ef4444';
  }
}
if (newPw)  newPw.addEventListener('input', checkMatch);
if (confPw) confPw.addEventListener('input', checkMatch);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
