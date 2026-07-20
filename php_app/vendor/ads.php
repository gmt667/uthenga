<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/restoration_helpers.php';

requireApprovedVendor();
$ads = getActiveAds('banner', 10);
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section" style="padding-top:3rem;"><div class="container">
  <div class="card" style="padding:1.5rem;"><h1>My Ads</h1><div class="simple-list" style="margin-top:1rem;">
    <?php if (empty($ads)): ?><div class="text-muted">No active ads found.</div><?php else: foreach ($ads as $ad): ?><div class="simple-list-item"><strong><?= e($ad['title'] ?? 'Advertisement') ?></strong><span class="text-xs text-muted"><?= e($ad['position'] ?? '') ?></span></div><?php endforeach; endif; ?>
  </div></div>
</div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
