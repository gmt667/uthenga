<?php
/**
 * Uthenga - Password Reset Request
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (isLoggedIn()) {
    redirect(BASE_URL . 'dashboard.php');
}

$message = '';
$error = '';
$devResetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if ($email === '') {
            $error = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $message = 'If that email address exists in our system, a password reset link has been sent to the official account inbox.';
            $user = dbQueryOne('SELECT id, email, name FROM users WHERE email = ? LIMIT 1', [strtolower($email)]);

            if ($user) {
                try {
                    dbExecute('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
                } catch (Throwable $ignored) {
                }

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + (PASSWORD_RESET_TTL_MINUTES * 60));

                dbExecute(
                    'INSERT INTO password_resets (user_id, reset_token_hash, expires_at) VALUES (?, ?, ?)',
                    [$user['id'], $tokenHash, $expiresAt]
                );

                $resetLink = BASE_URL . 'reset_password.php?token=' . urlencode($token);
                $subject = APP_NAME . ' password reset request';
                $html = '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:0 auto;padding:24px;color:#111827;">
                    <h2 style="margin:0 0 12px;">Password reset requested</h2>
                    <p style="margin:0 0 16px;">We received a request to reset the password for <strong>' . e($user['email']) . '</strong>.</p>
                    <p style="margin:0 0 16px;">Use the secure link below to create a new password. This link expires in ' . (int) PASSWORD_RESET_TTL_MINUTES . ' minutes.</p>
                    <p style="margin:0 0 20px;"><a href="' . e($resetLink) . '" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#f59e0b;color:#111827;text-decoration:none;font-weight:700;">Reset Password</a></p>
                    <p style="margin:0;color:#6b7280;font-size:14px;">If you did not request this change, you can safely ignore this email.</p>
                </div>';
                $text = "Password reset requested for {$user['email']}\n\nReset link: {$resetLink}\n\nThis link expires in " . PASSWORD_RESET_TTL_MINUTES . " minutes.";
                uthenga_send_mail($user['email'], $subject, $html, $text);

                if (APP_ENV === 'development') {
                    $devResetLink = $resetLink;
                }
            }
        }
    }
}

$pageTitle = 'Reset Password';
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
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= rawurlencode(APP_VERSION) ?>">
</head>
<body>
<?php require_once __DIR__ . '/includes/page_loader.php'; ?>
<div class="auth-page">
  <div class="auth-card animate-in">
    <div class="auth-logo">
      <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/includes/logo.php'; ?>
    </div>

    <h1 class="auth-title">Reset your password</h1>
    <p class="auth-subtitle">We will send a secure reset link to the official email address on file.</p>

    <?php if ($error): ?>
      <div class="alert alert-error">âœ– <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
      <div class="alert alert-success">âœ“ <?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($devResetLink): ?>
      <div class="alert alert-info" style="margin-top:1rem;">
        <strong>Development link:</strong><br>
        <a href="<?= e($devResetLink) ?>"><?= e($devResetLink) ?></a>
      </div>
    <?php endif; ?>

    <form method="POST" action="" style="margin-top:1.5rem;">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control"
          placeholder="you@example.com"
          value="<?= e($_POST['email'] ?? '') ?>"
          autocomplete="email"
          required
        >
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">Send Reset Link</button>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:0.875rem;color:var(--clr-text-muted);">
      <a href="<?= BASE_URL ?>login.php">Back to login</a>
    </p>
  </div>
</div>
</body>
</html>
