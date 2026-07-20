<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/restoration_helpers.php';

requireApprovedVendor();
$coupons = dbQuery('SELECT * FROM coupons ORDER BY created_at DESC');
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section" style="padding-top:3rem;"><div class="container">
  <div class="card" style="padding:1.5rem;"><h1>Promotions</h1><div class="simple-list" style="margin-top:1rem;">
    <?php if (empty($coupons)): ?><div class="text-muted">No coupons available.</div><?php else: foreach ($coupons as $coupon): ?><div class="simple-list-item"><strong><?= e($coupon['code']) ?></strong><span class="text-xs text-muted"><?= e($coupon['discount_type']) ?> · <?= e($coupon['value']) ?></span></div><?php endforeach; endif; ?>
  </div></div>
</div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
