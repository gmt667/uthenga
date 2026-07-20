<?php
/**
 * Uthenga - Customer Favorites Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';

requireCustomer();

$pageTitle = 'My Saved Wishlist';
$activeNav = 'wishlist';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = dbQueryOne('SELECT * FROM users WHERE id = ?', [$userId]);
$listings = marketplace_fetch_favorites($userId);

function renderStars($rating) {
    $rating = (float) $rating;
    $full = (int) floor($rating);
    $half = (($rating - $full) >= 0.5) ? 1 : 0;
    return str_repeat('â˜…', $full) . str_repeat('Â½', $half) . str_repeat('â˜†', 5 - $full - $half);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= e($_SESSION['csrf_token'] ?? '') ?>">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <style>
    .wishlist-grid { margin-top: 1.5rem; }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="container" style="padding-top:2.5rem;padding-bottom:4rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title">â¤ï¸ My Wishlist</h1>
      <p class="text-muted">Explore and book the items you saved for later.</p>
    </div>
  </div>

  <?php if (empty($listings)): ?>
    <div class="glass-panel animate-in text-center" style="padding:4rem 2rem; text-align:center; margin-top:1rem;">
      <div style="font-size:3.5rem;margin-bottom:1rem;">â¤ï¸</div>
      <h3>Your wishlist is empty</h3>
      <p class="text-muted" style="margin:0.5rem 0 1.5rem;">Explore the marketplace and click "Save to Favorites" to keep track of your favorites.</p>
      <a href="<?= BASE_URL ?>index.php" class="btn btn-primary btn-lg">Explore Marketplace</a>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-4 gap-3 wishlist-grid">
      <?php foreach ($listings as $listing): ?>
        <div class="listing-card-wrap" id="wish-card-<?= e($listing['type'] . '-' . $listing['id']) ?>">
          <div class="card">
            <div class="card-img-wrap">
              <img src="<?= e($listing['image']) ?>" alt="<?= e($listing['title']) ?>" class="card-img" loading="lazy">
              <span class="card-badge <?= e($listing['badge_class']) ?>"><?= e($listing['type_label']) ?></span>
              <button
                class="btn-remove-wishlist"
                data-id="<?= e($listing['id']) ?>"
                data-type="<?= e($listing['type']) ?>"
                style="position:absolute;top:0.75rem;right:0.75rem;background:rgba(0,0,0,0.6);color:#ef4444;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem;"
                title="Remove from Wishlist">âœ•</button>
            </div>
            <div class="card-body">
              <div class="card-title"><?= e($listing['title']) ?></div>
              <div class="card-loc">ðŸ“ <?= e($listing['location']) ?></div>
              <div class="flex items-center gap-1" style="margin-bottom:0.75rem;">
                <span class="stars"><?= renderStars(isset($listing['rating']) ? $listing['rating'] : 0) ?></span>
                <span class="text-xs text-muted"><?= e(isset($listing['rating']) ? $listing['rating'] : 0) ?></span>
              </div>
              <div class="card-price"><?= e($listing['price_label']) ?></div>
            </div>
            <div class="card-footer">
              <a href="<?= e($listing['detail_url']) ?>" class="btn btn-sm btn-secondary" style="width:100%;text-align:center;">View Details</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.btn-remove-wishlist').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const itemId = btn.dataset.id;
      const itemType = btn.dataset.type;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

      const formData = new FormData();
      formData.append('action', 'toggle_wishlist');
      formData.append('listing_id', itemId);
      formData.append('listing_type', itemType);
      if (csrfToken) formData.append('csrf_token', csrfToken);

      try {
        const res = await fetch('<?= BASE_URL ?>request_api.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success && !data.added) {
          const card = document.getElementById('wish-card-' + itemType + '-' + itemId);
          if (card) {
            card.remove();
            if (document.querySelectorAll('.listing-card-wrap').length === 0) {
              location.reload();
            }
          }
        } else {
          alert('Error removing item from wishlist.');
        }
      } catch (err) {
        console.error(err);
        alert('Network error.');
      }
    });
  });
});
</script>
</body>
</html>
