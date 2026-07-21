<?php
/**
 * Uthenga — Referral Program
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/auth_check.php';

requireLogin();

$userId = $_SESSION['user_id'];
$success = '';

// 1. Fetch or generate user's referral code
$refRow = dbQueryOne('SELECT * FROM referral_codes WHERE user_id = ?', [$userId]);
if (!$refRow) {
    // Generate a unique 8-character referral code
    $code = 'UTH' . strtoupper(substr(md5(uniqid()), 0, 5));
    $refId = generateId('REF');
    
    dbExecute("
        INSERT INTO referral_codes (id, user_id, code, reward_type, reward_value, uses_count, is_active, created_at)
        VALUES (?, ?, ?, 'loyalty_points', 500.00, 0, 1, NOW())
    ", [$refId, $userId, $code]);

    dbExecute("UPDATE users SET referral_code = ? WHERE id = ?", [$code, $userId]);
    $refRow = dbQueryOne('SELECT * FROM referral_codes WHERE user_id = ?', [$userId]);
}

// 2. Fetch successful referrals list
$referrals = dbQuery("
    SELECT u.name AS referred_name, ru.created_at, ru.referrer_rewarded
    FROM referral_uses ru
    JOIN users u ON u.id = ru.referred_user_id
    WHERE ru.referral_code_id = ?
    ORDER BY ru.created_at DESC
", [$refRow['id']]);

$totalEarnedPoints = count(array_filter($referrals, fn($r) => $r['referrer_rewarded'])) * 500;

$pageTitle = 'Referral Program';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Referral Program | <?= APP_NAME ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    .ref-wrap { max-width: 800px; margin: 3rem auto; }
    .ref-box { background: linear-gradient(135deg, #1e293b, #0f172a); border: 1px solid rgba(56,189,248,.2); border-radius: var(--radius-lg); padding: 2.5rem; text-align: center; }
    .ref-code { font-family: monospace; font-size: 2rem; font-weight: 800; color: var(--clr-cyan, #38bdf8); background: rgba(56,189,248,0.1); padding: 0.5rem 1.5rem; border-radius: var(--radius-sm); border: 1px dashed rgba(56,189,248,0.4); display: inline-block; margin: 1rem 0; cursor: pointer; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<main class="container ref-wrap">
  <div style="margin-bottom: 2rem; text-align:center;">
    <h1 style="font-size:2.2rem; font-weight:800; margin-bottom:0.5rem;"><?= uthenga_public_icon_svg('sparkles') ?> Invite Your Friends</h1>
    <p class="text-muted">Share the love of travel and earn loyalty points together.</p>
  </div>

  <div class="ref-box">
    <div style="font-size: 3.5rem; margin-bottom: 0.5rem;"><?= uthenga_public_icon_svg('wallet') ?></div>
    <h2 style="color: #fff; font-weight:800; font-size:1.5rem;">Your Referral Code</h2>
    <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-top:0.25rem;">Share this code or link with friends. You both get 500 loyalty points when they sign up!</p>
    
    <div class="ref-code" onclick="copyReferralLink()" title="Click to copy link">
      <?= e($refRow['code']) ?>
    </div>
    
    <div style="margin-top:0.5rem;">
      <button onclick="copyReferralLink()" class="btn btn-cyan btn-sm">Copy Referral Link</button>
    </div>
    
    <div id="copy-toast" style="color:#10b981; font-size:0.85rem; margin-top:0.75rem; font-weight:600; opacity:0; transition:opacity 0.3s;">
      Link copied to clipboard!
    </div>
  </div>

  <!-- Referral Performance Stats -->
  <div class="grid grid-cols-3 gap-2" style="margin: 2rem 0;">
    <div class="stat-card">
      <div style="font-size:1.8rem;font-weight:800;color:var(--clr-cyan);"><?= count($referrals) ?></div>
      <div class="text-xs text-muted">Total Invited Friends</div>
    </div>
    <div class="stat-card">
      <div style="font-size:1.8rem;font-weight:800;color:#10b981;"><?= number_format($totalEarnedPoints) ?></div>
      <div class="text-xs text-muted">Loyalty Points Earned</div>
    </div>
    <div class="stat-card">
      <div style="font-size:1.8rem;font-weight:800;color:#f59e0b;">500 pts</div>
      <div class="text-xs text-muted">Bonus per Sign-up</div>
    </div>
  </div>

  <!-- Friends List -->
  <div class="glass-panel" style="padding: 1.75rem;">
    <h3 style="font-size:1.15rem; font-weight:700; margin-bottom: 1.25rem;">Invited Friends</h3>
    <?php if (empty($referrals)): ?>
      <p class="text-muted text-sm" style="text-align:center; padding: 2rem 0;">You haven't referred any friends yet. Get started today!</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Friend Name</th>
              <th>Joined Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($referrals as $ref): ?>
              <tr>
                <td><strong><?= e($ref['referred_name']) ?></strong></td>
                <td class="text-xs text-muted"><?= e(date('M j, Y', strtotime($ref['created_at']))) ?></td>
                <td>
                  <?php if ($ref['referrer_rewarded']): ?>
                    <span style="color:#10b981;font-weight:600;"><?= uthenga_public_icon_svg('check') ?> +500 points rewarded</span>
                  <?php else: ?>
                    <span class="text-muted text-xs">Pending Reward</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</main>

<script>
function copyReferralLink() {
  const link = "<?= BASE_URL ?>register.php?ref=<?= e($refRow['code']) ?>";
  navigator.clipboard.writeText(link).then(() => {
    const toast = document.getElementById('copy-toast');
    toast.style.opacity = '1';
    setTimeout(() => toast.style.opacity = '0', 2500);
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
