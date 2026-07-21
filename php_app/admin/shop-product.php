<?php
/**
 * Uthenga - Shop Product Detail
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/shop_helpers.php';

$pageTitle = 'Shop Product';
$activeNav = 'admin-shop';
require_once __DIR__ . '/includes/admin_header.php';

$productId = (int) ($_GET['id'] ?? $_POST['product_id'] ?? 0);
$product = $productId > 0 ? dbQueryOne(
    "SELECT p.*, c.name AS category_name
     FROM shop_products p
     LEFT JOIN shop_categories c ON c.id = p.category_id
     WHERE p.id = ? LIMIT 1",
    [$productId]
) : null;

if (!$product) {
    echo '<div class="container dashboard-content-frame" style="padding-top:2rem;padding-bottom:3rem;"><div class="glass-panel" style="padding:1.25rem;"><h2>Product not found</h2><p class="text-muted">The selected product could not be loaded.</p></div></div>';
    require_once __DIR__ . '/includes/admin_footer.php';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = (string) ($_POST['action'] ?? 'save');
    try {
        if ($action === 'save') {
            dbExecute(
                'UPDATE shop_products SET category_id = ?, sku = ?, name = ?, short_description = ?, description = ?, brand = ?, unit_label = ?, price = ?, compare_at_price = ?, cost_price = ?, stock_quantity = ?, low_stock_threshold = ?, primary_image_url = ?, secondary_image_url = ?, is_featured = ?, is_new_arrival = ?, is_best_seller = ?, is_promotion = ?, promotion_label = ?, requires_age_verification = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [
                    (int) ($_POST['category_id'] ?? 0) ?: null,
                    trim((string) ($_POST['sku'] ?? '')),
                    trim((string) ($_POST['name'] ?? '')),
                    trim((string) ($_POST['short_description'] ?? '')) ?: null,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    trim((string) ($_POST['brand'] ?? '')) ?: null,
                    trim((string) ($_POST['unit_label'] ?? '')) ?: null,
                    (float) ($_POST['price'] ?? 0),
                    trim((string) ($_POST['compare_at_price'] ?? '')) !== '' ? (float) $_POST['compare_at_price'] : null,
                    trim((string) ($_POST['cost_price'] ?? '')) !== '' ? (float) $_POST['cost_price'] : null,
                    max(0, (int) ($_POST['stock_quantity'] ?? 0)),
                    max(0, (int) ($_POST['low_stock_threshold'] ?? 5)),
                    trim((string) ($_POST['primary_image_url'] ?? '')) ?: null,
                    trim((string) ($_POST['secondary_image_url'] ?? '')) ?: null,
                    !empty($_POST['is_featured']) ? 1 : 0,
                    !empty($_POST['is_new_arrival']) ? 1 : 0,
                    !empty($_POST['is_best_seller']) ? 1 : 0,
                    !empty($_POST['is_promotion']) ? 1 : 0,
                    trim((string) ($_POST['promotion_label'] ?? '')) ?: null,
                    !empty($_POST['requires_age_verification']) ? 1 : 0,
                    (string) ($_POST['status'] ?? 'active'),
                    $productId,
                ]
            );

            if (!empty($_POST['new_image_url'])) {
                dbExecute(
                    'INSERT INTO shop_product_images (product_id, image_url, alt_text, sort_order, is_primary) VALUES (?, ?, ?, ?, ?)',
                    [$productId, trim((string) $_POST['new_image_url']), trim((string) ($_POST['name'] ?? $product['name'])), 99, 0]
                );
            }
            $message = 'Product updated successfully.';
            $product = dbQueryOne(
                "SELECT p.*, c.name AS category_name
                 FROM shop_products p
                 LEFT JOIN shop_categories c ON c.id = p.category_id
                 WHERE p.id = ? LIMIT 1",
                [$productId]
            ) ?: $product;
        } elseif ($action === 'set_primary_image') {
            $imageId = (int) ($_POST['image_id'] ?? 0);
            dbExecute('UPDATE shop_product_images SET is_primary = 0 WHERE product_id = ?', [$productId]);
            dbExecute('UPDATE shop_product_images SET is_primary = 1 WHERE id = ? AND product_id = ?', [$imageId, $productId]);
            dbExecute(
                'UPDATE shop_products SET primary_image_url = (SELECT image_url FROM shop_product_images WHERE id = ? AND product_id = ? LIMIT 1), updated_at = NOW() WHERE id = ?',
                [$imageId, $productId, $productId]
            );
            $message = 'Primary image updated.';
        } elseif ($action === 'delete_image') {
            $imageId = (int) ($_POST['image_id'] ?? 0);
            dbExecute('DELETE FROM shop_product_images WHERE id = ? AND product_id = ?', [$imageId, $productId]);
            $message = 'Image removed.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$categories = uthenga_shop_category_tree();
$images = uthenga_shop_product_image_urls($product);
$imageRows = uthenga_table_exists('shop_product_images')
    ? dbQuery('SELECT * FROM shop_product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC', [$productId])
    : [];
?>
<div class="container dashboard-content-frame" style="padding-top:2rem;padding-bottom:3rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('cart') ?><span>Edit Product</span></h1>
      <p class="text-muted">Adjust catalog details, inventory, promotions, and gallery images.</p>
    </div>
    <div class="dashboard-head-meta">
      <a href="<?= BASE_URL ?>admin/shop.php" class="btn btn-secondary btn-sm">Back to Shop</a>
      <a href="<?= BASE_URL ?>shop.php?product=<?= urlencode((string) $product['slug']) ?>" class="btn btn-primary btn-sm">View Public Page</a>
    </div>
  </div>

  <?php if ($message !== '' || $error !== ''): ?>
    <div class="glass-panel" style="padding:1rem;margin-bottom:1rem;border-left:4px solid <?= $error !== '' ? 'var(--clr-red)' : 'var(--clr-green)' ?>;">
      <strong><?= e($error !== '' ? $error : $message) ?></strong>
    </div>
  <?php endif; ?>

  <div class="shop-card">
    <form method="post" class="grid" style="gap:1rem;">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
      <input type="hidden" name="action" value="save">
      <div class="shop-form-grid">
        <label class="form-group"><span class="form-label">Name</span><input class="form-control" type="text" name="name" value="<?= e($product['name']) ?>" required></label>
        <label class="form-group"><span class="form-label">SKU</span><input class="form-control" type="text" name="sku" value="<?= e($product['sku']) ?>" required></label>
        <label class="form-group"><span class="form-label">Category</span><select class="form-control" name="category_id"><option value="0">Uncategorized</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $product['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
        <label class="form-group"><span class="form-label">Brand</span><input class="form-control" type="text" name="brand" value="<?= e($product['brand'] ?? '') ?>"></label>
        <label class="form-group"><span class="form-label">Price</span><input class="form-control" type="number" step="0.01" name="price" value="<?= e((string) $product['price']) ?>"></label>
        <label class="form-group"><span class="form-label">Compare At Price</span><input class="form-control" type="number" step="0.01" name="compare_at_price" value="<?= e((string) ($product['compare_at_price'] ?? '')) ?>"></label>
        <label class="form-group"><span class="form-label">Cost Price</span><input class="form-control" type="number" step="0.01" name="cost_price" value="<?= e((string) ($product['cost_price'] ?? '')) ?>"></label>
        <label class="form-group"><span class="form-label">Stock Quantity</span><input class="form-control" type="number" name="stock_quantity" value="<?= (int) $product['stock_quantity'] ?>"></label>
        <label class="form-group"><span class="form-label">Low Stock Threshold</span><input class="form-control" type="number" name="low_stock_threshold" value="<?= (int) $product['low_stock_threshold'] ?>"></label>
        <label class="form-group"><span class="form-label">Primary Image</span><input class="form-control" type="url" name="primary_image_url" value="<?= e($product['primary_image_url'] ?? '') ?>"></label>
        <label class="form-group"><span class="form-label">Secondary Image</span><input class="form-control" type="url" name="secondary_image_url" value="<?= e($product['secondary_image_url'] ?? '') ?>"></label>
        <label class="form-group"><span class="form-label">Unit Label</span><input class="form-control" type="text" name="unit_label" value="<?= e($product['unit_label'] ?? '') ?>"></label>
        <label class="form-group"><span class="form-label">Promotion Label</span><input class="form-control" type="text" name="promotion_label" value="<?= e($product['promotion_label'] ?? '') ?>"></label>
        <label class="form-group"><span class="form-label">Short Description</span><input class="form-control" type="text" name="short_description" value="<?= e($product['short_description'] ?? '') ?>"></label>
        <label class="form-group"><span class="form-label">Status</span><select class="form-control" name="status"><option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="draft" <?= $product['status'] === 'draft' ? 'selected' : '' ?>>Draft</option><option value="archived" <?= $product['status'] === 'archived' ? 'selected' : '' ?>>Archived</option><option value="out_of_stock" <?= $product['status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option></select></label>
        <label class="form-group" style="grid-column:1/-1;"><span class="form-label">Description</span><textarea class="form-control" name="description" rows="4"><?= e($product['description'] ?? '') ?></textarea></label>
      </div>
      <div class="shop-table-actions">
        <label><input type="checkbox" name="is_featured" <?= !empty($product['is_featured']) ? 'checked' : '' ?>> Featured</label>
        <label><input type="checkbox" name="is_new_arrival" <?= !empty($product['is_new_arrival']) ? 'checked' : '' ?>> New Arrival</label>
        <label><input type="checkbox" name="is_best_seller" <?= !empty($product['is_best_seller']) ? 'checked' : '' ?>> Best Seller</label>
        <label><input type="checkbox" name="is_promotion" <?= !empty($product['is_promotion']) ? 'checked' : '' ?>> Promotion</label>
        <label><input type="checkbox" name="requires_age_verification" <?= !empty($product['requires_age_verification']) ? 'checked' : '' ?>> Age Verification</label>
      </div>
      <div class="grid" style="grid-template-columns:minmax(0,1fr) auto;gap:.75rem;align-items:end;">
        <label class="form-group">
          <span class="form-label">Add New Gallery Image</span>
          <input class="form-control" type="url" name="new_image_url" placeholder="https://...">
        </label>
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </div>
    </form>
  </div>

  <div class="grid grid-cols-2 gap-3">
    <section class="shop-card">
      <div class="section-head"><div><h3>Gallery</h3><p class="text-xs text-muted">Primary image first, then the rest of the gallery.</p></div></div>
      <div class="gallery-thumbs" style="margin-top:0;">
        <?php if (empty($imageRows)): ?>
          <p class="text-muted">No gallery images yet.</p>
        <?php else: ?>
          <?php foreach ($imageRows as $img): ?>
            <div style="display:grid;gap:.35rem;">
              <img src="<?= e($img['image_url']) ?>" alt="<?= e($product['name']) ?>" style="width:120px;height:120px;object-fit:cover;border-radius:14px;">
              <div class="shop-table-actions" style="justify-content:flex-start;">
                <?php if (empty($img['is_primary'])): ?>
                  <form method="post" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                    <input type="hidden" name="action" value="set_primary_image">
                    <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                    <button class="btn btn-sm btn-secondary" type="submit">Set Primary</button>
                  </form>
                <?php endif; ?>
                <form method="post" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                  <input type="hidden" name="product_id" value="<?= (int) $productId ?>">
                  <input type="hidden" name="action" value="delete_image">
                  <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
                  <button class="btn btn-sm btn-secondary" type="submit">Remove</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
    <section class="shop-card">
      <div class="section-head"><div><h3>Inventory Snapshot</h3><p class="text-xs text-muted">Quick status for operations.</p></div></div>
      <div class="shop-admin-grid" style="grid-template-columns:1fr 1fr;">
        <div class="shop-admin-stat"><span>Stock</span><strong><?= (int) $product['stock_quantity'] ?></strong></div>
        <div class="shop-admin-stat"><span>Status</span><strong><?= e($product['status']) ?></strong></div>
        <div class="shop-admin-stat"><span>Category</span><strong><?= e($product['category_name'] ?? 'Uncategorized') ?></strong></div>
        <div class="shop-admin-stat"><span>Gallery</span><strong><?= count($imageRows) ?></strong></div>
      </div>
    </section>
  </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
