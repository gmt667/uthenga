<?php
/**
 * Uthenga - Shop Management Console
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/shop_helpers.php';

$isSuperAdmin = currentRole() === ROLE_SUPER_ADMIN;
$pageTitle = $isSuperAdmin ? 'Global Shop Management' : 'Shop Management';
$activeNav = $isSuperAdmin ? 'admin-shop-global' : 'admin-shop';

require_once __DIR__ . '/includes/admin_header.php';

$flashMessage = '';
$flashError = '';

function shop_clean_csv(string $value): array {
    $parts = preg_split('/[\r\n,]+/', $value) ?: [];
    $parts = array_map('trim', $parts);
    return array_values(array_filter($parts, static fn($v) => $v !== ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_category') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Category name is required.');
            }
            $slug = uthenga_shop_unique_slug('shop_categories', uthenga_shop_slugify($name));
            dbExecute(
                'INSERT INTO shop_categories (name, slug, description, icon, image_url, sort_order, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $name,
                    $slug,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    trim((string) ($_POST['icon'] ?? '')) ?: null,
                    trim((string) ($_POST['image_url'] ?? '')) ?: null,
                    (int) ($_POST['sort_order'] ?? 0),
                    !empty($_POST['is_active']) ? 1 : 0,
                    $_SESSION['user_id'] ?? null,
                ]
            );
            $flashMessage = 'Category created.';
        } elseif ($action === 'create_product') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('Product name is required.');
            }
            $slug = uthenga_shop_unique_slug('shop_products', uthenga_shop_slugify($name));
            $gallery = shop_clean_csv((string) ($_POST['gallery_urls'] ?? ''));
            dbExecute(
                'INSERT INTO shop_products (category_id, supplier_id, warehouse_id, sku, name, slug, short_description, description, brand, unit_label, price, compare_at_price, cost_price, stock_quantity, low_stock_threshold, primary_image_url, secondary_image_url, is_featured, is_new_arrival, is_best_seller, is_promotion, promotion_label, requires_age_verification, status, meta, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    (int) ($_POST['category_id'] ?? 0) ?: null,
                    (int) ($_POST['supplier_id'] ?? 0) ?: null,
                    (int) ($_POST['warehouse_id'] ?? 0) ?: null,
                    trim((string) ($_POST['sku'] ?? '')) ?: 'SKU-' . strtoupper(bin2hex(random_bytes(3))),
                    $name,
                    $slug,
                    trim((string) ($_POST['short_description'] ?? '')) ?: null,
                    trim((string) ($_POST['description'] ?? '')) ?: null,
                    trim((string) ($_POST['brand'] ?? '')) ?: null,
                    trim((string) ($_POST['unit_label'] ?? '')) ?: null,
                    (float) ($_POST['price'] ?? 0),
                    trim((string) ($_POST['compare_at_price'] ?? '')) !== '' ? (float) $_POST['compare_at_price'] : null,
                    trim((string) ($_POST['cost_price'] ?? '')) !== '' ? (float) $_POST['cost_price'] : null,
                    (int) ($_POST['stock_quantity'] ?? 0),
                    (int) ($_POST['low_stock_threshold'] ?? 5),
                    trim((string) ($_POST['primary_image_url'] ?? '')) ?: null,
                    trim((string) ($_POST['secondary_image_url'] ?? '')) ?: null,
                    !empty($_POST['is_featured']) ? 1 : 0,
                    !empty($_POST['is_new_arrival']) ? 1 : 0,
                    !empty($_POST['is_best_seller']) ? 1 : 0,
                    !empty($_POST['is_promotion']) ? 1 : 0,
                    trim((string) ($_POST['promotion_label'] ?? '')) ?: null,
                    !empty($_POST['requires_age_verification']) ? 1 : 0,
                    (string) ($_POST['status'] ?? 'active'),
                    json_encode(['gallery' => $gallery], JSON_UNESCAPED_SLASHES),
                    $_SESSION['user_id'] ?? null,
                ]
            );
            $productId = (int) dbLastId();
            foreach ($gallery as $index => $imageUrl) {
                dbExecute(
                    'INSERT INTO shop_product_images (product_id, image_url, alt_text, sort_order, is_primary) VALUES (?, ?, ?, ?, ?)',
                    [$productId, $imageUrl, $name, $index + 1, $index === 0 ? 1 : 0]
                );
            }
            $flashMessage = 'Product created.';
        } elseif ($action === 'create_rider') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $phone = trim((string) ($_POST['phone_number'] ?? ''));
            if ($name === '' || $phone === '') {
                throw new RuntimeException('Rider name and phone number are required.');
            }
            dbExecute(
                'INSERT INTO delivery_riders (rider_code, name, phone_number, bike_registration, availability, status, current_location, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'RDR-' . strtoupper(bin2hex(random_bytes(4))),
                    $name,
                    $phone,
                    trim((string) ($_POST['bike_registration'] ?? '')) ?: null,
                    (string) ($_POST['availability'] ?? 'available'),
                    (string) ($_POST['status'] ?? 'active'),
                    trim((string) ($_POST['current_location'] ?? '')) ?: null,
                    trim((string) ($_POST['notes'] ?? '')) ?: null,
                ]
            );
            $flashMessage = 'Delivery rider added.';
        } elseif ($action === 'update_order') {
            $orderId = (int) ($_POST['order_id'] ?? 0);
            $status = (string) ($_POST['order_status'] ?? 'pending');
            $paymentStatus = (string) ($_POST['payment_status'] ?? 'pending');
            $riderId = (int) ($_POST['assigned_rider_id'] ?? 0) ?: null;
            $order = dbQueryOne('SELECT * FROM shop_orders WHERE id = ? LIMIT 1', [$orderId]);
            if (!$order) {
                throw new RuntimeException('Order not found.');
            }

            $updates = [
                'order_status = ?',
                'payment_status = ?',
                'assigned_rider_id = ?',
            ];
            $params = [$status, $paymentStatus, $riderId];

            if ($status === 'confirmed') {
                $updates[] = 'confirmed_at = COALESCE(confirmed_at, NOW())';
            } elseif ($status === 'preparing') {
                $updates[] = 'prepared_at = COALESCE(prepared_at, NOW())';
            } elseif ($status === 'assigned_to_rider' || $status === 'out_for_delivery') {
                $updates[] = 'dispatched_at = COALESCE(dispatched_at, NOW())';
                if ($riderId) {
                    dbExecute('UPDATE delivery_riders SET availability = ? WHERE id = ?', ['busy', $riderId]);
                }
            } elseif ($status === 'delivered') {
                $updates[] = 'delivered_at = COALESCE(delivered_at, NOW())';
                if ($riderId) {
                    dbExecute('UPDATE delivery_riders SET availability = ? WHERE id = ?', ['available', $riderId]);
                }
            } elseif ($status === 'cancelled') {
                $updates[] = 'cancelled_at = COALESCE(cancelled_at, NOW())';
            }

            dbExecute(
                'UPDATE shop_orders SET ' . implode(', ', $updates) . ' WHERE id = ?',
                array_merge($params, [$orderId])
            );

            if (uthenga_table_exists('shop_deliveries')) {
                $deliveryStatus = match ($status) {
                    'confirmed' => 'assigned',
                    'preparing' => 'assigned',
                    'assigned_to_rider' => 'assigned',
                    'out_for_delivery' => 'out_for_delivery',
                    'delivered' => 'delivered',
                    'cancelled' => 'cancelled',
                    default => 'assigned',
                };
                dbExecute(
                    'INSERT INTO shop_deliveries (order_id, rider_id, delivery_status, assigned_at, dispatched_at, delivered_at, completion_notes)
                     VALUES (?, ?, ?, NOW(), ?, ?, ?)
                     ON DUPLICATE KEY UPDATE rider_id = VALUES(rider_id), delivery_status = VALUES(delivery_status), dispatched_at = VALUES(dispatched_at), delivered_at = VALUES(delivered_at), updated_at = NOW()',
                    [
                        $orderId,
                        $riderId,
                        $deliveryStatus,
                        in_array($status, ['assigned_to_rider', 'out_for_delivery', 'delivered'], true) ? date('Y-m-d H:i:s') : null,
                        $status === 'delivered' ? date('Y-m-d H:i:s') : null,
                        $status === 'delivered' ? 'Delivery completed by admin.' : null,
                    ]
                );
            }

            if (!empty($order['user_id'])) {
                $notificationTitle = 'Order Updated';
                $notificationMessage = 'Your order ' . $order['order_number'] . ' status has been updated to ' . $status . '.';
                if ($status === 'confirmed') {
                    $notificationTitle = 'Order Confirmed';
                    $notificationMessage = 'Your order ' . $order['order_number'] . ' has been confirmed and is now being prepared.';
                } elseif ($status === 'preparing') {
                    $notificationTitle = 'Order Preparing';
                    $notificationMessage = 'Your order ' . $order['order_number'] . ' is now being prepared for dispatch.';
                } elseif ($status === 'assigned_to_rider' || $status === 'out_for_delivery') {
                    $notificationTitle = 'Order Dispatched';
                    $notificationMessage = 'Your order ' . $order['order_number'] . ' has been assigned to a rider and is on the way.';
                } elseif ($status === 'delivered') {
                    $notificationTitle = 'Order Delivered';
                    $notificationMessage = 'Your order ' . $order['order_number'] . ' has been delivered successfully.';
                } elseif ($status === 'cancelled') {
                    $notificationTitle = 'Order Cancelled';
                    $notificationMessage = 'Your order ' . $order['order_number'] . ' has been cancelled.';
                }
                uthenga_shop_notify_user((string) $order['user_id'], 'shop', $notificationTitle, $notificationMessage);
            }
            $flashMessage = 'Order updated.';
        } elseif ($action === 'adjust_stock') {
            dbExecute(
                'UPDATE shop_products SET stock_quantity = ?, status = ? WHERE id = ?',
                [
                    max(0, (int) ($_POST['stock_quantity'] ?? 0)),
                    (string) ($_POST['status'] ?? 'active'),
                    (int) ($_POST['product_id'] ?? 0),
                ]
            );
            $flashMessage = 'Stock updated.';
        } elseif ($action === 'save_settings' && $isSuperAdmin) {
            $fields = [
                'delivery_fee_mwk' => ['number', (float) ($_POST['delivery_fee_mwk'] ?? 0)],
                'free_delivery_threshold_mwk' => ['number', (float) ($_POST['free_delivery_threshold_mwk'] ?? 0)],
                'tax_rate_percent' => ['number', (float) ($_POST['tax_rate_percent'] ?? 0)],
                'cod_enabled' => ['boolean', !empty($_POST['cod_enabled']) ? 1 : 0],
                'bank_transfer_enabled' => ['boolean', !empty($_POST['bank_transfer_enabled']) ? 1 : 0],
                'tnm_mpamba_enabled' => ['boolean', !empty($_POST['tnm_mpamba_enabled']) ? 1 : 0],
                'airtel_money_enabled' => ['boolean', !empty($_POST['airtel_money_enabled']) ? 1 : 0],
                'paychangu_enabled' => ['boolean', !empty($_POST['paychangu_enabled']) ? 1 : 0],
                'shop_name' => ['string', trim((string) ($_POST['shop_name'] ?? 'Uthenga Shop'))],
                'shop_tagline' => ['string', trim((string) ($_POST['shop_tagline'] ?? ''))],
            ];
            foreach ($fields as $key => [$type, $value]) {
                dbExecute(
                    'INSERT INTO shop_settings (setting_key, setting_value, value_type, updated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_by = VALUES(updated_by), updated_at = NOW()',
                    [$key, (string) $value, $type, $_SESSION['user_id'] ?? null]
                );
            }
            $flashMessage = 'Shop settings saved.';
        }
    } catch (Throwable $e) {
        $flashError = $e->getMessage();
    }
}

$settings = uthenga_shop_settings();
$categories = uthenga_table_exists('shop_categories') ? dbQuery('SELECT * FROM shop_categories ORDER BY sort_order ASC, name ASC') : [];
$products = uthenga_table_exists('shop_products')
    ? dbQuery("SELECT p.*, c.name AS category_name FROM shop_products p LEFT JOIN shop_categories c ON c.id = p.category_id WHERE p.deleted_at IS NULL ORDER BY p.created_at DESC LIMIT 30")
    : [];
$orders = uthenga_table_exists('shop_orders')
    ? dbQuery("SELECT * FROM shop_orders ORDER BY placed_at DESC LIMIT 25")
    : [];
$riders = uthenga_shop_riders(false);
$riderNames = [];
foreach ($riders as $rider) {
    $riderNames[(int) ($rider['id'] ?? 0)] = (string) ($rider['name'] ?? '');
}
$metrics = [
    'products' => count($products),
    'categories' => count($categories),
    'orders' => count($orders),
    'revenue' => uthenga_table_exists('shop_orders') ? (dbQueryOne("SELECT COALESCE(SUM(total_amount),0) AS total FROM shop_orders WHERE LOWER(payment_status) IN ('paid','authorized','partially_paid')") ?: ['total' => 0]) : ['total' => 0],
    'low_stock' => uthenga_table_exists('shop_products') ? dbCount('SELECT COUNT(*) FROM shop_products WHERE stock_quantity <= low_stock_threshold') : 0,
    'riders' => count($riders),
];

$section = trim((string) ($_GET['section'] ?? 'overview'));
?>
<style>
  .shop-admin-grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:.9rem; margin-bottom:1rem; }
  .shop-admin-stat { padding:1rem; border:1px solid var(--clr-border); border-radius:18px; background:var(--clr-surface); }
  .shop-admin-stat span { display:block; color:var(--clr-text-muted); font-size:.74rem; text-transform:uppercase; letter-spacing:.06em; }
  .shop-admin-stat strong { display:block; margin-top:.2rem; font-size:1.2rem; }
  .shop-card { padding:1.25rem; border:1px solid var(--clr-border); border-radius:24px; background:var(--clr-surface); margin-bottom:1rem; }
  .shop-form-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:.9rem; }
  .shop-table-actions { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
  .shop-section-nav { display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem; }
  .shop-section-nav a { padding:.45rem .8rem; border:1px solid var(--clr-border); border-radius:999px; background:var(--clr-surface); color:var(--clr-text-soft); font-size:.82rem; font-weight:700; }
  .shop-section-nav a.active { background: var(--clr-accent); color: #fff; border-color: transparent; }
  @media (max-width: 960px) {
    .shop-admin-grid, .shop-form-grid { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 700px) {
    .shop-admin-grid, .shop-form-grid { grid-template-columns: 1fr; }
  }
</style>

<div class="container dashboard-content-frame" style="padding-top:2rem;padding-bottom:3rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('cart') ?><span><?= e($pageTitle) ?></span></h1>
      <p class="text-muted">Manage the shop catalog, deliveries, orders, and pricing settings.</p>
    </div>
    <div class="dashboard-head-meta">
      <a href="<?= BASE_URL ?>shop.php" class="btn btn-secondary btn-sm">View Shop</a>
      <a href="<?= BASE_URL ?>shop-orders.php" class="btn btn-secondary btn-sm">My Orders</a>
    </div>
  </div>

  <?php if ($flashMessage !== '' || $flashError !== ''): ?>
    <div class="glass-panel" style="padding:1rem;margin-bottom:1rem;border-left:4px solid <?= $flashError !== '' ? 'var(--clr-red)' : 'var(--clr-green)' ?>;">
      <strong><?= e($flashError !== '' ? $flashError : $flashMessage) ?></strong>
    </div>
  <?php endif; ?>

  <div class="shop-admin-grid">
    <div class="shop-admin-stat"><span>Products</span><strong><?= number_format((int) $metrics['products']) ?></strong></div>
    <div class="shop-admin-stat"><span>Orders</span><strong><?= number_format((int) $metrics['orders']) ?></strong></div>
    <div class="shop-admin-stat"><span>Revenue</span><strong><?= uthenga_shop_money((float) $metrics['revenue']) ?></strong></div>
    <div class="shop-admin-stat"><span>Low Stock</span><strong><?= number_format((int) $metrics['low_stock']) ?></strong></div>
  </div>

  <div class="shop-section-nav">
    <a href="#overview" class="<?= $section === 'overview' ? 'active' : '' ?>">Overview</a>
    <a href="#categories">Categories</a>
    <a href="#products">Products</a>
    <a href="#orders">Orders</a>
    <a href="#riders">Riders</a>
    <?php if ($isSuperAdmin): ?><a href="#settings">Settings</a><?php endif; ?>
  </div>

  <section class="shop-card" id="overview">
    <div class="section-head">
      <div>
        <h3>Shop Overview</h3>
        <p class="text-xs text-muted">Quick numbers from the live shop tables.</p>
      </div>
    </div>
    <div class="shop-admin-grid" style="margin-bottom:0;">
      <div class="shop-admin-stat"><span>Categories</span><strong><?= number_format((int) $metrics['categories']) ?></strong></div>
      <div class="shop-admin-stat"><span>Delivery Riders</span><strong><?= number_format((int) $metrics['riders']) ?></strong></div>
      <div class="shop-admin-stat"><span>Orders in Queue</span><strong><?= number_format(count(array_filter($orders, static fn($o) => in_array(strtolower((string) ($o['order_status'] ?? '')), ['pending','confirmed','preparing'], true)))) ?></strong></div>
      <div class="shop-admin-stat"><span>Shop Theme</span><strong><?= e($settings['shop_name']) ?></strong></div>
    </div>
  </section>

  <section class="shop-card" id="categories">
    <div class="section-head">
      <div>
        <h3>Categories</h3>
        <p class="text-xs text-muted">Manage product categories used across the storefront.</p>
      </div>
    </div>
    <form method="post" class="shop-form-grid" style="margin-bottom:1rem;">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="action" value="create_category">
      <label class="form-group">
        <span class="form-label">Category Name</span>
        <input type="text" name="name" class="form-control" placeholder="e.g. Groceries" required>
      </label>
      <label class="form-group">
        <span class="form-label">Sort Order</span>
        <input type="number" name="sort_order" class="form-control" value="0">
      </label>
      <label class="form-group">
        <span class="form-label">Description</span>
        <input type="text" name="description" class="form-control" placeholder="Short category summary">
      </label>
      <label class="form-group">
        <span class="form-label">Image URL</span>
        <input type="url" name="image_url" class="form-control" placeholder="https://...">
      </label>
      <label class="form-group">
        <span class="form-label">Icon</span>
        <input type="text" name="icon" class="form-control" placeholder="shop, cart, box">
      </label>
      <label class="form-group" style="display:flex;align-items:end;">
        <label style="display:flex;align-items:center;gap:.5rem;margin-top:1.2rem;">
          <input type="checkbox" name="is_active" checked> Active
        </label>
      </label>
      <div style="grid-column:1/-1;">
        <button type="submit" class="btn btn-primary">Create Category</button>
      </div>
    </form>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Name</th><th>Slug</th><th>Sort</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($categories as $category): ?>
            <tr>
              <td><?= e($category['name']) ?></td>
              <td><?= e($category['slug']) ?></td>
              <td><?= (int) $category['sort_order'] ?></td>
              <td><span class="badge <?= !empty($category['is_active']) ? 'badge-approved' : 'badge-cancelled' ?>"><?= !empty($category['is_active']) ? 'Active' : 'Inactive' ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="shop-card" id="products">
    <div class="section-head">
      <div>
        <h3>Products</h3>
        <p class="text-xs text-muted">Add products and manage stock, promotions, and gallery images.</p>
      </div>
    </div>
    <form method="post" class="grid" style="gap:1rem;margin-bottom:1rem;">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="action" value="create_product">
      <div class="shop-form-grid">
        <label class="form-group"><span class="form-label">Name</span><input type="text" name="name" class="form-control" required></label>
        <label class="form-group"><span class="form-label">SKU</span><input type="text" name="sku" class="form-control" placeholder="Optional"></label>
        <label class="form-group"><span class="form-label">Category</span><select name="category_id" class="form-control"><option value="0">Select</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
        <label class="form-group"><span class="form-label">Brand</span><input type="text" name="brand" class="form-control"></label>
        <label class="form-group"><span class="form-label">Price</span><input type="number" step="0.01" name="price" class="form-control" required></label>
        <label class="form-group"><span class="form-label">Compare At Price</span><input type="number" step="0.01" name="compare_at_price" class="form-control"></label>
        <label class="form-group"><span class="form-label">Cost Price</span><input type="number" step="0.01" name="cost_price" class="form-control"></label>
        <label class="form-group"><span class="form-label">Stock Quantity</span><input type="number" name="stock_quantity" class="form-control" value="0"></label>
        <label class="form-group"><span class="form-label">Low Stock Threshold</span><input type="number" name="low_stock_threshold" class="form-control" value="5"></label>
        <label class="form-group"><span class="form-label">Primary Image URL</span><input type="url" name="primary_image_url" class="form-control"></label>
        <label class="form-group"><span class="form-label">Secondary Image URL</span><input type="url" name="secondary_image_url" class="form-control"></label>
        <label class="form-group"><span class="form-label">Gallery URLs</span><textarea name="gallery_urls" class="form-control" rows="2" placeholder="One URL per line or comma-separated"></textarea></label>
        <label class="form-group"><span class="form-label">Short Description</span><input type="text" name="short_description" class="form-control"></label>
        <label class="form-group"><span class="form-label">Description</span><textarea name="description" class="form-control" rows="3"></textarea></label>
        <label class="form-group"><span class="form-label">Unit Label</span><input type="text" name="unit_label" class="form-control" placeholder="e.g. 6 pack, 25kg bag"></label>
        <label class="form-group"><span class="form-label">Promotion Label</span><input type="text" name="promotion_label" class="form-control" placeholder="Weekend offer"></label>
        <label class="form-group"><span class="form-label">Supplier</span><select name="supplier_id" class="form-control"><option value="0">Default</option><option value="1">Uthenga Direct Stock</option></select></label>
        <label class="form-group"><span class="form-label">Warehouse</span><select name="warehouse_id" class="form-control"><option value="0">Default</option><option value="1">Central Dispatch Store</option></select></label>
      </div>
      <div class="shop-table-actions">
        <label><input type="checkbox" name="is_featured"> Featured</label>
        <label><input type="checkbox" name="is_new_arrival"> New Arrival</label>
        <label><input type="checkbox" name="is_best_seller"> Best Seller</label>
        <label><input type="checkbox" name="is_promotion"> Promotion</label>
        <label><input type="checkbox" name="requires_age_verification"> Age Verification</label>
        <select name="status" class="form-control" style="max-width:200px;">
          <option value="active">Active</option>
          <option value="draft">Draft</option>
          <option value="archived">Archived</option>
          <option value="out_of_stock">Out of Stock</option>
        </select>
      </div>
      <div>
        <button type="submit" class="btn btn-primary">Create Product</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Quick Stock</th></tr></thead>
        <tbody>
          <?php foreach ($products as $product): ?>
            <tr>
              <td>
                <strong><a href="<?= BASE_URL ?>admin/shop-product.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></strong><br>
                <span class="text-xs text-muted"><?= e($product['sku']) ?></span>
              </td>
              <td><?= e($product['category_name'] ?? 'Uncategorized') ?></td>
              <td><?= uthenga_shop_money((float) $product['price']) ?></td>
              <td><?= (int) $product['stock_quantity'] ?></td>
              <td><span class="badge <?= uthenga_shop_status_badge((string) $product['status']) ?>"><?= e($product['status']) ?></span></td>
              <td>
                <form method="post" class="shop-table-actions" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                  <input type="hidden" name="action" value="adjust_stock">
                  <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                  <input type="number" name="stock_quantity" class="form-control" style="max-width:110px;" value="<?= (int) $product['stock_quantity'] ?>">
                  <select name="status" class="form-control" style="max-width:130px;">
                    <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="draft" <?= $product['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="archived" <?= $product['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                    <option value="out_of_stock" <?= $product['status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                  </select>
                  <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="shop-card" id="orders">
    <div class="section-head">
      <div>
        <h3>Orders</h3>
        <p class="text-xs text-muted">Assign riders and move orders through the shop workflow.</p>
      </div>
    </div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Rider</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td><?= e($order['order_number']) ?><br><span class="text-xs text-muted"><?= e($order['placed_at']) ?></span></td>
              <td><?= e($order['customer_name']) ?><br><span class="text-xs text-muted"><?= e($order['customer_phone']) ?></span></td>
              <td><?= uthenga_shop_money((float) $order['total_amount']) ?></td>
              <td><span class="badge <?= uthenga_shop_status_badge((string) $order['order_status']) ?>"><?= e($order['order_status']) ?></span><br><span class="badge <?= uthenga_shop_status_badge((string) $order['payment_status']) ?>"><?= e($order['payment_status']) ?></span></td>
              <td><?= e($riderNames[(int) ($order['assigned_rider_id'] ?? 0)] ?? 'Unassigned') ?></td>
              <td>
                <form method="post" class="shop-table-actions" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                  <input type="hidden" name="action" value="update_order">
                  <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                  <select name="order_status" class="form-control" style="max-width:150px;">
                    <?php foreach (['pending','confirmed','preparing','assigned_to_rider','out_for_delivery','delivered','cancelled'] as $status): ?>
                      <option value="<?= e($status) ?>" <?= $order['order_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="payment_status" class="form-control" style="max-width:130px;">
                    <?php foreach (['pending','authorized','paid','failed','refunded','partially_paid'] as $status): ?>
                      <option value="<?= e($status) ?>" <?= $order['payment_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="assigned_rider_id" class="form-control" style="max-width:160px;">
                    <option value="0">Assign Rider</option>
                    <?php foreach ($riders as $rider): ?>
                      <option value="<?= (int) $rider['id'] ?>" <?= (int) ($order['assigned_rider_id'] ?? 0) === (int) $rider['id'] ? 'selected' : '' ?>><?= e($rider['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-secondary btn-sm">Update</button>
                  <a href="<?= BASE_URL ?>admin/shop-order.php?id=<?= (int) $order['id'] ?>" class="btn btn-sm btn-secondary">Open</a>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="shop-card" id="riders">
    <div class="section-head">
      <div>
        <h3>Delivery Riders</h3>
        <p class="text-xs text-muted">Add third-party riders and keep delivery availability current.</p>
      </div>
    </div>
    <form method="post" class="shop-form-grid" style="margin-bottom:1rem;">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="action" value="create_rider">
      <label class="form-group"><span class="form-label">Name</span><input type="text" name="name" class="form-control" required></label>
      <label class="form-group"><span class="form-label">Phone Number</span><input type="text" name="phone_number" class="form-control" required></label>
      <label class="form-group"><span class="form-label">Bike Registration</span><input type="text" name="bike_registration" class="form-control"></label>
      <label class="form-group"><span class="form-label">Current Location</span><input type="text" name="current_location" class="form-control" placeholder="Blantyre"></label>
      <label class="form-group"><span class="form-label">Availability</span><select name="availability" class="form-control"><option value="available">Available</option><option value="busy">Busy</option><option value="offline">Offline</option><option value="inactive">Inactive</option></select></label>
      <label class="form-group"><span class="form-label">Status</span><select name="status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></label>
      <label class="form-group" style="grid-column:1/-1;"><span class="form-label">Notes</span><textarea name="notes" class="form-control" rows="2"></textarea></label>
      <div style="grid-column:1/-1;"><button type="submit" class="btn btn-primary">Add Rider</button></div>
    </form>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Name</th><th>Phone</th><th>Bike</th><th>Availability</th><th>History</th></tr></thead>
        <tbody>
          <?php foreach ($riders as $rider): ?>
            <tr>
              <td><?= e($rider['name']) ?></td>
              <td><?= e($rider['phone_number']) ?></td>
              <td><?= e($rider['bike_registration'] ?? 'N/A') ?></td>
              <td><span class="badge <?= uthenga_shop_status_badge((string) $rider['availability']) ?>"><?= e($rider['availability']) ?></span></td>
              <td><?= number_format((int) $rider['delivery_history_count']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($isSuperAdmin): ?>
    <section class="shop-card" id="settings">
      <div class="section-head">
        <div>
          <h3>Shop Settings</h3>
          <p class="text-xs text-muted">Configure delivery fees, taxes, and payment options.</p>
        </div>
      </div>
      <form method="post" class="shop-form-grid">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="action" value="save_settings">
        <label class="form-group"><span class="form-label">Shop Name</span><input type="text" name="shop_name" class="form-control" value="<?= e($settings['shop_name']) ?>"></label>
        <label class="form-group"><span class="form-label">Tagline</span><input type="text" name="shop_tagline" class="form-control" value="<?= e($settings['shop_tagline']) ?>"></label>
        <label class="form-group"><span class="form-label">Delivery Fee</span><input type="number" step="0.01" name="delivery_fee_mwk" class="form-control" value="<?= e((string) $settings['delivery_fee_mwk']) ?>"></label>
        <label class="form-group"><span class="form-label">Free Delivery Threshold</span><input type="number" step="0.01" name="free_delivery_threshold_mwk" class="form-control" value="<?= e((string) $settings['free_delivery_threshold_mwk']) ?>"></label>
        <label class="form-group"><span class="form-label">Tax Rate %</span><input type="number" step="0.01" name="tax_rate_percent" class="form-control" value="<?= e((string) $settings['tax_rate_percent']) ?>"></label>
        <div class="form-group">
          <span class="form-label">Payment Methods</span>
          <label style="display:block;margin:.35rem 0;"><input type="checkbox" name="cod_enabled" <?= !empty($settings['cod_enabled']) ? 'checked' : '' ?>> Cash on Delivery</label>
          <label style="display:block;margin:.35rem 0;"><input type="checkbox" name="bank_transfer_enabled" <?= !empty($settings['bank_transfer_enabled']) ? 'checked' : '' ?>> Bank Transfer</label>
          <label style="display:block;margin:.35rem 0;"><input type="checkbox" name="tnm_mpamba_enabled" <?= !empty($settings['tnm_mpamba_enabled']) ? 'checked' : '' ?>> TNM Mpamba</label>
          <label style="display:block;margin:.35rem 0;"><input type="checkbox" name="airtel_money_enabled" <?= !empty($settings['airtel_money_enabled']) ? 'checked' : '' ?>> Airtel Money</label>
          <label style="display:block;margin:.35rem 0;"><input type="checkbox" name="paychangu_enabled" <?= !empty($settings['paychangu_enabled']) ? 'checked' : '' ?>> PayChangu</label>
        </div>
        <div style="grid-column:1/-1;"><button type="submit" class="btn btn-primary">Save Shop Settings</button></div>
      </form>
    </section>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
