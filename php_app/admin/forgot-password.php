<?php
/**
 * Uthenga - Admin Forgot Password
 * Sends a reset link after verifying the admin identity fields.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$submitted = false;
$error = '';
$message = '';
$old = [
    'name' => '',
    'email' => '',
    'phone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token mismatch. Please reload the page.';
    } else {
        $old = [
            'name'  => trim((string)($_POST['name'] ?? '')),
            'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
            'phone' => trim((string)($_POST['phone'] ?? '')),
        ];

        if ($old['name'] === '' || $old['email'] === '' || $old['phone'] === '') {
            $error = 'Please complete all identity fields.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid official email address.';
        } else {
            $user = dbQueryOne(
                'SELECT id, name, email, phone, role, is_approved FROM users WHERE LOWER(email) = ? AND LOWER(name) = ? AND COALESCE(phone, "") = ? LIMIT 1',
                [$old['email'], strtolower($old['name']), $old['phone']]
            );

            if (!$user || !in_array((string)($user['role'] ?? ''), ADMIN_ROLES, true)) {
                $error = 'We could not verify those administrator details.';
            } elseif (empty($user['is_approved'])) {
                $error = 'This administrator account is not active.';
            } else {
                try {
                    $plainToken = bin2hex(random_bytes(32));
                    $hashToken = hash('sha256', $plainToken);
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                    dbExecute('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
                    dbExecute(
                        'INSERT INTO password_resets (user_id, reset_token_hash, expires_at) VALUES (?, ?, ?)',
                        [$user['id'], $hashToken, $expiresAt]
                    );

                    $resetLink = BASE_URL . 'reset_password.php?token=' . urlencode($plainToken);
                    $subject = APP_NAME . ' admin password reset';
                    $html = "
                        <div style='font-family:Arial,sans-serif;max-width:640px;margin:auto;padding:24px;border:1px solid #e2e8f0;border-radius:14px;background:#ffffff;'>
                          <h2 style='margin:0 0 12px;color:#0f172a;'>Admin Password Reset</h2>
                          <p style='margin:0 0 12px;color:#334155;'>Hello " . e($user['name']) . ",</p>
                          <p style='margin:0 0 16px;color:#334155;'>We received a request to reset your administrator password. Use the secure link below within 60 minutes.</p>
                          <p style='margin:0 0 20px;'>
                            <a href='" . e($resetLink) . "' style='display:inline-block;background:#06b6d4;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:700;'>Reset Password</a>
                          </p>
                          <p style='margin:0;color:#64748b;font-size:13px;'>If you did not request this reset, you can ignore this email.</p>
                        </div>
                    ";
                    @uthenga_send_mail($user['email'], $subject, $html);

                    $message = 'If the details match an administrator account, a reset link has been sent to the official email address.';
                    $submitted = true;
                } catch (Throwable $e) {
                    $error = 'Unable to create a reset link right now. Please try again later.';
                    error_log('[Uthenga admin forgot password] ' . $e->getMessage());
                }
            }
        }
    }
}

$pageTitle = 'Forgot Password';
$themePreference = uthenga_theme_preference();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($themePreference) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <meta name="theme-color" content="<?= $themePreference === 'dark' ? '#0b1120' : '#f8fafc' ?>">
  <title>Forgot Password | <?= APP_NAME ?></title>
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
      padding: 2.25rem;
      width: 100%;
      max-width: 520px;
      box-shadow: var(--shadow-lg);
    }
    .admin-login-title {
      font-size: 1.5rem;
      font-weight: 800;
      text-align: center;
      margin-bottom: 0.25rem;
      color: var(--clr-text);
    }
    .admin-forgot-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
    }
    @media (max-width: 560px) {
      .admin-login-body { padding: 1rem; }
      .admin-login-card { padding: 1.25rem; }
      .admin-forgot-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body class="admin-login-body">
<div class="admin-login-card animate-in">
  <div class="auth-logo" style="margin-bottom:1.25rem;">
    <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/../includes/logo.php'; ?>
  </div>
  <h1 class="admin-login-title">Admin Account Recovery</h1>
  <p class="text-xs text-muted" style="text-align:center;margin-bottom:1.5rem;">We will verify your details before sending a reset link.</p>

  <?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:1rem;">Error: <?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($submitted): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;"><?= e($message) ?></div>
  <?php else: ?>
    <form method="POST" action="forgot-password.php" id="admin-forgot-form" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <div class="admin-forgot-grid">
        <div class="form-group">
          <label class="form-label" for="forgot-name">Full Name</label>
          <input type="text" id="forgot-name" name="name" class="form-control" placeholder="Administrator name" value="<?= e($old['name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="forgot-email">Official Email</label>
          <input type="email" id="forgot-email" name="email" class="form-control" placeholder="admin@uthenga.com" value="<?= e($old['email']) ?>" required autocomplete="email">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="forgot-phone">Phone Number</label>
        <input type="tel" id="forgot-phone" name="phone" class="form-control" placeholder="+265 999 123 456" value="<?= e($old['phone']) ?>" required autocomplete="tel">
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;font-weight:700;">
        Send Reset Link
      </button>
    </form>
  <?php endif; ?>

  <p class="text-xs text-muted" style="text-align:center;margin-top:1.5rem;">
    <a href="<?= BASE_URL ?>admin/login.php" style="color:var(--clr-text-muted);">Back to Admin Login</a>
  </p>
</div>
</body>
</html>
