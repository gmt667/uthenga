<?php
/**
 * Uthenga — Newsletter Subscription Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$success = '';
$error = '';
$emailVal = '';
$nameVal = '';
$preferences = ['events', 'travel', 'transport', 'deals'];

$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    $user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($user) {
        $emailVal = $user['email'];
        $nameVal = $user['name'];
    }
}

// ─── Handle Unsubscribe ──────────────────────────────────────────────────────
if (isset($_GET['unsubscribe'])) {
    $token = trim($_GET['unsubscribe']);
    if ($token) {
        $sub = dbQueryOne('SELECT id FROM newsletter_subscribers WHERE token = ?', [$token]);
        if ($sub) {
            dbExecute("
                UPDATE newsletter_subscribers 
                SET status = 'unsubscribed', unsubscribed_at = NOW() 
                WHERE token = ?
            ", [$token]);
            $success = "You have been successfully unsubscribed from our newsletter. We're sorry to see you go!";
        } else {
            $error = "Invalid unsubscribe link.";
        }
    }
}

// ─── Handle POST Form ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf()) {
        $error = "Security validation failed. Please try again.";
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $name  = trim($_POST['full_name'] ?? '');
        $selectedPrefs = $_POST['prefs'] ?? [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if already subscribed
            $existing = dbQueryOne('SELECT * FROM newsletter_subscribers WHERE email = ?', [$email]);
            $token = $existing['token'] ?? bin2hex(random_bytes(16));
            $jsonPrefs = json_encode($selectedPrefs);

            if ($existing) {
                // Update existing subscriber
                dbExecute("
                    UPDATE newsletter_subscribers 
                    SET full_name = ?, preferences = ?, status = 'subscribed', unsubscribed_at = NULL 
                    WHERE email = ?
                ", [$name, $jsonPrefs, $email]);
                $success = "Your newsletter preferences have been updated successfully! 📧";
            } else {
                // Create new subscription
                dbExecute("
                    INSERT INTO newsletter_subscribers 
                    (user_id, email, full_name, preferences, status, token, created_at)
                    VALUES (?, ?, ?, ?, 'subscribed', ?, NOW())
                ", [$userId, $email, $name, $jsonPrefs, $token]);
                $success = "Thank you for subscribing to our newsletter! Stay tuned for travel deals. 🎉";
            }
        }
    }
}

$pageTitle = 'Newsletter Subscription';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Newsletter Subscription | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    .newsletter-card { max-width: 500px; margin: 4rem auto; padding: 2.5rem; background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: var(--radius-lg); }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<main class="container">
  <div class="newsletter-card">
    <div style="text-align: center; margin-bottom: 2rem;">
      <div style="font-size: 3rem; margin-bottom: 1rem;">📧</div>
      <h2 style="font-weight: 800;">Uthenga Newsletter</h2>
      <p class="text-muted" style="font-size: 0.875rem;">Get exclusive Malawi tourism deals, events updates, and transport guides.</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success" style="margin-bottom: 1.5rem;">✓ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error" style="margin-bottom: 1.5rem;">✕ <?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!isset($_GET['unsubscribe']) || $error): ?>
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

        <div class="form-group">
          <label class="form-label" for="full_name">Full Name</label>
          <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Your Name" value="<?= e($nameVal) ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" value="<?= e($emailVal) ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Interests &amp; Topics</label>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-top:0.5rem;">
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
              <input type="checkbox" name="prefs[]" value="events" checked>
              <span>Events Updates 🎫</span>
            </label>
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
              <input type="checkbox" name="prefs[]" value="travel" checked>
              <span>Travel &amp; Stays 🏨</span>
            </label>
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
              <input type="checkbox" name="prefs[]" value="transport" checked>
              <span>Transport Guides 🚌</span>
            </label>
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
              <input type="checkbox" name="prefs[]" value="deals" checked>
              <span>Exclusive Deals 🏷️</span>
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem; font-size: 1rem;">
          Subscribe / Update Settings
        </button>
      </form>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 1.5rem;">
      <a href="<?= BASE_URL ?>index.php" class="text-muted" style="font-size: 0.875rem;">← Return to Homepage</a>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
