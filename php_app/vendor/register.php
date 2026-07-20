<?php
/**
 * Uthenga - Vendor Registration Page
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === ROLE_SUPER_ADMIN) {
        redirect(BASE_URL . 'admin/super-dashboard.php');
    } elseif (in_array($role, ADMIN_ROLES, true)) {
        redirect(BASE_URL . 'admin/dashboard.php');
    } elseif (in_array($role, VENDOR_ROLES, true)) {
        $current = dbQueryOne('SELECT is_approved FROM users WHERE id = ?', [$_SESSION['user_id']]);
        redirect(($current && !$current['is_approved']) ? BASE_URL . 'vendor/pending.php' : BASE_URL . 'vendor/dashboard.php');
    }
    redirect(BASE_URL . 'dashboard.php');
}

$errors = [];
$old = [];
$categories = ['Hotel', 'Lodge', 'Tour Operator', 'Transport Provider', 'Event Organizer', 'Mbanda Seller', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    } else {
        $old = [
            'name'          => trim($_POST['name'] ?? ''),
            'email'         => trim($_POST['email'] ?? ''),
            'phone'         => trim($_POST['phone'] ?? ''),
            'business_name' => trim($_POST['business_name'] ?? ''),
            'category'      => trim($_POST['category'] ?? ''),
            'city'          => trim($_POST['city'] ?? ''),
            'address'       => trim($_POST['address'] ?? ''),
            'description'   => trim($_POST['description'] ?? ''),
        ];
        $password  = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password2'] ?? '');

        if ($old['name'] === '') $errors[] = 'Full name is required.';
        if ($old['email'] === '') $errors[] = 'Email address is required.';
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if ($old['phone'] === '') $errors[] = 'Phone number is required.';
        if (!preg_match('/^[0-9+()\-\s]{7,30}$/', $old['phone'])) $errors[] = 'Please enter a valid phone number.';
        if ($old['business_name'] === '') $errors[] = 'Business name is required.';
        if ($old['category'] === '') $errors[] = 'Business category is required.';
        if (!in_array($old['category'], $categories, true)) $errors[] = 'Please choose a valid business category.';
        if ($password === '') $errors[] = 'Password is required.';
        if (strlen($password) < MIN_PASSWORD_LEN) $errors[] = 'Password must be at least ' . MIN_PASSWORD_LEN . ' characters.';
        if ($password !== $password2) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $exists = dbQueryOne('SELECT id FROM users WHERE email = ? OR phone = ?', [strtolower($old['email']), $old['phone']]);
            if ($exists) {
                $errors[] = 'An account with this email or phone already exists.';
            }
        }

        if (empty($errors)) {
            $userId = generateId('U');
            $hashPw = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $email = strtolower($old['email']);

            dbExecute(
                'INSERT INTO users (id, name, email, phone, password_hash, role, is_approved, joined_date) VALUES (?, ?, ?, ?, ?, ?, 0, CURDATE())',
                [$userId, $old['name'], $email, $old['phone'], $hashPw, ROLE_VENDOR]
            );

            try {
                dbExecute(
                    'INSERT INTO vendor_profiles (vendor_id, business_name, phone, address, city, category, description, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$userId, $old['business_name'], $old['phone'], $old['address'], $old['city'], $old['category'], $old['description'], 'pending']
                );
            } catch (Throwable $e) {
                error_log('[Uthenga vendor register] vendor_profiles insert failed: ' . $e->getMessage());
            }

            dbExecute(
                'INSERT INTO audit_logs (user_id, user_name, user_role, action, details) VALUES (?, ?, ?, ?, ?)',
                [$userId, $old['name'], ROLE_VENDOR, 'Vendor Registration', "New vendor application submitted for {$old['business_name']}"]
            );

            $welcomeSubject = 'Vendor application received';
            $welcomeHtml = "
            <div style='font-family:sans-serif;max-width:600px;margin:auto;padding:1.5rem;border:1px solid #e2e8f0;border-radius:8px;'>
              <h2 style='color:#06b6d4;'>Vendor Application Received</h2>
              <p>Hello <strong>" . e($old['name']) . "</strong>,</p>
              <p>We have received your vendor application for <strong>" . e($old['business_name']) . "</strong>.</p>
              <table style='width:100%;border-collapse:collapse;margin:1rem 0;'>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Business:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['business_name']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Category:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['category']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Status:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>Pending Verification</td></tr>
              </table>
              <p>Our Admin or Super Admin team will review it and notify you once approved.</p>
            </div>";
            @uthenga_send_mail($email, $welcomeSubject, $welcomeHtml);

            $adminSubject = 'New Vendor Application: ' . $old['business_name'];
            $adminHtml = "
            <div style='font-family:sans-serif;max-width:600px;margin:auto;padding:1.5rem;border:1px solid #e2e8f0;border-radius:8px;'>
              <h2 style='color:#06b6d4;'>New Vendor Application</h2>
              <table style='width:100%;border-collapse:collapse;margin:1rem 0;'>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Name:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['name']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Email:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['email']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Phone:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['phone']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Business:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['business_name']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>Category:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['category']) . "</td></tr>
                <tr><td style='padding:8px;border-bottom:1px solid #edf2f7;'><strong>City:</strong></td><td style='padding:8px;border-bottom:1px solid #edf2f7;'>" . e($old['city']) . "</td></tr>
              </table>
            </div>";
            @uthenga_send_mail(SUPPORT_EMAIL, $adminSubject, $adminHtml);

            redirect(BASE_URL . 'login.php?vendor_registered=1');
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
  <title>Vendor Registration | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .auth-grid {
      display: grid;
      grid-template-columns: 1.1fr .9fr;
      gap: 1rem;
      align-items: stretch;
    }
    .pw-wrapper { position: relative; }
    .pw-toggle {
      position: absolute; right: 0.75rem; top: 50%;
      transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      color: var(--clr-text-soft, #9ca3af); padding: 0.25rem; line-height: 1;
    }
    .pw-wrapper .form-control { padding-right: 2.5rem; }
    @media (max-width: 900px) {
      .auth-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<section class="section" style="padding:2.5rem 0 4rem;">
  <div class="container" style="max-width:1100px;">
    <div class="auth-grid">
      <div class="card" style="padding:2rem;">
        <div class="section-label">Vendor Onboarding</div>
        <h1 style="margin:0.5rem 0 1rem;">Register your business on Uthenga</h1>
        <p class="text-muted" style="max-width:560px;">Create a vendor account, submit your business details, and wait for approval before you can access the vendor dashboard.</p>

        <?php if ($errors): ?>
          <div class="alert alert-error" style="margin-top:1rem;">
            <?php foreach ($errors as $err): ?>
              <div>• <?= e($err) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="" style="margin-top:1.25rem;display:grid;gap:1rem;" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= e($old['name'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= e($old['email'] ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone" class="form-control" value="<?= e($old['phone'] ?? '') ?>" placeholder="+265 999 123 456" required>
          </div>

          <div class="form-group">
            <label class="form-label">Business Name</label>
            <input type="text" name="business_name" class="form-control" value="<?= e($old['business_name'] ?? '') ?>" required>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="form-group">
              <label class="form-label">Category</label>
              <select name="category" class="form-control" required>
                <option value="">Choose category</option>
                <?php foreach ($categories as $category): ?>
                  <option value="<?= e($category) ?>" <?= (($old['category'] ?? '') === $category) ? 'selected' : '' ?>><?= e($category) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">City</label>
              <input type="text" name="city" class="form-control" value="<?= e($old['city'] ?? '') ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Business Address</label>
            <input type="text" name="address" class="form-control" value="<?= e($old['address'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Business Description</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Tell customers what you offer."><?= e($old['description'] ?? '') ?></textarea>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="form-group">
              <label class="form-label">Password</label>
              <div class="pw-wrapper">
                <input type="password" name="password" id="password" class="form-control" required minlength="<?= MIN_PASSWORD_LEN ?>">
                <button type="button" class="pw-toggle" onclick="utPwToggle('password',this)" aria-label="Show or hide password">Show</button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Confirm Password</label>
              <div class="pw-wrapper">
                <input type="password" name="password2" id="password2" class="form-control" required minlength="<?= MIN_PASSWORD_LEN ?>">
                <button type="button" class="pw-toggle" onclick="utPwToggle('password2',this)" aria-label="Show or hide password">Show</button>
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-lg">Submit Vendor Application</button>
        </form>
      </div>

      <div class="card" style="padding:2rem;background:linear-gradient(135deg,rgba(14,165,233,.12),rgba(230,57,70,.10));">
        <div class="section-label">Verification</div>
        <h2 style="margin:0.5rem 0 1rem;">Pending until approved</h2>
        <p class="text-muted" style="margin-bottom:1rem;">Your account starts in pending state and can be approved by an Admin or Super Admin only.</p>
        <div class="simple-list">
          <div class="simple-list-item"><strong>1.</strong><span class="text-xs text-muted">Submit your business information</span></div>
          <div class="simple-list-item"><strong>2.</strong><span class="text-xs text-muted">Wait for verification</span></div>
          <div class="simple-list-item"><strong>3.</strong><span class="text-xs text-muted">Log in after approval</span></div>
          <div class="simple-list-item"><strong>4.</strong><span class="text-xs text-muted">Manage listings and bookings</span></div>
        </div>
        <div style="margin-top:1.5rem;display:grid;gap:0.75rem;">
          <a href="<?= BASE_URL ?>login.php" class="btn btn-secondary">Back to Sign In</a>
          <a href="<?= BASE_URL ?>vendor/pending.php" class="btn btn-ghost">Pending page example</a>
        </div>
      </div>
    </div>
  </div>
</section>
<script>
function utPwToggle(inputId, btn) {
  var inp = document.getElementById(inputId);
  if (!inp) return;
  inp.type = inp.type === 'text' ? 'password' : 'text';
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
