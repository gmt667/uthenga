<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/restoration_helpers.php';

requireApprovedVendor();

$vendorId = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    try {
        $action = (string)($_POST['action'] ?? 'create');
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $image = trim((string)($_POST['image'] ?? ''));
        $listingType = (string)($_POST['listing_type'] ?? 'event');
        $price = (float)($_POST['price'] ?? 0);
        $meta = json_decode(trim((string)($_POST['meta_json'] ?? '{}')), true);
        if (!is_array($meta)) {
            $meta = [];
        }

        if ($action === 'delete') {
            $listingId = trim((string)($_POST['listing_id'] ?? ''));
            dbExecute('DELETE FROM listings WHERE id = ? AND vendor_id = ?', [$listingId, $vendorId]);
            $message = 'Listing deleted.';
        } else {
            if ($title === '' || $description === '' || $location === '' || $image === '') {
                throw new RuntimeException('Title, description, location, and image are required.');
            }
            $listingId = trim((string)($_POST['listing_id'] ?? ''));
            if ($listingId === '') {
                $listingId = generateId('LST');
                dbExecute(
                    'INSERT INTO listings (id, listing_type, title, description, location, image, gallery, vendor_id, vendor_name, rating, featured, is_active, meta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?)',
                    [
                        $listingId,
                        $listingType === 'property' ? 'accommodation' : $listingType,
                        $title,
                        $description,
                        $location,
                        $image,
                        json_encode([]),
                        $vendorId,
                        $_SESSION['user_name'],
                        json_encode(array_merge($meta, ['price' => $price]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]
                );
                $message = 'Listing created.';
            } else {
                dbExecute(
                    'UPDATE listings SET listing_type = ?, title = ?, description = ?, location = ?, image = ?, meta = ? WHERE id = ? AND vendor_id = ?',
                    [
                        $listingType === 'property' ? 'accommodation' : $listingType,
                        $title,
                        $description,
                        $location,
                        $image,
                        json_encode(array_merge($meta, ['price' => $price]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        $listingId,
                        $vendorId,
                    ]
                );
                $message = 'Listing updated.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$listings = dbQuery('SELECT * FROM listings WHERE vendor_id = ? ORDER BY created_at DESC', [$vendorId]);
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section" style="padding-top:3rem;">
  <div class="container">
    <div class="section-header">
      <div>
        <div class="section-label">Vendor CRUD</div>
        <h1>Business Listings</h1>
      </div>
    </div>
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="grid grid-cols-2 gap-3">
      <div class="card" style="padding:1.5rem;">
        <h3>Create or update listing</h3>
        <form method="post" style="display:grid;gap:1rem;margin-top:1rem;">
          <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="listing_id" id="listing_id">
          <input type="hidden" name="action" id="listing_action" value="create">
          <div class="grid grid-cols-2 gap-2">
            <input class="form-control" name="title" id="title" placeholder="Title">
            <select class="form-control" name="listing_type" id="listing_type">
              <option value="event">Event</option>
              <option value="property">Stay</option>
              <option value="tour">Tour</option>
              <option value="transport">Transport</option>
            </select>
          </div>
          <input class="form-control" name="location" id="location" placeholder="Location">
          <input class="form-control" name="image" id="image" placeholder="Image URL">
          <input class="form-control" name="price" id="price" placeholder="Base price">
          <textarea class="form-control" name="description" id="description" rows="4" placeholder="Description"></textarea>
          <textarea class="form-control" name="meta_json" id="meta_json" rows="4" placeholder='{"key":"value"}'></textarea>
          <button type="submit" class="btn btn-primary">Save Listing</button>
        </form>
      </div>
      <div class="card" style="padding:1.5rem;">
        <h3>Your listings</h3>
        <div class="simple-list" style="margin-top:1rem;">
          <?php if (empty($listings)): ?>
            <div class="text-muted">No listings yet.</div>
          <?php else: foreach ($listings as $listing): ?>
            <div class="simple-list-item">
              <div>
                <strong><?= e($listing['title']) ?></strong>
                <div class="text-xs text-muted"><?= e($listing['listing_type']) ?> · <?= e($listing['location']) ?></div>
              </div>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <button type="button" class="btn btn-sm btn-secondary" onclick='vendorFillListing(<?= json_encode($listing, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) ?>)'>Edit</button>
                <form method="post" style="margin:0;" onsubmit="return confirm('Delete this listing?');">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="listing_id" value="<?= e($listing['id']) ?>">
                  <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                </form>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<script>
function vendorFillListing(row) {
  document.getElementById('listing_action').value = 'update';
  document.getElementById('listing_id').value = row.id || '';
  document.getElementById('title').value = row.title || '';
  document.getElementById('listing_type').value = row.listing_type === 'accommodation' ? 'property' : (row.listing_type || 'event');
  document.getElementById('location').value = row.location || '';
  document.getElementById('image').value = row.image || '';
  document.getElementById('description').value = row.description || '';
  document.getElementById('price').value = row.price_amount || '';
  document.getElementById('meta_json').value = row.meta || '{}';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
