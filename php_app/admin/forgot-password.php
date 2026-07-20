<?php
/**
 * Uthenga — Admin Forgot Password (stub)
 *
 * UI placeholder only. Reset logic (token generation, email delivery,
 * password_resets table wiring) will be implemented later.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = 'Security token mismatch. Please reload the page.';
    } else {
        // TODO: implement reset logic — generate token, store in
        // password_resets, and email the link to the admin.
        $submitted = true;
    }
}

$pageTitle = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title>Forgot Password | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .admin-login-body {
      background: radial-gradient(ellipse at bottom, #111827, #030712);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .admin-login-card {
      background: rgba(17, 24, 39, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(24px);
      border-radius: var(--radius-xl);
      padding: 2.5rem;
      width: 100%;
      max-width: 440px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
    }
    .admin-login-title {
      font-size: 1.5rem;
      font-weight: 800;
      text-align: center;
      margin-bottom: 0.25rem;
      color: #fff;
    }
  </style>
</head>
<body class="admin-login-body">

<div class="admin-login-card animate-in">
  <div class="auth-logo" style="margin-bottom:1.25rem;">
    <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/../includes/logo.php'; ?>
  </div>
  <h1 class="admin-login-title"><?= APP_NAME ?> Portal</h1>
  <p class="text-xs text-muted" style="text-align: center; margin-bottom: 2rem;">Reset Administrator Password</p>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error" style="margin-bottom: 1.5rem;">✕ <?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($submitted): ?>
    <div class="alert alert-info" style="margin-bottom: 1.5rem;">
      Password reset isn't available yet — this feature is coming soon.
      Please contact a Super Administrator to reset your password manually.
    </div>
  <?php else: ?>
    <form method="POST" action="forgot-password.php" id="admin-forgot-form">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

      <div class="form-group" style="margin-bottom: 2rem;">
        <label class="form-label" for="forgot-email">Admin Email Address</label>
        <input type="email" id="forgot-email" name="email" class="form-control" placeholder="admin@uthenga.com" required autocomplete="email" autofocus>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; font-weight: 700; background: linear-gradient(135deg, var(--clr-accent) 0%, #d97706 100%);">
        Send Reset Link →
      </button>
    </form>
  <?php endif; ?>

  <p class="text-xs text-muted" style="text-align: center; margin-top: 2rem;">
    <a href="<?= BASE_URL ?>admin/login.php" style="color: var(--clr-text-muted);">← Back to Admin Login</a>
  </p>
</div>

</body>
</html>