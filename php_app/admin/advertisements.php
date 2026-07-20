<?php
/**
 * Uthenga — Admin Advertisements Overview
 */
$pageTitle = 'Advertisements';
$activeNav = 'admin-advertisements';

require_once __DIR__ . '/includes/admin_header.php';

$hasAdvertisements = uthenga_table_exists('advertisements');
$ads = $hasAdvertisements ? dbQuery("SELECT * FROM advertisements ORDER BY created_at DESC LIMIT 20") : [];
$activeAds = $hasAdvertisements ? dbCount("SELECT COUNT(*) FROM advertisements WHERE is_active = 1") : 0;
?>

<div class="page-header">
  <div>
    <h1 class="page-title">📣 Advertisements</h1>
    <p class="text-muted">Manage homepage ads, promoted placements, and campaign visibility.</p>
  </div>
</div>

<div class="grid grid-cols-2 gap-2" style="margin-bottom:2rem;">
  <div class="stat-card"><div class="stat-icon stat-icon-blue">📣</div><div><div class="stat-value"><?= number_format(count($ads)) ?></div><div class="stat-label">All Ads</div></div></div>
  <div class="stat-card"><div class="stat-icon stat-icon-green">✅</div><div><div class="stat-value"><?= number_format($activeAds) ?></div><div class="stat-label">Active Ads</div></div></div>
</div>

<div class="glass-panel" style="padding:1.25rem;">
  <div class="table-responsive">
    <table class="admin-table">
      <thead>
        <tr><th>Title</th><th>Type</th><th>Dates</th><th>Clicks</th><th>Impressions</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php if (empty($ads)): ?>
          <tr><td colspan="6" style="text-align:center;padding:1.5rem;color:var(--clr-text-muted);">No advertisements found.</td></tr>
        <?php else: ?>
          <?php foreach ($ads as $ad): ?>
            <tr>
              <td><?= e($ad['title'] ?? '') ?></td>
              <td class="text-xs"><?= e($ad['ad_type'] ?? '') ?></td>
              <td class="text-xs text-muted"><?= e($ad['start_date'] ?? '') ?> to <?= e($ad['end_date'] ?? '') ?></td>
              <td><?= number_format((int)($ad['clicks'] ?? 0)) ?></td>
              <td><?= number_format((int)($ad['impressions'] ?? 0)) ?></td>
              <td><span class="badge <?= !empty($ad['is_active']) ? 'badge-approved' : 'badge-cancelled' ?>"><?= !empty($ad['is_active']) ? 'Active' : 'Inactive' ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
