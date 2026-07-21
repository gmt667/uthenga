<?php
/**
 * Uthenga - Shop Order Detail
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/shop_helpers.php';

$pageTitle = 'Shop Order';
$activeNav = 'admin-shop';
require_once __DIR__ . '/includes/admin_header.php';

$orderId = (int) ($_GET['id'] ?? $_POST['order_id'] ?? 0);
$order = $orderId > 0 ? dbQueryOne('SELECT * FROM shop_orders WHERE id = ? LIMIT 1', [$orderId]) : null;
if (!$order) {
    echo '<div class="container dashboard-content-frame" style="padding-top:2rem;padding-bottom:3rem;"><div class="glass-panel" style="padding:1.25rem;"><h2>Order not found</h2><p class="text-muted">The selected order could not be loaded.</p></div></div>';
    require_once __DIR__ . '/includes/admin_footer.php';
    exit;
}

$message = '';
$error = '';
$riders = uthenga_shop_riders(false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrf()) {
    try {
        $status = (string) ($_POST['order_status'] ?? $order['order_status']);
        $paymentStatus = (string) ($_POST['payment_status'] ?? $order['payment_status']);
        $riderId = (int) ($_POST['assigned_rider_id'] ?? 0) ?: null;

        dbExecute(
            'UPDATE shop_orders SET order_status = ?, payment_status = ?, assigned_rider_id = ?, updated_at = NOW(), confirmed_at = CASE WHEN ? = "confirmed" AND confirmed_at IS NULL THEN NOW() ELSE confirmed_at END, prepared_at = CASE WHEN ? = "preparing" AND prepared_at IS NULL THEN NOW() ELSE prepared_at END, dispatched_at = CASE WHEN ? IN ("assigned_to_rider","out_for_delivery","delivered") AND dispatched_at IS NULL THEN NOW() ELSE dispatched_at END, delivered_at = CASE WHEN ? = "delivered" AND delivered_at IS NULL THEN NOW() ELSE delivered_at END, cancelled_at = CASE WHEN ? = "cancelled" AND cancelled_at IS NULL THEN NOW() ELSE cancelled_at END WHERE id = ?',
            [$status, $paymentStatus, $riderId, $status, $status, $status, $status, $status, $orderId]
        );

        if (uthenga_table_exists('shop_deliveries')) {
            $deliveryStatus = match ($status) {
                'confirmed', 'preparing', 'assigned_to_rider' => 'assigned',
                'out_for_delivery' => 'out_for_delivery',
                'delivered' => 'delivered',
                'cancelled' => 'cancelled',
                default => 'assigned',
            };
            dbExecute(
                'INSERT INTO shop_deliveries (order_id, rider_id, delivery_status, assigned_at, dispatched_at, delivered_at, completion_notes)
                 VALUES (?, ?, ?, NOW(), ?, ?, ?)
                 ON DUPLICATE KEY UPDATE rider_id = VALUES(rider_id), delivery_status = VALUES(delivery_status), dispatched_at = VALUES(dispatched_at), delivered_at = VALUES(delivered_at), completion_notes = VALUES(completion_notes), updated_at = NOW()',
                [
                    $orderId,
                    $riderId,
                    $deliveryStatus,
                    in_array($status, ['assigned_to_rider', 'out_for_delivery', 'delivered'], true) ? date('Y-m-d H:i:s') : null,
                    $status === 'delivered' ? date('Y-m-d H:i:s') : null,
                    $status === 'delivered' ? 'Marked delivered from admin detail screen.' : null,
                ]
            );
        }

        $order = dbQueryOne('SELECT * FROM shop_orders WHERE id = ? LIMIT 1', [$orderId]) ?: $order;
        $message = 'Order updated.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$items = uthenga_shop_order_items($orderId);
$payment = uthenga_shop_payment_by_order_id($orderId);
$delivery = uthenga_table_exists('shop_deliveries') ? dbQueryOne('SELECT * FROM shop_deliveries WHERE order_id = ? LIMIT 1', [$orderId]) : null;
$rider = !empty($delivery['rider_id']) ? dbQueryOne('SELECT * FROM delivery_riders WHERE id = ? LIMIT 1', [(int) $delivery['rider_id']]) : null;
?>
<div class="container dashboard-content-frame" style="padding-top:2rem;padding-bottom:3rem;">
  <div class="page-header">
    <div>
      <h1 class="page-title" style="display:flex;align-items:center;gap:0.55rem;"><?= admin_icon_svg('cart') ?><span>Order <?= e($order['order_number']) ?></span></h1>
      <p class="text-muted">Review items, delivery assignment, and update the order workflow.</p>
    </div>
    <div class="dashboard-head-meta">
      <a href="<?= BASE_URL ?>admin/shop.php" class="btn btn-secondary btn-sm">Back to Shop</a>
      <a href="<?= BASE_URL ?>shop-order.php?order=<?= urlencode((string) $order['order_number']) ?>" class="btn btn-primary btn-sm">Open Receipt</a>
    </div>
  </div>

  <?php if ($message !== '' || $error !== ''): ?>
    <div class="glass-panel" style="padding:1rem;margin-bottom:1rem;border-left:4px solid <?= $error !== '' ? 'var(--clr-red)' : 'var(--clr-green)' ?>;">
      <strong><?= e($error !== '' ? $error : $message) ?></strong>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-2 gap-3">
    <section class="shop-card">
      <div class="section-head"><div><h3>Customer & Delivery</h3><p class="text-xs text-muted">Customer details and delivery notes.</p></div></div>
      <div class="receipt-lines">
        <div class="receipt-line"><span>Customer</span><strong><?= e($order['customer_name']) ?></strong></div>
        <div class="receipt-line"><span>Phone</span><strong><?= e($order['customer_phone']) ?></strong></div>
        <div class="receipt-line"><span>Email</span><strong><?= e($order['customer_email']) ?></strong></div>
        <div class="receipt-line"><span>Address</span><strong><?= e($order['delivery_address']) ?></strong></div>
        <div class="receipt-line"><span>Preferred Time</span><strong><?= e($order['preferred_delivery_time'] ?? 'Any time') ?></strong></div>
      </div>
      <div class="shop-admin-grid" style="margin-top:1rem;grid-template-columns:1fr 1fr;">
        <div class="shop-admin-stat"><span>Status</span><strong><?= e($order['order_status']) ?></strong></div>
        <div class="shop-admin-stat"><span>Payment</span><strong><?= e($order['payment_status']) ?></strong></div>
        <div class="shop-admin-stat"><span>Total</span><strong><?= uthenga_shop_money((float) $order['total_amount']) ?></strong></div>
        <div class="shop-admin-stat"><span>Placed</span><strong><?= e($order['placed_at']) ?></strong></div>
      </div>
      <?php if ($payment): ?>
        <div class="glass-panel" style="padding:0.9rem;margin-top:1rem;">
          <div class="section-label">Gateway Record</div>
          <div class="receipt-lines" style="margin-top:.65rem;">
            <div class="receipt-line"><span>Provider</span><strong><?= e($payment['provider'] ?? $payment['payment_method'] ?? 'N/A') ?></strong></div>
            <div class="receipt-line"><span>Reference</span><strong><?= e($payment['payment_reference'] ?? 'N/A') ?></strong></div>
            <div class="receipt-line"><span>Gateway Status</span><strong><?= e($payment['payment_status'] ?? 'pending') ?></strong></div>
          </div>
        </div>
      <?php endif; ?>
    </section>

    <section class="shop-card">
      <div class="section-head"><div><h3>Update Order</h3><p class="text-xs text-muted">Adjust workflow and rider assignment.</p></div></div>
      <form method="post" class="grid" style="gap:1rem;">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
        <label class="form-group"><span class="form-label">Order Status</span><select class="form-control" name="order_status"><?php foreach (['pending','confirmed','preparing','assigned_to_rider','out_for_delivery','delivered','cancelled'] as $status): ?><option value="<?= e($status) ?>" <?= $order['order_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
        <label class="form-group"><span class="form-label">Payment Status</span><select class="form-control" name="payment_status"><?php foreach (['pending','authorized','paid','failed','refunded','partially_paid'] as $status): ?><option value="<?= e($status) ?>" <?= $order['payment_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
        <label class="form-group"><span class="form-label">Assign Rider</span><select class="form-control" name="assigned_rider_id"><option value="0">Unassigned</option><?php foreach ($riders as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) ($order['assigned_rider_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></label>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
      <?php if ($delivery): ?>
        <div style="margin-top:1rem;">
          <h3>Delivery Record</h3>
          <div class="receipt-lines">
            <div class="receipt-line"><span>Status</span><strong><?= e($delivery['delivery_status']) ?></strong></div>
            <div class="receipt-line"><span>Assigned</span><strong><?= e($delivery['assigned_at']) ?></strong></div>
            <div class="receipt-line"><span>Dispatched</span><strong><?= e($delivery['dispatched_at'] ?: 'Pending') ?></strong></div>
            <div class="receipt-line"><span>Delivered</span><strong><?= e($delivery['delivered_at'] ?: 'Pending') ?></strong></div>
          </div>
          <p class="text-muted" style="margin-top:.75rem;">Rider: <?= e($rider['name'] ?? 'Unassigned') ?> <?= !empty($rider['phone_number']) ? '· ' . e($rider['phone_number']) : '' ?></p>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <section class="shop-card">
    <div class="section-head"><div><h3>Order Items</h3><p class="text-xs text-muted">Purchased items and line totals.</p></div></div>
    <div class="table-responsive">
      <table class="admin-table">
        <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['product_name']) ?></td>
              <td><?= e($item['sku'] ?? '') ?></td>
              <td><?= (int) $item['quantity'] ?></td>
              <td><?= uthenga_shop_money((float) $item['unit_price']) ?></td>
              <td><?= uthenga_shop_money((float) $item['line_total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
