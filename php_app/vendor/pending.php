<?php
/**
 * Uthenga — Vendor Pending Approval Page
 * Shown after vendor registration until admin approves the account.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

// Must be logged in as a vendor role to see this page
if (!isLoggedIn()) redirect(BASE_URL . 'login.php');

$role = $_SESSION['user_role'];
if (!in_array($role, VENDOR_ROLES)) redirect(BASE_URL . 'dashboard.php');

$user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);

// Already approved → go to portal
if ($user && $user['is_approved']) redirect(BASE_URL . 'vendor/dashboard.php');

$profile = dbQueryOne('SELECT * FROM vendor_profiles WHERE vendor_id = ?', [$_SESSION['user_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Pending Approval | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= rawurlencode(APP_VERSION) ?>">
</head>
<body>
<div class="auth-page" style="min-height:100vh;">
  <div class="auth-card animate-in" style="max-width:520px;text-align:center;">

    <div class="auth-logo" style="margin-bottom:1.25rem;">
      <?php $logoSize = 'lg'; $logoLink = false; require __DIR__ . '/../includes/logo.php'; ?>
    </div>

    <div style="font-size:3.5rem;margin-bottom:0.5rem;">⏳</div>

    <h1 class="auth-title" style="font-size:1.6rem;">Application Under Review</h1>

    <p class="auth-subtitle" style="margin-bottom:1.5rem;">
      Thank you for registering, <strong><?= e(explode(' ', $user['name'])[0]) ?></strong>!
      Your vendor application is currently being reviewed by our team.
    </p>

    <div class="alert alert-info" style="text-align:left;">
      <div>
        <strong>What happens next?</strong>
        <ol style="margin-top:0.75rem;padding-left:1.5rem;line-height:1.9;font-size:0.875rem;">
          <li>Our team reviews your business details (typically 24–48 hours)</li>
          <li>You'll receive an email notification once your account is approved</li>
          <li>After approval, you can log in and access your vendor dashboard</li>
          <li>Upload your listings and start receiving bookings</li>
        </ol>
      </div>
    </div>

    <div class="glass-panel" style="padding:1.25rem;margin-top:1.5rem;text-align:left;">
      <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--clr-accent);margin-bottom:0.75rem;">Your Application</div>
      <div style="display:grid;gap:0.5rem;font-size:0.875rem;">
        <div style="display:flex;justify-content:space-between;">
          <span class="text-muted">Name</span>
          <strong><?= e($user['name']) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span class="text-muted">Email</span>
          <strong><?= e($user['email']) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span class="text-muted">Category</span>
          <strong><?= e($profile['category'] ?? $role) ?></strong>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span class="text-muted">Status</span>
          <span class="badge badge-pending">Pending Review</span>
        </div>
      </div>
    </div>

    <div style="display:grid;gap:0.75rem;margin-top:1.75rem;">
      <a href="<?= BASE_URL ?>support.php?tab=new" class="btn btn-secondary">
        💬 Contact Support
      </a>
      <a href="<?= BASE_URL ?>logout.php" class="btn btn-ghost" style="opacity:0.7;">
        Sign Out
      </a>
      <a href="<?= BASE_URL ?>index.php" class="btn btn-ghost" style="opacity:0.6;font-size:0.875rem;">
        ← Browse Marketplace
      </a>
    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>assets/js/main.js?v=<?= rawurlencode(APP_VERSION) ?>"></script>
</body>
</html>
